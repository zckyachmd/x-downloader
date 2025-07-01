# --- STAGE 1: Build Composer Dependencies
FROM composer:2 AS vendor

WORKDIR /app

COPY . .

RUN composer install --prefer-dist --optimize-autoloader

# --- STAGE 2: Build Production App
FROM php:8.2-fpm

# System deps + PHP extensions
RUN apt-get update && apt-get install -y \
    git curl zip unzip netcat-openbsd \
    libzip-dev libpq-dev libonig-dev libxml2-dev \
    libpng-dev libjpeg-dev libfreetype6-dev libssl-dev supervisor \
 && docker-php-ext-install pdo pdo_pgsql pgsql zip bcmath opcache \
 && pecl install redis && docker-php-ext-enable redis \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

# Working directory
WORKDIR /var/www

# Copy project (explicit so you know what’s included)
COPY . /var/www

# Copy composer vendor from builder
COPY --from=vendor /app/vendor /var/www/vendor

# Add composer binary
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy config files
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

# Fix permissions
RUN mkdir -p /var/log/supervisor /var/run/supervisord \
    && touch /var/log/supervisor/supervisord.log /var/run/supervisord/supervisord.pid \
    && chmod -R 755 /var/log/supervisor /var/run/supervisord \
    && chown -R root:root /var/log/supervisor /var/run/supervisord \
    \
    && find public -type f -exec chmod 644 {} \; \
    && find public -type d -exec chmod 755 {} \; \
    && chmod -R 755 /var/www/storage /var/www/bootstrap/cache \
    \
    && chown -R www-data:www-data /var/www \
    && chmod 755 /usr/local/bin/docker-entrypoint.sh

CMD ["/usr/local/bin/docker-entrypoint.sh"]
