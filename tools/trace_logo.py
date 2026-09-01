#!/usr/bin/env python3
"""
=============================================================================
 trace_logo.py -- turn the club's artwork PNGs into the SVGs the site serves.
=============================================================================

     python tools/trace_logo.py            ->  logo.svg, favicon.svg, logo-full.svg
     python tools/trace_logo.py --check    ->  report the fit, write nothing

 WHY THIS EXISTS. The mark arrives as a raster from whoever drew it, and the
 site serves SVG: one file that is sharp at 32px in a browser tab and at 512px
 on a phone home screen. The first mark was traced by hand with the potrace
 command line and the settings were written into a comment. That is fine once
 and useless the second time, because the settings are not runnable. This is
 the same operation as a script, so the next person who is handed a new drawing
 runs one command instead of reconstructing an invocation from prose.

 HOW IT WORKS. The artwork is flat colour, so each colour is a layer: classify
 every pixel to the nearest of a small named palette, trace each layer's mask
 separately, and stack the paths in draw order. Anti-aliased edge pixels fall
 to whichever palette entry they are closest to, which is what a threshold
 would have done anyway and needs no threshold to tune.

 THE PALETTE IS REMAPPED ON THE WAY THROUGH. The drawings use their own orange
 (#fd6901 on the badge, #ef5c17 on the lockup); the site has exactly one accent
 and it is --alpenglow in assets/css/style.css. So a layer is named by what it
 IS -- sky, rock, snow -- and the colour it comes out as is read from
 SITE_PALETTE below. Change a token there and in the stylesheet together; the
 mark is loaded with <img>, and an SVG used as an image has no parent, so
 currentColor resolves to black and cannot be used.

 CHECKING IT. --check re-renders each traced layer back to a bitmap and reports
 the fraction of pixels that disagree with the source. Under about 2% at
 1254px is what the hand-traced mark achieved and is not visible at any size
 the site uses. It also renders the result at 32px, because that is the size
 that decides whether a favicon reads at all.
=============================================================================
"""

import argparse
import os
import sys

try:
    import numpy as np
    from PIL import Image
except ImportError:
    sys.exit("needs numpy and Pillow: python -m pip install numpy Pillow")

try:
    import potrace
except ImportError:
    sys.exit("needs potracer (pure-python potrace): python -m pip install potracer")

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import palette                                          # noqa: E402

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
IMAGES = os.path.join(ROOT, "assets", "images")
# The drawings live OUTSIDE assets/, because assets/ is the served tree: both
# publishers copy it wholesale, and these two PNGs are 1.4 MB of source nobody
# ever requests over HTTP. Same reason docs/ and tools/ are not in there.
ART = os.path.join(ROOT, "art")

# THE MARK'S COLOURS ARE THE SITE'S COLOURS, READ FROM THE STYLESHEET.
#
# The drawings arrive in their own orange -- #fd6901 on the badge, #ef5c17 on
# the lockup -- and the site has exactly one accent. Both are remapped here, so
# nothing on any page is a near-miss of the colour beside it.
#
# READ, NOT COPIED. This started as three hex literals typed out of the :root
# block, and one of them was wrong: --paper is hsl(38 44% 96%), which is
# #f9f6f0, and the literal said #faf5ee. Two points off across three channels
# is invisible in isolation and is exactly the near-miss this remapping exists
# to prevent. tools/palette.py reads the stylesheet, so there is no copy to
# keep in step and no arithmetic for a human to get wrong.
SITE_PALETTE = {
    "accent": palette.hexof("alpenglow"),
    "ink":    palette.hexof("ink"),
    "paper":  palette.hexof("paper"),
}


# ---------------------------------------------------------------- classify --

def classify(rgba, layers):
    """Boolean mask per layer, by nearest colour in the source's own palette.

    `layers` is a list of (name, source_rgb, out_token). A pixel joins the
    layer whose source colour it is closest to in plain RGB distance --
    adequate because the artwork is three flat colours far apart, and a
    perceptual distance would only change which side of an anti-aliased edge
    a handful of pixels land on.
    """
    rgb = rgba[..., :3].astype(np.int32)
    alpha = rgba[..., 3]
    refs = np.array([l[1] for l in layers], dtype=np.int32)
    # (h, w, n_layers) of squared distance, then the winner per pixel.
    d = ((rgb[:, :, None, :] - refs[None, None, :, :]) ** 2).sum(axis=3)
    nearest = d.argmin(axis=2)
    return {name: (nearest == i) & (alpha > 128)
            for i, (name, _, _) in enumerate(layers)}


# ------------------------------------------------------------------- trace --

