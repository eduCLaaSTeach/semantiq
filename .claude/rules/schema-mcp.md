# Schema MCP Rules

Apply whenever Claude works on database schema or database-backed code. The SQL Server Schema MCP server named `schema` is the only approved database metadata source of truth for this gateway.

Before schema work starts, read the target repo `./docs/MCP-USAGE.md` if it exists, else `.claude/docs/MCP-USAGE.md`. Confirm to the developer that it was read and summarize these four rules:

1. Do not write DDL.
2. Do not invent table or column names.
3. Always check existing tables before proposing new ones.
4. Stop and tell the developer if the MCP server is unreachable.

## Prime Directive

Do not invent tables or columns. Do not write SQL DDL directly. Do not modify schema by any path other than the Schema MCP proposal workflow. Always verify SQL Server Schema MCP metadata before database-dependent work. If `schema` is unreachable or unauthorized, stop and tell the developer; do not guess.

Stop immediately if you are about to:

- Write a `CREATE TABLE`, `ALTER TABLE`, or `DROP TABLE` statement.
- Reference a table name not verified with `mcp__schema__list_tables` or `mcp__schema__describe_table`.
- Reference a column name not verified with `mcp__schema__describe_table`.
- Add or change a column without `mcp__schema__propose_table_change` after developer approval.
- Call `mcp__schema__propose_table_change` before showing the developer the exact proposed table/column shape and getting explicit confirmation (see "Preview And Confirm Before Any Proposal").
- Bypass MCP because a tool call failed.

## Mandatory MCP Preflight

The `schema` server lives in Claude Code's own MCP configuration, not this repository, so it can be missing, disconnected, or have an expired token at any time. Do not assume it is connected.

Before any schema-dependent work and before calling any `mcp__schema__*` tool, check your connected MCP servers and confirm `schema` and its tools are available. If missing, disconnected, or the token has expired, stop and alert the developer: state that `schema` is unavailable and ask them to add or refresh it through the approved setup path. Do not guess and do not try alternate credentials. If a `mcp__schema__*` call later fails with an authorization or connection error, stop immediately, report it, and follow the same path. Use `/mcp-health-check` when unsure.

## Available Tools

| Tool | Purpose |
| --- | --- |
| `mcp__schema__list_tables` | List available SQL Server tables, optionally by schema. |
| `mcp__schema__describe_table` | Describe columns, keys, indexes, constraints, relationships. |
| `mcp__schema__find_existing_tables_for_concept` | Search existing tables for a business/data concept before proposing new storage. |
| `mcp__schema__list_pending_proposals` | Review pending schema proposals. |
| `mcp__schema__propose_table_change` | Submit schema change proposals after the developer confirms the previewed shape. |
| `mcp__schema__get_proposal` | Review one proposal by ID. |

## Forbidden Use

- Do not request production row data or generate direct database connection code in application repositories.
- Do not invent a migration path or write DDL as a shortcut. When no file-based migration tool is confirmed in `.claude/PROJECT-CONTEXT.md`, do not create `.sql`, migration, or schema-definition files in application repositories. When one is confirmed, follow "Confirmed File-Based Migration Tools" below.
- Do not guess table names, column names, keys, constraints, indexes, enum values, or relationships.
- Do not write code that depends on a not-yet-fulfilled proposal.
- Do not print bearer tokens, Authorization headers, refresh tokens, full JWTs, or decoded token claims.
- Do not store credentials or keys in proposed tables; store secrets in the approved secret manager and keep only references.

## Before Data-Access Code

Before writing code that reads from or writes to a database:

1. Read the MCP usage document and summarize the four key rules.
2. Confirm the relevant schema and task concept from `.claude/PROJECT-CONTEXT.md` or ask.
3. Use `mcp__schema__list_tables` when schema/table existence needs confirmation.
4. Use `mcp__schema__describe_table` for every table the code will touch.
5. Use only verified table and column names.
6. If a table or column is not in MCP metadata, treat it as unknown and stop or propose a change through the approved workflow.

Memory, user statements, naming patterns, and old code are not enough. Schema metadata learned in a prior session is potentially stale (including facts in the table dictionary, `.claude/PROJECT-CONTEXT.md`, or the knowledge base) and must be re-verified in the current session with `mcp__schema__describe_table` (and `mcp__schema__list_tables` when existence is in question); use the live MCP result, not the stored record, as the source of truth. If MCP is unavailable, do not continue with schema-dependent design, code, tests, imports, exports, reports, mappings, or documentation naming unverified tables or columns.

## Reuse-First Workflow

When a feature seems to need new storage:

1. Restate the data concept in one short phrase.
2. Use `mcp__schema__find_existing_tables_for_concept` with hints (expected column names, schema prefix, related business terms).
3. Review every plausible result. Above 0.6 is a strong match; 0.4 to 0.6 is worth investigating; below 0.4 is usually weak but still worth a brief look.
4. Use `mcp__schema__describe_table` for each plausible match, and prefer reusing an existing table when it fits.
5. If no reusable option exists, present the options and tradeoffs to the developer before proposing a new table.

Do not skip `mcp__schema__find_existing_tables_for_concept` before proposing new storage.

Search for the feature's shared reference sets too, not only its main entity. The source, status, type, outcome, and reason lists a feature groups its reporting by are usually already platform dimensions, and a private copy is what makes cross-module reporting impossible later. Reuse the existing set, or propose adding a member to it, rather than starting a second one. See `.claude/rules/semantic-data-model.md`.

## Preview And Confirm Before Any Proposal

Never call `mcp__schema__propose_table_change` as the first step. Sending an unseen proposal to the Schema MCP is wrong: the developer must see and approve the exact shape first.

