/* Finite, event-driven polish. No polling, mouse tracking, or animation library. */
(() => {
  'use strict';
  const preference = matchMedia('(prefers-reduced-motion: reduce)');
  const root = document.documentElement;
  let observer;
  let updates;
  const visibility = () => root.classList.toggle('motion-tab-hidden', document.hidden);
  document.addEventListener('visibilitychange', visibility);
  visibility();

  const reveal = () => {
    if (preference.matches || !('IntersectionObserver' in window)) return;
    const selector = '.overview-intro,.welcome-row,.overview-metric,.overview-card,.booking-guide,.content-card,.stat-card,.admin-stat,.workspace-tile,.workspace-stats,.research-stats,.find-search-card,.ride-result-card,.trip-status-card,.privacy-card,.complaint-card,.members-card';
    const seen = new WeakSet();
    // A nested card moves with its parent; never animate live map containers.
    const observeCard = card => {
      if (seen.has(card) || card.parentElement?.closest(selector) || card.matches('.map-card') || card.querySelector('.leaflet-container,.maplibregl-map,.journey-map,.tracking-map,[data-live-map],canvas,[id*="Map"]')) return;
      seen.add(card);
      observer.observe(card);
    };
    observer = new IntersectionObserver(entries => {
      let step = 0;
      for (const entry of entries) {
        if (!entry.isIntersecting) continue;
        observer.unobserve(entry.target);
        entry.target.style.setProperty('--reveal-delay', `${Math.min(step++ * 65, 260)}ms`);
        entry.target.classList.add('interface-reveal');
        const finish = event => {
          if (event.target === entry.target && event.animationName === 'workspace-reveal') {
            entry.target.classList.remove('interface-reveal');
            entry.target.style.removeProperty('--reveal-delay');
            entry.target.removeEventListener('animationend', finish);
          }
        };
        entry.target.addEventListener('animationend', finish);
      }
    }, { threshold: 0.06 });
    const content = document.querySelector('.app-page .page-content');
    if (!content) return;
    content.querySelectorAll(selector).forEach(observeCard);
    // New ride-search results use the same reveal, without rescanning the page.
    if ('MutationObserver' in window) {
      updates = new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(node => {
        if (!(node instanceof Element)) return;
        if (node.matches(selector)) observeCard(node);
        node.querySelectorAll(selector).forEach(observeCard);
      })));
      updates.observe(content, { childList: true, subtree: true });
    }
  };
  if (document.querySelector('meta[name="rideutem-login-arrival"]')) {
    document.addEventListener('rideutem:arrival-complete', reveal, { once: true });
  } else reveal();
  preference.addEventListener('change', event => {
    if (!event.matches) return;
    observer?.disconnect();
    updates?.disconnect();
    document.querySelectorAll('.interface-reveal').forEach(card => card.classList.remove('interface-reveal'));
  });

  document.addEventListener('pointerdown', event => {
    if (preference.matches || event.button !== 0) return;
    const action = event.target.closest('.overview-primary,.solid-button,.find-submit,.search-button,.submit-button,.landing-button,.approve-button');
    if (!action || action.disabled || action.getAttribute('aria-disabled') === 'true') return;
    action.querySelector('.interface-ripple')?.remove();
    const bounds = action.getBoundingClientRect();
    const ripple = document.createElement('span');
    ripple.className = 'interface-ripple';
    ripple.setAttribute('aria-hidden', 'true');
    ripple.style.setProperty('--ripple-x', `${event.clientX - bounds.left}px`);
    ripple.style.setProperty('--ripple-y', `${event.clientY - bounds.top}px`);
    ripple.style.setProperty('--ripple-size', `${Math.max(bounds.width, bounds.height) * 2}px`);
    action.classList.add('interface-ripple-host');
    action.append(ripple);
    ripple.addEventListener('animationend', () => ripple.remove(), { once: true });
  }, { passive: true });
})();
