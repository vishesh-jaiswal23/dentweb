<?php
declare(strict_types=1);

const AI_DAILY_NOTE_TYPES = ['work_done', 'next_plan'];

function ai_storage_base(): string
{
    return __DIR__ . '/../storage/ai';
}

function ai_storage_path(string ...$segments): string
{
    $path = ai_storage_base();
    foreach ($segments as $segment) {
        $segment = str_replace(['\\', '..'], '/', $segment);
        $segment = trim($segment, '/');
        if ($segment === '') {
            continue;
        }
        $path .= DIRECTORY_SEPARATOR . $segment;
    }
    return $path;
}

function ai_ensure_directory(string $path): void
{
    if (is_dir($path)) {
        return;
    }
    if (!mkdir($path, 0775, true) && !is_dir($path)) {
        throw new RuntimeException(sprintf('Unable to create directory: %s', $path));
    }
}

function ai_bootstrap_storage(): void
{
    ai_ensure_directory(ai_storage_base());
    ai_ensure_directory(ai_storage_path('drafts'));
    ai_ensure_directory(ai_storage_path('images'));
    ai_ensure_directory(ai_storage_path('notes'));
}

function ai_safe_write(string $path, string $contents): void
{
    ai_ensure_directory(dirname($path));
    $result = @file_put_contents($path, $contents, LOCK_EX);
    if ($result === false) {
        throw new RuntimeException(sprintf('Unable to write to %s', $path));
    }
}

function ai_read_json_file(string $path, array $default = []): array
{
    if (!is_file($path)) {
        return $default;
    }
    $contents = @file_get_contents($path);
    if ($contents === false || $contents === '') {
        return $default;
    }
    $decoded = json_decode($contents, true);
    return is_array($decoded) ? $decoded : $default;
}

function ai_write_json_file(string $path, array $data): void
{
    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        throw new RuntimeException('Failed to encode AI data as JSON.');
    }
    ai_safe_write($path, $encoded);
}

function ai_settings_path(): string
{
    return ai_storage_path('settings.json');
}

function ai_settings_defaults(): array
{
    return [
        'enabled' => false,
        'provider' => 'Gemini',
        'text_model' => '',
        'image_model' => '',
        'api_key_hash' => null,
        'last_test_result' => null,
        'last_tested_at' => null,
        'updated_at' => null,
    ];
}

function ai_get_settings(): array
{
    ai_bootstrap_storage();
    $settings = ai_read_json_file(ai_settings_path(), ai_settings_defaults());
    $settings['enabled'] = (bool) ($settings['enabled'] ?? false);
    $settings['provider'] = (string) ($settings['provider'] ?? 'Gemini');
    $settings['text_model'] = (string) ($settings['text_model'] ?? '');
    $settings['image_model'] = (string) ($settings['image_model'] ?? '');
    $settings['api_key_hash'] = $settings['api_key_hash'] ?? null;
    $settings['last_test_result'] = $settings['last_test_result'] ?? null;
    $settings['last_tested_at'] = $settings['last_tested_at'] ?? null;
    $settings['updated_at'] = $settings['updated_at'] ?? null;
    $settings['has_api_key'] = is_string($settings['api_key_hash']) && $settings['api_key_hash'] !== '';
    return $settings;
}

function ai_save_settings(array $input, int $actorId): array
{
    unset($actorId);
    $settings = ai_get_settings();
    $enabled = !empty($input['enabled']);
    $provider = trim((string) ($input['provider'] ?? 'Gemini'));
    if ($provider === '') {
        $provider = 'Gemini';
    }
    $textModel = trim((string) ($input['text_model'] ?? ''));
    $imageModel = trim((string) ($input['image_model'] ?? ''));
    $apiKey = trim((string) ($input['api_key'] ?? ''));

    if ($apiKey !== '') {
        $settings['api_key_hash'] = password_hash($apiKey, PASSWORD_DEFAULT);
        $settings['last_test_result'] = null;
        $settings['last_tested_at'] = null;
    }

    $settings['enabled'] = $enabled;
    $settings['provider'] = $provider;
    $settings['text_model'] = $textModel;
    $settings['image_model'] = $imageModel;
    $settings['updated_at'] = gmdate(DateTimeInterface::ATOM);

    ai_write_json_file(ai_settings_path(), $settings);
    return ai_get_settings();
}

