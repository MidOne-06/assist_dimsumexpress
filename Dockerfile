# syntax=docker/dockerfile:1.7

FROM php:8.3-cli-alpine AS dependencies
WORKDIR /app

RUN apk add --no-cache icu-dev libzip-dev \
    && docker-php-ext-install intl zip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts --ignore-platform-req=ext-intl
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

FROM php:8.3-fpm-alpine AS application
WORKDIR /var/www/html

RUN apk add --no-cache bash curl libzip-dev postgresql-dev icu-dev libxml2-dev su-exec \
    && docker-php-ext-install pdo_pgsql zip intl opcache pcntl \
    && rm -rf /tmp/*

COPY --from=dependencies /app/vendor ./vendor
COPY . .
COPY docker/app/php-fpm-pool.conf /usr/local/etc/php-fpm.d/zz-pool.conf

RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && rm -f bootstrap/cache/*.php \
    && chmod -R a+rX app \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x docker/app/entrypoint.sh \
    && php artisan package:discover --ansi \
    && php artisan filament:assets --ansi \
    && cp -a public /opt/public

ENV COMPOSER_ALLOW_SUPERUSER=1

ENTRYPOINT ["/var/www/html/docker/app/entrypoint.sh"]
CMD ["php-fpm", "-F"]
