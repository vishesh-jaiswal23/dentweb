<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bootstrap.php';

require_admin();

$admin = current_user();
$adminId = (int) ($admin['id'] ?? 0);
$csrfToken = $_SESSION['csrf_token'] ?? '';

function ai_storage_dir(): string
{
    $dir = __DIR__ . '/storage/ai';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    return $dir;
}

function ai_settings_path(): string
{
    return ai_storage_dir() . '/settings.json';
}

function ai_chat_path(int $adminId): string
{
    $suffix = $adminId > 0 ? (string) $adminId : 'shared';
    return ai_storage_dir() . '/chat_' . $suffix . '.json';
}

function ai_settings_defaults(): array
{
    return [
        'enabled' => false,
        'api_key' => '',
        'text_model' => 'gpt-4o-mini',
        'image_model' => 'visionary-pro',
        'tts_model' => 'voicewave-lite',
        'temperature' => 0.7,
        'max_tokens' => 1024,
        'updated_at' => null,
    ];
}

function ai_sanitise_settings(array $settings): array
{
    $defaults = ai_settings_defaults();

    $enabled = !empty($settings['enabled']);
    $apiKey = isset($settings['api_key']) && is_string($settings['api_key']) ? trim($settings['api_key']) : '';
    $textModel = isset($settings['text_model']) && is_string($settings['text_model']) ? trim($settings['text_model']) : $defaults['text_model'];
    $imageModel = isset($settings['image_model']) && is_string($settings['image_model']) ? trim($settings['image_model']) : $defaults['image_model'];
    $ttsModel = isset($settings['tts_model']) && is_string($settings['tts_model']) ? trim($settings['tts_model']) : $defaults['tts_model'];

    $temperature = isset($settings['temperature']) ? (float) $settings['temperature'] : $defaults['temperature'];
    if ($temperature < 0.0) {
        $temperature = 0.0;
    }
    if ($temperature > 2.0) {
        $temperature = 2.0;
    }

    $maxTokens = isset($settings['max_tokens']) ? (int) $settings['max_tokens'] : $defaults['max_tokens'];
    if ($maxTokens < 1) {
        $maxTokens = 1;
    }
    if ($maxTokens > 4096) {
        $maxTokens = 4096;
    }

    return [
        'enabled' => $enabled,
        'api_key' => $apiKey,
        'text_model' => $textModel !== '' ? $textModel : $defaults['text_model'],
        'image_model' => $imageModel !== '' ? $imageModel : $defaults['image_model'],
        'tts_model' => $ttsModel !== '' ? $ttsModel : $defaults['tts_model'],
        'temperature' => $temperature,
        'max_tokens' => $maxTokens,
        'updated_at' => $settings['updated_at'] ?? null,
    ];
}

function ai_load_settings(): array
{
    $path = ai_settings_path();
    if (!is_file($path)) {
        return ai_settings_defaults();
    }

    $contents = file_get_contents($path);
    if ($contents === false || trim($contents) === '') {
        return ai_settings_defaults();
    }

    try {
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        error_log('ai_load_settings: failed to decode settings: ' . $exception->getMessage());
        return ai_settings_defaults();
    }

    if (!is_array($decoded)) {
        return ai_settings_defaults();
    }

    return ai_sanitise_settings($decoded);
}

function ai_save_settings(array $settings): void
{
    $payload = ai_sanitise_settings($settings);
    $payload['updated_at'] = ai_now_iso();

    $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($encoded === false) {
        throw new RuntimeException('Failed to encode AI settings.');
    }

    if (file_put_contents(ai_settings_path(), $encoded, LOCK_EX) === false) {
        throw new RuntimeException('Unable to store AI settings.');
    }
}

function ai_now_iso(): string
{
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
    return $now->format(DateTimeInterface::ATOM);
}

function ai_mask_api_key(string $key): string
{
    if ($key === '') {
        return '';
    }

    $length = strlen($key);
    $visible = min(4, $length);
    $maskedLength = max(4, $length - $visible);

    return str_repeat('•', $maskedLength) . ($visible > 0 ? substr($key, -$visible) : '');
}

function ai_load_chat_history(int $adminId): array
{
    $path = ai_chat_path($adminId);
    if (!is_file($path)) {
        return [];
    }

    $contents = file_get_contents($path);
    if ($contents === false || trim($contents) === '') {
        return [];
    }

    try {
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        error_log('ai_load_chat_history: decode failed: ' . $exception->getMessage());
        return [];
    }

    if (!is_array($decoded)) {
        return [];
    }

    $messages = [];
    foreach ($decoded as $message) {
        if (!is_array($message)) {
            continue;
        }

        $role = isset($message['role']) && is_string($message['role']) ? strtolower(trim($message['role'])) : '';
        if (!in_array($role, ['user', 'assistant'], true)) {
            continue;
        }

        $content = isset($message['content']) && is_string($message['content']) ? trim($message['content']) : '';
        $timestamp = isset($message['timestamp']) && is_string($message['timestamp']) ? $message['timestamp'] : ai_now_iso();

        $messages[] = [
            'role' => $role,
            'content' => $content,
            'timestamp' => $timestamp,
        ];
    }

    return $messages;
}

