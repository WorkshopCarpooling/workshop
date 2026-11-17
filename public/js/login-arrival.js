(() => {
  'use strict';
  const marker = document.querySelector('meta[name="rideutem-login-arrival"]');
  if (!marker) return;
  const preference = matchMedia('(prefers-reduced-motion: reduce)');
  let overlay;
  let timer = 0;
  let complete = false;
  const inactive = [];
  const finish = () => {
    if (complete) return;
    complete = true;
    clearTimeout(timer);
    overlay?.remove();
    marker.remove();
    document.getElementById('rideLoginArrival')?.remove();
    document.documentElement.classList.remove('login-arrival-playing');
    inactive.forEach(([element, wasInert]) => { element.inert = wasInert; });
    document.removeEventListener('keydown', skipWithKey);
    document.removeEventListener('visibilitychange', skipWhenHidden);
    document.dispatchEvent(new CustomEvent('rideutem:arrival-complete'));
  };
  const skipWithKey = event => { if (event.key === 'Escape') finish(); };
  const skipWhenHidden = () => { if (document.hidden) finish(); };
  if (preference.matches) { finish(); return; }
  const template = document.getElementById('rideLoginArrival');
  if (!template?.content.firstElementChild) { finish(); return; }
  overlay = template.content.firstElementChild.cloneNode(true);
  document.body.append(overlay);
  timer = setTimeout(finish, 2900);
  document.querySelectorAll('.app-shell,.schedule-robot').forEach(element => {
    inactive.push([element, element.inert]);
    element.inert = true;
  });
  document.documentElement.classList.add('login-arrival-playing');
  overlay.querySelector('.arrival-skip').addEventListener('click', finish);
  overlay.addEventListener('animationend', event => {
    if (event.target === overlay && event.animationName === 'arrival-dismiss') finish();
  });
  document.addEventListener('keydown', skipWithKey);
  document.addEventListener('visibilitychange', skipWhenHidden);
  preference.addEventListener('change', event => { if (event.matches) finish(); });
  window.addEventListener('pageshow', event => { if (event.persisted) finish(); });
  // Real navigation has already completed; animation never controls authentication.
})();
