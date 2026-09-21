#!/usr/bin/env bash
set -euo pipefail

# LIVE PRODUCTION CI/CD. See note.md and CICD.md.
# GitHub (SiddharthVaniya/sadbhavnadham-laravel) is the code source.
# Never: artisan down, migrate, db:seed, or delete public/vite before build.

APP_DIR="${APP_DIR:-/home/sadbhavnadham-admin/htdocs/admin.sadbhavnadham.org}"
APP_USER="${DEPLOY_APP_USER:-sadbhavnadham-admin}"
BRANCH="${DEPLOY_BRANCH:-main}"
REMOTE="${DEPLOY_GIT_REMOTE:-github}"
GITHUB_URL="git@github.com:SiddharthVaniya/sadbhavnadham-laravel.git"
NODE_BIN_DIR="/root/.nvm/versions/node/v24.19.0/bin"

as_app() {
    if [ "$(id -un)" = "$APP_USER" ]; then
        "$@"
    else
        runuser -u "$APP_USER" -- env HOME="/home/${APP_USER}" "$@"
    fi
}

load_node() {
    if [ -x "${NODE_BIN_DIR}/node" ]; then
        export PATH="${NODE_BIN_DIR}:$PATH"
        return
    fi

    if [ -s "${HOME}/.nvm/nvm.sh" ]; then
        # shellcheck disable=SC1091
        . "${HOME}/.nvm/nvm.sh"
        nvm use default >/dev/null 2>&1 || nvm use --lts >/dev/null 2>&1 || true
    fi
}

cd "$APP_DIR"

echo "==> Live deploy in $APP_DIR as $(id -un)"
echo "==> Source: GitHub ${REMOTE}/${BRANCH}"
echo "==> No downtime. No migrations. .env is never overwritten."

LOCK=/tmp/sadbhavnadham-deploy.lock
if [ -e "$LOCK" ] && [ ! -w "$LOCK" ]; then
    rm -f "$LOCK" || true
fi

exec 9>"$LOCK"
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

# Git must stay owned by APP_USER — root-owned .git/objects breaks fetch as app.
if [ "$(id -un)" = "root" ] || [ "$(id -u)" -eq 0 ]; then
    echo "==> Ensure .git owned by ${APP_USER}"
    chown -R "${APP_USER}:${APP_USER}" .git
fi

if as_app git remote get-url github >/dev/null 2>&1; then
    as_app git remote set-url github "$GITHUB_URL"
else
    as_app git remote add github "$GITHUB_URL"
fi

if as_app git remote get-url origin >/dev/null 2>&1; then
    as_app git remote set-url origin "$GITHUB_URL"
else
    as_app git remote add origin "$GITHUB_URL"
fi

if ! as_app git remote get-url "$REMOTE" >/dev/null 2>&1; then
    REMOTE="origin"
fi

echo "==> Fetch ${REMOTE}/${BRANCH}"
as_app git fetch --prune "$REMOTE" "$BRANCH"

echo "==> Sync live files to GitHub ${REMOTE}/${BRANCH} (tracked files only; .env kept)"
as_app git checkout -B "$BRANCH"
as_app git reset --hard "${REMOTE}/${BRANCH}"

echo "==> Composer (production)"
as_app composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

if [ -f package.json ]; then
    echo "==> npm build with Node $(load_node; node -v) (existing Vite files stay until replaced)"
    load_node
    echo "    node=$(command -v node) $(node -v)"
    if [ -f package-lock.json ]; then
        npm ci --no-audit --no-fund
    else
        npm install --no-audit --no-fund
    fi
    npm run build
    chown -R "${APP_USER}:${APP_USER}" node_modules public/vite 2>/dev/null || true
fi

echo "==> Delete Laravel caches"
as_app php artisan optimize:clear
as_app php artisan cache:clear

echo "==> Rebuild Laravel caches"
as_app php artisan config:cache
as_app php artisan route:cache
as_app php artisan view:cache

echo "==> Reload queue workers after in-flight jobs"
as_app php artisan queue:restart || true

echo "==> Deploy finished: live now matches GitHub ${REMOTE}/${BRANCH}"
as_app git log -1 --oneline
exit 0
