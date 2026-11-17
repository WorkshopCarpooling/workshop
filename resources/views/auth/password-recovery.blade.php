@php($isReset = isset($token))
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="referrer" content="no-referrer">
  <meta name="robots" content="noindex">
  <title>{{ $isReset ? 'Reset password' : 'Forgot password' }} · RideUTeM</title>
  <link rel="stylesheet" href="{{ asset('css/campus-auth.css') }}?v={{ filemtime(public_path('css/campus-auth.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/system-theme.css') }}?v={{ filemtime(public_path('css/system-theme.css')) }}">
</head>
<body class="campus-auth-page login-page">
  <div class="campus-backdrop" aria-hidden="true">
    <img src="{{ asset('images/utem-campus-aerial.png') }}" alt="" width="1600" height="583">
    <div class="campus-shade"></div>
  </div>
  <main class="auth-canvas">
    <div class="auth-stack">
      <header class="auth-identity"><a class="auth-brand" href="{{ url('welcome') }}">ride<span>UTeM</span><i aria-hidden="true">.</i></a></header>
      <section class="auth-card" aria-labelledby="auth-title">
        <header class="auth-card-heading">
          <h1 id="auth-title">{{ $isReset ? 'Set a new password' : 'Forgot your password?' }}</h1>
          <p>{{ $isReset ? 'Enter your account email and choose a new password.' : 'Enter your account email to request a reset link.' }}</p>
        </header>
        <form class="auth-form" method="post" action="{{ route($isReset ? 'password.update' : 'password.email') }}">
          @csrf
          @if($isReset)
            <input type="hidden" name="token" value="{{ $token }}">
          @endif
          <div class="field">
            <label for="email">Account email</label>
            <div class="input-wrap"><input id="email" name="email" type="email" value="{{ old('email', $email ?? '') }}" autocomplete="email" maxlength="150" required></div>
          </div>
          @if($isReset)
            <div class="field">
              <label for="password">New password</label>
              <div class="input-wrap"><input id="password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="72" required></div>
            </div>
            <div class="field">
              <label for="password_confirmation">Confirm new password</label>
              <div class="input-wrap"><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" maxlength="72" required></div>
            </div>
          @endif
          @if($errors->any())
            <p class="form-message error" role="alert">{{ $errors->first() }}</p>
          @endif
          @if(session('formSuccess'))
            <p class="form-message success" role="status">{{ session('formSuccess') }}</p>
          @endif
          <button class="submit-button" type="submit">{{ $isReset ? 'Save new password' : 'Send reset link' }}</button>
        </form>
        <p class="switch-prompt"><a href="{{ route('login') }}">Back to log in</a></p>
      </section>
    </div>
  </main>
</body>
</html>
