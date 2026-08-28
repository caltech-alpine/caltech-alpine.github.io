# How the copy on this site should read

## The one rule

**Do not try to sound human. Say the useful thing plainly. The specific facts
provide the personality.**

Everything below is that rule applied. The site is the club talking; it should
sound like an officer who has been up Baldy in February answering a question, not
like a brochure and not like something written to seem authentic.

## Two ways to get this wrong

The obvious failure is brochure copy: *passionate*, *vibrant*, *whether you are a
complete beginner or a seasoned mountaineer*. That one is easy to spot and
`tools/voice_check.py` catches most of it.

The failure this site actually kept producing is the opposite one, and it is
harder to see because each sentence reads pleasantly on its own. It is copy
written to *perform* being a real person: informal phrasing, transparency about
how the club works, reassurance the reader never asked for, em dashes used as a
conversational shrug. Every sentence sounds like somebody. Together they sound
like nobody, because a person writing to another person is trying to convey
information, not a tone.

Read this and notice what it is doing:

> The club is looking for sponsors and has not settled on what a sponsorship is.
> There are no tiers and no fixed price. What follows is the kind of support that
> would be useful, not a menu — the arrangement is whatever an officer and a
> sponsor agree on.

Four clauses, none of them for the reader. It narrates the club's internal
position to somebody who is deciding whether to send an email. What they need is
what to give, what they get, and where to write.

> The club welcomes sponsorship from companies and other organizations.
>
> [what would help]
>
> Depending on the arrangement, the club can name sponsors on this website, in
> event materials, and at the film festival screenings. There are no fixed tiers,
> so the terms depend on the kind of support.

Same facts, including the flexibility. One clause instead of a manifesto.

## The rules

**Lead with the answer.** For each section, ask what question brought the reader
to it, and answer that first. Do not warm up.

**Name real things.** The trailhead, the gear room, the day of the week, the
actual price. Mt. Islip, Horse Flats, the Caltech Ticket Office, $1 a day, 48
hours. Specifics cannot be faked and cannot be mistaken for filler. Check them
before publishing; a wrong specific is worse than a vague sentence.

**Delete any sentence that would be true of every club.** "We welcome members of
all experience levels" is not information. "You do not need your own rope, ask
the Gear Officer" is.

**Do not explain the club's internal reasoning.** Why there is no formal system,
why a rule is flexible, why the club has not decided something, why the page is
arranged the way it is. If the explanation changes what the reader should *do*,
keep it. Otherwise it is the club talking about itself.

**Do not reassure the reader about a worry they did not raise.** "This is not a
screening process." "There is no form to fill in and no deadline to miss."
"Nobody has to appoint you." "Deciding a trip is not for you is a normal
outcome." Insisting that something is not intimidating is what makes it sound
intimidating. Factual reassurance is different and stays: *"You do not need prior
experience for most events"* is a fact about the events.

**No hedged universals.** "Whether you are a complete beginner or a seasoned
mountaineer" is the single most recognizable machine construction in existence.
Cut it wherever it appears.

The test is whether the range is *information* or *reassurance*. On About,
"Experience ranges from first-time hikers to experienced climbers and
mountaineers" describes who the members are, which is what that section is for.
The same span of words appended to a paragraph about joining is telling the
reader not to feel unqualified, and that one goes.

**Avoid the vocabulary.** These rarely survive an honest edit: passionate,
vibrant, embark, unlock, elevate, foster, seamless, robust, curated, journey,
landscape, testament, nestled, dive in, community of like-minded individuals.

**Avoid the *other* vocabulary too** — the words reaching for informality rather
than polish: *somebody*, *whoever felt like it*, *another pair of hands*, *kit*,
*what happens next*, *end up with one*, *anything at all*. None is banned. Their
concentration is the problem: it makes the site sound like it is trying not to
sound institutional.

**Meta-transitions are always deletable.** *What follows... · One thing worth
noting... · One limit worth knowing before you write... · Easiest first move... ·
The same goes for... · When it comes to... · At its core...* The sentence after
the phrase can stand by itself. Start there instead.

**Em dashes are punctuation, not a voice.** They had become this site's default
device for a conversational aside. Use one where it genuinely improves a
sentence. Otherwise use a period. Two sentences are usually better than one
sentence with a reveal in the middle.

**Vary the shape.** Three-item lists in every paragraph, every section the same
length, a setup sentence followed by exactly three bullets. Real writing is
lumpy. A section with one sentence is finished if there was one sentence to say.
Do not pad a section so the page looks symmetrical.

**Use contractions where a person would.** *You don't need to be an officer.* But
do not go through the site converting every "do not" mechanically; that is the
same performance from the other direction.

**Say the awkward parts plainly.** What the gear costs, how much notice the Gear
Officer needs, what the club does not organize, which trips need experience. Copy
that admits a limitation is copy a person wrote.

