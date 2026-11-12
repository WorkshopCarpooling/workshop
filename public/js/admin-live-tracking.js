(async () => {
  const root = document.getElementById('live-drivers');
  if (!root) return;
  const list = document.getElementById('tracking-driver-list');
  const status = document.getElementById('tracking-feed-status');
  const count = document.getElementById('live-driver-count');
  const mapElement = document.getElementById('admin-driver-map');
  const routeStatus = document.getElementById('tracking-route-status');
  const geometry = window.NavigationGeometry;
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const markers = new Map();
  const L = (window.createAdminDriverMap ? await window.createAdminDriverMap(mapElement, { reducedMotion, moduleUrl: root.dataset.mapModuleUrl }) : null) || window.L;
  let map = null, rides = [], connected = false, receivedAt = 0, fitted = false, following = null, stopped = false;
  let selected = null, routeRequest = 0, routeLayers = [], activeRoute = null, initialViewChosen = false;
  if (L) {
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
  const stateOf = (ride) => !ride.location || ageOf(ride) > 600 ? 'waiting' : (!connected || ride.state === 'stale' || ageOf(ride) > 30 ? 'stale' : ride.source==='simulation'&&ride.simulation?'simulated':'live');
  const positionOf = (ride) => stateOf(ride) === 'waiting' ? null : ride.location;
  const clearRoute = () => {
    routeRequest++;
    routeLayers.forEach((layer) => layer.remove());
    routeLayers = [];
    activeRoute = null;
    selected = null;
    markers.forEach((entry) => { entry.path = null; });
    if (routeStatus) routeStatus.textContent = 'Select a driver to view their planned route.';
  };
  const updateRemainingRoute = (entry, point, force = false) => {
    if (!activeRoute || activeRoute.id !== entry.id || !entry.path || !geometry) return;
    const now = performance.now();
    // Update only the selected line, at most ten times a second, on slower devices.
    if (!force && now - activeRoute.updatedAt < 100) return;
    activeRoute.updatedAt = now;
    const projection = geometry.project(entry.path, point);
    if (projection.distance > 75) {
      activeRoute.lines.forEach((line) => line.setLatLngs(activeRoute.points));
      return;
    }
    const start = geometry.at(entry.path, projection.along);
    let low = 0, high = entry.path.cumulative.length;
    while (low < high) {
      const middle = (low + high) >>> 1;
      if (entry.path.cumulative[middle] <= projection.along + .1) low = middle + 1;
      else high = middle;
    }
    const points = [[start.lat, start.lng], ...activeRoute.points.slice(low)];
    activeRoute.lines.forEach((line) => line.setLatLngs(points));
  };
  const selectRoute = async (id) => {
    clearRoute();
    selected = id;
    if (!root.dataset.routeUrl || !map) return;
    const request = routeRequest;
    if (routeStatus) routeStatus.textContent = 'Loading the planned route…';
    try {
      const response = await fetch(`${root.dataset.routeUrl}/${id}/route`, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }, signal: AbortSignal.timeout(15000) });
      if (!response.ok) throw new Error('Route unavailable');
      const data = await response.json();
      if (request !== routeRequest || !markers.has(id)) return;
      const road = data.route;
      if (!road || !Array.isArray(road.path) || road.path.length < 2 || road.path.some((p) => !Number.isFinite(p.lat) || !Number.isFinite(p.lng) || Math.abs(p.lat) > 90 || Math.abs(p.lng) > 180)) throw new Error('No route coordinates');
      const points = road.path.map((p) => [p.lat, p.lng]);
      routeLayers.push(L.polyline(points, { color: '#fff', weight: 9, opacity: .95, interactive: false }).addTo(map));
      routeLayers.push(L.polyline(points, { color: '#15191d', weight: 5, opacity: .95, dashArray: road.road ? null : '6 9', interactive: false }).addTo(map));
      routeLayers.push(L.circleMarker(points.at(-1), { radius: 8, color: '#15191d', weight: 6, fillColor: '#fff', fillOpacity: 1, interactive: false }).addTo(map));
      const entry = markers.get(id);
      entry.path = road.road && geometry ? geometry.route(road.path) : null;
      activeRoute = { id, lines: routeLayers.slice(0, 2), points, updatedAt: -Infinity };
      updateRemainingRoute(entry, entry.marker.getLatLng(), true);
      if (routeStatus) routeStatus.textContent = road.road ? 'Planned road route · remaining route from the car to the destination.' : 'Approximate planned route · road routing unavailable. The car follows received GPS positions.';
    } catch (_) {
      if (request === routeRequest && routeStatus) routeStatus.textContent = 'Planned route unavailable. Driver GPS tracking continues.';
    }
  };
  const remove = (id) => {
    const entry = markers.get(id);
    if (!entry) return;
    cancelAnimationFrame(entry.frame);
    entry.marker.remove();
    entry.circle.remove();
    markers.delete(id);
    if (selected === id) clearRoute();
    if (following === id) following = null;
  };
  const fit = () => {
    following = null;
    clearRoute();
    const points = [...markers.values()].map((entry) => entry.marker.getLatLng());
    if (map && points.length) map.fitBounds(L.latLngBounds(points), { padding: [40, 40], maxZoom: 16 });
    else if (map) map.setView([2.3139, 102.3183], 14);
  };
  document.getElementById('tracking-fit').addEventListener('click', fit);
  const angleButton = document.getElementById('tracking-angle');
  if (angleButton) {
    angleButton.disabled = !map?.setAngle;
    angleButton.setAttribute('aria-pressed', String(map?.isAngled?.() ?? false));
    angleButton.addEventListener('click', () => {
      const angled = angleButton.getAttribute('aria-pressed') !== 'true';
      map?.setAngle?.(angled);
      angleButton.setAttribute('aria-pressed', String(angled));
    });
  }
  const follow = (id) => {
    const entry = markers.get(id);
    if (!entry) return;
    following = id;
    map.setView(entry.marker.getLatLng(), 16);
    // The driver's details stay in the card, leaving the car unobscured.
    entry.marker.closeTooltip?.();
    void selectRoute(id);
    renderList();
  };
  const headingDelta = (from, to) => (((to - from) % 360 + 540) % 360) - 180;
  const orient = (entry) => {
    const body = entry.marker.getElement()?.querySelector('.live-car');
    const heading = map.screenHeading ? map.screenHeading(entry.marker.getLatLng(), entry.heading) : entry.heading;
    if (body) body.style.transform = `rotate(${heading}deg)`;
  };
  map?.on('move', () => markers.forEach(orient));
  const place = (entry, point, heading) => {
    entry.marker.setLatLng([point.lat, point.lng]);
    entry.circle.setLatLng([point.lat, point.lng]);
    entry.heading = heading;
    updateRemainingRoute(entry, point);
    if (following === entry.id) map.panTo([point.lat, point.lng], { animate: false });
    orient(entry);
  };
  const animate = (entry, location) => {
    cancelAnimationFrame(entry.frame);
    const from = entry.marker.getLatLng();
    const start = performance.now();
    const duration = reducedMotion ? 0 : 5000;
    const initialHeading = entry.heading;
    const targetHeading = location.heading ?? (geometry && geometry.distance(from, location) > 2 ? geometry.bearing(from, location) : initialHeading);
    const path = entry.path;
    const origin = path ? geometry.project(path, from) : null;
    const target = path ? geometry.project(path, location) : null;
    const onRoad = origin && target && origin.distance < 75 && target.distance < 75 && location.accuracy <= 100;
    const distance = onRoad ? Math.abs(target.along - origin.along) : (geometry ? geometry.distance(from, location) : 0);
    const direction = onRoad ? Math.sign(target.along - origin.along) : null;
    const averageSpeed = distance / 5;
    const startSlope = entry.speed == null ? 1 : (direction && entry.pathDirection && direction !== entry.pathDirection ? 0 : Math.min(2, Math.max(0, averageSpeed ? entry.speed / averageSpeed : 0)));
    entry.pathDirection = direction;
    let previousTime = start;
    const step = (time) => {
      const fraction = duration ? Math.min(1, (time - start) / duration) : 1;
      // Carry the displayed speed into the next fix, bounded to avoid overshoot.
      const progress = reducedMotion ? 1 : fraction + (startSlope - 1) * fraction * (1 - fraction) ** 2;
      entry.speed = averageSpeed * (1 + (startSlope - 1) * (1 - 4 * fraction + 3 * fraction ** 2));
      const point = onRoad ? geometry.at(path, origin.along + (target.along - origin.along) * progress) : { lat: from.lat + (location.lat - from.lat) * progress, lng: from.lng + (location.lng - from.lng) * progress };
      let roadHeading = point.heading;
      if (onRoad) {
        const along = origin.along + (target.along - origin.along) * progress;
        // Average the road tangent on either side, so steering starts gradually.
        const before = geometry.at(path, along - 5), after = geometry.at(path, along + 5);
        roadHeading = (geometry.bearing(before, after) + (target.along < origin.along ? 180 : 0)) % 360;
      }
      const steeringTime = reducedMotion ? 1 : Math.min(1, (time - start) / 700);
      const steering = steeringTime * steeringTime * (3 - 2 * steeringTime);
      const heading = onRoad ? (reducedMotion ? roadHeading : entry.heading + headingDelta(entry.heading, roadHeading) * (1 - Math.exp(-(time - previousTime) / 160))) : initialHeading + headingDelta(initialHeading, targetHeading) * steering;
      previousTime = time;
      place(entry, point, heading);
      if (fraction < 1) entry.frame = requestAnimationFrame(step);
    };
    entry.frame = requestAnimationFrame(step);
  };
  const animateShared = (entry, ride) => {
    cancelAnimationFrame(entry.frame);
    const simulation=ride.simulation,points=ride.simulationRoute?.path;
    if(!geometry||!points?.length){place(entry,ride.location,ride.location.heading||0);return;}
    const path=geometry.route(points),base=ride.serverNow,received=receivedAt;
    const step=time=>{
      const elapsed=Math.max(0,base+(time-received)/1000-simulation.startedAt);
      const fraction=simulation.durationSeconds?Math.min(1,elapsed/simulation.durationSeconds):1;
      const point=geometry.at(path,path.total*fraction);
      place(entry,point,point.heading||0);
      if(fraction<1&&connected&&(time-received)/1000<30)entry.frame=requestAnimationFrame(step);
    };
    step(performance.now());
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
        const marker = L.marker([location.lat, location.lng], { icon: L.divIcon({ className: 'live-car-marker', html: carIcon(), iconSize: [64, 88], iconAnchor: [32, 44] }), title: `${ride.driver} · ${ride.plate || 'Plate not recorded'}` }).addTo(map);
        const circle = L.circle([location.lat, location.lng], { radius: location.accuracy || 0, color: '#f0bf4d', weight: 1, fillOpacity: .08 }).addTo(map);
        const tooltip = document.createElement('span');
        tooltip.textContent = `${ride.driver} · ${ride.plate || 'No plate'}`;
        marker.bindTooltip(tooltip);
        entry = { id: ride.id, marker, circle, capturedAt: location.capturedAt || 0, frame: null, hasGps: state !== 'waiting', heading: location.heading ?? 0, path: null };
        markers.set(ride.id, entry);
        marker.on?.('click', () => follow(ride.id));
        place(entry, location, entry.heading);
      } else if (state!=='simulated' && location.capturedAt > entry.capturedAt) {
        entry.capturedAt = location.capturedAt;
        if (state === 'live' && entry.hasGps) animate(entry, location);
        else { cancelAnimationFrame(entry.frame); place(entry, location, location.heading ?? entry.heading); }
        entry.hasGps = true;
      }
      if(state==='simulated'){entry.capturedAt=location.capturedAt;animateShared(entry,ride);}
      else if (state !== 'live') {
        cancelAnimationFrame(entry.frame);
        place(entry, location, location.heading ?? entry.heading);
      }
      entry.circle.setRadius(location.accuracy || 0);
      entry.circle.setStyle({ color: ['live','simulated'].includes(state) ? '#397552' : '#ac7a27' });
      window.LiveCarbon?.paintMarker(ride, entry.marker.getElement());
      entry.marker.getElement()?.classList.toggle('stale', state === 'stale');
      entry.marker.getElement()?.classList.toggle('planned', state === 'waiting');
      const stateLabel = entry.marker.getElement()?.querySelector('.car-state-label');
      if (stateLabel) stateLabel.textContent = state === 'waiting' ? 'Starting point · waiting for GPS' : state === 'stale' ? 'Last known' : state==='simulated'?'Live simulation':'';
    });
    [...markers.keys()].forEach((id) => { if (!ids.has(id)) remove(id); });
    if (!fitted && markers.size) { fit(); fitted = true; }
    if (!initialViewChosen && markers.size) {
      initialViewChosen = true;
      if (markers.size === 1 && root.dataset.autoFollow === 'true') follow(markers.keys().next().value);
    }
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
    const focusedCarbon = document.activeElement?.dataset?.carbonRide;
    list.replaceChildren();
    let live = 0;
    rides.forEach((ride) => {
      const state = stateOf(ride);
      if (['live','simulated'].includes(state)) live++;
      const card = el('article', '', 'tracking-driver');
      card.classList.toggle('selected', selected === ride.id);
      card.append(el('span', state === 'simulated' ? '● Live simulation · synced with driver' : state === 'live' ? '● Live GPS' : state === 'stale' ? '● Last known location' : '○ Awaiting GPS', `tracking-state ${state}`));
      card.append(el('b', ride.driver));
      window.LiveCarbon?.renderCard(card, ride);
      card.append(el('small', `${ride.vehicle || 'Vehicle'} · ${ride.plate || 'Plate not recorded'}`));
      card.append(el('small', `${ride.origin} → ${ride.destination}`));
      if (ride.gpsMessage && state !== 'live') card.append(el('small', ride.gpsMessage, 'tracking-gps-issue'));
      if (state !== 'waiting') {
        card.append(el('small', state==='simulated'?`Shared trip clock · updated ${Math.floor(ageOf(ride))}s ago`:`Updated ${Math.floor(ageOf(ride))}s ago · accuracy ±${Math.round(ride.location.accuracy)} m`));
        card.append(el('small', `Latitude ${ride.location.lat.toFixed(5)} · Longitude ${ride.location.lng.toFixed(5)}`));
        const button = el('button', 'Follow car');
        button.type = 'button';
        button.dataset.followRide = String(ride.id);
        button.disabled = !map;
        button.addEventListener('click', () => {
          follow(ride.id);
        });
        card.append(button);
      } else {
        card.append(el('small', 'Waiting for device GPS. A car marker appears after the first latitude and longitude update.'));
      }
      if(ride.previewUrl){const preview=el('a','Open 3-view live preview','text-link');preview.href=ride.previewUrl;card.append(preview);}
      list.append(card);
    });
    if (!rides.length) list.append(el('div', connected ? 'No active student trips. Trips appear when the driver presses Start trip. Automatic driving is shared immediately; GPS trips appear after the first location fix.' : 'Waiting for the server. Active trips cannot be confirmed.', 'tracking-empty'));
    count.textContent = connected ? `${live} live · ${rides.length} active trips` : 'Connection interrupted';
    if (focused) list.querySelector(`[data-follow-ride="${focused}"]`)?.focus({ preventScroll: true });
    if (focusedCarbon) list.querySelector(`[data-carbon-ride="${focusedCarbon}"]`)?.focus({ preventScroll: true });
    window.LiveCarbon?.refresh(rides);
  };
  const poll = async () => {
    if (stopped) return;
    try {
      const response = await fetch(root.dataset.feedUrl, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }, signal: AbortSignal.timeout(10000) });
      if (!response.ok) {
        if ([401, 403, 419].includes(response.status)) {
          stopped = true;
          rides = [];
          window.LiveCarbon?.refresh([]);
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
