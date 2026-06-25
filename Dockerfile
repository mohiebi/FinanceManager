# ── Stage 1: build frontend assets ───────────────────────────────────────────
FROM node:24-alpine AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# ── Stage 2: production image ─────────────────────────────────────────────────
FROM php:8.4-fpm-bookworm AS app

# System deps + PHP extensions
# tokenizer / ctype / fileinfo are already compiled into PHP — do not reinstall
RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        libzip-dev \
        libicu-dev \
        libxml2-dev \
        libpng-dev \
        libonig-dev \
        curl \
        unzip \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        bcmath \
        zip \
        intl \
        xml \
        gd \
        opcache \
    && docker-php-ext-enable opcache \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP dependencies first (cached layer)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# Copy full application
COPY --chown=www-data:www-data . .

# Copy built frontend assets from stage 1
COPY --from=frontend --chown=www-data:www-data /app/public/build ./public/build

# Finish composer (run scripts now that full app is present)
RUN composer run-script post-autoload-dump --no-interaction 2>/dev/null || true

# Storage & cache directories with correct ownership
RUN mkdir -p \
        storage/app/public \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Remove dev/Azure artefacts
RUN rm -f public/hot public/hostingstart.html

# Docker config files
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/production.ini
COPY docker/www.conf /usr/local/etc/php-fpm.d/www.conf

EXPOSE 8080

CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
