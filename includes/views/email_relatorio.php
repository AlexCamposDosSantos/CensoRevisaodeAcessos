<?php
/**
 * Corpo do relatório de andamento por e-mail ($d = relatorio_dados()). Feito
 * para cliente de e-mail: tabela, estilo em atributo, nenhuma folha externa —
 * o Outlook ignora <style>, flex e grid. Vai dentro do quadro de email_html().
 */
$n   = fn($x) => number_format((int) $x, 0, ',', '.');
$num = $d['num'];
$barra = function (int $c, int $t): string {
    $p = $t ? max(0, min(100, round(100 * $c / $t))) : 0;
    return '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;"><tr>'
         . ($p > 0 ? '<td width="' . $p . '%" style="background:#0f6e8c;height:8px;font-size:0;line-height:0;">&nbsp;</td>' : '')
         . ($p < 100 ? '<td width="' . (100 - $p) . '%" style="background:#c9d1da;height:8px;font-size:0;line-height:0;">&nbsp;</td>' : '')
         . '</tr></table>';
};
$th = 'style="text-align:left;font-size:12px;color:#6b7785;font-weight:bold;padding:6px 8px;border-bottom:1px solid #e3e8ee;"';
$thR = str_replace('text-align:left', 'text-align:right', $th);
$td = 'style="font-size:14px;padding:7px 8px;border-bottom:1px solid #eef1f4;"';
$tdR = 'style="font-size:14px;padding:7px 8px;border-bottom:1px solid #eef1f4;text-align:right;white-space:nowrap;"';
$lideres = array_slice($d['prior']['lideres'], 0, 10);
$link = censo_env('CENSO_BASE_URL') ? BASE_URL . '/dashboard.php?r=' . (int) $d['revisao']['id'] : '';
?>
<div style="font-size:12px;color:#0f6e8c;font-weight:bold;letter-spacing:1px;text-transform:uppercase;">Censo · andamento da revisão</div>
<h2 style="margin:4px 0 2px;font-size:22px;color:#1d2733;"><?= e($d['revisao']['nome']) ?></h2>
<div style="font-size:13px;color:#6b7785;margin-bottom:20px;">
  Atualizado em <?= $num['em'] ? date('d/m/Y \à\s H:i', strtotime($num['em'])) : '—' ?> · Horacius (<?= e($num['banco']) ?>)
</div>

<?php $m = $d['meta']; $vm = $m['variacao']; ?>
<!-- O número que conta: o grupo da meta, em destaque -->
<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-bottom:12px;">
  <tr><td style="padding:18px 20px;background:#e6f3f7;border-radius:8px;border-left:5px solid #0f6e8c;">
    <div style="font-size:12px;color:#0b5269;font-weight:bold;letter-spacing:.5px;text-transform:uppercase;">Meta da revisão · <?= e($m['nome']) ?></div>
    <div style="font-size:46px;line-height:1.1;font-weight:bold;color:#0b5269;margin:6px 0 10px;"><?= pct($m['concluidos'], $m['total']) ?>% <span style="font-size:16px;font-weight:normal;color:#3d6b7a;">concluído</span></div>
    <?= $barra($m['concluidos'], $m['total']) ?>
    <div style="font-size:14px;color:#1d2733;margin-top:10px;">
      <strong><?= $n($m['concluidos']) ?></strong> de <?= $n($m['total']) ?> já revisaram · <strong><?= $n($m['pendentes']) ?></strong> pendentes
    </div>
    <?php if ($vm !== null): ?>
      <div style="font-size:13px;color:#3d6b7a;margin-top:4px;">
        Desde a atualização automática de <?= date('d/m H:i', strtotime((string) $d['anterior']['em'])) ?>:
        <strong style="color:#1d2733;"><?= $vm > 0 ? '+' . $n($vm) . ' concluíram' : ($vm < 0 ? $n($vm) . ' (houve correção na base)' : 'ninguém concluiu') ?></strong>.
      </div>
    <?php endif; ?>
  </td></tr>
</table>

