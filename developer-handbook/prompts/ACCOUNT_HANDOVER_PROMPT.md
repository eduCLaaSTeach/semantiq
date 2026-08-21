# Account Handover Prompts

Copy-ready prompts for moving in-flight work from one Claude account to another: the usage allowance is exhausted, the account is being rotated, or you have to sign in as someone else. Signing in as a different account takes every open session with it, so you capture each chat you care about first, then point the new account at one index file and carry on.

This is the account boundary. `DAY_HANDOFF_PROMPT.md` covers the session, day, and developer-to-developer boundaries on the same account; use that one when the account is not changing. The two share the same `handoffs/` folder and the same file-naming rule, so the resume prompts are interchangeable once the files exist.

## When To Use This

- The account's usage allowance is exhausted mid-task and you have another account to continue on.
- A planned rotation: personal account to a team account, one team account to another, or a shared account being cycled.
- The account you are signed in with is being disabled, reassigned, or handed to someone else.

## What Moves And What Does Not

- The repository moves. You stay on the same machine, so the working tree, uncommitted edits, stashes, branches, and commits are all still there after the switch.
- Session context does not move. What you lose is the reasoning: the decisions that were only spoken in chat, the approaches already ruled out, the state each session had built up.
- The sign-in belongs to the Claude Code install, not to an individual chat. Treat the switch as affecting every session on the machine unless your team has a confirmed way to scope it per session. That is the whole reason you capture in one pass before switching.
- Do not assume the switch mechanism or the next account. Ask. In Claude Code the sign-in flow is `/login`, and you confirm the active account before starting work.

## The Flow

1. Freeze. Stop opening new work. Bring every active session to a clean boundary: finish the increment or park it, then commit and push per `.claude/rules/git-branching-release.md`. Never switch mid-edit.
2. Pick the chats worth transferring. A session with nothing in flight needs no file. Be selective; each file is something the new account has to read.
3. In each chosen chat, paste the capture prompt below. Each one writes its own handover file.
4. In any one chat, paste the index prompt. It writes a single index listing every file in the batch, in resume order. Commit and push the files and the index.
5. Switch the account, confirm the active account, then open a fresh session and paste the resume prompt pointing at the index.

## Where The Files Live And How They Are Named

Same convention as `DAY_HANDOFF_PROMPT.md`, so both kinds of handoff sit together:

- Folder: the shared `handoffs/` folder (ask Claude to confirm the path if none is set).
- Filename: `<timestamp>_<short-topic>.md`, timestamp `YYYY-MM-DD-HHMM` from the system clock, topic a very short kebab-case slug of 2 to 3 words. For example `handoffs/2026-07-21-1530_rounding-fix.md`.
- The index is one more file in the same folder, named `<timestamp>_account-handover-index.md`.
- Every capture file carries a `Batch:` line so the index can find its own members. The folder holds handoffs from other people and other days; the batch tag is what separates this switch from all of them.
- Claude never overwrites an existing handoff file. You resume by naming an exact file, or the exact index.

## 1. Capture One Session (paste in every chat you are transferring)

```text
I am about to switch to a different Claude account, so this session ends here and a fresh session on the new account has to pick the work up. Write a handover document to a new file handoffs/<timestamp>_<short-topic>.md (timestamp YYYY-MM-DD-HHMM from the system clock, topic a very short kebab-case slug, 2 to 3 words max) in the shared handoffs folder (ask me where it is if none is set). Do not overwrite an existing file. Keep it tight: the new account pays to read this, so cover what is needed to continue and nothing more.

Start the file with these two lines so the batch index can find it:
- Batch: account-handover <YYYY-MM-DD>
- Session topic: <one short line>

Do not invent or assume anything. Rebuild only from durable evidence:
- the working-memory log (per .claude/rules/knowledge-base.md)
- what we did in this session
- uncommitted changes (git status), the current branch, stashes, and any unpushed commits vs the remote
- open Pull Requests I own and the next promotion step (use gh only if it is available)
- the sprint task checklist in .claude/PROJECT-CONTEXT.md

Include these sections:
- Task and goal (what we are building or fixing, and why)
- Current state (done and verified vs done but unverified)
- In progress (where it stopped and the exact next step to take)
- Key decisions and constraints made this session (so they are not relitigated on the new account)
- Files and code touched (paths and symbols, not large blocks)
- Validation state (what passed, what is failing, what is unrun, with the exact commands)
- Open questions awaiting my input
- Resume here (the single first action for the next session)

Rules:
- Nothing is marked done or verified without evidence. Mark thin items "unverified - confirm".
- Flag anything you could not reconstruct and ask me to fill it in.
- No secrets, tokens, connection strings, .env values, or production row data; placeholders only. Never record account credentials, sign-in codes, or which credential belongs to which account - refer to accounts as <OLD_ACCOUNT> and <NEW_ACCOUNT>.
- After writing it, update the working-memory log's Focus/Next, then show me the exact file path and offer to commit and push it.
```

## 2. Write The Batch Index (paste once, after the per-session files exist)

