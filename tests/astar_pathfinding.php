<?php

// Pure in-memory fixtures and a brute-force oracle; no database records changed.
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Ride;
use App\Models\RideBooking;
use App\Services\AStarPathfinder;
use App\Services\Journey;
use App\Services\RoutePlanner;
use Carbon\Carbon;

$checks = 0;
function verifyPath($condition, $message) {
    global $checks;
    if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }
    $checks++; echo "PASS: $message\n";
}
function stop($label, $lat, $lng) { return compact('label', 'lat', 'lng'); }
function pathCost($points) {
    $cost = 0;
    for ($i = 1; $i < count($points); $i++) $cost += Journey::km($points[$i-1]['lat'], $points[$i-1]['lng'], $points[$i]['lat'], $points[$i]['lng']) * 1.28;
    return $cost;
}
// Enumerate every precedence-valid order, without heuristic bounds or dominance.
function exhaustiveCost($start, $end, $pairs) {
    $best = INF;
    $visit = function ($last, $picked, $dropped, $elapsed, $distance) use (&$visit, &$best, $end, $pairs) {
        if (count($dropped) === count($pairs)) {
            $best = min($best, $distance + pathCost([$last, $end])); return;
        }
        foreach ($pairs as $i => $pair) {
            if (isset($dropped[$i])) continue;
            $isDropoff = isset($picked[$i]);
            $next = $pair[$isDropoff ? 'dropoff' : 'pickup'];
            $arrival = $elapsed + Journey::minutes($last, $next) + 2;
            if (isset($next['deadlineMinutes']) && $arrival > $next['deadlineMinutes'] + 1e-9) continue;
            $nextPicked = $picked; $nextDropped = $dropped;
            if ($isDropoff) $nextDropped[$i] = true;
            else $nextPicked[$i] = true;
            $visit($next, $nextPicked, $nextDropped, $arrival, $distance + pathCost([$last, $next]));
        }
    };
    $visit($start, [], [], 0, 0);
    return $best;
}

$start = stop('Start', 2.22, 102.25);
$end = stop('End', 2.32, 102.32);
$pairs = [
    ['pickup' => stop('P1', 2.27, 102.29), 'dropoff' => stop('D1', 2.31, 102.30)],
    ['pickup' => stop('P2', 2.24, 102.26), 'dropoff' => stop('D2', 2.29, 102.31)],
    ['pickup' => stop('P3', 2.26, 102.30), 'dropoff' => stop('D3', 2.30, 102.28)],
    ['pickup' => stop('P4', 2.25, 102.27), 'dropoff' => stop('D4', 2.28, 102.29)],
];
$search = new AStarPathfinder;
for ($case = 0; $case < 12; $case++) {
    $fixture = array_slice($pairs, 0, 2 + $case % 3);
    if ($case >= 3) $fixture[$case % count($fixture)]['dropoff']['deadlineMinutes'] = [16, 24, 32][$case % 3];
    if ($case >= 6) $fixture[0]['pickup'] = array_replace($fixture[0]['pickup'], ['lat' => 2.22 + $case / 1000, 'lng' => 102.25]);
    $expected = exhaustiveCost($start, $end, $fixture);
    $actual = $search->search($start, $end, $fixture);
    verifyPath(is_infinite($expected) ? $actual['status'] === 'infeasible' : $actual['status'] === 'complete' && abs(pathCost($actual['points']) - $expected) < 1e-7, "A* matches exhaustive optimum/feasibility for fixture $case");
}
$plan = $search->search($start, $end, $pairs);
// Short legs exercise the minimum-leg-time rule and distance/time tradeoffs.
mt_srand(20261024);
$improvements = 0;
for ($case = 0; $case < 60; $case++) {
    $fixture = [];
    for ($passenger = 0; $passenger < 3; $passenger++) {
        $fixture[] = ['pickup' => stop('P'.$passenger, 2.22 + mt_rand(0, 30)/1000, 102.25 + mt_rand(0, 30)/1000),
            'dropoff' => stop('D'.$passenger, 2.22 + mt_rand(0, 30)/1000, 102.25 + mt_rand(0, 30)/1000)];
    }
    if ($case % 2) $fixture[$case % 3]['dropoff']['deadlineMinutes'] = mt_rand(10, 30);
    $expected = exhaustiveCost($start, $end, $fixture);
    $actual = $search->search($start, $end, $fixture);
    if (!(is_infinite($expected) ? $actual['status'] === 'infeasible' : $actual['status'] === 'complete' && abs(pathCost($actual['points']) - $expected) < 1e-7)) {
        verifyPath(false, "deterministic exhaustive comparison $case");
    }
    $greedy = [$start, $end];
    foreach ($fixture as $pair) {
        $greedy = (new RoutePlanner)->insert($greedy, $pair['pickup'], $pair['dropoff']);
        if (!$greedy) break;
    }
    if ($actual['points'] && (!$greedy || pathCost($actual['points']) < pathCost($greedy) - 1e-7)) $improvements++;
}
verifyPath(true, 'A* matches exhaustive search on 60 additional deterministic distance/deadline cases');
verifyPath($improvements > 0, 'A* improves on greedy insertion in regression cases');
$labels = array_column($plan['points'], 'label');
verifyPath(count($labels) === 10 && count(array_unique($labels)) === 10 && $labels[0] === 'Start' && end($labels) === 'End', 'every stop visited exactly once and driver endpoints fixed');
foreach (range(1, 4) as $i) verifyPath(array_search('P'.$i, $labels) < array_search('D'.$i, $labels), "passenger $i picked up before drop-off");
$direct = $search->search($start, $end, []);
verifyPath($direct['status'] === 'complete' && count($direct['points']) === 2, 'journey without passengers has a direct A* solution');
$coincident = [['pickup' => stop('P', 2.22, 102.25), 'dropoff' => stop('D', 2.22, 102.25)]];
verifyPath(count($search->search($start, $end, $coincident)['points']) === 4, 'coincident coordinates retain separate pickup and drop-off tasks');
$limited = $search->search($start, $end, $pairs, 1);
verifyPath($limited['status'] === 'limit' && $limited['expandedStates'] === 1, 'expansion limit returns an explicit incomplete result');
verifyPath($search->search($start, $end, $pairs, 20000, 2)['reason'] === 'stop_limit', 'oversized journeys stop before exponential search');

