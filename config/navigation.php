<?php
return [
    // Google routing is preferred when configured. OSRM supplies road geometry otherwise.
    'osrm_enabled'=>env('NAVIGATION_OSRM_ENABLED',true),
    'osrm_url'=>env('NAVIGATION_OSRM_URL','https://router.project-osrm.org'),
];
