#!/bin/sh
# Scheduler loop only (no nginx) — used by docker-compose.local.yml
set -e
echo "Scheduler waiting for MySQL..."
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
exec sh -c "while true; do php artisan schedule:run --verbose --no-interaction; sleep 60; done"
