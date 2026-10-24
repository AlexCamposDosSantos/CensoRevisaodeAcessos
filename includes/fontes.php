<?php
/**
 * Fonte externa do Censo: o banco do Horacius (MySQL, só leitura). É a única —
 * os arquivos de cada revisão são enviados por upload e guardados no próprio
 * Censo (ver includes/planilha.php e includes/revisao.php).
 *
 * Credenciais — nunca no código:
 *   1. config.local.php do Censo, se existir (CENSO_DB_HOST/PORT/NAME/USER/PASS);
 *   2. senão, o .env do Prumo (../divergencia-ia/.env): DB_PROD_*. No servidor
 *      ele já tem a conexão de produção funcionando — o Censo não duplica segredo.
 */
require_once __DIR__ . '/../config.php';
if (is_file(__DIR__ . '/../config.local.php')) require_once __DIR__ . '/../config.local.php';

/** Valor de configuração: constante do config.local.php, senão chave do .env do Prumo. */
function censo_cfg(string $const, string $chaveEnv, string $padrao = ''): string
{
    if (defined($const)) return (string) constant($const);

    static $env = null;
    if ($env === null) {
        $env = [];
        $arq = __DIR__ . '/../../divergencia-ia/.env';
        if (is_file($arq)) {
            foreach (file($arq, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
                $l = trim($l);
                if ($l === '' || $l[0] === '#' || !str_contains($l, '=')) continue;
                [$k, $v] = explode('=', $l, 2);
                $env[trim($k)] = trim(trim($v), "\"'");
            }
        }
    }
    return $env[$chaveEnv] ?? $padrao;
}

function horacius_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $host = censo_cfg('CENSO_DB_HOST', 'DB_PROD_HOST');
    $port = censo_cfg('CENSO_DB_PORT', 'DB_PROD_PORT', '3306');
    $nome = censo_cfg('CENSO_DB_NAME', 'DB_NAME', 'horacius');
    $user = censo_cfg('CENSO_DB_USER', 'DB_PROD_USER');
    $pass = censo_cfg('CENSO_DB_PASS', 'DB_PROD_PASSWORD');
    if ($host === '' || $user === '') {
        throw new RuntimeException('Conexão com o Horacius não configurada (config.local.php do Censo ou .env do Prumo).');
    }

    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$nome};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 15,
    ]);
    return $pdo;
}

/** Rótulo do banco em uso, para a tela dizer de onde veio o número. */
function horacius_rotulo(): string
{
    if (defined('CENSO_DB_ROTULO')) return (string) CENSO_DB_ROTULO;
    return defined('CENSO_DB_HOST') ? 'config.local' : 'produção';
}
