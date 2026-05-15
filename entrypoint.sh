#!/bin/bash

DB_HOST="${MYSQLHOST:-localhost}"
DB_USER="${MYSQLUSER:-root}"
DB_PASS="${MYSQLPASSWORD:-}"
DB_NAME="${MYSQLDATABASE:-tour_khach_san}"
DB_PORT="${MYSQLPORT:-3306}"
APP_PORT="${PORT:-8080}"

# create_new_tables.sql chạy ĐẦU TIÊN (chỉ CREATE TABLE IF NOT EXISTS — không bao giờ fail)
# Các file còn lại dùng ALTER TABLE — chạy với --force để tiếp tục dù có lỗi
MIGRATIONS=(
    "/app/database/create_new_tables.sql"
    "/app/database/platform_migration.sql"
    "/app/database/hotel_partner_migration.sql"
    "/app/database/payment_flow_migration.sql"
    "/app/database/alter_support_requests.sql"
    "/app/database/add_indexes.sql"
)

# Migrations chạy nền — PHP khởi động ngay, không chờ
(
    echo "[migrate] Waiting for MySQL at $DB_HOST:$DB_PORT..."
    for i in $(seq 1 30); do
        if mysqladmin ping -h"$DB_HOST" -u"$DB_USER" ${DB_PASS:+-p"$DB_PASS"} -P"$DB_PORT" --silent 2>/dev/null; then
            echo "[migrate] MySQL ready. Running migrations..."
            for sql in "${MIGRATIONS[@]}"; do
                echo "[migrate] -> $(basename $sql)"
                mysql --force -h"$DB_HOST" -u"$DB_USER" ${DB_PASS:+-p"$DB_PASS"} -P"$DB_PORT" "$DB_NAME" < "$sql" \
                    && echo "[migrate]    OK" \
                    || echo "[migrate]    done (some statements skipped)"
            done
            echo "[migrate] All migrations complete."
            break
        fi
        echo "[migrate] MySQL not ready ($i/30)..."
        sleep 2
    done
) &

echo "[startup] Starting PHP on 0.0.0.0:$APP_PORT"
exec php \
    -d output_buffering=On \
    -d display_errors=On \
    -d error_reporting=E_ALL \
    -d log_errors=On \
    -d error_log=/proc/1/fd/2 \
    -d mbstring.language=Vietnamese \
    -d default_charset=UTF-8 \
    -S "0.0.0.0:$APP_PORT"
