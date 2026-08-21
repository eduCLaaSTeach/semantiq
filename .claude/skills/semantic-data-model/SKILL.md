---
name: semantic-data-model
description: Designs persisted data structures so the feature's own analytics are answerable from them and the same structure lifts into a semantic model - the analytics question set, one declared grain per table, atomic measures, codified and shared dimensions, state and outcome history, identity resolution, and the ERD plus semantic hand-off. Use when designing or changing tables, proposing new storage, planning a data model or ERD, or when the user mentions analytics, reporting, semantic model, BI, measures, dimensions, KPIs, or "how many" questions about a feature.
---

# Semantic Data Model Skill

Designs the feature's tables so the feature's numbers can be reported without a redesign. Same tables, same transactional job, one extra input: the questions the business will ask of this data, agreed before the shape is proposed.

These are the approved rules for data-structure design in any project that persists data. Use them by default. Deviate only when the developer explicitly asks, recorded as a documented exception in `.claude/PROJECT-CONTEXT.md`.

The binding rule is `.claude/rules/semantic-data-model.md`. Verification and the proposal workflow stay in `.claude/rules/schema-mcp.md`; classification, retention, and privacy stay in `.claude/rules/data-governance.md`. This skill is the concrete shape those rules expect.

## How To Use This Skill (Load Only What You Need)

1. Read this file for the enforced rules and the shape of the pattern. It is deliberately small.
2. Open only the reference file the current task needs. Each one is detailed; most turns need one or two. Never read the whole `reference/` folder.
3. Run the question set past the developer before proposing any shape, and run the Delivery Checklist at the foot of this file before calling the work done.

Every rule is tagged ENFORCED (follow exactly) or PRINCIPLED (a sensible default, deviation allowed with written justification).

## Reference Index - Open Only The File You Need

| Reference file | Use when |
| --- | --- |
| `reference/question-set.md` | Eliciting, structuring, and confirming the questions a feature's data must answer |
| `reference/grain-and-keys.md` | Declaring what one row means, and giving it keys that join cleanly |
| `reference/measures-and-dimensions.md` | Deciding what is a measure, what is a dimension, and how each is stored |
| `reference/history-and-outcomes.md` | Capturing state changes, attempts, failures, duplicates, and merges |
| `reference/time-and-units.md` | Business dates, date roles, time zones, durations, money, and units |
| `reference/erd-and-handoff.md` | Producing the data structure, entity relationships, ERD, and semantic hand-off |
| `reference/worked-example-contacts.md` | One feature carried end to end, from questions to hand-off |

## Why The Pattern Exists

A screen needs the current state of a record, so a screen-shaped table stores the current state and overwrites it. Every analytical question is about something else: how many arrived, from where, how many failed, how long a step took, how many came back. Those are counted across rows and across time.

That difference is not a query problem, it is a capture problem. If the source was never recorded as a code, no query can group by source. If the status was overwritten, no query can count last month's transitions. If the failed enrichment attempt was only logged, no query can report a failure rate. The report is not late, the data is gone.

So the design changes at one point only: the questions are agreed first, and each one is traced to the row, column, or reference set that answers it. Everything else in this skill is how to do that without over-building.

## The Enforced Rules

- Questions before shape (ENFORCED). The confirmed analytics question set exists before a shape is proposed, and every question is traced to what answers it. See `reference/question-set.md`.
- One declared grain per table (ENFORCED). One sentence saying what a single row means, per table, carried into the proposal and the table dictionary. Mixed grain double counts. See `reference/grain-and-keys.md`.
- Atomic rows, derived aggregates (ENFORCED). Store the row; compute the total. A stored count that replaces its rows cannot be re-cut. Store a ratio's parts, never only the ratio. See `reference/measures-and-dimensions.md`.
- Codified, shared dimensions (ENFORCED). Anything a question groups by is a stable code plus a display label in a reference set, reused from the platform's existing set where one exists, never free text. See `reference/measures-and-dimensions.md`.
- History as rows (ENFORCED). Where a question asks how many, how long, or in what order, the change is a row, not an overwrite. Versioned attribute history only where a question reads the past value. See `reference/history-and-outcomes.md`.
- Outcomes and failures as rows (ENFORCED). Every counted attempt carries its outcome and a coded reason. A log line is not a measure. See `reference/history-and-outcomes.md`.
- Identity resolution as data (ENFORCED). Recurrence, duplicate, and merge questions need a match key and a survivor-to-duplicate link with the method and confidence. See `reference/history-and-outcomes.md`.
- Explicit business time (ENFORCED). A timezone-aware event time distinct from audit columns, one named primary business date per fact, a named role for every other date. See `reference/time-and-units.md`.
- Keys that join (ENFORCED). One stable single-column surrogate key per row, no meaning parsed out of a key, a real non-null foreign key to every dimension a question filters by, and an explicit bridge for a true many-to-many. See `reference/grain-and-keys.md`.
- Proportionate design (ENFORCED). The question set sizes the structure. No date dimension, versioned history, event table, or star schema that no confirmed question needs.
- Deliverables shipped (ENFORCED). Data structure, entity relationships, ERD, and semantic hand-off, as metadata only, through the knowledge-base gate. See `reference/erd-and-handoff.md`.
- Star-friendly shape (PRINCIPLED). Prefer a fact referencing its dimensions directly over a chain of joins to reach a filter. Declare it when the project's normalization makes a chain the right call.

