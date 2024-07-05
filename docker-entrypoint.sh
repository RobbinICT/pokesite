#!/bin/bash
set -e

# Install Composer dependencies
composer install --prefer-dist --no-scripts --no-progress --no-suggest --no-interaction

# Install Node.js dependencies and build assets
npm install
npm run build

# Run Symfony migrations
php bin/console doctrine:migrations:migrate --no-interaction

# Start PHP-FPM
exec php-fpm
