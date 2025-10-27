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

if (($actor['status'] ?? 'active') !== 'active') {
    respond_error('Account inactive. Contact Admin to regain access.');
    exit;
}

try {
    switch ($action) {
        case 'bootstrap':
            require_method('GET');
            respond_success(employee_bootstrap_payload($db, $userId));
            break;
        case 'upload-document':
            require_method('POST');
            respond_success([
                'document' => portal_employee_submit_document($db, read_json(), $userId),
                'documents' => portal_list_documents($db, 'employee', $userId),
            ]);
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
        case 'add-complaint-note':
            require_method('POST');
            $payload = read_json();
            $reference = (string) ($payload['reference'] ?? '');
            $note = (string) ($payload['note'] ?? '');
            enforce_complaint_access($db, $reference, $userId);
            $complaint = portal_add_complaint_note($db, $reference, $note, $userId);
            respond_success([
                'complaint' => $complaint,
                'complaints' => portal_employee_complaints($db, $userId),
            ]);
            break;
        case 'upload-complaint-document':
            require_method('POST');
            $payload = read_json();
            $reference = (string) ($payload['reference'] ?? '');
            if ($reference === '') {
                throw new RuntimeException('Complaint reference is required for uploads.');
            }
            $result = portal_employee_submit_complaint_document($db, $userId, $reference, $payload);
            respond_success($result + [
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
        'documents' => portal_list_documents($db, 'employee', $userId),
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
