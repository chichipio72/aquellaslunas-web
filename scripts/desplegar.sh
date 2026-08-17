#!/usr/bin/env bash

set -Eeuo pipefail

readonly EXPECTED_ROOT='/srv/proyectos/astronomia/web'
readonly FTP_HOST='set.servidoraweb.net'
readonly FTP_PORT='21'
readonly FTP_USER='andres@aquellaslunas.com.ar'
readonly ROOT_ROBOTS_REMOTE_PATH='robots.txt'
readonly -a DEPLOY_EXPLICIT_FILES=(
    'scripts/run-scheduled-tasks.php'
    'scripts/migrations/registry.php'
    'scripts/migrations/web-push-astronomy-config.sql'
    'scripts/migrations/create-web-push-astronomy-config.php'
    'scripts/migrations/web-push-notification-types.sql'
    'scripts/migrations/create-web-push-notification-types.php'
    'scripts/migrations/astronomy-request-log.sql'
    'scripts/migrations/create-astronomy-request-log.php'
    'scripts/migrations/web-push-astronomy-event-types.sql'
    'scripts/migrations/create-web-push-astronomy-event-types.php'
    'scripts/migrations/web-push-support-id.sql'
    'scripts/migrations/create-web-push-support-id.php'
    'scripts/cleanup-astronomy-request-log.php'
)

usage() {
    printf 'Uso: %s [--dry-run] [--list-local] [--include-vendor]\n' "$0" >&2
}

dry_run_option=''
list_local=false
include_vendor=false
for argument in "$@"; do
    case "$argument" in
        --dry-run) dry_run_option='--dry-run' ;;
        --list-local) list_local=true ;;
        --include-vendor) include_vendor=true ;;
        *) usage; exit 2 ;;
    esac
done

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
local_root="$(cd -- "${script_dir}/.." && pwd -P)"

if [[ "$local_root" != "$EXPECTED_ROOT" || ! -f "$local_root/index.php" || ! -f "$local_root/sol-y-luna.php" ]]; then
    printf 'Error: la carpeta local no es %s o no contiene la web esperada.\n' "$EXPECTED_ROOT" >&2
    exit 1
fi

DEPLOY_EXCLUDE_PATTERNS=(
    '^\.git(/|$)'
    '^\.github(/|$)'
    '^\.vscode(/|$)'
    '^\.idea(/|$)'
    '(^|/)\.venv(/|$)'
    '^docs(/|$)'
    '^apache(/|$)'
    '^scripts(/|$)'
    '^tests(/|$)'
    '(^|/)local-tools(/|$)'
    '^storage(/|$)'
    '^explorador/benchmarks/results(/|$)'
    '(^|/)Dockerfile[^/]*$'
    '^docker-compose\.yml$'
    '^\.(dockerignore|gitignore)$'
    '(^|/)\.env(\..*)?$'
    '^README\.md$'
    '(^|/)requirements\.txt$'
    '^robots\.txt$'
    '^(cuarto|llena|luna-nueva)\.png$'
    '^php\.ini$'
    '^pytest\.ini$'
    '\.[Cc][Ss][Vv]$'
    '\.[Zz][Ii][Pp]$'
    '(^|/)(logs?)(/|$)'
    '\.log$'
    '(^|/)(tmp|temp)(/|$)'
    '\.(tmp|temp)$'
    '\.pid$'
    '(^|/)(\.cache|cache|__pycache__|coverage)(/|$)'
    '\.(py|py[co]|sw[op])$'
    '(^|/)(\.DS_Store|Thumbs\.db|Desktop\.ini)$'
    '(^|/)~[^/]*$'
    '~$'
    '^\.phpunit\.result\.cache$'
)

if [[ "$include_vendor" != true ]]; then
    DEPLOY_EXCLUDE_PATTERNS+=('^vendor(/|$)')
fi
readonly -a DEPLOY_EXCLUDE_PATTERNS

deployment_path_is_excluded() {
    local relative_path="$1"
    local included_path
    local pattern
    for included_path in "${DEPLOY_EXPLICIT_FILES[@]}"; do
        if [[ "$relative_path" == "$included_path" ]]; then
            return 1
        fi
    done
    for pattern in "${DEPLOY_EXCLUDE_PATTERNS[@]}"; do
        if [[ "$relative_path" =~ $pattern ]]; then
            return 0
        fi
    done
    return 1
}

if [[ "$list_local" == true ]]; then
    candidate_count=0
    while IFS= read -r -d '' local_path; do
        relative_path="${local_path#"$local_root"/}"
        if deployment_path_is_excluded "$relative_path"; then
            continue
        fi
        printf '%s\n' "$relative_path"
        ((candidate_count += 1))
    done < <(find "$local_root" -mindepth 1 \( -type f -o -type l \) -print0 | sort -z)
    printf 'LISTADO LOCAL: %d archivos o enlaces candidatos; no se realizó ninguna conexión ni transferencia.\n' "$candidate_count" >&2
    exit 0
fi

