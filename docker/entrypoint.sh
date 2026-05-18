#!/bin/sh
set -e

cd /var/www

if [ ! -f .env ]; then
  cp .env.example .env
fi

if ! grep -qE '^APP_KEY=base64:' .env; then
  php artisan key:generate --force
fi

sed -i 's|^DB_HOST=.*|DB_HOST=postgres|' .env
sed -i 's|^DB_USERNAME=.*|DB_USERNAME=mobi_companies|' .env
sed -i 's|^DB_PASSWORD=.*|DB_PASSWORD=mobi_companies@2026|' .env
sed -i 's|^DB_DATABASE=.*|DB_DATABASE=mobi_companies|' .env

composer install --no-interaction --prefer-dist --no-progress

rm -f bootstrap/cache/config.php
php artisan config:clear

if ! php artisan migrate --force; then
  echo "WARNING: migrate failed — server will still start. Fix DB then run: php artisan migrate --force"
fi

exec php artisan serve --host=0.0.0.0 --port=8000
