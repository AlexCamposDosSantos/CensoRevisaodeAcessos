<?php
/**
 * Pessoas enviadas para revisão que ainda não têm nenhuma revisão registrada
 * no Horacius (revisão mais recente, ou ?r=id). É daqui que saem as cobranças.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/revisao.php';
require_login();

$rev = revisao_escolhida();
if (!$rev) { header('Location: ' . BASE_URL . '/dashboard.php'); exit; }
andamento_garantir($rev);

$f = [
    'vinculo' => trim((string) ($_GET['vinculo'] ?? '')),
    'grupo'   => trim((string) ($_GET['grupo'] ?? '')),
    'q'       => trim((string) ($_GET['q'] ?? '')),
    'status'  => 'pendente',
];
$lista = base_pessoas((int) $rev['id'], $f);

if (isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="pendentes_revisao_' . (int) $rev['id'] . '_' . date('Ymd_Hi') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Código', 'Nome', 'Identificador', 'Vínculo'], ';');
    foreach ($lista as $p) fputcsv($out, [$p['codigo'], $p['nome'], $p['id_unico'], $p['vinculo']], ';');
    exit;
}

$qs = fn(array $extra = []) => http_build_query(array_filter(['r' => $rev['id'], 'vinculo' => $f['vinculo'], 'grupo' => $f['grupo'], 'q' => $f['q']] + $extra, fn($v) => $v !== ''));
$pageTitle = 'Pendentes de Revisão';
require_once __DIR__ . '/includes/header.php';
?>

<form method="GET" class="card mb-4"><div class="card-body d-flex flex-wrap gap-2 align-items-end">
  <input type="hidden" name="r" value="<?= (int) $rev['id'] ?>">
  <div>
    <label class="form-label small fw-semibold mb-1" for="vinculo">Vínculo</label>
    <select id="vinculo" name="vinculo" class="form-select" style="min-width:190px" onchange="this.form.submit()">
      <option value="">Todos</option>
      <?php foreach (array_keys(VINCULOS) as $v): ?>
        <option value="<?= e($v) ?>" <?= $f['vinculo'] === $v ? 'selected' : '' ?>><?= e($v) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="flex-grow-1" style="min-width:220px">
    <label class="form-label small fw-semibold mb-1" for="q">Buscar</label>
    <input id="q" name="q" class="form-control" value="<?= e($f['q']) ?>" placeholder="nome, código ou identificador">
  </div>
  <?php if ($f['grupo'] !== ''): ?><input type="hidden" name="grupo" value="<?= e($f['grupo']) ?>"><?php endif; ?>
  <button class="btn btn-primary"><i class="bi bi-search me-1"></i> Filtrar</button>
  <a class="btn btn-outline-secondary" href="?<?= $qs(['csv' => '1']) ?>"><i class="bi bi-filetype-csv me-1"></i> Exportar CSV</a>
</div></form>

<div class="d-flex justify-content-between align-items-center mb-2">
  <div class="small text-muted">
    <strong class="text-dark"><?= number_format(count($lista), 0, ',', '.') ?></strong> pessoa<?= count($lista) === 1 ? '' : 's' ?> sem revisão
    <?= $f['grupo'] !== '' ? '· grupo ' . e($f['grupo']) : '' ?>
    · <?= e($rev['nome']) ?> · andamento de <?= $rev['andamento_em'] ? date('d/m/Y H:i', strtotime($rev['andamento_em'])) : '—' ?>
  </div>
  <?php if ($f['grupo'] !== '' || $f['vinculo'] !== '' || $f['q'] !== ''): ?><a class="small" href="?r=<?= (int) $rev['id'] ?>">limpar filtros</a><?php endif; ?>
</div>

<div class="card"><div class="card-body p-0"><div class="table-responsive">
  <table class="table table-hover align-middle mb-0">
    <thead class="table-light"><tr>
      <th class="ps-4">Código</th><th>Nome</th><th>Identificador</th><th>Vínculo</th>
    </tr></thead>
    <tbody>
      <?php foreach (array_slice($lista, 0, 500) as $p): ?>
        <tr>
          <td class="ps-4 font-monospace small"><?= (int) $p['codigo'] ?></td>
          <td class="fw-semibold"><?= e($p['nome']) ?></td>
          <td class="small"><?= e($p['id_unico']) ?></td>
          <td><span class="badge badge-user"><?= e($p['vinculo']) ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$lista): ?>
        <tr><td colspan="4" class="text-center text-muted py-5">Ninguém pendente com esses filtros.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div></div></div>
<?php if (count($lista) > 500): ?>
  <div class="small text-muted mt-2">Mostrando 500 de <?= number_format(count($lista), 0, ',', '.') ?>. O CSV traz todos.</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
