document.addEventListener('DOMContentLoaded', () => {
    // --- SETTINGS ---
    const settingsForm = document.getElementById('ai-settings-form');
    const toggleApiKeyBtn = document.getElementById('toggle-api-key');
    const apiKeyInput = document.getElementById('api-key');
    const temperatureSlider = document.getElementById('temperature');
    const temperatureValue = document.getElementById('temperature-value');
    const testConnectionBtn = document.getElementById('test-connection-btn');
    const testConnectionResult = document.getElementById('test-connection-result');
    const saveResult = document.getElementById('save-result');

    if (settingsForm) {
        settingsForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const saveButton = settingsForm.querySelector('button[type="submit"]');
            saveButton.disabled = true;
            saveButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
            saveResult.style.display = 'none';
            saveResult.className = 'alert';

            const formData = new FormData(settingsForm);
            const settings = Object.fromEntries(formData.entries());
            settings.enabled = formData.has('enabled');

            try {
                const response = await fetch('api/admin.php?action=save-ai-settings', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(settings)
                });
                const result = await response.json();

                if (result.success) {
                    saveResult.textContent = 'Settings saved successfully.';
                    saveResult.classList.add('alert-success');

                    // Update the form with the new settings
                    const newSettings = result.data;
                    for (const key in newSettings) {
                        const input = settingsForm.querySelector(`[name="${key}"]`);
                        if (input) {
                            if (input.type === 'checkbox') {
                                input.checked = newSettings[key];
                            } else {
                                input.value = newSettings[key];
                            }
                        }
                    }
                } else {
                    saveResult.textContent = result.error || 'An unknown error occurred.';
                    saveResult.classList.add('alert-danger');
                }
            } catch (error) {
                saveResult.textContent = 'Failed to connect to the server.';
                saveResult.classList.add('alert-danger');
            } finally {
                saveResult.style.display = 'block';
                saveButton.disabled = false;
                saveButton.innerHTML = 'Save Settings';
            }
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
            try {
                // The backend now sends well-formed JSON objects
                const data = JSON.parse(event.data);
                if (data.candidates && data.candidates[0].content.parts[0].text) {
                    const textChunk = data.candidates[0].content.parts[0].text;
                    fullResponse += textChunk;
                    aiMessageElement.textContent = fullResponse;
                }
            } catch (e) {
                // This can happen if the stream sends a DONE signal or an error
                if (event.data.includes('[DONE]')) {
                    eventSource.close();
                    sendChatBtn.disabled = false;
                } else {
                    console.error('Error parsing streaming data:', event.data);
                }
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