function ai_save_chat_history(int $adminId, array $messages): void
{
    $normalised = [];
    foreach ($messages as $message) {
        if (!is_array($message)) {
            continue;
        }

        $role = isset($message['role']) && is_string($message['role']) ? strtolower(trim($message['role'])) : '';
        if (!in_array($role, ['user', 'assistant'], true)) {
            continue;
        }

        $content = isset($message['content']) && is_string($message['content']) ? trim($message['content']) : '';
        $timestamp = isset($message['timestamp']) && is_string($message['timestamp']) ? $message['timestamp'] : ai_now_iso();

        $normalised[] = [
            'role' => $role,
            'content' => $content,
            'timestamp' => $timestamp,
        ];
    }

    $maxMessages = 200;
    if (count($normalised) > $maxMessages) {
        $normalised = array_slice($normalised, -$maxMessages);
    }

    $encoded = json_encode($normalised, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($encoded === false) {
        throw new RuntimeException('Failed to encode AI chat history.');
    }

    if (file_put_contents(ai_chat_path($adminId), $encoded, LOCK_EX) === false) {
        throw new RuntimeException('Unable to save AI chat history.');
    }
}

function ai_append_chat_messages(int $adminId, array $messagesToAppend): array
{
    $history = ai_load_chat_history($adminId);
    foreach ($messagesToAppend as $message) {
        if (!is_array($message)) {
            continue;
        }
        $history[] = $message;
    }

    ai_save_chat_history($adminId, $history);

    return $history;
}

function ai_is_ai_ready(array $settings): bool
{
    return !empty($settings['enabled']) && isset($settings['api_key']) && is_string($settings['api_key']) && trim($settings['api_key']) !== '';
}

function ai_generate_stubbed_response(string $prompt, array $history, array $settings): string
{
    $promptLower = strtolower($prompt);
    $segments = [];

    if (str_contains($promptLower, 'summarise') || str_contains($promptLower, 'summarize')) {
        $segments[] = 'Summary snapshot';
        $segments[] = '• Pipeline status: highlight the most active projects and any blockers.';
        $segments[] = '• Customer follow-ups: prioritise overdue replies before end of day.';
        $segments[] = '• Team focus: align field teams on the latest installation milestones.';
    } elseif (str_contains($promptLower, 'email')) {
        $segments[] = 'Draft email outline';
        $segments[] = 'Subject: Quick update from Dakshayani Enterprises';
        $segments[] = 'Hi there,';
        $segments[] = 'Thank you for staying connected with us. Here’s the status update and the next action items. Let me know if you would like a walkthrough call.';
        $segments[] = 'Warm regards,\nDakshayani Enterprises';
    } elseif (str_contains($promptLower, 'proposal') || str_contains($promptLower, 'solar')) {
        $segments[] = 'Solar proposal checklist';
        $segments[] = '1. Site snapshot with current consumption and sanctioned load.';
        $segments[] = '2. System design with capacity, module selection, and expected generation.';
        $segments[] = '3. Commercials covering capex, subsidies, and ROI timeline.';
        $segments[] = '4. Implementation plan with tentative installation and inspection dates.';
    } else {
        $segments[] = 'Here is a thoughtful response';
        $segments[] = '• Intent understood: ' . ucfirst(trim($prompt));
        if (!empty($history)) {
            $recentUserMessages = array_reverse(array_filter($history, static function ($message) {
                return is_array($message) && ($message['role'] ?? '') === 'user';
            }));
            $lastSnippets = [];
            foreach ($recentUserMessages as $message) {
                $content = (string) ($message['content'] ?? '');
                if ($content === '') {
                    continue;
                }
                $lastSnippets[] = mb_substr($content, 0, 80);
                if (count($lastSnippets) === 2) {
                    break;
                }
            }
            if (!empty($lastSnippets)) {
                $segments[] = '• Recent context: ' . implode(' / ', $lastSnippets);
            }
        }
        $segments[] = '• Suggested next step: convert the idea into tasks with due dates so the team can execute without delays.';
    }

    $segments[] = sprintf('Model: %s | Temperature: %.1f | Max tokens: %d', $settings['text_model'] ?? 'gpt-4o-mini', $settings['temperature'] ?? 0.7, $settings['max_tokens'] ?? 1024);

    return implode("\n\n", $segments);
}

function ai_is_async_request(): bool
{
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return true;
    }

    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (is_string($accept) && str_contains($accept, 'application/json')) {
        return true;
    }

    return false;
}

