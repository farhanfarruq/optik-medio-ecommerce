FROM php:8.3-cli-alpine

WORKDIR /var/www/html

# System dependencies (tambah openssl untuk key generation)
RUN apk add --no-cache \
    curl libpng-dev libxml2-dev zip unzip git \
    oniguruma-dev libzip-dev freetype-dev \
    libjpeg-turbo-dev icu-dev openssl nodejs npm

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

# Build Filament/Vite assets after Composer creates vendor files
RUN npm ci && npm run build && rm -rf node_modules

# Permissions
RUN mkdir -p storage/framework/{cache,sessions,views} \
    storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Entrypoint script
COPY docker-entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8080

CMD ["/entrypoint.sh"]
