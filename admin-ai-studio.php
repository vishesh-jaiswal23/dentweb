<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bootstrap.php';

require_admin();

$admin = current_user();
$db = get_db();

$adminId = (int) ($admin['id'] ?? 0);
$csrfToken = $_SESSION['csrf_token'] ?? '';
$allowedTabs = ['settings', 'generator', 'notes', 'activity'];
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
                $testResult = ai_test_connection();
                set_flash($testResult['status'] === 'pass' ? 'success' : 'error', $testResult['message']);
                break;
            case 'generate-draft':
                $result = ai_generate_blog_draft($_POST, $adminId);
                set_flash('success', 'New draft generated: ' . $result['title']);
                $activeTab = 'generator';
                $draftId = $result['draft_id'];
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
            case 'publish-now':
                $draftId = trim((string) ($_POST['draft_id'] ?? ''));
                if ($draftId === '') {
                    throw new RuntimeException('Select a draft to publish.');
                }
                $published = ai_publish_blog_draft_now($db, $draftId, $adminId);
                $slug = $published['published_slug'] ?? ($published['slug'] ?? '');
                $message = 'Draft published immediately.';
                if ($slug !== '') {
                    $message .= sprintf(' Post slug: %s.', $slug);
                }
                set_flash('success', $message);
                $activeTab = 'generator';
                $draftId = '';
                break;
            case 'delete-draft':
                $draftId = trim((string) ($_POST['draft_id'] ?? ''));
                if ($draftId === '') {
                    throw new RuntimeException('Select a draft to delete.');
                }
                ai_delete_blog_draft($draftId, $adminId);
                set_flash('success', 'Draft deleted successfully.');
                $activeTab = 'generator';
                $draftId = '';
                break;
            default:
                throw new RuntimeException('The requested action is not supported. Please try again.');
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
$snapshots = ai_list_temp_snapshots();

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
    'activity' => 'AI Activity',
];

