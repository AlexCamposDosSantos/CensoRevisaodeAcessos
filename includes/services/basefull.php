<?php
/**
 * BaseFull do dia, direto do SharePoint. O RH exporta um CSV por dia na pasta
 * General/BaseFull do site AuditoriaGestodeAcessos
 * (BaseFull-DDMMAAAA_AAAAMMDD_HHMMSS.csv, ~40 MB). Ao atualizar o andamento, o
 * Censo pega o mais recente e importa como base de funcionários, se ainda não
 * tiver importado esse mesmo arquivo.
 *
 * Credenciais: as do .env do Prumo (SHAREPOINT_*, a aplicação que já lê os
 * exports do SharePoint), na pasta vizinha funcoes/divergencia-ia. Se o Censo
 * tiver as próprias (CENSO_SHAREPOINT_*), elas valem. Sem nenhuma, nada é
 * baixado e o upload manual em "Base de funcionários" continua valendo.
 */
require_once __DIR__ . '/fontes.php';
require_once __DIR__ . '/funcionarios.php';

/** Quantos arquivos baixados automaticamente ficam guardados (cada um tem ~40 MB). */
const BASEFULL_GUARDAR = 5;

/** Caminho do .env do Prumo (CENSO_PRUMO_ENV muda, se o Prumo estiver em outro lugar). */
function basefull_prumo_env(): string
{
    return censo_cfg('CENSO_PRUMO_ENV', dirname(__DIR__, 3) . '/divergencia-ia/.env');
}

/** Só as chaves SHAREPOINT_* do .env do Prumo — o resto dele não interessa ao Censo. */
function basefull_prumo(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [];
    $f = basefull_prumo_env();
    if (!is_file($f) || !is_readable($f)) return $cache;
    foreach (file($f, FILE_IGNORE_NEW_LINES) ?: [] as $linha) {
        if (preg_match('/^\s*(SHAREPOINT_[A-Z_]+)\s*=\s*(.*?)\s*$/', $linha, $m)) {
            $v = $m[2];
            if (preg_match('/^"(.*)"$|^\'(.*)\'$/', $v, $q)) $v = $q[1] !== '' ? $q[1] : ($q[2] ?? '');
            else $v = trim(preg_replace('/\s+#.*$/', '', $v));
            $cache[$m[1]] = $v;
        }
    }
    return $cache;
}

function basefull_cfg(): array
{
    $p = basefull_prumo();
    $proprio = censo_cfg('CENSO_SHAREPOINT_CLIENT_ID') !== '';
    return [
        'tenant' => censo_cfg('CENSO_SHAREPOINT_TENANT_ID') ?: ($p['SHAREPOINT_TENANT_ID'] ?? '') ?: censo_cfg('CENSO_GRAPH_TENANT_ID'),
        'client' => $proprio ? censo_cfg('CENSO_SHAREPOINT_CLIENT_ID') : ($p['SHAREPOINT_CLIENT_ID'] ?? ''),
        'secret' => $proprio ? censo_cfg('CENSO_SHAREPOINT_CLIENT_SECRET') : ($p['SHAREPOINT_CLIENT_SECRET'] ?? ''),
        'origem' => $proprio ? '.env do Censo' : '.env do Prumo',
        'site'   => censo_cfg('CENSO_BASEFULL_SITE', 'animaeducacao.sharepoint.com:/sites/AuditoriaGestodeAcessos'),
        'pasta'  => trim(censo_cfg('CENSO_BASEFULL_PASTA', 'General/BaseFull'), '/'),
    ];
}

function basefull_configurado(): bool
{
    $c = basefull_cfg();
    return $c['tenant'] !== '' && $c['client'] !== '' && $c['secret'] !== '';
}

/** GET no Graph; devolve o JSON. */
function basefull_get(string $token, string $url): array
{
    $ch = curl_init(str_starts_with($url, 'https://') ? $url : 'https://graph.microsoft.com/v1.0' . $url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60,
                            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token]]);
    $resp = json_decode((string) curl_exec($ch), true);
    if (!is_array($resp)) throw new RuntimeException('SharePoint não respondeu: ' . (curl_error($ch) ?: 'resposta vazia'));
    if (isset($resp['error'])) throw new RuntimeException('SharePoint: ' . ($resp['error']['message'] ?? $resp['error']['code'] ?? 'erro'));
    return $resp;
}

function basefull_token(): string
{
    $c = basefull_cfg();
    $ch = curl_init('https://login.microsoftonline.com/' . rawurlencode($c['tenant']) . '/oauth2/v2.0/token');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 30,
        CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'client_credentials', 'client_id' => $c['client'],
            'client_secret' => $c['secret'], 'scope' => 'https://graph.microsoft.com/.default'])]);
    $r = json_decode((string) curl_exec($ch), true);
    if (empty($r['access_token'])) throw new RuntimeException('SharePoint recusou a autenticação: ' . ($r['error_description'] ?? $r['error'] ?? 'sem resposta'));
    return $r['access_token'];
}

