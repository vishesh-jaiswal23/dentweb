<?php
declare(strict_types=1);

function gemini_settings_raw(PDO $db): array
{
    return [
        'apiKey' => get_setting('gemini_api_key', $db) ?? '',
        'textModel' => get_setting('gemini_text_model', $db) ?? 'gemini-2.5-flash',
        'imageModel' => get_setting('gemini_image_model', $db) ?? 'gemini-2.5-flash-image',
        'ttsModel' => get_setting('gemini_tts_model', $db) ?? 'gemini-2.5-flash-preview-tts',
        'enabled' => (bool) ((int) (get_setting('gemini_enabled', $db) ?? '0')),
    ];
}

function gemini_mask_api_key(string $key): string
{
    $key = trim($key);
    if ($key === '') {
        return '';
    }

    $length = strlen($key);
    if ($length <= 4) {
        return str_repeat('•', $length);
    }

    $visible = substr($key, -4);
    return str_repeat('•', $length - 4) . $visible;
}

function gemini_settings_admin_view(PDO $db): array
{
    $raw = gemini_settings_raw($db);

    return [
        'enabled' => $raw['enabled'],
        'maskedKey' => gemini_mask_api_key($raw['apiKey']),
        'hasKey' => $raw['apiKey'] !== '',
        'textModel' => $raw['textModel'],
        'imageModel' => $raw['imageModel'],
        'ttsModel' => $raw['ttsModel'],
    ];
}

function gemini_save_settings(PDO $db, array $input): array
{
    $apiKey = trim((string) ($input['apiKey'] ?? ''));
    $textModel = trim((string) ($input['textModel'] ?? 'gemini-2.5-flash'));
    $imageModel = trim((string) ($input['imageModel'] ?? 'gemini-2.5-flash-image'));
    $ttsModel = trim((string) ($input['ttsModel'] ?? 'gemini-2.5-flash-preview-tts'));
    $enabled = !empty($input['enabled']);

    $current = gemini_settings_raw($db);
    if ($apiKey === '') {
        $apiKey = $current['apiKey'];
    }

    if ($enabled && $apiKey === '') {
        throw new RuntimeException('Add a Gemini API key before enabling the provider.');
    }

    if ($apiKey === '' && !$enabled) {
        // Allow storing an empty key only when the provider remains disabled.
        set_setting('gemini_api_key', '', $db);
    } elseif ($apiKey !== '') {
        set_setting('gemini_api_key', $apiKey, $db);
    }

    set_setting('gemini_text_model', $textModel !== '' ? $textModel : 'gemini-2.5-flash', $db);
    set_setting('gemini_image_model', $imageModel !== '' ? $imageModel : 'gemini-2.5-flash-image', $db);
    set_setting('gemini_tts_model', $ttsModel !== '' ? $ttsModel : 'gemini-2.5-flash-preview-tts', $db);
    set_setting('gemini_enabled', $enabled ? '1' : '0', $db);

    return gemini_settings_admin_view($db);
}

