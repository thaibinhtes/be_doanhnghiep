#!/bin/sh
set -e

. /usr/local/bin/php-limits.sh

HOST="${APP_SERVE_HOST:-0.0.0.0}"
PORT="${APP_SERVE_PORT:-8000}"
ROUTER="/var/www/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"

echo "[api] starting PHP built-in server ${HOST}:${PORT} (520M upload)"

# Single PHP process with -d flags — FPM/nginx-in-docker did NOT apply 520M to web requests.
exec php \
  -d upload_max_filesize=520M \
  -d post_max_size=520M \
  -d max_execution_time=7200 \
  -d max_input_time=7200 \
  -d memory_limit=512M \
  -S "${HOST}:${PORT}" \
  -t /var/www/public \
  "$ROUTER"
