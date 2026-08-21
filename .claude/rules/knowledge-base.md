# Knowledge Base Rules

Claude must use `.claude/PROJECT-CONTEXT.md` and the repository knowledge base before writing or updating files, and must update durable knowledge after verified work only when the developer has verified, validated, and explicitly approved the update. The knowledge base is a solution -> module -> feature hierarchy, indexed from its README, maintained as living documentation.

Three distinct tiers; keep them separate so they do not duplicate or drift:

| Tier | Store | When written | Holds |
| --- | --- | --- | --- |
| Intake snapshot | `.claude/PROJECT-CONTEXT.md` | During intake, after the developer confirms a fact | Confirmed non-secret context, plus its Open-questions, Confirmed-decisions, Assumptions sections |
| Working memory | A lightweight running log in the repo (see below) | As work proceeds, not gated on validation | In-flight progress, a dated decision log, a running open-questions list |
| Knowledge base | Repository knowledge-base / solution write-ups | Only after validation and explicit approval | Verified, longer-lived project knowledge |

Record each item once in the most authoritative tier and reference it from the others. A confirmed working-memory open question is promoted to `.claude/PROJECT-CONTEXT.md`; a verified outcome is promoted to the knowledge base. Do not maintain the same fact in parallel.

## Read Before Editing

Before editing, search for and read relevant knowledge-base files, including existing variants such as `docs/knowledge-base/`, `docs/kb/`, `knowledge-base/`, `docs/table-dictionary.md`, `docs/database/`. Use the repository's existing structure when present. Also read `.claude/PROJECT-CONTEXT.md`; if context is blank or incomplete, ask until the needed facts are confirmed.

## Default Structure

If no structure exists and the task creates durable knowledge, use this default after verification and explicit approval:

```text
docs/knowledge-base/README.md
docs/knowledge-base/master-knowledge-base.md
docs/knowledge-base/table-dictionary.md
docs/knowledge-base/solutions/<solution-name>/README.md
docs/knowledge-base/solutions/<solution-name>/<module-name>/README.md
docs/knowledge-base/solutions/<solution-name>/<module-name>/<feature-name>.md
```

## Solution, Module, And Feature Hierarchy

A solution has multiple modules; each module has multiple features.

| Level | Meaning | Documented in |
| --- | --- | --- |
| Solution | A deliverable product or system, made of one or more modules | `solutions/<solution-name>/README.md` |
| Module | A cohesive part of a solution, made of one or more features | `solutions/<solution-name>/<module-name>/README.md` |
| Feature | A single capability inside a module | `solutions/<solution-name>/<module-name>/<feature-name>.md` |

Before writing an entry, ask the developer whether the work is a solution, module, or feature, and where it sits (which solution, which module). Do not classify the work yourself and do not invent solution/module names; a new level is created only with a name the developer confirms.

- A solution README records the solution's purpose, its module inventory with links, and solution-wide facts (shared architecture, cross-module integration points).
- A module README records the module's purpose, its feature inventory with links, and module-wide facts (shared logic, shared libraries, shared data concepts).
- A feature file records the full write-up below.

When work is a solution or module, document its constituent features as feature write-ups and keep parent READMEs to overviews and inventories; detailed technical content lives at the feature level.

## Feature Write-Up Contents

Cover each of the following in this order. When a layer does not exist (for example a backend-only feature with no UI), state "Not applicable" rather than omitting it or inventing content:

1. Logical flows and workflows: end-to-end behavior, user flows, business workflows, state transitions, decision points.
2. Data models and tables: the entities the feature reads/writes and the verified tables and columns behind them, metadata only. Names come from Schema MCP verification per `.claude/rules/schema-mcp.md`, never from memory; detailed column metadata lives in the table dictionary, linked here rather than duplicated. Include each table's one-sentence grain, the feature's confirmed analytics question set with each question traced to what answers it, the entity relationships with cardinality and optionality, the ERD, and the semantic hand-off (measures with units and additivity, dimension attributes, hierarchies), per `.claude/rules/semantic-data-model.md`.
3. Frontend: code paths (files, components), logic, libraries.
4. Backend: code paths (files, services), logic, libraries.
5. API routing: routes/endpoints exposed or consumed, routing logic, libraries, consistent with `.claude/rules/api-design.md` when a service interface is in play.
6. System architecture: how the pieces fit, dependency direction, integration points, and where the feature sits in the confirmed architecture.

Cite code by file path and symbol name rather than pasting large blocks. Never include secrets, tokens, connection strings, or production row data, per `.claude/rules/secret-handling.md`.

## The README Index

The knowledge-base README is the index of the entire knowledge base. Maintain it so a reader can reach any file directly:

- Link every knowledge-base file (master knowledge base, table dictionary, and every solution/module/feature write-up), organized by the hierarchy.
- Update the index in the same change unit as any file added, renamed, moved, or removed. An unlinked knowledge-base file is a defect.

