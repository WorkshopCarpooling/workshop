(() => {
  const root = document.querySelector('[data-schedule-robot]');
  if (!root) return;
  if (root.dataset.robotInitialized) return;
  root.dataset.robotInitialized = 'true';
  const node = name => root.querySelector(`[data-robot-${name}]`);
  const card = root.querySelector('.schedule-robot-card'), toggle = node('toggle');
  const storageKey = root.hasAttribute('data-preview-events') ? 'rideutem:schedule-robot:preview' : `rideutem:schedule-robot:v1:${root.dataset.user}`;
  let state = {}, events = [], activeKey = null, clockOffset = 0, timezone = 'Asia/Kuala_Lumpur', ready = false, failed = false, fetching = false, stopped = false;
  try { state = JSON.parse(localStorage.getItem(storageKey) || '{}'); if (!state || Array.isArray(state) || typeof state !== 'object') state = {}; } catch (_) { state = {}; }
  const now = () => Date.now() + clockOffset;
  const key = event => `${event.id}@${event.startsAt}${event.kind === 'appointment' ? ':' + event.status : ''}`;
  const start = event => Date.parse(event.startsAt);
  const status = event => state[key(event)] || {};
  const upcoming = () => events.filter(event => start(event) > now() || (event.kind === 'appointment' && Date.parse(event.validUntil) > now()));
  const available = () => upcoming().filter(event => !status(event).dismissed && !(status(event).snoozeUntil > now()));
  const tier = event => event.kind === 'appointment' && start(event) <= now() ? event.status : start(event) - now() <= 10 * 60000 ? 10 : 30;
  function save() {
    const oldest = now() - 2 * 86400000;
    for (const [id, value] of Object.entries(state)) if (!value || typeof value !== 'object' || (value.updatedAt || 0) < oldest) delete state[id];
    try { localStorage.setItem(storageKey, JSON.stringify(state)); } catch (_) { /* Private-mode storage may be unavailable. */ }
  }
  function updateState(event, patch) { state[key(event)] = {...status(event), ...patch, updatedAt:now()}; save(); }
  function open(show) {
    card.hidden = !show;
    toggle.setAttribute('aria-expanded', String(show));
    toggle.setAttribute('aria-label', show ? 'Close schedule reminders' : 'Open schedule reminders');
  }
  function render() {
    const queue = available();
    const event = queue.find(event => key(event) === activeKey) || queue[0];
    activeKey = event ? key(event) : null;
    const dueCount = queue.filter(event => start(event) - now() <= 30 * 60000).length;
    node('badge').hidden = dueCount === 0;
    node('badge').textContent = String(dueCount);
    root.dataset.urgency = dueCount ? 'soon' : 'idle';
    node('controls').hidden = !event;
    node('next').hidden = queue.length < 2;
    if (!event) {
      node('kicker').textContent = failed ? 'STAYING IN TOUCH' : 'LET’S PLAN AHEAD';
      node('title').textContent = failed ? 'I couldn’t refresh your schedule.' : ready ? 'You’re all caught up!' : 'Checking your schedule…';
      node('countdown').textContent = failed ? 'I’ll try again shortly. You can still check your appointments.' : 'Book a ride appointment or add a class, and I’ll remind you before it starts.';
      node('location').textContent = '';
      node('time').textContent = '';
      node('link').href = root.dataset.scheduleUrl || 'passenger/schedule';
      node('link').textContent = 'Book a ride appointment ↗';
      return;
    }
    const minutes = Math.max(1, Math.ceil((start(event) - now()) / 60000));
    const duration = minutes < 60 ? `${minutes} minute${minutes === 1 ? '' : 's'}` : `${Math.floor(minutes / 60)}h${minutes % 60 ? ` ${minutes % 60}m` : ''}`;
    node('kicker').textContent = minutes <= 10 ? 'IT’S ALMOST TIME' : event.kind === 'appointment' ? 'YOUR RIDE APPOINTMENT' : event.kind === 'ride' ? 'YOUR NEXT RIDE' : 'ON YOUR TIMETABLE';
    node('title').textContent = event.title;
    node('countdown').textContent = `${event.kind === 'appointment' ? 'Driver search starts' : event.kind === 'ride' ? 'Your ride departs' : 'Your session starts'} in ${duration}.`;
    if (event.kind === 'appointment') {
      if (event.status === 'accepted') { node('kicker').textContent = 'YOUR DRIVER ACCEPTED'; node('countdown').textContent = `${event.driver || 'A driver'} accepted your appointment. Check the vehicle and pickup details.`; }
      else if (event.status === 'pending') { node('kicker').textContent = 'FINDING YOUR DRIVER'; node('countdown').textContent = 'Your appointment time has arrived. Your request is visible to approved drivers; waiting for one to accept.'; }
      else if (event.status === 'expired') { node('kicker').textContent = 'NO DRIVER CONFIRMED'; node('countdown').textContent = 'No driver accepted within the search window. Open Schedule to book again or find a ride now.'; }
      else if (start(event) <= now()) { node('kicker').textContent = 'YOUR APPOINTMENT IS DUE'; node('countdown').textContent = 'Driver search will start as soon as your current ride or request finishes.'; }
    }
    node('location').textContent = `${event.kind === 'ride' || event.kind === 'appointment' ? 'Pickup: ' : 'Location: '}${event.location}${event.kind === 'appointment' ? ' · ' + event.passengers + ' passenger' + (event.passengers===1?'':'s') : ''}`;
    try { node('time').textContent = new Intl.DateTimeFormat('en-MY', {timeZone:timezone, weekday:'short', day:'numeric', month:'short', hour:'numeric', minute:'2-digit'}).format(new Date(start(event))) + ' · Malaysia time'; }
    catch (_) { node('time').textContent = new Date(start(event)).toLocaleString(); }
    node('link').href = event.url;
    node('link').textContent = `${event.action} ↗`;
  }
  function check() {
    const event = available().find(event => start(event) - now() <= 30 * 60000 && status(event).notifiedTier !== tier(event));
    if (event && !document.hidden) {
      activeKey = key(event);
      updateState(event, {notifiedTier:tier(event), snoozeUntil:0});
      open(true);
    }
    render();
  }
  function accept(payload) {
    if (!payload || !Array.isArray(payload.events)) throw new Error('Invalid reminder data');
    const serverTime = Date.parse(payload.serverTime);
    if (Number.isFinite(serverTime)) clockOffset = serverTime - Date.now();
    timezone = payload.timezone || timezone;
    events = payload.events.filter(event => {
      if (!event || !['class','ride','appointment'].includes(event.kind) || !Number.isFinite(start(event)) || typeof event.title !== 'string') return false;
      if (event.kind === 'appointment' && (!['scheduled','pending','accepted','expired'].includes(event.status) || !Number.isFinite(Date.parse(event.validUntil)))) return false;
      try { return new URL(event.url, location.href).origin === location.origin; } catch (_) { return false; }
    }).sort((a,b) => start(a) - start(b));
    ready = true; failed = false;
    check();
  }
  async function refresh() {
    if (fetching || stopped || document.hidden) return;
    fetching = true;
    const controller = new AbortController(), timeout = setTimeout(() => controller.abort(), 8000);
    try {
      const response = await fetch(root.dataset.endpoint, {headers:{Accept:'application/json'}, credentials:'same-origin', signal:controller.signal});
      if (response.redirected || response.status === 401 || response.status === 403) { stopped = true; root.hidden = true; return; }
      if (!response.ok) throw new Error('Unavailable');
      accept(await response.json());
    } catch (_) { failed = true; render(); }
    finally { clearTimeout(timeout); fetching = false; }
  }
  toggle.addEventListener('click', () => { render(); open(card.hidden); });
  node('close').addEventListener('click', () => open(false));
  node('dismiss').addEventListener('click', () => {
    const event = upcoming().find(event => key(event) === activeKey);
    if (event) updateState(event, {dismissed:true});
    activeKey = null; open(false); check();
  });
  node('snooze').addEventListener('click', () => {
    const event = upcoming().find(event => key(event) === activeKey);
    if (event) updateState(event, {snoozeUntil:now() + 5*60000, notifiedTier:null});
    activeKey = null; open(false); check();
  });
  node('next').addEventListener('click', () => { const queue=available(), index=queue.findIndex(event=>key(event)===activeKey); activeKey=queue.length ? key(queue[(index+1)%queue.length]) : null; render(); });
  root.addEventListener('keydown', event => { if (event.key === 'Escape') { open(false); toggle.focus(); } });
  document.addEventListener('visibilitychange', () => { if (!document.hidden) { check(); if (!root.hasAttribute('data-preview-events')) refresh(); } });
  document.addEventListener('rideutem:appointments-changed', () => { if (!root.hasAttribute('data-preview-events')) refresh(); });
  window.addEventListener('storage', event => { if (event.key === storageKey) { try { const value=JSON.parse(event.newValue || '{}'); state=value && typeof value==='object' && !Array.isArray(value) ? value : {}; } catch (_) { state={}; } render(); } });
  setInterval(check, 30000);
  if (root.hasAttribute('data-preview-events')) {
    try { accept(JSON.parse(root.dataset.previewEvents)); } catch (_) { failed=true; render(); }
  } else { refresh(); setInterval(refresh, 60000); }
})();
