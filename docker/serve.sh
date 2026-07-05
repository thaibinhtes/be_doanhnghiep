#!/bin/sh
set -e

. /usr/local/bin/php-limits.sh

HOST="${APP_SERVE_HOST:-0.0.0.0}"
PORT="${APP_SERVE_PORT:-8000}"
SERVER_ROUTER="/var/www/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"

if [ ! -f "$SERVER_ROUTER" ]; then
  echo "ERROR: Laravel server router not found at $SERVER_ROUTER"
  exit 1
fi

echo "[php] starting built-in server ${HOST}:${PORT} with upload limits"

# Do NOT use "artisan serve" — its child php -S process ignores parent -d flags.
exec php $PHP_LIMIT_FLAGS -S "${HOST}:${PORT}" -t /var/www/public "$SERVER_ROUTER"
