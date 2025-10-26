<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    ensure_api_access('admin');
} catch (Throwable $exception) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode(['error' => $exception->getMessage()]);
    exit;
}

$db = get_db();
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {
        case 'bootstrap':
            require_method('GET');
            respond_success(bootstrap_payload($db));
            break;
        case 'create-user':
            require_method('POST');
            respond_success(create_user($db, read_json()));
            break;
        case 'update-user-status':
            require_method('POST');
            respond_success(update_user_status($db, read_json()));
            break;
        case 'invite-user':
            require_method('POST');
            respond_success(create_invitation($db, read_json()));
            break;
        case 'approve-invite':
            require_method('POST');
            respond_success(approve_invitation($db, read_json()));
            break;
        case 'reject-invite':
            require_method('POST');
            respond_success(reject_invitation($db, read_json()));
            break;
        case 'update-gemini':
            require_method('POST');
            respond_success(update_gemini_settings($db, read_json()));
            break;
        case 'test-gemini':
            require_method('POST');
            respond_success(test_gemini_connection(read_json()));
            break;
        case 'change-password':
            require_method('POST');
            respond_success(change_password($db, read_json()));
            break;
        case 'update-login-policy':
            require_method('POST');
            respond_success(update_login_policy($db, read_json()));
            break;
        case 'fetch-audit':
            require_method('GET');
            respond_success(['audit' => recent_audit_logs($db)]);
            break;
        case 'fetch-complaints':
            require_method('GET');
            respond_success(['complaints' => list_complaints($db)]);
            break;
        case 'fetch-metrics':
            require_method('GET');
            respond_success(current_metrics($db));
            break;
        default:
            throw new RuntimeException('Unknown action: ' . $action);
    }
} catch (Throwable $exception) {
    http_response_code(400);
    respond_error($exception->getMessage());
}

function require_method(string $expected): void
{
    if (strcasecmp($_SERVER['REQUEST_METHOD'], $expected) !== 0) {
        throw new RuntimeException('Invalid request method.');
    }
}

function read_json(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('Invalid JSON payload.');
    }

    return $data;
}

function respond_success(array $data): void
{
    echo json_encode(['success' => true, 'data' => $data]);
}

function respond_error(string $message): void
{
    echo json_encode(['success' => false, 'error' => $message]);
}

function bootstrap_payload(PDO $db): array
{
    return [
        'users' => list_users($db),
        'invitations' => list_invitations($db),
        'complaints' => list_complaints($db),
        'audit' => recent_audit_logs($db),
        'metrics' => current_metrics($db),
        'loginPolicy' => fetch_login_policy($db),
        'gemini' => [
            'apiKey' => get_setting('gemini_api_key', $db) ?? '',
            'textModel' => get_setting('gemini_text_model', $db) ?? 'gemini-2.5-flash',
            'imageModel' => get_setting('gemini_image_model', $db) ?? 'gemini-2.5-flash-image',
            'ttsModel' => get_setting('gemini_tts_model', $db) ?? 'gemini-2.5-flash-preview-tts',
        ],
    ];
}

function list_users(PDO $db): array
{
    $stmt = $db->query('SELECT users.id, users.full_name, users.email, users.username, users.status, users.permissions_note, users.created_at, users.password_last_set_at, roles.name AS role_name FROM users INNER JOIN roles ON users.role_id = roles.id ORDER BY users.created_at DESC');
    return $stmt->fetchAll();
}

function list_invitations(PDO $db): array
{
    $stmt = $db->query('SELECT invitations.id, invitations.invitee_name, invitations.invitee_email, invitations.status, invitations.created_at, invitations.approved_at, invitations.message, roles.name AS role_name FROM invitations INNER JOIN roles ON invitations.role_id = roles.id ORDER BY invitations.created_at DESC');
    return $stmt->fetchAll();
}

function list_complaints(PDO $db): array
{
    $stmt = $db->query('SELECT complaints.*, users.full_name AS assigned_to_name FROM complaints LEFT JOIN users ON complaints.assigned_to = users.id ORDER BY complaints.created_at DESC');
    return $stmt->fetchAll();
}

