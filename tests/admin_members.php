<?php
// Every account and trip in this file exists in SQLite memory, never the live database.
require __DIR__.'/carbon_and_pages.php';

use App\Http\Controllers\{AdminController,AdminMemberController,AuthController};
use App\Models\{User,Ride,RideBooking,RideRequest,Vehicle};
use App\Services\{MemberAccess,OnDemandRides,RoutePlanner};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,DB,Hash,Schema,URL};

foreach(['2026_10_29_000000_add_on_demand_ride_requests.php','2026_10_29_010000_add_ride_appointments.php','2026_10_29_020000_add_timetable_commutes.php','2026_10_29_040000_add_member_removal.php'] as $file)(require $root.'/database/migrations/'.$file)->up();
(require $root.'/database/migrations/2026_10_29_040000_add_member_removal.php')->up();
Carbon::setTestNow(Carbon::parse('2030-01-07 12:00:00','Asia/Kuala_Lumpur'));
$memberController=new AdminMemberController;$directoryController=new AdminController;
$makeMember=function($name,$type='passenger',$status='active') {
    return User::create(['full_name'=>$name,'email'=>strtolower(str_replace(' ','-',$name)).'@example.invalid',
        'password_hash'=>Hash::make('fixture-passphrase'),'role'=>'student','account_type'=>$type,'account_status'=>$status]);
};
$a=$makeMember('Demo passenger A');$b=$makeMember('Demo passenger B');
$da=$makeMember('Demo driver A','driver');$db=$makeMember('Demo driver B','driver');$busyDriver=$makeMember('Demo driver ongoing','driver');
$pendingMember=$makeMember('Demo pending passenger','passenger','pending');
$directory=function(array $query=[])use($admin,$directoryController){
    Auth::forgetGuards();Auth::guard()->setUser($admin);$r=Request::create('http://localhost/admin/members','GET',$query);$r->setLaravelSession(app('session')->driver());app()->instance('request',$r);
    return $directoryController->index('members');
};
$passengers=$directory(['type'=>'passenger','q'=>'Demo'])->getData()['members'];
cpCheck($passengers->count()===3&&collect($passengers->items())->every(fn($u)=>$u->account_type==='passenger'),'administrator can list all matching passengers, including pending applicants');
$drivers=$directory(['type'=>'driver','q'=>'Demo'])->getData()['members'];
cpCheck($drivers->count()===3&&collect($drivers->items())->every(fn($u)=>$u->account_type==='driver'),'administrator has a separate driver filter');
cpCheck($directory(['q'=>$a->email])->getData()['members']->total()===1,'member search matches an email address');
cpCheck($directory(['status'=>'pending','q'=>'Demo'])->getData()['members']->total()===1,'account-status filtering retains awaiting-approval accounts');
cpCheck(collect($directory()->getData()['members']->items())->every(fn($u)=>$u->role==='student'),'administrator accounts never appear as removable members');
cpReject(fn()=>$memberController->remove($req($a),$b->user_id),403,'passengers cannot remove another member');
cpReject(fn()=>$memberController->restore($req($da),$a->user_id),403,'drivers cannot restore member access');
cpReject(fn()=>$memberController->remove($req($admin),$admin->user_id),404,'the removal endpoint cannot remove an administrator or itself');

$makeRide=function($owner,$status='scheduled',$mode='scheduled')use($ride){$new=$ride->replicate();$new->driver_id=$owner->user_id;$new->ride_status=$status;$new->ride_mode=$mode;$new->departure_at=now()->addHour();$new->completed_at=$status==='completed'?now()->subHour():null;$new->available_seats=1;$new->save();return $new;};
$busy=$makeRide($busyDriver,'in_progress');
RideBooking::create(['ride_id'=>$busy->ride_id,'passenger_id'=>$b->user_id,'seats_requested'=>1,'booking_status'=>'accepted']);
cpReject(fn()=>$memberController->remove($req($admin),$busyDriver->user_id),422,'a driver in an ongoing trip cannot be removed mid-journey');
cpReject(fn()=>$memberController->remove($req($admin),$b->user_id),422,'a passenger in an ongoing trip cannot be removed mid-journey');
cpCheck($busyDriver->fresh()->isActive()&&$b->fresh()->isActive(),'a rejected ongoing-trip removal leaves account access unchanged');

