<?php
// The existing role/page/carbon suite and all added fixtures use SQLite memory.
require __DIR__.'/carbon_and_pages.php';

use App\Services\{CarbonImpact,CarbonPreferences,AutomaticCarbon,CarbonRoadDistance};
use App\Http\Controllers\CarbonToolsController;
use App\Models\RideBooking;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Http,Cache,DB,Auth};

(require $root.'/database/migrations/2026_10_29_030000_add_carbon_impact_tools.php')->up();
(require $root.'/database/migrations/2026_10_29_030000_add_carbon_impact_tools.php')->up();
$tools=new CarbonToolsController;
Http::preventStrayRequests();
Carbon::setTestNow(Carbon::parse('2030-01-07 12:00:00','Asia/Kuala_Lumpur'));
$profile=['baseline_mode'=>'drive_alone','baseline_cars'=>1,'baseline_fuel_type'=>'petrol','baseline_fuel_l_per_100km'=>7,'remember_consent'=>1];
$newRideId=function()use($scheduled){$new=$scheduled->replicate();$new->save();return $new->ride_id;};
cpReject(fn()=>$tools->preferences($req($p1,array_diff_key($profile,['remember_consent'=>true]))),422,'saved usual travel requires explicit reuse consent');
$tools->preferences($req($p1,$profile));
cpCheck(CarbonPreferences::forUser($p1->user_id)->baseline_mode==='drive_alone','passenger can save a usual travel alternative once');
$newBooking=RideBooking::create(['ride_id'=>$newRideId(),'passenger_id'=>$p1->user_id,'seats_requested'=>1,'booking_status'=>'accepted','journey_distance_km'=>12,'pickup_label'=>'Synthetic preference pickup']);
cpCheck($newBooking->baseline_mode==='drive_alone'&&$newBooking->baseline_cars===1&&$newBooking->baseline_distance_km===null,'future bookings reuse fuel and mode but keep their own journey distance');
$explicit=RideBooking::create(['ride_id'=>$newRideId(),'passenger_id'=>$p1->user_id,'seats_requested'=>1,'booking_status'=>'accepted','baseline_mode'=>'walk_cycle']);
cpCheck($explicit->baseline_mode==='walk_cycle','an explicit trip alternative overrides the saved usual travel');
cpCheck($bookings[0]->fresh()->baseline_mode!=='drive_alone','saving a preference does not rewrite existing historical bookings');
$tools->preferences($req($p1,array_replace($profile,['baseline_cars'=>3])));
$small=RideBooking::create(['ride_id'=>$newRideId(),'passenger_id'=>$p1->user_id,'seats_requested'=>1,'booking_status'=>'pending']);
cpCheck($small->baseline_mode===null,'a saved three-car group is not applied to a one-seat booking');
$tools->preferences($req($driver,['driver_would_travel'=>'yes','remember_consent'=>1]));
cpCheck(CarbonPreferences::forUser($driver->user_id)->driver_would_travel==='yes','driver can save whether the original drive would usually happen');
cpReject(fn()=>$tools->preferences($req($admin,['driver_would_travel'=>'yes','remember_consent'=>1])),403,'administrators cannot change commuter preferences through a user id');
$priceFields=['month'=>'2030-01','prices'=>['petrol'=>2.50],'reference'=>'Synthetic pilot rate; not a published retail price'];
cpReject(fn()=>$tools->prices($req($p1,$priceFields)),403,'passengers cannot change monthly fuel prices');
cpReject(fn()=>$tools->prices($req($admin,array_replace($priceFields,['prices'=>['petrol'=>0]]))),422,'zero fuel prices cannot silently manufacture savings');
cpReject(fn()=>$tools->prices($req($admin,array_replace($priceFields,['prices'=>['unlisted'=>2.50]]))),422,'unsupported fuel-price keys are rejected');
$tools->prices($req($admin,$priceFields));
$oldPrice=CarbonImpact::prices('2030-01')['petrol'];
$tools->prices($req($admin,array_replace($priceFields,['prices'=>['petrol'=>3.00],'reference'=>'Revised synthetic assumption'])));
cpCheck(DB::table('carbon_fuel_prices')->count()===2&&CarbonImpact::prices('2030-01')['petrol']->id!==$oldPrice->id,'monthly price changes append revisions and retain the previous assumption');
$tools->prices($req($admin,$priceFields));

