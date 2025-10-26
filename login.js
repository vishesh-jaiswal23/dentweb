(function () {
  const form = document.getElementById('login-form');
  if (!form) return;

  const ADMIN_EMAIL = 'd.entranchi@gmail.com';
  const ADMIN_PASSWORD = 'Dent@2025';

  const roleToRoute = {
    admin: 'admin-dashboard.html',
    customer: 'customer-dashboard.html',
    employee: 'employee-dashboard.html',
    installer: 'installer-dashboard.html',
    referrer: 'referrer-dashboard.html',
  };

  const feedbackEl = form.querySelector('[data-login-feedback]');
  const hintEl = form.querySelector('[data-role-hint]');
  const emailInput = form.querySelector('#login-email');
  const passwordInput = form.querySelector('#login-password');
  const roleInputs = form.querySelectorAll('input[name="role"]');

  function setHint(role) {
    if (!hintEl) return;
    if (role === 'admin') {
      hintEl.textContent = 'Enter your assigned admin email ID and password to continue.';
    } else {
      hintEl.textContent = 'Use your registered email ID and password to continue.';
    }
  }

  roleInputs.forEach((input) => {
    input.addEventListener('change', () => {
      setHint(input.value);
      if (feedbackEl) {
        feedbackEl.textContent = '';
        feedbackEl.classList.remove('is-error', 'is-success');
      }
    });
  });

  setHint(form.querySelector('input[name="role"]:checked')?.value || 'admin');

  form.addEventListener('submit', (event) => {
    event.preventDefault();

    const selectedRole = form.querySelector('input[name="role"]:checked')?.value;
    const email = emailInput.value.trim();
    const password = passwordInput.value;

    if (!selectedRole || !(selectedRole in roleToRoute)) {
      return;
    }

    if (!email || !password) {
      if (feedbackEl) {
        feedbackEl.textContent = 'Please enter both your email ID and password to continue.';
        feedbackEl.classList.add('is-error');
        feedbackEl.classList.remove('is-success');
      }
      return;
    }

    if (selectedRole === 'admin') {
      if (email.toLowerCase() !== ADMIN_EMAIL) {
        if (feedbackEl) {
          feedbackEl.textContent = 'The admin email ID does not match our records.';
          feedbackEl.classList.add('is-error');
          feedbackEl.classList.remove('is-success');
        }
        emailInput.focus();
        return;
      }

      if (password !== ADMIN_PASSWORD) {
        if (feedbackEl) {
          feedbackEl.textContent = 'The admin password is incorrect.';
          feedbackEl.classList.add('is-error');
          feedbackEl.classList.remove('is-success');
        }
        passwordInput.focus();
        return;
      }
    }

    if (feedbackEl) {
      feedbackEl.textContent = 'Logging you in…';
      feedbackEl.classList.remove('is-error');
      feedbackEl.classList.add('is-success');
    }

    setTimeout(() => {
      window.location.href = roleToRoute[selectedRole];
    }, 300);
  });
})();
