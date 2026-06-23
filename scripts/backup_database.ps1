param(
    [string]$OutputDirectory = ".\backups",
    [string]$MysqlDump = "mysqldump"
)

$ErrorActionPreference = "Stop"

if (-not (Test-Path $OutputDirectory)) {
    New-Item -ItemType Directory -Path $OutputDirectory | Out-Null
}

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$database = if ($env:DB_NAME) { $env:DB_NAME } else { "dts_db" }
$hostName = if ($env:DB_HOST) { $env:DB_HOST } else { "localhost" }
$userName = if ($env:DB_USER) { $env:DB_USER } else { "root" }
$password = if ($env:DB_PASS) { $env:DB_PASS } else { "" }
$plainPath = Join-Path $OutputDirectory "$database-$timestamp.sql"
$zipPath = "$plainPath.zip"

$args = @(
    "--single-transaction",
    "--routines",
    "--triggers",
    "--events",
    "--default-character-set=utf8mb4",
    "-h$hostName",
    "-u$userName",
    $database
)

if ($password -ne "") {
    $args = @("-p$password") + $args
}

& $MysqlDump @args | Out-File -LiteralPath $plainPath -Encoding utf8
Compress-Archive -LiteralPath $plainPath -DestinationPath $zipPath -Force
Remove-Item -LiteralPath $plainPath

Write-Output "Backup created: $zipPath"
Write-Output "Encrypt this archive before off-site transfer if the target storage is not already encrypted."
