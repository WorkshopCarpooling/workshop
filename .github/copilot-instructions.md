# RideUTeM project instructions

This is a Laravel 9 campus carpooling application. Read README and CONTRIBUTING for setup and collaboration.

Use `app/Http/Controllers`, middleware, `app/Models`, `app/Services`, Blade templates and `public` assets for new work. Older MVC folders and root PHP entry points remain for compatibility. Models use the `App\Models` namespace; preserve directory case for Linux.

Keep server-side authentication, account/vehicle approval, role and ownership checks, Laravel CSRF protection and escaped output. Preserve transactions and locking in shared-car acceptance. Passenger GPS responses must not expose another passenger's identity or pickup. Tracking is live only for active trips; do not invent driver coordinates or reopen finished trips.

Passenger Find Ride is immediate. Date/time belongs to appointments and recurring timetable plans. Keep the floating reminder robot; research is not a passenger feature. Instant car capacity is server-managed and closes at full capacity or trip start. Carbon savings require explicit alternative-travel assumptions and must remain labelled as estimates.

Never commit credentials, `.env`, database dumps with real records, installed dependencies, runtime caches or generated previews. Feature checks use SQLite memory and synthetic fixtures; read standalone utilities before running scripts that may touch a local database.
