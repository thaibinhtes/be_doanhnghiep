#!/bin/sh
# Deploy backend Docker (port 8001) và verify upload 520M.
set -e

cd "$(dirname "$0")/.."
API_PORT="${API_PORT:-8001}"

echo "=== Backend API port: ${API_PORT} (nginx FE: proxy_pass http://127.0.0.1:${API_PORT}) ==="
echo ""

if lsof -i :8000 -sTCP:LISTEN 2>/dev/null | grep -q php; then
  echo "NOTE: PHP cũ vẫn chạy trên :8000 (2M/8M) — OK nếu nginx đã trỏ /api → :${API_PORT}"
  lsof -i :8000 -sTCP:LISTEN 2>/dev/null || true
  echo ""
fi

echo "=== [1/4] Stop + rebuild Docker ==="
docker compose down 2>/dev/null || true
docker compose build --no-cache app queue

echo "=== [2/4] Start ==="
docker network create mobi-net 2>/dev/null || true
docker compose up -d app queue

echo "=== [3/4] Verify ${API_PORT} ==="
sleep 10

HEALTH=$(curl -sf "http://127.0.0.1:${API_PORT}/health.php" 2>/dev/null || echo "FAIL")
echo "$HEALTH"
echo ""

if ! echo "$HEALTH" | grep -q '"upload_max_filesize": "520M"'; then
  echo "❌ FAIL — Docker chưa trả 520M trên port ${API_PORT}"
  docker compose logs app | tail -20
  exit 1
fi

echo "✅ Docker OK — 520M trên port ${API_PORT}"
echo ""

echo "=== [4/4] Cập nhật nginx FE (bắt buộc nếu chưa đổi) ==="
echo "Trong /etc/nginx/sites-enabled/qldn.zsellers.com:"
echo ""
echo "  location /api {"
echo "      proxy_pass http://127.0.0.1:${API_PORT};   # ← đổi từ 8000 sang ${API_PORT}"
echo "      ..."
echo "  }"
echo ""
echo "  sudo nginx -t && sudo systemctl reload nginx"
echo ""
curl -sf "http://127.0.0.1:${API_PORT}/api/health" | head -c 400
echo ""
