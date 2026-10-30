@extends('layouts.commute',['title'=>'A timetable that plans your rides.','subtitle'=>'Add your weekly sessions, then connect each one to a ride that fits your day.'])
@section('content')
@if(!$ready)
  <p class="validation-errors">Your timetable is temporarily unavailable. Please try again later.</p>
@else
<div class="timetable-intro"><div><b>Add a session. Plan the journey once.</b><p>We estimate a ride time from your class start, the route and your arrival buffer. Book the next session or repeat weekly until your chosen end date.</p></div><span>Robot reminders<br><b>30 and 10 minutes before</b></span></div>
<div class="timetable-grid" id="timetable">
  <section class="content-card timetable-add" aria-labelledby="addSessionTitle">
    <span class="commute-kicker">YOUR WEEKLY ROUTINE</span><h2 id="addSessionTitle">Add a class or work session</h2>
    <form method="post" action="{{ url('timetable') }}">
      @csrf
      <label>Session name<input name="title" maxlength="100" value="{{ old('title') }}" placeholder="e.g. Software engineering" required></label>
      <label>Day<select name="weekday">@foreach(['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $day=>$name)<option value="{{ $day }}" @selected((int)old('weekday',1)===$day)>{{ $name }}</option>@endforeach</select></label>
      <label>Building / location<input name="location" maxlength="180" value="{{ old('location') }}" placeholder="e.g. FTMK Main Campus" required></label>
      <div class="commute-pair"><label>Starts at<input name="starts_at" type="time" value="{{ old('starts_at') }}" required></label><label>Ends at<input name="ends_at" type="time" value="{{ old('ends_at') }}" required></label></div>
      <button class="solid-button" type="submit">Add session →</button>
    </form>
    <p class="commute-help">After adding a session, choose “Plan my ride” to set its pickup and destination.</p>
  </section>
  <section class="timetable-sessions" aria-labelledby="sessionsTitle">
    <div class="section-head"><h2 id="sessionsTitle">My weekly sessions</h2><span>{{ $sessions->count() }} sessions</span></div>
    @forelse($sessions as $event)
      @php
        $plan=$plans[$event->id]??null;
        $next=($nextRides[$event->id]??collect())->first();
        $days=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
      @endphp
      <article class="content-card timetable-session" id="session-{{ $event->id }}">
        <div class="session-heading"><span class="session-day">{{ strtoupper(substr($days[$event->weekday],0,3)) }}</span><div><h3>{{ $event->title }}</h3><p>{{ \Carbon\Carbon::parse($event->starts_at)->format('g:i A') }}–{{ \Carbon\Carbon::parse($event->ends_at)->format('g:i A') }} · {{ $event->location }}</p></div></div>
        @if($plan)
          <div class="session-plan"><b>{{ $plan->is_enabled?'Weekly rides enabled':($plan->ends_on->copy()->endOfDay()->isPast()?'Weekly plan ended':'Weekly rides paused') }}</b><span>{{ $plan->pickup_label }} → {{ $plan->dropoff_label }}</span><small>{{ $plan->seats_requested }} passenger(s) · RM {{ number_format((float)$plan->fare_per_seat*$plan->seats_requested,2) }} total · through {{ $plan->ends_on->format('j M Y') }}</small></div>
        @endif
        @if($next)
          <a class="session-next" href="{{ url('passenger/schedule#appointment-'.$next->request_id) }}"><span>Next ride appointment</span><b>{{ $next->scheduled_for->format('D, j M · g:i A') }}</b><small>View in Schedule →</small></a>
        @elseif(!$plan)
          <p class="commute-help">Connect this session to a ride so your trip is planned before class.</p>
        @endif
        <div class="session-actions">
          @if($commuteReady)<a class="solid-button" href="{{ url('timetable/'.$event->id.'/ride') }}">{{ $plan?'Review ride plan':'Plan my ride' }} →</a>@endif
          @if($plan && !$plan->ends_on->copy()->endOfDay()->isPast())
            <form method="post" action="{{ url('timetable/'.$event->id.'/weekly') }}">@csrf<input type="hidden" name="enabled" value="{{ $plan->is_enabled?0:1 }}"><button class="outline-button" type="submit">{{ $plan->is_enabled?'Pause future weekly rides':'Resume weekly rides' }}</button></form>
          @endif
          <form method="post" action="{{ url('timetable/'.$event->id.'/remove') }}">@csrf<button class="session-remove" type="submit">Remove session</button></form>
        </div>
      </article>
    @empty
      <div class="content-card timetable-empty"><h3>Your week starts here.</h3><p>Add a class or work session, then plan the ride that gets you there.</p></div>
    @endforelse
    <p class="commute-help">The robot reminds you 30 and 10 minutes before sessions and ride appointments while RideUTeM is open. You can snooze for five minutes or dismiss a reminder. Removing a session cancels its future unconfirmed timetable rides.</p>
  </section>
</div>
@endif
@endsection
