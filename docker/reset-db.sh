#!/bin/sh
# Reset Postgres volume when password/user no longer matches .env
set -e
cd "$(dirname "$0")/.."
echo "Stopping containers and removing postgres volume..."
docker compose down -v
echo "Starting fresh database..."
docker compose up -d --build
echo "Done. Wait ~30s then check: docker compose logs -f app"
