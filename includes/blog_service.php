<?php
declare(strict_types=1);

final class BlogService
{
    private string $root;
    private string $draftDir;
    private string $publishedDir;
    private string $cacheDir;
    private string $lockDir;

    public function __construct(?string $root = null)
    {
        $this->root = $root ?? __DIR__ . '/../storage/blog';
        $this->draftDir = $this->root . '/drafts';
        $this->publishedDir = $this->root . '/published';
        $this->cacheDir = $this->root . '/cache';
        $this->lockDir = $this->root . '/locks';

        $this->ensureDirectory($this->root);
        $this->ensureDirectory($this->draftDir);
        $this->ensureDirectory($this->publishedDir);
        $this->ensureDirectory($this->cacheDir);
        $this->ensureDirectory($this->lockDir);
    }

    public function createDraft(array $input): array
    {
        $payload = $this->normaliseDraftPayload($input);
        $this->assertValidDraft($payload);

        $draftId = $this->generateDraftId();
        $slug = $this->generateSlug($payload['title']);
        $now = $this->now();

        $metadata = [
            'draft_id' => $draftId,
            'title' => $payload['title'],
            'slug' => $slug,
            'status' => 'draft',
            'created_at' => $now,
            'updated_at' => $now,
            'author' => $payload['author'],
            'tags' => $payload['tags'],
            'summary' => $payload['summary'],
            'hero_image' => $payload['hero_image'],
            'hero_image_alt' => $payload['hero_image_alt'],
            'attachments' => $payload['attachments'],
            'extra' => $payload['extra'],
        ];

        $this->writeDraft($draftId, $metadata, $payload['body_html']);

        return $this->formatDraftResponse($metadata, $payload['body_html']);
    }

    public function updateDraft(string $draftId, array $input): array
    {
        $draft = $this->getDraft($draftId);
        $payload = $this->normaliseDraftPayload($input, $draft);
        $this->assertValidDraft($payload);

        $metadata = $draft['metadata'];
        $metadata['title'] = $payload['title'];
        $metadata['summary'] = $payload['summary'];
        $metadata['author'] = $payload['author'];
        $metadata['tags'] = $payload['tags'];
        $metadata['hero_image'] = $payload['hero_image'];
        $metadata['hero_image_alt'] = $payload['hero_image_alt'];
        $metadata['attachments'] = $payload['attachments'];
        $metadata['extra'] = $payload['extra'];
        $metadata['updated_at'] = $this->now();

        $this->writeDraft($draftId, $metadata, $payload['body_html']);

        return $this->formatDraftResponse($metadata, $payload['body_html']);
    }

    public function getDraft(string $draftId): array
    {
        $path = $this->draftDir . '/' . $draftId;
        if (!is_dir($path)) {
            throw new RuntimeException('Draft not found.');
        }

        $metadata = $this->readJson($path . '/metadata.json');
        $body = $this->readFile($path . '/content.html');

        return [
            'metadata' => $metadata,
            'body_html' => $body,
        ];
    }

    public function listDrafts(): array
    {
        $drafts = [];
        foreach ($this->scanDirectory($this->draftDir) as $entry) {
            $metadataPath = $entry . '/metadata.json';
            if (!is_file($metadataPath)) {
                continue;
            }
            $metadata = $this->readJson($metadataPath);
            $body = '';
            try {
                $body = $this->readFile($entry . '/content.html');
            } catch (Throwable $exception) {
                $body = '';
            }
            $drafts[] = $this->formatDraftResponse($metadata, $body);
        }

        usort($drafts, static function (array $a, array $b): int {
            return strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? '');
        });

