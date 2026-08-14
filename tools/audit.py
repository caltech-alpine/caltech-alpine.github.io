#!/usr/bin/env python3
"""
 audit.py — pre-publish checks across every page at once.

 tools/check.php answers "is the DATA right" (links, officers, calendar).
 This answers "is the MARKUP right", over the rendered HTML of every page:
 heading order, alt text, image dimensions, link text, duplicate ids,
 placeholder text left behind, and anything still pointing at the mailing
 list address by mistake.

 Run the dev server first:  php -S 127.0.0.1:8800
 Then:                      python tools/audit.py http://127.0.0.1:8800
"""

import colorsys
import os
import re
import sys
import urllib.error
import urllib.request

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

PAGES = ["index.php", "events.php", "join.php", "gear.php", "about.php",
         "support.php", "404.php"]

# alpineclub@ is the MAILING LIST. A contact link wired to it mails ~200 people.
LIST_ADDRESS = "alpineclub@caltech.edu"

# Deliberately does NOT include "TBD" or "coming soon". Both are legitimate
# words in a real calendar entry — the club's own BANFF listing says
# "Time TBD" — and a check that cries wolf on live data gets ignored.
PLACEHOLDERS = ("lorem ipsum", "TODO", "FIXME", "placeholder text",
                "REPLACE ME", "your text here")

fails, warns = [], []


def get(base, page):
    try:
        return urllib.request.urlopen(base + "/" + page, timeout=20).read().decode("utf-8")
    except urllib.error.HTTPError as err:          # 404.php answers 404 by design
        return err.read().decode("utf-8")


def check(page, html):
    # --- headings ------------------------------------------------------------
    hs = [(int(m.group(1)), re.sub(r"<[^>]+>", "", m.group(2)).strip())
          for m in re.finditer(r"<h([1-6])[^>]*>(.*?)</h\1>", html, re.S)]
    h1s = [t for lvl, t in hs if lvl == 1]
    if len(h1s) != 1:
        fails.append("%s: %d <h1> (want exactly 1)" % (page, len(h1s)))
    for i in range(1, len(hs)):
        if hs[i][0] - hs[i - 1][0] > 1:
            fails.append("%s: heading jumps h%d -> h%d at %r"
                         % (page, hs[i - 1][0], hs[i][0], hs[i][1][:40]))

    # --- images --------------------------------------------------------------
    for m in re.finditer(r"<img\b[^>]*>", html):
        tag = m.group(0)
        src = (re.search(r'src="([^"]*)"', tag) or [None, "?"])[1]
        if 'alt=' not in tag:
            fails.append("%s: <img> with no alt: %s" % (page, src))
        if not re.search(r"\bwidth=", tag) or not re.search(r"\bheight=", tag):
            warns.append("%s: <img> without width/height (layout shift): %s" % (page, src))

    # --- links ---------------------------------------------------------------
    for m in re.finditer(r"<a\b([^>]*)>(.*?)</a>", html, re.S):
        attrs, text = m.group(1), re.sub(r"<[^>]+>", "", m.group(2)).strip()
        href = (re.search(r'href="([^"]*)"', attrs) or [None, ""])[1]
        if not text and "aria-label" not in attrs:
            fails.append("%s: link with no text: %s" % (page, href))
        if href.startswith("http") and "rel=" not in attrs and "caltech.edu" not in href:
            warns.append("%s: external link without rel: %s" % (page, href[:60]))

    # --- the expensive mistake ----------------------------------------------
    if LIST_ADDRESS in html:
        fails.append("%s: links the MAILING LIST address (%s) — mails every member"
                     % (page, LIST_ADDRESS))

    # --- duplicate ids -------------------------------------------------------
    ids = re.findall(r'\bid="([^"]+)"', html)
    for i in set(ids):
        if ids.count(i) > 1:
            fails.append("%s: duplicate id=%r (%d times)" % (page, i, ids.count(i)))

    # --- leftovers -----------------------------------------------------------
    text = re.sub(r"(?s)<(script|style).*?</\1>", " ", html)
    text = re.sub(r"<[^>]+>", " ", text)
    for p in PLACEHOLDERS:
        if p.lower() in text.lower():
            fails.append("%s: placeholder text %r still on the page" % (page, p))

    # --- head ----------------------------------------------------------------
    if '<meta name="viewport"' not in html:
        fails.append("%s: no viewport meta (mobile will render at desktop width)" % page)
    if not re.search(r'<html[^>]+lang=', html):
        fails.append("%s: <html> has no lang attribute" % page)
    desc = re.search(r'<meta name="description" content="([^"]*)"', html)
    if not desc or len(desc.group(1)) < 50:
        warns.append("%s: missing or very short meta description" % page)


