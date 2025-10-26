(function () {
  'use strict';

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
    const statusControl = card.querySelector('[data-ticket-status]');
    if (statusControl) {
      statusControl.addEventListener('change', () => {
        const value = statusControl.value;
        updateTicketStatus(card, value);
        logTicketTimeline(card, `Status updated to <strong>${STATUS_LABELS[value]?.label ?? value}</strong>.`);
      });
    }

    const noteButton = card.querySelector('[data-ticket-note]');
    if (noteButton) {
      noteButton.addEventListener('click', () => {
        const note = window.prompt('Add an internal note for this ticket:');
        if (!note) return;
        logTicketTimeline(card, `Note added by you: ${note.replace(/</g, '&lt;')}`);
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
      });
    }
  });

  syncVisitSummary();

  // Document vault ----------------------------------------------------------
  if (documentForm && documentList) {
    documentForm.addEventListener('submit', (event) => {
      event.preventDefault();
      const data = new FormData(documentForm);
      const customer = String(data.get('customer') || '').trim();
      const type = String(data.get('type') || '').trim();
      const filename = String(data.get('filename') || '').trim();
      const note = String(data.get('note') || '').trim();
      if (!customer || !type || !filename) {
        window.alert('Please provide the customer, document type, and file name.');
        return;
      }
      const row = document.createElement('tr');

      const docCell = document.createElement('td');
      const typeEl = document.createElement('strong');
      typeEl.textContent = type;
      docCell.appendChild(typeEl);
      const fileEl = document.createElement('span');
      fileEl.className = 'text-xs text-muted d-block';
      fileEl.textContent = filename;
      docCell.appendChild(fileEl);
      row.appendChild(docCell);

      const customerCell = document.createElement('td');
      customerCell.textContent = customer;
      row.appendChild(customerCell);

      const statusCell = document.createElement('td');
      const badge = document.createElement('span');
      badge.className = 'dashboard-status dashboard-status--waiting';
      badge.textContent = 'Pending Admin review';
      statusCell.appendChild(badge);
      row.appendChild(statusCell);

      const uploadedCell = document.createElement('td');
      const now = new Date();
      const timeEl = document.createElement('time');
      timeEl.dateTime = now.toISOString();
      timeEl.textContent = formatDateTime(now);
      uploadedCell.appendChild(timeEl);
      const byEl = document.createElement('span');
      byEl.className = 'text-xs text-muted d-block';
      byEl.textContent = 'by You';
      uploadedCell.appendChild(byEl);
      row.appendChild(uploadedCell);

      documentList.prepend(row);
      documentForm.reset();

      const metaText = [customer, note || filename].filter(Boolean).join(' · ');
      logCommunicationEntry('system', `${type} uploaded to document vault`, metaText, 'System');
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
  }

  function moveTaskToColumn(card, column) {
    const body = column.querySelector('.task-column-body');
    if (!body) return;
    body.appendChild(card);
    card.focus({ preventScroll: true });
    const label = column.dataset.statusLabel || 'Updated';
    logTaskActivity(card, label);
    syncTaskCounts();
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
    });
  }

  if (leadIntakeForm && pendingLeads) {
    leadIntakeForm.addEventListener('submit', (event) => {
      event.preventDefault();
      const data = new FormData(leadIntakeForm);
      const name = String(data.get('prospect') || '').trim();
      const location = String(data.get('location') || '').trim();
      const contact = String(data.get('contact') || '').trim();
      if (!name || !location || !contact) {
        return;
      }
      const entry = document.createElement('li');
      const title = document.createElement('strong');
      title.textContent = name;
      const meta = document.createElement('span');
      meta.textContent = `Pending Admin approval · ${location} · ${contact}`;
      entry.appendChild(title);
      entry.appendChild(meta);
      pendingLeads.prepend(entry);
      leadIntakeForm.reset();
    });
  }

  // Notifications badge ------------------------------------------------------
  if (notificationCount) {
    const initial = Number(notificationCount.textContent || '0');
    if (!Number.isFinite(initial) || initial < 0) {
      notificationCount.textContent = '0';
    }
  }
})();
