# RideUTeM project instructions

## Project structure

RideUTeM is a small PHP/MySQL campus carpool application organized with a lightweight MVC structure:

- `app/controllers/` handles requests, validation, access checks, and page data.
- `app/models/` contains database access.
- `app/views/` renders HTML templates.
- `app/core/` contains shared session and authorization helpers.
- Root-level PHP files are compatibility entry points. Static assets stay in `css/` and `js/`.

Keep the existing MVC boundaries when adding features. Use `databaseConnection(): PDO` and parameterized PDO queries. Do not put request or database logic in views.

## Product and security rules

The `users` table separates community role (`student`, `staff`, or `admin`) from account type (`passenger` or `driver`). Student and staff accounts need admin approval. Keep server-side role checks consistent across registration, routing, and authorization.

Ride publishing, seat requests and decisions, passenger pickup pins, member approval, and account authentication use MySQL. Preserve transaction and locking behavior in the booking flow. Admin ride, complaint, and report areas remain interface previews. `tracking.php` and `js/tracking.js` simulate movement in the browser only; they do not use GPS or send location data.

Use PDO prepared statements for request values, validate on the server, and follow the existing session CSRF-token pattern for state-changing forms. Escape dynamic HTML with `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`. Never add admin registration to public sign-up or commit credentials, personal data, `.env`, or `config/database.local.php`.

The pickup map uses Leaflet and OpenStreetMap. Keep attribution visible and follow the tile and Nominatim policies linked in `README.md`.

## Checks

There is no configured build system, automated test runner, or JavaScript linter. Check PHP syntax with `php -l path/to/file.php`. For end-to-end checks, follow the XAMPP and MySQL setup in `README.md`.

Before team changes, follow `CONTRIBUTING.md`: create a short-lived branch, push it, and open a pull request for review into `main`.