def trace(mask, turdsize, alphamax, opttolerance):
    """One boolean mask -> an SVG path `d` string.

    turdsize drops specks smaller than N pixels. On a 1254px drawing the
    anti-aliased boundary between two flat colours throws off a few one- and
    two-pixel islands, and each one becomes a subpath that is invisible at
    every size and costs bytes in a file the browser fetches on every page.
    """
    # TWO POTRACER GOTCHAS, both of which return a plausible path rather than
    # an error, so neither shows up as a failure:
    #
    #  1. dtype must be bool. Anything else is thresholded at
    #     `data > 255 * blacklevel`, so a 0/1 uint8 mask comes out entirely
    #     False and the trace is a rectangle around the whole frame.
    #  2. Bitmap.__init__ calls invert(), so it traces the COMPLEMENT of what
    #     you hand it. Pass ~mask to get the mask. Measured on a 3x5 block in
    #     a 10x10 field: Bitmap(m) returned the outer frame plus the block as
    #     a reversed inner contour.
    #
    # The y axis is NOT flipped -- a block at rows 2-4 traces to y 2-5 -- so
    # nothing here has to transform coordinates.
    bmp = potrace.Bitmap(~mask.astype(bool))
    path = bmp.trace(turdsize=turdsize, alphamax=alphamax,
                     opttolerance=opttolerance)
    # potracer hands back _Point objects with .x/.y rather than tuples, and a
    # corner segment carries ONE control point in .c while a bezier carries two
    # in .c[0]/.c[1]. Both are `.c`; only is_corner tells them apart.
    out = []
    for curve in path:
        p = curve.start_point
        out.append("M%s %s" % (n(p.x), n(p.y)))
        for seg in curve:
            e = seg.end_point
            if seg.is_corner:
                c = seg.c
                out.append("L%s %sL%s %s" % (n(c.x), n(c.y), n(e.x), n(e.y)))
            else:
                a, b = seg.c1, seg.c2
                out.append("C%s %s %s %s %s %s"
                           % (n(a.x), n(a.y), n(b.x), n(b.y), n(e.x), n(e.y)))
        out.append("Z")
    return "".join(out)


def n(v):
    """Two decimals, trailing zeros dropped. At a 1254 viewBox that is a
    hundredth of a pixel, which is well under what any renderer resolves, and
    it roughly halves the file against the six decimals potrace emits."""
    s = "%.2f" % v
    s = s.rstrip("0").rstrip(".")
    return s if s else "0"


# ------------------------------------------------------------------- check --

def disagreement(mask, d, size):
    """What fraction of pixels the traced path gets wrong, by re-rendering it.

    The honest check on a trace is not how it looks in a viewer -- it is
    whether the shape it produces is the shape it was given. Needs cairosvg;
    without it this returns None and --check says so rather than passing.
    """
    try:
        import cairosvg
        import io
    except ImportError:
        return None
    svg = ('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" '
           'width="%d" height="%d"><path d="%s" fill="#000"/></svg>'
           % (size, size, size, size, d))
    # ON WHITE, explicitly. Rendered on the default transparent background,
    # convert("L") reads every untouched pixel as 0 and the comparison comes
    # back inverted -- which reads as a 90%-wrong trace and is really a
    # 90%-wrong measurement.
    png = cairosvg.svg2png(bytestring=svg.encode(), background_color="white")
    got = np.asarray(Image.open(io.BytesIO(png)).convert("L")) < 128
    return float((got != mask).mean())


# ------------------------------------------------------- isolate the disc --

def crop_to_disc(im, sky_rgb):
    """Cut a circular mark out of an opaque rectangular drawing.

    WHY THIS IS NEEDED, and why it is geometry rather than colour. The favicon
    drawing arrives as a disc on a black field, and the rock inside the disc is
    the same black. Classifying by colour alone therefore cannot tell the
    mountain from the background: it merges them, and the trace comes out as a
    rectangle with a bite in it.

    The circle is recoverable because the SKY colour appears nowhere else. Its
    leftmost and rightmost pixels are the disc's horizontal extremes, and its
    topmost pixel is the top of the disc, which is three points on a circle and
    one of them is the apex -- so the centre and radius fall straight out with
    no fitting. Verified against this drawing at twelve angles: 4% inside the
    computed edge is sky or rock, 4% outside is the field.

    That holds for any mark whose accent reaches the disc's widest point, and
    fails loudly rather than quietly if it does not: the radius comes out too
    small and the trace visibly clips.
    """
    a = np.asarray(im.convert("RGB")).astype(np.int32)
    d = ((a - np.array(sky_rgb, dtype=np.int32)) ** 2).sum(axis=2)
    sky = d < 60 ** 2
    if not sky.any():
        sys.exit("crop_to_disc: no pixel is near the sky colour %s" % (sky_rgb,))
    ys, xs = np.nonzero(sky)
    cx = (xs.min() + xs.max()) / 2.0
    r = (xs.max() - xs.min()) / 2.0
    cy = ys.min() + r

    yy, xx = np.mgrid[0:a.shape[0], 0:a.shape[1]]
    inside = (xx - cx) ** 2 + (yy - cy) ** 2 <= r ** 2

    out = np.dstack([a, np.where(inside, 255, 0)]).astype(np.uint8)
    im = Image.fromarray(out, "RGBA")
    # Square, and tight to the disc, so the traced viewBox is the mark rather
    # than the drawing's canvas.
    box = (int(round(cx - r)), int(round(cy - r)),
           int(round(cx + r)), int(round(cy + r)))
    return im.crop(box)


