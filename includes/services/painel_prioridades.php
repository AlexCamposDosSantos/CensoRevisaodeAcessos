<?php
/** Priorização da revisão: mesmas regras de status, hierarquia e envios do Censo. */
require_once __DIR__ . '/cobranca.php';

/**
 * Carrega todos os participantes enviados; não usa o limite visual da lista de pendentes.
 * $vinculos filtra SÓ o ranking de líderes ('ranking'): os demais números seguem gerais.
 */
function painel_prioridades(int $revId, array $vinculos = []): array
{
    $pessoas = array_values(array_filter(base_pessoas($revId), fn($p) => $p['status'] !== 'fora'));
    $mapa = lideres_das_pessoas($pessoas);
    $cobradas = pessoas_cobradas($revId);
    $painel = painel_prioridades_agrupar($pessoas, $mapa, $cobradas);
    $vinculos = array_values(array_intersect(array_keys(VINCULOS), $vinculos));
    if ($vinculos && count($vinculos) < count(VINCULOS)) {
        $sub = painel_prioridades_agrupar(array_values(array_filter($pessoas, fn($p) => in_array($p['vinculo'], $vinculos, true))), $mapa, $cobradas);
        $painel['ranking'] = ['vinculos' => $vinculos, 'lideres' => $sub['lideres'], 'pendentes' => $sub['pendentes']];
    } else {
        $painel['ranking'] = ['vinculos' => [], 'lideres' => $painel['lideres'], 'pendentes' => $painel['pendentes']];
    }
    // O grupo da meta, sempre (independe do filtro do ranking): base do "Potencial de avanço"
    $daMeta = array_keys(array_filter(VINCULOS, fn($g) => $g === GRUPO_META));
    $m = (isset($sub) && $vinculos === $daMeta) ? $sub
       : painel_prioridades_agrupar(array_values(array_filter($pessoas, fn($p) => in_array($p['vinculo'], $daMeta, true))), $mapa, $cobradas);
    $painel['meta'] = ['total' => $m['total'], 'pendentes' => $m['pendentes'], 'concluidos' => $m['total'] - $m['pendentes'],
                       'top5_pendentes' => $m['top5_pendentes'], 'lideres' => count($m['lideres'])];
    $carga = funcionarios_carga_atual();
    $painel['hierarquia']['carga'] = $carga ? ['id' => (int) $carga['id'], 'enviado_em' => $carga['enviado_em']] : null;
    return $painel;
}

/** Agregação pura, para que totais e denominadores possam ser conferidos isoladamente. */
function painel_prioridades_agrupar(array $pessoas, array $lideres, array $cobradas, ?int $agora = null): array
{
    $agora ??= time();
    $out = ['total' => 0, 'pendentes' => 0, 'sem_destino' => 0, 'nunca_cobrados' => 0,
            'cobrados_pendentes' => 0, 'cobrados_7dias' => 0, 'lideres' => [], 'motivos' => [], 'vinculos' => [],
            'hierarquia' => ['basefull' => 0, 'base_revisao' => 0, 'sem_lider' => 0]];
    foreach ($pessoas as $p) {
        if ($p['status'] === 'fora') continue;
        $out['total']++;
        $pendente = $p['status'] === 'pendente';
        $lider = $lideres[(int) $p['codigo']];
        $email = $lider['ok'] ? strtolower(trim($lider['email'])) : '';
        if (!$lider['ok']) $out['hierarquia']['sem_lider']++;
        elseif (($lider['fonte'] ?? '') === 'base de funcionários') $out['hierarquia']['basefull']++;
        else $out['hierarquia']['base_revisao']++;
        $cb = $cobradas[(int) $p['codigo']] ?? null;
        if ($email !== '' && !isset($out['lideres'][$email])) {
            $out['lideres'][$email] = ['nome' => $lider['nome'], 'email' => $email, 'total' => 0,
                'concluidos' => 0, 'pendentes' => 0, 'nunca_cobrados' => 0, 'ultima_cobranca' => null];
        }
        if ($email !== '') {
            $out['lideres'][$email]['total']++;
            if (!$pendente) $out['lideres'][$email]['concluidos']++;
        }
        if (!$pendente) continue;
        $out['pendentes']++;
        $v = (string) $p['vinculo'];
        $out['vinculos'][$v] = ($out['vinculos'][$v] ?? 0) + 1;
        if ($cb) {
            $out['cobrados_pendentes']++;
            $ultima = $cb['ultima'] ?? null;
            $timestamp = $ultima ? strtotime($ultima) : false;
            if ($timestamp !== false && $timestamp <= $agora - 7 * 86400) $out['cobrados_7dias']++;
        } else {
            $out['nunca_cobrados']++;
        }
        if ($email === '') {
            $out['sem_destino']++;
            $motivo = $lider['motivo'] ?: 'Responsável não identificado';
            $out['motivos'][$motivo] = ($out['motivos'][$motivo] ?? 0) + 1;
        } else {
            $g = &$out['lideres'][$email];
            $g['pendentes']++;
            if (!$cb) $g['nunca_cobrados']++;
            if ($cb && ($cb['ultima'] ?? '') > ($g['ultima_cobranca'] ?? '')) $g['ultima_cobranca'] = $cb['ultima'];
            unset($g);
        }
    }
    $out['lideres'] = array_values(array_filter($out['lideres'], fn($g) => $g['pendentes'] > 0));
    usort($out['lideres'], fn($a, $b) => ($b['pendentes'] <=> $a['pendentes']) ?: strcasecmp($a['nome'], $b['nome']) ?: strcmp($a['email'], $b['email']));
    arsort($out['motivos']);
    arsort($out['vinculos']);
    $out['top5_pendentes'] = array_sum(array_column(array_slice($out['lideres'], 0, 5), 'pendentes'));
    return $out;
}

/** Filtros operacionais compartilhados pela lista e pela exportação CSV. */
function painel_filtrar_pendentes(array $pessoas, array $lideres, array $cobradas, array $f, ?int $agora = null): array
{
    $agora ??= time();
    return array_values(array_filter($pessoas, function ($p) use ($lideres, $cobradas, $f, $agora) {
        $l = $lideres[(int) $p['codigo']];
        $cb = $cobradas[(int) $p['codigo']] ?? null;
        if (($f['lider'] ?? '') !== '' && (!$l['ok'] || strtolower(trim($l['email'])) !== strtolower(trim($f['lider'])))) return false;
        if (($f['contato'] ?? '') === 'sem_destino' && $l['ok']) return false;
        if (($f['motivo'] ?? '') !== '' && ($l['ok'] || ($l['motivo'] ?: 'Responsável não identificado') !== $f['motivo'])) return false;
        if (($f['cobranca'] ?? '') === 'nunca' && $cb) return false;
        if (($f['cobranca'] ?? '') === 'cobrada' && !$cb) return false;
        if (($f['cobranca'] ?? '') === '7dias') {
            $data = !empty($cb['ultima']) ? strtotime($cb['ultima']) : false;
            if ($data === false || $data > $agora - 7 * 86400) return false;
        }
        return true;
    }));
}
