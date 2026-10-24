<?php
/**
 * Base de funcionários (hierarquia): quem é o líder de cada pessoa e o e-mail
 * dele. Vem por UPLOAD da BaseFull (CSV diário do RH, ~40 MB; aceita .zip) ou
 * de qualquer planilha com as mesmas colunas (modelo em PLANILHAS['funcionarios']).
 *
 * A BaseFull traz HISTÓRICO: o mesmo CPF aparece em várias linhas (admissões,
 * vínculos antigos). Por isso guardamos todas as linhas e a resolução prefere a
 * linha ATIVA e, entre ativas, a admissão mais recente — tanto para achar a
 * pessoa (pelo CPF) quanto o líder (pela matrícula do líder).
 *
 * E-mail: o corporativo (EML_FUNCIONARIO_EMPRESA); o outro só entra se não for
 * de provedor pessoal — cobrança não vai para gmail sem alguém decidir.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/planilha.php';

const DOMINIOS_PESSOAIS = ['gmail.com', 'hotmail.com', 'outlook.com', 'live.com', 'yahoo.com', 'yahoo.com.br',
                           'icloud.com', 'bol.com.br', 'uol.com.br', 'terra.com.br', 'msn.com', 'globo.com', 'ig.com.br'];

function funcionarios_estrutura(): void
{
    static $ok = false;
    if ($ok) return;
    $ok = true;
    db()->exec("
        CREATE TABLE IF NOT EXISTS funcionarios (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            cpf             TEXT,
            matricula       TEXT,
            nome            TEXT,
            email           TEXT,
            email_alt       TEXT,
            login_ad        TEXT,
            matricula_lider TEXT,
            nome_lider      TEXT,
            ativo           INTEGER NOT NULL DEFAULT 0,
            admissao        TEXT
        );
        CREATE INDEX IF NOT EXISTS idx_func_cpf ON funcionarios(cpf);
        CREATE INDEX IF NOT EXISTS idx_func_mat ON funcionarios(matricula);
        CREATE TABLE IF NOT EXISTS funcionarios_cargas (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            nome_original TEXT NOT NULL,
            arquivo       TEXT NOT NULL,
            tamanho       INTEGER NOT NULL,
            sha256        TEXT NOT NULL,
            linhas        INTEGER NOT NULL,
            ativos        INTEGER NOT NULL,
            enviado_por   INTEGER,
            enviado_em    DATETIME DEFAULT (datetime('now','localtime'))
        );
    ");
}

function so_digitos(string $s, int $pad = 0): string
{
    $d = preg_replace('/\D/', '', $s);
    return $pad && $d !== '' ? str_pad($d, $pad, '0', STR_PAD_LEFT) : $d;
}

function email_pessoal(string $email): bool
{
    $dom = strtolower(substr(strrchr($email, '@') ?: '', 1));
    return in_array($dom, DOMINIOS_PESSOAIS, true);
}

/** Melhor e-mail institucional de uma linha de funcionário ('' se só houver pessoal). */
function email_institucional(array $f): string
{
    foreach ([$f['email'] ?? '', $f['email_alt'] ?? ''] as $e) {
        $e = strtolower(trim($e));
        if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL) && !email_pessoal($e)) return $e;
    }
    return '';
}

function pasta_funcionarios(): string
{
    return dirname(DB_PATH) . '/funcionarios';
}

/** Última carga (ou null). */
function funcionarios_carga_atual(): ?array
{
    funcionarios_estrutura();
    $r = db()->query('SELECT c.*, u.name AS enviado_por_nome FROM funcionarios_cargas c LEFT JOIN users u ON u.id = c.enviado_por ORDER BY c.id DESC LIMIT 1')->fetch();
    return $r ?: null;
}

function funcionarios_cargas(): array
{
    funcionarios_estrutura();
    return db()->query('SELECT c.*, u.name AS enviado_por_nome FROM funcionarios_cargas c LEFT JOIN users u ON u.id = c.enviado_por ORDER BY c.id DESC')->fetchAll();
}

/**
 * Importa a base (substitui a anterior; o arquivo original de cada carga fica
 * guardado). Aceita .csv, .xlsx ou .zip com um .csv/.xlsx dentro.
 * @return array{linhas:int, ativos:int}
 */
