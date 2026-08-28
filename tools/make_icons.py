#!/usr/bin/env python3
"""
=============================================================================
 make_icons.py — raster icons, derived from the SVGs so they cannot drift.
=============================================================================

     python tools/make_icons.py

 Writes, from assets/images/favicon.svg and assets/images/logo.svg:

     assets/images/apple-touch-icon.png   180x180, iOS home screen
     assets/images/favicon-32.png          32x32, older browsers
     assets/images/logo-512.png           512x512, transparent, on-dark colour

 WHY THESE EXIST AT ALL, given the SVG favicon works everywhere modern:

   * iOS ignores rel="icon" with an SVG and will not use one for a home-screen
     bookmark. Without a PNG apple-touch-icon it screenshots the page instead,
     which is unreadable at that size.
   * Slack, Google Calendar, GitHub org avatars and print shops all want a
     raster. logo-512.png is the one to hand somebody who asks for "the logo".

 The SVGs are the source of record. Edit those, then re-run this; do not edit
 a PNG by hand, because the next run overwrites it.

 Requires: cairosvg, pillow. The outputs are committed, so nobody else needs
 them installed.
=============================================================================
"""

import os

import cairosvg
from PIL import Image

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(HERE)
IMAGES = os.path.join(ROOT, "assets", "images")

JOBS = [
    ("favicon.svg", "apple-touch-icon.png", 180),
    ("favicon.svg", "favicon-32.png", 32),
    ("logo.svg", "logo-512.png", 512),
]


def main():
    for src, out, size in JOBS:
        src_path = os.path.join(IMAGES, src)
        out_path = os.path.join(IMAGES, out)
        if not os.path.exists(src_path):
            print("  SKIP  %s is missing" % src)
            continue

        cairosvg.svg2png(url=src_path, write_to=out_path,
                         output_width=size, output_height=size)

        # Pillow's optimizer beats cairo's writer by roughly half on flat art.
        Image.open(out_path).save(out_path, "PNG", optimize=True)

        print("  wrote %-22s %3dx%-3d  %5.1f KB  from %s"
              % (out, size, size, os.path.getsize(out_path) / 1024.0, src))


if __name__ == "__main__":
    main()
