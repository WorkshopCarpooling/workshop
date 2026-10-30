@php($autoPickup = $autoPickup ?? false)
@php($pickerValues = $pickerValues ?? [])
<div class="journey-picker {{ $autoPickup ? 'journey-auto-pickup' : '' }}" data-journey-picker @if($autoPickup) data-auto-pickup @endif>
  @if ($autoPickup)
    @php($pickupLocalUrl = app()->environment('local') && request()->getHost() === 'workshop.test' && parse_url(config('app.url'), PHP_URL_HOST) === 'localhost' ? rtrim(config('app.url'), '/').'/findride.php' : null)
    <div class="journey-current-location">
      <span class="journey-location-icon" aria-hidden="true">⌖</span>
      <div><b>Pickup point · current location</b><p data-gps-status role="status">Detecting your location… Allow location access when your browser asks.</p>@if($pickupLocalUrl)<a class="location-access-link" data-location-access href="{{ $pickupLocalUrl }}" hidden>Open location-enabled page →</a><small class="location-access-help" data-location-help hidden>You may need to sign in again on this address.</small>@endif</div>
      <div class="journey-location-actions"><button type="button" data-auto-locate>Use current location</button><button type="button" data-edit-pickup aria-expanded="false">Choose pickup manually</button></div>
    </div>
  @endif
  <div class="journey-fields">
    @foreach (($autoPickup ? ['dropoff' => 'Destination', 'pickup' => 'Pickup'] : ['pickup' => 'Pickup', 'dropoff' => 'Drop-off']) as $point => $label)
      <div class="journey-field" data-point="{{ $point }}" @if($autoPickup && $point === 'pickup') data-pickup-manual hidden @endif>
        <label for="{{ $point }}Label">{{ $label }} location</label>
        <div class="journey-input-row">
          <input id="{{ $point }}Label" name="{{ $point }}_label" type="text" maxlength="180" value="{{ old($point . '_label', $pickerValues[$point . '_label'] ?? '') }}" placeholder="{{ $autoPickup && $point === 'dropoff' ? 'Where do you want to go?' : 'Search a place or click the map' }}" autocomplete="off" @unless($autoPickup && $point === 'pickup') required @endunless>
          <button type="button" data-search="{{ $point }}">Search</button>
          <button type="button" data-pin="{{ $point }}" aria-pressed="{{ ($autoPickup ? $point === 'dropoff' : $point === 'pickup') ? 'true' : 'false' }}">Pin on map</button>
        </div>
        <div class="journey-suggestions" data-results="{{ $point }}"></div>
        <small data-coordinates="{{ $point }}">No location selected</small>
        <input type="hidden" name="{{ $point }}_latitude" value="{{ old($point . '_latitude', $pickerValues[$point . '_latitude'] ?? '') }}">
        <input type="hidden" name="{{ $point }}_longitude" value="{{ old($point . '_longitude', $pickerValues[$point . '_longitude'] ?? '') }}">
      </div>
    @endforeach
  </div>
  @unless($autoPickup)<div class="journey-extra-tools"><button type="button" data-swap-locations>⇄ Swap locations</button><button type="button" data-use-location>⌖ Use my location for selected pin</button></div>@endunless
  <p class="journey-status" data-map-status role="status">{{ $autoPickup ? 'Type a destination and choose a search result, or click the map to set its pin. Drag either pin to adjust it.' : 'Select pickup or drop-off, then click the map. You can also search a place name.' }}</p>
  <div class="journey-map" data-journey-map aria-label="Map for choosing pickup and drop-off"></div>
  <div class="journey-route-summary" data-route-summary aria-live="polite">Choose both locations to preview the journey.</div>
  <a data-directions hidden target="_blank" rel="noopener noreferrer">Open driving directions ↗</a>
</div>