if ! command -v lftp >/dev/null 2>&1; then
    printf 'Error: lftp no está instalado.\n' >&2
    exit 1
fi

if [[ -z "${ASTRONOMY_FTP_PASSWORD:-}" ]]; then
    printf 'Error: falta la variable ASTRONOMY_FTP_PASSWORD.\n' >&2
    exit 1
fi

export LFTP_PASSWORD="$ASTRONOMY_FTP_PASSWORD"
trap 'unset LFTP_PASSWORD' EXIT

if [[ -n "${ASTRONOMY_ROOT_FTP_USER:-}" && -n "${ASTRONOMY_ROOT_FTP_PASSWORD:-}" ]]; then
    if [[ -n "$dry_run_option" ]]; then
        printf 'DRY RUN: se publicaría %s como /%s mediante la cuenta raíz.\n' "$local_root/robots.txt" "$ROOT_ROBOTS_REMOTE_PATH"
    else
        export LFTP_PASSWORD="$ASTRONOMY_ROOT_FTP_PASSWORD"
        lftp <<LFTP_ROOT_COMMANDS
set cmd:fail-exit yes
set ftp:ssl-force true
set ftp:ssl-auth TLS
set ftp:ssl-protect-data true
set ssl:verify-certificate true
set ssl:check-hostname true
open --env-password -u "${ASTRONOMY_ROOT_FTP_USER}" -p ${FTP_PORT} ftp://${FTP_HOST}
put "${local_root}/robots.txt" -o "${ROOT_ROBOTS_REMOTE_PATH}"
bye
LFTP_ROOT_COMMANDS
        export LFTP_PASSWORD="$ASTRONOMY_FTP_PASSWORD"
    fi
else
    printf 'Aviso: no se publicó /robots.txt en la raíz; faltan ASTRONOMY_ROOT_FTP_USER y ASTRONOMY_ROOT_FTP_PASSWORD.\n' >&2
fi

mirror_excludes=''
for pattern in "${DEPLOY_EXCLUDE_PATTERNS[@]}"; do
    mirror_excludes+=" --exclude '${pattern}'"
done

lftp <<LFTP_COMMANDS
set cmd:fail-exit yes
set ftp:ssl-force true
set ftp:ssl-auth TLS
set ftp:ssl-protect-data true
set ssl:verify-certificate true
set ssl:check-hostname true
open --env-password -u "${FTP_USER}" -p ${FTP_PORT} ftp://${FTP_HOST}
mirror --reverse ${dry_run_option} --verbose ${mirror_excludes} "${local_root}/" ./
bye
LFTP_COMMANDS

if [[ -n "$dry_run_option" ]]; then
    for relative_path in "${DEPLOY_EXPLICIT_FILES[@]}"; do
        printf 'DRY RUN: se publicaría explícitamente %s.\n' "$relative_path"
    done
else
    lftp <<LFTP_MIGRATION_COMMANDS
set cmd:fail-exit yes
set ftp:ssl-force true
set ftp:ssl-auth TLS
set ftp:ssl-protect-data true
set ssl:verify-certificate true
set ssl:check-hostname true
open --env-password -u "${FTP_USER}" -p ${FTP_PORT} ftp://${FTP_HOST}
cd scripts/migrations || mkdir -p scripts/migrations
put "${local_root}/scripts/migrations/web-push-astronomy-config.sql" -o "/scripts/migrations/web-push-astronomy-config.sql"
put "${local_root}/scripts/migrations/create-web-push-astronomy-config.php" -o "/scripts/migrations/create-web-push-astronomy-config.php"
put "${local_root}/scripts/migrations/web-push-notification-types.sql" -o "/scripts/migrations/web-push-notification-types.sql"
put "${local_root}/scripts/migrations/create-web-push-notification-types.php" -o "/scripts/migrations/create-web-push-notification-types.php"
put "${local_root}/scripts/migrations/astronomy-request-log.sql" -o "/scripts/migrations/astronomy-request-log.sql"
put "${local_root}/scripts/migrations/create-astronomy-request-log.php" -o "/scripts/migrations/create-astronomy-request-log.php"
put "${local_root}/scripts/migrations/web-push-astronomy-event-types.sql" -o "/scripts/migrations/web-push-astronomy-event-types.sql"
put "${local_root}/scripts/migrations/create-web-push-astronomy-event-types.php" -o "/scripts/migrations/create-web-push-astronomy-event-types.php"
put "${local_root}/scripts/migrations/web-push-support-id.sql" -o "/scripts/migrations/web-push-support-id.sql"
put "${local_root}/scripts/migrations/create-web-push-support-id.php" -o "/scripts/migrations/create-web-push-support-id.php"
put "${local_root}/scripts/migrations/registry.php" -o "/scripts/migrations/registry.php"
put "${local_root}/scripts/run-scheduled-tasks.php" -o "/scripts/run-scheduled-tasks.php"
put "${local_root}/scripts/cleanup-astronomy-request-log.php" -o "/scripts/cleanup-astronomy-request-log.php"
bye
LFTP_MIGRATION_COMMANDS
fi
