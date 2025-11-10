<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/ai_gemini.php';

require_admin();
$admin = current_user();
$adminId = (int) ($admin['id'] ?? 0);

$csrfToken = $_SESSION['csrf_token'] ?? '';
$settings = ai_settings_load();
$chatHistory = ai_chat_history_load($adminId);

$flashContext = $_SESSION['ai_flash_context'] ?? null;
if ($flashContext !== null) {
    unset($_SESSION['ai_flash_context']);
}
$flashData = consume_flash();
$flashMessage = '';
$flashTone = 'info';
if (is_array($flashData)) {
    $flashMessage = is_string($flashData['message'] ?? null) ? trim($flashData['message']) : '';
    $candidateTone = is_string($flashData['type'] ?? null) ? strtolower($flashData['type']) : 'info';
    if (in_array($candidateTone, ['success', 'info', 'warning', 'error'], true)) {
        $flashTone = $candidateTone;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token(is_string($token) ? $token : null)) {
        set_flash('error', 'Your session expired. Please try again.');
        $_SESSION['ai_flash_context'] = 'settings';
        header('Location: admin-ai-studio.php');
        exit;
    }

    $action = (string) ($_POST['action'] ?? '');

    try {
        switch ($action) {
            case 'save-settings':
                $candidate = ai_collect_settings_from_request($settings, $_POST);
                if ($candidate['api_key'] === '') {
                    throw new RuntimeException('Gemini API key is required to save settings.');
                }
                $ping = ai_gemini_ping($candidate, 'Configuration validation ping from AI Studio');
                if (!($ping['ok'] ?? false)) {
                    $error = is_string($ping['error'] ?? null) ? $ping['error'] : 'Unable to connect to Gemini.';
                    throw new RuntimeException('Gemini validation failed: ' . $error);
                }

                ai_settings_save($candidate);
                $settings = ai_settings_load();
                set_flash('success', 'Settings saved successfully. Connected.');
                $_SESSION['ai_flash_context'] = 'settings';
                header('Location: admin-ai-studio.php');
                exit;

            case 'test-connection':
                $candidate = ai_collect_settings_from_request($settings, $_POST);
                if ($candidate['api_key'] === '') {
                    throw new RuntimeException('Add a Gemini API key before testing the connection.');
                }
                $ping = ai_gemini_ping($candidate, 'Connection test from AI Studio');
                if (!($ping['ok'] ?? false)) {
                    $error = is_string($ping['error'] ?? null) ? $ping['error'] : 'Unable to connect to Gemini.';
                    throw new RuntimeException('Gemini test failed: ' . $error);
                }
                set_flash('success', 'Gemini connection confirmed.');
                $_SESSION['ai_flash_context'] = 'settings';
                header('Location: admin-ai-studio.php');
                exit;

            default:
                throw new RuntimeException('Unsupported action.');
        }
    } catch (Throwable $exception) {
        set_flash('error', $exception->getMessage());
        $_SESSION['ai_flash_context'] = 'settings';
        header('Location: admin-ai-studio.php');
        exit;
    }
}

$settingsMaskedKey = ai_settings_masked_key($settings['api_key']);
$settingsUpdatedAt = $settings['updated_at'] ?? null;
$settingsUpdatedDisplay = '';
if (is_string($settingsUpdatedAt) && $settingsUpdatedAt !== '') {
    try {
        $dt = new DateTimeImmutable($settingsUpdatedAt);
        $settingsUpdatedDisplay = $dt->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('d M Y · h:i A');
    } catch (Throwable $exception) {
        $settingsUpdatedDisplay = $settingsUpdatedAt;
    }
}

