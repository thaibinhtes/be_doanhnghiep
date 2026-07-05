#!/bin/sh
API_PORT="${API_PORT:-8001}"

echo "=== Port 8000 (thường là PHP cũ 2M/8M) ==="
lsof -i :8000 -sTCP:LISTEN 2>/dev/null || echo "(trống)"
echo ""

echo "=== Port ${API_PORT} (Docker backend — phải 520M) ==="
lsof -i :${API_PORT} -sTCP:LISTEN 2>/dev/null || echo "(chưa chạy — chạy: sh docker/deploy.sh)"
echo ""

echo "=== health.php :${API_PORT} ==="
curl -s "http://127.0.0.1:${API_PORT}/health.php" 2>/dev/null || echo "FAIL"
echo ""
echo ""

echo "=== /api/health qua domain ==="
curl -s https://qldn.zsellers.com/api/health 2>/dev/null | head -c 400 || echo "FAIL"
echo ""
echo ""

echo "Nginx FE phải có: proxy_pass http://127.0.0.1:${API_PORT};  (KHÔNG phải 8000)"
