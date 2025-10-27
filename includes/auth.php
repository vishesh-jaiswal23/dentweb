<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function start_session(): void
{
    $secure = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Strict',
            'cookie_secure' => $secure,
        ]);
    }

    $now = time();
    $timeoutMinutes = 45;
    try {
        $timeoutMinutes = get_session_timeout_minutes(get_db());
    } catch (Throwable $exception) {
        $fallback = (int) ($_SESSION['session_policy_timeout'] ?? 45);
        if ($fallback >= 15 && $fallback <= 720) {
            $timeoutMinutes = $fallback;
        }
    }

    $timeoutSeconds = $timeoutMinutes * 60;
    $lastActivity = isset($_SESSION['session_last_activity']) ? (int) $_SESSION['session_last_activity'] : $now;

    if (!empty($_SESSION['user']) && $timeoutSeconds > 0 && ($now - $lastActivity) >= $timeoutSeconds) {
        $_SESSION = [];
        session_unset();
        session_destroy();
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Strict',
            'cookie_secure' => $secure,
        ]);
        $lastActivity = $now;
    }

    $_SESSION['session_policy_timeout'] = $timeoutMinutes;
    $_SESSION['session_last_activity'] = $now;

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

function is_database_accessible(): bool
{
    try {
        get_db();
        return true;
    } catch (Throwable $exception) {
        return false;
    }
}

function enforce_offline_session_validity(): void
{
    start_session();

    $user = $_SESSION['user'] ?? null;
    if (!is_array($user) || empty($user['offline_mode'])) {
        return;
    }

    if (!is_database_accessible()) {
        return;
    }

    unset($_SESSION['user']);
    $_SESSION['offline_session_invalidated'] = true;
    session_regenerate_id(true);

    header('Location: login.php');
    exit;
}

function require_login(): void
{
    start_session();
    enforce_offline_session_validity();
    if (empty($_SESSION['user'])) {
        header('Location: login.php');
        exit;
    }
}

function require_role(string $role): void
{
    require_login();
    $user = $_SESSION['user'] ?? null;
    if (!$user || ($user['role_name'] ?? null) !== $role) {
        http_response_code(403);
        echo 'Access denied.';
        exit;
    }
}

function require_admin(): void
{
    start_session();
    enforce_offline_session_validity();

    $user = $_SESSION['user'] ?? null;
    if (!$user) {
        header('Location: login.php');
        exit;
    }

    if (($user['role_name'] ?? '') === 'admin') {
        return;
    }

    $redirect = 'login.php';
    if (($user['role_name'] ?? '') === 'employee') {
        $redirect = 'employee-dashboard.php';
    }

    set_flash('warning', 'Administrator permissions are required to access that workspace.');
    header('Location: ' . $redirect);
    exit;
}

function current_user(): ?array
{
    start_session();
    return $_SESSION['user'] ?? null;
}

function set_flash(string $type, string $message): void
{
    start_session();

    $allowed = ['success', 'info', 'warning', 'error'];
    if (!in_array($type, $allowed, true)) {
        $type = 'info';
    }

    $_SESSION['flash'] = [
        'type' => $type,
        'message' => trim($message),
    ];
}

function consume_flash(): ?array
{
    start_session();
    $flash = $_SESSION['flash'] ?? null;
    if (!is_array($flash)) {
        return null;
    }

    unset($_SESSION['flash']);

    $type = is_string($flash['type'] ?? null) ? $flash['type'] : 'info';
    $message = is_string($flash['message'] ?? null) ? $flash['message'] : '';

    return [
        'type' => $type,
        'message' => $message,
    ];
}

