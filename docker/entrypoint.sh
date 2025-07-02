#!/bin/bash
set -e

echo "▶️ Bootstrapping Laravel..."
cd /var/www

# Generate app key if missing
if ! grep -q '^APP_KEY=' .env || grep -q '^APP_KEY=$' .env; then
  echo "🔑 Generating app key..."
  php artisan key:generate
fi

# Laravel boot
echo "⚙️ Laravel setup..."
php artisan config:clear || true
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Migration & seeding
echo "📦 Running migration and seeder..."
if ! php artisan migrate --force; then
  echo "❌ Migration failed, aborting"
  exit 1
fi

php artisan db:seed --class=ConfigSeeder --force || echo "⚠️ Seeder failed, continuing..."

# Start supervisor
echo "✅ Laravel is ready. Launching Supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
