(() => {
  'use strict';
  const config=window.tripNavigation,G=window.NavigationGeometry,get=id=>document.getElementById(id);
  if(!config||!G||!get('journeyNavigationMap'))return;
  const road=config.route,path=G.route(road.path||[]);let state=config.state,follow=true,carPosition=null,frame=null,polling=false,confirming=false,stopped=false,lastFix=null;
  const confirm=get('navigationConfirm'),complete=get('navigationComplete'),list=get('navigationStops');
  if(!window.L||path.points.length<2){get('navigationGps').textContent='The map could not load. Check your connection and reopen the journey map.';return;}
  const map=L.map('journeyNavigationMap').setView([path.points[0].lat,path.points[0].lng],15);
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'© OpenStreetMap contributors',maxZoom:19}).addTo(map);
  const latlngs=path.points.map(p=>[p.lat,p.lng]);
  L.polyline(latlngs,{color:'#fff',weight:9,opacity:.9}).addTo(map);
  L.polyline(latlngs,{color:road.road?'#2d9ed2':'#81947c',weight:5,dashArray:road.road?null:'7 9'}).addTo(map);
  const travelled=L.polyline([],{color:'#244b39',weight:5,opacity:.8}).addTo(map);
  map.fitBounds(L.latLngBounds(latlngs),{padding:[30,30],maxZoom:16});
  const carBody=document.createElement('div');carBody.className='navigation-car-body';
  const carImage=document.createElement('img');carImage.src=config.carIconUrl;carImage.alt='';carImage.draggable=false;carBody.append(carImage);
  const car=L.marker(latlngs[0],{title:'Driver car position',icon:L.divIcon({className:'navigation-car-marker',html:carBody,iconSize:[42,48],iconAnchor:[21,24]}),opacity:0,zIndexOffset:1000}).addTo(map);
  state.stops.forEach((stop,index)=>{
    if(stop.kind==='start'||stop.kind==='end')return;
    const icon=L.divIcon({className:'',html:`<div class="navigation-stop-icon ${stop.kind==='dropoff'?'dropoff':''}">${stop.kind==='dropoff'?'⚑':index}</div>`,iconSize:[29,29],iconAnchor:[14,14]});
    const text=document.createElement('span');text.textContent=stop.label;L.marker([stop.lat,stop.lng],{icon}).addTo(map).bindTooltip(text);
  });
  get('navigationRoad').textContent=road.road?`${road.source==='osrm'?'OpenStreetMap / OSRM':'Google'} road route`:'Approximate route · road routing unavailable';
  get('navigationEstimate').textContent=`${Math.max(1,Math.round(road.durationSeconds/60))} min · ${(road.distanceMeters/1000).toFixed(1)} km`;
  function place(point){
    carPosition=point;car.setOpacity(1);car.setLatLng([point.lat,point.lng]);const body=car.getElement()?.querySelector('.navigation-car-body');if(body)body.style.transform=`rotate(${point.heading||0}deg)`;
    if(follow)map.panTo([point.lat,point.lng],{animate:false});
    const projection=G.project(path,point),passed=path.points.filter((_p,i)=>path.cumulative[i]<projection.along);travelled.setLatLngs(projection.distance<100?[...passed.map(p=>[p.lat,p.lng]),[point.lat,point.lng]]:[]);
  }
  function paintStops(){
    list.replaceChildren();for(const stop of state.stops){const row=document.createElement('li');row.textContent=stop.label;row.classList.toggle('done',stop.done);row.classList.toggle('current',stop.key===state.next?.key);const small=document.createElement('small');small.textContent=stop.done?'Done':stop.kind==='pickup'?'Passenger pickup':stop.kind==='dropoff'?'Passenger drop-off':'Journey point';row.append(small);list.append(row);}
  }
  function render(){
    if(state.status!=='in_progress'){endJourney();return;}
    paintStops();const active=state.status==='in_progress';
    get('navigationState').textContent=state.onBoard?'On board · shared journey':'Trip in progress';
    get('navigationNext').textContent=state.status==='completed'?'Destination reached':state.next?.label||'Destination reached';
    get('navigationHint').textContent=state.isDriver?'Follow the ordered stops. Confirm when passengers are on board or dropped off.':state.onBoard?'You are on board. Your driver may collect other shared passengers.':'Wait at your selected pickup. Your driver will confirm when you are on board.';
    if(confirm){confirm.hidden=!active||!state.next||!['pickup','dropoff'].includes(state.next.kind);confirm.disabled=confirming;confirm.textContent=state.next?.kind==='pickup'?'Passenger on board ✓':'Confirm passenger drop-off ✓';}
    if(complete)complete.hidden=!active||!state.allDroppedOff;
  }
  function endJourney(){
    if(stopped)return;stopped=true;cancelAnimationFrame(frame);car.setOpacity(0);if(confirm)confirm.hidden=true;if(complete)complete.hidden=true;
    list.replaceChildren();map.remove();get('navigationNext').textContent='Trip ended';get('navigationGps').textContent='Tracking closed. Returning to your rides…';
    window.location.replace(config.returnUrl);
  }
  function updateLocation(){
    if(state.status!=='in_progress')return;
    const fix=state.location;
    const fresh=fix&&state.gps==='live'&&Date.now()/1000-fix.capturedAt<=30;
    get('navigationMode').textContent=fresh?'LIVE GPS':fix?'LAST KNOWN LOCATION':'WAITING FOR DRIVER GPS';
    get('navigationUpdated').textContent=fix?new Date(fix.capturedAt*1000).toLocaleTimeString():'—';
    get('navigationGps').textContent=fresh?`Receiving driver GPS · accuracy ±${Math.round(fix.accuracy)} m.`:fix?'GPS is stale or paused. Showing the last known position; movement is stopped.':'Waiting for the driver GPS. Allow location access on the driver device.';
    if(!fix){cancelAnimationFrame(frame);car.setOpacity(0);lastFix=null;return;}
    if(!fresh){cancelAnimationFrame(frame);place({...fix,heading:fix.heading||0});lastFix=null;return;}
    if(lastFix===fix.capturedAt)return;lastFix=fix.capturedAt;cancelAnimationFrame(frame);
    const target=G.project(path,fix),from=carPosition?G.project(path,carPosition):target;
    const snap=road.road&&target.distance<75&&from.distance<75&&fix.accuracy<=100;
    const end=snap?G.at(path,target.along):{...fix,heading:fix.heading??(carPosition?G.bearing(carPosition,fix):0)};
    if(!carPosition||matchMedia('(prefers-reduced-motion: reduce)').matches){place(end);return;}
    const begin=carPosition,start=performance.now();
    const step=time=>{const t=Math.min(1,(time-start)/2800);place(snap?G.at(path,from.along+(target.along-from.along)*t):{lat:begin.lat+(end.lat-begin.lat)*t,lng:begin.lng+(end.lng-begin.lng)*t,heading:end.heading});if(t<1&&!stopped)frame=requestAnimationFrame(step);};
    frame=requestAnimationFrame(step);
  }
  async function refresh(){
    if(polling||stopped||document.hidden)return;polling=true;
    try{const response=await fetch(config.feedUrl,{headers:{Accept:'application/json'},credentials:'same-origin',signal:AbortSignal.timeout(10000)});
      if(!response.ok){if([401,403,404,419].includes(response.status)){endJourney();return;}throw new Error();}
      const data=await response.json();if(data.ended){endJourney();return;}state=data.navigation;render();if(!stopped)updateLocation();
    }catch(_){cancelAnimationFrame(frame);get('navigationGps').textContent=stopped?'Your access to this journey ended. Sign in and check your booking.':'Connection interrupted. Showing the last received position; reconnecting…';}
    finally{polling=false;}
  }
  get('navigationFollow').addEventListener('click',()=>{follow=!follow;get('navigationFollow').setAttribute('aria-pressed',String(follow));if(follow&&carPosition)map.panTo([carPosition.lat,carPosition.lng]);});
  map.on('dragstart',()=>{follow=false;get('navigationFollow').setAttribute('aria-pressed','false');});
  if(confirm)confirm.addEventListener('click',async()=>{
    if(confirming||!state.next)return;confirming=true;confirm.disabled=true;const key=state.next.key;get('navigationActionStatus').textContent='Saving this stop…';
    try{const response=await fetch(config.advanceUrl,{method:'POST',credentials:'same-origin',signal:AbortSignal.timeout(10000),headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':config.csrf},body:JSON.stringify({stop_key:key})});const data=await response.json();if(!response.ok)throw new Error(data.errors?Object.values(data.errors).flat()[0]:data.message);if(data.ended){endJourney();return;}state=data.navigation;get('navigationActionStatus').textContent='Stop confirmed. Continue to the next stop.';}
    catch(error){get('navigationActionStatus').textContent=error.message||'Could not save the stop. Refresh before retrying.';await refresh();}
    finally{confirming=false;if(!stopped)render();}
  });
  render();
  if(!stopped){updateLocation();refresh();setInterval(refresh,5000);document.addEventListener('visibilitychange',()=>{if(!document.hidden)refresh();});}
  window.addEventListener('pagehide',()=>{stopped=true;cancelAnimationFrame(frame);});
  window.addEventListener('pageshow',event=>{if(event.persisted)window.location.reload();});
})();
