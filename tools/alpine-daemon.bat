@echo off
REM ===================================================================
REM  Start the Alpine Club website's ssh daemon to portal.caltech.edu.
REM  Safe to double-click.
REM
REM  Authenticates ONCE - your Caltech password, then Duo on your phone -
REM  and then holds that session open so every server command after it
REM  is free. Publishing the site, or setting it up on the server, is a
REM  run of small commands; without this each one costs its own Duo push.
REM
REM  LEAVE THIS WINDOW OPEN. Closing it closes the session, which is the
REM  intended way to end the grant. It also closes itself after four
REM  hours idle.
REM
REM  Your password is never stored, logged, or written to disk. Because
REM  this window keeps stdin, you can actually type it - which is the
REM  whole reason this file exists rather than a background launch.
REM
REM  Canonical copy: <repo>\tools\alpine-daemon.bat. The one on the
REM  Desktop is a copy; edit the repo one and copy it out again.
REM
REM  Pass arguments through, e.g.:  "Alpine Daemon.bat" --status
REM ===================================================================

setlocal
title Alpine site daemon - portal.caltech.edu

REM  The REAL python, not the WindowsApps alias on PATH - the alias is a
REM  known trap for launches that are not run from a developer shell.
set "REPO=C:\Users\kyleh\Documents\2Projects\alpine-club\website"
set "PY=C:\Users\kyleh\AppData\Local\Python\pythoncore-3.14-64\python.exe"

if not exist "%PY%" (
    echo [ERROR] Python not found at:
    echo   %PY%
    echo Edit this .bat and point PY at your python.exe.
    goto :end
)
if not exist "%REPO%\tools\portal_daemon.py" (
    echo [ERROR] Website repo not found at:
    echo   %REPO%
    echo Edit this .bat and point REPO at the website folder.
    goto :end
)

cd /d "%REPO%"
"%PY%" "tools\portal_daemon.py" %*
set RC=%ERRORLEVEL%

echo.
if "%RC%"=="0" (
    echo [ok] Session closed cleanly.
) else (
    echo [!!] The daemon is NOT running. See the messages above.
)

:end
echo.
pause
endlocal
