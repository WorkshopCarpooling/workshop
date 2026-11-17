(() => {
  'use strict';
  const root = document.documentElement;
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  if (document.currentScript?.dataset.loginEntrance === 'true') root.classList.add('motion-login');
  const enter = () => {
    if (!reducedMotion.matches && document.visibilityState !== 'hidden') root.classList.add('motion-enter');
  };
  // Normal browser navigation preserves form submissions, maps and GPS lifecycles.
  window.addEventListener('pagereveal', (event) => {
    event.viewTransition?.skipTransition();
    enter();
  });
  // Cached pages can reveal before a deferred script runs. Also enter after parsing.
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', enter, { once: true });
  else enter();
  window.addEventListener('pageshow', (event) => {
    if (event.persisted) root.classList.remove('motion-enter', 'motion-login');
    updateVisibility();
  });
  reducedMotion.addEventListener('change', (event) => {
    if (event.matches) root.classList.remove('motion-enter');
  });

  // No polling or perpetual frame loop: observe only decorative scene visibility.
  const updateVisibility = () => root.classList.toggle('motion-tab-hidden', document.visibilityState === 'hidden');
  document.addEventListener('visibilitychange', updateVisibility);
  updateVisibility();
  let sceneObserver;
  const observeScenes = () => {
    if (!('IntersectionObserver' in window) || sceneObserver) return;
    sceneObserver = new window.IntersectionObserver((entries) => {
      entries.forEach((entry) => entry.target.classList.toggle('motion-decor-paused', !entry.isIntersecting));
    });
    document.querySelectorAll('.traffic-scene, .auth-vintage-art').forEach((scene) => {
      scene.classList.add('motion-decor-paused');
      sceneObserver.observe(scene);
    });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', observeScenes, { once: true });
  else observeScenes();
})();
