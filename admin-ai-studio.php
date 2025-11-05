<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bootstrap.php';

require_admin();
$admin = current_user();

// Load AI settings
$settings_file = __DIR__ . '/storage/ai/settings.json';
$ai_settings = [];
if (file_exists($settings_file)) {
    $ai_settings = json_decode(file_get_contents($settings_file), true);
}

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>AI Studio | Admin</title>
  <link rel="icon" href="images/favicon.ico" />
  <link rel="stylesheet" href="style.css" />
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    crossorigin="anonymous"
    referrerpolicy="no-referrer"
  />
</head>
<body class="admin-dashboard">
  <main class="admin-overview__shell">
    <header class="admin-overview__header">
        <div class="admin-overview__identity">
            <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
            <div>
                <h1 class="admin-overview__title">AI Studio</h1>
                <p class="admin-overview__user">Signed in as <strong><?= htmlspecialchars($admin['full_name'] ?? 'Administrator', ENT_QUOTES) ?></strong></p>
            </div>
        </div>
    </header>

    <div class="admin-records__grid">
        <section class="admin-records__card">
            <h2 class="admin-records__card-title">AI Settings (Gemini-Only)</h2>
            <form id="ai-settings-form">
                <div class="form-group">
                    <label for="api-key">Gemini API Key</label>
                    <div class="input-group">
                        <input type="password" id="api-key" name="api_key" value="<?= htmlspecialchars($ai_settings['api_key'] ?? '', ENT_QUOTES) ?>" class="form-control">
                        <button type="button" id="reveal-api-key" class="btn btn-secondary">Reveal</button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="model-text">Gemini Text Model Code</label>
                    <input type="text" id="model-text" name="model_text" value="<?= htmlspecialchars($ai_settings['model_text'] ?? 'gemini-2.5-flash', ENT_QUOTES) ?>" class="form-control">
                </div>
                <div class="form-group">
                    <label for="model-image">Gemini Image Model Code</label>
                    <input type="text" id="model-image" name="model_image" value="<?= htmlspecialchars($ai_settings['model_image'] ?? 'gemini-2.5-flash-image', ENT_QUOTES) ?>" class="form-control">
                </div>
                <div class="form-group">
                    <label for="model-tts">Gemini TTS Model Code</label>
                    <input type="text" id="model-tts" name="model_tts" value="<?= htmlspecialchars($ai_settings['model_tts'] ?? 'gemini-2.5-flash-preview-tts', ENT_QUOTES) ?>" class="form-control">
                </div>
                <div class="form-group">
                    <label for="temperature">Temperature</label>
                    <input type="range" id="temperature" name="temperature" min="0" max="1" step="0.1" value="<?= htmlspecialchars((string)($ai_settings['temperature'] ?? 0.7), ENT_QUOTES) ?>" class="form-range">
                    <span id="temperature-value"><?= htmlspecialchars((string)($ai_settings['temperature'] ?? 0.7), ENT_QUOTES) ?></span>
                </div>
                <div class="form-group">
                    <label for="max-tokens">Max Tokens</label>
                    <input type="number" id="max-tokens" name="max_tokens" value="<?= htmlspecialchars((string)($ai_settings['max_tokens'] ?? 1024), ENT_QUOTES) ?>" class="form-control">
                </div>
                <div class="form-group">
                    <label for="ai-enabled">AI Enabled</label>
                    <input type="checkbox" id="ai-enabled" name="enabled" <?= ($ai_settings['enabled'] ?? true) ? 'checked' : '' ?>>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update Settings</button>
                    <button type="button" id="test-connection" class="btn btn-secondary">Test Gemini Connection</button>
                </div>
            </form>
        </section>

        <section class="admin-records__card">
            <h2 class="admin-records__card-title">AI Chat (Gemini Text Model Only)</h2>
            <div class="chat-console">
                <div class="chat-history"></div>
                <div class="chat-input">
                    <input type="text" id="chat-message" placeholder="Type your message...">
                    <button id="send-chat-message" class="btn btn-primary">Send</button>
                </div>
                <div class="quick-prompts">
                    <button class="btn btn-secondary" data-prompt="Summarize the following text: ">Summarize</button>
                    <button class="btn btn-secondary" data-prompt="Draft a proposal for a new solar installation.">Proposal</button>
                    <button class="btn btn-secondary" data-prompt="Write a follow-up email to a customer.">Email</button>
                </div>
                <div class="chat-actions">
                    <button id="clear-history" class="btn btn-danger">Clear History</button>
                    <button id="export-pdf" class="btn btn-secondary">Export as PDF</button>
                </div>
            </div>
        </section>
    </div>
  </main>
  <script src="admin-dashboard.js" defer></script>
</body>
</html>
