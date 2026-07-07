@echo off
setlocal EnableExtensions
chcp 65001 >nul

set "ROOT=%~dp0"
set "ROOT=%ROOT:~0,-1%"

if /I not "%ROOT:~0,12%"=="C:\FXEStudio" (
  if exist "C:\FXEStudio\build-wizard.bat" (
    call "C:\FXEStudio\build-wizard.bat" %*
    exit /b %ERRORLEVEL%
  )
)

for %%J in (
  "C:\Program Files\BellSoft\LibericaJDK-8*Full*"
  "C:\Program Files\BellSoft\LibericaJDK-8*"
  "C:\Program Files\Eclipse Adoptium\jdk-8*"
  "C:\Program Files\Java\jdk1.8.0_*"
) do (
  if exist "%%~J\bin\java.exe" (
    set "JAVA_HOME=%%~J"
    goto :java_ok
  )
)

echo [FXEStudio] JDK 8 не найден. Установите Liberica JDK 8 Full:
echo   winget install -e --id BellSoft.LibericaJDK.8.Full
exit /b 1

:java_ok
set "PATH=%JAVA_HOME%\bin;%PATH%"
cd /d "%ROOT%"

set "WIZARD=3rd-party\wizard-framework"
if not exist "%WIZARD%\gradlew.bat" (
  echo [FXEStudio] Нет gradlew в wizard-framework. Запустите bootstrap-windows.bat
  exit /b 1
)

echo [FXEStudio] Сборка wizard-framework в mavenLocal (без Node.js)...
pushd "%WIZARD%"
call gradlew.bat install --no-daemon ^
  -x nodeSetup -x npmSetup -x gulp_compile-css -x gulp_compile ^
  -x buildCss -x buildJs -x buildWebLib
if errorlevel 1 (
  echo [FXEStudio] Полный install не удался — ставлю только модули для IDE...
  call gradlew.bat :wizard-core:install :wizard-web-ui:install :wizard-app:install :wizard-web:install :wizard-app-web:install :modules:wizard-localization:install :modules:wizard-httpclient:install :modules:wizard-ide-support:install --no-daemon ^
    -x nodeSetup -x npmSetup -x gulp_compile-css -x gulp_compile ^
    -x buildCss -x buildJs -x buildWebLib
)
set "RC=%ERRORLEVEL%"
popd
exit /b %RC%
