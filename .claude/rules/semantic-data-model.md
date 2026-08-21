# Semantic Data Model Rules

Claude must design every persisted data structure so the feature's own analytics are answerable from it, and so the same structure lifts into a semantic model without a redesign. A shape that serves the screen but cannot answer how many, from where, how complete, how often, and how long is unfinished, not minimal.

Conditional: applies whenever a change designs, adds, alters, or documents persisted data, so it fires with gate D and alongside `.claude/rules/data-governance.md`. It does not override `.claude/rules/secret-handling.md`, `.claude/rules/schema-mcp.md`, `.claude/rules/production-readiness.md`, or the DDL restrictions.

The concrete shape is the bundled `semantic-data-model` skill at `.claude/skills/semantic-data-model/`: the analytics question set, grain and key rules, measures and dimensions, history and outcome capture, the ERD and semantic hand-off, and a worked example. This rule is the authority; that skill is how it is implemented. Load `SKILL.md` first, then only the `reference/` file the task needs.

Verification and the proposal workflow stay in `.claude/rules/schema-mcp.md`. Classification, retention, and privacy stay in `.claude/rules/data-governance.md`. This rule owns whether the approved shape can be reported on; cross-reference rather than duplicate.

## Assume No Analytics Stack

- Do not assume the project has a semantic model, a BI tool, a warehouse, a read replica, or any reporting layer. Confirm from `.claude/PROJECT-CONTEXT.md` or ask.
- Do not assume or mandate any modeling product, model format, warehouse, transformation tool, diagram tool, reporting time zone, key convention, or history-retention period. Each is a confirmed fact; ask when unknown and record the answer.
- Analytics readiness is a property of the transactional design, not a second system. Do not introduce a warehouse, a cube, an extract job, a duplicate reporting store, or a BI dependency to obtain it, per `.claude/rules/enterprise-governance.md`. Propose it and get approval, or design the tables so it is not needed yet.

## The Questions Come Before The Shape

- Write the feature's analytics question set before proposing any shape, and get the developer to confirm it. The questions are in business words: how many records arrived, from which source, how complete or reliable they are, how many passed a step, how many failed it and why, how many are recurring, how long each step took, and who owns them.
- Carry every confirmed question into the proposal with the table, column, or row that answers it. A question with nothing behind it is a gap to raise, not a detail to leave for later.
- Show the question set beside the proposed shape in the Schema MCP preview, so the developer approves both together.
- Do not invent questions to look thorough, and do not silently drop one. Record the confirmed set and state plainly which questions the shape does not answer yet.

## One Declared Grain Per Table

- State what one row means, in one sentence, for every table proposed or changed. That sentence is the grain, and it belongs in the proposal and in the table dictionary.
- Keep one grain per table. A table whose row is a document in some rows and a line item in others double counts every measure taken from it.
- Keep header-level and line-level facts in separate tables, each with its own grain, rather than one wide table carrying both.
- Declare the grain of every bridge, history, and event table too. An undeclared grain is the single most common cause of a wrong number in a report.
- A draft or in-progress row is not a completed record, and counting the two together silently inflates every number a question asks for. Keep them in separate tables, or separate them with one codified state that every count, export, and report filters on, and say which in the grain sentence. A screen may still show a user their own in-progress row, per `.claude/rules/ui-ux-quality.md`; a measure may not count it. Where a confirmed question asks how many were started, abandoned, or completed, the draft's step reached and its outcome are rows, per Outcomes And Failures Are Data below.

## Measures Stay Atomic

- Store the atomic row and derive the aggregate. A stored count, total, or rate that is the only record of the rows behind it cannot be re-cut by source, by month, or by owner, which is what every analytical question asks for.
- Store a ratio's numerator and denominator, never only the ratio. Percentages do not aggregate, and an average of averages is wrong.
- Give every measure an exact type and a named unit: money in exact decimal or minor units and never floating point, duration in one named unit, a count as an integer.
- Keep a materialized aggregate only when the developer confirms it is needed, alongside the atomic rows it was computed from, never instead of them, and record how it is rebuilt.

## Dimensions Are Codified And Shared

- Anything a question groups by is a codified reference with a stable code and a separate display label, never free text and never a display string stored on the row. "Which source" is answerable only when source is one controlled set.
- Search for the platform's existing dimension before adding one, through the reuse-first workflow in `.claude/rules/schema-mcp.md`. A second private source or status list is what makes cross-module reporting impossible later.
- Give each reference set a stable code that never changes meaning, a display label that may change, an explicit display order, and an active flag so a retired member still resolves on historical rows.
- Where a question counts rows, represent missing or not applicable as a reserved member of the reference set rather than a null key, so a join does not silently drop the rows that matter most.

## History Is Not Overwritten

