FROM composer:latest AS composer

FROM php:8.2-fpm

ARG APP_ENV=production
ENV APP_ENV=${APP_ENV}
ENV COMPOSER_ALLOW_SUPERUSER=1

# --- Install dependencies ---
RUN apt-get update && apt-get install -y \
    git curl unzip zip libpq-dev libzip-dev libonig-dev \
    libxml2-dev libcurl4-openssl-dev libssl-dev supervisor \
    libgmp-dev tzdata \
  && pecl install redis \
  && docker-php-ext-enable redis \
  && docker-php-ext-install pdo pdo_pgsql zip gmp \
  && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www

# --- Copy full app early to avoid missing files during composer phase ---
COPY . .

# --- Composer setup ---
COPY --from=composer /usr/bin/composer /usr/bin/composer
RUN composer install --no-interaction --prefer-dist && \
    if [ "$APP_ENV" = "production" ]; then \
        composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader; \
    fi

# --- Laravel file prep & permission ---
RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache && \
    touch storage/logs/{worker.log,scheduler.log,php-fpm.log} && \
    chmod -R 775 storage bootstrap/cache && \
    chmod -R 755 public && \
    if [ "$APP_ENV" = "production" ]; then \
        chown -R www-data:www-data /var/www; \
    fi

# --- PHP-FPM & Supervisor & Entrypoint ---
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY --chmod=644 docker/php/supervisord.conf /etc/supervisor/supervisord.conf
COPY --chmod=755 docker/entrypoint.sh /docker/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/docker/entrypoint.sh"]
