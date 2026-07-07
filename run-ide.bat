@echo off
setlocal EnableExtensions

set "ROOT=%~dp0"
if exist "%ROOT%.fxe-env.bat" call "%ROOT%.fxe-env.bat"

call "%ROOT%sync-ide-install.bat"
if errorlevel 1 exit /b 1

set "IDE_HOME=%ROOT%develnext\build\install\develnext"
set "IDE_BIN=%IDE_HOME%\bin\develnext.bat"

set "BUNDLED_JRE=%ROOT%develnext-tools\jre"
if exist "%BUNDLED_JRE%\bin\java.exe" (
  set "JAVA_HOME=%BUNDLED_JRE%"
  set "PATH=%JAVA_HOME%\bin;%PATH%"
) else (
  for %%J in (
    "C:\Program Files\BellSoft\LibericaJDK-8*Full*"
    "C:\Program Files\BellSoft\LibericaJDK-8*"
    "C:\Program Files\Eclipse Adoptium\jdk-8*"
  ) do (
    if exist "%%~J\bin\java.exe" (
      set "JAVA_HOME=%%~J"
      set "PATH=%%~J\bin;%PATH%"
      goto :run_ide
    )
  )
)

:run_ide
cd /d "%IDE_HOME%"
call "%IDE_BIN%" %*

endlocal
