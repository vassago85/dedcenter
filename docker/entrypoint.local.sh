#!/bin/sh
set -e

echo "Starting DeadCenter (local Docker)..."

mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/framework/cache
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/bootstrap/cache

chown -R www-data:www-data /var/www/html/storage
chmod -R 775 /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/bootstrap/cache

# Bind-mounted named volumes start empty — populate vendor / frontend once
if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo "Composer dependencies missing — running composer install..."
    composer install --optimize-autoloader --no-interaction
    chown -R www-data:www-data /var/www/html/vendor 2>/dev/null || true
fi

if [ ! -f /var/www/html/public/build/manifest.json ] && [ -f /var/www/html/package.json ]; then
    echo "Vite build missing — running npm install && npm run build..."
    npm install --legacy-peer-deps
    npm run build
    rm -rf node_modules
    chown -R www-data:www-data /var/www/html/public/build 2>/dev/null || true
fi

echo "Waiting for database..."
max_tries=30
count=0
until nc -z db 3306 2>/dev/null; do
    count=$((count + 1))
    if [ $count -ge $max_tries ]; then
        echo "Database not reachable after $max_tries attempts"
        exit 1
    fi
    echo "  Attempt $count/$max_tries - waiting for db:3306..."
    sleep 2
done
echo "Database port is open"

sleep 3

echo "Testing database credentials..."
max_tries=10
count=0
until php -r "new PDO('mysql:host=db;port=3306;dbname=${DB_DATABASE}', '${DB_USERNAME}', '${DB_PASSWORD}');" 2>/dev/null; do
    count=$((count + 1))
    if [ $count -ge $max_tries ]; then
        echo "Database authentication failed after $max_tries attempts"
        break
    fi
    echo "  Auth attempt $count/$max_tries..."
    sleep 2
done
echo "Database is ready"

echo "Running migrations..."
php artisan migrate --force || echo "Migration had issues, continuing..."

echo "Clearing caches (local)..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear || true

php artisan livewire:publish --assets 2>/dev/null || true
php artisan storage:link 2>/dev/null || true

chown -R www-data:www-data /var/www/html/storage
chmod -R 775 /var/www/html/storage

echo "DeadCenter (local) is ready (map host port to container :80, e.g. http://localhost:8091)."

exec /usr/bin/supervisord -c /etc/supervisord.conf
