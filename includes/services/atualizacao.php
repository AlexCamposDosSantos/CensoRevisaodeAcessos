<?php
/** Atualização manual do andamento e cruzamento da hierarquia importada. */
require_once __DIR__ . '/painel_prioridades.php';

function painel_atualizar_revisao(array &$rev): array
{
    $numero = fn(int $n) => number_format($n, 0, ',', '.');
    $erroAndamento = andamento_garantir($rev, true);
    try {
        // A mesma resolução alimenta ranking, pendentes, CSV e destinatários de cobrança.
        $painel = painel_prioridades((int) $rev['id']);
        $hierarquia = $painel['hierarquia'];
        $texto = $erroAndamento
            ? 'Não foi possível atualizar o andamento no Horacius: ' . $erroAndamento . '. '
            : 'Andamento atualizado no Horacius (' . horacius_rotulo() . '). ';
        if ($hierarquia['carga']) {
            $texto .= 'BaseFull consultada: ' . $numero($hierarquia['basefull']) . ' pessoa(s) com líder encontrado na base';
            $texto .= ' e ' . $numero($hierarquia['base_revisao']) . ' usando o líder informado na planilha da revisão. ';
        } else {
            $texto .= 'A BaseFull ainda não foi importada no Censo. Foram usados os líderes disponíveis na planilha da revisão. ';
        }
        $texto .= $numero($painel['sem_destino']) . ' pendente(s) sem responsável contatável.';
        return ['tipo' => ($erroAndamento || !$hierarquia['carga']) ? 'aviso' : 'ok', 'texto' => $texto];
    } catch (Throwable $e) {
        $texto = $erroAndamento
            ? 'Não foi possível atualizar o andamento no Horacius: ' . $erroAndamento . '. '
            : 'Andamento atualizado no Horacius. ';
        return ['tipo' => $erroAndamento ? 'erro' : 'aviso',
                'texto' => $texto . 'Não foi possível cruzar os líderes com a BaseFull: ' . $e->getMessage()];
    }
}
