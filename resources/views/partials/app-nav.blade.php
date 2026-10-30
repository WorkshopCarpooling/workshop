@php
  $navUser = Auth::user();
  $navPath = trim(request()->path(), '/');
  if ($navUser->isAdmin()) {
    $navHome = 'admin.php';
    $navItems = [['admin_overview','admin.php','⌂','Overview'],['admin_live','admin/live','◎','Live drivers'],['admin_rides','admin/rides','▤','Ride activity'],['admin_members','admin/members','♙','Members'],['admin_vehicles','admin/vehicles','▣','Vehicles'],['carbon','carbon','♧','Carbon reports']];
    $navCurrent = in_array($navPath,['admin.php','admin','']) ? 'admin_overview' : str_replace('/','_',$navPath);
  } elseif ($navUser->isDriver()) {
    $navHome = 'driver.php';
    $navItems = [['driver_overview','driver.php','⌂','Overview'],['driver_offer','driver/offer','＋','Offer ride'],['driver_requests','driver/requests','▤','Ride requests'],['driver_schedule','driver/schedule','◷','Schedule'],['driver_vehicles','driver/vehicles','▣','Vehicles'],['carbon','carbon','♧','Carbon']];
    $navCurrent = in_array($navPath,['driver.php','driver','']) ? 'driver_overview' : str_replace('/','_',$navPath);
  } else {
    $navHome = 'dashboard.php';
    $navItems = [['passenger_overview','dashboard.php','⌂','Overview'],['passenger_find','findride.php','⌕','Find ride'],['passenger_bookings','passenger/bookings','▤','Bookings'],['passenger_schedule','passenger/schedule','◷','Schedule'],['carbon','carbon','♧','Carbon'],['timetable','timetable','▦','Timetable']];
    $navCurrent = in_array($navPath,['dashboard.php','dashboard','']) ? 'passenger_overview' : (in_array($navPath,['findride.php','findride']) ? 'passenger_find' : str_replace('/','_',$navPath));
  }
  $navCurrent = $activePage ?? $navCurrent;
@endphp
<aside class="sidebar" id="workspaceSidebar">
  <a class="app-brand" href="{{ url($navHome) }}" aria-label="RideUTeM home">@include('partials.brand-logo')</a>
  <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-controls="workspaceSidebar" aria-expanded="true" aria-label="Minimise sidebar" title="Minimise sidebar" hidden><span aria-hidden="true">«</span></button>
  <p class="workspace-nav-label">{{ $navUser->isAdmin() ? 'ADMINISTRATION' : ($navUser->isDriver() ? 'DRIVER WORKSPACE' : 'YOUR WORKSPACE') }}</p>
  <nav class="side-nav" aria-label="{{ $navUser->isAdmin() ? 'Admin navigation' : 'Main navigation' }}">
    @foreach ($navItems as [$navKey,$navUrl,$navIcon,$navLabel])
      <a class="side-link {{ $navCurrent === $navKey ? 'active' : '' }}" href="{{ url($navUrl) }}" aria-label="{{ $navLabel }}" title="{{ $navLabel }}" @if($navCurrent === $navKey) aria-current="page" @endif><span aria-hidden="true">@include('partials.overview-icon',['icon'=> match(true) { str_contains($navKey,'overview')=>'route', str_contains($navKey,'find'),str_contains($navKey,'offer')=>'arrow', str_contains($navKey,'schedule')=>'clock', str_contains($navKey,'booking'),str_contains($navKey,'request'),str_contains($navKey,'rides')=>'ticket', str_contains($navKey,'carbon')=>'leaf', str_contains($navKey,'timetable')=>'calendar', str_contains($navKey,'vehicles')=>'car', str_contains($navKey,'live')=>'location', default=>'people' }])</span><span class="nav-link-label">{{ $navLabel }}</span></a>
    @endforeach
  </nav>
  <div class="workspace-sidebar-note"><span class="workspace-community-dot" aria-hidden="true"></span><span>A shared way forward.<small>Made for the UTeM community.</small></span></div>
  @include('partials.account-nav')
</aside>
