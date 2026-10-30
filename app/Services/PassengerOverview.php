<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class PassengerOverview
{
    public static function fromBookings(Collection $bookings, ?CarbonInterface $at = null): array
    {
        $at ??= now();
        $upcoming = $bookings->filter(function ($booking) use ($at) {
            $ride = $booking->ride;
            if (!$ride || !in_array($booking->booking_status, ['pending', 'accepted'], true)) {
                return false;
            }
            return $ride->ride_status === 'in_progress'
                || ($ride->ride_status === 'scheduled' && in_array($ride->ride_mode ?? 'scheduled', ['now', 'appointment'], true))
                || ($ride->ride_status === 'scheduled' && $ride->departure_at && $ride->departure_at->gte($at));
        })->sortBy(fn ($booking) => $booking->ride->departure_at?->getTimestamp() ?? PHP_INT_MAX)->values();

        return [
            'upcomingCount' => $upcoming->count(),
            'confirmedCount' => $upcoming->where('booking_status', 'accepted')->count(),
            'pendingCount' => $upcoming->where('booking_status', 'pending')->count(),
            'nextBooking' => $upcoming->first(),
            'recentBookings' => $bookings->reject(fn ($booking) => $booking->booking_status === 'cancelled' || $booking->ride?->ride_status === 'cancelled')->sortByDesc(fn ($booking) => $booking->created_at?->getTimestamp()
                ?? $booking->ride?->departure_at?->getTimestamp() ?? 0)->take(3)->values(),
        ];
    }
}
