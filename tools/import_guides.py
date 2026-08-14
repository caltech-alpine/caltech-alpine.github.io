#!/usr/bin/env python3
"""
=============================================================================
 import_guides.py — pull the outdoor guides off the old site, once.
=============================================================================

 The guides were the only substantial writing on the old Wagtail site, and
 they are the kind of content that should outlive any particular website. This
 script copies them into content/guides/ as plain HTML files that any officer
 can edit in a text editor, with their images and attachments alongside.

     python tools/import_guides.py

 Run it once. After that the files in content/guides/ are the originals and
 this script is only a record of where they came from. Do NOT re-run it after
 editing a guide by hand: it overwrites.

 What it does
   - fetches each guide page from the old site
   - keeps only the article body, discarding the CMS chrome
   - reduces the markup to a small, predictable set of tags
   - downloads images and attachments into the repository
   - writes a front-matter header so the renderer knows the title

 Requires nothing but the Python standard library.
=============================================================================
"""

import html
import os
import re
import time
import urllib.parse
import urllib.request
from datetime import date

BASE = "https://alpine.caltech.edu"
PATH = "/resources/guidesadvice/"

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(HERE)
OUT = os.path.join(ROOT, "content", "guides")
IMG_DIR = os.path.join(ROOT, "assets", "images", "guides")
FILE_DIR = os.path.join(ROOT, "assets", "files", "guides")

# slug -> the title we want to use on the new site. The old titles are
# inconsistent about capitalisation and a couple are needlessly long.
GUIDES = {
    "backpacking-101":              "Backpacking 101",
    "packing-lists":                "Packing lists",
    "climbing-knots":               "Climbing knots",
    "climbing-nutrition":           "Climbing nutrition",
    "winter-mountaineering-advice": "Winter mountaineering",
    "melting-snow":                 "Melting snow for water",
    "blister-prevention":           "Blister prevention",
    "favorite-backpacking-meals":   "Backpacking meals",
    "leave-no-trace":               "Leave No Trace",
}

# Everything else is stripped. Deliberately small: these are articles, not
# web pages, and a narrow tag set is what keeps them portable.
KEEP_TAGS = {"p", "ul", "ol", "li", "a", "b", "strong", "i", "em",
             "h2", "h3", "br", "img", "blockquote"}


def fetch(url, binary=False):
    req = urllib.request.Request(url, headers={"User-Agent": "caltech-alpine-club-migration/1.0"})
    data = urllib.request.urlopen(req, timeout=60).read()
    return data if binary else data.decode("utf-8", "replace")


def div_block_at(text, start):
    """Inner HTML of the div whose opening tag ends at `start`, honouring nesting."""
    depth = 1
    for t in re.finditer(r"<(/?)div\b[^>]*>", text[start:]):
        depth += -1 if t.group(1) else 1
        if depth == 0:
            return text[start:start + t.start()]
    return None


def article_body(page):
    """
    Concatenate the CMS rich-text blocks, which hold the actual writing.

    The CMS nests <div class="rich-text"> inside <div class="airspace-rich-text">,
    so matching both would import every guide twice. Take the inner ones, and
    only fall back to the wrapper if a page has no inner block at all.
    """
    main = re.search(r'<main id="content".*?>(.*)</main>', page, re.S)
    scope = main.group(1) if main else page

    opens = list(re.finditer(r'<div class="rich-text">', scope))
    if not opens:
        opens = list(re.finditer(r'<div class="airspace-rich-text">', scope))

    parts, seen = [], set()
    for m in opens:
        b = div_block_at(scope, m.end())
        if b and b not in seen:      # identical blocks are duplicates, not repetition
            seen.add(b)
            parts.append(b)
    return "".join(parts), scope


def download(url, dest_dir, prefix=""):
    """Save a remote asset locally and return its repo-relative path."""
    os.makedirs(dest_dir, exist_ok=True)
    name = os.path.basename(urllib.parse.urlparse(url).path)
    name = re.sub(r"[^A-Za-z0-9._-]", "_", urllib.parse.unquote(name)) or "file"
    if prefix:
        name = prefix + "_" + name
    dest = os.path.join(dest_dir, name)
    if not os.path.exists(dest):
        try:
            with open(dest, "wb") as fh:
                fh.write(fetch(url, binary=True))
        except Exception as ex:
            print("      ! could not fetch %s (%s)" % (url, ex))
            return None
    return name


