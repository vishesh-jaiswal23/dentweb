<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bootstrap.php';

require_admin();
$admin = current_user();
$service = blog_service();

$csrfToken = $_SESSION['csrf_token'] ?? '';
$flashData = consume_flash();
$flashMessage = '';
$flashTone = 'info';
if (is_array($flashData)) {
    if (isset($flashData['message']) && is_string($flashData['message'])) {
        $flashMessage = trim($flashData['message']);
    }
    if (isset($flashData['type']) && is_string($flashData['type'])) {
        $flashTone = strtolower($flashData['type']);
    }
}

function manager_parse_tags($input): array
{
    if (is_array($input)) {
        $values = $input;
    } else {
        $values = preg_split('/[,\n]+/', (string) $input) ?: [];
    }
    $tags = [];
    foreach ($values as $value) {
        $tag = trim((string) $value);
        if ($tag !== '') {
            $tags[] = $tag;
        }
    }
    return array_values(array_unique($tags));
}

function manager_collect_uploaded_files(string $field): array
{
    if (!isset($_FILES[$field])) {
        return [];
    }
    $file = $_FILES[$field];
    if (!is_array($file['name'] ?? null)) {
        return [$file];
    }
    $result = [];
    foreach ($file['name'] as $index => $name) {
        $result[] = [
            'name' => $name,
            'type' => $file['type'][$index] ?? '',
            'tmp_name' => $file['tmp_name'][$index] ?? '',
            'error' => $file['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $file['size'][$index] ?? 0,
        ];
    }
    return $result;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token(is_string($token) ? $token : null)) {
        set_flash('error', 'Your session expired. Please refresh and retry.');
        header('Location: admin-blog-manager.php');
        exit;
    }

    $action = (string) ($_POST['action'] ?? '');
    $redirectParams = [];

    try {
        switch ($action) {
            case 'save-draft':
                $draftId = isset($_POST['draft_id']) ? trim((string) $_POST['draft_id']) : '';
                $scope = isset($_POST['scope']) ? trim((string) $_POST['scope']) : 'draft';
                $tags = manager_parse_tags($_POST['tags'] ?? []);
                $existingAttachments = [];
                if ($draftId !== '') {
                    try {
                        $existingDraft = $service->getDraft($draftId);
                        $existingAttachments = $existingDraft['metadata']['attachments'] ?? [];
                    } catch (Throwable $exception) {
                        $existingAttachments = [];
                    }
                }
                $payload = [
                    'title' => $_POST['title'] ?? '',
                    'summary' => $_POST['summary'] ?? '',
                    'body_html' => $_POST['body_html'] ?? '',
                    'tags' => $tags,
                    'hero_image' => $_POST['hero_image'] ?? '',
                    'hero_image_alt' => $_POST['hero_image_alt'] ?? '',
                    'attachments' => $existingAttachments,
                    'author' => [
                        'id' => (string) ($admin['id'] ?? ''),
                        'name' => $admin['full_name'] ?? 'Administrator',
                    ],
                    'extra' => [
                        'source' => $scope,
                    ],
                ];

                if ($draftId !== '') {
                    $draft = $service->updateDraft($draftId, $payload);
                } else {
                    $draft = $service->createDraft($payload);
                    $draftId = $draft['draft_id'];
                }

                if ($scope === 'published') {
                    $published = $service->publishPost($draftId, ['actor' => $admin['id'] ?? null]);
                    set_flash('success', 'Published post updated. URL: ' . ($published['url'] ?? ''));
                    $redirectParams['tab'] = 'published';
                    $redirectParams['published'] = $published['slug'] ?? '';
                } else {
                    set_flash('success', 'Draft saved successfully.');
                    $redirectParams['tab'] = 'drafts';
                    $redirectParams['draft'] = $draftId;
                }
                break;
            case 'publish-draft':
                $draftId = isset($_POST['draft_id']) ? trim((string) $_POST['draft_id']) : '';
                if ($draftId === '') {
                    throw new RuntimeException('Draft id missing.');
                }
                $published = $service->publishPost($draftId, ['actor' => $admin['id'] ?? null]);
                set_flash('success', 'Draft published. Public URL: ' . ($published['url'] ?? ''));
                $redirectParams['tab'] = 'published';
                $redirectParams['published'] = $published['slug'] ?? '';
                break;
            case 'unpublish-post':
                $slug = isset($_POST['slug']) ? trim((string) $_POST['slug']) : '';
                if ($slug === '') {
                    throw new RuntimeException('Slug missing.');
                }
                $draft = $service->unpublishPost($slug);
                set_flash('success', 'Post moved back to drafts.');
                $redirectParams['tab'] = 'drafts';
                $redirectParams['draft'] = $draft['draft_id'] ?? '';
                break;
            case 'delete-draft':
                $draftId = isset($_POST['draft_id']) ? trim((string) $_POST['draft_id']) : '';
                if ($draftId === '') {
                    throw new RuntimeException('Draft id missing.');
                }
                $service->deleteDraft($draftId);
                set_flash('success', 'Draft deleted.');
                $redirectParams['tab'] = 'drafts';
                break;
            case 'delete-published':
                $slug = isset($_POST['slug']) ? trim((string) $_POST['slug']) : '';
                if ($slug === '') {
                    throw new RuntimeException('Slug missing.');
                }
                $service->deletePublished($slug);
                set_flash('success', 'Published post deleted.');
                $redirectParams['tab'] = 'published';
                break;
            case 'attach-assets':
                $draftId = isset($_POST['draft_id']) ? trim((string) $_POST['draft_id']) : '';
                if ($draftId === '') {
                    throw new RuntimeException('Draft id missing for asset upload.');
                }
                $files = manager_collect_uploaded_files('asset_files');
                $payload = [];
                foreach ($files as $file) {
                    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                        $payload[] = $file;
                    }
                }
                if (isset($_POST['existing_path']) && is_array($_POST['existing_path'])) {
                    foreach ($_POST['existing_path'] as $path) {
                        $path = trim((string) $path);
                        if ($path !== '') {
                            $payload[] = ['path' => $path];
                        }
                    }
                }
                if (empty($payload)) {
                    throw new RuntimeException('No assets provided.');
                }
                $service->attachAssets($draftId, $payload);
                set_flash('success', 'Assets attached to draft.');
                $redirectParams['tab'] = 'drafts';
                $redirectParams['draft'] = $draftId;
                break;
            case 'detach-asset':
                $draftId = isset($_POST['draft_id']) ? trim((string) $_POST['draft_id']) : '';
                $path = isset($_POST['path']) ? trim((string) $_POST['path']) : '';
                if ($draftId === '' || $path === '') {
                    throw new RuntimeException('Missing draft or asset path.');
                }
                $service->detachAsset($draftId, $path);
                set_flash('success', 'Asset detached from draft.');
                $redirectParams['tab'] = 'drafts';
                $redirectParams['draft'] = $draftId;
                break;
            case 'rebuild-index':
                $service->rebuildIndex();
                set_flash('success', 'Blog index rebuilt successfully.');
                $redirectParams['tab'] = $_POST['tab'] ?? 'drafts';
                break;
            default:
                throw new RuntimeException('Unsupported action.');
        }
    } catch (Throwable $exception) {
        set_flash('error', $exception->getMessage());
    }

    $location = 'admin-blog-manager.php';
    if (!empty($redirectParams)) {
        $location .= '?' . http_build_query($redirectParams);
    }

    header('Location: ' . $location);
    exit;
}

