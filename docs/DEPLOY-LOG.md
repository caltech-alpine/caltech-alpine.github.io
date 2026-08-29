# Deploy log

One entry per deployment or per attempt at one. Newest at the top. The failures
are the valuable part: the next person to do this will be doing it for the first
time, and every wasted hour recorded here is one they do not spend.

Format: date, who, what was deployed, what happened. Paste in the actual output
rather than describing it.

---

## 2026-08-28 - the deploy that did not happen, and the port that let it

**Who:** Kyle ran the commands; Claude diagnosed afterwards.
**Deployed:** nothing. **Result: the daemon talked to a different daemon.**

`python tools/portal_daemon.py --run "…/bin/deploy"` printed:

```
unknown method None
```

That string is not in this repository. It came from
`hpc_monitor/nersc_daemon.py` in `1Research/HEA/vasp`, which was already
listening on port 19923 and speaks `method` where this one speaks `op`.

**Why the bind guard did not catch it.** `portal_daemon.py` set
`SO_REUSEADDR` before binding a fixed 19923. On Linux that only clears
TIME_WAIT. On Windows it lets a socket bind a port another **live** socket is
already listening on, so the bind succeeded, the `except OSError` never fired,
19923 went into the state file, and every `--run` reached the wrong process.
The daemon was running, `--status` would have said so, and nothing was wrong
except the answers.

Fixed the same day: `LISTEN_PORT = 0`, the real port read back with
`getsockname()`, and `SO_REUSEADDR` removed. A collision is now impossible by
construction.

**The second failure, and the worse one.** `verify_deploy.py` then reported
**23 checks, 0 failed** against a staging copy that was several weeks stale:
`/roles.php` returned **404**, `assets/images/logo.svg` and
`assets/images/officers/julian-schmitt.jpg` both 404, and `about.php` still said
"Getting people outside". It passed because `PAGES` never listed `roles.php`.
A verifier cannot tell you a page is missing if it does not know the page
exists. `roles.php` added to `PAGES`, with a note to add a row whenever a page
is.

**Still owed:** staging has not had any of the August 2026 work. Deploy it.

---

## 2026-08-19 (later) - first deploy through the daemon: 21 of 21

**Who:** Kyle authenticated; Claude ran the commands over `tools/portal_daemon.py`.
**Deployed:** `0b06ab7` to staging, from the server (§A). **Result: clean.**

```
21 checks, 0 failed, 0 could not be told
```

Everything the earlier entry left open is now closed. `X-Robots-Tag: noindex,
nofollow` arrives, so staging is out of the search index. The "calendar last
checked" stamp reads `10:53am` with a `-07:00` offset instead of seven hours
ahead. The calendar refresh is five minutes. Kyle's `[test] test event` renders,
10:00-11:00 AM, alongside the weekly run - two upcoming events.

**§A1 turned out to have been done already**, on 2026-08-18 at 18:40: `repo/`,
`bin/deploy`, `backups/` and a populated `docroot/` were all there. That was the
undocumented deploy the earlier entry noticed. Nothing needed setting up; what
needed doing was fixing the two ways `server-deploy.sh` died.

**Both failures were the same mistake in two places: acting on files the web
server owns.** `cache/` and `logs/` are mode 3777 so that www-data - which is
not in `alpinewww`, and is on a different machine - can write there. What that
does *not* give us is the right to read or chmod what it wrote.

1. **Backup, line 43.** `cp -a` over the whole docroot hit `logs/.salt`, mode
   0600 and owned by www-data. `cp: cannot open ... Permission denied`, and the
   deploy stopped **before publishing**. Now an `rsync -a` that excludes both
   directories. Neither is worth backing up: the cache regenerates on the next
   page view and the publish step never touches either.
2. **Permissions, line 100.** `find "$DOCROOT" -type f -exec chmod 0664` hit the
   same files. This one stopped the deploy **after publishing**, which is worse -
   the site was live and correct while the script reported failure. The sweep now
   prunes the contents of both directories; the directories themselves are ours
   and still get their mode.

The `trap ... ERR` added on 2026-08-18 earned itself here: both failures printed
`FAILED at line NN` and were fixed in one pass each. Without it they would have
been silent exits.

**Two notes for whoever runs this next.**

- **From Git Bash, prefix a `--run` with `MSYS_NO_PATHCONV=1`.** Otherwise MSYS
  rewrites the leading `/srv/...` into `C:/Program Files/Git/srv/...` before
  Python ever sees it, and the error - `bash: line 1: C:/Program: No such file
  or directory` - looks like a broken server rather than a mangled argument.
