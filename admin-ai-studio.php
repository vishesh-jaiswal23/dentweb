<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/ai_studio_settings.php';

require_admin();
$admin = current_user();
$settings = ai_studio_settings()->getSettings();

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>AI Studio | Admin</title>
  <link rel="icon" href="images/favicon.ico" />
  <link rel="stylesheet" href="style.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
  <link rel="stylesheet" href="print-chat.css" media="print" />
</head>
<body class="admin-page">
  <main class="admin-layout">
    <header class="admin-header">
      <h1>AI Studio</h1>
      <p>Signed in as <strong><?= htmlspecialchars($admin['full_name'] ?? 'Administrator', ENT_QUOTES) ?></strong></p>
    </header>

    <div class="admin-content">
      <div class="ai-studio-grid">
        <section class="ai-settings-card">
          <h2><i class="fa-solid fa-cogs"></i> AI Settings</h2>
          <form id="ai-settings-form">
            <div class="form-group">
              <label for="ai-enabled">AI Enabled</label>
              <label class="switch">
                <input type="checkbox" id="ai-enabled" name="enabled" <?= $settings['enabled'] ? 'checked' : '' ?>>
                <span class="slider round"></span>
              </label>
            </div>

            <div class="form-group">
              <label for="api-key">Gemini API Key</label>
              <div class="input-group">
                <input type="password" id="api-key" name="api_key" value="<?= htmlspecialchars($settings['api_key'] ?? '', ENT_QUOTES) ?>" class="form-control">
                <button type="button" class="btn btn-icon" id="toggle-api-key"><i class="fa-solid fa-eye"></i></button>
              </div>
            </div>

            <div class="form-group">
              <label for="text-model">Text Model Code</label>
              <input type="text" id="text-model" name="text_model" value="<?= htmlspecialchars($settings['text_model'] ?? '', ENT_QUOTES) ?>" class="form-control">
            </div>

            <div class="form-group">
              <label for="image-model">Image Model Code</label>
              <input type="text" id="image-model" name="image_model" value="<?= htmlspecialchars($settings['image_model'] ?? '', ENT_QUOTES) ?>" class="form-control">
            </div>

            <div class="form-group">
              <label for="tts-model">TTS Model Code</label>
              <input type="text" id="tts-model" name="tts_model" value="<?= htmlspecialchars($settings['tts_model'] ?? '', ENT_QUOTES) ?>" class="form-control">
            </div>

            <div class="form-group">
              <label for="temperature">Temperature: <span id="temperature-value"><?= htmlspecialchars((string)($settings['temperature'] ?? '0.7'), ENT_QUOTES) ?></span></label>
              <input type="range" id="temperature" name="temperature" min="0" max="1" step="0.1" value="<?= htmlspecialchars((string)($settings['temperature'] ?? '0.7'), ENT_QUOTES) ?>" class="form-range">
            </div>

            <div class="form-group">
              <label for="max-tokens">Max Tokens</label>
              <input type="number" id="max-tokens" name="max_tokens" value="<?= htmlspecialchars((string)($settings['max_tokens'] ?? '1024'), ENT_QUOTES) ?>" class="form-control">
            </div>

            <div class="form-actions">
              <button type="submit" class="btn btn-primary">Save Settings</button>
              <button type="button" id="test-connection-btn" class="btn btn-secondary">Test Gemini Connection</button>
            </div>
            <div id="test-connection-result" class="alert" style="display:none;"></div>
          </form>
        </section>

        <section class="ai-chat-card">
          <h2><i class="fa-solid fa-comments"></i> AI Chat</h2>
          <div class="chat-history" id="chat-history"></div>
          <div class="chat-input">
            <input type="text" id="chat-message" placeholder="Type your message..." class="form-control">
            <button id="send-chat-btn" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i></button>
          </div>
          <div class="chat-actions">
            <button id="clear-history-btn" class="btn btn-danger">Clear History</button>
            <button id="export-pdf-btn" class="btn btn-secondary">Export to PDF</button>
          </div>
           <div class="quick-prompts">
                <button class="prompt-btn">"Explain solar panels"</button>
                <button class="prompt-btn">"Draft a customer email"</button>
                <button class="prompt-btn">"Summarize this text..."</button>
            </div>
        </section>
      </div>
    </div>
  </main>
  <script src="admin-ai-studio.js" defer></script>
</body>
</html>
