@php
  $waitingEnabled = \App\Services\DriverWaitingRoutes::ready();
  $waitingRoute = $waitingEnabled ? \App\Services\DriverWaitingRoutes::current((int)Auth::id()) : null;
  $waitingBusy = $waitingEnabled && \App\Services\OnDemandRides::driverBusy((int)Auth::id());
  $waitingConfig = ['openUrl'=>url('driver/waiting-route'),'heartbeatUrl'=>url('driver/waiting-route/heartbeat'),'closeUrl'=>url('driver/waiting-route/close'),'csrf'=>csrf_token(),'waiting'=>\App\Services\DriverWaitingRoutes::summary($waitingRoute),'busy'=>$waitingBusy];
@endphp
@if($waitingEnabled && $approvedVehicles->isNotEmpty())
<section class="driver-waiting-section" aria-labelledby="driverWaitingTitle">
  <div class="waiting-heading"><div><span class="section-kicker">LEAVING NOW</span><h3 id="driverWaitingTitle">Open your route & wait for passengers</h3></div><span id="driverWaitingBadge" class="waiting-badge">Offline</span></div>
  <p class="waiting-help">Choose where you start and where you are going. Nearby students heading to the same destination can see your open route. No departure date or time is needed.</p>
  <div id="driverWaitingSummary" class="pool-summary" hidden><b id="driverWaitingJourney"></b><p id="driverWaitingState"></p><div class="pool-tools"><button type="button" class="outline-button" id="driverWaitingStop">Go offline</button><button type="button" class="solid-button" id="driverWaitingResume" hidden>Resume matching</button></div></div>
  <form id="driverWaitingForm" action="{{ url('driver/waiting-route') }}" method="post">
    @csrf
    <details class="waiting-route-picker" open><summary>Your starting point & destination</summary>
      @include('partials.journey-picker',['autoPickup'=>false,'pickerValues'=>['pickup_label'=>'UTeM Main Campus','pickup_latitude'=>2.3139,'pickup_longitude'=>102.3183]])
    </details>
    <div class="waiting-form-actions"><label for="driverWaitingVehicle">Your vehicle<select id="driverWaitingVehicle" name="vehicle_id" required>@foreach($approvedVehicles as $vehicle)<option value="{{ $vehicle->vehicle_id }}">{{ $vehicle->vehicle_color }} {{ $vehicle->vehicle_type }} · {{ $vehicle->vehicle_plate }}</option>@endforeach</select></label><button type="submit" class="solid-button" id="driverWaitingOpen">Open ride now →</button></div>
  </form>
  <p id="driverWaitingMessage" class="journey-status" role="status" aria-live="polite">{{ $waitingBusy ? 'Manage your current passengers in My schedule. You can open a new route when that trip is complete.' : 'Keep this page open while waiting. Going offline stops new matches and keeps accepted bookings.' }}</p>
  <p class="waiting-help">Going later? <a href="{{ url('driver/offer') }}">Offer a scheduled ride with a departure date and time →</a></p>
</section>
<script>window.driverWaiting = @json($waitingConfig);</script>
<script src="{{ asset('js/driver-waiting-route.js') }}?v={{ filemtime(public_path('js/driver-waiting-route.js')) }}" defer></script>
@endif
