<?php
/**
 * Uma revisão por dentro: os arquivos de origem (download do original), os
 * dados da base com o status de cada pessoa, e — para administrador — editar
 * nome/início e excluir.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/services/revisao.php';
require_login();

$rev = revisao((int) ($_GET['id'] ?? 0));
if (!$rev) { flash('erro', 'Revisão não encontrada.'); header('Location: ' . BASE_URL . '/revisoes.php'); exit; }
$id = (int) $rev['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    if (!csrf_ok() || !is_admin()) {
        flash('erro', 'Sessão expirada ou sem permissão.');
    } elseif ($acao === 'salvar') {
        $nome = utf8(trim($_POST['nome'] ?? '')); $inicio = trim($_POST['inicio'] ?? '');
        if ($nome === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $inicio)) {
            flash('erro', 'Informe nome e data de início.');
        } else {
            revisao_salvar($id, $nome, $inicio);
            flash('ok', 'Revisão atualizada.');
        }
    } elseif ($acao === 'excluir') {
        revisao_excluir($id);
        flash('aviso', 'Revisão "' . $rev['nome'] . '" excluída, com seus arquivos.');
        header('Location: ' . BASE_URL . '/revisoes.php');
        exit;
    }
    header('Location: ' . BASE_URL . '/revisao.php?id=' . $id);
    exit;
}

andamento_garantir($rev);
$f = [
    'vinculo' => trim((string) ($_GET['vinculo'] ?? '')),
    'status'  => trim((string) ($_GET['status'] ?? '')),
    'q'       => trim((string) ($_GET['q'] ?? '')),
];
$dados = base_pessoas($id, $f);

// Exportar os dados (com o status atual) — Excel abre direto: ponto e vírgula + BOM
if (isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="revisao_' . $id . '_dados_' . date('Ymd_Hi') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Linha', 'Código', 'Nome', 'Identificador', 'Vínculo', 'Enviado para revisão', 'Justificativa', 'Status', 'Acessos revisados', 'Mantidos', 'Revogados', 'Última revisão'], ';');
    foreach ($dados as $d) {
        fputcsv($out, [$d['linha'], $d['codigo'], $d['nome'], $d['id_unico'], $d['vinculo'], $d['enviado'] ? 'SIM' : 'NÃO', $d['justificativa'],
                       ['concluido' => 'Concluído', 'pendente' => 'Pendente', 'fora' => 'Fora da revisão'][$d['status']],
                       $d['linhas'], $d['mantidos'], $d['revogados'], $d['ultima']], ';');
    }
    exit;
}

session_start_safe();
$avisosUpload = $_SESSION['avisos_revisao'][$id] ?? [];
$arquivos = revisao_arquivos($id);
$pag      = max(1, (int) ($_GET['p'] ?? 1));
$porPag   = 100;
$total    = count($dados);
$pagina   = array_slice($dados, ($pag - 1) * $porPag, $porPag);
$qs = fn(array $extra = []) => http_build_query(array_filter(['id' => $id] + $f + $extra, fn($v) => $v !== '' && $v !== null));
$n  = fn($x) => number_format((int) $x, 0, ',', '.');
$rotStatus = ['concluido' => ['Concluído', 'badge-ativo'], 'pendente' => ['Pendente', 'badge-pend'], 'fora' => ['Fora da revisão', 'badge-inativo']];

$pageTitle = 'Dados da Revisão';
require_once __DIR__ . '/includes/views/header.php';
?>

<div class="mb-3 d-flex gap-2">
  <a href="<?= BASE_URL ?>/revisoes.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Revisões</a>
  <a href="<?= BASE_URL ?>/dashboard.php?r=<?= $id ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-speedometer2 me-1"></i> Painel desta revisão</a>
</div>

<div class="row g-4 mb-4">
  <!-- Identificação -->
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header">Revisão</div>
      <div class="card-body">
        <?php if (is_admin()): ?>
          <form method="POST">
            <?= csrf_field() ?><input type="hidden" name="acao" value="salvar">
            <div class="mb-3">
              <label class="form-label fw-semibold small" for="nome">Nome</label>
              <input id="nome" name="nome" class="form-control" value="<?= e($rev['nome']) ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold small" for="inicio">Início</label>
              <input id="inicio" name="inicio" type="date" class="form-control" style="max-width:220px" value="<?= e($rev['inicio']) ?>" required>
              <div class="form-text">Mudar o início relê o andamento: conta revisão registrada a partir desta data.</div>
            </div>
            <div class="small text-muted mb-3">Iniciada por <?= e($rev['criado_por_nome'] ?? '—') ?> em <?= date('d/m/Y H:i', strtotime($rev['criado_em'])) ?></div>
            <div class="d-flex justify-content-between">
              <button class="btn btn-primary"><i class="bi bi-floppy me-1"></i> Salvar</button>
            </div>
          </form>
          <hr>
          <form method="POST">
            <?= csrf_field() ?><input type="hidden" name="acao" value="excluir">
            <button class="btn btn-sm btn-outline-danger" data-confirm="Excluir a revisão &quot;<?= e($rev['nome']) ?>&quot;?"
                    data-confirm-text="Some do Censo junto com os arquivos enviados. Não tem volta." data-confirm-icon="error" data-confirm-btn="Sim, excluir">
              <i class="bi bi-trash me-1"></i> Excluir revisão
            </button>
          </form>
        <?php else: ?>
          <div class="fw-bold fs-5"><?= e($rev['nome']) ?></div>
          <div class="small text-muted">Desde <?= date('d/m/Y', strtotime($rev['inicio'])) ?> · iniciada por <?= e($rev['criado_por_nome'] ?? '—') ?> em <?= date('d/m/Y H:i', strtotime($rev['criado_em'])) ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Arquivos de origem -->
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header">Arquivos do início da revisão</div>
      <div class="card-body">
        <?php foreach ($arquivos as $a): ?>
          <div class="d-flex align-items-center gap-3 arq-linha">
            <i class="bi bi-file-earmark-spreadsheet fs-3" style="color:var(--censo)"></i>
            <div class="flex-grow-1 min-w-0">
              <div class="fw-semibold text-truncate"><?= e($a['nome_original']) ?></div>
              <div class="small text-muted">
                <?= e(PLANILHAS[$a['tipo']]['titulo'] ?? $a['tipo']) ?> ·
                <?= $n($a['linhas']) ?> linhas · <?= number_format($a['tamanho'] / 1024, 0, ',', '.') ?> KB ·
                enviado por <?= e($a['enviado_por_nome'] ?? '—') ?> em <?= date('d/m/Y H:i', strtotime($a['enviado_em'])) ?>
              </div>
              <div class="small text-muted font-monospace" title="Impressão digital do arquivo (SHA-256): prova de que é o mesmo que foi enviado">SHA-256 <?= e(substr($a['sha256'], 0, 16)) ?>…</div>
            </div>
            <a class="btn btn-sm btn-outline-primary text-nowrap" href="<?= BASE_URL ?>/arquivo.php?id=<?= (int) $a['id'] ?>"><i class="bi bi-download me-1"></i> Original</a>
          </div>
        <?php endforeach; ?>
        <?php if ($avisosUpload): ?>
          <details class="mt-3 small">
            <summary class="text-warning fw-semibold"><i class="bi bi-exclamation-triangle me-1"></i><?= count($avisosUpload) ?> aviso(s) na validação do envio</summary>
            <ul class="mt-2 mb-0"><?php foreach ($avisosUpload as $x): ?><li><?= e($x) ?></li><?php endforeach; ?></ul>
          </details>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Dados da base, com o status de cada pessoa -->
<form method="GET" class="card mb-3"><div class="card-body d-flex flex-wrap gap-2 align-items-end">
  <input type="hidden" name="id" value="<?= $id ?>">
  <div>
    <label class="form-label small fw-semibold mb-1" for="vinculo">Vínculo</label>
    <select id="vinculo" name="vinculo" class="form-select" style="min-width:170px" onchange="this.form.submit()">
      <option value="">Todos</option>
      <?php foreach (array_keys(VINCULOS) as $v): ?><option <?= $f['vinculo'] === $v ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="form-label small fw-semibold mb-1" for="status">Status</label>
    <select id="status" name="status" class="form-select" style="min-width:170px" onchange="this.form.submit()">
      <option value="">Todos</option>
      <?php foreach ($rotStatus as $k => [$rot]): ?><option value="<?= $k ?>" <?= $f['status'] === $k ? 'selected' : '' ?>><?= $rot ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="flex-grow-1" style="min-width:220px">
    <label class="form-label small fw-semibold mb-1" for="q">Buscar</label>
    <input id="q" name="q" class="form-control" value="<?= e($f['q']) ?>" placeholder="nome, código ou identificador">
  </div>
  <button class="btn btn-primary"><i class="bi bi-search me-1"></i> Filtrar</button>
  <a class="btn btn-outline-secondary" href="?<?= $qs(['csv' => '1']) ?>"><i class="bi bi-filetype-csv me-1"></i> Exportar CSV</a>
</div></form>

<div class="small text-muted mb-2"><strong class="text-dark"><?= $n($total) ?></strong> linha<?= $total === 1 ? '' : 's' ?> da base
  · status pelo andamento de <?= $rev['andamento_em'] ? date('d/m/Y H:i', strtotime($rev['andamento_em'])) : '—' ?></div>

<div class="card"><div class="card-body p-0"><div class="table-responsive">
  <table class="table table-hover align-middle mb-0 small">
    <thead class="table-light"><tr>
      <th class="ps-4">Linha</th><th>Código</th><th>Nome</th><th>Identificador</th><th>Vínculo</th><th>Status</th><th class="text-end">Acessos revisados</th><th>Última revisão</th>
    </tr></thead>
    <tbody>
      <?php foreach ($pagina as $d): [$rot, $cls] = $rotStatus[$d['status']]; ?>
        <tr>
          <td class="ps-4 text-muted"><?= (int) $d['linha'] ?></td>
          <td class="font-monospace"><?= (int) $d['codigo'] ?></td>
          <td class="fw-semibold"><?= e($d['nome']) ?>
            <?php if (!$d['enviado'] && $d['justificativa']): ?><div class="text-muted fw-normal"><?= e($d['justificativa']) ?></div><?php endif; ?></td>
          <td><?= e($d['id_unico']) ?></td>
          <td><?= e($d['vinculo']) ?></td>
          <td><span class="badge <?= $cls ?>"><?= $rot ?></span></td>
          <td class="text-end"><?= $d['linhas'] ? $n($d['linhas']) . ' <span class="text-muted">(' . $n($d['revogados']) . ' revog.)</span>' : '—' ?></td>
          <td><?= $d['ultima'] ? date('d/m/Y H:i', strtotime($d['ultima'])) : '—' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$pagina): ?><tr><td colspan="8" class="text-center text-muted py-5">Nenhuma linha com esses filtros.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div></div></div>

<?php if ($total > $porPag): $ult = (int) ceil($total / $porPag); ?>
  <nav class="d-flex justify-content-between align-items-center mt-3 small">
    <span class="text-muted">Página <?= $pag ?> de <?= $ult ?></span>
    <span class="d-flex gap-2">
      <?php if ($pag > 1): ?><a class="btn btn-sm btn-outline-secondary" href="?<?= $qs(['p' => $pag - 1]) ?>">‹ Anterior</a><?php endif; ?>
      <?php if ($pag < $ult): ?><a class="btn btn-sm btn-outline-secondary" href="?<?= $qs(['p' => $pag + 1]) ?>">Próxima ›</a><?php endif; ?>
    </span>
  </nav>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/views/footer.php'; ?>
