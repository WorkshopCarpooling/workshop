<section class="content-card member-directory" id="members" aria-labelledby="memberDirectoryTitle">
  <div class="section-head"><div><span class="section-kicker">YOUR CARPOOL COMMUNITY</span><h2 id="memberDirectoryTitle">Member directory</h2></div><span class="verification-pill">{{ $pendingMembers->count() }} awaiting approval</span></div>
  <p class="member-directory-intro">View passengers and drivers, approve new accounts and manage access.</p>
  @if($memberNotice!=='')<div class="member-notice {{ $memberNoticeType }}" role="status">{{ $memberNotice }}</div>@endif
  @if($errors->any())<div class="validation-errors" role="alert">{{ $errors->first() }}</div>@endif
  @if(!$directoryReady)<p class="member-access-note">The directory is available. Remove and restore actions are temporarily unavailable.</p>@endif
  <nav class="member-tabs" aria-label="Member types">
    @foreach(['all'=>'All members','passenger'=>'Passengers','driver'=>'Drivers','removed'=>'Removed'] as $key=>$label)
      <a href="{{ url('admin/members').'?'.http_build_query(['type'=>$key,'q'=>$directoryFilters['q']]) }}" class="{{ $directoryFilters['type']===$key?'selected':'' }}" @if($directoryFilters['type']===$key) aria-current="true" @endif>{{ $label }}<span>{{ $directoryCounts[$key] }}</span></a>
    @endforeach
  </nav>
  <form class="member-filters" method="get" action="{{ url('admin/members') }}">
    <input type="hidden" name="type" value="{{ $directoryFilters['type'] }}">
    <label class="member-search-label" for="memberSearch">Search members<input id="memberSearch" type="search" name="q" maxlength="100" value="{{ $directoryFilters['q'] }}" placeholder="Name or email address"></label>
    @if($directoryFilters['type']!=='removed')<label for="memberStatus">Account status<select id="memberStatus" name="status"><option value="">All statuses</option>@foreach(['active'=>'Active','pending'=>'Awaiting approval','suspended'=>'Suspended'] as $value=>$label)<option value="{{ $value }}" @selected($directoryFilters['status']===$value)>{{ $label }}</option>@endforeach</select></label>@endif
    <button class="solid-button" type="submit">Find members</button><a class="member-reset" href="{{ url('admin/members?type='.$directoryFilters['type']) }}">Reset</a>
  </form>
  <div class="member-table-scroll" role="region" aria-label="Passenger and driver accounts" tabindex="0">
    <table class="member-table"><thead><tr><th>Member</th><th>Account type</th><th>Status</th><th>{{ $directoryFilters['type']==='removed'?'Removed':'Joined' }}</th><th>Actions</th></tr></thead><tbody>
    @forelse($members as $member)
      @php($inTrip=$member->ongoing_driver_trips+$member->ongoing_passenger_trips>0)
      <tr id="member-{{ $member->user_id }}">
        <td><b>{{ $member->full_name }}</b><small>{{ $member->email }}</small></td>
        <td><span class="member-type {{ $member->account_type }}">{{ ucfirst($member->account_type) }}</span></td>
        <td><span class="member-state {{ $member->removed_at?'removed':$member->account_status }}">{{ $member->removed_at?'Removed':($member->account_status==='pending'?'Awaiting approval':ucfirst($member->account_status)) }}</span>@if($inTrip)<small>In an ongoing trip</small>@endif @if($member->removed_at)<small>Previously {{ $member->status_before_removal==='pending'?'awaiting approval':$member->status_before_removal }}</small>@endif</td>
        <td>{{ ($member->removed_at??$member->created_at)?->format('j M Y')??'—' }}</td>
        <td><div class="member-row-actions">
          @if($member->removed_at)
            <form method="post" action="{{ url('admin/members/'.$member->user_id.'/restore') }}" data-member-action="restore">@csrf<button class="member-restore" type="submit" @disabled(!$directoryReady)>Restore access</button></form>
          @else
            @if($member->account_status==='pending')<form method="post" action="{{ url('admin.php') }}">@csrf<input name="action" type="hidden" value="approve_member"><input name="user_id" type="hidden" value="{{ $member->user_id }}"><button class="member-approve" type="submit">Approve</button></form>@endif
            <form method="post" action="{{ url('admin/members/'.$member->user_id.'/remove') }}" data-member-action="remove" data-member-name="{{ $member->full_name }}">@csrf<button class="member-remove" type="submit" @disabled(!$directoryReady||$inTrip) @if($inTrip) title="Finish the ongoing trip before removing this member" @endif>Remove</button></form>
          @endif
        </div></td>
      </tr>
    @empty<tr><td colspan="5" class="member-empty">No {{ $directoryFilters['type']==='removed'?'removed accounts':'members' }} match these filters.</td></tr>@endforelse
    </tbody></table>
  </div>
  <div class="member-pagination"><span>{{ $members->total()?($members->firstItem().'–'.$members->lastItem().' of '.$members->total().' members'):'0 members' }}</span><div>@if($members->previousPageUrl())<a href="{{ $members->previousPageUrl() }}">← Previous</a>@endif @if($members->nextPageUrl())<a href="{{ $members->nextPageUrl() }}">Next →</a>@endif</div></div>
  <p class="member-access-note">Remove blocks sign-in and cancels unstarted bookings and requests. Past trips remain in the system. Restore returns the previous account status; it does not restart cancelled journeys. Members in an ongoing trip must finish it first.</p>
</section>