function recent_audit_logs(PDO $db): array
{
    $stmt = $db->prepare('SELECT audit_logs.id, audit_logs.action, audit_logs.entity_type, audit_logs.entity_id, audit_logs.description, audit_logs.created_at, users.full_name AS actor_name FROM audit_logs LEFT JOIN users ON audit_logs.actor_id = users.id ORDER BY audit_logs.created_at DESC LIMIT 25');
    $stmt->execute();
    return $stmt->fetchAll();
}

function current_metrics(PDO $db): array
{
    $customerCount = (int) $db->query("SELECT COUNT(*) FROM users INNER JOIN roles ON users.role_id = roles.id WHERE roles.name = 'customer'")->fetchColumn();
    $pendingInvites = (int) $db->query("SELECT COUNT(*) FROM invitations WHERE status = 'pending'")->fetchColumn();
    $openComplaints = (int) $db->query("SELECT COUNT(*) FROM complaints WHERE status IN ('intake','triage','work')")->fetchColumn();
    $metrics = $db->query('SELECT name, value FROM system_metrics')->fetchAll(PDO::FETCH_KEY_PAIR);

    return [
        'counts' => [
            'customers' => $customerCount,
            'pendingInvitations' => $pendingInvites,
            'openComplaints' => $openComplaints,
            'subsidyPipeline' => $metrics['subsidy_pipeline'] ?? '0',
        ],
        'system' => [
            'last_backup' => $metrics['last_backup'] ?? 'Not recorded',
            'errors_24h' => $metrics['errors_24h'] ?? '0',
            'disk_usage' => $metrics['disk_usage'] ?? 'Normal',
            'uptime' => $metrics['uptime'] ?? 'Unknown',
        ],
    ];
}

function fetch_login_policy(PDO $db): array
{
    $stmt = $db->query('SELECT retry_limit, lockout_minutes, twofactor_mode, session_timeout, updated_at FROM login_policies WHERE id = 1');
    $row = $stmt->fetch();
    if (!$row) {
        ensure_login_policy_row($db);
        $row = $db->query('SELECT retry_limit, lockout_minutes, twofactor_mode, session_timeout, updated_at FROM login_policies WHERE id = 1')->fetch();
    }
    return $row ?: [];
}

