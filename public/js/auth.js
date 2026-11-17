(() => {
  const form = document.getElementById('authForm');
  if (!form) return;

  const driverFields = document.querySelectorAll('[data-driver-upload]');
  if (driverFields.length) {
    const accountType = document.getElementById('accountType');
    const updateDriverUploads = () => {
      const isDriver = accountType.value === 'driver';
      for (const field of driverFields) {
        const input = field.querySelector('input');
        field.hidden = !isDriver;
        input.disabled = !isDriver;
        input.required = isDriver;
      }
    };
    accountType.addEventListener('change', updateDriverUploads);
    window.addEventListener('pageshow', updateDriverUploads);
    updateDriverUploads();
  }

  document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      const input = document.getElementById(button.dataset.passwordToggle);
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
  });

  form.addEventListener('submit', (event) => {
    const message = document.getElementById('formMessage');
    message.className = 'form-message';
    message.textContent = '';

    if (!form.reportValidity()) {
      event.preventDefault();
      return;
    }
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    if (!/@[^\s@]+\.[^\s@]+$/.test(email)) {
      event.preventDefault();
      message.textContent = 'Enter a valid university email address.';
      return;
    }
    if (form.dataset.mode === 'signup') {
      const confirm = document.getElementById('confirmPassword').value;
      if (password.length < 8) {
        event.preventDefault();
        message.textContent = 'Choose a password with at least 8 characters.';
        return;
      }
      if (password !== confirm) {
        event.preventDefault();
        message.textContent = 'Your passwords do not match yet.';
        document.getElementById('confirmPassword').focus();
        return;
      }
    }
  });
})();
