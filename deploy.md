# Manual deploy (live)

Live app: `/home/sadbhavnadham-admin/htdocs/admin.sadbhavnadham.org`  
Source: GitHub `main` — https://github.com/SiddharthVaniya/sadbhavnadham-laravel

The deploy script does **not** run migrations, `artisan down`, or overwrite `.env`. See `scripts/deploy.sh` and `CICD.md` for details.

## Standard deploy (run on the VPS as root)

```bash
sudo bash /home/sadbhavnadham-admin/htdocs/admin.sadbhavnadham.org/scripts/deploy.sh
```

## If Composer fails deleting `vendor/` files

Fix ownership, then deploy again:

```bash
sudo chown -R sadbhavnadham-admin:sadbhavnadham-admin /home/sadbhavnadham-admin/htdocs/admin.sadbhavnadham.org/vendor 2>/dev/null
sudo bash /home/sadbhavnadham-admin/htdocs/admin.sadbhavnadham.org/scripts/deploy.sh
```

One line (same as above):

```bash
sudo chown -R sadbhavnadham-admin:sadbhavnadham-admin /home/sadbhavnadham-admin/htdocs/admin.sadbhavnadham.org/vendor 2>/dev/null; sudo bash /home/sadbhavnadham-admin/htdocs/admin.sadbhavnadham.org/scripts/deploy.sh
```

## From the app directory

```bash
cd /home/sadbhavnadham-admin/htdocs/admin.sadbhavnadham.org
sudo bash scripts/deploy.sh
```

## Optional environment overrides

```bash
export APP_DIR=/home/sadbhavnadham-admin/htdocs/admin.sadbhavnadham.org
export DEPLOY_BRANCH=main
export DEPLOY_GIT_REMOTE=github
sudo -E bash scripts/deploy.sh
```

`DEPLOY_ALLOW_MIGRATE=1` and `DEPLOY_ALLOW_DOWNTIME=1` are **blocked** on live and will abort the script.