- There is a **partial backup folder** in `backups/` from the run that died at
  line 43. Harmless: the retention keeps the newest five and ages the rest out.
  It was left alone rather than cleaned with `rm -rf`, which is not a command to
  aim at a glob on a shared university server.

**Next:** stop the daemon when it is no longer needed
(`python tools/portal_daemon.py --stop`; it also expires after four hours idle).
The `~/.ssh/alpine-portal` key from 2026-08-18 no longer exists on this machine,
so the revocation item from that entry is moot - but **the line may still be in
`~/.ssh/authorized_keys` on the server** and should be checked.

## 2026-08-19 - audit from outside: the site is up, and the log had missed it

**Who:** Kyle, with Claude checking from the public internet only - no VPN, no
SSH, no key needed. **Deployed:** nothing today.

**The site is live.** Between the entry below and this one, somebody uploaded
the whole site and did not log it. `staging.alpine.caltech.edu` now serves the
real PHP application: `x-powered-by: PHP/8.2.29`, all eight pages HTTP 200, the
protected paths 403, our own 404 page.

```
21 checks, 1 failed, 0 could not be told
```

The one failure is real: **the staging copy is indexable.** No `X-Robots-Tag`,
and `robots.txt` says `Allow: /`, which contradicts SERVERS.md's own rule that
staging must never be indexed. Fixed in `.htaccess` with a host-conditional
`Header set X-Robots-Tag "noindex, nofollow"`, so the same committed file stays
correct on production. **Not yet deployed - it lands with the next upload.**

Two things the previous entry expected to be uncertain are now settled:

- **`.htaccess` is read.** `AllowOverride` is on: all three security headers
  arrive. The earlier `0 of 3` was `verify_deploy.py`'s own bug - it looked
  headers up case-sensitively and Cloudflare sends them lowercased. Fixed.
- **Outbound HTTP from the web server works.** The calendar is fetched live
  from Google; no `stale` banner, cache written and reused on the 30-minute TTL.

**The calendar is not stuck.** Checked because it looked like it was:
the page's copy matched the live Google feed exactly - six events, nothing
modified on the calendar since 2026-08-14, exactly one of them in the future
(the recurring Wednesday trail run). The GitHub Pages pilot, built by a
different route entirely, showed the same six. The site can only print what the
calendar holds.

What did look wrong was the **"Calendar last checked" stamp, which read seven
hours ahead** - `date()` uses PHP's default zone, UTC on this host, while every
event time is rendered through an explicit `DateTimeZone` and was always right.
`includes/bootstrap.php` now calls `date_default_timezone_set()` from the
configured timezone, which fixes every plain `date()` call site at once.
**Not yet deployed.**

**Calendar refresh cut from 30 minutes to 5** (`includes/config.php`), on Kyle's
call, after a test event proved the two lags separately: Google published the
edit to the public `.ics` in **under 40 seconds** (`LAST-MODIFIED 16:44:36Z`,
read at 16:45:15Z), so the entire remaining wait was our own TTL. Short is cheap
at this traffic - most visits already fall outside any TTL and pay the Google
round trip regardless. The Pages pilot stays at 30 minutes deliberately;
GitHub's scheduler will not reliably do better. Every doc that claimed "30
minutes" for the PHP cache was corrected in the same pass. **Not yet deployed.**

**Deploying was made a single command**, on Kyle's instruction - *"you need to
make deployment easy and intuitive. give me instructions each time."*
`tools/deploy.sh` now takes no arguments: it asks for the Caltech username once
and remembers it in a git-ignored `.deploy-user`, tests `portal:22` before doing
anything slow (a missing "Tunnel All" VPN used to present as a hang), **uploads
and chmods over ONE ssh connection instead of two, so Duo prompts once**, and
runs `verify_deploy.py` itself at the end. It also stopped printing
`php tools/check.php` as the next step - portal has no PHP, so that instruction
was impossible. On a dirty tree it now prints the exact `git commit` to run.
`DEPLOY.md` §*Two ways*, §3 and §5 updated to match, and a `CLAUDE.md` was added
at the repo root carrying the standing rule that any session changing a file
here ends by handing over the publish commands.

**Next:** deploy, to land the four fixes; then re-run
`python tools/verify_deploy.py https://staging.alpine.caltech.edu` and expect
21 of 21. Also still open: revoke `~/.ssh/alpine-portal` now that the deploy is
proven.

