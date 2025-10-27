<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/ai.php';

require_admin();

$db = get_db();
$user = current_user();
$csrfToken = $_SESSION['csrf_token'] ?? '';
$geminiView = gemini_settings_admin_view($db);
$drafts = gemini_prepare_drafts(blog_admin_list($db));

function admin_ai_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function admin_ai_status_label(bool $enabled): string
{
    return $enabled ? 'Enabled' : 'Disabled';
}

function admin_ai_status_icon(bool $enabled): string
{
    return $enabled ? 'fa-circle-check' : 'fa-circle-xmark';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>AI Settings &amp; Studio | Dakshayani Enterprises</title>
  <meta
    name="description"
    content="Configure Gemini access, test connectivity, and generate fresh Dentweb blog drafts without publishing automatically."
  />
  <link rel="icon" href="images/favicon.ico" />
  <link rel="stylesheet" href="style.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap"
    rel="stylesheet"
  />
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    crossorigin="anonymous"
    referrerpolicy="no-referrer"
  />
</head>
<body>
  <main class="dashboard">
    <div class="container dashboard-shell">
      <div class="dashboard-auth-bar" role="banner">
        <div class="dashboard-auth-user">
          <i class="fa-solid fa-robot" aria-hidden="true"></i>
          <div>
            <small>AI Settings &amp; Studio</small>
            <strong><?= admin_ai_escape($user['full_name'] ?? 'Administrator') ?> · Admin</strong>
          </div>
        </div>
        <div class="dashboard-auth-actions">
          <a href="admin-dashboard.php" class="btn btn-ghost">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            Back to overview
          </a>
          <a href="logout.php" class="btn btn-primary">
            <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
            Log out
          </a>
        </div>
      </div>

      <section class="dashboard-form" aria-labelledby="gemini-provider-title">
        <h1 id="gemini-provider-title">Gemini provider</h1>
        <p class="dashboard-muted">
          Control the single AI provider used across Dentweb. Store the API key securely, switch access on or off,
          and validate connectivity without making any changes to live content.
        </p>
        <div class="ai-status" role="status" aria-live="polite">
          <span
            class="ai-status-badge"
            data-gemini-status
            data-state="<?= $geminiView['enabled'] ? 'enabled' : 'disabled' ?>"
          >
            <i class="fa-solid <?= admin_ai_status_icon($geminiView['enabled']) ?>" aria-hidden="true"></i>
            <?= admin_ai_escape(admin_ai_status_label($geminiView['enabled'])) ?>
          </span>
          <span class="ai-status-key">
            Stored key:
            <strong data-masked-key>
              <?= $geminiView['hasKey'] ? admin_ai_escape($geminiView['maskedKey']) : 'Not set' ?>
            </strong>
          </span>
        </div>
        <form class="dashboard-form-grid" data-gemini-form>
          <label>
            <span>Gemini API key</span>
            <input
              type="password"
              name="api_key"
              placeholder="Enter or update the API key"
              autocomplete="off"
              inputmode="text"
            />
            <span class="dashboard-form-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i>Leave blank to keep the stored key.</span>
          </label>
          <label class="ai-toggle">
            <input type="checkbox" name="enabled" value="1" data-gemini-enabled <?= $geminiView['enabled'] ? 'checked' : '' ?> />
            <span>Enable Gemini-powered features across Dentweb</span>
          </label>
          <div class="dashboard-ai-suite__actions">
            <button type="submit" class="btn btn-primary">
              <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
              Save settings
            </button>
            <button type="button" class="btn btn-ghost" data-gemini-test>
              <i class="fa-solid fa-vial" aria-hidden="true"></i>
              Test connection
            </button>
          </div>
        </form>
        <p class="ai-feedback" data-feedback aria-live="polite" hidden></p>
      </section>

      <section class="dashboard-form" aria-labelledby="gemini-studio-title">
        <h2 id="gemini-studio-title">Gemini studio</h2>
        <p class="dashboard-muted">
          Generate a draft blog post straight into the publishing queue. Gemini drafts are always saved in <strong>Draft</strong>
          state so editors can review, refine, and publish manually.
        </p>
        <form class="dashboard-inline-form" data-generate-form>
          <h3>Create a new draft</h3>
          <div class="dashboard-inline-fields">
            <label>
              <span>Topic</span>
              <input type="text" name="topic" required placeholder="e.g. Rooftop solar for MSMEs" />
            </label>
            <label>
              <span>Tone</span>
              <select name="tone">
                <option value="informative">Informative</option>
                <option value="conversational">Conversational</option>
                <option value="technical">Technical</option>
                <option value="promotional">Promotional</option>
              </select>
            </label>
            <label>
              <span>Audience</span>
              <input type="text" name="audience" value="Jharkhand households" />
            </label>
            <label>
              <span>Call to action</span>
              <input type="text" name="call_to_action" value="Request a Dentweb rooftop solar consultation." />
            </label>
          </div>
          <div class="dashboard-ai-suite__actions">
            <button type="submit" class="btn btn-secondary">
              <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
              Generate draft
            </button>
          </div>
        </form>
        <p class="ai-feedback" data-generate-feedback aria-live="polite" hidden></p>
      </section>

      <section class="dashboard-form dashboard-form--table" aria-labelledby="draft-list-title">
        <div>
          <h2 id="draft-list-title">Recent Gemini drafts</h2>
          <p class="dashboard-muted">Review and edit drafts before publishing. Gemini never auto-publishes content.</p>
        </div>
        <div class="dashboard-table-wrapper">
          <table class="dashboard-table">
            <thead>
              <tr>
                <th scope="col">Title</th>
                <th scope="col">Status</th>
                <th scope="col">Updated</th>
              </tr>
            </thead>
            <tbody data-draft-list>
              <?php if (empty($drafts)): ?>
              <tr class="dashboard-empty-row" data-empty-row>
                <td colspan="3">No Gemini drafts yet. Generate your first draft above.</td>
              </tr>
              <?php else: ?>
              <?php foreach ($drafts as $draft): ?>
              <tr>
                <td>
                  <strong><?= admin_ai_escape($draft['title']) ?></strong>
                  <div class="ai-draft-list-meta">Slug: <?= admin_ai_escape($draft['slug']) ?></div>
                </td>
                <td><?= admin_ai_escape(ucfirst($draft['status'])) ?></td>
                <td><?= admin_ai_escape($draft['updatedDisplay']) ?></td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </main>
  <script>
    window.dentwebAdminAI = {
      csrfToken: <?= json_encode($csrfToken, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
      endpoints: {
        update: 'api/admin.php?action=update-gemini',
        test: 'api/admin.php?action=test-gemini',
        generate: 'api/admin.php?action=generate-blog-draft'
      },
      gemini: <?= json_encode($geminiView, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
      drafts: <?= json_encode($drafts, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
    };
  </script>
  <script src="admin-ai.js" defer></script>
</body>
</html>
