#!/usr/bin/env python3
"""
=============================================================================
 archive_old_site.py -- keep a copy of alpine.caltech.edu before it is gone.
=============================================================================

     python tools/archive_old_site.py                 write ../old-site-archive
     python tools/archive_old_site.py --out DIR       somewhere else
     python tools/archive_old_site.py --dry-run       list what it would fetch
     python tools/archive_old_site.py --delay 10      crawl at robots.txt's rate

 WHY. alpine.caltech.edu is still the club's Caltech Sites (Wagtail) page, and
 the plan is to point that hostname at this repository. On the day that
 happens the old site stops existing: there is no export, nobody at the club
 has a database login, and Wagtail's admin is behind IMSS. Fifteen years of
 the club writing things down would go with it -- the guides, the book
 reviews, the officer pages, the trip and film-festival history.

 tools/import_guides.py already took the guides, because those were wanted on
 the new site. This takes EVERYTHING, and it is not for reuse: it is a record.

 WHAT IT WRITES

     html/       every page, byte for byte as served
     text/       the same pages with the markup stripped, one .txt each, so
                 the words are greppable in ten years without a browser
     prose/      just the article body: what a person wrote, with the CMS
                 chrome cut. Absent for pages that are only navigation
     files/      every image, PDF and document the pages reference
     MANIFEST.json   url -> local path, bytes, sha256, HTTP status, fetched-at,
                 plus every external link the site pointed at
     INDEX.md    the site's shape as a list, with each page's title

 The split matters. HTML rots -- it needs a browser, and a browser from 2040
 may not render a 2016 Wagtail theme. Plain text does not rot. Keeping both
 costs almost nothing and means the archive is still readable when the styling
 is not.

 prose/ is the third copy because the other two are unreadable for a different
 reason: every page carries about sixty identical lines of menu before its
 first real sentence, so the guides -- the reason this is worth archiving --
 start below the fold of their own text file.

 POLITENESS, AND A DELIBERATE DEPARTURE FROM robots.txt. The site's robots.txt
 asks for `Crawl-delay: 10`. This defaults to 1 second instead, and says so
 out loud rather than quietly:

   * a 10-second delay over ~56 pages plus their assets is roughly half an
     hour of somebody watching a terminal, for a site that sits behind
     Cloudflare and serves this in milliseconds;
   * this is the club's own site, archived once by its own officer, not a
     third-party crawler harvesting it repeatedly.

 Pass --delay 10 to comply strictly. The two Disallow rules in robots.txt ARE
 honoured at any delay, and the crawl never leaves the host.

 Requires nothing but the Python standard library.
=============================================================================
"""

import argparse
import hashlib
import json
import os
import re
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from datetime import datetime, timezone

SITE = "https://alpine.caltech.edu"
SITEMAP = SITE + "/sitemap.xml"
HOST = "alpine.caltech.edu"

# THE PHOTOGRAPHS ARE NOT ON alpine.caltech.edu. Wagtail serves its renditions
# from a separate Caltech asset host, so a same-host rule -- which is the
# obvious way to write a crawler and the way this was written first -- silently
# archives the theme's chevron icons and none of the club's pictures. The first
# run got 25 files and looked like it had worked.
#
# These are the Caltech-owned hosts whose files are the club's own content.
ASSET_HOSTS = (
    HOST,
    "caltechsites-prod-assets.resources.caltech.edu",   # every photograph
    "alpine.sites.caltech.edu",                         # older uploads
)

# Third-party CDNs are deliberately NOT archived: jquery, datatables and the
# rest are generic libraries, they are not the club's writing or pictures, and
# a copy of them helps nobody read this in 2040. Every one is still recorded in
# MANIFEST.json under external_links, so nothing disappears without a trace.

# From the site's own robots.txt, read 2026-08-31. Honoured whatever --delay
# says: these are the paths the site asks nobody to walk, and unlike the rate
# they cost nothing to respect.
DISALLOW = ("/calendar/minicalendar/", "/map/landmark_ajax/", "/map/milestone/")

# Says who this is and why, so anyone reading the access log can tell an
# archive from a scraper.
UA = ("CaltechAlpineClub-Archiver/1.0 "
      "(one-time archive of the club's own site before migration; "
      "contact alpine@caltech.edu)")

ASSET_EXT = (".jpg", ".jpeg", ".png", ".gif", ".webp", ".svg", ".avif",
             ".pdf", ".doc", ".docx", ".xls", ".xlsx", ".ppt", ".pptx",
             ".txt", ".csv", ".zip", ".gpx", ".kml", ".ics",
             # The stylesheets too. They are what makes the saved HTML render
             # as something other than a wall of unstyled text, and they are a
             # few tens of KB. The scripts are not: they call a CMS that will
             # not be answering.
             ".css")


