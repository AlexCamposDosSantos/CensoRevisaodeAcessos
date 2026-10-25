<?php
/**
 * Agendamento — quando o Censo atualiza o andamento sozinho, e o relatório por
 * e-mail que sai depois dessa atualização. A configuração é editada na tela
 * (Configurações) e fica no banco do Censo (tabela ajustes, chave 'agendamento').
 *
 * O Agendador de Tarefas do Windows chama `php cli.php cron` de tempos em
 * tempos (sugestão: a cada 10 min); ESTE arquivo decide se já está na hora.
 *
 * Modos:
 *   'diario'    → uma vez por dia, no 1º tique a partir de 'hora' (HH:MM).
 *   'intervalo' → a cada 'intervalo_horas' horas.
 *   'semanal'   → uma vez por semana, no 'dia_semana' (1 = segunda), a partir de 'hora'.
 */
require_once __DIR__ . '/atualizacao.php';
require_once __DIR__ . '/email.php';

const AGENDA_TENTATIVAS = 3;   // leituras seguidas que podem falhar antes de desistir do ciclo

function agenda_padrao(): array
{
    return [
        'ativo'           => false,       // começa desligado — liga na tela
        'modo'            => 'semanal',
        'hora'            => '07:00',
        'dia_semana'      => 1,           // 1 = segunda … 7 = domingo (ISO-8601)
        'intervalo_horas' => 24,
        'forcar'          => false,       // "rodar na próxima verificação" (vale uma vez)
        'ultima_execucao' => null,
        'ultimo_resumo'   => null,        // números da última atualização AUTOMÁTICA (base da variação)
        'ultimo_erro'     => null,
        'falhas'          => 0,           // leituras seguidas que falharam neste ciclo
        'tique'         => null,        // última vez que o agendador do servidor chamou o cron

        // Relatório por e-mail, enviado logo depois da atualização automática
        'relatorio'         => false,
        'relatorio_freq'    => 'cada',    // 'cada' = após toda atualização | 'semanal' = 1x por semana
        'relatorio_dia'     => 1,         // modo semanal: a partir de qual dia da semana
        'relatorio_remetente' => '',
        'relatorio_para'    => '',
        'relatorio_copia'   => '',
        'relatorio_assunto' => '',
        'relatorio_semana'  => null,      // 'AAAA-SS' da última remessa semanal
        'relatorio_enviado' => null,
        'relatorio_erro'    => null,
    ];
}

function agenda_load(): array
{
    db()->exec('CREATE TABLE IF NOT EXISTS ajustes (chave TEXT PRIMARY KEY, valor TEXT)');
    $v = db()->query("SELECT valor FROM ajustes WHERE chave = 'agendamento'")->fetchColumn();
    $d = $v !== false ? json_decode((string) $v, true) : null;
    return is_array($d) ? array_merge(agenda_padrao(), $d) : agenda_padrao();
}

function agenda_save(array $cfg): void
{
    db()->exec('CREATE TABLE IF NOT EXISTS ajustes (chave TEXT PRIMARY KEY, valor TEXT)');
    db()->prepare("INSERT INTO ajustes (chave, valor) VALUES ('agendamento', ?) ON CONFLICT(chave) DO UPDATE SET valor = excluded.valor")
        ->execute([json_encode($cfg, JSON_UNESCAPED_UNICODE)]);
}

/** Altera só os campos dados, relendo antes (o cron e a tela gravam no mesmo registro). */
function agenda_marcar(array $campos): void
{
    agenda_save(array_merge(agenda_load(), $campos));
}

/** HH:MM válido, ou o padrão. */
function agenda_hora(string $v, string $padrao): string
{
    return preg_match('/^(\d{1,2}):(\d{2})$/', trim($v), $m) && (int) $m[1] < 24 && (int) $m[2] < 60
        ? sprintf('%02d:%02d', $m[1], $m[2]) : $padrao;
}

