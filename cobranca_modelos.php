<?php
/** Modelos de cobrança (topo, corpo, rodapé): lista, e excluir (administrador). */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cobranca.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok() || !is_admin()) {
        flash('erro', 'Sessão expirada ou sem permissão.');
    } elseif (($_POST['acao'] ?? '') === 'excluir' && ($m = modelo_cobranca((int) ($_POST['id'] ?? 0)))) {
        db()->prepare('DELETE FROM modelos_cobranca WHERE id = ?')->execute([$m['id']]);
        flash('aviso', 'Modelo "' . $m['nome'] . '" excluído. As cobranças já enviadas com ele continuam no histórico.');
    }
    header('Location: ' . BASE_URL . '/cobranca_modelos.php');
    exit;
}

$modelos = modelos_cobranca();
$pageTitle = 'Modelos de Cobrança';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div class="small text-muted"><?= count($modelos) ?> modelo<?= count($modelos) === 1 ? '' : 's' ?></div>
  <?php if (is_admin()): ?><a class="btn btn-primary" href="<?= BASE_URL ?>/cobranca_modelo.php"><i class="bi bi-plus-lg me-1"></i> Novo modelo</a><?php endif; ?>
</div>

<div class="row g-4">
  <?php foreach ($modelos as $m): ?>
    <div class="col-md-6 col-xl-4">
      <div class="card h-100 modelo-card">
        <?php if ($m['topo']): ?><img src="<?= BASE_URL ?>/assets/email/<?= rawurlencode($m['topo']) ?>" class="modelo-topo" alt=""><?php endif; ?>
        <div class="card-body d-flex flex-column">
          <div class="fw-bold"><?= e($m['nome']) ?></div>
          <div class="small text-muted mb-3"><i class="bi bi-envelope me-1"></i><?= e($m['assunto']) ?></div>
          <div class="small text-muted mt-auto mb-2">Atualizado em <?= date('d/m/Y H:i', strtotime($m['atualizado_em'])) ?></div>
          <div class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/cobranca_modelo.php?id=<?= (int) $m['id'] ?>"><i class="bi bi-pencil me-1"></i><?= is_admin() ? 'Editar' : 'Ver' ?></a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/cobranca_nova.php?modelo=<?= (int) $m['id'] ?>"><i class="bi bi-send me-1"></i>Usar</a>
            <?php if (is_admin()): ?>
              <form method="POST" class="ms-auto"><?= csrf_field() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                <button class="btn btn-sm btn-outline-danger" data-confirm="Excluir o modelo &quot;<?= e($m['nome']) ?>&quot;?" data-confirm-icon="error" data-confirm-btn="Sim, excluir"><i class="bi bi-trash"></i></button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
