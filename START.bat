@echo off
rem Единственная точка входа — всегда подтягивает languages/library и запускает IDE
call "%~dp0run-ide.bat" %*
