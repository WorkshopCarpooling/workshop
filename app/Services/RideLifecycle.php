<?php
namespace App\Services;

use App\Models\{Ride, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RideLifecycle {
    public static function transition(User $driver, int $rideId, string $nextStatus): bool {
        $previous = ['in_progress'=>'scheduled', 'completed'=>'in_progress', 'cancelled'=>'scheduled'][$nextStatus] ?? null;
        if (!$previous) return false;
        $updated = DB::transaction(function () use ($driver, $rideId, $nextStatus, $previous) {
            $owner = User::whereKey($driver->user_id)->lockForUpdate()->firstOrFail();
            abort_unless($owner->isDriver() && $owner->isActive(), 403);
            $ride = Ride::whereKey($rideId)->where('driver_id', $driver->user_id)->lockForUpdate()->first();
            if (!$ride || $ride->ride_status !== $previous) return false;
            $changes = ['ride_status'=>$nextStatus];
            if ($nextStatus === 'in_progress') {
                if (Ride::where('driver_id', $driver->user_id)->where('ride_status', 'in_progress')->where('ride_id', '<>', $rideId)->exists())
                    throw ValidationException::withMessages(['ride'=>'Finish your active trip before starting another pickup route.']);
                if (SharedRidePool::isPool($ride) && !(new RoutePlanner)->plan($ride->load('bookings'))) return false;
                $changes['started_at'] = now();
                if (TripNavigation::ready() && ($plan = TripNavigation::stops($ride->load('bookings')))) $changes['navigation_plan'] = $plan;
            }
            if ($nextStatus === 'completed') $changes['completed_at'] = now();
            $ride->update($changes);
            if (in_array($nextStatus, ['completed', 'cancelled'], true)) DriverLocation::forget($rideId);
            if (in_array($nextStatus, ['cancelled', 'in_progress'], true))
                $ride->bookings()->where('booking_status', 'pending')->update(['booking_status'=>'cancelled']);
            if ($nextStatus === 'cancelled') $ride->bookings()->where('booking_status', 'accepted')->update(['booking_status'=>'cancelled']);
            return true;
        }, 3);
        if ($updated && $nextStatus === 'completed') {
            try { AutomaticCarbon::recordCompleted($rideId); }
            catch (\Throwable $error) { report($error); }
        }
        return $updated;
    }
}
