<?php
// The common harness uses SQLite memory; no real accounts, requests or trips change.
require __DIR__.'/carbon_and_pages.php';
use App\Models\{User,Vehicle,Ride,RideBooking,RideRequest};
use App\Services\{OnDemandRides,SharedRidePool,RoutePlanner,MemberAccess,AutomaticCarbon};
use App\Http\Controllers\{DriverController,OnDemandRideController};
use Illuminate\Support\Facades\{Auth,DB,URL};
use Illuminate\Http\Request;

foreach(['2026_10_29_000000_add_on_demand_ride_requests.php','2026_10_29_010000_add_ride_appointments.php','2026_10_29_020000_add_timetable_commutes.php','2026_10_29_040000_add_member_removal.php','2026_10_30_000000_add_find_ride_pooling.php'] as $file)(require $root.'/database/migrations/'.$file)->up();
(require $root.'/database/migrations/2026_10_30_000000_add_find_ride_pooling.php')->up();
cpCheck(SharedRidePool::ready(),'pool migration is ready and idempotent');
$seq=0;
$person=function($name,$type='passenger')use(&$seq){return User::create(['full_name'=>$name,'email'=>'pool-'.(++$seq).'@example.invalid','password_hash'=>'unused','role'=>'student','account_type'=>$type,'account_status'=>'active']);};
$car=function($driver,$plate){return Vehicle::create(['user_id'=>$driver->user_id,'vehicle_type'=>'Demo shared car','vehicle_color'=>'Green','vehicle_plate'=>$plate,'verification_status'=>'approved','fuel_type'=>'petrol','fuel_l_per_100km'=>7]);};
$newRequest=function($name,$lat=2.29,$lng=102.29,$overrides=[])use($person){$p=$person($name);return RideRequest::create($overrides+[
    'passenger_id'=>$p->user_id,'pickup_label'=>$name.' pickup','pickup_latitude'=>$lat,'pickup_longitude'=>$lng,
    'dropoff_label'=>'UTeM shared entrance','dropoff_latitude'=>2.3139,'dropoff_longitude'=>102.3183,
    'seats_requested'=>1,'fare_per_seat'=>2.75,'journey_distance_km'=>8,'distance_source'=>'estimated','fare_policy'=>'synthetic',
    'request_status'=>'pending','expires_at'=>now()->addMinutes(10)]);};
