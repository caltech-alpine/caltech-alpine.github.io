#!/usr/bin/env bash
#
# server-deploy.sh - publish the site, from the server itself.
#
# You do not run this directly. Log into portal.caltech.edu and run:
#
#     /srv/www.alpine.caltech.edu/www/bin/deploy
#
# That wrapper pulls the latest code from GitHub and then runs this file, so
# the deploy logic below is version controlled and updates itself. Setting the
# whole thing up the first time is documented in docs/DEPLOY.md.
#
# Nothing here needs anything on your own computer. PuTTY is enough.
#
# One command does four things, on purpose, because the ones after the first
# are the ones that get skipped:
#
#   1. REFUSES to publish a commit GitHub's checks have failed, or have not
#      finished checking. So nobody has to remember to look at the Actions tab.
#   2. Backs up the current site before overwriting it.
#   3. Publishes, and writes version.txt saying exactly which commit is live.
#   4. Fetches the public address afterwards and says whether the change landed.
#
#     bin/deploy              publish and check
#     bin/deploy --rollback   put the previous copy back, now
#     bin/deploy --force      publish past a red or unfinished check. Emergency.

set -euo pipefail

# Without this, any failing command exits the script with no output at all,
# which is exactly what happened on the first run on 2026-08-18.
trap 'echo "FAILED at line $LINENO (exit $?)" >&2' ERR

SITE="/srv/www.alpine.caltech.edu/www"
REPO="$SITE/repo"
DOCROOT="$SITE/docroot"
BACKUPS="$SITE/backups"
KEEP=5                      # how many old copies of the docroot to keep

# THE ONE PLACE THE PUBLISHED ADDRESS IS WRITTEN. This script prints it and
# checks it at the end, and SECRETARY.md deliberately does not repeat it -- so
# the day IMSS repoints alpine.caltech.edu at this document root, exactly one
# line changes and no document anywhere goes quietly stale.
URL="https://staging.alpine.caltech.edu"

# Where the automatic checks live, for the gate below.
GH_REPO="caltech-alpine/caltech-alpine.github.io"

FORCE=0
ROLLBACK=0
for arg in "$@"; do
  case "$arg" in
    --force)    FORCE=1 ;;
    --rollback) ROLLBACK=1 ;;
    -h|--help)
      echo "usage: bin/deploy [--force | --rollback]"
      echo
      echo "  With no options: publishes whatever is on GitHub 'main' to"
      echo "  $URL, and checks that it landed."
      echo
      echo "  --rollback  put the previous copy of the site back, right now."
      echo "              Use this if a deploy made the site wrong. Then undo"
      echo "              the change on GitHub and deploy again properly."
      echo
      echo "  --force     publish even if GitHub's checks on that commit are"
      echo "              red, or have not finished yet. An emergency only."
      exit 0 ;;
    *) echo "unknown option: $arg   (try --help)"; exit 2 ;;
  esac
done

[ -d "$REPO/.git" ] || { echo "No git checkout at $REPO. See docs/DEPLOY.md."; exit 1; }
[ -d "$DOCROOT" ]   || { echo "No document root at $DOCROOT."; exit 1; }

# ------------------------------------------------------------- rollback -----
# Put back the copy taken before the last deploy. This has to live here, in the
# script the wrapper runs, rather than being done with git: bin/deploy does
# `git reset --hard origin/main` before it gets here, so the older instruction
# to `git checkout <commit>` and deploy could never have worked -- the checkout
# was discarded a line before this file was read. Found and fixed 2026-08-30.
#
# Deliberately not a way to reach an arbitrary old version. It is the one thing
# somebody needs at the moment the site is wrong and they are frightened: get
# back to the last state that was fine. The real fix -- undoing the edit on
# GitHub and deploying again -- happens afterwards, calmly.
if [ "$ROLLBACK" = 1 ]; then
  newest="$(ls -1dt "$BACKUPS"/docroot-* 2>/dev/null | head -1 || true)"
  if [ -z "$newest" ]; then
    echo "There are no backups in $BACKUPS, so there is nothing to roll back to."
    echo "Undo the change on GitHub instead, then run bin/deploy again."
    exit 1
  fi
  was="$(grep '^short' "$newest/version.txt" 2>/dev/null | awk '{print $2}' || true)"
  echo "rolling back to $(basename "$newest")${was:+  (commit $was)}"
  rsync -a --delete \
    --exclude 'cache/*' --exclude 'logs/*' --exclude 'includes/config.local.php' \
    "$newest/" "$DOCROOT/"
  mkdir -p "$DOCROOT/cache" "$DOCROOT/logs"
  find "$DOCROOT" -type d -not -path "$DOCROOT/cache/*" -not -path "$DOCROOT/logs/*" \
    -exec chmod 2775 {} +
  find "$DOCROOT" -type f -not -path "$DOCROOT/cache/*" -not -path "$DOCROOT/logs/*" \
    -exec chmod 0664 {} +
  chmod 3777 "$DOCROOT/cache" "$DOCROOT/logs"
  echo
  echo "done. $URL is back to the previous copy."
  echo
  echo "That is a patch, not a fix: the next ordinary deploy will publish"
  echo "whatever is on GitHub again. Undo the change there too."
  echo "Write down what happened in docs/DEPLOY-LOG.md."
  exit 0
