# RideUTeM prototype screens

Responsive RideUTeM campus carpool prototype with database-backed accounts and scheduled driver ride publishing.

## Pages

- `login.php` â€” UTeM community sign-in screen.
- `signup.php` â€” registration for students and UTeM staff, with Passenger or Driver account selection.
- `dashboard.php` â€” passenger overview with a scheduled-ride search interface.
- `findride.php` â€” ride search interface with an OpenStreetMap pickup map.
- `tracking.php` â€” a simulated route preview that demonstrates the intended driver-location interface without using GPS or storing location data.
- `driver.php` â€” driver workspace to publish and manage scheduled rides.
- `admin.php` â€” administrator dashboard with database-backed member approval; ride activity, complaints, and reporting remain interface previews.

The interface follows the supplied car-mobility reference and the proposal's campus-only, scheduled carpooling scope. Driver ride listings, seat requests, passenger pickup pins, sign-up, sign-in, and account approval are saved in the `workshop` database. Live trip tracking remains a preview. Registration and sign-in use the `users` table in the `workshop` database. Email verification is not required; new student and staff accounts remain pending until an administrator approves them in `admin.php`. The admin page is protected by a server-side session role check; public registration cannot create admin accounts.


## MVC structure

Application code is organized under `app/`:

- `app/controllers/` handles page requests, validation, access checks, and the data needed by each page.
- `app/models/` contains database access (`Database.php`).
- `app/views/` contains the HTML templates rendered by controllers.
- `app/core/` contains shared session and authorization helpers.

The existing root-level PHP URLs remain as small entry points for compatibility with current links and local XAMPP setup. Static assets, configuration, and SQL files remain in their existing folders.
## Run locally

Place the folder under XAMPP's `htdocs` directory, start Apache, and open `http://localhost/workshop/login.php` or `http://localhost/workshop/signup.php`. Open the passenger dashboard at `http://localhost/workshop/dashboard.php`, ride search at `http://localhost/workshop/findride.php`, driver workspace at `http://localhost/workshop/driver.php`, and admin page at `http://localhost/workshop/admin.php`. The location interface preview is at `http://localhost/workshop/tracking.php`.

### MySQL account setup

1. Start **Apache** and **MySQL** in XAMPP.
2. For a new setup, import `database/schema.sql`. For an existing setup without driver features, run `database/driver_feature_migration.sql` once. If you already installed the driver feature, run `database/vehicle_details_migration.sql` once to add car type, colour, and number plate. These scripts preserve existing records.
3. The connection defaults to `127.0.0.1:3301`, database `workshop`, and the XAMPP `root` user with an empty password. If your local MySQL settings differ, copy `config/database.local.php.example` to `config/database.local.php` and edit that ignored local file.
4. Register as a student or staff member and choose Passenger or Driver. Sign in as an administrator and approve the account from the Account approval section. Approval sets `account_status` to `active`; `email_verified` is not used for sign-in.

5. To grant admin access, promote a trusted account directly in MySQL. Never add an admin option to public sign-up:

   ```sql
   UPDATE users SET role = 'admin', email_verified = TRUE, account_status = 'active' WHERE email = 'admin@utem.edu.my';
   ```

The app redirects administrators to `admin.php`, drivers to `driver.php`, and passengers to `dashboard.php`. A commuter opening `admin.php` is redirected back to the dashboard.

### OpenStreetMap pickup selection

Passengers can search for a place or tap/drag the in-page map to set a pickup pin. The selected pin is saved with a seat request, and drivers can preview it inline in **My driving**. The map uses Leaflet and OpenStreetMap tiles and needs no Google API key. OpenStreetMap attribution remains visible. Place search is a one-time search button, not autocomplete; the public Nominatim service has a strict usage policy and a maximum of one request per second. The standard OpenStreetMap tile service is donation-supported, best-effort, and has usage rules, so use it lightly for this prototype. See the [tile policy](https://operations.osmfoundation.org/policies/tiles/) and [Nominatim policy](https://operations.osmfoundation.org/policies/nominatim/).

## Collaborate with GitHub

To work with teammates:

1. Create a **private** repository on GitHub for this project, then add its clone URL as `origin` in this local folder (`git remote add origin <repository-url>`).
2. Push the project to GitHub (`git push -u origin main`).
3. In the repository's **Settings → Collaborators**, invite each teammate by their GitHub username and grant the access they need.
4. Have teammates clone the repository, create a branch per change, and open pull requests into `main`. See [CONTRIBUTING.md](CONTRIBUTING.md) for the workflow.

This local folder does not currently have a GitHub remote. Keep credentials and local configuration out of the repository.

## Proposal details reflected in the screens

- Access is for UTeM students and staff using an official UTeM email address.
- Students and UTeM staff can sign up as passengers or drivers. Active, admin-approved driver accounts can publish scheduled trips and update their status; passengers can request seats and share a pickup pin.
- Same-gender selection is treated as a matching preference and is not presented as a safety guarantee.
- The product is for scheduled rides to and from UTeM through agreed pickup points, not door-to-door ride-hailing. Live GPS tracking and passenger booking requests are future work.





