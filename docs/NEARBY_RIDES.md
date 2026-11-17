# Nearby routes and driver waiting

Drivers leaving now choose a starting pin, destination and approved vehicle in
the waiting section on Driver overview or Ride requests, then select **Open ride
now**. Opening availability does not create a booking or a trip. **Offer a ride**
continues to use a departure date and time for planned journeys.

Passengers select their own pickup and destination in Find Ride. While their
request is pending, **Drivers heading your way** shows compatible online routes.
Different pickup names are allowed. The passenger keeps their chosen pickup;
the driver must accept before a booking is confirmed. This is an in-page notice,
refreshed while the page is visible, rather than a browser push notification.

Matching uses destination pins within 50 metres and estimates the additional
journey through the passenger pickup. Defaults allow up to 2 extra kilometres
and 5 extra minutes, including a two-minute pickup allowance. Settings are in
`config/carpool.php`. Existing accepted pickups are included. Road estimates use
the configured Google/OSRM routing; when routing is unavailable, the UI explicitly
labels geometric estimates as approximate. Candidate routes are bounded to keep
lookups manageable: the four closest eligible origins among the 20 most recently
updated nearby-destination availability records are checked.

Availability is renewed by a CSRF-protected heartbeat every 30 seconds while
the driver page is visible, and expires after 90 seconds without a renewal.
**Go offline** immediately stops new matching and preserves accepted bookings.
An expired/offline route requires an explicit **Resume matching** or reopening.
Reopening rotates a version so an old tab cannot close or renew a newer route.
Cars that are full, have begun pickups, have lost vehicle approval or whose
driver is inactive are excluded. The driver manages accepted passengers in My
schedule. Legacy pools that already had passengers keep their existing flow.

Install the additive table only:

```powershell
php artisan migrate --path=database/migrations/2026_10_31_000000_add_driver_waiting_routes.php --force
```

Checks use synthetic roles, pins and road responses; they do not create real
bookings or request real GPS:

```powershell
php tests/driver_waiting_routes.php
node tests/driver_waiting_client.cjs
node tests/ride_now_client.cjs
node tests/shared_find_ride_client.cjs
```
