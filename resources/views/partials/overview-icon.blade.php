<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($icon)
  @case('car')<path d="m5 7 2-4h10l2 4m-15 2 1-2h14l1 2v9H4V9Zm0 4h16M7 18v3m10-3v3M7 10h1m8 0h1"/>@break
  @case('location')<path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0Z"/><circle cx="12" cy="10" r="2.5"/>@break
  @case('calendar')<rect x="4" y="5" width="16" height="16" rx="3"/><path d="M8 3v4m8-4v4M4 11h16m-11 5h2"/>@break
  @case('clock')<circle cx="12" cy="12" r="8.5"/><path d="M12 7v5l3 2"/>@break
  @case('check')<path d="m7 12 3 3 7-7"/><circle cx="12" cy="12" r="8.5"/>@break
  @case('route')<circle cx="6" cy="6" r="2.5"/><circle cx="18" cy="18" r="2.5"/><path d="M9 6h6a4 4 0 0 1 0 8H9a4 4 0 0 0 0 8h5"/>@break
  @case('arrow')<path d="M6 18 18 6M6 6h12v12"/>@break
  @case('chevron')<path d="m10 6 6 6-6 6"/>@break
  @case('leaf')<path d="M20 4C8 2 3 8 5 15c2 7 16 5 15-11Z"/><path d="M4 21 15 10m-7 7v-5m3 2h5"/>@break
  @case('history')<path d="M4 10a8.5 8.5 0 1 1 1 7M4 4v6h6m2-3v5l3 2"/>@break
  @case('ticket')<path d="M4 5h16v5a2 2 0 0 0 0 4v5H4v-5a2 2 0 0 0 0-4V5Z"/><path d="M15 5v3m0 3v2m0 3v3"/>@break
  @case('people')<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3m1-16a3 3 0 0 1 0 6m3 10v-3a6 6 0 0 0-2-4"/>@break
  @default<circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/>
@endswitch
</svg>
