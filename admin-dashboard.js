// Moved from IIFE to global scope
function showToast(message, tone = 'info') {
    const toast = document.createElement('div');
    toast.className = `admin-toast admin-toast--${tone}`;
    toast.innerHTML = `<i class="fa-solid fa-circle-info"></i><span>${message}</span>`;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.remove();
    }, 5000);
}

function refreshContent() {
    const mainContent = document.querySelector('.admin-records__shell');
    if (!mainContent) return;

    fetch(window.location.href)
        .then(response => response.text())
        .then(html => {
            const newDoc = new DOMParser().parseFromString(html, 'text/html');
            const newContent = newDoc.querySelector('.admin-records__shell');
            if (newContent) {
                mainContent.innerHTML = newContent.innerHTML;
            }
        })
        .catch(error => {
            console.error('Error refreshing content:', error);
            showToast('Failed to refresh content.', 'error');
        });
}

(function () {
  'use strict';

  const storageKey = 'dakshayani-admin-theme';
  const toggleButton = document.querySelector('[data-theme-toggle]');
  const highlightTimes = document.querySelectorAll('[data-highlight-time]');
  const root = document.body;

  function applyTheme(theme, { persist = true } = {}) {
    if (!root) return;
    const value = theme === 'dark' ? 'dark' : 'light';
    root.setAttribute('data-theme', value);
    if (persist && window.localStorage) {
      window.localStorage.setItem(storageKey, value);
    }
  }

  function initTheme() {
    if (!root) return;
    if (window.localStorage) {
      const stored = window.localStorage.getItem(storageKey);
      if (stored === 'dark' || stored === 'light') {
        applyTheme(stored, { persist: false });
        return;
      }
    }
    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
      applyTheme('dark', { persist: false });
    }
  }

  toggleButton?.addEventListener('click', () => {
    const current = root?.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    applyTheme(current === 'dark' ? 'light' : 'dark');
  });

  function formatRelativeTime(node) {
    if (!node?.dateTime) return;
    const target = new Date(node.dateTime);
    if (Number.isNaN(target.getTime())) return;
    const now = new Date();
    const diffMs = target.getTime() - now.getTime();
    const diffMinutes = Math.round(diffMs / 60000);
    const formatter = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

    let value;
    let unit;
    if (Math.abs(diffMinutes) < 60) {
      value = diffMinutes;
      unit = 'minute';
    } else if (Math.abs(diffMinutes) < 60 * 24) {
      value = Math.round(diffMinutes / 60);
      unit = 'hour';
    } else {
      value = Math.round(diffMinutes / (60 * 24));
      unit = 'day';
    }

    node.textContent = formatter.format(value, unit);
    node.setAttribute('title', target.toLocaleString());
  }

  highlightTimes.forEach(formatRelativeTime);
  initTheme();

  function showModal(title, content, onConfirm) {
    const modal = document.createElement('div');
    modal.className = 'admin-modal';
    modal.innerHTML = `
      <div class="admin-modal__content">
        <h2>${title}</h2>
        <div>${content}</div>
        <div class="admin-modal__actions">
          <button class="btn btn-secondary" id="modal-cancel">Cancel</button>
          <button class="btn btn-primary" id="modal-confirm">Confirm</button>
        </div>
      </div>
    `;
    document.body.appendChild(modal);

    document.getElementById('modal-cancel').addEventListener('click', () => {
      modal.remove();
    });

    document.getElementById('modal-confirm').addEventListener('click', () => {
      onConfirm();
      modal.remove();
    });
  }
  // Expose showModal to global scope for other functions
  window.showModal = showModal;

  const bulkApplyButton = document.getElementById('bulk-apply');
  if (bulkApplyButton) {
    bulkApplyButton.addEventListener('click', () => {
      const actionSelect = document.getElementById('bulk-action');
      const selectedAction = actionSelect.value;
      const selectedOption = actionSelect.options[actionSelect.selectedIndex];
      const selectedCustomerIds = Array.from(document.querySelectorAll('input[name="customer_ids[]"]:checked')).map(cb => cb.value);

      if (selectedCustomerIds.length === 0) {
        showToast('Please select at least one customer.', 'warning');
        return;
      }

      if (!selectedAction) {
        showToast('Please select a bulk action.', 'warning');
        return;
      }

      if (selectedAction === 'export') {
        const state = new URLSearchParams(window.location.search).get('filter') || 'all';
        const url = `customer-export.php?state=${state}&ids=${selectedCustomerIds.join(',')}`;
        window.location.href = url;
        return;
      }

      const onConfirm = () => {
        const payload = {
          bulk_action: selectedAction,
          customer_ids: selectedCustomerIds,
        };

        if (selectedAction === 'change_state') {
          payload.state = selectedOption.dataset.state;
        }

        fetch('api/admin.php?action=bulk-update-customers', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              showToast('Bulk action completed successfully.', 'success');
              refreshContent();
            } else {
              showToast(data.error || 'An error occurred.', 'error');
            }
          })
          .catch(() => {
            showToast('An unexpected error occurred.', 'error');
          });
      };

      showModal(
        'Confirm Bulk Action',
        `<p>Are you sure you want to perform this action on ${selectedCustomerIds.length} customer(s)?</p>`,
        onConfirm
      );
    });
  }

  const csvUploadForm = document.getElementById('csv-upload-form');
  if (csvUploadForm) {
    csvUploadForm.addEventListener('submit', event => {
      event.preventDefault();
      const fileInput = document.getElementById('csv-file');
      const dryRunCheckbox = document.getElementById('csv-dry-run');
      const file = fileInput.files[0];

      if (!file) {
        showToast('Please select a CSV file to upload.', 'warning');
        return;
      }

      const formData = new FormData();
      formData.append('csv_file', file);
      if (dryRunCheckbox.checked) {
        formData.append('dry_run', '1');
      }

      fetch('api/admin.php?action=import-customers', {
        method: 'POST',
        body: formData,
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            let message = `CSV import successful. ${data.data.created} created, ${data.data.updated} updated.`;
            if (data.data.skipped > 0) {
                message += ` ${data.data.skipped} skipped.`;
            }
            showToast(message, 'success');
            refreshContent();
          } else {
            showToast(data.error || 'An error occurred during CSV import.', 'error');
          }
        })
        .catch(() => {
          showToast('An unexpected error occurred.', 'error');
        });
    });
  }

  const generateDraftForm = document.getElementById('ai-blog-generator-form');
  if (generateDraftForm) {
    generateDraftForm.addEventListener('submit', (event) => {
      event.preventDefault();
      const liveTypingCheckbox = generateDraftForm.querySelector('input[name="live_typing"]');
      if (liveTypingCheckbox && liveTypingCheckbox.checked) {
        handleLiveTyping();
        return;
      }

      const generateButton = generateDraftForm.querySelector('button[value="generate-draft"]');
      generateButton.disabled = true;
      generateButton.textContent = 'Generating...';

      const formData = new FormData(generateDraftForm);
      const payload = Object.fromEntries(formData.entries());

      fetch('api/admin.php?action=generate-draft', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const result = data.data;
            let message = `Draft "${result.title}" generated. `;
            if (result.artwork_generated) {
              message += 'Artwork attached.';
            }
            message += ` <a href="admin-ai-studio.php?tab=generator&draft=${result.draft_id}#ai-draft-editor" class="admin-toast__link">View Draft</a>`;
            showToast(message, 'success');
          } else {
            showToast(data.error || 'An error occurred.', 'error');
          }
        })
        .catch(() => {
          showToast('An unexpected network error occurred.', 'error');
        })
        .finally(() => {
          generateButton.disabled = false;
          generateButton.textContent = 'Generate blog draft';
        });
    });
  }

  async function handleLiveTyping() {
    const livePreviewContainer = document.getElementById('ai-live-preview-container');
    const livePreviewContent = document.getElementById('ai-live-preview-content');
    const liveStatus = document.getElementById('ai-live-status');
    const elapsedTimeEl = document.getElementById('ai-elapsed-time');
    const tokensSecEl = document.getElementById('ai-tokens-sec');
    const pauseResumeButton = document.getElementById('ai-pause-resume');
    const stopSaveButton = document.getElementById('ai-stop-save');
    const discardButton = document.getElementById('ai-discard');
    const generateButton = generateDraftForm.querySelector('button[value="generate-draft"]');

    livePreviewContainer.style.display = 'block';
    livePreviewContent.innerHTML = '';
    liveStatus.textContent = 'Initializing...';
    generateButton.disabled = true;

    const formData = new FormData(generateDraftForm);
    const payload = Object.fromEntries(formData.entries());

    let isPaused = false;
    let startTime = Date.now();
    let tokens = 0;
    const controller = new AbortController();

    pauseResumeButton.onclick = () => {
      isPaused = !isPaused;
      pauseResumeButton.textContent = isPaused ? 'Resume' : 'Pause';
    };

    stopSaveButton.onclick = () => {
      controller.abort();
    };

    discardButton.onclick = () => {
      controller.abort();
      livePreviewContainer.style.display = 'none';
    };

    const timerInterval = setInterval(() => {
      const elapsed = Math.round((Date.now() - startTime) / 1000);
      elapsedTimeEl.textContent = `${elapsed}s`;
      const tokensPerSec = elapsed > 0 ? (tokens / elapsed).toFixed(1) : 0;
      tokensSecEl.textContent = tokensPerSec;
    }, 1000);

    try {
      const response = await fetch('api/admin.php?action=stream-generate-draft', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        signal: controller.signal,
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const reader = response.body.getReader();
      const decoder = new TextDecoder();
      let buffer = '';

      while (true) {
        const { done, value } = await reader.read();
        if (done) {
          break;
        }

        buffer += decoder.decode(value, { stream: true });
        const lines = buffer.split('\n');
        buffer = lines.pop(); // Keep the last partial line in the buffer

        for (const line of lines) {
          if (!line.startsWith('data:')) continue;

          const eventData = line.substring(5).trim();
          if (!eventData) continue;

          let eventName = 'message';
          const prevLine = lines[lines.indexOf(line) - 1];
          if (prevLine && prevLine.startsWith('event:')) {
            eventName = prevLine.substring(6).trim();
          }

          const data = JSON.parse(eventData);

          if (eventName === 'start') {
            liveStatus.textContent = 'Live';
            liveStatus.insertAdjacentHTML('afterend', '<span class="ai-status-badge" id="ai-unverified-badge">Preview (Unverified)</span>');
          } else if (eventName === 'chunk' && !isPaused) {
            livePreviewContent.innerHTML += data.text;
            tokens++;
          } else if (eventName === 'saved') {
            showToast(`Draft saved: ${data.draft.title}`, 'success');
            liveStatus.textContent = 'Draft Saved';
            const unverifiedBadge = document.getElementById('ai-unverified-badge');
            if (unverifiedBadge) unverifiedBadge.remove();
          } else if (eventName === 'complete') {
            // Stream finished from server side
          } else if (eventName === 'error') {
            throw new Error(data.message);
          }
        }
      }
    } catch (error) {
        if (error.name !== 'AbortError') {
            showToast(error.message || 'An unexpected error occurred.', 'error');
            liveStatus.textContent = 'Error';
        }
    } finally {
      clearInterval(timerInterval);
      stopSaveButton.disabled = true;
      pauseResumeButton.disabled = true;
      generateButton.disabled = false;
    }
  }

  const testConnectionButton = document.querySelector('button[value="test-connection"]');
  if (testConnectionButton) {
    testConnectionButton.addEventListener('click', (event) => {
      event.preventDefault();
      testConnectionButton.disabled = true;
      testConnectionButton.textContent = 'Testing...';

      fetch('api/admin.php?action=test-connection', {
        method: 'POST',
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const result = data.data;
            showToast(result.message, result.status === 'pass' ? 'success' : 'warning');
          } else {
            showToast(data.error || 'An error occurred.', 'error');
          }
        })
        .catch(() => {
          showToast('An unexpected network error occurred.', 'error');
        })
        .finally(() => {
          testConnectionButton.disabled = false;
          testConnectionButton.textContent = 'Test connection';
        });
    });
  }

  const restoreSnapshotButton = document.getElementById('restore-snapshot-btn');
  if (restoreSnapshotButton) {
    restoreSnapshotButton.addEventListener('click', () => {
      const draftId = restoreSnapshotButton.dataset.draftId;
      fetch('api/admin.php?action=restore-draft', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ draftId }),
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          const topicTextarea = document.querySelector('textarea[name="topic"]');
          if (topicTextarea) {
            topicTextarea.value = data.data.content;
          }
          showToast('Draft restored.', 'success');
          restoreSnapshotButton.closest('.admin-alert').remove();
        } else {
          showToast(data.error || 'Failed to restore draft.', 'error');
        }
      })
      .catch(() => {
        showToast('An unexpected error occurred.', 'error');
      });
    });
  }

  const revealApiKeyButton = document.querySelector('[data-reveal-api-key]');
  if (revealApiKeyButton) {
    revealApiKeyButton.addEventListener('click', () => {
      const apiKeyInput = document.getElementById('api-key-input');
      if (apiKeyInput.type === 'password') {
        if (confirm('Show API key? This is not recommended.')) {
          apiKeyInput.type = 'text';
          revealApiKeyButton.textContent = 'Hide';
        }
      } else {
        apiKeyInput.type = 'password';
        revealApiKeyButton.textContent = 'Reveal';
      }
    });
  }
})();


