<?php
/**
 * Configurações (só administrador): mostra se o Censo tem o que precisa para
 * rodar sozinho e testa cada parte. As senhas ficam só no config.local.php —
 * nunca na tela.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cobranca.php';
require_admin();

$teste = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        flash('erro', 'Sessão expirada. Tente novamente.');
    } elseif (($_POST['acao'] ?? '') === 'banco') {
        try {
            $t = microtime(true);
            $n = (int) horacius_db()->query('SELECT COUNT(*) FROM ac_revisao WHERE datahoraregistro >= DATE_SUB(NOW(), INTERVAL 30 DAY)')->fetchColumn();
            flash('ok', sprintf('Horacius (%s) respondeu em %.1fs — %s revisões registradas nos últimos 30 dias.', horacius_rotulo(), microtime(true) - $t, number_format($n, 0, ',', '.')));
        } catch (Throwable $e) {
            flash('erro', 'O Horacius não respondeu: ' . $e->getMessage());
        }
    } elseif (($_POST['acao'] ?? '') === 'email') {
        $rem = (string) ($_POST['remetente'] ?? '');
        $eu  = current_user();
        if (!isset(remetentes()[$rem])) {
            flash('erro', 'Escolha um remetente.');
        } else {
            $erro = email_enviar($rem, $eu['email'], $eu['name'], [], '[Censo] Teste de envio',
                '<p>Olá, ' . e($eu['name']) . '!</p><p>Este é um e-mail de teste do <strong>Censo</strong>, enviado pela caixa <strong>' . e($rem) . '</strong>.</p><p>Se você está lendo, o envio das cobranças está funcionando.</p>',
                imagens_email()['topo'][0] ?? '', imagens_email()['rodape'][0] ?? '');
            $erro ? flash('erro', 'O e-mail de teste não saiu: ' . $erro) : flash('ok', 'E-mail de teste enviado de ' . $rem . ' para ' . $eu['email'] . '.');
        }
    }
    header('Location: ' . BASE_URL . '/configuracoes.php');
    exit;
}

$pend = config_pendencias();
$pageTitle = 'Configurações';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card mb-4">
  <div class="card-body d-flex align-items-start gap-3">
    <i class="bi <?= $pend ? 'bi-exclamation-triangle text-warning' : 'bi-check-circle' ?> fs-2" style="<?= $pend ? '' : 'color:var(--censo)' ?>"></i>
    <div>
      <div class="fw-bold fs-5"><?= $pend ? 'Falta configurar' : 'O Censo tem tudo para rodar sozinho' ?></div>
      <?php if ($pend): ?>
        <ul class="mb-0 small"><?php foreach ($pend as $p): ?><li><?= e($p) ?></li><?php endforeach; ?></ul>
      <?php else: ?>
        <div class="small text-muted">Banco do Horacius e envio de e-mail configurados no <code>config.local.php</code> do Censo — ele não depende da configuração de nenhum outro sistema.</div>
      <?php endif; ?>
      <div class="small text-muted mt-2">As senhas ficam só no arquivo <code>config.local.php</code> (fora do git). Modelo: <code>config.local.example.php</code>.</div>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="card h-100"><div class="card-header">Banco do Horacius</div><div class="card-body">
      <dl class="row small mb-3">
        <dt class="col-4">Servidor</dt><dd class="col-8"><?= e(censo_cfg('CENSO_DB_HOST') ?: '—') ?>:<?= e(censo_cfg('CENSO_DB_PORT', '3306')) ?></dd>
        <dt class="col-4">Banco</dt><dd class="col-8"><?= e(censo_cfg('CENSO_DB_NAME', 'horacius')) ?></dd>
        <dt class="col-4">Usuário</dt><dd class="col-8"><?= e(censo_cfg('CENSO_DB_USER') ?: '—') ?></dd>
        <dt class="col-4">Rótulo</dt><dd class="col-8"><?= e(horacius_rotulo()) ?></dd>
      </dl>
      <form method="POST"><?= csrf_field() ?><input type="hidden" name="acao" value="banco">
        <button class="btn btn-outline-primary"><i class="bi bi-plug me-1"></i> Testar conexão</button></form>
    </div></div>
  </div>
  <div class="col-lg-6">
    <div class="card h-100"><div class="card-header">Envio de e-mail (Microsoft Graph)</div><div class="card-body">
      <dl class="row small mb-3">
        <dt class="col-4">Aplicação</dt><dd class="col-8"><?= e(censo_cfg('CENSO_GRAPH_CLIENT_ID') ? substr(censo_cfg('CENSO_GRAPH_CLIENT_ID'), 0, 8) . '…' : '—') ?></dd>
        <dt class="col-4">Remetentes</dt><dd class="col-8"><?= e(implode(', ', array_keys(remetentes())) ?: '—') ?></dd>
        <dt class="col-4">Link da revisão</dt><dd class="col-8 text-break"><?= e(link_revisao()) ?></dd>
      </dl>
      <form method="POST" class="d-flex flex-wrap gap-2"><?= csrf_field() ?><input type="hidden" name="acao" value="email">
        <select name="remetente" class="form-select" style="max-width:320px">
          <?php foreach (remetentes() as $r => $c): ?><option value="<?= e($r) ?>"><?= e($r) ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-outline-primary"><i class="bi bi-envelope me-1"></i> Enviar teste para mim</button>
      </form>
      <div class="form-text">Vai para <?= e(current_user()['email']) ?>.</div>
    </div></div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