# ------------------------------------------------------------------- build --

def build(src, layers, out_name, size, header, extra_head="", turdsize=8,
          alphamax=1.0, opttolerance=0.6, check_only=False, disc_sky=None):
    path = os.path.join(ART, src)
    if not os.path.exists(path):
        sys.exit("missing source artwork: %s\n"
                 "  The drawings live in art/ and are committed,\n"
                 "  because a traced SVG cannot be re-traced from itself." % path)

    im = Image.open(path).convert("RGBA")
    if disc_sky is not None:
        im = crop_to_disc(im, disc_sky)
    if im.width != im.height:
        sys.exit("%s is %dx%d; the marks are square" % (src, im.width, im.height))
    if im.width != size:
        im = im.resize((size, size), Image.LANCZOS)
    rgba = np.asarray(im)

    masks = classify(rgba, layers)
    print("  %s  (%dx%d)" % (src, size, size))

    body = []
    worst = 0.0
    for name, _, token in layers:
        m = masks[name]
        if token is None or not m.any():      # a background layer we do not draw
            continue
        d = trace(m, turdsize, alphamax, opttolerance)
        bad = disagreement(m, d, size)
        worst = max(worst, bad or 0.0)
        print("    %-6s %7d px  %6d bytes  %s"
              % (name, m.sum(), len(d),
                 ("%.2f%% off" % (100 * bad)) if bad is not None
                 else "fit unmeasured (cairosvg missing)"))
        body.append('  <path fill="%s" d="%s"/>' % (SITE_PALETTE[token], d))

    svg = ('%s<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d"\n'
           '     role="img" aria-label="Caltech Alpine Club">\n%s%s\n</svg>\n'
           % (header, size, size, extra_head, "\n".join(body)))

    # IT HAS TO PARSE. An SVG this script writes is fetched by every visitor on
    # every page, and a malformed one fails in a way nothing here would notice:
    # the browser shows a broken image, and make_icons.py generates the favicons
    # from it. The failure that prompted this check is the easy one to make --
    # XML forbids "--" inside a comment, and every header in this file is a
    # comment written in the house style, which uses "--" as an em dash.
    try:
        import xml.etree.ElementTree as ET
        ET.fromstring(svg)
    except Exception as err:
        sys.exit("%s is not well-formed XML: %s\n"
                 "  If the message points at a comment, look for '--' in it: "
                 "XML does not allow it there." % (out_name, err))

    dest = os.path.join(IMAGES, out_name)
    if check_only:
        print("    --check: would write %s (%d bytes)" % (out_name, len(svg)))
    else:
        with open(dest, "w", encoding="utf-8", newline="\n") as f:
            f.write(svg)
        print("    wrote assets/images/%s (%d bytes)" % (out_name, len(svg)))
    return worst


# The drawings, and what each layer becomes.
#
# BADGE -- an orange sky, a dark mountain mass, a light torch, inside a circle.
# The disc is traced as its own layer from every non-transparent pixel, so the
# circle's edge comes from the drawing rather than from a <circle> guessing at
# its centre and radius.
BADGE_LAYERS = [
    ("sky",  (253, 105,   1), "accent"),
    ("rock", ( 12,  11,  11), "ink"),
    ("snow", (253, 253, 253), "paper"),
]

# FAVICON -- the same idea reduced to one peak and a snowcap, for the sizes
# where the torch is four grey pixels. Its own drawing rather than a simplified
# render of the badge, and its own file for the same reason a favicon has ever
# been separate: 16px is a different design problem, not a smaller one.
#
# The drawing is a disc on a BLACK FIELD, and the rock inside the disc is the
# same black, so crop_to_disc() has to find the circle geometrically before
# anything is classified. See its docstring.
FAVICON_SKY = (242, 92, 1)
FAVICON_LAYERS = [
    ("sky",  FAVICON_SKY,       "accent"),
    ("rock", (  9,   9,   9),   "ink"),
    ("snow", (255, 255, 255),   "paper"),
]

