<?php
declare(strict_types=1);

const AI_DAILY_NOTE_TYPES = ['work_done', 'next_plan'];

function ai_settings_row(PDO $db): array
{
    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS ai_settings (
    id INTEGER PRIMARY KEY CHECK (id = 1),
    enabled INTEGER NOT NULL DEFAULT 0,
    provider TEXT NOT NULL DEFAULT 'Gemini',
    api_key_hash TEXT,
    text_model TEXT,
    image_model TEXT,
    last_test_result TEXT,
    last_tested_at TEXT,
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
)
SQL
    );
    $db->exec("INSERT OR IGNORE INTO ai_settings(id, enabled, provider) VALUES (1, 0, 'Gemini')");

    $stmt = $db->query('SELECT enabled, provider, api_key_hash, text_model, image_model, last_test_result, last_tested_at, updated_at FROM ai_settings WHERE id = 1');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: [
        'enabled' => 0,
        'provider' => 'Gemini',
        'api_key_hash' => null,
        'text_model' => '',
        'image_model' => '',
        'last_test_result' => null,
        'last_tested_at' => null,
        'updated_at' => null,
    ];
}

function ai_get_settings(PDO $db): array
{
    $row = ai_settings_row($db);
    return [
        'enabled' => (bool) ($row['enabled'] ?? 0),
        'provider' => $row['provider'] ?? 'Gemini',
        'text_model' => $row['text_model'] ?? '',
        'image_model' => $row['image_model'] ?? '',
        'has_api_key' => isset($row['api_key_hash']) && $row['api_key_hash'] !== null && $row['api_key_hash'] !== '',
        'last_test_result' => $row['last_test_result'] ?? null,
        'last_tested_at' => $row['last_tested_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function ai_save_settings(PDO $db, array $input, int $actorId): array
{
    $enabled = !empty($input['enabled']);
    $provider = trim((string) ($input['provider'] ?? 'Gemini'));
    if ($provider === '') {
        $provider = 'Gemini';
    }
    $textModel = trim((string) ($input['text_model'] ?? ''));
    $imageModel = trim((string) ($input['image_model'] ?? ''));
    $apiKey = trim((string) ($input['api_key'] ?? ''));

    $db->beginTransaction();
    try {
        $update = $db->prepare('UPDATE ai_settings SET enabled = :enabled, provider = :provider, text_model = :text_model, image_model = :image_model, updated_at = datetime(\'now\') WHERE id = 1');
        $update->execute([
            ':enabled' => $enabled ? 1 : 0,
            ':provider' => $provider,
            ':text_model' => $textModel !== '' ? $textModel : null,
            ':image_model' => $imageModel !== '' ? $imageModel : null,
        ]);

        if ($apiKey !== '') {
            $hash = password_hash($apiKey, PASSWORD_DEFAULT);
            $keyStmt = $db->prepare('UPDATE ai_settings SET api_key_hash = :hash, last_test_result = NULL, last_tested_at = NULL WHERE id = 1');
            $keyStmt->execute([':hash' => $hash]);
        }

        $log = $db->prepare('INSERT INTO audit_logs(actor_id, action, entity_type, entity_id, description) VALUES(:actor_id, :action, :entity_type, :entity_id, :description)');
        $log->execute([
            ':actor_id' => audit_resolve_actor_id($db, $actorId),
            ':action' => 'ai.settings',
            ':entity_type' => 'ai',
            ':entity_id' => 1,
            ':description' => 'Updated AI Studio settings',
        ]);

        $db->commit();
    } catch (Throwable $exception) {
        $db->rollBack();
        throw $exception;
    }

    return ai_get_settings($db);
}

function ai_test_connection(PDO $db): array
{
    $settings = ai_get_settings($db);
    $pass = $settings['enabled'] && $settings['has_api_key'] && $settings['text_model'] !== '' && $settings['image_model'] !== '';
    $result = $pass ? 'pass' : 'fail';
    $message = $pass
        ? 'PASS — Configuration looks healthy. AI tools are ready.'
        : 'FAIL — Provide provider details, models, and a valid API key.';

    $stmt = $db->prepare('UPDATE ai_settings SET last_test_result = :result, last_tested_at = datetime(\'now\') WHERE id = 1');
    $stmt->execute([':result' => $result]);

    return ['status' => $result, 'message' => $message];
}

function ai_require_enabled(PDO $db): void
{
    $settings = ai_get_settings($db);
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

function ai_generate_blog_draft_from_prompt(PDO $db, string $prompt, int $actorId): array
{
    ai_require_enabled($db);
    ai_blog_tables($db);

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
        'cover_image' => '',
        'cover_image_alt' => '',
        'author_name' => '',
    ];

    $saved = ai_save_blog_draft($db, $payload, $actorId);
    $draftId = (int) $saved['draftId'];

    $updatePrompt = $db->prepare("UPDATE ai_blog_drafts SET image_prompt = :prompt, updated_at = datetime('now') WHERE id = :id");
    $updatePrompt->execute([
        ':prompt' => $content['image_prompt'],
        ':id' => $draftId,
    ]);

    $image = ai_generate_image_for_draft($db, $draftId, $actorId);

    return [
        'post_id' => (int) $saved['id'],
        'draft_id' => $draftId,
        'title' => $saved['title'],
        'image' => $image['image'] ?? '',
    ];
}

function ai_blog_tables(PDO $db): void
{
    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS ai_blog_drafts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    blog_post_id INTEGER NOT NULL UNIQUE,
    topic TEXT NOT NULL,
    tone TEXT,
    audience TEXT,
    keywords TEXT,
    purpose TEXT,
    generated_title TEXT,
    generated_body TEXT,
    status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft','scheduled','published')),
    scheduled_publish_at TEXT,
    image_url TEXT,
    image_alt TEXT,
    image_prompt TEXT,
    created_by INTEGER,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY(blog_post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    FOREIGN KEY(created_by) REFERENCES users(id)
)
SQL
    );
}

function ai_save_blog_draft(PDO $db, array $input, int $actorId): array
{
    ai_require_enabled($db);
    ai_blog_tables($db);
    if (function_exists('blog_ensure_backup_table')) {
        blog_ensure_backup_table($db);
    }

    $postId = isset($input['post_id']) ? (int) $input['post_id'] : null;
    $draftId = isset($input['draft_id']) ? (int) $input['draft_id'] : null;
    $title = trim((string) ($input['title'] ?? ''));
    $body = (string) ($input['body'] ?? '');
    $excerpt = trim((string) ($input['excerpt'] ?? ''));
    $topic = trim((string) ($input['topic'] ?? ''));
    $tone = trim((string) ($input['tone'] ?? ''));
    $audience = trim((string) ($input['audience'] ?? ''));
    $purpose = trim((string) ($input['purpose'] ?? ''));
    $generatedTitle = trim((string) ($input['generated_title'] ?? $title));
    $generatedBody = (string) ($input['generated_body'] ?? $body);
    $keywords = ai_normalize_keywords($input['keywords'] ?? []);

    if ($title === '' || trim(strip_tags($body)) === '') {
        throw new RuntimeException('Provide a title and body before saving the draft.');
    }
    if ($topic === '') {
        $topic = $title;
    }

    $authorName = trim((string) ($input['author_name'] ?? ''));

    $blogPayload = [
        'id' => $postId ?: null,
        'title' => $title,
        'slug' => $input['slug'] ?? '',
        'excerpt' => $excerpt !== '' ? $excerpt : null,
        'body' => $body,
        'authorName' => $authorName,
        'status' => 'draft',
        'tags' => $keywords,
        'coverImage' => $input['cover_image'] ?? '',
        'coverImageAlt' => $input['cover_image_alt'] ?? '',
        'coverPrompt' => 'AI Studio auto cover',
    ];

    $saved = blog_save_post($db, $blogPayload, $actorId);
    $postId = (int) $saved['id'];

    $db->beginTransaction();
    try {
        $row = $db->prepare('SELECT id FROM ai_blog_drafts WHERE blog_post_id = :post_id');
        $row->execute([':post_id' => $postId]);
        $existingId = $row->fetchColumn();

        if ($existingId) {
            $update = $db->prepare(<<<'SQL'
UPDATE ai_blog_drafts
SET topic = :topic,
    tone = :tone,
    audience = :audience,
    keywords = :keywords,
    purpose = :purpose,
    generated_title = :generated_title,
    generated_body = :generated_body,
    status = CASE WHEN status = 'published' THEN status ELSE 'draft' END,
    updated_at = datetime('now')
WHERE blog_post_id = :post_id
SQL
            );
            $update->execute([
                ':topic' => $topic,
                ':tone' => $tone !== '' ? $tone : null,
                ':audience' => $audience !== '' ? $audience : null,
                ':keywords' => json_encode($keywords, JSON_UNESCAPED_UNICODE),
                ':purpose' => $purpose !== '' ? $purpose : null,
                ':generated_title' => $generatedTitle !== '' ? $generatedTitle : null,
                ':generated_body' => $generatedBody !== '' ? $generatedBody : null,
                ':post_id' => $postId,
            ]);
            $draftId = (int) $existingId;
        } else {
            $insert = $db->prepare(<<<'SQL'
INSERT INTO ai_blog_drafts (blog_post_id, topic, tone, audience, keywords, purpose, generated_title, generated_body, status, created_by)
VALUES (:post_id, :topic, :tone, :audience, :keywords, :purpose, :generated_title, :generated_body, 'draft', :created_by)
SQL
            );
            $insert->execute([
                ':post_id' => $postId,
                ':topic' => $topic,
                ':tone' => $tone !== '' ? $tone : null,
                ':audience' => $audience !== '' ? $audience : null,
                ':keywords' => json_encode($keywords, JSON_UNESCAPED_UNICODE),
                ':purpose' => $purpose !== '' ? $purpose : null,
                ':generated_title' => $generatedTitle !== '' ? $generatedTitle : null,
                ':generated_body' => $generatedBody !== '' ? $generatedBody : null,
                ':created_by' => $actorId > 0 ? $actorId : null,
            ]);
            $draftId = (int) $db->lastInsertId();
        }

        $db->commit();
    } catch (Throwable $exception) {
        $db->rollBack();
        throw $exception;
    }

    $saved['draftId'] = $draftId;
    return $saved;
}

function ai_list_blog_drafts(PDO $db): array
{
    ai_blog_tables($db);
    ai_publish_due_posts($db);

    $stmt = $db->query(<<<'SQL'
SELECT
    ai_blog_drafts.id,
    ai_blog_drafts.topic,
    ai_blog_drafts.tone,
    ai_blog_drafts.audience,
    ai_blog_drafts.keywords,
    ai_blog_drafts.status,
    ai_blog_drafts.scheduled_publish_at,
    ai_blog_drafts.image_url,
    ai_blog_drafts.image_alt,
    blog_posts.id AS post_id,
    blog_posts.title,
    blog_posts.updated_at,
    blog_posts.status AS post_status,
    blog_posts.cover_image,
    blog_posts.cover_image_alt,
    blog_posts.slug
FROM ai_blog_drafts
INNER JOIN blog_posts ON blog_posts.id = ai_blog_drafts.blog_post_id
WHERE blog_posts.status IN ('draft','pending','published')
ORDER BY blog_posts.updated_at DESC
SQL
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $timezone = new DateTimeZone('Asia/Kolkata');

    return array_map(static function (array $row) use ($timezone): array {
        $keywords = [];
        if (!empty($row['keywords'])) {
            $decoded = json_decode((string) $row['keywords'], true);
            if (is_array($decoded)) {
                $keywords = array_values(array_filter(array_map('trim', $decoded)));
            }
        }
        $scheduledAt = null;
        if (!empty($row['scheduled_publish_at'])) {
            try {
                $dt = new DateTimeImmutable((string) $row['scheduled_publish_at'], new DateTimeZone('UTC'));
                $scheduledAt = $dt->setTimezone($timezone);
            } catch (Throwable $exception) {
                $scheduledAt = null;
            }
        }
        return [
            'id' => (int) $row['id'],
            'post_id' => (int) $row['post_id'],
            'title' => $row['title'],
            'topic' => $row['topic'],
            'tone' => $row['tone'] ?? '',
            'audience' => $row['audience'] ?? '',
            'keywords' => $keywords,
            'status' => $row['status'],
            'post_status' => $row['post_status'],
            'scheduled_at' => $scheduledAt,
            'image_url' => $row['image_url'] ?? '',
            'image_alt' => $row['image_alt'] ?? '',
            'cover_image' => $row['cover_image'] ?? '',
            'cover_image_alt' => $row['cover_image_alt'] ?? '',
            'slug' => $row['slug'] ?? '',
            'updated_at' => $row['updated_at'],
        ];
    }, $rows);
}

function ai_generate_image_for_draft(PDO $db, int $draftId, int $actorId): array
{
    ai_require_enabled($db);
    ai_blog_tables($db);

    $stmt = $db->prepare(<<<'SQL'
SELECT ai_blog_drafts.id, ai_blog_drafts.topic, ai_blog_drafts.image_prompt, blog_posts.id AS post_id, blog_posts.title
FROM ai_blog_drafts
INNER JOIN blog_posts ON blog_posts.id = ai_blog_drafts.blog_post_id
WHERE ai_blog_drafts.id = :id
LIMIT 1
SQL
    );
    $stmt->execute([':id' => $draftId]);
    $draft = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$draft) {
        throw new RuntimeException('Draft not found.');
    }

    $title = $draft['title'] ?? $draft['topic'] ?? 'Blog draft';
    $prompt = $draft['image_prompt'] ?? ($draft['topic'] ?? 'Clean energy insight');
    [$image, $alt] = blog_generate_placeholder_cover($title, (string) $prompt);

    $db->beginTransaction();
    try {
        $updatePost = $db->prepare("UPDATE blog_posts SET cover_image = :image, cover_image_alt = :alt, updated_at = datetime('now') WHERE id = :id");
        $updatePost->execute([
            ':image' => $image,
            ':alt' => $alt,
            ':id' => (int) $draft['post_id'],
        ]);

        $updateDraft = $db->prepare(<<<'SQL'
UPDATE ai_blog_drafts
SET image_url = :image,
    image_alt = :alt,
    image_prompt = :prompt,
    updated_at = datetime('now')
WHERE id = :id
SQL
        );
        $updateDraft->execute([
            ':image' => $image,
            ':alt' => $alt,
            ':prompt' => $prompt,
            ':id' => $draftId,
        ]);

        $log = $db->prepare('INSERT INTO audit_logs(actor_id, action, entity_type, entity_id, description) VALUES(:actor_id, :action, :entity_type, :entity_id, :description)');
        $log->execute([
            ':actor_id' => audit_resolve_actor_id($db, $actorId),
            ':action' => 'ai.image',
            ':entity_type' => 'blog_post',
            ':entity_id' => (int) $draft['post_id'],
            ':description' => 'Generated AI cover image for blog draft',
        ]);

        $db->commit();
    } catch (Throwable $exception) {
        $db->rollBack();
        throw $exception;
    }

    return ['image' => $image, 'alt' => $alt];
}

function ai_schedule_blog_draft(PDO $db, int $draftId, ?DateTimeImmutable $publishAtIst, int $actorId): void
{
    ai_blog_tables($db);

    $stmt = $db->prepare('SELECT blog_post_id FROM ai_blog_drafts WHERE id = :id');
    $stmt->execute([':id' => $draftId]);
    $postId = $stmt->fetchColumn();
    if (!$postId) {
        throw new RuntimeException('Draft not found.');
    }

    $utcTime = null;
    if ($publishAtIst instanceof DateTimeImmutable) {
        $utcTime = $publishAtIst->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    $status = $utcTime ? 'scheduled' : 'draft';

    $update = $db->prepare(<<<'SQL'
UPDATE ai_blog_drafts
SET scheduled_publish_at = :scheduled,
    status = :status,
    updated_at = datetime('now')
WHERE id = :id
SQL
    );
    $update->execute([
        ':scheduled' => $utcTime,
        ':status' => $status,
        ':id' => $draftId,
    ]);

    $log = $db->prepare('INSERT INTO audit_logs(actor_id, action, entity_type, entity_id, description) VALUES(:actor_id, :action, :entity_type, :entity_id, :description)');
    $log->execute([
        ':actor_id' => audit_resolve_actor_id($db, $actorId),
        ':action' => 'ai.schedule',
        ':entity_type' => 'blog_post',
        ':entity_id' => (int) $postId,
        ':description' => $utcTime ? 'Scheduled AI blog draft for publishing' : 'Cleared AI blog draft schedule',
    ]);
}

function ai_publish_due_posts(PDO $db, ?DateTimeImmutable $now = null): int
{
    ai_blog_tables($db);
    $now = $now ?: new DateTimeImmutable('now', new DateTimeZone('UTC'));

    $stmt = $db->prepare(<<<'SQL'
SELECT ai_blog_drafts.id, ai_blog_drafts.blog_post_id
FROM ai_blog_drafts
INNER JOIN blog_posts ON blog_posts.id = ai_blog_drafts.blog_post_id
WHERE ai_blog_drafts.status = 'scheduled'
  AND ai_blog_drafts.scheduled_publish_at IS NOT NULL
  AND ai_blog_drafts.scheduled_publish_at <= :now
SQL
    );
    $stmt->execute([':now' => $now->format('Y-m-d H:i:s')]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        return 0;
    }

    $count = 0;
    foreach ($rows as $row) {
        $postId = (int) $row['blog_post_id'];
        blog_publish_post($db, $postId, true, 0);
        $update = $db->prepare("UPDATE ai_blog_drafts SET status = 'published', scheduled_publish_at = NULL, updated_at = datetime('now') WHERE id = :id");
        $update->execute([':id' => (int) $row['id']]);
        $count++;
    }

    return $count;
}

function ai_daily_notes_table(PDO $db): void
{
    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS ai_daily_notes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    note_date TEXT NOT NULL,
    note_type TEXT NOT NULL CHECK(note_type IN ('work_done','next_plan')),
    content TEXT NOT NULL,
    generated_at TEXT NOT NULL DEFAULT (datetime('now')),
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(note_date, note_type)
)
SQL
    );
}

function ai_daily_notes_generate_if_due(PDO $db, ?DateTimeImmutable $now = null): void
{
    ai_daily_notes_table($db);

    $now = $now ?: new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $istZone = new DateTimeZone('Asia/Kolkata');
    $istNow = $now->setTimezone($istZone);
    $todayDate = $istNow->format('Y-m-d');

    $schedule = [
        'work_done' => ['hour' => 20, 'minute' => 0],
        'next_plan' => ['hour' => 21, 'minute' => 0],
    ];

    foreach ($schedule as $type => $slot) {
        $target = new DateTimeImmutable(sprintf('%s %02d:%02d:00', $todayDate, $slot['hour'], $slot['minute']), $istZone);
        if ($istNow < $target) {
            continue;
        }

        $exists = $db->prepare('SELECT 1 FROM ai_daily_notes WHERE note_date = :date AND note_type = :type LIMIT 1');
        $exists->execute([
            ':date' => $todayDate,
            ':type' => $type,
        ]);
        if ($exists->fetchColumn()) {
            continue;
        }

        $content = $type === 'work_done'
            ? ai_daily_notes_build_work_summary($db, $istNow)
            : ai_daily_notes_build_plan_summary($db, $istNow);

        if (trim($content) === '') {
            $content = 'No significant updates recorded yet. Check again after data sync.';
        }

        $insert = $db->prepare('INSERT INTO ai_daily_notes(note_date, note_type, content, generated_at, created_at) VALUES(:date, :type, :content, datetime(\'now\'), datetime(\'now\'))');
        $insert->execute([
            ':date' => $todayDate,
            ':type' => $type,
            ':content' => $content,
        ]);
    }
}

function ai_daily_notes_build_work_summary(PDO $db, DateTimeImmutable $nowIst): string
{
    $istZone = new DateTimeZone('Asia/Kolkata');
    $startIst = $nowIst->setTimezone($istZone)->setTime(0, 0);
    $endIst = $startIst->modify('+1 day');
    $startUtc = $startIst->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $endUtc = $endIst->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

    $queries = [
        'leads' => 'SELECT COUNT(*) FROM crm_leads WHERE updated_at >= :start AND updated_at < :end',
        'installations' => 'SELECT COUNT(*) FROM installations WHERE updated_at >= :start AND updated_at < :end',
        'complaints' => 'SELECT COUNT(*) FROM complaints WHERE updated_at >= :start AND updated_at < :end',
        'subsidy' => 'SELECT COUNT(*) FROM subsidy_tracker WHERE updated_at >= :start AND updated_at < :end',
        'reminders' => "SELECT COUNT(*) FROM reminders WHERE updated_at >= :start AND updated_at < :end AND deleted_at IS NULL",
    ];

    $counts = [];
    foreach ($queries as $key => $sql) {
        $stmt = $db->prepare($sql);
        $stmt->execute([':start' => $startUtc, ':end' => $endUtc]);
        $counts[$key] = (int) $stmt->fetchColumn();
    }

    $phrases = [
        'leads' => sprintf('%d lead %s touched', $counts['leads'], $counts['leads'] === 1 ? 'record' : 'records'),
        'installations' => sprintf('%d installation %s progressed', $counts['installations'], $counts['installations'] === 1 ? 'site' : 'sites'),
        'complaints' => sprintf('%d complaint %s updated', $counts['complaints'], $counts['complaints'] === 1 ? 'case' : 'cases'),
        'subsidy' => sprintf('%d subsidy %s logged', $counts['subsidy'], $counts['subsidy'] === 1 ? 'stage' : 'stages'),
        'reminders' => sprintf('%d reminder %s actioned', $counts['reminders'], $counts['reminders'] === 1 ? 'item' : 'items'),
    ];

    $summary = sprintf(
        'Work Done Today: %s. Focus on quality follow-through as teams close their day.',
        implode(', ', $phrases)
    );

    return $summary;
}

function ai_daily_notes_build_plan_summary(PDO $db, DateTimeImmutable $nowIst): string
{
    $startNextIst = $nowIst->setTimezone(new DateTimeZone('Asia/Kolkata'))->modify('+1 day')->setTime(0, 0);
    $endNextIst = $startNextIst->modify('+1 day');
    $startNextUtc = $startNextIst->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $endNextUtc = $endNextIst->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

    $pendingLeads = (int) $db->query("SELECT COUNT(*) FROM crm_leads WHERE status IN ('new','visited','quotation')")->fetchColumn();
    $activeInstallations = (int) $db->query("SELECT COUNT(*) FROM installations WHERE stage != 'commissioned' AND status != 'cancelled'")->fetchColumn();
    $openComplaints = (int) $db->query("SELECT COUNT(*) FROM complaints WHERE status != 'closed'")->fetchColumn();
    $pendingSubsidyStmt = $db->query(<<<'SQL'
WITH ranked AS (
    SELECT application_reference, stage, stage_date, id,
           ROW_NUMBER() OVER (PARTITION BY application_reference ORDER BY stage_date DESC, id DESC) AS rn
    FROM subsidy_tracker
)
SELECT COUNT(*) FROM ranked WHERE rn = 1 AND stage != 'disbursed'
SQL
    );
    $pendingSubsidy = (int) ($pendingSubsidyStmt ? $pendingSubsidyStmt->fetchColumn() : 0);

    $dueTomorrowStmt = $db->prepare(<<<'SQL'
SELECT COUNT(*) FROM reminders
WHERE status IN ('proposed','active')
  AND deleted_at IS NULL
  AND due_at >= :start
  AND due_at < :end
SQL
    );
    $dueTomorrowStmt->execute([':start' => $startNextUtc, ':end' => $endNextUtc]);
    $dueTomorrow = (int) $dueTomorrowStmt->fetchColumn();

    $lines = [];
    $lines[] = sprintf('Leads: %d awaiting conversion pushes.', $pendingLeads);
    $lines[] = sprintf('Installations: %d projects still in-flight.', $activeInstallations);
    $lines[] = sprintf('Complaints: %d open tickets to resolve quickly.', $openComplaints);
    $lines[] = sprintf('Subsidy: %d applications pending approval or disbursal.', $pendingSubsidy);
    $lines[] = sprintf('Reminders: %d follow-ups due tomorrow.', $dueTomorrow);

    return 'Next-Day Plan: ' . implode(' ', $lines) . ' Align owners before 10 AM IST stand-up.';
}

function ai_daily_notes_recent(PDO $db, int $limit = 6): array
{
    ai_daily_notes_table($db);
    $stmt = $db->prepare('SELECT note_date, note_type, content, generated_at FROM ai_daily_notes ORDER BY generated_at DESC LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $istZone = new DateTimeZone('Asia/Kolkata');

    return array_map(static function (array $row) use ($istZone): array {
        $generatedAt = null;
        try {
            $generatedAt = new DateTimeImmutable($row['generated_at'], new DateTimeZone('UTC'));
        } catch (Throwable $exception) {
            $generatedAt = null;
        }
        $displayTime = $generatedAt ? $generatedAt->setTimezone($istZone)->format('d M Y · h:i A') : '';
        $label = $row['note_type'] === 'work_done' ? 'Work Done Today' : 'Next-Day Plan';
        return [
            'date' => $row['note_date'],
            'type' => $row['note_type'],
            'label' => $label,
            'content' => $row['content'],
            'generated_at' => $row['generated_at'],
            'display_time' => $displayTime,
            'display_label' => $displayTime !== '' ? 'Generated ' . $displayTime : 'Generated recently',
        ];
    }, $rows);
}
