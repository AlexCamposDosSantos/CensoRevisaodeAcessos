<?php
/**
 * Configurações (só administrador): mostra se o Censo tem o que precisa para
 * rodar sozinho e testa cada parte. As senhas ficam só no .env —
 * nunca na tela.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/services/cobranca.php';
require_once __DIR__ . '/includes/services/agendamento.php';
require_once __DIR__ . '/includes/services/basefull.php';
require_admin();

$teste = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        flash('erro', 'Sessão expirada. Tente novamente.');
    } elseif (($_POST['acao'] ?? '') === 'banco') {
        $k = (string) ($_POST['banco'] ?? banco_atual());
        try {
            $t = microtime(true);
            $n = (int) horacius_db($k)->query('SELECT COUNT(*) FROM ac_revisao WHERE datahoraregistro >= DATE_SUB(NOW(), INTERVAL 30 DAY)')->fetchColumn();
            flash('ok', sprintf('Horacius (%s) respondeu em %.1fs — %s revisões registradas nos últimos 30 dias.', horacius_rotulo($k), microtime(true) - $t, number_format($n, 0, ',', '.')));
        } catch (Throwable $e) {
            flash('erro', 'O Horacius (' . horacius_rotulo($k) . ') não respondeu: ' . $e->getMessage());
        }
    } elseif (($_POST['acao'] ?? '') === 'agenda') {
        [, $erros] = agenda_salvar_form($_POST);
        if ($erros) {
            flash('erro', 'Nada foi salvo. ' . implode(' ', $erros));
            $_SESSION['agenda_form'] = $_POST;   // devolve o que foi digitado, para corrigir
        } else {
            flash('ok', 'Agendamento salvo.');
        }
    } elseif (($_POST['acao'] ?? '') === 'forcar') {
        agenda_marcar(['forcar' => true]);
        flash('ok', 'Pedido registrado: a atualização roda na próxima verificação do servidor (até 10 minutos), com o relatório se ele estiver ligado.');
    } elseif (($_POST['acao'] ?? '') === 'relatorio_teste') {
        $rev = agenda_revisao();
        $eu  = current_user();
        if (!$rev) {
            flash('erro', 'Não há revisão para relatar.');
        } else {
            $ant  = agenda_load()['ultimo_resumo'];
            $erro = relatorio_enviar($rev, ($ant['revisao_id'] ?? null) === (int) $rev['id'] ? $ant : null, [$eu['email']]);
            $erro ? flash('erro', 'O relatório de teste não saiu: ' . $erro) : flash('ok', 'Relatório de teste enviado só para ' . $eu['email'] . ', com os números da última leitura.');
        }
    } elseif (($_POST['acao'] ?? '') === 'email') {
        $rem = (string) ($_POST['remetente'] ?? '');
        $eu  = current_user();
        if (!isset(remetentes()[$rem])) {
            flash('erro', 'Escolha um remetente.');
        } else {
            $erro = email_enviar($rem, $eu['email'], $eu['name'], [], '[Censo] Teste de envio',
                '<p>Olá, ' . e($eu['name']) . '!</p><p>Este é um e-mail de teste do <strong>Censo</strong>, enviado pela caixa <strong>' . e($rem) . '</strong>.</p><p>Se você está lendo, o envio das cobranças está funcionando.</p>',
                imagens_email()['topo'][0] ?? '', imagens_email()['rodape'][0] ?? '');
            $erro ? flash('erro', 'O e-mail de teste não saiu: ' . $erro) : flash('ok', 'E-mail de teste enviado de ' . $rem . ' para ' . $eu['email'] . '.');
        }
    }
    header('Location: ' . BASE_URL . '/configuracoes.php');
    exit;
}

$pend = config_pendencias();
$ag   = agenda_load();
if (!empty($_SESSION['agenda_form'])) {   // formulário recusado: mostra de novo o que foi digitado
    $f = $_SESSION['agenda_form'];
    unset($_SESSION['agenda_form']);
    foreach (['modo', 'hora', 'dia_semana', 'intervalo_horas', 'relatorio_freq', 'relatorio_dia', 'relatorio_remetente', 'relatorio_para', 'relatorio_copia', 'relatorio_assunto'] as $k) {
        if (isset($f[$k])) $ag[$k] = (string) $f[$k];
    }
    $ag['ativo'] = !empty($f['ativo']);
    $ag['relatorio'] = !empty($f['relatorio']);
}
$dh = fn($s) => $s ? date('d/m/Y H:i', strtotime($s)) : '—';
$tiqueVelho = !$ag['tique'] || time() - strtotime($ag['tique']) > 30 * 60;
// Comando para o Agendador de Tarefas: o php.exe deste servidor (no Apache, PHP_BINARY pode não ser o CLI)
$php = preg_match('/php(-cgi)?(\.exe)?$/i', PHP_BINARY) ? preg_replace('/-cgi(?=(\.exe)?$)/i', '', PHP_BINARY) : 'php';
$comando = '"' . $php . '" "' . __DIR__ . DIRECTORY_SEPARATOR . 'cli.php" cron';
$pageTitle = 'Configurações';
require_once __DIR__ . '/includes/views/header.php';
?>

<div class="card mb-4">
  <div class="card-body d-flex align-items-start gap-3">
    <i class="bi <?= $pend ? 'bi-exclamation-triangle text-warning' : 'bi-check-circle' ?> fs-2" style="<?= $pend ? '' : 'color:var(--censo)' ?>"></i>
    <div>
      <div class="fw-bold fs-5"><?= $pend ? 'Falta configurar' : 'O Censo tem tudo para rodar sozinho' ?></div>
      <?php if ($pend): ?>
        <ul class="mb-0 small"><?php foreach ($pend as $p): ?><li><?= e($p) ?></li><?php endforeach; ?></ul>
      <?php else: ?>
        <div class="small text-muted">Banco do Horacius e envio de e-mail configurados no <code>.env</code> do Censo — ele não depende da configuração de nenhum outro sistema.</div>
      <?php endif; ?>
      <div class="small text-muted mt-2">As senhas ficam só no arquivo <code>.env</code> (fora do git). Modelo: <code>.env.example</code>.</div>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="card h-100"><div class="card-header">Banco do Horacius</div><div class="card-body">
      <?php foreach (bancos() as $k => $b): $emUso = $k === banco_atual(); ?>
        <div class="banco-item <?= $emUso ? 'em-uso' : '' ?>">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <strong><?= e($b['rotulo'] ?? $k) ?></strong>
            <?= $emUso ? '<span class="badge badge-ativo">em uso</span>' : '<span class="small text-muted">troca no switch do Painel</span>' ?>
          </div>
          <div class="small text-muted mb-2"><?= e(($b['host'] ?? '—') . ':' . ($b['port'] ?? '3306')) ?> · banco <?= e($b['name'] ?? 'horacius') ?> · usuário <?= e($b['user'] ?? '—') ?></div>
          <form method="POST"><?= csrf_field() ?><input type="hidden" name="acao" value="banco"><input type="hidden" name="banco" value="<?= e($k) ?>">
            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-plug me-1"></i> Testar conexão</button></form>
        </div>
      <?php endforeach; ?>
      <?php if (!bancos()): ?><div class="text-muted small">Nenhum banco configurado. Preencha CENSO_DB_AMBIENTES e os campos CENSO_DB_* no .env.</div><?php endif; ?>
    </div></div>
  </div>
  <div class="col-lg-6">
    <div class="card h-100"><div class="card-header">Envio de e-mail (Microsoft Graph)</div><div class="card-body">
      <dl class="row small mb-3">
        <dt class="col-4">Aplicação</dt><dd class="col-8"><?= e(censo_cfg('CENSO_GRAPH_CLIENT_ID') ? substr(censo_cfg('CENSO_GRAPH_CLIENT_ID'), 0, 8) . '…' : '—') ?></dd>
        <dt class="col-4">Remetentes</dt><dd class="col-8"><?= e(implode(', ', array_keys(remetentes())) ?: '—') ?></dd>
        <dt class="col-4">BaseFull</dt><dd class="col-8"><?= basefull_configurado() ? 'baixada do SharePoint a cada atualização (' . e(basefull_cfg()['pasta']) . ') · credenciais do ' . e(substr(basefull_cfg()['origem'], 8)) : '<span class="text-warning-emphasis">só envio manual — sem credenciais SHAREPOINT_* no .env do Prumo</span>' ?></dd>
        <dt class="col-4">Link da revisão</dt><dd class="col-8 text-break"><?= e(link_revisao()) ?></dd>
      </dl>
      <form method="POST" class="d-flex flex-wrap gap-2"><?= csrf_field() ?><input type="hidden" name="acao" value="email">
        <select name="remetente" class="form-select" style="max-width:320px">
          <?php foreach (remetentes() as $r => $c): ?><option value="<?= e($r) ?>"><?= e($r) ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-outline-primary"><i class="bi bi-envelope me-1"></i> Enviar teste para mim</button>
      </form>
      <div class="form-text">Vai para <?= e(current_user()['email']) ?>.</div>
    </div></div>
  </div>
</div>

<!-- Atualização automática + relatório: um formulário só (é a mesma decisão: quando o Censo age sozinho) -->
<form method="POST" id="form-agenda" class="mt-4">
  <?= csrf_field() ?><input type="hidden" name="acao" value="agenda">
  <div class="row g-4">
    <div class="col-lg-7">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="bi bi-clock-history me-2 text-muted"></i>Atualização automática do andamento</span>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" role="switch" id="ativo" name="ativo" value="1" <?= $ag['ativo'] ? 'checked' : '' ?>>
            <label class="form-check-label small" for="ativo" data-on="Ligada" data-off="Desligada"><?= $ag['ativo'] ? 'Ligada' : 'Desligada' ?></label>
          </div>
        </div>
        <div class="card-body">
          <p class="small text-muted">Lê o andamento da <strong>revisão mais recente</strong> no Horacius (banco em uso: <?= e(horacius_rotulo()) ?>), sem ninguém precisar abrir o painel.</p>
          <div class="row g-3 align-items-end">
            <div class="col-sm-4">
              <label class="form-label fw-semibold" for="modo">Frequência</label>
              <select id="modo" name="modo" class="form-select">
                <option value="semanal" <?= $ag['modo'] === 'semanal' ? 'selected' : '' ?>>Uma vez por semana</option>
                <option value="diario" <?= $ag['modo'] === 'diario' ? 'selected' : '' ?>>Todo dia</option>
                <option value="intervalo" <?= $ag['modo'] === 'intervalo' ? 'selected' : '' ?>>A cada N horas</option>
              </select>
            </div>
            <div class="col-sm-4" data-modo="semanal">
              <label class="form-label fw-semibold" for="dia_semana">Dia</label>
              <select id="dia_semana" name="dia_semana" class="form-select">
                <?php foreach (DIAS_SEMANA as $k => $nome): ?><option value="<?= $k ?>" <?= (int) $ag['dia_semana'] === $k ? 'selected' : '' ?>><?= ucfirst($nome) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-4" data-modo="semanal diario">
              <label class="form-label fw-semibold" for="hora">Horário</label>
              <input type="time" id="hora" name="hora" class="form-control" value="<?= e($ag['hora']) ?>">
            </div>
            <div class="col-sm-4" data-modo="intervalo">
              <label class="form-label fw-semibold" for="intervalo_horas">A cada (horas)</label>
              <input type="number" id="intervalo_horas" name="intervalo_horas" min="1" max="168" class="form-control" value="<?= (int) $ag['intervalo_horas'] ?>">
            </div>
          </div>
          <div class="form-text mt-2">O servidor verifica a cada 10 minutos, no mesmo agendador do Prumo: a atualização sai na primeira verificação a partir do horário. Se o horário passar com o servidor desligado, ela sai na verificação seguinte.</div>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card h-100"><div class="card-header"><i class="bi bi-activity me-2 text-muted"></i>Situação</div><div class="card-body">
        <dl class="row small mb-3">
          <dt class="col-5">Hora do servidor</dt><dd class="col-7"><?= date('d/m/Y H:i') ?> <span class="text-muted">(<?= e(date_default_timezone_get()) ?>)</span></dd>
          <dt class="col-5">Agendamento</dt><dd class="col-7"><?= e(agenda_descricao($ag) ?? 'desligado') ?></dd>
          <dt class="col-5">Próxima</dt><dd class="col-7 fw-semibold"><?= e(agenda_proxima($ag)) ?></dd>
          <dt class="col-5">Última</dt><dd class="col-7"><?= $dh($ag['ultima_execucao']) ?>
            <?php if ($ag['ultimo_erro']): ?><div class="text-danger" title="<?= e($ag['ultimo_erro']) ?>"><?= e(mb_strimwidth($ag['ultimo_erro'], 0, 140, '…')) ?></div>
            <?php elseif ($ag['ultimo_resumo']): $ur = $ag['ultimo_resumo']; ?>
              <?php if (isset($ur['meta_concluidos'])): $mt = $ur['meta_concluidos'] + $ur['meta_pendentes']; ?>
                <div class="text-muted"><strong><?= pct((int) $ur['meta_concluidos'], (int) $mt) ?>%</strong> na meta (<?= e(GRUPO_META) ?>) · <?= number_format((int) $ur['meta_pendentes'], 0, ',', '.') ?> pendentes</div>
              <?php else: ?>
                <div class="text-muted"><?= number_format((int) $ur['concluidos'], 0, ',', '.') ?> concluídos · <?= number_format((int) $ur['pendentes'], 0, ',', '.') ?> pendentes</div>
              <?php endif; ?>
            <?php endif; ?></dd>
          <dt class="col-5">Último relatório</dt><dd class="col-7"><?= $dh($ag['relatorio_enviado']) ?>
            <?php if ($ag['relatorio_erro']): ?><div class="text-danger" title="<?= e($ag['relatorio_erro']) ?>"><?= e(mb_strimwidth($ag['relatorio_erro'], 0, 140, '…')) ?></div><?php endif; ?></dd>
          <dt class="col-5">Servidor visto</dt><dd class="col-7"><?= $dh($ag['tique']) ?></dd>
        </dl>
        <?php if ($tiqueVelho): ?>
          <div class="alert alert-warning small mb-2">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <?= $ag['tique'] ? 'O agendador não chama o Censo há mais de 30 minutos.' : 'O agendador ainda não chamou o Censo.' ?>
            Sem ele, nada roda sozinho. O Censo pega carona no tique do <strong>Prumo</strong> — veja onde a corrente parou:
          </div>
          <ul class="list-unstyled small mb-3 diag-agenda">
            <?php foreach (agenda_diagnostico() as $d): ?>
              <li class="d-flex gap-2 mb-2">
                <i class="bi <?= $d['ok'] === true ? 'bi-check-circle-fill text-success' : ($d['ok'] === false ? 'bi-x-circle-fill text-danger' : 'bi-dash-circle text-muted') ?> mt-1"></i>
                <div><strong><?= e($d['titulo']) ?></strong><div class="text-muted text-break" style="white-space:pre-line"><?= e($d['detalhe']) ?></div></div>
              </li>
            <?php endforeach; ?>
          </ul>
          <details class="small mb-3"><summary class="text-muted">Sem o Prumo no servidor: tarefa própria</summary>
            <div class="mt-1">Crie uma tarefa a cada 10 minutos, como SYSTEM, com:</div>
            <code class="d-block mt-1 text-break user-select-all"><?= e($comando) ?></code>
          </details>
        <?php endif; ?>
        <button type="submit" form="form-forcar" class="btn btn-outline-primary btn-sm"><i class="bi bi-play-circle me-1"></i> Rodar na próxima verificação</button>
      </div></div>
    </div>

    <div class="col-12">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="bi bi-envelope-paper me-2 text-muted"></i>Relatório de andamento por e-mail</span>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" role="switch" id="relatorio" name="relatorio" value="1" <?= $ag['relatorio'] ? 'checked' : '' ?>>
            <label class="form-check-label small" for="relatorio" data-on="Ligado" data-off="Desligado"><?= $ag['relatorio'] ? 'Ligado' : 'Desligado' ?></label>
          </div>
        </div>
        <div class="card-body">
          <p class="small text-muted">Sai logo depois da atualização automática, com os números novos: percentual por grupo e vínculo, quanto andou desde a atualização anterior, cobranças e os responsáveis com mais pendentes. Se a leitura do Horacius falhar, o relatório não sai — número velho num e-mail novo engana.</p>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Quando enviar</label>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="relatorio_freq" id="freq_cada" value="cada" <?= $ag['relatorio_freq'] !== 'semanal' ? 'checked' : '' ?>>
                <label class="form-check-label" for="freq_cada">Depois de cada atualização automática</label>
              </div>
              <div class="form-check d-flex align-items-center gap-2 flex-wrap">
                <input class="form-check-input" type="radio" name="relatorio_freq" id="freq_semanal" value="semanal" <?= $ag['relatorio_freq'] === 'semanal' ? 'checked' : '' ?>>
                <label class="form-check-label" for="freq_semanal">Uma vez por semana, na primeira atualização a partir de</label>
                <select name="relatorio_dia" class="form-select form-select-sm" style="width:auto;min-width:130px" aria-label="Dia do relatório semanal">
                  <?php foreach (DIAS_SEMANA as $k => $nome): ?><option value="<?= $k ?>" <?= (int) $ag['relatorio_dia'] === $k ? 'selected' : '' ?>><?= $nome ?></option><?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold" for="relatorio_remetente">Enviado por</label>
              <select id="relatorio_remetente" name="relatorio_remetente" class="form-select">
                <option value="">— escolha a caixa —</option>
                <?php foreach (remetentes() as $r => $c): ?><option value="<?= e($r) ?>" <?= $ag['relatorio_remetente'] === $r ? 'selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold" for="relatorio_para">Destinatários</label>
              <textarea id="relatorio_para" name="relatorio_para" rows="3" class="form-control" placeholder="um e-mail por linha"><?= e($ag['relatorio_para']) ?></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold" for="relatorio_copia">Em cópia (Cc)</label>
              <textarea id="relatorio_copia" name="relatorio_copia" rows="3" class="form-control" placeholder="opcional"><?= e($ag['relatorio_copia']) ?></textarea>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold" for="relatorio_assunto">Assunto</label>
              <input type="text" id="relatorio_assunto" name="relatorio_assunto" maxlength="200" class="form-control" value="<?= e($ag['relatorio_assunto']) ?>" placeholder="<?= e(RELATORIO_ASSUNTO_PADRAO) ?>">
              <div class="form-text">Vazio usa o padrão. Marcadores:
                <?php foreach (RELATORIO_MARCADORES as $m => $desc): ?><code title="<?= e($desc) ?>"><?= e($m) ?></code> <?php endforeach; ?></div>
            </div>
          </div>
          <div class="small text-muted mt-3">Agora: <strong><?= e(agenda_relatorio_descricao($ag)) ?></strong>.</div>
        </div>
        <div class="card-footer bg-transparent d-flex flex-wrap gap-2 justify-content-between">
          <button type="submit" form="form-teste" class="btn btn-outline-secondary"><i class="bi bi-envelope me-1"></i> Enviar relatório de teste para mim</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-floppy me-1"></i> Salvar agendamento</button>
        </div>
      </div>
    </div>
  </div>
</form>
<form method="POST" id="form-forcar"><?= csrf_field() ?><input type="hidden" name="acao" value="forcar"></form>
<form method="POST" id="form-teste"><?= csrf_field() ?><input type="hidden" name="acao" value="relatorio_teste"></form>

<script>
// Mostra só os campos do modo escolhido
(function () {
  var sel = document.getElementById('modo');
  function aplicar() {
    document.querySelectorAll('#form-agenda [data-modo]').forEach(function (el) {
      el.hidden = el.dataset.modo.split(' ').indexOf(sel.value) < 0;
    });
  }
  sel.addEventListener('change', aplicar);
  aplicar();
  // O rótulo do interruptor diz o estado (um "Ligado" fixo ao lado de um interruptor desligado engana)
  document.querySelectorAll('#form-agenda [role="switch"]').forEach(function (sw) {
    var rot = document.querySelector('label[for="' + sw.id + '"][data-on]');
    sw.addEventListener('change', function () { rot.textContent = sw.checked ? rot.dataset.on : rot.dataset.off; });
  });
})();
</script>

<?php require_once __DIR__ . '/includes/views/footer.php'; ?>
