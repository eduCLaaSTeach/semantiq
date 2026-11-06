# Orientation Map

Build, refresh, or check the one-page Orientation Map, the project map an owner opens to see where the work stands. The gateway already regenerates it when a trigger fires, per `.claude/rules/orientation-map.md`; this command is for the moments you want to drive it yourself.

Input: `[MODE]` - one of `refresh` (default), `build`, `check`, `for <audience>`, `scope <area>`, `since <date>`.

The map path, who it is for, what counts as an accepted milestone, and which directories make up the configuration surface come from the Orientation Map section of `.claude/PROJECT-CONTEXT.md`. Ask for any that is still `<ASK_DEVELOPER>` and record the answer before writing the page.

Run it when coming back after a break and the shape of the project has gone fuzzy, when there are too many configuration files, decisions, or documents to hold in your head, when somebody new needs orienting without reading the whole repository, or after a milestone, to re-baseline what done now means.

## refresh (default)

Use after a milestone, or any time the page has stopped being true.

Process:

1. Read the sources fresh: `.claude/PROJECT-CONTEXT.md` (scope and non-goals, the planning approvals, documented exceptions), the phase plan in `doc/phasedocument/`, the task register, task board, and event log in `doc/tasktracking/`, the current state, infrastructure readiness, requirement coverage, and decision and conflict register in `doc/corerepo/`, the working-memory log, the decision log, the knowledge base, recent EOD reports, the configuration surface, and the recent commit history.
2. Treat every number on the current page as unverified. Rebuild the page from what the sources say now; never adjust the existing figures in place.
3. Keep the existing structure, palette, and type. Only the facts change.
4. Stamp the footer with today's date and the commit the page was generated from.
5. Report the difference: what changed since the previous version, and explicitly whether anything previously listed as open is now resolved. The difference is what a refresh is for, not the new page, so lead the report with it.

Return: the path, the trigger or reason for the refresh, the changed lines by section, the open items that closed, anything recorded as unknown, and the commit stamped in the footer.

## build

Use the first time, or when the page needs to be created from nothing.

Process:

1. Confirm the map path and its reader before writing anything, and record both in `.claude/PROJECT-CONTEXT.md`.
2. Read every source above, then answer the three questions in this order:
   - Position: what the project actually is, in one honest paragraph, and where in the build it stands now. Carry proportion: roughly how far through, what is genuinely left, the milestone last cleared and the one being worked toward.
   - Surface: of everything in the repository, what the owner has to care about and what they can ignore. Name real files. Where most of something is noise, say so plainly and separate the signal from it, with a count. Explain why the part they care about is shaped that way when there was a deliberate reason.
   - Open: what is unresolved, unanswered, or blocking, split into the decisions only the owner can make and the work Claude can do.
3. Write for a busy owner, not an engineer. Lead with the answer, not the background. Use plain language, and define an unavoidable term in the same sentence. Use numbers where they carry proportion. No decoration and no hedging.
4. Produce one self-contained HTML file that opens by double-clicking:
   - No build step, no server, no package, and no runtime reads: no fetch, no data-loading script, no analytics, no external asset. Inline every style, and give any web font a real fallback stack.
   - Both themes, following the reader's system setting. Define the full palette on `:root`, redefine only the tokens inside `prefers-color-scheme: dark`, and set an explicit background on `body`.
   - Readable on a phone. Nothing scrolls sideways, and a wide table gets its own scroll container.
   - State encoded in form, not only in words, so a done thing and a blocked thing are distinguishable at a glance before either is read. Status color is never the only signal.
   - A structural device only where it encodes something true. Number things only when they are genuinely a sequence.
   - The project's own design identity. Where the project uses the gateway's design system, take the tokens, palette, and fonts from `.claude/skills/ui-ux-design/reference/design-tokens.md` without re-deriving them, and keep the page standalone rather than inside the app shell. Never a generic template.
5. Put the snapshot notice in a comment at the top of the file, and the date and commit in the footer. The commit is `HEAD` at generation time, never a commit that does not exist yet.
6. Save it to the recorded path, then say in one paragraph what the page says, before the developer opens it.

Return: the path, the one-paragraph summary, the sources read, anything recorded as unknown, and what would settle each unknown.

## check

Use when you want to know whether the page can still be trusted, without rewriting it.

Process:

1. Read the footer's date and commit.
2. List the triggers that have fired since that commit: milestones or phases accepted, the Phase Plan approved, a verified closeout completed, task statuses changed in `doc/tasktracking/`, configuration files added or removed, blockers opened or resolved, a non-goal, a documented exception, or an accepted `Critical` or `High` review finding recorded.
3. State whether the page is current, and name the sections a refresh would change. An ordinary commit that fired no trigger does not make the page stale, and neither does age alone: the refresh follows milestones, not the calendar.
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

1. Refresh as above, then add what changed since the given date, drawn from commits, task events in `doc/tasktracking/task-events.jsonl`, and EOD reports in that window.
2. Keep it to what moved. A list of every commit is not a re-baseline.

Return: the refresh result plus the changes since that date.

## Always

- Answer all three questions. Most project summaries answer only Position, which is why they are comforting and useless. Surface is the part that reduces confusion, and Open is the part that turns the page into a decision aid.
- Ground every claim in the repository. Never summarize from memory and never infer what is probably there. Without that you get something plausible and subtly wrong, which is the worst outcome for a page somebody intends to trust later.
- Say unknown rather than filling a gap, and say what would settle it.
- Say plainly where behavior looks like a defect but is deliberate. Those lines save the most time: "This field reads Not Configured on purpose, because software must not guess a legal retention period" saves an hour of investigation every time somebody new reaches that screen.
- Regenerate rather than edit. The page is cheap to rebuild and expensive to keep partially accurate.
- Never let the page hold a fact that exists nowhere else. It is a rendering of the project's own records, not a record.
- Never put secrets, customer data, or production values in it, per `.claude/rules/secret-handling.md`. Name a configuration file and what it is for, never its values.
- Never claim the page is live. It reads nothing at runtime and reloading it changes nothing. It is a snapshot, and it says so at the top and in the footer.
- Keep it distinct from the EOD report and the session handoff. An EOD report is one developer's day and a handoff is technical continuity for the next session. The map is the whole project's position, for somebody who was in neither.
