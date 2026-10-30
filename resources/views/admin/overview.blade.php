<div class="workspace-stats">
  <article><small>STUDENT MEMBERS</small><strong>{{ $memberCount }}</strong><small>{{ $pendingMembers->count() }} waiting for approval</small></article>
  <article><small>SCHEDULED RIDES</small><strong>{{ $scheduledCount }}</strong><small>Published trips waiting to start</small></article>
  <article><small>ACTIVE TRIPS</small><strong>{{ $activeCount }}</strong><small>View cars and GPS status under Live drivers</small></article>
  <article><small>ESTIMATED NET CO₂e AVOIDED</small><strong>{{ $carbonSummary['count'] ? number_format($carbonSummary['avoided'],2).' kg' : '—' }}</strong><small>{{ $carbonSummary['count'] }} completed comparisons with complete inputs</small></article>
</div>
<div class="workspace-tiles">
  <a class="workspace-tile" href="{{ url('admin/live') }}"><b>Live drivers →</b><small>Follow active student trips on the map.</small></a>
  <a class="workspace-tile" href="{{ url('admin/rides') }}"><b>Ride activity →</b><small>Review scheduled, active, completed and cancelled journeys.</small></a>
  <a class="workspace-tile" href="{{ url('admin/members') }}"><b>Members · {{ $pendingMembers->count() }} pending</b><small>Verify UTeM student status and approve new accounts.</small></a>
  <a class="workspace-tile" href="{{ url('admin/vehicles') }}"><b>Vehicles · {{ $pendingVehicles->count() }} pending</b><small>Review vehicle submissions.</small></a>
  <a class="workspace-tile" href="{{ url('carbon') }}"><b>Carbon reports →</b><small>Completed-trip estimates, calculation assumptions and CSV export.</small></a>
</div>
