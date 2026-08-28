#!/usr/bin/env python3
"""
 prepare_officers.py — turn raw officer photos into the ones the site serves.

 THE CROP IS A HUMAN'S JOB. A person knows that Forrest's photo is about the
 Old Man of Storr behind him and not only about his face; no aspect-ratio rule
 knows that. So the contract is:

   * hand it a photo ALREADY at 4:5 and this only resizes it. Your crop is
     kept exactly as you made it.
   * hand it anything else and it takes a centre crop so the site is not
     broken while it waits for you, and it prints a LOUD warning naming the
     file, because that crop is a placeholder and probably wrong.

 Drop originals in assets/images/officers/raw/ named firstname-lastname.jpg
 (any common extension works). Output goes to assets/images/officers/ at
 528x660 — 2x the 264x330 the roster renders, so it stays sharp on a retina
 screen — and that name is what goes in the photo column of PEOPLE.csv.

 Raw files are kept. They are the only copy of the original framing, so if a
 crop is ever wrong you can redo it without asking anyone to dig out the photo
 again.

 Run:  python tools/prepare_officers.py
       python tools/prepare_officers.py --check   # report only, change nothing
"""

import argparse
import os
import sys

try:
    from PIL import Image, ImageOps
except ImportError:
    sys.exit("Pillow is needed: python -m pip install Pillow")

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
RAW = os.path.join(ROOT, "assets", "images", "officers", "raw")
OUT = os.path.join(ROOT, "assets", "images", "officers")

TARGET_W, TARGET_H = 528, 660          # 2x of the 264x330 the grid renders
TARGET_RATIO = TARGET_W / float(TARGET_H)
TOLERANCE = 0.02                        # 4:5 give or take a couple of percent
EXTS = (".jpg", ".jpeg", ".png", ".webp", ".heic")


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--check", action="store_true",
                    help="report what would happen, write nothing")
    args = ap.parse_args()

    if not os.path.isdir(RAW):
        os.makedirs(RAW, exist_ok=True)
        print("created %s — drop originals in there" % RAW)
        return 0

    names = sorted(f for f in os.listdir(RAW) if f.lower().endswith(EXTS))
    if not names:
        print("no raw photos in %s" % RAW)
        return 0

    guessed = []
    for name in names:
        src = os.path.join(RAW, name)
        stem = os.path.splitext(name)[0]
        dest = os.path.join(OUT, stem + ".jpg")

        # Phone photos carry their rotation in EXIF rather than in the pixels.
        im = ImageOps.exif_transpose(Image.open(src))
        ratio = im.width / float(im.height)
        # Report what was handed IN. Printing the post-crop size made a photo
        # this script had just mangled look like it arrived at 4:5.
        was = "%dx%d" % (im.width, im.height)

        if abs(ratio - TARGET_RATIO) <= TOLERANCE:
            action = "resize only — your crop kept"
        else:
            action = "CENTRE CROP — please re-crop by hand"
            guessed.append(name)
            if ratio > TARGET_RATIO:                    # too wide
                w = int(round(im.height * TARGET_RATIO))
                left = (im.width - w) // 2
                im = im.crop((left, 0, left + w, im.height))
            else:                                       # too tall
                h = int(round(im.width / TARGET_RATIO))
                top = (im.height - h) // 2
                im = im.crop((0, top, im.width, top + h))

        print("  %-34s %-11s %s" % (name, was, action))

        if args.check:
            continue

        out = im.convert("RGB").resize((TARGET_W, TARGET_H), Image.LANCZOS)
        out.save(dest, "JPEG", quality=86, optimize=True, progressive=True)

    if guessed:
        print("\n  " + "!" * 68)
        print("  These were not 4:5, so the centre was guessed. A centre crop of a")
        print("  photo of a person is usually wrong — it cuts heads off and keeps")
        print("  sky. Re-crop by hand to 4:5 and drop it back in raw/:")
        for g in guessed:
            print("    - %s" % g)
        print("  " + "!" * 68)

    print("\n%d photo(s) in raw/, output at %dx%d in assets/images/officers/"
          % (len(names), TARGET_W, TARGET_H))
    return 0


if __name__ == "__main__":
    sys.exit(main())
