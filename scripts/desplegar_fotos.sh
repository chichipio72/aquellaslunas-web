#!/usr/bin/env bash

set -Eeuo pipefail

readonly EXPECTED_ROOT='/srv/proyectos/astronomia/web'
readonly FTP_HOST='set.servidoraweb.net'
readonly FTP_PORT='21'
readonly FTP_USER='fotos'

usage() {
    printf 'Uso: %s [--dry-run]\n' "$0" >&2
}

dry_run_option=''
case "${1:-}" in
    '') ;;
    --dry-run) dry_run_option='--dry-run' ;;
    *) usage; exit 2 ;;
esac

if (( $# > 1 )); then
    usage
    exit 2
fi

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
local_root="$(cd -- "${script_dir}/.." && pwd -P)"
storage_root="${local_root}/storage/tienda"

if [[ "$local_root" != "$EXPECTED_ROOT" || ! -f "$local_root/index.php" || ! -f "$local_root/sol-y-luna.php" ]]; then
    printf 'Error: la carpeta local no es %s o no contiene la web esperada.\n' "$EXPECTED_ROOT" >&2
    exit 1
fi

if [[ ! -d "$storage_root" || ! -d "$storage_root/originales" || ! -d "$storage_root/catalogo" ]]; then
    printf 'Error: storage/tienda/ debe contener las carpetas originales/ y catalogo/.\n' >&2
    exit 1
fi

if ! command -v lftp >/dev/null 2>&1; then
    printf 'Error: lftp no está instalado.\n' >&2
    exit 1
fi

if [[ -z "${ASTRONOMY_PHOTOS_FTP_PASSWORD:-}" ]]; then
    printf 'Error: falta la variable ASTRONOMY_PHOTOS_FTP_PASSWORD.\n' >&2
    exit 1
fi

export LFTP_PASSWORD="$ASTRONOMY_PHOTOS_FTP_PASSWORD"
trap 'unset LFTP_PASSWORD' EXIT

printf '%s\n' "Sincronizando el almacenamiento privado de fotografías${dry_run_option:+ (dry-run)}..."

lftp <<LFTP_COMMANDS
set cmd:fail-exit yes
set ftp:ssl-force true
set ftp:ssl-auth TLS
set ftp:ssl-protect-data true
set ssl:verify-certificate true
set ssl:check-hostname true
open --env-password -u "${FTP_USER}" -p ${FTP_PORT} ftp://${FTP_HOST}
mirror --reverse ${dry_run_option} --verbose "${storage_root}/originales/" ./originales/
mirror --reverse ${dry_run_option} --verbose "${storage_root}/catalogo/" ./catalogo/
bye
LFTP_COMMANDS

printf '%s\n' "Sincronización privada completada${dry_run_option:+ en modo dry-run}."
