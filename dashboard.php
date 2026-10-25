<?php
/**
 * Painel de acompanhamento da revisão de acessos (a mais recente, ou ?r=id).
 * Andamento lido direto do Horacius; se a última leitura tem mais de 10
 * minutos, a abertura da tela já relê — a consulta é leve.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/services/revisao.php';
require_once __DIR__ . '/includes/services/atualizacao.php';
require_login();

$rev = revisao_escolhida();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$rev) {
        flash('aviso', 'Não há uma revisão disponível para atualizar.');
    } elseif (!csrf_ok()) {
        flash('erro', 'Sessão expirada. Tente novamente.');
    } elseif (($_POST['acao'] ?? '') === 'andamento') {
        $resultadoAtualizacao = painel_atualizar_revisao($rev);
        flash($resultadoAtualizacao['tipo'], $resultadoAtualizacao['texto']);
    } elseif (($_POST['acao'] ?? '') === 'banco' && is_admin()) {
        // Troca o banco de TODOS e já relê esta revisão nele (as demais releem ao abrir)
        try {
            banco_escolher((string) ($_POST['banco'] ?? ''));
            $erro = andamento_garantir($rev, true);
            $erro ? flash('erro', 'Banco trocado para ' . horacius_rotulo() . ', mas a leitura falhou: ' . $erro)
                  : flash('ok', 'Banco trocado para ' . horacius_rotulo() . ' — andamento relido agora.');
        } catch (Throwable $e) {
            flash('erro', $e->getMessage());
        }
    }
    $destinoAtualizacao = BASE_URL . '/dashboard.php' . ($rev ? '?r=' . (int) $rev['id'] : '');
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['redirect' => $destinoAtualizacao], JSON_UNESCAPED_SLASHES);
        exit;
    }
    header('Location: ' . $destinoAtualizacao);
    exit;
}

$erroAnd = null;
if ($rev) {
    $erroAnd = andamento_garantir($rev) ?? $rev['andamento_erro'];
    $vinc    = painel_por_vinculo((int) $rev['id']);
    $grupos  = painel_por_grupo($vinc);
    $totC = array_sum(array_column($vinc, 'concluido'));
    $totP = array_sum(array_column($vinc, 'pendente'));
    // Filtro do ranking de líderes por vínculo (múltipla seleção). Sem escolha na URL,
    // começa nos vínculos da meta (Professor desmarcado); o formulário marca lvf=1 e
    // aí vale o que veio — nada marcado = todos.
    $prioVinculos = isset($_GET['lvf']) || isset($_GET['lv'])
        ? array_values(array_intersect(array_keys(VINCULOS), (array) ($_GET['lv'] ?? [])))
        : array_keys(array_filter(VINCULOS, fn($g) => $g === GRUPO_META));
    $prioridades = painel_prioridades((int) $rev['id'], $prioVinculos);
}
$todas = revisoes_lista();
$n  = fn($x) => number_format((int) $x, 0, ',', '.');
$dh = fn($s) => $s ? date('d/m/Y H:i', strtotime($s)) : '—';

$pageTitle = 'Painel da Revisão';
require_once __DIR__ . '/includes/views/header.php';
?>

<?php if (!$rev): ?>
  <div class="card"><div class="card-body text-center py-5 empty-state">
    <i class="bi bi-clipboard2-plus fs-1 text-muted"></i>
    <h5 class="mt-3">Nenhuma revisão iniciada ainda</h5>
    <p class="text-muted">Uma revisão começa com o envio da base de pessoas — quem entra e com qual vínculo.
      O andamento vem sozinho do Horacius.</p>
    <?php if (is_admin()): ?>
      <a href="<?= BASE_URL ?>/revisao_nova.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Iniciar nova revisão</a>
    <?php else: ?>
      <p class="small text-muted mb-0">Peça a um administrador para iniciar a revisão.</p>
    <?php endif; ?>
  </div></div>
<?php else: ?>

<section class="overview-banner" aria-label="Resumo da revisão">
  <div>
    <div class="eyebrow">Cada acesso conta</div>
    <h2>Uma visão clara. Uma revisão mais segura.</h2>
    <p>Da visão geral ao próximo passo: acompanhe o progresso e priorize quem ainda precisa revisar.</p>
  </div>
  <a href="<?= BASE_URL ?>/pendentes.php?r=<?= (int) $rev['id'] ?>" class="btn">Ver pendentes <i class="bi bi-arrow-up-right ms-2" aria-hidden="true"></i></a>
</section>
<?php $gm = $grupos[GRUPO_META] ?? ['concluido' => 0, 'pendente' => 0]; $tm = $gm['concluido'] + $gm['pendente']; ?>
<!-- O número que conta é o do grupo da meta; os demais ficam ao lado, menores -->
<section class="summary-grid summary-meta" aria-label="Indicadores da revisão">
  <div class="summary-card summary-card-meta">
    <div class="summary-label">Meta da revisão · <?= e(GRUPO_META) ?> <i class="bi bi-bullseye" aria-hidden="true"></i></div>
    <strong class="summary-value"><?= pct($gm['concluido'], $tm) ?>%</strong>
    <div class="meter my-2" role="img" aria-label="<?= pct($gm['concluido'], $tm) ?>% concluído"><span style="width:<?= $tm ? round(100 * $gm['concluido'] / $tm, 2) : 0 ?>%"></span></div>
    <div class="summary-note"><strong><?= $n($gm['concluido']) ?></strong> de <?= $n($tm) ?> já revisaram · <strong><?= $n($gm['pendente']) ?></strong> pendentes</div>
  </div>
  <?php foreach ($grupos as $nomeG => $g): if ($nomeG === GRUPO_META) continue; $t = $g['concluido'] + $g['pendente']; ?>
    <div class="summary-card"><div class="summary-label"><?= e($nomeG) ?> <i class="bi bi-mortarboard" aria-hidden="true"></i></div><strong class="summary-value"><?= pct($g['concluido'], $t) ?>%</strong><div class="summary-note"><?= $n($g['pendente']) ?> pendentes de <?= $n($t) ?></div></div>
  <?php endforeach; ?>
  <div class="summary-card"><div class="summary-label">Geral — todos os vínculos <i class="bi bi-people" aria-hidden="true"></i></div><strong class="summary-value"><?= pct($totC, $totC + $totP) ?>%</strong><div class="summary-note"><?= $n($totP) ?> pendentes de <?= $n($totC + $totP) ?></div></div>
</section>

<!-- Cabeçalho: qual revisão, desde quando, de onde vem cada número -->
<div class="card mb-4 review-context">
  <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div class="min-w-0">
      <div class="text-uppercase small fw-bold text-muted" style="letter-spacing:.08em">Revisão</div>
      <?php if (count($todas) > 1): ?>
        <form method="GET" class="d-inline">
          <select name="r" class="form-select form-select-lg fw-bold border-0 ps-0 bg-transparent" style="width:auto" onchange="this.form.submit()" aria-label="Escolher revisão">
            <?php foreach ($todas as $t): ?>
              <option value="<?= (int) $t['id'] ?>" <?= (int) $t['id'] === (int) $rev['id'] ? 'selected' : '' ?>><?= e($t['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      <?php else: ?>
        <div class="fs-5 fw-bold"><?= e($rev['nome']) ?></div>
      <?php endif; ?>
      <div class="small text-muted">
        Desde <?= date('d/m/Y', strtotime($rev['inicio'])) ?> ·
        andamento lido no Horacius (<?= e(horacius_rotulo($rev['andamento_banco'] ?? '')) ?>) em <strong><?= $dh($rev['andamento_em']) ?></strong>
      </div>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
      <?php $bancos = bancos(); $atual = banco_atual(); ?>
      <?php if (count($bancos) > 1): ?>
        <!-- Banco do andamento: vale para todos; só administrador troca -->
        <form method="POST" class="seg-banco" title="De qual banco do Horacius o andamento é lido">
          <?= csrf_field() ?><input type="hidden" name="acao" value="banco">
          <?php foreach ($bancos as $k => $b): ?>
            <button name="banco" value="<?= e($k) ?>" class="<?= $k === $atual ? 'on' : '' ?> <?= $k === 'producao' ? 'prod' : '' ?>"
                    <?= (!is_admin() || $k === $atual) ? 'disabled' : '' ?>
                    <?= is_admin() && $k !== $atual ? 'data-confirm="Ler o andamento de ' . e($b['rotulo'] ?? $k) . '?" data-confirm-text="Vale para todos os usuários. O andamento é relido agora nesse banco." data-confirm-icon="question" data-confirm-btn="Sim, trocar"' : '' ?>>
              <?= e($b['rotulo'] ?? $k) ?>
            </button>
          <?php endforeach; ?>
        </form>
      <?php endif; ?>
      <form method="POST" action="<?= BASE_URL ?>/dashboard.php?r=<?= (int) $rev['id'] ?>" data-refresh-review>
        <?= csrf_field() ?><input type="hidden" name="acao" value="andamento">
        <button type="submit" class="btn btn-outline-primary" aria-controls="reviewRefreshStatus">
          <i class="bi bi-arrow-clockwise me-1" data-refresh-icon aria-hidden="true"></i>
          <span class="spinner-border spinner-border-sm me-1" data-refresh-spinner aria-hidden="true" hidden></span>
          <span data-refresh-label>Atualizar andamento</span>
        </button>
      </form>
      <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/revisao.php?id=<?= (int) $rev['id'] ?>">
        <i class="bi bi-folder2-open me-1"></i> Dados e arquivos
      </a>
      <?php if (is_admin()): ?>
        <a class="btn btn-primary" href="<?= BASE_URL ?>/revisao_nova.php"><i class="bi bi-plus-lg me-1"></i> Nova revisão</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="review-refresh-status" id="reviewRefreshStatus" role="status" aria-live="polite" hidden>
  <span class="spinner-border" aria-hidden="true"></span>
  <div><strong>Atualizando a revisão…</strong><p>Baixando a BaseFull do dia do SharePoint, consultando o Horacius e cruzando os líderes. O painel será recarregado ao concluir.</p><small>Quando há BaseFull nova, a atualização leva cerca de um minuto.</small></div>
</div>
<div class="alert alert-danger" data-refresh-error role="alert" hidden></div>

<?php if ($erroAnd): ?>
  <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i>
    Não consegui ler o andamento no Horacius — os números abaixo são da última leitura boa.
    <div class="small mt-1 text-muted"><?= e($erroAnd) ?></div></div>
<?php endif; ?>

<!-- Os grupos: percentual + barra (uma pizza de duas fatias diz o mesmo, pior) -->
<div class="row g-4 mb-4">
  <?php foreach ($grupos as $nomeG => $g): $t = $g['concluido'] + $g['pendente']; $p = $t ? 100 * $g['concluido'] / $t : 0; ?>
    <div class="col-lg-6">
      <div class="card h-100 group-card"><div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div>
            <div class="fw-bold"><?= e($nomeG) ?><?= $nomeG === GRUPO_META ? ' <span class="badge badge-admin ms-1">meta da revisão</span>' : '' ?></div>
            <div class="small text-muted"><?= $n($t) ?> pessoas na revisão</div>
          </div>
          <a href="<?= BASE_URL ?>/pendentes.php?r=<?= (int) $rev['id'] ?>&amp;grupo=<?= urlencode($nomeG) ?>" class="small">ver pendentes</a>
        </div>
        <div class="d-flex align-items-baseline gap-2">
          <span class="painel-pct"><?= pct($g['concluido'], $t) ?>%</span>
          <span class="text-muted">concluído</span>
        </div>
        <div class="meter my-2" role="img" aria-label="<?= pct($g['concluido'], $t) ?>% concluído"><span style="width:<?= round($p, 2) ?>%"></span></div>
        <div class="d-flex justify-content-between small">
          <span><span class="dot dot-ok"></span> <?= $n($g['concluido']) ?> concluído<?= $g['concluido'] === 1 ? '' : 's' ?></span>
          <span><span class="dot dot-pend"></span> <?= $n($g['pendente']) ?> pendente<?= $g['pendente'] === 1 ? '' : 's' ?> (<?= pct($g['pendente'], $t) ?>%)</span>
        </div>
      </div></div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Por vínculo: barra 0–100% com os números escritos + pendentes ao lado -->
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>Status da revisão por vínculo</span>
    <span class="small fw-normal text-muted"><span class="dot dot-ok"></span> Concluído &nbsp; <span class="dot dot-pend"></span> Pendente</span>
  </div>
  <div class="card-body">
    <?php foreach ($vinc as $v => $x): $t = $x['concluido'] + $x['pendente']; if (!$t) continue; $p = 100 * $x['concluido'] / $t; ?>
      <div class="vinc-linha">
        <div class="vinc-nome"><?= e($v) ?></div>
        <div class="vinc-barra" title="<?= e($v) ?>: <?= $n($x['concluido']) ?> concluídos, <?= $n($x['pendente']) ?> pendentes (<?= pct($x['concluido'], $t) ?>%)">
          <span class="seg-ok" style="width:<?= round($p, 2) ?>%"><?= $p >= 12 ? $n($x['concluido']) : '' ?></span>
          <span class="seg-pend" style="width:<?= round(100 - $p, 2) ?>%"></span>
        </div>
        <div class="vinc-pct"><?= pct($x['concluido'], $t) ?>%</div>
        <a class="vinc-pend" href="<?= BASE_URL ?>/pendentes.php?r=<?= (int) $rev['id'] ?>&amp;vinculo=<?= urlencode($v) ?>" title="Ver os pendentes de <?= e($v) ?>">
          <?= $n($x['pendente']) ?> <span>pendentes</span>
        </a>
      </div>
    <?php endforeach; ?>
    <div class="vinc-linha vinc-total">
      <div class="vinc-nome">Total</div>
      <div class="small text-muted"><?= $n($totC) ?> de <?= $n($totC + $totP) ?> pessoas já revisadas</div>
      <div class="vinc-pct"><?= pct($totC, $totC + $totP) ?>%</div>
      <a class="vinc-pend" href="<?= BASE_URL ?>/pendentes.php?r=<?= (int) $rev['id'] ?>"><?= $n($totP) ?> <span>pendentes</span></a>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/views/evolucao_view.php'; ?>
<?php require __DIR__ . '/includes/views/painel_prioridades_view.php'; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/views/footer.php'; ?>
