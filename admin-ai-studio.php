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
        switch ($action) {
            case 'save-settings':
                ai_save_settings($db, $_POST, $adminId);
                set_flash('success', 'AI settings saved.');
                break;
            case 'test-connection':
                $result = ai_test_connection($db);
                $tone = $result['status'] === 'pass' ? 'success' : 'warning';
                set_flash($tone, $result['message']);
                break;
            case 'generate-draft':
                ai_require_enabled($db);
                $generatorInput = [
                    'topic' => $_POST['topic'] ?? '',
                    'tone' => $_POST['tone'] ?? '',
                    'audience' => $_POST['audience'] ?? '',
                    'keywords' => $_POST['keywords'] ?? '',
                    'purpose' => $_POST['purpose'] ?? '',
                ];
                $draft = ai_generate_blog_draft_content($generatorInput);
                $_SESSION['ai_generator_state'] = array_merge($generatorInput, [
                    'title' => $draft['title'],
                    'body_html' => $draft['body_html'],
                    'excerpt' => $draft['excerpt'],
                    'generated_title' => $draft['title'],
                    'generated_body' => $draft['body_html'],
                ]);
                set_flash('success', 'Draft generated. Review and refine before saving.');
                $activeTab = 'generator';
                break;
            case 'save-draft':
                ai_require_enabled($db);
                $payload = [
                    'post_id' => isset($_POST['post_id']) && $_POST['post_id'] !== '' ? (int) $_POST['post_id'] : null,
                    'draft_id' => isset($_POST['draft_id']) && $_POST['draft_id'] !== '' ? (int) $_POST['draft_id'] : null,
                    'title' => $_POST['title'] ?? '',
                    'slug' => $_POST['slug'] ?? '',
                    'excerpt' => $_POST['excerpt'] ?? '',
                    'body' => $_POST['body_html'] ?? '',
                    'author_name' => $admin['full_name'] ?? '',
                    'keywords' => $_POST['keywords'] ?? '',
                    'topic' => $_POST['topic'] ?? '',
                    'tone' => $_POST['tone'] ?? '',
                    'audience' => $_POST['audience'] ?? '',
                    'purpose' => $_POST['purpose'] ?? '',
                    'generated_title' => $_POST['generated_title'] ?? '',
                    'generated_body' => $_POST['generated_body'] ?? '',
                    'cover_image' => $_POST['cover_image'] ?? '',
                    'cover_image_alt' => $_POST['cover_image_alt'] ?? '',
                ];
                $saved = ai_save_blog_draft($db, $payload, $adminId);
                unset($_SESSION['ai_generator_state']);
                set_flash('success', sprintf('Draft saved. Manage it anytime from Blog publishing (post #%d).', (int) $saved['id']));
                $activeTab = 'generator';
                break;
            case 'generate-image':
                $draftId = (int) ($_POST['draft_id'] ?? 0);
                if ($draftId <= 0) {
                    throw new RuntimeException('Select a draft to generate artwork.');
                }
                ai_generate_image_for_draft($db, $draftId, $adminId);
                set_flash('success', 'Feature image refreshed for the draft.');
                $activeTab = 'generator';
                break;
            case 'schedule-draft':
                $draftId = (int) ($_POST['draft_id'] ?? 0);
                if ($draftId <= 0) {
                    throw new RuntimeException('Select a draft to schedule.');
                }
                $scheduleInput = trim((string) ($_POST['schedule_at'] ?? ''));
                if ($scheduleInput === '') {
                    ai_schedule_blog_draft($db, $draftId, null, $adminId);
                    set_flash('success', 'Schedule cleared. Draft stays in review.');
                } else {
                    $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $scheduleInput, new DateTimeZone('Asia/Kolkata'));
                    if (!$date) {
                        throw new RuntimeException('Enter schedule date in local time.');
                    }
                    ai_schedule_blog_draft($db, $draftId, $date, $adminId);
                    set_flash('success', 'Draft scheduled for automatic publishing.');
                }
                $activeTab = 'generator';
                break;
            case 'clear-schedule':
                $draftId = (int) ($_POST['draft_id'] ?? 0);
                if ($draftId <= 0) {
                    throw new RuntimeException('Select a draft to clear schedule.');
                }
                ai_schedule_blog_draft($db, $draftId, null, $adminId);
                set_flash('success', 'Draft schedule cleared.');
                $activeTab = 'generator';
                break;
            default:
                throw new RuntimeException('Unsupported action.');
        }
    } catch (Throwable $exception) {
        if ($action === 'generate-draft') {
            $_SESSION['ai_generator_state'] = [
                'topic' => $_POST['topic'] ?? '',
                'tone' => $_POST['tone'] ?? '',
                'audience' => $_POST['audience'] ?? '',
                'keywords' => $_POST['keywords'] ?? '',
                'purpose' => $_POST['purpose'] ?? '',
                'title' => $_POST['title'] ?? '',
                'body_html' => $_POST['body_html'] ?? '',
                'excerpt' => $_POST['excerpt'] ?? '',
            ];
            $activeTab = 'generator';
        }
        set_flash('error', $exception->getMessage());
    }

    $redirect = 'admin-ai-studio.php?tab=' . urlencode($activeTab);
    if ($activeTab === 'generator') {
        $redirect .= '#ai-draft-tools';
    }
    header('Location: ' . $redirect);
    exit;
}

$settings = ai_get_settings($db);
$aiEnabled = $settings['enabled'];

ai_daily_notes_generate_if_due($db);
$drafts = ai_list_blog_drafts($db);
$notes = ai_daily_notes_recent($db, 12);

