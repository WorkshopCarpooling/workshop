<?php
// Requests, users, vehicles and rides exist only in an in-memory SQLite database.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\{OnDemandRideController, FindRideController, DriverController, DashboardController};
use App\Models\{Ride, RideBooking, RideRequest, User, Vehicle};
use App\Services\{FareQuote, OnDemandRides, PassengerOverview};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Crypt, DB, URL};
use Illuminate\Validation\ValidationException;

config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','cache.default'=>'array','services.google_maps.api_key'=>'','session.driver'=>'array']);
DB::purge('sqlite');
$app->instance('encrypter',new Illuminate\Encryption\Encrypter(str_repeat('R',32),'AES-256-CBC'));
Crypt::clearResolvedInstance('encrypter');
Carbon::setTestNow(Carbon::parse('2030-01-07 07:00:00','Asia/Kuala_Lumpur'));
foreach (['2026_10_21_000000_create_workshop_schema_baseline.php','2026_10_22_000000_add_journey_and_research_features.php','2026_10_23_000000_add_booking_fare_quotes.php','2026_10_29_000000_add_on_demand_ride_requests.php'] as $file) (require database_path('migrations/'.$file))->up();
(require database_path('migrations/2026_10_29_000000_add_on_demand_ride_requests.php'))->up();
$app['session']->start(); view()->share('errors',new Illuminate\Support\ViewErrorBag);
$checks=0;
function checkNow($ok,$label) { global $checks; if(!$ok) throw new RuntimeException($label); $checks++; echo 'PASS: '.$label.PHP_EOL; }
function rejectedNow(callable $call,$label) { try {$call();} catch (ValidationException $error) {checkNow(true,$label);return;} throw new RuntimeException($label); }
function useNowRequest($path,$method='GET',$fields=[]) { $request=Request::create('http://localhost/workshop/'.$path,$method,$fields,[],[],['HTTP_ACCEPT'=>'application/json']);app()->instance('request',$request);$request->setLaravelSession(app('session.store'));return $request; }
$users=[];
foreach (['Passenger','Other passenger','Campus driver','Second driver','Unapproved driver'] as $i=>$name) {
    $users[]=User::create(['full_name'=>$name,'email'=>'now'.$i.'@example.invalid','password_hash'=>'synthetic-no-login','role'=>'student','account_type'=>$i<2?'passenger':'driver','account_status'=>'active']);
}
$vehicles=[];
foreach ([2,3,4] as $i) $vehicles[$i]=Vehicle::create(['user_id'=>$users[$i]->user_id,'vehicle_type'=>'Campus car','vehicle_color'=>'Green','vehicle_plate'=>'TEST'.$i,'verification_status'=>$i===4?'pending':'approved']);
Auth::guard()->setUser($users[0]);
$journey=['pickup_label'=>'Mydin MITC','pickup_latitude'=>2.2707,'pickup_longitude'=>102.2856,'dropoff_label'=>'UTeM Main Campus','dropoff_latitude'=>2.3139,'dropoff_longitude'=>102.3183,'seats_requested'=>2];
$controller=new OnDemandRideController;
$search=fn($fields)=>$controller->index(useNowRequest('findride.php','GET',$fields))->getData(true);
$result=$search($journey);
checkNow(OnDemandRides::ready(),'new schema is ready and its migration is idempotent');
checkNow(Ride::count()===0&&RideRequest::count()===0,'search calculates a fare without requiring a published ride or creating a request');
checkNow(isset($result['fare']['token'])&&$result['fare']['seats']===2,'immediate journey receives a signed student fare');
checkNow($result['fare']['distanceSource']==='corridor_estimate','fallback distance is labelled as an estimate');
rejectedNow(fn()=>$search($journey+['departure_at'=>'2030-01-07 08:00:00']),'ride now rejects a future departure instead of silently scheduling it');
rejectedNow(fn()=>$search($journey+['arrive_by'=>'2030-01-07 09:00:00']),'arrival deadlines belong to Schedule');
$fare=$result['fare'];
rejectedNow(fn()=>FareQuote::verifyNow($fare['token'].'x',$users[0]->user_id,$journey),'tampered immediate fare is rejected');
rejectedNow(fn()=>FareQuote::verifyNow($fare['token'],$users[1]->user_id,$journey),'another passenger cannot reuse an immediate quote');
rejectedNow(fn()=>FareQuote::verifyNow($fare['token'],$users[0]->user_id,array_replace($journey,['seats_requested'=>3])),'changing the passenger count requires a new quote');
rejectedNow(fn()=>FareQuote::verifyNow($fare['token'],$users[0]->user_id,array_replace($journey,['dropoff_latitude'=>2.30])),'moving the destination requires a new quote');
$post=$journey+['fare_quote'=>$fare['token'],'fare_per_seat'=>'0.01'];
$created=$controller->store(useNowRequest('findride.php','POST',$post))->getData(true)['request'];
checkNow($created['status']==='pending'&&Ride::count()===0,'confirmation publishes an unassigned request with no fake driver or ride');
checkNow($created['perSeat']===$fare['perSeat'],'request stores the verified fare and ignores a forged client price');
$repeat=$controller->store(useNowRequest('findride.php','POST',$post))->getData(true)['request'];
checkNow($repeat['id']===$created['id']&&RideRequest::count()===1,'repeated confirmation cannot create duplicate active requests');
checkNow(OnDemandRides::feed($users[2]->user_id)->count()===1,'approved drivers see the request without publishing a scheduled ride');
checkNow(OnDemandRides::feed($users[4]->user_id)->isEmpty(),'drivers without an approved vehicle cannot receive requests');
Auth::guard()->setUser($users[1]);
checkNow($controller->status()->getData(true)['request']===null,'passenger status is scoped to the signed-in passenger');
try {OnDemandRides::cancel($users[1]->user_id,$created['id']);checkNow(false,'request ownership');} catch (Illuminate\Database\Eloquent\ModelNotFoundException $error) {checkNow(true,'another passenger cannot cancel the request');}
rejectedNow(fn()=>OnDemandRides::accept($users[2]->user_id,$created['id'],$vehicles[3]->vehicle_id),'a driver cannot accept using another driver’s vehicle');
rejectedNow(fn()=>OnDemandRides::accept($users[4]->user_id,$created['id'],$vehicles[4]->vehicle_id),'an unapproved vehicle cannot accept a request');
$ride=OnDemandRides::accept($users[2]->user_id,$created['id'],$vehicles[2]->vehicle_id);
checkNow($ride->ride_mode==='now'&&$ride->ride_status==='scheduled','acceptance creates a ride now ready for pickup');
$booking=RideBooking::first();
checkNow(Ride::count()===1&&$booking->booking_status==='accepted'&&$booking->passenger_id===$users[0]->user_id,'driver acceptance creates exactly one confirmed passenger booking');
checkNow($booking->fare_per_seat===$fare['perSeat']&&$booking->dropoff_label===$journey['dropoff_label'],'accepted booking keeps the passenger’s exact fare and locations');
rejectedNow(fn()=>OnDemandRides::accept($users[3]->user_id,$created['id'],$vehicles[3]->vehicle_id),'a second driver cannot take a request already accepted');
rejectedNow(fn()=>OnDemandRides::cancel($users[0]->user_id,$created['id']),'a stale cancellation cannot undo a driver acceptance');
checkNow(OnDemandRides::feed($users[3]->user_id)->isEmpty(),'accepted request disappears from other drivers’ feed');
Auth::guard()->setUser($users[0]);
$state=$controller->status()->getData(true)['request'];
checkNow($state['status']==='accepted'&&$state['driver']==='Campus driver'&&str_contains($state['vehicle'],'TEST2'),'passenger receives the accepting driver and vehicle details');
Carbon::setTestNow(now()->addMinutes(3));
$overview=PassengerOverview::fromBookings(collect([$booking->load('ride')]));
checkNow($overview['confirmedCount']===1,'an immediate pickup remains visible after its acceptance timestamp passes');
$again=OnDemandRides::create($users[0]->user_id,$journey,FareQuote::verifyNow($fare['token'],$users[0]->user_id,$journey));
checkNow($again->request_id===$created['id'],'an accepted active ride prevents another request by the same passenger');
Auth::guard()->setUser($users[2]);
(new DriverController)->handleAction(useNowRequest('driver.php','POST',['action'=>'update_status','ride_id'=>$ride->ride_id,'next_status'=>'in_progress']));
checkNow($controller->driverFeed()->getData(true)['requests']===[],'busy drivers do not receive additional immediate requests');
checkNow(RideRequest::first()->load('ride')->displayStatus()==='in_progress','trip start updates passenger request status');
(new DriverController)->handleAction(useNowRequest('driver.php','POST',['action'=>'update_status','ride_id'=>$ride->ride_id,'next_status'=>'completed']));
checkNow(RideRequest::first()->load('ride')->displayStatus()==='completed','trip completion updates passenger request status');
checkNow(OnDemandRides::active($users[0]->user_id)===null,'completed trips release the passenger to request again');
Auth::guard()->setUser($users[0]);
$freshFare=$search($journey)['fare'];
$second=$controller->store(useNowRequest('findride.php','POST',$journey+['fare_quote'=>$freshFare['token']]))->getData(true)['request'];
OnDemandRides::cancel($users[0]->user_id,$second['id']);
checkNow(OnDemandRides::feed($users[3]->user_id)->isEmpty(),'cancelled requests are removed from driver feed');
rejectedNow(fn()=>OnDemandRides::accept($users[3]->user_id,$second['id'],$vehicles[3]->vehicle_id),'a driver cannot accept a cancelled request');
$third=$controller->store(useNowRequest('findride.php','POST',$journey+['fare_quote'=>$freshFare['token']]))->getData(true)['request'];
Carbon::setTestNow(now()->addMinutes(11));
checkNow(OnDemandRides::latest($users[0]->user_id)->displayStatus()==='expired'&&OnDemandRides::feed($users[3]->user_id)->isEmpty(),'unaccepted requests expire and stop appearing for drivers');
rejectedNow(fn()=>OnDemandRides::accept($users[3]->user_id,$third['id'],$vehicles[3]->vehicle_id),'expired requests cannot be accepted');
rejectedNow(fn()=>$controller->store(useNowRequest('findride.php','POST',$journey+['fare_quote'=>$freshFare['token']])),'expired quotes cannot create a new request');