# ------------------------------------------------------------------ fetch --

def fetch(url, timeout=45):
    """(bytes, status, content_type). A failure is recorded, not fatal.

    One page 500ing must not end a half-hour crawl -- the manifest records the
    status so a later run can retry just that one, and a partial archive is
    worth incomparably more than none.
    """
    req = urllib.request.Request(url, headers={"User-Agent": UA})
    try:
        with urllib.request.urlopen(req, timeout=timeout) as r:
            return r.read(), r.status, r.headers.get("Content-Type", "")
    except urllib.error.HTTPError as err:
        return b"", err.code, ""
    except Exception as err:
        print("      ! %s" % err)
        return b"", 0, ""


def allowed(path):
    return not any(path.startswith(d) for d in DISALLOW)


# ------------------------------------------------------------- html -> text --

_BLOCK = ("p", "div", "br", "li", "tr", "h1", "h2", "h3", "h4", "h5", "h6",
          "section", "article", "header", "footer", "blockquote", "figcaption")


def to_text(html_bytes):
    """The words, with the tags gone and the block structure kept as newlines.

    Deliberately not a markdown converter. A converter has to guess at intent
    and gets it wrong on CMS markup; this only promises that every word that
    was on the page is in the file, in order, which is what an archive is for.
    """
    s = html_bytes.decode("utf-8", "replace")
    s = re.sub(r"(?is)<(script|style|noscript|svg)\b.*?</\1>", " ", s)
    s = re.sub(r"(?s)<!--.*?-->", " ", s)
    for tag in _BLOCK:
        s = re.sub(r"(?i)</?%s\b[^>]*>" % tag, "\n", s)
    s = re.sub(r"<[^>]+>", " ", s)
    import html as _h
    s = _h.unescape(s)
    s = re.sub(r"[ \t\xa0]+", " ", s)
    s = re.sub(r"\n\s*\n\s*\n+", "\n\n", s)
    return "\n".join(line.strip() for line in s.split("\n")).strip() + "\n"


def main_body(html_bytes):
    """Just the article, with the CMS chrome gone. '' if it cannot be found.

    WHY THIS EXISTS BESIDE to_text(). Every page on this site carries about
    sixty lines of masthead, menu, submenu and footer before the first word
    anybody wrote, and those sixty lines are identical on all 56 pages. A
    faithful text dump is right for an archive and is also nearly unreadable:
    the guides -- which are the reason this archive is worth having -- start
    below the fold of a text file.

    So the archive keeps both. text/ is everything the page said, in order.
    prose/ is the part a person wrote, and is empty for pages that are only
    navigation.

    <main id="content"> is the CMS's own wrapper, the same one
    tools/import_guides.py keyed on when it pulled the guides across. If a
    future Wagtail theme renames it, prose/ empties out and text/ still has
    everything -- which is the right way round for a fallback.
    """
    s = html_bytes.decode("utf-8", "replace")
    m = re.search(r'(?is)<main[^>]*\bid="content"[^>]*>(.*?)</main>', s)
    if not m:
        m = re.search(r"(?is)<main\b[^>]*>(.*?)</main>", s)
    if not m:
        return ""
    scope = m.group(1)
    # The in-page menus live inside <main> too on this theme.
    scope = re.sub(r'(?is)<nav\b.*?</nav>', " ", scope)
    return to_text(scope.encode("utf-8"))


def title_of(html_bytes):
    m = re.search(r"(?is)<title>(.*?)</title>", html_bytes.decode("utf-8", "replace"))
    if not m:
        return ""
    import html as _h
    return re.sub(r"\s+", " ", _h.unescape(m.group(1))).strip()


def slug_for(url):
    """A filesystem name for a URL path. '/' becomes 'index'."""
    path = urllib.parse.urlsplit(url).path.strip("/")
    if not path:
        return "index"
    return re.sub(r"[^a-zA-Z0-9._-]+", "_", path.replace("/", "__"))[:150]


# ------------------------------------------------------------------ assets --

def asset_urls(html_bytes, page_url):
    """Every image, document and stylesheet the page points at, absolute.

    src, href and srcset. srcset is in here because Wagtail renders responsive
    images and the largest rendition is often the only copy of the original at
    full size -- take them all; they are the photographs.
    """
    s = html_bytes.decode("utf-8", "replace")
    found = set()
    for m in re.finditer(r'(?:src|href)="([^"]+)"', s):
        found.add(m.group(1))
    for m in re.finditer(r'srcset="([^"]+)"', s):
        for part in m.group(1).split(","):
            found.add(part.strip().split(" ")[0])

    out, external = set(), set()
    for raw in found:
        if raw.startswith(("data:", "mailto:", "tel:", "javascript:", "#")):
            continue
        u = urllib.parse.urljoin(page_url, raw)
        sp = urllib.parse.urlsplit(u)
        if sp.netloc not in ASSET_HOSTS:
            if sp.scheme in ("http", "https"):
                external.add(urllib.parse.urlunsplit(sp._replace(fragment="")))
            continue
        if not allowed(sp.path):
            continue
        if sp.path.lower().endswith(ASSET_EXT):
            out.add(urllib.parse.urlunsplit(sp._replace(fragment="")))
    return out, external


