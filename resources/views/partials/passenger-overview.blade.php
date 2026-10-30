@php
  $overview = $overview ?? \App\Services\PassengerOverview::fromBookings($myBookings);
  $nextBooking = $overview['nextBooking'];
  $nextRide = $nextBooking?->ride;
  $firstName = explode(' ', trim($currentUser->full_name))[0] ?: 'there';
  $bookingLabels = ['pending'=>'Awaiting approval','accepted'=>'Confirmed','declined'=>'Declined','cancelled'=>'Cancelled'];
@endphp
<div class="overview-intro">
  <div><p class="overview-eyebrow"><i aria-hidden="true"></i> YOUR DAILY OVERVIEW</p><h1>Good to see you, {{ $firstName }}<span>.</span></h1><p class="overview-subtitle">A calmer commute. A little more connected.</p></div>
  <div class="overview-intro-actions"><span class="overview-date">@include('partials.overview-icon',['icon'=>'calendar']) {{ now()->format('D, j M') }}</span><a class="overview-primary" href="{{ url('findride.php') }}">Find a ride @include('partials.overview-icon',['icon'=>'arrow'])</a></div>
</div>

<section class="overview-metrics" aria-label="Upcoming journey summary">
  <article class="overview-metric"><span class="metric-icon">@include('partials.overview-icon',['icon'=>'route'])</span><div><span class="metric-label">Upcoming journeys</span><strong>{{ $overview['upcomingCount'] }}</strong><small>Scheduled or in progress</small></div></article>
  <article class="overview-metric"><span class="metric-icon metric-confirmed">@include('partials.overview-icon',['icon'=>'check'])</span><div><span class="metric-label">Confirmed</span><strong>{{ $overview['confirmedCount'] }}</strong><small>Accepted by your driver</small></div></article>
  <article class="overview-metric"><span class="metric-icon metric-pending">@include('partials.overview-icon',['icon'=>'clock'])</span><div><span class="metric-label">Awaiting approval</span><strong>{{ $overview['pendingCount'] }}</strong><small>Waiting for driver confirmation</small></div></article>
</section>

