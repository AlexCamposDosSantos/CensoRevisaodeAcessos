<?php
/**
 * Uma cobrança: progresso do envio, cada e-mail (para quem, cópia, quantas
 * pessoas, situação, erro), ver o e-mail exatamente como saiu, reenviar os que
 * falharam, e as pessoas que ficaram sem destinatário.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/services/cobranca.php';
require_login();

$c = cobranca((int) ($_GET['id'] ?? 0));
if (!$c) { flash('erro', 'Cobrança não encontrada.'); header('Location: ' . BASE_URL . '/cobrancas.php'); exit; }
$id = (int) $c['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (csrf_ok() && ($_POST['acao'] ?? '') === 'reenviar') {
        cobranca_reenviar_erros($id);
        header('Location: ' . BASE_URL . '/cobranca.php?id=' . $id . '&enviar=1');
        exit;
    }
    header('Location: ' . BASE_URL . '/cobranca.php?id=' . $id);
    exit;
}

$envios = cobranca_envios($id);
$sem    = json_decode($c['sem_destino_json'], true) ?: [];
$pend   = (int) $c['envios'] - (int) $c['enviados'] - (int) $c['erros'];
$n = fn($x) => number_format((int) $x, 0, ',', '.');
$rotSt = ['pendente' => ['Na fila', 'badge-user'], 'enviado' => ['Enviado', 'badge-ativo'], 'erro' => ['Erro', 'badge-erro']];

$pageTitle = 'Cobrança #' . $id;
require_once __DIR__ . '/includes/views/header.php';
?>

<div class="mb-3 d-flex gap-2">
  <a href="<?= BASE_URL ?>/cobrancas.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Histórico</a>
  <a href="<?= BASE_URL ?>/cobranca_nova.php" class="btn btn-sm btn-outline-primary"><i class="bi bi-send me-1"></i> Nova cobrança</a>
</div>

<div class="card mb-4"><div class="card-body">
  <div class="d-flex flex-wrap justify-content-between gap-3">
    <div>
      <div class="fw-bold fs-5"><?= e($c['modelo_nome']) ?></div>
      <div class="small text-muted">
        <?= e($c['revisao_nome'] ?? '—') ?> · <?= $c['alvo'] === 'superior' ? 'líder do gestor (escalonamento)' : 'gestor (líder imediato)' ?>
        · de <?= e($c['remetente']) ?> · por <?= e($c['criado_por_nome'] ?? '—') ?> em <?= date('d/m/Y H:i', strtotime($c['criado_em'])) ?>
        <?= $c['prazo'] !== '' ? ' · prazo: ' . e($c['prazo']) : '' ?>
      </div>
      <?php $ne = lista_emails((string) ($c['nao_enviar'] ?? ''))[0]; if ($ne): ?>
        <details class="small mt-1"><summary class="text-muted"><i class="bi bi-slash-circle me-1"></i>Não enviar para: <?= count($ne) ?> e-mail(s) nesta cobrança</summary>
          <div class="mt-1 font-monospace"><?= e(implode(', ', $ne)) ?></div></details>
      <?php endif; ?>
    </div>
    <?php if ((int) $c['erros'] > 0 && $pend === 0): ?>
      <form method="POST"><?= csrf_field() ?><input type="hidden" name="acao" value="reenviar">
        <button class="btn btn-outline-danger"><i class="bi bi-arrow-repeat me-1"></i> Reenviar os <?= $n($c['erros']) ?> com erro</button></form>
    <?php endif; ?>
  </div>
  <div class="mt-3">
    <div class="d-flex justify-content-between small mb-1">
      <span id="progTexto"><?= $n($c['enviados']) ?> de <?= $n($c['envios']) ?> enviados<?= (int) $c['erros'] ? ' · ' . $n($c['erros']) . ' com erro' : '' ?><?= $pend ? ' · ' . $n($pend) . ' na fila' : '' ?></span>
      <span><?= $n($c['pessoas']) ?> pessoas cobradas<?= (int) $c['sem_destino'] ? ' · ' . $n($c['sem_destino']) . ' sem destinatário' : '' ?></span>
    </div>
    <div class="meter"><span id="progBarra" style="width:<?= $c['envios'] ? round(100 * ($c['enviados'] + $c['erros']) / $c['envios'], 1) : 0 ?>%"></span></div>
  </div>
</div></div>

<div class="card mb-4"><div class="card-body p-0"><div class="table-responsive">
  <table class="table table-hover align-middle mb-0 small">
    <thead class="table-light"><tr><th class="ps-4">Para</th><th>Cc</th><th>Cco</th><th class="text-end">Pessoas</th><th>Situação</th><th>Enviado em</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($envios as $e): [$rot, $cls] = $rotSt[$e['status']]; ?>
        <tr>
          <td class="ps-4"><div class="fw-semibold"><?= e($e['para_nome']) ?></div><div class="text-muted"><?= e($e['para_email']) ?></div>
            <?php if (($e['delegados'] ?? '') !== ''): ?><div class="mt-1"><span class="tag-copia">Delegado</span> <span class="text-muted"><?= e($e['delegados']) ?></span></div><?php endif; ?></td>
          <td class="text-muted"><?= e($e['cc']) ?: '—' ?></td>
          <td class="text-muted"><?= e($e['cco'] ?? '') ?: '—' ?></td>
          <td class="text-end fw-semibold"><?= $n($e['qtd']) ?></td>
          <td><span class="badge <?= $cls ?>"><?= $rot ?></span><?php if ($e['erro']): ?><div class="text-danger mt-1"><?= e(mb_strimwidth($e['erro'], 0, 160, '…')) ?></div><?php endif; ?></td>
          <td><?= $e['enviado_em'] ? date('d/m/Y H:i', strtotime($e['enviado_em'])) : '—' ?></td>
          <td class="text-end pe-4"><button type="button" class="btn btn-sm btn-outline-primary btn-ver" data-envio="<?= (int) $e['id'] ?>" title="Ver o e-mail"><i class="bi bi-eye"></i></button></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div></div></div>

<?php if ($sem): ?>
  <div class="card border-warning"><div class="card-header"><i class="bi bi-person-x me-1 text-warning"></i> <?= $n(count($sem)) ?> pessoa(s) que ficaram sem destinatário</div>
    <div class="card-body"><details><summary class="small">Ver a lista</summary>
      <div class="table-responsive mt-2" style="max-height:320px"><table class="table table-sm small mb-0">
        <thead><tr><th>Código</th><th>Nome</th><th>Vínculo</th><th>Motivo</th></tr></thead><tbody>
        <?php foreach ($sem as $s): ?><tr><td><?= (int) $s['codigo'] ?></td><td><?= e($s['nome']) ?></td><td><?= e($s['vinculo']) ?></td><td><?= e($s['motivo']) ?></td></tr><?php endforeach; ?>
      </tbody></table></div></details></div></div>
<?php endif; ?>

<div class="modal fade" id="modalEmail" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">E-mail como foi registrado</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body p-0" style="background:#f4f4f4"><iframe id="frameEmail" style="width:100%;height:75vh;border:0"></iframe></div>
</div></div></div>

<?php
$extraJs = '<script>
document.querySelectorAll(".btn-ver").forEach(function (b) {
  b.addEventListener("click", function () {
    document.getElementById("frameEmail").src = "' . BASE_URL . '/cobranca_previa.php?envio=" + b.dataset.envio;
    new bootstrap.Modal(document.getElementById("modalEmail")).show();
  });
});
' . ((isset($_GET['enviar']) && $pend > 0) ? '
// Envio em lotes: cada chamada manda até 5 e-mails; repete até a fila acabar
(function enviar() {
  var dados = new FormData(); dados.append("csrf", ' . json_encode(csrf_token()) . ');
  fetch("' . BASE_URL . '/cobranca_enviar.php?id=' . $id . '", { method: "POST", body: dados, credentials: "same-origin" })
    .then(function (r) { return r.json(); })
    .then(function (j) {
      if (!j.ok) { document.getElementById("progTexto").textContent = "Parou: " + j.erro; return; }
      var feitos = j.enviados + j.erros;
      document.getElementById("progBarra").style.width = (j.total ? 100 * feitos / j.total : 100) + "%";
      document.getElementById("progTexto").textContent = "Enviando… " + j.enviados + " de " + j.total + (j.erros ? " · " + j.erros + " com erro" : "");
      if (j.pendentes > 0) enviar(); else location.replace("' . BASE_URL . '/cobranca.php?id=' . $id . '");
    })
    .catch(function () { document.getElementById("progTexto").textContent = "Falha de rede — recarregue a página para continuar de onde parou."; });
})();' : '') . '
</script>';
require_once __DIR__ . '/includes/views/footer.php';
