<?php
/**
 * Meu Perfil: nome, e-mail e troca da própria senha.
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo    = db();
$userId = (int) current_user()['id'];
$st = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$st->execute([$userId]);
$user = $st->fetch();

$errosPerfil = [];
$errosSenha  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        flash('erro', 'Sessão expirada. Tente novamente.');
        header('Location: ' . BASE_URL . '/profile.php');
        exit;
    }

    if (isset($_POST['save_profile'])) {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($name === '') $errosPerfil[] = 'Nome obrigatório.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errosPerfil[] = 'E-mail inválido.';
        else {
            $dup = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
            $dup->execute([$email, $userId]);
            if ($dup->fetch()) $errosPerfil[] = 'Este e-mail já está em uso por outro usuário.';
        }
        if (!$errosPerfil) {
            $pdo->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?')->execute([$name, $email, $userId]);
            flash('ok', 'Perfil atualizado.');
            header('Location: ' . BASE_URL . '/profile.php');
            exit;
        }
        $user['name'] = $name; $user['email'] = $email;
    }

    if (isset($_POST['save_password'])) {
        $atual = $_POST['current_password'] ?? '';
        $nova  = $_POST['new_password'] ?? '';
        if (!password_verify($atual, $user['password'])) $errosSenha[] = 'Senha atual incorreta.';
        if (strlen($nova) < SENHA_MIN)                    $errosSenha[] = 'A nova senha deve ter no mínimo ' . SENHA_MIN . ' caracteres.';
        if ($nova !== ($_POST['confirm_password'] ?? '')) $errosSenha[] = 'As novas senhas não conferem.';
        if (!$errosSenha) {
            $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($nova, PASSWORD_DEFAULT), $userId]);
            flash('ok', 'Senha alterada.');
            header('Location: ' . BASE_URL . '/profile.php');
            exit;
        }
    }
}

$pageTitle = 'Meu Perfil';
require_once __DIR__ . '/includes/views/header.php';
?>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-person me-2 text-muted"></i>Dados pessoais</div>
      <div class="card-body">
        <?php if ($errosPerfil): ?>
          <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errosPerfil as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="POST">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="name">Nome</label>
            <input type="text" id="name" name="name" class="form-control" value="<?= e($user['name']) ?>" required />
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="email">E-mail</label>
            <input type="email" id="email" name="email" class="form-control" value="<?= e($user['email']) ?>" required />
          </div>
          <div class="mb-4 small text-muted">
            Papel: <strong><?= e(PAPEIS[$user['role']]['rotulo'] ?? $user['role']) ?></strong> ·
            último acesso: <?= $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : '—' ?>
          </div>
          <button type="submit" name="save_profile" value="1" class="btn btn-primary"><i class="bi bi-floppy me-1"></i> Salvar dados</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-shield-lock me-2 text-muted"></i>Alterar senha</div>
      <div class="card-body">
        <?php if ($errosSenha): ?>
          <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errosSenha as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="POST">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="current_password">Senha atual</label>
            <input type="password" id="current_password" name="current_password" class="form-control" autocomplete="current-password" required />
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="new_password">Nova senha</label>
            <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Mínimo <?= SENHA_MIN ?> caracteres" autocomplete="new-password" required />
          </div>
          <div class="mb-4">
            <label class="form-label fw-semibold" for="confirm_password">Confirmar nova senha</label>
            <input type="password" id="confirm_password" name="confirm_password" class="form-control" autocomplete="new-password" required />
          </div>
          <button type="submit" name="save_password" value="1" class="btn btn-primary"><i class="bi bi-key me-1"></i> Alterar senha</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/views/footer.php'; ?>