# LOCKUP -- the mark and "CALTECH" in black, "ALPINE CLUB" in orange, on a
# white field that is not drawn at all so the file can sit on any background.
LOCKUP_LAYERS = [
    ("paper", (253, 253, 253), None),          # the field: classified, not drawn
    ("dark",  ( 18,  18,  18), "ink"),
    ("word",  (239,  92,  23), "accent"),
]


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--check", action="store_true",
                    help="report the fit and write nothing")
    a = ap.parse_args()

    print("trace_logo.py")
    worst = 0.0

    worst = max(worst, build(
        "badge.png", BADGE_LAYERS, "logo.svg", 512,
        header=HEADER_LOGO, check_only=a.check))

    worst = max(worst, build(
        "favicon.png", FAVICON_LAYERS, "favicon.svg", 512,
        header=HEADER_FAVICON, check_only=a.check, disc_sky=FAVICON_SKY))

    worst = max(worst, build(
        "lockup.png", LOCKUP_LAYERS, "logo-full.svg", 1254,
        header=HEADER_FULL, check_only=a.check))

    print()
    if worst:
        print("worst layer disagrees with its source on %.2f%% of pixels."
              % (100 * worst))
        print("Under ~2%% is what the previous hand-traced mark achieved.")
    if not a.check:
        print("\nNow regenerate the rasters that come from these:")
        print("  python tools/make_icons.py")
        print("  python tools/make_social.py")
    return 0


HEADER_LOGO = """<!--
  THE CALTECH ALPINE CLUB MARK: a torch in front of three peaks, in a disc.

  GENERATED. Do not edit this file. Edit the drawing at
  art/badge.png and run `python tools/trace_logo.py`, which
  traces each flat colour as its own layer and recolours them to the site's
  palette on the way through.

  THREE LAYERS, and the order is the drawing's: the disc, the mountain mass on
  top of it, the torch and the snow on top of that. The disc is traced rather
  than drawn as a <circle> so its edge is the artwork's edge.

  IT CARRIES ITS OWN BACKGROUND, which the crossed-axes mark before it did not.
  That mark was one path in the accent colour and depended on whatever was
  behind it; this one is opaque, so it reads the same on the ink masthead, on
  the ink footer, and on a paper page, and there is no second file for dark
  backgrounds.

  THE COLOURS ARE HARDCODED, not currentColor. This file is loaded with <img>,
  and an SVG used as an image has no parent to inherit from, so currentColor
  there resolves to black. The values below are not typed: trace_logo.py reads
  the alpenglow, ink and paper tokens out of assets/css/style.css and converts
  them, so the mark cannot end up a shade off the accent beside it. Change a
  token in the stylesheet and re-run the script. tools/audit.py fails the build
  if a colour in this file is not one the stylesheet declares.

  (The token names are written without their CSS prefix on purpose. This is an
  XML comment, and XML forbids two hyphens inside one.)
-->
"""

HEADER_FAVICON = """<!--
  Favicon: one peak and a snowcap in a disc. A SIMPLER MARK THAN logo.svg, on
  purpose.

  The masthead mark is a torch in front of three peaks. At 16 pixels the torch
  is four grey dots and the three ridgelines are one, so the tab shows a
  smudge. This is its own drawing, reduced to the two shapes that survive: the
  disc, and a peak with a cap on it. A favicon has always been a separate file
  for exactly this reason. 16px is a different design problem, not a smaller
  one.

  The favicon before this inset the mark inside a rounded ink tile, because
  that mark was line art with no background and lost its shape against
  whatever colour a browser paints its tab strip. This one is a filled disc,
  so it is already a tile and already has an edge.

  GENERATED from art/favicon.png. See logo.svg's header.
  Regenerate the raster icons after any change: python tools/make_icons.py
-->
"""

HEADER_FULL = """<!--
  THE FULL LOCKUP: the mark above CALTECH / ALPINE CLUB.

  For places that need the club's name in the artwork rather than beside it:
  the link-preview image, a poster, a slide. NOT the masthead, which already
  sets "Caltech Alpine Club" as live text next to logo.svg. Using this there
  would print the name twice.

  No background is drawn. The artwork's white field is classified and then
  skipped, so this sits on ink or on paper equally.

  GENERATED from art/lockup.png. See logo.svg's header.
-->
"""


if __name__ == "__main__":
    sys.exit(main())