fi

cd "$REPO"

COMMIT="$(git rev-parse HEAD)"
SHORT="$(git rev-parse --short HEAD)"
SUBJECT="$(git log -1 --format=%s)"

echo "deploying $SHORT  $SUBJECT"
echo

# ----------------------------------------------------------- the gate -------
# GitHub already checks every commit: that the officer data adds up, that next
# year's roster edits still render, and that every page comes out of PHP with
# no error in it. All of that has finished long before anybody logs in here.
#
# What was missing was anything that STOPPED a red commit being published. The
# only thing standing between a broken roster and the club's website was a
# human remembering to open the Actions tab first, and that is exactly the kind
# of thing a new officer does not know to do. So this asks GitHub instead.
#
# Three answers, three behaviours:
#
#   green      publish.
#   red        refuse. Something in this commit is known to be broken.
#   pending    refuse, and say to wait a minute. This is the ordinary case
#              when somebody edits and deploys inside the same minute.
#   cannot     publish, with a warning. GitHub's API being unreachable from
#     tell     this server is not a reason the club cannot update its website,
#              so this gate fails OPEN on purpose. Anything else would make the
#              site un-editable on the day GitHub has an outage.
#
# Unauthenticated, so no token has to live on this server. The repository is
# public and the rate limit (60/hour per IP) is far above one deploy.

check_status() {
  # ASK ABOUT THE PUSH RUN, NOT ABOUT THE COMMIT. The obvious endpoint --
  # /commits/<sha>/check-runs -- is the wrong one, and quietly so: the site's
  # workflow also runs on a half-hourly schedule against whatever commit is on
  # main, so a commit that has been live for a day carries dozens of check-runs
  # and usually has one queued at any moment. A gate reading that list would
  # have refused to deploy for most of every hour. Measured 2026-08-30: 30
  # check-runs on one commit, one of them queued.
  #
  # Filtering the workflow-runs endpoint to event=push returns exactly the run
  # that checked THIS change when it was committed, which is the question being
  # asked. Re-running that workflow updates the same run, so a fixed flake
  # clears the gate without needing a new commit.
  url="https://api.github.com/repos/$GH_REPO/actions/runs?head_sha=$COMMIT&event=push"
  body=""
  if command -v curl >/dev/null 2>&1; then
    body="$(curl -fsS --max-time 20 -H 'Accept: application/vnd.github+json' "$url" 2>/dev/null || true)"
  elif command -v python3 >/dev/null 2>&1; then
    body="$(python3 -c 'import sys,urllib.request
try:    print(urllib.request.urlopen(sys.argv[1], timeout=20).read().decode())
except Exception: pass' "$url" 2>/dev/null || true)"
  fi

  [ -n "$body" ] || { echo "unknown"; return; }

  # The run object comes first in the response, so the FIRST status and the
  # FIRST conclusion in the text are its own. No JSON parser has to exist on
  # this machine, which matters: portal is a file server with very little on it.
  count="$(printf '%s' "$body" | grep -oE '"total_count"[[:space:]]*:[[:space:]]*[0-9]+' \
           | head -1 | grep -oE '[0-9]+' || true)"
  [ -n "$count" ] || { echo "unknown"; return; }
  [ "$count" = "0" ] && { echo "pending"; return; }   # GitHub has not started yet

  state="$(printf '%s' "$body" | grep -o '"status"[[:space:]]*:[[:space:]]*"[a-z_]*"' \
           | head -1 | sed 's/.*"\([a-z_]*\)"$/\1/' || true)"
  concl="$(printf '%s' "$body" | grep -o '"conclusion"[[:space:]]*:[[:space:]]*"[a-z_]*"' \
           | head -1 | sed 's/.*"\([a-z_]*\)"$/\1/' || true)"

  [ "$state" = "completed" ] || { echo "pending"; return; }
  case "$concl" in
    success)                                       echo "green" ;;
    failure|timed_out|action_required|cancelled)   echo "red" ;;
    *)                                             echo "unknown" ;;
  esac
}

