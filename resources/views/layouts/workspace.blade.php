<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#244b39"><title>{{ $title }} · RideUTeM</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  @foreach (['dashboard','driver','usability','interface-refresh','campus-interface','journey','live-tracking','workspace-pages'] as $style)<link rel="stylesheet" href="{{ asset('css/'.$style.'.css') }}?v=20261027-pages-carbon">@endforeach
  @include('partials.device-head')
  @include('partials.theme-head')
  @stack('styles')
</head>
<body class="app-page research-page">
<div class="app-shell">@include('partials.app-nav')<main class="main-area">
  <header class="topbar"><div class="breadcrumb">RideUTeM <span>/</span> {{ $title }}</div></header>
  <div class="page-content">
    <div class="welcome-row"><div><div class="date-line"><span class="date-dot"></span> YOUR CAMPUS WORKSPACE</div><h1>{{ $title }}</h1><p class="subtitle">{{ $subtitle ?? '' }}</p></div></div>
    @if ($errors->any())<div class="validation-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @if(session('carbonNotice'))<p class="driver-notice success" role="status">{{ session('carbonNotice') }}</p>@endif
    @yield('content')
  </div>
</main></div>
@include('partials.driver-live-gps')
<script src="{{ asset('js/carbon-forms.js') }}?v=20261027-carbon" defer></script>
@include('partials.device-tools')
@stack('scripts')
</body></html>
