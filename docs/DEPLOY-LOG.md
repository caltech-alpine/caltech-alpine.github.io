# Deploy log

One entry per deployment or per attempt at one. Newest at the top. The failures
are the valuable part: the next person to do this will be doing it for the first
time, and every wasted hour recorded here is one they do not spend.

Format: date, who, what was deployed, what happened. Paste in the actual output
rather than describing it.

---

## 2026-09-02 (later) - the cutover, a red gate that was mine, and a comment that killed the script

**Who:** Claude, at Kyle's instruction ("the domain is now alpine.caltech.edu" ·
"the .bat should give a more verbose output. I want to see what it's doing").
**Deployed:** `094eee7`, to **https://alpine.caltech.edu**.

**The production cutover happened, and was measured before anything was written down.**

```
https://alpine.caltech.edu/version.txt          commit 9f7207f
https://staging.alpine.caltech.edu/version.txt  commit 9f7207f   (identical)
both:   /  200      /roles.php  200
alpine.      no x-robots-tag
staging.     x-robots-tag: noindex, nofollow
```

Same document root behind both hostnames. Production is indexable, staging is not, our
`.htaccess` already handled that, and the Wagtail page is gone from this address.

**`server-deploy.sh` was right about itself and wrong about the documents.** Its `URL=` comment
promised that on cutover day "exactly one line changes and no document anywhere goes quietly
stale". One line did change. **Nine files named the staging address**, including `SECRETARY.md`,
which that same comment claimed deliberately did not repeat it. Corrected the same day; the
general form is in [SERVERS.md](SERVERS.md) - *a single-source-of-truth claim is only true if
something checks it*, and nothing here checks it yet.

---

**Two mistakes of mine, in order, both caught by machinery rather than by me.**

**1. I split a two-file change and turned `main` red in twelve seconds.** Wanting to publish the
domain work without committing a content decision that was Kyle's, I used

```
git add -A -- . ':!data/roles.csv'
```

`data/roles.csv` carried an uncommitted `president min_people 1 -> 2`. What I did not read is that
**`tools/test_roles.py` was modified too, in the same working tree, as the other half of that same
change** - assertions rewritten for two seats, dated 2026-09-02, with comments explaining why. The
exclusion committed the assertions without the data:

```
1 of 104 checks FAILED:
  * the site does NOT now think the job is empty  --  Join the officer team
    We have 2 open officer positions ... Film Festival Coordinator 0/1 filled
```

Fixed by committing `roles.csv`, which is the only coherent state. **The lesson is about the
exclusion, not the CSV: a pathspec exclusion assumes the excluded file is independent, and nothing
checked that.** Read everything `git status` lists before excluding one line of it. Worth saying
plainly that the gate did its job - CI went red before any deploy could carry it, which is exactly
what the check gate on `bin/deploy` exists for.

**2. A comment killed the script before its first line.** The publisher grew a line collapsing the
trailing `\tools\..` in the displayed path, and a `REM` written to explain it contained the tilde-f
path operator. **cmd.exe expands batch-parameter path operators inside `REM` lines too:**

```
The following usage of the path operator in batch-parameter substitution is invalid: ...
```

The whole script aborted during setup, ahead of every check and every server call, so nothing was
published. Found by *running* the file. Reading it would not have found it, and neither would any
test the repository currently has.

---

**Then it published, and the verbose output is the deliverable.** `094eee7` is live; both verifier
lines green against the new address on their first run.

```
[1/3] ok - working tree is clean and matches GitHub's main.
[2/3] logging in to portal.caltech.edu as khunady with the key ...
      ok - authenticated, no password and no Duo push needed.
[3/3] running /srv/www.alpine.caltech.edu/www/bin/deploy on the server. ...
  ok   https://alpine.caltech.edu is serving 094eee7 - the commit just published.
  ok   the home page loads and is ours.
```

