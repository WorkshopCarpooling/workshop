# Timetable journeys

Timetable now connects weekly classes or work sessions to actual ride appointments.
Passengers add a session and choose **Plan my ride**. The destination starts with
the session's building name; the passenger confirms pickup and drop-off pins,
passenger count and desired early arrival.

The review works back from the next eligible session:

`driver search time = class start − estimated journey − early-arrival buffer − 10 minutes to find a driver`

For example, a 9 AM class with a 20-minute route and 10-minute early arrival starts
driver search at 8:20 AM, aiming to arrive at 8:50 AM. Route times are estimates,
not an arrival guarantee. Search and acceptance use the existing appointment and
driver request flow. Overnight journeys show the earlier date in the review.

The default books only the next session. **Repeat every week** requires a start
date, an end date and explicit confirmation of the reviewed route, passenger count
and contribution. The weekly plan reuses this reviewed contribution; it does not
silently introduce a new price each week. Editing the plan requires a fresh review.

The existing minute-by-minute appointment dispatcher prepares one future ride per
enabled plan and starts driver search when it becomes due. It does not need a new
Windows task. Cancelled occurrences remain skipped. **Pause future weekly rides**
pauses unconfirmed future appointments. Resuming restores future paused rides;
accepted/current rides continue normally. Plans stop at their selected end date.
Removing a timetable session disables its plans and cancels future unconfirmed
rides, preserving trip history and source snapshots.

Schedule labels generated rides with their timetable session. The robot uses that
session name for ride reminders, in addition to its existing session reminders.
Only the passenger can read session names; the driver feed continues to expose
the route, contribution, passenger count and request time, without the class name.

Install after the appointment migration:

```powershell
php artisan migrate --path=database/migrations/2026_10_29_020000_add_timetable_commutes.php
```

Existing classes do not enable ride plans by themselves. This migration adds an
empty weekly-plan table and nullable appointment source fields, with no sample or
user ride records. Existing plans/settings and appointment history are retained
when users replace their weekly preferences.

Validation:

- `php tests/timetable_commutes.php`: SQLite memory with a controlled clock,
  including timing, ownership, consent, idempotency, pause/resume, cancellation,
  automatic weekly generation, term limits, reminders and view rendering.
- `node tests/timetable_rides_client.cjs`: review, time display, expiry, weekly
  consent, stale responses and duplicate submission handling with a fake network.
