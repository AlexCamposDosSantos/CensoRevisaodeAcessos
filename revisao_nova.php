<?php
/**
 * Iniciar nova revisão (só administrador): nome, data de início e o upload das
 * planilhas do catálogo (includes/planilha.php). Cada arquivo é VALIDADO antes
 * de qualquer gravação — com erro, nada é criado e a tela diz a linha.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/revisao.php';
require_admin();

$nome   = 'Revisão de Acessos ' . date('Y');
$inicio = date('Y-m-d');
$erros  = [];
$avisos = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome   = utf8(trim($_POST['nome'] ?? ''));
    $inicio = trim($_POST['inicio'] ?? '');

    if (!csrf_ok())                                    $erros[] = 'Sessão expirada. Tente novamente.';
    if ($nome === '')                                  $erros[] = 'Informe o nome da revisão.';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $inicio)) $erros[] = 'Informe a data de início.';

    $arquivos = [];
    foreach (PLANILHAS as $tipo => $def) {
        $up = $_FILES['arq_' . $tipo] ?? null;
        $veio = $up && $up['error'] !== UPLOAD_ERR_NO_FILE;
        if (!$veio) {
            if ($def['obrigatoria']) $erros[] = "Envie a planilha \"{$def['titulo']}\".";
            continue;
        }
        if ($up['error'] !== UPLOAD_ERR_OK) {
            $erros[] = "\"{$def['titulo']}\": o envio falhou" . (in_array($up['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? ' — arquivo maior que o permitido pelo servidor.' : " (código {$up['error']}).");
            continue;
        }
        if ($up['size'] > UPLOAD_MAX_MB * 1024 * 1024) { $erros[] = "\"{$def['titulo']}\": maior que " . UPLOAD_MAX_MB . ' MB.'; continue; }
        try {
            [$linhas, $e, $a] = planilha_validar($tipo, $up['tmp_name'], $up['name']);
        } catch (Throwable $ex) {
            $erros[] = "\"{$up['name']}\": " . $ex->getMessage();
            continue;
        }
        foreach ($e as $x) $erros[] = "\"{$up['name']}\" — {$x}";
        foreach ($a as $x) $avisos[] = "\"{$up['name']}\" — {$x}";
        $arquivos[$tipo] = ['tmp' => $up['tmp_name'], 'nome' => $up['name'], 'linhas' => $linhas];
    }

    if (!$erros) {
        try {
            $id = revisao_criar($nome, $inicio, $arquivos, (int) current_user()['id']);
            $rev = revisao($id);
            andamento_garantir($rev, true);
            $base = count($arquivos['base']['linhas'] ?? []);
            flash($avisos ? 'aviso' : 'ok', "Revisão \"{$nome}\" iniciada com " . number_format($base, 0, ',', '.') . ' pessoas na base.'
                . ($avisos ? ' Atenção: ' . count($avisos) . ' aviso(s) na planilha — veja em "Dados e arquivos".' : ''));
            if ($avisos) { session_start_safe(); $_SESSION['avisos_revisao'][$id] = $avisos; }
            header('Location: ' . BASE_URL . '/dashboard.php?r=' . $id);
            exit;
        } catch (Throwable $ex) {
            $erros[] = 'Não consegui criar a revisão: ' . $ex->getMessage();
        }
    }
}

$pageTitle = 'Nova Revisão';
require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-3">
  <a href="<?= BASE_URL ?>/revisoes.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Revisões</a>
</div>

<?php if ($erros): ?>
  <div class="alert alert-danger">
    <div class="fw-semibold mb-1"><i class="bi bi-x-octagon me-1"></i> A revisão não foi criada. Corrija e envie de novo:</div>
    <ul class="mb-0 small"><?php foreach ($erros as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>
<?php if ($avisos && $erros): ?>
  <div class="alert alert-warning small"><ul class="mb-0"><?php foreach ($avisos as $a): ?><li><?= e($a) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="row g-4">
  <?= csrf_field() ?>

  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header"><span class="passo">1</span> Identificação</div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label fw-semibold" for="nome">Nome da revisão</label>
          <input id="nome" name="nome" class="form-control" value="<?= e($nome) ?>" required>
        </div>
        <div class="mb-1">
          <label class="form-label fw-semibold" for="inicio">Início</label>
          <input id="inicio" name="inicio" type="date" class="form-control" style="max-width:220px" value="<?= e($inicio) ?>" required>
        </div>
        <div class="form-text">Conta como revisado quem tiver revisão registrada no Horacius a partir desta data.
          O andamento é lido sozinho do banco — não há arquivo de acompanhamento para enviar.</div>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header"><span class="passo">2</span> Planilhas da revisão</div>
      <div class="card-body">
        <?php foreach (PLANILHAS as $tipo => $def): ?>
          <div class="arq-bloco">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
              <div>
                <div class="fw-semibold"><?= e($def['titulo']) ?> <?= $def['obrigatoria'] ? '<span class="badge badge-admin ms-1">obrigatória</span>' : '<span class="badge badge-user ms-1">opcional</span>' ?></div>
                <div class="small text-muted"><?= e($def['descricao']) ?></div>
              </div>
              <a class="btn btn-sm btn-outline-primary text-nowrap" href="<?= BASE_URL ?>/modelo.php?tipo=<?= e($tipo) ?>">
                <i class="bi bi-file-earmark-arrow-down me-1"></i> Baixar modelo
              </a>
            </div>
            <input type="file" name="arq_<?= e($tipo) ?>" class="form-control" accept=".xlsx,.csv" <?= $def['obrigatoria'] ? 'required' : '' ?>>
            <details class="mt-2 small">
              <summary class="text-muted">Colunas esperadas</summary>
              <table class="table table-sm mt-2 mb-0 small">
                <thead><tr><th>Coluna</th><th>O que preencher</th><th>Exemplo</th></tr></thead>
                <tbody>
                  <?php foreach ($def['colunas'] as $c): ?>
                    <tr>
                      <td class="text-nowrap"><code><?= e($c['cabecalho']) ?></code><?= $c['obrigatoria'] ? ' <span class="text-danger" title="obrigatória">*</span>' : '' ?></td>
                      <td><?= e($c['descricao']) ?></td>
                      <td class="text-nowrap"><?= e($c['exemplo']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </details>
          </div>
        <?php endforeach; ?>
        <div class="form-text">Aceita .xlsx (1ª aba) ou .csv, até <?= UPLOAD_MAX_MB ?> MB. A sua planilha atual serve, desde que tenha as colunas acima — os nomes podem variar um pouco ("codigo", "código", "cod pessoa"…).</div>
      </div>
    </div>
  </div>

  <div class="col-12 d-flex justify-content-end gap-2">
    <a href="<?= BASE_URL ?>/revisoes.php" class="btn btn-outline-secondary">Cancelar</a>
    <button class="btn btn-primary px-4"><i class="bi bi-play-circle me-1"></i> Iniciar revisão</button>
  </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
