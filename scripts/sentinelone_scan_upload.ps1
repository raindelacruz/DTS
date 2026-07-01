param(
    [Parameter(Mandatory = $true, Position = 0)]
    [string] $Path
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
    Write-Error "Upload scan target does not exist."
    exit 2
}

$sentinelRoot = Join-Path ${env:ProgramFiles} 'SentinelOne'
$agentDir = Get-ChildItem -LiteralPath $sentinelRoot -Directory -ErrorAction SilentlyContinue |
    Where-Object { $_.Name -like 'Sentinel Agent *' } |
    Sort-Object Name -Descending |
    Select-Object -First 1

if (-not $agentDir) {
    Write-Error "SentinelOne agent directory was not found."
    exit 2
}

$sentinelCtl = Join-Path $agentDir.FullName 'SentinelCtl.exe'
if (-not (Test-Path -LiteralPath $sentinelCtl -PathType Leaf)) {
    Write-Error "SentinelCtl.exe was not found."
    exit 2
}

$sentinelLogs = Join-Path ${env:ProgramData} 'Sentinel\logs'
$targetLeaf = Split-Path -Leaf $Path
$startedAt = Get-Date

function Test-SentinelThreatReport {
    if (-not (Test-Path -LiteralPath $sentinelLogs -PathType Container)) {
        return $false
    }

    $recentReports = Get-ChildItem -LiteralPath $sentinelLogs -Filter '*.scan_report.txt' -File -ErrorAction SilentlyContinue |
        Where-Object { $_.LastWriteTime -ge $startedAt.AddSeconds(-5) } |
        Sort-Object LastWriteTime -Descending |
        Select-Object -First 5

    foreach ($report in $recentReports) {
        $text = Get-Content -LiteralPath $report.FullName -Raw -ErrorAction SilentlyContinue
        if ($text -match [regex]::Escape($targetLeaf) -and $text -match 'Malicious files \(count:\s*[1-9]') {
            return $true
        }
    }

    $lastScanReport = Join-Path $sentinelLogs 'LastScanReport.log'
    if (Test-Path -LiteralPath $lastScanReport -PathType Leaf) {
        $recentLines = Get-Content -LiteralPath $lastScanReport -Tail 80 -ErrorAction SilentlyContinue
        foreach ($line in $recentLines) {
            if ($line -match [regex]::Escape($targetLeaf) -and $line -match '(?i)virus|malware|potentially unwanted|threat') {
                return $true
            }
        }
    }

    return $false
}

$maxAttempts = 7
$delaySeconds = 5

for ($attempt = 1; $attempt -le $maxAttempts; $attempt++) {
    $previousErrorActionPreference = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    $output = & $sentinelCtl scan_file -i $Path 2>&1 | ForEach-Object { $_.ToString() }
    $exitCode = $LASTEXITCODE
    $ErrorActionPreference = $previousErrorActionPreference

    if ($exitCode -eq 0) {
        Start-Sleep -Milliseconds 500
        if (Test-SentinelThreatReport) {
            Write-Error "SentinelOne reported the upload as malicious."
            exit 10
        }
        exit 0
    }

    $message = ($output | Out-String).Trim()
    if ($message -match 'scan is already running' -and $attempt -lt $maxAttempts) {
        Start-Sleep -Seconds $delaySeconds
        continue
    }

    if ($message) {
        Write-Error $message
    } else {
        Write-Error "SentinelOne scan failed with exit code $exitCode."
    }
    exit $(if ($exitCode -ne 0) { $exitCode } else { 1 })
}

Write-Error "SentinelOne scan could not start because another scan remained active."
exit 3
