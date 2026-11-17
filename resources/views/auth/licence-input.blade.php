<div class="input-wrap">
  <input id="driver_license" name="driver_license" type="file" accept="image/jpeg,image/png,image/webp"
    aria-describedby="licenceHelp{{ $errors->has('driver_license') ? ' formMessage' : '' }}"
    @if(isset($applicant)) required @endif @error('driver_license') aria-invalid="true" @enderror>
</div>
<small class="auth-field-help" id="licenceHelp">Upload a clear JPG, PNG or WebP up to 2 MB. An admin will review it.</small>