<div class="overview-grid">
  <div class="overview-main-column">
    <section class="overview-card next-journey" aria-labelledby="next-journey-title">
      <div class="overview-card-head"><div><span class="overview-section-label">COMING UP</span><h2 id="next-journey-title">Your next journey</h2></div><a class="overview-text-link" href="{{ url('passenger/schedule') }}">View schedule @include('partials.overview-icon',['icon'=>'chevron'])</a></div>
      @if($nextBooking)
        <div class="next-journey-content">
          <div class="journey-departure"><span>{{ $nextRide->ride_status === 'in_progress' ? 'IN PROGRESS' : (($nextRide->ride_mode ?? 'scheduled') === 'now' ? 'RIDE NOW' : ($nextRide->departure_at->isToday() ? 'TODAY' : ($nextRide->departure_at->isTomorrow() ? 'TOMORROW' : $nextRide->departure_at->format('D, j M')))) }}</span>@if(($nextRide->ride_mode ?? 'scheduled') === 'now')<strong>Now</strong><p>Meet at your pickup point</p>@else<strong>{{ $nextRide->departure_at->format('g:i') }}<small>{{ $nextRide->departure_at->format('A') }}</small></strong><p>{{ $nextRide->departure_at->format('j M Y') }}</p>@endif</div>
          <div class="journey-route-points"><div><i aria-hidden="true"></i><span><small>PICKUP</small><b>{{ $nextBooking->pickup_label ?: $nextRide->pickup_point }}</b></span></div><div><i aria-hidden="true"></i><span><small>DESTINATION</small><b>{{ $nextBooking->dropoff_label ?: $nextRide->destination }}</b></span></div></div>
        </div>
        <div class="journey-booking-detail"><span class="booking-driver"><i aria-hidden="true">{{ mb_strtoupper(mb_substr($nextRide->driver?->full_name ?? 'D',0,1)) }}</i><span><small>Your driver</small><b>{{ $nextRide->driver?->full_name ?? 'Driver details unavailable' }}</b></span></span><span class="booking-meta">@include('partials.overview-icon',['icon'=>'people']) {{ $nextBooking->seats_requested }} {{ $nextBooking->seats_requested === 1 ? 'seat' : 'seats' }}</span><span class="booking-fare">RM {{ number_format((float)($nextBooking->fare_per_seat ?? $nextRide->contribution_per_seat) * $nextBooking->seats_requested,2) }}<small>total contribution</small></span></div>
        <div class="journey-confirmation {{ $nextBooking->booking_status === 'pending' ? 'is-pending' : '' }}">@include('partials.overview-icon',['icon'=>$nextBooking->booking_status === 'pending' ? 'clock' : 'check'])<span>{{ $nextBooking->booking_status === 'pending' ? 'Awaiting driver approval. Your seat is not confirmed yet.' : 'Your seat is confirmed. Check your pickup details before departure.' }}</span><a href="{{ url('passenger/bookings') }}">View booking →</a></div>
      @else
        <div class="next-journey-empty"><div class="empty-route-art" aria-hidden="true"><span class="empty-route-start"></span><svg viewBox="0 0 160 52"><path d="M8 29h37c25 0 18-18 42-18h64"/><circle cx="8" cy="29" r="5"/><path d="m143 4 8 7-8 7"/></svg><span class="empty-route-end">@include('partials.overview-icon',['icon'=>'calendar'])</span></div><h3>A little planning. A smoother day.</h3><p>You don’t have an upcoming ride yet.<br>Find a journey that fits your day and request a seat.</p><a class="overview-empty-link" href="{{ url('findride.php') }}">Browse available rides @include('partials.overview-icon',['icon'=>'arrow'])</a></div>
      @endif
    </section>

    <section class="overview-card recent-bookings" aria-labelledby="recent-bookings-title"><div class="overview-card-head"><div><span class="overview-section-label">YOUR ACTIVITY</span><h2 id="recent-bookings-title">Recent bookings</h2></div><a class="overview-text-link" href="{{ url('passenger/bookings') }}">View all @include('partials.overview-icon',['icon'=>'chevron'])</a></div>
      @if($overview['recentBookings']->isNotEmpty())
        <div class="overview-booking-list">
          @foreach($overview['recentBookings'] as $recentBooking)
            @php
              $recentRide = $recentBooking->ride;
              $displayStatus = $recentBooking->booking_status;
              if ($displayStatus === 'accepted' && $recentRide?->ride_status === 'completed') $displayStatus = 'completed';
              elseif (in_array($displayStatus, ['pending','accepted'], true) && $recentRide?->ride_status === 'cancelled') $displayStatus = 'cancelled';
            @endphp
            <article class="overview-booking-row"><span class="recent-route-icon">@include('partials.overview-icon',['icon'=>'ticket'])</span><div class="recent-route-copy"><b>{{ $recentBooking->dropoff_label ?: $recentRide?->destination ?: 'Journey unavailable' }}</b><span>{{ $recentBooking->pickup_label ?: $recentRide?->pickup_point ?: 'Pickup unavailable' }} <i aria-hidden="true">→</i> {{ $recentRide?->departure_at?->format('j M, g:i A') ?? 'Departure unavailable' }}</span></div><span class="overview-status status-{{ $displayStatus }}"><i aria-hidden="true"></i>{{ $displayStatus === 'completed' ? 'Completed' : ($bookingLabels[$displayStatus] ?? ucfirst($displayStatus)) }}</span></article>
          @endforeach
        </div>
      @else
        <div class="recent-empty">@include('partials.overview-icon',['icon'=>'ticket'])<div><b>Your journeys will appear here.</b><p>Once you request a seat, you can follow its status here.</p></div></div>
      @endif
    </section>
  </div>

  <aside class="overview-side-column" aria-label="Planning tools">
    <section class="overview-card planning-card"><div class="campus-card-image"><img src="{{ asset('images/utem-campus-aerial.png') }}" width="1600" height="583" alt="Aerial view of the UTeM campus" decoding="async"><span>YOUR CAMPUS, CONNECTED.</span></div><div class="planning-card-body"><h2>A little more organised.</h2><p>Keep the rest of your commute in one place.</p><nav class="overview-tools" aria-label="Passenger planning tools"><a href="{{ url('timetable') }}"><span class="tool-icon">@include('partials.overview-icon',['icon'=>'calendar'])</span><span><b>My timetable</b><small>Plan around your classes</small></span>@include('partials.overview-icon',['icon'=>'chevron'])</a><a href="{{ url('carbon') }}"><span class="tool-icon">@include('partials.overview-icon',['icon'=>'leaf'])</span><span><b>Carbon impact</b><small>Review completed-trip estimates</small></span>@include('partials.overview-icon',['icon'=>'chevron'])</a><a href="{{ url('passenger/history') }}"><span class="tool-icon">@include('partials.overview-icon',['icon'=>'history'])</span><span><b>Journey history</b><small>Look back at your past rides</small></span>@include('partials.overview-icon',['icon'=>'chevron'])</a></nav></div></section>
    <section class="booking-guide"><span class="guide-icon">@include('partials.overview-icon',['icon'=>'check'])</span><div><h3>A request is the first step.</h3><p>Your driver confirms your seat. Check Bookings for the latest decision before heading to pickup.</p><a href="{{ url('passenger/bookings') }}">Check my bookings <span aria-hidden="true">→</span></a></div></section>
  </aside>
</div>
