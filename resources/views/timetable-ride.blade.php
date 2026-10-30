@extends('layouts.commute',['title'=>'Plan your ride to '.$session->title,'subtitle'=>'Choose your route and how early you want to arrive. We’ll work back from the session start.'])
@section('extra-head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endsection
@section('content')
<div class="commute-session-summary"><span class="commute-kicker">{{ ['SUNDAY','MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY','SATURDAY'][$session->weekday] }}</span><h2>{{ $session->title }}</h2><p>{{ \Carbon\Carbon::parse($session->starts_at)->format('g:i A') }}–{{ \Carbon\Carbon::parse($session->ends_at)->format('g:i A') }} · {{ $session->location }}</p></div>
<section class="content-card commute-form-card">
  <h2>Your usual journey</h2><p class="commute-help">Choose the pickup and drop-off you want to use for this session.</p>
  <form id="timetableRideForm" method="get" action="{{ url('timetable/'.$session->id.'/ride') }}">
    @include('partials.journey-picker',['autoPickup'=>false,'pickerValues'=>$pickerValues])
    <div class="commute-options">
      <label>Passengers<select name="seats_requested">@for($i=1;$i<=3;$i++)<option value="{{ $i }}" @selected((int)old('seats_requested',$plan->seats_requested??1)===$i)>{{ $i }} {{ $i===1?'passenger':'passengers' }}</option>@endfor</select></label>
      <label>Arrive before the session (minutes)<input name="arrival_buffer_minutes" type="number" min="0" max="120" value="{{ old('arrival_buffer_minutes',$plan->arrival_buffer_minutes??10) }}" required></label>
      <label>How often?<select name="booking_mode" id="commuteBookingMode"><option value="once" @selected(old('booking_mode',$plan?'weekly':'once')==='once')>Next session only</option><option value="weekly" @selected(old('booking_mode',$plan?'weekly':'once')==='weekly')>Repeat every week</option></select></label>
    </div>
    <div class="commute-pair commute-repeat" id="commuteRepeatDates" hidden>
      <label>Start from<input name="starts_on" type="date" value="{{ old('starts_on',isset($plan)?$plan->starts_on->format('Y-m-d'):now()->format('Y-m-d')) }}"></label>
      <label>Repeat weekly until<input name="ends_on" type="date" min="{{ now()->format('Y-m-d') }}" value="{{ old('ends_on',isset($plan)?$plan->ends_on->format('Y-m-d'):'') }}"></label>
    </div>
    <button class="solid-button" id="reviewTimetableRide" type="submit">Calculate & review ride →</button>
  </form>
  <p class="commute-help" id="timetableRideStatus" role="status">Your suggested time will include the route estimate, your arrival buffer and 10 minutes to find a driver.</p>
</section>
<section class="content-card commute-review" id="timetableRideReview" hidden aria-live="polite">
  <span class="commute-kicker">REVIEW YOUR NEXT SESSION</span><h2 id="commuteReviewDate"></h2>
  <div class="commute-review-times"><div><span>Start driver search</span><strong id="commuteReviewPickup"></strong></div><div><span>Aim to arrive by</span><strong id="commuteReviewArrival"></strong></div><div><span>Session starts</span><strong id="commuteReviewClass"></strong></div></div>
  <p id="commuteReviewRoute"></p><p id="commuteReviewFare"></p><p class="commute-help" id="commuteReviewTiming"></p>
  <label class="commute-consent" id="weeklyConsentLabel" hidden><input id="weeklyRideConsent" type="checkbox"> <span id="weeklyConsentText"></span></label>
  <p class="commute-help">Times are estimates. A car is confirmed after a driver accepts. Your robot will remind you before the ride appointment.</p>
  <button class="solid-button" id="confirmTimetableRide" type="button">Confirm ride appointment →</button>
</section>
<p class="commute-help"><a href="{{ url('timetable') }}">← Back to my timetable</a></p>
@endsection
@section('scripts')
@php($timetableRideConfig=['url'=>url('timetable/'.$session->id.'/ride'),'csrf'=>csrf_token()])
<script>
window.timetableRide = @json($timetableRideConfig);
window.bookingCsrf = @json(csrf_token());
window.googleMapsApiKey = @json(config('services.google_maps.api_key',''));
window.routesComputeUrl = @json(url('api/routes/compute'));
window.locationSearchUrl = @json(url('api/locations/search'));
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
<script src="{{ asset('js/maplibre-loader.js') }}" defer></script>
<script src="{{ asset('js/journey-picker.js') }}?v={{ filemtime(public_path('js/journey-picker.js')) }}" defer></script>
<script src="{{ asset('js/timetable-rides.js') }}?v={{ filemtime(public_path('js/timetable-rides.js')) }}" defer></script>
@endsection
