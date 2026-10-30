<?php

// Run against this checkout after composer install.
// Every user, booking, trip and carbon snapshot is in SQLite memory.
$root=dirname(__DIR__);
$runtime=$root;
$loader=require $runtime.'/vendor/autoload.php';
if($runtime !== $root) {
    $map=[];
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app')) as $file) {
        if(!$file->isFile() || $file->getExtension() !== 'php')continue;
        $text=file_get_contents($file->getPathname());
        if(preg_match('/namespace\s+([^;]+);/',$text,$ns) && preg_match('/\bclass\s+(\w+)/',$text,$class)) $map[$ns[1].'\\'.$class[1]]=$file->getPathname();
    }
    $loader->addClassMap($map);
}
$app=require $runtime.'/bootstrap/app.php';
$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);$kernel->bootstrap();
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Ride;
use App\Models\RideBooking;
use App\Services\CarbonEstimate;
use App\Http\Controllers\CarbonController;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','cache.default'=>'array','session.driver'=>'array',
    'services.google_maps.api_key'=>'','carbon'=>require $root.'/config/carbon.php','view.compiled'=>$root.'/storage/framework/views',
    'logging.default'=>'single','logging.channels.single.path'=>$root.'/storage/test.log']);
app()->instance('request', Request::create('http://localhost/'));
$app->instance(Illuminate\Cache\RateLimiter::class,new Illuminate\Cache\RateLimiter(Illuminate\Support\Facades\Cache::store('array')));
if(!is_dir(config('view.compiled')))mkdir(config('view.compiled'),0777,true);
DB::purge('sqlite');
view()->getFinder()->setPaths([$root.'/resources/views',$runtime.'/resources/views']);
$router=$app['router'];$router->setRoutes(new Illuminate\Routing\RouteCollection);
Route::middleware('web')->group($root.'/routes/web.php');
foreach(['2026_10_21_000000_create_workshop_schema_baseline.php','2026_10_22_000000_add_journey_and_research_features.php','2026_10_23_000000_add_booking_fare_quotes.php'] as $file)(require $runtime.'/database/migrations/'.$file)->up();
(require $root.'/database/migrations/2026_10_27_000000_add_carbon_estimates.php')->up();
(require $root.'/database/migrations/2026_10_27_000000_add_carbon_estimates.php')->up();
Carbon::setTestNow(Carbon::parse('2030-01-07 12:00:00','Asia/Kuala_Lumpur'));
app('session')->start();
view()->share('errors',new Illuminate\Support\ViewErrorBag);
$checks=0;
function cpCheck($ok,$label){global $checks;if(!$ok){fwrite(STDERR,"FAIL: $label\n");exit(1);}echo "PASS: $label\n";$checks++;}
function cpReject(callable $fn,$status,$label){try{$fn();cpCheck(false,$label);}catch(ValidationException $e){cpCheck($status===422,$label);}catch(Illuminate\Database\Eloquent\ModelNotFoundException $e){cpCheck($status===404,$label);}catch(HttpExceptionInterface $e){cpCheck($e->getStatusCode()===$status,$label);}}
$users=[];
foreach([['admin','passenger'],['student','driver'],['student','passenger'],['student','passenger'],['student','passenger'],['student','driver']] as $i=>[$role,$type])$users[]=User::create(['full_name'=>'Synthetic role '.$i,'email'=>'synthetic'.$i.'@example.invalid','password_hash'=>'unused','role'=>$role,'account_type'=>$type,'account_status'=>'active']);
[$admin,$driver,$p1,$p2,$p3,$otherDriver]=$users;
$vehicle=Vehicle::create(['user_id'=>$driver->user_id,'vehicle_type'=>'Synthetic car','vehicle_color'=>'White','vehicle_plate'=>'TEST','verification_status'=>'approved','fuel_type'=>'petrol','fuel_l_per_100km'=>7]);
$ride=Ride::create(['driver_id'=>$driver->user_id,'vehicle_id'=>$vehicle->vehicle_id,'pickup_point'=>'Synthetic start','destination'=>'UTeM Main Campus','pickup_latitude'=>2.27,'pickup_longitude'=>102.28,'dropoff_latitude'=>2.3139,'dropoff_longitude'=>102.3183,'departure_at'=>now()->subHours(2),'completed_at'=>now()->subHour(),'available_seats'=>0,'contribution_per_seat'=>2,'vehicle_description'=>'Synthetic car','ride_status'=>'completed']);
$bookings=[];foreach([$p1,$p2,$p3] as $p)$bookings[]=RideBooking::create(['ride_id'=>$ride->ride_id,'passenger_id'=>$p->user_id,'pickup_label'=>'Synthetic pickup','dropoff_label'=>'UTeM Main Campus','seats_requested'=>1,'booking_status'=>'accepted','journey_distance_km'=>10,'distance_source'=>'google_routes_api','baseline_mode'=>'drive_alone','baseline_cars'=>1,'baseline_fuel_type'=>'petrol','baseline_fuel_l_per_100km'=>7]);
$active=Ride::create(['driver_id'=>$driver->user_id,'pickup_point'=>'Synthetic active start','destination'=>'UTeM Main Campus','departure_at'=>now(),'available_seats'=>2,'contribution_per_seat'=>2,'vehicle_description'=>'Synthetic active car','ride_status'=>'in_progress']);
$scheduled=Ride::create(['driver_id'=>$driver->user_id,'pickup_point'=>'Synthetic future start','destination'=>'UTeM Main Campus','departure_at'=>now()->addDay(),'available_seats'=>2,'contribution_per_seat'=>2,'vehicle_description'=>'Synthetic scheduled car','ride_status'=>'scheduled']);
RideBooking::create(['ride_id'=>$scheduled->ride_id,'passenger_id'=>$p1->user_id,'pickup_label'=>'Synthetic future pickup','seats_requested'=>1,'booking_status'=>'accepted']);
$req=function($user,$data=[])use($app){Auth::forgetGuards();Auth::guard()->setUser($user);$r=Request::create('/synthetic','POST',$data,[],[],['HTTP_ACCEPT'=>'application/json']);$r->setUserResolver(fn()=>$user);$r->setLaravelSession(app('session')->driver());app()->instance('request',$r);return $r;};
$data=['fuel_type'=>'petrol','fuel_l_per_100km'=>7,'shared_km'=>12,'distance_basis'=>'odometer','driver_would_travel'=>'yes','driver_baseline_km'=>10];
$cc=new CarbonController;
cpCheck(CarbonEstimate::ready(),'carbon schema is ready and idempotent');
cpCheck(abs(CarbonEstimate::emissions(12,7,'petrol')-1.92192)<1e-8,'petrol mobile-combustion units match the published factor');
cpReject(fn()=>CarbonEstimate::emissions(12,0,'petrol'),422,'zero consumption cannot create fictitious zero emissions');
cpReject(fn()=>CarbonEstimate::emissions(12,7,'unsupported'),422,'unknown fuels do not silently receive a default factor');
$result=CarbonEstimate::calculate($ride->fresh()->load('bookings'),$data);
cpCheck(abs($result['baseline_kg']-6.4064)<1e-8 && abs($result['shared_kg']-1.92192)<1e-8 && abs($result['avoided_kg']-4.48448)<1e-8,'four separate 10 km petrol cars versus one 12 km trip matches the worked example');
$bookings[0]->update(['seats_requested'=>2]);
cpCheck(abs(CarbonEstimate::calculate($ride->fresh()->load('bookings'),$data)['baseline_kg']-6.4064)<1e-8,'two booked seats do not double a declared single alternative car');
$bookings[0]->update(['seats_requested'=>1,'journey_distance_km'=>null,'baseline_distance_km'=>10]);
cpCheck(abs(CarbonEstimate::calculate($ride->fresh()->load('bookings'),$data)['baseline_kg']-6.4064)<1e-8,'a passenger-reported route distance supports legacy bookings without a fare-quote distance');
$bookings[0]->update(['journey_distance_km'=>10,'baseline_distance_km'=>null]);
$cc->report($req($driver,$data),$ride->ride_id);$cc->report($req($driver,$data),$ride->ride_id);
cpCheck(DB::table('ride_carbon_reports')->count()===1,'repeated saves update one snapshot rather than duplicating savings');
cpCheck(CarbonEstimate::summary()['count']===1 && abs(CarbonEstimate::summary()['avoided']-4.48448)<1e-8,'completed-trip totals count the trip once');
$snapshot=json_decode(DB::table('ride_carbon_reports')->first()->details,true);
cpCheck($snapshot['fuel_factor']===2.288 && $snapshot['bookings'][0]['fuel_factor']===2.288 && str_contains($snapshot['source'],'July 2025'),'snapshots preserve driver and passenger factor values and source version');
cpReject(fn()=>$cc->report($req($otherDriver,$data),$ride->ride_id),404,'another driver cannot overwrite this carbon report');
cpReject(fn()=>$cc->report($req($p1,$data),$ride->ride_id),403,'passengers cannot submit shared-vehicle distance reports');
cpReject(fn()=>$cc->report($req($driver,$data),$scheduled->ride_id),404,'scheduled trips cannot be counted as completed carbon savings');
cpReject(fn()=>$cc->baseline($req($p2,['baseline_mode'=>'walk_cycle']),$bookings[0]->booking_id),404,'passengers cannot change another passenger baseline');
cpReject(fn()=>CarbonEstimate::baseline(Request::create('/','POST',['baseline_mode'=>'drive_alone','baseline_cars'=>2,'baseline_fuel_type'=>'petrol','baseline_fuel_l_per_100km'=>7]),1),422,'alternative cars cannot exceed the booked group size');
$cc->baseline($req($p1,['baseline_mode'=>'unknown']),$bookings[0]->booking_id);
cpCheck(!DB::table('ride_carbon_reports')->first()->needs_review && DB::table('ride_carbon_reports')->first()->avoided_kg===null && CarbonEstimate::summary()['count']===0,'changed passenger baselines automatically refresh and remove incomplete savings');
$cc->report($req($driver,$data),$ride->ride_id);
cpCheck(DB::table('ride_carbon_reports')->first()->avoided_kg===null,'unknown transport modes produce emissions-only results, not invented savings');
$cc->baseline($req($p1,['baseline_mode'=>'bus']),$bookings[0]->booking_id);$cc->report($req($driver,$data),$ride->ride_id);
cpCheck(DB::table('ride_carbon_reports')->first()->avoided_kg===null,'bus journeys are not treated as avoided solo cars');
foreach([[$p1,$bookings[0]],[$p2,$bookings[1]],[$p3,$bookings[2]]] as [$p,$b])$cc->baseline($req($p,['baseline_mode'=>'walk_cycle']),$b->booking_id);
$cc->report($req($driver,$data),$ride->ride_id);
cpCheck(abs(DB::table('ride_carbon_reports')->first()->avoided_kg-(-.32032))<1e-8 && CarbonEstimate::summary()['avoided']<0,'additional detour emissions remain negative rather than being clamped to zero');
$cc->vehicle($req($driver,['fuel_type'=>'diesel_b10','fuel_l_per_100km'=>6]),$vehicle->vehicle_id);
cpCheck($vehicle->fresh()->fuel_type==='diesel_b10' && json_decode(DB::table('ride_carbon_reports')->first()->details,true)['fuel_factor']===2.288,'editing a vehicle profile does not rewrite historical factor snapshots');
cpReject(fn()=>$cc->vehicle($req($otherDriver,['fuel_type'=>'petrol','fuel_l_per_100km'=>7]),$vehicle->vehicle_id),404,'fuel profiles remain scoped to their owner');

