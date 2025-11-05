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

function ai_blog_draft_path(int $adminId): string
{
    $suffix = $adminId > 0 ? (string) $adminId : 'shared';
    return ai_storage_dir() . '/blog_' . $suffix . '.json';
}

function ai_tts_history_path(int $adminId): string
{
    $suffix = $adminId > 0 ? (string) $adminId : 'shared';
    return ai_storage_dir() . '/tts_' . $suffix . '.json';
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

function ai_blog_default_state(): array
{
    return [
        'title' => '',
        'brief' => '',
        'keywords' => '',
        'tone' => 'Informative',
        'content' => '',
        'outline' => [],
        'summary' => '',
        'post_id' => null,
        'images' => [],
        'cover_image' => '',
        'cover_image_alt' => '',
        'last_saved_at' => null,
    ];
}

function ai_sanitise_blog_draft(array $draft): array
{
    $defaults = ai_blog_default_state();

    $outline = [];
    if (!empty($draft['outline']) && is_array($draft['outline'])) {
        foreach ($draft['outline'] as $item) {
            if (is_string($item)) {
                $outline[] = trim($item);
            }
        }
    }

    $images = [];
    if (!empty($draft['images']) && is_array($draft['images'])) {
        foreach ($draft['images'] as $image) {
            if (!is_array($image)) {
                continue;
            }
            $id = isset($image['id']) && is_string($image['id']) ? trim($image['id']) : '';
            $prompt = isset($image['prompt']) && is_string($image['prompt']) ? trim($image['prompt']) : '';
            $data = isset($image['data']) && is_string($image['data']) ? $image['data'] : '';
            $attached = !empty($image['attached']);
            $createdAt = isset($image['created_at']) && is_string($image['created_at']) ? $image['created_at'] : ai_now_iso();
            if ($id === '' || $data === '') {
                continue;
            }
            $images[] = [
                'id' => $id,
                'prompt' => $prompt,
                'data' => $data,
                'attached' => $attached,
                'created_at' => $createdAt,
            ];
        }
    }

    return [
        'title' => isset($draft['title']) && is_string($draft['title']) ? trim($draft['title']) : $defaults['title'],
        'brief' => isset($draft['brief']) && is_string($draft['brief']) ? trim($draft['brief']) : $defaults['brief'],
        'keywords' => isset($draft['keywords']) && is_string($draft['keywords']) ? trim($draft['keywords']) : $defaults['keywords'],
        'tone' => isset($draft['tone']) && is_string($draft['tone']) ? trim($draft['tone']) : $defaults['tone'],
        'content' => isset($draft['content']) && is_string($draft['content']) ? trim($draft['content']) : $defaults['content'],
        'outline' => $outline,
        'summary' => isset($draft['summary']) && is_string($draft['summary']) ? trim($draft['summary']) : $defaults['summary'],
        'post_id' => isset($draft['post_id']) && $draft['post_id'] !== null ? (int) $draft['post_id'] : null,
        'images' => $images,
        'cover_image' => isset($draft['cover_image']) && is_string($draft['cover_image']) ? trim($draft['cover_image']) : '',
        'cover_image_alt' => isset($draft['cover_image_alt']) && is_string($draft['cover_image_alt']) ? trim($draft['cover_image_alt']) : '',
        'last_saved_at' => isset($draft['last_saved_at']) && is_string($draft['last_saved_at']) ? $draft['last_saved_at'] : $defaults['last_saved_at'],
    ];
}

function ai_load_blog_draft(int $adminId): array
{
    $path = ai_blog_draft_path($adminId);
    if (!is_file($path)) {
        return ai_blog_default_state();
    }

    $contents = file_get_contents($path);
    if ($contents === false || trim($contents) === '') {
        return ai_blog_default_state();
    }

    try {
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        error_log('ai_load_blog_draft: decode failed: ' . $exception->getMessage());
        return ai_blog_default_state();
    }

    if (!is_array($decoded)) {
        return ai_blog_default_state();
    }

    return ai_sanitise_blog_draft($decoded);
}

function ai_save_blog_draft(int $adminId, array $draft): void
{
    $normalised = ai_sanitise_blog_draft($draft);
    $normalised['last_saved_at'] = ai_now_iso();

    $encoded = json_encode($normalised, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($encoded === false) {
        throw new RuntimeException('Failed to encode blog draft.');
    }

    if (file_put_contents(ai_blog_draft_path($adminId), $encoded, LOCK_EX) === false) {
        throw new RuntimeException('Unable to save blog draft.');
    }
}

function ai_load_tts_history(int $adminId): array
{
    $path = ai_tts_history_path($adminId);
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
        error_log('ai_load_tts_history: decode failed: ' . $exception->getMessage());
        return [];
    }

    if (!is_array($decoded)) {
        return [];
    }

    $entries = [];
    foreach ($decoded as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $id = isset($entry['id']) && is_string($entry['id']) ? trim($entry['id']) : '';
        $text = isset($entry['text']) && is_string($entry['text']) ? trim($entry['text']) : '';
        $data = isset($entry['data']) && is_string($entry['data']) ? $entry['data'] : '';
        $format = isset($entry['format']) && is_string($entry['format']) ? trim($entry['format']) : 'wav';
        $createdAt = isset($entry['created_at']) && is_string($entry['created_at']) ? $entry['created_at'] : ai_now_iso();
        if ($id === '' || $data === '') {
            continue;
        }
        $entries[] = [
            'id' => $id,
            'text' => $text,
            'data' => $data,
            'format' => $format,
            'created_at' => $createdAt,
        ];
    }

    return $entries;
}

function ai_save_tts_history(int $adminId, array $entries): void
{
    $normalised = [];
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $id = isset($entry['id']) && is_string($entry['id']) ? trim($entry['id']) : '';
        $text = isset($entry['text']) && is_string($entry['text']) ? trim($entry['text']) : '';
        $data = isset($entry['data']) && is_string($entry['data']) ? $entry['data'] : '';
        $format = isset($entry['format']) && is_string($entry['format']) ? trim($entry['format']) : 'wav';
        $createdAt = isset($entry['created_at']) && is_string($entry['created_at']) ? $entry['created_at'] : ai_now_iso();
        if ($id === '' || $data === '') {
            continue;
        }
        $normalised[] = [
            'id' => $id,
            'text' => $text,
            'data' => $data,
            'format' => $format,
            'created_at' => $createdAt,
        ];
    }

    $maxEntries = 10;
    if (count($normalised) > $maxEntries) {
        $normalised = array_slice($normalised, -$maxEntries);
    }

    $encoded = json_encode($normalised, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($encoded === false) {
        throw new RuntimeException('Failed to encode TTS history.');
    }

    if (file_put_contents(ai_tts_history_path($adminId), $encoded, LOCK_EX) === false) {
        throw new RuntimeException('Unable to save TTS history.');
    }
}

function ai_blog_keywords_to_array(string $keywords): array
{
    $parts = preg_split('/[\n,]+/', $keywords) ?: [];
    $result = [];
    foreach ($parts as $part) {
        $value = trim((string) $part);
        if ($value !== '') {
            $result[] = $value;
        }
    }

    return $result;
}

function ai_blog_outline_from_inputs(string $title, string $brief, array $keywords): array
{
    $outline = [];
    if ($title !== '') {
        $outline[] = 'Introduction: ' . $title;
    }
    if ($brief !== '') {
        $outline[] = 'Context: ' . substr($brief, 0, 80) . (strlen($brief) > 80 ? '…' : '');
    }
    foreach ($keywords as $keyword) {
        $outline[] = 'Key point: ' . $keyword;
    }
    if ($title !== '') {
        $outline[] = 'Conclusion: Next steps for ' . $title;
    }

    return array_slice($outline, 0, 8);
}

function ai_blog_generate_body(string $title, string $brief, array $keywords, string $tone): array
{
    $toneLabel = $tone !== '' ? $tone : 'Informative';
    $intro = sprintf("# %s\n\n%s tone overview: %s\n", $title !== '' ? $title : 'AI Generated Blog', $toneLabel, $brief !== '' ? $brief : 'A concise update for Dentweb readers.');

    $sections = [];
    $sectionKeywords = $keywords ?: ['highlights', 'insights', 'call to action'];
    foreach ($sectionKeywords as $keyword) {
        $heading = '## ' . ucfirst($keyword);
        $sections[] = $heading . "\n" . '• Insight: ' . ucfirst($keyword) . ' remains central to our solar roadmap.' . "\n" . '• Impact: Teams can translate this into actionable site improvements.' . "\n";
    }

    $conclusion = "## Next steps\n" . 'Summarise the recommended follow-ups and encourage readers to reach out for personalised consultations.';

    $body = trim($intro . '\n' . implode("\n", $sections) . "\n\n" . $conclusion);
    $summary = $brief !== '' ? $brief : 'Dentweb insights on solar adoption and customer engagement.';

    return [
        'body' => $body,
        'summary' => $summary,
    ];
}

function ai_blog_plain_to_html(string $content): string
{
    $lines = preg_split('/\r?\n/', trim($content));
    $html = [];
    $buffer = [];
    $listOpen = false;

    $flushParagraph = static function () use (&$buffer, &$html, &$listOpen): void {
        if ($listOpen) {
            $html[] = '</ul>';
            $listOpen = false;
        }
        if (empty($buffer)) {
            return;
        }
        $text = trim(implode(' ', $buffer));
        if ($text !== '') {
            $html[] = '<p>' . nl2br(htmlspecialchars($text, ENT_QUOTES), false) . '</p>';
        }
        $buffer = [];
    };

    foreach ($lines as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '') {
            $flushParagraph();
            continue;
        }

        if (preg_match('/^#{1,6}\s+(.+)$/', $trimmed, $matches)) {
            $flushParagraph();
            $level = min(6, max(1, strpos($trimmed, ' ')));
            $heading = trim((string) $matches[1]);
            $html[] = sprintf('<h%d>%s</h%d>', $level, htmlspecialchars($heading, ENT_QUOTES), $level);
            continue;
        }

        if (preg_match('/^[-•]\s+(.+)$/', $trimmed, $matches)) {
            if (!$listOpen) {
                $flushParagraph();
                $html[] = '<ul class="ai-generated-list">';
                $listOpen = true;
            }
            $html[] = '<li>' . htmlspecialchars(trim((string) $matches[1]), ENT_QUOTES) . '</li>';
            continue;
        }

        if ($listOpen) {
            $html[] = '</ul>';
            $listOpen = false;
        }

        $buffer[] = $trimmed;
    }

    $flushParagraph();

    if ($listOpen) {
        $html[] = '</ul>';
    }

    return implode('', $html);
}

