<?php
/**
 * Configurações (só administrador): mostra se o Censo tem o que precisa para
 * rodar sozinho e testa cada parte. As senhas ficam só no .env —
 * nunca na tela.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/services/cobranca.php';
require_admin();

$teste = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        flash('erro', 'Sessão expirada. Tente novamente.');
    } elseif (($_POST['acao'] ?? '') === 'banco') {
        $k = (string) ($_POST['banco'] ?? banco_atual());
        try {
            $t = microtime(true);
            $n = (int) horacius_db($k)->query('SELECT COUNT(*) FROM ac_revisao WHERE datahoraregistro >= DATE_SUB(NOW(), INTERVAL 30 DAY)')->fetchColumn();
            flash('ok', sprintf('Horacius (%s) respondeu em %.1fs — %s revisões registradas nos últimos 30 dias.', horacius_rotulo($k), microtime(true) - $t, number_format($n, 0, ',', '.')));
        } catch (Throwable $e) {
            flash('erro', 'O Horacius (' . horacius_rotulo($k) . ') não respondeu: ' . $e->getMessage());
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
require_once __DIR__ . '/includes/views/header.php';
?>

<div class="card mb-4">
  <div class="card-body d-flex align-items-start gap-3">
    <i class="bi <?= $pend ? 'bi-exclamation-triangle text-warning' : 'bi-check-circle' ?> fs-2" style="<?= $pend ? '' : 'color:var(--censo)' ?>"></i>
    <div>
      <div class="fw-bold fs-5"><?= $pend ? 'Falta configurar' : 'O Censo tem tudo para rodar sozinho' ?></div>
      <?php if ($pend): ?>
        <ul class="mb-0 small"><?php foreach ($pend as $p): ?><li><?= e($p) ?></li><?php endforeach; ?></ul>
      <?php else: ?>
        <div class="small text-muted">Banco do Horacius e envio de e-mail configurados no <code>.env</code> do Censo — ele não depende da configuração de nenhum outro sistema.</div>
      <?php endif; ?>
      <div class="small text-muted mt-2">As senhas ficam só no arquivo <code>.env</code> (fora do git). Modelo: <code>.env.example</code>.</div>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="card h-100"><div class="card-header">Banco do Horacius</div><div class="card-body">
      <?php foreach (bancos() as $k => $b): $emUso = $k === banco_atual(); ?>
        <div class="banco-item <?= $emUso ? 'em-uso' : '' ?>">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <strong><?= e($b['rotulo'] ?? $k) ?></strong>
            <?= $emUso ? '<span class="badge badge-ativo">em uso</span>' : '<span class="small text-muted">troca no switch do Painel</span>' ?>
          </div>
          <div class="small text-muted mb-2"><?= e(($b['host'] ?? '—') . ':' . ($b['port'] ?? '3306')) ?> · banco <?= e($b['name'] ?? 'horacius') ?> · usuário <?= e($b['user'] ?? '—') ?></div>
          <form method="POST"><?= csrf_field() ?><input type="hidden" name="acao" value="banco"><input type="hidden" name="banco" value="<?= e($k) ?>">
            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-plug me-1"></i> Testar conexão</button></form>
        </div>
      <?php endforeach; ?>
      <?php if (!bancos()): ?><div class="text-muted small">Nenhum banco configurado. Preencha CENSO_DB_AMBIENTES e os campos CENSO_DB_* no .env.</div><?php endif; ?>
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

<?php require_once __DIR__ . '/includes/views/footer.php'; ?>
