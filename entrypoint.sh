#!/bin/bash
set -e

DB_HOST="${MYSQLHOST:-localhost}"
DB_USER="${MYSQLUSER:-root}"
DB_PASS="${MYSQLPASSWORD:-}"
DB_NAME="${MYSQLDATABASE:-tour_khach_san}"
DB_PORT="${MYSQLPORT:-3306}"
APP_PORT="${PORT:-8080}"

# Danh sách migration chạy theo thứ tự (đều idempotent — dùng IF NOT EXISTS)
MIGRATIONS=(
    "/app/database/platform_migration.sql"
    "/app/database/hotel_partner_migration.sql"
    "/app/database/payment_flow_migration.sql"
)

echo "[startup] Waiting for MySQL at $DB_HOST:$DB_PORT..."
for i in $(seq 1 20); do
    if mysqladmin ping -h"$DB_HOST" -u"$DB_USER" ${DB_PASS:+-p"$DB_PASS"} -P"$DB_PORT" --silent 2>/dev/null; then
        echo "[startup] MySQL is ready."
        break
    fi
    echo "[startup] MySQL not ready yet, retry $i/20..."
    sleep 2
done

echo "[startup] Running DB migrations..."
for sql in "${MIGRATIONS[@]}"; do
    echo "  -> $(basename $sql)"
    mysql -h"$DB_HOST" -u"$DB_USER" ${DB_PASS:+-p"$DB_PASS"} -P"$DB_PORT" "$DB_NAME" < "$sql" \
        && echo "     OK" \
        || echo "     (skipped or already applied)"
done

echo "[startup] Starting PHP built-in server on 0.0.0.0:$APP_PORT"
exec php \
    -d output_buffering=On \
    -d display_errors=Off \
    -d error_reporting=E_ALL \
    -d mbstring.language=Vietnamese \
    -d default_charset=UTF-8 \
    -S "0.0.0.0:$APP_PORT"
