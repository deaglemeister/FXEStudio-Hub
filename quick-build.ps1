#Requires -Version 5.1
$ErrorActionPreference = 'Stop'

$src = 'c:\Users\svetl\OneDrive\Рабочий стол\FXEStudio'
$dst = 'C:\FXEStudio'

if (-not (Test-Path $dst)) {
    Write-Host "[FXEStudio] Не найден $dst — нужна копия проекта без кириллицы в пути." -ForegroundColor Red
    exit 1
}

$jdk = Get-ChildItem 'C:\Program Files\BellSoft' -Directory -ErrorAction SilentlyContinue | Select-Object -First 1
if (-not $jdk) {
    Write-Host '[FXEStudio] Нужен Liberica JDK 8 Full (BellSoft) с JavaFX.' -ForegroundColor Red
    exit 1
}

Write-Host '[FXEStudio] Sync sources -> C:\FXEStudio ...' -ForegroundColor Cyan
robocopy "$src\develnext\src" "$dst\develnext\src" /E /NFL /NDL /NJH /NJS /nc /ns /np | Out-Null
robocopy "$src\develnext-designer\src" "$dst\develnext-designer\src" /E /NFL /NDL /NJH /NJS /nc /ns /np | Out-Null
robocopy "$src\develnext-lexer\src" "$dst\develnext-lexer\src" /E /NFL /NDL /NJH /NJS /nc /ns /np | Out-Null
Get-ChildItem "$src\platforms" -Directory | ForEach-Object {
    $platformSrc = Join-Path $_.FullName 'src'
    if (Test-Path $platformSrc) {
        $platformDst = Join-Path $dst ("platforms\" + $_.Name + '\src')
        robocopy $platformSrc $platformDst /E /NFL /NDL /NJH /NJS /nc /ns /np | Out-Null
    }
}

$env:JAVA_HOME = $jdk.FullName
$env:Path = "$env:JAVA_HOME\bin;C:\Windows\System32;$env:Path"
Write-Host "[FXEStudio] JAVA_HOME=$env:JAVA_HOME"

Set-Location $dst
Write-Host '[FXEStudio] Gradle build ...' -ForegroundColor Cyan
& .\gradlew.bat :develnext-lexer:jar :develnext-designer:jar :develnext:jar --no-daemon
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

$installLib = Join-Path $dst 'develnext\build\install\develnext\lib'
if (-not (Test-Path $installLib)) {
    Write-Host '[FXEStudio] installDist (первый запуск) ...' -ForegroundColor Yellow
    & .\gradlew.bat :develnext:installDist --no-daemon
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
}

Copy-Item "$dst\develnext\build\libs\DevelNext.jar" "$installLib\DevelNext.jar" -Force
Copy-Item "$dst\develnext-designer\build\libs\develnext-designer-0.9.3-SNAPSHOT.jar" "$installLib\develnext-designer-0.9.3-SNAPSHOT.jar" -Force
Copy-Item "$dst\develnext-lexer\build\libs\develnext-lexer-1.0.jar" "$installLib\develnext-lexer-1.0.jar" -Force

$oneDriveInstallLib = Join-Path $src 'develnext\build\install\develnext\lib'
if (Test-Path (Split-Path $oneDriveInstallLib -Parent)) {
    Copy-Item "$dst\develnext\build\libs\DevelNext.jar" "$oneDriveInstallLib\DevelNext.jar" -Force
    Copy-Item "$dst\develnext-designer\build\libs\develnext-designer-0.9.3-SNAPSHOT.jar" "$oneDriveInstallLib\develnext-designer-0.9.3-SNAPSHOT.jar" -Force
    Copy-Item "$dst\develnext-lexer\build\libs\develnext-lexer-1.0.jar" "$oneDriveInstallLib\develnext-lexer-1.0.jar" -Force
}

Set-Location $src
Write-Host '[FXEStudio] Sync misc/languages -> install ...' -ForegroundColor Cyan
& powershell -NoProfile -ExecutionPolicy Bypass -File "$src\sync-ide-install.ps1"
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host '[FXEStudio] DONE — запуск: run-ide.bat' -ForegroundColor Green
Get-Item "$oneDriveInstallLib\DevelNext.jar", "$oneDriveInstallLib\develnext-designer-0.9.3-SNAPSHOT.jar" | Format-Table Name, Length, LastWriteTime -AutoSize
