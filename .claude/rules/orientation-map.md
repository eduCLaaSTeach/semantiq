# Orientation Map Rules

Claude keeps a one-page Orientation Map for the person who owns the project: where the work stands, what they actually have to care about, and what is unresolved. It is regenerated as a side effect of the work, so nobody has to remember to ask for one.

Always on. Every project this gateway is copied into keeps a map once the developer has confirmed where it lives and who reads it. It does not override `.claude/rules/secret-handling.md`, `.claude/rules/database-schema.md`, `.claude/rules/production-readiness.md`, or the DDL restrictions.

## What This Is, And What It Is Not

An Orientation Map is one page an owner opens after a break, or hands to somebody who needs the shape of the project without reading the repository. It answers three questions in a fixed order: Position, Surface, Open. Some teams call it the project map. It is the same document.

It is not a dashboard and not a live view. It reads nothing at runtime, calls nothing, and reloading it changes not one character on the page. Every fact in it was written into the file at the moment it was generated. This surprises people, so the file says so itself: a comment at the top, and the date and commit in the footer. A page that claims to be current and quietly is not is worse than no page.

It is also not a fifth place where project facts live:

| Store | Holds | Written |
| --- | --- | --- |
| `.claude/PROJECT-CONTEXT.md` | confirmed intake facts | when the developer confirms one |
| The working-memory log | in-flight progress, decisions, open questions | as work proceeds |
| The knowledge base | verified, durable project knowledge | after validation and explicit approval |
| `docs/eod/` | each developer's daily status | as tasks reach an outcome |
| The Orientation Map | nothing of its own | rebuilt from the four above, plus the repository |

The map owns no fact. Every line in it is read from one of those stores or from the repository when the page is generated, and that is what stops it becoming a second, competing account of the project. A fact that exists only on the map is a defect: put it in the store it belongs to, then regenerate.

It is not the EOD report in `.claude/rules/eod-reporting.md` and not the session handoff in `developer-handbook/prompts/DAY_HANDOFF_PROMPT.md`. An EOD report is one developer's day. A handoff is technical continuity for the next session. The map is the whole project's position, written for somebody who was in neither.

## The Three Questions, In This Order

The order is load-bearing. Most project summaries answer only the first, which is why they are comforting and useless.

- Position: what this thing actually is, in one honest paragraph, and where in the build it stands right now. Carry proportion: roughly how far through, what is genuinely left, the milestone last cleared and the one being worked toward.
- Surface: of everything in the repository, what the owner has to care about and what they can ignore. Be specific and name real files. Where most of something is noise, say so plainly and separate the signal from it, with a count. Explain why the thing they care about is shaped the way it is, when there was a deliberate reason.
- Open: what is unresolved, unanswered, or blocking, and which of those are decisions only the owner can make rather than work Claude can do. Keep those two apart, because that split is the reason the page exists.

Surface is the part that actually reduces confusion. Open is the part that turns the page into a decision aid. A map that answers only Position is a status report with better typography.

## Ground Every Claim In The Repository

- Read the sources before writing a line: `.claude/PROJECT-CONTEXT.md` (the sprint checklist, scope and non-goals, documented exceptions), the working-memory log, the decision log, the knowledge base, recent EOD reports, the project's configuration surface, and the recent commit history.
- Never summarize from memory and never infer what is probably there. Both produce something plausible and subtly wrong, which is the worst possible outcome for a page somebody intends to trust later.
- Unknown is an answer. Where something is genuinely unknown, say it is unknown and say what would settle it. Never fill the gap with a reasonable-sounding guess.
- Count what is countable. Numbers carry proportion where prose cannot: how many files sit in the configuration surface, how many of them the owner ever touches, how many sprint items are closed.
- Say plainly where behavior looks like a defect but is deliberate. In practice these are the highest-value lines on the page, because they are what somebody would otherwise spend an hour investigating. "This field reads Not Configured on purpose, because software must not guess a legal retention period" saves that hour every time a new reader hits that screen.

## Write It For The Owner

- Lead with the answer, not the background.
- Plain language. Where a term is unavoidable, define it in the same sentence.
- Short sentences, main points first, no decoration and no hedging.
- Speak to the owner's decisions rather than the team's implementation. An engineer's version, a funding version, or a single-subsystem version is a variant, asked for by name.

## Rebuild, Never Edit In Place

