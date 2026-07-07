@echo off
setlocal

set "ROOT=%~dp0"
if exist "%ROOT%.fxe-env.bat" (
  call "%ROOT%.fxe-env.bat"
) else (
  echo [FXEStudio] Не найден .fxe-env.bat. Сначала запустите bootstrap-windows.bat
  exit /b 1
)

if not exist "%ROOT%gradlew.bat" (
  echo [FXEStudio] Не найден gradlew.bat
  exit /b 1
)

cd /d "%ROOT%"
call gradlew.bat :develnext:installDist --offline --stacktrace --info
set "EC=%ERRORLEVEL%"
if %EC% neq 0 (
  echo [FXEStudio] Offline-сборка не удалась. Возможно, не хватает vendor\maven-repo или .m2 cache.
  exit /b %EC%
)

echo [FXEStudio] Сборка завершена. Запуск: run-ide.bat
endlocal & exit /b 0
