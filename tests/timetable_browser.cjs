const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync(require('node:path').join(__dirname,'../public/js/journey-search.js'),'utf8');
function choose(now,day,time){
  class Clock extends Date {constructor(...args){super(...(args.length?args:[now]));}static now(){return new Date(now).getTime();}}
  const nodes=new Map(),node=()=>({value:'',textContent:'',children:[],listeners:{},addEventListener(k,fn){this.listeners[k]=fn;},replaceChildren(){this.children=[];},append(n){this.children.push(n);},querySelectorAll(){return [];}});
  const get=id=>{if(!nodes.has(id))nodes.set(id,node());return nodes.get(id);};
  const form=get('journeySearchForm');let invalidated=false;
  form.elements={departure_at:node(),dropoff_label:node()};
  form.elements.dropoff_label.dispatchEvent=()=>{invalidated=true;};
  const context={Date:Clock,document:{getElementById:get,createElement:node},window:{availableRides:[]},setTimeout(){},clearTimeout(){},Event:class {}};
  vm.runInNewContext(source,context);
  get('timetableJourney').listeners.change({target:{selectedOptions:[{dataset:{day:String(day),time,location:'Campus lecture hall'}}]}});
  assert.equal(form.elements.dropoff_label.value,'Campus lecture hall');assert.ok(invalidated,'the copied location requires its pin to be confirmed');
  assert.ok(get('searchStatus').textContent.includes('Departure suggested'));
  return form.elements.departure_at.value;
}
assert.equal(choose('2030-01-07T07:00:00',1,'09:00'),'2030-01-07T07:50');
assert.equal(choose('2030-01-07T08:55:00',1,'09:00'),'2030-01-07T08:56');
assert.equal(choose('2030-01-06T23:50:00',1,'00:05'),'2030-01-06T23:51');
assert.equal(choose('2030-01-06T07:00:00',0,'09:00'),'2030-01-06T07:50');
assert.equal(choose('2030-01-07T09:05:00',1,'09:00'),'2030-01-14T07:50');
console.log('PASS: timetable suggests departure without an arrival field, handles upcoming/past classes, midnight and Sunday, and invalidates unconfirmed pins.');
