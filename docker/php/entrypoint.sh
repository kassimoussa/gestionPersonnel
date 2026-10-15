#!/bin/sh
set -e

# Préparation exécutée uniquement par le conteneur PHP-FPM (pas par le scheduler
# ni par les commandes ponctuelles). Les commandes artisan tournent sous
# www-data pour ne pas créer de fichiers root dans storage/.
if [ "$1" = "php-fpm" ]; then
    mkdir -p storage/app/public storage/app/private storage/fonts \
        storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
    chown -R www-data:www-data storage bootstrap/cache

    runuser -u www-data -- php artisan migrate --force
    runuser -u www-data -- php artisan optimize
fi

exec "$@"
