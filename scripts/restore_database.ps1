param(
    [Parameter(Mandatory = $true)]
    [string]$BackupSqlPath,
    [string]$Mysql = "mysql"
)

$ErrorActionPreference = "Stop"

if (-not (Test-Path $BackupSqlPath)) {
    throw "Backup file not found: $BackupSqlPath"
}

$database = if ($env:DB_NAME) { $env:DB_NAME } else { "dts_db" }
$hostName = if ($env:DB_HOST) { $env:DB_HOST } else { "localhost" }
$userName = if ($env:DB_USER) { $env:DB_USER } else { "root" }
$password = if ($env:DB_PASS) { $env:DB_PASS } else { "" }

$args = @("-h$hostName", "-u$userName", $database)
if ($password -ne "") {
    $args = @("-p$password") + $args
}

Get-Content -LiteralPath $BackupSqlPath -Raw | & $Mysql @args
Write-Output "Restore completed into database: $database"
