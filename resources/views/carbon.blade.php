@extends('layouts.workspace', ['title'=>$user->isAdmin() ? 'Carbon reports' : 'Carbon impact', 'subtitle'=>'Track fuel, commuting costs and carbon impact from completed trips.', 'activePage'=>'carbon'])
@push('styles')<link rel="stylesheet" href="{{ asset('css/carbon-impact.css') }}?v={{ is_file(public_path('css/carbon-impact.css'))?filemtime(public_path('css/carbon-impact.css')):'pilot' }}">@endpush
@section('content')
<div class="carbon-page">
  @include('partials.carbon-impact')
  <p class="carbon-section-label">ALL-TIME TRIP ESTIMATES</p>
  <div class="workspace-stats">
    <article><small>COMPLETED COMPARISONS</small><strong>{{ $summary['count'] }}</strong><small>One record per trip; incomplete or changed baselines excluded.</small></article>
    <article><small>ESTIMATED NET CO₂e AVOIDED</small><strong>{{ $summary['count'] ? number_format($summary['avoided'],2).' kg' : '—' }}</strong><small>Negative values mean more emissions than the stated alternative.</small></article>
    <article><small>SHARED-CAR EMISSIONS</small><strong>{{ $summary['emissions_count'] ? number_format($summary['emissions_total'],2).' kg' : '—' }}</strong><small>{{ $summary['emissions_count'] }} completed-trip estimates, including previews of older trips.</small></article>
  </div>
  @if($user->isPassenger())<p class="journey-status">These are whole-trip results for rides you joined, not individual carbon credits. The same trip is counted once in the administrator’s total.</p>@endif
  <section class="content-card">
    <h2>How the estimate works</h2>
    <p><b>kg CO₂e = kilometres × litres per 100 km ÷ 100 × fuel factor.</b> Avoided emissions compare the driver’s original trip plus passengers’ stated alternative journeys with the entire shared route, including detours.</p>
    <p>Fuel factors come from <a href="{{ config('carbon.source_url') }}" target="_blank" rel="noopener noreferrer">{{ config('carbon.source_title') }}</a>. Petrol: 2.288 kg CO₂e/L. The method covers vehicle fuel combustion; it excludes fuel production and vehicle manufacture. Automatic distances prefer a saved road-route quote for matching direct trips, or a road-route estimate for the planned stop order when the configured provider is available. Otherwise they use straight-line kilometres × 1.28, including estimated detours. They are estimates, not measured GPS or road distances. Fuel consumption comes from the saved vehicle profile.</p>
    @if($user->isDriver())<p><b>No trip-by-trip entry needed:</b> save fuel type and average consumption once in <a href="{{ url('driver/vehicles') }}">My vehicles</a>. Carbon estimates are saved when you complete a trip. Older trips show automatic previews. You can optionally correct a distance or confirm whether you would have driven anyway to complete the savings comparison.</p>@endif
    <details><summary>Example: four cars become one shared ride</summary><p>If four people would each drive 10 km in petrol cars using 7 L/100 km, separate travel produces 6.41 kg CO₂e. A 12 km shared trip produces 1.92 kg. The estimated difference is 4.48 kg CO₂e. These are example assumptions, not results from your trips.</p></details>
    @if($user->isAdmin() && $ready)<a class="text-link" href="{{ url('carbon/export') }}">Download completed-trip carbon CSV →</a>@endif
  </section>
  @if(!$ready)
    <p class="carbon-pending">Carbon storage is not installed yet. An administrator must run the carbon schema installer before estimates can be saved.</p>
  @else
    @if($bookings->isNotEmpty())
      <section class="content-card"><h2>Your alternative travel</h2><p>For each booking, tell us what your group would have done without this carpool. A seat does not automatically mean one avoided car.</p>
      @foreach($bookings as $booking)
        <details><summary>Trip #{{ $booking->ride_id }} · {{ $booking->pickup_label ?: $booking->ride->pickup_point }} → {{ $booking->dropoff_label ?: $booking->ride->destination }}</summary>
          <p>{{ $booking->seats_requested }} booked seat(s) · {{ $booking->journey_distance_km !== null ? number_format($booking->journey_distance_km,1).' km' : 'Distance unavailable' }} · {{ $booking->distance_source === 'google_routes_api' ? 'Road-route distance estimate' : 'Approximate / legacy distance' }}</p>
          <form method="post" action="{{ url('carbon/bookings/'.$booking->booking_id.'/baseline') }}">@csrf
            @include('partials.carbon-baseline-fields',['baselineBooking'=>$booking])
            <button class="solid-button" type="submit">Save alternative travel</button>
          </form>
        </details>
      @endforeach
      </section>
    @endif
    <section class="content-card"><h2>Completed trip results</h2><p><small>Showing up to 100 recent completed trips. Saved totals cover all your completed trips; older-trip previews cover the trips shown here. Passenger alternative-travel changes update saved comparisons automatically.</small></p>
    @forelse($rides as $ride)
      @php
        $report = $reports[$ride->ride_id] ?? null;
        $details = $report ? json_decode($report->details,true) : [];
        $useOld = (int)old('carbon_ride',0) === (int)$ride->ride_id;
        $value = fn($key,$default=null) => $useOld ? old($key,$default) : $default;
      @endphp
      <article class="carbon-report" id="carbon-trip-{{ $ride->ride_id }}">
        <h3>Trip #{{ $ride->ride_id }} · {{ $ride->pickup_point }} → {{ $ride->destination }}</h3>
        <p>{{ $ride->departure_at->format('d M Y, g:i A') }} · Driver: {{ $ride->driver->full_name }} · Completed</p>
        @if($report)
          <div class="carbon-result {{ $report->avoided_kg !== null && $report->avoided_kg < 0 ? 'negative' : '' }}">
            Shared car: <b>{{ number_format($report->shared_kg,2) }} kg CO₂e</b> · {{ number_format($report->shared_km,1) }} km · {{ ['odometer'=>'Driver-reported odometer distance','road_estimate'=>'Driver-reported road-route estimate','planned_estimate'=>'Automatic planned-route approximation'][$report->distance_basis] ?? $report->distance_basis }}
            @if($report->preview ?? false)<p>Automatic preview of an older completed trip. Uses the current vehicle profile; it is not a saved historical measurement.</p>@endif
            @if($report->needs_review)<p class="carbon-pending">The saved method or fuel factors changed. This comparison is excluded from savings totals until the driver saves a new estimate.</p>
            @elseif($report->avoided_kg !== null)<br>Separate journeys: {{ number_format($report->baseline_kg,2) }} kg CO₂e · <b>{{ $report->avoided_kg < 0 ? 'Additional emissions' : 'Estimated avoided emissions' }}: {{ number_format(abs($report->avoided_kg),2) }} kg CO₂e</b>
            @else<p class="carbon-pending">Fuel emissions are estimated. Savings stay unavailable until the driver’s alternative and every passenger’s alternative travel and distance are known. Unknown journeys are not counted as avoided cars.</p>@endif
          </div>
          <details><summary>Calculation inputs and source</summary>
            <p>Fuel: {{ config('carbon.fuels.'.($details['fuel'] ?? '').'.label', $details['fuel'] ?? 'Unknown') }} · {{ $details['litres_per_100km'] ?? '—' }} L/100 km · {{ $details['fuel_factor'] ?? '—' }} kg CO₂e/L. Driver’s alternative: {{ ['yes'=>'would drive anyway','no'=>'would not travel','unknown'=>'not confirmed'][$details['driver_would_travel'] ?? 'unknown'] ?? 'not confirmed' }}.</p>
            @if(isset($details['distance_method']))<p>{{ $details['distance_method'] }}</p>@endif
            <p>Method {{ $report->method }} · {{ $details['source'] ?? '' }} · Saved {{ $report->updated_at }}. Passenger distances use their reported alternative distance or saved fare-quote distance, which may be approximate. Unsupported transport modes stay incomplete rather than being treated as an avoided car.</p>
          </details>
        @else<p class="carbon-pending">Automatic estimate is waiting for a saved vehicle fuel profile and usable pickup/drop-off coordinates for the driver and accepted passengers. @if($user->isDriver())<a href="{{ url('driver/vehicles') }}">Set up your vehicle once</a>, or add a measured distance below for an older trip.@endif</p>@endif
        @if($user->isDriver())
          <details @if($useOld) open @endif><summary>{{ $report ? 'Optional: confirm savings or correct this estimate' : 'Optional: add details for this older trip' }}</summary>
            <form method="post" action="{{ url('carbon/rides/'.$ride->ride_id.'/report') }}" data-driver-baseline>@csrf
              <input type="hidden" name="carbon_ride" value="{{ $ride->ride_id }}">
              <div class="carbon-form-grid">
                <label>Total shared-route distance (km)<input name="shared_km" type="number" min="0.001" max="500" step="0.001" value="{{ $value('shared_km',$report->shared_km ?? '') }}" required></label>
                <label>Distance source<select name="distance_basis" required><option value="">Choose source</option><option value="planned_estimate" @selected($value('distance_basis',$report->distance_basis ?? '') === 'planned_estimate')>Automatic planned-route approximation</option><option value="odometer" @selected($value('distance_basis',$report->distance_basis ?? '') === 'odometer')>Odometer / trip meter</option><option value="road_estimate" @selected($value('distance_basis',$report->distance_basis ?? '') === 'road_estimate')>Road-route estimate</option></select></label>
                <label>Trip vehicle fuel<select name="fuel_type" required><option value="">Choose fuel</option>@foreach(config('carbon.fuels') as $fuel=>$entry)<option value="{{ $fuel }}" @selected($value('fuel_type',$details['fuel'] ?? $ride->vehicle->fuel_type ?? '') === $fuel)>{{ $entry['label'] }}</option>@endforeach</select></label>
                <label>Vehicle consumption (L/100 km)<input name="fuel_l_per_100km" type="number" min="0.1" max="50" step="0.1" value="{{ $value('fuel_l_per_100km',$details['litres_per_100km'] ?? $ride->vehicle->fuel_l_per_100km ?? '') }}" placeholder="e.g. 7.0" required></label>
                <label>Would you drive this trip without passengers?<select name="driver_would_travel" required><option value="">Choose an answer</option><option value="yes" @selected($value('driver_would_travel',$details['driver_would_travel'] ?? '') === 'yes')>Yes, I would drive anyway</option><option value="no" @selected($value('driver_would_travel',$details['driver_would_travel'] ?? '') === 'no')>No, I would not make this trip</option></select></label>
                <label>Your original route without pickup detours (km)<input name="driver_baseline_km" type="number" min="0.001" max="500" step="0.001" value="{{ $value('driver_baseline_km',$details['suggested_driver_km'] ?? $report->driver_baseline_km ?? '') }}"></label>
              </div>
              <small>Enter the whole vehicle journey once, including all pickup detours. Do not add individual passenger kilometres to the shared-car distance. Fuel use is estimated from the consumption you report.</small>
              <button class="solid-button" type="submit">Save carbon estimate</button>
            </form>
          </details>
        @endif
      </article>
    @empty<p>No completed trips in this view yet. Scheduled or cancelled trips do not count as carbon savings.</p>@endforelse
    </section>
  @endif
</div>
@endsection
