# If you just took this over

You have inherited a website. This is the first hour.

It assumes you know nothing about this site and have never used a command line.
Every command says which program to type it in and which folder to be in first.
Everything in sections 1 to 4 is read-only, so you cannot break anything by
following along.

---

## 1. The one program you need

**Git Bash.** Not Command Prompt, not PowerShell, not PuTTY. Those are different
programs that speak different languages, and commands copied from here will fail
in them in confusing ways.

If it is not installed, get it from <https://git-scm.com/download/win> and accept
every default. On a Mac or on Linux you already have the equivalent: open
**Terminal** and everything here works unchanged.

To open it: press the Windows key, type `Git Bash`, press Enter. You get a black
window with a prompt ending in `$`. That is where every command below goes.

**Paths look different in here.** `C:\Users\jane\Documents` is written
`/c/Users/jane/Documents`. Forward slashes, and `/c/` instead of `C:`. Your own
home folder can be shortened to `~`, so `~/Documents` is the same place.

---

## 2. Get the code onto your computer

In Git Bash, first go to your Documents folder:

```bash
cd ~/Documents
```

Then download the site:

```bash
git clone https://github.com/caltech-alpine/caltech-alpine.github.io.git alpine-website
```

That creates a folder called `alpine-website` inside Documents holding the whole
site. **This is now your working folder**, and every command in the rest of this
file assumes you are in it. Go there:

```bash
cd ~/Documents/alpine-website
```

Check you are in the right place — this should print a list including
`index.php`, `data` and `docs`:

```bash
ls
```

**If you close Git Bash and come back later, you have to `cd` there again.** The
window always opens in your home folder. This one line is the start of every
session:

```bash
cd ~/Documents/alpine-website
```

**If somebody else has changed the site since you cloned it**, catch up before
you do anything else:

```bash
git pull
```

---

## 3. What this site is, in five lines

- Plain HTML, CSS, a little JavaScript and some PHP. **No build step, no
  database, no CMS.** Edit a text file, copy it to the server, done.
- **Events come from the club's Google Calendar**, read by the server every 30
  minutes. Adding a trip means adding a calendar event, not editing the site.
- The files officers actually edit are in **`data/`** (roster, gear, sponsors)
  and **`includes/config.php`** (every link and club fact).
- Everything else is machinery you can ignore.
- The full manual is [`../README.md`](../README.md). This file is the tour.

There are three copies of the site living at once:

| Copy | Address | What it is |
|---|---|---|
| Production | <https://alpine.caltech.edu> | What members see today |
| Staging | <https://staging.alpine.caltech.edu> | Where changes are tested |
| Pages pilot | <https://caltech-alpine.github.io> | A preview built from GitHub |

Open all three in a browser now. Nothing you do can change them yet.

---

## 4. Look around, safely

Be in your working folder first:

```bash
cd ~/Documents/alpine-website
```

**Read the last twenty commit messages.** Every one explains *why* a change was
made. This is the fastest way to understand the site:

```bash
git log --oneline | head -20
```

Press `q` to get out if it fills the screen.

**Check nothing is half-finished.** This should print nothing:

```bash
git status
```

If it lists files, somebody left work unfinished. Find out what before building
on top of it.

**See the files officers edit:**

```bash
ls data/
```

**Check the live site is healthy.** This needs no password and changes nothing:

```bash
python tools/verify_deploy.py https://staging.alpine.caltech.edu
```

**Check the writing.** Flags copy that reads as machine-written:

```bash
python tools/voice_check.py
```

If `python` is not found, install it from <https://www.python.org/downloads/> and
tick *Add Python to PATH* during setup. Nothing else here needs it.

---

## 5. Back up before you change anything

There is no undo button on the server. Do this once, before your first deploy.

You need server access first — [ACCESS.md](ACCESS.md) is that whole procedure,
including PuTTY. You also need the **Caltech VPN connected on the Tunnel All
profile**, or none of this section can reach anything.

**A copy on the server, beside the original.** Log in first:

```bash
ssh YOUR_USERNAME@portal.caltech.edu
```

Then, at the server's prompt:

```bash
cd /srv/www.alpine.caltech.edu/www
```

```bash
cp -a docroot docroot.bak-$(date +%Y-%m-%d)
```

Type `exit` to come back to your own machine.

**A copy on your own computer.** Back in Git Bash, in your home folder:

```bash
cd ~
```

```bash
scp -r YOUR_USERNAME@portal.caltech.edu:/srv/www.alpine.caltech.edu/www/docroot ~/alpine-site-backup
```

**The backup that matters most is GitHub.** If what is on the server also exists
in a commit, you can always put it back. That is why `tools/deploy.sh` refuses to
run when you have uncommitted changes.

---

## 6. Change something and publish it

Do this once on a typo, before you ever need to do it in a hurry. Every command
goes in **Git Bash**, and every one of them assumes you have done step 1 first.

**1. Go to the working folder.** You have to do this every time you open a new
Git Bash window, because it always starts in your home folder:

