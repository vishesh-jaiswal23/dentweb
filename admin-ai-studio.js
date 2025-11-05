// AI Studio JavaScript
(function () {
    'use strict';

    function showToast(message, tone = 'info') {
        const toast = document.createElement('div');
        toast.className = `admin-toast admin-toast--${tone}`;
        toast.innerHTML = `<i class="fa-solid fa-circle-info"></i><span>${message}</span>`;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.remove();
        }, 5000);
    }

    // AI Settings form submission
    const settingsForm = document.getElementById('ai-settings-form');
    if (settingsForm) {
        settingsForm.addEventListener('submit', function (event) {
            event.preventDefault();
            const formData = new FormData(settingsForm);
            const settings = Object.fromEntries(formData.entries());

            fetch('api/admin.php?action=save-ai-settings', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(settings),
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Settings saved successfully. Connected.', 'success');
                } else {
                    showToast(data.error || 'An error occurred.', 'error');
                }
            })
            .catch(() => {
                showToast('An unexpected error occurred.', 'error');
            });
        });
    }

    // Reveal API Key functionality
    const revealApiKeyBtn = document.getElementById('reveal-api-key');
    const apiKeyInput = document.getElementById('api-key');
    if (revealApiKeyBtn && apiKeyInput) {
        revealApiKeyBtn.addEventListener('click', function () {
            if (apiKeyInput.type === 'password') {
                apiKeyInput.type = 'text';
                revealApiKeyBtn.innerHTML = '<i class="fa-solid fa-eye-slash"></i>';
            } else {
                apiKeyInput.type = 'password';
                revealApiKeyBtn.innerHTML = '<i class="fa-solid fa-eye"></i>';
            }
        });
    }

    // Test Gemini Connection functionality
    const testConnectionBtn = document.getElementById('test-connection-btn');
    if (testConnectionBtn) {
        testConnectionBtn.addEventListener('click', function () {
            const apiKey = apiKeyInput.value;
            const textModel = document.getElementById('gemini-text-model').value;

            fetch('api/admin.php?action=save-ai-settings', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ api_key: apiKey, gemini_text_model: textModel }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Gemini connection successful.', 'success');
                } else {
                    showToast(data.error || 'Gemini connection failed.', 'error');
                }
            })
            .catch(() => {
                showToast('An unexpected error occurred.', 'error');
            });
        });
    }

    // AI Chat functionality
    const chatLog = document.getElementById('ai-chat-log');
    const chatInput = document.getElementById('ai-chat-input');
    const chatSendBtn = document.getElementById('ai-chat-send-btn');
    const quickPromptButtons = document.querySelectorAll('.ai-chat__quick-prompts button');

    function appendToChatLog(message, sender) {
        const messageElement = document.createElement('div');
        messageElement.className = `ai-chat__message ai-chat__message--${sender}`;
        messageElement.textContent = message;
        chatLog.appendChild(messageElement);
        chatLog.scrollTop = chatLog.scrollHeight;
    }

    function sendChatMessage() {
        const message = chatInput.value.trim();
        if (message === '') return;

        appendToChatLog(message, 'user');
        chatInput.value = '';

        const eventSource = new EventSource(`api/admin.php?action=ai-chat&prompt=${encodeURIComponent(message)}`);
        let responseContainer = appendToChatLog('', 'model');

        eventSource.onmessage = function (event) {
            const data = JSON.parse(event.data);
            if (data.candidates && data.candidates[0].content.parts[0].text) {
                responseContainer.textContent += data.candidates[0].content.parts[0].text;
            }
        };

        eventSource.onerror = function () {
            showToast('Error receiving streaming response.', 'error');
            eventSource.close();
        };
    }

    chatSendBtn.addEventListener('click', sendChatMessage);
    chatInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendChatMessage();
        }
    });

    quickPromptButtons.forEach(button => {
        button.addEventListener('click', function () {
            const prompt = button.getAttribute('data-prompt');
            chatInput.value = prompt;
            chatInput.focus();
        });
    });
})();
