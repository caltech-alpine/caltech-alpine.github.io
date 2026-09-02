#!/usr/bin/env python3
"""
=============================================================================
 make_icons.py -- raster icons, derived from the SVGs so they cannot drift.
=============================================================================

     python tools/make_icons.py

 Writes, from assets/images/favicon.svg and its four siblings:

     favicon.ico                  16+32+48 in one file, AT THE SITE ROOT
     assets/images/apple-touch-icon.png  180x180, iOS home screen
     assets/images/icon-192.png    192x192, Android via site.webmanifest
     assets/images/icon-512.png    512x512, Android splash
     assets/images/icon-maskable-512.png  512x512, inset for Android masking
     assets/images/mark-512.png    512x512, TRANSPARENT, ink mountain
     assets/images/mark-on-dark-512.png   512x512, TRANSPARENT, paper mountain
     assets/images/favicon-disc-512.png   512x512, TRANSPARENT, the mark in a
                                          white disc, for dark UI

 The SVGs are the source of record. Edit art/favicon.png, run
 tools/trace_logo.py, then run this; do not edit a PNG by hand, because the
 next run overwrites it.

 ---------------------------------------------------------------------------
 WHY EACH FILE EXISTS. Every one of these closes a real gap, not a checklist
 item. What the site declared before 2026-09-02 was an SVG icon, a 32px PNG
 and the Apple touch icon, which leaves four holes:

  * /favicon.ico  Crawlers, RSS readers, link unfurlers and Windows
    pin-to-taskbar request the bare path and read no HTML first, and it was a
    404. It has to sit at the site ROOT, not in assets/, because that is the
    only path those clients try. ICO is the one format that holds several
    sizes in one file, so 16, 32 and 48 all live in it, each RENDERED at its
    own size -- 16px is what a tab shows on a 1x display and the size the mark
    is really judged at, and a 16 resampled from 512 is visibly softer than a
    16 rendered as a 16. That is also why no standalone favicon-16/32/48.png
    exists: the .ico has to be built anyway, so three more files declaring the
    same three sizes bought nothing. See the icon block in includes/header.php.

  * icon-192 / icon-512 + site.webmanifest  Without a manifest Android Chrome
    will not offer "Install app" at all and falls back to the Apple icon for
    a home-screen bookmark. These are what the manifest points at.

  * icon-maskable-512.png  Android may mask an icon to a circle, a squircle
    or a rounded square of its choosing, and it guarantees only the middle
    80% by diameter. Our mark fills its frame, so the unmodified icon loses
    its outer ring to any such crop. This one renders the mark at MASK_SAFE
    of the frame on a full-bleed ground, so the crop eats ground and never
    artwork. It is a separate file because a maskable icon looks wrong used
    unmasked -- too much padding -- so the manifest declares both.

 THE TRANSPARENT PAIR IS NOT AN ICON. mark-512.png and mark-on-dark-512.png
 are what to hand somebody who asks for "the logo as a PNG" for a deck, a
 poster or a Slack workspace icon. They REPLACE logo-512.png, which was
 documented here as transparent and had not been since favicon.svg gained its
 ground on 2026-08-31: it shipped as a warm off-white square, which reads as a
 dirty box on any dark background. Two files rather than one because the
 mountain has to flip; see mark.svg's own header.

 WHY THE BROWSER ICONS ARE NOT TRANSPARENT. The mountain is --ink. Drop the
 ground and it merges with a dark tab strip or an iOS home screen and the mark
 reads as a bare orange ring. Measured on 2026-08-31, which is why favicon.svg
 carries a paper rect. Transparency is right for a placement somebody chose
 and wrong for one the browser chose.

 Requires: cairosvg, pillow. The outputs are committed, so nobody else needs
 them installed.
=============================================================================
"""

import io
import os
import sys

import cairosvg
from PIL import Image

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(HERE)
IMAGES = os.path.join(ROOT, "assets", "images")

sys.path.insert(0, HERE)
import palette                                          # noqa: E402

# The fraction of the frame the artwork occupies in the maskable icon. Android
# promises the central 80% by DIAMETER, so a mark that must survive a circular
# crop has to fit inside that circle. Our mark is a disc rather than a square,
# so its corners are empty and 0.72 clears the crop with room to spare while
# not looking lost in padding.
MASK_SAFE = 0.72

