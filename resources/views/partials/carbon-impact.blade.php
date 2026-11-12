@php $totals=$impact['totals']; @endphp
<section class="content-card impact-month" aria-labelledby="impactTitle">
  <div class="impact-heading"><div><span class="carbon-section-label">SHARE THE JOURNEY. UNDERSTAND THE FOOTPRINT.</span><h2 id="impactTitle">Monthly carbon footprint</h2><p>Completed trips in {{ $impact['label'] }} · Malaysia time (GMT+8)</p></div>
    <form method="get" action="{{ url('carbon') }}" class="impact-month-form"><label for="carbonMonth">Report month<input id="carbonMonth" name="month" type="month" value="{{ $impact['month'] }}" required></label><button class="outline-button" type="submit">View month</button></form>
  </div>
  <div class="impact-metrics">
    <article><small>COMPLETED RIDES</small><strong>{{ $totals['trips'] }}</strong><span>{{ $user->isAdmin()?'Across the community':'Trips you drove or joined' }}</span></article>
    <article><small>PASSENGER SEATS SHARED</small><strong>{{ $totals['accepted_seats'] }}</strong><span>Accepted seats on completed rides; repeat journeys count again</span></article>
    <article><small>AVERAGE BOOKED OCCUPANCY</small><strong>{{ $totals['occupancy']!==null?number_format($totals['occupancy'],1):'—' }}<em>{{ $totals['occupancy']!==null?'people / car':'' }}</em></strong><span>Driver + accepted passenger seats per ride</span></article>
    <article><small>ESTIMATED VEHICLE DISTANCE</small><strong>{{ $totals['emission_trips']?number_format($totals['shared_km'],1):'—' }}<em>{{ $totals['emission_trips']?'km':'' }}</em></strong><span>Whole vehicle routes, including planned pickup detours</span></article>
    <article><small>ESTIMATED TRIP EMISSIONS</small><strong>{{ $totals['emission_trips']?number_format($totals['shared_kg'],2):'—' }}<em>{{ $totals['emission_trips']?'kg CO₂e':'' }}</em></strong><span>{{ $user->isAdmin()?'Each vehicle journey counted once':'Whole vehicles on the trips you drove or joined' }}</span></article>
    @if($user->isAdmin())
    <article><small>AVERAGE PER PARTICIPANT-TRIP</small><strong>{{ $totals['kg_per_participant']!==null?number_format($totals['kg_per_participant'],2):'—' }}<em>{{ $totals['kg_per_participant']!==null?'kg CO₂e':'' }}</em></strong><span>Estimated emissions ÷ booked participants on trips with estimates</span></article>
    @else
    <article><small>{{ $user->isPassenger()?'YOUR BOOKING’S ALLOCATED FOOTPRINT':'YOUR ALLOCATED FOOTPRINT' }}</small><strong>{{ $totals['your_estimate_trips']?number_format($totals['your_kg'],2):'—' }}<em>{{ $totals['your_estimate_trips']?'kg CO₂e':'' }}</em></strong><span>{{ $user->isPassenger()?'Includes every seat in your booking':'One driver’s share per trip' }} · {{ $totals['your_estimate_trips'] }} estimates</span></article>
    @endif
  </div>
  <div class="impact-coverage"><div><b>{{ $totals['emission_trips'] }} of {{ $totals['trips'] }} completed rides have an estimate</b><p>{{ $totals['saved_reports'] }} saved reports · {{ $totals['preview_reports'] }} previews using current vehicle profiles · {{ $totals['missing_reports'] }} awaiting fuel or route information.</p></div><progress value="{{ $totals['emission_trips'] }}" max="{{ max(1,$totals['trips']) }}" aria-label="Completed rides with footprint estimates"></progress></div>
  <p class="impact-footnote">No extra passenger questions. Occupancy comes from bookings, rather than verified boarding. Each trip’s emissions are divided equally across the driver and accepted passenger seats for the whole route, even when pickup or drop-off points differ. These figures describe an estimated footprint, not verified emissions savings.</p>
  @if($user->isAdmin() && $ready)<div class="impact-downloads"><a class="solid-button impact-export" href="{{ url('carbon/export/monthly?month='.$impact['month']) }}">Download monthly footprint CSV →</a><a class="text-link" href="{{ url('carbon/export') }}">All completed trips CSV ↗</a></div>@endif
  @if(!$totals['trips'])<p class="impact-empty">No completed rides this month. Estimates appear as journeys finish; scheduled and cancelled rides are excluded.</p>@endif
</section>
