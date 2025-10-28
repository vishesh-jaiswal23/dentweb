<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bootstrap.php';

require_admin();

$admin = current_user();
$db = get_db();

$adminId = (int) ($admin['id'] ?? 0);
$csrfToken = $_SESSION['csrf_token'] ?? '';
$allowedTabs = ['settings', 'generator', 'notes'];
$activeTab = isset($_GET['tab']) ? strtolower(trim((string) $_GET['tab'])) : 'settings';
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'settings';
}

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token(is_string($token) ? $token : null)) {
        set_flash('error', 'Your session expired. Please try again.');
        header('Location: admin-ai-studio.php?tab=' . urlencode($activeTab));
        exit;
    }

    $submittedTab = isset($_POST['tab']) ? strtolower(trim((string) $_POST['tab'])) : $activeTab;
    if (in_array($submittedTab, $allowedTabs, true)) {
        $activeTab = $submittedTab;
    }

    $action = strtolower(trim((string) ($_POST['action'] ?? '')));

    try {
        $draftId = '';
        switch ($action) {
            case 'save-settings':
                ai_save_settings($_POST, $adminId);
                set_flash('success', 'AI settings saved.');
                break;
            case 'test-connection':
                $result = ai_test_connection();
                $tone = $result['status'] === 'pass' ? 'success' : 'warning';
                set_flash($tone, $result['message']);
                break;
            case 'generate-draft':
                $prompt = trim((string) ($_POST['prompt'] ?? ''));
                $result = ai_generate_blog_draft_from_prompt($prompt, $adminId);
                set_flash('success', sprintf('Draft "%s" generated with artwork and saved for review.', $result['title']));
                $activeTab = 'generator';
                $draftId = $result['draft_id'] ?? '';
                break;
            case 'generate-image':
                $draftId = trim((string) ($_POST['draft_id'] ?? ''));
                if ($draftId === '') {
                    throw new RuntimeException('Select a draft to generate artwork.');
                }
                ai_generate_image_for_draft($draftId, $adminId);
                set_flash('success', 'Feature image refreshed for the draft.');
                $activeTab = 'generator';
                break;
            case 'schedule-draft':
                $draftId = trim((string) ($_POST['draft_id'] ?? ''));
                if ($draftId === '') {
                    throw new RuntimeException('Select a draft to schedule.');
                }
                $scheduleInput = trim((string) ($_POST['schedule_at'] ?? ''));
                if ($scheduleInput === '') {
                    ai_schedule_blog_draft($draftId, null, $adminId);
                    set_flash('success', 'Schedule cleared. Draft stays in review.');
                } else {
                    $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $scheduleInput, new DateTimeZone('Asia/Kolkata'));
                    if (!$date) {
                        throw new RuntimeException('Enter schedule date in local time.');
                    }
                    ai_schedule_blog_draft($draftId, $date, $adminId);
                    set_flash('success', 'Draft scheduled for automatic publishing.');
                }
                $activeTab = 'generator';
                break;
            case 'clear-schedule':
                $draftId = trim((string) ($_POST['draft_id'] ?? ''));
                if ($draftId === '') {
                    throw new RuntimeException('Select a draft to clear schedule.');
                }
                ai_schedule_blog_draft($draftId, null, $adminId);
                set_flash('success', 'Draft schedule cleared.');
                $activeTab = 'generator';
                break;
            case 'update-draft':
                $draftId = trim((string) ($_POST['draft_id'] ?? ''));
                if ($draftId === '') {
                    throw new RuntimeException('Select a draft to update.');
                }
                $payload = [
                    'draft_id' => $draftId,
                    'title' => $_POST['title'] ?? '',
                    'slug' => $_POST['slug'] ?? '',
                    'excerpt' => $_POST['excerpt'] ?? '',
                    'body' => $_POST['body'] ?? '',
                    'tone' => $_POST['tone'] ?? '',
                    'audience' => $_POST['audience'] ?? '',
                    'keywords' => $_POST['keywords'] ?? '',
                    'author_name' => $_POST['author_name'] ?? '',
                    'image_prompt' => $_POST['image_prompt'] ?? '',
                    'topic' => $_POST['topic'] ?? '',
                ];
                ai_save_blog_draft($payload, $adminId);
                set_flash('success', 'Draft updated successfully.');
                $activeTab = 'generator';
                break;
            default:
                throw new RuntimeException('Unsupported action.');
        }
    } catch (Throwable $exception) {
        set_flash('error', $exception->getMessage());
    }

    $redirect = 'admin-ai-studio.php?tab=' . urlencode($activeTab);
    if ($activeTab === 'generator' && !empty($draftId)) {
        $redirect .= '&draft=' . urlencode((string) $draftId);
    }
    if ($activeTab === 'generator') {
        $redirect .= '#ai-draft-tools';
    }
    header('Location: ' . $redirect);
    exit;
}

