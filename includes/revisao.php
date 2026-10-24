<?php
/**
 * Revisões de acesso: cada revisão nasce de um UPLOAD (a base de pessoas,
 * ver includes/planilha.php), guarda o arquivo original e acompanha o
 * andamento direto no banco do Horacius. Nada vem do SharePoint.
 *
 * Mesma regra do painel do Power BI ("Dashboard Governança de ID & Acessos"),
 * conferida contra ele em 24/10/2026 — os números batem um a um:
 *   - ENTRA quem está na base com "Foi enviado para revisão? = SIM";
 *   - CONCLUÍDO = a pessoa tem ao menos uma revisão registrada no Horacius
 *     (ac_revisao) desde o início da revisão; PENDENTE = ainda nenhuma;
 *   - vínculo "0" (ou vazio) conta como Polo, como faz o BI.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/fontes.php';
require_once __DIR__ . '/planilha.php';

/** Vínculos na ordem do painel, e o grupo de acompanhamento de cada um. */
const VINCULOS = [
    'Empregado'    => 'Empregado, Terceirizado e Polo',
    'Terceirizado' => 'Empregado, Terceirizado e Polo',
    'Polo'         => 'Empregado, Terceirizado e Polo',
    'Professor'    => 'Professor',
];
/** Como o BI: vínculo que vem "0" ou vazio na base entra como Polo. */
const VINCULO_ALIAS = ['0' => 'Polo', '' => 'Polo'];

/** O andamento é relido do banco quando a última leitura passou disto. */
const ANDAMENTO_VALIDADE_MIN = 10;

/** Pasta dos arquivos enviados (dentro de dados/, que o .htaccess fecha). */
function pasta_revisao(int $id): string
{
    return dirname(DB_PATH) . '/revisoes/' . $id;
}