// Restore a complete four-car baseline for the worked example.
foreach([[$p1,$bookings[0]],[$p2,$bookings[1]],[$p3,$bookings[2]]] as [$person,$booking])$cc->baseline($req($person,$profile),$booking->booking_id);
$cc->report($req($driver,$data),$ride->ride_id);
$report=DB::table('ride_carbon_reports')->where('ride_id',$ride->ride_id)->first();
$metric=CarbonImpact::metrics($report,CarbonImpact::prices('2030-01'));
cpCheck(abs($metric['shared_litres']-.84)<1e-8&&abs($metric['baseline_litres']-2.8)<1e-8&&abs($metric['saved_litres']-1.96)<1e-8,'fuel saving compares 2.80 L separate travel with 0.84 L shared travel');
cpCheck(abs($metric['saved_rm']-4.90)<1e-8,'RM saving uses recorded fuel prices, not passenger fares');
$missing=CarbonImpact::metrics($report,[]);
cpCheck($missing['saved_rm']===null&&abs($missing['saved_litres']-1.96)<1e-8,'missing prices leave RM unavailable while preserving valid fuel savings');
$changed=clone $report;$changed->needs_review=true;
cpCheck(!CarbonImpact::metrics($changed,CarbonImpact::prices('2030-01'))['comparison_complete'],'reports needing review are excluded from all savings comparisons');
$mixed=json_decode($report->details,true);$mixed['bookings'][0]['fuel']='diesel';
$mixedReport=clone $report;$mixedReport->details=json_encode($mixed);
cpCheck(CarbonImpact::metrics($mixedReport,CarbonImpact::prices('2030-01'))['saved_rm']===null,'a mixed-fuel baseline needs a price for every fuel, not just the shared car');
$tools->prices($req($admin,array_replace($priceFields,['prices'=>['diesel'=>3.00]])));
cpCheck(abs(CarbonImpact::metrics($mixedReport,CarbonImpact::prices('2030-01'))['saved_rm']-5.25)<1e-8,'mixed-fuel comparisons price each baseline fuel separately');
foreach([[$p1,$bookings[0]],[$p2,$bookings[1]],[$p3,$bookings[2]]] as [$person,$booking])$cc->baseline($req($person,['baseline_mode'=>'walk_cycle']),$booking->booking_id);
$negative=CarbonImpact::metrics(DB::table('ride_carbon_reports')->where('ride_id',$ride->ride_id)->first(),CarbonImpact::prices('2030-01'));
cpCheck($negative['saved_litres']<0&&$negative['saved_rm']<0,'additional detour fuel and cost stay negative rather than becoming green savings');
foreach([[$p1,$bookings[0]],[$p2,$bookings[1]],[$p3,$bookings[2]]] as [$person,$booking])$cc->baseline($req($person,$profile),$booking->booking_id);
$cc->report($req($driver,$data),$ride->ride_id);
$privateRide=$ride->replicate();$privateRide->driver_id=$otherDriver->user_id;$privateRide->save();
RideBooking::create(['ride_id'=>$privateRide->ride_id,'passenger_id'=>$p2->user_id,'seats_requested'=>1,'booking_status'=>'accepted','baseline_mode'=>'walk_cycle']);
$before=CarbonImpact::monthly($admin,'2030-01');
cpCheck(count(array_filter($before['rows'],fn($row)=>$row['ride']->ride_id===$ride->ride_id))===1,'administrator monthly results count a shared trip once across multiple passengers');
cpCheck(!collect(CarbonImpact::monthly($otherDriver,'2030-01')['rows'])->contains(fn($row)=>$row['ride']->ride_id===$ride->ride_id),'monthly driver results are scoped to their own rides');
cpCheck(!collect(CarbonImpact::monthly($p1,'2030-01')['rows'])->contains(fn($row)=>$row['ride']->ride_id===$privateRide->ride_id),'monthly passenger results exclude another passenger’s private ride');
$prior=$ride->replicate();$prior->completed_at='2029-12-31 23:59:59';$prior->save();
$nextMonth=$ride->replicate();$nextMonth->completed_at='2030-02-01 00:00:00';$nextMonth->save();
$cancelled=$ride->replicate();$cancelled->ride_status='cancelled';$cancelled->save();
cpCheck(CarbonImpact::monthly($admin,'2030-01')['totals']['trips']===$before['totals']['trips'],'month boundaries and cancelled trips do not inflate completed-trip totals');
cpReject(fn()=>CarbonImpact::month('2030-13'),422,'invalid report months are rejected');
cpCheck(CarbonImpact::monthly($admin,'2020-01')['totals']['comparison_trips']===0,'an empty month has no invented comparisons');

