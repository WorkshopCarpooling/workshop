@php
  $navUser = Auth::user();
  $profileHistory = $navUser->isDriver() ? 'driver/history' : ($navUser->isPassenger() ? 'passenger/history' : null);
  $historyCurrent = $profileHistory && trim(request()->path(), '/') === $profileHistory;
@endphp
<div class="account-nav" aria-label="Your account">
  <details class="profile-menu" data-profile-menu @if($historyCurrent) open @endif>
    <summary class="profile-trigger" aria-label="Profile menu for {{ $navUser->full_name }}">
      @include('partials.user-avatar', ['avatarUser' => $navUser])
      <span class="account-nav-name"><b>{{ $navUser->full_name }}</b><small>{{ $navUser->isAdmin() ? 'Administrator' : ucfirst($navUser->role) . ' · ' . ucfirst($navUser->account_type) }}</small></span>
      <span class="profile-chevron" aria-hidden="true">⌄</span>
    </summary>
    <div class="profile-menu-panel">
      <p class="profile-menu-label">MY ACCOUNT</p>
      <a class="profile-menu-link {{ request()->routeIs('profile.edit') ? 'active' : '' }}" href="{{ url('profile') }}" @if(request()->routeIs('profile.edit')) aria-current="page" @endif><span aria-hidden="true">✎</span> Edit profile</a>
      @if($profileHistory)
        <a class="profile-menu-link {{ $historyCurrent ? 'active' : '' }}" href="{{ url($profileHistory) }}" aria-label="History" @if($historyCurrent) aria-current="page" @endif><span aria-hidden="true">↺</span> History</a>
      @endif
      <form method="post" action="{{ url('logout.php') }}" class="logout-form">@csrf<button class="logout-button" type="submit" aria-label="Log out"><span aria-hidden="true">↪</span> Log out</button></form>
    </div>
  </details>
</div>
