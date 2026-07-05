#!/bin/sh
# Chạy trên server để chẩn đoán lỗi upload 2M/8M
# Usage: sh docker/check-upload-limits.sh

echo "=== 1. Process đang listen port 8000 (QUAN TRỌNG) ==="
lsof -i :8000 -sTCP:LISTEN 2>/dev/null || ss -tlnp | grep 8000 || netstat -tlnp 2>/dev/null | grep 8000 || echo "(không có lsof/ss)"
echo ""
echo "Nếu thấy 'php artisan serve' hoặc php KHÔNG phải docker → đó là nguyên nhân limit 2M/8M"
echo ""
curl -s http://127.0.0.1:8000/api/health 2>/dev/null | head -c 500 || echo "FAIL: không kết nối được :8000"
echo ""
echo ""

echo "=== 2. Qua FE nginx (/api/health) ==="
curl -s https://qldn.zsellers.com/api/health 2>/dev/null | head -c 500 || echo "FAIL: không kết nối được qua domain"
echo ""
echo ""

echo "=== 3. PHP trong Docker app ==="
docker compose exec app php -r "echo 'upload_max_filesize=' . ini_get('upload_max_filesize') . PHP_EOL . 'post_max_size=' . ini_get('post_max_size') . PHP_EOL;" 2>/dev/null || docker exec be_doanhnghiep-app-1 php -r "echo 'upload_max_filesize=' . ini_get('upload_max_filesize') . PHP_EOL . 'post_max_size=' . ini_get('post_max_size') . PHP_EOL;" 2>/dev/null || echo "SKIP: docker not running"
echo ""

echo "=== 4. PHP-FPM pool (trong container) ==="
docker compose exec app php-fpm -i 2>/dev/null | grep -E 'upload_max_filesize|post_max_size' | head -2 || echo "SKIP"
echo ""

echo "Kết luận:"
echo "  - uploadMaxFilesize phải là 520M"
echo "  - Nếu bước 1 vẫn 2M → rebuild backend: docker compose up -d --build"
echo "  - Nếu bước 1 OK, bước 2 FAIL → sửa nginx FE (xem fe/nginx-production.conf.example)"
