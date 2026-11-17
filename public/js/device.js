(() => {
  const connection = document.getElementById('connectionStatus');
  const connectivity = () => { if (connection) connection.hidden = navigator.onLine; };
  window.addEventListener('online', connectivity);
  window.addEventListener('offline', connectivity);
  connectivity();

  // Progressive enhancement: menus stay visible when JavaScript is unavailable.
  const sidebar=document.querySelector('.app-page .sidebar');
  if(sidebar) {
    const navs=[...sidebar.querySelectorAll(':scope > .side-nav')];
    if(navs.length) {
      const workspaceToggle = sidebar.querySelector('[data-workspace-mobile-toggle]');
      const toggle = workspaceToggle || document.createElement('button');
      if (!workspaceToggle) {
        toggle.type = 'button';
        toggle.className = 'mobile-nav-toggle';
        sidebar.querySelector('.app-brand')?.after(toggle);
      }
      navs.forEach((nav, index) => { nav.id ||= `deviceNavigation${index}`; });
      toggle.setAttribute('aria-controls', navs.map(nav => nav.id).join(' '));

      let menuOpen = false;
      const mobile = matchMedia('(max-width:760px)');
      const profile = sidebar.querySelector('[data-profile-menu]');
      const setMenuOpen = open => {
        menuOpen = open;
        sidebar.dataset.mobileNavOpen = String(open);
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
        if (!workspaceToggle) toggle.textContent = open ? 'Close ×' : 'Menu ☰';
      };
      toggle.addEventListener('click', () => {
        setMenuOpen(!menuOpen);
        if (menuOpen && workspaceToggle) {
          if (profile) profile.open = false;
          navs[0].querySelector('a')?.focus();
        }
      });
      document.addEventListener('focusin', event => {
        if (menuOpen && workspaceToggle && !toggle.contains(event.target) && !navs[0].contains(event.target)) setMenuOpen(false);
      });
      document.addEventListener('click', event => {
        if (menuOpen && !toggle.contains(event.target) && !navs.some(nav => nav.contains(event.target))) setMenuOpen(false);
      });
      sidebar.addEventListener('keydown', event => {
        if (event.key !== 'Escape' || !menuOpen) return;
        setMenuOpen(false);
        toggle.focus();
        event.preventDefault();
      });
      navs.forEach(nav => nav.addEventListener('click', event => {
        if (!menuOpen || !event.target.closest('a')) return;
        setMenuOpen(false);
        toggle.focus();
      }));
      mobile.addEventListener('change', () => setMenuOpen(false));
      window.addEventListener('pageshow', () => setMenuOpen(false));
      if (mobile.matches && workspaceToggle && profile) profile.open = false;
      setMenuOpen(false);
      document.body.classList.add('nav-ready');
    }
  }
  // Retire only the installable-web-app worker previously shipped by RideUTeM.
  const retiredWorker = document.querySelector('script[data-retired-worker-url]')?.dataset.retiredWorkerUrl;
  if ('serviceWorker' in navigator && retiredWorker) {
    navigator.serviceWorker.getRegistrations().then(async registrations => {
      for (const registration of registrations) {
        const workers = [registration.active, registration.waiting, registration.installing];
        if (workers.some(worker => worker?.scriptURL === retiredWorker)) await registration.unregister();
      }
      if ('caches' in window) {
        for (const key of await caches.keys()) if (key.startsWith('rideutem-public-')) await caches.delete(key);
      }
    }).catch(() => {});
  }

  document.addEventListener('submit',event=>{
    const form=event.target;if(!(form instanceof HTMLFormElement)||event.defaultPrevented)return;
    if(!navigator.onLine){event.preventDefault();connection.hidden=false;return;}
    if(form.method.toLowerCase()!=='post')return;
    if(form.getAttribute('aria-busy')==='true'){event.preventDefault();return;}
    form.setAttribute('aria-busy','true');
    const button=event.submitter;if(button){button.dataset.idleLabel=button.textContent;button.setAttribute('aria-disabled','true');button.textContent='Please wait…';}
  });
  window.addEventListener('pageshow',()=>{
    document.querySelectorAll('form[aria-busy=true]').forEach(form=>form.removeAttribute('aria-busy'));
    document.querySelectorAll('[data-idle-label]').forEach(button=>{button.textContent=button.dataset.idleLabel;button.removeAttribute('aria-disabled');delete button.dataset.idleLabel;});
  });
})();
