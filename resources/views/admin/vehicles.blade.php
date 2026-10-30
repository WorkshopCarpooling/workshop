      <section class="content-card members-card vehicle-approval-card" id="vehicles">
        <div class="section-head">
          <div><span class="section-kicker">DRIVER VERIFICATION</span><h2>Vehicle approval</h2></div>
          <span class="verification-pill">{{ count($pendingVehicles) }} pending</span>
        </div>
        @if ($vehicleNotice !== '')
          <div class="member-notice {{ $vehicleNoticeType }}" role="status">{{ $vehicleNotice }}</div>
        @endif
        @if ($pendingVehicles->isNotEmpty())
          <div class="member-list">
            @foreach ($pendingVehicles as $vehicle)
              <div class="member-review">
                <span class="user-avatar pending-avatar">V</span>
                <span class="member-info">
                  <b>{{ $vehicle->vehicle_type }} · {{ $vehicle->vehicle_color }} · {{ $vehicle->vehicle_plate }}</b>
                  <small>Driver: {{ $vehicle->user->full_name }} · {{ $vehicle->user->email }} · Submitted {{ $vehicle->created_at->format('M j, Y') }}</small>
                </span>
                <form method="post" action="{{ url('admin.php') }}" class="approve-form">
                  @csrf
                  <input type="hidden" name="vehicle_id" value="{{ $vehicle->vehicle_id }}">
                  <input type="hidden" name="action" value="approve_vehicle">
                  <button class="approve-button" type="submit">Approve vehicle</button>
                </form>
                <form method="post" action="{{ url('admin.php') }}" class="approve-form vehicle-reject-form">
                  @csrf
                  <input type="hidden" name="vehicle_id" value="{{ $vehicle->vehicle_id }}">
                  <input type="hidden" name="action" value="reject_vehicle">
                  <input name="admin_note" maxlength="255" placeholder="Reason (optional)" aria-label="Reason for declining vehicle">
                  <button class="approve-button vehicle-reject-button" type="submit">Decline</button>
                </form>
              </div>
            @endforeach
          </div>
        @else
          <div class="empty-member">
            <span class="user-avatar pending-avatar">✓</span>
            <span class="member-info"><b>No vehicles awaiting review</b><small>Driver vehicle submissions will appear here.</small></span>
          </div>
        @endif
      </section>
