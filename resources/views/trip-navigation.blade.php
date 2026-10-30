<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><title>Journey map · RideUTeM</title>
@foreach(['dashboard','driver','usability','campus-interface','live-tracking'] as $style)<link rel="stylesheet" href="{{ asset('css/'.$style.'.css') }}">@endforeach
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@include('partials.device-head') @include('partials.theme-head')
<link rel="stylesheet" href="{{ asset('css/trip-navigation.css') }}?v={{ is_file(public_path('css/trip-navigation.css'))?filemtime(public_path('css/trip-navigation.css')):'nav' }}">
</head><body class="app-page navigation-page"><div class="app-shell">@include('partials.passenger_sidebar')
<main class="main-area"><header class="topbar"><div class="breadcrumb">Your journey <span>/</span> Map</div></header><div class="page-content">
<div class="welcome-row"><div><div class="date-line">LIVE CAMPUS JOURNEY</div><h1>Follow the journey</h1><p class="subtitle">{{ $ride->destination }} · Trip #{{ $ride->ride_id }}</p></div><a class="outline-button" href="{{ url($navigation['isDriver']?'driver/schedule':'passenger/bookings') }}">← My rides</a></div>
<div class="navigation-layout">
 <section class="navigation-map-card"><div class="navigation-instruction"><span class="navigation-turn" aria-hidden="true">↗</span><div><small id="navigationMode">CONNECTING TO YOUR JOURNEY</small><h2 id="navigationNext">Preparing your route…</h2><p id="navigationHint">Pickup first, then your destination.</p></div></div>
 <div id="journeyNavigationMap" class="navigation-map" aria-label="Journey map with car position, pickup stops and destination"></div>
 <div class="navigation-map-tools"><button type="button" id="navigationFollow" aria-pressed="true">⌖ Follow car</button><span id="navigationRoad">Loading route…</span></div></section>
 <aside class="navigation-panel content-card"><span class="section-kicker">JOURNEY PROGRESS</span><h2 id="navigationState">Live journey</h2><p id="navigationGps" role="status">Waiting for the driver location. The car moves only when GPS updates arrive.</p>
 <div class="navigation-metrics"><div><small>Route estimate</small><b id="navigationEstimate">—</b></div><div><small>Last GPS update</small><b id="navigationUpdated">—</b></div></div>
 <ol class="navigation-stops" id="navigationStops" aria-label="Ordered journey stops"></ol>
 @if($navigation['isDriver'])<button class="solid-button" type="button" id="navigationConfirm" hidden>Confirm stop</button>
 <form id="navigationComplete" action="{{ url('driver.php') }}" method="post" hidden>@csrf<input type="hidden" name="action" value="update_status"><input type="hidden" name="ride_id" value="{{ $ride->ride_id }}"><input type="hidden" name="next_status" value="completed"><button class="solid-button" type="submit">Complete trip</button></form>
 @endif
 <p id="navigationActionStatus" role="status"></p>
 <p class="navigation-note">Pickup and drop-off are confirmed by the driver. Tracking closes when the trip ends. Road times are estimates; shared pickups and traffic can add time.</p>
 </aside></div></div></main></div>
@php($navigationConfig=['state'=>$navigation,'route'=>$road,'carIconUrl'=>asset('images/tracking-car-pixel.svg').'?v='.(is_file(public_path('images/tracking-car-pixel.svg'))?filemtime(public_path('images/tracking-car-pixel.svg')):'pixel'),'feedUrl'=>url('api/trips/'.$ride->ride_id.'/navigation'),'advanceUrl'=>url('trips/'.$ride->ride_id.'/stops'),'returnUrl'=>url($navigation['isDriver']?'driver/schedule':'passenger/bookings'),'csrf'=>csrf_token()])
<script>window.tripNavigation=@json($navigationConfig);</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
<script src="{{ asset('js/trip-navigation-geometry.js') }}?v={{ is_file(public_path('js/trip-navigation-geometry.js'))?filemtime(public_path('js/trip-navigation-geometry.js')):'nav' }}" defer></script>
<script src="{{ asset('js/trip-navigation.js') }}?v={{ is_file(public_path('js/trip-navigation.js'))?filemtime(public_path('js/trip-navigation.js')):'nav' }}" defer></script>
@include('partials.driver-live-gps')
@include('partials.device-tools')
</body></html>
