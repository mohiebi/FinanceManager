# ── Stage 1: builder (PHP 8.4 CLI + Node 24) ─────────────────────────────────
# Wayfinder's vite plugin calls `php artisan wayfinder:generate` during
# `npm run build`, so PHP must be present in the same stage as the Node build.
FROM php:8.4-cli-bookworm AS builder

# PHP extensions needed for artisan bootstrap + composer
RUN apt-get update && apt-get install -y --no-install-recommends \
        unzip \
        libzip-dev \
        libicu-dev \
        libxml2-dev \
        libpng-dev \
        libonig-dev \
        curl \
    && docker-php-ext-install -j$(nproc) \
        mbstring \
        zip \
        intl \
        xml \
        gd \
        bcmath \
    && rm -rf /var/lib/apt/lists/*

# Node 24 LTS
RUN curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# PHP deps (cached layer — only rebuilds when lock file changes)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# Copy full application
COPY . .

# Finish composer scripts (generates optimised files that artisan needs)
RUN composer run-script post-autoload-dump --no-interaction 2>/dev/null || true

# NPM deps + Vite build (wayfinder calls php artisan here — PHP is available)
RUN npm ci
RUN npm run build

# ── Stage 2: production runtime ───────────────────────────────────────────────
FROM php:8.4-fpm-bookworm AS app

# System deps + PHP extensions
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

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy the complete built application from the builder stage
COPY --chown=www-data:www-data --from=builder /app .

# Remove artefacts that don't belong in the runtime image
RUN rm -rf node_modules .git tests \
    && rm -f public/hot public/hostingstart.html

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

# Docker config files
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/production.ini
COPY docker/www.conf /usr/local/etc/php-fpm.d/www.conf

EXPOSE 8080

CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
