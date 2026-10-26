# syntax=docker/dockerfile:1

# =============================================================================
# Multi-stage build.
#
#   base    -> slim PHP-FPM runtime + compiled extensions (shared by all)
#   vendor  -> composer dependencies (cached on composer.json/lock only)
#   assets  -> Webpack Encore production build of public/build
#   dev     -> base + tooling for the bind-mounted local dev workflow
#   prod    -> self-contained, slim deployable image (default target)
#
# Build tooling (node, composer, git, build-essentials) lives only in the
# intermediate stages, so it never ends up in the final `prod` image.
# =============================================================================


# -----------------------------------------------------------------------------
# 1. Base runtime (shared by dev and prod)
# -----------------------------------------------------------------------------
FROM php:8.2-fpm-alpine AS base

WORKDIR /var/www/html

# Runtime shared libraries needed by the PHP extensions compiled below.
RUN apk add --no-cache \
        libpng \
        libjpeg-turbo \
        freetype \
        libzip \
        icu-libs \
        libxml2 \
        oniguruma \
        yaml \
        mysql-client

# Compile PHP extensions. The build dependencies are installed in a virtual
# package, used, and removed in the same layer so they add nothing to the image.
RUN set -eux; \
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libzip-dev \
        icu-dev \
        libxml2-dev \
        oniguruma-dev \
        yaml-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" gd mbstring zip pdo_mysql soap intl opcache; \
    pecl install yaml; \
    docker-php-ext-enable yaml; \
    apk del .build-deps


# -----------------------------------------------------------------------------
# 2. Composer dependencies
#    Only composer.json/lock are copied first, so this expensive layer is
#    reused as long as the dependencies don't change.
# -----------------------------------------------------------------------------
FROM base AS vendor

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apk add --no-cache git unzip

COPY composer.json composer.lock symfony.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --prefer-dist \
        --no-progress \
        --optimize-autoloader


# -----------------------------------------------------------------------------
# 3. Front-end assets (Webpack Encore)
#    ux-turbo is a `file:vendor/...` dependency and Encore reads the Stimulus /
#    Turbo assets straight out of vendor/, so vendor is brought in first.
# -----------------------------------------------------------------------------
FROM node:18-alpine AS assets

WORKDIR /var/www/html

COPY --from=vendor /var/www/html/vendor ./vendor

# Dependency layer: cached unless package manifests change.
COPY package.json package-lock.json ./
RUN npm ci

# Everything Encore + Tailwind need to produce and purge the bundle.
COPY webpack.config.js postcss.config.js tailwind.config.js .browserslistrc ./
COPY assets ./assets
COPY templates ./templates
RUN npm run build


# -----------------------------------------------------------------------------
# 4. Development image (used by docker-compose with bind mounts)
#    Dependencies and the asset build are handled at runtime against the
#    mounted source tree by docker-entrypoint.sh.
# -----------------------------------------------------------------------------
FROM base AS dev

ENV APP_ENV=dev

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apk add --no-cache git unzip bash nodejs npm \
    && ln -sf /bin/bash /bin/sh \
    && printf 'alias ll="ls -lah"\nalias sf="php bin/console"\n' >> /root/.bashrc

COPY docker/php-dev.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

EXPOSE 9000
ENTRYPOINT ["docker-entrypoint"]
CMD ["php-fpm"]


# -----------------------------------------------------------------------------
# 5. Production image (default target) — slim and self-contained
# -----------------------------------------------------------------------------
FROM base AS prod

ENV APP_ENV=prod \
    APP_DEBUG=0

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini

# Compiled dependencies and assets from the build stages.
COPY --from=vendor /var/www/html/vendor ./vendor
COPY --from=assets /var/www/html/public/build ./public/build

# Application source (vendor/, public/build, var/, node_modules are excluded
# via .dockerignore, so the artifacts copied above are preserved).
COPY . .

# Regenerate the optimized autoloader now that src/ is present, then make the
# runtime-writable directories available to php-fpm (www-data).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer dump-autoload --no-dev --optimize --no-interaction \
    && mkdir -p var/cache var/log \
    && chown -R www-data:www-data var \
    && rm -f /usr/bin/composer

COPY docker-entrypoint.prod.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

EXPOSE 9000
ENTRYPOINT ["docker-entrypoint"]
CMD ["php-fpm"]
