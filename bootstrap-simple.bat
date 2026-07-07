@echo off
chcp 65001 >nul
setlocal EnableExtensions

set "ROOT=%~dp0"
set "ROOT=%ROOT:~0,-1%"

rem ASCII-путь для Java 8 / Gradle (кириллица в пути ломает сборку)
if /I not "%ROOT:~0,12%"=="C:\FXEStudio" (
  if exist "C:\FXEStudio\gradlew.bat" (
    echo [FXEStudio] Запуск через C:\FXEStudio ...
    call "C:\FXEStudio\bootstrap-simple.bat" %*
    exit /b %ERRORLEVEL%
  )
)

for %%J in (
  "C:\Program Files\BellSoft\LibericaJDK-8*Full*"
  "C:\Program Files\BellSoft\LibericaJDK-8*"
  "C:\Program Files\Eclipse Adoptium\jdk-8.0.482.8-hotspot"
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
echo [FXEStudio] JAVA_HOME=%JAVA_HOME%
java -version 2>&1 | findstr /C:"1.8" >nul
if errorlevel 1 (
  echo [FXEStudio] Нужна Java 1.8.x, не 17/21.
  exit /b 1
)

cd /d "%ROOT%"
if not exist "gradle\wrapper\gradle-wrapper.jar" (
  echo [FXEStudio] Нет gradle-wrapper.jar — запустите bootstrap-windows.ps1
  exit /b 1
)

if not exist "%USERPROFILE%\.m2\repository\org\develnext\jphp\jphp-gui-ext\0.9.3-SNAPSHOT" (
  echo [FXEStudio] Собираю JPHP 0.9.3 с GUI-модулями...
  if exist "3rd-party\jphp\gradlew.bat" (
    pushd "3rd-party\jphp"
    git fetch --tags --depth 50 origin 2>nul
    git checkout 8af3a12e 2>nul
    call gradlew.bat install --no-daemon
    popd
  ) else (
    echo [FXEStudio] Нет 3rd-party\jphp. Сначала запустите bootstrap-windows.bat
    exit /b 1
  )
)

if not exist "%USERPROFILE%\.m2\repository\org\develnext\framework\wizard-core\1.0.0-SNAPSHOT" (
  echo [FXEStudio] Собираю wizard-framework...
  call "%ROOT%\build-wizard.bat"
  if errorlevel 1 exit /b 1
)

echo [FXEStudio] Сборка IDE...
call gradlew.bat :develnext:installDist --no-daemon --stacktrace
if errorlevel 1 exit /b 1

echo [FXEStudio] Готово. Запуск: run-ide.bat
endlocal & exit /b 0
