#!/bin/bash
# Development entrypoint. Runs against the bind-mounted source tree, so it only
# installs dependencies when they are missing to keep container start-up fast.
set -e

if [ ! -f vendor/autoload_runtime.php ]; then
    echo "> Installing Composer dependencies..."
    composer install --prefer-dist --no-progress --no-interaction
fi

if [ ! -d node_modules ]; then
    echo "> Installing npm dependencies..."
    npm install
fi

if [ ! -d public/build ]; then
    echo "> Building assets (run 'npm run watch' for live rebuilds)..."
    npm run build
fi

echo "> Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

exec "$@"
