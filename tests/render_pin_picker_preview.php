<?php
// Visual fixture: actual picker code and map, synthetic location-name/route responses.
require __DIR__.'/render_ride_waiting_preview.php';
$html=file_get_contents(public_path('device-review/ride-waiting/search.html'));
$anchor="if (path.includes('/api/passenger/reminders'))";
$html=str_replace($anchor,"if (path.includes('/api/locations/reverse')) return new Response(JSON.stringify({label:'UTeM Library, Main Campus'}));\n    ".$anchor,$html);
$scripts=<<<'HTML'
<script>
window.computeGoogleRoute=async fields=>({source:'preview',distanceText:'10 km',durationText:'15 mins',fare:{perSeat:'4.00'},path:[{lat:fields.originLat,lng:fields.originLng},{lat:fields.destLat,lng:fields.destLng}]});
window.buildGoogleMapsDirectionsUrl=(a,b,c,d)=>'https://www.google.com/maps/dir/?api=1&origin='+a+','+b+'&destination='+c+','+d;
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
HTML;
$scripts.='<script src="'.asset('js/journey-picker.js').'?v='.filemtime(public_path('js/journey-picker.js')).'" defer></script>';
$html=str_replace('</body>',$scripts.'</body>',$html);
file_put_contents(public_path('device-review/ride-waiting/pins.html'),$html);
echo "Rendered the map-pin name preview; no real booking or device location.\n";
