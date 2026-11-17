const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict'),path=require('node:path');
class Element{
  constructor(){this.hidden=false;this.textContent='';this.events={};this.children=[];this.classList={toggle(){}};}
  addEventListener(e,fn){this.events[e]=fn;} async fire(e){await this.events[e]?.({preventDefault(){}});}
  append(...nodes){this.children.push(...nodes);} replaceChildren(){this.children=[];}
}
const initial={id:1,version:'route-version',online:true,canResume:true,hasPassengers:false,pickup:'Tuah',destination:'FTMK',vehicleId:2,expiresAt:new Date(Date.now()+90000).toISOString()};
function boot(route=null){
  const ids=['driverWaitingForm','driverWaitingMessage','driverWaitingBadge','driverWaitingSummary','driverWaitingJourney','driverWaitingState','driverWaitingStop','driverWaitingResume','driverWaitingOpen'];
  const nodes=Object.fromEntries(ids.map(id=>[id,new Element()])),calls=[],intervals=[],replies={},events={};
  const fields={pickup_label:'Tuah',pickup_latitude:2.3139,pickup_longitude:102.3183,dropoff_label:'FTMK',dropoff_latitude:2.32,dropoff_longitude:102.326,vehicle_id:'2'};
  vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../public/js/driver-waiting-route.js'),'utf8'),{
    document:{getElementById:id=>nodes[id],hidden:false,addEventListener:(e,fn)=>events[e]=fn},
    window:{driverWaiting:{waiting:route,openUrl:'/open',heartbeatUrl:'/heartbeat',closeUrl:'/close',csrf:'test-csrf'},dispatchEvent(){}},
    FormData:class{[Symbol.iterator](){return Object.entries(fields)[Symbol.iterator]();}},Date,Event:class{},AbortController,
    setTimeout:()=>1,clearTimeout(){},setInterval:fn=>intervals.push(fn),
    fetch:async(url,options)=>{calls.push({url,options,body:JSON.parse(options.body)});if(replies[url])return replies[url]();return {ok:true,json:async()=>({waiting:url==='/close'?null:initial})};},
  });return {nodes,calls,replies,tick:()=>intervals[0](),fields};
}
let checks=0;function check(yes,label){assert.ok(yes,label);checks++;console.log('PASS: '+label);}
(async()=>{
  const app=boot();check(!app.nodes.driverWaitingForm.hidden&&app.calls.length===0,'opening driver page stays offline and publishes nothing');
  await app.nodes.driverWaitingForm.fire('submit');
  check(app.nodes.driverWaitingForm.hidden&&app.nodes.driverWaitingBadge.textContent.includes('Online'),'Open ride enters the online waiting section');
  check(app.calls[0].body.dropoff_label==='FTMK'&&!('departure_at' in app.calls[0].body)&&app.calls[0].options.headers['X-CSRF-TOKEN']==='test-csrf','opening posts chosen coordinates and vehicle with CSRF and no date/time');
  await app.tick();check(app.calls.at(-1).body.version===initial.version&&!app.calls.at(-1).body.resume,'background heartbeat carries the current version without silently resuming');
  await app.nodes.driverWaitingStop.fire('click');
  check(!app.nodes.driverWaitingForm.hidden&&app.nodes.driverWaitingBadge.textContent==='Offline'&&!app.nodes.driverWaitingResume.hidden,'Go offline restores route editing and exposes explicit Resume');
  const callCount=app.calls.length;await app.tick();check(app.calls.length===callCount,'offline pages do not send heartbeat requests');
  await app.nodes.driverWaitingResume.fire('click');check(app.calls.at(-1).body.resume===true&&app.nodes.driverWaitingForm.hidden,'Resume is an explicit action');
  const booked=boot({...initial,hasPassengers:true});await booked.nodes.driverWaitingStop.fire('click');
  check(booked.nodes.driverWaitingForm.hidden&&!booked.nodes.driverWaitingSummary.hidden,'going offline after acceptance preserves the route summary and prevents changing booked locations');
  const finished=boot({...initial,online:false,canResume:false,hasPassengers:false});check(!finished.nodes.driverWaitingForm.hidden&&finished.nodes.driverWaitingResume.hidden,'completed routes allow a new route to be opened');
  const expired=boot(initial);expired.replies['/heartbeat']=async()=>({ok:true,json:async()=>({waiting:null})});await expired.tick();
  check(expired.nodes.driverWaitingBadge.textContent==='Offline','an expired or withdrawn route becomes offline');
  const failed=boot();failed.replies['/open']=async()=>({ok:false,json:async()=>({errors:{dropoff_label:['Choose a destination.']}})});await failed.nodes.driverWaitingForm.fire('submit');
  check(!failed.nodes.driverWaitingForm.hidden&&failed.nodes.driverWaitingMessage.textContent==='Choose a destination.'&&!failed.nodes.driverWaitingOpen.disabled,'invalid opening displays the server error and restores the button');
  const panel=new Element(),list=new Element();const win={rideNow:{request:{status:'pending'},nearby:[]}};
  vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../public/js/nearby-rides.js'),'utf8'),{window:win,document:{getElementById:id=>id==='nearbyRideNotice'?panel:list,createElement:()=>new Element()}});
  check(panel.hidden,'no nearby route produces no misleading driver notice');
  win.renderNearbyRides([{pickup:'Tuah <script>',destination:'FTMK',driver:'Demo driver',seats:3,extraMinutes:2.5,basis:'approximate'}]);
  check(!panel.hidden&&list.children[0].children[0].textContent==='Tuah <script> → FTMK'&&list.children[0].children[2].textContent.includes('approximate'),'passenger notice renders plain text and clearly labels approximate routing');
  win.renderNearbyRides([]);check(panel.hidden&&list.children.length===0,'expired or accepted matches clear the passenger notice');
  console.log(`${checks} waiting UI and passenger notice checks passed; no real requests.`);
})().catch(e=>{console.error(e);process.exitCode=1;});
