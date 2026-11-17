// Execute the actual appointment controller with a controlled DOM and network.
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
class Node {
  constructor(){this.events={};this.children=[];this.dataset={};this.hidden=false;this.disabled=false;this.textContent='';}
  addEventListener(name,action){(this.events[name]??=[]).push(action);}
  async fire(name){for(const action of this.events[name]||[])await action({preventDefault(){}});}
  append(...nodes){this.children.push(...nodes);}replaceChildren(...nodes){this.children=nodes;}
}
const ids=['rideAppointmentForm','appointmentFormStatus','appointmentConfirm','appointmentReview','appointmentQuote','appointmentList','appointmentCount','appointmentListStatus','appointmentQuoteWhen','appointmentQuoteTotal','appointmentQuoteRoute','appointmentQuoteDetails'];
const nodes=Object.fromEntries(ids.map(id=>[id,new Node()]));
const fields={pickup_label:'Main gate',pickup_latitude:'2.2707',pickup_longitude:'102.2856',dropoff_label:'UTeM Main Campus',dropoff_latitude:'2.3139',dropoff_longitude:'102.3183',pickup_date:'2030-01-07',pickup_time:'09:00',seats_requested:'2'};
const events=[],document={hidden:false,getElementById:id=>nodes[id],createElement:()=>new Node(),addEventListener(){},dispatchEvent:event=>events.push(event.type)};
const timers=new Map(),intervals=[],calls=[];let counter=0,searchReply,storeReply,statusRows=[];
const fare={token:'synthetic-appointment-quote',total:'6.60',perSeat:'3.30',distanceKm:7.71,seats:2,distanceSource:'corridor_estimate',expiresAt:new Date(Date.now()+600000).toISOString()};
const saved={id:8,status:'scheduled',pickup:'Main gate',dropoff:'UTeM Main Campus',seats:2,total:'6.60',dateLabel:'Mon, 7 Jan 2030',timeLabel:'9:00 AM',scheduledFor:'2030-01-07T09:00:00+08:00'};
const response=data=>({ok:true,json:async()=>data});
vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname,'../public/js/ride-appointments.js'),'utf8'),{
  document,window:{rideAppointments:{url:'/passenger/schedule',statusUrl:'/appointments',cancelBase:'/cancel',csrf:'test-csrf',appointments:[]}},
  location:{href:'http://localhost/workshop/passenger/schedule'},Date,Intl,URL,URLSearchParams,CustomEvent:class{constructor(type){this.type=type;}},
  FormData:class{[Symbol.iterator](){return Object.entries(fields)[Symbol.iterator]();}},
  setTimeout:(action,delay)=>{const id=++counter;timers.set(id,{action,delay});return id;},clearTimeout:id=>timers.delete(id),setInterval:action=>intervals.push(action),
  fetch:async(url,options)=>{
    calls.push({url:String(url),options});
    if(String(url)==='/appointments')return response({appointments:statusRows});
    if(String(url).startsWith('/cancel'))return response({appointments:[{...saved,status:'cancelled'}]});
    if(options?.method==='POST')return storeReply?await storeReply():response({appointment:saved,appointments:[saved]});
    return searchReply?await searchReply():response({fare,bookingReady:true});
  },
});
let checks=0;const check=(ok,label)=>{assert.ok(ok,label);checks++;console.log('PASS: '+label);};
const text=node=>node.textContent+' '+node.children.map(text).join(' ');
(async()=>{
  check(nodes.appointmentConfirm.disabled,'appointment confirmation starts disabled until the journey is reviewed');
  await nodes.rideAppointmentForm.fire('submit');
  check(!nodes.appointmentQuote.hidden&&nodes.appointmentQuoteTotal.textContent==='RM 6.60','review displays the total for the selected passenger count');
  check(nodes.appointmentQuoteWhen.textContent.includes('9:00')&&nodes.appointmentQuoteWhen.textContent.includes('2030'),'review uses the selected Malaysia appointment date and time');
  check(calls[0].url.includes('pickup_date=2030-01-07')&&calls[0].url.includes('pickup_time=09%3A00'),'review sends the chosen appointment date and time');
  check(!calls.some(call=>call.options?.method==='POST'),'review alone does not create an appointment or broadcast a request');
  await nodes.rideAppointmentForm.fire('change');
  check(nodes.appointmentConfirm.disabled&&nodes.appointmentQuote.hidden,'changing date, time or passengers invalidates the earlier review');
  let resolveSearch;searchReply=()=>new Promise(resolve=>resolveSearch=resolve);
  const old=nodes.rideAppointmentForm.fire('submit');await nodes.rideAppointmentForm.fire('input');resolveSearch(response({fare,bookingReady:true}));await old;
  check(nodes.appointmentQuote.hidden,'out-of-order responses cannot restore stale appointment quotes');
  searchReply=null;await nodes.rideAppointmentForm.fire('submit');[...timers.values()].at(-1).action();
  check(nodes.appointmentConfirm.disabled&&nodes.appointmentFormStatus.textContent.includes('expired'),'expired quotes must be reviewed again');
  await nodes.rideAppointmentForm.fire('submit');let resolveStore;storeReply=()=>new Promise(resolve=>resolveStore=resolve);
  const first=nodes.appointmentConfirm.fire('click');await nodes.appointmentConfirm.fire('click');
  check(calls.filter(call=>call.options?.method==='POST').length===1,'double clicking confirmation saves only once');
  const submitted=JSON.parse(calls.find(call=>call.options?.method==='POST').options.body);
  check(submitted.pickup_date===fields.pickup_date&&submitted.pickup_time===fields.pickup_time&&submitted.fare_quote===fare.token,'saving binds the reviewed date/time, passengers and signed quote');
  resolveStore(response({appointment:saved,appointments:[saved]}));await first;
  check(text(nodes.appointmentList).includes('Appointment saved')&&text(nodes.appointmentList).includes('Driver search starts automatically'),'saved appointments clearly wait for the booked search time');
  check(events.includes('rideutem:appointments-changed'),'saving immediately refreshes the reminder robot');
  statusRows=[{...saved,status:'pending'}];await intervals[0]();
  check(text(nodes.appointmentList).includes('Finding a driver'),'automatic updates show driver search at the appointment time');
  statusRows=[{...saved,status:'accepted',driver:'Campus driver',vehicle:'Green car · TEST'}];await intervals[0]();
  check(text(nodes.appointmentList).includes('Campus driver')&&text(nodes.appointmentList).includes('Driver accepted'),'automatic updates show actual driver acceptance');
  statusRows=[saved];await intervals[0]();
  const row=nodes.appointmentList.children[0],actions=row.children[1],cancel=actions.children[1];await cancel.fire('click');
  check(!text(nodes.appointmentList).includes('Cancelled')&&nodes.appointmentCount.textContent==='0 appointments'&&nodes.appointmentListStatus.textContent==='Appointment cancelled.','cancellation removes the appointment card and refreshes the robot');
  console.log(`${checks} appointment review, saving, timing, cancellation and reminder client checks passed; synthetic network only.`);
})().catch(error=>{console.error(error);process.exitCode=1;});
