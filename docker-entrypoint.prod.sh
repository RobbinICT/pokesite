#!/bin/sh
# Production entrypoint. Dependencies and assets are already baked into the
# image, so only pending migrations are applied before the server starts.
set -e

php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

exec "$@"
