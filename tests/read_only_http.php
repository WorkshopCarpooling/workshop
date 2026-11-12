<?php

// Uses existing accounts for read-only controller checks; never submits mutations.
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// Kernel bootstrap registers a limiter before the tests configure cache stores.
// Keep page verification separate from real user request counters.
$app->instance(Illuminate\Cache\RateLimiter::class,new Illuminate\Cache\RateLimiter(Illuminate\Support\Facades\Cache::store('array')));

$checks = 0;
function getPage($kernel, $path, $user, $json = false) {
    $request = Request::create('http://localhost/workshop/' . $path, 'GET', [], [], [], ['SCRIPT_NAME'=>'/workshop/index.php', 'SCRIPT_FILENAME'=>dirname(__DIR__).'/public/index.php', 'HTTP_ACCEPT'=>$json?'application/json':'text/html']);
    app()->instance('request', $request);
    Auth::forgetGuards();
    if ($user) Auth::guard()->setUser($user);
    $response = $kernel->handle($request);
    return $response;
}
function httpCheck($condition, $label) {
    global $checks;
    if (!$condition) { fwrite(STDERR,"FAIL: $label\n");exit(1); }
    $checks++; echo "PASS: $label\n";
}
$counts = [];
foreach (DB::select('SHOW TABLES') as $row) { $table=array_values((array)$row)[0]; $counts[$table]=DB::table($table)->count(); }
$passenger=User::where('account_type','passenger')->where('role','student')->where('account_status','active')->first();
$driver=User::where('account_type','driver')->where('role','student')->where('account_status','active')->first();
$admin=User::where('role','admin')->first();
httpCheck($passenger && $driver && $admin, 'existing test roles available (no accounts created)');
foreach ([[$passenger,'dashboard.php'],[$passenger,'findride.php'],[$driver,'driver.php'],[$admin,'admin.php']] as [$user,$path]) {
    $response=getPage($kernel,$path,$user);
    httpCheck($response->getStatusCode()===200, "$path renders for its authorized role (HTTP ".$response->getStatusCode().')');
    httpCheck(str_contains($response->getContent(),'class="account-nav"') && str_contains($response->getContent(),'Log out'), 'navigation includes account and logout');
}
foreach([$passenger,$driver,$admin] as $user) { httpCheck(getPage($kernel,'research',$user)->getStatusCode()===404,'Research is retired'); httpCheck(getPage($kernel,'research/export/survey',$user)->getStatusCode()===404,'Research exports are retired'); }
httpCheck(getPage($kernel,'timetable',$driver)->isRedirect('http://localhost/workshop/driver.php'),'driver timetable bookmarks return to overview');
foreach([[$passenger,'passenger/bookings'],[$passenger,'passenger/schedule'],[$passenger,'passenger/history'],[$passenger,'carbon'],[$passenger,'timetable'],[$driver,'driver/requests'],[$driver,'driver/vehicles'],[$driver,'driver/offer'],[$driver,'driver/schedule'],[$driver,'driver/history'],[$driver,'carbon'],[$admin,'admin/live'],[$admin,'admin/rides'],[$admin,'admin/members'],[$admin,'admin/vehicles'],[$admin,'carbon']] as [$user,$path]) {
    $response=getPage($kernel,$path,$user);
    httpCheck($response->getStatusCode()===200,"$path loads existing authorized data without mutation");
    httpCheck(substr_count($response->getContent(),'aria-current="page"')===1,"$path selects exactly one navigation item");
}
httpCheck(getPage($kernel,'api/locations/search?q=Mydin',$passenger,true)->getStatusCode()===200,'known-place search returns JSON');
$response=getPage($kernel,'findride.php?pickup_label=Mydin&pickup_latitude=91&pickup_longitude=102.2&dropoff_label=Campus&dropoff_latitude=2.3&dropoff_longitude=102.3&seats_requested=1',$passenger,true);
httpCheck($response->getStatusCode()===422,'invalid search coordinates return validation errors');
$response=getPage($kernel,'findride.php?pickup_label=Mydin&pickup_latitude=2.2707&pickup_longitude=102.2856&dropoff_label=Campus&dropoff_latitude=2.3139&dropoff_longitude=102.3183&seats_requested=1',$passenger,true);
httpCheck($response->getStatusCode()===200 && is_array($response->getData(true)['rides']), 'valid journey search returns compatible ride list');
foreach (App\Models\Ride::where('driver_id',$driver->user_id)->pluck('ride_id') as $id) {
    $response=getPage($kernel,'driver/route/'.$id,$driver);
    httpCheck(in_array($response->getStatusCode(),[200,422],true),'owned route returns page or explicit legacy-coordinate constraint');
    if ($response->getStatusCode() === 200) httpCheck(str_contains($response->getContent(),'A* planned journey') && str_contains($response->getContent(),'shortest feasible stop order under the distance estimates'), 'owned journey renders the active A* planner and its distance assumptions');
    httpCheck(getPage($kernel,'driver/route/'.$id,$passenger)->getStatusCode()===302,'passenger cannot open driver route');
    if ($response->getStatusCode() === 200) break;
}
// Render anonymized HTML for local visual inspection, without touching the DB.
if (in_array('--render', $argv, true)) {
    $directory = storage_path('app/ui-review');
    if (!is_dir($directory)) mkdir($directory,0777,true);
    $preview = new User(['full_name'=>'Preview user','role'=>'student','account_type'=>'passenger','account_status'=>'active']);
    Auth::guard()->setUser($preview);
    app('session')->start();
    $html=view('findride',['currentUser'=>$preview,'availableRides'=>[],'timetable'=>collect(),'bookingNotice'=>''])->render();
    file_put_contents($directory.'/findride.html',$html);
    echo "Anonymized HTML review files rendered.\n";
}
foreach ($counts as $table=>$count) httpCheck(DB::table($table)->count()===$count,"$table record count unchanged");
echo "$checks HTTP/render/data-preservation checks passed; no database records inserted.\n";
