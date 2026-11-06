# Account Handover

Move in-flight work from one Claude account to another: the usage allowance is exhausted, the account is being rotated, or the developer has to sign in as someone else. Signing in as a different account takes every open session with it, so each session worth keeping is captured first, one index lists the batch, and the new account resumes from that index.

This is the account boundary. `/session-handoff` covers the session, day, and developer-to-developer boundaries on the same account; use that one when the account is not changing. The two share the same `handoffs/` folder and the same file-naming rule, so their resume paths are interchangeable once the files exist.

Input: `[MODE]` - one of `capture` (default), `index`, `resume <index file>`, `rebuild`.

## When To Use This

- The account's usage allowance is exhausted mid-task and the developer has another account to continue on.
- A planned rotation: personal account to a team account, one team account to another, or a shared account being cycled.
- The account in use is being disabled, reassigned, or handed to someone else.

## What Moves And What Does Not

- The repository moves. The developer stays on the same machine, so the working tree, uncommitted edits, stashes, branches, and commits are all still there after the switch.
- Session context does not move. What is lost is the reasoning: the decisions that were only spoken in chat, the approaches already ruled out, the state each session had built up.
- The sign-in belongs to the Claude Code install, not to an individual chat. Treat the switch as affecting every session on the machine unless the team has a confirmed way to scope it per session. That is why every session is captured in one pass before switching.
- Do not assume the switch mechanism or the next account. Ask. In Claude Code the sign-in flow is `/login`, and the active account is confirmed before work starts.

## The Flow

State this order to the developer when the handover starts, and hold to it:

1. Freeze. Stop opening new work. Bring every active session to a clean boundary: finish the increment or park it, then commit and push per `.claude/rules/git-branching-release.md`. Never switch mid-edit.
2. Pick the sessions worth transferring. A session with nothing in flight needs no file. Be selective; each file is something the new account has to read.
3. Run `/account-handover capture` in each chosen session. Each one writes its own handover file.
4. Run `/account-handover index` in any one session. It writes a single index listing every file in the batch, in resume order. Commit and push the files and the index.
5. Switch the account, confirm the active account, then open a fresh session and run `/account-handover resume <index file>`.

## Where The Files Live And How They Are Named

The same convention as `/session-handoff`, so both kinds of handoff sit together:

- Folder: the shared `handoffs/` folder. Ask the developer where it is if none is set.
- Filename: `<timestamp>_<short-topic>.md`, timestamp `YYYY-MM-DD-HHMM` from the system clock, topic a very short kebab-case slug of 2 to 3 words. For example `handoffs/2026-07-21-1530_rounding-fix.md`.
- The index is one more file in the same folder, named `<timestamp>_account-handover-index.md`.
- Every capture file carries a `Batch:` line so the index can find its own members. The folder holds handoffs from other people and other days; the batch tag is what separates this switch from all of them.
- Never overwrite an existing handoff file. Resume only from an exact file name or the exact index.

## capture (default)

Use in every session being transferred, before the account switch. This session ends here and a fresh session on the new account picks the work up.

Process:

1. Confirm the `handoffs/` folder, asking the developer if none is set.
2. Create a new file `handoffs/<timestamp>_<short-topic>.md`, with the timestamp from the system clock and a 2 to 3 word kebab-case topic. Do not overwrite an existing file.
3. Start the file with these two lines, so the batch index can find it:
   - `Batch: account-handover <YYYY-MM-DD>`
   - `Session topic: <one short line>`
4. Rebuild the content from durable evidence only, and invent or assume nothing:
   - the working-memory log, per `.claude/rules/knowledge-base.md`;
   - what was done in this session;
   - uncommitted changes (`git status`), the current branch, stashes, and any unpushed commits against the remote;
   - open Pull Requests the developer owns and the next promotion step, using `gh` only if it is available;
   - the task register, task board, and event log in `doc/tasktracking/`.
5. Write these sections:
   - Task and goal: the Task ID, its status, and its current gate when the work is tracked; what is being built or fixed, and why.
   - Current state: done and verified, against done but unverified.
   - In progress: where it stopped, and the exact next step to take.
   - Key decisions and constraints made this session, so they are not relitigated on the new account.
   - Files and code touched: paths and symbols, not large blocks.
   - Validation state: what passed, what is failing, what is unrun, with the exact commands.
   - Open questions awaiting the developer's input.
   - Resume here: the single first action for the next session.
6. Keep it tight. The new account pays to read this, so cover what is needed to continue and nothing more.
7. Mark nothing done or verified without evidence. Mark thin items `unverified - confirm`.
8. Flag anything that could not be reconstructed and ask the developer to fill it in.
9. Update the working-memory log's Focus and Next.
10. Show the exact file path and offer to commit and push it, stating the branch and command first.

Return: the handover file path, its batch tag, the gaps the developer was asked to fill, and the commit status.

## index

Use once, in any one session, after every per-session capture file exists.

Process:

