FROM php:8.4-fpm
# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    nano \
    postgresql-client \
    libpng-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    libxpm-dev \
    libfreetype-dev \
    libicu-dev \
    libzip-dev \
    libonig-dev \
    libonig5 \
    curl \
    bash \
    autoconf \
    libpq-dev \
    gcc \
    libmagickwand-dev \
    g++ \
    make \
    libssl-dev zlib1g-dev libcurl4-openssl-dev libnghttp2-dev \
    && apt-get clean \
        && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp \
    && docker-php-ext-install \
    pdo \
    pdo_pgsql \
    gd \
    pcntl \
    sockets \
    zip \
    bcmath \
    intl \
    mbstring

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN pecl install swoole-6.1.2 imagick \
    && docker-php-ext-enable swoole imagick

# Set the working directory in the container
WORKDIR /var/www/html

COPY composer.json composer.lock artisan ./

RUN mkdir -p storage/framework/{cache,sessions,views} bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache


RUN printf '%s\n' \
  '#!/usr/bin/env bash' \
  'set -euo pipefail' \
  'cd /var/www/html' \
  'if [ ! -L public/storage ]; then php artisan storage:link || true; fi' \
  'php artisan optimize:clear || true' \
  'php artisan optimize || true' \
  'exec php artisan octane:start --server=swoole --host=0.0.0.0 --port=9000' \
  > ./start.sh \
  && chmod +x ./start.sh

# Copy the rest of the application
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --optimize

RUN chown -R www-data:www-data /var/www/html \
 && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

COPY docker/php.ini /usr/local/etc/php/conf.d/99-custom.ini

CMD ["./start.sh"]