async function getEmployees() {
  try {
    const response = await fetch('api/admin.php?action=list-employees');
    if (!response.ok) return [];
    const data = await response.json();
    if (data.success && Array.isArray(data.data)) {
      return data.data;
    }
    return [];
  } catch (e) {
    console.error('Failed to fetch employees', e);
    return [];
  }
}

function showFormModal(title, content, onConfirm) {
  const modal = document.createElement('div');
  modal.className = 'admin-modal';
  modal.innerHTML = `
    <div class="admin-modal__content">
      <form id="dynamic-modal-form" style="display: contents;">
        <h2>${title}</h2>
        <div>${content}</div>
        <div class="admin-modal__actions">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Confirm</button>
        </div>
      </form>
    </div>
  `;
  document.body.appendChild(modal);
  const form = modal.querySelector('form');
  const cancelButton = modal.querySelector('[data-dismiss="modal"]');

  const closeModal = () => modal.remove();

  cancelButton.addEventListener('click', closeModal);
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());
    if (onConfirm(payload) !== false) {
      closeModal();
    }
  });
}

async function changeState(customerId, state) {
  const performStateChange = (payload) => {
    fetch('api/admin.php?action=change-customer-state', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showToast('Customer state updated.', 'success');
        refreshContent();
      } else {
        showToast(data.error || 'An error occurred.', 'error');
      }
    })
    .catch(() => {
      showToast('An unexpected error occurred.', 'error');
    });
  };

  if (state === 'ongoing') {
    const employees = await getEmployees();
    const employeeOptions = employees.map(e => `<option value="${e.id}">${e.full_name}</option>`).join('');
    const modalContent = `
      <div class="admin-form" style="display: grid; gap: 1rem;">
        <label for="modal-assigned-employee">Assigned Employee</label>
        <select id="modal-assigned-employee" name="assigned_employee_id" required>
          <option value="">-- Select Employee --</option>
          ${employeeOptions}
        </select>
        <label for="modal-system-type">System Type</label>
        <input type="text" id="modal-system-type" name="system_type" required />
        <label for="modal-system-kwp">System kWp</label>
        <input type="number" id="modal-system-kwp" name="system_kwp" step="0.1" required />
      </div>
    `;
    showFormModal('Move to Ongoing', modalContent, (formData) => {
      if (!formData.assigned_employee_id || !formData.system_type || !formData.system_kwp) {
        showToast('All fields are required.', 'warning');
        return false;
      }
      performStateChange({ ...formData, id: customerId, state: state });
    });
  } else if (state === 'installed') {
    const today = new Date().toISOString().split('T')[0];
    const modalContent = `
      <div class="admin-form" style="display: grid; gap: 1rem;">
        <label for="modal-handover-date">Handover Date</label>
        <input type="date" id="modal-handover-date" name="handover_date" value="${today}" required />
      </div>
    `;
    showFormModal('Mark as Installed', modalContent, (formData) => {
      if (!formData.handover_date) {
        showToast('Handover date is required.', 'warning');
        return false;
      }
      performStateChange({ ...formData, id: customerId, state: state });
    });
  }
}