$tab = isset($_GET['tab']) ? trim((string) $_GET['tab']) : 'drafts';
if (!in_array($tab, ['drafts', 'published', 'assets'], true)) {
    $tab = 'drafts';
}
$selectedDraftId = isset($_GET['draft']) ? trim((string) $_GET['draft']) : '';
$selectedPublishedSlug = isset($_GET['published']) ? trim((string) $_GET['published']) : '';

$drafts = $service->listDrafts();
$publishedPosts = $service->listPublished();
$health = $service->healthCheck();

$selectedDraft = null;
if ($selectedDraftId !== '') {
    foreach ($drafts as $draft) {
        if (($draft['draft_id'] ?? '') === $selectedDraftId) {
            $selectedDraft = $draft;
            break;
        }
    }
}

$selectedPublished = null;
if ($selectedPublishedSlug !== '') {
    foreach ($publishedPosts as $post) {
        if (($post['slug'] ?? '') === $selectedPublishedSlug) {
            $selectedPublished = $post;
            break;
        }
    }
}

$assetMap = [];
foreach ($drafts as $draft) {
    foreach ($draft['attachments'] as $path) {
        if (!isset($assetMap[$path])) {
            $assetMap[$path] = ['drafts' => [], 'published' => []];
        }
        $assetMap[$path]['drafts'][] = $draft['title'] ?: $draft['slug'];
    }
}
foreach ($publishedPosts as $post) {
    foreach ($post['attachments'] as $path) {
        if (!isset($assetMap[$path])) {
            $assetMap[$path] = ['drafts' => [], 'published' => []];
        }
        $assetMap[$path]['published'][] = $post['title'] ?: $post['slug'];
    }
}
ksort($assetMap);

