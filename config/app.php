<?php
/**
 * Censo — acompanhamento da revisão de acessos e cobrança dos responsáveis.
 * Configuração central. Incluído por todas as páginas (via includes/auth.php).
 */

require_once __DIR__ . '/environment.php';

date_default_timezone_set(censo_env('CENSO_TIMEZONE', 'America/Sao_Paulo'));

// ─── Banco de dados SQLite ───────────────────────────────────────────────────
// Fica em dados/ (com .htaccess negando tudo), nunca na raiz servida.
define('DB_PATH', dirname(__DIR__) . '/dados/censo.sqlite');

// ─── URL base do sistema (sem barra final), detectada pela requisição ────────
$_scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', rtrim(censo_env('CENSO_BASE_URL', $_scheme . '://' . $_host . '/horacius-tools/funcoes/Censo'), '/'));
unset($_scheme, $_host);

// ─── Sessão (cookie próprio: não se mistura com o Tools nem com os outros) ──
define('SESSION_NAME', censo_env('CENSO_SESSION_NAME', 'censo_sess'));
define('SESSION_EXPIRA', 8 * 3600);   // inatividade que encerra a sessão (8h)

// ─── Login: limite de tentativas por IP ──────────────────────────────────────
define('LOGIN_MAX_FALHAS', 8);        // falhas permitidas…
define('LOGIN_JANELA_MIN', 15);       // …nesta janela, em minutos

// ─── Senha ───────────────────────────────────────────────────────────────────
define('SENHA_MIN', 8);

// ─── Papéis ──────────────────────────────────────────────────────────────────
const PAPEIS = [
    'admin' => ['rotulo' => 'Administrador', 'desc' => 'Acesso total + gestão de usuários'],
    'user'  => ['rotulo' => 'Usuário',       'desc' => 'Acompanha revisões e registra cobranças'],
];
