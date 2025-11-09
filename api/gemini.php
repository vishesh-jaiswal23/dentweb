<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/ai_gemini.php';

header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    ensure_api_access('admin');
} catch (Throwable $exception) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => $exception->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? '';
$admin = current_user();
$adminId = (int) ($admin['id'] ?? 0);

switch ($action) {
    case 'chat':
        handle_chat_request($adminId);
        break;
    case 'clear-history':
        handle_clear_history($adminId);
        break;
    case 'export-pdf':
        handle_export_pdf($adminId, (string) ($admin['full_name'] ?? 'Administrator'));
        break;
    default:
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unsupported action.']);
        break;
}

function handle_chat_request(int $adminId): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Content-Type: application/json');
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Use POST for chat requests.']);
        return;
    }

    $body = file_get_contents('php://input');
    $payload = [];
    if (is_string($body) && trim($body) !== '') {
        try {
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            $payload = [];
        }
    }

    $message = isset($payload['message']) && is_string($payload['message']) ? trim($payload['message']) : '';
    if ($message === '') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Message cannot be empty.']);
        return;
    }

    $settings = ai_settings_load();
    if (!($settings['enabled'] ?? false)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'AI is currently disabled. Enable Gemini in settings.']);
        return;
    }

    if (($settings['api_key'] ?? '') === '') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Gemini API key is missing.']);
        return;
    }

    $history = ai_chat_history_load($adminId);
    $history[] = [
        'role' => 'user',
        'text' => $message,
        'timestamp' => ai_timestamp(),
    ];

    $contents = ai_convert_history_to_contents($history);

    try {
        $response = ai_gemini_generate($settings, $contents);
        $replyText = ai_gemini_extract_text($response);
        if ($replyText === '') {
            throw new RuntimeException('Gemini returned an empty response.');
        }

        $history[] = [
            'role' => 'assistant',
            'text' => $replyText,
            'timestamp' => ai_timestamp(),
        ];
        $history = ai_chat_history_replace($adminId, $history);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'reply' => $replyText,
            'history' => $history,
        ]);
    } catch (Throwable $exception) {
        header('Content-Type: application/json');
        http_response_code(502);
        echo json_encode([
            'success' => false,
            'error' => $exception->getMessage(),
        ]);
    }
}

function handle_clear_history(int $adminId): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Content-Type: application/json');
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Use POST to clear history.']);
        return;
    }

    ai_chat_history_clear($adminId);

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
}

function handle_export_pdf(int $adminId, string $adminName): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Content-Type: application/json');
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Use GET to export chat.']);
        return;
    }

    $pdf = ai_chat_history_export_pdf($adminId, $adminName);

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="ai-chat-transcript.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
}
