#Requires -Version 5.1
<#
.SYNOPSIS
    FXEStudio / DevelNext legacy bootstrap for Windows.
.DESCRIPTION
    One-click setup: JDK 8, local Maven deps (JPHP/Wizard), Gradle 2.10 build, IDE launch.
#>

param(
    [switch]$SkipBuild,
    [switch]$SkipClone,
    [switch]$LaunchIde
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$Script:Root = $PSScriptRoot
$Script:MavenLocal = Join-Path $env:USERPROFILE '.m2\repository'
$Script:VendorMaven = Join-Path $Root 'vendor\maven-repo'
$Script:EnvBat = Join-Path $Root '.fxe-env.bat'
$Script:JavaHomeFile = Join-Path $Root '.fxe-java-home'

$Script:JphpMavenMarker = Join-Path $MavenLocal 'org\develnext\jphp\jphp-core\0.9.3-SNAPSHOT'
$Script:WizardMavenMarker = Join-Path $MavenLocal 'org\develnext\framework\wizard-core\1.0.0-SNAPSHOT'

function Initialize-ToolPath {
    $extra = @(
        'C:\Program Files\Git\cmd',
        'C:\Program Files\Git\bin',
        'C:\Windows\System32'
    )
    foreach ($p in $extra) {
        if ((Test-Path $p) -and ($env:Path -notlike "*$p*")) {
            $env:Path = "$p;$env:Path"
        }
    }
}

function Write-Fxe {
    param([string]$Message, [string]$Level = 'INFO')
    $prefix = '[FXEStudio]'
    switch ($Level) {
        'ERROR' { Write-Host "$prefix $Message" -ForegroundColor Red }
        'WARN'  { Write-Host "$prefix $Message" -ForegroundColor Yellow }
        'OK'    { Write-Host "$prefix $Message" -ForegroundColor Green }
        default { Write-Host "$prefix $Message" }
    }
}

function Exit-Fxe {
    param([string]$Message, [int]$Code = 1)
    Write-Fxe $Message 'ERROR'
    exit $Code
}

function Test-DirHasSources {
    param([string]$Path)
    if (-not (Test-Path $Path)) { return $false }
    if (Test-Path (Join-Path $Path 'build.gradle')) { return $true }
    if (Test-Path (Join-Path $Path 'gradlew.bat')) { return $true }
    if (Test-Path (Join-Path $Path 'settings.gradle')) { return $true }
    if (Test-Path (Join-Path $Path 'pom.xml')) { return $true }
    $meaningful = Get-ChildItem -Path $Path -File -Recurse -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -notin @('.gitkeep', '.gitignore', 'README.md') } |
        Select-Object -First 1
    return $null -ne $meaningful
}

function Get-JavaVersionOutput {
    $java = if ($env:JAVA_HOME) { Join-Path $env:JAVA_HOME 'bin\java.exe' } else { 'java' }
    if ($java -ne 'java' -and -not (Test-Path $java)) {
        $java = 'java'
    }
    $prev = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        $lines = @(& $java -version 2>&1 | ForEach-Object { "$_" })
        return $lines
    }
    finally {
        $ErrorActionPreference = $prev
    }
}

function Test-IsJava8 {
    param([string[]]$VersionLines)
    foreach ($line in $VersionLines) {
        if ($line -match 'version\s+"1\.8\.' -or $line -match 'version\s+"1\.8"') {
            return $true
        }
        if ($line -match 'version\s+"(1[7-9]|[2-9][0-9])\.' -or $line -match 'version\s+"(2[1-9]|[3-9][0-9])\.') {
            return $false
        }
    }
    return $false
}

