#!/bin/sh
# Hardcoded 520M — do not rely on .env for PHP upload limits.

UPLOAD_MAX_MB=520
export UPLOAD_MAX_MB

cat > /usr/local/etc/php/conf.d/zzz-uploads.ini <<'EOF'
; mobi — Excel import (520M, auto-generated at container start)
upload_max_filesize = 520M
post_max_size = 520M
max_execution_time = 7200
max_input_time = 7200
memory_limit = 512M
EOF

export PHP_LIMIT_FLAGS="-d upload_max_filesize=520M -d post_max_size=520M -d max_execution_time=7200 -d max_input_time=7200 -d memory_limit=512M"

echo "[php] upload limits fixed at 520M"
php -r "echo '[php] cli upload_max_filesize=' . ini_get('upload_max_filesize') . ', post_max_size=' . ini_get('post_max_size') . PHP_EOL;"
