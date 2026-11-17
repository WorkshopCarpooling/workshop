# Driver button, automatic carbon and page explanations

Prepared against the active Laragon application at `C:/laragon/www/workshop`.
The XAMPP workspace contains an older application; originals/ keeps the exact
pre-change versions used to prepare this scoped update.

- The green driver hero button opens `/driver/offer`, where the form now lives.
- Fuel type and consumption are entered once per vehicle. Completing a trip
  automatically saves an emissions estimate if its locations and fuel profile
  are usable. Unknown inputs remain pending. Existing saved reports are preserved.
- Older completed trips get read-only previews using the current fuel profile.
  Opening a carbon page does not insert or update reports. Previews do not enter
  saved comparison totals or CSV exports; shared-emissions totals label them.
- Automatic distance uses the existing stop-order planner with expired deadlines
  removed for historical trips, including accepted passenger detours. Distances
  are straight-line kilometres × 1.28, explicitly approximate and never represented
  as GPS, odometer or measured road distances. Manual corrections remain optional.
- Unknown driver and passenger alternatives never become invented avoided cars.
  Confirming a driver's alternative uses prefilled numerical inputs. Passenger
  alternative changes automatically refresh comparisons with the saved shared
  distance and fuel profile. Changed factor versions require a new estimate.
- Timetable and Research pages explain their actual purpose and role differences.
  Selecting a session whose ten-minute arrival buffer has passed uses next week.

## Research basis

Fuel emissions = distance × L/100 km ÷ 100 × kg CO2e/L.
The existing petrol factor is 2.288 kg CO2e/L from Capital Markets Malaysia,
SEDG GHG Calculator User Guide, July 2025, page 9:
https://www.capitalmarketsmalaysia.com/sedg-ghg-calculator/pdf/User-Guide_SEDG-GHG-Emissions-Calculator.pdf

Comparison assumptions and boundaries should be disclosed, and both positive
and negative impacts retained:
https://ghgprotocol.org/estimating-and-reporting-avoided-emissions

These are fuel-combustion estimates, excluding fuel production and vehicle manufacture.
Automatic carbon route estimates are not training observations for travel-time research.

## Validation

`php tests/carbon_and_pages.php`: SQLite-memory users/trips only; actual completion
hook, automatic distance/fuel, idempotence, missing inputs, baseline refresh,
negative impacts, historical snapshots, role restrictions, CSRF and rendered pages.

`node tests/timetable_browser.cjs`: executes shipped timetable code with controlled
time, including an expired arrival buffer, midnight and Sunday.

The deployment script copies only changed files after checking the active files
still match originals/. It does not install schema, change accounts or edit trip data.
