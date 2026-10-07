#!/usr/bin/env bash

# Respaldo lógico diario de PostgreSQL para la instalación de asistencias.
# Se ejecuta desde el host de producción y conserva, por defecto, 14 días.
set -euo pipefail
umask 077

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_DIR="${BACKUP_DIR:-${APP_ROOT}/backups/automated}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
TARGET="${BACKUP_DIR}/asistencias-${STAMP}.sql.gz"
TEMP_TARGET="${TARGET}.tmp"

if ! [[ "${RETENTION_DAYS}" =~ ^[0-9]+$ ]] || (( RETENTION_DAYS < 1 )); then
    echo 'RETENTION_DAYS debe ser un entero positivo.' >&2
    exit 2
fi

mkdir -p "${BACKUP_DIR}"
trap 'rm -f "${TEMP_TARGET}"' EXIT

cd "${APP_ROOT}"
docker compose exec -T db sh -lc 'exec pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"' \
    | gzip -c > "${TEMP_TARGET}"

if [[ ! -s "${TEMP_TARGET}" ]]; then
    echo 'El respaldo generado está vacío.' >&2
    exit 1
fi

gzip -t "${TEMP_TARGET}"
mv "${TEMP_TARGET}" "${TARGET}"
sha256sum "${TARGET}" > "${TARGET}.sha256"

find "${BACKUP_DIR}" -maxdepth 1 -type f -name 'asistencias-*.sql.gz' -mtime +"${RETENTION_DAYS}" -delete
find "${BACKUP_DIR}" -maxdepth 1 -type f -name 'asistencias-*.sql.gz.sha256' -mtime +"${RETENTION_DAYS}" -delete

echo "Respaldo verificado: ${TARGET}"
