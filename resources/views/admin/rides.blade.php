<section class="content-card admin-table-card" id="rides">
  <div class="section-head"><div><span class="section-kicker">RIDE OPERATIONS</span><h2>Ride activity</h2></div>
    <form method="get" action="{{ url('admin/rides') }}"><label>Ride status<select name="status" onchange="this.form.submit()"><option value="">All activity</option>@foreach(['scheduled','in_progress','completed','cancelled'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucwords(str_replace('_',' ',$status)) }}</option>@endforeach</select></label><noscript><button type="submit">Filter</button></noscript></form>
  </div>
  <div class="table-scroll"><table><thead><tr><th>RIDE & ROUTE</th><th>DRIVER</th><th>DEPARTURE</th><th>AVAILABLE SEATS</th><th>STATUS</th></tr></thead><tbody>
    @forelse($activity as $ride)<tr><td>#{{ $ride->ride_id }} · {{ $ride->pickup_point }} → {{ $ride->destination }}</td><td>{{ $ride->driver->full_name ?? 'Account unavailable' }}</td><td>{{ $ride->departure_at->format('d M Y, g:i A') }}</td><td>{{ $ride->available_seats }}</td><td>{{ ucwords(str_replace('_',' ',$ride->ride_status)) }}</td></tr>
    @empty<tr><td colspan="5">No rides match this filter.</td></tr>@endforelse
  </tbody></table></div><p class="journey-status">Showing up to 100 recent trips. Available seats reflect confirmed bookings.</p>
</section>
