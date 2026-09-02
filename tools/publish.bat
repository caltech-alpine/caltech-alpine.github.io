@echo off
REM ===================================================================
REM  Publish the Caltech Alpine Club website.  Safe to double-click.
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
REM  This file is the only copy.  "Publish Alpine Site.bat" on the
REM  Desktop is a two-line stub that calls this one, deliberately: a
REM  real copy out there would drift from this one and nobody would
REM  know which had been run.
REM
REM  NO SSH KEY?  You do not need this file at all.  The same publish
REM  command typed into PuTTY is SECRETARY.md step 3, and that route is
REM  the one the club depends on.  This is a shortcut, not the
REM  procedure.  Making a key: docs\ACCESS.md.
REM ===================================================================

setlocal
title Publish the Caltech Alpine Club website

set "PLINK=C:\Program Files\PuTTY\plink.exe"
set "HOST=portal.caltech.edu"
set "SRV=/srv/www.alpine.caltech.edu/www"
set "LOG=%TEMP%\alpine-publish.txt"

REM  The server's host key, pinned.  Two reasons it is written down here
REM  rather than trusted on first use: a first run then cannot be asked
REM  to accept an unknown key, and a key that CHANGES stops the publish
REM  instead of being waved through.  Read off the server 2026-09-02
REM  with `plink -v`; if it ever fails, re-read it the same way before
REM  believing the new one.
set "HOSTKEY=SHA256:0kAZ/Q/s9uL1wfDko5ACpKe09zLVCsN62TmdGjEByTY"

REM ---- the website folder ----
set "REPO=%~dp0.."
if not exist "%REPO%\index.php" set "REPO=C:\Users\kyleh\Documents\2Projects\alpine-club\website"
if not exist "%REPO%\index.php" set "REPO="
REM  %~fi collapses the "\tools\.." so the window prints a path you can paste.
if defined REPO for %%i in ("%REPO%") do set "REPO=%%~fi"

REM ---- the key ----
set "KEY="
for %%K in ("%USERPROFILE%\.ssh\caltech-website-portal.ppk" "%USERPROFILE%\.ssh\caltech-portal.ppk") do if exist %%K set "KEY=%%~K"

REM ---- your Caltech username, the same file tools\deploy.sh remembers ----
set "SSHUSER="
if defined REPO if exist "%REPO%\.deploy-user" set /p SSHUSER=<"%REPO%\.deploy-user"
if not defined SSHUSER set "SSHUSER=%USERNAME%"

REM ---- what git knows, and where the site actually lives -------------
REM  The published address is read OUT OF tools\server-deploy.sh rather
REM  than written here.  That script's own comment calls itself "the one
REM  place the published address is written", and it is right: when IMSS
REM  repointed the hostname on 2026-09-02 exactly one line changed, and
REM  this window still printed the truth without being touched.
set "BRANCH="
set "HEADREV="
set "ORIGIN="
set "SITE="
set "DIRTY="
set "LOCAL="
set "REMOTE="
if not defined REPO goto :printhead
where git >nul 2>&1
if errorlevel 1 goto :readurl
pushd "%REPO%"
for /f "delims=" %%i in ('git rev-parse --abbrev-ref HEAD 2^>nul') do set "BRANCH=%%i"
for /f "delims=" %%i in ('git log -1 --format^="%%h  %%s" 2^>nul') do set "HEADREV=%%i"
for /f "delims=" %%i in ('git remote get-url origin 2^>nul') do set "ORIGIN=%%i"
for /f %%i in ('git status --porcelain') do set "DIRTY=1"
git fetch --quiet origin main >nul 2>&1
for /f %%i in ('git rev-parse HEAD 2^>nul') do set "LOCAL=%%i"
for /f %%i in ('git rev-parse origin/main 2^>nul') do set "REMOTE=%%i"
popd

:readurl
if not defined REPO goto :printhead
for /f "tokens=2 delims==" %%i in ('findstr /b /c:"URL=" "%REPO%\tools\server-deploy.sh"') do set "SITE=%%i"
if defined SITE set "SITE=%SITE:"=%"

