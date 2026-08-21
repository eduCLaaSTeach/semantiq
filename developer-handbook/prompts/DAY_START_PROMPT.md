# Day Start Prompt

Run this once when you open Claude Code, before you start coding a feature. It sets the model, confirms context, and keeps work memory-first and MCP-on-demand while always verifying schema live, so it stays efficient without risking duplicate tables or stale schema on the shared database. Paste the block below, fill in the feature, and go.

It pairs with the session-handoff prompts in `DAY_HANDOFF_PROMPT.md` (use those at mid-session, end-of-day, or developer-to-developer) and with `ACCOUNT_HANDOVER_PROMPT.md` (use that when you switch to a different Claude account). This one is for the start.

---

```markdown
# Daily start - run once when opening Claude Code, before coding

Session setup:
- Model: default to Sonnet for today's feature/CRUD/UI/test/docstring work.
  Escalate to Opus (High) only for architecture, schema design, or hard debugging,
  then switch back. (See developer-handbook/guidelines/MODEL-ROUTING.md.)
- Confirm which project/repo and the active PROJECT-CONTEXT sprint item we are on.
- Follow the CLAUDE.md loader: load rules only when the task hits their gate;
  pull, do not dump; keep reports concise.

Feature: <Feature Name>
Requirements:
<Paste feature requirements here>

How to work this feature:
- Analyze the requirements first and state the one-line task and your plan before editing.
- Load only the rule files the task actually triggers; do not pre-read the rulebook.
- MCP and schema: on demand, but mandatory when the task touches the database.
  - If the feature reads or writes data, MCP is required, not optional.
  - Before proposing any new table or column, run
    mcp__schema__find_existing_tables_for_concept first, to avoid duplicating
    tables or fields others may already have created on the shared DB.
  - Verify every table you will touch with mcp__schema__describe_table live this session.
    Never trust remembered, cached, or prior-session schema; the live DB is the truth.
  - Batch related schema lookups into as few calls as possible.
  - Within this session, reuse what you have already verified; do not re-query the same
    table repeatedly. There is no cross-session cache, so a new session re-verifies.
  - If the schema server is unreachable or unauthorized, stop and tell me; do not guess.
- If the feature does not touch data, do not call MCP at all.

Goal: memory-first within the session, MCP on demand and always live for schema,
minimum context loaded per turn, and no risk of duplicate tables or stale schema
on the shared database.
```

---

## Why It Is Shaped This Way

- Model first. Defaulting to Sonnet is the largest lever on your Claude plan's usage, because it changes the per-token rate, not just the context size.
- Schema is verified live, never time-cached. A timed cross-session cache would undercut the reason the Schema MCP exists, which is to stop developers from duplicating tables and fields on one shared database, and it would contradict `.claude/rules/schema-mcp.md` (prior-session schema is potentially stale; use the live MCP result as the source of truth). Reuse within a session is fine and encouraged; a stale cross-session cache is not.
- MCP on demand, but mandatory for data work. Not using MCP by default holds for non-data tasks, but any database or persistence work must run the reuse-check with `find_existing_tables_for_concept` before proposing new storage.
- The prompt reinforces the gated `CLAUDE.md` rather than repeating it: analyze first, load rules by gate, pull rather than dump, and keep reports concise.
