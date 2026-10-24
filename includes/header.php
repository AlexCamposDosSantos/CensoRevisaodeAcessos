<?php
require_once __DIR__ . '/auth.php';
require_login();
$uri      = $_SERVER['REQUEST_URI'] ?? '';
$userName = current_user_name();
$ativo    = fn(string $trecho) => str_contains($uri, $trecho) ? 'active' : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= e($pageTitle ?? 'Censo') ?> — Censo</title>
  <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/favicon.svg" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/censo.css?v=<?= @filemtime(__DIR__ . '/../assets/censo.css') ?: 1 ?>" />
</head>
<body>

<button class="btn-hamburger" id="btnHamburger" onclick="toggleSidebar()" aria-label="Abrir menu">
  <i class="bi bi-list"></i>
</button>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- Menu lateral -->
<nav class="sidebar" id="sidebar">
  <div class="brand">
    <span class="brand-nome"><img src="<?= BASE_URL ?>/assets/marca-clara.svg" alt="" width="30" height="30">Censo</span>
    <small>Revisão de acessos — Segurança da Informação</small>
  </div>

  <div class="mt-2 d-flex flex-column flex-grow-1">
    <div class="nav-section">Revisão</div>
    <a href="<?= BASE_URL ?>/dashboard.php" class="nav-link <?= $ativo('/dashboard') ?>">
      <i class="bi bi-speedometer2"></i> Painel
    </a>
    <a href="<?= BASE_URL ?>/pendentes.php" class="nav-link <?= $ativo('/pendentes') ?>">
      <i class="bi bi-hourglass-split"></i> Pendentes
    </a>
    <a href="<?= BASE_URL ?>/revisoes.php" class="nav-link <?= ($ativo('/revisoes') || $ativo('/revisao.php') || $ativo('/revisao_nova')) ? 'active' : '' ?>">
      <i class="bi bi-folder2-open"></i> Revisões
    </a>

    <div class="nav-section">Cobranças</div>
    <a href="<?= BASE_URL ?>/cobranca_nova.php" class="nav-link <?= $ativo('/cobranca_nova') ?>">
      <i class="bi bi-send"></i> Nova cobrança
    </a>
    <a href="<?= BASE_URL ?>/cobrancas.php" class="nav-link <?= ($ativo('/cobrancas.php') || $ativo('/cobranca.php')) ? 'active' : '' ?>">
      <i class="bi bi-clock-history"></i> Histórico
    </a>
    <a href="<?= BASE_URL ?>/cobranca_modelos.php" class="nav-link <?= ($ativo('/cobranca_modelo')) ? 'active' : '' ?>">
      <i class="bi bi-envelope-paper"></i> Modelos
    </a>

    <?php if (is_admin()): ?>
    <div class="nav-section">Administração</div>
    <a href="<?= BASE_URL ?>/funcionarios.php" class="nav-link <?= $ativo('/funcionarios') ?>">
      <i class="bi bi-diagram-3"></i> Base de funcionários
    </a>
    <a href="<?= BASE_URL ?>/users/index.php" class="nav-link <?= $ativo('/users/') ?>">
      <i class="bi bi-people-fill"></i> Usuários
    </a>
    <a href="<?= BASE_URL ?>/configuracoes.php" class="nav-link <?= $ativo('/configuracoes') ?>">
      <i class="bi bi-gear"></i> Configurações
    </a>
    <?php endif; ?>

    <div class="nav-section">Conta</div>
    <a href="<?= BASE_URL ?>/profile.php" class="nav-link <?= $ativo('/profile') ?>">
      <i class="bi bi-person-circle"></i> Meu Perfil
    </a>
    <a href="<?= BASE_URL ?>/logout.php" class="nav-link nav-sair">
      <i class="bi bi-box-arrow-left"></i> Sair
    </a>
  </div>
</nav>

<!-- Conteúdo principal -->
<div class="main-content">
  <div class="topbar">
    <h5><?= e($pageTitle ?? 'Dashboard') ?></h5>
    <div class="d-flex align-items-center gap-2">
      <i class="bi bi-person-circle text-muted"></i>
      <span class="text-muted small"><?= e($userName) ?></span>
      <?php if (is_admin()): ?><span class="badge badge-admin ms-1">Administrador</span><?php endif; ?>
    </div>
  </div>

  <?php // Nome próprio: o header roda no escopo da página, e "$f" já é dos filtros dela ?>
  <?php if ($flashMsg = flash_get()): ?>
    <div class="alert <?= $flashMsg['tipo'] === 'erro' ? 'alert-danger' : ($flashMsg['tipo'] === 'aviso' ? 'alert-warning' : 'alert-success') ?> alert-dismissible fade show">
      <?= e($flashMsg['texto']) ?> <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
  <?php endif; ?>
