# Working together

## Start a change

1. Clone the GitHub repository, then update your local `main` branch before starting.
2. Create a short-lived branch for one feature or fix, such as `feature/driver-tracking-ui` or `fix/booking-validation`.
3. Keep commits focused and describe each change in plain language.
4. Push your branch and open a pull request into `main`. Ask a teammate to review it.
5. Merge after review. Avoid committing directly to `main` so the team can review changes first.

Typical commands:

```sh
git switch main
git pull
git switch -c feature/short-description
# make and commit your changes
git push -u origin feature/short-description
```

## Project structure

- `app/controllers/` handles requests and prepares page data.
- `app/models/` contains database access.
- `app/views/` contains HTML templates.
- `app/core/` contains shared session and authorization code.
- Root-level PHP files remain entry points for the existing local URLs.
- `css/`, `js/`, `database/`, and `config/` hold styles, browser code, SQL, and database setup.

## Keep private data out of Git

Do not commit passwords, API keys, personal records, `.env` files, or `config/database.local.php`. Use environment variables or the ignored local configuration for credentials. Review the changed files before pushing.

The live-tracking page is a browser-only movement preview; it does not use GPS or send location data. Follow `README.md` for the current feature and database setup details.
