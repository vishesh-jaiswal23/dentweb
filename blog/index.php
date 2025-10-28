<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$db = get_db();

$defaultPageSize = 6;
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPageInput = isset($_GET['per_page']) ? (int) $_GET['per_page'] : $defaultPageSize;
$perPage = max(3, min(12, $perPageInput > 0 ? $perPageInput : $defaultPageSize));
$q = trim((string) ($_GET['q'] ?? ''));
$tag = trim((string) ($_GET['tag'] ?? ''));

$filters = [
    'search' => $q,
    'tag' => $tag,
];

$offset = ($page - 1) * $perPage;
$result = blog_fetch_published($db, $filters, $perPage, $offset);
$posts = $result['posts'];
$total = $result['total'];
$totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
if ($totalPages < 1) {
    $totalPages = 1;
}

$tags = blog_get_tag_summary($db);
$lastUpdated = blog_get_latest_update($db);

send_cache_headers($lastUpdated, sprintf('blog-list-%d-%d-%s-%s', $page, $perPage, md5($q), md5($tag)));

function send_cache_headers(?string $timestamp, string $seed): void
{
    if (!$timestamp) {
        return;
    }

    try {
        $lastModified = new DateTime($timestamp, new DateTimeZone('UTC'));
    } catch (Throwable $exception) {
        return;
    }

    $lastModified->setTimezone(new DateTimeZone('UTC'));
    $formatted = $lastModified->format('D, d M Y H:i:s') . ' GMT';
    $etag = '"' . sha1($seed . '|' . $lastModified->getTimestamp()) . '"';

    header('Last-Modified: ' . $formatted);
    header('ETag: ' . $etag);
    header('Cache-Control: public, max-age=300');

    $clientEtag = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
    $clientModified = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
    if (($clientEtag && trim($clientEtag) === $etag) || ($clientModified && trim($clientModified) === $formatted)) {
        http_response_code(304);
        exit;
    }
}

