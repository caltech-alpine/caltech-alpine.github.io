#!/usr/bin/env python3
"""
=============================================================================
 make_social.py — build the link-preview image.
=============================================================================

     python tools/make_social.py     ->  assets/images/social-default.png

 This is the picture Slack, iMessage and Twitter show when somebody pastes a
 link to the site. Without one they show a grey box, which is a poor first
 impression given how much of this club's sharing happens in Slack.

 It is a FALLBACK. Drop a real photograph at assets/images/social.jpg — 1200
 by 630, people visible — and the site uses that instead automatically. This
 exists so the site is never bare while you find one.

 Requires: pillow, svglib, reportlab, rlPyCairo, cairosvg (only to run the
 script; the output PNG is committed, so nobody else needs them).
=============================================================================
"""

import io
import os

from PIL import Image, ImageDraw, ImageFont

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(HERE)
IMAGES = os.path.join(ROOT, "assets", "images")
OUT = os.path.join(IMAGES, "social-default.png")

W, H = 1200, 630
INK = (20, 24, 26)
PAPER = (236, 231, 221)
MUTED = (150, 156, 150)
ALPENGLOW = (192, 82, 45)
ACCENT_ON_DARK = (226, 138, 101)   # the mark's colour, 6.86:1 on INK


def mark_layer(size):
    """The club mark, rasterised from assets/images/logo.svg.

    Imported here rather than at module scope: cairosvg is the one dependency
    this script has that is awkward to install, and the rest of the image is
    still worth generating without it.
    """
    svg = os.path.join(IMAGES, "logo.svg")
    if not os.path.exists(svg):
        return None
    try:
        import cairosvg
    except ImportError:
        print("  note: cairosvg missing, so the mark is omitted")
        return None
    png = cairosvg.svg2png(url=svg, output_width=size, output_height=size)
    return Image.open(io.BytesIO(png)).convert("RGBA")


def font(size, bold=False):
    """A real font if this machine has one, otherwise Pillow's built-in."""
    candidates = [
        r"C:\Windows\Fonts\arialbd.ttf" if bold else r"C:\Windows\Fonts\arial.ttf",
        "/System/Library/Fonts/Helvetica.ttc",
        "/usr/share/fonts/truetype/dejavu/DejaVuSans%s.ttf" % ("-Bold" if bold else ""),
    ]
    for path in candidates:
        if os.path.exists(path):
            try:
                return ImageFont.truetype(path, size)
            except Exception:
                pass
    return ImageFont.load_default()


def topo_layer():
    """The Mt Wilson contour map, rendered and tinted for use as texture."""
    svg = os.path.join(IMAGES, "mt-wilson-topo.svg")
    if not os.path.exists(svg):
        return None
    try:
        from svglib.svglib import svg2rlg
        from reportlab.graphics import renderPM
    except ImportError:
        print("  (svglib not installed — skipping the contour texture)")
        return None

    # The map draws with currentColor, which rasterises to black — invisible
    # against a dark background. Force the strokes white and render on black,
    # so the luminance of the result IS the mask: bright where a contour is.
    tmp_svg = os.path.join(HERE, "_topo_white.svg")
    with open(svg, encoding="utf-8") as fh:
        markup = fh.read().replace("currentColor", "#ffffff")
    with open(tmp_svg, "w", encoding="utf-8") as fh:
        fh.write(markup)

    drawing = svg2rlg(tmp_svg)
    scale = (W * 1.15) / drawing.width
    drawing.scale(scale, scale)
    drawing.width *= scale
    drawing.height *= scale

    tmp = os.path.join(HERE, "_topo_tmp.png")
    renderPM.drawToFile(drawing, tmp, fmt="PNG", bg=0x000000)
    layer = Image.open(tmp).convert("L")
    os.remove(tmp)
    os.remove(tmp_svg)
    return layer


def main():
    img = Image.new("RGB", (W, H), INK)

    texture = topo_layer()
    if texture is not None:
        texture = texture.resize((int(W * 1.15), int(texture.height * (W * 1.15) / texture.width)))
        mask = Image.eval(texture, lambda v: int(v * 0.16))     # ~16% strength
        white = Image.new("RGB", texture.size, (255, 255, 255))
        img.paste(white, (-int(W * 0.07), -int(texture.height * 0.18)), mask)

    d = ImageDraw.Draw(img)
    x = 84

    # Accent rule, the same one that sits above the hero eyebrow.
    d.rectangle([x, 150, x + 88, 155], fill=ALPENGLOW)

    d.text((x, 188), "C A L T E C H", font=font(27, True), fill=MUTED)

    d.text((x, 236), "ALPINE CLUB", font=font(104, True), fill=(255, 255, 255))

    d.text((x, 372), "Less lab. More mountains.", font=font(46), fill=ALPENGLOW)

    # NOT "to get Caltech and JPL outdoors": membership is open to anyone, and
    # this image is the first thing somebody sees when a link is pasted.
    d.text((x, 452), "Hiking, backpacking, climbing, and skiing.", font=font(28), fill=PAPER)
    d.text((x, 490), "Open to Caltech, JPL and anyone else.", font=font(28), fill=PAPER)

    d.text((x, 552), "alpine.caltech.edu", font=font(25), fill=MUTED)

    # The mark, on the right, clear of the longest line of text above.
    mark = mark_layer(280)
    if mark is not None:
        img.paste(mark, (W - 280 - 90, (H - 280) // 2), mark)

    img.save(OUT, "PNG", optimize=True)
    print("  wrote %s (%.0f KB, %dx%d)"
          % (os.path.relpath(OUT, ROOT), os.path.getsize(OUT) / 1024.0, W, H))


if __name__ == "__main__":
    main()
