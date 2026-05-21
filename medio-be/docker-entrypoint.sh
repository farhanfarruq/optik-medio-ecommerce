#!/bin/sh
set -e

echo "=== Optik Medio Backend Startup ==="

# Generate APP_KEY jika belum ada
if [ -z "$APP_KEY" ]; then
    echo "[WARN] APP_KEY not set, generating temporary key..."
    export APP_KEY=$(php artisan key:generate --show 2>/dev/null || echo "base64:$(openssl rand -base64 32)")
fi

echo "[INFO] APP_ENV=$APP_ENV"
echo "[INFO] PORT=${PORT:-8000}"
echo "[INFO] DB_HOST=${DB_HOST:-not set}"

# Cache configs (skip errors)
echo "[INFO] Caching config..."
php artisan config:clear 2>/dev/null || true
php artisan config:cache 2>/dev/null || true
php artisan route:cache 2>/dev/null || true
php artisan view:cache 2>/dev/null || true

# Storage link
echo "[INFO] Creating storage link..."
php artisan storage:link 2>/dev/null || true

# Run migrations if DB is available
if [ -n "$DB_HOST" ]; then
    echo "[INFO] Running migrations..."
    php artisan migrate --force 2>/dev/null || echo "[WARN] Migration failed, continuing..."
else
    echo "[WARN] DB_HOST not set, skipping migrations"
fi

# Start PHP server
echo "[INFO] Starting PHP server on 0.0.0.0:${PORT:-8000}..."
exec env PHP_CLI_SERVER_WORKERS=5 php -S 0.0.0.0:${PORT:-8000} -t public/
