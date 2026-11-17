<?php
// All people, trips, points and voucher codes below exist only in SQLite memory.
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function($error){fwrite(STDERR,(string)$error.PHP_EOL);exit(1);});
use App\Models\{User,Vehicle,Ride,RideBooking};
use App\Services\{CarbonRewards,CarbonEstimate,AutomaticCarbon,LiveCarbon};
use App\Http\Controllers\CarbonRewardsController;
use Illuminate\Support\Facades\{DB,Auth,Http,Crypt};
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','cache.default'=>'array','session.driver'=>'array',
    'services.google_maps.api_key'=>'','carbon'=>require __DIR__.'/../config/carbon.php','carbon_rewards'=>require __DIR__.'/../config/carbon_rewards.php',
    'view.compiled'=>__DIR__.'/../storage/framework/views','logging.channels.single.path'=>__DIR__.'/../storage/rewards-test.log']);
DB::purge('sqlite');Http::preventStrayRequests();Http::fake();
Carbon::setTestNow(Carbon::parse('2030-01-07 12:00:00','Asia/Kuala_Lumpur'));
foreach(['2026_10_21_000000_create_workshop_schema_baseline.php','2026_10_22_000000_add_journey_and_research_features.php','2026_10_23_000000_add_booking_fare_quotes.php',
    '2026_10_27_000000_add_carbon_estimates.php','2026_10_29_000000_add_on_demand_ride_requests.php','2026_10_29_010000_add_ride_appointments.php',
    '2026_10_30_000000_add_find_ride_pooling.php','2026_10_30_010000_add_trip_navigation.php','2026_11_02_000000_add_carbon_rewards.php'] as $file)(require database_path('migrations/'.$file))->up();
