#!/bin/sh
set -e

cd /var/www

if [ ! -f .env ]; then
  cp .env.example .env
fi

if ! grep -qE '^APP_KEY=base64:' .env; then
  php artisan key:generate --force
fi

# Inside Docker, DB host must be the compose service name (not 127.0.0.1)
sed -i 's|^DB_HOST=.*|DB_HOST=postgres|' .env

composer install --no-interaction --prefer-dist --no-progress

rm -f bootstrap/cache/config.php
php artisan config:clear

if ! php artisan migrate --force; then
  echo "WARNING: migrate failed — server will still start. Fix DB then run: php artisan migrate --force"
fi

exec php artisan serve --host=0.0.0.0 --port=8000