GATE="$(check_status)"

if [ "$GATE" != "green" ] && [ "$FORCE" = 1 ]; then
  echo "--force: publishing $SHORT even though the check gate says '$GATE'."
  echo
elif [ "$GATE" = "red" ]; then
  echo "REFUSING TO PUBLISH."
  echo
  echo "  GitHub's checks on commit $SHORT FAILED."
  echo "  Something in this change is broken. It should not go on the website."
  echo
  echo "  See what went wrong -- click the red X beside $SHORT:"
  echo "    https://github.com/$GH_REPO/commits/main"
  echo
  echo "  Fix it on GitHub, wait for the green tick, then run this again."
  echo "  To publish anyway, in an emergency only:  $SITE/bin/deploy --force"
  exit 1
elif [ "$GATE" = "pending" ]; then
  echo "NOT PUBLISHING YET."
  echo
  echo "  GitHub has not finished checking commit $SHORT."
  echo "  That normally means the change was committed less than a minute ago."
  echo "  Wait a minute and run this command again."
  echo
  echo "  Watch it here:  https://github.com/$GH_REPO/actions"
  echo "  To publish anyway, in an emergency only:  $SITE/bin/deploy --force"
  exit 1
elif [ "$GATE" = "green" ]; then
  echo "GitHub's checks on $SHORT passed."
  echo
else
  echo "WARNING: could not reach GitHub to ask whether $SHORT passed its checks."
  echo "         Publishing anyway. If the site looks wrong afterwards, look at"
  echo "         https://github.com/$GH_REPO/actions"
  echo
fi

# ------------------------------------------------------------------ backup --
# No rollback button exists, so take a copy first. This is cheap: the site is a
# few megabytes and the copy lives outside the document root.

mkdir -p "$BACKUPS"
STAMP="$(date +%Y-%m-%d-%H%M)"
# cache/ and logs/ are deliberately NOT copied. They hold files the web server
# created as www-data, mode 0600, in a world-writable directory - and we are
# khunady, so `cp -a` cannot read them and dies mid-backup taking the whole
# deploy with it (observed 2026-08-19 on logs/.salt). Nothing in either is worth
# keeping anyway: the cache regenerates on the next page view, and the deploy
# never touches the live copies of either.
if [ -n "$(ls -A "$DOCROOT" 2>/dev/null)" ]; then
  rsync -a --exclude 'cache/' --exclude 'logs/' \
    "$DOCROOT/" "$BACKUPS/docroot-$STAMP/"
  echo "backed up the current site to $BACKUPS/docroot-$STAMP"
fi

# Keep the most recent few and delete the rest. The || true matters: with no
# backups yet the glob matches nothing, ls exits non-zero, and under
# `set -o pipefail` that would kill this script without printing anything.
old_backups="$(ls -1dt "$BACKUPS"/docroot-* 2>/dev/null | tail -n +$((KEEP + 1)) || true)"
if [ -n "$old_backups" ]; then
  while read -r old; do
    [ -n "$old" ] || continue
    rm -rf "$old"
    echo "removed old backup $(basename "$old")"
  done <<< "$old_backups"
fi

# ----------------------------------------------------------------- publish --
# Excluded paths are also protected from --delete: rsync will not remove a file
# on the far side that an --exclude covers. That is what keeps the server's
# cache, its logs and its config.local.php safe.

echo
echo "publishing..."
rsync -a --delete \
  --exclude '.git/' \
  --exclude '.github/' \
  --exclude '.gitignore' \
  --exclude 'docs/' \
  --exclude 'README.md' \
  --exclude 'SECRETARY.md' \
  --exclude '_site/' \
  --exclude '_preview/' \
  --exclude '_deploy/' \
  --exclude 'tools/*.py' \
  --exclude 'tools/*.sh' \
  --exclude 'tools/*.bat' \
  --exclude 'tools/route.json' \
  --exclude 'cache/*' \
  --exclude 'logs/*' \
  --exclude 'includes/config.local.php' \
  "$REPO/" "$DOCROOT/"