$htmlRequest=Request::create('http://localhost/workshop/findride.php');$htmlRequest->setLaravelSession(app('session.store'));app()->instance('request',$htmlRequest);
$html=$controller->index($htmlRequest)->getContent();
checkNow(!str_contains($html,'datetime-local')&&!str_contains($html,'timetableJourney')&&str_contains($html,'rideNowForm'),'Find a ride contains locations and immediate request controls with no scheduling fields');
checkNow(str_contains($html,'window.bookingCsrf ='),'map route previews retain the CSRF token required by the shared route loader');
$schedule=(new App\Http\Controllers\RideAppointmentController)->index($htmlRequest)->getContent();
checkNow(str_contains($schedule,'name="pickup_date"')&&str_contains($schedule,'name="pickup_time"'),'Schedule provides appointment date and time controls');
checkNow(str_contains($schedule,'/passenger/schedule'),'scheduled searches and requests use the separate Schedule endpoint');
$routes=app('router')->getRoutes();
checkNow($routes->match(Request::create('/findride.php'))->getActionName()===OnDemandRideController::class.'@index','public Find a ride route uses the immediate flow');
checkNow($routes->match(Request::create('/passenger/schedule'))->getActionName()===App\Http\Controllers\RideAppointmentController::class.'@index','public Schedule route uses ride appointments');
$dashboard=(new DashboardController)->index('bookings')->render();
checkNow(str_contains($dashboard,'Ride now ·'),'accepted immediate bookings are labelled separately from planned departures');

