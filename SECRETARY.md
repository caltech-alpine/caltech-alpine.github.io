# Running the Alpine Club website

**You are in the right place. This is the only document you need for ordinary
club business.** It assumes you can use a web browser and nothing else — no
programming, no command line except one line at the very end, and nothing to
install.

Everything else in this repository is machinery. You can ignore it.

---

## The whole system, in six lines

- The website's files live on **GitHub**, at
  [caltech-alpine/caltech-alpine.github.io](https://github.com/caltech-alpine/caltech-alpine.github.io).
  You edit them **in your browser, on GitHub.** That is the only place changes are made.
- The branch that counts is **`main`**. There is only one.
- **Events are not on GitHub at all.** They come from the club's Google Calendar.
  Adding a trip means adding a calendar event, and the website updates itself
  within five minutes.
- When you save a change, GitHub **checks it automatically** and shows a green
  tick or a red X, then rebuilds a **preview** at
  <https://caltech-alpine.github.io> so you can look at it.
- When the preview is right, somebody with server access **publishes it** — one
  command, and it refuses to publish anything GitHub has marked red.
- The Caltech server holds a **copy**. Nobody ever edits it directly.

That is the whole thing:

> **EDIT on GitHub → GitHub CHECKS it → look at the PREVIEW → PUBLISH.**

### Which address is which, today

| Address | What it is |
|---|---|
| <https://alpine.caltech.edu> | **The club's live site, and it is now this repository.** This is what publishing updates, and what the public and Google see. |
| <https://staging.alpine.caltech.edu> | The same files at a second address, kept out of search results. Handy for showing somebody a change. Not a separate copy, so it cannot be "behind". |
| <https://caltech-alpine.github.io> | The preview. Rebuilt automatically from `main`. |

*(Checked 2026-09-02 — the day the move happened. `alpine.caltech.edu` served
the same commit as staging, and both answered every page. **Nothing in your
procedure changed**, which was the design: the publish command prints the
address it just updated, and that is the one to check. What did change is the
stakes — this is now the public site, so publish something you have looked at
on the preview first.)*

---

## Where do I change things?

**You should never have to search this repository.** Find the row, click the
link, edit the file.

| I want to change | Go to |
|---|---|
| **An event, trip, or trip cancellation** | **The club's Google Calendar.** Not this repository. |
| **Who the officers are** (after an election) | [`data/assignments.csv`](data/assignments.csv) |
| **A person's name, email, or photo** | [`data/people.csv`](data/people.csv) |
| **A job's title, description, or how many people do it** | [`data/roles.csv`](data/roles.csv) |
| The gear the club lends | [`data/gear.php`](data/gear.php) |
| Sponsors | [`data/sponsors.php`](data/sponsors.php) |
| Member discounts and deals | [`data/benefits.csv`](data/benefits.csv) |
| The Slack invite, the mailing-list link, the donate link, club email addresses | [`includes/config.php`](includes/config.php) |
| Photographs | the `assets/images/` folder — see [below](#photographs) |

Every one of those files explains itself at the top. Nothing you need is
written down in two places, so changing a fact is always one edit.

**Anything not in that table is not routine.** If you find yourself wanting to
edit a `.php` page or the stylesheet, that is website *development* — the
[README](README.md) covers it, and it is fine to leave alone.

---

## How to edit a file on GitHub

The same six steps for any file in the table above.

1. Click the file's link in the table. You are looking at it on GitHub.
2. Click the **pencil icon** (top right of the file) — *Edit this file*.
3. Make your change. It is a plain text file; type into it like a note.
4. Scroll down to **Commit changes**.
5. Write one line saying what you did — *"Alice Fell replaces Zach Auvil as
   President"*. That line is the record; in three years it is how somebody works
   out why the site says what it says.
6. Leave **"Commit directly to the `main` branch"** selected. Click **Commit changes**.

That is it. GitHub starts checking it immediately.

> **Nothing you do here can break the live website.** Saving a change on GitHub
> does not publish it. Publishing is a separate, deliberate step, and it refuses
> to publish a change GitHub has marked red.

---

## Updating the officers after an election

This is the common case, so it comes first.

### The three files, in ordinary English

There are three, and each fact is written in exactly one of them.

| File | What it holds | When you touch it |
|---|---|---|
| [`data/people.csv`](data/people.csv) | **Who exists.** One row per human, ever. Name, email, photo. | Somebody new joins the club's leadership, or an address changes |
| [`data/roles.csv`](data/roles.csv) | **What the jobs are.** Title, description, how many people. | The club renames a job, changes what it involves, or invents a new one |
| [`data/assignments.csv`](data/assignments.csv) | **Who is doing which job.** | After an election. **Usually this is the only file that changes.** |

They are spreadsheets saved as plain text — one row per line, columns separated
by commas. Lines starting with `#` are notes and are ignored by the website, so
each file carries its own instructions at the top.

### The one rule that matters

Every job has **two** names:

- **`role_id`** — `president`, `film_festival`, `gear`. Never shown to a
  visitor. It is the invisible label that ties a person to a job. **Pick it once
  and never change it.**
- **`title`** — *President*, *Film Festival Coordinator*. What the page prints.
  **Change it whenever you like.**

Same idea for people: `person_id` (`jane-doe`) is permanent, `name` is free.

You can rename *President* to *Chair* and back again and nothing breaks. Change
a `role_id` and things break quietly — and the automatic check will tell you
exactly what.

### Never delete anybody

When somebody steps down you do **not** delete their row. Put the year in the
`until` column instead. That one edit does three things by itself: they move to
*Past officers*, the job starts showing as open, and both undo themselves the
moment a replacement is added.

**Nobody ever types the word "vacant" anywhere on this site.** The site works
out what is open by counting. If you are ever about to write "we're looking for
a Treasurer" into a file, stop — you have found the wrong file.

### A real example: Alice replaces Zach as President

**Step 1.** Alice is new, so add her to
[`data/people.csv`](data/people.csv). Go to the bottom, add one line:

```
alice-fell,"Alice Fell",afell@caltech.edu,
```

*(The last column is a photo filename. Leave it empty for now — the site shows
her initials, which looks deliberate, and you can add the photo later.)*

**Step 2.** In [`data/assignments.csv`](data/assignments.csv), put the year on
Zach's row and add a row for Alice:

```
zach-auvil,president,2027,
alice-fell,president,,
```

Commit. Done — two lines, and the About page, the homepage, the Get Involved
page and the *Past officers* list all follow.

### The other cases

| Situation | What to do |
|---|---|
| **A role is temporarily vacant** | Nothing. Put the year in the leaver's `until` column and the site advertises the job by itself. |
| **Two people share a role** | Give each a row in `data/assignments.csv`. If `data/roles.csv` gives that job a `title_shared`, they are both titled with it automatically — two presidents become *Co-Presidents* with no further edit. Check `max_people` allows two; the check will say if it does not. |
| **A role changes title** | Change `title` in `data/roles.csv`. Leave `role_id` alone. |
| **A new role is created** | One row in `data/roles.csv`. It appears on Get Involved and in the roster straight away. **The order of the rows is the order on the page** — move the row to move it. |
| **A role is retired** | Delete its row from `data/roles.csv`. Nobody is lost; past holders stay on *Past officers*. First, though: if anyone held it, put what it was called into their `title_held` cell in `data/assignments.csv`, because the row you are deleting is the last record of the name. The check will stop you if you forget. |
| **A job is filled but the holder is leaving** | Write the reason in plain words in the `recruiting` column of `data/roles.csv` — `stepping down in June`. That sentence is what the site shows. It is the **one** thing on this site anybody has to remember to clear. |
| **Stop advertising an empty job** | Put the word `no` in the `recruiting` column. |

### Photographs

Crop the photo to a **portrait shape, about 4 wide by 5 tall**, and shrink it to
around 500–1000 pixels wide. Any photo app does both. Name it after the person:
`alice-fell.jpg`.

Then, on GitHub, open
[`assets/images/officers`](https://github.com/caltech-alpine/caltech-alpine.github.io/tree/main/assets/images/officers),
click **Add file → Upload files**, drop it in, and commit. Put that same
filename in the `photo` column of `data/people.csv`.

> ⚠ **Upload it to `assets/images/officers/`, not to the `raw/` folder inside
> it.** `raw/` is a keepsake drawer for original, uncropped photos so a crop can
> be redone later. A photo that is *only* in `raw/` is not on the website. If
> you name a photo in `data/people.csv` that is not where the site looks, the
> automatic check gives you a red X and says so, so this is caught rather than
> silent — but it is easier not to trip it.

Somebody with no photo shows their initials, so **never hold up a roster update
while chasing a headshot.**

---

## How do I test a change?

You do not have to do anything. GitHub does it for you, twice.

**1. The automatic check.** Within about a minute of committing, GitHub has read
your change and decided whether it holds together. It catches the mistakes that
are invisible on the finished page — a misspelled `person_id`, a duplicate, an
officer pointed at a job that does not exist, a photo filename that is not
there, a role deleted while somebody's job title still depends on it. It also
re-runs next year's likely edits against your data and re-renders every page to
make sure PHP produced no errors.

**Where to look:** open
[the commit list](https://github.com/caltech-alpine/caltech-alpine.github.io/commits/main).
Beside your commit is:

| | |
|---|---|
| 🟡 **Yellow dot** | still checking. Wait a minute. |
| ✅ **Green tick** | good. Carry on. |
| ❌ **Red X** | something is wrong. **Click it.** For the ordinary mistakes — a CSV edit that does not add up — it names the file, the row and what to fix, in plain words. Fix it the same way you made the change, and the tick appears. |

**Do not publish a commit with a red X.** You do not have to remember this: the
publish command checks for you and refuses.

**2. The preview.** A few minutes after the green tick,
<https://caltech-alpine.github.io> is rebuilt with your change in it. **This is
the normal preview — look at it before you publish.** It is a real render of
the real site, marked so search engines ignore it.

Its one limitation is the calendar. The preview is a snapshot, rebuilt on every
change and again every half hour, so the events showing on it can be up to
thirty minutes out of date. Everything you actually edit — officers, gear,
sponsors, links, wording — is exactly what will be published.

*(Optional, for developers only: a local copy and the test scripts are in
[docs/DEVELOPER.md](docs/DEVELOPER.md). A secretary does not need them.)*

---

## How do I publish it?

Three things, once you have a green tick and a preview you are happy with.

**1. Get on the Caltech network.** Be on campus, or connect the **Caltech VPN**
and choose the **"Tunnel All"** profile. Split tunnelling cannot reach the
server, and the failure looks like the server being down.

**2. Log in to the server.** Open **PuTTY**, load the saved session called
`portal`, and sign in with your Caltech account. *(Setting PuTTY up the first
time: [docs/ACCESS.md](docs/ACCESS.md).)*

**3. Run one command:**

```bash
/srv/www.alpine.caltech.edu/www/bin/deploy
```

That is the whole procedure. Type `exit` when it finishes.

### What success looks like

```
deploying a1b2c3d  Alice Fell replaces Zach Auvil as President
GitHub's checks on a1b2c3d passed.

backed up the current site to /srv/.../backups/docroot-2027-06-04-1132

publishing...

checking https://alpine.caltech.edu ...
  ok   https://alpine.caltech.edu is serving a1b2c3d - the commit just published.
  ok   the home page loads and is ours.

done.

  live at:   https://alpine.caltech.edu
```

The command takes the copy of the site from GitHub, backs up what was there,
publishes, and then **fetches the public address itself and confirms your change
landed.** You do not have to check separately — but open the address it prints
and look at the page anyway. A script cannot tell you that a photograph is
upside down.

### Two things it may say instead

**"NOT PUBLISHING YET — GitHub has not finished checking."** You committed less
than a minute ago. Wait a minute; run it again.

**"REFUSING TO PUBLISH — GitHub's checks FAILED."** The change is broken. It
gives you the link. Fix it on GitHub, wait for the green tick, run it again.

> ⚠ **Never edit files on the Caltech server.** It holds a copy, and the next
> publish overwrites it completely and without asking. Any edit made there is
> lost, and lost silently. Every change goes through GitHub.

---

## What if I mess something up?

Nothing you can do here is permanent, and there are three levels of undo.

**If you have not published yet — there is nothing to undo.** The live site
never saw it. Fix the file on GitHub the same way you edited it.

**If you published and the site is wrong**, put the previous copy straight back.
On the server, one command:

```bash
/srv/www.alpine.caltech.edu/www/bin/deploy --rollback
```

The site is back to how it was before your deploy, immediately. Every deploy
takes a copy first and the last five are kept. If there are no copies yet it
says so rather than doing something surprising.

**Then fix it properly.** The rollback is a patch: the next ordinary publish
will send whatever is on GitHub again. So go back to GitHub, undo your edit, get
the green tick, and publish again.

**If you cannot work out what broke**, every version of every file is kept on
GitHub forever, with your one-line message saying why it changed. Nothing is
ever really lost. Ask on the club Slack, or open a ticket at
[help.caltech.edu](https://help.caltech.edu) if the server itself is misbehaving.

---

## Where the files are on the Caltech server

**You do not need this for ordinary work** — it is here for the day something is
wrong and somebody asks. All of it lives under one folder on
`portal.caltech.edu`, last checked on the server itself on 2026-08-19
([docs/SERVERS.md](docs/SERVERS.md) records how):

```
/srv/www.alpine.caltech.edu/www/
├── docroot/       the live site. A COPY. Never edit anything in here
├── repo/          the server's own copy of GitHub, reset on every deploy
├── backups/       the last five copies of docroot, made automatically
└── bin/deploy     the publish command
```

Two other useful facts: `https://<the site>/version.txt` says exactly which
version is live and when it was published, and the full detail of how deployment
works is in [docs/DEPLOY.md](docs/DEPLOY.md).

---

## What to hand to your successor

**Do this before you leave, not after.** A club loses its website when one
person holds all the access and then graduates.

- [ ] **GitHub.** They need to be a member of the
      [`caltech-alpine`](https://github.com/caltech-alpine) organization with
      write access to the repository. A current organization owner adds them
      (*Settings → People → Invite member*). Show them this document.
- [ ] **The Caltech server.** They need to be in the **`alpinewww`** group.
      Open a ticket at [help.caltech.edu](https://help.caltech.edu) naming the
      person and the site. **Check at least two people are in it after you
      leave** — on the server, `getent group alpinewww` lists them.
- [ ] **The VPN.** Every Caltech account has it; they just need to know it must
      be the **"Tunnel All"** profile. Tell them, or they will lose an afternoon.
- [ ] **The Google Calendar.** They need permission to *make changes to events*
      on the club calendar. Whoever owns it grants that in Google Calendar's
      own sharing settings. **This is the one that gets forgotten**, and it is
      the one that stops the club posting trips.
- [ ] **The club email addresses.** Make sure they can read `alpine@caltech.edu`
      and `alpine-secretary@caltech.edu` — the site sends visitors to both.
- [ ] **Slack.** Whatever is needed to send invites.
- [ ] **Remove your own access when you go** — the three steps at the end of
      [docs/ACCESS.md](docs/ACCESS.md), including the one people skip: checking
      that the removal actually worked.
- [ ] **Add yourself to the past officers** — put the year in your `until`
      column in `data/assignments.csv`, and your successor's row in.

---

## Once a year

- [ ] Update `data/assignments.csv` after elections. That is also what makes
      newly empty jobs advertise themselves, so there is nothing separate to do.
- [ ] Check `data/roles.csv` still describes the jobs the club actually has.
- [ ] Check the mailing list and Slack links in `includes/config.php` still work.
- [ ] Check `data/gear.php` is still accurate. **Delete anything the club no
      longer has** — a list promising missing equipment is worse than a short one.
- [ ] Check anything in `data/benefits.csv` is still live and still advertisable.
- [ ] Refresh the photographs.
- [ ] Do the handover checklist above.
- [ ] Make sure somebody else knows this document exists.

---

## If you need more than this

You should not, for ordinary club business. But:

| | |
|---|---|
| How the site is built, the design, the calendar | [README.md](README.md) |
| Setting up a copy on your own computer | [docs/DEVELOPER.md](docs/DEVELOPER.md) |
| Getting server access, and giving it up | [docs/ACCESS.md](docs/ACCESS.md) |
| Deployment in full, and fixing a deploy that went wrong | [docs/DEPLOY.md](docs/DEPLOY.md) |
| Which machines, which paths, which permissions | [docs/SERVERS.md](docs/SERVERS.md) |
| What happened the last time somebody deployed | [docs/DEPLOY-LOG.md](docs/DEPLOY-LOG.md) |
| Why the site is hosted this way | [docs/HOSTING.md](docs/HOSTING.md) |
| How the writing on the site should read | [docs/WRITING.md](docs/WRITING.md) |

Server, hosting, DNS and group access are all
[help.caltech.edu](https://help.caltech.edu). This site's history there is
ticket **INC0028327**.
