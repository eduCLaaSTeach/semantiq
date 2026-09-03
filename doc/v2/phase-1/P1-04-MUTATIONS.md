# P1-04 — Business Domains: mutation record

`CLAUDE.md` §2: *a test that cannot fail is worse than no test.* Every guard
below was **broken deliberately and the test observed to fail.**

Each mutation is the one **a person who had misunderstood the rule would
plausibly write**, not the one easiest to make fail. Where a mutation
**survived**, it is recorded here with what was done about it — that is the
whole value of this exercise, and four of them found real problems.

| | |
| --- | --- |
| Mutations run | **63** |
| Caught on the first attempt | 55 |
| Survived, then resolved | **4** — two were defects in the tests, two were incomplete mutations |
| Recorded as genuine no-ops | 1 |
| Anchor collisions that were themselves evidence | 1 |

---

## The four that survived, and what each one meant

### M-N6 — a route consults ownership · **A REAL DEFECT IN THE TEST**

**Mutation:** make `DomainController::index()` `abort(418)` when the caller owns
any domain.

**Survived**, and it should not have. The test compared two **ordinary** users —
one owning three domains, one owning none. Ordinary users are refused by
`RequireSystemAdministrator` **before any controller runs**, so both received an
identical redirect and the comparison never reached the code the mutation
changed. The guard would have passed against a controller consulting ownership
on every line.

**Resolved by rewriting the test**, not the mutation. It now runs the same
comparison for **two pairs** — ordinary users *and* System Administrators — and
asserts that the administrator pair actually received a `200` from something, so
a future refusal cannot silently turn the comparison back into two identical
rejections. Re-run: **CAUGHT.**

### M-N49 — `withQueryString()` removed from the paginator · **A GENUINE NO-OP**

**Survived, and correctly so.** `Pagination.jsx` rebuilds the next-page request
from the **`filters` prop**, not from the paginator's own links, so removing
`withQueryString()` changes nothing anybody can observe in this codebase.

**Recorded as a no-op rather than dressed up as a pass.** The call is kept for
consistency with the People screens, and the guard that actually matters was
written and run instead:

| M-N49c | empty the `filters` prop, so paging loses the filter | **CAUGHT** |
| M-N49d | stop applying the page size | **CAUGHT** |
| M-N49b | ignore the Kind filter entirely | **CAUGHT** |

### M-N8 and M-N9 — **INCOMPLETE MUTATIONS, not weak guards**

**M-N8** made `code` fillable on the model; **M-N9** read `kind` from
`$attributes`. Both survived because the **controller never passes either
field** — `validate()` returns only the accepted keys, so the mutated line had
nothing to receive.

That is defence in depth working, but a survived mutation is not evidence until
it is complete. Both were re-run as **two-part mutations**, the way somebody
actually making the field editable would write them — controller *and* service
together. Re-run: **both CAUGHT.**

### M-N13 — a baseline domain becomes removable · **INCOMPLETE MUTATION**

Survived with only the pre-transaction check removed; the in-transaction check
still refused. Re-run with **both** removed: **CAUGHT.** The two checks are D-24's
shape — one for a fast answer, one under the lock — and this is what having both
looks like when it is tested.

### M-N7b — the anchor collision that was itself the finding

The mutation "remove `scopes` from the forbidden list" could not be applied
because the string appears **twice**: once in `FORBIDDEN`, once in
`P1_05_STILL_FORBIDDEN`. That is the guard's design — a wider deletion breaks
the second list — and it was then run with full context against each entry:
**both CAUGHT.**

---

## 1. The boundary — a domain grants nothing

| # | Mutation | Result |
| --- | --- | --- |
| M-N1 | Drop `RequireSystemAdministrator` from the domains route group | **CAUGHT** |
| M-N2 | `setOwner()` writes `platform_role` to the new owner | **CAUGHT** |
| M-N3 | An unrelated service (`GroupService`) imports `BusinessDomain` | **CAUGHT** |
| M-N3b | Add a `grantee_role` column | **CAUGHT** |
| M-N3c | Add `owner_user_id` beside the ownership table | **CAUGHT** |
| M-N3d | `sensitivity_expectation` returns | **CAUGHT** |
| M-N3e | `RequireSystemAdministrator` consults a domain | **CAUGHT** |
| M-N3f | Point the dependency scanner at a directory with no matches | **CAUGHT** |
| M-N6 | A route consults ownership | **CAUGHT** *(after the test was fixed)* |
| M-N34 | A middleware branches on `access_expectation` | **CAUGHT** |
| M-N7 | Spell `owner/clear` as a `DELETE` | **CAUGHT** |
| M-N7b | `scopes` leaves the forbidden list with `business_domains` | **CAUGHT** |
| M-N7b2 | `sensitivity` leaves the forbidden list | **CAUGHT** |
| M-N7c | Pre-create an `Access` module directory | **CAUGHT** |

## 2. Identity and lifecycle

| # | Mutation | Result |
| --- | --- | --- |
| M-N8 | `code` editable — controller accepts it **and** the service writes it | **CAUGHT** |
| M-N9 | `kind` accepted from a request — controller **and** service | **CAUGHT** |
| M-N10 | Check reserved codes against *enabled rows* instead of the closed catalogue | **CAUGHT** |
| M-N11 | Remove the duplicate pre-check and let the unique constraint surface | **CAUGHT** |
| M-N13 | A baseline domain becomes removable — **both** checks removed | **CAUGHT** |
| M-N16 | Register `/console/domains/archived` beside `{domain}` | **CAUGHT** |
| M-N29 | Enable checks an ownership row **ever** existed, not a current one | **CAUGHT** |
| M-N30 | Enable checks for a current owner without checking they are active | **CAUGHT** |
| M-N32 | Disable becomes refusable | **CAUGHT** |
| M-N45 | Purge checks only **current** ownership | **CAUGHT** |
| — | The menu entry goes back to `locked()` | **CAUGHT** |

