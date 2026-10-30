(() => {
  const picker = document.querySelector('[data-journey-picker]');
  if (!picker) return;
  const form = picker.closest('form');
  const status = picker.querySelector('[data-map-status]');
  const summary = picker.querySelector('[data-route-summary]');
  const container = picker.querySelector('[data-journey-map]');
  const autoPickup = picker.hasAttribute('data-auto-pickup');
  let active = autoPickup ? 'dropoff' : 'pickup', map, googleMode = false, path, routeVersion = 0, batching = false, queuedSubmit = false;
  const markers = {}, versions = {pickup: 0, dropoff: 0}, resolving = {pickup:false, dropoff:false};
  const field = name => form.elements.namedItem(name);
  const point = key => {
    const lat = field(`${key}_latitude`).value, lng = field(`${key}_longitude`).value;
    return lat !== '' && lng !== '' ? {lat: Number(lat), lng: Number(lng), label: field(`${key}_label`).value} : null;
  };
  const activate = key => {
    active = key;
    picker.querySelectorAll('[data-pin]').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.pin === key)));
    status.textContent = `Click the map to set ${key === 'pickup' ? 'pickup' : 'drop-off'}. Drag its marker to adjust.`;
  };
  async function preview() {
    const version = ++routeVersion;
    const a = point('pickup'), b = point('dropoff');
    if (path) { googleMode ? path.setMap(null) : path.remove(); path = null; }
    const directions = picker.querySelector('[data-directions]');
    directions.hidden = !(a && b);
    if (!a || !b) { summary.textContent = 'Choose both locations to preview the journey.'; return; }
    directions.href = window.buildGoogleMapsDirectionsUrl(a.lat, a.lng, b.lat, b.lng);
    summary.textContent = 'Calculating route…';
    try {
      const response = await window.computeGoogleRoute({originLat:a.lat, originLng:a.lng, originName:a.label, destLat:b.lat, destLng:b.lng, destName:b.label});
      if (version !== routeVersion) return;
      const road = response.source === 'google_routes_api';
      const seats = Number(field('seats_requested')?.value || 1);
      const price = autoPickup && response.fare ? ` · Student estimate RM ${response.fare.perSeat} / seat${seats > 1 ? ` · RM ${(Number(response.fare.perSeat)*seats).toFixed(2)} for ${seats} passengers` : ''}` : '';
      summary.textContent = `${response.distanceText} · ${response.durationText}${price} · ${road ? 'Google road route' : 'Approximate estimate; dashed line is not a road route'}`;
      const coords = response.path || [a, b];
      if (map) {
        if (googleMode) {
          path = new google.maps.Polyline({map, path: coords, strokeColor:'#244b39', strokeWeight:4, strokeOpacity:road ? 1 : 0, icons:road ? [] : [{icon:{path:'M 0,-1 0,1',strokeOpacity:1,scale:2},offset:'0',repeat:'14px'}]});
        } else {
          path = L.polyline(coords.map(p => [p.lat,p.lng]), {color:'#244b39', dashArray:road ? null : '7 8'}).addTo(map);
        }
      }
    } catch (error) { if (version === routeVersion) summary.textContent = 'Route preview unavailable. Your selected locations are retained; open driving directions to check the road route.'; }
  }
  async function resolveName(key, lat, lng, version) {
    try {
      const url = new URL(window.locationSearchUrl, location.href);
      url.pathname = url.pathname.replace(/\/search\/?$/, '/reverse'); url.search = '';
      url.searchParams.set('lat', lat); url.searchParams.set('lng', lng);
      const response = await fetch(url, {headers:{Accept:'application/json'}});
      if (!response.ok) throw new Error();
      const data = await response.json();
      if (version !== versions[key]) return;
      if (typeof data.label !== 'string' || !data.label.trim()) throw new Error();
      field(`${key}_label`).value = data.label.trim().slice(0,180);
      picker.querySelector(`[data-coordinates="${key}"]`).textContent = `${field(`${key}_label`).value} · Pin selected`;
      if (autoPickup && key === 'pickup') picker.querySelector('[data-gps-status]').textContent = `${field('pickup_label').value} · Pickup ready.`;
    } catch (_) {
      if (version !== versions[key]) return;
      picker.querySelector(`[data-coordinates="${key}"]`).textContent = 'Pin selected. Place name unavailable; choose a nearby named search result if needed.';
    } finally {
      if (version === versions[key]) {
        resolving[key] = false;
        form.dispatchEvent(new CustomEvent('journeychange', {bubbles:true}));
        preview();
        if (queuedSubmit && !resolving.pickup && !resolving.dropoff) { queuedSubmit = false; form.requestSubmit(); }
      }
    }
  }
  function setPoint(key, lat, lng, label, fit = true, restoring = false) {
    queuedSubmit = false;
    if (!restoring) versions[key]++;
    const needsName = !label || /^(My current location|(?:Pickup|Drop-off) pin\b|Pinned (?:pickup|drop-off)\b)/i.test(label) || /^\(?\s*-?\d+(?:\.\d+)?\s*,\s*-?\d+(?:\.\d+)?\s*\)?$/.test(label);
    resolving[key] = needsName;
    field(`${key}_latitude`).value = Number(lat).toFixed(7);
    field(`${key}_longitude`).value = Number(lng).toFixed(7);
    field(`${key}_label`).value = (needsName ? `Pinned ${key === 'pickup' ? 'pickup' : 'drop-off'}` : label).slice(0,180);
    field(`${key}_label`).setCustomValidity('');
    picker.querySelector(`[data-coordinates="${key}"]`).textContent = needsName ? 'Finding the place name…' : `${field(`${key}_label`).value} · Location selected`;
    picker.querySelector(`[data-results="${key}"]`).replaceChildren();
    if (map) {
      if (googleMode) {
        if (!markers[key]) {
          markers[key] = new google.maps.Marker({map, draggable:true, label:key === 'pickup' ? 'P' : 'D'});
          markers[key].addListener('dragend', e => setPoint(key,e.latLng.lat(),e.latLng.lng()));
        }
        markers[key].setPosition({lat:Number(lat),lng:Number(lng)});
      } else {
        if (!markers[key]) {
          markers[key] = L.marker([lat,lng], {draggable:true, icon:L.divIcon({className:'', html:`<span class="journey-marker ${key}">${key === 'pickup' ? 'P' : 'D'}</span>`, iconSize:[30,30],iconAnchor:[15,15]})}).addTo(map);
          markers[key].on('dragend', () => { const p=markers[key].getLatLng(); setPoint(key,p.lat,p.lng); });
        } else markers[key].setLatLng([lat,lng]);
      }
      if (fit) {
        const a=point('pickup'), b=point('dropoff');
        if (a && b) {
          if(googleMode) { const bounds=new google.maps.LatLngBounds();bounds.extend(a);bounds.extend(b);map.fitBounds(bounds,50); }
          else map.fitBounds([[a.lat,a.lng],[b.lat,b.lng]],{padding:[40,40],maxZoom:15});
        } else googleMode ? map.setCenter({lat:Number(lat),lng:Number(lng)}) : map.setView([lat,lng],14);
      }
    }
    status.textContent = `${key === 'pickup' ? 'Pickup' : 'Drop-off'} selected. ${point(key === 'pickup' ? 'dropoff' : 'pickup') ? 'Both locations are ready.' : 'Select the other location next.'}`;
    if (autoPickup && key === 'pickup') {
      picker.querySelector('[data-gps-status]').textContent = `${field('pickup_label').value} · Pickup ready. You can drag the pin to a safe meeting point.`;
      activate('dropoff');
    }
    if (!batching) {
      form.dispatchEvent(new CustomEvent('journeychange', {bubbles:true}));
      preview();
    }
    if (needsName) resolveName(key, lat, lng, versions[key]);
  }
  async function search(key) {
    queuedSubmit=false;resolving[key]=false;
    const query = field(`${key}_label`).value.trim(), version=++versions[key];
    if(query.length<2) { status.textContent='Enter at least two characters to search.';return; }
    status.textContent='Searching places…';
    const results=picker.querySelector(`[data-results="${key}"]`);results.replaceChildren();
    try {
      const url=new URL(window.locationSearchUrl,location.href);url.searchParams.set('q',query);
      const response=await fetch(url,{headers:{Accept:'application/json'}});
      if(!response.ok) throw new Error();
      const data=await response.json();if(version!==versions[key])return;
      for(const place of data.results) {
        const button=document.createElement('button');button.type='button';button.textContent=place.label;
        button.addEventListener('click',()=>setPoint(key,place.lat,place.lng,place.label));results.append(button);
      }
      status.textContent=data.results.length ? 'Choose a search result to confirm the location.' : 'No results found. Try a nearby landmark or pin the location on the map.';
    } catch(error) { if(version===versions[key]) status.textContent='Place search is unavailable. You can still select a pin on the map.'; }
  }
  for(const key of ['pickup','dropoff']) {
    picker.querySelector(`[data-search="${key}"]`).addEventListener('click',()=>search(key));
    picker.querySelector(`[data-pin="${key}"]`).addEventListener('click',()=>activate(key));
    field(`${key}_label`).addEventListener('input',()=>{
      queuedSubmit=false;versions[key]++;resolving[key]=false;field(`${key}_latitude`).value='';field(`${key}_longitude`).value='';
      if(markers[key]) { googleMode ? markers[key].setMap(null) : markers[key].remove();delete markers[key]; }
      field(`${key}_label`).setCustomValidity('');
      picker.querySelector(`[data-results="${key}"]`).replaceChildren();
      picker.querySelector(`[data-coordinates="${key}"]`).textContent='Search and select a result, or place a pin to confirm.';
      if(autoPickup && key === 'pickup') picker.querySelector('[data-gps-status]').textContent='Choose a pickup result or pin to confirm the meeting point.';
      preview();form.dispatchEvent(new CustomEvent('journeychange',{bubbles:true}));
    });
    field(`${key}_label`).addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();search(key);}});
  }
  form.addEventListener('submit',e=>{
    if (resolving.pickup || resolving.dropoff) {
      queuedSubmit=true;e.preventDefault();e.stopImmediatePropagation();status.textContent='Finding the place name before starting your search…';return;
    }
    for(const key of ['pickup','dropoff']) if(!point(key)) {
      if(autoPickup && key === 'pickup') showPickup(true);
      e.preventDefault();e.stopImmediatePropagation();field(`${key}_label`).setCustomValidity('Select a search result or pin this location on the map.');field(`${key}_label`).reportValidity();return;
    }
  },true);
  picker.querySelector('[data-swap-locations]')?.addEventListener('click',()=>{
    const a=point('pickup'),b=point('dropoff');
    if(!a||!b){status.textContent='Select both locations before swapping them.';return;}
    batching=true;setPoint('pickup',b.lat,b.lng,b.label,false);setPoint('dropoff',a.lat,a.lng,a.label,true);batching=false;
    form.dispatchEvent(new CustomEvent('journeychange',{bubbles:true}));preview();
  });
  function showPickup(show) {
    const manual = picker.querySelector('[data-pickup-manual]');
    if(!manual)return;
    manual.hidden=!show;
    picker.querySelector('[data-edit-pickup]').setAttribute('aria-expanded',String(show));
    if(show)activate('pickup');
  }
  picker.querySelector('[data-edit-pickup]')?.addEventListener('click',()=>showPickup(picker.querySelector('[data-pickup-manual]').hidden));
  function locatePickup() {
    const gps=picker.querySelector('[data-gps-status]'),button=picker.querySelector('[data-auto-locate]');
    const fail = message => { button.disabled=false;gps.textContent=message;showPickup(true); };
    const localLink=picker.querySelector('[data-location-access]'), localHelp=picker.querySelector('[data-location-help]');
    if(!window.isSecureContext){
      if(localLink)localLink.hidden=false;
      if(localHelp)localHelp.hidden=false;
      fail(localLink ? 'This address blocks device location. Open the location-enabled page below, or choose your pickup manually.' : 'Automatic pickup needs a secure connection (HTTPS or localhost). Choose your pickup pin below.');return;
    }
    if(!navigator.geolocation){fail('Device location is unavailable. Choose your pickup pin or search for a nearby landmark.');return;}
    const version=versions.pickup;
    button.disabled=true;gps.textContent='Detecting your pickup… Allow location access when your browser asks.';
    navigator.geolocation.getCurrentPosition(position=>{
      button.disabled=false;
      if(version!==versions.pickup)return;
      setPoint('pickup',position.coords.latitude,position.coords.longitude,'My current location');
      button.textContent='Refresh current location';
      showPickup(false);
      gps.textContent=`Current location selected · accuracy about ${Math.round(position.coords.accuracy)} m. Drag the pickup pin if needed.`;
    },error=>{if(version!==versions.pickup){button.disabled=false;return;}fail(error.code===1?'Location access declined. Choose a pickup pin or search for a nearby landmark.':'Could not detect your location. Choose a pickup pin or search for a nearby landmark.');},{enableHighAccuracy:true,timeout:15000,maximumAge:30000});
  }
  picker.querySelector('[data-auto-locate]')?.addEventListener('click',locatePickup);
  field('seats_requested')?.addEventListener('change',preview);
  picker.querySelector('[data-use-location]')?.addEventListener('click',event=>{
    const key=active,button=event.currentTarget;
    if(!window.isSecureContext){status.textContent='Device location needs HTTPS on your phone. You can still search or place a map pin.';return;}
    if(!navigator.geolocation){status.textContent='Device location is unavailable. Search or select a pin on the map.';return;}
    button.disabled=true;status.textContent='Waiting for your device location permission…';
    navigator.geolocation.getCurrentPosition(position=>{
      button.disabled=false;setPoint(key,position.coords.latitude,position.coords.longitude);
      status.textContent=`Device location selected for ${key==='pickup'?'pickup':'drop-off'}. Check the pin and adjust it if needed.`;
    },error=>{button.disabled=false;status.textContent=error.code===1?'Location permission was declined. Search or place a pin instead.':'Could not get your location. Search or place a pin instead.';},{enableHighAccuracy:false,timeout:10000,maximumAge:60000});
  });
  window.journeyPicker = {point,setPoint};
  if(autoPickup && point('pickup')) picker.querySelector('[data-gps-status]').textContent='Your saved pickup is selected. Refresh your location or adjust its pin if needed.';
  if(autoPickup && (!point('pickup') || point('pickup').label==='My current location')) {
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',locatePickup,{once:true});
    else locatePickup();
  }
  (async()=>{
    try {
      // Prefer the key-free picker even when Google road routing is configured.
      if(!window.L && window.googleMapsApiKey) {
        await window.loadGoogleMaps();googleMode=true;
        map=new google.maps.Map(container,{center:{lat:2.2707,lng:102.2856},zoom:12,mapTypeControl:false,streetViewControl:false,clickableIcons:false,gestureHandling:'cooperative'});
        map.addListener('click',e=>setPoint(active,e.latLng.lat(),e.latLng.lng(),null,false));
      } else {
        if(!window.L) throw new Error();
        map=L.map(container).setView([2.2707,102.2856],12);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'}).addTo(map);
        map.on('click',e=>setPoint(active,e.latlng.lat,e.latlng.lng,null,false));
      }
      for(const key of ['pickup','dropoff']) { const p=point(key);if(p)setPoint(key,p.lat,p.lng,p.label,true,true); }
    } catch(error) { status.textContent='Map could not load. Search for each place and choose a result to continue.'; }
  })();
})();