function funcionarios_importar(string $tmp, string $nomeOriginal, int $usuario): array
{
    funcionarios_estrutura();
    $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
    $dados = $tmp; $nomeDados = $nomeOriginal; $extraido = null;

    if ($ext === 'zip') {
        $z = new ZipArchive();
        if ($z->open($tmp) !== true) throw new RuntimeException('O .zip não abriu.');
        $alvo = null;
        for ($i = 0; $i < $z->numFiles; $i++) {
            $n = $z->getNameIndex($i);
            if (preg_match('/\.(csv|xlsx)$/i', $n) && !str_starts_with(basename($n), '.')) { $alvo = $n; break; }
        }
        if ($alvo === null) { $z->close(); throw new RuntimeException('O .zip não tem nenhum .csv ou .xlsx dentro.'); }
        $extraido = tempnam(sys_get_temp_dir(), 'func');
        file_put_contents($extraido, $z->getFromName($alvo));
        $z->close();
        $dados = $extraido; $nomeDados = basename($alvo);
    }

    try {
        $def  = PLANILHAS['funcionarios']['colunas'];
        $pdo  = db();
        $ins  = null; $mapa = null; $n = 0; $ativos = 0;
        $pdo->beginTransaction();
        $pdo->exec('DELETE FROM funcionarios');

        $grava = function (array $l) use (&$ins, &$mapa, &$n, &$ativos, $pdo, $def) {
            if ($mapa === null) {
                $mapa = [];
                foreach ($def as $chave => $col) {
                    foreach ($col['apelidos'] as $ap) if (array_key_exists($ap, $l)) { $mapa[$chave] = $ap; break; }
                    if (!isset($mapa[$chave]) && $col['obrigatoria']) throw new RuntimeException('Falta a coluna obrigatória "' . $col['cabecalho'] . '".');
                }
                $ins = $pdo->prepare('INSERT INTO funcionarios (cpf, matricula, nome, email, email_alt, login_ad, matricula_lider, nome_lider, ativo, admissao)
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            }
            $v = fn($k) => isset($mapa[$k]) ? trim((string) ($l[$mapa[$k]] ?? '')) : '';
            $cpf = so_digitos($v('cpf'), 11);
            $mat = ltrim(so_digitos($v('matricula')), '0');
            if ($cpf === '' && $mat === '') return;
            $atv = in_array(mb_strtoupper($v('ativo')), ['S', 'SIM', '1', 'ATIVO', 'A'], true) || $v('ativo') === '' ? 1 : 0;
            $ins->execute([$cpf, $mat, $v('nome'), strtolower($v('email')), strtolower($v('email_alt')), $v('login_ad'),
                           ltrim(so_digitos($v('matricula_lider')), '0'), $v('nome_lider'), $atv, data_iso($v('admissao'))]);
            $n++; $ativos += $atv;
        };

        if (strtolower(pathinfo($nomeDados, PATHINFO_EXTENSION)) === 'xlsx') {
            foreach (xlsx_linhas($dados) as $l) $grava($l);
        } else {
            csv_percorrer($dados, $grava);   // 40 MB: linha a linha, sem carregar tudo na memória
        }
        if ($n === 0) throw new RuntimeException('Nenhuma linha válida no arquivo.');

        // Guarda o ORIGINAL enviado (o .zip, se veio .zip)
        $pasta = pasta_funcionarios();
        if (!is_dir($pasta) && !mkdir($pasta, 0770, true)) throw new RuntimeException('Não consegui criar a pasta da base de funcionários.');
        $dest = 'funcionarios_' . date('Ymd_His') . '.' . $ext;
        if (!copy($tmp, $pasta . '/' . $dest)) throw new RuntimeException('Não consegui guardar o arquivo enviado.');
        $pdo->prepare('INSERT INTO funcionarios_cargas (nome_original, arquivo, tamanho, sha256, linhas, ativos, enviado_por) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$nomeOriginal, $dest, filesize($tmp), hash_file('sha256', $tmp), $n, $ativos, $usuario]);
        $pdo->commit();
        return ['linhas' => $n, 'ativos' => $ativos];
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    } finally {
        if ($extraido) @unlink($extraido);
    }
}

/** DD/MM/AAAA, AAAA-MM-DD ou serial do Excel → AAAA-MM-DD ('' se não entender). */
function data_iso(string $s): string
{
    $s = trim($s);
    if ($s === '') return '';
    if (preg_match('#^(\d{2})/(\d{2})/(\d{4})#', $s, $m)) return "{$m[3]}-{$m[2]}-{$m[1]}";
    if (preg_match('#^(\d{4})-(\d{2})-(\d{2})#', $s, $m)) return "{$m[1]}-{$m[2]}-{$m[3]}";
    if (is_numeric($s) && $s > 20000 && $s < 80000) return date('Y-m-d', (int) (((float) $s - 25569) * 86400));
    return '';
}

// ─── Resolução da hierarquia ─────────────────────────────────────────────────

/** Linha preferida de um funcionário (ativa, admissão mais recente). */
function funcionario_por(string $campo, string $valor): ?array
{
    if ($valor === '') return null;
    $col = $campo === 'cpf' ? 'cpf' : 'matricula';
    // Entre as linhas ativas, a que TEM líder vence (o mesmo CPF pode ter dois vínculos ativos)
    $st = db()->prepare("SELECT * FROM funcionarios WHERE {$col} = ?
                          ORDER BY ativo DESC, (matricula_lider <> '') DESC, admissao DESC, id DESC LIMIT 1");
    $st->execute([$valor]);
    return $st->fetch() ?: null;
}

/**
 * Líder de uma pessoa, a partir do CPF.
 * @return array{ok:bool, motivo:?string, nome:string, email:string, cpf:string, matricula:string}
 */
function lider_de_cpf(string $cpf): array
{
    $vazio = ['ok' => false, 'motivo' => null, 'nome' => '', 'email' => '', 'cpf' => '', 'matricula' => ''];
    $cpf = so_digitos($cpf, 11);
    if ($cpf === '' || $cpf === '00000000000') return ['motivo' => 'Sem CPF na base da revisão'] + $vazio;
    $f = funcionario_por('cpf', $cpf);
    if (!$f) return ['motivo' => 'CPF não está na base de funcionários'] + $vazio;
    if ($f['matricula_lider'] === '') return ['motivo' => 'Sem líder na base de funcionários', 'nome' => $f['nome_lider']] + $vazio;

    $l = funcionario_por('matricula', $f['matricula_lider']);
    $nome = ($l['nome'] ?? '') ?: $f['nome_lider'];
    if (!$l) return ['motivo' => 'Líder não está na base de funcionários', 'nome' => $nome, 'matricula' => $f['matricula_lider']] + $vazio;
    $email = email_institucional($l);
    if ($email === '') return ['motivo' => 'Líder sem e-mail corporativo', 'nome' => $nome, 'cpf' => $l['cpf'], 'matricula' => $l['matricula']] + $vazio;
    return ['ok' => true, 'motivo' => null, 'nome' => $nome, 'email' => $email, 'cpf' => $l['cpf'], 'matricula' => $l['matricula']];
}

/**
 * Líder de uma pessoa da revisão (linha de base_pessoas): primeiro o que veio
 * na própria base da revisão (colunas opcionais de líder), depois a base de
 * funcionários pelo CPF. Devolve o mesmo formato de lider_de_cpf() + 'fonte'.
 */
function lider_da_pessoa(array $p): array
{
    $email = strtolower(trim($p['lider_email'] ?? ''));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => true, 'motivo' => null, 'nome' => trim($p['lider_nome'] ?? '') ?: $email, 'email' => $email,
                'cpf' => '', 'matricula' => '', 'fonte' => 'base da revisão'];
    }
    return lider_de_cpf((string) ($p['id_unico'] ?? '')) + ['fonte' => 'base de funcionários'];
}

/**
 * Líder do líder: sobe um nível na base de funcionários a partir do CPF ou da
 * matrícula do líder (quando o líder veio da base da revisão, só pelo e-mail).
 */
function lider_do_lider(array $lider): array
{
    $vazio = ['ok' => false, 'motivo' => 'Líder do líder não encontrado', 'nome' => '', 'email' => '', 'cpf' => '', 'matricula' => ''];
    if (!$lider['ok']) return $vazio;
    if ($lider['cpf'] !== '') return lider_de_cpf($lider['cpf']);
    // Líder informado só por e-mail: acha a linha dele pelo e-mail
    $st = db()->prepare("SELECT cpf FROM funcionarios WHERE email = ? OR email_alt = ? ORDER BY ativo DESC LIMIT 1");
    $st->execute([$lider['email'], $lider['email']]);
    $cpf = (string) $st->fetchColumn();
    return $cpf !== '' ? lider_de_cpf($cpf) : $vazio;
}
