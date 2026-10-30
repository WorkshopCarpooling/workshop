      <section class="content-card live-tracking-card" id="live-drivers" data-feed-url="{{ url('api/admin/driver-locations') }}" data-car-icon-url="{{ asset('images/tracking-car-pixel.svg') }}?v={{ filemtime(public_path('images/tracking-car-pixel.svg')) }}">
        <div class="section-head">
          <div><span class="section-kicker">UTeM STUDENT JOURNEYS</span><h2>Live driver map</h2></div>
          <span class="tracking-pill" id="live-driver-count">Connecting…</span>
        </div>
        <p class="tracking-intro">Pixel cars show the driver's GPS latitude and longitude during active student trips. Select a car to follow its location.</p>
        <div class="tracking-toolbar"><p id="tracking-feed-status" role="status">Loading active trips…</p><button type="button" class="outline-button" id="tracking-fit">Show all cars</button></div>
        <div class="tracking-layout">
          <div class="tracking-map-wrap">
            <div id="admin-driver-map" role="region" aria-label="UTeM live driver locations"></div>
            <div class="tracking-map-legend"><span><i class="gps-live-dot"></i> Live GPS</span><span><i class="gps-wait-dot"></i> Last known location</span></div>
          </div>
          <div class="tracking-driver-list" id="tracking-driver-list" aria-label="Active driver trips"></div>
        </div>
        <p class="tracking-note">Updates every 5 seconds. A car appears after the first device GPS fix. “Last known” means updates have stopped. Movement animates between received coordinates; completed trips disappear automatically.</p>
        <noscript>Enable JavaScript to view live driver locations.</noscript>
      </section>
