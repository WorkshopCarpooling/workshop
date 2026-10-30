// Execute the shipped widget with a controlled clock, memory storage and no network.
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict'),path=require('node:path');
const source=fs.readFileSync(path.join(__dirname,'../public/js/schedule-robot.js'),'utf8');
let checks=0;const check=(condition,label)=>{assert.ok(condition,label);console.log('PASS: '+label);checks++;};
const flush=()=>new Promise(resolve=>setImmediate(resolve));
function widget(options={}){
  let clock=Date.parse('2030-01-07T00:35:00Z');
  class Clock extends Date {constructor(...args){super(...(args.length?args:[clock]));}static now(){return clock;}}
  class Node {constructor(){this.events={};this.hidden=false;this.dataset={};this.attributes={};this.textContent='';}addEventListener(type,fn){(this.events[type]??=[]).push(fn);}fire(type,event={}){for(const fn of this.events[type]||[])fn(event);}setAttribute(key,value){this.attributes[key]=value;}hasAttribute(key){return key in this.attributes;}focus(){this.focused=true;}}
  const nodes=new Map(),get=selector=>{if(!nodes.has(selector))nodes.set(selector,new Node());return nodes.get(selector);};
  const root=new Node(),document=new Node(),window=new Node();document.hidden=false;
  const card=get('.schedule-robot-card');card.hidden=true;
  root.dataset={user:'42',endpoint:'/api/passenger/reminders',timetableUrl:'http://localhost/timetable'};
  root.querySelector=get;document.querySelector=()=>root;
  const intervals=[],storage=options.storage || new Map();let fetches=0;
  let events=options.events || [{id:'class-1',kind:'class',title:'Software engineering',location:'Campus hall',startsAt:new Date(clock+25*60000).toISOString(),url:'http://localhost/timetable',action:'View timetable'}];
  const context={console,Date:Clock,Intl,URL,AbortController,location:{href:'http://localhost/passenger/schedule',origin:'http://localhost'},document,window,
    localStorage:{getItem:key=>{if(options.blockedStorage)throw Error('disabled');return storage.get(key);},setItem:(key,value)=>{if(options.blockedStorage)throw Error('disabled');storage.set(key,value);}},
    setTimeout:()=>1,clearTimeout(){},setInterval:(fn,delay)=>intervals.push({fn,delay}),
    fetch:async()=>{fetches++;return {ok:true,status:200,redirected:false,json:async()=>({serverTime:new Date(clock).toISOString(),timezone:'Asia/Kuala_Lumpur',events})};},
  };
  vm.runInNewContext(source,context);
  return {get,root,card,document,window,storage,click:name=>get(`[data-robot-${name}]`).fire('click'),advance:ms=>{clock+=ms;intervals.filter(i=>i.delay===30000).forEach(i=>i.fn());},refresh:()=>intervals.filter(i=>i.delay===60000).forEach(i=>i.fn()),setEvents:value=>{events=value;},fetches:()=>fetches};
}
(async()=>{
  const w=widget();await flush();
  check(!w.card.hidden&&w.get('[data-robot-title]').textContent==='Software engineering','upcoming saved sessions open the robot reminder automatically');
  check(w.get('[data-robot-countdown]').textContent.includes('25 minutes')&&w.get('[data-robot-time]').textContent.includes('Malaysia time'),'reminder shows the real countdown and application timezone');
  w.click('close');w.advance(30000);check(w.card.hidden,'closing a shown reminder prevents reopening at every timer tick');
  w.advance(14.5*60000);check(!w.card.hidden&&w.get('[data-robot-kicker]').textContent==='IT’S ALMOST TIME','a second reminder appears at ten minutes before the same session');
  w.click('snooze');check(w.card.hidden,'snooze hides the current reminder');
  w.advance(4*60000);check(w.card.hidden,'snoozed reminders stay quiet for the selected interval');
  w.advance(60000);check(!w.card.hidden&&w.get('[data-robot-countdown]').textContent.includes('5 minutes'),'snoozed reminder returns after five minutes');
  w.click('dismiss');w.advance(60000);check(w.card.hidden,'dismissed occurrences stay quiet');
  const replay=widget({storage:w.storage});await flush();check(replay.card.hidden,'dismissal persists across page navigation for the same passenger');
  w.advance(10*60000);w.click('toggle');check(w.get('[data-robot-title]').textContent==='You’re all caught up!','expired sessions are removed instead of showing late countdowns');
  const noStorage=widget({blockedStorage:true});await flush();check(!noStorage.card.hidden,'reminders still work when browser storage is unavailable');
  const late={id:'later',kind:'ride',title:'Later ride',location:'Gate',startsAt:'2030-01-07T01:10:00Z',url:'http://localhost/passenger/schedule',action:'View my ride'};
  const soon={...late,id:'soon',title:'Sooner ride',startsAt:'2030-01-07T00:50:00Z'};
  const multi=widget({events:[late,soon]});await flush();check(multi.get('[data-robot-title]').textContent==='Sooner ride','the nearest upcoming event gets priority');
  multi.click('next');check(multi.get('[data-robot-title]').textContent==='Later ride','multiple upcoming events can be browsed');
  multi.setEvents([]);multi.refresh();await flush();check(multi.get('[data-robot-title]').textContent==='You’re all caught up!','removed sessions and cancelled rides disappear after refresh');
  const safe=widget({events:[{...soon,url:'https://example.invalid/private'}]});await flush();check(safe.card.hidden,'foreign-origin reminder links are rejected');
  const hidden=widget();hidden.document.hidden=true;await flush();check(hidden.card.hidden,'background tabs do not open new reminder bubbles');
  hidden.document.hidden=false;hidden.document.fire('visibilitychange');await flush();check(!hidden.card.hidden,'returning to the app checks the current reminder window');
  hidden.root.fire('keydown',{key:'Escape'});check(hidden.card.hidden&&hidden.get('[data-robot-toggle]').focused,'Escape closes the card and restores focus to the robot');
  hidden.window.fire('storage',{key:'rideutem:schedule-robot:v1:42',newValue:'null'});check(true,'invalid cross-tab storage cannot crash the widget');
  const appointment={id:'appointment-1',kind:'appointment',status:'scheduled',title:'Ride appointment to campus',location:'Main gate',passengers:2,startsAt:'2030-01-07T01:00:00Z',validUntil:'2030-01-07T02:10:00Z',url:'http://localhost/passenger/schedule',action:'View appointment'};
  const ap=widget({events:[appointment]});await flush();
  check(!ap.card.hidden&&ap.get('[data-robot-countdown]').textContent.includes('Driver search starts in 25 minutes'),'robot reminds the passenger before an unassigned appointment');
  check(ap.get('[data-robot-location]').textContent.includes('2 passengers'),'appointment reminders show the requested passenger count');
  ap.advance(25*60000);ap.setEvents([{...appointment,status:'pending'}]);ap.refresh();await flush();
  check(ap.get('[data-robot-kicker]').textContent==='FINDING YOUR DRIVER'&&ap.get('[data-robot-countdown]').textContent.includes('waiting for one to accept'),'due appointments report actual searching rather than a confirmed car');
  ap.click('dismiss');ap.setEvents([{...appointment,status:'accepted',driver:'Campus driver'}]);ap.refresh();await flush();
  check(!ap.card.hidden&&ap.get('[data-robot-countdown]').textContent.includes('Campus driver accepted'),'real driver acceptance still appears after dismissing the earlier countdown or search reminder');
  ap.setEvents([{...appointment,status:'expired'}]);ap.refresh();await flush();
  check(ap.get('[data-robot-kicker]').textContent==='NO DRIVER CONFIRMED','the robot explains an unmatched appointment without inventing a car');
  console.log(`${checks} robot timing, snooze, dismissal, privacy, refresh and keyboard checks passed; no real schedule or network used.`);
})().catch(error=>{console.error(error);process.exit(1);});
