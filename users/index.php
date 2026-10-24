<?php
/**
 * Gestão de usuários (só administrador). Remover e ativar/desativar são POST
 * com CSRF — nunca link GET, que qualquer prefetch ou clique errado dispararia.
 */
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = db();
$eu  = (int) current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id   = (int) ($_POST['id'] ?? 0);
    $acao = $_POST['acao'] ?? '';
    $st = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([$id]);
    $alvo = $st->fetch();

    if (!csrf_ok()) {
        flash('erro', 'Sessão expirada. Tente novamente.');
    } elseif (!$alvo) {
        flash('erro', 'Usuário não encontrado.');
    } elseif ($id === $eu) {
        flash('erro', 'Você não pode remover nem desativar o próprio usuário.');
    } elseif ($alvo['role'] === 'admin' && (int) $alvo['active'] === 1 && !outro_admin_ativo($id)) {
        flash('erro', 'É preciso manter ao menos um administrador ativo.');
    } elseif ($acao === 'remover') {
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        flash('aviso', 'Usuário ' . $alvo['name'] . ' removido.');
    } elseif ($acao === 'alternar') {
        $novo = (int) $alvo['active'] === 1 ? 0 : 1;
        $pdo->prepare('UPDATE users SET active = ? WHERE id = ?')->execute([$novo, $id]);
        flash('ok', 'Usuário ' . $alvo['name'] . ($novo ? ' reativado.' : ' desativado — não consegue mais entrar.'));
    }
    header('Location: ' . BASE_URL . '/users/index.php');
    exit;
}

$users = $pdo->query('SELECT id, name, email, role, active, last_login, created_at FROM users ORDER BY active DESC, name')->fetchAll();
$pageTitle = 'Gestão de Usuários';
require_once __DIR__ . '/../includes/views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div class="text-muted small"><?= count($users) ?> usuário<?= count($users) === 1 ? '' : 's' ?> cadastrado<?= count($users) === 1 ? '' : 's' ?></div>
  <a href="<?= BASE_URL ?>/users/form.php" class="btn btn-primary">
    <i class="bi bi-person-plus me-1"></i> Novo Usuário
  </a>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th class="ps-4">Nome</th>
          <th>E-mail</th>
          <th>Papel</th>
          <th>Situação</th>
          <th>Último acesso</th>
          <th>Criado em</th>
          <th style="width:150px"></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($users as $u): $self = (int) $u['id'] === $eu; ?>
        <tr class="<?= (int) $u['active'] === 1 ? '' : 'text-muted' ?>">
          <td class="ps-4 fw-semibold">
            <?= e($u['name']) ?>
            <?php if ($self): ?><span class="badge bg-secondary ms-1">Você</span><?php endif; ?>
          </td>
          <td><?= e($u['email']) ?></td>
          <td>
            <span class="badge <?= $u['role'] === 'admin' ? 'badge-admin' : 'badge-user' ?>">
              <?= e(PAPEIS[$u['role']]['rotulo'] ?? $u['role']) ?>
            </span>
          </td>
          <td>
            <span class="badge <?= (int) $u['active'] === 1 ? 'badge-ativo' : 'badge-inativo' ?>">
              <?= (int) $u['active'] === 1 ? 'Ativo' : 'Inativo' ?>
            </span>
          </td>
          <td class="small"><?= $u['last_login'] ? date('d/m/Y H:i', strtotime($u['last_login'])) : '—' ?></td>
          <td class="small"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
          <td class="text-end pe-4 text-nowrap">
            <a href="<?= BASE_URL ?>/users/form.php?id=<?= (int) $u['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar">
              <i class="bi bi-pencil"></i>
            </a>
            <?php if (!$self): ?>
              <form method="POST" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                <input type="hidden" name="acao" value="alternar">
                <button type="submit" class="btn btn-sm btn-outline-secondary"
                        title="<?= (int) $u['active'] === 1 ? 'Desativar' : 'Reativar' ?>">
                  <i class="bi <?= (int) $u['active'] === 1 ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
                </button>
              </form>
              <form method="POST" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                <input type="hidden" name="acao" value="remover">
                <button type="submit" class="btn btn-sm btn-outline-danger" title="Remover"
                        data-confirm="Remover <?= e($u['name']) ?>?"
                        data-confirm-text="O usuário perderá o acesso ao Censo." data-confirm-icon="error"
                        data-confirm-btn="Sim, remover">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
            <?php else: ?>
              <button class="btn btn-sm btn-outline-danger" disabled title="Não é possível remover o próprio usuário">
                <i class="bi bi-trash"></i>
              </button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/views/footer.php'; ?>