**American English.** `tools/audit.py` checks this, because one page written by
one officer is all it takes to mix the two.

**Numbers beat adjectives.** "A 30-minute drive" rather than "conveniently
close." "Ten officers" rather than "a dedicated team."

**Say each fact once, where it is most useful.** The Gear page told the reader
six times that equipment is Caltech/JPL only. Repetition on one page does not
reinforce a fact; it makes the page sound anxious about it.

## Headings

A heading should tell the reader what is underneath it. Read every heading on a
page without its paragraph and see whether you can still say what the section
contains.

Conversational headings fail this almost every time. From this site:

| Was | Is | Why |
|---|---|---|
| Getting people outside | What we do | The first is a mission statement, and the nav already called the anchor "What we do". |
| You do not need a title | You don't need to be an officer | "Title" is an abstraction for the thing we mean. |
| How you end up with one | How to become an officer | Nobody can tell from the first one what the section is. |
| Room for more | Could use more help | |
| If you are not | Outside Caltech | A heading should name its audience, not complete the previous heading. |
| Anything at all | General questions | |
| Booked through the Caltech Y | Caltech Y gear | An inventory heading should say whose equipment it is, not describe the paperwork. |
| For companies → Sponsorship | Sponsorship | An eyebrow saying the same thing as the heading below it is deleted, not reworded. |

## Sometimes the best rewrite is deletion

This is the part that gets skipped. A sentence doing nothing does not need a
better version of itself; it needs to go. Passages removed in the August 2026
pass, with nothing put in their place:

- *"There is no form to fill in and no deadline to miss."*
- *"If what you have in mind is not on that list, say it anyway. The club would
  rather hear the idea than turn down something it did not think to ask for."*
- *"This page does not exist"*, under the heading **Page not found**.
- *"Ask alpine@caltech.edu about any of them. It reaches the officers"*, four
  inches below the same address under **Want to help?**
- The 1a / 1b / 2 / 3 numbering on Join, which drew a workflow diagram over
  something that is one email long.
- Two of the three things a prospective volunteer was asked to write: what they
  had organized before, and how long they expected to be around. Neither
  affects whether somebody can help, and asking implies they do.

After the semantic edits, do a deletion pass. For every sentence: **if I delete
this, does the reader lose useful information?** If not, delete it. A shorter
page is an acceptable and usually a better result. Do not add prose to compensate
for prose you removed.

## Do not invent policy while editing copy

A copy edit is not the place to decide what the club does. Three claims were
removed in August 2026 because nothing in this repository or on the old
alpine.caltech.edu supported them:

- **"The club follows and teaches Leave No Trace practice."** The old site
  published a Leave No Trace guide, which is not the same claim.
- **An open invitation for used ropes and climbing hardware**, "worn but still
  serviceable". The club has no written inspection or acceptance procedure, so
  the site does not publicly ask for used gear whose failure mode is a fall. An
  offer still reaches the Gear Officer, who can judge it.
- **"The club's largest events and the ones that fund most of the rest"** for the
  film festivals, softened to "a major source of its funding".

Also absent, and deliberately: any statement of who may be an officer, how long a
term is, or how elections run. There is no constitution, no bylaws and no officer
handbook anywhere. If an officer confirms one of these, write it down *and record
who said so*. Until then the site says only what is known.

## Checking it

```bash
python tools/voice_check.py
```

Reads the PHP source and the Markdown and reports the constructions above with a
file and line number, plus a per-file em-dash count. It is a word list and a
counter, so it is not clever: it catches the obvious cases and it will
occasionally flag a word being used properly. Read each hit and decide.

**A clean run does not mean the page is well written.** It means the page
contains none of the phrases that give the game away. The failure this document
is mostly about — pleasant sentences that convey nothing — is invisible to a word
list. The check for that is reading every visible word on the rendered page and
asking, sentence by sentence, what information it conveys, whether the reader
needs it here, and whether it could simply be deleted.

`tools/audit.py` is the other half, over rendered HTML: headings, alt text,
placeholder text, and the American English check.

## Using an assistant to draft

There is nothing wrong with drafting anything, including with an LLM. The
standard applies to the text that ships. Three things make the difference:

1. **Add what only you know.** A generated draft cannot know where the Tuesday
   run leaves from, or which month the Y stops lending a particular piece of
   gear. Putting those in is most of the edit.
2. **Cut roughly a third.** Generated prose is padded by default. Almost every
   sentence that survives a hard cut is more specific than the paragraph it came
   from.
3. **Watch for the model trying to sound human.** Told to write like a person, a
   model reaches for informality, transparency and reassurance, because those are
   the surface features of friendly writing. They are not what makes writing
   human. Specific reality is.
