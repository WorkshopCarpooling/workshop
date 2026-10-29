# RideUTeM campus carpooling

Laravel campus carpooling for UTeM students.

## Current journey and research features

- The main navigation contains the signed-in name, student and passenger/driver role, and CSRF-protected logout.
- Passengers allow device location for automatic pickup, then search a destination name and choose a result or drop-off pin. Pickup can be adjusted; denied/unavailable GPS falls back to a manual pickup pin. Drivers still publish their own start and destination using approved vehicles.
- Driver location planning is visible before vehicle approval; publishing still requires an approved vehicle. The picker prefers Leaflet/OpenStreetMap even when a Google routing key is configured, so it does not wait for the Google Maps SDK.
- Destination selection automatically searches compatible rides, ranked by straight-line distance to the driver's published starting point, then compatibility. This is scheduled-trip matching, not live driver tracking. Booking and approval recheck route feasibility and seats.
- Student fares use RM1 + RM0.30/km, minimum RM2 and maximum RM12 per seat, rounded to 10 sen and capped at the driver's advertised contribution (including RM0 free rides). Configure rates in `config/carpool.php`. Driver distance is not charged. Per-seat and total prices are displayed; encrypted 10-minute quotes bind the price to the passenger, ride, coordinates, schedule and seat count. The saved quote is visible to both parties and is confirmed when the driver accepts.
- Drivers review each passenger’s locations and deadlines. A* search plans the shortest feasible pickup/drop-off order under estimated distances, preserving pickup-before-drop-off and arrival deadlines. Bounded fallback plans are labelled. Google road routing is available with a configured API key.
- Admin → Live drivers uses a dark map and white cars for active student trips. GPS starts automatically on the driver page. Cars animate between received fixes, with driver details, plate, accuracy and follow controls. Published starting points and stale locations are labelled separately; GPS failure reasons are displayed instead of inventing a live position.
- Timetable connects weekly classes to one-time or optional recurring ride appointments, using route estimates and an arrival buffer. Weekly plans have an explicit end date and pause/resume controls. Generated appointments appear in Schedule with robot reminders. See [timetable journeys](docs/TIMETABLE_COMMUTES.md).
- An LSTM training/export pipeline and PHP inference are included. No trained model or fabricated accuracy results are supplied. Collect your own observations, then follow [the research workflow](docs/RESEARCH.md).

## Responsive website and future mobile app

The website includes responsive phone/tablet navigation and touch-friendly controls. App installation controls and prompts have been removed. See [future mobile app notes](docs/MOBILE.md) for the Laravel API and Flutter/native Android approach. Mobile app development is a future option; no mobile app is being built now.

## Run locally

## Separate role pages and carbon estimates

Navigation opens distinct server-rendered pages. Passenger pages are Overview, Find Ride, Bookings, Schedule, History, Carbon, Timetable and Research. Driver pages add Offer Ride, Seat Requests and Vehicles. Admin pages are Overview, Live Drivers, Ride Activity, Members, Vehicles, Carbon Reports and Research. Existing `.php` entry URLs still open the corresponding overview. Active-trip GPS is mounted once on each driver page and continues after moving between those pages.

To try the carbon workflow with your own trips:

1. Driver: **Vehicles** → save fuel type and your reported consumption in L/100 km. No fuel consumption is assumed for existing vehicles. Unsupported fuels can still use the ride system but do not get a carbon estimate.
2. Passenger: answer the alternative-travel question while requesting seats, or edit it later on **Carbon**. Separate-car alternatives require a car count, fuel and consumption; a booked seat is not automatically an avoided car.
3. Driver: complete the trip in **Schedule**, then open **Carbon**. Enter whole-trip kilometres including pickup detours, distance source, fuel consumption, and whether you would have travelled anyway. For an original trip that would happen anyway, also enter the original-route distance without pickup detours.
4. Save the estimate. Admin **Carbon Reports** totals each completed trip once and exports a CSV with source/version information. Passenger Carbon shows whole-trip results for rides they joined, not individual carbon credits.

Calculation: `kg CO2e = km × L/100 km / 100 × kg CO2e/L`. Net avoided emissions equal the driver's original trip plus supported passenger alternatives minus shared-vehicle emissions. Negative differences are retained. Unknown, bus, motorcycle and other-carpool alternatives leave the comparison incomplete; walking/cycling and not making the journey contribute zero. Passenger alternative-car distances use their reported alternative distance, falling back to the saved fare quote, and may be approximate. These are user-input estimates of fuel combustion, excluding vehicle manufacture and upstream fuel production.

