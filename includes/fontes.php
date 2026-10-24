<?php
/**
 * Fontes externas do Censo: o banco do Horacius (andamento) e o Microsoft Graph
 * (envio de e-mail). As credenciais vêm SÓ do config.local.php do Censo — ele
 * não lê configuração de nenhum outro sistema. Modelo: config.local.example.php.
 */
require_once __DIR__ . '/../config.php';
if (is_file(__DIR__ . '/../config.local.php')) require_once __DIR__ . '/../config.local.php';

function censo_cfg(string $const, string $padrao = ''): string
{
    return defined($const) ? (string) constant($const) : $padrao;
}

/** O que falta configurar (para a tela de Configurações e mensagens de erro). */
function config_pendencias(): array
{
    $p = [];
    if (!is_file(__DIR__ . '/../config.local.php')) $p[] = 'O arquivo config.local.php não existe (copie de config.local.example.php).';
    foreach (['CENSO_DB_HOST' => 'banco do Horacius', 'CENSO_DB_USER' => 'usuário do banco'] as $c => $o) {
        if (censo_cfg($c) === '') $p[] = "Falta {$c} ({$o}).";
    }
    foreach (['CENSO_GRAPH_TENANT_ID' => 'tenant do Microsoft Graph', 'CENSO_GRAPH_CLIENT_ID' => 'aplicação do Microsoft Graph'] as $c => $o) {
        if (censo_cfg($c) === '') $p[] = "Falta {$c} ({$o}).";
    }
    if (!remetentes()) $p[] = 'Nenhum remetente em CENSO_REMETENTES.';
    return $p;
}

// ─── Banco do Horacius ───────────────────────────────────────────────────────

function horacius_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $host = censo_cfg('CENSO_DB_HOST');
    $user = censo_cfg('CENSO_DB_USER');
    if ($host === '' || $user === '') {
        throw new RuntimeException('Conexão com o Horacius não configurada — preencha CENSO_DB_* no config.local.php do Censo.');
    }
    $pdo = new PDO(
        'mysql:host=' . $host . ';port=' . censo_cfg('CENSO_DB_PORT', '3306') . ';dbname=' . censo_cfg('CENSO_DB_NAME', 'horacius') . ';charset=utf8mb4',
        $user, censo_cfg('CENSO_DB_PASS'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_TIMEOUT => 15]
    );
    return $pdo;
}

/** Rótulo do banco em uso, para a tela dizer de onde veio o número. */
function horacius_rotulo(): string
{
    return censo_cfg('CENSO_DB_ROTULO', 'Horacius');
}

// ─── Microsoft Graph: remetentes ─────────────────────────────────────────────

/** e-mail => ['nome' => ..., 'secret' => ...] */
function remetentes(): array
{
    return defined('CENSO_REMETENTES') && is_array(CENSO_REMETENTES) ? CENSO_REMETENTES : [];
}

function link_revisao(): string
{
    return censo_cfg('CENSO_LINK_REVISAO', 'https://horacius.ecossistemaanima.com.br/portal/?menu=access-review');
}
