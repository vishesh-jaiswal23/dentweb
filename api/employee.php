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
    ensure_api_access('employee');
} catch (Throwable $exception) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode(['error' => $exception->getMessage()]);
    exit;
}

$db = get_db();
$action = $_GET['action'] ?? '';
$actor = current_user();
$userId = (int) ($actor['id'] ?? 0);

try {
    switch ($action) {
        case 'bootstrap':
            require_method('GET');
            respond_success(employee_bootstrap_payload($db, $userId));
            break;
        case 'update-task-status':
            require_method('POST');
            $payload = read_json();
            $taskId = (int) ($payload['id'] ?? 0);
            $status = (string) ($payload['status'] ?? '');
            if ($taskId <= 0) {
                throw new RuntimeException('Task ID is required.');
            }
            enforce_task_access($db, $taskId, $userId);
            $task = portal_update_task_status($db, $taskId, $status, $userId);
            respond_success([
                'task' => $task,
                'tasks' => portal_list_tasks($db, $userId),
            ]);
            break;
        case 'update-complaint-status':
            require_method('POST');
            $payload = read_json();
            $reference = (string) ($payload['reference'] ?? '');
            $status = (string) ($payload['status'] ?? '');
            if ($reference === '') {
                throw new RuntimeException('Complaint reference is required.');
            }
            enforce_complaint_access($db, $reference, $userId);
            $complaint = portal_update_complaint_status($db, $reference, $status, $userId);
            respond_success([
                'complaint' => $complaint,
                'complaints' => portal_employee_complaints($db, $userId),
            ]);
            break;
        case 'mark-notification':
            require_method('POST');
            $payload = read_json();
            $notificationId = (int) ($payload['id'] ?? 0);
            $status = (string) ($payload['status'] ?? 'read');
            if ($notificationId <= 0) {
                throw new RuntimeException('Notification ID is required.');
            }
            portal_mark_notification($db, $notificationId, $userId, $status === 'read' ? 'read' : 'unread');
            respond_success(['notifications' => portal_list_notifications($db, $userId, 'employee')]);
            break;
        case 'mark-all-notifications':
            require_method('POST');
            $notifications = portal_list_notifications($db, $userId, 'employee');
            foreach ($notifications as $notification) {
                portal_mark_notification($db, (int) $notification['id'], $userId, 'read');
            }
            respond_success(['notifications' => portal_list_notifications($db, $userId, 'employee')]);
            break;
        case 'search':
            require_method('POST');
            $payload = read_json();
            $query = trim((string) ($payload['query'] ?? ''));
            respond_success(['results' => employee_search($db, $userId, $query)]);
            break;
        case 'ai-generate':
            require_method('POST');
            $payload = read_json();
            respond_success(employee_generate_ai($db, $userId, $payload));
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

function employee_bootstrap_payload(PDO $db, int $userId): array
{
    return [
        'tasks' => portal_list_tasks($db, $userId),
        'complaints' => portal_employee_complaints($db, $userId),
        'documents' => portal_list_documents($db, 'employee'),
        'notifications' => portal_list_notifications($db, $userId, 'employee'),
        'sync' => portal_latest_sync($db, $userId),
    ];
}

function enforce_task_access(PDO $db, int $taskId, int $userId): void
{
    $stmt = $db->prepare('SELECT assignee_id FROM portal_tasks WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $taskId]);
    $assignee = $stmt->fetchColumn();
    if ($assignee === false) {
        throw new RuntimeException('Task not found.');
    }
    if ((int) $assignee !== $userId) {
        throw new RuntimeException('You do not have permission to update this task.');
    }
}

function enforce_complaint_access(PDO $db, string $reference, int $userId): void
{
    $stmt = $db->prepare('SELECT assigned_to FROM complaints WHERE reference = :reference LIMIT 1');
    $stmt->execute([':reference' => $reference]);
    $assignee = $stmt->fetchColumn();
    if ($assignee === false) {
        throw new RuntimeException('Complaint not found.');
    }
    if ((int) $assignee !== $userId) {
        throw new RuntimeException('You do not have permission to update this ticket.');
    }
}

function employee_search(PDO $db, int $userId, string $query): array
{
    $query = trim($query);
    if ($query === '') {
        return [
            'customers' => [],
            'leads' => [],
            'tickets' => [],
            'documents' => [],
            'referrers' => [],
        ];
    }

    $like = '%' . $query . '%';

    $taskStmt = $db->prepare(
        "SELECT id, title, status, priority, due_date, linked_reference FROM portal_tasks " .
        "WHERE assignee_id = :user_id AND (title LIKE :query COLLATE NOCASE " .
        "OR description LIKE :query COLLATE NOCASE OR linked_reference LIKE :query COLLATE NOCASE) " .
        "ORDER BY updated_at DESC LIMIT 6"
    );
    $taskStmt->execute([
        ':user_id' => $userId,
        ':query' => $like,
    ]);
    $leads = [];
    foreach ($taskStmt->fetchAll() as $row) {
        $leads[] = [
            'title' => $row['title'],
            'status' => $row['status'] ?? '',
            'priority' => $row['priority'] ?? '',
            'meta' => $row['linked_reference'] ? 'Reference ' . $row['linked_reference'] : '',
        ];
    }

    $ticketStmt = $db->prepare(
        "SELECT reference, title, status, description FROM complaints WHERE assigned_to = :user_id " .
        "AND (reference LIKE :query COLLATE NOCASE OR title LIKE :query COLLATE NOCASE " .
        "OR description LIKE :query COLLATE NOCASE) ORDER BY updated_at DESC LIMIT 6"
    );
    $ticketStmt->execute([
        ':user_id' => $userId,
        ':query' => $like,
    ]);
    $tickets = [];
    foreach ($ticketStmt->fetchAll() as $row) {
        $tickets[] = [
            'reference' => $row['reference'],
            'title' => $row['title'] ?? '',
            'status' => $row['status'] ?? '',
            'meta' => $row['description'] ? trim((string) $row['description']) : '',
        ];
    }

    $documentStmt = $db->prepare(
        "SELECT name, reference, tags, updated_at FROM portal_documents " .
        "WHERE visibility IN ('employee','both') AND (name LIKE :query COLLATE NOCASE " .
        "OR reference LIKE :query COLLATE NOCASE OR tags LIKE :query COLLATE NOCASE) " .
        "ORDER BY updated_at DESC LIMIT 6"
    );
    $documentStmt->execute([':query' => $like]);
    $documents = [];
    foreach ($documentStmt->fetchAll() as $row) {
        $tags = [];
        if (is_string($row['tags']) && $row['tags'] !== '') {
            $decoded = json_decode($row['tags'], true);
            if (is_array($decoded)) {
                $tags = array_values(array_filter(array_map('trim', $decoded)));
            } else {
                $parts = array_map('trim', explode(',', $row['tags']));
                $tags = array_values(array_filter($parts));
            }
        }
        $documents[] = [
            'title' => $row['name'],
            'reference' => $row['reference'] ?? '',
            'tags' => $tags,
            'meta' => $row['updated_at'] ? 'Updated ' . $row['updated_at'] : '',
        ];
    }

    return [
        'customers' => [],
        'leads' => $leads,
        'tickets' => $tickets,
        'documents' => $documents,
        'referrers' => [],
    ];
}

function employee_generate_ai(PDO $db, int $userId, array $payload): array
{
    $profile = portal_gemini_profile($db);
    $enabled = (bool) ($profile['enabled'] ?? false);

    $response = [
        'enabled' => $enabled,
        'profile' => $profile,
    ];

    if (!$enabled) {
        $response['message'] = 'Gemini assistant is disabled by Admin.';
        return $response;
    }

    $type = strtolower((string) ($payload['type'] ?? 'text'));
    switch ($type) {
        case 'image':
            $prompt = trim((string) ($payload['prompt'] ?? ''));
            $summary = $prompt !== '' ? $prompt : 'Service update illustration';
            $summary = preg_replace('/\s+/', ' ', $summary) ?? $summary;
            $summary = employee_truncate($summary, 140);
            $label = $summary !== '' ? $summary : 'Gemini illustration placeholder';
            $svgText = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
            $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 480 240" role="img" aria-label="Gemini placeholder">
  <defs>
    <linearGradient id="gradient" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#0f172a" />
      <stop offset="100%" stop-color="#1e293b" />
    </linearGradient>
  </defs>
  <rect width="480" height="240" fill="url(#gradient)" />
  <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="#f8fafc" font-family="Arial, sans-serif" font-size="20" opacity="0.9">$svgText</text>
  <text x="50%" y="70%" dominant-baseline="middle" text-anchor="middle" fill="#cbd5f5" font-family="Arial, sans-serif" font-size="14" opacity="0.8">Gemini preview</text>
</svg>
SVG;
            $response['result'] = [
                'kind' => 'image',
                'model' => $profile['imageModel'] ?? 'gemini-2.5-flash-image',
                'content' => 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg),
                'alt' => 'Gemini preview: ' . $label,
            ];
            $response['message'] = 'Illustration drafted locally for preview.';
            break;
        case 'audio':
            $script = trim((string) ($payload['text'] ?? ''));
            $transcript = $script !== '' ? $script : 'This is a synthesized service visit update generated locally.';
            $response['result'] = [
                'kind' => 'audio',
                'model' => $profile['ttsModel'] ?? 'gemini-2.5-flash-preview-tts',
                'content' => 'data:audio/wav;base64,' . employee_placeholder_audio_base64(),
                'transcript' => $transcript,
            ];
            $response['message'] = 'Audio draft generated using offline synthesizer.';
            break;
        case 'text':
        default:
            $purpose = strtolower((string) ($payload['purpose'] ?? 'summary'));
            $context = trim((string) ($payload['context'] ?? ''));
            $suggestion = employee_ai_text_suggestion($purpose, $context);
            $response['result'] = [
                'kind' => 'text',
                'model' => $profile['textModel'] ?? 'gemini-2.5-flash',
                'content' => $suggestion,
            ];
            $response['message'] = 'Gemini text drafted using local template.';
            break;
    }

    return $response;
}

function employee_placeholder_audio_base64(): string
{
    $sampleRate = 8000;
    $durationSeconds = 1.5;
    $samples = (int) ($sampleRate * $durationSeconds);
    $frequency = 523.25; // C5 tone
    $amplitude = 0.35;
    $wave = '';
    for ($i = 0; $i < $samples; $i++) {
        $value = (int) round((sin(2 * pi() * $frequency * $i / $sampleRate) * $amplitude + 0.5) * 255);
        $value = max(0, min(255, $value));
        $wave .= pack('C', $value);
    }

    $byteRate = $sampleRate;
    $blockAlign = 1;
    $dataSize = strlen($wave);
    $header = 'RIFF' . pack('V', 36 + $dataSize) . 'WAVEfmt ' . pack('V', 16) . pack('v', 1) . pack('v', 1) . pack('V', $sampleRate)
        . pack('V', $byteRate) . pack('v', $blockAlign) . pack('v', 8) . 'data' . pack('V', $dataSize);

    return base64_encode($header . $wave);
}

function employee_ai_text_suggestion(string $purpose, string $context): string
{
    $context = trim($context);
    switch ($purpose) {
        case 'followup':
            $intro = 'Hi team, following up on our recent service visit.';
            if ($context !== '') {
                $intro .= ' ' . $context;
            }
            return $intro . ' Please let me know if any additional support is required.';
        case 'caption':
            if ($context === '') {
                $context = 'Sunset over the Dakshayani rooftop installation';
            }
            return ucfirst($context) . ' — powered by Dakshayani Enterprises and ready for peak performance.';
        case 'summary':
        default:
            $summary = 'Service summary: Completed site checks, verified inverter health, and updated warranty logs.';
            if ($context !== '') {
                $summary .= ' Highlights: ' . $context;
            }
            return $summary;
    }
}

function employee_truncate(string $value, int $limit = 140): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $limit);
    }
    return substr($value, 0, $limit);
}
