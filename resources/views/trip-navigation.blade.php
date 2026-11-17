<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><title>Journey map · RideUTeM</title>
@foreach(['dashboard','driver','usability','campus-interface','live-tracking'] as $style)<link rel="stylesheet" href="{{ asset('css/'.$style.'.css') }}">@endforeach
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@include('partials.device-head') @include('partials.theme-head')
<link rel="stylesheet" href="{{ asset('css/trip-navigation.css') }}?v={{ is_file(public_path('css/trip-navigation.css'))?filemtime(public_path('css/trip-navigation.css')):'nav' }}">
<link rel="stylesheet" href="{{ asset('css/map-carbon.css') }}?v={{ filemtime(public_path('css/map-carbon.css')) }}">
<link rel="stylesheet" href="{{ asset('css/trip-assistance.css') }}?v={{ filemtime(public_path('css/trip-assistance.css')) }}">
</head><body class="app-page navigation-page"><div class="app-shell">@include('partials.passenger_sidebar')
<main class="main-area"><header class="topbar"><div class="breadcrumb">Your journey <span>/</span> Map</div></header><div class="page-content">
<div class="welcome-row"><div><h1>Follow the journey</h1><p class="subtitle">{{ \App\Services\LocationLabel::short($ride->destination) }} · Trip #{{ $ride->ride_id }}</p></div><a class="outline-button" href="{{ url($navigation['isDriver']?'driver/requests':'passenger/bookings') }}">My rides</a></div>
<div class="navigation-layout">
 <section class="navigation-map-card"><div class="navigation-instruction"><span class="navigation-turn" aria-hidden="true">↗</span><div><small id="navigationMode">CONNECTING TO YOUR JOURNEY</small><h2 id="navigationNext">Preparing your route…</h2><p id="navigationHint">{{ $navigation['isDriver']?'Pickup first, then your destination.':'Follow your car to the destination.' }}</p></div></div>
 <div id="journeyNavigationMap" class="navigation-map" aria-label="Journey map with car position, pickup stops and destination"></div>
 <div class="navigation-map-tools"><button type="button" id="navigationFollow" aria-pressed="true">⌖ Follow car</button><span id="navigationRoad">Loading route…</span></div></section>
 <aside class="navigation-panel content-card"><span class="section-kicker">JOURNEY PROGRESS</span><h2 id="navigationState">Trip in progress</h2><p id="navigationGps" role="status">Loading the latest trip update…</p>
 <p class="map-carbon-caption" id="navigationCarbon">Calculating the trip carbon estimate…</p>
 <section class="trip-assistance" id="trip-assistance" aria-label="Roadside assistance"
   data-feed-url="{{ route('assistance.trip.status', $ride->getKey()) }}" @unless($navigation['isDriver']) hidden @endunless>
   @if($navigation['isDriver'])
   <button type="button" class="assistance-emergency" data-assistance-open aria-haspopup="dialog" aria-controls="assistance-dialog">
     <span class="assistance-icon" aria-hidden="true">
       <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 4.9a2 2 0 0 1 3.4 0L21 17a2 2 0 0 1-1.7 3H4.7A2 2 0 0 1 3 17Z"/><path d="M12 9v4m0 3h.01"/></svg>
     </span>
     <span>Report an emergency</span>
     <svg class="assistance-arrow" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
   </button>
   <dialog id="assistance-dialog" class="assistance-dialog" aria-labelledby="assistance-dialog-title" aria-describedby="assistance-dialog-description">
     <div class="assistance-dialog-head">
       <span class="assistance-icon" aria-hidden="true">
         <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 4.9a2 2 0 0 1 3.4 0L21 17a2 2 0 0 1-1.7 3H4.7A2 2 0 0 1 3 17Z"/><path d="M12 9v4m0 3h.01"/></svg>
       </span>
       <button type="button" class="assistance-close" data-assistance-close aria-label="Close emergency report" autofocus>
         <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="m6 6 12 12M18 6 6 18"/></svg>
       </button>
     </div>
     <h2 id="assistance-dialog-title">Report an emergency</h2>
     <p id="assistance-dialog-description">Send a roadside assistance report for Trip #{{ $ride->ride_id }} to campus admin.</p>
     <form method="post" action="{{ route('assistance.trip.report', $ride->getKey()) }}">
       @csrf
       <fieldset>
         <legend>What happened?</legend>
         @foreach(\App\Models\TripAssistanceReport::REASONS as $value => $label)
           <label class="assistance-option"><input type="radio" name="reason" value="{{ $value }}" required> <span>{{ $label }}</span></label>
         @endforeach
       </fieldset>
       <p class="assistance-safety">If anyone is in immediate danger, contact emergency services directly.</p>
       <p class="assistance-feedback" data-assistance-feedback role="status"></p>
       <button class="assistance-submit" type="submit">Send report</button>
     </form>
   </dialog>
   @endif
   <p data-assistance-status role="status" tabindex="-1"></p>
 </section>
 @if($navigation['isDriver']&&!($navigation['testTrip']??false))@include('partials.driver-live-gps',['inlineGps'=>true,'activeRides'=>collect([$ride])])@endif
 <div class="navigation-metrics"><div><small id="navigationEstimateLabel">To next stop · estimate</small><b id="navigationEstimate">—</b></div><div><small id="navigationUpdatedLabel">Last GPS update</small><b id="navigationUpdated">—</b></div></div>
 <ol class="navigation-stops" id="navigationStops" aria-label="{{ $navigation['isDriver']?'Ordered journey stops':'Your journey' }}"></ol>
 @if($navigation['isDriver'])<button class="solid-button" type="button" id="navigationConfirm" hidden>Confirm stop</button>
 <form id="navigationComplete" action="{{ ($navigation['testTrip']??false)?url('trips/'.$ride->ride_id.'/test/finish'):url('driver.php') }}" method="post" hidden>@csrf
 @if($navigation['testTrip']??false)<input type="hidden" name="test_run_id" value="{{ $navigation['simulation']['runId'] }}">
 @else<input type="hidden" name="action" value="update_status"><input type="hidden" name="ride_id" value="{{ $ride->ride_id }}"><input type="hidden" name="next_status" value="completed">@endif
 <button class="solid-button" type="submit">Complete trip</button></form>
 @endif
 <p id="navigationActionStatus" role="status"></p>
 <p class="navigation-note">{{ $navigation['isDriver']?'Pickup and drop-off are confirmed by the driver. Tracking closes when the trip ends. Road times are estimates; shared pickups and traffic can add time.':'Your car follows the journey to your destination. This view closes when your driver completes the trip.' }}</p>
 </aside></div></div></main></div>
