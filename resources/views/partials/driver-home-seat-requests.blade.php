@php($homeRequests=$bookingRequests->where('booking_status','pending')->filter(fn($booking)=>$booking->ride->ride_status==='scheduled')->take(3))
@if($homeRequests->isNotEmpty())
<section class="content-card driver-home-requests" id="driver-home-seat-requests" aria-labelledby="homeSeatRequestsTitle">
  <div class="section-head"><div><span class="section-kicker">NEEDS YOUR RESPONSE</span><h2 id="homeSeatRequestsTitle">Requests for your scheduled rides</h2></div><a class="text-link" href="{{ url('driver/requests') }}">View all {{ $pendingRequestCount }} →</a></div>
  <div class="now-driver-list">
  @foreach($homeRequests as $booking)
    <article class="now-driver-request"><h3>{{ $booking->passenger?->full_name ?? 'Passenger' }} · {{ $booking->seats_requested }} {{ $booking->seats_requested===1?'seat':'seats' }}</h3>
      <p>{{ $booking->pickup_label ?: $booking->ride->pickup_point }} → {{ $booking->dropoff_label ?: $booking->ride->destination }}</p><p>{{ $booking->ride->departure_at->format('d M, g:i A') }}@if($booking->fare_per_seat!==null) · RM {{ number_format((float)$booking->fare_per_seat*$booking->seats_requested,2) }} total @endif</p>
      <div class="pool-tools">@foreach(['accept_booking'=>'Accept request','decline_booking'=>'Decline'] as $action=>$label)<form method="post" action="{{ url('driver.php') }}">@csrf<input type="hidden" name="action" value="{{ $action }}"><input type="hidden" name="booking_id" value="{{ $booking->booking_id }}"><button class="{{ $action==='accept_booking'?'solid-button':'outline-button' }}" type="submit">{{ $label }}</button></form>@endforeach</div>
    </article>
  @endforeach
  </div>
</section>
@endif
