<?php
/**
 * Prévia de e-mail (dentro de um iframe):
 *   POST assunto/topo/rodape/corpo → um modelo, com dados de exemplo;
 *   GET  envio=id                  → um e-mail já registrado de uma cobrança.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cobranca.php';
require_login();

header('X-Frame-Options: SAMEORIGIN');

if (isset($_GET['envio'])) {
    $st = db()->prepare('SELECT * FROM cobranca_envios WHERE id = ?');
    $st->execute([(int) $_GET['envio']]);
    $e = $st->fetch();
    if (!$e) { http_response_code(404); exit('E-mail não encontrado.'); }
    $assunto = $e['assunto']; $html = email_html($e['corpo'], $e['topo'], $e['rodape'], false);
    $cab = ['Para' => $e['para_nome'] . ' <' . $e['para_email'] . '>', 'Cc' => $e['cc']];
} else {
    if (!csrf_ok()) { http_response_code(403); exit('Sessão expirada.'); }
    $exemplo = [
        ['nome' => 'MARIA DA SILVA', 'vinculo' => 'Empregado', '_lider' => 'JOÃO PEREIRA'],
        ['nome' => 'JOSÉ SANTOS', 'vinculo' => 'Professor', '_lider' => 'JOÃO PEREIRA'],
        ['nome' => 'ANA OLIVEIRA', 'vinculo' => 'Terceirizado', '_lider' => 'CARLA MENDES'],
    ];
    $ctx = ['nome' => 'NOME DO LÍDER', 'prazo' => 'hoje, ' . date('d/m/Y'), 'revisao' => 'Revisão de Acessos ' . date('Y')];
    $assunto = cobranca_aplicar((string) ($_POST['assunto'] ?? ''), $ctx, $exemplo, true, false);
    $html = email_html(cobranca_aplicar((string) ($_POST['corpo'] ?? ''), $ctx, $exemplo, true),
                       (string) ($_POST['topo'] ?? ''), (string) ($_POST['rodape'] ?? ''), false);
    $cab = ['Para' => 'NOME DO LÍDER <lider@exemplo>'];
}
?><!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="UTF-8"><style>
  body { margin: 0; font-family: Segoe UI, Arial, sans-serif; }
  .cab { background: #fff; border-bottom: 1px solid #e5e5e5; padding: 12px 20px; font-size: 13px; color: #444; }
  .cab b { display: inline-block; width: 64px; color: #888; font-weight: 500; }
</style></head><body>
<div class="cab">
  <div><b>Assunto</b> <strong><?= e($assunto) ?></strong></div>
  <?php foreach ($cab as $k => $v): if ($v === '') continue; ?><div><b><?= e($k) ?></b> <?= e($v) ?></div><?php endforeach; ?>
</div>
<?= $html ?>
</body></html>
