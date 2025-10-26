(() => {
  const STORAGE_KEY = 'dakshayani-crm-state-v1';
  const STAGES = ['Applied', 'Sanctioned', 'Inspected', 'Redeemed', 'Closed'];
  const PERMISSIONS = {
    admin: ['createLead', 'importLead', 'convertLead', 'updateStage', 'export', 'viewReferrer', 'submitLead'],
    sales: ['createLead', 'importLead', 'convertLead', 'updateStage', 'export'],
    referrer: ['submitLead', 'viewReferrer'],
    viewer: []
  };

  const dom = {};
  let state = createInitialState();
  let role = 'admin';
  let activeLeadId = null;
  let activeCustomerId = null;
  let referrerContext = null;

  document.addEventListener('DOMContentLoaded', () => {
    if (!document.querySelector('.crm-app')) return;

    state = loadState();
    cacheDom();
    bindEvents();
    applyRole(role);
  });

  function cacheDom() {
    dom.roleSelect = document.querySelector('[data-role-select]');
    dom.aiToggle = document.querySelector('[data-ai-enabled]');

    dom.leadForm = document.querySelector('[data-lead-form]');
    dom.leadAlert = document.querySelector('[data-lead-alert]');
    dom.referrerForm = document.querySelector('[data-referrer-form]');
    dom.referrerAlert = document.querySelector('[data-referrer-alert]');
    dom.referrerSummary = document.querySelector('[data-referrer-summary]');
    dom.referrerLeadCount = document.querySelector('[data-referrer-lead-count]');
    dom.referrerConversion = document.querySelector('[data-referrer-conversion]');
    dom.referrerExport = document.querySelector('[data-referrer-export]');
    dom.importResults = document.querySelector('[data-import-results]');
    dom.leadImportInput = document.querySelector('[data-lead-import]');
    dom.leadExportBtn = document.querySelector('[data-download-leads]');

    dom.leadTable = document.querySelector('[data-lead-table] tbody');
    dom.conversionPanel = document.querySelector('[data-conversion-panel]');
    dom.conversionForm = document.querySelector('[data-conversion-form]');
    dom.conversionAlert = document.querySelector('[data-conversion-alert]');
    dom.convertLeadId = document.querySelector('[data-convert-lead-id]');
    dom.closeConversion = document.querySelector('[data-close-conversion]');

    dom.customerTable = document.querySelector('[data-customer-table] tbody');
    dom.customerSearch = document.querySelector('[data-customer-search]');
    dom.filterSubsidy = document.querySelector('[data-filter-subsidy]');
    dom.filterStage = document.querySelector('[data-filter-stage]');
    dom.filterFrom = document.querySelector('[data-filter-from]');
    dom.filterTo = document.querySelector('[data-filter-to]');
    dom.customerExport = document.querySelector('[data-export-customers]');
    dom.customerImport = document.querySelector('[data-import-customers]');
    dom.customerImportAlert = document.querySelector('[data-customer-import-alert]');

    dom.stageForm = document.querySelector('[data-stage-form]');
    dom.stageAlert = document.querySelector('[data-stage-alert]');
    dom.currentStage = document.querySelector('[data-current-stage]');
    dom.currentStageAge = document.querySelector('[data-current-stage-age]');
    dom.exportStageHistory = document.querySelector('[data-export-stage-history]');

    dom.stageCounts = document.querySelector('[data-stage-counts]');
    dom.stageAverages = document.querySelector('[data-stage-averages]');
    dom.stageAging = document.querySelector('[data-stage-aging]');
    dom.anomalyList = document.querySelector('[data-anomalies]');

    dom.activityLog = document.querySelector('[data-activity-log]');
    dom.permissionLog = document.querySelector('[data-permission-log]');

    dom.metricLeadCount = document.querySelector('[data-lead-count]');
    dom.metricCustomerCount = document.querySelector('[data-customer-count]');
    dom.metricStageAge = document.querySelector('[data-stage-age]');
    dom.metricSanctionRate = document.querySelector('[data-sanction-rate]');
  }

  function bindEvents() {
    dom.roleSelect?.addEventListener('change', (event) => {
      applyRole(event.target.value);
      logActivity(`Role switched to ${role}`, { actor: role });
    });

    dom.aiToggle?.addEventListener('change', (event) => {
      state.settings.aiEnabled = event.target.checked;
      saveState();
      logActivity(`AI assistance ${state.settings.aiEnabled ? 'enabled' : 'disabled'}`, { actor: role });
    });

    dom.leadForm?.addEventListener('submit', (event) => {
      event.preventDefault();
      if (!ensurePermission('createLead')) {
        displayAlert(dom.leadAlert, 'You do not have permission to add leads.', true);
        return;
      }
      const formData = new FormData(dom.leadForm);
      const payload = Object.fromEntries(formData.entries());
      const validation = validateLeadPayload(payload);
      if (!validation.valid) {
        displayAlert(dom.leadAlert, validation.message, true);
        return;
      }
      const duplicate = findDuplicateLead(payload.email, payload.phone);
      if (duplicate) {
        payload.duplicateOf = duplicate.id;
      }
      const lead = createLeadFromPayload(payload, 'Manual');
      state.leads.push(lead);
      logActivity('Lead created', { actor: role, leadId: lead.id });
      if (duplicate) {
        logActivity('Duplicate detected during lead entry', { actor: role, leadId: lead.id, duplicate: duplicate.id });
      }
      saveState();
      dom.leadForm.reset();
      displayAlert(dom.leadAlert, duplicate ? `Lead saved. Duplicate detected with ${duplicate.id}.` : 'Lead saved successfully.');
      renderAll();
    });

    dom.referrerForm?.addEventListener('submit', (event) => {
      event.preventDefault();
      if (!ensurePermission('submitLead')) {
        displayAlert(dom.referrerAlert, 'Permission denied for referrer submissions.', true);
        return;
      }
      const formData = new FormData(dom.referrerForm);
      const payload = Object.fromEntries(formData.entries());
      if (!payload.referrerId?.trim()) {
        displayAlert(dom.referrerAlert, 'Referrer ID is required.', true);
        return;
      }
      if (!payload.name?.trim()) {
        displayAlert(dom.referrerAlert, 'Lead name is required.', true);
        return;
      }
      if (!/\d{10}/.test(payload.phone || '')) {
        displayAlert(dom.referrerAlert, 'Phone must contain 10 digits.', true);
        return;
      }
      const duplicate = findDuplicateLead(payload.email, payload.phone);
      const lead = createLeadFromPayload(payload, 'Referrer');
      lead.referrerId = payload.referrerId.trim();
      lead.referrerContact = payload.referrerContact?.trim() || '';
      if (duplicate) {
        lead.duplicateOf = duplicate.id;
      }
      state.leads.push(lead);
      trackReferrer(lead.referrerId, lead.id, false);
      referrerContext = lead.referrerId;
      logActivity('Referrer lead submitted', { actor: role, leadId: lead.id, referrerId: lead.referrerId });
      if (duplicate) {
        logActivity('Duplicate detected on referrer submission', { actor: role, leadId: lead.id, duplicate: duplicate.id });
      }
      saveState();
      dom.referrerForm.reset();
      displayAlert(dom.referrerAlert, duplicate ? `Lead submitted. Duplicate of ${duplicate.id}.` : 'Lead submitted successfully.');
      renderReferrerSummary();
      renderAll();
    });

    dom.referrerExport?.addEventListener('click', () => {
      if (!referrerContext) return;
      const leads = state.leads.filter((lead) => lead.referrerId === referrerContext);
      exportCsv(leads, ['id', 'name', 'phone', 'email', 'status', 'createdAt'], `referrer-${referrerContext}-leads.csv`);
      logActivity('Referrer exported leads', { actor: role, referrerId: referrerContext });
    });

    dom.leadImportInput?.addEventListener('change', (event) => {
      if (!ensurePermission('importLead')) {
        displayAlert(dom.importResults, 'Permission denied for imports.', true);
        event.target.value = '';
        return;
      }
      const file = event.target.files?.[0];
      if (!file) return;
      importCsv(file, (rows) => {
        let imported = 0;
        let duplicates = 0;
        rows.forEach((row) => {
          const [name, email, phone, city, systemSize, source, notes] = row;
          if (!name || !phone) return;
          const payload = { name, email, phone, city, systemSize, source, notes };
          const validation = validateLeadPayload(payload);
          if (!validation.valid) return;
          const duplicate = findDuplicateLead(email, phone);
          if (duplicate) {
            duplicates += 1;
            return;
          }
          const lead = createLeadFromPayload(payload, source || 'Import');
          lead.notes = notes || '';
          state.leads.push(lead);
          imported += 1;
        });
        saveState();
        displayAlert(dom.importResults, `Imported ${imported} leads.${duplicates ? ` ${duplicates} duplicates skipped.` : ''}`);
        logActivity('Lead CSV import complete', { actor: role, imported, duplicates });
        dom.leadImportInput.value = '';
        renderAll();
      }, (error) => {
        displayAlert(dom.importResults, error, true);
        dom.leadImportInput.value = '';
      });
    });

    dom.leadExportBtn?.addEventListener('click', () => {
      if (!ensurePermission('export')) {
        displayAlert(dom.importResults, 'Permission denied for export.', true);
        return;
      }
      exportCsv(state.leads, ['id', 'name', 'email', 'phone', 'city', 'systemSize', 'source', 'status', 'createdAt'], 'leads.csv');
      logActivity('Lead CSV exported', { actor: role });
    });

    dom.leadTable?.addEventListener('click', (event) => {
      const button = event.target.closest('[data-action]');
      if (!button) return;
      const leadId = button.dataset.leadId;
      if (button.dataset.action === 'convert') {
        openConversionPanel(leadId);
      } else if (button.dataset.action === 'note') {
        if (!ensurePermission('createLead')) {
          alert('You do not have permission to add notes.');
          return;
        }
        const note = prompt('Add an internal note for this lead:');
        if (note) {
          addLeadNote(leadId, note.trim());
        }
      }
    });

    dom.closeConversion?.addEventListener('click', () => {
      dom.conversionPanel?.setAttribute('hidden', '');
      activeLeadId = null;
    });

    dom.conversionForm?.addEventListener('submit', (event) => {
      event.preventDefault();
      if (!ensurePermission('convertLead')) {
        displayAlert(dom.conversionAlert, 'You do not have permission to convert leads.', true);
        return;
      }
      const formData = new FormData(dom.conversionForm);
      const payload = Object.fromEntries(formData.entries());
      const lead = state.leads.find((item) => item.id === payload.leadId);
      if (!lead) {
        displayAlert(dom.conversionAlert, 'Lead not found.', true);
        return;
      }
      const customerName = payload.customerName?.trim();
      if (!customerName) {
        displayAlert(dom.conversionAlert, 'Customer name is required.', true);
        return;
      }
      const customerEmail = payload.customerEmail?.trim();
      if (!customerEmail) {
        displayAlert(dom.conversionAlert, 'Customer email is required.', true);
        return;
      }
      const customerPhone = payload.customerPhone?.trim();
      if (!customerPhone) {
        displayAlert(dom.conversionAlert, 'Customer phone is required.', true);
        return;
      }
      const phoneDigits = extractPhoneDigits(customerPhone);
      if (phoneDigits.length < 10) {
        displayAlert(dom.conversionAlert, 'Customer phone must include at least 10 digits.', true);
        return;
      }
      const systemSizeValue = payload.systemSize?.trim();
      if (!systemSizeValue) {
        displayAlert(dom.conversionAlert, 'System size is required.', true);
        return;
      }
      const systemSize = parseFloat(systemSizeValue);
      if (!Number.isFinite(systemSize) || systemSize <= 0) {
        displayAlert(dom.conversionAlert, 'System size must be a positive number.', true);
        return;
      }
      payload.customerName = customerName;
      payload.customerEmail = customerEmail;
      payload.customerPhone = customerPhone;
      payload.systemSize = systemSizeValue;
      if (payload.subsidyStatus !== 'Not Applied' && !payload.applicationNumber?.trim()) {
        displayAlert(dom.conversionAlert, 'Application number is required when subsidy is applied.', true);
        return;
      }
      const customer = convertLeadToCustomer(lead, payload);
      state.customers.push(customer);
      lead.status = 'Converted';
      lead.customerId = customer.id;
      trackReferrerConversion(lead.referrerId);
      logActivity('Lead converted to customer', { actor: role, leadId: lead.id, customerId: customer.id });
      saveState();
      dom.conversionPanel?.setAttribute('hidden', '');
      activeLeadId = null;
      renderAll();
      displayAlert(dom.conversionAlert, 'Customer record created and linked.');
    });

    dom.customerTable?.addEventListener('click', (event) => {
      const button = event.target.closest('[data-action]');
      if (!button) return;
      const customerId = button.dataset.customerId;
      if (button.dataset.action === 'manage-stage') {
        selectCustomer(customerId);
      }
    });

    dom.customerSearch?.addEventListener('input', renderCustomerTable);
    dom.filterSubsidy?.addEventListener('change', renderCustomerTable);
    dom.filterStage?.addEventListener('change', renderCustomerTable);
    dom.filterFrom?.addEventListener('change', renderCustomerTable);
    dom.filterTo?.addEventListener('change', renderCustomerTable);

    dom.customerExport?.addEventListener('click', () => {
      if (!ensurePermission('export')) {
        displayAlert(dom.customerImportAlert, 'Permission denied for export.', true);
        return;
      }
      exportCsv(state.customers, ['id', 'leadId', 'name', 'email', 'phone', 'applicationNumber', 'stage', 'subsidyStatus', 'systemSize', 'billingStart', 'billingEnd'], 'customers.csv');
      logActivity('Customer CSV exported', { actor: role });
    });

    dom.customerImport?.addEventListener('change', (event) => {
      if (!ensurePermission('importLead')) {
        displayAlert(dom.customerImportAlert, 'Permission denied for imports.', true);
        event.target.value = '';
        return;
      }
      const file = event.target.files?.[0];
      if (!file) return;
      importCsv(file, (rows) => {
        let imported = 0;
        rows.forEach((row) => {
          const [id, name, email, phone, applicationNumber, stage, subsidyStatus, systemSize, billingStart, billingEnd] = row;
          if (!name || !stage) return;
          const customer = {
            id: id || generateId('CUST'),
            leadId: null,
            name,
            email,
            phone,
            applicationNumber,
            stage: STAGES.includes(stage) ? stage : 'Applied',
            subsidyStatus: subsidyStatus || stage,
            systemSize: parseFloat(systemSize) || null,
            billingStart: billingStart || '',
            billingEnd: billingEnd || '',
            createdAt: new Date().toISOString(),
            stageHistory: [createStageHistoryEntry(stage, billingStart || new Date().toISOString(), 'Imported record', 'CSV Import')],
            documents: [],
            contacts: [],
            visit: null
          };
          state.customers.push(customer);
          imported += 1;
        });
        saveState();
        displayAlert(dom.customerImportAlert, `Imported ${imported} customers.`);
        dom.customerImport.value = '';
        logActivity('Customer CSV import complete', { actor: role, imported });
        renderAll();
      }, (error) => {
        displayAlert(dom.customerImportAlert, error, true);
        dom.customerImport.value = '';
      });
    });

    dom.stageForm?.addEventListener('submit', (event) => {
      event.preventDefault();
      if (!ensurePermission('updateStage')) {
        displayAlert(dom.stageAlert, 'You do not have permission to update stages.', true);
        return;
      }
      const formData = new FormData(dom.stageForm);
      const payload = Object.fromEntries(formData.entries());
      const customer = state.customers.find((item) => item.id === payload.customerId);
      if (!customer) {
        displayAlert(dom.stageAlert, 'Select a customer from the table first.', true);
        return;
      }
      const nextStage = payload.stage;
      if (!isStageAdvanceValid(customer.stage, nextStage)) {
        displayAlert(dom.stageAlert, `Cannot move from ${customer.stage} to ${nextStage} directly.`, true);
        return;
      }
      if (!payload.stageDate) {
        displayAlert(dom.stageAlert, 'Stage date is required.', true);
        return;
      }
      if (!payload.stageDocuments?.trim()) {
        displayAlert(dom.stageAlert, 'Provide documents before advancing stage.', true);
        return;
      }
      advanceStage(customer, nextStage, payload.stageDate, payload.stageDocuments.trim(), payload.stageOwner?.trim() || '');
      saveState();
      renderAll();
      selectCustomer(customer.id);
      displayAlert(dom.stageAlert, `Customer moved to ${nextStage}.`);
      logActivity('Stage advanced', { actor: role, customerId: customer.id, stage: nextStage });
    });

    dom.exportStageHistory?.addEventListener('click', () => {
      if (!activeCustomerId) return;
      const customer = state.customers.find((item) => item.id === activeCustomerId);
      if (!customer) return;
      const rows = customer.stageHistory.map((entry) => ({
        stage: entry.stage,
        enteredAt: entry.enteredAt,
        completedAt: entry.completedAt || '',
        documents: entry.documents,
        owner: entry.owner
      }));
      exportCsv(rows, ['stage', 'enteredAt', 'completedAt', 'documents', 'owner'], `${customer.id}-stage-history.csv`);
      logActivity('Exported stage history', { actor: role, customerId: customer.id });
    });
  }

  function applyRole(nextRole) {
    role = nextRole;
    if (dom.roleSelect) {
      dom.roleSelect.value = role;
    }
    togglePermissionZones();
    renderAll();
  }

  function togglePermissionZones() {
    document.querySelectorAll('[data-permission]').forEach((element) => {
      const allowed = element.dataset.permission.split(/\s+/);
      if (allowed.includes(role) || role === 'admin') {
        element.removeAttribute('hidden');
      } else {
        element.setAttribute('hidden', '');
      }
    });
  }

  function ensurePermission(action) {
    const allowed = PERMISSIONS[role] || [];
    const permitted = allowed.includes(action) || role === 'admin';
    if (!permitted) {
      logPermission(`Denied: ${action}`, { actor: role });
    }
    return permitted;
  }

  function can(action) {
    const allowed = PERMISSIONS[role] || [];
    return allowed.includes(action) || role === 'admin';
  }

  function createInitialState() {
    return {
      leads: [],
      customers: [],
      referrers: {},
      activity: [],
      permissions: [],
      settings: {
        aiEnabled: false
      }
    };
  }

  function loadState() {
    try {
      const stored = localStorage.getItem(STORAGE_KEY);
      if (!stored) return createInitialState();
      const parsed = JSON.parse(stored);
      return {
        ...createInitialState(),
        ...parsed,
        leads: parsed.leads || [],
        customers: parsed.customers || [],
        referrers: parsed.referrers || {},
        activity: parsed.activity || [],
        permissions: parsed.permissions || [],
        settings: {
          ...createInitialState().settings,
          ...(parsed.settings || {})
        }
      };
    } catch (error) {
      console.error('Failed to load CRM state', error);
      return createInitialState();
    }
  }

  function saveState() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
  }

  function renderAll() {
    renderMetrics();
    renderLeadTable();
    renderCustomerTable();
    renderPipeline();
    renderActivity();
    renderReferrerSummary();
  }

  function renderMetrics() {
    dom.metricLeadCount.textContent = state.leads.filter((lead) => lead.status !== 'Converted').length;
    dom.metricCustomerCount.textContent = state.customers.length;
    dom.metricStageAge.textContent = `${computeAverageStageAge().toFixed(1)} d`;
    dom.metricSanctionRate.textContent = `${computeSanctionRate().toFixed(0)}%`;
    if (dom.aiToggle) {
      dom.aiToggle.checked = state.settings.aiEnabled;
    }
  }

  function renderLeadTable() {
    if (!dom.leadTable) return;
    dom.leadTable.innerHTML = '';
    const fragment = document.createDocumentFragment();
    state.leads.forEach((lead) => {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td>${lead.id}</td>
        <td>${lead.name}</td>
        <td>${lead.source}</td>
        <td>${lead.status}</td>
        <td>${lead.pmInterest || 'Unknown'}</td>
        <td>${lead.internalNotes.length}</td>
        <td>${formatDate(lead.createdAt)}</td>
        <td>
          <div class="table-actions">
            <button type="button" class="btn btn-outline" data-action="note" data-lead-id="${lead.id}">Add note</button>
            ${lead.status !== 'Converted' && can('convertLead') ? `<button type="button" class="btn btn-secondary" data-action="convert" data-lead-id="${lead.id}">Convert</button>` : ''}
          </div>
        </td>
      `;
      fragment.appendChild(row);
    });
    dom.leadTable.appendChild(fragment);
  }

  function renderCustomerTable() {
    if (!dom.customerTable) return;
    dom.customerTable.innerHTML = '';
    const filters = {
      search: dom.customerSearch?.value?.toLowerCase() || '',
      subsidy: dom.filterSubsidy?.value || '',
      stage: dom.filterStage?.value || '',
      from: dom.filterFrom?.value ? new Date(dom.filterFrom.value) : null,
      to: dom.filterTo?.value ? new Date(dom.filterTo.value) : null
    };
    const rows = state.customers.filter((customer) => {
      if (filters.search) {
        const text = [customer.name, customer.phone, customer.applicationNumber, customer.id].join(' ').toLowerCase();
        if (!text.includes(filters.search)) return false;
      }
      if (filters.subsidy && customer.subsidyStatus !== filters.subsidy) return false;
      if (filters.stage && customer.stage !== filters.stage) return false;
      if (filters.from && new Date(customer.createdAt) < filters.from) return false;
      if (filters.to && new Date(customer.createdAt) > filters.to) return false;
      return true;
    });
    const fragment = document.createDocumentFragment();
    rows.forEach((customer) => {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td>${customer.id}</td>
        <td>${customer.name}</td>
        <td>${customer.applicationNumber || '—'}</td>
        <td>${customer.stage}</td>
        <td>${customer.subsidyStatus}</td>
        <td>${customer.systemSize || '—'}</td>
        <td>${customer.billingStart || '—'}</td>
        <td><button type="button" class="btn btn-outline" data-action="manage-stage" data-customer-id="${customer.id}">Manage</button></td>
      `;
      fragment.appendChild(row);
    });
    dom.customerTable.appendChild(fragment);
    if (activeCustomerId) {
      selectCustomer(activeCustomerId);
    }
  }

  function renderPipeline() {
    renderStageList(dom.stageCounts, computeStageCounts(), (stage, value) => `${stage} <strong>${value}</strong>`);
    renderStageList(dom.stageAverages, computeStageAverages(), (stage, value) => `${stage} <strong>${value.toFixed(1)} d</strong>`);
    renderStageList(dom.stageAging, computeStageAging(), (stage, value) => `${stage} <strong>${value}</strong>`);
    renderStageList(dom.anomalyList, detectAnomalies(), (_label, value) => value, true);
  }

  function renderStageList(container, data, template, allowHtml = false) {
    if (!container) return;
    container.innerHTML = '';
    if (!data.length) {
      const empty = document.createElement('li');
      empty.textContent = 'No data yet.';
      container.appendChild(empty);
      return;
    }
    data.forEach(([label, value]) => {
      const item = document.createElement('li');
      if (allowHtml) {
        item.textContent = value;
      } else {
        item.innerHTML = template(label, value);
      }
      container.appendChild(item);
    });
  }

  function renderActivity() {
    if (dom.activityLog) {
      dom.activityLog.innerHTML = state.activity.slice(-20).reverse().map(renderLogItem).join('');
    }
    if (dom.permissionLog) {
      dom.permissionLog.innerHTML = state.permissions.slice(-20).reverse().map(renderLogItem).join('');
    }
  }

  function renderLogItem(entry) {
    return `<li><strong>${formatDateTime(entry.timestamp)}</strong> — ${entry.message}${entry.actor ? ` <em>(${entry.actor})</em>` : ''}</li>`;
  }

  function renderReferrerSummary() {
    if (!dom.referrerSummary) return;
    if (!referrerContext) {
      dom.referrerSummary.setAttribute('hidden', '');
      return;
    }
    const stats = state.referrers[referrerContext];
    if (!stats) {
      dom.referrerSummary.setAttribute('hidden', '');
      return;
    }
    dom.referrerLeadCount.textContent = stats.leads.length;
    dom.referrerConversion.textContent = `${stats.conversions > 0 ? ((stats.conversions / stats.leads.length) * 100).toFixed(0) : 0}%`;
    dom.referrerSummary.removeAttribute('hidden');
  }

  function openConversionPanel(leadId) {
    const lead = state.leads.find((item) => item.id === leadId);
    if (!lead || !dom.conversionPanel) return;
    activeLeadId = leadId;
    dom.conversionPanel.removeAttribute('hidden');
    dom.convertLeadId.textContent = lead.id;
    dom.conversionForm.leadId.value = lead.id;
    dom.conversionForm.customerName.value = lead.name;
    dom.conversionForm.customerEmail.value = lead.email || '';
    dom.conversionForm.customerPhone.value = lead.phone || '';
    dom.conversionForm.systemSize.value = lead.systemSize || '';
    dom.conversionForm.subsidyStatus.value = lead.subsidyStatus || 'Applied';
    dom.conversionForm.applicationNumber.value = lead.applicationNumber || '';
    dom.conversionForm.billingStart.value = '';
    dom.conversionForm.billingEnd.value = '';
    dom.conversionForm.discom.value = lead.discom || '';
    dom.conversionForm.feasibilityDate.value = '';
    dom.conversionForm.documents.value = lead.documents || '';
    dom.conversionAlert.textContent = '';
  }

  function selectCustomer(customerId) {
    const customer = state.customers.find((item) => item.id === customerId);
    if (!customer) return;
    activeCustomerId = customerId;
    dom.stageForm.customerId.value = customer.id;
    const currentIndex = STAGES.indexOf(customer.stage);
    const nextStage = currentIndex >= 0 && currentIndex < STAGES.length - 1 ? STAGES[currentIndex + 1] : customer.stage;
    dom.stageForm.stage.value = nextStage;
    dom.stageForm.stageDate.value = new Date().toISOString().split('T')[0];
    dom.stageForm.stageDocuments.value = '';
    dom.stageForm.stageOwner.value = '';
    dom.currentStage.textContent = customer.stage;
    dom.currentStageAge.textContent = computeDaysInStage(customer);
    dom.stageAlert.textContent = '';
  }

  function extractPhoneDigits(value) {
    return (value || '').replace(/\D/g, '');
  }

  function hasRequiredPhoneDigits(value, requiredDigits = 10) {
    return extractPhoneDigits(value).length >= requiredDigits;
  }

  function validateLeadPayload(payload) {
    if (!payload.name?.trim()) return { valid: false, message: 'Name is required.' };
    if (!payload.email?.trim()) return { valid: false, message: 'Email is required.' };
    if (!hasRequiredPhoneDigits(payload.phone)) return { valid: false, message: 'Phone must contain at least 10 digits.' };
    const systemSize = payload.systemSize ? parseFloat(payload.systemSize) : null;
    if (systemSize !== null && systemSize <= 0) return { valid: false, message: 'System size must be greater than zero.' };
    return { valid: true };
  }

  function findDuplicateLead(email, phone) {
    if (!email && !phone) return null;
    return state.leads.find((lead) => lead.email === email || lead.phone === phone) || state.customers.find((customer) => customer.email === email || customer.phone === phone);
  }

  function createLeadFromPayload(payload, source) {
    return {
      id: generateId('LEAD'),
      name: payload.name?.trim(),
      email: payload.email?.trim() || '',
      phone: payload.phone?.trim() || '',
      city: payload.city?.trim() || '',
      systemSize: payload.systemSize ? parseFloat(payload.systemSize) : null,
      source: source || payload.source || 'Manual',
      notes: payload.notes?.trim() || '',
      pmInterest: payload.systemSize ? 'Yes' : 'Unknown',
      status: 'New',
      createdAt: new Date().toISOString(),
      internalNotes: [],
      duplicateOf: payload.duplicateOf || null,
      referrerId: payload.referrerId || null
    };
  }

  function addLeadNote(leadId, note) {
    const lead = state.leads.find((item) => item.id === leadId);
    if (!lead) return;
    lead.internalNotes.push({ note, author: role, createdAt: new Date().toISOString() });
    logActivity('Lead note added', { actor: role, leadId });
    saveState();
    renderAll();
  }

  function convertLeadToCustomer(lead, payload) {
    const now = new Date().toISOString();
    const stage = payload.subsidyStatus === 'Not Applied' ? 'Applied' : (STAGES.includes(payload.subsidyStatus) ? payload.subsidyStatus : 'Applied');
    const customer = {
      id: generateId('CUST'),
      leadId: lead.id,
      name: payload.customerName?.trim() || lead.name,
      email: payload.customerEmail?.trim() || lead.email,
      phone: payload.customerPhone?.trim() || lead.phone,
      applicationNumber: payload.applicationNumber?.trim() || '',
      stage,
      subsidyStatus: payload.subsidyStatus,
      systemSize: payload.systemSize ? parseFloat(payload.systemSize) : null,
      billingStart: payload.billingStart || '',
      billingEnd: payload.billingEnd || '',
      createdAt: now,
      documents: payload.documents?.trim() ? payload.documents.trim().split(',').map((item) => item.trim()) : [],
      pmSuryaGhar: {
        discom: payload.discom?.trim() || '',
        feasibilityDate: payload.feasibilityDate || '',
        stageDocuments: payload.documents?.trim() || ''
      },
      contacts: compactContacts(payload),
      visit: payload.visitDate ? { when: payload.visitDate, notes: payload.visitNotes?.trim() || '' } : null,
      stageHistory: [createStageHistoryEntry(stage, payload.feasibilityDate || now, payload.documents?.trim() || lead.notes || 'Conversion documents', role)]
    };
    return customer;
  }

  function compactContacts(payload) {
    const contacts = [];
    if (payload.accountsContact?.trim()) {
      contacts.push({ type: 'Accounts', name: payload.accountsContact.trim() });
    }
    if (payload.technicalContact?.trim()) {
      contacts.push({ type: 'Technical', name: payload.technicalContact.trim() });
    }
    return contacts;
  }

  function createStageHistoryEntry(stage, date, documents, owner) {
    return {
      stage,
      enteredAt: date || new Date().toISOString(),
      completedAt: null,
      documents,
      owner
    };
  }

  function advanceStage(customer, nextStage, date, documents, owner) {
    const current = customer.stageHistory[customer.stageHistory.length - 1];
    if (current && !current.completedAt) {
      current.completedAt = date;
    }
    customer.stageHistory.push({
      stage: nextStage,
      enteredAt: date,
      completedAt: null,
      documents,
      owner
    });
    customer.stage = nextStage;
    customer.subsidyStatus = nextStage;
  }

  function isStageAdvanceValid(currentStage, nextStage) {
    const currentIndex = STAGES.indexOf(currentStage);
    const nextIndex = STAGES.indexOf(nextStage);
    if (nextIndex === -1) return false;
    if (currentIndex === -1) return nextIndex === 0;
    if (nextIndex === currentIndex) return true;
    return nextIndex === currentIndex + 1;
  }

  function computeStageCounts() {
    const counts = new Map();
    STAGES.forEach((stage) => counts.set(stage, 0));
    state.customers.forEach((customer) => {
      counts.set(customer.stage, (counts.get(customer.stage) || 0) + 1);
    });
    return Array.from(counts.entries());
  }

  function computeStageAverages() {
    const totals = new Map();
    const counts = new Map();
    state.customers.forEach((customer) => {
      customer.stageHistory.forEach((entry) => {
        const stage = entry.stage;
        const duration = computeDuration(entry.enteredAt, entry.completedAt || new Date().toISOString());
        totals.set(stage, (totals.get(stage) || 0) + duration);
        counts.set(stage, (counts.get(stage) || 0) + 1);
      });
    });
    return STAGES.map((stage) => {
      const total = totals.get(stage) || 0;
      const count = counts.get(stage) || 1;
      return [stage, total / count];
    });
  }

  function computeStageAging() {
    const aging = new Map();
    state.customers.forEach((customer) => {
      aging.set(customer.stage, Math.max(aging.get(customer.stage) || 0, computeDaysInStage(customer)));
    });
    return Array.from(aging.entries());
  }

  function detectAnomalies() {
    const anomalies = [];
    state.leads.forEach((lead) => {
      if (lead.systemSize && lead.systemSize > 25) {
        anomalies.push([lead.id, `${lead.name}: unusually high system size (${lead.systemSize} kW)`]);
      }
      if (lead.duplicateOf) {
        anomalies.push([lead.id, `${lead.name}: duplicate of ${lead.duplicateOf}`]);
      }
    });
    state.customers.forEach((customer) => {
      const days = computeDaysInStage(customer);
      if (days > 30 && customer.stage !== 'Redeemed' && customer.stage !== 'Closed') {
        anomalies.push([customer.id, `${customer.name} stuck in ${customer.stage} for ${days} days`]);
      }
    });
    return anomalies.length ? anomalies : [['', 'No anomalies detected.']];
  }

  function computeDaysInStage(customer) {
    const current = customer.stageHistory[customer.stageHistory.length - 1];
    if (!current) return 0;
    return Math.round(computeDuration(current.enteredAt, new Date().toISOString()));
  }

  function computeDuration(from, to) {
    const start = new Date(from);
    const end = new Date(to);
    return (end - start) / (1000 * 60 * 60 * 24);
  }

  function computeAverageStageAge() {
    if (!state.customers.length) return 0;
    const total = state.customers.reduce((sum, customer) => sum + computeDaysInStage(customer), 0);
    return total / state.customers.length;
  }

  function computeSanctionRate() {
    if (!state.customers.length) return 0;
    const sanctioned = state.customers.filter((customer) => customer.stage === 'Sanctioned' || customer.stage === 'Redeemed' || customer.stage === 'Closed').length;
    return (sanctioned / state.customers.length) * 100;
  }

  function formatDate(isoString) {
    if (!isoString) return '—';
    return new Date(isoString).toLocaleDateString();
  }

  function formatDateTime(isoString) {
    return new Date(isoString).toLocaleString();
  }

  function generateId(prefix) {
    const random = Math.random().toString(16).slice(2, 8).toUpperCase();
    return `${prefix}-${random}`;
  }

  function displayAlert(container, message, isError = false) {
    if (!container) return;
    container.textContent = message;
    if (isError) {
      container.dataset.state = 'error';
    } else {
      container.dataset.state = 'success';
    }
  }

  function logActivity(message, meta = {}) {
    state.activity.push({ message, ...meta, timestamp: new Date().toISOString() });
    if (state.activity.length > 200) {
      state.activity = state.activity.slice(-200);
    }
    saveState();
    renderActivity();
  }

  function logPermission(message, meta = {}) {
    state.permissions.push({ message, ...meta, timestamp: new Date().toISOString() });
    if (state.permissions.length > 200) {
      state.permissions = state.permissions.slice(-200);
    }
    saveState();
    renderActivity();
  }

  function trackReferrer(referrerId, leadId, converted) {
    if (!referrerId) return;
    if (!state.referrers[referrerId]) {
      state.referrers[referrerId] = { leads: [], conversions: 0 };
    }
    state.referrers[referrerId].leads.push(leadId);
    if (converted) {
      state.referrers[referrerId].conversions += 1;
    }
    saveState();
  }

  function trackReferrerConversion(referrerId) {
    if (!referrerId) return;
    if (!state.referrers[referrerId]) {
      state.referrers[referrerId] = { leads: [], conversions: 0 };
    }
    state.referrers[referrerId].conversions += 1;
    saveState();
  }

  function exportCsv(items, headers, filename) {
    const rows = [headers.join(',')];
    items.forEach((item) => {
      const row = headers.map((header) => {
        const value = item[header] ?? '';
        return `"${String(value).replace(/"/g, '""')}"`;
      });
      rows.push(row.join(','));
    });
    const blob = new Blob([rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.click();
    URL.revokeObjectURL(url);
  }

  function importCsv(file, onSuccess, onError) {
    const reader = new FileReader();
    reader.onload = () => {
      try {
        const text = reader.result;
        const lines = text.split(/\r?\n/).filter(Boolean);
        const rows = lines.map((line) => line.split(',').map((cell) => cell.replace(/^"|"$/g, '').trim()));
        onSuccess(rows);
      } catch (error) {
        onError?.('Failed to parse CSV file.');
      }
    };
    reader.onerror = () => onError?.('Unable to read CSV file.');
    reader.readAsText(file);
  }

  function trackReferrerSummaryContext(id) {
    referrerContext = id;
    renderReferrerSummary();
  }

  window.dakshayaniCRM = {
    reset() {
      state = createInitialState();
      saveState();
      renderAll();
    },
    setReferrer(id) {
      trackReferrerSummaryContext(id);
    }
  };
})();
