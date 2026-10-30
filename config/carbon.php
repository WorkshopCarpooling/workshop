<?php

return [
    'road_lookup_enabled' => env('CARBON_ROAD_LOOKUP_ENABLED',true),
    'method' => 'CMM2025-MOBILE-v1',
    'source_title' => 'Capital Markets Malaysia SEDG GHG Calculator User Guide, July 2025, page 9',
    'source_url' => 'https://www.capitalmarketsmalaysia.com/sedg-ghg-calculator/pdf/User-Guide_SEDG-GHG-Emissions-Calculator.pdf',
    // kg CO2e / litre, mobile combustion. These already include GWP conversion.
    'fuels' => [
        'petrol' => ['label' => 'Petrol', 'factor' => 2.288],
        'diesel' => ['label' => 'Diesel (unblended)', 'factor' => 2.909],
        'diesel_b7' => ['label' => 'Diesel B7', 'factor' => 2.705],
        'diesel_b10' => ['label' => 'Diesel B10', 'factor' => 2.618],
        'diesel_b20' => ['label' => 'Diesel B20', 'factor' => 2.327],
    ],
    'modes' => [
        'unknown' => 'Not sure yet', 'drive_alone' => 'Drive in a separate car',
        'walk_cycle' => 'Walk or cycle', 'no_trip' => 'Not make this journey',
        'motorcycle' => 'Motorcycle', 'bus' => 'Bus / public transport', 'carpool' => 'Another shared ride',
    ],
];