/** Valida o formulário e salva. Devolve [cfg, erros] — com erro, nada é salvo. */
function agenda_salvar_form(array $post): array
{
    $cfg = agenda_load();
    $erros = [];
    $cfg['ativo'] = !empty($post['ativo']);
    $cfg['modo']  = in_array($post['modo'] ?? '', ['diario', 'intervalo', 'semanal'], true) ? $post['modo'] : 'semanal';
    $cfg['hora']  = agenda_hora((string) ($post['hora'] ?? ''), '07:00');
    $ds = (int) ($post['dia_semana'] ?? 1);
    $cfg['dia_semana'] = $ds >= 1 && $ds <= 7 ? $ds : 1;
    $cfg['intervalo_horas'] = max(1, min(168, (int) ($post['intervalo_horas'] ?? 24)));

    $cfg['relatorio']      = !empty($post['relatorio']);
    $cfg['relatorio_freq'] = ($post['relatorio_freq'] ?? '') === 'semanal' ? 'semanal' : 'cada';
    $rd = (int) ($post['relatorio_dia'] ?? 1);
    $cfg['relatorio_dia'] = $rd >= 1 && $rd <= 7 ? $rd : 1;
    $rem = (string) ($post['relatorio_remetente'] ?? '');
    $cfg['relatorio_remetente'] = isset(remetentes()[$rem]) ? $rem : '';
    [$para, $ruinsPara]   = lista_emails((string) ($post['relatorio_para'] ?? ''));
    [$copia, $ruinsCopia] = lista_emails((string) ($post['relatorio_copia'] ?? ''));
    $cfg['relatorio_para']    = implode("\n", $para);
    $cfg['relatorio_copia']   = implode("\n", $copia);
    $cfg['relatorio_assunto'] = trim(mb_substr(str_replace(["\r", "\n"], ' ', (string) ($post['relatorio_assunto'] ?? '')), 0, 200));

    // Endereço ruim salvo em silêncio vira envio que falha de madrugada, sem ninguém olhando
    foreach (array_merge($ruinsPara, $ruinsCopia) as $r) $erros[] = 'Não é um e-mail: "' . $r . '".';
    if ($cfg['relatorio'] && !$para)                      $erros[] = 'Para ligar o relatório, informe ao menos um destinatário.';
    if ($cfg['relatorio'] && $cfg['relatorio_remetente'] === '') $erros[] = 'Para ligar o relatório, escolha a caixa que envia.';

    if (!$erros) agenda_save($cfg);
    return [$cfg, $erros];
}

// ─── Quando rodar ────────────────────────────────────────────────────────────

/** Dia do ciclo atual: hoje (diário) ou o dia_semana desta semana ISO (semanal). */
function agenda_alvo(array $cfg, DateTimeImmutable $agora): DateTimeImmutable
{
    [$h, $m] = array_map('intval', explode(':', agenda_hora((string) $cfg['hora'], '07:00')));
    $dia = $agora;
    if ($cfg['modo'] === 'semanal') {
        $dia = $agora->setISODate((int) $agora->format('o'), (int) $agora->format('W'), max(1, min(7, (int) $cfg['dia_semana'])));
    }
    return $dia->setTime($h, $m);
}

/**
 * Já está na hora? 'forcar' passa na frente e vale uma vez. No diário e no
 * semanal, se o horário do ciclo passou sem rodar (servidor fora, ou o
 * agendamento acabou de ser ligado), roda no próximo tique.
 */
function agenda_na_hora(array $cfg, ?DateTimeImmutable $agora = null): bool
{
    if (!empty($cfg['forcar'])) return true;
    if (empty($cfg['ativo'])) return false;
    $agora ??= new DateTimeImmutable('now');
    $ult = !empty($cfg['ultima_execucao']) ? new DateTimeImmutable($cfg['ultima_execucao']) : null;

    if ($cfg['modo'] === 'intervalo') {
        return !$ult || $agora >= $ult->modify('+' . max(1, (int) $cfg['intervalo_horas']) . ' hours');
    }
    $alvo = agenda_alvo($cfg, $agora);
    return $agora >= $alvo && (!$ult || $ult < $alvo);
}

/** Próxima atualização, em texto para a tela. */
function agenda_proxima(array $cfg, ?DateTimeImmutable $agora = null): string
{
    if (!empty($cfg['forcar'])) return 'na próxima verificação (pedida na tela)';
    if (empty($cfg['ativo'])) return 'desligada';
    $agora ??= new DateTimeImmutable('now');
    if (agenda_na_hora($cfg, $agora)) return 'na próxima verificação';
    if ($cfg['modo'] === 'intervalo') {
        return (new DateTimeImmutable($cfg['ultima_execucao']))->modify('+' . max(1, (int) $cfg['intervalo_horas']) . ' hours')->format('d/m/Y H:i');
    }
    $alvo = agenda_alvo($cfg, $agora);
    if ($agora >= $alvo) $alvo = $alvo->modify($cfg['modo'] === 'semanal' ? '+1 week' : '+1 day');
    return $alvo->format('d/m/Y H:i');
}

