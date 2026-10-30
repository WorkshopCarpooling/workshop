<aside class="schedule-robot" data-schedule-robot data-endpoint="{{ url('api/passenger/reminders') }}" data-timetable-url="{{ url('timetable') }}" data-schedule-url="{{ url('passenger/schedule') }}" data-user="{{ Auth::id() }}" aria-label="Schedule reminder assistant">
  <section class="schedule-robot-card" id="scheduleRobotCard" aria-labelledby="scheduleRobotTitle" hidden>
    <div class="schedule-robot-card-head"><span><i aria-hidden="true"></i> YOUR CAMPUS COMPANION</span><button type="button" data-robot-close aria-label="Close reminder">×</button></div>
    <div data-robot-message role="status" aria-live="polite" aria-atomic="true">
      <p class="schedule-robot-kicker" data-robot-kicker>LET’S PLAN AHEAD</p>
      <h2 id="scheduleRobotTitle" data-robot-title>Hi! I’m your schedule buddy.</h2>
      <p class="schedule-robot-countdown" data-robot-countdown>I’ll give you a little nudge before it’s time to go.</p>
      <p class="schedule-robot-location" data-robot-location></p>
      <p class="schedule-robot-time" data-robot-time></p>
    </div>
    <a class="schedule-robot-primary" data-robot-link href="{{ url('timetable') }}">My timetable <span aria-hidden="true">↗</span></a>
    <div class="schedule-robot-controls" data-robot-controls hidden><button type="button" data-robot-snooze>Snooze 5 min</button><button type="button" data-robot-dismiss>Dismiss</button></div>
    <button class="schedule-robot-next" type="button" data-robot-next hidden>Next reminder →</button>
    <p class="schedule-robot-footnote">Reminders 30 &amp; 10 minutes before classes and ride appointments, plus driver-search updates while RideUTeM is open.</p>
  </section>
  <button class="schedule-robot-launcher" type="button" data-robot-toggle aria-label="Open schedule reminders" aria-controls="scheduleRobotCard" aria-expanded="false">
    <span class="schedule-robot-halo" aria-hidden="true"></span>
    <img src="{{ asset('images/schedule-robot.png') }}" width="112" height="112" alt="" decoding="async">
    <span class="schedule-robot-badge" data-robot-badge hidden></span>
    <span class="schedule-robot-launcher-label">Schedule buddy <i aria-hidden="true">✦</i></span>
  </button>
  <noscript><a href="{{ url('timetable') }}">View your timetable</a> · Enable JavaScript for schedule reminders.</noscript>
</aside>
<link rel="stylesheet" href="{{ asset('css/schedule-robot.css') }}?v={{ filemtime(public_path('css/schedule-robot.css')) }}">
<script src="{{ asset('js/schedule-robot.js') }}?v={{ filemtime(public_path('js/schedule-robot.js')) }}" defer></script>
