# Session Handoff Prompts

Copy-ready prompts you send to Claude when you want the work to survive a session boundary. Claude reviews the durable trace and writes a timestamped handoff file into a shared `handoffs/` folder, so the next session can point to that one file and pick up where you left off without you re-explaining anything. Three flows, each a handoff prompt paired with its resume prompt:

1. Mid-session handoff, then continue in a fresh session on the same device (scoped to the piece of work in flight).
2. End-of-day handoff, then continue next morning in the same or a new session (rebuilds the whole day).
3. Developer-to-developer handoff, then another developer continues on their own device (commit and push first, so nothing is left behind).

Flows 1 and 2 assume you (or the next session) come back to the same machine, where uncommitted work still sits on disk. Flow 3 does not: the work is moving to another person's device, so the handoff only counts what was committed and pushed, and it tells the next developer how to reach the same starting point.

All three assume the Claude account stays the same. When the account itself changes (the usage allowance ran out, or the account is being rotated), use `ACCOUNT_HANDOVER_PROMPT.md`: the switch takes every open session with it at once, so it captures each chat you are transferring and ties them together with one index file.

## Where handoffs live and how they are named

Many people work in parallel and each may leave several handoffs, so a single reused "handoff" or "handover" filename would clobber earlier ones. Every handoff is its own file in one shared folder:

