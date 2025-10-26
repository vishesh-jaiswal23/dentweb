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
  const taskForm = document.querySelector('[data-task-form]');
  const taskAssigneeSelect = document.querySelector('[data-task-assignee]');
  const taskColumns = document.querySelectorAll('[data-task-column]');
  const taskBoard = document.querySelector('[data-task-board]');
  const workloadTableBody = document.querySelector('[data-workload-table]');
  const workloadSummaryInput = document.querySelector('[data-workload-summary]');
  const workloadSplitInput = document.querySelector('[data-workload-split]');
  const documentForm = document.querySelector('[data-document-form]');
  const documentTableBody = document.querySelector('[data-document-table]');
  const documentFilter = document.querySelector('[data-document-filter]');
  const validationList = document.querySelector('[data-validation-list]');
  const duplicateList = document.querySelector('[data-duplicate-list]');
  const approvalList = document.querySelector('[data-approval-list]');
  const leadTableBody = document.querySelector('[data-leads-table]');
  const customerTableBody = document.querySelector('[data-customers-table]');
  const leadCountInput = document.querySelector('[data-lead-count]');
  const leadConversionInput = document.querySelector('[data-lead-conversion]');
  const customerCountInput = document.querySelector('[data-customer-count]');
  const installationCountInput = document.querySelector('[data-installation-count]');
  const referrerTableBody = document.querySelector('[data-referrer-table]');
  const referrerVerifiedInput = document.querySelector('[data-referrer-verified]');
  const referrerLeadsInput = document.querySelector('[data-referrer-leads]');
  const referrerSummaryList = document.querySelector('[data-referrer-summary]');
  const subsidyStageNodes = document.querySelectorAll('[data-subsidy-stage]');
  const subsidyAverageInput = document.querySelector('[data-subsidy-average]');
  const subsidyTableBody = document.querySelector('[data-subsidy-table]');

  const state = {
    users: [],
    invitations: [],
    complaints: [],
    audit: [],
    metrics: config.metrics || { counts: {}, system: {} },
    loginPolicy: null,
    gemini: config.gemini || {},
    tasks: config.tasks || { items: [], team: [] },
    documents: config.documents || [],
    dataQuality: config.dataQuality || { validations: [], duplicates: [], approvals: [] },
    crm: config.crm || { leads: [], customers: [] },
    referrers: config.referrers || [],
    subsidy: config.subsidy || { applications: [], stats: { stageCounts: {}, averageDays: 0 } },
  };

  const TASK_STATUS_LABELS = {
    todo: 'To Do',
    in_progress: 'In Progress',
    done: 'Done',
  };

  const TASK_STATUS_SEQUENCE = ['todo', 'in_progress', 'done'];
  const SUBSIDY_STAGE_SEQUENCE = ['applied', 'sanctioned', 'inspected', 'redeemed', 'closed'];

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

  function findTeamMember(id) {
    return state.tasks.team.find((member) => String(member.id) === String(id));
  }

  function populateTaskAssignees() {
    if (!taskAssigneeSelect) return;
    const currentValue = taskAssigneeSelect.value;
    taskAssigneeSelect.innerHTML = '<option value="">Select team member…</option>';
    state.tasks.team.forEach((member) => {
      const option = document.createElement('option');
      option.value = member.id;
      option.textContent = `${member.name} (${member.role})`;
      if (currentValue && String(member.id) === currentValue) {
        option.selected = true;
      }
      taskAssigneeSelect.appendChild(option);
    });
  }

  function renderTasks() {
    if (!taskColumns.length) return;
    const grouped = { todo: [], in_progress: [], done: [] };
    const sorted = [...state.tasks.items].sort((a, b) => {
      const priorityOrder = { high: 0, medium: 1, low: 2 };
      const statusDiff = TASK_STATUS_SEQUENCE.indexOf(a.status) - TASK_STATUS_SEQUENCE.indexOf(b.status);
      if (statusDiff !== 0) return statusDiff;
      const priorityDiff = (priorityOrder[a.priority] ?? 1) - (priorityOrder[b.priority] ?? 1);
      if (priorityDiff !== 0) return priorityDiff;
      return (a.dueDate || '').localeCompare(b.dueDate || '');
    });
    sorted.forEach((task) => {
      const status = TASK_STATUS_SEQUENCE.includes(task.status) ? task.status : 'todo';
      grouped[status].push(task);
    });

    taskColumns.forEach((column) => {
      const status = column.dataset.taskColumn;
      if (!status) return;
      const list = column;
      list.innerHTML = '';
      const tasks = grouped[status] || [];
      if (!tasks.length) {
        const empty = document.createElement('li');
        empty.className = 'dashboard-list-empty';
        empty.innerHTML = `
          <p class="primary">${status === 'todo' ? 'No tasks queued.' : status === 'in_progress' ? 'No active work.' : 'Nothing closed yet.'}</p>
          <p class="secondary">${status === 'todo' ? 'Create assignments to populate this stage.' : status === 'in_progress' ? 'Move tasks here as field teams begin execution.' : 'Completed work will roll into this column.'}</p>
        `;
        list.appendChild(empty);
        return;
      }

      tasks.forEach((task) => {
        const member = findTeamMember(task.assigneeId) || {};
        const nextStatus = TASK_STATUS_SEQUENCE[TASK_STATUS_SEQUENCE.indexOf(task.status) + 1];
        const li = document.createElement('li');
        li.className = 'dashboard-list-item';
        li.dataset.taskId = String(task.id);
        const badgeClass =
          task.priority === 'high'
            ? 'badge-soft-warning'
            : task.priority === 'low'
            ? 'badge-soft'
            : 'badge-soft-info';
        li.innerHTML = `
          <div>
            <p class="primary">${escapeHtml(task.title)} <span class="badge ${badgeClass}">${escapeHtml(task.priority || 'medium').toUpperCase()}</span></p>
            <p class="secondary">${escapeHtml(member.name || 'Unassigned')} · ${escapeHtml(member.role || 'Team')} ${task.dueDate ? `· Due ${escapeHtml(new Date(task.dueDate).toLocaleDateString())}` : ''}</p>
            ${task.linkedTo ? `<p class="secondary">Linked to ${escapeHtml(task.linkedTo)}</p>` : ''}
            ${task.notes ? `<p class="secondary">${escapeHtml(task.notes)}</p>` : ''}
          </div>
          <div class="dashboard-list-actions">
            ${
              nextStatus
                ? `<button type="button" class="btn btn-xs btn-secondary" data-action="advance-task" data-task-id="${task.id}" data-next-status="${nextStatus}">Move to ${escapeHtml(TASK_STATUS_LABELS[nextStatus])}</button>`
                : ''
            }
            ${
              task.status !== 'todo'
                ? `<button type="button" class="btn btn-xs btn-ghost" data-action="reset-task" data-task-id="${task.id}">Reopen</button>`
                : ''
            }
          </div>
        `;
        list.appendChild(li);
      });
    });

    renderWorkload();
  }

  function renderWorkload() {
    if (!workloadTableBody) return;
    workloadTableBody.innerHTML = '';
    const summary = new Map();
    state.tasks.team.forEach((member) => {
      summary.set(String(member.id), {
        member,
        todo: 0,
        in_progress: 0,
        done: 0,
        doneRecent: 0,
      });
    });

    const now = new Date();
    const sevenDaysAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
    let installerOpen = 0;
    let employeeOpen = 0;

    state.tasks.items.forEach((task) => {
      const key = String(task.assigneeId);
      if (!summary.has(key)) {
        summary.set(key, {
          member: { id: task.assigneeId, name: task.assigneeName || 'Unassigned', role: task.assigneeRole || 'Team' },
          todo: 0,
          in_progress: 0,
          done: 0,
          doneRecent: 0,
        });
      }
      const bucket = summary.get(key);
      if (bucket) {
        if (task.status === 'todo') bucket.todo += 1;
        if (task.status === 'in_progress') bucket.in_progress += 1;
        if (task.status === 'done') {
          bucket.done += 1;
          const finished = task.completedAt ? new Date(task.completedAt) : null;
          if (finished && !Number.isNaN(finished.getTime()) && finished >= sevenDaysAgo) {
            bucket.doneRecent += 1;
          }
        }
      }
      const member = bucket?.member || {};
      const isInstaller = /installer/i.test(member.role || '');
      if (task.status === 'todo' || task.status === 'in_progress') {
        if (isInstaller) installerOpen += 1;
        else employeeOpen += 1;
      }
    });

    const rows = Array.from(summary.values()).sort((a, b) => a.member.name.localeCompare(b.member.name));
    if (!rows.length) {
      const empty = document.createElement('tr');
      empty.className = 'dashboard-empty-row';
      empty.innerHTML = '<td colspan="5">No workload recorded yet.</td>';
      workloadTableBody.appendChild(empty);
    } else {
      rows.forEach((row) => {
        const { member, todo, in_progress: inProgress, doneRecent } = row;
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td><strong>${escapeHtml(member.name || 'Unassigned')}</strong></td>
          <td>${escapeHtml(member.role || 'Team')}</td>
          <td>${todo}</td>
          <td>${inProgress}</td>
          <td>${doneRecent}</td>
        `;
        workloadTableBody.appendChild(tr);
      });
    }

    const openTasks = state.tasks.items.filter((task) => task.status !== 'done').length;
    if (workloadSummaryInput) {
      workloadSummaryInput.value = `${openTasks} open`;
    }
    if (workloadSplitInput) {
      workloadSplitInput.value = `${installerOpen} / ${employeeOpen}`;
    }
  }

  function renderDocuments() {
    if (!documentTableBody) return;
    const filter = documentFilter?.value || 'all';
    documentTableBody.innerHTML = '';
    const filtered = state.documents.filter((doc) => filter === 'all' || doc.linkedTo === filter);
    if (!filtered.length) {
      const row = document.createElement('tr');
      row.className = 'dashboard-empty-row';
      row.innerHTML = '<td colspan="5">No documents match the selected filter.</td>';
      documentTableBody.appendChild(row);
      return;
    }

    filtered
      .slice()
      .sort((a, b) => (b.updatedAt || '').localeCompare(a.updatedAt || ''))
      .forEach((doc) => {
        const row = document.createElement('tr');
        row.innerHTML = `
          <td><strong>${escapeHtml(doc.name)}</strong><br /><small>${escapeHtml(doc.uploadedBy || 'Admin')}</small></td>
          <td>${escapeHtml(doc.reference || '—')}<br /><small>${escapeHtml(capitalize(doc.linkedTo || ''))}</small></td>
          <td>v${doc.version || 1}</td>
          <td>${(doc.tags || []).map((tag) => `<span class="badge badge-soft">${escapeHtml(tag)}</span>`).join(' ') || '—'}</td>
          <td>${escapeHtml(formatDate(doc.updatedAt))}</td>
        `;
        documentTableBody.appendChild(row);
      });
  }

  function renderValidations() {
    if (!validationList) return;
    validationList.innerHTML = '';
    if (!state.dataQuality.validations.length) {
      const li = document.createElement('li');
      li.className = 'dashboard-list-empty';
      li.innerHTML = '<p class="primary">No validation rules loaded.</p><p class="secondary">Bootstrap will hydrate the matrix of enforced fields.</p>';
      validationList.appendChild(li);
      return;
    }
    state.dataQuality.validations.forEach((rule) => {
      const li = document.createElement('li');
      li.className = 'dashboard-list-item';
      li.innerHTML = `
        <div>
          <p class="primary">${escapeHtml(rule.field)}</p>
          <p class="secondary">${escapeHtml(rule.description || '')}</p>
        </div>
        <div class="dashboard-list-actions">
          <span class="badge ${rule.status === 'enforced' ? 'badge-soft-success' : 'badge-soft-warning'}">${escapeHtml(capitalize(rule.status || 'pending'))}</span>
        </div>
      `;
      validationList.appendChild(li);
    });
  }

  function renderDuplicates() {
    if (!duplicateList) return;
    duplicateList.innerHTML = '';
    if (!state.dataQuality.duplicates.length) {
      const li = document.createElement('li');
      li.className = 'dashboard-list-empty';
      li.innerHTML = '<p class="primary">No duplicates flagged.</p><p class="secondary">CRM syncing will surface potential merge candidates here.</p>';
      duplicateList.appendChild(li);
      return;
    }
    state.dataQuality.duplicates.forEach((item) => {
      const li = document.createElement('li');
      li.className = 'dashboard-list-item';
      li.dataset.duplicateId = String(item.id);
      li.innerHTML = `
        <div>
          <p class="primary">${escapeHtml(item.primary)} ↔ ${escapeHtml(item.duplicate)}</p>
          <p class="secondary">${escapeHtml(item.reason || 'Potential duplicate')}</p>
        </div>
        <div class="dashboard-list-actions">
          <button type="button" class="btn btn-xs btn-secondary" data-action="merge-duplicate" data-duplicate-id="${item.id}">Merge</button>
          <button type="button" class="btn btn-xs btn-ghost" data-action="dismiss-duplicate" data-duplicate-id="${item.id}">Dismiss</button>
        </div>
      `;
      duplicateList.appendChild(li);
    });
  }

  function renderApprovals() {
    if (!approvalList) return;
    approvalList.innerHTML = '';
    if (!state.dataQuality.approvals.length) {
      const li = document.createElement('li');
      li.className = 'dashboard-list-empty';
      li.innerHTML = '<p class="primary">No pending approvals.</p><p class="secondary">Employee-initiated updates will require sign-off before publishing.</p>';
      approvalList.appendChild(li);
      return;
    }
    state.dataQuality.approvals.forEach((item) => {
      const li = document.createElement('li');
      li.className = 'dashboard-list-item';
      li.dataset.approvalId = String(item.id);
      li.innerHTML = `
        <div>
          <p class="primary">${escapeHtml(item.employee)}</p>
          <p class="secondary">${escapeHtml(item.change)}</p>
        </div>
        <div class="dashboard-list-actions">
          <button type="button" class="btn btn-xs btn-secondary" data-action="approve-change" data-approval-id="${item.id}">Approve</button>
          <button type="button" class="btn btn-xs btn-ghost" data-action="reject-change" data-approval-id="${item.id}">Reject</button>
        </div>
      `;
      approvalList.appendChild(li);
    });
  }

  function renderCRM() {
    renderLeads();
    renderCustomers();
  }

  function renderLeads() {
    if (!leadTableBody) return;
    leadTableBody.innerHTML = '';
    if (!state.crm.leads.length) {
      const row = document.createElement('tr');
      row.className = 'dashboard-empty-row';
      row.innerHTML = '<td colspan="5">No leads recorded.</td>';
      leadTableBody.appendChild(row);
    } else {
      state.crm.leads.forEach((lead) => {
        const row = document.createElement('tr');
        row.dataset.leadId = String(lead.id);
        row.innerHTML = `
          <td><strong>${escapeHtml(lead.name)}</strong><br /><small>${escapeHtml(lead.phone || '')}</small></td>
          <td>${escapeHtml(lead.source || 'Web')}</td>
          <td>${escapeHtml(lead.interest || 'Rooftop')}</td>
          <td><span class="badge badge-soft">${escapeHtml(capitalize(lead.status || 'new'))}</span></td>
          <td>
            <button type="button" class="btn btn-xs btn-secondary" data-action="convert-lead" data-lead-id="${lead.id}">Convert</button>
          </td>
        `;
        leadTableBody.appendChild(row);
      });
    }

    if (leadCountInput) {
      leadCountInput.value = String(state.crm.leads.length);
    }
    if (leadConversionInput) {
      const total = state.crm.leads.length + state.crm.customers.length;
      const rate = total ? Math.round((state.crm.customers.length / total) * 100) : 0;
      leadConversionInput.value = `${rate}%`;
    }
  }

  function renderCustomers() {
    if (!customerTableBody) return;
    customerTableBody.innerHTML = '';
    if (!state.crm.customers.length) {
      const row = document.createElement('tr');
      row.className = 'dashboard-empty-row';
      row.innerHTML = '<td colspan="5">No PM Surya Ghar customers.</td>';
      customerTableBody.appendChild(row);
    } else {
      state.crm.customers.forEach((customer) => {
        const row = document.createElement('tr');
        row.dataset.customerId = String(customer.id);
        row.innerHTML = `
          <td><strong>${escapeHtml(customer.name)}</strong><br /><small>${escapeHtml(customer.idNumber || '')}</small></td>
          <td>${escapeHtml(customer.systemSize || '—')}</td>
          <td>${escapeHtml(customer.installationDate || 'Not scheduled')}</td>
          <td>${escapeHtml(customer.leadReference || '—')}</td>
          <td>
            <button type="button" class="btn btn-xs btn-ghost" data-action="schedule-installation" data-customer-id="${customer.id}">Schedule</button>
          </td>
        `;
        customerTableBody.appendChild(row);
      });
    }

    if (customerCountInput) {
      customerCountInput.value = String(state.crm.customers.length);
    }
    if (installationCountInput) {
      const upcoming = state.crm.customers.filter((customer) => customer.installationDate && new Date(customer.installationDate) > new Date()).length;
      installationCountInput.value = String(upcoming);
    }
  }

  function renderReferrers() {
    if (!referrerTableBody) return;
    referrerTableBody.innerHTML = '';
    if (!state.referrers.length) {
      const row = document.createElement('tr');
      row.className = 'dashboard-empty-row';
      row.innerHTML = '<td colspan="6">No partners registered.</td>';
      referrerTableBody.appendChild(row);
    } else {
      state.referrers.forEach((partner) => {
        const row = document.createElement('tr');
        row.dataset.referrerId = String(partner.id);
        row.innerHTML = `
          <td><strong>${escapeHtml(partner.name)}</strong><br /><small>${escapeHtml(partner.company || '')}</small></td>
          <td><span class="badge ${partner.kycStatus === 'verified' ? 'badge-soft-success' : 'badge-soft-warning'}">${escapeHtml(capitalize(partner.kycStatus || 'pending'))}</span></td>
          <td>${partner.leads}</td>
          <td>${partner.conversions}</td>
          <td>${escapeHtml(partner.lastPayout || '—')}</td>
          <td>
            <button type="button" class="btn btn-xs btn-secondary" data-action="toggle-kyc" data-referrer-id="${partner.id}">${partner.kycStatus === 'verified' ? 'Re-check KYC' : 'Verify KYC'}</button>
          </td>
        `;
        referrerTableBody.appendChild(row);
      });
    }

    if (referrerVerifiedInput) {
      const verified = state.referrers.filter((partner) => partner.kycStatus === 'verified').length;
      referrerVerifiedInput.value = String(verified);
    }
    if (referrerLeadsInput) {
      const totalLeads = state.referrers.reduce((acc, partner) => acc + (partner.leads || 0), 0);
      referrerLeadsInput.value = String(totalLeads);
    }

    if (referrerSummaryList) {
      referrerSummaryList.innerHTML = '';
      if (!state.referrers.length) {
        const li = document.createElement('li');
        li.className = 'dashboard-list-empty';
        li.innerHTML = '<p class="primary">No partner activity logged.</p><p class="secondary">Approved referrers and installers will surface key stats here.</p>';
        referrerSummaryList.appendChild(li);
      } else {
        const topConversion = [...state.referrers].sort((a, b) => (b.conversions || 0) - (a.conversions || 0))[0];
        const topLead = [...state.referrers].sort((a, b) => (b.leads || 0) - (a.leads || 0))[0];
        if (topConversion) {
          const li = document.createElement('li');
          li.className = 'dashboard-list-item';
          li.innerHTML = `<div><p class="primary">Top converter</p><p class="secondary">${escapeHtml(topConversion.name)} · ${topConversion.conversions} conversions</p></div>`;
          referrerSummaryList.appendChild(li);
        }
        if (topLead && topLead !== topConversion) {
          const li = document.createElement('li');
          li.className = 'dashboard-list-item';
          li.innerHTML = `<div><p class="primary">Lead volume leader</p><p class="secondary">${escapeHtml(topLead.name)} · ${topLead.leads} leads</p></div>`;
          referrerSummaryList.appendChild(li);
        }
        state.referrers
          .filter((partner) => partner.alert)
          .forEach((partner) => {
            const li = document.createElement('li');
            li.className = 'dashboard-list-item';
            li.innerHTML = `<div><p class="primary">Attention</p><p class="secondary">${escapeHtml(partner.alert)}</p></div>`;
            referrerSummaryList.appendChild(li);
          });
      }
    }
  }

  function recalcSubsidyStats() {
    const stageCounts = SUBSIDY_STAGE_SEQUENCE.reduce((acc, stage) => ({ ...acc, [stage]: 0 }), {});
    state.subsidy.applications.forEach((app) => {
      const stage = SUBSIDY_STAGE_SEQUENCE.includes(app.stage) ? app.stage : 'applied';
      stageCounts[stage] += 1;
    });
    const durations = state.subsidy.applications
      .map((app) => Number(app.processingDays))
      .filter((value) => Number.isFinite(value));
    const average = durations.length ? Math.round(durations.reduce((sum, value) => sum + value, 0) / durations.length) : 0;
    state.subsidy.stats = { stageCounts, averageDays: average };
  }

  function renderSubsidy() {
    recalcSubsidyStats();
    subsidyStageNodes.forEach((node) => {
      const stage = node.dataset.subsidyStage;
      node.textContent = String(state.subsidy.stats.stageCounts[stage] || 0);
    });
    if (subsidyAverageInput) {
      subsidyAverageInput.value = `${state.subsidy.stats.averageDays} days`;
    }
    if (!subsidyTableBody) return;
    subsidyTableBody.innerHTML = '';
    if (!state.subsidy.applications.length) {
      const row = document.createElement('tr');
      row.className = 'dashboard-empty-row';
      row.innerHTML = '<td colspan="6">No subsidy applications tracked yet.</td>';
      subsidyTableBody.appendChild(row);
      return;
    }

    state.subsidy.applications
      .slice()
      .sort((a, b) => (SUBSIDY_STAGE_SEQUENCE.indexOf(a.stage) - SUBSIDY_STAGE_SEQUENCE.indexOf(b.stage)))
      .forEach((app) => {
        const nextStage = SUBSIDY_STAGE_SEQUENCE[SUBSIDY_STAGE_SEQUENCE.indexOf(app.stage) + 1];
        const row = document.createElement('tr');
        row.dataset.subsidyId = String(app.id);
        row.innerHTML = `
          <td><strong>${escapeHtml(app.reference)}</strong></td>
          <td>${escapeHtml(app.customer)}</td>
          <td>${escapeHtml(app.capacity)}</td>
          <td><span class="badge badge-soft">${escapeHtml(capitalize(app.stage))}</span></td>
          <td>${escapeHtml(formatDate(app.updatedAt))}</td>
          <td>
            ${
              nextStage
                ? `<button type="button" class="btn btn-xs btn-secondary" data-action="advance-subsidy" data-subsidy-id="${app.id}" data-next-stage="${nextStage}">Move to ${escapeHtml(capitalize(nextStage))}</button>`
                : `<button type="button" class="btn btn-xs btn-ghost" data-action="archive-subsidy" data-subsidy-id="${app.id}">Archive</button>`
            }
          </td>
        `;
        subsidyTableBody.appendChild(row);
      });
  }

  taskForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    const formData = new FormData(taskForm);
    const assigneeId = formData.get('assignee');
    if (!assigneeId) {
      showToast('Task incomplete', 'Select a team member before saving.', 'warning');
      return;
    }
    const title = (formData.get('title') || '').toString().trim();
    if (!title) {
      showToast('Task incomplete', 'Provide a task title before saving.', 'warning');
      return;
    }
    const task = {
      id: Date.now(),
      title,
      assigneeId: assigneeId.toString(),
      status: (formData.get('status') || 'todo').toString(),
      dueDate: formData.get('dueDate') || '',
      priority: (formData.get('priority') || 'medium').toString(),
      linkedTo: (formData.get('linkedTo') || '').toString(),
      notes: (formData.get('notes') || '').toString(),
      createdAt: new Date().toISOString(),
    };
    state.tasks.items.unshift(task);
    renderTasks();
    showToast('Task created', 'The assignment has been added to the board.', 'success');
    taskForm.reset();
    populateTaskAssignees();
  });

  taskForm?.addEventListener('reset', () => {
    populateTaskAssignees();
  });

  taskBoard?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-action]');
    if (!button) return;
    const taskId = button.dataset.taskId;
    if (!taskId) return;
    const task = state.tasks.items.find((item) => String(item.id) === String(taskId));
    if (!task) return;
    if (button.dataset.action === 'advance-task') {
      const nextStatus = button.dataset.nextStatus;
      if (!nextStatus) return;
      task.status = nextStatus;
      if (nextStatus === 'done') {
        task.completedAt = new Date().toISOString();
      }
      renderTasks();
      showToast('Task updated', `Moved to ${TASK_STATUS_LABELS[nextStatus]}.`, 'success');
    } else if (button.dataset.action === 'reset-task') {
      task.status = 'todo';
      renderTasks();
      showToast('Task reopened', 'Task moved back to To Do.', 'info');
    }
  });

  documentForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    const formData = new FormData(documentForm);
    const name = (formData.get('name') || '').toString().trim();
    const linkedTo = (formData.get('linkedTo') || '').toString();
    if (!name || !linkedTo) {
      showToast('Missing information', 'Document name and linked record are required.', 'warning');
      return;
    }
    const reference = (formData.get('reference') || '').toString();
    const existingIndex = state.documents.findIndex(
      (doc) => doc.name.toLowerCase() === name.toLowerCase() && (doc.reference || '') === reference
    );
    const tags = (formData.get('tags') || '')
      .toString()
      .split(',')
      .map((tag) => tag.trim())
      .filter(Boolean);
    const updatedAt = new Date().toISOString();
    if (existingIndex >= 0) {
      const existing = state.documents[existingIndex];
      state.documents[existingIndex] = {
        ...existing,
        linkedTo,
        reference,
        tags,
        url: (formData.get('url') || '').toString(),
        version: (existing.version || 1) + 1,
        updatedAt,
        uploadedBy: config.currentUser?.name || 'Administrator',
      };
      showToast('Version updated', `Created version ${state.documents[existingIndex].version} of ${name}.`, 'success');
    } else {
      state.documents.unshift({
        id: Date.now(),
        name,
        linkedTo,
        reference,
        tags,
        url: (formData.get('url') || '').toString(),
        version: 1,
        updatedAt,
        uploadedBy: config.currentUser?.name || 'Administrator',
      });
      showToast('Document saved', 'File metadata stored in the vault.', 'success');
    }
    documentForm.reset();
    renderDocuments();
  });

  documentFilter?.addEventListener('change', () => renderDocuments());

  duplicateList?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-action]');
    if (!button) return;
    const duplicateId = Number(button.dataset.duplicateId);
    if (!duplicateId) return;
    if (button.dataset.action === 'merge-duplicate') {
      state.dataQuality.duplicates = state.dataQuality.duplicates.filter((item) => item.id !== duplicateId);
      renderDuplicates();
      showToast('Records merged', 'Duplicate records merged successfully.', 'success');
    } else if (button.dataset.action === 'dismiss-duplicate') {
      state.dataQuality.duplicates = state.dataQuality.duplicates.filter((item) => item.id !== duplicateId);
      renderDuplicates();
      showToast('Duplicate dismissed', 'The potential duplicate was dismissed.', 'info');
    }
  });

  approvalList?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-action]');
    if (!button) return;
    const approvalId = Number(button.dataset.approvalId);
    if (!approvalId) return;
    if (button.dataset.action === 'approve-change') {
      state.dataQuality.approvals = state.dataQuality.approvals.filter((item) => item.id !== approvalId);
      renderApprovals();
      showToast('Change approved', 'Updates will be synced to the master record.', 'success');
    } else if (button.dataset.action === 'reject-change') {
      state.dataQuality.approvals = state.dataQuality.approvals.filter((item) => item.id !== approvalId);
      renderApprovals();
      showToast('Change rejected', 'The proposed update has been rejected.', 'info');
    }
  });

  leadTableBody?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-action="convert-lead"]');
    if (!button) return;
    const leadId = Number(button.dataset.leadId);
    const lead = state.crm.leads.find((item) => Number(item.id) === leadId);
    if (!lead) return;
    state.crm.leads = state.crm.leads.filter((item) => Number(item.id) !== leadId);
    const customer = {
      id: `C-${Date.now()}`,
      name: lead.name,
      idNumber: lead.customerId || `#C-${leadId}`,
      systemSize: lead.systemSize || lead.interest || '—',
      installationDate: lead.installationDate || '',
      leadReference: lead.reference || lead.leadNumber || `Lead #${leadId}`,
    };
    state.crm.customers.unshift(customer);
    renderCRM();
    showToast('Lead converted', `${lead.name} is now a customer under PM Surya Ghar.`, 'success');
  });

  customerTableBody?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-action="schedule-installation"]');
    if (!button) return;
    const customerId = button.dataset.customerId;
    const customer = state.crm.customers.find((item) => String(item.id) === String(customerId));
    if (!customer) return;
    const date = prompt('Enter installation date (YYYY-MM-DD)', customer.installationDate || '');
    if (!date) {
      showToast('Schedule skipped', 'Installation date was not updated.', 'info');
      return;
    }
    customer.installationDate = date;
    renderCustomers();
    showToast('Installation scheduled', `Installation planned for ${date}.`, 'success');
  });

  referrerTableBody?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-action="toggle-kyc"]');
    if (!button) return;
    const referrerId = Number(button.dataset.referrerId);
    const referrer = state.referrers.find((item) => Number(item.id) === referrerId);
    if (!referrer) return;
    referrer.kycStatus = referrer.kycStatus === 'verified' ? 'pending' : 'verified';
    if (referrer.kycStatus === 'verified') {
      referrer.lastPayout = referrer.lastPayout || new Date().toLocaleDateString();
    }
    renderReferrers();
    showToast('Partner updated', `${referrer.name} marked as ${referrer.kycStatus}.`, 'success');
  });

  subsidyTableBody?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-action]');
    if (!button) return;
    const subsidyId = Number(button.dataset.subsidyId);
    const application = state.subsidy.applications.find((item) => Number(item.id) === subsidyId);
    if (!application) return;
    if (button.dataset.action === 'advance-subsidy') {
      const nextStage = button.dataset.nextStage;
      if (!nextStage) return;
      application.stage = nextStage;
      application.updatedAt = new Date().toISOString();
      renderSubsidy();
      showToast('Stage updated', `Application moved to ${capitalize(nextStage)}.`, 'success');
    } else if (button.dataset.action === 'archive-subsidy') {
      state.subsidy.applications = state.subsidy.applications.filter((item) => Number(item.id) !== subsidyId);
      renderSubsidy();
      showToast('Application archived', 'Closed subsidy application archived from view.', 'info');
    }
  });

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

    state.tasks.items.forEach((task) => {
      const haystack = `${task.title} ${task.notes || ''} ${task.linkedTo || ''}`.toLowerCase();
      if (haystack.includes(lower)) {
        matches.push({
          type: 'Task',
          title: task.title,
          subtitle: `${TASK_STATUS_LABELS[task.status] || 'To Do'} · ${findTeamMember(task.assigneeId)?.name || 'Unassigned'}`,
          action: () => activateTab('tasks'),
        });
      }
    });

    state.documents.forEach((doc) => {
      const haystack = `${doc.name} ${(doc.tags || []).join(' ')} ${doc.reference || ''}`.toLowerCase();
      if (haystack.includes(lower)) {
        matches.push({
          type: 'Document',
          title: doc.name,
          subtitle: `${capitalize(doc.linkedTo || '')} · v${doc.version || 1}`,
          action: () => activateTab('documents'),
        });
      }
    });

    state.dataQuality.duplicates.forEach((item) => {
      const haystack = `${item.primary} ${item.duplicate} ${item.reason || ''}`.toLowerCase();
      if (haystack.includes(lower)) {
        matches.push({
          type: 'Duplicate',
          title: `${item.primary} ↔ ${item.duplicate}`,
          subtitle: item.reason || 'Potential duplicate',
          action: () => activateTab('data-quality'),
        });
      }
    });

    state.dataQuality.approvals.forEach((item) => {
      const haystack = `${item.employee} ${item.change}`.toLowerCase();
      if (haystack.includes(lower)) {
        matches.push({
          type: 'Approval',
          title: item.employee,
          subtitle: item.change,
          action: () => activateTab('data-quality'),
        });
      }
    });

    state.crm.leads.forEach((lead) => {
      const haystack = `${lead.name} ${lead.reference || ''} ${lead.interest || ''}`.toLowerCase();
      if (haystack.includes(lower)) {
        matches.push({
          type: 'Lead',
          title: lead.name,
          subtitle: `${lead.source || 'Unknown source'} · ${lead.interest || ''}`,
          action: () => activateTab('crm'),
        });
      }
    });

    state.crm.customers.forEach((customer) => {
      const haystack = `${customer.name} ${customer.leadReference || ''}`.toLowerCase();
      if (haystack.includes(lower)) {
        matches.push({
          type: 'Customer',
          title: customer.name,
          subtitle: `${customer.systemSize || ''} · ${customer.installationDate || 'Schedule pending'}`,
          action: () => activateTab('crm'),
        });
      }
    });

    state.referrers.forEach((partner) => {
      const haystack = `${partner.name} ${partner.company || ''}`.toLowerCase();
      if (haystack.includes(lower)) {
        matches.push({
          type: 'Referrer',
          title: partner.name,
          subtitle: `${partner.leads} leads · ${partner.conversions} conversions`,
          action: () => activateTab('referrers'),
        });
      }
    });

    state.subsidy.applications.forEach((app) => {
      const haystack = `${app.reference} ${app.customer}`.toLowerCase();
      if (haystack.includes(lower)) {
        matches.push({
          type: 'Subsidy',
          title: app.reference,
          subtitle: `${app.customer} · ${capitalize(app.stage)}`,
          action: () => activateTab('subsidy'),
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
    populateTaskAssignees();
    renderTasks();
    renderDocuments();
    renderValidations();
    renderDuplicates();
    renderApprovals();
    renderCRM();
    renderReferrers();
    renderSubsidy();
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
        if (data.tasks) state.tasks = data.tasks;
        if (data.documents) state.documents = data.documents;
        if (data.dataQuality) state.dataQuality = data.dataQuality;
        if (data.crm) state.crm = data.crm;
        if (data.referrers) state.referrers = data.referrers;
        if (data.subsidy) state.subsidy = data.subsidy;
        updateMetricCards(state.metrics.counts);
        updateSystemHealth(state.metrics.system);
        populateLoginPolicy();
        populateGemini();
        populateTaskAssignees();
        renderUsers();
        renderInvitations();
        renderComplaints();
        renderAudit();
        renderTasks();
        renderDocuments();
        renderValidations();
        renderDuplicates();
        renderApprovals();
        renderCRM();
        renderReferrers();
        renderSubsidy();
      })
      .catch((error) => {
        showToast('Failed to load admin data', error.message, 'error');
        hydrateFromConfig();
        renderUsers();
        renderInvitations();
        renderComplaints();
        renderAudit();
      });
  }

  hydrateFromConfig();
  loadBootstrap();
})();
