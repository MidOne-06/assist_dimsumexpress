#!/usr/bin/env sh
set -eu

mkdir -p public storage/app storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
cp -a /opt/public/. public/
chown -R www-data:www-data storage bootstrap/cache

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --no-interaction
    php artisan storage:link --force || true
    php artisan optimize:clear
fi

# El arranque puede ejecutar Artisan como root antes de que PHP-FPM atienda
# peticiones como www-data. Reaplicar los permisos aquí evita que Blade trate
# de actualizar una vista compilada creada por root y responda con HTTP 500.
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

if [ "${RUN_AS_WWW_DATA:-false}" = "true" ]; then
    exec su-exec www-data:www-data "$@"
fi

exec "$@"
