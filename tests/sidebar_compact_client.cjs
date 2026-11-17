const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict'),path=require('node:path');
function open(saved,blocked=false){
  let value=saved,listener,resizes=0;const attributes={},classes=new Set(),icon={textContent:''};
  const button={hidden:true,setAttribute:(k,v)=>attributes[k]=v,querySelector:()=>icon,addEventListener:(_e,fn)=>listener=fn};
  vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../public/js/sidebar-compact.js'),'utf8'),{
    document:{getElementById:()=>button,body:{classList:{toggle:(c,on)=>on?classes.add(c):classes.delete(c)}}},
    localStorage:{getItem:()=>{if(blocked)throw Error();return value;},setItem:(_k,v)=>{if(blocked)throw Error();value=v;}},
    window:{dispatchEvent:()=>resizes++},Event:class{},
  });return {button,attributes,classes,icon,click:()=>listener(),saved:()=>value,resizes:()=>resizes};
}
const expanded=open(null);assert.ok(!expanded.classes.size&&!expanded.button.hidden&&expanded.attributes['aria-expanded']==='true');
expanded.click();assert.ok(expanded.classes.has('sidebar-minimised')&&expanded.saved()==='true'&&expanded.attributes['aria-label']==='Expand sidebar'&&expanded.resizes()===1);
const next=open(expanded.saved());assert.ok(next.classes.has('sidebar-minimised'));next.click();assert.ok(!next.classes.size&&next.saved()==='false');
const denied=open(null,true);denied.click();assert.ok(denied.classes.has('sidebar-minimised'));
console.log('4 sidebar collapse, restore, persistence and unavailable-storage checks passed.');
