# EOD Reporting Rules

Claude writes each developer's end-of-day status report as the work happens, so nobody has to remember to write one before leaving. Claude opens today's report at the first substantive prompt of a session, records what the developer planned, and updates each task's status as it reaches an outcome.

Always on. Every project this gateway is copied into logs EOD status from the first prompt, without being asked and without asking permission. It does not override `.claude/rules/secret-handling.md`, `.claude/rules/schema-mcp.md`, `.claude/rules/production-readiness.md`, or the DDL restrictions.

## What This Is, And What It Is Not

An EOD report is a short status for whoever reads the day's progress: what was planned, what moved, what is left, what is blocked. Four lines and a task table per developer.

It is not the session handoff in `developer-handbook/prompts/DAY_HANDOFF_PROMPT.md`. A handoff is technical continuity for the next session: where the work stopped, the exact next step, validation state, branches, files touched. The two answer different questions and live in different folders. When a day ends with work in flight, write both.

It is not a time sheet, a productivity score, or an activity log. Record task status only. Never hours worked, session counts, prompt counts, idle time, or how long the developer sat at the machine.

## Where It Lives

- Reports go in `docs/eod/`, unless `.claude/PROJECT-CONTEXT.md` records a different EOD path, which wins.
- `docs/eod/eod-date-<D><Month><YYYY>.md` is the day's report, one file per calendar day, with one `## Developer Name` section per developer. The day carries no leading zero, the month is the full English name capitalized, and the year is four digits: `eod-date-17August2026.md`, `eod-date-5September2026.md`.
- The team table in `.claude/PROJECT-CONTEXT.md` is the roster: who can appear in a report, and in what order their sections are written. It is a project fact like any other, so it needs no file of its own.
- Create the folder and the day's file when they are missing. Creating them is not a change that needs approval.
- The date comes from the system clock. When `.claude/PROJECT-CONTEXT.md` records a reporting time zone and business-day boundary, use those instead.

## The Team Table

The `## Team` section of `.claude/PROJECT-CONTEXT.md` holds one row per developer: name, email, role, work start, work end, timezone, and the From and To dates of their period on the project. Role codes are `TL` Technical Lead, `PL` Project Lead, `LD` Lead Developer, `TD` Tech Developer, `TA` Tech Associate.

- Read only that section when EOD work needs it, not the whole file.
- Ask for the table during intake. When it is still `<ASK_DEVELOPER>` at the first session that needs it, ask for the current developer's row and fill it, then ask the developer to complete the rest of the team when they can. Do not invent a colleague's name, email, role, hours, or dates.
- Row order is section order in every daily report. Keep it stable so the same developer's section stays in the same place day to day.
- A developer's own name and email is attribution and is allowed there, per `.claude/rules/feedback-logging.md`. No other personal data belongs in it, and never a customer's.
- Work start, work end, and timezone are the developer's declared daily window, a static fact about their schedule. It is not a measurement of any day, and it never becomes one.

### Who Appears In A Day's Report

A developer gets a pre-seeded section in a report only when that report's date falls inside their From and To window. An empty To means they are still on the project.

- Someone who joins mid-project starts appearing on their From date and not before, so earlier reports are not rewritten to imply they were there.
- Someone who leaves, or moves to another project, gets their To date filled and stops appearing in new reports the next day. Every report they were part of stays exactly as it was, because those days happened.
- Do not delete a departed developer's row. The row is what keeps their past sections attributable, and removing it makes old reports unreadable.
- Ask for the To date rather than inferring it from silence. A quiet week is not a departure.
- Someone who returns to the project has their existing row reopened by clearing To, so one person keeps one row and the typed name still resolves to exactly one developer.

## Whose Session Is This

Ask. Do not work it out.

The Claude account is shared across the team, so the signed-in account, its email, and the GitHub login attached to it are the same for everyone and identify nobody. `git config` is no better: it is a plain text file, often set up identically across a team, and any user can edit it in seconds.

At the first substantive prompt, ask one short question: "Whose EOD am I logging today?" Then:

1. Match the answer to a name in the team table and use that row's email, role, and section position.
2. When the name is not in the table, ask for their email, role code, working window with its timezone, and their first day on the project, and add their row with To left empty. Add only the person who is present. Nobody's row is ever added on their behalf.
3. When the answer is ambiguous between two rows, ask which one rather than picking.

Hold the answer for this session only. Never write it to a settings file, a global config, or anywhere outside the team table and the day's report. A new session asks again.

This is attribution, and it is never identity or authority. A typed name cannot verify an override, satisfy `.claude/rules/owner-override.md`, unlock a rule, or stand in for any approval. Anyone can type any name, which is exactly why it is good enough to label a status report and worthless for anything else.

## Working Across Sessions

There is no memory between sessions, so the file on disk is the memory. A developer running three sessions and two `/clear`s still gets one continuous day, because each session opens the existing `docs/eod/eod-date-<D><Month><YYYY>.md` and appends to their own section rather than starting over.

Reconstruct what the day already holds from durable evidence only, the same sources the handoff prompts use: today's section in the report, today's commits, the current branch, uncommitted changes, unpushed commits, open Pull Requests, the sprint checklist in `.claude/PROJECT-CONTEXT.md`, feedback logs written today, and the current session. Never invent progress a session cannot see, and never restate an earlier session's entry as if this session verified it.

## When Claude Writes

Three moments, all as a side effect of the real work:

- **Session start.** At the first substantive prompt, open or create the day's file and ask both questions in one short message: whose EOD this is, and what is on their list today. It never blocks, and the developer's actual request proceeds whether or not they answer. If they name themselves but state no plan, fill Planned from the tasks they actually start and mark it `(inferred)`. If they answer nothing at all, do the work, ask once more at the next natural pause, and write nothing under a guessed name.
- **Task outcome.** When a task reaches an outcome, update that task's row. Not on every prompt, which would thrash the file and burn context for nothing. Only when a status genuinely changes.
- **Close.** At `/eod`, at a verified closeout, or when the developer signals the day is done, regenerate the four summary lines from the task rows and offer to commit.

Never ask permission to write the report and never announce it as a question. Writing to the EOD path is pre-approved in `.claude/settings.json`. Mentioning in one clause that the entry was updated is fine; waiting on approval is not.

## The Report Shape

The day's file carries a section for every developer whose From and To window covers that date, pre-seeded in team-table order when the file is created, so no session ever appends at the end of the file. Each developer edits only their own section. The heading carries the role code from the team table, so a reader knows whose status they are looking at.

```md
# EOD 17 August 2026

## <Developer Name> (LD)

Planned: Realign Interest Generation, Leads Qualification, and DBD Auto Assignment; work on Sales Handoff; conduct unit testing; and review LGN, SLS, and CSM workflow documents.
Progress: Sales Handoff Prompt and Name Card Capture unit testing were completed. CRM realignment remains in progress; Contract Management is in review, Customer Engagement and Communication is for review, and Renewal and Retention is in progress.
Pending / Next: Continue CRM realignment and the pending workflow document reviews.
Blockers / Risks: None.

### Tasks
| Task | Status | Note |
| --- | --- | --- |
| Sales Handoff Prompt | Done | unit tested |
| Name Card Capture | Done | unit tested |
| CRM realignment | In Progress | |
| Contract Management | In Review | |
| Renewal and Retention | Blocked | waiting on the SLS workflow document |

## <Next Developer, in team-table order> (TD)

Planned: No entry.
Progress: No entry.
Pending / Next: No entry.
Blockers / Risks: No entry.
```

- The task table is what Claude updates through the day. The four summary lines are derived from it and regenerated at close, never hand-patched task by task.
- A section nobody touched keeps its `No entry` placeholders. That is information for whoever reads the day, not noise, so do not delete it at close.
- Keep task names short and in the developer's own words. A task is a unit of work they would name in a stand-up, not a file, a commit, or a tool call.
- The Note column carries the one thing a reader needs: what was verified, what is waiting, who is reviewing. Leave it empty rather than padding it.

## Status Is A Closed List

Every task row carries exactly one of these. Do not invent a status, and do not write one as free text:

| Status | Means |
| --- | --- |
| `Not Started` | Planned today, not begun |
| `In Progress` | Actively being worked, not finished |
| `Partially Done` | Some of it is finished and usable; the rest is not started or in progress |
| `For Review` | Finished and waiting to be picked up by a reviewer |
| `In Review` | A reviewer has it now |
| `Blocked` | Cannot proceed, with the reason always stated in the Note |
| `Done` | Finished, with the same evidence any completion claim needs |
| `Dropped` | Deliberately stopped or descoped, with the reason in the Note |

The list is closed so a week of reports can be read and summarized consistently. A project that genuinely needs another status adds it here, in this table, rather than writing prose into a row.

`Done` follows `.claude/rules/production-readiness.md` like any other completion claim: validation ran, or the limitation is recorded. A task that was written but never validated is `Partially Done` or `In Progress`, never `Done`. `Blocked` without a reason is an incomplete row.

## Never Invent The Day

- Record only what the session can see or the developer stated. Do not infer that a task finished because a file changed, and do not promote a status the developer did not confirm.
- Mark anything reconstructed rather than observed as `(inferred)` so a reader can tell it apart.
- When the developer corrects an entry, take the correction and move on. Their account of their own day wins over anything Claude reconstructed.
- A report that shows less than the day held is fine. One that overstates it is a defect, because someone plans around it.

## Secrets And Sensitive Data

These files are committed and read by people beyond the developer who wrote them.

- Never put secrets, tokens, credentials, connection strings, private keys, `.env` values, decoded claims, customer records, or production row data in a report, per `.claude/rules/secret-handling.md`. Use placeholders such as `<TOKEN>` and `<DATABASE_NAME>`.
- Name the work, not the data it touched. "Fixed the duplicate contact merge" belongs in a report; a customer name, an account number, or a row from the table does not.
- The only personal data permitted is the team table's own developer names, emails, roles, declared working windows, and project dates.

## Committing

Write all day, commit at close. The file updates on disk as work happens, with no commit per update.

- At `/eod` or a verified closeout, offer to commit the day's report with a message such as `chore(eod): 17 August 2026`. Committing is a normal Git action, pre-authorized per `.claude/rules/git-branching-release.md`; state the branch and the files first.
- The repository's commit-identity requirements still apply and are checked before the commit, exactly as for any other commit.
- Never commit a report mid-task just to save it, and never push it to a branch the developer did not name.
- If the developer declines, leave the file written and uncommitted, and say so.

## Merge Conflicts

Two developers writing the same day's file on two branches is expected. The pre-seeded sections in fixed team-table order are what keep those edits apart, since each developer only ever changes lines inside their own section. When a conflict does reach the file, resolve it by keeping both developers' sections in team-table order and both sets of task rows. Never resolve an EOD conflict by dropping somebody's entry.

## Final Reporting

When an EOD entry was written or updated, note it in one line in the final summary: the report path, the session's developer, and any task whose status changed. Do not reproduce the whole report, and do not repeat the day's task table back to the developer who just lived it.

Final rule: if it is unclear whose session this is, whether a task is actually done, or why something is blocked, do not guess. Ask, or record the status one level lower and mark what is missing.