1. Read the shared `handoffs/` folder and find every file whose `Batch:` line is `account-handover <YYYY-MM-DD>` for today.
2. Show the developer that list before writing anything, and wait for them to confirm or correct it. The folder also holds other people's handoffs.
3. Once confirmed, create `handoffs/<timestamp>_account-handover-index.md`, with the timestamp from the system clock. Do not overwrite an existing file.
4. Write into it:
   - why the account was switched, in one line, with no account identifiers, using `<OLD_ACCOUNT>` and `<NEW_ACCOUNT>`;
   - the repository, the current branch, and whether anything is still uncommitted or unpushed;
   - one entry per captured session: the exact file path, its topic in one line, and its `Resume here` action copied verbatim;
   - the resume order: which session to pick up first, and why;
   - anything that applies across all of them: a shared blocker, a decision that affects several, a validation command that is still failing;
   - a one-line instruction telling the next session to read this index first, then open only the one handover file it is about to work on.
5. Write facts only, from the files and the repository. Do not re-derive or re-summarize the work.
6. Show the index path and offer to commit and push the index together with every capture file in the batch, stating the branch and command first.

Return: the index path, the files it lists in resume order, and the commit status.

## The Switch

Claude cannot switch the account. After `index` completes, state these steps to the developer, in order, and tell them they need nothing from Claude:

1. Confirm the index and every capture file are pushed. Anything unpushed is still on this machine, but the safety net is gone.
2. Note the exact index filename somewhere outside the session. It is the one thing needed after the switch.
3. Sign out of the current account and sign in as the next one, through the path the team has confirmed. In Claude Code this is the `/login` flow.
4. Confirm the active account is the new one before starting work.
5. Open a fresh session in the same repository and run `/account-handover resume <index file>`. Do not resume the old transcript: it carries the full history and the new account pays to re-read all of it.

## resume

Use on the new account, in a fresh session, with the exact index filename. `resume handoffs/<exact-index-filename>.md`.

Process:

1. Read the named index and tell the developer where the work stands across the sessions it lists.
2. Open only the first handover file in its resume order, and start from that file's `Resume here` item.
3. Read the index and that one file only, not the whole `handoffs/` folder and not the whole repository. Load rule files only when the task hits their gate, per `CLAUDE.md`.
4. Assume nothing that is not in the index, the file it points to, or the repository. Ask the developer when something is unclear or does not match what is on disk.
5. To pick up a second session afterwards, tell the developer to `/clear` and run the same command again for the next file, or run `/session-handoff` resume with that exact filename. One fresh session per handover file beats one long session carrying all of them.

Return: where the work stands across the batch, the handover file opened, the first action taken, and any mismatch between the files and the repository.

## rebuild

Use on the new account when the limit hit before a handover could be written, so there is no handover file to read. The work is not lost, only the reasoning.

Before the switch, when `capture` will not run at all, tell the developer to:

1. Write a two-line note for each session they care about: what they were doing, and the next step. Plain text in the `handoffs/` folder is enough.
2. Commit and push whatever is on disk. That is the part that travels regardless.
3. Run `/account-handover rebuild` on the new account instead of `resume`.

Process:

1. Rebuild where the work stands from the repository only, and invent nothing:
   - the working-memory log, per `.claude/rules/knowledge-base.md`;
   - recent commits, the current branch, uncommitted changes (`git status`), stashes, and any unpushed commits;
   - open Pull Requests and the next promotion step, using `gh` only if it is available;
   - the task register, task board, and event log in `doc/tasktracking/`;
   - any short notes in the `handoffs/` folder from today.
2. Tell the developer what looks finished, what looks half-done and where it stopped, and what cannot be reconstructed.
3. List the gaps as questions for the developer rather than filling them in.
4. Wait for the developer's answers before editing anything.

Return: what looks finished, what looks half-done, the gaps as questions, and confirmation that nothing was edited.

## Keeping The Switch Cheap

The switch usually happens because an allowance ran out, so the resume should not burn the next one:

- Read the index and one handover file. Do not re-read the repository or reload the rulebook.
- One fresh session per handover file, with `/clear` between them, instead of one session carrying every thread.
- Default to Sonnet for the continued work and escalate only where the reasoning is genuinely hard, per `00-read-first/02-guidelines/MODEL-ROUTING.md`.
- Read the schema only when the task actually touches data, and verify tables against the project's schema source in the new session when it does. Schema verified on the old account does not carry over.
- More on the levers: `00-read-first/03-reference/CREDIT-OPTIMIZATION.md`.

## Notes

- These files are shared and committed, so they stay secret-free. Placeholders such as `<APP_URL>`, `<DATABASE_NAME>`, and `<TOKEN>` only, per `.claude/rules/secret-handling.md`. Never record account credentials, sign-in codes, session tokens, or which credential belongs to which account; refer to accounts as `<OLD_ACCOUNT>` and `<NEW_ACCOUNT>`.
- A handover is working-memory continuity, not verified knowledge. It does not go through the knowledge-base approval gate; that gate still applies to durable write-ups (see `/verified-closeout`).
- Committing and pushing the files is a normal Git action and is pre-authorized. State the branch and command first.
- If the sessions span more than one repository, run the flow once per repository. Each repository gets its own captures and its own index.
- Keep the working-memory log current. A capture is only as complete as the trace the session left behind, and an account switch is exactly when that gap shows.
