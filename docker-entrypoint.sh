#!/bin/bash

# Run Composer install
composer install --prefer-dist --no-scripts --no-progress --no-suggest --no-interaction

# Additional setup commands here, e.g., running migrations
# php bin/console doctrine:migrations:migrate --no-interaction

# Start the PHP-FPM service
php-fpm
