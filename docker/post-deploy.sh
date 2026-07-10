#!/bin/sh
# Sau git pull — migrate, clear cache, seed HTX import config, restart API.
# Usage: cd backend && sh docker/post-deploy.sh
set -e

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

. "$ROOT/docker/resolve-php.sh"
if ! resolve_php_bin; then
  if command -v docker >/dev/null 2>&1 && [ -f "$ROOT/docker-compose.yml" ]; then
    echo "=== Docker: migrate + cache clear ==="
    docker compose exec -T app php artisan route:clear
    docker compose exec -T app php artisan config:clear
    docker compose exec -T app php artisan migrate --force
    docker compose exec -T app php artisan nav-menu:sync
    docker compose exec -T app php artisan db:seed --class=HopTacXaImportConfigSeeder --force
    docker compose exec -T app php artisan route:list --path=hop-tac-xa | head -10
    docker compose restart app queue
    exit 0
  fi
  php_not_found_help
  exit 1
fi

echo "=== [1/5] Composer autoload ==="
if [ -f "$ROOT/composer.json" ]; then
  composer dump-autoload -o 2>/dev/null || "$PHP_BIN" "$(command -v composer)" dump-autoload -o 2>/dev/null || true
fi

echo "=== [2/5] Clear route/config cache ==="
"$PHP_BIN" artisan route:clear
"$PHP_BIN" artisan config:clear

echo "=== [3/5] Migrate ==="
"$PHP_BIN" artisan migrate --force

echo "=== [3b] Sync nav menu defaults ==="
"$PHP_BIN" artisan nav-menu:sync

echo "=== [4/5] Seed HTX import config ==="
"$PHP_BIN" artisan db:seed --class=HopTacXaImportConfigSeeder --force

echo "=== [5/5] Verify hop-tac-xa routes ==="
"$PHP_BIN" artisan route:list --path=hop-tac-xa | head -12

echo ""
echo "Restart API: sudo sh start-api.sh"
