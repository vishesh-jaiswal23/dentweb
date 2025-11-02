function refreshContent() {
  fetch(window.location.href)
    .then(response => response.text())
    .then(html => {
      const parser = new DOMParser();
      const doc = parser.parseFromString(html, 'text/html');
      const newContent = doc.getElementById('main-content').innerHTML;
      document.getElementById('main-content').innerHTML = newContent;
    })
    .catch(error => {
      console.error('Failed to refresh content:', error);
      showToast('Could not refresh content automatically.', 'error');
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

  // Make showToast globally available
  window.showToast = function showToast(message, tone = 'info') {
    const toast = document.createElement('div');
    toast.className = `admin-toast admin-toast--${tone}`;
    toast.innerHTML = `<i class="fa-solid fa-circle-info"></i><span>${message}</span>`;
    document.body.appendChild(toast);
    setTimeout(() => {
      toast.remove();
    }, 5000);
  }

  // Make showModal globally available
  window.showModal = function showModal(title, content, onConfirm) {
    const modal = document.createElement('div');
    modal.className = 'admin-modal';
    modal.innerHTML = `
      <div class="admin-modal__content">
        <h2>${title}</h2>
        <div class="admin-modal-body">${content}</div>
        <div class="admin-modal__actions">
          <button class="btn btn-secondary" id="modal-cancel">Cancel</button>
          <button class="btn btn-primary" id="modal-confirm">Confirm</button>
        </div>
      </div>
    `;
    document.body.appendChild(modal);

    const closeModal = () => modal.remove();
    const confirmButton = document.getElementById('modal-confirm');
    const cancelButton = document.getElementById('modal-cancel');

    cancelButton.addEventListener('click', closeModal);
    confirmButton.addEventListener('click', () => {
      const modalBody = modal.querySelector('.admin-modal-body');
      const form = modalBody.querySelector('form');
      let payload = {};
      if (form) {
        const formData = new FormData(form);
        for (const [key, value] of formData.entries()) {
          payload[key] = value;
        }
      }
      if (onConfirm(payload) !== false) {
        closeModal();
      }
    });
  }

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
})();

async function changeState(customerId, state) {
  let modalTitle = 'Confirm State Change';
  let modalContent = `<p>Are you sure you want to move this customer to '${state}'?</p>`;
  let onConfirm = (payload) => {
    // Default confirm handler
    const finalPayload = { id: customerId, state, ...payload };
    fetch('api/admin.php?action=change-customer-state', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(finalPayload),
    })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showToast('Customer state updated successfully.', 'success');
          refreshContent();
        } else {
          showToast(data.error || 'An error occurred.', 'error');
        }
      })
      .catch((err) => {
        showToast('An unexpected network error occurred.', 'error');
        console.error(err);
      });
    return true; // Close modal after sending
  };

  if (state === 'ongoing') {
    modalTitle = 'Move to Ongoing';
    try {
      const response = await fetch('api/admin.php?action=get-employees');
      const { data } = await response.json();
      const employees = data.employees || [];
      const installers = data.installers || [];
      const employeeOptions = employees.map(e => `<option value="${e.id}">${e.full_name}</option>`).join('');
      const installerOptions = installers.map(i => `<option value="${i.id}">${i.full_name}</option>`).join('');

      modalContent = `
        <form id="ongoing-form">
          <div class="form-group">
            <label for="assigned_employee_id">Assign Employee *</label>
            <select name="assigned_employee_id" id="assigned_employee_id" required>${employeeOptions}</select>
          </div>
          <div class="form-group">
            <label for="assigned_installer_id">Assign Installer</label>
            <select name="assigned_installer_id" id="assigned_installer_id"><option value="">-- None --</option>${installerOptions}</select>
          </div>
          <div class="form-group">
            <label for="system_type">System Type *</label>
            <input type="text" name="system_type" id="system_type" required>
          </div>
          <div class="form-group">
            <label for="system_kwp">System kWp *</label>
            <input type="number" name="system_kwp" id="system_kwp" step="0.01" required>
          </div>
          <div class="form-group">
            <label for="notes">Notes</label>
            <textarea name="notes" id="notes"></textarea>
          </div>
        </form>
      `;
    } catch (error) {
        showToast('Failed to load required data. Please try again.', 'error');
        return;
    }
  } else if (state === 'installed') {
    modalTitle = 'Mark as Installed';
    modalContent = `
      <form id="installed-form">
        <div class="form-group">
          <label for="handover_date">Handover Date *</label>
          <input type="date" name="handover_date" id="handover_date" required>
        </div>
      </form>
    `;
  }

  showModal(modalTitle, modalContent, onConfirm);
}

function deactivateCustomer(customerId) {
  const onConfirm = () => {
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
    };

    showModal(
        'Confirm Deactivation',
        '<p>Are you sure you want to deactivate this customer?</p>',
        onConfirm
    );
}

  const generateDraftForm = document.getElementById('ai-blog-generator-form');
  if (generateDraftForm) {
    generateDraftForm.addEventListener('submit', (event) => {
      event.preventDefault();
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
            showToast(message, 'success');
            setTimeout(() => {
              window.location.href = `admin-ai-studio.php?tab=generator&draft=${result.draft_id}#ai-draft-editor`;
            }, 1000);
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

function reactivateCustomer(customerId) {
    const onConfirm = () => {
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
    };

    showModal(
        'Confirm Reactivation',
        '<p>Are you sure you want to reactivate this customer?</p>',
        onConfirm
    );
}

function deleteCustomer(customerId) {
    const onConfirm = () => {
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
    };

    showModal(
        'Confirm Deletion',
        '<p>Are you sure you want to permanently delete this customer?</p>',
        onConfirm
    );
}
