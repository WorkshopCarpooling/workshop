# RideUTeM Laravel Migration Plan

## Goal

Move RideUTeM from the current custom PHP MVC structure to Laravel while preserving its screens, MySQL records, security rules, and local Windows/XAMPP workflow. Migrate incrementally so the existing implementation remains a working reference until each feature has a Laravel equivalent.

## Current system inventory

- PHP 8.3 and Composer 2.10 are available in the current environment. Select and pin a Laravel release compatible with the PHP version used by the actual XAMPP runtime when implementation begins.
- The application uses MySQL database `workshop`, PDO prepared statements, custom PHP sessions, and handcrafted CSRF tokens.
- Existing route entry points are `login.php`, `signup.php`, `logout.php`, `dashboard.php`, `findride.php`, `driver.php`, `admin.php`, and `tracking.php`.
- Existing code is already separated into `app/controllers/`, `app/views/`, `app/models/`, and `app/core/`, but is not a Composer-managed framework application.
- Features to carry over: student/staff registration and sign-in, pending-account approval, passenger ride search and seat requests, driver vehicles and vehicle review, ride publication and booking decisions, admin review, and simulated tracking preview.
- The database has a canonical fresh-install schema in `database/schema.sql` and a set of incremental SQL migration scripts. It includes users, vehicles, campus routes and stops, rides, ride stops, and bookings.
- Static resources are in `assets/`, `css/`, and `js/`. Map functionality has local configuration examples and must retain its current configuration behavior.

## Migration principles

1. Preserve the existing `workshop` database and primary key values. Laravel migrations must not recreate or drop production/local data.
2. Keep the current `.php` URLs working during the migration; optionally add clean Laravel URLs after compatibility is confirmed.
3. Preserve account approval and administrator-only access rules. Public registration must never assign the admin role.
4. Use Laravel routing, validation, CSRF protection, sessions, Blade templates, Eloquent/query builder, and authorization middleware/policies instead of custom equivalents.
5. Keep secrets in `.env` or ignored local configuration. Do not commit database passwords, map keys, or populated local environment files.
6. Make each phase reviewable and runnable before removing the legacy implementation.

## Proposed implementation phases

### Phase 1 — Framework foundation

- Initialize Laravel in the existing repository without overwriting the current app files or the user's uncommitted edits.
- Add Composer metadata and lockfile, Laravel bootstrap, `artisan`, route files, and the standard `public/` entry point.
- Configure `.env.example` for MySQL and document local `.env` setup; keep real credentials out of Git.
- Confirm XAMPP's Apache points to Laravel's `public/` directory (through a local virtual host or Apache alias). Document the exact local URL and rewrite requirements.
- Move/copy existing static resources into Laravel's public asset layout only as part of the implementation and update references consistently.

### Phase 2 — Existing database integration

- Connect Laravel to the existing MySQL schema using environment-based settings, retaining `workshop` as the default database name where appropriate.
- Build a schema inventory against an actual database before changing tables; distinguish the canonical `schema.sql` from databases upgraded using the individual scripts.
- Add Laravel migration tracking in a safe, additive manner. Baseline the already-existing schema so `migrate` does not try to create duplicate tables; do not run destructive resets or `migrate:fresh` against retained data.
- Map legacy key names (`user_id`, `ride_id`, etc.), timestamps, ENUM fields, unique constraints, foreign keys, indexes, and pivot tables explicitly in models/migrations.
- Preserve password hashes and test Laravel authentication against existing hashes. Do not force password resets unless hash verification proves necessary.

### Phase 3 — Authentication and shared layout

- Implement login, registration, and logout using Laravel controllers, request validation, session regeneration/invalidation, and CSRF middleware.
- Preserve UTeM student/staff registration, passenger/driver selection, pending account status, active-account sign-in, and role-based post-login redirects.
- Add authorization middleware for commuter and administrator areas, preserving current redirect behavior where it is part of the existing experience.
- Convert shared navigation and auth screens to Blade while retaining current CSS/JS and visual behavior.

### Phase 4 — Passenger features