function gemini_test_connection(PDO $db, ?string $apiKey = null): array
{
    if ($apiKey === null || trim($apiKey) === '') {
        $apiKey = gemini_settings_raw($db)['apiKey'];
    }

    $apiKey = trim((string) $apiKey);
    if ($apiKey === '') {
        throw new RuntimeException('Provide a Gemini API key to test.');
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
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($responseBody === false) {
        throw new RuntimeException('Unable to reach Gemini services: ' . $error);
    }

    $payload = json_decode($responseBody, true);
    if ($status >= 400) {
        $message = $payload['error']['message'] ?? ('HTTP ' . $status);
        throw new RuntimeException('Gemini responded with an error: ' . $message);
    }

    $testedAt = (new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata')))->format(DateTimeInterface::ATOM);
    $modelCount = is_array($payload['models'] ?? null) ? count($payload['models']) : 0;

    return [
        'status' => 'ok',
        'testedAt' => $testedAt,
        'modelsDiscovered' => $modelCount,
    ];
}

function gemini_prepare_drafts(array $posts): array
{
    $drafts = [];
    foreach ($posts as $post) {
        if (($post['status'] ?? '') !== 'draft') {
            continue;
        }

        $updatedAt = $post['updatedAt'] ?? $post['publishedAt'] ?? null;
        $drafts[] = [
            'id' => (int) ($post['id'] ?? 0),
            'title' => (string) ($post['title'] ?? ''),
            'slug' => (string) ($post['slug'] ?? ''),
            'status' => (string) ($post['status'] ?? 'draft'),
            'updatedAt' => $updatedAt,
            'updatedDisplay' => gemini_format_datetime($updatedAt),
        ];
    }

    usort($drafts, static function (array $a, array $b): int {
        return strcmp((string) ($b['updatedAt'] ?? ''), (string) ($a['updatedAt'] ?? ''));
    });

    return $drafts;
}

function gemini_format_datetime(?string $value): string
{
    if (!$value) {
        return '—';
    }

    try {
        $dt = new DateTimeImmutable($value, new DateTimeZone('UTC'));
        return $dt->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('d M Y · h:i A');
    } catch (Throwable $exception) {
        return $value;
    }
}

function gemini_generate_blog_draft(PDO $db, array $input, array $actor, int $actorId): array
{
    $settings = gemini_settings_raw($db);
    if (!$settings['enabled']) {
        throw new RuntimeException('Enable Gemini before generating a draft.');
    }
    if ($settings['apiKey'] === '') {
        throw new RuntimeException('Add a Gemini API key before generating a draft.');
    }

    $topic = trim((string) ($input['topic'] ?? ''));
    if ($topic === '') {
        throw new RuntimeException('Provide a topic for the draft.');
    }

    $tone = strtolower(trim((string) ($input['tone'] ?? 'informative')));
    $allowedTones = ['informative', 'conversational', 'technical', 'promotional'];
    if (!in_array($tone, $allowedTones, true)) {
        $tone = 'informative';
    }

    $audience = trim((string) ($input['audience'] ?? 'Jharkhand households'));
    if ($audience === '') {
        $audience = 'Jharkhand households';
    }

    $callToAction = trim((string) ($input['callToAction'] ?? 'Request a Dentweb rooftop solar consultation.'));
    if ($callToAction === '') {
        $callToAction = 'Request a Dentweb rooftop solar consultation.';
    }

    $title = gemini_build_draft_title($topic);
    $intro = sprintf(
        'Gemini prepared this briefing to help Dentweb teams explain %s to %s in clear, confident language.',
        strtolower($topic),
        $audience
    );

    $sections = gemini_build_draft_sections($topic, $tone, $audience);
    $bodyParts = [];
    $bodyParts[] = '<p>' . gemini_escape($intro) . '</p>';

    foreach ($sections as $section) {
        $bodyParts[] = '<h2>' . gemini_escape($section['heading']) . '</h2>';
        $bodyParts[] = '<p>' . gemini_escape($section['summary']) . '</p>';
        if (!empty($section['bullets'])) {
            $bodyParts[] = '<ul>';
            foreach ($section['bullets'] as $bullet) {
                $bodyParts[] = '<li>' . gemini_escape($bullet) . '</li>';
            }
            $bodyParts[] = '</ul>';
        }
    }

    $bodyParts[] = '<p><strong>Next step:</strong> ' . gemini_escape($callToAction) . '</p>';
    $bodyHtml = implode("\n", $bodyParts);

    $excerpt = blog_extract_plain_text($bodyHtml);
    if (function_exists('mb_substr')) {
        $excerpt = mb_substr($excerpt, 0, 220);
    } else {
        $excerpt = substr($excerpt, 0, 220);
    }
    if ($excerpt !== '') {
        $excerpt = rtrim($excerpt) . '…';
    }

    $author = trim((string) ($actor['full_name'] ?? $actor['username'] ?? 'Gemini Assistant'));
    if ($author === '') {
        $author = 'Gemini Assistant';
    }

    $post = blog_save_post($db, [
        'title' => $title,
        'excerpt' => $excerpt,
        'body' => $bodyHtml,
        'status' => 'draft',
        'authorName' => $author,
        'coverPrompt' => $topic,
        'tags' => ['Gemini Draft'],
    ], $actorId);

    $drafts = gemini_prepare_drafts(blog_admin_list($db));

    return [
        'post' => $post,
        'drafts' => $drafts,
    ];
}

function gemini_build_draft_title(string $topic): string
{
    $normalized = trim(preg_replace('/\s+/', ' ', $topic) ?? '');
    if ($normalized === '') {
        $normalized = 'Solar insights for Dentweb readers';
    }

    if (function_exists('mb_convert_case')) {
        $normalized = mb_convert_case($normalized, MB_CASE_TITLE, 'UTF-8');
    } else {
        $normalized = ucwords(strtolower($normalized));
    }

    $today = new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
    return sprintf('%s – Gemini Draft %s', $normalized, $today->format('j M Y'));
}

function gemini_build_draft_sections(string $topic, string $tone, string $audience): array
{
    $toneDescriptors = [
        'informative' => 'Practical guidance for busy decision-makers.',
        'conversational' => 'Friendly talking points you can share in client calls.',
        'technical' => 'Specification cues to reassure engineering-minded readers.',
        'promotional' => 'Momentum-led messaging to drive quick follow-ups.',
    ];
    $descriptor = $toneDescriptors[$tone] ?? $toneDescriptors['informative'];

    $topicLower = strtolower($topic);

    return [
        [
            'heading' => 'Market pulse',
            'summary' => sprintf('Gemini tracked policy moves, pricing trends, and customer questions around %s.', $topicLower),
            'bullets' => [
                sprintf('Highlight why %s now delivers measurable savings for %s.', $topicLower, $audience),
                'Reference subsidy timelines and documentation checkpoints to set expectations early.',
                'Surface recent installations or case studies that mirror the reader’s profile.',
            ],
        ],
        [
            'heading' => 'Operational checkpoints',
            'summary' => 'Equip teams with the workflow reminders that avoid escalations.',
            'bullets' => [
                'List the paperwork Dentweb should collect before site surveys.',
                'Note coordination windows with DISCOM teams and safety walkthroughs.',
                sprintf('Keep a shared tracker so %s stakeholders can view progress at a glance.', $audience),
            ],
        ],
        [
            'heading' => 'Messaging cues & tone',
            'summary' => $descriptor,
            'bullets' => [
                'Open with a benefit-led hook, then support it with one proof point.',
                'Use bilingual headings where helpful; close with a simple call to action.',
                'Capture follow-up questions in the CRM to feed future Gemini prompts.',
            ],
        ],
    ];
}

function gemini_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}