if (in_array('--render',$argv,true)) {
    URL::forceRootUrl('http://localhost/workshop');
    $directory=public_path('device-review/ride-now');if(!is_dir($directory))mkdir($directory,0777,true);
    $save=function($file,$html)use($directory,$result){
        $html=preg_replace('~<form\b~','<form inert',$html);
        $html=preg_replace('~<script\b[^>]*src="[^"]*(ride-now|journey-search)\.js[^>]*>.*?</script>~s','',$html);
        $html=str_replace(' data-auto-pickup','',$html);
        $html=preg_replace('~(<p data-gps-status role="status">).*?</p>~s','$1Sample pickup selected for this preview. No device location is used.</p>',$html);
        $trip=json_encode($result['journey'],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
        $mock='<script>window.fetch=async function(url){if(String(url).includes("api/routes/compute"))return {ok:true,json:async()=>('.$trip.')};throw new Error("Inert preview; requests are disabled.");};</script>';
        $html=str_replace('</head>',$mock.'</head>',$html);
        // Inert visual fixtures: synthetic pins, no GPS access or application requests.
        file_put_contents($directory.'/'.$file.'.html',$html);
    };
    $app['session']->flashInput($journey);
    $save('passenger',$controller->index($htmlRequest)->getContent());
    $save('schedule',(new FindRideController)->index($htmlRequest)->getContent());
    Auth::guard()->setUser($users[2]);
    $save('driver',(new DriverController)->index()->render());
}
Carbon::setTestNow();
echo "$checks immediate request, fare, driver acceptance and separation checks passed; SQLite memory only.\n";
