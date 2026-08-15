#!/usr/bin/env bash
set -euo pipefail
: "${DB_NAME:?Set DB_NAME before backup}"
: "${DB_USER:?Set DB_USER before backup}"
: "${DB_HOST:=127.0.0.1}"
: "${DB_PORT:=3306}"
: "${BACKUP_DIR:=./backups}"
mkdir -p "$BACKUP_DIR"
file="$BACKUP_DIR/${DB_NAME}_$(date -u +%Y%m%dT%H%M%SZ).sql.gz"
if [[ -n "${DB_PASSWORD:-}" ]]; then export MYSQL_PWD="$DB_PASSWORD"; fi
mysqldump --single-transaction --routines --triggers --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USER" "$DB_NAME" | gzip -9 > "$file"
unset MYSQL_PWD || true
echo "Created $file"
