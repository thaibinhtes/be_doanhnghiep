#!/bin/sh
set -e

. /usr/local/bin/bootstrap.sh

exec php artisan serve --host=0.0.0.0 --port=8000
