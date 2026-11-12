<section class="content-card driver-now-requests {{ ($section??'')==='overview'?'driver-home-requests':'' }}" id="driverPassengerRequests" aria-labelledby="ride-now-requests-title">
  <div class="section-head"><div><span class="section-kicker">PASSENGER REQUESTS</span><h2 id="ride-now-requests-title">Passengers looking for a driver</h2></div><span class="driver-count" id="nowDriverRequestCount">Checking…</span></div>
  <p class="driver-intro">Press Accept to confirm a pickup, then Start trip to open your driver map here. You can accept directly without opening a route first.</p>
  <p id="poolSummary" class="pool-summary" hidden></p><a id="poolSchedule" class="text-link" href="#driverAcceptedTrips" hidden>View accepted passengers / start trip →</a>
  <p class="journey-status" role="status" id="nowDriverFeedStatus">{{ ($onDemandReady ?? false) ? ($approvedVehicles->isEmpty() ? 'You need an approved vehicle to accept ride requests.' : 'Checking passenger requests…') : 'Ride requests are temporarily unavailable.' }}</p>
  <div class="now-driver-list" id="nowDriverRequests"></div>
  @if($approvedVehicles->isNotEmpty())
  <details class="driver-request-tools">
    <summary>Starting point & optional route</summary>
  <div class="pool-setup" id="poolSetup">
    <div><b>Starting location · UTeM area</b><p id="poolLocationStatus" role="status">UTeM Main Campus selected. Adjust the pin to where you will start within {{ \App\Services\CampusDriverArea::settings()['radius_km'] }} km of campus.</p><div class="pool-tools"><button type="button" class="outline-button" id="poolUseLocation">Use my location</button><button type="button" class="outline-button" id="poolChooseLocation" aria-expanded="false">Adjust UTeM pin</button></div></div>
    <div id="poolStartMap" class="pool-start-map" aria-label="Choose the driver starting location" hidden></div>
  </div>
    @include('partials.driver-waiting-route')
  </details>
  @endif
</section>
@php
  $driverRideNowConfig = ['ready'=>($onDemandReady ?? false) && $approvedVehicles->isNotEmpty(),'waitingEnabled'=>\App\Services\DriverWaitingRoutes::ready(),'waiting'=>\App\Services\DriverWaitingRoutes::summary(\App\Services\DriverWaitingRoutes::current((int)Auth::id())),'campusArea'=>\App\Services\CampusDriverArea::settings(),'feedUrl'=>url('api/driver/ride-requests'),'acceptUrl'=>url('driver/ride-requests/accept'),'csrf'=>csrf_token(),'vehicles'=>$approvedVehicles->map(fn($vehicle)=>['id'=>$vehicle->vehicle_id,'label'=>$vehicle->vehicle_color.' '.$vehicle->vehicle_type.' · '.$vehicle->vehicle_plate])->values()];
@endphp
<script>
window.driverRideNow = @json($driverRideNowConfig);
</script>
<script src="{{ asset('js/driver-ride-now.js') }}?v={{ filemtime(public_path('js/driver-ride-now.js')) }}" defer></script>
