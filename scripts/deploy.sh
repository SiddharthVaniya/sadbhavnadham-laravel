#!/usr/bin/env bash
set -euo pipefail

# LIVE PRODUCTION CI/CD. See note.md and CICD.md.
# GitHub (SiddharthVaniya/sadbhavnadham-laravel) is the code source.
# This script syncs live to GitHub, builds assets, and refreshes caches.
# Never: artisan down, migrate, db:seed, or delete public/vite before build.

APP_DIR="${APP_DIR:-/home/sadbhavnadham-admin/htdocs/admin.sadbhavnadham.org}"
APP_USER="${DEPLOY_APP_USER:-sadbhavnadham-admin}"
BRANCH="${DEPLOY_BRANCH:-main}"
REMOTE="${DEPLOY_GIT_REMOTE:-github}"
GITHUB_URL="git@github.com:SiddharthVaniya/sadbhavnadham-laravel.git"

if [ "$(id -un)" != "$APP_USER" ]; then
    if [ "$(id -u)" -eq 0 ]; then
        exec runuser -u "$APP_USER" -- env \
            APP_DIR="$APP_DIR" \
            DEPLOY_APP_USER="$APP_USER" \
            DEPLOY_BRANCH="$BRANCH" \
            DEPLOY_GIT_REMOTE="$REMOTE" \
            bash "$0" "$@"
    fi

    echo "Deploy must run as $APP_USER (or root)."
    exit 1
fi

cd "$APP_DIR"

echo "==> Live deploy in $APP_DIR as $(id -un)"
echo "==> Source: GitHub ${REMOTE}/${BRANCH}"
echo "==> No downtime. No migrations. .env is never overwritten."

exec 9>/tmp/sadbhavnadham-deploy.lock
if ! flock -n 9; then
    echo "Another deploy is already running. Aborting."
    exit 1
fi

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    echo "This is not a git repository."
    exit 1
fi

if [ "${DEPLOY_ALLOW_MIGRATE:-0}" = "1" ] || [ "${DEPLOY_ALLOW_DOWNTIME:-0}" = "1" ]; then
    echo "REFUSING: migrate / maintenance mode is not allowed on this live site."
    exit 1
fi

if git remote get-url github >/dev/null 2>&1; then
    git remote set-url github "$GITHUB_URL"
else
    git remote add github "$GITHUB_URL"
fi

if git remote get-url origin >/dev/null 2>&1; then
    git remote set-url origin "$GITHUB_URL"
else
    git remote add origin "$GITHUB_URL"
fi

if ! git remote get-url "$REMOTE" >/dev/null 2>&1; then
    REMOTE="origin"
fi

echo "==> Fetch ${REMOTE}/${BRANCH}"
git fetch --prune "$REMOTE" "$BRANCH"

echo "==> Sync live files to GitHub ${REMOTE}/${BRANCH} (tracked files only; .env kept)"
git checkout -B "$BRANCH"
git reset --hard "${REMOTE}/${BRANCH}"

echo "==> Composer (production)"
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

if [ -f package.json ]; then
    echo "==> npm build (existing Vite files stay until the new build replaces them)"
    if [ -f package-lock.json ]; then
        npm ci --no-audit --no-fund
    else
        npm install --no-audit --no-fund
    fi
    npm run build
fi

echo "==> Delete Laravel caches"
php artisan optimize:clear
php artisan cache:clear

echo "==> Rebuild Laravel caches"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Reload queue workers after in-flight jobs"
php artisan queue:restart || true

echo "==> Deploy finished: live now matches GitHub ${REMOTE}/${BRANCH}"
git log -1 --oneline
exit 0
