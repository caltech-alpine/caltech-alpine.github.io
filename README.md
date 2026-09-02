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
| **Production** | <https://alpine.caltech.edu> | **The club's live site, and since 2026-09-02 it is this repository.** What `bin/deploy` publishes to, and what search engines index. The Wagtail page that used to answer here is gone. |
| **The same files, unindexed** | <https://staging.alpine.caltech.edu> | The same document root at a second hostname, held out of search by `.htaccess`. An alias, not a second copy - it cannot be behind production. |
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

assets/              THE SERVED TREE. Both deploy routes copy this wholesale,
  css/style.css      so nothing belongs in here that a browser never asks for.
                     One stylesheet; all colors and sizes are tokens at the top.
  js/site.js         Menu, dropdowns, FAQ. The site works without it.
  images/            Including logo.svg, favicon.svg and logo-full.svg, which
                     are GENERATED from art/ - see "The logo" below.

art/                 The logo drawings, as drawn. Source for tools/trace_logo.py,
                     committed because a traced SVG cannot be re-traced from
                     itself, and excluded from both deploys.

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
  audit.py           Checks the rendered markup, and that no colour in the
                     logo files is one style.css does not declare. Needs PHP.
  palette.py         The site's colours, read from style.css. Every tool that
                     draws asks this rather than carrying a hex literal.
  trace_logo.py      Traces art/*.png into logo.svg, favicon.svg and
                     logo-full.svg, recolouring to the site palette.
  check.php          Command-line health check. --data checks only the
                     officer CSVs, and is the one to run after editing them.
  test_roles.py      Makes next year's officer changes against the real data,
                     renders every page, and checks what came out. Runs in CI.
  check_docs.py      Every relative link in every .md resolves, and SECRETARY.md
                     links every file it tells an officer to edit. Runs in CI.
  make_topo.py       Regenerates the contour-map artwork.
  make_icons.py      Regenerates the favicons and app icons from the SVGs.
  make_social.py     Regenerates the link-preview image.
  import_guides.py   One-off import of the old site's outdoor guides.
  archive_old_site.py  Takes a copy of the OLD alpine.caltech.edu -- every
                     page as HTML, as plain text and as prose-only, plus every
                     photograph and PDF -- before that hostname is repointed
                     here and the Wagtail site stops existing. Writes to
                     ../old-site-archive, outside this repository.
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

The club logo is a sun behind a mountain range, followed by the club's name.
**The source of record is the drawing, not the SVG:** the artwork lives in
`art/`, and everything the site serves is generated from it, so nothing can
drift out of step.

```
art/logo.png      the logo, as drawn: sun, mountains, CALTECH / ALPINE CLUB
art/favicon.png   an open orange C with a mountain breaking out of it, no words
```

**The name is inside the logo.** Anywhere `logo.svg` or `logo-on-dark.svg` is
placed must not also print "Caltech Alpine Club" as text beside it. The
masthead used to do exactly that, because the older mark had no words; the
`.brand__text` spans in `includes/header.php` are now only the fallback for an
empty `site.logo_dark`.

**The favicon is a different drawing, not a smaller render of the logo.** At 16
pixels a wordmark is a smudge, so the favicon keeps only the C and one peak.
That is why a favicon has always been a separate file: 16px is a different
design problem, not a smaller one.

**And 16px is what decides the mark's weight.** The C was redrawn heavier on
2026-09-02. Fitting the ring's circle on both drawings (sub-pixel: outer radius
p10/p90 453.5/454.5) gives a stroke of 0.263 × radius before and 0.384 after —
**46% heavier**. The old stroke landed on about 1.5 device pixels in a 16px tab
and anti-aliased into a smear; this one lands on about 2.5 and holds. The arc's
endpoints barely moved, so it is the same mark at a different weight.

`art/` sits outside `assets/`, and is excluded by both deploy routes, because
`assets/` is the served tree and these are megabytes of source nobody requests
over HTTP. Same reason `docs/` and `tools/` are not in there.

| File | Built from | What it is |
|---|---|---|
| `logo.svg` | `art/logo.png` | the logo, dark type. **For light backgrounds** |
| `logo-on-dark.svg` | `art/logo.png` | the same, light type. Masthead and footer, which are ink |
| `favicon.svg` | `art/favicon.png` | the wordless mark, **transparent, mountain flips on `prefers-color-scheme`** |
| `favicon-on-dark.svg` | `art/favicon.png` | the same, transparent, dark colour baked in |
| `mark.svg`, `mark-on-dark.svg` | `art/favicon.png` | the mark **on transparency**, for slides and print |
| `favicon.ico` **(site root)** | `favicon.svg` | 16+32+48 in one file, each rendered at its own size |
| `apple-touch-icon.png`, `icon-192.png`, `icon-512.png`, `icon-maskable-512.png` | `favicon.svg` | home-screen rasters, `tools/make_icons.py` |
| `mark-512.png`, `mark-on-dark-512.png` | `mark*.svg` | transparent rasters — what to hand somebody who asks for "the logo as a PNG" |
| `social-default.png` | `favicon.svg` | the link preview, written by `tools/make_social.py` |

**The light and dark logos are one trace, not two drawings.** `trace_logo.py`
traces the artwork once and writes it twice, swapping a single token, so the
pair cannot disagree with each other. `currentColor` would be the obvious
alternative and does not work: the logo is loaded with `<img>`, and an SVG used
as an image has no parent to inherit from.

**Every square icon comes from `favicon.svg`**, never from the lockup. The logo
is about 3.9:1, so rendering it into a 512 square either squashes it or leaves a
thin strip in a lot of nothing. Somebody asking for "the logo as a PNG" for an
avatar wants `mark-512.png`; somebody who wants the lockup wants `logo.svg`, at
its own shape.

**Nothing the browser shows has a ground, and that took two tries.** A tab
should show the mark, not a pale square sitting in the tab strip — Kyle's call,
2026-09-02. The difficulty is that the mountain is `--ink`, a near-black, so a
*fixed* transparent icon merges into a dark tab strip and reads as a bare orange
ring. A full-bleed `--paper` rect was the first answer, and it worked at the
cost of the square.

**The second answer flips the artwork instead of hiding it.** `favicon.svg`
carries its own `@media (prefers-color-scheme: dark)` block and repaints the
mountain `--paper`. No ground, nothing to disappear. Verified in a real browser
rather than by reading the CSS — drawing the SVG to a canvas and sampling the
pixel back gives ink `20,24,26` under a light scheme, paper `249,246,240` under
dark, and corner alpha `0` in both. `favicon-on-dark.svg` is linked with
`media="(prefers-color-scheme: dark)"` as belt and braces, for a browser that
honours the attribute on `<link>` but not a query inside an SVG it is using as
an icon.

**Three files are opaque, each for a reason that is not aesthetic.**
`apple-touch-icon.png` and the two `icon-*.png` because iOS masks a home-screen
icon to a rounded square and composites alpha to **black**, so a transparent
icon arrives as a mark in a black tile nobody chose with a near-black mountain
invisible inside it. `favicon.ico` because its real consumer is Windows
pin-to-taskbar, the taskbar is dark by default, and an `.ico` has no way to
express a media query. Those three get `--paper` imposed by `make_icons.py` at
render time, never by the SVG.

**All of it is asserted, not trusted.** `make_icons.py` fails if `favicon.svg`
contains a `<rect>`, if it lacks a `prefers-color-scheme` rule, or if any
output's corner alpha disagrees with what it is supposed to be — in both
directions, because both mistakes look fine in a file listing. One of them
shipped for two days: `logo-512.png` was documented here and in `make_icons.py`
as transparent and had not been since `favicon.svg` first gained a ground on
2026-08-31. It was a warm off-white square, a dirty box on any dark background.
It is now `mark-512.png`. `tools/audit.py` reads **both** `fill="#hex"` and
`fill:#hex`, so the dark-mode colour — which lives only in the style block, and
which nobody would catch by eye — is checked like every other.

**`favicon.ico` and `site.webmanifest` live at the site root**, not in
`assets/`. The `.ico` is the only path crawlers, feed readers, link unfurlers
and pin-to-taskbar try, and they read no HTML first — so it was a 404. The
manifest is what makes Android Chrome use the club's icon for a home-screen
bookmark instead of falling back to the Apple one; it declares
`display: browser` deliberately, because this is a website whose useful links
go to Google Calendar and Slack, and stripping the browser chrome would trap
somebody there. Both files are in `ASSET_FILES` in `tools/build_static.py`, so
the static preview serves them exactly as the PHP copy does, and `.htaccess`
declares their media types — **not cosmetic**, because the site also sends
`X-Content-Type-Options: nosniff`, and under `nosniff` a manifest served as
`application/octet-stream` is refused rather than guessed.

**The icon block in `includes/header.php` is five lines and is not sorted the
way it reads.** A browser that cannot choose by `sizes` takes the *last*
`rel=icon` it understands, so the legacy `.ico` is declared **first** and the
SVG last. Written the intuitive way round — vector first, bitmaps after — a
modern browser settles on a bitmap in a tab that would have taken vector.
There are no standalone `favicon-16/32/48.png` files: the `.ico` already holds
those three sizes and has to exist regardless.

**The lockup still carries the OLD mark, and should not for long.** `art/logo.png`
was drawn before the C was redrawn: its ring measures 0.284 × radius against the
favicon's 0.384, and its mountain sits *inside* the ring with a baseline rule
fused to it rather than breaking out of it. Those are two different marks on the
same page — the lockup in the masthead, the favicon in the tab. Fixing it needs a
new `art/logo.png`, not a code change: the rule and the mark share paths in the
current raster, so there is no column of empty pixels to cut the old mark out at
(measured — the widest internal gutter is zero).

**To change the mark:** drop the new drawing into `art/` under the
same filename, then run the three generators in this order:

```bash
python tools/trace_logo.py
python tools/make_icons.py
python tools/make_social.py
```

`trace_logo.py` traces each flat colour as its own layer, recolours it to the
site's palette on the way through, and prints how far each traced layer is from
the drawing it came from. Under about 2% is fine; the current logo is 0.43%.
`--check` reports without writing.

Do not edit an SVG or a PNG in `assets/images/` by hand. The next run of a
generator overwrites it.

**No tool carries a colour literal.** `tools/palette.py` reads the `hsl()`
tokens out of `assets/css/style.css` and converts them; `trace_logo.py`,
`make_social.py` and `audit.py` ask it by name. That is not tidiness: the three
had grown their own copies and three of those copies had drifted, one of them
by a single point on each channel, which is invisible alone and a visible seam
where the two colours meet. Change a token in the stylesheet and re-run the
generators. `tools/audit.py` fails the build if a fill in a logo file is not a
colour some token resolves to.

**The favicon carries its own background; the logo does not.** The disc is
opaque, so one file reads on the ink masthead, on a paper page and on whatever
colour a browser paints its tab strip. The logo's field is keyed out, including
the ridgeline between the sun and the mountain, so that gap is the page showing
through and is right on both.

Setting `site.logo_dark` in `includes/config.php` to an empty string removes the
logo from the masthead and falls back to setting the club's name as text, which
is what the masthead did before the artwork had words in it.

## A note on scope

This site is deliberately small. Six real pages, one stylesheet, no framework,
and a calendar integration that runs itself.

Before adding a system to it, ask whether an officer three years from now will
understand how to maintain it. A beautiful, fast site with six excellent pages
and automatic events is worth more than a sophisticated web application that
nobody maintains.
