<?php
/**
 * Cobranças da revisão: modelos (topo, corpo, rodapé), o PLANO de uma cobrança
 * (quem recebe, com quais pessoas, quem vai em cópia) e o envio em lotes, com
 * tudo registrado — cada e-mail, para quem, quais pessoas, quando, e o erro.
 *
 * Destinatário (a pergunta do assistente):
 *   'lider'    → o líder imediato de cada pessoa pendente (opcional: o líder
 *                dele em cópia);
 *   'superior' → o líder DO líder (escalonamento; opcional: o líder imediato
 *                em cópia). A tabela de pendentes ganha a coluna "Líder".
 * Pessoas sem líder resolvido não somem: ficam na lista de "sem destinatário"
 * da prévia e da cobrança, para tratar à parte.
 */
require_once __DIR__ . '/revisao.php';
require_once __DIR__ . '/funcionarios.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/delegacao.php';

/** Variáveis que um modelo pode usar (para a tela de modelos). */
const VARIAVEIS_COBRANCA = [
    '{{nome}}'             => 'Nome de quem recebe o e-mail',
    '{{qtd_pendentes}}'    => 'Quantas pessoas pendentes estão neste e-mail',
    '{{pendentes}}'        => 'Lista com o nome das pessoas pendentes',
    '{{tabela_pendentes}}' => 'Tabela das pessoas pendentes (nome, vínculo e, no escalonamento, o líder)',
    '{{prazo}}'            => 'Prazo informado na cobrança (texto livre, ex.: "hoje, 21/10/2026")',
    '{{revisao}}'          => 'Nome da revisão',
    '{{link_revisao}}'     => 'Link do portal do Horacius para revisar',
];

