<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/ai_gemini.php';
require_once __DIR__ . '/../includes/blog.php';

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
    case 'blog-generate':
        handle_blog_generate($adminId);
        break;
    case 'blog-autosave':
        handle_blog_autosave($adminId);
        break;
    case 'blog-load-draft':
        handle_blog_load_draft($adminId);
        break;
    case 'blog-publish':
        handle_blog_publish($adminId);
        break;
    case 'blog-regenerate-paragraph':
        handle_blog_regenerate_paragraph($adminId);
        break;
    case 'image-generate':
        handle_image_generate($adminId);
        break;
    case 'tts-generate':
        handle_tts_generate($adminId);
        break;
    case 'sandbox-text':
        handle_sandbox_text($adminId);
        break;
    case 'sandbox-image':
        handle_sandbox_image($adminId);
        break;
    case 'sandbox-tts':
        handle_sandbox_tts($adminId);
        break;
    case 'scheduler-status':
        handle_scheduler_status();
        break;
    case 'scheduler-save':
        handle_scheduler_save();
        break;
    case 'scheduler-run':
        handle_scheduler_run($adminId);
        break;
    case 'usage-summary':
        handle_usage_summary();
        break;
    case 'error-retry':
        handle_error_retry($adminId);
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

        ai_usage_register_text($message, $replyText, $settings['models']['text'] ?? '');

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'reply' => $replyText,
            'history' => $history,
        ]);
    } catch (Throwable $exception) {
        ai_error_log_append(ai_classify_error($exception->getMessage()), $exception->getMessage(), [
            'action' => 'chat',
            'prompt' => $message,
        ]);
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

function handle_blog_generate(int $adminId): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Content-Type: application/json');
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Use POST to generate blogs.']);
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

    $title = trim((string) ($payload['title'] ?? ''));
    $brief = trim((string) ($payload['brief'] ?? ''));
    $keywords = trim((string) ($payload['keywords'] ?? ''));
    $tone = trim((string) ($payload['tone'] ?? ''));

    if ($title === '' || $brief === '') {
        header('Content-Type: application/json');
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Title and brief are required to generate a blog.']);
        return;
    }

    $settings = ai_settings_load();
    if (!($settings['enabled'] ?? false)) {
        header('Content-Type: application/json');
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'AI is disabled. Enable Gemini in settings.']);
        return;
    }

    if (($settings['api_key'] ?? '') === '') {
        header('Content-Type: application/json');
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Gemini API key is missing.']);
        return;
    }

    ignore_user_abort(true);
    @set_time_limit(0);
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');

    while (ob_get_level() > 0) {
        ob_end_flush();
    }

    $promptPieces = [];
    $promptPieces[] = 'Write a structured, SEO-friendly blog post for Dakshayani Energy.';
    $promptPieces[] = 'Title: ' . $title;
    $promptPieces[] = 'Brief: ' . $brief;
    if ($keywords !== '') {
        $promptPieces[] = 'Incorporate these keywords naturally: ' . $keywords . '.';
    }
    if ($tone !== '') {
        $promptPieces[] = 'Adopt a ' . $tone . ' tone.';
    }
    $promptPieces[] = 'Return plain paragraphs with headings using Markdown style (#, ##) where appropriate.';
    $promptPieces[] = 'Avoid preambles about being an AI.';

    try {
        $blogText = ai_gemini_generate_text($settings, implode("\n", $promptPieces));
    } catch (Throwable $exception) {
        sse_emit('error', ['message' => $exception->getMessage()]);
        return;
    }

    $paragraphs = ai_normalize_paragraphs_from_text($blogText);
    if (empty($paragraphs)) {
        sse_emit('error', ['message' => 'Gemini returned empty content.']);
        return;
    }

    foreach ($paragraphs as $paragraph) {
        sse_emit('chunk', ['paragraph' => $paragraph]);
    }

    $imageInfo = null;
    try {
        $imagePromptParts = [$title];
        if ($keywords !== '') {
            $imagePromptParts[] = $keywords;
        }
        $imagePromptParts[] = 'High-quality editorial illustration for renewable energy blog.';
        $imageInfo = ai_gemini_generate_image($settings, implode(' · ', $imagePromptParts));
    } catch (Throwable $exception) {
        $imageInfo = null;
    }

    $draft = ai_blog_draft_load($adminId);
    $draft['title'] = $title;
    $draft['brief'] = $brief;
    $draft['keywords'] = $keywords;
    $draft['tone'] = $tone;
    $draft['paragraphs'] = $paragraphs;
    if ($imageInfo) {
        $draft['coverImage'] = $imageInfo['path'];
        $draft['coverImageAlt'] = 'AI generated illustration for ' . $title;
    }
    $draft['updatedAt'] = ai_timestamp();
    try {
        ai_blog_draft_save($adminId, $draft);
    } catch (Throwable $exception) {
        // Silent failure to avoid interrupting streaming.
    }

    $payload = [
        'success' => true,
        'paragraphs' => $paragraphs,
        'excerpt' => ai_build_excerpt_from_paragraphs($paragraphs, $brief),
    ];
    if ($imageInfo) {
        $payload['image'] = $imageInfo;
        $payload['image']['alt'] = 'AI generated illustration for ' . $title;
    }

    sse_emit('done', $payload);
}

