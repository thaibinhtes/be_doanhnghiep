#!/bin/sh
# Khởi động API production — 520M upload. KHÔNG dùng "php artisan serve".
# Usage: sudo sh start-api.sh
set -e

ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"

. "$ROOT/docker/resolve-php.sh"
if ! resolve_php_bin; then
  php_not_found_help
  exit 1
fi
echo "PHP: $PHP_BIN ($("$PHP_BIN" -v 2>/dev/null | head -1))"

PORT="${API_PORT:-8000}"
PHP_INI="$ROOT/docker/php/api-runtime.ini"
PUBLIC="$ROOT/public"
ROUTER="$ROOT/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"
PID_FILE="$ROOT/storage/api-server.pid"
LOG_FILE="$ROOT/storage/logs/api-server.log"

mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views

if [ ! -f "$PHP_INI" ]; then
  echo "ERROR: missing $PHP_INI — git pull lại"
  exit 1
fi

if [ ! -f "$ROUTER" ]; then
  echo "ERROR: chạy composer install (thiếu vendor/)"
  exit 1
fi

if [ ! -f "$PUBLIC/index.php" ]; then
  echo "ERROR: missing $PUBLIC/index.php"
  exit 1
fi

stop_api() {
  if [ -f "$PID_FILE" ]; then
    kill "$(cat "$PID_FILE")" 2>/dev/null || true
    rm -f "$PID_FILE"
  fi
  pkill -f "artisan serve" 2>/dev/null || true
  pkill -f "Foundation/resources/server.php" 2>/dev/null || true
  if command -v fuser >/dev/null 2>&1; then
    fuser -k "${PORT}/tcp" 2>/dev/null || true
  fi
  if command -v lsof >/dev/null 2>&1; then
    PIDS=$(lsof -ti :"${PORT}" 2>/dev/null || true)
    [ -n "$PIDS" ] && kill -9 $PIDS 2>/dev/null || true
  fi
}

echo "=== [1/4] Dừng process cũ port ${PORT} ==="
stop_api
sleep 2
stop_api
sleep 1

if lsof -i :"${PORT}" -sTCP:LISTEN 2>/dev/null | grep -q .; then
  echo "ERROR: port ${PORT} vẫn bị chiếm:"
  lsof -i :"${PORT}" -sTCP:LISTEN 2>/dev/null || true
  echo "Chạy: sudo fuser -k ${PORT}/tcp"
  exit 1
fi

echo "=== [2/4] php.ini 520M ==="
"$PHP_BIN" -c "$PHP_INI" -r "echo 'upload=' . ini_get('upload_max_filesize') . ' post=' . ini_get('post_max_size') . PHP_EOL;"

echo "=== [3/4] Start API 0.0.0.0:${PORT} (docroot ${PUBLIC}) ==="
# server.php dùng getcwd() — phải cd public (giống php artisan serve)
cd "$PUBLIC"

if [ "${FOREGROUND:-0}" = "1" ]; then
  exec "$PHP_BIN" -c "$PHP_INI" -S "0.0.0.0:${PORT}" -t "$PUBLIC" "$ROUTER"
fi

: > "$LOG_FILE"
nohup "$PHP_BIN" -c "$PHP_INI" \
  -S "0.0.0.0:${PORT}" \
  -t "$PUBLIC" \
  "$ROUTER" \
  >> "$LOG_FILE" 2>&1 &

echo $! > "$PID_FILE"

echo "=== [4/4] Kiểm tra ==="
OK=0
for i in 1 2 3 4 5 6 7 8 9 10; do
  sleep 1
  if curl -sf "http://127.0.0.1:${PORT}/health.php" >/tmp/mobi-health.json 2>/dev/null; then
    OK=1
    break
  fi
done

if [ "$OK" != "1" ]; then
  echo "❌ API không phản hồi — nginx sẽ 502 Bad Gateway"
  echo "Log:"
  tail -30 "$LOG_FILE" 2>/dev/null || true
  exit 1
fi

cat /tmp/mobi-health.json
echo ""
echo ""

if grep -q '"upload_max_filesize": "520M"' /tmp/mobi-health.json; then
  echo "✅ API OK — 520M upload (PID $(cat "$PID_FILE"))"
else
  echo "⚠️  API chạy nhưng chưa 520M — xem log: tail -f $LOG_FILE"
fi

echo "Test: curl -s http://127.0.0.1:${PORT}/api/health"
