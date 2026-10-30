(() => {
  'use strict';
  const form = document.getElementById('rideNowForm');
  if (!form) return;
  const config = window.rideNow, get = id => document.getElementById(id);
  const status = get('nowSearchStatus'), search = get('findDriversButton');
  const activeStatuses = ['pending', 'accepted', 'in_progress'];
  let request = config.request, submitting = false, cancelling = false, polling = false, requestVersion = 0;
  const animation = get('rideSearchAnimation');
  let cancelError = '';
  const write = (id, value) => { if (get(id).textContent !== value) get(id).textContent = value; };
  const errorMessage = (data, fallback) => data.errors ? Object.values(data.errors).flat()[0] : data.message || fallback;
  const csrfHeaders = {Accept:'application/json', 'Content-Type':'application/json', 'X-CSRF-TOKEN':config.csrf};
  async function fetchJson(url, options, fallback) {
    const controller = new AbortController(), timeout = setTimeout(() => controller.abort(), 25000);
    try {
      const response = await fetch(url, {...options, signal:controller.signal}), data = await response.json();
      if (!response.ok) throw new Error(errorMessage(data, fallback));
      return data;
    } finally { clearTimeout(timeout); }
  }
  function invalidate() {
    if (!submitting) status.textContent = 'Choose both locations, then search to send your ride request.';
  }
  function showRequest(value) {
    const wasWaiting = ['sending','pending'].includes(request?.status) && !get('rideRequestPanel').hidden;
    if (value?.status === 'cancelled') value = null;
    request = value;
    const sending = value?.status === 'sending';
    const active = sending || (value && activeStatuses.includes(value.status));
    const waiting = sending || value?.status === 'pending';
    if (!waiting) cancelError = '';
    get('rideRequestPanel').hidden = !value;
    get('rideNowSearch').hidden = !!active;
    get('rideRequestPanel').classList.toggle('is-waiting', waiting);
    animation.hidden = !waiting;
    get('rideNowIntro').hidden = waiting;
    get('rideNowLater').hidden = waiting;
    get('rideWaitingHint').hidden = !waiting;
    if (!value) {
      if (wasWaiting) { search.focus({preventScroll:true}); get('rideNowSearch').scrollIntoView({block:'start'}); }
      return;
    }
    const titles = {sending:'Starting your search', pending:'Finding a driver', accepted:'Your driver accepted', in_progress:'You’re on your way', expired:'No driver accepted yet', cancelled:'Your request was cancelled', completed:'You’ve arrived'};
    const messages = {sending:'Checking your journey and sending your request to campus drivers…', pending:'Your request is with approved campus drivers. We’ll update you here when someone accepts.', accepted:'Your ride is confirmed. Meet your driver at the pickup point.', in_progress:'Your trip is in progress.', expired:'No driver accepted within the search window. You can try finding a ride again.', cancelled:'Your search has stopped. You can find another ride whenever you’re ready.', completed:'Your trip is complete. Thank you for sharing the journey.'};
    write('rideRequestTitle', titles[value.status] || 'Your ride');
    write('rideRequestMessage', cancelError || messages[value.status] || 'Check your bookings for details.');
    write('rideRequestEyebrow', sending ? 'STARTING YOUR SEARCH' : (waiting ? 'REQUEST SENT · RIDE NOW' : 'YOUR RIDE'));
    write('rideWaitingPickup', value.pickup);
    write('rideWaitingDropoff', value.dropoff);
    write('rideRequestRoute', `${value.pickup} → ${value.dropoff}`);
    write('rideRequestFare', value.total == null ? 'Calculating your student fare…' : `RM ${value.total} total · ${value.seats} passenger${value.seats===1?'':'s'}`);
    write('rideRequestDriver', value.driver ? `${value.driver} · ${value.vehicle}` : '');
    get('rideRequestDriver').hidden = !value.driver;
    if(get('rideNavigationLink')){get('rideNavigationLink').hidden=value.status!=='in_progress'||!value.navigationUrl;if(value.status==='in_progress'&&value.navigationUrl)get('rideNavigationLink').href=value.navigationUrl;}
    if(get('rideSharedSummary')) {
      const shared=value.shared, summary=get('rideSharedSummary');summary.hidden=!shared;
      if(shared)summary.textContent=`Shared car · ${shared.passengers}/${shared.capacity} passenger seats booked · ${shared.remaining} left. ${shared.pickupPosition?`Your pickup is stop ${shared.pickupPosition}. `:''}${shared.open?'The driver can add passengers before starting pickups; your position may change.':'Your driver will follow the planned pickup order.'}`;
    }
    get('cancelRideRequest').hidden = !waiting;
    get('cancelRideRequest').disabled = sending || cancelling;
    write('rideWaitingHint', sending ? 'Cancel becomes available once your request has been sent.' : 'You can cancel while no driver has accepted.');
    get('rideRequestBooking').hidden = !value.rideId;
    if (waiting !== wasWaiting) {
      get('rideRequestTitle').focus({preventScroll:true});
      if (submitting || wasWaiting) get('rideRequestPanel').scrollIntoView({block:'start'});
    }
  }
  form.addEventListener('journeychange', invalidate);
  form.addEventListener('input', invalidate);
  form.addEventListener('change', invalidate);
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (submitting || activeStatuses.includes(request?.status)) return;
    const fields = Object.fromEntries(new FormData(form));
    submitting = true; requestVersion++; cancelError = ''; search.disabled = true; form.inert = true;
    let posted = false;
    const starting = {status:'sending',pickup:fields.pickup_label,dropoff:fields.dropoff_label,seats:Number(fields.seats_requested),total:null};
    showRequest(starting);
    try {
      const url = new URL(config.searchUrl, location.href); url.search = new URLSearchParams(fields).toString();
      const review = await fetchJson(url, {headers:{Accept:'application/json'}}, 'Could not check your journey. Please try again.');
      if (!review.bookingReady) throw new Error('Ride requests are temporarily unavailable. Please try again later.');
      if (!review.fare?.token || !Number.isFinite(Date.parse(review.fare.expiresAt)) || Date.parse(review.fare.expiresAt) <= Date.now()) throw new Error('Could not get a current fare. Please search again.');
      showRequest({...starting,total:review.fare.total});
      posted = true;
      const data = await fetchJson(config.requestUrl, {method:'POST',headers:csrfHeaders,body:JSON.stringify({...fields,fare_quote:review.fare.token})}, 'Could not send your request. Please try again.');
      requestVersion++; showRequest(data.request);
    } catch (error) {
      // A lost POST response does not mean the server failed to create the ride request.
      let recovered = false;
      if (posted) {
        try {
          const data = await fetchJson(config.statusUrl, {headers:{Accept:'application/json'}}, 'Could not refresh your request.');
          if (activeStatuses.includes(data.request?.status)) { requestVersion++; showRequest(data.request); recovered = true; }
        } catch (_) {}
      }
      if (!recovered) {
        showRequest(null);
        status.textContent = error.name === 'AbortError' ? 'The connection timed out. Please search again to check or retry your request.' : error.message || 'Could not start your search. Please try again.';
        status.focus({preventScroll:true}); get('rideNowSearch').scrollIntoView({block:'start'});
      }
    } finally { submitting = false; search.disabled = false; form.inert = false; }
  });
  get('cancelRideRequest').addEventListener('click', async () => {
    if (cancelling || request?.status !== 'pending') return;
    cancelling = true; requestVersion++; cancelError = ''; get('cancelRideRequest').disabled = true;
    get('cancelRideRequest').textContent = 'Cancelling…';
    try {
      const response = await fetch(`${config.cancelBase}/${request.id}/cancel`, {method:'POST',headers:csrfHeaders,body:'{}'}), data = await response.json();
      if (!response.ok) throw new Error(errorMessage(data, 'Could not cancel. Refresh to check your request.'));
      requestVersion++; showRequest(data.request); invalidate();
    } catch (error) {
      cancelError = error.message || 'Could not cancel. Your search may still be active. Try again.';
      get('rideRequestMessage').textContent = cancelError;
    }
    finally { cancelling = false; get('cancelRideRequest').disabled = false; get('cancelRideRequest').textContent = 'Cancel find ride'; }
    // Acceptance may have won the server-side lock. Refresh the real state on failure too.
    if (request?.status === 'pending') poll();
  });
  async function poll() {
    if (document.hidden || !request || !activeStatuses.includes(request.status) || polling || cancelling) return;
    polling = true; const current = requestVersion;
    try {
      const response = await fetch(config.statusUrl, {headers:{Accept:'application/json'}});
      if (!response.ok) throw new Error();
      const data = await response.json(); if (current === requestVersion) showRequest(data.request);
    } catch (_) { if (current === requestVersion) get('rideRequestMessage').textContent = 'Could not refresh your ride status. Reconnecting… Your request may still be active. You can retry cancellation below.'; }
    finally { polling = false; }
  }
  showRequest(request);
  setInterval(poll, 5000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
})();
