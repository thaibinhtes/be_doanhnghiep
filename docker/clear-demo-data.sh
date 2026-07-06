#!/bin/sh
# Chỉ xóa dữ liệu doanh nghiệp + hợp tác xã (và job import / lịch sử định danh liên quan).
# KHÔNG xóa: danh mục, đơn vị, users/roles, thành viên, cấu hình import.
#
# Usage (trong thư mục backend):
#   sh docker/clear-demo-data.sh              # xem preview
#   sh docker/clear-demo-data.sh --yes        # xóa
#   sh docker/clear-demo-data.sh --yes --seed # xóa + seed demo DN
#   docker compose exec app sh docker/clear-demo-data.sh --yes
#
# VPS không có php host — script tự chạy qua Docker nếu có docker-compose.yml.

set -e

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

RUN_ARTISAN="sh $ROOT/docker/run-artisan.sh"

CONFIRM=0
RESEED=0

for arg in "$@"; do
  case "$arg" in
    --yes) CONFIRM=1 ;;
    --seed) RESEED=1 ;;
  esac
done

if [ "$CONFIRM" != "1" ] && [ "${CLEAR_DEMO_DATA_YES:-}" != "1" ]; then
  echo "⚠️  Sẽ XÓA dữ liệu doanh nghiệp + hợp tác xã:"
  $RUN_ARTISAN demo:clear --preview
  echo ""
  echo "Giữ nguyên: danh mục, đơn vị, users/roles, thành viên, cấu hình import."
  echo ""
  echo "Chạy lại với: sh docker/clear-demo-data.sh --yes"
  echo "Seed lại demo DN: sh docker/clear-demo-data.sh --yes --seed"
  exit 1
fi

echo "=== Xóa dữ liệu DN + HTX ==="
$RUN_ARTISAN demo:clear --force

if [ "$RESEED" = "1" ]; then
  echo ""
  echo "=== Seed lại dữ liệu demo DN ==="
  $RUN_ARTISAN db:seed --class=DemoDataSeeder --force
fi

echo ""
echo "✅ Hoàn tất."
