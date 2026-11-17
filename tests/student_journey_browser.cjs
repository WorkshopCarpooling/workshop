// Execute the shipped client code with synthetic GPS, maps and fetch; no real location or network.
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict'),path=require('node:path');
let checks=0;
const check=(ok,label)=>{assert.ok(ok,label);checks++;console.log('PASS: '+label);};
const flush=()=>new Promise(resolve=>setImmediate(resolve));
class Event {
  constructor(type){this.type=type;this.stopped=false;this.defaultPrevented=false;}
  preventDefault(){this.defaultPrevented=true;}stopImmediatePropagation(){this.stopped=true;}
}
class Element {
  constructor(tag='div'){this.tagName=tag.toUpperCase();this.events={};this.children=[];this.dataset={};this.value='';this.textContent='';this.hidden=false;this.disabled=false;this.attributes={};this.validation='';}
  addEventListener(name,fn){(this.events[name]??=[]).push(fn);}
  dispatchEvent(e){e.currentTarget=this;for(const fn of this.events[e.type]||[]){fn(e);if(e.stopped)break;}return !e.defaultPrevented;}
  append(...nodes){this.children.push(...nodes);}replaceChildren(...nodes){this.children=nodes;}
  setAttribute(key,value){this.attributes[key]=value;}hasAttribute(key){return key in this.attributes;}
  setCustomValidity(value){this.validation=value;}reportValidity(){return !this.validation;}
  querySelectorAll(selector){const all=this.children.flatMap(c=>[c,...c.querySelectorAll(selector)]);return selector==='button'?all.filter(c=>c.tagName==='BUTTON'):[];}
}
const nodes=new Map(),get=key=>{if(!nodes.has(key))nodes.set(key,new Element());return nodes.get(key);};
const picker=new Element(),form=new Element('form'),document=new Element();
nodes.set('journeySearchForm',form);
picker.attributes['data-auto-pickup']='';picker.closest=()=>form;picker.querySelector=get;
const pinButtons=['pickup','dropoff'].map(pin=>{const b=new Element('button');b.dataset.pin=pin;return b;});
picker.querySelectorAll=selector=>selector==='[data-pin]'?pinButtons:[];
document.querySelector=()=>picker;document.getElementById=get;document.createElement=tag=>new Element(tag);
const fields={};for(const key of ['pickup_label','pickup_latitude','pickup_longitude','dropoff_label','dropoff_latitude','dropoff_longitude','seats_requested','departure_at','arrive_by'])fields[key]=new Element('input');
fields.seats_requested.value='1';form.elements={...fields,namedItem:name=>fields[name]};
form.checkValidity=()=>!Object.values(fields).some(f=>f.validation);
form.requestSubmit=()=>form.dispatchEvent(new Event('submit'));
get('[data-pickup-manual]').hidden=true;
const timers=new Map();let nextTimer=0;
const setTimeoutFake=(fn,delay)=>{const id=++nextTimer;timers.set(id,{fn,delay});return id;};
const runTimer=delay=>{for(const [id,t] of [...timers])if(t.delay===delay){timers.delete(id);t.fn();}};
let gpsSuccess,gpsFailure,gpsRequests=0,routeResolver,searchResolver,reverseResolver,reverseFailure=false;
const calls=[],mapEvents={};
const map={setView(){return this;},on(name,fn){mapEvents[name]=fn;},fitBounds(){}};
const layer=()=>({addTo(){return this;},on(){return this;},remove(){},setLatLng(){}});
const trip={source:'google_routes_api',distanceMeters:10000,distanceText:'10.0 km',durationText:'15 mins',fare:{perSeat:'4.00'},path:[{lat:2.27,lng:102.28},{lat:2.31,lng:102.32}]};
const ride={id:7,driver:'Synthetic driver',pickup:'Published start',destination:'Campus',vehicle:'Synthetic car',departureAt:'Tomorrow',seats:3,cost:'8.00',driverDistanceKm:.4,bookingStatus:null,fare:{perSeat:'4.00',total:'4.00',distanceKm:10,distanceSource:'google_routes_api',seats:1,token:'synthetic-signed-quote',expiresAt:new Date(Date.now()+600000).toISOString()},match:{score:90,breakdown:{route:40,schedule:35,seats:15},pickupAt:'8:00 AM',arrivalAt:'8:15 AM',estimateSource:'Baseline estimate'}};
const sandbox={
  console,URL,URLSearchParams,Date,CustomEvent:Event,Event,
  FormData:class {constructor(){this.values=Object.entries(fields).map(([k,v])=>[k,v.value]);}[Symbol.iterator](){return this.values[Symbol.iterator]();}},
  document,location:{href:'http://localhost/workshop/findride.php'},
  navigator:{geolocation:{getCurrentPosition(ok,fail){gpsRequests++;gpsSuccess=ok;gpsFailure=fail;}}},
  L:{map:()=>map,tileLayer:layer,marker:layer,divIcon:x=>x,polyline:layer},
  setTimeout:setTimeoutFake,clearTimeout:id=>timers.delete(id),
  fetch:async url=>{calls.push(String(url));if(String(url).includes('/places/reverse')){if(reverseResolver)return await new Promise(resolve=>{reverseResolver.resolve=resolve;});return {ok:!reverseFailure,json:async()=>({label:Number(new URL(url).searchParams.get('lat'))===2.3139?'UTeM Main Campus':'Detected pickup, Ayer Keroh'})};}if(String(url).includes('/places/search'))return {ok:true,json:async()=>({results:[{label:'UTeM Main Campus',lat:2.3139,lng:102.3183}]})};if(searchResolver)return await new Promise(resolve=>{searchResolver.resolve=resolve;});return {ok:true,json:async()=>({rides:[{...ride,fare:{...ride.fare,seats:Number(fields.seats_requested.value),total:(4*Number(fields.seats_requested.value)).toFixed(2)}}],bookingReady:true})};},
  window:{isSecureContext:true,googleMapsApiKey:'',availableRides:[],bookingReady:true,bookingCsrf:'synthetic-csrf',rideSearchUrl:'/rides',locationSearchUrl:'/places/search',buildGoogleMapsDirectionsUrl:()=> 'https://www.google.com/maps/dir/',computeGoogleRoute:async()=>routeResolver?await new Promise(resolve=>{routeResolver.resolve=resolve;}):trip},
};
sandbox.window.L=sandbox.L;
let googleRequests=0;
sandbox.window.googleMapsApiKey='synthetic-configured-key';
sandbox.window.loadGoogleMaps=()=>{googleRequests++;return Promise.reject(new Error('synthetic Google SDK failure'));};
vm.createContext(sandbox);
for(const file of ['journey-picker.js','journey-search.js'])vm.runInContext(fs.readFileSync(path.join(__dirname,'../public/js',file),'utf8'),sandbox);
const textTree=n=>n.textContent+' '+n.children.map(textTree).join(' ');
const bookingForms=()=>get('rideResults').children.flatMap(c=>c.children.filter(x=>x.tagName==='FORM'));
(async()=>{
  check(typeof mapEvents.click==='function'&&googleRequests===0,'the location map loads independently of a configured or broken Google Maps key');
  document.dispatchEvent(new Event('DOMContentLoaded'));
  check(gpsRequests===1&&get('[data-pickup-manual]').hidden,'opening the destination screen requests GPS without requiring typed pickup');
  gpsSuccess({coords:{latitude:2.2707,longitude:102.2856,accuracy:12}});await flush();
  check(fields.pickup_label.value==='Detected pickup, Ayer Keroh'&&fields.pickup_latitude.value==='2.2707000','GPS fills the exact hidden pickup and resolves its place name');
  check(calls.filter(url=>url.includes('/rides')).length===0,'GPS alone does not search for rides without a destination');
  fields.dropoff_label.value='UTeM';get('[data-search="dropoff"]').dispatchEvent(new Event('click'));await flush();
  get('[data-results="dropoff"]').children[0].dispatchEvent(new Event('click'));await flush();
  runTimer(600);await flush();
  check(calls.some(u=>u.includes('/rides?')&&u.includes('pickup_latitude=2.2707000')),'choosing only a destination automatically searches using detected pickup');
  check(textTree(get('rideResults')).includes('Nearest compatible driver · 0.40 km'),'nearest planned driver and calculated distance are displayed');
  check(textTree(get('rideResults')).includes('RM 4.00 / seat · RM 4.00 total'),'generated RM price is displayed');
  let booking=bookingForms()[0],button=booking.children.find(n=>n.tagName==='BUTTON');
  check(!button.disabled&&booking.children.some(n=>n.name==='fare_quote'&&n.value==='synthetic-signed-quote'),'booking submits the server quote with detected coordinates');
  fields.seats_requested.value='2';form.dispatchEvent(new Event('change'));runTimer(600);await flush();
  check(textTree(get('rideResults')).includes('RM 4.00 / seat · RM 8.00 total'),'passenger-count changes automatically re-quote the total');
  fields.dropoff_label.value='Another place';fields.dropoff_label.dispatchEvent(new Event('input'));form.dispatchEvent(new Event('input'));await flush();
  check(bookingForms()[0].children.find(n=>n.tagName==='BUTTON').disabled,'editing a destination immediately disables stale booking buttons');
  check(!sandbox.window.journeyPicker.point('dropoff'),'unconfirmed destination text clears the old coordinates');
  mapEvents.click({latlng:{lat:2.3139,lng:102.3183}});await flush();runTimer(600);await flush();
  check(fields.dropoff_latitude.value==='2.3139000','the default map target is destination and pin selection triggers matching');
  get('[data-auto-locate]').dispatchEvent(new Event('click'));
  sandbox.window.journeyPicker.setPoint('pickup',2.27,102.28,'Manual safe pickup');
  gpsSuccess({coords:{latitude:1,longitude:1,accuracy:10}});await flush();
  check(fields.pickup_label.value==='Manual safe pickup','a delayed GPS response cannot overwrite a pickup selected by the user');
  get('[data-auto-locate]').dispatchEvent(new Event('click'));gpsFailure({code:1});await flush();
  check(!get('[data-pickup-manual]').hidden&&get('[data-gps-status]').textContent.includes('declined'),'permission denial reveals an accessible manual pickup fallback');
  // A response started for the previous destination must not enable an obsolete booking.
  searchResolver={};form.requestSubmit();await flush();const oldResolve=searchResolver.resolve;
  fields.dropoff_label.value='Changed again';fields.dropoff_label.dispatchEvent(new Event('input'));form.dispatchEvent(new Event('input'));await flush();
  oldResolve({ok:true,json:async()=>({rides:[ride],bookingReady:true})});await flush();searchResolver=null;
  check(bookingForms()[0].children.find(n=>n.tagName==='BUTTON').disabled,'out-of-order search responses cannot restore stale booking actions');
  sandbox.window.journeyPicker.setPoint('dropoff',2.3139,102.3183,'Campus');await flush();runTimer(600);await flush();
  const expiry=[...timers].find(([,t])=>t.delay>10000);timers.delete(expiry[0]);expiry[1].fn();
  check(bookingForms()[0].children.find(n=>n.tagName==='BUTTON').disabled&&get('searchStatus').textContent.includes('expired'),'expired quotes disable booking until refreshed');
  check(get('[data-route-summary]').textContent.includes('Student estimate RM 4.00'),'route preview shows kilometres, time and student fare');
  reverseResolver={};sandbox.window.journeyPicker.setPoint('dropoff',2.4,102.4);await flush();const olderPin=reverseResolver.resolve;
  check(fields.dropoff_label.value==='Pinned drop-off'&&!get('[data-coordinates="dropoff"]').textContent.includes('2.400'),'a new pin immediately uses a readable placeholder instead of coordinates');
  reverseResolver={};sandbox.window.journeyPicker.setPoint('dropoff',2.5,102.5);await flush();const newerPin=reverseResolver.resolve;
  newerPin({ok:true,json:async()=>({label:'New campus gate'})});await flush();olderPin({ok:true,json:async()=>({label:'Old campus gate'})});await flush();reverseResolver=null;
  check(fields.dropoff_label.value==='New campus gate'&&fields.dropoff_latitude.value==='2.5000000','a late address response cannot replace the newest pin or move its position');
  reverseFailure=true;sandbox.window.journeyPicker.setPoint('dropoff',2.6,102.6);await flush();reverseFailure=false;
  check(fields.dropoff_label.value==='Pinned drop-off'&&fields.dropoff_latitude.value==='2.6000000'&&get('[data-coordinates="dropoff"]').textContent.includes('unavailable'),'failed lookup retains the pin without inventing an address or showing coordinates');
  reverseResolver={};sandbox.window.journeyPicker.setPoint('dropoff',2.7,102.7);await flush();const submitName=reverseResolver.resolve;
  const attempted=new Event('submit');form.dispatchEvent(attempted);
  check(attempted.defaultPrevented,'Search waits for the pinned place name before submitting');
  submitName({ok:true,json:async()=>({label:'Named campus stop'})});await flush();reverseResolver=null;
  check(fields.dropoff_label.value==='Named campus stop'&&calls.some(url=>url.includes('/rides?')&&url.includes('Named+campus+stop')),'the queued Search resumes automatically with the resolved name');
  console.log(`${checks} automatic-pickup, destination, fare and stale-request checks passed; no real GPS or network used.`);
})().catch(error=>{console.error(error);process.exit(1);});
