FROM php:8.3-cli-alpine

WORKDIR /var/www/html

# System dependencies
RUN apk add --no-cache \
    curl libpng-dev libxml2-dev zip unzip git \
    oniguruma-dev libzip-dev freetype-dev \
    libjpeg-turbo-dev icu-dev

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    pdo_mysql mbstring exif pcntl bcmath gd zip intl opcache

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy source
COPY . .

# Install PHP dependencies (production, no dev)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Permissions
RUN mkdir -p storage/framework/{cache,sessions,views} \
    storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8000

# PENTING: setiap step pakai || true agar server tetap start meski ada yang gagal
# Railway inject $PORT secara otomatis — shell form wajib agar ter-expand
CMD sh -c "php artisan config:cache || true && \
    php artisan route:cache || true && \
    php artisan view:cache || true && \
    php artisan storage:link || true && \
    php artisan migrate --force || true && \
    php -S 0.0.0.0:${PORT:-8000} -t public/"
