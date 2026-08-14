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

The website reads the calendar every 30 minutes and builds the event cards
itself. You do not touch this repository, and you do not create a page per trip.

Optionally, put an activity tag in square brackets at the start of the title:

```
[HIKE] Mount Baldy day hike
[CLIMB] Joshua Tree weekend
[SNOW] Mammoth trip
[RUN] Eaton Canyon trail run
[BIKE] Brown Mountain ride
[SOCIAL] Alpine Club lunch
[SOCIAL] Post-run coffee
```

The tag becomes the activity label on the card and is removed from the title
shown to visitors. Events with no tag still work — the site makes a sensible
guess from words in the title, and if it cannot, the card simply has no label.

The tags are defined in [`data/activities.php`](data/activities.php). Add an
entry there and you can start using a new tag the same day.

**Other things worth knowing:**

- All-day, multi-day and repeating events all work.
- Cancelling an event in Google Calendar shows a "Cancelled" badge rather than
  making it vanish, so people who were planning to come find out.
- Events move from "Upcoming" to "Past events" by themselves. Nobody has to
  write a trip report for the archive to exist.
- **Whatever you put in the event description appears on the card**, and every
  event has a **Details** button that opens the full text in a pop-up. Links
  survive there, so a sign-up sheet or a map pin in the description is
  clickable. Keep the first sentence useful — that is the part shown on the
  card itself.
- **A repeating event shows only its next occurrence**, labelled "Weekly on
  Tuesdays" or similar, so a standing weekly run does not bury everything else.
  Editing or deleting a single week in Google Calendar works as you would
  expect.

### To change an officer

Edit [`data/officers.csv`](data/officers.csv). One row per person:

```php
array(
    'name'    => 'Jane Doe',
    'role'    => 'Climbing Commodore',
    'handles' => 'Climbing trips and the bouldering wall',
    'group'   => 'Activity Leaders',
    'photo'   => 'jane-doe.jpg',
),
```

**When somebody steps down, do not delete them.** Add an `'until'` year:

```php
    'until'   => 2026,
```

and they move to the *Past officers* list on the About page by themselves. That
is the whole alumni system — the club keeps a record of who ran it without
anyone maintaining a second list, and nothing is ever lost by an edit.

**You do not need to order this file.** The page sorts officers by seniority of
role and then alphabetically, so two people sharing a title always appear
together. The seniority table is `alpine_role_rank()` in
`includes/officers.php` — edit it if the club invents a new position.

`handles` is what makes the page useful: it tells a visitor who to email about
what. Leave it empty to hide the line. `email` is optional; club addresses are
fine to publish, personal ones deserve a second thought.

**Headshots** go in `assets/images/officers/`, cropped to roughly 4:5 and about
500px wide, then named in the `photo` field. If somebody has no photo the page
shows their initials instead, so nobody is left off while you chase a headshot.

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

While the list is empty, the homepage shows a short sponsorship invitation
instead of an empty row of logos. Add one sponsor and it switches over.

### To add or change photos

Drop image files into `assets/images/`. The names matter, because the site looks
for them:

| File | Where it appears |
|---|---|
| `hero.jpg` | The big homepage banner. Wide, high quality, people visible. |
| `social.jpg` | The preview image when the site is shared on Slack or social media |
| `officers/*.jpg` | Officer headshots, named in `data/officers.csv` |
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

Two links are currently blank and should be filled in when you have them:
`links.slack` and `links.donate`. Both fail gracefully — the Join page shows a
"request an invite" email link instead of a dead Slack button.

**`links.secretary` needs checking before launch.** It is where membership
requests from outside Caltech and JPL land, and an unanswered join request is
the worst kind of broken link.

### To change the logo

Put the file in `assets/images/` and set `site.logo` in `includes/config.php` to
its filename. Until then the header shows a placeholder ice axe next to the
wordmark. `assets/images/favicon.svg` is the browser tab icon and is separate.

### To deploy

See [Deployment](#deployment) below.

---

## Once a year, ideally

Run the health check (see [Health check](#health-check)) and then:

- [ ] Update `data/officers.csv` after elections
- [ ] Update `data/sponsors.php`
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
  partials.php       Event card, event row, empty states, "write to us" blocks
  helpers.php        e(), cfg(), asset(), url(), icon()
  icons.php          Inline SVG icon sprite

data/                >>> The files officers actually edit
  officers.csv
  sponsors.php
  activities.php     The [TAG] labels understood on calendar event titles

lib/calendar/        The Google Calendar integration. You should never need to
  Calendar.php       open these.
  Event.php
  IcsParser.php
  Sources.php
  Tags.php

assets/
  css/style.css      One stylesheet. All colours and sizes are tokens at the top.
  js/site.js         Menu, dropdowns, FAQ. The site works without it.
  images/

cache/               Cached calendar data. Git-ignored, must be writable.

tools/
  check.php          Command-line health check.
  make_topo.py       Regenerates the contour-map artwork.
  make_social.py     Regenerates the link-preview image.
  import_officers.py One-off import of the roster and headshots.
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
PHP, and caches the result for 30 minutes.

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
the 30-minute cache to expire.

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

`GitHub repository → Caltech Unix web server → alpine.caltech.edu`

### The simple way (start here)

Copy the files to the server over SFTP or `scp`. Everything in this repository is
deployable as-is; there is nothing to build or compile.

```bash
# from the repository root, adjust the path to match the server
rsync -av --delete \
  --exclude '.git' \
  --exclude 'cache/*' \
  --exclude 'includes/config.local.php' \
  ./ user@server.caltech.edu:/path/to/web/root/
```

Then, **once, on the server**:

```bash
chmod 775 cache          # the site must be able to write here
php tools/check.php      # confirm everything works from the server itself
```

The `--exclude` lines matter. Never overwrite the server's `cache/` (it is
regenerated anyway) or its `config.local.php` (that is the copy holding any key).

If `rsync` is not available, plain SFTP with an FTP client works — just do not
delete `cache/` or `config.local.php` on the far side.

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

All colours, type sizes and spacing are CSS custom properties in the `:root`
block at the top of `assets/css/style.css`. Change a value there and it updates
consistently across the whole site. The rest of the file is organised into
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