function handle_blog_autosave(int $adminId): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Content-Type: application/json');
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Use POST to save drafts.']);
        return;
    }

    $payload = decode_json_body();
    $draft = [
        'title' => trim((string) ($payload['title'] ?? '')),
        'brief' => trim((string) ($payload['brief'] ?? '')),
        'keywords' => trim((string) ($payload['keywords'] ?? '')),
        'tone' => trim((string) ($payload['tone'] ?? '')),
        'paragraphs' => ai_normalize_paragraphs($payload['paragraphs'] ?? []),
        'coverImage' => trim((string) ($payload['coverImage'] ?? '')),
        'coverImageAlt' => trim((string) ($payload['coverImageAlt'] ?? '')),
        'coverPrompt' => trim((string) ($payload['coverPrompt'] ?? '')),
    ];

    if (isset($payload['postId']) && (int) $payload['postId'] > 0) {
        $draft['postId'] = (int) $payload['postId'];
    }

    $draft['updatedAt'] = ai_timestamp();

    try {
        ai_blog_draft_save($adminId, $draft);
    } catch (Throwable $exception) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $exception->getMessage()]);
        return;
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'savedAt' => $draft['updatedAt']]);
}

function handle_blog_load_draft(int $adminId): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Content-Type: application/json');
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Use GET to load drafts.']);
        return;
    }

    $draft = ai_blog_draft_load($adminId);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'draft' => $draft]);
}

function handle_blog_publish(int $adminId): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Content-Type: application/json');
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Use POST to publish blogs.']);
        return;
    }

    $payload = decode_json_body();
    $title = trim((string) ($payload['title'] ?? ''));
    $brief = trim((string) ($payload['brief'] ?? ''));
    $keywords = trim((string) ($payload['keywords'] ?? ''));
    $tone = trim((string) ($payload['tone'] ?? ''));
    $paragraphs = ai_normalize_paragraphs($payload['paragraphs'] ?? []);
    $coverImage = trim((string) ($payload['coverImage'] ?? ''));
    $coverImageAlt = trim((string) ($payload['coverImageAlt'] ?? ''));
    $postId = isset($payload['postId']) ? (int) $payload['postId'] : 0;

    if ($title === '' || empty($paragraphs)) {
        header('Content-Type: application/json');
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Title and generated content are required before publishing.']);
        return;
    }

    $settings = ai_settings_load();
    if (!($settings['enabled'] ?? false)) {
        header('Content-Type: application/json');
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'AI is disabled. Enable Gemini in settings.']);
        return;
    }

    $db = get_db();

    $bodyHtml = ai_paragraphs_to_html($paragraphs);
    $excerpt = ai_build_excerpt_from_paragraphs($paragraphs, $brief);
    $tags = ai_keywords_to_tags($keywords);

    try {
        $saved = blog_save_post($db, [
            'id' => $postId > 0 ? $postId : null,
            'title' => $title,
            'excerpt' => $brief !== '' ? $brief : $excerpt,
            'body' => $bodyHtml,
            'coverImage' => $coverImage,
            'coverImageAlt' => $coverImageAlt,
            'coverPrompt' => $brief,
            'authorName' => '',
            'status' => 'published',
            'tags' => $tags,
            'slug' => $payload['slug'] ?? '',
        ], $adminId);
    } catch (Throwable $exception) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $exception->getMessage()]);
        return;
    }

    ai_blog_draft_clear($adminId);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'postId' => (int) ($saved['id'] ?? 0),
        'slug' => $saved['slug'] ?? '',
    ]);
}

