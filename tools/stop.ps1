$ErrorActionPreference = 'Stop'
$pidFile = Join-Path $PSScriptRoot 'server.pid'
if(-not (Test-Path -LiteralPath $pidFile)) {Write-Host 'No ScholarDesk launcher record found.';exit}
$record=Get-Content -LiteralPath $pidFile -Raw | ConvertFrom-Json
$server=Get-Process -Id $record.id -ErrorAction SilentlyContinue
if($server -and $server.Path -eq $record.executable -and $server.StartTime.ToUniversalTime().ToString('o') -eq $record.started) {
    Stop-Process -Id $server.Id
    Write-Host 'ScholarDesk stopped. Your saved records are kept.'
} else {Write-Host 'The recorded ScholarDesk server is no longer running.'}