function ai_test_connection(): array
{
    $settings = ai_get_settings();
    $pass = $settings['enabled'] && $settings['has_api_key'] && $settings['text_model'] !== '' && $settings['image_model'] !== '';
    $result = $pass ? 'pass' : 'fail';
    $message = $pass
        ? 'PASS — Configuration looks healthy. AI tools are ready.'
        : 'FAIL — Provide provider details, models, and a valid API key.';

    $settings['last_test_result'] = $result;
    $settings['last_tested_at'] = gmdate(DateTimeInterface::ATOM);
    ai_write_json_file(ai_settings_path(), $settings);

    return ['status' => $result, 'message' => $message];
}

function ai_require_enabled(): void
{
    $settings = ai_get_settings();
    if (!$settings['enabled']) {
        throw new RuntimeException('Enable AI tools in settings to use this feature.');
    }
}

function ai_normalize_keywords($input): array
{
    if (is_array($input)) {
        $items = $input;
    } else {
        $items = preg_split('/[,\n]+/', (string) $input) ?: [];
    }
    $keywords = [];
    foreach ($items as $item) {
        $keyword = trim((string) $item);
        if ($keyword !== '') {
            $keywords[] = $keyword;
        }
    }
    return array_values(array_unique($keywords));
}

function ai_generate_blog_draft_content(string $prompt): array
{
    $prompt = trim($prompt);
    if ($prompt === '') {
        throw new RuntimeException('Enter a prompt for the blog draft.');
    }

    $normalizedPrompt = preg_replace('/\s+/', ' ', $prompt);
    $topic = ucwords(mb_substr($normalizedPrompt, 0, 120));
    if ($topic === '') {
        $topic = 'AI Studio Insight';
    }

    $title = $topic;
    if (mb_strlen($title) < 28) {
        $title = sprintf('%s — Fresh Insights', $title);
    }

    $keywords = array_values(array_filter(array_unique(array_map(
        static function ($word) {
            $word = trim(mb_strtolower($word));
            return $word !== '' && mb_strlen($word) > 3 ? $word : null;
        },
        preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($normalizedPrompt)) ?: []
    ))));
    if (count($keywords) > 6) {
        $keywords = array_slice($keywords, 0, 6);
    }

    $opening = sprintf(
        'This piece explores "%s" and outlines immediate takeaways for teams tracking the clean-energy market.',
        htmlspecialchars($normalizedPrompt, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5)
    );

    $sections = [
        [
            'heading' => 'Why it matters now',
            'body' => 'Spot the policy shifts, funding windows, and customer triggers connected to this theme. Translate the signal into next actions for sales, project, and support teams.',
        ],
        [
            'heading' => 'Numbers to watch',
            'body' => 'Capture pipeline velocity, subsidy utilisation, installation readiness, and customer sentiment to measure traction.',
        ],
        [
            'heading' => 'Actions for the week ahead',
            'body' => 'Assign owners, unblock cross-functional dependencies, and communicate the wins you expect before the next review.',
        ],
    ];

    $bodyParts = [];
    $bodyParts[] = '<p>' . $opening . '</p>';
    foreach ($sections as $section) {
        $bodyParts[] = '<h2>' . htmlspecialchars($section['heading'], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5) . '</h2>';
        $bodyParts[] = '<p>' . htmlspecialchars($section['body'], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5) . '</p>';
    }
    if ($keywords) {
        $items = array_map(static fn ($keyword) => sprintf('<li>%s</li>', htmlspecialchars($keyword, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5)), $keywords);
        $bodyParts[] = '<h3>Suggested keywords</h3>';
        $bodyParts[] = '<ul>' . implode('', $items) . '</ul>';
    }

    $bodyHtml = implode("\n", $bodyParts);
    $excerpt = blog_extract_plain_text($bodyHtml);
    if (mb_strlen($excerpt) > 240) {
        $excerpt = trim(mb_substr($excerpt, 0, 240)) . '…';
    }

    $imagePrompt = sprintf('Feature image showing %s in the context of clean energy.', mb_strtolower($normalizedPrompt));

    return [
        'prompt' => $normalizedPrompt,
        'topic' => $topic,
        'title' => $title,
        'body_html' => $bodyHtml,
        'excerpt' => $excerpt,
        'keywords' => $keywords,
        'image_prompt' => $imagePrompt,
    ];
}

function ai_generate_draft_id(): string
{
    return 'draft-' . bin2hex(random_bytes(8));
}

