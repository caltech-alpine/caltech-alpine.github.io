#!/usr/bin/env python3
"""
 build_static.py — render the PHP site to plain HTML for GitHub Pages.

 WHY THIS EXISTS
 ---------------
 GitHub Pages serves static files and does not execute PHP. This site is
 server-rendered PHP, so it cannot be published there as-is. This script starts
 PHP's own development server, requests every page exactly as a browser would,
 and writes the result to _site/ as ordinary HTML.

 That works because the site has no server-side behaviour that depends on the
 visitor: no forms, no logins, no query parameters, no user input. The only live
 input is the club's Google Calendar, and the PHP caches that for 5 minutes.
 This build runs every 30, which is deliberately coarser: GitHub's scheduler
 will not reliably do better, and the pilot is a preview of the design rather
 than the club's calendar of record. See docs/HOSTING.md for the comparison.

 WHAT IS DIFFERENT IN THE OUTPUT
 -------------------------------
   * links between pages lose the .php extension (index.php -> index.html) and
     stay relative, so the output works from any base path — a project page
     under /reponame/ or a user page at the domain root — with no configuration
   * preview.php is NOT built. It needs PHP at request time, and it is an
     officer tool, not a public page
   * sitemap.php is rendered to sitemap.xml and its <loc> values are rewritten
     to match wherever the build is being published

 Usage:
   python tools/build_static.py                       # -> _site/
   python tools/build_static.py --base-url https://caltech-alpine.github.io
"""

import argparse
import os
import re
import shutil
import subprocess
import sys
import time
import urllib.error
import urllib.request

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(ROOT, "_site")

# Public pages, in the order the sitemap lists them. preview.php is absent on
# purpose — see the module docstring.
PAGES = ["index.php", "events.php", "join.php", "gear.php", "about.php",
         "support.php", "404.php"]

# Copied verbatim. Everything the pages reference at runtime.
ASSET_DIRS = ["assets"]
ASSET_FILES = ["robots.txt"]

HOST, PORT = "127.0.0.1", 8899


