<?php // Recebe $prioridades, $rev, $n, $dh, $totC e $totP do painel.
$prioUrl = fn(array $f = []) => BASE_URL . '/pendentes.php?' . http_build_query(['r' => (int) $rev['id']] + $f);
$prioTodos = ($_GET['lideres'] ?? '') === 'todos';
$prioLista = $prioTodos ? $prioridades['lideres'] : array_slice($prioridades['lideres'], 0, 10);
?>
<section class="priority-section" id="prioridades" aria-labelledby="priorityTitle">
  <div class="priority-heading">
    <div><div class="eyebrow">Do acompanhamento à ação</div><h2 id="priorityTitle">Onde atuar para concluir a revisão</h2><p>Priorize as equipes com maior volume e trate as pendências que precisam de atenção.</p></div>
    <a href="<?= e($prioUrl()) ?>" class="btn btn-outline-primary"><i class="bi bi-list-check me-1" aria-hidden="true"></i> Todas as pendências</a>
  </div>
  <div class="hierarchy-source <?= $prioridades['hierarquia']['carga'] ? '' : 'hierarchy-source-empty' ?>">
    <i class="bi bi-diagram-3" aria-hidden="true"></i>
    <div>
      <?php if ($prioridades['hierarquia']['carga']): ?>
        <strong>BaseFull importada em <?= $dh($prioridades['hierarquia']['carga']['enviado_em']) ?></strong>
        <p>Cruzamento de toda a revisão: <?= $n($prioridades['hierarquia']['basefull']) ?> pessoa(s) com líder encontrado na BaseFull; <?= $n($prioridades['hierarquia']['base_revisao']) ?> usando o líder da planilha como alternativa.</p>
      <?php else: ?>
        <strong>BaseFull ainda não importada</strong>
        <p>O painel usa os líderes informados na planilha da revisão. Importe a BaseFull em Base de funcionários para ampliar a identificação dos responsáveis.</p>
      <?php endif; ?>
    </div>
    <?php if (is_admin()): ?><a href="<?= BASE_URL ?>/funcionarios.php" class="btn btn-sm btn-outline-secondary text-nowrap"><?= $prioridades['hierarquia']['carga'] ? 'Conferir BaseFull' : 'Importar BaseFull' ?></a><?php endif; ?>
  </div>
  <?php if (!$prioridades['pendentes']): ?>
    <div class="alert alert-success"><i class="bi bi-check2-circle me-1" aria-hidden="true"></i> Nenhuma pendência na base desta revisão. Todas as pessoas enviadas já têm revisão registrada na última leitura.</div>
  <?php else: ?>
  <div class="priority-metrics">
    <a class="priority-metric" href="#ranking-lideres"><span><i class="bi bi-bullseye" aria-hidden="true"></i> Concentração nos 5 primeiros</span><strong><?= pct($prioridades['top5_pendentes'], $totP) ?>%</strong><small><?= $n($prioridades['top5_pendentes']) ?> das <?= $n($totP) ?> pendências</small></a>
    <a class="priority-metric" href="<?= e($prioUrl(['cobranca' => 'nunca'])) ?>"><span><i class="bi bi-envelope" aria-hidden="true"></i> Ainda sem cobrança enviada</span><strong><?= $n($prioridades['nunca_cobrados']) ?></strong><small>Pessoas pendentes sem envio bem-sucedido</small></a>
    <a class="priority-metric" href="<?= e($prioUrl(['cobranca' => '7dias'])) ?>"><span><i class="bi bi-clock-history" aria-hidden="true"></i> Cobrança há 7 dias ou mais</span><strong><?= $n($prioridades['cobrados_7dias']) ?></strong><small>Pendentes com último envio há pelo menos 7 dias</small></a>
    <a class="priority-metric priority-metric-attention" href="<?= e($prioUrl(['contato' => 'sem_destino'])) ?>"><span><i class="bi bi-person-exclamation" aria-hidden="true"></i> Sem responsável contatável</span><strong><?= $n($prioridades['sem_destino']) ?></strong><small>Pendências que exigem ajuste na identificação do líder</small></a>
  </div>
  <div class="priority-layout">
    <div class="card min-w-0" id="ranking-lideres">
      <div class="card-header d-flex flex-wrap justify-content-between gap-2"><span>Líderes com mais pendências na equipe</span><span class="small text-muted fw-normal"><?= $n(count($prioridades['lideres'])) ?> líderes com pendências</span></div>
      <div class="card-body p-0"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0 priority-table">
          <caption class="visually-hidden">Equipes ordenadas pela quantidade de pessoas pendentes. O impacto representa a participação no total de pendências da revisão.</caption>
          <thead><tr><th class="ps-4" scope="col">Líder / equipe</th><th scope="col">Pendentes / impacto</th><th scope="col">Progresso da equipe</th><th scope="col">Sem cobrança</th><th scope="col">Último envio</th><th scope="col"><span class="visually-hidden">Ações</span></th></tr></thead>
          <tbody>
          <?php foreach ($prioLista as $prioIndice => $prioLider): ?>
            <tr>
              <th scope="row" class="ps-4 fw-normal"><div class="leader-identity"><span class="leader-rank"><?= $prioIndice + 1 ?></span><div><strong><?= e($prioLider['nome']) ?></strong><small><?= e($prioLider['email']) ?></small></div></div></th>
              <td><strong class="priority-count"><?= $n($prioLider['pendentes']) ?></strong><span class="priority-impact"><?= pct($prioLider['pendentes'], $totP) ?>% do restante</span><div class="priority-bar" aria-hidden="true"><span style="width:<?= $totP ? round(100 * $prioLider['pendentes'] / $totP, 2) : 0 ?>%"></span></div></td>
              <td><strong><?= pct($prioLider['concluidos'], $prioLider['total']) ?>%</strong><small class="d-block text-muted"><?= $n($prioLider['concluidos']) ?> de <?= $n($prioLider['total']) ?> pessoas</small></td>
              <td><?= $n($prioLider['nunca_cobrados']) ?></td>
              <td class="small text-nowrap"><?= $prioLider['ultima_cobranca'] ? $dh($prioLider['ultima_cobranca']) : 'Sem envio' ?></td>
              <td class="pe-4"><a class="btn btn-sm btn-outline-primary text-nowrap" href="<?= e($prioUrl(['lider' => $prioLider['email']])) ?>" aria-label="Ver pendentes da equipe de <?= e($prioLider['nome']) ?>">Ver equipe <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$prioLista): ?><tr><td colspan="6" class="text-center py-4 text-muted">As pendências atuais estão sem responsável contatável. Confira os motivos ao lado.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div></div>
      <div class="priority-table-foot"><span>Ranking por pessoas pendentes na equipe; não indica a revisão pessoal do líder.</span>
        <?php if (count($prioridades['lideres']) > 10): ?><a href="?<?= e(http_build_query(['r' => (int) $rev['id']] + ($prioTodos ? [] : ['lideres' => 'todos']))) ?>#ranking-lideres"><?= $prioTodos ? 'Mostrar os 10 primeiros' : 'Ver todos os líderes' ?></a><?php endif; ?>
      </div>
    </div>
    <aside class="priority-insights" aria-label="Informações para priorizar">
      <div class="card"><div class="card-body">
        <div class="eyebrow">Potencial de avanço</div><h3>Comece pelo maior volume</h3>
        <?php if ($prioridades['top5_pendentes']): ?>
          <p>Concluir as pendências dos <strong><?= min(5, count($prioridades['lideres'])) ?> primeiros líderes</strong> acrescentaria <strong><?= pct($prioridades['top5_pendentes'], $prioridades['total']) ?> pontos percentuais</strong> ao progresso geral.</p>
          <div class="priority-projection"><span>Progresso atual<strong><?= pct($totC, $prioridades['total']) ?>%</strong></span><i class="bi bi-arrow-right" aria-hidden="true"></i><span>Com essas conclusões<strong><?= pct($totC + $prioridades['top5_pendentes'], $prioridades['total']) ?>%</strong></span></div>
          <small class="text-muted">Cenário calculado sobre as pendências atuais, sem previsão de prazo.</small>
        <?php else: ?><p>Identifique os responsáveis pelas pendências para viabilizar o acompanhamento por equipe.</p><?php endif; ?>
      </div></div>
      <div class="card"><div class="card-body">
        <div class="eyebrow">O que compõe o restante</div><h3>Pendências por vínculo</h3>
        <?php foreach ($prioridades['vinculos'] as $prioVinculo => $prioQuantidade): ?>
          <a class="priority-breakdown" href="<?= e($prioUrl(['vinculo' => $prioVinculo])) ?>"><span><?= e($prioVinculo) ?><small><?= pct($prioQuantidade, $totP) ?>% das pendências</small></span><strong><?= $n($prioQuantidade) ?></strong></a>
        <?php endforeach; ?>
      </div></div>
      <div class="card"><div class="card-body">
        <div class="eyebrow">Qualidade dos dados</div><h3>Responsáveis a identificar</h3>
        <?php foreach ($prioridades['motivos'] as $prioMotivo => $prioQuantidade): ?>
          <a class="priority-breakdown" href="<?= e($prioUrl(['contato' => 'sem_destino', 'motivo' => $prioMotivo])) ?>"><span><?= e($prioMotivo) ?></span><strong><?= $n($prioQuantidade) ?></strong></a>
        <?php endforeach; ?>
        <?php if (!$prioridades['sem_destino']): ?><p class="mb-0"><i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Todos os pendentes têm um líder com e-mail identificado.</p><?php elseif (is_admin()): ?><a href="<?= BASE_URL ?>/funcionarios.php" class="btn btn-sm btn-outline-secondary mt-3">Conferir base de funcionários</a><?php endif; ?>
      </div></div>
    </aside>
  </div>
  <p class="priority-method">Os indicadores usam a última leitura do andamento e a BaseFull importada mais recente. O cruzamento prioriza o líder encontrado pelo CPF na BaseFull; se não houver um contato válido, usa o líder informado na planilha da revisão. Cobranças contam apenas envios bem-sucedidos nesta revisão; o último envio considera as pessoas ainda pendentes, e os grupos de atenção podem se sobrepor. A presença de uma revisão registrada segue o mesmo critério do painel.</p>
  <?php endif; ?>
</section>

