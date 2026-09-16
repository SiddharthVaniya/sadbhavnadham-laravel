#!/usr/bin/env bash
set -euo pipefail

# LIVE PRODUCTION. See note.md.
# Never git pull, artisan down, migrate, seed, or delete public/vite.

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_DIR"

echo "==> Production-safe deploy in $APP_DIR"
echo "==> This host is live (high traffic). Git pull / downtime / migrations are forbidden."

exec 9>/tmp/sadbhavnadham-deploy.lock
if ! flock -n 9; then
  echo "Another deploy is already running. Aborting to protect the live site."
  exit 1
fi

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "This is not a git repository."
  exit 1
fi

if [ "${DEPLOY_ALLOW_GIT_PULL:-0}" = "1" ]; then
  echo "REFUSING: git pull is not allowed on this live site. See note.md"
  exit 1
fi

if [ "${DEPLOY_ALLOW_MIGRATE:-0}" = "1" ] || [ "${DEPLOY_ALLOW_DOWNTIME:-0}" = "1" ]; then
  echo "REFUSING: migrate / maintenance mode is not allowed on this live site. See note.md"
  exit 1
fi

if [ -f "package.json" ]; then
  echo "==> Build frontend assets (existing Vite files stay until the new build replaces them)"
  if [ -f "package-lock.json" ]; then
    npm ci --no-audit --no-fund
  else
    npm install --no-audit --no-fund
  fi
  npm run build
fi

echo "==> Clear and rebuild Laravel caches (site stays online)"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Signal queue workers to reload code after in-flight jobs"
php artisan queue:restart || true

echo "==> Deployment completed (no downtime, no git pull, no migrations)"
exit 0
