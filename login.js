(function () {
  const form = document.getElementById('login-form');
  if (!form) return;

  const roleInputs = form.querySelectorAll('input[name="role"]');
  const feedbackEl = form.querySelector('[data-login-feedback]');
  const hintEl = form.querySelector('[data-role-hint]');

  function setHint(role) {
    if (!hintEl) return;
    switch (role) {
      case 'admin':
        hintEl.textContent = 'Administrators must use the credentials issued by Dakshayani Enterprises.';
        break;
      case 'customer':
        hintEl.textContent = 'Customers can sign in with their registered email ID and password.';
        break;
      case 'employee':
        hintEl.textContent = 'Employees use their corporate email ID and secure password.';
        break;
      case 'installer':
        hintEl.textContent = 'Installers should enter the credentials assigned after activation.';
        break;
      case 'referrer':
        hintEl.textContent = 'Referrers can log in with their verified partner credentials.';
        break;
      default:
        hintEl.textContent = 'Use your assigned credentials to access the selected portal.';
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

  const checked = form.querySelector('input[name="role"]:checked');
  setHint(checked ? checked.value : 'admin');
})();