function deactivateCustomer(customerId) {
    window.showModal(
        'Confirm Deactivation',
        '<p>Are you sure you want to deactivate this customer?</p>',
        () => {
            fetch('api/admin.php?action=deactivate-customer', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: customerId }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Customer deactivated.', 'success');
                    refreshContent();
                } else {
                    showToast(data.error || 'An error occurred.', 'error');
                }
            })
            .catch(() => {
                showToast('An unexpected error occurred.', 'error');
            });
        }
    );
}

function reactivateCustomer(customerId) {
    window.showModal(
        'Confirm Reactivation',
        '<p>Are you sure you want to reactivate this customer?</p>',
        () => {
            fetch('api/admin.php?action=reactivate-customer', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: customerId }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Customer reactivated.', 'success');
                    refreshContent();
                } else {
                    showToast(data.error || 'An error occurred.', 'error');
                }
            })
            .catch(() => {
                showToast('An unexpected error occurred.', 'error');
            });
        }
    );
}

function deleteCustomer(customerId) {
    window.showModal(
        'Confirm Deletion',
        '<p>Are you sure you want to permanently delete this customer?</p>',
        () => {
            fetch('api/admin.php?action=delete-customer', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: customerId }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Customer deleted.', 'success');
                    refreshContent();
                } else {
                    showToast(data.error || 'An error occurred.', 'error');
                }
            })
            .catch(() => {
                showToast('An unexpected error occurred.', 'error');
            });
        }
    );
}