function handle_blog_regenerate_paragraph(int $adminId): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Content-Type: application/json');
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Use POST to regenerate paragraphs.']);
        return;
    }

    $payload = decode_json_body();
    $paragraph = trim((string) ($payload['paragraph'] ?? ''));
    $context = trim((string) ($payload['context'] ?? ''));
    $title = trim((string) ($payload['title'] ?? ''));
    $tone = trim((string) ($payload['tone'] ?? ''));

    if ($paragraph === '') {
        header('Content-Type: application/json');
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Paragraph content is required.']);
        return;
    }

    $settings = ai_settings_load();
    if (!($settings['enabled'] ?? false)) {
        header('Content-Type: application/json');
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'AI is disabled. Enable Gemini in settings.']);
        return;
    }

    $prompt = 'Rewrite the following paragraph for a Dakshayani Energy blog post.';
    if ($title !== '') {
        $prompt .= "\nBlog title: " . $title;
    }
    if ($tone !== '') {
        $prompt .= "\nTone: " . $tone;
    }
    if ($context !== '') {
        $prompt .= "\nArticle context: " . $context;
    }
    $prompt .= "\nParagraph:\n" . $paragraph;
    $prompt .= "\nReturn a refined paragraph only.";

    try {
        $text = ai_gemini_generate_text($settings, $prompt);
    } catch (Throwable $exception) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $exception->getMessage()]);
        return;
    }

    $clean = trim($text);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'paragraph' => $clean]);
}

function handle_image_generate(int $adminId): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Content-Type: application/json');
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Use POST to generate images.']);
        return;
    }

    $payload = decode_json_body();
    $prompt = trim((string) ($payload['prompt'] ?? ''));

    if ($prompt === '') {
        header('Content-Type: application/json');
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Prompt is required to generate an image.']);
        return;
    }

    $settings = ai_settings_load();
    if (!($settings['enabled'] ?? false)) {
        header('Content-Type: application/json');
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'AI is disabled. Enable Gemini in settings.']);
        return;
    }

    try {
        $image = ai_gemini_generate_image($settings, $prompt);
        ai_usage_register_image(['action' => 'blog-image']);
    } catch (Throwable $exception) {
        ai_error_log_append(ai_classify_error($exception->getMessage()), $exception->getMessage(), [
            'action' => 'blog-image',
            'prompt' => $prompt,
        ]);
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $exception->getMessage()]);
        return;
    }

    $draft = ai_blog_draft_load($adminId);
    $draft['coverImage'] = $image['path'];
    $draft['coverImageAlt'] = $payload['alt'] ?? ('AI generated visual for ' . ($draft['title'] ?? 'blog post'));
    $draft['updatedAt'] = ai_timestamp();
    try {
        ai_blog_draft_save($adminId, $draft);
    } catch (Throwable $exception) {
        // ignore
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'image' => $image]);
}

