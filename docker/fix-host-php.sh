#!/bin/sh
# Wrapper: sửa php.ini hệ thống + gọi start-api.sh
set -e
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

UPLOAD_MB="${UPLOAD_MB:-520}"

patch_ini() {
  FILE="$1"
  [ -f "$FILE" ] || return 0
  echo "  → $FILE"
  sed -i "s/^upload_max_filesize = .*/upload_max_filesize = ${UPLOAD_MB}M/" "$FILE"
  sed -i "s/^post_max_size = .*/post_max_size = ${UPLOAD_MB}M/" "$FILE"
  sed -i 's/^memory_limit = .*/memory_limit = 512M/' "$FILE"
}

echo "=== Patch system php.ini (optional backup) ==="
for INI in /etc/php/*/cli/php.ini /etc/php/*/fpm/php.ini; do
  patch_ini "$INI"
done

for SVC in php8.3-fpm php8.2-fpm php8.1-fpm php-fpm; do
  systemctl restart "$SVC" 2>/dev/null && echo "restarted $SVC" || true
done

echo ""
sh "$ROOT/start-api.sh"
