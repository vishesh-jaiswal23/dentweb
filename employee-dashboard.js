(function () {
  'use strict';

  const config = window.DakshayaniEmployee || {};
  const API_BASE = config.apiBase || 'api/employee.php';
  const CSRF_TOKEN = config.csrfToken || '';

  function api(action, { method = 'GET', body } = {}) {
    if (!API_BASE) {
      return Promise.reject(new Error('API base not configured'));
    }
    const options = {
      method,
      headers: {
        Accept: 'application/json',
        'X-CSRF-Token': CSRF_TOKEN,
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
          throw new Error(payload.error || `Request failed (${response.status})`);
        }
        return payload.data;
      });
  }

  const THEME_KEY = 'dakshayani-employee-theme';
  const body = document.body;
  const themeInputs = document.querySelectorAll('[data-theme-option]');
  const quickLinks = Array.from(document.querySelectorAll('[data-quick-link]'));
  const sectionNodes = quickLinks
    .map((link) => {
      const id = link.getAttribute('href');
      if (!id || !id.startsWith('#')) return null;
      const section = document.querySelector(id);
      if (!section) return null;
      section.setAttribute('data-section-observed', '');
      return { link, section };
    })
    .filter(Boolean);
  const taskBoard = document.querySelector('[data-task-board]');
  const taskColumns = taskBoard ? Array.from(taskBoard.querySelectorAll('[data-task-column]')) : [];
  const taskActivity = document.querySelector('[data-task-activity]');
  const pendingSummary = document.querySelector('[data-summary-target="pendingTasks"]');
  const ticketSummary = document.querySelector('[data-summary-target="activeComplaints"]');
  const ticketCards = Array.from(document.querySelectorAll('[data-ticket-id]'));
  const leadNoteForm = document.querySelector('[data-lead-note-form]');
  const leadActivity = document.querySelector('[data-lead-activity]');
  const leadIntakeForm = document.querySelector('[data-lead-intake]');
  const pendingLeads = document.querySelector('[data-pending-leads]');
  const notificationCount = document.querySelector('[data-notification-count]');
  const notificationPanel = document.querySelector('[data-notification-panel]');
  const notificationListNode = notificationPanel ? notificationPanel.querySelector('[data-notification-list]') : null;
  const notificationClose = document.querySelector('[data-close-notifications]');
  const notificationMarkAll = document.querySelector('[data-notification-mark-all]');
  const notificationSecondaryCount = document.querySelector('[data-notification-count-secondary]');
  const notificationOpeners = document.querySelectorAll('[data-open-notifications]');
  const visitCards = Array.from(document.querySelectorAll('[data-visit-card]'));
  const visitActivity = document.querySelector('[data-visit-activity]');
  const visitSummary = document.querySelector('[data-summary-target="scheduledVisits"]');
  const documentForm = document.querySelector('[data-document-form]');
  const documentList = document.querySelector('[data-document-list]');
  const subsidyBoard = document.querySelector('[data-subsidy-board]');
  const subsidyActivity = document.querySelector('[data-subsidy-activity]');
  const warrantyRows = Array.from(document.querySelectorAll('[data-warranty-row]'));
  const warrantyActivity = document.querySelector('[data-warranty-activity]');
  const communicationForm = document.querySelector('[data-communication-form]');
  const communicationLog = document.querySelector('[data-communication-log]');
  const aiForm = document.querySelector('[data-ai-form]');
  const aiOutput = document.querySelector('[data-ai-output]');
  const profilePanel = document.querySelector('[data-profile-panel]');
  const profileForm = document.querySelector('[data-profile-form]');
  const profileAlert = document.querySelector('[data-profile-alert]');
  const profileClose = document.querySelector('[data-close-profile]');
  const profileOpeners = document.querySelectorAll('[data-open-profile]');
  const feedbackForm = document.querySelector('[data-feedback-form]');
  const feedbackAlert = document.querySelector('[data-feedback-alert]');
  const feedbackLog = document.querySelector('[data-feedback-log]');
  const feedbackEmpty = feedbackLog ? feedbackLog.querySelector('[data-feedback-empty]') : null;
  const complianceList = document.querySelector('[data-compliance-flags]');
  const complianceCount = document.querySelector('[data-compliance-count]');
  const auditLog = document.querySelector('[data-audit-log]');
  const syncIndicator = document.querySelector('[data-sync-indicator]');
  const analyticsCards = Array.from(document.querySelectorAll('[data-analytics-card]'));
  const analyticsReportButton = document.querySelector('[data-download-report]');
  const validatedForms = Array.from(document.querySelectorAll('[data-validate-form]'));
  const complianceIssues = new Set();
  const notificationSummaryMap = new Map();
  document.querySelectorAll('[data-notification-summary-item]').forEach((item) => {
    const id = item.dataset.notificationId;
    if (!id) return;
    notificationSummaryMap.set(id, item);
  });

  function formatTime(date) {
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }

  function formatDateTime(date) {
    return `${date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short' })} · ${formatTime(date)}`;
  }

  function textFromHTML(html) {
    const temp = document.createElement('div');
    temp.innerHTML = html;
    return temp.textContent || temp.innerText || '';
  }

  function openDrawer(panel) {
    if (!panel) return;
    panel.hidden = false;
    panel.setAttribute('aria-hidden', 'false');
    panel.classList.add('is-open');
  }

  function closeDrawer(panel) {
    if (!panel) return;
    panel.classList.remove('is-open');
    panel.setAttribute('aria-hidden', 'true');
    panel.hidden = true;
  }

  function refreshSyncIndicator(context = '') {
    if (!syncIndicator) return;
    const now = new Date();
    const label = syncIndicator.dataset.syncLabel || 'Realtime sync with Admin portal';
    const contextText = context ? ` (${context})` : '';
    syncIndicator.dataset.lastSync = now.toISOString();
    syncIndicator.textContent = `${label} · Last update ${formatDateTime(now)}${contextText}`;
  }

  function recordAuditEvent(action, detail = '') {
    if (!auditLog) return;
    const placeholder = auditLog.querySelector('.text-muted');
    if (placeholder) {
      placeholder.remove();
    }
    const entry = document.createElement('li');
    const now = new Date();
    const timeEl = document.createElement('time');
    timeEl.dateTime = now.toISOString();
    timeEl.textContent = formatDateTime(now);
    entry.appendChild(timeEl);
    const content = document.createElement('p');
    const strong = document.createElement('strong');
    strong.textContent = action;
    content.appendChild(strong);
    if (detail) {
      const detailEl = document.createElement('span');
      detailEl.className = 'text-xs text-muted d-block';
      detailEl.textContent = detail;
      content.appendChild(detailEl);
    }
    entry.appendChild(content);
    auditLog.prepend(entry);
    while (auditLog.children.length > 20) {
      auditLog.removeChild(auditLog.lastElementChild);
    }
    refreshSyncIndicator(action);
  }

  function updateComplianceCount() {
    if (!complianceCount) return;
    complianceCount.textContent = String(complianceIssues.size);
  }

  function flagComplianceIssue(source, fieldName, message) {
    if (!complianceList) return;
    const key = `${source}::${fieldName}::${message}`;
    if (complianceIssues.has(key)) return;
    complianceIssues.add(key);
    const emptyState = complianceList.querySelector('.text-muted');
    if (emptyState) {
      emptyState.remove();
    }
    const item = document.createElement('li');
    const sourceEl = document.createElement('strong');
    sourceEl.textContent = source;
    const messageEl = document.createElement('span');
    messageEl.textContent = message;
    const timeEl = document.createElement('time');
    const now = new Date();
    timeEl.dateTime = now.toISOString();
    timeEl.textContent = formatDateTime(now);
    item.appendChild(sourceEl);
    item.appendChild(messageEl);
    item.appendChild(timeEl);
    complianceList.prepend(item);
    updateComplianceCount();
  }

  function escapeSelectorValue(value) {
    if (window.CSS && typeof window.CSS.escape === 'function') {
      return window.CSS.escape(value);
    }
    return String(value).replace(/["\\]/g, '\\$&');
  }

  function complaintStatusKey(status) {
    switch (status) {
      case 'resolution':
        return 'awaiting_response';
      case 'closed':
        return 'resolved';
      case 'triage':
      case 'work':
      case 'intake':
      default:
        return 'in_progress';
    }
  }

  function buildComplaintTimelineEntries(complaint) {
    const entries = [];
    if (complaint.createdAt) {
      entries.push({
        time: new Date(complaint.createdAt).toISOString(),
        label: 'Created',
        message: 'Ticket opened from Admin portal.',
      });
    }
    if (complaint.updatedAt && complaint.updatedAt !== complaint.createdAt) {
      const statusLabel = STATUS_LABELS[complaintStatusKey(complaint.status)]?.label || 'Updated';
      entries.push({
        time: new Date(complaint.updatedAt).toISOString(),
        label: 'Last update',
        message: `Status updated to ${statusLabel}.`,
      });
    }
    (complaint.notes || []).forEach((note) => {
      if (!note.createdAt) return;
      entries.push({
        time: new Date(note.createdAt).toISOString(),
        label: 'Note added',
        message: `${note.authorName || 'Team'}: ${note.body || ''}`,
      });
    });
    (complaint.attachments || []).forEach((attachment) => {
      if (!attachment.uploadedAt) return;
      if (attachment.visibility === 'admin') return;
      entries.push({
        time: new Date(attachment.uploadedAt).toISOString(),
        label: 'Attachment uploaded',
        message: `${attachment.uploadedBy || 'Team'} uploaded ${attachment.label || attachment.filename || 'Attachment'}.`,
      });
    });
    entries.sort((a, b) => (a.time < b.time ? 1 : -1));
    return entries;
  }

  function renderCardAttachments(card, complaint) {
    let attachmentsContainer = card.querySelector('.ticket-attachments');
    const attachments = (complaint.attachments || []).filter((item) => item.visibility !== 'admin');
    let list = attachmentsContainer?.querySelector('ul') || null;
    if (!attachments.length) {
      if (attachmentsContainer && list) {
        list.innerHTML = '';
        const empty = document.createElement('li');
        empty.textContent = 'No attachments available.';
        list.appendChild(empty);
      }
      return;
    }
    if (!attachmentsContainer) {
      attachmentsContainer = document.createElement('div');
      attachmentsContainer.className = 'ticket-attachments';
      const heading = document.createElement('h4');
      heading.textContent = 'Attachments';
      attachmentsContainer.appendChild(heading);
      list = document.createElement('ul');
      attachmentsContainer.appendChild(list);
      const timeline = card.querySelector('.ticket-timeline');
      if (timeline) {
        card.insertBefore(attachmentsContainer, timeline);
      } else {
        card.appendChild(attachmentsContainer);
      }
    }
    if (!list) {
      list = document.createElement('ul');
      attachmentsContainer.appendChild(list);
    }
    list.innerHTML = '';
    attachments.forEach((attachment) => {
      const li = document.createElement('li');
      const icon = attachmentIcon(attachment.filename || '');
      const iconEl = document.createElement('i');
      iconEl.className = icon;
      iconEl.setAttribute('aria-hidden', 'true');
      li.appendChild(iconEl);
      if (attachment.downloadToken) {
        const link = document.createElement('a');
        link.href = `download.php?complaint=${encodeURIComponent(complaint.reference)}&token=${encodeURIComponent(attachment.downloadToken)}`;
        link.target = '_blank';
        link.rel = 'noopener';
        link.textContent = attachment.label || attachment.filename || 'Attachment';
        li.appendChild(link);
      } else {
        const span = document.createElement('span');
        span.textContent = attachment.label || attachment.filename || 'Attachment';
        li.appendChild(span);
      }
      if (attachment.filename && attachment.filename !== attachment.label) {
        const detail = document.createElement('span');
        detail.className = 'text-xs text-muted d-block';
        detail.textContent = attachment.filename;
        li.appendChild(detail);
      }
      list.appendChild(li);
    });
  }

  function renderCardTimeline(card, complaint) {
    const timelineNode = card.querySelector('[data-ticket-timeline]');
    if (!timelineNode) return;
    timelineNode.innerHTML = '';
    const entries = buildComplaintTimelineEntries(complaint);
    entries.forEach((entry) => {
      const li = document.createElement('li');
      const timeEl = document.createElement('time');
      timeEl.dateTime = entry.time;
      timeEl.textContent = new Date(entry.time).toLocaleString('en-IN', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
      const message = document.createElement('p');
      message.textContent = entry.message;
      li.appendChild(timeEl);
      li.appendChild(message);
      timelineNode.appendChild(li);
    });
  }

  function applyComplaintUpdate(complaint) {
    if (!complaint || !complaint.reference) return;
    const card = document.querySelector(`[data-ticket-id="${escapeSelectorValue(complaint.reference)}"]`);
    if (!card) return;
    const statusKey = complaintStatusKey(complaint.status);
    card.dataset.status = statusKey;
    const statusConfig = STATUS_LABELS[statusKey] || STATUS_LABELS.in_progress;
    const statusLabelNode = card.querySelector('[data-ticket-status-label]');
    if (statusLabelNode) {
      statusLabelNode.textContent = statusConfig.label;
      statusLabelNode.className = `dashboard-status dashboard-status--${statusConfig.tone}`;
    }
    const statusSelect = card.querySelector('[data-ticket-status]');
    if (statusSelect) {
      statusSelect.value = statusKey;
    }
    const slaNode = card.querySelector('[data-ticket-sla]');
    if (slaNode) {
      const slaSource = complaint.slaDue || complaint.sla_due_at || complaint.sla_due || '';
      slaNode.textContent = slaSource
        ? new Date(slaSource).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
        : 'Not set';
    }
    renderCardAttachments(card, complaint);
    renderCardTimeline(card, complaint);
    syncTicketSummary();
  }

  function prependDocumentRow(documentData, sizeMb) {
    if (!documentList) return;
    const placeholder = documentList.querySelector('.text-muted');
    if (placeholder && placeholder.parentElement?.children.length === 1) {
      placeholder.parentElement.removeChild(placeholder);
    }
    const row = document.createElement('tr');

    const docCell = document.createElement('td');
    const typeEl = document.createElement('strong');
    typeEl.textContent = documentData.name || 'Document';
    docCell.appendChild(typeEl);
    if (documentData.reference) {
      const fileEl = document.createElement('span');
      fileEl.className = 'text-xs text-muted d-block';
      fileEl.textContent = documentData.reference;
      docCell.appendChild(fileEl);
    }
    if (Number.isFinite(sizeMb)) {
      const sizeEl = document.createElement('span');
      sizeEl.className = 'text-xs text-muted d-block';
      sizeEl.textContent = `${sizeMb.toFixed(1)} MB`;
      docCell.appendChild(sizeEl);
    }
    row.appendChild(docCell);

    const customerCell = document.createElement('td');
    const linked = documentData.linkedTo || '';
    customerCell.textContent = linked.includes(':') ? linked.split(':').pop() : linked;
    row.appendChild(customerCell);

    const statusCell = document.createElement('td');
    const badge = document.createElement('span');
    const visibility = documentData.visibility || 'employee';
    badge.className = `dashboard-status dashboard-status--${visibility === 'both' ? 'resolved' : 'waiting'}`;
    badge.textContent = visibility === 'both' ? 'Shared with Admin' : 'Employee only';
    statusCell.appendChild(badge);
    row.appendChild(statusCell);

    const uploadedCell = document.createElement('td');
    const updatedAt = documentData.updatedAt ? new Date(documentData.updatedAt) : new Date();
    const timeEl = document.createElement('time');
    timeEl.dateTime = updatedAt.toISOString();
    timeEl.textContent = formatDateTime(updatedAt);
    uploadedCell.appendChild(timeEl);
    const byEl = document.createElement('span');
    byEl.className = 'text-xs text-muted d-block';
    byEl.textContent = `by ${documentData.uploadedBy || 'You'}`;
    uploadedCell.appendChild(byEl);
    row.appendChild(uploadedCell);

    documentList.prepend(row);
  }

  function showFieldError(field, message) {
    if (!field) return;
    const wrapper = field.closest('label') || field.parentElement;
    field.classList.add('has-error');
    field.setAttribute('aria-invalid', 'true');
    const messageNode = wrapper ? wrapper.querySelector('[data-validation-message]') : null;
    if (messageNode) {
      messageNode.textContent = message;
      messageNode.hidden = false;
    } else {
      field.title = message;
    }
  }

  function clearFieldError(field) {
    if (!field) return;
    const wrapper = field.closest('label') || field.parentElement;
    field.classList.remove('has-error');
    field.removeAttribute('aria-invalid');
    const messageNode = wrapper ? wrapper.querySelector('[data-validation-message]') : null;
    if (messageNode) {
      messageNode.textContent = '';
      messageNode.hidden = true;
    }
  }

  function runValidationRule(ruleKey, rawValue, field) {
    const value = String(rawValue ?? '').trim();
    if (value.length === 0) {
      if (field.hasAttribute('required')) {
        return { valid: false, message: 'This field is required.' };
      }
      return { valid: true, message: '' };
    }
    switch (ruleKey) {
      case 'phone':
        return /^[6-9][0-9]{9}$/.test(value)
          ? { valid: true, message: '' }
          : { valid: false, message: 'Enter a 10-digit mobile starting with 6-9.' };
      case 'pincode':
        return /^[1-9][0-9]{5}$/.test(value)
          ? { valid: true, message: '' }
          : { valid: false, message: 'Provide a valid 6-digit Indian pincode.' };
      case 'date': {
        const dateValue = new Date(value);
        if (Number.isNaN(dateValue.getTime())) {
          return { valid: false, message: 'Select a valid date.' };
        }
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        if (dateValue < today) {
          return { valid: false, message: 'Date cannot be in the past.' };
        }
        return { valid: true, message: '' };
      }
      case 'yesno': {
        const normalized = value.toLowerCase();
        return normalized === 'yes' || normalized === 'no'
          ? { valid: true, message: '' }
          : { valid: false, message: 'Select Yes or No as configured by Admin.' };
      }
      case 'filename': {
        const allowed = (field.dataset.allowedExt || '')
          .split(',')
          .map((ext) => ext.trim().toLowerCase())
          .filter(Boolean);
        const match = value.toLowerCase().match(/\.([a-z0-9]+)$/);
        if (!match || (allowed.length > 0 && !allowed.includes(match[1]))) {
          return { valid: false, message: `File must be ${allowed.join(', ')}` };
        }
        if (value.length > 120) {
          return { valid: false, message: 'File name is too long.' };
        }
        return { valid: true, message: '' };
      }
      case 'filesize': {
        const maxSize = Number.parseFloat(field.dataset.maxSize || '25');
        const size = Number.parseFloat(value);
        if (!Number.isFinite(size) || size <= 0) {
          return { valid: false, message: 'Enter a positive file size.' };
        }
        if (size > maxSize) {
          return { valid: false, message: `File size exceeds ${maxSize} MB limit.` };
        }
        return { valid: true, message: '' };
      }
      default:
        return { valid: true, message: '' };
    }
  }

  function renderSparkline(container, history) {
    if (!container) return;
    container.innerHTML = '';
    if (!Array.isArray(history) || history.length < 2) {
      container.classList.add('is-empty');
      return;
    }
    const width = 96;
    const height = 32;
    const padding = 4;
    const min = Math.min(...history);
    const max = Math.max(...history);
    const range = max - min || 1;
    const step = (width - padding * 2) / (history.length - 1);
    const points = history
      .map((value, index) => {
        const x = padding + step * index;
        const normalized = (value - min) / range;
        const y = height - padding - normalized * (height - padding * 2);
        return `${x.toFixed(1)},${y.toFixed(1)}`;
      })
      .join(' ');
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
    svg.setAttribute('aria-hidden', 'true');
    const polyline = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
    polyline.setAttribute('points', points);
    polyline.setAttribute('fill', 'none');
    polyline.setAttribute('stroke', 'currentColor');
    polyline.setAttribute('stroke-width', '2');
    svg.appendChild(polyline);
    const lastPoint = points.split(' ').pop();
    if (lastPoint) {
      const [x, y] = lastPoint.split(',').map(Number.parseFloat);
      if (Number.isFinite(x) && Number.isFinite(y)) {
        const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        circle.setAttribute('cx', x.toFixed(1));
        circle.setAttribute('cy', y.toFixed(1));
        circle.setAttribute('r', '3');
        svg.appendChild(circle);
      }
    }
    container.appendChild(svg);
  }

  function renderAnalyticsCards() {
    analyticsCards.forEach((card) => {
      const value = Number.parseFloat(card.dataset.metricValue || '0');
      const target = Number.parseFloat(card.dataset.metricTarget || '0');
      const progress = Number.isFinite(target) && target > 0 ? Math.min(100, Math.max(0, Math.round((value / target) * 100))) : 100;
      const progressEl = card.querySelector('[data-metric-progress]');
      if (progressEl) {
        progressEl.style.setProperty('--metric-progress', `${progress}%`);
        progressEl.setAttribute('aria-valuenow', String(progress));
        progressEl.setAttribute('aria-valuemin', '0');
        progressEl.setAttribute('aria-valuemax', '100');
        progressEl.title = `${progress}% of target achieved`;
      }
      const history = (card.dataset.metricHistory || '')
        .split(',')
        .map((valueText) => Number.parseFloat(valueText))
        .filter((num) => Number.isFinite(num));
      const sparkline = card.querySelector('[data-metric-sparkline]');
      if (sparkline) {
        renderSparkline(sparkline, history);
      }
      card.dataset.metricProgress = String(progress);
    });
  }

  function setNotificationRead(item, read) {
    if (!item) return;
    const isRead = Boolean(read);
    item.dataset.notificationRead = isRead ? 'true' : 'false';
    item.classList.toggle('is-read', isRead);
    item.classList.toggle('is-unread', !isRead);
    const toggleButton = item.querySelector('[data-notification-action="toggle"]');
    if (toggleButton) {
      toggleButton.textContent = isRead ? 'Mark unread' : 'Mark read';
    }
    const id = item.dataset.notificationId;
    if (id && notificationSummaryMap.has(id)) {
      const summaryItem = notificationSummaryMap.get(id);
      summaryItem.dataset.notificationRead = isRead ? 'true' : 'false';
      summaryItem.classList.toggle('is-read', isRead);
      summaryItem.classList.toggle('is-unread', !isRead);
    }
  }

  function syncNotificationCount() {
    if (!notificationListNode) {
      if (notificationCount) {
        notificationCount.textContent = '0';
      }
      if (notificationSecondaryCount) {
        notificationSecondaryCount.textContent = '0';
      }
      return;
    }
    const items = Array.from(notificationListNode.querySelectorAll('[data-notification-item]'));
    const unread = items.filter((node) => node.dataset.notificationRead !== 'true').length;
    if (notificationCount) {
      notificationCount.textContent = String(unread);
    }
    if (notificationSecondaryCount) {
      notificationSecondaryCount.textContent = String(unread);
    }
  }

  if (analyticsCards.length > 0) {
    renderAnalyticsCards();
  }

  validatedForms.forEach((form) => {
    const fields = Array.from(form.querySelectorAll('[data-validate]'));
    fields.forEach((field) => {
      clearFieldError(field);
      field.addEventListener('input', () => {
        clearFieldError(field);
      });
      field.addEventListener('change', () => {
        clearFieldError(field);
      });
    });
    form.addEventListener('submit', (event) => {
      const issues = [];
      fields.forEach((field) => {
        const rules = (field.dataset.validate || '')
          .split(',')
          .map((rule) => rule.trim())
          .filter(Boolean);
        rules.forEach((rule) => {
          const result = runValidationRule(rule, field.value, field);
          if (!result.valid) {
            issues.push({ field, message: result.message, rule });
          }
        });
      });
      if (issues.length > 0) {
        event.preventDefault();
        event.stopImmediatePropagation();
        issues.forEach((issue) => {
          showFieldError(issue.field, issue.message);
          const source = form.dataset.complianceSource || 'Form submission';
          const fieldName = issue.field.name || issue.field.id || issue.rule || 'field';
          flagComplianceIssue(source, fieldName, issue.message);
        });
        if (issues[0]?.field) {
          issues[0].field.focus();
        }
        const sourceLabel = form.dataset.complianceSource || 'Form submission';
        recordAuditEvent('Validation blocked', `${sourceLabel}: ${issues[0]?.message || 'Check required fields'}`);
      }
    });
  });

  updateComplianceCount();

  if (notificationOpeners.length > 0 && notificationPanel) {
    notificationOpeners.forEach((button) => {
      button.addEventListener('click', () => {
        openDrawer(notificationPanel);
        recordAuditEvent('Notifications viewed');
      });
    });
  }

  if (notificationClose && notificationPanel) {
    notificationClose.addEventListener('click', () => {
      closeDrawer(notificationPanel);
    });
  }

  if (notificationListNode) {
    notificationListNode.addEventListener('click', (event) => {
      const toggleButton = event.target.closest('[data-notification-action="toggle"]');
      if (toggleButton) {
        const item = toggleButton.closest('[data-notification-item]');
        if (!item) return;
        const shouldMarkRead = item.dataset.notificationRead !== 'true';
        setNotificationRead(item, shouldMarkRead);
        syncNotificationCount();
        const rawId = item.dataset.notificationId || '';
        const numericId = Number.parseInt(rawId.replace(/^N-/, ''), 10);
        if (!Number.isNaN(numericId)) {
          api('mark-notification', {
            method: 'POST',
            body: { id: numericId, status: shouldMarkRead ? 'read' : 'unread' },
          }).catch((error) => {
            console.error(error);
            setNotificationRead(item, !shouldMarkRead);
            syncNotificationCount();
          });
        }
        const title = item.querySelector('.notification-item__title');
        recordAuditEvent(shouldMarkRead ? 'Notification read' : 'Notification marked unread', title ? title.textContent : 'Alert');
        return;
      }
      const followLink = event.target.closest('[data-notification-action="follow"]');
      if (followLink) {
        const item = followLink.closest('[data-notification-item]');
        if (!item) return;
        setNotificationRead(item, true);
        syncNotificationCount();
        const rawId = item.dataset.notificationId || '';
        const numericId = Number.parseInt(rawId.replace(/^N-/, ''), 10);
        if (!Number.isNaN(numericId)) {
          api('mark-notification', {
            method: 'POST',
            body: { id: numericId, status: 'read' },
          }).catch((error) => {
            console.error(error);
          });
        }
        const title = item.querySelector('.notification-item__title');
        recordAuditEvent('Notification opened', title ? title.textContent : 'Alert');
      }
    });
  }

  if (notificationMarkAll && notificationListNode) {
    notificationMarkAll.addEventListener('click', () => {
      notificationListNode.querySelectorAll('[data-notification-item]').forEach((item) => {
        setNotificationRead(item, true);
      });
      syncNotificationCount();
      api('mark-all-notifications', { method: 'POST' }).catch((error) => {
        console.error(error);
      });
      recordAuditEvent('Notifications marked read', 'All alerts cleared');
    });
  }

  if (profileOpeners.length > 0 && profilePanel) {
    profileOpeners.forEach((button) => {
      button.addEventListener('click', () => {
        openDrawer(profilePanel);
      });
    });
  }

  if (profileClose && profilePanel) {
    profileClose.addEventListener('click', () => {
      closeDrawer(profilePanel);
    });
  }

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    if (notificationPanel && notificationPanel.classList.contains('is-open')) {
      closeDrawer(notificationPanel);
    }
    if (profilePanel && profilePanel.classList.contains('is-open')) {
      closeDrawer(profilePanel);
    }
  });

  syncNotificationCount();

  if (profileForm && profileAlert) {
    profileForm.addEventListener('submit', (event) => {
      if (event.defaultPrevented) return;
      event.preventDefault();
      const data = new FormData(profileForm);
      const phone = String(data.get('phone') || '').trim();
      const photo = String(data.get('photo') || '').trim();
      const reviewDate = String(data.get('compliance_review') || '').trim();
      const details = [];
      if (phone) details.push(`Phone: ${phone}`);
      if (photo) details.push('Photo update submitted');
      if (reviewDate) details.push(`Compliance review: ${reviewDate}`);
      profileAlert.textContent = 'Profile changes queued for Admin approval.';
      profileAlert.classList.add('is-success');
      window.setTimeout(() => {
        profileAlert.textContent = '';
        profileAlert.classList.remove('is-success');
      }, 5000);
      const detailText = details.join(' · ') || 'No field changes detected';
      recordAuditEvent('Profile update submitted', detailText);
      logCommunicationEntry('profile', 'Profile update queued for Admin review', detailText, 'You');
    });
  }

  if (feedbackForm && feedbackLog) {
    feedbackForm.addEventListener('submit', (event) => {
      if (event.defaultPrevented) return;
      event.preventDefault();
      const data = new FormData(feedbackForm);
      const subject = String(data.get('subject') || '').trim();
      const message = String(data.get('message') || '').trim();
      const followUp = String(data.get('follow_up') || 'No').trim() || 'No';
      if (!subject || !message) return;
      if (feedbackEmpty && feedbackEmpty.parentElement) {
        feedbackEmpty.remove();
      }
      const now = new Date();
      const item = document.createElement('li');
      const titleEl = document.createElement('strong');
      titleEl.textContent = subject;
      item.appendChild(titleEl);
      const meta = document.createElement('span');
      meta.className = 'text-xs text-muted d-block';
      meta.textContent = `${formatDateTime(now)} · Follow-up: ${followUp}`;
      item.appendChild(meta);
      const body = document.createElement('p');
      body.textContent = message;
      item.appendChild(body);
      feedbackLog.prepend(item);
      feedbackForm.reset();
      if (feedbackAlert) {
        feedbackAlert.textContent = 'Feedback sent to Admin.';
        feedbackAlert.classList.add('is-success');
        window.setTimeout(() => {
          feedbackAlert.textContent = '';
          feedbackAlert.classList.remove('is-success');
        }, 5000);
      }
      logCommunicationEntry('feedback', subject, message, 'You');
      recordAuditEvent('Feedback submitted', `${subject} · Follow-up: ${followUp}`);
    });
  }

  if (analyticsReportButton) {
    analyticsReportButton.addEventListener('click', () => {
      if (analyticsCards.length === 0) {
        window.alert('No analytics available to download yet.');
        return;
      }
      const rows = [['Metric', 'Value', 'Target', 'Trend', 'Unit']];
      analyticsCards.forEach((card) => {
        const label = card.querySelector('.analytics-metric__label');
        const value = card.querySelector('.analytics-metric__value');
        rows.push([
          label ? label.textContent.trim() : card.dataset.metricId || 'Metric',
          value ? value.textContent.trim().replace(/\s+/g, ' ') : '',
          card.dataset.metricTarget || '',
          card.dataset.metricTrend || '',
          card.dataset.metricUnit || '',
        ]);
      });
      const csv = rows
        .map((row) => row.map((value) => `"${String(value).replace(/"/g, '""')}"`).join(','))
        .join('\n');
      const blob = new Blob([csv], { type: 'text/csv' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      const period = analyticsReportButton.dataset.reportPeriod || 'current';
      link.href = url;
      link.download = `employee-performance-${period}.csv`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(url);
      recordAuditEvent('Downloaded performance report', `Period ${period}`);
      logCommunicationEntry('system', 'Performance report downloaded', `Period ${period}`, 'System');
    });
  }

  function logCommunicationEntry(channelKey, summary, metaText = '', actorLabel = '') {
    if (!communicationLog) return;
    const now = new Date();
    const item = document.createElement('li');
    const meta = document.createElement('div');
    meta.className = 'communication-log-meta';
    const normalizedChannel = (channelKey || 'system').toLowerCase();
    const tag = document.createElement('span');
    tag.className = `communication-channel communication-channel--${normalizedChannel}`;
    const label = actorLabel || normalizedChannel.replace(/^./, (char) => char.toUpperCase());
    tag.textContent = label;
    meta.appendChild(tag);
    const timeEl = document.createElement('time');
    timeEl.dateTime = now.toISOString();
    timeEl.textContent = formatDateTime(now);
    meta.appendChild(timeEl);
    item.appendChild(meta);
    const summaryEl = document.createElement('p');
    summaryEl.textContent = summary;
    if (metaText) {
      summaryEl.appendChild(document.createElement('br'));
      const detail = document.createElement('span');
      detail.className = 'text-xs text-muted d-block';
      detail.textContent = metaText;
      summaryEl.appendChild(detail);
    }
    item.appendChild(summaryEl);
    communicationLog.prepend(item);
  }

  function logVisitActivity(message) {
    if (!visitActivity) return;
    const now = new Date();
    const entry = document.createElement('li');
    const timeEl = document.createElement('time');
    timeEl.dateTime = now.toISOString();
    timeEl.textContent = formatDateTime(now);
    const messageEl = document.createElement('p');
    messageEl.textContent = message;
    entry.appendChild(timeEl);
    entry.appendChild(messageEl);
    visitActivity.prepend(entry);
  }

  function syncVisitSummary() {
    if (!visitSummary) return;
    const active = visitCards.filter((card) => card.dataset.visitStatus !== 'completed').length;
    visitSummary.textContent = String(active);
  }

  function logSubsidyActivity(message) {
    if (!subsidyActivity) return;
    const now = new Date();
    const entry = document.createElement('li');
    const timeEl = document.createElement('time');
    timeEl.dateTime = now.toISOString();
    timeEl.textContent = formatDateTime(now);
    const messageEl = document.createElement('p');
    messageEl.textContent = message;
    entry.appendChild(timeEl);
    entry.appendChild(messageEl);
    subsidyActivity.prepend(entry);
  }

  function logWarrantyActivity(message) {
    if (!warrantyActivity) return;
    const now = new Date();
    const entry = document.createElement('li');
    const timeEl = document.createElement('time');
    timeEl.dateTime = now.toISOString();
    timeEl.textContent = formatDateTime(now);
    const messageEl = document.createElement('p');
    messageEl.textContent = message;
    entry.appendChild(timeEl);
    entry.appendChild(messageEl);
    warrantyActivity.prepend(entry);
  }

  // Theme toggle -------------------------------------------------------------
  const savedTheme = localStorage.getItem(THEME_KEY);
  if (savedTheme === 'dark' || savedTheme === 'light') {
    body.setAttribute('data-dashboard-theme', savedTheme);
    themeInputs.forEach((input) => {
      input.checked = input.value === savedTheme;
    });
  }

  themeInputs.forEach((input) => {
    input.addEventListener('change', () => {
      if (!input.checked) return;
      const value = input.value === 'dark' ? 'dark' : 'light';
      body.setAttribute('data-dashboard-theme', value);
      try {
        localStorage.setItem(THEME_KEY, value);
      } catch (err) {
        console.warn('Theme preference could not be saved:', err);
      }
    });
  });

  // Quick navigation ---------------------------------------------------------
  if (sectionNodes.length > 0 && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          const match = sectionNodes.find((node) => node.section === entry.target);
          if (!match) return;
          quickLinks.forEach((link) => link.classList.remove('is-active'));
          match.link.classList.add('is-active');
        });
      },
      { rootMargin: '-45% 0px -45% 0px', threshold: 0.1 }
    );
    sectionNodes.forEach((node) => observer.observe(node.section));
  }

  // Ticket workflow ----------------------------------------------------------
  const STATUS_LABELS = {
    in_progress: { label: 'In Progress', tone: 'progress' },
    awaiting_response: { label: 'Awaiting Response', tone: 'waiting' },
    resolved: { label: 'Resolved', tone: 'resolved' },
    escalated: { label: 'Escalated to Admin', tone: 'escalated' },
  };

  function updateTicketStatus(card, statusKey) {
    const config = STATUS_LABELS[statusKey] || STATUS_LABELS.in_progress;
    card.dataset.status = statusKey;
    const statusNode = card.querySelector('[data-ticket-status-label]');
    if (statusNode) {
      statusNode.textContent = config.label;
      statusNode.className = `dashboard-status dashboard-status--${config.tone}`;
    }
    syncTicketSummary();
    const reference = card.dataset.ticketId;
    if (reference) {
      api('update-complaint-status', {
        method: 'POST',
        body: { reference, status: statusKey },
      })
        .then((data) => {
          refreshSyncIndicator(`Ticket ${reference}`);
          if (data.complaint) {
            applyComplaintUpdate(data.complaint);
          }
        })
        .catch((error) => {
          console.error(error);
          window.alert('Unable to sync ticket update with Admin. Please retry.');
        });
    }
  }

  function logTicketTimeline(card, message) {
    const timeline = card.querySelector('[data-ticket-timeline]');
    if (!timeline) return;
    const now = new Date();
    const item = document.createElement('li');
    const timeEl = document.createElement('time');
    timeEl.dateTime = now.toISOString();
    timeEl.textContent = formatTime(now);
    const messageEl = document.createElement('p');
    messageEl.innerHTML = message;
    item.appendChild(timeEl);
    item.appendChild(messageEl);
    timeline.prepend(item);
    const ticketLabel = card.dataset.ticketId ? `Ticket ${card.dataset.ticketId}` : 'Ticket update';
    logCommunicationEntry('system', `${ticketLabel}: ${textFromHTML(message)}`, '', 'System');
  }

  function syncTicketSummary() {
    if (!ticketSummary) return;
    const active = ticketCards.filter((card) => card.dataset.status !== 'resolved').length;
    ticketSummary.textContent = String(active);
  }

  ticketCards.forEach((card) => {
    const heading = card.querySelector('h3');
    const ticketLabel = heading ? heading.textContent.trim() : card.dataset.ticketId ? `Ticket ${card.dataset.ticketId}` : 'Ticket';
    const statusControl = card.querySelector('[data-ticket-status]');
    if (statusControl) {
      statusControl.addEventListener('change', () => {
        const value = statusControl.value;
        updateTicketStatus(card, value);
        logTicketTimeline(card, `Status updated to <strong>${STATUS_LABELS[value]?.label ?? value}</strong>.`);
        recordAuditEvent('Ticket status updated', `${ticketLabel} → ${STATUS_LABELS[value]?.label ?? value}`);
      });
    }

    const noteButton = card.querySelector('[data-ticket-note]');
    if (noteButton) {
      noteButton.addEventListener('click', () => {
        const note = window.prompt('Add an internal note for this ticket:');
        if (!note) return;
        api('add-complaint-note', {
          method: 'POST',
          body: { reference: card.dataset.ticketId, note },
        })
          .then((data) => {
            if (data.complaint) {
              applyComplaintUpdate(data.complaint);
            }
            refreshSyncIndicator(`Ticket ${card.dataset.ticketId}`);
            recordAuditEvent('Ticket note added', `${ticketLabel} · ${note}`);
            logCommunicationEntry('system', `Note added to ${ticketLabel}`, note, 'You');
          })
          .catch((error) => {
            console.error(error);
            window.alert('Unable to sync note with Admin. Please retry.');
          });
      });
    }

    const escalateButton = card.querySelector('[data-ticket-escalate]');
    if (escalateButton) {
      escalateButton.addEventListener('click', () => {
        updateTicketStatus(card, 'escalated');
        logTicketTimeline(card, 'Ticket escalated back to <strong>Admin</strong> for review.');
        const statusControlNode = card.querySelector('[data-ticket-status]');
        if (statusControlNode) {
          statusControlNode.value = 'escalated';
        }
        recordAuditEvent('Ticket escalated', ticketLabel);
      });
    }
  });

  syncTicketSummary();

  // Field visits ------------------------------------------------------------
  visitCards.forEach((card) => {
    const statusLabel = card.querySelector('[data-visit-status]');
    const completeButton = card.querySelector('[data-visit-complete]');
    const geotagButton = card.querySelector('[data-visit-geotag]');

    if (completeButton) {
      completeButton.addEventListener('click', () => {
        if (card.dataset.visitStatus === 'completed') {
          window.alert('This visit is already marked complete and is awaiting Admin review.');
          return;
        }
        const note = window.prompt('Add completion notes for Admin review:', '') || '';
        card.dataset.visitStatus = 'completed';
        if (statusLabel) {
          statusLabel.textContent = 'Completed · Pending Admin review';
          statusLabel.className = 'dashboard-status dashboard-status--resolved';
        }
        const visitId = card.dataset.visitId || 'Visit';
        const customer = card.dataset.visitCustomer || '';
        const cleanNote = note.trim();
        const visitSummaryMessage = cleanNote ? `${visitId} marked completed – ${cleanNote}.` : `${visitId} marked completed.`;
        logVisitActivity(`${visitSummaryMessage} Admin review pending.`);
        const metaText = [customer, cleanNote].filter(Boolean).join(' · ');
        logCommunicationEntry('visit', `${visitId} completed`, metaText, 'System');
        syncVisitSummary();
        recordAuditEvent('Visit completed', [visitId, customer, cleanNote].filter(Boolean).join(' · '));
      });
    }

    if (geotagButton) {
      geotagButton.addEventListener('click', () => {
        const existingTag = card.dataset.visitGeotag || '';
        const input = window.prompt('Enter geo-tag coordinates (lat, long):', existingTag || '23.3441, 85.3096');
        if (!input) return;
        card.dataset.visitGeotag = input;
        const wrapper = card.querySelector('[data-visit-geotag-wrapper]');
        const label = card.querySelector('[data-visit-geotag-label]');
        if (wrapper && label) {
          label.textContent = input;
          wrapper.hidden = false;
        }
        const visitId = card.dataset.visitId || 'Visit';
        logVisitActivity(`${visitId} geo-tag captured (${input}).`);
        logCommunicationEntry('visit', `${visitId} geo-tag recorded`, input, 'System');
        recordAuditEvent('Visit geo-tag captured', `${visitId} · ${input}`);
      });
    }
  });

  syncVisitSummary();

  // Document vault ----------------------------------------------------------
  if (documentForm && documentList) {
    documentForm.addEventListener('submit', (event) => {
      if (event.defaultPrevented) return;
      event.preventDefault();
      const data = new FormData(documentForm);
      const customer = String(data.get('customer') || '').trim();
      const type = String(data.get('type') || '').trim();
      const filename = String(data.get('filename') || '').trim();
      const note = String(data.get('note') || '').trim();
      const sizeValue = String(data.get('file_size') || '').trim();
      const sizeNumber = sizeValue ? Number.parseFloat(sizeValue) : null;
      if (!customer || !type || !filename) {
        window.alert('Please provide the customer, document type, and file name.');
        return;
      }
      api('upload-document', {
        method: 'POST',
        body: {
          reference: customer,
          type,
          filename,
          note,
          file_size: sizeValue,
        },
      })
        .then((data) => {
          documentForm.reset();
          if (data.document) {
            prependDocumentRow(data.document, sizeNumber);
          }
          if (data.complaint) {
            applyComplaintUpdate(data.complaint);
          }
          const metaText = [customer, note || filename].filter(Boolean).join(' · ');
          logCommunicationEntry('system', `${type} uploaded to document vault`, metaText, 'You');
          const auditDetailParts = [type, customer];
          if (Number.isFinite(sizeNumber)) {
            auditDetailParts.push(`${sizeNumber.toFixed(1)} MB`);
          }
          recordAuditEvent('Document uploaded', auditDetailParts.join(' · '));
          refreshSyncIndicator(`Ticket ${customer}`);
        })
        .catch((error) => {
          console.error(error);
          window.alert('Unable to upload document to Admin. Please retry.');
        });
    });
  }

  // Subsidy workflow --------------------------------------------------------
  if (subsidyBoard) {
    subsidyBoard.addEventListener('click', (event) => {
      const button = event.target.closest('[data-subsidy-action]');
      if (!button) return;
      const card = button.closest('[data-subsidy-case]');
      if (!card) return;
      const stage = button.getAttribute('data-subsidy-stage');
      if (!stage) return;
      const stageNode = card.querySelector(`[data-subsidy-stage="${stage}"]`);
      if (!stageNode) return;
      if (stageNode.dataset.subsidyCompleted === 'true') {
        window.alert('This stage is already complete and awaiting Admin approval.');
        return;
      }
      stageNode.dataset.subsidyCompleted = 'true';
      stageNode.classList.add('is-complete');
      button.disabled = true;
      button.textContent = 'Completed';
      const stageLabel = button.dataset.stageLabel || stage;
      const caseId = card.getAttribute('data-subsidy-case') || 'Case';
      const caseName = card.querySelector('h3')?.textContent?.trim() || caseId;
      logSubsidyActivity(`${stageLabel} stage completed for ${caseId}.`);
      logCommunicationEntry('system', `Subsidy ${stageLabel} marked complete`, caseName, 'System');
      recordAuditEvent('Subsidy stage completed', `${caseId} · ${stageLabel}`);
    });
  }

  // Warranty tracker --------------------------------------------------------
  warrantyRows.forEach((row) => {
    const logButton = row.querySelector('[data-warranty-log]');
    if (!logButton) return;
    logButton.addEventListener('click', () => {
      const summary = window.prompt('Add service visit details or issues found:', '') || '';
      if (!summary.trim()) {
        window.alert('Please enter visit details before logging the update.');
        return;
      }
      const geoTag = window.prompt('Enter geo-tag coordinates for proof (optional):', row.dataset.warrantyGeotag || '') || '';
      if (geoTag.trim()) {
        row.dataset.warrantyGeotag = geoTag.trim();
      }
      const statusLabel = row.querySelector('[data-warranty-status-label]');
      if (statusLabel) {
        statusLabel.textContent = 'Completed · Pending Admin review';
        statusLabel.className = 'dashboard-status dashboard-status--resolved';
      }
      const warrantyId = row.dataset.warrantyId || 'Asset';
      const assetName = row.dataset.warrantyAsset || warrantyId;
      const customer = row.dataset.warrantyCustomer || '';
      const details = [summary.trim()];
      if (geoTag.trim()) {
        details.push(`Geo-tag: ${geoTag.trim()}`);
      }
      logWarrantyActivity(`${warrantyId} · ${details.join(' | ')}`);
      const metaText = [customer, geoTag.trim()].filter(Boolean).join(' · ');
      logCommunicationEntry('system', `Warranty update for ${assetName}`, metaText ? `${metaText} · ${summary.trim()}` : summary.trim(), 'System');
      const auditDetails = [warrantyId, assetName, summary.trim()].filter(Boolean).join(' · ');
      recordAuditEvent('Warranty update logged', auditDetails);
    });
  });

  // Communication log -------------------------------------------------------
  if (communicationForm && communicationLog) {
    communicationForm.addEventListener('submit', (event) => {
      event.preventDefault();
      const data = new FormData(communicationForm);
      const customer = String(data.get('customer') || '').trim();
      const channel = String(data.get('channel') || 'call').toLowerCase();
      const summary = String(data.get('summary') || '').trim();
      if (!customer || !summary) {
        window.alert('Please provide both the customer and a summary for the communication log.');
        return;
      }
      logCommunicationEntry(channel, summary, customer, 'You');
      communicationForm.reset();
      recordAuditEvent('Communication logged', `${customer} · ${summary}`);
    });
  }

  // AI assistance -----------------------------------------------------------
  if (aiForm && aiOutput) {
    aiForm.addEventListener('submit', (event) => {
      event.preventDefault();
      const data = new FormData(aiForm);
      const purpose = String(data.get('purpose') || 'summary');
      const context = String(data.get('context') || '').trim();
      let response = '';
      switch (purpose) {
        case 'followup':
          response = `Follow-up message template:\nHi Customer,\n${context || 'Thank you for today\'s visit. The system is operating within expected parameters.'}\nLet us know if you need any additional support.\n— Dakshayani Service Team`;
          break;
        case 'caption':
          response = `Suggested caption:\n"${context || '5 kW rooftop installation commissioned under PM Surya Ghar subsidy.'}"\n#SolarEnergy #Dakshayani`; 
          break;
        default:
          response = `Gemini summary draft:\n• ${context || 'Commissioning checklist completed and photos uploaded for Admin review.'}\n• Pending Admin sign-off before closing the ticket.`;
          break;
      }
      aiOutput.textContent = response;
      const metaText = context ? `Prompt: ${context}` : 'Gemini workspace';
      logCommunicationEntry('system', `AI ${purpose} suggestion generated`, metaText, 'System');
    });
  }

  // Task board ---------------------------------------------------------------
  function syncTaskCounts() {
    const totals = { todo: 0, in_progress: 0, done: 0 };
    taskColumns.forEach((column) => {
      const status = column.dataset.taskColumn;
      const cards = column.querySelectorAll('.task-card');
      const count = cards.length;
      const countNode = column.querySelector('[data-task-count]');
      if (countNode) {
        countNode.textContent = `(${count})`;
      }
      if (status && Object.prototype.hasOwnProperty.call(totals, status)) {
        totals[status] = count;
      }
    });
    if (pendingSummary) {
      const pendingTotal = totals.todo + totals.in_progress;
      pendingSummary.textContent = String(pendingTotal);
    }
  }

  function logTaskActivity(card, statusLabel) {
    if (!taskActivity) return;
    const now = new Date();
    const item = document.createElement('li');
    const timeEl = document.createElement('time');
    timeEl.dateTime = now.toISOString();
    timeEl.textContent = formatDateTime(now);
    const message = document.createElement('p');
    const title = card.querySelector('.task-card-title');
    message.innerHTML = `${title ? title.textContent : 'Task'} moved to <strong>${statusLabel}</strong>.`;
    item.appendChild(timeEl);
    item.appendChild(message);
    taskActivity.prepend(item);
    const taskName = title ? title.textContent.trim() : card.dataset.taskId || 'Task';
    logCommunicationEntry('system', `${taskName} moved to ${statusLabel}.`, '', 'System');
    recordAuditEvent('Task moved', `${taskName} → ${statusLabel}`);
  }

  function moveTaskToColumn(card, column) {
    const body = column.querySelector('.task-column-body');
    if (!body) return;
    body.appendChild(card);
    card.focus({ preventScroll: true });
    const label = column.dataset.statusLabel || 'Updated';
    logTaskActivity(card, label);
    syncTaskCounts();
    const status = column.dataset.taskColumn || 'todo';
    const taskId = card.dataset.taskId;
    if (taskId) {
      api('update-task-status', {
        method: 'POST',
        body: { id: Number.parseInt(taskId, 10), status },
      })
        .then(() => refreshSyncIndicator(`Task ${taskId}`))
        .catch((error) => {
          console.error(error);
          window.alert('Unable to sync task update with Admin. Please retry.');
        });
    }
  }

  if (taskBoard) {
    taskColumns.forEach((column) => {
      column.addEventListener('dragover', (event) => {
        event.preventDefault();
        column.classList.add('is-drop-target');
      });
      column.addEventListener('dragleave', () => {
        column.classList.remove('is-drop-target');
      });
      column.addEventListener('drop', (event) => {
        event.preventDefault();
        column.classList.remove('is-drop-target');
        const taskId = event.dataTransfer?.getData('text/plain');
        if (!taskId) return;
        const card = taskBoard.querySelector(`[data-task-id="${CSS.escape(taskId)}"]`);
        if (!card) return;
        moveTaskToColumn(card, column);
      });
    });

    taskBoard.querySelectorAll('.task-card').forEach((card) => {
      card.setAttribute('tabindex', '0');
      card.addEventListener('dragstart', (event) => {
        event.dataTransfer?.setData('text/plain', card.dataset.taskId || '');
        event.dataTransfer?.setDragImage(card, 20, 20);
      });
    });

    taskBoard.addEventListener('dragend', () => {
      taskColumns.forEach((column) => column.classList.remove('is-drop-target'));
    });

    taskBoard.addEventListener('click', (event) => {
      const completeButton = event.target.closest('[data-task-complete]');
      const undoButton = event.target.closest('[data-task-undo]');
      if (!completeButton && !undoButton) return;
      const card = event.target.closest('.task-card');
      if (!card) return;
      if (completeButton) {
        const doneColumn = taskColumns.find((column) => column.dataset.taskColumn === 'done');
        if (doneColumn) {
          moveTaskToColumn(card, doneColumn);
        }
      } else if (undoButton) {
        const todoColumn = taskColumns.find((column) => column.dataset.taskColumn === 'todo');
        if (todoColumn) {
          moveTaskToColumn(card, todoColumn);
        }
      }
    });

    taskBoard.addEventListener('dragenter', (event) => {
      if (!(event.target instanceof HTMLElement)) return;
      const column = event.target.closest('[data-task-column]');
      if (column) {
        column.classList.add('is-drop-target');
      }
    });
  }

  syncTaskCounts();

  // Lead follow-ups ----------------------------------------------------------
  if (leadNoteForm && leadActivity) {
    leadNoteForm.addEventListener('submit', (event) => {
      event.preventDefault();
      const formData = new FormData(leadNoteForm);
      const leadId = formData.get('lead');
      const note = String(formData.get('note') || '').trim();
      if (!leadId || note.length === 0) {
        return;
      }
      const now = new Date();
      const item = document.createElement('li');
      const timeEl = document.createElement('time');
      timeEl.dateTime = now.toISOString();
      timeEl.textContent = formatDateTime(now);
      const message = document.createElement('p');
      message.textContent = note;
      item.appendChild(timeEl);
      item.appendChild(message);
      leadActivity.prepend(item);
      leadNoteForm.reset();
      const select = leadNoteForm.querySelector('select[name="lead"]');
      const selectedLabel = select && select.options[select.selectedIndex] ? select.options[select.selectedIndex].textContent.trim() : '';
      logCommunicationEntry('lead', 'Lead update recorded', [selectedLabel, note].filter(Boolean).join(' · '), 'You');
      recordAuditEvent('Lead note logged', selectedLabel ? `${selectedLabel} · ${note}` : note);
    });
  }

  if (leadIntakeForm && pendingLeads) {
    leadIntakeForm.addEventListener('submit', (event) => {
      if (event.defaultPrevented) return;
      event.preventDefault();
      const data = new FormData(leadIntakeForm);
      const name = String(data.get('prospect') || '').trim();
      const location = String(data.get('location') || '').trim();
      const contact = String(data.get('contact') || '').trim();
      const pincode = String(data.get('pincode') || '').trim();
      const visitDate = String(data.get('visit_date') || '').trim();
      if (!name || !location || !contact || !pincode) {
        return;
      }
      const entry = document.createElement('li');
      const title = document.createElement('strong');
      title.textContent = name;
      const meta = document.createElement('span');
      const metaParts = ['Pending Admin approval', `${location} (${pincode})`, contact];
      if (visitDate) {
        metaParts.push(`Preferred visit: ${visitDate}`);
      }
      meta.textContent = metaParts.filter(Boolean).join(' · ');
      entry.appendChild(title);
      entry.appendChild(meta);
      pendingLeads.prepend(entry);
      leadIntakeForm.reset();
      const detailParts = [name, location, pincode, contact];
      if (visitDate) {
        detailParts.push(`Visit: ${visitDate}`);
      }
      const detailText = detailParts.filter(Boolean).join(' · ');
      logCommunicationEntry('lead', 'New lead submitted for Admin approval', detailText, 'You');
      recordAuditEvent('Lead submitted', detailText);
    });
  }

})();