$settings = ai_get_settings();
$aiEnabled = $settings['enabled'];

ai_daily_notes_generate_if_due();
ai_publish_due_posts($db);
$drafts = ai_list_blog_drafts();
$notes = ai_daily_notes_recent(12);
$editingDraftId = '';
$editingDraft = null;
$editingError = '';
if ($activeTab === 'generator') {
    $editingDraftId = isset($_GET['draft']) ? trim((string) $_GET['draft']) : '';
    if ($editingDraftId !== '') {
        try {
            $editingDraft = ai_load_draft($editingDraftId);
        } catch (Throwable $exception) {
            $editingError = 'Draft not found or no longer available.';
            $editingDraftId = '';
        }
    }
}
$editingKeywords = '';
if (is_array($editingDraft) && !empty($editingDraft['keywords'])) {
    $editingKeywords = implode(', ', array_map('trim', (array) $editingDraft['keywords']));
}

$tabs = [
    'settings' => 'AI Settings',
    'generator' => 'AI Blog Generator',
    'notes' => 'AI Daily Notes',
];

$lastTest = '';
if (!empty($settings['last_test_result']) && !empty($settings['last_tested_at'])) {
    try {
        $testedAt = new DateTimeImmutable((string) $settings['last_tested_at'], new DateTimeZone('UTC'));
        $testedAt = $testedAt->setTimezone(new DateTimeZone('Asia/Kolkata'));
        $lastTest = sprintf('%s at %s IST', strtoupper((string) $settings['last_test_result']), $testedAt->format('d M Y · h:i A'));
    } catch (Throwable $exception) {
        $lastTest = strtoupper((string) $settings['last_test_result']);
    }
}

