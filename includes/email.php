<?php
/**
 * E-mail do Censo: montagem (topo + corpo + rodapé, no mesmo desenho do Mala
 * Direta) e envio pela Microsoft Graph, com as credenciais do PRÓPRIO Censo
 * (config.local.php: CENSO_GRAPH_* e CENSO_REMETENTES).
 *
 * Topo e rodapé vão EMBUTIDOS na mensagem (anexo inline, cid:) — não dependem
 * de o destinatário alcançar o servidor do Censo para ver as imagens.
 */
require_once __DIR__ . '/fontes.php';

function pasta_imagens_email(): string
{
    return dirname(__DIR__) . '/assets/email';
}

/** Imagens disponíveis: ['topo' => [...arquivos], 'rodape' => [...]] */
function imagens_email(): array
{
    $out = ['topo' => [], 'rodape' => []];
    foreach (glob(pasta_imagens_email() . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE) ?: [] as $f) {
        $n = basename($f);
        $out[stripos($n, 'roda') === 0 ? 'rodape' : 'topo'][] = $n;
    }
    natcasesort($out['topo']); natcasesort($out['rodape']);
    return ['topo' => array_values($out['topo']), 'rodape' => array_values($out['rodape'])];
}

/** Caminho seguro de uma imagem de topo/rodapé ('' se não existir). */
function imagem_email(string $nome): string
{
    $nome = basename($nome);
    $p = pasta_imagens_email() . '/' . $nome;
    return ($nome !== '' && is_file($p)) ? $p : '';
}

/**
 * HTML final do e-mail. $paraEnvio = true usa cid: (imagens embutidas);
 * false usa a URL (prévia na tela).
 */
function email_html(string $corpo, string $topo, string $rodape, bool $paraEnvio): string
{
    $src = fn(string $img, string $cid) => $paraEnvio ? 'cid:' . $cid : BASE_URL . '/assets/email/' . rawurlencode(basename($img));
    $topoRow   = imagem_email($topo)   ? '<tr><td><img src="' . $src($topo, 'topo_img') . '" width="620" alt="" style="display:block;width:100%;max-width:620px;"></td></tr>' : '';
    $rodapeRow = imagem_email($rodape) ? '<tr><td><img src="' . $src($rodape, 'rodape_img') . '" width="620" alt="" style="display:block;width:100%;max-width:620px;"></td></tr>' : '';

    return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
         . '<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif;">'
         . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:20px 0;"><tr><td align="center">'
         . '<table width="620" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:6px;overflow:hidden;">'
         . $topoRow
         . '<tr><td style="padding:40px;color:#333333;font-size:16px;line-height:1.7;">' . $corpo . '</td></tr>'
         . $rodapeRow
         . '</table>'
         . '<p style="color:#ababab;font-size:11px;margin-top:20px;">Ânima Educação — Segurança da Informação</p>'
         . '</td></tr></table></body></html>';
}

function graph_token(string $remetente): string
{
    static $cache = [];
    $conta = remetentes()[$remetente] ?? null;
    if (!$conta) throw new RuntimeException("Remetente {$remetente} não está em CENSO_REMETENTES.");
    if (isset($cache[$remetente])) return $cache[$remetente];

    $tenant = censo_cfg('CENSO_GRAPH_TENANT_ID');
    $ch = curl_init("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type'    => 'client_credentials',
            'client_id'     => censo_cfg('CENSO_GRAPH_CLIENT_ID'),
            'client_secret' => $conta['secret'] ?? '',
            'scope'         => 'https://graph.microsoft.com/.default',
        ]),
    ]);
    $resp = json_decode((string) curl_exec($ch), true);
    if (empty($resp['access_token'])) {
        throw new RuntimeException('Microsoft Graph recusou a autenticação: ' . ($resp['error_description'] ?? $resp['error'] ?? 'sem resposta'));
    }
    return $cache[$remetente] = $resp['access_token'];
}

/**
 * Envia um e-mail. $cc: lista de endereços. Devolve null quando foi aceito,
 * ou a mensagem de erro.
 */
function email_enviar(string $remetente, string $para, string $paraNome, array $cc, string $assunto,
                      string $corpo, string $topo, string $rodape): ?string
{
    try {
        $token = graph_token($remetente);
    } catch (Throwable $e) {
        return $e->getMessage();
    }
    $conta = remetentes()[$remetente];

    $anexos = [];
    foreach (['topo_img' => $topo, 'rodape_img' => $rodape] as $cid => $img) {
        $p = imagem_email($img);
        if ($p === '') continue;
        $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
        $anexos[] = [
            '@odata.type'  => '#microsoft.graph.fileAttachment',
            'name'         => basename($p),
            'contentType'  => $ext === 'png' ? 'image/png' : ($ext === 'gif' ? 'image/gif' : 'image/jpeg'),
            'contentBytes' => base64_encode((string) file_get_contents($p)),
            'contentId'    => $cid,
            'isInline'     => true,
        ];
    }

    $cc = array_values(array_unique(array_filter(array_map(fn($e) => strtolower(trim($e)), $cc),
        fn($e) => $e !== '' && $e !== strtolower($para) && filter_var($e, FILTER_VALIDATE_EMAIL))));

    $payload = [
        'message' => [
            'subject'      => $assunto,
            'body'         => ['contentType' => 'HTML', 'content' => email_html($corpo, $topo, $rodape, true)],
            'toRecipients' => [['emailAddress' => ['address' => $para, 'name' => $paraNome]]],
            'ccRecipients' => array_map(fn($e) => ['emailAddress' => ['address' => $e]], $cc),
            'from'         => ['emailAddress' => ['address' => $remetente, 'name' => $conta['nome'] ?? $remetente]],
            'attachments'  => $anexos,
        ],
        'saveToSentItems' => true,
    ];

    $ch = curl_init('https://graph.microsoft.com/v1.0/users/' . rawurlencode($remetente) . '/sendMail');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($payload),
    ]);
    $resp = (string) curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    if ($err) return 'Falha de rede ao falar com o Microsoft Graph: ' . $err;
    if ($code !== 202) return 'Microsoft Graph (HTTP ' . $code . '): ' . (json_decode($resp, true)['error']['message'] ?? mb_substr($resp, 0, 300));
    return null;
}
