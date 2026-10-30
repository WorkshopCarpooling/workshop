<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#101b27">
  <title>{{ $isSignup ? 'Sign up' : 'Log in' }} · RideUTeM</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="preload" as="image" href="{{ asset('images/utem-campus-aerial.png') }}">
  @include('partials.device-head')
  <link rel="stylesheet" href="{{ asset('css/campus-auth.css') }}?v={{ filemtime(public_path('css/campus-auth.css')) }}">
  @include('partials.theme-head')
</head>
<body class="campus-auth-page {{ $isSignup ? 'signup-page' : 'login-page' }}">
  <div class="campus-backdrop" aria-hidden="true"><img src="{{ asset('images/utem-campus-aerial.png') }}" alt="" width="1600" height="583" fetchpriority="high"><div class="campus-shade"></div></div>
  <main class="auth-canvas">
    <div class="auth-stack {{ $isSignup ? 'signup-stack' : '' }}">
      <header class="auth-identity"><a class="auth-brand" href="{{ url('welcome') }}" aria-label="RideUTeM home">ride<span>UTeM</span><i aria-hidden="true">.</i></a><p>Your campus. Better connected.</p></header>
      <section class="auth-card" aria-labelledby="auth-title">
        <header class="auth-card-heading"><h1 id="auth-title">{{ $isSignup ? 'Join the journey.' : 'Welcome back.' }}</h1><p>{{ $isSignup ? 'Create your account and find your way together.' : 'Sign in for your next campus journey.' }}</p></header>
        @php
          $formError = session('formError', '') ?: ($errors->any() ? $errors->first() : '');
          $formSuccess = session('formSuccess', '');
        @endphp
        <form class="auth-form" id="authForm" method="post" action="{{ $isSignup ? url('signup.php') : url('login.php') }}" novalidate data-mode="{{ $isSignup ? 'signup' : 'login' }}">
          @csrf
          @if ($isSignup)
            <div class="fields-grid">
              <div class="field"><label for="fullName">Full name</label><div class="input-wrap"><input id="fullName" name="name" type="text" maxlength="100" placeholder="Your full name" autocomplete="name" value="{{ old('name') }}" required></div></div>
              <div class="field"><label for="email">University email</label><div class="input-wrap"><input id="email" name="email" type="email" placeholder="name@utem.edu.my" autocomplete="email" value="{{ old('email') }}" required></div></div>
            </div>
            <div class="fields-grid">
              <div class="field"><label for="memberType">I’m joining as</label><div class="input-wrap"><select id="memberType" name="memberType" required><option value="student">UTeM student</option></select></div></div>
              <div class="field"><label for="accountType">Account role</label><div class="input-wrap"><select id="accountType" name="accountType" required><option value="" disabled {{ old('accountType') ? '' : 'selected' }}>Choose your role</option><option value="passenger" @selected(old('accountType') === 'passenger')>Passenger — find rides</option><option value="driver" @selected(old('accountType') === 'driver')>Driver — offer rides</option></select></div></div>
            </div>
            <div class="fields-grid">
              <div class="field"><label for="password">Password</label><div class="input-wrap password-wrap"><input id="password" name="password" type="password" placeholder="At least 8 characters" autocomplete="new-password" minlength="8" required><button class="show-password" type="button" aria-label="Show password" data-password-toggle="password"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M2 10s2.8-5 8-5 8 5 8 5-2.8 5-8 5-8-5-8-5Z"/><circle cx="10" cy="10" r="2.2"/></svg></button></div></div>
              <div class="field"><label for="confirmPassword">Confirm password</label><div class="input-wrap password-wrap"><input id="confirmPassword" name="confirmPassword" type="password" placeholder="Repeat your password" autocomplete="new-password" minlength="8" required><button class="show-password" type="button" aria-label="Show password" data-password-toggle="confirmPassword"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M2 10s2.8-5 8-5 8 5 8 5-2.8 5-8 5-8-5-8-5Z"/><circle cx="10" cy="10" r="2.2"/></svg></button></div></div>
            </div>
            <label class="checkbox-row"><input type="checkbox" name="genderPreference"><span>Show me rides with a same-gender preference</span></label>
          @else
            <div class="field"><label for="email">University email</label><div class="input-wrap"><input id="email" name="email" type="email" placeholder="Enter your university email" autocomplete="email" value="{{ old('email') }}" required></div></div>
            <div class="field"><label for="password">Password</label><div class="input-wrap password-wrap"><input id="password" name="password" type="password" placeholder="Enter your password" autocomplete="current-password" minlength="8" required><button class="show-password" type="button" aria-label="Show password" data-password-toggle="password"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M2 10s2.8-5 8-5 8 5 8 5-2.8 5-8 5-8-5-8-5Z"/><circle cx="10" cy="10" r="2.2"/></svg></button></div></div>
            <div class="form-options"><label class="checkbox-row"><input type="checkbox" name="remember"><span>Remember me</span></label><a href="#" id="forgotLink">Forgot password?</a></div>
          @endif
          <div class="form-message {{ $formError !== '' ? 'error' : ($formSuccess !== '' ? 'success' : '') }}" id="formMessage" role="status" aria-live="polite">{{ $formError !== '' ? $formError : $formSuccess }}</div>
          <button class="submit-button" type="submit">{{ $isSignup ? 'Create account' : 'Log in' }}<span aria-hidden="true">↗</span></button>
        </form>
        <p class="switch-prompt">{{ $isSignup ? 'Already have an account?' : 'New to the community?' }} <a href="{{ $isSignup ? url('login.php') : url('signup.php') }}">{{ $isSignup ? 'Log in' : 'Sign up' }} <span aria-hidden="true">↗</span></a></p>
        <p class="approval-note"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 2 4 4.5v4.3c0 4 2.6 7.4 6 8.7 3.4-1.3 6-4.7 6-8.7V4.5L10 2Z"/><path d="m7 9.5 2 2 4-4"/></svg>For UTeM students. Admin approval is required.</p>
      </section>
    </div>
  </main>
  <footer class="campus-photo-caption"><span>UNIVERSITI TEKNIKAL MALAYSIA MELAKA</span><span>Move together, thoughtfully.</span></footer>
  <script src="{{ asset('js/auth.js') }}?v={{ filemtime(public_path('js/auth.js')) }}" defer></script>
  @include('partials.device-tools')
</body>
</html>