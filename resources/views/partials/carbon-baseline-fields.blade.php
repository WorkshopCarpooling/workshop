@php($baselineBooking = $baselineBooking ?? null)
<div data-carbon-baseline class="carbon-baseline-fields">
  <label>Without this carpool, how would your group travel?
    <select name="baseline_mode" data-baseline-mode>
      @foreach(config('carbon.modes') as $mode=>$label)<option value="{{ $mode }}" @selected(($baselineBooking->baseline_mode ?? 'unknown') === $mode)>{{ $label }}</option>@endforeach
    </select>
  </label>
  <div data-baseline-car class="carbon-form-grid">
    <label>Separate cars your group would use<input name="baseline_cars" type="number" min="1" max="{{ $baselineBooking->seats_requested ?? 1 }}" value="{{ $baselineBooking->baseline_cars ?? 1 }}"></label>
    <label>Alternative car fuel<select name="baseline_fuel_type"><option value="">Choose fuel</option>@foreach(config('carbon.fuels') as $fuel=>$entry)<option value="{{ $fuel }}" @selected(($baselineBooking->baseline_fuel_type ?? '') === $fuel)>{{ $entry['label'] }}</option>@endforeach</select></label>
    <label>Alternative car consumption (L/100 km)<input name="baseline_fuel_l_per_100km" type="number" min="0.1" max="50" step="0.1" value="{{ $baselineBooking->baseline_fuel_l_per_100km ?? '' }}" placeholder="e.g. 7.0"></label>
    <label>Alternative route distance (km)<input name="baseline_distance_km" type="number" min="0.001" max="500" step="0.001" value="{{ $baselineBooking->baseline_distance_km ?? $baselineBooking->journey_distance_km ?? '' }}" placeholder="Your one-way route distance"></label>
  </div>
  <small>Car details apply only to a separate-car baseline. The route distance starts from the saved fare quote when available; confirm or replace it with your own road-distance estimate. Count cars, not passengers. Other transport modes remain incomplete in this first version.</small>
</div>