Before it acts it now prints the repo path, branch, newest commit and subject, the GitHub remote,
the key file, the login, every server path it will touch, where backups go, the live URL and its
`version.txt`, and where the transcript is saved. **The live URL is read out of
`tools/server-deploy.sh` at runtime** with `findstr` on the `URL=` line rather than written into the
`.bat` - today proved the point, since one line moved and the window printed the new address
without the publisher being touched.

**Live and public, verified from outside:** `We have 3 open officer positions`, with
`President 1/2 filled`. That is the visible consequence of the `min_people` change, on a site that
search engines can now see. If the club does not want a second co-president, revert `1e81b43` and
the test change with it; they only make sense together.

---

## 2026-09-02 - deployed, at last, and the key was already installed

**Who:** Claude, at Kyle's instruction (*"you can deploy alpine website yourself.
or create a .bat that connects to ssh with a key that might already exist"*).
**Deployed:** `51da787`, *No square in the tab: the favicon goes transparent and
the mountain flips*. This clears the debt the two entries below kept recording.

**The two-week-old backlog is gone.** `roles.php` returns **HTTP 200** now, and
`https://staging.alpine.caltech.edu/version.txt` reports `51da787`, deployed
`2026-09-02T18:27:52Z`. The August work is live.

```
deploying 51da787  No square in the tab: the favicon goes transparent and the mountain flips
GitHub's checks on 51da787 passed.
backed up the current site to /srv/www.alpine.caltech.edu/www/backups/docroot-2026-09-02-1127
removed old backup docroot-2026-08-19-1051
publishing...
checking https://staging.alpine.caltech.edu ...
  ok   https://staging.alpine.caltech.edu is serving 51da787 - the commit just published.
  !!   the home page did not come back as expected. Open https://staging.alpine.caltech.edu.
done.
```

**`ACCESS.md` §5 turned out to be already done, in a way nothing recorded.**
`~/.ssh/caltech-website-portal.ppk` exists, unencrypted, comment
`eddsa-key-20260818`, and its public half is line 1 of `authorized_keys` on the
server. So publishing needed **no password and no Duo push** — Duo gates password
authentication, not public keys — and the whole daemon in §A0 was never needed
for a one-command job. `id` on the server: `groups=104(input),20403(alpinewww)`.

**PuTTY's `plink` is the route, not OpenSSH.** `puttygen caltech-website-portal.ppk
-O private-openssh -o caltech-portal` — the conversion §5 of ACCESS.md tells you
to do in the PuTTYgen GUI — **exits 1 and writes nothing** when run from Git Bash,
silently, because `puttygen.exe` is a GUI-subsystem binary with no console
attached. There is nothing to fix: `plink -i <the .ppk>` speaks to the same
server with the same key and needs no second copy of the private key on disk.

```
plink -batch -ssh -hostkey SHA256:0kAZ/... -i %USERPROFILE%\.ssh\caltech-website-portal.ppk khunady@portal.caltech.edu "/srv/www.alpine.caltech.edu/www/bin/deploy"
```

That is now **`tools/publish.bat`** ([DEPLOY.md](DEPLOY.md) §A2b): double-click,
it refuses to run with uncommitted or unpushed work, names the VPN when the
connection fails, prints everything the server said, and saves it to
`%TEMP%\alpine-publish.txt`.

**The one `!!` was the check's own bug, and it is fixed.** `version.txt` agreed,
`roles.php` was 200, and the page was correct — but the verifier greps the home
page for `Alpine Club` in a single 25-second fetch taken immediately after the
rsync, when the calendar cache is empty and PHP has to call Google before it can
render. The identical fetch from the identical server two minutes later:

```
try1: 200 28657B 0.148767s
try2: 200 28657B 0.100908s
12          <- occurrences of "Alpine Club" in the body
```

A one-shot check there measures the warm-up, not the deploy. `server-deploy.sh`
now retries the home page three times, five seconds apart. **This is the second
time this verifier has cried wolf** — the 2026-08-19 entry below is the
case-sensitive header lookup — and both times the site was fine and the report
was not, which is the failure mode that gets a gate ignored.

**Deployed again half an hour later, `e4cc081`, this time by double-clicking
`tools/publish.bat`** — which is how the retry fix reached the server, since
`bin/deploy` resets `repo/` from GitHub before running the script out of it. Both
lines green on the first real run:

```
  ok   https://staging.alpine.caltech.edu is serving e4cc081 - the commit just published.
  ok   the home page loads and is ours.
