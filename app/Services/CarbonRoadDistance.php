<?php
namespace App\Services;

use App\Http\Controllers\RoutesController;
use App\Models\Ride;
use Illuminate\Http\Request;

final class CarbonRoadDistance
{
    public static function estimate(Ride $ride,array $points,array $endpoints,bool $allowNetwork): ?array
    {
        $ordered=[];
        foreach($points as $point) {
            $last=end($ordered);
            if(!$last||Journey::km($last['lat'],$last['lng'],$point['lat'],$point['lng'])>.001)$ordered[]=$point;
        }
        if(count($ordered)<2)return null;
        if(count($ordered)===2) foreach($ride->bookings->where('booking_status','accepted') as $booking) {
            if($booking->distance_source!=='google_routes_api'||$booking->journey_distance_km<=0||$booking->journey_distance_km>500)continue;
            if($booking->pickup_latitude===null||$booking->pickup_longitude===null||$booking->dropoff_latitude===null||$booking->dropoff_longitude===null)continue;
            if(Journey::km($booking->pickup_latitude,$booking->pickup_longitude,$endpoints[0]['lat'],$endpoints[0]['lng'])>.005)continue;
            if(Journey::km($booking->dropoff_latitude,$booking->dropoff_longitude,$endpoints[1]['lat'],$endpoints[1]['lng'])>.005)continue;
            return ['shared_km'=>$booking->journey_distance_km,'driver_baseline_km'=>$booking->journey_distance_km,'method'=>'Saved road-route fare distance; pickup and drop-off match the entire shared journey. Not measured GPS.'];
        }
        if(!$allowNetwork||!config('carbon.road_lookup_enabled',true)||!config('services.google_maps.api_key'))return null;
        $route=function(array $points): ?float {
            $first=$points[0];$last=end($points);
            $result=app(RoutesController::class)->computeRoute(new Request(['origin_lat'=>$first['lat'],'origin_lng'=>$first['lng'],
                'dest_lat'=>$last['lat'],'dest_lng'=>$last['lng'],'intermediates'=>array_slice($points,1,-1)]))->getData(true);
            $km=($result['distanceMeters']??0)/1000;
            return ($result['source']??'')==='google_routes_api'&&is_finite($km)&&$km>0&&$km<=500?$km:null;
        };
        try {
            $shared=$route($ordered);
            if($shared===null)return null;
            $original=count($ordered)===2?$shared:$route($endpoints);
            if($original===null)return null;
            return ['shared_km'=>round($shared,3),'driver_baseline_km'=>round($original,3),
                'method'=>'Road-route estimate for the planned stop order, including pickup detours. Not recorded GPS or measured distance.'];
        } catch(\Throwable $error) { return null; }
    }
}
