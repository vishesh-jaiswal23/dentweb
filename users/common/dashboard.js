(function () {
  const dashboards = document.querySelectorAll('[data-dashboard]');
  if (!dashboards.length) {
    return;
  }

  const toastContainer = (() => {
    const el = document.createElement('div');
    el.className = 'dashboard-toast-container';
    document.body.appendChild(el);
    return el;
  })();

  function showToast(message, type = 'success') {
    if (!message) return;
    const toast = document.createElement('div');
    toast.className = `dashboard-toast dashboard-toast--${type}`;
    toast.innerHTML = `<span>${message}</span>`;
    toastContainer.appendChild(toast);
    requestAnimationFrame(() => {
      toast.dataset.state = 'visible';
    });
    setTimeout(() => {
      toast.dataset.state = 'hidden';
      setTimeout(() => {
        toast.remove();
      }, 250);
    }, 4000);
  }

  function renderSearch(resultsEl, data, query) {
    resultsEl.innerHTML = '';
    if (!query || query.length < 2) {
      resultsEl.hidden = false;
      resultsEl.innerHTML = '<p class="dashboard-search-empty">Type at least two characters to search.</p>';
      return;
    }

    const normalized = query.trim().toLowerCase();
    let totalMatches = 0;

    Object.entries(data).forEach(([group, items]) => {
      const groupMatches = items.filter((item) => {
        const haystack = `${item.title} ${item.description || ''}`.toLowerCase();
        return haystack.includes(normalized);
      });
      if (!groupMatches.length) return;
      totalMatches += groupMatches.length;

      const section = document.createElement('section');
      section.className = 'dashboard-search-group';
      const heading = document.createElement('h3');
      heading.textContent = group.charAt(0).toUpperCase() + group.slice(1);
      section.appendChild(heading);

      const list = document.createElement('ul');
      groupMatches.forEach((match) => {
        const li = document.createElement('li');
        li.innerHTML = `
          <div class="title">${match.title}</div>
          <div class="desc">${match.description || ''}</div>
          <span class="badge badge-soft">${match.badge || ''}</span>
        `;
        list.appendChild(li);
      });
      section.appendChild(list);
      resultsEl.appendChild(section);
    });

    if (!totalMatches) {
      resultsEl.innerHTML = '<p class="dashboard-search-empty">No matches found. Try a different keyword or module.</p>';
    }
    resultsEl.hidden = false;
  }

  dashboards.forEach((dashboard) => {
    const searchDataRaw = dashboard.getAttribute('data-search') || '{}';
    let searchData = {};
    try {
      searchData = JSON.parse(searchDataRaw);
    } catch (error) {
      console.warn('Unable to parse dashboard search data', error);
    }

    const searchInput = dashboard.querySelector('[data-dashboard-search-input]');
    const searchResults = dashboard.querySelector('[data-dashboard-search-results]');
    if (searchInput && searchResults) {
      searchInput.addEventListener('input', (event) => {
        renderSearch(searchResults, searchData, event.target.value || '');
      });
      searchInput.addEventListener('focus', () => {
        if ((searchInput.value || '').length >= 2) {
          renderSearch(searchResults, searchData, searchInput.value || '');
        } else {
          searchResults.hidden = false;
        }
      });
      document.addEventListener('click', (event) => {
        if (!searchResults.contains(event.target) && event.target !== searchInput) {
          searchResults.hidden = true;
        }
      });
    }

    const navLinks = dashboard.querySelectorAll('[data-dashboard-nav-link]');
    const sections = dashboard.querySelectorAll('[data-dashboard-section]');
    function setActiveLink(targetId) {
      navLinks.forEach((link) => {
        if (link.getAttribute('href') === `#${targetId}`) {
          link.setAttribute('aria-current', 'page');
        } else {
          link.removeAttribute('aria-current');
        }
      });
    }

    navLinks.forEach((link) => {
      link.addEventListener('click', (event) => {
        const href = link.getAttribute('href') || '';
        if (!href.startsWith('#')) return;
        const id = href.slice(1);
        const section = dashboard.querySelector(`#${CSS.escape(id)}`);
        if (section) {
          event.preventDefault();
          section.scrollIntoView({ behavior: 'smooth', block: 'start' });
          setActiveLink(id);
        }
      });
    });

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            const id = entry.target.getAttribute('id');
            if (id) setActiveLink(id);
          }
        });
      },
      { rootMargin: '-40% 0px -55% 0px' }
    );

    sections.forEach((section) => observer.observe(section));

    dashboard.querySelectorAll('[data-profile-form]').forEach((form) => {
      form.addEventListener('submit', (event) => {
        event.preventDefault();
        const message = form.getAttribute('data-success-message') || 'Saved successfully';
        showToast(message, 'success');
      });
    });

    const twoFactorToggle = dashboard.querySelector('[data-two-factor-toggle]');
    if (twoFactorToggle) {
      twoFactorToggle.addEventListener('change', () => {
        if (twoFactorToggle.checked) {
          showToast('Two-factor authentication enabled. Save backup codes safely.', 'success');
        } else {
          const confirmed = window.confirm('Disable two-factor authentication? This reduces account security.');
          if (!confirmed) {
            twoFactorToggle.checked = true;
            return;
          }
          showToast('Two-factor authentication disabled. Consider enabling soon.', 'warning');
        }
      });
    }

    dashboard.querySelectorAll('[data-quick-action]').forEach((button) => {
      button.addEventListener('click', () => {
        const confirmMessage = button.getAttribute('data-action-confirm');
        if (confirmMessage) {
          const proceed = window.confirm(confirmMessage);
          if (!proceed) {
            showToast('Action cancelled.', 'info');
            return;
          }
        }
        const type = button.getAttribute('data-action-type') || 'success';
        const message = button.getAttribute('data-action-message') || 'Action completed.';
        showToast(message, type);
      });
    });
  });
})();
