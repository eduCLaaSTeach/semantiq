# Session Handoff

Write a handoff file so work survives a session boundary, resume from one, or find the right one. Use it mid-session to continue in a fresh session, at the end of the day to continue next morning, or when another developer will finish the work on their own device. The next session points at that one file and picks up without the developer re-explaining anything.

Input: `[MODE]` - one of `mid` (default), `eod`, `dev`, `resume <handoff file>`, `find`.

`mid` and `eod` assume the work comes back to the same machine, where uncommitted work still sits on disk. `dev` does not: the work moves to another person's device, so the handoff counts only what was committed and pushed, and it tells the next developer how to reach the same starting point.

All three assume the Claude account stays the same. When the account itself changes (the usage allowance ran out, or the account is being rotated), use `/account-handover` instead: the switch takes every open session with it at once, so that command captures each chat being transferred and ties them together with one index file.

## Where Handoffs Live And How They Are Named

Many people work in parallel and each may leave several handoffs, so a single reused "handoff" or "handover" filename would clobber earlier ones. Every handoff is its own file in one shared folder.

- Folder: a dedicated `handoffs/` folder, under the repo's working-memory location or wherever the team keeps handoffs. Ask the developer to confirm the path if none is set. All handoff files live there together.
- Filename: `<timestamp>_<short-topic>.md`. The timestamp is `YYYY-MM-DD-HHMM` from the system clock. The topic is a very short kebab-case slug, 2 to 3 words max. For example `handoffs/2026-07-21-1530_rounding-fix.md`.
- The timestamp keeps files sorted and unique across people and sessions. The short topic tells a reader what the handoff covers at a glance without turning into a sentence.
- Write a new file every time. Never overwrite an existing handoff file.
- Resume only from an exact file the developer names. There is no "latest" shortcut.

## What Claude Can And Cannot See

Claude has no memory of past chat sessions. Rebuild the state only from what left a durable trace in the repo:

- the working-memory log, per `.claude/rules/knowledge-base.md`, which in a project that uses the solution development kit is `doc/corerepo/01_CURRENT_STATE.md` and `doc/corerepo/11_DECISION_CONFLICT_REGISTER.md`
- Git activity: commits, the current branch, uncommitted changes, stashes, unpushed commits
- open Pull Requests and their status, and the next promotion step
- the task register, task board, and event log in `doc/tasktracking/`
- feedback logs in `.claude/feedback-logs/`
- the current session

Anything done in an earlier session that left no trace (a decision only spoken in chat, work never committed) will not appear unless the developer adds it. Flag what could not be reconstructed and ask the developer to fill the gaps rather than guessing.

## mid (default)

Use when the developer is not ending the day but wants to stop this session and pick the work up in a fresh one on the same device. Scope: same user, same device. It covers the current piece of work only, so the next session can proceed from the file alone.

Process:

1. Confirm the `handoffs/` folder path, asking the developer if none is set.
2. Rebuild the state from durable evidence only, and invent or assume nothing:
   - the working-memory log
   - what was done in this session
   - uncommitted changes (`git status`), the current branch, stashes, and any unpushed commits against the remote
   - open Pull Requests the developer owns and the next promotion step, using `gh` only if it is available
   - the task register, task board, and event log in `doc/tasktracking/`
3. Write a new file `handoffs/<timestamp>_<short-topic>.md` with these sections:
   - Task and goal: the Task ID, its status, and its current gate when the work is tracked; what is being built or fixed, and why.
   - Current state: what is done and verified against what is done but unverified.
   - In progress: where it stopped and the exact next step to take.
   - Key decisions and constraints made this session, so they are not relitigated.
   - Files and code touched: paths and symbols, not large blocks.
   - Validation state: what passed, what is failing, what is unrun, with the exact commands.
   - Open questions awaiting the developer's input.
   - Resume here: the single first action for the next session.
4. Mark nothing done or verified without evidence. Mark a thin item `unverified - confirm`.
5. Flag anything that could not be reconstructed and ask the developer to fill it in.
6. Update the working-memory log's Focus and Next.
7. Show the exact file path written and offer to commit and push it, stating the branch and command first.

Return: the handoff file path, the gaps flagged for the developer, and the commit status.

## eod

Use before leaving for the day. Scope: usually the same device next morning, and the resume works whether the same developer or a teammate continues. Anything only on this machine (uncommitted or unpushed) reaches a teammate only if it is pushed first.

This is not the EOD status report. That report is written automatically all day into `doc/eod/` and closed with `/eod`, per `.claude/rules/eod-reporting.md`: four status lines over a task table, for whoever reads the day's progress. This handoff is the technical detail that report deliberately leaves out, written for the next session rather than for a reader. A day that ends with work in flight wants both.

Process:

1. Confirm the `handoffs/` folder path, asking the developer if none is set.
2. Rebuild the whole day from durable evidence only, and invent or assume nothing:
   - the working-memory log
   - today's Git activity: commits since the start of today, the current branch, uncommitted changes (`git status`), stashes, and any unpushed or ahead commits against the remote
   - open Pull Requests the developer owns, their review and check status, and the next promotion step, using `gh` only if it is available
   - the task register, task board, and event log in `doc/tasktracking/`
   - feedback logs written today in `.claude/feedback-logs/`
   - what was done in this session
3. Write a new file `handoffs/<timestamp>_<short-topic>.md` as a dated checklist with these sections:
   - Done today: each item with its evidence (commit, PR, files, validation result).
   - In progress: where it stopped and the exact next step.
   - Pending / not started: remaining tracked tasks by Task ID, and untouched planned work.
   - Blockers: and who or what is needed to unblock them.
   - Open questions and decisions awaiting input.
   - Validation state: what passed, what is failing, what is unrun.
   - Branches and Pull Requests: unpushed work, open PRs, the next promotion step.
   - Risks, follow-ups, and manual steps.
   - Resume here tomorrow: the single first action for the next person.
