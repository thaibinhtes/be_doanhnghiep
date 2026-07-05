#!/bin/sh
set -e

. /usr/local/bin/bootstrap.sh
. /usr/local/bin/php-limits.sh

exec php $PHP_LIMIT_FLAGS artisan serve --host=0.0.0.0 --port=8000
