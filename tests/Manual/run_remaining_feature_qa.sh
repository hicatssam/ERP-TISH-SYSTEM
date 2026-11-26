#!/usr/bin/env bash
# Validate the remaining automated feature and unit test files.
# Runs only inside detached QA checkout and uses a fresh in-memory SQLite DB.
set -euo pipefail

QA="/tmp/tish-finance-qa-20261125"
cd "$QA"
[[ "$(pwd -P)" == "$QA" ]] || { echo "REFUSED: unexpected path"; exit 1; }
[[ -d .git ]] || { echo "REFUSED: not a QA git checkout"; exit 1; }
[[ ! -e .env && ! -e bootstrap/cache/config.php ]] || { echo "REFUSED: existing .env or cached config"; exit 1; }
[[ -f vendor/autoload.php && -f phpunit.xml ]] || { echo "REFUSED: dependencies missing"; exit 1; }
[[ "$(git status --porcelain --untracked-files=no)" == "" ]] || { echo "REFUSED: tracked checkout modified"; exit 1; }
command -v openssl >/dev/null
command -v php8.4 >/dev/null

# These suites already passed independently on the same branch; don't repeat them.
declare -A passed=()
for file in \
    tests/Feature/Finance/AccountingBooksTest.php \
    tests/Feature/Finance/EmployeePurchaseAccountTest.php \
    tests/Feature/Finance/IncomingBankTransferDashboardTest.php \
    tests/Feature/Finance/OrderBankTransferFlowTest.php \
    tests/Feature/Inventory/InventoryStockVisibilityTest.php \
    tests/Feature/OrderUpdateInventoryGuardTest.php \
    tests/Feature/ProductionLaunchHardeningTest.php \
    tests/Feature/Production/ProductionRoutesRegistrationTest.php \
    tests/Feature/Sales/SpecialCakeAutoApprovalAndProductionReportTest.php \
    tests/Feature/TishRecipeStockFlowTest.php
do
    [[ -f "$file" ]] || { echo "REFUSED: missing previously-tested file $file"; exit 1; }
    passed["$file"]=1
done

mapfile -t all < <(find tests/Feature tests/Unit -type f -name '*Test.php' | sort)
remaining=()
for file in "${all[@]}"; do
    [[ -n "${passed[$file]:-}" ]] && continue
    remaining+=("$file")
done

echo "=== REMAINING AUTOMATED QA ==="
echo "Commit: $(git rev-parse --short HEAD)"
echo "Already-tested feature files: ${#passed[@]}"
echo "Other Feature + Unit test files: ${#remaining[@]}"
if (( ${#remaining[@]} == 0 )); then
    echo "=== NO ADDITIONAL TEST FILES ==="
    exit 0
fi

umask 077
key="base64:$(openssl rand -base64 32)"
cleanup() { rm -f -- "$QA/.env"; }
trap cleanup EXIT

printf 'APP_NAME=TISH-QA\nAPP_ENV=testing\nAPP_DEBUG=false\nAPP_KEY=%s\nDB_CONNECTION=sqlite\nDB_DATABASE=:memory:\nCACHE_STORE=array\nSESSION_DRIVER=array\nQUEUE_CONNECTION=sync\nMAIL_MAILER=array\n' "$key" > "$QA/.env"

env -u DB_URL -u DATABASE_URL \
    -u DB_HOST -u DB_PORT -u DB_USERNAME -u DB_PASSWORD \
    APP_ENV=testing APP_KEY="$key" DB_CONNECTION=sqlite DB_DATABASE=:memory: \
    CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync MAIL_MAILER=array \
    php8.4 artisan test "${remaining[@]}" --stop-on-failure --no-ansi

echo "=== REMAINING FEATURE AND UNIT QA PASSED ==="
