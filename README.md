# RideUTeM

UTeM student carpooling system built with Laravel 9, PHP, MySQL and browser JavaScript.

## Get the latest shared code

The current system is on **`codex/share-latest-ridetem`**. Clone this branch until its pull request has been merged into `main`:

```sh
git clone --branch codex/share-latest-ridetem https://github.com/WorkshopCarpooling/workshop.git workshop
cd workshop
```

If you already cloned the repository, commit or stash your work first, then:

```sh
git fetch origin
git switch codex/share-latest-ridetem
git pull --ff-only origin codex/share-latest-ridetem
```

## First installation

Use PHP 8.0.30 or a compatible PHP 8.x runtime, Composer 2 and MySQL/MariaDB. Enable PDO MySQL, mbstring, OpenSSL, cURL, fileinfo, XML and tokenizer. PDO SQLite is needed for the isolated PHP checks. Node.js is only needed for the browser-script checks; there is no frontend build step.

From the project directory:

```sh
composer install
```

Create your own environment file. In Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

On macOS/Linux, use `cp .env.example .env`. Edit `.env` for your local database:

```dotenv
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=workshop
DB_USERNAME=root
DB_PASSWORD=
```

Set your own MySQL username, password and port. Create an **empty** database named `workshop` in phpMyAdmin or your MySQL client, then run:

```sh
php artisan key:generate
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

Open <http://127.0.0.1:8000>. Migrations create the complete schema; do not import old SQL files as well. No real accounts, trip records, database exports, API keys or installed dependencies are included.

For Laragon/XAMPP Apache, use a virtual host with its document root set to this repository's `public` directory and set `APP_URL` to that host. The root compatibility rewrite also supports a folder named `workshop`, but `php artisan serve` is the simplest setup. Make `storage` and `bootstrap/cache` writable by PHP.

### First administrator

Register an account through the signup page using your own email and password. New accounts await approval. On your own local database, open:

```sh
php artisan tinker
```

Promote only the account you just registered, replacing the example email:

```php
App\Models\User::where('email', 'your-email@example.com')->update(['role' => 'admin', 'account_status' => 'active']);
```

Exit Tinker and log in. This administrator can approve passenger accounts, driver accounts and driver vehicles. There are no default passwords.

### Appointments and reminders

Keep a second terminal open while developing:

```sh
php artisan schedule:work
```

The scheduler dispatches saved ride appointments every minute. A one-off check is `php artisan rides:dispatch-appointments`. Booking a time releases the request at that time; driver availability still determines acceptance. The reminder robot is part of the passenger interface.

### Maps and GPS

`GOOGLE_MAPS_API_KEY` in your own `.env` is optional. Use a newly created, appropriately restricted key with the APIs your setup needs. Without Google routing, the navigation service tries OSRM; if no road service is available, approximate geometry is labelled in the interface. Network access is required for online tiles, address search and road routing.

Drivers must allow browser location access. Use localhost or HTTPS for geolocation. A live car marker uses received latitude/longitude; it is not a simulated GPS position. Tracking is restricted to an active trip and its authorised users, and closes when the trip finishes.

Do not reuse credentials from an old checkout. Generate a fresh `APP_KEY` for a new installation and keep `.env` untracked. Credentials previously committed to Git must be replaced at their provider; deleting the current file does not remove older commits.

## Main flows

- **Find Ride:** pickup and drop-off, direct search, animated waiting and cancellation. Drivers accept directly. Matching requests for the same destination can share the car until its three passenger seats fill or the driver starts pickups.
- **Driver:** passenger requests on the home page, UTeM starting area, approved vehicles, ordered pickups and live GPS navigation to drop-off.
- **Schedule and timetable:** dated ride appointments, passenger count, opt-in recurring commute plans and robot reminders.
- **Carbon:** documented estimates for shared travel, monthly impact, fuel/cost assumptions and role-scoped exports. Unknown alternative travel is not reported as an invented saving.
- **Admin:** approve accounts and vehicles, manage passengers/drivers, review rides and active tracking. Member removal preserves historical records.

Research is not exposed in passenger navigation. Legacy research tables/files remain for compatibility; they are not part of the current passenger flow.

## Project structure

- `app/Http/Controllers`, `app/Http/Middleware`: HTTP actions and access checks.
- `app/Models`, `app/Services`: data models and ride, navigation, reminder and carbon logic.
- `resources/views`: Blade templates; `public/css`, `public/js`, `public/images`: served assets.
- `routes`: application/API/console routes; `database/migrations`: complete schema.
- `tests`: standalone PHP and Node checks with synthetic fixtures.
- Root PHP entry points and older MVC folders remain for compatibility. Add new work to the Laravel structure.

## Checks

After installation, these PHP checks use SQLite memory and synthetic records:

```sh
php tests/driver_home.php
php tests/trip_navigation.php
php tests/admin_members.php
php tests/carbon_impact.php
php tests/live_tracking.php
```

Browser logic checks:

```sh
node tests/shared_find_ride_client.cjs
node tests/live_tracking_browser.cjs
node tests/trip_navigation_geometry.cjs
```

Some other utility scripts query a configured local database or generate previews; read them before running them. Follow [CONTRIBUTING.md](CONTRIBUTING.md) for team changes.
