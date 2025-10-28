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
$actor = current_user();
$actorId = (int) ($actor['id'] ?? 0);

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
        case 'change-password':
            require_method('POST');
            respond_success(change_password($db, read_json()));
            break;
        case 'update-login-policy':
            require_method('POST');
            respond_success(update_login_policy($db, read_json()));
            break;
        case 'save-task':
            require_method('POST');
            respond_success(['task' => portal_save_task($db, read_json(), $actorId), 'tasks' => portal_list_tasks($db)]);
            break;
        case 'update-task-status':
            require_method('POST');
            $payload = read_json();
            $taskId = (int) ($payload['id'] ?? 0);
            if ($taskId <= 0) {
                throw new RuntimeException('Task ID is required.');
            }
            $status = (string) ($payload['status'] ?? '');
            respond_success(['task' => portal_update_task_status($db, $taskId, $status, $actorId), 'tasks' => portal_list_tasks($db)]);
            break;
        case 'save-document':
            require_method('POST');
            respond_success(['document' => portal_save_document($db, read_json(), $actorId), 'documents' => portal_list_documents($db, 'admin')]);
            break;
        case 'save-complaint':
            require_method('POST');
            $complaint = portal_save_complaint($db, read_json(), $actorId);
            respond_success([
                'complaint' => $complaint,
                'complaints' => portal_all_complaints($db),
                'metrics' => current_metrics($db),
            ]);
            break;
        case 'assign-complaint':
            require_method('POST');
            $payload = read_json();
            $reference = (string) ($payload['reference'] ?? '');
            $assigneeId = isset($payload['assigneeId']) && $payload['assigneeId'] !== '' ? (int) $payload['assigneeId'] : null;
            $slaDue = (string) ($payload['slaDue'] ?? '');
            $complaint = portal_assign_complaint($db, $reference, $assigneeId, $slaDue !== '' ? $slaDue : null, $actorId);
            respond_success([
                'complaint' => $complaint,
                'complaints' => portal_all_complaints($db),
                'metrics' => current_metrics($db),
            ]);
            break;
        case 'add-complaint-note':
            require_method('POST');
            $payload = read_json();
            $reference = (string) ($payload['reference'] ?? '');
            $note = (string) ($payload['note'] ?? '');
            $visibility = (string) ($payload['visibility'] ?? 'internal');
            $complaint = portal_add_complaint_note($db, $reference, $note, $actorId, $visibility);
            respond_success([
                'complaint' => $complaint,
                'complaints' => portal_all_complaints($db),
                'metrics' => current_metrics($db),
            ]);
            break;
        case 'add-complaint-attachment':
            require_method('POST');
            $payload = read_json();
            $reference = (string) ($payload['reference'] ?? '');
            $attachment = $payload['attachment'] ?? [];
            if (!is_array($attachment)) {
                throw new RuntimeException('Invalid attachment payload.');
            }
            $complaint = portal_add_complaint_attachment($db, $reference, $attachment, $actorId);
            respond_success([
                'complaint' => $complaint,
                'complaints' => portal_all_complaints($db),
                'metrics' => current_metrics($db),
            ]);
            break;
        case 'update-complaint-status':
            require_method('POST');
            $payload = read_json();
            $reference = (string) ($payload['reference'] ?? '');
            $status = (string) ($payload['status'] ?? '');
            $complaint = portal_admin_update_complaint_status($db, $reference, $status, $actorId);
            respond_success([
                'complaint' => $complaint,
                'complaints' => portal_all_complaints($db),
                'metrics' => current_metrics($db),
            ]);
            break;
        case 'complaint-timeline':
            require_method('GET');
            respond_success([
                'timeline' => fetch_complaint_timeline($db, $_GET),
                'metrics' => current_metrics($db),
            ]);
            break;
        case 'fetch-audit':
            require_method('GET');
            respond_success([
                'audit' => recent_audit_logs($db),
                'metrics' => current_metrics($db),
            ]);
            break;
        case 'fetch-complaints':
            require_method('GET');
            respond_success([
                'complaints' => portal_all_complaints($db),
                'metrics' => current_metrics($db),
            ]);
            break;
        case 'fetch-metrics':
            require_method('GET');
            respond_success(current_metrics($db));
            break;
        case 'list-blog-posts':
            require_method('GET');
            respond_success(['posts' => blog_admin_list($db)]);
            break;
        case 'get-blog-post':
            require_method('GET');
            respond_success(['post' => get_blog_post_for_admin($db, $_GET)]);
            break;
        case 'save-blog-post':
            require_method('POST');
            respond_success(['post' => blog_save_post($db, read_json(), $actorId)]);
            break;
        case 'publish-blog-post':
            require_method('POST');
            $payload = read_json();
            $postId = (int) ($payload['id'] ?? 0);
            if ($postId <= 0) {
                throw new RuntimeException('Post ID is required.');
            }
            $publish = !empty($payload['publish']);
            respond_success(['post' => blog_publish_post($db, $postId, $publish, $actorId)]);
            break;
        case 'archive-blog-post':
            require_method('POST');
            $payload = read_json();
            $postId = (int) ($payload['id'] ?? 0);
            if ($postId <= 0) {
                throw new RuntimeException('Post ID is required.');
            }
            respond_success(['post' => blog_archive_post($db, $postId, $actorId)]);
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
    $blogPosts = blog_admin_list($db);

    return [
        'users' => list_users($db),
        'invitations' => list_invitations($db),
        'complaints' => portal_all_complaints($db),
        'audit' => recent_audit_logs($db),
        'metrics' => current_metrics($db),
        'loginPolicy' => fetch_login_policy($db),
        'tasks' => [
            'items' => portal_list_tasks($db),
            'team' => portal_list_team($db),
        ],
        'documents' => portal_list_documents($db, 'admin'),
        'blog' => [
            'posts' => $blogPosts,
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

function fetch_complaint_timeline(PDO $db, array $query): array
{
    return portal_all_complaints($db);
}

function recent_audit_logs(PDO $db): array
{
    return portal_recent_audit_logs($db);
}

function current_metrics(PDO $db): array
{
    $counts = admin_overview_counts($db);
    $pendingInvites = (int) $db->query("SELECT COUNT(*) FROM invitations WHERE status = 'pending'")->fetchColumn();
    $metrics = $db->query('SELECT name, value FROM system_metrics')->fetchAll(PDO::FETCH_KEY_PAIR);

    return [
        'counts' => [
            'employees' => $counts['employees'],
            'leads' => $counts['leads'],
            'installations' => $counts['installations'],
            'complaints' => $counts['complaints'],
            'subsidy' => $counts['subsidy'],
            'pendingInvitations' => $pendingInvites,
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

function get_blog_post_for_admin(PDO $db, array $query): array
{
    $postId = isset($query['id']) ? (int) $query['id'] : 0;
    $slug = isset($query['slug']) ? trim((string) $query['slug']) : '';

    if ($postId > 0) {
        return blog_get_post_by_id($db, $postId);
    }

    if ($slug !== '') {
        $post = blog_get_post_by_slug($db, $slug, true);
        if ($post) {
            $tags = array_map(static fn ($tag) => $tag['name'] ?? $tag, $post['tags'] ?? []);
            return [
                'id' => (int) $post['id'],
                'title' => $post['title'],
                'slug' => $post['slug'],
                'excerpt' => $post['excerpt'] ?? '',
                'body' => $post['body_html'] ?? '',
                'coverImage' => $post['cover_image'] ?? '',
                'coverImageAlt' => $post['cover_image_alt'] ?? '',
                'authorName' => $post['author_name'] ?? '',
                'status' => $post['status'],
                'publishedAt' => $post['published_at'],
                'updatedAt' => $post['updated_at'],
                'tags' => $tags,
            ];
        }
    }

    throw new RuntimeException('Post not found.');
}

function create_user(PDO $db, array $input): array
{
    $fullName = trim((string)($input['fullName'] ?? ''));
    $email = strtolower(trim((string)($input['email'] ?? '')));
    $username = trim((string)($input['username'] ?? ''));
    $password = (string)($input['password'] ?? '');
    $requestedRole = strtolower(trim((string)($input['role'] ?? '')));
    $permissions = trim((string)($input['permissions'] ?? ''));

    if ($fullName === '' || $email === '' || $username === '' || $password === '' || $requestedRole === '') {
        throw new RuntimeException('All required fields must be supplied.');
    }

    $roleName = normalize_role_name($requestedRole);

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
    $auditMessage = sprintf('User %s (%s) created with role %s', $fullName, $email, $roleName);
    if ($roleName !== $requestedRole) {
        $auditMessage .= sprintf(' (requested %s)', $requestedRole);
    }
    audit('create_user', 'user', $userId, $auditMessage);

    return ['user' => fetch_user($db, $userId), 'metrics' => current_metrics($db)];
}

function normalize_role_name(string $roleName): string
{
    $key = strtolower(trim($roleName));
    $map = [
        'admin' => 'admin',
        'administrator' => 'admin',
        'employee' => 'employee',
        'staff' => 'employee',
        'team' => 'employee',
        'installer' => 'installer',
        'referrer' => 'referrer',
        'customer' => 'customer',
    ];

    if (!array_key_exists($key, $map)) {
        throw new RuntimeException('Unknown role specified.');
    }

    return $map[$key];
}

function resolve_role_id(PDO $db, string $roleName): int
{
    $normalized = normalize_role_name($roleName);
    $stmt = $db->prepare('SELECT id FROM roles WHERE LOWER(name) = LOWER(:name) LIMIT 1');
    $stmt->execute([':name' => $normalized]);
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
    $requestedRole = strtolower(trim((string)($input['role'] ?? '')));
    $submittedBy = trim((string)($input['submittedBy'] ?? ''));

    if ($name === '' || $email === '' || $requestedRole === '' || $submittedBy === '') {
        throw new RuntimeException('Complete all invitation fields.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Enter a valid email address.');
    }

    $roleName = normalize_role_name($requestedRole);
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
    $inviteAudit = sprintf('Invitation recorded for %s (%s) with role %s', $name, $email, $roleName);
    if ($roleName !== $requestedRole) {
        $inviteAudit .= sprintf(' (requested %s)', $requestedRole);
    }
    audit('create_invitation', 'invitation', $inviteId, $inviteAudit);
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
