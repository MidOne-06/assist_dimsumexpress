# syntax=docker/dockerfile:1.7

FROM php:8.3-cli-alpine AS dependencies
WORKDIR /app

RUN apk add --no-cache icu-dev libzip-dev \
    && docker-php-ext-install intl zip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
ARG COMPOSER_LOCK_SHA=development
# El argumento cambia con composer.lock en cada release y evita reutilizar una
# capa de vendor anterior cuando el builder remoto conserva su caché.
RUN printf '%s\n' "Installing dependencies for lock: ${COMPOSER_LOCK_SHA}" \
    && composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts --ignore-platform-req=ext-intl
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

FROM dependencies AS testing

# La imagen de producción no contiene herramientas de prueba. Esta etapa
# independiente permite ejecutar la suite completa sin añadirlas al runtime.
RUN apk add --no-cache sqlite-dev libxml2-dev oniguruma-dev \
    && docker-php-ext-install pdo_sqlite dom xml xmlwriter mbstring
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts --ignore-platform-req=ext-intl
COPY . .
RUN composer dump-autoload --optimize --no-scripts

FROM node:22-alpine AS frontend
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

FROM php:8.3-fpm-alpine AS application
WORKDIR /var/www/html

RUN apk add --no-cache bash curl libzip-dev postgresql-dev icu-dev libxml2-dev su-exec \
    && docker-php-ext-install pdo_pgsql zip intl opcache pcntl \
    && rm -rf /tmp/*

COPY --from=dependencies /app/vendor ./vendor
COPY . .
COPY --from=frontend /app/public/build ./public/build
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
