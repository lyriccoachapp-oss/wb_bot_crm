#!/bin/bash
set -e

echo "=== WorkBangers API startup ==="

# Запускаем PHP-FPM в фоне
php-fpm -D

# Оптимизируем Laravel для production
php artisan config:cache  2>/dev/null || true
php artisan route:cache   2>/dev/null || true

# Выполняем миграции
php artisan migrate --force 2>/dev/null || true

echo "=== Starting Nginx ==="
exec nginx -g "daemon off;"