REM ---- say what is about to happen, in full --------------------------
:printhead
echo.
echo ===================================================================
echo  Publishing the Caltech Alpine Club website
echo ===================================================================
if defined REPO      echo  this folder .... %REPO%
if not defined REPO  echo  this folder .... NOT FOUND - the git checks below are skipped
if defined BRANCH    echo  branch ......... %BRANCH%
if defined HEADREV   echo  newest commit .. %HEADREV%
if defined ORIGIN    echo  GitHub ......... %ORIGIN%
echo                   the server publishes GitHub's 'main', NOT this folder
echo  -------------------------------------------------------------------
echo  key ............ %KEY%
echo  login .......... %SSHUSER%@%HOST%
echo                   needs the Caltech VPN on "Tunnel All"
echo  runs ........... %SRV%/bin/deploy %*
echo  publishes into . %SRV%/docroot
echo  backs up to .... %SRV%/backups        the newest 5 are kept
if defined SITE      echo  live at ........ %SITE%
if defined SITE      echo  what ran ....... %SITE%/version.txt
echo  transcript ..... %LOG%
echo ===================================================================
echo.

REM ---- preconditions that stop everything ----
if not exist "%PLINK%" goto :noplink
if not defined KEY goto :nokey
if defined DIRTY goto :dirty
if not defined LOCAL goto :nogitnote
if not defined REMOTE goto :nogitnote
if /i "%LOCAL%"=="%REMOTE%" goto :inagreement
goto :ahead

:nogitnote
echo [1/3] git could not be consulted, so whether your work is committed
echo       and pushed is UNCHECKED.  The server publishes GitHub's 'main',
echo       whatever that currently is.
goto :connect

:inagreement
echo [1/3] ok - working tree is clean and matches GitHub's main.
goto :connect

REM ---- talk to the server ----
:connect
echo [2/3] logging in to %HOST% as %SSHUSER% with the key ...
"%PLINK%" -batch -ssh -hostkey "%HOSTKEY%" -i "%KEY%" %SSHUSER%@%HOST% "exit" >"%LOG%" 2>&1
if errorlevel 1 goto :noconnect
echo       ok - authenticated, no password and no Duo push needed.
echo.
echo [3/3] running %SRV%/bin/deploy on the server.  It fetches the code
echo       from GitHub itself, refuses a commit whose checks failed, backs
echo       the site up, publishes, writes version.txt, then fetches the
echo       public address to prove the change landed.  About ten seconds.
echo.
echo -------------------- what the server said -------------------------
"%PLINK%" -batch -ssh -hostkey "%HOSTKEY%" -i "%KEY%" %SSHUSER%@%HOST% "%SRV%/bin/deploy %*" >"%LOG%" 2>&1
set "RC=%ERRORLEVEL%"
type "%LOG%"
echo -------------------------------------------------------------------
echo.
if "%RC%"=="0" goto :ok
echo [!!] The server exited with code %RC%, so the publish did not
echo      finish.  Read what it said above - it is written to say why.
echo      Nothing is left half-done: the site is backed up before
echo      anything is overwritten, and --rollback puts that copy back.
goto :saved

:ok
echo [ok] The server finished without an error.  Read its "checking"
echo      lines above, not just this one - that is where it says
echo      whether the change actually landed on the public address.
goto :saved

:saved
if defined SITE echo      Look at it: %SITE%
if defined SITE echo      What is live: %SITE%/version.txt
echo      Roll back:    %SRV%/bin/deploy --rollback
echo.
echo      A copy of the above: %LOG%
echo      Paste it into docs\DEPLOY-LOG.md, newest at the top.  The
echo      failures are the valuable part.
goto :end

REM ---- the ways this stops early ----
:dirty
echo [1/3] STOP - this folder has changes that are not committed:
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
echo [1/3] WARN - this folder is ahead of GitHub.  Commits here that
echo       GitHub has never seen:
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
echo [stop] Could not log in to %SSHUSER%@%HOST%.  What the connection
echo        actually said:
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