function ai_chat_timestamp_display(string $timestamp): string
{
    try {
        $dt = new DateTimeImmutable($timestamp);
        $dt = $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
        return $dt->format('d M Y · h:i A');
    } catch (Throwable $exception) {
        return $timestamp;
    }
}

function ai_pdf_escape(string $text): string
{
    $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    return preg_replace('/[\r\n]+/', ' ', $text) ?? $text;
}

function ai_render_chat_pdf(array $messages, string $adminName): void
{
    $title = 'AI Chat Export - ' . ($adminName !== '' ? $adminName : 'Administrator');
    $lines = [];
    foreach ($messages as $message) {
        if (!is_array($message)) {
            continue;
        }
        $role = ($message['role'] ?? '') === 'assistant' ? 'Assistant' : 'You';
        $timestamp = ai_chat_timestamp_display((string) ($message['timestamp'] ?? ai_now_iso()));
        $lines[] = sprintf('%s · %s', $role, $timestamp);
        $content = (string) ($message['content'] ?? '');
        if ($content !== '') {
            $wrapped = preg_split('/\r?\n/', $content) ?: [$content];
            foreach ($wrapped as $line) {
                $lines[] = $line;
            }
        }
        $lines[] = '';
    }

    if (empty($lines)) {
        $lines[] = 'No chat history available.';
    }

    $content = "BT\n/F1 12 Tf\n72 760 Td\n(" . ai_pdf_escape($title) . ") Tj\n";
    foreach ($lines as $index => $line) {
        $content .= "0 -18 Td\n(" . ai_pdf_escape($line) . ") Tj\n";
    }
    $content .= "ET\n";

    $objects = [];
    $objects[] = "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n";
    $objects[] = "2 0 obj << /Type /Pages /Count 1 /Kids [3 0 R] >> endobj\n";
    $objects[] = "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj\n";
    $objects[] = sprintf("4 0 obj << /Length %d >> stream\n%sendstream\nendobj\n", strlen($content), $content);
    $objects[] = "5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n";

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $object) {
        $offsets[] = strlen($pdf);
        $pdf .= $object;
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 " . count($offsets) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1, $total = count($offsets); $i < $total; $i++) {
        $pdf .= sprintf('%010d 00000 n %s', $offsets[$i], "\n");
    }

    $pdf .= "trailer << /Size " . count($offsets) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

    $filename = 'ai-chat-' . date('Ymd-His') . '.pdf';

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdf));

    echo $pdf;
    exit;
}