function handle_tts_generate(int $adminId): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Content-Type: application/json');
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Use POST to generate audio.']);
        return;
    }

    $payload = decode_json_body();
    $text = trim((string) ($payload['text'] ?? ''));
    $format = trim((string) ($payload['format'] ?? 'mp3'));

    if ($text === '') {
        header('Content-Type: application/json');
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Text is required to generate audio.']);
        return;
    }

    $settings = ai_settings_load();
    if (!($settings['enabled'] ?? false)) {
        header('Content-Type: application/json');
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'AI is disabled. Enable Gemini in settings.']);
        return;
    }

    try {
        $audio = ai_gemini_generate_tts($settings, $text, $format);
        ai_usage_register_tts($text, ['action' => 'blog-tts']);
    } catch (Throwable $exception) {
        ai_error_log_append(ai_classify_error($exception->getMessage()), $exception->getMessage(), [
            'action' => 'blog-tts',
            'text' => $text,
        ]);
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $exception->getMessage()]);
        return;
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'audio' => $audio]);
}

function decode_json_body(): array
{
    $body = file_get_contents('php://input');
    if (!is_string($body) || trim($body) === '') {
        return [];
    }

    try {
        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        return [];
    }

    return is_array($decoded) ? $decoded : [];
}

function ai_normalize_paragraphs($value): array
{
    $paragraphs = [];
    if (is_array($value)) {
        foreach ($value as $item) {
            $paragraph = trim((string) $item);
            if ($paragraph !== '') {
                $paragraphs[] = $paragraph;
            }
        }
    }

    return $paragraphs;
}

function ai_normalize_paragraphs_from_text(string $text): array
{
    $parts = preg_split('/\n{2,}/', trim($text)) ?: [];
    $result = [];
    foreach ($parts as $part) {
        $clean = trim(preg_replace('/\s+/', ' ', $part) ?? '');
        if ($clean !== '') {
            $result[] = $clean;
        }
    }

    return $result;
}

function ai_build_excerpt_from_paragraphs(array $paragraphs, string $fallback = ''): string
{
    $source = $fallback !== '' ? $fallback : implode(' ', $paragraphs);
    $source = preg_replace('/\s+/', ' ', trim($source) ?? '');
    if ($source === '') {
        return '';
    }

    $limit = 220;
    if (mb_strlen($source) <= $limit) {
        return $source;
    }

    $truncated = mb_substr($source, 0, $limit);
    $lastSpace = mb_strrpos($truncated, ' ');
    if ($lastSpace !== false) {
        $truncated = mb_substr($truncated, 0, $lastSpace);
    }

    return rtrim($truncated) . '…';
}

function ai_paragraphs_to_html(array $paragraphs): string
{
    $htmlParts = [];
    foreach ($paragraphs as $paragraph) {
        if (preg_match('/^(#{1,6})\s+(.+)$/', $paragraph, $matches)) {
            $level = min(6, max(1, strlen($matches[1])));
            $text = trim($matches[2]);
            $htmlParts[] = sprintf('<h%d>%s</h%d>', $level, htmlspecialchars($text, ENT_QUOTES, 'UTF-8'), $level);
        } else {
            $htmlParts[] = '<p>' . nl2br(htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8')) . '</p>';
        }
    }

    return implode("\n", $htmlParts);
}

function ai_keywords_to_tags(string $keywords): array
{
    $parts = preg_split('/[\n,]+/', $keywords) ?: [];
    $tags = [];
    foreach ($parts as $part) {
        $tag = trim($part);
        if ($tag !== '') {
            $tags[] = $tag;
        }
    }

    return $tags;
}

function sse_emit(string $event, array $data): void
{
    $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        $encoded = '{}';
    }

    echo 'event: ' . $event . "\n";
    echo 'data: ' . $encoded . "\n\n";
    @ob_flush();
    flush();
}

function handle_sandbox_text(int $adminId): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Content-Type: application/json');
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Use POST for sandbox text requests.']);
        return;
    }

    $payload = decode_json_body();
    $prompt = trim((string) ($payload['prompt'] ?? ''));

    if ($prompt === '') {
        header('Content-Type: application/json');
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Prompt is required.']);
        return;
    }

    $settings = ai_settings_load();
    if (($settings['api_key'] ?? '') === '') {
        header('Content-Type: application/json');
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Gemini API key is missing in settings.']);
        return;
    }

    try {
        $text = ai_gemini_generate_text($settings, $prompt);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'text' => $text]);
    } catch (Throwable $exception) {
        ai_error_log_append(ai_classify_error($exception->getMessage()), $exception->getMessage(), [
            'action' => 'sandbox-text',
            'prompt' => $prompt,
        ]);
        header('Content-Type: application/json');
        http_response_code(502);
        echo json_encode(['success' => false, 'error' => $exception->getMessage()]);
    }
}

