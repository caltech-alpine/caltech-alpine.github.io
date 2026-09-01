#!/usr/bin/env python3
"""
 test_roles.py -- pretend to be next year's secretary and try to break the site.

 WHY THIS EXISTS
 ---------------
 The officer system's whole promise is "change the data, not the website".
 That promise is only worth anything if somebody has actually tried the changes
 a new officer will make -- and the failure mode when it breaks is not a crash.
 It is a page that renders perfectly and says something false: an officer who is
 not on the roster, a job advertised as open that somebody is doing, an email
 address that is right on one page and eight months out of date on another.
 Nothing about the rendered site would tell you, which is exactly why it has to
 be checked mechanically.

 So this runs the edits themselves. For each one it rewrites the CSVs, renders
 every page through real PHP, and asks what actually came out -- including
 asking every page whether the OLD name and the OLD address are still anywhere
 on the site, which is the check a human reading one page cannot do.

 Usage:
   python tools/test_roles.py           # run them all
   python tools/test_roles.py -v        # print each page assertion as it passes

 The data files are restored afterwards, including if a scenario fails or you
 interrupt it. It never leaves the roster edited.
"""

import io
import os
import re
import shutil
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.request

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
# Everything a person edits is in data/. One place, one rule.
DATA = os.path.join(ROOT, "data")
FILES = ["people.csv", "roles.csv", "assignments.csv"]

# Every page a person or a role can appear on. A change is only safe if it is
# consistent across ALL of them -- the whole point of one authoritative source.
PAGES = ["index.php", "about.php", "roles.php", "gear.php", "join.php"]

HOST, PORT = "127.0.0.1", 8898
VERBOSE = "-v" in sys.argv

_failures = []
_checks = 0


# --------------------------------------------------------------------------
#  the data files, as text we can edit and put back
# --------------------------------------------------------------------------

def read(name):
    return io.open(os.path.join(DATA, name), encoding="utf-8").read()


def write(name, text):
    io.open(os.path.join(DATA, name), "w", encoding="utf-8", newline="").write(text)


def add_row(name, row):
    """Append a data row, keeping the file's trailing newline habit."""
    text = read(name)
    if not text.endswith("\n"):
        text += "\n"
    write(name, text + row + "\n")


def edit_cell(name, match, column, value):
    """Set one column of the first data row containing `match`.

    `match` is compared against the row with its quoting removed, so the same
    match string keeps working after a row has already been rewritten once.
    Matching the raw line instead meant the SECOND edit to a row quietly found
    nothing, and the test then reported a site failure that was really a
    failure in this file.
    """
    lines = read(name).split("\n")
    header = None
    for i, line in enumerate(lines):
        if line.startswith("#") or not line.strip():
            continue
        if header is None:
            header = [c.strip() for c in split_csv(line)]
            continue
        if match in plain(line):
            cells = split_csv(line)
            cells += [""] * (len(header) - len(cells))
            cells[header.index(column)] = value
            # Re-quote EVERY cell, not just the one being changed. Quoting only
            # the edited cell silently corrupted the row: a description contains
            # commas, so writing it back bare split it into extra columns and
            # the role lost its maximum. The site then behaved correctly on
            # nonsense data and the test blamed the site.
            lines[i] = ",".join(quote(c) for c in cells)
            write(name, "\n".join(lines))
            return
    raise SystemExit("test bug: no row matching %r in %s" % (match, name))


def drop_row(name, match):
    lines = [l for l in read(name).split("\n")
             if l.startswith("#") or match not in plain(l)]
    write(name, "\n".join(lines))


def person(person_id):
    """One row of data/people.csv, as {column: value}.

    Scenarios read the real name and address from here rather than repeating
    them as literals. A test that hardcodes zauvil@caltech.edu is one more
    place that address lives, and it goes stale the day he steps down -- which
    would be this file failing the rule it exists to enforce.
    """
    rows = list(__import__("csv").DictReader(
        [l for l in read("people.csv").split("\n") if not l.startswith("#")]))
    for r in rows:
        if (r.get("person_id") or "").strip() == person_id:
            return r
    raise SystemExit("test bug: no person %r in people.csv" % person_id)


def plain(line):
    """The row with its quoting removed, for matching that survives a rewrite."""
    try:
        return ",".join(split_csv(line))
    except Exception:
        return line


