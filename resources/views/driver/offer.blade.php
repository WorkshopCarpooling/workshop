      <section class="content-card offer-card" id="offer-ride">
        <div class="section-head">
          <div><span class="section-kicker">PLAN AHEAD</span><h2>Offer a scheduled ride</h2></div>
          <span class="driver-step">01 <i>/</i> 02</span>
        </div>
        <p class="driver-intro">Search your start and destination, or pin them on the map. Passenger requests include their own pickup and drop-off for your review.</p>

        @if ($approvedVehicles->isEmpty())
          <div class="driver-notice vehicle-required" id="vehicle-approval-notice">
            <b>Vehicle approval required</b>
            <p>You can choose your locations and preview the map below. Publishing a ride requires an administrator-approved vehicle.</p>
            <a href="#my-vehicles">Go to my vehicles <span>↑</span></a>
          </div>
        @endif
          <form method="post" action="{{ url('driver.php') }}" class="driver-form">
            @csrf
            <input type="hidden" name="action" value="create_ride">
            <div class="driver-form-grid">
              @include('partials.journey-picker')
              <label class="driver-field"><span>Departure date and time</span><input name="departure_at" type="datetime-local" required></label>
              <label class="driver-field">
                <span>Available passenger seats</span>
                <select name="available_seats" required>
                  <option value="" selected disabled>Select seats</option>
                  @for ($seat = 1; $seat <= 6; $seat++)
                    <option value="{{ $seat }}">{{ $seat }} {{ $seat === 1 ? 'seat' : 'seats' }}</option>
                  @endfor
                </select>
              </label>
              <label class="driver-field"><span>Maximum contribution per seat (RM)</span><input name="contribution_per_seat" type="number" min="0" max="9999.99" step="0.01" value="0.00" required><small>Student fares are calculated from each passenger’s journey, up to this amount. Enter 0 for a free ride. Review the quoted amount before accepting a request.</small></label>
              <label class="driver-field">
                <span>Approved vehicle</span>
                <select name="vehicle_id" required @disabled($approvedVehicles->isEmpty())>
                  <option value="" selected disabled>{{ $approvedVehicles->isEmpty() ? 'Waiting for vehicle approval' : 'Select vehicle' }}</option>
                  @foreach ($approvedVehicles as $vehicle)
                    <option value="{{ $vehicle->vehicle_id }}">{{ $vehicle->vehicle_type }} · {{ $vehicle->vehicle_color }} · {{ $vehicle->vehicle_plate }}</option>
                  @endforeach
                </select>
              </label>
              <label class="driver-field driver-field-wide"><span>Trip notes <small>Optional</small></span><textarea name="notes" maxlength="500" rows="3" placeholder="Anything passengers should know about the pickup"></textarea></label>
            </div>
            <div class="driver-form-bottom">
              <span><b>Safety reminder</b> Review each passenger’s locations and arrival deadline before accepting.</span>
              <button class="solid-button" type="submit" @disabled($approvedVehicles->isEmpty()) @if($approvedVehicles->isEmpty()) aria-describedby="vehicle-approval-notice" @endif>Publish ride <span>→</span></button>
            </div>
          </form>
      </section>
