#!/bin/bash
# Render entrypoint: wire Apache to $PORT, boot Laravel, serve.
set -e
cd /var/www/html

PORT="${PORT:-80}"
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-enabled/000-default.conf

# Public URLs default to Render's own domain (override via env if needed).
export APP_URL="${APP_URL:-${RENDER_EXTERNAL_URL:-http://localhost}}"
export FRONTEND_URL="${FRONTEND_URL:-$APP_URL}"

mkdir -p storage/app/reports storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache database

if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
  [ -f database/database.sqlite ] || touch database/database.sqlite
fi

# Ownership AFTER all files exist (sqlite file included) so Apache can write.
chown -R www-data:www-data storage bootstrap/cache database
chmod -R ug+rwx storage bootstrap/cache

php artisan storage:link --force || true
php artisan migrate --force
# Seeders are idempotent (updateOrCreate) — safe on every deploy.
# Fills admin user, categories and test catalog on fresh Postgres.
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Queue worker (database driver) next to Apache; restarts if it ever exits.
( while true; do php artisan queue:work --sleep=3 --tries=3; sleep 2; done ) &

exec apache2-foreground
