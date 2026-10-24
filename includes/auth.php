<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

// ─── Sessão ──────────────────────────────────────────────────────────────────

function session_start_safe(): void
{
    if (session_status() !== PHP_SESSION_NONE) return;
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

/** Usuário logado (relido do banco a cada requisição) ou null. */
function current_user(): ?array
{
    static $cache = false;
    if ($cache !== false) return $cache;
    session_start_safe();

    $id = (int) ($_SESSION['user_id'] ?? 0);
    if ($id <= 0) return $cache = null;

    // Inatividade encerra a sessão
    if (time() - (int) ($_SESSION['visto'] ?? 0) > SESSION_EXPIRA) {
        unset($_SESSION['user_id'], $_SESSION['visto']);
        return $cache = null;
    }

    // Desativar ou remover alguém vale na hora, não só no próximo login
    $st = db()->prepare('SELECT id, name, email, role, active FROM users WHERE id = ?');
    $st->execute([$id]);
    $u = $st->fetch();
    if (!$u || (int) $u['active'] !== 1) {
        unset($_SESSION['user_id'], $_SESSION['visto']);
        return $cache = null;
    }

    $_SESSION['visto'] = time();
    return $cache = $u;
}

function is_logged_in(): bool { return current_user() !== null; }
function is_admin(): bool     { return (current_user()['role'] ?? '') === 'admin'; }
function current_user_name(): string { return current_user()['name'] ?? 'Usuário'; }

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }
}

// ─── Login ───────────────────────────────────────────────────────────────────

function users_count(): int
{
    return (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
}

function ip_cliente(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function login_bloqueado(): bool
{
    $st = db()->prepare("SELECT COUNT(*) FROM login_falhas
        WHERE ip = ? AND criado_em >= datetime('now','localtime','-" . LOGIN_JANELA_MIN . " minutes')");
    $st->execute([ip_cliente()]);
    return (int) $st->fetchColumn() >= LOGIN_MAX_FALHAS;
}

/** true quando entrou; senão, a mensagem de erro. */
function login(string $email, string $password): bool|string
{
    session_start_safe();
    if (login_bloqueado()) return 'Muitas tentativas. Aguarde alguns minutos e tente novamente.';

    $st = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $st->execute([$email]);
    $user = $st->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        db()->prepare('INSERT INTO login_falhas (ip) VALUES (?)')->execute([ip_cliente()]);
        db()->exec("DELETE FROM login_falhas WHERE criado_em < datetime('now','localtime','-1 day')");
        sleep(1);   // tentativa em massa fica cara sem travar ninguém
        return 'E-mail ou senha incorretos.';
    }
    if ((int) $user['active'] !== 1) return 'Este usuário está inativo. Fale com o administrador.';

    db()->prepare('DELETE FROM login_falhas WHERE ip = ?')->execute([ip_cliente()]);
    abrir_sessao((int) $user['id']);
    return true;
}

function abrir_sessao(int $id): void
{
    session_start_safe();
    session_regenerate_id(true);   // sessão nova a cada login: evita fixação
    $_SESSION['user_id'] = $id;
    $_SESSION['visto']   = time();
    db()->prepare("UPDATE users SET last_login = datetime('now','localtime') WHERE id = ?")->execute([$id]);
}

function logout(): void
{
    session_start_safe();
    $_SESSION = [];
    session_destroy();
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// ─── CSRF ────────────────────────────────────────────────────────────────────

function csrf_token(): string
{
    session_start_safe();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_ok(): bool
{
    session_start_safe();
    $t = $_POST['csrf'] ?? null;
    return !empty($_SESSION['csrf']) && is_string($t) && hash_equals($_SESSION['csrf'], $t);
}

// ─── Utilidades ──────────────────────────────────────────────────────────────

function e(?string $s): string
{
    // ENT_SUBSTITUTE: texto com byte inválido aparece com "�", em vez de sumir inteiro
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Texto vindo de formulário/planilha sempre em UTF-8 (converte de Windows-1252 se preciso). */
function utf8(string $s): string
{
    return mb_check_encoding($s, 'UTF-8') ? $s : mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
}

/** Mensagem que sobrevive a um redirect (mostrada pelo header). */
function flash(string $tipo, string $texto): void
{
    session_start_safe();
    $_SESSION['flash'] = ['tipo' => $tipo, 'texto' => $texto];
}

function flash_get(): ?array
{
    session_start_safe();
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

/** Ainda sobra outro administrador ativo além de $id? (anti-lockout) */
function outro_admin_ativo(int $id): bool
{
    $st = db()->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1 AND id <> ?");
    $st->execute([$id]);
    return (int) $st->fetchColumn() > 0;
}