$auto=$ride->replicate();$auto->save();
$autoBooking=RideBooking::create(['ride_id'=>$auto->ride_id,'passenger_id'=>$p1->user_id,'pickup_label'=>'Detour pickup','dropoff_label'=>'Campus','pickup_latitude'=>2.25,'pickup_longitude'=>102.30,'dropoff_latitude'=>2.3139,'dropoff_longitude'=>102.3183,'arrive_by'=>now()->subDays(2),'seats_requested'=>1,'booking_status'=>'accepted','journey_distance_km'=>10,'baseline_mode'=>'walk_cycle']);
$inputs=\App\Services\AutomaticCarbon::inputs($auto->fresh());
cpCheck($inputs && $inputs['shared_km']>$inputs['driver_baseline_km'] && $inputs['fuel_type']==='diesel_b10','automatic input uses the saved fuel profile and includes passenger detours despite expired historical deadlines');
$before=DB::table('ride_carbon_reports')->count();
$preview=\App\Services\AutomaticCarbon::preview($auto->fresh());
cpCheck($preview->shared_kg>0 && $preview->avoided_kg===null && DB::table('ride_carbon_reports')->count()===$before,'read-only previews estimate emissions without guessing the driver alternative or writing records');
\App\Services\AutomaticCarbon::recordCompleted($auto->ride_id);\App\Services\AutomaticCarbon::recordCompleted($auto->ride_id);
cpCheck(DB::table('ride_carbon_reports')->where('ride_id',$auto->ride_id)->count()===1,'automatic completion creates exactly one report');
$automaticBefore=DB::table('ride_carbon_reports')->where('ride_id',$auto->ride_id)->first();
$autoData=$inputs;$autoData['driver_would_travel']='yes';
$cc->report($req($driver,$autoData),$auto->ride_id);
$cc->baseline($req($p1,['baseline_mode'=>'no_trip']),$autoBooking->booking_id);
$automaticAfter=DB::table('ride_carbon_reports')->where('ride_id',$auto->ride_id)->first();
cpCheck($automaticAfter->avoided_kg<0 && !$automaticAfter->needs_review,'confirmed alternatives recalculate automatically and retain negative detour impacts');
cpCheck($automaticAfter->shared_km===$automaticBefore->shared_km && $automaticAfter->shared_kg===$automaticBefore->shared_kg,'baseline refresh preserves the shared distance and vehicle emissions');
$missing=$auto->fresh();$missing->setRelation('vehicle',new Vehicle);
cpCheck(\App\Services\AutomaticCarbon::preview($missing)===null,'missing fuel profiles remain pending instead of inventing consumption');
$legacy=$auto->fresh();$legacy->pickup_latitude=null;$legacy->pickup_longitude=null;$legacy->pickup_point='Unknown legacy origin';
cpCheck(\App\Services\AutomaticCarbon::preview($legacy)===null,'unknown legacy locations do not receive fabricated trip distance');
cpCheck(\App\Services\AutomaticCarbon::preview($scheduled->fresh())===null,'scheduled trips never receive automatic carbon estimates');
$autoActive=$auto->replicate();$autoActive->ride_status='in_progress';$autoActive->completed_at=null;$autoActive->save();
(new \App\Http\Controllers\DriverController)->handleAction($req($driver,['action'=>'update_status','ride_id'=>$autoActive->ride_id,'next_status'=>'completed']));
cpCheck($autoActive->fresh()->ride_status==='completed' && DB::table('ride_carbon_reports')->where('ride_id',$autoActive->ride_id)->exists(),'the actual complete-trip action automatically saves emissions without a carbon form');

