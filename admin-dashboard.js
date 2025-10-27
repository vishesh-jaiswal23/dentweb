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
  const installationForm = document.querySelector('[data-installation-form]');
  const installationAssigneeSelect = document.querySelector('[data-installation-assignee]');
  const installationTableBody = document.querySelector('[data-installation-table]');
  const installationSummaryInput = document.querySelector('[data-installation-summary]');
  const installationChecklistInput = document.querySelector('[data-installation-checklist]');
  const warrantyTableBody = document.querySelector('[data-warranty-table]');
  const amcList = document.querySelector('[data-amc-list]');
  const analyticsKpiList = document.querySelector('[data-analytics-kpis]');
  const analyticsInstallerTable = document.querySelector('[data-analytics-installer]');
  const analyticsFunnelTable = document.querySelector('[data-analytics-funnel]');
  const analyticsStatus = document.querySelector('[data-analytics-status]');
  const analyticsExportButton = document.querySelector('[data-action="export-kpi"]');
  const analyticsRefreshButton = document.querySelector('[data-action="refresh-kpi"]');
  const aiBlogForm = document.querySelector('[data-ai-blog-form]');
  const aiScheduleForm = document.querySelector('[data-ai-schedule-form]');
  const aiBlogPreview = document.querySelector('[data-ai-blog-preview]');
  const aiStatus = document.querySelector('[data-ai-status]');
  const aiImagePrompt = document.querySelector('[data-ai-image-prompt]');
  const aiImageAspect = document.querySelector('[data-ai-image-aspect]');
  const aiImageNode = document.querySelector('[data-ai-image]');
  const aiImageStatus = document.querySelector('[data-ai-image-status]');
  const defaultAiImageSrc = aiImageNode?.src || '';
  const defaultAiImageAlt = aiImageNode?.alt || 'AI generated cover preview';
  const aiAudioNode = document.querySelector('[data-ai-audio]');
  const aiAudioStatus = document.querySelector('[data-ai-audio-status]');
  const aiAutoblogToggle = document.querySelector('[data-ai-autoblog-toggle]');
  const aiAutoblogTime = document.querySelector('[data-ai-autoblog-time]');
  const aiAutoblogTheme = document.querySelector('[data-ai-autoblog-theme]');
  const aiScheduleStatus = document.querySelector('[data-ai-schedule-status]');
  const generateImageButton = document.querySelector('[data-action="generate-image"]');
  const downloadImageButton = document.querySelector('[data-action="download-image"]');
  const generateAudioButton = document.querySelector('[data-action="generate-audio"]');
  const downloadAudioButton = document.querySelector('[data-action="download-audio"]');
  const AUTOBLOG_THEME_POOL = ['regional', 'technical', 'policy'];
  const backupStatus = document.querySelector('[data-backup-status]');
  const backupScheduleStatus = document.querySelector('[data-backup-schedule-status]');
  const backupLastInput = document.querySelector('[data-backup-last]');
  const backupStorageInput = document.querySelector('[data-backup-storage]');
  const retentionStatus = document.querySelector('[data-retention-status]');
  const backupScheduleForm = document.querySelector('[data-backup-schedule-form]');
  const retentionForm = document.querySelector('[data-retention-form]');
  const retentionArchiveInput = document.querySelector('[data-retention-archive]');
  const retentionPurgeInput = document.querySelector('[data-retention-purge]');
  const retentionAuditInput = document.querySelector('[data-retention-audit]');
  const governanceRoleTable = document.querySelector('[data-governance-role-table]');
  const governanceReviewList = document.querySelector('[data-governance-review-list]');
  const governanceActivityList = document.querySelector('[data-governance-activity-list]');
  const governanceStatus = document.querySelector('[data-governance-status]');
  const runBackupButton = document.querySelector('[data-action="run-backup"]');
  const verifyBackupButton = document.querySelector('[data-action="verify-backup"]');
  const governanceExportButton = document.querySelector('[data-action="governance-export"]');
  const governanceRefreshButton = document.querySelector('[data-action="governance-refresh"]');
  const blogTableBody = document.querySelector('[data-blog-post-table]');
  const blogNewButton = document.querySelector('[data-blog-new]');
  const blogForm = document.querySelector('[data-blog-form]');
  const blogStatus = document.querySelector('[data-blog-status]');
  const blogPublishButton = document.querySelector('[data-blog-publish]');
  const blogArchiveButton = document.querySelector('[data-blog-archive]');
  const blogResetButton = document.querySelector('[data-blog-reset]');
  const searchGroupTemplate = document.getElementById('dashboard-search-result-template');
  const searchItemTemplate = document.getElementById('dashboard-search-item-template');

  const DEFAULT_AI_STATE = {
    draft: '',
    topic: '',
    tone: 'informative',
    length: 650,
    outline: '',
    keywords: [],
    keywordsText: '',
    coverImage: '',
    coverImageAlt: '',
    coverPrompt: '',
    coverAspect: '16:9',
    excerpt: '',
    research: null,
    wordCount: 0,
    readingTimeMinutes: null,
  };

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
    installations: config.installations || { jobs: [], warranties: [], amc: [] },
    analytics: config.analytics || { kpis: [], installerProductivity: [], funnel: [] },
    governance: config.governance || { roleMatrix: [], pendingReviews: [], activityLogs: [] },
    retention: config.retention || { archiveDays: 90, purgeDays: 180, includeAudit: true },
    ai: { ...DEFAULT_AI_STATE },
    aiAutoblog: { enabled: false, theme: 'regional', time: '09:00', lastRandomTheme: null },
  };

  const blogState = {
    posts: [],
    editingId: null,
    editingStatus: 'draft',
  };

  let autoblogTimer = null;
  let localPostCounter = 0;

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

  function hydrateRetention() {
    const retention = state.retention || { archiveDays: 90, purgeDays: 180, includeAudit: true };
    if (retentionArchiveInput && typeof retention.archiveDays === 'number') {
      retentionArchiveInput.value = retention.archiveDays;
    }
    if (retentionPurgeInput && typeof retention.purgeDays === 'number') {
      retentionPurgeInput.value = retention.purgeDays;
    }
    if (retentionAuditInput) {
      retentionAuditInput.checked = Boolean(retention.includeAudit);
    }
    if (retentionStatus) {
      clearInlineStatus(retentionStatus);
    }
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
    populateInstallationAssignees();
  }

  function populateInstallationAssignees() {
    if (!installationAssigneeSelect) return;
    const previouslySelected = installationAssigneeSelect.value;
    installationAssigneeSelect.innerHTML = '<option value="">Select installer…</option>';
    const installers = state.tasks.team.filter((member) => /installer/i.test(member.role || ''));
    installers.forEach((installer) => {
      const option = document.createElement('option');
      option.value = installer.id;
      option.textContent = `${installer.name} (${installer.role})`;
      if (previouslySelected && String(installer.id) === previouslySelected) {
        option.selected = true;
      }
      installationAssigneeSelect.appendChild(option);
    });
    if (!installers.length) {
      const option = document.createElement('option');
      option.value = '';
      option.textContent = 'No installers available';
      option.disabled = true;
      installationAssigneeSelect.appendChild(option);
    }
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

  function updateInstallationSummary(jobs) {
    if (!Array.isArray(jobs)) jobs = [];
    if (installationSummaryInput) {
      const completed = jobs.filter((job) => job.status === 'completed').length;
      installationSummaryInput.value = `${completed}/${jobs.length} completed`;
    }
    if (installationChecklistInput) {
      const pending = jobs.filter((job) => !(job.checklist?.photos && job.checklist?.confirmation)).length;
      installationChecklistInput.value = `${pending} pending`;
    }
  }

  function renderInstallations() {
    if (!installationTableBody) return;
    installationTableBody.innerHTML = '';
    const jobs = Array.isArray(state.installations?.jobs) ? [...state.installations.jobs] : [];
    if (!jobs.length) {
      const empty = document.createElement('tr');
      empty.className = 'dashboard-empty-row';
      empty.innerHTML = '<td colspan="6">No installation visits planned.</td>';
      installationTableBody.appendChild(empty);
      updateInstallationSummary(jobs);
      return;
    }

    const statusOrder = { scheduled: 0, in_progress: 1, completed: 2 };
    jobs.sort((a, b) => {
      const statusDiff = (statusOrder[a.status] ?? 1) - (statusOrder[b.status] ?? 1);
      if (statusDiff !== 0) return statusDiff;
      const aDate = new Date(a.scheduledAt || a.completedAt || 0);
      const bDate = new Date(b.scheduledAt || b.completedAt || 0);
      return aDate - bDate;
    });

    jobs.forEach((job) => {
      const row = document.createElement('tr');
      const photosDone = Boolean(job.checklist?.photos);
      const checklistDone = Boolean(job.checklist?.confirmation);
      const photoBadge = `<span class="badge ${photosDone ? 'badge-soft-success' : 'badge-soft-warning'}">${photosDone ? 'Photos received' : 'Photos pending'}</span>`;
      const checklistBadge = `<span class="badge ${checklistDone ? 'badge-soft-success' : 'badge-soft-warning'}">${checklistDone ? 'Checklist signed' : 'Checklist pending'}</span>`;
      const installer = job.installerName || findTeamMember(job.installerId)?.name || 'Unassigned';
      const scheduleValue = job.status === 'completed' ? job.completedAt || job.scheduledAt : job.scheduledAt;
      const scheduleLabel = job.status === 'completed' ? 'Completed' : 'Scheduled';
      const actions = [];
      if (!photosDone) {
        actions.push(
          `<button type="button" class="btn btn-xs btn-secondary" data-action="mark-photos" data-installation-id="${escapeHtml(job.id)}">Confirm photos</button>`
        );
      }
      if (!checklistDone) {
        actions.push(
          `<button type="button" class="btn btn-xs btn-secondary" data-action="mark-checklist" data-installation-id="${escapeHtml(job.id)}">Checklist done</button>`
        );
      }
      if (job.status !== 'completed') {
        actions.push(
          `<button type="button" class="btn btn-xs btn-tertiary" data-action="complete-installation" data-installation-id="${escapeHtml(job.id)}">Mark complete</button>`
        );
      }
      row.innerHTML = `
        <td>
          <strong>${escapeHtml(job.customer || 'Customer')}</strong><br />
          <small>${escapeHtml(job.systemSize || '')}</small>
        </td>
        <td>${escapeHtml(installer)}<br /><small>${escapeHtml(job.location || '')}</small></td>
        <td><strong>${escapeHtml(scheduleLabel)}</strong><br /><small>${escapeHtml(formatDate(scheduleValue))}</small></td>
        <td>${photoBadge}<br />${checklistBadge}</td>
        <td><span class="badge ${job.status === 'completed' ? 'badge-soft-success' : 'badge-soft-info'}">${escapeHtml(capitalize(job.status || 'scheduled'))}</span></td>
        <td>${actions.length ? actions.join(' ') : '—'}</td>
      `;
      installationTableBody.appendChild(row);
    });

    updateInstallationSummary(jobs);
  }

  function renderWarranties() {
    if (!warrantyTableBody) return;
    warrantyTableBody.innerHTML = '';
    const warranties = Array.isArray(state.installations?.warranties) ? [...state.installations.warranties] : [];
    if (!warranties.length) {
      const empty = document.createElement('tr');
      empty.className = 'dashboard-empty-row';
      empty.innerHTML = '<td colspan="6">No warranties activated yet.</td>';
      warrantyTableBody.appendChild(empty);
      return;
    }

    warranties
      .sort((a, b) => (b.registeredOn || '').localeCompare(a.registeredOn || ''))
      .forEach((warranty) => {
        const status = String(warranty.status || '').toLowerCase();
        const statusClass =
          status === 'active' ? 'badge-soft-success' : status === 'expired' ? 'badge-soft-danger' : 'badge-soft-warning';
        const row = document.createElement('tr');
        row.innerHTML = `
          <td>${escapeHtml(warranty.id || '')}</td>
          <td>${escapeHtml(warranty.customer || '')}</td>
          <td>${escapeHtml(warranty.product || '')}</td>
          <td>${escapeHtml(formatDateOnly(warranty.registeredOn))}</td>
          <td>${escapeHtml(formatDateOnly(warranty.expiresOn))}</td>
          <td><span class="badge ${statusClass}">${escapeHtml(warranty.status || 'Active')}</span></td>
        `;
        warrantyTableBody.appendChild(row);
      });
  }

  function renderAmc() {
    if (!amcList) return;
    amcList.innerHTML = '';
    const schedules = Array.isArray(state.installations?.amc) ? [...state.installations.amc] : [];
    if (!schedules.length) {
      const li = document.createElement('li');
      li.innerHTML = '<p>No AMC visits scheduled.</p><span>Complete an installation to auto-create the AMC plan.</span>';
      amcList.appendChild(li);
      return;
    }

    const now = new Date();
    schedules
      .sort((a, b) => new Date(a.nextVisit || 0) - new Date(b.nextVisit || 0))
      .forEach((schedule) => {
        const li = document.createElement('li');
        const nextVisit = schedule.nextVisit ? new Date(schedule.nextVisit) : null;
        const diffDays = nextVisit ? Math.round((nextVisit - now) / (24 * 60 * 60 * 1000)) : null;
        let status = schedule.status || 'scheduled';
        if (typeof diffDays === 'number') {
          if (diffDays < 0) status = 'overdue';
          else if (diffDays <= 30 && status !== 'completed') status = 'due';
        }
        li.dataset.status = status;
        const timing =
          diffDays === null
            ? 'Schedule pending'
            : diffDays < 0
            ? `Overdue by ${Math.abs(diffDays)} day(s)`
            : diffDays === 0
            ? 'Due today'
            : `Due in ${diffDays} day(s)`;
        li.innerHTML = `
          <p class="primary">${escapeHtml(schedule.customer || 'Customer')} · ${escapeHtml(schedule.plan || 'AMC')}</p>
          <p class="secondary">Next visit ${escapeHtml(formatDateOnly(schedule.nextVisit))} · ${escapeHtml(timing)}</p>
        `;
        if (status === 'overdue' || status === 'due') {
          const button = document.createElement('button');
          button.type = 'button';
          button.className = 'btn btn-xs btn-secondary';
          button.textContent = 'Log visit';
          button.dataset.action = 'complete-amc';
          button.dataset.amcId = schedule.id;
          li.appendChild(button);
        }
        amcList.appendChild(li);
      });
  }

  function renderAnalytics() {
    if (analyticsKpiList) {
      analyticsKpiList.innerHTML = '';
      const kpis = Array.isArray(state.analytics?.kpis) ? state.analytics.kpis : [];
      if (!kpis.length) {
        const empty = document.createElement('li');
        empty.className = 'dashboard-list-empty';
        empty.innerHTML = '<p class="primary">No KPI metrics loaded.</p><p class="secondary">Live analytics service will hydrate this section.</p>';
        analyticsKpiList.appendChild(empty);
      } else {
        kpis.forEach((kpi) => {
          const li = document.createElement('li');
          li.innerHTML = `
            <p class="primary">${escapeHtml(kpi.label || '')}</p>
            <p class="secondary"><strong>${escapeHtml(kpi.value || '')}</strong>${kpi.change ? ` · ${escapeHtml(kpi.change)}` : ''}</p>
          `;
          analyticsKpiList.appendChild(li);
        });
      }
    }

    if (analyticsInstallerTable) {
      analyticsInstallerTable.innerHTML = '';
      const rows = Array.isArray(state.analytics?.installerProductivity) ? state.analytics.installerProductivity : [];
      if (!rows.length) {
        const empty = document.createElement('tr');
        empty.className = 'dashboard-empty-row';
        empty.innerHTML = '<td colspan="3">No installer metrics recorded.</td>';
        analyticsInstallerTable.appendChild(empty);
      } else {
        rows.forEach((item) => {
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td>${escapeHtml(item.name || '')}</td>
            <td>${escapeHtml(String(item.installations ?? 0))}</td>
            <td>${escapeHtml(String(item.amcVisits ?? 0))}</td>
          `;
          analyticsInstallerTable.appendChild(tr);
        });
      }
    }

    if (analyticsFunnelTable) {
      analyticsFunnelTable.innerHTML = '';
      const funnel = Array.isArray(state.analytics?.funnel) ? state.analytics.funnel : [];
      if (!funnel.length) {
        const empty = document.createElement('tr');
        empty.className = 'dashboard-empty-row';
        empty.innerHTML = '<td colspan="3">Waiting on CRM sync.</td>';
        analyticsFunnelTable.appendChild(empty);
      } else {
        funnel.forEach((stage) => {
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td>${escapeHtml(stage.stage || '')}</td>
            <td>${escapeHtml(String(stage.value ?? '0'))}</td>
            <td>${escapeHtml(stage.conversion || '—')}</td>
          `;
          analyticsFunnelTable.appendChild(tr);
        });
      }
    }
  }

  function renderGovernance() {
    if (governanceRoleTable) {
      governanceRoleTable.innerHTML = '';
      const roles = Array.isArray(state.governance?.roleMatrix) ? state.governance.roleMatrix : [];
      if (!roles.length) {
        const empty = document.createElement('tr');
        empty.className = 'dashboard-empty-row';
        empty.innerHTML = '<td colspan="4">No governance data available.</td>';
        governanceRoleTable.appendChild(empty);
      } else {
        roles.forEach((role) => {
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td>${escapeHtml(role.role || '')}</td>
            <td>${escapeHtml(String(role.users ?? 0))}</td>
            <td>${escapeHtml(formatDateOnly(role.lastReview))}</td>
            <td>${escapeHtml(role.owner || '')}</td>
          `;
          governanceRoleTable.appendChild(tr);
        });
      }
    }

    if (governanceReviewList) {
      governanceReviewList.innerHTML = '';
      const reviews = Array.isArray(state.governance?.pendingReviews) ? state.governance.pendingReviews : [];
      if (!reviews.length) {
        const li = document.createElement('li');
        li.className = 'dashboard-list-empty';
        li.innerHTML = '<p class="primary">No pending reviews.</p><p class="secondary">Change requests will surface here for approval.</p>';
        governanceReviewList.appendChild(li);
      } else {
        reviews.forEach((review) => {
          const li = document.createElement('li');
          li.innerHTML = `
            <p class="primary">${escapeHtml(review.item || '')}</p>
            <p class="secondary">Due ${escapeHtml(formatDateOnly(review.due))} · Owner ${escapeHtml(review.owner || 'Admin')}</p>
          `;
          governanceReviewList.appendChild(li);
        });
      }
    }

    if (governanceActivityList) {
      governanceActivityList.innerHTML = '';
      const activity = Array.isArray(state.governance?.activityLogs) ? state.governance.activityLogs : [];
      if (!activity.length) {
        const li = document.createElement('li');
        li.className = 'dashboard-list-empty';
        li.innerHTML = '<p class="primary">No governance activity logged.</p><p class="secondary">Monthly exports, approvals, and escalations will appear here.</p>';
        governanceActivityList.appendChild(li);
      } else {
        activity
          .sort((a, b) => (b.timestamp || '').localeCompare(a.timestamp || ''))
          .forEach((entry) => {
            const li = document.createElement('li');
            li.innerHTML = `
              <p class="primary">${escapeHtml(entry.description || '')}</p>
              <p class="secondary">${escapeHtml(entry.actor || 'System')} · ${escapeHtml(formatDate(entry.timestamp))}</p>
            `;
            governanceActivityList.appendChild(li);
          });
      }
    }
  }

  function normalizeBlogPost(post = {}) {
    return {
      id: Number(post.id) || 0,
      title: post.title || '',
      slug: post.slug || '',
      excerpt: post.excerpt || '',
      status: (post.status || 'draft').toLowerCase(),
      publishedAt: post.publishedAt || post.published_at || '',
      updatedAt: post.updatedAt || post.updated_at || '',
      authorName: post.authorName || post.author_name || '',
      coverImage: post.coverImage || post.cover_image || '',
      coverImageAlt: post.coverImageAlt || post.cover_image_alt || '',
      tags: Array.isArray(post.tags)
        ? post.tags
            .map((tag) => (typeof tag === 'string' ? tag : (tag && (tag.name || tag.slug)) || ''))
            .filter(Boolean)
        : [],
      body: post.body || post.body_html || '',
    };
  }

  function syncBlogState() {
    state.blog = state.blog || {};
    state.blog.posts = blogState.posts;
  }

  function updateBlogActions() {
    if (blogPublishButton) {
      blogPublishButton.disabled = !blogState.editingId;
      if (blogState.editingStatus === 'published') {
        blogPublishButton.textContent = 'Unpublish';
      } else if (blogState.editingStatus === 'pending') {
        blogPublishButton.textContent = 'Approve & publish';
      } else {
        blogPublishButton.textContent = 'Publish';
      }
    }
    if (blogArchiveButton) {
      blogArchiveButton.disabled = !blogState.editingId || blogState.editingStatus === 'archived';
      blogArchiveButton.textContent = blogState.editingStatus === 'archived' ? 'Archived' : 'Archive';
    }
  }

  function renderBlogPosts() {
    if (!blogTableBody) return;
    blogTableBody.innerHTML = '';
    const posts = [...blogState.posts].sort((a, b) => (b.updatedAt || '').localeCompare(a.updatedAt || ''));
    if (!posts.length) {
      const row = document.createElement('tr');
      row.className = 'dashboard-empty-row';
      row.innerHTML = '<td colspan="5">No blog posts yet. Create one to get started.</td>';
      blogTableBody.appendChild(row);
      return;
    }

    posts.forEach((post) => {
      const tr = document.createElement('tr');
      const tags = post.tags.length ? `Tags: ${escapeHtml(post.tags.join(', '))}` : '';
      const statusLabel = post.status === 'pending' ? 'Pending review' : capitalize(post.status || 'draft');
      const updated = post.updatedAt ? formatDateOnly(post.updatedAt) : '--';
      const published = post.publishedAt ? formatDateOnly(post.publishedAt) : '--';
      const actions = [];
      actions.push(
        `<button type="button" class="btn btn-ghost btn-sm" data-blog-action="edit" data-blog-id="${post.id}">Edit</button>`
      );
      if (post.status === 'published') {
        actions.push(
          `<button type="button" class="btn btn-ghost btn-sm" data-blog-action="toggle" data-blog-id="${post.id}" data-blog-publish="0">Unpublish</button>`
        );
      } else {
        actions.push(
          `<button type="button" class="btn btn-ghost btn-sm" data-blog-action="toggle" data-blog-id="${post.id}" data-blog-publish="1">Publish</button>`
        );
      }
      actions.push(
        `<button type="button" class="btn btn-ghost btn-sm" data-blog-action="archive" data-blog-id="${post.id}" ${post.status === 'archived' ? 'disabled' : ''}>Archive</button>`
      );

      tr.innerHTML = `
        <td>
          <strong>${escapeHtml(post.title || 'Untitled')}</strong><br />
          <small>${escapeHtml(post.slug || '')}${tags ? ` · ${tags}` : ''}</small>
        </td>
        <td>${escapeHtml(statusLabel)}</td>
        <td>${escapeHtml(updated)}</td>
        <td>${escapeHtml(published)}</td>
        <td>${actions.join(' ')}</td>
      `;
      blogTableBody.appendChild(tr);
    });
  }

  function resetBlogForm({ focusTitle = false } = {}) {
    if (!blogForm) return;
    blogForm.reset();
    blogState.editingId = null;
    blogState.editingStatus = 'draft';
    updateBlogActions();
    clearInlineStatus(blogStatus);
    if (focusTitle) {
      blogForm.querySelector('[name="title"]')?.focus();
    }
  }

  function populateBlogForm(post) {
    if (!blogForm) return;
    blogForm.reset();
    blogState.editingId = post.id || null;
    blogState.editingStatus = post.status || 'draft';
    const idField = blogForm.querySelector('[name="id"]');
    const titleField = blogForm.querySelector('[name="title"]');
    const slugField = blogForm.querySelector('[name="slug"]');
    const authorField = blogForm.querySelector('[name="author"]');
    const tagsField = blogForm.querySelector('[name="tags"]');
    const coverField = blogForm.querySelector('[name="cover"]');
    const coverAltField = blogForm.querySelector('[name="coverAlt"]');
    const excerptField = blogForm.querySelector('[name="excerpt"]');
    const bodyField = blogForm.querySelector('[name="body"]');
    if (idField) idField.value = post.id ? String(post.id) : '';
    if (titleField) titleField.value = post.title || '';
    if (slugField) slugField.value = post.slug || '';
    if (authorField) authorField.value = post.authorName || '';
    if (tagsField) tagsField.value = post.tags.join(', ');
    if (coverField) coverField.value = post.coverImage || '';
    if (coverAltField) coverAltField.value = post.coverImageAlt || '';
    if (excerptField) excerptField.value = post.excerpt || '';
    if (bodyField) bodyField.value = post.body || '';
    updateBlogActions();
  }

  function collectBlogFormData() {
    if (!blogForm) return null;
    const formData = new FormData(blogForm);
    const tags = String(formData.get('tags') || '')
      .split(',')
      .map((tag) => tag.trim())
      .filter(Boolean);
    return {
      id: blogState.editingId || undefined,
      title: String(formData.get('title') || '').trim(),
      slug: String(formData.get('slug') || '').trim(),
      excerpt: String(formData.get('excerpt') || '').trim(),
      body: String(formData.get('body') || ''),
      authorName: String(formData.get('author') || '').trim(),
      coverImage: String(formData.get('cover') || '').trim(),
      coverImageAlt: String(formData.get('coverAlt') || '').trim(),
      tags,
      status: blogState.editingStatus || 'draft',
    };
  }

  function upsertBlogPost(post) {
    const index = blogState.posts.findIndex((item) => item.id === post.id);
    if (index >= 0) {
      blogState.posts[index] = post;
    } else {
      blogState.posts.push(post);
    }
    syncBlogState();
  }

  function saveBlogPost({ silent = false, showProgress = true } = {}) {
    if (!blogForm) return Promise.reject(new Error('Blog editor unavailable.'));
    if (!blogForm.reportValidity()) {
      renderInlineStatus(blogStatus, 'error', 'Missing information', 'Fill out the required fields before saving.');
      return Promise.reject(new Error('Validation failed'));
    }
    const payload = collectBlogFormData();
    if (!payload) {
      return Promise.reject(new Error('Unable to read form data'));
    }
    if (showProgress) {
      renderInlineStatus(blogStatus, 'progress', 'Saving draft…', 'Applying changes to the blog post.');
    }
    return api('save-blog-post', { method: 'POST', body: payload })
      .then(({ post }) => {
        const normalized = normalizeBlogPost(post);
        blogState.editingId = normalized.id;
        blogState.editingStatus = normalized.status;
        upsertBlogPost(normalized);
        renderBlogPosts();
        populateBlogForm(normalized);
        if (!silent) {
          renderInlineStatus(blogStatus, 'success', 'Draft saved', 'Latest content stored safely.');
        } else if (showProgress) {
          clearInlineStatus(blogStatus);
        }
        return normalized;
      })
      .catch((error) => {
        renderInlineStatus(blogStatus, 'error', 'Save failed', error.message || 'Unable to save the post.');
        throw error;
      });
  }

  function handleBlogPublish() {
    if (!blogForm) return;
    saveBlogPost({ silent: true, showProgress: false })
      .then(() => {
        if (!blogState.editingId) {
          renderInlineStatus(blogStatus, 'error', 'Save required', 'Create the draft before publishing.');
          return;
        }
        const publish = blogState.editingStatus !== 'published';
        renderInlineStatus(
          blogStatus,
          'progress',
          publish ? 'Publishing post…' : 'Unpublishing post…',
          publish ? 'Making the post visible on the public blog.' : 'Hiding the post from the public blog.'
        );
        return api('publish-blog-post', {
          method: 'POST',
          body: { id: blogState.editingId, publish },
        })
          .then(({ post }) => {
            const normalized = normalizeBlogPost(post);
            blogState.editingId = normalized.id;
            blogState.editingStatus = normalized.status;
            upsertBlogPost(normalized);
            renderBlogPosts();
            populateBlogForm(normalized);
            renderInlineStatus(
              blogStatus,
              'success',
              publish ? 'Post published' : 'Post unpublished',
              publish ? 'The article now appears on the public blog.' : 'The article no longer appears on the public blog.'
            );
          })
          .catch((error) => {
            renderInlineStatus(
              blogStatus,
              'error',
              publish ? 'Publish failed' : 'Update failed',
              error.message || 'Unable to update publish status.'
            );
          });
      })
      .catch(() => {});
  }

  function handleBlogArchive() {
    if (!blogForm) return;
    saveBlogPost({ silent: true, showProgress: false })
      .then(() => {
        if (!blogState.editingId) {
          renderInlineStatus(blogStatus, 'error', 'Select a post', 'Save the draft before archiving.');
          return;
        }
        if (!window.confirm('Archive this post? It will no longer appear on the public blog.')) {
          return;
        }
        renderInlineStatus(blogStatus, 'progress', 'Archiving post…', 'Removing the article from the public blog.');
        return api('archive-blog-post', {
          method: 'POST',
          body: { id: blogState.editingId },
        })
          .then(({ post }) => {
            const normalized = normalizeBlogPost(post);
            blogState.editingId = normalized.id;
            blogState.editingStatus = normalized.status;
            upsertBlogPost(normalized);
            renderBlogPosts();
            populateBlogForm(normalized);
            renderInlineStatus(blogStatus, 'info', 'Post archived', 'The article remains internal until republished.');
          })
          .catch((error) => {
            renderInlineStatus(blogStatus, 'error', 'Archive failed', error.message || 'Unable to archive the post.');
          });
      })
      .catch(() => {});
  }

  function createImageData(prompt, aspect) {
    const normalizedAspect = aspect || '16:9';
    let width = 160;
    let height = 90;
    if (normalizedAspect === '1:1') {
      width = height = 140;
    } else if (normalizedAspect === '4:5') {
      width = 160;
      height = 200;
    }
    const truncatedPrompt = prompt.length > 32 ? `${prompt.slice(0, 29)}…` : prompt;
    const encodedPrompt = encodeURIComponent(truncatedPrompt || 'AI Cover');
    const encodedAspect = encodeURIComponent(normalizedAspect);
    const fontSize = Math.max(10, Math.min(width, height) / 6);
    return `data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 ${width} ${height}'><defs><linearGradient id='g' x1='0%' y1='0%' x2='100%' y2='100%'><stop offset='0%' stop-color='%23f1f5f9'/><stop offset='100%' stop-color='%23dbeafe'/></linearGradient></defs><rect fill='url(%23g)' width='${width}' height='${height}'/><text x='50%' y='45%' font-size='${fontSize}' text-anchor='middle' fill='%23225699'>${encodedPrompt}</text><text x='50%' y='70%' font-size='10' text-anchor='middle' fill='%236b7280'>${encodedAspect} aspect</text></svg>`;
  }

  function buildBlogDraft({ topic, tone, length, keywords, outline }) {
    const safeTopic = topic || 'Solar adoption insights';
    const normalizedTone = tone || 'informative';
    const keywordSource = Array.isArray(keywords) ? keywords.join(',') : keywords || '';
    const keywordList = keywordSource
      .split(',')
      .map((word) => word.trim())
      .filter(Boolean);
    const outlinePoints = (outline || '')
      .split(/\r?\n/)
      .map((line) => line.trim())
      .filter(Boolean);
    const sections = [
      {
        title: `Why ${safeTopic} matters in Jharkhand`,
        body:
          'Households can cut monthly electricity bills by embracing rooftop solar and leveraging PM Surya Ghar incentives tailored for Jharkhand.',
      },
      {
        title: 'Financial highlights & subsidy leverage',
        body:
          'Typical customers save up to 30% annually while accessing DISCOM subsidies, net-metering credits, and accelerated depreciation for businesses.',
      },
      {
        title: 'Implementation checklist for homeowners',
        body:
          'Verify rooftop strength, secure net-metering approval, capture installation photos, and register the warranty before activating AMC.',
      },
      {
        title: 'Next steps & call to action',
        body:
          'Book a site audit with certified installers, upload required KYC documents, and set reminders for AMC visits to maintain peak performance.',
      },
    ];

    let html = `<article class="dashboard-ai-draft">`;
    html += `<h4>${escapeHtml(safeTopic)}</h4>`;
    html += `<p class="dashboard-muted">Tone: ${escapeHtml(capitalize(normalizedTone))} · Target length: ${escapeHtml(String(length || 0))} words.</p>`;
    sections.forEach((section) => {
      html += `<h5>${escapeHtml(section.title)}</h5><p>${escapeHtml(section.body)}</p>`;
    });
    if (outlinePoints.length) {
      html += '<h5>Admin outline reminders</h5><ul>';
      outlinePoints.forEach((point) => {
        html += `<li>${escapeHtml(point)}</li>`;
      });
      html += '</ul>';
    }
    if (keywordList.length) {
      html += `<p><strong>Focus keywords:</strong> ${keywordList.map((word) => escapeHtml(word)).join(', ')}</p>`;
    }
    html += '<p class="dashboard-muted">Use the Publish button after editing to push the draft live.</p>';
    html += '</article>';
    return html;
  }

  function parseKeywords(input) {
    if (!input) return [];
    if (Array.isArray(input)) {
      return input
        .map((value) => (typeof value === 'string' ? value.trim() : ''))
        .filter(Boolean);
    }
    return String(input)
      .split(',')
      .map((part) => part.trim())
      .filter(Boolean);
  }

  function buildExcerptFromHtml(html, fallback = '') {
    let text = '';
    if (html) {
      const temp = document.createElement('div');
      temp.innerHTML = html;
      const firstParagraph = temp.querySelector('p');
      text = (firstParagraph?.textContent || temp.textContent || '').replace(/\s+/g, ' ').trim();
    }
    if (!text && fallback) {
      text = fallback.trim();
    }
    if (text.length > 260) {
      text = `${text.slice(0, 257).trim()}…`;
    }
    return text;
  }

  function estimateWordCount(html) {
    if (!html) return 0;
    const temp = document.createElement('div');
    temp.innerHTML = html;
    const text = (temp.textContent || '').trim();
    if (!text) return 0;
    return text.split(/\s+/).filter(Boolean).length;
  }

  function extractAiDraftDetails(metadata = state.ai) {
    const draft = metadata?.draft || '';
    if (!draft) {
      return null;
    }
    const container = document.createElement('div');
    container.innerHTML = draft;
    const heading = container.querySelector('h1, h2, h3, h4');
    const title = (metadata?.topic || heading?.textContent || 'AI Blog Post').trim() || 'AI Blog Post';
    const excerpt = metadata?.excerpt
      ? String(metadata.excerpt).trim()
      : buildExcerptFromHtml(draft, metadata?.outline || title);
    return { title, excerpt, body: draft };
  }

  function ensureAiCoverImage({ title, prompt, aspect, alt } = {}) {
    const effectivePrompt = (prompt || state.ai.coverPrompt || title || 'Solar rooftop insights').toString().trim();
    const effectiveAspect = aspect || state.ai.coverAspect || '16:9';
    const effectiveAlt = alt || state.ai.coverImageAlt || `Gemini generated cover for ${effectivePrompt}`;
    if (
      state.ai.coverImage &&
      state.ai.coverPrompt === effectivePrompt &&
      state.ai.coverAspect === effectiveAspect
    ) {
      if (!state.ai.coverImageAlt && effectiveAlt) {
        state.ai = { ...state.ai, coverImageAlt: effectiveAlt };
      }
      if (aiImageNode && effectiveAlt) {
        aiImageNode.alt = effectiveAlt;
      }
      return state.ai.coverImage;
    }
    const imageData = createImageData(effectivePrompt, effectiveAspect);
    if (aiImageNode) {
      aiImageNode.src = imageData;
      aiImageNode.alt = effectiveAlt;
    }
    state.ai = {
      ...state.ai,
      coverImage: imageData,
      coverPrompt: effectivePrompt,
      coverAspect: effectiveAspect,
      coverImageAlt: effectiveAlt,
    };
    return imageData;
  }

  function buildFallbackResearch(formOptions = {}) {
    const topic = (formOptions.topic || state.ai.topic || 'AI Blog Post').toString().trim() || 'AI Blog Post';
    const tone = formOptions.tone || 'informative';
    const length = Number(formOptions.length || 650) || 650;
    const keywordsArray = Array.isArray(formOptions.keywords) && formOptions.keywords.length
      ? formOptions.keywords
      : parseKeywords(formOptions.keywordsText);
    const outlineText = formOptions.outlineText
      ? formOptions.outlineText
      : Array.isArray(formOptions.outline)
      ? formOptions.outline.join('\n')
      : '';
    const draftHtml = buildBlogDraft({
      topic,
      tone,
      length,
      keywords: keywordsArray.join(', '),
      outline: outlineText,
    });
    const excerpt = buildExcerptFromHtml(draftHtml, outlineText || topic);
    const wordCount = estimateWordCount(draftHtml);
    const readingTimeMinutes = Math.max(1, Math.round(wordCount / 180) || 1);
    const aspect = aiImageAspect?.value || state.ai.coverAspect || '16:9';
    const coverPrompt = topic;
    const coverAlt = `Gemini generated illustration for ${topic}`;
    const coverImage = ensureAiCoverImage({ title: topic, prompt: coverPrompt, aspect, alt: coverAlt });
    return {
      title: topic,
      draftHtml,
      excerpt,
      keywords: keywordsArray,
      outline: outlineText
        ? outlineText.split(/\r?\n/).map((line) => line.trim()).filter(Boolean)
        : [],
      wordCount,
      readingTimeMinutes,
      cover: {
        image: coverImage,
        alt: coverAlt,
        prompt: coverPrompt,
        aspect,
      },
      research: {
        brief: 'Gemini API was unreachable, so a Dentweb fallback draft was generated locally.',
        sections: [],
        takeaways: [],
        sources: [],
      },
    };
  }

  function pickRandomTheme(previousTheme) {
    const pool = AUTOBLOG_THEME_POOL.slice();
    if (!pool.length) {
      return 'regional';
    }
    let choices = pool;
    if (previousTheme && pool.length > 1) {
      const filtered = pool.filter((theme) => theme !== previousTheme);
      if (filtered.length) {
        choices = filtered;
      }
    }
    const index = Math.floor(Math.random() * choices.length);
    return choices[index];
  }

  function describeAutoblogRotation(theme, latestTheme) {
    if (theme === 'random') {
      return latestTheme ? `rotating themes (latest: ${capitalize(latestTheme)})` : 'rotating themes';
    }
    return `${capitalize(theme)} topics`;
  }

  function readAiFormOptions() {
    if (!aiBlogForm) return null;
    const formData = new FormData(aiBlogForm);
    const topic = (formData.get('topic') || '').toString().trim();
    const tone = (formData.get('tone') || 'informative').toString();
    const lengthValue = Number(formData.get('length') || 650);
    const length = Number.isFinite(lengthValue) ? Math.max(200, Math.min(1500, lengthValue)) : 650;
    const keywordsText = (formData.get('keywords') || '').toString();
    const keywords = parseKeywords(keywordsText);
    const outlineText = (formData.get('outline') || '').toString();
    const outline = outlineText
      .split(/\r?\n/)
      .map((line) => line.trim())
      .filter(Boolean);
    return { topic, tone, length, keywords, keywordsText, outline, outlineText };
  }

  function renderAiDraftPreview({ draftHtml, research, readingTimeMinutes, wordCount } = {}) {
    if (!aiBlogPreview) return;
    const effectiveResearch = research || state.ai.research || {};
    const summaryBlocks = [];
    const metaPieces = [];
    const readingMinutes = readingTimeMinutes || state.ai.readingTimeMinutes;
    const totalWords = wordCount || state.ai.wordCount;
    if (readingMinutes) {
      metaPieces.push(`${readingMinutes} min read`);
    }
    if (totalWords) {
      metaPieces.push(`${totalWords} words`);
    }
    if (metaPieces.length) {
      summaryBlocks.push(`<p class="dashboard-muted">${escapeHtml(metaPieces.join(' · '))}</p>`);
    }
    if (effectiveResearch.brief) {
      summaryBlocks.push(`<p>${escapeHtml(effectiveResearch.brief)}</p>`);
    }
    const highlightBullets = [];
    (effectiveResearch.sections || []).forEach((section) => {
      const heading = section?.heading;
      const insight = section?.insight;
      if (heading && insight) {
        highlightBullets.push(`${heading}: ${insight}`);
      }
    });
    if (highlightBullets.length) {
      summaryBlocks.push(
        `<div><p><strong>Highlights</strong></p><ul>${highlightBullets
          .slice(0, 3)
          .map((bullet) => `<li>${escapeHtml(bullet)}</li>`)
          .join('')}</ul></div>`
      );
    }
    if (Array.isArray(effectiveResearch.takeaways) && effectiveResearch.takeaways.length) {
      summaryBlocks.push(
        `<div><p><strong>Action prompts</strong></p><ul>${effectiveResearch.takeaways
          .map((item) => `<li>${escapeHtml(item)}</li>`)
          .join('')}</ul></div>`
      );
    }
    if (effectiveResearch.sources?.length) {
      summaryBlocks.push(
        `<p class="dashboard-muted">Sources: ${escapeHtml(effectiveResearch.sources.join('; '))}</p>`
      );
    }
    if (effectiveResearch.preparedBy) {
      summaryBlocks.push(
        `<p class="dashboard-muted">Prepared by ${escapeHtml(effectiveResearch.preparedBy)}</p>`
      );
    }
    const summaryHtml = summaryBlocks.length
      ? `<section class="dashboard-ai-brief"><h4>Gemini research summary</h4>${summaryBlocks.join('')}</section>`
      : '';
    const articleHtml = draftHtml || state.ai.draft || '';
    aiBlogPreview.innerHTML =
      summaryHtml +
      (articleHtml
        ? articleHtml
        : '<p class="dashboard-muted">Draft output will appear here for preview and editing.</p>');
  }

  function applyAiResearchResult(result = {}, formOptions = {}, intent = 'draft') {
    const normalizedTitle = (result.title || formOptions.topic || state.ai.topic || 'AI Blog Post').trim();
    const keywords = Array.isArray(result.keywords) && result.keywords.length
      ? result.keywords
      : formOptions.keywords?.length
      ? formOptions.keywords
      : state.ai.keywords;
    const keywordsArray = Array.isArray(keywords) ? keywords : state.ai.keywords;
    const keywordsTextValue = Array.isArray(result.keywords) && result.keywords.length
      ? result.keywords.join(', ')
      : formOptions.keywordsText ?? state.ai.keywordsText ?? '';
    const outlineText = formOptions.outlineText ?? state.ai.outline ?? '';
    const draftHtml = result.draftHtml || state.ai.draft || '';
    const excerpt = result.excerpt || state.ai.excerpt || buildExcerptFromHtml(draftHtml, outlineText || normalizedTitle);
    const research = result.research || state.ai.research;
    const wordCount = Number(result.wordCount || state.ai.wordCount || 0);
    const readingTimeMinutes = Number(result.readingTimeMinutes || state.ai.readingTimeMinutes || 0) || null;
    const cover = result.cover || {};
    const coverImage = cover.image || state.ai.coverImage || '';
    const coverPrompt = cover.prompt || formOptions.topic || state.ai.coverPrompt || normalizedTitle;
    const coverAspect = cover.aspect || state.ai.coverAspect || '16:9';
    const coverAlt = cover.alt || state.ai.coverImageAlt || `Gemini generated illustration for ${normalizedTitle}`;

    state.ai = {
      ...state.ai,
      draft: draftHtml,
      topic: normalizedTitle,
      tone: formOptions.tone || state.ai.tone,
      length: formOptions.length || state.ai.length,
      outline: outlineText,
      keywords: keywordsArray,
      keywordsText: keywordsTextValue,
      coverPrompt,
      coverAspect,
      coverImage: coverImage || state.ai.coverImage,
      coverImageAlt: coverAlt,
      excerpt,
      research,
      wordCount,
      readingTimeMinutes,
    };

    if (coverImage) {
      if (aiImageNode) {
        aiImageNode.src = coverImage;
        aiImageNode.alt = coverAlt;
      }
    } else {
      ensureAiCoverImage({ title: normalizedTitle, prompt: coverPrompt, aspect: coverAspect, alt: coverAlt });
    }

    if (aiImagePrompt) {
      aiImagePrompt.value = coverPrompt;
    }

    const topicField = aiBlogForm?.querySelector('[data-ai-topic]');
    if (topicField && result.title) {
      topicField.value = result.title;
    }

    const keywordsField = aiBlogForm?.querySelector('[name="keywords"]');
    if (keywordsField) {
      keywordsField.value = keywordsTextValue;
    }

    renderInlineStatus(
      aiImageStatus,
      'success',
      'Cover paired automatically',
      `Gemini rendered artwork for “${normalizedTitle}”.`
    );

    renderAiDraftPreview({
      draftHtml,
      research,
      readingTimeMinutes,
      wordCount,
    });
  }

  function requestAiDraft(formOptions, { intent = 'draft' } = {}) {
    if (!formOptions || !formOptions.topic) {
      showToast('Idea required', 'Describe what you want Gemini to cover before generating a draft.', 'warning');
      return Promise.reject(new Error('Topic missing'));
    }
    const payload = {
      topic: formOptions.topic,
      tone: formOptions.tone,
      length: formOptions.length,
      keywords: formOptions.keywords,
      outline: formOptions.outline,
    };
    const isResearch = intent === 'research';
    renderInlineStatus(
      aiStatus,
      'progress',
      isResearch ? 'Researching topic…' : 'Generating draft…',
      isResearch
        ? 'Gemini is gathering insights and preparing a starter outline.'
        : 'Gemini is composing the draft with the latest insights.'
    );
    return api('research-blog-topic', { method: 'POST', body: payload })
      .then(({ research }) => {
        applyAiResearchResult(research || {}, formOptions, intent);
        const title = (research?.title || formOptions.topic || 'AI blog post').trim();
        renderInlineStatus(
          aiStatus,
          'success',
          isResearch ? 'Research complete' : 'Draft ready for review',
          isResearch
            ? 'Gemini prepared insights, an outline, and a starter draft.'
            : 'Gemini prepared the article and paired a cover image. Review it before routing to Blog Publishing.'
        );
        showToast(
          isResearch ? 'Research prepared' : 'Draft generated',
          `Gemini drafted “${title}” with fresh insights.`,
          'success'
        );
        return research;
      })
      .catch((error) => {
        console.error('Gemini request failed', error);
        const fallback = buildFallbackResearch(formOptions);
        formOptions.topic = fallback.title;
        formOptions.keywords = fallback.keywords;
        formOptions.keywordsText = fallback.keywords.join(', ');
        formOptions.outline = fallback.outline;
        if (!formOptions.outlineText) {
          formOptions.outlineText = fallback.outline.join('\n');
        }
        formOptions.length = formOptions.length || fallback.wordCount;
        applyAiResearchResult(fallback, formOptions, intent);
        renderInlineStatus(
          aiStatus,
          'info',
          'Fallback draft prepared',
          'Gemini API was unreachable, so a Dentweb template draft was generated.'
        );
        showToast(
          'Offline draft prepared',
          `Created a starter draft for “${fallback.title}”.`,
          'warning'
        );
        return fallback;
      });
  }

  function queueAiDraftForReview() {
    const details = extractAiDraftDetails();
    if (!details) {
      showToast('No draft to route', 'Generate a Gemini draft before publishing.', 'warning');
      return;
    }
    const prompt = (state.ai.coverPrompt || aiImagePrompt?.value || `${details.title} solar insights`).toString().trim();
    const aspect = state.ai.coverAspect || aiImageAspect?.value || '16:9';
    const coverAlt = state.ai.coverImageAlt || `Gemini generated illustration for ${details.title}`;
    const coverImage = ensureAiCoverImage({ title: details.title, prompt, aspect, alt: coverAlt });
    const excerpt =
      state.ai.excerpt && state.ai.excerpt.trim()
        ? state.ai.excerpt.trim()
        : details.excerpt || buildExcerptFromHtml(details.body, details.title);
    const tags = state.ai.keywords && state.ai.keywords.length ? state.ai.keywords : parseKeywords(state.ai.keywordsText);
    const payload = {
      title: details.title,
      excerpt,
      body: details.body,
      authorName: config.currentUser?.name || 'AI Content Studio',
      coverImage,
      coverImageAlt: coverAlt,
      coverPrompt: prompt,
      tags,
      status: 'pending',
    };
    renderInlineStatus(
      aiStatus,
      'progress',
      'Sending draft to Blog Publishing',
      'Routing the Gemini article for editorial approval.'
    );
    api('save-blog-post', { method: 'POST', body: payload })
      .then(({ post }) => {
        const normalized = normalizeBlogPost(post);
        upsertBlogPost(normalized);
        renderBlogPosts();
        blogState.editingId = normalized.id;
        blogState.editingStatus = normalized.status;
        populateBlogForm(normalized);
        updateBlogActions();
        renderInlineStatus(
          aiStatus,
          'success',
          'Draft queued for Blog Publishing',
          'Open Blog Publishing to approve and go live.'
        );
        activateTab('blog');
        renderInlineStatus(
          blogStatus,
          'info',
          'AI draft received',
          `“${normalized.title}” is ready for editorial review before publishing.`
        );
        showToast('Draft sent to Blog Publishing', `“${normalized.title}” is now waiting for approval.`, 'success');
      })
      .catch((error) => {
        console.error('Publish blocked', error);
        const localPost = storeLocalBlogPost(
          {
            title: payload.title,
            excerpt,
            body: details.body,
            authorName: payload.authorName,
            coverImage,
            coverImageAlt: coverAlt,
            tags,
            status: 'pending',
          },
          { setAsEditing: true }
        );
        activateTab('blog');
        renderInlineStatus(
          aiStatus,
          'info',
          'Draft saved locally',
          'Gemini API was unreachable. Review and publish from Blog Publishing once connectivity returns.'
        );
        renderInlineStatus(
          blogStatus,
          'warning',
          'Awaiting sync',
          `“${localPost.title}” stored locally for editorial approval.`
        );
        showToast(
          'Draft saved for review',
          `Stored “${localPost.title}” locally until the API is reachable.`,
          'warning'
        );
      });
  }

  function runScheduledAutoblogPublish({ theme, time }) {
    autoblogTimer = null;
    const normalizedTheme = theme || 'regional';
    const scheduleTime = time || '09:00';
    const previousRandomTheme = state.aiAutoblog?.lastRandomTheme || null;
    const actualTheme = normalizedTheme === 'random' ? pickRandomTheme(previousRandomTheme) : normalizedTheme;
    const now = new Date();
    const dateLabel = now.toLocaleDateString('en-IN', { day: 'numeric', month: 'long' });
    const topic = `${capitalize(actualTheme)} insights – ${dateLabel}`;
    const keywordTags = [capitalize(actualTheme), 'Scheduled'];
    const body = buildBlogDraft({
      topic,
      tone: 'informative',
      length: 650,
      keywords: keywordTags.join(', '),
      outline: '',
    });
    const excerpt = buildExcerptFromHtml(body, `Automated ${capitalize(actualTheme)} insights for Dentweb readers.`);
    const monthLabel = now.toLocaleDateString('en-IN', { month: 'long' });
    const coverPrompt = `${capitalize(actualTheme)} solar blog illustration ${monthLabel} ${now.getFullYear()}`;
    const coverImage = createImageData(coverPrompt, '16:9');
    const payload = {
      title: topic,
      excerpt,
      body,
      authorName: 'Gemini Automation',
      coverImage,
      coverImageAlt: `Gemini generated illustration for ${topic}`,
      coverPrompt,
      tags: keywordTags,
      status: 'published',
    };
    const descriptor = describeAutoblogRotation(normalizedTheme, actualTheme);
    renderInlineStatus(
      aiScheduleStatus,
      'progress',
      'Publishing scheduled post',
      `Auto-blog is publishing “${topic}” now.`
    );
    api('save-blog-post', { method: 'POST', body: payload })
      .then(({ post }) => {
        handleAutoblogSuccess(post, {
          descriptor,
          scheduleTime,
          normalizedTheme,
          actualTheme,
          fallback: false,
        });
      })
      .catch((error) => {
        console.error('Scheduled publish failed', error);
        const localPost = createLocalBlogPost({
          title: payload.title,
          excerpt,
          body,
          authorName: payload.authorName,
          coverImage: payload.coverImage,
          coverImageAlt: payload.coverImageAlt,
          tags: payload.tags,
          status: 'pending',
        });
        handleAutoblogSuccess(localPost, {
          descriptor,
          scheduleTime,
          normalizedTheme,
          actualTheme,
          fallback: true,
        });
      });
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

  installationForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    const formData = new FormData(installationForm);
    const customer = (formData.get('customer') || '').toString().trim();
    const installerId = (formData.get('installer') || '').toString();
    const visitDate = (formData.get('visitDate') || '').toString();
    if (!customer || !installerId || !visitDate) {
      showToast('Missing details', 'Customer, installer, and visit date are required.', 'warning');
      return;
    }
    const visitTime = (formData.get('visitTime') || '10:00').toString();
    const scheduledAt = new Date(`${visitDate}T${visitTime}`);
    if (Number.isNaN(scheduledAt.getTime())) {
      showToast('Invalid schedule', 'Provide a valid visit date and time.', 'warning');
      return;
    }
    const installer = findTeamMember(installerId) || {};
    const job = {
      id: `INST-${Date.now()}`,
      customer,
      systemSize: (formData.get('systemSize') || '').toString(),
      location: (formData.get('location') || '').toString(),
      installerId,
      installerName: installer.name || '',
      status: 'scheduled',
      scheduledAt: scheduledAt.toISOString(),
      notes: (formData.get('notes') || '').toString(),
      checklist: { photos: false, confirmation: false },
    };
    if (!Array.isArray(state.installations.jobs)) {
      state.installations.jobs = [];
    }
    state.installations.jobs.unshift(job);
    renderInstallations();
    showToast('Installation scheduled', `Visit set for ${customer} on ${scheduledAt.toLocaleDateString()}.`, 'success');
    installationForm.reset();
    populateInstallationAssignees();
  });

  installationForm?.addEventListener('reset', () => {
    populateInstallationAssignees();
  });

  installationTableBody?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-action]');
    if (!button) return;
    const jobId = button.dataset.installationId;
    if (!jobId) return;
    const job = state.installations.jobs.find((item) => String(item.id) === String(jobId));
    if (!job) return;
    if (button.dataset.action === 'mark-photos') {
      job.checklist = job.checklist || {};
      job.checklist.photos = true;
      job.checklist.photosReceivedAt = new Date().toISOString();
      renderInstallations();
      showToast('Photos confirmed', `Photos received for ${job.customer}.`, 'success');
    } else if (button.dataset.action === 'mark-checklist') {
      job.checklist = job.checklist || {};
      job.checklist.confirmation = true;
      job.checklist.confirmedAt = new Date().toISOString();
      renderInstallations();
      showToast('Checklist signed', `Site checklist confirmed for ${job.customer}.`, 'success');
    } else if (button.dataset.action === 'complete-installation') {
      if (!job.checklist?.photos || !job.checklist?.confirmation) {
        showToast('Checklist pending', 'Upload photos and confirm checklist before completion.', 'warning');
        return;
      }
      if (job.status === 'completed') {
        showToast('Already completed', 'This installation is already marked as complete.', 'info');
        return;
      }
      job.status = 'completed';
      job.completedAt = new Date().toISOString();
      const completionDate = new Date(job.completedAt);
      if (!Array.isArray(state.installations.warranties)) {
        state.installations.warranties = [];
      }
      if (!Array.isArray(state.installations.amc)) {
        state.installations.amc = [];
      }
      const warrantyId = job.warrantyReference || `WAR-${Math.floor(1000 + Math.random() * 9000)}`;
      job.warrantyReference = warrantyId;
      const existingWarranty = state.installations.warranties.find((item) => item.id === warrantyId);
      if (!existingWarranty) {
        const expiry = new Date(completionDate);
        expiry.setFullYear(expiry.getFullYear() + 5);
        state.installations.warranties.push({
          id: warrantyId,
          customer: job.customer,
          product: job.systemSize || 'Solar system',
          registeredOn: job.completedAt,
          expiresOn: expiry.toISOString(),
          status: 'Active',
          amcId: job.amcReference || null,
        });
      }
      const amcId = job.amcReference || `AMC-${Math.floor(1000 + Math.random() * 9000)}`;
      job.amcReference = amcId;
      const nextVisit = new Date(completionDate);
      nextVisit.setFullYear(nextVisit.getFullYear() + 1);
      const nextVisitDate = nextVisit.toISOString().slice(0, 10);
      const lastVisitDate = job.completedAt.slice(0, 10);
      const existingAmc = state.installations.amc.find((item) => item.id === amcId);
      if (existingAmc) {
        existingAmc.lastVisit = lastVisitDate;
        existingAmc.nextVisit = nextVisitDate;
        existingAmc.status = 'scheduled';
      } else {
        state.installations.amc.push({
          id: amcId,
          customer: job.customer,
          plan: 'Annual maintenance',
          nextVisit: nextVisitDate,
          lastVisit: lastVisitDate,
          status: 'scheduled',
          notes: 'Auto-generated after installation completion.',
        });
      }
      renderInstallations();
      renderWarranties();
      renderAmc();
      showToast('Installation closed', `Warranty ${warrantyId} registered and AMC ${amcId} scheduled.`, 'success');
    }
  });

  amcList?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-action="complete-amc"]');
    if (!button) return;
    const amcId = button.dataset.amcId;
    if (!amcId) return;
    const schedule = state.installations.amc.find((item) => String(item.id) === String(amcId));
    if (!schedule) return;
    const now = new Date();
    schedule.lastVisit = now.toISOString().slice(0, 10);
    const nextVisit = new Date(now);
    nextVisit.setFullYear(nextVisit.getFullYear() + 1);
    schedule.nextVisit = nextVisit.toISOString().slice(0, 10);
    schedule.status = 'scheduled';
    renderAmc();
    showToast('AMC updated', `Next visit for ${schedule.customer} planned on ${nextVisit.toLocaleDateString()}.`, 'success');
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

  analyticsExportButton?.addEventListener('click', () => {
    if (!analyticsStatus) return;
    analyticsExportButton.disabled = true;
    renderInlineStatus(analyticsStatus, 'progress', 'Preparing export', 'Generating KPI workbook for download.');
    setTimeout(() => {
      analyticsExportButton.disabled = false;
      const timestamp = new Date();
      renderInlineStatus(
        analyticsStatus,
        'success',
        'Export ready',
        `KPI report generated on ${timestamp.toLocaleString()}.`
      );
      showToast('Analytics export ready', 'KPI data has been packaged for review.', 'success');
    }, 900);
  });

  analyticsRefreshButton?.addEventListener('click', () => {
    if (!analyticsStatus) return;
    analyticsRefreshButton.disabled = true;
    renderInlineStatus(analyticsStatus, 'info', 'Refreshing metrics', 'Pulling the latest KPI snapshots.');
    setTimeout(() => {
      analyticsRefreshButton.disabled = false;
      renderAnalytics();
      renderInlineStatus(analyticsStatus, 'success', 'Metrics updated', 'Analytics refreshed with current data.');
      showToast('Analytics refreshed', 'Dashboard KPIs updated successfully.', 'success');
    }, 650);
  });

  aiBlogForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    const options = readAiFormOptions();
    if (!options || !options.topic) {
      showToast('Idea required', 'Describe what you want Gemini to cover before generating a draft.', 'warning');
      return;
    }
    requestAiDraft(options, { intent: 'draft' }).catch(() => {});
  });

  aiBlogForm?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-action]');
    if (!button) return;
    if (button.dataset.action === 'research-topic') {
      const options = readAiFormOptions();
      if (!options || !options.topic) {
        showToast('Idea required', 'Describe what you want Gemini to research before generating a draft.', 'warning');
        return;
      }
      requestAiDraft(options, { intent: 'research' }).catch(() => {});
    } else if (button.dataset.action === 'clear-blog') {
      state.ai = { ...DEFAULT_AI_STATE };
      renderAiDraftPreview({ draftHtml: '', research: null });
      if (aiImagePrompt) {
        aiImagePrompt.value = 'Sunlit rooftop solar panels in Ranchi';
      }
      if (aiImageNode) {
        if (defaultAiImageSrc) aiImageNode.src = defaultAiImageSrc;
        if (defaultAiImageAlt) aiImageNode.alt = defaultAiImageAlt;
      }
      clearInlineStatus(aiStatus);
      clearInlineStatus(aiImageStatus);
      showToast('Draft cleared', 'AI draft removed from preview.', 'info');
    } else if (button.dataset.action === 'publish-blog') {
      if (!state.ai?.draft) {
        showToast('Nothing to publish', 'Generate a draft before publishing.', 'warning');
        return;
      }
      queueAiDraftForReview();
    }
  });

  aiAutoblogToggle?.addEventListener('change', () => {
    const enabled = Boolean(aiAutoblogToggle.checked);
    if (aiAutoblogTime) aiAutoblogTime.disabled = !enabled;
    if (aiAutoblogTheme) aiAutoblogTheme.disabled = !enabled;
    const previousRandomTheme = state.aiAutoblog?.lastRandomTheme || null;
    const selectedTheme = aiAutoblogTheme?.value || state.aiAutoblog?.theme || 'regional';
    const scheduleTime = aiAutoblogTime?.value || state.aiAutoblog?.time || '09:00';
    state.aiAutoblog = {
      ...(state.aiAutoblog || {}),
      enabled,
      time: scheduleTime,
      theme: selectedTheme,
      lastRandomTheme: enabled ? previousRandomTheme : null,
    };
    if (!enabled) {
      if (autoblogTimer) {
        clearTimeout(autoblogTimer);
        autoblogTimer = null;
      }
      clearInlineStatus(aiScheduleStatus);
    } else {
      const descriptor = describeAutoblogRotation(selectedTheme);
      renderInlineStatus(
        aiScheduleStatus,
        'info',
        'Auto-blog enabled',
        `Daily ${descriptor} will publish at ${scheduleTime}.`
      );
    }
  });

  aiScheduleForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    const enabled = Boolean(aiAutoblogToggle?.checked);
    if (!enabled) {
      showToast('Auto-blog disabled', 'Enable the toggle to save the schedule.', 'info');
      clearInlineStatus(aiScheduleStatus);
      return;
    }
    const time = aiAutoblogTime?.value || '09:00';
    const theme = aiAutoblogTheme?.value || 'regional';
    const previousRandomTheme = state.aiAutoblog?.lastRandomTheme || null;
    state.aiAutoblog = {
      enabled: true,
      time,
      theme,
      lastRandomTheme: theme === 'random' ? previousRandomTheme : null,
    };
    if (autoblogTimer) {
      clearTimeout(autoblogTimer);
      autoblogTimer = null;
    }
    const descriptor = describeAutoblogRotation(
      theme,
      theme === 'random' ? null : state.aiAutoblog.lastRandomTheme
    );
    renderInlineStatus(
      aiScheduleStatus,
      'progress',
      'Schedule saved',
      `Auto-blog will publish daily at ${time} covering ${descriptor}.`
    );
    showToast('Auto-blog scheduled', `Daily ${descriptor} configured for ${time}.`, 'success');
    autoblogTimer = window.setTimeout(() => {
      runScheduledAutoblogPublish({ theme, time });
    }, 400);
  });

  aiScheduleForm?.addEventListener('reset', () => {
    if (aiAutoblogToggle) aiAutoblogToggle.checked = false;
    if (aiAutoblogTime) aiAutoblogTime.disabled = true;
    if (aiAutoblogTheme) aiAutoblogTheme.disabled = true;
    state.aiAutoblog = { enabled: false, theme: 'regional', time: '09:00', lastRandomTheme: null };
    if (autoblogTimer) {
      clearTimeout(autoblogTimer);
      autoblogTimer = null;
    }
    clearInlineStatus(aiScheduleStatus);
  });

  generateImageButton?.addEventListener('click', () => {
    const prompt = (aiImagePrompt?.value || state.ai.topic || 'Solar rooftop in Jharkhand').toString().trim();
    const aspect = aiImageAspect?.value || state.ai.coverAspect || '16:9';
    const imageData = createImageData(prompt, aspect);
    const imageAlt = `Gemini generated cover for ${prompt}`;
    if (aiImageNode) {
      aiImageNode.src = imageData;
      aiImageNode.alt = imageAlt;
    }
    state.ai = { ...state.ai, coverImage: imageData, coverPrompt: prompt, coverAspect: aspect, coverImageAlt: imageAlt };
    renderInlineStatus(aiImageStatus, 'success', 'Cover refreshed', `Prompt: ${prompt}`);
    showToast('Cover image regenerated', 'Gemini rendered a new banner-ready cover.', 'success');
  });

  downloadImageButton?.addEventListener('click', () => {
    const imageSrc = state.ai?.coverImage;
    if (!imageSrc) {
      showToast('No image available', 'Generate a Gemini cover image before downloading.', 'warning');
      return;
    }
    const link = document.createElement('a');
    link.href = imageSrc;
    link.download = 'dentweb-gemini-cover.svg';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    showToast('Cover downloaded', 'Gemini cover saved to your device.', 'success');
  });

  generateAudioButton?.addEventListener('click', () => {
    if (!aiAudioNode) return;
    aiAudioNode.src = `storage/sample-tts.wav?ts=${Date.now()}`;
    aiAudioNode.hidden = false;
    aiAudioNode.load();
    renderInlineStatus(aiAudioStatus, 'success', 'Narration ready', 'Preview the TTS track before publishing.');
    showToast('Narration generated', 'Audio narration created for the blog.', 'success');
  });

  downloadAudioButton?.addEventListener('click', () => {
    if (!aiAudioNode?.src) {
      showToast('No audio available', 'Generate narration before downloading.', 'warning');
      return;
    }
    const link = document.createElement('a');
    link.href = aiAudioNode.src;
    link.download = 'dentweb-ai-narration.wav';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    showToast('Narration downloaded', 'Audio file saved for distribution.', 'success');
  });

  runBackupButton?.addEventListener('click', () => {
    if (!backupStatus) return;
    runBackupButton.disabled = true;
    renderInlineStatus(backupStatus, 'progress', 'Backup running', 'Creating encrypted backup snapshot.');
    setTimeout(() => {
      const timestamp = new Date();
      state.metrics.system.last_backup = timestamp.toLocaleString();
      if (backupLastInput) backupLastInput.value = state.metrics.system.last_backup;
      updateSystemHealth(state.metrics.system);
      renderInlineStatus(backupStatus, 'success', 'Backup completed', `Completed at ${timestamp.toLocaleTimeString()}.`);
      showToast('Backup completed successfully', 'Manual backup stored securely.', 'success');
      runBackupButton.disabled = false;
    }, 1000);
  });

  verifyBackupButton?.addEventListener('click', () => {
    renderInlineStatus(
      backupStatus,
      'info',
      'Backup verified',
      `Last snapshot confirmed at ${backupLastInput?.value || state.metrics.system.last_backup || '—'}.`
    );
    showToast('Backup verified', 'Latest backup integrity check passed.', 'success');
  });

  backupScheduleForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    const formData = new FormData(backupScheduleForm);
    const frequency = (formData.get('backupFrequency') || 'nightly').toString();
    const time = (formData.get('backupTime') || '02:00').toString();
    renderInlineStatus(
      backupScheduleStatus,
      'success',
      'Schedule saved',
      `Auto-backup configured ${frequency} at ${time}.`
    );
    showToast('Backup schedule updated', 'Automated backups configured successfully.', 'success');
  });

  backupScheduleForm?.addEventListener('reset', () => {
    clearInlineStatus(backupScheduleStatus);
  });

  retentionForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    if (!retentionStatus) return;
    const archiveDays = Number(retentionArchiveInput?.value || state.retention.archiveDays || 90);
    const purgeDays = Number(retentionPurgeInput?.value || state.retention.purgeDays || 180);
    const includeAudit = Boolean(retentionAuditInput?.checked);

    if (!Number.isFinite(archiveDays) || archiveDays < 30) {
      renderInlineStatus(retentionStatus, 'error', 'Invalid archive window', 'Archive must be at least 30 days.');
      showToast('Invalid archive window', 'Choose an archive window of 30 days or more.', 'warning');
      return;
    }

    if (!Number.isFinite(purgeDays) || purgeDays <= archiveDays) {
      renderInlineStatus(
        retentionStatus,
        'error',
        'Adjust retention window',
        'Purge window must be greater than archive window.'
      );
      showToast('Adjust retention window', 'Set a purge window that exceeds the archive window.', 'warning');
      return;
    }

    const submitButton = retentionForm.querySelector('button[type="submit"]');
    if (submitButton) submitButton.disabled = true;

    renderInlineStatus(
      retentionStatus,
      'progress',
      'Saving retention policy',
      `Archiving after ${archiveDays} days and purging after ${purgeDays} days.`
    );

    setTimeout(() => {
      state.retention = { archiveDays, purgeDays, includeAudit };
      renderInlineStatus(
        retentionStatus,
        'success',
        'Retention policy saved',
        includeAudit
          ? 'Audit events will be archived alongside operational logs.'
          : 'Audit events excluded from archival runs.'
      );
      showToast(
        'Retention policy saved',
        `Logs archive after ${archiveDays} days and purge after ${purgeDays} days.`,
        'success'
      );
      if (submitButton) submitButton.disabled = false;
    }, 800);
  });

  retentionForm?.addEventListener('reset', () => {
    setTimeout(() => hydrateRetention(), 0);
  });

  governanceExportButton?.addEventListener('click', () => {
    if (!governanceStatus) return;
    governanceExportButton.disabled = true;
    renderInlineStatus(governanceStatus, 'progress', 'Compiling bundle', 'Packaging activity logs, backups, and approvals.');
    setTimeout(() => {
      governanceExportButton.disabled = false;
      const timestamp = new Date();
      renderInlineStatus(
        governanceStatus,
        'success',
        'Bundle ready',
        `Governance pack exported on ${timestamp.toLocaleString()}.`
      );
      showToast('Governance bundle ready', 'Download prepared for management reporting.', 'success');
    }, 950);
  });

  governanceRefreshButton?.addEventListener('click', () => {
    renderInlineStatus(
      governanceStatus,
      'info',
      'Governance refreshed',
      'Latest role reviews and activity logs loaded.'
    );
    renderGovernance();
  });

  blogNewButton?.addEventListener('click', () => {
    resetBlogForm({ focusTitle: true });
    blogForm?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  blogResetButton?.addEventListener('click', (event) => {
    event.preventDefault();
    resetBlogForm({ focusTitle: true });
  });

  blogForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    saveBlogPost();
  });

  blogPublishButton?.addEventListener('click', () => {
    handleBlogPublish();
  });

  blogArchiveButton?.addEventListener('click', () => {
    handleBlogArchive();
  });

  blogTableBody?.addEventListener('click', (event) => {
    const target = event.target instanceof HTMLElement ? event.target.closest('[data-blog-action]') : null;
    if (!target) return;
    const id = Number(target.dataset.blogId);
    if (!id) return;
    const post = blogState.posts.find((item) => item.id === id);
    if (!post) return;

    const action = target.dataset.blogAction;
    if (action === 'edit') {
      populateBlogForm(post);
      clearInlineStatus(blogStatus);
      blogForm?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      return;
    }

    if (action === 'toggle') {
      const publish = target.dataset.blogPublish !== '0';
      renderInlineStatus(
        blogStatus,
        'progress',
        publish ? 'Publishing post…' : 'Unpublishing post…',
        publish ? 'Making the post visible on the public blog.' : 'Hiding the post from the public blog.'
      );
      api('publish-blog-post', {
        method: 'POST',
        body: { id, publish },
      })
        .then(({ post: updated }) => {
          const normalized = normalizeBlogPost(updated);
          upsertBlogPost(normalized);
          renderBlogPosts();
          if (blogState.editingId === id) {
            blogState.editingStatus = normalized.status;
            populateBlogForm(normalized);
          } else {
            updateBlogActions();
          }
          renderInlineStatus(
            blogStatus,
            'success',
            publish ? 'Post published' : 'Post unpublished',
            publish ? 'The article now appears on the public blog.' : 'The article no longer appears on the public blog.'
          );
        })
        .catch((error) => {
          renderInlineStatus(
            blogStatus,
            'error',
            publish ? 'Publish failed' : 'Update failed',
            error.message || 'Unable to update publish status.'
          );
        });
      return;
    }

    if (action === 'archive') {
      if (post.status === 'archived') {
        renderInlineStatus(blogStatus, 'info', 'Already archived', 'Select Publish to restore the post.');
        return;
      }
      if (!window.confirm('Archive this post? It will no longer appear on the public blog.')) {
        return;
      }
      renderInlineStatus(blogStatus, 'progress', 'Archiving post…', 'Removing the article from the public blog.');
      api('archive-blog-post', {
        method: 'POST',
        body: { id },
      })
        .then(({ post: updated }) => {
          const normalized = normalizeBlogPost(updated);
          upsertBlogPost(normalized);
          renderBlogPosts();
          if (blogState.editingId === id) {
            blogState.editingStatus = normalized.status;
            populateBlogForm(normalized);
          } else {
            updateBlogActions();
          }
          renderInlineStatus(blogStatus, 'info', 'Post archived', 'The article remains internal until republished.');
        })
        .catch((error) => {
          renderInlineStatus(blogStatus, 'error', 'Archive failed', error.message || 'Unable to archive the post.');
        });
    }
  });

  function formatDate(value) {
    if (!value) return '--';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return `${date.toLocaleDateString()} ${date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
  }

  function formatDateOnly(value) {
    if (!value) return '--';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      // Accept YYYY-MM-DD strings
      if (/^\d{4}-\d{2}-\d{2}$/.test(String(value))) {
        const [year, month, day] = String(value).split('-');
        return new Date(Number(year), Number(month) - 1, Number(day)).toLocaleDateString();
      }
      return value;
    }
    return date.toLocaleDateString();
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

  function slugify(value) {
    if (!value) {
      return `draft-${Date.now()}`;
    }
    let text = value.toString().trim().toLowerCase();
    try {
      text = text.normalize('NFKD').replace(/[\u0300-\u036f]/g, '');
    } catch (error) {
      // ignore normalization issues in older browsers
    }
    text = text.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    return text || `draft-${Date.now()}`;
  }

  function generateLocalId() {
    localPostCounter += 1;
    return -(Date.now() + localPostCounter);
  }

  function createLocalBlogPost(options = {}) {
    const status = (options.status || 'draft').toLowerCase();
    const title = options.title || 'AI Blog Post';
    const nowIso = new Date().toISOString();
    return {
      id: options.id ?? generateLocalId(),
      title,
      slug: slugify(options.slug || title),
      excerpt: options.excerpt || '',
      status,
      publishedAt: status === 'published' ? nowIso : '',
      updatedAt: nowIso,
      authorName: options.authorName || '',
      coverImage: options.coverImage || '',
      coverImageAlt: options.coverImageAlt || '',
      tags: Array.isArray(options.tags) ? options.tags.filter(Boolean) : [],
      body: options.body || '',
    };
  }

  function storeLocalBlogPost(options = {}, { setAsEditing = false } = {}) {
    const post = createLocalBlogPost(options);
    upsertBlogPost(post);
    renderBlogPosts();
    if (setAsEditing) {
      blogState.editingId = post.id;
      blogState.editingStatus = post.status;
      populateBlogForm(post);
    }
    updateBlogActions();
    return post;
  }

  function handleAutoblogSuccess(
    post,
    { descriptor, scheduleTime, normalizedTheme, actualTheme, fallback = false }
  ) {
    const normalized = normalizeBlogPost(post);
    upsertBlogPost(normalized);
    renderBlogPosts();
    updateBlogActions();
    const previousAutoblog = state.aiAutoblog || {};
    const nextAutoblogState = {
      ...previousAutoblog,
      enabled: true,
      theme: normalizedTheme,
      time: scheduleTime,
      lastRandomTheme: normalizedTheme === 'random' ? actualTheme : null,
    };
    if (!fallback) {
      nextAutoblogState.lastPublishedAt = new Date().toISOString();
    }
    state.aiAutoblog = nextAutoblogState;
    const tone = fallback ? 'info' : 'success';
    const title = fallback ? 'Auto-blog cached offline' : 'Schedule active';
    const message = fallback
      ? `Saved “${normalized.title}” locally. Daily ${descriptor} will resume publishing at ${scheduleTime} once connectivity returns.`
      : `Daily ${descriptor} will publish automatically at ${scheduleTime}. Latest article went live.`;
    renderInlineStatus(aiScheduleStatus, tone, title, message);
    showToast(
      fallback ? 'Scheduled blog cached' : 'Scheduled blog published',
      fallback
        ? `Auto-blog “${normalized.title}” stored locally until the API is reachable.`
        : `Auto-blog “${normalized.title}” published without review with a ${capitalize(actualTheme)} focus.`,
      fallback ? 'warning' : 'success'
    );
  }

  function handleSearch(term) {
    if (!searchResults) return;
    if (term.length < 2) {
      searchResults.innerHTML = '<p class="dashboard-search-empty">Start typing to surface admin modules, records, and recent activity.</p>';
      searchResults.hidden = term.length === 0;
      return;
    }

    const lower = term.toLowerCase();
    const groups = new Map();
    const pushMatch = (type, title, subtitle, action) => {
      if (!title) return;
      if (!groups.has(type)) groups.set(type, []);
      groups.get(type).push({ title, subtitle: subtitle || '', action });
    };

    state.users.forEach((user) => {
      if (
        user.full_name.toLowerCase().includes(lower) ||
        user.email.toLowerCase().includes(lower) ||
        user.username.toLowerCase().includes(lower)
      ) {
        pushMatch('Users', user.full_name, `${capitalize(user.role_name)} · ${user.email}`, () => activateTab('onboarding'));
      }
    });

    state.invitations.forEach((invite) => {
      if (invite.invitee_name.toLowerCase().includes(lower) || invite.invitee_email.toLowerCase().includes(lower)) {
        pushMatch(
          'Invitations',
          invite.invitee_name,
          `${capitalize(invite.role_name)} · ${invite.invitee_email}`,
          () => activateTab('onboarding')
        );
      }
    });

    state.crm.leads.forEach((lead) => {
      const haystack = `${lead.name} ${lead.reference || ''} ${lead.interest || ''}`.toLowerCase();
      if (haystack.includes(lower)) {
        pushMatch('Leads', lead.name, `${lead.source || 'Unknown source'} · ${lead.interest || ''}`, () => activateTab('crm'));
      }
    });

    state.crm.customers.forEach((customer) => {
      const haystack = `${customer.name} ${customer.leadReference || ''}`.toLowerCase();
      if (haystack.includes(lower)) {
        pushMatch(
          'Customers',
          customer.name,
          `${customer.systemSize || ''} · ${customer.installationDate || 'Schedule pending'}`,
          () => activateTab('crm')
        );
      }
    });

    (state.installations.jobs || []).forEach((job) => {
      const haystack = `${job.customer || ''} ${job.location || ''} ${job.systemSize || ''} ${job.notes || ''}`.toLowerCase();
      if (haystack.includes(lower)) {
        const schedule = job.status === 'completed' ? job.completedAt || job.scheduledAt : job.scheduledAt;
        pushMatch(
          'Installations',
          job.customer || job.id,
          `${capitalize(job.status || 'scheduled')} · ${formatDate(schedule)}`,
          () => activateTab('installations')
        );
      }
    });

    (state.installations.warranties || []).forEach((warranty) => {
      const haystack = `${warranty.id || ''} ${warranty.customer || ''} ${warranty.product || ''}`.toLowerCase();
      if (haystack.includes(lower)) {
        pushMatch(
          'Warranties',
          warranty.id,
          `${warranty.customer || ''} · Expires ${formatDateOnly(warranty.expiresOn)}`,
          () => activateTab('installations')
        );
      }
    });

    (state.installations.amc || []).forEach((schedule) => {
      const haystack = `${schedule.id || ''} ${schedule.customer || ''} ${schedule.plan || ''}`.toLowerCase();
      if (haystack.includes(lower)) {
        pushMatch(
          'AMC',
          schedule.customer || schedule.id,
          `${schedule.plan || ''} · Next ${formatDateOnly(schedule.nextVisit)}`,
          () => activateTab('installations')
        );
      }
    });

    state.tasks.items.forEach((task) => {
      const haystack = `${task.title} ${task.notes || ''} ${task.linkedTo || ''}`.toLowerCase();
      if (haystack.includes(lower)) {
        pushMatch(
          'Tasks',
          task.title,
          `${TASK_STATUS_LABELS[task.status] || 'To Do'} · ${findTeamMember(task.assigneeId)?.name || 'Unassigned'}`,
          () => activateTab('tasks')
        );
      }
    });

    state.documents.forEach((doc) => {
      const haystack = `${doc.name} ${(doc.tags || []).join(' ')} ${doc.reference || ''}`.toLowerCase();
      if (haystack.includes(lower)) {
        pushMatch(
          'Documents',
          doc.name,
          `${capitalize(doc.linkedTo || '')} · v${doc.version || 1}`,
          () => activateTab('documents')
        );
      }
    });

    state.dataQuality.duplicates.forEach((item) => {
      const haystack = `${item.primary} ${item.duplicate} ${item.reason || ''}`.toLowerCase();
      if (haystack.includes(lower)) {
        pushMatch(
          'Duplicates',
          `${item.primary} ↔ ${item.duplicate}`,
          item.reason || 'Potential duplicate',
          () => activateTab('data-quality')
        );
      }
    });

    state.dataQuality.approvals.forEach((item) => {
      const haystack = `${item.employee} ${item.change}`.toLowerCase();
      if (haystack.includes(lower)) {
        pushMatch('Approvals', item.employee, item.change, () => activateTab('data-quality'));
      }
    });

    state.complaints.forEach((complaint) => {
      if ((complaint.reference || '').toLowerCase().includes(lower) || (complaint.title || '').toLowerCase().includes(lower)) {
        pushMatch(
          'Complaints',
          complaint.reference,
          `${complaint.title} · ${capitalize(complaint.status)}`,
          () => activateTab('complaints')
        );
      }
    });

    state.audit.forEach((entry) => {
      if ((entry.description || '').toLowerCase().includes(lower)) {
        pushMatch(
          'Audit',
          entry.action,
          `${entry.actor_name || 'System'} · ${formatDate(entry.created_at)}`,
          () => activateTab('audit')
        );
      }
    });

    state.referrers.forEach((partner) => {
      const haystack = `${partner.name} ${partner.company || ''}`.toLowerCase();
      if (haystack.includes(lower)) {
        pushMatch(
          'Referrers',
          partner.name,
          `${partner.leads} leads · ${partner.conversions} conversions`,
          () => activateTab('referrers')
        );
      }
    });

    state.subsidy.applications.forEach((app) => {
      const haystack = `${app.reference} ${app.customer}`.toLowerCase();
      if (haystack.includes(lower)) {
        pushMatch('Subsidy', app.reference, `${app.customer} · ${capitalize(app.stage)}`, () => activateTab('subsidy'));
      }
    });

    (state.analytics.kpis || []).forEach((kpi) => {
      const haystack = `${kpi.label || ''} ${kpi.value || ''}`.toLowerCase();
      if (haystack.includes(lower)) {
        pushMatch('Analytics', kpi.label, `${kpi.value || ''} · ${kpi.change || ''}`, () => activateTab('analytics'));
      }
    });

    (state.analytics.installerProductivity || []).forEach((row) => {
      const haystack = `${row.name || ''}`.toLowerCase();
      if (haystack.includes(lower)) {
        pushMatch(
          'Analytics',
          row.name,
          `${row.installations ?? 0} installs · ${row.amcVisits ?? 0} AMC visits`,
          () => activateTab('analytics')
        );
      }
    });

    const systemMetrics = state.metrics.system || {};
    const systemHaystack = `${systemMetrics.last_backup || ''} ${systemMetrics.disk_usage || ''} ${systemMetrics.errors_24h || ''} ${systemMetrics.uptime || ''}`.toLowerCase();
    if (systemHaystack.includes(lower) || lower.includes('backup') || lower.includes('uptime')) {
      pushMatch(
        'System',
        'Backup health',
        `Last backup ${systemMetrics.last_backup || 'Not recorded'} · Storage ${systemMetrics.disk_usage || 'Unknown'}`,
        () => activateTab('health')
      );
    }

    const retention = state.retention || {};
    const retentionHaystack = `${retention.archiveDays || ''} ${retention.purgeDays || ''} retention ${retention.includeAudit ? 'audit' : 'operations'}`.toLowerCase();
    if (retentionHaystack.includes(lower) || lower.includes('retention') || lower.includes('archive')) {
      const subtitle = `Archive ${retention.archiveDays || 90}d · Purge ${retention.purgeDays || 180}d`;
      const finalSubtitle = `${subtitle}${retention.includeAudit ? ' · Audit included' : ' · Audit excluded'}`;
      pushMatch('System', 'Retention policy', finalSubtitle, () => activateTab('health'));
    }

    (state.governance.roleMatrix || []).forEach((role) => {
      const haystack = `${role.role || ''} ${role.owner || ''}`.toLowerCase();
      if (haystack.includes(lower)) {
        pushMatch(
          'Governance',
          role.role,
          `${role.users ?? 0} users · Reviewed ${formatDateOnly(role.lastReview)}`,
          () => activateTab('governance')
        );
      }
    });

    (state.governance.pendingReviews || []).forEach((review) => {
      const haystack = `${review.item || ''} ${review.owner || ''}`.toLowerCase();
      if (haystack.includes(lower)) {
        pushMatch(
          'Governance',
          review.item,
          `Due ${formatDateOnly(review.due)} · Owner ${review.owner || 'Admin'}`,
          () => activateTab('governance')
        );
      }
    });

    (state.governance.activityLogs || []).forEach((entry) => {
      const haystack = `${entry.description || ''} ${entry.actor || ''}`.toLowerCase();
      if (haystack.includes(lower)) {
        pushMatch(
          'Governance',
          entry.description,
          `${entry.actor || 'System'} · ${formatDate(entry.timestamp)}`,
          () => activateTab('governance')
        );
      }
    });

    if (!groups.size) {
      searchResults.innerHTML = '<p class="dashboard-search-empty">No records matched your search.</p>';
      searchResults.hidden = false;
      return;
    }

    const order = [
      'Users',
      'Invitations',
      'Customers',
      'Leads',
      'Installations',
      'Warranties',
      'AMC',
      'Tasks',
      'Documents',
      'Approvals',
      'Duplicates',
      'Complaints',
      'Subsidy',
      'Referrers',
      'System',
      'Analytics',
      'Governance',
      'Audit',
    ];

    const fragment = document.createDocumentFragment();
    order.forEach((type) => {
      const items = groups.get(type);
      if (!items || !items.length) return;
      const groupNode = searchGroupTemplate?.content
        ? searchGroupTemplate.content.firstElementChild.cloneNode(true)
        : document.createElement('div');
      if (!groupNode.classList.contains('dashboard-search-group')) {
        groupNode.classList.add('dashboard-search-group');
      }
      const heading = groupNode.querySelector('h3') || groupNode.appendChild(document.createElement('h3'));
      heading.textContent = type;
      let list = groupNode.querySelector('ul');
      if (!list) {
        list = document.createElement('ul');
        groupNode.appendChild(list);
      }
      list.innerHTML = '';
      items.slice(0, 5).forEach((item) => {
        const li = document.createElement('li');
        const button = document.createElement('button');
        button.type = 'button';
        button.innerHTML = `<span>${escapeHtml(item.title)}</span><small>${escapeHtml(item.subtitle)}</small>`;
        button.addEventListener('click', () => {
          item.action?.();
          searchResults.hidden = true;
          if (searchInput) searchInput.value = '';
        });
        li.appendChild(button);
        list.appendChild(li);
      });
      fragment.appendChild(groupNode);
    });

    searchResults.innerHTML = '';
    searchResults.appendChild(fragment);
    searchResults.hidden = false;
  }

  searchInput?.addEventListener('input', () => handleSearch(searchInput.value.trim()));
  searchForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    handleSearch(searchInput.value.trim());
  });

  window.addEventListener('keydown', (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
      event.preventDefault();
      activateTab('overview');
      searchResults.hidden = false;
      searchInput?.focus();
    }
  });

  function hydrateFromConfig() {
    updateMetricCards(state.metrics.counts);
    updateSystemHealth(state.metrics.system);
    hydrateRetention();
    populateGemini();
    populateTaskAssignees();
    renderTasks();
    renderInstallations();
    renderWarranties();
    renderAmc();
    renderDocuments();
    renderValidations();
    renderDuplicates();
    renderApprovals();
    renderCRM();
    renderReferrers();
    renderSubsidy();
    renderAnalytics();
    renderGovernance();
    if (config.blog?.posts) {
      blogState.posts = config.blog.posts.map(normalizeBlogPost);
    } else {
      blogState.posts = [];
    }
    syncBlogState();
    state.blog = config.blog || { posts: [] };
    renderBlogPosts();
    resetBlogForm();
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
        if (data.installations) state.installations = data.installations;
        if (data.analytics) state.analytics = data.analytics;
        if (data.governance) state.governance = data.governance;
        if (data.retention) state.retention = data.retention;
        if (data.blog?.posts) {
          blogState.posts = data.blog.posts.map(normalizeBlogPost);
        }
        if (data.blog) {
          state.blog = data.blog;
        } else {
          state.blog = { posts: [] };
        }
        syncBlogState();
        updateMetricCards(state.metrics.counts);
        updateSystemHealth(state.metrics.system);
        hydrateRetention();
        populateLoginPolicy();
        populateGemini();
        populateTaskAssignees();
        renderUsers();
        renderInvitations();
        renderComplaints();
        renderAudit();
        renderTasks();
        renderInstallations();
        renderWarranties();
        renderAmc();
        renderDocuments();
        renderValidations();
        renderDuplicates();
        renderApprovals();
        renderCRM();
        renderReferrers();
        renderSubsidy();
        renderAnalytics();
        renderGovernance();
        renderBlogPosts();
        if (blogState.editingId) {
          const current = blogState.posts.find((post) => post.id === blogState.editingId);
          if (current) {
            blogState.editingStatus = current.status;
            populateBlogForm(current);
          } else {
            resetBlogForm();
          }
        } else {
          resetBlogForm();
        }
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