## 3. Ownership

| # | Mutation | Result |
| --- | --- | --- |
| M-N18 | Drop the same-organisation check | **CAUGHT** |
| M-N19 | Let a `NULL` organisation pass | **CAUGHT** |
| M-N20 | Move the locking reads outside the transaction | **CAUGHT** |
| M-N21 | Change owner updates the row in place | **CAUGHT** |
| M-N22 | Clearing the owner **deletes** the period | **CAUGHT** |
| M-N23 | `assigned_at` becomes a `DATE` | **CAUGHT** *(see below)* |
| M-N23b | Add `UNIQUE(business_domain_id, assigned_at)` — the P1-01 key shape | **CAUGHT** |
| M-N24 | An inactive user may be newly assigned | **CAUGHT** |
| M-N25 | Deactivating an owner clears their ownership | **CAUGHT** |
| M-N26 | The current owner is answered from the earliest period | **CAUGHT** |
| M-N28 | Re-assigning the same person always inserts | **CAUGHT** |
| M-N31 | Clearing an **enabled** domain's owner is permitted | **CAUGHT** |
| M-N31b | Clearing is refused in **both** states | **CAUGHT** |

**M-N23 needed the guard moved to the schema.** The first version of the test
wrote two ownership periods hours apart and asserted their timestamps differed.
It **survived** a `date()` column — because **SQLite has type affinity rather
than types**, and a `DATE` column there stores and returns a full timestamp
quite happily. A behavioural test cannot see this mutation on the engine the
suite runs on.

The guard now reads the **declared column type** and the **index list** from the
schema, which is true on both engines. That is the P1-01 collision — the one
production reproduced for group membership on its first day of use — caught at
the only layer that can see it.

## 4. Concurrency — the corrected serialisation boundary

| # | Mutation | Result |
| --- | --- | --- |
| M-C2 | `clear()` decides from the handed-in model rather than the locked re-read | **CAUGHT** |
| M-C3 | `enable()` decides from the handed-in model rather than the locked re-read | **CAUGHT** |
| M-C3b | `enable()` reads the owner's status from the stale relation | **CAUGHT** |
| M-C4 | Replacement ends the period the **stale snapshot** remembers | **CAUGHT** |
| M-C5 | Purge runs its dependency walk only **before** the transaction | **CAUGHT** |
| M-C7 | Purge reads ownership **before** it locks the domain | **CAUGHT** |
| M-C7b | `setOwner()` reads ownership **before** it locks the domain | **CAUGHT** |

**M-C7 was not a rehearsal. It caught a real ordering violation in code that had
already been written**: `purge()` ran a dependency pre-check before opening its
transaction, which read `business_domain_owners` before the domain row was
locked — the one lock order this unit promises, broken in the first operation
that was allowed a fast path. The pre-check was removed rather than the test
weakened; `isPurgeable()` still drives the screen, and the in-transaction check
was always the guard.

**C1 — the demonstration the whole correction exists for** — runs on **MySQL
only**, with two connections, and is not in the table above because it is not a
mutation: it is a measurement. With no ownership row present it shows that
locking the open ownership row **blocks nothing**, and that locking the domain
row **does** block a second connection. SQLite has no `SELECT ... FOR UPDATE`,
so it **skips explicitly with a stated reason** rather than passing vacuously,
and CI fails the MySQL step if anything in the Domains suite skips there.

## 5. Initialisation

| # | Mutation | Result |
| --- | --- | --- |
| M-N37 | Initialisation drops its existence check | **CAUGHT** |
| M-N38 | A re-run resets the display name and status | **CAUGHT** |
| M-N39 | The seven arrive **enabled** | **CAUGHT** |
| M-N39b | The seven arrive **owned by the creating administrator** | **CAUGHT** |
| M-N41 | The list screen materialises the seven on first view | **CAUGHT** |
| M-N42 | An eighth baseline domain called *Custom* | **CAUGHT** |
| M-N43 | The command writes without an organisation | **CAUGHT** |

## 6. Presentation and events

| # | Mutation | Result |
| --- | --- | --- |
| M-N27 | Replace the inert-statement sentence with a lock icon and the word *policy* | **CAUGHT** |
| M-N36 | Delete the "the owner does not get access" sentence | **CAUGHT** |
| M-N36b | Keep the sentence **only as a comment** | **CAUGHT** |
| M-N47 | Add the domain's name to a security event | **CAUGHT** |
| M-N49b/c/d | Ignore a filter · empty the `filters` prop · drop the page size | **CAUGHT** |
| M-N50 | One message serves both empty states | **CAUGHT** |
| M-N51 | The Enabled pill hardcodes a hex | **CAUGHT** |
| — | The enable event stops being recorded | **CAUGHT** |

**M-N36b is the P1-03 lesson, applied before it could bite again.** That
mutation — moving the copy into a comment — is exactly what survived in P1-03 as
M-N13, because the assertion matched the docblock that quoted the sentence.
Every screen-source assertion in this unit runs through
`ScreenSource::rendered()`, which strips comments first, and this mutation
confirms it.
