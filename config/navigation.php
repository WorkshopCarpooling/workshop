<?php
$local=is_file(__DIR__.'/navigation.local.php')?require __DIR__.'/navigation.local.php':[];
return [
    // Google routing is preferred when configured. OSRM supplies road geometry otherwise.
    'osrm_enabled'=>env('NAVIGATION_OSRM_ENABLED',true),
    'osrm_url'=>env('NAVIGATION_OSRM_URL','https://router.project-osrm.org'),
    'test_mode'=>env('NAVIGATION_TEST_MODE',$local['test_mode']??false),
    'test_leg_seconds'=>20,
];
