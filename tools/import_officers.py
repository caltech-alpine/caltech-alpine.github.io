#!/usr/bin/env python3
"""
=============================================================================
 import_officers.py — pull the officer roster and headshots off the old site.
=============================================================================

     python tools/import_officers.py            # writes photos, prints the roster
     python tools/import_officers.py --force    # also overwrites data/officers.php

 This was a one-off migration. After the roster is in data/officers.php you
 edit that file by hand — it is meant to be the easy part of running the site.
 The script refuses to overwrite it unless you pass --force, so a year of hand
 edits cannot be lost by running this by accident.

 Headshots are saved to assets/images/officers/<slug>.jpg at 528x702, which is
 twice the size they display at, so they stay sharp on high-resolution screens.

 Requires nothing but the Python standard library.
=============================================================================
"""

import html
import os
import re
import sys
import urllib.request

SRC = "https://alpine.caltech.edu/about-us/officers"

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(HERE)
PHOTO_DIR = os.path.join(ROOT, "assets", "images", "officers")
ROSTER = os.path.join(ROOT, "data", "officers.php")

# What each role is the person to ask about. Used to fill in the "handles"
# field, which is what makes the officers page useful rather than decorative.
# Anything not listed here gets an empty string for a human to fill in.
HANDLES = {
    "co-president":                          "Anything about the club in general",
    "president":                             "Anything about the club in general",
    "treasurer":                             "Reimbursements and club funds",
    "secretary":                             "Mailing list and membership",
    "film festival coordinator":             "Banff and the other film screenings",
    "talks coordinator":                     "Speaker events",
    "deals shark":                           "Member discounts and pro deals",
    "hiking and backpacking trip coordinator": "Hiking and backpacking trips",
    "tyrant of trail running":               "Trail runs",
    "climbing commodore":                    "Climbing trips and the bouldering wall",
    "gear officer":                          "Borrowing club equipment",
}


def fetch(url, binary=False):
    req = urllib.request.Request(url, headers={"User-Agent": "caltech-alpine-club-migration/1.0"})
    data = urllib.request.urlopen(req, timeout=60).read()
    return data if binary else data.decode("utf-8", "replace")


def slugify(name):
    return re.sub(r"-+", "-", re.sub(r"[^a-z0-9]+", "-", name.lower())).strip("-")


def parse(page):
    """[(group, name, role, photo_url_or_None), ...] in page order."""
    people = []
    chunks = re.split(r'<h3 class="person-list__title"[^>]*>(.*?)</h3>', page, flags=re.S)

    for i in range(1, len(chunks), 2):
        group = re.sub(r"<[^>]+>", "", chunks[i]).strip()
        for teaser in re.split(r'<div class="person-teaser ', chunks[i + 1])[1:]:

            after = teaser[teaser.rfind("</picture>") + 10:] if "</picture>" in teaser else teaser
            lines = [html.unescape(x).strip() for x in re.sub(r"<[^>]+>", "\n", after).split("\n")]
            lines = [x for x in lines if x and "col-lg" not in x and "California Institute" not in x]
            if len(lines) < 2:
                continue
            name, role = lines[0], lines[1]

            m = re.search(r'<img[^>]+src="([^"]+)"', teaser)
            photo = m.group(1) if m else None
            if photo and "blank-headshot" in photo:
                photo = None                       # the CMS placeholder, not a photo
            if photo:
                # Ask for the 2x rendition; it is the same image, larger.
                photo = photo.replace("fill-264x351-c100", "fill-528x702-c100")

            people.append((group, name, role, photo))
    return people


def main():
    force = "--force" in sys.argv
    print("  fetching %s" % SRC)
    page = fetch(page_url := SRC)
    people = parse(page)
    print("  found %d officers" % len(people))

    os.makedirs(PHOTO_DIR, exist_ok=True)
    rows = []

    for group, name, role, photo in people:
        slug = slugify(name)
        saved = ""

        if photo:
            ext = ".png" if photo.lower().split("?")[0].endswith(".png") else ".jpg"
            dest = os.path.join(PHOTO_DIR, slug + ext)
            try:
                with open(dest, "wb") as fh:
                    fh.write(fetch(photo, binary=True))
                saved = slug + ext
                print("    %-32s %s" % (name, saved))
            except Exception as ex:
                print("    %-32s ! photo failed (%s)" % (name, ex))
        else:
            print("    %-32s (no photo on the old site)" % name)

        rows.append({
            "name": name,
            "role": role,
            "group": group,
            "handles": HANDLES.get(role.lower().strip(), ""),
            "photo": saved,
        })

    if os.path.exists(ROSTER) and not force:
        print("\n  data/officers.php already exists — not overwriting.")
        print("  Re-run with --force to replace it, or paste the block below by hand.\n")
        print(render(rows))
    else:
        with open(ROSTER, "w", encoding="utf-8") as fh:
            fh.write(render(rows))
        print("\n  wrote data/officers.php")


def render(rows):
    out = ['<?php', '/**',
           ' * ============================================================================',
           ' *  OFFICERS  —  update this after every election.',
           ' * ============================================================================',
           ' *',
           ' *  This is the only file you need to touch to change who runs the club.',
           ' *  Add, remove or reorder entries and the About page follows.',
           ' *',
           ' *  Fields',
           ' *    name     required',
           ' *    role     required — their title',
           ' *    handles  what to contact them about. This is the useful bit: it tells',
           ' *             a visitor who to ask. Leave empty to hide the line.',
           ' *    group    the heading they appear under',
           ' *    email    optional. Club addresses are fine to publish; think twice',
           ' *             before publishing a personal one.',
           ' *    photo    optional filename in assets/images/officers/',
           ' *             Missing photo? The page draws their initials instead, which',
           ' *             looks deliberate — so nobody is blocked on a headshot.',
           ' *',
           ' *  ADDING A HEADSHOT: crop it square-ish, about 500px wide, save it as',
           ' *  assets/images/officers/firstname-lastname.jpg, and name it below.',
           ' * ============================================================================',
           ' */', '', 'return array(', '']

    last_group = None
    for r in rows:
        if r["group"] != last_group:
            out.append("    /* ---- %s %s */" % (r["group"], "-" * max(0, 58 - len(r["group"]))))
            out.append("")
            last_group = r["group"]
        out.append("    array(")
        out.append("        'name'    => %s," % php_str(r["name"]))
        out.append("        'role'    => %s," % php_str(r["role"]))
        out.append("        'handles' => %s," % php_str(r["handles"]))
        out.append("        'group'   => %s," % php_str(r["group"]))
        out.append("        'photo'   => %s," % php_str(r["photo"]))
        out.append("    ),")
        out.append("")
    out.append(");")
    return "\n".join(out) + "\n"


def php_str(s):
    return "'" + str(s).replace("\\", "\\\\").replace("'", "\\'") + "'"


if __name__ == "__main__":
    main()
