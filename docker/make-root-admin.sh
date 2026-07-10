#!/bin/sh
# Gán vai trò ROOT cho admin@htqldn.local (hoặc email truyền vào).
# Usage:
#   sh docker/make-root-admin.sh
#   sh docker/make-root-admin.sh admin@htqldn.local
#   sh docker/make-root-admin.sh admin@htqldn.local --reset-password
set -e

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

EMAIL="${1:-admin@htqldn.local}"
shift 2>/dev/null || true

exec sh "$ROOT/docker/run-artisan.sh" user:make-root "$EMAIL" "$@"
