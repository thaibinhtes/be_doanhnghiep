#!/bin/sh
# Tìm PHP CLI trên VPS (PATH tối thiểu khi chạy sudo sh ... thường không có php).
# Usage: . docker/resolve-php.sh && echo "$PHP_BIN"

resolve_php_bin() {
  if [ -n "${PHP_BIN:-}" ] && [ -x "$PHP_BIN" ]; then
    return 0
  fi

  if command -v php >/dev/null 2>&1; then
    PHP_BIN=$(command -v php)
    return 0
  fi

  for candidate in \
    /usr/bin/php8.4 \
    /usr/bin/php8.3 \
    /usr/bin/php8.2 \
    /usr/bin/php8.1 \
    /usr/bin/php \
    /usr/local/bin/php
  do
    if [ -x "$candidate" ]; then
      PHP_BIN="$candidate"
      return 0
    fi
  done

  return 1
}

php_not_found_help() {
  echo "ERROR: không tìm thấy php trên PATH."
  echo ""
  echo "Cách 1 — Cài PHP CLI (Ubuntu/Debian):"
  echo "  sudo apt update && sudo apt install -y php8.2-cli php8.2-mbstring php8.2-xml php8.2-pgsql php8.2-zip php8.2-bcmath"
  echo "  sudo sh start-api.sh"
  echo ""
  echo "Cách 2 — Chạy API bằng Docker (không cần php host):"
  echo "  cd $(dirname "$0")/.. && sudo sh docker/deploy.sh"
  echo ""
  echo "Cách 3 — Chỉ định tay:"
  echo "  PHP_BIN=/usr/bin/php8.2 sudo sh start-api.sh"
}
