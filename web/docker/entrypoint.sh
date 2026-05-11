#!/bin/bash
set -e

echo "=== WorkBangers Web Panel startup ==="

# Запускаем PHP-FPM в фоне
php-fpm -D

echo "=== Starting Nginx ==="
exec nginx -g "daemon off;"