## 2026-08-18 (later) - logged into portal, and the procedure changed

**Who:** Kyle, with Claude driving read-only commands over an SSH key. **Deployed:** still nothing.

The docroot is **`/srv/www.alpine.caltech.edu/www/docroot/`** - one folder, named
for production rather than staging, created 2026-08-17 15:45, owner `khunady`,
group `alpinewww`, mode `2775` with the setgid bit already set by IMSS. Empty.

Four things came out of the session:

1. **`alpinewww` has four members**: `khunady`, `mpfreema`, `zauvil`, `mhannah`.
   Three other people can already deploy this site.
2. **portal has no PHP and no Apache.** RHEL 9.8, nothing listening on 80 or 443.
   The site is served by Apache 2.4.65 on Debian - a different machine.
3. **`/srv` is an AWS EFS share.** Files are written here and read there.
4. **So `php tools/check.php` cannot be run at all**, on either machine.
   `DEPLOY.md` step 4 rewritten to say so instead of instructing something
   impossible.

Still open: which hostname this docroot serves. It is named
`www.alpine.caltech.edu`, but `alpine.caltech.edu` currently returns the Wagtail
site through Cloudflare, so it is not being served there. The probe upload
settles it.

**Access note.** SSH connection sharing (`ControlMaster`) does not work from Git
Bash - the Unix-socket emulation cannot pass file descriptors, so `-O check`
succeeds and an actual session is refused. Three failed password attempts were
logged against the account before that was understood. Replaced with a dedicated
ed25519 key, `~/.ssh/alpine-portal`, installed with the `restrict` option, host
alias `alpine-portal`. **Revoke it when the deploy is proven** - it is full
account access without Duo for as long as the line sits in `authorized_keys`.

## 2026-08-18 - staging provisioned, nothing uploaded yet

**Who:** Kyle. **Deployed:** nothing.

IMSS provisioned `staging.alpine.caltech.edu` under ticket **INC0028327**
(Danny Caballero, comment at 15:15 PDT). Uploads go through
`portal.caltech.edu`, following
<https://www.imss.caltech.edu/services/web-hosting-development/web-hosting/managing-your-dynamic-web-site>.

Checked what could be checked from outside, off campus, before touching
anything. Full detail in [SERVERS.md](SERVERS.md).

- The hostname resolves, through Cloudflare, and HTTPS already works. The
  certificate was issued at 20:50 UTC the same day and renews itself.
- The origin identifies itself as `Apache/2.4.65 (Debian) ... Port 80`. Apache
  means `.htaccess` should be read. Port 80 means TLS is terminated in front of
  it, which is why a force-HTTPS rule must never be added here.
- The document root is **empty**: the server returns its own directory listing
  with nothing in it.

Nothing was uploaded, because the upload needs the VPN on Tunnel All and the
document root path is still a guess (`/srv/www.staging.alpine.caltech.edu/www/docroot/`,
from the IMSS naming pattern, not yet seen).

**Baseline from `tools/verify_deploy.py`**, run against the empty document root
so that there is something to compare the first real deploy against:

```
  ok    GET /                                    HTTP 200, 568 bytes  (Apache's own listing)
  FAIL  GET /index.php ... /sitemap.php          HTTP 404             (nothing uploaded)
  FAIL  home page is not a directory listing     empty document root
  FAIL  security headers arrive (.htaccess read) 0 of 3 present
  --    denied /includes/, /data/, /cache/       HTTP 404, proves nothing yet
  ok    missing page returns 404                 HTTP 404
  FAIL  404 page is ours, not Apache's default   288 bytes
  FAIL  this copy tells search engines away      X-Robots-Tag: (none)

  20 checks, 12 failed, 5 could not be told
```

Every one of those failures is expected on an empty document root. After the
first upload they should all turn green except possibly the `.htaccess` row,
which depends on whether IMSS has `AllowOverride` on.

⚠ `tools/probe.php` has **never been run through a PHP interpreter** - there is
no PHP on the machine it was written on. If it returns a 500, suspect the probe
before suspecting the server.

**Written while this was fresh:** [DEPLOY.md](DEPLOY.md) (the procedure),
[SERVERS.md](SERVERS.md) (the machines), `tools/probe.php` (the one file to
upload first), `tools/deploy.sh` (the upload), `tools/verify_deploy.py` (the
check afterwards).

**Next:** on the VPN with Tunnel All, run DEPLOY.md steps 1 and 2 - find the
real document root, upload the probe, read it, paste the result here.