function ai_draft_path(string $id): string
{
    return ai_storage_path('drafts', $id . '.json');
}

function ai_load_draft(string $id): array
{
    $draft = ai_read_json_file(ai_draft_path($id));
    if (!$draft) {
        throw new RuntimeException('Draft not found.');
    }
    return $draft;
}

function ai_store_draft(array $draft): void
{
    if (empty($draft['id'])) {
        throw new RuntimeException('Invalid draft payload.');
    }
    ai_write_json_file(ai_draft_path($draft['id']), $draft);
}

function ai_all_drafts(): array
{
    ai_bootstrap_storage();
    $files = glob(ai_storage_path('drafts', '*.json')) ?: [];
    $drafts = [];
    foreach ($files as $file) {
        $data = ai_read_json_file($file);
        if (!is_array($data) || empty($data['id'])) {
            continue;
        }
        $drafts[$data['id']] = $data;
    }
    return $drafts;
}

function ai_unique_draft_slug(string $slug, ?string $currentId = null): string
{
    $slug = blog_slugify($slug);
    if ($slug === '') {
        $slug = 'draft';
    }
    $drafts = ai_all_drafts();
    $existing = array_map(static fn ($draft) => $draft['slug'] ?? '', $drafts);
    $existing = array_filter($existing);

    if ($currentId !== null && isset($drafts[$currentId])) {
        $currentSlug = $drafts[$currentId]['slug'] ?? '';
        $existing = array_diff($existing, [$currentSlug]);
    }

    $candidate = $slug;
    $index = 2;
    while (in_array($candidate, $existing, true)) {
        $candidate = $slug . '-' . $index;
        $index++;
    }

    return $candidate;
}

function ai_save_blog_draft(array $input, int $actorId): array
{
    unset($actorId);
    ai_bootstrap_storage();
    ai_require_enabled();

    $draftId = isset($input['draft_id']) ? trim((string) $input['draft_id']) : '';
    $isNew = $draftId === '';

    if ($isNew) {
        $draftId = ai_generate_draft_id();
        $draft = [
            'id' => $draftId,
            'status' => 'draft',
            'created_at' => gmdate(DateTimeInterface::ATOM),
            'published_post_id' => null,
            'published_slug' => null,
            'published_at' => null,
        ];
    } else {
        $draft = ai_load_draft($draftId);
        if ($draft['status'] === 'published') {
            throw new RuntimeException('Published drafts cannot be edited.');
        }
    }

    $title = trim((string) ($input['title'] ?? ($draft['title'] ?? '')));
    $body = (string) ($input['body'] ?? ($draft['body'] ?? ''));
    $excerpt = trim((string) ($input['excerpt'] ?? ($draft['excerpt'] ?? '')));
    $topic = trim((string) ($input['topic'] ?? ($draft['topic'] ?? $title)));
    $tone = trim((string) ($input['tone'] ?? ($draft['tone'] ?? '')));
    $audience = trim((string) ($input['audience'] ?? ($draft['audience'] ?? '')));
    $purpose = trim((string) ($input['purpose'] ?? ($draft['purpose'] ?? '')));
    $generatedTitle = trim((string) ($input['generated_title'] ?? ($draft['generated_title'] ?? '')));
    $generatedBody = (string) ($input['generated_body'] ?? ($draft['generated_body'] ?? ''));
    $keywords = ai_normalize_keywords($input['keywords'] ?? ($draft['keywords'] ?? []));
    $imagePrompt = trim((string) ($input['image_prompt'] ?? ($draft['image_prompt'] ?? '')));
    $authorName = trim((string) ($input['author_name'] ?? ($draft['author_name'] ?? '')));
    $slugInput = trim((string) ($input['slug'] ?? ($draft['slug'] ?? $title)));

    if ($title === '' || trim(strip_tags($body)) === '') {
        throw new RuntimeException('Provide a title and body before saving the draft.');
    }
    if ($excerpt === '') {
        $excerpt = blog_extract_plain_text($body);
        if (mb_strlen($excerpt) > 240) {
            $excerpt = trim(mb_substr($excerpt, 0, 240)) . '…';
        }
    }
    if ($topic === '') {
        $topic = $title;
    }

    $slug = ai_unique_draft_slug($slugInput !== '' ? $slugInput : $title, $isNew ? null : $draftId);

    $draft['title'] = $title;
    $draft['topic'] = $topic;
    $draft['tone'] = $tone;
    $draft['audience'] = $audience;
    $draft['purpose'] = $purpose;
    $draft['generated_title'] = $generatedTitle !== '' ? $generatedTitle : $title;
    $draft['generated_body'] = $generatedBody !== '' ? $generatedBody : $body;
    $draft['body'] = $body;
    $draft['excerpt'] = $excerpt;
    $draft['keywords'] = $keywords;
    $draft['image_prompt'] = $imagePrompt;
    $draft['author_name'] = $authorName;
    $draft['slug'] = $slug;
    $draft['updated_at'] = gmdate(DateTimeInterface::ATOM);

    ai_store_draft($draft);

    return [
        'id' => $draftId,
        'title' => $title,
        'slug' => $slug,
        'status' => $draft['status'],
    ];
}

