#
# vigilo image. One Dockerfile, three targets:
#   app  — PHP-FPM with code, vendor/ and built assets baked in (also used by
#          the queue and scheduler containers)
#   web  — Caddy with public/ baked in
# Nothing is bind-mounted, so `docker compose up -d` needs no extra steps.

ARG PHP_VERSION=8.4

# --- PHP runtime ---------------------------------------------------------------
FROM php:${PHP_VERSION}-fpm-alpine AS php-base
# icu-data-full: Alpine's default ICU data is English-only, which would
# silently format German numbers and money the English way.
RUN apk add --no-cache icu-libs icu-data-full libpq fcgi tzdata \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libpq-dev linux-headers \
 && docker-php-ext-install -j"$(nproc)" bcmath intl opcache pcntl pdo_pgsql \
 && apk del .build-deps \
 && rm -rf /tmp/* /var/cache/apk/*

# --- Composer dependencies -----------------------------------------------------
FROM php-base AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apk add --no-cache git unzip
ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /app
COPY composer.json composer.lock ./
# Dev dependencies (Pest, Pint, Larastan) are included by default so
# `docker compose exec app php artisan test` works on any install. Build with
# --build-arg VIGILO_DEV_DEPENDENCIES=false for a slimmer image.
ARG VIGILO_DEV_DEPENDENCIES=true
RUN composer install --no-interaction --no-progress --no-scripts --no-autoloader --prefer-dist \
        $([ "$VIGILO_DEV_DEPENDENCIES" = "true" ] || echo --no-dev)
COPY . .
RUN composer dump-autoload --optimize --no-scripts \
        $([ "$VIGILO_DEV_DEPENDENCIES" = "true" ] || echo --no-dev)

# --- Frontend assets -----------------------------------------------------------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
# Tailwind scans app/ for classes built in PHP (severity badges, …).
COPY app ./app
RUN npm run build

# --- Application ---------------------------------------------------------------
FROM php-base AS app
WORKDIR /app
ENV PHP_FPM_MAX_CHILDREN=4
COPY docker/php/conf.d/zz-vigilo.ini /usr/local/etc/php/conf.d/zz-vigilo.ini
COPY docker/php/zz-vigilo-pool.conf /usr/local/etc/php-fpm.d/zz-vigilo-pool.conf
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/vigilo-entrypoint
COPY --from=vendor --chown=www-data:www-data /app /app
COPY --from=assets --chown=www-data:www-data /app/public/build /app/public/build
# Configuration comes from the environment (compose env_file). The empty
# .env only stops Dotenv from probing for a file that is not there.
RUN touch .env \
 && mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions \
        storage/framework/views storage/framework/testing storage/logs bootstrap/cache \
 && chown -R www-data:www-data /app
USER www-data
RUN php artisan package:discover --ansi
EXPOSE 9000
ENTRYPOINT ["vigilo-entrypoint"]
CMD ["php-fpm", "-F"]

# --- Web server ----------------------------------------------------------------
FROM caddy:2-alpine AS web
COPY docker/caddy/Caddyfile /etc/caddy/Caddyfile
COPY --from=app /app/public /app/public
