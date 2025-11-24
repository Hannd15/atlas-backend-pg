# syntax=docker/dockerfile:1.7

# --- Composer dependencies ---------------------------------------------------
FROM composer:2.8 AS vendor
WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-ansi \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative

# --- Frontend assets ---------------------------------------------------------
FROM node:20-alpine AS frontend
WORKDIR /app

COPY package*.json ./
RUN npm install --no-progress

COPY resources ./resources
COPY vite.config.js ./vite.config.js
RUN npm run build

# --- Runtime image -----------------------------------------------------------
FROM dunglas/frankenphp:1.6-php8.3-bookworm AS production
WORKDIR /app

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    OCTANE_SERVER=frankenphp \
    PORT=8000

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl gosu \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions \
    bcmath \
    gd \
    intl \
    opcache \
    pcntl \
    pdo_pgsql \
    redis \
    zip

COPY --from=vendor /app /app
COPY --from=frontend /app/public/build ./public/build

COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

RUN set -eux; \
    mkdir -p \
        storage/app/public \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs; \
    touch storage/logs/laravel.log; \
    ln -sfn /app/storage/app/public /app/public/storage; \
    chown -R www-data:www-data storage bootstrap/cache; \
    chmod -R ug+rwX storage bootstrap/cache

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -f http://127.0.0.1:${PORT}/health || exit 1

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--workers=auto", "--max-requests=500"]