# (source svg, output png, pixels, background or None)
#
# THE GROUND IS IMPOSED HERE, NOT CARRIED BY THE SVG (changed 2026-09-02).
# favicon.svg is transparent so that a browser tab shows the mark and not a
# pale square. But a HOME-SCREEN icon cannot be transparent: iOS masks it to a
# rounded square and composites alpha to BLACK, so a transparent icon arrives
# as a mark floating in a black tile nobody chose, and the near-black mountain
# inside it disappears. Android is the same story with a different mask.
#
# So the three home-screen rasters get PAPER imposed at render time, and only
# there. `background=None` on the two mark-*.png files means "whatever the SVG
# carries", which for those is nothing -- they come out genuinely transparent,
# and that is asserted at the end of this run rather than trusted.
#
# The media query inside favicon.svg does not fire under cairosvg, so these
# render with the light (ink) mountain, which is correct on paper.
PAPER = palette.hexof("paper")
JOBS = [
    ("favicon.svg",      "apple-touch-icon.png",  180, PAPER),
    ("favicon.svg",      "icon-192.png",          192, PAPER),
    ("favicon.svg",      "icon-512.png",          512, PAPER),
    ("mark.svg",         "mark-512.png",          512, None),
    ("mark-on-dark.svg", "mark-on-dark-512.png",  512, None),
    # THE 512 MASTER OF THE WHITE-DISC VARIANT (Kyle, 2026-09-02). The one
    # raster here that is a deliverable rather than something a browser asked
    # for: it is what to upload where a service wants "a PNG" and will put it
    # on dark chrome of its own choosing. `background=None` because the disc
    # is IN the SVG, and imposing paper behind it would fill the transparent
    # corners and turn the circle back into the square it exists to avoid.
    ("favicon-disc.svg", "favicon-disc-512.png",  512, None),
]

# Which of the above must have a transparent corner, and which must not. Both
# directions are failures that look fine in a file listing: an icon that lost
# its ground vanishes on a dark tab strip, and a "transparent" mark that kept
# one ships a visible box on every dark slide. That is the exact regression
# this file documented and did not catch for two days.
MUST_BE_OPAQUE = ("apple-touch-icon.png", "icon-192.png", "icon-512.png",
                  "icon-maskable-512.png")
MUST_BE_TRANSPARENT = ("mark-512.png", "mark-on-dark-512.png",
                       "favicon-disc-512.png")

# The sizes inside favicon.ico. 48 is included for Windows taskbar pinning,
# which upscales anything smaller.
ICO_SIZES = (16, 32, 48)

# WHICH SVG THE BROWSER ICONS COME FROM. One name, in one place, because the
# .ico and the <link rel="icon"> in includes/header.php have to agree: a tab
# showing a disc while the pinned shortcut shows a pale square is the kind of
# mismatch nobody notices until it is on somebody's taskbar.
ICO_SRC = "favicon-disc.svg"


def render(src, size, background=None):
    """One SVG at one pixel size, as an RGBA Pillow image."""
    png = cairosvg.svg2png(url=os.path.join(IMAGES, src),
                           output_width=size, output_height=size,
                           background_color=background)
    return Image.open(io.BytesIO(png)).convert("RGBA")


def save(im, path):
    """Write a PNG. Pillow's optimizer beats cairo's writer by roughly half on
    flat art, so everything goes back out through it."""
    im.save(path, "PNG", optimize=True)
    return os.path.getsize(path)


def maskable(size):
    """The mark inset on a full-bleed ground, for Android's icon masking.

    Rendered at MASK_SAFE of the frame and centred, so a circular or squircle
    crop removes ground rather than the top of the C. The ground is the mark's
    own paper, so the padding is not visible as padding on a light launcher.
    """
    inner = max(1, int(round(size * MASK_SAFE)))
    art = render("favicon.svg", inner)
    out = Image.new("RGBA", (size, size), palette.rgb("paper") + (255,))
    off = (size - inner) // 2
    out.paste(art, (off, off), art)
    return out


