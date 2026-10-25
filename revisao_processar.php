<?php
/**
 * Primeira atualização de uma revisão recém-criada, em etapas (POST, JSON).
 * A tela "Nova revisão" chama uma etapa por vez e mostra o progresso de cada
 * uma; a última grava o resumo (flash) e a tela abre o painel já carregado.
 *
 * Etapas: basefull → andamento → delegacoes → lideres. Só "andamento" é
 * essencial; as outras, se falharem, viram aviso e o processo segue.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/services/atualizacao.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$resp = function (array $r, int $code = 200) { http_response_code($code); exit(json_encode($r, JSON_UNESCAPED_UNICODE)); };
if (!is_logged_in() || !is_admin()) $resp(['ok' => false, 'texto' => 'Sessão encerrada ou sem permissão. Entre de novo.'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) $resp(['ok' => false, 'texto' => 'Requisição inválida.'], 403);

$rev = revisao((int) ($_POST['id'] ?? 0));
if (!$rev) $resp(['ok' => false, 'texto' => 'Revisão não encontrada.'], 404);
set_time_limit(600);
$n = fn(int $x) => number_format($x, 0, ',', '.');
$guardar = function (string $etapa, string $texto) use ($rev) { $_SESSION['processar'][(int) $rev['id']][$etapa] = $texto; };

switch ($_POST['etapa'] ?? '') {
    case 'basefull':
        $r = basefull_sincronizar();
        $guardar('basefull', $r['texto']);
        $resp(['ok' => true, 'aviso' => in_array($r['status'], ['erro', 'desligada'], true), 'texto' => $r['texto']]);

    case 'andamento':
        $erro = andamento_garantir($rev, true);
        if ($erro) {
            $t = 'Não foi possível ler o andamento no Horacius: ' . $erro . '. O painel abre sem andamento; use "Atualizar andamento" depois.';
            $guardar('andamento', $t);
            $resp(['ok' => true, 'aviso' => true, 'texto' => $t]);
        }
        $c = (int) db()->query('SELECT COUNT(*) FROM revisados WHERE revisao_id = ' . (int) $rev['id'])->fetchColumn();
        $t = 'Andamento lido no Horacius (' . horacius_rotulo() . '): ' . $n($c) . ' pessoa(s) com revisão registrada desde ' . date('d/m/Y', strtotime($rev['inicio'])) . '.';
        $guardar('andamento', $t);
        $resp(['ok' => true, 'texto' => $t]);

    case 'delegacoes':
        $r = delegacoes_atualizar();
        $guardar('delegacoes', $r['texto']);
        $resp(['ok' => true, 'aviso' => $r['status'] === 'erro', 'texto' => $r['texto']]);

    case 'lideres':
        try {
            $p = painel_prioridades((int) $rev['id']);
            $h = $p['hierarquia'];
            $t = $h['carga']
                ? 'Líderes: ' . $n($h['basefull']) . ' pessoa(s) pela BaseFull e ' . $n($h['base_revisao']) . ' pela planilha da revisão; ' . $n($p['sem_destino']) . ' pendente(s) sem responsável contatável.'
                : 'Sem BaseFull importada: líderes só da planilha da revisão; ' . $n($p['sem_destino']) . ' pendente(s) sem responsável contatável.';
            $aviso = !$h['carga'];
        } catch (Throwable $e) {
            $t = 'Não foi possível cruzar os líderes: ' . $e->getMessage();
            $aviso = true;
        }
        // Resumo de tudo no aviso do painel
        $passos = $_SESSION['processar'][(int) $rev['id']] ?? [];
        unset($_SESSION['processar'][(int) $rev['id']]);
        $tem = fn($s) => $s !== '' && preg_match('/não foi possível|desligad|falh/i', $s);
        $algumAviso = $aviso || array_filter($passos, $tem);
        flash($algumAviso ? 'aviso' : 'ok', 'Revisão "' . $rev['nome'] . '" iniciada e carregada. ' . implode(' ', array_filter([
            $passos['andamento'] ?? '', $passos['basefull'] ?? '', $passos['delegacoes'] ?? '', $t])));
        $resp(['ok' => true, 'aviso' => $aviso, 'texto' => $t, 'destino' => BASE_URL . '/dashboard.php?r=' . (int) $rev['id']]);

    default:
        $resp(['ok' => false, 'texto' => 'Etapa desconhecida.'], 400);
}
