#!/usr/bin/env bash
set -euo pipefail

fix_permissions() {
    local target

    for target in /app/storage /app/bootstrap/cache; do
        if [ -d "${target}" ]; then
            chown -R www-data:www-data "${target}"
            chmod -R ug+rwX "${target}"
        fi
    done
}

fix_permissions

if [ "$#" -eq 0 ]; then
    set -- php artisan octane:start --server=frankenphp --host=0.0.0.0 --workers=auto --max-requests=500
fi

if [ "$1" = "php" ] && [ "${2:-}" = "artisan" ] && [ "${3:-}" = "octane:start" ]; then
    port_flag_set=0

    for arg in "$@"; do
        if [[ "$arg" == --port=* ]]; then
            port_flag_set=1
            break
        fi
    done

    if [ "$port_flag_set" -eq 0 ]; then
        set -- "$@" "--port=${PORT:-8000}"
    fi
fi

exec gosu www-data "$@"