@php($navigationConfig=['state'=>$navigation,'route'=>$road,'testTrip'=>$navigation['testTrip']??false,'carIconUrl'=>asset('images/tracking-car-3d.png').'?v='.filemtime(public_path('images/tracking-car-3d.png')),'feedUrl'=>url('api/trips/'.$ride->ride_id.(($navigation['testTrip']??false)?'/test-navigation':'/navigation')),'advanceUrl'=>url('trips/'.$ride->ride_id.(($navigation['testTrip']??false)?'/test/stops':'/stops')),'returnUrl'=>url($navigation['isDriver']?'driver/schedule':'passenger/bookings'),'csrf'=>csrf_token()])
<script>window.tripNavigation=@json($navigationConfig);</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
<script src="{{ asset('js/trip-navigation-geometry.js') }}?v={{ is_file(public_path('js/trip-navigation-geometry.js'))?filemtime(public_path('js/trip-navigation-geometry.js')):'nav' }}" defer></script>
<script src="{{ asset('js/trip-navigation.js') }}?v={{ is_file(public_path('js/trip-navigation.js'))?filemtime(public_path('js/trip-navigation.js')):'nav' }}" defer></script>
<script src="{{ asset('js/trip-assistance.js') }}?v={{ filemtime(public_path('js/trip-assistance.js')) }}" defer></script>
@include('partials.device-tools')
</body></html>
