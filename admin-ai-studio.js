document.addEventListener('DOMContentLoaded', () => {
    // --- SETTINGS ---
    const settingsForm = document.getElementById('ai-settings-form');
    const toggleApiKeyBtn = document.getElementById('toggle-api-key');
    const apiKeyInput = document.getElementById('api-key');
    const temperatureSlider = document.getElementById('temperature');
    const temperatureValue = document.getElementById('temperature-value');
    const testConnectionBtn = document.getElementById('test-connection-btn');
    const testConnectionResult = document.getElementById('test-connection-result');

    if (settingsForm) {
        settingsForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(settingsForm);
            const settings = Object.fromEntries(formData.entries());
            settings.enabled = formData.has('enabled');

            await fetch('api/admin.php?action=save-ai-settings', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(settings)
            });
            // You can add a success message here
        });
    }

    if (toggleApiKeyBtn && apiKeyInput) {
        toggleApiKeyBtn.addEventListener('click', () => {
            apiKeyInput.type = apiKeyInput.type === 'password' ? 'text' : 'password';
        });
    }

    if (temperatureSlider && temperatureValue) {
        temperatureSlider.addEventListener('input', () => {
            temperatureValue.textContent = temperatureSlider.value;
        });
    }

    if (testConnectionBtn && testConnectionResult) {
        testConnectionBtn.addEventListener('click', async () => {
            testConnectionResult.style.display = 'none';
            testConnectionResult.className = 'alert';
            testConnectionBtn.disabled = true;

            try {
                const response = await fetch('api/admin.php?action=test-gemini-connection', { method: 'POST' });
                const result = await response.json();

                if (result.success) {
                    testConnectionResult.textContent = result.data.message;
                    testConnectionResult.classList.add('alert-success');
                } else {
                    testConnectionResult.textContent = result.error || 'An unknown error occurred.';
                    testConnectionResult.classList.add('alert-danger');
                }
            } catch (error) {
                testConnectionResult.textContent = 'Failed to connect to the server.';
                testConnectionResult.classList.add('alert-danger');
            } finally {
                testConnectionResult.style.display = 'block';
                testConnectionBtn.disabled = false;
            }
        });
    }

    // --- CHAT ---
    const chatHistory = document.getElementById('chat-history');
    const chatMessageInput = document.getElementById('chat-message');
    const sendChatBtn = document.getElementById('send-chat-btn');
    const clearHistoryBtn = document.getElementById('clear-history-btn');
    const exportPdfBtn = document.getElementById('export-pdf-btn');
    const quickPromptBtns = document.querySelectorAll('.prompt-btn');

    const addMessageToHistory = (sender, message) => {
        const messageElement = document.createElement('div');
        messageElement.classList.add('chat-message', `chat-message-${sender}`);
        messageElement.textContent = message;
        chatHistory.appendChild(messageElement);
        chatHistory.scrollTop = chatHistory.scrollHeight;
        return messageElement;
    };

    const loadChatHistory = async () => {
        const response = await fetch('api/admin.php?action=get-chat-history');
        const result = await response.json();
        if (result.success && Array.isArray(result.data)) {
            chatHistory.innerHTML = '';
            result.data.forEach(msg => addMessageToHistory(msg.sender, msg.message));
        }
    };

    const handleSendMessage = () => {
        const message = chatMessageInput.value.trim();
        if (!message) return;

        addMessageToHistory('user', message);
        chatMessageInput.value = '';
        sendChatBtn.disabled = true;

        const aiMessageElement = addMessageToHistory('ai', '');
        let fullResponse = '';

        const eventSource = new EventSource(`api/admin.php?action=handle-chat&message=${encodeURIComponent(message)}`);

        eventSource.onmessage = (event) => {
            // Check for the DONE signal or handle data
            if (event.data === '[DONE]') {
                eventSource.close();
                sendChatBtn.disabled = false;
                return;
            }

            // The Gemini stream sends multiple JSON objects, they need to be parsed carefully.
            // This is a simplified parser.
            try {
                const jsonString = event.data.trim();
                const data = JSON.parse(jsonString);
                if (data.candidates && data.candidates[0].content.parts[0].text) {
                    const textChunk = data.candidates[0].content.parts[0].text;
                    fullResponse += textChunk;
                    aiMessageElement.textContent = fullResponse;
                }
            } catch (e) {
                // Ignore parsing errors, as the stream may send partial data
            }
        };

        eventSource.onerror = () => {
            aiMessageElement.textContent = 'Error: Could not get response from AI.';
            aiMessageElement.classList.add('chat-error');
            eventSource.close();
            sendChatBtn.disabled = false;
        };
    };

    if (sendChatBtn && chatMessageInput) {
        sendChatBtn.addEventListener('click', handleSendMessage);
        chatMessageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') handleSendMessage();
        });
    }

    if (clearHistoryBtn && chatHistory) {
        clearHistoryBtn.addEventListener('click', async () => {
            if (confirm('Are you sure you want to clear the entire chat history?')) {
                await fetch('api/admin.php?action=clear-chat-history', { method: 'POST' });
                chatHistory.innerHTML = '';
            }
        });
    }

    loadChatHistory();

    if (exportPdfBtn) {
        exportPdfBtn.addEventListener('click', () => {
            window.print();
        });
    }

    quickPromptBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            chatMessageInput.value = btn.textContent.replace(/"/g, '');
            chatMessageInput.focus();
        });
    });
});
