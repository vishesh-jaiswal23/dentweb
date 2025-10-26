(function () {
  const form = document.getElementById('login-form');
  if (!form) return;

  const roleCredentials = {
    admin: {
      email: 'd.entranchi@gmail.com',
      password: 'Dent@2025',
    },
    customer: {
      email: 'customer.portal@dakshayanienterprises.in',
      password: 'Customer@2025',
    },
    employee: {
      email: 'employee.portal@dakshayanienterprises.in',
      password: 'Employee@2025',
    },
    installer: {
      email: 'installer.portal@dakshayanienterprises.in',
      password: 'Installer@2025',
    },
    referrer: {
      email: 'referrer.portal@dakshayanienterprises.in',
      password: 'Referrer@2025',
    },
  };

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

    const expectedCredentials = roleCredentials[selectedRole];

    if (!expectedCredentials) {
      return;
    }

    const normalizedEmail = email.toLowerCase();
    const matchedRoleEntry = Object.entries(roleCredentials).find(([, creds]) => {
      return creds.email.toLowerCase() === normalizedEmail;
    });

    if (matchedRoleEntry && matchedRoleEntry[0] !== selectedRole) {
      if (feedbackEl) {
        feedbackEl.textContent =
          'These credentials belong to a different portal. Please switch to the matching portal type to continue.';
        feedbackEl.classList.add('is-error');
        feedbackEl.classList.remove('is-success');
      }
      return;
    }

    if (normalizedEmail !== expectedCredentials.email.toLowerCase() || password !== expectedCredentials.password) {
      if (feedbackEl) {
        feedbackEl.textContent = 'The email ID or password does not match this portal. Please try again.';
        feedbackEl.classList.add('is-error');
        feedbackEl.classList.remove('is-success');
      }
      passwordInput.focus();
      return;
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
