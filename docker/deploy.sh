#!/bin/sh
# Deploy backend — dừng PHP cũ trên :8000, chạy Docker 520M cùng port (nginx không cần đổi).
set -e

cd "$(dirname "$0")/.."
API_PORT="${API_PORT:-8000}"

echo "=== Deploy backend API → port ${API_PORT} (520M upload) ==="
echo ""

free_port() {
  PORT="$1"
  if command -v fuser >/dev/null 2>&1; then
    if fuser "${PORT}/tcp" >/dev/null 2>&1; then
      echo "Dừng process đang chiếm port ${PORT}..."
      fuser -k "${PORT}/tcp" 2>/dev/null || true
      sleep 2
    fi
  elif command -v lsof >/dev/null 2>&1; then
    PIDS=$(lsof -ti :"${PORT}" -sTCP:LISTEN 2>/dev/null || true)
    if [ -n "$PIDS" ]; then
      echo "Dừng PID trên port ${PORT}: $PIDS"
      kill $PIDS 2>/dev/null || sudo kill $PIDS 2>/dev/null || true
      sleep 2
    fi
  fi
}

echo "=== [1/5] Giải phóng port ${API_PORT} ==="
free_port "${API_PORT}"

if lsof -i :"${API_PORT}" -sTCP:LISTEN 2>/dev/null | grep -q .; then
  echo "ERROR: Port ${API_PORT} vẫn bị chiếm. Chạy thủ công:"
  echo "  sudo fuser -k ${API_PORT}/tcp"
  lsof -i :"${API_PORT}" -sTCP:LISTEN 2>/dev/null || true
  exit 1
fi

echo "=== [2/5] Stop Docker cũ ==="
docker compose down 2>/dev/null || true

echo "=== [3/5] Rebuild ==="
docker compose build --no-cache app queue

echo "=== [4/5] Start (map ${API_PORT}:8000) ==="
docker network create mobi-net 2>/dev/null || true
API_PORT="${API_PORT}" docker compose up -d app queue

echo "=== [5/5] Verify 520M ==="
sleep 10

HEALTH=$(curl -sf "http://127.0.0.1:${API_PORT}/health.php" 2>/dev/null || echo "FAIL")
echo "$HEALTH"
echo ""

if echo "$HEALTH" | grep -q '"upload_max_filesize": "520M"'; then
  echo "✅ OK — port ${API_PORT} trả upload 520M"
  echo ""
  echo "Nginx FE giữ nguyên: proxy_pass http://127.0.0.1:${API_PORT};"
  curl -sf "http://127.0.0.1:${API_PORT}/api/health" | head -c 350
  echo ""
  exit 0
fi

echo "❌ FAIL"
docker compose logs app | tail -20
exit 1
