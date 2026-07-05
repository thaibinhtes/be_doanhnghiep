#!/bin/sh
# Write PHP upload limits at container start (applies to all php processes including artisan serve child).
apply_php_upload_ini() {
  UPLOAD_MAX_MB="${UPLOAD_MAX_MB:-520}"

  if [ -f /var/www/.env ]; then
    ENV_MB="$(grep -E '^UPLOAD_MAX_MB=' /var/www/.env | tail -1 | cut -d= -f2 | tr -d '\r' | tr -d ' ')"
    if [ -n "$ENV_MB" ] && [ "$ENV_MB" -gt 0 ] 2>/dev/null; then
      UPLOAD_MAX_MB="$ENV_MB"
    fi
  fi

  export UPLOAD_MAX_MB

  cat > /usr/local/etc/php/conf.d/zzz-uploads.ini <<EOF
; mobi — large Excel import uploads (generated at container start)
upload_max_filesize = ${UPLOAD_MAX_MB}M
post_max_size = ${UPLOAD_MAX_MB}M
max_execution_time = 7200
max_input_time = 7200
memory_limit = 512M
EOF

  export PHP_LIMIT_FLAGS="-d upload_max_filesize=${UPLOAD_MAX_MB}M -d post_max_size=${UPLOAD_MAX_MB}M -d max_execution_time=7200 -d max_input_time=7200 -d memory_limit=512M"

  echo "[php] configured upload_max_filesize=${UPLOAD_MAX_MB}M post_max_size=${UPLOAD_MAX_MB}M"
  php -r "echo '[php] effective upload_max_filesize=' . ini_get('upload_max_filesize') . ', post_max_size=' . ini_get('post_max_size') . PHP_EOL;"
}

apply_php_upload_ini
