<?php

namespace App\Services;

use App\Models\{RideRequest, User};
use Carbon\Carbon;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;

class RideAppointments
{
    public static function ready(): bool
    {
        return OnDemandRides::ready() && Schema::hasColumn('ride_requests', 'scheduled_for');
    }

    public static function create(int $passenger, array $journey, array $fare): RideRequest
    {
        return DB::transaction(function () use ($passenger, $journey, $fare) {
            User::whereKey($passenger)->lockForUpdate()->firstOrFail();
            $existing = RideRequest::where('passenger_id', $passenger)->where('scheduled_for', $journey['scheduled_for'])
                ->whereIn('request_status', ['scheduled', 'pending', 'accepted'])->lockForUpdate()->first();
            if ($existing) {
                $same = (int)$existing->seats_requested === (int)$journey['seats_requested'];
                foreach (['pickup', 'dropoff'] as $point) {
                    foreach (['latitude', 'longitude'] as $axis) {
                        $key = $point.'_'.$axis;
                        $same = $same && abs((float)$existing->$key - (float)$journey[$key]) < 0.0000001;
                    }
                }
                if ($same) return $existing;
                throw ValidationException::withMessages(['pickup_time'=>'You already have a ride appointment at this time. Cancel it before booking a different journey.']);
            }
            return RideRequest::create($journey + $fare + [
                'passenger_id'=>$passenger, 'request_status'=>'scheduled',
                'expires_at'=>Carbon::parse($journey['scheduled_for'])->addMinutes(10),
            ]);
        });
    }

    public static function dispatchDue(): int
    {
        if (!self::ready()) return 0;
        TimetableCommutes::maintain();
        $dispatched = 0;
        // Iterate in bounded batches so an unattended scheduler catches every due appointment.
        RideRequest::where('request_status', 'scheduled')->where('scheduled_for', '<=', now())
            ->select(['request_id','passenger_id'])->chunkById(100, function ($requests) use (&$dispatched) {
                foreach ($requests as $candidate) {
                    $released = DB::transaction(function () use ($candidate) {
                        $passenger = User::whereKey($candidate->passenger_id)->lockForUpdate()->first();
                        $request = RideRequest::whereKey($candidate->request_id)->lockForUpdate()->first();
                        if (!$request || $request->request_status !== 'scheduled' || $request->scheduled_for->gt(now())) return false;
                        if (!$passenger || !$passenger->isPassenger() || !$passenger->isActive()) {
                            $request->update(['request_status'=>'cancelled']); return false;
                        }
                        if ($request->expires_at->lte(now())) {
                            $request->update(['request_status'=>'expired']); return false;
                        }
                        // An active trip or an existing search must finish before another is released.
                        if (OnDemandRides::active((int)$passenger->user_id)) return false;
                        $request->update(['request_status'=>'pending']);
                        return true;
                    });
                    if ($released) $dispatched++;
                }
            }, 'request_id');
        return $dispatched;
    }

    public static function payload(RideRequest $request): array
    {
        return OnDemandRides::payload($request) + [
            'timetableTitle'=>$request->timetable_session_title,
            'classStartsAt'=>$request->timetable_session_at?->toIso8601String(),
            'scheduledFor'=>$request->scheduled_for->toIso8601String(),
            'dateLabel'=>$request->scheduled_for->format('D, j M Y'),
            'timeLabel'=>$request->scheduled_for->format('g:i A'),
        ];
    }
}
