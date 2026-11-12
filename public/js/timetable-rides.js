(() => {
  'use strict';
  const form=document.getElementById('timetableRideForm');if(!form)return;
  const config=window.timetableRide,get=id=>document.getElementById(id),status=get('timetableRideStatus');
  const mode=get('commuteBookingMode'),confirm=get('confirmTimetableRide'),consent=get('weeklyRideConsent');
  let version=0,quote=null,inputs=null,expiry=0,saving=false,weekly=false;
  const errorMessage=(data,fallback)=>data.errors?Object.values(data.errors).flat()[0]:data.message||fallback;
  const date=value=>new Date(value.replace(' ','T')+'+08:00');
  const formatTime=value=>new Intl.DateTimeFormat('en-MY',{timeZone:'Asia/Kuala_Lumpur',hour:'numeric',minute:'2-digit'}).format(date(value));
  function updateMode() {
    const repeat=mode.value==='weekly';get('commuteRepeatDates').hidden=!repeat;
    for(const name of ['starts_on','ends_on']) {form.elements.namedItem(name).disabled=!repeat;form.elements.namedItem(name).required=repeat;}
  }
  function invalidate() {
    version++;clearTimeout(expiry);quote=null;inputs=null;consent.checked=false;confirm.disabled=true;
    get('timetableRideReview').hidden=true;status.textContent='Review your updated route and preferences before confirming.';
    updateMode();
  }
  form.addEventListener('input',invalidate);form.addEventListener('change',invalidate);form.addEventListener('journeychange',invalidate);
  consent.addEventListener('change',()=>{confirm.disabled=!quote||(weekly&&!consent.checked);});
  form.addEventListener('submit',async event=>{
    event.preventDefault();invalidate();const current=version,fields=Object.fromEntries(new FormData(form));
    get('reviewTimetableRide').disabled=true;status.textContent='Estimating your journey and a ride time for the next session…';
    try {
      const url=new URL(config.url,location.href);url.search=new URLSearchParams(fields).toString();
      const response=await fetch(url,{headers:{Accept:'application/json'}}),data=await response.json();
      if(current!==version)return;
      if(!response.ok)throw new Error(errorMessage(data,'Could not review this timetable ride. Try again.'));
      quote=data.fare;inputs=fields;weekly=data.bookingMode==='weekly';
      const journeyTime=value=>value.slice(0,10)===data.timing.classStartsAt.slice(0,10)?formatTime(value):new Intl.DateTimeFormat('en-MY',{timeZone:'Asia/Kuala_Lumpur',weekday:'short',day:'numeric',month:'short',hour:'numeric',minute:'2-digit'}).format(date(value));
      get('commuteReviewDate').textContent=new Intl.DateTimeFormat('en-MY',{timeZone:'Asia/Kuala_Lumpur',weekday:'long',day:'numeric',month:'short',year:'numeric'}).format(date(data.timing.classStartsAt));
      get('commuteReviewPickup').textContent=journeyTime(data.timing.scheduledFor);
      get('commuteReviewArrival').textContent=journeyTime(data.timing.arriveBy);
      get('commuteReviewClass').textContent=formatTime(data.timing.classStartsAt);
      get('commuteReviewRoute').textContent=`${fields.pickup_label} → ${fields.dropoff_label}`;
      get('commuteReviewFare').textContent=`RM ${quote.total} total · ${quote.seats} passenger${quote.seats===1?'':'s'} · RM ${quote.perSeat} per seat · ${Number(quote.distanceKm).toFixed(1)} km`;
      get('commuteReviewTiming').textContent=`About ${data.timing.travelMinutes} minutes of travel + ${data.timing.arrivalBuffer} minutes to arrive early + ${data.timing.driverAllowance} minutes to find a driver. ${quote.distanceSource==='google_routes_api'?'Based on a road route.':'Based on an approximate route estimate.'} All times are Malaysia time.`;
      get('weeklyConsentLabel').hidden=!weekly;
      get('weeklyConsentText').textContent=weekly?`Create a ride appointment for this session every week from ${fields.starts_on} through ${fields.ends_on}, using this route, passenger count and reviewed contribution. Changes to a weekly plan update its future unconfirmed appointments.`:'';
      confirm.textContent=weekly?'Enable weekly rides →':'Confirm next-session ride →';confirm.disabled=weekly;
      get('timetableRideReview').hidden=false;
      status.textContent='Review the session date, suggested search time and contribution below.';
      expiry=setTimeout(()=>{invalidate();status.textContent='Your quote expired. Calculate and review the ride again.';},Math.max(0,Date.parse(quote.expiresAt)-Date.now()));
    } catch(error) {if(current===version)status.textContent=error.message||'Could not review your ride. Try again.';}
    finally {get('reviewTimetableRide').disabled=false;}
  });
  confirm.addEventListener('click',async()=>{
    if(!quote||!inputs||saving||(weekly&&!consent.checked))return;
    saving=true;confirm.disabled=true;
    const fields={...inputs,fare_quote:quote.token,weekly_consent:weekly&&consent.checked?'1':'0'};
    status.textContent=weekly?'Saving your weekly ride plan…':'Saving your next ride appointment…';
    try {
      const response=await fetch(config.url,{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':config.csrf},body:JSON.stringify(fields)}),data=await response.json();
      if(!response.ok)throw new Error(errorMessage(data,'Could not save this ride. Try again.'));
      document.dispatchEvent(new CustomEvent('rideutem:appointments-changed'));
      const destination=new URL(data.redirectUrl,location.href);
      if(destination.origin!==location.origin)throw new Error('Your ride was saved. Open Timetable to view it.');
      window.location.assign(destination.href);
    } catch(error) {status.textContent=error.message||'Could not save your ride.';confirm.disabled=!quote||(weekly&&!consent.checked);}
    finally {saving=false;}
  });
  updateMode();confirm.disabled=true;
})();