$generatorState = $_SESSION['ai_generator_state'] ?? [];
if (isset($_SESSION['ai_generator_state'])) {
    unset($_SESSION['ai_generator_state']);
}

$draftDefaults = [
    'topic' => '',
    'tone' => 'Informative',
    'audience' => '',
    'keywords' => '',
    'purpose' => '',
    'title' => '',
    'body_html' => '',
    'excerpt' => '',
    'generated_title' => '',
    'generated_body' => '',
    'post_id' => '',
    'draft_id' => '',
];

$draftForm = $draftDefaults;
foreach ($generatorState as $key => $value) {
    if (!array_key_exists($key, $draftForm)) {
        continue;
    }
    if ($key === 'keywords') {
        if (is_array($value)) {
            $draftForm[$key] = implode(', ', array_map('strval', $value));
        } else {
            $draftForm[$key] = (string) $value;
        }
        continue;
    }
    if (is_scalar($value) || $value === null) {
        $draftForm[$key] = (string) $value;
    }
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
          <p>Create long-form drafts with AI, tweak wording, and push them into the blog workflow without leaving this page.</p>
        </div>
        <?php if (!$aiEnabled): ?>
        <span class="ai-status-badge">AI disabled</span>
        <?php endif; ?>
      </div>
      <form method="post" class="admin-form ai-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
        <input type="hidden" name="tab" value="generator" />
        <fieldset <?= $aiEnabled ? '' : 'disabled' ?> class="ai-form__fieldset">
          <div class="admin-form__grid">
            <label>
              Topic
              <input type="text" name="topic" value="<?= htmlspecialchars($draftForm['topic'], ENT_QUOTES) ?>" placeholder="Solar finance update" required />
            </label>
            <label>
              Tone
              <input type="text" name="tone" value="<?= htmlspecialchars($draftForm['tone'], ENT_QUOTES) ?>" placeholder="Informative" />
            </label>
            <label>
              Audience
              <input type="text" name="audience" value="<?= htmlspecialchars($draftForm['audience'], ENT_QUOTES) ?>" placeholder="Operations leaders" />
            </label>
            <label>
              Keywords
              <input type="text" name="keywords" value="<?= htmlspecialchars($draftForm['keywords'], ENT_QUOTES) ?>" placeholder="PM Surya Ghar, rooftop, subsidy" />
            </label>
          </div>
          <label class="admin-form__full">
            Purpose / Notes for AI
            <input type="text" name="purpose" value="<?= htmlspecialchars($draftForm['purpose'], ENT_QUOTES) ?>" placeholder="Highlight subsidy reimbursements and installation timelines." />
          </label>
          <label class="admin-form__full">
            Generated Title
            <input type="text" name="title" value="<?= htmlspecialchars($draftForm['title'], ENT_QUOTES) ?>" required />
          </label>
          <label class="admin-form__full">
            Draft Body (HTML allowed)
            <textarea name="body_html" rows="14" required><?= htmlspecialchars($draftForm['body_html'], ENT_QUOTES) ?></textarea>
          </label>
          <label class="admin-form__full">
            Excerpt
            <textarea name="excerpt" rows="3" placeholder="Short summary for cards."><?= htmlspecialchars($draftForm['excerpt'], ENT_QUOTES) ?></textarea>
          </label>
          <input type="hidden" name="generated_title" value="<?= htmlspecialchars($draftForm['generated_title'], ENT_QUOTES) ?>" />
          <input type="hidden" name="generated_body" value="<?= htmlspecialchars($draftForm['generated_body'], ENT_QUOTES) ?>" />
          <div class="ai-form__actions">
            <button type="submit" name="action" value="generate-draft" class="btn btn-secondary">Generate Draft</button>
            <button type="submit" name="action" value="save-draft" class="btn btn-primary">Save as Draft</button>
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
                <a href="admin-blog.php?id=<?= (int) $draft['post_id'] ?>" class="admin-link"><?= htmlspecialchars($draft['title'] ?: $draft['topic'], ENT_QUOTES) ?></a>
                <div class="admin-muted">Topic: <?= htmlspecialchars($draft['topic'], ENT_QUOTES) ?></div>
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
                <form method="post">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
                  <input type="hidden" name="tab" value="generator" />
                  <input type="hidden" name="draft_id" value="<?= (int) $draft['id'] ?>" />
                  <button type="submit" name="action" value="generate-image" class="btn btn-secondary btn-xs" <?= $aiEnabled ? '' : 'disabled' ?>>Generate Image</button>
                </form>
                <form method="post" class="ai-schedule-form">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
                  <input type="hidden" name="tab" value="generator" />
                  <input type="hidden" name="draft_id" value="<?= (int) $draft['id'] ?>" />
                  <label>
                    <span class="sr-only">Schedule date</span>
                    <input type="datetime-local" name="schedule_at" value="<?= $draft['scheduled_at'] instanceof DateTimeImmutable ? htmlspecialchars($draft['scheduled_at']->format('Y-m-d\TH:i'), ENT_QUOTES) : '' ?>" <?= $aiEnabled ? '' : 'disabled' ?> />
                  </label>
                  <div class="ai-schedule-buttons">
                    <button type="submit" name="action" value="schedule-draft" class="btn btn-primary btn-xs" <?= $aiEnabled ? '' : 'disabled' ?>>Save</button>
                    <button type="submit" name="action" value="clear-schedule" class="btn btn-ghost btn-xs" <?= $aiEnabled ? '' : 'disabled' ?>>Clear</button>
                  </div>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </section>
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
