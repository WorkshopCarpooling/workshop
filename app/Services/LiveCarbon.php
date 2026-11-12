<?php

namespace App\Services;

use App\Models\Ride;

final class LiveCarbon
{
    /** Read-only intensity from the fuel profile and confirmed pickups and drop-offs. */
    private static function intensity(Ride $ride): array
    {
        $ride->loadMissing(['vehicle','bookings']);
        $seats = (int) $ride->bookings
            ->where('booking_status', 'accepted')
            ->whereNotNull('picked_up_at')
            ->whereNull('dropped_off_at')
            ->sum('seats_requested');
        $occupants=1+max(0,$seats);
        $low=(float)config('carbon.live_map.low_max_g_per_participant_km',60);
        $medium=(float)config('carbon.live_map.medium_max_g_per_participant_km',100);
        if(!is_finite($low)||!is_finite($medium)||$low<=0||$medium<$low){$low=60;$medium=100;}
        $result=['status'=>'unavailable','reason'=>'Save a supported fuel type and average consumption in My vehicles.',
            'passenger_seats'=>max(0,$seats),'occupants'=>$occupants,'level'=>'unknown',
            'vehicle_g_per_km'=>null,'participant_g_per_km'=>null,'planned'=>null,
            'allocation_method'=>'equal-onboard-participant-v1',
            'thresholds'=>['low_max'=>$low,'medium_max'=>$medium,'basis'=>'local_pilot_assumption'],
            'method'=>config('carbon.method'),'source'=>config('carbon.source_title'),'source_url'=>config('carbon.source_url')];
        $vehicle=$ride->vehicle;
        $consumption=(float)($vehicle->fuel_l_per_100km??0);
        $fuel=$vehicle->fuel_type??'';
        $factor=(float)config('carbon.fuels.'.$fuel.'.factor',0);
        if(!$vehicle||!is_finite($consumption)||$consumption<=0||$consumption>50||!is_finite($factor)||$factor<=0||$seats<0)return $result;
        $vehicleRate=$consumption/100*$factor*1000;
        $rate=$vehicleRate/$occupants;
        return array_replace($result,['status'=>'estimated','reason'=>null,'fuel_type'=>$fuel,
            'fuel_label'=>config('carbon.fuels.'.$fuel.'.label',$fuel),'litres_per_100km'=>$consumption,'fuel_factor'=>$factor,
            'vehicle_g_per_km'=>round($vehicleRate,6),'participant_g_per_km'=>round($rate,6),
            'level'=>$rate<=$low?'low':($rate<=$medium?'medium':'high')]);
    }

    public static function marker(Ride $ride): array
    {
        return array_intersect_key(self::intensity($ride), array_flip(['level','participant_g_per_km']));
    }

    /** Read-only live estimate. No network requests or saved reports. */
    public static function estimate(Ride $ride): array
    {
        $result=self::intensity($ride);
        if($result['status']!=='estimated')return $result;
        // Reuse the completed-trip distance method without completing or saving the ride.
        // Read-only inputs never query the road provider or apply remembered travel choices.
        $planned=clone $ride;$planned->ride_status='completed';
        try {$inputs=AutomaticCarbon::inputs($planned);}
        catch(\Throwable $error){$inputs=null;} // A missing route must not interrupt device GPS tracking.
        if($inputs) {
            $km=(float)$inputs['shared_km'];
            $litres=$km*$result['litres_per_100km']/100;$kg=$litres*$result['fuel_factor'];
            $bookedOccupants = 1 + (int) $ride->bookings->where('booking_status', 'accepted')->sum('seats_requested');
            $result['planned']=['distance_km'=>$km,'fuel_litres'=>round($litres,6),'vehicle_kg'=>round($kg,6),
                'participant_kg'=>round($kg/max(1,$bookedOccupants),6),'distance_basis'=>$inputs['distance_basis'],'distance_method'=>$inputs['distance_method']];
        }
        $result['daily']=CarbonRewards::daily($ride,$result['planned']['vehicle_kg']??null);
        return $result;
    }
}
