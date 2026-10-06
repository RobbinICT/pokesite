#!/bin/bash
# Development entrypoint. Runs against the bind-mounted source tree, so it only
# installs dependencies when they are missing to keep container start-up fast.
set -e

# var/ is bind-mounted from the host and is owned by the host user, but
# php-fpm's workers run as www-data. Make sure the writable directories exist
# and are owned by www-data so Symfony can write logs and cache.
mkdir -p var/cache var/log
chown -R www-data:www-data var

if [ ! -f vendor/autoload_runtime.php ]; then
    echo "> Installing Composer dependencies..."
    composer install --prefer-dist --no-progress --no-interaction
fi

if [ ! -d node_modules ]; then
    echo "> Installing npm dependencies..."
    npm install
fi

# Rebuild assets when they are missing or when any source that feeds the build
# is newer than the last build, so updated code is never served against a stale
# bundle. Use 'npm run watch' for live rebuilds while developing.
if [ ! -f public/build/entrypoints.json ] || \
   [ -n "$(find assets templates tailwind.config.js postcss.config.js webpack.config.js package.json -newer public/build/entrypoints.json 2>/dev/null | head -1)" ]; then
    echo "> Building assets..."
    npm run build
else
    echo "> Assets up to date, skipping build."
fi

echo "> Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

exec "$@"