const DIAS_SEMANA = [1 => 'segunda', 2 => 'terça', 3 => 'quarta', 4 => 'quinta', 5 => 'sexta', 6 => 'sábado', 7 => 'domingo'];

/** "toda segunda às 07:00", ou null se desligado. */
function agenda_descricao(array $cfg): ?string
{
    if (empty($cfg['ativo'])) return null;
    return match ($cfg['modo']) {
        'intervalo' => 'a cada ' . (int) $cfg['intervalo_horas'] . ' hora' . ((int) $cfg['intervalo_horas'] === 1 ? '' : 's'),
        'semanal'   => ((int) $cfg['dia_semana'] >= 6 ? 'todo ' : 'toda ') . DIAS_SEMANA[(int) $cfg['dia_semana']] . ' às ' . $cfg['hora'],
        default     => 'todo dia às ' . $cfg['hora'],
    };
}

// ─── Diagnóstico do agendador ────────────────────────────────────────────────
//
// O Censo pega carona no tique do Prumo (tarefa do Windows → php cli.php cron
// do Prumo → php cli.php cron do Censo). Quando o Censo não é chamado, a tela
// confere cada elo dessa corrente para dizer ONDE ela quebrou.

function agenda_prumo_dir(): string
{
    return dirname(basefull_prumo_env());
}

/** Últimos $bytes de um arquivo (o cron.log cresce sem parar). */
function agenda_cauda(string $arquivo, int $bytes = 65536): string
{
    $fh = @fopen($arquivo, 'rb');
    if (!$fh) return '';
    $tam = filesize($arquivo);
    fseek($fh, max(0, $tam - $bytes));
    $txt = (string) stream_get_contents($fh);
    fclose($fh);
    return mb_check_encoding($txt, 'UTF-8') ? $txt : mb_convert_encoding($txt, 'UTF-8', 'Windows-1252');
}

/** A tarefa do Windows, se o PHP da web puder perguntar (schtasks). Campo => valor, ou null. */
function agenda_tarefa_windows(string $nome): ?array
{
    if (PHP_OS_FAMILY !== 'Windows' || !function_exists('exec')) return null;
    $linhas = [];
    @exec('schtasks /query /tn ' . escapeshellarg($nome) . ' /fo CSV /v 2>NUL', $linhas, $codigo);
    if ($codigo !== 0 || count($linhas) < 2) return ['_erro' => true];
    $conv = fn($s) => mb_check_encoding($s, 'UTF-8') ? $s : mb_convert_encoding($s, 'UTF-8', 'CP850');
    $cab = str_getcsv($conv($linhas[0]));
    $val = str_getcsv($conv($linhas[1]));
    return count($cab) === count($val) ? array_combine($cab, $val) : ['_erro' => true];
}

/** Valor de um campo do schtasks pelo começo do nome (o cabeçalho vem no idioma do Windows). */
function agenda_campo(array $t, array $prefixos): string
{
    foreach ($t as $k => $v) foreach ($prefixos as $p) if (stripos($k, $p) === 0) return trim((string) $v);
    return '';
}

/**
 * Cada elo da corrente: ['ok' => true|false|null, 'titulo', 'detalhe'].
 * null = não deu para verificar (não é erro).
 */