function absolute_url(string $path = ''): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $root = rtrim($scheme . '://' . $host, '/');
    if ($path === '') {
        $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
        $scriptDir = $scriptDir === '.' ? '' : $scriptDir;
        return $scriptDir ? $root . $scriptDir : $root;
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    if ($path[0] === '/') {
        return $root . $path;
    }
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
    $scriptDir = $scriptDir === '.' ? '' : $scriptDir;
    $base = $scriptDir ? $root . $scriptDir : $root;
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function format_ist(?string $timestamp): string
{
    if (!$timestamp) {
        return '';
    }
    try {
        $date = new DateTime($timestamp, new DateTimeZone('UTC'));
        $date->setTimezone(new DateTimeZone('Asia/Kolkata'));
        return $date->format('j F Y');
    } catch (Throwable $exception) {
        return '';
    }
}

function build_query(array $params): string
{
    return http_build_query(array_filter($params, static fn ($value) => $value !== null && $value !== '' && $value !== false));
}

$canonicalParams = [];
if ($page > 1) {
    $canonicalParams['page'] = $page;
}
if ($q !== '') {
    $canonicalParams['q'] = $q;
}
if ($tag !== '') {
    $canonicalParams['tag'] = $tag;
}
if ($perPage !== $defaultPageSize) {
    $canonicalParams['per_page'] = $perPage;
}
$canonicalUrl = absolute_url('/blog/index.php' . ($canonicalParams ? ('?' . build_query($canonicalParams)) : ''));

$pageTitleParts = ['Dakshayani Blog & Insights'];
if ($tag !== '') {
    $pageTitleParts[] = ucfirst($tag);
}
if ($q !== '') {
    $pageTitleParts[] = 'Search results';
}
$pageTitle = implode(' · ', $pageTitleParts);

$metaDescription = 'Explore published solar, subsidy, and hydrogen insights from Dakshayani Enterprises. Filter by topic or search for installation learnings.';
if ($q !== '') {
    $metaDescription = 'Search results for ' . htmlspecialchars($q, ENT_QUOTES | ENT_HTML5) . ' from Dakshayani Enterprises blog.';
}
if ($tag !== '') {
    $metaDescription = 'Published posts tagged ' . htmlspecialchars($tag, ENT_QUOTES | ENT_HTML5) . ' from Dakshayani Enterprises.';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES | ENT_HTML5) ?></title>
    <meta name="description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES | ENT_HTML5) ?>" />
    <link rel="icon" href="../images/favicon.ico" />
    <link rel="stylesheet" href="../style.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;700;800;900&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES | ENT_HTML5) ?>" />
    <meta property="og:type" content="website" />
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle, ENT_QUOTES | ENT_HTML5) ?>" />
    <meta property="og:description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES | ENT_HTML5) ?>" />
    <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES | ENT_HTML5) ?>" />
    <meta property="og:image" content="<?= htmlspecialchars(absolute_url('/images/hero/hero large.png'), ENT_QUOTES | ENT_HTML5) ?>" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle, ENT_QUOTES | ENT_HTML5) ?>" />
    <meta name="twitter:description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES | ENT_HTML5) ?>" />
    <meta name="twitter:image" content="<?= htmlspecialchars(absolute_url('/images/hero/hero large.png'), ENT_QUOTES | ENT_HTML5) ?>" />
    <style>
        .blog-listing { display: grid; gap: 2rem; }
        .blog-filters { display: grid; gap: 1rem; padding: 1.5rem; border-radius: 1.5rem; background: rgba(15, 23, 42, 0.04); }
        .blog-filters form { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit,minmax(220px,1fr)); align-items: end; }
        .blog-filters label { display: grid; gap: 0.35rem; font-weight: 600; color: var(--base-700); }
        .blog-filters input[type="search"], .blog-filters select { border-radius: 0.75rem; padding: 0.75rem 1rem; border: 1px solid rgba(15, 23, 42, 0.12); font: inherit; }
        .blog-filters button { justify-self: start; }
        .blog-grid { display: grid; gap: 2rem; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }
        .blog-card { display: grid; gap: 1rem; text-decoration: none; color: inherit; border-radius: 1.5rem; overflow: hidden; background: #fff; box-shadow: 0 24px 60px -40px rgba(15, 23, 42, 0.35); transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .blog-card:hover, .blog-card:focus { transform: translateY(-6px); box-shadow: 0 32px 70px -40px rgba(15, 23, 42, 0.45); }
        .blog-card img { width: 100%; height: 180px; object-fit: cover; }
        .blog-card-body { display: grid; gap: 0.75rem; padding: 1.25rem 1.5rem 1.75rem; }
        .blog-card h3 { margin: 0; font-size: 1.3rem; color: var(--base-900); }
        .blog-card p { margin: 0; color: var(--base-600); line-height: 1.6; }
        .blog-card-meta { font-size: 0.95rem; color: var(--base-500); display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .blog-card-meta time { font-weight: 600; color: var(--base-700); }
        .blog-card-tags { display: flex; gap: 0.4rem; flex-wrap: wrap; }
        .blog-card-tags span { background: rgba(37, 99, 235, 0.12); color: #1d4ed8; border-radius: 999px; padding: 0.25rem 0.75rem; font-size: 0.8rem; font-weight: 600; }
        .blog-empty { text-align: center; padding: 4rem 1rem; border-radius: 1.5rem; background: rgba(15, 23, 42, 0.04); color: var(--base-600); }
        .blog-pagination { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-top: 2.5rem; }
        .blog-pagination nav { display: flex; gap: 0.75rem; }
        .blog-pagination a, .blog-pagination span { padding: 0.65rem 1.1rem; border-radius: 999px; border: 1px solid rgba(15, 23, 42, 0.12); font-weight: 600; text-decoration: none; }
        .blog-pagination .current { background: var(--base-900); color: #fff; }
        .tag-cloud { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .tag-chip { display: inline-flex; align-items: center; gap: 0.3rem; border-radius: 999px; padding: 0.3rem 0.8rem; background: rgba(37, 99, 235, 0.12); color: #1d4ed8; font-size: 0.85rem; font-weight: 600; }
        body.modal-open { overflow: hidden; }
        .blog-modal { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; padding: 1.5rem; z-index: 1000; }
        .blog-modal[hidden] { display: none; }
        .blog-modal__backdrop { position: absolute; inset: 0; background: rgba(15, 23, 42, 0.55); }
        .blog-modal__dialog { position: relative; z-index: 1; background: #fff; border-radius: 1.5rem; max-width: min(960px, 100%); width: 100%; max-height: 90vh; overflow: hidden; box-shadow: 0 32px 80px -40px rgba(15, 23, 42, 0.55); display: grid; grid-template-rows: auto 1fr; }
        .blog-modal__close { position: absolute; top: 1rem; right: 1rem; border: none; background: rgba(15, 23, 42, 0.08); color: var(--base-700); width: 2.5rem; height: 2.5rem; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: background 0.2s ease; }
        .blog-modal__close:hover, .blog-modal__close:focus { background: rgba(15, 23, 42, 0.18); outline: none; }
        .blog-modal__scroll { overflow-y: auto; padding: 2.5rem 3rem 2.75rem; display: grid; gap: 1.5rem; }
        .blog-modal__content-wrap { display: grid; gap: 1.5rem; }
        .blog-modal__header { display: grid; gap: 0.75rem; }
        .blog-modal__meta { color: var(--base-500); font-weight: 600; display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center; }
        .blog-modal__hero { width: 100%; border-radius: 1.25rem; object-fit: cover; max-height: 360px; }
        .blog-modal__tags { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .blog-modal__tags span { background: rgba(37, 99, 235, 0.12); color: #1d4ed8; border-radius: 999px; padding: 0.35rem 0.9rem; font-size: 0.85rem; font-weight: 600; }
        .blog-modal__content { display: grid; gap: 1rem; line-height: 1.7; color: var(--base-700); }
        .blog-modal__content h2, .blog-modal__content h3, .blog-modal__content h4 { margin-top: 1.5rem; color: var(--base-900); }
        .blog-modal__loader, .blog-modal__error { text-align: center; padding: 2rem 1rem; border-radius: 1rem; background: rgba(15, 23, 42, 0.04); color: var(--base-600); font-weight: 600; display: grid; gap: 0.5rem; justify-items: center; }
        .blog-modal__loader[hidden], .blog-modal__error[hidden] { display: none; }
        @media (max-width: 680px) {
            .blog-filters form { grid-template-columns: 1fr; }
            .blog-card img { height: 200px; }
            .blog-modal__dialog { max-height: 100vh; border-radius: 1.1rem; }
            .blog-modal__scroll { padding: 2rem 1.5rem 2.25rem; }
            .blog-modal__hero { max-height: 220px; }
        }
    </style>
</head>
<body>
<header class="site-header"></header>
<main>
    <section class="page-hero" style="background:linear-gradient(135deg,rgba(15,23,42,0.94),rgba(37,99,235,0.85));">
        <div class="container hero-inner">
            <div class="hero-copy">
                <span class="hero-eyebrow"><i class="fa-solid fa-newspaper"></i> Blog &amp; insights</span>
                <h1>Fresh solar intelligence from the Dakshayani editorial desk</h1>
                <p class="lead" style="color:rgba(255,255,255,0.82); max-width: 640px;">Policy explainers, EPC deep-dives, and hydrogen breakthroughs—published only after the admin desk reviews each story.</p>
                <div class="tag-cloud" aria-label="Popular blog tags">
                    <span class="tag-chip"><i class="fa-solid fa-solar-panel"></i> Rooftop</span>
                    <span class="tag-chip"><i class="fa-solid fa-scale-balanced"></i> Compliance</span>
                    <span class="tag-chip"><i class="fa-solid fa-sack-dollar"></i> Subsidy</span>
                    <span class="tag-chip"><i class="fa-solid fa-flask"></i> Hydrogen</span>
                </div>
            </div>
            <div class="hero-media" aria-hidden="true">
                <img src="../images/hero/hero large.png" alt="Dakshayani editorial team" loading="lazy" />
                <div class="caption">Published in Ranchi · Updated <?= htmlspecialchars(format_ist($lastUpdated) ?: 'recently', ENT_QUOTES | ENT_HTML5) ?></div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container blog-listing">
            <div class="blog-filters" aria-labelledby="blog-filter-title">
                <div>
                    <h2 id="blog-filter-title" style="margin:0; font-size:1.3rem;">Filter published posts</h2>
                    <p class="text-sm" style="margin:0; color:var(--base-500);">Search the archive or focus on a specific tag. Only published stories are listed.</p>
                </div>
                <form method="get" action="index.php" novalidate>
                    <label for="blog-search">Search title or excerpt
                        <input type="search" id="blog-search" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES | ENT_HTML5) ?>" placeholder="e.g. net-metering timeline" />
                    </label>
                    <label for="blog-tag">Filter by tag
                        <select id="blog-tag" name="tag">
                            <option value="">All tags</option>
                            <?php foreach ($tags as $tagRow): ?>
                                <option value="<?= htmlspecialchars($tagRow['slug'], ENT_QUOTES | ENT_HTML5) ?>" <?= $tagRow['slug'] === $tag ? 'selected' : '' ?>><?= htmlspecialchars($tagRow['name'], ENT_QUOTES | ENT_HTML5) ?> (<?= (int) $tagRow['post_count'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label for="blog-per-page">Posts per page
                        <select id="blog-per-page" name="per_page">
                            <?php for ($size = 3; $size <= 12; $size += 3): ?>
                                <option value="<?= $size ?>" <?= $size === $perPage ? 'selected' : '' ?>><?= $size ?></option>
                            <?php endfor; ?>
                        </select>
                    </label>
                    <button type="submit" class="btn btn-primary">Apply filters</button>
                    <?php if ($q !== '' || $tag !== '' || $perPage !== $defaultPageSize): ?>
                        <a href="index.php" class="btn btn-outline">Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if ($posts): ?>
                <div class="blog-grid" aria-live="polite">
                    <?php foreach ($posts as $post): ?>
                        <?php
                        $cover = $post['cover_image'] ?? '';
                        $coverAlt = $post['cover_image_alt'] ?? '';
                        $altText = $coverAlt !== '' ? $coverAlt : ($post['title'] ?? 'Blog cover');
                        $tagsDisplay = [];
                        foreach (($post['tags'] ?? []) as $tagItem) {
                            $tagName = is_array($tagItem) ? ($tagItem['name'] ?? '') : (string) $tagItem;
                            if ($tagName === '') {
                                continue;
                            }
                            $tagsDisplay[] = $tagItem;
                        }
                        ?>
                        <article class="blog-card">
                            <?php if ($cover): ?>
                                <img src="<?= htmlspecialchars($cover, ENT_QUOTES | ENT_HTML5) ?>" alt="<?= htmlspecialchars($altText, ENT_QUOTES | ENT_HTML5) ?>" loading="lazy" />
                            <?php else: ?>
                <img src="<?= htmlspecialchars(absolute_url('/images/hero/hero.png'), ENT_QUOTES | ENT_HTML5) ?>" alt="<?= htmlspecialchars($altText, ENT_QUOTES | ENT_HTML5) ?>" loading="lazy" />
                            <?php endif; ?>
                            <div class="blog-card-body">
                                <div class="blog-card-meta">
                                    <time datetime="<?= htmlspecialchars((string) $post['published_at'], ENT_QUOTES | ENT_HTML5) ?>"><?= htmlspecialchars(format_ist($post['published_at']), ENT_QUOTES | ENT_HTML5) ?></time>
                                </div>
                                <h3><?= htmlspecialchars($post['title'], ENT_QUOTES | ENT_HTML5) ?></h3>
                                <?php if (!empty($post['excerpt'])): ?>
                                    <p><?= htmlspecialchars($post['excerpt'], ENT_QUOTES | ENT_HTML5) ?></p>
                                <?php endif; ?>
                                <?php if ($tagsDisplay): ?>
                                    <div class="blog-card-tags" aria-label="Tags">
                                        <?php foreach ($tagsDisplay as $tagItem): ?>
                                            <span><?= htmlspecialchars($tagItem['name'] ?? $tagItem, ENT_QUOTES | ENT_HTML5) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <a
                                    class="btn btn-secondary"
                                    href="<?= htmlspecialchars('post.php?slug=' . urlencode($post['slug']), ENT_QUOTES | ENT_HTML5) ?>"
                                    data-read-more
                                    data-post-slug="<?= htmlspecialchars($post['slug'], ENT_QUOTES | ENT_HTML5) ?>"
                                    aria-haspopup="dialog"
                                    aria-controls="blog-modal"
                                >Read more</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="blog-empty" role="status">
                    <p class="lead">No published posts match your filters yet.</p>
                    <p>Check back soon—our admin team reviews and publishes new insights regularly.</p>
                </div>
            <?php endif; ?>

            <?php if ($totalPages > 1): ?>
                <div class="blog-pagination" aria-label="Blog pagination">
                    <nav>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php
                            $query = build_query([
                                'page' => $i,
                                'q' => $q,
                                'tag' => $tag,
                                'per_page' => $perPage !== $defaultPageSize ? $perPage : null,
                            ]);
                            $url = 'index.php' . ($query ? ('?' . $query) : '');
                            ?>
                            <?php if ($i === $page): ?>
                                <span class="current" aria-current="page">Page <?= $i ?></span>
                            <?php else: ?>
                                <a href="<?= htmlspecialchars($url, ENT_QUOTES | ENT_HTML5) ?>">Page <?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </nav>
                    <div>
                        <span style="font-weight:600;">Showing <?= min($total, $offset + $perPage) ?> of <?= $total ?> published posts</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="blog-modal" id="blog-modal" data-blog-modal hidden>
        <div class="blog-modal__backdrop" data-blog-modal-overlay></div>
        <article class="blog-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="blog-modal-title" tabindex="-1" data-blog-modal-dialog>
            <button type="button" class="blog-modal__close" data-blog-modal-close aria-label="Close blog post">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
            <div class="blog-modal__scroll">
                <div class="blog-modal__loader" data-blog-modal-loader role="status">
                    <i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i>
                    <span>Loading post…</span>
                </div>
                <div class="blog-modal__error" data-blog-modal-error hidden role="alert">
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    <p>We couldn’t load this post. Please try again in a moment.</p>
                </div>
                <div class="blog-modal__content-wrap" data-blog-modal-content hidden>
                    <header class="blog-modal__header">
                        <p class="blog-modal__meta" data-blog-modal-meta></p>
                        <h2 id="blog-modal-title" data-blog-modal-title></h2>
                        <div class="blog-modal__tags" data-blog-modal-tags></div>
                    </header>
                    <img class="blog-modal__hero" data-blog-modal-hero alt="" hidden />
                    <div class="blog-modal__content" data-blog-modal-body></div>
                </div>
            </div>
        </article>
    </div>
</main>
<footer class="site-footer"></footer>
<script src="../script.js" defer></script>
<script>
    (function () {
        const modal = document.querySelector('[data-blog-modal]');
        if (!modal) return;
        const dialog = modal.querySelector('[data-blog-modal-dialog]');
        const overlay = modal.querySelector('[data-blog-modal-overlay]');
        const closeButton = modal.querySelector('[data-blog-modal-close]');
        const loader = modal.querySelector('[data-blog-modal-loader]');
        const errorNode = modal.querySelector('[data-blog-modal-error]');
        const contentWrap = modal.querySelector('[data-blog-modal-content]');
        const titleNode = modal.querySelector('[data-blog-modal-title]');
        const metaNode = modal.querySelector('[data-blog-modal-meta]');
        const tagsNode = modal.querySelector('[data-blog-modal-tags]');
        const heroNode = modal.querySelector('[data-blog-modal-hero]');
        const bodyNode = modal.querySelector('[data-blog-modal-body]');
        const scrollContainer = modal.querySelector('.blog-modal__scroll');
        let lastFocused = null;

        function resetModalState() {
            if (contentWrap) contentWrap.hidden = true;
            if (loader) loader.hidden = false;
            if (errorNode) errorNode.hidden = true;
            if (titleNode) titleNode.textContent = '';
            if (metaNode) metaNode.textContent = '';
            if (tagsNode) tagsNode.innerHTML = '';
            if (bodyNode) bodyNode.innerHTML = '';
            if (heroNode) {
                heroNode.hidden = true;
                heroNode.removeAttribute('src');
                heroNode.alt = '';
            }
            if (dialog) dialog.setAttribute('aria-busy', 'true');
        }

        function showError(message) {
            if (loader) loader.hidden = true;
            if (errorNode) {
                errorNode.hidden = false;
                const paragraph = errorNode.querySelector('p');
                if (paragraph) {
                    paragraph.textContent = message;
                }
            }
            if (contentWrap) contentWrap.hidden = true;
            if (dialog) dialog.removeAttribute('aria-busy');
        }

        function renderTags(tags) {
            if (!tagsNode) return;
            tagsNode.innerHTML = '';
            const list = Array.isArray(tags) ? tags : [];
            list.forEach((tag) => {
                const label = typeof tag === 'string' ? tag : (tag && tag.name);
                if (!label) return;
                const chip = document.createElement('span');
                chip.textContent = label;
                tagsNode.appendChild(chip);
            });
        }

        function renderPost(post) {
            if (!post) {
                showError('We couldn’t load this post. Please try again in a moment.');
                return;
            }
            if (loader) loader.hidden = true;
            if (errorNode) errorNode.hidden = true;
            if (contentWrap) contentWrap.hidden = false;
            if (dialog) dialog.removeAttribute('aria-busy');
            if (titleNode) titleNode.textContent = post.title || 'Blog post';
            if (metaNode) {
                const metaParts = [];
                if (post.published_ist) {
                    metaParts.push(`Published ${post.published_ist} IST`);
                }
                if (post.updated_ist && post.updated_ist !== post.published_ist) {
                    metaParts.push(`Updated ${post.updated_ist} IST`);
                }
                if (post.author_name) {
                    metaParts.push(`By ${post.author_name}`);
                }
                metaNode.textContent = metaParts.join(' · ');
            }
            if (heroNode) {
                if (post.cover_image) {
                    heroNode.src = post.cover_image;
                    heroNode.alt = post.cover_image_alt || post.title || 'Blog cover image';
                    heroNode.hidden = false;
                } else {
                    heroNode.hidden = true;
                    heroNode.removeAttribute('src');
                }
            }
            renderTags(post.tags);
            if (bodyNode) {
                bodyNode.innerHTML = post.body_html || '';
            }
            if (scrollContainer) scrollContainer.scrollTo(0, 0);
        }

        function closeModal() {
            modal.hidden = true;
            document.body.classList.remove('modal-open');
            if (dialog) dialog.removeAttribute('aria-busy');
            if (scrollContainer) scrollContainer.scrollTo(0, 0);
            resetModalState();
            if (lastFocused && typeof lastFocused.focus === 'function') {
                lastFocused.focus();
            }
            lastFocused = null;
        }

        function openModal(slug, trigger) {
            if (!slug) return;
            lastFocused = trigger || document.activeElement;
            modal.hidden = false;
            document.body.classList.add('modal-open');
            resetModalState();
            if (dialog) {
                window.requestAnimationFrame(() => {
                    dialog.focus();
                });
            }
            fetch(`post.php?slug=${encodeURIComponent(slug)}&format=json`, {
                headers: { Accept: 'application/json' },
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Failed to load');
                    }
                    return response.json();
                })
                .then((payload) => {
                    if (!payload || payload.status !== 'ok' || !payload.post) {
                        throw new Error('Invalid payload');
                    }
                    renderPost(payload.post);
                })
                .catch(() => {
                    showError('We couldn’t load this post. Please try again in a moment.');
                });
        }

        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-read-more]');
            if (!trigger) return;
            const slug = trigger.getAttribute('data-post-slug');
            if (!slug) return;
            event.preventDefault();
            openModal(slug, trigger);
        });

        if (closeButton) {
            closeButton.addEventListener('click', (event) => {
                event.preventDefault();
                closeModal();
            });
        }

        if (overlay) {
            overlay.addEventListener('click', (event) => {
                event.preventDefault();
                closeModal();
            });
        }

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !modal.hidden) {
                event.preventDefault();
                closeModal();
            }
        });
    })();
</script>
</body>
</html>