## The Responsibility Boundary

Two things are being built, with two owners, and confusing them is what produces both a bloated application and an unusable model.

The application owns the atomic truth: the rows, the codes, the timestamps, the outcomes, the keys, and the relationships. It records what happened, once, at the finest grain the questions need.

The semantic model owns presentation of that truth: aggregation, hierarchies, business-friendly naming, the calculations built on the measures, and the report. It is built by whoever owns reporting, from the documented structure.

The hand-off point is the documented grain plus the measure and dimension roles in `reference/erd-and-handoff.md`. The application does not pre-aggregate for the model, and the model does not invent a measure the rows cannot support. When a wanted measure has no rows behind it, that is a data-structure gap to raise, not a formula to fake.

## Confirmed, Never Assumed

The question set is confirmed with the developer. So is everything about how the data will be consumed: whether a semantic model or BI tool exists at all, who owns it, whether reporting reads the operational tables or a replica, the reporting time zone and business-day boundary, the key and naming conventions, which conformed dimensions the platform already has, how long history and event rows are kept, and where the ERD and data-model write-up live. Record each in `.claude/PROJECT-CONTEXT.md` under Analytics And Semantic Model, and ask when one is unset.

Table and column names in the reference files are concepts, not identifiers. Map them to the project's confirmed naming convention, and get the real names through the Schema MCP workflow in `.claude/rules/schema-mcp.md`. Search for an existing table for the concept before proposing a new one, show the developer the exact shape with its grain and question set, get explicit confirmation, then propose. Never DDL.

## Global Anti-Patterns (ENFORCED - Never)

- Propose a shape before the analytics questions are confirmed, or answer a question the developer never asked for.
- Leave a table's grain undeclared, or mix two grains in one table.
- Overwrite a status, stage, owner, or score that a question asks about over time.
- Store a count, total, or percentage as the only record of the rows behind it, or store a rate without its numerator and denominator.
- Store a value a question groups by as free text, as a display label, or as a duplicated string on every row.
- Invent a private source, status, type, or reason list when the platform already has that dimension.
- Leave a dimension key nullable where the question counts rows, so an inner join drops them.
- Record an attempt's failure only in a log file, or keep failure reasons as raw message strings.
- Hard delete a record a report has already counted.
- Pack several facts into one composite key string, or reuse a key after deletion.
- Model a many-to-many as a delimited column or a JSON array, or hide an analytic attribute inside a JSON blob when a question groups by it.
- Use money as floating point, or store a duration without its endpoints and its unit.
- Use an audit timestamp as the business event date, or count by day without the confirmed reporting time zone.
- Build a warehouse, cube, extract job, second reporting store, or dashboard that was not requested and approved.
- Put row data, customer records, or PII into the ERD, the data-model write-up, or the table dictionary.

## Delivery Checklist

- [ ] The analytics question set is written, confirmed by the developer, and each question is traced to the table, column, or reference set that answers it, with any unanswered question stated.
- [ ] Every table touched has one declared grain in one sentence, recorded in the proposal and the table dictionary.
- [ ] Measures are stored atomic with an exact type and a named unit; every rate carries its numerator and denominator.
- [ ] Every grouped-by value is a codified reference set with a stable code, a display label, a display order, and an active flag; existing platform dimensions were searched first and reused where they fit.
- [ ] Reserved unknown members exist where a question counts rows, and every dimension key a question filters by is non-null with a real foreign key.
- [ ] State changes, attempts, outcomes, coded failure reasons, and merges are rows; nothing a question counts is only overwritten or only logged.
- [ ] Attribute history is versioned only where a confirmed question reads a past value, and soft delete is in place.
- [ ] Every fact carries a timezone-aware business event time, one named primary business date, and a named role for each additional date; the confirmed reporting time zone is applied.
- [ ] Keys are single-column, stable, meaning-free, and never reused; a true many-to-many has an explicit bridge with its own grain.
- [ ] Scope columns a report filters by (owner, team or business unit, tenant) sit on the fact.
- [ ] The structure adds nothing the confirmed questions do not need, and any larger structure was raised and approved.
- [ ] The deliverables are produced and placed: data structure, entity relationships with cardinality, the ERD, and the semantic hand-off with one agreed definition per measure.
- [ ] Every name was verified live through Schema MCP this session, the reuse-first search ran before any new storage, and the shape plus grain plus question set was confirmed before proposing.
- [ ] No row data, customer record, or PII appears in any document produced.

## Deviating From A Principled Default

State the standard pattern, the proposed deviation, the rationale in two or three sentences, the domain context, and the trade-offs acknowledged. Get sign-off before building. Anything ENFORCED is not deviable, and an authorized deviation is recorded as a documented exception in `.claude/PROJECT-CONTEXT.md`, never applied silently.
