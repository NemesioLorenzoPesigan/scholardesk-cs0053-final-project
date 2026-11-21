$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$phpPath = 'C:\xampp\php\php.exe'
if (-not (Test-Path -LiteralPath $phpPath)) {
    $phpCommand = Get-Command php -ErrorAction SilentlyContinue
    if (-not $phpCommand) { throw 'PHP was not found. Install XAMPP or update phpPath in tools/serve.ps1.' }
    $phpPath = $phpCommand.Source
}
$localUrl = 'http://127.0.0.1:8053/'
try {
    $existingPage = Invoke-WebRequest -Uri $localUrl -UseBasicParsing -TimeoutSec 2
    if ($existingPage.Content -notmatch 'ScholarDesk') { throw 'Port 8053 is used by another application.' }
} catch {
    if ($_.Exception.Message -match 'used by another') { throw }
    $arguments = '-S 127.0.0.1:8053 -t "' + (Join-Path $projectRoot 'public') + '"'
    $server = Start-Process -FilePath $phpPath -ArgumentList $arguments -WorkingDirectory $projectRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $projectRoot 'tools/server-output.log') -RedirectStandardError (Join-Path $projectRoot 'tools/server-error.log')
    @{id=$server.Id; started=$server.StartTime.ToUniversalTime().ToString('o'); executable=$phpPath} | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $projectRoot 'tools/server.pid')
    $ready=$false
    for($attempt=0;$attempt -lt 20;$attempt++) {
        Start-Sleep -Milliseconds 250
        try { $page=Invoke-WebRequest -Uri $localUrl -UseBasicParsing -TimeoutSec 1; if($page.Content -match 'ScholarDesk') {$ready=$true;break} } catch {}
    }
    if(-not $ready) {throw 'The local server did not start. See tools/server-error.log.'}
}
Start-Process $localUrl
