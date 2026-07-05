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

echo "Khởi động API host (start-api.sh)..."
sh "$ROOT/start-api.sh"
