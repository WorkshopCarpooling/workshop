@if(($requestTrips ?? collect())->isNotEmpty())
<section class="content-card driver-now-requests" id="driverAcceptedTrips" aria-labelledby="acceptedTripsTitle">
  <div class="section-head"><div><span class="section-kicker">READY FOR PICKUP</span><h2 id="acceptedTripsTitle">Your accepted rides</h2></div></div>
  <div class="now-driver-list">
  @foreach($requestTrips as $trip)
    <article class="now-driver-request" id="accepted-trip-{{ $trip->ride_id }}">
      <h3>{{ \App\Services\LocationLabel::short($trip->pickup_point) }} → {{ \App\Services\LocationLabel::short($trip->destination) }}</h3>
      @foreach($trip->bookings->where('booking_status','accepted') as $passengerBooking)
      <p><b>{{ $passengerBooking->passenger?->full_name ?? 'Passenger' }}</b> · {{ $passengerBooking->seats_requested }} {{ $passengerBooking->seats_requested===1?'passenger':'passengers' }}<br>{{ \App\Services\LocationLabel::short($passengerBooking->pickup_label ?: $trip->pickup_point) }} → {{ \App\Services\LocationLabel::short($passengerBooking->dropoff_label ?: $trip->destination) }}</p>
      @endforeach
      <p>{{ $trip->ride_status==='in_progress'?'Trip in progress':(($trip->ride_mode??'scheduled')==='now'?'Ready to start':$trip->departure_at->format('j M, g:i A')) }} · {{ $trip->vehicle_type ?: $trip->vehicle_description }} · {{ $trip->vehicle_plate }}</p>
      <div class="accepted-trip-actions">
      @if($trip->ride_status==='scheduled')
        <form method="post" action="{{ url('driver.php') }}">
          @csrf
          <input type="hidden" name="action" value="update_status">
          <input type="hidden" name="ride_id" value="{{ $trip->ride_id }}">
          <input type="hidden" name="next_status" value="in_progress">
          <button type="submit" class="solid-button">Start trip & open map →</button>
        </form>
        <p>{{ \App\Services\TripTest::enabled()?'Trip ujian · the car drives automatically to each stop.':'Starting opens your pickup map and closes this trip to new passengers.' }}</p>
      @else
        <a class="solid-button" href="{{ url('trips/'.$trip->ride_id.'/map') }}">Open driver map →</a>
      @endif
      </div>
    </article>
  @endforeach
  </div>
</section>
@endif
