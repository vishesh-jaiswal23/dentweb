(() => {
  const shell = document.querySelector('.admin-shell');
  if (!shell) return;

  const navToggle = document.querySelector('.admin-nav-toggle');
  if (navToggle) {
    navToggle.addEventListener('click', () => {
      const open = shell.getAttribute('data-nav-open') === 'true';
      shell.setAttribute('data-nav-open', open ? 'false' : 'true');
    });
  }

  const searchForm = document.querySelector('.admin-search');
  const openSearchBtn = document.querySelector('[data-action="open-search"]');
  if (openSearchBtn && searchForm) {
    const input = searchForm.querySelector('input[type="search"]');
    openSearchBtn.addEventListener('click', () => {
      input?.focus();
    });
  }

  const notificationContainer = document.querySelector('[data-component="notification-center"]');
  if (notificationContainer) {
    const toggleButton = notificationContainer.querySelector('[data-action="toggle-notifications"]');
    const panel = notificationContainer.querySelector('.notification-panel');
    toggleButton?.addEventListener('click', () => {
      const open = notificationContainer.getAttribute('data-open') === 'true';
      notificationContainer.setAttribute('data-open', open ? 'false' : 'true');
      if (!open) {
        panel?.removeAttribute('hidden');
      }
    });

    const clearButton = notificationContainer.querySelector('[data-action="clear-notifications"]');
    const list = notificationContainer.querySelector('.notification-panel__list');
    clearButton?.addEventListener('click', () => {
      list.innerHTML = '<li class="notification-panel__empty">Notifications cleared.</li>';
      notificationContainer.removeAttribute('data-open');
    });
  }

  const profile = document.querySelector('.admin-header__profile');
  if (profile) {
    const button = profile.querySelector('[data-action="toggle-profile"]');
    button?.addEventListener('click', () => {
      const open = profile.getAttribute('data-open') === 'true';
      profile.setAttribute('data-open', open ? 'false' : 'true');
      button.setAttribute('aria-expanded', (!open).toString());
    });
    document.addEventListener('click', (event) => {
      if (!profile.contains(event.target)) {
        profile.setAttribute('data-open', 'false');
        button?.setAttribute('aria-expanded', 'false');
      }
    });
  }

  const modal = document.querySelector('[data-component="confirmation-modal"]');
  const modalMessage = modal?.querySelector('.admin-modal__body');
  const confirmButton = modal?.querySelector('[data-action="modal-confirm"]');
  const cancelButton = modal?.querySelector('[data-action="modal-cancel"]');
  let pendingSuccessMessage = '';

  const closeModal = () => {
    modal?.setAttribute('hidden', 'hidden');
    pendingSuccessMessage = '';
  };

  cancelButton?.addEventListener('click', closeModal);
  modal?.addEventListener('click', (event) => {
    if (event.target === modal) {
      closeModal();
    }
  });

  const toastContainer = document.querySelector('.admin-toast-container');
  const createToast = (toast) => {
    if (!toastContainer) return;
    const item = document.createElement('div');
    item.className = 'admin-toast';
    item.dataset.type = toast.type || 'info';
    item.innerHTML = `
      <span><i class="fa-solid fa-circle-info"></i></span>
      <div class="admin-toast__body">${toast.message}</div>
      <button class="admin-toast__close" aria-label="Dismiss">&times;</button>
    `;
    item.querySelector('.admin-toast__close')?.addEventListener('click', () => {
      item.remove();
    });
    toastContainer.appendChild(item);
    setTimeout(() => item.remove(), toast.duration || 5000);
  };

  confirmButton?.addEventListener('click', () => {
    if (pendingSuccessMessage) {
      createToast({ type: 'success', message: pendingSuccessMessage });
    }
    closeModal();
  });

  document.querySelectorAll('[data-action="confirm"]').forEach((button) => {
    button.addEventListener('click', () => {
      const message = button.getAttribute('data-confirm-message') || 'Are you sure?';
      pendingSuccessMessage = button.getAttribute('data-success-message') || '';
      if (modal && modalMessage) {
        modalMessage.textContent = message;
        modal.removeAttribute('hidden');
      }
    });
  });

  const bootToasts = Array.isArray(window.__ADMIN_TOASTS) ? window.__ADMIN_TOASTS : [];
  bootToasts.forEach((toast) => {
    if (toast?.message) {
      createToast({ type: toast.type || 'info', message: toast.message });
    }
  });

  document.addEventListener('keydown', (event) => {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
      event.preventDefault();
      searchForm?.querySelector('input[type="search"]')?.focus();
    }
  });
})();
