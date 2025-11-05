<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bootstrap.php';

require_admin();
$admin = current_user();

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>AI Studio | Admin</title>
  <link rel="icon" href="images/favicon.ico" />
  <link rel="stylesheet" href="style.css" />
</head>
<body class="admin-dashboard">
  <main class="admin-overview__shell">
    <header class="admin-overview__header">
        <div class="admin-overview__identity">
            <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
            <div>
                <p class="admin-overview__subtitle">Welcome to the</p>
                <h1 class="admin-overview__title">AI Studio</h1>
                <p class="admin-overview__user">Signed in as <strong><?= htmlspecialchars($admin['full_name'] ?? 'Administrator', ENT_QUOTES) ?></strong></p>
            </div>
        </div>
    </header>

    <div class="admin-records__body">
        <div class="admin-records__content">
            <section class="admin-form-container">
                <h2 class="admin-form-container__title">AI Settings (Gemini-Only)</h2>
                <form id="ai-settings-form" class="admin-form">
                    <div class="admin-form__group">
                        <label for="gemini-text-model">Gemini Text Model Code</label>
                        <input type="text" id="gemini-text-model" name="gemini_text_model" value="gemini-2.5-flash" required>
                    </div>
                    <div class="admin-form__group">
                        <label for="gemini-image-model">Gemini Image Model Code</label>
                        <input type="text" id="gemini-image-model" name="gemini_image_model" value="gemini-2.5-flash-image" required>
                    </div>
                    <div class="admin-form__group">
                        <label for="gemini-tts-model">Gemini TTS Model Code</label>
                        <input type="text" id="gemini-tts-model" name="gemini_tts_model" value="gemini-2.5-flash-preview-tts" required>
                    </div>
                    <div class="admin-form__group">
                        <label for="api-key">API Key</label>
                        <div class="admin-form__input-group">
                            <input type="password" id="api-key" name="api_key" placeholder="Enter your Gemini API Key" required>
                            <button type="button" id="reveal-api-key" class="btn btn-secondary"><i class="fa-solid fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="admin-form__group">
                        <label for="temperature">Temperature</label>
                        <input type="range" id="temperature" name="temperature" min="0" max="1" step="0.1" value="0.7">
                    </div>
                    <div class="admin-form__group">
                        <label for="max-tokens">Max Tokens</label>
                        <input type="number" id="max-tokens" name="max_tokens" min="1" value="2048">
                    </div>
                    <div class="admin-form__group admin-form__group--inline">
                        <label for="ai-enabled">AI On/Off</label>
                        <input type="checkbox" id="ai-enabled" name="ai_enabled" class="admin-form__toggle" checked>
                    </div>
                    <div class="admin-form__actions">
                        <button type="submit" class="btn btn-primary">Update Settings</button>
                        <button type="button" id="test-connection-btn" class="btn btn-secondary">Test Gemini Connection</button>
                    </div>
                </form>
            </section>
            <section class="admin-form-container">
                <h2 class="admin-form-container__title">AI Chat</h2>
                <div class="ai-chat__container">
                    <div id="ai-chat-log" class="ai-chat__log"></div>
                    <div class="ai-chat__input-container">
                        <textarea id="ai-chat-input" class="ai-chat__input" placeholder="Type your message..."></textarea>
                        <button id="ai-chat-send-btn" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i></button>
                    </div>
                    <div class="ai-chat__quick-prompts">
                        <button class="btn btn-secondary" data-prompt="Summarize the following text: ">Summarize</button>
                        <button class="btn btn-secondary" data-prompt="Write a professional email about ">Write Email</button>
                        <button class="btn btn-secondary" data-prompt="Generate a project proposal for ">Project Proposal</button>
                    </div>
                     <div class="ai-chat__actions">
                        <button id="ai-chat-clear-btn" class="btn btn-danger">Clear History</button>
                        <button id="ai-chat-export-pdf-btn" class="btn btn-secondary">Export as PDF</button>
                    </div>
                </div>
            </section>
        </div>
    </div>
  </main>
  <script src="admin-ai-studio.js" defer></script>
</body>
</html>
