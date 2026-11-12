@extends('layouts.workspace', ['title'=>'Live trip preview','subtitle'=>'Passenger, driver and admin views follow one car and one shared trip clock.'])
@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="{{ asset('css/live-preview.css') }}?v={{ filemtime(public_path('css/live-preview.css')) }}">
<link rel="stylesheet" href="{{ asset('css/map-carbon.css') }}?v={{ filemtime(public_path('css/map-carbon.css')) }}">
@endpush
@section('content')
<section class="content-card live-preview" aria-label="Synchronized trip preview">
  <div class="preview-toolbar">
    <label for="previewRide">Trip to preview<select id="previewRide"><option value="">Follow the next active trip</option>@foreach($rides as $trip)<option value="{{ $trip->ride_id }}" @selected($selectedRide===$trip->ride_id)>Trip #{{ $trip->ride_id }} · {{ \App\Services\LocationLabel::short($trip->destination) }}</option>@endforeach</select></label>
    <span class="preview-sync" id="previewConnection" role="status">Connecting…</span>
    <a class="text-link" href="{{ url('admin/live') }}">All live drivers →</a>
  </div>
  <p class="preview-route" id="previewJourney">Waiting for a driver to start a trip.</p>
  <div class="preview-panels">
    @foreach(['driver'=>['Driver','Driver confirms pickups and drop-offs on the trip page.'],'passenger'=>['Passenger','Follow the car with no pickup or drop-off controls.'],'admin'=>['Admin','Observe the active driver and the same live car position.']] as $role=>$copy)
    <article class="preview-panel" aria-labelledby="preview-{{ $role }}-title">
      <header><span class="preview-role">{{ strtoupper($role) }} VIEW</span><h2 id="preview-{{ $role }}-title">{{ $copy[0] }}</h2><p>{{ $copy[1] }}</p></header>
      <div class="preview-map" id="preview-{{ $role }}-map" aria-label="{{ $copy[0] }} preview map"></div>
      <div class="preview-position"><b data-preview-state>Waiting for Start trip</b><span data-preview-position>Car position appears when the trip begins.</span></div>
    </article>
    @endforeach
  </div>
  <p class="preview-footnote">All three panels share one update every 5 seconds. Automatic driving follows the same server clock between updates. GPS trips use the driver’s device location. Closing this preview does not stop the trip.</p>
</section>
@endsection
@push('scripts')
@php($previewConfig=['feedUrl'=>url('api/admin/live/preview'.($selectedRide?'/'.$selectedRide:'')),'previewUrl'=>url('admin/live/preview'),'carIconUrl'=>asset('images/tracking-car-3d.png').'?v='.filemtime(public_path('images/tracking-car-3d.png'))])
<script>window.liveTripPreview=@json($previewConfig);</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
<script src="{{ asset('js/trip-navigation-geometry.js') }}" defer></script>
<script src="{{ asset('js/live-preview.js') }}?v={{ filemtime(public_path('js/live-preview.js')) }}" defer></script>
@endpush