function revisao_estrutura(): void
{
    static $ok = false;
    if ($ok) return;
    $ok = true;
    $pdo = db();

    // v1 (ciclo único carregado do SharePoint) não tinha dado de produção:
    // as tabelas antigas saem e a estrutura por revisão entra no lugar.
    if ((int) $pdo->query('PRAGMA user_version')->fetchColumn() < 2) {
        $pdo->exec('DROP TABLE IF EXISTS ciclo; DROP TABLE IF EXISTS base_pessoas;
                    DROP TABLE IF EXISTS revisados; DROP TABLE IF EXISTS meta;');
        $pdo->exec('PRAGMA user_version = 2');
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS revisoes (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            nome           TEXT NOT NULL,
            inicio         TEXT NOT NULL,
            criado_por     INTEGER,
            criado_em      DATETIME DEFAULT (datetime('now','localtime')),
            andamento_em   DATETIME,
            andamento_erro TEXT
        );
        CREATE TABLE IF NOT EXISTS revisao_arquivos (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            revisao_id    INTEGER NOT NULL REFERENCES revisoes(id) ON DELETE CASCADE,
            tipo          TEXT NOT NULL,
            nome_original TEXT NOT NULL,
            arquivo       TEXT NOT NULL,
            tamanho       INTEGER NOT NULL,
            sha256        TEXT NOT NULL,
            linhas        INTEGER NOT NULL,
            enviado_por   INTEGER,
            enviado_em    DATETIME DEFAULT (datetime('now','localtime'))
        );
        CREATE TABLE IF NOT EXISTS base_pessoas (
            revisao_id    INTEGER NOT NULL REFERENCES revisoes(id) ON DELETE CASCADE,
            codigo        INTEGER NOT NULL,
            id_unico      TEXT,
            nome          TEXT,
            enviado       INTEGER NOT NULL DEFAULT 1,
            justificativa TEXT,
            vinculo       TEXT NOT NULL,
            linha         INTEGER,
            PRIMARY KEY (revisao_id, codigo)
        );
        CREATE TABLE IF NOT EXISTS revisados (
            revisao_id INTEGER NOT NULL REFERENCES revisoes(id) ON DELETE CASCADE,
            codigo     INTEGER NOT NULL,
            linhas     INTEGER NOT NULL,
            mantidos   INTEGER NOT NULL,
            revogados  INTEGER NOT NULL,
            ultima     TEXT,
            PRIMARY KEY (revisao_id, codigo)
        );
    ");
}

// ─── Revisões ────────────────────────────────────────────────────────────────

function revisoes_lista(): array
{
    revisao_estrutura();
    return db()->query("
        SELECT r.*, u.name AS criado_por_nome,
               (SELECT COUNT(*) FROM base_pessoas b WHERE b.revisao_id = r.id AND b.enviado = 1) AS pessoas
          FROM revisoes r LEFT JOIN users u ON u.id = r.criado_por
         ORDER BY r.inicio DESC, r.id DESC")->fetchAll();
}

function revisao(int $id): ?array
{
    revisao_estrutura();
    $st = db()->prepare('SELECT r.*, u.name AS criado_por_nome FROM revisoes r LEFT JOIN users u ON u.id = r.criado_por WHERE r.id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** A revisão pedida (?r=) ou, sem pedido, a mais recente. */
function revisao_escolhida(): ?array
{
    revisao_estrutura();
    $id = (int) ($_GET['r'] ?? 0);
    if ($id > 0 && ($r = revisao($id))) return $r;
    $id = (int) db()->query('SELECT id FROM revisoes ORDER BY inicio DESC, id DESC LIMIT 1')->fetchColumn();
    return $id ? revisao($id) : null;
}

function normalizar_vinculo(string $v): string
{
    $v = trim($v);
    if (array_key_exists($v, VINCULO_ALIAS)) return VINCULO_ALIAS[$v];
    foreach (array_keys(VINCULOS) as $conhecido) {
        if (strcasecmp($v, $conhecido) === 0) return $conhecido;
    }
    return $v;   // vínculo novo aparece com o próprio nome, nunca some
}

/**
 * Cria a revisão a partir dos arquivos já VALIDADOS (planilha_validar).
 * $arquivos: tipo => ['tmp' => caminho, 'nome' => nome original, 'linhas' => linhas mapeadas]
 */
function revisao_criar(string $nome, string $inicio, array $arquivos, int $usuario): int
{
    revisao_estrutura();
    $pdo = db();
    $pdo->beginTransaction();
    $pasta = null;
    try {
        $pdo->prepare('INSERT INTO revisoes (nome, inicio, criado_por) VALUES (?, ?, ?)')->execute([$nome, $inicio, $usuario]);
        $id = (int) $pdo->lastInsertId();
        $pasta = pasta_revisao($id);
        if (!is_dir($pasta) && !mkdir($pasta, 0770, true)) throw new RuntimeException('Não consegui criar a pasta dos arquivos da revisão.');

        foreach ($arquivos as $tipo => $a) {
            // Guarda o ORIGINAL, como veio: é a prova do que a revisão recebeu
            $ext = strtolower(pathinfo($a['nome'], PATHINFO_EXTENSION));
            $dest = $tipo . '_' . date('Ymd_His') . '.' . $ext;
            if (!copy($a['tmp'], $pasta . '/' . $dest)) throw new RuntimeException("Não consegui guardar o arquivo \"{$a['nome']}\".");
            $pdo->prepare('INSERT INTO revisao_arquivos (revisao_id, tipo, nome_original, arquivo, tamanho, sha256, linhas, enviado_por)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$id, $tipo, $a['nome'], $dest, filesize($a['tmp']), hash_file('sha256', $a['tmp']), count($a['linhas']), $usuario]);

            if ($tipo === 'base') {
                $ins = $pdo->prepare('INSERT OR REPLACE INTO base_pessoas (revisao_id, codigo, id_unico, nome, enviado, justificativa, vinculo, linha)
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                foreach ($a['linhas'] as $l) {
                    $ins->execute([$id, $l['codigo'], $l['id_unico'] ?? '', $l['nome'], $l['enviado'], $l['justificativa'] ?? '', $l['vinculo'], $l['_linha']]);
                }
            }
        }
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        $pdo->rollBack();
        if ($pasta && is_dir($pasta)) { array_map('unlink', glob($pasta . '/*') ?: []); @rmdir($pasta); }
        throw $e;
    }
}

function revisao_salvar(int $id, string $nome, string $inicio): void
{
    $antes = revisao($id);
    db()->prepare('UPDATE revisoes SET nome = ?, inicio = ? WHERE id = ?')->execute([$nome, $inicio, $id]);
    // Outra data de início muda quem conta como revisado: o andamento é relido.
    if ($antes && $antes['inicio'] !== $inicio) db()->prepare('UPDATE revisoes SET andamento_em = NULL WHERE id = ?')->execute([$id]);
}

function revisao_excluir(int $id): void
{
    $pasta = pasta_revisao($id);
    db()->prepare('DELETE FROM revisoes WHERE id = ?')->execute([$id]);   // cascata: arquivos, base, revisados
    if (is_dir($pasta)) { array_map('unlink', glob($pasta . '/*') ?: []); @rmdir($pasta); }
}

function revisao_arquivos(int $id): array
{
    $st = db()->prepare('SELECT a.*, u.name AS enviado_por_nome FROM revisao_arquivos a LEFT JOIN users u ON u.id = a.enviado_por
                          WHERE a.revisao_id = ? ORDER BY a.id');
    $st->execute([$id]);
    return $st->fetchAll();
}

function revisao_arquivo(int $arquivoId): ?array
{
    $st = db()->prepare('SELECT * FROM revisao_arquivos WHERE id = ?');
    $st->execute([$arquivoId]);
    return $st->fetch() ?: null;
}

// ─── Andamento (quem já revisou), direto do Horacius ─────────────────────────

/**
 * Relê no Horacius quem tem revisão registrada desde o início da revisão.
 * Mesmos joins da consulta do relatório de revisões executadas: uma revisão
 * de perfil que já não existe não conta, como não contava no arquivo.
 */
function andamento_atualizar(array $rev): int
{
    $st = horacius_db()->prepare("
        SELECT r.codpessoa             AS codigo,
               COUNT(*)                AS linhas,
               SUM(r.atitude = 1)      AS mantidos,
               SUM(r.atitude <> 1)     AS revogados,
               MAX(r.datahoraregistro) AS ultima
          FROM ac_revisao r
          JOIN perfilacesso p ON p.codperfil = r.codperfil
          JOIN acesso a       ON a.codacesso = p.codacesso
          JOIN ativo t        ON t.codativo  = a.codativo
         WHERE r.datahoraregistro >= ?
         GROUP BY r.codpessoa");
    $st->execute([$rev['inicio']]);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM revisados WHERE revisao_id = ?')->execute([$rev['id']]);
        $ins = $pdo->prepare('INSERT INTO revisados (revisao_id, codigo, linhas, mantidos, revogados, ultima) VALUES (?, ?, ?, ?, ?, ?)');
        $n = 0;
        while ($r = $st->fetch()) {
            $ins->execute([$rev['id'], (int) $r['codigo'], (int) $r['linhas'], (int) $r['mantidos'], (int) $r['revogados'], $r['ultima']]);
            $n++;
        }
        $pdo->prepare("UPDATE revisoes SET andamento_em = datetime('now','localtime'), andamento_erro = NULL WHERE id = ?")->execute([$rev['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return $n;
}

/** Relê o andamento se a última leitura venceu. Falha não derruba a tela: vira aviso. */
function andamento_garantir(array &$rev, bool $forcar = false): ?string
{
    if (!$forcar && $rev['andamento_em'] && (time() - strtotime($rev['andamento_em'])) < ANDAMENTO_VALIDADE_MIN * 60) return null;
    try {
        andamento_atualizar($rev);
        $erro = null;
    } catch (Throwable $e) {
        $erro = $e->getMessage();
        db()->prepare('UPDATE revisoes SET andamento_erro = ? WHERE id = ?')->execute([$erro, $rev['id']]);
    }
    $rev = revisao((int) $rev['id']);
    return $erro;
}

// ─── Números ─────────────────────────────────────────────────────────────────

/** Concluído/pendente por vínculo, só de quem foi enviado para revisão. */
function painel_por_vinculo(int $revId): array
{
    $st = db()->prepare("
        SELECT b.vinculo,
               SUM(CASE WHEN r.codigo IS NOT NULL THEN 1 ELSE 0 END) AS concluido,
               SUM(CASE WHEN r.codigo IS NULL     THEN 1 ELSE 0 END) AS pendente
          FROM base_pessoas b
          LEFT JOIN revisados r ON r.revisao_id = b.revisao_id AND r.codigo = b.codigo
         WHERE b.revisao_id = ? AND b.enviado = 1
         GROUP BY b.vinculo");
    $st->execute([$revId]);

    $porVinculo = [];
    foreach (array_keys(VINCULOS) as $v) $porVinculo[$v] = ['concluido' => 0, 'pendente' => 0];
    foreach ($st->fetchAll() as $r) $porVinculo[$r['vinculo']] = ['concluido' => (int) $r['concluido'], 'pendente' => (int) $r['pendente']];
    return $porVinculo;
}

/** Soma por grupo de acompanhamento (os dois cartões do topo). */
function painel_por_grupo(array $porVinculo): array
{
    $g = [];
    foreach ($porVinculo as $v => $n) {
        $grupo = VINCULOS[$v] ?? 'Outros vínculos';
        $g[$grupo]['concluido'] = ($g[$grupo]['concluido'] ?? 0) + $n['concluido'];
        $g[$grupo]['pendente']  = ($g[$grupo]['pendente'] ?? 0) + $n['pendente'];
    }
    return array_filter($g, fn($x) => $x['concluido'] + $x['pendente'] > 0);
}

function pct(int $parte, int $total, int $casas = 1): string
{
    return number_format($total > 0 ? 100 * $parte / $total : 0, $casas, ',', '.');
}

/**
 * Pessoas da base com o status calculado. Filtros: vinculo, grupo, status
 * ('pendente' | 'concluido' | 'fora' = não enviada), q (nome/código/id).
 */
function base_pessoas(int $revId, array $f = []): array
{
    $sql = "SELECT b.*, r.linhas, r.mantidos, r.revogados, r.ultima,
                   CASE WHEN b.enviado = 0 THEN 'fora' WHEN r.codigo IS NULL THEN 'pendente' ELSE 'concluido' END AS status
              FROM base_pessoas b
              LEFT JOIN revisados r ON r.revisao_id = b.revisao_id AND r.codigo = b.codigo
             WHERE b.revisao_id = ?";
    $p = [$revId];
    if (($f['vinculo'] ?? '') !== '') { $sql .= ' AND b.vinculo = ?'; $p[] = $f['vinculo']; }
    if (($f['grupo'] ?? '') !== '') {
        $vs = array_keys(array_filter(VINCULOS, fn($g) => $g === $f['grupo']));
        $sql .= ' AND b.vinculo IN (' . implode(',', array_fill(0, max(1, count($vs)), '?')) . ')';
        array_push($p, ...($vs ?: ['—']));
    }
    if (($f['q'] ?? '') !== '') {
        $sql .= ' AND (b.nome LIKE ? OR CAST(b.codigo AS TEXT) LIKE ? OR b.id_unico LIKE ?)';
        array_push($p, "%{$f['q']}%", "%{$f['q']}%", "%{$f['q']}%");
    }
    $sql .= ' ORDER BY b.vinculo, b.nome';
    $st = db()->prepare($sql);
    $st->execute($p);
    $linhas = $st->fetchAll();
    if (($f['status'] ?? '') !== '') $linhas = array_values(array_filter($linhas, fn($l) => $l['status'] === $f['status']));
    return $linhas;
}
