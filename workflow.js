(() => {
  const WORKFLOW_SELECTOR = '.workflow-main';
  const SLA_WARNING_THRESHOLD_HOURS = 24;

  const employees = [
    { id: 'EMP-001', name: 'Anita Sharma' },
    { id: 'EMP-002', name: 'Ravi Prasad' },
    { id: 'EMP-003', name: 'Sonia Dutta' },
    { id: 'EMP-004', name: 'Karan Mehta' }
  ];

  const state = {
    ticketCounter: 0,
    tickets: [],
    documents: [],
    approvals: [],
    anomalies: [],
    activityLog: [],
    errorLog: [],
    notifications: [],
    selectedTicketId: null,
    pendingMerge: null,
    docFilters: {
      type: '',
      tag: '',
      linkedEntity: '',
      dateFrom: '',
      dateTo: ''
    }
  };

  const dom = {};

  document.addEventListener('DOMContentLoaded', () => {
    if (!document.querySelector(WORKFLOW_SELECTOR)) {
      return;
    }

    cacheDom();
    populateEmployees();
    bindEvents();
    seedInitialData();
    renderAll();
    scheduleSLAWatcher();
  });

  function cacheDom() {
    dom.publicIntakeForm = document.getElementById('public-intake-form');
    dom.internalIntakeForm = document.getElementById('internal-intake-form');
    dom.publicIntakeAlert = document.querySelector('[data-public-intake-alert]');
    dom.internalIntakeAlert = document.querySelector('[data-internal-intake-alert]');
    dom.ticketList = document.querySelector('[data-ticket-list]');
    dom.ticketDetail = document.querySelector('[data-ticket-detail]');
    dom.ticketDetailTitle = document.querySelector('[data-ticket-detail-title]');
    dom.ticketDetailSub = document.querySelector('[data-ticket-detail-sub]');
    dom.ticketBadges = document.querySelector('[data-ticket-badges]');
    dom.ticketInfo = document.querySelector('[data-ticket-info]');
    dom.ticketTimeline = document.querySelector('[data-ticket-timeline]');
    dom.triageForm = document.querySelector('[data-triage-form]');
    dom.triageAssigneeSelect = dom.triageForm?.querySelector('[data-triage-assignee]');
    dom.resolutionForm = document.querySelector('[data-resolution-form]');
    dom.noteForm = document.querySelector('[data-note-form]');
    dom.ticketStatusFilter = document.querySelector('[data-ticket-status-filter]');
    dom.ticketAssigneeFilter = document.querySelector('[data-ticket-assignee-filter]');
    dom.notificationFeed = document.querySelector('[data-notification-feed]');
    dom.notificationCount = document.querySelector('[data-notification-count]');
    dom.slaReminderList = document.querySelector('[data-sla-reminders]');
    dom.taskBoard = document.querySelector('[data-task-board]');
    dom.documentUploadForm = document.getElementById('document-upload-form');
    dom.documentUploadAlert = document.querySelector('[data-document-upload-alert]');
    dom.documentFilterForm = document.getElementById('document-filter-form');
    dom.documentList = document.querySelector('[data-document-list]');
    dom.resetDocFilters = document.querySelector('[data-reset-doc-filters]');
    dom.approvalForm = document.getElementById('change-proposal-form');
    dom.approvalAlert = document.querySelector('[data-change-proposal-alert]');
    dom.approvalsList = document.querySelector('[data-approvals-list]');
    dom.anomalyForm = document.getElementById('anomaly-simulator');
    dom.anomalyList = document.querySelector('[data-anomaly-list]');
    dom.activityLog = document.querySelector('[data-activity-log]');
    dom.errorLog = document.querySelector('[data-error-log]');
    dom.slaRiskMetric = document.querySelector('[data-sla-risk-count]');
    dom.approvalMetric = document.querySelector('[data-approval-count]');
    dom.documentVersionMetric = document.querySelector('[data-document-version-count]');
    dom.anomalyMetric = document.querySelector('[data-anomaly-count]');
    dom.duplicateAlert = document.querySelector('[data-duplicate-alert]');
    dom.duplicateTicketId = document.querySelector('[data-duplicate-ticket-id]');
    dom.duplicateDetails = document.querySelector('[data-duplicate-details]');
    dom.mergeTicketBtn = document.querySelector('[data-merge-ticket]');
    dom.createSeparateBtn = document.querySelector('[data-create-new]');
  }

  function populateEmployees() {
    if (!dom.ticketAssigneeFilter) return;

    const fragment = document.createDocumentFragment();
    employees.forEach((employee) => {
      const option = document.createElement('option');
      option.value = employee.name;
      option.textContent = employee.name;
      fragment.appendChild(option);
    });
    dom.ticketAssigneeFilter.appendChild(fragment);
    const unassignedOption = document.createElement('option');
    unassignedOption.value = '';
    unassignedOption.textContent = 'Unassigned';
    dom.ticketAssigneeFilter.appendChild(unassignedOption);

    if (dom.triageAssigneeSelect) {
      dom.triageAssigneeSelect.innerHTML = '<option value="">Unassigned</option>';
      employees.forEach((employee) => {
        const option = document.createElement('option');
        option.value = employee.name;
        option.textContent = employee.name;
        dom.triageAssigneeSelect.appendChild(option);
      });
    }
  }

  function bindEvents() {
    dom.publicIntakeForm?.addEventListener('submit', (event) => {
      event.preventDefault();
      handleIntakeSubmit(event.currentTarget, 'Public');
    });

    dom.internalIntakeForm?.addEventListener('submit', (event) => {
      event.preventDefault();
      handleIntakeSubmit(event.currentTarget, 'Internal');
    });

    dom.ticketStatusFilter?.addEventListener('change', renderTicketList);
    dom.ticketAssigneeFilter?.addEventListener('change', renderTicketList);

    dom.triageForm?.addEventListener('submit', (event) => {
      event.preventDefault();
      const ticket = getSelectedTicket();
      if (!ticket) return;
      const formData = new FormData(dom.triageForm);
      const updates = {
        priority: formData.get('priority'),
        status: formData.get('status'),
        category: formData.get('category'),
        assignee: formData.get('assignee'),
        slaDue: formData.get('slaDue')
      };
      if (!updates.slaDue) {
        displayFormAlert(dom.triageForm, 'SLA due date is required for triage.', 'error');
        logError('SLA due date missing during triage update', { ticketId: ticket.id });
        return;
      }
      applyTriage(ticket, updates, 'Triage Desk');
      renderAfterTicketMutation(ticket.id);
      displayFormAlert(dom.triageForm, 'Triage updated successfully.', 'success');
    });

    dom.resolutionForm?.addEventListener('submit', (event) => {
      event.preventDefault();
      const ticket = getSelectedTicket();
      if (!ticket) return;
      handleResolution(ticket, new FormData(dom.resolutionForm));
    });

    dom.noteForm?.addEventListener('submit', (event) => {
      event.preventDefault();
      const ticket = getSelectedTicket();
      if (!ticket) return;
      handleInternalNote(ticket, new FormData(dom.noteForm));
    });

    dom.mergeTicketBtn?.addEventListener('click', () => {
      if (!state.pendingMerge) return;
      mergeIntoExistingTicket();
    });

    dom.createSeparateBtn?.addEventListener('click', () => {
      if (!state.pendingMerge) return;
      finalizeTicketCreation(state.pendingMerge.candidate, state.pendingMerge.source, { skipDuplicateCheck: true });
      hideDuplicatePrompt();
    });

    dom.ticketList?.addEventListener('click', (event) => {
      const row = event.target.closest('tr[data-ticket-id]');
      if (!row) return;
      selectTicket(row.dataset.ticketId);
    });

    dom.taskBoard?.addEventListener('click', (event) => {
      const button = event.target.closest('button[data-action]');
      if (!button) return;
      const ticketId = button.dataset.ticketId;
      const ticket = state.tickets.find((item) => item.id === ticketId);
      if (!ticket) return;

      const action = button.dataset.action;
      if (action === 'add-note') {
        const textarea = dom.taskBoard.querySelector(`textarea[data-task-note="${ticketId}"]`);
        const note = textarea?.value.trim();
        if (!note) {
          logError('Quick note cannot be empty.', { ticketId });
          return;
        }
        addTimelineEntry(ticket, {
          actor: ticket.assignedTo || 'Unassigned',
          message: `Quick note added: ${note}`,
          type: 'note'
        });
        textarea.value = '';
        logActivity('Quick Note', `Inline note recorded on ${ticket.id}.`, { ticketId: ticket.id });
        renderAfterTicketMutation(ticket.id);
      } else if (action === 'start') {
        updateTicketStatus(ticket, 'In Progress', 'Task Board');
      } else if (action === 'wait') {
        updateTicketStatus(ticket, 'Waiting', 'Task Board');
      } else if (action === 'resolve') {
        updateTicketStatus(ticket, 'Resolved', 'Task Board');
      }
    });

    dom.documentUploadForm?.addEventListener('submit', (event) => {
      event.preventDefault();
      handleDocumentUpload(new FormData(dom.documentUploadForm));
    });

    dom.documentFilterForm?.addEventListener('submit', (event) => {
      event.preventDefault();
      applyDocumentFilters(new FormData(dom.documentFilterForm));
    });

    dom.resetDocFilters?.addEventListener('click', (event) => {
      event.preventDefault();
      dom.documentFilterForm?.reset();
      state.docFilters = { type: '', tag: '', linkedEntity: '', dateFrom: '', dateTo: '' };
      renderDocumentList();
    });

    dom.documentList?.addEventListener('click', (event) => {
      const button = event.target.closest('button[data-download]');
      if (!button) return;
      const { docId, version } = button.dataset;
      initiateDocumentDownload(docId, Number(version));
    });

    dom.approvalForm?.addEventListener('submit', (event) => {
      event.preventDefault();
      handleChangeProposal(new FormData(dom.approvalForm));
    });

    dom.approvalsList?.addEventListener('click', (event) => {
      const button = event.target.closest('button[data-approval-action]');
      if (!button) return;
      const { approvalId, approvalAction } = button.dataset;
      processApproval(approvalId, approvalAction);
    });

    dom.anomalyForm?.addEventListener('submit', (event) => {
      event.preventDefault();
      handleAnomalySimulation(new FormData(dom.anomalyForm));
    });
  }

  function seedInitialData() {
    const ticketA = finalizeTicketCreation(
      {
        customerName: 'Kavita Roy',
        email: 'kavita.roy@example.com',
        phone: '9876543210',
        pincode: '834001',
        contactMethod: 'Phone',
        category: 'Subsidy Status',
        description: 'Awaiting DISCOM inspection confirmation.',
        attachments: [],
        consent: 'Yes',
        subsidyStage: 'Inspection',
        requiresSiteVisit: 'Yes'
      },
      'Public',
      { skipDuplicateCheck: true, createdAt: shiftHours(new Date(), -30) }
    );
    applyTriage(ticketA, {
      priority: 'High',
      status: 'In Progress',
      category: 'Subsidy Status',
      assignee: 'Anita Sharma',
      slaDue: toDatetimeLocalString(shiftHours(new Date(), 8))
    }, 'Seeder');
    addTimelineEntry(ticketA, {
      actor: 'Anita Sharma',
      message: 'Called DISCOM coordinator; inspection scheduled for tomorrow.',
      type: 'update',
      timestamp: shiftHours(new Date(), -4)
    });

    const ticketB = finalizeTicketCreation(
      {
        customerName: 'Prakash Singh',
        email: 'prakash.singh@example.com',
        phone: '9123456780',
        pincode: '827012',
        contactMethod: 'Email',
        category: 'System Performance',
        description: 'Inverter showing error code 504 during peak hours.',
        attachments: [],
        consent: 'Yes',
        requiresSiteVisit: 'No'
      },
      'Internal',
      { skipDuplicateCheck: true, createdAt: shiftHours(new Date(), -54) }
    );
    applyTriage(ticketB, {
      priority: 'Critical',
      status: 'Waiting',
      category: 'System Performance',
      assignee: 'Ravi Prasad',
      slaDue: toDatetimeLocalString(shiftHours(new Date(), -2))
    }, 'Seeder');
    addTimelineEntry(ticketB, {
      actor: 'Ravi Prasad',
      message: 'Awaiting inverter vendor callback. Temporary bypass shared with customer.',
      type: 'update',
      timestamp: shiftHours(new Date(), -3)
    });

    const ticketC = finalizeTicketCreation(
      {
        customerName: 'Mansi Verma',
        email: 'mansi.verma@example.com',
        phone: '9012345678',
        pincode: '835222',
        contactMethod: 'WhatsApp',
        category: 'Billing & Payments',
        description: 'Need subsidy disbursement acknowledgement for the bank.',
        attachments: [],
        consent: 'Yes',
        subsidyStage: 'Disbursement',
        requiresSiteVisit: 'No'
      },
      'Public',
      { skipDuplicateCheck: true, createdAt: shiftHours(new Date(), -80) }
    );
    applyTriage(ticketC, {
      priority: 'Medium',
      status: 'Resolved',
      category: 'Billing & Payments',
      assignee: 'Sonia Dutta',
      slaDue: toDatetimeLocalString(shiftHours(new Date(), -12))
    }, 'Seeder');
    ticketC.resolution = {
      notes: 'Shared acknowledgement letter and updated subsidy ledger screenshot.',
      resolvedAt: new Date().toISOString()
    };
    ticketC.satisfaction = {
      followUp: 'Yes',
      rating: '5',
      feedback: 'Extremely fast turnaround with clear instructions.'
    };
    addTimelineEntry(ticketC, {
      actor: 'Sonia Dutta',
      message: 'Customer confirmed receipt of subsidy letter via WhatsApp.',
      type: 'resolution',
      timestamp: shiftHours(new Date(), -10)
    });

    addDocumentVersion({
      name: 'DISCOM Inspection Report',
      type: 'Permit',
      linkedEntity: 'Ticket',
      reference: ticketA.id,
      tags: ['Inspection', 'DISCOM'],
      fileName: 'inspection-report.pdf',
      uploadedBy: 'Anita Sharma',
      size: '256 KB',
      uploadedAt: shiftHours(new Date(), -6)
    });

    addDocumentVersion({
      name: 'Customer KYC Bundle',
      type: 'Agreement',
      linkedEntity: 'Customer',
      reference: ticketC.customer.email,
      tags: ['KYC', 'Subsidy'],
      fileName: 'kyc-documents.zip',
      uploadedBy: 'Sonia Dutta',
      size: '1.4 MB',
      uploadedAt: shiftHours(new Date(), -40)
    });

    state.approvals.push({
      id: generateApprovalId(),
      ticketId: ticketA.id,
      field: 'slaDue',
      previousValue: ticketA.slaDueDate,
      newValue: shiftHours(new Date(ticketA.slaDueDate), 6).toISOString(),
      proposedBy: 'Ravi Prasad',
      status: 'Pending',
      createdAt: new Date().toISOString()
    });

    state.anomalies.push({
      id: generateAnomalyId(),
      type: 'Login Burst',
      detail: '5 login attempts within 60 seconds from installer accounts.',
      timestamp: shiftHours(new Date(), -1),
      severity: 'Medium'
    });

    logActivity('Seed Data', 'Preloaded demo tickets, documents, and governance items for quick exploration.');
  }

  function renderAll() {
    renderTicketList();
    renderTicketDetail();
    renderNotifications();
    renderTaskBoard();
    renderDocumentList();
    renderApprovals();
    renderAnomalies();
    renderActivityLog();
    renderErrorLog();
    updateMetrics();
  }

  function scheduleSLAWatcher() {
    evaluateSLARisks();
    window.setInterval(evaluateSLARisks, 60 * 1000);
  }

  function handleIntakeSubmit(form, source) {
    const formData = new FormData(form);
    const intake = {
      customerName: (formData.get('customerName') || '').trim(),
      email: (formData.get('email') || '').trim().toLowerCase(),
      phone: normalizePhone(formData.get('phone') || ''),
      pincode: (formData.get('pincode') || '').trim(),
      contactMethod: formData.get('contactMethod') || 'Phone',
      category: formData.get('category') || 'General',
      description: (formData.get('description') || '').trim(),
      subsidyStage: formData.get('subsidyStage') || '',
      requiresSiteVisit: normalizeYesNo(formData.get('requiresSiteVisit')),
      consent: normalizeYesNo(formData.get('public-consent') || formData.get('consent'))
    };

    if (!intake.customerName || !intake.email || !intake.phone || !intake.description) {
      displayFormAlert(form, 'Please fill in the required fields.', 'error');
      logError('Mandatory intake fields missing.', { source });
      return;
    }

    if (intake.phone.length < 10) {
      displayFormAlert(form, 'Phone number must contain at least 10 digits.', 'error');
      logError('Invalid phone length provided.', { phone: intake.phone });
      return;
    }

    if (intake.pincode && intake.pincode.length !== 6) {
      displayFormAlert(form, 'Pincode must be exactly 6 characters.', 'error');
      logError('Invalid pincode length provided.', { pincode: intake.pincode });
      return;
    }

    const attachments = extractAttachments(formData.getAll('attachments'));
    intake.attachments = attachments;

    const duplicates = findDuplicateTickets(intake);
    if (duplicates.length) {
      state.pendingMerge = {
        candidate: intake,
        duplicates,
        source
      };
      showDuplicatePrompt(duplicates[0]);
      return;
    }

    finalizeTicketCreation(intake, source, { skipDuplicateCheck: true });
    form.reset();
    displayFormAlert(form, 'Ticket created successfully.', 'success');
  }

  function finalizeTicketCreation(intake, source, options = {}) {
    const createdAt = options.createdAt ? new Date(options.createdAt) : new Date();
    const ticket = {
      id: generateTicketId(),
      createdAt: createdAt.toISOString(),
      source,
      customer: {
        name: intake.customerName,
        email: intake.email,
        phone: intake.phone,
        pincode: intake.pincode
      },
      contactMethod: intake.contactMethod,
      consent: intake.consent || 'Yes',
      description: intake.description,
      category: intake.category,
      subsidyStage: intake.subsidyStage || '',
      requiresSiteVisit: intake.requiresSiteVisit || 'No',
      attachments: intake.attachments || [],
      priority: 'Medium',
      status: 'New',
      assignedTo: '',
      slaDueDate: '',
      resolution: {
        notes: '',
        resolvedAt: null
      },
      satisfaction: {
        followUp: 'No',
        rating: '',
        feedback: ''
      },
      flags: {
        slaWarningSent: false,
        slaBreachedLogged: false
      },
      timeline: []
    };

    state.tickets.push(ticket);
    logActivity('Ticket Created', `Ticket ${ticket.id} logged for ${ticket.customer.name}.`, { ticketId: ticket.id });
    addTimelineEntry(ticket, {
      actor: source,
      message: `Ticket created via ${source.toLowerCase()} intake form.`,
      type: 'create',
      timestamp: createdAt
    });

    hideDuplicatePrompt();
    renderAfterTicketMutation(ticket.id);
    return ticket;
  }

  function findDuplicateTickets(intake) {
    return state.tickets.filter((ticket) => {
      return ticket.customer.phone === intake.phone || ticket.customer.email === intake.email;
    });
  }

  function showDuplicatePrompt(existingTicket) {
    if (!dom.duplicateAlert) return;
    dom.duplicateTicketId.textContent = existingTicket.id;
    dom.duplicateDetails.innerHTML = `
      <p><strong>Existing ticket owner:</strong> ${existingTicket.customer.name}</p>
      <p><strong>Current status:</strong> ${existingTicket.status}</p>
      <p><strong>Assignee:</strong> ${existingTicket.assignedTo || 'Unassigned'}</p>
    `;
    dom.duplicateAlert.hidden = false;
  }

  function hideDuplicatePrompt() {
    if (!dom.duplicateAlert) return;
    dom.duplicateAlert.hidden = true;
    dom.duplicateTicketId.textContent = '';
    dom.duplicateDetails.innerHTML = '';
    state.pendingMerge = null;
  }

  function mergeIntoExistingTicket() {
    const mergeContext = state.pendingMerge;
    if (!mergeContext) return;
    const existingTicket = mergeContext.duplicates[0];
    const intake = mergeContext.candidate;

    if (intake.description) {
      addTimelineEntry(existingTicket, {
        actor: mergeContext.source,
        message: `Duplicate complaint merged: ${intake.description}`,
        type: 'merge'
      });
    }
    if (intake.attachments?.length) {
      existingTicket.attachments.push(...intake.attachments);
    }
    existingTicket.flags.slaWarningSent = false;
    existingTicket.flags.slaBreachedLogged = false;

    logActivity('Ticket Merge', `Merged new intake into existing ticket ${existingTicket.id}.`, {
      ticketId: existingTicket.id
    });

    renderAfterTicketMutation(existingTicket.id);
    hideDuplicatePrompt();
  }

  function extractAttachments(fileInputs) {
    return fileInputs
      .flatMap((item) => (item instanceof File && item.size ? [item] : []))
      .map((file) => ({
        name: file.name,
        size: formatFileSize(file.size),
        type: file.type || 'application/octet-stream'
      }));
  }

  function applyTriage(ticket, updates, actor = 'System') {
    const changes = [];

    if (updates.priority && updates.priority !== ticket.priority) {
      changes.push(`Priority → ${updates.priority}`);
      ticket.priority = updates.priority;
    }
    if (updates.status && updates.status !== ticket.status) {
      updateTicketStatus(ticket, updates.status, actor, {
        suppressNotification: true,
        suppressLog: true,
        skipRender: true
      });
      changes.push(`Status → ${updates.status}`);
    }
    if (updates.category && updates.category !== ticket.category) {
      changes.push(`Category → ${updates.category}`);
      ticket.category = updates.category;
    }
    if (typeof updates.assignee !== 'undefined' && updates.assignee !== ticket.assignedTo) {
      const assigneeName = updates.assignee || '';
      ticket.assignedTo = assigneeName;
      if (assigneeName) {
        addNotification({
          title: 'Ticket Assigned',
          message: `${ticket.id} assigned to ${assigneeName}.`,
          ticketId: ticket.id
        });
      }
      changes.push(`Assignee → ${assigneeName || 'Unassigned'}`);
    }
    if (updates.slaDue) {
      const iso = new Date(updates.slaDue).toISOString();
      ticket.slaDueDate = iso;
      ticket.flags.slaWarningSent = false;
      ticket.flags.slaBreachedLogged = false;
      changes.push(`SLA Due → ${formatDateTime(iso)}`);
    }

    if (changes.length) {
      addTimelineEntry(ticket, {
        actor,
        message: `Triage updated: ${changes.join(', ')}`,
        type: 'triage'
      });
      logActivity('Triage Update', `Ticket ${ticket.id} updated (${changes.length} change${
        changes.length > 1 ? 's' : ''
      }).`, { ticketId: ticket.id });
    }
  }

  function updateTicketStatus(ticket, nextStatus, actor, options = {}) {
    if (ticket.status === nextStatus) return;
    ticket.status = nextStatus;
    if (nextStatus === 'Resolved') {
      ticket.resolution.resolvedAt = new Date().toISOString();
      ticket.flags.slaWarningSent = true;
    }
    if (!options.suppressNotification) {
      addNotification({
        title: `Status: ${nextStatus}`,
        message: `${ticket.id} moved to ${nextStatus} by ${actor}.`,
        ticketId: ticket.id
      });
    }
    addTimelineEntry(ticket, {
      actor,
      message: `Status changed to ${nextStatus}.`,
      type: 'status'
    });
    if (!options.suppressLog) {
      logActivity('Status Update', `Ticket ${ticket.id} marked as ${nextStatus}.`, { ticketId: ticket.id });
    }
    if (!options.skipRender) {
      renderAfterTicketMutation(ticket.id);
    }
  }

  function handleResolution(ticket, formData) {
    const notes = (formData.get('resolutionNotes') || '').trim();
    const followUp = normalizeYesNo(formData.get('followUp'));
    const rating = (formData.get('rating') || '').trim();
    const feedback = (formData.get('feedback') || '').trim();

    ticket.resolution.notes = notes;
    ticket.satisfaction.followUp = followUp;
    ticket.satisfaction.rating = rating;
    ticket.satisfaction.feedback = feedback;

    updateTicketStatus(ticket, 'Resolved', 'Resolution Desk', { suppressNotification: false });

    if (followUp === 'Yes') {
      addTimelineEntry(ticket, {
        actor: 'Automation',
        message: 'Customer satisfaction follow-up triggered via SMS/Email.',
        type: 'follow-up'
      });
      addNotification({
        title: 'Feedback Sent',
        message: `Satisfaction survey sent for ${ticket.id}.`,
        ticketId: ticket.id
      });
    }

    dom.resolutionForm?.reset();
    displayFormAlert(dom.resolutionForm, 'Resolution captured. Ticket marked as resolved.', 'success');
    renderAfterTicketMutation(ticket.id);
  }

  function handleInternalNote(ticket, formData) {
    const note = (formData.get('note') || '').trim();
    const attachment = formData.get('noteAttachment');
    const isCustomerVisible = normalizeYesNo(formData.get('isCustomerVisible'));

    if (!note && !(attachment instanceof File)) {
      displayFormAlert(dom.noteForm, 'Provide a note or attachment to post an update.', 'error');
      return;
    }

    const attachments = attachment instanceof File && attachment.size ? [attachment] : [];
    if (attachments.length) {
      ticket.attachments.push({
        name: attachment.name,
        size: formatFileSize(attachment.size),
        type: attachment.type || 'application/octet-stream'
      });
    }

    addTimelineEntry(ticket, {
      actor: ticket.assignedTo || 'Unassigned',
      message: note || `${attachment.name} uploaded.`,
      type: 'note',
      visibility: isCustomerVisible
    });

    logActivity('Internal Note', `Update posted on ticket ${ticket.id}.`, { ticketId: ticket.id });
    dom.noteForm?.reset();
    renderAfterTicketMutation(ticket.id);
  }

  function renderAfterTicketMutation(ticketId) {
    renderTicketList();
    renderTicketDetail(ticketId || state.selectedTicketId);
    renderTaskBoard();
    updateMetrics();
  }

  function renderTicketList() {
    if (!dom.ticketList) return;
    const filterStatus = dom.ticketStatusFilter?.value || 'all';
    const filterAssignee = dom.ticketAssigneeFilter?.value || 'all';
    dom.ticketList.innerHTML = '';

    if (!state.tickets.length) {
      const row = document.createElement('tr');
      const cell = document.createElement('td');
      cell.colSpan = 6;
      cell.textContent = 'No tickets logged yet.';
      row.appendChild(cell);
      dom.ticketList.appendChild(row);
      return;
    }

    state.tickets
      .filter((ticket) => {
        const statusMatch = filterStatus === 'all' || ticket.status === filterStatus;
        const assigneeMatch =
          filterAssignee === 'all' || (filterAssignee === '' && !ticket.assignedTo) || ticket.assignedTo === filterAssignee;
        return statusMatch && assigneeMatch;
      })
      .sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt))
      .forEach((ticket) => {
        const row = document.createElement('tr');
        row.dataset.ticketId = ticket.id;
        if (ticket.id === state.selectedTicketId) {
          row.classList.add('is-active');
        }

        const idCell = document.createElement('td');
        idCell.textContent = ticket.id;
        row.appendChild(idCell);

        const customerCell = document.createElement('td');
        const customerName = document.createElement('div');
        customerName.textContent = ticket.customer.name;
        const customerPhone = document.createElement('small');
        customerPhone.textContent = ticket.customer.phone;
        customerCell.appendChild(customerName);
        customerCell.appendChild(customerPhone);
        row.appendChild(customerCell);

        const priorityCell = document.createElement('td');
        priorityCell.innerHTML = renderPriorityChip(ticket.priority);
        row.appendChild(priorityCell);

        const statusCell = document.createElement('td');
        statusCell.innerHTML = renderStatusBadge(ticket.status);
        row.appendChild(statusCell);

        const slaCell = document.createElement('td');
        slaCell.innerHTML = renderSlaCell(ticket);
        row.appendChild(slaCell);

        const assigneeCell = document.createElement('td');
        assigneeCell.textContent = ticket.assignedTo || 'Unassigned';
        row.appendChild(assigneeCell);

        dom.ticketList.appendChild(row);
      });
  }

  function renderTicketDetail(ticketId = state.selectedTicketId) {
    if (!dom.ticketDetail) return;
    const ticket = ticketId ? state.tickets.find((item) => item.id === ticketId) : null;
    if (!ticket) {
      dom.ticketDetail.hidden = true;
      state.selectedTicketId = null;
      return;
    }

    state.selectedTicketId = ticket.id;
    dom.ticketDetail.hidden = false;
    dom.ticketDetailTitle.textContent = `${ticket.id} • ${ticket.customer.name}`;
    dom.ticketDetailSub.textContent = `${ticket.category} • ${ticket.contactMethod} • Created ${formatDateTime(
      ticket.createdAt
    )}`;

    renderTicketBadges(ticket);
    renderTicketInfo(ticket);
    renderTimeline(ticket);
    populateTriageForm(ticket);
    populateResolutionForm(ticket);
  }

  function renderTicketBadges(ticket) {
    if (!dom.ticketBadges) return;
    dom.ticketBadges.innerHTML = '';
    const status = document.createElement('span');
    status.className = `status-badge status-${ticket.status.replace(/\s+/g, '-').toLowerCase()}`;
    status.innerHTML = `<i class="fa-solid fa-circle"></i>${ticket.status}`;
    dom.ticketBadges.appendChild(status);

    const priority = document.createElement('span');
    priority.className = `priority-chip priority-${ticket.priority.toLowerCase()}`;
    priority.innerHTML = `<i class="fa-solid fa-flag"></i>${ticket.priority}`;
    dom.ticketBadges.appendChild(priority);

    if (ticket.slaDueDate) {
      const now = new Date();
      const due = new Date(ticket.slaDueDate);
      const isBreached = now > due && ticket.status !== 'Resolved';
      const hoursToDue = (due.getTime() - now.getTime()) / (1000 * 60 * 60);
      const badge = document.createElement('span');
      badge.className = `sla-badge ${isBreached ? 'sla-badge--breach' : hoursToDue <= SLA_WARNING_THRESHOLD_HOURS ? 'sla-badge--warning' : ''}`;
      badge.innerHTML = `<i class="fa-solid fa-clock"></i>${isBreached ? 'SLA Breached' : `SLA ${formatRelativeTime(due)}`}`;
      dom.ticketBadges.appendChild(badge);
    }
  }

  function renderTicketInfo(ticket) {
    if (!dom.ticketInfo) return;
    const fields = [
      ['Customer Email', ticket.customer.email],
      ['Phone', ticket.customer.phone],
      ['Pincode', ticket.customer.pincode || '—'],
      ['Preferred Contact', ticket.contactMethod],
      ['Requires Site Visit', ticket.requiresSiteVisit || 'No'],
      ['Subsidy Stage', ticket.subsidyStage || '—'],
      ['SLA Due', ticket.slaDueDate ? formatDateTime(ticket.slaDueDate) : 'Not set'],
      ['Resolution Notes', ticket.resolution.notes || '—'],
      ['Attachments', ticket.attachments.length ? ticket.attachments.map((item) => item.name).join(', ') : 'None']
    ];
    dom.ticketInfo.innerHTML = fields
      .map((item) => `<dt>${item[0]}</dt><dd>${item[1]}</dd>`)
      .join('');
  }

  function renderTimeline(ticket) {
    if (!dom.ticketTimeline) return;
    if (!ticket.timeline.length) {
      dom.ticketTimeline.innerHTML = '<li><time>Timeline</time>No updates recorded yet.</li>';
      return;
    }
    const entries = ticket.timeline
      .slice()
      .sort((a, b) => new Date(a.timestamp) - new Date(b.timestamp))
      .map((entry) => {
        return `
          <li>
            <time>${formatDateTime(entry.timestamp)}</time>
            <strong>${entry.actor}</strong>
            <span>${entry.message}</span>
          </li>
        `;
      })
      .join('');
    dom.ticketTimeline.innerHTML = entries;
  }

  function populateTriageForm(ticket) {
    if (!dom.triageForm) return;
    dom.triageForm.elements.priority.value = ticket.priority;
    dom.triageForm.elements.status.value = ticket.status;
    dom.triageForm.elements.category.value = ticket.category;
    dom.triageForm.elements.assignee.value = ticket.assignedTo;
    dom.triageForm.elements.slaDue.value = ticket.slaDueDate ? toDatetimeLocalString(new Date(ticket.slaDueDate)) : '';
  }

  function populateResolutionForm(ticket) {
    if (!dom.resolutionForm) return;
    dom.resolutionForm.elements.resolutionNotes.value = ticket.resolution.notes || '';
    dom.resolutionForm.elements.followUp.value = ticket.satisfaction.followUp || 'No';
    dom.resolutionForm.elements.rating.value = ticket.satisfaction.rating || '';
    dom.resolutionForm.elements.feedback.value = ticket.satisfaction.feedback || '';
  }

  function selectTicket(ticketId) {
    renderTicketDetail(ticketId);
    renderTicketList();
  }

  function renderNotifications() {
    if (!dom.notificationFeed) return;
    dom.notificationFeed.innerHTML = '';
    if (!state.notifications.length) {
      dom.notificationFeed.innerHTML = '<p>No notifications yet.</p>';
    } else {
      state.notifications
        .slice(-12)
        .reverse()
        .forEach((notification) => {
          const item = document.createElement('div');
          item.className = 'notification-item';
          item.innerHTML = `
            <strong>${notification.title}</strong>
            <span>${notification.message}</span>
            <small>${formatDateTime(notification.timestamp)}</small>
          `;
          dom.notificationFeed.appendChild(item);
        });
    }
    if (dom.notificationCount) {
      dom.notificationCount.textContent = state.notifications.length.toString();
    }
    renderSlaReminders();
  }

  function renderSlaReminders() {
    if (!dom.slaReminderList) return;
    const reminders = state.tickets
      .filter((ticket) => ticket.slaDueDate && ticket.status !== 'Resolved')
      .map((ticket) => {
        const due = new Date(ticket.slaDueDate);
        const now = new Date();
        const diffHours = (due.getTime() - now.getTime()) / (1000 * 60 * 60);
        return {
          ticket,
          diffHours
        };
      })
      .filter(({ diffHours }) => diffHours <= SLA_WARNING_THRESHOLD_HOURS)
      .sort((a, b) => a.diffHours - b.diffHours);

    if (!reminders.length) {
      dom.slaReminderList.innerHTML = '<li>No SLA reminders pending.</li>';
      return;
    }

    dom.slaReminderList.innerHTML = reminders
      .map(({ ticket, diffHours }) => {
        const label = diffHours <= 0 ? 'Overdue' : `${Math.max(diffHours, 0).toFixed(1)}h left`;
        return `<li class="reminder-item"><strong>${ticket.id}</strong> • ${label}</li>`;
      })
      .join('');
  }

  function renderTaskBoard() {
    if (!dom.taskBoard) return;
    dom.taskBoard.querySelectorAll('.task-column').forEach((column) => {
      const status = column.dataset.taskColumn;
      const wrapper = column.querySelector('.task-cards');
      if (!wrapper) return;
      const ticketsForColumn = state.tickets.filter((ticket) => ticket.status === status);
      if (!ticketsForColumn.length) {
        wrapper.innerHTML = '<p class="text-sm">No tickets here.</p>';
        return;
      }
      wrapper.innerHTML = '';
      ticketsForColumn.forEach((ticket) => {
        const card = document.createElement('article');
        card.className = 'task-card';
        card.dataset.ticketId = ticket.id;
        card.innerHTML = `
          <h4>${ticket.id}</h4>
          <div class="task-meta">
            <span>${ticket.assignedTo || 'Unassigned'}</span>
            <span>${ticket.slaDueDate ? formatRelativeTime(new Date(ticket.slaDueDate)) : 'SLA not set'}</span>
          </div>
          <p>${ticket.customer.name} • ${ticket.category}</p>
          <textarea data-task-note="${ticket.id}" placeholder="Add inline note"></textarea>
          <div class="task-actions">
            <button type="button" class="btn btn-outline btn-sm" data-action="add-note" data-ticket-id="${ticket.id}">
              Add Note
            </button>
            <button type="button" class="btn btn-outline btn-sm" data-action="start" data-ticket-id="${ticket.id}">
              Start
            </button>
            <button type="button" class="btn btn-outline btn-sm" data-action="wait" data-ticket-id="${ticket.id}">
              Wait
            </button>
            <button type="button" class="btn btn-primary btn-sm" data-action="resolve" data-ticket-id="${ticket.id}">
              Resolve
            </button>
          </div>
        `;
        wrapper.appendChild(card);
      });
    });
  }

  function handleDocumentUpload(formData) {
    const name = (formData.get('name') || '').trim();
    const type = formData.get('type');
    const linkedEntity = formData.get('linkedEntity');
    const reference = (formData.get('reference') || '').trim();
    const tags = (formData.get('tags') || '')
      .split(',')
      .map((tag) => tag.trim())
      .filter(Boolean);
    const file = formData.get('file');

    if (!name || !reference || !(file instanceof File)) {
      displayFormAlert(dom.documentUploadForm, 'Please provide document name, reference, and upload a file.', 'error');
      return;
    }

    addDocumentVersion({
      name,
      type,
      linkedEntity,
      reference,
      tags,
      fileName: file.name,
      uploadedBy: 'Service Desk',
      size: formatFileSize(file.size),
      file,
      uploadedAt: new Date()
    });

    dom.documentUploadForm?.reset();
    displayFormAlert(dom.documentUploadForm, 'Document stored with version control.', 'success');
    renderDocumentList();
    updateMetrics();
  }

  function addDocumentVersion({ name, type, linkedEntity, reference, tags = [], fileName, uploadedBy, size, file, uploadedAt }) {
    let documentRecord = state.documents.find(
      (doc) => doc.name === name && doc.linkedEntity === linkedEntity && doc.reference === reference
    );

    const timestamp = uploadedAt ? new Date(uploadedAt) : new Date();
    const versionEntry = {
      version: 1,
      fileName,
      uploadedBy,
      uploadedAt: timestamp.toISOString(),
      size,
      url: file instanceof File ? URL.createObjectURL(file) : createPlaceholderDownload(name)
    };

    if (!documentRecord) {
      documentRecord = {
        id: generateDocumentId(),
        name,
        type,
        linkedEntity,
        reference,
        tags: Array.from(new Set(tags)),
        versions: [versionEntry]
      };
      state.documents.push(documentRecord);
    } else {
      versionEntry.version = documentRecord.versions.length + 1;
      documentRecord.versions.unshift(versionEntry);
      documentRecord.tags = Array.from(new Set([...documentRecord.tags, ...tags]));
      documentRecord.type = type;
    }

    logActivity('Document Upload', `${name} saved (${versionEntry.version} versions).`, { documentId: documentRecord.id });
  }

  function renderDocumentList() {
    if (!dom.documentList) return;
    const filters = state.docFilters;
    const filtered = state.documents.filter((doc) => {
      const typeMatch = !filters.type || doc.type === filters.type;
      const entityMatch = !filters.linkedEntity || doc.linkedEntity === filters.linkedEntity;
      const tagMatch = !filters.tag || doc.tags.some((tag) => tag.toLowerCase().includes(filters.tag.toLowerCase()));
      const dateMatch = evaluateDocumentDateFilter(doc, filters.dateFrom, filters.dateTo);
      return typeMatch && entityMatch && tagMatch && dateMatch;
    });

    if (!filtered.length) {
      dom.documentList.innerHTML = '<p>No documents match the filters.</p>';
      return;
    }

    dom.documentList.innerHTML = filtered
      .map((doc) => {
        const versions = doc.versions
          .map((version) => {
            return `
              <span>
                v${version.version} • ${formatDateTime(version.uploadedAt)}
                <button type="button" class="btn btn-outline btn-sm" data-download data-doc-id="${doc.id}" data-version="${
                  version.version
                }">Download</button>
              </span>
            `;
          })
          .join('');
        return `
          <div class="document-card">
            <header>
              <h4>${doc.name}</h4>
              <div class="document-actions">
                <span class="status-badge status-${doc.type.toLowerCase().replace(/\s+/g, '-')}">${doc.type}</span>
              </div>
            </header>
            <div class="document-meta">
              <span><i class="fa-solid fa-link"></i>${doc.linkedEntity}: ${doc.reference}</span>
              <span><i class="fa-solid fa-layer-group"></i>${doc.versions.length} version(s)</span>
            </div>
            <div class="document-tags">
              ${doc.tags.map((tag) => `<span class="tag-chip">${tag}</span>`).join('')}
            </div>
            <div class="version-history">${versions}</div>
          </div>
        `;
      })
      .join('');
  }

  function applyDocumentFilters(formData) {
    state.docFilters = {
      type: formData.get('type') || '',
      tag: (formData.get('tag') || '').trim(),
      linkedEntity: formData.get('linkedEntity') || '',
      dateFrom: formData.get('dateFrom') || '',
      dateTo: formData.get('dateTo') || ''
    };
    renderDocumentList();
  }

  function handleChangeProposal(formData) {
    const ticketId = (formData.get('ticketId') || '').trim();
    const field = formData.get('field');
    let newValue = (formData.get('newValue') || '').trim();
    const proposedBy = (formData.get('proposedBy') || '').trim();

    const ticket = state.tickets.find((item) => item.id === ticketId);
    if (!ticket) {
      displayFormAlert(dom.approvalForm, `Ticket ${ticketId} was not found.`, 'error');
      logError('Change proposal submitted for unknown ticket.', { ticketId });
      return;
    }

    const previousValue = getTicketFieldValue(ticket, field);
    if (field === 'slaDue') {
      const parsed = new Date(newValue);
      if (Number.isNaN(parsed.getTime())) {
        displayFormAlert(dom.approvalForm, 'Provide a valid date/time for SLA updates.', 'error');
        return;
      }
      newValue = parsed.toISOString();
    }

    if (field === 'requiresSiteVisit') {
      newValue = normalizeYesNo(newValue);
    }

    const approval = {
      id: generateApprovalId(),
      ticketId,
      field,
      previousValue,
      newValue,
      proposedBy,
      status: 'Pending',
      createdAt: new Date().toISOString()
    };
    state.approvals.push(approval);
    logActivity('Change Proposal', `Approval requested for ${ticketId} (${field}).`, { ticketId });
    displayFormAlert(dom.approvalForm, 'Change submitted for approval.', 'success');
    dom.approvalForm?.reset();
    renderApprovals();
    updateMetrics();
  }

  function processApproval(approvalId, action) {
    const approval = state.approvals.find((item) => item.id === approvalId);
    if (!approval || approval.status !== 'Pending') return;
    const ticket = state.tickets.find((item) => item.id === approval.ticketId);
    if (!ticket) return;

    approval.status = action === 'approve' ? 'Approved' : 'Rejected';
    approval.resolvedAt = new Date().toISOString();

    if (action === 'approve') {
      applyApprovedChange(ticket, approval);
      logActivity('Change Approved', `${approval.field} updated for ${ticket.id}.`, { ticketId: ticket.id });
    } else {
      logActivity('Change Rejected', `${approval.field} rejected for ${ticket.id}.`, { ticketId: ticket.id });
    }

    renderApprovals();
    renderAfterTicketMutation(ticket.id);
    updateMetrics();
  }

  function applyApprovedChange(ticket, approval) {
    switch (approval.field) {
      case 'category':
        ticket.category = approval.newValue;
        break;
      case 'priority':
        ticket.priority = approval.newValue;
        break;
      case 'slaDue':
        ticket.slaDueDate = new Date(approval.newValue).toISOString();
        ticket.flags.slaWarningSent = false;
        ticket.flags.slaBreachedLogged = false;
        break;
      case 'contactMethod':
        ticket.contactMethod = approval.newValue;
        break;
      case 'requiresSiteVisit':
        ticket.requiresSiteVisit = normalizeYesNo(approval.newValue);
        break;
      default:
        break;
    }

    addTimelineEntry(ticket, {
      actor: 'Approver',
      message: `${approval.field} updated after approval.`,
      type: 'approval'
    });
  }

  function renderApprovals() {
    if (!dom.approvalsList) return;
    if (!state.approvals.length) {
      dom.approvalsList.innerHTML = '<p>No approvals pending.</p>';
      return;
    }
    dom.approvalsList.innerHTML = state.approvals
      .slice()
      .sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt))
      .map((approval) => {
        return `
          <div class="approval-card">
            <div>
              <strong>${approval.ticketId}</strong>
              <p>${approval.field} change proposed by ${approval.proposedBy}</p>
            </div>
            <div>
              <small>Current:</small>
              <p>${formatApprovalValue(approval.field, approval.previousValue) || '—'}</p>
            </div>
            <div>
              <small>Proposed:</small>
              <p>${formatApprovalValue(approval.field, approval.newValue) || '—'}</p>
            </div>
            <div>
              <small>Status:</small>
              <p>${approval.status}</p>
            </div>
            <footer>
              <button type="button" class="btn btn-primary btn-sm" data-approval-action="approve" data-approval-id="${
                approval.id
              }" ${approval.status !== 'Pending' ? 'disabled' : ''}>Approve</button>
              <button type="button" class="btn btn-outline btn-sm" data-approval-action="reject" data-approval-id="${
                approval.id
              }" ${approval.status !== 'Pending' ? 'disabled' : ''}>Reject</button>
            </footer>
          </div>
        `;
      })
      .join('');
  }

  function handleAnomalySimulation(formData) {
    const eventType = formData.get('eventType');
    const quantity = Number(formData.get('quantity') || 0);
    const detail = createAnomalyDetail(eventType, quantity);
    const anomaly = {
      id: generateAnomalyId(),
      type: eventType,
      detail,
      timestamp: new Date(),
      severity: inferAnomalySeverity(eventType, quantity)
    };
    state.anomalies.unshift(anomaly);
    logActivity('Anomaly Alert', `${eventType} detected.`, { anomalyId: anomaly.id });
    renderAnomalies();
    updateMetrics();
    dom.anomalyForm?.reset();
  }

  function renderAnomalies() {
    if (!dom.anomalyList) return;
    if (!state.anomalies.length) {
      dom.anomalyList.innerHTML = '<li>No anomaly alerts detected.</li>';
      return;
    }
    dom.anomalyList.innerHTML = state.anomalies
      .slice(0, 8)
      .map((alert) => `<li><strong>${alert.type}</strong> • ${alert.detail} <br /><small>${formatDateTime(alert.timestamp)}</small></li>`)
      .join('');
  }

  function renderActivityLog() {
    if (!dom.activityLog) return;
    dom.activityLog.innerHTML = state.activityLog
      .slice(-50)
      .reverse()
      .map((entry) => `<li><time>${formatDateTime(entry.timestamp)}</time><strong>${entry.title}</strong><span>${entry.message}</span></li>`)
      .join('');
  }

  function renderErrorLog() {
    if (!dom.errorLog) return;
    if (!state.errorLog.length) {
      dom.errorLog.innerHTML = '<li><time>—</time>No errors logged.</li>';
      return;
    }
    dom.errorLog.innerHTML = state.errorLog
      .slice(-20)
      .reverse()
      .map((entry) => `<li><time>${formatDateTime(entry.timestamp)}</time><strong>${entry.message}</strong></li>`)
      .join('');
  }

  function renderSlaCell(ticket) {
    if (!ticket.slaDueDate) return 'Not set';
    const due = new Date(ticket.slaDueDate);
    const now = new Date();
    const diff = due.getTime() - now.getTime();
    const hours = diff / (1000 * 60 * 60);
    if (hours < 0 && ticket.status !== 'Resolved') {
      return `<span class="sla-badge sla-badge--breach">${Math.abs(hours).toFixed(1)}h overdue</span>`;
    }
    return `${formatDateTime(ticket.slaDueDate)}`;
  }

  function renderPriorityChip(priority) {
    return `<span class="priority-chip priority-${priority.toLowerCase()}"><i class="fa-solid fa-flag"></i>${priority}</span>`;
  }

  function renderStatusBadge(status) {
    return `<span class="status-badge status-${status.replace(/\s+/g, '-').toLowerCase()}">${status}</span>`;
  }

  function addTimelineEntry(ticket, entry) {
    ticket.timeline.push({
      timestamp: (entry.timestamp ? new Date(entry.timestamp) : new Date()).toISOString(),
      actor: entry.actor || 'System',
      message: entry.message,
      type: entry.type || 'info',
      visibility: entry.visibility || 'Internal'
    });
    ticket.timeline.sort((a, b) => new Date(a.timestamp) - new Date(b.timestamp));
  }

  function addNotification({ title, message, ticketId }) {
    state.notifications.push({
      id: `NTF-${state.notifications.length + 1}`,
      title,
      message,
      ticketId,
      timestamp: new Date().toISOString()
    });
    renderNotifications();
  }

  function logActivity(title, message, metadata = {}) {
    state.activityLog.push({
      title,
      message,
      metadata,
      timestamp: new Date().toISOString()
    });
    renderActivityLog();
  }

  function logError(message, metadata = {}) {
    state.errorLog.push({
      message,
      metadata,
      timestamp: new Date().toISOString()
    });
    renderErrorLog();
  }

  function evaluateSLARisks() {
    state.tickets.forEach((ticket) => {
      if (!ticket.slaDueDate || ticket.status === 'Resolved') return;
      const now = new Date();
      const due = new Date(ticket.slaDueDate);
      const diffHours = (due.getTime() - now.getTime()) / (1000 * 60 * 60);
      if (diffHours <= SLA_WARNING_THRESHOLD_HOURS && diffHours > 0 && !ticket.flags.slaWarningSent) {
        ticket.flags.slaWarningSent = true;
        addNotification({
          title: 'SLA Approaching',
          message: `${ticket.id} due in ${diffHours.toFixed(1)} hours.`,
          ticketId: ticket.id
        });
        logActivity('SLA Reminder', `${ticket.id} nearing SLA breach.`, { ticketId: ticket.id });
      }
      if (diffHours <= 0 && !ticket.flags.slaBreachedLogged) {
        ticket.flags.slaBreachedLogged = true;
        addNotification({
          title: 'SLA Breach',
          message: `${ticket.id} breached its SLA.`,
          ticketId: ticket.id
        });
        logActivity('SLA Breach', `${ticket.id} is overdue.`, { ticketId: ticket.id });
      }
    });
    updateMetrics();
    renderNotifications();
  }

  function updateMetrics() {
    const slaRiskCount = state.tickets.filter((ticket) => {
      if (!ticket.slaDueDate || ticket.status === 'Resolved') return false;
      const due = new Date(ticket.slaDueDate);
      const now = new Date();
      const diff = (due.getTime() - now.getTime()) / (1000 * 60 * 60);
      return diff <= SLA_WARNING_THRESHOLD_HOURS;
    }).length;

    const pendingApprovals = state.approvals.filter((approval) => approval.status === 'Pending').length;
    const documentVersions = state.documents.reduce((sum, doc) => sum + doc.versions.length, 0);
    const anomalies = state.anomalies.length;

    if (dom.slaRiskMetric) dom.slaRiskMetric.textContent = slaRiskCount.toString();
    if (dom.approvalMetric) dom.approvalMetric.textContent = pendingApprovals.toString();
    if (dom.documentVersionMetric) dom.documentVersionMetric.textContent = documentVersions.toString();
    if (dom.anomalyMetric) dom.anomalyMetric.textContent = anomalies.toString();
  }

  function initiateDocumentDownload(docId, versionNumber) {
    const documentRecord = state.documents.find((doc) => doc.id === docId);
    if (!documentRecord) return;
    const version = documentRecord.versions.find((item) => item.version === versionNumber);
    if (!version) return;

    if (version.url) {
      const link = document.createElement('a');
      link.href = version.url;
      link.download = version.fileName;
      link.target = '_blank';
      link.click();
    }

    logActivity('Document Download', `${documentRecord.name} v${version.version} downloaded.`, {
      documentId: documentRecord.id
    });
  }

  function displayFormAlert(form, message, type) {
    const alert = form?.querySelector('.form-alert');
    if (!alert) return;
    alert.textContent = message;
    alert.classList.remove('is-success', 'is-error');
    alert.classList.add(type === 'success' ? 'is-success' : 'is-error');
  }

  function getSelectedTicket() {
    return state.tickets.find((item) => item.id === state.selectedTicketId) || null;
  }

  function getTicketFieldValue(ticket, field) {
    switch (field) {
      case 'category':
        return ticket.category;
      case 'priority':
        return ticket.priority;
      case 'slaDue':
        return ticket.slaDueDate || '';
      case 'contactMethod':
        return ticket.contactMethod;
      case 'requiresSiteVisit':
        return ticket.requiresSiteVisit;
      default:
        return '';
    }
  }

  function evaluateDocumentDateFilter(doc, from, to) {
    if (!from && !to) return true;
    const newestVersion = doc.versions[0];
    const uploaded = new Date(newestVersion.uploadedAt);
    if (from && uploaded < new Date(from)) return false;
    if (to) {
      const toDate = new Date(to);
      toDate.setHours(23, 59, 59, 999);
      if (uploaded > toDate) return false;
    }
    return true;
  }

  function createAnomalyDetail(eventType, quantity) {
    switch (eventType) {
      case 'Bulk Delete':
        return `${quantity} records flagged for deletion.`;
      case 'Large Import':
        return `${quantity} new tickets imported in a single batch.`;
      case 'Login Burst':
        return `${quantity} login attempts within 5 minutes.`;
      case 'Low Disk':
        return `Storage at ${quantity}% capacity.`;
      default:
        return 'Unexpected pattern detected.';
    }
  }

  function inferAnomalySeverity(eventType, quantity) {
    if (eventType === 'Low Disk' && quantity >= 90) return 'High';
    if (eventType === 'Bulk Delete' && quantity > 20) return 'High';
    if (eventType === 'Large Import' && quantity > 50) return 'Medium';
    if (eventType === 'Login Burst' && quantity > 5) return 'Medium';
    return 'Low';
  }

  function formatApprovalValue(field, value) {
    if (!value) return '';
    if (field === 'slaDue') {
      return formatDateTime(value);
    }
    if (field === 'requiresSiteVisit') {
      return normalizeYesNo(value);
    }
    return value;
  }

  function generateTicketId() {
    state.ticketCounter += 1;
    const counter = `${state.ticketCounter}`.padStart(3, '0');
    const datePart = new Date().toISOString().slice(0, 10).replace(/-/g, '');
    return `CMP-${datePart}-${counter}`;
  }

  function generateDocumentId() {
    return `DOC-${state.documents.length + 1}`;
  }

  function generateApprovalId() {
    return `APR-${state.approvals.length + 1}`;
  }

  function generateAnomalyId() {
    return `ANM-${state.anomalies.length + 1}`;
  }

  function createPlaceholderDownload(name) {
    const blob = new Blob([`Placeholder content for ${name}`], { type: 'text/plain' });
    return URL.createObjectURL(blob);
  }

  function formatDateTime(value) {
    if (!value) return '';
    const date = new Date(value);
    return date.toLocaleString('en-IN', {
      dateStyle: 'medium',
      timeStyle: 'short'
    });
  }

  function formatRelativeTime(date) {
    const now = new Date();
    const diffMs = date.getTime() - now.getTime();
    const diffHours = diffMs / (1000 * 60 * 60);
    if (diffHours < -24) {
      return `${Math.ceil(Math.abs(diffHours) / 24)} day(s) overdue`;
    }
    if (diffHours < 0) {
      return `${Math.abs(diffHours).toFixed(1)} hour(s) overdue`;
    }
    if (diffHours < 24) {
      return `${diffHours.toFixed(1)} hour(s) left`;
    }
    return `${Math.round(diffHours / 24)} day(s) left`;
  }

  function formatFileSize(bytes) {
    if (!bytes) return '0 KB';
    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unitIndex = 0;
    while (size >= 1024 && unitIndex < units.length - 1) {
      size /= 1024;
      unitIndex += 1;
    }
    return `${size.toFixed(1)} ${units[unitIndex]}`;
  }

  function normalizePhone(phone) {
    return phone.replace(/[^0-9]/g, '');
  }

  function normalizeYesNo(value) {
    if (!value) return 'No';
    const normalized = value.toString().trim().toLowerCase();
    return normalized === 'yes' || normalized === 'y' ? 'Yes' : 'No';
  }

  function shiftHours(date, hours) {
    const copy = new Date(date);
    copy.setHours(copy.getHours() + hours);
    return copy;
  }

  function toDatetimeLocalString(date) {
    const current = date instanceof Date ? date : new Date(date);
    const offset = current.getTimezoneOffset();
    const adjusted = new Date(current.getTime() - offset * 60 * 1000);
    return adjusted.toISOString().slice(0, 16);
  }
})();