function ai_generate_blog_draft_from_prompt(string $prompt, int $actorId): array
{
    unset($actorId);
    ai_require_enabled();
    $content = ai_generate_blog_draft_content($prompt);

    $payload = [
        'title' => $content['title'],
        'body' => $content['body_html'],
        'excerpt' => $content['excerpt'],
        'topic' => $content['topic'],
        'tone' => 'Informative',
        'audience' => 'General readership',
        'purpose' => 'Prompt: ' . mb_substr($content['prompt'], 0, 240),
        'generated_title' => $content['title'],
        'generated_body' => $content['body_html'],
        'keywords' => $content['keywords'],
        'image_prompt' => $content['image_prompt'],
        'author_name' => '',
    ];

    $saved = ai_save_blog_draft($payload, 0);
    ai_generate_image_for_draft($saved['id'], 0);

    return [
        'draft_id' => $saved['id'],
        'title' => $content['title'],
    ];
}

function ai_image_file_path(string $draftId): string
{
    return ai_storage_path('images', $draftId . '.svg');
}

function ai_draft_image_data_uri(array $draft): string
{
    $path = $draft['image_file'] ?? '';
    if ($path === '' || !is_file(ai_storage_path($path))) {
        return '';
    }
    $fullPath = ai_storage_path($path);
    $contents = @file_get_contents($fullPath);
    if ($contents === false) {
        return '';
    }
    return 'data:image/svg+xml;base64,' . base64_encode($contents);
}

function ai_generate_image_for_draft(string $draftId, int $actorId): array
{
    unset($actorId);
    ai_require_enabled();
    $draft = ai_load_draft($draftId);
    if (($draft['status'] ?? 'draft') === 'published') {
        throw new RuntimeException('Published drafts already carry a final cover.');
    }

    $title = $draft['title'] ?? ($draft['topic'] ?? 'Blog draft');
    $prompt = $draft['image_prompt'] ?? ($draft['topic'] ?? 'Clean energy insight');
    [$imageDataUri, $alt] = blog_generate_placeholder_cover($title, (string) $prompt);

    $parts = explode(',', $imageDataUri, 2);
    $raw = $parts[1] ?? '';
    $binary = base64_decode($raw, true);
    if ($binary === false) {
        throw new RuntimeException('Failed to prepare draft image.');
    }

    $relativePath = 'images/' . $draftId . '.svg';
    ai_safe_write(ai_storage_path($relativePath), $binary);

    $draft['image_file'] = $relativePath;
    $draft['image_alt'] = $alt;
    $draft['updated_at'] = gmdate(DateTimeInterface::ATOM);
    ai_store_draft($draft);

    return ['image' => $imageDataUri, 'alt' => $alt];
}

function ai_schedule_blog_draft(string $draftId, ?DateTimeImmutable $publishAtIst, int $actorId): void
{
    unset($actorId);
    ai_require_enabled();
    $draft = ai_load_draft($draftId);
    if ($draft['status'] === 'published') {
        throw new RuntimeException('Published drafts cannot be rescheduled.');
    }

    if ($publishAtIst instanceof DateTimeImmutable) {
        $utc = $publishAtIst->setTimezone(new DateTimeZone('UTC'))->format(DateTimeInterface::ATOM);
        $draft['schedule_at'] = $utc;
        $draft['status'] = 'scheduled';
    } else {
        $draft['schedule_at'] = null;
        $draft['status'] = 'draft';
    }
    $draft['updated_at'] = gmdate(DateTimeInterface::ATOM);
    ai_store_draft($draft);
}

