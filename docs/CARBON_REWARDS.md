# Carbon Rewards

Carbon Rewards adds passenger points, an all-time leaderboard, a stocked voucher
catalogue and receipt history at `/carbon/rewards`. Existing booking, acceptance,
trip start, navigation, GPS and monthly footprint calculations are preserved.

## Comparison and car colours

Each driver and accepted passenger confirms their own alternative travel for a
trip, before or after completion. Separate-car declarations record distance, fuel,
consumption and number of cars (at most the party's booked seats). Other travel
claims no avoided-car emissions: this does not treat bus emissions as zero.
Missing declarations never become invented separate-car journeys.

Separate-car kg = sum(distance × consumption / 100 × fuel factor × cars).
Shared kg retains the existing whole-route footprint, including pickup detours.
Savings kg = separate-car kg − shared kg. Negative savings remain negative.

The live car tint uses shared kg / separate-car kg × 100: green ≤65%, yellow
>65–80%, red >80%. Grey means comparison or route inputs are unavailable.
The daily comparison combines finalised trips completed today for the vehicle
with the full planned active trip. This is a projected daily comparison, not
measured fuel use along the current GPS position. Dates use Malaysia time.
No emission-sensor or verified-carbon-credit claim is made. Original per-km and
per-participant footprint figures remain available separately.

## Points and ranking

Points are issued only after completion, a saved footprint report, and all
participant declarations. No historical points are invented or backfilled when
pages are opened. A trip finalises once under a ride lock. Its report, declared
inputs, rate and allocation are frozen for audit; later footprint corrections
do not automatically reissue or rewrite points.

Passenger allocation = max(0, trip saving) × booked seats / (1 + accepted seats).
Award = floor(passenger allocation × 100). Driver allocation earns no passenger
points. This counts the trip's saving once rather than awarding the whole amount
to everyone. Group-booking points belong to the account that booked the seats.
These are estimates based on self-confirmed alternatives, not independently
verified avoided emissions. The initial project rate is 100 points/kg in
`config/carbon_rewards.php`.

The leaderboard has only an all-time ranking, ordered by lifetime earned points,
then allocated savings and user ID. Spending points reduces the wallet balance,
not lifetime earned points or ranking. Suspended accounts are excluded.

## Rewards

Admins create inactive catalogue entries with title, provider, cost and terms,
upload genuine voucher inventory, and activate listings. No voucher or merchant
partnership is generated or seeded. ZUS or other providers can be listed when
the programme has authorised, usable voucher codes and terms.

Admins also set **Discount amount (RM off)** and **Points required to claim**,
for example RM5.00 off for 500 points. **Edit discount & points** on each listing
updates future claims. Discount amounts must be positive with at most two decimal
places; points must be whole numbers from 1 to 1,000,000. The discount must match
the uploaded voucher inventory and provider terms. Passenger cards and receipts
show the RM value. A receipt snapshots both discount and points cost, so changing
a listing never rewrites earlier claims. A passenger reviewing an outdated value
must refresh and confirm the updated offer before points can be spent.

Install `2026_11_03_000000_add_reward_discount_settings.php` after the rewards
migration. It adds nullable discount fields without guessing values for existing
listings or receipts. Existing listings can receive a value through the admin
editor; old receipts keep their original unknown value.

Codes are encrypted with Laravel's application key and globally deduplicated by
a hash. Expired/claimed stock cannot be allocated. Claims lock the passenger,
reward and stock, reserve one voucher, and debit points in one transaction.
A unique request UUID makes retries idempotent. Insufficient balance or stock
leaves the wallet unchanged. Receipts snapshot original terms/cost and reveal
the code only to the owner, with private no-store responses. Issuing a voucher
does not establish whether it has been redeemed with its merchant.

## Install and verify

Run only `php artisan migrate --path=database/migrations/2026_11_02_000000_add_carbon_rewards.php`.
The additive migration creates six empty rewards tables. It inserts no people,
points, sample rewards or voucher codes and preserves existing records.

`php tests/carbon_rewards.php` uses SQLite memory for declarations, point
allocation, completion integration, colour boundaries/day rollover, all-time
ranking, claims, stock expiry, idempotency, role access and CSRF. It creates no
live rewards. `node tests/carbon_rewards_client.cjs` checks marker tints and
missing-data behaviour without GPS or network.
