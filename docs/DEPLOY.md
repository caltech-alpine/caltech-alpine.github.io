# How to publish the site

**The ordinary case is one command on the server, and it is [§A2](#a2-deploying-from-then-on).**
[`../SECRETARY.md`](../SECRETARY.md) is the same thing written for somebody who
has never done it. This file is the full account: what the command does, how the
server was set up, the laptop route for the day GitHub is unreachable, and the
production cutover.

Read [SERVERS.md](SERVERS.md) if you do not know what `portal.caltech.edu` is.
Write down what happened in [DEPLOY-LOG.md](DEPLOY-LOG.md) when you are finished,
including the parts that failed — those are the valuable entries.

**Nothing here touches `alpine.caltech.edu`.** As of 2026-08-30 that hostname
still serves the Caltech Sites (Wagtail) page and nothing in this repository can
reach it. What this publishes to is `staging.alpine.caltech.edu`, a separate
document root. The worst outcome of getting a step wrong is a broken staging
page. Moving production onto this repository is a decision plus an IMSS request:
[the last section](#the-production-cutover).

---

## Two ways to publish, and which to use

**From the server — this is the way.** Log into `portal.caltech.edu` with PuTTY
and run one command. The server fetches the code from GitHub itself and
publishes it. Nothing is needed on your own computer beyond PuTTY and the VPN,
which is the entire point: **the ability to publish must not depend on one
person having a laptop set up correctly**, because that person graduates.
→ §A

**From your own machine — the fallback.** `tools/deploy.sh` pushes the files over
SSH from a clone on your laptop. It needs Git Bash, a clone, the VPN on "Tunnel
All", and a password plus Duo. Two reasons to use it and no others: bootstrapping
a brand-new server, and the day GitHub itself is unreachable. → §B

Both publish the same files and set the same permissions. Neither can touch
`alpine.caltech.edu`.

---

# §A. Deploying from the server

## A0. One authentication instead of a dozen

```bash
python tools/portal_daemon.py
```

Asks for your Caltech password, then Duo. Approve it on your phone once and
leave the window open; from then on anything can run a server command with

```bash
python tools/portal_daemon.py --run "whoami"
```

without another prompt. `--status` says whether it is up, `--stop` closes it,
and it closes itself after four hours idle or when you close the window. Your
password is never stored or written anywhere — it goes straight into the ssh
handshake.

This exists because ssh's own `ControlMaster` cannot work from Git Bash: the
Unix-socket emulation cannot pass file descriptors, so the master accepts
`-O check` and then refuses to carry a session (DEPLOY-LOG, 2026-08-18). The
daemon is the same pattern the HPC monitor in `1Research/HEA/vasp` has used for
months, cut down to one file with nothing VASP-shaped in it.

**It is a real grant of access while it runs** — anything on your machine that
can reach `127.0.0.1` and read the token file can run commands on portal as
you. It listens on loopback only, requires a random token, and expires. Stop it
when you are done.


## A1. One-time setup

Done once, by somebody in the `alpinewww` group, and never again. **The repo has
to be pushed to GitHub first**, because this clones from there.

Log in — PuTTY, or `ssh YOUR_USERNAME@portal.caltech.edu` — and paste this as
one block:

> **Doing several server commands in a row?** Every ssh to portal costs a
> password and a Duo push, and this section is a dozen commands. Start
> `tools/portal_daemon.py` first: it authenticates **once**, holds the session,
> and runs everything after that for free. See §A0.

```bash
SITE=/srv/www.alpine.caltech.edu/www
chmod 2775 "$SITE"
git clone https://github.com/caltech-alpine/caltech-alpine.github.io.git "$SITE/repo"
mkdir -p "$SITE/bin" "$SITE/backups"
chmod 2775 "$SITE/bin" "$SITE/backups"
printf '#!/usr/bin/env bash
set -e
SITE=/srv/www.alpine.caltech.edu/www
git -C "$SITE/repo" fetch --quiet origin
git -C "$SITE/repo" reset --hard origin/main
exec "$SITE/repo/tools/server-deploy.sh" "$@"
' > "$SITE/bin/deploy"
chmod 775 "$SITE/bin/deploy"
ls -la "$SITE"
```

What that builds, beside the live site:

```
/srv/www.alpine.caltech.edu/www/
├── docroot/     the live site, served over HTTP
├── repo/        a git clone. NOT under docroot, so .git is never web-readable
├── backups/     the last five copies of docroot, made automatically
└── bin/deploy   the one command
```

`chmod 2775` on those folders is what lets **any** member of `alpinewww` use and
maintain them, rather than only the person who set it up.

## A2. Deploying, from then on

```bash
/srv/www.alpine.caltech.edu/www/bin/deploy
```

That is the whole procedure. `bin/deploy` resets the server's checkout to match
GitHub's `main` and then runs [`tools/server-deploy.sh`](../tools/server-deploy.sh)
out of it, so the deploy logic is version controlled and updates itself: change
that script, push, and the next deploy runs the new one.

It does four things, and the last three are there because they are the ones a
human skips.

**1. It refuses to publish a commit GitHub has not passed.** Before anything
else it asks GitHub whether the workflow run for this exact commit succeeded.

| Answer | What happens |
|---|---|
| the push run succeeded | publishes |
| the push run failed | **refuses**, and prints the link to the failure |
| GitHub has not finished | **refuses**, and says to wait a minute |
| GitHub cannot be reached | publishes, with a warning |

That last row is deliberate: the gate **fails open**. A GitHub outage must not
be the reason the club cannot update its own website. `--force` overrides the
other two, and is for emergencies.

It asks about the *push* run specifically
(`/actions/runs?head_sha=...&event=push`), not about the commit's check-runs.
The obvious endpoint is the wrong one: this repository's workflow also runs on a
half-hourly schedule, so a commit that has been on `main` for a day carries
dozens of check-runs and usually has one queued at any moment. A gate reading
that list would have refused to deploy for most of every hour. Measured
2026-08-30: 30 check-runs on one commit, one of them queued.

**2. It backs up the document root** into `backups/`, keeping the newest five.

**3. It publishes, and writes `docroot/version.txt`** — the commit, the subject,
the time and who ran it. That file is readable at
`https://staging.alpine.caltech.edu/version.txt`, which is how anybody can find
out what is actually live without logging in.

**4. It fetches the public address and checks the change landed**, printing
whether the site is now serving the commit it just published. Publishing and
verifying are one action because the second one is the one that gets skipped:
on 2026-08-28 a verifier reported 23 checks and 0 failures against a staging
copy that was weeks out of date.

Everything it publishes comes from GitHub. Anything edited by hand inside
`repo/`, or inside `docroot/`, is discarded without a prompt.

The home page check gets **three tries, five seconds apart**. Added 2026-09-02,
after it reported "the home page did not come back as expected" on a deploy that
was entirely healthy: the rsync leaves an empty calendar cache, so the very first
request has to call Google before it can render, while `version.txt` is a static
file and answers instantly. One shot right after a publish measures the warm-up
rather than the deploy.

## A2b. `tools\publish.bat` — the same command, without PuTTY

**A shortcut for somebody who already has an SSH key, not a second procedure.**
§A2 above is what the club depends on, because it needs nothing but PuTTY and
survives the secretary's laptop being reinstalled. Read A2 first; this is A2 with
the typing removed.

Double-click **`tools\publish.bat`** (or run it from a terminal, which is the
only way to pass it an argument). It logs into `portal` with the `.ppk` already
in `%USERPROFILE%\.ssh`, runs `bin/deploy`, and prints back everything the server
said. **No password and no Duo push — Duo gates password authentication, not
public keys.** Arguments pass straight through, so `publish.bat --rollback` and
`publish.bat --force` do what §A3 and §A2 describe.

What it checks before it connects, because each one has cost somebody an
afternoon:

| It stops when | Because |
|---|---|
| the working tree has uncommitted changes | the server publishes GitHub, so publishing now would put the **old** code back. It prints `git status --short` and the commit-and-push line |
| the working tree is ahead of `origin/main` | same trap, one step later. It lists the unpushed commits and asks before continuing |
| `portal` will not accept the key | it prints what the connection actually said, then names the VPN — off campus *and* the default split-tunnel profile both fail, and both look like the server being down |
| `plink.exe` or the key is missing | it says which file it looked for, and points at §A2 and [ACCESS.md](ACCESS.md) instead of failing obscurely |

Two details worth not undoing. **The host key is pinned in the file** (`-hostkey
SHA256:0kAZ/…`, read off the server with `plink -v` on 2026-09-02) so a first run
is never asked to trust an unknown key and a *changed* key stops the publish
rather than being waved through — if it ever complains, find out why before
editing the pin. **The username comes from `.deploy-user`**, the same gitignored
file `tools/deploy.sh` and `tools/portal_daemon.py` already read, so the next
person's is theirs and no name is hard-coded in a tracked file.

It writes the whole transcript to `%TEMP%\alpine-publish.txt`, which is what you
paste into [DEPLOY-LOG.md](DEPLOY-LOG.md).

This does not replace `tools/portal_daemon.py` (§A0). The daemon exists for a
*run* of server commands on one authentication — setting the site up, poking at
permissions. Publishing is one command, so a key is simpler than a session.

## A3. Rolling back

```bash
/srv/www.alpine.caltech.edu/www/bin/deploy --rollback
```

Puts the copy taken before the last deploy straight back. That is the whole
recovery path at the moment somebody needs it.

Then fix it properly on GitHub — undo the change, wait for the green tick, and
deploy again. **The rollback is a patch, not a fix:** the next ordinary deploy
publishes whatever is on `main`, so if the bad change is still there it comes
straight back.

> ⚠ **The older instruction here did not work.** It said to
> `git -C .../repo checkout <commit>` and then run `bin/deploy`. It cannot: the
> `bin/deploy` wrapper runs `git reset --hard origin/main` before it reads
> anything else, so the checkout was discarded one line before the deploy
> script saw it. Found and fixed 2026-08-30, when `--rollback` was added. If you
> genuinely need an arbitrary old version rather than the last one, put it back
> on GitHub — that is the source of truth, and doing it there means the next
> deploy agrees with you instead of undoing you.

## A4. Why not have it deploy itself on every push

GitHub's runners are on the public internet and `portal.caltech.edu` answers
only from campus or the VPN, so a GitHub Action can never reach it. A scheduled
pull on the server would work, but it would publish whatever is on `main`
without anyone looking. For a site four people edit occasionally, one deliberate
command is better than a robot.

---

# §B. Deploying from your own machine

> **This is not the normal way to publish, and has not been since 2026-08-19.**
> §A is. Use this only to bring up a **new server**, or on a day GitHub itself
> is unreachable. It needs a clone, Git Bash and a laptop set up correctly — the
> three things §A exists to stop the club depending on.
>
> Steps 1 and 2 are the *first-server* steps and were done once, on 2026-08-18.
> They are kept for the next server, or for the day production gets a document
> root of its own. If you are publishing a change, you want §A.
>
> This route has **no check gate, no version stamp and no `--rollback`**. Those
> live in `tools/server-deploy.sh`, which only §A runs.

## 0. Before you start

- [ ] You are on campus, **or** on the Caltech VPN with **Tunnel All** selected.
      Split tunnelling cannot reach `portal.caltech.edu`, and the error looks
      like the server is down rather than like a VPN problem.
- [ ] You can log in with your Caltech account, and you are in the `alpinewww`
      group. If either is missing, or you are setting this up for the first
      time, [ACCESS.md](ACCESS.md) is the whole procedure including PuTTY.
- [ ] Your working copy is committed and clean: `git status` says nothing.
      Deploying uncommitted edits means what is on the server exists nowhere
      else.
- [ ] You have thirty minutes. A half-finished upload is a broken site.

Check the connection before anything else:

```bash
ssh YOUR_USERNAME@portal.caltech.edu
```

If that hangs or is refused, the VPN is the first thing to suspect. Nothing
below will work until it succeeds.

---

## 1. Find the document root

IMSS documents the pattern as `/srv/www.SITE.caltech.edu/www/docroot/`, but our
hostname has no `www.` in it, so the folder name is a guess until you look. In
the SSH session:

```bash
ls -la /srv/ | grep -i alpine
```

**Done once, on 2026-08-18.** There is exactly one folder,
`/srv/www.alpine.caltech.edu/`, owned by `khunady`, group **`alpinewww`**, and
the docroot inside it is already mode `2775` with the setgid bit set. The steps
below are kept for the next server, or for the day production gets its own
folder.

Then confirm the docroot itself and note who owns it:

```bash
ls -la /srv/www.alpine.caltech.edu/www/docroot/
stat -c '%U %G %a' /srv/www.alpine.caltech.edu/www/docroot/
```

The group name matters: it is the group every uploaded file should belong to, so
that the next officer can edit them.

**Write the real path and the group into [SERVERS.md](SERVERS.md) now**, while
you are looking at them. That file currently says the path is unverified.

---

## 2. Send one file first, not the whole site

Uploading a 300-file site to a server you have never used produces one useless
error message. Send a single probe page instead. It answers, in one screen,
every open question in `SERVERS.md`.

```bash
scp tools/probe.php YOUR_USERNAME@portal.caltech.edu:/srv/www.alpine.caltech.edu/www/docroot/_probe.php
```

Then open <https://staging.alpine.caltech.edu/_probe.php>.

| What you see | What it means |
|---|---|
| A page of green and red checks | PHP works. Read the rows. |
| The PHP **source code** as text | PHP is not enabled for this document root. Stop and ask IMSS. |
| A download prompt | Same problem: PHP is not being executed. |
| 404 | Wrong document root. Go back to step 1. |
| 403 | The file permissions are wrong. See step 4. |
| 500 | Most likely the probe itself. It was written on a machine with no PHP installed and has never been run through an interpreter, so a typo in it is possible. Fix or delete it; it says nothing about the server. |

The rows to care about are the PHP version (7.4 or newer), one of cURL or
`allow_url_fopen` being available, and whether `.htaccess` is being read. The
last one is reported by the probe as a hint only; step 5 tests it properly.

**Delete the probe when you are done with it.** It reports server details that
no visitor needs.

```bash
ssh YOUR_USERNAME@portal.caltech.edu 'rm /srv/www.alpine.caltech.edu/www/docroot/_probe.php'
```

---

## 3. Upload the site

```bash
./tools/deploy.sh --dry-run
```

That stages a clean copy under `_deploy/` and prints the file list without
sending anything. Look at the list. Then:

```bash
./tools/deploy.sh
```

The first run asks for your Caltech username and writes it to `.deploy-user`,
which is git-ignored; after that it never asks again. It refuses to run on an
uncommitted working copy, and prints the `git commit` you need. It checks
`portal.caltech.edu:22` before anything else, because without the VPN on "Tunnel
All" the upload would otherwise just hang. Upload and permissions go over one
ssh connection, so Duo prompts once rather than twice. When it finishes it runs
step 5 for you.

The script exists so that the excluded files stay excluded. What never goes to
the server, and why:

| Not uploaded | Reason |
|---|---|
| `.git/`, `.github/` | The repository is not the website |
| `_site/`, `_preview/` | Build output for the Pages pilot |
| `docs/`, `README.md`, `SECRETARY.md` | Documentation, not pages for visitors. `.htaccess` denies `.md` anyway; this keeps them off the server entirely |
| `cache/*` | The server writes its own; overwriting it is how you serve yesterday's calendar |
| `includes/config.local.php` | The server's copy is the one holding any key. **Overwriting it is the one mistake here that is hard to undo.** |
| `logs/*` | Written on the server |
| `tools/*.py`, `tools/route.json` | Maintenance scripts, no reason to publish them |

`tools/check.php` **is** uploaded, because it is useful to run from the server,
and `tools/.htaccess` blocks it from being reached over the web.

If `scp` fails partway through, run the script again. It overwrites; it does not
merge, and it never deletes anything on the far side. Removing a file from the
server is a manual `rm`.

---

## 4. Fix the permissions, once

IMSS asks for `0664` on files and `2775` on folders. The setgid bit in `2775` is
what makes new files inherit the group, so the next officer can edit what you
uploaded. In the SSH session:

```bash
cd /srv/www.alpine.caltech.edu/www/docroot/
find . -type d -exec chmod 2775 {} \;
find . -type f -exec chmod 0664 {} \;
chmod 2775 cache logs
```

**`cache/` and `logs/` need to be writable by the web server, and as of
2026-08-18 they are not.** Apache runs as `www-data`, the files are owned by
`alpinewww`, and `www-data` is not in that group. The site still works — it falls
back to calling Google on every page load instead of caching — but it should be
fixed. The clean fix is an IMSS ticket asking for `www-data` to be able to write
here. **`tools/deploy.sh` already applies the workaround** — it sets those two
directories to `3777` on every deploy, so there is nothing to do by hand. World
writable, plus the sticky bit so only a file's owner can delete it. Both
directories deny all HTTP access through their own `.htaccess`, so this is less
exposed than it looks, and `chmod 2775 cache logs` reverts it the day IMSS
answers the ticket.

POSIX ACLs, which would have granted the web server alone, are **not available**:
`/srv` is an Amazon EFS share and `setfacl` returns `Operation not supported`.

**`php tools/check.php` cannot be run.** The older instructions said to run it
on the server, and that turns out to be impossible: `portal` is a file server
with no PHP installed, and the machine that actually serves the site is a
different one we have no shell on. See [SERVERS.md](SERVERS.md).

What replaces it: `tools/probe.php` in step 2 for the server's own view, and
`tools/verify_deploy.py` in step 5 for everything visible over HTTP. Neither
covers the config-link audit that `check.php` does. If that becomes worth
having, the fix is to give `check.php` an HTML mode and reach it over the web
behind a key, the way `preview.php` already works.

---

## 5. Verify from outside

**Both deploy routes check the site themselves at the end.** Run this by hand
when you want to check without deploying — from your own machine, off the VPN if
you can:

```bash
python tools/verify_deploy.py https://staging.alpine.caltech.edu
```

Add `--expect HEAD` inside a clone to assert that the server is running the
commit you are sitting on. It reads that from `version.txt`, which §A2 writes.

It checks every page returns 200, that the pages are HTML rather than PHP source,
that events rendered, that `.htaccess` is in force (the security headers arrive,
`/data/assignments.csv` is denied, and a folder with no index does not list its
contents), that the 404 page is ours, and that staging is telling search engines
to stay away.

A red line means read it, not that the site is broken. The most common one is
`.htaccess` being ignored because `AllowOverride` is off, which is an IMSS
request, not something to fix in this repository.

Then look at the site with your eyes. A script cannot tell you that a photograph
is upside down.

---

## 6. Write down what happened

Add an entry to [DEPLOY-LOG.md](DEPLOY-LOG.md): the date, what you uploaded, what
worked, what did not, and anything you had to look up. The entries that matter
most are the failures. The next person to deploy will be doing it for the first
time.

---

## If something goes wrong

| Symptom | Where to look |
|---|---|
| Every URL is a redirect loop | Something added a force-HTTPS rule. See [SERVERS.md](SERVERS.md), "Never force HTTPS in `.htaccess` on this host". |
| PHP source is shown as text | PHP is not enabled for this document root. IMSS ticket. |
| Directory listings appear | `.htaccess` is being ignored. `AllowOverride` is off. IMSS ticket. |
| No events on the page | The calendar fetch failed, or the calendar has no future events. `php tools/check.php` on a laptop distinguishes the two; it cannot be run on the server. |
| The site is slow | `cache/` is not writable, so every visitor triggers a call to Google. §B step 4. |
| A page 403s | Permissions. §B step 4. |
| "Connection refused" from `scp` or PuTTY | The VPN is not on Tunnel All. |
| The site is not showing a change that was published | Read `<site>/version.txt`. It says which commit is live. If that is not the one you expected, the deploy did not happen or did not finish. |
| `bin/deploy` refuses to publish | Read what it says. Either GitHub's checks failed on that commit, or they have not finished yet. Both are described in §A2. |

### Rollback

**`bin/deploy --rollback`**, on the server. See [§A3](#a3-rolling-back), which
also records why the older instruction in this place — checkout an old commit,
then deploy — could never have worked.

Two things underneath it, in order of how much you will wish you had used them:

1. **The five automatic copies in `backups/`.** `--rollback` restores the newest.
   An older one can be copied back by hand.
2. **Do nothing.** Until the production cutover, staging is not the club's site.
   `alpine.caltech.edu` is untouched, and a broken staging page harms nobody.

---

## The production cutover

Not a deployment step. A decision, and it belongs to the officers rather than to
whoever holds the SSH key. [HOSTING.md](HOSTING.md) §3 and §5 are the argument;
this is the checklist for the day it is made.

- [ ] Staging has run clean for long enough that somebody other than the author
      has clicked through it.
- [ ] The officers have agreed to replace the Caltech Sites version.
- [ ] IMSS has been asked what happens to `alpine.caltech.edu`: repoint the
      hostname, or a second document root. Their answer decides the target.
- [ ] Office of Strategic Communications has been asked whether a club site off
      the Caltech Sites template is acceptable at a `caltech.edu` hostname.
      Cheap to ask now, expensive to discover afterwards.
- [ ] **Somebody who did not build this has done the procedure**, start to
      finish, from [`../SECRETARY.md`](../SECRETARY.md) alone, without asking
      anyone. Officer turnover is annual and this is the step that decides
      whether the site survives it.
- [ ] `noindex` comes off production and stays on staging. Two indexable copies
      of the same site compete with each other in search results.
- [ ] The Wagtail site is left in place, not deleted, until the new one has been
      live for a term.

**Three things in this repository change on the day, and only three.**

- [ ] `URL=` at the top of [`../tools/server-deploy.sh`](../tools/server-deploy.sh) —
      the one place the published address is written. The deploy prints it and
      smoke-tests it, and `SECRETARY.md` deliberately never repeats it, so this
      single line is what keeps every document honest.
- [ ] `site.url` in [`../includes/config.php`](../includes/config.php), which
      sets the canonical link.
- [ ] The "which address is which, today" table at the top of
      [`../SECRETARY.md`](../SECRETARY.md), and the matching one in
      [`../README.md`](../README.md).

Nothing else. `.htaccess` needs no edit: the `noindex` header is keyed on the
hostname starting `staging.`, so the same committed file is already correct on
both.
