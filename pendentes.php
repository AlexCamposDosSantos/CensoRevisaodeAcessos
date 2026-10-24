<?php
/**
 * Pessoas enviadas para revisão que ainda não têm nenhuma revisão registrada
 * no Horacius (revisão mais recente, ou ?r=id). É daqui que saem as cobranças.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/services/painel_prioridades.php';
require_login();

$rev = revisao_escolhida();
if (!$rev) { header('Location: ' . BASE_URL . '/dashboard.php'); exit; }
andamento_garantir($rev);

$f = [
    'vinculo' => trim((string) ($_GET['vinculo'] ?? '')),
    'grupo'   => trim((string) ($_GET['grupo'] ?? '')),
    'q'       => trim((string) ($_GET['q'] ?? '')),
    'status'  => 'pendente',
    'lider' => strtolower(trim((string) ($_GET['lider'] ?? ''))),
    'contato' => (string) ($_GET['contato'] ?? ''),
    'cobranca' => (string) ($_GET['cobranca'] ?? ''),
    'motivo' => (string) ($_GET['motivo'] ?? ''),
];
$lista = base_pessoas((int) $rev['id'], $f);
$cobradas = pessoas_cobradas((int) $rev['id']);
$lideres = lideres_das_pessoas($lista);
$opcoesLideres = [];
foreach ($lideres as $liderOpcao) {
    if ($liderOpcao['ok']) $opcoesLideres[strtolower(trim($liderOpcao['email']))] = $liderOpcao['nome'];
}
asort($opcoesLideres, SORT_NATURAL | SORT_FLAG_CASE);
if ($f['lider'] !== '' && !isset($opcoesLideres[$f['lider']])) $opcoesLideres[$f['lider']] = $f['lider'];
$lista = painel_filtrar_pendentes($lista, $lideres, $cobradas, $f);

if (isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="pendentes_revisao_' . (int) $rev['id'] . '_' . date('Ymd_Hi') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Código', 'Nome', 'Identificador', 'Vínculo', 'Líder', 'E-mail do líder', 'Sem líder porque', 'Cobranças', 'Última cobrança'], ';');
    foreach ($lista as $p) {
        $L = $lideres[(int) $p['codigo']]; $cb = $cobradas[(int) $p['codigo']] ?? null;
        fputcsv($out, [$p['codigo'], $p['nome'], $p['id_unico'], $p['vinculo'], $L['nome'], $L['email'], $L['ok'] ? '' : $L['motivo'],
                        $cb['vezes'] ?? 0, $cb['ultima'] ?? ''], ';');
    }
    exit;
}

$qs = fn(array $extra = []) => http_build_query(array_filter(array_replace(['r' => $rev['id'], 'vinculo' => $f['vinculo'], 'grupo' => $f['grupo'], 'q' => $f['q'], 'lider' => $f['lider'], 'contato' => $f['contato'], 'cobranca' => $f['cobranca'], 'motivo' => $f['motivo']], $extra), fn($v) => $v !== ''));
$pageTitle = 'Pendentes de Revisão';
require_once __DIR__ . '/includes/views/header.php';
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
  <div style="min-width:200px;max-width:100%">
    <label class="form-label small fw-semibold mb-1" for="lider">Líder da equipe</label>
    <select id="lider" name="lider" class="form-select"><option value="">Todos os líderes</option>
      <?php foreach ($opcoesLideres as $emailLider => $nomeLider): ?><option value="<?= e($emailLider) ?>" <?= $f['lider'] === $emailLider ? 'selected' : '' ?>><?= e($nomeLider) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="form-label small fw-semibold mb-1" for="cobranca">Cobrança enviada</label>
    <select id="cobranca" name="cobranca" class="form-select">
      <?php foreach (['' => 'Todas', 'nunca' => 'Ainda sem envio', 'cobrada' => 'Com envio registrado', '7dias' => 'Último envio há 7+ dias'] as $valorCob => $rotuloCob): ?><option value="<?= e($valorCob) ?>" <?= $f['cobranca'] === $valorCob ? 'selected' : '' ?>><?= e($rotuloCob) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="form-label small fw-semibold mb-1" for="contato">Responsável</label>
    <select id="contato" name="contato" class="form-select"><option value="">Todos</option><option value="sem_destino" <?= $f['contato'] === 'sem_destino' ? 'selected' : '' ?>>Sem responsável contatável</option></select>
  </div>
  <?php if ($f['motivo'] !== ''): ?><input type="hidden" name="motivo" value="<?= e($f['motivo']) ?>"><?php endif; ?>
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
  <?php if ($f['grupo'] !== '' || $f['vinculo'] !== '' || $f['q'] !== '' || $f['lider'] !== '' || $f['contato'] !== '' || $f['cobranca'] !== '' || $f['motivo'] !== ''): ?><a class="small" href="?r=<?= (int) $rev['id'] ?>">limpar filtros</a><?php endif; ?>
</div>

<?php if ($f['motivo'] !== ''): ?><div class="alert alert-info py-2">Motivo: <?= e($f['motivo']) ?> · <a href="?<?= e($qs(['motivo' => ''])) ?>">Remover motivo</a></div><?php endif; ?>
<div class="card"><div class="card-body p-0"><div class="table-responsive">
  <table class="table table-hover align-middle mb-0">
    <thead class="table-light"><tr>
      <th class="ps-4">Código</th><th>Nome</th><th>Vínculo</th><th>Líder</th><th>Cobranças</th>
    </tr></thead>
    <tbody>
      <?php foreach (array_slice($lista, 0, 500) as $p): ?>
        <tr>
          <td class="ps-4 font-monospace small"><?= (int) $p['codigo'] ?></td>
          <td class="fw-semibold"><?= e($p['nome']) ?></td>
          <td><span class="badge badge-user"><?= e($p['vinculo']) ?></span></td>
          <?php $L = $lideres[(int) $p['codigo']]; $cb = $cobradas[(int) $p['codigo']] ?? null; ?>
          <td class="small"><?php if ($L['ok']): ?><?= e($L['nome']) ?><div class="text-muted"><?= e($L['email']) ?></div>
            <?php else: ?><span class="text-warning-emphasis"><i class="bi bi-exclamation-triangle me-1"></i><?= e($L['motivo']) ?></span><?php endif; ?></td>
          <td class="small"><?= $cb ? $cb['vezes'] . '× · última ' . date('d/m H:i', strtotime($cb['ultima'])) : '<span class="text-muted">nunca</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$lista): ?>
        <tr><td colspan="5" class="text-center text-muted py-5">Ninguém pendente com esses filtros.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div></div></div>
<?php if (count($lista) > 500): ?>
  <div class="small text-muted mt-2">Mostrando 500 de <?= number_format(count($lista), 0, ',', '.') ?>. O CSV traz todos.</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/views/footer.php'; ?>
