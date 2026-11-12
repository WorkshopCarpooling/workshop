(() => {
  'use strict';
  const config=window.liveTripPreview,G=window.NavigationGeometry,L=window.L;
  if(!config||!G)return;
  const connection=document.getElementById('previewConnection'),journey=document.getElementById('previewJourney');
  const statusNodes=[...document.querySelectorAll('[data-preview-state]')],positions=[...document.querySelectorAll('[data-preview-position]')];
  if(!L){connection.textContent='Maps could not load. Reload to retry.';return;}
  const maps=['driver','passenger','admin'].map(role=>{
    const map=L.map(`preview-${role}-map`).setView([2.3139,102.3183],14);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'© OpenStreetMap contributors',maxZoom:19}).addTo(map);
    const body=document.createElement('div');body.className='preview-car-body';const image=document.createElement('img');image.src=config.carIconUrl;image.alt='';body.append(image);
    const marker=L.marker([2.3139,102.3183],{title:'Shared car position',opacity:0,icon:L.divIcon({className:'preview-car-marker',html:body,iconSize:[64,88],iconAnchor:[32,44]})}).addTo(map);
    return {map,marker,body,line:L.polyline([],{color:'#377d62',weight:5}).addTo(map)};
  });
  let snapshot=null,received=0,frame=null,busy=false,stopped=false,pathKey='',path=null;
  const hide=()=>{maps.forEach(view=>{view.marker.setOpacity(0);view.line.setLatLngs([]);});pathKey='';path=null;};
  const label=value=>statusNodes.forEach(node=>{node.textContent=value;});
  const place=(point,state)=>{
    maps.forEach(view=>{view.marker.setOpacity(1);view.marker.setLatLng([point.lat,point.lng]);view.body.style.transform=`rotate(${point.heading||0}deg)`;});
    positions.forEach(node=>{node.textContent=`${point.lat.toFixed(5)}, ${point.lng.toFixed(5)}`;});label(state);
  };
  function animate(time){
    if(stopped||!snapshot?.ride)return;
    const ride=snapshot.ride,simulation=ride.simulation;
    if(simulation&&path?.points.length){
      const elapsed=Math.max(0,snapshot.serverNow+(time-received)/1000-simulation.startedAt);
      const fraction=simulation.durationSeconds?Math.min(1,elapsed/simulation.durationSeconds):1;
      place(G.at(path,path.total*fraction),simulation.destinationReached?'Destination reached':fraction>=1?'Driver paused':'Moving together');
      if(fraction<1&&(time-received)<30000)frame=requestAnimationFrame(animate);
    }else if(ride.location)place(ride.location,ride.state==='stale'?'Last known position':'Live GPS');
  }
  async function poll(){
    if(stopped||busy||document.hidden)return;busy=true;
    try{
      const response=await fetch(config.feedUrl,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal:AbortSignal.timeout(10000)});
      if(!response.ok){if([401,403,404,419].includes(response.status)){stopped=true;hide();label('Preview unavailable');positions.forEach(node=>{node.textContent='Location unavailable';});journey.textContent='Trip details unavailable.';}throw new Error();}
      snapshot=await response.json();received=performance.now();cancelAnimationFrame(frame);
      if(!snapshot.ride){hide();label(snapshot.ended?'Trip completed':'Waiting for Start trip');connection.textContent=snapshot.ended?'Trip ended · all views closed':'Connected · waiting for an active trip';journey.textContent=snapshot.ended?'This trip has ended. Its record is available in History.':'The next trip appears automatically when the driver presses Start trip.';return;}
      const ride=snapshot.ride;journey.textContent=`Trip #${ride.id} · ${ride.origin} → ${ride.destination}`;
      maps.forEach(view=>view.body.setAttribute('data-carbon-level',ride.carbon?.level||'unknown'));
      if(Number.isFinite(ride.carbon?.participant_g_per_km))journey.textContent+=` · ${ride.carbon.participant_g_per_km.toFixed(1)} g CO₂e / person / km`;
      connection.textContent=ride.source==='simulation'?'Connected · shared simulation clock':'Connected · driver GPS';
      const key=JSON.stringify([ride.id,ride.route.path]);
      if(key!==pathKey){pathKey=key;path=G.route(ride.route.path);const points=path.points.map(p=>[p.lat,p.lng]);maps.forEach(view=>{view.line.setLatLngs(points);if(points.length>1)view.map.fitBounds(L.latLngBounds(points),{padding:[36,36],maxZoom:16});});}
      if(!ride.location){hide();label('Waiting for driver GPS');positions.forEach(node=>{node.textContent='No location received yet.';});return;}
      animate(performance.now());
    }catch(_){cancelAnimationFrame(frame);connection.textContent=stopped?'Admin access ended. Reopen the preview after signing in.':'Connection interrupted · showing last received position';if(!stopped)label('Last known position');}
    finally{busy=false;}
  }
  document.getElementById('previewRide').addEventListener('change',event=>{window.location.assign(config.previewUrl+(event.target.value?'/'+encodeURIComponent(event.target.value):''));});
  setInterval(poll,5000);document.addEventListener('visibilitychange',()=>{if(!document.hidden)poll();});
  window.addEventListener('pagehide',()=>{stopped=true;cancelAnimationFrame(frame);});
  window.addEventListener('pageshow',event=>{if(event.persisted)window.location.reload();});
  poll();
})();
