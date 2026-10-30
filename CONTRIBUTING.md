# Working together

Clone and install the current shared branch using [README.md](README.md). Keep your database and `.env` on your own computer.

1. Commit or stash local work before pulling updates.
2. Until the initial sharing PR is merged, branch from `codex/share-latest-ridetem`; afterwards branch from an updated `main`.
3. Make a short-lived feature/fix branch and keep commits focused.
4. Review `git diff --cached` before committing. Never stage `.env`, API keys, local database exports, uploaded personal data, runtime caches or `vendor`.
5. Push your branch and open a pull request against the branch you started from. Ask a teammate to review it; merge after review.

```sh
git switch codex/share-latest-ridetem
git pull --ff-only
git switch -c feature/short-description
# Edit files, then stage specific paths and commit.
git push -u origin feature/short-description
```

New application code belongs in Laravel controllers, middleware, `app/Models`, services and Blade views. Preserve server-side roles, CSRF protection, transactions and row locks for seat allocation, authorised GPS access and synthetic test isolation. Root PHP files are compatibility URLs; older MVC files are retained for compatibility.

Run the checks relevant to your change, listed in README and feature documents under `docs/`. Use migrations for schema changes and commit `composer.lock` when dependencies deliberately change. Each teammate runs `composer install` and `php artisan migrate` after pulling relevant updates. Do not run `migrate:fresh` against an existing database.
