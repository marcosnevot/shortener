FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    libzip-dev unzip git libicu-dev libonig-dev \
 && docker-php-ext-install pdo_mysql intl opcache \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
