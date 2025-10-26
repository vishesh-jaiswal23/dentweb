<?php
// Shared session and auth helpers

if (session_status() === PHP_SESSION_NONE) {
    // Secure cookie flags where supported
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $params = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $params['path'] ?? '/',
        'domain' => $params['domain'] ?? '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** Returns current user array or null */
function portal_current_user() {
    return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
}

/** True if someone is logged in */
function portal_is_logged_in() {
    return portal_current_user() !== null;
}

/** Log in user with email, role, name */
function portal_login_user($email, $role, $name) {
    $_SESSION['user'] = [
        'email' => $email,
        'role' => $role,
        'name' => $name,
        'login_at' => time(),
    ];
}

/** Log out current user */
function portal_logout() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'] ?? '/', $params['domain'] ?? '', $params['secure'] ?? false, true);
    }
    session_destroy();
}

/** Require any authenticated user */
function portal_require_login() {
    if (!portal_is_logged_in()) {
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
