#!/usr/bin/env python3
"""
 voice_check.py - flag copy that reads as machine-written.

     python tools/voice_check.py            # the pages and the docs
     python tools/voice_check.py index.php  # one file

 The rules this enforces, and why they are these rules, are in docs/WRITING.md.
 It reads the SOURCE files rather than rendered HTML, so unlike tools/audit.py
 it needs no PHP and no running server.

 It is a word list. It catches the obvious cases and it will sometimes flag a
 word being used properly - "robust" is a fine word about a rope. Read each hit
 and decide. Exit status is 1 if anything was flagged, so it can be wired into
 a check later, but a clean run only means the page avoids the known tells.
"""

import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

DEFAULT_TARGETS = [
    "index.php", "events.php", "join.php", "roles.php", "gear.php", "about.php",
    "support.php", "404.php",
    "includes/partials.php", "includes/config.php",
    "data/gear.php", "data/sponsors.php",
    # Officer-written prose, same as any page: the role descriptions in
    # roles.csv descriptions and the contact_for lines both end up on screen.
    "data/roles.csv", "data/people.csv",
    "README.md", "docs/HOSTING.md", "docs/DEPLOY.md", "docs/SERVERS.md",
    "docs/WRITING.md", "docs/README.md", "docs/DEPLOY-LOG.md",
]

# Phrases that almost never survive an honest edit. Each is a regex.
PHRASES = [
    (r"whether you(?:'| a)re a\b", "the hedged universal - cut the whole clause"),
    (r"\bno matter your (?:skill|experience|level)", "same construction"),
    (r"\bsomething for everyone\b", "says nothing"),
    (r"\blike-minded\b", "brochure word"),
    (r"\bpassionate about\b", "brochure word"),
    (r"\bvibrant\b", "brochure word"),
    (r"\bembark\b", "brochure word"),
    (r"\bunlock\b", "brochure word"),
    (r"\belevate\b", "brochure word"),
    (r"\bfoster(?:ing|s)? a\b", "brochure word"),
    (r"\bseamless(?:ly)?\b", "brochure word"),
    (r"\bcurated\b", "brochure word"),
    (r"\bnestled\b", "brochure word"),
    (r"\btestament to\b", "brochure phrase"),
    (r"\bdelve\b", "not a word this club uses"),
    (r"\btapestry\b", "not a word this club uses"),
    (r"\bdive (?:in|into)\b", "not a word this club uses"),
    (r"\bgame.?chang(?:er|ing)\b", "not a word this club uses"),
    (r"\bin today's\b", "opener with no content"),
    (r"\bit(?:'|)s worth noting\b", "filler - say the thing"),
    (r"\bwhen it comes to\b", "filler - say the thing"),
    (r"\bat the end of the day\b", "filler"),
    (r"\bunleash\b", "brochure word"),
    (r"\brich (?:history|tradition)\b", "brochure phrase"),
    (r"\bnot (?:just|only) .{3,40} but (?:also )?\b", "the not-just-but construction, used sparingly at most"),
    (r"\bjourney\b", "check it means a real journey"),
    (r"\bcommunity of\b", "usually padding"),
    (r"\bstunning\b", "adjective doing a photograph's job"),
    (r"\bbreathtaking\b", "adjective doing a photograph's job"),
    (r"\bwhether it(?:'|)s\b", "the hedged universal again"),
]

# Markdown and PHP comments are notes to officers, not copy shown to visitors.
# The style guide itself quotes every phrase in the list, so it is skipped.
SKIP_FILES = {"docs/WRITING.md", "tools/voice_check.py"}


def strip_noise(line, path):
    """Remove code that is not prose, so a variable name does not get flagged."""
    line = re.sub(r"<\?php|\?>", " ", line)
    line = re.sub(r"^\s*(//|#|\*|/\*).*", " ", line)
    line = re.sub(r"https?://\S+", " ", line)
    return line


def check(path):
    rel = path.replace("\\", "/")
    hits = []
    full = os.path.join(ROOT, path)
    if not os.path.exists(full):
        return hits
    with open(full, encoding="utf-8", errors="replace") as fh:
        for n, raw in enumerate(fh, 1):
            line = strip_noise(raw, rel)
            for pattern, why in PHRASES:
                m = re.search(pattern, line, re.IGNORECASE)
                if m:
                    hits.append((rel, n, m.group(0).strip(), why, raw.strip()))
    return hits


def main(argv):
    targets = argv[1:] or DEFAULT_TARGETS
    targets = [t for t in targets if t.replace("\\", "/") not in SKIP_FILES]

    all_hits = []
    for t in targets:
        all_hits.extend(check(t))

    if not all_hits:
        print("\n%d files, nothing flagged.\n" % len(targets))
        print("That means none of the known tells are present. It does not mean")
        print("the writing is good - see docs/WRITING.md for the part a word")
        print("list cannot check.\n")
        return 0

    print()
    for rel, n, found, why, raw in all_hits:
        print("%s:%d" % (rel, n))
        print('  "%s"  - %s' % (found, why))
        print("  %s" % (raw[:110] + ("..." if len(raw) > 110 else "")))
        print()
    print("%d flagged across %d files. Read each one; some will be fine.\n"
          % (len(all_hits), len(targets)))
    return 1


if __name__ == "__main__":
    sys.exit(main(sys.argv))
