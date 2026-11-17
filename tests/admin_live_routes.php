<?php
// Isolated route-access checks: SQLite memory, fake road responses, array cache.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\{User, Ride};
use App\Http\Controllers\DriverLocationController;
use Illuminate\Support\Facades\{DB, Http, Cache};
use Illuminate\Http\Request;
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','cache.default'=>'array', 'services.google_maps.api_key'=>'','navigation.osrm_enabled'=>true]);
DB::purge('sqlite');
foreach (['2026_10_21_000000_create_workshop_schema_baseline.php','2026_10_22_000000_add_journey_and_research_features.php','2026_10_30_010000_add_trip_navigation.php'] as $file) (require database_path('migrations/'.$file))->up();
$checks = 0;
$check = function ($value, $message) use (&$checks) { if (!$value) throw new RuntimeException($message); $checks++; echo 'PASS: '.$message.PHP_EOL; };
$reject = function ($call, $status, $message) use ($check) {
    try { $call(); throw new RuntimeException('Expected rejection: '.$message); }
    catch (Illuminate\Database\Eloquent\ModelNotFoundException $e) { $check($status === 404, $message); }
    catch (Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) { $check($e->getStatusCode() === $status, $message); }
};
$driver = User::create(['full_name'=>'Synthetic driver','email'=>'driver@example.invalid','password_hash'=>'unused','role'=>'student','account_type'=>'driver','account_status'=>'active']);
$admin = User::create(['full_name'=>'Synthetic admin','email'=>'admin@example.invalid','password_hash'=>'unused','role'=>'admin','account_type'=>'passenger','account_status'=>'active']);
$plan = [
    ['lat'=>2.31,'lng'=>102.31,'label'=>'Private passenger pickup','bookingIds'=>[99],'kind'=>'start','key'=>'start'],
    ['lat'=>2.32,'lng'=>102.32,'label'=>'Private passenger destination','bookingIds'=>[99],'kind'=>'end','key'=>'end'],
];
$ride = Ride::create(['driver_id'=>$driver->user_id,'pickup_point'=>'Start','destination'=>'End','departure_at'=>now(),'available_seats'=>2,'contribution_per_seat'=>2,'vehicle_description'=>'Synthetic car','vehicle_plate'=>'DEMO 001','ride_status'=>'in_progress','navigation_plan'=>$plan]);
$call = function ($user, $id = null) use ($ride) {
    $request = Request::create('/api/admin/rides/'.($id ?? $ride->ride_id).'/route');
    $request->setUserResolver(fn() => $user);
    return (new DriverLocationController)->route($request, $id ?? $ride->ride_id);
};
Http::fake(['*'=>Http::response(['code'=>'Ok','routes'=>[['distance'=>1200,'duration'=>180,'geometry'=>['coordinates'=>[[102.31,2.31],[102.315,2.31],[102.32,2.32]]]]]])]);
$response = $call($admin);
$data = $response->getData(true);
$check($data['route']['road'] && count($data['route']['path']) === 3, 'active admin receives the selected active trip’s road geometry');
$check(!str_contains(json_encode($data),'Private passenger') && !isset($data['route']['bookingIds']), 'route response omits passenger labels and booking identifiers');
$check(str_contains($response->headers->get('Cache-Control'), 'no-store'), 'admin route response prevents browser caching');
$reject(fn() => $call($driver), 403, 'driver accounts cannot use the admin route endpoint');
$reject(fn() => $call(null), 403, 'anonymous requests cannot read admin routes');
$reject(fn() => $call($admin, 99999), 404, 'missing trips do not expose route geometry');
$ride->update(['ride_status'=>'completed']);
$reject(fn() => $call($admin), 404, 'completed trips cannot be tracked through the route endpoint');
$ride->update(['ride_status'=>'in_progress']);
$driver->update(['account_status'=>'suspended']);
$reject(fn() => $call($admin), 404, 'trips from suspended drivers are excluded');
$driver->update(['account_status'=>'active']);
$admin->update(['account_status'=>'suspended']);
$reject(fn() => $call($admin), 403, 'suspended administrators cannot read route geometry');
$admin->update(['account_status'=>'active']);
config(['navigation.osrm_enabled'=>false]); Cache::flush();
$check($call($admin)->getData(true)['route']['road'] === false, 'missing road routing returns an explicitly approximate route');
$matched = app('router')->getRoutes()->match(Request::create('/api/admin/rides/'.$ride->ride_id.'/route'));
$check(str_ends_with($matched->getActionName(),'DriverLocationController@route') && in_array('auth', $matched->gatherMiddleware()), 'the new GET endpoint is registered behind authentication');
echo $checks." admin route access and fallback checks passed.\n";
