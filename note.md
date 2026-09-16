# LIVE PRODUCTION SITE — READ THIS FIRST

**This repository is the live Sadbhavna donation platform.**

- Public: `https://sadbhavnadham.org` / donate checkout
- Admin: `https://admin.sadbhavnadham.org`
- App path: `/home/sadbhavnadham-admin/htdocs/admin.sadbhavnadham.org`

This site handles **millions of visits and real donation traffic** (Razorpay, subscriptions, WhatsApp, receipts). Treat every change as production.

## Do not harm or take this site down

Agents, CI, and humans must **never**:

- Run `git pull`, `git reset --hard`, `git checkout --`, or `git clean` on this server (GitHub can be older than live)
- Run `php artisan down` / maintenance mode
- Run `php artisan migrate`, `db:seed`, `db:wipe`, `migrate:fresh`, or any `UPDATE` / `INSERT` / `DELETE` / `ALTER` against the live database
- Delete `public/vite` (or other assets) before a new build finishes
- Restart nginx/php-fpm/mysql, reboot the VPS, or `composer install --no-dev` unless a human explicitly asks
- Change `.env`, payment keys, webhook secrets, or queue/supervisor config
- Force-push to the live server’s `origin` (`MonilAsense/SadbhavnaDonation`)

If a database change is required, write SQL into `alter.md` and wait for a human to run it.

## Source of truth

**This live server is the source of truth.** Send code **to** GitHub with **push only**. Do **not** pull GitHub onto this server.

GitHub backup remote: `https://github.com/SiddharthVaniya/sadbhavnadham-laravel.git`

## Allowed CI/CD on live (after a successful GitHub push)

`scripts/deploy.sh` may only:

1. `npm ci` + `npm run build` (keep existing Vite files until the new build writes replacements)
2. `php artisan optimize:clear` then `config:cache` / `route:cache` / `view:cache`
3. Graceful `php artisan queue:restart` (workers finish in-flight jobs)

No git pull. No downtime. No migrations. No seeders.

## If something looks broken after deploy

1. Do **not** pull or migrate.
2. Confirm `public/vite/manifest.json` exists.
3. Re-run only: `npm run build` then the cache commands above.
4. Check `storage/logs/laravel.log` without truncating it.
