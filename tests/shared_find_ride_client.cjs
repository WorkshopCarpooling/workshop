const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict'),path=require('node:path');
class Element{
  constructor(tag='div'){this.tagName=tag;this.children=[];this.events={};this.dataset={};this.value='';this.hidden=false;this.disabled=false;this.textContent='';}
  append(...nodes){this.children.push(...nodes);}
  replaceChildren(...nodes){this.children=nodes;}
  setAttribute(){}
  addEventListener(event,fn){this.events[event]=fn;}
  querySelectorAll(tag){return this.children.flatMap(c=>[...(c.tagName===tag?[c]:[]),...c.querySelectorAll(tag)]);}
  querySelector(){return this.querySelectorAll('form').find(f=>f.dataset.submitting)||null;}
  get elements(){return Object.fromEntries([...this.querySelectorAll('input'),...this.querySelectorAll('select')].map(n=>[n.name,n]));}
  async fire(event){let prevented=false;await this.events[event]?.({preventDefault(){prevented=true;}});return prevented;}
}
const ids=['nowDriverRequests','nowDriverFeedStatus','nowDriverRequestCount','poolLocationStatus','poolUseLocation','poolChooseLocation','poolSetup','poolSummary','poolSchedule','poolStartMap'];
const request={id:1,pickup:'Near pickup',dropoff:'Campus',passenger:'Demo student',seats:2,total:'5.50',distanceKm:8,distanceSource:'estimated'};
let checks=0;
const check=(yes,label)=>{assert.ok(yes,label);checks++;console.log('PASS: '+label);};
async function start(pool=null,campusArea=null){
  const nodes=Object.fromEntries(ids.map(id=>[id,new Element()]));let data={requests:[request],pool},gps,interval;
  vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../public/js/driver-ride-now.js'),'utf8'),{
    document:{getElementById:id=>nodes[id],createElement:tag=>new Element(tag),hidden:false,addEventListener(){}},
    window:{driverRideNow:{ready:true,campusArea,feedUrl:'/feed',acceptUrl:'/accept',csrf:'test-csrf',vehicles:[{id:1,label:'Car A'},{id:2,label:'Car B'}]}},
    navigator:{geolocation:{getCurrentPosition:fn=>{gps=fn;}}},fetch:async()=>({ok:true,json:async()=>data}),
    location:{href:'http://localhost/workshop/driver/requests'},URL,Intl,Date,setInterval:fn=>interval=fn,
  });
  await new Promise(setImmediate);
  return {nodes,setData:value=>data=value,tick:async()=>{await interval();},gps:(latitude=2.28,longitude=102.28)=>gps({coords:{latitude,longitude,accuracy:20}})};
}
(async()=>{
  const app=await start(),n=app.nodes;let form=n.nowDriverRequests.querySelectorAll('form')[0];
  check(!await form.fire('submit')&&!('pool_seat_capacity' in form.elements),'first Accept submits directly without a passenger-count field');
  check(form.querySelectorAll('button')[0].disabled&&await form.fire('submit'),'acceptance disables its button and prevents duplicate submission');
  const pooled=await start({id:7,vehicleId:2,capacity:3,remaining:1,destination:'Campus',scheduleUrl:'/schedule'});const p=pooled.nodes;
  check(p.poolSetup.hidden&&!p.poolSummary.hidden&&p.poolSummary.textContent.includes('2/3')&&p.poolSchedule.href==='/schedule','open shared car shows occupancy and schedule action');
  const join=p.nowDriverRequests.querySelectorAll('form')[0];check(join.elements.vehicle_id.children.length===1&&join.elements.vehicle_id.children[0].value===2,'additional requests can choose only the vehicle already assigned');
  check(!await join.fire('submit'),'joining a car does not require another origin or capacity');
  const full=await start({id:7,vehicleId:2,capacity:3,remaining:0,destination:'Campus',scheduleUrl:'/schedule'});full.setData({requests:[],pool:{id:7,vehicleId:2,capacity:3,remaining:0,destination:'Campus',scheduleUrl:'/schedule'}});await full.tick();
  check(full.nodes.nowDriverRequests.children.length===0&&full.nodes.poolSummary.textContent.includes('full'),'full cars retain the occupancy summary and expose no acceptance button');
  const located=await start();await located.nodes.poolUseLocation.fire('click');located.gps();await new Promise(setImmediate);const locatedForm=located.nodes.nowDriverRequests.querySelectorAll('form')[0];
  check(!await locatedForm.fire('submit')&&locatedForm.elements.pool_origin_latitude.value===2.28,'an optional starting-pin change is retained without extra seat input');
  const campus=await start(null,{lat:2.3139,lng:102.3183,radius_km:5});
  await campus.nodes.poolUseLocation.fire('click');campus.gps(3.15,101.7);
  check(campus.nodes.poolLocationStatus.textContent.includes('previous UTeM pin'),'out-of-area device GPS cannot replace the campus starting pin');
  const campusForm=campus.nodes.nowDriverRequests.querySelectorAll('form')[0];await campusForm.fire('submit');
  check(campusForm.elements.pool_origin_latitude.value===2.3139&&campusForm.elements.pool_origin_longitude.value===102.3183,'a new driver request uses the UTeM Main Campus default');
  console.log(`${checks} shared-car client checks passed; no real location or network used.`);
})().catch(e=>{console.error(e);process.exit(1);});
