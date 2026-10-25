<?php
require_once __DIR__ . '/../auth.php';
require_login();
$uri      = $_SERVER['REQUEST_URI'] ?? '';
$userName = current_user_name();
$ativo    = fn(string $trecho) => str_contains($uri, $trecho) ? 'active' : '';
$censoTela = basename($_SERVER['SCRIPT_NAME'] ?? '');
$censoDescricoes = [
    'dashboard.php' => ['Visão geral', 'Acompanhe a revisão de acessos, identifique pendências e avance com clareza.'],
    'pendentes.php' => ['Acompanhamento', 'Encontre quem ainda precisa revisar os acessos e acompanhe os responsáveis.'],
    'revisoes.php' => ['Revisão de acessos', 'Todos os ciclos de revisão, com seus dados, arquivos e andamento.'],
    'revisao.php' => ['Revisão de acessos', 'Consulte a base de pessoas e os arquivos desta revisão.'],
    'revisao_nova.php' => ['Novo ciclo', 'Defina a revisão e importe a base de pessoas para começar.'],
    'cobranca_nova.php' => ['Comunicação', 'Prepare os destinatários, personalize a mensagem e confira antes de enviar.'],
    'cobrancas.php' => ['Comunicação', 'Consulte o histórico de envios e o resultado de cada cobrança.'],
    'cobranca.php' => ['Comunicação', 'Acompanhe os destinatários e os resultados desta cobrança.'],
    'cobranca_modelos.php' => ['Comunicação', 'Uma comunicação consistente começa com modelos bem preparados.'],
    'cobranca_modelo.php' => ['Comunicação', 'Personalize o conteúdo e a identidade dos e-mails de cobrança.'],
    'funcionarios.php' => ['Administração', 'Mantenha a hierarquia de pessoas e responsáveis atualizada.'],
    'configuracoes.php' => ['Administração', 'Gerencie as integrações que sustentam a revisão e as cobranças.'],
    'profile.php' => ['Sua conta', 'Atualize seus dados e gerencie sua senha de acesso.'],
];
$censoContexto = str_contains($uri, '/users/')
    ? ['Administração', 'Gerencie os usuários e os papéis de acesso ao Censo.']
    : ($censoDescricoes[$censoTela] ?? ['Censo', 'Revisão de acessos e acompanhamento em um só lugar.']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= e($pageTitle ?? 'Censo') ?> — Censo</title>
  <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/favicon.svg" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/censo.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/censo.css') ?: 1 ?>" />
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/experience.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/experience.css') ?: 1 ?>" />
</head>
<body>
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>

<button class="btn-hamburger" id="btnHamburger" onclick="toggleSidebar()" aria-label="Abrir menu" aria-controls="sidebar" aria-expanded="false">
  <i class="bi bi-list"></i>
</button>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Menu lateral -->
<nav class="sidebar" id="sidebar" aria-label="Navegação principal">
  <div class="brand">
    <a class="brand-lockup" href="<?= BASE_URL ?>/dashboard.php" aria-label="Censo — ir para o painel">
      <img src="<?= BASE_URL ?>/assets/img/marca.svg" alt="" width="42" height="42">
      <span class="brand-copy"><span class="brand-nome">Censo</span><small>Revisão de acessos</small></span>
    </a>
  </div>

  <div class="mt-2 d-flex flex-column flex-grow-1">
    <div class="nav-section">Revisão</div>
    <a href="<?= BASE_URL ?>/dashboard.php" data-search="indicadores progresso dashboard" class="nav-link <?= $ativo('/dashboard') ?>">
      <i class="bi bi-speedometer2"></i> Painel
    </a>
    <a href="<?= BASE_URL ?>/pendentes.php" data-search="pendencias pessoas revisao" class="nav-link <?= $ativo('/pendentes') ?>">
      <i class="bi bi-hourglass-split"></i> Pendentes
    </a>
    <a href="<?= BASE_URL ?>/revisoes.php" data-search="revisao ciclos" class="nav-link <?= ($ativo('/revisoes') || $ativo('/revisao.php') || $ativo('/revisao_nova')) ? 'active' : '' ?>">
      <i class="bi bi-folder2-open"></i> Revisões
    </a>

    <div class="nav-section">Cobranças</div>
    <a href="<?= BASE_URL ?>/cobranca_nova.php" class="nav-link <?= $ativo('/cobranca_nova') ?>">
      <i class="bi bi-send"></i> Nova cobrança
    </a>
    <a href="<?= BASE_URL ?>/cobrancas.php" data-search="cobrancas envios" class="nav-link <?= ($ativo('/cobrancas.php') || $ativo('/cobranca.php')) ? 'active' : '' ?>">
      <i class="bi bi-clock-history"></i> Histórico
    </a>
    <a href="<?= BASE_URL ?>/cobranca_modelos.php" data-search="cobrancas mensagens emails" class="nav-link <?= ($ativo('/cobranca_modelo')) ? 'active' : '' ?>">
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
  <div class="sidebar-foot"><i class="bi bi-shield-check" aria-hidden="true"></i><span>Segurança da Informação<br>Ecossistema Ânima</span></div>
</nav>

<!-- Conteúdo principal -->
<main class="main-content" id="conteudo" tabindex="-1">
  <div class="topbar">
    <div class="topbar-crumb"><span>Censo</span><i class="bi bi-chevron-right" aria-hidden="true"></i><strong><?= e($censoContexto[0]) ?></strong></div>
    <button type="button" class="search-trigger" id="navSearchTrigger" aria-haspopup="dialog" aria-controls="navDialog" aria-label="Buscar telas (Ctrl+K)">
      <i class="bi bi-search" aria-hidden="true"></i><span>Ir para uma tela</span><kbd>Ctrl K</kbd>
    </button>
    <div class="topbar-account">
      <span class="user-avatar" aria-hidden="true"><i class="bi bi-person"></i></span>
      <div><div class="account-name"><?= e($userName) ?></div><div class="account-role"><?= is_admin() ? 'Administrador' : 'Usuário' ?></div></div>
    </div>
  </div>
  <div class="page-heading">
    <div class="eyebrow"><?= e($censoContexto[0]) ?></div>
    <h1><?= e($pageTitle ?? 'Painel') ?></h1>
    <p><?= e($censoContexto[1]) ?></p>
  </div>

  <?php // Nome próprio: o header roda no escopo da página, e "$f" já é dos filtros dela ?>
  <?php if ($flashMsg = flash_get()): ?>
    <div class="alert <?= $flashMsg['tipo'] === 'erro' ? 'alert-danger' : ($flashMsg['tipo'] === 'aviso' ? 'alert-warning' : 'alert-success') ?> alert-dismissible fade show">
      <?= e($flashMsg['texto']) ?> <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
  <?php endif; ?>