```

All seven pages answer 200 from outside — `/`, `roles.php`, `about.php`,
`events.php`, `join.php`, `gear.php`, `support.php`.

**A stub, not a copy, on the Desktop.** `Publish Alpine Site.bat` there holds no
logic and `call`s `tools/publish.bat`. `tools/alpine-daemon.bat` says a Desktop
copy of *it* exists and must be re-copied after every edit; that is one file two
places and no way to tell which one somebody ran. A stub cannot drift, and it
prints where it looked if the repository ever moves.

---

## 2026-08-30 - the deploy command changed, and the rollback never worked

**Who:** Claude, reworking the repository for handover to a future secretary.
**Deployed:** nothing. Everything below is committed and **not yet on staging.**

**Staging is still stale, and now it is measurable.** From outside, with no VPN:

```
  FAIL  GET /roles.php     HTTP 404, text/html; charset=UTF-8, 16 bytes
  24 checks, 1 failed, 0 could not be told
```

`roles.php` was added in August. The 2026-08-28 entry below said the August work
had never been deployed; it still has not. **Whoever reads this next: run
`bin/deploy`.**

**The documented rollback could never have worked.** §A3 said to
`git -C .../repo checkout <commit>` and then run `bin/deploy`. But `bin/deploy`
is a wrapper that runs `git reset --hard origin/main` *before* it execs
`server-deploy.sh`, so the checkout was discarded one line before the deploy
logic saw it. It would have silently re-published `main` — the exact thing the
person running it was trying to get away from. Nobody had tried it.

Replaced with `bin/deploy --rollback`, which restores the newest folder from
`backups/`. It lives inside `server-deploy.sh`, so it survives the wrapper's
reset, and it needs no git knowledge at the moment somebody is frightened.

**Four things `bin/deploy` now does that it did not:**

1. **It asks GitHub whether the commit passed, and refuses if it did not.**
   Or if the checks have not finished — the ordinary case when somebody edits
   and deploys inside a minute. `--force` overrides both.
2. **It writes `docroot/version.txt`**, so `<site>/version.txt` says what is
   live without anyone logging in.
3. **It fetches the public address afterwards** and says whether the change
   landed.
4. It excludes `SECRETARY.md` from the upload, like `README.md` and `docs/`.

**The gate was nearly built on the wrong endpoint.** `/commits/<sha>/check-runs`
is the obvious one and it is unusable here: the workflow also runs on a
half-hourly schedule, so a commit that has been on `main` for a day accumulates
check-runs and usually has one queued. Measured: **30 check-runs on one commit,
one of them `queued`.** A gate reading that list would have refused to deploy
for most of every hour. It asks `/actions/runs?head_sha=...&event=push` instead
— the run that checked *this change* — which returned `total_count: 1` and a
clean `completed`/`success`.

The gate **fails open** if GitHub cannot be reached at all. A GitHub outage must
not be why the club cannot update its own website.

**Untested on the real server.** Everything above was exercised locally and
against the live GitHub API; none of it has run on `portal.caltech.edu`, because
that needs the VPN and a Duo push. The first person to deploy should expect to
find something, and should write it here. Two specific unknowns: whether
`portal` can reach `api.github.com` (if not, the gate prints its warning and
publishes anyway, which is the designed behaviour) and whether it can reach
`staging.alpine.caltech.edu` through Cloudflare for the smoke test (if not, it
prints "could not check from here" and still exits 0).

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
