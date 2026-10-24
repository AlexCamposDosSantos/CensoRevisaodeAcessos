<?php
/**
 * Nova cobrança — assistente em três perguntas (quem cobrar, quem recebe, qual
 * e-mail), seguido da PRÉVIA: destinatários, quantas pessoas em cada um, o
 * e-mail montado e quem ficou sem destinatário. Nada sai antes de "Confirmar".
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/services/cobranca.php';
require_login();
cobranca_estrutura();

$revisoes = revisoes_lista();
$modelos  = modelos_cobranca();
$rems     = remetentes();
$eu       = current_user();

// Valores do formulário (POST, ou os padrões)
$o = [
    'revisao_id'      => (int) ($_POST['revisao_id'] ?? ($revisoes[0]['id'] ?? 0)),
    'vinculo'         => trim((string) ($_POST['vinculo'] ?? '')),
    'grupo'           => trim((string) ($_POST['grupo'] ?? '')),
    'so_nao_cobrados' => !empty($_POST['so_nao_cobrados']),
    'alvo'            => ($_POST['alvo'] ?? 'lider') === 'superior' ? 'superior' : 'lider',
    'copiar_outro'    => !empty($_POST['copiar_outro']),
    'tipo_copia_outro' => ($_POST['tipo_copia_outro'] ?? 'cc') === 'cco' ? 'cco' : 'cc',
    'tipo_copia_fixa'  => ($_POST['tipo_copia_fixa'] ?? 'cc') === 'cco' ? 'cco' : 'cc',
    'modelo_id'       => (int) ($_POST['modelo_id'] ?? ($_GET['modelo'] ?? ($modelos[0]['id'] ?? 0))),
    'remetente'       => (string) ($_POST['remetente'] ?? (array_key_first($rems) ?? '')),
    'prazo'           => utf8(trim((string) ($_POST['prazo'] ?? ''))),
    'copia_fixa'      => trim((string) ($_POST['copia_fixa'] ?? 'seginfo@animaeducacao.com.br')),
];
// "Não enviar" desta cobrança: um e-mail por linha
$naoEnviarTexto = (string) ($_POST['nao_enviar'] ?? '');
[$o['nao_enviar'], $naoEnviarRuins] = lista_emails($naoEnviarTexto);
$acao  = $_POST['acao'] ?? '';
$erros = [];
$plano = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok())                       $erros[] = 'Sessão expirada. Tente novamente.';
    if (!revisao($o['revisao_id']))       $erros[] = 'Escolha a revisão.';
    $modelo = modelo_cobranca($o['modelo_id']);
    if (!$modelo)                         $erros[] = 'Escolha o modelo do e-mail.';
    if (!isset($rems[$o['remetente']]))   $erros[] = 'Escolha o remetente (CENSO_REMETENTES no .env).';
    [, $ruins] = enderecos_validos($o['copia_fixa']);
    if ($ruins)                           $erros[] = 'E-mail inválido em "cópia fixa": ' . implode(', ', $ruins);
    if ($naoEnviarRuins)                  $erros[] = 'Linha que não é e-mail em "não enviar para": ' . implode(', ', $naoEnviarRuins);

    if (!$erros) {
        $rev = revisao($o['revisao_id']);
        andamento_garantir($rev);   // cobra com o andamento atual — quem já revisou não recebe
        $plano = cobranca_planejar($o);

        if ($acao === 'teste' && $plano['grupos']) {
            $g = $plano['grupos'][0];
            $ctx = ['nome' => $g['nome'], 'prazo' => $o['prazo'], 'revisao' => $rev['nome']];
            $erro = email_enviar($o['remetente'], $eu['email'], $eu['name'], [],
                '[TESTE] ' . cobranca_aplicar($modelo['assunto'], $ctx, $g['pessoas'], $o['alvo'] === 'superior', false),
                '<p style="background:#fff4e5;border:1px solid #f0c27b;padding:8px 12px;border-radius:6px;font-size:13px;color:#7a4b00;">'
                . '<strong>Teste</strong> — este e-mail iria para <strong>' . e($g['nome']) . ' &lt;' . e($g['email']) . '&gt;</strong>'
                . ($g['cc'] ? ', com cópia (Cc) para ' . e(implode(', ', $g['cc'])) : '')
                . ($g['cco'] ? ' e cópia oculta (Cco) para ' . e(implode(', ', $g['cco'])) : '') . '.</p>'
                . cobranca_aplicar($modelo['corpo'], $ctx, $g['pessoas'], $o['alvo'] === 'superior'),
                $modelo['topo'], $modelo['rodape']);
            if ($erro) $erros[] = 'O teste não saiu: ' . $erro;
            else $okTeste = 'Teste enviado para ' . $eu['email'] . ' (o e-mail que iria para ' . $g['nome'] . ').';
        } elseif ($acao === 'enviar') {
            if (!$plano['grupos']) {
                $erros[] = 'Ninguém para cobrar com esses filtros.';
            } else {
                $id = cobranca_criar($o, $plano, $modelo, (int) $eu['id']);
                header('Location: ' . BASE_URL . '/cobranca.php?id=' . $id . '&enviar=1');
                exit;
            }
        }
    }
}

$n = fn($x) => number_format((int) $x, 0, ',', '.');
$pageTitle = 'Nova Cobrança';
require_once __DIR__ . '/includes/views/header.php';
?>

<?php if (!$revisoes): ?>
  <div class="alert alert-warning">Não há revisão para cobrar. Inicie uma em <a href="<?= BASE_URL ?>/revisao_nova.php">Nova revisão</a>.</div>
<?php elseif (!$modelos): ?>
  <div class="alert alert-warning">Não há modelo de e-mail. Crie um em <a href="<?= BASE_URL ?>/cobranca_modelo.php">Modelos de cobrança</a>.</div>
<?php endif; ?>
<?php if (config_pendencias()): ?>
  <div class="alert alert-warning small"><i class="bi bi-exclamation-triangle me-1"></i> A configuração está incompleta — veja em
    <a href="<?= BASE_URL ?>/configuracoes.php">Configurações</a>. Dá para montar a prévia, mas o envio pode falhar.</div>
<?php endif; ?>
<?php if ($erros): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if (!empty($okTeste)): ?><div class="alert alert-success"><?= e($okTeste) ?></div><?php endif; ?>

<form method="POST" id="formCob">
  <?= csrf_field() ?>
  <div class="row g-4 mb-4">
    <!-- 1. Quem cobrar -->
    <div class="col-xl-4">
      <div class="card h-100"><div class="card-header"><span class="passo">1</span> Quem está pendente</div><div class="card-body">
        <label class="form-label fw-semibold small" for="revisao_id">Revisão</label>
        <select id="revisao_id" name="revisao_id" class="form-select mb-3">
          <?php foreach ($revisoes as $r): ?><option value="<?= (int) $r['id'] ?>" <?= $o['revisao_id'] === (int) $r['id'] ? 'selected' : '' ?>><?= e($r['nome']) ?></option><?php endforeach; ?>
        </select>
        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label fw-semibold small" for="vinculo">Vínculo</label>
            <select id="vinculo" name="vinculo" class="form-select"><option value="">Todos</option>
              <?php foreach (array_keys(VINCULOS) as $v): ?><option <?= $o['vinculo'] === $v ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold small" for="grupo">Grupo</label>
            <select id="grupo" name="grupo" class="form-select"><option value="">Todos</option>
              <?php foreach (array_unique(array_values(VINCULOS)) as $g): ?><option <?= $o['grupo'] === $g ? 'selected' : '' ?>><?= e($g) ?></option><?php endforeach; ?></select>
          </div>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="so_nao_cobrados" name="so_nao_cobrados" value="1" <?= $o['so_nao_cobrados'] ? 'checked' : '' ?>>
          <label class="form-check-label small" for="so_nao_cobrados">Só quem ainda não foi cobrado nesta revisão</label>
        </div>
        <div class="form-text">Sempre só os pendentes: o andamento é relido no Horacius antes da prévia.</div>
      </div></div>
    </div>

    <!-- 2. Quem recebe -->
    <div class="col-xl-4">
      <div class="card h-100"><div class="card-header"><span class="passo">2</span> Quem recebe a cobrança</div><div class="card-body">
        <label class="opcao-alvo">
          <input type="radio" name="alvo" value="lider" <?= $o['alvo'] === 'lider' ? 'checked' : '' ?>>
          <span><strong>O gestor (líder imediato)</strong><small>Cada líder recebe um e-mail com as pessoas pendentes da equipe dele.</small></span>
        </label>
        <label class="opcao-alvo">
          <input type="radio" name="alvo" value="superior" <?= $o['alvo'] === 'superior' ? 'checked' : '' ?>>
          <span><strong>O líder do gestor (escalonamento)</strong><small>Sobe um nível: o líder do gestor recebe as pendências de todas as equipes abaixo dele, com o nome de cada gestor.</small></span>
        </label>
        <div class="form-check mt-2">
          <input class="form-check-input" type="checkbox" id="copiar_outro" name="copiar_outro" value="1" <?= $o['copiar_outro'] ? 'checked' : '' ?>>
          <label class="form-check-label small" for="copiar_outro" id="lblCopiar"><?= $o['alvo'] === 'superior' ? 'Colocar o gestor (líder imediato) em cópia' : 'Colocar o líder do gestor em cópia' ?></label>
        </div>
        <div class="tipo-copia" id="tipoCopiaOutro" <?= $o['copiar_outro'] ? '' : 'hidden' ?>>
          <span class="small text-muted">Como copiar:</span>
          <label><input type="radio" name="tipo_copia_outro" value="cc" <?= $o['tipo_copia_outro'] === 'cc' ? 'checked' : '' ?>> <strong>Cc</strong> <small>— o destinatário vê quem está em cópia</small></label>
          <label><input type="radio" name="tipo_copia_outro" value="cco" <?= $o['tipo_copia_outro'] === 'cco' ? 'checked' : '' ?>> <strong>Cco</strong> <small>— cópia oculta, o destinatário não vê</small></label>
        </div>
        <label class="form-label fw-semibold small mt-3" for="nao_enviar"><i class="bi bi-slash-circle me-1"></i>Não enviar para <span class="fw-normal text-muted">(nesta cobrança)</span></label>
        <textarea id="nao_enviar" name="nao_enviar" rows="4" class="form-control font-monospace small" placeholder="um e-mail por linha"><?= e($naoEnviarTexto) ?></textarea>
        <div class="form-text">Quem estiver aqui não recebe esta cobrança em nenhum campo (Para, Cc ou Cco). Se for um líder, as pessoas da equipe dele aparecem como "sem destinatário".</div>
      </div></div>
    </div>

    <!-- 3. Qual e-mail -->
    <div class="col-xl-4">
      <div class="card h-100"><div class="card-header"><span class="passo">3</span> O e-mail</div><div class="card-body">
        <label class="form-label fw-semibold small" for="modelo_id">Modelo</label>
        <select id="modelo_id" name="modelo_id" class="form-select mb-3">
          <?php foreach ($modelos as $m): ?><option value="<?= (int) $m['id'] ?>" <?= $o['modelo_id'] === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['nome']) ?></option><?php endforeach; ?>
        </select>
        <label class="form-label fw-semibold small" for="remetente">Remetente</label>
        <select id="remetente" name="remetente" class="form-select mb-3">
          <?php foreach ($rems as $r => $c): ?><option value="<?= e($r) ?>" <?= $o['remetente'] === $r ? 'selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?>
        </select>
        <label class="form-label fw-semibold small" for="prazo">Prazo <span class="text-muted fw-normal">({{prazo}})</span></label>
        <input id="prazo" name="prazo" class="form-control mb-3" value="<?= e($o['prazo']) ?>" placeholder="ex.: hoje, <?= date('d/m/Y') ?>">
        <label class="form-label fw-semibold small" for="copia_fixa">Cópia em todos os e-mails</label>
        <input id="copia_fixa" name="copia_fixa" class="form-control" value="<?= e($o['copia_fixa']) ?>" placeholder="separe por vírgula">
        <div class="tipo-copia mt-2">
          <label><input type="radio" name="tipo_copia_fixa" value="cc" <?= $o['tipo_copia_fixa'] === 'cc' ? 'checked' : '' ?>> <strong>Cc</strong></label>
          <label><input type="radio" name="tipo_copia_fixa" value="cco" <?= $o['tipo_copia_fixa'] === 'cco' ? 'checked' : '' ?>> <strong>Cco</strong> <small>(oculta)</small></label>
        </div>
      </div></div>
    </div>
  </div>

  <div class="d-flex justify-content-end mb-4">
    <button name="acao" value="previa" class="btn btn-primary px-4" <?= (!$revisoes || !$modelos) ? 'disabled' : '' ?>><i class="bi bi-eye me-1"></i> Gerar prévia</button>
  </div>

<?php if ($plano): $comDest = array_sum(array_map(fn($g) => count($g['pessoas']), $plano['grupos'])); $g0 = $plano['grupos'][0] ?? null; ?>
  <!-- Prévia -->
  <div class="row g-3 mb-4">
    <?php foreach ([['Pendentes no filtro', $plano['total'], 'bi-hourglass-split'], ['E-mails a enviar', count($plano['grupos']), 'bi-envelope'],
                    ['Pessoas cobradas', $comDest, 'bi-person-check'], ['Sem destinatário', count($plano['sem_destino']), 'bi-person-x']] as [$rot, $val, $ico]): ?>
      <div class="col-6 col-lg-3"><div class="card"><div class="card-body kpi">
        <div class="kpi-ico"><i class="bi <?= $ico ?>"></i></div><div><div class="kpi-num"><?= $n($val) ?></div><div class="kpi-rot"><?= $rot ?></div></div>
      </div></div></div>
    <?php endforeach; ?>
  </div>

  <?php if (!empty($plano['copias_bloqueadas'])): ?>
    <div class="alert alert-info small"><i class="bi bi-slash-circle me-1"></i> <?= $n($plano['copias_bloqueadas']) ?> cópia(s) retirada(s) por estarem em "não enviar para".</div>
  <?php endif; ?>
  <?php if ($plano['grupos']): ?>
  <div class="row g-4 mb-4">
    <div class="col-xl-6">
      <div class="card h-100"><div class="card-header d-flex justify-content-between"><span>Destinatários</span><span class="small fw-normal text-muted"><?= $o['alvo'] === 'superior' ? 'líderes dos gestores' : 'gestores' ?></span></div>
      <div class="card-body p-0"><div class="table-responsive" style="max-height:560px">
        <table class="table table-sm table-hover align-middle mb-0 small">
          <thead class="table-light" style="position:sticky;top:0"><tr><th class="ps-3">Recebe</th><th>Cópias</th><th class="text-end pe-3">Pessoas</th></tr></thead>
          <tbody>
            <?php foreach ($plano['grupos'] as $g): ?>
              <tr><td class="ps-3"><div class="fw-semibold"><?= e($g['nome']) ?></div><div class="text-muted"><?= e($g['email']) ?></div></td>
                  <td class="text-muted">
                    <?php if ($g['cc']): ?><div><span class="tag-copia">Cc</span> <?= e(implode(', ', $g['cc'])) ?></div><?php endif; ?>
                    <?php if ($g['cco']): ?><div><span class="tag-copia tag-cco">Cco</span> <?= e(implode(', ', $g['cco'])) ?></div><?php endif; ?>
                    <?= (!$g['cc'] && !$g['cco']) ? '—' : '' ?>
                  </td>
                  <td class="text-end pe-3 fw-semibold"><?= $n(count($g['pessoas'])) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div></div></div>
    </div>
    <div class="col-xl-6">
      <div class="card h-100"><div class="card-header">E-mail de <?= e($g0['nome']) ?> <span class="small fw-normal text-muted">— o maior da lista</span></div>
        <div class="card-body p-0" style="background:#f4f4f4">
          <?php $ctx0 = ['nome' => $g0['nome'], 'prazo' => $o['prazo'], 'revisao' => revisao($o['revisao_id'])['nome']]; ?>
          <div class="px-3 py-2 small bg-white border-bottom"><strong><?= e(cobranca_aplicar($modelo['assunto'], $ctx0, $g0['pessoas'], $o['alvo'] === 'superior', false)) ?></strong></div>
          <iframe style="width:100%;height:520px;border:0" srcdoc="<?= e(email_html(cobranca_aplicar($modelo['corpo'], $ctx0, $g0['pessoas'], $o['alvo'] === 'superior'), $modelo['topo'], $modelo['rodape'], false)) ?>"></iframe>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($plano['sem_destino']): $mot = array_count_values(array_column($plano['sem_destino'], 'motivo')); arsort($mot); ?>
    <div class="card mb-4 border-warning">
      <div class="card-header"><i class="bi bi-person-x me-1 text-warning"></i> <?= $n(count($plano['sem_destino'])) ?> pessoa(s) sem destinatário — não entram nesta cobrança</div>
      <div class="card-body">
        <div class="d-flex flex-wrap gap-2 mb-3"><?php foreach ($mot as $k => $v): ?><span class="badge badge-pend"><?= e($k) ?>: <?= $n($v) ?></span><?php endforeach; ?></div>
        <div class="small text-muted mb-2">Para incluí-las: atualize a <a href="<?= BASE_URL ?>/funcionarios.php">base de funcionários</a>, ou envie a base da revisão com as colunas "Nome do lider" e "E-mail do lider".</div>
        <details><summary class="small">Ver a lista</summary>
          <div class="table-responsive mt-2" style="max-height:320px"><table class="table table-sm small mb-0">
            <thead><tr><th>Código</th><th>Nome</th><th>Vínculo</th><th>Motivo</th></tr></thead><tbody>
            <?php foreach ($plano['sem_destino'] as $s): ?><tr><td><?= (int) $s['codigo'] ?></td><td><?= e($s['nome']) ?></td><td><?= e($s['vinculo']) ?></td><td><?= e($s['motivo']) ?></td></tr><?php endforeach; ?>
          </tbody></table></div>
        </details>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($plano['grupos']): ?>
    <div class="cfg-barra-cob">
      <span class="small text-muted"><?= $n(count($plano['grupos'])) ?> e-mail(s) de <strong><?= e($o['remetente']) ?></strong>, cobrando <?= $n($comDest) ?> pessoa(s).</span>
      <div class="d-flex gap-2">
        <button name="acao" value="teste" class="btn btn-outline-primary"><i class="bi bi-envelope-check me-1"></i> Enviar teste para mim</button>
        <button name="acao" value="enviar" class="btn btn-primary" data-confirm="Enviar <?= $n(count($plano['grupos'])) ?> e-mail(s) de cobrança?"
                data-confirm-text="Saem de <?= e($o['remetente']) ?> para os destinatários listados. Fica tudo registrado no histórico." data-confirm-icon="question" data-confirm-btn="Sim, enviar">
          <i class="bi bi-send me-1"></i> Confirmar e enviar</button>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>
</form>

<?php
$extraJs = '<script>
var cbOutro = document.getElementById("copiar_outro");
cbOutro.addEventListener("change", function () { document.getElementById("tipoCopiaOutro").hidden = !cbOutro.checked; });
document.querySelectorAll("[name=alvo]").forEach(function (r) {
  r.addEventListener("change", function () {
    document.getElementById("lblCopiar").textContent = this.value === "superior"
      ? "Colocar o gestor (líder imediato) em cópia" : "Colocar o líder do gestor em cópia";
  });
});
</script>';
require_once __DIR__ . '/includes/views/footer.php';
