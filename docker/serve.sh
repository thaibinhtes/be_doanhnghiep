#!/bin/sh
set -e

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PHP_INI="$ROOT/docker/php/api-runtime.ini"
HOST="${APP_SERVE_HOST:-0.0.0.0}"
PORT="${APP_SERVE_PORT:-8000}"
ROUTER="/var/www/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"

echo "[api] PHP built-in server ${HOST}:${PORT} (520M via api-runtime.ini)"

exec php -c "$PHP_INI" \
  -S "${HOST}:${PORT}" \
  -t /var/www/public \
  "$ROUTER"
