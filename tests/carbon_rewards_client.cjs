const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
class Element {
  constructor(){this.children=[];this.dataset={};this.style={setProperty:(key,value)=>this.style[key]=value};this.textContent='';}
  append(...children){this.children.push(...children);} addEventListener(){} replaceChildren(){this.children=[];}
  querySelector(){return this.body;} text(){return this.textContent+' '+this.children.map(c=>c.text()).join(' ');}
}
const root=new Element();root.dataset.carIconUrl='/existing-car.png';const context={document:{getElementById:id=>id==='live-drivers'?root:null,createElement:()=>new Element()},window:{}};
vm.runInNewContext(fs.readFileSync(__dirname+'/../public/js/live-carbon.js','utf8'),context);
const api=context.window.LiveCarbon;
const c={status:'estimated',level:'medium',participant_g_per_km:80.08,vehicle_g_per_km:160.16,occupants:2,litres_per_100km:7,fuel_factor:2.288,passenger_seats:1};
let checks=0;function check(ok,label){assert.ok(ok,label);checks++;console.log('PASS: '+label);}
for(const [percent,level,colour] of [[0,'low','#3c9366'],[65,'low','#3c9366'],[65.01,'medium','#ce9929'],[80,'medium','#ce9929'],[80.01,'high','#cb5653'],[140,'high','#cb5653']]){
  const marker=new Element();marker.body=new Element();const ride={id:1,carbon:{...c,daily:{status:'estimated',remaining_percent:percent,baseline_kg:4,shared_kg:4*percent/100,saved_kg:4-4*percent/100}}};
  api.paintMarker(ride,marker);check(marker.dataset.carbonLevel===level&&marker.body.style['--live-carbon-colour']===colour,'daily marker colour handles '+percent+'%');
  const card=new Element();api.renderCard(card,ride);check(card.children[0].dataset.level===level&&card.text().includes(percent.toFixed(1)+'%'),'driver card explains the same daily percentage at '+percent+'%');
  check(marker.body.style['--live-carbon-mask'].includes('/existing-car.png'),'colour tint retains the existing animated car asset');
}
for(const daily of [null,{status:'unavailable',reason:'Confirm alternatives'},{status:'estimated',remaining_percent:40,baseline_kg:0,shared_kg:1,saved_kg:1}]){
  const marker=new Element();marker.body=new Element();api.paintMarker({carbon:{...c,daily}},marker);check(marker.dataset.carbonLevel==='unknown'&&marker.body.style['--live-carbon-colour']==='#85918a','missing or invalid comparison data is grey rather than invented savings');
}
console.log(`${checks} carbon colour checks passed; no GPS, network or real rewards.`);
