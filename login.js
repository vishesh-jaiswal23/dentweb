(function () {
  const form = document.getElementById('login-form');
  if (!form) return;

  const roleInput = form.querySelector('[data-role-input]');
  const roleButtons = form.querySelectorAll('[data-role-select]');
  const feedbackEl = form.querySelector('[data-login-feedback]');
  const hintEl = form.querySelector('[data-role-hint]');
  const identifierLabel = form.querySelector('[data-identifier-label]');
  const identifierInput = form.querySelector('[data-identifier-input]');

  function setHint(role) {
    if (!hintEl) return;
    switch (role) {
      case 'admin':
        hintEl.textContent = 'Administrators must use the credentials issued by Dakshayani Enterprises.';
        break;
      case 'employee':
        hintEl.textContent = 'Employees can sign in only after Admin approves their account.';
        break;
      case 'customer':
        hintEl.textContent = 'Customers must enter their registered mobile number and password to access updates.';
        break;
      default:
        hintEl.textContent = 'Use your assigned credentials to access the selected portal.';
    }
  }

  function configureIdentifier(role) {
    if (!identifierInput || !identifierLabel) return;

    if (role === 'customer') {
      identifierLabel.textContent = 'Mobile number';
      identifierInput.type = 'tel';
      identifierInput.placeholder = 'Enter registered mobile number';
      identifierInput.setAttribute('inputmode', 'tel');
      identifierInput.setAttribute('autocomplete', 'tel');
      identifierInput.setAttribute('pattern', '[0-9+()\-\s]{6,}');
    } else {
      identifierLabel.textContent = 'Email ID';
      identifierInput.type = 'email';
      identifierInput.placeholder = 'you@example.com';
      identifierInput.setAttribute('inputmode', 'email');
      identifierInput.setAttribute('autocomplete', 'email');
      identifierInput.removeAttribute('pattern');
    }
  }

  function selectRole(role) {
    if (!roleInput) return;
    roleInput.value = role;
    setHint(role);
    configureIdentifier(role);
    roleButtons.forEach((button) => {
      button.classList.toggle('is-active', button.dataset.roleSelect === role);
    });
    if (feedbackEl) {
      feedbackEl.textContent = '';
      feedbackEl.classList.remove('is-error', 'is-success');
    }
  }

  roleButtons.forEach((button) => {
    button.addEventListener('click', (event) => {
      event.preventDefault();
      selectRole(button.dataset.roleSelect || 'admin');
    });
  });

  const initialRole = roleInput && roleInput.value ? roleInput.value : roleButtons[0]?.dataset.roleSelect || 'admin';
  selectRole(initialRole);
})();