4. Mark nothing done without evidence. Mark a thin item `unverified - confirm with <name>`.
5. Give a tracked task the status `doc/tasktracking/` records. Finished code waiting for user testing is `Implementation Complete`, never `Accepted`.
6. Flag anything that could not be reconstructed and ask the developer to fill it in.
7. Update the working-memory log's Focus and Next.
8. Show the exact file path written and offer to commit and push it so the team can point to it, stating the branch and command first.

Return: the handoff file path, the gaps flagged for the developer, and the commit status.

## dev

Use when the developer is stopping and a different developer will finish the work on their own machine. Scope: one user and device to another. Nothing on this disk travels with them, so the rule flips: commit and push first, then hand off only what is on the remote. The handoff also tells the next developer how to reach this exact starting point, since they start from a clean checkout.

Process:

1. Confirm the `handoffs/` folder path, asking the developer if none is set.
2. Help the developer get everything onto the remote first. Check uncommitted changes (`git status`), the current branch, stashes, and any unpushed commits against the remote, and flag each one as "not on the remote yet" so nothing is stranded on this device.
3. Rebuild the state from durable evidence only, based on what is committed and pushed, and invent or assume nothing:
   - the working-memory log
   - what was done in this session
   - the Git state from step 2
   - open Pull Requests the developer owns and the next promotion step, using `gh` only if it is available
   - the task register, task board, and event log in `doc/tasktracking/`
4. Write a new file `handoffs/<timestamp>_<short-topic>.md` with these sections, so the next developer can pick up from the file and the remote alone, without this developer or this machine:
   - Task and goal: the Task ID, its status, and its current gate when the work is tracked; what is being built or fixed, and why.
   - How to get to my starting point: the branch to check out or pull, its remote, the exact `HEAD` commit, and any local setup the next developer must do on their device: install and build commands, environment variables they must set (as placeholders), and configuration that is not in the repo.
   - Current state: what is done and verified against what is done but unverified.
   - In progress: where it stopped and the exact next step to take.
   - Key decisions and constraints made this session, so they are not relitigated.
   - Files and code touched: paths and symbols, not large blocks.
   - Validation state: what passed, what is failing, what is unrun, with the exact commands so they can re-run them on their device.
   - Open questions to settle between the two developers.
   - Resume here: the single first action for the next developer.
5. Mark nothing done or verified without evidence. Mark a thin item `unverified - confirm`.
6. Treat any uncommitted or unpushed work as not handed off until it is on the remote, and call it out explicitly in the file.
7. Flag anything that could not be reconstructed and ask the developer to fill it in.
8. Keep the file free of secrets, tokens, connection strings, `.env` values, and production row data, using placeholders only. It goes to another person, so it must be safe to share.
9. Help the developer commit and push both the work and the handoff file, stating the branch and command first.
10. Update the working-memory log's Focus and Next.
11. Show the exact file path written.

Return: the handoff file path, the branch and `HEAD` commit handed off, anything still not on the remote, the gaps flagged, and the push status.

## resume

Use at the start of a fresh session, next morning, or on the next developer's device, to pick up from a handoff. The developer names the exact file, for example `/session-handoff resume handoffs/2026-07-21-1530_rounding-fix.md`. When no file is named, run `find` instead of guessing; never pick the newest file on the developer's behalf.

Process:

1. Read the named handoff file. If it does not exist, say so and offer `find`.
2. Tell the developer where the work left off.
3. When the file carries a "How to get to my starting point" section, it came from another developer. Walk the developer through it first, so their device matches: the branch to pull, the setup, the environment placeholders. Ask when their local setup does not match.
4. Start from the file's resume item: "Resume here", or "Resume here tomorrow" for an end-of-day handoff.
5. Assume nothing that is not in the file or the repo. Ask the developer when something is unclear.

Return: where the work left off, the setup steps still outstanding (for a developer-to-developer handoff), and the first action being taken.

## find

Use when the developer is not sure which handoff to resume. The `handoffs/` folder holds many files from different people and sessions, so a resume always names one exact file.

Process:

1. List the files in the `handoffs/` folder.
2. For each, give the date, the topic, and a one-line summary, so the developer can choose by timestamp, topic, or author.
3. Do not open, act on, or resume any of them yet.

Return: the list, and a reminder to run `/session-handoff resume <exact file>` with the chosen file.

## Notes

- A handoff is working-memory continuity, not verified knowledge. It does not go through the knowledge-base approval gate, which still applies to durable write-ups (see `/verified-closeout`).
- A session handoff is not the project handover either. In a project that uses the solution development kit, `doc/corerepo/13_HANDOVER.md` is the project-level handover, kept current by `core-repo-maintainer` when a handover is requested or a task or phase closes, and it says where the whole project stands. A handoff here says where one session stopped. Neither replaces the other.
- A handoff is not the EOD status report and does not replace it. The report is written automatically into `doc/eod/` as the work happens and is closed with `/eod`, per `.claude/rules/eod-reporting.md`. A handoff is written when the developer asks, and carries the resume detail a status report has no room for.
- The file is meant to be shared, so it must stay secret-free. Never put secrets, tokens, connection strings, or production row data in it; keep placeholders such as `<APP_URL>`, `<DATABASE_NAME>`, and `<TOKEN>`, per `.claude/rules/secret-handling.md`.
- Committing and pushing the handoff is a normal Git action and is pre-authorized. State the branch and command first, and push it wherever the team agrees handoffs live.
- The handoff is only as complete as the trace the session left behind. Remind the developer to keep the working-memory log current (Focus and Progress, decisions, open questions).
