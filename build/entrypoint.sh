#!/bin/sh
set -e

cd /var/www/html

# 1. Wait for MySQL to accept connections
if [ -n "${DB_HOST:-}" ]; then
    echo "[entrypoint] Waiting for MySQL at ${DB_HOST}:${DB_PORT:-3306}..."
    i=0
    until mysqladmin ping -h"${DB_HOST}" -P"${DB_PORT:-3306}" --silent >/dev/null 2>&1; do
        i=$((i+1))
        if [ "$i" -ge 60 ]; then
            echo "[entrypoint] MySQL did not become ready in time." >&2
            exit 1
        fi
        sleep 1
    done
    echo "[entrypoint] MySQL is up."
fi

# 2. Ensure storage permissions (idempotent)
mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs \
         bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache || true
chmod -R ug+rwX storage bootstrap/cache || true

# 3. Generate APP_KEY if missing (helps first-run; user can set their own in .env)
if [ -z "${APP_KEY:-}" ] || [ "${APP_KEY}" = "" ]; then
    export APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
    echo "[entrypoint] APP_KEY was empty — auto-generated one for this container:"
    echo "[entrypoint]   ${APP_KEY}"
    echo "[entrypoint] Copy the line above into docker/.env to make it permanent."
fi

# 4. Apply DB migrations
php artisan migrate --force --no-interaction || {
    echo "[entrypoint] Migrations failed." >&2
    exit 1
}

# 5. Cache config / routes / views (after env is fully present)
php artisan config:clear >/dev/null 2>&1 || true
php artisan route:clear  >/dev/null 2>&1 || true
php artisan view:clear   >/dev/null 2>&1 || true
php artisan config:cache >/dev/null 2>&1 || true
php artisan route:cache  >/dev/null 2>&1 || true
php artisan view:cache   >/dev/null 2>&1 || true

# 6. Wipe the application cache so files owned by root (created during
#    the artisan commands above, which bootstrap Laravel) are deleted.
#    PHP-FPM runs as www-data and must own the cache files it writes.
php artisan cache:clear >/dev/null 2>&1 || true

# 7. Hand off to supervisord (or whatever CMD was given)
exec "$@"
