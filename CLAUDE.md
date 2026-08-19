# CLAUDE.md — Caltech Alpine Club website

The club's site. Kyle is Secretary; the sponsorship side of the club lives in
`~/Documents/1Research/planning/projects/alpine-club/`, and everything about the
site itself lives here.

## Where the answers are

| Question | File |
|---|---|
| How do I change an officer, the gear list, a photo? | `README.md` |
| How do I publish? | `docs/DEPLOY.md` |
| What machines, what paths, what permissions? | `docs/SERVERS.md` |
| What happened last time somebody deployed? | `docs/DEPLOY-LOG.md` |
| Why is it hosted this way? | `docs/HOSTING.md` |
| How should the copy read? | `docs/WRITING.md` |

## Publishing — hand Kyle the instructions EVERY time (Kyle, 2026-08-19)

*"you need to make deployment easy and intuitive. give me instructions each time."*

Any session that changes a file here **ends by telling him exactly how to
publish it** — the literal commands, in a copy-paste block, with nothing left to
remember or look up. Never leave a change sitting in the working tree with only
a note that it is "not yet deployed."

The whole procedure is two commands:

```bash
git add -A && git commit -m "what changed"
./tools/deploy.sh
```

`deploy.sh` asks for his Caltech username once and remembers it, checks the
server is reachable before doing anything slow (a missing "Tunnel All" VPN is
the usual cause and it says so), uploads over **one** ssh connection so Duo
prompts once, sets the IMSS permissions, and runs `tools/verify_deploy.py`
itself. Add `--dry-run` to see what would be sent, `--target prod` for the
cutover.

The reason this rule exists: the site is only correct once it is *on the
server*, and a fix that never gets deployed is a fix that did not happen.

## Two invariants worth not breaking

1. **Deployed == committed.** `deploy.sh` sends only what git has committed, so
   everything on the server exists somewhere else too. Do not add a path around
   that.
2. **The calendar is the whole CMS.** An officer adds an event to the club's
   Google Calendar and the site shows it within five minutes
   (`includes/config.php`, `cache_ttl`). There is no second step and no
   per-trip page. Anything that would require editing this repository to
   announce a trip is the wrong design.

## After a deploy

Write an entry in `docs/DEPLOY-LOG.md` — one per deploy or per attempt at one,
newest at the top, actual output pasted rather than described. The failures are
the valuable part.