function agenda_diagnostico(): array
{
    $out = [];
    $dir = agenda_prumo_dir();
    $cli = $dir . '/cli.php';
    if (!is_file($cli)) {
        return [['ok' => false, 'titulo' => 'Prumo não encontrado', 'detalhe' => "Não há {$cli}. Sem o Prumo, crie uma tarefa própria para o Censo (comando abaixo), ou aponte CENSO_PRUMO_ENV no .env."]];
    }
    $out[] = ['ok' => true, 'titulo' => 'Prumo encontrado', 'detalhe' => $dir];

    $temCarona = str_contains((string) file_get_contents($cli), 'cron_carona');
    $out[] = $temCarona
        ? ['ok' => true, 'titulo' => 'O Prumo chama o Censo', 'detalhe' => 'O cli.php do Prumo tem a carona (cron_carona).']
        : ['ok' => false, 'titulo' => 'O Prumo deste servidor ainda não chama o Censo', 'detalhe' => 'O cli.php do Prumo é a versão antiga, sem a carona. Suba o cli.php novo do Prumo.'];

    $log = $dir . '/logs/cron.log';
    if (!is_file($log)) {
        $out[] = ['ok' => false, 'titulo' => 'O tique do Prumo não deixou log', 'detalhe' => "Não existe {$log}. A tarefa do Windows precisa gravar a saída lá (>> logs\\cron.log 2>&1)."];
    } else {
        $min = (int) floor((time() - filemtime($log)) / 60);
        $out[] = $min <= 15
            ? ['ok' => true, 'titulo' => 'O tique do Prumo está rodando', 'detalhe' => "Último registro no cron.log há {$min} min."]
            : ['ok' => false, 'titulo' => 'O tique do Prumo parou', 'detalhe' => 'Último registro no cron.log em ' . date('d/m/Y H:i', filemtime($log)) . " (há {$min} min). Confira a tarefa no Agendador de Tarefas."];
        $linhasCenso = preg_grep('/^\[Censo\]/', preg_split('/\r?\n/', agenda_cauda($log)));
        if ($linhasCenso) {
            $ult = end($linhasCenso);
            $out[] = ['ok' => !preg_match('/erro|falh|fatal|não consegui|código [1-9]/i', $ult), 'titulo' => 'Última linha do Censo no log do Prumo', 'detalhe' => mb_strimwidth($ult, 0, 300, '…')];
        } elseif ($temCarona) {
            $out[] = ['ok' => false, 'titulo' => 'Nenhuma linha [Censo] no log do Prumo', 'detalhe' => 'O Prumo tem a carona mas ainda não chamou o Censo — talvez o tique ainda não tenha rodado desde a atualização dos arquivos.'];
        }
    }

    $nome = censo_cfg('CENSO_PRUMO_TAREFA', 'DivergenciaIA');
    $t = agenda_tarefa_windows($nome);
    if ($t === null) {
        $out[] = ['ok' => null, 'titulo' => 'Tarefa do Windows', 'detalhe' => 'Não deu para consultar o Agendador de Tarefas daqui.'];
    } elseif (!empty($t['_erro'])) {
        $out[] = ['ok' => null, 'titulo' => "Tarefa \"{$nome}\"", 'detalhe' => 'O usuário do servidor web não consegue ler o Agendador de Tarefas (ou a tarefa tem outro nome — CENSO_PRUMO_TAREFA no .env).'];
    } else {
        $status = agenda_campo($t, ['Status']);
        $estado = agenda_campo($t, ['Estado da tarefa agendada', 'Scheduled Task State']);
        $resultado = agenda_campo($t, ['Último resultado', 'Ultimo resultado', 'Last Result']);
        $ultima = agenda_campo($t, ['Hora da última execução', 'Última execução', 'Last Run Time']);
        $proxima = agenda_campo($t, ['Hora da próxima execução', 'Próxima execução', 'Next Run Time']);
        $acao = agenda_campo($t, ['Tarefa a executar', 'Tarefa a ser executada', 'Task To Run']);
        $desab = stripos($estado, 'desab') !== false || stripos($estado, 'disab') !== false;
        $out[] = ['ok' => !$desab && in_array($resultado, ['0', ''], true),
                  'titulo' => "Tarefa \"{$nome}\" no Windows" . ($desab ? ' — DESABILITADA' : ''),
                  'detalhe' => trim("Status: {$status}" . ($estado ? " · {$estado}" : '') . " · última: {$ultima} · próxima: {$proxima} · resultado: {$resultado}"
                             . ($acao ? "\nExecuta: {$acao}" : '') . ($resultado !== '' && $resultado !== '0' ? "\nResultado diferente de 0 = a última execução falhou." : ''))];
    }
    return $out;
}

/**
 * Manda o relatório depois desta atualização automática? No semanal, o guarda é
 * a SEMANA da última remessa, não a data: se o dia configurado passa sem
 * atualização (feriado, servidor fora), o relatório sai na próxima da semana.
 */
function agenda_relatorio_agora(array $cfg, ?DateTimeImmutable $agora = null): bool
{
    if (empty($cfg['relatorio'])) return false;
    if ($cfg['relatorio_freq'] !== 'semanal') return true;
    $agora ??= new DateTimeImmutable('now');
    return (int) $agora->format('N') >= (int) $cfg['relatorio_dia']
        && ($cfg['relatorio_semana'] ?? null) !== $agora->format('o-W');
}

function agenda_relatorio_descricao(array $cfg): string
{
    if (empty($cfg['relatorio'])) return 'desligado';
    return $cfg['relatorio_freq'] === 'semanal'
        ? 'uma vez por semana, após a primeira atualização automática a partir de ' . DIAS_SEMANA[(int) $cfg['relatorio_dia']]
        : 'após cada atualização automática';
}