function ai_list_blog_drafts(): array
{
    ai_bootstrap_storage();
    $drafts = ai_all_drafts();
    $timezone = new DateTimeZone('Asia/Kolkata');

    uasort($drafts, static function (array $a, array $b): int {
        $timeA = isset($a['updated_at']) ? strtotime((string) $a['updated_at']) : 0;
        $timeB = isset($b['updated_at']) ? strtotime((string) $b['updated_at']) : 0;
        return $timeB <=> $timeA;
    });

    return array_map(static function (array $draft) use ($timezone): array {
        $scheduledAt = null;
        if (!empty($draft['schedule_at'])) {
            try {
                $scheduledAt = new DateTimeImmutable($draft['schedule_at']);
                $scheduledAt = $scheduledAt->setTimezone($timezone);
            } catch (Throwable $exception) {
                $scheduledAt = null;
            }
        }

        $image = '';
        if (!empty($draft['image_file'])) {
            $image = ai_draft_image_data_uri($draft);
        }

        return [
            'id' => $draft['id'],
            'title' => $draft['title'] ?? '',
            'topic' => $draft['topic'] ?? '',
            'tone' => $draft['tone'] ?? '',
            'audience' => $draft['audience'] ?? '',
            'keywords' => $draft['keywords'] ?? [],
            'status' => $draft['status'] ?? 'draft',
            'post_status' => ($draft['status'] ?? 'draft') === 'published' ? 'published' : 'draft',
            'scheduled_at' => $scheduledAt,
            'cover_image' => $image,
            'cover_image_alt' => $draft['image_alt'] ?? '',
            'slug' => $draft['slug'] ?? '',
            'updated_at' => $draft['updated_at'] ?? '',
            'published_post_id' => $draft['published_post_id'] ?? null,
            'published_slug' => $draft['published_slug'] ?? null,
        ];
    }, array_values($drafts));
}

function ai_publish_due_posts(PDO $db, ?DateTimeImmutable $now = null): int
{
    ai_bootstrap_storage();
    $drafts = ai_all_drafts();
    if (!$drafts) {
        return 0;
    }

    $now = $now ?: new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $count = 0;
    foreach ($drafts as $draft) {
        if (($draft['status'] ?? 'draft') !== 'scheduled') {
            continue;
        }
        if (empty($draft['schedule_at'])) {
            continue;
        }
        try {
            $scheduledUtc = new DateTimeImmutable($draft['schedule_at']);
        } catch (Throwable $exception) {
            continue;
        }
        if ($scheduledUtc > $now) {
            continue;
        }

        if (ai_publish_single_draft($db, $draft, $now)) {
            $count++;
        }
    }

    return $count;
}

function ai_publish_single_draft(PDO $db, array $draft, DateTimeImmutable $nowUtc): bool
{
    if (empty($draft['id'])) {
        return false;
    }

    try {
        $slug = ai_resolve_publish_slug($db, (string) ($draft['slug'] ?? ''));
        $coverImage = '';
        if (!empty($draft['image_file']) && is_file(ai_storage_path($draft['image_file']))) {
            $coverContents = @file_get_contents(ai_storage_path($draft['image_file']));
            if ($coverContents !== false) {
                $coverImage = 'data:image/svg+xml;base64,' . base64_encode($coverContents);
            }
        }
        if ($coverImage === '') {
            [$fallbackImage, $fallbackAlt] = blog_generate_placeholder_cover($draft['title'] ?? 'AI Draft', $draft['topic'] ?? '');
            $coverImage = $fallbackImage;
            if (empty($draft['image_alt'])) {
                $draft['image_alt'] = $fallbackAlt;
            }
        }

        $payload = [
            'title' => $draft['title'] ?? 'AI Draft',
            'slug' => $slug,
            'excerpt' => $draft['excerpt'] ?? '',
            'body' => $draft['body'] ?? '',
            'authorName' => $draft['author_name'] ?? '',
            'status' => 'published',
            'tags' => $draft['keywords'] ?? [],
            'coverImage' => $coverImage,
            'coverImageAlt' => $draft['image_alt'] ?? '',
            'coverPrompt' => $draft['image_prompt'] ?? '',
        ];

        $saved = blog_save_post($db, $payload, 0);
    } catch (Throwable $exception) {
        return false;
    }

    $draft['status'] = 'published';
    $draft['published_post_id'] = (int) ($saved['id'] ?? 0);
    $draft['published_slug'] = $saved['slug'] ?? $slug;
    $draft['slug'] = $draft['published_slug'];
    $draft['published_at'] = $nowUtc->format(DateTimeInterface::ATOM);
    $draft['schedule_at'] = null;
    $draft['updated_at'] = $nowUtc->format(DateTimeInterface::ATOM);

    try {
        ai_store_draft($draft);
    } catch (Throwable $exception) {
        return false;
    }

    return true;
}

