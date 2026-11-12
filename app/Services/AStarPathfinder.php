<?php

namespace App\Services;

use SplPriorityQueue;

/** A* over journey states and estimated distances, not a street-network graph. */
class AStarPathfinder
{
    public function search(array $origin, array $destination, array $pairs, int $maxExpansions = 20000, int $maxPairs = 7): array
    {
        if (count($pairs) > min(7, max(1, $maxPairs))) {
            return ['status' => 'limit', 'points' => [], 'expandedStates' => 0, 'reason' => 'stop_limit'];
        }
        $points = [$origin];
        foreach ($pairs as $pair) {
            $points[] = $pair['pickup'];
            $points[] = $pair['dropoff'];
        }
        $points[] = $destination;
        $end = count($points) - 1;
        $stopCount = $end - 1;
        $all = (1 << $stopCount) - 1;
        $distance = $minutes = [];
        foreach ($points as $i => $from) {
            foreach ($points as $j => $to) {
                $distance[$i][$j] = Journey::km($from['lat'], $from['lng'], $to['lat'], $to['lng']) * 1.28;
                $minutes[$i][$j] = Journey::minutes($from, $to);
            }
        }
        // Every completion connects the remaining stops to the destination.
        // Their MST plus the cheapest connection from the current stop is a
        // lower bound. Relaxing precedence/deadlines keeps it admissible.
        $mstCache = [];
        $heuristic = function (int $mask, int $last) use (&$mstCache, $distance, $end, $stopCount): float {
            $remaining = [$end];
            for ($i = 1; $i <= $stopCount; $i++) {
                if (!($mask & (1 << ($i - 1)))) $remaining[] = $i;
            }
            if (!array_key_exists($mask, $mstCache)) {
                $tree = [$end]; $outside = array_slice($remaining, 1); $cost = 0.0;
                while ($outside) {
                    $best = INF; $bestIndex = 0;
                    foreach ($outside as $index => $node) {
                        foreach ($tree as $inside) {
                            if ($distance[$inside][$node] < $best) {
                                $best = $distance[$inside][$node]; $bestIndex = $index;
                            }
                        }
                    }
                    $cost += $best; $tree[] = $outside[$bestIndex]; unset($outside[$bestIndex]);
                }
                $mstCache[$mask] = $cost;
            }
            $connection = INF;
            foreach ($remaining as $node) $connection = min($connection, $distance[$last][$node]);
            return $mstCache[$mask] + $connection;
        };
        $queue = new SplPriorityQueue;
        $queue->setExtractFlags(SplPriorityQueue::EXTR_DATA);
        $labels = [['mask' => 0, 'last' => 0, 'km' => 0.0, 'minutes' => 0.0, 'previous' => null, 'active' => true, 'bound' => $heuristic(0, 0)]];
        $frontiers = ['0:0' => [0]];
        $queue->insert(0, [-$labels[0]['bound'], 0]);
        $bestCost = INF; $bestLabel = null; $expanded = 0; $limited = false;
        $maxExpansions = min(50000, max(1, $maxExpansions));
        while (!$queue->isEmpty()) {
            $id = $queue->extract(); $state = $labels[$id];
            if (!$state['active']) continue;
            if ($state['bound'] >= $bestCost - 1e-9) break;
            if ($state['mask'] === $all) {
                $bestCost = $state['km'] + $distance[$state['last']][$end]; $bestLabel = $id;
                continue;
            }
            if ($expanded >= $maxExpansions || count($labels) >= 50000) { $limited = true; break; }
            $expanded++;
            for ($next = 1; $next <= $stopCount; $next++) {
                $bit = 1 << ($next - 1);
                if ($state['mask'] & $bit) continue;
                // Odd indices are pickups; paired drop-offs are even.
                if ($next % 2 === 0 && !($state['mask'] & (1 << ($next - 2)))) continue;
                $elapsed = $state['minutes'] + $minutes[$state['last']][$next] + 2;
                if (isset($points[$next]['deadlineMinutes']) && $elapsed > $points[$next]['deadlineMinutes'] + 1e-9) continue;
                $mask = $state['mask'] | $bit;
                $km = $state['km'] + $distance[$state['last']][$next];
                $bound = $km + $heuristic($mask, $next);
                if ($bound >= $bestCost - 1e-9) continue;
                $key = $mask . ':' . $next; $dominated = false;
                // Keep non-dominated distance/time labels. Distance alone cannot
                // close a state when a longer prefix may meet a later deadline.
                foreach ($frontiers[$key] ?? [] as $otherId) {
                    $other = $labels[$otherId];
                    if ($other['active'] && $other['km'] <= $km + 1e-9 && $other['minutes'] <= $elapsed + 1e-9) { $dominated = true; break; }
                }
                if ($dominated) continue;
                $survivors = [];
                foreach ($frontiers[$key] ?? [] as $otherId) {
                    $other = $labels[$otherId];
                    if (!$other['active']) continue;
                    if ($km <= $other['km'] + 1e-9 && $elapsed <= $other['minutes'] + 1e-9) $labels[$otherId]['active'] = false;
                    else $survivors[] = $otherId;
                }
                $newId = count($labels);
                $labels[] = ['mask' => $mask, 'last' => $next, 'km' => $km, 'minutes' => $elapsed, 'previous' => $id, 'active' => true, 'bound' => $bound];
                $survivors[] = $newId; $frontiers[$key] = $survivors;
                $queue->insert($newId, [-$bound, -$newId]);
            }
        }
        $path = [];
        if ($bestLabel !== null) {
            for ($id = $bestLabel; $id !== null; $id = $labels[$id]['previous']) $path[] = $points[$labels[$id]['last']];
            $path = array_reverse($path); $path[] = $destination;
        }
        return ['status' => $limited ? 'limit' : ($path ? 'complete' : 'infeasible'), 'points' => $path, 'expandedStates' => $expanded, 'reason' => $limited ? 'search_limit' : null];
    }
}
