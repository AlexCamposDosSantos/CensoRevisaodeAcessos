<?php
/**
 * Fontes externas do Censo: o banco do Horacius (andamento) e o Microsoft Graph
 * (envio de e-mail). As credenciais vêm do ambiente/.env do Censo, com
 * compatibilidade para config.local.php. Nenhuma configuração de outro sistema é lida.
 */
require_once __DIR__ . '/../../config/app.php';
if (is_file(__DIR__ . '/../../config.local.php')) require_once __DIR__ . '/../../config.local.php';

function censo_cfg(string $const, string $padrao = ''): string
{
    return censo_env($const) ?? (defined($const) ? (string) constant($const) : $padrao);
}

/** O que falta configurar (para a tela de Configurações e mensagens de erro). */
function config_pendencias(): array
{
    $p = [];
    if (!bancos()) $p[] = 'Nenhum banco do Horacius configurado. Preencha o .env conforme .env.example.';
    foreach (bancos() as $k => $b) {
        if (($b['host'] ?? '') === '' || ($b['user'] ?? '') === '') $p[] = "Banco \"{$k}\" sem host ou usuário. Confira os campos CENSO_DB_* no .env.";
    }
    foreach (['CENSO_GRAPH_TENANT_ID' => 'tenant do Microsoft Graph', 'CENSO_GRAPH_CLIENT_ID' => 'aplicação do Microsoft Graph'] as $c => $o) {
        if (censo_cfg($c) === '') $p[] = "Falta {$c} ({$o}).";
    }
    if (!remetentes()) $p[] = 'Nenhum remetente em CENSO_REMETENTES.';
    return $p;
}

// ─── Banco do Horacius: produção e homologação ───────────────────────────────
//
// .env (CENSO_DB_AMBIENTES) traz um ou mais ambientes; o formato legado é:
//   'producao' => ['rotulo' => 'Produção', 'host' => …, 'port' => …, 'name' => …, 'user' => …, 'pass' => …]
// O ambiente EM USO é escolhido na tela (switch do painel, administrador) e
// vale para todos — fica gravado no banco do Censo. Config antiga (CENSO_DB_*
// soltos) continua aceita, como um ambiente só.

/** chave => ['rotulo','host','port','name','user','pass'] */
function bancos(): array
{
    $ambiente = censo_env_bancos();
    if ($ambiente !== null) return $ambiente;
    if (defined('CENSO_BANCOS') && is_array(CENSO_BANCOS)) return CENSO_BANCOS;
    if (censo_cfg('CENSO_DB_HOST') === '') return [];
    $rot = censo_cfg('CENSO_DB_ROTULO', 'Produção');
    return [str_contains(mb_strtolower($rot), 'homolog') ? 'homologacao' : 'producao' => [
        'rotulo' => mb_convert_case($rot, MB_CASE_TITLE), 'host' => censo_cfg('CENSO_DB_HOST'), 'port' => censo_cfg('CENSO_DB_PORT', '3306'),
        'name' => censo_cfg('CENSO_DB_NAME', 'horacius'), 'user' => censo_cfg('CENSO_DB_USER'), 'pass' => censo_cfg('CENSO_DB_PASS'),
    ]];
}

/** Chave do ambiente em uso (o escolhido na tela, se ainda existir; senão o primeiro). */
function banco_atual(): string
{
    require_once __DIR__ . '/../db.php';
    $b = bancos();
    try {
        db()->exec('CREATE TABLE IF NOT EXISTS ajustes (chave TEXT PRIMARY KEY, valor TEXT)');
        $esc = db()->query("SELECT valor FROM ajustes WHERE chave = 'banco'")->fetchColumn();
    } catch (Throwable $e) {
        $esc = false;
    }
    return ($esc !== false && isset($b[$esc])) ? $esc : (string) (array_key_first($b) ?? '');
}

function banco_escolher(string $chave): void
{
    if (!isset(bancos()[$chave])) throw new RuntimeException('Ambiente de banco desconhecido.');
    require_once __DIR__ . '/../db.php';
    db()->exec('CREATE TABLE IF NOT EXISTS ajustes (chave TEXT PRIMARY KEY, valor TEXT)');
    db()->prepare("INSERT INTO ajustes (chave, valor) VALUES ('banco', ?) ON CONFLICT(chave) DO UPDATE SET valor = excluded.valor")->execute([$chave]);
}

/** Conexão com um ambiente (o em uso, se $chave vazio). */
function horacius_db(string $chave = ''): PDO
{
    static $pdos = [];
    $chave = $chave !== '' ? $chave : banco_atual();
    if (isset($pdos[$chave])) return $pdos[$chave];

    $b = bancos()[$chave] ?? null;
    if (!$b || ($b['host'] ?? '') === '' || ($b['user'] ?? '') === '') {
        throw new RuntimeException('Banco do Horacius não configurado — preencha os campos CENSO_DB_* no .env do Censo.');
    }
    return $pdos[$chave] = new PDO(
        'mysql:host=' . $b['host'] . ';port=' . ($b['port'] ?? '3306') . ';dbname=' . ($b['name'] ?? 'horacius') . ';charset=utf8mb4',
        $b['user'], $b['pass'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_TIMEOUT => 15]
    );
}

/** Rótulo de um ambiente (o em uso, se $chave vazio), para a tela dizer de onde veio o número. */
function horacius_rotulo(string $chave = ''): string
{
    $chave = $chave !== '' ? $chave : banco_atual();
    return bancos()[$chave]['rotulo'] ?? ($chave !== '' ? $chave : 'Horacius');
}

// ─── Microsoft Graph: remetentes ─────────────────────────────────────────────

/** e-mail => ['nome' => ..., 'secret' => ...] */
function remetentes(): array
{
    $ambiente = censo_env_remetentes();
    if ($ambiente !== null) return $ambiente;
    return defined('CENSO_REMETENTES') && is_array(CENSO_REMETENTES) ? CENSO_REMETENTES : [];
}

function link_revisao(): string
{
    return censo_cfg('CENSO_LINK_REVISAO', 'https://horacius.ecossistemaanima.com.br/portal/?menu=access-review');
}
