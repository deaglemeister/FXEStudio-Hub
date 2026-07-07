@echo off
setlocal
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0bootstrap-windows.ps1" %*
set "EC=%ERRORLEVEL%"
endlocal & exit /b %EC%
