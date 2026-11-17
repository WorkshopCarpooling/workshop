// Run the shipped one-click search flow against a synthetic DOM and network.
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict'),path=require('node:path');
class Element {
  constructor(){this.events={};this.hidden=false;this.disabled=false;this.textContent='';this.classes=new Set();this.classList={toggle:(name,on)=>on?this.classes.add(name):this.classes.delete(name),contains:name=>this.classes.has(name)};}
  focus(){this.focused=true;} scrollIntoView(){this.scrolled=true;}
  addEventListener(name,action){(this.events[name]??=[]).push(action);}
  async fire(name){for(const action of this.events[name]||[])await action({preventDefault(){}});}
}
const ids=['rideNowForm','nowSearchStatus','findDriversButton','rideRequestPanel','rideNowSearch','rideRequestTitle','rideRequestMessage','rideRequestRoute','rideRequestFare','rideRequestDriver','rideSharedSummary','rideNavigationLink','cancelRideRequest','rideRequestBooking','rideSearchAnimation','rideNowIntro','rideNowLater','rideWaitingHint','rideRequestEyebrow','rideWaitingPickup','rideWaitingDropoff'];
const fare={perSeat:'4.00',total:'4.00',distanceKm:10,seats:1,token:'synthetic-quote',distanceSource:'corridor_estimate',expiresAt:new Date(Date.now()+600000).toISOString()};
const pending={id:1,status:'pending',pickup:'Pickup A',dropoff:'Campus',seats:1,total:'4.00',rideId:null};
const accepted={...pending,status:'accepted',rideId:9,driver:'Campus driver',vehicle:'Green car · TEST'};
const answer=(data,ok=true)=>({ok,json:async()=>data});
function start(initial=null){
  const nodes=Object.fromEntries(ids.map(id=>[id,new Element()])),calls=[],intervals=[],timers=new Map(),replies={};let timer=0;
  const fields={pickup_label:'Pickup A',pickup_latitude:'2.2707',pickup_longitude:'102.2856',dropoff_label:'Campus',dropoff_latitude:'2.3139',dropoff_longitude:'102.3183',seats_requested:'1'};
  vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../public/js/ride-now.js'),'utf8'),{
    document:{getElementById:id=>{assert.ok(nodes[id],'unexpected/retired DOM node '+id);return nodes[id];},hidden:false,addEventListener(){}},
    window:{rideNow:{searchUrl:'/findride.php',requestUrl:'/findride.php',statusUrl:'/status',cancelBase:'/cancel',csrf:'synthetic-csrf',request:initial}},
    location:{href:'http://localhost/workshop/findride.php'},URL,URLSearchParams,Date,AbortController,
    FormData:class{[Symbol.iterator](){return Object.entries(fields)[Symbol.iterator]();}},
    setTimeout:(action,delay)=>{const id=++timer;timers.set(id,{action,delay});return id;},clearTimeout:id=>timers.delete(id),setInterval:action=>intervals.push(action),
    fetch:async(url,options)=>{
      calls.push({url:String(url),options});
      const kind=String(url)==='/status'?'status':String(url).startsWith('/cancel')?'cancel':options?.method==='POST'?'create':'search';
      if(typeof replies[kind]==='function')return await replies[kind](url,options);
      return replies[kind]??answer(kind==='search'?{fare,bookingReady:true}:{request:kind==='cancel'?{...pending,status:'cancelled'}:pending});
    },
  });
  return {nodes,calls,intervals,timers,replies,fields,submit:()=>nodes.rideNowForm.fire('submit')};
}
let checks=0;const check=(condition,label)=>{assert.ok(condition,label);checks++;console.log('PASS: '+label);};
(async()=>{
  const app=start(),{nodes,calls,replies}=app;
  check(calls.length===0,'opening Find Ride does not send a request');
  let resolveQuote;replies.search=()=>new Promise(resolve=>resolveQuote=resolve);
  const first=app.submit();
  check(!nodes.rideSearchAnimation.hidden&&nodes.rideNowSearch.hidden&&nodes.rideRequestTitle.textContent==='Starting your search','one Search click immediately opens the animated waiting screen');
  check(nodes.cancelRideRequest.disabled&&nodes.rideRequestFare.textContent.includes('Calculating'),'preparation is labelled honestly until the server creates the request');
  await app.submit();await nodes.cancelRideRequest.fire('click');
  check(calls.length===1,'repeated Search clicks and premature cancellation cannot duplicate a submission');
  resolveQuote(answer({fare,bookingReady:true}));await first;
  check(calls.length===2&&calls[1].options.method==='POST','the route check automatically sends the request without another confirmation');
  const body=JSON.parse(calls[1].options.body);
  check(body.fare_quote===fare.token&&body.pickup_label===app.fields.pickup_label&&calls[1].options.headers['X-CSRF-TOKEN']==='synthetic-csrf','automatic request retains the trusted fare, chosen locations and CSRF protection');
  check(!calls[0].url.includes('departure_at')&&!calls[0].url.includes('arrive_by'),'Search remains an immediate ride with no scheduling fields');
  check(nodes.rideRequestTitle.textContent==='Finding a driver'&&!nodes.cancelRideRequest.disabled&&!nodes.cancelRideRequest.hidden,'a real pending request enables cancellation');
  check(nodes.rideWaitingPickup.textContent===pending.pickup&&nodes.rideWaitingDropoff.textContent===pending.dropoff&&nodes.rideRequestFare.textContent.includes('4.00'),'the waiting screen displays the actual requested route and fare');
  await app.submit();check(calls.length===2,'an active request prevents another Search submission');
  let resolvePoll;replies.status=()=>new Promise(resolve=>resolvePoll=resolve);
  const stalePoll=app.intervals[0]();await nodes.cancelRideRequest.fire('click');
  resolvePoll(answer({request:pending}));await stalePoll;
  check(nodes.rideSearchAnimation.hidden&&!nodes.rideNowSearch.hidden&&nodes.rideRequestPanel.hidden,'successful cancellation restores Search without a recent-cancelled card, even after a stale poll');
  const reopened=start({...pending,status:'cancelled'});
  check(reopened.nodes.rideRequestPanel.hidden&&!reopened.nodes.rideNowSearch.hidden,'reopening Find Ride does not restore a cancelled request card');
  const live=start(pending);
  check(live.nodes.rideNowSearch.hidden&&!live.nodes.rideSearchAnimation.hidden,'reloading an active request resumes waiting without resubmitting');
  live.replies.status=answer({request:accepted});await live.intervals[0]();
  check(live.nodes.rideSearchAnimation.hidden&&live.nodes.cancelRideRequest.hidden&&!live.nodes.rideRequestBooking.hidden&&live.nodes.rideRequestDriver.textContent.includes('Campus driver'),'real driver acceptance stops waiting and shows the driver and booking');
  live.replies.status=answer({request:{...accepted,status:'completed'}});await live.intervals[0]();
  check(!live.nodes.rideNowSearch.hidden&&live.nodes.rideRequestTitle.textContent==='You’ve arrived','a completed ride permits another Search');
  const shared=start({...accepted,shared:{passengers:2,capacity:3,remaining:1,pickupPosition:2,open:true}});
  check(!shared.nodes.rideSharedSummary.hidden&&shared.nodes.rideSharedSummary.textContent.includes('2/3')&&shared.nodes.rideSharedSummary.textContent.includes('stop 2')&&shared.nodes.rideSharedSummary.textContent.includes('may change'),'shared acceptance shows occupancy and the passenger own pickup position');
  const expired=start(pending);expired.replies.status=answer({request:{...pending,status:'expired'}});await expired.intervals[0]();
  const liveMap=start({...accepted,status:'in_progress',navigationUrl:'/trips/9/map'});
  check(!liveMap.nodes.rideNavigationLink.hidden&&liveMap.nodes.rideNavigationLink.href==='/trips/9/map','only an in-progress ride exposes the live map');
  liveMap.replies.status=answer({request:{...accepted,status:'completed',navigationUrl:'/trips/9/map'}});await liveMap.intervals[0]();
  check(liveMap.nodes.rideNavigationLink.hidden,'completion hides even a stale live-map link');
  check(expired.nodes.rideSearchAnimation.hidden&&!expired.nodes.rideNowSearch.hidden,'an unmatched request ends the animation and restores Search');
  for(const [reply,label] of [[answer({errors:{pickup_label:['Choose a pickup.']}},false),'route validation errors'],[answer({fare,bookingReady:false}),'unavailable ride requests'],[answer({fare:{...fare,expiresAt:'2020-01-01'},bookingReady:true}),'expired fares']]){
    const failed=start();failed.replies.search=reply;await failed.submit();
    check(failed.calls.length===1&&!failed.nodes.rideNowSearch.hidden&&failed.nodes.rideSearchAnimation.hidden&&!failed.nodes.findDriversButton.disabled,'Search recovers from '+label+' without broadcasting a request');
  }
  const unknown=start();unknown.replies.create=async()=>{throw new Error('Connection lost.');};await unknown.submit();
  check(unknown.nodes.rideRequestTitle.textContent==='Finding a driver'&&unknown.calls.filter(c=>c.options.method==='POST').length===1,'a lost send response checks server status and recovers the real request without reposting');
  const rejected=start();rejected.replies.create=answer({message:'Fare changed. Please search again.'},false);rejected.replies.status=answer({request:null});await rejected.submit();
  check(!rejected.nodes.rideNowSearch.hidden&&rejected.nodes.rideSearchAnimation.hidden&&rejected.nodes.nowSearchStatus.textContent.includes('Fare changed'),'a rejected request returns to Search with the real error');
  const timeout=start();timeout.replies.search=(_url,options)=>new Promise((_resolve,reject)=>options.signal.addEventListener('abort',()=>reject(Object.assign(new Error('Timed out'),{name:'AbortError'}))));
  const slow=timeout.submit();[...timeout.timers.values()][0].action();await slow;
  check(timeout.nodes.rideSearchAnimation.hidden&&!timeout.nodes.findDriversButton.disabled&&timeout.nodes.nowSearchStatus.textContent.includes('timed out'),'a stalled connection times out and restores Search');
  const cancelFail=start(pending);cancelFail.replies.cancel=answer({message:'Could not cancel. Try again.'},false);await cancelFail.nodes.cancelRideRequest.fire('click');await cancelFail.intervals[0]();
  check(!cancelFail.nodes.rideSearchAnimation.hidden&&!cancelFail.nodes.cancelRideRequest.disabled&&cancelFail.nodes.rideRequestMessage.textContent.includes('Could not cancel'),'failed cancellation leaves the real pending search available to retry');
  cancelFail.replies.status=answer({request:accepted});await cancelFail.intervals[0]();
  check(cancelFail.nodes.rideSearchAnimation.hidden&&cancelFail.nodes.rideRequestTitle.textContent==='Your driver accepted','acceptance racing a cancellation is not reported as cancelled');
  const html=fs.readFileSync(path.join(__dirname,'../resources/views/findride.blade.php'),'utf8');
  check(!/toggleRideSearchAnimation|Pause animation|Play animation|requestRideNow|rideNowQuote/.test(html),'the page has no animation controls or extra ride-confirmation step');
  console.log(`${checks} one-click ride search, waiting and cancellation checks passed; no real GPS or network.`);
})().catch(error=>{console.error(error);process.exitCode=1;});
