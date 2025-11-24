FROM dunglas/frankenphp:php8.3

# Install system dependencies and PHP extensions
# install-php-extensions is provided by the base image
RUN install-php-extensions \
    pcntl \
    pdo_pgsql \
    pgsql \
    redis \
    bcmath \
    intl \
    zip \
    opcache

# Install Node.js and NPM for frontend build
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get install -y nodejs

# Set working directory
WORKDIR /app

# Copy composer files first to leverage cache
COPY composer.json composer.lock ./

# Install composer dependencies
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy package files
COPY package.json package-lock.json ./

# Install npm dependencies
RUN npm ci

# Copy the rest of the application
COPY . .

# Build frontend assets
RUN npm run build

# Optimize autoloader
RUN composer dump-autoload --optimize

# Create storage directories and set permissions
RUN mkdir -p storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache \
    bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Environment variables
ENV APP_NAME=Laravel \
    APP_ENV=local \
    APP_KEY=base64:5G3/KAIBwKLJSGIueOkx4eMG8zpwgHp9ntgD9tGuC14= \
    APP_DEBUG=false \
    APP_URL=http://localhost \
    APP_LOCALE=en \
    APP_FALLBACK_LOCALE=en \
    APP_FAKER_LOCALE=en_US \
    APP_MAINTENANCE_DRIVER=file \
    PHP_CLI_SERVER_WORKERS=4 \
    BCRYPT_ROUNDS=12 \
    LOG_CHANNEL=stack \
    LOG_STACK=single \
    LOG_DEPRECATIONS_CHANNEL=null \
    LOG_LEVEL=debug \
    DB_CONNECTION=pgsql \
    DB_HOST=136.113.109.137 \
    DB_PORT=5432 \
    DB_DATABASE=atlas_db \
    DB_USERNAME=postgres \
    DB_PASSWORD=Rootqwerty123! \
    SESSION_DRIVER=database \
    SESSION_LIFETIME=120 \
    SESSION_ENCRYPT=false \
    SESSION_PATH=/ \
    SESSION_DOMAIN=null \
    BROADCAST_CONNECTION=log \
    FILESYSTEM_DISK=local \
    QUEUE_CONNECTION=database \
    CACHE_STORE=database \
    MEMCACHED_HOST=127.0.0.1 \
    REDIS_CLIENT=phpredis \
    REDIS_HOST=127.0.0.1 \
    REDIS_PASSWORD=null \
    REDIS_PORT=6379 \
    MAIL_MAILER=log \
    MAIL_SCHEME=null \
    MAIL_HOST=127.0.0.1 \
    MAIL_PORT=2525 \
    MAIL_USERNAME=null \
    MAIL_PASSWORD=null \
    MAIL_FROM_ADDRESS="hello@example.com" \
    MAIL_FROM_NAME="Laravel" \
    AWS_ACCESS_KEY_ID= \
    AWS_SECRET_ACCESS_KEY= \
    AWS_DEFAULT_REGION=us-east-1 \
    AWS_BUCKET= \
    AWS_USE_PATH_STYLE_ENDPOINT=false \
    VITE_APP_NAME="Laravel" \
    OCO_LANGUAGE=es_ES \
    ATLAS_AUTH_URL=http://localhost:8000 \
    MODULE_PG_TOKEN=test-pg-token \
    TELESCOPE_ENABLED=false \
    OCTANE_SERVER=frankenphp

# Expose port 8000
EXPOSE 8000

# Start Octane with FrankenPHP
ENTRYPOINT ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=8000"]
