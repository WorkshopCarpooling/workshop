(() => {
  'use strict';
  const root=document.getElementById('live-drivers');
  if(!root)return;
  const dialog=document.getElementById('tracking-carbon-dialog');
  const title=document.getElementById('tracking-carbon-title');
  const steps=document.getElementById('tracking-carbon-steps');
  const close=document.getElementById('tracking-carbon-close');
  const palette={low:'#3c9366',medium:'#ce9929',high:'#cb5653',unknown:'#85918a'};
  const labels={low:'Low / Rendah',medium:'Medium / Sederhana',high:'High / Tinggi',unknown:'Unavailable / Belum tersedia'};
  let current=new Map(),openId=null,lastContent='';
  const el=(tag,text,className)=>{const node=document.createElement(tag);node.textContent=text;if(className)node.className=className;return node;};
  const carbonOf=ride=>{
    const value=ride.carbon;
    const valid=value?.status==='estimated'&&Number.isFinite(value.participant_g_per_km)&&value.participant_g_per_km>0
      &&Number.isFinite(value.vehicle_g_per_km)&&value.vehicle_g_per_km>0&&Number.isInteger(value.occupants)&&value.occupants>=1
      &&Number.isFinite(value.litres_per_100km)&&value.litres_per_100km>0&&Number.isFinite(value.fuel_factor)&&value.fuel_factor>0;
    return valid&&['low','medium','high'].includes(value.level)?value:{status:'unavailable',level:'unknown',
      reason:value?.reason||'A supported vehicle fuel profile is needed to estimate carbon.',occupants:value?.occupants,passenger_seats:value?.passenger_seats};
  };
  const plannedOf=c=>{
    const p=c.planned;
    return p&&[p.distance_km,p.fuel_litres,p.vehicle_kg,p.participant_kg].every(Number.isFinite)&&p.distance_km>0&&p.vehicle_kg>=0?p:null;
  };
  const addStep=(label,calculation,note)=>{
    const row=el('div','','live-carbon-step');row.append(el('b',label),el('p',calculation));
    if(note)row.append(el('small',note));steps.append(row);
  };
  function fillDialog() {
    if(openId===null||!steps)return;
    const ride=current.get(openId);
    const content=JSON.stringify(ride?[ride.id,ride.driver,ride.carbon]:null);
    if(lastContent===content)return;lastContent=content;
    steps.replaceChildren();
    if(!ride){title.textContent='Trip no longer active';addStep('Completed-trip reports','This trip is no longer in the live feed.','Open Carbon reports to review available completed-trip estimates.');return;}
    title.textContent=`${ride.driver} · Carbon calculation`;
    const c=carbonOf(ride),p=plannedOf(c);
    if(c.status!=='estimated'){addStep('Estimate unavailable',c.reason,'Grey means missing information, not zero emissions.');return;}
    addStep('Vehicle profile',`${c.fuel_label||c.fuel_type} · ${c.litres_per_100km.toFixed(1)} L/100 km · ${c.fuel_factor.toFixed(3)} kg CO₂e/L`,
      `${c.passenger_seats} passenger(s) + 1 driver = ${c.occupants} on board now.`);
    if(p){
      addStep('1 · Estimated fuel used',`${p.distance_km.toFixed(3)} km × ${c.litres_per_100km.toFixed(1)} ÷ 100 ≈ ${p.fuel_litres.toFixed(6)} L`,p.distance_method);
      addStep('2 · Whole vehicle footprint',`${p.fuel_litres.toFixed(6)} L × ${c.fuel_factor.toFixed(3)} ≈ ${p.vehicle_kg.toFixed(4)} kg CO₂e`);
      addStep('3 · Planned footprint per booked participant',`${p.participant_kg.toFixed(4)} kg CO₂e per booked participant`,
        'The planned trip footprint is shared across the driver and all accepted seats.');
    }else addStep('Planned trip total unavailable','Usable whole-route distance is not available yet.','The per-person rate and car colour are still available.');
    addStep('Vehicle emissions per kilometre',`${c.litres_per_100km.toFixed(1)} ÷ 100 × ${c.fuel_factor.toFixed(3)} × 1,000 ≈ ${c.vehicle_g_per_km.toFixed(2)} g CO₂e / km`);
    addStep('Live emissions per person on board per kilometre',`${c.vehicle_g_per_km.toFixed(2)} g/km ÷ ${c.occupants} ≈ ${c.participant_g_per_km.toFixed(2)} g CO₂e / person / km`);
    const {low_max:low=60,medium_max:medium=100}=c.thresholds||{};
    addStep('Car colour · estimated carbon intensity',labels[c.level],
      `Local pilot thresholds: green ≤${low}; yellow >${low} to ${medium}; red >${medium} g CO₂e / person / km.`);
    if(c.source)addStep('Source',c.source,`Method ${c.method} · ${c.allocation_method}`);
  }
  function open(id){openId=id;lastContent='';fillDialog();if(dialog&&!dialog.open)dialog.showModal();}
  close?.addEventListener('click',()=>dialog.close());
  dialog?.addEventListener('close',()=>{openId=null;lastContent='';});
  window.LiveCarbon={
    paintMarker(ride,marker){
      const body=marker?.querySelector('.live-car');if(!body)return;
      const c=carbonOf(ride);marker.dataset.carbonLevel=c.level;
      body.style.setProperty('--live-carbon-colour',palette[c.level]);
      if(root.dataset.carIconUrl)body.style.setProperty('--live-carbon-mask',`url(${JSON.stringify(root.dataset.carIconUrl)})`);
    },
    renderCard(card,ride){
      const c=carbonOf(ride),p=plannedOf(c),box=el('div','','live-carbon-card');box.dataset.level=c.level;
      box.style.setProperty('--live-carbon-colour',palette[c.level]);
      box.append(el('div',labels[c.level]+' · estimated carbon intensity','live-carbon-level'));
      if(c.status==='estimated'){
        const value=el('div',c.participant_g_per_km.toFixed(1),'live-carbon-value');value.append(el('span','g CO₂e / participant / km'));box.append(value);
        box.append(el('small',`On board now · ${c.passenger_seats} passenger(s) + 1 driver`));
        if(p){
          box.append(el('small',`Planned trip: ${p.distance_km.toFixed(2)} km · ${p.vehicle_kg.toFixed(2)} kg CO₂e / car`));
          box.append(el('small',`Planned share: ${p.participant_kg.toFixed(2)} kg CO₂e / booked participant`));
          box.append(el('small',p.distance_basis==='road_estimate'?'Road-route estimate':'Planned-route approximation','live-carbon-basis'));
        }else box.append(el('small','Whole-trip distance unavailable.'));
      }else box.append(el('small',c.reason));
      if(dialog){const button=el('button','Lihat kiraan');button.type='button';button.dataset.carbonRide=String(ride.id);button.addEventListener('click',()=>open(ride.id));box.append(button);}
      card.append(box);
    },
    refresh(rides){current=new Map(rides.map(ride=>[ride.id,ride]));if(dialog?.open)fillDialog();}
  };
})();
