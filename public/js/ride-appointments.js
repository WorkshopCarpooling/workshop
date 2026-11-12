(() => {
  'use strict';
  const form = document.getElementById('rideAppointmentForm');
  if (!form) return;
  const config = window.rideAppointments, get = id => document.getElementById(id);
  const notice = get('appointmentFormStatus'), confirm = get('appointmentConfirm'), review = get('appointmentReview');
  const headers = {Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':config.csrf};
  let version = 0, quote = null, fields = null, expiry = 0, saving = false, polling = false, rowsSignature = '';
  const element = (tag,text,cls) => { const node=document.createElement(tag);if(text!==undefined)node.textContent=text;if(cls)node.className=cls;return node; };
  const message = (data,fallback) => data.errors ? Object.values(data.errors).flat()[0] : data.message || fallback;
  function changed() {
    version++;quote=null;fields=null;clearTimeout(expiry);confirm.disabled=true;get('appointmentQuote').hidden=true;
    notice.textContent='Review your updated journey, date, time and passenger count.';
  }
  const announce = () => document.dispatchEvent(new CustomEvent('rideutem:appointments-changed'));
  function render(appointments) {
    appointments = appointments.filter(appointment => appointment.status !== 'cancelled');
    const signature=JSON.stringify(appointments);if(signature===rowsSignature)return;rowsSignature=signature;
    const list=get('appointmentList');list.replaceChildren();
    get('appointmentCount').textContent=`${appointments.length} appointment${appointments.length===1?'':'s'}`;
    if(!appointments.length)list.append(element('p','No ride appointments yet. Book your first one above.','journey-status'));
    const labels={scheduled:'Appointment saved',paused:'Weekly ride paused',pending:'Finding a driver',accepted:'Driver accepted',in_progress:'In progress',completed:'Completed',cancelled:'Cancelled',expired:'No driver confirmed'};
    for(const appointment of appointments) {
      const row=element('article',undefined,'appointment-row');row.id=`appointment-${appointment.id}`;
      const copy=element('div');copy.append(element('b',`${appointment.dateLabel} · ${appointment.timeLabel}`));
      if(appointment.timetableTitle)copy.append(element('p',`From your timetable: ${appointment.timetableTitle}`));
      copy.append(element('p',`${appointment.pickup} → ${appointment.dropoff}`));
      copy.append(element('small',`${appointment.seats} passenger${appointment.seats===1?'':'s'} · RM ${appointment.total} total · Malaysia time`));
      const explanation=appointment.status==='scheduled'?'Driver search starts automatically at this time. A car is confirmed after a driver accepts.':appointment.status==='pending'?'Your appointment time has arrived. Waiting for an approved driver to accept.':appointment.status==='expired'?'No driver accepted within 10 minutes. Book another appointment or find a ride now.':appointment.driver?`${appointment.driver} · ${appointment.vehicle}`:'';
      if(explanation)copy.append(element('p',explanation));
      const actions=element('div',undefined,'appointment-row-actions'),badge=element('span',labels[appointment.status]||appointment.status,'appointment-state');badge.dataset.state=appointment.status;actions.append(badge);
      if(['scheduled','pending','paused'].includes(appointment.status)) {
        const cancel=element('button','Cancel appointment');cancel.type='button';
        cancel.addEventListener('click',async()=>{
          cancel.disabled=true;get('appointmentListStatus').textContent='Cancelling your appointment…';
          try {
            const response=await fetch(`${config.cancelBase}/${appointment.id}/cancel`,{method:'POST',headers,body:'{}'}),data=await response.json();
            if(!response.ok)throw new Error(message(data,'Could not cancel. Refresh to check your appointment.'));
            render(data.appointments);announce();get('appointmentListStatus').textContent='Appointment cancelled.';
          } catch(error) {get('appointmentListStatus').textContent=error.message||'Could not cancel your appointment.';cancel.disabled=false;}
        });actions.append(cancel);
      }
      row.append(copy,actions);list.append(row);
    }
  }
  form.addEventListener('input',changed);form.addEventListener('change',changed);form.addEventListener('journeychange',changed);
  form.addEventListener('submit',async event=>{
    event.preventDefault();changed();const current=version,submitted=Object.fromEntries(new FormData(form));
    review.disabled=true;notice.textContent='Checking your appointment and fare…';
    try {
      const url=new URL(config.url,location.href);url.search=new URLSearchParams(submitted).toString();
      const response=await fetch(url,{headers:{Accept:'application/json'}}),data=await response.json();
      if(current!==version)return;
      if(!response.ok)throw new Error(message(data,'Could not review this appointment. Try again.'));
      fields=submitted;quote=data.fare;
      const time=new Date(`${submitted.pickup_date}T${submitted.pickup_time}:00+08:00`);
      get('appointmentQuoteWhen').textContent=new Intl.DateTimeFormat('en-MY',{timeZone:'Asia/Kuala_Lumpur',day:'numeric',month:'short',year:'numeric',hour:'numeric',minute:'2-digit'}).format(time);
      get('appointmentQuoteTotal').textContent=`RM ${quote.total}`;
      get('appointmentQuoteRoute').textContent=`${submitted.pickup_label} → ${submitted.dropoff_label}`;
      get('appointmentQuoteDetails').textContent=`${quote.seats} passenger${quote.seats===1?'':'s'} · ${Number(quote.distanceKm).toFixed(1)} km · RM ${quote.perSeat} per seat · ${quote.distanceSource==='google_routes_api'?'Road distance':'Estimated distance; actual travel may vary'}`;
      get('appointmentQuote').hidden=false;confirm.disabled=!data.bookingReady;
      notice.textContent=data.bookingReady?'Review the date and time, then confirm to save your appointment.':'Appointments are temporarily unavailable. Please try again later.';
      expiry=setTimeout(()=>{changed();notice.textContent='This fare quote expired. Review the appointment again before confirming.';},Math.max(0,Date.parse(quote.expiresAt)-Date.now()));
    } catch(error) {if(current===version)notice.textContent=error.message||'Could not review your appointment.';}
    finally {review.disabled=false;}
  });
  confirm.addEventListener('click',async()=>{
    if(!quote||!fields||saving)return;saving=true;confirm.disabled=true;
    const submitted={...fields,fare_quote:quote.token};notice.textContent='Saving your ride appointment…';
    try {
      const response=await fetch(config.url,{method:'POST',headers,body:JSON.stringify(submitted)}),data=await response.json();
      if(!response.ok)throw new Error(message(data,'Could not save your appointment. Try again.'));
      changed();render(data.appointments);announce();
      notice.textContent='Appointment saved. Driver search will start automatically at the booked time.';
      get('appointmentListStatus').textContent='Your reminder robot will nudge you before the appointment.';
    } catch(error) {notice.textContent=error.message||'Could not save your appointment.';confirm.disabled=!quote;}
    finally {saving=false;}
  });
  async function refresh() {
    if(document.hidden||polling)return;polling=true;
    try {
      const response=await fetch(config.statusUrl,{headers:{Accept:'application/json'}});if(!response.ok)throw new Error();
      const data=await response.json();render(data.appointments);
      get('appointmentListStatus').textContent='Appointment and driver updates refresh automatically.';
    } catch(_) {get('appointmentListStatus').textContent='Could not refresh appointments. Reconnecting…';}
    finally {polling=false;}
  }
  render(config.appointments||[]);confirm.disabled=true;
  setInterval(refresh,15000);document.addEventListener('visibilitychange',()=>{if(!document.hidden)refresh();});
})();
