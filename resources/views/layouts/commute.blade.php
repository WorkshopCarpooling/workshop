<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <title>{{ $title }} · RideUTeM</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
  @foreach(['dashboard','driver','usability','interface-refresh','campus-interface','journey','student-journey'] as $style)<link rel="stylesheet" href="{{ asset('css/'.$style.'.css') }}">@endforeach
  @include('partials.device-head')
  @include('partials.theme-head')
  <link rel="stylesheet" href="{{ asset('css/timetable-commutes.css') }}?v={{ filemtime(public_path('css/timetable-commutes.css')) }}">
  @yield('extra-head')
</head>
<body class="app-page timetable-page">
<div class="app-shell">
  @include('partials.app-nav',['activePage'=>'timetable'])
  <main class="main-area">
    <header class="topbar"><div class="breadcrumb">Workspace <span>/</span> Timetable</div></header>
    <div class="page-content">
      <div class="welcome-row"><div><div class="date-line"><span class="date-dot"></span> YOUR WEEK, CONNECTED</div><h1>{{ $title }}</h1><p class="subtitle">{{ $subtitle }}</p></div><a class="outline-button" href="{{ url('passenger/schedule') }}">My ride appointments →</a></div>
      @if(session('commuteNotice'))<p class="driver-notice success" role="status">{{ session('commuteNotice') }}</p>@endif
      @if($errors->any())<div class="validation-errors" role="alert">{{ $errors->first() }}</div>@endif
      @yield('content')
      <footer class="dashboard-footer">RideUTeM · Your campus, better connected.</footer>
    </div>
  </main>
</div>
@include('partials.device-tools')
@yield('scripts')
</body>
</html>
