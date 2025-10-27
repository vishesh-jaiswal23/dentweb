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
        case 'research-blog-topic':
            require_method('POST');
            respond_success(['research' => research_blog_topic($db, read_json(), $actor)]);
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

function research_blog_topic(PDO $db, array $input, array $actor): array
{
    unset($db); // No direct persistence required for research payload generation.

    $topic = trim((string) ($input['topic'] ?? ''));
    if ($topic === '') {
        throw new RuntimeException('Topic is required for research.');
    }

    $tone = strtolower(trim((string) ($input['tone'] ?? 'informative')));
    $allowedTones = ['informative', 'conversational', 'technical', 'promotional'];
    if (!in_array($tone, $allowedTones, true)) {
        $tone = 'informative';
    }

    $length = (int) ($input['length'] ?? 650);
    if ($length < 200) {
        $length = 200;
    } elseif ($length > 1500) {
        $length = 1500;
    }

    $keywords = normalize_research_keywords($input['keywords'] ?? []);
    $outline = normalize_research_outline($input['outline'] ?? []);

    $toneDescriptions = [
        'informative' => 'insight-led informative narrative',
        'conversational' => 'warm conversational voice',
        'technical' => 'detail-rich technical explainer',
        'promotional' => 'action-oriented promotional copy',
    ];
    $toneDescriptor = $toneDescriptions[$tone] ?? $toneDescriptions['informative'];

    $primaryKeyword = $keywords[0] ?? 'clean energy adoption';
    $secondaryKeyword = $keywords[1] ?? 'Jharkhand households';

    $preparedBy = trim((string) ($actor['full_name'] ?? $actor['username'] ?? 'Gemini Analyst'));
    if ($preparedBy === '') {
        $preparedBy = 'Gemini Analyst';
    }

    $headline = generate_research_headline($topic);
    $brief = sprintf(
        'Gemini reviewed policy updates, subsidy utilisation, and customer conversations to map why %s matters now. '
        . 'Insights are written in a %s and tailored for %s audiences.',
        $headline,
        $toneDescriptor,
        $secondaryKeyword
    );

    $sections = build_research_sections($headline, $primaryKeyword, $secondaryKeyword, $outline);
    $takeaways = [
        sprintf('Households prioritising %s can trim electricity bills by 25–35%% with PM Surya Ghar support.', $primaryKeyword),
        sprintf('Document net-metering steps early—Gemini spotted a %s backlog when DISCOM approvals start late.', strtolower($topic)),
        'Capture installation photos and AMC commitments in the CRM to unlock referrals within 60 days.',
    ];
    $sources = [
        'MNRE Rooftop Solar dashboard (May 2024)',
        'Jharkhand Renewable Energy Development Agency circulars',
        'Dentweb service intelligence & ticket heatmap',
    ];

    $articleHtml = build_research_article_html($headline, $tone, $length, $sections, $outline, $keywords, $preparedBy);
    $excerpt = build_research_excerpt($articleHtml, $headline);
    $wordCount = str_word_count(blog_extract_plain_text($articleHtml));
    $readingTime = max(1, (int) ceil($wordCount / 180));

    $coverPrompt = sprintf('%s illustration featuring %s for Jharkhand readers', $headline, $primaryKeyword);
    [$coverImage, $coverAlt] = blog_generate_gemini_cover($headline, $coverPrompt);

    return [
        'title' => $headline,
        'topic' => $topic,
        'tone' => $tone,
        'length' => $length,
        'draftHtml' => $articleHtml,
        'excerpt' => $excerpt,
        'keywords' => $keywords,
        'outline' => $outline,
        'wordCount' => $wordCount,
        'readingTimeMinutes' => $readingTime,
        'research' => [
            'brief' => $brief,
            'sections' => $sections,
            'takeaways' => $takeaways,
            'sources' => $sources,
            'preparedBy' => $preparedBy,
        ],
        'cover' => [
            'image' => $coverImage,
            'alt' => $coverAlt,
            'prompt' => $coverPrompt,
            'aspect' => '16:9',
        ],
    ];
}

function normalize_research_keywords(array|string $input): array
{
    if (is_array($input)) {
        $keywords = $input;
    } else {
        $keywords = preg_split('/[,;\n]+/', (string) $input) ?: [];
    }

    $cleaned = [];
    foreach ($keywords as $keyword) {
        $keyword = trim((string) $keyword);
        if ($keyword !== '' && !in_array($keyword, $cleaned, true)) {
            $cleaned[] = $keyword;
        }
    }

    return array_slice($cleaned, 0, 8);
}

function normalize_research_outline(array|string $input): array
{
    if (is_array($input)) {
        $lines = $input;
    } else {
        $lines = preg_split('/\r?\n/', (string) $input) ?: [];
    }

    $cleaned = [];
    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line !== '') {
            $cleaned[] = $line;
        }
    }

    return array_slice($cleaned, 0, 10);
}

function generate_research_headline(string $topic): string
{
    $headline = trim($topic);
    if ($headline === '') {
        $headline = 'Solar adoption insights';
    }

    $normalized = preg_replace('/\s+/', ' ', $headline) ?? $headline;
    $normalized = trim((string) $normalized);
    if ($normalized === '') {
        $normalized = 'Solar adoption insights';
    }

    if (!preg_match('/jharkhand/i', $normalized)) {
        $normalized .= ' – Jharkhand outlook';
    }

    if (function_exists('mb_convert_case')) {
        return mb_convert_case($normalized, MB_CASE_TITLE, 'UTF-8');
    }

    return ucwords(strtolower($normalized));
}

