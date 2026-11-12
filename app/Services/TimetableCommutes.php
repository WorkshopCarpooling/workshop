<?php

namespace App\Services;

use App\Models\{RideRequest, TimetableRidePlan, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{DB, Schema};

class TimetableCommutes
{
    public static function ready(): bool
    {
        return RideAppointments::ready() && Schema::hasTable('timetable_ride_plans')
            && Schema::hasColumn('ride_requests','timetable_entry_id');
    }

    public static function nextSession(object $session, string $startsOn, string $endsOn, int $travel, int $buffer, ?CarbonImmutable $at=null): ?array
    {
        $now=$at??now(config('app.timezone'))->toImmutable();
        $date=$now->startOfDay()->max(CarbonImmutable::parse($startsOn,config('app.timezone'))->startOfDay());
        $date=$date->addDays(((int)$session->weekday-$date->dayOfWeek+7)%7)->setTimeFromTimeString($session->starts_at);
        $end=CarbonImmutable::parse($endsOn,config('app.timezone'))->endOfDay();
        while($date<=$end) {
            $search=$date->subMinutes($travel+$buffer+10);
            if($search>$now) return ['classStartsAt'=>$date->format('Y-m-d H:i:s'),
                'scheduledFor'=>$search->format('Y-m-d H:i:s'),'arriveBy'=>$date->subMinutes($buffer)->format('Y-m-d H:i:s'),
                'travelMinutes'=>$travel,'arrivalBuffer'=>$buffer,'driverAllowance'=>10];
            $date=$date->addWeek();
        }
        return null;
    }

    private static function journey(array $inputs, array $timing, object $session, ?int $plan=null): array
    {
        $journey=array_intersect_key($inputs,array_flip(['pickup_label','pickup_latitude','pickup_longitude','dropoff_label','dropoff_latitude','dropoff_longitude','seats_requested']));
        return $journey+['scheduled_for'=>$timing['scheduledFor'],'timetable_plan_id'=>$plan,
            'timetable_entry_id'=>(int)$session->id,'timetable_session_title'=>$session->title,'timetable_session_at'=>$timing['classStartsAt']];
    }

    private static function fare(array $quote): array
    {
        return ['fare_per_seat'=>$quote['fare']['perSeat'],'journey_distance_km'=>$quote['fare']['distanceKm'],
            'distance_source'=>$quote['source'],'fare_policy'=>$quote['fare']['policy']];
    }

    public static function confirm(int $user, int $sessionId, array $inputs, string $token): RideRequest
    {
        return DB::transaction(function()use($user,$sessionId,$inputs,$token) {
            $owner=User::whereKey($user)->lockForUpdate()->firstOrFail();
            abort_unless($owner->isPassenger()&&$owner->isActive(),403);
            $session=DB::table('commute_timetable')->where('id',$sessionId)->where('user_id',$user)->lockForUpdate()->first();
            abort_unless($session,404);
            $quote=TimetableRideQuote::verify($token,$user,$session,$inputs);
            $planId=null;
            if($inputs['booking_mode']==='weekly') {
                // Retain the old settings and appointment history when replacing a weekly plan.
                $old=TimetableRidePlan::where('timetable_id',$sessionId)->where('user_id',$user)->lockForUpdate()->first();
                if($old) {
                    $same=$old->is_enabled && $old->starts_on->format('Y-m-d')===$inputs['starts_on']
                        &&$old->ends_on->format('Y-m-d')===$inputs['ends_on']
                        &&$old->arrival_buffer_minutes===$inputs['arrival_buffer_minutes']&&$old->seats_requested===$inputs['seats_requested'];
                    foreach(['pickup_label','pickup_latitude','pickup_longitude','dropoff_label','dropoff_latitude','dropoff_longitude'] as $field) $same=$same&&(string)$old->$field===(string)$inputs[$field];
                    $same=$same&&$old->fare_per_seat===$quote['fare']['perSeat']&&$old->travel_minutes===$quote['timing']['travelMinutes'];
                    if($same) {
                        $existing=RideRequest::where('passenger_id',$user)->where('scheduled_for',$quote['timing']['scheduledFor'])
                            ->whereIn('request_status',['scheduled','pending','accepted'])->first();
                        if($existing&&in_array($existing->request_status,['scheduled','pending','accepted'],true)) return $existing;
                    }
                    RideRequest::where('timetable_plan_id',$old->id)->whereIn('request_status',['scheduled','paused'])
                        ->where('scheduled_for','>',now())->update(['request_status'=>'cancelled','updated_at'=>now()]);
                    $old->update(['timetable_id'=>null,'is_enabled'=>false]);
                }
                $plan=TimetableRidePlan::create(array_intersect_key($inputs,array_flip([
                    'pickup_label','pickup_latitude','pickup_longitude','dropoff_label','dropoff_latitude','dropoff_longitude',
                    'seats_requested','arrival_buffer_minutes','starts_on','ends_on',
                ]))+self::fare($quote)+['user_id'=>$user,'timetable_id'=>$sessionId,'is_enabled'=>true,
                    'travel_minutes'=>$quote['timing']['travelMinutes'],'driver_allowance_minutes'=>10]);
                $planId=$plan->id;
            }
            return RideAppointments::create($user,self::journey($inputs,$quote['timing'],$session,$planId),self::fare($quote));
        });
    }

    public static function maintain(): int
    {
        if(!self::ready()) return 0;
        $created=0;
        TimetableRidePlan::where('is_enabled',true)->whereNotNull('timetable_id')->select(['id','user_id'])->chunkById(100,function($plans)use(&$created) {
            foreach($plans as $candidate) {
                $count=DB::transaction(function()use($candidate) {
                    $owner=User::whereKey($candidate->user_id)->lockForUpdate()->first();
                    $plan=TimetableRidePlan::whereKey($candidate->id)->lockForUpdate()->first();
                    if(!$owner||!$owner->isPassenger()||!$owner->isActive()||!$plan||!$plan->is_enabled||!$plan->timetable_id) return 0;
                    $session=DB::table('commute_timetable')->where('id',$plan->timetable_id)->where('user_id',$owner->user_id)->first();
                    if(!$session) return 0;
                    if($plan->ends_on->endOfDay()->lt(now())) {$plan->update(['is_enabled'=>false]);return 0;}
                    if(RideRequest::where('timetable_plan_id',$plan->id)->where('request_status','scheduled')->where('scheduled_for','>',now())->exists()) return 0;
                    $start=$plan->starts_on->format('Y-m-d');
                    while($timing=self::nextSession($session,$start,$plan->ends_on->format('Y-m-d'),$plan->travel_minutes,$plan->arrival_buffer_minutes)) {
                        $existing=RideRequest::where('timetable_plan_id',$plan->id)->where('timetable_session_at',$timing['classStartsAt'])->exists();
                        $conflict=RideRequest::where('passenger_id',$owner->user_id)->where('scheduled_for',$timing['scheduledFor'])
                            ->whereIn('request_status',['scheduled','pending','accepted'])->exists();
                        if(!$existing&&!$conflict) {
                            $inputs=$plan->only(['pickup_label','pickup_latitude','pickup_longitude','dropoff_label','dropoff_latitude','dropoff_longitude','seats_requested']);
                            RideAppointments::create((int)$owner->user_id,self::journey($inputs,$timing,$session,$plan->id),
                                $plan->only(['fare_per_seat','journey_distance_km','distance_source','fare_policy']));
                            return 1;
                        }
                        // A cancelled occurrence is skipped, rather than silently booked again.
                        $start=CarbonImmutable::parse($timing['classStartsAt'])->addWeek()->format('Y-m-d');
                    }
                    return 0;
                });
                $created+=$count;
            }
        });
        return $created;
    }

    public static function toggle(int $user, int $sessionId, bool $enabled): void
    {
        DB::transaction(function()use($user,$sessionId,$enabled) {
            User::whereKey($user)->lockForUpdate()->firstOrFail();
            $plan=TimetableRidePlan::where('user_id',$user)->where('timetable_id',$sessionId)->lockForUpdate()->firstOrFail();
            if($enabled&&$plan->ends_on->endOfDay()->lt(now())) throw \Illuminate\Validation\ValidationException::withMessages(['ends_on'=>'This weekly plan has ended. Review new dates before resuming it.']);
            $plan->update(['is_enabled'=>$enabled]);
            RideRequest::where('timetable_plan_id',$plan->id)->where('request_status',$enabled?'paused':'scheduled')
                ->where('scheduled_for','>',now())->update(['request_status'=>$enabled?'scheduled':'paused','updated_at'=>now()]);
        });
        if($enabled) self::maintain();
    }

    public static function removeSession(int $user, int $sessionId): void
    {
        DB::transaction(function()use($user,$sessionId) {
            User::whereKey($user)->lockForUpdate()->firstOrFail();
            $session=DB::table('commute_timetable')->where('user_id',$user)->where('id',$sessionId)->lockForUpdate()->first();
            if(!$session) return;
            if(self::ready()) {
                $plans=TimetableRidePlan::where('user_id',$user)->where('timetable_id',$sessionId)->pluck('id');
                TimetableRidePlan::whereIn('id',$plans)->update(['is_enabled'=>false,'updated_at'=>now()]);
                RideRequest::where('passenger_id',$user)->where('timetable_entry_id',$sessionId)->whereIn('request_status',['scheduled','paused'])
                    ->where('scheduled_for','>',now())->update(['request_status'=>'cancelled','updated_at'=>now()]);
            }
            DB::table('commute_timetable')->where('user_id',$user)->where('id',$sessionId)->delete();
        });
    }
}