function ai_test_connection(array $settings): array
{
    if (empty($settings['enabled'])) {
        return [
            'success' => false,
            'message' => 'AI is currently disabled. Enable it before running a test.',
        ];
    }

    $apiKey = isset($settings['api_key']) && is_string($settings['api_key']) ? trim($settings['api_key']) : '';
    if ($apiKey === '') {
        return [
            'success' => false,
            'message' => 'Add an API key before testing the connection.',
        ];
    }

    if (strlen($apiKey) < 12) {
        return [
            'success' => false,
            'message' => 'The API key looks incomplete. Double-check and try again.',
        ];
    }

    return [
        'success' => true,
        'message' => 'Connection looks good. You can start using AI features.',
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    switch ($action) {
        case 'save_api_key':
            if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
                set_flash('error', 'Your session expired. Please try again.');
                header('Location: admin-ai-studio.php');
                exit;
            }

            $newKey = isset($_POST['api_key']) && is_string($_POST['api_key']) ? trim($_POST['api_key']) : '';
            if ($newKey === '') {
                set_flash('error', 'Enter a valid API key.');
                header('Location: admin-ai-studio.php#ai-settings');
                exit;
            }

            $settings = ai_load_settings();
            $settings['api_key'] = $newKey;
            ai_save_settings($settings);
            set_flash('success', 'API key saved securely.');
            header('Location: admin-ai-studio.php#ai-settings');
            exit;

        case 'delete_api_key':
            if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
                set_flash('error', 'Your session expired. Please try again.');
                header('Location: admin-ai-studio.php');
                exit;
            }

            $settings = ai_load_settings();
            $settings['api_key'] = '';
            ai_save_settings($settings);
            set_flash('success', 'API key removed.');
            header('Location: admin-ai-studio.php#ai-settings');
            exit;

        case 'reveal_api_key':
            if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
                set_flash('error', 'Your session expired. Please try again.');
                header('Location: admin-ai-studio.php');
                exit;
            }

            $settings = ai_load_settings();
            $apiKey = $settings['api_key'] ?? '';
            if (!is_string($apiKey) || trim($apiKey) === '') {
                set_flash('warning', 'No API key is configured.');
            } else {
                $_SESSION['ai_key_reveal_once'] = $apiKey;
                set_flash('info', 'API key revealed below. It will be hidden after you leave this page.');
            }
            header('Location: admin-ai-studio.php#ai-settings');
            exit;

        case 'save_settings':
            if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
                set_flash('error', 'Your session expired. Please try again.');
                header('Location: admin-ai-studio.php');
                exit;
            }

            $settings = ai_load_settings();
            $settings['enabled'] = isset($_POST['ai_enabled']) && $_POST['ai_enabled'] === '1';
            $settings['text_model'] = isset($_POST['text_model']) ? (string) $_POST['text_model'] : $settings['text_model'];
            $settings['image_model'] = isset($_POST['image_model']) ? (string) $_POST['image_model'] : $settings['image_model'];
            $settings['tts_model'] = isset($_POST['tts_model']) ? (string) $_POST['tts_model'] : $settings['tts_model'];
            $settings['temperature'] = isset($_POST['temperature']) ? (float) $_POST['temperature'] : $settings['temperature'];
            $settings['max_tokens'] = isset($_POST['max_tokens']) ? (int) $_POST['max_tokens'] : $settings['max_tokens'];

            ai_save_settings($settings);
            set_flash('success', 'AI preferences updated.');
            header('Location: admin-ai-studio.php#ai-settings');
            exit;

        case 'test_connection':
            if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
                set_flash('error', 'Your session expired. Please try again.');
                header('Location: admin-ai-studio.php');
                exit;
            }

            $settings = ai_load_settings();
            $result = ai_test_connection($settings);
            $_SESSION['ai_test_result'] = $result;
            header('Location: admin-ai-studio.php#ai-settings');
            exit;

        case 'chat_clear':
            if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
                if (ai_is_async_request()) {
                    http_response_code(419);
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Session expired. Refresh and try again.']);
                    exit;
                }
                set_flash('error', 'Your session expired. Please try again.');
                header('Location: admin-ai-studio.php#ai-chat');
                exit;
            }

            ai_save_chat_history($adminId, []);

            if (ai_is_async_request()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit;
            }

            set_flash('success', 'Chat history cleared.');
            header('Location: admin-ai-studio.php#ai-chat');
            exit;

        case 'chat_send':
            if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
                http_response_code(419);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Session expired. Refresh and try again.']);
                exit;
            }

            $message = isset($_POST['message']) && is_string($_POST['message']) ? trim($_POST['message']) : '';
            if ($message === '') {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Enter a message to start the chat.']);
                exit;
            }

            $settings = ai_load_settings();
            if (!ai_is_ai_ready($settings)) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'AI is offline. Configure the API key and enable it in settings.']);
                exit;
            }

            $timestamp = ai_now_iso();
            ai_append_chat_messages($adminId, [[
                'role' => 'user',
                'content' => $message,
                'timestamp' => $timestamp,
            ]]);

            $history = ai_load_chat_history($adminId);
            $responseText = ai_generate_stubbed_response($message, $history, $settings);
            $assistantTimestamp = ai_now_iso();
            $history[] = [
                'role' => 'assistant',
                'content' => $responseText,
                'timestamp' => $assistantTimestamp,
            ];
            ai_save_chat_history($adminId, $history);

            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('X-Accel-Buffering: no');

            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            ob_implicit_flush(true);

            $chunks = str_split($responseText, 80);
            foreach ($chunks as $chunk) {
                echo $chunk . "\n";
                flush();
                usleep(50000);
            }
            exit;

        case 'chat_export_pdf':
            if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
                set_flash('error', 'Your session expired. Please try again.');
                header('Location: admin-ai-studio.php#ai-chat');
                exit;
            }

            $history = ai_load_chat_history($adminId);
            ai_render_chat_pdf($history, (string) ($admin['full_name'] ?? 'Administrator'));
            break;

        default:
            break;
    }
}

$settings = ai_load_settings();
$chatHistory = ai_load_chat_history($adminId);
$aiReady = ai_is_ai_ready($settings);

$revealedApiKey = null;
if (isset($_SESSION['ai_key_reveal_once'])) {
    $revealedApiKey = (string) $_SESSION['ai_key_reveal_once'];
    unset($_SESSION['ai_key_reveal_once']);
}

$testResult = null;
if (isset($_SESSION['ai_test_result']) && is_array($_SESSION['ai_test_result'])) {
    $testResult = $_SESSION['ai_test_result'];
    unset($_SESSION['ai_test_result']);
}

