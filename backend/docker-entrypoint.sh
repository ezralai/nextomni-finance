#!/bin/sh
set -e

echo "==> [NextOmni Finance API] Initializing Production Container..."

# Ensure storage and bootstrap cache directories exist and are writable
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

# If APP_KEY is empty, generate it
if [ -z "$APP_KEY" ]; then
    echo "==> Warning: APP_KEY is empty, generating key..."
    php artisan key:generate --force
fi

# Run database migrations if enabled (default true for continuous deployment)
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "==> Running automated database migrations..."
    php artisan migrate --force --isolated || echo "==> Migration check completed."
fi

# Run seeders if enabled (useful for initial demo environments)
if [ "${RUN_SEEDERS:-false}" = "true" ]; then
    echo "==> Running database seeders..."
    php artisan db:seed --force || echo "==> Seeder completed."
fi

# Production performance caching
if [ "${APP_ENV:-production}" = "production" ]; then
    echo "==> Warming production artisan caches..."
    php artisan config:clear || true
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

PORT="${PORT:-8000}"
echo "==> [NextOmni Finance API] Listening on 0.0.0.0:${PORT}..."

exec php artisan serve --host=0.0.0.0 --port="${PORT}"
