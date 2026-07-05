#!/bin/sh
set -e

. /usr/local/bin/bootstrap.sh
. /usr/local/bin/php-limits.sh

exec /usr/local/bin/serve.sh
