# Automatic carbon footprint

RideUTeM estimates the combustion footprint of completed carpool trips without
asking passengers how they would otherwise have travelled. The campus pilot
supports digital transport management and sustainability reporting. SME relevance
still needs an actual employee-commuting use case or partner; this is not a complete
organisation-wide inventory or proof of emissions avoided.

## User flow

- Drivers save vehicle fuel type and average consumption once in My vehicles.
- Passengers book seats normally; no alternative-travel questionnaire or preference
  is needed. Old saved travel preferences are not applied to new bookings.
- Completing a ride automatically saves its route, fuel-factor and accepted-seat
  snapshot. Retries do not create duplicate reports.
- Drivers may optionally correct the whole vehicle distance and fuel inputs.
  There is no question about whether the driver would have travelled anyway.
- Admins see monthly completed rides, accepted passenger seats, booked occupancy,
  vehicle kilometres, trip emissions and average emissions per participant-trip.
  Monthly and all-time CSV exports contain footprint inputs and data coverage.

## Calculation and allocation

Fuel litres = whole vehicle distance km * litres per 100 km / 100.
Trip kg CO2e = fuel litres * saved fuel factor.
Per-participant allocation = trip kg CO2e / (1 driver + accepted passenger seats).
A passenger's booking allocation = per-participant allocation * their booked seats.
The driver's allocation is one participant's share.

The equal allocation is a simple reporting convention across the entire route,
including pickup detours. It does not adjust for different boarding distances.
Occupancy is based on accepted bookings, not verified boarding. Seat counts are
participant-journeys, not a count of unique people. Monthly per-participant figures
use total emissions divided by total booked participant-trips on estimated rides,
not an unweighted average of trip averages. Admin totals count each vehicle once.

New reports snapshot party seat counts in details.allocation with method
`equal-booked-participant-v1`. Older reports retain all original data and fuel
factors; their allocation uses currently accepted bookings and is identified as
such in the page and CSV. No old report is rewritten by opening a page.

Fuel factors retain the existing versioned CMM2025-MOBILE-v1 method and source.
They estimate fuel combustion, excluding upstream fuel and vehicle manufacture.
Distances prefer a matching whole-trip saved road quote, or the configured road
provider's planned stop order at completion. Provider failures fall back to the
labelled straight-line distance * 1.28 approximation. The footprint calculation
needs no additional request for a hypothetical original driver journey.
`CARBON_ROAD_LOOKUP_ENABLED=false` disables new provider lookups.

Completed historical trips lacking reports may receive clearly marked read-only
previews using the current vehicle profile. Previews make no new routing requests
and are separated from saved reports in monthly coverage and CSV estimate_status.
Missing vehicle profiles or usable routes remain unavailable, not zero emissions.

## Compatibility and setup

No new database migration is needed when the existing carbon schema is installed.
Old baseline fields, historical savings reports and price revisions are preserved.
The current UI and exports show footprints rather than avoided emissions, fuel
savings or cost savings. No monthly price entry is required.

Legacy comparison routines remain available for historical integrations/tests only
when `carbon.legacy_comparisons_enabled` is explicitly enabled. It defaults to
false. Retired preference/baseline submissions return HTTP 410 in normal operation.
Current automatic reports store no separate-journey baseline or avoided amount.

## Validation

`php tests/carbon_footprint.php` runs the existing historical/role suite explicitly
in compatibility mode, then checks the default footprint workflow, automatic
completion, multi-seat allocations, frozen occupancy/fuel assumptions, ownership,
month boundaries, missing-data coverage, read-only previews, no routing on page
reads, CSV parity and removal of passenger questions. All data uses SQLite memory.

Because the workspace has older unrelated view files than the running Laragon
installation, validation can use an isolated prepared copy of the running site's
application with only these carbon changes overlaid. `--footprint-preview` writes
inert, clearly labelled synthetic role pages; CARBON_PREVIEW_OUTPUT can select the
output folder. No live users, trips or GPS are created by the tests.