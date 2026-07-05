#!/bin/sh
# Sửa upload limit PHP trên VPS (không Docker) — chạy: sudo sh docker/fix-host-php.sh
set -e

UPLOAD_MB="${UPLOAD_MB:-520}"

echo "=== Sửa PHP upload → ${UPLOAD_MB}M (cli + fpm) ==="

patch_ini() {
  FILE="$1"
  [ -f "$FILE" ] || return 0
  echo "  → $FILE"
  sed -i "s/^upload_max_filesize = .*/upload_max_filesize = ${UPLOAD_MB}M/" "$FILE"
  sed -i "s/^post_max_size = .*/post_max_size = ${UPLOAD_MB}M/" "$FILE"
  sed -i 's/^max_execution_time = .*/max_execution_time = 7200/' "$FILE"
  sed -i 's/^memory_limit = .*/memory_limit = 512M/' "$FILE"
}

# Debian/Ubuntu: /etc/php/8.x/cli/php.ini và fpm/php.ini
for INI in /etc/php/*/cli/php.ini /etc/php/*/fpm/php.ini; do
  patch_ini "$INI"
done

# Alpine / custom
patch_ini /usr/local/etc/php/php.ini

echo ""
echo "=== Restart PHP-FPM (nếu có) ==="
for SVC in php8.3-fpm php8.2-fpm php8.1-fpm php-fpm; do
  if systemctl is-active --quiet "$SVC" 2>/dev/null; then
    systemctl restart "$SVC"
    echo "  restarted $SVC"
  fi
done

echo ""
echo "=== Dừng php artisan serve cũ (port 8000) ==="
fuser -k 8000/tcp 2>/dev/null || true
pkill -f "artisan serve" 2>/dev/null || true
sleep 2

echo ""
echo "=== Kiểm tra CLI ==="
php -r "echo 'cli upload_max_filesize=' . ini_get('upload_max_filesize') . ', post_max_size=' . ini_get('post_max_size') . PHP_EOL;"

echo ""
echo "=== Khởi động API với 520M (nohup) ==="
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
BACKEND_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$BACKEND_DIR"

nohup php \
  -d upload_max_filesize=${UPLOAD_MB}M \
  -d post_max_size=${UPLOAD_MB}M \
  -d max_execution_time=7200 \
  -d memory_limit=512M \
  -S 0.0.0.0:8000 \
  -t public \
  vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php \
  >> storage/logs/api-server.log 2>&1 &

sleep 3
echo ""
echo "=== health.php ==="
curl -sf http://127.0.0.1:8000/health.php || echo "FAIL"
echo ""
echo ""
echo "=== /api/health ==="
curl -sf http://127.0.0.1:8000/api/health | head -c 400
echo ""
echo ""
echo "Nếu uploadMaxFilesize=520M → upload Excel OK."
echo "Log API: tail -f $BACKEND_DIR/storage/logs/api-server.log"
