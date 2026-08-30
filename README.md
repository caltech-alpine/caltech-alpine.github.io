# Caltech Alpine Club website

The code behind [alpine.caltech.edu](https://alpine.caltech.edu).

> ## Are you the Secretary, or an officer who needs to change something?
> ## Read **[SECRETARY.md](SECRETARY.md)** instead. That is the whole job, in one page.
>
> This file is the developer's view: how the site is put together and why. You
> need none of it to update the officers, the gear, the sponsors or the links.

---

## What this is

Plain HTML, CSS, JavaScript and a small amount of PHP. There is **no build step,
no npm, no database and no CMS.** If you can edit a text file and copy it to a
server, you can maintain this site.

It is built on one assumption: **routine club operations should not require
editing the website.** Events come from Google Calendar. Conversations happen on
Slack. This repository holds the things that stay true for years.

### The three copies, and what each is for

| | Address | What it is |
|---|---|---|
| **Production** | <https://alpine.caltech.edu> | The club's live site. **Still the Caltech Sites (Wagtail) page** as of 2026-08-30; nothing here reaches it yet. Moving it is a decision plus an IMSS request - [docs/DEPLOY.md](docs/DEPLOY.md), *The production cutover*. |
| **The Caltech copy of this repo** | <https://staging.alpine.caltech.edu> | This repository, running as real PHP on Caltech hosting. What `bin/deploy` publishes to. |
| **The preview** | <https://caltech-alpine.github.io> | A static render of `main`, rebuilt by [`.github/workflows/pages.yml`](.github/workflows/pages.yml) on every push and every 30 minutes. `noindex`, so it cannot compete with the real site in search. **This is the normal preview for content changes.** |

Use the preview for anything that is data or wording. Use the Caltech copy for
anything that depends on the *server*: `.htaccess`, PHP behaviour, `preview.php`,
caching, headers. [docs/HOSTING.md](docs/HOSTING.md) §2 is the full list of what
a static render cannot show.

### How a change reaches the website

```
edit on GitHub  ->  the workflow checks it  ->  the preview rebuilds
                                                        |
                         somebody runs bin/deploy on portal.caltech.edu
                                                        |
                                              the Caltech copy updates
```

The checks are not advisory. `tools/server-deploy.sh` asks GitHub whether the
commit it is about to publish passed, and **refuses to publish it if it did
not.** Nobody has to remember to look.

---

## Where things are edited

The instructions are in [SECRETARY.md](SECRETARY.md). The map, so that this file
is not a second source of truth for them:

| | |
|---|---|
| Events | the club's **Google Calendar** - never this repository |
| Officers, roles, people | `data/people.csv`, `data/roles.csv`, `data/assignments.csv` |
| Gear, sponsors, member deals | `data/gear.php`, `data/sponsors.php`, `data/benefits.csv` |
| Links, addresses, calendar id, club facts | `includes/config.php` |
| Photographs | `assets/images/` |

**Those five rows are the whole editable surface.** Everything else in the
repository is machinery, and changing it is development rather than
administration.

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

data/                >>> THE LISTS A PERSON EDITS. Everything in here is
                     content, and nothing in here is code. The only other
                     editable file is includes/config.php, which holds the
                     things that are single values rather than lists: the
                     links, the addresses, the calendar id.

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

SECRETARY.md         >>> THE OFFICER'S MANUAL. The only file most people who
                     ever maintain this site need to open.

docs/                Reference for whoever runs the site. Start at docs/README.md
  DEVELOPER.md       Setting up a local copy, and the tests. Optional.
  ACCESS.md          Getting an account and an SSH key, and handing them back
  DEPLOY.md          How the site is published, in full
  SERVERS.md         The machines, hostnames, paths and permissions
  DEPLOY-LOG.md      What happened on each deploy, newest first
  HOSTING.md         Why it is hosted this way
  WRITING.md         How the copy should read

tools/
  server-deploy.sh   >>> THE DEPLOY. Run through bin/deploy on the server.
                     Gate, backup, publish, version stamp, smoke test.
  deploy.sh          The laptop route: uploads over SSH. Bootstrap and
                     fallback only. See docs/DEPLOY.md.
  portal_daemon.py   Holds one authenticated ssh session to portal so a run of
                     server commands costs one Duo prompt instead of a dozen.
  probe.php          The one file to upload first to a new server
  verify_deploy.py   Checks a deployed site from outside, including which
                     commit it is running. No PHP needed.
  build_static.py    Renders the site to HTML for the Pages preview.
  prepare_officers.py  Makes the 528x660 roster crop from a raw headshot.
  voice_check.py     Flags copy that reads as machine-written
  audit.py           Checks the rendered markup. Needs PHP locally.
  check.php          Command-line health check. --data checks only the
                     officer CSVs, and is the one to run after editing them.
  test_roles.py      Makes next year's officer changes against the real data,
                     renders every page, and checks what came out. Runs in CI.
  check_docs.py      Every relative link in every .md resolves, and SECRETARY.md
                     links every file it tells an officer to edit. Runs in CI.
  make_topo.py       Regenerates the contour-map artwork.
  make_icons.py      Regenerates the favicons and app icons from logo.svg.
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

**One command, run on the server, and it is documented in exactly one place:
[`docs/DEPLOY.md`](docs/DEPLOY.md).**

```bash
/srv/www.alpine.caltech.edu/www/bin/deploy
```

That wrapper resets the server's checkout to GitHub's `main` and runs
[`tools/server-deploy.sh`](tools/server-deploy.sh), so the deploy logic is
version controlled and updates itself. It refuses a commit whose GitHub checks
failed or have not finished, backs the site up first, writes a `version.txt`
saying what is live, and then fetches the public address to confirm the change
landed. `--rollback` puts the previous copy back; `--force` overrides the check
gate in an emergency.

There is a second route, `tools/deploy.sh`, which uploads from a laptop over
SSH. It exists to bootstrap a new server, and for the day GitHub is unreachable.
It is not the normal path - see [docs/DEPLOY.md](docs/DEPLOY.md) §B.

GitHub Actions cannot do this for us: `portal.caltech.edu` answers only from
campus or the Caltech VPN, and GitHub's runners are on the public internet. So
publishing is a deliberate human command, which is a reasonable thing for it to
be.

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

### The logo

The club mark is two crossed ice axes under a flame, and it lives in exactly one
place: **`assets/images/logo.svg`**, a single path in the alpenglow accent.
Everything else is generated from it, so nothing can drift out of step.

| File | What it is |
|---|---|
| `logo.svg` | the mark. Masthead and footer. The source of record |
| `favicon.svg` | the mark inset on a rounded ink tile, for the browser tab |
| `apple-touch-icon.png`, `favicon-32.png`, `logo-512.png` | rasters, written by `tools/make_icons.py` |
| `social-default.png` | the link preview, written by `tools/make_social.py` |

**To change the mark:** replace the path in `logo.svg` and `favicon.svg`, then
run both generators:

```bash
python tools/make_icons.py
python tools/make_social.py
```

Do not edit a PNG by hand; the next run of either script overwrites it.

**Why the colour is hardcoded rather than `currentColor`:** the mark is loaded
with `<img>`, and an SVG used as an image has no parent to inherit from, so
`currentColor` there resolves to black. If you change the `alpenglow` token in
`assets/css/style.css`, change `logo.svg` and `favicon.svg` to match.

**One colour, not two.** There was briefly an `accent-on-dark` variant for the
dark pages. That token is the small-text lift for dark sections, not the accent;
the palette names `alpenglow` for "fills, buttons, icons, large text,
decoration", and using anything else put the mark a step lighter than every
other accent on the same screen.

Setting `site.logo` in `includes/config.php` to an empty string removes the mark
from the masthead and leaves the wordmark, which still looks deliberate.

## A note on scope

This site is deliberately small. Six real pages, one stylesheet, no framework,
and a calendar integration that runs itself.

Before adding a system to it, ask whether an officer three years from now will
understand how to maintain it. A beautiful, fast site with six excellent pages
and automatic events is worth more than a sophisticated web application that
nobody maintains.
