(() => {
  const radians=d=>d*Math.PI/180;
  const distance=(a,b)=>{const x=Math.sin(radians(b.lat-a.lat)/2)**2+Math.cos(radians(a.lat))*Math.cos(radians(b.lat))*Math.sin(radians(b.lng-a.lng)/2)**2;return 6371000*2*Math.asin(Math.sqrt(Math.min(1,x)));};
  const bearing=(a,b)=>(Math.atan2(Math.sin(radians(b.lng-a.lng))*Math.cos(radians(b.lat)),Math.cos(radians(a.lat))*Math.sin(radians(b.lat))-Math.sin(radians(a.lat))*Math.cos(radians(b.lat))*Math.cos(radians(b.lng-a.lng)))*180/Math.PI+360)%360;
  const route=points=>{let total=0;const cumulative=[0];for(let i=1;i<points.length;i++){total+=distance(points[i-1],points[i]);cumulative.push(total);}return {points,cumulative,total};};
  function at(path,meters){const d=Math.max(0,Math.min(path.total,meters));let i=1;while(i<path.points.length-1&&path.cumulative[i]<d)i++;const a=path.points[i-1]||path.points[0],b=path.points[i]||a;const length=(path.cumulative[i]||0)-(path.cumulative[i-1]||0),t=length?(d-(path.cumulative[i-1]||0))/length:0;return {lat:a.lat+(b.lat-a.lat)*t,lng:a.lng+(b.lng-a.lng)*t,heading:bearing(a,b)};}
  function project(path,point,min=0){let best={distance:Infinity,along:min};const scale=Math.cos(radians(point.lat));for(let i=1;i<path.points.length;i++){
    if(path.cumulative[i]<min)continue;const a=path.points[i-1],b=path.points[i],dx=(b.lng-a.lng)*scale,dy=b.lat-a.lat;
    let t=(dx||dy)?(((point.lng-a.lng)*scale*dx+(point.lat-a.lat)*dy)/(dx*dx+dy*dy)):0;
    const len=path.cumulative[i]-path.cumulative[i-1];t=Math.max(len?(min-path.cumulative[i-1])/len:0,Math.max(0,Math.min(1,t)));t=Math.min(1,t);
    const p={lat:a.lat+(b.lat-a.lat)*t,lng:a.lng+(b.lng-a.lng)*t},gap=distance(point,p);if(gap<best.distance)best={distance:gap,along:path.cumulative[i-1]+len*t};
  }return best;}
  window.NavigationGeometry={distance,bearing,route,at,project};
})();
