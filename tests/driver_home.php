<?php
// Reuses the isolated shared-ride harness; all fixtures remain in SQLite memory.
require __DIR__.'/shared_find_ride.php';
use App\Models\{Ride,RideBooking};
use App\Http\Controllers\DriverController;
use App\Services\{CampusDriverArea,OnDemandRides};
use Illuminate\Support\Facades\{Auth,URL};
use Illuminate\Http\Request;

$area=CampusDriverArea::settings();
cpCheck(CampusDriverArea::contains($area['lat'],$area['lng']),'UTeM Main Campus is a permitted starting point');
cpCheck(!CampusDriverArea::contains(3.15,101.7),'driver starts outside the campus service radius are rejected');
$homeDriver=$person('Demo UTeM driver','driver');$homeVehicle=$car($homeDriver,'UTEM-DEMO');
$homeA=$newRequest('Demo hostel student',2.3125,102.316);$homeB=$newRequest('Demo library student',2.315,102.319);
$published=Ride::create(['driver_id'=>$homeDriver->user_id,'vehicle_id'=>$homeVehicle->vehicle_id,'pickup_point'=>'UTeM Main Campus','destination'=>'Library',
    'departure_at'=>now()->addDay(),'available_seats'=>3,'contribution_per_seat'=>2,'vehicle_description'=>'Demo shared car','ride_status'=>'scheduled']);
$scheduledPassenger=$person('Demo scheduled passenger');
RideBooking::create(['ride_id'=>$published->ride_id,'passenger_id'=>$scheduledPassenger->user_id,'pickup_label'=>'Faculty entrance','dropoff_label'=>'Library','seats_requested'=>1,'booking_status'=>'pending']);
Auth::forgetGuards();Auth::guard()->setUser($homeDriver);$r=Request::create('http://localhost/driver.php');$r->setLaravelSession(app('session')->driver());app()->instance('request',$r);
$controller=new DriverController;$html=$controller->index()->render();
cpCheck(strpos($html,'id="driverPassengerRequests"')<strpos($html,'class="journey-hero'),'instant passenger requests are above the large illustration on the driver home page');
cpCheck(str_contains($html,'id="driver-home-seat-requests"')&&str_contains($html,'Demo scheduled passenger')&&str_contains($html,'Accept request'),'driver home exposes own pending scheduled passengers and accept actions');
cpCheck(!str_contains($html,'Synthetic future pickup'),'another driver scheduled requests do not appear on this home page');
cpCheck(str_contains($html,'"campusArea":')&&str_contains($html,'UTeM Main Campus selected'),'driver home receives the UTeM starting point and configured area');
cpCheck(substr_count($html,'id="sidebarToggle"')===1&&str_contains($html,'aria-label="Minimise sidebar"'),'driver navigation contains one labelled collapse control');
foreach(['dashboard.php'=>$p1,'admin.php'=>$admin] as $path=>$user){$page=cpPage($kernel,$user,$path);cpCheck($page->getStatusCode()===200&&str_contains($page->getContent(),'id="sidebarToggle"'),'sidebar minimisation is also available on '.$path);}
if(in_array('--home-preview',$argv,true)){
    URL::forceRootUrl('http://localhost/workshop');Auth::forgetGuards();Auth::guard()->setUser($homeDriver);$r=Request::create('http://localhost/workshop/driver.php');$r->setLaravelSession(app('session')->driver());app()->instance('request',$r);
    $folder=$root.'/public/device-review/driver-home';if(!is_dir($folder))mkdir($folder,0777,true);
    $demo=['requests'=>collect([$homeA,$homeB])->map(fn($q)=>OnDemandRides::payload($q)+['passenger'=>$q->passenger->full_name])->values(),'pool'=>null];
    file_put_contents($folder.'/feed.json',json_encode($demo));
    $html=$controller->index()->render();$html=str_replace(json_encode(url('api/driver/ride-requests')),json_encode(url('device-review/driver-home/feed.json')),$html);
    $html=str_replace('</head>','<meta name="robots" content="noindex,nofollow"><script>document.addEventListener("submit",function(e){e.preventDefault();e.stopImmediatePropagation();},true);</script></head>',$html);
    $html=str_replace('<body class="app-page driver-page">','<body class="app-page driver-page"><div style="padding:8px;background:#e9f0df;text-align:center;font:11px system-ui">DEMO DATA · Synthetic passenger requests. Booking actions disabled.</div>',$html);
    $html=preg_replace('~<form\b~','<form inert',$html);file_put_contents($folder.'/index.html',$html);
}
echo "Driver home, UTeM location and navigation: $checks total checks passed; SQLite memory only.\n";
