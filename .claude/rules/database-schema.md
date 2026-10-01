# Database Schema Rules

Apply whenever Claude works on database schema or database-backed code: designing a table, changing one, writing a migration, or writing code that reads from or writes to a database.

The project's own schema is the source of truth, read from the place `.claude/PROJECT-CONTEXT.md` records for it: the database's own metadata where approved read access exists, otherwise the committed migrations and the schema definition the confirmed migration tool maintains. Which one applies is a confirmed fact. Ask when it is not recorded; never infer it from the stack.

The shape of a table is governed by `.claude/rules/semantic-data-model.md`. Classification, retention, and privacy stay in `.claude/rules/data-governance.md`. This rule owns how a schema fact is verified and how a schema change is agreed and delivered; cross-reference rather than duplicate.

## Prime Directive

Do not invent tables or columns. Do not execute DDL directly against any database. Do not change schema by any path other than the project's confirmed migration tool, after the developer has seen and confirmed the exact shape. If the schema source cannot be read, stop and tell the developer; do not guess.

Stop immediately if you are about to:

- Run a `CREATE TABLE`, `ALTER TABLE`, or `DROP TABLE` statement against any database.
- Reference a table or column name not verified against the project's schema source in the current session.
- Add or change a table or column before showing the developer the exact shape and getting explicit confirmation (see "Preview And Confirm Before Any Schema Change").
- Write a migration, `.sql`, or schema-definition file when no migration tool is confirmed in `.claude/PROJECT-CONTEXT.md`.

## Forbidden Use

- Do not request production row data to answer a schema question. Metadata answers it.
- Do not invent a migration path or write DDL as a shortcut. When no file-based migration tool is confirmed in `.claude/PROJECT-CONTEXT.md`, do not create `.sql`, migration, or schema-definition files in application repositories; ask the developer.
- Do not guess table names, column names, keys, constraints, indexes, enum values, or relationships.
- Do not print connection strings, passwords, tokens, or any credential used to reach a database, per `.claude/rules/secret-handling.md`.
- Do not store credentials or keys in a table; store secrets in the approved secret manager and keep only references.

## Before Data-Access Code

Before writing code that reads from or writes to a database:

1. Confirm the relevant schema and task concept from `.claude/PROJECT-CONTEXT.md`, or ask.
2. Verify every table the code will touch, and every column it names, against the project's schema source.
3. Use only verified table and column names.
4. If a table or column is not in the schema, treat it as unknown: stop, or run the reuse-first workflow and the preview-and-confirm loop below.

Memory, user statements, naming patterns, and old code are not enough. Schema facts learned in a prior session are potentially stale, including facts in the table dictionary, `.claude/PROJECT-CONTEXT.md`, or the knowledge base, and must be re-verified against the schema source in the current session. The source wins over the stored record.

## Reuse-First Workflow

When a feature seems to need new storage:

1. Restate the data concept in one short phrase.
2. Search the existing schema and the table dictionary for tables that already hold it, by name, by expected columns, and by related business terms.
3. Review every plausible match against its actual columns, and prefer reusing an existing table when it fits.
4. If no reusable option exists, present the options and tradeoffs to the developer before designing a new table.

Search for the feature's shared reference sets too, not only its main entity. The source, status, type, outcome, and reason lists a feature groups its reporting by often already exist, and a private copy is what makes cross-module reporting impossible later. Reuse the existing set, or add a member to it, rather than starting a second one. See `.claude/rules/semantic-data-model.md`.

## Preview And Confirm Before Any Schema Change

The developer must see and approve the exact shape before any migration is written. For every change, a new table or an existing-table change:

1. Show the developer the full shape in a readable form: the table name(s) and, per column, name, type, nullability, default, keys, and any indexes, constraints, or relationships, plus the one-sentence grain of every table (what one row means), per `.claude/rules/semantic-data-model.md`. For an existing-table change, show the current shape beside the proposed change so the difference is clear.
2. State the business concept, the developer-confirmed analytics question set with each question traced to what answers it, the reuse candidates checked and why they do or do not fit, the data risk, and the rollback or mitigation notes alongside the shape.
3. Ask the developer to confirm or request adjustments.
4. If they request adjustments, revise and show the updated shape again. Repeat until they explicitly confirm. Do not deliver a partially agreed shape.
5. Only after explicit confirmation of the shown shape, author the migration.

Developer approval is approval of the specific shape you displayed, not a blanket go-ahead. If the shape changes after approval, show it again and re-confirm.

## Modifying Existing Tables

Higher risk. First verify the current shape against the schema source.

Usually safe candidates: add a nullable column with no default; add a NOT NULL column with a default when the justification explains how existing rows are handled; add an index when the justification explains the access pattern.

Dangerous changes needing human design rather than an agent's proposal: rename a column; change a column type; drop a column; add a CHECK constraint without verifying existing data through approved human review.

## Migrations

The gateway is stack-neutral and must not assume or introduce any migration tool. The confirmed approach is recorded in `.claude/PROJECT-CONTEXT.md`. A confirmed tool changes only how an already verified, confirmed change is delivered; it relaxes no guarantee above.

Schema changes are authored as migration files following that tool's conventions (location, naming, ordering, format as recorded), under these lifecycle rules:

- Produce the migration only after the shape is verified and confirmed. It expresses the agreed change; it does not originate or shortcut it.
- Pair every forward migration with its rollback (down).
- Never edit a migration already merged or applied; add a new one instead.
- When two contributors' sequential ordering keys collide, the second to merge rebases and re-stamps its ordering key (the confirmed format) so the sequence stays gap-free.
- For shared or widely used tables, use backward-compatible, incremental (expand/contract) changes so features deploy independently.
- Authoring a migration file does not apply it. Running migrations against any environment is a deployment action governed by `.claude/rules/deployment.md` and `.claude/rules/production-readiness.md` and still requires explicit approval.

Do not write code that depends on a table or column until the migration creating it exists in the same change or is already applied.

## Legacy Or Suspicious Tables

If a table appears to contradict the project's design, do not propose deletion and do not redesign it on your own. Tell the developer it looks legacy or suspicious and should be assessed by its owner, then continue only with verified schema that fits the task.

## Final Reporting

For schema work, include: the schema source used for verification; tables verified; the reuse check run and its result; the exact shape shown and confirmed; the grain declared; the migration authored, with its rollback; whether any schema-dependent work was blocked; table-dictionary or knowledge-base update status; security impact, especially confirmation that no credentials, connection strings, row data, or secrets were printed.
