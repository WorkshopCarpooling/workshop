/* Public assets only. Never cache account HTML, API responses, locations or mutations. */
const CACHE = 'rideutem-public-v1';
const base = new URL('./', self.registration.scope);
const offlineURL = new URL('offline.html', base).href;
const assets = ['offline.html', 'js/offline.js', 'css/device.css', 'js/device.js', 'icons/icon-192.png', 'icons/icon-512.png', 'icons/icon-maskable-512.png', 'icons/apple-touch-icon.png'];
self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE).then(cache => cache.addAll(assets.map(path => new URL(path, base).href))));
});
self.addEventListener('activate', event => {
  event.waitUntil((async () => {
    for (const key of await caches.keys()) if (key.startsWith('rideutem-public-') && key !== CACHE) await caches.delete(key);
    await self.clients.claim();
  })());
});
self.addEventListener('message', event => {
  if (event.data?.type === 'APPLY_UPDATE') self.skipWaiting();
});
self.addEventListener('fetch', event => {
  const request = event.request, url = new URL(request.url);
  if (request.method !== 'GET' || url.origin !== base.origin || !url.pathname.startsWith(base.pathname)) return;
  if (request.mode === 'navigate') {
    event.respondWith(fetch(request).catch(async () => {
      const cached = await caches.match(offlineURL);
      // An inline fallback also handles an interrupted cache installation.
      if (cached) return new Response((await cached.text()).replace('<head>', `<head><base href="${base.href}">`), {status:503, headers:{'Content-Type':'text/html; charset=utf-8'}});
      return new Response('You are offline. Reconnect and reload RideUTeM.', {status:503, headers:{'Content-Type':'text/plain; charset=utf-8'}});
    }));
    return;
  }
  const relative = url.pathname.slice(base.pathname.length);
  if (!/^(?:css\/[a-z0-9-]+\.css|js\/[a-z0-9-]+\.js|icons\/[a-z0-9-]+\.(?:png|svg))$/i.test(relative)) return;
  event.respondWith((async () => {
    const cache = await caches.open(CACHE);
    try {
      const response = await fetch(request);
      if (response.ok && !response.redirected && response.type === 'basic') await cache.put(request, response.clone());
      return response;
    } catch (error) { return await cache.match(request) || Response.error(); }
  })());
});
