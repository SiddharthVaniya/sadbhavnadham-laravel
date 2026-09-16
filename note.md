# LIVE PRODUCTION SITE — READ THIS FIRST

**This repository is the live Sadbhavna donation platform.**

- Public: `https://sadbhavnadham.org` / donate checkout
- Admin: `https://admin.sadbhavnadham.org`
- App path: `/home/sadbhavnadham-admin/htdocs/admin.sadbhavnadham.org`

This site handles **millions of visits and real donation traffic** (Razorpay, subscriptions, WhatsApp, receipts). Treat every change as production.

## Code source

**GitHub `main` is the code source.** Change files on GitHub, then CI/CD copies them to this live server, runs `npm run build`, and deletes/rebuilds Laravel cache.

Live `.env`, uploads, and the database are **never** taken from GitHub.

## Do not harm or take this site down

Agents, CI, and humans must **never**:

- Run `php artisan down` / maintenance mode
- Run `php artisan migrate`, `db:seed`, `db:wipe`, `migrate:fresh`, or any `UPDATE` / `INSERT` / `DELETE` / `ALTER` against the live database
- Delete `public/vite` (or other assets) before a new build finishes
- Restart nginx/php-fpm/mysql or reboot the VPS unless a human explicitly asks
- Change `.env`, payment keys, webhook secrets, or queue/supervisor config
- Force-push to GitHub `main` unless a human explicitly asks

If a database change is required, write SQL into `alter.md` and wait for a human to run it.

## Allowed CI/CD on live (`scripts/deploy.sh`)

1. `git fetch` + `git reset --hard` to GitHub `main` (tracked files only; `.env` stays)
2. `composer install --no-dev`
3. `npm ci` + `npm run build` (keep existing Vite files until the new build writes replacements)
4. Delete cache: `php artisan optimize:clear` + `php artisan cache:clear`
5. Rebuild cache: `php artisan config:cache` / `route:cache` / `view:cache`
6. Graceful `php artisan queue:restart`

No downtime. No migrations. No seeders.

Setup: see `CICD.md`.

## If something looks broken after deploy

1. Do **not** migrate or run `artisan down`.
2. Confirm `public/vite/manifest.json` exists.
3. Re-run only: `bash scripts/deploy.sh`
4. Check `storage/logs/laravel.log` without truncating it.
