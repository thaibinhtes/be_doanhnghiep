#!/bin/sh
# Deploy backend — dừng PHP cũ trên :8000, rebuild Docker, verify 520M.
set -e

cd "$(dirname "$0")/.."

echo "=== [1/5] Kiểm tra port 8000 ==="
if command -v lsof >/dev/null 2>&1; then
  P8000=$(lsof -i :8000 -sTCP:LISTEN 2>/dev/null || true)
  if [ -n "$P8000" ]; then
    echo "$P8000"
    echo ""
    echo "WARNING: Port 8000 đang bị chiếm (thường là 'php artisan serve' cũ → limit 2M/8M)."
    echo "         Nginx FE proxy /api → 127.0.0.1:8000 sẽ trúng process này, KHÔNG phải Docker!"
    echo ""
    if [ "${FORCE_KILL_PORT_8000:-}" = "1" ]; then
      echo "FORCE_KILL_PORT_8000=1 → dừng process trên :8000..."
      fuser -k 8000/tcp 2>/dev/null || true
      sleep 2
    else
      echo "Chạy lại với: FORCE_KILL_PORT_8000=1 sh docker/deploy.sh"
      echo "Hoặc thủ công: sudo fuser -k 8000/tcp && sudo kill \$(pgrep -f 'artisan serve')"
      exit 1
    fi
  fi
fi

echo "=== [2/5] Stop Docker cũ ==="
docker compose down 2>/dev/null || true

echo "=== [3/5] Rebuild ==="
docker compose build --no-cache app queue

echo "=== [4/5] Start ==="
docker network create mobi-net 2>/dev/null || true
docker compose up -d app queue

echo "=== [5/5] Verify 520M ==="
sleep 10

HEALTH=$(curl -sf http://127.0.0.1:8000/health.php 2>/dev/null || echo "FAIL")
echo "$HEALTH"
echo ""

if echo "$HEALTH" | grep -q '"upload_max_filesize": "520M"'; then
  echo "✅ OK — upload 520M active"
  curl -sf http://127.0.0.1:8000/api/health | head -c 300
  echo ""
  exit 0
fi

echo "❌ FAIL — vẫn chưa 520M"
echo ""
echo "Nguyên nhân thường gặp:"
echo "  1. PHP cũ (artisan serve) vẫn chạy trên :8000 → sudo fuser -k 8000/tcp"
echo "  2. Docker chưa rebuild → docker compose build --no-cache app"
echo "  3. Nginx proxy sai port"
docker compose logs app | tail -15
exit 1
