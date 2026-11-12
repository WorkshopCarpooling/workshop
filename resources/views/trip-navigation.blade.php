<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><title>Journey map · RideUTeM</title>
@foreach(['dashboard','driver','usability','campus-interface','live-tracking'] as $style)<link rel="stylesheet" href="{{ asset('css/'.$style.'.css') }}">@endforeach
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@include('partials.device-head') @include('partials.theme-head')
<link rel="stylesheet" href="{{ asset('css/trip-navigation.css') }}?v={{ is_file(public_path('css/trip-navigation.css'))?filemtime(public_path('css/trip-navigation.css')):'nav' }}">
<link rel="stylesheet" href="{{ asset('css/map-carbon.css') }}?v={{ filemtime(public_path('css/map-carbon.css')) }}">
</head><body class="app-page navigation-page"><div class="app-shell">@include('partials.passenger_sidebar')
<main class="main-area"><header class="topbar"><div class="breadcrumb">Your journey <span>/</span> Map</div></header><div class="page-content">
<div class="welcome-row"><div><div class="date-line">{{ ($navigation['testTrip']??false)?'TRIP UJIAN · AUTOMATIC DRIVING':'LIVE CAMPUS JOURNEY' }}</div><h1>Follow the journey</h1><p class="subtitle">{{ \App\Services\LocationLabel::short($ride->destination) }} · Trip #{{ $ride->ride_id }}</p></div><a class="outline-button" href="{{ url($navigation['isDriver']?'driver/requests':'passenger/bookings') }}">← My rides</a></div>
@if($navigation['testTrip']??false)<p class="navigation-test-notice" role="note"><b>Automatic driving</b> · Confirm each pickup and drop-off when the car arrives. Finishing completes this order for you and your passengers.</p>@endif
<div class="navigation-layout">
 <section class="navigation-map-card"><div class="navigation-instruction"><span class="navigation-turn" aria-hidden="true">↗</span><div><small id="navigationMode">CONNECTING TO YOUR JOURNEY</small><h2 id="navigationNext">Preparing your route…</h2><p id="navigationHint">{{ $navigation['isDriver']?'Pickup first, then your destination.':'Follow your car to the destination.' }}</p></div></div>
 <div id="journeyNavigationMap" class="navigation-map" aria-label="Journey map with car position, pickup stops and destination"></div>
 <div class="navigation-map-tools"><button type="button" id="navigationFollow" aria-pressed="true">⌖ Follow car</button><span id="navigationRoad">Loading route…</span></div></section>
 <aside class="navigation-panel content-card"><span class="section-kicker">JOURNEY PROGRESS</span><h2 id="navigationState">Live journey</h2><p id="navigationGps" role="status">Waiting for the driver location. The car moves only when GPS updates arrive.</p>
 <p class="map-carbon-caption" id="navigationCarbon">Calculating the trip carbon estimate…</p>
 @if($navigation['isDriver']&&!($navigation['testTrip']??false))@include('partials.driver-live-gps',['inlineGps'=>true,'activeRides'=>collect([$ride])])@endif
 <div class="navigation-metrics"><div><small id="navigationEstimateLabel">To next stop · estimate</small><b id="navigationEstimate">—</b></div><div><small>{{ ($navigation['testTrip']??false)?'Position source':'Last GPS update' }}</small><b id="navigationUpdated">—</b></div></div>
 <ol class="navigation-stops" id="navigationStops" aria-label="{{ $navigation['isDriver']?'Ordered journey stops':'Your journey' }}"></ol>
 @if($navigation['isDriver'])<button class="solid-button" type="button" id="navigationConfirm" hidden>Confirm stop</button>
 <form id="navigationComplete" action="{{ ($navigation['testTrip']??false)?url('trips/'.$ride->ride_id.'/test/finish'):url('driver.php') }}" method="post" hidden>@csrf
 @if($navigation['testTrip']??false)<input type="hidden" name="test_run_id" value="{{ $navigation['simulation']['runId'] }}">
 @else<input type="hidden" name="action" value="update_status"><input type="hidden" name="ride_id" value="{{ $ride->ride_id }}"><input type="hidden" name="next_status" value="completed">@endif
 <button class="solid-button" type="submit">{{ ($navigation['testTrip']??false)?'Finish test trip':'Complete trip' }}</button></form>
 @endif
 <p id="navigationActionStatus" role="status"></p>
 <p class="navigation-note">{{ $navigation['isDriver']?'Pickup and drop-off are confirmed by the driver. Tracking closes when the trip ends. Road times are estimates; shared pickups and traffic can add time.':'Your car follows the journey to your destination. This view closes when your driver completes the trip.' }}</p>
 </aside></div></div></main></div>
@php($navigationConfig=['state'=>$navigation,'route'=>$road,'testTrip'=>$navigation['testTrip']??false,'carIconUrl'=>asset('images/tracking-car-3d.png').'?v='.filemtime(public_path('images/tracking-car-3d.png')),'feedUrl'=>url('api/trips/'.$ride->ride_id.(($navigation['testTrip']??false)?'/test-navigation':'/navigation')),'advanceUrl'=>url('trips/'.$ride->ride_id.(($navigation['testTrip']??false)?'/test/stops':'/stops')),'returnUrl'=>url($navigation['isDriver']?'driver/schedule':'passenger/bookings'),'csrf'=>csrf_token()])
<script>window.tripNavigation=@json($navigationConfig);</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
<script src="{{ asset('js/trip-navigation-geometry.js') }}?v={{ is_file(public_path('js/trip-navigation-geometry.js'))?filemtime(public_path('js/trip-navigation-geometry.js')):'nav' }}" defer></script>
<script src="{{ asset('js/trip-navigation.js') }}?v={{ is_file(public_path('js/trip-navigation.js'))?filemtime(public_path('js/trip-navigation.js')):'nav' }}" defer></script>
@include('partials.device-tools')
</body></html>
