FROM php:8.2-fpm

# Установка зависимостей системы
RUN apt-get update && apt-get install -y \
    nginx \
    curl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Рабочая директория
WORKDIR /var/www/html

# Копируем исходный код
COPY . .

# Права
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Конфигурация Nginx
COPY docker/nginx.conf /etc/nginx/sites-available/default

# Entrypoint скрипт
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
