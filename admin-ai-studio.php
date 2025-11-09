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

      renderHistory();
      setChatEnabled(!!(window.aiSettings && window.aiSettings.enabled));
    })();
  </script>
</body>
</html>
