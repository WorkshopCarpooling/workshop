(() => {
  const form = document.getElementById('journeySearchForm');
  if (!form) return;
  const list = document.getElementById('rideResults'), status = document.getElementById('searchStatus');
  let searchVersion = 0, lastJourney = null, autoSearch, quoteExpiry, bookingReady = window.bookingReady !== false;
  const el = (tag, text, cls) => { const node=document.createElement(tag);if(text!==undefined)node.textContent=text;if(cls)node.className=cls;return node; };
  function render(rides) {
    clearTimeout(quoteExpiry);
    list.replaceChildren();document.getElementById('resultsCount').textContent=`${rides.length} ride${rides.length===1?'':'s'}`;
    if(!rides.length) list.append(el('p','No compatible rides found. Adjust your locations or time, or check again after a driver publishes a ride.','journey-status'));
    for(const [index,ride] of rides.entries()) {
      const card=el('article',undefined,'ride-result-card');
      card.append(el('h3',`${ride.pickup} → ${ride.destination}`));
      card.append(el('p',`${ride.driver} · ${ride.vehicle} · ${ride.departureAt}`));
      if(ride.driverDistanceKm !== null && ride.driverDistanceKm !== undefined) {
        card.append(el('span',`${index===0?'Nearest compatible driver · ':''}${Number(ride.driverDistanceKm).toFixed(2)} km from your pickup`,'match-score'));
        card.append(el('p','Straight-line distance to the driver’s published starting point.'));
      }
      card.append(el('p',`${ride.seats} seats available`));
      if(ride.fare) {
        card.append(el('strong',`RM ${ride.fare.perSeat} / seat · RM ${ride.fare.total} total`,'student-fare-amount'));
        card.append(el('p',`${Number(ride.fare.distanceKm).toFixed(1)} km ${ride.fare.distanceSource==='google_routes_api'?'by road':'estimated journey'} · ${ride.fare.seats} passenger${ride.fare.seats===1?'':'s'} · Driver confirms this contribution.`));
      } else card.append(el('p',`Up to RM ${ride.cost} / seat · Choose your destination for a student fare.`));
      if(ride.notes)card.append(el('p',ride.notes));
      if(ride.match) {
        card.append(el('span',`${ride.match.score}% compatibility`,'match-score'));
        card.append(el('p',`Your pickup ≈ ${ride.match.pickupAt} · Arrival ≈ ${ride.match.arrivalAt}. Times are approximate; allow extra time for stops.`));
      }
      const booking=el('form');booking.method='post';booking.action=window.rideSearchUrl;
      const values={...(lastJourney||{}),_token:window.bookingCsrf,ride_id:ride.id,fare_quote:ride.fare?.token||''};
      for(const [name,value] of Object.entries(values)) { const input=el('input');input.type='hidden';input.name=name;input.value=value;booking.append(input); }
      if(window.carbonReady) {
        const baseline=el('fieldset',undefined,'carbon-baseline-fields');baseline.append(el('legend','Alternative travel for the carbon estimate'));
        const modeLabel=el('label','Without this ride, how would your group travel?'), mode=el('select');mode.name='baseline_mode';
        for(const [value,label] of Object.entries({unknown:'Not sure yet',drive_alone:'Drive in a separate car',walk_cycle:'Walk or cycle',no_trip:'Not make this journey',motorcycle:'Motorcycle',bus:'Bus / public transport',carpool:'Another shared ride'})) {const option=el('option',label);option.value=value;mode.append(option);}
        modeLabel.append(mode);baseline.append(modeLabel);
        const car=el('div',undefined,'carbon-form-grid');car.hidden=true;
        const carsLabel=el('label','Separate cars your group would use'), cars=el('input');cars.type='number';cars.name='baseline_cars';cars.min='1';cars.max=String(ride.fare?.seats||1);cars.value='1';carsLabel.append(cars);car.append(carsLabel);
        const fuelLabel=el('label','Alternative car fuel'),fuel=el('select');fuel.name='baseline_fuel_type';const empty=el('option','Choose fuel');empty.value='';fuel.append(empty);
        for(const [value,entry] of Object.entries(window.carbonFuels||{})){const option=el('option',entry.label);option.value=value;fuel.append(option);}fuelLabel.append(fuel);car.append(fuelLabel);
        const consumptionLabel=el('label','Consumption (L/100 km)'), consumption=el('input');consumption.type='number';consumption.name='baseline_fuel_l_per_100km';consumption.min='0.1';consumption.max='50';consumption.step='0.1';consumption.placeholder='e.g. 7.0';consumptionLabel.append(consumption);car.append(consumptionLabel);baseline.append(car);
        const distanceLabel=el('label','Alternative route (km)'),distance=el('input');distance.type='number';distance.name='baseline_distance_km';distance.min='0.001';distance.max='500';distance.step='0.001';distance.value=String(ride.fare?.distanceKm||'');distanceLabel.append(distance);car.append(distanceLabel);
        const sync=()=>{const selected=mode.value==='drive_alone';car.hidden=!selected;for(const input of [cars,fuel,consumption,distance]){input.disabled=!selected;input.required=selected;}};mode.addEventListener('change',sync);sync();
        baseline.append(el('small','Count separate cars, not seats. You can update this later on the Carbon page. Unsupported travel modes stay incomplete.'));booking.append(baseline);
      }
      const button=el('button',ride.bookingStatus==='pending'?'Request pending':ride.bookingStatus==='accepted'?'Seats confirmed':!bookingReady?'Requests temporarily unavailable':ride.match&&lastJourney?'Request seats':'Select your journey first','solid-button');
      button.type='submit';button.disabled=!ride.match||!lastJourney||!ride.fare?.token||!bookingReady||['pending','accepted'].includes(ride.bookingStatus);
      booking.append(button);card.append(booking);list.append(card);
    }
    const expiry=Math.min(...rides.filter(r=>r.fare).map(r=>Date.parse(r.fare.expiresAt)));
    if(Number.isFinite(expiry))quoteExpiry=setTimeout(()=>{invalidate();status.textContent='Fare quotes expired. Refresh nearby rides for updated prices.';},Math.max(0,expiry-Date.now()));
  }
  function invalidate() { clearTimeout(autoSearch);clearTimeout(quoteExpiry);searchVersion++;lastJourney=null; list.querySelectorAll('button').forEach(button=>button.disabled=true);status.textContent='Choose a destination and confirm your pickup to find nearby compatible drivers.'; }
  function scheduleSearch() {
    invalidate();
    if(!window.journeyPicker?.point('pickup')||!window.journeyPicker?.point('dropoff'))return;
    status.textContent='Updating nearby drivers and student fares…';
    autoSearch=setTimeout(()=>{if(form.checkValidity())form.requestSubmit();},600);
  }
  form.addEventListener('journeychange',scheduleSearch);
  form.addEventListener('change',scheduleSearch);
  form.addEventListener('input',scheduleSearch);
  form.addEventListener('submit',async e=>{
    e.preventDefault();clearTimeout(autoSearch);clearTimeout(quoteExpiry);const version=++searchVersion;
    const journey=Object.fromEntries(new FormData(form));
    status.textContent='Checking route, seats and departure compatibility…';list.querySelectorAll('button').forEach(b=>b.disabled=true);
    try {
      const url=new URL(window.rideSearchUrl,location.href);url.search=new URLSearchParams(journey).toString();
      const response=await fetch(url,{headers:{Accept:'application/json'}});const data=await response.json();
      if(version!==searchVersion)return;
      if(!response.ok)throw new Error(data.errors?Object.values(data.errors).flat()[0]:data.message||'Ride search unavailable.');
      lastJourney=journey;bookingReady=data.bookingReady!==false;render(data.rides);
      status.textContent=bookingReady?`${data.rides.length} compatible ride${data.rides.length===1?'':'s'} · nearest published driver starting point first. Fares are valid for 10 minutes; the driver confirms your request.`:'Student fares are available, but seat requests are temporarily unavailable. Please try again later.';
    } catch(error) { if(version===searchVersion)status.textContent=error.message||'Could not load rides. Try again.'; }
  });
  document.getElementById('timetableJourney').addEventListener('change',e=>{
    const option=e.target.selectedOptions[0];if(!option.dataset.day)return;
    const now=new Date(), date=new Date(now);const [hour,minute]=option.dataset.time.split(':').map(Number);
    date.setHours(hour,minute,0,0);date.setDate(date.getDate()+((Number(option.dataset.day)-date.getDay()+7)%7));
    if(date<=now)date.setDate(date.getDate()+7);
    const local=d=>`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}T${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`;
    const depart=new Date(date.getTime()-70*60000);form.elements.departure_at.value=local(depart>now?depart:new Date(now.getTime()+60000));
    form.elements.dropoff_label.value=option.dataset.location;
    form.elements.dropoff_label.dispatchEvent(new Event('input',{bubbles:true}));
    status.textContent='Departure suggested from your timetable. Confirm the destination pin and check the ride’s estimated arrival against your class time.';
  });
  render(window.availableRides||[]);
  if(window.journeyPicker?.point('pickup')&&window.journeyPicker?.point('dropoff'))scheduleSearch();
})();
