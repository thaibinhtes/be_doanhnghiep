#!/bin/sh
set -e

. /usr/local/bin/php-limits.sh

echo "[nginx] starting php-fpm + nginx on :8000 (upload 520M)"

php-fpm -D

php -r "echo '[php-fpm] upload_max_filesize=' . ini_get('upload_max_filesize') . ', post_max_size=' . ini_get('post_max_size') . PHP_EOL;"

exec nginx -g 'daemon off;'
