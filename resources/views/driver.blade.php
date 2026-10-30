<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#244b39">
  <title>{{ $pageTitle }} · RideUTeM</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
  <link rel="stylesheet" href="{{ asset('css/driver.css') }}">
  <link rel="stylesheet" href="{{ asset('css/usability.css') }}">
  <link rel="stylesheet" href="{{ asset('css/interface-refresh.css') }}">
  <link rel="stylesheet" href="{{ asset('css/pickup-map.css') }}">
  <link rel="stylesheet" href="{{ asset('css/maplibre.css') }}">
  <link rel="stylesheet" href="{{ asset('css/campus-interface.css') }}">
  <link rel="stylesheet" href="{{ asset('css/journey.css') }}">
  <link rel="stylesheet" href="{{ asset('css/live-tracking.css') }}">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="{{ asset('css/workspace-pages.css') }}?v=20261027-pages-carbon">
@include('partials.device-head')
  @include('partials.theme-head')
  <link rel="stylesheet" href="{{ asset('css/ride-now.css') }}?v={{ filemtime(public_path('css/ride-now.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/shared-find-ride.css') }}?v={{ is_file(public_path('css/shared-find-ride.css'))?filemtime(public_path('css/shared-find-ride.css')):'pool' }}">
</head>
<body class="app-page driver-page">
<div class="app-shell">
  @include('partials.passenger_sidebar', ['passengerActivePage' => 'driver'])

  <main class="main-area">
    <header class="topbar">
      <div class="breadcrumb">Driver space <span>/</span> {{ $pageTitle }}</div>
    </header>

    <div class="page-content driver-content">
      @if ($errors->any())<div class="validation-errors" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
      <div class="welcome-row">
        <div>
          <div class="date-line"><span class="date-dot"></span> YOUR DRIVER SPACE</div>
          <h1>{{ $pageTitle }}</h1>
          <p class="subtitle">Pick up a passenger now, or share a planned trip to campus.</p>
        </div>
        <a class="outline-button" href="{{ url('driver/offer') }}">＋ Offer a ride</a>
      </div>

      @if ($pendingRequestCount > 0 && $section === 'overview')
        <a class="driver-pending-banner" href="{{ url('driver/requests') }}">
          <b>{{ $pendingRequestCount }} passenger request{{ $pendingRequestCount === 1 ? '' : 's' }} waiting</b>
          <span>Review and respond to your seat requests →</span>
        </a>
      @endif

      @if ($notice !== '')
        <div class="driver-notice {{ $noticeType }}" role="status">{{ $notice }}</div>
      @endif

      @if(in_array($section,['overview','requests'])) @include('partials.driver-now-requests') @endif

      @if($section === 'overview')
        @include('partials.driver-home-seat-requests')
        @include('partials.journey-hero', ['journeyMode' => true])
@include('partials.workspace-overview', ['workspaceRole'=>'driver'])
      @elseif(in_array($section,['schedule','history']))
        @include('driver.trips')
      @else
        @include('driver.'.$section)
      @endif
    <footer class="dashboard-footer">
        <span>RideUTeM <i>·</i> Driver workspace</span>
        <span>Accept requests that fit your planned journey.</span>
      </footer>
    </div>
  </main>
</div>
<script>
  window.googleMapsApiKey = @json(config('services.google_maps.api_key', ''));
  window.routesComputeUrl = @json(url('api/routes/compute'));
  window.bookingCsrf = @json(csrf_token());
  window.locationSearchUrl = @json(url('api/locations/search'));
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
<script src="{{ asset('js/maplibre-loader.js') }}" defer></script>
<script src="{{ asset('js/journey-picker.js') }}?v={{ filemtime(public_path('js/journey-picker.js')) }}" defer></script>
@include('partials.driver-live-gps')
<script src="{{ asset('js/carbon-forms.js') }}?v=20261027-carbon" defer></script>
@include('partials.device-tools')
</body>
</html>
