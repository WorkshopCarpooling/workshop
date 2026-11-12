<aside class="sidebar overview-sidebar">
  <a class="app-brand" href="{{ url('dashboard.php') }}" aria-label="RideUTeM overview">@include('partials.brand-logo')</a>
  <p class="overview-nav-label">YOUR WORKSPACE</p>
  <nav class="side-nav" aria-label="Main navigation">
    @foreach ([['dashboard.php','route','Overview'],['findride.php','arrow','Find a ride'],['passenger/bookings','ticket','Bookings'],['passenger/schedule','clock','Schedule'],['timetable','calendar','Timetable'],['carbon','leaf','Carbon impact']] as [$navUrl,$navIcon,$navLabel])
      <a class="side-link {{ $navUrl === 'dashboard.php' ? 'active' : '' }}" href="{{ url($navUrl) }}" @if($navUrl === 'dashboard.php') aria-current="page" @endif><span>@include('partials.overview-icon',['icon'=>$navIcon])</span>{{ $navLabel }}</a>
    @endforeach
  </nav>
  <div class="overview-sidebar-note"><span class="overview-community-dot" aria-hidden="true"></span><span>A shared way forward.<small>Made for the UTeM community.</small></span></div>
  @include('partials.account-nav')
</aside>
