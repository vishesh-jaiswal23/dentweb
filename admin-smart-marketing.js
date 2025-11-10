(function () {
  'use strict';

  const state = window.SmartMarketingState || {};
  const csrfToken = state.csrfToken || document.querySelector('meta[name="csrf-token"]').getAttribute('content');

  const elements = {
    aiHealth: document.querySelector('[data-ai-health]'),
    aiModels: document.querySelector('[data-ai-models]'),
    integrations: document.querySelector('[data-integrations-list]'),
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
      default:
        return 'status status--neutral';
    }
  }

  function renderIntegrations() {
    if (!elements.integrations) return;
    const integrations = state.integrations || {};
    elements.integrations.innerHTML = '';
    Object.keys(integrations).forEach((key) => {
      const entry = integrations[key];
      const li = document.createElement('li');
      const status = entry.status || 'unknown';
      li.innerHTML = `
        <span class="${statusBadgeClass(status)}">${status.toUpperCase()}</span>
        <strong>${entry.label || key}</strong>
        <small>${Object.entries(entry.details || {})
          .filter(([k]) => k !== 'status')
          .map(([k, v]) => `${k}: ${v}`)
          .join(' · ')}</small>`;
      elements.integrations.appendChild(li);
    });
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

      const original = button.innerHTML;
      button.disabled = true;
      button.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Working…';

      fetch('admin-smart-marketing.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload),
      })
        .then((response) => response.json())
        .then((data) => {
          if (!data.ok) throw new Error(data.error || 'Connector update failed');
          if (data.settings) state.settings = data.settings;
          if (data.integrations) state.integrations = data.integrations;
          if (data.connectors) state.connectors = data.connectors;
          if (data.audit) state.audit = data.audit;
          renderIntegrations();
          renderConnectors();
          renderAudit();
          renderSettingsKillSwitch();
        })
        .catch((error) => {
          alert(error.message);
        })
        .finally(() => {
          button.disabled = false;
          button.innerHTML = original;
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
          renderCampaigns();
          renderAutomationLog();
          renderAudit();
        })
        .catch((error) => {
          alert(error.message);
        })
        .finally(() => {
          elements.runAutomationsButton.disabled = false;
          elements.runAutomationsButton.innerHTML = original;
        });
    });
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
    renderAudit();
    renderAutonomyMode();
    renderRuns();
    renderAssets();
    renderCampaigns();
    renderAutomationLog();
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
    setSaving('saved', 'All changes saved');
  }

  document.addEventListener('DOMContentLoaded', init);
})();
