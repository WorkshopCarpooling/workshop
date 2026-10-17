(() => {
  const form = document.getElementById('authForm');
  if (!form) return;

  document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      const input = document.getElementById(button.dataset.passwordToggle);
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
  });

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    const message = document.getElementById('formMessage');
    message.className = 'form-message';
    message.textContent = '';

    if (!form.reportValidity()) return;
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    if (!/@[^\s@]+\.[^\s@]+$/.test(email)) {
      message.textContent = 'Enter a valid university email address.';
      return;
    }
    if (form.dataset.mode === 'signup') {
      const confirm = document.getElementById('confirmPassword').value;
      if (password.length < 8) {
        message.textContent = 'Choose a password with at least 8 characters.';
        return;
      }
      if (password !== confirm) {
        message.textContent = 'Your passwords do not match yet.';
        document.getElementById('confirmPassword').focus();
        return;
      }
      message.textContent = 'Your details look good. Account creation will be connected to UTeM email verification.';
    } else {
      message.textContent = 'The sign-in form is ready to connect to your account service.';
    }
    message.classList.add('success');
  });

  document.getElementById('googleButton')?.addEventListener('click', () => {
    const message = document.getElementById('formMessage');
    message.className = 'form-message';
    message.textContent = 'Google sign-in can be enabled when the UTeM authentication service is connected.';
  });

  document.getElementById('forgotLink')?.addEventListener('click', (event) => {
    event.preventDefault();
    const message = document.getElementById('formMessage');
    message.className = 'form-message';
    message.textContent = 'Password recovery will be available when email verification is connected.';
  });
})();