// ─── Execução ────────────────────────────────────────────────────────────────

/** A revisão que o agendamento acompanha: a mais recente. */
function agenda_revisao(): ?array
{
    $lista = revisoes_lista();
    return $lista ? revisao((int) $lista[0]['id']) : null;
}

/** Números da revisão no momento, no formato guardado em 'ultimo_resumo'. */
function agenda_numeros(array $rev): array
{
    $vinc = painel_por_vinculo((int) $rev['id']);
    $c = array_sum(array_column($vinc, 'concluido'));
    $p = array_sum(array_column($vinc, 'pendente'));
    $meta = array_filter($vinc, fn($v) => (VINCULOS[$v] ?? '') === GRUPO_META, ARRAY_FILTER_USE_KEY);
    return ['revisao_id' => (int) $rev['id'], 'concluidos' => $c, 'pendentes' => $p,
            'meta_concluidos' => array_sum(array_column($meta, 'concluido')), 'meta_pendentes' => array_sum(array_column($meta, 'pendente')),
            'em' => $rev['andamento_em'], 'banco' => horacius_rotulo((string) ($rev['andamento_banco'] ?? ''))];
}

/**
 * Atualiza a revisão mais recente e, se for a vez, manda o relatório. É o que
 * o cron faz quando está na hora. $log recebe as linhas para o cron.log.
 */
function agenda_executar(callable $log): void
{
    $cfg = agenda_load();
    $anterior = $cfg['ultimo_resumo'];
    $rev = agenda_revisao();
    if (!$rev) {
        agenda_marcar(['ultima_execucao' => date('Y-m-d H:i:s'), 'forcar' => false, 'ultimo_erro' => 'Nenhuma revisão iniciada — nada a atualizar.']);
        $log('nenhuma revisão iniciada — nada a atualizar.');
        return;
    }

    $res = painel_atualizar_revisao($rev);
    $erro = ($rev['andamento_erro'] ?? null) ?: null;   // a leitura boa limpa o erro da revisão
    $num = agenda_numeros($rev);
    $log(($erro ? 'FALHOU: ' : 'ok: ') . $res['texto']);

    // Leitura que falhou tenta de novo nos próximos tiques (o banco pode ter só
    // soluçado); depois de AGENDA_TENTATIVAS, desiste deste ciclo e espera o próximo.
    if ($erro) {
        $falhas = (int) $cfg['falhas'] + 1;
        $desiste = $falhas >= AGENDA_TENTATIVAS;
        agenda_marcar(['ultimo_erro' => $erro, 'falhas' => $desiste ? 0 : $falhas]
            + ($desiste ? ['ultima_execucao' => date('Y-m-d H:i:s'), 'forcar' => false] : []));
        // Relatório só com leitura boa: número velho num e-mail novo engana quem lê
        $log($desiste ? "falhou {$falhas} vezes seguidas — fica para o próximo ciclo, sem relatório."
                      : "tentativa {$falhas} de " . AGENDA_TENTATIVAS . ' — tenta de novo na próxima verificação.');
        return;
    }
    agenda_marcar(['ultima_execucao' => date('Y-m-d H:i:s'), 'forcar' => false, 'ultimo_erro' => null, 'falhas' => 0, 'ultimo_resumo' => $num]);
    if (!agenda_relatorio_agora(agenda_load())) return;
    $falha = relatorio_enviar($rev, ($anterior['revisao_id'] ?? null) === $num['revisao_id'] ? $anterior : null);
    agenda_marcar($falha
        ? ['relatorio_erro' => $falha]
        : ['relatorio_erro' => null, 'relatorio_enviado' => date('Y-m-d H:i:s'), 'relatorio_semana' => date('o-W')]);
    $log($falha ? 'relatório FALHOU: ' . $falha : 'relatório enviado para ' . str_replace("\n", ', ', agenda_load()['relatorio_para']));
}

// ─── Relatório ───────────────────────────────────────────────────────────────

const RELATORIO_ASSUNTO_PADRAO = 'Censo · {revisao}: {percentual}% — ' . GRUPO_META . ' · {pendentes} pendentes{variacao} — {data}';

