<div class="connection-status" id="connectionStatus" role="status" aria-live="polite" hidden>You're offline. Ride search and bookings need an internet connection. Your form stays on this screen.</div>
<script src="{{ asset('js/device.js') }}?v={{ filemtime(public_path('js/device.js')) }}" data-retired-worker-url="{{ asset('service-worker.js') }}" defer></script>
@if(Auth::check() && Auth::user()->isPassenger() && Auth::user()->isActive())
@include('partials.schedule-robot')
@endif
