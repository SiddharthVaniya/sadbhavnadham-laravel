# CI/CD — GitHub to live

Repo: https://github.com/SiddharthVaniya/sadbhavnadham-laravel  
Live path: `/home/sadbhavnadham-admin/htdocs/admin.sadbhavnadham.org`

Push (or edit) `main` on GitHub → tests on GitHub → SSH to this VPS → sync files → **npm build** → **delete and rebuild cache**. The public site stays up. The live database is not touched.

## GitHub Actions secrets

Add these under **Settings → Secrets and variables → Actions**:

| Secret | Value |
| --- | --- |
| `VPS_HOST` | `195.35.23.88` |
| `VPS_USER` | `sadbhavnadham-admin` |
| `VPS_PORT` | `22` |
| `APP_DIR` | `/home/sadbhavnadham-admin/htdocs/admin.sadbhavnadham.org` |
| `VPS_SSH_KEY` | Private key from the live server (see below) |

Copy the SSH private key on the VPS (do not commit it):

```bash
sudo cat /root/.ssh/id_ed25519_github_actions
```

Paste the full block (including `BEGIN` / `END` lines) into `VPS_SSH_KEY`.

## What deploy runs on live

```bash
bash scripts/deploy.sh
```

That script:

1. Fetches `github/main`
2. Hard-resets tracked files to match GitHub (keeps `.env`)
3. `composer install --no-dev`
4. `npm ci` and `npm run build`
5. `php artisan optimize:clear` and `php artisan cache:clear`
6. `php artisan config:cache`, `route:cache`, `view:cache`
7. `php artisan queue:restart`

## Manual deploy

SSH as `sadbhavnadham-admin` or root:

```bash
cd /home/sadbhavnadham-admin/htdocs/admin.sadbhavnadham.org
bash scripts/deploy.sh
```