function Find-Java8Home {
    $candidates = @()

    if ($env:FXE_JAVA_HOME -and (Test-Path (Join-Path $env:FXE_JAVA_HOME 'bin\java.exe'))) {
        $candidates += $env:FXE_JAVA_HOME
    }

    if (Test-Path $JavaHomeFile) {
        $saved = (Get-Content $JavaHomeFile -Raw).Trim()
        if ($saved) { $candidates += $saved }
    }

    $searchRoots = @(
        'C:\Program Files\BellSoft',
        'C:\Program Files\Eclipse Adoptium',
        'C:\Program Files\Adoptium',
        'C:\Program Files\Java',
        'C:\Program Files (x86)\Java'
    )

    foreach ($root in $searchRoots) {
        if (-not (Test-Path $root)) { continue }
        Get-ChildItem -Path $root -Directory -ErrorAction SilentlyContinue | ForEach-Object {
            $name = $_.Name
            if ($name -match 'jdk-?8|1\.8|8\.0|temurin-8|jdk8|LibericaJDK-8|liberica.*8' -and (Test-Path (Join-Path $_.FullName 'bin\java.exe'))) {
                if ($name -match 'Full|full|fx|FX') {
                    $candidates = @($_.FullName) + $candidates
                }
                else {
                    $candidates += $_.FullName
                }
            }
        }
    }

    foreach ($javaHomeCandidate in ($candidates | Select-Object -Unique)) {
        $env:JAVA_HOME = $javaHomeCandidate
        $env:Path = "$javaHomeCandidate\bin;" + ($env:Path -split ';' | Where-Object { $_ -and ($_ -notmatch [regex]::Escape($javaHomeCandidate)) } | Select-Object -Unique) -join ';'
        $ver = Get-JavaVersionOutput
        if (Test-IsJava8 $ver) {
            return $javaHomeCandidate
        }
    }

    return $null
}

function Install-Java8 {
    Write-Fxe 'JDK 8 не найден. Устанавливаю Eclipse Temurin 8 через WinGet...'
    if (-not (Get-Command winget -ErrorAction SilentlyContinue)) {
        Exit-Fxe 'WinGet не найден. Установите JDK 8 вручную: https://adoptium.net/temurin/releases/?version=8'
    }

    & winget install -e --id EclipseAdoptium.Temurin.8.JDK --accept-package-agreements --accept-source-agreements
    if ($LASTEXITCODE -ne 0) {
        Exit-Fxe "WinGet завершился с кодом $LASTEXITCODE. Установите JDK 8 вручную."
    }

    Start-Sleep -Seconds 3
    $found = Find-Java8Home
    if (-not $found) {
        Exit-Fxe 'JDK 8 установлен, но не найден в стандартных путях. Перезапустите терминал и bootstrap.'
    }
    return $found
}

function Set-Java8Environment {
    param([string]$JavaHome)

    $env:JAVA_HOME = $JavaHome
    $env:Path = "$JavaHome\bin;" + ($env:Path -split ';' | Where-Object { $_ } | Select-Object -Unique) -join ';'

    Set-Content -Path $JavaHomeFile -Value $JavaHome -Encoding ASCII
    @(
        '@echo off',
        "set `"JAVA_HOME=$JavaHome`"",
        "set `"PATH=%JAVA_HOME%\bin;%PATH%`""
    ) | Set-Content -Path $EnvBat -Encoding ASCII

    $ver = Get-JavaVersionOutput
    Write-Fxe "JAVA_HOME=$JavaHome"
    $ver | ForEach-Object { Write-Fxe $_ }

    if (-not (Test-IsJava8 $ver)) {
        Exit-Fxe 'Нельзя запускать этот Gradle на Java 17/21. Нужен JDK 1.8.x (лучше Liberica JDK 8 Full с JavaFX).'
    }
}

function Get-CmdExe {
    if (Test-Path "$env:ComSpec") { return $env:ComSpec }
    if (Test-Path 'C:\Windows\System32\cmd.exe') { return 'C:\Windows\System32\cmd.exe' }
    return 'cmd.exe'
}

