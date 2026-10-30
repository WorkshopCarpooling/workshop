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
      const toggle=document.createElement('button');toggle.type='button';toggle.className='mobile-nav-toggle';toggle.textContent='Menu ☰';
      navs.forEach((nav,i)=>{nav.id=nav.id||`deviceNavigation${i}`;});
      toggle.setAttribute('aria-controls',navs.map(nav=>nav.id).join(' '));toggle.setAttribute('aria-expanded','false');
      toggle.setAttribute('aria-label','Open navigation menu');sidebar.querySelector('.app-brand')?.after(toggle);
      const openMenu=open=>{sidebar.dataset.mobileNavOpen=String(open);toggle.setAttribute('aria-expanded',String(open));toggle.textContent=open?'Close ×':'Menu ☰';toggle.setAttribute('aria-label',open?'Close navigation menu':'Open navigation menu');};
      toggle.addEventListener('click',()=>openMenu(sidebar.dataset.mobileNavOpen!=='true'));
      sidebar.addEventListener('keydown',e=>{if(e.key==='Escape'){openMenu(false);toggle.focus();}});
      navs.forEach(nav=>nav.addEventListener('click',e=>{if(e.target.closest('a'))openMenu(false);}));
      matchMedia('(min-width:761px)').addEventListener('change',()=>openMenu(false));
      openMenu(false);document.body.classList.add('nav-ready');
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
