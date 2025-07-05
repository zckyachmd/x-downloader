FROM php:8.3-fpm

ARG APP_ENV=production
ENV APP_ENV=${APP_ENV}
ENV COMPOSER_ALLOW_SUPERUSER=1

# --- Install system dependencies ---
RUN apt-get update && apt-get install -y \
    git curl unzip zip libpq-dev libzip-dev libonig-dev \
    libxml2-dev libcurl4-openssl-dev libssl-dev supervisor \
    libgmp-dev tzdata \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && docker-php-ext-install pcntl pdo pdo_pgsql zip gmp \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer manually
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy source & install dependencies
COPY . .

RUN composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader \
 && mkdir -p storage/framework/{cache,sessions,views} \
 && mkdir -p bootstrap/cache storage/logs \
 && touch storage/logs/{horizon.log,scheduler.log,php-fpm.log} \
 && chmod -R 775 storage bootstrap/cache \
 && chmod -R 755 public \
 && php artisan config:clear \
 && php artisan config:cache \
 && php artisan route:cache \
 && php artisan view:cache \
 && if [ "$APP_ENV" = "production" ]; then \
      chown -R www-data:www-data /var/www; \
    fi

# Runtime config
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY --chmod=644 docker/php/supervisord.conf /etc/supervisor/supervisord.conf
COPY --chmod=755 docker/entrypoint.sh /docker/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/docker/entrypoint.sh"]
