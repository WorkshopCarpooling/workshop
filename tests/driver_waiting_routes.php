<?php
// Synthetic coordinates and roles in SQLite memory only; road responses are mocked.
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{User,Vehicle,Ride,RideRequest,RideBooking,DriverWaitingRoute};
use App\Services\{DriverWaitingRoutes,OnDemandRides,Journey};
use App\Http\Controllers\{DriverWaitingRouteController,OnDemandRideController,DriverController};
use Illuminate\Support\Facades\{Auth,Cache,DB,Http,URL};
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','cache.default'=>'array','session.driver'=>'array',
    'services.google_maps.api_key'=>'','navigation.osrm_enabled'=>true,'carpool'=>require __DIR__.'/../config/carpool.php']);
DB::purge('sqlite');
Carbon::setTestNow(Carbon::parse('2030-01-07 12:00:00','Asia/Kuala_Lumpur'));
foreach(['2026_10_21_000000_create_workshop_schema_baseline.php','2026_10_22_000000_add_journey_and_research_features.php','2026_10_23_000000_add_booking_fare_quotes.php','2026_10_29_000000_add_on_demand_ride_requests.php','2026_10_29_010000_add_ride_appointments.php','2026_10_30_000000_add_find_ride_pooling.php','2026_10_31_000000_add_driver_waiting_routes.php'] as $file)(require database_path('migrations/'.$file))->up();
(require database_path('migrations/2026_10_31_000000_add_driver_waiting_routes.php'))->up();
app('session')->start();view()->share('errors',new Illuminate\Support\ViewErrorBag);
view()->getFinder()->setPaths([resource_path('views'),'C:/laragon/www/workshop/resources/views']);
$checks=0;
function waitingCheck($yes,$label){global $checks;if(!$yes)throw new RuntimeException($label);echo 'PASS: '.$label.PHP_EOL;$checks++;}
function waitingReject($action,$label){try{$action();}catch(ValidationException $error){waitingCheck(true,$label);return;}throw new RuntimeException($label);}
$n=0;
$person=function($name,$type='passenger')use(&$n){return User::create(['full_name'=>$name,'email'=>'waiting'.(++$n).'@example.invalid','password_hash'=>'unused','role'=>'student','account_type'=>$type,'account_status'=>'active']);};
$driver=$person('Demo Tuah driver','driver');$otherDriver=$person('Other driver','driver');$passenger=$person('Demo Lekir passenger');
$vehicle=Vehicle::create(['user_id'=>$driver->user_id,'vehicle_type'=>'Demo car','vehicle_color'=>'Green','vehicle_plate'=>'WAIT-DEMO','verification_status'=>'approved']);
$otherVehicle=Vehicle::create(['user_id'=>$otherDriver->user_id,'vehicle_type'=>'Demo car','vehicle_color'=>'Green','vehicle_plate'=>'OTHER-DEMO','verification_status'=>'approved']);
$journey=['pickup_label'=>'Satria Tuah (synthetic preview)','pickup_latitude'=>2.3139,'pickup_longitude'=>102.3183,'dropoff_label'=>'FTMK (synthetic preview)','dropoff_latitude'=>2.320,'dropoff_longitude'=>102.326];
$passengerJourney=array_replace($journey,['pickup_label'=>'Satria Lekir (synthetic preview)','pickup_latitude'=>2.314,'pickup_longitude'=>102.319,'seats_requested'=>1]);
Http::fake(function($request){
    $coordinateText=explode('?',explode('/driving/',$request->url())[1])[0];
    $points=array_map(fn($value)=>array_map('floatval',explode(',',$value)),explode(';',$coordinateText));
    $blocked=count($points)>2 && (abs($points[1][1]-2.315)<.00001 || $points[1][1]<2.3);
    return Http::response(['code'=>'Ok','routes'=>[['geometry'=>['coordinates'=>$points],'distance'=>$blocked?8000:(count($points)>2?1200:1000),'duration'=>$blocked?1000:(count($points)>2?150:120)]]]);
});
Auth::guard()->setUser($driver);
$request=Request::create('/driver/waiting-route','POST',$journey+['vehicle_id'=>$vehicle->vehicle_id],[],[],['HTTP_ACCEPT'=>'application/json']);
$request->setLaravelSession(app('session.store'));app()->instance('request',$request);
$opened=(new DriverWaitingRouteController)->store($request)->getData(true)['waiting'];
$route=DriverWaitingRoutes::current($driver->user_id);
waitingCheck(DriverWaitingRoutes::ready()&&$opened['online']&&Ride::count()===0&&RideBooking::count()===0,'opening a waiting route needs no date/time and creates no fake ride or booking');
waitingReject(fn()=>DriverWaitingRoutes::open($driver->user_id,$journey,$otherVehicle->vehicle_id),'another driver vehicle cannot be used to go online');
$nearby=DriverWaitingRoutes::nearby($passenger->user_id,$passengerJourney);
waitingCheck(count($nearby)===1&&str_contains($nearby[0]['pickup'],'Tuah')&&str_contains($nearby[0]['destination'],'FTMK'),'Lekir pickup discovers Tuah to FTMK despite different pickup names and pins');
waitingCheck($nearby[0]['basis']==='road_estimate'&&$nearby[0]['extraMinutes']===2.5,'matching uses mocked road detour plus a pickup stop allowance');
waitingCheck(!str_contains(json_encode($nearby),'latitude')&&!str_contains(json_encode($nearby),'plate')&&!str_contains(json_encode($nearby),'version'),'nearby notices omit driver coordinates, plate and private route version');
waitingCheck(DriverWaitingRoutes::nearby($passenger->user_id,array_replace($passengerJourney,['dropoff_latitude'=>2.34]))===[],'same destination label cannot match a different destination pin');
waitingCheck(DriverWaitingRoutes::nearby($passenger->user_id,array_replace($passengerJourney,['pickup_latitude'=>2.27,'pickup_longitude'=>102.27]))===[],'a distant pickup is excluded even with the same destination');
waitingCheck(DriverWaitingRoutes::nearby($passenger->user_id,array_replace($passengerJourney,['pickup_latitude'=>2.315]))===[],'a nearby pin with an excessive road detour is excluded');
$makeRequest=function($who,$fields)use($journey){return RideRequest::create($fields+['passenger_id'=>$who->user_id,'request_status'=>'pending','expires_at'=>now()->addMinutes(10),'fare_per_seat'=>2.75,'journey_distance_km'=>1.2,'distance_source'=>'estimated','fare_policy'=>'synthetic']);};
$pending=$makeRequest($passenger,$passengerJourney);
$farPerson=$person('Distant passenger');$far=$makeRequest($farPerson,array_replace($passengerJourney,['pickup_latitude'=>2.27,'pickup_longitude'=>102.27]));
waitingCheck(OnDemandRides::feed($driver->user_id)->pluck('request_id')->all()===[$pending->request_id],'online driver receives only requests compatible with the published route');
Auth::guard()->setUser($passenger);$poll=(new OnDemandRideController)->status()->getData(true);
waitingCheck(count($poll['nearby'])===1&&$poll['request']['status']==='pending','passenger polling discovers open routes before any booking is confirmed');
$firstVersion=$route->version;
DriverWaitingRoutes::close($otherDriver->user_id,$route->id,$route->version);
waitingCheck(DriverWaitingRoutes::current($driver->user_id)->available_until->gt(now()),'another driver cannot close this waiting route');
DriverWaitingRoutes::close($driver->user_id,$route->id,$route->version);
waitingCheck(DriverWaitingRoutes::nearby($passenger->user_id,$passengerJourney)===[],'going offline immediately removes the passenger notice');
waitingCheck(DriverWaitingRoutes::heartbeat($driver->user_id,$route->id,$route->version)===null,'a late heartbeat cannot reopen an offline route');
waitingReject(fn()=>OnDemandRides::accept($driver->user_id,$pending->request_id,$vehicle->vehicle_id),'a stale Accept cannot reserve seats after the driver goes offline');
$route=DriverWaitingRoutes::open($driver->user_id,$journey,$vehicle->vehicle_id);
waitingCheck($route->version!==$firstVersion&&DriverWaitingRoutes::heartbeat($driver->user_id,$route->id,$firstVersion,true)===null,'reopening rotates the route version and rejects commands from an old tab');
DriverWaitingRoutes::close($driver->user_id,$route->id,$firstVersion);
waitingCheck(DriverWaitingRoutes::usable($route->fresh()),'stale close cannot take a newly opened route offline');
$ride=OnDemandRides::accept($driver->user_id,$pending->request_id,$vehicle->vehicle_id);
$booking=RideBooking::where('ride_id',$ride->ride_id)->first();
waitingCheck($booking->pickup_label===$passengerJourney['pickup_label']&&$ride->pickup_point===$journey['pickup_label']&&$ride->available_seats===2,'driver acceptance keeps the passenger at Lekir and the driver start at Tuah, with real seat accounting');
waitingCheck(DriverWaitingRoutes::current($driver->user_id)->ride_id===$ride->ride_id,'the online route links to the real shared ride after first acceptance');
waitingReject(fn()=>DriverWaitingRoutes::open($driver->user_id,$journey,$vehicle->vehicle_id),'driver cannot replace the route after accepting passengers');
OnDemandRides::accept($driver->user_id,$pending->request_id,$vehicle->vehicle_id);
waitingCheck(RideBooking::count()===1&&$ride->fresh()->available_seats===2,'retrying acceptance does not duplicate bookings or consume another seat');
$two=$person('Two students');$twoRequest=$makeRequest($two,array_replace($passengerJourney,['seats_requested'=>2]));
OnDemandRides::accept($driver->user_id,$twoRequest->request_id,$vehicle->vehicle_id);
waitingCheck($ride->fresh()->available_seats===0&&DriverWaitingRoutes::nearby($farPerson->user_id,$passengerJourney)===[],'a full car disappears from nearby notices');
$ride->update(['ride_status'=>'in_progress']);
waitingCheck(DriverWaitingRoutes::heartbeat($driver->user_id,$route->id,$route->version,true)===null,'starting pickups prevents any resume of matching');
$ride->update(['ride_status'=>'completed']);
waitingCheck(!DriverWaitingRoutes::summary($route->fresh())['hasPassengers'],'after completion the driver can use the open-route form again');
$route=DriverWaitingRoutes::open($driver->user_id,$journey,$vehicle->vehicle_id);
Carbon::setTestNow(now()->addSeconds(91));
waitingCheck(DriverWaitingRoutes::nearby($farPerson->user_id,$passengerJourney)===[]&&DriverWaitingRoutes::heartbeat($driver->user_id,$route->id,$route->version)===null,'closing the driver page eventually expires availability without automatic resurrection');
$route=DriverWaitingRoutes::heartbeat($driver->user_id,$route->id,$route->version,true);
waitingCheck($route&&DriverWaitingRoutes::usable($route),'an explicit Resume can restore an expired route');
$vehicle->update(['verification_status'=>'pending']);
waitingCheck(DriverWaitingRoutes::nearby($farPerson->user_id,$passengerJourney)===[],'vehicle approval revoked during waiting removes the notice');
$vehicle->update(['verification_status'=>'approved']);
config(['navigation.osrm_enabled'=>false]);Cache::flush();
$approx=DriverWaitingRoutes::nearby($farPerson->user_id,$passengerJourney);
waitingCheck(count($approx)===1&&$approx[0]['basis']==='approximate','routing outage fallback is explicitly approximate, never described as a verified road route');