Fuel factors are pinned to [Capital Markets Malaysia's July 2025 SME GHG Calculator User Guide, page 9](https://www.capitalmarketsmalaysia.com/sedg-ghg-calculator/pdf/User-Guide_SEDG-GHG-Emissions-Calculator.pdf): petrol 2.288, unblended diesel 2.909, diesel B7 2.705, B10 2.618 and B20 2.327 kg CO2e/L. They already include CO2-equivalent conversion. Snapshots preserve their inputs, factor and method `CMM2025-MOBILE-v1`. Updating a passenger alternative marks that trip's comparison for recalculation and removes it from totals until reviewed. Changing a vehicle profile does not rewrite historical snapshots.

Install the nullable fuel/baseline fields and empty report table with `php artisan commute:install-carbon` (also included in `commute:install-schema`). The installer does not invent historical fuel, travel-mode or carbon results.

## Local startup

With Laragon, use the project at `C:\laragon\www\workshop`, start Apache and MySQL from Laragon, then open [RideUTeM](http://localhost/workshop/login.php). Laragon's local `.env` uses `DB_PORT=3306`, `DB_DATABASE=workshop`, and the persistent file cache/session drivers. Existing URLs such as dashboard.php, findride.php, driver.php and admin.php are preserved. The separate `C:\xampp\htdocs\workshop` checkout is not served by Laragon; changes must reach the Laragon project before they appear in the browser.

For a fresh database, import database/schema.sql, then run:

```powershell
php artisan commute:install-schema
```

The installer adds nullable journey fields and empty research tables. It does not insert accounts, sample routes, bookings or research observations, and preserves existing records. Fresh schema SQL also contains no sample INSERT statements. Legacy survey data remains separate from the new commute_requirement_surveys table.

For an existing installation that already has journey fields, add only the four nullable booking fare columns with `php artisan commute:install-fares`. Existing bookings keep their original advertised contribution; new requests save their quoted distance and price. Requests remain unavailable until these columns exist.

The connection defaults to database workshop at 127.0.0.1:3301. Configure your own credentials in .env or the ignored config/database.local.php. Configure GOOGLE_MAPS_API_KEY to enable Google Maps and Routes; otherwise the picker uses Leaflet/OpenStreetMap and estimates are labelled as approximate. Dates use Asia/Kuala_Lumpur.

Google road routing sends pickup, destination and requested intermediate coordinates to Google's configured Routes API. Road distance is used for fares when available; outages or a missing key produce an explicitly labelled distance estimate (straight-line distance × 1.28). Automatic pickup requires browser location permission and HTTPS or localhost. A* is classical AI search over stop-order states, using straight-line distance × 1.28 and a minimum-spanning-tree lower bound. It does not search a street graph or use a trained model. Road directions still come from the configured provider.

Register your own student account as Passenger or Driver. An existing administrator approves accounts and vehicles. Public registration cannot create administrators. Promote only a trusted account directly in your own database when setting up an initial administrator.

The commuter scope is UTeM students only. Staff registrations and staff commuter access are disabled; existing records are preserved. Student status is reviewed manually by the administrator before approval; the app does not currently verify enrolment through a university identity service.

For live tracking, the driver presses **Start trip**. GPS starts automatically whenever an in-progress trip is open on the driver page, with no separate Share location step. Browser location permission is still required and cannot be bypassed. Admin opens **Live drivers**, which uses a dark map and white car icons. Before GPS arrives, a known published starting point is labelled separately; unknown coordinates are never invented. GPS blocks and permission errors appear on the admin list. Leaving the driver page retains the last fix as stale for up to ten minutes; completing a trip clears it. Use separate browser/private sessions for driver and admin. On the same computer use `http://localhost/workshop/driver.php` for GPS: plain HTTP `workshop.test` is not a secure browser context. Phones need HTTPS. GPS uploads and admin refreshes are limited to roughly five-second intervals. Driver GPS needs HTTPS (localhost is allowed on the same device); a phone visiting a plain HTTP LAN address cannot share location. Background/locked phone browsers may pause updates, which the map labels as stale. **Stop sharing** or completing the trip removes the cached location. Only the latest fix is cached for ten minutes, with no location history; page-close status reporting is best effort. Keep the default persistent file cache (or a shared persistent cache for multiple servers) rather than an array/null cache in production. This works without a Google API key using Leaflet/OpenStreetMap tiles; internet is required for those map assets. The passenger tracking page remains a separately labelled preview.

## Verification

```powershell
php tests/astar_pathfinding.php
php tests/live_tracking.php
node tests/live_tracking_browser.cjs
php tests/commute_features.php
php tests/student_fares.php
node tests/student_journey_browser.cjs
php tests/read_only_http.php
php artisan view:cache
```

The HTTP test uses existing active passenger, driver and administrator accounts and checks that record counts remain unchanged. No test accounts or records are created. The map/tracking simulation remains explicitly labelled as a preview; it is not actual GPS tracking or measured travel-time data.

## Application structure

- routes/web.php: authenticated routes, role restrictions and CSRF-protected actions.
- app/Http/Controllers: page and action handling.
- app/Services: journey validation, compatibility, precedence-aware route insertion and LSTM inference.
- resources/views: Blade pages and shared navigation/location picker.
- public/css and public/js: served browser assets.
- database/migrations: schema-only journey/research extension.
- ml: training and chronological baseline comparison on collected observations.
- docs/RESEARCH.md: objective mapping, model training, evaluation and limitations.

## Collaborate with GitHub

To work with teammates:

1. Create a **private** repository on GitHub for this project, then add its clone URL as `origin` in this local folder (`git remote add origin <repository-url>`).
2. Push the project to GitHub (`git push -u origin main`).
3. In the repository's **Settings → Collaborators**, invite each teammate by their GitHub username and grant the access they need.
4. Have teammates clone the repository, create a branch per change, and open pull requests into `main`. See [CONTRIBUTING.md](CONTRIBUTING.md) for the workflow.

This local folder does not currently have a GitHub remote. Keep credentials and local configuration out of the repository.

