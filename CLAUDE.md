# CLAUDE.md — Caltech Alpine Club website

The club's site. Kyle is Secretary; the sponsorship side of the club lives in
`~/Documents/1Research/planning/projects/alpine-club/`, and everything about the
site itself lives here.

## Where the answers are — THREE LAYERS, one fact in one of them

Restructured 2026-08-30, because the deploy procedure existed in four places and
three of them were wrong. **Do not answer from the wrong layer, and never copy a
procedure between them.** Add a short link instead.

| Layer | File | Holds |
|---|---|---|
| 1 | **`SECRETARY.md`** | the officer's whole job: edit on GitHub, automatic check, preview, publish, roll back, hand over. Written for somebody who knows a browser and nothing else. **If a change makes routine maintenance easier or harder, it belongs here.** |
| 2 | `README.md` | architecture and rationale, for a developer |
| 3 | `docs/` | access, machines, deployment internals, history |

| Question | File |
|---|---|
| How do I change an officer, the gear list, a photo? | `SECRETARY.md` |
| How do I publish? | `SECRETARY.md` for the command, `docs/DEPLOY.md` for what it does |
| How do I run the site locally, and what are the tests? | `docs/DEVELOPER.md` |
| What machines, what paths, what permissions? | `docs/SERVERS.md` |
| What happened last time somebody deployed? | `docs/DEPLOY-LOG.md` |
| Why is it hosted this way? | `docs/HOSTING.md` |
| How should the copy read? | `docs/WRITING.md` |

**After renaming or moving any document, run `python tools/check_docs.py`.** It
proves every relative link still resolves and that `SECRETARY.md` still links
every file it tells an officer to edit. It runs in CI too.

## Publishing — hand Kyle the instructions EVERY time (Kyle, 2026-08-19)

*"you need to make deployment easy and intuitive. give me instructions each time."*

Any session that changes a file here **ends by telling him exactly how to
publish it** — the literal commands, in a copy-paste block, with nothing left to
remember or look up. Never leave a change sitting in the working tree with only
a note that it is "not yet deployed."

The whole procedure is **commit, push, then one command on the server**:

```bash
git add -A && git commit -m "what changed" && git push
```

```bash
/srv/www.alpine.caltech.edu/www/bin/deploy
```

That second one runs in PuTTY on `portal.caltech.edu`, over the Caltech VPN on
**Tunnel All**. It refuses to publish a commit whose GitHub checks failed or
have not finished, backs the site up, publishes, writes `version.txt`, and then
fetches the public address to confirm the change landed. `--rollback` puts the
previous copy back. Full account: `docs/DEPLOY.md` §A.

**`./tools/deploy.sh` is the laptop fallback, not the procedure** — it has no
check gate, no version stamp and no rollback. Use it only to bring up a new
server, or if GitHub is unreachable.

The reason this rule exists: the site is only correct once it is *on the
server*, and a fix that never gets deployed is a fix that did not happen.

## Four invariants worth not breaking

1. **Deployed == committed, and committed == on GitHub.** `bin/deploy` publishes
   only what is on `origin/main`; `deploy.sh` sends only what git has committed.
   So everything on the server exists somewhere else too, and the server is a
   copy rather than a place. Do not add a path around that, and never edit the
   document root by hand.
2. **The calendar is the whole CMS.** An officer adds an event to the club's
   Google Calendar and the site shows it within five minutes
   (`includes/config.php`, `cache_ttl`). There is no second step and no
   per-trip page. Anything that would require editing this repository to
   announce a trip is the wrong design.

3. **A vacancy is DERIVED, never typed.** `data/roles.csv` lists the jobs;
   `data/assignments.csv` lists the people; a job with nobody in it is shown as
   open, on the About page, on Get Involved and as a line on the homepage.
   There is no status column and no "we are looking for..." paragraph anywhere
   in the source, because a claim like that takes two edits to be right -- one
   to put it up and one to take it down -- and the second one never happens.
   The GSC site reached the same conclusion in its own words: *"a claim that a
   seat is vacant goes stale the day it is filled."* If you are about to add a
   field that an officer has to remember to unset, do not.

   The one exception is `recruiting` in `data/roles.csv`, for the case
   derivation cannot see: a filled job whose holder is leaving. It is free
   text, it is shown verbatim, and it is the only thing here anyone has to
   remember to clear.

4. **THE NEXT SECRETARY IS THE USER (2026-08-30).** The person this repository is
   built for is a student in 2029 who has never met anyone who worked on it.
   Every change here is measured against one question: *could they inherit this,
   update the officers, preview it, publish it, and never need to ask?* Concretely:
   routine work happens **in the GitHub web editor**, needing nothing installed;
   there is **one** preview (`caltech-alpine.github.io`) and **one** publish
   command (`bin/deploy`); a dangerous thing is either impossible or behind a
   flag; and anything they would otherwise have to *remember* becomes a check
   rather than a paragraph. The check gate on `bin/deploy` is that rule applied —
   "look at Actions before deploying" was a sentence nobody would read, so the
   script asks GitHub itself. Adding a step to `SECRETARY.md` is a real cost;
   spend it deliberately, and prefer automating the step away.

## After a deploy

Write an entry in `docs/DEPLOY-LOG.md` — one per deploy or per attempt at one,
newest at the top, actual output pasted rather than described. The failures are
the valuable part.