function cobranca_estrutura(): void
{
    static $ok = false;
    if ($ok) return;
    $ok = true;
    $pdo = db();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS modelos_cobranca (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            nome          TEXT NOT NULL,
            assunto       TEXT NOT NULL,
            topo          TEXT NOT NULL DEFAULT '',
            rodape        TEXT NOT NULL DEFAULT '',
            corpo         TEXT NOT NULL,
            atualizado_em DATETIME DEFAULT (datetime('now','localtime'))
        );
        CREATE TABLE IF NOT EXISTS cobrancas (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            revisao_id  INTEGER NOT NULL REFERENCES revisoes(id) ON DELETE CASCADE,
            modelo_id   INTEGER,
            modelo_nome TEXT,
            remetente   TEXT NOT NULL,
            alvo        TEXT NOT NULL,
            copia       TEXT NOT NULL DEFAULT '',
            filtros     TEXT NOT NULL DEFAULT '{}',
            prazo       TEXT NOT NULL DEFAULT '',
            sem_destino INTEGER NOT NULL DEFAULT 0,
            sem_destino_json TEXT NOT NULL DEFAULT '[]',
            status      TEXT NOT NULL DEFAULT 'enviando',
            criado_por  INTEGER,
            criado_em   DATETIME DEFAULT (datetime('now','localtime')),
            concluido_em DATETIME
        );
        CREATE TABLE IF NOT EXISTS cobranca_envios (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            cobranca_id INTEGER NOT NULL REFERENCES cobrancas(id) ON DELETE CASCADE,
            para_email  TEXT NOT NULL,
            para_nome   TEXT NOT NULL,
            cc          TEXT NOT NULL DEFAULT '',
            assunto     TEXT NOT NULL,
            corpo       TEXT NOT NULL,
            topo        TEXT NOT NULL DEFAULT '',
            rodape      TEXT NOT NULL DEFAULT '',
            qtd         INTEGER NOT NULL,
            status      TEXT NOT NULL DEFAULT 'pendente',
            erro        TEXT,
            enviado_em  DATETIME
        );
        CREATE TABLE IF NOT EXISTS cobranca_pessoas (
            envio_id   INTEGER NOT NULL REFERENCES cobranca_envios(id) ON DELETE CASCADE,
            revisao_id INTEGER NOT NULL,
            codigo     INTEGER NOT NULL
        );
        CREATE INDEX IF NOT EXISTS idx_cobpes ON cobranca_pessoas(revisao_id, codigo);
    ");
    if (!in_array('cco', array_column($pdo->query('PRAGMA table_info(cobranca_envios)')->fetchAll(), 'name'), true)) {
        $pdo->exec("ALTER TABLE cobranca_envios ADD COLUMN cco TEXT NOT NULL DEFAULT ''");
    }
    // Delegados de gestão do destinatário (Horacius): vão JUNTO no "Para"
    if (!in_array('delegados', array_column($pdo->query('PRAGMA table_info(cobranca_envios)')->fetchAll(), 'name'), true)) {
        $pdo->exec("ALTER TABLE cobranca_envios ADD COLUMN delegados TEXT NOT NULL DEFAULT ''");
    }
    if (!in_array('nao_enviar', array_column($pdo->query('PRAGMA table_info(cobrancas)')->fetchAll(), 'name'), true)) {
        $pdo->exec("ALTER TABLE cobrancas ADD COLUMN nao_enviar TEXT NOT NULL DEFAULT ''");   // um e-mail por linha
    }

    // Modelos iniciais: o texto das cobranças da revisão de outubro/2026 feitas
    // no Mala Direta, com as datas e a lista trocadas por variáveis.
    if ((int) $pdo->query('SELECT COUNT(*) FROM modelos_cobranca')->fetchColumn() === 0) {
        $roxo = 'color: rgb(132, 63, 161);';
        $ins = $pdo->prepare('INSERT INTO modelos_cobranca (nome, assunto, topo, rodape, corpo) VALUES (?, ?, ?, ?, ?)');
        $ins->execute(['Revisão vencida — evite revogações', '[LEMBRETE] - Horacius revisão de acessos vencida', 'Topo 02.jpg', 'Roda pe 1.png',
            '<p>Prezado(a) <span style="' . $roxo . '"><strong>{{nome}}</strong></span>, bom dia!</p>'
          . '<p>Identificamos que ainda existe <span style="' . $roxo . '"><strong>pendência</strong></span> de revisão de acessos em sua equipe.</p>'
          . '<p>Para evitar a revogação automática dos acessos, conforme critérios de auditoria, por favor realize a revisão até <span style="' . $roxo . '"><strong>{{prazo}}</strong></span>.</p>'
          . '<p><strong><span style="' . $roxo . '">Contamos com sua ação imediata.</span></strong></p>'
          . '<p>➡️ Realize a revisão acessando o link: <a href="{{link_revisao}}">Portal Horacius - Revisão</a></p>'
          . '<p><strong><span style="' . $roxo . '">⚠️ Pessoas com revisão pendente ({{qtd_pendentes}}):</span></strong></p>'
          . '<p>{{pendentes}}</p>'
          . '<p><strong><span style="' . $roxo . '">Atenção:</span></strong></p>'
          . '<p><em><strong>Caso não seja um(a) educador(a) que se reporta a você, acione o time responsável por esse ajuste via Service Desk Ânima: Jornada do Educador para educadores(as) do Ecossistema, ou Transformação Digital para colaboradores de empresas terceirizadas.</strong></em></p>'
          . '<p>Em caso de dúvidas, contate o time de Segurança da Informação: <span style="' . $roxo . '"><strong><em>seginfo@animaeducacao.com.br</em></strong></span></p>'
          . '<p><strong><span style="' . $roxo . '">Contamos com você!</span></strong></p>']);
        $ins->execute(['Escalonamento — líder do gestor', '[ESCALONAMENTO] Revisão de acessos pendente na sua estrutura', 'Topo 02.jpg', 'Roda pe 1.png',
            '<p>Prezado(a) <span style="' . $roxo . '"><strong>{{nome}}</strong></span>,</p>'
          . '<p>A revisão de acessos <strong>{{revisao}}</strong> ainda tem <span style="' . $roxo . '"><strong>{{qtd_pendentes}} pessoa(s) pendente(s)</strong></span> em equipes da sua estrutura, mesmo após as cobranças aos líderes diretos.</p>'
          . '<p>Pedimos seu apoio para que a revisão seja concluída até <span style="' . $roxo . '"><strong>{{prazo}}</strong></span>. Depois do prazo, os acessos pendentes serão revogados, por critério de auditoria e segurança da informação.</p>'
          . '{{tabela_pendentes}}'
          . '<p>➡️ Revisão no portal: <a href="{{link_revisao}}">Portal Horacius - Revisão</a></p>'
          . '<p>Em caso de dúvidas, contate o time de Segurança da Informação: <span style="' . $roxo . '"><strong><em>seginfo@animaeducacao.com.br</em></strong></span></p>'
          . '<p><strong><span style="' . $roxo . '">Contamos com você!</span></strong></p>']);
    }
}

