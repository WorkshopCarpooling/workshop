(() => {
  'use strict';
  window.NavigationDemo = {
    attach({map,path,carIconUrl,onChange}) {
      const get=id=>document.getElementById(id),play=get('navigationDemoPlay'),exit=get('navigationDemoExit'),status=get('navigationDemoStatus');
      if(!play||!exit||!status||!window.L||path.points.length<2)return null;
      const G=window.NavigationGeometry,duration=10000,stops=window.tripNavigation.state.stops;
      let active=false,frame=null,startTime=null;
      const body=document.createElement('div');body.className='navigation-car-body navigation-demo-car';
      const picture=document.createElement('img');picture.src=carIconUrl;picture.alt='Simulated driver car';picture.draggable=false;body.append(picture);
      const marker=L.marker([path.points[0].lat,path.points[0].lng],{title:'DEMO car — simulated route preview',
        icon:L.divIcon({className:'navigation-car-marker navigation-demo-marker',html:body,iconSize:[42,48],iconAnchor:[21,24]}),opacity:0,zIndexOffset:1200}).addTo(map);
      const trail=L.polyline([],{color:'#c38a26',weight:5,opacity:.9,dashArray:'6 5'}).addTo(map);
      const targets=[];let minimum=0;
      for(const stop of stops){
        if(!['pickup','dropoff'].includes(stop.kind))continue;
        const projected=G.project(path,stop,minimum);minimum=Math.max(minimum,projected.along);
        targets.push({along:Math.min(path.total,minimum),kind:stop.kind,label:stop.label});
      }
      if(!targets.length||targets.at(-1).along<path.total-1)targets.push({along:path.total,kind:'end',label:'Destination'});
      const holds=targets.filter(t=>t.kind==='pickup').length,hold=holds?Math.min(600,2000/holds):0,moving=duration-holds*hold;
      const timeline=[];let elapsed=0,along=0;
      for(const target of targets){
        const segment=path.total>0?moving*Math.max(0,target.along-along)/path.total:0;
        timeline.push({begin:elapsed,end:elapsed+segment,from:along,to:target.along,target,holding:false});elapsed+=segment;along=target.along;
        if(target.kind==='pickup'){timeline.push({begin:elapsed,end:elapsed+hold,from:along,to:along,target,holding:true});elapsed+=hold;}
      }
      function place(distance){
        const point=G.at(path,distance);marker.setOpacity(1);marker.setLatLng([point.lat,point.lng]);
        const element=marker.getElement()?.querySelector('.navigation-demo-car');if(element)element.style.transform=`rotate(${point.heading||0}deg)`;
        const passed=path.points.filter((_p,i)=>path.cumulative[i]<distance);trail.setLatLngs([...passed.map(p=>[p.lat,p.lng]),[point.lat,point.lng]]);
      }
      function step(time){
        if(!active)return;if(startTime===null)startTime=time;
        const elapsedMs=Math.min(duration,Math.max(0,time-startTime));
        const segment=timeline.find(s=>elapsedMs<s.end)||timeline.at(-1);
        const ratio=segment.end>segment.begin?Math.min(1,Math.max(0,(elapsedMs-segment.begin)/(segment.end-segment.begin))):1;
        place(elapsedMs>=duration?path.total:segment.from+(segment.to-segment.from)*ratio);
        status.textContent=elapsedMs>=duration?'DEMO complete · simulated drop-off reached. No real stops or trip records were changed.':
          segment.holding?`DEMO · simulated pickup at ${segment.target.label}`:`DEMO · driving to ${segment.target.label} · ${Math.ceil((duration-elapsedMs)/1000)}s`;
        if(elapsedMs<duration)frame=requestAnimationFrame(step);else{frame=null;play.disabled=false;play.textContent='Replay 10-second demo';}
      }
      function stop(){
        if(!active)return;active=false;cancelAnimationFrame(frame);frame=null;startTime=null;marker.setOpacity(0);trail.setLatLngs([]);
        exit.hidden=true;play.disabled=false;play.textContent='Play 10-second car demo';status.textContent='Preview only: driver start → pickup → drop-off. Real GPS and trip progress are separate.';
        get('journeyNavigationMap').classList.remove('is-demo');onChange(false);
      }
      function run(){
        cancelAnimationFrame(frame);active=true;startTime=null;play.disabled=true;exit.hidden=false;status.textContent='DEMO · preparing the simulated car route…';
        get('journeyNavigationMap').classList.add('is-demo');onChange(true);place(0);map.fitBounds(L.latLngBounds(path.points.map(p=>[p.lat,p.lng])),{padding:[45,45],maxZoom:16});
        frame=requestAnimationFrame(step);
      }
      play.addEventListener('click',run);exit.addEventListener('click',stop);
      window.addEventListener('pagehide',()=>{active=false;cancelAnimationFrame(frame);});
      return {active:()=>active,stop};
    }
  };
})();
