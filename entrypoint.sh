#!/bin/bash

DB_HOST="${MYSQLHOST:-localhost}"
DB_USER="${MYSQLUSER:-root}"
DB_PASS="${MYSQLPASSWORD:-}"
DB_NAME="${MYSQLDATABASE:-tour_khach_san}"
DB_PORT="${MYSQLPORT:-3306}"
APP_PORT="${PORT:-8080}"

MIGRATIONS=(
    "/app/database/platform_migration.sql"
    "/app/database/hotel_partner_migration.sql"
    "/app/database/payment_flow_migration.sql"
)

# Run migrations in background so PHP starts immediately (Railway health check won't timeout)
(
    echo "[migrate] Waiting for MySQL at $DB_HOST:$DB_PORT..."
    for i in $(seq 1 30); do
        if mysqladmin ping -h"$DB_HOST" -u"$DB_USER" ${DB_PASS:+-p"$DB_PASS"} -P"$DB_PORT" --silent 2>/dev/null; then
            echo "[migrate] MySQL is ready. Running migrations..."
            for sql in "${MIGRATIONS[@]}"; do
                echo "[migrate] -> $(basename $sql)"
                mysql -h"$DB_HOST" -u"$DB_USER" ${DB_PASS:+-p"$DB_PASS"} -P"$DB_PORT" "$DB_NAME" < "$sql" \
                    && echo "[migrate]    OK" \
                    || echo "[migrate]    skipped (already applied or error)"
            done
            echo "[migrate] Done."
            break
        fi
        echo "[migrate] MySQL not ready yet ($i/30)..."
        sleep 2
    done
) &

echo "[startup] Starting PHP built-in server on 0.0.0.0:$APP_PORT"
exec php \
    -d output_buffering=On \
    -d display_errors=Off \
    -d error_reporting=E_ALL \
    -d log_errors=On \
    -d error_log=/proc/1/fd/2 \
    -d mbstring.language=Vietnamese \
    -d default_charset=UTF-8 \
    -S "0.0.0.0:$APP_PORT"
