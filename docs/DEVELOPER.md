# Working on the site itself

**You almost certainly do not need this.** Changing the officers, the gear, the
sponsors, the links or the photographs is done in a browser on GitHub and is
covered by [`../SECRETARY.md`](../SECRETARY.md). Nothing here is required for
any of it.

This file is for the other thing: changing how the site *works* — a page, the
stylesheet, the calendar code, one of the tools. That needs a copy on your own
computer.

---

## What to install

| | Why | Where |
|---|---|---|
| **Git** | to get a copy and send changes back | <https://git-scm.com/download/win> — accept every default. macOS and Linux already have it. |
| **PHP 8** | to run the site locally | Windows: `winget install PHP.PHP.8.4`. macOS: `brew install php`. |
| **Python 3** | for the test and build tools | <https://www.python.org/downloads/> — tick *Add Python to PATH*. |

On Windows, run everything below in **Git Bash**, not Command Prompt or
PowerShell. Press the Windows key, type `Git Bash`, press Enter. Paths look
different in it: `C:\Users\jane\Documents` is written `/c/Users/jane/Documents`,
and `~` is your home folder.

If `php` is not found after installing it, the installer put it somewhere that
is not on your `PATH`. Either open a new shell, or set `PHP=` to the full path
of `php.exe`; `tools/build_static.py` reads that variable.

---

## Get a copy

```bash
cd ~/Documents
```

```bash
git clone https://github.com/caltech-alpine/caltech-alpine.github.io.git alpine-website
```

```bash
cd ~/Documents/alpine-website
```

**Every command below assumes you are in that folder.** Git Bash opens in your
home folder each time, so `cd ~/Documents/alpine-website` is the first line of
every session.

Catch up with anybody else's work before you start:

```bash
git pull
```

---

## Run the site locally

```bash
php -S 127.0.0.1:8800
```

Then open <http://127.0.0.1:8800>. This is real PHP reading the real calendar,
so it is a truer picture than the Pages preview. Ctrl-C stops it.

`preview.php` is worth knowing about: it renders **any** public Google Calendar
through the site's own components, with a table of exactly what the parser
extracted from each event. It is how to check that an unusual event — all-day,
multi-day, repeating, cancelled — will look right before it goes on the club
calendar. Setting it up is in the [main README](../README.md), *Testing changes*.

---

## The checks

All six are read-only, and none of them touch the server.

```bash
php tools/check.php --data
```

The officer data: every id resolves, nothing is duplicated, no photo is named
that is not there, no retired role leaves somebody's history unrecorded. Under a
second, and no network. **Run it after editing a CSV.** It is the same check
GitHub runs.

```bash
python tools/test_roles.py
```

Applies each of next year's likely officer edits to the real data files —
replacing somebody, adding a co-officer, renaming a title, changing a maximum,
retiring a role — renders every page through real PHP, and checks what came out,
including whether the old name and address survive anywhere. Puts the files back
afterwards. 92 checks. Also runs in CI.

```bash
python tools/check_docs.py
```

Every relative link in every `.md` file resolves to a real file, and
`SECRETARY.md` links every file it tells an officer to edit. **Run it after
renaming or moving any document.** Also runs in CI.

```bash
php tools/check.php
```

The fuller health check: PHP version, cache writability, whether the calendar
fetched and how many events it found, which config links are blank. Add
`--links` to test that every URL actually resolves. **This one cannot be run on
the Caltech server** — see [SERVERS.md](SERVERS.md).

```bash
python tools/verify_deploy.py https://staging.alpine.caltech.edu
```

Checks a deployed copy from outside, over the public internet: every page
returns 200, PHP is executing rather than being served as text, the calendar
rendered, `.htaccess` is in force, the protected paths are refused, the 404 page
is ours, and **which commit the server is running**. Add `--expect HEAD` to
assert that it is running the commit you are sitting on.

```bash
python tools/voice_check.py
```

Flags copy that reads as machine-written. Judgement, not a gate — see
[WRITING.md](WRITING.md).

---

## Send a change back

```bash
git pull
```

Edit the files. Then look at exactly what you changed, before committing
anything:

```bash
git diff
```

`q` exits. If it is not what you expected, `git checkout .` throws it all away
and you start again.

```bash
git add -A
```

```bash
git status --short
```

Every line should start with `A` or `M`; nothing should still say `??`.

```bash
git commit -m "Say why, since the diff already says what"
```

```bash
git push
```

Then watch <https://github.com/caltech-alpine/caltech-alpine.github.io/actions>.
A green tick means the checks passed and the preview at
<https://caltech-alpine.github.io> is rebuilding. Publishing to Caltech is
[DEPLOY.md](DEPLOY.md), and it is the same one command whether the change came
from a clone or from the GitHub web editor.

### When the push is refused

**"Repository not found"** does not mean the repository is missing. It means git
is signed in as the wrong GitHub account. The repository belongs to the
**`caltech-alpine`** organization and your account has to have been added to it.
Check who you are with `gh auth status`.

**"Updates were rejected because the remote contains work that you do not have
locally"** means somebody pushed while you were editing. Do not force anything:

```bash
git pull --rebase
```

**"stale info", or a complaint that the remote changed unexpectedly**, means
your machine's record of GitHub is out of date:

```bash
git fetch origin
```

**Rewriting history that is already on GitHub** is not part of normal work. If
you ever genuinely need it, the safe form is
`git push --force-with-lease origin main`, run after `git fetch origin`, and only
when nobody else has cloned since. It was done once here, on 2026-08-18; see
[DEPLOY-LOG.md](DEPLOY-LOG.md).

---

## Four things that will confuse you

**`php tools/check.php` cannot be run on the Caltech server.** It appears in
older notes. `portal.caltech.edu` is a file server with no PHP on it, and the
machine that actually serves the site only answers web requests. Run it locally;
`tools/verify_deploy.py` covers the deployed side over HTTP.

**Never add a force-HTTPS rule to `.htaccess`.** HTTPS is terminated in front of
the web server, so the usual recipe redirects forever and looks like an outage.
[SERVERS.md](SERVERS.md) has the form that works.

**Anything printed before `<!DOCTYPE html>` breaks the page**, not just the
markup: it puts the browser in quirks mode and breaks any later `header()` call.
A PHP deprecation notice did exactly this in August 2026, and a local
`build_static.py` run baked it into the top of every file in `_site/`. CI's grep
did not catch it, because that grep looks for `Fatal error` and this was only a
notice. `build_static.py` now fails the build if anything precedes the doctype.

**The site hides missing things on purpose.** No photo gives a drawn contour
pattern, no headshot gives initials, no calendar gives a link to Google. Plain
usually means missing data, not broken code.

---

## Before you change anything structural

Read the file's header comment first. The ones worth knowing:

| File | What its header explains |
|---|---|
| `includes/roles.php` | the whole of "is this job open", and why nothing compares a role *title* |
| `includes/validate.php` | why the error messages are written for next year's secretary rather than for a programmer |
| `tools/build_static.py` | what changes between the PHP site and the static render |
| `tools/server-deploy.sh` | why the deploy does four things rather than one |
| `.github/workflows/pages.yml` | why the rebuild is on a 30-minute schedule, and the two ways GitHub's scheduler will surprise you |

And the constraint the whole thing is built around, from the
[main README](../README.md): *before adding a system to this site, ask whether an
officer three years from now will understand how to maintain it.*