/** Data/hora do export pelo nome (…_AAAAMMDD_HHMMSS.csv); senão, a da modificação. */
function basefull_quando(array $item): string
{
    if (preg_match('/_(\d{8})_(\d{6})\.csv$/i', $item['name'], $m)) {
        return DateTime::createFromFormat('YmdHis', $m[1] . $m[2])->format('Y-m-d H:i:s');
    }
    return date('Y-m-d H:i:s', strtotime((string) ($item['lastModifiedDateTime'] ?? 'now')));
}

/** O export mais recente da pasta: ['id','name','size','quando','url']. */
function basefull_mais_recente(string $token): array
{
    $c = basefull_cfg();
    $site = basefull_get($token, '/sites/' . $c['site'])['id'];
    $drive = basefull_get($token, '/sites/' . $site . '/drive')['id'];
    $caminho = implode('/', array_map('rawurlencode', explode('/', $c['pasta'])));
    $url = '/drives/' . $drive . '/root:/' . $caminho . ':/children?$top=999&$select=id,name,size,lastModifiedDateTime,file';
    $melhor = null;
    while ($url) {
        $pg = basefull_get($token, $url);
        foreach ($pg['value'] ?? [] as $i) {
            if (!isset($i['file']) || !preg_match('/^BaseFull.*\.csv$/i', $i['name'])) continue;
            $i['quando'] = basefull_quando($i);
            if (!$melhor || $i['quando'] > $melhor['quando']) $melhor = $i;
        }
        $url = $pg['@odata.nextLink'] ?? null;
    }
    if (!$melhor) throw new RuntimeException('Nenhum arquivo BaseFull*.csv na pasta ' . $c['pasta'] . ' do SharePoint.');
    $melhor['drive'] = $drive;
    return $melhor;
}

/** Baixa um item para um arquivo temporário (a URL de download já vem autorizada). */
function basefull_baixar(string $token, array $item): string
{
    $url = basefull_get($token, '/drives/' . $item['drive'] . '/items/' . $item['id'])['@microsoft.graph.downloadUrl'] ?? '';
    if ($url === '') throw new RuntimeException('O SharePoint não liberou o download de ' . $item['name'] . '.');
    $tmp = tempnam(sys_get_temp_dir(), 'bf');
    $fh = fopen($tmp, 'wb');
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_FILE => $fh, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 600]);
    $ok = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    fclose($fh);
    if (!$ok || $code !== 200 || filesize($tmp) !== (int) $item['size']) {
        @unlink($tmp);
        throw new RuntimeException('Download de ' . $item['name'] . ' incompleto' . ($err ? ': ' . $err : " (HTTP {$code})") . '.');
    }
    return $tmp;
}

/**
 * Traz a BaseFull mais recente, se for nova. Nunca lança: devolve
 * ['status' => 'importada'|'atual'|'desligada'|'erro', 'texto' => …].
 */
function basefull_sincronizar(): array
{
    if (!basefull_configurado()) {
        return ['status' => 'desligada', 'texto' => 'Download automático da BaseFull desligado: não achei as credenciais SHAREPOINT_* no .env do Prumo (' . basefull_prumo_env() . ').'];
    }
    $tmp = null;
    try {
        $token = basefull_token();
        $item = basefull_mais_recente($token);
        $dia = date('d/m/Y', strtotime($item['quando']));
        $velha = substr($item['quando'], 0, 10) < date('Y-m-d') ? " A de hoje ainda não chegou no SharePoint; a mais recente é de {$dia}." : '';

        $atual = funcionarios_carga_atual();
        if ($atual && $atual['nome_original'] === $item['name']) {
            return ['status' => 'atual', 'texto' => "BaseFull de {$dia} já estava importada." . $velha];
        }
        set_time_limit(600);
        $tmp = basefull_baixar($token, $item);
        $r = funcionarios_importar($tmp, $item['name'], null);
        basefull_limpar();
        return ['status' => 'importada', 'texto' => "BaseFull de {$dia} baixada do SharePoint: "
            . number_format($r['linhas'], 0, ',', '.') . ' funcionários (' . number_format($r['ativos'], 0, ',', '.') . ' ativos).' . $velha];
    } catch (Throwable $e) {
        return ['status' => 'erro', 'texto' => 'Não foi possível baixar a BaseFull do SharePoint: ' . $e->getMessage() . '. Seguiu com a base já importada.'];
    } finally {
        if ($tmp) @unlink($tmp);
    }
}

/** Apaga os arquivos das cargas automáticas antigas (o registro da carga fica). */
function basefull_limpar(): void
{
    $velhas = db()->query('SELECT id, arquivo FROM funcionarios_cargas WHERE enviado_por IS NULL ORDER BY id DESC LIMIT -1 OFFSET ' . BASEFULL_GUARDAR)->fetchAll();
    foreach ($velhas as $c) @unlink(pasta_funcionarios() . '/' . basename($c['arquivo']));
}