$shared=$makeRide($da);$acceptedA=RideBooking::create(['ride_id'=>$shared->ride_id,'passenger_id'=>$a->user_id,'seats_requested'=>1,'booking_status'=>'accepted']);
$acceptedB=RideBooking::create(['ride_id'=>$shared->ride_id,'passenger_id'=>$b->user_id,'seats_requested'=>1,'booking_status'=>'accepted']);
$private=$makeRide($da,'scheduled','now');$privateBooking=RideBooking::create(['ride_id'=>$private->ride_id,'passenger_id'=>$a->user_id,'seats_requested'=>1,'booking_status'=>'accepted']);
$history=$makeRide($da,'completed');$historicalBooking=RideBooking::create(['ride_id'=>$history->ride_id,'passenger_id'=>$a->user_id,'seats_requested'=>1,'booking_status'=>'accepted','baseline_mode'=>'walk_cycle']);
$requestFields=['pickup_label'=>'Sample pickup A','pickup_latitude'=>2.28,'pickup_longitude'=>102.29,'dropoff_label'=>'UTeM Main Campus','dropoff_latitude'=>2.3139,'dropoff_longitude'=>102.3183,'seats_requested'=>1,
    'fare_per_seat'=>'4.00','journey_distance_km'=>10,'distance_source'=>'corridor_estimate','fare_policy'=>'synthetic-fixture','expires_at'=>now()->addMinutes(10)];
$newRequest=fn($passenger,$extra=[])=>RideRequest::create(array_replace($requestFields,['passenger_id'=>$passenger->user_id,'request_status'=>'pending'],$extra));
$waiting=$newRequest($a);
$appointment=$newRequest($a,['request_status'=>'scheduled','scheduled_for'=>now()->addDay(),'expires_at'=>now()->addDay()->addMinutes(10)]);
$acceptedRequest=$newRequest($a,['request_status'=>'accepted','driver_id'=>$da->user_id,'ride_id'=>$private->ride_id]);
$historyRequest=$newRequest($a,['request_status'=>'accepted','driver_id'=>$da->user_id,'ride_id'=>$history->ride_id]);
$session=DB::table('commute_timetable')->insertGetId(['user_id'=>$a->user_id,'title'=>'Demo class','weekday'=>1,'starts_at'=>'09:00','ends_at'=>'10:00','location'=>'Campus','created_at'=>now(),'updated_at'=>now()]);
$plan=DB::table('timetable_ride_plans')->insertGetId(['user_id'=>$a->user_id,'timetable_id'=>$session,
    'pickup_label'=>'Demo home','pickup_latitude'=>2.28,'pickup_longitude'=>102.29,'dropoff_label'=>'Campus','dropoff_latitude'=>2.3139,'dropoff_longitude'=>102.3183,
    'seats_requested'=>1,'arrival_buffer_minutes'=>10,'travel_minutes'=>15,'fare_per_seat'=>4,'journey_distance_km'=>10,'distance_source'=>'corridor_estimate','fare_policy'=>'synthetic-fixture',
    'starts_on'=>'2030-01-07','ends_on'=>'2030-05-01','is_enabled'=>true,'created_at'=>now(),'updated_at'=>now()]);