For every proposal, a new table or an existing-table change, before the tool call:

1. Show the developer the full proposed shape in a readable form: the table name(s) and, per column, name, SQL Server type, nullability, default, keys, and any indexes, constraints, or relationships, plus the one-sentence grain of every table (what one row means), per `.claude/rules/semantic-data-model.md`. For an existing-table change, show the current shape (from `mcp__schema__describe_table`) beside the proposed change so the difference is clear.
2. State the business concept, the developer-confirmed analytics question set with each question traced to what answers it, the reuse candidates checked and why they do or do not fit, the data risk, and the rollback/mitigation notes alongside the shape.
3. Ask the developer to confirm or request adjustments.
4. If they request adjustments, revise and show the updated shape again. Repeat until they explicitly confirm. Do not send a partially-agreed shape.
5. Only after explicit confirmation of the shown shape, call `mcp__schema__propose_table_change`.

Developer approval is approval of the specific shape you displayed, not a blanket go-ahead. If the shape changes after approval, show it again and re-confirm before proposing.

## Proposing A New Table

Run the preview-and-confirm loop above first; call `mcp__schema__propose_table_change` only after the developer explicitly confirms the shown shape. At minimum capture the business concept, the table's grain sentence, the confirmed analytics question set and what answers each question, reuse candidates checked and why they do or do not fit, proposed fields with SQL Server types where known, relationships, indexes/constraints if relevant, data risk, rollback/mitigation notes, and validation plan. Column naming follows the confirmed SQL Server/platform convention.

After submitting: capture the returned proposal ID; tell the developer the table does not exist yet; stop any work that would query, insert, map, import, export, or otherwise reference it; wait until the developer-confirmed status that means the schema physically exists; then verify the final shape with `mcp__schema__describe_table` before coding against it.

## Pending Proposals

Proposal statuses are project-specific; ask the developer which status means the table physically exists and is safe to reference. While a proposal is unfulfilled you may work on code that does not depend on it, but you may not query, insert into, or update it, add ORM mappings, or add imports, exports, reports, jobs, tests, fixtures, or generated queries that reference the proposed table or columns. Use `mcp__schema__get_proposal` to check status.

## Modifying Existing Tables

Higher risk. First verify the current shape with `mcp__schema__describe_table`.

Usually safe candidates: add a nullable column with no default; add a NOT NULL column with a default when the justification explains how existing rows are handled; add an index when the justification explains the access pattern.

Dangerous changes needing human design instead of an agent proposal: rename a column; change a column type; drop a column; add a CHECK constraint without verifying existing data through approved human review.

Run the preview-and-confirm loop above before any existing-table proposal, showing the current shape beside the proposed change so the developer sees the difference and confirms it. For any existing-table proposal, follow the confirmed DBA/platform proposal format so reviewers do not mistake it for a new-table proposal.

## Confirmed File-Based Migration Tools

The gateway is stack-neutral and must not assume or introduce any migration tool. This section applies only when the confirmed migration approach is recorded in `.claude/PROJECT-CONTEXT.md`. A confirmed tool changes only how an already-verified, approved change is delivered; it relaxes no guarantee above (never execute DDL directly, verify and propose through the Schema MCP first, never invent tables/columns/relationships).

When confirmed, schema changes may be authored as migration files following that tool's conventions (location, naming, ordering, format as recorded), under these lifecycle rules:

- Produce the migration only after the change is verified against live MCP metadata and approved through the proposal workflow. It expresses the approved change; it does not originate or shortcut it.
- Pair every forward migration with its rollback (down).
- Never edit a migration already merged or applied; add a new one instead.
- When two contributors' sequential ordering keys collide, the second to merge rebases and re-stamps its ordering key (the confirmed format) so the sequence stays gap-free.
- For shared or widely-used tables, use backward-compatible, incremental (expand/contract) changes so features deploy independently.
- Authoring a migration file does not apply it. Running migrations against any environment is a deployment action governed by `.claude/rules/deployment.md` and `.claude/rules/production-readiness.md` and still requires explicit approval.

When no migration tool is confirmed, do not invent one or write migration/DDL files as a shortcut; follow the proposal workflow and ask the developer.

## Legacy Or Suspicious Tables

If a table appears to contradict platform design, do not propose deletion and do not redesign it through MCP. Tell the developer it looks legacy or suspicious and should be assessed by the platform team, then continue only with verified schema that fits the task.

## Failure Handling

| Failure | Required response |
| --- | --- |
| Server unreachable, connection refused, or 5xx | Tell the developer `schema` is unreachable and stop schema-touching work. |
| Unauthorized or expired credentials | Tell the developer MCP credentials are unusable and ask them to follow the approved refresh/setup path. |
| Missing write/proposal permission | Tell the developer the access is read-only or insufficient and the approved proposal/write permission is required. |
| Proposal/write-path compatibility error | Provide the intended proposal payload with placeholders only, state the write path failed, and ask the platform owner or authorized user to file it. |

Do not fall back to direct SQL, an invented migration path, guessed schema, alternate credentials, or production row data. A confirmed file-based migration tool is a delivery format for already-verified, MCP-proposed changes, never a workaround for an unreachable or unauthorized MCP server: if MCP is unavailable, schema-touching work stops regardless of whether a migration tool exists.

## Final Reporting Requirements

For schema work, include: MCP tools used; tables described or proposals reviewed; proposal IDs submitted or checked; whether any schema-dependent work was blocked; database/schema impact; table-dictionary or knowledge-base update status; security impact, especially confirmation that no tokens, headers, row data, or secrets were printed.