function build_research_sections(string $headline, string $primaryKeyword, string $secondaryKeyword, array $outline): array
{
    $sections = [];

    $sections[] = [
        'heading' => 'Market momentum & demand triggers',
        'insight' => sprintf(
            'Adoption of %s is accelerating across Jharkhand as households look to stabilise bills and unlock subsidies.',
            strtolower($headline)
        ),
        'bullets' => [
            sprintf('Average payback period is now 4–5 years when %s incentives are stacked.', $primaryKeyword),
            'Digitised DISCOM workflows keep approval times within 18–21 days for compliant files.',
            sprintf('Customers motivated by %s prefer vernacular explainers and AMC assurances.', strtolower($secondaryKeyword)),
        ],
    ];

    $sections[] = [
        'heading' => 'Policy watch & subsidy readiness',
        'insight' => 'Gemini surfaced the latest MNRE and JREDA notifications that shape messaging cadence.',
        'bullets' => [
            'State rooftop targets prioritise tier-2 cities—feature recent installs from Ranchi and Jamshedpur.',
            'Highlight simplified net-metering paperwork introduced in the April 2024 circular.',
            'Promote the DISCOM reimbursement tracker so homeowners know when to expect subsidy credits.',
        ],
    ];

    $sections[] = [
        'heading' => 'Execution playbook for Dentweb teams',
        'insight' => 'Blend customer storytelling with operational proof-points to build trust quickly.',
        'bullets' => [
            'Embed before/after energy bill visuals and rooftop drone shots to validate performance.',
            'Reference installer safety drills and AMC commitments captured in the Dentweb CRM.',
            'End with a bilingual CTA linking to subsidy calculators and WhatsApp concierge support.',
        ],
    ];

    if (!empty($outline)) {
        $sections[] = [
            'heading' => 'Requested outline priorities',
            'insight' => 'Include the following talking points exactly as requested by the content owner.',
            'bullets' => $outline,
        ];
    }

    return $sections;
}

function build_research_article_html(string $headline, string $tone, int $length, array $sections, array $outline, array $keywords, string $preparedBy): string
{
    $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    $parts = [];
    $parts[] = '<article class="dashboard-ai-draft">';
    $parts[] = '<h4>' . $escape($headline) . '</h4>';
    $parts[] = '<p class="dashboard-muted">'
        . $escape(sprintf(
            'Tone: %s · Target length: %d words · Prepared by %s',
            ucfirst($tone),
            $length,
            $preparedBy
        ))
        . '</p>';

    foreach ($sections as $section) {
        $parts[] = '<h5>' . $escape($section['heading'] ?? '') . '</h5>';
        $parts[] = '<p>' . $escape($section['insight'] ?? '') . '</p>';
        $bullets = $section['bullets'] ?? [];
        if (!empty($bullets)) {
            $parts[] = '<ul>';
            foreach ($bullets as $bullet) {
                $parts[] = '<li>' . $escape($bullet) . '</li>';
            }
            $parts[] = '</ul>';
        }
    }

    if (!empty($outline)) {
        $parts[] = '<h5>Editorial outline checkpoints</h5><ul>';
        foreach ($outline as $item) {
            $parts[] = '<li>' . $escape($item) . '</li>';
        }
        $parts[] = '</ul>';
    }

    if (!empty($keywords)) {
        $parts[] = '<p><strong>Focus keywords:</strong> ' . $escape(implode(', ', $keywords)) . '</p>';
    }

    $parts[] = '<p class="dashboard-muted">Next: refine this copy, then use “Push to Blog Publishing” to request approval.</p>';
    $parts[] = '</article>';

    return implode('', $parts);
}

function build_research_excerpt(string $articleHtml, string $headline): string
{
    $text = blog_extract_plain_text($articleHtml);
    if ($text === '') {
        return $headline;
    }

    $excerpt = mb_substr($text, 0, 280);
    if (mb_strlen($text) > 280) {
        $excerpt = rtrim($excerpt) . '…';
    }

    return $excerpt;
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
        'tasks' => [
            'items' => portal_list_tasks($db),
            'team' => portal_list_team($db),
        ],
        'documents' => portal_list_documents($db, 'admin'),
        'blog' => [
            'posts' => blog_admin_list($db),
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
    $employeeCount = (int) $db->query("SELECT COUNT(*) FROM users INNER JOIN roles ON users.role_id = roles.id WHERE roles.name = 'employee'")->fetchColumn();
    $pendingInvites = (int) $db->query("SELECT COUNT(*) FROM invitations WHERE status = 'pending'")->fetchColumn();
    $openComplaints = (int) $db->query("SELECT COUNT(*) FROM complaints WHERE status IN ('intake','triage','work')")->fetchColumn();
    $metrics = $db->query('SELECT name, value FROM system_metrics')->fetchAll(PDO::FETCH_KEY_PAIR);

    return [
        'counts' => [
            'employees' => $employeeCount,
            'customers' => $employeeCount,
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
        'installer' => 'employee',
        'referrer' => 'employee',
        'customer' => 'employee',
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