Carbon::setTestNow(Carbon::parse('2030-01-07 07:00:00', 'Asia/Kuala_Lumpur'));
$ride = new Ride(['pickup_point'=>'Start', 'destination'=>'End', 'pickup_latitude'=>$start['lat'], 'pickup_longitude'=>$start['lng'], 'dropoff_latitude'=>$end['lat'], 'dropoff_longitude'=>$end['lng'], 'departure_at'=>'2030-01-07 08:00:00', 'available_seats'=>3]);
$bookings = [];
foreach (array_slice($pairs, 0, 3) as $i => $pair) {
    $bookings[] = new RideBooking(['booking_id'=>$i+1, 'booking_status'=>'accepted', 'pickup_label'=>$pair['pickup']['label'], 'pickup_latitude'=>$pair['pickup']['lat'], 'pickup_longitude'=>$pair['pickup']['lng'], 'dropoff_label'=>$pair['dropoff']['label'], 'dropoff_latitude'=>$pair['dropoff']['lat'], 'dropoff_longitude'=>$pair['dropoff']['lng'], 'arrive_by'=>'2030-01-07 09:00:00']);
}
$ride->setRelation('bookings', collect($bookings));
$integrated = (new RoutePlanner)->plan($ride);
verifyPath($integrated['source'] === 'astar_stop_order' && $integrated['optimal'], 'production planner uses A* by default');
verifyPath(abs($integrated['distanceKm'] - round(exhaustiveCost($start, $end, array_slice($pairs,0,3)),2)) < .001, 'integrated plan matches exhaustive distance');
$oldLimit = config('carpool.pathfinding.max_pairs');
config(['carpool.pathfinding.max_pairs'=>1]);
$fallback = (new RoutePlanner)->plan($ride);
verifyPath($fallback && $fallback['algorithm'] === 'distance_insertion_heuristic' && !$fallback['optimal'] && $fallback['fallbackReason'] === 'stop_limit', 'bounded fallback is feasible and never claims optimal A*');
$bookings[0]->arrive_by = '2030-01-07 08:00:01';
verifyPath((new RoutePlanner)->plan($ride) === null, 'fallback also rejects impossible accepted-passenger deadlines');
config(['carpool.pathfinding.max_pairs'=>$oldLimit]);
verifyPath((new RoutePlanner)->plan($ride) === null, 'A* rejects impossible accepted-passenger deadlines');
Carbon::setTestNow();
echo "$checks A* checks passed; no database writes.\n";
