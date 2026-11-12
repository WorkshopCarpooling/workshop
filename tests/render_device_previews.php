<?php

// In-memory view fixtures only. No database queries or inserted records.
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$request=Illuminate\Http\Request::create('http://localhost/workshop/login.php');
$app->instance('request',$request);
Illuminate\Support\Facades\URL::forceRootUrl('http://localhost/workshop');
$app['session']->start();
view()->share('errors',new Illuminate\Support\ViewErrorBag);
$directory=public_path('device-review');
if(!is_dir($directory))mkdir($directory,0777,true);
$user=new App\Models\User(['full_name'=>'Preview user','role'=>'student','account_type'=>'passenger','account_status'=>'active']);
Illuminate\Support\Facades\Auth::guard()->setUser($user);
file_put_contents($directory.'/findride.html',view('findride',['currentUser'=>$user,'availableRides'=>[],'timetable'=>collect(),'bookingNotice'=>''])->render());
file_put_contents($directory.'/dashboard.html',view('dashboard',['currentUser'=>$user,'myBookings'=>collect()])->render());
$user->account_type='driver';
$vehicle=new App\Models\Vehicle(['vehicle_type'=>'Preview car','vehicle_color'=>'Silver','vehicle_plate'=>'PREVIEW','verification_status'=>'approved']);
$vehicle->vehicle_id=0;
file_put_contents($directory.'/driver.html',view('driver',['currentUser'=>$user,'campusRoutes'=>collect(),'routeStopsByRoute'=>[],'rides'=>collect(),'rideStopsByRide'=>[],'vehicles'=>collect([$vehicle]),'approvedVehicles'=>collect([$vehicle]),'bookingRequests'=>collect(),'pendingRequestCount'=>0,'notice'=>'','noticeType'=>'','routePlans'=>collect()])->render());
Illuminate\Support\Facades\Auth::guard()->forgetUser();
file_put_contents($directory.'/login.html',view('auth.auth-page',['isSignup'=>false])->render());
file_put_contents($directory.'/signup.html',view('auth.auth-page',['isSignup'=>true])->render());
foreach([360,390,768,1024,1440] as $width){
    $html='<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Device review '.$width.'px</title><style>body{margin:0;padding:22px;background:#e4eae0;font:14px system-ui}h1{font-size:16px;color:#244b39;text-align:center}iframe{display:block;border:1px solid #bdcbb8;border-radius:18px;margin:auto;background:#f2f4ef}</style></head><body><h1>RideUTeM · '.$width.'px device preview</h1><iframe title="RideUTeM device preview" src="findride.html" width="'.$width.'" height="844"></iframe></body></html>';
    file_put_contents($directory.'/width-'.$width.'.html',$html);
    foreach(['driver','login'] as $page) file_put_contents($directory.'/'.$page.'-'.$width.'.html',str_replace('src="findride.html"','src="'.$page.'.html"',$html));
}
$styles = '';
foreach (['dashboard','campus-interface','device','traffic-scene'] as $style) $styles .= '<link rel="stylesheet" href="'.asset('css/'.$style.'.css').'">';
$traffic = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>RideUTeM traffic animation preview</title><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">'.$styles.'</head><body class="app-page"><main style="max-width:1100px;width:calc(100% - 32px);margin:32px auto">'.view('partials.journey-hero')->render().'</main></body></html>';
file_put_contents($directory.'/traffic.html',$traffic);
file_put_contents($directory.'/traffic-motorcycle.html',str_replace('</head>','<style>.traffic-vehicle{animation-delay:-6.8s!important;animation-play-state:paused!important}</style></head>',$traffic));
$mobileTraffic = '<!doctype html><html><head><meta charset="utf-8"><title>Phone animation preview</title><style>body{margin:0;background:#e4eae0}iframe{display:block;width:390px;height:820px;margin:16px auto;border:1px solid #bdcbb8;border-radius:18px}</style></head><body><iframe title="Phone animation preview" src="traffic-motorcycle.html"></iframe></body></html>';
file_put_contents($directory.'/traffic-phone.html',$mobileTraffic);
echo "Anonymized device review fixtures rendered without database access.\n";