function handle_sandbox_image(int $adminId): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Content-Type: application/json');
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Use POST for sandbox image requests.']);
        return;
    }

    $payload = decode_json_body();
    $prompt = trim((string) ($payload['prompt'] ?? ''));

    if ($prompt === '') {
        header('Content-Type: application/json');
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Prompt is required.']);
        return;
    }

    $settings = ai_settings_load();
    if (($settings['api_key'] ?? '') === '') {
        header('Content-Type: application/json');
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Gemini API key is missing in settings.']);
        return;
    }

    try {
        $image = ai_gemini_generate_image($settings, $prompt);
        ai_usage_register_image(['action' => 'sandbox-image']);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'image' => $image]);
    } catch (Throwable $exception) {
        ai_error_log_append(ai_classify_error($exception->getMessage()), $exception->getMessage(), [
            'action' => 'sandbox-image',
            'prompt' => $prompt,
        ]);
        header('Content-Type: application/json');
        http_response_code(502);
        echo json_encode(['success' => false, 'error' => $exception->getMessage()]);
    }
}

function handle_sandbox_tts(int $adminId): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Content-Type: application/json');
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Use POST for sandbox audio requests.']);
        return;
    }

    $payload = decode_json_body();
    $text = trim((string) ($payload['text'] ?? ''));
    $format = trim((string) ($payload['format'] ?? 'mp3'));

    if ($text === '') {
        header('Content-Type: application/json');
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Text is required.']);
        return;
    }

    $settings = ai_settings_load();
    if (($settings['api_key'] ?? '') === '') {
        header('Content-Type: application/json');
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Gemini API key is missing in settings.']);
        return;
    }

    try {
        $audio = ai_gemini_generate_tts($settings, $text, $format);
        ai_usage_register_tts($text, ['action' => 'sandbox-tts']);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'audio' => $audio]);
    } catch (Throwable $exception) {
        ai_error_log_append(ai_classify_error($exception->getMessage()), $exception->getMessage(), [
            'action' => 'sandbox-tts',
            'text' => $text,
        ]);
        header('Content-Type: application/json');
        http_response_code(502);
        echo json_encode(['success' => false, 'error' => $exception->getMessage()]);
    }
}

function handle_scheduler_status(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Content-Type: application/json');
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Use GET to fetch scheduler status.']);
        return;
    }

    $settings = ai_scheduler_settings_load();
    $logs = array_reverse(ai_scheduler_logs_load());

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'settings' => $settings,
        'logs' => $logs,
    ]);
}

function handle_scheduler_save(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Content-Type: application/json');
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Use POST to update scheduler settings.']);
        return;
    }

    $payload = decode_json_body();
    $topic = trim((string) ($payload['topic'] ?? ''));
    $frequency = trim((string) ($payload['frequency'] ?? 'weekly'));
    $enabled = isset($payload['enabled']) ? (bool) $payload['enabled'] : false;

    try {
        $settings = ai_scheduler_settings_save([
            'topic' => $topic,
            'frequency' => $frequency,
            'enabled' => $enabled,
        ]);
    } catch (Throwable $exception) {
        ai_error_log_append('API failure', $exception->getMessage(), ['action' => 'scheduler-save']);
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $exception->getMessage()]);
        return;
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'settings' => $settings]);
}

function handle_scheduler_run(int $adminId): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Content-Type: application/json');
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Use POST to trigger the scheduler.']);
        return;
    }

    $payload = decode_json_body();
    $topicOverride = isset($payload['topic']) ? trim((string) $payload['topic']) : null;

    try {
        $result = perform_scheduler_run($adminId, $topicOverride);
        header('Content-Type: application/json');
        echo json_encode(['success' => true] + $result);
    } catch (Throwable $exception) {
        ai_error_log_append(ai_classify_error($exception->getMessage()), $exception->getMessage(), [
            'action' => 'scheduler-run',
        ]);
        header('Content-Type: application/json');
        http_response_code(502);
        echo json_encode(['success' => false, 'error' => $exception->getMessage()]);
    }
}