// A saved road quote is only valid for the whole shared journey when endpoints match.
$direct=$ride->replicate();$direct->vehicle_id=$vehicle->vehicle_id;$direct->save();
$directBooking=RideBooking::create(['ride_id'=>$direct->ride_id,'passenger_id'=>$p2->user_id,'booking_status'=>'accepted','seats_requested'=>1,
    'pickup_latitude'=>$direct->pickup_latitude,'pickup_longitude'=>$direct->pickup_longitude,'dropoff_latitude'=>$direct->dropoff_latitude,'dropoff_longitude'=>$direct->dropoff_longitude,
    'pickup_label'=>'Direct pickup','dropoff_label'=>'Direct drop-off','journey_distance_km'=>13,'distance_source'=>'google_routes_api','baseline_mode'=>'walk_cycle']);
$road=AutomaticCarbon::inputs($direct->fresh());
cpCheck($road['distance_basis']==='road_estimate'&&$road['shared_km']==13,'matching direct trips reuse the saved road-route distance instead of the straight-line approximation');
$automatic=AutomaticCarbon::preview($direct->fresh(),true);
cpCheck(json_decode($automatic->details,true)['driver_would_travel']==='yes','new completion reports can use the driver’s explicitly saved usual travel');
cpCheck(json_decode(AutomaticCarbon::preview($direct->fresh())->details,true)['driver_would_travel']==='unknown','historical read-only previews do not apply a new driver preference retroactively');

$directBooking->update(['distance_source'=>'corridor_estimate']);
config(['services.google_maps.api_key'=>'synthetic-road-key']);Cache::flush();
Http::fake(['routes.googleapis.com/*'=>function($request){$detour=count($request['intermediates']??[])>0;return Http::response(['routes'=>[['distanceMeters'=>$detour?25000:18000,'duration'=>'1200s']]]);}]);
$routed=AutomaticCarbon::inputs($auto->fresh(),true);
cpCheck($routed['distance_basis']==='road_estimate'&&$routed['shared_km']==25&&$routed['driver_baseline_km']==18,'road lookup includes pickup detours and estimates the original driver route separately');
Http::assertSent(fn($request)=>count($request['intermediates']??[])>0);
Cache::flush();Http::swap(new Illuminate\Http\Client\Factory);Http::preventStrayRequests();Http::fake(['*'=>Http::response([],503)]);
$fallback=AutomaticCarbon::inputs($auto->fresh(),true);
cpCheck($fallback['distance_basis']==='planned_estimate'&&str_contains($fallback['distance_method'],'straight-line'),'unavailable road routing falls back to a clearly identified planned approximation');
config(['services.google_maps.api_key'=>'']);Cache::flush();

