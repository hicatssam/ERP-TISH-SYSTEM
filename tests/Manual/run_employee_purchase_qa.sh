#!/usr/bin/env bash
# Safely run employee purchase feature tests on ephemeral SQLite.
# Run only from /tmp/tish-finance-qa-20261125; never on a production checkout.
set -euo pipefail

QA="/tmp/tish-finance-qa-20261125"
cd "$QA"

[[ "$(pwd -P)" == "$QA" ]] || { echo "REFUSED: unexpected directory"; exit 1; }
[[ -d .git ]] || { echo "REFUSED: missing QA git checkout"; exit 1; }
[[ ! -e .env ]] || { echo "REFUSED: .env already exists; do not overwrite"; exit 1; }
[[ ! -e bootstrap/cache/config.php ]] || { echo "REFUSED: cached app config exists"; exit 1; }
[[ -f vendor/autoload.php && -f phpunit.xml ]] || { echo "REFUSED: missing QA dependencies"; exit 1; }
[[ -f tests/Feature/Finance/EmployeePurchaseAccountTest.php ]] || { echo "REFUSED: missing targeted test"; exit 1; }
command -v php8.4 >/dev/null
command -v openssl >/dev/null

umask 077
key="base64:$(openssl rand -base64 32)"
# The full key is ephemeral, never committed or printed.
cleanup() { rm -f -- "$QA/.env"; }
trap cleanup EXIT
printf 'APP_NAME=TISH-QA\nAPP_ENV=testing\nAPP_DEBUG=false\nAPP_KEY=%s\nDB_CONNECTION=sqlite\nDB_DATABASE=:memory:\nCACHE_STORE=array\nSESSION_DRIVER=array\nQUEUE_CONNECTION=sync\nMAIL_MAILER=array\n' "$key" > "$QA/.env"

echo "=== EMPLOYEE PURCHASE FEATURE QA (SQLITE MEMORY) ==="
echo "Commit: $(git rev-parse --short HEAD)"

env -u DB_URL -u DATABASE_URL \
    -u DB_HOST -u DB_PORT -u DB_USERNAME -u DB_PASSWORD \
    APP_ENV=testing APP_KEY="$key" \
    DB_CONNECTION=sqlite DB_DATABASE=:memory: \
    CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync MAIL_MAILER=array \
    php8.4 artisan test tests/Feature/Finance/EmployeePurchaseAccountTest.php --stop-on-failure --no-ansi

echo "=== EMPLOYEE PURCHASE QA PASSED ==="