def main():
    print("make_icons.py  (paper %s, ink %s)"
          % (palette.hexof("paper"), palette.hexof("ink")))

    written = {}
    for src, out, size, background in JOBS:
        if not os.path.exists(os.path.join(IMAGES, src)):
            print("  SKIP  %s is missing -- run tools/trace_logo.py" % src)
            continue
        im = render(src, size, background)
        n = save(im, os.path.join(IMAGES, out))
        written[out] = im
        print("  wrote %-24s %3dx%-3d %6.1f KB  from %s"
              % (out, size, size, n / 1024.0, src))

    # -- the maskable icon ---------------------------------------------------
    im = maskable(512)
    n = save(im, os.path.join(IMAGES, "icon-maskable-512.png"))
    written["icon-maskable-512.png"] = im
    print("  wrote %-24s %3dx%-3d %6.1f KB  from favicon.svg at %.0f%%"
          % ("icon-maskable-512.png", 512, 512, n / 1024.0, 100 * MASK_SAFE))

    # -- favicon.ico, AT THE ROOT -------------------------------------------
    # Pillow writes a multi-size ICO from one image plus a size list, but it
    # DOWNSCALES that single image for each entry. A 16px entry resampled from
    # 512 is measurably softer than a 16px render, and 16px is the size that
    # matters most here, so each entry is rendered at its own size and the ICO
    # is assembled from the largest with the others appended.
    #
    # IT COMES OFF THE DISC, AND IT KEEPS ITS TRANSPARENCY (2026-09-02). This
    # used to be the one deliberately opaque file: a paper ground was imposed
    # here because the .ico's real consumer is Windows pin-to-taskbar, the
    # taskbar is dark by default, and an .ico cannot express the media query
    # favicon.svg uses to survive there. The disc answers that in the artwork
    # instead, so the ground is redundant -- and dropping it means the tab, the
    # taskbar and the pinned shortcut are finally the same picture rather than
    # a disc in one place and a pale square in the other.
    frames = [render(ICO_SRC, s) for s in ICO_SIZES]
    ico_path = os.path.join(ROOT, "favicon.ico")
    frames[-1].save(ico_path, format="ICO",
                    sizes=[(s, s) for s in ICO_SIZES],
                    append_images=frames[:-1])
    print("  wrote %-24s %-11s %6.1f KB  from %s"
          % ("favicon.ico  (site root)",
             "+".join(str(s) for s in ICO_SIZES),
             os.path.getsize(ico_path) / 1024.0, ICO_SRC))

    # -- NO SQUARE GROUND, ANYWHERE THE BROWSER LOOKS ------------------------
    # Kyle's call, 2026-09-02: a pale square in the tab strip is the one thing
    # this mark must not have. Asserted rather than trusted because the ground
    # came and went twice in three days, and a <rect> reappearing in one of
    # these files is invisible in a file listing and obvious in a browser tab.
    # The disc is a <circle> and passes this by construction, which is the
    # distinction the whole variant rests on.
    bad = []
    for name in ("favicon.svg", "favicon-on-dark.svg", "favicon-disc.svg",
                 "mark.svg", "mark-on-dark.svg"):
        svg = open(os.path.join(IMAGES, name), encoding="utf-8").read()
        if "<rect" in svg:
            bad.append("  %s contains a <rect>: these are the transparent "
                       "outputs and none of them may carry a square ground. "
                       "See the FAVICON spec in tools/trace_logo.py." % name)

    # THE TAB ICON MUST STILL BE THE ONE header.php LINKS. ICO_SRC decides what
    # /favicon.ico is rendered from; if the two drift, a browser that reads the
    # .ico and a browser that reads the SVG show different marks, and neither
    # is wrong enough for anybody to file it.
    disc = open(os.path.join(IMAGES, ICO_SRC), encoding="utf-8").read()
    if "<circle" not in disc:
        bad.append("  %s has no <circle>: the disc IS the ground, and without "
                   "it the ink mountain vanishes on a dark tab strip with "
                   "nothing left to save it -- see in_disc in "
                   "tools/trace_logo.py." % ICO_SRC)
    header = open(os.path.join(ROOT, "includes", "header.php"),
                  encoding="utf-8").read()
    if "images/" + ICO_SRC not in header:
        bad.append("  includes/header.php does not link %s, but favicon.ico is "
                   "rendered from it. Point rel=\"icon\" at the same file or "
                   "change ICO_SRC." % ICO_SRC)

    # favicon.svg is no longer the tab icon, but it is still what every
    # home-screen raster and the social card are rendered from, and it is the
    # transparent adaptive mark anyone reaching for "the favicon as an SVG"
    # will find. Its flip is the thing that makes it safe on an unknown
    # background, so it stays checked.
    if "prefers-color-scheme" not in open(
            os.path.join(IMAGES, "favicon.svg"), encoding="utf-8").read():
        bad.append("  favicon.svg has no prefers-color-scheme rule. It carries "
                   "no ground, so without the flip its ink mountain vanishes "
                   "on anything dark -- see the `adaptive` key in "
                   "tools/trace_logo.py.")

    # -- prove the alpha, do not assume it ----------------------------------
    for name in MUST_BE_OPAQUE + MUST_BE_TRANSPARENT:
        im = written.get(name)
        if im is None:
            continue
        corner = im.getpixel((0, 0))
        opaque = corner[3] == 255
        want_opaque = name in MUST_BE_OPAQUE
        if opaque != want_opaque:
            bad.append("  %s: corner alpha %d, expected %s"
                       % (name, corner[3],
                          "255 (opaque ground)" if want_opaque
                          else "0 (transparent)"))
    print()
    if bad:
        print("ALPHA CHECK FAILED:")
        print("\n".join(bad))
        print()
        print("  An icon that lost its ground disappears on a dark tab strip;")
        print("  a mark that kept one ships a box on every dark slide. Look at")
        print("  the `background` and `outputs` entries in tools/trace_logo.py.")
        return 1
    print("alpha check: %d opaque, %d transparent, all as intended."
          % (len(MUST_BE_OPAQUE), len(MUST_BE_TRANSPARENT)))
    return 0


if __name__ == "__main__":
    sys.exit(main())
