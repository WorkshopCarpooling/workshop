@if(Auth::check() && Auth::user()->isDriver() && !\App\Services\TripTest::enabled())
  @php($gpsRides = $activeRides ?? \App\Models\Ride::where('driver_id', Auth::id())->where('ride_status','in_progress')->get())
  @if($gpsRides->isNotEmpty())
    @if($inlineGps ?? false)<div class="navigation-gps-controls" aria-label="Driver location access">
    @else<details class="global-driver-gps"><summary>◎ Active trip GPS · {{ $gpsRides->count() }} trip{{ $gpsRides->count() === 1 ? '' : 's' }}</summary>@endif
      @foreach($gpsRides as $gpsRide)
        <div class="driver-location-sharing" data-location-url="{{ url('api/driver/rides/'.$gpsRide->ride_id) }}">
          <b>Trip #{{ $gpsRide->ride_id }} · {{ $gpsRide->destination }}</b>
          <p>GPS starts automatically for your active trip. Admin and confirmed passengers can see its location. Keep a driver page open and allow browser location access if asked.</p>
          <button class="ride-action-button" type="button" data-share-start hidden>Retry GPS</button>
          <button class="ride-action-button quiet-action" type="button" data-share-stop hidden>Stop sharing</button>
          <small data-share-status role="status">Starting GPS automatically…</small>
          <a href="http://localhost/workshop/trips/{{ $gpsRide->ride_id }}/map" data-gps-localhost hidden>Open this trip on localhost for GPS →</a>
        </div>
      @endforeach
    @if($inlineGps ?? false)</div>@else</details>@endif
    <script>window.bookingCsrf = @json(csrf_token());</script>
    <script src="{{ asset('js/driver-location.js') }}?v={{ filemtime(public_path('js/driver-location.js')) }}" defer></script>
  @endif
@endif
