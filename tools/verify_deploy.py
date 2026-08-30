#!/usr/bin/env python3
"""
 verify_deploy.py - check a deployed copy of the site from outside.

     python tools/verify_deploy.py https://staging.alpine.caltech.edu
     python tools/verify_deploy.py https://staging.alpine.caltech.edu --expect HEAD

 tools/check.php answers "is the data right" and has to run on the server.
 tools/audit.py answers "is the markup right" and needs PHP locally.
 This one answers "did the upload land, and is the server configured the way we
 assumed", using nothing but the public internet and the standard library, so
 it runs on any machine including one with no PHP installed.

 --expect <sha|HEAD> also asserts that the copy out there is running that
 commit, read from the version.txt the deploy writes. HEAD means "whatever this
 clone is on". Without it the commit is reported but never fails the run,
 because a laptop's HEAD is not necessarily what the club meant to publish.

 Exit status is 0 when nothing failed, 1 otherwise. Warnings do not fail.
"""

import subprocess
import sys
import urllib.error
import urllib.request

# EVERY PUBLIC PAGE. roles.php was missing until 2026-08-28, and the omission
# is the reason this reported "23 checks, 0 failed" against a staging copy that
# did not have the page at all. A verifier that does not know about a page
# cannot tell you it is gone. Add a row here whenever a page is added.
PAGES = ["/", "/index.php", "/events.php", "/join.php", "/gear.php",
         "/roles.php", "/about.php", "/support.php", "/sitemap.php"]

# Paths .htaccess is supposed to refuse. If any of these come back 200 the
# server is ignoring .htaccess, which means AllowOverride is off.
# data/ has its own .htaccess denying everything in it, and the root one
# denies .csv by name as well. These lines prove both are actually in force on
# the server rather than merely present in a file -- if AllowOverride is off,
# every officer's address is one URL away.
DENIED = ["/includes/config.php", "/includes/", "/cache/", "/data/gear.php",
          "/data/people.csv", "/data/roles.csv", "/data/assignments.csv"]

TIMEOUT = 20
UA = "alpine-club-deploy-check"

results = []


def record(ok, label, detail=""):
    """ok is True, False, or None for 'cannot tell yet'. None never fails."""
    results.append((ok, label, detail))


class Headers(dict):
    """Header names are case-insensitive, and Cloudflare sends them lowercased.

    A plain dict() of the response headers made every lookup here miss, so this
    script reported "0 of 3 security headers" against a server that was sending
    all three (staging, 2026-08-19). Look them up without caring about case.
    """

    def __init__(self, pairs):
        dict.__init__(self, ((k.lower(), v) for k, v in pairs))

    def get(self, name, default=""):
        return dict.get(self, name.lower(), default)


def get(url, method="GET"):
    req = urllib.request.Request(url, method=method, headers={"User-Agent": UA})
    try:
        with urllib.request.urlopen(req, timeout=TIMEOUT) as r:
            return r.status, Headers(r.headers.items()), r.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as e:
        return e.code, Headers(e.headers.items()), e.read().decode("utf-8", "replace")
    except Exception as e:                                   # DNS, TLS, timeout
        return None, Headers([]), str(e)


