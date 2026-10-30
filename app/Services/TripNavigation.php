<?php
namespace App\Services;
use App\Models\{Ride,User};
use Illuminate\Support\Facades\{DB,Schema};
use Illuminate\Validation\ValidationException;

final class TripNavigation {
    public static function ready(): bool {return Schema::hasColumn('rides','navigation_plan')&&Schema::hasColumns('ride_bookings',['picked_up_at','dropped_off_at']);}
    public static function stops(Ride $ride): array {
        if($ride->navigation_plan)return $ride->navigation_plan;
        $ride->loadMissing('bookings');$planner=new RoutePlanner;$plan=$planner->plan($ride);$endpoints=$planner->endpoints($ride);
        if(!$endpoints)return [];
        $accepted=$ride->bookings->where('booking_status','accepted');$result=[];
        if($plan)foreach($plan['points'] as $i=>$p){
            $kind=$p['kind']??(isset($p['bookingId'])?'pickup':($i===0?'start':'end'));
            $ids=isset($p['bookingId'])?[(int)$p['bookingId']]:[];
            if(SharedRidePool::isPool($ride)&&$i===count($plan['points'])-1){$ids=$accepted->pluck('booking_id')->map(fn($id)=>(int)$id)->values()->all();$kind=$ids?'dropoff':'end';}
            $result[]=['key'=>$kind.'-'.($ids?implode('-',$ids):$i),'kind'=>$kind,'lat'=>(float)$p['lat'],'lng'=>(float)$p['lng'],'label'=>$p['label'],'bookingIds'=>$ids];
        }
        if(!$result){
            $result[]=$endpoints[0]+['key'=>'start','kind'=>'start','bookingIds'=>[]];
            foreach($accepted as $b)foreach(['pickup','dropoff'] as $kind){$fallback=$endpoints[$kind==='pickup'?0:1];$result[]=['key'=>$kind.'-'.$b->booking_id,'kind'=>$kind,'lat'=>$b->{$kind.'_latitude'}??$fallback['lat'],'lng'=>$b->{$kind.'_longitude'}??$fallback['lng'],'label'=>($kind==='pickup'?$b->pickup_label:$b->dropoff_label)?:$fallback['label'],'bookingIds'=>[(int)$b->booking_id]];}
            $result[]=$endpoints[1]+['key'=>'end','kind'=>'end','bookingIds'=>[]];
        }
        return $result;
    }
    public static function status(Ride $ride,User $user): array {
        $ride->loadMissing('bookings');$isDriver=(int)$ride->driver_id===(int)$user->user_id;$bookings=$ride->bookings->where('booking_status','accepted');
        $mine=$bookings->where('passenger_id',$user->user_id);$stops=self::stops($ride);$visible=[];$next=null;
        foreach($stops as $stop){
            $ids=$stop['bookingIds'];$related=$bookings->whereIn('booking_id',$ids);
            if($ids&&$related->isEmpty())continue;
            if(!$isDriver&&$ids&&!$mine->whereIn('booking_id',$ids)->count())continue;
            $done=$stop['kind']==='start'||($ids&&$related->every(fn($b)=>$stop['kind']==='pickup'?$b->picked_up_at!==null:$b->dropped_off_at!==null));
            if($ride->ride_status==='completed')$done=true;
            // A passenger sees their own pins only; shared strangers' names and pickup pins stay private.
            if(!$isDriver&&$stop['kind']==='end')continue;
            if(!$isDriver&&$ids){$own=$mine->whereIn('booking_id',$ids)->first();$stop['label']=$stop['kind']==='pickup'?$own->pickup_label:($own->dropoff_label?:$ride->destination);$stop['lat']=$own->{$stop['kind'].'_latitude'}??$stop['lat'];$stop['lng']=$own->{$stop['kind'].'_longitude'}??$stop['lng'];}
            unset($stop['bookingIds']);$stop['done']=$done;$visible[]=$stop;
            if(!$done&&$stop['kind']!=='start'&&!$next)$next=$stop;
        }
        $fix=$ride->ride_status==='in_progress'?DriverLocation::get($ride->ride_id):null;$location=null;$gps='waiting';
        if(isset($fix['latitude'],$fix['longitude'],$fix['capturedAt'])){
            $age=max(0,now()->timestamp-(float)$fix['capturedAt']);
            if($age<=600){$location=['lat'=>(float)$fix['latitude'],'lng'=>(float)$fix['longitude'],'accuracy'=>(float)($fix['accuracy']??0),'capturedAt'=>(float)$fix['capturedAt'],'heading'=>$fix['heading']??null];$gps=$age<=30&&($fix['sharing']??true)&&($fix['gpsState']??'live')==='live'?'live':'stale';}
        }
        return ['rideId'=>$ride->ride_id,'status'=>$ride->ride_status,'isDriver'=>$isDriver,'stops'=>$visible,'next'=>$next,'gps'=>$gps,'location'=>$location,'serverNow'=>now()->timestamp,
            'onBoard'=>!$isDriver&&$mine->contains(fn($b)=>$b->picked_up_at&&!$b->dropped_off_at),'allDroppedOff'=>$bookings->isEmpty()||$bookings->every(fn($b)=>$b->dropped_off_at!==null)];
    }
    public static function advance(int $driver,int $id,string $key): void {
        abort_unless(self::ready(),503);
        DB::transaction(function()use($driver,$id,$key){
            $user=User::whereKey($driver)->lockForUpdate()->firstOrFail();abort_unless($user->isDriver()&&$user->isActive(),403);
            $ride=Ride::whereKey($id)->where('driver_id',$driver)->lockForUpdate()->firstOrFail();abort_unless($ride->ride_status==='in_progress',409,'Start the trip before confirming a stop.');
            $ride->setRelation('bookings',$ride->bookings()->orderBy('booking_id')->lockForUpdate()->get());
            $next=self::status($ride,$user)['next'];
            // A retry of the same completed stop succeeds without advancing a second passenger.
            $stop=collect(self::status($ride,$user)['stops'])->firstWhere('key',$key);
            if($stop&&$stop['done'])return;
            if(!$next||$next['key']!==$key||!in_array($next['kind'],['pickup','dropoff'],true))throw ValidationException::withMessages(['stop'=>'The next stop changed. Refresh the journey before confirming.']);
            $raw=collect(self::stops($ride))->firstWhere('key',$key);$column=$next['kind']==='pickup'?'picked_up_at':'dropped_off_at';
            $ride->bookings()->whereIn('booking_id',$raw['bookingIds'])->where('booking_status','accepted')->whereNull($column)->update([$column=>now(),'updated_at'=>now()]);
        },3);
    }
}