function ai_tab_class(string $current, string $tab): string
{
    return $current === $tab ? 'ai-tabs__link is-active' : 'ai-tabs__link';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>AI Studio | Admin</title>
  <meta name="description" content="Configure AI tools, generate blog drafts and assets, and review automated daily notes." />
  <link rel="icon" href="images/favicon.ico" />
  <link rel="stylesheet" href="style.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap"
    rel="stylesheet"
  />
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    crossorigin="anonymous"
    referrerpolicy="no-referrer"
  />
</head>
<body class="admin-ai" data-theme="light">
  <main class="admin-ai__shell">
    <header class="admin-ai__header">
      <div>
        <p class="admin-ai__subtitle">Admin workspace</p>
        <h1 class="admin-ai__title">AI Studio</h1>
        <p class="admin-ai__meta">Signed in as <strong><?= htmlspecialchars($admin['full_name'] ?? 'Administrator', ENT_QUOTES) ?></strong></p>
      </div>
      <div class="admin-ai__actions">
        <a href="admin-dashboard.php" class="btn btn-ghost"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to overview</a>
        <a href="logout.php" class="btn btn-primary"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i> Log out</a>
      </div>
    </header>

    <?php if ($flashMessage !== ''): ?>
    <div class="admin-alert admin-alert--<?= htmlspecialchars($flashTone, ENT_QUOTES) ?>" role="status" aria-live="polite">
      <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
      <span><?= htmlspecialchars($flashMessage, ENT_QUOTES) ?></span>
    </div>
    <?php endif; ?>

    <nav class="ai-tabs" aria-label="AI Studio sections">
      <?php foreach ($tabs as $tab => $label): ?>
      <a class="<?= ai_tab_class($activeTab, $tab) ?>" href="admin-ai-studio.php?tab=<?= urlencode($tab) ?>"><?= htmlspecialchars($label, ENT_QUOTES) ?></a>
      <?php endforeach; ?>
    </nav>

    <?php if ($activeTab === 'settings'): ?>
    <section class="admin-panel ai-panel" aria-labelledby="ai-settings-heading">
      <div class="admin-panel__header ai-panel__header">
        <div>
          <h2 id="ai-settings-heading">AI Settings</h2>
          <p>Control platform-wide AI availability and provider credentials. When AI is off, all studio tools stay disabled.</p>
        </div>
      </div>
      <form method="post" class="admin-form ai-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
        <input type="hidden" name="tab" value="settings" />
        <div class="admin-form__grid">
          <label class="ai-toggle">
            <span>AI Enabled</span>
            <input type="checkbox" name="enabled" value="1" <?= $settings['enabled'] ? 'checked' : '' ?> />
            <span class="ai-toggle__hint">Switch off to pause all AI modules instantly.</span>
          </label>
          <label>
            Provider
            <input type="text" name="provider" value="<?= htmlspecialchars($settings['provider'] ?? 'Gemini', ENT_QUOTES) ?>" placeholder="Gemini" />
          </label>
          <label>
            Text Model Code
            <input type="text" name="text_model" value="<?= htmlspecialchars($settings['text_model'] ?? '', ENT_QUOTES) ?>" placeholder="models/text-bison" />
          </label>
          <label>
            Image Model Code
            <input type="text" name="image_model" value="<?= htmlspecialchars($settings['image_model'] ?? '', ENT_QUOTES) ?>" placeholder="models/image-vision" />
          </label>
        </div>
        <label class="admin-form__full">
          API Key
          <input type="password" name="api_key" autocomplete="off" placeholder="Enter new key to replace stored value" />
          <span class="ai-field-help">Stored securely. Leave blank to keep the current key. Keys are never displayed.</span>
        </label>
        <?php if ($lastTest !== ''): ?>
        <p class="ai-field-note">Last connection test: <?= htmlspecialchars($lastTest, ENT_QUOTES) ?></p>
        <?php endif; ?>
        <div class="ai-form__actions">
          <button type="submit" name="action" value="save-settings" class="btn btn-primary">Save settings</button>
          <button type="submit" name="action" value="test-connection" class="btn btn-secondary">Test connection</button>
        </div>
      </form>
    </section>
    <?php endif; ?>

    <?php if ($activeTab === 'generator'): ?>
    <section class="admin-panel ai-panel" id="ai-draft-tools" aria-labelledby="ai-generator-heading">
      <div class="admin-panel__header ai-panel__header">
        <div>
          <h2 id="ai-generator-heading">AI Blog Generator</h2>
          <p>Provide a short prompt and AI Studio will draft the blog, attach artwork, and save everything automatically.</p>
        </div>
        <?php if (!$aiEnabled): ?>
        <span class="ai-status-badge">AI disabled</span>
        <?php endif; ?>
      </div>
      <form method="post" class="admin-form ai-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
        <input type="hidden" name="tab" value="generator" />
        <fieldset <?= $aiEnabled ? '' : 'disabled' ?> class="ai-form__fieldset">
          <label class="admin-form__full">
            Prompt for blog + image
            <textarea name="prompt" rows="4" placeholder="e.g. Rooftop solar adoption trends for small businesses in tier-2 cities" required></textarea>
            <span class="ai-field-help">Describe the idea in a few words. AI Studio will handle the title, content, and feature image.</span>
          </label>
          <div class="ai-form__actions">
            <button type="submit" name="action" value="generate-draft" class="btn btn-primary">Generate blog draft</button>
          </div>
        </fieldset>
      </form>
    </section>

    <section class="admin-panel ai-panel" aria-labelledby="ai-draft-library">
      <div class="admin-panel__header ai-panel__header">
        <div>
          <h2 id="ai-draft-library">AI Draft Library</h2>
          <p>Review AI-authored drafts, trigger feature images, and schedule automatic publishing.</p>
        </div>
      </div>
      <?php if (empty($drafts)): ?>
      <p class="ai-empty">No AI drafts yet. Generate a draft above to begin populating this list.</p>
      <?php else: ?>
      <div class="ai-draft-table">
        <table class="admin-table">
          <thead>
            <tr>
              <th scope="col">Draft</th>
              <th scope="col">Status</th>
              <th scope="col">Schedule</th>
              <th scope="col">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($drafts as $draft): ?>
            <tr>
              <td>
                <a href="admin-ai-studio.php?tab=generator&amp;draft=<?= urlencode($draft['id']) ?>#ai-draft-editor" class="admin-link"><?= htmlspecialchars($draft['title'] ?: $draft['topic'], ENT_QUOTES) ?></a>
                <div class="admin-muted">Topic: <?= htmlspecialchars($draft['topic'], ENT_QUOTES) ?></div>
                <div class="admin-muted">Slug: <?= htmlspecialchars($draft['slug'], ENT_QUOTES) ?></div>
                <?php if ($draft['cover_image']): ?>
                <div class="ai-draft-thumb">
                  <img src="<?= htmlspecialchars($draft['cover_image'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($draft['cover_image_alt'] ?: 'Draft cover image', ENT_QUOTES) ?>" />
                </div>
                <?php endif; ?>
              </td>
              <td>
                <span class="ai-status-label ai-status-label--<?= htmlspecialchars($draft['status'], ENT_QUOTES) ?>"><?= htmlspecialchars(ucfirst($draft['status']), ENT_QUOTES) ?></span>
                <?php if ($draft['post_status'] === 'published'): ?>
                <div class="ai-status-note">Published via auto scheduler</div>
                <?php if (!empty($draft['published_slug'])): ?>
                <div class="ai-status-note"><a href="blog/post.php?slug=<?= urlencode($draft['published_slug']) ?>" class="admin-link" target="_blank" rel="noopener">View post</a></div>
                <?php endif; ?>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($draft['scheduled_at'] instanceof DateTimeImmutable): ?>
                <div><?= htmlspecialchars($draft['scheduled_at']->format('d M Y · h:i A'), ENT_QUOTES) ?> IST</div>
                <?php else: ?>
                <div class="admin-muted">Not scheduled</div>
                <?php endif; ?>
              </td>
              <td class="ai-draft-actions">
                <?php if ($draft['post_status'] !== 'published'): ?>
                <form method="post">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
                  <input type="hidden" name="tab" value="generator" />
                  <input type="hidden" name="draft_id" value="<?= htmlspecialchars($draft['id'], ENT_QUOTES) ?>" />
                  <button type="submit" name="action" value="generate-image" class="btn btn-secondary btn-xs" <?= $aiEnabled ? '' : 'disabled' ?>>Generate Image</button>
                </form>
                <form method="post" class="ai-schedule-form">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
                  <input type="hidden" name="tab" value="generator" />
                  <input type="hidden" name="draft_id" value="<?= htmlspecialchars($draft['id'], ENT_QUOTES) ?>" />
                  <label>
                    <span class="sr-only">Schedule date</span>
                    <input type="datetime-local" name="schedule_at" value="<?= $draft['scheduled_at'] instanceof DateTimeImmutable ? htmlspecialchars($draft['scheduled_at']->format('Y-m-d\TH:i'), ENT_QUOTES) : '' ?>" <?= $aiEnabled ? '' : 'disabled' ?> />
                  </label>
                  <div class="ai-schedule-buttons">
                    <button type="submit" name="action" value="schedule-draft" class="btn btn-primary btn-xs" <?= $aiEnabled ? '' : 'disabled' ?>>Save</button>
                    <button type="submit" name="action" value="clear-schedule" class="btn btn-ghost btn-xs" <?= $aiEnabled ? '' : 'disabled' ?>>Clear</button>
                  </div>
                </form>
                <?php endif; ?>
                <a href="admin-ai-studio.php?tab=generator&amp;draft=<?= urlencode($draft['id']) ?>#ai-draft-editor" class="btn btn-ghost btn-xs">Edit</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </section>
    <?php if (is_array($editingDraft)): ?>
    <section class="admin-panel ai-panel" id="ai-draft-editor" aria-labelledby="ai-editor-heading">
      <div class="admin-panel__header ai-panel__header">
        <div>
          <h2 id="ai-editor-heading">Edit Draft</h2>
          <p>Update copy, tone, keywords, and artwork prompts directly from AI Studio.</p>
        </div>
        <span class="ai-status-badge">File-based</span>
      </div>
      <form method="post" class="admin-form ai-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
        <input type="hidden" name="tab" value="generator" />
        <input type="hidden" name="draft_id" value="<?= htmlspecialchars($editingDraft['id'], ENT_QUOTES) ?>" />
        <fieldset class="ai-form__fieldset" <?= $aiEnabled && (($editingDraft['status'] ?? 'draft') !== 'published') ? '' : 'disabled' ?> >
          <div class="admin-form__grid">
            <label>
              Title
              <input type="text" name="title" value="<?= htmlspecialchars($editingDraft['title'] ?? '', ENT_QUOTES) ?>" required />
            </label>
            <label>
              Topic
              <input type="text" name="topic" value="<?= htmlspecialchars($editingDraft['topic'] ?? '', ENT_QUOTES) ?>" />
            </label>
            <label>
              Slug
              <input type="text" name="slug" value="<?= htmlspecialchars($editingDraft['slug'] ?? '', ENT_QUOTES) ?>" />
            </label>
            <label>
              Tone
              <input type="text" name="tone" value="<?= htmlspecialchars($editingDraft['tone'] ?? '', ENT_QUOTES) ?>" />
            </label>
            <label>
              Audience
              <input type="text" name="audience" value="<?= htmlspecialchars($editingDraft['audience'] ?? '', ENT_QUOTES) ?>" />
            </label>
            <label>
              Author (optional)
              <input type="text" name="author_name" value="<?= htmlspecialchars($editingDraft['author_name'] ?? '', ENT_QUOTES) ?>" />
            </label>
          </div>
          <label class="admin-form__full">
            Keywords
            <input type="text" name="keywords" value="<?= htmlspecialchars($editingKeywords, ENT_QUOTES) ?>" placeholder="solar, adoption, finance" />
            <span class="ai-field-help">Separate keywords with commas. They feed into blog tags on publish.</span>
          </label>
          <label class="admin-form__full">
            Summary / Excerpt
            <textarea name="excerpt" rows="3"><?= htmlspecialchars($editingDraft['excerpt'] ?? '', ENT_QUOTES) ?></textarea>
          </label>
          <label class="admin-form__full">
            Body (HTML allowed)
            <textarea name="body" rows="10" required><?= htmlspecialchars($editingDraft['body'] ?? '', ENT_QUOTES) ?></textarea>
          </label>
          <label class="admin-form__full">
            Image Prompt
            <input type="text" name="image_prompt" value="<?= htmlspecialchars($editingDraft['image_prompt'] ?? '', ENT_QUOTES) ?>" placeholder="Feature image showing rooftop solar..." />
          </label>
          <div class="ai-form__actions">
            <?php if (($editingDraft['status'] ?? 'draft') === 'published'): ?>
            <span class="ai-status-note">Published drafts are locked for editing.</span>
            <?php else: ?>
            <button type="submit" name="action" value="update-draft" class="btn btn-primary" <?= $aiEnabled ? '' : 'disabled' ?>>Save draft changes</button>
            <?php endif; ?>
            <a href="admin-ai-studio.php?tab=generator" class="btn btn-ghost">Close</a>
          </div>
        </fieldset>
      </form>
    </section>
    <?php else: ?>
    <section class="admin-panel ai-panel" id="ai-draft-editor" aria-labelledby="ai-editor-heading">
      <div class="admin-panel__header ai-panel__header">
        <div>
          <h2 id="ai-editor-heading">AI Draft Editor</h2>
          <p>Select a draft from the library to edit its content.</p>
        </div>
      </div>
      <p class="ai-empty"><?= $editingError !== '' ? htmlspecialchars($editingError, ENT_QUOTES) : 'Choose any draft in the library to begin editing. Changes are saved to secure files.' ?></p>
    </section>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($activeTab === 'notes'): ?>
    <section class="admin-panel ai-panel" aria-labelledby="ai-notes-heading">
      <div class="admin-panel__header ai-panel__header">
        <div>
          <h2 id="ai-notes-heading">AI Daily Notes</h2>
          <p>Automated summaries appear nightly — 8 PM for the day’s work log and 9 PM for the next-day plan.</p>
        </div>
      </div>
      <?php if (empty($notes)): ?>
      <p class="ai-empty">Daily notes will appear after the first scheduled generation at 8 PM IST.</p>
      <?php else: ?>
      <div class="ai-notes-grid">
        <?php foreach ($notes as $note): ?>
        <article class="ai-note-card">
          <header>
            <p class="ai-note-card__type"><?= htmlspecialchars($note['label'], ENT_QUOTES) ?></p>
            <p class="ai-note-card__timestamp"><?= htmlspecialchars($note['display_label'], ENT_QUOTES) ?></p>
          </header>
          <p class="ai-note-card__content"><?= htmlspecialchars($note['content'], ENT_QUOTES) ?></p>
        </article>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </section>
    <?php endif; ?>
  </main>
</body>
</html>