- Folder: a dedicated `handoffs/` folder (under the repo's working-memory location, or wherever your team keeps handoffs; ask Claude to confirm the path if none is set). All handoff files live here together.
- Filename: `<timestamp>_<short-topic>.md`, where the timestamp is `YYYY-MM-DD-HHMM` from the system clock and the topic is a very short kebab-case slug, 2 to 3 words max. For example `handoffs/2026-07-21-1530_rounding-fix.md`.
- The timestamp keeps files sorted and unique across people and sessions; the very short topic tells a reader what the handoff covers at a glance without turning into a sentence.
- Claude writes a new file every time and never overwrites an existing one. When you resume, you name the exact file: there is no "latest" shortcut.

## What Claude can and cannot see

Claude has no memory of past chat sessions. It rebuilds the state only from what left a durable trace in the repo:

- the working-memory log (per `.claude/rules/knowledge-base.md`)
- Git activity: commits, the current branch, uncommitted changes, stashes, unpushed commits
- open Pull Requests and their status, and the next promotion step
- the sprint task checklist in `.claude/PROJECT-CONTEXT.md`
- feedback logs in `.claude/feedback-logs/`
- the current session

Anything you did in an earlier session that left no trace (a decision only spoken in chat, work never committed) will not appear unless you add it. Claude flags what it could not reconstruct and asks you to fill the gaps rather than guessing, so the more you kept in the working-memory log, the more complete the handoff.

## 1. Mid-session handoff (continue in a fresh session)

Scope: same user, same device. You come back to this machine, where any uncommitted work still sits on disk.

Use this when you are not ending the day but want to stop this session and pick the work up in a fresh one without re-explaining it. It is scoped to the current piece of work, so the next session can proceed from the file alone.

### Hand off (copy and send when you want to switch sessions)

```text
I want to continue this work in a new session without explaining it again. Write a handoff document to a new file handoffs/<timestamp>_<short-topic>.md (timestamp YYYY-MM-DD-HHMM from the system clock, topic a very short kebab-case slug, 2 to 3 words max) in the shared handoffs folder (ask me where it is if none is set). Do not overwrite an existing handoff file. A fresh session should be able to pick up from this file alone. Do not invent or assume anything; rebuild only from durable evidence:
- the working-memory log (per .claude/rules/knowledge-base.md)
- what we did in this session
- uncommitted changes (git status), the current branch, stashes, and any unpushed commits vs the remote
- open Pull Requests I own and the next promotion step (use gh only if it is available)
- the sprint task checklist in .claude/PROJECT-CONTEXT.md

Include these sections:
- Task and goal (what we are building or fixing, and why)
- Current state (what is done and verified vs done but unverified)
- In progress (where it stopped and the exact next step to take)
- Key decisions and constraints made this session (so they are not relitigated)
- Files and code touched (paths and symbols, not large blocks)
- Validation state (what passed, what is failing, what is unrun, with the exact commands)
- Open questions awaiting my input
- Resume here (the single first action for the next session)

Rules:
- Do not mark anything done or verified without evidence. Mark thin items "unverified - confirm".
- Flag anything you could not reconstruct and ask me to fill it in.
- No secrets, tokens, connection strings, or production row data; placeholders only.
- After writing it, update the working-memory log's Focus/Next, then show me the exact file path you wrote and offer to commit and push it.
```

### Continue in a fresh session (resume)

```text
Read the handoff file handoffs/<exact-filename>.md and tell me where we left off. Start from the "Resume here" item. Do not assume anything not in the file or the repo; ask me if something is unclear.
```

## 2. End-of-day handoff (continue next morning)

Scope: usually same device, next morning; the resume works whether you or a teammate continues. Anything only on this machine (uncommitted or unpushed) reaches a teammate only if you push it first.

This is not the EOD status report. Claude already writes that one on its own, all day, into `docs/eod/` (see `.claude/rules/eod-reporting.md`): four status lines over a task table, for whoever reads the day's progress. This handoff is the technical detail that report deliberately leaves out, written for the next session rather than for a reader. End a day with work in flight and you want both.

Use this before leaving for the day. Claude rebuilds the whole day and writes a dated checklist so the next morning anyone can point to that one file and pick up, in the same session or a new one.

### Hand off (copy and send at end of day)

```text
End of day handoff. Go through everything from today and write a handoff checklist so the next person can continue tomorrow.

You cannot read my past chat sessions, so rebuild the day only from durable evidence, and do not invent or assume anything:
- the working-memory log (per .claude/rules/knowledge-base.md)
- today's Git activity: commits since the start of today, the current branch, uncommitted changes (git status), stashes, and any unpushed or ahead commits vs the remote
- open Pull Requests I own, their review/check status, and the next promotion step (use gh only if it is available)
- the sprint task checklist in .claude/PROJECT-CONTEXT.md
- feedback logs written today in .claude/feedback-logs/
- what we did in this session

Write the handoff to a new file handoffs/<timestamp>_<short-topic>.md (timestamp YYYY-MM-DD-HHMM from the system clock, topic a very short kebab-case slug, 2 to 3 words max) in the shared handoffs folder (ask me where it is if none is set); do not overwrite an existing handoff file. Use these sections:
- Done today (each item with evidence: commit, PR, files, validation result)
- In progress (where it stopped and the exact next step)
- Pending / not started (remaining sprint items and untouched planned work)
- Blockers (and who or what is needed to unblock)
- Open questions and decisions awaiting input
- Validation state (what passed, what is failing, what is unrun)
- Branches and Pull Requests (unpushed work, open PRs, next promotion step)
- Risks, follow-ups, and manual steps
- Resume here tomorrow (the single first action for the next person)

Rules:
- Do not mark anything done without evidence. Mark thin items "unverified - confirm with <name>".
- Flag anything you could not reconstruct and ask me to fill it in.
- No secrets, tokens, connection strings, or production row data in the file; placeholders only.
- After writing it, update the working-memory log's Focus/Next, then show me the exact file path you wrote and offer to commit and push it so the team can point to it.
```

### Next morning, same or new session (resume)

```text
Read the handoff file handoffs/<exact-filename>.md and tell me where we left off. Start from the "Resume here tomorrow" item. Do not assume anything not in the file or the repo; ask me if something is unclear.
```

## 3. Developer-to-developer handoff (another developer continues on their device)

Scope: one user/device to another user/device. Nothing on your disk travels with them, so only committed and pushed work is handed off.

Use this when you are stopping and a different developer will finish the work on their own machine. Because nothing on your disk travels with them, the rule flips: commit and push first, then hand off only what is on the remote. The handoff must also tell the next developer how to reach your exact starting point (which branch to pull, what to set up locally), since they start from a clean checkout.

### Hand off (copy and send when another developer will take over)

```text
Create a handoff document for this session. I am going to commit and push the changes, and another developer will pull the work on their own device and finish the rest. Write it to a new file handoffs/<timestamp>_<short-topic>.md (timestamp YYYY-MM-DD-HHMM from the system clock, topic a very short kebab-case slug, 2 to 3 words max) in the shared handoffs folder (ask me where it is if none is set); do not overwrite an existing handoff file. They should be able to pick up from this file and the remote alone, without me or my machine.

Because the work moves to another device, first help me get everything onto the remote, then base the handoff only on what is committed and pushed. Do not invent or assume anything; rebuild only from durable evidence:
- the working-memory log (per .claude/rules/knowledge-base.md)
- what we did in this session
- uncommitted changes (git status), the current branch, stashes, and any unpushed commits vs the remote - flag each as "not on the remote yet" so nothing is stranded on my device
- open Pull Requests I own and the next promotion step (use gh only if it is available)
- the sprint task checklist in .claude/PROJECT-CONTEXT.md

Include these sections:
- Task and goal (what we are building or fixing, and why)
- How to get to my starting point (the branch to check out or pull, its remote, the exact HEAD commit, and any local setup the next developer must do on their device: install/build commands, env vars they must set as placeholders, and config that is not in the repo)
- Current state (what is done and verified vs done but unverified)
- In progress (where it stopped and the exact next step to take)
- Key decisions and constraints made this session (so they are not relitigated)
- Files and code touched (paths and symbols, not large blocks)
- Validation state (what passed, what is failing, what is unrun, with the exact commands so they can re-run on their device)
- Open questions to settle between us
- Resume here (the single first action for the next developer)

Rules:
- Do not mark anything done or verified without evidence. Mark thin items "unverified - confirm".
- Treat any uncommitted or unpushed work as not handed off until it is on the remote; call it out explicitly.
- Flag anything you could not reconstruct and ask me to fill it in.
- No secrets, tokens, connection strings, .env values, or production row data; placeholders only. This file goes to another person, so it must be safe to share.
- After writing it, help me commit and push both the work and the handoff file (state the branch and command first), update the working-memory log's Focus/Next, then show me the exact file path you wrote.
```

### The next developer continues (resume, on their device)

```text
I am picking up work another developer handed off. Read the handoff file handoffs/<exact-filename>.md and tell me where we left off. First walk me through "How to get to my starting point" so my device matches theirs (branch to pull, setup, env placeholders), then start from the "Resume here" item. Do not assume anything not in the file or the repo; ask me if something is unclear or if my local setup does not match.
```

## Finding the right handoff file

You always resume from an exact file, never a "latest" pointer, since the `handoffs/` folder holds many files from different people and sessions. To pick one, list the folder and choose by timestamp, topic, or author, then use the matching resume prompt above with that exact filename. If you are not sure which to use:

```text
List the files in the handoffs folder and, for each, give me the date, topic, and a one-line summary so I can pick the one to resume. Do not open or act on any of them yet.
```

## Notes

- The handoff is working-memory continuity, not verified knowledge. It does not go through the knowledge-base approval gate; that gate still applies to durable write-ups (see `verified-closeout`).
- A handoff is not the EOD status report and does not replace it. The report is written automatically into `docs/eod/` as the work happens and is closed with `/eod`; a handoff is written when you ask, and carries the resume detail a status report has no room for.
- The file is meant to be shared, so it must stay secret-free. Keep placeholders such as `<APP_URL>`, `<DATABASE_NAME>`, and `<TOKEN>`, per `.claude/rules/secret-handling.md`.
- Committing and pushing the handoff is a normal Git action (pre-authorized); Claude states the branch and command first. Push it wherever your team agrees handoffs live.
- Keep your working-memory log current (Focus/Progress, decisions, open questions). The handoff is only as complete as the trace the session left behind.
