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

  function formatTime(date) {
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }

  function formatDateTime(date) {
    return `${date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short' })} · ${formatTime(date)}`;
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
