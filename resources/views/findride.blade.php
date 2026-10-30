<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Find a ride · RideUTeM</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
  @foreach (['dashboard','driver','find-ride','usability','interface-refresh','campus-interface','journey'] as $style)
    <link rel="stylesheet" href="{{ asset('css/'.$style.'.css') }}">
  @endforeach
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  @include('partials.device-head')
  <link rel="stylesheet" href="{{ asset('css/student-journey.css') }}">
  @include('partials.theme-head')
  <link rel="stylesheet" href="{{ asset('css/ride-now.css') }}?v={{ filemtime(public_path('css/ride-now.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/shared-find-ride.css') }}?v={{ is_file(public_path('css/shared-find-ride.css'))?filemtime(public_path('css/shared-find-ride.css')):'pool' }}">
</head>
<body class="app-page find-ride-page ride-now-page">
<div class="app-shell">
  @include('partials.passenger_sidebar', ['passengerActivePage'=>'findride'])
  <main class="main-area">
    <header class="topbar"><div class="breadcrumb">Workspace <span>/</span> Find a ride</div></header>
    <div class="page-content">
      <div class="welcome-row" id="rideNowIntro"><div><div class="date-line"><span class="date-dot"></span> LET’S GET YOU THERE</div><h1>Where to?</h1><p class="subtitle">Set your pickup and drop-off. Share a car with students going to the same destination. Pickups follow the nearest-stop order from your driver’s starting point.</p></div><a class="outline-button" href="{{ url('passenger/schedule') }}">◷ Schedule for later</a></div>
      @if(session('bookingNotice'))<div class="driver-notice" role="status">{{ session('bookingNotice') }}</div>@endif
      @if($errors->any())<div class="validation-errors" role="alert">{{ $errors->first() }}</div>@endif
      <section class="content-card now-request-card" id="rideRequestPanel" hidden aria-labelledby="rideRequestTitle">
        <div class="ride-waiting-visual" id="rideSearchAnimation" hidden>
          <div class="ride-waiting-scene" aria-hidden="true">@include('partials.ride-search-animation')</div>
          <p class="ride-animation-caption"><i aria-hidden="true"></i> Waiting for acceptance</p>
        </div>
        <div class="ride-request-content">
          <span class="now-kicker" id="rideRequestEyebrow">YOUR RIDE</span>
          <div class="ride-request-update" role="status" aria-live="polite" aria-atomic="true"><h2 id="rideRequestTitle" tabindex="-1">Finding a driver</h2><p id="rideRequestMessage"></p></div>
          <div class="ride-request-journey"><span class="ride-journey-line" aria-hidden="true"><i></i><b></b></span><dl><div><dt>Pickup</dt><dd id="rideWaitingPickup"></dd></div><div><dt>Drop-off</dt><dd id="rideWaitingDropoff"></dd></div></dl></div>
          <p id="rideRequestRoute" class="ride-now-sr-only"></p>
          <div class="ride-request-price"><span>Your contribution</span><p id="rideRequestFare"></p></div>
          <p id="rideRequestDriver" class="ride-request-driver" hidden></p>
          <p id="rideSharedSummary" class="pool-passenger-summary" hidden></p>
          <a class="solid-button" id="rideNavigationLink" hidden>View live journey map →</a>
          <div class="now-request-actions"><button class="outline-button ride-cancel-button" type="button" id="cancelRideRequest" hidden>Cancel find ride</button><a class="solid-button" id="rideRequestBooking" href="{{ url('passenger/bookings') }}" hidden>View booking →</a></div>
          <p class="ride-waiting-hint" id="rideWaitingHint" hidden>You can cancel while no driver has accepted.</p>
        </div>
      </section>
      <section class="content-card now-search-card" id="rideNowSearch">
        <div class="now-card-head"><div><span class="now-kicker">PICKUP & DROP-OFF</span><h2>Your journey</h2></div><span class="now-badge"><i></i> Ride now</span></div>
        <form id="rideNowForm" method="get" action="{{ url('findride.php') }}">
          @include('partials.journey-picker', ['autoPickup'=>true])
          <div class="now-search-actions"><label for="nowPassengers">Passengers<select id="nowPassengers" name="seats_requested">@for($i=1;$i<=3;$i++)<option value="{{ $i }}" @selected((int)old('seats_requested',1)===$i)>{{ $i }} {{ $i===1 ? 'passenger' : 'passengers' }}</option>@endfor</select></label><button class="solid-button find-submit" type="submit" id="findDriversButton">Search for a ride →</button></div>
        </form>
        <p class="journey-status" id="nowSearchStatus" role="status" tabindex="-1">Choose both locations and search to send your request straight to campus drivers.</p>
        <noscript><p>Enable JavaScript to choose locations and request a ride.</p></noscript>
      </section>
      <p class="now-help" id="rideNowLater">Going later? Choose your date and time in <a href="{{ url('passenger/schedule') }}">Schedule</a>.</p>
      <footer class="dashboard-footer">RideUTeM · Your campus, better connected.</footer>
    </div>
  </main>
</div>
@php
  $rideNowConfig = ['searchUrl'=>url('findride.php'),'requestUrl'=>url('findride.php'),'statusUrl'=>url('api/passenger/ride-request'),'cancelBase'=>url('passenger/ride-requests'),'csrf'=>csrf_token(),'ready'=>$bookingReady ?? false,'request'=>$rideRequest ?? null];
@endphp
<script>
window.rideNow = @json($rideNowConfig);
window.bookingCsrf = @json(csrf_token());
window.googleMapsApiKey = @json(config('services.google_maps.api_key', ''));
window.routesComputeUrl = @json(url('api/routes/compute'));
window.locationSearchUrl = @json(url('api/locations/search'));
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
<script src="{{ asset('js/maplibre-loader.js') }}" defer></script>
<script src="{{ asset('js/journey-picker.js') }}?v={{ filemtime(public_path('js/journey-picker.js')) }}" defer></script>
<script src="{{ asset('js/ride-now.js') }}?v={{ filemtime(public_path('js/ride-now.js')) }}" defer></script>
@include('partials.device-tools')
</body>
</html>
