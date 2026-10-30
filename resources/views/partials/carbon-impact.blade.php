@php($totals=$impact['totals'])
<section class="content-card impact-month" aria-labelledby="impactTitle">
  <div class="impact-heading"><div><span class="carbon-section-label">SHARED JOURNEYS, MEASURABLE IMPACT</span><h2 id="impactTitle">Monthly transport impact</h2><p>Completed trips in {{ $impact['label'] }} · Malaysia time (GMT+8)</p></div>
    <form method="get" action="{{ url('carbon') }}" class="impact-month-form"><label for="carbonMonth">Report month<input id="carbonMonth" name="month" type="month" value="{{ $impact['month'] }}" required></label><button class="outline-button" type="submit">View month</button></form>
  </div>
  <div class="impact-metrics">
    <article><small>COMPLETED RIDES</small><strong>{{ $totals['trips'] }}</strong><span>Cancelled and scheduled trips excluded</span></article>
    <article><small>NET CO₂e AVOIDED</small><strong>{{ $totals['comparison_trips']?number_format($totals['avoided_kg'],2):'—' }}<em>{{ $totals['comparison_trips']?'kg':'' }}</em></strong><span>{{ $totals['comparison_trips'] }} complete trip comparisons</span></article>
    <article><small>NET FUEL SAVED</small><strong>{{ $totals['comparison_trips']?number_format($totals['saved_litres'],2):'—' }}<em>{{ $totals['comparison_trips']?'L':'' }}</em></strong><span>Separate journeys minus the shared route</span></article>
    <article><small>ESTIMATED FUEL-COST SAVING</small><strong>{{ $totals['cost_trips']?'RM '.number_format($totals['saved_rm'],2):'—' }}</strong><span>{{ $totals['cost_trips'] }} comparisons with all fuel prices available</span></article>
    <article><small>AVERAGE BOOKED OCCUPANCY</small><strong>{{ $totals['occupancy']!==null?number_format($totals['occupancy'],1):'—' }}</strong><span>Driver + accepted passenger seats per completed ride</span></article>
    <article><small>SHARED-CAR EMISSIONS</small><strong>{{ $totals['emission_trips']?number_format($totals['shared_kg'],2):'—' }}<em>{{ $totals['emission_trips']?'kg':'' }}</em></strong><span>{{ $totals['emission_trips'] }} saved reports · {{ number_format($totals['shared_litres'],2) }} L estimated fuel used</span></article>
  </div>
  <div class="impact-coverage"><div><b>{{ $totals['comparison_trips'] }} of {{ $totals['trips'] }} rides have a complete comparison</b><p>Incomplete or unsupported travel alternatives stay out of savings totals. Negative savings mean the shared trip used more fuel, cost or emissions than the stated alternatives.</p></div><progress value="{{ $totals['comparison_trips'] }}" max="{{ max(1,$totals['trips']) }}" aria-label="Completed rides with complete comparisons"></progress></div>
  <div class="impact-quality"><span>Distance sources</span><b>{{ $totals['quality']['road_estimate'] }} road estimates</b><b>{{ $totals['quality']['planned_estimate'] }} planned approximations</b><b>{{ $totals['quality']['odometer'] }} driver-reported odometers</b><b>{{ $totals['missing_reports'] }} awaiting a report</b></div>
  <p class="impact-footnote">Monthly totals use saved reports only, once per completed trip. Fuel-cost estimates compare fuel expenditure using this month’s recorded prices; passenger payments are not counted as fuel savings. Prices may be updated, and each exported estimate includes the price revision used.</p>
  @if($user->isAdmin() && $impact['ready'])<a class="solid-button impact-export" href="{{ url('carbon/export/monthly?month='.$impact['month']) }}">Download monthly impact CSV →</a>@endif
  @if(!$totals['trips'])<p class="impact-empty">No completed rides this month. As real journeys finish, their saved estimates will appear here.</p>@endif
  @if($totals['trips'])
  <details class="impact-trip-list"><summary>See this month’s trip figures · {{ $totals['trips'] }} rides</summary>
    <div class="impact-table-scroll"><table><thead><tr><th>Completed trip</th><th>CO₂e avoided</th><th>Fuel saved</th><th>Fuel-cost saving</th><th>Comparison</th></tr></thead><tbody>
    @foreach(array_slice($impact['rows'],0,50) as $row)
      @php($metric=$row['metrics'])
      <tr><td>#{{ $row['ride']->ride_id }}<small>{{ $row['ride']->completed_at->format('j M, g:i A') }}</small></td><td>{{ ($metric['comparison_complete']??false)?number_format($row['report']->avoided_kg,2).' kg':'—' }}</td><td>{{ ($metric['saved_litres']??null)!==null?number_format($metric['saved_litres'],2).' L':'—' }}</td><td>{{ ($metric['saved_rm']??null)!==null?'RM '.number_format($metric['saved_rm'],2):'—' }}</td><td>{{ !$row['report']?'Awaiting an estimate':($row['report']->needs_review?'Needs review':(($metric['comparison_complete']??false)?'Complete':'Comparison incomplete')) }}</td></tr>
    @endforeach
    </tbody></table></div>
    @if($totals['trips']>50)<p class="impact-footnote">Showing the first 50 trips. The administrator’s CSV includes every completed trip in this month.</p>@endif
  </details>
  @endif