(require database_path('migrations/2026_11_02_000000_add_carbon_rewards.php'))->up();
(require database_path('migrations/2026_11_03_000000_add_reward_discount_settings.php'))->up();
(require database_path('migrations/2026_11_03_000000_add_reward_discount_settings.php'))->up();
app('session')->start();view()->share('errors',new Illuminate\Support\ViewErrorBag);
view()->getFinder()->setPaths([resource_path('views'),'C:/laragon/www/workshop/resources/views']);
$checks=0;
function rewardCheck($ok,$label){global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo 'PASS: '.$label.PHP_EOL;}
function rewardReject($fn,$label){try{$fn();}catch(ValidationException $e){rewardCheck(true,$label);return;}throw new RuntimeException($label);}
function rewardStatus($fn,$status,$label){try{$fn();}catch(Throwable $e){$actual=$e instanceof Illuminate\Database\Eloquent\ModelNotFoundException||$e instanceof Illuminate\Database\RecordsNotFoundException?404:($e instanceof Symfony\Component\HttpKernel\Exception\HttpExceptionInterface?$e->getStatusCode():0);rewardCheck($actual===$status,$label);return;}throw new RuntimeException($label);}
$n=0;$person=function($name,$type='passenger',$role='student')use(&$n){return User::create(['full_name'=>$name,'email'=>'rewards'.(++$n).'@example.invalid','password_hash'=>'unused','role'=>$role,'account_type'=>$type,'account_status'=>'active']);};
$driver=$person('Reward test driver','driver');$passenger=$person('Reward test passenger');$other=$person('Other reward passenger');$admin=$person('Reward test admin','passenger','admin');
$car=Vehicle::create(['user_id'=>$driver->user_id,'vehicle_type'=>'Synthetic car','vehicle_color'=>'Green','vehicle_plate'=>'REWARD-TEST','verification_status'=>'approved','fuel_type'=>'petrol','fuel_l_per_100km'=>7]);
$trip=function($who,$seats=1)use($driver,$car){
    $r=Ride::create(['driver_id'=>$driver->user_id,'vehicle_id'=>$car->vehicle_id,'ride_mode'=>'now','pickup_point'=>'Satria Tuah','destination'=>'FTMK',
        'pickup_latitude'=>2.30,'pickup_longitude'=>102.30,'dropoff_latitude'=>2.31,'dropoff_longitude'=>102.31,
        'departure_at'=>now(),'available_seats'=>0,'contribution_per_seat'=>2,'vehicle_description'=>'Synthetic car','ride_status'=>'scheduled']);
    RideBooking::create(['ride_id'=>$r->ride_id,'passenger_id'=>$who->user_id,'booking_status'=>'accepted','seats_requested'=>$seats,
        'pickup_label'=>'Satria Tuah','dropoff_label'=>'FTMK','pickup_latitude'=>2.30,'pickup_longitude'=>102.30,'dropoff_latitude'=>2.31,'dropoff_longitude'=>102.31,'journey_distance_km'=>10]);return $r;
};
$fields=['alternative'=>'separate_car','confirmed'=>1,'cars'=>1,'distance_km'=>10,'fuel'=>'petrol','consumption'=>7];
$saveReport=function($r,$km=12){$data=CarbonEstimate::footprint($r->fresh(),['shared_km'=>$km,'distance_basis'=>'road_estimate','fuel_type'=>'petrol','fuel_l_per_100km'=>7]);DB::table('ride_carbon_reports')->insert($data+['ride_id'=>$r->ride_id,'recorded_by'=>$r->driver_id,'created_at'=>now(),'updated_at'=>now()]);};
rewardCheck(CarbonRewards::ready()&&DB::table('carbon_reward_ledger')->count()===0,'rewards migration is additive, idempotent and creates no accounts, balances or sample rewards');
$ride=$trip($passenger);
rewardStatus(fn()=>CarbonRewards::declare($other,$ride->ride_id,$fields),404,'another passenger cannot declare an alternative for this ride');
rewardReject(fn()=>CarbonRewards::declare($passenger,$ride->ride_id,array_replace($fields,['cars'=>2])),'one passenger seat cannot claim two avoided cars');
rewardReject(fn()=>CarbonRewards::declare($passenger,$ride->ride_id,array_replace($fields,['confirmed'=>0])),'travel declarations require explicit confirmation');
CarbonRewards::declare($passenger,$ride->ride_id,$fields);
rewardCheck(CarbonRewards::compare($ride,1.92192)['status']==='unavailable','missing driver alternative cannot fabricate a green comparison');
CarbonRewards::declare($driver,$ride->ride_id,$fields);
$comparison=CarbonRewards::compare($ride,1.92192);
rewardCheck(abs($comparison['baseline_kg']-3.2032)<1e-8&&abs($comparison['saved_kg']-1.28128)<1e-8&&$comparison['remaining_percent']===60.0&&$comparison['level']==='low','two 10 km alternatives compared with a 12 km shared trip save 40 percent including the detour');
foreach([[65,'low'],[65.01,'medium'],[80,'medium'],[80.01,'high'],[120,'high']] as [$pct,$level])rewardCheck(CarbonRewards::compare($ride,3.2032*$pct/100)['level']===$level,'colour threshold handles '.$pct.' percent');
rewardCheck(CarbonRewards::wallet($passenger->user_id)['earned']===0,'scheduled and active estimates never award points early');
$ride->update(['ride_status'=>'completed','completed_at'=>now()]);CarbonRewards::finalize($ride->ride_id);
rewardCheck(CarbonRewards::wallet($passenger->user_id)['earned']===0,'a completed trip without a saved footprint cannot mint points');
$saveReport($ride);CarbonRewards::finalize($ride->ride_id);CarbonRewards::finalize($ride->ride_id);
$wallet=CarbonRewards::wallet($passenger->user_id);
rewardCheck($wallet['earned']===64&&abs($wallet['saved_kg']-.64064)<1e-8&&DB::table('carbon_reward_ledger')->count()===1,'passenger receives only their share at 100 points per kg, once, rounded down');
rewardCheck(CarbonRewards::wallet($driver->user_id)['earned']===0,'driver share is not awarded again as passenger points');
rewardReject(fn()=>CarbonRewards::declare($passenger,$ride->ride_id,array_replace($fields,['distance_km'=>100])),'finalised declarations cannot be inflated to reissue points');
DB::table('ride_carbon_reports')->where('ride_id',$ride->ride_id)->update(['shared_kg'=>.1]);CarbonRewards::finalize($ride->ride_id);
rewardCheck(CarbonRewards::wallet($passenger->user_id)['earned']===64,'later footprint corrections preserve the audited original points snapshot');
$active=$trip($other);CarbonRewards::declare($driver,$active->ride_id,$fields);CarbonRewards::declare($other,$active->ride_id,$fields);
$daily=CarbonRewards::daily($active,1.92192);
rewardCheck($daily['remaining_percent']===60.0&&abs($daily['baseline_kg']-6.4064)<1e-8,'daily map sums today’s finalised comparisons and planned active trip for the same vehicle');
Carbon::setTestNow(now()->addDay());$nextDay=CarbonRewards::daily($active,1.92192);
rewardCheck(abs($nextDay['baseline_kg']-3.2032)<1e-8,'daily map excludes yesterday’s completed comparisons after Malaysia midnight');Carbon::setTestNow(now()->subDay());
$active->update(['ride_status'=>'completed','completed_at'=>now()]);$saveReport($active);CarbonRewards::finalize($active->ride_id);
rewardCheck(CarbonRewards::wallet($other->user_id)['earned']===64,'a second passenger keeps an independent points balance');
$group=$trip($passenger,2);CarbonRewards::declare($driver,$group->ride_id,$fields);CarbonRewards::declare($passenger,$group->ride_id,$fields);
$group->update(['ride_status'=>'completed','completed_at'=>now()]);$saveReport($group);CarbonRewards::finalize($group->ride_id);
rewardCheck(DB::table('carbon_reward_ledger')->where('ride_id',$group->ride_id)->count()===1&&DB::table('carbon_reward_ledger')->where('ride_id',$group->ride_id)->value('points')===85,'multi-seat bookings receive their proportional share, not the full saving per seat');
$cancelled=$trip($other);$cancelled->update(['ride_status'=>'cancelled']);rewardStatus(fn()=>CarbonRewards::declare($other,$cancelled->ride_id,$fields),409,'cancelled rides cannot enter the rewards workflow');
$walk=$trip($other);CarbonRewards::declare($driver,$walk->ride_id,['alternative'=>'no_separate_car','confirmed'=>1]);CarbonRewards::declare($other,$walk->ride_id,['alternative'=>'no_separate_car','confirmed'=>1]);
rewardCheck(CarbonRewards::compare($walk,1)['status']==='unavailable','walking or bus alternatives do not invent avoided car emissions');
$negative=$trip($other);CarbonRewards::declare($driver,$negative->ride_id,$fields);CarbonRewards::declare($other,$negative->ride_id,$fields);
$negative->update(['ride_status'=>'completed','completed_at'=>now()]);$saveReport($negative,30);CarbonRewards::finalize($negative->ride_id);
rewardCheck(DB::table('carbon_reward_trips')->where('ride_id',$negative->ride_id)->value('saved_kg')<0&&DB::table('carbon_reward_ledger')->where('ride_id',$negative->ride_id)->value('points')===0,'a detour that emits more keeps negative savings and earns zero points');
$rewardId=DB::table('carbon_reward_catalog')->insertGetId(['title'=>'Synthetic coffee voucher','provider'=>'TEST ONLY','terms'=>'No real merchant value.','points_cost'=>20,'active'=>true,'created_by'=>$admin->user_id,'created_at'=>now(),'updated_at'=>now()]);
CarbonRewards::addVouchers($admin,$rewardId,['SYNTHETIC-CODE-ONE'],now()->addMonth()->format('Y-m-d H:i:s'));
rewardCheck(DB::table('carbon_reward_vouchers')->value('encrypted_code')!=='SYNTHETIC-CODE-ONE','voucher codes are encrypted at rest');
$before=CarbonRewards::wallet($passenger->user_id);$key=(string)Illuminate\Support\Str::uuid();$receipt=CarbonRewards::redeem($passenger,$rewardId,$key);
rewardCheck(CarbonRewards::redeem($passenger,$rewardId,$key)===$receipt&&CarbonRewards::wallet($passenger->user_id)['balance']===$before['balance']-20&&DB::table('carbon_reward_redemptions')->count()===1,'retrying a claim reserves one voucher and deducts its cost once');
rewardCheck(CarbonRewards::wallet($passenger->user_id)['earned']===$before['earned']&&CarbonRewards::leaderboard()->first()->user_id===$passenger->user_id,'redemption keeps lifetime earned points and all-time leaderboard ranking');
rewardReject(fn()=>CarbonRewards::redeem($other,$rewardId,(string)Illuminate\Support\Str::uuid()),'another account cannot claim a voucher already reserved');
rewardCheck(CarbonRewards::wallet($other->user_id)['balance']===64,'out-of-stock redemption leaves the second passenger balance unchanged');
CarbonRewards::addVouchers($admin,$rewardId,['EXPIRED-TEST'],now()->subDay()->format('Y-m-d H:i:s'));
rewardReject(fn()=>CarbonRewards::redeem($other,$rewardId,(string)Illuminate\Support\Str::uuid()),'expired voucher inventory is never issued');
DB::table('carbon_reward_catalog')->where('id',$rewardId)->update(['points_cost'=>1000000]);
rewardReject(fn()=>CarbonRewards::redeem($other,$rewardId,(string)Illuminate\Support\Str::uuid()),'insufficient points cannot create a negative wallet');
rewardStatus(fn()=>CarbonRewards::addVouchers($other,$rewardId,['UNAUTHORISED'],now()->addDay()->format('Y-m-d H:i:s')),403,'passengers cannot supply reward inventory');
rewardReject(fn()=>CarbonRewards::addVouchers($admin,$rewardId,['NEW-BATCH','SYNTHETIC-CODE-ONE'],now()->addDay()->format('Y-m-d H:i:s')),'duplicate voucher batch rolls back every new code');
rewardCheck(!DB::table('carbon_reward_vouchers')->where('code_hash',hash('sha256','NEW-BATCH'))->exists(),'failed stock upload is atomic');
$controller=new CarbonRewardsController;
$as=function($u,$path='/carbon/rewards'){$r=Request::create('http://localhost'.$path);$r->setLaravelSession(app('session.store'));$r->setUserResolver(fn()=>$u);app()->instance('request',$r);Auth::forgetGuards();Auth::guard()->setUser($u);return $r;};
$as($other);rewardStatus(fn()=>$controller->receipt($receipt),404,'a passenger cannot read another passenger’s voucher receipt');
$as($passenger);$receiptPage=$controller->receipt($receipt);
rewardCheck(str_contains($receiptPage->getContent(),'SYNTHETIC-CODE-ONE')&&str_contains($receiptPage->headers->get('Cache-Control'),'no-store'),'only the owner can view their private uncached voucher receipt');
foreach([$passenger,$driver,$admin] as $u){$as($u);$page=$controller->index();$html=$page->getContent();rewardCheck(str_contains($html,'All-time leaderboard')&&!str_contains($html,'Weekly leaderboard')&&!str_contains($html,'SYNTHETIC-CODE-ONE'),'rewards page renders all-time ranking for '.$u->account_type.' without leaking voucher codes');}
$as($admin);$adminHtml=$controller->index()->getContent();rewardCheck(str_contains($adminHtml,'Create a reward')&&str_contains($adminHtml,'Add voucher stock'),'admin can manage real reward listings and voucher inventory');
// Voucher settings, authorisation, decimal validation and immutable claim values.
$settingsRequest=function(array $input){$r=Request::create('http://localhost/carbon/rewards/catalog','POST',$input);$r->setLaravelSession(app('session.store'));return $r;};
$listing=['title'=>'Synthetic RM discount voucher','provider'=>'TEST ONLY','terms'=>'Synthetic test only.','discount_rm'=>'5.50','points_cost'=>20];
$controller->create($settingsRequest($listing));
$configured=DB::table('carbon_reward_catalog')->where('title',$listing['title'])->first();
rewardCheck((float)$configured->discount_rm===5.5&&(int)$configured->points_cost===20&&!$configured->active,'admin creates an inactive voucher with a configured RM discount and points requirement');
rewardCheck(DB::table('carbon_reward_catalog')->where('id',$rewardId)->value('discount_rm')===null&&DB::table('carbon_reward_redemptions')->where('id',$receipt)->value('discount_rm')===null,'existing listings and receipts are not assigned invented discount values');
foreach(['0','-1','1.005','1e2','1000000','abc'] as $invalid)rewardReject(fn()=>$controller->settings($settingsRequest(['discount_rm'=>$invalid,'points_cost'=>30]),$configured->id),'invalid RM discount rejected: '.$invalid);
foreach([0,-1,1.5,1000001] as $invalid)rewardReject(fn()=>$controller->settings($settingsRequest(['discount_rm'=>'10.00','points_cost'=>$invalid]),$configured->id),'invalid points requirement rejected: '.$invalid);
rewardReject(fn()=>$controller->create($settingsRequest(array_diff_key($listing,['discount_rm'=>true]))),'new vouchers require an explicit RM discount');
rewardStatus(fn()=>$controller->settings($settingsRequest(['discount_rm'=>'10.00','points_cost'=>30]),999999),404,'editing a missing reward returns not found');
foreach([$passenger,$driver] as $u){$as($u);rewardStatus(fn()=>$controller->settings($settingsRequest(['discount_rm'=>'99.00','points_cost'=>1]),$configured->id),403,'non-admin cannot change voucher values or points');rewardStatus(fn()=>$controller->create($settingsRequest($listing)),403,'non-admin cannot create a voucher');}
$as($admin);$controller->settings($settingsRequest(['discount_rm'=>'10.00','points_cost'=>30]),$configured->id);
$controller->availability($settingsRequest(['active'=>1]),$configured->id);
CarbonRewards::addVouchers($admin,$configured->id,['SETTINGS-TEST-ONE','SETTINGS-TEST-TWO'],now()->addMonth()->format('Y-m-d H:i:s'));
$as($passenger);$settingsHtml=$controller->index()->getContent();
rewardCheck(str_contains($settingsHtml,'RM10.00')&&str_contains($settingsHtml,'points to claim')&&!str_contains($settingsHtml,'Save voucher settings'),'passenger sees configured RM off and claim cost without admin editing controls');
$balanceBefore=CarbonRewards::wallet($passenger->user_id)['balance'];
rewardReject(fn()=>CarbonRewards::redeem($passenger,$configured->id,(string)Illuminate\Support\Str::uuid(),['points_cost'=>20,'discount_rm'=>'5.50']),'outdated discount and points review cannot spend points');
rewardCheck(CarbonRewards::wallet($passenger->user_id)['balance']===$balanceBefore&&DB::table('carbon_reward_vouchers')->where('reward_id',$configured->id)->whereNull('claimed_at')->count()===2,'outdated review leaves wallet and voucher stock unchanged');
$claimKey=(string)Illuminate\Support\Str::uuid();
$claimResponse=$controller->redeem($settingsRequest(['request_key'=>$claimKey,'confirm'=>1,'points_cost'=>30,'discount_rm'=>'10.00']),$configured->id);
$configuredReceipt=DB::table('carbon_reward_redemptions')->where('request_key',$claimKey)->first();
rewardCheck($claimResponse->isRedirect()&&(float)$configuredReceipt->discount_rm===10.0&&(int)$configuredReceipt->points_cost===30&&CarbonRewards::wallet($passenger->user_id)['balance']===$balanceBefore-30,'passenger claim snapshots RM discount and deducts the configured points once');
$as($admin);$controller->settings($settingsRequest(['discount_rm'=>'15.25','points_cost'=>40]),$configured->id);
$as($passenger);$preservedReceipt=$controller->receipt($configuredReceipt->id)->getContent();
rewardCheck(str_contains($preservedReceipt,'RM10.00')&&str_contains($preservedReceipt,'30 points spent')&&!str_contains($preservedReceipt,'RM15.25'),'existing receipt keeps original discount and points after admin edits');
rewardCheck(CarbonRewards::redeem($passenger,$configured->id,$claimKey,['points_cost'=>30,'discount_rm'=>'10.00'])===$configuredReceipt->id&&CarbonRewards::wallet($passenger->user_id)['balance']===$balanceBefore-30,'retrying an earlier claim after an edit remains idempotent');
rewardReject(fn()=>CarbonRewards::redeem($passenger,$configured->id,(string)Illuminate\Support\Str::uuid(),['points_cost'=>40,'discount_rm'=>'10.00']),'changed discount alone requires a fresh passenger review');
rewardReject(fn()=>CarbonRewards::redeem($passenger,$configured->id,(string)Illuminate\Support\Str::uuid(),['points_cost'=>30,'discount_rm'=>'15.25']),'changed points alone requires a fresh passenger review');
$as($admin);$editHtml=$controller->index()->getContent();rewardCheck(str_contains($editHtml,'Edit discount &amp; points')||str_contains($editHtml,'Edit discount & points'),'admin can open voucher settings from each reward card');
$auto=$trip($other);CarbonRewards::declare($driver,$auto->ride_id,$fields);CarbonRewards::declare($other,$auto->ride_id,$fields);
$auto->update(['ride_status'=>'in_progress','started_at'=>now()]);$as($driver);
$complete=Request::create('http://localhost/driver.php','POST',['action'=>'update_status','ride_id'=>$auto->ride_id,'next_status'=>'completed']);$complete->setLaravelSession(app('session.store'));app()->instance('request',$complete);
(new App\Http\Controllers\DriverController)->handleAction($complete);
rewardCheck($auto->fresh()->ride_status==='completed'&&DB::table('carbon_reward_trips')->where('ride_id',$auto->ride_id)->exists()&&DB::table('carbon_reward_ledger')->where('ride_id',$auto->ride_id)->where('user_id',$other->user_id)->exists(),'the existing Complete trip action automatically saves the footprint and awards eligible points');
$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);
foreach([$passenger,$driver,$admin] as $u){$r=$as($u);$response=$kernel->handle($r);rewardCheck($response->getStatusCode()===200,'authenticated HTTP Carbon Rewards page works for '.$u->full_name);}
$r=Request::create('http://localhost/carbon/rewards/'.$rewardId.'/redeem','POST',['request_key'=>(string)Illuminate\Support\Str::uuid(),'confirm'=>1],[],[],['HTTP_ACCEPT'=>'application/json']);
$as($passenger);rewardCheck($kernel->handle($r)->getStatusCode()===419,'real redemption endpoint requires a valid CSRF token');
$routes=app('router')->getRoutes();$route=$routes->match(Request::create('/carbon/rewards/catalog','POST'));
rewardCheck(in_array('admin',$route->gatherMiddleware(),true),'real reward catalogue mutations are restricted by admin middleware');
$settingsRoute=$routes->match(Request::create('/carbon/rewards/catalog/'.$configured->id.'/settings','POST'));
rewardCheck(in_array('admin',$settingsRoute->gatherMiddleware(),true)&&in_array('web',$settingsRoute->gatherMiddleware(),true),'voucher settings endpoint requires admin access and CSRF middleware');
$as($admin);$csrfSettings=Request::create('http://localhost/carbon/rewards/catalog/'.$configured->id.'/settings','POST',['discount_rm'=>'99.00','points_cost'=>1],[],[],['HTTP_ACCEPT'=>'application/json']);
rewardCheck($kernel->handle($csrfSettings)->getStatusCode()===419,'voucher settings endpoint rejects missing CSRF token');
if(in_array('--preview',$argv,true)){
    Illuminate\Support\Facades\URL::forceRootUrl('http://workshop.test');
    $as($passenger);$html=$controller->index()->getContent();$html=str_replace('<form ','<form inert ',$html);
    $html=str_replace('</head>','<meta name="robots" content="noindex,nofollow"></head>',$html);
    $html=str_replace('<body ','<body data-synthetic-preview="true" ',$html);
    file_put_contents(__DIR__.'/../storage/app/carbon-rewards-preview.html',str_replace('<h1>Carbon Rewards</h1>','<h1>Carbon Rewards · SYNTHETIC PREVIEW</h1>',$html));
    $as($admin);$html=$controller->index()->getContent();$html=str_replace('<form ','<form inert ',$html);
    file_put_contents(__DIR__.'/../storage/app/carbon-rewards-admin-preview.html',str_replace('<h1>Carbon Rewards</h1>','<h1>Carbon Rewards · SYNTHETIC ADMIN PREVIEW</h1>',$html));
}
Http::assertNothingSent();rewardCheck(true,'rewards calculations, ranking and voucher claims make no external requests');
Carbon::setTestNow();echo "$checks carbon reward, daily colour, all-time ranking and redemption checks passed; SQLite memory only.\n";
