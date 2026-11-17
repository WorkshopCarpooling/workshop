# Ride now and scheduled journeys

`findride.php` is the immediate passenger flow. It asks for pickup, drop-off and
passenger count. Search calculates a route and a signed student fare; confirmation
publishes a request to approved campus drivers without requiring a published trip.
The first driver to accept creates one confirmed booking with the quoted fare.
The driver then manages pickup, trip start and completion through the existing trip
controls. A pending request can be cancelled and expires after ten minutes.

`passenger/schedule` retains scheduled-trip matching, departure date/time, optional
arrival deadline, and timetable suggestions. Scheduled requests still go to the
driver who published the selected trip.

Install the additive schema with:

```powershell
php artisan migrate --path=database/migrations/2026_10_29_000000_add_on_demand_ride_requests.php
```

Existing rides default to `ride_mode=scheduled`. The new empty `ride_requests` table
stores unassigned requests. This migration does not create users, rides or bookings,
or rewrite existing journeys. It supports the existing MySQL ID signedness and
SQLite test databases. Requests remain temporarily unavailable until it is applied.

Passenger status is polled every five seconds, and the driver request list every
eight seconds while visible. Drivers need an approved vehicle and cannot take a
second immediate ride while already busy. Driver distance and availability are not
fabricated: the feed is for approved campus drivers, with no claim of live nearby
matching. Existing routing fallbacks remain labelled as estimates.

Validation:

- `php tests/on_demand_rides.php`: in-memory request lifecycle, ownership, fare
  integrity, competing acceptance, expiry, scheduled separation and view checks.
- `node tests/ride_now_client.cjs`: quote invalidation, stale responses, duplicate
  clicks, cancellation and real acceptance/completion states.
- `php tests/student_fares.php`: existing scheduled fares, matching and bookings.

Reference: [Grab booking flow](https://www.grab.com/sg/transport/) and
[Advance Booking](https://www.grab.com/my/transport/advance-booking/).