def asset_name(url):
    sp = urllib.parse.urlsplit(url)
    base = os.path.basename(sp.path) or "file"
    # The asset host repeats basenames across Wagtail renditions of the same
    # photograph, so the host has to be part of the name or the second one
    # overwrites the first and the archive quietly loses pictures.
    if sp.netloc != HOST:
        base = sp.netloc.split(".")[0] + "__" + base
    # Wagtail serves several renditions under one basename; the directory
    # part disambiguates them, so keep a squashed version of it.
    prefix = re.sub(r"[^a-zA-Z0-9]+", "_", os.path.dirname(sp.path)).strip("_")
    name = ("%s__%s" % (prefix[-60:], base)) if prefix else base
    if sp.query:
        name += "__" + hashlib.sha1(sp.query.encode()).hexdigest()[:8]
    return re.sub(r"[^a-zA-Z0-9._-]+", "_", name)[:180]


# ------------------------------------------------------------------- crawl --

def sitemap_urls():
    body, status, _ = fetch(SITEMAP)
    if status != 200:
        sys.exit("sitemap returned %s. Without it this would have to crawl by\n"
                 "  following links, which is a different and less complete\n"
                 "  thing. Check the site is up before assuming it moved." % status)
    urls = re.findall(r"<loc>\s*(.*?)\s*</loc>", body.decode("utf-8", "replace"))
    return [u for u in urls if urllib.parse.urlsplit(u).netloc == HOST
            and allowed(urllib.parse.urlsplit(u).path)]


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--out", default=None, help="archive directory")
    ap.add_argument("--delay", type=float, default=1.0,
                    help="seconds between requests (robots.txt asks for 10)")
    ap.add_argument("--dry-run", action="store_true")
    a = ap.parse_args()

    root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    out = a.out or os.path.join(os.path.dirname(root), "old-site-archive")

    print("archive_old_site.py")
    print("  from   %s" % SITE)
    print("  to     %s" % out)
    print("  delay  %.1fs%s" % (a.delay,
          "   (robots.txt asks for 10; see this file's header)"
          if a.delay < 10 else "   (robots.txt's own rate)"))
    print()

    pages = sitemap_urls()
    print("  sitemap lists %d pages" % len(pages))
    if a.dry_run:
        for u in pages:
            print("    " + u)
        return 0

    for d in ("html", "text", "prose", "files"):
        os.makedirs(os.path.join(out, d), exist_ok=True)

    stamp = datetime.now(timezone.utc).isoformat(timespec="seconds")
    manifest = {"source": SITE, "fetched_at": stamp, "delay_seconds": a.delay,
                "robots_crawl_delay": 10, "asset_hosts": list(ASSET_HOSTS),
                "pages": [], "files": [], "external_links": []}
    assets = {}
    external = {}
    index = []

    print()
    for i, url in enumerate(pages, 1):
        body, status, ctype = fetch(url)
        slug = slug_for(url)
        title = title_of(body) if body else ""
        print("  %2d/%d  %-3s  %-58s %7d B  %s"
              % (i, len(pages), status, urllib.parse.urlsplit(url).path or "/",
                 len(body), title[:40]))

        if body:
            with open(os.path.join(out, "html", slug + ".html"), "wb") as f:
                f.write(body)
            head = "%s\n%s\n\n%s\n\n" % (url, "=" * len(url), title)
            with open(os.path.join(out, "text", slug + ".txt"), "w",
                      encoding="utf-8", newline="\n") as f:
                f.write(head + to_text(body))
            prose = main_body(body)
            if prose.strip():
                with open(os.path.join(out, "prose", slug + ".txt"), "w",
                          encoding="utf-8", newline="\n") as f:
                    f.write(head + prose)
            mine, theirs = asset_urls(body, url)
            for au in mine:
                assets.setdefault(au, set()).add(url)
            for eu in theirs:
                external.setdefault(eu, set()).add(url)

        manifest["pages"].append({
            "url": url, "status": status, "title": title,
            "bytes": len(body), "content_type": ctype,
            "html": "html/%s.html" % slug if body else None,
            "text": "text/%s.txt" % slug if body else None,
            "prose": ("prose/%s.txt" % slug
                      if body and main_body(body).strip() else None),
            "sha256": hashlib.sha256(body).hexdigest() if body else None,
        })
        index.append((url, title))
        time.sleep(a.delay)

    print()
    print("  %d distinct files referenced" % len(assets))
    # A FILE ALREADY ON DISK IS NOT RE-FETCHED, BUT IT IS STILL RECORDED.
    # Skipping the manifest entry as well as the download made a second run
    # write a MANIFEST.json claiming the archive had no files at all, next to a
    # files/ directory holding 434 of them. The whole point of the manifest is
    # to be the thing you trust when the directory is twenty years old.
    reused = 0
    for i, (au, seen_on) in enumerate(sorted(assets.items()), 1):
        name = asset_name(au)
        dest = os.path.join(out, "files", name)
        if os.path.exists(dest):
            with open(dest, "rb") as f:
                body = f.read()
            status, ctype = 200, ""
            reused += 1
        else:
            body, status, ctype = fetch(au)
            if body:
                with open(dest, "wb") as f:
                    f.write(body)
            print("  %3d/%d  %-3s  %-70s %8d B"
                  % (i, len(assets), status, name[:70], len(body)))
            time.sleep(min(a.delay, 0.5))
        manifest["files"].append({
            "url": au, "status": status, "bytes": len(body),
            "content_type": ctype, "path": "files/%s" % name if body else None,
            "sha256": hashlib.sha256(body).hexdigest() if body else None,
            "referenced_by": sorted(seen_on),
        })
    if reused:
        print("  %d already on disk, re-hashed rather than re-downloaded" % reused)

    # EVERY OUTBOUND LINK, RECORDED BUT NOT FETCHED. These are the guidebooks,
    # the Wikipedia entries, the trip reports and the CDN libraries the pages
    # pointed at. Copying them is somebody else's archive; losing the fact that
    # the club linked to them is losing part of what the site said.
    manifest["external_links"] = [
        {"url": u, "referenced_by": sorted(pages_seen)}
        for u, pages_seen in sorted(external.items())]

    with open(os.path.join(out, "MANIFEST.json"), "w", encoding="utf-8") as f:
        json.dump(manifest, f, indent=1)

    ok_pages = sum(1 for p in manifest["pages"] if p["status"] == 200)
    ok_files = sum(1 for p in manifest["files"] if p["status"] == 200)
    total = sum(p["bytes"] for p in manifest["pages"] + manifest["files"])

    with open(os.path.join(out, "INDEX.md"), "w", encoding="utf-8", newline="\n") as f:
        f.write(INDEX_HEADER % (SITE, stamp, ok_pages, len(pages), ok_files,
                                len(manifest["files"]), total / 1e6, a.delay))
        by_url = {p["url"]: p for p in manifest["pages"]}
        for url, title in index:
            path = urllib.parse.urlsplit(url).path or "/"
            slug = slug_for(url)
            links = ["[html](html/%s.html)" % slug, "[text](text/%s.txt)" % slug]
            if by_url.get(url, {}).get("prose"):
                links.append("[prose](prose/%s.txt)" % slug)
            f.write("- `%s` — %s  \n  %s\n"
                    % (path, title or "(no title)", " · ".join(links)))

    print()
    print("  %d/%d pages, %d/%d files, %.1f MB" % (ok_pages, len(pages),
                                                   ok_files, len(manifest["files"]),
                                                   total / 1e6))
    print("  wrote %s" % out)
    failed = [p["url"] for p in manifest["pages"] + manifest["files"]
              if p["status"] != 200]
    if failed:
        print("\n  %d did NOT return 200 -- they are in MANIFEST.json with their "
              "status:" % len(failed))
        for u in failed[:15]:
            print("    " + u)
    return 0


INDEX_HEADER = """# The old alpine.caltech.edu, archived

A copy of the club's Caltech Sites (Wagtail) website, taken before that
hostname is pointed at the new site. **This is a record, not a website.** The
HTML here will not render exactly as it did: it references stylesheets and
scripts from a CMS that will not be there.

The `text/` copy is the one that will still be readable in twenty years.

| | |
|---|---|
| Source | %s |
| Taken | %s |
| Pages | %d of %d fetched |
| Files | %d of %d fetched |
| Size | %.1f MB |
| Request delay | %.1fs |

Anything that did not return 200 is listed in `MANIFEST.json` with its status,
which is also where every file's sha256 is, so a future copy can be checked
against this one.

Re-run with `python tools/archive_old_site.py` from the website repository.

## Pages

"""


if __name__ == "__main__":
    sys.exit(main())
