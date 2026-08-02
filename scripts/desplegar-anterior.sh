#!/usr/bin/env bash

set -Eeuo pipefail

readonly EXPECTED_ROOT='/srv/proyectos/astronomia/web'
readonly FTP_HOST='set.servidoraweb.net'
readonly FTP_PORT='21'
readonly FTP_USER='andres@aquellaslunas.com.ar'

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

if ! command -v lftp >/dev/null 2>&1; then
    printf 'Error: lftp no está instalado.\n' >&2
    exit 1
fi

if [[ -z "${ASTRONOMY_FTP_PASSWORD:-}" ]]; then
    printf 'Error: falta la variable ASTRONOMY_FTP_PASSWORD.\n' >&2
    exit 1
fi

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
local_root="$(cd -- "${script_dir}/.." && pwd -P)"

if [[ "$local_root" != "$EXPECTED_ROOT" || ! -f "$local_root/index.php" || ! -f "$local_root/sol-y-luna.php" ]]; then
    printf 'Error: la carpeta local no es %s o no contiene la web esperada.\n' "$EXPECTED_ROOT" >&2
    exit 1
fi

export LFTP_PASSWORD="$ASTRONOMY_FTP_PASSWORD"
trap 'unset LFTP_PASSWORD' EXIT

lftp <<LFTP_COMMANDS
set cmd:fail-exit yes
set ftp:ssl-force true
set ftp:ssl-auth TLS
set ftp:ssl-protect-data true
set ssl:verify-certificate true
set ssl:check-hostname true
open --env-password -u "${FTP_USER}" -p ${FTP_PORT} ftp://${FTP_HOST}
mirror --reverse ${dry_run_option} --verbose \
    --exclude '^\\.git(/|$)' \
    --exclude '^\\.github(/|$)' \
    --exclude '^\\.vscode(/|$)' \
    --exclude '^\\.idea(/|$)' \
    --exclude '^docs(/|$)' \
    --exclude '^apache(/|$)' \
    --exclude '^scripts(/|$)' \
    --exclude '^tests(/|$)' \
    --exclude '^storage(/|$)' \
    --exclude '(^|/)Dockerfile[^/]*$' \
    --exclude '^docker-compose\\.yml$' \
    --exclude '^\\.(dockerignore|gitignore)$' \
    --exclude '(^|/)\\.env(\\..*)?$' \
    --exclude '^README\\.md$' \
    --exclude '^robots\\.txt$' \
    --exclude '^(cuarto|llena|luna-nueva)\\.png$' \
    --exclude '^php\\.ini$' \
    --exclude '^pytest\\.ini$' \
    --exclude '\\.[Zz][Ii][Pp]$' \
    --exclude '(^|/)(logs?)(/|$)' \
    --exclude '\\.log$' \
    --exclude '(^|/)(tmp|temp)(/|$)' \
    --exclude '\\.(tmp|temp)$' \
    --exclude '\\.pid$' \
    --exclude '(^|/)(\\.cache|cache|__pycache__|coverage)(/|$)' \
    --exclude '\\.(py[co]|sw[op])$' \
    --exclude '(^|/)(\\.DS_Store|Thumbs\\.db|Desktop\\.ini)$' \
    --exclude '(^|/)~[^/]*$' \
    --exclude '~$' \
    --exclude '^\\.phpunit\\.result\\.cache$' \
    "${local_root}/" ./
bye
LFTP_COMMANDS
