@extends('layouts.workspace', ['title'=>$user->isAdmin() ? 'Carbon reports' : 'Carbon footprint', 'subtitle'=>'Automatic trip estimates. No extra passenger forms.', 'activePage'=>'carbon'])
@push('styles')<link rel="stylesheet" href="{{ asset('css/carbon-impact.css') }}?v={{ is_file(public_path('css/carbon-impact.css'))?filemtime(public_path('css/carbon-impact.css')):'footprint' }}">@endpush
@section('content')
<div class="carbon-page">
  <p><a class="solid-button" href="{{ url('carbon/rewards') }}">Carbon Rewards · points, all-time leaderboard & rewards →</a></p>
  @include('partials.carbon-impact')
  <section class="content-card footprint-explainer">
    <span class="carbon-section-label">CALCULATED AS YOU CARPOOL</span><h2>How your footprint is estimated</h2>
    <div class="footprint-steps"><p><b>1. Complete a trip</b><span>The route provides an estimated distance, including planned pickup detours.</span></p><p><b>2. Estimate the vehicle’s emissions</b><span>Distance in km × consumption in L/100 km ÷ 100 × fuel factor in kg CO₂e/L.</span></p><p><b>3. Share the footprint</b><span>Divide the trip estimate across the driver and accepted passenger seats.</span></p></div>
    <div class="footprint-example"><b>An example, not your trip data</b><p>A trip estimated at 4 kg CO₂e with one driver and three passengers is allocated as <strong>1 kg CO₂e per participant.</strong> This does not mean that 3 kg was saved.</p></div>
    @if($user->isDriver())<p>Save your fuel type and average consumption once in <a href="{{ url('driver/vehicles') }}">My vehicles</a>. Completing a trip saves its estimate automatically. Distance corrections below are optional.</p>@endif
    <details><summary>Calculation method and references</summary>
      <p><b>Fuel used (L) = distance (km) × consumption (L/100 km) ÷ 100.</b> Multiply the litres by the fuel factor to get estimated vehicle emissions in kg CO₂e. The fuel factors cover vehicle fuel combustion.</p>
      <p>Distances use a matching saved road quote or an estimated route through the planned stops. The fallback uses straight-line distance × 1.28. Drivers can enter the distance recorded by a trip meter after completing a trip. Trips without a saved report can show a preview based on the current vehicle profile.</p>
      <p>Each booked participant receives an equal share of the entire trip, regardless of where they board. A group booking receives one share per accepted seat. Older reports without a saved participant count use the currently accepted bookings. The admin total counts each vehicle trip once.</p>
      <p><b>Live map rate (g CO₂e/person/km) = consumption (L/100 km) ÷ 100 × fuel factor (kg CO₂e/L) × 1,000 ÷ people on board.</b> The driver counts as one person. Passenger seats count from confirmed pickup until confirmed drop-off. The green, yellow and red thresholds are display settings for this project.</p>
      @include('partials.carbon-method-references')
    </details>
  </section>
  @if(!$ready)<p class="carbon-pending">Carbon storage is not installed yet. Estimates will be available after the carbon schema is installed.</p>@endif
  @if($impact['totals']['trips'])
  @php
    $highEmissionsAbove = (float) config('carbon.live_map.medium_max_g_per_participant_km', 100);
  @endphp
  <section class="content-card footprint-results"><div class="impact-heading"><div><span class="carbon-section-label">{{ strtoupper($impact['label']) }}</span><h2>Completed trip estimates</h2><p>Trip emissions and their allocation across booked participants.</p></div></div>
    <div class="impact-table-scroll"><table><thead><tr><th>Completed trip</th><th>Distance<small>km</small></th><th>Participants</th><th>Trip footprint<small>kg CO₂e</small></th><th>Per participant<small>kg CO₂e</small></th>@if(!$user->isAdmin())<th>{{ $user->isPassenger()?'Your booking':'Your share' }}<small>kg CO₂e</small></th>@endif<th>Estimate</th></tr></thead><tbody>
      @foreach(array_slice($impact['rows'],0,50) as $row)
        @php
          $fp = $row['footprint'];
          $intensity = $fp['per_person_kg'] !== null ? round(1000 * $fp['per_person_kg'] / $fp['shared_km'], 1) : null;
        @endphp
        <tr>
          <td><a href="#carbon-trip-{{ $row['ride']->ride_id }}">#{{ $row['ride']->ride_id }}</a><small>{{ $row['ride']->completed_at->format('j M, g:i A') }}</small></td>
          <td>{{ $fp['shared_km']!==null?number_format($fp['shared_km'],1):'—' }}</td>
          <td>{{ $fp['occupants'] }}</td>
          <td>{{ $fp['shared_kg']!==null?number_format($fp['shared_kg'],2):'—' }}</td>
          <td>
            {{ $fp['per_person_kg']!==null?number_format($fp['per_person_kg'],2):'—' }}
            @if($intensity !== null)
              <small>{{ number_format($intensity, 1) }} g CO₂e/km</small>
              @if($intensity > $highEmissionsAbove)<span class="footprint-status footprint-high">High emissions</span>@endif
            @endif
          </td>
          @if(!$user->isAdmin())<td>{{ $fp['your_kg']!==null?number_format($fp['your_kg'],2):'—' }}</td>@endif
          <td><span class="footprint-status {{ $fp['status']==='awaiting_estimate'?'pending':'' }}">{{ ['saved_estimate'=>'Saved','current_profile_preview'=>'Preview','awaiting_estimate'=>'Awaiting data'][$fp['status']] }}</span></td>
        </tr>
      @endforeach
    </tbody></table></div>
    <p class="impact-footnote">High emissions means above {{ $highEmissionsAbove }} g CO₂e per participant per km, the cutoff used in this pilot.</p>
    @if($impact['totals']['trips']>50)<p class="impact-footnote">Showing the first 50 trips. The monthly summary and admin CSV include every completed trip in this month.</p>@endif
    @foreach(array_slice($impact['rows'],0,50) as $row)
      @php
        $ride=$row['ride']; $report=$row['report']; $fp=$row['footprint'];
        $details=$report?(json_decode($report->details,true)?:[]):[];
        $useOld=(int)old('carbon_ride',0)===(int)$ride->ride_id;
        $value=fn($key,$default=null)=>$useOld?old($key,$default):$default;
      @endphp
      <details class="footprint-trip" id="carbon-trip-{{ $ride->ride_id }}" @if($useOld) open @endif><summary>Trip #{{ $ride->ride_id }} · {{ $ride->pickup_point }} → {{ $ride->destination }}</summary>
        @if($report)
          <p>{{ $fp['occupants'] }} booked participants · {{ number_format($report->shared_kg,2) }} kg CO₂e for the vehicle · {{ number_format($fp['per_person_kg']??0,2) }} kg CO₂e allocated per participant.</p>
          <p>{{ ['odometer'=>'Driver-reported odometer distance','road_estimate'=>'Road-route distance estimate','planned_estimate'=>'Automatic planned-route approximation'][$report->distance_basis]??$report->distance_basis }} · {{ number_format($report->shared_km,1) }} km.</p>
          @if($report->preview??false)<p class="carbon-pending">Read-only preview using the current vehicle profile, not a saved historical measurement.</p>@endif
          <p>Fuel: {{ config('carbon.fuels.'.($details['fuel']??'').'.label',$details['fuel']??'Unknown') }} · {{ $details['litres_per_100km']??'—' }} L/100 km · {{ $details['fuel_factor']??'—' }} kg CO₂e/L.</p>
          @if(isset($details['distance_method']))<p>{{ $details['distance_method'] }}</p>@endif
          <p class="impact-footnote">Method {{ $report->method }} · {{ $details['source']??'' }} · {{ $fp['occupancy_source']==='saved_booking_snapshot'?'Occupancy saved with this estimate.':'Occupancy based on currently accepted bookings.' }}</p>
        @else<p class="carbon-pending">Waiting for a saved vehicle fuel profile and usable journey coordinates. @if($user->isDriver())<a href="{{ url('driver/vehicles') }}">Set up your vehicle once</a>, or enter a distance below.@endif</p>@endif
        @if($user->isDriver() && $ready)
          <details @if($useOld) open @endif><summary>Optional: correct trip distance or fuel details</summary>
            <form method="post" action="{{ url('carbon/rides/'.$ride->ride_id.'/report') }}">@csrf<input type="hidden" name="carbon_ride" value="{{ $ride->ride_id }}">
              <div class="carbon-form-grid">
                <label>Total vehicle distance (km)<input name="shared_km" type="number" min="0.001" max="500" step="0.001" value="{{ $value('shared_km',$report->shared_km??'') }}" required></label>
                <label>Distance source<select name="distance_basis" required><option value="">Choose source</option>@foreach(['planned_estimate'=>'Automatic planned-route approximation','odometer'=>'Odometer / trip meter','road_estimate'=>'Road-route estimate'] as $key=>$label)<option value="{{ $key }}" @selected($value('distance_basis',$report->distance_basis??'')===$key)>{{ $label }}</option>@endforeach</select></label>
                <label>Vehicle fuel<select name="fuel_type" required><option value="">Choose fuel</option>@foreach(config('carbon.fuels') as $fuel=>$entry)<option value="{{ $fuel }}" @selected($value('fuel_type',$details['fuel']??$ride->vehicle->fuel_type??'')===$fuel)>{{ $entry['label'] }}</option>@endforeach</select></label>
                <label>Consumption (L/100 km)<input name="fuel_l_per_100km" type="number" min="0.1" max="50" step="0.1" value="{{ $value('fuel_l_per_100km',$details['litres_per_100km']??$ride->vehicle->fuel_l_per_100km??'') }}" required></label>
              </div><p class="impact-footnote">Enter the whole vehicle journey, including pickup detours.</p><button class="solid-button" type="submit">Save footprint estimate</button>
            </form>
          </details>
        @endif
      </details>
    @endforeach
  </section>
  @endif
</div>
@endsection
