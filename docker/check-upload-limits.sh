#!/bin/sh
API_PORT="${API_PORT:-8000}"

echo "=== Port ${API_PORT} ==="
lsof -i :${API_PORT} -sTCP:LISTEN 2>/dev/null || echo "(trống)"
echo ""

echo "=== health.php (phải 520M) ==="
curl -s "http://127.0.0.1:${API_PORT}/health.php" 2>/dev/null || echo "FAIL — chạy: cd backend && sudo sh docker/deploy.sh"
echo ""
echo ""

echo "=== /api/health qua domain ==="
curl -s https://qldn.zsellers.com/api/health 2>/dev/null | head -c 400 || echo "FAIL"
echo ""
