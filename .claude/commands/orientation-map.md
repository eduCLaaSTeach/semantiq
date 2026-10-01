# Orientation Map

Build, refresh, or check the one-page Orientation Map, the project map an owner opens to see where the work stands. The gateway already regenerates it when a trigger fires, per `.claude/rules/orientation-map.md`; this command is for the moments you want to drive it yourself.

Input: `[MODE]` - one of `refresh` (default), `build`, `check`, `for <audience>`, `scope <area>`, `since <date>`.

The map path, who it is for, what counts as an accepted milestone, and which directories make up the configuration surface come from the Orientation Map section of `.claude/PROJECT-CONTEXT.md`. Ask for any that is still `<ASK_DEVELOPER>` and record the answer before writing the page.

## refresh (default)

Use after a milestone, or any time the page has stopped being true.

Process:

1. Read the sources fresh: `.claude/PROJECT-CONTEXT.md` (sprint checklist, scope and non-goals, documented exceptions), the working-memory log, the decision log, the knowledge base, recent EOD reports, the configuration surface, and the recent commit history.
2. Treat every number on the current page as unverified. Rebuild the page from what the sources say now; never adjust the existing figures in place.
3. Keep the existing structure, palette, and type. Only the facts change.
4. Stamp the footer with today's date and the commit the page was generated from.
5. Report the difference: what changed since the previous version, and explicitly whether anything previously listed as open is now resolved.

Return: the path, the trigger or reason for the refresh, the changed lines by section, the open items that closed, anything recorded as unknown, and the commit stamped in the footer.

## build

Use the first time, or when the page needs to be created from nothing.

Process:

1. Confirm the map path and its reader before writing anything, and record both in `.claude/PROJECT-CONTEXT.md`.
2. Read every source above, then answer Position, Surface, and Open in that order.
3. Produce one self-contained HTML file: no build step, no server, inline styles, both themes through `prefers-color-scheme`, readable on a phone, state encoded in form rather than in words alone.
4. Put the snapshot notice in a comment at the top of the file, and the date and commit in the footer.
5. Say in one paragraph what the page says, before the developer opens it.

Return: the path, the one-paragraph summary, the sources read, anything recorded as unknown, and what would settle each unknown.

## check

Use when you want to know whether the page can still be trusted, without rewriting it.

Process:

1. Read the footer's date and commit.
2. List the triggers that have fired since that commit: milestones accepted, sprint states changed, configuration files added or removed, blockers opened or resolved, exceptions recorded.
3. State whether the page is current, and name the sections a refresh would change.
4. Change nothing.

Return: current or stale, the commit distance, the triggers that fired since, and the sections affected.

## for

Use when the reader is not the owner. `for a new engineer joining this week`, `for someone deciding whether to fund the next phase`.

Process:

1. Keep the three questions and the page contract exactly as they are.
2. Re-pitch the content for the named reader: onboarding leads with Surface, a funding reader leads with proportion and risk.
3. Write it to its own file rather than overwriting the owner's map, and name the reader on the page.

Return: the path, the reader it was written for, and how it differs from the owner's map.

## scope

Use when one area has become the confusing part. `scope <subsystem>`.

Process:

1. Answer the same three questions about the named area only, and say on the page that the scope is partial.
2. Do not overwrite the whole-project map with it.

Return: the path, the area covered, and what was deliberately left out.

## since

Use to re-baseline after a stretch of work. `since <date>`.

Process:

1. Refresh as above, then add what changed since the given date, drawn from commits, closed sprint items, and EOD reports in that window.
2. Keep it to what moved. A list of every commit is not a re-baseline.

Return: the refresh result plus the changes since that date.

## Always

- Ground every claim in the repository. Never summarize from memory and never infer what is probably there.
- Say unknown rather than filling a gap, and say what would settle it.
- Say plainly where behavior looks like a defect but is deliberate. Those lines save the most time.
- Never let the page hold a fact that exists nowhere else. It is a rendering of the project's own records, not a record.
- Never put secrets, customer data, or production values in it, per `.claude/rules/secret-handling.md`. Name a configuration file and what it is for, never its values.
- Never claim the page is live. It is a snapshot, and it says so at the top and in the footer.
