# Ride appointments and the reminder robot

Passengers use Schedule to save pickup, drop-off, a date, a time and passenger count.
All dates use Asia/Kuala_Lumpur (GMT+8). Reviewing the fare does not book a ride;
confirmation saves the appointment. A saved appointment is not a confirmed car.

At the selected time, the dispatcher releases the saved request to approved campus
drivers. A driver accepts through the existing request list. The accepted booking
keeps the selected time, locations and reviewed student contribution. Repeated
dispatch or confirmation cannot create duplicate requests. Cancelled appointments
never dispatch, and requests more than ten minutes late expire rather than sending
a driver for a missed pickup. An active ride or search defers another appointment
until the existing journey ends, within the same ten-minute window.

The robot image is visible again on passenger pages. It reminds passengers 30 and
10 minutes before appointments and classes while RideUTeM is open. It also reports
when a due appointment is looking for a driver, a driver accepts, or no driver was
confirmed. Reminders use only the signed-in passenger’s schedule. Snooze and
dismiss remain available. These are in-app reminders, not operating-system push
notifications after the browser closes.

Install schema after the ride-now migration:

```powershell
php artisan migrate --path=database/migrations/2026_10_29_010000_add_ride_appointments.php
```

Laravel's scheduler runs `rides:dispatch-appointments` every minute. On this local
Windows installation, `scripts/install-appointment-dispatcher.ps1` creates the
named RideUTeM Workshop Appointment Dispatcher task. It uses PHP's windowless
runtime (`php-win.exe`) and the served project's own artisan file. It does not
change Windows execution policy or require an unsigned PowerShell background script.
It runs once per minute while the computer and MySQL are running and the Windows
account is signed in. Passenger/driver refreshes also run the idempotent dispatch
check, so an open driver request list can pick up due appointments promptly.

Validation uses SQLite memory, a controlled clock and synthetic people:

- `php tests/ride_appointments.php`
- `node tests/schedule_robot_browser.cjs`
- `node tests/ride_appointments_client.cjs`

No user appointments, rides or bookings are created by the tests or migration.
