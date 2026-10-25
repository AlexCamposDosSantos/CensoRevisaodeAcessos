<?php
/** Atualização do andamento: BaseFull do dia, andamento no Horacius e cruzamento da hierarquia. */
require_once __DIR__ . '/painel_prioridades.php';
require_once __DIR__ . '/basefull.php';
require_once __DIR__ . '/delegacao.php';

function painel_atualizar_revisao(array &$rev): array
{
    $numero = fn(int $n) => number_format($n, 0, ',', '.');
    // Primeiro a hierarquia do dia: os líderes do painel e das cobranças saem dela
    $bf = basefull_sincronizar();
    $erroAndamento = andamento_garantir($rev, true);
    $texto = $erroAndamento
        ? 'Não foi possível atualizar o andamento no Horacius: ' . $erroAndamento . '. '
        : 'Andamento atualizado no Horacius (' . horacius_rotulo() . '). ';
    $texto .= $bf['texto'] . ' ';
    // Delegações de gestão: com o banco já respondendo, a leitura é rápida
    $dl = $erroAndamento ? ['status' => 'erro', 'texto' => 'Delegações não relidas (Horacius fora do ar).'] : delegacoes_atualizar();
    $texto .= $dl['texto'] . ' ';
    try {
        // A mesma resolução alimenta ranking, pendentes, CSV e destinatários de cobrança.
        $painel = painel_prioridades((int) $rev['id']);
        $hierarquia = $painel['hierarquia'];
        if ($hierarquia['carga']) {
            $texto .= 'Líderes: ' . $numero($hierarquia['basefull']) . ' pessoa(s) pela BaseFull'
                    . ' e ' . $numero($hierarquia['base_revisao']) . ' pela planilha da revisão. ';
        } else {
            $texto .= 'A BaseFull ainda não foi importada no Censo. Foram usados os líderes disponíveis na planilha da revisão. ';
        }
        $texto .= $numero($painel['sem_destino']) . ' pendente(s) sem responsável contatável.';
        $aviso = $erroAndamento || !$hierarquia['carga'] || in_array($bf['status'], ['erro', 'desligada'], true) || $dl['status'] === 'erro';
        return ['tipo' => $aviso ? 'aviso' : 'ok', 'texto' => $texto];
    } catch (Throwable $e) {
        return ['tipo' => $erroAndamento ? 'erro' : 'aviso',
                'texto' => $texto . 'Não foi possível cruzar os líderes com a BaseFull: ' . $e->getMessage()];
    }
}
