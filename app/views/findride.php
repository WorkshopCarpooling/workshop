<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#f7f8f5">
  <title>Find a ride Â· RideUTeM</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
  <link rel="stylesheet" href="css/dashboard.css">
  <link rel="stylesheet" href="css/find-ride.css">
  <link rel="stylesheet" href="css/driver.css">
  <link rel="stylesheet" href="css/pickup-map.css"><link rel="stylesheet" href="css/usability.css">
</head>
<body class="app-page find-ride-page">
<div class="app-shell">
  <?php $passengerActivePage = 'findride'; require dirname(__DIR__, 2) . '/includes/passenger_sidebar.php'; ?>

  <main class="main-area">
    <header class="topbar">
      <div class="breadcrumb"><a href="dashboard.php">Workspace</a><span>/</span>Find a ride</div>
      <div class="top-actions"><span class="find-preview-badge live-rides-badge"><i></i> LIVE RIDE LISTINGS</span><span class="top-divider"></span><a class="profile-chip" href="dashboard.php#profile"><span class="user-avatar">U</span><span><b><?= htmlspecialchars($currentUser['full_name'], ENT_QUOTES, 'UTF-8') ?></b><small><?= htmlspecialchars($passengerAccountLabel, ENT_QUOTES, 'UTF-8') ?></small></span><span class="chevron">âŒ„</span></a><form method="post" action="logout.php" class="logout-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($logoutToken, ENT_QUOTES, 'UTF-8') ?>"><button class="logout-button" type="submit">Log out</button></form></div>
    </header>

    <div class="page-content find-content">
      <?php if ($bookingNotice !== ''): ?><div class="driver-notice success" role="status"><?= htmlspecialchars($bookingNotice, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      <div class="welcome-row find-welcome">
        <div><div class="date-line"><span class="date-dot"></span> CAMPUS CARPOOL</div><h1>Find your next ride <span class="wave">âœ¦</span></h1><p class="subtitle">Browse a scheduled journey that fits your day.</p></div>
        <a class="outline-button" href="dashboard.php">â† Back to overview</a>
      </div>

      <section class="find-search-card" aria-labelledby="search-title">
        <div class="find-search-heading"><div><span class="section-kicker">PLAN A JOURNEY</span><h2 id="search-title">Where would you like to go?</h2><p>Choose your route, travel date, and earliest departure time.</p></div><span class="find-search-icon">âŒ•</span></div>
        <form class="find-search-form" id="rideSearchForm">
          <label><span>PICKUP POINT</span><input id="pickupArea" type="search" placeholder="Any pickup point" autocomplete="off"></label>
          <span class="find-route-arrow" aria-hidden="true">â†’</span>
          <label><span>DESTINATION</span><input id="rideDestination" type="search" placeholder="Any destination" autocomplete="off"></label>
          <label class="departure-field"><span>DEPARTURE DATE &amp; TIME</span><input id="travelDeparture" type="datetime-local" aria-label="Desired departure date and time"></label>
          <label class="seats-field"><span>PASSENGERS</span><select id="passengerCount"><option value="1">1 passenger</option><option value="2">2 passengers</option><option value="3">3 passengers</option></select></label>
          <button class="find-submit" type="submit">Search rides <span>â†’</span></button>
        </form>
        <section class="pickup-map-section" aria-labelledby="pickup-map-title">
          <div class="pickup-map-heading"><div><b id="pickup-map-title">Choose an exact pickup point</b><small>Search for a place or tap the map to place your pin.</small></div><div class="pickup-map-heading-actions"><button class="map-toggle" id="togglePickupMap" type="button" aria-expanded="false" aria-controls="pickupMapPanel">Open map <span>âŒ„</span></button></div></div>
          <div class="pickup-map-panel" id="pickupMapPanel" hidden>
            <div class="pickup-map-tools"><form id="pickupPlaceSearch" class="pickup-place-search"><label class="visually-hidden" for="pickupPlaceQuery">Search for a pickup place</label><input id="pickupPlaceQuery" type="search" placeholder="Search a place in Malaysia" autocomplete="off"><button type="submit">Search place</button></form><p id="pickupSearchStatus" class="pickup-search-status" role="status" aria-live="polite">Search for a place or tap the map to set your pickup point.</p><div id="pickupSearchResults" class="pickup-search-results" aria-live="polite"></div></div>
            <div class="pickup-map-wrap"><div id="pickupMap" class="pickup-map" role="application" aria-label="OpenStreetMap for choosing a pickup point"></div><div class="map-load-error" id="mapLoadError" hidden>Could not load the map. Check your internet connection and try again.</div></div>
            <div class="selected-pickup"><span class="selected-pickup-icon">âŒ–</span><span><b id="pickupPointLabel">No exact map point selected</b><small id="pickupCoordinates">Choose a place or tap the map to add a pin.</small></span><input type="hidden" id="pickupLatitude"><input type="hidden" id="pickupLongitude"></div>
          </div>
        </section>
        <div class="find-preview-note"><span>âœ“</span> These scheduled rides are published by approved UTeM drivers. A pickup pin is shared with the driver when you request a seat.</div>
      </section>

      <div class="find-results-layout">
        <section class="find-results-panel content-card" aria-labelledby="results-title">
          <div class="find-results-heading"><div><span class="section-kicker">RIDE OPTIONS</span><h2 id="results-title">Available rides</h2></div><span class="results-count" id="resultsCount">Loading ridesâ€¦</span></div>
          <div class="find-empty" id="findEmpty" hidden><span class="find-empty-icon">âŒ–</span><b>No upcoming rides yet</b><p>When a driver publishes a scheduled trip, it will be listed here.</p></div>
          <div class="find-ride-list" id="rideResults" aria-live="polite"></div>
        </section>

        <aside class="find-side-column">
          <section class="content-card find-tips-card"><div class="section-head"><div><span class="section-kicker">HOW IT WORKS</span><h2>Share the journey</h2></div><span class="find-tips-mark">âœ³</span></div>
            <div class="find-tip"><span>01</span><div><b>Choose a ride</b><p>Compare departure times, seats, and cost sharing.</p></div></div>
            <div class="find-tip"><span>02</span><div><b>Request a seat</b><p>Share an exact pickup pin with your request, if you need one.</p></div></div>
            <div class="find-tip"><span>03</span><div><b>Meet at pickup</b><p>The driver can preview your shared pickup pin in the app.</p></div></div>
          </section>
          <section class="content-card find-safety-card"><span class="find-safety-icon">âŒ‘</span><div><b>Ride with care</b><p>Check the route and driver details before arranging a journey. Live location is only for a confirmed, active ride.</p><a href="tracking.php">See tracking preview <span>â†’</span></a></div></section>
        </aside>
      </div>
      <footer class="dashboard-footer"><span>RideUTeM <i>Â·</i> Move together, thoughtfully.</span><span>Upcoming ride listings are loaded from the workshop database.</span></footer>
    </div>
  </main>
</div>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script>window.availableRides = <?= json_encode($availableRides, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>; window.bookingCsrf = <?= json_encode($bookingCsrf, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="js/find-ride.js" defer></script>
</body>
</html>
