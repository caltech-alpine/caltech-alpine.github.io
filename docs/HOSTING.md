# Hosting: the Pages pilot, and how to go dynamic with Caltech

Written 2026-08-14, while standing up the GitHub Pages pilot. The point of this
file is that the pilot is a **staging post, not a destination** — it exists so
the committee can look at the site and decide. This records exactly what the
pilot gives up, and exactly what has to happen to run the real thing on Caltech
infrastructure.

---

## 1. Why there are two options at all

The site is plain PHP with no build step and no dependencies. Every page is
assembled on the server: `includes/header.php`, the `cfg()` config layer, the
officer roster in `data/people.csv` and `data/assignments.csv`, and — the only genuinely live part — the
club's Google Calendar, fetched as ICS and parsed in `lib/calendar/`.

**GitHub Pages does not execute PHP.** It serves files. Pushed there as-is, every
URL either 404s or offers `index.php` to the visitor as a download.

So publishing to Pages requires rendering the site to HTML first, which is what
`tools/build_static.py` does. It works because of a fact worth stating plainly:

> **Nothing on this site depends on who is asking.** No forms, no logins, no
> query parameters, no sessions, no user input of any kind. The only input is
> the Google Calendar, and the PHP layer already caches that for 5 minutes.

A site whose output is the same for every visitor is a site that can be
pre-rendered without loss. That is why the static pilot is a faithful copy and
not a degraded one.

---

## 2. What the pilot actually gives up

| | Static (Pages) | Dynamic (Caltech PHP) |
|---|---|---|
| Page content, layout, styling | identical | identical |
| Event dialogs, FAQ collapse, nav menu | works — client-side JS | works |
| Recurring-event collapsing, past archive | computed at build | computed per request |
| Officer roster, alumni `until` logic, gear inventory, sponsors | computed at build | computed per request |
| 404 page | `404.html`, served by Pages | `ErrorDocument` in `.htaccess` |
| Sitemap | rendered to `sitemap.xml` | live `sitemap.php` |
| **Calendar freshness** | as of the last build (30-min cron) | per request, 5-min cache |
| **Editing `data/assignments.csv`** | push, wait for the build | upload, live immediately |
| **`preview.php`** | **not available** | works |
| **`.htaccess`** | **ignored** — no custom headers, no directory-listing block, no deny rules on `.md`/`.json` | fully applied |
| Custom domain `alpine.caltech.edu` | possible but needs a CNAME and IMSS DNS | native |

Read the freshness row carefully: the *effective* difference for a visitor is
small but no longer nil. The PHP site shows calendar data up to **five** minutes
old; the Pages build shows it up to **thirty**, plus however late GitHub runs the
job. The other losses are `preview.php`, the `.htaccess` headers, and the
edit-to-live delay for officers.

### The two scheduling caveats

1. GitHub runs `schedule:` workflows **late** when its fleet is busy — usually
   minutes, occasionally much longer. Nothing here is time-critical.
2. GitHub **disables scheduled workflows in a repository with no activity for 60
   days**, notifying only the last committer. If the calendar silently stops
   updating months from now, check the Actions tab first. A dynamic host has no
   equivalent failure mode.

---

## 3. Going dynamic with Caltech

### 3.1 What to ask IMSS for

Caltech departmental and club sites run on IMSS-managed Unix web hosting. Open a
ticket (help.caltech.edu) and ask for:

- **Web space for the Alpine Club**, with the hostname `alpine.caltech.edu`
  pointed at it. That hostname already exists and serves the current Wagtail
  site, so the real ask is *"how do we move alpine.caltech.edu to a plain
  PHP document root we control"* — which may mean coordinating with whoever
  administers the existing Caltech Sites/Wagtail instance.
- **PHP 7.4 or newer.** The site is tested on 8.3 and uses nothing exotic.
- **Either the `curl` extension or `allow_url_fopen`.** One of the two is
  required to read the calendar; `lib/calendar/Sources.php` tries cURL first and
  falls back to streams.
