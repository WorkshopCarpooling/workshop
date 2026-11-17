<?php
// Visual fixtures only: empty collections and in-memory users. Never creates accounts,
// bookings, vehicles or rides. Original scripts are removed and forms made inert.
// Fixtures use only safe navigation and interface motion scripts. Forms stay inert.
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\URL::forceRootUrl('http://localhost/workshop');
$app['session']->start();
view()->share('errors',new Illuminate\Support\ViewErrorBag);
$directory=public_path('device-review/theme');
if(!is_dir($directory)) mkdir($directory,0777,true);
$empty=collect();
$base=[
 'currentUser'=>null,'activeRides'=>$empty,'myBookings'=>$empty,'bookingStats'=>['all'=>0,'accepted'=>0,'pending'=>0],
 'campusRoutes'=>$empty,'routeStopsByRoute'=>[],'rides'=>$empty,'rideStopsByRide'=>[],
 'vehicles'=>$empty,'approvedVehicles'=>$empty,'bookingRequests'=>$empty,'pendingRequestCount'=>0,
 'notice'=>'','noticeType'=>'','routePlans'=>$empty,'rideStats'=>['scheduled'=>0,'active'=>0,'completed'=>0],
 'activity'=>$empty,'scheduledCount'=>0,'activeCount'=>0,'memberCount'=>0,'pendingMembers'=>$empty,'pendingVehicles'=>$empty,
 'memberNotice'=>'','memberNoticeType'=>'','vehicleNotice'=>'','vehicleNoticeType'=>'',
 'carbonSummary'=>['count'=>0,'avoided'=>0],'availableRides'=>[],'timetable'=>$empty,'bookingNotice'=>'',
 'ready'=>true,'survey'=>null,'samples'=>$empty,'stats'=>['surveys'=>0,'samples'=>0,'sus_count'=>0,'sus_mean'=>null],'evaluation'=>null,
];
$count=0;
$render=function(string $file,string $view,array $data,string $role,string $path)use($app,$directory,$base,&$count){
 $request=Illuminate\Http\Request::create('http://localhost/workshop/'.$path);
 $app->instance('request',$request);
 if($role==='guest') Illuminate\Support\Facades\Auth::guard()->forgetUser();
 else {
  $user=new App\Models\User(['full_name'=>'Theme preview','role'=>$role==='admin'?'admin':'student','account_type'=>$role==='driver'?'driver':'passenger','account_status'=>'active']);
  $user->user_id=0;
  Illuminate\Support\Facades\Auth::guard()->setUser($user);
  $data+=['currentUser'=>$user,'adminUser'=>$user,'user'=>$user];
 }
 $html=view($view,array_merge($base,$data))->render();
 if(!str_contains($html,'css/system-theme.css')) throw new RuntimeException('Missing shared palette in '.$file);
 if(!str_contains($html,'css/workspace-experience.css')) throw new RuntimeException('Missing shared interface in '.$file);
 if(str_contains($html,'css/dashboard-atmosphere.css')) throw new RuntimeException('Retired dark palette is still loaded in '.$file);
 $hasLoginArrival = str_contains($html,'name="rideutem-login-arrival"');
 $html=preg_replace('~<script\b[^>]*>.*?</script\s*>~is','',$html);
 $html=preg_replace('~<form\b~i','<form inert',$html);
 $html=preg_replace('~<input\b[^>]*name="_token"[^>]*>~i','',$html);
 $html=str_replace('</head>','<meta name="robots" content="noindex"></head>',$html);
 $html=str_replace('</body>','<script src="http://localhost/workshop/js/device.js" defer></script><script src="http://localhost/workshop/js/workspace-menus.js" defer></script><script src="http://localhost/workshop/js/interface-motion.js" defer></script></body>',$html);
 if($hasLoginArrival) $html=str_replace('</body>','<script src="http://localhost/workshop/js/login-arrival.js" defer></script></body>',$html);
 file_put_contents($directory.'/'.$file.'.html',$html);
 $count++;
};
$render('welcome','landing',[],'guest','welcome');
$render('login','auth.auth-page',['isSignup'=>false],'guest','login.php');
$render('signup','auth.auth-page',['isSignup'=>true],'guest','signup.php');
foreach(['overview','bookings','schedule','history'] as $section){
 $render('passenger-'.$section,'dashboard',['section'=>$section,'pageTitle'=>'Passenger '.ucfirst($section),'activePage'=>'passenger_'.$section],'passenger',$section==='overview'?'dashboard.php':'passenger/'.$section);
}
// Exercise the populated dashboard and the unconfirmed-request branch with models
// that exist only in memory. These are not inserted into the database.
$fixtureDriver=new App\Models\User(['full_name'=>'Preview driver','role'=>'student','account_type'=>'driver','account_status'=>'active']);
$fixtureRide=new App\Models\Ride(['pickup_point'=>'Melaka Sentral','destination'=>'FTMK, UTeM Main Campus','departure_at'=>now()->addDay()->setTime(8,30),'ride_status'=>'scheduled','contribution_per_seat'=>4]);
$fixtureRide->setRelation('driver',$fixtureDriver);
$fixtureBooking=new App\Models\RideBooking(['pickup_label'=>'Mydin MITC Ayer Keroh','dropoff_label'=>'FTMK, UTeM Main Campus','booking_status'=>'accepted','seats_requested'=>2,'fare_per_seat'=>2.5]);
$fixtureBooking->setRelation('ride',$fixtureRide);
$fixtureBooking->created_at=now();
$completedRide=clone $fixtureRide;
$completedRide->ride_status='completed';
$completedRide->departure_at=now()->subDay();
$completedBooking=clone $fixtureBooking;
$completedBooking->setRelation('ride',$completedRide);
$completedBooking->created_at=now()->subDay();
$declinedBooking=clone $completedBooking;
$declinedBooking->booking_status='declined';
$declinedBooking->created_at=now()->subHours(2);
$fixtureBookings=collect([$fixtureBooking,$completedBooking,$declinedBooking]);
$render('passenger-upcoming','dashboard',['section'=>'overview','pageTitle'=>'Passenger overview','activePage'=>'passenger_overview','myBookings'=>$fixtureBookings,'overview'=>App\Services\PassengerOverview::fromBookings($fixtureBookings)],'passenger','dashboard.php');
$pendingBooking=clone $fixtureBooking;
$pendingBooking->booking_status='pending';
$pendingBookings=collect([$pendingBooking,$completedBooking,$declinedBooking]);
$render('passenger-pending','dashboard',['section'=>'overview','pageTitle'=>'Passenger overview','activePage'=>'passenger_overview','myBookings'=>$pendingBookings,'overview'=>App\Services\PassengerOverview::fromBookings($pendingBookings)],'passenger','dashboard.php');
$acceptedHtml=file_get_contents($directory.'/passenger-upcoming.html');
$pendingHtml=file_get_contents($directory.'/passenger-pending.html');
if(!str_contains($acceptedHtml,'Your seat is confirmed.')||!str_contains($acceptedHtml,'RM 5.00')||!str_contains($acceptedHtml,'status-declined')) throw new RuntimeException('Populated dashboard details failed to render.');
if(!str_contains($pendingHtml,'Your seat is not confirmed yet.')) throw new RuntimeException('Pending request must remain visibly unconfirmed.');
$render('findride','findride',[],'passenger','findride.php');
$render('tracking','tracking',[],'passenger','tracking.php');
$render('timetable','timetable',['sessions'=>collect(),'plans'=>collect(),'nextRides'=>collect(),'ready'=>true,'commuteReady'=>true],'passenger','timetable');
foreach(['overview','offer','requests','vehicles','schedule','history'] as $section){
 $render('driver-'.$section,'driver',['section'=>$section,'pageTitle'=>'Driver '.ucfirst($section),'activePage'=>'driver_'.$section],'driver','driver/'.$section);
}
$previewRide=new App\Models\Ride(['departure_at'=>now()->addDay(),'pickup_point'=>'Preview pickup','destination'=>'Preview campus']);
$render('driver-route','driver-route',[
 'ride'=>$previewRide,'plan'=>['algorithm'=>'astar_stop_order','points'=>[],'optimal'=>true,'distanceKm'=>0,'durationMinutes'=>0,'baselineKm'=>0],
 'roadRoute'=>['source'=>'estimate','distanceText'=>'0 km','durationText'=>'0 minutes'],
 'prediction'=>['minutes'=>0,'source'=>'Theme preview','samples'=>0,'reason'=>'Visual fixture only'],'key'=>'theme-preview',
],'driver','driver/route/0');
foreach(['overview','live','rides','members','vehicles'] as $section){
 $render('admin-'.$section,'admin',['section'=>$section,'pageTitle'=>'Admin '.ucfirst($section),'activePage'=>'admin_'.$section],'admin','admin/'.$section);
}
foreach(['passenger','driver','admin'] as $role){
 $render('carbon-'.$role,'carbon',['ready'=>false,'activePage'=>'carbon','summary'=>['count'=>0,'avoided'=>0,'emissions_count'=>0,'emissions_total'=>0]],$role,'carbon');
}
foreach(['passenger'=>['dashboard','dashboard.php'],'driver'=>['driver','driver.php'],'admin'=>['admin','admin.php']] as $arrivalRole=>[$arrivalView,$arrivalPath]) {
 $app['session']->put('workspaceEntrance',true);
 $render('login-arrival-'.$arrivalRole,$arrivalView,['section'=>'overview','pageTitle'=>'Your workspace','activePage'=>$arrivalRole.'_overview'],$arrivalRole,$arrivalPath);
 $arrivalHtml=file_get_contents($directory.'/login-arrival-'.$arrivalRole.'.html');
 if(!str_contains($arrivalHtml,'name="rideutem-login-arrival"') || !str_contains($arrivalHtml,'js/login-arrival.js')) throw new RuntimeException('Missing login arrival for '.$arrivalRole);
 if($app['session']->has('workspaceEntrance')) throw new RuntimeException('Login arrival must be consumed once.');
}
foreach(['passenger-overview','driver-overview','admin-overview','login','signup'] as $ordinaryPage) {
 if(str_contains(file_get_contents($directory.'/'.$ordinaryPage.'.html'),'name="rideutem-login-arrival"')) throw new RuntimeException('Unexpected login animation on '.$ordinaryPage);
}
echo 'Rendered '.$count.' anonymous, non-submitting theme previews; shared palette and one-time login arrival checks passed.'.PHP_EOL;
