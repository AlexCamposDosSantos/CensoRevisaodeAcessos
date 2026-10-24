<?php
/** Todas as revisões: a atual e as anteriores, com acesso aos dados e arquivos de cada uma. */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/revisao.php';
require_login();

$lista = revisoes_lista();
$pageTitle = 'Revisões';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div class="small text-muted"><?= count($lista) ?> revis<?= count($lista) === 1 ? 'ão' : 'ões' ?></div>
  <?php if (is_admin()): ?>
    <a href="<?= BASE_URL ?>/revisao_nova.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Nova revisão</a>
  <?php endif; ?>
</div>

<div class="card"><div class="card-body p-0"><div class="table-responsive">
  <table class="table table-hover align-middle mb-0">
    <thead class="table-light"><tr>
      <th class="ps-4">Revisão</th><th>Início</th><th>Pessoas</th><th>Iniciada por</th><th>Andamento lido em</th><th style="width:210px"></th>
    </tr></thead>
    <tbody>
      <?php foreach ($lista as $i => $r): ?>
        <tr>
          <td class="ps-4 fw-semibold"><?= e($r['nome']) ?><?= $i === 0 ? ' <span class="badge badge-admin ms-1">mais recente</span>' : '' ?></td>
          <td><?= date('d/m/Y', strtotime($r['inicio'])) ?></td>
          <td><?= number_format((int) $r['pessoas'], 0, ',', '.') ?></td>
          <td class="small"><?= e($r['criado_por_nome'] ?? '—') ?><div class="text-muted"><?= date('d/m/Y H:i', strtotime($r['criado_em'])) ?></div></td>
          <td class="small"><?= $r['andamento_em'] ? date('d/m/Y H:i', strtotime($r['andamento_em'])) : '—' ?></td>
          <td class="text-end pe-4 text-nowrap">
            <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/dashboard.php?r=<?= (int) $r['id'] ?>"><i class="bi bi-speedometer2 me-1"></i>Painel</a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/revisao.php?id=<?= (int) $r['id'] ?>"><i class="bi bi-folder2-open me-1"></i>Dados</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$lista): ?>
        <tr><td colspan="6" class="text-center text-muted py-5">Nenhuma revisão ainda.<?= is_admin() ? ' Use "Nova revisão" para começar.' : '' ?></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div></div></div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
