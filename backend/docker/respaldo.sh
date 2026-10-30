#!/bin/sh
# RNF-06: respaldo diario de SIGMA (base de datos y archivos: fotos, firmas, informes y certificados).
#
# Corre en el servicio sigma_respaldos. Cada día, a partir de la hora RESPALDO_HORA (hora de Colombia),
# guarda en /respaldos un volcado de PostgreSQL y un comprimido de los archivos, y borra los de más de
# RESPALDO_DIAS días. Para un respaldo inmediato:  docker exec sigma_respaldos sh /respaldo.sh ahora
set -eu

DESTINO=/respaldos
HORA=${RESPALDO_HORA:-3}
DIAS=${RESPALDO_DIAS:-14}

respaldar() {
    marca=$(date +%Y%m%d_%H%M)
    echo "SIGMA respaldo $marca: base de datos…"
    pg_dump --format=custom --no-owner --file="$DESTINO/sigma_db_$marca.dump.parcial"
    mv "$DESTINO/sigma_db_$marca.dump.parcial" "$DESTINO/sigma_db_$marca.dump"

    echo "SIGMA respaldo $marca: archivos…"
    tar -czf "$DESTINO/sigma_archivos_$marca.tar.gz.parcial" -C /archivos .
    mv "$DESTINO/sigma_archivos_$marca.tar.gz.parcial" "$DESTINO/sigma_archivos_$marca.tar.gz"

    # Solo se borran respaldos completos y antiguos de SIGMA.
    find "$DESTINO" -maxdepth 1 -type f \( -name 'sigma_db_*.dump' -o -name 'sigma_archivos_*.tar.gz' \) -mtime "+$DIAS" -delete
    find "$DESTINO" -maxdepth 1 -type f -name '.hecho_*' -mtime "+$DIAS" -delete
    echo "SIGMA respaldo $marca: listo."
}

mkdir -p "$DESTINO"

if [ "${1:-}" = "ahora" ]; then
    respaldar
    exit 0
fi

echo "SIGMA respaldos: un respaldo diario desde las $HORA:00 (hora de Colombia), se conservan $DIAS días."
until pg_isready --quiet; do sleep 5; done

while true; do
    hoy=$(date +%Y%m%d)
    hora=$(date +%H | sed 's/^0//')
    if [ ! -f "$DESTINO/.hecho_$hoy" ] && [ "${hora:-0}" -ge "$HORA" ]; then
        if respaldar; then
            touch "$DESTINO/.hecho_$hoy"
        else
            echo "SIGMA respaldo: falló; se reintenta en 10 minutos." >&2
        fi
    fi
    sleep 600
done
