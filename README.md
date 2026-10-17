# RideUTeM prototype screens

Responsive login and registration pages for the proposed UTeM Smart Campus Eco-Carpooling System.

## Pages

- `login.php` — UTeM community sign-in screen.
- `signup.php` — registration for students and UTeM staff, with university-email guidance and an optional same-gender ride preference.
- `dashboard.php` — empty commuter overview with a scheduled-ride search interface.
- `tracking.php` — a simulated route preview that demonstrates the intended driver-location interface without using GPS or storing location data.
- `admin.php` — empty administrator interface for future ride activity, member verification, complaints, and reporting.

The interface follows the supplied car-mobility reference and the proposal's campus-only, scheduled carpooling scope. The screens provide visual layouts and simple interface feedback only. No sample user, ride, complaint, or report records are included, and no account or trip data is saved. Account creation, UTeM email verification, Google sign-in, password recovery, ride matching, and live tracking require future services. The admin preview is not protected by authentication and must not be exposed as a live admin console.

## Run locally

Place the folder under XAMPP's `htdocs` directory, start Apache, and open `http://localhost/workshop/login.php` or `http://localhost/workshop/signup.php`. Open the empty commuter and admin interfaces at `http://localhost/workshop/dashboard.php` and `http://localhost/workshop/admin.php`. The location interface preview is at `http://localhost/workshop/tracking.php`.

## Collaborate with GitHub

This folder is prepared for a private GitHub repository. Invite teammates from the repository's Collaborators settings, then use the branch and pull-request workflow in [CONTRIBUTING.md](CONTRIBUTING.md). Keep credentials and local configuration out of the repository.

## Proposal details reflected in the screens

- Access is for UTeM students and staff using an official UTeM email address.
- Users may act as drivers or passengers; that choice can be added to account setup when the profile and ride modules are implemented.
- Same-gender selection is treated as a matching preference and is not presented as a safety guarantee.
- The product is for scheduled rides to and from UTeM through predefined pickup points, not door-to-door ride-hailing.





