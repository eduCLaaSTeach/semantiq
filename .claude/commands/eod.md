# EOD Report

Close out, review, or correct today's end-of-day status report. The gateway already writes the report as work happens, per `.claude/rules/eod-reporting.md`; this command is for the moments you want to drive it yourself.

Input: `[MODE]` - one of `close` (default), `who`, `team`, `date <date>`. A date is accepted in any unambiguous form and resolved to the day's file, `docs/eod/eod-date-<D><Month><YYYY>.md`.

## close (default)

Use at the end of the day, or any time you want the report brought up to date.

Process:

1. Ask whose EOD this is if the session has not already been told, and match the answer to the `## Team` table in `.claude/PROJECT-CONTEXT.md`. Never infer it from the Claude account, the GitHub login, or `git config`; all three are shared across the team.
2. Open today's report at `docs/eod/eod-date-<D><Month><YYYY>.md` (for example `eod-date-17August2026.md`), creating the folder and the file if missing.
3. Rebuild what the day holds from durable evidence only: the existing section, today's commits, the current branch, uncommitted changes, unpushed commits, open Pull Requests, the sprint checklist in `.claude/PROJECT-CONTEXT.md`, feedback logs written today, and this session.
4. Show the developer their current task rows and ask only about what is genuinely unresolved: a task with no status, a `Blocked` row with no reason, or work that appears in the evidence but not in the table.
5. Update the task rows, using only the closed status list in the rule.
6. Regenerate the four summary lines (Planned, Progress, Pending / Next, Blockers / Risks) from the task rows.
7. Offer to commit the report, stating the branch and file first. Leave it written and uncommitted if the developer declines.

Return: the report path, the developer, the task rows that changed status, anything left unresolved, and the commit status.

## who

Use when the report is being written for the wrong developer, or when a machine is shared.

Process:

1. Show which developer this session is currently logging for.
2. Switch to the named developer after confirming they are in the `## Team` table, or add their row after asking for their email, role code, working window, and start date if they are not.
3. Leave every existing section untouched. Switching who this session logs for never moves, merges, or deletes another developer's entry.

Return: the developer now in effect for this session, and a reminder that it holds for this session only.

## team

Use when you want the whole day, not just your own section.

Process:

1. Read today's report in full and present every developer's section in team-table order, with each one's role code.
2. Name any developer on the project that day whose section is still `No entry`.
3. Do not edit anything, and do not fill another developer's section on their behalf.

Return: the day's status across the team, and who has not reported.

## date

Use to read or correct an earlier day.

Process:

1. Resolve the requested date to its file name and open it, reporting that the file does not exist rather than creating one for a past date.
2. Present the requested day, and correct it only where the developer states the correction.
3. Mark a late correction plainly in the Note column rather than rewriting history silently.

Return: the day requested, what changed, and the commit status if anything was edited.

## Always

- Never invent progress. Record only what the evidence shows or the developer states, and mark anything reconstructed `(inferred)`.
- Never mark a task `Done` without the evidence any completion claim needs, per `.claude/rules/production-readiness.md`.
- Never put secrets, customer data, or production values in a report, per `.claude/rules/secret-handling.md`.
- Never record hours, session counts, or activity levels. Status only.
- If the file carries merge-conflict markers, resolve them by keeping every developer's section in team-table order and every task row from both sides, then say what was merged.
