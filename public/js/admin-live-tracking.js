(() => {
  const root = document.getElementById('live-drivers');
  if (!root) return;
  const list = document.getElementById('tracking-driver-list');
  const status = document.getElementById('tracking-feed-status');
  const count = document.getElementById('live-driver-count');
  const mapElement = document.getElementById('admin-driver-map');
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const markers = new Map();
  let map = null, rides = [], connected = false, receivedAt = 0, fitted = false, following = null, stopped = false;
  if (window.L) {
    map = L.map(mapElement).setView([2.3139, 102.3183], 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors', maxZoom: 19
    }).addTo(map);
    map.on('dragstart', () => { following = null; });
  } else {
    mapElement.textContent = 'Map could not load. Driver locations and GPS status are available in the list. Reload to retry the map.';
    mapElement.style.padding = '24px';
  }
  const carIcon = () => {
    const holder=document.createElement('div'),body=document.createElement('div'),image=document.createElement('img'),label=document.createElement('span');
    body.className='live-car';image.src=root.dataset.carIconUrl;image.alt='';image.draggable=false;body.append(image);
    label.className='car-state-label';holder.append(body,label);return holder;
  };
  const ageOf = (ride) => ride.location ? ride.location.ageSeconds + (performance.now() - receivedAt) / 1000 : null;
  const stateOf = (ride) => !ride.location || ageOf(ride) > 600 ? 'waiting' : (!connected || ride.state === 'stale' || ageOf(ride) > 30 ? 'stale' : 'live');
  const positionOf = (ride) => stateOf(ride) === 'waiting' ? null : ride.location;
  const remove = (id) => {
    const entry = markers.get(id);
    if (!entry) return;
    cancelAnimationFrame(entry.frame);
    entry.marker.remove();
    entry.circle.remove();
    markers.delete(id);
    if (following === id) following = null;
  };
  const fit = () => {
    following = null;
    const points = [...markers.values()].map((entry) => entry.marker.getLatLng());
    if (map && points.length) map.fitBounds(L.latLngBounds(points), { padding: [40, 40], maxZoom: 16 });
    else if (map) map.setView([2.3139, 102.3183], 14);
  };
  document.getElementById('tracking-fit').addEventListener('click', fit);
  const animate = (entry, location) => {
    cancelAnimationFrame(entry.frame);
    const from = entry.marker.getLatLng();
    const start = performance.now();
    const duration = reducedMotion ? 0 : 3500;
    const step = (time) => {
      const fraction = duration ? Math.min(1, (time - start) / duration) : 1;
      const point = [from.lat + (location.lat - from.lat) * fraction, from.lng + (location.lng - from.lng) * fraction];
      entry.marker.setLatLng(point);
      entry.circle.setLatLng(point);
      if (following === entry.id) map.panTo(point, { animate: false });
      if (fraction < 1) entry.frame = requestAnimationFrame(step);
    };
    entry.frame = requestAnimationFrame(step);
  };
  const syncMarkers = () => {
    if (!map) return;
    const ids = new Set();
    rides.forEach((ride) => {
      const state = stateOf(ride);
      const location = positionOf(ride);
      if (!location) return;
      ids.add(ride.id);
      let entry = markers.get(ride.id);
      if (!entry) {
        const marker = L.marker([location.lat, location.lng], { icon: L.divIcon({ className: 'live-car-marker', html: carIcon(), iconSize: [42, 48], iconAnchor: [21, 24] }), title: `${ride.driver} · ${ride.plate || 'Plate not recorded'}` }).addTo(map);
        const circle = L.circle([location.lat, location.lng], { radius: location.accuracy || 0, color: '#f0bf4d', weight: 1, fillOpacity: .08 }).addTo(map);
        const tooltip = document.createElement('span');
        tooltip.textContent = `${ride.driver} · ${ride.plate || 'No plate'}`;
        marker.bindTooltip(tooltip);
        entry = { id: ride.id, marker, circle, capturedAt: location.capturedAt || 0, frame: null, hasGps: state !== 'waiting' };
        markers.set(ride.id, entry);
      } else if (location.capturedAt > entry.capturedAt) {
        entry.capturedAt = location.capturedAt;
        if (state === 'live' && entry.hasGps) animate(entry, location);
        else { cancelAnimationFrame(entry.frame); entry.marker.setLatLng([location.lat, location.lng]); entry.circle.setLatLng([location.lat, location.lng]); }
        entry.hasGps = true;
      }
      if (state !== 'live') {
        cancelAnimationFrame(entry.frame);
        entry.marker.setLatLng([location.lat, location.lng]);
        entry.circle.setLatLng([location.lat, location.lng]);
      }
      entry.circle.setRadius(location.accuracy || 0);
      entry.circle.setStyle({ color: state === 'live' ? '#397552' : '#ac7a27' });
      entry.marker.getElement()?.classList.toggle('stale', state === 'stale');
      entry.marker.getElement()?.classList.toggle('planned', state === 'waiting');
      const stateLabel = entry.marker.getElement()?.querySelector('.car-state-label');
      if (stateLabel) stateLabel.textContent = state === 'waiting' ? 'Starting point · waiting for GPS' : state === 'stale' ? 'Last known' : '';
      const car = entry.marker.getElement()?.querySelector('.live-car');
      if (car && location.heading != null) car.style.transform = `rotate(${location.heading}deg)`;
    });
    [...markers.keys()].forEach((id) => { if (!ids.has(id)) remove(id); });
    if (!fitted && markers.size) { fit(); fitted = true; }
  };
  const el = (tag, text, className) => {
    const node = document.createElement(tag);
    node.textContent = text;
    if (className) node.className = className;
    return node;
  };
  const renderList = () => {
    // Preserve button focus across age/status updates.
    const focused = document.activeElement?.dataset?.followRide;
    list.replaceChildren();
    let live = 0;
    rides.forEach((ride) => {
      const state = stateOf(ride);
      if (state === 'live') live++;
      const card = el('article', '', 'tracking-driver');
      card.append(el('span', state === 'live' ? '● Live GPS' : state === 'stale' ? '● Last known location' : '○ Awaiting GPS', `tracking-state ${state}`));
      card.append(el('b', ride.driver));
      card.append(el('small', `${ride.vehicle || 'Vehicle'} · ${ride.plate || 'Plate not recorded'}`));
      card.append(el('small', `${ride.origin} → ${ride.destination}`));
      if (ride.gpsMessage && state !== 'live') card.append(el('small', ride.gpsMessage, 'tracking-gps-issue'));
      if (state !== 'waiting') {
        card.append(el('small', `Updated ${Math.floor(ageOf(ride))}s ago · accuracy ±${Math.round(ride.location.accuracy)} m`));
        card.append(el('small', `Latitude ${ride.location.lat.toFixed(5)} · Longitude ${ride.location.lng.toFixed(5)}`));
        const button = el('button', 'Follow car');
        button.type = 'button';
        button.dataset.followRide = String(ride.id);
        button.disabled = !map;
        button.addEventListener('click', () => {
          const entry = markers.get(ride.id);
          if (!entry) return;
          following = ride.id;
          map.setView(entry.marker.getLatLng(), 16);
          entry.marker.openTooltip();
        });
        card.append(button);
      } else {
        card.append(el('small', 'Waiting for device GPS. A car marker appears after the first latitude and longitude update.'));
      }
      list.append(card);
    });
    if (!rides.length) list.append(el('div', connected ? 'No active student trips. A car appears after a driver starts a trip and shares their location.' : 'Waiting for the server. Active trips cannot be confirmed.', 'tracking-empty'));
    count.textContent = connected ? `${live} live · ${rides.length} active trips` : 'Connection interrupted';
    if (focused) list.querySelector(`[data-follow-ride="${focused}"]`)?.focus({ preventScroll: true });
  };
  const poll = async () => {
    if (stopped) return;
    try {
      const response = await fetch(root.dataset.feedUrl, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }, signal: AbortSignal.timeout(10000) });
      if (!response.ok) {
        if ([401, 403, 419].includes(response.status)) {
          stopped = true;
          rides = [];
          [...markers.keys()].forEach(remove);
          list.replaceChildren(el('div', 'Admin session ended. Log in again to view driver locations.', 'tracking-empty'));
          count.textContent = 'Session ended';
        }
        throw new Error('Feed unavailable');
      }
      const data = await response.json();
      if (!Array.isArray(data.rides)) throw new Error('Invalid feed');
      rides = data.rides;
      connected = true;
      receivedAt = performance.now();
      syncMarkers();
      renderList();
      status.textContent = `Connected · last checked ${new Date().toLocaleTimeString()}`;
    } catch (error) {
      connected = false;
      if (!stopped) { syncMarkers(); renderList(); }
      status.textContent = stopped ? 'Please log in again.' : 'Connection interrupted. Retrying; displayed cars are last known locations.';
    } finally {
      if (!stopped) setTimeout(poll, 5000);
    }
  };
  setInterval(() => { if (!stopped) { syncMarkers(); renderList(); } }, 1000);
  void poll();
})();
