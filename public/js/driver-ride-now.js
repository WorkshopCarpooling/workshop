(() => {
  'use strict';
  const config=window.driverRideNow,list=document.getElementById('nowDriverRequests');
  if(!config?.ready||!list)return;
  const get=id=>document.getElementById(id),status=get('nowDriverFeedStatus'),count=get('nowDriverRequestCount');
  const node=(tag,text,cls)=>{const n=document.createElement(tag);if(text!==undefined)n.textContent=text;if(cls)n.className=cls;return n;};
  const area=config.campusArea;
  let busy=false,lastSignature='',origin=area?{lat:Number(area.lat),lng:Number(area.lng)}:null,pool=null,map=null,marker=null,locationVersion=0;
  const locationStatus=get('poolLocationStatus');
  function inArea(lat,lng){
    if(!area)return true;
    const radians=v=>v*Math.PI/180,a=Math.sin(radians(lat-area.lat)/2)**2+Math.cos(radians(lat))*Math.cos(radians(area.lat))*Math.sin(radians(lng-area.lng)/2)**2;
    return 6371*2*Math.asin(Math.sqrt(Math.min(1,a)))<=Number(area.radius_km);
  }
  function setOrigin(lat,lng,message){
    if(!inArea(lat,lng)){locationStatus.textContent=`Choose a starting point within ${area.radius_km} km of UTeM Main Campus. Your previous UTeM pin is kept.`;return;}
    locationVersion++;origin={lat,lng};locationStatus.textContent=message;
    if(map){if(marker)marker.setLatLng([lat,lng]);else marker=L.marker([lat,lng]).addTo(map);map.setView([lat,lng],15);}
    lastSignature='';refresh();
  }
  get('poolUseLocation').addEventListener('click',()=>{
    const version=++locationVersion;
    if(!navigator.geolocation){locationStatus.textContent='Device location is unavailable. Choose your starting point on the map.';return;}
    locationStatus.textContent='Finding your current location…';
    navigator.geolocation.getCurrentPosition(p=>{if(version!==locationVersion)return;
      if(p.coords.accuracy>500){locationStatus.textContent='Location is too imprecise. Choose your starting point on the map.';return;}
      setOrigin(p.coords.latitude,p.coords.longitude,'Current location selected. Pickups will start from here.');
    },()=>{if(version===locationVersion)locationStatus.textContent='Location access unavailable. Choose your starting point on the map.';},{enableHighAccuracy:true,timeout:15000,maximumAge:30000});
  });
  get('poolChooseLocation').addEventListener('click',()=>{
    const element=get('poolStartMap');element.hidden=!element.hidden;get('poolChooseLocation').setAttribute('aria-expanded',String(!element.hidden));
    if(element.hidden)return;
    if(!window.L){locationStatus.textContent='Map is loading. Try again or use your current location.';return;}
    if(!map){map=L.map(element).setView(origin?[origin.lat,origin.lng]:[2.3139,102.3183],14);
      L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'© OpenStreetMap contributors',maxZoom:19}).addTo(map);
      if(area){const zone=L.circle([area.lat,area.lng],{radius:area.radius_km*1000,color:'#55784a',fillOpacity:.06}).addTo(map);map.setMaxBounds(zone.getBounds().pad(.15));}
      if(origin)marker=L.marker([origin.lat,origin.lng]).addTo(map);
      map.on('click',e=>setOrigin(e.latlng.lat,e.latlng.lng,'Starting pin selected. Pickups will start from this point.'));
    }map.invalidateSize();
  });
  function render(requests){
    const signature=JSON.stringify([requests,pool]);if(signature===lastSignature)return;
    const selections=new Map(Array.from(list.querySelectorAll('form')).map(f=>[f.elements.request_id.value,f.elements.vehicle_id.value]));
    lastSignature=signature;list.replaceChildren();count.textContent=`${requests.length} waiting`;
    get('poolSetup').hidden=!!pool;get('poolSummary').hidden=!pool;get('poolSchedule').hidden=!pool;
    if(pool){get('poolSummary').textContent=`Shared car to ${pool.destination} · ${pool.capacity-pool.remaining}/${pool.capacity} passenger seats booked · ${pool.remaining} left. ${pool.remaining?'Keep adding passengers, or start pickups now.':'Your car is full. Start pickups when ready.'}`;get('poolSchedule').href=pool.scheduleUrl;}
    for(const request of requests){
      const card=node('article',undefined,'now-driver-request');card.append(node('h3',`${request.pickup} → ${request.dropoff}`));
      const timing=request.scheduledFor?'Appointment · '+new Intl.DateTimeFormat('en-MY',{timeZone:'Asia/Kuala_Lumpur',day:'numeric',month:'short',hour:'numeric',minute:'2-digit'}).format(new Date(request.scheduledFor)):'Shared ride now';
      card.append(node('p',`${request.passenger} · ${request.seats} passenger${request.seats===1?'':'s'} · ${timing}`));
      card.append(node('p',`RM ${request.total} total · ${Number(request.distanceKm).toFixed(1)} km ${request.distanceSource==='google_routes_api'?'by road':'estimated'}`));
      if(request.pickupDistanceKm!=null)card.append(node('p',`Pickup ≈ ${request.pickupDistanceKm} km from your starting point (straight-line estimate)`));
      const form=node('form');form.method='post';form.action=config.acceptUrl;
      for(const [name,value] of Object.entries({_token:config.csrf,request_id:request.id,pool_origin_latitude:'',pool_origin_longitude:''})){const input=node('input');input.type='hidden';input.name=name;input.value=value;form.append(input);}
      const label=node('label','Your vehicle'),select=node('select');select.name='vehicle_id';select.required=true;
      for(const vehicle of config.vehicles){if(pool&&Number(vehicle.id)!==pool.vehicleId)continue;const option=node('option',vehicle.label);option.value=vehicle.id;select.append(option);}
      if(!pool&&selections.has(String(request.id)))select.value=selections.get(String(request.id));label.append(select);form.append(label);
      const accept=node('button',pool?'Add to shared car →':'Accept ride →','solid-button');accept.type='submit';form.append(accept);
      form.addEventListener('submit',event=>{
        if(form.dataset.submitting){event.preventDefault();return;}
        if(!pool&&!request.scheduledFor){
          if(origin){form.elements.pool_origin_latitude.value=origin.lat;form.elements.pool_origin_longitude.value=origin.lng;}
        }
        form.dataset.submitting='true';accept.disabled=true;accept.textContent='Accepting…';
      });card.append(form);list.append(card);
    }
  }
  async function refresh(){
    if(busy||document.hidden||list.querySelector('[data-submitting]'))return;busy=true;
    try{
      const url=new URL(config.feedUrl,location.href);if(origin){url.searchParams.set('latitude',origin.lat);url.searchParams.set('longitude',origin.lng);}
      const response=await fetch(url,{headers:{Accept:'application/json'}});if(!response.ok)throw new Error();
      const data=await response.json();pool=data.pool||null;render(data.requests);
      status.textContent=pool?(pool.remaining?'Only requests for your shared drop-off that fit the remaining seats appear below.':'Your car is full. Open your schedule to start pickups.'):(data.requests.length?'Press Accept on a request. Seats are counted automatically; requests update live.':'No requests available right now. New requests appear automatically when you are free.');
    }catch(_){list.replaceChildren();lastSignature='';count.textContent='—';status.textContent='Could not refresh requests. Reconnecting…';}
    finally{busy=false;}
  }
  refresh();setInterval(refresh,8000);document.addEventListener('visibilitychange',()=>{if(!document.hidden)refresh();});
})();
