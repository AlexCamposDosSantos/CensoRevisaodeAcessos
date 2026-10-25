<?php
/**
 * cli.php — linha de comando do Censo (chamado pelo Agendador de Tarefas).
 *
 * Uso:
 *   php cli.php cron                     tique do agendador: atualiza se estiver na hora
 *                                        (e manda o relatório, se for a vez)
 *   php cli.php status                   mostra o agendamento e a próxima atualização
 *   php cli.php atualizar                atualiza a revisão mais recente agora (sem relatório)
 *   php cli.php relatorio --para=a@b.com monta o relatório e envia SÓ para esse endereço
 *
 * Agendador de Tarefas (Windows), a cada 10 minutos:
 *   C:\xampp\php\php.exe C:\xampp\htdocs\horacius-tools\funcoes\Censo\cli.php cron >> ...\dados\cron.log
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("cli.php só roda via linha de comando.\n");
}
set_time_limit(0);
ini_set('memory_limit', '1024M');

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/services/agendamento.php';

$args = array_slice($argv, 1);
$cmd  = $args[0] ?? 'status';
$out  = fn(string $s) => fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . '] ' . $s . "\n");

try {
    switch ($cmd) {
        case 'cron':
            agenda_marcar(['tique' => date('Y-m-d H:i:s')]);   // a tela mostra que o agendador está vivo
            $cfg = agenda_load();
            if (!agenda_na_hora($cfg)) {
                $out(empty($cfg['ativo']) ? 'atualização automática desligada. Nada a fazer.'
                                          : 'fora do horário (próxima: ' . agenda_proxima($cfg) . '). Nada a fazer.');
                break;
            }
            // Um tique não atropela o outro (a leitura do Horacius pode demorar)
            $lock = dirname(DB_PATH) . '/cron.lock';
            if (is_file($lock) && time() - (int) filemtime($lock) < 3600) {
                $out('outra atualização em andamento (lock). Pulando.');
                break;
            }
            file_put_contents($lock, (string) getmypid());
            try {
                $out('INICIANDO atualização automática' . (!empty($cfg['forcar']) ? ' (pedida na tela)' : '') . '...');
                agenda_executar($out);
                $out('CONCLUÍDO.');
            } finally {
                @unlink($lock);
            }
            break;

        case 'status':
            $cfg = agenda_load();
            $rev = agenda_revisao();
            $out('agendamento : ' . (agenda_descricao($cfg) ?? 'desligado'));
            $out('próxima     : ' . agenda_proxima($cfg));
            $out('última      : ' . ($cfg['ultima_execucao'] ?? '—') . ($cfg['ultimo_erro'] ? ' (ERRO: ' . $cfg['ultimo_erro'] . ')' : ''));
            $out('relatório   : ' . agenda_relatorio_descricao($cfg));
            $out('revisão     : ' . ($rev ? $rev['nome'] . ' (#' . $rev['id'] . ')' : 'nenhuma'));
            $out('banco       : ' . horacius_rotulo());
            break;

        case 'atualizar':
            $rev = agenda_revisao();
            if (!$rev) { $out('nenhuma revisão iniciada.'); exit(1); }
            $res = painel_atualizar_revisao($rev);
            $out($res['tipo'] . ': ' . $res['texto']);
            break;

        case 'relatorio':
            $para = [];
            foreach ($args as $a) if (str_starts_with($a, '--para=')) $para = lista_emails(substr($a, 7))[0];
            if (!$para) { $out('Uso: php cli.php relatorio --para=seu@email (o teste vai só para esse endereço).'); exit(1); }
            $rev = agenda_revisao();
            if (!$rev) { $out('nenhuma revisão iniciada.'); exit(1); }
            $ant  = agenda_load()['ultimo_resumo'];
            $erro = relatorio_enviar($rev, ($ant['revisao_id'] ?? null) === (int) $rev['id'] ? $ant : null, $para);
            $out($erro ? 'FALHOU: ' . $erro : 'relatório enviado para ' . implode(', ', $para));
            break;

        default:
            $out("comando desconhecido: {$cmd}. Use cron, status, atualizar ou relatorio.");
            exit(1);
    }
} catch (Throwable $e) {
    $out('ERRO: ' . $e->getMessage());
    if ($cmd === 'cron') agenda_marcar(['ultima_execucao' => date('Y-m-d H:i:s'), 'forcar' => false, 'ultimo_erro' => $e->getMessage()]);
    exit(1);
}
