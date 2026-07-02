#!/bin/sh
set -e

cd /var/www

composer install --no-interaction --prefer-dist --no-progress

if [ ! -f .env ]; then
  cp .env.example .env
fi

if ! grep -qE '^APP_KEY=base64:' .env; then
  php artisan key:generate --force
fi

if ! grep -qE '^JWT_SECRET=.+' .env; then
  php artisan jwt:secret --force
fi

mkdir -p database
touch database/database.sqlite

rm -f bootstrap/cache/config.php
php artisan config:clear

if ! php artisan migrate --force; then
  echo "WARNING: migrate failed — server will still start. Fix DB then run: php artisan migrate --force"
fi

exec php artisan serve --host=0.0.0.0 --port=8000