</section>
@if($impact['ready'])
  @if(!$user->isAdmin())
  <section class="content-card impact-preferences"><h2>Save your usual travel once</h2><p>Use your typical alternative for future trips, then change an individual trip below whenever your plans differ. Existing trip answers stay as saved.</p>
    <form method="post" action="{{ url('carbon/preferences') }}">@csrf
      @if($user->isPassenger())
        <div data-carbon-baseline>
          <label>Without this carpool, how would your group usually travel?<select name="baseline_mode" data-baseline-mode required>@foreach(config('carbon.modes') as $key=>$label)<option value="{{ $key }}" @selected(($carbonPreference->baseline_mode??'unknown')===$key)>{{ $label }}</option>@endforeach</select></label>
          <div data-baseline-car class="carbon-form-grid">
            <label>Separate cars your group would use<input type="number" name="baseline_cars" min="1" max="3" value="{{ $carbonPreference->baseline_cars??1 }}"></label>
            <label>Alternative car fuel<select name="baseline_fuel_type"><option value="">Choose fuel</option>@foreach(config('carbon.fuels') as $key=>$entry)<option value="{{ $key }}" @selected(($carbonPreference->baseline_fuel_type??'')===$key)>{{ $entry['label'] }}</option>@endforeach</select></label>
            <label>Alternative consumption (L/100 km)<input name="baseline_fuel_l_per_100km" type="number" min="0.1" max="50" step="0.1" value="{{ $carbonPreference->baseline_fuel_l_per_100km??'' }}" placeholder="e.g. 7.0"></label>
          </div>
        </div><p class="impact-footnote">Count separate cars, not passengers. The distance comes from each new booking. Motorcycle, bus and other shared-ride alternatives remain incomplete until supported by a suitable calculation.</p>
      @else
        <label>Without passengers, would you usually make this drive?<select name="driver_would_travel" required><option value="unknown" @selected(($carbonPreference->driver_would_travel??'unknown')==='unknown')>Ask me for each trip</option><option value="yes" @selected(($carbonPreference->driver_would_travel??'')==='yes')>Yes, I would drive anyway</option><option value="no" @selected(($carbonPreference->driver_would_travel??'')==='no')>No, I would not make the journey</option></select></label>
        <p class="impact-footnote">New automatic completion reports can use this answer. Review and correct a completed trip below when its circumstances differ. Save the vehicle’s fuel type and consumption in <a href="{{ url('driver/vehicles') }}">My vehicles</a>.</p>
      @endif
      <label class="impact-consent"><input name="remember_consent" type="checkbox" value="1" required> I want future trips to reuse this usual travel choice unless I change it.</label>
      <button class="solid-button" type="submit">Save usual travel</button>
    </form>
  </section>
  @endif
  <section class="content-card impact-prices"><h2>Fuel-price assumptions · {{ $impact['label'] }}</h2>
    <p>RM estimates use administrator-entered prices for this report month. An empty price stays unavailable.</p>
    @if($user->isAdmin())
      <form method="post" action="{{ url('carbon/prices') }}">@csrf<input type="hidden" name="month" value="{{ $impact['month'] }}">
        <div class="impact-price-grid">@foreach(config('carbon.fuels') as $key=>$entry)<label>{{ $entry['label'] }} (RM/L)<input type="number" name="prices[{{ $key }}]" min="0.01" max="1000" step="0.0001" value="{{ $impact['prices'][$key]->price_per_litre??'' }}" placeholder="Not provided"></label>@endforeach</div>
        <label>Source or pricing assumption<input type="text" name="reference" maxlength="180" required placeholder="e.g. receipt date, published price source, or pilot assumption"></label>
        <p class="impact-footnote">Save the rate that applies to your pilot. Clearing a field removes that fuel’s price from current calculations. Previous revisions remain recorded.</p>
        <button class="solid-button" type="submit">Save monthly fuel prices</button>
      </form>
    @endif
    <div class="impact-price-notes">@forelse($impact['prices'] as $key=>$price)@if($price->price_per_litre!==null)<p><b>{{ config('carbon.fuels.'.$key.'.label',$key) }}: RM {{ number_format($price->price_per_litre,4) }}/L</b><span>{{ $price->reference }} · recorded {{ $price->created_at }}</span></p>@endif @empty<p>No monthly prices recorded yet. Fuel and emissions can still be reported.</p>@endforelse</div>
  </section>
@else<p class="carbon-pending">Monthly fuel-cost settings and saved travel choices are temporarily unavailable.</p>@endif
