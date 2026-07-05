#!/bin/sh
# PHP upload limits for artisan serve / queue worker (CLI).
# Reads UPLOAD_MAX_MB from .env when present.

UPLOAD_MAX_MB="${UPLOAD_MAX_MB:-520}"

if [ -f /var/www/.env ]; then
  ENV_MB="$(grep -E '^UPLOAD_MAX_MB=' /var/www/.env | tail -1 | cut -d= -f2 | tr -d '\r' | tr -d ' ')"
  if [ -n "$ENV_MB" ] && [ "$ENV_MB" -gt 0 ] 2>/dev/null; then
    UPLOAD_MAX_MB="$ENV_MB"
  fi
fi

export UPLOAD_MAX_MB
export PHP_LIMIT_FLAGS="-d upload_max_filesize=${UPLOAD_MAX_MB}M -d post_max_size=${UPLOAD_MAX_MB}M -d max_execution_time=7200 -d max_input_time=7200 -d memory_limit=512M"

echo "[php] upload_max_filesize=${UPLOAD_MAX_MB}M post_max_size=${UPLOAD_MAX_MB}M (cli flags: ${PHP_LIMIT_FLAGS})"

php $PHP_LIMIT_FLAGS -r "echo '[php] effective upload_max_filesize=' . ini_get('upload_max_filesize') . ', post_max_size=' . ini_get('post_max_size') . PHP_EOL;"
