<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#244b39">
  <title>Live tracking preview · RideUTeM</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
  <link rel="stylesheet" href="{{ asset('css/tracking.css') }}">
  <link rel="stylesheet" href="{{ asset('css/usability.css') }}">
  <link rel="stylesheet" href="{{ asset('css/interface-refresh.css') }}">
  <link rel="stylesheet" href="{{ asset('css/campus-interface.css') }}">
  <link rel="stylesheet" href="{{ asset('css/journey.css') }}">
@include('partials.device-head')
  @include('partials.theme-head')
</head>
<body class="app-page tracking-page">
<div class="app-shell">
  @include('partials.passenger_sidebar', ['passengerActivePage' => 'tracking'])
  <main class="main-area">
    <header class="topbar">
      <div class="breadcrumb"><a href="{{ url('dashboard.php') }}">Workspace</a><span>/</span>Live tracking</div>
    </header>
    <div class="page-content tracking-content">
      <div class="welcome-row">
        <div>
          <div class="date-line"><span class="date-dot"></span> TRIP LOCATION INTERFACE</div>
          <h1>Live trip tracking <span class="wave">⌖</span></h1>
          <p class="subtitle">A driver shares location only while a confirmed journey is active.</p>
        </div>
        <a class="outline-button tracking-back" href="{{ url('dashboard.php') }}">← Back to dashboard</a>
      </div>
      <div class="simulation-banner">
        <span>i</span>
        <p><b>Interface preview only.</b> The moving car is simulated. This page does not request your device location, connect to GPS, send updates, or store trip data.</p>
      </div>
      <div class="tracking-grid">
        <section class="map-card">
          <div class="map-card-head">
            <div><span class="section-kicker">SCHEDULED ROUTE</span><h2>Pickup point <span>→</span> UTeM Main Campus</h2></div>
            <span class="trip-state" id="tripState"><i></i> NOT STARTED</span>
          </div>
          <div class="map-canvas" id="mapCanvas">
            <div class="map-label map-label-a">Pickup point</div>
            <div class="map-label map-label-b">UTeM Main Campus</div>
            <div class="map-label map-label-road road-one">Campus Link Road</div>
            <div class="map-label map-label-road road-two">Main Access Road</div>
            <div class="map-block block-a"></div><div class="map-block block-b"></div><div class="map-block block-c"></div><div class="map-block block-d"></div><div class="map-block block-e"></div><div class="map-block block-f"></div>
            <svg class="route-map" viewBox="0 0 900 510" preserveAspectRatio="none" aria-label="Schematic route map from a designated pickup point to UTeM Main Campus">
              <path class="road-underlay" d="M105 393 C180 370 214 318 290 326 S400 390 475 335 S558 230 645 252 S740 240 793 126"/>
              <path class="route-base" d="M105 393 C180 370 214 318 290 326 S400 390 475 335 S558 230 645 252 S740 240 793 126"/>
              <path class="route-progress" id="routeProgress" d="M105 393 C180 370 214 318 290 326 S400 390 475 335 S558 230 645 252 S740 240 793 126" pathLength="100"/>
              <g class="stop-marker pickup-marker"><circle cx="105" cy="393" r="13"/><circle cx="105" cy="393" r="4"/></g>
              <g class="stop-marker campus-marker"><circle cx="793" cy="126" r="13"/><circle cx="793" cy="126" r="4"/></g>
              <g class="car-marker" id="carMarker" transform="translate(105 393)" opacity="0">
                <circle class="car-halo" r="21"/>
                <circle class="car-dot" r="12"/>
                <path d="M-6 1-4-4l3-1 2-3 3 3 2 1 2 4-1 3h-12l-1-3Z"/>
                <circle class="wheel" cx="-4" cy="4" r="1.4"/>
                <circle class="wheel" cx="4" cy="4" r="1.4"/>
              </g>
            </svg>
            <div class="map-scale"><span></span> Route preview · not to scale</div>
            <div class="map-zoom"><button type="button" aria-label="Zoom in">+</button><button type="button" aria-label="Zoom out">−</button></div>
            <div class="map-watermark">SCHEMATIC MAP</div>
          </div>
          <div class="map-legend">
            <span><i class="legend-driver"></i> Simulated driver location</span>
            <span><i class="legend-pickup"></i> Pickup point</span>
            <span><i class="legend-campus"></i> Destination</span>
          </div>
        </section>
        <aside class="tracking-side">
          <section class="content-card trip-status-card">
            <div class="tracking-side-head">
              <div><span class="section-kicker">JOURNEY STATUS</span><h2 id="statusTitle">Waiting for trip to start</h2></div>
              <span class="status-icon">⌖</span>
            </div>
            <p class="status-copy" id="statusCopy">When implemented, location sharing starts when the driver begins a confirmed ride.</p>
            <div class="status-divider"></div>
            <div class="tracking-metric"><span>Estimated arrival</span><b id="etaValue">—</b></div>
            <div class="tracking-metric"><span>Last location update</span><b id="updateValue">—</b></div>
            <div class="tracking-metric"><span>Route progress</span><b id="progressValue">—</b></div>
            <div class="tracking-actions">
              <button class="solid-button" id="startPreview">Start movement preview <span>→</span></button>
              <button class="outline-button" id="endPreview" disabled>End preview</button>
            </div>
            <div class="tracking-footnote"><span>ⓘ</span> Preview movement is generated in this browser only.</div>
          </section>
          <section class="content-card privacy-card">
            <span class="privacy-lock">⌑</span>
            <div>
              <b>Location access rules</b>
              <p>For the real feature, only confirmed ride participants should see the driver’s location. Stop sharing when the journey ends.</p>
            </div>
          </section>
        </aside>
      </div>
      <section class="route-steps-card">
        <div class="route-steps-heading">
          <div><span class="section-kicker">TRIP FLOW</span><h2>How location sharing should work</h2></div>
          <span class="flow-caption">Planned interface behavior</span>
        </div>
        <div class="route-steps">
          <article><span class="step-number">01</span><b>Driver starts the trip</b><p>Sharing begins after the driver starts an accepted ride.</p></article>
          <article><span class="step-number">02</span><b>Passengers follow progress</b><p>Confirmed passengers see the route and last update time.</p></article>
          <article><span class="step-number">03</span><b>Sharing ends with the ride</b><p>Location access stops when the driver ends the journey.</p></article>
        </div>
      </section>
      <footer class="dashboard-footer">
        <span>RideUTeM <i>·</i> Live tracking interface</span>
        <span>No GPS, server, account, or trip data is connected.</span>
      </footer>
    </div>
  </main>
</div>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script src="{{ asset('js/tracking.js') }}" defer></script>
@include('partials.device-tools')
</body>
</html>
