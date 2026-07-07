@echo off
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0sync-ide-install.ps1"
exit /b %ERRORLEVEL%
