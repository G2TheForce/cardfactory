#!/usr/bin/env bash
# Zero-downtime deploy into releases/<timestamp>, then switch the "current" symlink.
# Layout:
#   /var/www/smartcard-studio/
#     releases/       one folder per deploy (the last 5 are kept)
#     shared/.env     production settings
#     shared/storage/ uploads, exports, logs (survives deploys)
#     current ->      releases/<latest>
#
# Usage: deploy/deploy.sh [git-ref]   (run as the deploy user, default ref: main)
set -euo pipefail

APP=/var/www/smartcard-studio
REPO=${REPO:-https://github.com/G2TheForce/cardfactory.git}
REF=${1:-main}
RELEASE=$APP/releases/$(date +%Y%m%d%H%M%S)

mkdir -p "$APP/releases" "$APP/shared/storage"
git clone --depth 1 --branch "$REF" "$REPO" "$RELEASE"
cd "$RELEASE"

# Shared state
rm -rf storage
ln -s "$APP/shared/storage" storage
ln -s "$APP/shared/.env" .env
mkdir -p storage/app/public storage/app/private storage/framework/{cache,sessions,views} storage/logs

composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
npm ci
npm run build
rm -rf node_modules

php artisan storage:link
php artisan migrate --force
php artisan db:seed --class='Database\Seeders\RolesAndPermissionsSeeder' --force   # new permissions
php artisan db:seed --class='Database\Seeders\ThemeSeeder' --force               # new built-in card themes
php artisan optimize          # config, routes, events and views cached

ln -sfn "$RELEASE" "$APP/current"
sudo systemctl reload php8.3-fpm   # clears OPcache for the new release
php artisan queue:restart          # workers finish their current job, then restart on the new code

# Keep the last 5 releases
ls -1dt "$APP"/releases/* | tail -n +6 | xargs -r rm -rf
echo "Deployed $REF to $RELEASE"
