# Caltech Alpine Club website

The code behind [alpine.caltech.edu](https://alpine.caltech.edu).

**A live pilot of this rebuild is at <https://caltech-alpine.github.io/>.** It is a
static render of this repository, published by `.github/workflows/pages.yml` and
rebuilt every 30 minutes so the calendar stays current. It is marked `noindex` so
it cannot compete with the club's real site in search results. What the pilot
gives up, and what running the dynamic PHP version on Caltech hosting would take,
are both in [docs/HOSTING.md](docs/HOSTING.md).

Plain HTML, CSS, JavaScript and a small amount of PHP. There is **no build step,
no npm, no database and no CMS**. If you can edit a text file and copy it to a
server, you can maintain this site.

It is built on one assumption: **routine club operations should not require
editing the website.** Events come from Google Calendar. Conversations happen on
Slack. This site holds the things that stay true for years.

---

## How to update the website

### To add an event

**Add it to the club Google Calendar. That is the whole process.**

The website reads the calendar every 5 minutes and builds the event cards
itself. You do not touch this repository, and you do not create a page per trip.

Write the title the way it should read on the website. There are no activity
tags to remember.

The site used to accept a `[HIKE]`-style prefix and turn it into a label on the
card. That was removed in August 2026: the labels were not worth the table of
activities, aliases and keyword guesses behind them. Old titles are not a
problem — a leading `[BRACKET]` is still dropped from the displayed title, so
`[RUN] Weekly trail run` reads as *Weekly trail run*. Nothing needs editing in
the calendar.

**Other things worth knowing:**

- All-day, multi-day and repeating events all work.
- Cancelling an event in Google Calendar shows a "Cancelled" badge rather than
  making it vanish, so people who were planning to come find out.
- Events move from "Upcoming" to "Past events" by themselves. Nobody has to
  write a trip report for the archive to exist.
- **Whatever you put in the event description appears on the card**, and every
  event has a **Details** button that opens the full text in a pop-up. Links
  survive there, so a sign-up sheet or a map pin in the description is
  clickable.
- **The first sentence is the one shown on the card**, so put the useful part
  first: *"Meeting at the Chaney Trail gate at 7am, about 6 miles round trip"*,
  not *"Come join us for a great morning out!"*. Everything after it still shows
  in the pop-up. Write the event the way you would tell a friend about it — the
  calendar is allowed to sound like club members, and the site does not tidy it
  up.
- **Do not leave a test event on the calendar.** The archive fills itself in
  from the calendar and never forgets, so an event called `test` is on the
  public website until somebody deletes it from Google Calendar. There is a
  place to test without touching the club calendar: `preview.php`, which renders
  any public calendar using the real site's components. See the top of that
  file.
- **Google Meet boilerplate is stripped automatically.** Ticking "Add Google
  Meet video conferencing" makes Google write *"Join with Google Meet: ..."* and
  *"Learn more about Meet at: ..."* into the description; the site removes both
  lines and keeps anything you wrote yourself. See
  `stripConferencingBoilerplate()` in `lib/calendar/IcsParser.php`.
- **A repeating event shows only its next occurrence**, labelled "Weekly on
  Tuesdays" or similar, so a standing weekly run does not bury everything else.
  Editing or deleting a single week in Google Calendar works as you would
  expect.

## Changing the officers

Three files, all in [`data/`](data). **Everything on this site that a person
edits is in that one directory** — these three, plus the gear list, the
sponsors and the member deals. There is no second place to look.

Each fact is written in exactly **one** of the three, so changing an email
address is one edit and every page follows.

| File | What it says | You edit it when |
|---|---|---|
| [`data/people.csv`](data/people.csv) | **who exists** — name, email, photo | somebody new joins the club's leadership, or an address changes |
| [`data/roles.csv`](data/roles.csv) | **what the jobs are** — title, description, how many people | the club changes what a job is called, what it involves, or how many do it |
| [`data/assignments.csv`](data/assignments.csv) | **who is doing which job** | after an election. Usually the only file that changes |

They are spreadsheets. Open them in Excel, Sheets, Notepad, or the GitHub web
editor. Lines starting with `#` are notes and are ignored, so each file carries
its own instructions at the top.

**After any edit, run this.** It takes under a second, needs no network, and it
catches the one class of mistake that is invisible on the finished page:

```bash
php tools/check.php --data
```

---

### The one durability rule: `role_id` is permanent, `title` is not

Every job has two names.

- **`role_id`** — `president`, `film_festival`, `gear`. Short, lowercase, never
  shown to a visitor. It is what `data/assignments.csv` points at, what the page
  anchors use, and what the website's own code looks for. **Pick it once and
  never change it.**
- **`title`** — `President`, `Film Festival Coordinator`. What the page prints.
  **Change it as often as you like.**

Nothing in the site decides anything by reading a title. You can rename
*President* to *Co-President*, to *Presidents*, to *Chair*, and back again, and
the only thing that changes is the words on the screen — the president stays
attached to the job, the vacancy maths keeps working, the links keep working.

The one edit that *does* break things is changing a `role_id`, and
`php tools/check.php --data` will tell you exactly what it broke.

---

### Replacing an officer

Two edits, both in `data/assignments.csv`. Put the year in the `until` column of the
person leaving, and add a row for the person arriving:

```csv
person_id,role_id,until,title_held
zach-auvil,president,2027,
alice-fell,president,,
```

If the new person is not in `data/people.csv` yet, add them there first:

```csv
person_id,name,email,photo
alice-fell,"Alice Fell",afell@caltech.edu,alice-fell.jpg
```

**Never delete anybody.** The `until` year moves them to the *Past officers*
list by itself, and the job starts showing as open by itself, and both undo
themselves when the replacement is added. **Nobody ever types the word
"vacant".**

### Adding a co-officer

One more row in `data/assignments.csv`:

```csv
alice-fell,president,,
bob-ridge,president,,
```

If `data/roles.csv` gives that job a `title_shared`, both are titled with it
automatically — two presidents become *Co-Presidents* without anyone editing a
title. Drop back to one and it reads *President* again.

Check `max_people` allows two. If it does not, the data check says so.

### Renaming a role

Change `title` in `data/roles.csv`. That is all. Leave `role_id` alone.

```csv
role_id,title,title_shared,...
president,Chair,Co-Chair,...
```

### Changing how many people a job wants

Two numbers in `data/roles.csv`, and they mean different things:

- **`min_people`** — how many the club *needs*. Below this, the job is
  advertised as open, on the homepage and everywhere else.
- **`max_people`** — how many it can *use*. Between min and max the site offers
  the place quietly instead of announcing a gap. **Leave it blank** for a job
  that takes as many volunteers as turn up.

That gives four different things the site can say, and you choose between them
with two numbers rather than by rewording a notice:

| `min` | `max` | People doing it | What the site says |
|---:|---:|---:|---|
| 1 | 2 | 0 | **Open** — and the homepage says the club is short one |
| 1 | 2 | 1 | *Room for one more* — quietly, not on the homepage |
| 1 | 2 | 2 | nothing |
| 1 | *(blank)* | 2 | nothing — as many as turn up is never short |
| **0** | 1 | 0 | *Open, if somebody wants it* — never on the homepage |

That last row is the useful one. **`min_people = 0` means the job is not
required.** An empty one is an invitation rather than a gap, and it never
appears among the things the club is short of. Use it for anything the club is
genuinely happy to go a year without.

### Adding a new role

One row in `data/roles.csv`, and a row in `data/assignments.csv` if somebody is already
doing it. No template to edit — it appears on Get Involved, in the officer
roster, and in the vacancy counting straight away.

```csv
role_id,title,title_shared,group,min_people,max_people,description,contact_for,recruiting
ski_touring,"Ski Touring Coordinator",,"Activity Leaders",1,2,"Organizes backcountry ski days.","Ski touring",
```

**The order of the rows is the order on the page**, for the roles *and* for the
officers holding them. Move a row to move both. There is no sort column.

### Retiring a role

Delete the row from `data/roles.csv`. **Nobody is lost** — the people who held it
stay in `data/people.csv` and on the *Past officers* list, which is built from
`data/assignments.csv` and deliberately outlives the jobs.

One thing to do first: if anybody held that job in the past, make sure their
`title_held` cell in `data/assignments.csv` says what the job was called. The row you
are about to delete is the last record of it. `php tools/check.php --data` will
stop you and name the people concerned if you forget.

### Pausing recruitment for a role

Put the word `no` in the `recruiting` column and the site stops asking for that
job, even while it is empty. It still appears on Get Involved with its
description; it just stops advertising. Clear the cell to start again.

The same column does the opposite job when you write a sentence in it: for a
role that is **filled and still needs somebody** — because the holder is
leaving — write the reason in plain words, `stepping down in June`, and that
sentence is what the site shows. It is the one case the site cannot work out by
counting.

---

### The rest of the columns

`contact_for` in `data/roles.csv` is what makes the roster useful: it tells a visitor
what to write to that officer about. It describes the **job**, so two people
sharing one say the same thing, and it is written once.

`email` in `data/people.csv` is optional but worth chasing — without it a visitor can
only reach that officer through the shared mailbox. Club addresses are fine to
publish; personal ones deserve a second thought. Wherever a person's name
appears on the site it is a `mailto:` link, built from this one cell.

`title_held` in `data/assignments.csv` is almost always blank. Fill it in only for
somebody who has finished and whose title at the time was not what the job is
called now, so the *Past officers* list stays honest instead of quietly
restating history in this year's vocabulary.

**Headshots** go in `assets/images/officers/`, cropped to roughly 4:5 and about
500px wide, then named in the `photo` column of `data/people.csv`. Put the original
in `assets/images/officers/raw/` and `python tools/prepare_officers.py` makes
the cropped one. Somebody with no photo shows their initials, so nobody is left
off the page while you chase a headshot.

### If you want to be sure you have not broken anything

```bash
python tools/test_roles.py
```

That makes each of the changes above against the real data files, renders every
page, and checks what actually came out — including whether the old name and the
old address survive anywhere on the site. It puts the files back when it is
done. It also runs on every push, so a broken roster cannot reach the live site.

---

## Everything else on the site

### To change the gear list

Edit [`data/gear.php`](data/gear.php). It is split by how each item is booked —
through the Caltech Y, or through the club's Gear Officer — and grouped by
activity within that.

Keep it honest rather than complete. Specific models are worth listing only
where they change what someone can do with the item; ski lengths and binding
ranges belong on the Caltech Y's listing, which is always more current. **If an
item is retired or lost, delete the line** — a list promising equipment the club
no longer has is worse than a short one.

### To change a sponsor

Edit [`data/sponsors.php`](data/sponsors.php). If you have a logo file, put it in
`assets/images/sponsors/` and name it in the entry. **A sponsor with no logo file
still displays**, as a wordmark, so you can list them the day they say yes.

While the list is empty, the homepage's sponsor row is not rendered at all, and
the closing section carries the invitation instead. Add one sponsor and the row
appears.

### To add or change photos

Drop image files into `assets/images/`. The names matter, because the site looks
for them:

| File | Where it appears |
|---|---|
| `hero.jpg` | The big homepage banner. Wide, high quality, people visible. |
| `social.jpg` | The preview image when the site is shared on Slack or social media |
| `officers/*.jpg` | Officer headshots, named in the `photo` column of `data/people.csv` |
| `officers/raw/` | The ORIGINAL photos. Kept so a crop can be redone |
| `sponsors/*.svg` | Sponsor logos, named in `data/sponsors.php` |

**Every one of these is optional.** Where a photo is missing the site falls back
to a drawn topographic pattern, which is a deliberate design, not a broken image.
Add photos when you have good ones.

Photographs of **people doing things outdoors** are worth far more here than
empty landscapes. Resize them to about 2000px wide before uploading; a 6 MB
photo straight off a camera will make the site slow.

### To change the join links, the calendar, or contact details

Edit [`includes/config.php`](includes/config.php). Everything that is a URL or a
club fact lives in that one file: mailing list, Slack invite, email addresses,
gear rental, donation page, and the calendar itself.

**Three email addresses, and they are easy to confuse.** Getting this wrong once
means accidentally mailing every member, so they are named for what they do:

| Config key | Address | What it is |
|---|---|---|
| `links.officers` | `alpine@caltech.edu` | Shared mailbox reaching the officers. **Every "contact us" link uses this.** |
| `links.list` | `alpineclub@caltech.edu` | The mailing list. Anything sent here goes to every member. Never wire a contact button to it. |
| `links.secretary` | `alpine-secretary@caltech.edu` | Membership, including join requests from outside Caltech and JPL. |

One link is currently blank and should be filled in when you have it:
`links.slack`. It fails gracefully — the Join page shows a "request an invite"
email link instead of a dead Slack button.

**`links.secretary` needs checking before launch.** It is where membership
requests from outside Caltech and JPL land, and an unanswered join request is
the worst kind of broken link.

### The logo

The club mark is two crossed ice axes under a flame, and it lives in exactly one
place: **`assets/images/logo.svg`**, a single path in the accent-on-dark colour.
Everything else is generated from it, so nothing can drift out of step.

| File | What it is |
|---|---|
| `logo.svg` | the mark, on-dark colour. Masthead and footer. The source of record |
| `logo-on-light.svg` | the same path in the darker accent, for a pale background |
| `favicon.svg` | the mark inset on a rounded ink tile, for the browser tab |
| `apple-touch-icon.png`, `favicon-32.png`, `logo-512.png` | rasters, written by `tools/make_icons.py` |
| `social-default.png` | the link preview, written by `tools/make_social.py` |

**To change the mark:** replace the path in `logo.svg` and `logo-on-light.svg`,
then run both generators:

```bash
python tools/make_icons.py
python tools/make_social.py
```

Do not edit a PNG by hand; the next run of either script overwrites it.

**Why two colour files rather than `currentColor`:** both are loaded with
`<img>`, and an SVG used as an image has no parent to inherit from, so
`currentColor` there resolves to black. The colours are hardcoded and are
recorded in each file's comment. If you change the `alpenglow` or
`accent-on-dark` token in `assets/css/style.css`, change them to match.

Setting `site.logo` in `includes/config.php` to an empty string removes the mark
from the masthead and leaves the wordmark, which still looks deliberate.

### To deploy

See [Deployment](#deployment) below.

---

## Once a year, ideally

Run the health check (see [Health check](#health-check)) and then:

- [ ] Update `data/assignments.csv` after elections — that is also what makes
      newly empty jobs show as open, so there is nothing separate to do about
      those. Add anyone new to `data/people.csv` first
- [ ] Check `data/roles.csv` still describes the jobs the club actually has, and
      that `min_people` still reflects what the club genuinely needs rather than
      what it once hoped for
- [ ] `php tools/check.php --data` after any of the above
- [ ] Update `data/sponsors.php`
- [ ] Check anything in `data/benefits.csv` is still live and still advertisable
- [ ] Check the mailing list and Slack links still work
- [ ] Check the gear information is still accurate (prices, notice period, what is on the shelves)
- [ ] Refresh the photographs
- [ ] Make sure someone else knows this document exists

---

## Repository structure

```
index.php            Home
events.php           Events and trips (upcoming, calendar, past)
join.php             Join
gear.php             Gear rental
roles.php            Get Involved: the jobs, which are open, how to start
about.php            What we do, officers, contact
support.php          Sponsorship and donations
404.php              Page not found
sitemap.php          Generated sitemap
preview.php          Internal calendar test page (not linked, noindex)

includes/
  config.php         >>> START HERE. Links, calendar, club facts.
  config.local.php   Optional, git-ignored, overrides the above. Not committed.
  bootstrap.php      Loads config, helpers and the calendar. First line of every page.
  header.php         Page head, masthead, navigation
  footer.php         Footer and closing markup
  nav.php            The navigation menu, defined once
  partials.php       Event card, event row, page heroes, empty states, "write to us"
  people.php         The humans. Names, addresses, photographs, read once.
  roles.php          The jobs, joined to the people by role_id, and the whole of
                     "is this open" -- see its header comment. Nothing in it
                     compares a role title.
  officers.php       The same data in the shape a roster page wants: grouped,
                     sorted, past officers split off. No data of its own.
  validate.php       Does the data add up? Run by tools/check.php --data.
  benefits.php       Member discounts, and what may be shown publicly
  helpers.php        e(), cfg(), asset(), url(), icon()
  icons.php          Inline SVG icon sprite

data/                >>> EVERYTHING A PERSON EDITS. Nothing else is in here,
                     and nothing editable is anywhere else.

  people.csv         WHO EXISTS. One row per human, ever. Name, email, photo,
                     each written exactly once and read by every page.
  roles.csv          WHAT THE JOBS ARE. role_id is permanent; title is not.
                     min_people and max_people are how the site decides between
                     "open", "room for one more", and saying nothing.
  assignments.csv    WHO IS DOING WHICH JOB. The file that changes after an
                     election, and usually the only one. A job here with nobody
                     in it is what makes a vacancy appear -- nothing anywhere
                     says "vacant".

  sponsors.php       Who supports the club
  gear.php           What the club lends
  benefits.csv       Member discounts. Empty, and the section stays hidden
                     until it is not. Read includes/benefits.php first: some
                     deals may not be advertised publicly.

lib/calendar/        The Google Calendar integration. You should never need to
  Calendar.php       open these.
  Event.php
  IcsParser.php
  Sources.php

assets/
  css/style.css      One stylesheet. All colors and sizes are tokens at the top.
  js/site.js         Menu, dropdowns, FAQ. The site works without it.
  images/

cache/               Cached calendar data. Git-ignored, must be writable.

docs/                >>> Notes for whoever runs the site. Start at docs/README.md
  ORIENTATION.md     >>> READ FIRST if you have just taken over the site
  ACCESS.md          Getting an account and an SSH key, and handing them back
  DEPLOY.md          How to publish the site to the Caltech server
  SERVERS.md         The machines, hostnames, paths and permissions
  DEPLOY-LOG.md      What happened on each deploy, newest first
  HOSTING.md         Why it is hosted this way
  WRITING.md         How the copy should read

tools/
  deploy.sh          Uploads the site. See docs/DEPLOY.md.
  probe.php          The one file to upload first to a new server
  verify_deploy.py   Checks a deployed site from outside. No PHP needed.
  voice_check.py     Flags copy that reads as machine-written
  check.php          Command-line health check. --data checks only the
                     officer CSVs, and is the one to run after editing them.
  test_roles.py      Makes next year's officer changes against the real data,
                     renders every page, and checks what came out. Runs in CI.
  make_topo.py       Regenerates the contour-map artwork.
  make_social.py     Regenerates the link-preview image.
  import_guides.py   One-off import of the old site's outdoor guides.
  import_photos.py   One-off import of the old site's photographs into
                     assets/images/photos/ (deduped, capped at 1600px,
                     festival posters excluded). See its MANIFEST.json.
                     Not used by the site any more — kept so the guides can be
                     restored in one command if they are ever wanted back.
```

**The things that matter to a new officer are `data/`, `assets/images/`,
`includes/config.php`, and Google Calendar.** Everything else is machinery.

### What this site deliberately does not have

No activity pages, no news section, no per-trip pages, and no "upcoming events"
list anyone has to maintain. **The calendar is the description of what the club
does.** If you find yourself wanting to add a page that will need updating every
term, add a Google Calendar event instead.

---

## How the calendar works, and why it works this way

The site reads the club's **public `.ics` feed** from Google, on the server, in
PHP, and caches the result for 5 minutes.

**Why not do it in JavaScript in the browser?** Because it cannot be done.
Google's `.ics` endpoint does not send an `Access-Control-Allow-Origin` header
(verified against our own feed), so a browser refuses to read it from our pages.
This is not a matter of writing the JavaScript better.

**Why not use the Google Calendar API?** It would work, but it needs an API key.
A key means a Google Cloud project, referrer restrictions, quota, and a secret
that must not end up in a public GitHub repository — all maintained by whoever
happens to be an officer in four years. The public feed needs none of that.

**What server-side PHP buys us:**

- No API key, so nothing secret is ever in this repository
- Events are in the HTML, so they load fast, work with JavaScript disabled, and
  can be read by search engines
- One cached copy serves every visitor instead of every visitor calling Google
- If Google is unreachable, the site serves the last good copy rather than an
  empty page

**If Google is down and there is no cache at all**, the events section says so
and links to the calendar directly. It never shows a blank space.

### If you ever add a Google API key

The code is already structured for it, so this is a configuration change and not
a rewrite. `lib/calendar/Sources.php` contains a second source class that talks
to the API and returns identical objects.

1. Create a project in the Google Cloud console and enable the Calendar API.
2. Create an API key. **Restrict it** to the Calendar API and to the
   `alpine.caltech.edu` HTTP referrer.
3. On the server, copy `includes/config.local.example.php` to
   `includes/config.local.php` and put the key in it.

`config.local.php` is git-ignored, so the key stays off GitHub. There is also an
`ALPINE_GCAL_API_KEY` environment variable if the host offers one; it takes
precedence.

Setting a key switches the site to the API automatically. Leaving it empty keeps
the `.ics` feed. Note that the API source has never been run against a real key —
test it on `preview.php` first.

---

## Testing changes: `preview.php`

`preview.php` renders **any public Google Calendar** using the real site's
components. Use it to check how an unusual event will look before putting it on
the club calendar.

It is not linked from anywhere, is marked `noindex`, and is disallowed in
`robots.txt`. To lock it properly, set `calendar.preview_key` in
`includes/config.local.php`; the page then returns 404 unless the URL carries
`?key=<that value>`.

**Set up a test calendar once:**

1. In Google Calendar: Settings → Add calendar → Create new calendar. Call it
   "Alpine Club (test)".
2. Open its settings → Access permissions → tick **Make available to public**.
   This is the step that lets the site read it.
3. Under "Integrate calendar", copy the **Calendar ID**.
4. Paste it into `includes/config.php` as `calendar.test_calendar_id`.

Then visit `preview.php`. It shows the rendered cards, a table of exactly what
the parser extracted from every event, and a checklist of cases worth creating
(all-day, multi-day, repeating, cancelled, no location, HTML in the description,
and so on).

Tick **Skip cache** to see calendar changes immediately instead of waiting for
the five-minute cache to expire.

---

## Health check

```bash
php tools/check.php
```

Reports the PHP version, whether the cache directory is writable, whether the
calendar fetched successfully, how many events it found, which config links are
still blank, and whether any photos have been added. Add `--links` to test that
every URL in the config actually resolves.

Run it after any change, and once a year regardless. If "Upcoming" is empty here,
it is empty because the calendar has no future events, not because the site is
broken.

---

## Deployment

**The procedure is [`docs/DEPLOY.md`](docs/DEPLOY.md).** It is a checklist, and
following it in order matters more than understanding it. The machines it talks
about are in [`docs/SERVERS.md`](docs/SERVERS.md), and what happened last time
somebody deployed is in [`docs/DEPLOY-LOG.md`](docs/DEPLOY-LOG.md).

Since 2026-08-18 there has been a staging site at
**<https://staging.alpine.caltech.edu>**, provisioned by IMSS. That is where
changes get tested. `alpine.caltech.edu` still runs the old Caltech Sites
version, and nothing in this repository can affect it.

Everything here is deployable as-is, with nothing to build or compile:

```bash
./tools/deploy.sh --dry-run YOUR_CALTECH_USERNAME    # stage and list, send nothing
./tools/deploy.sh YOUR_CALTECH_USERNAME              # upload to staging
python tools/verify_deploy.py https://staging.alpine.caltech.edu
```

You have to be on campus, or on the Caltech VPN with **Tunnel All**. The script
sends what git has committed, leaves the server's `cache/` and
`config.local.php` alone (that second one holds any API key, and overwriting it
is the one mistake here that is annoying to undo), and sets the file permissions
IMSS asks for.

### What automated deployment could look like later

When someone has the time, a GitHub Actions workflow triggered on pushes to
`main` could run the same `rsync` over SSH, using a deploy key stored in the
repository's Actions secrets. That requires IMSS to allow key-based SSH to the
web host, so ask them first.

A simpler intermediate step, if the web server has git: clone the repository on
the server and run `git pull` to deploy. That gives you one-command deploys and
an easy rollback (`git checkout <previous-commit>`) without needing CI at all.

Do not put deploy credentials in this repository under any circumstances.

### Server requirements

- PHP 7.4 or newer (tested on 8.3)
- Either the `curl` extension or `allow_url_fopen` — for reading the calendar
- A writable `cache/` directory (the site still works without one, but it will
  call Google on every page load)
- Apache, for `.htaccess` to take effect. On nginx those rules must be moved into
  the server config; nothing there is required for the site to function.

---

## Editing the design

All colors, type sizes and spacing are CSS custom properties in the `:root`
block at the top of `assets/css/style.css`. Change a value there and it updates
consistently across the whole site. The rest of the file is organized into
numbered sections with a table of contents at the top.

The site loads one webfont (Archivo) from Google Fonts. To remove it, delete the
two `<link>` tags in `includes/header.php` and drop `'Archivo',` from
`--font-sans`; the fallback stack is close enough that nothing will break.

---

## A note on scope

This site is deliberately small. Six real pages, one stylesheet, no framework,
and a calendar integration that runs itself.

Before adding a system to it, ask whether an officer three years from now will
understand how to maintain it. A beautiful, fast site with six excellent pages
and automatic events is worth more than a sophisticated web application that
nobody maintains.