        return $drafts;
    }

    public function listPublished(): array
    {
        $posts = [];
        foreach ($this->scanDirectory($this->publishedDir) as $entry) {
            $metadataPath = $entry . '/metadata.json';
            $contentPath = $entry . '/content.html';
            if (!is_file($metadataPath) || !is_file($contentPath)) {
                continue;
            }
            $metadata = $this->readJson($metadataPath);
            $body = $this->readFile($contentPath);
            $posts[] = $this->formatPublishedResponse($metadata, $body);
        }

        usort($posts, static function (array $a, array $b): int {
            return strcmp($b['published_at'] ?? '', $a['published_at'] ?? '');
        });

        return $posts;
    }

    public function publishPost(string $draftId, array $context = []): array
    {
        $lock = $this->acquireLock('draft_' . $draftId);
        try {
            $draft = $this->getDraft($draftId);
            $metadata = $draft['metadata'];
            if (($metadata['status'] ?? 'draft') === 'published') {
                throw new RuntimeException('This draft has already been published.');
            }

            $body = $draft['body_html'] ?? '';
            $publishedAt = $this->now();
            $slug = $metadata['slug'];
            $publishedPath = $this->publishedDir . '/' . $slug;
            if (is_dir($publishedPath)) {
                throw new RuntimeException('A published post with this slug already exists.');
            }
            $this->ensureDirectory($publishedPath);
            $assetsDir = $publishedPath . '/assets';
            $this->ensureDirectory($assetsDir);

            $heroImage = $this->moveAssetIfPresent($metadata['hero_image'] ?? '', $assetsDir, 'hero');
            $attachments = [];
            foreach ($metadata['attachments'] ?? [] as $index => $attachment) {
                $attachments[] = $this->moveAssetIfPresent($attachment, $assetsDir, 'asset_' . $index);
            }

            $publishedMetadata = [
                'post_id' => $metadata['draft_id'],
                'draft_id' => $metadata['draft_id'],
                'title' => $metadata['title'],
                'slug' => $slug,
                'status' => 'published',
                'summary' => $metadata['summary'],
                'tags' => $metadata['tags'],
                'hero_image' => $heroImage,
                'hero_image_alt' => $metadata['hero_image_alt'],
                'attachments' => $attachments,
                'author' => $metadata['author'],
                'created_at' => $metadata['created_at'],
                'updated_at' => $publishedAt,
                'published_at' => $publishedAt,
                'extra' => $metadata['extra'],
            ];

            $this->writeJson($publishedPath . '/metadata.json', $publishedMetadata);
            $this->writeFileAtomic($publishedPath . '/content.html', $body);

            $metadata['status'] = 'published';
            $metadata['published_at'] = $publishedAt;
            $metadata['hero_image'] = $heroImage;
            $metadata['attachments'] = $attachments;
            $metadata['updated_at'] = $publishedAt;
            $this->writeJson($this->draftDir . '/' . $draftId . '/metadata.json', $metadata);

            $this->refreshCache();

            return $this->formatPublishedResponse($publishedMetadata, $body);
        } finally {
            if ($lock !== null) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    public function attachAssets(string $draftId, array $assets): array
    {
        $draftPath = $this->draftDir . '/' . $draftId;
        if (!is_dir($draftPath)) {
            throw new RuntimeException('Draft not found for asset attachment.');
        }
        $assetsDir = $draftPath . '/assets';
        $this->ensureDirectory($assetsDir);

        $attached = [];
        foreach ($assets as $asset) {
            if (!is_array($asset)) {
                continue;
            }
            $source = isset($asset['path']) ? (string) $asset['path'] : '';
            if ($source === '') {
                continue;
            }
            $preferredName = isset($asset['name']) ? (string) $asset['name'] : 'asset';
            $attached[] = $this->copyAsset($source, $assetsDir, $preferredName);
        }

        if (!$attached) {
            return [];
        }

        $draft = $this->getDraft($draftId);
        $metadata = $draft['metadata'];
        $existing = $metadata['attachments'] ?? [];
        $metadata['attachments'] = array_values(array_unique(array_merge($existing, $attached)));
        $metadata['updated_at'] = $this->now();
        $this->writeJson($draftPath . '/metadata.json', $metadata);

        return $metadata['attachments'];
    }

    private function normaliseDraftPayload(array $input, ?array $existing = null): array
    {
        $author = $input['author'] ?? ($existing['metadata']['author'] ?? []);
        if (!is_array($author)) {
            $author = [];
        }
        $authorId = isset($author['id']) ? (string) $author['id'] : '';
        if ($authorId === '' && isset($input['author_id'])) {
            $authorId = (string) $input['author_id'];
        }
        $authorName = isset($author['name']) ? (string) $author['name'] : '';
        if ($authorName === '' && isset($input['author_name'])) {
            $authorName = (string) $input['author_name'];
        }

        return [
            'title' => trim((string) ($input['title'] ?? '')),
            'summary' => trim((string) ($input['summary'] ?? ($existing['metadata']['summary'] ?? ''))),
            'body_html' => trim((string) ($input['body_html'] ?? '')),
            'tags' => $this->normaliseTags($input['tags'] ?? []),
            'hero_image' => trim((string) ($input['hero_image'] ?? ($existing['metadata']['hero_image'] ?? ''))),
            'hero_image_alt' => trim((string) ($input['hero_image_alt'] ?? ($existing['metadata']['hero_image_alt'] ?? ''))),
            'attachments' => $this->normaliseAttachments($input['attachments'] ?? []),
            'author' => [
                'id' => $authorId,
                'name' => $authorName,
            ],
            'extra' => $this->normaliseExtra($input['extra'] ?? []),
        ];
    }

    private function normaliseTags($tags): array
    {
        $normalised = [];
        if (is_array($tags)) {
            foreach ($tags as $tag) {
                if (is_string($tag)) {
                    $label = trim($tag);
                    if ($label !== '') {
                        $normalised[] = $label;
                    }
                } elseif (is_array($tag) && isset($tag['name'])) {
                    $label = trim((string) $tag['name']);
                    if ($label !== '') {
                        $normalised[] = $label;
                    }
                }
            }
        }
        $normalised = array_values(array_unique($normalised));
        sort($normalised, SORT_NATURAL | SORT_FLAG_CASE);
        return $normalised;
    }

    private function normaliseAttachments($attachments): array
    {
        $normalised = [];
        if (is_array($attachments)) {
            foreach ($attachments as $attachment) {
                $path = '';
                if (is_string($attachment)) {
                    $path = trim($attachment);
                } elseif (is_array($attachment) && isset($attachment['path'])) {
                    $path = trim((string) $attachment['path']);
                }
                if ($path !== '') {
                    $normalised[] = $path;
                }
            }
        }
        return array_values(array_unique($normalised));
    }

    private function normaliseExtra($extra): array
    {
        if (!is_array($extra)) {
            return [];
        }
        return $extra;
    }

    private function assertValidDraft(array $payload): void
    {
        if ($payload['title'] === '') {
            throw new RuntimeException('Title is required.');
        }
        if ($payload['body_html'] === '') {
            throw new RuntimeException('Content is required.');
        }
    }

    private function writeDraft(string $draftId, array $metadata, string $body): void
    {
        $draftPath = $this->draftDir . '/' . $draftId;
        $this->ensureDirectory($draftPath);
        $this->writeJson($draftPath . '/metadata.json', $metadata);
        $this->writeFileAtomic($draftPath . '/content.html', $body);
    }

    private function formatDraftResponse(array $metadata, string $body): array
    {
        return [
            'draft_id' => $metadata['draft_id'],
            'title' => $metadata['title'],
            'slug' => $metadata['slug'],
            'status' => $metadata['status'],
            'summary' => $metadata['summary'],
            'tags' => $metadata['tags'],
            'hero_image' => $metadata['hero_image'],
            'hero_image_alt' => $metadata['hero_image_alt'],
            'attachments' => $metadata['attachments'],
            'author' => $metadata['author'],
            'created_at' => $metadata['created_at'],
            'updated_at' => $metadata['updated_at'],
            'published_at' => $metadata['published_at'] ?? null,
            'body_html' => $body,
            'extra' => $metadata['extra'],
        ];
    }

    private function formatPublishedResponse(array $metadata, string $body): array
    {
        $heroImageUrl = $metadata['hero_image'] !== '' ? $this->buildPublicUrl('/' . ltrim($metadata['hero_image'], '/')) : '';
        $attachmentUrls = [];
        foreach ($metadata['attachments'] ?? [] as $item) {
            $attachmentUrls[] = $this->buildPublicUrl('/' . ltrim($item, '/'));
        }

        return [
            'post_id' => $metadata['post_id'],
            'draft_id' => $metadata['draft_id'],
            'title' => $metadata['title'],
            'slug' => $metadata['slug'],
            'status' => $metadata['status'],
            'summary' => $metadata['summary'],
            'tags' => $metadata['tags'],
            'hero_image' => $metadata['hero_image'],
            'hero_image_url' => $heroImageUrl,
            'hero_image_alt' => $metadata['hero_image_alt'],
            'attachments' => $metadata['attachments'],
            'attachment_urls' => $attachmentUrls,
            'author' => $metadata['author'],
            'created_at' => $metadata['created_at'],
            'updated_at' => $metadata['updated_at'],
            'published_at' => $metadata['published_at'],
            'body_html' => $body,
            'extra' => $metadata['extra'],
            'url' => $this->buildPublicUrl('/blog/post.php?slug=' . rawurlencode($metadata['slug'])),
        ];
    }

    private function generateDraftId(): string
    {
        do {
            $id = 'drf_' . substr(bin2hex(random_bytes(8)), 0, 12);
        } while (is_dir($this->draftDir . '/' . $id));
        return $id;
    }

    private function generateSlug(string $title): string
    {
        $base = $this->slugify($title);
        do {
            $candidate = $base . '-' . substr($this->randomBase36(), 0, 6);
        } while ($this->slugExists($candidate));
        return $candidate;
    }

    private function randomBase36(): string
    {
        $bytes = random_bytes(4);
        $value = unpack('N', $bytes)[1];
        return strtolower(base_convert((string) $value, 10, 36));
    }

    private function slugExists(string $slug): bool
    {
        if (is_dir($this->publishedDir . '/' . $slug)) {
            return true;
        }
        foreach ($this->scanDirectory($this->draftDir) as $entry) {
            $metaPath = $entry . '/metadata.json';
            if (!is_file($metaPath)) {
                continue;
            }
            $metadata = $this->readJson($metaPath);
            if (($metadata['slug'] ?? '') === $slug) {
                return true;
            }
        }
        return false;
    }

    private function slugify(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return 'post';
        }
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text) ?? '';
        $text = trim($text, '-');
        return $text !== '' ? $text : 'post';
    }

    private function moveAssetIfPresent(string $path, string $destinationDir, string $basename): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        return $this->copyAsset($path, $destinationDir, $basename);
    }

    private function copyAsset(string $source, string $destinationDir, string $basename): string
    {
        $sourcePath = $this->resolvePath($source);
        if (!is_file($sourcePath)) {
            return '';
        }
        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);
        $extension = $extension !== '' ? '.' . $extension : '';
        $fileName = $basename . '_' . substr(bin2hex(random_bytes(6)), 0, 10) . $extension;
        $targetPath = rtrim($destinationDir, '/') . '/' . $fileName;
        if (!copy($sourcePath, $targetPath)) {
            throw new RuntimeException('Failed to copy asset.');
        }
        $relative = $this->relativePath($targetPath);
        return $relative;
    }

    private function resolvePath(string $path): string
    {
        if (preg_match('#^https?://#i', $path)) {
            throw new RuntimeException('Remote assets cannot be copied.');
        }
        if ($path !== '' && $path[0] === '/') {
            $absolute = __DIR__ . '/../' . ltrim($path, '/');
        } else {
            $absolute = __DIR__ . '/../' . ltrim($path, '/');
        }
        return $absolute;
    }

    private function relativePath(string $absolute): string
    {
        $root = realpath(__DIR__ . '/..');
        $absoluteReal = realpath($absolute);
        if ($absoluteReal !== false && $root !== false && strpos($absoluteReal, $root) === 0) {
            return ltrim(substr($absoluteReal, strlen($root)), '/');
        }
        return ltrim(str_replace('\\', '/', $absolute), '/');
    }

    private function buildPublicUrl(string $path): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return rtrim($scheme . '://' . $host, '/') . '/' . ltrim($path, '/');
    }

    private function scanDirectory(string $path): array
    {
        $items = [];
        $handle = opendir($path);
        if ($handle === false) {
            return $items;
        }
        while (($entry = readdir($handle)) !== false) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $path . '/' . $entry;
            if (is_dir($full)) {
                $items[] = $full;
            }
        }
        closedir($handle);
        return $items;
    }

    private function readJson(string $path): array
    {
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
            throw new RuntimeException('Failed to decode blog metadata: ' . $exception->getMessage(), 0, $exception);
        }
        return is_array($decoded) ? $decoded : [];
    }

    private function writeJson(string $path, array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Failed to encode blog metadata.');
        }
        $this->writeFileAtomic($path, $json);
    }

    private function readFile(string $path): string
    {
        if (!is_file($path)) {
            return '';
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            return '';
        }
        return (string) $contents;
    }

    private function writeFileAtomic(string $path, string $contents): void
    {
        $dir = dirname($path);
        $this->ensureDirectory($dir);
        $temp = tempnam($dir, 'blog');
        if ($temp === false) {
            throw new RuntimeException('Failed to create temporary file for blog write.');
        }
        if (file_put_contents($temp, $contents) === false) {
            @unlink($temp);
            throw new RuntimeException('Failed to write blog contents.');
        }
        if (!rename($temp, $path)) {
            @unlink($temp);
            throw new RuntimeException('Failed to persist blog contents.');
        }
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM);
    }

    private function acquireLock(string $name)
    {
        $lockPath = $this->lockDir . '/' . $this->slugify($name) . '.lock';
        $handle = fopen($lockPath, 'c+');
        if ($handle === false) {
            return null;
        }
        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            throw new RuntimeException('Failed to acquire blog lock.');
        }
        return $handle;
    }

    private function refreshCache(): void
    {
        $posts = $this->listPublished();
        $payload = json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            return;
        }
        $this->writeFileAtomic($this->cacheDir . '/published_index.json', $payload);
    }
}

function blog_service(): BlogService
{
    static $service = null;
    if (!$service instanceof BlogService) {
        $service = new BlogService();
    }
    return $service;
}
