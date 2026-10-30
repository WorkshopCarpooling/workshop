<?php

return [
    // Instant carpool passenger limit, excluding the driver; no per-accept seat entry.
    'instant_passenger_capacity'=>3,
    // Approximate campus service area; this is not an official campus boundary.
    'driver_area'=>['label'=>'UTeM Main Campus (Durian Tunggal)','lat'=>2.3139,'lng'=>102.3183,'radius_km'=>5],
    // Student cost-sharing defaults in sen. No surge pricing or pickup-distance surcharge.
    'fare' => ['base_sen' => 100, 'per_km_sen' => 30, 'minimum_sen' => 200, 'maximum_sen' => 1200, 'round_to_sen' => 10],
    'quote_minutes' => 10,
    // Bound stop-order search; larger plans use a labelled insertion fallback.
    'pathfinding' => ['max_pairs' => 7, 'max_expansions' => 20000],
];
