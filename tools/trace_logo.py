#!/usr/bin/env python3
"""
=============================================================================
 trace_logo.py -- turn the club's artwork PNGs into the SVGs the site serves.
=============================================================================

     python tools/trace_logo.py            write every SVG
     python tools/trace_logo.py --check    report the fit, write nothing

 WHY THIS EXISTS. The mark arrives as a raster from whoever drew it, and the
 site serves SVG: one file that is sharp at 16px in a browser tab and at 512px
 on a phone home screen. The first mark was traced by hand with the potrace
 command line and the settings written into a comment. That is fine once and
 useless the second time, because a comment is not runnable. This is the same
 operation as a script, so the next person handed a new drawing runs one
 command instead of reconstructing an invocation from prose.

 HOW IT WORKS. The artwork is flat colour, so each colour is a layer: classify
 every pixel to the nearest of a small named palette, trace each layer's mask
 separately, and stack the paths in draw order. Anti-aliased edge pixels fall
 to whichever palette entry they are closest to, which is what a threshold
 would have done anyway and needs no threshold to tune.

 THE PALETTE IS REMAPPED ON THE WAY THROUGH. Every drawing so far has used its
 own orange, and the site has exactly one accent. So a layer is named for what
 it IS -- sky, rock, figure -- and the colour it comes out as is a token name
 that tools/palette.py resolves against assets/css/style.css. Nothing here is
 a hex literal.

 ONE DRAWING CAN PRODUCE SEVERAL FILES. The wordmark needs a dark-text version
 for light backgrounds and a light-text version for dark ones. Both come from
 the same trace with one token swapped, so they cannot drift: there is no
 second drawing to keep in step and no hand-recoloured copy.

 CHECKING IT. --check re-renders each traced layer back to a bitmap and reports
 the fraction of pixels that disagree with the source. Under about 2% is what
 the hand-traced mark achieved and is invisible at any size the site uses.
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

try:
    from scipy import ndimage
except ImportError:                                     # only widen_gap needs it
    ndimage = None

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import palette                                          # noqa: E402

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
IMAGES = os.path.join(ROOT, "assets", "images")
# The drawings live OUTSIDE assets/, because assets/ is the served tree: both
# publishers copy it wholesale, and these are megabytes of source nobody ever
# requests over HTTP. Same reason docs/ and tools/ are not in there.
ART = os.path.join(ROOT, "art")


# ---------------------------------------------------------------- classify --

def classify(rgba, layers):
    """Boolean mask per layer, by nearest colour in the source's own palette.

    `layers` is a list of (name, source_rgb, token). A pixel joins the layer
    whose source colour it is closest to in plain RGB distance -- adequate
    because the artwork is three or four flat colours far apart, and a
    perceptual distance would only change which side of an anti-aliased edge a
    handful of pixels land on.
    """
    rgb = rgba[..., :3].astype(np.int32)
    alpha = rgba[..., 3]
    refs = np.array([l[1] for l in layers], dtype=np.int32)
    d = ((rgb[:, :, None, :] - refs[None, None, :, :]) ** 2).sum(axis=3)
    nearest = d.argmin(axis=2)
    return {name: (nearest == i) & (alpha > 128)
            for i, (name, _, _) in enumerate(layers)}


def widen_gap(masks, shrink, against, k, despeckle=500):
    """Cut `shrink` back by k px wherever it comes near `against`.

    WHY THE MARK NEEDS THIS AT ALL. In art/favicon.png the mountain and the C
    are two pixels apart at their closest and touch outright over 248 pixels,
    measured 2026-09-02. In colour that reads fine, because orange beside black
    is two shapes whatever the gap is. In ONE ink it is a single blob, and that
    is the case this exists for: a monochrome mark, a fax-grade print, an
    engraving, a service that flattens an avatar. Kyle asked for the channel to
    be wider so the two halves stay two halves.

    IT IS NOT A UNIFORM EROSION, and that distinction is the whole function.
    Shrinking the mountain everywhere would pull its base off the outer circle
    and the mark would stop being a disc. Only rock within k of the orange goes,
    so the peaks, the ridges and the base arc are bit-for-bit what they were and
    the silhouette is unchanged. At k=20 that costs 1.8% of the mountain, all of
    it along the one edge nobody could see anyway.

    IT DESPECKLES `against` FIRST, and skipping that is a real bug rather than
    tidiness. classify() assigns each pixel to the nearest source colour, and a
    mid-grey anti-aliased pixel on the BLACK/WHITE edge is nearer to the orange
    (190,81,45) than to white or ink -- so the raw sun mask carries 63 stray
    specks strewn along the mountain's own ridge. Measure distance to those and
    the erosion nibbles the ridge everywhere, which is exactly what the first
    attempt produced. potrace hides the same specks after the fact with
    turdsize; this runs before it, so it has to clear them itself.
    """
    if not k:
        return masks
    if ndimage is None:
        sys.exit("widening the gap needs scipy: python -m pip install scipy")

    lab, n = ndimage.label(masks[against])
    if n:
        keep = [i for i in range(1, n + 1) if (lab == i).sum() >= despeckle]
        solid = np.isin(lab, keep)
    else:
        solid = masks[against]

    before = masks[shrink].sum()
    d = ndimage.distance_transform_edt(~solid)
    out = dict(masks)
    out[shrink] = masks[shrink] & (d >= k)
    print("    gap     %s pulled %d px clear of %s: %d px removed (%.1f%%), "
          "%d of %d %s specks dropped first"
          % (shrink, k, against, before - out[shrink].sum(),
             100.0 * (before - out[shrink].sum()) / max(1, before),
             n - len(keep) if n else 0, n, against))
    return out


# --------------------------------------------------------------- preparing --

def crop_to_disc(im, sky_rgb):
    """Cut a circular mark out of an opaque rectangular drawing.

    WHY GEOMETRY AND NOT COLOUR. A badge drawing is a disc on a flat field, and
    the field has been both black and white across the drawings so far -- in
    the black case it is the same colour as the rock inside the disc, so
    classifying by colour merges the mountain with the background and traces a
    rectangle with a bite out of it. In the white case the field is the same
    colour as the ridgeline. Either way colour alone cannot find the edge.

    The circle is recoverable because the SKY colour appears nowhere else. Its
    leftmost and rightmost pixels are the disc's horizontal extremes and its
    topmost pixel is the apex, which is three points on a circle, so the centre
    and radius fall out with no fitting. Verified against the drawing at twelve
    angles: just inside the computed edge is sky or rock, just outside is
    field.

    Fails loudly rather than quietly if a future drawing's accent does not
    reach the disc's widest point: the radius comes out small and the trace
    visibly clips.
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
    box = (int(round(cx - r)), int(round(cy - r)),
           int(round(cx + r)), int(round(cy + r)))
    return Image.fromarray(out, "RGBA").crop(box)


