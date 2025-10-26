<?php
// Shared session and auth helpers

require_once __DIR__ . '/security.php';

if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $params = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $params['path'] ?? '/',
        'domain' => $params['domain'] ?? '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_name('dentweb_admin');
    session_start();
}

if (!isset($_SESSION['session_fingerprint'])) {
    $_SESSION['session_fingerprint'] = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . ($_SERVER['REMOTE_ADDR'] ?? ''));
    $_SESSION['session_last_active'] = time();
} else {
    $fingerprint = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . ($_SERVER['REMOTE_ADDR'] ?? ''));
    if (!hash_equals($_SESSION['session_fingerprint'], $fingerprint ?? '')) {
        $_SESSION = [];
        session_destroy();
        session_start();
    }
}

if (!empty($_SESSION['session_last_active']) && (time() - (int) $_SESSION['session_last_active'] > 1800)) {
    $_SESSION = [];
    session_destroy();
    session_start();
}

$_SESSION['session_last_active'] = time();

function portal_session_regenerate() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

function portal_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function portal_verify_csrf($token) {
    $valid = isset($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
    if (!$valid) {
        throw new RuntimeException('Invalid request token.');
    }
}

function portal_record_login_attempt($success) {
    $now = time();
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    $_SESSION['login_attempts'] = array_filter(
        $_SESSION['login_attempts'],
        function ($attempt) use ($now) {
            return ($now - $attempt['time']) < 900;
        }
    );
    if (!$success) {
        $_SESSION['login_attempts'][] = ['time' => $now];
    } else {
        $_SESSION['login_attempts'] = [];
    }
}

function portal_login_attempts_remaining() {
    $limit = 5;
    $attempts = $_SESSION['login_attempts'] ?? [];
    $count = count($attempts);
    return max(0, $limit - $count);
}

function portal_login_throttle_enabled() {
    return portal_login_attempts_remaining() <= 0;
}

/** Returns current user array or null */
function portal_current_user() {
    return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
}

/** True if someone is logged in */
function portal_is_logged_in() {
    return portal_current_user() !== null;
}

function portal_verify_admin_password($password) {
    $password = (string) $password;
    if ($password === '') {
        return false;
    }
    if (password_verify($password, PORTAL_ADMIN_PASSWORD_HASH)) {
        return true;
    }
    if (hash_equals(PORTAL_ADMIN_PASSWORD, $password)) {
        return true;
    }
    return false;
}

/** Log in user with email, role, name */
function portal_login_user($email, $role, $name) {
    portal_session_regenerate();
    $_SESSION['user'] = [
        'email' => $email,
        'role' => $role,
        'name' => $name,
        'login_at' => time(),
        'session_id' => session_id(),
    ];
}

/** Log out current user */
function portal_logout() {
    $fingerprint = $_SESSION['session_fingerprint'] ?? null;
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'] ?? '/', $params['domain'] ?? '', $params['secure'] ?? false, true);
    }
    session_destroy();
    session_start();
    if ($fingerprint) {
        $_SESSION['session_fingerprint'] = $fingerprint;
    }
}

/** Require any authenticated user */
function portal_require_login() {
    if (!portal_is_logged_in()) {
        portal_redirect('login.php');
    }
}

function portal_require_session() {
    $user = portal_current_user();
    if (!$user) {
        portal_require_login();
    }
    if (($user['session_id'] ?? null) !== session_id()) {
        portal_logout();
        portal_redirect('login.php');
    }
}

/** Require role from list */
function portal_require_role($roles) {
    portal_require_login();
    $user = portal_current_user();
    $allowed = is_array($roles) ? $roles : [$roles];
    if (!$user || !in_array($user['role'] ?? '', $allowed, true)) {
        portal_redirect('login.php');
    }
}
