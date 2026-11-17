<?php
// Render synthetic, interactive visual fixtures. No live accounts or ride requests are used.
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$app->instance('request',Illuminate\Http\Request::create('http://localhost/workshop/findride.php'));
Illuminate\Support\Facades\URL::forceRootUrl('http://localhost/workshop');
app('session')->start();
view()->share('errors',new Illuminate\Support\ViewErrorBag);
$user=new App\Models\User(['full_name'=>'Preview passenger','role'=>'student','account_type'=>'passenger','account_status'=>'active']);
$user->user_id=0;
Illuminate\Support\Facades\Auth::guard()->setUser($user);
$pending=['id'=>0,'status'=>'pending','pickup'=>'UTeM Main Campus · Main entrance','dropoff'=>'Mydin MITC, Ayer Keroh','seats'=>2,'total'=>'8.00','perSeat'=>'4.00','rideId'=>null,'driver'=>null,'vehicle'=>null,'expiresAt'=>now()->addMinutes(10)->toIso8601String()];
$render=function(?array $initial) use($user,$pending) {
$pickerValues=['pickup_label'=>$pending['pickup'],'pickup_latitude'=>2.3139,'pickup_longitude'=>102.3183,'dropoff_label'=>$pending['dropoff'],'dropoff_latitude'=>2.2707,'dropoff_longitude'=>102.2856];
$html=view('findride',['currentUser'=>$user,'bookingReady'=>true,'rideRequest'=>$initial,'pickerValues'=>$pickerValues])->render();
$html=str_replace('</head>','<meta name="robots" content="noindex,nofollow"></head>',$html);
$html=str_replace(' data-auto-pickup','',$html);
$html=preg_replace('~(<p data-gps-status role="status">).*?</p>~s','$1Demo pickup selected. No device location is used.</p>',$html);
$html=preg_replace('~<script\b[^>]*src="[^"]*(journey-picker|maplibre-loader|leaflet)[^>]*>.*?</script>~s','',$html);
$html=preg_replace('~window.googleMapsApiKey = .*?;~','window.googleMapsApiKey = "";',$html);
$mock=<<<'HTML'
<script>
(() => {
  let current = window.rideNow.request ? {...window.rideNow.request} : null;
  const original = __PREVIEW_RIDE__;
  window.fetch = async (url, options = {}) => {
    const path = String(url);
    if (path.includes('/api/passenger/reminders')) return new Response(JSON.stringify({ready:true,events:[]}));
    if (path.includes('/cancel')) current = {...current,status:'cancelled'};
    if (path.includes('findride.php')) {
      if (options.method === 'POST') { current = {...original}; return new Response(JSON.stringify({request:current})); }
      return new Response(JSON.stringify({bookingReady:true,fare:{token:'preview-only',perSeat:'4.00',total:'8.00',distanceKm:10,seats:2,distanceSource:'corridor_estimate',expiresAt:new Date(Date.now()+600000).toISOString()}}));
    }
    return new Response(JSON.stringify({request:current}));
  };
  document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('previewAccept').addEventListener('click', () => { if(current?.status!=='pending')return; current = {...current,status:'accepted',rideId:999,driver:'Demo campus driver',vehicle:'Green hatchback · DEMO'}; document.dispatchEvent(new Event('visibilitychange')); });
    document.getElementById('previewExpire').addEventListener('click', () => { if(current?.status!=='pending')return; current = {...current,status:'expired'}; document.dispatchEvent(new Event('visibilitychange')); });
  });
})();
</script>
HTML;
$mock=str_replace('__PREVIEW_RIDE__',json_encode($pending,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT),$mock);
$html=preg_replace('~(?=<script\b[^>]*src="[^"]*ride-now\.js)~',$mock,$html,1);
$previewControls='<details class="ride-preview-tools" style="margin-bottom:18px;font-size:10px;color:#6d8065"><summary style="cursor:pointer">PREVIEW ONLY · No ride is sent.</summary><div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px"><button type="button" id="previewAccept" style="font:inherit;padding:6px 10px;border:1px solid #ccd7be;border-radius:8px;background:#fffef9">Demo: driver accepts</button><button type="button" id="previewExpire" style="font:inherit;padding:6px 10px;border:1px solid #ccd7be;border-radius:8px;background:#fffef9">Demo: no driver found</button></div></details>';
$html=str_replace('<section class="content-card now-request-card"',$previewControls.'<section class="content-card now-request-card"',$html);
return $html;
};
$directory=public_path('device-review/ride-waiting');
if(!is_dir($directory)) mkdir($directory,0777,true);
file_put_contents($directory.'/index.html',$render($pending));
file_put_contents($directory.'/search.html',$render(null));
echo "Rendered the waiting-screen preview with synthetic status and cancellation responses; no database access.\n";
