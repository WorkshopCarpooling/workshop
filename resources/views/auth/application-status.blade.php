@php($status = $applicant->applicationStatus())
<header class="auth-card-heading">
  <h1 id="auth-title">Application status</h1>
  <p>{{ $applicant->full_name }} · {{ ucfirst($applicant->account_type) }}</p>
</header>

<div class="form-message {{ $status === 'active' ? 'success' : '' }}" role="status">
  @if ($status === 'declined')
    Application declined. {{ $applicant->application_review['reason'] }}
  @elseif ($status === 'resubmit')
    A replacement licence is required. {{ $applicant->application_review['reason'] }}
  @elseif ($status === 'active')
    Application approved. Sign in again to open your dashboard.
  @elseif ($applicant->canUploadLicense())
    Upload your driving licence for admin review.
  @elseif ($status === 'pending')
    Your application is waiting for admin review.
  @else
    This account is not active. Contact the administrator.
  @endif
</div>

@if ($applicant->canUploadLicense())
  <form class="auth-form" method="post" action="{{ route('application.licence') }}" enctype="multipart/form-data">
    @csrf
    <div class="field">
      <label for="driver_license">{{ $applicant->driver_license_path ? 'Replacement licence' : 'Driving licence' }}</label>
      @include('auth.licence-input')
    </div>
    @error('driver_license')
      <div class="form-message error" id="formMessage" role="alert">{{ $message }}</div>
    @enderror
    <button class="submit-button" type="submit">Submit licence</button>
  </form>
@endif

@if (session('formSuccess'))
  <div class="form-message success" role="status">{{ session('formSuccess') }}</div>
@endif
<form class="switch-prompt" method="post" action="{{ url('logout.php') }}">
  @csrf
  <button type="submit" class="submit-button">Back to sign in</button>
</form>
