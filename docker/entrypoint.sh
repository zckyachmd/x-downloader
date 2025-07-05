#!/bin/bash
set -e

echo "▶️ Bootstrapping Laravel..."
cd /var/www

# Run DB migration
echo "📦 Running database migration..."
php artisan migrate --force --no-interaction

# Run seeder (fail if error)
echo "🌱 Running ConfigSeeder..."
php artisan db:seed --class=ConfigSeeder --force --no-interaction

# Start Supervisor
echo "✅ Laravel is ready. Launching Supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