```bash
cd ~/Documents/alpine-website
```

**2. Catch up with whatever anyone else has done:**

```bash
git pull
```

**3. Edit the file.** Any text editor. Notepad works, VS Code is nicer. For a
roster change open `data/officers.csv`; for a link or an email address open
`includes/config.php`. [`../README.md`](../README.md) says which file for every
kind of change.

**4. See exactly what you changed**, before saving anything:

```bash
git diff
```

Press `q` to exit if it fills the screen. If the changes are not what you
expected, undo everything with `git checkout .` and start again.

**5. Stage every change:**

```bash
git add -A
```

**6. Check what you staged.** Every line should start with `A` or `M`, and
nothing should still say `??`:

```bash
git status --short
```

**7. Save it to your local history.** Write *why*, since the diff already shows
what:

```bash
git commit -m "Add Jane Doe as Climbing Commodore"
```

Nothing has left your computer yet. Up to this point everything is reversible.

**8. Send it to GitHub:**

```bash
git push
```

**9. Confirm it arrived.** Open
<https://github.com/caltech-alpine/caltech-alpine.github.io/commits/main> — your
commit should be at the top. Pushing also rebuilds
<https://caltech-alpine.github.io> within a few minutes, which is a free preview
before anything reaches the real site.

**10. Publish it to the Caltech server.** Log into `portal.caltech.edu` with
PuTTY and run one command:

```bash
/srv/www.alpine.caltech.edu/www/bin/deploy
```

Full detail, the first-time setup and the laptop alternative are in
[DEPLOY.md](DEPLOY.md).

**11. Write down what happened** in [DEPLOY-LOG.md](DEPLOY-LOG.md), especially
anything that went wrong.

### When the push is refused

**"Repository not found"** does not mean the repository is missing. It means git
is signed in as the wrong GitHub account. The repository belongs to the
**`caltech-alpine`** organization, and your personal account has to have been
added to it. Check who you are with:

```bash
gh auth status
```

**"Updates were rejected because the remote contains work that you do not have
locally"** means somebody else pushed while you were editing. Do not force
anything. Get their work first, then push again:

```bash
git pull --rebase
```

**"stale info" or a complaint about the remote having changed unexpectedly**
means your machine's record of GitHub is out of date. Refresh it:

```bash
git fetch origin
```

**Rewriting history that is already on GitHub** — replacing past commits rather
than adding new ones — is a different operation and it is not part of normal
work. If you ever genuinely need it, the safe form is
`git push --force-with-lease origin main`, run only after `git fetch origin`, and
only when you know nobody else has cloned the repository since. It was done once
here, on 2026-08-18; see [DEPLOY-LOG.md](DEPLOY-LOG.md).

---

## 7. Four things that will confuse you

**`php tools/check.php` cannot be run on the server.** It appears in older
notes. The server you can log into has no PHP, and the machine with PHP only
answers web requests. It runs fine on a laptop with PHP installed
(`winget install PHP.PHP.8.4`), which is also what `tools/build_static.py` and
`tools/audit.py` need. Kyle's machine has PHP 8.4 as of 2026-08-26; winget puts
it outside `PATH` for non-login shells, so either restart the shell or set
`PHP=` to the `php.exe` under
`%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.4_*\`, which
`build_static.py` reads. One caveat: PHP 8.4 prints a `Constant E_STRICT is
deprecated` notice from `includes/bootstrap.php:26`. It is harmless locally and
the server's older PHP does not emit it.

**Never add a force-HTTPS rule to `.htaccess`.** HTTPS is handled in front of the
web server, so the usual recipe redirects forever and looks like an outage.
[SERVERS.md](SERVERS.md) has the version that works.

**GitHub switches off the automatic rebuild after 60 days with no commits**, and
emails only the last person who committed. If the pilot's calendar freezes, check
the Actions tab on GitHub before checking the code.

**The site hides missing things on purpose.** No photo gives a drawn contour
pattern, no headshot gives initials, no calendar gives a link to Google. Plain
usually means missing data, not broken code.

---

## 8. Who to ask

| For | Contact |
|---|---|
| Server, hosting, DNS, group access | [help.caltech.edu](https://help.caltech.edu) — this site's history is ticket INC0028327 |
| The Caltech Sites platform, if the club ever goes back to it | `templates@caltech.edu` |
| The club's calendar and accounts | the current officers |

---

## 9. Your first week

- [ ] Install Git Bash and clone the repository (§1, §2)
- [ ] Open all three copies of the site in a browser (§3)
- [ ] Get server access and a key — [ACCESS.md](ACCESS.md)
- [ ] Confirm at least two people can deploy: `getent group alpinewww` on the
      server. One name is how a club loses its website.
- [ ] Take both backups (§5)
- [ ] Push one trivial change all the way through (§6)
- [ ] Read [`../README.md`](../README.md) properly, once
- [ ] Make sure you can add events to the club's Google Calendar
- [ ] Add your own name to `data/officers.csv`
