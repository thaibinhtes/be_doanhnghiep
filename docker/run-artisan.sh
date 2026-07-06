#!/bin/sh
# Chạy artisan trên host PHP hoặc trong container Docker (app).
# Usage: sh docker/run-artisan.sh demo:clear --preview
set -e

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

. "$ROOT/docker/resolve-php.sh"

if resolve_php_bin; then
  exec "$PHP_BIN" artisan "$@"
fi

if command -v docker >/dev/null 2>&1 && [ -f "$ROOT/docker-compose.yml" ]; then
  exec docker compose exec -T app php artisan "$@"
fi

php_not_found_help
exit 1