def main(base, expect=None):
    base = base.rstrip("/")

    # --- which commit is actually out there ------------------------------
    # The deploy writes this. Before it existed, the only way to answer "did my
    # change reach the server" was to read the page and hope you would notice
    # the difference -- and on 2026-08-28 that produced a clean bill of health
    # for a copy that was several weeks stale. A version stamp turns that into
    # one line of fact.
    status, _, version = get(base + "/version.txt")
    live = ""
    if status == 200 and version.startswith("commit"):
        for row in version.splitlines():
            if row.startswith("short"):
                live = row.split()[1]
        record(True, "reports which commit it is running", "commit " + live)
    elif status == 404:
        record(None, "reports which commit it is running",
               "no version.txt - deployed before the stamp existed, or by hand")
    else:
        record(None, "reports which commit it is running", "HTTP %s" % status)

    if expect:
        if not live:
            record(False, "running the expected commit",
                   "it does not say which commit it is running")
        elif expect.startswith(live) or live.startswith(expect):
            record(True, "running the expected commit", live)
        else:
            record(False, "running the expected commit",
                   "expected %s, the server says %s" % (expect[:9], live))

    # --- the pages themselves -------------------------------------------
    home_body = ""
    for path in PAGES:
        status, headers, body = get(base + path)
        if status is None:
            record(False, "GET " + path, body)
            continue
        ctype = headers.get("Content-Type", "")
        ok = status == 200
        record(ok, "GET " + path, "HTTP %s, %s, %d bytes" % (status, ctype, len(body)))
        if path == "/":
            home_body = body

        # The failure that matters most: PHP served as text rather than run.
        if "<?php" in body[:4000]:
            record(False, "PHP executes at " + path,
                   "PHP SOURCE was returned instead of a page. PHP is not "
                   "enabled for this document root.")

    if not home_body:
        record(False, "home page", "nothing came back; the checks below mean little")

    # --- did it render our site, or somebody else's ----------------------
    if home_body:
        record("Alpine Club" in home_body, "home page mentions the club")
        record("</html>" in home_body.lower(), "home page is complete HTML")
        record("Index of /" not in home_body,
               "home page is not a directory listing",
               "an empty document root, or the upload went to the wrong folder")

    # --- the calendar ----------------------------------------------------
    status, _, events = get(base + "/events.php")
    if status == 200:
        looks_live = any(w in events for w in ("Upcoming", "upcoming", "Past events"))
        record(looks_live, "events page rendered its sections")
        if "calendar could not be reached" in events.lower():
            record(False, "calendar fetched",
                   "the server could not reach Google; check egress and cache/")

    # --- .htaccess ---------------------------------------------------------
    status, headers, _ = get(base + "/")
    htaccess_signals = 0
    if headers.get("X-Content-Type-Options", "").lower() == "nosniff":
        htaccess_signals += 1
    if headers.get("X-Frame-Options", ""):
        htaccess_signals += 1
    if headers.get("Referrer-Policy", ""):
        htaccess_signals += 1
    record(htaccess_signals >= 2, "security headers arrive (.htaccess is read)",
           "%d of 3 present" % htaccess_signals)

    for path in DENIED:
        status, _, _ = get(base + path)
        if status == 403:
            record(True, "denied " + path, "HTTP 403")
        elif status == 404:
            # Before the first upload everything 404s, which proves nothing.
            record(None, "denied " + path,
                   "HTTP 404 - not there yet, so this proves nothing")
        else:
            record(False, "denied " + path, "HTTP %s - it is being served" % status)

    # --- our 404, not the server's ----------------------------------------
    status, _, body = get(base + "/this-page-does-not-exist-9f3a")
    record(status == 404, "missing page returns 404", "HTTP %s" % status)
    if status == 404:
        # The hostname appears in Apache's own error page, so "alpine" alone is
        # not evidence. Look for something only our template says.
        record("Alpine Club" in body, "404 page is ours, not Apache's default",
               "%d bytes" % len(body))

    # --- staging must not be indexed --------------------------------------
    if "staging" in base or "github.io" in base:
        status, headers, body = get(base + "/")
        tag = headers.get("X-Robots-Tag", "")
        meta = "noindex" in body.lower()
        record("noindex" in tag.lower() or meta,
               "this copy tells search engines to stay away",
               "X-Robots-Tag: %s" % (tag or "(none)"))

    # --- report ------------------------------------------------------------
    print("\n%s\n" % base)
    failed = 0
    unknown = 0
    for ok, label, detail in results:
        if ok is None:
            mark, unknown = "  --  ", unknown + 1
        elif ok:
            mark = "  ok  "
        else:
            mark, failed = "  FAIL", failed + 1
        print("%s  %-48s %s" % (mark, label, detail))
    print("\n%d checks, %d failed, %d could not be told\n"
          % (len(results), failed, unknown))
    if failed:
        print("A failure is something to read, not necessarily something broken.")
        print("docs/DEPLOY.md has a table of what each one usually means.\n")
    return 1 if failed else 0


if __name__ == "__main__":
    args = sys.argv[1:]
    want = None
    if "--expect" in args:
        i = args.index("--expect")
        try:
            want = args.pop(i + 1)
        except IndexError:
            print("--expect needs a commit, or the word HEAD")
            sys.exit(2)
        args.pop(i)
        if want.upper() == "HEAD":
            try:
                want = subprocess.check_output(
                    ["git", "rev-parse", "--short", "HEAD"]).decode().strip()
            except Exception:
                print("--expect HEAD needs to be run inside a clone of the repository")
                sys.exit(2)
    if len(args) != 1:
        print(__doc__)
        sys.exit(2)
    sys.exit(main(args[0], want))
