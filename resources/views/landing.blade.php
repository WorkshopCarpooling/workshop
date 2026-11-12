<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#eff1e7">
  <meta name="description" content="RideUTeM — share your everyday journey to campus.">
  <title>RideUTeM</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ filemtime(public_path('css/landing.css')) }}">
  <link rel="preload" as="image" href="{{ asset('images/welcome-classic-car.png') }}?v=2">
  <script src="{{ asset('js/landing.js') }}?v={{ filemtime(public_path('js/landing.js')) }}" defer></script>
  @include('partials.theme-head')
</head>
<body class="landing-page">
  <main class="entrance-stage" id="main">
    <div class="entrance-copy">
      <h1 class="system-title" aria-label="RideUTeM">ride<span>UTeM</span><span class="title-period" aria-hidden="true">.</span></h1>
      <a class="landing-button" id="startJourney" href="{{ url('login.php') }}">Let’s ride together <span aria-hidden="true">↗</span></a>
    </div>

    <div class="welcome-display" role="img" aria-label="A cinematic display of the orange classic car">
      <div class="welcome-display-halo" aria-hidden="true"></div>
      <div class="welcome-display-floor" aria-hidden="true"></div>
      <img class="welcome-display-image" src="{{ asset('images/welcome-classic-car.png') }}?v=2" width="1536" height="1024" alt="" fetchpriority="high" decoding="async">
      <div class="welcome-display-light" aria-hidden="true"></div>
    </div>
    <div class="cinema-scene" id="cinemaScene" aria-hidden="true">
      <div class="cinema-halo"></div>
      <div class="cinema-ground"></div>
      <div class="cinema-shot shot-wide"><img class="cinema-car" src="{{ asset('images/welcome-classic-car.png') }}?v=2" width="1536" height="1024" alt="" fetchpriority="high" decoding="async"></div>
      <div class="cinema-shot shot-rear"><img class="cinema-car" src="{{ asset('images/welcome-classic-rear.png') }}?v=1" width="1536" height="1024" alt="" decoding="async"></div>
      <div class="cinema-light"></div>
      <div class="cinema-bar bar-top"></div><div class="cinema-bar bar-bottom"></div>
      <div class="cinema-exit"></div>
    </div>
    <p class="screen-reader-status" id="journeyStatus" role="status" aria-live="polite"></p>
  </main>
</body>
</html>