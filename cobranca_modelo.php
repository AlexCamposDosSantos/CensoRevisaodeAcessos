<?php
/**
 * Criar/editar modelo de cobrança: nome, assunto, topo e rodapé (imagens do
 * próprio Censo), corpo no editor e as variáveis disponíveis. A prévia monta
 * o e-mail de verdade com dados de exemplo.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/services/cobranca.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
$m  = $id ? modelo_cobranca($id) : null;
if ($id && !$m) { flash('erro', 'Modelo não encontrado.'); header('Location: ' . BASE_URL . '/cobranca_modelos.php'); exit; }
$imgs = imagens_email();
$m ??= ['nome' => '', 'assunto' => '', 'topo' => $imgs['topo'][0] ?? '', 'rodape' => $imgs['rodape'][0] ?? '', 'corpo' => '<p>Prezado(a) <strong>{{nome}}</strong>,</p><p></p>'];
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_admin()) {
    $m['nome']    = utf8(trim($_POST['nome'] ?? ''));
    $m['assunto'] = utf8(trim($_POST['assunto'] ?? ''));
    $m['topo']    = imagem_email($_POST['topo'] ?? '') ? basename($_POST['topo']) : '';
    $m['rodape']  = imagem_email($_POST['rodape'] ?? '') ? basename($_POST['rodape']) : '';
    $m['corpo']   = (string) ($_POST['corpo'] ?? '');
    if (!csrf_ok())             $erros[] = 'Sessão expirada. Tente novamente.';
    if ($m['nome'] === '')      $erros[] = 'Informe o nome do modelo.';
    if ($m['assunto'] === '')   $erros[] = 'Informe o assunto.';
    if (trim(strip_tags($m['corpo'])) === '') $erros[] = 'O corpo do e-mail está vazio.';
    if (!$erros) {
        if ($id) {
            db()->prepare("UPDATE modelos_cobranca SET nome = ?, assunto = ?, topo = ?, rodape = ?, corpo = ?, atualizado_em = datetime('now','localtime') WHERE id = ?")
                ->execute([$m['nome'], $m['assunto'], $m['topo'], $m['rodape'], $m['corpo'], $id]);
        } else {
            db()->prepare('INSERT INTO modelos_cobranca (nome, assunto, topo, rodape, corpo) VALUES (?, ?, ?, ?, ?)')
                ->execute([$m['nome'], $m['assunto'], $m['topo'], $m['rodape'], $m['corpo']]);
        }
        flash('ok', 'Modelo salvo.');
        header('Location: ' . BASE_URL . '/cobranca_modelos.php');
        exit;
    }
}

$pageTitle = $id ? 'Modelo de Cobrança' : 'Novo Modelo de Cobrança';
$soLeitura = !is_admin();
require_once __DIR__ . '/includes/views/header.php';
?>

<div class="mb-3"><a href="<?= BASE_URL ?>/cobranca_modelos.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Modelos</a></div>

<?php if ($erros): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<form method="POST" id="formModelo" class="row g-4">
  <?= csrf_field() ?>
  <div class="col-xl-8">
    <div class="card mb-4"><div class="card-body">
      <div class="row g-3">
        <div class="col-md-5">
          <label class="form-label fw-semibold" for="nome">Nome do modelo</label>
          <input id="nome" name="nome" class="form-control" value="<?= e($m['nome']) ?>" required <?= $soLeitura ? 'disabled' : '' ?>>
        </div>
        <div class="col-md-7">
          <label class="form-label fw-semibold" for="assunto">Assunto do e-mail</label>
          <input id="assunto" name="assunto" class="form-control" value="<?= e($m['assunto']) ?>" required <?= $soLeitura ? 'disabled' : '' ?>>
          <div class="form-text">Aceita variáveis, ex.: "Revisão pendente — {{qtd_pendentes}} pessoa(s)".</div>
        </div>
      </div>
    </div></div>

    <div class="card mb-4"><div class="card-header">Topo</div><div class="card-body">
      <div class="img-escolha">
        <label><input type="radio" name="topo" value="" <?= $m['topo'] === '' ? 'checked' : '' ?> <?= $soLeitura ? 'disabled' : '' ?>><span class="sem-img">Sem topo</span></label>
        <?php foreach ($imgs['topo'] as $img): ?>
          <label><input type="radio" name="topo" value="<?= e($img) ?>" <?= $m['topo'] === $img ? 'checked' : '' ?> <?= $soLeitura ? 'disabled' : '' ?>>
            <img src="<?= BASE_URL ?>/assets/email/<?= rawurlencode($img) ?>" alt="<?= e($img) ?>"></label>
        <?php endforeach; ?>
      </div>
    </div></div>

    <div class="card mb-4"><div class="card-header">Corpo do e-mail</div><div class="card-body">
      <textarea id="corpo" name="corpo" rows="18" class="form-control"><?= e($m['corpo']) ?></textarea>
    </div></div>

    <div class="card mb-4"><div class="card-header">Rodapé</div><div class="card-body">
      <div class="img-escolha">
        <label><input type="radio" name="rodape" value="" <?= $m['rodape'] === '' ? 'checked' : '' ?> <?= $soLeitura ? 'disabled' : '' ?>><span class="sem-img">Sem rodapé</span></label>
        <?php foreach ($imgs['rodape'] as $img): ?>
          <label><input type="radio" name="rodape" value="<?= e($img) ?>" <?= $m['rodape'] === $img ? 'checked' : '' ?> <?= $soLeitura ? 'disabled' : '' ?>>
            <img src="<?= BASE_URL ?>/assets/email/<?= rawurlencode($img) ?>" alt="<?= e($img) ?>"></label>
        <?php endforeach; ?>
      </div>
    </div></div>
  </div>

  <div class="col-xl-4">
    <div class="card mb-4 sticky-xl-top" style="top:20px">
      <div class="card-header">Variáveis</div>
      <div class="card-body">
        <p class="small text-muted">Clique para inserir no corpo. Cada destinatário recebe os próprios valores.</p>
        <?php foreach (VARIAVEIS_COBRANCA as $v => $d): ?>
          <button type="button" class="var-btn" data-var="<?= e($v) ?>" <?= $soLeitura ? 'disabled' : '' ?>><code><?= e($v) ?></code><span><?= e($d) ?></span></button>
        <?php endforeach; ?>
        <hr>
        <div class="d-flex flex-wrap gap-2">
          <button type="button" class="btn btn-outline-primary" id="btnPrevia"><i class="bi bi-eye me-1"></i> Prévia</button>
          <?php if (!$soLeitura): ?><button class="btn btn-primary"><i class="bi bi-floppy me-1"></i> Salvar modelo</button><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</form>

<!-- Prévia: o e-mail montado de verdade, com dados de exemplo -->
<div class="modal fade" id="modalPrevia" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Prévia do e-mail <small class="text-muted fw-normal">— dados de exemplo</small></h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body p-0" style="background:#f4f4f4"><iframe name="framePrevia" style="width:100%;height:75vh;border:0"></iframe></div>
</div></div></div>
<form id="formPrevia" method="POST" action="<?= BASE_URL ?>/cobranca_previa.php" target="framePrevia" class="d-none">
  <?= csrf_field() ?><input name="assunto"><input name="topo"><input name="rodape"><textarea name="corpo"></textarea>
</form>

<?php
$extraJs = '<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js" referrerpolicy="origin"></script>
<script>
tinymce.init({
  selector: "#corpo", height: 520, menubar: false, promotion: false, branding: false, language: "pt_BR",
  plugins: "autolink link lists table code charmap emoticons",
  toolbar: "undo redo | blocks fontsize | bold italic underline | forecolor backcolor | alignleft aligncenter alignright | bullist numlist | link table | removeformat | code",
  content_style: "body { font-family: Arial, sans-serif; font-size: 15px; color: #333; }",
  readonly: ' . ($soLeitura ? 'true' : 'false') . '
});
document.querySelectorAll(".var-btn").forEach(function (b) {
  b.addEventListener("click", function () { tinymce.get("corpo").insertContent(b.dataset.var); });
});
document.getElementById("btnPrevia").addEventListener("click", function () {
  var f = document.getElementById("formPrevia"), m = document.getElementById("formModelo");
  f.assunto.value = m.assunto.value;
  f.topo.value = (m.querySelector("[name=topo]:checked") || {}).value || "";
  f.rodape.value = (m.querySelector("[name=rodape]:checked") || {}).value || "";
  f.corpo.value = tinymce.get("corpo").getContent();
  f.submit();
  new bootstrap.Modal(document.getElementById("modalPrevia")).show();
});
</script>';
require_once __DIR__ . '/includes/views/footer.php';