function ai_blog_excerpt(string $content): string
{
    $plain = preg_replace('/\s+/', ' ', trim($content)) ?? '';
    if ($plain === '') {
        return '';
    }
    if (mb_strlen($plain) <= 200) {
        return $plain;
    }
    return rtrim(mb_substr($plain, 0, 197), ' .,;:-') . '…';
}

function ai_generate_image_svg(string $prompt, string $id): string
{
    $displayPrompt = htmlspecialchars(mb_substr($prompt, 0, 60), ENT_QUOTES);
    $bgColour = sprintf('#%02x%02x%02x', (strlen($id) * 47) % 200, (strlen($prompt) * 19) % 200, (strlen($prompt) * 29) % 200);
    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 400" role="img" aria-label="AI generated prompt preview">
  <defs>
    <linearGradient id="grad-$id" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="$bgColour" stop-opacity="0.9" />
      <stop offset="100%" stop-color="#1b2735" stop-opacity="0.95" />
    </linearGradient>
  </defs>
  <rect width="640" height="400" fill="url(#grad-$id)" rx="24" />
  <g fill="#ffffff" font-family="'Poppins', sans-serif" font-size="28" font-weight="600">
    <text x="40" y="120">Dentweb AI Concept</text>
  </g>
  <g fill="#ffffff" font-family="'Roboto Mono', monospace" font-size="20">
    <text x="40" y="200" style="white-space: pre-wrap">
      $displayPrompt
    </text>
  </g>
</svg>
SVG;

    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

function ai_generate_image_entry(string $prompt): array
{
    $id = uniqid('img', true);
    $data = ai_generate_image_svg($prompt !== '' ? $prompt : 'Dentweb solar concept', preg_replace('/[^A-Za-z0-9]/', '', $id));

    return [
        'id' => $id,
        'prompt' => $prompt,
        'data' => $data,
        'attached' => false,
        'created_at' => ai_now_iso(),
    ];
}

function ai_generate_silent_wav(float $seconds = 1.5, int $sampleRate = 16000): string
{
    $duration = max(1.0, min($seconds, 8.0));
    $samples = (int) ($sampleRate * $duration);
    $data = str_repeat("\x00\x00", $samples);

    $chunkSize = 36 + strlen($data);
    $byteRate = $sampleRate * 2;
    $blockAlign = 2;

    $header = 'RIFF'
        . pack('V', $chunkSize)
        . 'WAVEfmt '
        . pack('V', 16)
        . pack('v', 1)
        . pack('v', 1)
        . pack('V', $sampleRate)
        . pack('V', $byteRate)
        . pack('v', $blockAlign)
        . pack('v', 16)
        . 'data'
        . pack('V', strlen($data));

    return $header . $data;
}

function ai_blog_prepare_payload(array $draft, string $authorName): array
{
    $content = (string) ($draft['content'] ?? '');
    $title = trim((string) ($draft['title'] ?? ''));
    $brief = trim((string) ($draft['brief'] ?? ''));
    $keywords = ai_blog_keywords_to_array((string) ($draft['keywords'] ?? ''));
    $tone = trim((string) ($draft['tone'] ?? 'Informative'));

    if ($title === '' || $content === '') {
        throw new RuntimeException('Generate content before saving to the blog module.');
    }

    $html = ai_blog_plain_to_html($content);
    if ($html === '') {
        throw new RuntimeException('Generated content is empty.');
    }

    $excerpt = $brief !== '' ? $brief : ai_blog_excerpt($content);
    $slug = blog_slugify($title);

    $coverImage = isset($draft['cover_image']) && is_string($draft['cover_image']) ? trim($draft['cover_image']) : '';
    $coverAlt = isset($draft['cover_image_alt']) && is_string($draft['cover_image_alt']) ? trim($draft['cover_image_alt']) : '';

    if ($coverImage === '' && !empty($draft['images'])) {
        $attachedImage = null;
        foreach ($draft['images'] as $image) {
            if (!empty($image['attached']) && is_string($image['data'])) {
                $attachedImage = $image;
                break;
            }
        }
        if ($attachedImage) {
            $coverImage = $attachedImage['data'];
            $coverAlt = $attachedImage['prompt'] !== '' ? $attachedImage['prompt'] : 'AI generated concept';
        }
    }

    return [
        'id' => isset($draft['post_id']) && $draft['post_id'] ? (int) $draft['post_id'] : null,
        'title' => $title,
        'slug' => $slug,
        'excerpt' => $excerpt,
        'body' => $html,
        'authorName' => $authorName,
        'status' => 'draft',
        'tags' => $keywords,
        'coverImage' => $coverImage,
        'coverImageAlt' => $coverAlt,
        'coverPrompt' => $tone . ' ' . implode(' ', $keywords),
    ];
}

function ai_blog_update_post_id(array &$draft, array $savedPost): void
{
    if (!empty($savedPost['id'])) {
        $draft['post_id'] = (int) $savedPost['id'];
    }
    if (!empty($savedPost['coverImage'])) {
        $draft['cover_image'] = (string) $savedPost['coverImage'];
    }
    if (!empty($savedPost['coverImageAlt'])) {
        $draft['cover_image_alt'] = (string) $savedPost['coverImageAlt'];
    }
}

function ai_tts_entry(string $text, string $format, string $dataUri): array
{
    return [
        'id' => uniqid('tts', true),
        'text' => $text,
        'format' => $format,
        'data' => $dataUri,
        'created_at' => ai_now_iso(),
    ];
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

        case 'blog_generate':
            if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
                http_response_code(419);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Session expired. Refresh and try again.']);
                exit;
            }

            $settings = ai_load_settings();
            if (!ai_is_ai_ready($settings)) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'AI is offline. Enable it in settings to generate a blog.']);
                exit;
            }

            $title = isset($_POST['title']) && is_string($_POST['title']) ? trim($_POST['title']) : '';
            $brief = isset($_POST['brief']) && is_string($_POST['brief']) ? trim($_POST['brief']) : '';
            $keywordsRaw = isset($_POST['keywords']) && is_string($_POST['keywords']) ? trim($_POST['keywords']) : '';
            $tone = isset($_POST['tone']) && is_string($_POST['tone']) ? trim($_POST['tone']) : 'Informative';

            if ($title === '' && $brief === '') {
                http_response_code(422);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Add a title or brief to guide the blog generator.']);
                exit;
            }

            $keywords = ai_blog_keywords_to_array($keywordsRaw);
            $outline = ai_blog_outline_from_inputs($title, $brief, $keywords);
            $generated = ai_blog_generate_body($title, $brief, $keywords, $tone);

            $draft = ai_load_blog_draft($adminId);
            $draft['title'] = $title;
            $draft['brief'] = $brief;
            $draft['keywords'] = $keywordsRaw;
            $draft['tone'] = $tone;
            $draft['content'] = $generated['body'];
            $draft['summary'] = $generated['summary'];
            $draft['outline'] = $outline;

            ai_save_blog_draft($adminId, $draft);
            $draft = ai_load_blog_draft($adminId);

            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('X-Accel-Buffering: no');

            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            ob_implicit_flush(true);

            $meta = [
                'outline' => $outline,
                'summary' => $generated['summary'],
                'saved_at' => $draft['last_saved_at'],
            ];
            echo '__META__' . json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
            flush();

            $tokens = preg_split('/(\s+)/u', $generated['body'], -1, PREG_SPLIT_DELIM_CAPTURE);
            if (is_array($tokens)) {
                foreach ($tokens as $token) {
                    echo $token;
                    flush();
                    usleep(40000);
                }
            } else {
                echo $generated['body'];
            }
            exit;

        case 'blog_save_draft':
        case 'blog_autosave':
            if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
                http_response_code(419);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Session expired. Refresh and try again.']);
                exit;
            }

            $draft = ai_load_blog_draft($adminId);
            $draft['title'] = isset($_POST['title']) && is_string($_POST['title']) ? trim($_POST['title']) : $draft['title'];
            $draft['brief'] = isset($_POST['brief']) && is_string($_POST['brief']) ? trim($_POST['brief']) : $draft['brief'];
            $draft['keywords'] = isset($_POST['keywords']) && is_string($_POST['keywords']) ? trim($_POST['keywords']) : $draft['keywords'];
            $draft['tone'] = isset($_POST['tone']) && is_string($_POST['tone']) ? trim($_POST['tone']) : $draft['tone'];
            $draft['content'] = isset($_POST['content']) && is_string($_POST['content']) ? trim($_POST['content']) : $draft['content'];
            $draft['summary'] = isset($_POST['summary']) && is_string($_POST['summary']) ? trim($_POST['summary']) : $draft['summary'];

            ai_save_blog_draft($adminId, $draft);
            $draft = ai_load_blog_draft($adminId);

            $response = [
                'success' => true,
                'saved_at' => $draft['last_saved_at'],
                'post_id' => $draft['post_id'],
            ];

            if ($draft['title'] !== '' && $draft['content'] !== '' && $action === 'blog_save_draft') {
                try {
                    $payload = ai_blog_prepare_payload($draft, (string) ($admin['full_name'] ?? 'Administrator'));
                    $db = get_db();
                    $saved = blog_save_post($db, $payload, $adminId);
                    ai_blog_update_post_id($draft, $saved);
                    ai_save_blog_draft($adminId, $draft);
                    $draft = ai_load_blog_draft($adminId);
                    $response['post_id'] = $draft['post_id'];
                    $response['synced'] = true;
                    $response['message'] = 'Draft synced with the blog workspace.';
                } catch (Throwable $exception) {
                    $response['synced'] = false;
                    $response['message'] = $exception->getMessage();
                }
            }

            header('Content-Type: application/json');
            echo json_encode($response);
            exit;

        case 'blog_preview':
            if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
                http_response_code(419);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Session expired.']);
                exit;
            }

            $content = isset($_POST['content']) && is_string($_POST['content']) ? trim($_POST['content']) : '';
            $title = isset($_POST['title']) && is_string($_POST['title']) ? trim($_POST['title']) : 'Preview';

            if ($content === '') {
                http_response_code(422);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Generate content before previewing.']);
                exit;
            }

            $html = ai_blog_plain_to_html($content);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'title' => $title,
                'html' => $html,
            ]);
            exit;

        case 'blog_publish':
            if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
                http_response_code(419);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Session expired. Refresh and try again.']);
                exit;
            }

            $draft = ai_load_blog_draft($adminId);
            $draft['title'] = isset($_POST['title']) && is_string($_POST['title']) ? trim($_POST['title']) : $draft['title'];
            $draft['brief'] = isset($_POST['brief']) && is_string($_POST['brief']) ? trim($_POST['brief']) : $draft['brief'];
            $draft['keywords'] = isset($_POST['keywords']) && is_string($_POST['keywords']) ? trim($_POST['keywords']) : $draft['keywords'];
            $draft['tone'] = isset($_POST['tone']) && is_string($_POST['tone']) ? trim($_POST['tone']) : $draft['tone'];
            $draft['content'] = isset($_POST['content']) && is_string($_POST['content']) ? trim($_POST['content']) : $draft['content'];
            $draft['summary'] = isset($_POST['summary']) && is_string($_POST['summary']) ? trim($_POST['summary']) : $draft['summary'];

            ai_save_blog_draft($adminId, $draft);
            $draft = ai_load_blog_draft($adminId);

            try {
                $payload = ai_blog_prepare_payload($draft, (string) ($admin['full_name'] ?? 'Administrator'));
            } catch (Throwable $exception) {
                http_response_code(422);
                header('Content-Type: application/json');
                echo json_encode(['error' => $exception->getMessage()]);
                exit;
            }

            try {
                $db = get_db();
                $saved = blog_save_post($db, $payload, $adminId);
                $published = blog_publish_post($db, (int) $saved['id'], true, $adminId);
                ai_blog_update_post_id($draft, $published);
                ai_save_blog_draft($adminId, $draft);
                $draft = ai_load_blog_draft($adminId);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'post_id' => $draft['post_id'],
                    'message' => 'Post published to the blog module.',
                ]);
            } catch (Throwable $exception) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(['error' => $exception->getMessage()]);
            }
            exit;

        case 'image_generate':
            if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
                http_response_code(419);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Session expired.']);
                exit;
            }

            $settings = ai_load_settings();
            if (!ai_is_ai_ready($settings)) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'AI is offline. Enable it to generate images.']);
                exit;
            }

            $prompt = isset($_POST['prompt']) && is_string($_POST['prompt']) ? trim($_POST['prompt']) : '';
            if ($prompt === '') {
                http_response_code(422);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Add a prompt to generate an image.']);
                exit;
            }

            $draft = ai_load_blog_draft($adminId);
            $entry = ai_generate_image_entry($prompt);
            $draft['images'][] = $entry;
            if (count($draft['images']) > 12) {
                $draft['images'] = array_slice($draft['images'], -12);
            }
            ai_save_blog_draft($adminId, $draft);
            $draft = ai_load_blog_draft($adminId);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'image' => $entry,
            ]);
            exit;

        case 'image_attach':
            if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
                http_response_code(419);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Session expired.']);
                exit;
            }

            $imageId = isset($_POST['image_id']) && is_string($_POST['image_id']) ? trim($_POST['image_id']) : '';
            if ($imageId === '') {
                http_response_code(422);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Select an image to attach.']);
                exit;
            }

            $draft = ai_load_blog_draft($adminId);
            $found = false;
            foreach ($draft['images'] as &$image) {
                if (isset($image['id']) && $image['id'] === $imageId) {
                    $image['attached'] = true;
                    $draft['cover_image'] = $image['data'];
                    $draft['cover_image_alt'] = $image['prompt'] !== '' ? $image['prompt'] : 'AI generated concept';
                    $found = true;
                } else {
                    $image['attached'] = false;
                }
            }
            unset($image);

            if (!$found) {
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Image not found.']);
                exit;
            }

            ai_save_blog_draft($adminId, $draft);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'cover_image' => $draft['cover_image'],
                'cover_image_alt' => $draft['cover_image_alt'],
            ]);
            exit;

        case 'tts_generate':
            if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
                http_response_code(419);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Session expired.']);
                exit;
            }

            $settings = ai_load_settings();
            if (!ai_is_ai_ready($settings)) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'AI is offline. Enable it before generating audio.']);
                exit;
            }

            $text = isset($_POST['text']) && is_string($_POST['text']) ? trim($_POST['text']) : '';
            if ($text === '') {
                http_response_code(422);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Add text to generate speech.']);
                exit;
            }

            $length = max(1.5, min(strlen($text) / 30, 6));
            $wav = ai_generate_silent_wav((float) $length);
            $dataUri = 'data:audio/wav;base64,' . base64_encode($wav);
            $entry = ai_tts_entry($text, 'wav', $dataUri);

            $history = ai_load_tts_history($adminId);
            $history[] = $entry;
            ai_save_tts_history($adminId, $history);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'entry' => $entry,
            ]);
            exit;

        default:
            break;
    }
}

