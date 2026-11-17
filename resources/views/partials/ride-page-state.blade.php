@php($ridePageStateConfig=['url'=>url('api/rides/page-state'),'version'=>\App\Services\RidePageState::version(Auth::user())])
<script>window.ridePageState = @json($ridePageStateConfig);</script>
<script src="{{ asset('js/ride-page-state.js') }}?v={{ filemtime(public_path('js/ride-page-state.js')) }}" defer></script>
