#!/usr/bin/env bash
# Put the demo database back to exactly what demo.sql describes.
#
#   bash demo/reset.sh
#
# Rehearse the demo, run reset, then run the demo for real from a clean state:
# pending requests restored, enrollment counts restored, brute-force counters
# cleared. Reads DB_* from .env, so XAMPP and MAMP both work.
set -euo pipefail
cd "$(dirname "$0")/.."

[ -f .env ] || { echo "No .env here. Copy .env.example to .env first."; exit 1; }

db_env() { grep -E "^$1=" .env | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
DB_HOST=$(db_env DB_HOST); DB_HOST=${DB_HOST:-localhost}
DB_NAME=$(db_env DB_NAME); DB_NAME=${DB_NAME:-timetable}
DB_USER=$(db_env DB_USER); DB_USER=${DB_USER:-root}
DB_PASS=$(db_env DB_PASS)
DB_PORT=$(db_env DB_PORT); DB_PORT=${DB_PORT:-3306}

# Find a mysql client: PATH first, then the usual XAMPP/MAMP/Homebrew spots.
MYSQL=${MYSQL:-}
if [ -z "$MYSQL" ]; then
  for candidate in "$(command -v mysql || true)" \
      /Applications/XAMPP/xamppfiles/bin/mysql \
      /Applications/MAMP/Library/bin/mysql \
      /opt/homebrew/bin/mysql /usr/local/bin/mysql; do
    if [ -n "$candidate" ] && [ -x "$candidate" ]; then MYSQL=$candidate; break; fi
  done
fi
[ -n "$MYSQL" ] || { echo "mysql client not found. Set MYSQL=/path/to/mysql and retry."; exit 1; }

args=(-h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER")
[ -n "$DB_PASS" ] && args+=("-p$DB_PASS")

echo "Recreating database '$DB_NAME' on $DB_HOST:$DB_PORT ..."
"$MYSQL" "${args[@]}" -e "DROP DATABASE IF EXISTS \`$DB_NAME\`; CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
"$MYSQL" "${args[@]}" "$DB_NAME" < demo.sql

# Lockout from the brute-force protector is the one state that survives an
# import, because it lives on disk.
rm -f storage/bruteforce/*.json 2>/dev/null || true

echo "Done: 9 tables, 6 users, demo rows loaded, login lockouts cleared."