function client_ip_address(): string
{
    $candidates = [
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
        $_SERVER['HTTP_X_REAL_IP'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if (!is_string($candidate)) {
            continue;
        }

        if (str_contains($candidate, ',')) {
            $parts = explode(',', $candidate);
            $candidate = $parts[0] ?? $candidate;
        }

        $candidate = trim($candidate);
        if ($candidate !== '') {
            return $candidate;
        }
    }

    return '0.0.0.0';
}

function verify_csrf_token(?string $token): bool
{
    start_session();
    return is_string($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function ensure_api_access(string $requiredRole = 'admin'): void
{
    start_session();
    enforce_offline_session_validity();
    if (empty($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        exit;
    }

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        http_response_code(419);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }

    if (($requiredRole !== '') && (($_SESSION['user']['role_name'] ?? '') !== $requiredRole)) {
        http_response_code(403);
        echo json_encode(['error' => 'Insufficient permissions']);
        exit;
    }
}

function authenticate_user(string $identifier, string $password, string $roleName): ?array
{
    try {
        $db = get_db();
    } catch (Throwable $dbError) {
        return authenticate_user_fallback($identifier, $password, $roleName, $dbError);
    }

    $loginIdentifier = trim($identifier);
    try {
        $stmt = $db->prepare("SELECT users.*, roles.name AS role_name FROM users INNER JOIN roles ON users.role_id = roles.id WHERE roles.name = :role AND (LOWER(users.email) = LOWER(:identifier) OR LOWER(users.username) = LOWER(:identifier)) LIMIT 1");
        $stmt->execute([
            ':identifier' => $loginIdentifier,
            ':role' => $roleName,
        ]);

        $user = $stmt->fetch();
        if (!$user) {
            return null;
        }

        if ($user['status'] !== 'active') {
            return null;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }

        $update = $db->prepare("UPDATE users SET last_login_at = datetime('now'), updated_at = datetime('now') WHERE id = :id");
        $update->execute([':id' => $user['id']]);

        $log = $db->prepare('INSERT INTO audit_logs(actor_id, action, entity_type, entity_id, description) VALUES(:actor_id, :action, :entity_type, :entity_id, :description)');
        $log->execute([
            ':actor_id' => $user['id'],
            ':action' => 'login',
            ':entity_type' => 'user',
            ':entity_id' => $user['id'],
            ':description' => sprintf('User %s logged in to the %s portal', $user['email'], $roleName),
        ]);

        return $user;
    } catch (Throwable $queryError) {
        return authenticate_user_fallback($identifier, $password, $roleName, $queryError);
    }
}

function authenticate_user_fallback(string $identifier, string $password, string $roleName, Throwable $reason): ?array
{
    $account = resolve_offline_account($reason);
    if ($account === null) {
        return null;
    }

    $normalized = trim($identifier);
    $emailMatches = strcasecmp($account['email'], $normalized) === 0;
    $username = $account['username'] ?? null;
    $usernameMatches = is_string($username) && strcasecmp($username, $normalized) === 0;

    if (!$emailMatches && !$usernameMatches) {
        return null;
    }

    if ($roleName !== $account['role']) {
        return null;
    }

    if (!verify_offline_password($password, $account)) {
        return null;
    }

    return [
        'id' => $account['id'],
        'full_name' => $account['name'],
        'email' => $account['email'],
        'username' => $account['username'] ?? $account['email'],
        'role_name' => $account['role'],
        'status' => 'active',
        'permissions_note' => 'Offline administrator access granted while the database is unavailable.',
        'offline_mode' => true,
    ];
}

function resolve_offline_account(Throwable $reason): ?array
{
    static $account = null;
    if ($account !== null) {
        return $account;
    }

    if (is_offline_access_disabled()) {
        error_log('Offline authentication is disabled. Database error: ' . $reason->getMessage());
        $account = null;
        return $account;
    }

    static $hasLogged = false;
    if (!$hasLogged) {
        error_log('Database unavailable for authentication: ' . $reason->getMessage() . ' — enabling offline administrator access.');
        $hasLogged = true;
    }

    $default = [
        'id' => 0,
        'name' => 'Primary Administrator',
        'email' => 'admin@dakshayani.in',
        'username' => 'admin',
        'role' => 'admin',
        'password_hash' => '$2y$12$TvquhYdWBtKSPQ56kB4S1OTeNntaEv8QE9Woq2SPKkuuFVr4dMy/q',
        'password_plain' => null,
    ];

    $overrides = [
        'email' => collect_first_non_empty([
            $_ENV['FALLBACK_ADMIN_EMAIL'] ?? null,
            $_SERVER['FALLBACK_ADMIN_EMAIL'] ?? null,
            getenv('FALLBACK_ADMIN_EMAIL') ?: null,
        ]),
        'name' => collect_first_non_empty([
            $_ENV['FALLBACK_ADMIN_NAME'] ?? null,
            $_SERVER['FALLBACK_ADMIN_NAME'] ?? null,
            getenv('FALLBACK_ADMIN_NAME') ?: null,
        ]),
        'role' => collect_first_non_empty([
            $_ENV['FALLBACK_ADMIN_ROLE'] ?? null,
            $_SERVER['FALLBACK_ADMIN_ROLE'] ?? null,
            getenv('FALLBACK_ADMIN_ROLE') ?: null,
        ]),
        'username' => collect_first_non_empty([
            $_ENV['FALLBACK_ADMIN_USERNAME'] ?? null,
            $_SERVER['FALLBACK_ADMIN_USERNAME'] ?? null,
            getenv('FALLBACK_ADMIN_USERNAME') ?: null,
        ]),
        'password_hash' => collect_first_non_empty([
            $_ENV['FALLBACK_ADMIN_PASSWORD_HASH'] ?? null,
            $_SERVER['FALLBACK_ADMIN_PASSWORD_HASH'] ?? null,
            getenv('FALLBACK_ADMIN_PASSWORD_HASH') ?: null,
        ]),
        'password_plain' => collect_first_non_empty([
            $_ENV['FALLBACK_ADMIN_PASSWORD'] ?? null,
            $_SERVER['FALLBACK_ADMIN_PASSWORD'] ?? null,
            getenv('FALLBACK_ADMIN_PASSWORD') ?: null,
        ]),
        'id' => collect_first_non_empty([
            $_ENV['FALLBACK_ADMIN_ID'] ?? null,
            $_SERVER['FALLBACK_ADMIN_ID'] ?? null,
            getenv('FALLBACK_ADMIN_ID') ?: null,
        ]),
    ];

    if (is_string($overrides['email']) && filter_var($overrides['email'], FILTER_VALIDATE_EMAIL)) {
        $default['email'] = $overrides['email'];
    }

    if (is_string($overrides['name']) && trim($overrides['name']) !== '') {
        $default['name'] = trim($overrides['name']);
    }

    if (is_string($overrides['role']) && trim($overrides['role']) !== '') {
        $default['role'] = trim($overrides['role']);
    }

    if (is_string($overrides['username']) && trim($overrides['username']) !== '') {
        $default['username'] = trim($overrides['username']);
    }

    if (is_string($overrides['password_hash']) && trim($overrides['password_hash']) !== '') {
        $default['password_hash'] = trim($overrides['password_hash']);
    }

    if (is_string($overrides['password_plain']) && trim($overrides['password_plain']) !== '') {
        $default['password_plain'] = trim($overrides['password_plain']);
    }

    if (is_string($overrides['id']) && trim($overrides['id']) !== '' && is_numeric($overrides['id'])) {
        $default['id'] = (int) $overrides['id'];
    }

    $account = $default;

    return $account;
}

function collect_first_non_empty(array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (!is_string($candidate)) {
            continue;
        }

        $candidate = trim($candidate);
        if ($candidate !== '') {
            return $candidate;
        }
    }

    return null;
}

function verify_offline_password(string $password, array $account): bool
{
    if (isset($account['password_hash']) && is_string($account['password_hash']) && $account['password_hash'] !== '') {
        if (@password_verify($password, $account['password_hash'])) {
            return true;
        }
    }

    if (isset($account['password_plain']) && is_string($account['password_plain']) && $account['password_plain'] !== '') {
        return hash_equals($account['password_plain'], $password);
    }

    return false;
}

function is_offline_access_disabled(): bool
{
    $candidates = [
        $_ENV['FALLBACK_ADMIN_DISABLE'] ?? null,
        $_SERVER['FALLBACK_ADMIN_DISABLE'] ?? null,
        getenv('FALLBACK_ADMIN_DISABLE') ?: null,
    ];

    foreach ($candidates as $candidate) {
        if (!is_string($candidate)) {
            continue;
        }

        $normalized = strtolower(trim($candidate));
        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
    }

    return false;
}

function logout_user(): void
{
    start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'cookie_secure' => !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off',
    ]);
    session_regenerate_id(true);
    $_SESSION['session_policy_timeout'] = 45;
    $_SESSION['session_last_activity'] = time();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['flash'] = [
        'type' => 'success',
        'message' => 'You have been signed out securely.',
    ];
}
