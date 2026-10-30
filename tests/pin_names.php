<?php
// All records are in SQLite memory and every geocoder request is mocked.
require __DIR__.'/ride_appointments.php';

use App\Http\Controllers\{LocationController,RideAppointmentController};
use App\Models\RideRequest;
use App\Services\OnDemandRides;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,Cache,Http};

$controller=new LocationController;
$reverse=fn($lat,$lng)=>$controller->reverse(Request::create('/api/locations/reverse','GET',['lat'=>$lat,'lng'=>$lng]));
Http::preventStrayRequests();Cache::flush();
config(['services.google_maps.api_key'=>'']);
$photon=fn(array $properties)=>Http::response(['features'=>[['properties'=>$properties]]]);
Http::fake(['photon.komoot.io/*'=>$photon(['name'=>'Campus library','street'=>'Jalan Ilmu','city'=>'Melaka','country'=>'Malaysia'])]);
$resolved=$reverse(2.31,102.31)->getData(true);
checkNow($resolved['label']==='Campus library, Jalan Ilmu, Melaka, Malaysia','a map pin resolves to a place/address instead of a coordinate label');
Http::assertSent(fn($request)=>str_contains($request->url(),'/reverse')&&(float)$request['lat']===2.31&&(float)$request['lon']===102.31);
$reverse(2.31,102.31);Http::assertSentCount(1);
checkNow(true,'pin lookup preserves the exact coordinates and caches repeated lookups');
config(['services.google_maps.api_key'=>'synthetic-key']);Cache::flush();
Http::swap(new Illuminate\Http\Client\Factory);Http::preventStrayRequests();
Http::fake(['maps.googleapis.com/*'=>Http::response(['results'=>[['formatted_address'=>'UTeM Main Campus, Durian Tunggal']]])]);
checkNow($reverse(2.32,102.32)->getData(true)['label']==='UTeM Main Campus, Durian Tunggal','configured Google reverse geocoding returns a readable address');
Cache::flush();Http::swap(new Illuminate\Http\Client\Factory);Http::preventStrayRequests();Http::fake(['maps.googleapis.com/*'=>Http::response(['status'=>'REQUEST_DENIED']),'photon.komoot.io/*'=>$photon(['name'=>'Mydin MITC','city'=>'Ayer Keroh'])]);
checkNow($reverse(2.33,102.33)->getData(true)['label']==='Mydin MITC, Ayer Keroh','a denied Google key falls back to the existing OpenStreetMap provider');
Cache::flush();config(['services.google_maps.api_key'=>'']);Http::swap(new Illuminate\Http\Client\Factory);Http::preventStrayRequests();Http::fake(['*'=>Http::response(['features'=>[]])]);
checkNow($reverse(2.34,102.34)->getStatusCode()===503,'an unknown pin never invents a place name');
foreach([[91,102],[2,181]] as [$lat,$lng]) rejectedNow(fn()=>$reverse($lat,$lng),'invalid pin coordinates are rejected');
$route=app('router')->getRoutes()->match(Request::create('/api/locations/reverse'));
checkNow(in_array('auth',$route->gatherMiddleware())&&in_array('throttle:60,1',$route->gatherMiddleware()),'reverse lookup is authenticated and rate limited');
Auth::guard()->setUser($users[0]);RideRequest::query()->update(['request_status'=>'cancelled']);
$cancelled=RideRequest::firstOrFail()->replicate();$cancelled->passenger_id=$users[0]->user_id;$cancelled->scheduled_for=null;$cancelled->ride_id=null;$cancelled->driver_id=null;$cancelled->request_status='cancelled';$cancelled->save();
checkNow(OnDemandRides::latest($users[0]->user_id)===null&&RideRequest::find($cancelled->request_id)!==null,'cancelled searches stay saved but are absent from Find Ride on reload');
$cancelled->scheduled_for=now()->addDay();$cancelled->save();
$appointments=(new RideAppointmentController)->status()->getData(true)['appointments'];
checkNow(!collect($appointments)->contains('id',$cancelled->request_id),'cancelled appointments no longer appear in the passenger Schedule list');
echo "$checks total ride, cancellation and pin-name checks passed; no real location/network used.\n";