function create_user(PDO $db, array $input): array
{
    $fullName = trim((string)($input['fullName'] ?? ''));
    $email = strtolower(trim((string)($input['email'] ?? '')));
    $username = trim((string)($input['username'] ?? ''));
    $password = (string)($input['password'] ?? '');
    $roleName = strtolower(trim((string)($input['role'] ?? '')));
    $permissions = trim((string)($input['permissions'] ?? ''));

    if ($fullName === '' || $email === '' || $username === '' || $password === '' || $roleName === '') {
        throw new RuntimeException('All required fields must be supplied.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Enter a valid email address.');
    }

    if (!preg_match('/^[a-z0-9._-]{3,}$/i', $username)) {
        throw new RuntimeException('Username must be at least 3 characters and contain only letters, numbers, dots, dashes, or underscores.');
    }

    if (strlen($password) < 8) {
        throw new RuntimeException('Password must contain at least 8 characters.');
    }

    $roleId = resolve_role_id($db, $roleName);
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

    $stmt = $db->prepare('INSERT INTO users(full_name, email, username, password_hash, role_id, status, permissions_note, password_last_set_at, created_at, updated_at) VALUES(:full_name, :email, :username, :password_hash, :role_id, "active", :permissions, :password_last_set_at, :created_at, :updated_at)');
    try {
        $stmt->execute([
            ':full_name' => $fullName,
            ':email' => $email,
            ':username' => strtolower($username),
            ':password_hash' => $hash,
            ':role_id' => $roleId,
            ':permissions' => $permissions ?: null,
            ':password_last_set_at' => $now,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
            throw new RuntimeException('A user with that email or username already exists.');
        }
        throw $exception;
    }

    $userId = (int)$db->lastInsertId();
    audit('create_user', 'user', $userId, sprintf('User %s (%s) created with role %s', $fullName, $email, $roleName));

    return ['user' => fetch_user($db, $userId), 'metrics' => current_metrics($db)];
}

function resolve_role_id(PDO $db, string $roleName): int
{
    $stmt = $db->prepare('SELECT id FROM roles WHERE LOWER(name) = LOWER(:name) LIMIT 1');
    $stmt->execute([':name' => $roleName]);
    $roleId = $stmt->fetchColumn();
    if ($roleId === false) {
        throw new RuntimeException('Unknown role specified.');
    }
    return (int)$roleId;
}

function fetch_user(PDO $db, int $userId): array
{
    $stmt = $db->prepare('SELECT users.id, users.full_name, users.email, users.username, users.status, users.permissions_note, users.created_at, users.password_last_set_at, roles.name AS role_name FROM users INNER JOIN roles ON users.role_id = roles.id WHERE users.id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();
    if (!$user) {
        throw new RuntimeException('User not found.');
    }
    return $user;
}

function update_user_status(PDO $db, array $input): array
{
    $userId = (int)($input['userId'] ?? 0);
    $status = strtolower(trim((string)($input['status'] ?? '')));
    $validStatuses = ['active', 'inactive', 'pending'];
    if (!in_array($status, $validStatuses, true)) {
        throw new RuntimeException('Invalid status provided.');
    }
    $stmt = $db->prepare("UPDATE users SET status = :status, updated_at = datetime('now') WHERE id = :id");
    $stmt->execute([
        ':status' => $status,
        ':id' => $userId,
    ]);
    audit('update_user_status', 'user', $userId, sprintf('User status changed to %s', $status));
    return ['user' => fetch_user($db, $userId), 'metrics' => current_metrics($db)];
}

function create_invitation(PDO $db, array $input): array
{
    $name = trim((string)($input['name'] ?? ''));
    $email = strtolower(trim((string)($input['email'] ?? '')));
    $roleName = strtolower(trim((string)($input['role'] ?? '')));
    $submittedBy = trim((string)($input['submittedBy'] ?? ''));

    if ($name === '' || $email === '' || $roleName === '' || $submittedBy === '') {
        throw new RuntimeException('Complete all invitation fields.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Enter a valid email address.');
    }

    $roleId = resolve_role_id($db, $roleName);
    $token = bin2hex(random_bytes(16));
    $currentUser = current_user();
    $stmt = $db->prepare('INSERT INTO invitations(inviter_id, invitee_name, invitee_email, role_id, status, token, message) VALUES(:inviter_id, :invitee_name, :invitee_email, :role_id, "pending", :token, :message)');
    $stmt->execute([
        ':inviter_id' => $currentUser['id'] ?? null,
        ':invitee_name' => $name,
        ':invitee_email' => $email,
        ':role_id' => $roleId,
        ':token' => $token,
        ':message' => $submittedBy,
    ]);

    $inviteId = (int)$db->lastInsertId();
    audit('create_invitation', 'invitation', $inviteId, sprintf('Invitation recorded for %s (%s)', $name, $email));
    return ['invitation' => fetch_invitation($db, $inviteId), 'metrics' => current_metrics($db)];
}

function fetch_invitation(PDO $db, int $inviteId): array
{
    $stmt = $db->prepare('SELECT invitations.id, invitations.invitee_name, invitations.invitee_email, invitations.status, invitations.created_at, invitations.approved_at, invitations.message, roles.name AS role_name FROM invitations INNER JOIN roles ON invitations.role_id = roles.id WHERE invitations.id = :id LIMIT 1');
    $stmt->execute([':id' => $inviteId]);
    $invite = $stmt->fetch();
    if (!$invite) {
        throw new RuntimeException('Invitation not found.');
    }
    return $invite;
}

function approve_invitation(PDO $db, array $input): array
{
    $inviteId = (int)($input['inviteId'] ?? 0);
    $username = trim((string)($input['username'] ?? ''));
    $password = (string)($input['password'] ?? '');

    if ($inviteId <= 0) {
        throw new RuntimeException('Invalid invitation identifier.');
    }
    if ($username === '' || $password === '') {
        throw new RuntimeException('Set a username and password for the new user.');
    }
    if (strlen($password) < 8) {
        throw new RuntimeException('Passwords must be at least 8 characters.');
    }

    $inviteStmt = $db->prepare('SELECT * FROM invitations WHERE id = :id AND status = "pending" LIMIT 1');
    $inviteStmt->execute([':id' => $inviteId]);
    $invite = $inviteStmt->fetch();
    if (!$invite) {
        throw new RuntimeException('Only pending invitations can be approved.');
    }

    $roleId = (int)$invite['role_id'];
    $fullName = $invite['invitee_name'];
    $email = strtolower($invite['invitee_email']);

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('INSERT INTO users(full_name, email, username, password_hash, role_id, status, permissions_note, password_last_set_at, created_at, updated_at) VALUES(:full_name, :email, :username, :password_hash, :role_id, "active", :permissions_note, :password_last_set_at, :created_at, :updated_at)');
        $stmt->execute([
            ':full_name' => $fullName,
            ':email' => $email,
            ':username' => strtolower($username),
            ':password_hash' => $hash,
            ':role_id' => $roleId,
            ':permissions_note' => null,
            ':password_last_set_at' => $now,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        $db->prepare("UPDATE invitations SET status = 'approved', approved_at = datetime('now') WHERE id = :id")->execute([':id' => $inviteId]);
        $db->commit();
    } catch (Throwable $exception) {
        $db->rollBack();
        if ($exception instanceof PDOException && $exception->getCode() === '23000') {
            throw new RuntimeException('A user with that email or username already exists.');
        }
        throw $exception;
    }

    $userId = (int)$db->lastInsertId();
    audit('approve_invitation', 'invitation', $inviteId, sprintf('Invitation %d approved and converted to user %s', $inviteId, $fullName));

    return [
        'invitation' => fetch_invitation($db, $inviteId),
        'user' => fetch_user($db, $userId),
        'metrics' => current_metrics($db),
    ];
}

function reject_invitation(PDO $db, array $input): array
{
    $inviteId = (int)($input['inviteId'] ?? 0);
    if ($inviteId <= 0) {
        throw new RuntimeException('Invalid invitation identifier.');
    }
    $stmt = $db->prepare('UPDATE invitations SET status = "rejected", approved_at = NULL WHERE id = :id');
    $stmt->execute([':id' => $inviteId]);
    audit('reject_invitation', 'invitation', $inviteId, sprintf('Invitation %d rejected', $inviteId));
    return ['invitation' => fetch_invitation($db, $inviteId), 'metrics' => current_metrics($db)];
}

function update_gemini_settings(PDO $db, array $input): array
{
    $apiKey = trim((string)($input['apiKey'] ?? ''));
    $textModel = trim((string)($input['textModel'] ?? 'gemini-2.5-flash'));
    $imageModel = trim((string)($input['imageModel'] ?? 'gemini-2.5-flash-image'));
    $ttsModel = trim((string)($input['ttsModel'] ?? 'gemini-2.5-flash-preview-tts'));

    if ($apiKey === '') {
        throw new RuntimeException('The Gemini API key is required.');
    }

    set_setting('gemini_api_key', $apiKey, $db);
    set_setting('gemini_text_model', $textModel, $db);
    set_setting('gemini_image_model', $imageModel, $db);
    set_setting('gemini_tts_model', $ttsModel, $db);

    audit('update_gemini', 'settings', 0, 'Gemini provider credentials updated');

    return ['gemini' => [
        'apiKey' => $apiKey,
        'textModel' => $textModel,
        'imageModel' => $imageModel,
        'ttsModel' => $ttsModel,
    ]];
}

function test_gemini_connection(array $input): array
{
    $apiKey = trim((string)($input['apiKey'] ?? ''));
    if ($apiKey === '') {
        throw new RuntimeException('Provide an API key to test.');
    }

    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL extension is required to test Gemini connectivity.');
    }

    $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models';
    $query = http_build_query(['key' => $apiKey, 'pageSize' => 1]);
    $url = $endpoint . '?' . $query;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);

    $responseBody = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($responseBody === false) {
        throw new RuntimeException('Unable to reach Gemini services: ' . $error);
    }

    $payload = json_decode($responseBody, true);
    if ($status >= 400) {
        $message = $payload['error']['message'] ?? ('HTTP ' . $status);
        throw new RuntimeException('Gemini responded with an error: ' . $message);
    }

    return [
        'status' => 'ok',
        'models' => $payload['models'] ?? [],
        'testedAt' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
    ];
}

function change_password(PDO $db, array $input): array
{
    $user = current_user();
    if (!$user) {
        throw new RuntimeException('Authentication required.');
    }

    $current = (string)($input['currentPassword'] ?? '');
    $new = (string)($input['newPassword'] ?? '');
    $confirm = (string)($input['confirmPassword'] ?? '');

    if ($current === '' || $new === '' || $confirm === '') {
        throw new RuntimeException('Provide current, new, and confirmation passwords.');
    }

    if ($new !== $confirm) {
        throw new RuntimeException('New password and confirmation do not match.');
    }

    if (strlen($new) < 8) {
        throw new RuntimeException('New password must be at least 8 characters long.');
    }

    $stmt = $db->prepare('SELECT id, password_hash FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $user['id']]);
    $record = $stmt->fetch();
    if (!$record) {
        throw new RuntimeException('Unable to load the current user.');
    }

    if (!password_verify($current, $record['password_hash'])) {
        throw new RuntimeException('Current password is incorrect.');
    }

    if (password_verify($new, $record['password_hash'])) {
        throw new RuntimeException('New password must differ from the current password.');
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);
    $updatedAt = (new DateTimeImmutable())->format(DateTimeInterface::ATOM);

    $update = $db->prepare("UPDATE users SET password_hash = :hash, password_last_set_at = datetime('now'), updated_at = datetime('now') WHERE id = :id");
    $update->execute([
        ':hash' => $hash,
        ':id' => $user['id'],
    ]);

    start_session();
    $_SESSION['user']['password_last_set_at'] = $updatedAt;

    audit('change_password', 'user', (int)$user['id'], sprintf('Password updated for %s', $user['email'] ?? 'administrator'));

    return ['changedAt' => $updatedAt];
}

function update_login_policy(PDO $db, array $input): array
{
    $retry = max(1, (int)($input['retry'] ?? 5));
    $lockout = max(1, (int)($input['lockout'] ?? 30));
    $session = max(5, (int)($input['session'] ?? 45));
    $twofactor = strtolower(trim((string)($input['twofactor'] ?? 'admin')));
    $validTwofactor = ['all', 'admin', 'none'];
    if (!in_array($twofactor, $validTwofactor, true)) {
        throw new RuntimeException('Invalid two-factor mode.');
    }

    $stmt = $db->prepare("UPDATE login_policies SET retry_limit = :retry, lockout_minutes = :lockout, session_timeout = :session, twofactor_mode = :twofactor, updated_at = datetime('now') WHERE id = 1");
    $stmt->execute([
        ':retry' => $retry,
        ':lockout' => $lockout,
        ':session' => $session,
        ':twofactor' => $twofactor,
    ]);

    audit('update_login_policy', 'policy', 1, sprintf('Authentication policy updated (retry: %d, lockout: %d, twofactor: %s)', $retry, $lockout, $twofactor));

    return ['loginPolicy' => fetch_login_policy($db)];
}

function audit(string $action, string $entityType, int $entityId, string $description): void
{
    $db = get_db();
    $user = current_user();
    $stmt = $db->prepare('INSERT INTO audit_logs(actor_id, action, entity_type, entity_id, description) VALUES(:actor_id, :action, :entity_type, :entity_id, :description)');
    $stmt->execute([
        ':actor_id' => $user['id'] ?? null,
        ':action' => $action,
        ':entity_type' => $entityType,
        ':entity_id' => $entityId,
        ':description' => $description,
    ]);
}
