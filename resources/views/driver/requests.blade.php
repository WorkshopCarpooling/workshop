      <section class="content-card my-rides-card" id="seat-requests">
        <div class="section-head">
          <div><span class="section-kicker">SCHEDULED RIDES</span><h2>Scheduled seat requests</h2></div>
          <div class="driver-request-tools">
            <span class="driver-count">{{ $pendingRequestCount }} waiting</span>
            <a class="text-link" href="{{ url('driver.php#seat-requests') }}">Refresh</a>
          </div>
        </div>
        <p class="driver-intro">Only requests for rides published by this driver account appear here. Refresh after a passenger sends a request.</p>

        @if ($bookingRequests->isNotEmpty())
          <div class="driver-ride-list">
            @foreach ($bookingRequests as $request)
              <article class="driver-ride">
                <div class="driver-ride-date">
                  <b>{{ $request->ride->departure_at->format('d') }}</b>
                  <small>{{ $request->ride->departure_at->format('M') }}</small>
                </div>
                <div class="driver-ride-main">
                  <div class="driver-ride-route">
                    <b>{{ $request->ride->pickup_point }}</b>
                    <span>→</span>
                    <b>{{ $request->ride->destination }}</b>
                  </div>
                  <div class="driver-ride-meta">
                    <span>Passenger: {{ $request->passenger->full_name }}</span>
                    <span>{{ (int) $request->seats_requested }} seat(s)</span>
                    <span>{{ $request->ride->departure_at->format('g:i A') }}</span>
                    <span>Requested {{ $request->created_at->format('d M, g:i A') }}</span>
                  </div>

                  @php
                    $stopLabel = $request->pickupStop ? $request->pickupStop->stop_name : ($request->pickup_label ?: $request->ride->pickup_point);
                  @endphp
                  <div class="driver-request">
                    <span>
                      <b>Pickup:</b> {{ $request->pickup_label ?: $stopLabel }}<br>
                      <b>Drop-off:</b> {{ $request->dropoff_label ?: $request->ride->destination }}<br>
                      @if ($request->fare_per_seat !== null)
                        <b>Student contribution:</b> RM {{ number_format((float)$request->fare_per_seat, 2) }} / seat · RM {{ number_format((float)$request->fare_per_seat * $request->seats_requested, 2) }} total<br>
                        <b>Passenger journey:</b> {{ number_format($request->journey_distance_km, 1) }} km · {{ $request->distance_source === 'google_routes_api' ? 'road distance' : 'estimated distance' }}<br>
                      @endif
                      @if ($request->arrive_by)<b>Arrive by:</b> {{ $request->arrive_by->format('d M, g:i A') }}<br>@endif
                      @if ($request->compatibility_score !== null)<b>Compatibility:</b> {{ $request->compatibility_score }}%<br>@endif
                      @if ($request->pickup_latitude !== null && $request->dropoff_latitude !== null)
                        <a href="https://www.google.com/maps/dir/?api=1&amp;origin={{ $request->pickup_latitude }},{{ $request->pickup_longitude }}&amp;destination={{ $request->dropoff_latitude }},{{ $request->dropoff_longitude }}&amp;travelmode=driving" target="_blank" rel="noopener noreferrer">Open passenger driving directions ↗</a>
                      @endif
                    </span>
                  </div>
                </div>

                <span class="ride-status {{ $request->booking_status }}">{{ ucfirst($request->booking_status) }}</span>

                @if ($request->booking_status === 'pending' && $request->ride->ride_status === 'scheduled')
                  <div class="ride-actions">
                    <form method="post" action="{{ url('driver.php') }}">
                      @csrf
                      <input type="hidden" name="action" value="accept_booking">
                      <input type="hidden" name="booking_id" value="{{ $request->booking_id }}">
                      <button class="ride-action-button" type="submit">Accept</button>
                    </form>
                    <form method="post" action="{{ url('driver.php') }}">
                      @csrf
                      <input type="hidden" name="action" value="decline_booking">
                      <input type="hidden" name="booking_id" value="{{ $request->booking_id }}">
                      <button class="ride-action-button quiet-action" type="submit">Decline</button>
                    </form>
                  </div>
                @endif
              </article>
            @endforeach
          </div>
        @else
          <div class="driver-empty">
            <span>⌖</span>
            <b>No seat requests for your rides</b>
            <p>Requests appear here only when a passenger requests a ride published by this account. If the passenger sees “Pending,” make sure you are signed in as that ride’s driver and refresh.</p>
          </div>
        @endif
      </section>