$occupancyRide=$ride->replicate();$occupancyRide->completed_at='2031-02-01 00:00:00';$occupancyRide->save();
foreach([$p1,$p2,$p3] as $person)RideBooking::create(['ride_id'=>$occupancyRide->ride_id,'passenger_id'=>$person->user_id,'seats_requested'=>1,'booking_status'=>'accepted','baseline_mode'=>'walk_cycle']);
$occupancy=CarbonImpact::monthly($admin,'2031-02')['totals'];
cpCheck($occupancy['trips']===1&&$occupancy['accepted_seats']===3&&$occupancy['occupancy']===4,'booked occupancy counts the driver plus three accepted passenger seats');

$request=$req($admin);$export=$tools->export(Request::create('/carbon/export/monthly?month=2030-01'));ob_start();$export->sendContent();$csv=ob_get_clean();
cpCheck(str_contains($csv,'net_fuel_cost_saved_rm')&&str_contains($csv,'rm_per_litre')&&str_contains($csv,'Synthetic pilot rate'),'monthly CSV preserves fuel, price assumptions and comparison coverage');
cpReject(fn()=>$tools->export($req($p1)),403,'passengers cannot download the administrator monthly export');
$tools->prices($req($admin,array_replace($priceFields,['prices'=>['petrol'=>null]])));
cpCheck(CarbonImpact::prices('2030-01')['petrol']->price_per_litre===null&&DB::table('carbon_fuel_prices')->where('fuel','petrol')->count()===4,'clearing a monthly price disables cost estimates without erasing its revisions');

$reportCount=DB::table('ride_carbon_reports')->count();$priceCount=DB::table('carbon_fuel_prices')->count();
foreach([$admin,$p1,$driver] as $person){$req($person);$html=$cc->index()->getContent();cpCheck(str_contains($html,'Monthly transport impact')&&str_contains($html,'NET FUEL SAVED'),'carbon impact renders for each role');}
cpCheck(DB::table('ride_carbon_reports')->count()===$reportCount&&DB::table('carbon_fuel_prices')->count()===$priceCount,'opening a monthly impact page never writes reports or pricing assumptions');
foreach([[$p1,'carbon/preferences'],[$admin,'carbon/prices']] as [$person,$path]){
    Auth::forgetGuards();Auth::guard()->setUser($person);$r=Request::create('http://localhost/'.$path,'POST',[],[],[],['HTTP_ACCEPT'=>'application/json']);
    cpCheck($kernel->handle($r)->getStatusCode()===419,'carbon setup mutations retain CSRF protection');
}
cpCheck(cpPage($kernel,$p1,'carbon/export/monthly?month=2030-01')->getStatusCode()!==200,'monthly report route protects administrator exports');
if(in_array('--impact-preview',$argv,true)) {
    $tools->prices($req($admin,$priceFields));
    \Illuminate\Support\Facades\URL::forceRootUrl('http://localhost/workshop');
    $folder=$root.'/public/device-review/carbon-impact';if(!is_dir($folder))mkdir($folder,0777,true);
    foreach([[$admin,'admin'],[$p1,'passenger'],[$driver,'driver']] as [$person,$name]){
        session()->forget('carbonNotice');
        $req($person);$html=$cc->index()->getContent();
        $html=preg_replace('~<form\b~','<form inert',$html);
        $html=str_replace('data-schedule-robot data-endpoint','data-schedule-robot data-preview-events="{&quot;events&quot;:[]}" data-endpoint',$html);
        $html=preg_replace('~<details class="global-driver-gps">.*?</details>~s','',$html);
        $html=preg_replace('~<script\b[^>]*src="[^"]*driver-location\.js[^>]*>.*?</script>~s','',$html);
        $html=str_replace('<body class="app-page research-page">','<body class="app-page research-page"><div style="padding:8px;background:#e9f0df;text-align:center;font:11px system-ui">DEMO DATA · Synthetic trips and prices, no live account or booking</div>',$html);
        $html=str_replace('</head>','<meta name="robots" content="noindex,nofollow"></head>',$html);
        file_put_contents($folder.'/'.$name.'.html',$html);
    }
}
echo "Carbon impact: $checks total calculation, monthly-report, privacy and preference checks passed; SQLite memory only.\n";
