@echo off
REM ===================================================================
REM  Publish the Alpine Club website.  Safe to double-click.
REM
REM  Logs in to portal.caltech.edu with the SSH key already sitting in
REM  %USERPROFILE%\.ssh, runs the one publish command on the server, and
REM  prints back everything the server said.  No password and no Duo
REM  push: Duo gates password logins, not public keys.
REM
REM  IT PUBLISHES WHAT IS ON GITHUB, not what is in this folder.  So it
REM  checks first that your work is committed and pushed, and stops and
REM  says so rather than quietly republishing yesterday's code.
REM
REM  Needs the Caltech VPN on "Tunnel All".  Split tunnelling cannot
REM  reach portal at all and the failure looks like the server being
REM  down, so this names it for you instead of hanging.
REM
REM  Arguments pass straight through to the server:
REM      publish.bat --rollback    put the previous copy of the site back
REM      publish.bat --force       publish past a red or unfinished check
REM
REM  Canonical copy: <repo>\tools\publish.bat.  A copy on the Desktop is
REM  a copy - edit this one and copy it out again.
REM
REM  NO SSH KEY?  You do not need this file at all.  The same publish
REM  command typed into PuTTY is SECRETARY.md step 3, and that route is
REM  the one the club depends on.  This is a shortcut, not the
REM  procedure.  Making a key: docs\ACCESS.md.
REM ===================================================================

setlocal
title Publish the Alpine Club site

set "PLINK=C:\Program Files\PuTTY\plink.exe"
set "HOST=portal.caltech.edu"
set "DEPLOY=/srv/www.alpine.caltech.edu/www/bin/deploy"
set "LOG=%TEMP%\alpine-publish.txt"

REM  The server's host key, pinned.  Two reasons it is written down here
REM  rather than trusted on first use: a first run then cannot be asked
REM  to accept an unknown key, and a key that CHANGES stops the publish
REM  instead of being waved through.  Read off the server 2026-09-02
REM  with `plink -v`; if it ever fails, re-read it the same way before
REM  believing the new one.
set "HOSTKEY=SHA256:0kAZ/Q/s9uL1wfDko5ACpKe09zLVCsN62TmdGjEByTY"

REM ---- the website folder, for the committed-and-pushed check only ----
set "REPO=%~dp0.."
if not exist "%REPO%\index.php" set "REPO=C:\Users\kyleh\Documents\2Projects\alpine-club\website"
if not exist "%REPO%\index.php" set "REPO="

REM ---- the key ----
set "KEY="
for %%K in ("%USERPROFILE%\.ssh\caltech-website-portal.ppk" "%USERPROFILE%\.ssh\caltech-portal.ppk") do if exist %%K set "KEY=%%~K"

REM ---- your Caltech username, the same file tools\deploy.sh remembers ----
set "SSHUSER="
if defined REPO if exist "%REPO%\.deploy-user" set /p SSHUSER=<"%REPO%\.deploy-user"
if not defined SSHUSER set "SSHUSER=%USERNAME%"

REM ---- preconditions that stop everything ----
if not exist "%PLINK%" goto :noplink
if not defined KEY goto :nokey

REM ---- is your work actually on GitHub? ----
set "DIRTY="
set "LOCAL="
set "REMOTE="
if not defined REPO goto :norepo
where git >nul 2>&1
if errorlevel 1 goto :nogit
pushd "%REPO%"
for /f %%i in ('git status --porcelain') do set "DIRTY=1"
git fetch --quiet origin main >nul 2>&1
for /f %%i in ('git rev-parse HEAD 2^>nul') do set "LOCAL=%%i"
for /f %%i in ('git rev-parse origin/main 2^>nul') do set "REMOTE=%%i"
popd
if defined DIRTY goto :dirty
if not defined LOCAL goto :connect
if not defined REMOTE goto :connect
if /i "%LOCAL%"=="%REMOTE%" goto :connect
goto :ahead

