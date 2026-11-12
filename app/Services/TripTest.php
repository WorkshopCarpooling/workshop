<?php
namespace App\Services;

use App\Models\{Ride,User};
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Automatic local driving with the same order lifecycle as a GPS trip. */
final class TripTest {
    public static function enabled(): bool {
        return app()->environment(['local','testing']) && (bool)config('navigation.test_mode',false);
    }
    private static function authorize(Ride $ride,User $driver,bool $finishing=false): void {
        abort_unless(self::enabled(),404);
        abort_unless($driver->isActive()&&$driver->isDriver()&&(int)$ride->driver_id===(int)$driver->user_id,403);
        abort_unless(in_array($ride->ride_status,$finishing?['in_progress','completed']:['scheduled','in_progress'],true),409,'Choose a scheduled or active trip for this test.');
    }
    private static function key(Ride $ride): string {return 'trip-test:v1:'.$ride->driver_id.':'.$ride->ride_id;}
    private static function save(Ride $ride,array $test): void {Cache::put(self::key($ride),$test,now()->addHours(4));}
    private static function leg(array $from,array $target): array {
        $road=NavigationRoad::route([$from,$target]);
        return ['road'=>$road+['targetKey'=>$target['key'],'origin'=>['lat'=>$from['lat'],'lng'=>$from['lng']]],
            'startedAt'=>now()->getTimestampMs()/1000,'durationSeconds'=>count($road['path'])>1?max(5,min(120,(int)config('navigation.test_leg_seconds',20))):0];
    }
    public static function start(Ride $ride,User $driver): array {
        self::authorize($ride,$driver);
        return Cache::lock(self::key($ride).':lock',15)->block(5,function()use($ride,$driver){
            $test=Cache::get(self::key($ride));
            if(!$test||$test['ended']){
                $all=TripNavigation::status($ride,$driver)['stops'];
                $stops=array_values(array_filter($all,fn($s)=>in_array($s['kind'],['start','pickup','dropoff'],true)));
                if(count($stops)<2&&count($all)>1){$last=end($all);$last['kind']='dropoff';$stops[]=$last;}
                abort_unless(count($stops)>1,422,'This trip needs a starting point and a destination.');
                foreach($stops as &$stop)$stop['done']=$stop['kind']==='start';unset($stop);
                $test=['runId'=>(string)Str::uuid(),'stops'=>$stops,'index'=>1,'ended'=>false,'startedAt'=>now()->getTimestampMs()/1000,'leg'=>self::leg($stops[0],$stops[1])];
            }
            if($ride->ride_status==='scheduled')abort_unless(RideLifecycle::transition($driver,(int)$ride->ride_id,'in_progress'),409,'This trip could not be started. Refresh your rides.');
            $ride->refresh();
            self::save($ride,$test);
            return self::payload($ride,$test);
        });
    }
    public static function status(Ride $ride,User $driver): array {
        self::authorize($ride,$driver);$test=Cache::get(self::key($ride));
        return !$test||$test['ended']?['ended'=>true]:self::payload($ride,$test);
    }
    public static function advance(Ride $ride,User $driver,string $key,string $runId): array {
        self::authorize($ride,$driver);
        return Cache::lock(self::key($ride).':lock',15)->block(5,function()use($ride,$driver,$key,$runId){
            $test=Cache::get(self::key($ride));
            abort_unless($test&&!$test['ended']&&hash_equals($test['runId'],$runId),409,'This test session ended. Reopen the trip map.');
            foreach($test['stops'] as $stop)if($stop['key']===$key&&$stop['done'])return self::payload($ride,$test);
            $snapshot=self::payload($ride,$test);$next=$snapshot['navigation']['next'];
            if(!$next||$next['key']!==$key||!$snapshot['navigation']['simulation']['arrived'])
                throw ValidationException::withMessages(['stop'=>'Wait for the car to arrive at the next stop before confirming.']);
            $raw=collect(TripNavigation::stops($ride))->firstWhere('key',$key);
            if(!empty($raw['bookingIds']))TripNavigation::advance((int)$driver->user_id,(int)$ride->ride_id,$key);
            $test['stops'][$test['index']]['done']=true;$from=$test['stops'][$test['index']];$test['index']++;
            if(isset($test['stops'][$test['index']]))$test['leg']=self::leg($from,$test['stops'][$test['index']]);
            self::save($ride,$test);return self::payload($ride,$test);
        });
    }
    public static function finish(Ride $ride,User $driver,string $runId): void {
        self::authorize($ride,$driver,true);
        Cache::lock(self::key($ride).':lock',15)->block(5,function()use($ride,$driver,$runId){
            $test=Cache::get(self::key($ride));abort_unless($test&&hash_equals($test['runId'],$runId),409);
            if($test['ended']&&$ride->ride_status==='completed')return;
            if(isset($test['stops'][$test['index']]))throw ValidationException::withMessages(['stop'=>'Confirm the final drop-off before finishing this test.']);
            abort_unless(RideLifecycle::transition($driver,(int)$ride->ride_id,'completed'),409,'The trip status changed. Refresh your rides.');
            $test['ended']=true;self::save($ride,$test);
        });
    }
    public static function passengerStatus(Ride $ride,User $passenger): ?array {
        $movement=self::movement($ride);if(!$movement)return null;
        $navigation=TripNavigation::status($ride,$passenger);
        abort_if($navigation['isDriver'],403);
        $start=$navigation['stops'][0]??null;$destination=null;
        foreach($navigation['stops'] as $stop)if($stop['kind']==='dropoff')$destination=$stop;
        if(!$start||!$destination)return null;
        $booking=$ride->bookings->where('booking_status','accepted')->firstWhere('passenger_id',$passenger->user_id);
        abort_unless($booking,403);
        $start=['key'=>'journey-start','kind'=>'start','label'=>LocationLabel::short($booking->pickup_label?:$ride->pickup_point),
            'lat'=>$booking->pickup_latitude??$start['lat'],'lng'=>$booking->pickup_longitude??$start['lng'],'done'=>true];
        // All viewers follow the driver's current leg and clock. Only the driver's UI has stop controls.
        $destination['key']=$movement['route']['targetKey'];
        $navigation['stops']=[$start,$destination];$navigation['next']=$destination;
        $navigation['passengerJourney']=true;$navigation['continuousAnimation']=true;
        $navigation['gps']='simulated';$navigation['gpsState']='simulated';
        $navigation['serverNow']=$movement['serverNow'];
        $navigation['location']=$movement['location'];
        $navigation['simulation']=$movement['simulation'];
        $navigation['allDroppedOff']=$destination['done'];
        return ['navigation'=>$navigation,'route'=>$movement['route']];
    }
    public static function movement(Ride $ride): ?array {
        if(!self::enabled()||$ride->ride_status!=='in_progress')return null;
        $test=Cache::get(self::key($ride));
        if(!$test||$test['ended'])return null;
        $payload=self::payload($ride,$test);$navigation=$payload['navigation'];
        $simulation=$navigation['simulation'];unset($simulation['runId']);
        $simulation['destinationReached']=$navigation['allDroppedOff'];
        $route=$payload['route'];$route['targetKey']='shared-leg-'.$test['index'];
        return ['serverNow'=>$navigation['serverNow'],'location'=>$navigation['location'],
            'simulation'=>$simulation,'route'=>$route];
    }
    private static function payload(Ride $ride,array $test): array {
        $leg=$test['leg'];$next=$test['stops'][$test['index']]??null;
        $elapsed=max(0,now()->getTimestampMs()/1000-$leg['startedAt']);
        $fraction=$leg['durationSeconds']?min(1,$elapsed/$leg['durationSeconds']):1;
        $point=self::point($leg['road']['path'],$fraction);
        return ['navigation'=>['rideId'=>$ride->ride_id,'status'=>'in_progress','isDriver'=>true,'testTrip'=>true,
            'stops'=>$test['stops'],'next'=>$next,'gps'=>'simulated','gpsState'=>'simulated','serverNow'=>now()->getTimestampMs()/1000,
            'location'=>$point+['capturedAt'=>now()->getTimestampMs()/1000],'onBoard'=>false,'allDroppedOff'=>$next===null,
            'simulation'=>['runId'=>$test['runId'],'startedAt'=>$leg['startedAt'],'durationSeconds'=>$leg['durationSeconds'],'arrived'=>$fraction>=1]],
            'route'=>$leg['road']];
    }
    private static function point(array $path,float $fraction): array {
        $lengths=[];$total=0;
        for($i=1;$i<count($path);$i++){$lengths[$i]=Journey::km($path[$i-1]['lat'],$path[$i-1]['lng'],$path[$i]['lat'],$path[$i]['lng']);$total+=$lengths[$i];}
        $remaining=$total*$fraction;
        for($i=1;$i<count($path);$i++){
            if($remaining<=$lengths[$i]||$i===count($path)-1){$t=$lengths[$i]?$remaining/$lengths[$i]:1;$a=$path[$i-1];$b=$path[$i];return ['lat'=>$a['lat']+($b['lat']-$a['lat'])*$t,'lng'=>$a['lng']+($b['lng']-$a['lng'])*$t];}
            $remaining-=$lengths[$i];
        }
        return $path[0];
    }
}
