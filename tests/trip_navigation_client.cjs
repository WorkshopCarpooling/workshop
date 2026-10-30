const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict'),path=require('node:path');
class Node{
  constructor(){this.hidden=false;this.disabled=false;this.textContent='';this.style={};this.children=[];this.events={};this.classList={toggle(){}};}
  append(...n){this.children.push(...n);}replaceChildren(...n){this.children=n;}setAttribute(){}addEventListener(k,f){this.events[k]=f;}querySelector(){return {style:{}};}
}
const stops=[{key:'start',kind:'start',lat:2.31,lng:102.31,label:'Start',done:true},{key:'pickup-1',kind:'pickup',lat:2.311,lng:102.31,label:'Pickup A',done:false},{key:'dropoff-1',kind:'dropoff',lat:2.311,lng:102.311,label:'Campus',done:false}];
async function start(){
  const nodes={},calls=[],frames=new Map(),redirects=[],events={};let interval,seq=0,car,reply,status=200,removed=0,reloaded=0;
  const state={status:'in_progress',isDriver:true,stops:structuredClone(stops),next:stops[1],gps:'waiting',location:null,allDroppedOff:false};reply=structuredClone(state);
  const get=id=>nodes[id]??=new Node();
  const layer=()=>({addTo(){return this;},setLatLngs(){return this;},bindTooltip(){return this;},setOpacity(v){this.opacity=v;return this;},setLatLng(v){this.position=v;return this;},getElement(){return new Node();}});
  const map={setView(){return this;},fitBounds(){},panTo(){},on(){},remove(){removed++;}};
  const context={window:{tripNavigation:{state,route:{road:true,source:'osrm',path:stops,durationSeconds:240,distanceMeters:1800},carIconUrl:'/images/tracking-car-pixel.svg',feedUrl:'/feed',advanceUrl:'/advance',returnUrl:'/rides',csrf:'token'},addEventListener:(name,fn)=>events[name]=fn,location:{replace:url=>redirects.push(url),reload:()=>reloaded++}},
    document:{getElementById:get,createElement:()=>new Node(),addEventListener(){},hidden:false},
    L:{map:()=>map,tileLayer:layer,polyline:layer,latLngBounds:v=>v,divIcon:v=>v,marker:(_p,o)=>{const m=layer();if(o.zIndexOffset)car=m;return m;}},
    fetch:async(url,options)=>{calls.push({url,options});return {ok:status===200,status,json:async()=>reply.ended?structuredClone(reply):({navigation:structuredClone(reply)})};},
    Date,structuredClone,performance:{now:()=>0},matchMedia:()=>({matches:false}),AbortSignal,
    requestAnimationFrame:fn=>{const id=++seq;frames.set(id,fn);return id;},cancelAnimationFrame:id=>frames.delete(id),setInterval:fn=>interval=fn,
  };
  context.window.L=context.L;vm.createContext(context);for(const file of ['trip-navigation-geometry.js','trip-navigation.js'])vm.runInContext(fs.readFileSync(path.join(__dirname,'../public/js/'+file),'utf8'),context);
  await new Promise(setImmediate);
  return {nodes,calls,car,redirects,events,removed:()=>removed,reloaded:()=>reloaded,setReply:(r,s=200)=>{reply=r;status=s;},tick:async()=>{await interval();},frame:t=>{const q=[...frames.values()];frames.clear();q.forEach(fn=>fn(t));},state};
}
(async()=>{
  const live=await start();assert.equal(live.car.opacity,0);live.frame(60000);assert.equal(live.car.opacity,0);
  const fix={...live.state,gps:'live',location:{lat:2.3105,lng:102.31,accuracy:8,capturedAt:Date.now()/1000}};live.setReply(fix);await live.tick();
  assert.equal(live.car.opacity,1);assert.ok(live.nodes.navigationMode.textContent.includes('LIVE GPS'));
  live.setReply({...fix,gps:'stale',location:{...fix.location,capturedAt:Date.now()/1000-31}});await live.tick();
  assert.ok(live.nodes.navigationGps.textContent.includes('stale')&&live.nodes.navigationMode.textContent.includes('LAST KNOWN'));
  live.setReply({...fix,next:stops[2],stops:stops.map((s,i)=>({...s,done:i<2}))});await live.nodes.navigationConfirm.events.click();
  const post=live.calls.find(c=>c.options.method==='POST');assert.ok(post.options.headers['X-CSRF-TOKEN']==='token'&&JSON.parse(post.options.body).stop_key==='pickup-1');
  assert.ok(live.nodes.navigationConfirm.textContent.includes('drop-off'));
  live.setReply({ended:true,redirectUrl:'/rides'});await live.tick();assert.equal(live.car.opacity,0);assert.ok(live.nodes.navigationConfirm.hidden&&live.removed()===1&&live.redirects[0]==='/rides'&&live.nodes.navigationStops.children.length===0);
  const endedCalls=live.calls.length;await live.tick();assert.equal(live.calls.length,endedCalls);
  const revoked=await start();revoked.setReply(fix,403);await revoked.tick();assert.ok(revoked.removed()===1&&revoked.redirects[0]==='/rides');
  const cancelled=await start();cancelled.setReply({...cancelled.state,status:'cancelled'});await cancelled.tick();assert.ok(cancelled.removed()===1&&cancelled.redirects[0]==='/rides');
  cancelled.events.pageshow({persisted:true});assert.equal(cancelled.reloaded(),1);
  console.log('Live-only navigation checks passed: GPS movement, stale fixes, stop confirmation, completion/cancellation redirects, revoked access and browser-back revalidation.');
})().catch(e=>{console.error(e);process.exit(1);});