def split_csv(line):
    return next(iter(__import__("csv").reader([line])))


def quote(value):
    return '"%s"' % value.replace('"', '""')


# --------------------------------------------------------------------------
#  rendering
# --------------------------------------------------------------------------

def start_server():
    env = dict(os.environ, ALPINE_STATIC="1")
    proc = subprocess.Popen(
        [os.environ.get("PHP", "php"), "-S", "%s:%d" % (HOST, PORT), "-t", ROOT],
        cwd=ROOT, env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    base = "http://%s:%d" % (HOST, PORT)
    for _ in range(60):
        try:
            urllib.request.urlopen(base + "/index.php", timeout=1).read()
            return proc, base
        except urllib.error.HTTPError:
            return proc, base
        except Exception:
            time.sleep(0.2)
    proc.terminate()
    raise SystemExit("PHP dev server never came up -- is php on PATH?")


def render(base):
    """Every page, as {page: html}. Fails loudly on a PHP error in any of them."""
    out = {}
    for page in PAGES:
        try:
            html = urllib.request.urlopen(base + "/" + page, timeout=30).read().decode("utf-8")
        except urllib.error.HTTPError as err:
            html = err.read().decode("utf-8")
        if re.search(r"Fatal error|Parse error|Warning:</b>", html):
            raise AssertionError("PHP error while rendering %s" % page)
        out[page] = html
    return out


def text_of(html):
    """Tags stripped, whitespace collapsed -- what a reader actually sees."""
    html = re.sub(r"(?s)<(script|style)[^>]*>.*?</\1>", " ", html)
    html = re.sub(r"(?s)<!--.*?-->", " ", html)
    return re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", html))


# --------------------------------------------------------------------------
#  assertions
# --------------------------------------------------------------------------

def check(label, condition, detail=""):
    global _checks
    _checks += 1
    if condition:
        if VERBOSE:
            print("      ok   %s" % label)
    else:
        _failures.append("%s%s" % (label, ("  -- " + detail) if detail else ""))
        print("      FAIL %s%s" % (label, ("  -- " + detail) if detail else ""))


def on_page(pages, page, needle, why):
    check(why, needle in text_of(pages[page]),
          "%r not found on %s" % (needle, page))


def not_on_page(pages, page, needle, why):
    check(why, needle not in text_of(pages[page]),
          "%r still on %s" % (needle, page))


def strip(pages):
    """Just the homepage recruitment block, or '' when it is not rendering.

    Scoped on purpose. Asking whether the whole homepage mentions "Film
    Festival" answers the wrong question -- the page has a section ABOUT the
    film festivals, and always will. What matters is whether the club is asking
    for somebody to run them.

    Matched to the closing tag of the outer div rather than to the first
    "</div></div>" it can find: the block became a heading plus a <ul> of role
    names on 2026-08-31, so the lazy match stopped inside it and every check
    below silently narrowed to the heading.
    """
    m = re.search(r'(?s)<div class="wanted-strip">.*?\n</div>', pages["index.php"])
    return text_of(m.group(0)) if m else ""


def role_block(pages, role_id):
    """One role's entry on Get Involved, so a claim about it is not confused
    with the identical claim about a different role further up the page.

    Raises rather than returning '' when the role is not on the page at all.
    An empty string would make every "this must NOT say X" check pass without
    looking at anything, which is the failure mode that makes a green test
    suite worse than none.
    """
    m = re.search(r'(?s)<article class="role[^"]*"\s*\n?\s*id="%s">.*?</article>'
                  % re.escape(role_id), pages["roles.php"])
    if not m:
        raise AssertionError("role %r is not on roles.php at all" % role_id)
    return text_of(m.group(0))


def wanted_list(pages):
    """The 'ways to get involved right now' list, or '' when it is absent.

    Scoped for the same reason strip() is: every job is on this page somewhere,
    with its description, whether or not the club is asking for somebody. What
    is being advertised is a question about this list alone.
    """
    m = re.search(r'(?s)<ul class="wanted[^"]*">.*?</ul>', pages["roles.php"])
    return text_of(m.group(0)) if m else ""


def body_of(pages, page):
    """One page without the site footer.

    The footer carries the club's two shared addresses on every page, by
    design. A check about what a PAGE does with contact links has to stop
    before it, or it is really a check about the footer.
    """
    html = pages[page]
    cut = html.find("<footer")
    return html if cut < 0 else html[:cut]


def in_link(pages, page, address, why):
    """An address reachable on this page, whether shown or behind a linked name."""
    check(why, ("mailto:" + address) in pages[page] or address in text_of(pages[page]),
          "%r is not reachable on %s" % (address, page))


def nowhere(pages, needle, why):
    """The check a human reading one page cannot make.

    Searches the RAW HTML, not the visible text. An old address that survives
    inside a mailto: href is exactly as wrong as one printed on the page and
    considerably harder to notice -- the link says the right person's name and
    writes to the wrong inbox.
    """
    found = [p for p, html in pages.items() if needle in html]
    check(why, not found, "%r still appears on %s" % (needle, ", ".join(found)))


def data_ok(expect_ok=True):
    r = subprocess.run(
        [os.environ.get("PHP", "php"), os.path.join(ROOT, "tools", "check.php"), "--data"],
        cwd=ROOT, capture_output=True, text=True)
    passed = (r.returncode == 0)
    check("the data checker %s" % ("accepts this" if expect_ok else "rejects this"),
          passed == expect_ok, r.stdout.strip().replace("\n", " ")[:300])
    return r.stdout


# --------------------------------------------------------------------------
#  the scenarios
# --------------------------------------------------------------------------

def scenario_1_replace_an_officer(base):
    """Zach leaves and Alice becomes President."""
    outgoing = person("zach-auvil")
    edit_cell("assignments.csv", "zach-auvil,president", "until", "2027")
    add_row("people.csv", 'alice-fell,"Alice Fell",afell@caltech.edu,')
    add_row("assignments.csv", "alice-fell,president,,")
    data_ok()
    p = render(base)

    on_page(p, "about.php", "Alice Fell", "Alice is on the roster")
    in_link(p, "about.php", "afell@caltech.edu", "with her address, from people.csv")
    on_page(p, "roles.php", "Currently Alice Fell", "Get Involved names her too")
    on_page(p, "roles.php", "Alice Fell",
            "and Get Involved names her, without linking her address")
    # The Past officers heading is the 'until' year alone. It was "Through
    # 2027", which asked the reader to work out through-to-when from a column
    # that holds one number.
    on_page(p, "about.php", "2027", "Zach moved to Past officers")
    # The one that matters: his ADDRESS must be gone from the whole site, even
    # though his name is still on the alumni list.
    nowhere(p, outgoing["email"],
            "the outgoing president's address is gone from every page")
    not_on_page(p, "roles.php", outgoing["name"].split()[0],
                "and he is not still shown as doing the job")


def scenario_2_two_co_presidents(base):
    """Alice and Bob become Co-Presidents."""
    add_row("people.csv", 'bob-ridge,"Bob Ridge",bridge@caltech.edu,')
    add_row("assignments.csv", "bob-ridge,president,,")
    data_ok()
    p = render(base)

    on_page(p, "about.php", "Alice Fell", "Alice is still there")
    on_page(p, "about.php", "Bob Ridge", "Bob is there too")
    # title_shared takes over automatically at two people. Nobody retitled anything.
    check("both are now titled Co-President",
          text_of(p["about.php"]).count("Co-President") >= 2)
    check("the homepage stopped asking for a second president",
          "President" not in strip(p), strip(p))
    check("and Get Involved stopped offering the seat",
          "Room for" not in role_block(p, "president"), role_block(p, "president"))


def scenario_3_rename_the_title(base):
    """The club renames the displayed title to Co-President, with one holder."""
    drop_row("assignments.csv", "bob-ridge,president")
    # ONE edit, to the human-facing title. title_shared already says
    # Co-President and is not touched, and neither is any code.
    edit_cell("roles.csv", "president,President", "title", "Co-President")
    data_ok()
    p = render(base)

    on_page(p, "about.php", "Co-President", "the new title is used")
    on_page(p, "about.php", "Alice Fell", "and the holder is still attached to the role")
    on_page(p, "roles.php", "Currently Alice Fell",
            "the role page still knows who does it")
    # THE POINT OF THE WHOLE REFACTOR. Under the old title-matching join, a
    # renamed role lost its holder and the site advertised a job somebody was
    # doing as vacant.
    check("the site does NOT now think the job is empty",
          "Co-President" not in strip(p), strip(p))


def scenario_4_rename_it_back(base):
    """And back again, which must be equally uneventful."""
    edit_cell("roles.csv", "president,Co-President", "title", "President")
    data_ok()
    p = render(base)
    on_page(p, "about.php", "Alice Fell", "still attached after a second rename")
    check("the page says President again",
          re.search(r"\bPresident\b", text_of(p["about.php"])) is not None)


def scenario_5_change_the_staffing(base):
    """Hiking goes from max 2 to max 3."""
    edit_cell("roles.csv", "hiking,", "max_people", "2")
    p = render(base)
    check("two of two hiking coordinators is full, so nothing is offered",
          "filled" not in role_block(p, "hiking"), role_block(p, "hiking"))

    edit_cell("roles.csv", "hiking,", "max_people", "3")
    data_ok()
    p = render(base)
    check("raising the maximum to 3 opens a place, with no code change",
          "2/3 filled" in role_block(p, "hiking"), role_block(p, "hiking"))
    check("and the two people doing it are still counted, not erased",
          "0/3" not in role_block(p, "hiking"), role_block(p, "hiking"))
    check("but it is NOT on the homepage: having room is not being short",
          "Hiking" not in strip(p), strip(p))


def scenario_6_a_role_with_nobody(base):
    """The film festival role has nobody, and it is below its minimum."""
    p = render(base)
    check("the homepage names it",
          "Film Festival Coordinator" in strip(p), strip(p))
    check("and names the thing being joined, not a vague kind of helping",
          "Join the officer team" in strip(p), strip(p))
    # THE ELIGIBILITY LINE IS LOAD-BEARING. Officer positions are held by
    # Caltech students; club membership is not restricted that way. If this
    # sentence is ever lost from the block, the homepage invites the whole
    # Caltech/JPL community into a job it cannot have.
    check("and says who the officer positions are for, in the block itself",
          "for Caltech students" in strip(p), strip(p))
    # THE COUNT IS DERIVED. A hard-coded number goes wrong the day a seat is
    # filled, and it goes wrong silently, so the test compares the sentence
    # against the list rendered directly beneath it.
    listed = strip(p).count(" filled")
    check("and the count in the sentence equals the roles listed under it",
          ("We have %d open officer position%s" % (listed, "" if listed == 1 else "s"))
          in strip(p), strip(p))
    check("with a seat count on each opening",
          "0/1 filled" in strip(p), strip(p))
    # ...and the restriction must not leak into the general membership copy.
    home = text_of(p["index.php"])
    check("while the hero still describes a club wider than the student body",
          "extended Caltech community" in home
          and "Caltech affiliation is not required to join" in home, home[:300])
    # The names ARE the message here. A sentence around them would be the
    # heading restated, so the homepage says nothing about status at all --
    # only roles the club is short of reach this block.
    check("without a sentence explaining that they are open",
          "Looking for" not in strip(p) and "Open" not in strip(p), strip(p))
    # An EMPTY job is measured against min_people, not max: Film Festival has
    # room for two, but the club needs one, and 0/2 would advertise a need
    # nobody stated. max only becomes the denominator once the minimum is met.
    check("Get Involved shows the seats it is short",
          "0/1 filled" in role_block(p, "film_festival"),
          role_block(p, "film_festival"))
    check("...and says so ONCE, not three ways at once",
          "Currently open" not in role_block(p, "film_festival")
          and "Talk to one of the officers" not in role_block(p, "film_festival"),
          role_block(p, "film_festival"))
    # NO MAILTO ON THIS PAGE AT ALL. The roster on About is where somebody
    # decides who to write to; a blank message to a shared address is the least
    # useful thing a "contact us" link can do.
    check("and roles.php opens no mail client anywhere above the footer",
          "mailto:" not in body_of(p, "roles.php"))
    not_on_page(p, "about.php", "Could be you",
                "and the officers grid does not pretend it is a person")


def scenario_7_an_optional_role(base):
    """min_people is the only thing that decides whether the homepage asks.

    Talks is empty and has a minimum of one, so the club is short of it and the
    homepage says so. Dropping the minimum to zero makes it a job that would be
    nice to have: still offered on Get Involved, in the same words as any other,
    and silent on the homepage. One cell, no code, no second edit anywhere.
    """
    p = render(base)
    check("an empty role below its minimum is on the homepage",
          "Talks Coordinator" in strip(p), strip(p))
    check("and it is asked for in the same words as any other role",
          "0/1 filled" in role_block(p, "talks"), role_block(p, "talks"))

    edit_cell("roles.csv", "talks,", "min_people", "0")
    p = render(base)
    check("dropping min_people to 0 takes it off the homepage, with no other edit",
          "Talks" not in strip(p), strip(p))
    # An optional job with nobody in it still has a SEAT, so it still gets a
    # fraction: min_people 0 only says the club can survive without it, and
    # max_people 1 says there is one place to take. What it must never print
    # is "0/0 filled", which is not a status -- see alpine_role_status_line().
    check("but Get Involved still offers it, as a seat count",
          "0/1 filled" in role_block(p, "talks"), role_block(p, "talks"))
    check("...and not as a nonsense fraction or as prose",
          "0/0" not in role_block(p, "talks")
          and "Room for" not in role_block(p, "talks"), role_block(p, "talks"))
    edit_cell("roles.csv", "talks,", "min_people", "1")


def scenario_8_an_email_changes(base):
    """Somebody's address changes. It must change everywhere at once."""
    before = person("forrest-mccann")
    edit_cell("people.csv", "forrest-mccann", "email", "forrest@caltech.edu")
    data_ok()
    p = render(base)

    in_link(p, "about.php", "forrest@caltech.edu", "the roster has the new address")
    in_link(p, "gear.php", "forrest@caltech.edu",
            "and so does the gear page, which names the gear officer")
    on_page(p, "roles.php", before["name"],
            "and Get Involved names him, without linking his address")
    nowhere(p, before["email"],
            "the OLD address is gone from every page on the site")


def scenario_9_add_a_role(base):
    """A brand new job, added with one row and no template edit."""
    add_row("roles.csv",
            'ski_touring,"Ski Touring Coordinator",,"Activity Leaders",1,2,'
            '"Organizes backcountry ski days.","Ski touring",')
    data_ok()
    p = render(base)

    on_page(p, "roles.php", "Ski Touring Coordinator", "the new job is on Get Involved")
    on_page(p, "roles.php", "Organizes backcountry ski days.", "with its description")
    check("and the homepage counts it, because nobody is doing it",
          "Ski Touring Coordinator" in strip(p), strip(p))
    check("its anchor is the role_id", 'id="ski_touring"' in p["roles.php"])

    # Now fill it, and every one of those notices must retract by itself.
    add_row("assignments.csv", "alice-fell,ski_touring,,")
    p = render(base)
    on_page(p, "roles.php", "Currently Alice Fell", "filling it names the holder")
    check("and the homepage notice retracts with no second edit",
          "Ski Touring" not in strip(p), strip(p))
    check("one person can hold two jobs",
          text_of(p["about.php"]).count("Alice Fell") >= 2)


def scenario_10_delete_a_role(base):
    """A job the club has stopped doing. The row goes, and so does every trace."""
    drop_row("roles.csv", "ski_touring,")
    drop_row("assignments.csv", "alice-fell,ski_touring")
    data_ok()
    p = render(base)
    nowhere(p, "Ski Touring", "the deleted job is gone from the whole site")
    check("and Alice is still an officer in her other job",
          "Alice Fell" in text_of(p["about.php"]))


def scenario_10b_retiring_a_role_keeps_its_alumni(base):
    """Retiring a job must not delete the people who used to do it.

    The README promises this in as many words, and the obvious implementation
    breaks it silently: build the Past officers list by walking the roles and
    collecting their old holders, and deleting a role takes its alumni with it.
    """
    # A job of its own, so this scenario does not depend on which real role
    # happens to have alumni -- and does not disturb the ones that do.
    add_row("roles.csv",
            'winter_school,"Winter School Lead",,"Activity Leaders",1,1,'
            '"Ran the winter skills weekend.","The winter school",no')
    add_row("people.csv", 'wanda-crag,"Wanda Crag",,')
    add_row("assignments.csv", 'wanda-crag,winter_school,2024,"Winter School Tsar"')
    data_ok()
    p = render(base)
    on_page(p, "about.php", "Wanda Crag", "she is on the Past officers list")
    on_page(p, "about.php", "Winter School Tsar",
            "under the title she actually held, not the one the job has now")

    # Now retire the job entirely.
    drop_row("roles.csv", "winter_school,")
    data_ok()
    p = render(base)
    on_page(p, "about.php", "Wanda Crag", "and she survives the job being retired")
    on_page(p, "about.php", "Winter School Tsar", "still under her real title")
    not_on_page(p, "roles.php", "Winter School Lead",
                "while the job itself is gone from Get Involved")

    # Without a title_held nothing would record what the job was called, so the
    # checker has to ask for one BEFORE the row is deleted.
    edit_cell("assignments.csv", "wanda-crag,winter_school", "title_held", "")
    out = data_ok(expect_ok=False)
    check("  ...and it says to fill in title_held before retiring a job",
          "title_held" in out, out.strip()[:200])

    # Put the world back for the scenarios that follow.
    drop_row("assignments.csv", "wanda-crag,winter_school")
    drop_row("people.csv", "wanda-crag,")
    data_ok()


def scenario_11_stop_recruiting(base):
    """Temporarily stop advertising a job, without filling it."""
    edit_cell("roles.csv", "film_festival,", "recruiting", "no")
    data_ok()
    p = render(base)
    check("'recruiting = no' takes it off the homepage",
          "Film Festival" not in strip(p), strip(p))

    # Talks is also below its minimum, so the strip is still up for that. Quiet
    # it too and the whole block must disappear rather than render empty. Worth
    # saying explicitly: "not in ''" would pass without looking at anything, so
    # this asks about the element and not about the text inside it.
    edit_cell("roles.csv", "talks,", "recruiting", "no")
    p = render(base)
    check("and with nothing left short, the homepage notice vanishes entirely",
          'class="wanted-strip"' not in p["index.php"])
    on_page(p, "roles.php", "Film Festival Coordinator",
            "but the job still appears on Get Involved with its description")
    check("and is not listed as something to help with",
          "Film Festival" not in wanted_list(p), wanted_list(p))
    # A quiet, empty job has no holders AND no status, so the line that carries
    # them must not render as an empty paragraph under the description.
    check("a quiet empty job renders no staffing line at all",
          "Currently" not in role_block(p, "film_festival")
          and "Open" not in role_block(p, "film_festival"),
          role_block(p, "film_festival"))

    # EVERY ROLE SETTLED. The state the site spends most of a good year in, and
    # the one nobody looks at, so it is worth an assertion: with nothing open
    # anywhere, the sections that advertise openings have to disappear rather
    # than render a heading over an empty list.
    #
    # Done by quieting EVERY row rather than naming the ones that happen to be
    # asking today. The scenarios above accumulate -- a co-president here, a
    # raised maximum there -- so a list of role_ids written now is a list that
    # goes wrong the next time somebody adds a scenario, and it fails by
    # checking nothing rather than by failing.
    settled = read("roles.csv")
    for row in read("roles.csv").split("\n"):
        if row.startswith("#") or not row.strip():
            continue
        cells = split_csv(row)
        if cells[0] == "role_id":
            continue
        edit_cell("roles.csv", cells[0] + ",", "recruiting", "no")
    p = render(base)
    check("with nothing open anywhere, Get Involved drops the whole section",
          'id="open"' not in p["roles.php"])
    check("and the About page drops its invitation band",
          "join-callout" not in p["about.php"])
    check("...and offers the roles page instead, so About is not a dead end",
          "What each of these jobs involves" in text_of(p["about.php"]))
    on_page(p, "roles.php", "The roles",
            "while the roles themselves are still described in full")
    write("roles.csv", settled)

    edit_cell("roles.csv", "talks,", "recruiting", "")
    edit_cell("roles.csv", "film_festival,", "recruiting", "")
    p = render(base)
    check("clearing the cell starts advertising it again",
          "Film Festival Coordinator" in strip(p), strip(p))


def scenario_12_a_handover(base):
    """A filled job whose holder is leaving -- the one case counting cannot see."""
    edit_cell("roles.csv", "treasurer,", "recruiting", "stepping down in June")
    data_ok()
    p = render(base)
    check("the sentence a human wrote is the sentence shown",
          "stepping down in June" in role_block(p, "treasurer"), role_block(p, "treasurer"))
    check("and the current holder is still named",
          "Currently" in role_block(p, "treasurer"))
    check("a handover is not the same as being short, so it stays off the homepage",
          "Treasurer" not in strip(p), strip(p))
    edit_cell("roles.csv", "treasurer,", "recruiting", "")


def scenario_13_broken_data_is_caught(base):
    """Every mistake the validator promises to catch, actually made."""
    cases = [
        ("a role_id that does not exist",
         lambda: add_row("assignments.csv", "alice-fell,tresurer,,")),
        ("a person_id that does not exist",
         lambda: add_row("assignments.csv", "alise-fell,treasurer,,")),
        ("two roles with the same role_id",
         lambda: add_row("roles.csv", 'treasurer,"Second Treasurer",,"Steering Committee",1,1,"x","x",')),
        ("two people with the same person_id",
         lambda: add_row("people.csv", 'kyle-hunady,"Someone Else",x@caltech.edu,')),
        ("more people in a job than max_people allows",
         lambda: add_row("assignments.csv", "alice-fell,secretary,,")),
        ("min_people larger than max_people",
         lambda: edit_cell("roles.csv", "secretary,", "min_people", "5")),
        ("a negative maximum",
         lambda: edit_cell("roles.csv", "secretary,", "max_people", "-1")),
        ("max_people of zero",
         lambda: edit_cell("roles.csv", "secretary,", "max_people", "0")),
        ("a person with no name",
         lambda: add_row("people.csv", "nameless,,,")),
        ("a role_id the site's own pages depend on being deleted",
         lambda: drop_row("roles.csv", "gear,")),
    ]
    snapshot = {f: read(f) for f in FILES}
    for label, break_it in cases:
        for f, text in snapshot.items():
            write(f, text)
        break_it()
        out = data_ok(expect_ok=False)
        # A message is only useful if it names the file to open.
        check("  ...and says which file to fix (%s)" % label,
              any(f in out for f in FILES), out.strip()[:200])
    for f, text in snapshot.items():
        write(f, text)


SCENARIOS = [
    ("1.  Zach leaves and Alice becomes President", scenario_1_replace_an_officer),
    ("2.  Alice and Bob become Co-Presidents", scenario_2_two_co_presidents),
    ("3.  The displayed title becomes Co-President", scenario_3_rename_the_title),
    ("4.  ...and is changed back to President", scenario_4_rename_it_back),
    ("5.  Hiking goes from max 2 to max 3", scenario_5_change_the_staffing),
    ("6.  The Film Festival role has nobody", scenario_6_a_role_with_nobody),
    ("7.  An optional role (min 0) has nobody", scenario_7_an_optional_role),
    ("8.  Somebody's email address changes", scenario_8_an_email_changes),
    ("9.  A new role is added, then filled", scenario_9_add_a_role),
    ("10. A role is deleted", scenario_10_delete_a_role),
    ("10b.Retiring a role keeps its alumni", scenario_10b_retiring_a_role_keeps_its_alumni),
    ("11. Recruiting is paused for a role", scenario_11_stop_recruiting),
    ("12. A filled role whose holder is leaving", scenario_12_a_handover),
    ("13. Broken data is caught, not rendered", scenario_13_broken_data_is_caught),
]


def main():
    backup = tempfile.mkdtemp(prefix="alpine-roster-")
    for f in FILES:
        shutil.copy(os.path.join(DATA, f), os.path.join(backup, f))

    proc, base = start_server()
    try:
        print()
        print("Pretending to be next year's secretary")
        print("=" * 62)
        # The scenarios run in order and build on each other, exactly as a real
        # year of edits would -- scenario 2 inherits the president scenario 1
        # appointed. A suite that reset between every case would never catch the
        # state that accumulates, which is where this kind of system rots.
        for label, fn in SCENARIOS:
            print()
            print("  " + label)
            fn(base)
    finally:
        proc.terminate()
        for f in FILES:
            shutil.copy(os.path.join(backup, f), os.path.join(DATA, f))
        shutil.rmtree(backup, ignore_errors=True)

    print()
    print("=" * 62)
    if _failures:
        print("%d of %d checks FAILED:" % (len(_failures), _checks))
        for f in _failures:
            print("  * " + f)
        print()
        return 1
    print("all %d checks passed. The data files are back as they were." % _checks)
    print()
    return 0


if __name__ == "__main__":
    sys.exit(main())
