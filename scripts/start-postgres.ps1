<#
    Starts the local PostgreSQL 16 instance used for development.

    The server is a portable build with no Windows service registered, so it
    does not survive a reboot. Run this after logging in, or whenever
    `php artisan migrate` reports "Connection refused".

    Two things this script deliberately avoids, both learned from hanging a
    terminal for half an hour:

      - It does NOT pass -Wait to Start-Process. `pg_ctl start` can sit for a
        long time without returning, and -Wait blocks the caller for exactly as
        long. The server starting and the launcher returning are separate
        events; only the first one matters.

      - It does NOT sleep for a fixed interval and then declare failure. A first
        start after an unclean shutdown fsyncs the whole data directory, which
        on this machine takes ~30 seconds because antivirus scans every file on
        the way past. A 5-second timeout would call that a failure while the
        server was starting perfectly well.

    Instead it polls the port until the server actually answers.
#>

$ErrorActionPreference = 'Stop'

$pgBin = 'C:\Users\mania\tools\pgsql16\bin'
$pgData = 'C:\Users\mania\tools\pgdata'
$port = 5432
$timeoutSeconds = 90

function Test-PostgresReady {
    param([int]$Port)

    return [bool](Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue)
}

if (-not (Test-Path $pgData)) {
    Write-Error "No data directory at $pgData. The cluster has not been initialised."
}

if (Test-PostgresReady -Port $port) {
    Write-Host "PostgreSQL is already up on port $port." -ForegroundColor Green
    exit 0
}

Write-Host 'Starting PostgreSQL...' -ForegroundColor Cyan

# Fire and forget. pg_ctl supervises the server; this shell does not need to.
Start-Process -FilePath "$pgBin\pg_ctl.exe" `
    -ArgumentList @('-D', "`"$pgData`"", '-l', "`"$pgData\server.log`"", '-o', "`"-p $port`"", 'start') `
    -WindowStyle Hidden

$elapsed = 0

while ($elapsed -lt $timeoutSeconds) {
    if (Test-PostgresReady -Port $port) {
        Write-Host "PostgreSQL is up on port $port (took ${elapsed}s)." -ForegroundColor Green
        exit 0
    }

    if ($elapsed -eq 10) {
        Write-Host 'Still starting. A first start after an unclean shutdown fsyncs the data directory; this is normal.' -ForegroundColor DarkGray
    }

    Start-Sleep -Seconds 2
    $elapsed += 2
}

Write-Warning "PostgreSQL did not accept connections within ${timeoutSeconds}s. Last lines of the server log:"
Get-Content "$pgData\server.log" -Tail 20
exit 1
