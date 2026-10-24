<?php
/**
 * Base de funcionários (hierarquia) — só administrador. Upload da BaseFull
 * (.csv, .xlsx ou .zip), situação da carga atual, consulta de líder por CPF e
 * histórico de cargas com o download do original.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/services/cobranca.php';
require_admin();

$consulta = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        flash('erro', 'Sessão expirada. Tente novamente.');
    } else {
        $up = $_FILES['arquivo'] ?? null;
        if (!$up || $up['error'] === UPLOAD_ERR_NO_FILE) {
            flash('erro', 'Escolha o arquivo da base de funcionários.');
        } elseif ($up['error'] !== UPLOAD_ERR_OK) {
            flash('erro', in_array($up['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'O arquivo passou do limite de upload do servidor (' . ini_get('upload_max_filesize') . '). Envie compactado em .zip.'
                : 'O envio falhou (código ' . $up['error'] . ').');
        } elseif (!preg_match('/\.(csv|xlsx|zip)$/i', $up['name'])) {
            flash('erro', 'Envie .csv, .xlsx ou .zip.');
        } else {
            try {
                set_time_limit(300);
                $r = funcionarios_importar($up['tmp_name'], $up['name'], (int) current_user()['id']);
                flash('ok', 'Base de funcionários atualizada: ' . number_format($r['linhas'], 0, ',', '.') . ' linhas, '
                    . number_format($r['ativos'], 0, ',', '.') . ' ativas.');
            } catch (Throwable $e) {
                flash('erro', 'A base não foi atualizada: ' . $e->getMessage());
            }
        }
    }
    header('Location: ' . BASE_URL . '/funcionarios.php');
    exit;
}

if (($cpf = trim((string) ($_GET['cpf'] ?? ''))) !== '') {
    $consulta = ['cpf' => $cpf, 'lider' => lider_de_cpf($cpf)];
    $consulta['superior'] = lider_do_lider($consulta['lider']);
}
$atual  = funcionarios_carga_atual();
$cargas = funcionarios_cargas();
$n = fn($x) => number_format((int) $x, 0, ',', '.');

// Download do original de uma carga
if (isset($_GET['baixar'])) {
    foreach ($cargas as $c) {
        if ((int) $c['id'] === (int) $_GET['baixar']) {
            $p = pasta_funcionarios() . '/' . basename($c['arquivo']);
            if (!is_file($p)) break;
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . str_replace('"', '', $c['nome_original']) . '"');
            header('Content-Length: ' . filesize($p));
            readfile($p);
            exit;
        }
    }
    http_response_code(404); exit('Arquivo não encontrado.');
}

$pageTitle = 'Base de Funcionários';
require_once __DIR__ . '/includes/views/header.php';
?>

<div class="row g-4 mb-4">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header">Carga atual</div>
      <div class="card-body">
        <?php if ($atual): ?>
          <div class="d-flex align-items-center gap-3 mb-3">
            <i class="bi bi-people fs-2" style="color:var(--censo)"></i>
            <div>
              <div class="fw-bold fs-5"><?= $n($atual['ativos']) ?> funcionários ativos</div>
              <div class="small text-muted"><?= $n($atual['linhas']) ?> linhas no total (a BaseFull traz histórico de vínculos)</div>
            </div>
          </div>
          <div class="small text-muted">
            <?= e($atual['nome_original']) ?> · enviado por <?= e($atual['enviado_por_nome'] ?? '—') ?> em <?= date('d/m/Y H:i', strtotime($atual['enviado_em'])) ?>
          </div>
        <?php else: ?>
          <div class="text-muted">Nenhuma base carregada ainda. Sem ela, as cobranças não sabem quem é o líder de cada pessoa
            (a não ser que a base da revisão traga as colunas de líder).</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header">Atualizar a base</div>
      <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <input type="file" name="arquivo" class="form-control mb-2" accept=".csv,.xlsx,.zip" required>
          <div class="form-text mb-3">A <strong>BaseFull</strong> diária do RH serve direto (.csv). Ela tem ~40 MB —
            acima do limite de upload deste servidor (<?= e(ini_get('upload_max_filesize')) ?>), envie <strong>compactada em .zip</strong>.
            A carga substitui a anterior; o arquivo de cada carga fica guardado.</div>
          <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-primary"><i class="bi bi-upload me-1"></i> Carregar base</button>
            <a class="btn btn-outline-primary" href="<?= BASE_URL ?>/modelo.php?tipo=funcionarios"><i class="bi bi-file-earmark-arrow-down me-1"></i> Baixar modelo</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Consulta: quem é o líder desta pessoa? -->
<div class="card mb-4">
  <div class="card-header">Consultar líder por CPF</div>
  <div class="card-body">
    <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
      <input name="cpf" class="form-control" style="max-width:260px" placeholder="CPF (só números)" value="<?= e($consulta['cpf'] ?? '') ?>">
      <button class="btn btn-outline-primary"><i class="bi bi-search me-1"></i> Consultar</button>
    </form>
    <?php if ($consulta): foreach (['Líder imediato' => $consulta['lider'], 'Líder do líder' => $consulta['superior']] as $rot => $L): ?>
      <div class="d-flex gap-2 align-items-start mb-1">
        <span class="fw-semibold" style="min-width:130px"><?= $rot ?></span>
        <?php if ($L['ok']): ?>
          <span><?= e($L['nome']) ?> — <span class="text-muted"><?= e($L['email']) ?></span></span>
        <?php else: ?>
          <span class="text-warning-emphasis"><i class="bi bi-exclamation-triangle me-1"></i><?= e($L['motivo']) ?><?= $L['nome'] ? ' (' . e($L['nome']) . ')' : '' ?></span>
        <?php endif; ?>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<div class="card"><div class="card-header">Cargas anteriores</div><div class="card-body p-0"><div class="table-responsive">
  <table class="table table-hover align-middle mb-0 small">
    <thead class="table-light"><tr><th class="ps-4">Arquivo</th><th>Linhas</th><th>Ativos</th><th>Enviado por</th><th>Em</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($cargas as $c): ?>
        <tr>
          <td class="ps-4 fw-semibold"><?= e($c['nome_original']) ?></td>
          <td><?= $n($c['linhas']) ?></td><td><?= $n($c['ativos']) ?></td>
          <td><?= e($c['enviado_por_nome'] ?? '—') ?></td>
          <td><?= date('d/m/Y H:i', strtotime($c['enviado_em'])) ?></td>
          <td class="text-end pe-4"><a class="btn btn-sm btn-outline-primary" href="?baixar=<?= (int) $c['id'] ?>"><i class="bi bi-download"></i></a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$cargas): ?><tr><td colspan="6" class="text-center text-muted py-4">Nenhuma carga ainda.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div></div></div>

<?php require_once __DIR__ . '/includes/views/footer.php'; ?>
