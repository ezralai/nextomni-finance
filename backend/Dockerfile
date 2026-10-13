FROM php:8.3-cli-alpine

# Install system dependencies & PostgreSQL PHP extensions
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    linux-headers \
    && docker-php-ext-install pdo pdo_pgsql zip pcntl bcmath opcache

# Configure production OPcache tuning
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.enable_cli=1'; \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=16'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.revalidate_freq=0'; \
    echo 'opcache.validate_timestamps=1'; \
    echo 'opcache.fast_shutdown=1'; \
} > /usr/local/etc/php/conf.d/opcache-recommended.ini

# Install Composer binary
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Cache Composer dependencies layer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# Copy full application codebase
COPY . .

# Generate optimized production autoloader
RUN composer dump-autoload --optimize --no-dev

# Ensure execution permissions for container entrypoint
RUN chmod +x docker-entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["/var/www/html/docker-entrypoint.sh"]
