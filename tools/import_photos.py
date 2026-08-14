#!/usr/bin/env python3
"""
 import_photos.py — pull the club's own photographs off the old site, once.

 The old alpine.caltech.edu runs on Wagtail, which serves every image as a set
 of pre-cut "renditions" — the same photo at fill-192x100, fill-768x432,
 max-1400x800 and so on. What we want is the largest rendition of each DISTINCT
 photograph, not forty crops of eight pictures, so this groups by the base name
 Wagtail derives from the original upload and keeps the biggest.

 These are the club's own photos on the club's own site, being moved to the
 club's new site. Nothing here touches anything that is not alpine.caltech.edu.

 Run:  python tools/import_photos.py            # download into assets/images/photos
       python tools/import_photos.py --dry-run  # just list what it found

 Writes assets/images/photos/MANIFEST.json recording where each file came from,
 so a later editor can tell a real trip photo from a stock banner.
"""

import argparse
import json
import os
import re
import struct
import sys
import urllib.request

BASE = "https://alpine.caltech.edu"
ASSET_HOST = "caltechsites-prod-assets.resources.caltech.edu"

# The pages worth harvesting: the ones that carry trip photography rather than
# chrome. Ordered roughly by how likely they are to hold a usable wide shot.
PAGES = [
    "/",
    "/about-us",
    "/about-us/activities",
    "/about-us/activities/climbing",
    "/about-us/activities/hiking",
    "/about-us/activities/mountain-biking",
    "/about-us/activities/running",
    "/about-us/activities/snow-sports",
    "/about-us/events",
    "/about-us/events/club-trips",
    "/about-us/events/film-festivals",
    "/about-us/events/stewardship",
    "/about-us/events/talks",
    "/about-us/education",
    "/explore",
    "/explore/climbs",
    "/explore/hikes",
    "/explore/mountain-biking",
    "/explore/skiing-snowboarding",
    "/explore/trail-runs",
    "/join",
    "/news",
]

OUT_DIR = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
                       "assets", "images", "photos")

UA = {"User-Agent": "alpine-club-site-migration (one-off asset import)"}

# Wagtail names renditions two different ways and BOTH appear on this site:
#   IMG_6669_2.2e16d0ba.fill-768x432-c100.jpg   hash + crop spec
#   Sunset_ridge.width-850.jpg                  plain width
#   Trail.width-850_UbRGGpH.jpg                 plain width + collision suffix
# Missing the second form is what produced 139 files for ~40 photographs.
REND_HASH = re.compile(
    r"^(?P<stem>.+?)\.[0-9a-f]{8}\.(?P<spec>[^.]+)\.(?P<ext>jpg|jpeg|png|webp)$", re.I)
REND_WIDTH = re.compile(
    r"^(?P<stem>.+?)\.width-(?P<w>\d+)(?:_[A-Za-z0-9]+)?\.(?P<ext>jpg|jpeg|png|webp)$", re.I)
SIZE_IN_SPEC = re.compile(r"(\d+)x(\d+)")

# Promotional artwork for the Banff and Warren Miller festivals. The club is
# licensed to SCREEN those films; the posters and key art belong to the
# distributors, so they do not travel to a new site with the club's own photos.
NOT_OURS = ("wt-na-", "banner", "ticketmaster", "flame")


def fetch(url, timeout=30):
    req = urllib.request.Request(url, headers=UA)
    return urllib.request.urlopen(req, timeout=timeout).read()


def image_urls(html):
    """Every asset-host image referenced in a page, from src or srcset."""
    found = set()
    for m in re.finditer(r'https://%s/[^\s"\'<>)]+' % re.escape(ASSET_HOST), html):
        u = m.group(0).rstrip('",\'')
        if re.search(r"\.(jpg|jpeg|png|webp)$", u, re.I):
            found.add(u)
    return found


def rendition_area(url):
    """Which photograph this is, and how big this copy of it is."""
    name = url.rsplit("/", 1)[-1]
    m = REND_HASH.match(name)
    if m:
        dims = SIZE_IN_SPEC.search(m.group("spec"))
        area = int(dims.group(1)) * int(dims.group(2)) if dims else 0
        return (normalise(m.group("stem")), area)
    m = REND_WIDTH.match(name)
    if m:
        w = int(m.group("w"))
        return (normalise(m.group("stem")), w * w)   # width alone ranks fine
    return (normalise(name.rsplit(".", 1)[0]), 0)


def normalise(stem):
    """One key per photograph, whatever Wagtail called this copy of it."""
    s = re.sub(r"[^a-z0-9]+", "-", stem.lower()).strip("-")
    s = re.sub(r"-(width|max|fill|original)-.*$", "", s)
    s = re.sub(r"-[a-z0-9]{7}$", "", s)          # collision suffix
    return s


