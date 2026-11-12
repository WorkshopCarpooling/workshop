(() => {
  'use strict';
  const config=window.tripNavigation,G=window.NavigationGeometry,get=id=>document.getElementById(id);
  if(!config||!G||!get('journeyNavigationMap'))return;
  let state=config.state,road=config.route,path=G.route(road.path||[]),follow=true,carPosition=null,frame=null,polling=false,confirming=false,stopped=false,lastFix=null,version=0;
  let routeTarget=road.targetKey??state.next?.key,routeOrigin=road.origin,routeFix=road.originCapturedAt,routeAttempt=0,testFraction=0;
  let testClock={serverNow:state.serverNow,at:performance.now()};
  const passenger=!state.isDriver,animated=()=>config.testTrip||state.continuousAnimation;
  const confirm=passenger?null:get('navigationConfirm'),complete=passenger?null:get('navigationComplete'),list=get('navigationStops');
  if(!window.L){get('navigationGps').textContent='The map could not load. Check your connection and reopen the journey map.';return;}
  const first=path.points[0]||state.stops[0];
  const map=L.map('journeyNavigationMap').setView([first.lat,first.lng],15);
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'© OpenStreetMap contributors',maxZoom:19}).addTo(map);
  const outline=L.polyline([],{color:'#fff',weight:9,opacity:.9}).addTo(map);
  const routeLine=L.polyline([],{color:road.road?'#2d9ed2':'#81947c',weight:5,dashArray:road.road?null:'7 9'}).addTo(map);
  const travelled=L.polyline([],{color:'#244b39',weight:5,opacity:.8}).addTo(map);
  const carBody=document.createElement('div');carBody.className='navigation-car-body';
  const carImage=document.createElement('img');carImage.src=config.carIconUrl;carImage.alt='';carImage.draggable=false;carBody.append(carImage);
  const car=L.marker([first.lat,first.lng],{title:'Driver car position',icon:L.divIcon({className:'navigation-car-marker',html:carBody,iconSize:[64,88],iconAnchor:[32,44]}),opacity:0,zIndexOffset:1000}).addTo(map);
  state.stops.forEach((stop,index)=>{
    if(stop.kind==='start'||stop.kind==='end')return;
    const icon=L.divIcon({className:'',html:`<div class="navigation-stop-icon ${stop.kind==='dropoff'?'dropoff':''}">${stop.kind==='dropoff'?'⚑':index}</div>`,iconSize:[29,29],iconAnchor:[14,14]});
    const text=document.createElement('span');text.textContent=stop.label;L.marker([stop.lat,stop.lng],{icon}).addTo(map).bindTooltip(text);
  });
  function drawRoute(){
    const points=path.points.map(p=>[p.lat,p.lng]);outline.setLatLngs(points);routeLine.setLatLngs(points);travelled.setLatLngs([]);
    if(routeLine.setStyle)routeLine.setStyle({color:road.road?'#2d9ed2':'#81947c',dashArray:road.road?null:'7 9'});
    get('navigationRoad').textContent=road.road?(passenger?'Your route to the destination':'Road route to next stop'):'Approximate route · road routing unavailable';
    if(points.length>1&&!carPosition)map.fitBounds(L.latLngBounds(points),{padding:[40,40],maxZoom:16});
    estimate();
  }
  function estimate(){
    get('navigationEstimateLabel').textContent=passenger?'To destination · estimate':config.testTrip?'Test arrival':state.next?.kind==='pickup'?'To pickup · estimate':'To drop-off · estimate';
    if(routeTarget!==state.next?.key||!path.points.length){get('navigationEstimate').textContent=state.allDroppedOff?'Arrived':'Updating…';return;}
    const projection=carPosition&&path.points.length>1?G.project(path,carPosition):null;
    const fraction=animated()?1-testFraction:projection&&projection.distance<75&&path.total?Math.max(0,1-projection.along/path.total):1;
    const distance=road.distanceMeters*fraction,minutes=Math.round(road.durationSeconds*fraction/60);
    get('navigationEstimate').textContent=animated()?`${Math.ceil((state.simulation?.durationSeconds||0)*fraction)} sec · ${(distance/1000).toFixed(1)} km`:`${minutes<1?'<1':minutes} min · ${(distance/1000).toFixed(1)} km`;
  }
  function place(point){
    carPosition=point;car.setOpacity(1);car.setLatLng([point.lat,point.lng]);const body=car.getElement()?.querySelector('.navigation-car-body');if(body)body.style.transform=`rotate(${point.heading||0}deg)`;
    if(follow)map.panTo([point.lat,point.lng],{animate:false});
    if(path.points.length>1&&routeTarget===state.next?.key){
      const projection=animated()?{along:path.total*testFraction,distance:0}:G.project(path,point),passed=path.points.filter((_p,i)=>path.cumulative[i]<projection.along);
      travelled.setLatLngs(projection.distance<75?[...passed.map(p=>[p.lat,p.lng]),[point.lat,point.lng]]:[]);
    }else travelled.setLatLngs([]);
    estimate();
  }
  function paintStops(){
    if(passenger){list.replaceChildren();const destination=state.stops.find(s=>s.kind==='dropoff')||state.next;if(destination){const row=document.createElement('li');row.textContent=destination.label;const small=document.createElement('small');small.textContent='Your destination';row.append(small);list.append(row);}return;}
    list.replaceChildren();for(const stop of state.stops){const row=document.createElement('li');row.textContent=stop.label;row.classList.toggle('done',stop.done);row.classList.toggle('current',stop.key===state.next?.key);const small=document.createElement('small');small.textContent=stop.done?'Done':stop.kind==='pickup'?'Passenger pickup':stop.kind==='dropoff'?'Passenger drop-off':'Journey point';row.append(small);list.append(row);}
  }
  function render(){
    if(state.status!=='in_progress'){endJourney();return;}
    carBody.setAttribute('data-carbon-level',state.carbon?.level||'unknown');
    const carbonLabel=get('navigationCarbon');
    if(carbonLabel)carbonLabel.textContent=Number.isFinite(state.carbon?.participant_g_per_km)?`${state.carbon.participant_g_per_km.toFixed(1)} g CO₂e / person on board / km · estimated`:'Carbon estimate unavailable · vehicle fuel profile needed';
    paintStops();
    const pickup=state.next?.kind==='pickup';
    get('navigationState').textContent=state.allDroppedOff?'Drop-off complete':state.isDriver?(pickup?'On the way to pickup':'On the way to drop-off'):(state.onBoard?'On the way to your destination':'Driver on the way to pickup');
    get('navigationNext').textContent=state.allDroppedOff?'Destination reached':state.next?.label||'Destination reached';
    get('navigationHint').textContent=state.allDroppedOff?'All passengers have been dropped off. Complete your trip.':state.isDriver?(pickup?'Head to the pickup. Confirm once the passenger is on board.':'Head to the destination. Confirm once the passenger is dropped off.'):(state.onBoard?'Your driver is heading to your destination. Shared trips may include other stops.':'Wait at your selected pickup. Your driver is on the way.');
    if(passenger){get('navigationState').textContent=state.allDroppedOff?'You have arrived':'On the way to your destination';get('navigationHint').textContent=state.allDroppedOff?'You have reached your destination. Waiting for your driver to finish the trip.':'Follow your car as it heads to your destination.';}
    if(confirm){confirm.hidden=!state.next||!['pickup','dropoff'].includes(state.next.kind);confirm.disabled=confirming||(config.testTrip&&!state.simulation?.arrived);confirm.textContent=config.testTrip&&!state.simulation?.arrived?'Driving to '+(pickup?'pickup…':'drop-off…'):pickup?'Passenger on board ✓':'Confirm passenger drop-off ✓';}
    if(complete)complete.hidden=!state.allDroppedOff;
    if(routeTarget!==state.next?.key){outline.setLatLngs([]);routeLine.setLatLngs([]);travelled.setLatLngs([]);get('navigationRoad').textContent=state.allDroppedOff?'Destination reached':'Updating route to next stop…';}
    estimate();
  }
  function endJourney(){
    if(stopped)return;stopped=true;cancelAnimationFrame(frame);car.setOpacity(0);if(confirm)confirm.hidden=true;if(complete)complete.hidden=true;
    list.replaceChildren();map.remove();get('navigationNext').textContent='Trip ended';get('navigationGps').textContent='Tracking closed. Returning to your rides…';
    window.location.replace(config.returnUrl);
  }
  function freshLocation(){const fix=state.location;return fix&&state.gps==='live'&&Date.now()/1000-fix.capturedAt<=30;}
  function updateTestLocation(){
    cancelAnimationFrame(frame);const simulation=state.simulation;if(!simulation||!path.points.length)return;
    const base=testClock.serverNow,received=testClock.at,start=simulation.startedAt,duration=simulation.durationSeconds;
    function step(time){
      if(stopped)return;
      testFraction=duration?Math.max(0,Math.min(1,(base+(time-received)/1000-start)/duration)):1;
      const arrived=testFraction>=1,changed=simulation.arrived!==arrived;simulation.arrived=arrived;
      if(changed)render();
      if(passenger){
        const finished=simulation.destinationReached??arrived;
        get('navigationMode').textContent=finished?'DESTINATION REACHED':'YOUR JOURNEY';
        get('navigationState').textContent=finished?'You have arrived':'Your journey is in progress';
        get('navigationGps').textContent=finished?'Your car has reached the destination. Waiting for the driver to complete the trip.':arrived?'Your driver has paused along the journey. The car will continue when the driver moves.':'Following the same car position as your driver.';
        get('navigationUpdated').textContent='Animation';
        get('navigationHint').textContent=finished?'You have reached your destination.':'Sit back and follow your journey.';
        place(path.points.length>1?G.at(path,path.total*testFraction):{...path.points[0],heading:0});
        if(!arrived)frame=requestAnimationFrame(step);return;
      }
      get('navigationMode').textContent='TRIP UJIAN · AUTOMATIC DRIVING';
      get('navigationGps').textContent=arrived?'Test car has arrived. Confirm this stop to continue.':'Simulated car is following the road. Device location is not used.';
      get('navigationUpdated').textContent='Simulation';
      if(state.allDroppedOff){get('navigationGps').textContent='All test stops confirmed. Finish the test trip.';}
      else get('navigationHint').textContent=arrived?'Car stopped. Confirm '+(state.next?.kind==='pickup'?'passenger on board.':'passenger drop-off.'):'Car driving automatically to '+(state.next?.kind==='pickup'?'pickup.':'drop-off.');
      place(path.points.length>1?G.at(path,path.total*testFraction):{...path.points[0],heading:0});
      if(!arrived)frame=requestAnimationFrame(step);
    }
    step(performance.now());
  }
  function updateLocation(){
    if(stopped)return;
    if(animated()){updateTestLocation();return;}
    const fix=state.location,fresh=freshLocation();
    get('navigationMode').textContent=fresh?'LIVE GPS':fix?'LAST KNOWN LOCATION':'WAITING FOR DRIVER GPS';
    get('navigationUpdated').textContent=fix?new Date(fix.capturedAt*1000).toLocaleTimeString():'—';
    const gpsState=state.isDriver&&window.isSecureContext===false?'insecure_context':state.gpsState;
    const problems={insecure_context:state.isDriver?'GPS is blocked on this HTTP address. Open this trip on localhost below, or use HTTPS.':'Your driver needs to open the trip on a GPS-capable address.',permission_denied:state.isDriver?'Location access is blocked. Allow Location for this site in your browser, then press Retry GPS below.':'Your driver needs to allow location access.',unavailable:state.isDriver?'Your device could not get a location. Check device location services and keep this page open.':'The driver device cannot get a location right now.',unsupported:'The driver browser does not support device location.',page_closed:'The driver page is closed or paused. Waiting for the driver to reopen the trip.'};
    const problem=problems[gpsState];
    if(problem&&!fresh)get('navigationMode').textContent=gpsState==='insecure_context'?'GPS BLOCKED · HTTP':gpsState==='permission_denied'?'LOCATION ACCESS BLOCKED':'DRIVER GPS UNAVAILABLE';
    get('navigationGps').textContent=fresh?`Receiving driver GPS · accuracy ±${Math.round(fix.accuracy)} m.`:problem?(problem+(fix?' Showing the last known position.':'')):fix?'GPS is stale or paused. Showing the last known position; movement is stopped.':'Waiting for the driver GPS. Allow location access on the driver device.';
    if(!fix){cancelAnimationFrame(frame);car.setOpacity(0);lastFix=null;return;}
    if(!fresh){cancelAnimationFrame(frame);place({...fix,heading:fix.heading||0});lastFix=null;return;}
    if(lastFix===fix.capturedAt)return;lastFix=fix.capturedAt;cancelAnimationFrame(frame);
    const usable=path.points.length>1&&routeTarget===state.next?.key;
    const target=usable?G.project(path,fix):null,from=usable&&carPosition?G.project(path,carPosition):target;
    const snap=usable&&road.road&&target.distance<75&&from.distance<75&&fix.accuracy<=100;
    const end=snap?G.at(path,target.along):{...fix,heading:fix.heading??(carPosition?G.bearing(carPosition,fix):0)};
    if(!carPosition||matchMedia('(prefers-reduced-motion: reduce)').matches){place(end);return;}
    const begin=carPosition,start=performance.now();
    const step=time=>{const t=Math.min(1,(time-start)/2800);place(snap?G.at(path,from.along+(target.along-from.along)*t):{lat:begin.lat+(end.lat-begin.lat)*t,lng:begin.lng+(end.lng-begin.lng)*t,heading:end.heading});if(t<1&&!stopped)frame=requestAnimationFrame(step);};
    frame=requestAnimationFrame(step);
  }
  function needsRoute(){
    if(animated())return false;
    if(!state.next||state.allDroppedOff)return false;
    // Limit road-provider calls while still rerouting after a stop or a deviation.
    if(Date.now()-routeAttempt<20000)return false;
    if(routeTarget!==state.next.key)return true;
    if(!freshLocation())return false;
    return !routeFix||!road.road||!routeOrigin||G.distance(routeOrigin,state.location)>100||(path.points.length>1&&G.project(path,state.location).distance>75);
  }
  async function refresh(){
    if(polling||confirming||stopped||document.hidden)return;polling=true;const current=version;
    updateLocation();const withRoute=needsRoute();if(withRoute)routeAttempt=Date.now();
    try{const url=config.feedUrl+(withRoute?(config.feedUrl.includes('?')?'&':'?')+'route=1':'');
      const response=await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin',signal:AbortSignal.timeout(10000)});
      if(current!==version||stopped)return;
      if(!response.ok){if([401,403,404,419].includes(response.status)){endJourney();return;}throw new Error();}
      const data=await response.json();if(current!==version||stopped)return;if(data.ended){endJourney();return;}state=data.navigation;testClock={serverNow:state.serverNow,at:performance.now()};
      if(data.route){road=data.route;path=G.route(road.path||[]);routeTarget=road.targetKey;routeOrigin=road.origin;routeFix=road.originCapturedAt;cancelAnimationFrame(frame);lastFix=null;drawRoute();}
      render();if(!stopped)updateLocation();
    }catch(_){cancelAnimationFrame(frame);get('navigationGps').textContent='Connection interrupted. Showing the last received position; reconnecting…';}
    finally{polling=false;}
  }
  get('navigationFollow').addEventListener('click',()=>{follow=!follow;get('navigationFollow').setAttribute('aria-pressed',String(follow));if(follow&&carPosition)map.panTo([carPosition.lat,carPosition.lng]);});
  map.on('dragstart',()=>{follow=false;get('navigationFollow').setAttribute('aria-pressed','false');});
  if(confirm)confirm.addEventListener('click',async()=>{
    if(confirming||!state.next||stopped||(config.testTrip&&!state.simulation?.arrived))return;confirming=true;version++;confirm.disabled=true;const key=state.next.key;get('navigationActionStatus').textContent='Saving this stop…';
    try{const response=await fetch(config.advanceUrl,{method:'POST',credentials:'same-origin',signal:AbortSignal.timeout(10000),headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':config.csrf},body:JSON.stringify({stop_key:key,...(config.testTrip?{test_run_id:state.simulation.runId}:{})})});const data=await response.json();if(!response.ok)throw new Error(data.errors?Object.values(data.errors).flat()[0]:data.message);if(data.ended){endJourney();return;}state=data.navigation;testClock={serverNow:state.serverNow,at:performance.now()};if(data.route){road=data.route;path=G.route(road.path||[]);routeTarget=road.targetKey;drawRoute();}get('navigationActionStatus').textContent='Stop confirmed. Continue to the next stop.';routeAttempt=0;}
    catch(error){get('navigationActionStatus').textContent=error.message||'Could not save the stop. Refresh before retrying.';}
    finally{version++;confirming=false;if(!stopped){render();if(config.testTrip)updateLocation();await refresh();}}
  });
  drawRoute();render();
  if(!stopped){updateLocation();refresh();setInterval(refresh,5000);document.addEventListener('visibilitychange',()=>{if(!document.hidden)refresh();});}
  window.addEventListener('pagehide',()=>{stopped=true;cancelAnimationFrame(frame);});
  window.addEventListener('pageshow',event=>{if(event.persisted)window.location.reload();});
})();