$hasApiKey = isset($settings['api_key']) && is_string($settings['api_key']) && trim($settings['api_key']) !== '';
$apiKeyDisplay = $revealedApiKey !== null
    ? $revealedApiKey
    : ($hasApiKey ? ai_mask_api_key($settings['api_key']) : '');
$apiKeyRevealed = $revealedApiKey !== null;

$flashData = consume_flash();
$flashMessage = '';
$flashTone = 'info';
$flashIcon = 'fa-circle-info';
$flashIcons = [
    'success' => 'fa-circle-check',
    'warning' => 'fa-triangle-exclamation',
    'error' => 'fa-circle-exclamation',
    'info' => 'fa-circle-info',
];

if (is_array($flashData)) {
    if (isset($flashData['message']) && is_string($flashData['message'])) {
        $flashMessage = trim($flashData['message']);
    }
    if (isset($flashData['type']) && is_string($flashData['type'])) {
        $candidateTone = strtolower(trim($flashData['type']));
        if (isset($flashIcons[$candidateTone])) {
            $flashTone = $candidateTone;
            $flashIcon = $flashIcons[$candidateTone];
        }
    }
}

$textModelOptions = [
    'gpt-4o-mini' => 'GPT-4o Mini',
    'gpt-4o' => 'GPT-4o',
    'claude-haiku' => 'Claude Haiku',
    'mistral-large' => 'Mistral Large',
];

$imageModelOptions = [
    'visionary-pro' => 'Visionary Pro',
    'dalle-3' => 'DALL·E 3',
    'stable-diffusion-xl' => 'Stable Diffusion XL',
    'midjourney-lite' => 'Midjourney Lite',
];

$ttsModelOptions = [
    'voicewave-lite' => 'VoiceWave Lite',
    'sonic-flow' => 'Sonic Flow',
    'clarity-pro' => 'Clarity Pro',
];

$temperatureValue = isset($settings['temperature']) ? (float) $settings['temperature'] : 0.7;
$maxTokensValue = isset($settings['max_tokens']) ? (int) $settings['max_tokens'] : 1024;
$temperatureDisplay = number_format($temperatureValue, 1);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>AI Studio | Admin</title>
  <link rel="icon" href="images/favicon.ico" />
  <link rel="stylesheet" href="style.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap"
    rel="stylesheet"
  />
  <link
    href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@500&display=swap"
    rel="stylesheet"
  />
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    crossorigin="anonymous"
    referrerpolicy="no-referrer"
  />