$activityLogs = [];
if ($activeTab === 'activity') {
    $activityLogs = ai_get_activity_logs();
}

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
          <label>
            TTS Model (Optional)
            <input type="text" name="tts_model" value="<?= htmlspecialchars($settings['tts_model'] ?? '', ENT_QUOTES) ?>" placeholder="models/tts-standard" />
          </label>
          <label>
            Temperature
            <input type="number" name="temperature" min="0.0" max="1.0" step="0.05" value="<?= htmlspecialchars((string) ($settings['temperature'] ?? 0.7), ENT_QUOTES) ?>" />
          </label>
          <label>
            Max Tokens
            <input type="number" name="max_tokens" min="256" max="8192" step="64" value="<?= htmlspecialchars((string) ($settings['max_tokens'] ?? 2048), ENT_QUOTES) ?>" />
          </label>
        </div>
        <div class="admin-form__full ai-api-key-field">
          <label for="api-key-input">API Key</label>
          <div class="ai-api-key-wrapper">
            <input id="api-key-input" type="password" name="api_key" autocomplete="off" placeholder="•••••••••••••••••••••••••••••••••••••••" />
            <button type="button" class="btn btn-secondary btn-xs" data-reveal-api-key>Reveal</button>
          </div>
          <span class="ai-field-help">Stored securely. Leave blank to keep the current key. Your key is masked for security.</span>
        </div>
        <?php if ($lastTest !== ''): ?>
        <p class="ai-field-note">Last connection test: <?= htmlspecialchars($lastTest, ENT_QUOTES) ?></p>
        <?php endif; ?>
        <div class="ai-form__actions">
          <button type="submit" name="action" value="save-settings" class="btn btn-primary">Save settings</button>
          <button type="submit" name="action" value="test-connection" class="btn btn-secondary">Test connection</button>
        </div>
      </form>
      <div class="admin-form__grid">
        <label>
            Max Drafts per Day
            <input type="number" name="max_drafts_per_day" min="1" max="100" value="<?= htmlspecialchars((string) ($settings['max_drafts_per_day'] ?? 10), ENT_QUOTES) ?>" />
        </label>
        <label>
            Max Tokens per Day
            <input type="number" name="max_tokens_per_day" min="1000" max="100000" step="1000" value="<?= htmlspecialchars((string) ($settings['max_tokens_per_day'] ?? 50000), ENT_QUOTES) ?>" />
        </label>
      </div>
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
      <form method="post" class="admin-form ai-form" id="ai-blog-generator-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
        <input type="hidden" name="tab" value="generator" />
        <fieldset <?= $aiEnabled ? '' : 'disabled' ?> class="ai-form__fieldset">
          <?php if (!empty($snapshots)): ?>
          <div class="admin-alert admin-alert--info" role="status">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            <span>Found an unsaved draft. <button type="button" class="admin-link" id="restore-snapshot-btn" data-draft-id="<?= htmlspecialchars(array_key_first($snapshots), ENT_QUOTES) ?>">Restore it?</button></span>
          </div>
          <?php endif; ?>
          <div class="admin-form__grid">
            <label>
              Title (Optional)
              <input type="text" name="title" placeholder="e.g. Solar Adoption Trends" />
            </label>
            <label>
              Tone
              <select name="tone">
                <option value="formal">Formal</option>
                <option value="informative" selected>Informative</option>
                <option value="casual">Casual</option>
                <option value="inspirational">Inspirational</option>
              </select>
            </label>
            <label>
              Target Length
              <select name="target_length">
                <option value="short">Short (~300 words)</option>
                <option value="medium" selected>Medium (~600 words)</option>
                <option value="long">Long (~1000 words)</option>
              </select>
            </label>
            <label>
              Audience (Optional)
              <input type="text" name="audience" placeholder="e.g. Small Business Owners" />
            </label>
          </div>
          <label class="admin-form__full">
            Topic / Keywords
            <textarea name="topic" rows="3" placeholder="e.g. Rooftop solar adoption trends for small businesses in tier-2 cities" required></textarea>
            <span class="ai-field-help">Provide a clear topic or a comma-separated list of keywords. This is the primary input for the draft.</span>
          </label>
          <div class="admin-form__grid">
            <label class="ai-toggle">
              <span>Live Typing</span>
              <input type="checkbox" name="live_typing" value="1" checked />
              <span class="ai-toggle__hint">Stream the draft word-by-word.</span>
            </label>
          </div>
          <div class="ai-form__actions">
            <button type="submit" name="action" value="generate-draft" class="btn btn-primary">Generate blog draft</button>
          </div>
        </fieldset>
      </form>

      <div id="ai-live-preview-container" style="display: none;">
        <div class="admin-panel__header ai-panel__header">
            <div>
                <h2 id="ai-live-preview-heading">Live Preview</h2>
                <p>The AI is generating the blog draft in real-time.</p>
            </div>
            <span id="ai-live-status" class="ai-status-badge">Initializing...</span>
        </div>
        <div class="ai-live-preview">
            <div id="ai-live-preview-content" class="ai-live-preview-content"></div>
        </div>
        <div class="ai-live-controls">
            <button id="ai-pause-resume" class="btn btn-secondary">Pause</button>
            <button id="ai-stop-save" class="btn btn-primary">Stop & Save Draft</button>
            <button id="ai-discard" class="btn btn-danger">Discard</button>
        </div>
        <div class="ai-live-meta">
            <span>Elapsed time: <span id="ai-elapsed-time">0s</span></span>
            <span>Tokens/sec: <span id="ai-tokens-sec">0</span></span>
        </div>
      </div>
    </section>

    <section class="admin-panel ai-panel" aria-labelledby="ai-test-playground-heading">
        <div class="admin-panel__header ai-panel__header">
            <div>
                <h2 id="ai-test-playground-heading">AI Test Playground</h2>
                <p>Use this space to test the AI's capabilities with different prompts and models.</p>
            </div>
        </div>
        <div class="ai-test-playground">
            <div class="ai-tabs">
                <button class="ai-tabs__link is-active" data-tab="live-chat">Live Chat Tester</button>
                <button class="ai-tabs__link" data-tab="image-generator">Live Image Generator</button>
                <button class="ai-tabs__link" data-tab="tts-tester">TTS Tester</button>
            </div>
            <div id="live-chat" class="ai-test-playground__tab is-active">
                <div class="ai-chat-window">
                    <div id="ai-chat-messages" class="ai-chat-messages"></div>
                    <form id="ai-chat-form" class="ai-chat-form">
                        <input type="text" id="ai-chat-input" placeholder="Type your message..." />
                        <button type="submit" class="btn btn-primary">Send</button>
                    </form>
                </div>
            </div>
            <div id="image-generator" class="ai-test-playground__tab">
                <form id="ai-image-generator-form" class="admin-form">
                    <label for="ai-image-prompt">Describe the image:</label>
                    <input type="text" id="ai-image-prompt" name="prompt" required />
                    <button type="submit" class="btn btn-primary">Generate Image</button>
                </form>
                <div id="ai-image-preview" class="ai-image-preview"></div>
            </div>
            <div id="tts-tester" class="ai-test-playground__tab">
                <form id="ai-tts-form" class="admin-form">
                    <label for="ai-tts-text">Text to speak:</label>
                    <textarea id="ai-tts-text" name="text" rows="3" required></textarea>
                    <button type="submit" class="btn btn-primary">Generate Audio</button>
                </form>
                <div id="ai-tts-audio" class="ai-tts-audio"></div>
            </div>
        </div>
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
                <form method="post">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
                  <input type="hidden" name="tab" value="generator" />
                  <input type="hidden" name="draft_id" value="<?= htmlspecialchars($draft['id'], ENT_QUOTES) ?>" />
                  <button type="submit" name="action" value="publish-now" class="btn btn-success btn-xs">Post Now</button>
                </form>
                <?php endif; ?>
                <form method="post" onsubmit="return confirm('Delete this draft? This action cannot be undone.');">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
                  <input type="hidden" name="tab" value="generator" />
                  <input type="hidden" name="draft_id" value="<?= htmlspecialchars($draft['id'], ENT_QUOTES) ?>" />
                  <button type="submit" name="action" value="delete-draft" class="btn btn-danger btn-xs" <?= $draft['post_status'] === 'published' ? 'disabled' : '' ?>>Delete</button>
                </form>
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
      <?php
      $previewBody = blog_sanitize_html((string) ($editingDraft['body'] ?? ''));
      $previewImage = ai_draft_image_data_uri($editingDraft);
      $previewAltText = (string) ($editingDraft['image_alt'] ?? 'Draft cover image');
      $previewKeywords = array_values(array_filter(array_map('trim', (array) ($editingDraft['keywords'] ?? []))));
      ?>
      <div class="ai-editor-layout">
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
      <aside class="ai-preview-card" aria-live="polite">
        <header>
          <h3>Live blog preview</h3>
          <p class="ai-preview-meta">Preview refreshes when you save changes.</p>
        </header>
        <article class="ai-preview">
          <?php if ($previewImage !== ''): ?>
          <figure class="ai-preview-cover">
            <img src="<?= htmlspecialchars($previewImage, ENT_QUOTES) ?>" alt="<?= htmlspecialchars($previewAltText !== '' ? $previewAltText : 'Draft cover image', ENT_QUOTES) ?>" />
          </figure>
          <?php endif; ?>
          <div class="ai-preview-head">
            <h3><?= htmlspecialchars($editingDraft['title'] ?? '', ENT_QUOTES) ?></h3>
            <?php if (!empty($editingDraft['excerpt'])): ?>
            <p class="ai-preview-excerpt"><?= htmlspecialchars($editingDraft['excerpt'], ENT_QUOTES) ?></p>
            <?php endif; ?>
          </div>
          <div class="ai-preview-body">
            <?php if ($previewBody !== ''): ?>
            <?= $previewBody ?>
            <?php else: ?>
            <p class="ai-preview-empty">Add content to see the formatted blog preview.</p>
            <?php endif; ?>
          </div>
          <?php if (!empty($previewKeywords)): ?>
          <div class="ai-preview-tags">
            <h4>Keywords</h4>
            <ul>
              <?php foreach ($previewKeywords as $keyword): ?>
              <li><?= htmlspecialchars($keyword, ENT_QUOTES) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
          <?php endif; ?>
        </article>
      </aside>
      </div>
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

    <?php if ($activeTab === 'activity'): ?>
    <section class="admin-panel ai-panel" aria-labelledby="ai-activity-heading">
      <div class="admin-panel__header ai-panel__header">
        <div>
          <h2 id="ai-activity-heading">AI Activity</h2>
          <p>Recent AI-related events across the platform.</p>
        </div>
      </div>
      <?php if (empty($activityLogs)): ?>
      <p class="ai-empty">No AI activity recorded yet.</p>
      <?php else: ?>
      <table class="admin-table">
        <thead>
          <tr>
            <th scope="col">Timestamp</th>
            <th scope="col">User</th>
            <th scope="col">Action</th>
            <th scope="col">Model</th>
            <th scope="col">Status</th>
            <th scope="col">Latency</th>
            <th scope="col">Tokens</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $timezone = new DateTimeZone('Asia/Kolkata');
          foreach ($activityLogs as $log):
              $timestamp = '–';
              try {
                  $utc = new DateTimeImmutable($log['timestamp'], new DateTimeZone('UTC'));
                  $timestamp = $utc->setTimezone($timezone)->format('d M Y, h:i:s A');
              } catch (Throwable $e) {}
              $details = $log['details'] ?? [];
              $tokens = (int) ($details['usage']['totalTokens'] ?? ($details['tokens'] ?? 0));
          ?>
          <tr>
            <td><?= htmlspecialchars($timestamp, ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars((string) ($log['user_id'] ?? 'N/A'), ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($log['action'] ?? 'N/A', ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($details['model'] ?? 'N/A', ENT_QUOTES) ?></td>
            <td>
              <span class="status-badge status-<?= htmlspecialchars($details['status'] ?? 'default', ENT_QUOTES) ?>">
                <?= htmlspecialchars(ucfirst($details['status'] ?? 'N/A'), ENT_QUOTES) ?>
              </span>
            </td>
            <td><?= isset($details['latency']) ? htmlspecialchars(sprintf('%.2f s', $details['latency']), ENT_QUOTES) : 'N/A' ?></td>
            <td><?= $tokens > 0 ? htmlspecialchars((string) $tokens, ENT_QUOTES) : 'N/A' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </section>
    <?php endif; ?>
  </main>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
        const blogGeneratorForm = document.getElementById('ai-blog-generator-form');
        const livePreviewContainer = document.getElementById('ai-live-preview-container');
        const livePreviewContent = document.getElementById('ai-live-preview-content');
        const liveStatus = document.getElementById('ai-live-status');
        const pauseResumeBtn = document.getElementById('ai-pause-resume');
        const stopSaveBtn = document.getElementById('ai-stop-save');
        const discardBtn = document.getElementById('ai-discard');
        const elapsedTimeEl = document.getElementById('ai-elapsed-time');
        const tokensSecEl = document.getElementById('ai-tokens-sec');

        let source;
        let isPaused = false;
        let draftId = '';
        let startTime;
        let timerInterval;

        if (blogGeneratorForm) {
            blogGeneratorForm.addEventListener('submit', (e) => {
                e.preventDefault();
                livePreviewContainer.style.display = 'block';
                liveStatus.textContent = 'Generating...';
                isPaused = false;
                pauseResumeBtn.textContent = 'Pause';

                const formData = new FormData(blogGeneratorForm);
                const payload = Object.fromEntries(formData.entries());

                source = new EventSource(`api/admin.php?action=stream-generate-draft&payload=${encodeURIComponent(JSON.stringify(payload))}`);
                startTime = Date.now();
                timerInterval = setInterval(updateTimer, 1000);

                source.addEventListener('open', () => {
                    liveStatus.textContent = 'Connection opened...';
                });

                source.addEventListener('chunk', (event) => {
                    const data = JSON.parse(event.data);
                    if (!isPaused) {
                        livePreviewContent.innerHTML += data.text;
                    }
                });

                source.addEventListener('error', (event) => {
                    liveStatus.textContent = 'Error occurred';
                    source.close();
                    clearInterval(timerInterval);
                });

                source.addEventListener('start', (event) => {
                    const data = JSON.parse(event.data);
                    draftId = data.draftId;
                });

                source.addEventListener('saved', (event) => {
                    const data = JSON.parse(event.data);
                    liveStatus.textContent = 'Draft saved!';
                });

                source.addEventListener('complete', (event) => {
                    liveStatus.textContent = 'Generation complete';
                    source.close();
                    clearInterval(timerInterval);
                });
            });
        }

        if (pauseResumeBtn) {
            pauseResumeBtn.addEventListener('click', () => {
                isPaused = !isPaused;
                pauseResumeBtn.textContent = isPaused ? 'Resume' : 'Pause';
            });
        }

        if (stopSaveBtn) {
            stopSaveBtn.addEventListener('click', () => {
                source.close();
                clearInterval(timerInterval);
                liveStatus.textContent = 'Stopped and saved.';
            });
        }

        if (discardBtn) {
            discardBtn.addEventListener('click', () => {
                source.close();
                clearInterval(timerInterval);
                livePreviewContainer.style.display = 'none';
                livePreviewContent.innerHTML = '';
            });
        }

        function updateTimer() {
            const elapsedSeconds = Math.round((Date.now() - startTime) / 1000);
            elapsedTimeEl.textContent = `${elapsedSeconds}s`;
        }

        // Playground tabs
        const tabs = document.querySelectorAll('.ai-tabs__link[data-tab]');
        const tabContents = document.querySelectorAll('.ai-test-playground__tab');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                tabs.forEach(t => t.classList.remove('is-active'));
                tab.classList.add('is-active');

                const target = document.getElementById(tab.dataset.tab);
                tabContents.forEach(tc => tc.classList.remove('is-active'));
                target.classList.add('is-active');
            });
        });

        const ttsForm = document.getElementById('ai-tts-form');
        const ttsAudio = document.getElementById('ai-tts-audio');

        if (ttsForm) {
            ttsForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(ttsForm);
                const text = formData.get('text');

                try {
                    const response = await fetch('api/admin.php?action=generate-tts', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ text }),
                    });

                    if (!response.ok) {
                        throw new Error('Failed to generate audio.');
                    }

                    const result = await response.json();
                    const audioSrc = `data:${result.data.mime_type};base64,${result.data.audio_content}`;
                    ttsAudio.innerHTML = `<audio controls src="${audioSrc}"></audio>`;
                } catch (error) {
                    ttsAudio.innerHTML = `<p class="error">${error.message}</p>`;
                }
            });
        }

        const chatForm = document.getElementById('ai-chat-form');
        const chatInput = document.getElementById('ai-chat-input');
        const chatMessages = document.getElementById('ai-chat-messages');

        if (chatForm) {
            chatForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const message = chatInput.value;
                chatInput.value = '';

                const userMessage = document.createElement('div');
                userMessage.classList.add('chat-message', 'user');
                userMessage.textContent = message;
                chatMessages.appendChild(userMessage);

                try {
                    const response = await fetch('api/admin.php?action=live-chat', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ prompt: message }),
                    });

                    if (!response.ok) {
                        throw new Error('Failed to get response from AI.');
                    }

                    const result = await response.json();
                    const aiMessage = document.createElement('div');
                    aiMessage.classList.add('chat-message', 'ai');
                    aiMessage.textContent = result.data.text;
                    chatMessages.appendChild(aiMessage);
                } catch (error) {
                    const errorMessage = document.createElement('div');
                    errorMessage.classList.add('chat-message', 'ai', 'error');
                    errorMessage.textContent = error.message;
                    chatMessages.appendChild(errorMessage);
                }
            });
        }

        const imageGeneratorForm = document.getElementById('ai-image-generator-form');
        const imagePreview = document.getElementById('ai-image-preview');

        if (imageGeneratorForm) {
            imageGeneratorForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(imageGeneratorForm);
                const prompt = formData.get('prompt');

                try {
                    const response = await fetch('api/admin.php?action=generate-image', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ prompt }),
                    });

                    if (!response.ok) {
                        throw new Error('Failed to generate image.');
                    }

                    const result = await response.json();
                    const imageSrc = `data:image/png;base64,${result.data.image}`;
                    imagePreview.innerHTML = `<img src="${imageSrc}" alt="${prompt}" />`;
                } catch (error) {
                    imagePreview.innerHTML = `<p class="error">${error.message}</p>`;
                }
            });
        }

        const restoreSnapshotBtn = document.getElementById('restore-snapshot-btn');
        if (restoreSnapshotBtn) {
            restoreSnapshotBtn.addEventListener('click', async () => {
                const draftId = restoreSnapshotBtn.dataset.draftId;
                try {
                    const response = await fetch('api/admin.php?action=restore-draft', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ draftId }),
                    });

                    if (!response.ok) {
                        throw new Error('Failed to restore draft.');
                    }

                    const result = await response.json();
                    const form = document.getElementById('ai-blog-generator-form');
                    form.querySelector('[name="topic"]').value = result.data.content;
                } catch (error) {
                    console.error('Failed to restore snapshot:', error);
                }
            });
        }
    });
  </script>
</body>
</html>
