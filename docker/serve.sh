#!/bin/sh
set -e

APP_ROOT="${APP_ROOT:-/var/www}"
PHP_INI="${APP_ROOT}/docker/php/api-runtime.ini"
HOST="${APP_SERVE_HOST:-0.0.0.0}"
PORT="${APP_SERVE_PORT:-8000}"
PUBLIC="${APP_ROOT}/public"
ROUTER="${APP_ROOT}/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"

if [ ! -f "${PUBLIC}/index.php" ]; then
  echo "[api] ERROR: missing ${PUBLIC}/index.php"
  exit 1
fi

echo "[api] PHP built-in server ${HOST}:${PORT} (docroot ${PUBLIC}, 520M)"

# server.php dùng getcwd() làm public path — phải cd vào public (giống php artisan serve)
cd "${PUBLIC}"
exec php -c "$PHP_INI" \
  -S "${HOST}:${PORT}" \
  -t "${PUBLIC}" \
  "$ROUTER"