## Update Timing And The Approval Gate

Update knowledge-base files only after: (1) implementation is complete; (2) relevant validation has run, or the limitation is documented; (3) the developer has verified, validated, and explicitly approved the update.

After each completed implementation, prompt the developer to create/update now or defer. Do not write unprompted, and do not treat silence, the passage of time, or your own confidence as approval. When the developer approves, ask the classification question first, then write or update the entry, refresh the README index, and report what was written. When the developer defers, write nothing and record the deferral in the final report. If knowledge-base files do not exist, ask before creating them; prefer the existing structure.

Update `.claude/PROJECT-CONTEXT.md` with confirmed non-secret facts during intake. Update knowledge-base files only after verified work and explicit approval.

## Living Documentation

The knowledge base is living documentation, not a one-time artifact:

- When later verified work changes behavior a write-up already documents, update it in the same closeout, behind the same gate.
- When the hierarchy grows (a new module in a documented solution, a new feature in a documented module), add the entry and refresh the parent README inventory and the README index in the same change.
- A write-up that contradicts shipped, verified behavior is a defect; fix it at the closeout of the change that made it stale, not in a later pass.

## Working Memory Across Sessions

There is no durable memory between sessions. Anything Claude knows about in-flight work is lost at session end unless written into the repository. The working-memory log is the only thing that survives a restart, and unlike the knowledge base it is updated as work proceeds, not gated on validation. Keep it short and current; it is a scratch log, not a deliverable.

Use the repository's existing structure when present. If none exists and a running log is needed, ask before creating one, and keep it in a single obvious location (for example an `.ai/` directory; any agreed location is fine, do not assume a fixed folder name). Cover three things:

### Current Focus And Progress

```text
## Focus: <one-line description of the current task>

Done:        <completed, verified-or-not steps>
In progress: <what is actively being worked, and where it stopped>
Next:        <the immediate next steps>
Blockers:    <anything preventing progress, and who/what is needed to unblock>
```

### Decision Log (Lightweight ADRs)

```text
### <YYYY-MM-DD> <short decision title>
Context:    <what prompted the decision>
Decision:   <what was chosen>
Why:        <the reasoning and any rejected alternatives>
Reversible: <yes / no - and if no, what makes it costly to undo>
```

Never silently reverse a recorded decision. If new information makes a logged decision look wrong, surface it as an open question, note the conflicting decision by date, and ask before reversing. A reversal is a new dated entry, not an edit that erases the old one.

### Open Questions

```text
- [ ] <open question> - <why it matters / what is blocked by it>
```

When the developer answers, promote the confirmed answer to the right tier (`.claude/PROJECT-CONTEXT.md` for durable intake facts, or the knowledge base for verified outcomes through the approval gate) and close it out. Do not let the same open question live in two tiers.

## What To Record

Concise, verified facts: changed behavior; affected files/modules/apps; commands used for validation; reusable implementation patterns; integration assumptions; table/schema concepts touched; MCP proposal IDs; known risks and follow-up.

Do not record tentative plans as verified facts. Do not record secrets, credentials, hosting/deployment/database passwords, tokens, private keys, production row data, or customer records.

## Table Dictionary

Update the table dictionary when work verifies or changes knowledge about database tables, columns, relationships, indexes, repositories, reports, imports, exports, or schema proposals. Metadata only; no row data, customer records, tokens, passwords, or production values.

Record each table's grain sentence and each column's analytical role (key, dimension key, dimension attribute, measure with its unit and additivity, business date, audit, scope, row reference) beside its classification, per `.claude/rules/semantic-data-model.md`, so whoever builds a report or a semantic model does not have to infer it from code.

Table-dictionary updates follow the approval gate above, with one exception below.

### Keep Schema Documentation Atomic With Schema Changes

Durable write-ups still follow post-validation timing. The exception: when a change actually alters the schema, the schema-metadata / table-dictionary update travels in the same change unit (same commit or Pull Request) as the schema change, so documented schema does not drift from real schema. Do not split them or defer the metadata update. A change unit that lands a verified schema change without its matching table-dictionary update is incomplete. This applies only to metadata describing the changed schema; broader write-ups still wait for validation and explicit approval.

## Master Knowledge Base

For repositories with multiple solutions, services, modules, or tools, update the master knowledge base after verified work, behind the same gate. It tracks: application/module inventory; purpose and ownership; setup/build/test commands; main data concepts; table dictionary links; shared patterns and pitfalls.

## Final Reporting

For any task reaching the knowledge-base gate, report: whether the post-implementation prompt was made and the developer's decision (approved or deferred); the confirmed classification (solution, module, feature) and where it sits; the files written or updated, including the README index; table-dictionary and master-knowledge-base status; confirmation that no secrets, production row data, or unverified assumptions were recorded.
