      <section class="content-card my-rides-card" id="my-rides">
        <div class="section-head">
          <div><span class="section-kicker">YOUR SCHEDULE</span><h2>{{ $section === 'history' ? 'Trip history' : 'My schedule' }}</h2></div>
          <span class="driver-count">{{ count($rides) }} total</span>
        </div>

        @if ($rides->isNotEmpty())
          <div class="driver-ride-list">
            @foreach ($rides as $ride)
              <article class="driver-ride" id="ride-{{ $ride->ride_id }}">
                <div class="driver-ride-date">
                  <b>{{ $ride->departure_at->format('d') }}</b>
                  <small>{{ $ride->departure_at->format('M') }}</small>
                </div>
                <div class="driver-ride-main">
                  <div class="driver-ride-route">
                    <b>{{ $ride->pickup_point }}</b>
                    <span>→</span>
                    <b>{{ $ride->destination }}</b>
                  </div>
                  @if (!empty($rideStopsByRide[$ride->ride_id]))
                    <div class="published-stop-list"><b>Pickup stops:</b> {{ implode(' · ', $rideStopsByRide[$ride->ride_id]) }}</div>
                  @endif
                  <div class="driver-ride-meta">
                    <span>{{ ($ride->ride_mode ?? 'scheduled') === 'now' ? 'Ride now' : $ride->departure_at->format('g:i A') }}</span>
                    <span>{{ (int) $ride->available_seats }} passenger seats left @if($ride->pool_seat_capacity) / {{ $ride->pool_seat_capacity }}@endif</span>
                    <span>RM {{ number_format((float) $ride->contribution_per_seat, 2) }} / seat</span>
                    <span>{{ $ride->vehicle_type ?: $ride->vehicle_description }}</span>
                    <span>{{ $ride->vehicle_color ?: 'Colour not set' }}</span>
                    <span>Plate: {{ $ride->vehicle_plate ?: 'Not set' }}</span>
                  </div>
                  @if ($ride->notes)
                    <p>{{ $ride->notes }}</p>
                  @endif
                </div>

                @if ($plan = $routePlans[$ride->ride_id] ?? null)
                  <div class="route-plan"><b>{{ $plan['algorithm'] === 'nearest_pickup' ? 'Nearest-first pickup order' : ($plan['algorithm'] === 'astar_stop_order' ? 'A* planned stop order' : 'Suggested stop order') }}</b><ol>@foreach ($plan['points'] as $point)<li>{{ $point['label'] }}@if(isset($point['passenger'])) · {{ $point['passenger'] }} ({{ $point['seats'] }} seats)@endif · ≈ {{ $point['elapsedMinutes'] }} min from departure</li>@endforeach</ol><small>{{ $plan['distanceKm'] }} km estimated · @if($plan['algorithm']==='nearest_pickup')Closest remaining pickup at each stop, based on pin distance. Everyone is picked up before the shared drop-off. @else Unoptimized stop order {{ $plan['baselineKm'] }} km. @endif Two-minute stop allowance included. Check road directions before travel.</small>
                  <a href="{{ url('driver/route/'.$ride->ride_id) }}" class="text-link">View road route & prediction →</a></div>
                @endif
                @if($ride->pool_seat_capacity && $ride->ride_status==='scheduled')<p class="pool-route-note">@if($ride->available_seats>0)<a href="{{ url('driver/requests') }}">Add passengers for this drop-off →</a><br>@endif Start pickups when ready, even if the car is not full. Starting closes the car to new requests.</p>@endif
                @if($ride->ride_status === 'completed')<a class="text-link" href="{{ url('carbon#carbon-trip-'.$ride->ride_id) }}">Record / view carbon estimate →</a>@endif
                <span class="ride-status {{ $ride->ride_status }}">{{ ucwords(str_replace('_', ' ', $ride->ride_status)) }}</span>

                <div class="ride-actions">
                  @if($ride->ride_status==='in_progress')<a class="ride-action-button" href="{{ url('trips/'.$ride->ride_id.'/map') }}">Open live journey map →</a>@endif
                  @if ($ride->ride_status === 'scheduled')
                    <form method="post" action="{{ url('driver.php') }}">
                      @csrf
                      <input type="hidden" name="action" value="update_status">
                      <input type="hidden" name="ride_id" value="{{ $ride->ride_id }}">
                      <input type="hidden" name="next_status" value="in_progress">
                      <button class="ride-action-button" type="submit">{{ $ride->pool_seat_capacity?'Start pickups':'Start trip' }}</button>
                      <small class="trip-gps-hint">Live GPS starts automatically during your trip.</small>
                    </form>
                    <form method="post" action="{{ url('driver.php') }}">
                      @csrf
                      <input type="hidden" name="action" value="update_status">
                      <input type="hidden" name="ride_id" value="{{ $ride->ride_id }}">
                      <input type="hidden" name="next_status" value="cancelled">
                      <button class="ride-action-button quiet-action" type="submit">Cancel</button>
                    </form>
                  @elseif ($ride->ride_status === 'in_progress')
                    <form method="post" action="{{ url('driver.php') }}">
                      @csrf
                      <input type="hidden" name="action" value="update_status">
                      <input type="hidden" name="ride_id" value="{{ $ride->ride_id }}">
                      <input type="hidden" name="next_status" value="completed">
                      <button class="ride-action-button" type="submit">Complete trip</button>
                    </form>
                  @endif
                </div>
              </article>
            @endforeach
          </div>
        @else
          <div class="driver-empty">
            <span>↗</span>
            <b>{{ $section === 'history' ? 'No past trips yet' : 'No scheduled trips yet' }}</b>
            <p>Publish a scheduled ride using the form above, and it will appear here for you to manage.</p>
            <a href="{{ url('driver/offer') }}">Create a ride <span>↑</span></a>
          </div>
        @endif
      </section>
