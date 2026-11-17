(() => {
  const config = window.ridePageState;
  if (!config) return;
  let polling = false;
  async function refresh() {
    if (polling || document.hidden) return;
    polling = true;
    try {
      const response = await fetch(config.url, {headers:{Accept:'application/json'}, credentials:'same-origin', cache:'no-store', signal:AbortSignal.timeout(10000)});
      if (!response.ok) return;
      const data = await response.json();
      if (data.version && data.version !== config.version) window.location.reload();
    } catch (_) { /* Retry on the next poll without replacing the visible orders. */ }
    finally { polling = false; }
  }
  setInterval(refresh, 5000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
  window.addEventListener('pageshow', event => { if (event.persisted) window.location.reload(); });
  refresh();
})();