if(in_array('--preview',$argv,true)){
    URL::forceRootUrl('http://localhost/workshop');
    $folder=public_path('device-review/nearby-rides');if(!is_dir($folder))mkdir($folder,0777,true);
    $save=function($file,$html){
        $html=preg_replace('~<script\b[^>]*src="[^"]*(journey-picker|maplibre-loader|leaflet)[^>]*>.*?</script>~s','',$html);
        $html=str_replace(' data-auto-pickup','',$html);
        $html=preg_replace('~(<p data-gps-status role="status">).*?</p>~s','$1Synthetic preview; no device location is used.</p>',$html);
        $html=str_replace('</head>','<meta name="robots" content="noindex,nofollow"></head>',$html);
        $html=str_replace('</body>','<script>document.addEventListener("submit",e=>e.preventDefault(),true);</script></body>',$html);
        file_put_contents(public_path('device-review/nearby-rides/'.$file.'.html'),$html);
    };
    Auth::forgetGuards();Auth::guard()->setUser($driver);$r=Request::create('/driver/requests');$r->setLaravelSession(app('session.store'));app()->instance('request',$r);
    $driverHtml=(new DriverController)->index('requests')->render();
    $mock='<script>window.fetch=async(url,options={})=>new Response(JSON.stringify(String(url).includes("heartbeat")?{waiting:window.driverWaiting.waiting}:String(url).includes("api/driver/ride-requests")?{requests:[],pool:null,waiting:window.driverWaiting.waiting}:{ready:true,events:[]}));</script>';
    $save('driver',str_replace('</head>',$mock.'</head>',$driverHtml));
    Auth::forgetGuards();Auth::guard()->setUser($farPerson);$r=Request::create('/findride.php');$r->setLaravelSession(app('session.store'));app()->instance('request',$r);
    $requestPayload=OnDemandRides::payload($far->fresh());$requestPayload['pickup']=$passengerJourney['pickup_label'];
    $html=view('findride',['currentUser'=>$farPerson,'bookingReady'=>true,'rideRequest'=>$requestPayload,'nearbyRides'=>$approx])->render();
    $mock='<script>window.fetch=async()=>new Response(JSON.stringify({request:window.rideNow.request,nearby:window.rideNow.nearby,ready:true,events:[]}));</script>';
    $save('passenger',str_replace('</head>',$mock.'</head>',$html));
}
Carbon::setTestNow();
echo "$checks waiting-route, nearby matching, privacy and seat checks passed; SQLite memory and mocked roads only.\n";
