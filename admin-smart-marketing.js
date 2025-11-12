(function () {
  'use strict';

  const state = window.SmartMarketingState || {};
  state.settingsSections = state.settingsSections || {};
  state.settingsAudit = state.settingsAudit || [];
  state.integrationsAudit = state.integrationsAudit || [];
  const csrfToken = state.csrfToken || document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  const SECRET_SENTINEL = '__SECRET_PRESENT__';
  const SECRET_PLACEHOLDER = '••••••••';

  const elements = {
    aiHealth: document.querySelector('[data-ai-health]'),
    aiModels: document.querySelector('[data-ai-models]'),
    integrations: document.querySelector('[data-integrations-list]'),
    integrationBanner: document.querySelector('[data-integrations-banner]'),
    integrationBannerMessage: document.querySelector('[data-integrations-banner-message]'),
    integrationBannerAction: document.querySelector('[data-integrations-banner-action]'),
    audit: document.querySelector('[data-audit-log]'),
    brainForm: document.querySelector('[data-brain-form]'),
    goalsGroup: document.querySelector('[data-checkbox-group="goals"]'),
    productsGroup: document.querySelector('[data-checkbox-group="products"]'),
    languagesGroup: document.querySelector('[data-checkbox-group="languages"]'),
    regionsInput: document.querySelector('[data-regions-input]'),
    dailyBudget: document.querySelector('[data-daily-budget]'),
    monthlyBudget: document.querySelector('[data-monthly-budget]'),
    minBid: document.querySelector('[data-min-bid]'),
    cpaGuardrail: document.querySelector('[data-cpa-guardrail]'),
    autonomySelect: document.querySelector('[data-autonomy-select]'),
    autonomyModeLabel: document.querySelector('[data-autonomy-mode]'),
    complianceChecks: document.querySelectorAll('[data-compliance]'),
    notesInput: document.querySelector('[data-notes]'),
    brainError: document.querySelector('[data-brain-error]'),
    brainRuns: document.querySelector('[data-brain-runs]'),
    tabs: document.querySelector('[data-settings-tabs]'),
    panels: document.querySelector('[data-settings-panels]'),
    settingsStatus: document.querySelector('[data-settings-status]'),
    settingsRoot: document.querySelector('[data-settings-root]'),
    killSwitchButton: document.querySelector('[data-kill-switch]'),
    creativeTextCategory: document.querySelector('[data-creative-text-category]'),
    creativeTextBrief: document.querySelector('[data-creative-text-brief]'),
    creativeTextStream: document.querySelector('[data-text-stream]'),
    creativeImagePrompt: document.querySelector('[data-creative-image-prompt]'),
    creativeImagePreset: document.querySelector('[data-creative-image-preset]'),
    creativeImageStream: document.querySelector('[data-image-stream]'),
    creativeTtsScript: document.querySelector('[data-creative-tts-script]'),
    creativeTtsStream: document.querySelector('[data-tts-stream]'),
    assetsList: document.querySelector('[data-assets-list]'),
    connectorList: document.querySelector('[data-connector-list]'),
    campaignForm: document.querySelector('[data-campaign-form]'),
    campaignRunSelect: document.querySelector('[data-campaign-run]'),
    campaignTypes: document.querySelector('[data-campaign-types]'),
    landingModeRadios: document.querySelectorAll('[data-landing-mode]'),
    landingExisting: document.querySelector('[data-landing-existing]'),
    landingAuto: document.querySelector('[data-landing-auto]'),
    landingHeadline: document.querySelector('[data-landing-headline]'),
    landingOffer: document.querySelector('[data-landing-offer]'),
    landingCta: document.querySelector('[data-landing-cta]'),
    landingWhatsapp: document.querySelector('[data-landing-whatsapp]'),
    landingCall: document.querySelector('[data-landing-call]'),
    campaignOutput: document.querySelector('[data-campaign-output]'),
    automationLog: document.querySelector('[data-automation-log]'),
    runAutomationsButton: document.querySelector('[data-run-automations]'),
    analyticsUpdated: document.querySelector('[data-analytics-updated]'),
    analyticsKpis: document.querySelector('[data-analytics-kpis]'),
    analyticsCohorts: document.querySelector('[data-analytics-cohorts]'),
    analyticsFunnel: document.querySelector('[data-analytics-funnel]'),
    analyticsCreatives: document.querySelector('[data-analytics-creatives]'),
    analyticsBudget: document.querySelector('[data-analytics-budget]'),
    analyticsAlerts: document.querySelector('[data-analytics-alerts]'),
    analyticsRefresh: document.querySelector('[data-analytics-refresh]'),
    optimizationAuto: document.querySelector('[data-optimization-auto]'),
    optimizationSave: document.querySelector('[data-optimization-save]'),
    optimizationManualForm: document.querySelector('[data-optimization-manual]'),
    optimizationManualDetails: document.querySelector('[data-optimization-manual-details]'),
    optimizationHistory: document.querySelector('[data-optimization-history]'),
    optimizationLearning: document.querySelector('[data-optimization-learning]'),
    governanceBudgetToggle: document.querySelector('[data-governance-budget-lock]'),
    governanceBudgetCap: document.querySelector('[data-governance-budget-cap]'),
    governanceSaveBudget: document.querySelector('[data-governance-save-budget]'),
    governancePolicyList: document.querySelector('[data-governance-policy]'),
    governanceSavePolicy: document.querySelector('[data-governance-save-policy]'),
    governanceEmergency: document.querySelector('[data-governance-emergency]'),
    governanceEmergencyStatus: document.querySelector('[data-governance-emergency-status]'),
    governanceExport: document.querySelector('[data-governance-export]'),
    governanceErase: document.querySelector('[data-governance-erase]'),
    governanceLog: document.querySelector('[data-governance-log]'),
    notificationsForm: document.querySelector('[data-notifications-form]'),
    notificationsDigestEnabled: document.querySelector('[data-notifications-digest-enabled]'),
    notificationsDigestTime: document.querySelector('[data-notifications-digest-time]'),
    notificationsDigestEmail: document.querySelector('[data-notifications-digest-email]'),
    notificationsDigestWhatsapp: document.querySelector('[data-notifications-digest-whatsapp]'),
    notificationsInstantEmail: document.querySelector('[data-notifications-instant-email]'),
    notificationsInstantWhatsapp: document.querySelector('[data-notifications-instant-whatsapp]'),
    notificationsLog: document.querySelector('[data-notifications-log]'),
    notificationsTest: document.querySelector('[data-notifications-test]'),
  };

  const analyticsMetricDefinitions = [
    { key: 'impressions', label: 'Impr', format: formatNumber },
    { key: 'clicks', label: 'Clicks', format: formatNumber },
    { key: 'ctr', label: 'CTR', format: formatPercent },
    { key: 'cpc', label: 'CPC', format: formatCurrency },
    { key: 'spend', label: 'Spend', format: formatCurrency },
    { key: 'leads', label: 'Leads', format: formatNumber },
    { key: 'cpl', label: 'CPL', format: formatCurrency },
    { key: 'convRate', label: 'Conv-rate', format: formatPercent },
    { key: 'callConnects', label: 'Call connects', format: formatNumber },
    { key: 'meetings', label: 'Meetings', format: formatNumber },
    { key: 'sales', label: 'Sales', format: formatNumber },
  ];

  const settingsViews = {};

  const optimizationRuleConfig = {
    pauseUnderperforming: {
      label: 'Pause underperforming ads',
      description: 'Automatically pauses ads below CTR/CVR guardrails.',
      fields: [
        { key: 'ctrThreshold', label: 'CTR threshold', type: 'percent' },
        { key: 'cvrThreshold', label: 'Conversion threshold', type: 'percent' },
      ],
    },
    bidGuardrails: {
      label: 'Bid guardrails',
      description: 'Raises or lowers bids within configured guardrails.',
      fields: [
        { key: 'minBid', label: 'Min bid', type: 'currency' },
        { key: 'maxBid', label: 'Max bid', type: 'currency' },
        { key: 'step', label: 'Adjustment %', type: 'percent' },
      ],
    },
    budgetShift: {
      label: 'Budget shift to top performers',
      description: 'Moves budget toward top quartile CPL performers.',
      fields: [
        { key: 'shiftPercent', label: 'Shift %', type: 'percent' },
        { key: 'targetCpl', label: 'Target CPL', type: 'currency' },
      ],
    },
    creativeRefresh: {
      label: 'Creative refresh cadence',
      description: 'Refreshes creatives that have decayed beyond schedule.',
      fields: [
        { key: 'decayDays', label: 'Refresh every (days)', type: 'number' },
      ],
    },
  };

  function formatDate(value) {
    if (!value) return '';
    try {
      return new Intl.DateTimeFormat('en-IN', {
        dateStyle: 'medium',
        timeStyle: 'short'
      }).format(new Date(value));
    } catch (error) {
      return value;
    }
  }

  function formatNumber(value) {
    const number = Number(value || 0);
    return Number.isFinite(number) ? number.toLocaleString('en-IN') : value;
  }

  function formatPercent(value) {
    const number = Number(value || 0);
    if (!Number.isFinite(number)) return value;
    return `${(number * 100).toFixed(number >= 1 ? 0 : 1)}%`;
  }

  function formatCurrency(value, currencyOverride) {
    const currency = currencyOverride || state.settings?.budget?.currency || 'INR';
    const amount = Number(value || 0);
    if (!Number.isFinite(amount)) {
      return `${currency} ${value}`;
    }
    return `${currency} ${amount.toLocaleString('en-IN', {
      minimumFractionDigits: amount >= 1000 ? 0 : 2,
      maximumFractionDigits: 2,
    })}`;
  }

  function getNestedValue(object, path) {
    if (!object) return undefined;
    return path.split('.').reduce((acc, key) => {
      if (acc && Object.prototype.hasOwnProperty.call(acc, key)) {
        return acc[key];
      }
      return undefined;
    }, object);
  }

  function setNestedValue(object, path, value) {
    const keys = path.split('.');
    let ref = object;
    keys.forEach((key, index) => {
      if (index === keys.length - 1) {
        ref[key] = value;
      } else {
        if (!ref[key] || typeof ref[key] !== 'object') {
          ref[key] = {};
        }
        ref = ref[key];
      }
    });
  }

  function escapeSelector(value) {
    if (window.CSS && typeof window.CSS.escape === 'function') {
      return window.CSS.escape(value);
    }
    return String(value).replace(/[^a-zA-Z0-9_-]/g, '\\$&');
  }

  function setupSecretInput(input) {
    if (!input || input.dataset.secretBound === '1') return;
    input.dataset.secretBound = '1';
    input.addEventListener('focus', () => {
      if (input.dataset.secretHasValue === '1' && input.dataset.secretDirty !== '1' && input.value === SECRET_PLACEHOLDER) {
        input.value = '';
      }
    });
    const markDirty = () => {
      input.dataset.secretDirty = '1';
      if (input.value && input.value !== SECRET_PLACEHOLDER) {
        input.dataset.secretHasValue = '1';
      }
    };
    input.addEventListener('input', markDirty);
    input.addEventListener('change', markDirty);
    input.addEventListener('blur', () => {
      if (input.dataset.secretHasValue === '1' && input.dataset.secretDirty !== '1' && !input.value) {
        input.value = SECRET_PLACEHOLDER;
      }
      if (input.dataset.secretDirty === '1' && !input.value) {
        input.dataset.secretHasValue = '0';
      }
    });
  }

  function parseTags(text) {
    if (!text) return [];
    return text
      .split(/[\n,]+/)
      .map((item) => item.trim())
      .filter(Boolean);
  }

  function parseScheduleText(text) {
    if (!text) return [];
    return text
      .split(/\n+/)
      .map((line) => line.trim())
      .filter(Boolean)
      .map((line) => {
        const match = line.match(/^([A-Za-z]{2,})\s+(\d{2}:\d{2})\s*-\s*(\d{2}:\d{2})$/);
        if (!match) return null;
        const [, day, start, end] = match;
        return { day: day.toLowerCase(), start, end };
      })
      .filter(Boolean);
  }

  function scheduleToText(entries) {
    if (!Array.isArray(entries) || !entries.length) return '';
    return entries
      .map((entry) => {
        if (!entry || !entry.day) return null;
        const day = String(entry.day).slice(0, 3).toUpperCase();
        const start = entry.start || '00:00';
        const end = entry.end || '23:59';
        return `${day.charAt(0)}${day.slice(1).toLowerCase()} ${start}-${end}`;
      })
      .filter(Boolean)
      .join('\n');
  }

  function formatTone(value) {
    switch (String(value)) {
      case 'professional':
        return 'Professional';
      case 'government-aligned':
        return 'Government-aligned';
      case 'aggressive':
        return 'Aggressive Sales';
      default:
        return 'Friendly';
    }
  }

  function formatAutonomy(value) {
    switch (String(value)) {
      case 'auto':
        return 'Auto';
      case 'review':
        return 'Review-before-launch';
      default:
        return 'Draft-only';
    }
  }

  function showToast(message, variant = 'info') {
    const toast = document.createElement('div');
    toast.className = `smart-marketing__toast smart-marketing__toast--${variant}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    requestAnimationFrame(() => {
      toast.classList.add('is-visible');
    });
    setTimeout(() => {
      toast.classList.remove('is-visible');
      toast.addEventListener('transitionend', () => toast.remove(), { once: true });
    }, 4000);
  }

  function setButtonLoading(button, loading, label) {
    if (!button) return;
    if (loading) {
      if (!button.dataset.originalLabel) {
        button.dataset.originalLabel = button.innerHTML;
      }
      button.disabled = true;
      button.innerHTML = `<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> ${label || 'Working…'}`;
    } else {
      button.disabled = false;
      if (button.dataset.originalLabel) {
        button.innerHTML = button.dataset.originalLabel;
        delete button.dataset.originalLabel;
      }
    }
  }

  function apiRequest(action, body = {}) {
    const payload = Object.assign({ action, csrfToken }, body);
    return fetch('admin-smart-marketing.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload),
    }).then((response) => response.json());
  }

  function createChip(value, groupName) {
    const id = `${groupName}-${value.replace(/[^a-z0-9]+/gi, '-').toLowerCase()}`;
    const label = document.createElement('label');
    label.className = 'smart-marketing__chip';
    const input = document.createElement('input');
    input.type = 'checkbox';
    input.value = value;
    input.name = groupName + '[]';
    input.dataset.group = groupName;
    input.id = id;
    const span = document.createElement('span');
    span.textContent = value;
    label.appendChild(input);
    label.appendChild(span);
    return label;
  }

  function renderChips(container, values, selected) {
    if (!container) return;
    container.innerHTML = '';
    values.forEach((value) => {
      const chip = createChip(value, container.dataset.checkboxGroup);
      const input = chip.querySelector('input');
      if (selected && selected.includes(value)) {
        input.checked = true;
      }
      container.appendChild(chip);
    });
  }

  function createMetricsGrid(metrics) {
    const grid = document.createElement('div');
    grid.className = 'smart-marketing__metrics-grid';
    analyticsMetricDefinitions.forEach((definition) => {
      const value = metrics[definition.key] ?? 0;
      const item = document.createElement('div');
      item.innerHTML = `<span>${definition.label}</span><strong>${definition.format(value)}</strong>`;
      grid.appendChild(item);
    });
    return grid;
  }

  function renderAiHealth() {
    if (!elements.aiHealth || !elements.aiModels) return;
    const aiHealth = state.aiHealth || {};
    elements.aiHealth.textContent = aiHealth.message || 'Gemini configuration required.';
    elements.aiHealth.className = aiHealth.connected ? 'status status--ok' : 'status status--error';
    const models = aiHealth.models || {};
    elements.aiModels.innerHTML = '';
    Object.keys(models).forEach((key) => {
      const item = document.createElement('li');
      item.innerHTML = `<strong>${key.toUpperCase()}:</strong> ${models[key] || 'Unset'}`;
      elements.aiModels.appendChild(item);
    });
  }

  function statusBadgeClass(status) {
    switch (status) {
      case 'connected':
        return 'status status--ok';
      case 'warning':
        return 'status status--warn';
      case 'error':
        return 'status status--error';
      case 'disabled':
        return 'status status--warn';
      case 'disconnected':
        return 'status status--neutral';
      default:
        return 'status status--neutral';
    }
  }

  function renderIntegrationBanner() {
    if (!elements.integrationBanner) return;
    const entries = Object.entries(state.integrations || {}).map(([id, entry]) => ({
      id,
      ...(entry || {}),
    }));

    const critical = entries.filter((entry) => entry.status === 'error');
    const warnings = entries.filter(
      (entry) => entry.status !== 'error' && ['warning', 'disconnected', 'disabled'].includes(entry.status)
    );

    if (!critical.length && !warnings.length) {
      elements.integrationBanner.hidden = true;
      elements.integrationBanner.dataset.variant = '';
      if (elements.integrationBannerMessage) {
        elements.integrationBannerMessage.textContent = '';
      }
      return;
    }

    const affected = critical.length ? critical : warnings;
    const names = affected
      .map((entry) => entry.label || entry.id || '')
      .filter(Boolean);
    const summary = names.length
      ? names.join(', ')
      : `${affected.length} integration${affected.length > 1 ? 's' : ''}`;

    const variant = critical.length ? 'error' : 'warning';
    const message = critical.length
      ? `Critical integrations offline: ${summary}. Campaign automation is paused until reconnection.`
      : `Integrations need attention: ${summary}. Review before the next AI run.`;

    elements.integrationBanner.hidden = false;
    elements.integrationBanner.dataset.variant = variant;
    if (elements.integrationBannerMessage) {
      elements.integrationBannerMessage.textContent = message;
    }
  }

  function renderIntegrations() {
    if (!elements.integrations) return;
    const integrations = state.integrations || {};
    const catalogOrder = Object.keys(state.connectorCatalog || {});
    const keys = catalogOrder.length ? catalogOrder : Object.keys(integrations);
    elements.integrations.innerHTML = '';

    if (!keys.length) {
      const empty = document.createElement('li');
      empty.textContent = 'No integrations configured yet.';
      elements.integrations.appendChild(empty);
    } else {
      keys.forEach((key) => {
        const entry = integrations[key] || {};
        const status = entry.status || 'unknown';
        const details = entry.details || {};
        const label = entry.label || state.connectorCatalog?.[key]?.label || key;
        const summaryParts = [];
        if (details.message) {
          summaryParts.push(details.message);
        }
        if (details.lastValidatedAt) {
          summaryParts.push(`Validated ${formatDate(details.lastValidatedAt)}`);
        }
        if (details.validatedBy) {
          summaryParts.push(`By ${details.validatedBy}`);
        }
        const channels = Array.isArray(details.channels) ? details.channels : [];
        if (channels.length) {
          summaryParts.push(`Channels: ${channels.slice(0, 3).join(', ')}`);
        }
        const detailText = summaryParts.join(' · ');

        const li = document.createElement('li');
        li.innerHTML = `
          <span class="${statusBadgeClass(status)}">${status.toUpperCase()}</span>
          <strong>${escapeHtml(label)}</strong>
          <small>${escapeHtml(detailText || 'No validation run yet.')}</small>`;
        elements.integrations.appendChild(li);
      });
    }

    updateIntegrationSummaryViews();
    renderIntegrationBanner();
  }

  function getIntegrationRow(key) {
    if (!elements.settingsRoot) return null;
    return elements.settingsRoot.querySelector(
      `[data-integration-row="${escapeSelector(key)}"]`
    );
  }

  function updateIntegrationSummaryViews() {
    const integrationsData = state.integrations || {};
    const sectionChannels = state.settingsSections?.integrations?.channels || {};
    const catalogKeys = Object.keys(state.connectorCatalog || integrationsData);
    catalogKeys.forEach((key) => {
      const row = getIntegrationRow(key);
      if (!row) return;

      const integration = integrationsData[key] || {};
      const channel = sectionChannels[key] || {};
      const status = integration.status || channel.status || 'unknown';
      const details = integration.details || {};
      const statusEl = row.querySelector(`[data-integration-status="${escapeSelector(key)}"]`);
      if (statusEl) {
        statusEl.textContent = status.replace(/_/g, ' ').toUpperCase();
        statusEl.className = statusBadgeClass(status);
      }

      const validatedAt = details.lastValidatedAt || channel.lastValidatedAt || channel.lastTested || '';
      const validatedEl = row.querySelector(`[data-integration-validated="${escapeSelector(key)}"]`);
      if (validatedEl) {
        validatedEl.textContent = validatedAt ? formatDate(validatedAt) : '—';
      }

      const validatedBy = details.validatedBy || channel.validatedBy || '';
      const validatedByEl = row.querySelector(`[data-integration-validated-by="${escapeSelector(key)}"]`);
      if (validatedByEl) {
        validatedByEl.textContent = validatedBy ? validatedBy : '—';
      }

      const message = details.message || channel.message || '';
      const messageEl = row.querySelector(`[data-integration-message="${escapeSelector(key)}"]`);
      if (messageEl) {
        messageEl.textContent = message || '';
        messageEl.hidden = !message;
      }
    });
  }

  function setIntegrationRowBusy(key, busy, activeButton) {
    const row = getIntegrationRow(key);
    if (!row) return;
    row.querySelectorAll('button').forEach((button) => {
      if (button === activeButton) return;
      button.disabled = busy;
    });
  }

  function collectIntegrationCredentials(platform) {
    const row = getIntegrationRow(platform);
    if (!row) return {};
    const inputs = Array.from(row.querySelectorAll('[data-setting-field]'));
    const prefix = `channels.${platform}.`;
    const payload = {};
    inputs.forEach((input) => {
      const path = input.dataset.settingField || '';
      if (!path.startsWith(prefix)) return;
      const fieldKey = path.slice(prefix.length);
      const type = input.dataset.settingType || input.type;
      if (type === 'secret') {
        const dirty = input.dataset.secretDirty === '1';
        const hasExisting = input.dataset.secretHasValue === '1';
        const raw = input.value;
        if (!dirty && hasExisting) {
          return;
        }
        if (!dirty && !hasExisting && raw === '') {
          return;
        }
        if (!dirty && raw === SECRET_PLACEHOLDER) {
          return;
        }
        const cleaned = raw === SECRET_PLACEHOLDER ? '' : raw;
        if (!dirty && cleaned === '') {
          return;
        }
        payload[fieldKey] = cleaned;
        return;
      }
      let value = input.value;
      if (typeof value === 'string') {
        value = value.trim();
      }
      payload[fieldKey] = value;
    });
    return payload;
  }

  function formatIntegrationAuditContext(context) {
    if (!context || typeof context !== 'object') return '';
    const parts = [];
    Object.entries(context).forEach(([key, value]) => {
      if (value === null || value === undefined || value === '') {
        return;
      }
      let text;
      if (typeof value === 'object') {
        const keys = Object.keys(value);
        if (!keys.length) {
          return;
        }
        try {
          text = JSON.stringify(value);
        } catch (error) {
          text = String(value);
        }
      } else {
        text = String(value);
      }
      parts.push(`${key}: ${text}`);
    });
    return parts.slice(0, 3).join(' · ');
  }

  function applyIntegrationStatePatch(data, platform, entryKey = 'integration') {
    if (data.settings) {
      state.settings = data.settings;
    }
    if (data.sections) {
      state.settingsSections = data.sections;
    } else if (platform && data[entryKey]) {
      state.settingsSections = state.settingsSections || {};
      const current = state.settingsSections.integrations || { channels: {} };
      current.channels = current.channels || {};
      current.channels[platform] = data[entryKey];
      state.settingsSections.integrations = current;
    }
    if (data.integrations) state.integrations = data.integrations;
    if (data.connectors) state.connectors = data.connectors;
    if (data.audit) state.audit = data.audit;
    if (data.integrationsAudit) state.integrationsAudit = data.integrationsAudit;
    if (data.aiHealth) state.aiHealth = data.aiHealth;

    if (settingsViews.integrations) {
      renderSettingsSection('integrations');
    }

    renderAiHealth();
    renderIntegrations();
    renderConnectors();
    renderSettingsHistory('integrations');
    renderAudit();
    evaluateSettingsStatus();
  }

  function integrationRequest(action, platform, credentials, button, workingLabel) {
    if (!platform) {
      showToast('Integration platform missing.', 'error');
      return Promise.reject(new Error('Integration platform missing.'));
    }

    const payload = { platform };
    if (action === 'save') {
      payload.credentials = credentials || {};
    }

    setSectionAlert('integrations', []);
    setButtonLoading(button, true, workingLabel);
    setIntegrationRowBusy(platform, true, button);

    return apiRequest(`integration-${action}`, payload)
      .then((data) => {
        applyIntegrationStatePatch(data, platform, 'integration');
        const defaults = {
          save: 'Credentials saved',
          test: data.ok ? 'Validation successful' : 'Validation failed',
          disable: 'Integration disabled',
          delete: 'Credentials deleted',
        };
        const messages = Array.isArray(data?.messages) && data.messages.length
          ? data.messages
          : [defaults[action] || 'Action completed'];
        const success = data.ok !== false;
        const variant = success ? 'info' : 'error';
        setSectionAlert('integrations', messages, variant);
        showToast(messages[0], success ? 'success' : 'error');
        return data;
      })
      .catch((error) => {
        setSectionAlert('integrations', [error.message], 'error');
        showToast(error.message, 'error');
        throw error;
      })
      .finally(() => {
        setIntegrationRowBusy(platform, false);
        setButtonLoading(button, false);
      });
  }

  function initIntegrationActions() {
    const view = settingsViews.integrations;
    if (!view || !view.form) return;
    view.form.addEventListener('click', (event) => {
      const saveBtn = event.target.closest('[data-integration-save]');
      if (saveBtn) {
        const platform = saveBtn.dataset.integrationSave;
        const credentials = collectIntegrationCredentials(platform);
        integrationRequest('save', platform, credentials, saveBtn, 'Saving…').catch(() => {});
        return;
      }
      const testBtn = event.target.closest('[data-integration-test]');
      if (testBtn) {
        const platform = testBtn.dataset.integrationTest;
        integrationRequest('test', platform, {}, testBtn, 'Testing…').catch(() => {});
        return;
      }
      const disableBtn = event.target.closest('[data-integration-disable]');
      if (disableBtn) {
        const platform = disableBtn.dataset.integrationDisable;
        if (!platform) return;
        if (!confirm(`Disable ${connectorLabel(platform)} integration?`)) return;
        integrationRequest('disable', platform, {}, disableBtn, 'Disabling…').catch(() => {});
        return;
      }
      const deleteBtn = event.target.closest('[data-integration-delete]');
      if (deleteBtn) {
        const platform = deleteBtn.dataset.integrationDelete;
        if (!platform) return;
        if (!confirm(`Delete ${connectorLabel(platform)} credentials?`)) return;
        integrationRequest('delete', platform, {}, deleteBtn, 'Deleting…').catch(() => {});
      }
    });
  }

  function renderAnalytics() {
    const analytics = state.analytics || {};
    if (elements.analyticsUpdated) {
      elements.analyticsUpdated.textContent = analytics.updatedAt
        ? `Last sync ${formatDate(analytics.updatedAt)}`
        : 'Sync pending';
    }

    if (elements.analyticsKpis) {
      elements.analyticsKpis.innerHTML = '';
      (analytics.channels || []).forEach((channel) => {
        const wrapper = document.createElement('article');
        wrapper.className = 'smart-marketing__analytics-channel';
        const metrics = channel.metrics || {};
        wrapper.innerHTML = `
          <header>
            <h3>${escapeHtml(channel.label || channel.id || 'Channel')}</h3>
            <p>${formatNumber(metrics.leads || 0)} leads · ${formatCurrency(metrics.spend || 0)}</p>
          </header>`;
        wrapper.appendChild(createMetricsGrid(metrics));
        const campaignsContainer = document.createElement('div');
        campaignsContainer.className = 'smart-marketing__analytics-campaigns';
        (channel.campaigns || []).forEach((campaign) => {
          const details = document.createElement('details');
          const summary = document.createElement('summary');
          summary.innerHTML = `<strong>${escapeHtml(campaign.label || campaign.id || 'Campaign')}</strong><span>${formatCurrency((campaign.metrics || {}).cpl || 0)} CPL</span>`;
          details.appendChild(summary);
          details.appendChild(createMetricsGrid(campaign.metrics || {}));
          if (campaign.ads && campaign.ads.length) {
            const adsList = document.createElement('div');
            adsList.className = 'smart-marketing__analytics-ads';
            campaign.ads.forEach((ad) => {
              const adItem = document.createElement('div');
              adItem.className = 'smart-marketing__analytics-ad';
              adItem.innerHTML = `<h4>${escapeHtml(ad.label || ad.id || 'Ad')}</h4>`;
              adItem.appendChild(createMetricsGrid(ad.metrics || {}));
              adsList.appendChild(adItem);
            });
            details.appendChild(adsList);
          }
          campaignsContainer.appendChild(details);
        });
        wrapper.appendChild(campaignsContainer);
        elements.analyticsKpis.appendChild(wrapper);
      });
      if (!(analytics.channels || []).length) {
        elements.analyticsKpis.innerHTML = '<p class="smart-marketing__hint">No channel data available yet.</p>';
      }
    }

    if (elements.analyticsCohorts) {
      elements.analyticsCohorts.innerHTML = '';
      const cohorts = analytics.cohorts || {};
      Object.entries(cohorts).forEach(([key, items]) => {
        const section = document.createElement('section');
        section.innerHTML = `<h3>${escapeHtml(key.replace(/_/g, ' '))}</h3>`;
        const list = document.createElement('ul');
        (items || []).forEach((item) => {
          const li = document.createElement('li');
          li.innerHTML = `<strong>${escapeHtml(item.label || 'Cohort')}</strong><span>${formatNumber(item.leads || 0)} leads · ${formatCurrency(item.cpl || 0)}</span>`;
          if (item.sales) {
            li.innerHTML += `<span>${formatNumber(item.sales)} sales</span>`;
          }
          list.appendChild(li);
        });
        if (!(items || []).length) {
          const li = document.createElement('li');
          li.textContent = 'No cohort data available.';
          list.appendChild(li);
        }
        section.appendChild(list);
        elements.analyticsCohorts.appendChild(section);
      });
    }

    if (elements.analyticsFunnel) {
      const funnel = analytics.funnels || {};
      const stages = [
        { key: 'impressions', label: 'Impressions' },
        { key: 'clicks', label: 'Clicks' },
        { key: 'leads', label: 'Leads' },
        { key: 'qualified', label: 'Qualified' },
        { key: 'converted', label: 'Converted' },
      ];
      const max = Math.max(...stages.map((stage) => funnel[stage.key] || 0), 1);
      elements.analyticsFunnel.innerHTML = stages
        .map((stage) => {
          const value = funnel[stage.key] || 0;
          const width = Math.max(10, Math.round((value / max) * 100));
          return `<div class="smart-marketing__funnel-stage"><span>${stage.label}</span><div class="smart-marketing__funnel-bar" style="width:${width}%">${formatNumber(value)}</div></div>`;
        })
        .join('');
    }

    if (elements.analyticsCreatives) {
      elements.analyticsCreatives.innerHTML = '';
      const creatives = analytics.creatives || {};
      Object.entries(creatives).forEach(([key, items]) => {
        const section = document.createElement('section');
        section.innerHTML = `<h3>${escapeHtml(key.replace(/_/g, ' '))}</h3>`;
        const list = document.createElement('ul');
        (items || []).forEach((item) => {
          const li = document.createElement('li');
          li.innerHTML = `<strong>${escapeHtml(item.label || 'Creative')}</strong><span>${formatPercent(item.ctr || 0)} CTR · ${formatNumber(item.leads || 0)} leads · ${formatCurrency(item.cpl || 0)} CPL</span>`;
          list.appendChild(li);
        });
        if (!(items || []).length) {
          const li = document.createElement('li');
          li.textContent = 'No performance data yet.';
          list.appendChild(li);
        }
        section.appendChild(list);
        elements.analyticsCreatives.appendChild(section);
      });
    }

    if (elements.analyticsBudget) {
      const budget = analytics.budget || {};
      elements.analyticsBudget.innerHTML = `
        <div class="smart-marketing__budget-row"><span>Monthly cap</span><strong>${formatCurrency(budget.monthlyCap || 0)}</strong></div>
        <div class="smart-marketing__budget-row"><span>Spend to date</span><strong>${formatCurrency(budget.spendToDate || 0)}</strong></div>
        <div class="smart-marketing__budget-row"><span>Pacing</span><strong>${formatPercent(budget.pacing || 0)}</strong></div>
        <div class="smart-marketing__budget-row"><span>Burn rate</span><strong>${formatCurrency(budget.burnRate || 0)}/day</strong></div>
        <div class="smart-marketing__budget-row"><span>Expected burn</span><strong>${formatCurrency(budget.expectedBurn || 0)}/day</strong></div>`;
    }

    if (elements.analyticsAlerts) {
      elements.analyticsAlerts.innerHTML = '';
      const alerts = [...(analytics.budget?.alerts || []), ...(analytics.alerts || [])];
      if (!alerts.length) {
        elements.analyticsAlerts.innerHTML = '<p class="smart-marketing__hint">No alerts at this time.</p>';
      } else {
        const list = document.createElement('ul');
        alerts.forEach((alert) => {
          const li = document.createElement('li');
          li.textContent = alert.message || 'Alert logged.';
          list.appendChild(li);
        });
        elements.analyticsAlerts.appendChild(list);
      }
    }
  }

  function updateManualDetails(kind) {
    if (!elements.optimizationManualDetails) return;
    const container = elements.optimizationManualDetails;
    container.innerHTML = '';
    if (kind === 'promote_creative') {
      container.innerHTML = `
        <label>Creative name<input type="text" name="creative" placeholder="Headline or asset ID" required></label>
        <label>Channels<textarea name="channels" rows="2" placeholder="Google, Meta, WhatsApp"></textarea></label>`;
    } else if (kind === 'duplicate_campaign') {
      container.innerHTML = `
        <label>Source campaign<input type="text" name="source" placeholder="Campaign ID" required></label>
        <label>Target districts<textarea name="targets" rows="2" placeholder="Ranchi, Bokaro"></textarea></label>`;
    } else {
      container.innerHTML = `
        <label>Test type<select name="testType" required>
          <option value="Creative">Creative</option>
          <option value="CTA">CTA</option>
          <option value="Offer">Offer</option>
          <option value="Landing">Landing</option>
        </select></label>
        <label>Variant A<input type="text" name="variantA" placeholder="Current control" required></label>
        <label>Variant B<input type="text" name="variantB" placeholder="New challenger" required></label>
        <label>Schedule<input type="text" name="schedule" placeholder="Start next Monday"></label>`;
    }
  }

  function collectOptimizationPayload() {
    const payload = { autoRules: {} };
    Object.entries(optimizationRuleConfig).forEach(([ruleKey, config]) => {
      const toggle = elements.optimizationAuto?.querySelector(`[data-optimization-toggle="${ruleKey}"]`);
      if (!toggle) {
        return;
      }
      const rule = { enabled: toggle.checked };
      (config.fields || []).forEach((field) => {
        const input = elements.optimizationAuto.querySelector(`input[name="${ruleKey}.${field.key}"]`);
        if (!input) return;
        if (input.value === '') return;
        let value = Number(input.value);
        if (field.type === 'percent') {
          value = Number(input.value) / 100;
        }
        rule[field.key] = Number.isFinite(value) ? value : input.value;
      });
      payload.autoRules[ruleKey] = rule;
    });
    return payload;
  }

  function collectPolicyPayload() {
    const payload = {};
    if (!elements.governancePolicyList) return payload;
    elements.governancePolicyList.querySelectorAll('input[data-policy]').forEach((input) => {
      payload[input.dataset.policy] = input.checked;
    });
    const notes = elements.governancePolicyList.querySelector('[data-policy-notes]');
    if (notes) {
      payload.notes = notes.value;
    }
    return payload;
  }

  function collectNotificationsPayload() {
    const payload = { notifications: { dailyDigest: { channels: {} }, instant: {} } };
    if (elements.notificationsDigestEnabled) {
      payload.notifications.dailyDigest.enabled = elements.notificationsDigestEnabled.checked;
    }
    if (elements.notificationsDigestTime) {
      payload.notifications.dailyDigest.time = elements.notificationsDigestTime.value;
    }
    if (elements.notificationsDigestEmail) {
      payload.notifications.dailyDigest.channels.email = elements.notificationsDigestEmail.value;
    }
    if (elements.notificationsDigestWhatsapp) {
      payload.notifications.dailyDigest.channels.whatsapp = elements.notificationsDigestWhatsapp.value;
    }
    if (elements.notificationsInstantEmail) {
      payload.notifications.instant.email = elements.notificationsInstantEmail.checked;
    }
    if (elements.notificationsInstantWhatsapp) {
      payload.notifications.instant.whatsapp = elements.notificationsInstantWhatsapp.checked;
    }
    return payload;
  }

  function renderOptimization() {
    const optimization = state.optimization || {};
    const autoRules = optimization.autoRules || {};
    if (elements.optimizationAuto) {
      elements.optimizationAuto.innerHTML = '';
      Object.entries(optimizationRuleConfig).forEach(([ruleKey, config]) => {
        const rule = autoRules[ruleKey] || {};
        const section = document.createElement('section');
        section.className = 'smart-marketing__optimization-rule';
        const enabled = !!rule.enabled;
        section.innerHTML = `
          <header>
            <label><input type="checkbox" data-optimization-toggle="${ruleKey}" ${enabled ? 'checked' : ''}/> ${escapeHtml(config.label)}</label>
            <p>${escapeHtml(config.description)}</p>
          </header>`;
        const fieldsContainer = document.createElement('div');
        fieldsContainer.className = 'smart-marketing__optimization-fields';
        (config.fields || []).forEach((field) => {
          const value = rule[field.key];
          const input = document.createElement('input');
          input.name = `${ruleKey}.${field.key}`;
          input.dataset.rule = ruleKey;
          input.dataset.field = field.key;
          input.dataset.type = field.type;
          input.type = 'number';
          input.step = field.type === 'number' ? '1' : '0.1';
          if (field.type === 'currency') {
            input.step = '1';
          }
          if (field.type === 'percent') {
            input.value = Number.isFinite(value) ? (Number(value) * 100).toFixed(1) : '';
          } else {
            input.value = Number.isFinite(value) ? Number(value) : '';
          }
          const label = document.createElement('label');
          label.textContent = field.label;
          label.appendChild(input);
          fieldsContainer.appendChild(label);
        });
        section.appendChild(fieldsContainer);
        elements.optimizationAuto.appendChild(section);
      });
    }

    if (elements.optimizationHistory) {
      const history = optimization.history || [];
      elements.optimizationHistory.innerHTML = '<h3>Automation history</h3>';
      const list = document.createElement('ul');
      if (!history.length) {
        const item = document.createElement('li');
        item.textContent = 'No optimisation actions recorded yet.';
        list.appendChild(item);
      } else {
        history.slice(-10).reverse().forEach((entry) => {
          const item = document.createElement('li');
          item.innerHTML = `<strong>${escapeHtml(entry.rule || 'action')}</strong> · ${escapeHtml(entry.message || '')}<span>${formatDate(entry.timestamp)}</span>`;
          list.appendChild(item);
        });
      }
      elements.optimizationHistory.appendChild(list);
    }

    if (elements.optimizationLearning) {
      const learning = optimization.learning || {};
      elements.optimizationLearning.innerHTML = '<h3>Learning memory</h3>';
      const next = document.createElement('p');
      next.className = 'smart-marketing__hint';
      next.textContent = learning.nextBestAction || 'No recommendation yet.';
      elements.optimizationLearning.appendChild(next);
      const tests = document.createElement('ul');
      (learning.tests || []).slice(-5).reverse().forEach((test) => {
        const item = document.createElement('li');
        item.innerHTML = `<strong>${escapeHtml(test.id || '')}</strong> · ${escapeHtml(test.type || '')} · ${escapeHtml(test.status || '')}<span>${escapeHtml(test.result || '')}</span>`;
        tests.appendChild(item);
      });
      if (!(learning.tests || []).length) {
        const item = document.createElement('li');
        item.textContent = 'No experiments logged yet.';
        tests.appendChild(item);
      }
      elements.optimizationLearning.appendChild(tests);
    }

    updateManualDetails(elements.optimizationManualForm?.querySelector('select')?.value || 'promote_creative');
  }

  function renderGovernance() {
    const governance = state.governance || {};
    const emergency = governance.emergencyStop || {};
    if (elements.governanceBudgetToggle) {
      elements.governanceBudgetToggle.checked = !!(governance.budgetLock?.enabled);
    }
    if (elements.governanceBudgetCap) {
      elements.governanceBudgetCap.value = governance.budgetLock?.cap ?? '';
    }
    if (elements.governanceEmergency) {
      const isActive = !!emergency.active;
      elements.governanceEmergency.disabled = isActive;
      elements.governanceEmergency.classList.toggle('is-active', isActive);
      elements.governanceEmergency.innerHTML = isActive
        ? '<i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i> Emergency stop active'
        : '<i class="fa-solid fa-stop" aria-hidden="true"></i> Emergency stop';
      elements.governanceEmergency.setAttribute('aria-pressed', isActive ? 'true' : 'false');
      elements.governanceEmergency.title = isActive
        ? `Activated by ${emergency.triggeredBy || 'Admin'}`
        : 'Pause all channels instantly';
    }
    if (elements.governanceEmergencyStatus) {
      const isActive = !!emergency.active;
      if (isActive) {
        const since = emergency.triggeredAt ? formatDate(emergency.triggeredAt) : 'just now';
        const by = emergency.triggeredBy ? emergency.triggeredBy : 'Admin';
        elements.governanceEmergencyStatus.textContent = `Emergency stop active since ${since} by ${by}.`;
        elements.governanceEmergencyStatus.classList.add('is-active');
      } else {
        elements.governanceEmergencyStatus.textContent = 'Emergency stop is idle.';
        elements.governanceEmergencyStatus.classList.remove('is-active');
      }
    }
    if (elements.governancePolicyList) {
      const policy = governance.policyChecklist || {};
      elements.governancePolicyList.innerHTML = '';
      const items = [
        { key: 'pmSuryaClaims', label: 'PM Surya Ghar claims accurate' },
        { key: 'ethicalMessaging', label: 'Solar advertising ethics followed' },
        { key: 'disclaimerPlaced', label: 'Required disclaimers placed' },
        { key: 'dataAccuracy', label: 'Performance data verified' },
      ];
      items.forEach((item) => {
        const li = document.createElement('li');
        li.innerHTML = `<label><input type="checkbox" data-policy="${item.key}" ${policy[item.key] ? 'checked' : ''}/> ${item.label}</label>`;
        elements.governancePolicyList.appendChild(li);
      });
      const notes = document.createElement('li');
      notes.innerHTML = `<label>Notes<textarea rows="2" data-policy-notes>${escapeHtml(policy.notes || '')}</textarea></label>`;
      elements.governancePolicyList.appendChild(notes);
      const reviewed = document.createElement('li');
      reviewed.className = 'smart-marketing__hint';
      reviewed.textContent = policy.lastReviewed ? `Last reviewed ${formatDate(policy.lastReviewed)}` : 'Checklist not reviewed yet.';
      elements.governancePolicyList.appendChild(reviewed);
    }
    if (elements.governanceLog) {
      const logEntries = governance.log || [];
      elements.governanceLog.innerHTML = '<h3>Autonomy audit trail</h3>';
      const list = document.createElement('ul');
      if (!logEntries.length) {
        const li = document.createElement('li');
        li.textContent = 'No governance actions logged yet.';
        list.appendChild(li);
      } else {
        logEntries.slice(-10).reverse().forEach((entry) => {
          const li = document.createElement('li');
          const context = entry.context && Object.keys(entry.context).length
            ? Object.entries(entry.context)
                .map(([key, value]) => `${key}: ${typeof value === 'object' ? JSON.stringify(value) : value}`)
                .join(' · ')
            : '';
          li.innerHTML = `<strong>${escapeHtml(entry.event || '')}</strong> · ${escapeHtml(entry.user?.name || 'Admin')}<span>${formatDate(entry.timestamp)}</span>${context ? `<p>${escapeHtml(context)}</p>` : ''}`;
          list.appendChild(li);
        });
      }
      elements.governanceLog.appendChild(list);
    }
  }

  function renderNotifications() {
    const notifications = state.notifications || {};
    const digest = notifications.dailyDigest || {};
    if (elements.notificationsDigestEnabled) {
      elements.notificationsDigestEnabled.checked = !!digest.enabled;
    }
    if (elements.notificationsDigestTime) {
      elements.notificationsDigestTime.value = digest.time || '';
    }
    if (elements.notificationsDigestEmail) {
      elements.notificationsDigestEmail.value = digest.channels?.email || '';
    }
    if (elements.notificationsDigestWhatsapp) {
      elements.notificationsDigestWhatsapp.value = digest.channels?.whatsapp || '';
    }
    if (elements.notificationsInstantEmail) {
      elements.notificationsInstantEmail.checked = !!notifications.instant?.email;
    }
    if (elements.notificationsInstantWhatsapp) {
      elements.notificationsInstantWhatsapp.checked = !!notifications.instant?.whatsapp;
    }
    if (elements.notificationsLog) {
      const log = notifications.log || [];
      elements.notificationsLog.innerHTML = '<h3>Notification log</h3>';
      const list = document.createElement('ul');
      if (!log.length) {
        const li = document.createElement('li');
        li.textContent = 'No notifications sent yet.';
        list.appendChild(li);
      } else {
        log.slice(-10).reverse().forEach((entry) => {
          const li = document.createElement('li');
          li.innerHTML = `<strong>${escapeHtml(entry.type || 'notice')}</strong> · ${formatDate(entry.timestamp)}<span>${escapeHtml(entry.message || '')}</span>`;
          list.appendChild(li);
        });
      }
      elements.notificationsLog.appendChild(list);
    }
  }

  function createSelectField(config) {
    const { label, name, options = [], value } = config;
    const wrapper = document.createElement('label');
    wrapper.textContent = label;
    const select = document.createElement('select');
    select.name = name;
    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = 'Select';
    select.appendChild(placeholder);
    options.forEach((option) => {
      const opt = document.createElement('option');
      if (typeof option === 'string') {
        opt.value = option;
        opt.textContent = option;
      } else {
        opt.value = option.id || option.value || '';
        opt.textContent = option.name || option.label || opt.value;
      }
      select.appendChild(opt);
    });
    if (value && !options.some((option) => (option.id || option.value || option) === value)) {
      const current = document.createElement('option');
      current.value = value;
      current.textContent = value;
      current.selected = true;
      select.appendChild(current);
    } else if (value) {
      select.value = value;
    }
    wrapper.appendChild(select);
    return wrapper;
  }

  function createInputField(config) {
    const { label, name, value, placeholder = '' } = config;
    const wrapper = document.createElement('label');
    wrapper.textContent = label;
    const input = document.createElement('input');
    input.type = 'text';
    input.name = name;
    input.value = value || '';
    if (placeholder) {
      input.placeholder = placeholder;
    }
    wrapper.appendChild(input);
    return wrapper;
  }

  function renderConnectors() {
    if (!elements.connectorList) return;
    const connectors = state.connectors || [];
    elements.connectorList.innerHTML = '';
    if (!connectors.length) {
      const empty = document.createElement('p');
      empty.className = 'smart-marketing__hint';
      empty.textContent = 'No connectors configured yet.';
      elements.connectorList.appendChild(empty);
      return;
    }

    connectors.forEach((connector) => {
      const card = document.createElement('article');
      card.className = 'smart-marketing__connector-card';
      card.dataset.connectorId = connector.id;

      const header = document.createElement('header');
      header.innerHTML = `<h3>${connector.label}</h3><span class="${statusBadgeClass(connector.status || 'unknown')}">${(connector.status || 'unknown').toUpperCase()}</span>`;
      card.appendChild(header);

      if (connector.description) {
        const desc = document.createElement('p');
        desc.textContent = connector.description;
        card.appendChild(desc);
      }

      const fields = document.createElement('div');
      fields.className = 'smart-marketing__connector-fields';
      const details = connector.details || {};
      const options = connector.options || {};

      if (options.accounts && options.accounts.length) {
        fields.appendChild(
          createSelectField({
            label: 'Ad account',
            name: 'account',
            options: options.accounts,
            value: details.account || '',
          })
        );
      }

      if (options.pages && options.pages.length) {
        fields.appendChild(
          createSelectField({
            label: 'Page',
            name: 'page',
            options: options.pages,
            value: details.page || '',
          })
        );
      }

      if (options.youtubeChannels && options.youtubeChannels.length) {
        fields.appendChild(
          createSelectField({
            label: 'YouTube channel',
            name: 'channel',
            options: options.youtubeChannels,
            value: details.channel || '',
          })
        );
      }

      if (options.numbers && options.numbers.length) {
        fields.appendChild(
          createSelectField({
            label: 'Business number',
            name: 'number',
            options: options.numbers,
            value: details.number || '',
          })
        );
      }

      if (options.providers && options.providers.length) {
        fields.appendChild(
          createSelectField({
            label: 'Provider',
            name: 'provider',
            options: options.providers,
            value: details.provider || '',
          })
        );
      }

      if (connector.id === 'googleAds') {
        fields.appendChild(
          createInputField({
            label: 'Sub-account label',
            name: 'subAccount',
            value: details.subAccount || '',
            placeholder: 'Performance Max sandbox',
          })
        );
      }

      if (connector.id === 'email') {
        fields.appendChild(
          createInputField({
            label: 'Sending profile',
            name: 'profile',
            value: details.profile || '',
            placeholder: 'marketing@dakshayani.in',
          })
        );
      }

      if (fields.children.length) {
        card.appendChild(fields);
      }

      const actions = document.createElement('div');
      actions.className = 'smart-marketing__connector-actions';

      const connectButton = document.createElement('button');
      connectButton.type = 'button';
      connectButton.className = 'btn btn-primary';
      connectButton.dataset.connectorAction = 'connect';
      connectButton.textContent = 'Connect';
      actions.appendChild(connectButton);

      const testButton = document.createElement('button');
      testButton.type = 'button';
      testButton.className = 'btn btn-ghost';
      testButton.dataset.connectorAction = 'test';
      testButton.textContent = 'Test connection';
      actions.appendChild(testButton);

      card.appendChild(actions);

      const meta = document.createElement('p');
      meta.className = 'smart-marketing__connector-meta';
      const connectedAt = details.connectedAt ? `Connected ${formatDate(details.connectedAt)}` : 'Not connected';
      const lastTest = connector.lastTested
        ? `Last test ${formatDate(connector.lastTested)} (${(connector.lastTestResult || 'unknown').toUpperCase()})`
        : 'Test pending';
      meta.textContent = `${connectedAt} · ${lastTest}`;
      card.appendChild(meta);

      elements.connectorList.appendChild(card);
    });
  }

  function collectConnectorFields(card) {
    const data = {};
    card.querySelectorAll('select, input[type="text"]').forEach((input) => {
      if (!input.name) return;
      data[input.name] = input.value;
    });
    return data;
  }

  function initConnectorActions() {
    if (!elements.connectorList) return;
    elements.connectorList.addEventListener('click', (event) => {
      const button = event.target.closest('[data-connector-action]');
      if (!button) return;
      const card = button.closest('[data-connector-id]');
      if (!card) return;
      const connectorId = card.dataset.connectorId;
      if (!connectorId) return;

      const action = button.dataset.connectorAction;
      const payload = {
        action: action === 'connect' ? 'connector-connect' : 'connector-test',
        csrfToken,
        connector: connectorId,
      };
      if (action === 'connect') {
        payload.fields = collectConnectorFields(card);
      }

      const workingLabel = action === 'connect' ? 'Connecting…' : 'Testing…';
      setButtonLoading(button, true, workingLabel);

      fetch('admin-smart-marketing.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload),
      })
        .then((response) => response.json())
        .then((data) => {
          applyIntegrationStatePatch(data, connectorId, 'connector');
          renderSettingsKillSwitch();
          const success = data.ok !== false;
          const connectorEntry = data.connector || {};
          const message = connectorEntry.message || (success ? 'Connector updated' : 'Connector validation failed');
          showToast(message, success ? 'success' : 'error');
        })
        .catch((error) => {
          showToast(error.message, 'error');
        })
        .finally(() => {
          setButtonLoading(button, false);
        });
    });
  }

  function smartCurrency(value) {
    if (value === undefined || value === null || Number.isNaN(Number(value))) {
      return '—';
    }
    return `₹${Number(value).toLocaleString('en-IN', { maximumFractionDigits: 0 })}`;
  }

  function smartLabel(key) {
    const connector = (state.connectors || []).find((item) => item.id === key);
    return connector ? connector.label : key;
  }

  function getCampaignCatalog() {
    if (Array.isArray(state.campaignCatalog) && state.campaignCatalog.length) {
      return state.campaignCatalog;
    }
    return [
      { id: 'search', label: 'Search' },
      { id: 'display', label: 'Display' },
      { id: 'video', label: 'Video (YouTube Shorts)' },
      { id: 'lead_gen', label: 'Lead Gen forms' },
      { id: 'whatsapp', label: 'Click-to-WhatsApp' },
      { id: 'boosted', label: 'Boosted posts' },
      { id: 'email_sms', label: 'Email/SMS blasts' },
    ];
  }

  function renderCampaignBuilder() {
    if (!elements.campaignRunSelect) return;
    const runs = state.brainRuns || [];
    const currentRun = elements.campaignRunSelect.value;
    elements.campaignRunSelect.innerHTML = '<option value="">Select a generated plan</option>';
    runs.forEach((run) => {
      const option = document.createElement('option');
      option.value = run.id;
      const status = (run.status || '').toUpperCase();
      option.textContent = `#${run.id} · ${status} · ${formatDate(run.created_at)}`;
      if (String(run.id) === currentRun) {
        option.selected = true;
      }
      elements.campaignRunSelect.appendChild(option);
    });
    if (currentRun && !runs.some((run) => String(run.id) === currentRun)) {
      elements.campaignRunSelect.value = '';
    }

    if (elements.campaignTypes) {
      const previousSelection = new Set();
      elements.campaignTypes.querySelectorAll('input[type="checkbox"]:checked').forEach((input) => {
        previousSelection.add(input.value);
      });
      elements.campaignTypes.innerHTML = '';
      getCampaignCatalog().forEach((entry) => {
        const label = document.createElement('label');
        label.className = 'smart-marketing__checkbox';
        const input = document.createElement('input');
        input.type = 'checkbox';
        input.name = 'campaign_types[]';
        input.value = entry.id;
        input.checked = previousSelection.size ? previousSelection.has(entry.id) : true;
        label.appendChild(input);
        const span = document.createElement('span');
        span.textContent = entry.label;
        label.appendChild(span);
        elements.campaignTypes.appendChild(label);
      });
    }

    if (elements.landingExisting) {
      elements.landingExisting.innerHTML = '';
      const pages = state.sitePages || [];
      if (!pages.length) {
        const option = document.createElement('option');
        option.value = '/contact.html';
        option.textContent = '/contact.html';
        elements.landingExisting.appendChild(option);
      } else {
        pages.forEach((page) => {
          const option = document.createElement('option');
          option.value = page.path || '/contact.html';
          option.textContent = `${page.label} (${page.path})`;
          elements.landingExisting.appendChild(option);
        });
      }
    }

    updateLandingVisibility();
  }

  function updateLandingVisibility() {
    if (!elements.landingAuto) return;
    const selected = Array.from(elements.landingModeRadios || []).find((radio) => radio.checked)?.value || 'existing';
    elements.landingAuto.hidden = selected !== 'auto';
  }

  function collectSelectedCampaignTypes() {
    if (!elements.campaignTypes) return [];
    return Array.from(elements.campaignTypes.querySelectorAll('input[type="checkbox"]:checked')).map((input) => input.value);
  }

  function submitCampaignLaunch(event) {
    event.preventDefault();
    if (!elements.campaignRunSelect) return;
    const runId = parseInt(elements.campaignRunSelect.value, 10);
    if (!runId) {
      alert('Select a generated plan before launching.');
      return;
    }
    const types = collectSelectedCampaignTypes();
    if (!types.length) {
      alert('Select at least one campaign type.');
      return;
    }
    const landingMode = Array.from(elements.landingModeRadios || []).find((radio) => radio.checked)?.value || 'existing';
    const landing = { mode: landingMode };
    if (landingMode === 'existing') {
      landing.page = elements.landingExisting?.value || '/contact.html';
    } else {
      landing.headline = elements.landingHeadline?.value.trim() || '';
      landing.offer = elements.landingOffer?.value.trim() || '';
      landing.cta = elements.landingCta?.value.trim() || '';
      landing.whatsapp = elements.landingWhatsapp?.value.trim() || '';
      landing.call = elements.landingCall?.value.trim() || '';
    }

    const submitButton = elements.campaignForm.querySelector('button[type="submit"]');
    const original = submitButton.innerHTML;
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Launching…';

    fetch('admin-smart-marketing.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        action: 'campaign-launch',
        csrfToken,
        runId,
        campaignTypes: types,
        landing,
      }),
    })
      .then((response) => response.json())
      .then((data) => {
        if (!data.ok) throw new Error(data.error || 'Unable to launch campaigns');
        if (data.runs) state.brainRuns = data.runs;
        if (data.campaigns) state.campaigns = data.campaigns;
        if (data.automationLog) state.automationLog = data.automationLog;
        if (data.audit) state.audit = data.audit;
        renderRuns();
        renderCampaigns();
        renderAutomationLog();
        renderAudit();
      })
      .catch((error) => {
        alert(error.message);
      })
      .finally(() => {
        submitButton.disabled = false;
        submitButton.innerHTML = original;
      });
  }

  function renderCampaigns() {
    if (!elements.campaignOutput) return;
    const campaigns = state.campaigns || [];
    elements.campaignOutput.innerHTML = '';
    if (!campaigns.length) {
      const empty = document.createElement('p');
      empty.className = 'smart-marketing__hint';
      empty.textContent = 'No campaigns launched yet.';
      elements.campaignOutput.appendChild(empty);
      return;
    }

    campaigns
      .slice()
      .reverse()
      .forEach((campaign) => {
        const block = document.createElement('article');
        block.className = 'smart-marketing__campaign';
        block.innerHTML = `
          <header>
            <h3>${campaign.id}</h3>
            <span class="${statusBadgeClass(campaign.status || 'launched')}">${(campaign.label || campaign.type || '').toUpperCase()}</span>
          </header>
          <p class="smart-marketing__campaign-meta">Run #${campaign.run_id || '-'} · ${formatDate(campaign.launched_at)}</p>
          <dl>
            <div><dt>Budget</dt><dd>Daily ${smartCurrency(campaign.budget?.daily)} · Monthly ${smartCurrency(campaign.budget?.monthly)}</dd></div>
            <div><dt>Connectors</dt><dd>${Object.keys(campaign.connectors || {}).map((key) => smartLabel(key)).join(', ') || '—'}</dd></div>
            <div><dt>Landing</dt><dd>${campaign.landing?.type === 'auto' ? `Auto: ${campaign.landing?.url}` : campaign.landing?.url || '—'}</dd></div>
            <div><dt>Canonical IDs</dt><dd>${campaign.canonical?.campaign || '—'} · ${(campaign.canonical?.ad_groups || []).join(', ')}</dd></div>
            <div><dt>Metrics</dt><dd>CTR ${(Number(campaign.metrics?.ctr || 0) * 100).toFixed(2)}% · CPL ${smartCurrency(campaign.metrics?.cpl)}</dd></div>
            <div><dt>Leads</dt><dd>${(campaign.leads || []).map((lead) => `#${lead.id || '?'} (${lead.action || 'synced'})`).join(', ') || 'None yet'}</dd></div>
          </dl>
        `;
        elements.campaignOutput.appendChild(block);
      });
  }

  function renderAutomationLog() {
    if (!elements.automationLog) return;
    const log = state.automationLog || [];
    elements.automationLog.innerHTML = '';
    if (!log.length) {
      const empty = document.createElement('p');
      empty.className = 'smart-marketing__hint';
      empty.textContent = 'Automation activity will appear here.';
      elements.automationLog.appendChild(empty);
      return;
    }

    log.forEach((entry) => {
      const item = document.createElement('article');
      item.className = 'smart-marketing__automation-entry';
      item.innerHTML = `
        <header>
          <strong>${(entry.type || 'automation').replace(/_/g, ' ')}</strong>
          <span>${formatDate(entry.timestamp)}</span>
        </header>
        <p>${entry.message || ''}</p>
        ${entry.campaign_id ? `<p class="smart-marketing__automation-meta">Campaign: ${entry.campaign_id}</p>` : ''}
      `;
      elements.automationLog.appendChild(item);
    });
  }

  function initCampaignBuilder() {
    if (!elements.campaignForm) return;
    elements.campaignForm.addEventListener('submit', submitCampaignLaunch);
    (elements.landingModeRadios || []).forEach((radio) => {
      radio.addEventListener('change', updateLandingVisibility);
    });
    updateLandingVisibility();
  }

  function initAutomations() {
    if (!elements.runAutomationsButton) return;
    elements.runAutomationsButton.addEventListener('click', () => {
      const original = elements.runAutomationsButton.innerHTML;
      elements.runAutomationsButton.disabled = true;
      elements.runAutomationsButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Running…';
      fetch('admin-smart-marketing.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ action: 'automation-run', csrfToken }),
      })
        .then((response) => response.json())
        .then((data) => {
          if (!data.ok) throw new Error(data.error || 'Unable to run automations');
          if (data.campaigns) state.campaigns = data.campaigns;
          if (data.automationLog) state.automationLog = data.automationLog;
          if (data.audit) state.audit = data.audit;
          if (data.optimization) state.optimization = data.optimization;
          if (data.notifications) state.notifications = data.notifications;
          if (data.governance) state.governance = data.governance;
          renderCampaigns();
          renderAutomationLog();
          renderOptimization();
          renderNotifications();
          renderGovernance();
          renderAudit();
          showToast('Automation sweep completed', 'success');
        })
        .catch((error) => {
          showToast(error.message, 'error');
        })
        .finally(() => {
          elements.runAutomationsButton.disabled = false;
          elements.runAutomationsButton.innerHTML = original;
        });
    });
  }

  function initAnalyticsSection() {
    if (!elements.analyticsRefresh) return;
    elements.analyticsRefresh.addEventListener('click', () => {
      const original = elements.analyticsRefresh.innerHTML;
      elements.analyticsRefresh.disabled = true;
      elements.analyticsRefresh.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Refreshing…';
      apiRequest('analytics-refresh')
        .then((data) => {
          if (!data.ok) throw new Error(data.error || 'Unable to refresh analytics');
          if (data.analytics) state.analytics = data.analytics;
          if (data.notifications) state.notifications = data.notifications;
          renderAnalytics();
          renderNotifications();
          showToast('Analytics refreshed', 'success');
        })
        .catch((error) => {
          showToast(error.message, 'error');
        })
        .finally(() => {
          elements.analyticsRefresh.disabled = false;
          elements.analyticsRefresh.innerHTML = original;
        });
    });
  }

  function initOptimizationSection() {
    if (elements.optimizationSave) {
      elements.optimizationSave.addEventListener('click', () => {
        const payload = collectOptimizationPayload();
        elements.optimizationSave.disabled = true;
        apiRequest('optimization-save', { optimization: payload })
          .then((data) => {
            if (!data.ok) throw new Error(data.error || 'Unable to save optimisation rules');
            if (data.optimization) state.optimization = data.optimization;
            renderOptimization();
            showToast('Optimisation guardrails saved', 'success');
          })
          .catch((error) => {
            showToast(error.message, 'error');
          })
          .finally(() => {
            elements.optimizationSave.disabled = false;
          });
      });
    }

    if (elements.optimizationManualForm) {
      const kindSelect = elements.optimizationManualForm.querySelector('select[name="kind"]');
      if (kindSelect) {
        kindSelect.addEventListener('change', (event) => {
          updateManualDetails(event.target.value);
        });
        updateManualDetails(kindSelect.value);
      }
      elements.optimizationManualForm.addEventListener('submit', (event) => {
        event.preventDefault();
        const formData = new FormData(elements.optimizationManualForm);
        const kind = formData.get('kind');
        if (!kind) return;
        const notes = formData.get('notes') || '';
        const details = {};
        formData.forEach((value, key) => {
          if (['kind', 'notes'].includes(key)) return;
          details[key] = value;
        });
        elements.optimizationManualForm.querySelector('button[type="submit"]').disabled = true;
        apiRequest('optimization-manual-action', { kind, notes, details })
          .then((data) => {
            if (!data.ok) throw new Error(data.error || 'Unable to log manual action');
            if (data.optimization) state.optimization = data.optimization;
            if (data.notifications) state.notifications = data.notifications;
            if (data.governance) state.governance = data.governance;
            renderOptimization();
            renderNotifications();
            renderGovernance();
            showToast('Manual optimisation logged', 'success');
            elements.optimizationManualForm.reset();
            updateManualDetails('promote_creative');
          })
          .catch((error) => {
            showToast(error.message, 'error');
          })
          .finally(() => {
            const submit = elements.optimizationManualForm.querySelector('button[type="submit"]');
            if (submit) submit.disabled = false;
          });
      });
    }
  }

  function initGovernanceSection() {
    if (elements.governanceSaveBudget) {
      elements.governanceSaveBudget.addEventListener('click', () => {
        const payload = {
          enabled: elements.governanceBudgetToggle?.checked ?? false,
          cap: parseFloat(elements.governanceBudgetCap?.value || '0'),
        };
        elements.governanceSaveBudget.disabled = true;
        apiRequest('governance-budget-lock', payload)
          .then((data) => {
            if (!data.ok) throw new Error(data.error || 'Unable to update budget lock');
            if (data.governance) state.governance = data.governance;
            renderGovernance();
            showToast('Budget lock updated', 'success');
          })
          .catch((error) => {
            showToast(error.message, 'error');
          })
          .finally(() => {
            elements.governanceSaveBudget.disabled = false;
          });
      });
    }

    if (elements.governanceSavePolicy) {
      elements.governanceSavePolicy.addEventListener('click', () => {
        const policy = collectPolicyPayload();
        elements.governanceSavePolicy.disabled = true;
        apiRequest('governance-policy-save', { policy })
          .then((data) => {
            if (!data.ok) throw new Error(data.error || 'Unable to save policy checklist');
            if (data.governance) state.governance = data.governance;
            renderGovernance();
            showToast('Policy checklist saved', 'success');
          })
          .catch((error) => {
            showToast(error.message, 'error');
          })
          .finally(() => {
            elements.governanceSavePolicy.disabled = false;
          });
      });
    }

    if (elements.governanceEmergency) {
      elements.governanceEmergency.addEventListener('click', () => {
        if (!confirm('Activate emergency stop across all channels?')) return;
        elements.governanceEmergency.disabled = true;
        apiRequest('governance-emergency-stop')
          .then((data) => {
            if (!data.ok) throw new Error(data.error || 'Unable to trigger emergency stop');
            if (data.campaigns) state.campaigns = data.campaigns;
            if (data.settings) state.settings = data.settings;
            if (data.governance) state.governance = data.governance;
            if (data.notifications) state.notifications = data.notifications;
            if (data.automationLog) state.automationLog = data.automationLog;
            if (data.audit) state.audit = data.audit;
            renderCampaigns();
            renderGovernance();
            renderNotifications();
            renderAutomationLog();
            renderAudit();
            renderAutonomyMode();
            showToast('Emergency stop engaged', 'warning');
          })
          .catch((error) => {
            showToast(error.message, 'error');
          })
          .finally(() => {
            elements.governanceEmergency.disabled = false;
          });
      });
    }

    if (elements.governanceExport) {
      elements.governanceExport.addEventListener('click', () => {
        elements.governanceExport.disabled = true;
        apiRequest('governance-data-request', { mode: 'export' })
          .then((data) => {
            if (!data.ok) throw new Error(data.error || 'Unable to export data');
            if (data.governance) state.governance = data.governance;
            if (data.notifications) state.notifications = data.notifications;
            renderGovernance();
            renderNotifications();
            if (data.download) {
              showToast(`Export saved as ${data.download}`, 'success');
            } else {
              showToast('Export queued', 'success');
            }
          })
          .catch((error) => {
            showToast(error.message, 'error');
          })
          .finally(() => {
            elements.governanceExport.disabled = false;
          });
      });
    }

    if (elements.governanceErase) {
      elements.governanceErase.addEventListener('click', () => {
        if (!confirm('Queue PII erasure request?')) return;
        elements.governanceErase.disabled = true;
        apiRequest('governance-data-request', { mode: 'erase' })
          .then((data) => {
            if (!data.ok) throw new Error(data.error || 'Unable to queue erasure');
            if (data.governance) state.governance = data.governance;
            if (data.notifications) state.notifications = data.notifications;
            renderGovernance();
            renderNotifications();
            showToast('Erasure request queued', 'info');
          })
          .catch((error) => {
            showToast(error.message, 'error');
          })
          .finally(() => {
            elements.governanceErase.disabled = false;
          });
      });
    }
  }

  function initNotificationsSection() {
    if (elements.notificationsForm) {
      elements.notificationsForm.addEventListener('submit', (event) => {
        event.preventDefault();
        const payload = collectNotificationsPayload();
        elements.notificationsForm.querySelector('button[type="submit"]').disabled = true;
        apiRequest('notifications-save', payload)
          .then((data) => {
            if (!data.ok) throw new Error(data.error || 'Unable to save notifications');
            if (data.notifications) state.notifications = data.notifications;
            renderNotifications();
            showToast('Notification preferences saved', 'success');
          })
          .catch((error) => {
            showToast(error.message, 'error');
          })
          .finally(() => {
            const submit = elements.notificationsForm.querySelector('button[type="submit"]');
            if (submit) submit.disabled = false;
          });
      });
    }

    if (elements.notificationsTest) {
      elements.notificationsTest.addEventListener('click', () => {
        elements.notificationsTest.disabled = true;
        apiRequest('notifications-test')
          .then((data) => {
            if (!data.ok) throw new Error(data.error || 'Unable to send test notification');
            if (data.notifications) state.notifications = data.notifications;
            renderNotifications();
            showToast('Test notification logged', 'success');
          })
          .catch((error) => {
            showToast(error.message, 'error');
          })
          .finally(() => {
            elements.notificationsTest.disabled = false;
          });
      });
    }
  }

  function renderAudit() {
    if (!elements.audit) return;
    const auditEntries = state.audit || [];
    elements.audit.innerHTML = '';
    if (!auditEntries.length) {
      const li = document.createElement('li');
      li.textContent = 'No activity recorded yet.';
      elements.audit.appendChild(li);
      return;
    }
    auditEntries.forEach((entry) => {
      const li = document.createElement('li');
      const context = entry.context && Object.keys(entry.context).length
        ? Object.entries(entry.context)
            .map(([key, value]) => `${key}: ${typeof value === 'object' ? JSON.stringify(value) : value}`)
            .join(' · ')
        : '';
      li.innerHTML = `
        <span>${formatDate(entry.timestamp)}</span>
        <strong>${entry.action}</strong>
        <small>${entry.user?.name || 'Admin'}${context ? ' · ' + escapeHtml(context) : ''}</small>`;
      elements.audit.appendChild(li);
    });
  }

  function renderAutonomyMode() {
    if (!elements.autonomyModeLabel) return;
    const mode = state.settings?.autonomy?.mode || 'draft';
    let text = 'Draft-only';
    if (mode === 'auto') text = 'Auto launch & optimise';
    if (mode === 'review') text = 'Review-before-launch';
    elements.autonomyModeLabel.innerHTML = `<span class="status status--mode">${text}</span>`;
  }

  function getSelectedChips(container) {
    if (!container) return [];
    return Array.from(container.querySelectorAll('input[type="checkbox"]:checked')).map((input) => input.value);
  }

  function collectBrainPayload() {
    return {
      action: 'generate-plan',
      csrfToken,
      goals: getSelectedChips(elements.goalsGroup),
      regions: (elements.regionsInput.value || '')
        .split(',')
        .map((value) => value.trim())
        .filter(Boolean),
      products: getSelectedChips(elements.productsGroup),
      languages: getSelectedChips(elements.languagesGroup),
      daily_budget: parseFloat(elements.dailyBudget.value || '0'),
      monthly_budget: parseFloat(elements.monthlyBudget.value || '0'),
      min_bid: parseFloat(elements.minBid.value || '0'),
      cpa_guardrail: parseFloat(elements.cpaGuardrail.value || '0'),
      autonomy_mode: elements.autonomySelect.value,
      notes: elements.notesInput.value || '',
      compliance_platform_policy: elements.brainForm.querySelector('[data-compliance="platform_policy"]').checked,
      compliance_brand_tone: elements.brainForm.querySelector('[data-compliance="brand_tone"]').checked,
      compliance_legal_disclaimers: elements.brainForm.querySelector('[data-compliance="legal_disclaimers"]').checked,
    };
  }

  function renderPlanRun(run) {
    const wrapper = document.createElement('article');
    wrapper.className = 'smart-marketing__run';
    const status = run.status || 'draft';
    const statusLabel = status.toUpperCase();
    const buttons = [];
    if (status === 'pending') {
      buttons.push({ label: 'Approve & Launch', status: 'live', tone: 'primary' });
      buttons.push({ label: 'Send back to Draft', status: 'draft', tone: 'ghost' });
    } else if (status === 'draft') {
      buttons.push({ label: 'Submit for Review', status: 'pending', tone: 'primary' });
    } else if (status === 'live') {
      buttons.push({ label: 'Pause (Kill)', status: 'halted', tone: 'danger' });
    } else if (status === 'halted') {
      buttons.push({ label: 'Reopen as Draft', status: 'draft', tone: 'ghost' });
    }

    const created = formatDate(run.created_at);
    const rawText = run.plan?.rawText;
    const targetKpis = run.plan?.kpi_targets || {};

    wrapper.innerHTML = `
      <header>
        <h3>Plan #${run.id}</h3>
        <span class="${statusBadgeClass(status)}">${statusLabel}</span>
      </header>
      <p class="smart-marketing__run-meta">Created ${created}</p>
      ${rawText ? `<div class="smart-marketing__run-section">
        <section>
          <h4>AI summary</h4>
          <p>${escapeHtml(rawText)}</p>
        </section>
      </div>` : `<div class="smart-marketing__run-section">
        ${renderPlanSection('Channel plan', run.plan?.channel_plan)}
        ${renderPlanSection('Audience plan', run.plan?.audience_plan)}
        ${renderPlanSection('Creative plan', run.plan?.creative_plan)}
        ${renderPlanSection('Landing plan', run.plan?.landing_plan)}
        ${renderPlanSection('Budget allocation', run.plan?.budget_allocation)}
        ${renderPlanSection('KPI targets', targetKpis)}
        ${renderPlanSection('Optimisation loop', run.plan?.optimisation_loop)}
      </div>`}
      <details>
        <summary>Raw response</summary>
        <pre>${escapeHtml(run.response_text || '')}</pre>
      </details>
    `;

    if (buttons.length) {
      const actions = document.createElement('div');
      actions.className = 'smart-marketing__run-actions';
      buttons.forEach((button) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = button.label;
        btn.className = `btn btn-${button.tone}`;
        btn.addEventListener('click', () => updateRunStatus(run.id, button.status));
        actions.appendChild(btn);
      });
      wrapper.appendChild(actions);
    }

    return wrapper;
  }

  function renderPlanSection(title, data) {
    if (!data) return '';
    const formatted = Array.isArray(data) ? data.map((entry) => formatObject(entry)).join('') : formatObject(data);
    return `
      <section>
        <h4>${title}</h4>
        ${formatted || '<p>No data provided.</p>'}
      </section>
    `;
  }

  function formatObject(value) {
    if (Array.isArray(value)) {
      return `<ul>${value.map((item) => `<li>${formatObject(item)}</li>`).join('')}</ul>`;
    }
    if (typeof value === 'object' && value !== null) {
      return `<ul>${Object.entries(value)
        .map(([key, val]) => `<li><strong>${escapeHtml(String(key))}:</strong> ${formatObject(val)}</li>`)
        .join('')}</ul>`;
    }
    return `<p>${escapeHtml(String(value))}</p>`;
  }

  function escapeHtml(value) {
    return value
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function renderRuns() {
    if (!elements.brainRuns) return;
    const runs = state.brainRuns || [];
    elements.brainRuns.innerHTML = '';
    if (!runs.length) {
      const empty = document.createElement('p');
      empty.textContent = 'No plans generated yet. Configure inputs and run the brain.';
      elements.brainRuns.appendChild(empty);
      return;
    }
    runs.forEach((run) => {
      elements.brainRuns.appendChild(renderPlanRun(run));
    });
    renderCampaignBuilder();
  }

  function renderAssets() {
    if (!elements.assetsList) return;
    const assets = state.assets || [];
    elements.assetsList.innerHTML = '';
    if (!assets.length) {
      elements.assetsList.textContent = 'No creative assets generated yet.';
      return;
    }
    assets.forEach((asset) => {
      const block = document.createElement('article');
      block.className = 'smart-marketing__asset';
      block.innerHTML = `<header><h4>${asset.type.toUpperCase()}</h4><span>${formatDate(asset.created_at)}</span></header>`;
      if (asset.type === 'text') {
        const english = asset.payload?.output?.english || [];
        const hindi = asset.payload?.output?.hindi || [];
        block.innerHTML += `
          <section><h5>English</h5>${renderList(english)}</section>
          <section><h5>Hindi</h5>${renderList(hindi)}</section>
          <details><summary>Brief</summary><p>${escapeHtml(asset.payload?.brief || '')}</p></details>`;
      } else if (asset.type === 'image') {
        const path = asset.payload?.path;
        block.innerHTML += `
          <figure>
            <img src="${path}" alt="Generated marketing image" />
            <figcaption>${escapeHtml(asset.payload?.preset || '')}</figcaption>
          </figure>
          <details><summary>Prompt</summary><p>${escapeHtml(asset.payload?.prompt || '')}</p></details>`;
      } else if (asset.type === 'tts') {
        const path = asset.payload?.path;
        block.innerHTML += `
          <audio controls src="${path}"></audio>
          <details><summary>Script</summary><p>${escapeHtml(asset.payload?.script || '')}</p></details>`;
      }
      elements.assetsList.appendChild(block);
    });
  }

  function renderList(items) {
    if (!items || !items.length) return '<p>No output.</p>';
    return `<ul>${items.map((item) => `<li>${escapeHtml(String(item))}</li>`).join('')}</ul>`;
  }

  function summariseList(list, limit = 2) {
    if (!Array.isArray(list) || !list.length) return '—';
    const values = list
      .map((item) => (typeof item === 'string' ? item : String(item || '')))
      .map((item) => item.trim())
      .filter(Boolean);
    if (!values.length) return '—';
    const preview = values.slice(0, limit);
    const extra = values.length - preview.length;
    return preview.join(', ') + (extra > 0 ? ` +${extra}` : '');
  }

  function formatUpdatedLabel(data) {
    if (!data || !data.lastUpdatedAt) {
      return 'Never updated';
    }
    const user = data.lastUpdatedBy || 'Admin';
    return `Last updated on ${formatDate(data.lastUpdatedAt)} by ${escapeHtml(user)}`;
  }

  function renderComplianceFlags(data) {
    const view = settingsViews.compliance;
    if (!view || !view.flagged || !view.flaggedList) return;
    const items = Array.isArray(data?.flaggedCreatives) ? data.flaggedCreatives : [];
    if (!items.length) {
      view.flagged.hidden = true;
      view.flaggedList.innerHTML = '';
      return;
    }
    view.flagged.hidden = false;
    view.flaggedList.innerHTML = items
      .map((item) => `<li>${escapeHtml(String(item))}</li>`)
      .join('');
  }

  function updateBudgetUsage() {
    const view = settingsViews.budget;
    if (!view || !view.budgetSpend || !view.budgetRemaining) return;
    const analyticsBudget = state.analytics?.budget || {};
    const section = state.settingsSections?.budget || {};
    const currency = section.currency || state.settings?.budget?.currency || 'INR';
    const todaySpend = analyticsBudget.burnRate || analyticsBudget.dailySpend || 0;
    const remaining = Math.max(0, (section.monthlyCap || 0) - (analyticsBudget.spendToDate || 0));
    view.budgetSpend.textContent = formatCurrency(todaySpend, currency);
    view.budgetRemaining.textContent = formatCurrency(remaining, currency);
  }

  function renderSettingsHistory(section) {
    const view = settingsViews[section];
    if (!view || !view.history) return;
    if (section === 'integrations') {
      const entries = (state.integrationsAudit || []).slice(-8).reverse();
      if (!entries.length) {
        view.history.innerHTML = '<p class="smart-settings__hint">No integration activity recorded yet.</p>';
        return;
      }
      const list = document.createElement('ul');
      entries.forEach((entry) => {
        const li = document.createElement('li');
        const when = formatDate(entry.timestamp);
        const platformLabel = connectorLabel(entry.platform || '') || entry.platform || 'Integration';
        const actionLabel = (entry.action || 'update').replace(/_/g, ' ').toUpperCase();
        const actor = escapeHtml(entry.admin?.email || entry.admin?.name || 'Admin');
        const developerMessage = entry.developer_message ? escapeHtml(entry.developer_message) : '';
        const contextTextRaw = formatIntegrationAuditContext(entry.context);
        const contextText = contextTextRaw ? escapeHtml(contextTextRaw) : '';
        const detailSegments = [developerMessage, contextText].filter(Boolean).join(' · ');
        li.innerHTML = `<strong>${when}</strong> · ${escapeHtml(platformLabel)} · ${escapeHtml(actionLabel)} · ${actor}`;
        if (detailSegments) {
          li.innerHTML += `<small>${detailSegments}</small>`;
        }
        list.appendChild(li);
      });
      view.history.innerHTML = '';
      view.history.appendChild(list);
      return;
    }

    const entries = (state.settingsAudit || [])
      .filter((entry) => entry.section === section)
      .slice(-5)
      .reverse();
    if (!entries.length) {
      view.history.innerHTML = '<p class="smart-settings__hint">No changes logged yet.</p>';
      return;
    }
    const list = document.createElement('ul');
    entries.forEach((entry) => {
      const li = document.createElement('li');
      const when = formatDate(entry.timestamp);
      const actor = escapeHtml(entry.user?.email || entry.user?.name || 'Admin');
      const fields = (entry.changes || []).slice(0, 3).map((item) => escapeHtml(String(item))).join(', ');
      li.innerHTML = `<strong>${when}</strong> · ${actor}${fields ? ` · ${fields}` : ''}`;
      list.appendChild(li);
    });
    view.history.innerHTML = '';
    view.history.appendChild(list);
  }

  function buildSectionSummary(section, data) {
    if (!data) return '';
    switch (section) {
      case 'business': {
        const tone = formatTone(data.brandTone);
        const languages = summariseList(data.defaultLanguages, 3);
        const locations = summariseList(data.baseLocations, 2);
        return `Tone ${tone} · Lang ${languages} · ${locations}`;
      }
      case 'goals': {
        const goal = data.goalType || 'Leads';
        const autonomy = formatAutonomy(data.autonomyMode);
        const products = summariseList(data.targetProducts, 2);
        return `${goal} · ${autonomy} · ${products}`;
      }
      case 'budget': {
        const daily = formatCurrency(data.dailyBudget || 0, data.currency);
        const monthly = formatCurrency(data.monthlyCap || 0, data.currency);
        const autoScaling = data.autoScaling ? 'Auto-scaling ON' : 'Auto-scaling OFF';
        return `${daily}/day · ${monthly}/mo · ${autoScaling}`;
      }
      case 'audience': {
        const locations = summariseList(data.locations, 2);
        const ages = `${data.ageRange?.min || 18}-${data.ageRange?.max || 65}`;
        const languageSummary = summariseList((data.languageSplit || []).map((entry) => `${entry.language || ''} ${entry.percent || 0}%`), 2);
        return `${locations} · Ages ${ages} · ${languageSummary}`;
      }
      case 'compliance': {
        const disclaimer = data.autoDisclaimer ? 'Disclaimer ON' : 'Disclaimer OFF';
        const checks = data.policyChecks ? 'Policy checks ON' : 'Policy checks OFF';
        return `${disclaimer} · ${checks}`;
      }
      case 'integrations': {
        const channels = data.channels || {};
        const total = Object.keys(channels).length;
        const connected = Object.values(channels).filter((item) => item.status === 'connected').length;
        const failing = Object.values(channels).filter((item) => item.status === 'error').length;
        if (failing) {
          return `${connected}/${total} connected · ${failing} failing`;
        }
        return `${connected}/${total} connected`;
      }
      default:
        return '';
    }
  }

  function setSectionAlert(section, messages, variant = 'info') {
    const view = settingsViews[section];
    if (!view || !view.alert) return;
    if (!messages || !messages.length) {
      view.alert.hidden = true;
      view.alert.textContent = '';
      view.alert.dataset.variant = '';
      return;
    }
    const list = Array.isArray(messages) ? messages : [messages];
    view.alert.hidden = false;
    view.alert.dataset.variant = variant;
    view.alert.innerHTML = `<ul>${list
      .map((message) => `<li>${escapeHtml(String(message))}</li>`)
      .join('')}</ul>`;
  }

  function setSectionBusy(section, busy, message) {
    const view = settingsViews[section];
    if (!view) return;
    const buttons = [view.saveBtn, view.revertBtn, view.testBtn, view.syncBtn, view.revalidateBtn];
    buttons.forEach((button) => {
      if (button) button.disabled = busy;
    });
    if (busy && message) {
      setSaving('pending', message);
    } else {
      evaluateSettingsStatus();
    }
  }

  function populateSection(section, data) {
    const view = settingsViews[section];
    if (!view || !view.form) return;
    const fields = Array.from(view.form.querySelectorAll('[data-setting-field]'));
    const grouped = new Map();
    fields.forEach((field) => {
      const path = field.dataset.settingField;
      if (!path) return;
      if (!grouped.has(path)) grouped.set(path, []);
      grouped.get(path).push(field);
    });

    grouped.forEach((inputs, path) => {
      const primary = inputs[0];
      const type = primary.dataset.settingType || primary.type;
      const value = getNestedValue(data, path);

      if (type === 'array') {
        const selected = Array.isArray(value) ? value.map(String) : [];
        inputs.forEach((input) => {
          input.checked = selected.includes(input.value);
        });
      } else if (type === 'boolean') {
        inputs[0].checked = Boolean(value);
      } else if (type === 'language') {
        const split = Array.isArray(value) ? value : [];
        inputs.forEach((input) => {
          const entry = split.find((item) => item.language === input.dataset.language);
          input.value = entry ? entry.percent : 0;
        });
      } else if (type === 'schedule') {
        inputs[0].value = scheduleToText(Array.isArray(value) ? value : []);
      } else if (type === 'tags') {
        inputs[0].value = Array.isArray(value) ? value.join('\n') : value || '';
      } else if (type === 'secret') {
        const hasValue = value === SECRET_SENTINEL || (typeof value === 'string' && value !== '');
        inputs.forEach((input) => {
          input.value = hasValue ? SECRET_PLACEHOLDER : '';
          input.dataset.secretHasValue = hasValue ? '1' : '0';
          input.dataset.secretDirty = '0';
          setupSecretInput(input);
        });
      } else if (primary.type === 'radio') {
        inputs.forEach((input) => {
          input.checked = input.value === String(value || '');
        });
      } else if (primary.type === 'number' || type === 'number') {
        inputs[0].value = value !== undefined && value !== null ? value : '';
      } else {
        inputs[0].value = value !== undefined && value !== null ? value : '';
      }
    });

    if (view.summary) {
      view.summary.textContent = buildSectionSummary(section, data);
    }
    if (view.updated) {
      view.updated.innerHTML = formatUpdatedLabel(data);
    }
    renderSettingsHistory(section);

    if (section === 'budget') {
      updateBudgetUsage();
    } else if (section === 'compliance') {
      renderComplianceFlags(data);
    } else if (section === 'integrations') {
      updateIntegrationSummaryViews();
      if (view.gemini) {
        const status = state.aiHealth || {};
        const connected = status.connected ? 'Gemini key validated' : 'Add Gemini key in AI Studio';
        const models = Array.isArray(status.models) ? status.models : Object.values(status.models || {});
        const modelsText = models && models.length ? `Models: ${models.filter(Boolean).join(', ')}` : '';
        view.gemini.textContent = `${connected}${modelsText ? ` · ${modelsText}` : ''}`;
      }
    }
  }

  function collectSectionData(section) {
    const view = settingsViews[section];
    if (!view || !view.form) return {};
    const fields = Array.from(view.form.querySelectorAll('[data-setting-field]'));
    const grouped = new Map();
    fields.forEach((field) => {
      const path = field.dataset.settingField;
      if (!path) return;
      if (!grouped.has(path)) grouped.set(path, []);
      grouped.get(path).push(field);
    });

    const payload = {};
    grouped.forEach((inputs, path) => {
      const primary = inputs[0];
      const type = primary.dataset.settingType || primary.type;
      let value;

      if (type === 'array') {
        value = inputs.filter((input) => input.checked).map((input) => input.value);
      } else if (type === 'boolean') {
        value = Boolean(primary.checked);
      } else if (type === 'language') {
        value = inputs
          .map((input) => ({
            language: input.dataset.language,
            percent: parseInt(input.value, 10) || 0,
          }))
          .filter((entry) => entry.language);
      } else if (type === 'schedule') {
        value = parseScheduleText(primary.value);
      } else if (type === 'tags') {
        value = parseTags(primary.value);
      } else if (type === 'secret') {
        const dirty = primary.dataset.secretDirty === '1';
        const hasExisting = primary.dataset.secretHasValue === '1';
        const raw = primary.value;
        if (!dirty && hasExisting) {
          return;
        }
        if (!dirty && !hasExisting && raw === '') {
          return;
        }
        if (!dirty && raw === SECRET_PLACEHOLDER) {
          return;
        }
        const cleaned = raw === SECRET_PLACEHOLDER ? '' : raw;
        if (!dirty && cleaned === '') {
          return;
        }
        value = cleaned;
      } else if (primary.type === 'radio') {
        const selected = inputs.find((input) => input.checked);
        value = selected ? selected.value : inputs[0].value;
      } else if (primary.type === 'number' || type === 'number') {
        const numeric = parseFloat(primary.value);
        value = Number.isFinite(numeric) ? numeric : 0;
      } else {
        value = primary.value;
      }

      setNestedValue(payload, path, value);
    });

    return payload;
  }

  function renderSettingsSection(section) {
    const data = state.settingsSections?.[section] || {};
    populateSection(section, data);
  }

  function renderSettingsSections() {
    Object.keys(settingsViews).forEach((section) => {
      renderSettingsSection(section);
    });
  }

  function renderSettingsAudit() {
    Object.keys(settingsViews).forEach((section) => renderSettingsHistory(section));
  }

  function evaluateSettingsStatus() {
    if (!elements.settingsStatus) return;
    const values = Object.values(state.integrations || {});
    const failing = values.filter((entry) => entry.status === 'error');
    const warning = values.filter((entry) => entry.status === 'warning');
    const offline = values.filter((entry) => ['disconnected', 'disabled'].includes(entry.status));
    if (failing.length) {
      setSaving('error', `${failing.length} integration${failing.length > 1 ? 's' : ''} require attention`);
    } else if (warning.length) {
      setSaving('pending', `${warning.length} integration${warning.length > 1 ? 's' : ''} in warning`);
    } else if (offline.length) {
      setSaving('pending', `${offline.length} integration${offline.length > 1 ? 's' : ''} offline`);
    } else {
      setSaving('saved', 'Smart Marketing brain is in sync');
    }
  }

  function handleSaveSection(section) {
    const payload = collectSectionData(section);
    handleSaveSectionWith(section, payload, 'Settings updated');
  }

  function handleSaveSectionWith(section, payload, successMessage) {
    setSectionBusy(section, true, 'Saving…');
    setSectionAlert(section, []);
    apiRequest('save-settings', { section, settings: payload })
      .then((data) => {
        if (!data.ok) {
          const messages = data.messages && data.messages.length ? data.messages : [data.error || 'Unable to save section'];
          if (data.data) {
            state.settingsSections[section] = data.data;
            populateSection(section, data.data);
          }
          setSectionAlert(section, messages, 'error');
          showToast(messages[0], 'error');
          return;
        }

        if (data.settings) state.settings = data.settings;
        if (data.sections) state.settingsSections = data.sections;
        if (data.audit) state.settingsAudit = data.audit;
        if (data.aiHealth) state.aiHealth = data.aiHealth;
        if (data.integrations) state.integrations = data.integrations;
        if (data.connectors) state.connectors = data.connectors;
        if (data.integrationsAudit) state.integrationsAudit = data.integrationsAudit;

        renderAiHealth();
        renderIntegrations();
        renderConnectors();
        renderSettingsSection(section);
        renderSettingsAudit();
        evaluateSettingsStatus();

        if (data.messages && data.messages.length) {
          setSectionAlert(section, data.messages, 'info');
        } else {
          setSectionAlert(section, []);
        }

        showToast(successMessage || 'Settings saved', 'success');
      })
      .catch((error) => {
        setSectionAlert(section, [error.message], 'error');
        showToast(error.message, 'error');
      })
      .finally(() => {
        setSectionBusy(section, false);
        updateBudgetUsage();
      });
  }

  function handleRevertSection(section) {
    setSectionBusy(section, true, 'Reverting…');
    setSectionAlert(section, []);
    apiRequest('revert-settings', { section })
      .then((data) => {
        if (!data.ok) throw new Error(data.error || 'Unable to revert');
        if (data.settings) state.settings = data.settings;
        if (data.sections) state.settingsSections = data.sections;
        if (data.audit) state.settingsAudit = data.audit;
        if (data.aiHealth) state.aiHealth = data.aiHealth;
        if (data.integrations) state.integrations = data.integrations;
        if (data.connectors) state.connectors = data.connectors;
        if (data.integrationsAudit) state.integrationsAudit = data.integrationsAudit;
        renderSettingsSection(section);
        renderSettingsAudit();
        renderAiHealth();
        renderIntegrations();
        renderConnectors();
        evaluateSettingsStatus();
        showToast('Reverted to last saved state', 'info');
      })
      .catch((error) => {
        setSectionAlert(section, [error.message], 'error');
        showToast(error.message, 'error');
      })
      .finally(() => {
        setSectionBusy(section, false);
      });
  }

  function handleTestSection(section) {
    const payload = collectSectionData(section);
    setSectionBusy(section, true, 'Testing…');
    setSectionAlert(section, []);
    apiRequest('test-settings', { section, settings: payload })
      .then((data) => {
        const messages = data.messages && data.messages.length ? data.messages : [data.ok ? 'Validation successful' : 'Validation failed'];
        const variant = data.ok ? 'info' : 'error';
        setSectionAlert(section, messages, variant);
        showToast(messages[0], data.ok ? 'success' : 'error');
        if (data.sections) {
          state.settingsSections = data.sections;
          renderSettingsSection(section);
        }
        if (data.settings) state.settings = data.settings;
        if (data.aiHealth) state.aiHealth = data.aiHealth;
        if (data.integrations) state.integrations = data.integrations;
        if (data.connectors) state.connectors = data.connectors;
        if (data.integrationsAudit) state.integrationsAudit = data.integrationsAudit;
        renderAiHealth();
        renderIntegrations();
        renderConnectors();
        evaluateSettingsStatus();
      })
      .catch((error) => {
        setSectionAlert(section, [error.message], 'error');
        showToast(error.message, 'error');
      })
      .finally(() => {
        setSectionBusy(section, false);
      });
  }

  function handleSyncBusiness() {
    setSectionBusy('business', true, 'Syncing…');
    setSectionAlert('business', []);
    apiRequest('sync-business-profile')
      .then((data) => {
        if (!data.ok) throw new Error(data.messages?.[0] || data.error || 'Unable to sync profile');
        if (data.settings) state.settings = data.settings;
        if (data.sections) state.settingsSections = data.sections;
        if (data.audit) state.settingsAudit = data.audit;
        if (data.aiHealth) state.aiHealth = data.aiHealth;
        if (data.integrations) state.integrations = data.integrations;
        if (data.connectors) state.connectors = data.connectors;
        if (data.integrationsAudit) state.integrationsAudit = data.integrationsAudit;
        renderSettingsSection('business');
        renderSettingsAudit();
        renderAiHealth();
        renderIntegrations();
        renderConnectors();
        evaluateSettingsStatus();
        const messages = data.messages && data.messages.length ? data.messages : ['Business profile synced'];
        setSectionAlert('business', messages, 'info');
        showToast(messages[0], 'success');
      })
      .catch((error) => {
        setSectionAlert('business', [error.message], 'error');
        showToast(error.message, 'error');
      })
      .finally(() => {
        setSectionBusy('business', false);
      });
  }

  function handleRevalidateAll() {
    handleTestSection('integrations');
  }

  function connectorLabel(key) {
    return state.connectorCatalog?.[key]?.label || key;
  }

  function handleReloadSettings() {
    setSaving('pending', 'Reloading settings…');
    apiRequest('reload-settings')
      .then((data) => {
        if (!data.ok) throw new Error(data.error || 'Unable to reload');
        if (data.settings) state.settings = data.settings;
        if (data.sections) state.settingsSections = data.sections;
        if (data.audit) state.settingsAudit = data.audit;
        if (data.aiHealth) state.aiHealth = data.aiHealth;
        if (data.integrations) state.integrations = data.integrations;
        if (data.connectors) state.connectors = data.connectors;
        if (data.integrationsAudit) state.integrationsAudit = data.integrationsAudit;
        renderSettingsSections();
        renderSettingsAudit();
        renderAiHealth();
        renderIntegrations();
        renderConnectors();
        evaluateSettingsStatus();
        showToast('Settings reloaded', 'info');
      })
      .catch((error) => {
        showToast(error.message, 'error');
      })
      .finally(() => {
        evaluateSettingsStatus();
      });
  }

  function initSmartSettings() {
    if (!elements.settingsRoot) return;
    const sectionsData = state.settingsSections || {};
    const sectionNodes = elements.settingsRoot.querySelectorAll('[data-settings-section]');

    sectionNodes.forEach((sectionEl, index) => {
      const key = sectionEl.dataset.settingsSection;
      const view = {
        key,
        section: sectionEl,
        toggle: sectionEl.querySelector('[data-settings-toggle]'),
        body: sectionEl.querySelector('[data-settings-body]'),
        form: sectionEl.querySelector('[data-settings-form]'),
        summary: sectionEl.querySelector('[data-settings-summary]'),
        updated: sectionEl.querySelector('[data-settings-updated]'),
        alert: sectionEl.querySelector('[data-settings-alert]'),
        history: sectionEl.querySelector('[data-settings-history]'),
        saveBtn: sectionEl.querySelector('[data-settings-save]'),
        revertBtn: sectionEl.querySelector('[data-settings-revert]'),
        testBtn: sectionEl.querySelector('[data-settings-test]'),
        syncBtn: sectionEl.querySelector('[data-settings-sync]'),
        revalidateBtn: sectionEl.querySelector('[data-settings-revalidate-all]'),
        budgetSpend: sectionEl.querySelector('[data-settings-budget-spend]'),
        budgetRemaining: sectionEl.querySelector('[data-settings-budget-remaining]'),
        flagged: sectionEl.querySelector('[data-compliance-flagged]'),
        flaggedList: sectionEl.querySelector('[data-compliance-flagged-list]'),
        gemini: sectionEl.querySelector('[data-settings-gemini]'),
      };

      settingsViews[key] = view;

      if (view.saveBtn) view.saveBtn.addEventListener('click', () => handleSaveSection(key));
      if (view.revertBtn) view.revertBtn.addEventListener('click', () => handleRevertSection(key));
      if (view.testBtn) view.testBtn.addEventListener('click', () => handleTestSection(key));
      if (view.syncBtn) view.syncBtn.addEventListener('click', handleSyncBusiness);
      if (view.revalidateBtn) view.revalidateBtn.addEventListener('click', handleRevalidateAll);

      if (view.toggle && view.body) {
        const expanded = index === 0;
        view.toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        view.body.hidden = !expanded;
      }

      populateSection(key, sectionsData[key] || {});
    });

    elements.settingsRoot.addEventListener('click', (event) => {
      const toggle = event.target.closest('[data-settings-toggle]');
      if (!toggle) return;
      const section = toggle.dataset.settingsToggle;
      const view = settingsViews[section];
      if (!view || !view.body) return;
      const expanded = toggle.getAttribute('aria-expanded') === 'true';
      toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
      view.body.hidden = expanded;
    });

    elements.settingsRoot.querySelectorAll('[data-settings-reload]').forEach((button) => {
      button.addEventListener('click', handleReloadSettings);
    });

    renderSettingsSections();
    renderSettingsAudit();
    evaluateSettingsStatus();
  }

  function expandSettingsSection(section) {
    const view = settingsViews[section];
    if (!view) return;
    if (view.toggle) {
      view.toggle.setAttribute('aria-expanded', 'true');
    }
    if (view.body) {
      view.body.hidden = false;
      view.body.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  function setSaving(status, message) {
    if (!elements.settingsStatus) return;
    elements.settingsStatus.textContent = message || '';
    elements.settingsStatus.dataset.state = status;
  }

  let saveTimer;
  function queueSave(update) {
    state.settings = merge(state.settings, update);
    setSaving('pending', 'Saving…');
    if (saveTimer) clearTimeout(saveTimer);
    saveTimer = setTimeout(() => saveSettings(update), 400);
  }

  function saveSettings(update) {
    fetch('admin-smart-marketing.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ action: 'save-settings', csrfToken, settings: update }),
    })
      .then((response) => response.json())
      .then((data) => {
        if (!data.ok) throw new Error(data.error || 'Unable to save settings');
        state.settings = data.settings;
        state.integrations = data.integrations;
        state.aiHealth = data.aiHealth;
        state.audit = data.audit;
        if (data.integrationsAudit) {
          state.integrationsAudit = data.integrationsAudit;
        }
        if (data.connectors) {
          state.connectors = data.connectors;
        }
        state.defaults = state.defaults || {};
        state.defaults.regions = data.settings?.businessProfile?.serviceRegions || state.defaults.regions;
        state.defaults.compliance = {
          platform_policy: data.settings?.compliance?.policyChecks,
          brand_tone: data.settings?.compliance?.brandTone,
          legal_disclaimers: data.settings?.compliance?.legalDisclaimers,
        };
        renderAiHealth();
        renderIntegrations();
        renderConnectors();
        renderAutonomyMode();
        renderAudit();
        renderSettingsHistory('integrations');
        setSaving('saved', 'Saved');
      })
      .catch((error) => {
        console.error(error);
        setSaving('error', error.message);
      });
  }

  function merge(target, update) {
    if (!update || typeof update !== 'object') return target;
    const clone = Array.isArray(target) ? target.slice() : { ...(target || {}) };
    Object.keys(update).forEach((key) => {
      const value = update[key];
      if (value && typeof value === 'object' && !Array.isArray(value)) {
        clone[key] = merge(clone[key] || {}, value);
      } else {
        clone[key] = value;
      }
    });
    return clone;
  }

  function updateRunStatus(runId, status) {
    fetch('admin-smart-marketing.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ action: 'update-run-status', csrfToken, runId, status }),
    })
      .then((response) => response.json())
      .then((data) => {
        if (!data.ok) throw new Error(data.error || 'Unable to update status');
        state.brainRuns = data.runs;
        state.audit = data.audit;
        renderRuns();
        renderAudit();
      })
      .catch((error) => {
        alert(error.message);
      });
  }

  function submitBrainForm(event) {
    event.preventDefault();
    elements.brainError.hidden = true;
    const payload = collectBrainPayload();
    const submitButton = elements.brainForm.querySelector('button[type="submit"]');
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Generating…';

    fetch('admin-smart-marketing.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload),
    })
      .then((response) => response.json())
      .then((data) => {
        if (!data.ok) throw new Error(data.error || 'Generation failed');
        state.brainRuns = data.runs;
        state.audit = data.audit;
        if (data.campaigns) state.campaigns = data.campaigns;
        if (data.automationLog) state.automationLog = data.automationLog;
        renderRuns();
        renderAudit();
        renderCampaigns();
        renderAutomationLog();
      })
      .catch((error) => {
        elements.brainError.textContent = error.message;
        elements.brainError.hidden = false;
      })
      .finally(() => {
        submitButton.disabled = false;
        submitButton.innerHTML = '<i class="fa-solid fa-robot" aria-hidden="true"></i> Generate autonomous plan';
      });
  }

  function applyDefaultSelections() {
    renderChips(elements.goalsGroup, state.defaults?.goals || [], state.defaults?.goals || []);
    renderChips(elements.productsGroup, state.defaults?.products || [], state.defaults?.products || []);
    renderChips(elements.languagesGroup, state.defaults?.languages || [], state.defaults?.languages || []);
    elements.regionsInput.value = (state.defaults?.regions || []).join(', ');
    elements.dailyBudget.value = state.settings?.budget?.dailyCap || 0;
    elements.monthlyBudget.value = state.settings?.budget?.monthlyCap || 0;
    elements.minBid.value = state.settings?.budget?.minBid || 0;
    elements.cpaGuardrail.value = state.settings?.budget?.targetCpl || 0;
    elements.autonomySelect.value = state.settings?.autonomy?.mode || 'draft';
    elements.notesInput.value = '';
    elements.complianceChecks.forEach((input) => {
      const key = input.dataset.compliance;
      input.checked = Boolean(state.defaults?.compliance?.[key]);
    });
  }

  function initTabs() {
    if (!elements.tabs || !elements.panels) return;
    const buttons = Array.from(elements.tabs.querySelectorAll('[data-tab]'));
    const panels = Array.from(elements.panels.querySelectorAll('[data-tab-panel]'));

    function activate(tab) {
      buttons.forEach((button) => {
        const isActive = button.dataset.tab === tab;
        button.classList.toggle('is-active', isActive);
      });
      panels.forEach((panel) => {
        panel.hidden = panel.dataset.tabPanel !== tab;
      });
    }

    buttons.forEach((button) => {
      button.addEventListener('click', () => activate(button.dataset.tab));
    });

    activate('business');
  }

  function pathToUpdate(path, value) {
    const keys = path.split('.');
    const update = {};
    let ref = update;
    keys.forEach((key, index) => {
      if (index === keys.length - 1) {
        ref[key] = value;
      } else {
        ref[key] = {};
        ref = ref[key];
      }
    });
    return update;
  }

  function initSettingsBindings() {
    if (!elements.panels) return;
    const fields = elements.panels.querySelectorAll('[data-setting]');
    fields.forEach((field) => {
      const path = field.dataset.setting;
      const value = getValueFromPath(state.settings, path);
      if (field.type === 'checkbox') {
        field.checked = Boolean(value);
      } else if (Array.isArray(value)) {
        field.value = value.join(field.tagName === 'TEXTAREA' ? '\n' : ', ');
      } else if (value !== undefined && value !== null) {
        field.value = value;
      }

      field.addEventListener('change', (event) => {
        const target = event.target;
        let newValue;
        if (target.type === 'checkbox') {
          newValue = target.checked;
        } else if (target.tagName === 'TEXTAREA' && target.dataset.setting?.includes('portfolio')) {
          newValue = target.value.split('\n').map((line) => line.trim()).filter(Boolean);
        } else if (target.tagName === 'TEXTAREA' && target.dataset.setting?.includes('primarySegments')) {
          newValue = target.value.split('\n').map((line) => line.trim()).filter(Boolean);
        } else if (target.dataset.setting?.includes('serviceRegions')) {
          newValue = target.value;
        } else {
          newValue = target.value;
        }
        queueSave(pathToUpdate(path, newValue));
      });
    });
  }

  function initIntegrationBanner() {
    if (!elements.integrationBannerAction) return;
    elements.integrationBannerAction.addEventListener('click', () => {
      expandSettingsSection('integrations');
    });
  }

  function getValueFromPath(obj, path) {
    return path.split('.').reduce((acc, key) => (acc && acc[key] !== undefined ? acc[key] : undefined), obj);
  }

  function initKillSwitch() {
    if (!elements.killSwitchButton) return;
    elements.killSwitchButton.addEventListener('click', () => {
      if (!confirm('Trigger emergency kill switch? All live plans will be halted.')) {
        return;
      }
      elements.killSwitchButton.disabled = true;
      elements.killSwitchButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Halting…';
      fetch('admin-smart-marketing.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ action: 'kill-switch', csrfToken }),
      })
        .then((response) => response.json())
        .then((data) => {
          if (!data.ok) throw new Error(data.error || 'Kill switch failed');
          state.brainRuns = data.runs;
          state.settings = data.settings;
          state.audit = data.audit;
          if (data.campaigns) state.campaigns = data.campaigns;
          if (data.automationLog) state.automationLog = data.automationLog;
          renderRuns();
          renderAutonomyMode();
          renderAudit();
          renderSettingsKillSwitch();
          renderCampaigns();
          renderAutomationLog();
        })
        .catch((error) => {
          alert(error.message);
        })
        .finally(() => {
          elements.killSwitchButton.disabled = false;
          elements.killSwitchButton.innerHTML = '<i class="fa-solid fa-stop-circle" aria-hidden="true"></i> Trigger kill switch';
        });
    });
  }

  function renderSettingsKillSwitch() {
    const checkbox = elements.panels.querySelector('[data-setting="autonomy.killSwitchEngaged"]');
    if (checkbox) {
      checkbox.checked = Boolean(state.settings?.autonomy?.killSwitchEngaged);
    }
  }

  function streamTextOutput(container, english, hindi) {
    container.innerHTML = '';
    const englishBlock = document.createElement('div');
    const hindiBlock = document.createElement('div');
    container.appendChild(englishBlock);
    container.appendChild(hindiBlock);

    function appendLines(block, lines) {
      let index = 0;
      const interval = setInterval(() => {
        if (index >= lines.length) {
          clearInterval(interval);
          return;
        }
        const p = document.createElement('p');
        p.textContent = lines[index];
        block.appendChild(p);
        index += 1;
      }, 120);
    }

    englishBlock.innerHTML = '<h4>English</h4>';
    hindiBlock.innerHTML = '<h4>Hindi</h4>';
    appendLines(englishBlock, english || []);
    appendLines(hindiBlock, hindi || []);
  }

  function initCreativeActions() {
    const textButton = document.querySelector('[data-generate-text]');
    if (textButton) {
      textButton.addEventListener('click', () => {
        const category = elements.creativeTextCategory.value;
        const brief = elements.creativeTextBrief.value.trim();
        if (!brief) {
          alert('Add a brief for the copy.');
          return;
        }
        elements.creativeTextStream.innerHTML = '<span class="status status--neutral">Generating…</span>';
        fetch('admin-smart-marketing.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ action: 'creative-text', csrfToken, category, brief }),
        })
          .then((response) => response.json())
          .then((data) => {
            if (!data.ok) throw new Error(data.error || 'Unable to generate text');
            const output = data.asset.payload.output || {};
            streamTextOutput(elements.creativeTextStream, output.english, output.hindi);
            state.assets = data.assets;
            state.audit = data.audit;
            renderAssets();
            renderAudit();
          })
          .catch((error) => {
            elements.creativeTextStream.innerHTML = `<span class="status status--error">${error.message}</span>`;
          });
      });
    }

    const imageButton = document.querySelector('[data-generate-image]');
    if (imageButton) {
      imageButton.addEventListener('click', () => {
        const prompt = elements.creativeImagePrompt.value.trim();
        if (!prompt) {
          alert('Describe the image you need.');
          return;
        }
        elements.creativeImageStream.innerHTML = '<span class="status status--neutral">Generating…</span>';
        fetch('admin-smart-marketing.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ action: 'creative-image', csrfToken, prompt, preset: elements.creativeImagePreset.value }),
        })
          .then((response) => response.json())
          .then((data) => {
            if (!data.ok) throw new Error(data.error || 'Unable to generate image');
            const path = data.asset.payload.path;
            elements.creativeImageStream.innerHTML = `<img src="${path}" alt="Generated" />`;
            state.assets = data.assets;
            state.audit = data.audit;
            renderAssets();
            renderAudit();
          })
          .catch((error) => {
            elements.creativeImageStream.innerHTML = `<span class="status status--error">${error.message}</span>`;
          });
      });
    }

    const ttsButton = document.querySelector('[data-generate-tts]');
    if (ttsButton) {
      ttsButton.addEventListener('click', () => {
        const script = elements.creativeTtsScript.value.trim();
        if (!script) {
          alert('Provide a script.');
          return;
        }
        elements.creativeTtsStream.innerHTML = '<span class="status status--neutral">Generating…</span>';
        fetch('admin-smart-marketing.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ action: 'creative-tts', csrfToken, script }),
        })
          .then((response) => response.json())
          .then((data) => {
            if (!data.ok) throw new Error(data.error || 'Unable to generate audio');
            const path = data.asset.payload.path;
            elements.creativeTtsStream.innerHTML = `<audio controls src="${path}"></audio>`;
            state.assets = data.assets;
            state.audit = data.audit;
            renderAssets();
            renderAudit();
          })
          .catch((error) => {
            elements.creativeTtsStream.innerHTML = `<span class="status status--error">${error.message}</span>`;
          });
      });
    }
  }

  function initBrainForm() {
    if (!elements.brainForm) return;
    elements.brainForm.addEventListener('submit', submitBrainForm);
  }

  function init() {
    renderAiHealth();
    renderIntegrations();
    renderConnectors();
    renderAnalytics();
    renderAudit();
    renderAutonomyMode();
    renderRuns();
    renderAssets();
    renderCampaigns();
    renderAutomationLog();
    renderOptimization();
    renderGovernance();
    renderNotifications();
    initSmartSettings();
    initIntegrationActions();
    initIntegrationBanner();
    applyDefaultSelections();
    initTabs();
    initSettingsBindings();
    initKillSwitch();
    initCreativeActions();
    initBrainForm();
    renderSettingsKillSwitch();
    initConnectorActions();
    initCampaignBuilder();
    initAutomations();
    initAnalyticsSection();
    initOptimizationSection();
    initGovernanceSection();
    initNotificationsSection();
    evaluateSettingsStatus();
  }

  document.addEventListener('DOMContentLoaded', init);
})();
