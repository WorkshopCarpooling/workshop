      <section class="content-card live-tracking-card" id="live-drivers" data-map-module-url="{{ asset('vendor/maplibre/maplibre-gl.mjs') }}" data-auto-follow="true" data-feed-url="{{ url('api/admin/driver-locations') }}" data-route-url="{{ url('api/admin/rides') }}" data-car-icon-url="{{ asset('images/tracking-car-3d.png') }}?v={{ filemtime(public_path('images/tracking-car-3d.png')) }}">
        <div class="section-head">
          <div><span class="section-kicker">UTeM STUDENT JOURNEYS</span><h2>Live driver map</h2></div>
          <span class="tracking-pill" id="live-driver-count">Connecting…</span>
        </div>
        <p class="tracking-intro">Watch drivers move along their routes. Car colours show estimated CO₂e per person on board per kilometre.</p>
        <a class="text-link" href="{{ url('admin/live/preview') }}">Open passenger, driver & admin live preview →</a>
        <div class="tracking-toolbar"><p id="tracking-feed-status" role="status">Loading active trips…</p><div class="tracking-map-actions"><button type="button" class="outline-button" id="tracking-angle" aria-pressed="false" disabled>Angled view</button><button type="button" class="outline-button" id="tracking-fit">Show all cars</button></div></div>
        <div class="tracking-layout">
          <div class="tracking-map-wrap">
            <div id="admin-driver-map" role="region" aria-label="UTeM live driver locations"></div>
            <div class="tracking-map-legend live-carbon-legend"><span><i class="carbon-low"></i> Green ≤{{ config('carbon.live_map.low_max_g_per_participant_km',60) }}</span><span><i class="carbon-medium"></i> Yellow &gt;{{ config('carbon.live_map.low_max_g_per_participant_km',60) }}–{{ config('carbon.live_map.medium_max_g_per_participant_km',100) }}</span><span><i class="carbon-high"></i> Red &gt;{{ config('carbon.live_map.medium_max_g_per_participant_km',100) }}</span><span><i class="carbon-unknown"></i> Estimate unavailable</span><small>Estimated g CO₂e / person / km</small></div>
          </div>
          <div class="tracking-driver-list" id="tracking-driver-list" aria-label="Active driver trips"></div>
        </div>
        <p class="tracking-route-status" id="tracking-route-status" role="status">Select a driver to view their planned route.</p>
        <p class="tracking-note">Updates every 5 seconds. Automatic trips appear after Start trip; GPS trips appear after the first device location fix. Simulation is labelled separately from GPS. “Last known” means updates have stopped. Completed trips disappear automatically.</p>
        <p class="tracking-note live-carbon-note">The live rate is shared by the driver and passengers currently on board. It updates after each confirmed pickup or drop-off. Missing fuel data stays grey. <a href="{{ url('carbon/rewards') }}">Carbon Rewards</a> compares the trip with confirmed travel alternatives.</p>
        <dialog id="tracking-carbon-dialog" aria-labelledby="tracking-carbon-title">
          <div class="live-carbon-dialog-head"><div><span class="section-kicker">ESTIMATED CARBON FOOTPRINT</span><h2 id="tracking-carbon-title">Carbon calculation</h2></div><button type="button" class="outline-button" id="tracking-carbon-close">Tutup</button></div>
          <div id="tracking-carbon-steps" aria-live="polite"></div>
          <details class="live-carbon-note"><summary>Calculation references</summary>@include('partials.carbon-method-references')</details>
          <p class="live-carbon-note">These figures describe allocated emissions, not verified savings. Trip distances are planned estimates, not recorded GPS travel history. <a href="{{ url('carbon') }}">Open completed-trip carbon reports ↗</a></p>
        </dialog>
        <noscript>Enable JavaScript to view live driver locations.</noscript>
      </section>

    
