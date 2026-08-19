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

set -euo pipefail

# Without this, any failing command exits the script with no output at all,
# which is exactly what happened on the first run on 2026-08-18.
trap 'echo "FAILED at line $LINENO (exit $?)" >&2' ERR

SITE="/srv/www.alpine.caltech.edu/www"
REPO="$SITE/repo"
DOCROOT="$SITE/docroot"
BACKUPS="$SITE/backups"
KEEP=5                      # how many old copies of the docroot to keep
URL="https://staging.alpine.caltech.edu"

[ -d "$REPO/.git" ] || { echo "No git checkout at $REPO. See docs/DEPLOY.md."; exit 1; }
[ -d "$DOCROOT" ]   || { echo "No document root at $DOCROOT."; exit 1; }

cd "$REPO"

echo "deploying $(git rev-parse --short HEAD)  $(git log -1 --format=%s)"
echo

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

echo
echo "done."
echo
echo "  check it:  $URL"
echo "  rollback:  git -C $REPO checkout <commit> && $SITE/bin/deploy"
echo "             (or copy a folder back from $BACKUPS)"
echo
echo "Write down what happened in docs/DEPLOY-LOG.md."