- An in-place update destroys the answer to every question about change over time. Where a confirmed question asks how many, how long, or in what order, record the change as its own row: the new state, when it started, what caused it, and who or what did it.
- Version an attribute's history (valid-from, valid-to, current flag) only for the attributes the confirmed questions need to read as of a past date. Versioning every column is gold-plating, per `.claude/rules/enterprise-governance.md`.
- Delete softly, per the recycle-bin default in `.claude/rules/ui-ux-quality.md`. A hard delete rewrites last month's reported numbers with no trace.
- History and event rows have their own confirmed retention and classification, per `.claude/rules/data-governance.md`. Ask rather than assume they are kept forever.

## Outcomes And Failures Are Data

- Every attempted step a question counts is a row carrying its outcome: succeeded, failed, skipped, partial, with a coded reason. A step that records its failure only to a log file cannot report a failure rate.
- Record what a question needs to explain the outcome: the attempt number, the actor or provider, the start and end, and the coded error class rather than a raw message string alone.
- A log line is not a measure. Logs rotate, are shaped for humans, and are not queryable beside the record they describe.
- Keep the outcome codes a codified reference set like any other dimension, so failure reasons group instead of accumulating as prose variants.

## Identity And Recurrence Are Modeled

- Questions about recurring, duplicate, or returning records need identity resolution stored as data: a stable match key, and a link between the surviving record and the duplicate carrying the match method and its confidence.
- Record the merge as a row rather than deleting the loser silently. How many duplicates were found, by which method, is itself one of the answers.
- Where a record can legitimately arrive more than once, decide and record whether a repeat is a new row or an incremented occurrence on the existing one, because that decision changes every count taken afterwards.

## Time Is Explicit

- Every fact carries the business event time, timezone-aware, distinct from the row's audit timestamps. The audit column that says when the row was last written is not when the thing happened.
- Name one primary business date per fact, and name the role of every additional date on it (created, enriched, converted, closed), so a model can relate each one deliberately instead of guessing.
- The reporting time zone and the business-day boundary are confirmed facts. A count by day is wrong in a predictable, hard-to-find way when they are assumed.
- Where duration is a question, store the two endpoints and let duration be derived, rather than storing only an elapsed number.

## Keys Join Cleanly

- Give every row one stable surrogate key, one column wide, never reused after deletion. Business and natural keys live in their own columns beside it.
- Never parse meaning out of a key, and never pack several facts into one composite string. A source, a year, and a sequence inside one identifier are three columns.
- Have each fact reference every dimension it filters by directly, by key, with a real foreign key and a non-null value using the reserved unknown member.
- Model a genuine many-to-many as an explicit bridge table with its own declared grain, rather than a repeated column, a delimited list, or a JSON array that no join can reach.
- Keep the scope columns a report must filter by on the fact itself (owner, team or business unit, tenant), so record-level filtering in a model matches the application's own access scope.

## Proportionate, Not Gold-Plated

- The confirmed question set sizes the design. Three questions get three answers. Do not add a date dimension, an event table per column, versioned history on every attribute, or a star schema nobody asked for, per the ceremony limit in `.claude/rules/enterprise-governance.md`.
- Do not build the semantic model, the reports, the pipeline, or the dashboards as part of feature work unless that was the request. The deliverable here is a structure that supports them.
- When the questions genuinely warrant a larger structure, raise it with the developer and get approval rather than building it unasked.

## The Deliverables

A change that persists data carries these, as metadata only and never as row data:

- the confirmed analytics question set, and which questions the shape answers;
- the complete data structure: tables, columns, types, nullability, defaults, keys, foreign keys, and indexes, every name verified live through Schema MCP;
- the entity relationships: parent, child, cardinality, optionality, and the join key;
- an ERD in the project's confirmed diagram location and format;
- the semantic hand-off: each table's grain; each numeric column's role, unit, default aggregation, and additivity; each attribute's role as a dimension attribute and the hierarchy it sits in; and one agreed definition in words for every measure.

Durable write-ups land through the approval gate in `.claude/rules/knowledge-base.md`, and metadata describing an actual schema change travels in the same change unit as that change.

## Governed Through Schema MCP

Nothing in this rule relaxes a schema guarantee. Verify every name live in the current session, run the reuse-first search before proposing storage (including for an existing dimension, history, or event table that already fits), show the developer the exact shape together with its grain and question set, get explicit confirmation, then propose. Never write DDL, and never name a table or column that Schema MCP has not confirmed.

## Final Reporting

For data-model work, report: the confirmed analytics question set, which questions the shape answers, and any it does not; the declared grain of every table touched; the measures with their units, additivity, and where the atomic rows live; the dimensions reused or added, and which existing platform dimension was checked first; the history, event, outcome, and identity capture added; the primary business date and the reporting time zone applied; the key and foreign-key shape, including how unknown members are handled; the deliverables produced (data structure, entity relationships, ERD, semantic hand-off) and where they landed; the Schema MCP tools used and any proposal IDs; confirmation that no row data or PII entered the documentation.

Final rule: if the analytics questions, the semantic-model consumer, the reporting time zone, the key convention, the platform's existing dimensions, or the history-retention expectation is unclear, do not guess. Ask and record the confirmed values first.
