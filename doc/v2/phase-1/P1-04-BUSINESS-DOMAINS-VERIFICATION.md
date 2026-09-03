# P1-04 — Business Domains: Verification

What was actually executed and actually observed. Where something is unverified,
blocked or was skipped, it says so and says why (`CLAUDE.md` §6).

| | |
| --- | --- |
| PLAN merge SHA | `b083b30f261820a00f8fdfc37addcd1a6e063789` (PR #88) |
| DESIGN merge SHA | `bffa100032cf9fe2d18869961b1659f4003c7aae` (PR #89) |
| Implementation merge SHA | *(recorded at merge — §9)* |
| **Status** | **Awaiting Product Owner test** |

---

## 1. What was delivered

| Area | Delivered |
| --- | --- |
| Schema | `business_domains`, `business_domain_owners` |
| Module | `App\Modules\Domains` — 2 services + 1 initialiser, 1 controller, 2 models, 3 enums, 1 violation type, 1 console command |
| Screens | Business Domains list, one domain record. **No tab strip** — the unit delivers one kind of thing |
| Routes | 9 under `/console/domains`, behind `RequireSystemAdministrator` **and** `RequireOrganisation` |
| Events | 7 new declared security events, **no new context key** |
| Navigation | *Business Domains* moves from `locked()` to `leaf()` — the single edit that entry was designed for |

**No service provider was added.** The first DESIGN draft listed one; reading the
codebase showed People has none either — services are autowired and the menu is
registered once from `ApprovedMenu`. The console command is registered in
`bootstrap/app.php`. Corrected in the DESIGN rather than discovered here.

---

## 2. Automated tests

| | |
| --- | --- |
| Total | **538 passing, 8,393 assertions** |
| New for P1-04 | 8 feature files (69 cases) + 2 architecture files (14 cases) |
| Skipped, deliberately | **2** — `DomainLockBoundaryTest` on SQLite, §4 |
| Existing tests amended | 6, each extended to the fourth delivered capability without being weakened |

### The six existing guards that had to be extended

None was loosened. Each one **failed first**, which is what a guard asserting an
exact set is supposed to do when a unit is delivered.

| Guard | Extension |
| --- | --- |
| Delivered-destination set (×3 files) | *Business Domains → `/console/domains`* added |
| Roadmap inertness | *Business Domains* added to the delivered list, and it must now carry a route |
| `PeopleCompletenessTest` DELETE routes | **Six → seven**, still asserted as an equality |
| `P1BoundaryTest` event families | `business_domain` added |
| `P1BoundaryTest` / `NoBusinessSchemaTest` forbidden schema | **Reviewed transfer — §6** |

---

## 3. Mutation testing — every guard broken deliberately

**63 mutations run. 55 caught first time. 4 survived. 1 genuine no-op.**
Full record: `P1-04-MUTATIONS.md`.

**The four that survived are the value of the exercise**, and two of them were
defects rather than incomplete mutations:

| Mutation | What it meant |
| --- | --- |
| **M-N6** — a route consults ownership | **A real defect in the test.** It compared two *ordinary* users, who never reach a controller. Rewritten to compare administrators too |
| **M-N23** — `assigned_at` becomes a `DATE` | **A guard that could not see its own mutation.** SQLite has type affinity rather than types. Moved to the declared column type |
| **M-N8 / M-N9** | Incomplete mutations — the controller never passed the field. Re-run as two-part mutations, both caught |
| **M-N49** — `withQueryString()` removed | **A genuine no-op**, recorded as one. `Pagination` rebuilds from the `filters` prop |

---

## 3b. The measurement that contradicted this unit's own reasoning

**Recorded because it happened, and because it corrects three files that said
otherwise.**

The DESIGN's justification for moving the serialisation boundary to the domain
row was: *with no current owner there is no ownership row to lock, so
`lockForUpdate()` holds nothing and two concurrent first-owner assignments both
insert.* That sentence was written into the DESIGN, the service docblock and the
migration.

**Run against MySQL 8.4, it is false.** `DomainLockBoundaryTest` reported:

> `[C1] With no ownership row present: locking the open ownership row **BLOCKED**
> a concurrent first-owner insert; locking the domain row **BLOCKED** a
> concurrent domain read.`

InnoDB takes a **gap lock** on the empty index range under REPEATABLE READ.

**The correction to the code still stands**, on the two reasons that survive
being measured:

| # | Reason |
| --- | --- |
| 1 | **The ownership row is the wrong object.** All five operations decide from the domain's `status` **and** its ownership together, and a lock on one of two things cannot serialise a decision taken over both |
| 2 | **It works by accident.** The protection comes from the shape of `domain_owners_domain_ended_idx` and from the isolation level. Change either and it is gone, with no change to the service and no test that would notice |

All three files were corrected, **with the original claim left visible rather
than quietly replaced.** The test now **reports** that reading and asserts only
what must be true: the domain row is held, and the lock is released when the
transaction ends.

**The measurement also had to be moved before it could be trusted.** Its first
version lived in a class using `RefreshDatabase`, so the domain row was
uncommitted and the second connection blocked on the **foreign key to that
uncommitted parent** — the right answer for the wrong reason. CI caught it.

---

## 4. What the automated tests CANNOT observe

Stated rather than left for a reader to assume.

| | |
| --- | --- |
| **Row locking on SQLite** | SQLite has no `SELECT … FOR UPDATE`; the locking reads compile away entirely. `DomainLockBoundaryTest` **skips explicitly with a stated reason** rather than passing vacuously, and CI **fails the Domains MySQL step if anything skips there** |
| **True simultaneity** | The C2–C7 cases are *stale-instance* tests: they prove the service re-reads under the lock rather than deciding from a pre-lock snapshot. They do not run two requests at the same instant |
| **That disabling never broadens access** | There is no access. Carried to P1-05 — §8 |
| **Search and filter at real volume** | Exercised against 60 seeded domains in the suite; the Product Owner will *exercise* rather than *stress* them |

---

## 5. Browser verification

Chromium, at 1440×1000 and 390×844, in both themes, against the running
application with a real signed session.

**Recorded because it happened, not because it was expected:** the font CDN is
unreachable from this environment, so the pages rendered with their fallback
stack. Font-loading is therefore **not** verified here, and step 56 of the test
script asks the Product Owner to check the browser console on a machine that can
reach it.

### 5.1 Three defects the browser found that CI could not

| # | Defect | Fix |
| --- | --- | --- |
| 1 | **Both sentence-length hints rendered in ALL CAPS.** `.org-hint` is uppercased by design — it labels a control. *"THIS IS A STATEMENT OF INTENT. IT DOES NOT GRANT OR RESTRICT ANYTHING TODAY."* shouted the one sentence in the unit that most needs to be read calmly | `.org-hint-plain`, which already existed for exactly this and carried a comment saying *"so it is not shouted"* |
| 2 | **The identity code looked editable.** `readOnly` and `disabled`, and styled identically to the Name field beside it — which invites an administrator to try, and then says nothing when nothing happens | `.org-readonly` |
| 3 | **The owner control said a domain had no owner when it had one.** The select was bound to the current owner's id, and the picker offers **active** people only — so a domain whose owner had gone inactive showed *"Choose a person"*, which is the exact case the *Needs attention* banner above it was describing | Who is accountable is stated as a fact, separately from the control that changes it. The select is labelled **Replace with** |

**Defect 3 is the one worth noticing.** Every test passed. The screen contradicted
itself in the one state the whole D-42/D-45 design exists to handle, and only
looking at it found that.

### 5.2 What was observed after the fixes

| Screen | Observed |
| --- | --- |
| List, light and dark | Eight domains; *Commercial* showing code `sales` — a renamed baseline keeping its identity; **Enabled** / **Disabled** pills; *Not assigned* in muted text; the **Owner inactive** attention pill beside Sam Okonkwo |
| Record — enabled, healthy owner | Details, Accountability, Availability in order; *"The owner is accountable for this domain. They do not get access to it."* present |
| Record — **Needs attention** | The banner reads *"The domain remains enabled. Assign an active owner when you can. This ownership status does not change anyone's access."* Status still **Enabled** |
| Record — two ownership periods | Both listed, newest first, the ended one quietened |
| Record — custom, never owned | **Permanent removal** section present, with the sentence explaining why it is still possible |
| Record — baseline | **No Permanent removal section at all** |
| Filtered to nothing | *"No domains match these filters."* with **Clear filters** — not the no-domains sentence |
| 390 px | The table scrolls inside its own container; the page does not |
| Both themes | Readable; **no new colour token** — the attention pill reuses P1-02's |

---

## 6. The reviewed transfer of two architecture guards

`NoBusinessSchemaTest` and `P1BoundaryTest` both forbade `domains` and
`business_domains` outright. They are the guards that say *this unit has not been
delivered*, and they become **false** the day it is — so they were changed the
way `organisations`, `teams` and `business_units` moved to P1-01 and `users` to
P1-00.

**What did not move is the point.** `roles`, `permissions`, `scopes`,
`sensitivity`, `entitlements`, `audit`, `access_reviews` and `fabric` all stay
forbidden, and a **new test asserts they are still there** — because the way this
guard is really lost is a wider deletion made in the moment, with a red test in
the way and `scopes` on the same line as `business_domains`. Two mutations
confirm it: removing `scopes`, and removing `sensitivity`. Both caught.

---

## 7. MySQL

| Check | Where |
| --- | --- |
| `migrate` → `migrate:rollback --step=1` → `migrate` | CI, against **MySQL 8.4** |
| Identifier lengths within 64 characters | `MigrationIdentifierLengthTest`, plus the real `CREATE TABLE` |
| The Domains suite on MySQL, **concurrency cases included** | A new CI step, which **fails if anything skips** |

**Rollback cannot be checked locally and was not claimed to be.** SQLite cannot
drop a foreign key by name at all, so a rollback MySQL would accept fails here
and one MySQL would reject looks identical.

---

## 8. The carried gate this unit creates

| Gate | To | Why it cannot run here |
| --- | --- | --- |
| **Disabling a domain must never BROADEN effective access** | **P1-05** | P1-04 ships no code that reads `status` to decide anything, so the failure is unreachable and untestable here. It becomes reachable the moment P1-05 builds effective access |

The failure it anticipates is concrete: the natural implementation of "disabled"
is *a filter that removes the domain from a set*, and a filter skipped when the
set is empty turns **no domains enabled** into **allow everything**.

**To be recorded in `PHASE-1-PLAN.md` §10 on acceptance**, because that register
has already lost two gates once.

---

## 9. Production

*Recorded after deployment — deploy SHA, the `domains:initialise` run and its
actual output, and the read-only state report.*

---

## 10. Statements this document does NOT make

- It does **not** claim two requests were run simultaneously. C2–C7 prove where
  the decision is taken; `DomainLockBoundaryTest` proves the row is held.
- It does **not** claim the ownership-row lock holds nothing on MySQL. **It
  measured the opposite** — see §3b. What is asserted is that **the domain row is
  held**.
- It does **not** claim font loading was verified. The CDN is unreachable here.
- It does **not** claim any domain grants or withholds access. Nothing reads a
  domain to authorize anything, and that is asserted twice — in source across
  `app/` and `resources/js`, and behaviourally, by giving an owner and a
  non-owner the same answer from every route.
- It does **not** record a PASS or FAIL for any numbered step of the Product
  Owner test script. That result is the Product Owner's.
