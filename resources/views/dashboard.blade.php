@php
  $section = $section ?? 'overview';
  $pageTitle = $pageTitle ?? 'Overview';
@endphp
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
  <link rel="stylesheet" href="{{ asset('css/campus-interface.css') }}">
  <link rel="stylesheet" href="{{ asset('css/journey.css') }}">
<link rel="stylesheet" href="{{ asset('css/workspace-pages.css') }}?v=20261027-pages-carbon">
@include('partials.device-head')
  @if($section === 'overview')<link rel="stylesheet" href="{{ asset('css/passenger-overview.css') }}?v={{ filemtime(public_path('css/passenger-overview.css')) }}">@endif
  @include('partials.theme-head')

</head>
<body class="app-page passenger-dashboard {{ $section === 'overview' ? 'passenger-overview' : '' }}">
<div class="app-shell">
  @if($section === 'overview')
    @include('partials.app-nav')
  @else
    @include('partials.passenger_sidebar', ['passengerActivePage' => $section])
  @endif
  <main class="main-area">
    <header class="topbar">
      <div class="breadcrumb">Workspace <span>/</span> {{ $pageTitle }}</div>
    </header>
    <div class="page-content">
      @if(($rideRequest['status'] ?? '') === 'pending')
        <div class="driver-notice" role="status"><b>Finding a driver for your ride now.</b> {{ $rideRequest['pickup'] }} → {{ $rideRequest['dropoff'] }}. <a href="{{ url('findride.php') }}">View or cancel request →</a></div>
      @endif
      @if($section === 'overview')
        @include('partials.passenger-overview')
      @else
      <div class="welcome-row">
        <div>
          <div class="date-line"><span class="date-dot"></span> YOUR CAMPUS CARPOOL</div>
          <h1>{{ $pageTitle }}</h1>
          <p class="subtitle">Follow your ride requests and confirmed journeys here.</p>
        </div>
      </div>
      <div class="content-grid passenger-bookings-grid">
        <section class="content-card rides-card" id="upcoming">
          <div class="section-head">
            <div><span class="section-kicker">BOOKING STATUS</span><h2>{{ $pageTitle }}</h2></div>
            <a class="text-link" href="{{ url('findride.php') }}">Browse available rides <span>→</span></a>
          </div>
          @if ($myBookings->isNotEmpty())
            <div class="driver-ride-list">
              @foreach ($myBookings as $booking)
                <article class="driver-ride">
                  <div class="driver-ride-main">
                    <div class="driver-ride-route">
                      <b>{{ $booking->pickup_label ?: $booking->ride->pickup_point }}</b>
                      <span>→</span>
                      <b>{{ $booking->dropoff_label ?: $booking->ride->destination }}</b>
                    </div>
                    <div class="driver-ride-meta">
                      <span>{{ ($booking->ride->ride_mode ?? 'scheduled') === 'now' ? 'Ride now · '.$booking->created_at->format('d M, g:i A') : $booking->ride->departure_at->format('d M Y, g:i A') }}</span>
                      <span>Driver: {{ $booking->ride->driver->full_name }}</span>
                      <span>{{ (int) $booking->seats_requested }} seat(s)</span>
                      <span>RM {{ number_format((float) ($booking->fare_per_seat ?? $booking->ride->contribution_per_seat), 2) }} / seat · RM {{ number_format((float) ($booking->fare_per_seat ?? $booking->ride->contribution_per_seat) * $booking->seats_requested, 2) }} total</span>
                      @if ($booking->journey_distance_km !== null)<span>{{ number_format($booking->journey_distance_km, 1) }} km · {{ $booking->distance_source === 'google_routes_api' ? 'road distance' : 'estimated distance' }}</span>@endif
                    </div>
                  </div>
                  <span class="ride-status {{ $booking->booking_status }}">{{ ucfirst($booking->booking_status) }}</span>
                  @if($booking->booking_status==='accepted'&&$booking->ride->ride_status==='in_progress')<a class="text-link" href="{{ url('trips/'.$booking->ride_id.'/map') }}">View live journey map →</a>@endif
                </article>
              @endforeach
            </div>
          @else
            <div class="empty-state">
              <span class="empty-illustration">⌖</span>
              <b>No bookings yet</b>
              <p>When you request a seat, your request status will appear here.</p>
              <a class="empty-action" href="{{ url('findride.php') }}">Find a ride now <span>→</span></a>
            </div>
          @endif
        </section>
      </div>
      @endif
      <footer class="dashboard-footer">
        <span>RideUTeM <i>·</i> Move together, thoughtfully.</span>
        <span>Your seat requests and driver decisions appear here.</span>
      </footer>
    </div>
  </main>
</div>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script src="{{ asset('js/dashboard.js') }}" defer></script>
@include('partials.device-tools')
</body>
</html>
