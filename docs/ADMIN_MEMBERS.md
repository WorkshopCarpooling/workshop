# Member directory and access removal

Admin → Members lists student passenger and driver accounts. Filters cover all
members, passengers, drivers, removed accounts and account status; name/email
search and 20-row pagination are supported. Administrator accounts are excluded.
Pending applicants retain the existing approval action.

The migration `2026_10_29_040000_add_member_removal.php` adds nullable removal
metadata, previous status and a default-zero access version to users. It does
not remove or suspend any existing member.

Remove is a recoverable access-removal action, not a cascading database delete:

- Only an active administrator can remove or restore a student member.
- Ongoing driver or accepted-passenger trips block removal until finished.
- Removal suspends access, increments its session version, cancels pending and
  scheduled ride requests and pauses weekly timetable automation.
- Unstarted private rides and the removed driver's scheduled rides are cancelled.
  On a published shared ride, only the removed passenger's bookings are cancelled;
  accepted seats are returned for other passengers to use.
- Completed trips, fare records, carbon reports, vehicles and timetable sessions
  remain. Their user relationships still resolve for historical reports.
- Removed members are hidden from the current directory and available under
  Removed. Restore returns the previous active/pending/suspended status and never
  reactivates cancelled rides or weekly plans. Restored members must sign in again.
- Approval and removal serialize on the member row. Approval cannot bypass a
  removal marker. Authenticated requests check active status and access version.

Existing sessions for unaffected accounts continue to work with version zero.
New logins record the current version in their session. Removed or stale sessions
are signed out; the login redirect uses the actual `login.php` URL.

## Two pickups, one destination: current system status

`tests/admin_members.php` includes a concrete two-passenger case:

1. Two active students submit different pickups with the same drop-off.
2. Both individual requests appear to an available approved driver.
3. A driver can accept both into one instant car when the chosen passenger capacity permits it.
4. Matching closes when the car is full or the driver starts pickups. The shared
   route begins at the driver starting point and chooses the nearest remaining pickup.

See `SHARED_FIND_RIDE.md` for the shared-car flow. Each passenger keeps their quoted contribution.

## Validation

Run `php tests/admin_members.php` on the installed site or from its prepared
staging tree. The suite extends the existing role/carbon/page harness and uses
SQLite memory only. `node tests/admin_members_client.cjs` checks confirmation,
cancel and duplicate-submit behaviour with synthetic forms. `--members-preview`
creates inert example pages containing only clearly labelled synthetic accounts.
No real account is removed by installation, tests or previews.
