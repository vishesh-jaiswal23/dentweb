<?php
declare(strict_types=1);

function blog_slugify(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return bin2hex(random_bytes(6));
    }

    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text) ?? '';
    $text = trim($text, '-');

    return $text !== '' ? $text : bin2hex(random_bytes(6));
}

function blog_normalize_tag(string $tag): string
{
    $tag = trim($tag);
    $tag = preg_replace('/\s+/', ' ', $tag) ?? '';
    return $tag;
}

function blog_sanitize_html(string $html): string
{
    $allowedTags = [
        'p', 'ul', 'ol', 'li', 'strong', 'em', 'a', 'blockquote', 'h2', 'h3', 'h4', 'figure', 'figcaption', 'code', 'pre', 'br'
    ];
    $allowedAttrs = [
        'a' => ['href', 'title'],
        'figure' => ['class'],
        'code' => ['class'],
        'pre' => ['class'],
    ];

    if (!class_exists('DOMDocument')) {
        $plain = blog_extract_plain_text($html);
        return $plain === ''
            ? ''
            : nl2br(htmlspecialchars($plain, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'));
    }

    $document = new DOMDocument();
    libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8" ?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $walker = function (DOMNode $node) use (&$walker, $allowedTags, $allowedAttrs) {
        if ($node instanceof DOMElement) {
            $tag = strtolower($node->tagName);
            if (!in_array($tag, $allowedTags, true)) {
                if ($node->parentNode) {
                    while ($node->firstChild) {
                        $node->parentNode->insertBefore($node->firstChild, $node);
                    }
                    $node->parentNode->removeChild($node);
                }
                return;
            }

            if (!empty($allowedAttrs[$tag])) {
                foreach (iterator_to_array($node->attributes) as $attr) {
                    $name = strtolower($attr->name);
                    if (!in_array($name, $allowedAttrs[$tag], true)) {
                        $node->removeAttributeNode($attr);
                        continue;
                    }
                    if ($tag === 'a' && $name === 'href') {
                        $href = trim($attr->value);
                        if ($href === '' || !preg_match('/^(https?:|mailto:)/i', $href)) {
                            $node->removeAttribute('href');
                        } else {
                            $node->setAttribute('rel', 'noopener');
                        }
                    }
                }
            } else {
                foreach (iterator_to_array($node->attributes) as $attr) {
                    $node->removeAttributeNode($attr);
                }
            }
        }

        foreach (iterator_to_array($node->childNodes) as $child) {
            $walker($child);
        }
    };

    foreach (iterator_to_array($document->documentElement->childNodes) as $child) {
        $walker($child);
    }

    $innerHTML = '';
    foreach ($document->documentElement->childNodes as $child) {
        $innerHTML .= $document->saveHTML($child);
    }

    return $innerHTML;
}

function blog_extract_plain_text(string $html): string
{
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text) ?? '';
    return trim($text);
}