# ---------------------------------------------------------------- contrast --
# The stylesheet documents which token may be used for accent TEXT and which may
# not. That rule was stated in a comment and then broken in four hover rules, so
# it is checked here instead. Anything that fails is a WCAG AA failure, not a
# matter of taste.
CONTRAST = [
    ("text",            "paper",   "body text on the page"),
    ("text",            "paper-2", "body text on the tinted band"),
    ("text",            "white",   "body text on a card"),
    ("text-mute",       "paper",   "muted text on the page"),
    ("text-mute",       "paper-2", "muted text on the tinted band"),
    ("text-mute",       "white",   "muted text on a card"),
    ("alpenglow-dark",  "paper",   "--accent-text on the page"),
    ("alpenglow-dark",  "paper-2", "--accent-text on the tinted band"),
    ("alpenglow-dark",  "white",   "--accent-text on a card"),
    ("alpenglow-hover", "paper",   "--alpenglow-hover on the page"),
    ("alpenglow-hover", "paper-2", "--alpenglow-hover on the tinted band"),
    ("alpenglow-hover", "white",   "--alpenglow-hover on a card"),
    ("on-dark",         "ink",     "text on a dark section"),
    ("on-dark-mute",    "ink",     "muted text on a dark section"),
    ("accent-on-dark",  "ink",     "accent text on a dark section"),
    ("white",           "alpenglow", "button label on the accent fill"),
    ("glacier",         "paper",   "glacier text on the page"),
]


def _lum(rgb):
    def f(c):
        return c / 12.92 if c <= 0.03928 else ((c + 0.055) / 1.055) ** 2.4
    r, g, b = [f(c) for c in rgb]
    return 0.2126 * r + 0.7152 * g + 0.0722 * b


def contrast_check():
    css_path = os.path.join(ROOT, "assets", "css", "style.css")
    css = open(css_path, encoding="utf-8").read()

    tokens = {}
    for m in re.finditer(r"--([a-z0-9-]+):\s*hsl\(\s*([\d.]+)\s+([\d.]+)%\s+([\d.]+)%\s*\)", css):
        tokens[m.group(1)] = colorsys.hls_to_rgb(
            float(m.group(2)) / 360.0, float(m.group(4)) / 100.0, float(m.group(3)) / 100.0)

    for fg, bg, label in CONTRAST:
        if fg not in tokens or bg not in tokens:
            warns.append("contrast: token --%s or --%s is gone" % (fg, bg))
            continue
        la, lb = _lum(tokens[fg]), _lum(tokens[bg])
        r = (max(la, lb) + 0.05) / (min(la, lb) + 0.05)
        if r < 4.5:
            fails.append("contrast: %s is %.2f:1, under the AA floor of 4.5" % (label, r))

    # And the rule the tokens exist to enforce: the bright accent is for fills,
    # icons and large display type. It must never be a plain text colour.
    for m in re.finditer(r"([^{}]+)\{([^}]*)\}", css):
        sel, body = " ".join(m.group(1).split()), m.group(2)
        if re.search(r"(?<!-)\bcolor:\s*var\(--alpenglow\)\s*;", body) \
                and "hero__title" not in sel and "::marker" not in sel \
                and "__icon" not in sel:
            fails.append("contrast: %s colours text with --alpenglow "
                         "(4.43:1 on paper, 4.07:1 on tint) — use --accent-text "
                         "or --alpenglow-hover" % sel)


def main():
    base = (sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8800").rstrip("/")
    contrast_check()
    for page in PAGES:
        html = get(base, page)
        print("  %-12s %6d bytes" % (page, len(html)))
        check(page, html)

    print()
    for f in fails:
        print("  FAIL  %s" % f)
    for w in warns:
        print("  WARN  %s" % w)
    if not fails and not warns:
        print("  All clear.")
    print("\n%d failure(s), %d warning(s)" % (len(fails), len(warns)))
    return 1 if fails else 0


if __name__ == "__main__":
    sys.exit(main())
