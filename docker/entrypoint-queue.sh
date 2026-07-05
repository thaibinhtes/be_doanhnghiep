#!/bin/sh
set -e

. /usr/local/bin/bootstrap.sh
. /usr/local/bin/php-limits.sh

echo "Starting queue worker (tries=1, timeout=7200)..."
exec php $PHP_LIMIT_FLAGS artisan queue:listen --tries=1 --timeout=7200 --sleep=3