function Invoke-Gradle {
    param(
        [string]$WorkingDir = $Script:Root,
        [string[]]$GradleArgs,
        [switch]$Quiet
    )

    $gradlew = Join-Path $WorkingDir 'gradlew.bat'
    if (-not (Test-Path $gradlew)) {
        $gradlew = Join-Path $Script:Root 'gradlew.bat'
    }

    $cmd = if ($env:ComSpec) { $env:ComSpec } else { 'C:\Windows\System32\cmd.exe' }
    $argLine = ($GradleArgs | ForEach-Object {
        if ($_ -match '\s') { "`"$_`"" } else { $_ }
    }) -join ' '

    Push-Location $WorkingDir
    try {
        if ($Quiet) {
            $null = & $cmd /c "`"$gradlew`" $argLine" 2>&1
        }
        else {
            & $cmd /c "`"$gradlew`" $argLine"
        }
        return [int]$LASTEXITCODE
    }
    finally {
        Pop-Location
    }
}

function Test-PathIsAscii {
    param([string]$Path)
    return ($Path.ToCharArray() | Where-Object { [int]$_ -gt 127 } | Measure-Object).Count -eq 0
}

function Ensure-AsciiProjectRoot {
    if (Test-PathIsAscii $Root) {
        return $Root
    }

    $junction = 'C:\FXEStudio'
    Write-Fxe "Путь содержит кириллицу/не-ASCII: $Root" 'WARN'
    Write-Fxe 'Java 8 / Gradle 2.10 не работают с такими путями. Создаю junction C:\FXEStudio ...' 'WARN'

    if (Test-Path $junction) {
        $item = Get-Item $junction -Force
        if ($item.Target -and ($item.Target -eq $Root -or $item.Target -contains $Root)) {
            return $junction
        }
        Remove-Item $junction -Force -Recurse -ErrorAction SilentlyContinue
    }

    New-Item -ItemType Junction -Path $junction -Target $Root | Out-Null
    Write-Fxe "Используйте для сборки: $junction" 'OK'
    return $junction
}

function Ensure-GradleWrapperJar {
    $jar = Join-Path $Root 'gradle\wrapper\gradle-wrapper.jar'
    $valid = $false
    if (Test-Path $jar) {
        try {
            $jh = Join-Path $env:JAVA_HOME 'bin\jar.exe'
            if (Test-Path $jh) {
                $listing = & $jh tf $jar 2>&1
                $valid = ($LASTEXITCODE -eq 0) -and ($listing -match 'GradleWrapperMain')
            }
            else {
                $valid = (Get-Item $jar).Length -gt 50000
            }
        }
        catch {
            $valid = $false
        }
    }
    if ($valid) { return }

    if (Test-Path $jar) {
        Write-Fxe 'gradle-wrapper.jar повреждён — перекачиваю...' 'WARN'
        Remove-Item $jar -Force
    }
    else {
        Write-Fxe 'Не найден gradle\wrapper\gradle-wrapper.jar — скачиваю...' 'WARN'
    }
    New-Item -ItemType Directory -Path (Split-Path $jar) -Force | Out-Null
    $urls = @(
        'https://github.com/gradle/gradle/raw/v2.10.0/gradle/wrapper/gradle-wrapper.jar',
        'https://raw.githubusercontent.com/gradle/gradle/v2.10.0/gradle/wrapper/gradle-wrapper.jar'
    )
    foreach ($url in $urls) {
        try {
            Invoke-WebRequest -Uri $url -OutFile $jar -UseBasicParsing
            if ((Get-Item $jar).Length -gt 1000) {
                Write-Fxe 'gradle-wrapper.jar восстановлен.' 'OK'
                return
            }
        }
        catch {
            Write-Fxe "Не удалось скачать $url" 'WARN'
        }
    }
    Exit-Fxe 'Не найден gradle-wrapper.jar. Склонируйте полный репозиторий с GitHub.'
}

function Test-GradleWrapper {
    if (-not (Test-Path (Join-Path $Root 'gradlew.bat'))) {
        Exit-Fxe 'Не найден gradlew.bat в корне проекта.'
    }

    Write-Fxe 'Проверяю Gradle Wrapper...'
    $code = Invoke-Gradle -GradleArgs @('--version') -Quiet
    if ($code -ne 0) {
        Exit-Fxe "gradlew.bat --version завершился с кодом $code. Убедитесь, что JAVA_HOME указывает на JDK 8."
    }
    Write-Fxe 'Gradle Wrapper OK.' 'OK'
}

function Copy-MavenTree {
    param(
        [string]$Source,
        [string]$Destination
    )
    if (-not (Test-Path $Source)) { return }
    New-Item -ItemType Directory -Path $Destination -Force | Out-Null
    & robocopy $Source $Destination /E /NFL /NDL /NJH /NJS /nc /ns /np | Out-Null
}

function Seed-MavenFromVendor {
    $vendorOrg = Join-Path $VendorMaven 'org\develnext'
    if (-not (Test-Path $vendorOrg)) {
        Write-Fxe 'vendor\maven-repo\org\develnext не найден — пропускаю seed (будет сборка из исходников).' 'WARN'
        return
    }

    Write-Fxe 'Копирую готовые артефакты из vendor\maven-repo в %USERPROFILE%\.m2\repository ...'
    Copy-MavenTree (Join-Path $VendorMaven 'org') (Join-Path $MavenLocal 'org')
    Write-Fxe 'Maven cache обновлён из vendor.' 'OK'
}

function Save-MavenToVendor {
    $destOrg = Join-Path $VendorMaven 'org\develnext'
    $srcJphp = Join-Path $MavenLocal 'org\develnext\jphp'
    $srcFramework = Join-Path $MavenLocal 'org\develnext\framework'

    if (-not (Test-Path $srcJphp)) { return }

    Write-Fxe 'Сохраняю собранные артефакты в vendor\maven-repo для offline-запуска...'
    New-Item -ItemType Directory -Path $destOrg -Force | Out-Null
    if (Test-Path $srcJphp) {
        Copy-MavenTree $srcJphp (Join-Path $destOrg 'jphp')
    }
    if (Test-Path $srcFramework) {
        Copy-MavenTree $srcFramework (Join-Path $destOrg 'framework')
    }
    Write-Fxe 'vendor\maven-repo обновлён.' 'OK'
}

function Test-MavenJphpPresent {
    return (Test-Path (Join-Path $JphpMavenMarker 'jphp-core-0.9.3-SNAPSHOT.jar')) -or
           (Test-Path (Join-Path $JphpMavenMarker '_remote.repositories')) -or
           (Test-Path (Join-Path $JphpMavenMarker 'maven-metadata-local.xml'))
}

function Test-MavenWizardPresent {
    $wizardCore = Join-Path $MavenLocal 'org\develnext\framework\wizard-core\1.0.0-SNAPSHOT'
    return (Test-Path $wizardCore)
}

function Get-GitExe {
    $cmd = Get-Command git -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }

    foreach ($candidate in @(
        'C:\Program Files\Git\cmd\git.exe',
        'C:\Program Files\Git\bin\git.exe',
        'C:\Program Files (x86)\Git\cmd\git.exe'
    )) {
        if (Test-Path $candidate) { return $candidate }
    }
    return $null
}

function Invoke-Git {
    param([string[]]$GitArguments)
    $git = Get-GitExe
    if (-not $git) {
        return $false
    }
    & $git @GitArguments
    return ($LASTEXITCODE -eq 0)
}

function Ensure-ThirdPartyDir {
    param(
        [string]$RelativePath,
        [string]$VendorPath,
        [string]$CloneUrl,
        [string[]]$FallbackTags = @()
    )

    $target = Join-Path $Root $RelativePath
    if (Test-DirHasSources $target) {
        Write-Fxe "OK: $RelativePath содержит исходники."
        return $target
    }

    if (Test-DirHasSources $VendorPath) {
        Write-Fxe "Копирую $VendorPath -> $RelativePath ..."
        if (Test-Path $target) { Remove-Item $target -Recurse -Force -ErrorAction SilentlyContinue }
        New-Item -ItemType Directory -Path (Split-Path $target) -Force | Out-Null
        & robocopy $VendorPath $target /E /NFL /NDL /NJH /NJS /nc /ns /np | Out-Null
        if (Test-DirHasSources $target) { return $target }
    }

    if ($SkipClone) {
        Exit-Fxe "Папка $RelativePath пустая. Запустите полный clone: git clone --recurse-submodules https://github.com/jphp-group/develnext.git"
    }

    if (-not (Get-GitExe)) {
        Exit-Fxe "Папка $RelativePath пустая и git не установлен. Сначала нужен полный git clone с submodules."
    }

    Write-Fxe "Клонирую $CloneUrl в $VendorPath ..."
    if (Test-Path $VendorPath) { Remove-Item $VendorPath -Recurse -Force -ErrorAction SilentlyContinue }
    New-Item -ItemType Directory -Path (Split-Path $VendorPath) -Force | Out-Null

    $cloned = $false
    foreach ($tag in $FallbackTags) {
        if (Invoke-Git -GitArguments @('clone', '--depth', '1', '--branch', $tag, $CloneUrl, $VendorPath)) {
            $cloned = $true
            break
        }
        if (Test-Path $VendorPath) { Remove-Item $VendorPath -Recurse -Force -ErrorAction SilentlyContinue }
    }

    if (-not $cloned) {
        if (-not (Invoke-Git -GitArguments @('clone', '--depth', '1', $CloneUrl, $VendorPath))) {
            Exit-Fxe "Не удалось клонировать $CloneUrl"
        }
    }

    if (-not (Test-DirHasSources $VendorPath)) {
        Exit-Fxe "После клонирования $VendorPath всё ещё пуст."
    }

    if (Test-Path $target) { Remove-Item $target -Recurse -Force -ErrorAction SilentlyContinue }
    & robocopy $VendorPath $target /E /NFL /NDL /NJH /NJS /nc /ns /np | Out-Null
    return $target
}

function Invoke-GradleInstall {
    param(
        [string]$WorkingDir,
        [string[]]$ExtraTasks = @('install')
    )

    Write-Fxe "Сборка: $WorkingDir -> $($ExtraTasks -join ' ')"
    $code = Invoke-Gradle -WorkingDir $WorkingDir -GradleArgs (@('--no-daemon') + $ExtraTasks + @('--stacktrace', '--info'))
    if ($code -ne 0) {
        Exit-Fxe "Gradle install failed in $WorkingDir (exit $code)"
    }
    Write-Fxe "OK: $($ExtraTasks -join ' ') в $WorkingDir" 'OK'
}

function Install-JphpParserExt {
    $jphpDir = Join-Path $Root '3rd-party\jphp'
    $parserDir = Join-Path $jphpDir 'exts\jphp-parser-ext'
    $parserJar = Join-Path $MavenLocal 'org\develnext\jphp\jphp-parser-ext\0.9.3-SNAPSHOT\jphp-parser-ext-0.9.3-SNAPSHOT.jar'
    $vendorParser = Join-Path $Root 'vendor\jphp\exts\jphp-parser-ext'

    if (-not (Test-Path (Join-Path $parserDir 'build.gradle'))) {
        if (-not (Test-Path (Join-Path $vendorParser 'build.gradle'))) {
            Write-Fxe 'Не найден jphp-parser-ext в vendor — пропускаю.' 'WARN'
            return
        }
        Write-Fxe 'Восстанавливаю исходники jphp-parser-ext из vendor...'
        if (Test-Path $parserDir) { Remove-Item $parserDir -Recurse -Force }
        & robocopy $vendorParser $parserDir /E /NFL /NDL /NJH /NJS /nc /ns /np | Out-Null
    }

    $needsBuild = $true
    if (Test-Path $parserJar) {
        if ((Get-Item $parserJar).Length -gt 10000) { $needsBuild = $false }
    }

    if ($needsBuild) {
        Write-Fxe 'Собираю jphp-parser-ext (нужен для редактора форм)...'
        $code = Invoke-Gradle -WorkingDir $jphpDir -GradleArgs @(
            '--no-daemon', 'install', '-p', 'exts\jphp-parser-ext', '--stacktrace'
        )
        if ($code -ne 0) {
            Exit-Fxe "Сборка jphp-parser-ext завершилась с кодом $code"
        }
    }
    else {
        Write-Fxe 'jphp-parser-ext уже в Maven cache.' 'OK'
    }
}

    param([string]$WizardDir)

    $wizardCommit = 'f90199e'
    $skipNodeTasks = @(
        '-x', 'nodeSetup', '-x', 'npmSetup',
        '-x', 'gulp_compile-css', '-x', 'gulp_compile',
        '-x', 'buildCss', '-x', 'buildJs', '-x', 'buildWebLib'
    )

    if (Test-Path (Join-Path $WizardDir '.git')) {
        Push-Location $WizardDir
        try {
            $remote = (& git remote get-url origin 2>$null)
            if ($remote -notmatch 'jphp-group/wizard-framework') {
                Write-Fxe 'Переключаю remote wizard-framework на jphp-group...' 'WARN'
                Invoke-Git -GitArguments @('remote', 'set-url', 'origin', 'https://github.com/jphp-group/wizard-framework.git') | Out-Null
            }
            Invoke-Git -GitArguments @('fetch', '--tags', '--depth', '50', 'origin') | Out-Null
            Invoke-Git -GitArguments @('checkout', $wizardCommit) | Out-Null
        }
        finally {
            Pop-Location
        }
    }

    if (-not (Test-Path (Join-Path $WizardDir 'gradlew.bat'))) {
        Exit-Fxe "В $WizardDir нет gradlew.bat (нужен коммит $wizardCommit с Gradle-сборкой)."
    }

    Write-Fxe "Сборка wizard-framework ($wizardCommit) без Node.js (ресурсы уже в репозитории)..."
    $code = Invoke-Gradle -WorkingDir $WizardDir -GradleArgs (@(
        '--no-daemon', 'install'
    ) + $skipNodeTasks + @('--stacktrace'))
    if ($code -ne 0) {
        Write-Fxe 'Полный install не удался — ставлю только модули, нужные DevelNext...' 'WARN'
        $code = Invoke-Gradle -WorkingDir $WizardDir -GradleArgs (@(
            '--no-daemon',
            ':wizard-core:install',
            ':wizard-web-ui:install',
            ':wizard-app:install',
            ':wizard-web:install',
            ':wizard-app-web:install',
            ':modules:wizard-localization:install',
            ':modules:wizard-httpclient:install',
            ':modules:wizard-ide-support:install'
        ) + $skipNodeTasks + @('--stacktrace'))
        if ($code -ne 0) {
            Exit-Fxe "Сборка wizard-framework завершилась с кодом $code"
        }
    }
    Write-Fxe 'Wizard Framework установлен в mavenLocal.' 'OK'
}

function Build-ThirdPartyDependencies {
    if (-not (Test-MavenJphpPresent)) {
        Write-Fxe 'Не найден JPHP 0.9.3-SNAPSHOT в Maven cache. Собираю JPHP...'
        $jphpDir = Ensure-ThirdPartyDir `
            -RelativePath '3rd-party\jphp' `
            -VendorPath (Join-Path $Root 'vendor\jphp') `
            -CloneUrl 'https://github.com/jphp-compiler/jphp.git' `
            -FallbackTags @('0.9.2', 'master')

        if (Test-Path (Join-Path $jphpDir '.git')) {
            Write-Fxe 'Переключаю JPHP на коммит 0.9.3 с GUI-модулями (8af3a12e)...'
            Push-Location $jphpDir
            try {
                Invoke-Git -GitArguments @('fetch', '--tags', '--unshallow') | Out-Null
                Invoke-Git -GitArguments @('checkout', '8af3a12e') | Out-Null
            }
            finally {
                Pop-Location
            }
        }

        Invoke-GradleInstall -WorkingDir $jphpDir
        if (-not (Test-MavenJphpPresent)) {
            Exit-Fxe 'JPHP собран, но org.develnext.jphp:jphp-core:0.9.3-SNAPSHOT не появился в mavenLocal.'
        }
        Install-JphpParserExt
    }
    else {
        Write-Fxe 'JPHP 0.9.3-SNAPSHOT уже в Maven cache.' 'OK'
    }

  $guiMarker = Join-Path $MavenLocal 'org\develnext\jphp\jphp-gui-ext\0.9.3-SNAPSHOT'
  if (-not (Test-Path $guiMarker)) {
      Write-Fxe 'jphp-gui-ext не найден в Maven — пересобираю JPHP 0.9.3 (коммит 8af3a12e)...' 'WARN'
      $jphpDir = Join-Path $Root '3rd-party\jphp'
      if (Test-Path (Join-Path $jphpDir '.git')) {
          Push-Location $jphpDir
          try {
              Invoke-Git -GitArguments @('fetch', '--tags', '--depth', '50', 'origin') | Out-Null
              Invoke-Git -GitArguments @('checkout', '8af3a12e') | Out-Null
              Invoke-GradleInstall -WorkingDir $jphpDir
              Install-JphpParserExt
          }
          finally {
              Pop-Location
          }
      }
  }
  else {
      Write-Fxe 'jphp-gui-ext уже в Maven cache.' 'OK'
      Install-JphpParserExt
  }

    if (-not (Test-MavenWizardPresent)) {
        Write-Fxe 'Не найден Wizard Framework в Maven cache. Собираю wizard-framework...'
        $wizardDir = Ensure-ThirdPartyDir `
            -RelativePath '3rd-party\wizard-framework' `
            -VendorPath (Join-Path $Root 'vendor\wizard-framework') `
            -CloneUrl 'https://github.com/jphp-group/wizard-framework.git' `
            -FallbackTags @('master')

        Install-WizardFramework -WizardDir $wizardDir
        if (-not (Test-MavenWizardPresent)) {
            Write-Fxe 'Wizard Framework не найден в mavenLocal — возможно, не все платформы соберутся.' 'WARN'
        }
    }
    else {
        Write-Fxe 'Wizard Framework уже в Maven cache.' 'OK'
    }

    $richtextDir = Join-Path $Root '3rd-party\RichTextFX'
    if (-not (Test-DirHasSources $richtextDir)) {
        Ensure-ThirdPartyDir `
            -RelativePath '3rd-party\RichTextFX' `
            -VendorPath (Join-Path $Root 'vendor\RichTextFX') `
            -CloneUrl 'https://github.com/TomasMikula/RichTextFX.git' `
            -FallbackTags @('0.8.2', 'master') | Out-Null
    }

    if (Test-DirHasSources $richtextDir) {
        Write-Fxe 'Сборка RichTextFX (install)...'
        try {
            Invoke-GradleInstall -WorkingDir $richtextDir
        }
        catch {
            Write-Fxe 'RichTextFX install не удался — designer может использовать richtextfx:0.8.2 из Maven Central.' 'WARN'
        }
    }

    Save-MavenToVendor
}

function Copy-DevelnextInstallAssets {
    $installDir = Join-Path $Root 'develnext\build\install\develnext'
    $miscDir = Join-Path $Root 'develnext\misc'

    if (-not (Test-Path $miscDir)) {
        Write-Fxe 'Не найден develnext\misc — пропускаю копирование ресурсов IDE.' 'WARN'
        return
    }

    Write-Fxe 'Копирую develnext\misc в install (languages, library, ...)...'
    & robocopy $miscDir $installDir /E /NFL /NDL /NJH /NJS /nc /ns /np | Out-Null

    $startBat = Join-Path $installDir 'bin\develnext.bat'
    if (Test-Path $startBat) {
        $content = Get-Content $startBat -Raw
        if ($content -notmatch 'cd /d "%APP_HOME%"') {
            $content = $content -replace ':init\r?\n', ":init`r`n@rem IDE ищет ./languages относительно текущей папки`r`ncd /d `"%APP_HOME%`"`r`n"
            Set-Content -Path $startBat -Value $content -Encoding ASCII
        }
    }

    Write-Fxe 'Ресурсы IDE скопированы в install.' 'OK'
}

function Build-DevelnextIde {
    Write-Fxe 'Сборка IDE: gradlew.bat :develnext:installDist ...'
    $code = Invoke-Gradle -GradleArgs @(':develnext:installDist', '--stacktrace', '--info')
    if ($code -ne 0) {
        Exit-Fxe "Сборка :develnext:installDist завершилась с кодом $code"
    }

    Copy-DevelnextInstallAssets

    $binBat = Join-Path $Root 'develnext\build\install\develnext\bin\develnext.bat'
    if (-not (Test-Path $binBat)) {
        Exit-Fxe "Сборка завершилась, но не найден $binBat"
    }
    Write-Fxe 'IDE собрана успешно.' 'OK'
}

function Test-IdeBuilt {
    Test-Path (Join-Path $Root 'develnext\build\install\develnext\bin\develnext.bat')
}

function Write-RunIdeBat {
    $runBat = Join-Path $Root 'run-ide.bat'
    @(
        '@echo off',
        'setlocal',
        '',
        'set "ROOT=%~dp0"',
        'if exist "%ROOT%.fxe-env.bat" call "%ROOT%.fxe-env.bat"',
        '',
        'set "IDE_BIN=%ROOT%develnext\build\install\develnext\bin\develnext.bat"',
        'if not exist "%IDE_BIN%" (',
        '  echo [FXEStudio] IDE не собрана. Запустите bootstrap-windows.bat',
        '  exit /b 1',
        ')',
        '',
        'set "BUNDLED_JRE=%ROOT%develnext-tools\jre"',
        'if exist "%BUNDLED_JRE%\bin\java.exe" (',
        '  set "JAVA_HOME=%BUNDLED_JRE%"',
        '  set "PATH=%JAVA_HOME%\bin;%PATH%"',
        ')',
        '',
        'call "%IDE_BIN%" %*',
        '',
        'endlocal'
    ) | Set-Content -Path $runBat -Encoding ASCII
    Write-Fxe "Создан run-ide.bat" 'OK'
}

# --- main ---

Write-Fxe '=== FXEStudio Bootstrap (Windows) ==='

Initialize-ToolPath

$Script:Root = Ensure-AsciiProjectRoot
Set-Location $Script:Root

$java8 = Find-Java8Home
if (-not $java8) {
    $java8 = Install-Java8
}
Set-Java8Environment -JavaHome $java8

Ensure-GradleWrapperJar
Test-GradleWrapper

if (Test-Path (Join-Path $Root '.git')) {
    Write-Fxe 'Обновляю git submodules...'
    Push-Location $Root
    try {
        Invoke-Git submodule update --init --recursive | Out-Null
    }
    finally {
        Pop-Location
    }
}

Seed-MavenFromVendor
Build-ThirdPartyDependencies

if (-not $SkipBuild) {
    if (-not (Test-IdeBuilt)) {
        Build-DevelnextIde
    }
    else {
        Write-Fxe 'IDE уже собрана (develnext\build\install\develnext\bin\develnext.bat).' 'OK'
    }
}

Write-RunIdeBat

Write-Fxe '=== Bootstrap завершён ===' 'OK'
Write-Fxe 'Запуск IDE: run-ide.bat'
Write-Fxe 'Offline-сборка: build-offline.bat'

if ($LaunchIde -or $env:FXE_LAUNCH_IDE -eq '1') {
    & (Join-Path $Root 'run-ide.bat')
}

exit 0
