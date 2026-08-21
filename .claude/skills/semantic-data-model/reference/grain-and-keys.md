# Grain And Keys

Grain is what one row means. Keys are how that row is found and joined. Get these two wrong and every number computed from the table is wrong in a way that is hard to see and harder to correct later.

Table and column names here are concepts. Map them to the project's confirmed naming convention, and get the real names through the Schema MCP workflow in `.claude/rules/schema-mcp.md`.

## Declare The Grain In One Sentence

Every table proposed or changed carries a grain sentence, in the proposal and in the table dictionary. The form is "one row is one ...".

| Good grain sentence | Why it works |
| --- | --- |
| One row is one contact. | Countable, unambiguous, no qualifier needed |
| One row is one enrichment attempt on one contact. | Names the event and its subject, so attempts per contact are countable |
| One row is one state change of one contact. | The change is the thing, so transitions are countable |
| One row is one contact's quality score as at one snapshot date. | The snapshot date is part of the grain, so history does not overwrite |
| One row is one matched pair of a surviving contact and a duplicate. | A pair, so duplicates found are countable without touching the contacts |

A grain sentence that needs "or" is two tables. "One row is one contact, or an imported batch summary" mixes grains, and any count of contacts from that table is wrong by however many summary rows exist.

## Symptoms Of Mixed Grain

- A type or level column that changes what the other columns mean.
- Nullable measure columns that are only populated for some row types.
- A total that must exclude certain rows to be correct, remembered in the query rather than in the structure.
- Header values repeated on every line row, so summing the header value multiplies it by the line count.

Split by grain instead. Header facts and line facts are two tables, related by key, each summable on its own.

## The Table Kinds And Their Grain

| Kind | Grain pattern | Holds |
| --- | --- | --- |
| Entity or current-state table | One row per thing | The record as the application works with it now |
| Event or attempt table | One row per occurrence | What happened, when, with what outcome |
| State history table | One row per transition | From-state, to-state, when, why, by whom |
| Versioned attribute history | One row per attribute value period | Valid-from, valid-to, current flag |
| Periodic snapshot | One row per thing per period | The measured state as at a date, for mix-over-time questions |
| Reference set (dimension) | One row per member | Stable code, display label, display order, active flag |
| Bridge | One row per related pair | The two keys plus anything true only of the pairing |

Pick the kind from the question, per `history-and-outcomes.md`. Do not add a kind no confirmed question needs.

## Keys

- One surrogate key per row, one column wide, system-assigned, stable for the life of the row, never reused after a delete. Single-column keys keep joins simple and keep a model's relationships unambiguous.
- Business and natural keys (an external reference, a document number, an email as an identifier) live in their own columns beside the surrogate key, with their own uniqueness where the business guarantees it.
- Never parse meaning out of a key. A key that encodes a source, a year, and a sequence is three columns plus a key, and the day the encoding changes, every report built on substring logic breaks silently.
- Keep a reference number a user quotes on the fact as its own column, even though nothing joins to it. It belongs to the row, and a report needs to show it.

## Foreign Keys That A Model Can Trust

- Give the fact a real foreign key to every reference set a confirmed question filters or groups by.
- Make that key non-null, using the reserved unknown member from `measures-and-dimensions.md` rather than null. A nullable key plus an inner join silently drops exactly the rows a "how many unattributed" question is about.
- Prefer one hop from fact to reference set over a chain of joins to reach a filter. Where the project's normalization makes a chain the right call, declare it in the hand-off so whoever builds the model does not flatten it wrongly.
- Model a genuine many-to-many as an explicit bridge table with its own grain sentence. A delimited column, a repeated column set, or a JSON array is not joinable, so any grouped count over it is manual.
- Never point a single key column at more than one parent table depending on a type column. A model cannot form a reliable relationship on it, and neither can a query.

## Scope Columns Belong On The Fact

Keep the columns a report has to filter by on the fact itself: owner, team or business unit, tenant, and whatever else the application's access scope uses. They are denormalized on purpose. Reporting filters by them constantly, record-level security in a model needs them present, and reaching them through three joins is how a report ends up showing one team another team's numbers.

Where ownership can move, the current owner sits on the record and the ownership change is a row, per `history-and-outcomes.md`, so "how many did this owner have last quarter" stays answerable.

## Soft Delete Is Part Of The Grain

A row a report has counted must not vanish. Mark it deleted with a timestamp, the actor, and a coded reason, keep it, and have the application filter by state. Reports then choose whether to include it, and last month's number stays reproducible. This is the same soft-delete default as the recycle bin in `.claude/rules/ui-ux-quality.md`.

## Never

- Leave a grain undeclared, or write one that needs "or".
- Mix header and line grain, or gate a column's meaning behind a type column.
- Use a composite string as a key, reuse a key after deletion, or spread a key across several columns for joining.
- Leave a dimension key nullable where a question counts rows.
- Model a many-to-many as a list in a column, or point one key at several possible parents.
- Push a filter a report needs behind extra joins when the column belongs on the fact.
- Hard delete a row that has been reported on.
