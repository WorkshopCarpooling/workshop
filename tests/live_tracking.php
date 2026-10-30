<?php

// Synthetic users and trips live only in SQLite memory. GPS cache uses array storage.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\DriverLocationController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\AuthController;
use App\Models\Ride;
use App\Models\RideBooking;
use App\Models\User;
use App\Services\DriverLocation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

config(['database.default'=>'sqlite', 'database.connections.sqlite.database'=>':memory:', 'cache.default'=>'array', 'session.driver'=>'array']);
$app->instance(Illuminate\Cache\RateLimiter::class,new Illuminate\Cache\RateLimiter(Illuminate\Support\Facades\Cache::store('array')));
DB::purge('sqlite');
foreach (['2026_10_21_000000_create_workshop_schema_baseline.php', '2026_10_22_000000_add_journey_and_research_features.php'] as $file) (require database_path('migrations/'.$file))->up();
Carbon::setTestNow(Carbon::parse('2030-01-07 08:00:00', 'Asia/Kuala_Lumpur'));
$checks = 0;
function verifyTracking($condition, $label) { global $checks; if (!$condition) { fwrite(STDERR, "FAIL: $label\n"); exit(1); } $checks++; echo "PASS: $label\n"; }
function rejectedTracking(callable $fn, int $status, string $label) {
    try { $fn(); verifyTracking(false, $label); }
    catch (ValidationException $error) { verifyTracking($status === 422, $label); }
    catch (Illuminate\Database\Eloquent\ModelNotFoundException $error) { verifyTracking($status === 404, $label); }
    catch (HttpExceptionInterface $error) { verifyTracking($error->getStatusCode() === $status, $label); }
}
$users = [];
foreach ([['student','driver','active'], ['student','driver','active'], ['student','passenger','active'], ['admin','passenger','active'], ['staff','driver','active'], ['student','driver','suspended']] as $i => [$role,$type,$state]) {
    $users[] = User::create(['full_name'=>'Synthetic '.$i, 'email'=>'synthetic'.$i.'@example.invalid', 'password_hash'=>'unused', 'role'=>$role, 'account_type'=>$type, 'account_status'=>$state]);
}
[$driver,$other,$passenger,$admin,$staff,$suspended] = $users;
$ride = Ride::create(['driver_id'=>$driver->user_id, 'pickup_point'=>'Synthetic start', 'pickup_latitude'=>2.31, 'pickup_longitude'=>102.31, 'destination'=>'UTeM Main Campus', 'departure_at'=>now(), 'available_seats'=>2, 'contribution_per_seat'=>2, 'vehicle_description'=>'Synthetic car', 'vehicle_plate'=>'TEST', 'ride_status'=>'scheduled']);
$controller = new DriverLocationController;
$req = function ($user, $data = [], $token = '') use ($app) {
    $request = Request::create('/synthetic', 'POST', $data, [], [], ['HTTP_ACCEPT'=>'application/json', 'HTTP_X_TRACKING_TOKEN'=>$token]);
    $request->setUserResolver(fn () => $user);
    $request->setLaravelSession(app('session')->driver());
    app()->instance('request', $request);
    Auth::forgetGuards();
    if ($user) Auth::guard()->setUser($user);
    return $request;
};
rejectedTracking(fn () => $controller->start($req($driver), $ride->ride_id), 409, 'scheduled trips cannot share GPS');
RideBooking::create(['ride_id'=>$ride->ride_id, 'passenger_id'=>$passenger->user_id, 'pickup_label'=>'Synthetic pickup', 'seats_requested'=>1, 'booking_status'=>'accepted']);
$started = (new DriverController)->handleAction($req($driver, ['action'=>'update_status', 'ride_id'=>$ride->ride_id, 'next_status'=>'in_progress', 'share_location'=>'1']));
verifyTracking($ride->fresh()->ride_status === 'in_progress' && str_ends_with($started->getTargetUrl(), '#ride-'.$ride->ride_id), 'start trip redirects to the active trip instead of the top of the page');
view()->share('errors', new Illuminate\Support\ViewErrorBag);
$driverHtml = (new DriverController)->index('schedule')->render();
verifyTracking(substr_count($driverHtml, 'id="ride-'.$ride->ride_id.'"') === 1, 'driver page with existing seat requests renders one anchor on the trip card');
verifyTracking(str_contains($driverHtml, 'Active trip GPS') && !str_contains($driverHtml, '>Share location</button>'), 'active trip starts GPS automatically without a separate share-location step');
$offerHtml=(new DriverController)->index('offer')->render();
verifyTracking(str_contains($offerHtml, 'data-journey-map') && preg_match('/<button[^>]*disabled[^>]*>Publish ride/', $offerHtml), 'map is visible before vehicle approval while publishing stays disabled');
verifyTracking(str_contains((new DriverController)->index()->render(), 'data-location-url'), 'refreshing an active trip keeps automatic GPS available');
foreach ([$other,$passenger,$admin,$staff,$suspended,null] as $user) {
    rejectedTracking(fn () => $controller->start($req($user), $ride->ride_id), $user === $other ? 404 : 403, 'only an active student driver may start sharing their own trip');
}
foreach ([$driver,$passenger,$staff,$suspended,null] as $user) rejectedTracking(fn () => $controller->feed($req($user)), 403, 'GPS feed rejects non-admin users');
$feed = $controller->feed($req($admin))->getData(true);
verifyTracking(count($feed['rides']) === 1 && $feed['rides'][0]['state'] === 'waiting' && $feed['rides'][0]['location'] === null, 'active trip has no fabricated car location before sharing');
verifyTracking($feed['rides'][0]['plannedStart']['lat'] === 2.31, 'published starting coordinates are provided separately from live GPS');
$token = $controller->start($req($driver), $ride->ride_id)->getData(true)['token'];
$fix = ['latitude'=>2.3139, 'longitude'=>102.3183, 'accuracy'=>12, 'heading'=>90, 'capturedAt'=>now()->timestamp];
rejectedTracking(fn () => $controller->update($req($driver,$fix,'wrong'),$ride->ride_id),409,'sharing token is required');
rejectedTracking(fn () => $controller->update($req($other,$fix,$token),$ride->ride_id),404,'another driver cannot upload GPS for this trip');
foreach ([['latitude'=>91], ['longitude'=>181], ['accuracy'=>-1], ['heading'=>361], ['capturedAt'=>now()->timestamp-61], ['capturedAt'=>now()->timestamp+16]] as $bad) rejectedTracking(fn () => $controller->update($req($driver,array_replace($fix,$bad),$token),$ride->ride_id),422,'invalid or out-of-date GPS is rejected');
$controller->update($req($driver,$fix,$token),$ride->ride_id);
$feed = $controller->feed($req($admin))->getData(true);
verifyTracking($feed['rides'][0]['state']==='live' && $feed['rides'][0]['location']['lat']===2.3139,'admin receives the actual uploaded GPS fix');
verifyTracking(!str_contains(json_encode($feed),$token),'admin feed never reveals sharing tokens');
verifyTracking(str_contains($controller->feed($req($admin))->headers->get('Cache-Control'),'no-store'),'GPS feed disables response caching');
rejectedTracking(fn () => $controller->update($req($driver,$fix,$token),$ride->ride_id),422,'duplicate or out-of-order fixes cannot move the car backwards');
$controller->report($req($driver,['state'=>'page_closed'],$token),$ride->ride_id);
verifyTracking(DriverLocation::get($ride->ride_id)['latitude'] == $fix['latitude'] && $controller->feed($req($admin))->getData(true)['rides'][0]['state'] === 'stale', 'leaving the driver page retains a labelled last-known car instead of erasing it');
rejectedTracking(fn () => $controller->update($req($driver,$fix,$token),$ride->ride_id),409,'a closed page cannot upload new GPS after its lease is retired');
$token = $controller->start($req($driver),$ride->ride_id)->getData(true)['token'];
$controller->report($req($driver,['state'=>'insecure_context'],$token),$ride->ride_id);
verifyTracking($controller->feed($req($admin))->getData(true)['rides'][0]['gpsState'] === 'insecure_context', 'admin can see when HTTP blocks the driver GPS');
$controller->report($req($driver,['state'=>'permission_denied'],$token),$ride->ride_id);
verifyTracking(str_contains($controller->feed($req($admin))->getData(true)['rides'][0]['gpsMessage'],'permission'), 'admin sees browser permission failures instead of a silently missing car');
rejectedTracking(fn () => $controller->report($req($other,['state'=>'page_closed'],$token),$ride->ride_id),404,'another driver cannot change a trip GPS status');
Carbon::setTestNow(now()->addSeconds(31));
verifyTracking($controller->feed($req($admin))->getData(true)['rides'][0]['state']==='stale','GPS becomes explicitly stale after 30 seconds');
$newToken = $controller->start($req($driver),$ride->ride_id)->getData(true)['token'];
verifyTracking($controller->feed($req($admin))->getData(true)['rides'][0]['location'] !== null, 'restarting tracking preserves the previous valid fix until a new fix arrives');
rejectedTracking(fn () => $controller->update($req($driver,array_replace($fix,['capturedAt'=>now()->timestamp]),$token),$ride->ride_id),409,'restarting invalidates previous browser uploads');
rejectedTracking(fn () => $controller->stop($req($driver,[],$token),$ride->ride_id),409,'a previous tab cannot stop a new sharing session');
$controller->update($req($driver,array_replace($fix,['capturedAt'=>now()->timestamp]),$newToken),$ride->ride_id);
$controller->stop($req($driver,[],$newToken),$ride->ride_id);
verifyTracking(DriverLocation::get($ride->ride_id)===null,'Stop sharing removes the latest GPS fix');
rejectedTracking(fn () => $controller->update($req($driver,array_replace($fix,['capturedAt'=>now()->timestamp]),$newToken),$ride->ride_id),409,'in-flight updates cannot restore GPS after Stop sharing');
$token = $controller->start($req($driver),$ride->ride_id)->getData(true)['token'];
$controller->update($req($driver,array_replace($fix,['capturedAt'=>now()->timestamp]),$token),$ride->ride_id);
Carbon::setTestNow(now()->addMinutes(11));
verifyTracking(DriverLocation::get($ride->ride_id)===null,'GPS cache expires after ten minutes without new updates');
$token = $controller->start($req($driver),$ride->ride_id)->getData(true)['token'];
$controller->update($req($driver,array_replace($fix,['capturedAt'=>now()->timestamp]),$token),$ride->ride_id);
if (in_array('--render', $argv, true)) {
    $directory = storage_path('app/tracking-preview');
    if (!is_dir($directory)) mkdir($directory, 0777, true);
    Illuminate\Support\Facades\URL::forceRootUrl('http://127.0.0.1:8766');
    view()->share('errors', new Illuminate\Support\ViewErrorBag);
    $req($admin);
    $html = (new App\Http\Controllers\AdminController)->index('live')->render();
    $html = str_replace('http://127.0.0.1:8766/api/admin/driver-locations', '/__review__/feed.json', $html);
    $banner = '<div style="padding:12px;background:#fff2c9;text-align:center;font:14px sans-serif">TEST PREVIEW — Synthetic data, not live tracking</div>';
    $html = str_replace('<div class="app-shell">', $banner.'<div class="app-shell">', $html);
    file_put_contents($directory.'/admin.html', $html);
    $previewFeed = $controller->feed($req($admin))->getData(true);
    $second = $previewFeed['rides'][0]; $second['id'] = 9992; $second['driver'] = 'Synthetic driver 2'; $second['plate'] = 'TEST 2'; $second['location']['lat'] += .0013; $second['location']['lng'] += .0015; $second['location']['heading'] = 140;
    $third = $previewFeed['rides'][0]; $third['id'] = 9993; $third['driver'] = 'Synthetic driver 3'; $third['plate'] = 'TEST 3'; $third['state'] = 'waiting'; $third['location'] = null; $third['plannedStart'] = ['lat'=>2.3126,'lng'=>102.3194]; $third['gpsMessage'] = 'Waiting for a device GPS fix.';
    $previewFeed['rides'][] = $second; $previewFeed['rides'][] = $third;
    file_put_contents($directory.'/feed.json', json_encode($previewFeed));
    $req($driver);
    $html = (new DriverController)->index('schedule')->render();
    $html = str_replace('<div class="app-shell">', $banner.'<div class="app-shell">', $html);
    file_put_contents($directory.'/driver.html', $html);
    echo "Synthetic admin/driver review pages rendered without database writes.\n";
}
(new DriverController)->handleAction($req($driver,['action'=>'update_status','ride_id'=>$ride->ride_id,'next_status'=>'completed']));
verifyTracking($ride->fresh()->ride_status==='completed' && DriverLocation::get($ride->ride_id)===null,'completing a trip removes its GPS fix');
verifyTracking($controller->feed($req($admin))->getData(true)['rides']===[],'completed cars disappear from the admin map');
rejectedTracking(fn () => $controller->update($req($driver,array_replace($fix,['capturedAt'=>now()->timestamp]),$token),$ride->ride_id),409,'completed trips cannot upload location');
verifyTracking(!$staff->isDriver() && !$staff->isPassenger(),'staff accounts are outside the student commuter scope');
$before = User::count();
(new AuthController)->signup($req(null,['name'=>'Synthetic staff', 'email'=>'new@example.invalid','memberType'=>'staff','accountType'=>'driver','password'=>'synthetic-password','confirmPassword'=>'synthetic-password']));
verifyTracking(User::count()===$before,'server rejects staff registration without creating an account');
// Exercise the actual route stack, including authentication and CSRF protection.
foreach ([[$admin,200],[$driver,403],[$staff,403],[null,401]] as [$user,$expected]) {
    $request = Request::create('http://localhost/api/admin/driver-locations','GET',[],[],[],['HTTP_ACCEPT'=>'application/json']);
    Auth::forgetGuards(); if ($user) Auth::guard()->setUser($user);
    $response=$kernel->handle($request);
    verifyTracking($response->getStatusCode()===$expected,'feed route enforces active admin access (HTTP '.$expected.')');
}
Auth::forgetGuards(); Auth::guard()->setUser($driver);
$request = Request::create('http://localhost/api/driver/rides/'.$ride->ride_id.'/tracking/start','POST',[],[],[],['HTTP_ACCEPT'=>'application/json']);
verifyTracking($kernel->handle($request)->getStatusCode()===419,'GPS mutations require a valid CSRF token');
Carbon::setTestNow();
echo "$checks live tracking and student-scope checks passed; SQLite memory and array cache only.\n";