def clean(body, slug):
    """Reduce the CMS markup to the small tag set, and localise assets."""
    # Drop widgets wholesale: lightbox buttons, inline svg icons, scripts.
    body = re.sub(r"<(script|style|button|svg)\b.*?</\1>", "", body, flags=re.S | re.I)
    body = re.sub(r"<use\b[^>]*>", "", body, flags=re.I)

    # Images: download and repoint before attributes get stripped.
    def img_sub(m):
        tag = m.group(0)
        src = re.search(r'src="([^"]+)"', tag)
        if not src:
            return ""
        url = urllib.parse.urljoin(BASE, html.unescape(src.group(1)))
        name = download(url, os.path.join(IMG_DIR, slug))
        if not name:
            return ""
        alt = re.search(r'alt="([^"]*)"', tag)
        return '<img src="assets/images/guides/%s/%s" alt="%s" loading="lazy">' % (
            slug, name, html.escape(alt.group(1) if alt else "", quote=True))

    body = re.sub(r"<img\b[^>]*>", img_sub, body, flags=re.I)

    # Links: download attachments, rewrite cross-links between guides,
    # and make anything else that pointed at the old site absolute.
    def a_sub(m):
        href = m.group(1)
        url = html.unescape(href)
        if url.startswith("/documents/"):
            name = download(urllib.parse.urljoin(BASE, url), FILE_DIR, prefix=slug)
            if name:
                return '<a href="assets/files/guides/%s">' % name
            return '<a href="%s">' % urllib.parse.urljoin(BASE, url)
        other = re.match(r"^%s([a-z0-9-]+)/?$" % re.escape(PATH), url)
        if other and other.group(1) in GUIDES:
            return '<a href="guide.php?g=%s">' % other.group(1)
        if url.startswith("/"):
            return '<a href="%s">' % urllib.parse.urljoin(BASE, url)
        return '<a href="%s">' % html.escape(url, quote=True)

    body = re.sub(r'<a\b[^>]*href="([^"]*)"[^>]*>', a_sub, body, flags=re.I)

    # Normalise headings. The old pages mix h2/h3/h4 arbitrarily.
    body = re.sub(r"<(/?)h4\b[^>]*>", r"<\1h3>", body, flags=re.I)
    body = re.sub(r"<(/?)h[56]\b[^>]*>", r"<\1h3>", body, flags=re.I)
    body = re.sub(r"<(/?)h1\b[^>]*>", r"<\1h2>", body, flags=re.I)

    # Strip attributes from every remaining tag except <a> and <img>.
    body = re.sub(r"<(?!/?(?:a|img)\b)(/?)([a-zA-Z0-9]+)\b[^>]*>", r"<\1\2>", body)

    # Drop anything not on the list, keeping its text.
    def tag_filter(m):
        return m.group(0) if m.group(2).lower() in KEEP_TAGS else ""
    body = re.sub(r"<(/?)([a-zA-Z0-9]+)\b[^>]*>", tag_filter, body)

    body = re.sub(r"<p>\s*(?:&nbsp;|\s)*</p>", "", body, flags=re.I)
    body = re.sub(r"\n{3,}", "\n\n", body)
    return body.strip()


def summarise(body):
    """First sentence or so of the first paragraph, for the index page."""
    m = re.search(r"<p>(.*?)</p>", body, re.S)
    if not m:
        return ""
    text = html.unescape(re.sub(r"<[^>]+>", "", m.group(1)))
    text = re.sub(r"\s+", " ", text).strip()
    if len(text) > 165:
        cut = text[:165].rsplit(" ", 1)[0]
        text = cut.rstrip(".,;:") + "…"
    return text


def main():
    os.makedirs(OUT, exist_ok=True)
    today = date.today().isoformat()

    for slug, title in GUIDES.items():
        url = BASE + PATH + slug
        print("  %s" % slug)
        try:
            page = fetch(url)
        except Exception as ex:
            print("    ! fetch failed: %s" % ex)
            continue

        body, scope = article_body(page)
        if not body.strip():
            print("    ! no article body found, skipping")
            continue

        body = clean(body, slug)

        # Attachments sometimes sit in their own CMS block outside the prose.
        # Collect any we have not already linked and list them at the end.
        linked = set(re.findall(r'href="assets/files/guides/([^"]+)"', body))
        extras = []
        for doc in dict.fromkeys(re.findall(r'href="(/documents/[^"]+)"', scope)):
            name = download(urllib.parse.urljoin(BASE, doc), FILE_DIR, prefix=slug)
            if name and name not in linked:
                label = re.sub(r"^%s_" % re.escape(slug), "", name)
                extras.append('<li><a href="assets/files/guides/%s">%s</a></li>' % (name, label))
        if extras:
            body += "\n<h3>Attachments</h3>\n<ul>" + "".join(extras) + "</ul>"

        header = (
            "<!--\n"
            "  Outdoor guide. Plain HTML — edit it in any text editor.\n"
            "  Use only: p, h2, h3, ul, ol, li, a, b, i, br, img, blockquote.\n"
            "\n"
            "  title: %s\n"
            "  summary: %s\n"
            "  source: %s\n"
            "  imported: %s\n"
            "-->\n" % (title, summarise(body).replace("--", "-"), url, today)
        )

        with open(os.path.join(OUT, slug + ".html"), "w", encoding="utf-8") as fh:
            fh.write(header + body + "\n")

        words = len(re.sub(r"<[^>]+>", " ", body).split())
        print("    %s — %d words, %d images, %d files"
              % (title, words, body.count("<img"), body.count("assets/files/")))
        time.sleep(0.4)


if __name__ == "__main__":
    main()
