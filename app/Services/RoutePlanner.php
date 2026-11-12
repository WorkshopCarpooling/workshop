<?php

namespace App\Services;

use App\Http\Controllers\RoutesController;
use App\Models\Ride;

class RoutePlanner
{
    public function endpoints(Ride $ride): ?array
    {
        if(SharedRidePool::isPool($ride))return [
            ['lat'=>$ride->pool_origin_latitude,'lng'=>$ride->pool_origin_longitude,'label'=>'Driver starting point'],
            ['lat'=>$ride->dropoff_latitude,'lng'=>$ride->dropoff_longitude,'label'=>$ride->destination],
        ];
        $known = RoutesController::getCampusLocations();
        $origin = $ride->pickup_latitude !== null && $ride->pickup_longitude !== null
            ? ['lat' => $ride->pickup_latitude, 'lng' => $ride->pickup_longitude]
            : ($known[$ride->pickup_point] ?? null);
        $destination = $ride->dropoff_latitude !== null && $ride->dropoff_longitude !== null
            ? ['lat' => $ride->dropoff_latitude, 'lng' => $ride->dropoff_longitude]
            : ($known[$ride->destination] ?? null);
        if (!$origin && $ride->origin_latitude !== null && $ride->origin_longitude !== null) $origin = ['lat' => (float)$ride->origin_latitude, 'lng' => (float)$ride->origin_longitude];
        if (!$destination && $ride->destination_latitude !== null && $ride->destination_longitude !== null) $destination = ['lat' => (float)$ride->destination_latitude, 'lng' => (float)$ride->destination_longitude];
        return $origin && $destination ? [$origin + ['label' => $ride->pickup_point], $destination + ['label' => $ride->destination]] : null;
    }

    public function distance(array $points): float
    {
        $km = 0;
        for ($i = 1; $i < count($points); $i++) $km += Journey::km($points[$i-1]['lat'], $points[$i-1]['lng'], $points[$i]['lat'], $points[$i]['lng']) * 1.28;
        return $km;
    }

    public function insert(array $path, array $pickup, array $dropoff): array
    {
        $best = null; $bestKm = INF;
        // Bounded fallback for large journeys; pickup precedes its drop-off.
        for ($i = 1; $i < count($path); $i++) {
            $candidate = $path; array_splice($candidate, $i, 0, [$pickup]);
            for ($j = $i + 1; $j < count($candidate); $j++) {
                $trial = $candidate; array_splice($trial, $j, 0, [$dropoff]);
                $km = $this->distance($trial);
                if ($km < $bestKm && $this->deadlinesMet($trial)) { $best = $trial; $bestKm = $km; }
            }
        }
        return $best ?? [];
    }

    private function deadlinesMet(array $path): bool
    {
        $elapsed = 0;
        for ($i = 1; $i < count($path); $i++) {
            $elapsed += Journey::minutes($path[$i-1], $path[$i]) + ($i < count($path)-1 ? 2 : 0);
            if (isset($path[$i]['deadlineMinutes']) && $elapsed > $path[$i]['deadlineMinutes'] + 1e-9) return false;
        }
        return true;
    }

    private function optimize(array $endpoints, array $pairs): ?array
    {
        $result = (new AStarPathfinder)->search($endpoints[0], $endpoints[1], $pairs,
            (int)config('carpool.pathfinding.max_expansions', 20000), (int)config('carpool.pathfinding.max_pairs', 7));
        if ($result['status'] === 'infeasible') return null;
        $algorithm = 'astar_stop_order';
        if (!$result['points']) {
            $path = $endpoints;
            foreach ($pairs as $pair) {
                $path = $this->insert($path, $pair['pickup'], $pair['dropoff']);
                if (!$path) return null;
            }
            $result['points'] = $path;
            $algorithm = 'distance_insertion_heuristic';
        }
        return $result + ['algorithm' => $algorithm, 'optimal' => $result['status'] === 'complete'];
    }

    public function plan(Ride $ride, ?array $request = null): ?array
    {
        if(SharedRidePool::isPool($ride))return $request ? null : SharedRidePool::plan($ride);
        $endpoints = $this->endpoints($ride);
        if (!$endpoints) return null;
        $pairs = [];
        foreach ($ride->bookings->where('booking_status', 'accepted')->sortBy('booking_id') as $booking) {
            if ($booking->pickup_latitude === null || $booking->pickup_longitude === null) return null;
            $pickup = ['lat' => $booking->pickup_latitude, 'lng' => $booking->pickup_longitude, 'label' => 'Pickup: ' . $booking->pickup_label];
            $dropoff = ['lat' => $booking->dropoff_latitude ?? $endpoints[1]['lat'], 'lng' => $booking->dropoff_longitude ?? $endpoints[1]['lng'], 'label' => 'Drop-off: ' . ($booking->dropoff_label ?: $ride->destination)];
            $pickup+=['kind'=>'pickup','bookingId'=>$booking->booking_id];
            $dropoff+=['kind'=>'dropoff','bookingId'=>$booking->booking_id];
            if ($booking->arrive_by) $dropoff['deadlineMinutes'] = ($booking->arrive_by->getTimestamp() - $ride->departure_at->getTimestamp()) / 60;
            $pairs[] = compact('pickup', 'dropoff');
        }
        $base = $this->optimize($endpoints, $pairs);
        if (!$base) return null;
        $before = $this->distance($base['points']);
        $result = $base;
        if ($request) {
            $pickup = ['lat' => (float)$request['pickup_latitude'], 'lng' => (float)$request['pickup_longitude'], 'label' => 'Pickup: ' . $request['pickup_label'], 'requestedPickup' => true];
            $dropoff = ['lat' => (float)$request['dropoff_latitude'], 'lng' => (float)$request['dropoff_longitude'], 'label' => 'Drop-off: ' . $request['dropoff_label'], 'requestedDropoff' => true];
            if (!empty($request['arrive_by'])) $dropoff['deadlineMinutes'] = (strtotime($request['arrive_by']) - $ride->departure_at->getTimestamp()) / 60;
            $pairs[] = compact('pickup', 'dropoff');
            $result = $this->optimize($endpoints, $pairs);
            if (!$result) return null;
        }
        $path = $result['points'];
        $baseline = array_merge([$endpoints[0]], array_column($pairs, 'pickup'), array_column($pairs, 'dropoff'), [$endpoints[1]]);
        $distance = $this->distance($path); $minutes = 0;
        foreach ($path as $i => &$point) {
            if ($i > 0) $minutes += Journey::minutes($path[$i-1], $point) + ($i < count($path)-1 ? 2 : 0);
            $point['elapsedMinutes'] = round($minutes, 1);
        }
        unset($point);
        return ['points' => $path, 'distanceKm' => round($distance, 2), 'durationMinutes' => round($minutes, 1),
            'extraKm' => max(0, $distance - $before), 'baseKm' => $before, 'baselineKm' => round($this->distance($baseline), 2),
            'source' => $result['algorithm'], 'algorithm' => $result['algorithm'], 'optimal' => $result['optimal'],
            'expandedStates' => $result['expandedStates'], 'fallbackReason' => $result['reason'],
            'distanceBasis' => 'straight_line_times_1_28'];
    }
}