def start_server():
    """PHP's built-in server, which is enough to render the site once."""
    # ALPINE_STATIC switches off the activity log (includes/activity.php).
    # Without it the build would log its own crawl as if it were visitors,
    # and every outbound link would come out as go.php?to=..., which a
    # static host cannot serve.
    env = dict(os.environ, ALPINE_STATIC="1")
    proc = subprocess.Popen(
        [os.environ.get("PHP", "php"), "-S", "%s:%d" % (HOST, PORT), "-t", ROOT],
        cwd=ROOT, env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    base = "http://%s:%d" % (HOST, PORT)
    for _ in range(50):                       # up to ~10s for it to come up
        try:
            urllib.request.urlopen(base + "/index.php", timeout=1).read()
            return proc, base
        except urllib.error.HTTPError:
            return proc, base                 # answering at all is enough
        except Exception:
            time.sleep(0.2)
    proc.terminate()
    raise SystemExit("PHP dev server never came up — is php on PATH?")


def fetch(url):
    try:
        return urllib.request.urlopen(url, timeout=30).read().decode("utf-8")
    except urllib.error.HTTPError as err:
        # 404.php answers with a 404 status. That is correct; we still want it.
        return err.read().decode("utf-8")


def site_url():
    """Whatever includes/config.php calls this site, so we can replace it."""
    cfg = os.path.join(ROOT, "includes", "config.php")
    m = re.search(r"'url'\s*=>\s*'([^']+)'", open(cfg, encoding="utf-8").read())
    return m.group(1).rstrip("/") if m else "https://alpine.caltech.edu"


def rewrite_links(html, base_url="", noindex=False):
    """index.php -> index.html, everywhere it appears in an attribute."""
    # The leading character may be the opening quote of a relative link
    # (href="join.php") OR a slash inside an absolute one
    # (<link rel=canonical href="https://host/join.php">). Matching only the
    # quote left every canonical and og:url on the site pointing at a .php file
    # that a static host does not serve.
    for page in PAGES:
        stem = page[:-4]
        html = re.sub(r'(["\'/])%s(?=["\'#?])' % re.escape(page),
                      r"\g<1>%s.html" % stem, html)

    # Those absolute URLs also still name the production host. Left alone on a
    # pilot build they point every page at a URL that does not serve this
    # content, which is worse than having no canonical at all.
    if base_url:
        html = html.replace(site_url(), base_url.rstrip("/"))

    # A pilot must not compete with the real site in search results. This is a
    # meta tag rather than a robots.txt rule on purpose: Disallow stops the
    # crawl, which stops the crawler ever SEEING the noindex.
    if noindex and "<meta name=\"robots\"" not in html:
        html = html.replace("</title>",
                            "</title>\n<meta name=\"robots\" content=\"noindex, nofollow\">", 1)
    return html


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--base-url", default="",
                    help="where this build will be published; rewrites the sitemap")
    ap.add_argument("--noindex", action="store_true",
                    help="mark every page noindex; use for pilot/staging builds")
    ap.add_argument("--keep", action="store_true",
                    help="do not wipe _site/ first")
    args = ap.parse_args()

    if os.path.isdir(OUT) and not args.keep:
        shutil.rmtree(OUT)
    os.makedirs(OUT, exist_ok=True)

    proc, base = start_server()
    try:
        for page in PAGES:
            html = rewrite_links(fetch(base + "/" + page), args.base_url, args.noindex)
            dest = os.path.join(OUT, page[:-4] + ".html")
            with open(dest, "w", encoding="utf-8", newline="") as fh:
                fh.write(html)
            print("  %-14s %7d bytes" % (os.path.basename(dest), len(html)))

        # The sitemap is XML, not HTML, and carries absolute URLs. Rewrite ONLY
        # what is inside <loc>: an earlier version replaced every http(s) host in
        # the document and clobbered the xmlns, which is a sitemaps.org URL that
        # is an identifier, not a link, and must be left exactly as it is.
        xml = fetch(base + "/sitemap.php")
        for page in PAGES:
            xml = xml.replace("/" + page, "/" + page[:-4] + ".html")
        xml = xml.replace("/index.html", "/")
        if args.base_url:
            new = args.base_url.rstrip("/")
            xml = re.sub(r"(<loc>)https?://[^<]*?(?=/|</loc>)",
                         lambda m: m.group(1) + new, xml)
        with open(os.path.join(OUT, "sitemap.xml"), "w", encoding="utf-8", newline="") as fh:
            fh.write(xml)
        print("  %-14s %7d bytes" % ("sitemap.xml", len(xml)))
    finally:
        proc.terminate()

    for d in ASSET_DIRS:
        src = os.path.join(ROOT, d)
        if os.path.isdir(src):
            shutil.copytree(src, os.path.join(OUT, d))
            print("  copied %s/" % d)
    for f in ASSET_FILES:
        src = os.path.join(ROOT, f)
        if os.path.isfile(src):
            shutil.copy2(src, os.path.join(OUT, f))
            if f == "robots.txt" and args.base_url:
                dst = os.path.join(OUT, f)
                txt = open(dst, encoding="utf-8").read()
                txt = re.sub(r"Sitemap:.*", "Sitemap: %s/sitemap.xml"
                             % args.base_url.rstrip("/"), txt)
                open(dst, "w", encoding="utf-8", newline="").write(txt)
            print("  copied %s" % f)

    # Pages needs this to stop Jekyll eating files that begin with an underscore.
    open(os.path.join(OUT, ".nojekyll"), "w").close()

    total = sum(os.path.getsize(os.path.join(dp, f))
                for dp, _, fs in os.walk(OUT) for f in fs)
    print("\n_site/ built: %.1f MB" % (total / 1048576.0))
    return 0


if __name__ == "__main__":
    sys.exit(main())
