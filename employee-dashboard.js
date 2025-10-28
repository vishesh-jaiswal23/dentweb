(function () {
  'use strict';

  const config = window.DakshayaniEmployee || {};
  const API_BASE = config.apiBase || 'api/employee.php';
  const CSRF_TOKEN = config.csrfToken || '';

  function api(action, payload) {
    if (!API_BASE) {
      return Promise.reject(new Error('API not available'));
    }
    const options = {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-Token': CSRF_TOKEN,
      },
      credentials: 'same-origin',
      body: JSON.stringify(payload || {}),
    };
    return fetch(`${API_BASE}?action=${encodeURIComponent(action)}`, options)
      .then((response) => response.json())
      .then((data) => {
        if (!data || data.success === false) {
          throw new Error(data && data.error ? data.error : 'Request failed');
        }
        return data.data;
      });
  }

  // Theme toggle -------------------------------------------------------------
  const body = document.body;
  const themeInputs = document.querySelectorAll('[data-theme-option]');
  const THEME_KEY = 'dakshayani-employee-theme';

  function applyTheme(value) {
    body.dataset.dashboardTheme = value || 'light';
  }

  const storedTheme = window.localStorage ? window.localStorage.getItem(THEME_KEY) : null;
  if (storedTheme) {
    applyTheme(storedTheme);
    themeInputs.forEach((input) => {
      input.checked = input.value === storedTheme;
    });
  }

  themeInputs.forEach((input) => {
    input.addEventListener('change', () => {
      if (!input.checked) return;
      applyTheme(input.value);
      if (window.localStorage) {
        window.localStorage.setItem(THEME_KEY, input.value);
      }
    });
  });

  // Complaint workflow -------------------------------------------------------
  const STATUS_LABELS = {
    in_progress: { label: 'In Progress', tone: 'progress' },
    awaiting_response: { label: 'Resolved (Pending Admin)', tone: 'waiting' },
    resolved: { label: 'Resolved (Pending Admin)', tone: 'waiting' },
    escalated: { label: 'Escalated to Admin', tone: 'escalated' },
  };

  function updateTicketCard(card, statusKey) {
    const status = STATUS_LABELS[statusKey] || STATUS_LABELS.in_progress;
    card.dataset.status = statusKey;
    const statusLabel = card.querySelector('[data-ticket-status-label]');
    if (statusLabel) {
      statusLabel.textContent = status.label;
      statusLabel.className = `dashboard-status dashboard-status--${status.tone}`;
    }
    const select = card.querySelector('[data-ticket-status]');
    if (select) {
      select.value = statusKey;
    }
  }

  function handleStatusChange(card, statusKey) {
    const reference = card.dataset.ticketId;
    if (!reference) {
      return;
    }
    updateTicketCard(card, statusKey);
    api('update-complaint-status', { reference, status: statusKey }).catch((error) => {
      console.error(error);
      window.alert('Unable to update complaint status. Please try again.');
    });
  }

  document.querySelectorAll('[data-ticket-id]').forEach((card) => {
    const statusSelect = card.querySelector('[data-ticket-status]');
    if (statusSelect) {
      statusSelect.addEventListener('change', () => {
        handleStatusChange(card, statusSelect.value);
      });
    }

    const noteButton = card.querySelector('[data-ticket-note]');
    if (noteButton) {
      noteButton.addEventListener('click', () => {
        const note = window.prompt('Add an internal note for this ticket:');
        if (!note) return;
        api('add-complaint-note', {
          reference: card.dataset.ticketId,
          note,
        }).catch((error) => {
          console.error(error);
          window.alert('Unable to add note. Please try again.');
        });
      });
    }

    const escalateButton = card.querySelector('[data-ticket-escalate]');
    if (escalateButton) {
      escalateButton.addEventListener('click', () => {
        handleStatusChange(card, 'escalated');
        window.alert('Ticket escalated to Admin for review.');
      });
    }
  });
})();
