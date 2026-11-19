#!/bin/sh
# Despliegue de SIGMA en la VPS con respaldo previo, chequeo de salud y reversión automática.
#
#   sh /opt/apps/sigma/backend/scripts/desplegar.sh
#
# 1. Respalda la base (sigma_respaldos).
# 2. Trae el código (git pull --ff-only; nunca reescribe historia).
# 3. Guarda las imágenes actuales como :anterior.
# 4. Construye y levanta SOLO los contenedores de SIGMA.
# 5. Espera a que los chequeos de salud pasen. Si no pasan, vuelve a las imágenes :anterior y termina con error.
# No toca contenedores, imágenes ni redes de otros proyectos.
set -eu

REPO=/opt/apps/sigma
ARCHIVO_COMPOSE="$REPO/backend/docker-compose.prod.yml"
IMAGENES="sigma-backend sigma-frontend sigma-analitica"
CON_SALUD="sigma_backend sigma_frontend sigma_analitica sigma_reverb sigma_db"
ESPERA="${SIGMA_ESPERA_SALUD:-300}"

compose() { docker compose -f "$ARCHIVO_COMPOSE" "$@"; }

estado() { docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$1" 2>/dev/null || echo "ausente"; }

todos_sanos() {
    for c in $CON_SALUD; do
        [ "$(estado "$c")" = "healthy" ] || return 1
    done
    return 0
}

cd "$REPO"
echo "== 1/5 Respaldo de la base"
docker exec sigma_respaldos sh /respaldo.sh ahora
RESPALDO=$(ls -1t "$REPO/backend/respaldos"/sigma_db_*.dump | head -1)
echo "   $RESPALDO"

echo "== 2/5 Código"
ANTERIOR=$(git rev-parse --short HEAD)
git pull --ff-only -q
NUEVO=$(git rev-parse --short HEAD)
echo "   $ANTERIOR -> $NUEVO"

echo "== 3/5 Imágenes actuales guardadas como :anterior"
for i in $IMAGENES; do
    if docker image inspect "$i:latest" >/dev/null 2>&1; then docker tag "$i:latest" "$i:anterior"; fi
done

echo "== 4/5 Construcción y arranque"
compose build
compose up -d

echo "== 5/5 Chequeos de salud (máximo ${ESPERA}s)"
transcurrido=0
while [ "$transcurrido" -lt "$ESPERA" ]; do
    if todos_sanos; then
        echo "   Todo sano: SIGMA $NUEVO en producción."
        exit 0
    fi
    sleep 10
    transcurrido=$((transcurrido + 10))
done

echo "!! Los chequeos de salud no pasaron:"
for c in $CON_SALUD; do echo "   $c: $(estado "$c")"; done
echo "!! Volviendo a las imágenes anteriores ($ANTERIOR)…"
for i in $IMAGENES; do
    if docker image inspect "$i:anterior" >/dev/null 2>&1; then docker tag "$i:anterior" "$i:latest"; fi
done
compose up -d --no-build
echo "!! Reversión aplicada. Revise: docker logs sigma_backend --tail 100"
echo "!! Si la versión nueva aplicó migraciones que la anterior no entiende, restaure la base con el respaldo:"
echo "   $RESPALDO   (procedimiento en backend/README.md, «Operación y emergencias»)"
echo "!! El código del servidor quedó en $NUEVO; el próximo despliegue lo vuelve a intentar."
exit 1
