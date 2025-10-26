<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Strict',
        ]);
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

function require_login(): void
{
    start_session();
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

function current_user(): ?array
{
    start_session();
    return $_SESSION['user'] ?? null;
}

function verify_csrf_token(?string $token): bool
{
    start_session();
    return is_string($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function ensure_api_access(string $requiredRole = 'admin'): void
{
    start_session();
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

function authenticate_user(string $email, string $password, string $roleName): ?array
{
    $db = get_db();
    $stmt = $db->prepare('SELECT users.*, roles.name AS role_name FROM users INNER JOIN roles ON users.role_id = roles.id WHERE LOWER(users.email) = LOWER(:email) AND roles.name = :role LIMIT 1');
    $stmt->execute([
        ':email' => $email,
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

    $update = $db->prepare('UPDATE users SET last_login_at = datetime("now"), updated_at = datetime("now") WHERE id = :id');
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
}
