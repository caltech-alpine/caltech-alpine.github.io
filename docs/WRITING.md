# How the copy on this site should read

The site is the club talking. It should sound like an officer who has actually
been up Baldy in February, not like a brochure and not like something generated.

## The standard, stated honestly

The requirement is that nothing on this site reads as machine-written. That is a
writing standard, not a detector score. Automated "AI detectors" cannot be
relied on in either direction: they flag plain, formal, correct prose written by
people, and they miss text that was generated and then edited. Chasing a
particular tool's verdict is a waste of an afternoon.

What can be controlled is the writing, and the tells are consistent enough to
list. A page written to these rules reads as a person's work because it contains
things only a person who was there would know.

## The rules

**Name real things.** The trailhead, the gear room, the day of the week, the
actual price. Generic outdoor writing reads generated because it could be about
any club at any university. Specifics cannot be faked and cannot be mistaken for
filler. Check them before publishing; a wrong specific is worse than a vague
sentence.

**Delete any sentence that would be true of every club.** "We welcome members of
all experience levels" is not information. "You do not need your own rope, ask
the Gear Officer" is.

**No hedged universals.** "Whether you are a complete beginner or a seasoned
mountaineer" is the single most recognizable machine construction in existence.
Cut it wherever it appears.

**Avoid the vocabulary.** These words rarely survive an honest edit: passionate,
vibrant, embark, unlock, elevate, foster, seamless, robust, curated, journey,
landscape, testament, nestled, dive in, community of like-minded individuals.
The club does not talk like that.

**Vary the shape.** Three-item lists in every paragraph, every bullet opening
with a bolded phrase, every section the same length. Real writing is lumpy.
Some sections are two sentences because there are only two sentences to say.

**Say the awkward parts plainly.** What the gear costs, how much notice the Gear
Officer needs, what the club does not organize, which trips need experience.
Copy that admits a limitation is copy a person wrote.

**American English.** `tools/audit.py` checks this, because one page written by
one officer is all it takes to mix the two.

**Numbers beat adjectives.** "A 30-minute drive" rather than "conveniently
close." "Ten officers" rather than "a dedicated team."

## Checking it

```bash
python tools/voice_check.py
```

Reads the PHP source and the Markdown, and reports the constructions above with
a file and line number. It is a word list, so it is not clever: it catches the
obvious cases and it will occasionally flag a word being used properly. Read
each hit and decide. A clean run does not mean the page is well written; it means
the page contains none of the phrases that give the game away.

`tools/audit.py` is the other half, over rendered HTML: headings, alt text,
placeholder text, and the American English check.

## Using an assistant to draft

There is nothing wrong with drafting anything, including with an LLM. The
standard applies to the text that ships, not to how the first version got onto
the screen. Two things make the difference between a draft that survives and one
that reads as generated:

1. **Add what only you know.** A generated draft cannot know where the Tuesday
   run leaves from, or which month the Y stops lending a particular piece of
   gear. Putting those in is most of the edit.
2. **Cut roughly a third.** Generated prose is padded by default. Almost every
   sentence that survives a hard cut is more specific than the paragraph it came
   from.
