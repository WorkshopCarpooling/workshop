<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#244b39">
  <title>{{ $pageTitle }} · RideUTeM</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
  <link rel="stylesheet" href="{{ asset('css/usability.css') }}">
  <link rel="stylesheet" href="{{ asset('css/vehicle-approval.css') }}">
  <link rel="stylesheet" href="{{ asset('css/interface-refresh.css') }}">
  <link rel="stylesheet" href="{{ asset('css/campus-interface.css') }}">
  <link rel="stylesheet" href="{{ asset('css/journey.css') }}">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <link rel="stylesheet" href="{{ asset('css/live-tracking.css') }}?v={{ filemtime(public_path('css/live-tracking.css')) }}">
<link rel="stylesheet" href="{{ asset('css/workspace-pages.css') }}?v=20261027-pages-carbon">
@include('partials.device-head')
  @include('partials.theme-head')
  @if($section==='members')<link rel="stylesheet" href="{{ asset('css/admin-members.css') }}?v={{ is_file(public_path('css/admin-members.css'))?filemtime(public_path('css/admin-members.css')):'members' }}">@endif
</head>
<body class="app-page admin-page">
<div class="app-shell">
  @include('partials.app-nav')

  <main class="main-area">
    <header class="topbar">
      <div class="breadcrumb">Administration <span>/</span> {{ $pageTitle }}</div>
    </header>

    <div class="page-content admin-content">
      <div class="welcome-row">
        <div>
          <div class="date-line"><span class="date-dot"></span> ADMINISTRATION</div>
          <h1>{{ $pageTitle }}</h1>
          <p class="subtitle">Manage the UTeM student carpool community.</p>
        </div>
        <div class="admin-actions">
          <a class="outline-button" href="{{ url('carbon') }}">Carbon reports ↗</a>
          <button class="solid-button" id="printReport">Print summary <span>↗</span></button>
        </div>
      </div>

      @include('admin.'.$section)
  <footer class="dashboard-footer">
        <span>RideUTeM admin · Member approvals are active</span>
        <span>Live trips, approvals and completed carbon estimates have their own pages.</span>
      </footer>
    </div>
  </main>
</div>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script src="{{ asset('js/admin.js') }}" defer></script>
@if($section==='members')<script src="{{ asset('js/admin-members.js') }}?v={{ is_file(public_path('js/admin-members.js'))?filemtime(public_path('js/admin-members.js')):'members' }}" defer></script>@endif
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
<script src="{{ asset('js/admin-live-tracking.js') }}?v={{ filemtime(public_path('js/admin-live-tracking.js')) }}" defer></script>
@include('partials.device-tools')
</body>
</html>
