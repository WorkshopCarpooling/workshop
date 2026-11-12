@extends('layouts.workspace', ['title' => 'Edit profile', 'subtitle' => 'Manage your profile, contact details and password.'])

@push('styles')
<link rel="stylesheet" href="{{ asset('css/profile.css') }}?v={{ filemtime(public_path('css/profile.css')) }}">
@endpush

@section('content')
<div class="profile-shell">
  <section class="content-card profile-identity" aria-label="Current profile">
    <div class="profile-cover"><span>UTeM Main Campus</span></div>
    <div class="profile-identity-details">
      @include('partials.user-avatar', ['avatarUser' => $user])
      <div>
        <h2>{{ $user->full_name }}</h2>
        <p>{{ $user->isAdmin() ? 'Administrator' : ucfirst($user->role) . ' · ' . ucfirst($user->account_type) }}</p>
      </div>
    </div>
  </section>

  <div class="profile-settings">
    <form class="content-card profile-form" method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" aria-labelledby="profile-details-title">
      @csrf
      @method('PATCH')
      <header class="profile-form-heading">
        <h2 id="profile-details-title">Personal details</h2>
        <p>Update your photo and contact information.</p>
      </header>

      @if(session('profileNotice'))
        <p class="profile-status" role="status">{{ session('profileNotice') }}</p>
      @endif

      <div class="profile-field">
        <label for="avatar">{{ $user->avatar_path ? 'Replace profile photo' : 'Add a profile photo' }}</label>
        <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="avatar-help" @error('avatar') aria-invalid="true" @enderror>
        <small id="avatar-help">JPG, PNG or WebP · Up to 2 MB and 4096 × 4096 px</small>
      </div>

      <div class="profile-field">
        <label for="full_name">Full name</label>
        <input id="full_name" name="full_name" value="{{ old('full_name', $user->full_name) }}" autocomplete="name" maxlength="100" required @error('full_name') aria-invalid="true" @enderror>
      </div>

      <div class="profile-field">
        <label for="phone_number">Phone number <small>(optional)</small></label>
        <input id="phone_number" name="phone_number" type="tel" value="{{ old('phone_number', $user->phone_number) }}" autocomplete="tel" maxlength="20" @error('phone_number') aria-invalid="true" @enderror>
      </div>

      <div class="profile-field">
        <label for="account_email">Account email</label>
        <input id="account_email" type="email" value="{{ $user->email }}" readonly>
      </div>

      <button class="solid-button" type="submit">Save profile</button>
    </form>

    <form id="change-password" class="content-card profile-form" method="post" action="{{ route('profile.password') }}" aria-labelledby="change-password-title">
      @csrf
      @method('PATCH')
      <header class="profile-form-heading">
        <h2 id="change-password-title">Change password</h2>
        <p>Verify your current password before choosing a new one.</p>
      </header>

      @if(session('passwordNotice'))
        <p class="profile-status" role="status">{{ session('passwordNotice') }}</p>
      @endif

      <div class="profile-field">
        <label for="current_password">Current password</label>
        <input id="current_password" name="current_password" type="password" autocomplete="current-password" required @error('current_password', 'changePassword') aria-invalid="true" aria-describedby="current-password-error" @enderror>
        @error('current_password', 'changePassword')
          <small id="current-password-error" class="profile-error" role="alert">{{ $message }}</small>
        @enderror
      </div>

      <div class="profile-field">
        <label for="password">New password</label>
        <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="72" required aria-describedby="password-help @error('password', 'changePassword') password-error @enderror" @error('password', 'changePassword') aria-invalid="true" @enderror>
        <small id="password-help">Use at least 8 characters.</small>
        @error('password', 'changePassword')
          <small id="password-error" class="profile-error" role="alert">{{ $message }}</small>
        @enderror
      </div>

      <div class="profile-field">
        <label for="password_confirmation">Confirm new password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" maxlength="72" required @error('password_confirmation', 'changePassword') aria-invalid="true" aria-describedby="password-confirmation-error" @enderror>
        @error('password_confirmation', 'changePassword')
          <small id="password-confirmation-error" class="profile-error" role="alert">{{ $message }}</small>
        @enderror
      </div>

      <button class="solid-button" type="submit">Update password</button>
    </form>
  </div>
</div>
@endsection
