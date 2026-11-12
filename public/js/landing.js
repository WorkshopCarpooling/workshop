(() => {
  const body = document.body;
  const link = document.getElementById('startJourney');
  const status = document.getElementById('journeyStatus');
  const exit = document.querySelector('.cinema-exit');
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  let starting = false;
  let destination = '';
  let fallbackTimer = 0;

  function finish() {
    if (!starting) return;
    starting = false;
    window.clearTimeout(fallbackTimer);
    window.location.assign(destination);
  }

  link.addEventListener('click', event => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target === '_blank') return;
    if (reducedMotion.matches) return;
    event.preventDefault();
    if (starting) return;
    starting = true;
    destination = link.href;
    link.setAttribute('aria-disabled', 'true');
    status.textContent = 'Starting your journey. Opening login.';
    body.classList.add('is-entering');
    // Animation end is the primary navigation trigger; the timeout covers missing
    // animation events and browsers that suspend effects in background tabs.
    fallbackTimer = window.setTimeout(finish, 2900);
  });

  exit.addEventListener('animationend', event => {
    if (event.animationName === 'cinema-exit') finish();
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && starting) {
      event.preventDefault();
      finish();
    }
  });
  reducedMotion.addEventListener('change', event => {
    if (event.matches && starting) finish();
  });
  window.addEventListener('pageshow', () => {
    starting = false;
    window.clearTimeout(fallbackTimer);
    body.classList.remove('is-entering');
    link.removeAttribute('aria-disabled');
    status.textContent = '';
  });
})();