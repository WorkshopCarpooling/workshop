@if(Auth::check() && Auth::user()->isDriver())
  @php($gpsRides = $activeRides ?? \App\Models\Ride::where('driver_id', Auth::id())->where('ride_status','in_progress')->get())
  @if($gpsRides->isNotEmpty())
    <details class="global-driver-gps"><summary>◎ Active trip GPS · {{ $gpsRides->count() }} trip{{ $gpsRides->count() === 1 ? '' : 's' }}</summary>
      @foreach($gpsRides as $gpsRide)
        <div class="driver-location-sharing" data-location-url="{{ url('api/driver/rides/'.$gpsRide->ride_id) }}">
          <b>Trip #{{ $gpsRide->ride_id }} · {{ $gpsRide->destination }}</b>
          <p>GPS starts automatically for your active trip. Admin and confirmed passengers can see its location. Keep a driver page open and allow browser location access if asked.</p>
          <button class="ride-action-button" type="button" data-share-start hidden>Retry GPS</button>
          <button class="ride-action-button quiet-action" type="button" data-share-stop hidden>Stop sharing</button>
          <small data-share-status role="status">Starting GPS automatically…</small>
          <a href="http://localhost/workshop/driver.php" data-gps-localhost hidden>Use localhost for GPS on this computer →</a>
        </div>
      @endforeach
    </details>
    <script>window.bookingCsrf = @json(csrf_token());</script>
    <script src="{{ asset('js/driver-location.js') }}?v={{ filemtime(public_path('js/driver-location.js')) }}" defer></script>
  @endif
@endif
