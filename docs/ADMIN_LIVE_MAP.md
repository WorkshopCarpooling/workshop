# Admin live driver map

Open **Admin → Live drivers**. Active trips appear in the list; a coloured car appears
after the first device GPS fix. A single live car is followed automatically on
initial load. Select **Follow car**, or a car marker, to choose a different driver
and load that trip's planned route. **Show all cars** returns to the fleet view.
The black line shows the remaining planned route to a destination ring. Driver
details stay in the side card so the car is not covered by a persistent tooltip.

The default camera uses the previous flat, north-up angle. **Angled view** is
optional and tilts 38 degrees with a 35-degree diagonal rotation. The locally
bundled MapLibre 6.16 renderer uses fractional-pixel car positions; car heading is
projected into the camera's perspective. It uses the same OpenStreetMap tiles.
One worker limits resource use. Devices without this rendering support fall back
to Leaflet, and reduced-motion users start flat. The angle control is available
when the tilted renderer is active.

The map refreshes every five seconds. Cars glide between received fixes and turn
smoothly, carrying displayed speed into the next update without overshooting it.
Steering uses the road tangent around each bend. For the selected road route,
nearby GPS fixes are interpolated along its
geometry to avoid cutting corners. Off-route or inaccurate fixes use the received
GPS positions. Movement stops at the latest fix and when GPS becomes stale or the
connection fails. Stationary drivers do not get artificial movement.

The planned route uses the existing Google/OSRM routing and cache. If unavailable,
the map shows an explicitly labelled dashed estimate. A routing failure does not
interrupt GPS tracking. Completed trips and their selected routes disappear.

The route endpoint requires an active administrator and an active trip from an
active student driver. Responses contain geometry without passenger labels or
upload tokens, and use `no-store` caching headers.

`php tests/render_admin_live_preview.php` generates a separate labelled simulation
under `public/device-review/admin-live-trip/`, using synthetic positions on a saved
road fixture. It does not start trips, read device GPS, or alter real bookings.

Checks: `node tests/live_tracking_browser.cjs` and
`node tests/admin_driver_map.cjs`, plus `php tests/admin_live_routes.php`.
These use synthetic data without real GPS.

## Live carbon colour and calculation

Each car uses its own saved fuel profile and current accepted seat quantities.
Intensity = consumption L/100 km / 100 × saved fuel factor × 1000 / (1 driver +
accepted passenger seats), in g CO2e per booked participant per kilometre.
The approved local pilot thresholds are green ≤60, yellow >60–100, red >100.
These are display assumptions, not an official environmental rating. Missing or
invalid fuel data is grey and unavailable, never zero. Values can change when
accepted seats or the vehicle profile change even if the GPS fix is unchanged.

Driver cards include the intensity, booked occupancy and, when coordinates are
usable, a planned whole-trip and per-participant footprint. **Lihat kiraan** opens
the calculation inputs, distance basis and source. The modal refreshes with new
inputs and indicates when its trip leaves the live feed. Whole-route emissions
are divided equally, including for participants with different boarding distances.

The existing admin-only feed supplies aggregate carbon values without passenger
identities or upload tokens. Reads make no external routing requests or report
writes. Planned distance reuses the existing matching saved road quote or labelled
straight-line ×1.28 fallback; it is not recorded GPS mileage. GPS freshness and
carbon estimates remain separate. Missing route information leaves trip totals
unavailable while a valid per-km intensity can still colour the car.

The existing completion action saves the completed-trip footprint as before.
Monthly and all-time footprint CSVs also include g CO2e per booked participant/km
from the saved estimate, alongside the existing source and allocation metadata.
Run `php tests/live_carbon.php` for the isolated feed/calculation/privacy checks;
`node tests/live_tracking_browser.cjs` also covers independent car colours, live
occupancy updates, missing data and the calculation dialog.

The original 3D car asset is `public/images/tracking-car-3d.png` (200 × 300,
transparent PNG, approximately 80 KB). Generation details are in
[the asset prompt](ADMIN_CAR_ASSET.md).
