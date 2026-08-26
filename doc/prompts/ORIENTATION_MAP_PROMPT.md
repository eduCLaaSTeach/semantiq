# The Orientation Map prompt

A reusable prompt that produces a one-page, plain-language answer to
"where is this project actually up to, and what do I actually need to care
about" - for the person who owns the project, not for the team.

Paste the block below into any project. It is deliberately tool-agnostic: it
works in Claude Code, in a chat window with the repository pasted in, or given
to any assistant that can read files.

---

## When to run it

- Coming back after a break and the shape of the thing has gone fuzzy.
- Too many config files, decisions or documents to hold in your head.
- Somebody new needs orienting without reading the whole repository.
- After a milestone, to re-baseline what "done" now means.

## What it is NOT

It is not a dashboard and not a status report. It reads nothing at runtime.
It is a **snapshot with a date and a commit on it**, regenerated when it stops
being true. That constraint is what keeps it honest: a page that claims to be
live, and quietly is not, is worse than no page.

Reloading the page changes nothing. Every fact in it is typed into the file.
This surprises people, so the file says so in a comment at the top and carries
the date and commit in its footer.

---

## Keeping it current

A snapshot that nobody refreshes becomes a confident lie. Pick a rule and put
it somewhere the rule will actually be read - in this repository it lives in
`CLAUDE.md` under "Keep The Orientation Map Current", so it is part of the
closing routine rather than a good intention.

**Regenerate in the same commit as any of these:**

| Trigger | Why the map goes wrong without it |
| --- | --- |
| A milestone is formally accepted | Position section is stale, which is the whole point of the page |
| The status file changes state | Same |
| A file is added to or removed from `config/` | The "what is yours vs ignore" count is wrong |
| An open item is resolved, or a new blocker appears | The Open section is the part that drives decisions |

**Do not regenerate on ordinary commits.** A map rewritten on every push is
noise in the history and stops being a signal that anything changed.

**Rebuild from the repository, never by editing the numbers in place.** Re-read
the sources every time rather than trusting what the previous version said. An
inherited error survives every regeneration that does not go back to the source,
and it gets more convincing each time it is copied forward.

### The refresh prompt

Shorter than the build prompt, because the page already exists:

```text
Refresh the Orientation Map at <path>.

Rebuild it from the repository, do not edit the existing numbers in place.
Re-read the status file, the active plan, the decision records, config/ and
the recent history, and treat what the current page says as unverified.

Keep the existing structure, palette and type - only the facts change.
Update the date and commit in the footer.

Then tell me exactly what changed on the page since the last version, and
say explicitly if anything I previously listed as open is now resolved.
```

That last line is the useful one. The value of a refresh is not the new page,
it is the diff.

---

## The prompt

```text
Build me an Orientation Map for this project as a standalone HTML page.

An Orientation Map answers three questions for the person who OWNS this
project, in this order:

  1. POSITION - What is this thing actually, in one honest paragraph, and
     where in the build are we right now? Include a sense of proportion:
     roughly how far through, and what is genuinely left.

  2. SURFACE - Of everything in this repository, what do I actually have to
     care about, and what can I ignore? Be specific and name real files.
     Wherever most of something is noise, say so plainly and separate the
     signal from it. Explain WHY the thing I care about is shaped that way,
     if there was a deliberate reason.

  3. OPEN - What is unresolved, unanswered or blocking, and which of those
     are decisions only I can make rather than work you can do?

Ground every claim in the repository. Read the status files, the plan, the
decision records, the config directory and the recent history. Do not
summarise from memory or infer what is probably there. If something is
genuinely unknown, say it is unknown rather than filling the gap.

Write it for a busy owner, not an engineer:
- Lead with the answer, not the background.
- Plain language. If a term is unavoidable, define it in the same sentence.
- Where behaviour looks like a bug but is deliberate, say so explicitly -
  those are the things that waste the most time.
- Numbers where they help proportion. No decoration.

Design constraints for the page:
- Self-contained: one HTML file, opens by double-clicking, no build step and
  no server. Inline all CSS. Any web font needs a real fallback stack.
- Works in both light and dark, following the reader's system setting.
  Define the full palette on :root, and redefine ONLY the tokens inside
  prefers-color-scheme: dark. Set an explicit background on body.
- Readable on a phone. Nothing scrolls sideways; wide tables get their own
  scroll container.
- Encode state in form, not just words - a done thing and a blocked thing
  should be distinguishable at a glance without reading.
- Use a structural device only where it encodes something true. Number
  things only if they are genuinely a sequence.
- Derive the palette and type from THIS project's own identity if it has
  one. Do not reach for a generic template.

Put the date and the current commit in the footer, and state in a comment at
the top of the file that this is a snapshot rather than a live view.

Save it into the repository, tell me the path, and give me a one-paragraph
summary of what it says before I open it.
```

---

## Notes on getting a good result

**The three-part structure is the load-bearing part.** Position, Surface,
Open. Most project summaries only do the first, which is why they are
comforting and useless. Surface is the part that actually reduces confusion;
Open is the part that turns the page into a decision aid.

**"Ground every claim in the repository" is not boilerplate.** Without it an
assistant will produce something plausible and subtly wrong, which is the
worst possible outcome for a document you intend to trust later.

**Ask for the deliberate-looking-like-a-bug callouts.** In practice these are
the highest-value lines on the page. "This field says Not Configured on
purpose, because software must not guess a legal retention period" saves an
hour of investigation every time somebody new sees that screen.

**Regenerate rather than edit.** The page is cheap to rebuild and expensive to
keep partially accurate. When it drifts, run the refresh prompt above.

**Tie the refresh to a milestone, not to a calendar.** A weekly rebuild
produces a page nobody trusts, because most weeks nothing on it moved. A
rebuild at each acceptance produces a page that is accurate at exactly the
moments somebody opens it - which, in practice, is after a break, and a break
usually follows a milestone.

## Variants worth asking for

| Ask for | When |
| --- | --- |
| "...for a new engineer joining this week" | Onboarding, rather than owner orientation |
| "...for someone deciding whether to fund the next phase" | Money conversations - lead with proportion and risk |
| "...covering only <subsystem>" | One area has become the confusing part |
| "...and include what changed since <date>" | Re-baselining after a stretch of work |