$reportCount=DB::table('ride_carbon_reports')->count();$rideCount=Ride::count();
$memberController->remove($req($admin),$a->user_id);
$removed=$a->fresh();
cpCheck(!$removed->isActive()&&$removed->removed_at&&$removed->removed_by===$admin->user_id&&$removed->access_version===1,'Remove revokes access, records the administrator and advances the session version');
$removed->forceFill(['account_status'=>'active'])->save();
cpCheck(!$removed->fresh()->isActive(),'the removed marker blocks access even if another process changes the status field');
$removed->forceFill(['account_status'=>'suspended'])->save();
cpCheck($waiting->fresh()->request_status==='cancelled'&&$appointment->fresh()->request_status==='cancelled','removal cancels pending searches and future ride appointments');
cpCheck($private->fresh()->ride_status==='cancelled'&&$privateBooking->fresh()->booking_status==='cancelled'&&$acceptedRequest->fresh()->request_status==='cancelled','an accepted but unstarted private ride is cancelled so its driver is released');
cpCheck($shared->fresh()->ride_status==='scheduled'&&$shared->fresh()->available_seats===2&&$acceptedA->fresh()->booking_status==='cancelled'&&$acceptedB->fresh()->booking_status==='accepted','removing one passenger releases their seat without cancelling other passengers’ published ride');
cpCheck(!DB::table('timetable_ride_plans')->where('id',$plan)->value('is_enabled')&&DB::table('commute_timetable')->where('id',$session)->exists(),'weekly automation is paused while the member’s timetable is preserved');
cpCheck($history->fresh()->ride_status==='completed'&&$historicalBooking->fresh()->booking_status==='accepted'&&$historyRequest->fresh()->request_status==='accepted','completed journeys and their accepted records are never erased by removal');
cpCheck(Ride::count()===$rideCount&&DB::table('ride_carbon_reports')->count()===$reportCount,'removing an account does not delete trips or carbon reports');
cpCheck($directory(['q'=>$a->email])->getData()['members']->isEmpty()&&$directory(['type'=>'removed','q'=>$a->email])->getData()['members']->total()===1,'removed accounts leave the current directory and appear in Removed');
$memberController->remove($req($admin),$a->user_id);
cpCheck($shared->fresh()->available_seats===2&&$a->fresh()->access_version===1,'repeated removal cannot release seats twice or alter the original removal status');
cpReject(fn()=>OnDemandRides::create($a->user_id,$requestFields,[]),403,'a removed passenger cannot create another request through the ride service');
cpCheck(in_array(cpPage($kernel,$a->fresh(),'dashboard.php')->getStatusCode(),[302,403],true),'an existing removed-account session cannot access passenger pages');
cpCheck(!Auth::check(),'the blocked account session is signed out rather than getting stuck authenticated');
$memberController->restore($req($admin),$a->user_id);
cpCheck($a->fresh()->isActive()&&$a->fresh()->removed_at===null&&$a->fresh()->access_version===1,'Restore returns the previous active status without resetting the session version');
cpCheck($appointment->fresh()->request_status==='cancelled'&&!DB::table('timetable_ride_plans')->where('id',$plan)->value('is_enabled'),'restore does not restart cancelled appointments or weekly automation');
app('session')->put('member_access_version.'.$a->user_id,0);
$oldSessionResponse=cpPage($kernel,$a->fresh(),'dashboard.php');
cpCheck($oldSessionResponse->isRedirect(),'a session from before removal must sign in again after restoration (HTTP '.$oldSessionResponse->getStatusCode().')');
$login=Request::create('http://localhost/login.php','POST',['email'=>$a->email,'password'=>'fixture-passphrase']);$login->setLaravelSession(app('session')->driver());app()->instance('request',$login);
(new AuthController)->login($login);
cpCheck(app('session')->get('member_access_version.'.$a->user_id)===1&&cpPage($kernel,$a->fresh(),'dashboard.php')->getStatusCode()===200,'fresh sign-in records the restored account version and restores normal access');
$memberController->remove($req($admin),$pendingMember->user_id);
$directoryController->handleAction($req($admin,['action'=>'approve_member','user_id'=>$pendingMember->user_id]));
cpCheck($pendingMember->fresh()->account_status==='suspended','approval cannot bypass a removed member’s access block');
$memberController->restore($req($admin),$pendingMember->user_id);
cpCheck($pendingMember->fresh()->account_status==='pending','restoring an unapproved account does not silently activate it');
$future=$makeRide($da);$futureBooking=RideBooking::create(['ride_id'=>$future->ride_id,'passenger_id'=>$a->user_id,'seats_requested'=>1,'booking_status'=>'accepted']);
$futureRequest=$newRequest($a,['request_status'=>'accepted','driver_id'=>$da->user_id,'ride_id'=>$future->ride_id]);
$memberController->remove($req($admin),$da->user_id);
cpCheck($future->fresh()->ride_status==='cancelled'&&$futureBooking->fresh()->booking_status==='cancelled'&&$futureRequest->fresh()->request_status==='cancelled','removing a driver cancels their unstarted trips and notifies the linked request state');
cpCheck($history->fresh()->driver->full_name===$da->full_name&&$historicalBooking->fresh()->passenger->full_name===$a->full_name,'historical trip names still resolve after a driver is removed');
cpReject(fn()=>$memberController->restore($req($b),$da->user_id),403,'a passenger cannot undo an administrator removal');
foreach(range(1,25) as $number)$makeMember('Pagination fixture '.str_pad($number,2,'0',STR_PAD_LEFT));
$page=$directory(['type'=>'passenger','q'=>'Pagination fixture'])->getData()['members'];
cpCheck($page->total()===25&&$page->count()===20&&$page->nextPageUrl()!==null,'large member lists are paginated while retaining their search filters');
$page2=$directory(['type'=>'passenger','q'=>'Pagination fixture','page'=>2])->getData()['members'];
cpCheck($page2->count()===5,'the second directory page contains the remaining members');
Auth::forgetGuards();Auth::guard()->setUser($admin);$post=Request::create('http://localhost/admin/members/'.$db->user_id.'/remove','POST',[],[],[],['HTTP_ACCEPT'=>'application/json']);
cpCheck($kernel->handle($post)->getStatusCode()===419,'member removal remains protected by CSRF');
cpCheck(in_array(cpPage($kernel,$b,'admin/members')->getStatusCode(),[302,403],true),'passengers cannot open the admin member directory');

