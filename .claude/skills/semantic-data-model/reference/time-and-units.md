# Time And Units

Almost every analytical question has a time axis and a unit. Both are places where a wrong answer looks completely normal: a count by day that is off by one day's boundary, a total that mixes minutes with hours, a cost that drifted because it was recomputed at today's price.

Column names here are concepts. Map them to the project's confirmed naming convention, and get the real names through the Schema MCP workflow in `.claude/rules/schema-mcp.md`.

## Three Different Times

| Time | Means | Used for |
| --- | --- | --- |
| Business event time | When the thing actually happened | Every analytical question about when |
| Audit time | When this row was created or last written | Debugging, ordering of writes, change tracking |
| Load or capture time | When the system received or imported it | Latency between happening and knowing |

Keep them separate columns. The audit column that says when the row was last written is not when the thing happened, and using it as the business date makes a report change every time an unrelated field is edited.

Where a record arrives late (an import, a backfill, a device that was offline), both the event time and the capture time matter, and "how many arrived yesterday" is a different question from "how many happened yesterday". Ask which one the confirmed question means.

## The Primary Business Date

Name one date per fact as the primary business date, the one a report defaults to when it says "last month". Name the role of every other date on the row explicitly: created, first contacted, enriched, converted, closed, cancelled.

This matters because a model relates dates deliberately. A contact fact with created, enriched, and converted dates supports three different "last month" questions, and whoever builds the model has to know which is the default and what the others mean. Record it in the hand-off per `erd-and-handoff.md`.

Where a question compares two of them (time from arrival to conversion), both endpoints must be on a row that can be read together, or reachable by one join.

## Time Zone And Day Boundary

- Store timezone-aware values in the project's confirmed storage convention, and record it. A naive local timestamp is ambiguous twice a year and unusable across regions.
- The reporting time zone is a confirmed fact, separate from the storage convention. A count grouped by day in the wrong zone moves rows between days at the edges, which is small enough to pass review and large enough to make two reports disagree forever.
- The business-day boundary, the first day of the week, and the fiscal year start are confirmed facts too. Do not assume midnight, Monday, or January.
- Never convert a stored event time to a fixed zone in the write path "to make reporting easier". It loses information and forces a second correction later.

## A Date Reference Set

A date dimension (one row per calendar date, carrying month, quarter, year, fiscal period, week number, weekday, and working-day flags) is what lets a model answer "by fiscal quarter" and "working days only" without recomputing calendar logic in every query.

Add one when a confirmed question needs a calendar concept the raw date does not carry (fiscal periods, working days, week semantics, holiday exclusion), and when the project confirms it, sharing one across features rather than per feature. Otherwise a real date or timestamp column is enough, and adding a date dimension nobody asked for is the same gold-plating this skill forbids everywhere else.

Whichever way, the date columns are real date or timestamp types. A date stored as text cannot be ordered, filtered by range, or rolled up.

## Durations

- Store the two endpoints and derive the duration. A stored elapsed number goes stale, disagrees with its endpoints after any correction, and cannot be re-cut by a different boundary.
- State the unit in the hand-off when a duration is exposed as a measure, and use one unit per measure. Seconds and minutes in the same column is a permanent source of wrong averages.
- Where a clock legitimately pauses (a business-hours service target, a waiting-on-customer state), the pauses are rows, per the state history shape in `history-and-outcomes.md`. Duration is then the sum of the running segments, not end minus start.
- Store an average duration nowhere. It is a non-additive measure computed from endpoints, per `measures-and-dimensions.md`.

## Money

- Exact decimal or minor units, never floating point, per `.claude/rules/api-design.md`.
- Where more than one currency can occur, the currency code sits beside the amount, and any converted amount is its own column recorded with the rate and the rate date used at the time. Never recompute a historical amount at today's rate; that silently rewrites what a past period cost.
- A per-event cost is captured with the event, so a report of last quarter's spend reproduces last quarter's numbers. This is the same rule the AI model catalog applies to per-call cost in `.claude/rules/ai-model-catalog.md`.
- Report unknown rather than zero when a cost is genuinely unknown. Zero claims it was free.

## Other Units

- Give every numeric measure a unit in the hand-off: count, minutes, bytes, records, currency.
- Store a percentage as its numerator and denominator, per `measures-and-dimensions.md`. A stored percentage cannot be re-aggregated and cannot be checked.
- Keep a score's scale and its rule version beside it. A score of 72 means nothing next to a score of 0.72 produced by a different version of the rules.

## Never

- Use an audit timestamp as the business event date.
- Store a naive local timestamp, or convert away the original event time in the write path.
- Group by day without applying the confirmed reporting time zone and day boundary.
- Assume a week start, a fiscal year, or a working-day calendar.
- Store a date as text, or a duration without its endpoints.
- Mix units in one column, or expose a numeric measure with no stated unit.
- Use floating point for money, drop the currency code where more than one is possible, or recompute a historical amount at a current rate.
- Add a date dimension no confirmed question needs.
