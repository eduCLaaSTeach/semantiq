# Orientation Map Prompts

Copy-ready prompts for the orientation map, the one-page answer to "where is this project actually up to, and what do I actually need to care about". It is written for the person who owns the project, not for the team. Some people call it the project map; it is the same page.

You do not normally need these. In any project carrying the `.claude/` gateway, Claude keeps the map itself: it builds the page when a trigger first fires and rebuilds it whenever a milestone, a sprint state, the configuration surface, or an open item moves, per `.claude/rules/orientation-map.md`. Reach for `/orientation-map` when you want to drive it, and for the raw prompts below when you are in a repository that does not carry the gateway, or when you are handing the idea to somebody who is.

## When To Run It

- Coming back after a break and the shape of the thing has gone fuzzy.
- Too many config files, decisions, or documents to hold in your head.
- Somebody new needs orienting without reading the whole repository.
- After a milestone, to re-baseline what "done" now means.

## What It Is Not

It is not a dashboard and not a status report. It reads nothing at runtime. It is a snapshot with a date and a commit on it, regenerated when it stops being true, and that constraint is what keeps it honest. A page that claims to be live and quietly is not is worse than no page.

Reloading it changes nothing. Every fact in it was typed into the file when it was generated. This surprises people, so the file says so in a comment at the top and carries the date and commit in its footer.

It is not the EOD report and not the session handoff either. An EOD report is one developer's day. A handoff is technical continuity for the next session. The map is the whole project's position, for somebody who was in neither.

## The Build Prompt

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
- Use this project's own design identity. Where the project carries the
  approved design tokens, use those and do not re-derive them; the page is
  standalone rather than inside the app shell. Do not reach for a generic
  template.

No secrets on the page: name a config file and what it is for, never its
values, and no customer or production data anywhere.

Put the date and the current commit in the footer, and state in a comment at
the top of the file that this is a snapshot rather than a live view.

Save it into the repository, tell me the path, and give me a one-paragraph
summary of what it says before I open it.
```

## The Refresh Prompt

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

That last line is the useful one. The value of a refresh is not the new page, it is the difference.

## Keeping It Current

A snapshot nobody refreshes becomes a confident lie. In a gateway project the rule already handles this, and these are the triggers it uses:

| Trigger | Why the map goes wrong without it |
| --- | --- |
| A milestone or sprint item is formally accepted | Position is stale, which is the whole point of the page |
| A sprint task changes state | Same |
| A file is added to or removed from the configuration surface | The signal-against-noise count in Surface is wrong |
| An open item is resolved, or a new blocker appears | Open is the section that drives decisions |
| A non-goal, a documented exception, or an accepted `Critical` or `High` finding is recorded | The owner is carrying a decision the map does not show |

Not on ordinary commits. A map rewritten on every push is noise in the history and stops being a signal that anything changed.

Tie the refresh to a milestone rather than to a calendar. A weekly rebuild produces a page nobody trusts, because most weeks nothing on it moved. A rebuild at each acceptance produces a page that is accurate at the moment somebody actually opens it, which is after a break, and a break usually follows a milestone.

Rebuild from the repository, never by editing the numbers in place. An inherited error survives every regeneration that does not go back to the source, and it gets more convincing each time it is copied forward.

## Getting A Good Result

The three-part structure is the load-bearing part. Position, Surface, Open. Most project summaries only do the first, which is why they are comforting and useless. Surface is the part that actually reduces confusion; Open is the part that turns the page into a decision aid.

"Ground every claim in the repository" is not boilerplate. Without it you get something plausible and subtly wrong, which is the worst possible outcome for a document you intend to trust later.

Ask for the deliberate-looking-like-a-bug callouts. In practice these are the highest-value lines on the page. "This field says Not Configured on purpose, because software must not guess a legal retention period" saves an hour of investigation every time somebody new sees that screen.

Regenerate rather than edit. The page is cheap to rebuild and expensive to keep partially accurate.

## Variants Worth Asking For

| Ask for | When |
| --- | --- |
| `/orientation-map for a new engineer joining this week` | Onboarding, rather than owner orientation |
| `/orientation-map for someone deciding whether to fund the next phase` | Money conversations: lead with proportion and risk |
| `/orientation-map scope <subsystem>` | One area has become the confusing part |
| `/orientation-map since <date>` | Re-baselining after a stretch of work |
| `/orientation-map check` | You only want to know whether the page can still be trusted |

A variant is written to its own file rather than overwriting the owner's map, and it names its reader on the page.
