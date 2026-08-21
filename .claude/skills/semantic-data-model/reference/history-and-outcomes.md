# History And Outcomes

An update destroys the previous value. That is correct behavior for a screen and fatal for every question about change: how many last month, how long in stage, how many failed and why, how many came back. Those answers exist only if the change was written as a row at the time it happened.

This file covers the four capture shapes, the failure taxonomy, and identity resolution. Table and column names are concepts. Map them to the project's confirmed naming convention, and get the real names through the Schema MCP workflow in `.claude/rules/schema-mcp.md`.

## Pick The Shape From The Question

| The question asks | Use | Grain |
| --- | --- | --- |
| How many moved into a state, how long they sat in one, in what order | State history | One row per transition |
| How many attempts, how many succeeded, how many failed and why, how long each took | Attempt or event log | One row per attempt |
| What an attribute's value was as at a past date | Versioned attribute history | One row per value period |
| How the mix across states looked month by month | Periodic snapshot | One row per thing per period |
| What the value is now | The record itself | One row per thing |

Add only the shapes a confirmed question needs. Most features need the record plus one other shape. A feature that needs all four is unusual and worth a conversation before building.

## State History

One row per transition, written when the state changes.

| Column | Holds |
| --- | --- |
| entity key | The record whose state changed |
| from state | The state left, and a reserved member for the initial transition |
| to state | The state entered, keyed to the state reference set |
| changed at | The timezone-aware business time of the change |
| changed by | The user, service, or job that caused it, and how (screen, import, API, automation) |
| reason code | Why, from a codified reason set, where the question asks why |
| correlation id | The id that ties this change to the request or job that made it, per `.claude/rules/production-readiness.md` |

The current state stays on the record so the screen and the access rules stay simple. The history table is what makes transitions countable, and the two must be written in the same transaction so they cannot disagree.

Duration in a state is derived from consecutive transitions. Do not store a "days in stage" number that goes stale the moment nothing updates it.

## Attempt And Event Logs

One row per attempt at a step: an enrichment call, a verification, an import of one record, a send, a sync.

| Column | Holds |
| --- | --- |
| entity key | The record the step ran against |
| step code | Which step, from a codified set, so one table can serve several steps |
| attempt number | Which try this was for that record and step |
| started at, ended at | The endpoints, so duration is derived |
| outcome code | succeeded, failed, skipped, partial, from a codified set |
| reason code | The coded failure or skip reason, from a codified set |
| actor or provider | Who or what performed it, keyed where it is a controlled set |
| result reference | A pointer to the payload or evidence, not the payload pasted into a reporting column |
| cost and usage | What the attempt cost, in exact decimal, with its unit, where cost is a question |

This is the table that answers "how many failed to enrich". Without it, the record shows only that it is not enriched, which conflates never attempted, attempted and failed, and attempted and skipped. Those are three different answers to three different questions.

When the step calls an AI model, the per-call cost and token usage come from the catalog result per `.claude/rules/ai-model-catalog.md`, and are stored with the attempt so historical cost stays what it actually was rather than being recomputed from today's price.

### Failure Is A Taxonomy, Not A String

- Keep failure reasons as a codified reference set, so they group. Raw provider messages vary by whitespace, id, and wording, and produce a report with four hundred distinct one-row reasons.
- Keep the raw message too, in its own column or an evidence reference, for debugging. The code is what reports read; the message is what a developer reads.
- Distinguish retryable from permanent on the reason set itself, so "failures worth retrying" is a filter rather than a judgement call in each query.
- Distinguish our failure from their failure (validation, timeout, provider error, quota, bad input), because the fix differs and the report is the thing that shows which is growing.

## Versioned Attribute History

Use when a confirmed question needs an attribute's value as at a past date: which owner held it then, which business unit it belonged to, which tier the customer was on.

| Column | Holds |
| --- | --- |
| entity key | The record the value belongs to |
| attribute value | The value for this period, keyed where it is a reference set |
| valid from, valid to | The period the value held, timezone-aware, with an open end for the current row |
| current flag | Whether this is the row in force now |

Two rules keep this from becoming a burden:

- Version only the attributes a confirmed question reads historically. Versioning every column is gold-plating and doubles the write path for no answer.
- Never leave two current rows for one entity. The uniqueness that prevents it belongs in the structure, not in the application code that writes it.

## Periodic Snapshots

Use when the question is about the mix over time and transitions do not already provide it, or when the measured value has no event to hang it on (a computed quality score, a balance, a backlog size).

The period is part of the grain ("one row per contact per month end"). Measures on a snapshot are semi-additive: they sum across contacts and owners, never across periods. Retention and frequency are confirmed facts, per `.claude/rules/data-governance.md`, because snapshots grow with the entity count multiplied by the period count.

## Identity, Recurrence, And Merges

"How many are recurring" needs identity resolution stored as data. Three pieces:

- A match key on the record: the normalized value the matching runs on (a lowercased trimmed email, a normalized phone, a hash of a composite), stored as its own column so matching is repeatable and reportable.
- A link table: one row per matched pair, holding the surviving record, the duplicate, the match method, the confidence or score, when it was matched, and who or what confirmed it. Grain: one row per pair.
- Occurrence counters where a repeat is legitimately the same record arriving again: first seen at, last seen at, occurrence count on the record, incremented as part of the same transaction that records the occurrence row.

Decide and record whether a repeat arrival under the same match key becomes a new identity row or increments the existing one, because that choice changes every count taken afterwards. A new row inflates volume; a counter deflates it. Neither is wrong, and only one can be true per feature. The link table is needed either way, for the identities that are only matched after the fact.

Never resolve a duplicate by deleting the loser silently. Keep it, mark it merged, and point it at the survivor. How many duplicates were found, by which method, at what confidence, is one of the answers the question set asked for.

## Soft Delete And Retention

- Deleting is a state change: a deleted-at timestamp, the actor, and a coded reason, with the row kept and filtered out by the application. Restores are the same, recorded rather than assumed.
- History, attempt, and snapshot tables often outlive the records they describe and are usually the largest tables the feature has. Their retention, classification, and purge schedule are confirmed facts per `.claude/rules/data-governance.md`. Ask; do not assume they are kept forever, and do not assume they may be pruned.
- Never place PII in an attempt's evidence column or a history row that does not need it, and never copy production row data into a document, per `.claude/rules/secret-handling.md`.

## Never

- Overwrite a status, stage, owner, or score that a question asks about over time.
- Record an attempt's failure only in a log file, or keep failure reasons as raw strings.
- Conflate never attempted with attempted and failed.
- Store a duration or a days-in-stage number instead of the endpoints it comes from.
- Version every attribute, or leave two current rows for one entity.
- Delete a duplicate rather than linking it, or drop the match method and confidence.
- Hard delete a row that has been reported on.
- Add a snapshot, a history table, or an attempt log that no confirmed question needs.
