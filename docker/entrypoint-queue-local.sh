#!/bin/sh
# Queue worker only (no nginx) — used by docker-compose.local.yml
set -e
echo "Queue worker waiting for MySQL..."
count=0
until nc -z db 3306 2>/dev/null; do
    count=$((count + 1))
    if [ "$count" -ge 90 ]; then
        echo "MySQL not reachable"
        exit 1
    fi
    sleep 1
done
sleep 3
cd /var/www/html
exec php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