```text
I have captured several sessions for an account switch. Write one index so the new account has a single entry point. Read the shared handoffs folder, find every file whose Batch line is "account-handover <YYYY-MM-DD>" for today, and show me that list before writing anything - I will confirm or correct it, since the folder also holds other people's handoffs.

Once I confirm, write handoffs/<timestamp>_account-handover-index.md (timestamp YYYY-MM-DD-HHMM from the system clock; do not overwrite an existing file) with:
- Why we switched, in one line, with no account identifiers (use <OLD_ACCOUNT> and <NEW_ACCOUNT>)
- Repository, current branch, and whether anything is still uncommitted or unpushed
- One entry per captured session: the exact file path, its topic in one line, and its "Resume here" action copied verbatim
- Resume order: which session to pick up first, and why
- Anything that applies across all of them (a shared blocker, a decision that affects several, a validation command that is still failing)
- A one-line instruction telling the next session to read this index first, then open only the one handover file it is about to work on

Rules:
- Facts only, from the files and the repo. Do not re-derive or re-summarize the work.
- No secrets, tokens, or account credentials; placeholders only.
- After writing it, show me the index path and offer to commit and push the index together with every capture file in the batch.
```

## 3. Switch The Account

Do this only after the files are committed and pushed. Ordered, and nothing here needs Claude:

1. Confirm the index and every capture file are pushed. Anything unpushed is fine for you on this machine, but you lose the safety net.
2. Note the exact index filename somewhere outside the session. It is the one thing you need after the switch.
3. Sign out of the current account and sign in as the next one, through the path your team has confirmed. In Claude Code this is the `/login` flow.
4. Confirm the active account is the new one before you start work.
5. Open a fresh session in the same repository and paste the resume prompt below. Do not resume the old transcript: it carries the full history and the new account pays to re-read all of it.

## 4. Resume On The New Account

```text
I have switched to a different Claude account. Read the handover index handoffs/<exact-index-filename>.md and tell me where we left off across the sessions it lists. Then open only the first handover file in its resume order and start from that file's "Resume here" item.

Keep this cheap: read the index and that one file, not the whole folder and not the whole repository. Load rule files only when the task hits their gate, per CLAUDE.md. Do not assume anything that is not in the index, the file it points to, or the repo - ask me if something is unclear or does not match what is on disk.
```

To pick up a second session afterwards, `/clear` and paste the same prompt with the next file, or use the single-file resume prompt in `DAY_HANDOFF_PROMPT.md` with that exact filename. One fresh session per handover file beats one long session carrying all of them.

## When The Limit Has Already Hit

The capture prompt is deliberately short so it still runs when the allowance is nearly gone. If it will not run at all, the work is not lost, only the reasoning is:

1. Write a two-line note yourself for each session you care about: what you were doing, and the next step. Plain text in the `handoffs/` folder is enough.
2. Commit and push whatever is on disk. That is the part that travels regardless.
3. On the new account, use the rebuild prompt below instead of the index resume.

```text
I ran out of usage on my previous Claude account before I could write a handover, so there is no handover file to read. Rebuild where we were from the repository only, and do not invent anything:
- the working-memory log (per .claude/rules/knowledge-base.md)
- recent commits, the current branch, uncommitted changes (git status), stashes, and any unpushed commits
- open Pull Requests and the next promotion step (use gh only if it is available)
- the sprint task checklist in .claude/PROJECT-CONTEXT.md
- any short notes in the handoffs folder from today

Then tell me: what looks finished, what looks half-done and where it stopped, and what you cannot reconstruct. List the gaps as questions for me rather than filling them in. Wait for my answers before editing anything.
```

## Keeping The Switch Cheap

You switched because an allowance ran out, so the resume should not burn the next one:

- Read the index and one handover file. Do not re-read the repository or reload the rulebook.
- One fresh session per handover file, with `/clear` between them, instead of one session carrying every thread.
- Default to Sonnet for the continued work and escalate only where the reasoning is genuinely hard, per `developer-handbook/guidelines/MODEL-ROUTING.md`.
- Call the Schema MCP only when the task actually touches data, and verify tables live in the new session when it does. Schema verified on the old account does not carry over.
- More on the levers: `developer-handbook/reference/CREDIT-OPTIMIZATION.md`.

## Notes

- These files are shared and committed, so they must stay secret-free. Placeholders such as `<APP_URL>`, `<DATABASE_NAME>`, and `<TOKEN>` only, per `.claude/rules/secret-handling.md`. Never record account credentials, sign-in codes, session tokens, or which credential belongs to which account.
- A handover is working-memory continuity, not verified knowledge. It does not go through the knowledge-base approval gate; that gate still applies to durable write-ups (see `verified-closeout`).
- Committing and pushing the files is a normal Git action and is pre-authorized; Claude states the branch and command first.
- If your sessions span more than one repository, run the flow once per repository. Each repo gets its own captures and its own index.
- Keep your working-memory log current. A capture is only as complete as the trace the session left behind, and an account switch is exactly when that gap shows.
