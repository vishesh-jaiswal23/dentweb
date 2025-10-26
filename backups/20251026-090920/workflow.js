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
    jobCounter: 0,
    checklistCounter: 0,
    commissioningCounter: 0,
    backupCounter: 0,
    alertCounter: 0,
    tickets: [],
    documents: [],
    approvals: [],
    anomalies: [],
    activityLog: [],
    errorLog: [],
    notifications: [],
    selectedTicketId: null,
    pendingMerge: null,
    jobs: [],
    checklists: [],
    commissioning: [],
    amcSchedules: [],
    analyticsRecords: [],
    analyticsView: [],
    analyticsFiltersApplied: false,
    backupHistory: [],
    systemAlerts: [],
    healthSnapshot: {
      lastBackup: '',
      diskFree: '',
      recentErrors: 0,
      pendingAlerts: 0,
      recordCounts: {
        customers: 0,
        tickets: 0,
        documents: 0,
        assets: 0
      }
    },
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
    dom.jobAssignmentForm = document.getElementById('job-assignment-form');
    dom.jobInstallerSelect = document.querySelector('[data-job-installer]');
    dom.jobList = document.querySelector('[data-job-list]');
    dom.checklistForm = document.getElementById('checklist-form');
    dom.checklistJobSelect = document.querySelector('[data-checklist-job]');
    dom.checklistList = document.querySelector('[data-checklist-list]');
    dom.commissioningForm = document.getElementById('commissioning-form');
    dom.commissioningJobSelect = document.querySelector('[data-commissioning-job]');
    dom.commissioningList = document.querySelector('[data-commissioning-list]');
    dom.amcForm = document.getElementById('amc-form');
    dom.amcUpcoming = document.querySelector('[data-amc-upcoming]');
    dom.amcDue = document.querySelector('[data-amc-due]');
    dom.amcOverdue = document.querySelector('[data-amc-overdue]');
    dom.amcSummary = document.querySelector('[data-amc-summary]');
    dom.analyticsForm = document.getElementById('analytics-filter-form');
    dom.analyticsCards = document.querySelector('[data-analytics-cards]');
    dom.analyticsTable = document.querySelector('[data-analytics-table]');
    dom.analyticsExport = document.querySelector('[data-analytics-export]');
    dom.alertPanel = document.querySelector('[data-alert-panel]');
    dom.backupForm = document.getElementById('backup-form');
    dom.restoreForm = document.getElementById('restore-form');
    dom.backupHistory = document.querySelector('[data-backup-history]');
    dom.backupSelect = document.querySelector('[data-backup-select]');
    dom.healthSummary = document.querySelector('[data-health-summary]');
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

    if (dom.jobInstallerSelect) {
      dom.jobInstallerSelect.innerHTML = '<option value="">Select installer</option>';
      employees.forEach((employee) => {
        const option = document.createElement('option');
        option.value = employee.name;
        option.textContent = employee.name;
        dom.jobInstallerSelect.appendChild(option);
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

    dom.jobAssignmentForm?.addEventListener('submit', (event) => {
      event.preventDefault();
      handleJobAssignment(new FormData(dom.jobAssignmentForm));
    });

    dom.checklistForm?.addEventListener('submit', (event) => {
      event.preventDefault();
      handleChecklistSubmit(new FormData(dom.checklistForm));
    });

    dom.commissioningForm?.addEventListener('submit', (event) => {
      event.preventDefault();
      handleCommissioningSubmit(new FormData(dom.commissioningForm));
    });

    dom.amcForm?.addEventListener('submit', (event) => {
      event.preventDefault();
      handleAmcSchedule(new FormData(dom.amcForm));
    });

    dom.analyticsForm?.addEventListener('submit', (event) => {
      event.preventDefault();
      applyAnalyticsFilters(new FormData(dom.analyticsForm));
    });

    dom.analyticsExport?.addEventListener('click', () => exportAnalyticsCsv());

    dom.backupForm?.addEventListener('submit', (event) => {
      event.preventDefault();
      triggerManualBackup();
    });

    dom.restoreForm?.addEventListener('submit', (event) => {
      event.preventDefault();
      handleRestore(new FormData(dom.restoreForm));
    });

    dom.alertPanel?.addEventListener('click', (event) => {
      const button = event.target.closest('[data-alert-action]');
      if (!button) return;
      const alertId = button.dataset.alertId;
      const action = button.dataset.alertAction;
      if (action === 'ack') {
        acknowledgeAlert(alertId);
      } else if (action === 'dismiss') {
        dismissAlert(alertId);
      }
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

    const job1 = {
      id: generateJobId(),
      type: 'Installation',
      installer: 'Anita Sharma',
      customer: 'Kavita Roy',
      location: 'Ranchi',
      notes: '5kW hybrid with subsidy paperwork',
      scheduledAt: shiftHours(new Date(), -3).toISOString(),
      status: 'On-Site',
      createdAt: shiftHours(new Date(), -12).toISOString(),
      checklistId: null,
      commissioned: false,
      completedAt: null
    };

    const job2 = {
      id: generateJobId(),
      type: 'AMC Service',
      installer: 'Ravi Prasad',
      customer: 'Prakash Singh',
      location: 'Bokaro',
      notes: 'Annual preventive maintenance',
      scheduledAt: shiftHours(new Date(), 30).toISOString(),
      status: 'Scheduled',
      createdAt: shiftHours(new Date(), -6).toISOString(),
      checklistId: null,
      commissioned: false,
      completedAt: null
    };

    state.jobs.push(job1, job2);

    const checklist = {
      id: generateChecklistId(),
      jobId: job1.id,
      arrival: shiftHours(new Date(), -2).toISOString(),
      tasks: ['Safety brief', 'Structure verified', 'Photos captured'],
      notes: 'Earthing pit resistance 1.8Ω. Customer requested extra conduit.',
      signoff: {
        name: 'Kavita Roy',
        device: 'Installer App',
        capturedAt: shiftHours(new Date(), -2).toISOString()
      },
      photo: { name: 'array.jpg', size: '1.2 MB', type: 'image/jpeg' }
    };

    state.checklists.push(checklist);
    job1.checklistId = checklist.id;

    const commissioning = {
      id: generateCommissioningId(),
      jobId: job1.id,
      customer: job1.customer,
      assetType: 'Inverter',
      serial: 'SUN-INV-93844',
      warrantyMonths: 60,
      commissionedOn: shiftHours(new Date(), -1).toISOString(),
      warrantyId: 'WR-2024-7782',
      warrantyExpiresAt: shiftHours(new Date(), 60 * 24 * 30).toISOString()
    };

    state.commissioning.push(commissioning);
    job1.commissioned = true;
    job1.status = 'Completed';
    job1.completedAt = commissioning.commissionedOn;

    state.amcSchedules.push(
      {
        id: 'AMC-1',
        customer: 'Mansi Verma',
        frequency: 'Quarterly',
        nextDue: shiftHours(new Date(), 48).toISOString(),
        reminderDays: 7,
        createdAt: shiftHours(new Date(), -10).toISOString(),
        flags: {}
      },
      {
        id: 'AMC-2',
        customer: 'Prakash Singh',
        frequency: 'Yearly',
        nextDue: shiftHours(new Date(), -12).toISOString(),
        reminderDays: 10,
        createdAt: shiftHours(new Date(), -40).toISOString(),
        flags: {}
      }
    );

    state.analyticsRecords = [
      {
        date: '2024-06-15',
        segment: 'residential',
        avgTurnaroundHours: 26,
        medianTurnaroundHours: 22,
        resolvedTickets: 18,
        firstContactResolved: 11,
        installerJobsCompleted: 9,
        installerTeamSize: 4,
        leads: 32,
        conversions: 12,
        pmSuryaFunnel: { enquiry: 28, survey: 22, application: 17, inspection: 12, disbursement: 8 },
        pmSuryaAging: { enquiry: 2.5, survey: 3.5, application: 4.5, inspection: 3.2, disbursement: 2.1 },
        defects: 1,
        returns: 0
      },
      {
        date: '2024-07-01',
        segment: 'commercial',
        avgTurnaroundHours: 34,
        medianTurnaroundHours: 30,
        resolvedTickets: 14,
        firstContactResolved: 7,
        installerJobsCompleted: 6,
        installerTeamSize: 3,
        leads: 18,
        conversions: 5,
        pmSuryaFunnel: { enquiry: 12, survey: 9, application: 7, inspection: 5, disbursement: 3 },
        pmSuryaAging: { enquiry: 3.5, survey: 4.8, application: 6.2, inspection: 4.5, disbursement: 3.1 },
        defects: 2,
        returns: 1
      },
      {
        date: '2024-07-15',
        segment: 'residential',
        avgTurnaroundHours: 21,
        medianTurnaroundHours: 18,
        resolvedTickets: 22,
        firstContactResolved: 15,
        installerJobsCompleted: 11,
        installerTeamSize: 4,
        leads: 36,
        conversions: 14,
        pmSuryaFunnel: { enquiry: 30, survey: 26, application: 19, inspection: 15, disbursement: 11 },
        pmSuryaAging: { enquiry: 2.1, survey: 3.2, application: 4.1, inspection: 3.0, disbursement: 2.0 },
        defects: 1,
        returns: 1
      }
    ];

    state.analyticsView = state.analyticsRecords.slice();

    state.backupHistory = [
      {
        id: generateBackupId(),
        startedAt: shiftHours(new Date(), -30).toISOString(),
        completedAt: shiftHours(new Date(), -30).toISOString(),
        status: 'Completed',
        initiatedBy: 'Scheduler'
      },
      {
        id: generateBackupId(),
        startedAt: shiftHours(new Date(), -6).toISOString(),
        completedAt: shiftHours(new Date(), -6).toISOString(),
        status: 'Completed',
        initiatedBy: 'Scheduler'
      }
    ];

    state.systemAlerts.push({
      id: generateAlertId(),
      severity: 'Medium',
      summary: 'Low disk headroom',
      detail: 'Storage utilisation at 78%. Consider pruning archives.',
      refId: 'disk-warning',
      createdAt: shiftHours(new Date(), -5).toISOString(),
      acknowledged: false
    });

    state.healthSnapshot.lastBackup = state.backupHistory[0]?.completedAt || '';
    state.healthSnapshot.diskFree = '71% free';
    refreshJobSelectors();
    renderJobAssignments();
    renderChecklists();
    renderCommissioning();
    renderAmcBoards();
    updateAmcAlerts();
    renderAnalyticsView();
    renderBackupHistory();
    renderAlertPanel();
    refreshHealthSnapshot();

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
    renderJobAssignments();
    renderChecklists();
    renderCommissioning();
    renderAmcBoards();
    renderAnalyticsView();
    renderBackupHistory();
    renderHealthSummary();
    renderAlertPanel();
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
    refreshHealthSnapshot();
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
        row.innerHTML = `
          <td>${ticket.id}</td>
          <td>
            <div>${ticket.customer.name}</div>
            <small>${ticket.customer.phone}</small>
          </td>
          <td>${renderPriorityChip(ticket.priority)}</td>
          <td>${renderStatusBadge(ticket.status)}</td>
          <td>${renderSlaCell(ticket)}</td>
          <td>${ticket.assignedTo || 'Unassigned'}</td>
        `;
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

  function handleJobAssignment(formData) {
    const jobType = (formData.get('jobType') || '').trim();
    const installer = (formData.get('installer') || '').trim();
    const customer = (formData.get('customer') || '').trim();
    const location = (formData.get('location') || '').trim();
    const notes = (formData.get('notes') || '').trim();
    const scheduledRaw = formData.get('scheduledAt');
    const scheduledAt = scheduledRaw ? new Date(scheduledRaw) : null;

    if (!jobType || !installer || !customer || !location || !scheduledAt || Number.isNaN(scheduledAt.getTime())) {
      displayFormAlert(dom.jobAssignmentForm, 'Provide job type, installer, customer, location, and schedule.', 'error');
      return;
    }

    const job = {
      id: generateJobId(),
      type: jobType,
      installer,
      customer,
      location,
      notes,
      scheduledAt: scheduledAt.toISOString(),
      status: 'Scheduled',
      createdAt: new Date().toISOString(),
      checklistId: null,
      commissioned: false,
      completedAt: null
    };

    state.jobs.push(job);
    logActivity('Job Assigned', `Job ${job.id} scheduled for ${customer}.`, { jobId: job.id });
    addNotification({
      title: 'Job Assigned',
      message: `${job.customer} scheduled with ${job.installer} (${job.type}).`,
      ticketId: job.id
    });
    displayFormAlert(dom.jobAssignmentForm, 'Job assignment saved.', 'success');
    dom.jobAssignmentForm?.reset();
    refreshJobSelectors();
    renderJobAssignments();
    refreshHealthSnapshot();
  }

  function renderJobAssignments() {
    if (!dom.jobList) return;
    if (!state.jobs.length) {
      dom.jobList.innerHTML = '<tr><td colspan="5">No jobs scheduled yet.</td></tr>';
      return;
    }

    const now = new Date();
    const rows = state.jobs
      .slice()
      .sort((a, b) => new Date(a.scheduledAt) - new Date(b.scheduledAt))
      .map((job) => {
        let status = job.status;
        const scheduleDate = new Date(job.scheduledAt);
        if (job.commissioned) {
          status = 'Completed';
        } else if (scheduleDate.getTime() < now.getTime()) {
          status = job.checklistId ? 'On-Site' : 'Overdue';
        } else if (scheduleDate.getTime() - now.getTime() < 6 * 60 * 60 * 1000) {
          status = 'Due Soon';
        } else {
          status = 'Scheduled';
        }
        job.status = status;
        return `
          <tr>
            <td>${job.id}</td>
            <td>${job.customer}</td>
            <td>${job.installer}</td>
            <td>${formatDateTime(job.scheduledAt)}</td>
            <td>${status}</td>
          </tr>
        `;
      })
      .join('');

    dom.jobList.innerHTML = rows;
  }

  function refreshJobSelectors() {
    if (dom.checklistJobSelect) {
      dom.checklistJobSelect.innerHTML = '<option value="">Select job</option>';
      state.jobs
        .filter((job) => !job.commissioned)
        .forEach((job) => {
          const option = document.createElement('option');
          option.value = job.id;
          option.textContent = `${job.id} • ${job.customer}`;
          dom.checklistJobSelect.appendChild(option);
        });
    }

    if (dom.commissioningJobSelect) {
      dom.commissioningJobSelect.innerHTML = '<option value="">Select job</option>';
      state.jobs
        .filter((job) => !job.commissioned)
        .forEach((job) => {
          const option = document.createElement('option');
          option.value = job.id;
          option.textContent = `${job.id} • ${job.customer}`;
          dom.commissioningJobSelect.appendChild(option);
        });
    }
  }

  function handleChecklistSubmit(formData) {
    const jobId = formData.get('jobId');
    const arrivalRaw = formData.get('arrival');
    const arrival = arrivalRaw ? new Date(arrivalRaw) : null;
    const tasks = formData.getAll('tasks').map((task) => task.toString());
    const notes = (formData.get('notes') || '').trim();
    const signoffName = (formData.get('signoffName') || '').trim();
    const signoffDevice = (formData.get('signoffDevice') || 'Installer App').trim();
    const photo = formData.get('photo');

    const job = state.jobs.find((item) => item.id === jobId);
    if (!job) {
      displayFormAlert(dom.checklistForm, 'Select a valid job before submitting the checklist.', 'error');
      return;
    }

    if (!arrival || Number.isNaN(arrival.getTime()) || !signoffName) {
      displayFormAlert(dom.checklistForm, 'Arrival time and customer sign-off are required.', 'error');
      return;
    }

    if (photo instanceof File && photo.size) {
      const maxSize = 5 * 1024 * 1024;
      if (!photo.type.startsWith('image/')) {
        displayFormAlert(dom.checklistForm, 'Photo must be an image file.', 'error');
        logError('Checklist photo rejected (type).', { jobId });
        return;
      }
      if (photo.size > maxSize) {
        displayFormAlert(dom.checklistForm, 'Photo exceeds 5MB limit.', 'error');
        logError('Checklist photo rejected (size).', { jobId });
        return;
      }
    }

    const checklist = {
      id: generateChecklistId(),
      jobId,
      arrival: arrival.toISOString(),
      tasks: tasks.length ? tasks : ['Safety brief'],
      notes,
      signoff: {
        name: signoffName,
        device: signoffDevice,
        capturedAt: new Date().toISOString()
      },
      photo:
        photo instanceof File && photo.size
          ? { name: photo.name, size: formatFileSize(photo.size), type: photo.type }
          : null
    };

    state.checklists.push(checklist);
    job.checklistId = checklist.id;
    job.status = 'On-Site';
    logActivity('Checklist Submitted', `On-site checklist logged for ${job.id}.`, { jobId: job.id });
    addNotification({
      title: 'Checklist Submitted',
      message: `${job.customer} checklist signed by ${signoffName}.`,
      ticketId: job.id
    });
    dom.checklistForm?.reset();
    displayFormAlert(dom.checklistForm, 'Checklist captured with sign-off.', 'success');
    renderChecklists();
    renderJobAssignments();
    refreshHealthSnapshot();
  }

  function renderChecklists() {
    if (!dom.checklistList) return;
    if (!state.checklists.length) {
      dom.checklistList.innerHTML = '<li>No site checklists submitted yet.</li>';
      return;
    }
    dom.checklistList.innerHTML = state.checklists
      .slice(-6)
      .reverse()
      .map((entry) => {
        const job = state.jobs.find((item) => item.id === entry.jobId);
        const customer = job ? job.customer : entry.jobId;
        const photoLabel = entry.photo ? `${entry.photo.name} (${entry.photo.size})` : 'No photo';
        return `
          <li>
            <strong>${customer}</strong>
            <span>${formatDateTime(entry.arrival)} • Tasks: ${entry.tasks.join(', ')}</span>
            <span>Sign-off: ${entry.signoff.name} via ${entry.signoff.device}</span>
            <span>${photoLabel}</span>
          </li>
        `;
      })
      .join('');
  }

  function handleCommissioningSubmit(formData) {
    const jobId = formData.get('jobId');
    const assetType = (formData.get('assetType') || '').trim();
    const serial = (formData.get('serial') || '').trim();
    const warrantyMonths = Number(formData.get('warrantyMonths') || 0);
    const commissionedOnRaw = formData.get('commissionedOn');
    const commissionedOn = commissionedOnRaw ? new Date(commissionedOnRaw) : null;
    const warrantyId = (formData.get('warrantyId') || '').trim();

    const job = state.jobs.find((item) => item.id === jobId);
    if (!job) {
      displayFormAlert(dom.commissioningForm, 'Select a job to mark commissioning.', 'error');
      return;
    }
    if (!assetType || !serial || !commissionedOn || Number.isNaN(commissionedOn.getTime())) {
      displayFormAlert(dom.commissioningForm, 'Asset type, serial, and commissioning date are mandatory.', 'error');
      return;
    }

    const record = {
      id: generateCommissioningId(),
      jobId,
      customer: job.customer,
      assetType,
      serial,
      warrantyMonths: warrantyMonths || 0,
      commissionedOn: commissionedOn.toISOString(),
      warrantyId: warrantyId || 'Pending',
      warrantyExpiresAt: commissionedOn
        ? new Date(commissionedOn.getTime() + (warrantyMonths || 0) * 30 * 24 * 60 * 60 * 1000).toISOString()
        : null
    };

    state.commissioning.push(record);
    job.commissioned = true;
    job.completedAt = record.commissionedOn;
    job.status = 'Completed';
    refreshJobSelectors();
    logActivity('Commissioned', `Job ${job.id} commissioned with asset ${serial}.`, { jobId: job.id });
    addNotification({
      title: 'Commissioned',
      message: `${job.customer} commissioning complete (${assetType}).`,
      ticketId: job.id
    });
    dom.commissioningForm?.reset();
    displayFormAlert(dom.commissioningForm, 'Commissioning recorded and warranty asset registered.', 'success');
    renderCommissioning();
    renderJobAssignments();
    refreshHealthSnapshot();
  }

  function renderCommissioning() {
    if (!dom.commissioningList) return;
    if (!state.commissioning.length) {
      dom.commissioningList.innerHTML = '<tr><td colspan="4">No commissioning events captured yet.</td></tr>';
      return;
    }
    dom.commissioningList.innerHTML = state.commissioning
      .slice()
      .sort((a, b) => new Date(b.commissionedOn) - new Date(a.commissionedOn))
      .map((record) => {
        return `
          <tr>
            <td>${record.jobId} • ${record.customer}</td>
            <td>${record.assetType}</td>
            <td>${record.serial}</td>
            <td>${record.warrantyMonths} mo${record.warrantyId ? ` • ${record.warrantyId}` : ''}</td>
          </tr>
        `;
      })
      .join('');
  }

  function handleAmcSchedule(formData) {
    const customer = (formData.get('customer') || '').trim();
    const frequency = (formData.get('frequency') || '').trim();
    const nextDueRaw = formData.get('nextDue');
    const reminderDays = Number(formData.get('reminderDays') || 7);
    const nextDue = nextDueRaw ? new Date(nextDueRaw) : null;

    if (!customer || !frequency || !nextDue || Number.isNaN(nextDue.getTime())) {
      displayFormAlert(dom.amcForm, 'Customer, frequency, and next due date are required.', 'error');
      return;
    }

    const schedule = {
      id: `AMC-${state.amcSchedules.length + 1}`,
      customer,
      frequency,
      nextDue: nextDue.toISOString(),
      reminderDays,
      createdAt: new Date().toISOString(),
      flags: {}
    };

    state.amcSchedules.push(schedule);
    dom.amcForm?.reset();
    displayFormAlert(dom.amcForm, 'AMC schedule created.', 'success');
    logActivity('AMC Scheduled', `${customer} scheduled (${frequency}).`, { scheduleId: schedule.id });
    renderAmcBoards();
    updateAmcAlerts();
  }

  function renderAmcBoards() {
    if (!dom.amcUpcoming || !dom.amcDue || !dom.amcOverdue) return;
    if (!state.amcSchedules.length) {
      dom.amcUpcoming.innerHTML = '<li>No AMC schedules yet.</li>';
      dom.amcDue.innerHTML = '<li>All caught up.</li>';
      dom.amcOverdue.innerHTML = '<li>None overdue.</li>';
      if (dom.amcSummary) dom.amcSummary.textContent = '';
      return;
    }

    const now = new Date();
    const upcoming = state.amcSchedules.slice().sort((a, b) => new Date(a.nextDue) - new Date(b.nextDue));
    const dueSoon = [];
    const overdue = [];

    const renderList = (items, container, emptyMessage) => {
      if (!items.length) {
        container.innerHTML = `<li>${emptyMessage}</li>`;
        return;
      }
      container.innerHTML = items
        .map((item) => {
          const due = new Date(item.nextDue);
          return `<li><strong>${item.customer}</strong> • ${formatDateTime(item.nextDue)} • ${formatRelativeTime(due)}</li>`;
        })
        .join('');
    };

    upcoming.forEach((item) => {
      const due = new Date(item.nextDue);
      const diffDays = (due.getTime() - now.getTime()) / (1000 * 60 * 60 * 24);
      if (diffDays < 0) {
        overdue.push(item);
      } else if (diffDays <= item.reminderDays) {
        dueSoon.push(item);
      }
    });

    renderList(upcoming, dom.amcUpcoming, 'No upcoming visits.');
    renderList(dueSoon, dom.amcDue, 'No service due soon.');
    renderList(overdue, dom.amcOverdue, 'No overdue visits.');

    if (dom.amcSummary) {
      dom.amcSummary.textContent = `${state.amcSchedules.length} schedules • ${dueSoon.length} due soon • ${overdue.length} overdue`;
    }
  }

  function updateAmcAlerts() {
    const now = new Date();
    state.amcSchedules.forEach((schedule) => {
      const due = new Date(schedule.nextDue);
      const diffDays = (due.getTime() - now.getTime()) / (1000 * 60 * 60 * 24);
      schedule.flags = schedule.flags || {};

      if (diffDays <= schedule.reminderDays && diffDays >= 0 && !schedule.flags.dueSoonAlert) {
        schedule.flags.dueSoonAlert = true;
        addNotification({
          title: 'AMC Due Soon',
          message: `${schedule.customer} due ${due.toLocaleDateString('en-IN')}.`,
          ticketId: schedule.id
        });
        addSystemAlert({
          severity: 'Medium',
          summary: 'AMC visit due soon',
          detail: `${schedule.customer} due ${formatDateTime(schedule.nextDue)}`,
          refId: schedule.id
        });
      }

      if (diffDays < 0 && !schedule.flags.overdueAlert) {
        schedule.flags.overdueAlert = true;
        addNotification({
          title: 'AMC Overdue',
          message: `${schedule.customer} overdue by ${Math.abs(diffDays).toFixed(0)} day(s).`,
          ticketId: schedule.id
        });
        addSystemAlert({
          severity: 'High',
          summary: 'AMC overdue',
          detail: `${schedule.customer} missed visit on ${formatDateTime(schedule.nextDue)}`,
          refId: `${schedule.id}-overdue`
        });
      }
    });
    renderAlertPanel();
  }

  function applyAnalyticsFilters(formData) {
    if (!state.analyticsRecords.length) {
      if (dom.analyticsCards) dom.analyticsCards.innerHTML = '<p>No analytics captured yet.</p>';
      if (dom.analyticsTable) dom.analyticsTable.innerHTML = '';
      return;
    }

    const fromValue = formData.get('from');
    const toValue = formData.get('to');
    const segment = (formData.get('segment') || 'all').toLowerCase();
    const from = fromValue ? new Date(fromValue) : null;
    const to = toValue ? new Date(toValue) : null;

    const filtered = state.analyticsRecords.filter((record) => {
      const recordDate = new Date(record.date);
      if (from && recordDate < from) return false;
      if (to) {
        const endOfDay = new Date(to);
        endOfDay.setHours(23, 59, 59, 999);
        if (recordDate > endOfDay) return false;
      }
      if (segment !== 'all' && record.segment !== segment) return false;
      return true;
    });

    state.analyticsView = filtered;
    state.analyticsFiltersApplied = true;
    if (dom.analyticsForm) {
      const alert = dom.analyticsForm.querySelector('.form-alert');
      if (alert) {
        alert.textContent = filtered.length ? `${filtered.length} record(s) loaded.` : 'No data in the selected range.';
        alert.classList.toggle('is-success', Boolean(filtered.length));
        alert.classList.toggle('is-error', !filtered.length);
      }
    }
    renderAnalyticsView();
  }

  function renderAnalyticsView() {
    if (!dom.analyticsCards || !dom.analyticsTable) return;
    const hasFilters = state.analyticsFiltersApplied;
    const records = hasFilters ? state.analyticsView : state.analyticsRecords;
    if (!records.length) {
      dom.analyticsCards.innerHTML = hasFilters
        ? '<p>No analytics match the selected filters.</p>'
        : '<p>No analytics captured yet.</p>';
      dom.analyticsTable.innerHTML = '';
      return;
    }

    const summary = summariseAnalytics(records);
    renderAnalyticsSummary(summary);
    renderAnalyticsTable(records);
  }

  function summariseAnalytics(records) {
    const totals = {
      resolved: 0,
      fcr: 0,
      avgTurnaroundWeighted: 0,
      medianAccumulator: 0,
      installerJobs: 0,
      installerTeam: 0,
      leads: 0,
      conversions: 0,
      defects: 0,
      returns: 0,
      funnel: { enquiry: 0, survey: 0, application: 0, inspection: 0, disbursement: 0 },
      aging: { enquiry: 0, survey: 0, application: 0, inspection: 0, disbursement: 0 }
    };

    records.forEach((record) => {
      totals.resolved += record.resolvedTickets;
      totals.fcr += record.firstContactResolved;
      totals.avgTurnaroundWeighted += record.avgTurnaroundHours * record.resolvedTickets;
      totals.medianAccumulator += record.medianTurnaroundHours;
      totals.installerJobs += record.installerJobsCompleted;
      totals.installerTeam += record.installerTeamSize;
      totals.leads += record.leads;
      totals.conversions += record.conversions;
      totals.defects += record.defects;
      totals.returns += record.returns;
      Object.keys(totals.funnel).forEach((stage) => {
        totals.funnel[stage] += record.pmSuryaFunnel[stage] || 0;
        totals.aging[stage] += record.pmSuryaAging[stage] || 0;
      });
    });

    const recordCount = records.length || 1;
    const avgTurnaround = totals.resolved
      ? totals.avgTurnaroundWeighted / totals.resolved
      : totals.avgTurnaroundWeighted / recordCount;
    const medianTurnaround = totals.medianAccumulator / recordCount;
    const fcrRate = totals.resolved ? totals.fcr / totals.resolved : 0;
    const productivity = totals.installerTeam
      ? totals.installerJobs / totals.installerTeam
      : totals.installerJobs / Math.max(recordCount, 1);
    const conversionRate = totals.leads ? totals.conversions / totals.leads : 0;
    const defectRate = totals.installerJobs ? (totals.defects + totals.returns) / totals.installerJobs : 0;
    const averageAging = {};
    Object.keys(totals.aging).forEach((stage) => {
      averageAging[stage] = totals.aging[stage] / recordCount;
    });

    return {
      avgTurnaround,
      medianTurnaround,
      fcrRate,
      productivity,
      conversionRate,
      funnel: totals.funnel,
      aging: averageAging,
      defectRate
    };
  }

  function renderAnalyticsSummary(summary) {
    if (!dom.analyticsCards) return;
    const funnel = summary.funnel;
    const aging = summary.aging;
    const agingText = Object.keys(aging)
      .map((stage) => `${stage}: ${aging[stage].toFixed(1)}d`)
      .join(' · ');
    dom.analyticsCards.innerHTML = `
      <article class="metric-card">
        <p class="metric-label">Avg Turnaround</p>
        <p class="metric-value">${summary.avgTurnaround.toFixed(1)}h</p>
        <p class="metric-meta">Median ${summary.medianTurnaround.toFixed(1)}h</p>
      </article>
      <article class="metric-card">
        <p class="metric-label">First-contact Resolution</p>
        <p class="metric-value">${(summary.fcrRate * 100).toFixed(1)}%</p>
        <p class="metric-meta">Target ≥ 60%</p>
      </article>
      <article class="metric-card">
        <p class="metric-label">Installer Productivity</p>
        <p class="metric-value">${summary.productivity.toFixed(1)} jobs/installer</p>
        <p class="metric-meta">Rolling 30-day</p>
      </article>
      <article class="metric-card">
        <p class="metric-label">Lead → Customer</p>
        <p class="metric-value">${(summary.conversionRate * 100).toFixed(1)}%</p>
        <p class="metric-meta">Includes PM Surya Ghar funnel</p>
      </article>
      <article class="metric-card">
        <p class="metric-label">PM Surya Ghar Funnel</p>
        <p class="metric-value">${funnel.enquiry}→${funnel.survey}→${funnel.application}→${funnel.inspection}→${funnel.disbursement}</p>
        <p class="metric-meta">Average aging: ${agingText}</p>
      </article>
      <article class="metric-card">
        <p class="metric-label">Defect / Return Rate</p>
        <p class="metric-value">${(summary.defectRate * 100).toFixed(1)}%</p>
        <p class="metric-meta">Post-install quality incidents</p>
      </article>
    `;
  }

  function renderAnalyticsTable(records) {
    if (!dom.analyticsTable) return;
    dom.analyticsTable.innerHTML = records
      .map((record) => {
        const conversion = record.leads ? ((record.conversions / record.leads) * 100).toFixed(1) : '0.0';
        const fcr = record.resolvedTickets
          ? ((record.firstContactResolved / record.resolvedTickets) * 100).toFixed(1)
          : '0.0';
        const productivity = record.installerTeamSize
          ? (record.installerJobsCompleted / record.installerTeamSize).toFixed(1)
          : record.installerJobsCompleted.toFixed(1);
        const defectRate = record.installerJobsCompleted
          ? (((record.defects + record.returns) / record.installerJobsCompleted) * 100).toFixed(1)
          : '0.0';
        const funnel = record.pmSuryaFunnel;
        return `
          <tr>
            <td>${record.date}</td>
            <td>${record.avgTurnaroundHours.toFixed(1)}</td>
            <td>${record.medianTurnaroundHours.toFixed(1)}</td>
            <td>${fcr}</td>
            <td>${productivity}</td>
            <td>${conversion}</td>
            <td>${funnel.enquiry}→${funnel.survey}→${funnel.application}→${funnel.inspection}→${funnel.disbursement}</td>
            <td>${defectRate}</td>
          </tr>
        `;
      })
      .join('');
  }

  function exportAnalyticsCsv() {
    const records = state.analyticsView.length ? state.analyticsView : state.analyticsRecords;
    if (!records.length) return;
    const header = [
      'Date',
      'Segment',
      'Avg Turnaround (hrs)',
      'Median Turnaround (hrs)',
      'Resolved Tickets',
      'First-contact Resolved',
      'Installer Jobs Completed',
      'Installer Team Size',
      'Leads',
      'Conversions',
      'PM Surya Enquiry',
      'PM Surya Survey',
      'PM Surya Application',
      'PM Surya Inspection',
      'PM Surya Disbursement',
      'Defects',
      'Returns'
    ];
    const rows = records.map((record) => [
      record.date,
      record.segment,
      record.avgTurnaroundHours,
      record.medianTurnaroundHours,
      record.resolvedTickets,
      record.firstContactResolved,
      record.installerJobsCompleted,
      record.installerTeamSize,
      record.leads,
      record.conversions,
      record.pmSuryaFunnel.enquiry,
      record.pmSuryaFunnel.survey,
      record.pmSuryaFunnel.application,
      record.pmSuryaFunnel.inspection,
      record.pmSuryaFunnel.disbursement,
      record.defects,
      record.returns
    ]);

    const csv = [header.join(','), ...rows.map((row) => row.join(','))].join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `service-analytics-${Date.now()}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
    logActivity('Analytics Export', 'Analytics CSV exported.', {});
  }

  function triggerManualBackup() {
    const backup = {
      id: generateBackupId(),
      startedAt: new Date().toISOString(),
      completedAt: new Date().toISOString(),
      status: 'Completed',
      initiatedBy: 'Operations Admin'
    };
    state.backupHistory.unshift(backup);
    if (state.backupHistory.length > 8) {
      state.backupHistory.length = 8;
    }
    const diskFree = Math.max(52, Math.min(85, Math.round(70 + Math.random() * 6 - 3)));
    state.healthSnapshot.diskFree = `${diskFree}% free`;
    displayFormAlert(dom.backupForm, `Backup ${backup.id} started and completed successfully.`, 'success');
    logActivity('Backup', `${backup.id} completed.`, { backupId: backup.id });
    addSystemAlert({
      severity: 'Low',
      summary: 'Backup Completed',
      detail: `${backup.id} finished at ${formatDateTime(backup.completedAt)}`,
      refId: backup.id
    });
    renderBackupHistory();
    refreshHealthSnapshot();
  }

  function handleRestore(formData) {
    const backupId = formData.get('backupId');
    const backup = state.backupHistory.find((item) => item.id === backupId);
    if (!backup) {
      displayFormAlert(dom.restoreForm, 'Select a backup snapshot to restore.', 'error');
      return;
    }
    displayFormAlert(dom.restoreForm, `Restore for ${backup.id} queued. Sandbox verification in progress.`, 'success');
    logActivity('Restore Requested', `${backup.id} restore queued.`, { backupId: backup.id });
    addSystemAlert({
      severity: 'Medium',
      summary: 'Restore Pending',
      detail: `${backup.id} restore awaiting approval`,
      refId: `${backup.id}-restore`
    });
    renderAlertPanel();
  }

  function renderBackupHistory() {
    if (!dom.backupHistory) return;
    if (!state.backupHistory.length) {
      dom.backupHistory.innerHTML = '<li>No backups executed yet.</li>';
    } else {
      dom.backupHistory.innerHTML = state.backupHistory
        .map((backup) => `<li><strong>${backup.id}</strong> • ${backup.status} • ${formatDateTime(backup.completedAt)}</li>`)
        .join('');
    }

    if (dom.backupSelect) {
      dom.backupSelect.innerHTML = '<option value="">Select backup</option>';
      state.backupHistory.forEach((backup) => {
        const option = document.createElement('option');
        option.value = backup.id;
        option.textContent = `${backup.id} • ${formatDateTime(backup.completedAt)}`;
        dom.backupSelect.appendChild(option);
      });
    }
  }

  function refreshHealthSnapshot() {
    const snapshot = state.healthSnapshot;
    const uniqueCustomers = new Set(state.tickets.map((ticket) => ticket.customer.email));
    snapshot.recordCounts.customers = uniqueCustomers.size;
    snapshot.recordCounts.tickets = state.tickets.length;
    snapshot.recordCounts.documents = state.documents.length;
    snapshot.recordCounts.assets = state.commissioning.length;
    snapshot.recentErrors = state.errorLog.slice(-5).length;
    snapshot.pendingAlerts = state.systemAlerts.filter((alert) => !alert.acknowledged).length;
    if (state.backupHistory.length) {
      snapshot.lastBackup = state.backupHistory[0].completedAt;
    }
    if (!snapshot.diskFree) {
      snapshot.diskFree = '68% free';
    }
    renderHealthSummary();
  }

  function renderHealthSummary() {
    if (!dom.healthSummary) return;
    const snapshot = state.healthSnapshot;
    if (!snapshot) {
      dom.healthSummary.innerHTML = '<dt>Health</dt><dd>No data</dd>';
      return;
    }
    dom.healthSummary.innerHTML = `
      <dt>Last backup</dt><dd>${snapshot.lastBackup ? formatDateTime(snapshot.lastBackup) : 'Pending'}</dd>
      <dt>Disk space</dt><dd>${snapshot.diskFree}</dd>
      <dt>Recent errors</dt><dd>${snapshot.recentErrors}</dd>
      <dt>Pending alerts</dt><dd>${snapshot.pendingAlerts}</dd>
      <dt>Records</dt><dd>${snapshot.recordCounts.tickets} tickets · ${snapshot.recordCounts.documents} documents · ${snapshot.recordCounts.assets} assets</dd>
    `;
  }

  function addSystemAlert(alert) {
    if (!alert) return;
    const exists = state.systemAlerts.some((item) => item.refId === alert.refId && item.summary === alert.summary);
    if (exists) return;
    const record = {
      id: generateAlertId(),
      severity: alert.severity || 'Low',
      summary: alert.summary,
      detail: alert.detail,
      refId: alert.refId || '',
      createdAt: new Date().toISOString(),
      acknowledged: false
    };
    state.systemAlerts.unshift(record);
    if (state.systemAlerts.length > 12) {
      state.systemAlerts.length = 12;
    }
    state.healthSnapshot.pendingAlerts = state.systemAlerts.filter((item) => !item.acknowledged).length;
    renderAlertPanel();
  }

  function renderAlertPanel() {
    if (!dom.alertPanel) return;
    if (!state.systemAlerts.length) {
      dom.alertPanel.innerHTML = '<li>No alerts. Systems normal.</li>';
      return;
    }
    dom.alertPanel.innerHTML = state.systemAlerts
      .map((alert) => {
        const severity = alert.severity.toLowerCase();
        const acknowledged = alert.acknowledged ? 'acknowledged' : '';
        return `
          <li class="alert-panel__item alert-${severity} ${acknowledged}">
            <div>
              <strong>${alert.summary}</strong>
              <p>${alert.detail}</p>
              <small>${formatDateTime(alert.createdAt)}</small>
            </div>
            <div class="alert-panel__actions">
              <button type="button" class="btn btn-text" data-alert-action="ack" data-alert-id="${alert.id}" ${
                alert.acknowledged ? 'disabled' : ''
              }>Acknowledge</button>
              <button type="button" class="btn btn-text" data-alert-action="dismiss" data-alert-id="${alert.id}">Dismiss</button>
            </div>
          </li>
        `;
      })
      .join('');
  }

  function acknowledgeAlert(alertId) {
    const alert = state.systemAlerts.find((item) => item.id === alertId);
    if (!alert) return;
    alert.acknowledged = true;
    logActivity('Alert Acknowledged', `${alert.summary}`, { alertId });
    state.healthSnapshot.pendingAlerts = state.systemAlerts.filter((item) => !item.acknowledged).length;
    renderAlertPanel();
    renderHealthSummary();
  }

  function dismissAlert(alertId) {
    const index = state.systemAlerts.findIndex((item) => item.id === alertId);
    if (index === -1) return;
    const [alert] = state.systemAlerts.splice(index, 1);
    logActivity('Alert Dismissed', `${alert.summary}`, { alertId });
    state.healthSnapshot.pendingAlerts = state.systemAlerts.filter((item) => !item.acknowledged).length;
    renderAlertPanel();
    renderHealthSummary();
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

  function generateJobId() {
    state.jobCounter += 1;
    return `JOB-${String(state.jobCounter).padStart(3, '0')}`;
  }

  function generateChecklistId() {
    state.checklistCounter += 1;
    return `CHK-${String(state.checklistCounter).padStart(3, '0')}`;
  }

  function generateCommissioningId() {
    state.commissioningCounter += 1;
    return `CMS-${String(state.commissioningCounter).padStart(3, '0')}`;
  }

  function generateBackupId() {
    state.backupCounter += 1;
    return `BKP-${String(state.backupCounter).padStart(3, '0')}`;
  }

  function generateAlertId() {
    state.alertCounter += 1;
    return `ALT-${String(state.alertCounter).padStart(3, '0')}`;
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
