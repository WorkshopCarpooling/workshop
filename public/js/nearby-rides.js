(() => {
  'use strict';
  const panel=document.getElementById('nearbyRideNotice'),list=document.getElementById('nearbyRideList');
  if(!panel||!list)return;
  const text=(tag,value)=>{const node=document.createElement(tag);node.textContent=value;return node;};
  let lastSignature='';
  function render(items){
    const signature=JSON.stringify(items);if(signature===lastSignature)return;lastSignature=signature;
    list.replaceChildren();panel.hidden=!items.length;
    for(const ride of items){
      const card=document.createElement('article');
      card.append(text('b',`${ride.pickup} → ${ride.destination}`));
      card.append(text('p',`${ride.driver} · ${ride.seats} passenger seat${ride.seats===1?'':'s'} available`));
      card.append(text('small',`Pickup detour ≈ ${ride.extraMinutes} min · ${ride.basis==='road_estimate'?'road-route estimate':'approximate pin-distance estimate'}`));
      list.append(card);
    }
  }
  window.renderNearbyRides=render;
  render(window.rideNow?.request?.status==='pending'?(window.rideNow.nearby||[]):[]);
})();