$chatHistoryJson = json_encode($chatHistory, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$settingsForClient = [
    'enabled' => (bool) ($settings['enabled'] ?? false),
    'temperature' => ai_normalize_temperature($settings['temperature'] ?? 0.9),
    'maxTokens' => ai_normalize_max_tokens($settings['max_tokens'] ?? 1024),
    'models' => [
        'text' => $settings['models']['text'] ?? 'gemini-2.5-flash',
        'image' => $settings['models']['image'] ?? 'gemini-2.5-flash-image',
        'tts' => $settings['models']['tts'] ?? 'gemini-2.5-flash-preview-tts',
    ],
];
$settingsJson = json_encode($settingsForClient, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$toastMeta = '';
if ($flashMessage !== '') {
    $toastMeta = json_encode([
        'message' => $flashMessage,
        'tone' => $flashTone,
        'context' => $flashContext,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>AI Studio | Admin</title>
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
  <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
</head>
<body class="admin-ai-studio" data-theme="light">
  <main class="admin-ai-studio__shell">
    <header class="admin-ai-studio__header">
      <div>
        <p class="admin-ai-studio__subtitle">Admin workspace</p>
        <h1 class="admin-ai-studio__title">AI Studio</h1>
        <p class="admin-ai-studio__meta">Signed in as <strong><?= htmlspecialchars($admin['full_name'] ?? 'Administrator', ENT_QUOTES) ?></strong></p>
      </div>
      <div class="admin-ai-studio__actions">
        <a href="admin-dashboard.php" class="btn btn-ghost"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to overview</a>
        <a href="logout.php" class="btn btn-primary"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i> Log out</a>
      </div>
    </header>

    <section class="admin-panel" aria-labelledby="ai-settings">
      <div class="admin-panel__header">
        <div>
          <h2 id="ai-settings">AI Settings (Gemini-only)</h2>
          <p>Configure Gemini model codes, authentication, and response parameters for the admin workspace.</p>
        </div>
        <div class="ai-settings__status" role="status">
          <i class="fa-solid fa-circle-dot" aria-hidden="true"></i>
          <span><?= htmlspecialchars($settings['enabled'] ? 'AI responses enabled' : 'AI responses disabled', ENT_QUOTES) ?></span>
        </div>
      </div>

      <?php if ($flashMessage !== '' && $flashContext === 'settings'): ?>
      <div class="ai-settings__feedback ai-settings__feedback--<?= htmlspecialchars($flashTone, ENT_QUOTES) ?>" role="status" aria-live="polite">
        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
        <span><?= htmlspecialchars($flashMessage, ENT_QUOTES) ?></span>
      </div>
      <?php endif; ?>

      <form method="post" class="admin-form" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
        <input type="hidden" name="action" value="save-settings" data-ai-settings-action />
        <input type="hidden" name="ai_enabled" value="0" />
        <div class="ai-settings__grid">
          <label>
            Gemini Text Model Code
            <input type="text" name="gemini_text_model" value="<?= htmlspecialchars($settings['models']['text'] ?? 'gemini-2.5-flash', ENT_QUOTES) ?>" required autocomplete="off" />
            <small>Default: gemini-2.5-flash</small>
          </label>
          <label>
            Gemini Image Model Code
            <input type="text" name="gemini_image_model" value="<?= htmlspecialchars($settings['models']['image'] ?? 'gemini-2.5-flash-image', ENT_QUOTES) ?>" required autocomplete="off" />
            <small>Default: gemini-2.5-flash-image</small>
          </label>
          <label>
            Gemini TTS Model Code
            <input type="text" name="gemini_tts_model" value="<?= htmlspecialchars($settings['models']['tts'] ?? 'gemini-2.5-flash-preview-tts', ENT_QUOTES) ?>" required autocomplete="off" />
            <small>Default: gemini-2.5-flash-preview-tts</small>
          </label>
          <label class="ai-settings__api">
            Gemini API Key
            <div class="ai-settings__api-field">
              <input
                type="password"
                name="api_key"
                id="ai-api-key"
                placeholder="<?= $settingsMaskedKey !== '' ? htmlspecialchars($settingsMaskedKey, ENT_QUOTES) : 'Enter Gemini API key' ?>"
                autocomplete="new-password"
              />
              <button type="button" class="btn btn-ghost btn-sm" data-ai-reveal data-api-key="<?= htmlspecialchars($settings['api_key'] ?? '', ENT_QUOTES) ?>">
                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                Reveal once
              </button>
            </div>
            <small>Leave blank to keep the stored key. The key is never logged.</small>
          </label>
        </div>

        <div class="ai-settings__controls">
          <label class="dashboard-toggle">
            <input type="checkbox" name="ai_enabled" value="1" <?= $settings['enabled'] ? 'checked' : '' ?> />
            <span>AI On / Off</span>
          </label>
          <div class="ai-settings__range">
            <label for="ai-temperature">Temperature <span data-ai-temp-value><?= htmlspecialchars(number_format($settings['temperature'], 2, '.', ''), ENT_QUOTES) ?></span></label>
            <input type="range" id="ai-temperature" name="temperature" min="0" max="2" step="0.1" value="<?= htmlspecialchars((string) $settings['temperature'], ENT_QUOTES) ?>" />
          </div>
          <label class="ai-settings__max-tokens">
            Max token limit
            <input type="number" name="max_tokens" min="1" max="8192" value="<?= htmlspecialchars((string) $settings['max_tokens'], ENT_QUOTES) ?>" />
          </label>
          <div class="ai-settings__actions">
            <button type="submit" class="btn btn-primary">
              <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
              Save settings
            </button>
            <button type="submit" class="btn btn-ghost" data-ai-test>
              <i class="fa-solid fa-vial-circle-check" aria-hidden="true"></i>
              Test Gemini connection
            </button>
          </div>
        </div>
        <p class="ai-settings__meta">
          Using Gemini text model <strong><?= htmlspecialchars($settings['models']['text'] ?? 'gemini-2.5-flash', ENT_QUOTES) ?></strong> · Temperature <strong><?= htmlspecialchars(number_format($settings['temperature'], 2, '.', ''), ENT_QUOTES) ?></strong> · Max tokens <strong><?= htmlspecialchars((string) $settings['max_tokens'], ENT_QUOTES) ?></strong><br />
          Last updated <?= $settingsUpdatedDisplay !== '' ? htmlspecialchars($settingsUpdatedDisplay, ENT_QUOTES) : '—' ?>
        </p>
      </form>
    </section>

    <section class="admin-panel" aria-labelledby="ai-chat">
      <div class="admin-panel__header">
        <div>
          <h2 id="ai-chat">AI Chat (Gemini)</h2>
          <p>Chat with the configured Gemini text model. Streaming replies respect the saved temperature and token settings.</p>
        </div>
        <div class="ai-chat__actions">
          <button type="button" class="btn btn-ghost btn-sm" data-ai-export>
            <i class="fa-solid fa-file-export" aria-hidden="true"></i>
            Export chat as PDF
          </button>
          <button type="button" class="btn btn-ghost btn-sm" data-ai-clear>
            <i class="fa-solid fa-trash" aria-hidden="true"></i>
            Clear history
          </button>
        </div>
      </div>

      <div class="ai-chat" data-ai-chat data-enabled="<?= $settings['enabled'] ? 'true' : 'false' ?>">
        <aside class="ai-chat__sidebar">
          <h3>Quick prompts</h3>
          <ul>
            <li><button type="button" data-ai-prompt="Summarise today's operations updates in three bullet points.">Operations summary</button></li>
            <li><button type="button" data-ai-prompt="Draft a customer email acknowledging receipt of a solar installation query.">Customer email</button></li>
            <li><button type="button" data-ai-prompt="Outline a proposal for expanding rooftop solar adoption in urban schools.">Proposal outline</button></li>
            <li><button type="button" data-ai-prompt="Provide a motivational update for the field installation team this week.">Team motivation</button></li>
          </ul>
        </aside>
        <div class="ai-chat__console">
          <div class="ai-chat__status" data-ai-disabled-message <?= $settings['enabled'] ? 'hidden' : '' ?>>
            <i class="fa-solid fa-power-off" aria-hidden="true"></i>
            <p>AI responses are currently disabled. Enable Gemini in the settings to continue.</p>
          </div>
          <div class="ai-chat__history" data-ai-history aria-live="polite"></div>
          <form class="ai-chat__composer" data-ai-composer>
            <label for="ai-chat-message" class="sr-only">Your message</label>
            <textarea id="ai-chat-message" name="message" rows="3" placeholder="Ask Gemini for assistance…" required></textarea>
            <div class="ai-chat__composer-actions">
              <button type="submit" class="btn btn-primary" data-ai-send>
                <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                Send
              </button>
            </div>
          </form>
        </div>
      </div>
    </section>

    <section class="admin-panel" aria-labelledby="ai-blog-generator">
      <div class="admin-panel__header">
        <div>
          <h2 id="ai-blog-generator">Blog Generator (Gemini text)</h2>
          <p>Generate long-form blog drafts with the saved Gemini text model and publish directly to the blog system.</p>
        </div>
        <div class="ai-blog-generator__status" data-blog-status aria-live="polite">Idle</div>
      </div>

      <div class="ai-blog-generator" data-blog-generator>
        <form class="ai-blog-generator__form" data-blog-form novalidate>
          <div class="ai-form-grid">
            <label>
              Title
              <input type="text" name="blog_title" data-blog-title required placeholder="Enter working title" />
            </label>
            <label>
              Brief
              <textarea name="blog_brief" data-blog-brief rows="3" placeholder="Summarise the article goals"></textarea>
            </label>
            <label>
              Keywords
              <input type="text" name="blog_keywords" data-blog-keywords placeholder="Comma separated keywords" />
            </label>
            <label>
              Tone
              <input type="text" name="blog_tone" data-blog-tone placeholder="e.g. confident, friendly" />
            </label>
          </div>
          <div class="ai-blog-generator__controls">
            <div class="ai-blog-generator__progress" data-blog-progress role="status"></div>
            <div class="ai-blog-generator__actions">
              <button type="button" class="btn btn-primary" data-blog-generate>
                <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                Generate blog
              </button>
              <button type="button" class="btn btn-ghost" data-blog-save>
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                Save draft
              </button>
              <button type="button" class="btn btn-ghost" data-blog-preview-scroll>
                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                Preview
              </button>
              <button type="button" class="btn btn-primary" data-blog-publish>
                <i class="fa-solid fa-cloud-upload" aria-hidden="true"></i>
                Publish
              </button>
            </div>
            <p class="ai-blog-generator__autosave" data-blog-autosave-status aria-live="polite">Draft not saved</p>
          </div>
        </form>

        <aside class="ai-blog-preview">
          <div class="ai-blog-preview__cover" data-blog-cover hidden></div>
          <h3>Live preview</h3>
          <div class="ai-blog-preview__content" data-blog-preview aria-live="polite">
            <p class="ai-blog-preview__placeholder">Generated blog content will appear here.</p>
          </div>
        </aside>
      </div>
    </section>

    <section class="admin-panel" aria-labelledby="ai-image-generator">
      <div class="admin-panel__header">
        <div>
          <h2 id="ai-image-generator">AI Image Generator (Gemini image)</h2>
          <p>Create supporting visuals with the saved Gemini image model and attach them to your blog draft.</p>
        </div>
        <div class="ai-image-generator__status" data-image-status aria-live="polite">Idle</div>
      </div>

      <div class="ai-image-generator" data-image-generator>
        <div class="ai-image-generator__inputs">
          <label>
            Prompt
            <textarea rows="2" data-image-prompt placeholder="Describe the illustration you need"></textarea>
          </label>
          <div class="ai-image-generator__actions">
            <button type="button" class="btn btn-ghost btn-sm" data-image-autofill>
              <i class="fa-solid fa-lightbulb" aria-hidden="true"></i>
              Use blog context
            </button>
            <button type="button" class="btn btn-primary" data-image-generate>
              <i class="fa-solid fa-palette" aria-hidden="true"></i>
              Generate image
            </button>
          </div>
        </div>
        <div class="ai-image-generator__preview" data-image-preview hidden>
          <figure>
            <img src="" alt="Generated visual" data-image-output />
            <figcaption data-image-caption></figcaption>
          </figure>
          <div class="ai-image-generator__preview-actions">
            <a href="#" class="btn btn-ghost btn-sm" data-image-download download>
              <i class="fa-solid fa-download" aria-hidden="true"></i>
              Download
            </a>
            <button type="button" class="btn btn-primary btn-sm" data-image-attach>
              <i class="fa-solid fa-paperclip" aria-hidden="true"></i>
              Attach to draft
            </button>
          </div>
        </div>
      </div>
    </section>

    <section class="admin-panel" aria-labelledby="ai-tts-generator">
      <div class="admin-panel__header">
        <div>
          <h2 id="ai-tts-generator">TTS Generator (Gemini voice)</h2>
          <p>Voice your copy using the saved Gemini TTS model and download it for distribution.</p>
        </div>
        <div class="ai-tts-generator__status" data-tts-status aria-live="polite">Idle</div>
      </div>

      <div class="ai-tts-generator" data-tts-generator>
        <label>
          Text to narrate
          <textarea rows="3" data-tts-text placeholder="Paste the text you want to voice"></textarea>
        </label>
        <div class="ai-tts-generator__controls">
          <label>
            Format
            <select data-tts-format>
              <option value="mp3">MP3</option>
              <option value="wav">WAV</option>
            </select>
          </label>
          <button type="button" class="btn btn-primary" data-tts-generate>
            <i class="fa-solid fa-volume-high" aria-hidden="true"></i>
            Generate audio
          </button>
        </div>
        <div class="ai-tts-generator__player" data-tts-output hidden>
          <audio controls data-tts-audio></audio>
          <a href="#" class="btn btn-ghost btn-sm" data-tts-download download>
            <i class="fa-solid fa-download" aria-hidden="true"></i>
            Download audio
          </a>
        </div>
      </div>
    </section>

    <section class="admin-panel" aria-labelledby="ai-sandbox">
      <div class="admin-panel__header">
        <div>
          <h2 id="ai-sandbox">AI Sandbox (Gemini only)</h2>
          <p>Experiment with text, image, and voice outputs using the configured Gemini models without affecting chat or blog workflows.</p>
        </div>
      </div>

      <div class="ai-sandbox" data-sandbox>
        <div class="ai-sandbox__tabs" role="tablist">
          <button type="button" role="tab" aria-selected="true" data-sandbox-tab="text">Text Sandbox</button>
          <button type="button" role="tab" aria-selected="false" data-sandbox-tab="image">Image Sandbox</button>
          <button type="button" role="tab" aria-selected="false" data-sandbox-tab="tts">TTS Sandbox</button>
        </div>

        <div class="ai-sandbox__panels">
          <section class="ai-sandbox__panel" data-sandbox-panel="text" role="tabpanel">
            <header>
              <h3>Gemini Text Model</h3>
              <p class="ai-sandbox__meta">Model: <span data-sandbox-text-model><?= htmlspecialchars($settings['models']['text'] ?? 'gemini-2.5-flash', ENT_QUOTES) ?></span></p>
            </header>
            <form class="ai-sandbox__form" data-sandbox-text-form novalidate>
              <label>
                Prompt
                <textarea rows="3" data-sandbox-text-input placeholder="Ask Gemini for a quick experiment"></textarea>
              </label>
              <div class="ai-sandbox__actions">
                <button type="submit" class="btn btn-primary" data-sandbox-text-run>
                  <i class="fa-solid fa-play" aria-hidden="true"></i>
                  Run prompt
                </button>
                <span class="ai-sandbox__status" data-sandbox-text-status aria-live="polite">Idle</span>
              </div>
            </form>
            <div class="ai-sandbox__output" data-sandbox-text-output>
              <p class="ai-sandbox__placeholder">Responses stream here.</p>
            </div>
          </section>

          <section class="ai-sandbox__panel" data-sandbox-panel="image" role="tabpanel" hidden>
            <header>
              <h3>Gemini Image Model</h3>
              <p class="ai-sandbox__meta">Model: <span data-sandbox-image-model><?= htmlspecialchars($settings['models']['image'] ?? 'gemini-2.5-flash-image', ENT_QUOTES) ?></span></p>
            </header>
            <form class="ai-sandbox__form" data-sandbox-image-form novalidate>
              <label>
                Prompt
                <textarea rows="2" data-sandbox-image-input placeholder="Describe the visual you want to explore"></textarea>
              </label>
              <div class="ai-sandbox__actions">
                <button type="submit" class="btn btn-primary" data-sandbox-image-run>
                  <i class="fa-solid fa-palette" aria-hidden="true"></i>
                  Generate visual
                </button>
                <span class="ai-sandbox__status" data-sandbox-image-status aria-live="polite">Idle</span>
              </div>
            </form>
            <div class="ai-sandbox__media" data-sandbox-image-output hidden>
              <figure>
                <img src="" alt="Sandbox visual" data-sandbox-image-preview />
                <figcaption data-sandbox-image-caption></figcaption>
              </figure>
              <a href="#" class="btn btn-ghost btn-sm" data-sandbox-image-download download>
                <i class="fa-solid fa-download" aria-hidden="true"></i>
                Download
              </a>
            </div>
          </section>

          <section class="ai-sandbox__panel" data-sandbox-panel="tts" role="tabpanel" hidden>
            <header>
              <h3>Gemini TTS Model</h3>
              <p class="ai-sandbox__meta">Model: <span data-sandbox-tts-model><?= htmlspecialchars($settings['models']['tts'] ?? 'gemini-2.5-flash-preview-tts', ENT_QUOTES) ?></span></p>
            </header>
            <form class="ai-sandbox__form" data-sandbox-tts-form novalidate>
              <label>
                Text
                <textarea rows="3" data-sandbox-tts-input placeholder="Paste text to voice instantly"></textarea>
              </label>
              <div class="ai-sandbox__form-row">
                <label>
                  Format
                  <select data-sandbox-tts-format>
                    <option value="mp3">MP3</option>
                    <option value="wav">WAV</option>
                  </select>
                </label>
              </div>
              <div class="ai-sandbox__actions">
                <button type="submit" class="btn btn-primary" data-sandbox-tts-run>
                  <i class="fa-solid fa-volume-high" aria-hidden="true"></i>
                  Generate audio
                </button>
                <span class="ai-sandbox__status" data-sandbox-tts-status aria-live="polite">Idle</span>
              </div>
            </form>
            <div class="ai-sandbox__media" data-sandbox-tts-output hidden>
              <audio controls data-sandbox-tts-audio></audio>
              <a href="#" class="btn btn-ghost btn-sm" data-sandbox-tts-download download>
                <i class="fa-solid fa-download" aria-hidden="true"></i>
                Download
              </a>
            </div>
          </section>
        </div>
      </div>
    </section>

    <section class="admin-panel" aria-labelledby="automation-scheduler">
      <div class="admin-panel__header">
        <div>
          <h2 id="automation-scheduler">Automation Scheduler</h2>
          <p>Automatically draft blogs with Gemini text, visuals, and audio on a recurring cadence. Drafts are stored without publishing.</p>
        </div>
        <div class="automation-scheduler__next" data-scheduler-next aria-live="polite">Next run: —</div>
      </div>

      <form class="automation-scheduler" data-scheduler-form novalidate>
        <div class="automation-scheduler__grid">
          <label>
            Topic
            <input type="text" data-scheduler-topic placeholder="e.g. Rooftop solar incentives" />
          </label>
          <label>
            Frequency
            <select data-scheduler-frequency>
              <option value="daily">Daily</option>
              <option value="weekly" selected>Weekly</option>
              <option value="monthly">Monthly</option>
            </select>
          </label>
          <label class="dashboard-toggle">
            <input type="checkbox" data-scheduler-enabled />
            <span>Scheduler enabled</span>
          </label>
        </div>
        <div class="automation-scheduler__actions">
          <button type="button" class="btn btn-primary" data-scheduler-save>
            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
            Save schedule
          </button>
          <button type="button" class="btn btn-ghost" data-scheduler-run>
            <i class="fa-solid fa-rocket" aria-hidden="true"></i>
            Run now
          </button>
          <span class="automation-scheduler__status" data-scheduler-status aria-live="polite">Idle</span>
        </div>
      </form>

      <div class="automation-scheduler__log" data-scheduler-logs>
        <header>
          <h3>Automation log</h3>
          <p>Latest auto-generated drafts with associated assets.</p>
        </header>
        <ul></ul>
      </div>
    </section>

    <section class="admin-panel" aria-labelledby="usage-logs">
      <div class="admin-panel__header">
        <div>
          <h2 id="usage-logs">Usage &amp; Logs</h2>
          <p>Track Gemini token usage, approximate spend, and any API issues across the studio.</p>
        </div>
      </div>

      <div class="usage-logs" data-usage-shell>
        <div class="usage-logs__grid">
          <article>
            <h3>Daily usage</h3>
            <p class="usage-logs__metric" data-usage-daily-tokens>0 tokens</p>
            <p class="usage-logs__cost" data-usage-daily-cost>₹0.00</p>
          </article>
          <article>
            <h3>Monthly usage</h3>
            <p class="usage-logs__metric" data-usage-monthly-tokens>0 tokens</p>
            <p class="usage-logs__cost" data-usage-monthly-cost>₹0.00</p>
          </article>
          <article>
            <h3>Aggregate</h3>
            <p class="usage-logs__metric" data-usage-aggregate-tokens>0 tokens</p>
            <p class="usage-logs__cost" data-usage-aggregate-cost>₹0.00</p>
          </article>
        </div>

        <div class="usage-logs__pricing">
          <h3>Pricing reference</h3>
          <ul data-usage-pricing></ul>
        </div>

        <div class="usage-logs__errors">
          <header>
            <h3>Error logs</h3>
            <p>Recent Gemini issues across text, image, and audio calls.</p>
          </header>
          <ul data-error-log></ul>
          <div class="usage-logs__error-actions">
            <button type="button" class="btn btn-primary btn-sm" data-error-retry>
              <i class="fa-solid fa-rotate-right" aria-hidden="true"></i>
              Retry last action
            </button>
            <button type="button" class="btn btn-ghost btn-sm" data-error-copy>
              <i class="fa-solid fa-copy" aria-hidden="true"></i>
              Copy error details
            </button>
          </div>
        </div>
      </div>
    </section>
  </main>

  <div class="dashboard-toast-container" data-ai-toast-container hidden></div>

  <script>
    window.csrfToken = <?= json_encode($csrfToken, JSON_UNESCAPED_SLASHES) ?>;
    window.aiChatHistory = <?= $chatHistoryJson !== false ? $chatHistoryJson : '[]' ?>;
    window.aiSettings = <?= $settingsJson !== false ? $settingsJson : '{}' ?>;
    window.aiToastMeta = <?= $toastMeta !== '' ? $toastMeta : 'null' ?>;
  </script>
  <script>
    (function () {
      'use strict';

      const settingsForm = document.querySelector('[data-ai-settings-action]')?.form;
      const testButton = document.querySelector('[data-ai-test]');
      const actionInput = document.querySelector('[data-ai-settings-action]');
      const tempSlider = document.getElementById('ai-temperature');
      const tempValue = document.querySelector('[data-ai-temp-value]');
      const revealButton = document.querySelector('[data-ai-reveal]');
      const toastContainer = document.querySelector('[data-ai-toast-container]');

      function showToast(message, tone = 'info') {
        if (!toastContainer) {
          return;
        }
        toastContainer.hidden = false;
        const toast = document.createElement('div');
        toast.className = `dashboard-toast dashboard-toast--${tone}`;
        toast.setAttribute('data-state', 'visible');
        toast.innerHTML = `<span>${message}</span>`;
        toastContainer.appendChild(toast);
        setTimeout(() => {
          toast.setAttribute('data-state', 'hidden');
          setTimeout(() => toast.remove(), 250);
        }, 4500);
      }

      if (window.aiToastMeta && window.aiToastMeta.context === 'settings' && window.aiToastMeta.message) {
        showToast(window.aiToastMeta.message, window.aiToastMeta.tone || 'info');
      }

      if (settingsForm && testButton && actionInput) {
        testButton.addEventListener('click', function (event) {
          event.preventDefault();
          actionInput.value = 'test-connection';
          settingsForm.submit();
        });
        settingsForm.addEventListener('submit', function () {
          if (actionInput.value !== 'test-connection') {
            actionInput.value = 'save-settings';
          }
        });
      }

      if (tempSlider && tempValue) {
        tempSlider.addEventListener('input', () => {
          tempValue.textContent = Number(tempSlider.value).toFixed(2);
        });
      }

      if (revealButton) {
        let revealed = false;
        revealButton.addEventListener('click', () => {
          if (revealed) {
            revealButton.disabled = true;
            return;
          }
          const field = document.getElementById('ai-api-key');
          if (!field) {
            return;
          }
          const key = revealButton.getAttribute('data-api-key') || '';
          if (key === '') {
            showToast('No API key stored.', 'warning');
            revealButton.disabled = true;
            return;
          }
          field.value = key;
          field.type = 'text';
          field.focus();
          revealButton.disabled = true;
          revealed = true;
        });
      }

      // AI Chat logic
      const chatState = {
        history: Array.isArray(window.aiChatHistory) ? window.aiChatHistory.slice() : [],
        enabled: !!(window.aiSettings && window.aiSettings.enabled),
        pendingPrompt: null,
      };

      const historyContainer = document.querySelector('[data-ai-history]');
      const composer = document.querySelector('[data-ai-composer]');
      const textarea = composer ? composer.querySelector('textarea[name="message"]') : null;
      const sendButton = composer ? composer.querySelector('[data-ai-send]') : null;
      const quickPrompts = document.querySelectorAll('[data-ai-prompt]');
      const clearButton = document.querySelector('[data-ai-clear]');
      const exportButton = document.querySelector('[data-ai-export]');
      const disabledMessage = document.querySelector('[data-ai-disabled-message]');
      const chatShell = document.querySelector('[data-ai-chat]');

      function escapeHtml(value) {
        return String(value)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;');
      }

      function formatTimestamp(iso) {
        if (!iso) {
          return '';
        }
        try {
          const date = new Date(iso);
          if (Number.isNaN(date.getTime())) {
            return '';
          }
          return date.toLocaleString('en-IN', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
          });
        } catch (error) {
          return '';
        }
      }

      function renderHistory() {
        if (!historyContainer) {
          return;
        }
        historyContainer.innerHTML = '';
        chatState.history.forEach((entry) => {
          const message = document.createElement('article');
          const role = entry.role === 'assistant' ? 'assistant' : 'user';
          message.className = `ai-chat__message ai-chat__message--${role}`;
          const meta = document.createElement('header');
          meta.className = 'ai-chat__meta';
          const label = role === 'assistant' ? 'Gemini' : 'You';
          const time = formatTimestamp(entry.timestamp);
          meta.textContent = time ? `${label} · ${time}` : label;
          const bubble = document.createElement('div');
          bubble.className = 'ai-chat__bubble';
          bubble.innerHTML = escapeHtml(entry.text || '').replace(/\n/g, '<br />');
          message.appendChild(meta);
          message.appendChild(bubble);
          historyContainer.appendChild(message);
        });
        historyContainer.scrollTop = historyContainer.scrollHeight;
      }

      function setChatEnabled(enabled) {
        chatState.enabled = !!enabled;
        if (composer) {
          composer.toggleAttribute('aria-disabled', !chatState.enabled);
        }
        if (textarea) {
          textarea.disabled = !chatState.enabled;
        }
        if (sendButton) {
          sendButton.disabled = !chatState.enabled;
        }
        if (disabledMessage) {
          disabledMessage.hidden = !!chatState.enabled;
        }
        if (chatShell) {
          chatShell.setAttribute('data-enabled', chatState.enabled ? 'true' : 'false');
        }
      }

      function appendMessage(role, text, options = {}) {
        const entry = {
          role: role === 'assistant' ? 'assistant' : 'user',
          text: text,
          timestamp: options.timestamp || new Date().toISOString(),
        };
        chatState.history.push(entry);
        renderHistory();
        return entry;
      }

      function streamIntoBubble(bubble, text) {
        const tokens = text.split(/(\s+)/);
        let index = 0;
        function step() {
          if (index >= tokens.length) {
            return;
          }
          bubble.innerHTML += escapeHtml(tokens[index]).replace(/\n/g, '<br />');
          historyContainer.scrollTop = historyContainer.scrollHeight;
          index += 1;
          setTimeout(step, 45);
        }
        step();
      }

      async function sendPrompt(prompt) {
        if (!chatState.enabled) {
          showToast('Enable Gemini to start chatting.', 'warning');
          return;
        }
        if (!prompt || !prompt.trim()) {
          return;
        }
        appendMessage('user', prompt.trim());
        if (textarea) {
          textarea.value = '';
        }
        if (sendButton) {
          sendButton.disabled = true;
        }

        const placeholder = document.createElement('article');
        placeholder.className = 'ai-chat__message ai-chat__message--assistant';
        const meta = document.createElement('header');
        meta.className = 'ai-chat__meta';
        meta.textContent = 'Gemini · responding…';
        const bubble = document.createElement('div');
        bubble.className = 'ai-chat__bubble';
        placeholder.appendChild(meta);
        placeholder.appendChild(bubble);
        historyContainer.appendChild(placeholder);
        historyContainer.scrollTop = historyContainer.scrollHeight;

        try {
          const response = await fetch('api/gemini.php?action=chat', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': window.csrfToken || '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ message: prompt }),
          });

          if (!response.ok) {
            throw new Error('Request failed');
          }

          const payload = await response.json();
          if (!payload || !payload.success) {
            const errorText = payload && payload.error ? payload.error : 'Gemini did not respond.';
            throw new Error(errorText);
          }

          chatState.history = Array.isArray(payload.history) ? payload.history : chatState.history;
          bubble.innerHTML = '';
          streamIntoBubble(bubble, payload.reply || '');
          meta.textContent = 'Gemini · just now';
        } catch (error) {
          bubble.innerHTML = `<span class="ai-chat__error">${escapeHtml(error.message || 'Failed to reach Gemini.')} <button type="button" data-ai-retry>Retry</button></span>`;
          placeholder.addEventListener('click', (event) => {
            if (event.target && event.target.matches('[data-ai-retry]')) {
              placeholder.remove();
              sendPrompt(prompt);
            }
          }, { once: true });
          showToast('Gemini request failed. Retry or check settings.', 'error');
        } finally {
          if (sendButton) {
            sendButton.disabled = !chatState.enabled;
          }
        }
      }

      if (composer && textarea && historyContainer) {
        composer.addEventListener('submit', (event) => {
          event.preventDefault();
          sendPrompt(textarea.value);
        });
      }

      quickPrompts.forEach((button) => {
        button.addEventListener('click', () => {
          if (!textarea) {
            return;
          }
          const preset = button.getAttribute('data-ai-prompt') || '';
          textarea.value = preset;
          textarea.focus();
        });
      });

      if (clearButton) {
        clearButton.addEventListener('click', async () => {
          if (!window.confirm('Clear the AI chat history?')) {
            return;
          }
          try {
            const response = await fetch('api/gemini.php?action=clear-history', {
              method: 'POST',
              headers: {
                'X-CSRF-Token': window.csrfToken || '',
              },
              credentials: 'same-origin',
            });
            if (!response.ok) {
              throw new Error('Unable to clear history.');
            }
            const payload = await response.json();
            if (!payload || !payload.success) {
              throw new Error(payload && payload.error ? payload.error : 'Unable to clear history.');
            }
            chatState.history = [];
            renderHistory();
            showToast('Chat history cleared.', 'success');
          } catch (error) {
            showToast(error.message || 'Failed to clear history.', 'error');
          }
        });
      }

      if (exportButton) {
        exportButton.addEventListener('click', async () => {
          try {
            const response = await fetch('api/gemini.php?action=export-pdf', {
              method: 'GET',
              headers: {
                'X-CSRF-Token': window.csrfToken || '',
              },
              credentials: 'same-origin',
            });
            if (!response.ok) {
              throw new Error('Unable to export chat.');
            }
            const blob = await response.blob();
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'ai-chat-transcript.pdf';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
            showToast('Chat exported as PDF.', 'success');
          } catch (error) {
            showToast(error.message || 'Failed to export chat.', 'error');
          }
        });
      }

      // Blog generator, image, and TTS logic
      const blogShell = document.querySelector('[data-blog-generator]');
      const blogForm = document.querySelector('[data-blog-form]');
      const blogStatus = document.querySelector('[data-blog-status]');
      const blogProgress = document.querySelector('[data-blog-progress]');
      const blogAutosave = document.querySelector('[data-blog-autosave-status]');
      const blogPreview = document.querySelector('[data-blog-preview]');
      const blogCover = document.querySelector('[data-blog-cover]');
      const blogTitleInput = document.querySelector('[data-blog-title]');
      const blogBriefInput = document.querySelector('[data-blog-brief]');
      const blogKeywordsInput = document.querySelector('[data-blog-keywords]');
      const blogToneInput = document.querySelector('[data-blog-tone]');
      const blogGenerateButton = document.querySelector('[data-blog-generate]');
      const blogSaveButton = document.querySelector('[data-blog-save]');
      const blogPublishButton = document.querySelector('[data-blog-publish]');
      const blogPreviewButton = document.querySelector('[data-blog-preview-scroll]');

      const blogState = {
        paragraphs: [],
        coverImage: '',
        coverImageAlt: '',
        title: '',
        brief: '',
        keywords: '',
        tone: '',
        draftId: null,
        publishedUrl: null,
        dirty: false,
        saving: false,
        generating: false,
        regenerating: false,
      };

      function updateBlogStatus(message) {
        if (blogStatus) {
          blogStatus.textContent = message;
        }
      }

      function updateBlogAutosave(message, tone = 'muted') {
        if (!blogAutosave) {
          return;
        }
        blogAutosave.textContent = message;
        blogAutosave.dataset.tone = tone;
      }

      function syncBlogStateFromInputs() {
        blogState.title = blogTitleInput ? blogTitleInput.value.trim() : '';
        blogState.brief = blogBriefInput ? blogBriefInput.value.trim() : '';
        blogState.keywords = blogKeywordsInput ? blogKeywordsInput.value.trim() : '';
        blogState.tone = blogToneInput ? blogToneInput.value.trim() : '';
      }

      function markBlogDirty() {
        blogState.dirty = true;
        blogState.publishedUrl = null;
        updateBlogAutosave('Unsaved changes', 'warning');
      }

      function renderBlogCover() {
        if (!blogCover) {
          return;
        }
        blogCover.innerHTML = '';
        if (!blogState.coverImage) {
          blogCover.hidden = true;
          return;
        }
        const figure = document.createElement('figure');
        const img = document.createElement('img');
        const url = blogState.coverImage.startsWith('/') ? blogState.coverImage : `/${blogState.coverImage}`;
        img.src = url;
        img.alt = blogState.coverImageAlt || 'AI generated cover';
        const caption = document.createElement('figcaption');
        caption.textContent = blogState.coverImageAlt || 'AI generated cover image';
        figure.appendChild(img);
        figure.appendChild(caption);
        blogCover.appendChild(figure);
        blogCover.hidden = false;
      }

      function renderBlogPreview() {
        if (!blogPreview) {
          return;
        }
        blogPreview.innerHTML = '';
        if (!blogState.paragraphs.length) {
          const placeholder = document.createElement('p');
          placeholder.className = 'ai-blog-preview__placeholder';
          placeholder.textContent = 'Generated blog content will appear here.';
          blogPreview.appendChild(placeholder);
          return;
        }

        blogState.paragraphs.forEach((paragraph, index) => {
          const block = document.createElement('article');
          block.className = 'ai-blog-preview__block';
          if (/^#{1,6}\s+/.test(paragraph)) {
            const heading = document.createElement('h3');
            heading.textContent = paragraph.replace(/^#{1,6}\s+/, '').trim();
            block.appendChild(heading);
          } else {
            const text = document.createElement('p');
            text.textContent = paragraph;
            block.appendChild(text);
          }
          const actions = document.createElement('div');
          actions.className = 'ai-blog-preview__block-actions';
          const regen = document.createElement('button');
          regen.type = 'button';
          regen.className = 'btn btn-ghost btn-sm';
          regen.textContent = 'Regenerate';
          regen.addEventListener('click', () => {
            regenerateParagraph(index, regen);
          });
          actions.appendChild(regen);
          block.appendChild(actions);
          blogPreview.appendChild(block);
        });
      }

      async function regenerateParagraph(index, triggerButton) {
        if (blogState.regenerating) {
          return;
        }
        const paragraph = blogState.paragraphs[index];
        if (!paragraph) {
          return;
        }
        syncBlogStateFromInputs();
        blogState.regenerating = true;
        if (triggerButton) {
          triggerButton.disabled = true;
        }
        updateBlogStatus('Regenerating paragraph…');
        try {
          const response = await fetch('api/gemini.php?action=blog-regenerate-paragraph', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': window.csrfToken || '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
              paragraph,
              context: blogState.paragraphs.join(' '),
              title: blogState.title,
              tone: blogState.tone,
            }),
          });
          if (!response.ok) {
            throw new Error('Unable to regenerate paragraph.');
          }
          const payload = await response.json();
          if (!payload || !payload.success || !payload.paragraph) {
            throw new Error(payload && payload.error ? payload.error : 'Gemini did not return a revision.');
          }
          blogState.paragraphs[index] = payload.paragraph.trim();
          renderBlogPreview();
          markBlogDirty();
          showToast('Paragraph refreshed.', 'success');
        } catch (error) {
          showToast(error.message || 'Unable to regenerate paragraph.', 'error');
        } finally {
          blogState.regenerating = false;
          if (triggerButton) {
            triggerButton.disabled = false;
          }
          updateBlogStatus('Ready');
        }
      }

      async function saveBlogDraft(manual = false) {
        if (!blogShell || blogState.saving) {
          return;
        }
        if (!blogState.dirty && !manual) {
          return;
        }
        syncBlogStateFromInputs();
        if ((!blogState.title || blogState.paragraphs.length === 0) && manual) {
          showToast('Add a title and generate content before saving.', 'warning');
          return;
        }
        if (!blogState.title || blogState.paragraphs.length === 0) {
          return;
        }
        blogState.saving = true;
        updateBlogAutosave(manual ? 'Saving draft…' : 'Auto-saving…', 'info');
        try {
          const response = await fetch('api/gemini.php?action=blog-autosave', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': window.csrfToken || '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
              title: blogState.title,
              brief: blogState.brief,
              keywords: blogState.keywords,
              tone: blogState.tone,
              paragraphs: blogState.paragraphs,
              coverImage: blogState.coverImage,
              coverImageAlt: blogState.coverImageAlt,
              draftId: blogState.draftId,
            }),
          });
          if (!response.ok) {
            throw new Error('Unable to save draft.');
          }
          const payload = await response.json();
          blogState.dirty = false;
          if (payload && payload.draftId) {
            blogState.draftId = payload.draftId;
          }
          if (payload && payload.savedAt) {
            const saved = new Date(payload.savedAt);
            updateBlogAutosave(`Draft saved ${saved.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' })}`, 'success');
          } else {
            updateBlogAutosave('Draft saved', 'success');
          }
          if (manual) {
            showToast('Draft saved successfully.', 'success');
          }
        } catch (error) {
          updateBlogAutosave('Failed to save draft', 'error');
          if (manual) {
            showToast(error.message || 'Failed to save draft.', 'error');
          }
        } finally {
          blogState.saving = false;
        }
      }

      async function publishBlog() {
        if (!blogShell || !blogPublishButton) {
          return;
        }
        syncBlogStateFromInputs();
        if (!blogState.title || blogState.paragraphs.length === 0) {
          showToast('Generate the blog content before publishing.', 'warning');
          return;
        }
        if (!blogState.draftId) {
          showToast('Save the draft at least once before publishing.', 'warning');
          return;
        }
        blogPublishButton.disabled = true;
        updateBlogStatus('Publishing…');
        if (blogProgress) {
          blogProgress.textContent = 'Publishing blog post…';
        }
        try {
          const response = await fetch('api/gemini.php?action=blog-publish', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': window.csrfToken || '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
              title: blogState.title,
              brief: blogState.brief,
              keywords: blogState.keywords,
              tone: blogState.tone,
              paragraphs: blogState.paragraphs,
              coverImage: blogState.coverImage,
              coverImageAlt: blogState.coverImageAlt,
              draftId: blogState.draftId,
            }),
          });
          if (!response.ok) {
            throw new Error('Unable to publish blog.');
          }
          const payload = await response.json();
          if (!payload || !payload.success) {
            throw new Error(payload && payload.error ? payload.error : 'Gemini could not publish the blog.');
          }
          blogState.draftId = payload.postId || blogState.draftId;
          blogState.publishedUrl = payload.url || null;
          blogState.dirty = false;
          updateBlogAutosave('Published just now', 'success');
          updateBlogStatus('Published successfully');
          if (blogState.publishedUrl) {
            showToast(`Blog published: ${blogState.publishedUrl}`, 'success');
          } else {
            showToast('Blog published to the site. Review it in Blog publishing.', 'success');
          }
          if (blogProgress) {
            blogProgress.textContent = '';
          }
        } catch (error) {
          showToast(error.message || 'Failed to publish blog.', 'error');
          updateBlogStatus('Publish failed');
        } finally {
          blogPublishButton.disabled = false;
          if (blogProgress && blogProgress.textContent === 'Publishing blog post…') {
            blogProgress.textContent = '';
          }
        }
      }

      function startBlogGeneration() {
        if (!blogShell || blogState.generating) {
          return;
        }
        syncBlogStateFromInputs();
        if (!blogState.title || !blogState.brief) {
          showToast('Add a title and brief before generating.', 'warning');
          return;
        }
        blogState.generating = true;
        blogState.paragraphs = [];
        renderBlogPreview();
        updateBlogStatus('Generating blog…');
        if (blogProgress) {
          blogProgress.textContent = 'Generating blog draft…';
        }
        if (blogGenerateButton) {
          blogGenerateButton.disabled = true;
        }

        (async () => {
          try {
            const response = await fetch('api/gemini.php?action=blog-generate', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.csrfToken || '',
              },
              credentials: 'same-origin',
              body: JSON.stringify({
                title: blogState.title,
                brief: blogState.brief,
                keywords: blogState.keywords,
                tone: blogState.tone,
              }),
            });
            if (!response.ok || !response.body) {
              throw new Error('Gemini could not stream the blog.');
            }
            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';
            const handleEvent = (eventName, payload) => {
              if (eventName === 'chunk' && payload.paragraph) {
                blogState.paragraphs.push(payload.paragraph);
                renderBlogPreview();
              }
              if (eventName === 'done') {
                if (Array.isArray(payload.paragraphs)) {
                  blogState.paragraphs = payload.paragraphs;
                  renderBlogPreview();
                }
                if (payload && payload.draftId) {
                  blogState.draftId = payload.draftId;
                }
                if (payload.image && payload.image.path) {
                  blogState.coverImage = payload.image.path;
                  blogState.coverImageAlt = payload.image.alt || '';
                  renderBlogCover();
                  showToast('AI illustration attached to the draft.', 'success');
                }
                blogState.dirty = true;
                updateBlogStatus('Draft ready');
                updateBlogAutosave('Draft updated · remember to save', 'info');
              }
              if (eventName === 'error') {
                const message = payload && payload.message ? payload.message : 'Gemini was unable to complete the blog.';
                showToast(message, 'error');
                updateBlogStatus('Generation failed');
              }
            };

            while (true) {
              const { value, done } = await reader.read();
              if (done) {
                break;
              }
              buffer += decoder.decode(value, { stream: true });
              let boundary;
              while ((boundary = buffer.indexOf('\n\n')) !== -1) {
                const rawEvent = buffer.slice(0, boundary).trim();
                buffer = buffer.slice(boundary + 2);
                if (!rawEvent) {
                  continue;
                }
                const lines = rawEvent.split('\n');
                let eventName = 'message';
                let dataString = '';
                lines.forEach((line) => {
                  if (line.startsWith('event:')) {
                    eventName = line.replace('event:', '').trim();
                  }
                  if (line.startsWith('data:')) {
                    dataString += line.replace('data:', '').trim();
                  }
                });
                let parsed = {};
                try {
                  parsed = dataString ? JSON.parse(dataString) : {};
                } catch (parseError) {
                  parsed = {};
                }
                handleEvent(eventName, parsed);
              }
            }
          } catch (error) {
            showToast(error.message || 'Unable to generate blog.', 'error');
            updateBlogStatus('Generation failed');
          } finally {
            blogState.generating = false;
            if (blogGenerateButton) {
              blogGenerateButton.disabled = false;
            }
            if (blogProgress) {
              blogProgress.textContent = '';
            }
          }
        })();
      }

      async function loadBlogDraft() {
        if (!blogShell) {
          return;
        }
        try {
          const response = await fetch('api/gemini.php?action=blog-load-draft', {
            method: 'GET',
            headers: {
              'X-CSRF-Token': window.csrfToken || '',
            },
            credentials: 'same-origin',
          });
          if (!response.ok) {
            throw new Error('Unable to load saved draft.');
          }
          const payload = await response.json();
          const draft = payload && payload.draft ? payload.draft : {};
          if (draft && Object.keys(draft).length > 0) {
            if (blogTitleInput) {
              blogTitleInput.value = draft.title || '';
            }
            if (blogBriefInput) {
              blogBriefInput.value = draft.brief || '';
            }
            if (blogKeywordsInput) {
              blogKeywordsInput.value = draft.keywords || '';
            }
            if (blogToneInput) {
              blogToneInput.value = draft.tone || '';
            }
            blogState.title = draft.title || '';
            blogState.brief = draft.brief || '';
            blogState.keywords = draft.keywords || '';
            blogState.tone = draft.tone || '';
            blogState.paragraphs = Array.isArray(draft.paragraphs) ? draft.paragraphs : [];
            blogState.coverImage = draft.coverImage || '';
            blogState.coverImageAlt = draft.coverImageAlt || '';
            blogState.draftId = draft.draftId || draft.postId || null;
            blogState.dirty = false;
            renderBlogPreview();
            renderBlogCover();
            if (draft.updatedAt) {
              const loaded = new Date(draft.updatedAt);
              updateBlogAutosave(`Draft loaded · saved ${loaded.toLocaleString('en-IN', { hour: '2-digit', minute: '2-digit' })}`, 'success');
            } else {
              updateBlogAutosave('Draft loaded', 'info');
            }
            updateBlogStatus('Draft loaded');
          } else {
            updateBlogAutosave('No saved draft yet', 'muted');
            updateBlogStatus('Idle');
          }
        } catch (error) {
          updateBlogAutosave('Unable to load draft', 'error');
        }
      }

      if (blogShell) {
        updateBlogStatus('Idle');
        loadBlogDraft();
        const inputs = [blogTitleInput, blogBriefInput, blogKeywordsInput, blogToneInput];
        inputs.forEach((input) => {
          if (!input) {
            return;
          }
          input.addEventListener('input', () => {
            markBlogDirty();
          });
        });
        if (blogGenerateButton) {
          blogGenerateButton.addEventListener('click', startBlogGeneration);
        }
        if (blogSaveButton) {
          blogSaveButton.addEventListener('click', () => {
            saveBlogDraft(true);
          });
        }
        if (blogPublishButton) {
          blogPublishButton.addEventListener('click', publishBlog);
        }
        if (blogPreviewButton && blogPreview) {
          blogPreviewButton.addEventListener('click', () => {
            blogPreview.scrollIntoView({ behavior: 'smooth' });
          });
        }

        window.setInterval(() => {
          saveBlogDraft(false);
        }, 10000);
      }

      const imageShell = document.querySelector('[data-image-generator]');
      const imageStatus = document.querySelector('[data-image-status]');
      const imagePrompt = document.querySelector('[data-image-prompt]');
      const imageAutofill = document.querySelector('[data-image-autofill]');
      const imageGenerate = document.querySelector('[data-image-generate]');
      const imagePreview = document.querySelector('[data-image-preview]');
      const imageOutput = document.querySelector('[data-image-output]');
      const imageCaption = document.querySelector('[data-image-caption]');
      const imageDownload = document.querySelector('[data-image-download]');
      const imageAttach = document.querySelector('[data-image-attach]');

      function updateImageStatus(message) {
        if (imageStatus) {
          imageStatus.textContent = message;
        }
      }

      async function generateImage() {
        if (!imageShell || !imagePrompt) {
          return;
        }
        const prompt = imagePrompt.value.trim();
        if (!prompt) {
          showToast('Write a short prompt for the illustration.', 'warning');
          return;
        }
        if (imageGenerate) {
          imageGenerate.disabled = true;
        }
        updateImageStatus('Generating image…');
        try {
          const response = await fetch('api/gemini.php?action=image-generate', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': window.csrfToken || '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ prompt, draftId: blogState.draftId }),
          });
          if (!response.ok) {
            throw new Error('Unable to generate image.');
          }
          const payload = await response.json();
          if (!payload || !payload.success || !payload.image) {
            throw new Error(payload && payload.error ? payload.error : 'Gemini image output missing.');
          }
          const path = payload.image.path;
          const url = path.startsWith('/') ? path : `/${path}`;
          if (imageOutput) {
            imageOutput.src = url;
          }
          if (imageDownload) {
            imageDownload.href = url;
          }
          if (imageCaption) {
            imageCaption.textContent = `Generated via Gemini image model · ${new Date().toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' })}`;
          }
          if (imagePreview) {
            imagePreview.hidden = false;
          }
          blogState.coverImage = path;
          blogState.coverImageAlt = blogState.title ? `AI illustration for ${blogState.title}` : 'AI generated illustration';
          renderBlogCover();
          markBlogDirty();
          updateImageStatus('Image ready and attached');
          showToast('Image attached to the draft.', 'success');
        } catch (error) {
          updateImageStatus('Image generation failed');
          showToast(error.message || 'Unable to generate image.', 'error');
        } finally {
          if (imageGenerate) {
            imageGenerate.disabled = false;
          }
        }
      }

      if (imageShell) {
        updateImageStatus('Idle');
        if (imageGenerate) {
          imageGenerate.addEventListener('click', generateImage);
        }
        if (imageAutofill) {
          imageAutofill.addEventListener('click', () => {
            syncBlogStateFromInputs();
            const segments = [];
            if (blogState.title) {
              segments.push(`Hero illustration for "${blogState.title}"`);
            }
            if (blogState.keywords) {
              segments.push(`Keywords: ${blogState.keywords}`);
            }
            if (blogState.brief) {
              segments.push(blogState.brief);
            }
            if (!segments.length) {
              showToast('Add blog details to build an image prompt.', 'info');
              return;
            }
            if (imagePrompt) {
              imagePrompt.value = `${segments.join('. ')}.`;
              imagePrompt.focus();
            }
            updateImageStatus('Prompt filled using blog context');
          });
        }
        if (imageAttach && imageOutput) {
          imageAttach.addEventListener('click', () => {
            if (!imageOutput.src) {
              showToast('Generate an image first.', 'warning');
              return;
            }
            const currentPath = imageOutput.src.replace(window.location.origin, '');
            blogState.coverImage = currentPath.startsWith('/') ? currentPath.substring(1) : currentPath;
            blogState.coverImageAlt = blogState.title ? `AI illustration for ${blogState.title}` : 'AI generated illustration';
            renderBlogCover();
            markBlogDirty();
            showToast('Image attached to draft.', 'success');
          });
        }
        if (imageDownload) {
          imageDownload.addEventListener('click', () => {
            if (!imageDownload.href || imageDownload.href === '#') {
              showToast('Generate an image before downloading.', 'info');
            }
          });
        }
      }

      const ttsShell = document.querySelector('[data-tts-generator]');
      const ttsStatus = document.querySelector('[data-tts-status]');
      const ttsText = document.querySelector('[data-tts-text]');
      const ttsFormat = document.querySelector('[data-tts-format]');
      const ttsGenerate = document.querySelector('[data-tts-generate]');
      const ttsOutput = document.querySelector('[data-tts-output]');
      const ttsAudio = document.querySelector('[data-tts-audio]');
      const ttsDownload = document.querySelector('[data-tts-download]');

      function updateTtsStatus(message) {
        if (ttsStatus) {
          ttsStatus.textContent = message;
        }
      }

      if (ttsShell && ttsGenerate) {
        updateTtsStatus('Idle');
        ttsGenerate.addEventListener('click', async () => {
          if (!ttsText) {
            return;
          }
          const text = ttsText.value.trim();
          if (!text) {
            showToast('Enter the text that should be voiced.', 'warning');
            return;
          }
          const format = ttsFormat ? ttsFormat.value : 'mp3';
          ttsGenerate.disabled = true;
          updateTtsStatus('Generating audio…');
          try {
            const response = await fetch('api/gemini.php?action=tts-generate', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.csrfToken || '',
              },
              credentials: 'same-origin',
              body: JSON.stringify({ text, format }),
            });
            if (!response.ok) {
              throw new Error('Unable to generate audio.');
            }
            const payload = await response.json();
            if (!payload || !payload.success || !payload.audio) {
              throw new Error(payload && payload.error ? payload.error : 'Gemini TTS failed.');
            }
            const path = payload.audio.path;
            const url = path.startsWith('/') ? path : `/${path}`;
            if (ttsAudio) {
              ttsAudio.src = url;
              ttsAudio.load();
            }
            if (ttsDownload) {
              ttsDownload.href = url;
            }
            if (ttsOutput) {
              ttsOutput.hidden = false;
            }
            updateTtsStatus('Audio ready');
            showToast('Audio file generated.', 'success');
          } catch (error) {
            updateTtsStatus('Audio generation failed');
            showToast(error.message || 'Unable to generate audio.', 'error');
          } finally {
            ttsGenerate.disabled = false;
          }
        });
      }

      const sandboxShell = document.querySelector('[data-sandbox]');
      if (sandboxShell) {
        const sandboxTabs = sandboxShell.querySelectorAll('[data-sandbox-tab]');
        const sandboxPanels = sandboxShell.querySelectorAll('[data-sandbox-panel]');
        const sandboxTextForm = sandboxShell.querySelector('[data-sandbox-text-form]');
        const sandboxTextInput = sandboxShell.querySelector('[data-sandbox-text-input]');
        const sandboxTextOutput = sandboxShell.querySelector('[data-sandbox-text-output]');
        const sandboxImageForm = sandboxShell.querySelector('[data-sandbox-image-form]');
        const sandboxImageInput = sandboxShell.querySelector('[data-sandbox-image-input]');
        const sandboxImageOutput = sandboxShell.querySelector('[data-sandbox-image-output]');
        const sandboxImagePreview = sandboxShell.querySelector('[data-sandbox-image-preview]');
        const sandboxImageCaption = sandboxShell.querySelector('[data-sandbox-image-caption]');
        const sandboxImageDownload = sandboxShell.querySelector('[data-sandbox-image-download]');
        const sandboxTtsForm = sandboxShell.querySelector('[data-sandbox-tts-form]');
        const sandboxTtsInput = sandboxShell.querySelector('[data-sandbox-tts-input]');
        const sandboxTtsFormat = sandboxShell.querySelector('[data-sandbox-tts-format]');
        const sandboxTtsOutput = sandboxShell.querySelector('[data-sandbox-tts-output]');
        const sandboxTtsAudio = sandboxShell.querySelector('[data-sandbox-tts-audio]');
        const sandboxTtsDownload = sandboxShell.querySelector('[data-sandbox-tts-download]');
        let sandboxTextTimer = null;

        function activateSandboxTab(name) {
          sandboxTabs.forEach((tab) => {
            const isActive = tab.getAttribute('data-sandbox-tab') === name;
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
          });
          sandboxPanels.forEach((panel) => {
            const isActive = panel.getAttribute('data-sandbox-panel') === name;
            panel.hidden = !isActive;
          });
        }

        function updateSandboxStatus(kind, message) {
          const status = sandboxShell.querySelector(`[data-sandbox-${kind}-status]`);
          if (status) {
            status.textContent = message;
          }
        }

        function streamSandboxText(target, text) {
          if (!target) {
            return;
          }
          if (sandboxTextTimer !== null) {
            window.clearTimeout(sandboxTextTimer);
            sandboxTextTimer = null;
          }
          target.innerHTML = '';
          const article = document.createElement('article');
          article.className = 'ai-sandbox__text';
          target.appendChild(article);
          const tokens = text.split(/(\s+)/);
          let index = 0;
          function step() {
            if (index >= tokens.length) {
              sandboxTextTimer = null;
              return;
            }
            article.innerHTML += escapeHtml(tokens[index]).replace(/\n/g, '<br />');
            target.scrollTop = target.scrollHeight;
            index += 1;
            sandboxTextTimer = window.setTimeout(step, 35);
          }
          step();
        }

        sandboxTabs.forEach((tab) => {
          tab.addEventListener('click', () => {
            const target = tab.getAttribute('data-sandbox-tab');
            if (target) {
              activateSandboxTab(target);
            }
          });
        });

        if (sandboxTextForm && sandboxTextInput) {
          sandboxTextForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const prompt = sandboxTextInput.value.trim();
            if (!prompt) {
              showToast('Enter a prompt for Gemini.', 'info');
              return;
            }
            const runButton = sandboxTextForm.querySelector('[data-sandbox-text-run]');
            if (runButton) {
              runButton.disabled = true;
            }
            updateSandboxStatus('text', 'Generating…');
            try {
              const response = await fetch('api/gemini.php?action=sandbox-text', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-Token': window.csrfToken || '',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ prompt }),
              });
              if (!response.ok) {
                throw new Error('Unable to reach Gemini text sandbox.');
              }
              const payload = await response.json();
              if (!payload || !payload.success || !payload.text) {
                throw new Error(payload && payload.error ? payload.error : 'Gemini returned no text.');
              }
              updateSandboxStatus('text', 'Streaming response…');
              streamSandboxText(sandboxTextOutput, payload.text);
            } catch (error) {
              updateSandboxStatus('text', 'Text generation failed');
              showToast(error.message || 'Gemini text sandbox failed.', 'error');
            } finally {
              if (runButton) {
                runButton.disabled = false;
              }
            }
          });
        }

        if (sandboxImageForm && sandboxImageInput) {
          sandboxImageForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const prompt = sandboxImageInput.value.trim();
            if (!prompt) {
              showToast('Add a description for the visual.', 'info');
              return;
            }
            const runButton = sandboxImageForm.querySelector('[data-sandbox-image-run]');
            if (runButton) {
              runButton.disabled = true;
            }
            updateSandboxStatus('image', 'Generating image…');
            try {
              const response = await fetch('api/gemini.php?action=sandbox-image', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-Token': window.csrfToken || '',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ prompt }),
              });
              if (!response.ok) {
                throw new Error('Unable to reach Gemini image sandbox.');
              }
              const payload = await response.json();
              if (!payload || !payload.success || !payload.image || !payload.image.path) {
                throw new Error(payload && payload.error ? payload.error : 'Gemini did not return an image.');
              }
              const path = payload.image.path;
              const url = path.startsWith('/') ? path : `/${path}`;
              if (sandboxImagePreview) {
                sandboxImagePreview.src = url;
              }
              if (sandboxImageCaption) {
                sandboxImageCaption.textContent = `Generated ${new Date().toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' })}`;
              }
              if (sandboxImageDownload) {
                sandboxImageDownload.href = url;
              }
              if (sandboxImageOutput) {
                sandboxImageOutput.hidden = false;
              }
              updateSandboxStatus('image', 'Image ready');
              showToast('Sandbox image ready.', 'success');
            } catch (error) {
              updateSandboxStatus('image', 'Image generation failed');
              showToast(error.message || 'Gemini image sandbox failed.', 'error');
            } finally {
              if (runButton) {
                runButton.disabled = false;
              }
            }
          });
        }

        if (sandboxTtsForm && sandboxTtsInput) {
          sandboxTtsForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const text = sandboxTtsInput.value.trim();
            if (!text) {
              showToast('Provide text for the TTS sandbox.', 'info');
              return;
            }
            const format = sandboxTtsFormat ? sandboxTtsFormat.value : 'mp3';
            const runButton = sandboxTtsForm.querySelector('[data-sandbox-tts-run]');
            if (runButton) {
              runButton.disabled = true;
            }
            updateSandboxStatus('tts', 'Generating audio…');
            try {
              const response = await fetch('api/gemini.php?action=sandbox-tts', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-Token': window.csrfToken || '',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ text, format }),
              });
              if (!response.ok) {
                throw new Error('Unable to reach Gemini TTS sandbox.');
              }
              const payload = await response.json();
              if (!payload || !payload.success || !payload.audio || !payload.audio.path) {
                throw new Error(payload && payload.error ? payload.error : 'Gemini did not return audio.');
              }
              const path = payload.audio.path;
              const url = path.startsWith('/') ? path : `/${path}`;
              if (sandboxTtsAudio) {
                sandboxTtsAudio.src = url;
                sandboxTtsAudio.load();
              }
              if (sandboxTtsDownload) {
                sandboxTtsDownload.href = url;
              }
              if (sandboxTtsOutput) {
                sandboxTtsOutput.hidden = false;
              }
              updateSandboxStatus('tts', 'Audio ready');
              showToast('Sandbox audio ready.', 'success');
            } catch (error) {
              updateSandboxStatus('tts', 'Audio generation failed');
              showToast(error.message || 'Gemini TTS sandbox failed.', 'error');
            } finally {
              if (runButton) {
                runButton.disabled = false;
              }
            }
          });
        }
      }

      const schedulerForm = document.querySelector('[data-scheduler-form]');
      const schedulerTopic = schedulerForm ? schedulerForm.querySelector('[data-scheduler-topic]') : null;
      const schedulerFrequency = schedulerForm ? schedulerForm.querySelector('[data-scheduler-frequency]') : null;
      const schedulerEnabled = schedulerForm ? schedulerForm.querySelector('[data-scheduler-enabled]') : null;
      const schedulerSaveButton = schedulerForm ? schedulerForm.querySelector('[data-scheduler-save]') : null;
      const schedulerRunButton = schedulerForm ? schedulerForm.querySelector('[data-scheduler-run]') : null;
      const schedulerStatus = document.querySelector('[data-scheduler-status]');
      const schedulerNext = document.querySelector('[data-scheduler-next]');
      const schedulerLogs = document.querySelector('[data-scheduler-logs]');
      const schedulerState = { autoRunning: false, lastSettings: null };

      function setSchedulerStatus(message) {
        if (schedulerStatus) {
          schedulerStatus.textContent = message;
        }
      }

      function formatSchedulerDate(value) {
        if (!value) {
          return '—';
        }
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
          return '—';
        }
        return date.toLocaleString('en-IN', {
          day: '2-digit',
          month: 'short',
          hour: '2-digit',
          minute: '2-digit',
        });
      }

      function renderSchedulerLogs(logs) {
        if (!schedulerLogs) {
          return;
        }
        const list = schedulerLogs.querySelector('ul');
        if (!list) {
          return;
        }
        list.innerHTML = '';
        if (!Array.isArray(logs) || logs.length === 0) {
          const empty = document.createElement('li');
          empty.className = 'automation-scheduler__empty';
          empty.textContent = 'No automated drafts generated yet.';
          list.appendChild(empty);
          return;
        }
        logs.forEach((entry) => {
          const item = document.createElement('li');
          item.className = 'automation-scheduler__item';
          const title = document.createElement('h4');
          title.textContent = entry.title || entry.topic || 'Automated draft';
          item.appendChild(title);
          const meta = document.createElement('p');
          meta.className = 'automation-scheduler__item-meta';
          const when = entry.created_at ? formatSchedulerDate(entry.created_at) : '—';
          meta.textContent = `${entry.topic || 'Scheduled topic'} · ${when}`;
          item.appendChild(meta);
          if (entry.summary) {
            const summary = document.createElement('p');
            summary.className = 'automation-scheduler__item-summary';
            summary.textContent = entry.summary;
            item.appendChild(summary);
          }
          const assets = document.createElement('div');
          assets.className = 'automation-scheduler__item-assets';
          if (entry.draft) {
            const draftLink = document.createElement('a');
            const draftUrl = entry.draft.startsWith('/') ? entry.draft : `/${entry.draft}`;
            draftLink.href = draftUrl;
            draftLink.className = 'btn btn-ghost btn-sm';
            draftLink.textContent = 'Download draft';
            draftLink.download = '';
            assets.appendChild(draftLink);
          }
          if (Array.isArray(entry.images)) {
            entry.images.forEach((image, index) => {
              if (!image || !image.path) {
                return;
              }
              const url = image.path.startsWith('/') ? image.path : `/${image.path}`;
              const link = document.createElement('a');
              link.href = url;
              link.className = 'btn btn-ghost btn-sm';
              link.textContent = `Image ${index + 1}`;
              link.download = '';
              assets.appendChild(link);
            });
          }
          if (entry.audio && entry.audio.path) {
            const audioUrl = entry.audio.path.startsWith('/') ? entry.audio.path : `/${entry.audio.path}`;
            const audioLink = document.createElement('a');
            audioLink.href = audioUrl;
            audioLink.className = 'btn btn-ghost btn-sm';
            audioLink.textContent = 'Audio';
            audioLink.download = '';
            assets.appendChild(audioLink);
          }
          if (assets.childNodes.length > 0) {
            item.appendChild(assets);
          }
          list.appendChild(item);
        });
      }

      function maybeTriggerAutoRun(settings) {
        if (!settings || !settings.enabled) {
          return;
        }
        if (!settings.next_run) {
          return;
        }
        const next = new Date(settings.next_run);
        if (Number.isNaN(next.getTime())) {
          return;
        }
        const now = new Date();
        if (next <= now && !schedulerState.autoRunning) {
          schedulerState.autoRunning = true;
          runScheduler(true).finally(() => {
            schedulerState.autoRunning = false;
          });
        }
      }

      async function loadScheduler() {
        if (!schedulerForm) {
          return;
        }
        try {
          const response = await fetch('api/gemini.php?action=scheduler-status', {
            method: 'GET',
            headers: {
              'X-CSRF-Token': window.csrfToken || '',
            },
            credentials: 'same-origin',
          });
          if (!response.ok) {
            throw new Error('Unable to load scheduler status.');
          }
          const payload = await response.json();
          if (!payload || !payload.success) {
            throw new Error(payload && payload.error ? payload.error : 'Scheduler status unavailable.');
          }
          const settings = payload.settings || {};
          schedulerState.lastSettings = settings;
          if (schedulerTopic) {
            schedulerTopic.value = settings.topic || '';
          }
          if (schedulerFrequency) {
            schedulerFrequency.value = settings.frequency || 'weekly';
          }
          if (schedulerEnabled) {
            schedulerEnabled.checked = !!settings.enabled;
          }
          if (schedulerNext) {
            const label = settings.enabled && settings.next_run
              ? `Next run: ${formatSchedulerDate(settings.next_run)}`
              : 'Next run: paused';
            schedulerNext.textContent = label;
          }
          renderSchedulerLogs(payload.logs || []);
          setSchedulerStatus('Idle');
          maybeTriggerAutoRun(settings);
        } catch (error) {
          setSchedulerStatus('Status unavailable');
          showToast(error.message || 'Unable to load scheduler status.', 'error');
        }
      }

      async function saveSchedulerSettings() {
        if (!schedulerForm) {
          return;
        }
        const topic = schedulerTopic ? schedulerTopic.value.trim() : '';
        const frequency = schedulerFrequency ? schedulerFrequency.value : 'weekly';
        const enabled = schedulerEnabled ? schedulerEnabled.checked : false;
        if (!topic && enabled) {
          showToast('Add a topic before enabling the scheduler.', 'warning');
          return;
        }
        if (schedulerSaveButton) {
          schedulerSaveButton.disabled = true;
        }
        if (schedulerRunButton) {
          schedulerRunButton.disabled = true;
        }
        setSchedulerStatus('Saving…');
        try {
          const response = await fetch('api/gemini.php?action=scheduler-save', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': window.csrfToken || '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ topic, frequency, enabled }),
          });
          if (!response.ok) {
            throw new Error('Unable to save scheduler settings.');
          }
          const payload = await response.json();
          if (!payload || !payload.success) {
            throw new Error(payload && payload.error ? payload.error : 'Scheduler save failed.');
          }
          showToast('Scheduler updated.', 'success');
          await loadScheduler();
        } catch (error) {
          setSchedulerStatus('Save failed');
          showToast(error.message || 'Failed to save scheduler settings.', 'error');
        } finally {
          if (schedulerSaveButton) {
            schedulerSaveButton.disabled = false;
          }
          if (schedulerRunButton) {
            schedulerRunButton.disabled = false;
          }
        }
      }

      async function runScheduler(auto = false) {
        if (!schedulerForm) {
          return;
        }
        if (schedulerRunButton && !auto) {
          schedulerRunButton.disabled = true;
        }
        if (schedulerSaveButton) {
          schedulerSaveButton.disabled = true;
        }
        setSchedulerStatus(auto ? 'Auto generating…' : 'Running…');
        try {
          const response = await fetch('api/gemini.php?action=scheduler-run', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': window.csrfToken || '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({}),
          });
          if (!response.ok) {
            throw new Error('Automation run failed.');
          }
          const payload = await response.json();
          if (!payload || !payload.success) {
            throw new Error(payload && payload.error ? payload.error : 'Scheduler did not finish.');
          }
          showToast(auto ? 'Automation executed automatically.' : 'Automation draft is ready.', 'success');
          await loadScheduler();
          await loadUsage();
        } catch (error) {
          setSchedulerStatus('Run failed');
          if (!auto) {
            showToast(error.message || 'Automation run failed.', 'error');
          }
        } finally {
          if (schedulerRunButton) {
            schedulerRunButton.disabled = false;
          }
          if (schedulerSaveButton) {
            schedulerSaveButton.disabled = false;
          }
        }
      }

      if (schedulerSaveButton) {
        schedulerSaveButton.addEventListener('click', (event) => {
          event.preventDefault();
          saveSchedulerSettings();
        });
      }

      if (schedulerRunButton) {
        schedulerRunButton.addEventListener('click', (event) => {
          event.preventDefault();
          runScheduler(false);
        });
      }

      if (schedulerForm) {
        loadScheduler();
        window.setInterval(loadScheduler, 60000);
      }

      const usageShell = document.querySelector('[data-usage-shell]');
      const usageDailyTokens = usageShell ? usageShell.querySelector('[data-usage-daily-tokens]') : null;
      const usageDailyCost = usageShell ? usageShell.querySelector('[data-usage-daily-cost]') : null;
      const usageMonthlyTokens = usageShell ? usageShell.querySelector('[data-usage-monthly-tokens]') : null;
      const usageMonthlyCost = usageShell ? usageShell.querySelector('[data-usage-monthly-cost]') : null;
      const usageAggregateTokens = usageShell ? usageShell.querySelector('[data-usage-aggregate-tokens]') : null;
      const usageAggregateCost = usageShell ? usageShell.querySelector('[data-usage-aggregate-cost]') : null;
      const usagePricingList = usageShell ? usageShell.querySelector('[data-usage-pricing]') : null;
      const errorLogList = usageShell ? usageShell.querySelector('[data-error-log]') : null;
      const errorRetryButton = usageShell ? usageShell.querySelector('[data-error-retry]') : null;
      const errorCopyButton = usageShell ? usageShell.querySelector('[data-error-copy]') : null;
      const usageState = { lastError: null };
      const numberFormat = typeof Intl !== 'undefined' ? new Intl.NumberFormat('en-IN') : null;
      const currencyFormat = typeof Intl !== 'undefined' ? new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', minimumFractionDigits: 2 }) : null;

      function formatTokens(value) {
        const total = Math.max(0, Math.round(value));
        return numberFormat ? numberFormat.format(total) : String(total);
      }

      function formatCurrency(value) {
        const amount = Number.isFinite(value) ? value : 0;
        return currencyFormat ? currencyFormat.format(amount) : `₹${amount.toFixed(2)}`;
      }

      function renderUsageErrors(errors) {
        usageState.lastError = null;
        if (!errorLogList) {
          return;
        }
        errorLogList.innerHTML = '';
        if (!Array.isArray(errors) || errors.length === 0) {
          const empty = document.createElement('li');
          empty.className = 'usage-logs__empty';
          empty.textContent = 'No errors logged.';
          errorLogList.appendChild(empty);
          return;
        }
        errors.forEach((entry, index) => {
          const item = document.createElement('li');
          item.className = 'usage-logs__error';
          const title = document.createElement('strong');
          title.textContent = entry.type || 'API failure';
          item.appendChild(title);
          const message = document.createElement('p');
          message.textContent = entry.message || 'Gemini error encountered.';
          item.appendChild(message);
          if (entry.created_at) {
            const time = document.createElement('span');
            time.className = 'usage-logs__error-time';
            const date = new Date(entry.created_at);
            time.textContent = Number.isNaN(date.getTime()) ? '' : date.toLocaleString('en-IN', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
            item.appendChild(time);
          }
          errorLogList.appendChild(item);
          if (index === 0) {
            usageState.lastError = entry;
          }
        });
      }

      async function loadUsage() {
        if (!usageShell) {
          return;
        }
        try {
          const response = await fetch('api/gemini.php?action=usage-summary', {
            method: 'GET',
            headers: {
              'X-CSRF-Token': window.csrfToken || '',
            },
            credentials: 'same-origin',
          });
          if (!response.ok) {
            throw new Error('Unable to load usage summary.');
          }
          const payload = await response.json();
          if (!payload || !payload.success) {
            throw new Error(payload && payload.error ? payload.error : 'Usage summary unavailable.');
          }
          const usage = payload.usage || {};
          const dailyTokens = (usage.daily ? (usage.daily.input_tokens || 0) + (usage.daily.output_tokens || 0) : 0);
          const dailyCost = usage.daily ? usage.daily.cost || 0 : 0;
          const monthlyTokens = (usage.monthly ? (usage.monthly.input_tokens || 0) + (usage.monthly.output_tokens || 0) : 0);
          const monthlyCost = usage.monthly ? usage.monthly.cost || 0 : 0;
          const aggregateTokens = (usage.aggregate ? (usage.aggregate.input_tokens || 0) + (usage.aggregate.output_tokens || 0) : 0);
          const aggregateCost = usage.aggregate ? usage.aggregate.cost || 0 : 0;
          if (usageDailyTokens) {
            usageDailyTokens.textContent = `${formatTokens(dailyTokens)} tokens`;
          }
          if (usageDailyCost) {
            usageDailyCost.textContent = formatCurrency(dailyCost);
          }
          if (usageMonthlyTokens) {
            usageMonthlyTokens.textContent = `${formatTokens(monthlyTokens)} tokens`;
          }
          if (usageMonthlyCost) {
            usageMonthlyCost.textContent = formatCurrency(monthlyCost);
          }
          if (usageAggregateTokens) {
            usageAggregateTokens.textContent = `${formatTokens(aggregateTokens)} tokens`;
          }
          if (usageAggregateCost) {
            usageAggregateCost.textContent = formatCurrency(aggregateCost);
          }
          if (usagePricingList) {
            usagePricingList.innerHTML = '';
            const pricing = payload.usage ? payload.usage.pricing || {} : {};
            if (pricing.text) {
              const item = document.createElement('li');
              item.textContent = `Text input ₹${Number(pricing.text.input_per_million || 0).toFixed(2)} / 1M tokens · output ₹${Number(pricing.text.output_per_million || 0).toFixed(2)} / 1M tokens`;
              usagePricingList.appendChild(item);
            }
            if (pricing.image) {
              const item = document.createElement('li');
              item.textContent = `Image generation ₹${Number(pricing.image.per_call || 0).toFixed(2)} per call`;
              usagePricingList.appendChild(item);
            }
            if (pricing.tts) {
              const item = document.createElement('li');
              item.textContent = `TTS ₹${Number(pricing.tts.per_thousand_chars || 0).toFixed(2)} per 1K characters`;
              usagePricingList.appendChild(item);
            }
          }
          renderUsageErrors(payload.errors || []);
        } catch (error) {
          showToast(error.message || 'Unable to load usage summary.', 'error');
        }
      }

      if (errorRetryButton) {
        errorRetryButton.addEventListener('click', async () => {
          if (!usageState.lastError) {
            showToast('No error available to retry.', 'info');
            return;
          }
          errorRetryButton.disabled = true;
          try {
            const response = await fetch('api/gemini.php?action=error-retry', {
              method: 'POST',
              headers: {
                'X-CSRF-Token': window.csrfToken || '',
              },
              credentials: 'same-origin',
            });
            if (!response.ok) {
              throw new Error('Retry failed.');
            }
            const payload = await response.json();
            if (!payload || !payload.success) {
              throw new Error(payload && payload.error ? payload.error : 'Retry failed.');
            }
            showToast('Last action retried successfully.', 'success');
            await loadUsage();
            if (payload.payload && payload.payload.type === 'scheduler-run') {
              await loadScheduler();
            }
          } catch (error) {
            showToast(error.message || 'Unable to retry last action.', 'error');
          } finally {
            errorRetryButton.disabled = false;
          }
        });
      }

      if (errorCopyButton) {
        errorCopyButton.addEventListener('click', () => {
          if (!usageState.lastError) {
            showToast('No error available to copy.', 'info');
            return;
          }
          const text = JSON.stringify(usageState.lastError, null, 2);
          const clipboard = navigator.clipboard;
          if (clipboard && clipboard.writeText) {
            clipboard.writeText(text).then(() => {
              showToast('Error details copied.', 'success');
            }).catch(() => {
              showToast('Unable to copy error details.', 'error');
            });
            return;
          }
          const temp = document.createElement('textarea');
          temp.value = text;
          temp.setAttribute('readonly', 'readonly');
          temp.style.position = 'absolute';
          temp.style.left = '-9999px';
          document.body.appendChild(temp);
          temp.select();
          try {
            document.execCommand('copy');
            showToast('Error details copied.', 'success');
          } catch (error) {
            showToast('Unable to copy error details.', 'error');
          }
          document.body.removeChild(temp);
        });
      }

      if (usageShell) {
        loadUsage();
        window.setInterval(loadUsage, 45000);
      }

      renderHistory();
      setChatEnabled(!!(window.aiSettings && window.aiSettings.enabled));
    })();
  </script>
</body>
</html>