function manager_format_datetime(?string $value): string
{
    if (!$value) {
        return '—';
    }
    try {
        $dt = new DateTimeImmutable($value);
        return $dt->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('d M Y · h:i A');
    } catch (Throwable $exception) {
        return $value;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Blog Manager · Admin</title>
    <link rel="icon" href="images/favicon.ico" />
    <link rel="stylesheet" href="style.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body { background: #f5f7fb; font-family: 'Poppins', sans-serif; }
        .manager-wrapper { max-width: 1200px; margin: 2rem auto 4rem; padding: 0 1.5rem; display: grid; gap: 2rem; }
        .manager-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
        .manager-tabs { display: inline-flex; gap: 0.75rem; background: #fff; padding: 0.5rem; border-radius: 999px; box-shadow: 0 10px 30px -20px rgba(15,23,42,0.35); }
        .manager-tab { border: none; background: transparent; padding: 0.65rem 1.25rem; border-radius: 999px; font-weight: 600; cursor: pointer; color: #475569; }
        .manager-tab.active { background: #111827; color: #fff; }
        .flash { padding: 1rem 1.5rem; border-radius: 1rem; display: flex; align-items: center; gap: 0.75rem; }
        .flash.info { background: rgba(37,99,235,0.12); color: #1d4ed8; }
        .flash.success { background: rgba(16,185,129,0.12); color: #047857; }
        .flash.error { background: rgba(220,38,38,0.12); color: #b91c1c; }
        .flash.warning { background: rgba(250,204,21,0.18); color: #92400e; }
        table.manager-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 1.25rem; overflow: hidden; box-shadow: 0 20px 40px -32px rgba(15,23,42,0.4); }
        table.manager-table th, table.manager-table td { padding: 0.85rem 1rem; text-align: left; border-bottom: 1px solid rgba(15,23,42,0.06); font-size: 0.95rem; }
        table.manager-table th { background: rgba(15,23,42,0.04); font-weight: 700; color: #0f172a; }
        table.manager-table tbody tr:hover { background: rgba(15,23,42,0.03); }
        .actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem; padding: 0.55rem 1rem; border-radius: 0.75rem; border: none; font-weight: 600; cursor: pointer; text-decoration: none; }
        .btn-primary { background: #111827; color: #fff; }
        .btn-secondary { background: rgba(15,23,42,0.08); color: #111827; }
        .btn-danger { background: rgba(220,38,38,0.18); color: #991b1b; }
        .section { display: grid; gap: 1.5rem; }
        .card { background: #fff; border-radius: 1.25rem; padding: 1.5rem; box-shadow: 0 20px 45px -30px rgba(15,23,42,0.35); }
        .card h2 { margin-top: 0; font-size: 1.4rem; }
        .editor-grid { display: grid; gap: 1rem; }
        .editor-grid label { display: grid; gap: 0.5rem; font-weight: 600; color: #111827; }
        .editor-grid input[type="text"],
        .editor-grid textarea { padding: 0.75rem 1rem; border-radius: 0.85rem; border: 1px solid rgba(15,23,42,0.15); font: inherit; }
        .editor-grid textarea { min-height: 220px; resize: vertical; }
        .attachment-list { display: grid; gap: 0.75rem; }
        .attachment-item { display: flex; justify-content: space-between; align-items: center; padding: 0.65rem 0.75rem; border-radius: 0.75rem; background: rgba(15,23,42,0.04); font-size: 0.9rem; }
        .status-grid { display: flex; gap: 1rem; flex-wrap: wrap; }
        .status-pill { padding: 0.35rem 0.75rem; border-radius: 999px; font-weight: 600; }
        .status-ok { background: rgba(16,185,129,0.12); color: #047857; }
        .status-bad { background: rgba(220,38,38,0.12); color: #b91c1c; }
        .assets-table td { font-size: 0.85rem; }
        @media (max-width: 820px) {
            .manager-header { flex-direction: column; align-items: flex-start; }
            table.manager-table th, table.manager-table td { font-size: 0.85rem; }
        }
    </style>
</head>
<body>
    <div class="manager-wrapper">
        <div class="manager-header">
            <div>
                <h1 style="margin:0;font-size:2rem;color:#0f172a;">Blog Manager</h1>
                <p style="margin:0;color:#475569;">Control drafts, assets, and published posts outside AI Studio.</p>
            </div>
            <div class="manager-tabs" role="tablist">
                <a class="manager-tab <?= $tab === 'drafts' ? 'active' : '' ?>" href="?tab=drafts">Drafts</a>
                <a class="manager-tab <?= $tab === 'published' ? 'active' : '' ?>" href="?tab=published">Published</a>
                <a class="manager-tab <?= $tab === 'assets' ? 'active' : '' ?>" href="?tab=assets">Assets</a>
            </div>
        </div>

        <div class="card">
            <h2 style="margin-bottom:0.5rem;">Health</h2>
            <p style="margin-top:0;margin-bottom:1rem;color:#475569;">Storage readiness and manual cache rebuild.</p>
            <div class="status-grid">
                <span class="status-pill <?= $health['drafts'] ? 'status-ok' : 'status-bad' ?>">Drafts store <?= $health['drafts'] ? 'writable' : 'unavailable' ?></span>
                <span class="status-pill <?= $health['published'] ? 'status-ok' : 'status-bad' ?>">Published store <?= $health['published'] ? 'writable' : 'unavailable' ?></span>
                <span class="status-pill <?= $health['assets'] ? 'status-ok' : 'status-bad' ?>">Assets store <?= $health['assets'] ? 'writable' : 'unavailable' ?></span>
            </div>
            <form method="post" style="margin-top:1rem;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_HTML5) ?>" />
                <input type="hidden" name="action" value="rebuild-index" />
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab, ENT_QUOTES | ENT_HTML5) ?>" />
                <button type="submit" class="btn btn-secondary"><i class="fa-solid fa-rotate"></i> Rebuild Blog Index</button>
            </form>
        </div>

        <?php if ($flashMessage !== ''): ?>
            <div class="flash <?= htmlspecialchars($flashTone, ENT_QUOTES | ENT_HTML5) ?>"><strong><?= ucfirst(htmlspecialchars($flashTone, ENT_QUOTES | ENT_HTML5)) ?>:</strong> <?= htmlspecialchars($flashMessage, ENT_QUOTES | ENT_HTML5) ?></div>
        <?php endif; ?>

        <?php if ($tab === 'drafts'): ?>
            <section class="section">
                <div class="card">
                    <h2>Drafts</h2>
                    <table class="manager-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Updated</th>
                                <th>Attachments</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($drafts)): ?>
                                <tr><td colspan="4" style="text-align:center;padding:2rem;">No drafts yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($drafts as $draft): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($draft['title'] ?: $draft['slug'], ENT_QUOTES | ENT_HTML5) ?></td>
                                        <td><?= htmlspecialchars(manager_format_datetime($draft['updated_at'] ?? null), ENT_QUOTES | ENT_HTML5) ?></td>
                                        <td><?= count($draft['attachments']) ?></td>
                                        <td>
                                            <div class="actions">
                                                <a class="btn btn-secondary" href="?tab=drafts&amp;draft=<?= urlencode($draft['draft_id']) ?>">Edit</a>
                                                <form method="post" onsubmit="return confirm('Publish this draft?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_HTML5) ?>" />
                                                    <input type="hidden" name="action" value="publish-draft" />
                                                    <input type="hidden" name="draft_id" value="<?= htmlspecialchars($draft['draft_id'], ENT_QUOTES | ENT_HTML5) ?>" />
                                                    <button type="submit" class="btn btn-primary">Publish</button>
                                                </form>
                                                <form method="post" onsubmit="return confirm('Delete this draft permanently?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_HTML5) ?>" />
                                                    <input type="hidden" name="action" value="delete-draft" />
                                                    <input type="hidden" name="draft_id" value="<?= htmlspecialchars($draft['draft_id'], ENT_QUOTES | ENT_HTML5) ?>" />
                                                    <button type="submit" class="btn btn-danger">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card">
                    <h2><?= $selectedDraft ? 'Edit draft' : 'Create draft' ?></h2>
                    <form method="post" class="editor-grid">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_HTML5) ?>" />
                        <input type="hidden" name="action" value="save-draft" />
                        <input type="hidden" name="scope" value="draft" />
                        <input type="hidden" name="draft_id" value="<?= htmlspecialchars($selectedDraft['draft_id'] ?? '', ENT_QUOTES | ENT_HTML5) ?>" />
                        <label>Title
                            <input type="text" name="title" required value="<?= htmlspecialchars($selectedDraft['title'] ?? '', ENT_QUOTES | ENT_HTML5) ?>" />
                        </label>
                        <label>Summary
                            <textarea name="summary" rows="3"><?= htmlspecialchars($selectedDraft['summary'] ?? '', ENT_QUOTES | ENT_HTML5) ?></textarea>
                        </label>
                        <label>Tags (comma separated)
                            <input type="text" name="tags" value="<?= htmlspecialchars(implode(', ', $selectedDraft['tags'] ?? []), ENT_QUOTES | ENT_HTML5) ?>" />
                        </label>
                        <label>Hero image path
                            <input type="text" name="hero_image" value="<?= htmlspecialchars($selectedDraft['hero_image'] ?? '', ENT_QUOTES | ENT_HTML5) ?>" />
                        </label>
                        <label>Hero image alt text
                            <input type="text" name="hero_image_alt" value="<?= htmlspecialchars($selectedDraft['hero_image_alt'] ?? '', ENT_QUOTES | ENT_HTML5) ?>" />
                        </label>
                        <label>Body HTML
                            <textarea name="body_html"><?= htmlspecialchars($selectedDraft['body_html'] ?? '', ENT_QUOTES | ENT_HTML5) ?></textarea>
                        </label>
                        <button type="submit" class="btn btn-primary" style="justify-self:start;">Save Draft</button>
                    </form>
                </div>

                <?php if ($selectedDraft): ?>
                    <div class="card">
                        <h2>Attachments for <?= htmlspecialchars($selectedDraft['title'] ?: $selectedDraft['slug'], ENT_QUOTES | ENT_HTML5) ?></h2>
                        <div class="attachment-list">
                            <?php if (empty($selectedDraft['attachments'])): ?>
                                <p>No attachments yet.</p>
                            <?php else: ?>
                                <?php foreach ($selectedDraft['attachments'] as $path): ?>
                                    <div class="attachment-item">
                                        <span><?= htmlspecialchars($path, ENT_QUOTES | ENT_HTML5) ?></span>
                                        <form method="post" onsubmit="return confirm('Detach this asset?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_HTML5) ?>" />
                                            <input type="hidden" name="action" value="detach-asset" />
                                            <input type="hidden" name="draft_id" value="<?= htmlspecialchars($selectedDraft['draft_id'], ENT_QUOTES | ENT_HTML5) ?>" />
                                            <input type="hidden" name="path" value="<?= htmlspecialchars($path, ENT_QUOTES | ENT_HTML5) ?>" />
                                            <button type="submit" class="btn btn-secondary">Detach</button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <form method="post" enctype="multipart/form-data" style="margin-top:1rem; display:grid; gap:0.75rem;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_HTML5) ?>" />
                            <input type="hidden" name="action" value="attach-assets" />
                            <input type="hidden" name="draft_id" value="<?= htmlspecialchars($selectedDraft['draft_id'], ENT_QUOTES | ENT_HTML5) ?>" />
                            <label>Upload new assets
                                <input type="file" name="asset_files[]" multiple />
                            </label>
                            <button type="submit" class="btn btn-secondary" style="justify-self:start;">Attach Assets</button>
                        </form>
                    </div>
                <?php endif; ?>
            </section>
        <?php elseif ($tab === 'published'): ?>
            <section class="section">
                <div class="card">
                    <h2>Published posts</h2>
                    <table class="manager-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Published</th>
                                <th>URL</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($publishedPosts)): ?>
                                <tr><td colspan="4" style="text-align:center;padding:2rem;">No published posts.</td></tr>
                            <?php else: ?>
                                <?php foreach ($publishedPosts as $post): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($post['title'] ?: $post['slug'], ENT_QUOTES | ENT_HTML5) ?></td>
                                        <td><?= htmlspecialchars(manager_format_datetime($post['published_at'] ?? null), ENT_QUOTES | ENT_HTML5) ?></td>
                                        <td><a href="<?= htmlspecialchars($post['url'] ?? '', ENT_QUOTES | ENT_HTML5) ?>" target="_blank" rel="noopener">Open</a></td>
                                        <td>
                                            <div class="actions">
                                                <a class="btn btn-secondary" href="?tab=published&amp;published=<?= urlencode($post['slug']) ?>">Edit</a>
                                                <form method="post" onsubmit="return confirm('Unpublish this post?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_HTML5) ?>" />
                                                    <input type="hidden" name="action" value="unpublish-post" />
                                                    <input type="hidden" name="slug" value="<?= htmlspecialchars($post['slug'], ENT_QUOTES | ENT_HTML5) ?>" />
                                                    <button type="submit" class="btn btn-secondary">Unpublish</button>
                                                </form>
                                                <form method="post" onsubmit="return confirm('Delete this published post permanently?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_HTML5) ?>" />
                                                    <input type="hidden" name="action" value="delete-published" />
                                                    <input type="hidden" name="slug" value="<?= htmlspecialchars($post['slug'], ENT_QUOTES | ENT_HTML5) ?>" />
                                                    <button type="submit" class="btn btn-danger">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($selectedPublished): ?>
                    <div class="card">
                        <h2>Edit published post</h2>
                        <form method="post" class="editor-grid">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_HTML5) ?>" />
                            <input type="hidden" name="action" value="save-draft" />
                            <input type="hidden" name="scope" value="published" />
                            <input type="hidden" name="draft_id" value="<?= htmlspecialchars($selectedPublished['draft_id'] ?? '', ENT_QUOTES | ENT_HTML5) ?>" />
                            <label>Title
                                <input type="text" name="title" required value="<?= htmlspecialchars($selectedPublished['title'] ?? '', ENT_QUOTES | ENT_HTML5) ?>" />
                            </label>
                            <label>Summary
                                <textarea name="summary" rows="3"><?= htmlspecialchars($selectedPublished['summary'] ?? '', ENT_QUOTES | ENT_HTML5) ?></textarea>
                            </label>
                            <label>Tags (comma separated)
                                <input type="text" name="tags" value="<?= htmlspecialchars(implode(', ', $selectedPublished['tags'] ?? []), ENT_QUOTES | ENT_HTML5) ?>" />
                            </label>
                            <label>Hero image path
                                <input type="text" name="hero_image" value="<?= htmlspecialchars($selectedPublished['hero_image'] ?? '', ENT_QUOTES | ENT_HTML5) ?>" />
                            </label>
                            <label>Hero image alt text
                                <input type="text" name="hero_image_alt" value="<?= htmlspecialchars($selectedPublished['hero_image_alt'] ?? '', ENT_QUOTES | ENT_HTML5) ?>" />
                            </label>
                            <label>Body HTML
                                <textarea name="body_html"><?= htmlspecialchars($selectedPublished['body_html'] ?? '', ENT_QUOTES | ENT_HTML5) ?></textarea>
                            </label>
                            <button type="submit" class="btn btn-primary" style="justify-self:start;">Save &amp; Publish Update</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <p>Select a published post to edit its content.</p>
                    </div>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <section class="section">
                <div class="card">
                    <h2>Assets</h2>
                    <table class="manager-table assets-table">
                        <thead>
                            <tr>
                                <th>Path</th>
                                <th>Referenced by drafts</th>
                                <th>Referenced by published</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($assetMap)): ?>
                                <tr><td colspan="3" style="text-align:center;padding:2rem;">No assets tracked yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($assetMap as $path => $usage): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($path, ENT_QUOTES | ENT_HTML5) ?></td>
                                        <td><?= htmlspecialchars(implode(', ', $usage['drafts'] ?? []), ENT_QUOTES | ENT_HTML5) ?: '—' ?></td>
                                        <td><?= htmlspecialchars(implode(', ', $usage['published'] ?? []), ENT_QUOTES | ENT_HTML5) ?: '—' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    </div>
</body>
</html>