function cpPage($kernel,$user,$path){Auth::forgetGuards();if($user)Auth::guard()->setUser($user);$r=Request::create('http://localhost/'.$path,'GET',[],[],[],['HTTP_ACCEPT'=>'text/html']);app()->instance('request',$r);return $kernel->handle($r);}
$paths=[[$driver,'driver.php'],[$driver,'driver/offer'],[$driver,'driver/requests'],[$driver,'driver/schedule'],[$driver,'driver/history'],[$driver,'driver/vehicles'],[$driver,'carbon'],[$p1,'dashboard.php'],[$p1,'findride.php'],[$p1,'passenger/bookings'],[$p1,'passenger/schedule'],[$p1,'passenger/history'],[$p1,'carbon'],[$p1,'timetable'],[$admin,'admin.php'],[$admin,'admin/live'],[$admin,'admin/rides'],[$admin,'admin/members'],[$admin,'admin/vehicles'],[$admin,'carbon']];
$pages=[];
foreach($paths as [$user,$path]){$response=cpPage($kernel,$user,$path);$html=$response->getContent();if($response->getStatusCode()!==200)file_put_contents($root.'/storage/page-error.html',$html);cpCheck($response->getStatusCode()===200,$path.' renders for its authorized role');cpCheck(substr_count($html,'aria-current="page"')===1,$path.' has one current navigation item');$pages[$path]=$html;}
foreach([$p1,$driver,$admin] as $user) {
    foreach(['research','research/export/survey','research/export/samples','research/export/usability'] as $path) cpCheck(cpPage($kernel,$user,$path)->getStatusCode()===404,'Research pages and exports are retired for every role');
}
foreach($pages as $path=>$html) cpCheck(!str_contains($html,'/research'),'Research links are absent from '.$path);
cpCheck(!str_contains($pages['driver.php'],'id="seat-requests"') && !str_contains($pages['driver.php'],'id="offer-ride"'),'driver overview does not contain the offer and request pages');
cpCheck(str_contains($pages['driver.php'],'class="journey-cta" href="http://localhost/driver/offer"'),'green Offer a ride button links to the separate offer page');
$driverCarbon=cpPage($kernel,$driver,'carbon')->getContent();
cpCheck(str_contains($driverCarbon,'Automatic planned-route approximation') && str_contains($driverCarbon,'Optional: confirm savings or correct this estimate'),'carbon results show automatic distance and keep corrections optional');
cpCheck(str_contains($pages['driver/offer'],'data-journey-map') && !str_contains($pages['driver/offer'],'id="seat-requests"'),'driver offer page contains only the relevant form');
cpCheck(str_contains($pages['driver/schedule'],'Synthetic active start') && !str_contains($pages['driver/schedule'],'id="ride-'.$ride->ride_id.'"'),'driver schedule excludes completed trip cards');
cpCheck(str_contains($pages['driver/history'],'id="ride-'.$ride->ride_id.'"') && !str_contains($pages['driver/history'],'id="ride-'.$scheduled->ride_id.'"'),'driver history excludes future trip cards');
foreach(['driver.php'=>'driver/history','dashboard.php'=>'passenger/history'] as $page=>$historyPath) {
    preg_match('/<nav class="side-nav".*?<\/nav>/s',$pages[$page],$mainNav);
    cpCheck(!str_contains($mainNav[0],'/'.$historyPath) && str_contains($pages[$page],'data-profile-menu') && str_contains($pages[$page],'href="http://localhost/'.$historyPath.'"'),'History is available through the profile menu instead of main navigation on '.$page);
}
cpCheck(str_contains($pages['driver/vehicles'],'id="driverVehicleSelect"') && str_contains($pages['driver/vehicles'],'data-vehicle-panel=') && str_contains($pages['driver/vehicles'],'Apply for another vehicle'),'vehicle applications use a selector and expandable application form');
cpCheck(str_contains($pages['findride.php'],'data-auto-pickup') && str_contains($pages['findride.php'],'Use current location') && !str_contains($pages['findride.php'],'name="arrive_by"'),'passenger search uses current pickup and has no visible or hidden arrival-deadline input');
foreach(['driver.php','driver/offer','driver/requests','driver/schedule','driver/history','driver/vehicles','carbon'] as $path){$response=cpPage($kernel,$driver,$path);cpCheck(substr_count($response->getContent(),'data-location-url=')===1,'active GPS is mounted once on driver page '.$path);}
cpCheck(str_contains($pages['admin/live'],'id="admin-driver-map"') && !str_contains($pages['admin/members'],'id="admin-driver-map"'),'admin map and member approval are separate server-rendered pages');
cpCheck(str_contains($pages['admin/rides'],'Synthetic start') && !str_contains($pages['admin/rides'],'No ride activity yet'),'admin activity table reads actual trip rows');
cpCheck(str_contains($pages['timetable'],'id="timetable"') && !str_contains($pages['timetable'],'id="survey"'),'timetable is separated from the research forms');
cpCheck(!str_contains($pages['driver.php'],'href="http://localhost/timetable"') && str_contains($pages['dashboard.php'],'href="http://localhost/timetable"'),'Timetable is removed from driver navigation and remains available to passengers');
cpCheck(cpPage($kernel,$driver,'timetable')->isRedirect('http://localhost/driver.php'),'old driver timetable links return to driver overview');
cpReject(fn()=>(new \App\Http\Controllers\TimetableController)->store($req($driver)),403,'drivers cannot add timetable sessions');
cpReject(fn()=>(new \App\Http\Controllers\TimetableController)->destroy(1),403,'drivers cannot remove existing timetable records');
cpCheck(!str_contains($pages['dashboard.php'],'id="upcoming"') && str_contains($pages['passenger/bookings'],'id="upcoming"'),'passenger overview and bookings render separate content');
foreach([[$p1,'admin/live'],[$p1,'driver/offer'],[$driver,'passenger/bookings']] as [$user,$path])cpCheck(in_array(cpPage($kernel,$user,$path)->getStatusCode(),[302,403],true),'role restrictions protect '.$path);
cpCheck(cpPage($kernel,$driver,'driver/not-a-page')->getStatusCode()===404,'unknown navigation sections return 404');
cpCheck(cpPage($kernel,$p1,'carbon/export')->getStatusCode()!==200,'passengers cannot export the admin carbon report');
$req($admin);$export=$cc->export();ob_start();$export->sendContent();$csv=ob_get_clean();cpCheck(str_contains($csv,'net_avoided_kg_co2e') && str_contains($csv,'CMM2025-MOBILE-v1'),'admin CSV includes reproducible carbon method metadata');
Auth::forgetGuards();Auth::guard()->setUser($driver);$r=Request::create('http://localhost/carbon/rides/'.$ride->ride_id.'/report','POST',$data,[],[],['HTTP_ACCEPT'=>'application/json']);cpCheck($kernel->handle($r)->getStatusCode()===419,'carbon report mutations remain CSRF protected');
if(in_array('--render',$argv,true)){
    foreach([[$p1,$bookings[0]],[$p2,$bookings[1]],[$p3,$bookings[2]]] as [$p,$b])$cc->baseline($req($p,['baseline_mode'=>'drive_alone','baseline_cars'=>1,'baseline_fuel_type'=>'petrol','baseline_fuel_l_per_100km'=>7]),$b->booking_id);
    $cc->report($req($driver,$data),$ride->ride_id);
    $directory=$root.'/storage/app/pages-preview';if(!is_dir($directory))mkdir($directory,0777,true);
    foreach([[$driver,'driver/offer','driver-offer'],[$p1,'passenger/schedule','passenger-schedule'],[$admin,'admin.php','admin-overview'],[$admin,'carbon','carbon']] as [$user,$url,$name]){
        $html=cpPage($kernel,$user,$url)->getContent();
        $html=str_replace('http://localhost/','http://127.0.0.1:8767/',$html);
        // Preview images contain synthetic data; disable synthetic GPS endpoints.
        $html=preg_replace('/<script src="[^"]*driver-location.js[^\"]*" defer><\/script>/','',$html);
        $html=str_replace('<div class="app-shell">','<div style="padding:12px;background:#fff2c9;text-align:center">TEST PREVIEW — synthetic data</div><div class="app-shell">',$html);
        file_put_contents($directory.'/'.$name.'.html',$html);
    }
}
Carbon::setTestNow();echo "$checks carbon, scope, navigation and page checks passed; SQLite memory only.\n";
