# TISH release preflight - read-only against the existing business database.
# Run with: powershell -ExecutionPolicy Bypass -File scripts/qa/tish-release-preflight.ps1
# DO NOT run migrate:fresh, db:seed, or PHPUnit against a populated MySQL database.

$ErrorActionPreference = 'Stop'
Set-Location (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path

$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$reportDir = Join-Path (Get-Location) 'storage/app/qa'
New-Item -ItemType Directory -Force -Path $reportDir | Out-Null
$report = Join-Path $reportDir ("preflight-" + $timestamp + ".log")
$failed = New-Object System.Collections.Generic.List[string]
$passed = New-Object System.Collections.Generic.List[string]

function Message([string] $line) {
    Write-Host $line
    Add-Content -Path $report -Value $line -Encoding UTF8
}

function Check([string] $name, [scriptblock] $command) {
    Message ""
    Message ("===== " + $name + " =====")
    try {
        $global:LASTEXITCODE = 0
        $output = & $command 2>&1 | Out-String
        Add-Content -Path $report -Value $output -Encoding UTF8
        if ($output.Length -gt 0) {
            Write-Host ($output.Substring(0, [Math]::Min($output.Length, 1800)))
        }
        if ($global:LASTEXITCODE -ne 0) {
            throw ("Exit code " + $global:LASTEXITCODE)
        }
        $passed.Add($name)
        Message ("PASS: " + $name)
    } catch {
        $failed.Add($name)
        Message ("FAIL: " + $name + " - " + $_.Exception.Message)
    }
}

Message "TISH release readiness preflight (NO business database writes)"
Message ("Generated: " + (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'))
Message ("Directory: " + (Get-Location).Path)
Message ("User code branch: " + (& git branch --show-current))
Message ("Local test runner: sqlite :memory: from phpunit.xml (verified below)")
Message "WARNING: This script does NOT merge PRs, migrate live DB, seed, or deploy."

$phpunit = Get-Content 'phpunit.xml' -Raw
if ($phpunit -notmatch 'name="DB_CONNECTION"\s+value="sqlite"' -or $phpunit -notmatch 'name="DB_DATABASE"\s+value=":memory:"') {
    throw 'Unsafe PHPUnit settings: isolated SQLite :memory: DB not guaranteed. Do NOT run automated tests.'
}
if ($env:DB_CONNECTION -and $env:DB_CONNECTION -ne 'sqlite') {
    throw 'DB_CONNECTION is externally set to a non-SQLite DB. Clear external override before running tests.'
}
if (Test-Path '.env.testing') {
    $testingEnv = Get-Content '.env.testing'
    if ($testingEnv | Select-String -Pattern '^\s*DB_CONNECTION\s*=\s*(mysql|pgsql|sqlsrv)' -Quiet) {
        throw 'Unsafe .env.testing: database points to a non-SQLite service.'
    }
    if ($testingEnv | Select-String -Pattern '^\s*DB_DATABASE\s*=\s*(?!:memory:)\S+' -Quiet) {
        throw 'Unsafe .env.testing: database is not :memory:.'
    }
}

Check 'Git working tree (must be clean for release)' {
    $changes = @(git status --porcelain)
    if ($changes.Count -gt 0 -and ($changes | Where-Object { $_ -ne '' }).Count -gt 0) {
        $changes
        throw 'Uncommitted changes must be committed to a tested release branch before deployment.'
    }
    git log -1 --oneline
}
Check 'Patch formatting / conflict markers' { git diff --check; git diff --cached --check }
Check 'Composer metadata' { composer validate --no-check-publish }
Check 'PHP version' { php -v }
Check 'PHP packages / extensions' { composer check-platform-reqs }
Check 'Laravel configuration and routes' { php artisan route:list --json }
Check 'Current MySQL schema migration status - READ ONLY' {
    $status = php artisan migrate:status 2>&1
    $status
    if ($status -match '\bPending\b') { throw 'Unapplied migrations. Review in staging; do not auto-migrate the live DB.' }
}
Check 'PHPUnit FULL suite, sqlite in-memory' { php -d memory_limit=1024M artisan test }
Check 'Frontend production build' { npm run build }

Message ""
Message "========================================"
Message ("PASSED: " + $passed.Count)
Message ("FAILED: " + $failed.Count)
if ($failed.Count -gt 0) {
    foreach ($failure in $failed) { Message (" - FAILED: " + $failure) }
    Message "RELEASE BLOCKED. Review failures, local code/PR differences, finance reconciliation and manual smoke tests."
    Message ("Full log: " + $report)
    exit 1
}

Message "AUTOMATED PREFLIGHT PASS only. Still required: MySQL integration CI, manual role/branch checks, financial reconciliation, secure deployment configuration and database backup."
Message ("Full log: " + $report)
exit 0