def collapse_truncated(best):
    """Wagtail truncates long stems, so 'mammoth-mountain-backcountr' and
    'mammoth-mountain-backcountry' are the same photo. Fold the shorter into
    the longer whenever one is a prefix of the other."""
    keys = sorted(best, key=len)
    merged = {}
    for k in keys:
        target = k
        for other in best:
            if other != k and len(k) >= 12 and other.startswith(k):
                target = other
                break
        if target in merged:
            if best[k][0] > merged[target][0]:
                merged[target] = best[k]
        else:
            merged[target] = best[k] if target == k else max(best[k], best[target])
    return merged


def dimensions(path):
    """Width/height without pulling in Pillow."""
    with open(path, "rb") as fh:
        data = fh.read()
    if data[:8] == b"\x89PNG\r\n\x1a\n":
        return struct.unpack(">II", data[16:24])
    if data[:2] == b"\xff\xd8":
        i = 2
        while i < len(data) - 9:
            if data[i] != 0xFF:
                i += 1
                continue
            marker = data[i + 1]
            if marker in (0xC0, 0xC1, 0xC2, 0xC3):
                h = struct.unpack(">H", data[i + 5:i + 7])[0]
                w = struct.unpack(">H", data[i + 7:i + 9])[0]
                return (w, h)
            if marker in (0xD8, 0xD9) or 0xD0 <= marker <= 0xD7:
                i += 2
                continue
            i += 2 + struct.unpack(">H", data[i + 2:i + 4])[0]
    return (0, 0)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--dry-run", action="store_true")
    ap.add_argument("--max-width", type=int, default=1600,
                    help="cap the stored width; these files live in git")
    args = ap.parse_args()

    best = {}      # stem -> (area, url, first page it appeared on)
    for page in PAGES:
        url = BASE + page
        try:
            html = fetch(url).decode("utf-8", "replace")
        except Exception as err:            # a page may have been retired
            print("  skip %-38s %s" % (page, err))
            continue
        urls = image_urls(html)
        print("  %-38s %d image refs" % (page, len(urls)))
        for u in urls:
            stem, area = rendition_area(u)
            if stem not in best or area > best[stem][0]:
                best[stem] = (area, u, page)

    best = collapse_truncated(best)
    skipped = [k for k in best if any(b in k for b in NOT_OURS)]
    for k in skipped:
        del best[k]

    print("\n%d distinct photographs (%d festival posters skipped: %s)\n"
          % (len(best), len(skipped), ", ".join(sorted(skipped)) or "none"))
    for stem, (area, u, page) in sorted(best.items()):
        print("  %-40s %s" % (stem[:40], page))

    if args.dry_run:
        return 0

    os.makedirs(OUT_DIR, exist_ok=True)
    manifest = []
    for stem, (area, u, page) in sorted(best.items()):
        ext = "png" if u.lower().endswith(".png") else "jpg"
        dest = os.path.join(OUT_DIR, "%s.%s" % (stem, ext))
        try:
            blob = fetch(u)
        except Exception as err:
            print("  FAILED %s: %s" % (stem, err))
            continue
        with open(dest, "wb") as fh:
            fh.write(blob)

        # 2880px wide is a print size. Nothing on this site is served wider than
        # a 1600px hero, and these live in git, so cap them on the way in.
        note = ""
        try:
            from PIL import Image
            with Image.open(dest) as im:
                if im.width > args.max_width:
                    im = im.convert("RGB")
                    ratio = args.max_width / float(im.width)
                    im = im.resize((args.max_width, int(im.height * ratio)),
                                   Image.LANCZOS)
                    dest = os.path.splitext(dest)[0] + ".jpg"
                    im.save(dest, "JPEG", quality=82, optimize=True,
                            progressive=True)
                    note = " (resized)"
        except ImportError:
            note = " (Pillow absent, kept full size)"

        w, h = dimensions(dest)
        size = os.path.getsize(dest)
        manifest.append({
            "file": os.path.basename(dest),
            "width": w, "height": h, "bytes": size,
            "source_page": BASE + page,
            "source_url": u,
        })
        print("  %-34s %5dx%-5d %7d bytes%s" % (os.path.basename(dest), w, h, size, note))

    with open(os.path.join(OUT_DIR, "MANIFEST.json"), "w", encoding="utf-8") as fh:
        json.dump({
            "note": "Imported from the old alpine.caltech.edu by tools/import_photos.py. "
                    "Club's own photographs, moved to the club's new site.",
            "images": manifest,
        }, fh, indent=2)
    print("\nwrote %d files + MANIFEST.json to %s" % (len(manifest), OUT_DIR))
    return 0


if __name__ == "__main__":
    sys.exit(main())
