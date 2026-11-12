<?php

namespace App\Services;

use App\Models\Ride;

class RideMatcher
{
    public function match(Ride $ride, array $journey): ?array
    {
        if ((int)$ride->available_seats < (int)($journey['seats_requested'] ?? 1)) return null;
        $desired = empty($journey['departure_at']) ? null : strtotime($journey['departure_at']);
        $difference = $desired ? ($ride->departure_at->getTimestamp() - $desired) / 60 : 0;
        if ($desired && ($difference < 0 || $difference > 120)) return null;
        $plan = (new RoutePlanner)->plan($ride, $journey);
        if (!$plan) return null;
        $maxDetour = max(5, $plan['baseKm'] * 0.4);
        if ($plan['extraKm'] > $maxDetour) return null;
        $routeScore = (int)round(50 * (1 - $plan['extraKm'] / $maxDetour));
        $scheduleScore = (int)round(35 * (1 - $difference / 120));
        $pickupPoint = collect($plan['points'])->first(fn ($p) => $p['requestedPickup'] ?? false);
        $dropoffPoint = collect($plan['points'])->first(fn ($p) => $p['requestedDropoff'] ?? false);
        $start = $plan['points'][0];
        $driverDistance = Journey::km($journey['pickup_latitude'], $journey['pickup_longitude'], $start['lat'], $start['lng']);
        return ['driverDistanceKm' => $driverDistance, 'score' => max(0, min(100, $routeScore + $scheduleScore + 15)), 'breakdown' => ['route' => $routeScore, 'schedule' => $scheduleScore, 'seats' => 15], 'pickupAt' => $ride->departure_at->copy()->addSeconds((int)round(($pickupPoint['elapsedMinutes'] ?? 0)*60))->format('g:i A'), 'arrivalAt' => $ride->departure_at->copy()->addSeconds((int)round(($dropoffPoint['elapsedMinutes'] ?? 0)*60))->format('g:i A'), 'plan' => $plan, 'estimateSource' => $plan['algorithm'] === 'astar_stop_order' ? 'A* stop order; estimated travel times, allow extra time' : 'Fallback stop order; estimated travel times, allow extra time'];
    }
}
