<?php
/**
 * Cadastro e edição de usuário (só administrador).
 */
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo    = db();
$id     = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$eu     = (int) current_user()['id'];
$isSelf = $id && $id === $eu;
$errors = [];
$user   = ['name' => '', 'email' => '', 'role' => 'user', 'active' => 1];

if ($id) {
    $st = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([$id]);
    $user = $st->fetch();
    if (!$user) { flash('erro', 'Usuário não encontrado.'); header('Location: ' . BASE_URL . '/users/index.php'); exit; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $role     = array_key_exists($_POST['role'] ?? '', PAPEIS) ? $_POST['role'] : 'user';
    $active   = isset($_POST['active']) ? 1 : 0;
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    if (!csrf_ok())                                     $errors[] = 'Sessão expirada. Tente novamente.';
    if ($name === '')                                   $errors[] = 'Nome obrigatório.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))     $errors[] = 'E-mail inválido.';
    else {
        $dup = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $dup->execute([$email, $id]);
        if ($dup->fetch()) $errors[] = 'Este e-mail já está em uso.';
    }
    if (!$id && $password === '')                       $errors[] = 'Senha obrigatória para novos usuários.';
    if ($password !== '' && strlen($password) < SENHA_MIN) $errors[] = 'A senha deve ter no mínimo ' . SENHA_MIN . ' caracteres.';
    if ($password !== '' && $password !== $confirm)     $errors[] = 'As senhas não conferem.';

    // Ninguém tira o próprio acesso de administrador nem se desativa
    if ($isSelf) { $role = 'admin'; $active = 1; }
    // Sempre sobra um administrador ativo
    if ($id && $user['role'] === 'admin' && ($role !== 'admin' || !$active) && !outro_admin_ativo($id)) {
        $errors[] = 'É preciso manter ao menos um administrador ativo.';
    }

    if (!$errors) {
        if ($id) {
            $pdo->prepare('UPDATE users SET name = ?, email = ?, role = ?, active = ? WHERE id = ?')
                ->execute([$name, $email, $role, $active, $id]);
            if ($password !== '') {
                $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            }
            flash('ok', 'Usuário atualizado.');
        } else {
            $pdo->prepare('INSERT INTO users (name, email, role, active, password) VALUES (?, ?, ?, ?, ?)')
                ->execute([$name, $email, $role, $active, password_hash($password, PASSWORD_DEFAULT)]);
            flash('ok', 'Usuário ' . $name . ' cadastrado.');
        }
        header('Location: ' . BASE_URL . '/users/index.php');
        exit;
    }
    $user = compact('name', 'email', 'role', 'active');
}

$pageTitle = $id ? 'Editar Usuário' : 'Novo Usuário';
require_once __DIR__ . '/../includes/views/header.php';
?>

<div class="mb-3">
  <a href="<?= BASE_URL ?>/users/index.php" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i> Voltar
  </a>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<div class="card" style="max-width:560px">
  <div class="card-header"><?= $id ? 'Editar Usuário' : 'Novo Usuário' ?></div>
  <div class="card-body">
    <form method="POST">
      <?= csrf_field() ?>

      <div class="mb-3">
        <label class="form-label fw-semibold" for="name">Nome *</label>
        <input type="text" id="name" name="name" class="form-control" value="<?= e($user['name']) ?>" required autofocus />
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold" for="email">E-mail *</label>
        <input type="email" id="email" name="email" class="form-control" value="<?= e($user['email']) ?>" required autocomplete="off" />
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold" for="role">Papel</label>
        <select id="role" name="role" class="form-select" <?= $isSelf ? 'disabled' : '' ?>>
          <?php foreach (PAPEIS as $chave => $p): ?>
            <option value="<?= $chave ?>" <?= $user['role'] === $chave ? 'selected' : '' ?>><?= e($p['rotulo'] . ' — ' . $p['desc']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if ($isSelf): ?>
          <div class="form-text text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Você não pode alterar o próprio papel.</div>
        <?php endif; ?>
      </div>

      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" role="switch" id="active" name="active" value="1"
               <?= (int) $user['active'] === 1 ? 'checked' : '' ?> <?= $isSelf ? 'disabled' : '' ?>>
        <label class="form-check-label" for="active">Usuário ativo</label>
        <div class="form-text">Inativo não entra no Censo — e quem estiver logado é desconectado na próxima tela.</div>
      </div>

      <hr>

      <div class="mb-3">
        <label class="form-label fw-semibold" for="password">
          <?= $id ? 'Nova Senha <span class="text-muted fw-normal">(deixe em branco para não alterar)</span>' : 'Senha *' ?>
        </label>
        <input type="password" id="password" name="password" class="form-control"
               placeholder="<?= $id ? 'Deixe em branco para manter a atual' : 'Mínimo ' . SENHA_MIN . ' caracteres' ?>" autocomplete="new-password" />
      </div>

      <div class="mb-4">
        <label class="form-label fw-semibold" for="password_confirm">Confirmar Senha</label>
        <input type="password" id="password_confirm" name="password_confirm" class="form-control" placeholder="Repita a senha" autocomplete="new-password" />
      </div>

      <div class="d-flex gap-2 justify-content-end">
        <a href="<?= BASE_URL ?>/users/index.php" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-floppy me-1"></i> Salvar</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/views/footer.php'; ?>
