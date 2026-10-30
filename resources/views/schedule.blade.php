<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <title>Ride appointments · RideUTeM</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
  @foreach(['dashboard','driver','find-ride','usability','interface-refresh','campus-interface','journey','student-journey'] as $style)<link rel="stylesheet" href="{{ asset('css/'.$style.'.css') }}">@endforeach
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  @include('partials.device-head')
  @include('partials.theme-head')
  <link rel="stylesheet" href="{{ asset('css/ride-now.css') }}?v={{ filemtime(public_path('css/ride-now.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/ride-appointments.css') }}?v={{ filemtime(public_path('css/ride-appointments.css')) }}">
</head>
<body class="app-page ride-now-page appointment-page">
<div class="app-shell">
  @include('partials.app-nav',['activePage'=>'passenger_schedule'])
  <main class="main-area">
    <header class="topbar"><div class="breadcrumb">Workspace <span>/</span> Schedule</div></header>
    <div class="page-content">
      <div class="welcome-row"><div><div class="date-line"><span class="date-dot"></span> A RIDE, ON YOUR TIME</div><h1>Book a ride appointment.</h1><p class="subtitle">Choose where and when. We’ll start looking for your driver at your selected time.</p></div><a class="outline-button" href="{{ url('findride.php') }}">Need a ride now? →</a></div>
      @if(session('appointmentNotice'))<div class="driver-notice" role="status">{{ session('appointmentNotice') }}</div>@endif
      @if($errors->any())<div class="validation-errors" role="alert">{{ $errors->first() }}</div>@endif
      <section class="content-card now-search-card">
        <div class="now-card-head"><div><span class="now-kicker">PICKUP & DROP-OFF</span><h2>Plan your journey</h2></div><span class="now-badge">◷ Appointment</span></div>
        <form id="rideAppointmentForm" action="{{ url('passenger/schedule') }}" method="get">
          @include('partials.journey-picker',['autoPickup'=>true])
          <div class="appointment-options">
            <label for="appointmentDate">Pickup date<input id="appointmentDate" name="pickup_date" type="date" min="{{ now()->format('Y-m-d') }}" value="{{ old('pickup_date') }}" required></label>
            <label for="appointmentTime">Pickup time<input id="appointmentTime" name="pickup_time" type="time" value="{{ old('pickup_time','09:00') }}" required></label>
            <label for="appointmentPassengers">Passengers<select id="appointmentPassengers" name="seats_requested">@for($i=1;$i<=3;$i++)<option value="{{ $i }}" @selected((int)old('seats_requested',1)===$i)>{{ $i }} {{ $i===1?'passenger':'passengers' }}</option>@endfor</select></label>
          </div>
          <div class="now-search-actions"><p class="journey-status">All times are Malaysia time (GMT+8).</p><button class="solid-button" id="appointmentReview" type="submit">Review appointment →</button></div>
        </form>
        <p class="journey-status" id="appointmentFormStatus" role="status">Choose your locations, date, time and passenger count to continue.</p>
      </section>
      <section class="content-card now-quote-card" id="appointmentQuote" hidden aria-live="polite">
        <div class="now-card-head"><div><span class="now-kicker">YOUR APPOINTMENT</span><h2 id="appointmentQuoteWhen"></h2></div><strong class="now-quote-total" id="appointmentQuoteTotal"></strong></div>
        <p id="appointmentQuoteRoute"></p><p class="journey-status" id="appointmentQuoteDetails"></p>
        <p class="journey-status">We’ll automatically send your request to approved drivers at the booked time. Your robot reminds you 30 and 10 minutes beforehand while RideUTeM is open. A driver must accept before a car is confirmed.</p>
        <button class="solid-button" id="appointmentConfirm" type="button">Confirm appointment →</button>
      </section>
      <section class="content-card appointment-list-card" aria-labelledby="appointmentsTitle">
        <div class="section-head"><div><span class="now-kicker">SAVED FOR LATER</span><h2 id="appointmentsTitle">My ride appointments</h2></div><span id="appointmentCount">{{ count($appointments ?? []) }} appointments</span></div>
        <p class="journey-status" id="appointmentListStatus" role="status">Your upcoming appointments and driver updates appear here.</p>
        <div id="appointmentList">
          @forelse(($appointments ?? []) as $appointment)
            <article class="appointment-row" id="appointment-{{ $appointment['id'] }}">
              <div><b>{{ $appointment['dateLabel'] }} · {{ $appointment['timeLabel'] }}</b>@if($appointment['timetableTitle']??null)<p>From your timetable: {{ $appointment['timetableTitle'] }}</p>@endif<p>{{ $appointment['pickup'] }} → {{ $appointment['dropoff'] }}</p><small>{{ $appointment['seats'] }} passenger(s) · RM {{ $appointment['total'] }} total</small></div>
              <span class="appointment-state">{{ $appointment['status']==='scheduled'?'Appointment saved':ucfirst(str_replace('_',' ',$appointment['status'])) }}</span>
            </article>
          @empty<p class="journey-status">No ride appointments yet. Book your first one above.</p>@endforelse
        </div>
      </section>
      <p class="now-help">Your appointment saves the date and time. Driver availability is checked when the search begins; a car is confirmed only after a driver accepts.</p>
      <footer class="dashboard-footer">RideUTeM · Your campus, better connected.</footer>
    </div>
  </main>
</div>
@php
  $appointmentConfig=['url'=>url('passenger/schedule'),'statusUrl'=>url('api/passenger/appointments'),'cancelBase'=>url('passenger/appointments'),'csrf'=>csrf_token(),'ready'=>$appointmentReady ?? false,'appointments'=>$appointments ?? []];
@endphp
<script>
window.rideAppointments = @json($appointmentConfig);
window.bookingCsrf = @json(csrf_token());
window.googleMapsApiKey = @json(config('services.google_maps.api_key',''));
window.routesComputeUrl = @json(url('api/routes/compute'));
window.locationSearchUrl = @json(url('api/locations/search'));
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
<script src="{{ asset('js/maplibre-loader.js') }}" defer></script>
<script src="{{ asset('js/journey-picker.js') }}?v={{ filemtime(public_path('js/journey-picker.js')) }}" defer></script>
<script src="{{ asset('js/ride-appointments.js') }}?v={{ filemtime(public_path('js/ride-appointments.js')) }}" defer></script>
@include('partials.device-tools')
</body>
</html>