function blog_generate_placeholder_cover(string $title, string $prompt = ''): array
{
    $baseTitle = trim($title) !== '' ? trim($title) : 'Dakshayani Blog';
    $promptText = trim($prompt) !== '' ? trim($prompt) : 'Clean energy insights';
    $primary = function_exists('mb_substr') ? mb_substr($baseTitle, 0, 64) : substr($baseTitle, 0, 64);
    $secondary = function_exists('mb_substr') ? mb_substr($promptText, 0, 64) : substr($promptText, 0, 64);
    $primaryEscaped = htmlspecialchars($primary, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    $secondaryEscaped = htmlspecialchars($secondary, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

    $svg = <<<SVG
<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1200 630' role='img'>
  <defs>
    <linearGradient id='g' x1='0%' y1='0%' x2='100%' y2='100%'>
      <stop offset='0%' stop-color='#1d4ed8'/>
      <stop offset='100%' stop-color='#0ea5e9'/>
    </linearGradient>
  </defs>
  <rect fill='url(#g)' width='1200' height='630'/>
  <g fill='#ffffff'>
    <text x='50%' y='45%' font-size='64' font-family='Inter, Arial, sans-serif' text-anchor='middle' font-weight='600'>{$primaryEscaped}</text>
    <text x='50%' y='70%' font-size='32' font-family='Inter, Arial, sans-serif' text-anchor='middle' opacity='0.85'>Dakshayani Insights · {$secondaryEscaped}</text>
  </g>
</svg>
SVG;

    $dataUri = 'data:image/svg+xml;base64,' . base64_encode($svg);
    $alt = sprintf('Illustrative cover for %s', $baseTitle);

    return [$dataUri, $alt];
}

function blog_sync_tags(PDO $db, int $postId, array $tags): array
{
    $normalized = [];
    foreach ($tags as $tag) {
        $tag = blog_normalize_tag($tag);
        if ($tag === '') {
            continue;
        }
        $slug = blog_slugify($tag);
        $normalized[$slug] = [
            'name' => $tag,
            'slug' => $slug,
        ];
    }

    $manageTransaction = !$db->inTransaction();
    if ($manageTransaction) {
        $db->beginTransaction();
    }
    try {
        $tagIds = [];
        $select = $db->prepare('SELECT id, name, slug FROM blog_tags WHERE slug = :slug');
        $insert = $db->prepare('INSERT INTO blog_tags(name, slug) VALUES(:name, :slug)');
        foreach ($normalized as $slug => $info) {
            $select->execute([':slug' => $slug]);
            $tagRow = $select->fetch();
            if (!$tagRow) {
                $insert->execute([
                    ':name' => $info['name'],
                    ':slug' => $slug,
                ]);
                $tagId = (int) $db->lastInsertId();
                $tagIds[$slug] = $tagId;
            } else {
                $tagIds[$slug] = (int) $tagRow['id'];
            }
        }

        $db->prepare('DELETE FROM blog_post_tags WHERE post_id = :post_id')->execute([':post_id' => $postId]);

        if ($tagIds) {
            $link = $db->prepare('INSERT OR IGNORE INTO blog_post_tags(post_id, tag_id) VALUES(:post_id, :tag_id)');
            foreach ($tagIds as $tagId) {
                $link->execute([
                    ':post_id' => $postId,
                    ':tag_id' => $tagId,
                ]);
            }
        }

        if ($manageTransaction) {
            $db->commit();
        }
    } catch (Throwable $exception) {
        if ($manageTransaction && $db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }

    return array_values(array_map(static fn ($info) => $info['name'], $normalized));
}

function blog_get_tag_summary(PDO $db): array
{
    $stmt = $db->query(<<<'SQL'
SELECT blog_tags.name, blog_tags.slug, COUNT(blog_post_tags.post_id) AS post_count
FROM blog_tags
INNER JOIN blog_post_tags ON blog_post_tags.tag_id = blog_tags.id
INNER JOIN blog_posts ON blog_posts.id = blog_post_tags.post_id
WHERE blog_posts.status = 'published'
GROUP BY blog_tags.id
ORDER BY blog_tags.name COLLATE NOCASE
SQL
    );
    return $stmt->fetchAll();
}

function blog_fetch_published(PDO $db, array $filters, int $limit, int $offset): array
{

    $conditions = ["blog_posts.status = 'published'"];
    $params = [];

    if (!empty($filters['search'])) {
        $conditions[] = '(LOWER(blog_posts.title) LIKE :search OR LOWER(blog_posts.excerpt) LIKE :search OR LOWER(blog_posts.body_text) LIKE :search)';
        $params[':search'] = '%' . strtolower($filters['search']) . '%';
    }

    if (!empty($filters['tag'])) {
        $conditions[] = 'blog_posts.id IN (
            SELECT blog_post_tags.post_id FROM blog_post_tags
            INNER JOIN blog_tags ON blog_post_tags.tag_id = blog_tags.id
            WHERE blog_tags.slug = :tag
        )';
        $params[':tag'] = $filters['tag'];
    }

    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $countStmt = $db->prepare("SELECT COUNT(DISTINCT blog_posts.id) FROM blog_posts {$where}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $sql = <<<SQL
SELECT
    blog_posts.id,
    blog_posts.title,
    blog_posts.slug,
    blog_posts.excerpt,
    blog_posts.cover_image,
    blog_posts.cover_image_alt,
    blog_posts.author_name,
    blog_posts.published_at,
    GROUP_CONCAT(blog_tags.name, '\u0001') AS tag_names,
    GROUP_CONCAT(blog_tags.slug, '\u0001') AS tag_slugs
FROM blog_posts
LEFT JOIN blog_post_tags ON blog_post_tags.post_id = blog_posts.id
LEFT JOIN blog_tags ON blog_tags.id = blog_post_tags.tag_id
{$where}
GROUP BY blog_posts.id
ORDER BY blog_posts.published_at DESC, blog_posts.id DESC
LIMIT :limit OFFSET :offset
SQL;
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $posts = array_map(static function (array $row): array {
        $tags = [];
        if (!empty($row['tag_names']) && !empty($row['tag_slugs'])) {
            $names = explode("\u0001", (string) $row['tag_names']);
            $slugs = explode("\u0001", (string) $row['tag_slugs']);
            foreach ($names as $index => $name) {
                $slug = $slugs[$index] ?? '';
                if ($name !== '' && $slug !== '') {
                    $tags[] = ['name' => $name, 'slug' => $slug];
                }
            }
        }
        return [
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'slug' => $row['slug'],
            'excerpt' => $row['excerpt'] ?? '',
            'cover_image' => $row['cover_image'] ?? null,
            'cover_image_alt' => $row['cover_image_alt'] ?? null,
            'author_name' => $row['author_name'] ?? null,
            'published_at' => $row['published_at'] ?? null,
            'tags' => $tags,
        ];
    }, $rows ?: []);

    return [
        'total' => $total,
        'posts' => $posts,
    ];
}

function blog_get_latest_update(PDO $db): ?string
{
    $stmt = $db->query("SELECT MAX(updated_at) FROM blog_posts WHERE status = 'published'");
    $timestamp = $stmt->fetchColumn();
    return $timestamp ? (string) $timestamp : null;
}

function blog_get_post_by_slug(PDO $db, string $slug, bool $includeDrafts = false): ?array
{

    $condition = $includeDrafts ? '1=1' : "blog_posts.status = 'published'";
    $stmt = $db->prepare(<<<SQL
SELECT
    blog_posts.*,
    GROUP_CONCAT(blog_tags.name, '\u0001') AS tag_names,
    GROUP_CONCAT(blog_tags.slug, '\u0001') AS tag_slugs
FROM blog_posts
LEFT JOIN blog_post_tags ON blog_post_tags.post_id = blog_posts.id
LEFT JOIN blog_tags ON blog_tags.id = blog_post_tags.tag_id
WHERE blog_posts.slug = :slug AND {$condition}
GROUP BY blog_posts.id
LIMIT 1
SQL
    );
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $tags = [];
    if (!empty($row['tag_names']) && !empty($row['tag_slugs'])) {
        $names = explode("\u0001", (string) $row['tag_names']);
        $slugs = explode("\u0001", (string) $row['tag_slugs']);
        foreach ($names as $index => $name) {
            $slugValue = $slugs[$index] ?? '';
            if ($name !== '' && $slugValue !== '') {
                $tags[] = ['name' => $name, 'slug' => $slugValue];
            }
        }
    }

    unset($row['tag_names'], $row['tag_slugs']);
    $row['tags'] = $tags;
    $row['id'] = (int) $row['id'];
    return $row;
}

function blog_get_adjacent_posts(PDO $db, int $postId): array
{
    $currentStmt = $db->prepare('SELECT published_at FROM blog_posts WHERE id = :id');
    $currentStmt->execute([':id' => $postId]);
    $publishedAt = $currentStmt->fetchColumn();
    if (!$publishedAt) {
        return ['previous' => null, 'next' => null];
    }

    $prev = $db->prepare(<<<'SQL'
SELECT title, slug FROM blog_posts
WHERE status = 'published' AND published_at > :published_at
ORDER BY published_at ASC
LIMIT 1
SQL
    );
    $prev->execute([':published_at' => $publishedAt]);
    $previous = $prev->fetch() ?: null;

    $nextStmt = $db->prepare(<<<'SQL'
SELECT title, slug FROM blog_posts
WHERE status = 'published' AND published_at < :published_at
ORDER BY published_at DESC
LIMIT 1
SQL
    );
    $nextStmt->execute([':published_at' => $publishedAt]);
    $next = $nextStmt->fetch() ?: null;

    return [
        'previous' => $previous,
        'next' => $next,
    ];
}

function blog_related_posts(PDO $db, int $postId, int $limit = 3): array
{
    $stmt = $db->prepare(<<<'SQL'
SELECT DISTINCT related.id, related.title, related.slug, related.excerpt, related.cover_image
FROM blog_posts AS related
INNER JOIN blog_post_tags ON blog_post_tags.post_id = related.id
INNER JOIN blog_post_tags AS current_tags ON current_tags.tag_id = blog_post_tags.tag_id
WHERE current_tags.post_id = :post_id
  AND related.status = 'published'
  AND related.id != :post_id
ORDER BY related.published_at DESC
LIMIT :limit
SQL
    );
    $stmt->bindValue(':post_id', $postId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    if (!$rows && $limit > 0) {
        $fallback = $db->prepare(<<<'SQL'
SELECT id, title, slug, excerpt, cover_image
FROM blog_posts
WHERE status = 'published' AND id != :post_id
ORDER BY published_at DESC
LIMIT :limit
SQL
        );
        $fallback->bindValue(':post_id', $postId, PDO::PARAM_INT);
        $fallback->bindValue(':limit', $limit, PDO::PARAM_INT);
        $fallback->execute();
        $rows = $fallback->fetchAll();
    }

    return array_map(static fn ($row) => [
        'title' => $row['title'],
        'slug' => $row['slug'],
        'excerpt' => $row['excerpt'] ?? '',
        'cover_image' => $row['cover_image'] ?? null,
    ], $rows ?: []);
}

function blog_admin_list(PDO $db): array
{

    $stmt = $db->query(<<<'SQL'
SELECT
    blog_posts.id,
    blog_posts.title,
    blog_posts.slug,
    blog_posts.status,
    blog_posts.published_at,
    blog_posts.updated_at,
    blog_posts.excerpt
FROM blog_posts
ORDER BY blog_posts.updated_at DESC
SQL
    );
    $rows = $stmt->fetchAll();
    return array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'slug' => $row['slug'],
            'status' => $row['status'],
            'excerpt' => $row['excerpt'] ?? '',
            'publishedAt' => $row['published_at'],
            'updatedAt' => $row['updated_at'],
        ];
    }, $rows ?: []);
}

function blog_save_post(PDO $db, array $input, int $actorId): array
{
    $title = trim((string) ($input['title'] ?? ''));
    $excerpt = trim((string) ($input['excerpt'] ?? ''));
    $body = (string) ($input['body'] ?? '');
    $coverImage = trim((string) ($input['coverImage'] ?? ''));
    $coverAlt = trim((string) ($input['coverImageAlt'] ?? ''));
    $coverPrompt = trim((string) ($input['coverPrompt'] ?? ''));
    $author = trim((string) ($input['authorName'] ?? ''));
    $status = (string) ($input['status'] ?? 'draft');
    $slug = trim((string) ($input['slug'] ?? ''));
    $tags = $input['tags'] ?? [];

    if ($title === '') {
        throw new RuntimeException('Title is required.');
    }
    if (!in_array($status, ['draft', 'pending', 'published', 'archived'], true)) {
        $status = 'draft';
    }

    $cleanHtml = blog_sanitize_html($body);
    $plainText = blog_extract_plain_text($cleanHtml);
    if ($plainText === '') {
        throw new RuntimeException('Body content is required.');
    }

    $slug = $slug !== '' ? blog_slugify($slug) : blog_slugify($title);

    if ($coverImage !== '' && !preg_match('#^(https?://|/|data:image/)#i', $coverImage)) {
        $coverImage = '';
    }

    if ($coverImage === '') {
        [$generatedCover, $generatedAlt] = blog_generate_placeholder_cover($title, $coverPrompt !== '' ? $coverPrompt : $excerpt);
        $coverImage = $generatedCover;
        if ($coverAlt === '') {
            $coverAlt = $generatedAlt;
        }
    } elseif ($coverAlt === '') {
        $coverAlt = sprintf('Illustrative cover for %s', $title !== '' ? $title : 'blog post');
    }

    $db->beginTransaction();
    try {
        $postId = isset($input['id']) ? (int) $input['id'] : null;

        if ($postId) {
            $update = $db->prepare(<<<'SQL'
UPDATE blog_posts
SET title = :title,
    slug = :slug,
    excerpt = :excerpt,
    body_html = :body_html,
    body_text = :body_text,
    cover_image = :cover_image,
    cover_image_alt = :cover_image_alt,
    author_name = :author_name,
    status = :status,
    published_at = CASE WHEN :status = 'published' THEN COALESCE(published_at, datetime('now')) ELSE NULL END,
    updated_at = datetime('now')
WHERE id = :id
SQL
            );
            $update->execute([
                ':title' => $title,
                ':slug' => $slug,
                ':excerpt' => $excerpt,
                ':body_html' => $cleanHtml,
                ':body_text' => $plainText,
                ':cover_image' => $coverImage !== '' ? $coverImage : null,
                ':cover_image_alt' => $coverAlt !== '' ? $coverAlt : null,
                ':author_name' => $author !== '' ? $author : null,
                ':status' => $status,
                ':id' => $postId,
            ]);
        } else {
            $insert = $db->prepare(<<<'SQL'
INSERT INTO blog_posts (title, slug, excerpt, body_html, body_text, cover_image, cover_image_alt, author_name, status, published_at)
VALUES (:title, :slug, :excerpt, :body_html, :body_text, :cover_image, :cover_image_alt, :author_name, :status, CASE WHEN :status = 'published' THEN datetime('now') ELSE NULL END)
SQL
            );
            $insert->execute([
                ':title' => $title,
                ':slug' => $slug,
                ':excerpt' => $excerpt,
                ':body_html' => $cleanHtml,
                ':body_text' => $plainText,
                ':cover_image' => $coverImage !== '' ? $coverImage : null,
                ':cover_image_alt' => $coverAlt !== '' ? $coverAlt : null,
                ':author_name' => $author !== '' ? $author : null,
                ':status' => $status,
            ]);
            $postId = (int) $db->lastInsertId();
        }

        $tagNames = blog_sync_tags($db, $postId, is_array($tags) ? $tags : []);

        $log = $db->prepare('INSERT INTO audit_logs(actor_id, action, entity_type, entity_id, description) VALUES(:actor_id, :action, :entity_type, :entity_id, :description)');
        $log->execute([
            ':actor_id' => audit_resolve_actor_id($db, $actorId),
            ':action' => 'blog.save',
            ':entity_type' => 'blog_post',
            ':entity_id' => $postId,
            ':description' => sprintf('Saved blog post "%s" (%s)', $title, $status),
        ]);

        $db->commit();
    } catch (Throwable $exception) {
        $db->rollBack();
        if ($exception instanceof PDOException && str_contains($exception->getMessage(), 'UNIQUE')) {
            throw new RuntimeException('Slug already exists. Choose a different slug.');
        }
        throw $exception;
    }

    return blog_get_post_by_id($db, $postId);
}

function blog_publish_post(PDO $db, int $postId, bool $publish, int $actorId): array
{
    $currentStmt = $db->prepare('SELECT title, cover_image, cover_image_alt FROM blog_posts WHERE id = :id');
    $currentStmt->execute([':id' => $postId]);
    $current = $currentStmt->fetch();
    if (!$current) {
        throw new RuntimeException('Post not found.');
    }

    if ($publish) {
        $existingCover = trim((string) ($current['cover_image'] ?? ''));
        if ($existingCover === '') {
            [$generatedCover, $generatedAlt] = blog_generate_placeholder_cover((string) ($current['title'] ?? ''), '');
            $coverUpdate = $db->prepare(<<<'SQL'
UPDATE blog_posts
SET cover_image = :cover_image,
    cover_image_alt = CASE
        WHEN cover_image_alt IS NULL OR trim(cover_image_alt) = '' THEN :cover_alt
        ELSE cover_image_alt
    END,
    updated_at = datetime('now')
WHERE id = :id
SQL
            );
            $coverUpdate->execute([
                ':cover_image' => $generatedCover,
                ':cover_alt' => $generatedAlt,
                ':id' => $postId,
            ]);
        }
    }

    $status = $publish ? 'published' : 'draft';
    $update = $db->prepare(<<<'SQL'
UPDATE blog_posts
SET status = :status,
    published_at = CASE WHEN :status = 'published' THEN COALESCE(published_at, datetime('now')) ELSE NULL END,
    updated_at = datetime('now')
WHERE id = :id
SQL
    );
    $update->execute([
        ':status' => $status,
        ':id' => $postId,
    ]);

    $log = $db->prepare('INSERT INTO audit_logs(actor_id, action, entity_type, entity_id, description) VALUES(:actor_id, :action, :entity_type, :entity_id, :description)');
    $log->execute([
        ':actor_id' => audit_resolve_actor_id($db, $actorId),
        ':action' => 'blog.publish',
        ':entity_type' => 'blog_post',
        ':entity_id' => $postId,
        ':description' => $publish ? 'Published blog post' : 'Returned blog post to draft',
    ]);

    return blog_get_post_by_id($db, $postId);
}

function blog_archive_post(PDO $db, int $postId, int $actorId): array
{
    $update = $db->prepare(<<<'SQL'
UPDATE blog_posts
SET status = 'archived', published_at = NULL, updated_at = datetime('now')
WHERE id = :id
SQL
    );
    $update->execute([':id' => $postId]);
    if ($update->rowCount() === 0) {
        throw new RuntimeException('Post not found.');
    }

    $log = $db->prepare('INSERT INTO audit_logs(actor_id, action, entity_type, entity_id, description) VALUES(:actor_id, :action, :entity_type, :entity_id, :description)');
    $log->execute([
        ':actor_id' => audit_resolve_actor_id($db, $actorId),
        ':action' => 'blog.archive',
        ':entity_type' => 'blog_post',
        ':entity_id' => $postId,
        ':description' => 'Archived blog post',
    ]);

    return blog_get_post_by_id($db, $postId);
}

function blog_get_post_by_id(PDO $db, int $postId): array
{

    $stmt = $db->prepare(<<<'SQL'
SELECT blog_posts.*, GROUP_CONCAT(blog_tags.name, '\u0001') AS tag_names
FROM blog_posts
LEFT JOIN blog_post_tags ON blog_post_tags.post_id = blog_posts.id
LEFT JOIN blog_tags ON blog_tags.id = blog_post_tags.tag_id
WHERE blog_posts.id = :id
GROUP BY blog_posts.id
LIMIT 1
SQL
    );
    $stmt->execute([':id' => $postId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Post not found.');
    }
    $tags = [];
    if (!empty($row['tag_names'])) {
        $tags = array_filter(array_map('trim', explode("\u0001", (string) $row['tag_names'])));
    }
    return [
        'id' => (int) $row['id'],
        'title' => $row['title'],
        'slug' => $row['slug'],
        'excerpt' => $row['excerpt'] ?? '',
        'body' => $row['body_html'] ?? '',
        'coverImage' => $row['cover_image'] ?? '',
        'coverImageAlt' => $row['cover_image_alt'] ?? '',
        'authorName' => $row['author_name'] ?? '',
        'status' => $row['status'],
        'publishedAt' => $row['published_at'],
        'updatedAt' => $row['updated_at'],
        'tags' => $tags,
    ];
}

function blog_seed_default(PDO $db): void
{
    $count = (int) $db->query('SELECT COUNT(*) FROM blog_posts')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $samplePosts = [
        [
            'title' => 'Rooftop solar readiness checklist for Jharkhand homes',
            'slug' => 'rooftop-solar-readiness-checklist',
            'excerpt' => 'Inspect structural, electrical, and subsidy paperwork before applying for PM Surya Ghar. Download the 15-point readiness checklist curated by our EPC leads.',
            'body_html' => '<p>Before you schedule a site inspection, confirm that your rooftop can support the proposed solar array. Start with a structural review, check shading through the day, and secure family approval.</p><p>Our EPC desk also recommends capturing photos of the load distribution board and noting sanctioned load. This keeps DISCOM applications smooth and prevents late-stage surprises.</p><h3>Documents you will need</h3><ul><li>Latest electricity bill (with consumer number visible)</li><li>Identity proof matching the property owner</li><li>Cancelled cheque for subsidy transfer</li></ul><p>Once you have the paperwork ready, call our PM Surya Ghar helpline for a guided walkthrough.</p>',
            'author_name' => 'Dakshayani Editorial Desk',
            'status' => 'published',
            'tags' => ['PM Surya Ghar', 'Residential'],
        ],
        [
            'title' => 'Why hybrid inverters are winning commercial tenders in 2025',
            'slug' => 'hybrid-inverters-commercial-tenders-2025',
            'excerpt' => 'Commercial EPC bids across Jharkhand now demand hybrid-ready inverters. Explore the technical and financial drivers behind the shift, including battery integration roadmaps.',
            'body_html' => '<p>Hybrid inverters are increasingly specified in EPC tenders because they simplify future storage integration. They include bidirectional power electronics, battery-ready firmware, and advanced monitoring.</p><p>During our 2024 installations, we noticed a 28% reduction in downtime for sites that opted for hybrid models.</p><blockquote>“The ability to plug in storage without replacing the inverter is now a board-level requirement,” notes our EPC director.</blockquote><p>Pair this hardware with a demand response audit to unlock additional DISCOM incentives.</p>',
            'author_name' => 'Vishesh Entranchi',
            'status' => 'published',
            'tags' => ['Technology', 'Commercial'],
        ],
    ];

    foreach ($samplePosts as $post) {
        [$coverImage, $coverAlt] = blog_generate_placeholder_cover($post['title'], $post['excerpt'] ?? '');
        $insert = $db->prepare(<<<'SQL'
INSERT INTO blog_posts (title, slug, excerpt, body_html, body_text, cover_image, cover_image_alt, author_name, status, published_at)
VALUES (:title, :slug, :excerpt, :body_html, :body_text, :cover_image, :cover_image_alt, :author_name, :status, datetime('now'))
SQL
        );
        $insert->execute([
            ':title' => $post['title'],
            ':slug' => $post['slug'],
            ':excerpt' => $post['excerpt'],
            ':body_html' => $post['body_html'],
            ':body_text' => blog_extract_plain_text($post['body_html']),
            ':cover_image' => $coverImage,
            ':cover_image_alt' => $coverAlt,
            ':author_name' => $post['author_name'],
            ':status' => $post['status'],
        ]);
        $postId = (int) $db->lastInsertId();
        blog_sync_tags($db, $postId, $post['tags']);
    }
}

function blog_backfill_cover_images(PDO $db): void
{
    $stmt = $db->query("SELECT id, title, excerpt FROM blog_posts WHERE cover_image IS NULL OR trim(cover_image) = ''");
    $rows = $stmt->fetchAll();
    if (!$rows) {
        return;
    }

    $update = $db->prepare(<<<'SQL'
UPDATE blog_posts
SET cover_image = :cover_image,
    cover_image_alt = CASE
        WHEN cover_image_alt IS NULL OR trim(cover_image_alt) = '' THEN :cover_alt
        ELSE cover_image_alt
    END,
    updated_at = datetime('now')
WHERE id = :id
SQL
    );

    foreach ($rows as $row) {
        [$coverImage, $coverAlt] = blog_generate_placeholder_cover((string) ($row['title'] ?? ''), (string) ($row['excerpt'] ?? ''));
        $update->execute([
            ':cover_image' => $coverImage,
            ':cover_alt' => $coverAlt,
            ':id' => (int) $row['id'],
        ]);
    }
}
