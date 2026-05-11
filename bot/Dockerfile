FROM php:8.2-apache

# Устанавливаем необходимые зависимости
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    curl \
    && docker-php-ext-install zip mysqli pdo pdo_mysql

# Включаем mod_rewrite (на всякий случай)
RUN a2enmod rewrite

# Устанавливаем ServerName для устранения варнингов Apache
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Копируем код или устанавливаем рабочую директорию
WORKDIR /var/www/html

# Перенаправляем порты если нужно (Apache по умолчанию 80)
EXPOSE 80
