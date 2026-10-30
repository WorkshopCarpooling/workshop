# Monthly transport impact

The Carbon page now adds a monthly view for completed rides, fuel use and savings,
fuel-cost comparisons, booked occupancy and comparison coverage. RideUTeM remains
the campus pilot; the report can support an SME commuting pilot without claiming
a complete organisation-wide emissions inventory.

## Setup

Run only `2026_10_29_030000_add_carbon_impact_tools.php` after the existing carbon
schema. It creates saved travel preferences and append-only monthly price revisions.
It does not seed users, trips, prices, travel choices or savings.

- Passengers may save their usual alternative, fuel consumption and separate-car
  count on Carbon. New bookings reuse it unless an explicit trip answer is given.
  A default car count larger than the booked party is not applied.
- Drivers save vehicle fuel/consumption in My vehicles and may save whether they
  normally make the original drive anyway. New completion reports can reuse this
  answer. Existing reports and historical read-only previews are unchanged.
- Administrators enter fuel prices and a source/assumption for the chosen month.
  There is no built-in retail price. Clearing a price records a null revision;
  previous revisions remain available in `carbon_fuel_prices`.

## Calculations and reporting

Fuel litres = distance km × litres/100 km ÷ 100. Baseline fuel is the driver's
original journey, if applicable, plus supported passenger alternatives. Net fuel
saving is baseline minus the entire shared route, including detours. Prices are
applied separately to each fuel. Passenger contributions are not fuel savings.
Unknown travel alternatives, changed comparisons and missing prices leave their
affected savings unavailable. Negative results remain negative.

Shared emissions retain the existing versioned combustion factors. Distance
prefers a saved road quote only when it matches a complete direct shared journey.
New completion reports may query the existing configured Google road provider
for the full planned stop order and the original driver journey. Failure falls
back to the labelled planned approximation. `CARBON_ROAD_LOOKUP_ENABLED=false`
disables new provider lookups while still allowing matching saved road quotes.
Read-only page previews never make new road-provider requests.

Monthly totals use completion timestamps and saved reports, one vehicle journey
per row. They exclude cancelled and scheduled rides. Passenger totals are whole
trip results for joined rides, not amounts to sum across passenger accounts.
Occupancy is driver plus accepted booked seats, not verified boarding.

The admin monthly CSV includes every completed ride in the period, missing-data
statuses, distance source, fuel and CO2e comparisons, RM savings and the exact
monthly price revision/reference used. Updating a price revises cost estimates;
it does not rewrite saved fuel/emissions snapshots. The existing all-time carbon
export remains available.

## Validation

`php tests/carbon_impact.php` extends the in-memory role/carbon harness with
preferences, consent, price revisions, mixed fuels, signed savings, ownership,
month boundaries, routing fallbacks, occupancy, CSV and CSRF checks. In a staged
deployment, run the same test from that prepared tree so other role pages use
the running site's established implementation. `--impact-preview` generates
inert HTML containing only synthetic trips and prices for visual checks.
