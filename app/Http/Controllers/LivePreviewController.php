<?php
namespace App\Http\Controllers;

use App\Models\Ride;
use App\Services\{NavigationRoad, TripNavigation};
use Illuminate\Http\Request;

class LivePreviewController extends Controller {
    private function authorizeAdmin(Request $request): void {
        abort_unless($request->user()?->isAdmin() && $request->user()->isActive(), 403);
    }
    public function show(Request $request, ?int $ride = null) {
        $this->authorizeAdmin($request);
        if ($ride !== null) Ride::findOrFail($ride);
        $rides=Ride::where('ride_status','in_progress')->orderBy('ride_id')->get();
        return response()->view('live-preview', ['selectedRide'=>$ride,'rides'=>$rides])
            ->header('Cache-Control','no-store, private');
    }
    public function status(Request $request, ?int $ride = null) {
        $this->authorizeAdmin($request);
        $feed=(new DriverLocationController)->feed($request)->getData(true);
        $current=$ride===null?($feed['rides'][0]??null):collect($feed['rides'])->firstWhere('id',$ride);
        if (!$current) {
            $known=$ride===null?null:Ride::findOrFail($ride);
            $payload=['ride'=>null,'ended'=>$known&&in_array($known->ride_status,['completed','cancelled'],true),
                'serverNow'=>now()->getTimestampMs()/1000];
        } else {
            $road=$current['simulationRoute'];
            if (!$road) {
                $owned=Ride::with('bookings')->findOrFail($current['id']);
                $road=NavigationRoad::route(TripNavigation::stops($owned));
            }
            // This is an admin observation page. It never signs in as another role or supplies stop actions.
            $payload=['ended'=>false,'serverNow'=>$current['serverNow'],'ride'=>[
                'id'=>$current['id'],'origin'=>$current['origin'],'destination'=>$current['destination'],
                'source'=>$current['source'],'state'=>$current['state'],'location'=>$current['location'],
                'simulation'=>$current['simulation'],'route'=>$road,
                'carbon'=>array_intersect_key($current['carbon'],array_flip(['level','participant_g_per_km']))]];
        }
        return response()->json($payload)->header('Cache-Control','no-store, private');
    }
}
