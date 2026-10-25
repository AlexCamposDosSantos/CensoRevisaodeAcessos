<?php
/**
 * Iniciar nova revisão (só administrador): nome, data de início e o upload das
 * planilhas do catálogo (includes/services/planilha.php). Cada arquivo é VALIDADO antes
 * de qualquer gravação — com erro, nada é criado e a tela diz a linha.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/services/revisao.php';
require_once __DIR__ . '/includes/services/atualizacao.php';
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
    foreach (planilhas_da_revisao() as $tipo => $def) {
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

    // Pela tela (fetch): só cria e devolve o id — a primeira atualização vem em
    // etapas (revisao_processar.php), com o progresso numa janela.
    $json = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    if (!$erros) {
        try {
            $id = revisao_criar($nome, $inicio, $arquivos, (int) current_user()['id']);
            $base = count($arquivos['base']['linhas'] ?? []);
            if ($avisos) { session_start_safe(); $_SESSION['avisos_revisao'][$id] = $avisos; }
            $texto = "Revisão \"{$nome}\" criada com " . number_format($base, 0, ',', '.') . ' pessoas na base.'
                   . ($avisos ? ' Atenção: ' . count($avisos) . ' aviso(s) na planilha — veja em "Dados e arquivos".' : '');
            if ($json) {
                header('Content-Type: application/json; charset=utf-8');
                exit(json_encode(['ok' => true, 'id' => $id, 'texto' => $texto, 'aviso' => (bool) $avisos], JSON_UNESCAPED_UNICODE));
            }
            // Sem JavaScript: cria e já faz a primeira atualização completa de uma vez
            set_time_limit(600);
            $rev = revisao($id);
            $r = painel_atualizar_revisao($rev);
            flash($avisos || $r['tipo'] !== 'ok' ? 'aviso' : 'ok', $texto . ' ' . $r['texto']);
            header('Location: ' . BASE_URL . '/dashboard.php?r=' . $id);
            exit;
        } catch (Throwable $ex) {
            $erros[] = 'Não consegui criar a revisão: ' . $ex->getMessage();
        }
    }
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        exit(json_encode(['ok' => false, 'erros' => $erros, 'avisos' => $avisos], JSON_UNESCAPED_UNICODE));
    }
}

$pageTitle = 'Nova Revisão';
require_once __DIR__ . '/includes/views/header.php';
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

<div class="alert alert-danger" id="novaErros" hidden>
  <div class="fw-semibold mb-1"><i class="bi bi-x-octagon me-1"></i> <span data-titulo>A revisão não foi criada. Corrija e envie de novo:</span></div>
  <ul class="mb-0 small"></ul>
</div>

<form method="POST" enctype="multipart/form-data" class="row g-4" id="formNovaRevisao">
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
        <?php foreach (planilhas_da_revisao() as $tipo => $def): ?>
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

<!-- Progresso da criação + primeira atualização (cada etapa é uma chamada ao servidor) -->
<div class="proc-fundo" id="procModal" hidden>
  <div class="proc-caixa" role="dialog" aria-modal="true" aria-labelledby="procTitulo" aria-describedby="procSub">
    <h2 class="h5 fw-bold mb-1" id="procTitulo">Iniciando a revisão</h2>
    <p class="small text-muted mb-3" id="procSub">Não feche esta página. Ao terminar, o painel abre com a revisão carregada.</p>
    <ol class="proc-etapas" aria-live="polite">
      <li data-etapa="envio"><span class="proc-ico"></span><div><strong>Enviar e validar a planilha</strong><small></small></div></li>
      <li data-etapa="criar"><span class="proc-ico"></span><div><strong>Criar a revisão</strong><small></small></div></li>
      <li data-etapa="basefull"><span class="proc-ico"></span><div><strong>Baixar a BaseFull do dia</strong><small>SharePoint — hierarquia de líderes</small></div></li>
      <li data-etapa="andamento"><span class="proc-ico"></span><div><strong>Ler o andamento no Horacius</strong><small>quem já revisou desde o início</small></div></li>
      <li data-etapa="delegacoes"><span class="proc-ico"></span><div><strong>Ler as delegações de gestão</strong><small>Horacius</small></div></li>
      <li data-etapa="lideres"><span class="proc-ico"></span><div><strong>Cruzar líderes e montar o painel</strong><small></small></div></li>
    </ol>
    <div class="proc-rodape" id="procRodape" hidden>
      <button type="button" class="btn btn-primary" id="procAbrir">Abrir o painel</button>
    </div>
  </div>
</div>

<script>
(function () {
  var form = document.getElementById('formNovaRevisao');
  if (!form || !window.FormData || !window.fetch) return;   // sem isso, o envio normal faz tudo de uma vez
  var modal = document.getElementById('procModal'), caixaErros = document.getElementById('novaErros');
  var csrf = form.querySelector('input[name=csrf]').value;
  var etapa = function (k) { return modal.querySelector('[data-etapa="' + k + '"]'); };
  function marcar(k, estado, texto) {
    var li = etapa(k);
    li.className = 'proc-' + estado;
    if (texto !== undefined) li.querySelector('small').textContent = texto;
  }
  function erros(lista, avisos, titulo) {
    modal.hidden = true; document.body.style.overflow = '';
    var ul = caixaErros.querySelector('ul'); ul.innerHTML = '';
    lista.concat(avisos || []).forEach(function (t) { var li = document.createElement('li'); li.textContent = t; ul.appendChild(li); });
    if (titulo) caixaErros.querySelector('[data-titulo]').textContent = titulo;
    caixaErros.hidden = false; caixaErros.scrollIntoView({ block: 'start' });
  }
  // Envio com percentual (fetch não informa progresso de upload)
  function enviar() {
    return new Promise(function (ok, falha) {
      var xhr = new XMLHttpRequest();
      xhr.open('POST', form.action || location.href);
      xhr.setRequestHeader('Accept', 'application/json');
      xhr.upload.onprogress = function (e) {
        if (e.lengthComputable) marcar('envio', 'andamento', e.loaded < e.total ? 'Enviando… ' + Math.round(100 * e.loaded / e.total) + '%' : 'Validando as linhas da planilha…');
      };
      xhr.onload = function () { try { ok(JSON.parse(xhr.responseText)); } catch (e) { falha(new Error('Resposta inesperada do servidor (HTTP ' + xhr.status + ').')); } };
      xhr.onerror = function () { falha(new Error('Falha de rede ao enviar a planilha.')); };
      xhr.send(new FormData(form));
    });
  }
  function passo(id, k) {
    marcar(k, 'andamento');
    var dados = new FormData(); dados.append('csrf', csrf); dados.append('id', id); dados.append('etapa', k);
    return fetch('<?= BASE_URL ?>/revisao_processar.php', { method: 'POST', body: dados, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (j) { if (!j.ok) throw new Error(j.texto); marcar(k, j.aviso ? 'aviso' : 'ok', j.texto); return j; });
  }
  form.addEventListener('submit', async function (ev) {
    if (!form.checkValidity()) return;
    ev.preventDefault();
    caixaErros.hidden = true;
    modal.querySelectorAll('li').forEach(function (li) { li.className = ''; });
    document.getElementById('procRodape').hidden = true;
    modal.hidden = false; document.body.style.overflow = 'hidden';
    var id;
    try {
      marcar('envio', 'andamento', 'Enviando…');
      var r = await enviar();
      if (!r.ok) return erros(r.erros || ['Erro desconhecido.'], r.avisos);
      marcar('envio', 'ok', 'Planilha válida.');
      marcar('criar', r.aviso ? 'aviso' : 'ok', r.texto);
      id = r.id;
      for (var k of ['basefull', 'andamento', 'delegacoes']) await passo(id, k);
      var fim = await passo(id, 'lideres');
      document.getElementById('procTitulo').textContent = 'Revisão carregada';
      document.getElementById('procSub').textContent = 'Abrindo o painel…';
      setTimeout(function () { location.href = fim.destino; }, 900);
    } catch (e) {
      var ativa = modal.querySelector('li.proc-andamento');
      if (ativa) marcar(ativa.dataset.etapa, 'erro', e.message);
      if (!id) return erros([e.message]);
      // A revisão já existe: o resto se completa no painel com "Atualizar andamento"
      document.getElementById('procTitulo').textContent = 'Revisão criada, mas a atualização parou';
      document.getElementById('procSub').textContent = 'Abra o painel e use "Atualizar andamento" para completar.';
      var rod = document.getElementById('procRodape'); rod.hidden = false;
      document.getElementById('procAbrir').onclick = function () { location.href = '<?= BASE_URL ?>/dashboard.php?r=' + id; };
    }
  });
})();
</script>

<?php require_once __DIR__ . '/includes/views/footer.php'; ?>
