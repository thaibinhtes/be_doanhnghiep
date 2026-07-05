#!/bin/sh
# Khởi động API production — 520M upload. KHÔNG dùng "php artisan serve".
# Usage: sudo sh start-api.sh
set -e

ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"

PORT="${API_PORT:-8000}"
PHP_INI="$ROOT/docker/php/api-runtime.ini"
ROUTER="$ROOT/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"
PID_FILE="$ROOT/storage/api-server.pid"
LOG_FILE="$ROOT/storage/logs/api-server.log"

mkdir -p storage/logs

if [ ! -f "$PHP_INI" ]; then
  echo "ERROR: missing $PHP_INI"
  exit 1
fi

if [ ! -f "$ROUTER" ]; then
  echo "ERROR: run composer install first (missing Laravel server router)"
  exit 1
fi

echo "=== Dừng API cũ trên port ${PORT} ==="
if [ -f "$PID_FILE" ]; then
  OLD_PID=$(cat "$PID_FILE")
  kill "$OLD_PID" 2>/dev/null || true
fi
pkill -f "artisan serve" 2>/dev/null || true
pkill -f "Illuminate/Foundation/resources/server.php" 2>/dev/null || true
if command -v fuser >/dev/null 2>&1; then
  fuser -k "${PORT}/tcp" 2>/dev/null || true
fi
sleep 2

echo "=== Verify php.ini 520M ==="
php -c "$PHP_INI" -r "echo 'ini upload=' . ini_get('upload_max_filesize') . ' post=' . ini_get('post_max_size') . ' mem=' . ini_get('memory_limit') . PHP_EOL;"

echo "=== Start API :${PORT} ==="
nohup php -c "$PHP_INI" \
  -S "0.0.0.0:${PORT}" \
  -t "$ROOT/public" \
  "$ROUTER" \
  >> "$LOG_FILE" 2>&1 &

echo $! > "$PID_FILE"
sleep 3

echo "=== health.php ==="
HEALTH=$(curl -sf "http://127.0.0.1:${PORT}/health.php" || echo "FAIL")
echo "$HEALTH"
echo ""

if echo "$HEALTH" | grep -q '"upload_max_filesize": "520M"'; then
  echo "✅ OK — API chạy 520M (PID $(cat "$PID_FILE"))"
  echo "   Log: tail -f $LOG_FILE"
  exit 0
fi

echo "❌ FAIL — vẫn chưa 520M. Log:"
tail -20 "$LOG_FILE" 2>/dev/null || true
exit 1
