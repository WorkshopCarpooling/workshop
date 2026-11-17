(() => {
  'use strict';
  const config=window.liveTripPreview,G=window.NavigationGeometry,L=window.L;
  if(!config||!G)return;
  const connection=document.getElementById('previewConnection'),journey=document.getElementById('previewJourney');
  const statusNodes=[...document.querySelectorAll('[data-preview-state]')],positions=[...document.querySelectorAll('[data-preview-position]')];
  if(!L){connection.textContent='Maps could not load. Reload to retry.';return;}
  function car(map, title) {
    const body = document.createElement('div');
    body.className = 'preview-car-body';
    const image = document.createElement('img');
    image.src = config.carIconUrl;
    image.alt = '';
    body.append(image);
    const marker = L.marker([2.3139, 102.3183], {
      title, opacity: 0, icon: L.divIcon({ className: 'preview-car-marker', html: body, iconSize: [64, 88], iconAnchor: [32, 44] }),
    }).addTo(map);
    return { marker, body };
  }
  const maps=['driver','passenger','admin'].map(role=>{
    const map=L.map(`preview-${role}-map`).setView([2.3139,102.3183],14);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'© OpenStreetMap contributors',maxZoom:19}).addTo(map);
    return {map,...car(map,'Selected car'),line:L.polyline([],{color:'#377d62',weight:5}).addTo(map)};
  });
  const adminMap = maps[2].map;
  const fleetMarkers = new Map();
  let fleetBoundsKey = '';
  let snapshot=null,received=0,frame=null,busy=false,stopped=false,pathKey='',path=null;
  const hide=()=>{maps.forEach(view=>{view.marker.setOpacity(0);view.line.setLatLngs([]);});pathKey='';path=null;};
  const carCount = count => `${count} ${count === 1 ? 'car' : 'cars'} on the admin map`;
  const label=(value, count = 0)=>statusNodes.forEach((node, index)=>{
    const message = index === 2 && count ? carCount(count) : value;
    if (node.textContent !== message) node.textContent = message;
  });
  const place=(point,state)=>{
    maps.forEach(view=>{view.marker.setOpacity(1);view.marker.setLatLng([point.lat,point.lng]);view.body.style.transform=`rotate(${point.heading||0}deg)`;});
    positions.forEach(node=>{node.textContent=`${point.lat.toFixed(5)}, ${point.lng.toFixed(5)}`;});
    label(state, fleetMarkers.size ? fleetMarkers.size + 1 : 0);
  };

  function updateFleet() {
    const otherCars = (snapshot.fleet ?? []).filter(ride => ride.id !== snapshot.ride?.id);
    const currentIds = new Set(otherCars.map(ride => ride.id));
    for (const [id, view] of fleetMarkers) {
      if (!currentIds.has(id)) {
        view.marker.remove();
        fleetMarkers.delete(id);
      }
    }
    for (const ride of otherCars) {
      let view = fleetMarkers.get(ride.id);
      if (!view) {
        view = car(adminMap, `Trip #${ride.id}${ride.simulation ? ' · Estimated position' : ''}`);
        fleetMarkers.set(ride.id, view);
      }
      view.ride = ride;
      view.path = G.route(ride.path);
      view.body.setAttribute('data-carbon-level', ride.carbonLevel || 'unknown');
    }
    const boundsKey = JSON.stringify([...currentIds]);
    if (boundsKey !== fleetBoundsKey && (otherCars.length || fleetBoundsKey)) {
      fleetBoundsKey = boundsKey;
      fitAdminMap();
    }
  }

  function fitAdminMap() {
    const points = (path?.points ?? []).map(p => [p.lat, p.lng]);
    if (snapshot.ride?.location) points.push([snapshot.ride.location.lat, snapshot.ride.location.lng]);
    for (const view of fleetMarkers.values()) {
      points.push([view.ride.location.lat, view.ride.location.lng]);
      for (const point of view.path.points) points.push([point.lat, point.lng]);
    }
    if (points.length) adminMap.fitBounds(L.latLngBounds(points), { padding: [36, 36], maxZoom: 16 });
  }

  function clearFleet() {
    fleetMarkers.forEach(view => view.marker.remove());
    fleetMarkers.clear();
    fleetBoundsKey = '';
  }

  function position(ride, route, serverNow, time) {
    const simulation = ride.simulation;
    if (!simulation || !route?.points.length) return { point: ride.location, moving: false };
    const elapsed = Math.max(0, serverNow + (time - received) / 1000 - simulation.startedAt);
    const fraction = simulation.durationSeconds ? Math.min(1, elapsed / simulation.durationSeconds) : 1;
    return { point: G.at(route, route.total * fraction), moving: fraction < 1 };
  }

  function animate(time){
    if (stopped || !snapshot) return;
    let moving = false;
    const ride = snapshot.ride;
    if (ride?.location) {
      const selected = position(ride, path, snapshot.serverNow, time);
      const state = ride.simulation && path?.points.length
        ? (ride.simulation.destinationReached ? 'Destination reached' : selected.moving ? 'Moving together' : 'Driver paused')
        : (ride.state === 'stale' ? 'Last known position' : 'Live GPS');
      place(selected.point, state);
      moving = selected.moving;
    }
    for (const view of fleetMarkers.values()) {
      const other = position(view.ride, view.path, view.ride.serverNow, time);
      view.marker.setOpacity(1);
      view.marker.setLatLng([other.point.lat, other.point.lng]);
      view.body.style.transform = `rotate(${other.point.heading || 0}deg)`;
      moving ||= other.moving;
    }
    if (!ride?.location && fleetMarkers.size) {
      const message = carCount(fleetMarkers.size);
      if (statusNodes[2].textContent !== message) statusNodes[2].textContent = message;
    }
    if (moving && time - received < 30000) frame = requestAnimationFrame(animate);
  }
  async function poll(){
    if(stopped||busy||document.hidden)return;busy=true;
    try{
      const response=await fetch(config.feedUrl,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal:AbortSignal.timeout(10000)});
      if(!response.ok){if([401,403,404,419].includes(response.status)){stopped=true;hide();clearFleet();label('Trip monitor unavailable');positions.forEach(node=>{node.textContent='Location unavailable';});journey.textContent='Trip details unavailable.';}throw new Error();}
      snapshot=await response.json();received=performance.now();cancelAnimationFrame(frame);
      if(!snapshot.ride){hide();label(snapshot.ended?'Trip completed':'Waiting for Start trip');connection.textContent=snapshot.ended?'Trip ended':'Connected · waiting for an active trip';journey.textContent=snapshot.ended?'This trip has ended. Its record is available in History.':'The next trip appears automatically when the driver presses Start trip.';updateFleet();animate(performance.now());return;}
      const ride=snapshot.ride;journey.textContent=`Trip #${ride.id} · ${ride.origin} → ${ride.destination}`;
      maps.forEach(view=>view.body.setAttribute('data-carbon-level',ride.carbon?.level||'unknown'));
      if(Number.isFinite(ride.carbon?.participant_g_per_km))journey.textContent+=` · ${ride.carbon.participant_g_per_km.toFixed(1)} g CO₂e / person / km`;
      connection.textContent=ride.source==='simulation'?'Connected · estimated trip progress':'Connected · driver GPS';
      const key=JSON.stringify([ride.id,ride.route.path]);
      const routeChanged = key !== pathKey;
      if(routeChanged){pathKey=key;path=G.route(ride.route.path);const points=path.points.map(p=>[p.lat,p.lng]);maps.forEach(view=>{view.line.setLatLngs(points);if(view.map!==adminMap&&points.length>1)view.map.fitBounds(L.latLngBounds(points),{padding:[36,36],maxZoom:16});});}
      if(!ride.location){hide();label('Waiting for driver GPS');positions.forEach(node=>{node.textContent='No location received yet.';});}
      updateFleet();
      if (routeChanged) fitAdminMap();
      animate(performance.now());
    }catch(_){cancelAnimationFrame(frame);connection.textContent=stopped?'Sign in again to open the trip monitor.':'Connection interrupted · showing last received position';if(!stopped)label('Last known position');}
    finally{busy=false;}
  }
  document.getElementById('previewRide').addEventListener('change',event=>{window.location.assign(config.previewUrl+(event.target.value?'/'+encodeURIComponent(event.target.value):''));});
  setInterval(poll,5000);document.addEventListener('visibilitychange',()=>{if(!document.hidden)poll();});
  window.addEventListener('pagehide',()=>{stopped=true;cancelAnimationFrame(frame);});
  window.addEventListener('pageshow',event=>{if(event.persisted)window.location.reload();});
  poll();
})();