function perform_scheduler_run(int $adminId, ?string $topicOverride = null): array
{
    $settings = ai_settings_load();
    if (($settings['api_key'] ?? '') === '') {
        throw new RuntimeException('Gemini API key is missing.');
    }

    $scheduler = ai_scheduler_settings_load();
    $topic = $topicOverride !== null && $topicOverride !== '' ? $topicOverride : ($scheduler['topic'] ?? '');
    $topic = trim($topic);
    if ($topic === '') {
        throw new RuntimeException('Scheduler topic is empty.');
    }

    $frequency = $scheduler['frequency'] ?? 'weekly';

    $prompt = <<<PROMPT
You are the editorial voice of Dakshayani Energy. Prepare a complete blog draft focused on "{$topic}".
Respond using this structure:
Title: <concise headline>
Summary: <two sentences>
Body:
<5-7 markdown paragraphs with headings where suitable>
Keep the tone informative, optimistic, and tailored to clean energy professionals in India.
PROMPT;

    $blogText = ai_gemini_generate_text($settings, $prompt);

    $lines = preg_split('/\r?\n/', trim($blogText)) ?: [];
    $title = $topic;
    $summary = '';
    $bodyLines = [];
    foreach ($lines as $line) {
        if ($title === $topic && stripos($line, 'title:') === 0) {
            $titleCandidate = trim(substr($line, strlen('title:')));
            if ($titleCandidate !== '') {
                $title = $titleCandidate;
                continue;
            }
        }
        if ($summary === '' && stripos($line, 'summary:') === 0) {
            $summaryCandidate = trim(substr($line, strlen('summary:')));
            if ($summaryCandidate !== '') {
                $summary = $summaryCandidate;
                continue;
            }
        }
        if (stripos($line, 'body:') === 0) {
            $bodyLines[] = trim(substr($line, strlen('body:')));
            continue;
        }
        $bodyLines[] = $line;
    }

    $bodyText = trim(implode("\n", $bodyLines));
    $paragraphs = ai_normalize_paragraphs_from_text($bodyText !== '' ? $bodyText : $blogText);
    if (empty($paragraphs)) {
        throw new RuntimeException('Gemini returned empty content.');
    }

    $images = [];
    $imageCount = random_int(1, 3);
    for ($i = 0; $i < $imageCount; $i++) {
        try {
            $snippet = $paragraphs[$i % count($paragraphs)] ?? $topic;
            $imagePrompt = sprintf('%s – editorial illustration %d. %s', $topic, $i + 1, $snippet);
            $image = ai_gemini_generate_image($settings, $imagePrompt);
            ai_usage_register_image(['action' => 'scheduler-image']);
            $images[] = $image;
        } catch (Throwable $exception) {
            ai_error_log_append(ai_classify_error($exception->getMessage()), $exception->getMessage(), [
                'action' => 'scheduler-image',
                'prompt' => $topic,
            ]);
        }
    }

    $summaryPrompt = "Craft a 45-second spoken summary for Dakshayani Energy on the topic: {$topic}. Highlight the core insights in warm, confident language. Source material: " . implode(' ', array_slice($paragraphs, 0, 5));
    $summaryText = ai_gemini_generate_text($settings, $summaryPrompt);
    $summaryText = trim(mb_substr($summaryText, 0, 800));
    if ($summaryText === '') {
        throw new RuntimeException('Gemini returned an empty summary.');
    }

    $audio = ai_gemini_generate_tts($settings, $summaryText, 'mp3');
    ai_usage_register_tts($summaryText, ['action' => 'scheduler-tts']);

    $draftPath = ai_scheduler_store_generated_post([
        'topic' => $topic,
        'title' => $title,
        'summary' => $summary !== '' ? $summary : ai_build_excerpt_from_paragraphs($paragraphs),
        'paragraphs' => $paragraphs,
        'images' => $images,
        'audio' => $audio,
        'frequency' => $frequency,
        'source' => 'automation-scheduler',
    ]);

    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
    $updatedSettings = ai_scheduler_settings_save([
        'topic' => $scheduler['topic'] ?? $topic,
        'frequency' => $frequency,
        'enabled' => (bool) ($scheduler['enabled'] ?? false),
        'last_run' => $now->format(DateTimeInterface::ATOM),
    ]);

    ai_scheduler_logs_append([
        'topic' => $topic,
        'frequency' => $frequency,
        'draft' => $draftPath,
        'title' => $title,
        'summary' => $summaryText,
        'images' => $images,
        'audio' => $audio,
    ]);

    return [
        'draft' => $draftPath,
        'title' => $title,
        'summary' => $summaryText,
        'paragraphs' => $paragraphs,
        'images' => $images,
        'audio' => $audio,
        'scheduler' => $updatedSettings,
    ];
}

