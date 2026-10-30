document.addEventListener('DOMContentLoaded', async () => {
  const points=window.orderedRoute, first=points[0], last=points[points.length-1];
  const url=new URL('https://www.google.com/maps/dir/');url.searchParams.set('api','1');url.searchParams.set('travelmode','driving');url.searchParams.set('origin',`${first.lat},${first.lng}`);url.searchParams.set('destination',`${last.lat},${last.lng}`);
  if(points.length>2)url.searchParams.set('waypoints',points.slice(1,-1).map(p=>`${p.lat},${p.lng}`).join('|'));
  document.getElementById('roadDirections').href=url.href;
});
