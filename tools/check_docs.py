#!/usr/bin/env python3
"""
 check_docs.py - the documentation has to point at things that exist.

     python tools/check_docs.py

 Three checks, all offline and instant:

   1. EVERY relative link in every .md file resolves to a real file. This is
      the one that matters. The documentation is now the product -- SECRETARY.md
      is what a new officer follows literally -- and a link into thin air sends
      somebody hunting through a repository they were promised they would never
      have to read. Renaming a file is the way it happens: docs/ORIENTATION.md
      became docs/DEVELOPER.md on 2026-08-30 and six pointers went with it.

   2. Every file SECRETARY.md tells an officer to edit is really there, and is
      linked rather than merely named, so the "task -> location" table can never
      quietly rot into a list of places that used to exist.

   3. The four documents that make up the hierarchy exist at all.

 Exit status is 0 when nothing failed, 1 otherwise. Runs in CI, so a rename
 that breaks a pointer is caught before anybody follows it.
"""

import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

# Directories with nothing hand-written in them.
SKIP_DIRS = {".git", "_site", "_preview", "_deploy", "node_modules",
             "__pycache__", "cache", "logs"}

LINK = re.compile(r"\[[^\]]*\]\(([^)#\s]+)(?:#[^)]*)?\)")

# The documentation hierarchy. Layer 1 is the officer's manual, layer 2 the
# developer's overview, layer 3 the reference folder.
REQUIRED = [
    "SECRETARY.md",
    "README.md",
    "docs/README.md",
    "docs/DEPLOY.md",
]

# What SECRETARY.md promises an officer can edit. If one of these moves, the
# manual is wrong and somebody follows it anyway.
SECRETARY_MUST_LINK = [
    "data/people.csv",
    "data/roles.csv",
    "data/assignments.csv",
    "data/gear.php",
    "data/sponsors.php",
    "data/benefits.csv",
    "includes/config.php",
]

problems = []


def markdown_files():
    out = []
    for dirpath, dirnames, filenames in os.walk(ROOT):
        dirnames[:] = [d for d in dirnames if d not in SKIP_DIRS]
        out += [os.path.join(dirpath, f) for f in sorted(filenames)
                if f.endswith(".md")]
    return sorted(out)


def rel(path):
    return os.path.relpath(path, ROOT).replace(os.sep, "/")


def main():
    # --- 3. the hierarchy exists -----------------------------------------
    for name in REQUIRED:
        if not os.path.isfile(os.path.join(ROOT, name)):
            problems.append("%s is missing. It is one of the four documents "
                            "the rest of them point at." % name)

    # --- 1. every relative link resolves ---------------------------------
    checked = 0
    files = markdown_files()
    for path in files:
        with open(path, encoding="utf-8") as fh:
            text = fh.read()
        for match in LINK.finditer(text):
            target = match.group(1)
            if target.startswith(("http://", "https://", "mailto:", "//")):
                continue
            checked += 1
            resolved = os.path.normpath(
                os.path.join(os.path.dirname(path), target))
            if not os.path.exists(resolved):
                problems.append("%s links to '%s', which does not exist."
                                % (rel(path), target))

    # --- 2. the officer's manual points at real files --------------------
    manual = os.path.join(ROOT, "SECRETARY.md")
    if os.path.isfile(manual):
        with open(manual, encoding="utf-8") as fh:
            text = fh.read()
        linked = set(m.group(1) for m in LINK.finditer(text))
        for name in SECRETARY_MUST_LINK:
            if not os.path.isfile(os.path.join(ROOT, name)):
                problems.append("SECRETARY.md tells officers to edit %s, and "
                                "there is no such file." % name)
            elif name not in linked:
                problems.append("SECRETARY.md mentions %s but does not LINK to "
                                "it. An officer should be able to click through "
                                "to every file they are asked to edit." % name)

    # --- report ------------------------------------------------------------
    print()
    if not problems:
        print("%d markdown files, %d relative links, all resolve."
              % (len(files), checked))
        print("SECRETARY.md links every file it asks an officer to edit.")
        print()
        return 0

    print("%d problem(s) in the documentation:" % len(problems))
    print()
    for i, p in enumerate(problems):
        print("  %d. %s" % (i + 1, p))
    print()
    return 1


if __name__ == "__main__":
    sys.exit(main())
