#!/usr/bin/env python3
"""
=============================================================================
 palette.py -- the site's colours, read from the stylesheet.
=============================================================================

     python tools/palette.py          # print every token and its hex

 ONE SOURCE OF COLOUR, AND IT IS assets/css/style.css.

 Three tools need the palette in a form CSS cannot give them: trace_logo.py
 fills an SVG, make_social.py draws a PNG, audit.py checks contrast ratios.
 Each one had grown its own copy, and copies of a colour go wrong in a way
 nobody sees:

   * trace_logo.py had --paper as #faf5ee. It is #f9f6f0.
   * make_social.py has PAPER = (236, 231, 221) against --on-dark's
     (237, 232, 222) -- one point off on all three channels.
   * make_social.py had ALPENGLOW two points off until 2026-08-27, when it was
     found sitting next to a mark drawn from the real value.

 None of those is visible on its own. All of them are visible as a seam when
 the two colours meet, which is the only place they ever appear. So no tool
 writes a colour literal: they ask for a token by name and get whatever the
 stylesheet says today.

 Only hsl() tokens are read, because that is the notation the :root block uses
 and the one its comment tells the next person to keep using ("same hue, walk
 the lightness"). A token written any other way is not returned at all, which
 fails loudly at the call site rather than quietly resolving to something else.
=============================================================================
"""

import colorsys
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CSS = os.path.join(ROOT, "assets", "css", "style.css")

_TOKEN_RE = re.compile(
    r"--([a-z0-9-]+):\s*hsl\(\s*([\d.]+)\s+([\d.]+)%\s+([\d.]+)%\s*\)")

_cache = None


def tokens():
    """{'alpenglow': (190, 81, 45), ...} -- every hsl() custom property."""
    global _cache
    if _cache is not None:
        return _cache
    with open(CSS, encoding="utf-8") as f:
        css = f.read()
    out = {}
    for m in _TOKEN_RE.finditer(css):
        h, s, l = float(m.group(2)), float(m.group(3)), float(m.group(4))
        r, g, b = colorsys.hls_to_rgb(h / 360.0, l / 100.0, s / 100.0)
        out[m.group(1)] = (round(r * 255), round(g * 255), round(b * 255))
    if not out:
        sys.exit("no hsl() tokens found in %s. If the palette moved to another\n"
                 "  notation, teach this file to read it. Do not paste literals\n"
                 "  back into the tools that call it." % CSS)
    _cache = out
    return out


def rgb(name):
    """One token as an (r, g, b) tuple. Unknown names are a hard error, so a
    typo is a stack trace rather than a silently wrong colour."""
    t = tokens()
    if name not in t:
        sys.exit("no --%s in %s.\n  Known: %s"
                 % (name, os.path.relpath(CSS, ROOT), ", ".join(sorted(t))))
    return t[name]


def hexof(name):
    """One token as '#rrggbb'."""
    return "#%02x%02x%02x" % rgb(name)


def name_for(value):
    """Which token this colour IS, or None. Takes '#rrggbb' or (r, g, b).

    What audit.py uses to reject a colour in a generated file that no token
    resolves to.
    """
    if isinstance(value, str):
        v = value.lstrip("#").lower()
        value = (int(v[0:2], 16), int(v[2:4], 16), int(v[4:6], 16))
    for k, rgbv in tokens().items():
        if rgbv == tuple(value):
            return k
    return None


if __name__ == "__main__":
    for k, v in sorted(tokens().items()):
        print("  --%-18s %-16s #%02x%02x%02x" % (k, str(v), *v))
