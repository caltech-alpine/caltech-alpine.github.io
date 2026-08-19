#!/usr/bin/env bash
#
# deploy.sh - copy this repository to Caltech hosting.
#
#   ./tools/deploy.sh --dry-run USERNAME     stage and list, send nothing
#   ./tools/deploy.sh USERNAME               stage and send to staging
#   ./tools/deploy.sh --target prod USERNAME send to production (asks first)
#
# USERNAME is your Caltech username. You must be on campus or on the VPN with
# "Tunnel All". The full procedure, including permissions and verification, is
# in docs/DEPLOY.md.
#
# What gets sent is exactly what git has committed, minus the exclusions below.
# That is deliberate: it makes "deployed" and "committed" the same thing, so
# what is on the server always exists somewhere else too.
#
# No password, key or hostname secret is stored here. ssh asks you.

set -euo pipefail

HOST="portal.caltech.edu"
DOCROOT_STAGING="/srv/www.alpine.caltech.edu/www/docroot"        # verified on the server 2026-08-18
DOCROOT_PROD=""          # not set until the cutover; see docs/DEPLOY.md
STAGE_DIR="_deploy"

# Committed files that belong in the repository but not on the web server.
EXCLUDE_PATTERNS=(
  '^\.github/'
  '^\.gitignore$'
  '^docs/'
  '^README\.md$'
  '^tools/.*\.py$'
  '^tools/route\.json$'
  '^tools/deploy\.sh$'
  '^tools/probe\.php$'      # uploaded by hand, once, under a different name
  '^_site/'
  '^_preview/'
)

# ---------------------------------------------------------------- arguments --

DRY_RUN=0
TARGET="staging"
USERNAME=""

while [ $# -gt 0 ]; do
  case "$1" in
    --dry-run) DRY_RUN=1; shift ;;
    --target)  TARGET="${2:-}"; shift 2 ;;
    -h|--help) sed -n '2,20p' "$0"; exit 0 ;;
    -*)        echo "unknown option: $1" >&2; exit 2 ;;
    *)         USERNAME="$1"; shift ;;
  esac
done

if [ -z "$USERNAME" ]; then
  echo "usage: ./tools/deploy.sh [--dry-run] [--target staging|prod] USERNAME" >&2
  exit 2
fi

case "$TARGET" in
  staging) DOCROOT="$DOCROOT_STAGING" ;;
  prod)    DOCROOT="$DOCROOT_PROD" ;;
  *)       echo "target must be staging or prod" >&2; exit 2 ;;
esac

if [ -z "$DOCROOT" ]; then
  echo "No document root is set for target '$TARGET'." >&2
  echo "Fill in DOCROOT_PROD at the top of this script once IMSS has told us" >&2
  echo "what production looks like. See docs/DEPLOY.md, production cutover." >&2
  exit 2
fi

cd "$(dirname "$0")/.."

# ------------------------------------------------------------------- checks --

if [ -n "$(git status --porcelain)" ]; then
  echo "The working copy has uncommitted changes." >&2
  echo "Commit them first, so that what is on the server also exists in git." >&2
  git status --short >&2
  exit 1
fi

echo "repository : $(git rev-parse --short HEAD) on $(git rev-parse --abbrev-ref HEAD)"
echo "target     : $TARGET"
echo "server     : $USERNAME@$HOST:$DOCROOT"
echo

# -------------------------------------------------------------------- stage --

rm -rf "$STAGE_DIR"
mkdir -p "$STAGE_DIR"

count=0
while IFS= read -r f; do
  skip=0
  for pat in "${EXCLUDE_PATTERNS[@]}"; do
    if printf '%s' "$f" | grep -Eq "$pat"; then skip=1; break; fi
  done
  [ "$skip" -eq 1 ] && continue
  mkdir -p "$STAGE_DIR/$(dirname "$f")"
  cp "$f" "$STAGE_DIR/$f"
  count=$((count + 1))
done < <(git ls-files)

# The site writes here. The directories must exist even though their contents
# are not committed; their .htaccess files are.
mkdir -p "$STAGE_DIR/cache" "$STAGE_DIR/logs"

echo "staged $count files in $STAGE_DIR/"
echo "size   $(du -sh "$STAGE_DIR" | cut -f1)"
echo

if [ "$DRY_RUN" -eq 1 ]; then
  echo "--- files that would be sent ---"
  (cd "$STAGE_DIR" && find . -type f | sed 's|^\./||' | sort)
  echo
  echo "Dry run. Nothing was sent. Remove --dry-run to deploy."
  exit 0
fi

if [ "$TARGET" = "prod" ]; then
  echo "This replaces the club's live website."
  printf 'Type the word production to continue: '
  read -r confirm
  [ "$confirm" = "production" ] || { echo "Stopped."; exit 1; }
fi

# ------------------------------------------------------------------- upload --
#
# tar over ssh rather than scp, for two reasons: dotfiles (.htaccess is most of
# our server configuration, and shell globs miss it), and one connection instead
# of one per file. Nothing on the far side is deleted; this overwrites.

echo "uploading..."
tar czf - -C "$STAGE_DIR" . \
  | ssh "$USERNAME@$HOST" "mkdir -p '$DOCROOT' && tar xzf - -C '$DOCROOT'"

# 0664/2775 is what IMSS asks for. cache/ and logs/ are the exception: the web
# server runs as www-data on a different machine, is not in the alpinewww group,
# and so cannot write to a 2775 directory. Until IMSS puts it in the group those
# two get 3777 - world writable, plus the sticky bit so only a file's owner can
# delete it. Both deny all HTTP access via their own .htaccess. See docs/SERVERS.md.
echo "setting permissions (0664 files, 2775 folders, 3777 on cache and logs)..."
ssh "$USERNAME@$HOST" "cd '$DOCROOT' \
  && find . -type d -exec chmod 2775 {} \; \
  && find . -type f -exec chmod 0664 {} \; \
  && chmod 3777 cache logs"

echo
echo "done. Now:"
echo "  ssh $USERNAME@$HOST 'cd $DOCROOT && php tools/check.php'"
echo "  python tools/verify_deploy.py https://staging.alpine.caltech.edu"
echo "  write what happened into docs/DEPLOY-LOG.md"
