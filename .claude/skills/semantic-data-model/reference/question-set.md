# The Analytics Question Set

The question set is a short, confirmed list of the questions the business will ask of a feature's data, written before the table shape is proposed. It is the one input that turns an ordinary transactional design into one that can be reported on.

It is agreed with the developer, not invented. Claude walks the families below to make sure nothing obvious is missed, proposes a draft list, and asks the developer to confirm, cut, or add. What comes back is the confirmed set, and it sizes everything after it.

## Ask For It Like This

> These are the questions I expect this feature's data to answer. Confirm the ones that matter, cut the ones that do not, and add anything I have missed. Which of these are needed from day one, and which are later? I will design the tables against the confirmed list and tell you which questions the shape cannot answer.

Two answers matter beyond the list itself: which questions are day-one (they shape the structure now) and which are later (they are recorded so the structure does not block them). A question nobody will ever ask is not a reason to add a table.

## The Question Families

Walk these ten families. Most features touch five or six. Each family demands something specific from the structure, and the third column is what makes the question answerable at all.

| Family | Question shape | What the structure must carry |
| --- | --- | --- |
| Volume | How many arrived, in a period, and is that rising or falling | One row per arrival at the finest grain, with a timezone-aware business event time |
| Origin and attribution | Which source, channel, campaign, partner, or import they came from | A codified source reference on the row, reused from the platform's dimension |
| Completeness and reliability | How complete, valid, or trustworthy the records are | The scored or flagged result stored as data, with the rule version that produced it |
| Process outcome | How many passed a step: enriched, verified, approved, converted | One row per attempt or transition with a coded outcome, not just a current flag |
| Failure | How many failed, and why they failed | A coded failure reason set beside the outcome, not a raw message and not a log line |
| Duration and velocity | How long a step took, how long a record sat in a stage | Both endpoints stored, so duration is derived rather than asserted |
| Recurrence and identity | How many are repeats, duplicates, or returning records | A stable match key plus a survivor-to-duplicate link with the method and confidence |
| Lifecycle and current mix | How many sit in each state right now, and how the mix moved | Current state on the record plus the transitions as rows, or a periodic snapshot |
| Ownership and scope | How many per owner, team, business unit, or tenant | Scope keys on the fact itself, matching the application's access scope |
| Cost and effort | What it cost to acquire, enrich, or process one record | The per-event cost captured with the event, in exact decimal, with its unit |

## Trace Every Question

The confirmed set becomes a trace table, carried in the proposal, shown beside the shape in the Schema MCP preview, and kept in the data-model write-up. It is the artifact that proves the design answers the questions rather than claiming to.

| Question | Answered by | Counted at | Status |
| --- | --- | --- | --- |
| How many contacts arrived per source last month | contact row plus its source reference | one row per contact | answered |
| How many failed to enrich, and why | enrichment attempt rows with outcome and reason codes | one row per attempt | answered |
| How long enrichment takes end to end | attempt start and end timestamps | one row per attempt | answered |
| How many contacts are recurring | duplicate link rows with match method | one row per matched pair | answered |
| Which campaign produced the best-converting contacts | no campaign reference on the contact today | not captured | gap, raised |

Four rules for the trace:

- Every confirmed question gets a row. No silent drops.
- "Counted at" is the grain the number comes from, and it must match a declared table grain from `grain-and-keys.md`.
- A question with nothing behind it is recorded as a gap and raised with the developer, with the missing capture named. The developer decides whether to add it now, defer it, or drop the question.
- A question answered only by reading a log file, a JSON blob, or an external system is a gap, not an answer.

## Sizing The Set

Five to fifteen questions is the useful range for one feature. Beyond that the list usually contains report variants rather than distinct questions: "by source", "by source and month", and "by source, month, and owner" are one question, because one codified source reference plus a business date answers all three.

Compress variants into the underlying question, then design for that. The point of the set is coverage of what must be captured, not a catalogue of every future report.

## Keep It Proportionate

The set sizes the structure, in both directions. A feature with three confirmed questions gets three answers and no speculative event table. A feature whose questions all concern process outcome and failure earns an attempt table, and nothing else earns one.

Do not add a family's structure because the family exists. Volume needs no event table when the record itself is the arrival. Duration needs no history table when both endpoints sit on the record. Reach for the heavier shapes in `history-and-outcomes.md` only when a confirmed question asks for change over time.

## Never

- Propose a shape before the set is confirmed.
- Invent questions to look thorough, or pad the list with families the developer cut.
- Answer a question the developer did not ask for, then present the extra tables as required.
- Record a question as answered when the answer needs a log file, a text search, or a manual export.
- Drop a question because the shape does not support it. Raise it.
