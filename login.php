<?php
/**
 * Login. Sem nenhum usuário cadastrado, a mesma tela cria o primeiro
 * administrador — no lugar de um setup.php com senha fixa no código.
 */
require_once __DIR__ . '/includes/auth.php';

session_start_safe();
if (is_logged_in()) { header('Location: ' . BASE_URL . '/dashboard.php'); exit; }

$primeiro = users_count() === 0;
$error = '';
$nome  = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['password'] ?? '';

    if (!csrf_ok()) {
        $error = 'Sessão expirada. Recarregue a página e tente novamente.';
    } elseif ($primeiro) {
        if ($nome === '')                                   $error = 'Informe o nome.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Informe um e-mail válido.';
        elseif (strlen($senha) < SENHA_MIN)                 $error = 'A senha deve ter no mínimo ' . SENHA_MIN . ' caracteres.';
        elseif ($senha !== ($_POST['password_confirm'] ?? '')) $error = 'As senhas não conferem.';
        else {
            db()->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')")
                ->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT)]);
            abrir_sessao((int) db()->lastInsertId());
            header('Location: ' . BASE_URL . '/dashboard.php');
            exit;
        }
    } else {
        $r = login($email, $senha);
        if ($r === true) { header('Location: ' . BASE_URL . '/dashboard.php'); exit; }
        $error = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Entrar — Censo</title>
  <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/favicon.svg" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/censo.css?v=<?= @filemtime(__DIR__ . '/assets/css/censo.css') ?: 1 ?>" />
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/experience.css?v=<?= @filemtime(__DIR__ . '/assets/css/experience.css') ?: 1 ?>" />
</head>
<body class="login-page">
  <div class="login-container">
    <div class="login-side">
      <div class="login-brand"><img src="<?= BASE_URL ?>/assets/img/marca.svg" alt="" width="39" height="39">Censo<span class="visually-hidden"> — Revisão de acessos</span></div>
      <div class="login-copy">
        <div class="eyebrow">Governança de acessos</div>
        <h2>Clareza para revisar.<br><em>Confiança para avançar.</em></h2>
        <p>Pessoas, acessos e responsáveis conectados. Acompanhe cada etapa e saiba onde agir.</p>
        <div class="login-features"><span><i class="bi bi-list-check" aria-hidden="true"></i>Revisões</span><span><i class="bi bi-send" aria-hidden="true"></i>Cobranças</span><span><i class="bi bi-graph-up" aria-hidden="true"></i>Acompanhamento</span></div>
      </div>
      <div class="login-side-foot"><i class="bi bi-shield-check" aria-hidden="true"></i>Segurança da Informação · Ecossistema Ânima</div>
      <div class="login-orbit" aria-hidden="true"></div>
    </div>

    <div class="login-form">
      <div class="login-box">
        <img class="marca-form" src="<?= BASE_URL ?>/assets/img/marca.svg" alt="Censo" width="52" height="52">
        <div class="eyebrow">Seu espaço de revisão</div>
        <h1><?= $primeiro ? 'Primeiro acesso' : 'Bem-vindo ao Censo' ?></h1>
        <p class="lead-txt"><?= $primeiro ? 'Crie a conta administradora para começar a usar o Censo.' : 'Acesse sua conta para acompanhar as revisões e dar o próximo passo.' ?></p>

        <?php if ($primeiro): ?>
          <div class="aviso-primeiro"><i class="bi bi-info-circle me-1"></i>
            Nenhum usuário cadastrado ainda. Crie o <strong>administrador</strong> — depois ele cadastra os demais em Usuários.</div>
        <?php endif; ?>

        <?php if ($error): ?>
          <div class="alert alert-danger py-2 border-0 shadow-sm mb-4" style="border-radius:12px" role="alert">
            <i class="bi bi-exclamation-circle me-2"></i><?= e($error) ?>
          </div>
        <?php endif; ?>

        <form method="POST" novalidate>
          <?= csrf_field() ?>
          <?php if ($primeiro): ?>
            <div class="mb-3">
              <label class="form-label" for="name">Nome</label>
              <input type="text" id="name" name="name" class="form-control" value="<?= e($nome) ?>" autofocus required />
            </div>
          <?php endif; ?>
          <div class="mb-3">
            <label class="form-label" for="email">E-mail</label>
            <input type="email" id="email" name="email" class="form-control" placeholder="seu@animaeducacao.com.br"
                   value="<?= e($email) ?>" autocomplete="username" <?= $primeiro ? '' : 'autofocus' ?> required />
          </div>
          <div class="mb-3">
            <label class="form-label" for="password">Senha</label>
            <div class="password-field"><input type="password" id="password" name="password" class="form-control" placeholder="••••••••"
                   autocomplete="<?= $primeiro ? 'new-password' : 'current-password' ?>" required />
            <button type="button" class="password-toggle" data-password-toggle="password" aria-label="Mostrar senha" aria-pressed="false" aria-controls="password"><i class="bi bi-eye" aria-hidden="true"></i></button></div>
          </div>
          <?php if ($primeiro): ?>
            <div class="mb-3">
              <label class="form-label" for="password_confirm">Confirmar senha</label>
              <input type="password" id="password_confirm" name="password_confirm" class="form-control"
                     placeholder="Mínimo <?= SENHA_MIN ?> caracteres" autocomplete="new-password" required />
            </div>
          <?php endif; ?>

          <button type="submit" class="btn btn-login w-100 mt-2 mb-3">
            <?= $primeiro ? 'Criar administrador e entrar' : 'Acessar Censo' ?> <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i>
          </button>

          <div class="login-footer">
            © <?= date('Y') ?> Ânima Inc. Todos os direitos reservados.<br>
            Desenvolvido por <a href="mailto:alex.campos@animaeducacao.com.br">Alex Campos dos Santos</a>
            <div class="mt-3"><img src="<?= BASE_URL ?>/assets/img/logo.png" alt="Ecossistema Ânima" style="max-width:110px;opacity:.5"></div>
          </div>
        </form>
      </div>
    </div>
  </div>
<script src="<?= BASE_URL ?>/assets/js/experience.js?v=<?= @filemtime(__DIR__ . '/assets/js/experience.js') ?: 1 ?>"></script>
</body>
</html>
