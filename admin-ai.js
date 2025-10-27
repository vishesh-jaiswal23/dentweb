(function () {
  'use strict';

  const config = window.dentwebAdminAI || {};
  const csrfToken = typeof config.csrfToken === 'string' ? config.csrfToken : '';
  const endpoints = config.endpoints || {};
  let drafts = Array.isArray(config.drafts) ? config.drafts.slice() : [];

  const providerForm = document.querySelector('[data-gemini-form]');
  const providerFeedback = document.querySelector('[data-feedback]');
  const apiKeyInput = providerForm ? providerForm.querySelector('input[name="api_key"]') : null;
  const enabledInput = providerForm ? providerForm.querySelector('[data-gemini-enabled]') : null;
  const statusBadge = document.querySelector('[data-gemini-status]');
  const maskedKeyNode = document.querySelector('[data-masked-key]');
  const testButton = document.querySelector('[data-gemini-test]');
  const generateForm = document.querySelector('[data-generate-form]');
  const generateFeedback = document.querySelector('[data-generate-feedback]');
  const draftList = document.querySelector('[data-draft-list]');

  function setFeedback(node, message, tone = 'info') {
    if (!node) return;
    const text = message ? String(message) : '';
    if (text === '') {
      node.textContent = '';
      node.hidden = true;
      node.removeAttribute('data-tone');
      return;
    }

    node.textContent = text;
    node.dataset.tone = tone;
    node.hidden = false;
  }

  function normaliseView(view) {
    if (!view || typeof view !== 'object') {
      return { enabled: false, hasKey: false, maskedKey: '' };
    }
    return {
      enabled: Boolean(view.enabled),
      hasKey: Boolean(view.hasKey),
      maskedKey: typeof view.maskedKey === 'string' ? view.maskedKey : '',
    };
  }

  function updateStatus(view) {
    const normalised = normaliseView(view);
    if (statusBadge) {
      statusBadge.dataset.state = normalised.enabled ? 'enabled' : 'disabled';
      statusBadge.innerHTML = '';
      const icon = document.createElement('i');
      icon.className = `fa-solid ${normalised.enabled ? 'fa-circle-check' : 'fa-circle-xmark'}`;
      icon.setAttribute('aria-hidden', 'true');
      statusBadge.appendChild(icon);
      statusBadge.appendChild(document.createTextNode(' '));
      statusBadge.appendChild(document.createTextNode(normalised.enabled ? 'Enabled' : 'Disabled'));
    }

    if (maskedKeyNode) {
      maskedKeyNode.textContent = normalised.hasKey && normalised.maskedKey ? normalised.maskedKey : 'Not set';
    }

    if (enabledInput) {
      enabledInput.checked = normalised.enabled;
    }

    window.dentwebAdminAI.gemini = Object.assign({}, window.dentwebAdminAI.gemini, normalised);
  }

  function apiPost(url, payload) {
    if (!url) {
      return Promise.reject(new Error('Service unavailable.'));
    }

    const body = payload === undefined ? '{}' : JSON.stringify(payload);

    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken,
      },
      body,
    })
      .then((response) =>
        response
          .json()
          .catch(() => ({}))
          .then((data) => {
            if (!response.ok || !data || data.success !== true) {
              const message = (data && data.error) || response.statusText || 'Request failed.';
              throw new Error(message);
            }
            return data.data;
          })
      );
  }

  function renderDrafts(list) {
    if (!draftList) return;

    draftList.innerHTML = '';
    if (!list || list.length === 0) {
      const row = document.createElement('tr');
      row.className = 'dashboard-empty-row';
      const cell = document.createElement('td');
      cell.colSpan = 3;
      cell.textContent = 'No Gemini drafts yet. Generate your first draft above.';
      row.appendChild(cell);
      draftList.appendChild(row);
      return;
    }

    list.forEach((draft) => {
      const row = document.createElement('tr');

      const titleCell = document.createElement('td');
      const title = document.createElement('strong');
      title.textContent = draft.title || 'Untitled draft';
      titleCell.appendChild(title);
      const meta = document.createElement('div');
      meta.className = 'ai-draft-list-meta';
      meta.textContent = `Slug: ${draft.slug || '—'}`;
      titleCell.appendChild(meta);
      row.appendChild(titleCell);

      const statusCell = document.createElement('td');
      statusCell.textContent = draft.status ? draft.status.charAt(0).toUpperCase() + draft.status.slice(1) : 'Draft';
      row.appendChild(statusCell);

      const updatedCell = document.createElement('td');
      updatedCell.textContent = draft.updatedDisplay || draft.updatedAt || '—';
      row.appendChild(updatedCell);

      draftList.appendChild(row);
    });
  }

  function handleProviderSubmit(event) {
    event.preventDefault();
    if (!providerForm) return;

    const payload = {
      apiKey: apiKeyInput ? apiKeyInput.value.trim() : '',
      enabled: enabledInput ? enabledInput.checked : false,
    };

    apiPost(endpoints.update, payload)
      .then((data) => {
        const view = data && data.gemini ? data.gemini : payload;
        updateStatus(view);
        if (apiKeyInput) {
          apiKeyInput.value = '';
        }
        setFeedback(providerFeedback, 'Gemini settings saved successfully.', 'success');
      })
      .catch((error) => {
        setFeedback(providerFeedback, error.message || 'Unable to save Gemini settings.', 'error');
      });
  }

  function handleTestClick() {
    if (!testButton) return;

    testButton.disabled = true;
    testButton.dataset.loading = 'true';
    const candidateKey = apiKeyInput ? apiKeyInput.value.trim() : '';

    apiPost(endpoints.test, { apiKey: candidateKey })
      .then((data) => {
        const testResult = data && data.test ? data.test : null;
        if (!testResult) {
          throw new Error('Gemini test did not return a response.');
        }
        const modelCount = typeof testResult.modelsDiscovered === 'number' ? testResult.modelsDiscovered : 0;
        const testedAt = testResult.testedAt
          ? new Date(testResult.testedAt).toLocaleString('en-IN', {
              dateStyle: 'medium',
              timeStyle: 'short',
            })
          : 'just now';
        const suffix = modelCount > 0 ? ` ${modelCount} model(s) discovered.` : '';
        setFeedback(providerFeedback, `Connection successful · ${testedAt}.${suffix}`, 'success');
      })
      .catch((error) => {
        setFeedback(providerFeedback, error.message || 'Gemini connectivity test failed.', 'error');
      })
      .finally(() => {
        testButton.disabled = false;
        delete testButton.dataset.loading;
      });
  }

  function handleGenerateSubmit(event) {
    event.preventDefault();
    if (!generateForm) return;

    const formData = new FormData(generateForm);
    const topic = String(formData.get('topic') || '').trim();
    if (topic === '') {
      setFeedback(generateFeedback, 'Enter a topic before generating a draft.', 'error');
      return;
    }

    const payload = {
      topic,
      tone: String(formData.get('tone') || 'informative').trim(),
      audience: String(formData.get('audience') || '').trim(),
      callToAction: String(formData.get('call_to_action') || '').trim(),
    };

    generateForm.querySelectorAll('button, input, select').forEach((element) => {
      element.disabled = true;
    });

    apiPost(endpoints.generate, payload)
      .then((data) => {
        const post = data && data.post ? data.post : null;
        drafts = Array.isArray(data && data.drafts) ? data.drafts : drafts;
        renderDrafts(drafts);
        if (apiKeyInput) {
          apiKeyInput.value = '';
        }
        const title = post && post.title ? post.title : 'Gemini draft';
        setFeedback(generateFeedback, `Draft "${title}" saved as draft.`, 'success');
        const topicField = generateForm.querySelector('input[name="topic"]');
        if (topicField) {
          topicField.value = '';
          topicField.focus();
        }
      })
      .catch((error) => {
        setFeedback(generateFeedback, error.message || 'Unable to generate a draft right now.', 'error');
      })
      .finally(() => {
        generateForm.querySelectorAll('button, input, select').forEach((element) => {
          element.disabled = false;
        });
      });
  }

  if (providerForm) {
    providerForm.addEventListener('submit', handleProviderSubmit);
  }

  if (testButton) {
    testButton.addEventListener('click', handleTestClick);
  }

  if (generateForm) {
    generateForm.addEventListener('submit', handleGenerateSubmit);
  }

  if (config.gemini) {
    updateStatus(config.gemini);
  }

  renderDrafts(drafts);
})();
