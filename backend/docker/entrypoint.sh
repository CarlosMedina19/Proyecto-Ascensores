#!/bin/sh
# Arranque de SIGMA en producción. Solo el servicio web (SIGMA_ROL=web) migra y prepara la base;
# la cola y el programador de tareas esperan a que la API esté lista.
set -e

cd /var/www/html

# El volumen de archivos (fotos, firmas, informes, certificados) puede llegar vacío o con otro dueño.
mkdir -p storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/fonts
chown -R www-data:www-data storage bootstrap/cache

# Los comandos de Laravel corren como www-data (el mismo usuario de Apache).
artisan() { su -s /bin/sh www-data -c "php artisan $*"; }

if [ "${SIGMA_ROL:-web}" = "web" ]; then
    echo "SIGMA: esperando la base de datos…"
    i=0
    until php -r 'new PDO("pgsql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD"));' >/dev/null 2>&1; do
        i=$((i + 1))
        if [ "$i" -ge 60 ]; then echo "SIGMA: la base de datos no respondió." >&2; exit 1; fi
        sleep 2
    done

    artisan migrate --force
    # Roles, permisos, configuración y el administrador (idempotentes).
    artisan db:seed --force
else
    # Cola y programador: esperan a que la API haya migrado.
    sleep 20
fi

artisan config:cache
artisan route:cache
artisan event:cache

exec "$@"
