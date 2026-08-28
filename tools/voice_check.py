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

    # ---------------------------------------------------------------------
    # The second failure mode: copy trying to SOUND human rather than saying
    # the useful thing. Added August 2026 after a pass that found the site
    # clean by the list above and still reading as written-to-seem-authentic.
    # Every pattern here is anchored to an exact construction, because the
    # underlying problem is judgment and a longer blacklist does not help.
    # ---------------------------------------------------------------------

    # Meta-transitions. The sentence after these can always stand by itself.
    (r"\bwhat follows\b", "meta-transition - start with the next sentence"),
    (r"\bworth (?:noting|knowing|saying)\b", "meta-transition - just say it"),
    (r"\beasiest first (?:move|step)\b", "meta-transition - just say it"),
    (r"\bthe same goes for\b", "meta-transition - start the sentence properly"),
    (r"\bas mentioned above\b", "if they need it twice, one place is wrong"),
    (r"\bat its core\b", "filler"),
    (r"\bone (?:thing|limit) [a-z ]{0,20}before you\b", "meta-transition"),

    # Reassurance about a worry the reader had not raised. A FACTUAL
    # "you do not need prior experience" is fine and is not matched here.
    (r"\bis not a (?:screening|selection|vetting|test|competition)\b",
     "denying a filter announces one - state the reason instead"),
    (r"\bno (?:form|forms) to fill\b", "reassurance nobody asked for"),
    (r"\bno deadline to miss\b", "reassurance nobody asked for"),
    (r"\bnobody has to appoint you\b", "reassurance nobody asked for"),
    (r"\bis a normal outcome\b", "reassurance nobody asked for"),
    (r"\bit(?:'|) ?is fine if\b", "reassurance - either ask for it or do not"),
    (r"\bdo(?:n't| not) worry\b", "reassurance nobody asked for"),
    (r"\beasier than you (?:might )?think\b", "reassurance nobody asked for"),

    # Reaching for informality. Flagged, not banned - read each one.
    (r"\banother pair of hands\b", "written informality"),
    (r"\bwhoever felt like\b", "written informality"),
    (r"\bend up with one\b", "written informality - say what the section is"),
    (r"\banything at all\b", "written informality - name the thing"),
    (r"\btheir own kit\b|\bown kit\b", "British; and 'gear' is the word this site uses"),
]

# Markdown and PHP comments are notes to officers, not copy shown to visitors.
# The style guide itself quotes every phrase in the list, so it is skipped.
SKIP_FILES = {"docs/WRITING.md", "tools/voice_check.py"}


def blank_block_comments(text):
    """Blank the inside of every /* ... */ while keeping the line count.

    The comments in this repository explain WHY a piece of copy reads the way it
    does, which means they quote the copy that was removed -- so scanning them
    reports every rule this file enforces as a violation of itself. Line-start
    matching was not enough: these comments run to five or six lines and only
    the first one starts with the marker.
    """
    out, i = [], 0
    for m in re.finditer(r"/\*.*?\*/", text, re.S):
        out.append(text[i:m.start()])
        out.append(re.sub(r"[^\n]", " ", m.group(0)))
        i = m.end()
    out.append(text[i:])
    return "".join(out)


def strip_noise(line, path):
    """Remove code that is not prose, so a variable name does not get flagged."""
    line = re.sub(r"<\?php|\?>", " ", line)
    line = re.sub(r"^\s*(//|#|\*|/\*).*", " ", line)
    line = re.sub(r"https?://\S+", " ", line)
    return line


def read_prose(path):
    """One file's lines with the block comments blanked out."""
    full = os.path.join(ROOT, path)
    if not os.path.exists(full):
        return []
    with open(full, encoding="utf-8", errors="replace") as fh:
        text = fh.read()
    return blank_block_comments(text).split("\n")


# An em dash is punctuation. As a page's default device for a conversational
# aside it is one of the loudest tells there is, and it is the one thing here a
# word list cannot express -- the problem is the RATE, not any single use. Six is
# not a law; it is high enough that a page under it was never the problem and a
# page over it always was when this was calibrated (August 2026).
EM_DASH_BUDGET = 6

# Only the pages. docs/ and README.md are notes to whoever runs the site, not
# copy a visitor reads, and counting their dashes was the check crying wolf on
# its first run.
EM_DASH_TARGETS = [t for t in DEFAULT_TARGETS if not t.endswith(".md")]


def em_dashes(path):
    """Count em dashes in the visible copy of one file."""
    n = 0
    for raw in read_prose(path):
        line = strip_noise(raw, path)
        n += line.count("\u2014") + line.count("&mdash;")
    return n


def check(path):
    rel = path.replace("\\", "/")
    hits = []
    for n, raw in enumerate(read_prose(path), 1):
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

    scanned = [t for t in targets if t in EM_DASH_TARGETS or argv[1:]]
    heavy = [(t, n) for t in scanned for n in [em_dashes(t)] if n > EM_DASH_BUDGET]
    if heavy:
        print()
        for t, n in heavy:
            print("%s: %d em dashes (budget %d)" % (t, n, EM_DASH_BUDGET))
            print("  Read them. Most are a period wearing a costume - see")
            print("  docs/WRITING.md, 'Em dashes are punctuation, not a voice'.")
            print()

    if not all_hits:
        if heavy:
            return 1
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