- Regenerate the whole page from the repository every time. Do not adjust the numbers on the page that is already there.
- Treat what the current page says as unverified. An inherited error survives every regeneration that does not go back to the source, and it reads as more settled each time it is carried forward.
- Keep the existing structure, palette, and type across a refresh. Only the facts change.
- After a refresh, say exactly what changed since the previous version, and state explicitly whether anything previously listed as open is now resolved. The value of a refresh is the difference, not the page.

## When Claude Regenerates It

Regenerate in the same change unit as the trigger, without being asked and without asking permission. Writing to the confirmed map path is pre-approved in `.claude/settings.json`.

| Trigger | Why the map is wrong without it |
| --- | --- |
| A milestone or sprint item is formally accepted, or a verified closeout completes | Position is stale, which is the whole point of the page |
| A sprint task changes state in `.claude/PROJECT-CONTEXT.md` | Same |
| A file is added to or removed from the project's configuration surface | The signal-against-noise count in Surface is wrong |
| An open item is resolved, or a new blocker appears | Open is the section that drives decisions |
| A declared non-goal, a documented exception, or an accepted `Critical` or `High` review finding is recorded | The owner is carrying a decision the map does not show |

Do not regenerate on an ordinary commit. A map rewritten on every push is noise in the history and stops being a signal that anything moved.

Tie the refresh to a milestone rather than to a calendar. A weekly rebuild produces a page nobody trusts, because most weeks nothing on it moved. A rebuild at each acceptance produces a page that is accurate at the moment somebody actually opens it, which is after a break, and a break usually follows a milestone.

Build the map where a trigger fires and none exists yet. Where the footer's commit is behind `HEAD` and a trigger has fired since, regenerate it rather than leaving it stale and mentioning it in passing.

## The Page Itself

One standalone HTML file that opens by double-clicking. These constraints are not stylistic preferences. Each one is what keeps the page usable in the situation it gets opened in.

- Self-contained: one file, no build step, no server, no package. Inline every style. Any web font carries a real fallback stack, because the page is often opened offline or passed on as a file.
- No runtime reads of any kind: no fetch, no script that loads data, no analytics, no external asset. The page is text that was true at a moment, and it must not be able to disagree with itself.
- Both themes, following the reader's system setting. Define the full palette on `:root`, redefine only the tokens inside `prefers-color-scheme: dark`, and set an explicit background on `body`.
- Readable on a phone. Nothing scrolls sideways, and a wide table gets its own scroll container.
- Encode state in form, not only in words, so a done thing and a blocked thing are distinguishable at a glance before either is read. Status color is never the only signal, per `.claude/rules/ui-ux-quality.md`.
- Use a structural device only where it encodes something true. Number things only when they are genuinely a sequence.
- A comment at the top of the file states that this is a snapshot rather than a live view. The footer carries the generation date and the commit the page was generated from, which is `HEAD` at generation time and therefore the parent of the commit that lands it. Never stamp a commit that does not exist yet.

Where the project uses this gateway's design system, the map takes the approved tokens, palette, and fonts from `.claude/skills/ui-ux-design/reference/design-tokens.md` and does not re-derive them, per `.claude/rules/ui-ux-quality.md`. It does not take the application shell: like the auth archetype it is a standalone page, sharing only the tokens, the fonts, and the brand mark. A project with its own established identity uses that identity instead. A project with neither gets a plain, legible page rather than a generic template.

## Secrets And Sensitive Data

The map names real files and real open items, and it is committed where anyone with repository access reads it.

- Never put secrets, tokens, credentials, connection strings, private keys, `.env` values, decoded claims, customer records, or production row data in it, per `.claude/rules/secret-handling.md`. Use placeholders such as `<TOKEN>` and `<DATABASE_NAME>`.
- Name a configuration file and what it is for, never its values.
- Name the work an open item concerns, not the data it touched.
- The only personal data permitted is the names and roles already recorded in the `## Team` table of `.claude/PROJECT-CONTEXT.md`.

## Committing

- The map travels in the same change unit as the trigger that made it stale, so the regenerated page and the change it describes land together.
- Committing is a normal Git action, pre-authorized per `.claude/rules/git-branching-release.md`. State the branch and the file, then commit.
- Never push a map to a branch the developer did not name.

## Final Reporting

When a map was built or refreshed, report: the path; whether it was a build or a refresh, and which trigger fired; what changed since the previous version, and explicitly whether anything previously open is now resolved; which sources were read; anything recorded as unknown and what would settle it; confirmation that no secrets, customer data, or production values were placed in it.

Final rule: if the map path, who the map is for, what counts as a milestone in this project, or which directories make up the configuration surface is unclear, do not guess. Ask and record the confirmed values in `.claude/PROJECT-CONTEXT.md` first.
