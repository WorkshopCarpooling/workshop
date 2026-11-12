(() => {
  'use strict';
  const config=window.driverWaiting, form=document.getElementById('driverWaitingForm');
  if(!config||!form)return;
  const get=id=>document.getElementById(id),message=get('driverWaitingMessage');
  let route=config.waiting, busy=false;
  const headers={Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':config.csrf};
  function render(){
    const online=!!route?.online, booked=!!route?.hasPassengers||config.busy;
    get('driverWaitingBadge').textContent=online?'Online · waiting':'Offline';
    get('driverWaitingBadge').classList.toggle('is-online',online);
    form.hidden=online||booked; form.inert=form.hidden;
    get('driverWaitingSummary').hidden=!route;
    if(route){
      get('driverWaitingJourney').textContent=`${route.pickup} → ${route.destination}`;
      get('driverWaitingState').textContent=online?'Matching nearby pickups for your destination. You choose which requests to accept.':(route.canResume?'New matching is paused. Resume when you are ready.':'This route is closed or full. Manage accepted passengers in My schedule.');
    }
    get('driverWaitingStop').hidden=!online;
    get('driverWaitingResume').hidden=online||!route?.canResume;
    for(const id of ['driverWaitingOpen','driverWaitingStop','driverWaitingResume'])get(id).disabled=busy;
    window.dispatchEvent(new Event('driverwaitingchange'));
  }
  async function post(url,body){
    const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),15000);
    try{
      const response=await fetch(url,{method:'POST',headers,body:JSON.stringify(body),signal:controller.signal});
      const data=await response.json();
      if(!response.ok)throw new Error(data.errors?Object.values(data.errors).flat()[0]:data.message||'Could not update your waiting route.');
      return data;
    }finally{clearTimeout(timer);}
  }
  form.addEventListener('submit',async event=>{
    event.preventDefault();if(busy)return;busy=true;render();message.textContent='Opening your route…';
    try{const data=await post(config.openUrl,Object.fromEntries(new FormData(form)));route=data.waiting;message.textContent='Your route is open. Keep this page visible to receive matching requests.';}
    catch(error){message.textContent=error.name==='AbortError'?'The connection timed out. Refresh to check whether your route opened.':error.message;}
    finally{busy=false;render();}
  });
  async function update(kind){
    if(busy||!route)return;
    if(kind==='heartbeat'&&(!route.online||document.hidden))return;
    busy=true;const previous=route;render();
    try{
      const data=await post(kind==='close'?config.closeUrl:config.heartbeatUrl,{id:previous.id,version:previous.version,...(kind==='resume'?{resume:true}:{})});
      if(data.waiting)route=data.waiting;
      else route={...previous,online:false,canResume:kind==='close'&&previous.canResume};
      if(kind==='close')message.textContent='You are offline. Accepted bookings are unchanged.';
      else if(!route.online)message.textContent='Matching has stopped. Refresh to check your route, or open it again.';
      else if(kind==='resume')message.textContent='Matching resumed. Keep this page visible while waiting.';
    }catch(error){
      if(kind==='heartbeat'&&Date.parse(previous.expiresAt)<=Date.now())route={...previous,online:false,canResume:true};
      message.textContent='Could not update availability. Your route disappears from nearby matches when its online time expires. Retry or refresh.';
    }finally{busy=false;render();}
  }
  get('driverWaitingStop').addEventListener('click',()=>update('close'));
  get('driverWaitingResume').addEventListener('click',()=>update('resume'));
  document.addEventListener('visibilitychange',()=>{if(!document.hidden)update('heartbeat');});
  setInterval(()=>update('heartbeat'),30000);
  render();
})();
