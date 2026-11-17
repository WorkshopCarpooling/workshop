<details class="member-application">
  <summary>{{ $applicationStatus === 'pending' ? 'Review application' : 'Application details' }}</summary>
  @if($member->application_review)
    @php($review = $member->application_review)
    <small>
      {{ match($review['decision']) { 'approved' => 'Approved', 'declined' => 'Declined', 'resubmit' => 'Replacement requested' } }}
      · Admin #{{ $review['reviewed_by'] }}
      · {{ \Illuminate\Support\Carbon::parse($review['reviewed_at'])->timezone(config('app.timezone'))->format('j M Y, H:i') }}
    </small>
    @if($review['reason'])<small>{{ $review['reason'] }}</small>@endif
  @endif

  @if($applicationStatus === 'pending')
    @if($member->isDriver() && $member->driver_license_path)
      <a href="{{ route('admin.members.licence', ['member' => $member->getKey(), 'version' => $member->applicationVersion()]) }}"
        target="_blank" rel="noopener noreferrer">Open driving licence in a new tab</a>
    @elseif($member->isDriver())
      <small>Awaiting a driving licence. The applicant can upload one after signing in.</small>
    @endif

    @if(!$member->isDriver() || $member->driver_license_path)
      <form method="post" action="{{ url('admin.php') }}">
        @csrf
        <input name="user_id" type="hidden" value="{{ $member->getKey() }}">
        <input name="licence_version" type="hidden" value="{{ $member->applicationVersion() }}">
        @if($member->isDriver())
          <label><input type="checkbox" name="licence_checked" value="1" required> I verified the driving licence</label>
        @endif
        <button class="member-approve" name="action" value="approve_member" type="submit">Approve</button>
      </form>
    @endif

    <form method="post" action="{{ url('admin.php') }}">
      @csrf
      <input name="user_id" type="hidden" value="{{ $member->getKey() }}">
      <input name="licence_version" type="hidden" value="{{ $member->applicationVersion() }}">
      <label for="review-reason-{{ $member->getKey() }}">Reason shown to the applicant</label>
      <textarea id="review-reason-{{ $member->getKey() }}" name="review_reason" rows="2" maxlength="500" required></textarea>
      @if($member->isDriver())
        <button name="action" value="request_licence" type="submit">Request replacement</button>
      @endif
      <button class="member-remove" name="action" value="decline_member" type="submit">Decline</button>
    </form>
  @endif
</details>