- Migrate dashboard booking history and fixed-route ride search.
- Migrate seat request creation/update with the current per-ride/passenger uniqueness and pickup-stop rules.
- Retain the existing transactional seat and booking checks, including concurrent requests and available-seat limits.
- Preserve the tracking page as a simulated preview; do not add GPS storage or live tracking in this migration.

### Phase 5 — Driver and administrator features

- Migrate vehicle submission/resubmission and administrator vehicle approval/decline.
- Migrate ride publishing, selected route stops, booking acceptance/decline, seat accounting, and ride status transitions.
- Migrate account approval and admin dashboard counts.
- Ensure state changes are owner-scoped and administrator actions are protected by role checks and CSRF.

### Phase 6 — URL compatibility, cleanup, and handoff

- Define Laravel routes for the existing paths, including the `.php` paths in use today. Add clean paths as aliases only if desired.
- Preserve redirects and POST form destinations; ensure all in-app links, assets, and browser refreshes work under the chosen XAMPP base URL.
- Remove legacy controllers only after each equivalent Laravel feature is verified and the compatibility URLs work.
- Update `README.md` with Laravel installation, environment setup, database handling, XAMPP/Apache configuration, and admin promotion guidance.

## Route and feature mapping

| Existing URL | Laravel responsibility |
| --- | --- |
| `login.php` | Login form and authentication POST |
| `signup.php` | Registration form and account creation POST |
| `logout.php` | Authenticated logout POST |
| `dashboard.php` | Passenger dashboard and booking history |
| `findride.php` | Search rides and submit/update seat request |
| `driver.php` | Driver vehicles, ride management, booking decisions |
| `admin.php` | Member approval and vehicle review |
| `tracking.php` | Authenticated simulated tracking preview |

## Data migration and rollback approach

- Take a verified database backup before the first schema or application cutover.
- Keep the original tables and identifiers. First deploy Laravel code that reads and writes the current schema; do not combine framework adoption with a schema redesign.
- Introduce Laravel's migration history without reapplying table-creation operations to an existing schema. Prefer a reviewed baseline migration or a carefully scoped schema baseline procedure after comparing the real database to the known schema.
- For each later schema change, use a reversible additive Laravel migration and verify it against a copy of the database before applying it locally.
- Keep the legacy PHP implementation available in version control until Laravel has passed the acceptance checklist. Roll back application routing/code first; restore database backups only if a reviewed schema change requires it.

## Acceptance checklist

- Existing approved and pending users can sign in or remain pending as before, with their current password hashes intact.
- New public accounts can only be students or staff, and can only choose passenger or driver; they remain pending until approved.
- Admin routes/actions reject non-admin users; commuter routes reject signed-out users and admins according to current behavior.
- Passenger can find active future rides, request seats at an offered stop, and see existing bookings.
- Driver can submit a vehicle, publish rides only with approved vehicles, and accept/decline booking requests without overselling seats.
- Admin can approve accounts and review vehicles; pending lists and review notes behave as before.
- Existing rows and primary keys remain unchanged; relationships and route/stop selections load correctly.
- Existing `.php` URLs, redirects, CSS, JavaScript, and map configuration work through XAMPP/Apache.
- Fresh setup and existing-database setup instructions are both clear and do not instruct users to erase retained data.

## Scope boundaries and assumptions

- This is a framework migration, not a product redesign or schema redesign.
- Existing ride and booking records, pending users, route choices, and password hashes must be retained.
- Email verification, live GPS tracking, payment processing, and unrelated new functionality are out of scope.
- Before implementation, compare the actual local database against the current schema/migration history; this repository alone cannot establish which SQL scripts have already been applied to a developer's database.
- Current worktree contains user changes to `README.md`, several ride controllers/views and styles, `database/schema.sql`, and route-related JavaScript/SQL. Those edits must be preserved during implementation.

## Recommended sequence

Proceed phase by phase. Start implementation with Phase 1 and Phase 2 together only after confirming the target XAMPP PHP version and examining the live database schema; then migrate authentication and features in the order above. Keep each phase deployable so failures can be corrected without losing the existing application or data.
