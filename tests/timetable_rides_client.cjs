const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
class Node {constructor(){this.events={};this.hidden=false;this.disabled=false;this.checked=false;this.value='';this.textContent='';}addEventListener(name,fn){(this.events[name]??=[]).push(fn);}async fire(name){for(const fn of this.events[name]||[])await fn({preventDefault(){}});}}
const ids=['timetableRideForm','timetableRideStatus','commuteBookingMode','confirmTimetableRide','weeklyRideConsent','commuteRepeatDates','timetableRideReview','reviewTimetableRide','commuteReviewDate','commuteReviewPickup','commuteReviewArrival','commuteReviewClass','commuteReviewRoute','commuteReviewFare','commuteReviewTiming','weeklyConsentLabel','weeklyConsentText'];
const nodes=Object.fromEntries(ids.map(id=>[id,new Node()])),dates={starts_on:new Node(),ends_on:new Node()};
const fields={pickup_label:'Home gate',pickup_latitude:'2.2707',pickup_longitude:'102.2856',dropoff_label:'Campus',dropoff_latitude:'2.3139',dropoff_longitude:'102.3183',seats_requested:'2',arrival_buffer_minutes:'10',booking_mode:'once',starts_on:'2030-01-07',ends_on:'2030-01-28'};
nodes.commuteBookingMode.value='once';nodes.timetableRideForm.elements={namedItem:name=>dates[name]};
const calls=[],timers=new Map(),events=[],redirects=[];let timerId=0,searchReply,storeReply;
const fare={token:'synthetic-weekly-quote',total:'6.60',perSeat:'3.30',seats:2,distanceKm:7.71,distanceSource:'corridor_estimate',expiresAt:new Date(Date.now()+600000).toISOString()};
const answer=data=>({ok:true,json:async()=>data});
const review=()=>answer({fare,bookingMode:fields.booking_mode,timing:{classStartsAt:'2030-01-07 09:00:00',scheduledFor:'2030-01-07 08:20:00',arriveBy:'2030-01-07 08:50:00',travelMinutes:20,arrivalBuffer:10,driverAllowance:10}});
vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname,'../public/js/timetable-rides.js'),'utf8'),{
  document:{getElementById:id=>nodes[id],dispatchEvent:event=>events.push(event.type)},
  window:{timetableRide:{url:'/timetable/1/ride',csrf:'test-csrf'},location:{assign:url=>redirects.push(url)}},
  location:{href:'http://localhost/workshop/timetable/1/ride',origin:'http://localhost'},Date,Intl,URL,URLSearchParams,CustomEvent:class{constructor(type){this.type=type;}},
  FormData:class{[Symbol.iterator](){return Object.entries(fields).filter(([name])=>!dates[name]?.disabled)[Symbol.iterator]();}},
  setTimeout:(action,delay)=>{const id=++timerId;timers.set(id,{action,delay});return id;},clearTimeout:id=>timers.delete(id),
  fetch:async(url,options)=>{calls.push({url:String(url),options});if(options?.method==='POST')return storeReply?await storeReply():answer({redirectUrl:'/timetable'});return searchReply?await searchReply():review();},
});
let checks=0;const check=(ok,label)=>{assert.ok(ok,label);checks++;console.log('PASS: '+label);};
(async()=>{
  check(nodes.commuteRepeatDates.hidden&&dates.ends_on.disabled,'next-session booking is the default and does not silently enable weekly rides');
  await nodes.timetableRideForm.fire('submit');
  check(nodes.commuteReviewPickup.textContent.includes('8:20')&&nodes.commuteReviewArrival.textContent.includes('8:50')&&nodes.commuteReviewClass.textContent.includes('9:00'),'review shows the calculated driver-search, arrival and class times in Malaysia time');
  check(nodes.weeklyConsentLabel.hidden&&!nodes.confirmTimetableRide.disabled,'a one-time ride can be confirmed without weekly consent');
  nodes.commuteBookingMode.value='weekly';fields.booking_mode='weekly';await nodes.timetableRideForm.fire('change');
  check(!nodes.commuteRepeatDates.hidden&&dates.ends_on.required&&!dates.ends_on.disabled,'weekly mode requires a repeat date range');
  check(nodes.confirmTimetableRide.disabled,'changing the booking mode invalidates the previous review');
  await nodes.timetableRideForm.fire('submit');
  check(!nodes.weeklyConsentLabel.hidden&&nodes.confirmTimetableRide.disabled,'weekly review stays disabled until explicit consent is checked');
  check(nodes.weeklyConsentText.textContent.includes('2030-01-28')&&nodes.weeklyConsentText.textContent.includes('reviewed contribution'),'weekly consent states the selected term and re-used route, passenger count and contribution');
  await nodes.confirmTimetableRide.fire('click');check(!calls.some(call=>call.options?.method==='POST'),'unconsented weekly confirmation cannot submit');
  nodes.weeklyRideConsent.checked=true;await nodes.weeklyRideConsent.fire('change');check(!nodes.confirmTimetableRide.disabled,'checking consent enables the reviewed weekly plan');
  await nodes.timetableRideForm.fire('journeychange');check(nodes.confirmTimetableRide.disabled&&!nodes.weeklyRideConsent.checked,'changing a route clears both its old quote and weekly consent');
  let resolveSearch;searchReply=()=>new Promise(resolve=>resolveSearch=resolve);const old=nodes.timetableRideForm.fire('submit');await nodes.timetableRideForm.fire('input');resolveSearch(review());await old;
  check(nodes.timetableRideReview.hidden,'a stale route response cannot restore an obsolete weekly review');
  searchReply=null;await nodes.timetableRideForm.fire('submit');[...timers.values()].at(-1).action();check(nodes.confirmTimetableRide.disabled,'expired reviews cannot enable recurring rides');
  await nodes.timetableRideForm.fire('submit');nodes.weeklyRideConsent.checked=true;await nodes.weeklyRideConsent.fire('change');
  let resolveStore;storeReply=()=>new Promise(resolve=>resolveStore=resolve);const save=nodes.confirmTimetableRide.fire('click');await nodes.confirmTimetableRide.fire('click');
  check(calls.filter(call=>call.options?.method==='POST').length===1,'double clicking saves a weekly plan only once');
  const submitted=JSON.parse(calls.find(call=>call.options?.method==='POST').options.body);
  check(submitted.weekly_consent==='1'&&submitted.ends_on==='2030-01-28'&&submitted.fare_quote===fare.token,'confirmation sends the reviewed settings, end date and signed quote');
  resolveStore(answer({redirectUrl:'/timetable'}));await save;
  check(redirects[0]==='http://localhost/timetable'&&events.includes('rideutem:appointments-changed'),'a saved ride refreshes the robot and returns to Timetable');
  console.log(`${checks} timetable consent, timing, expiry and client-flow checks passed; synthetic network only.`);
})().catch(error=>{console.error(error);process.exitCode=1;});
