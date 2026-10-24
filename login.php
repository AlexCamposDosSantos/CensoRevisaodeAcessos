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
  <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/favicon.svg" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <style>
    body { font-family: 'Inter', sans-serif; height: 100vh; margin: 0; background: #fff; }
    .login-container { display: flex; height: 100vh; overflow: hidden; }

    /* Esquerda: apresentação do sistema */
    .login-side {
      width: 60%; position: relative; overflow: hidden; color: #fff;
      background: radial-gradient(1200px 600px at 10% 0%, #1b8fb3 0%, transparent 60%),
                  linear-gradient(160deg, #0b5269 0%, #0e1620 100%);
      display: flex; flex-direction: column; justify-content: center; padding: 70px;
    }
    .login-side::after {   /* grade sutil ao fundo */
      content: ""; position: absolute; inset: 0; pointer-events: none; opacity: .08;
      background-image: linear-gradient(#fff 1px, transparent 1px), linear-gradient(90deg, #fff 1px, transparent 1px);
      background-size: 44px 44px;
    }
    .login-side > * { position: relative; z-index: 1; max-width: 520px; }
    .login-side img.marca { width: 76px; height: 76px; margin-bottom: 22px; }
    .login-side h2 { font-weight: 800; font-size: 2.6rem; letter-spacing: -.5px; margin-bottom: 6px; }
    .login-side .sub { color: rgba(255,255,255,.75); font-size: 1.05rem; margin-bottom: 34px; }
    .login-side ul { list-style: none; padding: 0; margin: 0; }
    .login-side li { display: flex; gap: 14px; align-items: flex-start; margin-bottom: 18px; color: rgba(255,255,255,.88); }
    .login-side li i { font-size: 1.2rem; color: #7fd3ea; margin-top: 1px; }

    /* Direita: formulário */
    .login-form { width: 40%; display: flex; align-items: center; justify-content: center; padding: 40px; }
    .login-box { width: 100%; max-width: 380px; }
    .login-box h1 { font-weight: 800; font-size: 1.75rem; color: #1a1a2e; margin-bottom: 6px; }
    .login-box p.lead-txt { color: #555; font-weight: 500; margin-bottom: 28px; font-size: .95rem; }
    .form-label { font-weight: 600; color: #333; margin-bottom: 8px; font-size: .9rem; }
    .form-control { padding: 13px 16px; border-radius: 12px; border: 1px solid #ececec; background: #f9f9f9; font-size: .95rem; }
    .form-control:focus { background: #fff; border-color: #0f6e8c; box-shadow: 0 0 0 4px rgba(15,110,140,.08); }
    .btn-login { padding: 13px; font-weight: 700; border-radius: 12px; background: #0f6e8c; color: #fff; border: none;
                 box-shadow: 0 10px 20px rgba(15,110,140,.22); transition: all .2s ease; }
    .btn-login:hover { background: #0b5269; color: #fff; transform: translateY(-1px); }
    .login-footer { margin-top: 30px; color: #b5b5b5; font-size: .8rem; text-align: center; }
    .login-footer a { color: #0f6e8c; text-decoration: none; font-weight: 600; }
    .marca-form { display: none; width: 52px; height: 52px; margin-bottom: 18px; }
    .aviso-primeiro { background: #e6f3f7; color: #0b5269; border-radius: 12px; padding: 12px 14px; font-size: .88rem; margin-bottom: 22px; }
    @media (max-width: 992px) { .login-side { display: none; } .login-form { width: 100%; } .marca-form { display: block; } }
  </style>
</head>
<body>
  <div class="login-container">
    <div class="login-side">
      <img class="marca" src="<?= BASE_URL ?>/assets/marca-clara.svg" alt="">
      <h2>Censo</h2>
      <div class="sub">Revisão de acessos, do início ao fim — com cobrança de quem ainda não revisou.</div>
      <ul>
        <li><i class="bi bi-list-check"></i><span>Ciclos de revisão por sistema, gestor e prazo</span></li>
        <li><i class="bi bi-bell"></i><span>Cobranças registradas e acompanhadas até a resposta</span></li>
        <li><i class="bi bi-graph-up"></i><span>Andamento de cada revisão num só painel</span></li>
      </ul>
    </div>

    <div class="login-form">
      <div class="login-box">
        <img class="marca-form" src="<?= BASE_URL ?>/assets/marca.svg" alt="Censo" width="52" height="52">
        <h1><?= $primeiro ? 'Primeiro acesso' : 'Bem-vindo ao Censo' ?></h1>
        <p class="lead-txt">Sistema de uso exclusivo da Segurança da Informação</p>

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
            <input type="password" id="password" name="password" class="form-control" placeholder="••••••••"
                   autocomplete="<?= $primeiro ? 'new-password' : 'current-password' ?>" required />
          </div>
          <?php if ($primeiro): ?>
            <div class="mb-3">
              <label class="form-label" for="password_confirm">Confirmar senha</label>
              <input type="password" id="password_confirm" name="password_confirm" class="form-control"
                     placeholder="Mínimo <?= SENHA_MIN ?> caracteres" autocomplete="new-password" required />
            </div>
          <?php endif; ?>

          <button type="submit" class="btn btn-login w-100 mt-2 mb-3">
            <?= $primeiro ? 'Criar administrador e entrar' : 'Entrar' ?>
          </button>

          <div class="login-footer">
            © <?= date('Y') ?> Ânima Inc. Todos os direitos reservados.<br>
            Desenvolvido por <a href="mailto:alex.campos@animaeducacao.com.br">Alex Campos dos Santos</a>
            <div class="mt-3"><img src="<?= BASE_URL ?>/assets/logo.png" alt="Ecossistema Ânima" style="max-width:110px;opacity:.5"></div>
          </div>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
