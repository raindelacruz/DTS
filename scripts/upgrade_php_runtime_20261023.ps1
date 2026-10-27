$ErrorActionPreference = 'Stop'
$PhpRoot = [IO.Path]::GetFullPath('C:\xampp\php')
$BackupRoot = [IO.Path]::GetFullPath('C:\xampp\php.pre-security-20261023')
$FailedRoot = [IO.Path]::GetFullPath('C:\xampp\php.failed-security-20261023-attempt2')
$StageRoot = [IO.Path]::GetFullPath('C:\xampp\htdocs\DTS\storage\maintenance\php-security-20261023')
$CandidateRoot = [IO.Path]::GetFullPath((Join-Path $StageRoot 'runtime'))
$Httpd = 'C:\xampp\apache\bin\httpd.exe'
$XamppConfig = 'C:\xampp\apache\conf\extra\httpd-xampp.conf'

# All directory moves below have fixed, validated targets; nothing is recursively deleted.
foreach ($target in @($PhpRoot, $BackupRoot, $FailedRoot)) {
    if ([IO.Path]::GetDirectoryName($target) -ne 'C:\xampp') { throw 'Unexpected runtime target.' }
}
if ($CandidateRoot -ne 'C:\xampp\htdocs\DTS\storage\maintenance\php-security-20261023\runtime') {
    throw 'Unexpected candidate directory.'
}
if ((Test-Path -LiteralPath $BackupRoot) -or (Test-Path -LiteralPath $FailedRoot)) {
    throw 'A backup or failed-runtime directory already exists; refusing to overwrite it.'
}
$archive = Join-Path $StageRoot 'php-8.2.35-Win32-vs16-x64.zip'
foreach ($supportFile in @('extras\browscap.ini', 'pear\PEAR.php', 'libssh2.dll')) {
    if (!(Test-Path -LiteralPath (Join-Path $CandidateRoot $supportFile) -PathType Leaf)) {
        throw ('Missing required runtime support file: ' + $supportFile)
    }
}
if ((Get-FileHash -LiteralPath $archive -Algorithm SHA256).Hash -ne 'CFA6000BE835931961E40740BD3B51111700061D6655DDBFE5A2125807947457') {
    throw 'Official PHP archive checksum mismatch.'
}
if ((Get-FileHash -LiteralPath (Join-Path $PhpRoot 'php.ini')).Hash -ne
    (Get-FileHash -LiteralPath (Join-Path $CandidateRoot 'php.ini')).Hash) {
    throw 'Live PHP configuration changed after staging; restage before deployment.'
}
if ((Get-FileHash -LiteralPath 'C:\xampp\apache\conf\extra\httpd-xampp.conf').Hash -ne
    (Get-FileHash -LiteralPath (Join-Path $StageRoot 'original-httpd-xampp.conf')).Hash) {
    throw 'Apache PHP configuration changed after staging.'
}
$baseline = Get-Content -LiteralPath (Join-Path $StageRoot 'web-baseline.json') -Raw | ConvertFrom-Json
$stagedHealth = Get-Content -LiteralPath (Join-Path $StageRoot 'staged-web-health.json') -Raw | ConvertFrom-Json
if ($stagedHealth.version -ne '8.2.35' -or !$stagedHealth.database_read_only -or !$stagedHealth.pdf_generation) {
    throw 'Staged web verification has not passed.'
}
& $Httpd -t -f (Join-Path $StageRoot 'staged-main.conf')
if ($LASTEXITCODE -ne 0) { throw 'Patched Apache module validation failed.' }
$wasRunning = (Get-Service -Name Apache2.4).Status -eq 'Running'
$movedOld = $false
$movedNew = $false
$configChanged = $false
try {
    Stop-Service -Name Apache2.4
    (Get-Service -Name Apache2.4).WaitForStatus('Stopped', [TimeSpan]::FromSeconds(30))
    for ($attempt = 0; $attempt -lt 30; $attempt++) {
        if (!(Get-Process -Name httpd -ErrorAction SilentlyContinue)) { break }
        Start-Sleep -Seconds 1
    }
    if (Get-Process -Name httpd -ErrorAction SilentlyContinue) {
        throw 'Apache workers remain; aborting before changing the runtime.'
    }
    Rename-Item -LiteralPath $PhpRoot -NewName 'php.pre-security-20261023'
    $movedOld = $true
    Move-Item -LiteralPath $CandidateRoot -Destination $PhpRoot
    $movedNew = $true
    Copy-Item -LiteralPath (Join-Path $StageRoot 'httpd-xampp-patched.conf') -Destination $XamppConfig
    $configChanged = $true
    & $Httpd -t
    if ($LASTEXITCODE -ne 0) { throw 'Apache configuration failed with the installed runtime.' }
    Start-Service -Name Apache2.4
    (Get-Service -Name Apache2.4).WaitForStatus('Running', [TimeSpan]::FromSeconds(30))
    $verified = $null
    for ($attempt = 0; $attempt -lt 20; $attempt++) {
        try {
            $response = Invoke-WebRequest -UseBasicParsing -TimeoutSec 5 -Uri 'http://127.0.0.1/runtime-security-check-6f42b8c1.php?key=72ad13c64f8950e7' -Headers @{Host='dts.nfa.gov.ph'}
            $verified = $response.Content | ConvertFrom-Json
            break
        } catch { Start-Sleep -Seconds 1 }
    }
    if (!$verified -or $verified.version -ne '8.2.35' -or $verified.sapi -ne 'apache2handler' -or
        !$verified.database_read_only -or !$verified.pdf_generation -or
        $verified.ini -ne 'C:\xampp\php\php.ini') {
        throw 'Post-switch web runtime, database or PDF verification failed.'
    }
    $missingExtensions = @($baseline.extensions | Where-Object { $_ -notin $verified.extensions })
    if ($missingExtensions.Count) { throw ('Missing extensions: ' + ($missingExtensions -join ', ')) }
    foreach ($setting in @('upload_tmp_dir','upload_max_filesize','post_max_size','session_save_path')) {
        if ($baseline.$setting -ne $verified.$setting) { throw ('Unexpected change to ' + $setting) }
    }
    $verified | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath (Join-Path $StageRoot 'web-after.json')
    Write-Output ('Verified Apache PHP ' + $verified.version + '; database and PDF checks passed; existing extensions and settings preserved.')
    Write-Output ('Previous runtime preserved at ' + $BackupRoot)
} catch {
    $deploymentError = $_
    if ($movedOld) {
        if ((Get-Service -Name Apache2.4).Status -ne 'Stopped') {
            Stop-Service -Name Apache2.4
            (Get-Service -Name Apache2.4).WaitForStatus('Stopped', [TimeSpan]::FromSeconds(30))
        }
        if ($movedNew) { Rename-Item -LiteralPath $PhpRoot -NewName 'php.failed-security-20261023-attempt2' }
        Rename-Item -LiteralPath $BackupRoot -NewName 'php'
    }
    if ($configChanged) {
        Copy-Item -LiteralPath (Join-Path $StageRoot 'original-httpd-xampp.conf') -Destination $XamppConfig
    }
    if ($wasRunning -and (Get-Service -Name Apache2.4).Status -ne 'Running') { Start-Service -Name Apache2.4 }
    throw $deploymentError
}
