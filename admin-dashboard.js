(function () {
  'use strict';

  const config = window.DakshayaniAdmin;
  if (!config) return;

  const API_BASE = config.apiBase || 'api/admin.php';
  const THEME_KEY = 'dakshayani-admin-theme';
  const tabButtons = Array.from(document.querySelectorAll('[data-tab-target]'));
  const tabPanels = Array.from(document.querySelectorAll('[data-tab-panel]'));
  const toastContainer = document.querySelector('[data-toast-container]');
  const toastTemplate = document.getElementById('dashboard-toast-template');
  const searchInput = document.getElementById('dashboard-search-input');
  const searchForm = document.querySelector('[data-dashboard-search]');
  const searchResults = document.querySelector('[data-dashboard-search-results]');
  const quickSearchButton = document.querySelector('[data-open-quick-search]');
  const themeOptions = Array.from(document.querySelectorAll('[data-theme-option]'));
  const createUserForm = document.querySelector('[data-admin-user-form]');
  const inviteForm = document.querySelector('[data-invite-form]');
  const pendingList = document.querySelector('[data-pending-list]');
  const userTableBody = document.querySelector('[data-user-table-body]');
  const userFilter = document.querySelector('[data-user-filter]');
  const userCount = document.querySelector('[data-user-count]');
  const loginPolicyForm = document.querySelector('[data-login-policy]');
  const geminiForm = document.querySelector('[data-gemini-form]');
  const geminiResetButton = document.querySelector('[data-action="reset-gemini"]');
  const geminiTestButton = document.querySelector('[data-action="test-gemini"]');
  const geminiSaveButton = document.querySelector('[data-action="save-gemini"]');
  const geminiStatus = document.querySelector('[data-gemini-status]');
  const exportLogsButton = document.querySelector('[data-action="export-logs"]');
  const viewMonitoringButton = document.querySelector('[data-action="view-monitoring"]');
  const complaintPlaceholders = document.querySelectorAll('[data-placeholder^="complaint-"]');
  const auditPlaceholder = document.querySelector('[data-placeholder="activity-feed"]');
  const systemInputs = document.querySelectorAll('[data-system-metric]');
  const passwordForm = document.querySelector('[data-password-form]');
  const passwordStatus = document.querySelector('[data-password-status]');

  const state = {
    users: [],
    invitations: [],
    complaints: [],
    audit: [],
    metrics: config.metrics || { counts: {}, system: {} },
    loginPolicy: null,
    gemini: config.gemini || {},
  };

  function api(action, { method = 'GET', body } = {}) {
    const options = {
      method,
      headers: {
        'Accept': 'application/json',
        'X-CSRF-Token': config.csrfToken || '',
      },
      credentials: 'same-origin',
    };
    if (body) {
      options.headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(body);
    }
    return fetch(`${API_BASE}?action=${encodeURIComponent(action)}`, options)
      .then(async (response) => {
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || payload.success === false) {
          const message = payload.error || `Request failed (${response.status})`;
          throw new Error(message);
        }
        return payload.data;
      });
  }

  function showToast(title, message, tone = 'info', { autoDismiss = true, timeout = 4000 } = {}) {
    if (!toastContainer || !toastTemplate) return;
    const clone = toastTemplate.content.firstElementChild.cloneNode(true);
    clone.querySelector('.dashboard-toast-title').textContent = title;
    clone.querySelector('.dashboard-toast-message').textContent = message;
    clone.classList.add(`dashboard-toast--${tone}`);

    const closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'dashboard-toast-dismiss';
    closeButton.innerHTML = '<span class="sr-only">Dismiss</span><i class="fa-solid fa-xmark" aria-hidden="true"></i>';
    closeButton.addEventListener('click', () => dismissToast(clone));
    clone.appendChild(closeButton);

    toastContainer.appendChild(clone);
    requestAnimationFrame(() => clone.classList.add('is-visible'));

    if (autoDismiss) {
      const timer = setTimeout(() => dismissToast(clone), timeout);
      clone.dataset.timeoutId = String(timer);
    }
    return clone;
  }

  function dismissToast(node) {
    if (!node || node.classList.contains('is-leaving')) return;
    node.classList.add('is-leaving');
    const timeoutId = node.dataset.timeoutId;
    if (timeoutId) {
      clearTimeout(Number(timeoutId));
    }
    setTimeout(() => {
      node.remove();
    }, 200);
  }

  function prefersDark() {
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  }

  function applyTheme(mode, { silent = false } = {}) {
    const resolved = mode === 'auto' ? (prefersDark() ? 'dark' : 'light') : mode;
    document.body.dataset.dashboardTheme = resolved;
    document.body.dataset.dashboardThemeMode = mode;
    themeOptions.forEach((option) => {
      option.checked = option.value === mode;
    });
    try {
      localStorage.setItem(THEME_KEY, mode);
    } catch (error) {
      // ignore storage issues
    }
    if (!silent) {
      showToast('Theme updated', `Admin theme switched to ${resolved} mode.`, 'info');
    }
  }

  function initTheme() {
    let stored = 'light';
    try {
      stored = localStorage.getItem(THEME_KEY) || 'light';
    } catch (error) {
      stored = 'light';
    }
    applyTheme(stored, { silent: true });
    if (stored === 'auto' && window.matchMedia) {
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => applyTheme('auto', { silent: true }));
    }
  }

  themeOptions.forEach((option) => {
    option.addEventListener('change', () => applyTheme(option.value));
  });

  initTheme();

  function activateTab(id) {
    tabPanels.forEach((panel) => {
      const isActive = panel.id === id;
      panel.hidden = !isActive;
      panel.setAttribute('aria-hidden', String(!isActive));
    });
    tabButtons.forEach((button) => {
      const active = button.dataset.tabTarget === id;
      button.classList.toggle('dashboard-nav-link--active', active);
      button.setAttribute('aria-selected', String(active));
    });
    if (id) {
      history.replaceState(null, '', `#${id}`);
    }
  }

  tabButtons.forEach((button) => {
    button.addEventListener('click', () => activateTab(button.dataset.tabTarget));
  });

  const initialHash = window.location.hash.replace('#', '');
  if (initialHash && tabPanels.some((panel) => panel.id === initialHash)) {
    activateTab(initialHash);
  } else if (tabPanels.length) {
    activateTab(tabPanels[0].id);
  }

  quickSearchButton?.addEventListener('click', () => {
    activateTab('overview');
    searchInput?.focus();
  });

  function updateMetricCards(counts) {
    if (!counts) return;
    document.querySelectorAll('[data-metric]').forEach((node) => {
      const key = node.dataset.metric;
      switch (key) {
        case 'customers':
          node.textContent = counts.customers ?? '0';
          break;
        case 'approvals':
          node.textContent = counts.pendingInvitations ?? '0';
          break;
        case 'tickets':
          node.textContent = counts.openComplaints ?? '0';
          break;
        case 'subsidy':
          node.textContent = counts.subsidyPipeline ?? '0';
          break;
        default:
          break;
      }
    });
  }

  function updateSystemHealth(system) {
    if (!systemInputs.length || !system) return;
    systemInputs.forEach((input) => {
      const key = input.dataset.systemMetric;
      if (key && key in system) {
        input.value = system[key];
      }
    });
  }

  function formatDate(value) {
    if (!value) return '--';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return `${date.toLocaleDateString()} ${date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
  }

  function renderUsers() {
    if (!userTableBody) return;
    userTableBody.innerHTML = '';
    const filter = (userFilter?.value || 'all').toLowerCase();
    const filtered = state.users.filter((user) => {
      if (filter === 'all') return true;
      return user.role_name?.toLowerCase() === filter;
    });

    if (!filtered.length) {
      const row = document.createElement('tr');
      row.className = 'dashboard-empty-row';
      const cell = document.createElement('td');
      cell.colSpan = 6;
      cell.textContent = 'No users match the selected filters.';
      row.appendChild(cell);
      userTableBody.appendChild(row);
    } else {
      filtered.forEach((user) => {
        const row = document.createElement('tr');
        row.dataset.userId = String(user.id);
        row.innerHTML = `
          <td>
            <strong>${escapeHtml(user.full_name)}</strong>
            <small>${escapeHtml(user.email)}</small>
          </td>
          <td>${escapeHtml(capitalize(user.role_name))}</td>
          <td>${escapeHtml(user.username)}</td>
          <td><span class="badge ${statusClass(user.status)}">${escapeHtml(capitalize(user.status))}</span></td>
          <td>${user.password_last_set_at ? `Updated ${escapeHtml(formatDate(user.password_last_set_at))}` : 'Never set'}</td>
          <td>
            <button type="button" class="btn btn-ghost btn-xs" data-action="toggle-user" data-user-id="${user.id}" data-next-status="${nextStatus(user.status)}">
              ${toggleLabel(user.status)}
            </button>
          </td>
        `;
        userTableBody.appendChild(row);
      });
    }

    if (userCount) {
      userCount.textContent = `${state.users.length} user${state.users.length === 1 ? '' : 's'}`;
    }
  }

  function statusClass(status) {
    switch (status) {
      case 'active':
        return 'badge-soft-success';
      case 'inactive':
        return 'badge-soft-warning';
      case 'pending':
        return 'badge-soft';
      default:
        return 'badge-soft';
    }
  }

  function toggleLabel(status) {
    switch (status) {
      case 'active':
        return 'Deactivate';
      case 'inactive':
        return 'Activate';
      default:
        return 'Activate';
    }
  }

  function nextStatus(status) {
    return status === 'active' ? 'inactive' : 'active';
  }

  userFilter?.addEventListener('change', renderUsers);

  userTableBody?.addEventListener('click', (event) => {
    const target = event.target.closest('[data-action="toggle-user"]');
    if (!target) return;
    const userId = Number(target.dataset.userId);
    const status = target.dataset.nextStatus;
    api('update-user-status', { method: 'POST', body: { userId, status } })
      .then((data) => {
        const updated = data.user;
        state.users = state.users.map((user) => (user.id === updated.id ? updated : user));
        state.metrics = data.metrics;
        renderUsers();
        updateMetricCards(state.metrics.counts);
        showToast('User updated', `Status set to ${capitalize(updated.status)}.`, 'success');
      })
      .catch((error) => showToast('Update failed', error.message, 'error'));
  });

  function renderInvitations() {
    if (!pendingList) return;
    pendingList.innerHTML = '';
    if (!state.invitations.length) {
      const li = document.createElement('li');
      li.className = 'dashboard-list-empty';
      li.innerHTML = '<p class="primary">No pending invitations logged.</p><p class="secondary">Employee-submitted profiles will appear here for your approval.</p>';
      pendingList.appendChild(li);
      return;
    }

    state.invitations.forEach((invite) => {
      const li = document.createElement('li');
      li.className = 'dashboard-list-item';
      li.dataset.inviteId = String(invite.id);
      li.innerHTML = `
        <div>
          <p class="primary">${escapeHtml(invite.invitee_name)} · ${escapeHtml(capitalize(invite.role_name))}</p>
          <p class="secondary">${escapeHtml(invite.invitee_email)} · Submitted by ${escapeHtml(invite.message || 'Employee portal')} · ${escapeHtml(formatDate(invite.created_at))}</p>
        </div>
        <div class="dashboard-list-actions">
          <button type="button" class="btn btn-xs btn-secondary" data-action="approve-invite" data-invite-id="${invite.id}">Approve</button>
          <button type="button" class="btn btn-xs btn-ghost" data-action="reject-invite" data-invite-id="${invite.id}">Reject</button>
        </div>
      `;
      pendingList.appendChild(li);
    });
  }

  pendingList?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-action]');
    if (!button) return;
    const inviteId = Number(button.dataset.inviteId);
    if (!inviteId) return;

    if (button.dataset.action === 'approve-invite') {
      const username = prompt('Set a username for this account');
      if (!username) {
        showToast('Approval cancelled', 'Username is required to approve invitations.', 'warning');
        return;
      }
      const password = prompt('Set a temporary password (min 8 characters)');
      if (!password) {
        showToast('Approval cancelled', 'A password is required to approve invitations.', 'warning');
        return;
      }
      api('approve-invite', { method: 'POST', body: { inviteId, username, password } })
        .then((data) => {
          state.invitations = state.invitations.map((invite) => (invite.id === inviteId ? data.invitation : invite)).filter((invite) => invite.status === 'pending');
          state.users.push(data.user);
          state.metrics = data.metrics;
          renderInvitations();
          renderUsers();
          updateMetricCards(state.metrics.counts);
          showToast('Invitation approved', 'The user account has been created and activated.', 'success');
        })
        .catch((error) => showToast('Approval failed', error.message, 'error'));
    } else if (button.dataset.action === 'reject-invite') {
      api('reject-invite', { method: 'POST', body: { inviteId } })
        .then((data) => {
          state.invitations = state.invitations.filter((invite) => invite.id !== inviteId);
          state.metrics = data.metrics;
          renderInvitations();
          updateMetricCards(state.metrics.counts);
          showToast('Invitation rejected', 'The invitation has been marked as rejected.', 'info');
        })
        .catch((error) => showToast('Rejection failed', error.message, 'error'));
    }
  });

  function renderComplaints() {
    complaintPlaceholders.forEach((placeholder) => {
      placeholder.textContent = 'No complaints loaded.';
    });
    if (!state.complaints.length) {
      return;
    }

    const grouped = state.complaints.reduce((acc, complaint) => {
      const status = complaint.status || 'intake';
      if (!acc[status]) acc[status] = [];
      acc[status].push(complaint);
      return acc;
    }, {});

    complaintPlaceholders.forEach((placeholder) => {
      const key = placeholder.dataset.placeholder?.replace('complaint-', '') || '';
      const complaints = grouped[key] || [];
      if (!complaints.length) {
        placeholder.textContent = 'No records in this stage.';
        return;
      }
      const list = document.createElement('ul');
      list.className = 'dashboard-inline-list';
      complaints.slice(0, 3).forEach((complaint) => {
        const item = document.createElement('li');
        item.innerHTML = `
          <strong>${escapeHtml(complaint.reference)}</strong>
          <span>${escapeHtml(complaint.title)}</span>
          <small>Assigned to ${escapeHtml(complaint.assigned_to_name || 'Unassigned')} · SLA ${complaint.sla_due_at ? escapeHtml(formatDate(complaint.sla_due_at)) : 'Not set'}</small>
        `;
        list.appendChild(item);
      });
      placeholder.innerHTML = '';
      placeholder.appendChild(list);
    });
  }

  function renderAudit() {
    if (!auditPlaceholder) return;
    const list = document.createElement('ul');
    list.className = 'dashboard-notifications';
    if (!state.audit.length) {
      auditPlaceholder.innerHTML = '<li class="dashboard-notification dashboard-notification--info"><i class="fa-solid fa-bolt" aria-hidden="true"></i><div><p>No recent activity to display.</p><span>Live audit events will appear after integration.</span></div></li>';
      return;
    }

    state.audit.slice(0, 10).forEach((event) => {
      const li = document.createElement('li');
      li.className = 'dashboard-notification dashboard-notification--info';
      li.innerHTML = `
        <i class="fa-solid fa-shield" aria-hidden="true"></i>
        <div>
          <p>${escapeHtml(event.action)} · ${escapeHtml(event.actor_name || 'System')}</p>
          <span>${escapeHtml(event.description)} · ${escapeHtml(formatDate(event.created_at))}</span>
        </div>
      `;
      list.appendChild(li);
    });
    auditPlaceholder.innerHTML = '';
    auditPlaceholder.appendChild(list);
  }

  function populateLoginPolicy() {
    if (!loginPolicyForm || !state.loginPolicy) return;
    loginPolicyForm.retry.value = state.loginPolicy.retry_limit;
    loginPolicyForm.lockout.value = state.loginPolicy.lockout_minutes;
    loginPolicyForm.session.value = state.loginPolicy.session_timeout;
    loginPolicyForm.twofactor.value = state.loginPolicy.twofactor_mode;
  }

  function populateGemini() {
    if (!geminiForm || !state.gemini) return;
    geminiForm.apiKey.value = state.gemini.apiKey || '';
    geminiForm.textModel.value = state.gemini.textModel || 'gemini-2.5-flash';
    geminiForm.imageModel.value = state.gemini.imageModel || 'gemini-2.5-flash-image';
    geminiForm.ttsModel.value = state.gemini.ttsModel || 'gemini-2.5-flash-preview-tts';
  }

  createUserForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    const formData = new FormData(createUserForm);
    const payload = Object.fromEntries(formData.entries());
    api('create-user', { method: 'POST', body: payload })
      .then((data) => {
        state.users.unshift(data.user);
        state.metrics = data.metrics;
        updateMetricCards(state.metrics.counts);
        renderUsers();
        createUserForm.reset();
        showToast('User created', 'The new account has been saved and activated.', 'success');
      })
      .catch((error) => showToast('Creation failed', error.message, 'error'));
  });

  inviteForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    const formData = new FormData(inviteForm);
    const payload = Object.fromEntries(formData.entries());
    api('invite-user', { method: 'POST', body: payload })
      .then((data) => {
        state.invitations.unshift(data.invitation);
        state.metrics = data.metrics;
        updateMetricCards(state.metrics.counts);
        renderInvitations();
        inviteForm.reset();
        showToast('Invitation logged', 'Waiting for administrator approval.', 'info');
      })
      .catch((error) => showToast('Unable to log invitation', error.message, 'error'));
  });

  loginPolicyForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    const formData = new FormData(loginPolicyForm);
    const payload = Object.fromEntries(formData.entries());
    api('update-login-policy', { method: 'POST', body: payload })
      .then((data) => {
        state.loginPolicy = data.loginPolicy;
        populateLoginPolicy();
        showToast('Policies updated', 'Authentication policy saved successfully.', 'success');
      })
      .catch((error) => showToast('Policy update failed', error.message, 'error'));
  });

  geminiForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    const formData = new FormData(geminiForm);
    const payload = Object.fromEntries(formData.entries());
    api('update-gemini', { method: 'POST', body: payload })
      .then((data) => {
        state.gemini = data.gemini;
        populateGemini();
        showToast('Gemini saved', 'API credentials stored securely.', 'success');
      })
      .catch((error) => showToast('Save failed', error.message, 'error'));
  });

  geminiResetButton?.addEventListener('click', () => {
    state.gemini = {
      apiKey: config.gemini?.apiKey || '',
      textModel: 'gemini-2.5-flash',
      imageModel: 'gemini-2.5-flash-image',
      ttsModel: 'gemini-2.5-flash-preview-tts',
    };
    populateGemini();
    clearInlineStatus(geminiStatus);
    showToast('Defaults restored', 'Gemini configuration reset to defaults.', 'info');
  });

  geminiTestButton?.addEventListener('click', () => {
    if (!geminiForm) return;
    const apiKey = geminiForm.apiKey.value.trim();
    if (!apiKey) {
      showToast('Missing API key', 'Enter a Gemini API key before testing.', 'warning');
      return;
    }
    geminiTestButton.disabled = true;
    geminiTestButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Testing…';
    renderInlineStatus(
      geminiStatus,
      'progress',
      'Testing connection…',
      'Attempting to reach the Gemini models endpoint.'
    );
    api('test-gemini', { method: 'POST', body: { apiKey } })
      .then((data) => {
        const models = Array.isArray(data?.models) ? data.models : [];
        const names = models
          .map((model) => model.displayName || model.name)
          .filter(Boolean)
          .slice(0, 3);
        const testedAt = data?.testedAt ? new Date(data.testedAt).toLocaleString() : '';
        const detailParts = [];
        if (testedAt) {
          detailParts.push(`Tested at ${testedAt}`);
        }
        if (names.length) {
          detailParts.push(`Sample models: ${names.join(', ')}`);
        }
        renderInlineStatus(
          geminiStatus,
          'success',
          'Connection verified',
          'Gemini responded successfully with accessible models.',
          detailParts.join(' · ')
        );
        showToast('Gemini verified', 'Gemini connection verified successfully.', 'success');
      })
      .catch((error) => {
        renderInlineStatus(geminiStatus, 'error', 'Test failed', error.message);
        showToast('Test failed', error.message, 'error');
      })
      .finally(() => {
        geminiTestButton.disabled = false;
        geminiTestButton.innerHTML = '<i class="fa-solid fa-plug-circle-check" aria-hidden="true"></i> Test connection';
      });
  });

  passwordForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    const formData = new FormData(passwordForm);
    const currentPassword = (formData.get('currentPassword') || '').toString();
    const newPassword = (formData.get('newPassword') || '').toString();
    const confirmPassword = (formData.get('confirmPassword') || '').toString();

    if (!currentPassword || !newPassword || !confirmPassword) {
      renderInlineStatus(passwordStatus, 'error', 'Missing information', 'Fill in all password fields before submitting.');
      showToast('Update failed', 'Complete all password fields before submitting.', 'error');
      return;
    }

    if (newPassword !== confirmPassword) {
      renderInlineStatus(passwordStatus, 'error', 'Passwords do not match', 'Re-enter the new password so both fields match.');
      showToast('Update failed', 'New password and confirmation must match.', 'error');
      return;
    }

    if (newPassword.length < 8) {
      renderInlineStatus(passwordStatus, 'error', 'Password too short', 'Choose a password with at least 8 characters.');
      showToast('Update failed', 'Password must be at least 8 characters.', 'error');
      return;
    }

    const submitButton = passwordForm.querySelector('[data-action="change-password"]');
    const originalLabel = submitButton?.innerHTML;
    if (submitButton) {
      submitButton.disabled = true;
      submitButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Updating…';
    }

    renderInlineStatus(passwordStatus, 'progress', 'Updating password…', 'Saving your new administrator credentials.');

    api('change-password', {
      method: 'POST',
      body: { currentPassword, newPassword, confirmPassword },
    })
      .then((data) => {
        passwordForm.reset();
        const changedAt = data?.changedAt ? new Date(data.changedAt).toLocaleString() : '';
        renderInlineStatus(
          passwordStatus,
          'success',
          'Password updated',
          'Your administrator password has been changed successfully.',
          changedAt ? `Updated at ${changedAt}` : ''
        );
        showToast('Password updated', 'Administrator password updated successfully.', 'success');
      })
      .catch((error) => {
        renderInlineStatus(passwordStatus, 'error', 'Update failed', error.message);
        showToast('Update failed', error.message, 'error');
      })
      .finally(() => {
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.innerHTML = originalLabel || 'Update password';
        }
      });
  });

  passwordForm?.addEventListener('reset', () => {
    clearInlineStatus(passwordStatus);
  });

  exportLogsButton?.addEventListener('click', () => {
    api('fetch-audit')
      .then((data) => {
        const content = JSON.stringify(data.audit, null, 2);
        const blob = new Blob([content], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = `audit-log-${Date.now()}.json`;
        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();
        URL.revokeObjectURL(url);
        showToast('Logs exported', 'Latest audit entries downloaded.', 'success');
      })
      .catch((error) => showToast('Export failed', error.message, 'error'));
  });

  viewMonitoringButton?.addEventListener('click', () => {
    activateTab('health');
    showToast('Monitoring view', 'Scroll for the latest uptime and error summaries.', 'info');
  });

  function escapeHtml(value) {
    return (value ?? '').toString().replace(/[&<'">]+/g, (match) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;',
    })[match]);
  }

  function renderInlineStatus(node, tone, title, message, details = '') {
    if (!node) return;
    const icons = {
      success: 'fa-circle-check',
      error: 'fa-triangle-exclamation',
      progress: 'fa-arrows-rotate',
      info: 'fa-circle-info',
    };
    const icon = icons[tone] || icons.info;
    node.hidden = false;
    node.dataset.tone = tone || 'info';
    let html = `<i class="fa-solid ${icon}" aria-hidden="true"></i><div>`;
    if (title) {
      html += `<strong>${escapeHtml(title)}</strong>`;
    }
    if (message) {
      html += `<p>${escapeHtml(message)}</p>`;
    }
    if (details) {
      html += `<p>${escapeHtml(details)}</p>`;
    }
    html += '</div>';
    node.innerHTML = html;
  }

  function clearInlineStatus(node) {
    if (!node) return;
    node.hidden = true;
    node.innerHTML = '';
    delete node.dataset.tone;
  }

  function capitalize(value) {
    if (!value) return '';
    return value.charAt(0).toUpperCase() + value.slice(1);
  }

  function handleSearch(term) {
    if (!searchResults) return;
    if (term.length < 2) {
      searchResults.innerHTML = '<p class="dashboard-search-empty">Start typing to surface admin modules, records, and recent activity.</p>';
      searchResults.hidden = term.length === 0;
      return;
    }

    const lower = term.toLowerCase();
    const matches = [];

    state.users.forEach((user) => {
      if (
        user.full_name.toLowerCase().includes(lower) ||
        user.email.toLowerCase().includes(lower) ||
        user.username.toLowerCase().includes(lower)
      ) {
        matches.push({
          type: 'User',
          title: user.full_name,
          subtitle: `${capitalize(user.role_name)} · ${user.email}`,
          action: () => activateTab('onboarding'),
        });
      }
    });

    state.invitations.forEach((invite) => {
      if (invite.invitee_name.toLowerCase().includes(lower) || invite.invitee_email.toLowerCase().includes(lower)) {
        matches.push({
          type: 'Invitation',
          title: invite.invitee_name,
          subtitle: `${capitalize(invite.role_name)} · ${invite.invitee_email}`,
          action: () => activateTab('onboarding'),
        });
      }
    });

    state.complaints.forEach((complaint) => {
      if ((complaint.reference || '').toLowerCase().includes(lower) || (complaint.title || '').toLowerCase().includes(lower)) {
        matches.push({
          type: 'Complaint',
          title: complaint.reference,
          subtitle: `${complaint.title} · ${capitalize(complaint.status)}`,
          action: () => activateTab('complaints'),
        });
      }
    });

    state.audit.forEach((entry) => {
      if ((entry.description || '').toLowerCase().includes(lower)) {
        matches.push({
          type: 'Audit',
          title: entry.action,
          subtitle: `${entry.actor_name || 'System'} · ${formatDate(entry.created_at)}`,
          action: () => activateTab('audit'),
        });
      }
    });

    if (!matches.length) {
      searchResults.innerHTML = '<p class="dashboard-search-empty">No records matched your search.</p>';
      searchResults.hidden = false;
      return;
    }

    const list = document.createElement('ul');
    list.className = 'dashboard-search-list';
    matches.slice(0, 8).forEach((match) => {
      const item = document.createElement('li');
      item.innerHTML = `
        <button type="button">
          <span>${escapeHtml(match.title)}</span>
          <small>${escapeHtml(match.type)} · ${escapeHtml(match.subtitle)}</small>
        </button>
      `;
      item.querySelector('button').addEventListener('click', () => {
        match.action?.();
        searchResults.hidden = true;
        searchInput.value = '';
      });
      list.appendChild(item);
    });
    searchResults.innerHTML = '';
    searchResults.appendChild(list);
    searchResults.hidden = false;
  }

  searchInput?.addEventListener('input', () => handleSearch(searchInput.value.trim()));
  searchForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    handleSearch(searchInput.value.trim());
  });

  function hydrateFromConfig() {
    updateMetricCards(state.metrics.counts);
    updateSystemHealth(state.metrics.system);
    populateGemini();
  }

  function loadBootstrap() {
    api('bootstrap')
      .then((data) => {
        state.users = data.users;
        state.invitations = data.invitations.filter((invite) => invite.status === 'pending');
        state.complaints = data.complaints;
        state.audit = data.audit;
        state.metrics = data.metrics;
        state.loginPolicy = data.loginPolicy;
        state.gemini = data.gemini;
        updateMetricCards(state.metrics.counts);
        updateSystemHealth(state.metrics.system);
        populateLoginPolicy();
        populateGemini();
        renderUsers();
        renderInvitations();
        renderComplaints();
        renderAudit();
      })
      .catch((error) => {
        showToast('Failed to load admin data', error.message, 'error');
        hydrateFromConfig();
        renderUsers();
        renderInvitations();
      });
  }

  hydrateFromConfig();
  loadBootstrap();
})();
