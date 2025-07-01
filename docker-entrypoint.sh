#!/bin/bash
set -e

echo "📦 Running Laravel entrypoint..."

# Fix permission for bind-mounted files
if [ ! -x /var/www/artisan ]; then
  echo "🔧 Fixing file permissions for artisan & bootstrap/cache..."
  chmod +x /var/www/artisan
  chmod -R 775 /var/www/storage /var/www/bootstrap/cache
  chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
fi

# Wait for DB
until nc -z "$DB_HOST" "$DB_PORT"; do
  echo "⏳ Waiting for PostgreSQL at $DB_HOST:$DB_PORT..."
  sleep 3
done

# Clear cache
echo "🧼 Clearing Laravel config cache..."
php artisan config:clear
php artisan cache:clear
php artisan config:cache

# Run migrations
echo "🔁 Running migrations..."
php artisan migrate --force

# Run Seeder
echo "🌱 Running seeders..."
php artisan db:seed --force

# Start Supervisor
echo "🚀 Starting Supervisor..."
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