:norepo
echo [note] Cannot find the website folder from here, so the
echo        committed-and-pushed check is skipped.  Publishing still
echo        works - it publishes whatever is on GitHub.
echo.
goto :connect

:nogit
echo [note] git is not on PATH, so this cannot check whether your work
echo        is committed and pushed.  The server publishes GitHub's
echo        'main' branch, whatever that currently is.
echo.
goto :connect

REM ---- talk to the server ----
:connect
echo Checking %HOST% is reachable as %SSHUSER% ...
"%PLINK%" -batch -ssh -hostkey "%HOSTKEY%" -i "%KEY%" %SSHUSER%@%HOST% "exit" >"%LOG%" 2>&1
if errorlevel 1 goto :noconnect

echo Publishing.  The server does the work; this takes about ten seconds.
echo.
"%PLINK%" -batch -ssh -hostkey "%HOSTKEY%" -i "%KEY%" %SSHUSER%@%HOST% "%DEPLOY% %*" >"%LOG%" 2>&1
set "RC=%ERRORLEVEL%"
type "%LOG%"
echo.
if "%RC%"=="0" goto :ok
echo [!!] The server exited with code %RC%, so the publish did not
echo      finish.  Read what it said above - it is written to say why.
echo      Nothing is left half-done: the site is backed up before
echo      anything is overwritten, and --rollback puts that copy back.
goto :saved

:ok
echo [ok] The server finished without an error.  Read the "checking"
echo      lines above, not just this one - that is where it says
echo      whether the change actually landed on the public address.
goto :saved

:saved
echo.
echo   A copy of the above: %LOG%
echo   Paste it into docs\DEPLOY-LOG.md, newest at the top.  The
echo   failures are the valuable part.
goto :end

REM ---- the ways this stops early ----
:dirty
echo [stop] This folder has changes that are not committed:
echo.
pushd "%REPO%"
git status --short
popd
echo.
echo   The server publishes GitHub, not this folder, so publishing now
echo   would put the OLD code back.  Commit and push first:
echo.
echo       git add -A ^&^& git commit -m "what changed" ^&^& git push
echo.
goto :end

:ahead
echo [warn] This folder is ahead of GitHub.  Commits here that GitHub
echo        has never seen:
echo.
pushd "%REPO%"
git log --oneline origin/main..HEAD
popd
echo.
echo   Publishing now would publish GitHub's version, without these.
echo   Push them first:
echo.
echo       git push
echo.
set /p ANSWER="Publish GitHub's version anyway? [y/N] "
if /i "%ANSWER%"=="y" goto :connect
goto :end

:noplink
echo [stop] PuTTY's plink.exe is not at:
echo   %PLINK%
echo.
echo   Install PuTTY, or edit this .bat and point PLINK at plink.exe.
echo   Without it, publish the normal way: SECRETARY.md step 3.
goto :end

:nokey
echo [stop] No SSH key for portal found.  Looked for:
echo   %USERPROFILE%\.ssh\caltech-website-portal.ppk
echo   %USERPROFILE%\.ssh\caltech-portal.ppk
echo.
echo   This file is only a shortcut for somebody who has a key.  To
echo   publish without one, use PuTTY: SECRETARY.md step 3.  To make a
echo   key: docs\ACCESS.md.
goto :end

:noconnect
echo.
echo [stop] Could not log in to %HOST%.  What the connection said:
echo.
type "%LOG%"
echo.
echo   Almost always the VPN.  portal is unreachable from off campus,
echo   and also unreachable on the default split-tunnel profile - so
echo   connect the Caltech VPN, choose "Tunnel All", and run this
echo   again.  "Permission denied" instead means the key is not
echo   installed on the server: docs\ACCESS.md.  A host key complaint
echo   means the pinned fingerprint in this file no longer matches -
echo   do not just delete the pin, find out why.
goto :end

:end
echo.
pause
endlocal
