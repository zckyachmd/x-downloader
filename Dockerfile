# --- STAGE 1: Build Dependencies
FROM composer:2 AS vendor

WORKDIR /app

COPY . .

RUN composer install --prefer-dist --optimize-autoloader

# --- STAGE 2: Build Final Image
FROM php:8.2-fpm

WORKDIR /var/www

# System + PHP Extensions
RUN apt-get update && apt-get install -y \
    git curl zip unzip netcat-openbsd \
    libzip-dev libpq-dev libonig-dev libxml2-dev \
    libpng-dev libjpeg-dev libfreetype6-dev libssl-dev supervisor \
    && docker-php-ext-install pdo pdo_pgsql pgsql zip bcmath opcache \
    && pecl install redis && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy entire Laravel project (explicit)
COPY . /var/www

# Add vendor from stage 1
COPY --from=vendor /app/vendor /var/www/vendor

# Entrypoint
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

# Permissions
RUN mkdir -p /var/log/supervisor /var/run/supervisord \
    && touch /var/log/supervisor/supervisord.log /var/run/supervisord/supervisord.pid \
    && chown -R www-data:www-data \
        /var/www \
        /usr/local/bin/docker-entrypoint.sh \
        /var/log/supervisor /var/run/supervisord \
        /var/log/supervisor/supervisord.log \
        /var/run/supervisord/supervisord.pid \
    && chmod 755 /usr/local/bin/docker-entrypoint.sh \
    && chmod -R 755 /var/www/storage /var/www/bootstrap/cache

USER www-data

CMD ["/usr/local/bin/docker-entrypoint.sh"]
