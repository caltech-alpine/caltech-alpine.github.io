#!/usr/bin/env bash
#
# deploy.sh - copy this repository to Caltech hosting.
#
#   ./tools/deploy.sh              publish to staging, then check it
#   ./tools/deploy.sh --dry-run    list what would be sent, send nothing
#   ./tools/deploy.sh --target prod  publish to production (asks first)
#
# That is the whole procedure. The script asks for your Caltech username once
# and remembers it, checks that the server is reachable before it does anything
# slow, uploads over a SINGLE ssh connection so Duo prompts you once rather
# than twice, and runs the outside-in verification for you at the end.
#
# You must be on campus or on the VPN with "Tunnel All" - the script says so
# plainly if you are not. Full background is in docs/DEPLOY.md.
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
URL_STAGING="https://staging.alpine.caltech.edu"
URL_PROD="https://alpine.caltech.edu"
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
    -h|--help) sed -n '2,18p' "$0"; exit 0 ;;
    -*)        echo "unknown option: $1" >&2; exit 2 ;;
    *)         USERNAME="$1"; shift ;;
  esac
done

# Where the remembered username lives. Git-ignored: it is a preference, not
# configuration, and the next person's is different.
USER_FILE="$(dirname "$0")/../.deploy-user"

if [ -z "$USERNAME" ]; then
  USERNAME="${ALPINE_DEPLOY_USER:-}"
fi
if [ -z "$USERNAME" ] && [ -r "$USER_FILE" ]; then
  USERNAME="$(tr -d '[:space:]' < "$USER_FILE")"
fi
# A dry run never opens a connection, so it has no business asking who you are.
if [ -z "$USERNAME" ] && [ "$DRY_RUN" -eq 1 ]; then
  USERNAME="(not needed for a dry run)"
fi

if [ -z "$USERNAME" ]; then
  printf 'Your Caltech username (the one you log in to access.caltech.edu with): '
  read -r USERNAME
  [ -n "$USERNAME" ] || { echo "Nothing entered. Stopped." >&2; exit 2; }
  echo "$USERNAME" > "$USER_FILE"
  echo "Remembered in .deploy-user - you will not be asked again."
fi

case "$TARGET" in
  staging) DOCROOT="$DOCROOT_STAGING"; SITE_URL="$URL_STAGING" ;;
  prod)    DOCROOT="$DOCROOT_PROD";    SITE_URL="$URL_PROD" ;;
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
  echo "The working copy has uncommitted changes, and this script sends only" >&2
  echo "what git has committed - so that everything on the server also exists" >&2
  echo "somewhere else. Commit first:" >&2
  echo >&2
  git status --short >&2
  echo >&2
  echo "    git add -A && git commit -m \"what you changed\"" >&2
  echo "    ./tools/deploy.sh" >&2
  exit 1
fi

# Reachability first. Without the VPN on "Tunnel All" the ssh below simply
# hangs until it times out, which reads like a broken script rather than a
# missing VPN. One second of checking removes that whole class of confusion.
if [ "$DRY_RUN" -eq 0 ]; then
  if ! (exec 3<>"/dev/tcp/$HOST/22") 2>/dev/null; then
    echo "Cannot reach $HOST on port 22." >&2
    echo >&2
    echo "Almost always this means the VPN is off, or it is on but not set to" >&2
    echo "\"Tunnel All\" - split tunnelling does not route Caltech hosting." >&2
    echo "Turn it on, then run this again. Nothing has been sent." >&2
    exit 1
  fi
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

# ONE ssh connection, not two. Upload and permissions used to be separate
# invocations, which meant two password prompts and two Duo pushes for a single
# deploy - the most annoying thing about publishing this site. They are one
# command now.
#
# 0664/2775 is what IMSS asks for. cache/ and logs/ are the exception: the web
# server runs as www-data on a different machine, is not in the alpinewww group,
# and so cannot write to a 2775 directory. Until IMSS puts it in the group those
# two get 3777 - world writable, plus the sticky bit so only a file's owner can
# delete it. Both deny all HTTP access via their own .htaccess. See docs/SERVERS.md.

echo "uploading, then setting permissions. One connection, so Duo asks once..."
tar czf - -C "$STAGE_DIR" . \
  | ssh "$USERNAME@$HOST" "mkdir -p '$DOCROOT' \
      && tar xzf - -C '$DOCROOT' \
      && cd '$DOCROOT' \
      && find . -type d -exec chmod 2775 {} \; \
      && find . -type f -exec chmod 0664 {} \; \
      && chmod 3777 cache logs"

echo
echo "uploaded. checking it from outside..."
echo

# Verify automatically. Deploying and then not checking is how a broken upload
# sits there for a week, and making the check a separate command somebody has
# to remember is how that happens.
#
# php tools/check.php is deliberately NOT run here: portal has no PHP
# interpreter, so it cannot be (docs/DEPLOY-LOG.md, 2026-08-18). The old
# version of this script printed it as the next step, which sent at least one
# person looking for a broken installation rather than a missing one.
if command -v python >/dev/null 2>&1; then
  PYBIN=python
elif command -v python3 >/dev/null 2>&1; then
  PYBIN=python3
else
  PYBIN=""
fi

if [ -n "$PYBIN" ]; then
  "$PYBIN" tools/verify_deploy.py "$SITE_URL" || true
else
  echo "(no python on PATH - check it yourself:"
  echo "   python tools/verify_deploy.py $SITE_URL )"
fi

echo
echo "Last step: write what happened into docs/DEPLOY-LOG.md - one entry per"
echo "deploy, and the failures are the valuable part."
