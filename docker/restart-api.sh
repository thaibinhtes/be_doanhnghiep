#!/bin/sh
# Khôi phục nhanh khi nginx 502 — chạy: sudo sh docker/restart-api.sh
set -e
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "=== Port 8000 ==="
lsof -i :8000 -sTCP:LISTEN 2>/dev/null || echo "(trống — đây là nguyên nhân 502)"
echo ""

if [ -f "$ROOT/docker-compose.yml" ] && command -v docker >/dev/null 2>&1; then
  if docker compose ps app 2>/dev/null | grep -q "Up"; then
    echo "Docker app đang chạy — restart..."
    docker compose restart app
    sleep 5
    curl -sf http://127.0.0.1:8000/health.php && exit 0
  fi
fi

. "$ROOT/docker/resolve-php.sh"
if ! resolve_php_bin; then
  if [ -f "$ROOT/docker-compose.yml" ] && command -v docker >/dev/null 2>&1; then
    echo "Không có php host — dùng Docker deploy..."
    sh "$ROOT/docker/deploy.sh"
    exit $?
  fi
  php_not_found_help
  exit 1
fi

echo "Khởi động API host (start-api.sh)..."
sh "$ROOT/start-api.sh"
