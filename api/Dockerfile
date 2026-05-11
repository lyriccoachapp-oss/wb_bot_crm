FROM php:8.4-fpm

# Установка зависимостей системы
RUN apt-get update && apt-get install -y \
    nginx \
    git \
    curl \
    libpng-dev \
    libzip-dev \
    libicu-dev \
    libxml2-dev \
    zip \
    unzip \
    && docker-php-ext-install \
        pdo_mysql \
        gd \
        zip \
        intl \
        exif \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Установка Composer
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

# Рабочая директория
WORKDIR /var/www/html

# Копируем зависимости
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Копируем исходный код
COPY . .

# Выполняем скрипты Composer после копирования всего кода
RUN composer run-script post-autoload-dump --no-interaction 2>/dev/null || true

# Права на storage и cache
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Конфигурация Nginx
COPY docker/nginx.conf /etc/nginx/sites-available/default

# Entrypoint скрипт
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