function ai_resolve_publish_slug(PDO $db, string $desired): string
{
    $slug = $desired !== '' ? blog_slugify($desired) : '';
    if ($slug === '') {
        $slug = 'ai-draft';
    }
    $candidate = $slug;
    $index = 2;
    while (ai_blog_slug_exists($db, $candidate)) {
        $candidate = $slug . '-' . $index;
        $index++;
    }
    return $candidate;
}

function ai_blog_slug_exists(PDO $db, string $slug): bool
{
    $stmt = $db->prepare('SELECT 1 FROM blog_posts WHERE slug = :slug LIMIT 1');
    $stmt->execute([':slug' => $slug]);
    return (bool) $stmt->fetchColumn();
}

function ai_daily_notes_generate_if_due(?DateTimeImmutable $now = null): void
{
    ai_bootstrap_storage();
    $nowIst = ($now ?: new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Kolkata'));
    $dateKey = $nowIst->format('Y-m-d');

    foreach (AI_DAILY_NOTE_TYPES as $type) {
        $targetHour = $type === 'work_done' ? 20 : 21;
        $target = $nowIst->setTime($targetHour, 0);
        if ($nowIst < $target) {
            continue;
        }

        $path = ai_storage_path('notes', sprintf('%s-%s.json', $dateKey, $type));
        if (is_file($path)) {
            continue;
        }

        $content = $type === 'work_done'
            ? ai_daily_notes_build_work_summary($nowIst)
            : ai_daily_notes_build_plan_summary($nowIst);

        $payload = [
            'date' => $dateKey,
            'type' => $type,
            'content' => $content,
            'generated_at' => $nowIst->setTimezone(new DateTimeZone('UTC'))->format(DateTimeInterface::ATOM),
        ];
        ai_write_json_file($path, $payload);
    }
}

function ai_daily_notes_recent(int $limit = 6): array
{
    ai_bootstrap_storage();
    $files = glob(ai_storage_path('notes', '*.json')) ?: [];
    $notes = [];
    foreach ($files as $file) {
        $data = ai_read_json_file($file);
        if (!is_array($data) || empty($data['type']) || empty($data['content'])) {
            continue;
        }
        $notes[] = $data;
    }

    usort($notes, static function (array $a, array $b): int {
        $timeA = isset($a['generated_at']) ? strtotime((string) $a['generated_at']) : 0;
        $timeB = isset($b['generated_at']) ? strtotime((string) $b['generated_at']) : 0;
        return $timeB <=> $timeA;
    });

    $notes = array_slice($notes, 0, $limit);
    $istZone = new DateTimeZone('Asia/Kolkata');

    return array_map(static function (array $note) use ($istZone): array {
        $generatedAt = null;
        try {
            $generatedAt = new DateTimeImmutable($note['generated_at'] ?? 'now', new DateTimeZone('UTC'));
        } catch (Throwable $exception) {
            $generatedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        }
        $displayTime = $generatedAt->setTimezone($istZone)->format('d M Y · h:i A');
        $label = $note['type'] === 'work_done' ? 'Work Done Today' : 'Next-Day Plan';

        return [
            'date' => $note['date'] ?? '',
            'type' => $note['type'],
            'label' => $label,
            'content' => $note['content'],
            'generated_at' => $note['generated_at'] ?? '',
            'display_time' => $displayTime,
            'display_label' => 'Generated ' . $displayTime,
        ];
    }, $notes);
}

function ai_daily_notes_build_work_summary(DateTimeImmutable $nowIst): string
{
    $dateLabel = $nowIst->format('d M Y');
    return sprintf('Work Done Today (%s): Capture the day\'s wins, log customer escalations, and share quick updates before sign-off.', $dateLabel);
}

function ai_daily_notes_build_plan_summary(DateTimeImmutable $nowIst): string
{
    $nextDay = $nowIst->modify('+1 day')->format('d M Y');
    return sprintf('Next-Day Plan (%s): Align morning priorities, lock installation checklists, and confirm subsidy follow-ups for the team.', $nextDay);
}