<!-- Secundários: os outros grupos e o geral, menores -->
<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-bottom:24px;">
  <tr>
    <?php foreach ($d['grupos'] as $nome => $g): if ($nome === $m['nome']) continue; $t = $g['concluido'] + $g['pendente']; ?>
      <td width="49%" valign="top" style="padding:12px 14px;background:#f4f6f8;border-radius:6px;">
        <div style="font-size:12px;color:#6b7785;"><?= e($nome) ?></div>
        <div style="font-size:22px;font-weight:bold;color:#1d2733;margin:2px 0 6px;"><?= pct($g['concluido'], $t) ?>%</div>
        <?= $barra($g['concluido'], $t) ?>
        <div style="font-size:12px;color:#6b7785;margin-top:6px;"><?= $n($g['pendente']) ?> pendentes de <?= $n($t) ?></div>
      </td>
      <td width="2%"></td>
    <?php endforeach; ?>
    <td width="49%" valign="top" style="padding:12px 14px;background:#f4f6f8;border-radius:6px;">
      <div style="font-size:12px;color:#6b7785;">Geral — todos os vínculos</div>
      <div style="font-size:22px;font-weight:bold;color:#1d2733;margin:2px 0 6px;"><?= pct($num['concluidos'], $d['total']) ?>%</div>
      <?= $barra($num['concluidos'], $d['total']) ?>
      <div style="font-size:12px;color:#6b7785;margin-top:6px;"><?= $n($num['pendentes']) ?> pendentes de <?= $n($d['total']) ?></div>
    </td>
  </tr>
</table>

<h3 style="font-size:16px;color:#1d2733;margin:26px 0 6px;">Por vínculo</h3>
<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
  <tr><th <?= $th ?>>Vínculo</th><th <?= $thR ?>>Concluído</th><th <?= $thR ?>>Pendentes</th></tr>
  <?php foreach ($d['vinculos'] as $nome => $x): ?>
    <tr><td <?= $td ?>><?= e($nome) ?></td><td <?= $tdR ?>><?= pct($x['concluido'], $x['concluido'] + $x['pendente']) ?>%</td><td <?= $tdR ?>><?= $n($x['pendente']) ?></td></tr>
  <?php endforeach; ?>
</table>

<h3 style="font-size:16px;color:#1d2733;margin:26px 0 6px;">Cobranças</h3>
<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
  <tr><td <?= $td ?>>Pendentes que nunca foram cobrados</td><td <?= $tdR ?>><strong><?= $n($d['prior']['nunca_cobrados']) ?></strong></td></tr>
  <tr><td <?= $td ?>>Cobrados há 7 dias ou mais e ainda pendentes</td><td <?= $tdR ?>><strong><?= $n($d['prior']['cobrados_7dias']) ?></strong></td></tr>
  <tr><td <?= $td ?>>Pendentes sem responsável com e-mail</td><td <?= $tdR ?>><strong><?= $n($d['prior']['sem_destino']) ?></strong></td></tr>
</table>

<?php if ($lideres): ?>
  <h3 style="font-size:16px;color:#1d2733;margin:26px 0 6px;">Responsáveis com mais pendentes</h3>
  <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
    <tr><th <?= $th ?>>Responsável</th><th <?= $thR ?>>Pendentes</th><th <?= $thR ?>>Última cobrança</th></tr>
    <?php foreach ($lideres as $l): ?>
      <tr>
        <td <?= $td ?>><?= e($l['nome'] ?: $l['email']) ?><div style="font-size:12px;color:#6b7785;"><?= e($l['email']) ?></div></td>
        <td <?= $tdR ?>><?= $n($l['pendentes']) ?></td>
        <td <?= $tdR ?>><?= $l['ultima_cobranca'] ? date('d/m/Y', strtotime($l['ultima_cobranca'])) : '<span style="color:#b45309;">nunca</span>' ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>

<?php if ($link): ?>
  <p style="margin:28px 0 0;"><a href="<?= e($link) ?>" style="display:inline-block;background:#0f6e8c;color:#ffffff;text-decoration:none;padding:10px 20px;border-radius:6px;font-weight:bold;font-size:14px;">Abrir o painel no Censo</a></p>
<?php endif; ?>
<p style="font-size:12px;color:#8a95a1;margin:24px 0 0;">E-mail automático do Censo, enviado após a atualização agendada do andamento.</p>
