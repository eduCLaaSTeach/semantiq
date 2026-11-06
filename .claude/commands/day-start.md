# Day Start

Use once when a working session opens, before coding a feature. It sets the model, confirms which task the session is on, and keeps the work memory-first, with the schema read on demand and always verified against the project's schema source, so the session stays efficient without risking duplicate tables or stale schema. This command is for the start. Use `/session-handoff` at mid-session, at end of day, or from one developer to another, and `/account-handover` when switching to a different Claude account.

Input: `<TASK-ID>` for a tracked task, or a one-line goal when the project has no task backlog yet. With no input, ask for one.

Process:

1. Set the model. Default to Sonnet for the day's feature, CRUD, UI, test, and docstring work. Recommend Opus (High) only for architecture, schema design, or hard debugging, and switch back to Sonnet once that part is done. Sonnet is the default because it changes the per-token rate, not just the context size. Tell the developer which model fits, and to switch with `/model` when it is not the one in use. Model routing is in `00-read-first/02-guidelines/MODEL-ROUTING.md`.
2. Confirm which project and repository this is, and the active Task ID.
3. Where `doc/corerepo/` exists, read `doc/corerepo/14_AI_CONTEXT.md`, then `doc/corerepo/PROJECT_MANIFEST.yaml`. They hold the current task, gate, blockers, and next action in a few lines, which makes them the cheapest way back into the work. They are a snapshot: `doc/tasktracking/` stays the record of task status.
4. Read only the named task's entry in `doc/tasktracking/TASK_REGISTER.md`, never the whole register. When the Task ID is unclear, or does not match the register, ask which task is meant.
5. When no task backlog exists yet, say so plainly and work from the one-line goal.
6. Follow the root `CLAUDE.md` loader. Load a rule file only when the task hits its gate, and do not pre-read the rulebook. Pull, do not dump. Keep reports concise.
7. Read the task's requirements, acceptance criteria, and dependencies from its register entry. On a tracked task, start at Gate 1 through `/execute-approved-task <TASK-ID>`.
8. State the one-line task and the plan before editing anything.
9. Decide whether the task reads or writes data. When it does not, do not read the schema at all. When it does, the rules under Schema below apply.

Return: the model recommended, the project and Task ID (or that no backlog exists, with the goal), the task's status and gate, whether the task touches data and therefore needs schema verification, the one-line task and plan, and the next step, normally Gate 1 through `/execute-approved-task <TASK-ID>`.

## Schema

- Read the schema on demand, and always when the task touches the database, from the source recorded in `.claude/PROJECT-CONTEXT.md`.
- On a tracked task, data work runs Gates 2 to 4: the logical proposal comes first, and it is approved before any physical proposal is built against the project's schema source.
- Before proposing any new table or column, search the existing schema and the table dictionary first, so nobody duplicates a table or field another developer already created.
- Verify every table the task touches against the schema source in this session. Never trust remembered, cached, or prior-session schema. The schema source is the truth, per `.claude/rules/database-schema.md`.
- Batch related schema lookups into as few calls as possible.
- Within this session, reuse what was already verified, and do not query the same table again. There is no cross-session cache, so a new session verifies again.
- Never keep a timed cache of schema across sessions. It would undercut the reason schema is verified each session, which is to stop developers duplicating tables and fields.
- If the schema source cannot be read, stop and tell the developer. Do not guess.

## Always

- Work memory-first within the session, read the schema on demand and always verify it against its source, and load the minimum context per turn.
- Reinforce the gated `CLAUDE.md`, never restate it: analyze first, load rules by gate, pull rather than dump, and keep reports concise.
