@if($workspaceRole === 'driver')
  <div class="workspace-stats"><article><small>SCHEDULED</small><strong>{{ $rideStats['scheduled'] }}</strong><small>Trips waiting to start</small></article><article><small>IN PROGRESS</small><strong>{{ $rideStats['active'] }}</strong><small>GPS runs automatically while a driver page is open</small></article><article><small>COMPLETED</small><strong>{{ $rideStats['completed'] }}</strong><small>Ready for a carbon estimate</small></article></div>
  <div class="workspace-tiles">
    <a class="workspace-tile" href="{{ url('driver/offer') }}"><b>Offer a ride →</b><small>Choose pickup, destination, departure and an approved vehicle.</small></a>
    <a class="workspace-tile" href="{{ url('driver/requests') }}"><b>Seat requests · {{ $pendingRequestCount }}</b><small>Review passenger locations, deadlines and quoted contributions.</small></a>
    <a class="workspace-tile" href="{{ url('driver/schedule') }}"><b>My schedule →</b><small>Start, track and complete your upcoming trips.</small></a>
    <a class="workspace-tile" href="{{ url('driver/vehicles') }}"><b>Vehicles →</b><small>Vehicle approval and fuel consumption profiles.</small></a>
    <a class="workspace-tile" href="{{ url('carbon') }}"><b>Carbon impact →</b><small>Automatic completed-trip estimates using your saved vehicle profile.</small></a>
  </div>
@else
  <div class="workspace-stats"><article><small>BOOKINGS</small><strong>{{ $bookingStats['all'] }}</strong><small>Your most recent seat requests</small></article><article><small>CONFIRMED</small><strong>{{ $bookingStats['accepted'] }}</strong><small>Requests accepted by drivers</small></article><article><small>WAITING</small><strong>{{ $bookingStats['pending'] }}</strong><small>Requests awaiting a driver decision</small></article></div>
  <div class="workspace-tiles">
    <a class="workspace-tile" href="{{ url('findride.php') }}"><b>Find a ride →</b><small>Search a destination, compare nearby scheduled drivers and request seats.</small></a>
    <a class="workspace-tile" href="{{ url('passenger/bookings') }}"><b>My bookings →</b><small>See driver decisions and saved fare quotes.</small></a>
    <a class="workspace-tile" href="{{ url('passenger/schedule') }}"><b>My schedule →</b><small>Upcoming and in-progress journeys.</small></a>
    <a class="workspace-tile" href="{{ url('timetable') }}"><b>Class timetable →</b><small>Plan around your campus sessions.</small></a>
    <a class="workspace-tile" href="{{ url('carbon') }}"><b>Carbon impact →</b><small>Tell us how you would otherwise travel and view completed-trip results.</small></a>
  </div>
@endif