// ─── Modelos ─────────────────────────────────────────────────────────────────

function modelos_cobranca(): array
{
    cobranca_estrutura();
    return db()->query('SELECT * FROM modelos_cobranca ORDER BY nome')->fetchAll();
}

function modelo_cobranca(int $id): ?array
{
    cobranca_estrutura();
    $st = db()->prepare('SELECT * FROM modelos_cobranca WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

// ─── Variáveis ───────────────────────────────────────────────────────────────

/** Substitui as variáveis. $pessoas: linhas de base_pessoas (+ '_lider' no escalonamento). */
function cobranca_aplicar(string $texto, array $ctx, array $pessoas, bool $comLider, bool $html = true): string
{
    $h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $lista = '<ul style="margin:4px 0;padding-left:20px;">' . implode('', array_map(fn($p) => '<li>' . $h($p['nome']) . '</li>', $pessoas)) . '</ul>';

    $th = 'style="background:#77206D;color:#ffffff;padding:6px 8px;border:1px solid #d9c7d6;text-align:left;font-size:9pt;"';
    $td = 'style="padding:6px 8px;border:1px solid #d9c7d6;font-size:9pt;vertical-align:top;"';
    $tab = '<table cellspacing="0" cellpadding="0" style="border-collapse:collapse;width:100%;margin:8px 0;"><tr>'
         . '<th ' . $th . '>Pessoa</th><th ' . $th . '>Vínculo</th>' . ($comLider ? '<th ' . $th . '>Líder</th>' : '') . '</tr>';
    foreach ($pessoas as $p) {
        $tab .= '<tr><td ' . $td . '>' . $h($p['nome']) . '</td><td ' . $td . '>' . $h($p['vinculo']) . '</td>'
              . ($comLider ? '<td ' . $td . '>' . $h($p['_lider'] ?? '') . '</td>' : '') . '</tr>';
    }
    $tab .= '</table>';

    $vars = [
        '{{nome}}'             => $h($ctx['nome'] ?? ''),
        '{{qtd_pendentes}}'    => (string) count($pessoas),
        '{{pendentes}}'        => $lista,
        '{{tabela_pendentes}}' => $tab,
        '{{prazo}}'            => $h($ctx['prazo'] ?? ''),
        '{{revisao}}'          => $h($ctx['revisao'] ?? ''),
        '{{link_revisao}}'     => $h(link_revisao()),
    ];
    if (!$html) {   // assunto: texto puro
        $vars['{{pendentes}}'] = $vars['{{tabela_pendentes}}'] = (string) count($pessoas);
        $vars = array_map(fn($v) => html_entity_decode(strip_tags($v), ENT_QUOTES, 'UTF-8'), $vars);
    }
    return strtr($texto, $vars);
}

// ─── Plano (prévia) ──────────────────────────────────────────────────────────

/**
 * Monta quem recebe o quê, sem enviar nada.
 * $o: revisao_id, vinculo, grupo, alvo ('lider'|'superior'), copiar_outro (bool),
 *     tipo_copia_outro ('cc'|'cco'), copia_fixa (string), tipo_copia_fixa ('cc'|'cco'),
 *     so_nao_cobrados (bool)
 *     nao_enviar (array de e-mails — vale só para ESTA cobrança): destinatário
 *     na lista vira "sem destinatário"; endereço na lista sai de Cc/Cco.
 * @return array{grupos: array, sem_destino: array, total: int}
 */
function cobranca_planejar(array $o): array
{
    cobranca_estrutura();
    $pessoas = base_pessoas((int) $o['revisao_id'], [
        'vinculo' => $o['vinculo'] ?? '', 'grupo' => $o['grupo'] ?? '', 'status' => 'pendente',
    ]);
    if (!empty($o['so_nao_cobrados'])) {
        $cob = pessoas_cobradas((int) $o['revisao_id']);
        $pessoas = array_values(array_filter($pessoas, fn($p) => !isset($cob[(int) $p['codigo']])));
    }
    [$fixa] = enderecos_validos((string) ($o['copia_fixa'] ?? ''));
    $naoEnviar = array_flip($o['nao_enviar'] ?? []);
    $bloqueado = fn(string $e) => isset($naoEnviar[strtolower(trim($e))]);

    $grupos = []; $sem = [];
    foreach ($pessoas as $p) {
        $lider = lider_da_pessoa($p);
        if ($o['alvo'] === 'superior') {
            $sup = lider_do_lider($lider);
            $dest = $sup; $outro = $lider;
            if (!$lider['ok']) $dest = ['ok' => false, 'motivo' => $lider['motivo']] + $sup;
        } else {
            $dest = $lider; $outro = !empty($o['copiar_outro']) ? lider_do_lider($lider) : ['ok' => false];
        }
        if (!$dest['ok']) {
            $sem[] = ['codigo' => (int) $p['codigo'], 'nome' => $p['nome'], 'vinculo' => $p['vinculo'], 'motivo' => $dest['motivo'] ?? 'Sem destinatário'];
            continue;
        }
        if ($bloqueado($dest['email'])) {
            $sem[] = ['codigo' => (int) $p['codigo'], 'nome' => $p['nome'], 'vinculo' => $p['vinculo'], 'motivo' => 'Está na lista "não enviar" desta cobrança'];
            continue;
        }
        $k = $dest['email'];
        if (!isset($grupos[$k])) {
            // Delegação de gestão vigente no Horacius: o delegado recebe junto
            $dels = array_values(array_filter(delegados_de($dest), fn($d) => !$bloqueado($d['email'])));
            $grupos[$k] = ['email' => $dest['email'], 'nome' => $dest['nome'], 'delegados' => $dels, 'cc' => [], 'cco' => [], 'pessoas' => []];
        }
        if (!empty($o['copiar_outro']) && ($outro['ok'] ?? false)) {
            $grupos[$k][($o['tipo_copia_outro'] ?? 'cc') === 'cco' ? 'cco' : 'cc'][] = $outro['email'];
        }
        $grupos[$k]['pessoas'][] = $p + ['_lider' => $lider['nome'] ?? ''];
    }
    $bloqueados = 0;
    foreach ($grupos as &$g) {
        // Cópia fixa no tipo escolhido; tira repetidos, o próprio destinatário e os bloqueados
        $g[(($o['tipo_copia_fixa'] ?? 'cc') === 'cco') ? 'cco' : 'cc'] = array_merge($g[(($o['tipo_copia_fixa'] ?? 'cc') === 'cco') ? 'cco' : 'cc'], $fixa);
        foreach (['cc', 'cco'] as $tipo) {
            $bloqueados += count(array_filter(array_unique($g[$tipo]), $bloqueado));
            $para = array_merge([$g['email']], array_column($g['delegados'], 'email'));   // quem está no "Para" não repete em cópia
            $g[$tipo] = array_values(array_unique(array_filter($g[$tipo], fn($e) => !in_array($e, $para, true) && !$bloqueado($e))));
        }
        $g['cco'] = array_values(array_diff($g['cco'], $g['cc']));
        usort($g['pessoas'], fn($a, $b) => strcmp($a['nome'], $b['nome']));
    }
    unset($g);
    uasort($grupos, fn($a, $b) => count($b['pessoas']) <=> count($a['pessoas']) ?: strcmp($a['nome'], $b['nome']));
    return ['grupos' => array_values($grupos), 'sem_destino' => $sem, 'total' => count($pessoas), 'copias_bloqueadas' => $bloqueados,
            'com_delegado' => count(array_filter($grupos, fn($g) => $g['delegados']))];
}

/** [válidos, inválidos] de uma lista "a@b.com; c@d.com". */
function enderecos_validos(string $bruto): array
{
    $ok = []; $ruins = [];
    foreach (preg_split('/[;,\s]+/', $bruto) ?: [] as $e) {
        $e = strtolower(trim($e));
        if ($e === '') continue;
        filter_var($e, FILTER_VALIDATE_EMAIL) ? $ok[] = $e : $ruins[] = $e;
    }
    return [array_values(array_unique($ok)), $ruins];
}

// ─── Registro e envio ────────────────────────────────────────────────────────

/** Grava a cobrança com um envio 'pendente' por destinatário (já renderizado). */
function cobranca_criar(array $o, array $plano, array $modelo, int $usuario): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO cobrancas (revisao_id, modelo_id, modelo_nome, remetente, alvo, copia, filtros, prazo, sem_destino, sem_destino_json, criado_por, nao_enviar)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$o['revisao_id'], $modelo['id'], $modelo['nome'], $o['remetente'], $o['alvo'], (string) ($o['copia_fixa'] ?? ''),
                       json_encode(['vinculo' => $o['vinculo'] ?? '', 'grupo' => $o['grupo'] ?? '', 'copiar_outro' => !empty($o['copiar_outro']),
                                    'tipo_copia_outro' => $o['tipo_copia_outro'] ?? 'cc', 'tipo_copia_fixa' => $o['tipo_copia_fixa'] ?? 'cc',
                                    'so_nao_cobrados' => !empty($o['so_nao_cobrados'])], JSON_UNESCAPED_UNICODE),
                       (string) ($o['prazo'] ?? ''), count($plano['sem_destino']),
                       json_encode($plano['sem_destino'], JSON_UNESCAPED_UNICODE), $usuario, implode("
", $o['nao_enviar'] ?? [])]);
        $id  = (int) $pdo->lastInsertId();
        $rev = revisao((int) $o['revisao_id']);
        $insE = $pdo->prepare('INSERT INTO cobranca_envios (cobranca_id, para_email, para_nome, delegados, cc, cco, assunto, corpo, topo, rodape, qtd) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $insP = $pdo->prepare('INSERT INTO cobranca_pessoas (envio_id, revisao_id, codigo) VALUES (?, ?, ?)');
        foreach ($plano['grupos'] as $g) {
            $ctx = ['nome' => $g['nome'], 'prazo' => $o['prazo'] ?? '', 'revisao' => $rev['nome'] ?? ''];
            $insE->execute([$id, $g['email'], $g['nome'], implode(', ', array_column($g['delegados'] ?? [], 'email')), implode(', ', $g['cc']), implode(', ', $g['cco']),
                            cobranca_aplicar($modelo['assunto'], $ctx, $g['pessoas'], $o['alvo'] === 'superior', false),
                            cobranca_aplicar($modelo['corpo'], $ctx, $g['pessoas'], $o['alvo'] === 'superior'),
                            $modelo['topo'], $modelo['rodape'], count($g['pessoas'])]);
            $eid = (int) $pdo->lastInsertId();
            foreach ($g['pessoas'] as $p) $insP->execute([$eid, $o['revisao_id'], (int) $p['codigo']]);
        }
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function cobranca(int $id): ?array
{
    cobranca_estrutura();
    $st = db()->prepare("SELECT c.*, u.name AS criado_por_nome, r.nome AS revisao_nome,
               (SELECT COUNT(*) FROM cobranca_envios e WHERE e.cobranca_id = c.id) AS envios,
               (SELECT COUNT(*) FROM cobranca_envios e WHERE e.cobranca_id = c.id AND e.status = 'enviado') AS enviados,
               (SELECT COUNT(*) FROM cobranca_envios e WHERE e.cobranca_id = c.id AND e.status = 'erro') AS erros,
               (SELECT COALESCE(SUM(qtd), 0) FROM cobranca_envios e WHERE e.cobranca_id = c.id) AS pessoas
          FROM cobrancas c LEFT JOIN users u ON u.id = c.criado_por LEFT JOIN revisoes r ON r.id = c.revisao_id WHERE c.id = ?");
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function cobrancas_lista(): array
{
    cobranca_estrutura();
    return db()->query("SELECT c.id FROM cobrancas c ORDER BY c.id DESC")->fetchAll(PDO::FETCH_COLUMN);
}

function cobranca_envios(int $id): array
{
    $st = db()->prepare('SELECT * FROM cobranca_envios WHERE cobranca_id = ? ORDER BY qtd DESC, para_nome');
    $st->execute([$id]);
    return $st->fetchAll();
}

/** Envia até $n e-mails pendentes da cobrança. Devolve o progresso. */
function cobranca_enviar_lote(int $id, int $n = 5): array
{
    $c = cobranca($id);
    if (!$c) throw new RuntimeException('Cobrança não encontrada.');
    $st = db()->prepare("SELECT * FROM cobranca_envios WHERE cobranca_id = ? AND status = 'pendente' ORDER BY id LIMIT " . max(1, $n));
    $st->execute([$id]);
    $upd = db()->prepare("UPDATE cobranca_envios SET status = ?, erro = ?, enviado_em = datetime('now','localtime') WHERE id = ?");
    foreach ($st->fetchAll() as $e) {
        // Com delegado, os dois vão no "Para"; sem, só o destinatário (com o nome)
        $para = ($e['delegados'] ?? '') !== '' ? array_merge([$e['para_email']], explode(', ', $e['delegados'])) : $e['para_email'];
        $erro = email_enviar($c['remetente'], $para, $e['para_nome'], $e['cc'] !== '' ? explode(', ', $e['cc']) : [],
                             $e['assunto'], $e['corpo'], $e['topo'], $e['rodape'],
                             ($e['cco'] ?? '') !== '' ? explode(', ', $e['cco']) : [],
                             lista_emails((string) ($c['nao_enviar'] ?? ''))[0]);
        $upd->execute([$erro ? 'erro' : 'enviado', $erro, $e['id']]);
    }
    $c = cobranca($id);
    $pend = (int) $c['envios'] - (int) $c['enviados'] - (int) $c['erros'];
    if ($pend === 0 && $c['status'] !== 'concluida') {
        db()->prepare("UPDATE cobrancas SET status = 'concluida', concluido_em = datetime('now','localtime') WHERE id = ?")->execute([$id]);
    }
    return ['total' => (int) $c['envios'], 'enviados' => (int) $c['enviados'], 'erros' => (int) $c['erros'], 'pendentes' => $pend];
}

/** Recoloca os e-mails com erro na fila. */
function cobranca_reenviar_erros(int $id): void
{
    db()->prepare("UPDATE cobranca_envios SET status = 'pendente', erro = NULL WHERE cobranca_id = ? AND status = 'erro'")->execute([$id]);
    db()->prepare("UPDATE cobrancas SET status = 'enviando', concluido_em = NULL WHERE id = ?")->execute([$id]);
}

/** codigo => ['vezes' => n, 'ultima' => data] das cobranças ENVIADAS numa revisão. */
function pessoas_cobradas(int $revId): array
{
    cobranca_estrutura();
    $st = db()->prepare("SELECT p.codigo, COUNT(*) AS vezes, MAX(e.enviado_em) AS ultima
                           FROM cobranca_pessoas p JOIN cobranca_envios e ON e.id = p.envio_id
                          WHERE p.revisao_id = ? AND e.status = 'enviado' GROUP BY p.codigo");
    $st->execute([$revId]);
    $out = [];
    foreach ($st->fetchAll() as $r) $out[(int) $r['codigo']] = ['vezes' => (int) $r['vezes'], 'ultima' => $r['ultima']];
    return $out;
}
