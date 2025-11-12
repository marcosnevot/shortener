# --- Build de vendor con Composer (sin Composer en runtime) ---
FROM composer:2 AS vendor
WORKDIR /app

# 1) Instala dependencias sin ejecutar scripts (no hay artisan aún)
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-progress --no-interaction --optimize-autoloader --no-scripts

# 2) Copia el código y AHORA sí ejecuta scripts
COPY . .
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer dump-autoload --optimize \
    && php artisan package:discover --ansi
# --- Runtime PHP-FPM ---
FROM php:8.3-fpm-alpine

RUN apk add --no-cache icu-libs icu-data-full oniguruma libzip \
  && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev oniguruma-dev libzip-dev \
  && docker-php-ext-configure intl \
  && docker-php-ext-install -j$(nproc) intl pdo_mysql opcache \
  && apk del .build-deps

WORKDIR /var/www/html

# Copia de la app + vendor desde el stage anterior
COPY --from=vendor /app /var/www/html

# Permisos mínimos
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
    
# Opcache
RUN { \
  echo "opcache.enable=1"; \
  echo "opcache.enable_cli=0"; \
  echo "opcache.validate_timestamps=0"; \
  echo "opcache.max_accelerated_files=20000"; \
  echo "opcache.memory_consumption=256"; \
  echo "opcache.interned_strings_buffer=16"; \
} > /usr/local/etc/php/conf.d/opcache.ini

USER www-data

# Arranque
CMD ["sh","-lc","php artisan storage:link || true && php artisan migrate --force && php artisan optimize && php-fpm -F"]
