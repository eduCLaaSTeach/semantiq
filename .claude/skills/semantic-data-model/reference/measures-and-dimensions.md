# Measures And Dimensions

Every column a report touches plays one of three parts: something counted or summed (a measure), something grouped or filtered by (a dimension attribute), or something shown but never aggregated (a reference the row carries). Deciding the part while the table is designed is what makes the structure usable by a semantic model.

Table and column names here are concepts. Map them to the project's confirmed naming convention, and get the real names through the Schema MCP workflow in `.claude/rules/schema-mcp.md`.

## Deciding The Part

| Part | Test | Stored as |
| --- | --- | --- |
| Measure | Would a report add it up, average it, or count it | An exact numeric column, or the row itself when the count is the measure |
| Dimension attribute | Would a report group or filter by it | A key to a codified reference set, or a typed attribute on a dimension |
| Row reference | Shown to a human, never grouped or summed | A column on the fact |

The count of rows is a measure. A fact table with no numeric column at all is perfectly normal: one row per contact answers "how many contacts", and one row per attempt answers "how many attempts", with no counter column anywhere. Prefer that over an incremented tally.

## Measures

- Store the atomic value, at the grain the questions need, and let every total, average, and rate be computed from it. A stored aggregate that replaces its rows cannot be re-cut by source, month, or owner, which is the whole point of the question set.
- Declare each measure's unit and default aggregation. A number with no stated unit gets summed as currency in one report and as minutes in another.
- Use exact types. Money is exact decimal or minor units, never floating point, per the serialization rule in `.claude/rules/api-design.md`. Counts are integers. Durations are derived from their endpoints, per `time-and-units.md`.
- Store a rate's numerator and denominator, never only the rate. Percentages do not add up, and averaging them across groups gives a number that is wrong and looks plausible. An enrichment success rate is stored as attempts and successes; the model divides.
- Score columns follow the same rule: keep the inputs the score came from, plus the version of the rule that produced it, so a score can be explained and recomputed rather than trusted blindly.

### Additivity

Declare each measure's additivity in the hand-off, because a model aggregates on that declaration.

| Additivity | Meaning | Example |
| --- | --- | --- |
| Additive | Sums correctly across every dimension including time | Contacts received, enrichment attempts, cost |
| Semi-additive | Sums across everything except time, where it is a period-end or average | Open records at a date, balance, headcount |
| Non-additive | Cannot be summed at all; store its parts | Rate, ratio, percentage, average score |

A semi-additive measure needs a snapshot grain to be meaningful. A non-additive one is never the only record of what it came from: store the parts and let the model calculate it. Storing the computed value beside its parts is fine, and its hand-off row is what says not to sum it.

### Materialized Aggregates

Keep one only when the developer confirms it is needed for a real performance reason, and then:

- Keep the atomic rows it was computed from. The aggregate is a cache, never the record.
- Record how it is rebuilt and when, so a wrong number can be corrected rather than argued about.
- Keep it out of the hand-off as a source of truth. The model reads it for speed, and the atomic rows define what it means.

## Dimensions

A dimension is a controlled set of members that rows point at. It is what makes "which source", "which status", "which failure reason" answerable at all.

Every reference set carries:

| Column | Holds |
| --- | --- |
| key | The surrogate key rows point at |
| code | A stable code that never changes meaning, used by logic and by reports |
| label | The display text, which may be renamed without breaking anything |
| display order | The order the set is shown in, so every screen and report sorts alike |
| active flag | Whether the member may be chosen now; retired members still resolve on historical rows |
| description | What the member means, in one line, so two people count it the same way |

Rules:

- Never store the label on the fact instead of the key. Renaming "Web Form" to "Website" then rewrites history, or worse, splits one source into two in every report.
- Never store a grouped-by value as free text. "Web Form", "web form", and "WebForm" are three sources to a report and one source to a human.
- Retire a member with the active flag rather than deleting it. Deleting orphans every historical row that pointed at it.
- Add a new member to the existing set rather than starting a private set beside it.

### Reserved Members

Where a question counts rows, the dimension carries explicit members for the cases that would otherwise be null:

- unknown: the value was not supplied, and that is itself an answer ("how many contacts arrived with no source").
- not applicable: the value cannot apply to this row.
- other: a real value outside the controlled set, used sparingly and reviewed, because a growing "other" is a sign the set needs a new member.

The fact's key then stays non-null and no inner join drops the rows a question is about.

### Conformed Dimensions Are The Point

A dimension used by more than one feature is conformed: one set, one code list, one meaning, shared. Source, status, country, currency, business unit, owner, and reason are the usual candidates.

- Search for the platform's existing set before adding one, through the reuse-first workflow in `.claude/rules/schema-mcp.md`. A second private source list is what makes "contacts and leads by source" impossible to produce later, and it cannot be merged retroactively without touching every row.
- When the existing set lacks a member the feature needs, propose adding the member, not a new set.
- When the existing set genuinely does not fit (different meaning, different owner, different lifecycle), say so explicitly in the proposal and name the difference, rather than quietly cloning it.

### Hierarchies

Declare the parent-child relationships a report will roll up through, so a model can build them instead of guessing: date to month to quarter to year, user to team to business unit to organization, city to region to country, product to category. Declare the level order and whether a level may be skipped. A hierarchy declared in the hand-off costs nothing; one discovered by the reporting owner costs a rebuild.

## Snapshots For Mix Questions

"How many sit in each state right now" is answered from the current record. "How the mix moved over the last six months" is not, unless either every transition is a row (see `history-and-outcomes.md`) or a periodic snapshot is captured.

Add a snapshot only when a confirmed question asks for the mix over time and transitions do not already give it. Its grain includes the period ("one row per contact per month end"), its measures are semi-additive, and its retention is a confirmed fact.

## Never

- Store a count, total, or rate as the only record of the rows behind it.
- Store a percentage, ratio, or average without its parts.
- Use floating point for money, or a numeric column with no stated unit.
- Store a grouped-by value as free text, as a display label, or as a duplicated string on every row.
- Leave a dimension key nullable where a question counts rows, instead of using a reserved member.
- Clone a platform dimension into a private list, or delete a dimension member that historical rows point at.
- Hide an attribute a question groups by inside a JSON blob. Promote it to a typed column and keep the blob as evidence only.
- Present a materialized aggregate as the source of truth, or ship one nobody asked for.