/** Os números sem sufixo são os do grupo da meta (GRUPO_META); "_geral" soma todos os vínculos. */
const RELATORIO_MARCADORES = [
    '{revisao}'          => 'nome da revisão',
    '{percentual}'       => 'percentual concluído de ' . GRUPO_META . ' (a meta)',
    '{concluidos}'       => 'quem já revisou, em ' . GRUPO_META,
    '{pendentes}'        => 'quem falta, em ' . GRUPO_META,
    '{variacao}'         => 'concluídos em ' . GRUPO_META . ' desde a atualização automática anterior — ex.: " (+35)"',
    '{percentual_geral}' => 'percentual concluído com todos os vínculos (inclui Professor)',
    '{pendentes_geral}'  => 'quem falta, todos os vínculos',
    '{data}'             => 'data da atualização (dd/mm)',
];

/** Tudo que o e-mail mostra, montado dos mesmos cálculos do painel. */
function relatorio_dados(array $rev, ?array $anterior): array
{
    $vinc = painel_por_vinculo((int) $rev['id']);
    $num = agenda_numeros($rev);
    $total = $num['concluidos'] + $num['pendentes'];
    return [
        'revisao'   => $rev,
        'num'       => $num,
        'total'     => $total,
        'variacao'  => $anterior ? $num['concluidos'] - (int) $anterior['concluidos'] : null,
        // O número que conta: o grupo da meta, com a própria variação
        'meta'      => ['nome' => GRUPO_META, 'concluidos' => $num['meta_concluidos'], 'pendentes' => $num['meta_pendentes'],
                        'total' => $num['meta_concluidos'] + $num['meta_pendentes'],
                        'variacao' => isset($anterior['meta_concluidos']) ? $num['meta_concluidos'] - (int) $anterior['meta_concluidos'] : null],
        'anterior'  => $anterior,
        'grupos'    => painel_por_grupo($vinc),
        'vinculos'  => array_filter($vinc, fn($x) => $x['concluido'] + $x['pendente'] > 0),
        'prior'     => painel_prioridades((int) $rev['id']),
    ];
}

function relatorio_assunto(array $d, string $modelo = ''): string
{
    $fmt = fn($x) => number_format((int) $x, 0, ',', '.');
    $m = $d['meta'];
    $v = $m['variacao'];
    $assunto = strtr(trim($modelo) !== '' ? $modelo : RELATORIO_ASSUNTO_PADRAO, [
        '{revisao}'          => $d['revisao']['nome'],
        '{percentual_geral}' => pct($d['num']['concluidos'], $d['total']),
        '{pendentes_geral}'  => $fmt($d['num']['pendentes']),
        '{percentual}'       => pct($m['concluidos'], $m['total']),
        '{concluidos}'       => $fmt($m['concluidos']),
        '{pendentes}'        => $fmt($m['pendentes']),
        '{variacao}'   => $v === null ? '' : ($v > 0 ? ' (+' . $fmt($v) . ')' : ($v < 0 ? ' (' . $fmt($v) . ')' : ' (estável)')),
        '{data}'       => $d['num']['em'] ? date('d/m', strtotime($d['num']['em'])) : date('d/m'),
    ]);
    return trim(str_replace(["\r", "\n"], ' ', $assunto));   // cabeçalho de e-mail não tem quebra de linha
}

/** Corpo do e-mail: tabela e estilo em atributo — é o que o Outlook respeita. */
function relatorio_html(array $d): string
{
    ob_start();
    require dirname(__DIR__) . '/views/email_relatorio.php';
    return (string) ob_get_clean();
}

/**
 * Monta e envia. $para vazio = os destinatários configurados (com cópia);
 * informado = só para ele (teste pela tela). Devolve null ou o erro.
 */
function relatorio_enviar(array $rev, ?array $anterior, array $para = []): ?string
{
    $cfg = agenda_load();
    $rem = $cfg['relatorio_remetente'] ?: (string) array_key_first(remetentes());
    if ($rem === '' || !isset(remetentes()[$rem])) return 'Nenhuma caixa de envio configurada (CENSO_REMETENTES).';
    $copia = [];
    if (!$para) {
        $para  = lista_emails($cfg['relatorio_para'])[0];
        $copia = lista_emails($cfg['relatorio_copia'])[0];
    }
    if (!$para) return 'Sem destinatário: preencha em Configurações → Relatório por e-mail.';
    try {
        $d = relatorio_dados($rev, $anterior);
        return email_enviar($rem, $para, '', $copia, relatorio_assunto($d, $cfg['relatorio_assunto']), relatorio_html($d), '', '');
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}
