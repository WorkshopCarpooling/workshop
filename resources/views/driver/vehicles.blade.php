      <section class="content-card offer-card vehicle-card" id="my-vehicles">
        <div class="section-head">
          <div><span class="section-kicker">DRIVER VERIFICATION</span><h2>My vehicles</h2></div>
          <span class="driver-count">{{ count($approvedVehicles) }} approved</span>
        </div>
        <p class="driver-intro">Submit your vehicle details once. An administrator must approve a vehicle before you can use it for a ride.</p>

        @if ($vehicles->isNotEmpty())
          @php($selectedVehicle = $vehicles->firstWhere('vehicle_id', (int)request('vehicle')) ?? $vehicles->first())
          <div class="vehicle-picker" data-vehicle-picker>
            <label for="driverVehicleSelect">Your vehicle applications</label>
            <select id="driverVehicleSelect" aria-controls="driverVehicleDetails">
              @foreach($vehicles as $vehicle)
                <option value="{{ $vehicle->vehicle_id }}" @selected($selectedVehicle->vehicle_id === $vehicle->vehicle_id)>{{ $vehicle->vehicle_type }} · {{ $vehicle->vehicle_plate }} · {{ ucfirst($vehicle->verification_status) }}</option>
              @endforeach
            </select>
            <p class="vehicle-picker-help">Choose a vehicle to see its application status and fuel profile.</p>
            <noscript><p>Open a vehicle: @foreach($vehicles as $vehicle)<a href="{{ url('driver/vehicles?vehicle='.$vehicle->vehicle_id) }}">{{ $vehicle->vehicle_plate }} · {{ ucfirst($vehicle->verification_status) }}</a> @endforeach</p></noscript>
          <div class="vehicle-review-list" id="driverVehicleDetails">
            @foreach ($vehicles as $vehicle)
              <article class="vehicle-review-row" data-vehicle-panel="{{ $vehicle->vehicle_id }}" @if($selectedVehicle->vehicle_id !== $vehicle->vehicle_id) hidden @endif>
                <div>
                  <b>{{ $vehicle->vehicle_type }}</b>
                  <small>{{ $vehicle->vehicle_color }} · Plate {{ $vehicle->vehicle_plate }}</small>
                  @if ($vehicle->admin_note)
                    <small class="vehicle-admin-note">Admin note: {{ $vehicle->admin_note }}</small>
                  @endif
                </div>
                <span class="ride-status {{ $vehicle->verification_status }}">{{ ucfirst($vehicle->verification_status) }}</span>
                <p class="vehicle-status-help">{{ ['approved'=>'Approved — you can select this vehicle when offering a ride.','pending'=>'Application submitted — waiting for administrator approval.','rejected'=>'Application rejected — review the administrator’s note before applying again.'][$vehicle->verification_status] ?? 'Review your vehicle application status.' }}</p>
                @if(\App\Services\CarbonEstimate::ready())
                  <details class="vehicle-fuel-details" @if(old('action') === null && $errors->any()) open @endif>
                    <summary>{{ $vehicle->fuel_type && $vehicle->fuel_l_per_100km ? 'Fuel profile · '.config('carbon.fuels.'.$vehicle->fuel_type.'.label',$vehicle->fuel_type).' · '.number_format($vehicle->fuel_l_per_100km,1).' L/100 km' : 'Set up automatic carbon calculation' }} <span aria-hidden="true">⌄</span></summary>
                  <form class="vehicle-fuel-form" method="post" action="{{ url('driver/vehicles/'.$vehicle->vehicle_id.'/fuel') }}">@csrf
                    <b>Automatic carbon calculation — set up once</b>
                    <div class="carbon-form-grid">
                      <label>Fuel type<select name="fuel_type" required><option value="">Choose fuel</option>@foreach(config('carbon.fuels') as $fuel=>$entry)<option value="{{ $fuel }}" @selected($vehicle->fuel_type === $fuel)>{{ $entry['label'] }}</option>@endforeach</select></label>
                      <label>Consumption (L/100 km)<input name="fuel_l_per_100km" type="number" min="0.1" max="50" step="0.1" value="{{ $vehicle->fuel_l_per_100km ?? '' }}" placeholder="e.g. 7.0" required></label>
                    </div>
                    <small>Save this once and completed trips will use it automatically. Use a trip-computer average or your own fuel records.</small>
                    <button class="ride-action-button" type="submit">Save fuel profile</button>
                  </form>
                  </details>
                @endif
              </article>
            @endforeach
          </div>
          </div>
        @else
          <p class="vehicle-picker-help">No vehicle applications yet. Add your first vehicle below.</p>
        @endif

        <details class="vehicle-application" @if($vehicles->isEmpty() || old('action') === 'submit_vehicle') open @endif>
          <summary>{{ $vehicles->isEmpty() ? 'Apply for your first vehicle' : 'Apply for another vehicle' }} <span aria-hidden="true">＋</span></summary>
        <form method="post" action="{{ url('driver.php') }}" class="driver-form vehicle-submit-form">
          @csrf
          <input type="hidden" name="action" value="submit_vehicle">
          <div class="driver-form-grid">
            <label class="driver-field"><span>Car type / model</span><input name="vehicle_type" type="text" maxlength="80" placeholder="e.g. Perodua Myvi" required></label>
            <label class="driver-field"><span>Vehicle colour</span><input name="vehicle_color" type="text" maxlength="60" placeholder="e.g. Silver" required></label>
            <label class="driver-field"><span>Vehicle number plate</span><input name="vehicle_plate" type="text" maxlength="20" autocomplete="off" placeholder="e.g. ABC 1234" required></label>
            @if(\App\Services\CarbonEstimate::ready())
              <label class="driver-field"><span>Fuel type (optional)</span><select name="fuel_type"><option value="">Unknown / other — no estimate yet</option>@foreach(config('carbon.fuels') as $fuel=>$entry)<option value="{{ $fuel }}">{{ $entry['label'] }}</option>@endforeach</select></label>
              <label class="driver-field"><span>Fuel consumption (L/100 km)</span><input name="fuel_l_per_100km" type="number" min="0.1" max="50" step="0.1" placeholder="e.g. 7.0"><small>Required when a supported fuel is selected. 7.0 is an example, not a default.</small></label>
            @endif
          </div>
          <div class="driver-form-bottom">
            <span><b>Admin review</b> Your vehicle is not available for ride publishing until approved.</span>
            <button class="solid-button" type="submit">Submit vehicle <span>→</span></button>
          </div>
        </form>
        </details>
      </section>
