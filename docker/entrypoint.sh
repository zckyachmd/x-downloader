#!/bin/bash
set -e

echo "▶️ Bootstrapping Laravel..."
cd /var/www

# Migration & seeding
echo "📦 Running migration and seeder..."
if ! php artisan migrate --force; then
  echo "❌ Migration failed, aborting"
  exit 1
fi

php artisan db:seed --class=ConfigSeeder --force || echo "⚠️ Seeder failed, continuing..."

# Start Supervisor
echo "✅ Laravel is ready. Launching Supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
