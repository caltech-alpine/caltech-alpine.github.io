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

import sys

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(HERE)
IMAGES = os.path.join(ROOT, "assets", "images")

sys.path.insert(0, HERE)
import palette                                          # noqa: E402

# (source, output, pixels, background)
#
# ONE OF THESE NEEDS A BACKGROUND AND THE OTHER TWO MUST NOT HAVE ONE.
#
# The mark is a disc, so a square icon of it has transparent corners. That is
# right for a browser tab and for the file people ask for when they say "the
# logo": both get composited onto something, and a hard square would show as a
# box around the circle.
#
# It is wrong for the iOS home screen. There the icon is masked to a rounded
# square and transparency comes out BLACK, so a bare disc reads as a circle
# floating in a dark box that nobody chose. Filling the corners with --ink
# makes that box deliberate, and it is the same treatment the previous favicon
# used when the mark had no background of its own.
# EVERY SQUARE ICON COMES FROM favicon.svg, INCLUDING logo-512.png. The logo
# is a wide lockup now (about 3.9:1) and rendering it into a 512 square either
# squashes it or leaves it a thin strip in a lot of nothing. The square mark is
# what somebody asking for "the logo, as a PNG" for an avatar or a print job
# actually wants; anyone who wants the lockup wants logo.svg, at its own shape.
JOBS = [
    ("favicon.svg", "apple-touch-icon.png", 180, palette.hexof("ink")),
    ("favicon.svg", "favicon-32.png", 32, None),
    ("favicon.svg", "logo-512.png", 512, None),
]


def main():
    for src, out, size, background in JOBS:
        src_path = os.path.join(IMAGES, src)
        out_path = os.path.join(IMAGES, out)
        if not os.path.exists(src_path):
            print("  SKIP  %s is missing" % src)
            continue

        cairosvg.svg2png(url=src_path, write_to=out_path,
                         output_width=size, output_height=size,
                         background_color=background)

        # Pillow's optimizer beats cairo's writer by roughly half on flat art.
        Image.open(out_path).save(out_path, "PNG", optimize=True)

        print("  wrote %-22s %3dx%-3d  %5.1f KB  from %s%s"
              % (out, size, size, os.path.getsize(out_path) / 1024.0, src,
                 ("  on " + background) if background else ""))


if __name__ == "__main__":
    main()
