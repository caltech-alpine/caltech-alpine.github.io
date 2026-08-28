# How to publish the site

The procedure for putting this repository onto Caltech hosting. Written for
staging; the production cutover is the same procedure with a different target
and an extra approval, and it is at the end.

Read [SERVERS.md](SERVERS.md) first if you do not know what `portal.caltech.edu`
is. Write down what happened in [DEPLOY-LOG.md](DEPLOY-LOG.md) when you are
finished, including the parts that failed.

**Nothing here touches alpine.caltech.edu.** Staging is a separate document root
on a separate server. The worst outcome of getting a step wrong is a broken
staging page.

---

## Two ways to deploy, and which to use

**From the server (recommended).** An admin logs into `portal.caltech.edu` with
PuTTY and runs one command. The server pulls the code from GitHub itself and
publishes it. Nothing is needed on the admin's own computer beyond PuTTY and the
VPN, which is the point: the ability to deploy stops depending on one person
having a laptop set up correctly. → §A

**From your own machine.** `tools/deploy.sh` sends the files over SSH from a
clone on your laptop. Needs Git Bash, a clone, the VPN on "Tunnel All", and one
round of password plus Duo. It is a single command — it remembers your username,
checks the server is reachable before doing anything slow, uploads and sets
permissions over one connection, and verifies the result itself. Use it to
bootstrap the server-side setup, or if GitHub is unreachable. → §0 onwards.

Both publish exactly the same files and set the same permissions. Neither can
touch `alpine.caltech.edu`.

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

That takes whatever is on GitHub, backs up the current site, publishes, and sets
the permissions. It always overwrites: the checkout is reset to match `origin/main`
first, so anything edited by hand inside `repo/` is discarded without a prompt.
GitHub is the source of truth; the server is a copy of it.

## A3. Rolling back

```bash
git -C /srv/www.alpine.caltech.edu/www/repo checkout <commit>
```

```bash
/srv/www.alpine.caltech.edu/www/bin/deploy
```

Return to normal with `git -C .../repo checkout main` and deploy again. The
five automatic copies in `backups/` are the cruder fallback.

## A4. Why not have it deploy itself on every push

GitHub's runners are on the public internet and `portal.caltech.edu` answers
only from campus or the VPN, so a GitHub Action can never reach it. A scheduled
pull on the server would work, but it would publish whatever is on `main`
without anyone looking. For a site four people edit occasionally, one deliberate
command is better than a robot.

---

# §B. Deploying from your own machine

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
| `docs/`, `README.md` | Notes for officers, not pages for visitors |
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

**`tools/deploy.sh` does this for you at the end of every deploy.** Run it by
hand when you want to check the site without deploying — from your own machine,
off the VPN if you can:

```bash
python tools/verify_deploy.py https://staging.alpine.caltech.edu
```

It checks every page returns 200, that the pages are HTML rather than PHP source,
that events rendered, that `.htaccess` is in force (the security headers arrive,
`/ASSIGNMENTS.csv` is denied, and a folder with no index does not list its
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
| No events on the page | The calendar fetch failed, or the calendar has no future events. `php tools/check.php` on the server distinguishes the two. |
| The site is slow | `cache/` is not writable, so every visitor triggers a call to Google. Step 4. |
| A page 403s | Permissions. Step 4. |
| "Connection refused" from `scp` | The VPN is not on Tunnel All. |

### Rollback

There is no automatic rollback. There are three ways back, in order of how much
you will wish you had used them:

1. **Re-deploy an older commit.** `git checkout <commit>`, run `tools/deploy.sh`,
   `git checkout main`. This is why deploying uncommitted work is a bad idea.
2. **Copy the document root before you overwrite it**, once there is anything
   there worth keeping: `cp -a docroot docroot.bak-2026-08-18` on the server.
3. **Do nothing.** Staging is not the club's site. `alpine.caltech.edu` is
   untouched, and a broken staging page harms nobody.

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
- [ ] **Someone other than Kyle can do this procedure.** Officer turnover is
      annual and this is the step that decides whether the site survives it.
- [ ] `noindex` comes off production and stays on staging. Two indexable copies
      of the same site compete with each other in search results.
- [ ] The Wagtail site is left in place, not deleted, until the new one has been
      live for a term.