def trim_to_content(im, field_rgb, pad=0):
    """Drop the drawing's margins and make the field transparent.

    A wordmark arrives centred on a big white canvas, and that canvas is not
    part of the logo: left in, it becomes empty space inside the <img>, so
    every placement has to guess how much of the box is really the mark. Here
    the field is keyed out and the result cropped to what is left, so the
    file's own edges ARE the artwork's edges and `height: 44px` means the
    artwork is 44px tall.
    """
    a = np.asarray(im.convert("RGB")).astype(np.int32)
    d = ((a - np.array(field_rgb, dtype=np.int32)) ** 2).sum(axis=2)
    content = d >= 40 ** 2
    if not content.any():
        sys.exit("trim_to_content: the whole drawing is the field colour %s"
                 % (field_rgb,))
    ys, xs = np.nonzero(content)
    out = np.dstack([a, np.where(content, 255, 0)]).astype(np.uint8)
    box = (max(0, xs.min() - pad), max(0, ys.min() - pad),
           min(a.shape[1], xs.max() + 1 + pad), min(a.shape[0], ys.max() + 1 + pad))
    return Image.fromarray(out, "RGBA").crop(box)


def pad_to_square(im):
    """Centre the artwork on a square, transparent canvas.

    A FAVICON IS SQUARE WHETHER OR NOT THE DRAWING IS. Every consumer of this
    file -- a browser tab, an iOS home screen, make_icons.py -- renders it into
    a square box, and cairosvg is given output_width == output_height, so a
    non-square viewBox is not letterboxed but STRETCHED. The new mark trims to
    512x561, so without this it would ship 9% too short and nothing would say
    so: the trace succeeds, the fit is measured against the squashed source,
    and the icon simply looks wrong.

    The padding is transparent, so this changes the canvas and never a pixel of
    the mark. The wordmark does NOT get this treatment -- it is a wide lockup
    placed by height, and squaring it would put empty space inside every <img>
    on the site, which is the exact thing trim_to_content() exists to remove.
    """
    side = max(im.width, im.height)
    if (im.width, im.height) == (side, side):
        return im
    out = Image.new("RGBA", (side, side), (0, 0, 0, 0))
    out.paste(im, ((side - im.width) // 2, (side - im.height) // 2))
    return out


# ------------------------------------------------------------------- trace --

def trace(mask, turdsize, alphamax, opttolerance):
    """One boolean mask -> an SVG path `d` string.

    TWO POTRACER GOTCHAS, both of which return a plausible path rather than an
    error, so neither shows up as a failure:

     1. dtype must be bool. Anything else is thresholded at
        `data > 255 * blacklevel`, so a 0/1 uint8 mask comes out entirely False
        and the trace is a rectangle around the whole frame.
     2. Bitmap.__init__ calls invert(), so it traces the COMPLEMENT of what you
        hand it. Pass ~mask. Measured on a 3x5 block in a 10x10 field:
        Bitmap(m) returned the outer frame plus the block as a reversed
        inner contour.

    The y axis is NOT flipped -- a block at rows 2-4 traces to y 2-5 -- so
    nothing here transforms coordinates.

    turdsize drops specks smaller than N pixels. The anti-aliased boundary
    between two flat colours throws off a few one- and two-pixel islands, and
    each becomes a subpath that is invisible at every size and costs bytes in a
    file the browser fetches on every page.
    """
    bmp = potrace.Bitmap(~mask.astype(bool))
    path = bmp.trace(turdsize=turdsize, alphamax=alphamax,
                     opttolerance=opttolerance)
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
    """Two decimals, trailing zeros dropped. On a 1024-wide viewBox that is a
    hundredth of a pixel, well under what any renderer resolves, and it roughly
    halves the file against the six decimals potrace emits."""
    s = "%.2f" % v
    s = s.rstrip("0").rstrip(".")
    return s if s else "0"


# ------------------------------------------------------------------- check --

def disagreement(mask, d, w, h):
    """What fraction of pixels the traced path gets wrong, by re-rendering it.

    The honest check on a trace is not how it looks in a viewer. It is whether
    the shape it produces is the shape it was given. Needs cairosvg; without it
    this returns None and the report says so rather than passing.
    """
    try:
        import cairosvg
        import io
    except ImportError:
        return None
    svg = ('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" '
           'width="%d" height="%d"><path d="%s" fill="#000"/></svg>'
           % (w, h, w, h, d))
    # ON WHITE, explicitly. Rendered on the default transparent background,
    # convert("L") reads every untouched pixel as 0 and the comparison comes
    # back inverted -- which reads as a 90%-wrong trace and is really a
    # 90%-wrong measurement.
    png = cairosvg.svg2png(bytestring=svg.encode(), background_color="white")
    got = np.asarray(Image.open(io.BytesIO(png)).convert("L")) < 128
    return float((got != mask).mean())


def enclosing_circle(ds, w, h, oversample=2):
    """The smallest circle holding every traced path. Returns (cx, cy, r).

    MEASURED FROM THE TRACE, NEVER TYPED. The disc variant below has to scale
    the mark down to leave a ring of white around it, and how far down depends
    on how big the mark actually is -- which is a property of the trace and
    changes the next time art/favicon.png does. A number copied into the spec
    would be right today and silently wrong after the next retrace, with the
    only symptom a mark whose corner has grown past the edge of its disc.

    A BOUNDING BOX WOULD BE THE WRONG MEASURE. This mark is itself a disc: a C
    closed by a mountain. Its box corners are empty, so fitting the box's
    circumcircle would shrink the artwork by a fifth to make room for white
    space that has nothing in it. What has to fit inside the white circle is
    the mark's own circumscribed circle, so that is what this returns.

    Coordinate descent on the centre rather than Welzl's algorithm: the input
    is a raster of a quarter-million pixels, the objective is convex, and the
    answer is wanted to a tenth of a pixel on a 512 grid. Halving the step
    until it is under a twentieth of a pixel gets there in well under a second
    and needs no geometry library.
    """
    import io

    import cairosvg

    n_px = w * oversample
    svg = ('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d">'
           '%s</svg>'
           % (w, h, "".join('<path d="%s" fill="#000"/>' % d for d in ds)))
    png = cairosvg.svg2png(bytestring=svg.encode(), output_width=n_px,
                           output_height=int(round(n_px * h / float(w))))
    alpha = np.asarray(Image.open(io.BytesIO(png)).convert("RGBA"))[:, :, 3]
    ys, xs = np.nonzero(alpha > 8)
    if not len(xs):
        raise ValueError("the trace rendered to nothing")
    xs = xs.astype(float)
    ys = ys.astype(float)

    cx = (xs.min() + xs.max()) / 2.0
    cy = (ys.min() + ys.max()) / 2.0
    r = np.sqrt((xs - cx) ** 2 + (ys - cy) ** 2).max()
    step = float(n_px) / 64.0
    while step > 0.05 * oversample:
        moved = True
        while moved:
            moved = False
            for dx, dy in ((step, 0), (-step, 0), (0, step), (0, -step)):
                rr = np.sqrt((xs - (cx + dx)) ** 2 + (ys - (cy + dy)) ** 2).max()
                if rr < r - 1e-9:
                    cx, cy, r, moved = cx + dx, cy + dy, rr, True
        step /= 2.0
    return cx / oversample, cy / oversample, r / oversample


# ------------------------------------------------------------------- build --

def load(spec):
    """The drawing, prepared and scaled. Returns (rgba, width, height)."""
    path = os.path.join(ART, spec["src"])
    if not os.path.exists(path):
        sys.exit("missing source artwork: %s\n"
                 "  The drawings live in art/ and are committed, because a\n"
                 "  traced SVG cannot be re-traced from itself." % path)

    im = Image.open(path).convert("RGBA")
    if spec.get("disc") is not None:
        im = crop_to_disc(im, spec["disc"])
    elif spec.get("trim") is not None:
        im = trim_to_content(im, spec["trim"])
    if spec.get("square"):
        im = pad_to_square(im)

    # Width is what is specified; height follows the artwork's own aspect, so
    # a wordmark is not squeezed into a square it was never drawn for.
    w = spec["width"]
    h = max(1, int(round(w * im.height / float(im.width))))
    if (im.width, im.height) != (w, h):
        im = im.resize((w, h), Image.LANCZOS)
    return np.asarray(im), w, h


def build(spec, check_only=False, gap=None):
    rgba, w, h = load(spec)
    masks = classify(rgba, spec["layers"])
    print("  %-13s %dx%d" % (spec["src"], w, h))

    # `gap: (shrink, against, k)` -- see widen_gap(). k is in the pixels of THIS
    # spec's own frame, which `width` states, so it does not silently mean
    # something different in a drawing of another size. --gap on the command
    # line overrides it, which is how a different value gets tried without
    # editing anything.
    if spec.get("gap"):
        shrink, against, k = spec["gap"]
        masks = widen_gap(masks, shrink, against, k if gap is None else gap)

    # Trace ONCE per layer. The variants differ only in which token a layer is
    # painted with, and tracing the same mask twice would be the same work for
    # the same bytes -- and a place for two files to disagree if a setting were
    # ever passed differently.
    traced = []
    worst = 0.0
    for name, _, token in spec["layers"]:
        m = masks[name]
        if token is None or not m.any():        # a field we key out, not draw
            continue
        d = trace(m, spec.get("turdsize", 8), spec.get("alphamax", 1.0),
                  spec.get("opttolerance", 0.6))
        bad = disagreement(m, d, w, h)
        worst = max(worst, bad or 0.0)
        print("    %-7s %8d px  %6d bytes  %s"
              % (name, m.sum(), len(d),
                 ("%.2f%% off" % (100 * bad)) if bad is not None
                 else "fit unmeasured (cairosvg missing)"))
        traced.append((name, token, d))

    for out_name, overrides, header in spec["outputs"]:
        body = []
        # A FULL-BLEED GROUND, for a mark that has to survive being composited
        # onto something it did not choose. Only the favicon asks for this --
        # see the note on its spec -- and it is the drawing's own white field
        # kept rather than an invention.
        #
        # `background: None` in an output's overrides DROPS the rect instead of
        # inheriting the spec's. That is how the transparent mark files come off
        # the SAME trace as the opaque favicon: one knob, and no second drawing
        # to keep in step. `overrides.get(k, default)` returns None for an
        # explicit None, which is exactly the behaviour wanted here.
        bg = overrides.get("background", spec.get("background"))
        if bg:
            body.append('  <rect width="%d" height="%d" fill="%s"/>'
                        % (w, h, palette.hexof(bg)))

        # AN `adaptive` LAYER CARRIES ITS COLOUR IN CSS, NOT IN AN ATTRIBUTE, so
        # one transparent file can read on a light AND a dark tab strip without
        # a ground behind it. `{"rock": ("ink", "paper")}` means: ink normally,
        # paper under prefers-color-scheme: dark.
        #
        # WHY THIS EXISTS AT ALL. A ground was the earlier answer to "an ink
        # mountain vanishes on a dark tab strip", and it works, but it puts a
        # visible pale square in the tab -- which is the thing an icon is not
        # supposed to have. Flipping the artwork instead solves the legibility
        # problem without introducing a box.
        #
        # THE ATTRIBUTE IS STILL WRITTEN, as `fill` on the same element, and the
        # stylesheet overrides it. That is the fallback: a consumer that applies
        # no CSS at all -- an old rasteriser, a crawler thumbnailer -- gets the
        # light colour rather than black-by-default, which is what a bare
        # `class` with no fill attribute would give it.
        adaptive = overrides.get("adaptive", {})
        rules = []
        art = []
        for name, token, d in traced:
            tok = overrides.get(name, token)
            if name in adaptive:
                light, dark = adaptive[name]
                rules.append(("  .%s{fill:%s}" % (name, palette.hexof(light)),
                              "    .%s{fill:%s}" % (name, palette.hexof(dark))))
                art.append('<path class="%s" fill="%s" d="%s"/>'
                           % (name, palette.hexof(light), d))
            else:
                art.append('<path fill="%s" d="%s"/>'
                           % (palette.hexof(tok), d))

        # `in_disc: 0.84` PUTS THE WHOLE MARK INSIDE A FILLED CIRCLE, at that
        # fraction of the circle's diameter, with the rest of the square
        # transparent. The one variant that is a different COMPOSITION rather
        # than a recolour, so it is the one that needs a transform.
        #
        # WHY A DISC AND NOT THE FLIPPED MOUNTAIN. Both answer "an ink mountain
        # disappears on a dark tab strip". Flipping (favicon-on-dark.svg) keeps
        # the mark transparent and repaints the rock, which is the lighter
        # touch and stays the default. A disc instead keeps the artwork in its
        # real colours and gives it its own ground -- correct where the mark
        # must read as the club's mark rather than adapt, and the shape Kyle
        # asked for on 2026-09-02. A `rect` would be wrong here for the reason
        # the flipped file exists at all: a square in a tab strip is a box, and
        # a circle in a tab strip is an icon.
        #
        # THE SCALE IS DERIVED FROM THE TRACE, not written down. See
        # enclosing_circle(): the mark's own circumscribed circle is measured
        # and mapped onto `in_disc` of the frame's, so the ring of white is
        # even the whole way round without anybody nudging a number, and a
        # retrace that changes the artwork's size moves the scale with it.
        in_disc = overrides.get("in_disc")
        if in_disc:
            mcx, mcy, mr = enclosing_circle([d for _, _, d in traced], w, h)
            radius = min(w, h) / 2.0
            s = (radius * in_disc) / mr
            body.append('  <circle cx="%s" cy="%s" r="%s" fill="%s"/>'
                        % (n(w / 2.0), n(h / 2.0), n(radius),
                           palette.hexof(overrides.get("disc_fill", "white"))))
            body.append('  <g transform="matrix(%s,0,0,%s,%s,%s)">'
                        % (n(s), n(s), n(w / 2.0 - s * mcx), n(h / 2.0 - s * mcy)))
            body.extend("    " + a for a in art)
            body.append("  </g>")
            print("      in_disc %.2f: mark r=%.1f at (%.1f,%.1f) -> r=%.1f, "
                  "white ring %.1f px of %d" % (in_disc, mr, mcx, mcy,
                                                radius * in_disc,
                                                radius * (1 - in_disc), w))
        else:
            body.extend("  " + a for a in art)

        style = ""
        if rules:
            style = ('  <style>\n%s\n    @media (prefers-color-scheme: dark){\n'
                     '%s\n    }\n  </style>\n'
                     % ("\n".join(r[0] for r in rules),
                        "\n".join(r[1] for r in rules)))

        svg = ('%s<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d"\n'
               '     role="img" aria-label="Caltech Alpine Club">\n%s%s\n</svg>\n'
               % (header, w, h, style, "\n".join(body)))

        # IT HAS TO PARSE. These files are fetched by every visitor on every
        # page, and a malformed one fails where nothing here would notice: the
        # browser shows a broken image and make_icons.py renders from it. The
        # mistake that prompted this check is the easy one -- XML forbids "--"
        # inside a comment, and every header below is a comment written in the
        # house style, which uses "--" as an em dash.
        try:
            import xml.etree.ElementTree as ET
            ET.fromstring(svg)
        except Exception as err:
            sys.exit("%s is not well-formed XML: %s\n"
                     "  If the message points at a comment, look for '--' in "
                     "it: XML does not allow it there." % (out_name, err))

        if check_only:
            print("      --check: would write %s (%d bytes)" % (out_name, len(svg)))
        else:
            with open(os.path.join(IMAGES, out_name), "w",
                      encoding="utf-8", newline="\n") as f:
                f.write(svg)
            print("      wrote assets/images/%-18s %6d bytes  %s"
                  % (out_name, len(svg),
                     ", ".join("%s=%s" % kv for kv in overrides.items()) or "as drawn"))
    return worst


# =========================================================== the drawings ==

HEADER_LOGO = """<!--
  THE CALTECH ALPINE CLUB LOGO: a sun behind a mountain range, then the club's
  name. FOR LIGHT BACKGROUNDS. The dark-background twin is logo-on-dark.svg,
  which is the same trace with one token swapped.

  GENERATED. Do not edit this file. Edit the drawing at art/logo.png and run
  `python tools/trace_logo.py`.

  THE NAME IS IN THE ARTWORK. Anywhere this is placed must not also print
  "Caltech Alpine Club" as text beside it, which is what the masthead used to
  do with the older, wordless mark.

  THE FIELD IS TRANSPARENT, including the ridgeline between the sun and the
  mountain: that gap is the page showing through, so it is right on paper and
  right on ink without a third file.

  THE COLOURS ARE HARDCODED, not currentColor. This file is loaded with <img>,
  and an SVG used as an image has no parent to inherit from, so currentColor
  resolves to black. That is why the light and dark versions are two files
  rather than one that adapts. tools/trace_logo.py reads the accent, ink and
  paper tokens out of assets/css/style.css, so the mark cannot end up a shade
  off the accent beside it; tools/audit.py fails the build if a colour in here
  is not one the stylesheet declares.
-->
"""

HEADER_LOGO_DARK = """<!--
  THE LOGO, FOR DARK BACKGROUNDS: the masthead and the footer. Identical to
  logo.svg except that the mountain, the rule and "ALPINE CLUB" are paper
  instead of ink. The sun keeps the accent, which is legible on both.

  GENERATED from art/logo.png alongside logo.svg, from the SAME trace, so the
  two cannot drift apart. See logo.svg's header.
-->
"""

HEADER_FAVICON = """<!--
  Favicon: a sun behind one peak, in a disc. A SIMPLER MARK THAN logo.svg, on
  purpose, and it carries no text.

  At 16 pixels a wordmark is a smudge and fine detail is one grey dot, so this
  is its own drawing reduced to the shapes that survive: the disc, the sun, a
  capped peak. A favicon has always been a separate file for exactly this
  reason. 16px is a different design problem, not a smaller one.

  THE BACKGROUND IS TRANSPARENT, AND THE MOUNTAIN ADAPTS. There is no ground
  rect: a browser tab should show the mark, not a pale square sitting in the
  tab strip. Legibility is handled instead by the &lt;style&gt; block, which paints
  the mountain ink normally and paper under prefers-color-scheme: dark.

  THAT REPLACES THE GROUND THIS FILE USED TO CARRY, and the problem the ground
  solved is real: the mountain is a near-black, so a transparent icon with a
  fixed ink mountain merges into a dark tab strip and reads as a bare orange
  ring. Flipping the artwork fixes that without the square. The fill attribute
  is still written alongside the class, so a consumer that applies no CSS gets
  the light colour rather than a default black.

  GENERATED from art/favicon.png. See logo.svg's header.
  Regenerate the raster icons after any change: python tools/make_icons.py
-->
"""

HEADER_FAVICON_DARK = """<!--
  THE FAVICON FOR DARK BROWSER UI. Also transparent; the mountain is paper
  rather than ink, baked into the fill attribute instead of a media query.

  BELT AND BRACES, NOT A SECOND MECHANISM THAT MATTERS ON ITS OWN. favicon.svg
  already adapts by itself through a media query inside the file, which is the
  technique with the widest support. This file exists for the reverse case: a
  browser that honours media="(prefers-color-scheme: dark)" on the &lt;link&gt; but
  does not evaluate a media query inside an SVG being used as an icon. Either
  path lands on a paper mountain, and a browser that does neither still gets a
  transparent icon whose only weak case is a dark tab strip.

  GENERATED from art/favicon.png, from the SAME trace as favicon.svg.
-->
"""

HEADER_FAVICON_DISC = """<!--
  THE MARK IN A WHITE DISC, for dark browser UI. Kyle, 2026-09-02.

  The artwork is untouched: the same trace, the same alpenglow C, the same ink
  mountain, placed inside a filled white circle with an even ring of white
  around it. Outside that circle the square is transparent, so nothing here
  ever renders as a box.

  THE THIRD ANSWER TO ONE PROBLEM, and worth being clear about which is which,
  because all three sit in this directory. An ink mountain vanishes on a dark
  tab strip. favicon.svg flips the mountain to paper through a media query;
  favicon-on-dark.svg is the same flip baked into an attribute, for a browser
  that reads the &lt;link&gt; media attribute but not a query inside an SVG icon.
  Both keep the mark transparent and CHANGE THE ARTWORK. This one changes no
  artwork and gives the mark its own ground instead. Reach for it where the
  mark has to look like the club's mark rather than adapt to whatever is behind
  it: an avatar on a dark service, a dark mode app tile.

  A CIRCLE AND NOT A SQUARE. A pale rectangle behind an icon reads as a box
  somebody forgot to remove, which is exactly the complaint that took the
  ground out of favicon.svg on 2026-09-02. A disc reads as the icon.

  HOW BIG THE MARK IS INSIDE IT IS MEASURED, NOT CHOSEN BY EYE. See
  enclosing_circle() and the in_disc note in build(). The fraction was picked
  by rendering the candidates at 16, 32 and 48 px on a dark strip, which are
  the sizes and the background it actually has to survive; a 512px view flatters
  every one of them equally and decides nothing.

  GENERATED from art/favicon.png, from the SAME trace as favicon.svg.
  Raster master: assets/images/favicon-disc-512.png, tools/make_icons.py.
-->
"""

HEADER_MARK = """<!--
  THE BARE MARK, ON TRANSPARENCY. For slides, print, a Slack workspace icon,
  an org avatar: places where whoever places it knows what is behind it.

  NOT FOR A BROWSER TAB. The mountain is ink, so on a dark tab strip it
  disappears and leaves a bare orange ring. That is measured, not feared: it
  is what shipped on 2026-08-31 before favicon.svg gained its ground. Use
  favicon.svg there, which carries its own paper.

  THE DARK-BACKGROUND TWIN IS mark-on-dark.svg. Same trace, mountain in paper.

  GENERATED from art/favicon.png. Raster copies at mark-512.png and
  mark-on-dark-512.png, written by tools/make_icons.py.
-->
"""

HEADER_MARK_DARK = """<!--
  THE BARE MARK ON TRANSPARENCY, FOR DARK BACKGROUNDS: the mountain is paper
  rather than ink. The C keeps the accent, which reads on both.

  GENERATED from art/favicon.png, from the SAME trace as mark.svg.
-->
"""

# LOGO -- a white field that is keyed out, an orange sun and "CALTECH", and
# everything else in one dark colour: the mountain, the baseline rule, and
# "ALPINE CLUB". That dark layer is called `figure` rather than `ink` because
# it is the layer that flips between the light and dark files.
LOGO = dict(
    src="logo.png",
    trim=(254, 254, 254),
    width=1024,
    layers=[
        ("field",  (254, 254, 254), None),      # keyed out, never drawn
        ("sun",    (254,  87,  13), "alpenglow"),
        ("figure", ( 16,  16,  16), "ink"),
    ],
    outputs=[
        ("logo.svg",         {},                   HEADER_LOGO),
        ("logo-on-dark.svg", {"figure": "paper"},  HEADER_LOGO_DARK),
    ],
)

# FAVICON -- an open orange C-ring with a mountain breaking out of it, on a
# white field that is keyed out.
#
# IT IS NOT A DISC ANY MORE, AND IT IS NOT CROPPED LIKE ONE (2026-08-31).
# The previous drawing was a filled sun with the mountain contained inside it,
# so crop_to_disc() could recover the circle from the accent's own extremes and
# mask everything outside it. The mountain in this drawing extends past the
# ring on the right: measured against that circle, 30.0% of the dark layer
# falls outside it, and crop_to_disc() would have cut that flank off WITHOUT
# failing -- the trace succeeds, the fit looks fine, and the icon is simply
# missing a third of its mountain. If a future drawing goes back to a
# contained disc, put `disc=FAVICON_SUN` back; the function is still there.
#
# NO `snow` LAYER, BUT STILL A BACKGROUND. The white inside the old disc was
# drawn snow and had to be painted as a layer. Here the only white IS the
# field, so it is keyed out with everything outside the mark -- and then put
# back underneath as one full-bleed rect via `background`.
#
# THAT RECT IS NOT DECORATION. Keying the field out and stopping there ships an
# ink mountain on transparency, and make_icons.py renders apple-touch-icon.png
# onto --ink: measured 2026-08-31, the iOS icon came out as a bare orange ring
# with the mountain invisible inside it. A dark browser tab strip does the same
# thing. The old mark was an opaque disc for exactly this reason.
# THE C GOT THICKER (2026-09-02). Same construction, redrawn heavier, because
# the old one failed at the only size that matters. Measured off both drawings
# by fitting the ring's circle (sub-pixel: outer radius p10/p90 453.5/454.5):
#
#     2026-08-31   R_out 454.0   ring width 119.3   = 0.263 * R
#     2026-09-02   R_out 504.7   ring width 194.0   = 0.384 * R
#
# 46% heavier relative to the radius. At a 16px tab the old stroke landed on
# about 1.5 device pixels and anti-aliased into a smear; this one lands on
# about 2.5 and holds. The arc's endpoints barely moved (the gap edge went from
# -34.5 to -32.0 degrees, the lower-left from 150.0 to 142.5), so this is the
# same mark at a different weight and not a new one.
FAVICON_SUN = (252, 69, 4)
FAVICON = dict(
    src="favicon.png",
    trim=(254, 254, 254),
    square=True,        # see pad_to_square(); make_icons.py forces a square
    # NO `background` HERE ANY MORE. Every output below is transparent; the two
    # that a browser puts on an unknown colour handle it by flipping the
    # mountain instead (see `adaptive`), and the home-screen rasters -- which
    # genuinely cannot be transparent, because iOS composites alpha to black --
    # get their ground imposed by tools/make_icons.py at render time. Keeping a
    # ground here would have put it back in the browser tab, which is the one
    # place Kyle does not want it.
    width=512,
    layers=[
        ("field", (254, 254, 254),  None),      # keyed out, never drawn
        ("sun",   FAVICON_SUN,      "alpenglow"),
        ("rock",  ( 21,  21,  21),  "ink"),
    ],
    # A REAL WHITE CHANNEL BETWEEN THE TWO HALVES (Kyle, 2026-09-02). In the
    # drawing they touch: 248 pixels of the mountain are adjacent to the C and
    # the narrowest separation is 2 px in this 512 frame, which is 0.06 of a
    # device pixel at 16 px. Colour carried it; one ink does not, and a
    # monochrome mark is the case this was raised for.
    #
    # 20 px here, chosen from renders in ONE INK at 16, 24, 32 and 48 px: it is
    # the smallest value whose seam is unambiguous by 24 px. Nothing separates
    # at 16 px in one colour, at any k, so there was no point paying more
    # mountain for it. It costs 1.8% of the rock, entirely along the edge that
    # was touching. See widen_gap() for why this is not a uniform erosion.
    gap=("rock", "sun", 20),
    # FOUR FILES, ONE TRACE. Two axes, and they are independent: whether the
    # mark carries its own ground (a tab strip: yes; a slide: no), and which
    # way round the mountain is (on paper: ink; on ink: paper). Every one of
    # them used to be either missing or a hand-recoloured copy.
    outputs=[
        # THE BROWSER TAB ICON IS TRANSPARENT AND ADAPTS (2026-09-02, Kyle).
        # No ground, so there is no pale square in the tab; the mountain flips
        # to paper under prefers-color-scheme: dark, so nothing disappears.
        ("favicon.svg", {"adaptive": {"rock": ("ink", "paper")}},
                        HEADER_FAVICON),
        # The same thing again with the dark colour baked in as an attribute,
        # linked with media="(prefers-color-scheme: dark)". Belt and braces:
        # this covers a browser that honours the media attribute on <link> but
        # not a media query inside an SVG it is using as an icon.
        ("favicon-on-dark.svg", {"rock": "paper"},   HEADER_FAVICON_DARK),
        # The mark unaltered, inside a white disc, for dark UI that should see
        # the club's real colours rather than a flipped mountain. 0.84 of the
        # disc's diameter: see HEADER_FAVICON_DISC for how that was measured.
        ("favicon-disc.svg", {"in_disc": 0.84},     HEADER_FAVICON_DISC),
        ("mark.svg",            {},                 HEADER_MARK),
        ("mark-on-dark.svg",    {"rock": "paper"},  HEADER_MARK_DARK),
    ],
)

SOURCES = [LOGO, FAVICON]


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--check", action="store_true",
                    help="report the fit and write nothing")
    # VARY THE CHANNEL BY FLAG, NOT BY EDITING THE SPEC. Trying a value should
    # not mean a diff, and comparing two should not mean remembering to put the
    # first one back. Pair it with --check to look without writing.
    ap.add_argument("--gap", type=int, default=None, metavar="PX",
                    help="override the white channel between the mark's two "
                         "halves, in source pixels (spec default: %d). 0 to "
                         "restore the drawing as it was traced."
                         % FAVICON["gap"][2])
    a = ap.parse_args()

    print("trace_logo.py  (palette from assets/css/style.css: %s)"
          % ", ".join("%s %s" % (t, palette.hexof(t))
                      for t in ("alpenglow", "ink", "paper")))
    worst = max(build(s, check_only=a.check, gap=a.gap) for s in SOURCES)

    print()
    print("worst layer disagrees with its source on %.2f%% of pixels "
          "(under ~2%% is fine)." % (100 * worst))
    if not a.check:
        print("\nNow regenerate the rasters that come from these:")
        print("  python tools/make_icons.py")
        print("  python tools/make_social.py")
    return 0


if __name__ == "__main__":
    sys.exit(main())