# ----------------------------------------------------------- what is live ---
# A stamp saying which commit this document root is, readable over the web at
# $URL/version.txt. Without it the only way to answer "did my change actually
# reach the server" is to read the page and hope you would notice - and the
# 2026-08-28 entry in docs/DEPLOY-LOG.md is what that costs: a verifier
# reported 23 checks and 0 failures against a copy that was weeks out of date.
#
# The rsync above deletes it every time, because it is not in the repository.
# That is fine and deliberate: it is rewritten here, one line later, so it can
# never survive as a stale claim about a deploy that did not happen.
{
  echo "commit   $COMMIT"
  echo "short    $SHORT"
  echo "subject  $SUBJECT"
  echo "deployed $(date -u '+%Y-%m-%dT%H:%M:%SZ')"
  echo "by       $(id -un)"
} > "$DOCROOT/version.txt"

# ------------------------------------------------------------- permissions --
# 0664 and 2775 are what IMSS asks for, so that every member of the group can
# edit what any of them uploaded. cache/ and logs/ are the exception: the web
# server runs as www-data on a different machine and is not in our group, so
# until IMSS fixes that they get 3777 - world writable, plus the sticky bit so
# only a file's owner can delete it. Both deny all HTTP access via .htaccess.

# The CONTENTS of cache/ and logs/ are skipped, not just given a different
# mode: the web server wrote them as www-data, and chmod on somebody else's
# file fails even in a directory you own. Sweeping the whole tree therefore
# killed the deploy after it had already published (2026-08-19). The two
# directories themselves are ours, so those still get set.
mkdir -p "$DOCROOT/cache" "$DOCROOT/logs"
find "$DOCROOT" -type d -not -path "$DOCROOT/cache/*" -not -path "$DOCROOT/logs/*" \
  -exec chmod 2775 {} +
find "$DOCROOT" -type f -not -path "$DOCROOT/cache/*" -not -path "$DOCROOT/logs/*" \
  -exec chmod 0664 {} +
chmod 3777 "$DOCROOT/cache" "$DOCROOT/logs"

# ------------------------------------------------------------ smoke test ----
# Publishing and checking are one action, not two, because the second one is
# the one that gets skipped. This asks the public address the same questions a
# visitor would: does the home page come back, and is the copy now being served
# the commit we just published?
#
# It fails SOFT. A deploy that worked must not be reported as a failure because
# this server cannot reach Cloudflare, so an unreachable site prints "could not
# check from here" and the exit status stays 0.

echo
echo "checking $URL ..."

fetch() {                                   # fetch <url> -> body on stdout
  if command -v curl >/dev/null 2>&1; then
    curl -fsS --max-time 25 "$1" 2>/dev/null || true
  elif command -v python3 >/dev/null 2>&1; then
    python3 -c 'import sys,urllib.request
try:    print(urllib.request.urlopen(sys.argv[1], timeout=25).read().decode("utf-8","replace"))
except Exception: pass' "$1" 2>/dev/null || true
  fi
}

live_version="$(fetch "$URL/version.txt")"
home="$(fetch "$URL/")"

if [ -z "$home" ] && [ -z "$live_version" ]; then
  echo "  --   could not reach $URL from this server."
  echo "       That does not mean the deploy failed. Open it in a browser."
elif printf '%s' "$live_version" | grep -q "^commit   $COMMIT$"; then
  echo "  ok   $URL is serving $SHORT - the commit just published."
  if printf '%s' "$home" | grep -q "Alpine Club"; then
    echo "  ok   the home page loads and is ours."
  else
    echo "  !!   the home page did not come back as expected. Open $URL."
  fi
else
  echo "  !!   $URL is NOT yet reporting $SHORT."
  echo "       Cloudflare caches for a short time; wait a minute and check"
  echo "       $URL/version.txt in a browser. If it still disagrees, ask for"
  echo "       help - something is serving a different copy of the site."
fi

echo
echo "done."
echo
echo "  live at:   $URL"
echo "  what ran:  $URL/version.txt"
echo "  gone wrong: $SITE/bin/deploy --rollback   puts the previous copy back"
echo
echo "Write down what happened in docs/DEPLOY-LOG.md."
