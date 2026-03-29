#!/bin/sh
set -e

echo "Starting DeadCenter..."

# Create required directories
echo "Creating directories..."
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/framework/cache
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/bootstrap/cache

# Fix permissions
echo "Setting permissions..."
chown -R www-data:www-data /var/www/html/storage
chmod -R 775 /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/bootstrap/cache

# Wait for database
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

# Test database connection
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

# Run migrations
echo "Running migrations..."
php artisan migrate --force || echo "Migration had issues, continuing..."

# Clear caches (env vars come from Docker at runtime)
echo "Preparing for production..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear || echo "Cache clear had issues, continuing..."

# Publish Livewire assets
echo "Publishing Livewire assets..."
php artisan livewire:publish --assets 2>/dev/null || true

# Create storage link
php artisan storage:link 2>/dev/null || true

# Final permissions fix
echo "Final permissions check..."
chown -R www-data:www-data /var/www/html/storage
chmod -R 775 /var/www/html/storage

echo "DeadCenter is ready!"

# Start supervisor
exec /usr/bin/supervisord -c /etc/supervisord.conf