function handle_usage_summary(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Content-Type: application/json');
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Use GET to fetch usage summary.']);
        return;
    }

    $usage = ai_usage_summary();
    $errors = array_reverse(ai_error_log_load());

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'usage' => $usage,
        'errors' => $errors,
    ]);
}

function handle_error_retry(int $adminId): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Content-Type: application/json');
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Use POST to retry actions.']);
        return;
    }

    $errors = ai_error_log_load();
    if (!$errors) {
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'No errors recorded.']);
        return;
    }

    $last = end($errors);
    $context = is_array($last['context'] ?? null) ? $last['context'] : [];
    $action = (string) ($context['action'] ?? '');

    try {
        switch ($action) {
            case 'sandbox-text':
                $prompt = trim((string) ($context['prompt'] ?? ''));
                if ($prompt === '') {
                    throw new RuntimeException('No prompt captured for retry.');
                }
                $settings = ai_settings_load();
                $text = ai_gemini_generate_text($settings, $prompt);
                $payload = ['type' => 'sandbox-text', 'text' => $text];
                break;
            case 'sandbox-image':
                $prompt = trim((string) ($context['prompt'] ?? ''));
                if ($prompt === '') {
                    throw new RuntimeException('No prompt captured for retry.');
                }
                $settings = ai_settings_load();
                $image = ai_gemini_generate_image($settings, $prompt);
                ai_usage_register_image(['action' => 'sandbox-image']);
                $payload = ['type' => 'sandbox-image', 'image' => $image];
                break;
            case 'sandbox-tts':
                $textInput = trim((string) ($context['text'] ?? ''));
                if ($textInput === '') {
                    throw new RuntimeException('No text captured for retry.');
                }
                $settings = ai_settings_load();
                $audio = ai_gemini_generate_tts($settings, $textInput, 'mp3');
                ai_usage_register_tts($textInput, ['action' => 'sandbox-tts']);
                $payload = ['type' => 'sandbox-tts', 'audio' => $audio];
                break;
            case 'scheduler-run':
                $payload = ['type' => 'scheduler-run'] + perform_scheduler_run($adminId, null);
                break;
            default:
                throw new RuntimeException('Last error cannot be retried automatically.');
        }
    } catch (Throwable $exception) {
        ai_error_log_append(ai_classify_error($exception->getMessage()), $exception->getMessage(), [
            'action' => 'retry-' . $action,
        ]);
        header('Content-Type: application/json');
        http_response_code(502);
        echo json_encode(['success' => false, 'error' => $exception->getMessage()]);
        return;
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'payload' => $payload]);
}

function ai_classify_error(string $message): string
{
    $normalized = strtolower($message);
    if (str_contains($normalized, 'empty')) {
        return 'Empty response';
    }
    if (str_contains($normalized, 'timeout') || str_contains($normalized, 'timed out')) {
        return 'Timeout';
    }
    if (str_contains($normalized, '429') || str_contains($normalized, 'rate')) {
        return 'Rate limit';
    }

    return 'API failure';
}