</head>
<body data-dashboard-theme="light">
  <main class="dashboard">
    <div class="container dashboard-shell">
      <header class="dashboard-header">
        <div class="dashboard-heading">
          <span class="badge"><i class="fa-solid fa-robot" aria-hidden="true"></i> Admin AI Studio</span>
          <h1>AI Studio</h1>
        </div>
        <p class="dashboard-subheading">
          Configure Dentweb AI assistants and experiment with quick prompts using the secure studio sandbox.
        </p>
        <p class="dashboard-meta">
          <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
          Signed in as <strong><?= htmlspecialchars($admin['full_name'] ?? 'Administrator', ENT_QUOTES) ?></strong>
        </p>
      </header>

      <?php if ($flashMessage !== ''): ?>
      <div class="portal-flash portal-flash--<?= htmlspecialchars($flashTone, ENT_QUOTES) ?>" role="status" aria-live="polite">
        <i class="fa-solid <?= htmlspecialchars($flashIcon, ENT_QUOTES) ?>" aria-hidden="true"></i>
        <span><?= htmlspecialchars($flashMessage, ENT_QUOTES) ?></span>
      </div>
      <?php endif; ?>

      <section id="ai-settings" class="dashboard-section">
        <h2>AI Settings</h2>
        <p class="dashboard-section-sub">Centralise model access, manage secure keys, and fine-tune response behaviour.</p>

        <?php if ($testResult !== null): ?>
        <div class="dashboard-inline-status" data-tone="<?= !empty($testResult['success']) ? 'success' : 'error' ?>">
          <i class="fa-solid <?= !empty($testResult['success']) ? 'fa-circle-check' : 'fa-circle-exclamation' ?>" aria-hidden="true"></i>
          <div>
            <strong><?= !empty($testResult['success']) ? 'Connection successful' : 'Connection failed' ?></strong>
            <p><?= htmlspecialchars((string) ($testResult['message'] ?? ''), ENT_QUOTES) ?></p>
          </div>
        </div>
        <?php endif; ?>

        <?php if (!$aiReady): ?>
        <div class="dashboard-inline-status">
          <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
          <div>
            <strong>AI is offline</strong>
            <p>Add an API key and enable AI to unlock chat and automation features.</p>
          </div>
        </div>
        <?php endif; ?>

        <div class="ai-settings-grid">
          <form method="post" class="dashboard-form ai-settings-card" autocomplete="off">
            <h3>API Key</h3>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
            <div class="ai-key-display" aria-live="polite">
              <span class="ai-key-display__label">Current key</span>
              <code class="ai-key-display__value">
                <?= $hasApiKey ? htmlspecialchars($apiKeyDisplay, ENT_QUOTES) : 'Not set' ?>
              </code>
            </div>
            <p class="dashboard-form-note"><i class="fa-solid fa-lock" aria-hidden="true"></i> Stored securely on disk and hidden by default.</p>
            <label>
              <span>Update key</span>
              <input type="password" name="api_key" placeholder="Enter provider API key" autocomplete="new-password" />
            </label>
            <div class="ai-settings-actions">
              <button type="submit" name="action" value="save_api_key" class="btn btn-primary">Save key</button>
              <button type="submit" name="action" value="reveal_api_key" class="btn btn-secondary">Reveal once</button>
              <button type="submit" name="action" value="delete_api_key" class="btn btn-link">Remove key</button>
            </div>
          </form>

          <form method="post" class="dashboard-form ai-settings-card">
            <h3>Preferences</h3>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
            <label class="ai-toggle">
              <span>Enable AI workspace</span>
              <input type="checkbox" name="ai_enabled" value="1" <?= $settings['enabled'] ? 'checked' : '' ?> />
            </label>
            <div class="dashboard-form-grid dashboard-form-grid--two">
              <label>
                <span>Text model</span>
                <select name="text_model">
                  <?php foreach ($textModelOptions as $value => $label): ?>
                  <option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>" <?= $settings['text_model'] === $value ? 'selected' : '' ?>>
                    <?= htmlspecialchars($label, ENT_QUOTES) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>
                <span>Image model</span>
                <select name="image_model">
                  <?php foreach ($imageModelOptions as $value => $label): ?>
                  <option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>" <?= $settings['image_model'] === $value ? 'selected' : '' ?>>
                    <?= htmlspecialchars($label, ENT_QUOTES) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>
                <span>Text-to-speech model</span>
                <select name="tts_model">
                  <?php foreach ($ttsModelOptions as $value => $label): ?>
                  <option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>" <?= $settings['tts_model'] === $value ? 'selected' : '' ?>>
                    <?= htmlspecialchars($label, ENT_QUOTES) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>
                <span>Max tokens</span>
                <input type="number" name="max_tokens" min="1" max="4096" value="<?= htmlspecialchars((string) $maxTokensValue, ENT_QUOTES) ?>" />
              </label>
            </div>
            <label>
              <span>Temperature <output class="ai-temperature-display" data-temperature-display><?= htmlspecialchars($temperatureDisplay, ENT_QUOTES) ?></output></span>
              <input
                type="range"
                name="temperature"
                min="0"
                max="2"
                step="0.1"
                value="<?= htmlspecialchars((string) $temperatureValue, ENT_QUOTES) ?>"
                data-temperature-input
              />
            </label>
            <div class="ai-settings-actions">
              <button type="submit" name="action" value="save_settings" class="btn btn-primary">Save preferences</button>
              <button type="submit" name="action" value="test_connection" class="btn btn-secondary" formnovalidate>Test connection</button>
            </div>
          </form>
        </div>
      </section>

      <section id="ai-chat" class="dashboard-section">
        <h2>AI Chat</h2>
        <p class="dashboard-section-sub">Test prompts with streaming responses. History stays private to your admin account.</p>

        <?php if (!$aiReady): ?>
        <div class="dashboard-inline-status">
          <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
          <div>
            <strong>Chat disabled</strong>
            <p>Enable AI and add a valid API key to start the conversation.</p>
          </div>
        </div>
        <?php endif; ?>

        <div class="ai-chat-console" data-chat-ready="<?= $aiReady ? '1' : '0' ?>">
          <div class="ai-chat-quick-prompts" role="list" aria-label="Quick prompts">
            <button type="button" data-quick-prompt="Summarise the current project pipeline">Summarise projects</button>
            <button type="button" data-quick-prompt="Draft a friendly follow-up email for a prospect">Draft follow-up email</button>
            <button type="button" data-quick-prompt="Create a solar proposal outline for a 25 kW rooftop system">Solar proposal</button>
            <button type="button" data-quick-prompt="List key talking points for a customer review call">Review call prep</button>
          </div>

          <div class="ai-chat-messages" data-chat-messages>
            <?php foreach ($chatHistory as $message): ?>
            <article class="ai-chat-message ai-chat-message--<?= htmlspecialchars($message['role'], ENT_QUOTES) ?>">
              <header class="ai-chat-message__meta">
                <strong><?= $message['role'] === 'assistant' ? 'Assistant' : 'You' ?></strong>
                <span><?= htmlspecialchars(ai_chat_timestamp_display((string) $message['timestamp']), ENT_QUOTES) ?></span>
              </header>
              <div class="ai-chat-message__content"><?= nl2br(htmlspecialchars((string) $message['content'], ENT_QUOTES), false) ?></div>
            </article>
            <?php endforeach; ?>
          </div>

          <div class="ai-chat-alerts" data-chat-alerts role="status" aria-live="polite"></div>

          <form
            id="ai-chat-form"
            class="ai-chat-form"
            method="post"
            action="admin-ai-studio.php"
            data-endpoint="admin-ai-studio.php"
          >
            <input type="hidden" name="action" value="chat_send" />
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
            <label class="sr-only" for="ai-chat-input">Your message</label>
            <textarea
              id="ai-chat-input"
              name="message"
              class="ai-chat-input"
              placeholder="Type a prompt or choose a quick suggestion..."
              rows="5"
              <?= $aiReady ? '' : 'disabled' ?>
            ></textarea>
            <div class="ai-chat-actions">
              <button type="submit" class="btn btn-primary" data-chat-submit <?= $aiReady ? '' : 'disabled' ?>>Send message</button>
              <button type="button" class="btn btn-secondary" data-chat-clear <?= empty($chatHistory) ? 'disabled' : '' ?>>Clear history</button>
              <button type="button" class="btn btn-link" data-chat-export>Export as PDF</button>
            </div>
            <p class="ai-chat-streaming" data-streaming-indicator hidden>
              <i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i>
              Streaming response...
            </p>
          </form>
        </div>
      </section>
    </div>
  </main>

  <form id="ai-chat-export-form" method="post" action="admin-ai-studio.php" target="_blank" hidden>
    <input type="hidden" name="action" value="chat_export_pdf" />
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
  </form>

  <script>
    (function () {
      const temperatureInput = document.querySelector('[data-temperature-input]');
      const temperatureDisplay = document.querySelector('[data-temperature-display]');
      if (temperatureInput && temperatureDisplay) {
        temperatureInput.addEventListener('input', function () {
          temperatureDisplay.textContent = Number.parseFloat(this.value).toFixed(1);
        });
      }

      const chatForm = document.getElementById('ai-chat-form');
      const messageInput = document.getElementById('ai-chat-input');
      const messagesContainer = document.querySelector('[data-chat-messages]');
      const alertsContainer = document.querySelector('[data-chat-alerts]');
      const submitButton = chatForm ? chatForm.querySelector('[data-chat-submit]') : null;
      const streamingIndicator = document.querySelector('[data-streaming-indicator]');
      const clearButton = document.querySelector('[data-chat-clear]');
      const exportButton = document.querySelector('[data-chat-export]');
      const exportForm = document.getElementById('ai-chat-export-form');
      const readyState = document.querySelector('[data-chat-ready]');

      function pushAlert(message, tone = 'error') {
        if (!alertsContainer) {
          return;
        }
        alertsContainer.innerHTML = '';
        if (!message) {
          return;
        }
        const wrapper = document.createElement('div');
        wrapper.className = 'dashboard-inline-status';
        wrapper.dataset.tone = tone === 'success' ? 'success' : tone === 'progress' ? 'progress' : 'error';
        const icon = document.createElement('i');
        icon.className = tone === 'success' ? 'fa-solid fa-circle-check' : tone === 'progress' ? 'fa-solid fa-circle-notch fa-spin' : 'fa-solid fa-circle-exclamation';
        icon.setAttribute('aria-hidden', 'true');
        const content = document.createElement('div');
        const strong = document.createElement('strong');
        strong.textContent = tone === 'success' ? 'All set' : tone === 'progress' ? 'Working on it' : 'Heads up';
        const para = document.createElement('p');
        para.textContent = message;
        content.append(strong, para);
        wrapper.append(icon, content);
        alertsContainer.append(wrapper);
      }

      function scrollMessagesToBottom() {
        if (!messagesContainer) {
          return;
        }
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
      }

      function appendMessage(role, content, timestamp) {
        if (!messagesContainer) {
          return null;
        }
        const article = document.createElement('article');
        article.className = 'ai-chat-message ai-chat-message--' + role;

        const header = document.createElement('header');
        header.className = 'ai-chat-message__meta';
        const strong = document.createElement('strong');
        strong.textContent = role === 'assistant' ? 'Assistant' : 'You';
        const time = document.createElement('span');
        time.textContent = timestamp;
        header.append(strong, time);

        const body = document.createElement('div');
        body.className = 'ai-chat-message__content';
        body.textContent = content;

        article.append(header, body);
        messagesContainer.append(article);
        scrollMessagesToBottom();
        return { article, body, time };
      }

      function formatTimestamp(date) {
        try {
          return new Intl.DateTimeFormat('en-IN', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
          }).format(date);
        } catch (error) {
          return date.toLocaleString();
        }
      }

      if (messagesContainer) {
        scrollMessagesToBottom();
      }

      if (readyState && readyState.dataset.chatReady === '0') {
        pushAlert('AI chat is currently disabled. Update the settings above to start chatting.', 'progress');
      }

      if (chatForm && messageInput) {
        chatForm.addEventListener('submit', async function (event) {
          event.preventDefault();
          const message = messageInput.value.trim();
          if (!message) {
            pushAlert('Add a prompt or choose a quick suggestion to continue.', 'error');
            return;
          }

          pushAlert('Sending your message…', 'progress');

          const now = new Date();
          appendMessage('user', message, formatTimestamp(now));
          messageInput.value = '';
          messageInput.focus();

          if (submitButton) {
            submitButton.disabled = true;
          }
          if (clearButton) {
            clearButton.disabled = false;
          }
          if (streamingIndicator) {
            streamingIndicator.hidden = false;
          }

          const formData = new FormData(chatForm);
          formData.set('message', message);

          try {
            const response = await fetch(chatForm.getAttribute('action') || 'admin-ai-studio.php', {
              method: 'POST',
              body: formData,
              headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!response.ok) {
              const errorPayload = await response.json().catch(() => ({ error: 'Something went wrong. Try again.' }));
              pushAlert(errorPayload.error || 'Unable to process that request.', 'error');
              if (submitButton) {
                submitButton.disabled = false;
              }
              if (streamingIndicator) {
                streamingIndicator.hidden = true;
              }
              return;
            }

            const assistantTimestamp = formatTimestamp(new Date());
            const assistantMessage = appendMessage('assistant', '', assistantTimestamp);

            if (!assistantMessage) {
              pushAlert('Unable to render the assistant response.', 'error');
              return;
            }

            const reader = response.body?.getReader();
            if (!reader) {
              assistantMessage.body.textContent = 'Unable to stream response.';
              pushAlert('Streaming is not supported in this browser.', 'error');
              return;
            }

            const decoder = new TextDecoder();
            let fullText = '';
            while (true) {
              const { value, done } = await reader.read();
              if (done) {
                break;
              }
              const chunk = decoder.decode(value, { stream: true });
              fullText += chunk;
              assistantMessage.body.textContent = fullText;
              scrollMessagesToBottom();
            }

            assistantMessage.body.textContent = fullText.trim();
            pushAlert('Response ready.', 'success');
          } catch (error) {
            pushAlert('We could not reach the assistant. Check your connection and try again.', 'error');
          } finally {
            if (submitButton) {
              submitButton.disabled = false;
            }
            if (streamingIndicator) {
              streamingIndicator.hidden = true;
            }
          }
        });
      }

      if (clearButton) {
        clearButton.addEventListener('click', async function () {
          if (!window.confirm('Clear the entire chat history for this admin account?')) {
            return;
          }

          const formData = new FormData();
          formData.set('action', 'chat_clear');
          formData.set('csrf_token', '<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>');

          try {
            const response = await fetch('admin-ai-studio.php', {
              method: 'POST',
              body: formData,
              headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
              },
            });

            if (!response.ok) {
              const payload = await response.json().catch(() => ({ message: 'Unable to clear chat history.' }));
              pushAlert(payload.message || 'Unable to clear chat history.', 'error');
              return;
            }

            if (messagesContainer) {
              messagesContainer.innerHTML = '';
            }
            pushAlert('Chat history cleared.', 'success');
            clearButton.disabled = true;
          } catch (error) {
            pushAlert('Clearing the chat failed. Please try again.', 'error');
          }
        });
      }

      if (exportButton && exportForm) {
        exportButton.addEventListener('click', function () {
          exportForm.requestSubmit();
        });
      }

      const quickPromptButtons = document.querySelectorAll('[data-quick-prompt]');
      quickPromptButtons.forEach(function (button) {
        button.addEventListener('click', function () {
          if (!messageInput || messageInput.disabled) {
            return;
          }
          const prompt = this.getAttribute('data-quick-prompt');
          if (!prompt) {
            return;
          }
          messageInput.value = prompt;
          messageInput.focus();
          pushAlert('Prompt inserted. You can edit it before sending.', 'progress');
        });
      });
    })();
  </script>
</body>
</html>