// Explicitly reproduce the user's two-pickup/same-destination case.
(require $root.'/database/migrations/2026_10_30_000000_add_find_ride_pooling.php')->up();
$poolDriver=$makeMember('Pooling driver','driver');$poolA=$makeMember('Pooling passenger A');$poolB=$makeMember('Pooling passenger B');
$poolVehicle=Vehicle::create(['user_id'=>$poolDriver->user_id,'vehicle_type'=>'Fixture car','vehicle_plate'=>'POOL-TEST','vehicle_color'=>'Green','verification_status'=>'approved']);
$requestA=$newRequest($poolA,['pickup_label'=>'Pickup A','pickup_latitude'=>2.28,'pickup_longitude'=>102.29]);
$requestB=$newRequest($poolB,['pickup_label'=>'Pickup B','pickup_latitude'=>2.29,'pickup_longitude'=>102.30]);
$feed=OnDemandRides::feed($poolDriver->user_id)->pluck('request_id');
cpCheck($feed->contains($requestA->request_id)&&$feed->contains($requestB->request_id),'two different pickup requests for the same destination are individually visible to an available driver');
$single=OnDemandRides::accept($poolDriver->user_id,$requestA->request_id,$poolVehicle->vehicle_id,['pool_seat_capacity'=>2,'pool_origin_latitude'=>2.30,'pool_origin_longitude'=>102.31]);
$joined=OnDemandRides::accept($poolDriver->user_id,$requestB->request_id,$poolVehicle->vehicle_id);
cpCheck($joined->ride_id===$single->ride_id,'two instant students with different pickups can share the same car');
cpCheck($single->fresh()->available_seats===1&&$requestB->fresh()->request_status==='accepted'&&OnDemandRides::feed($poolDriver->user_id)->isEmpty(),'two shared requests leave one automatic passenger seat available');
$published=$makeRide($poolDriver);$published->update(['pickup_latitude'=>2.27,'pickup_longitude'=>102.28,'dropoff_latitude'=>2.3139,'dropoff_longitude'=>102.3183,'available_seats'=>1]);
foreach([[$poolA,'Pickup A',2.28,102.29],[$poolB,'Pickup B',2.29,102.30]] as [$person,$label,$lat,$lng])RideBooking::create(['ride_id'=>$published->ride_id,'passenger_id'=>$person->user_id,'seats_requested'=>1,'booking_status'=>'accepted','pickup_label'=>$label,'pickup_latitude'=>$lat,'pickup_longitude'=>$lng,'dropoff_label'=>'Campus','dropoff_latitude'=>2.3139,'dropoff_longitude'=>102.3183]);
$route=(new RoutePlanner)->plan($published->fresh()->load('bookings'));
cpCheck($route&&collect($route['points'])->contains('label','Pickup: Pickup A')&&collect($route['points'])->contains('label','Pickup: Pickup B'),'the published-ride route planner already supports different pickups for one common drop-off');
if(in_array('--members-preview',$argv,true)) {
    URL::forceRootUrl('http://localhost/workshop');session()->forget('memberNotice');session()->forget('memberNoticeType');
    $folder=$root.'/public/device-review/admin-members';if(!is_dir($folder))mkdir($folder,0777,true);
    foreach(['all','passenger','driver','removed'] as $type){$html=$directory(['type'=>$type,'q'=>'Demo'])->render();$html=preg_replace('~<form\b~','<form inert',$html);$html=str_replace('<body class="app-page admin-page">','<body class="app-page admin-page"><div style="padding:8px;background:#e9f0df;text-align:center;font:11px system-ui">DEMO DATA · Synthetic accounts only. Actions disabled.</div>',$html);$html=str_replace('</head>','<meta name="robots" content="noindex,nofollow"></head>',$html);file_put_contents($folder.'/'.$type.'.html',$html);}
}
echo "Member directory, removal and pooling audit: $checks total checks passed; SQLite memory only.\n";