- **SFTP or SSH access** for whoever will deploy, plus a second account so the
  club is not locked out when that person graduates. This matters more than it
  sounds: officer turnover is annual.
- **A writable directory** for `cache/` (`chmod 775`). Without it the site still
  works, but it calls Google on every single page load.
- Confirmation of whether the server runs **Apache or nginx**. `.htaccess` is
  read by Apache only; under nginx the equivalent rules move into the server
  config, which means an IMSS change request rather than a file we control.

### 3.2 Deploying

**This section used to carry its own `rsync` recipe. It has been removed** — it
was written in August 2026 before the server existed, it did not match what was
built, and two procedures for one job is how a club ends up doing neither
correctly. There is one:

```bash
/srv/www.alpine.caltech.edu/www/bin/deploy
```

[DEPLOY.md](DEPLOY.md) is the only description of it. The recipe that was here
was wrong in three ways worth recording, because each is a trap the real script
had to be taught: `rsync` is not installed on the machine deploys were run from;
`--delete` with those excludes would have removed the server's own `logs/`; and
`php tools/check.php` cannot be run on the server at all, because
`portal.caltech.edu` has no PHP. See [SERVERS.md](SERVERS.md).

### 3.3 What to turn back on once it is dynamic

- **Drop `--noindex`** from the Pages workflow, or stop publishing to Pages
  entirely (see §4).
- **`preview.php` returns.** Set `calendar.preview_key` in
  `includes/config.local.php` on the server if it should be key-protected;
  otherwise it stays unlisted and `noindex`.
- **`.htaccess` is being read** — confirmed on staging 2026-08-19 and again
  2026-08-30: all three security headers arrive and every protected path returns
  403. `tools/verify_deploy.py` re-checks it on every deploy, so this needs
  re-confirming on a *new* server and not otherwise.
- **`php tools/check.php` cannot be run from the server**, which is what an
  earlier version of this line asked for. There is no PHP on the machine we can
  log into, and no shell on the machine that runs PHP. Run it on a laptop, and
  use `tools/verify_deploy.py` for everything visible over HTTP.

### 3.4 Clean URLs, if wanted

Both the current site and the static build use `.php` / `.html` extensions. To
serve `/events` instead, add to `.htaccess`:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME}.php -f
RewriteRule ^(.+)$ $1.php [L]
# and redirect the old form so links do not silently duplicate
RewriteCond %{THE_REQUEST} " /([^?\ ]+)\.php"
RewriteRule ^ /%1 [R=301,L]
```

If this is done, update `sitemap.php`, the `canonical` tag in
`includes/header.php`, and the internal `url()` calls to match. Do it **once**
and commit to it — half-migrated URLs are how a site ends up with two of every
page.

---

## 4. If both run at once

Only one can be canonical. The rules:

- The dynamic site at `alpine.caltech.edu` is production.
- The Pages build keeps `--noindex` **permanently** if it stays up as staging.
  Two indexable copies of the same content is a self-inflicted duplicate-content
  problem, and the Pages copy will sometimes outrank the real one.
- `tools/build_static.py --base-url` rewrites `canonical` and `og:url` to the
  build's own location. If you would rather have the staging copy point at
  production, change that in `rewrite_links()` — but do not combine `noindex`
  with a cross-site canonical; pick one.

---

## 5. Decision shortcut

- **Just need the committee to look at it** → the Pages pilot. Already built.
- **Want it to be the club's real site** → Caltech PHP hosting. Ask IMSS for
  §3.1, deploy with §3.2, then §3.3.
- **Want Pages to be the real site** → possible, but accept losing
  `preview.php` and the `.htaccess` headers, accept the 60-day scheduled-workflow
  expiry, and point `alpine.caltech.edu` at Pages via a `CNAME` file plus an
  IMSS DNS change. Cheapest to run, most dependent on GitHub.

---

## 6. Rollback

The old Wagtail site at `alpine.caltech.edu` is untouched by any of this. Until
DNS or the document root changes, it is still the live site, and every step here
is reversible by not doing the next one.
