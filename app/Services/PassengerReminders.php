<?php

namespace App\Services;

use App\Models\RideBooking;
use App\Models\RideRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PassengerReminders
{
    public static function upcoming(int $userId, ?CarbonImmutable $at = null): array
    {
        $now = $at ?? now(config('app.timezone'))->toImmutable();
        $until = $now->addDay();
        $events = [];
        if (Schema::hasTable('commute_timetable')) {
            foreach (DB::table('commute_timetable')->where('user_id', $userId)->get() as $session) {
                $start = $now->startOfDay()->addDays(((int)$session->weekday - $now->dayOfWeek + 7) % 7)->setTimeFromTimeString($session->starts_at);
                if ($start <= $now) $start = $start->addWeek();
                if ($start > $until) continue;
                $events[] = ['id'=>'timetable-'.$session->id, 'kind'=>'class', 'title'=>$session->title,
                    'location'=>$session->location, 'startsAt'=>$start->toIso8601String(),
                    'url'=>url('timetable'), 'action'=>'View timetable'];
            }
        }
        $bookings = RideBooking::where('passenger_id', $userId)->where('booking_status', 'accepted')
            ->whereHas('ride', fn($q) => $q->where('ride_status', 'scheduled')->where('departure_at', '>', $now)->where('departure_at', '<=', $until))
            ->with('ride')->get();
        foreach ($bookings as $booking) {
            if ($booking->ride->ride_mode === 'appointment') continue;
            $events[] = ['id'=>'booking-'.$booking->booking_id, 'kind'=>'ride', 'title'=>'Ride to '.$booking->ride->destination,
                'location'=>$booking->pickup_label ?: $booking->ride->pickup_point,
                'startsAt'=>$booking->ride->departure_at->toIso8601String(),
                'url'=>url('passenger/schedule'), 'action'=>'View my ride'];
        }
        if (RideAppointments::ready()) {
            $requests = RideRequest::where('passenger_id',$userId)->whereNotNull('scheduled_for')
                ->where('scheduled_for','<=',$until)->where('scheduled_for','>=',$now->subDay())
                ->whereIn('request_status',['scheduled','pending','accepted','expired'])->with(['driver','ride'])->get();
            foreach ($requests as $request) {
                $status = $request->displayStatus();
                if (in_array($status,['cancelled','completed','in_progress'],true)) continue;
                $validUntil = $status === 'accepted' ? $now->addMinutes(60) : $request->expires_at->toImmutable()->addHour();
                if ($validUntil <= $now) continue;
                $events[] = ['id'=>'appointment-'.$request->request_id, 'kind'=>'appointment',
                    'title'=>$request->timetable_session_title?'Ride for '.$request->timetable_session_title:'Ride appointment to '.$request->dropoff_label, 'location'=>$request->pickup_label,
                    'startsAt'=>$request->scheduled_for->toIso8601String(), 'status'=>$status,
                    'validUntil'=>$validUntil->toIso8601String(), 'passengers'=>$request->seats_requested,
                    'driver'=>$request->driver?->full_name,
                    'url'=>url('passenger/schedule#appointment-'.$request->request_id), 'action'=>'View appointment'];
            }
        }
        usort($events, fn($a, $b) => strcmp($a['startsAt'], $b['startsAt']) ?: strcmp($a['id'], $b['id']));
        return array_slice($events, 0, 40);
    }
}
