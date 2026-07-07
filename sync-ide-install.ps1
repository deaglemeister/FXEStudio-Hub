$ErrorActionPreference = 'Stop'

$Root = Split-Path -Parent $MyInvocation.MyCommand.Path
$Install = Join-Path $Root 'develnext\build\install\develnext'
$Misc = Join-Path $Root 'develnext\misc'
$StartBat = Join-Path $Install 'bin\develnext.bat'
$StartSh = Join-Path $Install 'bin\develnext'

function Optimize-DevelnextClasspath {
    param(
        [string]$Path,
        [string]$Pattern,
        [string]$Replacement
    )

    if (-not (Test-Path $Path)) {
        return
    }

    $content = Get-Content $Path -Raw
    if ($content -notmatch $Pattern) {
        return
    }

    $updated = [regex]::Replace($content, $Pattern, $Replacement)
    if ($updated -ne $content) {
        Set-Content -Path $Path -Value $updated -Encoding ASCII -NoNewline:$false
    }
}

if (-not (Test-Path $StartBat)) {
    Write-Host "[FXEStudio] IDE не собрана: $StartBat" -ForegroundColor Red
    exit 1
}

if (-not (Test-Path (Join-Path $Misc 'languages'))) {
    Write-Host "[FXEStudio] Не найден misc\languages" -ForegroundColor Red
    exit 1
}

Write-Host '[FXEStudio] Синхронизация misc -> install ...' -ForegroundColor Cyan
& robocopy $Misc $Install /E /NFL /NDL /NJH /NJS /nc /ns /np | Out-Null

if (-not (Test-Path (Join-Path $Install 'languages\ru\messages.ini'))) {
    Write-Host '[FXEStudio] Ошибка: languages не в install' -ForegroundColor Red
    exit 1
}

$content = Get-Content $StartBat -Raw
if ($content -notmatch 'cd /d "%APP_HOME%"') {
    $content = $content -replace ':init\r?\n', ":init`r`n@rem FXE Studio: cwd = install`r`ncd /d `"%APP_HOME%`"`r`n"
    Set-Content -Path $StartBat -Value $content -Encoding ASCII
}

if ($content -notmatch 'file\.encoding=UTF-8') {
    $content = Get-Content $StartBat -Raw
    $content = $content -replace 'set DEFAULT_JVM_OPTS=', 'set DEFAULT_JVM_OPTS=-Dfile.encoding=UTF-8 '
    Set-Content -Path $StartBat -Value $content -Encoding ASCII
    Write-Host '[FXEStudio] Добавлен -Dfile.encoding=UTF-8 в develnext.bat' -ForegroundColor Yellow
}

$JarSrc = Join-Path $Root 'develnext\build\libs\DevelNext.jar'
$JarDst = Join-Path $Install 'lib\DevelNext.jar'
if (Test-Path $JarSrc) {
    Copy-Item $JarSrc $JarDst -Force
}

$libSrcDirs = @(
    (Join-Path $Root 'develnext-designer\build\libs'),
    (Join-Path $Root 'dn-app-framework\build\libs'),
    (Join-Path $Root 'develnext-launcher\build\libs'),
    (Join-Path $Root 'fxe-gui-ext\build\libs'),
    (Join-Path $Root 'fxe-console\build\libs'),
    (Join-Path $Root 'platforms\develnext-desktop-platform\build\libs\platforms'),
    (Join-Path $Root 'platforms\develnext-php-platform\build\libs\platforms'),
    (Join-Path $Root 'jphp-gui-tabs-ext\build\libs'),
    (Join-Path $Root 'jphp-gui-richtext-ext\build\libs')
)

foreach ($libSrc in $libSrcDirs) {
    if (-not (Test-Path $libSrc)) {
        continue
    }

    Get-ChildItem $libSrc -Filter '*.jar' | ForEach-Object {
        $dst = Join-Path (Join-Path $Install 'lib') $_.Name
        Copy-Item $_.FullName $dst -Force
    }
}

$helperJars = @(
    'fxe-runner.jar',
    'fxe-indexer.jar',
    'fxe-analyzer.jar',
    'fxe-language-server.jar',
    'fxe-builder.jar',
    'fxe-process-core-1.0.jar',
    'fxe-process-host-1.0.jar'
)

$helperDirs = @(
    (Join-Path $Root 'fxe-runner\build\libs'),
    (Join-Path $Root 'fxe-indexer\build\libs'),
    (Join-Path $Root 'fxe-analyzer\build\libs'),
    (Join-Path $Root 'fxe-language-server\build\libs'),
    (Join-Path $Root 'fxe-builder\build\libs'),
    (Join-Path $Root 'fxe-process-core\build\libs'),
    (Join-Path $Root 'fxe-process-host\build\libs')
)

$helpersDst = Join-Path (Join-Path $Install 'lib') 'helpers'
if (-not (Test-Path $helpersDst)) {
    New-Item -ItemType Directory -Path $helpersDst -Force | Out-Null
}

foreach ($helperDir in $helperDirs) {
    if (-not (Test-Path $helperDir)) {
        continue
    }

    Get-ChildItem $helperDir -Filter '*.jar' | ForEach-Object {
        Copy-Item $_.FullName (Join-Path $helpersDst $_.Name) -Force
    }
}

Optimize-DevelnextClasspath -Path $StartBat `
    -Pattern 'set CLASSPATH=%APP_HOME%\\lib\\[^
]+' `
    -Replacement 'set CLASSPATH=%APP_HOME%\lib\DevelNext.jar;%APP_HOME%\lib\*'

Optimize-DevelnextClasspath -Path $StartSh `
    -Pattern 'CLASSPATH=\$APP_HOME/lib/[^
]+' `
    -Replacement 'CLASSPATH=$APP_HOME/lib/DevelNext.jar:$APP_HOME/lib/*'

Write-Host '[FXEStudio] Install готов.' -ForegroundColor Green
exit 0