$d=$person('Demo pool driver','driver');$v=$car($d,'POOL-DEMO');$v2=$car($d,'POOL-OTHER');
$far=$newRequest('Far student',2.30,102.30);$near=$newRequest('Near student',2.281,102.281);$third=$newRequest('Third student',2.29,102.29);
$setup=['pool_seat_capacity'=>3,'pool_origin_latitude'=>2.29,'pool_origin_longitude'=>102.29];
cpCheck(SharedRidePool::setup([],1)['pool_seat_capacity']===3,'new instant cars receive server-managed capacity without driver input');
cpCheck(SharedRidePool::setup(['pool_seat_capacity'=>6]+$setup,1)['pool_seat_capacity']===3,'posted seat capacity cannot override the automatic passenger limit');
cpReject(fn()=>OnDemandRides::accept($d->user_id,$far->request_id,$v->vehicle_id,['pool_origin_latitude'=>100]+$setup),422,'invalid driver coordinates are rejected');
cpReject(fn()=>OnDemandRides::accept($d->user_id,$far->request_id,$v->vehicle_id,['pool_origin_latitude'=>3.15,'pool_origin_longitude'=>101.7]+$setup),422,'driver starting locations outside the UTeM service area are rejected by the server');
$ride=OnDemandRides::accept($d->user_id,$far->request_id,$v->vehicle_id,['pool_origin_latitude'=>2.29,'pool_origin_longitude'=>102.29]);
cpCheck($ride->available_seats===2&&$ride->pool_seat_capacity===3,'first request reserves one of three actual passenger seats');
$id=$ride->ride_id;cpCheck(SharedRidePool::current($d->user_id)->ride_id===$id,'the open shared car is reused rather than creating a second ride');
$wrong=$newRequest('Different destination',2.285,102.285,['dropoff_latitude'=>2.35]);
$big=$newRequest('Large party',2.285,102.285,['seats_requested'=>3]);
$appointment=$newRequest('Appointment student',2.285,102.285,['scheduled_for'=>now()]);
$expired=$newRequest('Expired student',2.285,102.285,['expires_at'=>now()->subSecond()]);
$feed=OnDemandRides::feed($d->user_id)->pluck('request_id');
cpCheck($feed->contains($near->request_id)&&!$feed->contains($wrong->request_id)&&!$feed->contains($big->request_id)&&!$feed->contains($appointment->request_id)&&!$feed->contains($expired->request_id),'open cars see only unexpired instant requests for their destination that fit');
cpReject(fn()=>OnDemandRides::accept($d->user_id,$wrong->request_id,$v->vehicle_id),422,'a matching place label cannot join a different pinned destination');
cpReject(fn()=>OnDemandRides::accept($d->user_id,$appointment->request_id,$v->vehicle_id),422,'appointments cannot be absorbed by an instant car');
cpReject(fn()=>OnDemandRides::accept($d->user_id,$big->request_id,$v->vehicle_id),422,'a whole party must fit; groups are never split or overbooked');
cpReject(fn()=>OnDemandRides::accept($d->user_id,$near->request_id,$v2->vehicle_id),422,'later acceptance cannot switch the shared vehicle');
$joined=OnDemandRides::accept($d->user_id,$near->request_id,$v->vehicle_id,['pool_seat_capacity'=>6,'pool_origin_latitude'=>0,'pool_origin_longitude'=>0]);
cpCheck($joined->ride_id===$id&&$joined->available_seats===1&&$joined->pool_seat_capacity===3&&$joined->pool_origin_latitude===2.29,'second student joins the same car without changing its capacity or origin');
OnDemandRides::accept($d->user_id,$near->request_id,$v->vehicle_id);
cpCheck($ride->fresh()->available_seats===1&&RideBooking::where('ride_id',$id)->count()===2,'retrying an acceptance does not duplicate bookings or consume seats');
$plan=(new RoutePlanner)->plan($ride->fresh()->load('bookings'));
cpCheck($plan['algorithm']==='nearest_pickup'&&$plan['points'][0]['label']==='Driver starting point'&&$plan['points'][1]['label']==='Pickup: Near student pickup'&&$plan['points'][2]['label']==='Pickup: Far student pickup'&&count($plan['points'])===4,'far request accepted first is reordered after the closer pickup, with one final drop-off');
$payload=OnDemandRides::payload($near->fresh()->load(['ride','driver']));
cpCheck($payload['shared']['pickupPosition']===1&&$payload['shared']['remaining']===1&&!str_contains(json_encode($payload),'Far student')&&!str_contains(json_encode($payload),'pool_origin'),'passenger status exposes only own pickup position and shared occupancy, not other pickup identities or driver coordinates');
OnDemandRides::accept($d->user_id,$third->request_id,$v->vehicle_id);
cpCheck($ride->fresh()->available_seats===0&&OnDemandRides::feed($d->user_id)->isEmpty(),'matching stops when all passenger seats are full');
$extra=$newRequest('Extra student');cpReject(fn()=>OnDemandRides::accept($d->user_id,$extra->request_id,$v->vehicle_id),422,'a stale accept button cannot overfill a full car');
cpCheck($extra->fresh()->request_status==='pending','rejected acceptance leaves the passenger available to other drivers');
$d2=$person('Demo second driver','driver');$vOther=$car($d2,'OTHER-DEMO');
cpReject(fn()=>OnDemandRides::accept($d2->user_id,$near->request_id,$vOther->vehicle_id,$setup),422,'a competing driver cannot steal an accepted passenger');
cpCheck(RideBooking::where('ride_id',$id)->get()->every(fn($b)=>(float)$b->fare_per_seat===2.75),'joining preserves every passenger quoted contribution');
$nearBooking=RideBooking::where('ride_id',$id)->where('passenger_id',$near->passenger_id)->first();
MemberAccess::remove($near->passenger_id,$admin->user_id);
cpCheck($ride->fresh()->ride_status==='scheduled'&&$ride->fresh()->available_seats===1&&$far->fresh()->request_status==='accepted','admin removal releases just the removed member seats while preserving other shared passengers');
cpCheck(!collect((new RoutePlanner)->plan($ride->fresh()->load('bookings'))['points'])->contains('bookingId',$nearBooking->booking_id),'cancelled passengers disappear from the pickup route');
OnDemandRides::accept($d->user_id,$extra->request_id,$v->vehicle_id);
cpCheck($ride->fresh()->available_seats===0,'a released seat can be filled by a compatible new passenger');
$controller=new DriverController;
Auth::forgetGuards();Auth::guard()->setUser($d);$r=Request::create('/driver.php','POST',['action'=>'update_status','ride_id'=>$id,'next_status'=>'in_progress']);$r->setLaravelSession(app('session')->driver());app()->instance('request',$r);$controller->handleAction($r);
cpCheck($ride->fresh()->ride_status==='in_progress'&&OnDemandRides::feed($d->user_id)->isEmpty(),'starting pickups closes all new acceptances');
$late=$newRequest('Late student');cpReject(fn()=>OnDemandRides::accept($d->user_id,$late->request_id,$v->vehicle_id),422,'in-progress pickups cannot receive a late additional passenger');
cpReject(fn()=>MemberAccess::remove($far->passenger_id,$admin->user_id),422,'admin cannot remove a passenger during pooled travel');
$r=Request::create('/driver.php','POST',['action'=>'update_status','ride_id'=>$id,'next_status'=>'completed']);$r->setLaravelSession(app('session')->driver());app()->instance('request',$r);$controller->handleAction($r);
cpCheck(OnDemandRides::payload($far->fresh()->load('ride'))['status']==='completed','all pooled passengers see completion of the same ride');
$carbon=DB::table('ride_carbon_reports')->where('ride_id',$id)->first();$details=$carbon?json_decode($carbon->details,true):[];
cpCheck($carbon&&count($details['bookings'])===3&&$ride->fresh()->bookings->where('booking_status','accepted')->sum('seats_requested')===3,'one shared carbon report includes all three accepted passenger bookings');
cpCheck(AutomaticCarbon::inputs($ride->fresh())['shared_km']>(new RoutePlanner)->distance((new RoutePlanner)->endpoints($ride->fresh())),'shared carbon distance includes every intermediate pickup');
$party=$newRequest('Two friends',2.29,102.29,['seats_requested'=>2]);$one=$newRequest('One friend',2.30,102.30);
$partyRide=OnDemandRides::accept($d2->user_id,$party->request_id,$vOther->vehicle_id,$setup);
cpCheck($partyRide->available_seats===1,'two passengers on one request consume two seats');
$partyRide=OnDemandRides::accept($d2->user_id,$one->request_id,$vOther->vehicle_id);
cpCheck($partyRide->available_seats===0&&SharedRidePool::passengerSummary($party->fresh()->load('ride'))['passengers']===3,'mixed parties fill the car by seat count, not number of requests');
$d3=$person('Demo appointment driver','driver');$v3=$car($d3,'APPT-DEMO');
$apptRide=OnDemandRides::accept($d3->user_id,$appointment->request_id,$v3->vehicle_id);
cpCheck($apptRide->ride_mode==='appointment'&&$apptRide->available_seats===0&&!SharedRidePool::isPool($apptRide),'appointment acceptance keeps its existing private ride flow');
Auth::forgetGuards();Auth::guard()->setUser($d2);$feedRequest=Request::create('/api/driver/ride-requests','GET');app()->instance('request',$feedRequest);
$feedJson=(new OnDemandRideController)->driverFeed($feedRequest)->getData(true);
cpCheck($feedJson['pool']['remaining']===0&&$feedJson['pool']['capacity']===3,'driver feed reports full-car capacity even when no matching requests remain');
$directDriver=$person('Direct accept driver','driver');$directVehicle=$car($directDriver,'DIRECT-TEST');$directRequest=$newRequest('Direct accept passenger');
Auth::forgetGuards();Auth::guard()->setUser($directDriver);$directPost=Request::create('/driver/ride-requests/accept','POST',['request_id'=>$directRequest->request_id,'vehicle_id'=>$directVehicle->vehicle_id]);$directPost->setLaravelSession(app('session')->driver());app()->instance('request',$directPost);
(new OnDemandRideController)->accept($directPost);$directRide=$directRequest->fresh()->ride;
cpCheck($directRequest->fresh()->request_status==='accepted'&&$directRide->pool_seat_capacity===3&&$directRide->available_seats===2&&$directRide->pool_origin_latitude===2.3139,'Accept works with only the request and approved vehicle, using automatic seats and UTeM start');
$directRequest2=$newRequest('Direct second passenger');$directPost2=Request::create('/driver/ride-requests/accept','POST',['request_id'=>$directRequest2->request_id,'vehicle_id'=>$directVehicle->vehicle_id,'pool_seat_capacity'=>99]);$directPost2->setLaravelSession(app('session')->driver());app()->instance('request',$directPost2);(new OnDemandRideController)->accept($directPost2);
cpCheck($directRequest2->fresh()->ride_id===$directRide->ride_id&&$directRide->fresh()->available_seats===1,'direct acceptance keeps sharing the same car and ignores submitted capacity changes');
$route=cpPage($kernel,$d2,'driver/route/'.$partyRide->ride_id);
cpCheck($route->getStatusCode()===200&&str_contains($route->getContent(),'Nearest-first shared journey'),'driver road route displays the ordered shared pickups');
cpCheck(cpPage($kernel,$d3,'driver/route/'.$partyRide->ride_id)->getStatusCode()===404,'other drivers cannot see the shared pickup route');
cpCheck(in_array(cpPage($kernel,$party->passenger,'driver/route/'.$partyRide->ride_id)->getStatusCode(),[302,403],true),'passengers cannot access another passenger pickup route');
if(in_array('--pool-preview',$argv,true)){
    URL::forceRootUrl('http://localhost/workshop');$folder=$root.'/public/device-review/shared-find-ride';if(!is_dir($folder))mkdir($folder,0777,true);
    Auth::forgetGuards();Auth::guard()->setUser($d2);$r=Request::create('/driver/requests');$r->setLaravelSession(app('session')->driver());app()->instance('request',$r);
    $demoPool=['id'=>$partyRide->ride_id,'vehicleId'=>$vOther->vehicle_id,'capacity'=>3,'remaining'=>0,'destination'=>$partyRide->destination,'scheduleUrl'=>'schedule.html'];
    file_put_contents($folder.'/feed.json',json_encode(['requests'=>[],'pool'=>$demoPool]));
    foreach(['requests','schedule'] as $section){$html=$controller->index($section)->render();$html=str_replace(url('api/driver/ride-requests'),url('device-review/shared-find-ride/feed.json'),$html);
        $html=str_replace(json_encode(url('api/driver/ride-requests')),json_encode(url('device-review/shared-find-ride/feed.json')),$html);
        $html=str_replace('</head>','<meta name="robots" content="noindex,nofollow"><script>document.addEventListener("submit",function(e){e.preventDefault();e.stopImmediatePropagation();},true);</script></head>',$html);
        $html=str_replace('<body class="app-page driver-page">','<body class="app-page driver-page"><div style="padding:8px;background:#e9f0df;text-align:center;font:11px system-ui">DEMO DATA · Synthetic shared car. Actions disabled.</div>',$html);
        $html=preg_replace('~<form\b~','<form inert',$html);file_put_contents($folder.'/'.$section.'.html',$html);
    }
}
echo "Shared Find Ride: $checks total checks passed; SQLite memory only.\n";
