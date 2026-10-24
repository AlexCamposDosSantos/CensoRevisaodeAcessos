<?php
/** Histórico de cobranças: o que foi enviado, quando, por quem, para quantos. */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cobranca.php';
require_login();

$lista = array_map('cobranca', cobrancas_lista());
$n = fn($x) => number_format((int) $x, 0, ',', '.');
$pageTitle = 'Histórico de Cobranças';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div class="small text-muted"><?= count($lista) ?> cobrança<?= count($lista) === 1 ? '' : 's' ?></div>
  <a class="btn btn-primary" href="<?= BASE_URL ?>/cobranca_nova.php"><i class="bi bi-send me-1"></i> Nova cobrança</a>
</div>

<div class="card"><div class="card-body p-0"><div class="table-responsive">
  <table class="table table-hover align-middle mb-0 small">
    <thead class="table-light"><tr><th class="ps-4">Quando</th><th>Modelo</th><th>Revisão</th><th>Quem recebeu</th><th class="text-end">E-mails</th><th class="text-end">Pessoas</th><th>Situação</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($lista as $c): $pend = (int) $c['envios'] - (int) $c['enviados'] - (int) $c['erros']; ?>
        <tr>
          <td class="ps-4"><?= date('d/m/Y H:i', strtotime($c['criado_em'])) ?><div class="text-muted"><?= e($c['criado_por_nome'] ?? '—') ?></div></td>
          <td class="fw-semibold"><?= e($c['modelo_nome']) ?></td>
          <td><?= e($c['revisao_nome'] ?? '—') ?></td>
          <td><?= $c['alvo'] === 'superior' ? 'Líder do gestor' : 'Gestor' ?></td>
          <td class="text-end"><?= $n($c['enviados']) ?>/<?= $n($c['envios']) ?></td>
          <td class="text-end"><?= $n($c['pessoas']) ?></td>
          <td>
            <?php if ($pend > 0): ?><span class="badge badge-pend">Enviando (<?= $n($pend) ?> na fila)</span>
            <?php elseif ((int) $c['erros'] > 0): ?><span class="badge badge-erro"><?= $n($c['erros']) ?> com erro</span>
            <?php else: ?><span class="badge badge-ativo">Concluída</span><?php endif; ?>
          </td>
          <td class="text-end pe-4"><a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/cobranca.php?id=<?= (int) $c['id'] ?>"><i class="bi bi-eye"></i></a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$lista): ?><tr><td colspan="8" class="text-center text-muted py-5">Nenhuma cobrança ainda.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div></div></div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
