<?php
namespace App\Services;

use App\Models\{Ride,RideRequest};
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class SharedRidePool
{
    public static function ready(): bool { return Schema::hasColumn('rides','pool_seat_capacity'); }
    public static function isPool(Ride $ride): bool { return $ride->ride_mode==='now' && $ride->pool_seat_capacity!==null; }
    public static function current(int $driver): ?Ride {
        if(!self::ready())return null;
        return Ride::where('driver_id',$driver)->where('ride_mode','now')->where('ride_status','scheduled')
            ->whereNotNull('pool_seat_capacity')->orderBy('ride_id')->first();
    }
    public static function compatible(Ride $ride,RideRequest $request): bool {
        // One common pinned drop-off; matching names alone cannot join unrelated places.
        return self::isPool($ride)&&$ride->ride_status==='scheduled'&&!$request->scheduled_for
            &&$ride->available_seats>=$request->seats_requested
            &&$ride->dropoff_latitude!==null&&$ride->dropoff_longitude!==null
            &&Journey::km($ride->dropoff_latitude,$ride->dropoff_longitude,$request->dropoff_latitude,$request->dropoff_longitude)<=.05;
    }
    public static function setup(array $options,int $seats): array {
        $area=CampusDriverArea::settings();
        if(($options['pool_origin_latitude']??'')===''&&($options['pool_origin_longitude']??'')===''){
            $options['pool_origin_latitude']=$area['lat'];$options['pool_origin_longitude']=$area['lng'];
        }
        $data=validator($options,[
            'pool_origin_latitude'=>'required|numeric|between:-90,90',
            'pool_origin_longitude'=>'required|numeric|between:-180,180',
        ])->validate();
        $capacity=(int)config('carpool.instant_passenger_capacity',3);
        $data['pool_seat_capacity']=$capacity>=1&&$capacity<=6?$capacity:3;
        if(!CampusDriverArea::contains((float)$data['pool_origin_latitude'],(float)$data['pool_origin_longitude']))
            throw ValidationException::withMessages(['pool_origin_latitude'=>'Choose a driver starting point within the UTeM area ('.CampusDriverArea::settings()['radius_km'].' km from Main Campus).']);
        if((int)$data['pool_seat_capacity']<$seats)throw ValidationException::withMessages(['pool_seat_capacity'=>'This request needs more passenger seats than your car has available.']);
        return $data;
    }
    public static function plan(Ride $ride): ?array {
        if(!self::isPool($ride)||$ride->pool_origin_latitude===null||$ride->pool_origin_longitude===null)return null;
        $origin=['lat'=>(float)$ride->pool_origin_latitude,'lng'=>(float)$ride->pool_origin_longitude,'label'=>'Driver starting point'];
        $remaining=$ride->bookings->where('booking_status','accepted')->sortBy('booking_id')->values()->all();
        $points=[$origin];$previous=$origin;
        while($remaining) {
            $best=null;$km=INF;
            foreach($remaining as $i=>$booking) {
                if($booking->pickup_latitude===null||$booking->pickup_longitude===null)return null;
                $distance=Journey::km($previous['lat'],$previous['lng'],$booking->pickup_latitude,$booking->pickup_longitude);
                if($distance<$km){$km=$distance;$best=$i;}
            }
            $booking=$remaining[$best];unset($remaining[$best]);
            $points[]=$previous=['lat'=>$booking->pickup_latitude,'lng'=>$booking->pickup_longitude,
                'label'=>'Pickup: '.$booking->pickup_label,'bookingId'=>$booking->booking_id,
                'passenger'=>$booking->passenger?->full_name,'seats'=>$booking->seats_requested];
        }
        $points[]=['lat'=>(float)$ride->dropoff_latitude,'lng'=>(float)$ride->dropoff_longitude,'label'=>'Drop-off: '.$ride->destination];
        $minutes=0;
        foreach($points as $i=>&$point){if($i)$minutes+=Journey::minutes($points[$i-1],$point)+($i<count($points)-1?2:0);$point['elapsedMinutes']=round($minutes,1);}unset($point);
        $planner=new RoutePlanner;$distance=$planner->distance($points);
        return ['points'=>$points,'distanceKm'=>round($distance,2),'durationMinutes'=>round($minutes,1),
            'extraKm'=>max(0,$distance-$planner->distance([$origin,end($points)])),'baseKm'=>$planner->distance([$origin,end($points)]),
            'baselineKm'=>round($distance,2),'source'=>'nearest_pickup','algorithm'=>'nearest_pickup','optimal'=>false,
            'expandedStates'=>0,'fallbackReason'=>null,'distanceBasis'=>'straight_line_times_1_28'];
    }
    public static function passengerSummary(RideRequest $request): ?array {
        $ride=$request->ride;
        if(!$ride||!self::isPool($ride))return null;
        $ride->loadMissing('bookings');$plan=self::plan($ride);$position=null;$index=0;
        foreach($plan['points']??[] as $point)if(isset($point['bookingId'])){
            $index++;$booking=$ride->bookings->firstWhere('booking_id',$point['bookingId']);
            if((int)$booking?->passenger_id===(int)$request->passenger_id)$position=$index;
        }
        return ['capacity'=>(int)$ride->pool_seat_capacity,'remaining'=>$ride->available_seats,
            'passengers'=>(int)$ride->bookings->where('booking_status','accepted')->sum('seats_requested'),
            'pickupPosition'=>$position,'open'=>$ride->ride_status==='scheduled'&&$ride->available_seats>0];
    }
}
