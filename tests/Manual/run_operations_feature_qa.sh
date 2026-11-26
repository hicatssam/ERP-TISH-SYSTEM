#!/usr/bin/env bash
# Guarded, short-runner for eight remaining operations regression suites.
# Uses only in-memory SQLite with an ephemeral app key.
set -euo pipefail

QA="/tmp/tish-finance-qa-20261125"
cd "$QA"
[[ "$(pwd -P)" == "$QA" ]] || { echo "REFUSED: not in QA checkout"; exit 1; }
[[ -d .git ]] || { echo "REFUSED: missing QA checkout"; exit 1; }
[[ ! -e .env ]] || { echo "REFUSED: .env exists"; exit 1; }
[[ ! -e bootstrap/cache/config.php ]] || { echo "REFUSED: cached config exists"; exit 1; }
[[ -f vendor/autoload.php && -f phpunit.xml ]] || { echo "REFUSED: missing dependencies"; exit 1; }
command -v openssl >/dev/null
command -v php8.4 >/dev/null

tests=(
  tests/Feature/Finance/IncomingBankTransferDashboardTest.php
  tests/Feature/Finance/OrderBankTransferFlowTest.php
  tests/Feature/Inventory/InventoryStockVisibilityTest.php
  tests/Feature/OrderUpdateInventoryGuardTest.php
  tests/Feature/ProductionLaunchHardeningTest.php
  tests/Feature/Production/ProductionRoutesRegistrationTest.php
  tests/Feature/Sales/SpecialCakeAutoApprovalAndProductionReportTest.php
  tests/Feature/TishRecipeStockFlowTest.php
)
for file in "${tests[@]}"; do
    [[ -f "$file" ]] || { echo "REFUSED: missing test $file"; exit 1; }
done

umask 077
key="base64:$(openssl rand -base64 32)"
cleanup() { rm -f -- "$QA/.env"; }
trap cleanup EXIT

printf 'APP_NAME=TISH-QA\nAPP_ENV=testing\nAPP_DEBUG=false\nAPP_KEY=%s\nDB_CONNECTION=sqlite\nDB_DATABASE=:memory:\nCACHE_STORE=array\nSESSION_DRIVER=array\nQUEUE_CONNECTION=sync\nMAIL_MAILER=array\n' "$key" > "$QA/.env"

echo "=== OPERATIONS QA: ${#tests[@]} TEST FILES ==="
echo "Commit: $(git rev-parse --short HEAD)"

env -u DB_URL -u DATABASE_URL \
    -u DB_HOST -u DB_PORT -u DB_USERNAME -u DB_PASSWORD \
    APP_ENV=testing APP_KEY="$key" \
    DB_CONNECTION=sqlite DB_DATABASE=:memory: \
    CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync MAIL_MAILER=array \
    php8.4 artisan test "${tests[@]}" --stop-on-failure --no-ansi

echo "=== OPERATIONS FEATURE QA PASSED ==="
