#!/bin/sh
# Xóa toàn bộ dữ liệu doanh nghiệp (+ bảng liên quan).
# Usage (trong thư mục backend):
#   docker compose exec app sh docker/wipe-doanh-nghiep.sh
#   docker compose exec app sh docker/wipe-doanh-nghiep.sh --yes
# Host (có php):
#   sh docker/wipe-doanh-nghiep.sh --yes

set -e

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [ "${1:-}" != "--yes" ] && [ "${WIPE_DOANH_NGHIEP_YES:-}" != "1" ]; then
  echo "⚠️  Sẽ XÓA TOÀN BỘ doanh nghiệp và dữ liệu liên quan:"
  echo "   - doanh_nghieps"
  echo "   - member_companies"
  echo "   - dn_dinh_danh_lich_sus"
  echo "   - doanh_nghiep_import_job_rows"
  echo "   - doanh_nghiep_import_jobs"
  echo ""
  echo "Chạy lại với: sh docker/wipe-doanh-nghiep.sh --yes"
  exit 1
fi

if [ -f "$ROOT/docker/resolve-php.sh" ]; then
  . "$ROOT/docker/resolve-php.sh"
  resolve_php_bin || true
fi
PHP_BIN="${PHP_BIN:-php}"

echo "=== Xóa dữ liệu doanh nghiệp ==="

"$PHP_BIN" artisan tinker --execute="
use Illuminate\Support\Facades\DB;

DB::transaction(function () {
    \$counts = [
        'import_job_rows' => DB::table('doanh_nghiep_import_job_rows')->count(),
        'import_jobs' => DB::table('doanh_nghiep_import_jobs')->count(),
        'dinh_danh_lich_su' => DB::table('dn_dinh_danh_lich_sus')->count(),
        'member_companies' => DB::table('member_companies')->count(),
        'doanh_nghieps' => DB::table('doanh_nghieps')->count(),
    ];

    DB::table('doanh_nghiep_import_job_rows')->delete();
    DB::table('doanh_nghiep_import_jobs')->delete();
    DB::table('dn_dinh_danh_lich_sus')->delete();
    DB::table('member_companies')->delete();
    DB::table('doanh_nghieps')->delete();

    echo json_encode(\$counts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
});
"

echo ""
echo "✅ Đã xóa xong. Kiểm tra:"
"$PHP_BIN" artisan tinker --execute="echo 'doanh_nghieps=' . \App\Models\DoanhNghiep::count() . PHP_EOL;"