$settings = ai_load_settings();
$chatHistory = ai_load_chat_history($adminId);
$aiReady = ai_is_ai_ready($settings);
$blogDraft = ai_load_blog_draft($adminId);
$ttsHistory = ai_load_tts_history($adminId);

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
$blogTitleValue = $blogDraft['title'] ?? '';
$blogBriefValue = $blogDraft['brief'] ?? '';
$blogKeywordsValue = $blogDraft['keywords'] ?? '';
$blogToneValue = $blogDraft['tone'] ?? 'Informative';
$blogContentValue = $blogDraft['content'] ?? '';
$blogSummaryValue = $blogDraft['summary'] ?? '';
$blogSavedAt = isset($blogDraft['last_saved_at']) && $blogDraft['last_saved_at'] ? ai_chat_timestamp_display($blogDraft['last_saved_at']) : null;
$blogOutlineItems = isset($blogDraft['outline']) && is_array($blogDraft['outline']) ? $blogDraft['outline'] : [];
$blogPostId = isset($blogDraft['post_id']) && $blogDraft['post_id'] ? (int) $blogDraft['post_id'] : null;
$blogImages = isset($blogDraft['images']) && is_array($blogDraft['images']) ? $blogDraft['images'] : [];
$blogToneOptions = [
    'Informative' => 'Informative',
    'Conversational' => 'Conversational',
    'Professional' => 'Professional',
    'Friendly' => 'Friendly',
    'Persuasive' => 'Persuasive',
    'Enthusiastic' => 'Enthusiastic',
];

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

      <section id="ai-blog-generator" class="dashboard-section">
        <h2>Blog Generator</h2>
        <p class="dashboard-section-sub">Draft long-form posts, keep AI notes, and push ready stories straight into the blog workspace.</p>

        <?php if (!$aiReady): ?>
        <div class="dashboard-inline-status">
          <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
          <div>
            <strong>Generation paused</strong>
            <p>Enable AI and store an API key above to unlock automated drafting.</p>
          </div>
        </div>
        <?php endif; ?>

        <div class="ai-blog-grid" data-blog-ready="<?= $aiReady ? '1' : '0' ?>">
          <form id="ai-blog-form" class="dashboard-form ai-blog-card" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
            <input type="hidden" name="summary" value="<?= htmlspecialchars((string) $blogSummaryValue, ENT_QUOTES) ?>" data-blog-summary-input />
            <div class="dashboard-form-grid dashboard-form-grid--two">
              <label>
                <span>Blog title</span>
                <input type="text" name="title" value="<?= htmlspecialchars((string) $blogTitleValue, ENT_QUOTES) ?>" placeholder="e.g. Scaling rooftop solar for MSMEs" data-blog-title <?= $aiReady ? '' : 'disabled' ?> />
              </label>
              <label>
                <span>Tone</span>
                <select name="tone" data-blog-tone <?= $aiReady ? '' : 'disabled' ?>>
                  <?php foreach ($blogToneOptions as $value => $label): ?>
                  <option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>" <?= $blogToneValue === $value ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
            </div>
            <label>
              <span>Brief</span>
              <textarea name="brief" rows="3" placeholder="Key context or updates to cover" data-blog-brief <?= $aiReady ? '' : 'disabled' ?>><?= htmlspecialchars((string) $blogBriefValue, ENT_QUOTES) ?></textarea>
            </label>
            <label>
              <span>Keywords</span>
              <input type="text" name="keywords" value="<?= htmlspecialchars((string) $blogKeywordsValue, ENT_QUOTES) ?>" placeholder="Comma separated terms" data-blog-keywords <?= $aiReady ? '' : 'disabled' ?> />
            </label>
            <div class="ai-blog-actions">
              <button type="button" class="btn btn-primary" data-blog-generate <?= $aiReady ? '' : 'disabled' ?>>Generate blog</button>
              <button type="button" class="btn btn-secondary" data-blog-save <?= $aiReady ? '' : 'disabled' ?>>Save draft</button>
              <button type="button" class="btn btn-ghost" data-blog-autosave <?= $aiReady ? '' : 'disabled' ?>>Auto-save off</button>
              <button type="button" class="btn btn-link" data-blog-preview>Preview</button>
              <button type="button" class="btn btn-primary ai-blog-publish" data-blog-publish <?= $aiReady ? '' : 'disabled' ?>>Publish</button>
            </div>
            <div class="ai-blog-status" data-blog-status aria-live="polite">
              <p class="ai-blog-status__message" data-blog-status-message></p>
              <div class="ai-blog-status__meta">
                <?php if ($blogSavedAt): ?>
                <span data-blog-status-saved><i class="fa-regular fa-clock" aria-hidden="true"></i> Last saved <?= htmlspecialchars($blogSavedAt, ENT_QUOTES) ?></span>
                <?php endif; ?>
                <?php if ($blogPostId): ?>
                <span data-blog-status-post><i class="fa-solid fa-newspaper" aria-hidden="true"></i> Linked post #<?= (int) $blogPostId ?></span>
                <?php endif; ?>
              </div>
            </div>
            <label>
              <span>Generated draft</span>
              <textarea
                name="content"
                rows="16"
                placeholder="Generated content will stream here. You can edit before saving or publishing."
                data-blog-output
                <?= $aiReady ? '' : 'disabled' ?>
              ><?= htmlspecialchars((string) $blogContentValue, ENT_QUOTES) ?></textarea>
            </label>
          </form>

          <aside class="ai-blog-sidebar">
            <div class="ai-blog-outline">
              <h3>Outline</h3>
              <ol data-blog-outline>
                <?php if (!empty($blogOutlineItems)): ?>
                <?php foreach ($blogOutlineItems as $item): ?>
                <li><?= htmlspecialchars((string) $item, ENT_QUOTES) ?></li>
                <?php endforeach; ?>
                <?php else: ?>
                <li class="ai-blog-outline__placeholder">Outline will appear after the first generation.</li>
                <?php endif; ?>
              </ol>
            </div>
            <div class="ai-blog-images" data-blog-images>
              <div class="ai-blog-images__header">
                <h3>AI visuals</h3>
                <p>Attach a favourite concept as the blog cover.</p>
              </div>
              <?php if (empty($blogImages)): ?>
              <p class="ai-blog-images__empty">No AI visuals yet. Generate below to see concepts here.</p>
              <?php else: ?>
              <?php foreach ($blogImages as $image): ?>
              <figure class="ai-blog-image" data-image-id="<?= htmlspecialchars((string) $image['id'], ENT_QUOTES) ?>" <?= !empty($image['attached']) ? 'data-attached="1"' : '' ?>>
                <img src="<?= htmlspecialchars((string) $image['data'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($image['prompt'] !== '' ? $image['prompt'] : 'AI generated visual', ENT_QUOTES) ?>" loading="lazy" />
                <figcaption><?= htmlspecialchars($image['prompt'] !== '' ? $image['prompt'] : 'AI generated visual', ENT_QUOTES) ?></figcaption>
                <div class="ai-blog-image__actions">
                  <a class="btn btn-link" href="<?= htmlspecialchars((string) $image['data'], ENT_QUOTES) ?>" download="ai-visual-<?= htmlspecialchars((string) $image['id'], ENT_QUOTES) ?>.svg">Download</a>
                  <button type="button" class="btn btn-secondary" data-image-attach>Attach to blog</button>
                </div>
              </figure>
              <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </aside>
        </div>

        <div class="ai-blog-preview" data-blog-preview hidden>
          <div class="ai-blog-preview__inner">
            <header>
              <h3 data-blog-preview-title>Preview</h3>
              <button type="button" class="btn btn-link" data-blog-preview-close><i class="fa-solid fa-xmark" aria-hidden="true"></i> Close preview</button>
            </header>
            <article data-blog-preview-body></article>
          </div>
        </div>
      </section>

      <section id="ai-image-generator" class="dashboard-section">
        <h2>AI Image Generator</h2>
        <p class="dashboard-section-sub">Craft quick hero concepts that sit alongside your draft and attach the best fit instantly.</p>

        <form id="ai-image-form" class="dashboard-form ai-image-form" method="post" action="admin-ai-studio.php">
          <input type="hidden" name="action" value="image_generate" />
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
          <label>
            <span>Prompt</span>
            <textarea name="prompt" rows="3" placeholder="Describe the visual you want to see" data-image-prompt <?= $aiReady ? '' : 'disabled' ?>></textarea>
          </label>
          <div class="ai-image-actions">
            <button type="button" class="btn btn-ghost" data-image-autofill <?= $aiReady ? '' : 'disabled' ?>>Use blog summary</button>
            <button type="submit" class="btn btn-primary" data-image-generate <?= $aiReady ? '' : 'disabled' ?>>Generate image</button>
          </div>
          <p class="ai-image-status" data-image-status aria-live="polite"></p>
          <p class="ai-image-tip">New concepts appear in the Blog Generator sidebar for downloading or attaching.</p>
        </form>
      </section>

      <section id="ai-tts-generator" class="dashboard-section">
        <h2>TTS Generator</h2>
        <p class="dashboard-section-sub">Turn summaries into short audio clips for quick reviews or on-the-go listening.</p>

        <form id="ai-tts-form" class="dashboard-form ai-tts-form" method="post" action="admin-ai-studio.php">
          <input type="hidden" name="action" value="tts_generate" />
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
          <label>
            <span>Text for narration</span>
            <textarea name="text" rows="4" placeholder="Paste a highlight or summary to voice" data-tts-input <?= $aiReady ? '' : 'disabled' ?>><?= htmlspecialchars((string) $blogSummaryValue, ENT_QUOTES) ?></textarea>
          </label>
          <div class="ai-tts-actions">
            <button type="button" class="btn btn-ghost" data-tts-use-summary <?= $aiReady ? '' : 'disabled' ?>>Use blog summary</button>
            <button type="submit" class="btn btn-primary" data-tts-generate <?= $aiReady ? '' : 'disabled' ?>>Generate audio</button>
          </div>
          <p class="ai-tts-status" data-tts-status aria-live="polite"></p>
        </form>

        <div class="ai-tts-history" data-tts-history>
          <?php if (empty($ttsHistory)): ?>
          <p class="ai-tts-empty">Generate audio to build a quick listening library.</p>
          <?php else: ?>
          <?php foreach ($ttsHistory as $entry): ?>
          <article class="ai-tts-item" data-tts-id="<?= htmlspecialchars((string) $entry['id'], ENT_QUOTES) ?>">
            <header>
              <strong><?= htmlspecialchars(mb_strimwidth((string) ($entry['text'] ?? ''), 0, 80, '…'), ENT_QUOTES) ?></strong>
              <span><?= htmlspecialchars(ai_chat_timestamp_display((string) $entry['created_at']), ENT_QUOTES) ?></span>
            </header>
            <audio controls src="<?= htmlspecialchars((string) $entry['data'], ENT_QUOTES) ?>"></audio>
            <div class="ai-tts-item__actions">
              <a class="btn btn-link" href="<?= htmlspecialchars((string) $entry['data'], ENT_QUOTES) ?>" download="ai-audio-<?= htmlspecialchars((string) $entry['id'], ENT_QUOTES) ?>.wav">Download</a>
            </div>
          </article>
          <?php endforeach; ?>
          <?php endif; ?>
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

      const blogForm = document.getElementById('ai-blog-form');
      const blogGenerateButton = blogForm ? blogForm.querySelector('[data-blog-generate]') : null;
      const blogSaveButton = blogForm ? blogForm.querySelector('[data-blog-save]') : null;
      const blogAutosaveButton = blogForm ? blogForm.querySelector('[data-blog-autosave]') : null;
      const blogPreviewButton = blogForm ? blogForm.querySelector('[data-blog-preview]') : null;
      const blogPublishButton = blogForm ? blogForm.querySelector('[data-blog-publish]') : null;
      const blogOutput = blogForm ? blogForm.querySelector('[data-blog-output]') : null;
      const blogTitleInput = blogForm ? blogForm.querySelector('[data-blog-title]') : null;
      const blogBriefInput = blogForm ? blogForm.querySelector('[data-blog-brief]') : null;
      const blogKeywordsInput = blogForm ? blogForm.querySelector('[data-blog-keywords]') : null;
      const blogToneSelect = blogForm ? blogForm.querySelector('[data-blog-tone]') : null;
      const blogSummaryInput = blogForm ? blogForm.querySelector('[data-blog-summary-input]') : null;
      const blogStatus = blogForm ? blogForm.querySelector('[data-blog-status]') : null;
      const blogStatusMessage = blogStatus ? blogStatus.querySelector('[data-blog-status-message]') : null;
      const blogStatusMeta = blogStatus ? blogStatus.querySelector('.ai-blog-status__meta') : null;
      const blogReady = document.querySelector('[data-blog-ready]');
      const blogOutline = document.querySelector('[data-blog-outline]');
      const blogPreviewPanel = document.querySelector('[data-blog-preview]');
      const blogPreviewBody = blogPreviewPanel ? blogPreviewPanel.querySelector('[data-blog-preview-body]') : null;
      const blogPreviewTitle = blogPreviewPanel ? blogPreviewPanel.querySelector('[data-blog-preview-title]') : null;
      const blogPreviewClose = blogPreviewPanel ? blogPreviewPanel.querySelector('[data-blog-preview-close]') : null;
      const blogImagesContainer = document.querySelector('[data-blog-images]');

      const blogInitialButtonState = new Map();
      [blogGenerateButton, blogSaveButton, blogPublishButton].forEach(function (button) {
        if (button) {
          blogInitialButtonState.set(button, button.disabled);
        }
      });

      let blogAutosaveEnabled = false;
      let blogAutosaveTimer = null;
      let blogDirty = false;

      function updateBlogButtonsBusy(isBusy) {
        [blogGenerateButton, blogSaveButton, blogPublishButton].forEach(function (button) {
          if (!button) {
            return;
          }
          if (isBusy) {
            button.disabled = true;
          } else {
            const initial = blogInitialButtonState.get(button);
            if (typeof initial === 'boolean') {
              button.disabled = initial;
            }
          }
        });
      }

      function showBlogMessage(message, tone = 'info') {
        if (!blogStatus || !blogStatusMessage) {
          return;
        }
        blogStatus.dataset.tone = tone;
        blogStatusMessage.textContent = message || '';
      }

      function ensureMetaSpan(selector, iconClass) {
        if (!blogStatusMeta) {
          return null;
        }
        let element = blogStatusMeta.querySelector(selector);
        if (!element) {
          element = document.createElement('span');
          element.setAttribute(selector.replace('[', '').replace(']', ''), '');
          const icon = document.createElement('i');
          icon.className = iconClass;
          icon.setAttribute('aria-hidden', 'true');
          element.append(icon, document.createTextNode(' '));
          blogStatusMeta.append(element);
        } else {
          element.innerHTML = '';
          const icon = document.createElement('i');
          icon.className = iconClass;
          icon.setAttribute('aria-hidden', 'true');
          element.append(icon, document.createTextNode(' '));
        }
        return element;
      }

      function formatIsoDisplay(value) {
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
          return value;
        }
        return formatTimestamp(date);
      }

      function updateBlogSavedAt(value) {
        if (!blogStatusMeta) {
          return;
        }
        if (!value) {
          const existing = blogStatusMeta.querySelector('[data-blog-status-saved]');
          if (existing) {
            existing.remove();
          }
          return;
        }
        const formatted = formatIsoDisplay(value);
        const span = ensureMetaSpan('[data-blog-status-saved]', 'fa-regular fa-clock');
        if (span) {
          span.append(document.createTextNode('Last saved ' + formatted));
        }
      }

      function updateBlogPostId(postId) {
        if (!blogStatusMeta) {
          return;
        }
        if (!postId) {
          const existing = blogStatusMeta.querySelector('[data-blog-status-post]');
          if (existing) {
            existing.remove();
          }
          return;
        }
        const span = ensureMetaSpan('[data-blog-status-post]', 'fa-solid fa-newspaper');
        if (span) {
          span.append(document.createTextNode('Linked post #' + postId));
        }
      }

      function updateOutline(items) {
        if (!blogOutline) {
          return;
        }
        blogOutline.innerHTML = '';
        if (!items || items.length === 0) {
          const li = document.createElement('li');
          li.className = 'ai-blog-outline__placeholder';
          li.textContent = 'Outline will appear after the first generation.';
          blogOutline.append(li);
          return;
        }
        items.forEach(function (item) {
          const li = document.createElement('li');
          li.textContent = item;
          blogOutline.append(li);
        });
      }

      function scheduleBlogAutosave() {
        if (!blogAutosaveEnabled || !blogDirty) {
          return;
        }
        if (blogAutosaveTimer) {
          window.clearTimeout(blogAutosaveTimer);
        }
        blogAutosaveTimer = window.setTimeout(function () {
          void saveBlogDraft(false);
        }, 2000);
      }

      async function streamBlogGeneration(formData) {
        if (!blogOutput) {
          return;
        }
        blogOutput.value = '';
        blogOutput.focus();
        showBlogMessage('Generating blog draft…', 'progress');
        updateBlogButtonsBusy(true);
        try {
          const response = await fetch('admin-ai-studio.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
          });
          if (!response.ok || !response.body) {
            const payload = await response.json().catch(() => ({ error: 'Unable to generate the blog right now.' }));
            showBlogMessage(payload.error || 'Unable to generate the blog right now.', 'error');
            return;
          }

          const reader = response.body.getReader();
          const decoder = new TextDecoder();
          let carry = '';
          let text = '';
          let metaApplied = false;
          while (true) {
            const { value, done } = await reader.read();
            if (done) {
              break;
            }
            carry += decoder.decode(value, { stream: true });
            let newlineIndex;
            while ((newlineIndex = carry.indexOf('\n')) !== -1) {
              const line = carry.slice(0, newlineIndex);
              carry = carry.slice(newlineIndex + 1);
              if (!metaApplied && line.startsWith('__META__')) {
                metaApplied = true;
                try {
                  const meta = JSON.parse(line.slice(8));
                  updateOutline(meta.outline || []);
                  if (meta.summary && blogSummaryInput) {
                    blogSummaryInput.value = meta.summary;
                  }
                } catch (error) {
                  console.error('Failed to parse blog metadata', error);
                }
                continue;
              }
              text += line;
              blogOutput.value = text;
            }
          }
          if (carry !== '') {
            if (!metaApplied && carry.startsWith('__META__')) {
              try {
                const meta = JSON.parse(carry.slice(8));
                updateOutline(meta.outline || []);
                if (meta.summary && blogSummaryInput) {
                  blogSummaryInput.value = meta.summary;
                }
              } catch (error) {
                console.error('Failed to parse blog metadata', error);
              }
            } else {
              text += carry;
              blogOutput.value = text;
            }
          }
          blogDirty = true;
          scheduleBlogAutosave();
          showBlogMessage('Draft ready. Review and refine before saving.', 'success');
        } catch (error) {
          console.error(error);
          showBlogMessage('Generation failed. Check your connection and try again.', 'error');
        } finally {
          updateBlogButtonsBusy(false);
        }
      }

      async function saveBlogDraft(manual) {
        if (!blogForm) {
          return;
        }
        const formData = new FormData(blogForm);
        formData.set('action', manual ? 'blog_save_draft' : 'blog_autosave');
        if (blogOutput) {
          formData.set('content', blogOutput.value);
        }
        if (blogSummaryInput && !formData.has('summary')) {
          formData.set('summary', blogSummaryInput.value);
        }
        try {
          if (manual && blogSaveButton) {
            blogSaveButton.disabled = true;
          }
          const response = await fetch('admin-ai-studio.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
          });
          const payload = await response.json().catch(() => null);
          if (!response.ok || !payload) {
            showBlogMessage((payload && payload.message) || 'Unable to save the draft right now.', 'error');
            return;
          }
          blogDirty = false;
          if (payload.saved_at) {
            updateBlogSavedAt(payload.saved_at);
          }
          if (payload.post_id) {
            updateBlogPostId(payload.post_id);
          }
          if (manual) {
            if (payload.synced === false && payload.message) {
              showBlogMessage(payload.message, 'warning');
            } else {
              showBlogMessage(payload.message || 'Draft synced successfully.', 'success');
            }
          } else if (payload.saved_at) {
            showBlogMessage('Auto-saved at ' + formatIsoDisplay(payload.saved_at), 'success');
          }
        } catch (error) {
          console.error(error);
          if (manual) {
            showBlogMessage('Saving failed. Please retry.', 'error');
          }
        } finally {
          if (manual && blogSaveButton) {
            blogSaveButton.disabled = blogInitialButtonState.get(blogSaveButton) ?? false;
          }
        }
      }

      async function publishBlog() {
        if (!blogForm) {
          return;
        }
        const formData = new FormData(blogForm);
        formData.set('action', 'blog_publish');
        if (blogOutput) {
          formData.set('content', blogOutput.value);
        }
        if (blogSummaryInput && !formData.has('summary')) {
          formData.set('summary', blogSummaryInput.value);
        }
        updateBlogButtonsBusy(true);
        showBlogMessage('Publishing to the blog module…', 'progress');
        try {
          const response = await fetch('admin-ai-studio.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
          });
          const payload = await response.json().catch(() => null);
          if (!response.ok || !payload || !payload.success) {
            showBlogMessage((payload && payload.error) || 'Publishing failed. Review the draft and try again.', 'error');
            return;
          }
          if (payload.post_id) {
            updateBlogPostId(payload.post_id);
          }
          showBlogMessage('Published to the blog module.', 'success');
        } catch (error) {
          console.error(error);
          showBlogMessage('Publishing failed. Please try again.', 'error');
        } finally {
          updateBlogButtonsBusy(false);
        }
      }

      async function previewBlog() {
        if (!blogForm) {
          return;
        }
        const formData = new FormData(blogForm);
        formData.set('action', 'blog_preview');
        if (blogOutput) {
          formData.set('content', blogOutput.value);
        }
        try {
          const response = await fetch('admin-ai-studio.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
          });
          const payload = await response.json().catch(() => null);
          if (!response.ok || !payload || !payload.success) {
            showBlogMessage((payload && payload.error) || 'Preview failed. Ensure the draft has content.', 'error');
            return;
          }
          if (blogPreviewTitle) {
            blogPreviewTitle.textContent = payload.title || 'Preview';
          }
          if (blogPreviewBody) {
            blogPreviewBody.innerHTML = payload.html || '';
          }
          if (blogPreviewPanel) {
            blogPreviewPanel.hidden = false;
          }
        } catch (error) {
          console.error(error);
          showBlogMessage('Unable to load preview.', 'error');
        }
      }

      function appendImageCard(image) {
        if (!blogImagesContainer) {
          return;
        }
        const empty = blogImagesContainer.querySelector('.ai-blog-images__empty');
        if (empty) {
          empty.remove();
        }
        const figure = document.createElement('figure');
        figure.className = 'ai-blog-image';
        figure.dataset.imageId = image.id;
        if (image.attached) {
          figure.dataset.attached = '1';
        }
        const img = document.createElement('img');
        img.src = image.data;
        img.alt = image.prompt || 'AI generated visual';
        img.loading = 'lazy';
        const caption = document.createElement('figcaption');
        caption.textContent = image.prompt || 'AI generated visual';
        const actions = document.createElement('div');
        actions.className = 'ai-blog-image__actions';
        const download = document.createElement('a');
        download.className = 'btn btn-link';
        download.href = image.data;
        download.download = 'ai-visual-' + image.id + '.svg';
        download.textContent = 'Download';
        const attach = document.createElement('button');
        attach.type = 'button';
        attach.className = 'btn btn-secondary';
        attach.setAttribute('data-image-attach', '');
        attach.textContent = 'Attach to blog';
        actions.append(download, attach);
        figure.append(img, caption, actions);
        blogImagesContainer.append(figure);
      }

      const blogInputs = blogForm
        ? blogForm.querySelectorAll('[data-blog-title], [data-blog-brief], [data-blog-keywords], [data-blog-tone], [data-blog-output]')
        : [];
      blogInputs.forEach(function (input) {
        const eventName = input.tagName === 'SELECT' ? 'change' : 'input';
        input.addEventListener(eventName, function () {
          blogDirty = true;
          scheduleBlogAutosave();
        });
      });

      if (blogAutosaveButton) {
        blogAutosaveButton.addEventListener('click', function () {
          blogAutosaveEnabled = !blogAutosaveEnabled;
          blogAutosaveButton.textContent = blogAutosaveEnabled ? 'Auto-save on' : 'Auto-save off';
          if (blogAutosaveEnabled) {
            scheduleBlogAutosave();
            showBlogMessage('Auto-save enabled. Changes will sync shortly after edits.', 'success');
          } else if (blogAutosaveTimer) {
            window.clearTimeout(blogAutosaveTimer);
            blogAutosaveTimer = null;
          }
        });
      }

      if (blogGenerateButton && blogForm && blogReady && blogReady.dataset.blogReady === '1') {
        blogGenerateButton.addEventListener('click', function () {
          if (!blogForm) {
            return;
          }
          if (blogAutosaveTimer) {
            window.clearTimeout(blogAutosaveTimer);
          }
          const formData = new FormData();
          formData.set('action', 'blog_generate');
          formData.set('csrf_token', blogForm.querySelector('input[name="csrf_token"]').value);
          if (blogTitleInput) {
            formData.set('title', blogTitleInput.value);
          }
          if (blogBriefInput) {
            formData.set('brief', blogBriefInput.value);
          }
          if (blogKeywordsInput) {
            formData.set('keywords', blogKeywordsInput.value);
          }
          if (blogToneSelect) {
            formData.set('tone', blogToneSelect.value);
          }
          if (!formData.get('title') && !formData.get('brief')) {
            showBlogMessage('Add a title or brief before generating.', 'error');
            return;
          }
          void streamBlogGeneration(formData);
        });
      }

      if (blogSaveButton && blogForm) {
        blogSaveButton.addEventListener('click', function () {
          void saveBlogDraft(true);
        });
      }

      if (blogPublishButton && blogForm) {
        blogPublishButton.addEventListener('click', function () {
          void publishBlog();
        });
      }

      if (blogPreviewButton && blogForm) {
        blogPreviewButton.addEventListener('click', function () {
          void previewBlog();
        });
      }

      if (blogPreviewClose && blogPreviewPanel) {
        blogPreviewClose.addEventListener('click', function () {
          blogPreviewPanel.hidden = true;
        });
      }

      if (blogPreviewPanel) {
        blogPreviewPanel.addEventListener('click', function (event) {
          if (event.target === blogPreviewPanel) {
            blogPreviewPanel.hidden = true;
          }
        });
      }

      if (blogImagesContainer) {
        blogImagesContainer.addEventListener('click', async function (event) {
          const target = event.target instanceof HTMLElement ? event.target : null;
          if (!target || !target.hasAttribute('data-image-attach')) {
            return;
          }
          const card = target.closest('[data-image-id]');
          if (!card) {
            return;
          }
          const imageId = card.getAttribute('data-image-id');
          if (!imageId) {
            return;
          }
          target.disabled = true;
          try {
            const formData = new FormData();
            formData.set('action', 'image_attach');
            formData.set('csrf_token', blogForm.querySelector('input[name="csrf_token"]').value);
            formData.set('image_id', imageId);
            const response = await fetch('admin-ai-studio.php', {
              method: 'POST',
              body: formData,
              headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const payload = await response.json().catch(() => null);
            if (!response.ok || !payload || !payload.success) {
              showBlogMessage((payload && payload.error) || 'Unable to attach that image.', 'error');
              return;
            }
            blogImagesContainer.querySelectorAll('[data-image-id]').forEach(function (figure) {
              figure.removeAttribute('data-attached');
            });
            card.setAttribute('data-attached', '1');
            showBlogMessage('Image attached to the draft.', 'success');
          } catch (error) {
            console.error(error);
            showBlogMessage('Unable to attach that image right now.', 'error');
          } finally {
            target.disabled = false;
          }
        });
      }

      const imageForm = document.getElementById('ai-image-form');
      const imagePromptInput = imageForm ? imageForm.querySelector('[data-image-prompt]') : null;
      const imageStatus = imageForm ? imageForm.querySelector('[data-image-status]') : null;
      const imageAutofillButton = imageForm ? imageForm.querySelector('[data-image-autofill]') : null;
      const imageGenerateButton = imageForm ? imageForm.querySelector('[data-image-generate]') : null;

      function showImageStatus(message, tone = 'info') {
        if (!imageStatus) {
          return;
        }
        imageStatus.textContent = message || '';
        imageStatus.dataset.tone = tone;
      }

      if (imageAutofillButton && imagePromptInput) {
        imageAutofillButton.addEventListener('click', function () {
          const promptPieces = [];
          if (blogTitleInput && blogTitleInput.value) {
            promptPieces.push(blogTitleInput.value);
          }
          if (blogSummaryInput && blogSummaryInput.value) {
            promptPieces.push(blogSummaryInput.value);
          }
          if (blogKeywordsInput && blogKeywordsInput.value) {
            promptPieces.push(blogKeywordsInput.value);
          }
          if (promptPieces.length === 0) {
            showImageStatus('Add some blog details first to build an image prompt.', 'warning');
            return;
          }
          imagePromptInput.value = promptPieces.join(' — ');
          showImageStatus('Prompt populated from the current draft.', 'success');
        });
      }

      if (imageForm && imagePromptInput) {
        imageForm.addEventListener('submit', async function (event) {
          event.preventDefault();
          const prompt = imagePromptInput.value.trim();
          if (!prompt) {
            showImageStatus('Describe what you want to see before generating.', 'error');
            return;
          }
          showImageStatus('Generating visual…', 'progress');
          if (imageGenerateButton) {
            imageGenerateButton.disabled = true;
          }
          try {
            const formData = new FormData(imageForm);
            const response = await fetch(imageForm.getAttribute('action') || 'admin-ai-studio.php', {
              method: 'POST',
              body: formData,
              headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const payload = await response.json().catch(() => null);
            if (!response.ok || !payload || !payload.success) {
              showImageStatus((payload && payload.error) || 'Unable to create that image right now.', 'error');
              return;
            }
            appendImageCard(payload.image);
            showImageStatus('New visual ready in the sidebar.', 'success');
            imagePromptInput.value = '';
          } catch (error) {
            console.error(error);
            showImageStatus('Image generation failed. Try again.', 'error');
          } finally {
            if (imageGenerateButton) {
              imageGenerateButton.disabled = false;
            }
          }
        });
      }

      const ttsForm = document.getElementById('ai-tts-form');
      const ttsInput = ttsForm ? ttsForm.querySelector('[data-tts-input]') : null;
      const ttsStatus = ttsForm ? ttsForm.querySelector('[data-tts-status]') : null;
      const ttsUseSummaryButton = ttsForm ? ttsForm.querySelector('[data-tts-use-summary]') : null;
      const ttsGenerateButton = ttsForm ? ttsForm.querySelector('[data-tts-generate]') : null;
      const ttsHistoryContainer = document.querySelector('[data-tts-history]');

      function showTtsStatus(message, tone = 'info') {
        if (!ttsStatus) {
          return;
        }
        ttsStatus.textContent = message || '';
        ttsStatus.dataset.tone = tone;
      }

      function appendTtsEntry(entry) {
        if (!ttsHistoryContainer) {
          return;
        }
        const empty = ttsHistoryContainer.querySelector('.ai-tts-empty');
        if (empty) {
          empty.remove();
        }
        const article = document.createElement('article');
        article.className = 'ai-tts-item';
        article.dataset.ttsId = entry.id;
        const header = document.createElement('header');
        const strong = document.createElement('strong');
        strong.textContent = entry.text.length > 80 ? entry.text.slice(0, 79) + '…' : entry.text;
        const time = document.createElement('span');
        time.textContent = formatTimestamp(new Date(entry.created_at));
        header.append(strong, time);
        const audio = document.createElement('audio');
        audio.controls = true;
        audio.src = entry.data;
        const actions = document.createElement('div');
        actions.className = 'ai-tts-item__actions';
        const download = document.createElement('a');
        download.className = 'btn btn-link';
        download.href = entry.data;
        download.download = 'ai-audio-' + entry.id + '.wav';
        download.textContent = 'Download';
        actions.append(download);
        article.append(header, audio, actions);
        ttsHistoryContainer.prepend(article);
      }

      if (ttsUseSummaryButton && ttsInput) {
        ttsUseSummaryButton.addEventListener('click', function () {
          if (blogSummaryInput && blogSummaryInput.value) {
            ttsInput.value = blogSummaryInput.value;
            showTtsStatus('Filled with the latest blog summary.', 'success');
          } else {
            showTtsStatus('Generate a draft to capture a summary first.', 'warning');
          }
        });
      }

      if (ttsForm && ttsInput) {
        ttsForm.addEventListener('submit', async function (event) {
          event.preventDefault();
          const text = ttsInput.value.trim();
          if (!text) {
            showTtsStatus('Add some text before generating audio.', 'error');
            return;
          }
          showTtsStatus('Generating audio clip…', 'progress');
          if (ttsGenerateButton) {
            ttsGenerateButton.disabled = true;
          }
          try {
            const formData = new FormData(ttsForm);
            const response = await fetch(ttsForm.getAttribute('action') || 'admin-ai-studio.php', {
              method: 'POST',
              body: formData,
              headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const payload = await response.json().catch(() => null);
            if (!response.ok || !payload || !payload.success) {
              showTtsStatus((payload && payload.error) || 'Unable to generate audio right now.', 'error');
              return;
            }
            appendTtsEntry(payload.entry);
            showTtsStatus('Audio clip ready.', 'success');
          } catch (error) {
            console.error(error);
            showTtsStatus('Unable to generate audio right now.', 'error');
          } finally {
            if (ttsGenerateButton) {
              ttsGenerateButton.disabled = false;
            }
          }
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
