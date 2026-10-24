<?php
/**
 * Painel de acompanhamento da revisão de acessos (a mais recente, ou ?r=id).
 * Andamento lido direto do Horacius; se a última leitura tem mais de 10
 * minutos, a abertura da tela já relê — a consulta é leve.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/revisao.php';
require_login();

$rev = revisao_escolhida();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $rev) {
    if (!csrf_ok()) {
        flash('erro', 'Sessão expirada. Tente novamente.');
    } elseif (($_POST['acao'] ?? '') === 'andamento') {
        $erro = andamento_garantir($rev, true);
        $erro ? flash('erro', 'Não consegui ler o andamento no Horacius: ' . $erro)
              : flash('ok', 'Andamento atualizado agora, direto do Horacius.');
    }
    header('Location: ' . BASE_URL . '/dashboard.php?r=' . (int) $rev['id']);
    exit;
}

$erroAnd = null;
if ($rev) {
    $erroAnd = andamento_garantir($rev) ?? $rev['andamento_erro'];
    $vinc    = painel_por_vinculo((int) $rev['id']);
    $grupos  = painel_por_grupo($vinc);
    $totC = array_sum(array_column($vinc, 'concluido'));
    $totP = array_sum(array_column($vinc, 'pendente'));
}
$todas = revisoes_lista();
$n  = fn($x) => number_format((int) $x, 0, ',', '.');
$dh = fn($s) => $s ? date('d/m/Y H:i', strtotime($s)) : '—';

$pageTitle = 'Painel da Revisão';
require_once __DIR__ . '/includes/header.php';
?>

<?php if (!$rev): ?>
  <div class="card"><div class="card-body text-center py-5">
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

<!-- Cabeçalho: qual revisão, desde quando, de onde vem cada número -->
<div class="card mb-4">
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
        andamento lido no Horacius (<?= e(horacius_rotulo()) ?>) em <strong><?= $dh($rev['andamento_em']) ?></strong>
      </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <form method="POST"><?= csrf_field() ?><input type="hidden" name="acao" value="andamento">
        <button class="btn btn-outline-primary"><i class="bi bi-arrow-clockwise me-1"></i> Atualizar andamento</button>
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

<?php if ($erroAnd): ?>
  <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i>
    Não consegui ler o andamento no Horacius — os números abaixo são da última leitura boa.
    <div class="small mt-1 text-muted"><?= e($erroAnd) ?></div></div>
<?php endif; ?>

<!-- Os grupos: percentual + barra (uma pizza de duas fatias diz o mesmo, pior) -->
<div class="row g-4 mb-4">
  <?php foreach ($grupos as $nomeG => $g): $t = $g['concluido'] + $g['pendente']; $p = $t ? 100 * $g['concluido'] / $t : 0; ?>
    <div class="col-lg-6">
      <div class="card h-100"><div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div>
            <div class="fw-bold"><?= e($nomeG) ?></div>
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
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
