#!/bin/sh
set -e

cd /var/www

if [ ! -f .env ]; then
    cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

if [ ! -d vendor ]; then
    composer install --no-interaction --prefer-dist
fi

php artisan storage:link >/dev/null 2>&1 || true

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    attempt=1
    until php artisan migrate --force; do
        if [ "$attempt" -ge 30 ]; then
            echo "Database migrations failed after ${attempt} attempts." >&2
            exit 1
        fi
        attempt=$((attempt + 1))
        echo "Waiting for the database (attempt ${attempt}/30)..."
        sleep 2
    done
fi

exec "$@"
