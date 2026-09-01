# Daily Development Handover — 1 September 2026

**Project:** SemantIQ v2.2
**Current phase:** Phase 1 — P1-01 Organisation
**Status at end of day:** **P1-01 SCOPE COMPLETION READY FOR PRODUCT OWNER TEST**
**`main` at end of day:** `251988b`
**Production:** https://semantiq.claas2saas.com — deployed and healthy

This is a technical handover and restart checkpoint, not a meeting summary.
**Failures are retained even where they were subsequently fixed**, because the
failures are the evidence that the gates work. Today produced several, and the
most useful ones are the tests that were wrong before they were right.

---

## 1. Starting state

`main` at `9a9b001`. P1-01 was implemented and deployed, the UI foundation was
in flight, and P1-01 live verification was outstanding with six checks unrun.

The blanket rule **"no hard delete anywhere in P1-01"** was in force.

---

## 2. Product Owner decisions made today

| Decision | Outcome |
| --- | --- |
| **D-23** — Product-area navigation order | Approved (recorded 31 August, delivered today) |
| **D-24** — Guarded permanent delete / purge | **APPROVED.** Supersedes the blanket no-hard-delete rule for four master types |
| **D-25** — Organisation primary legal entity | **APPROVED.** Closes a PLAN → DESIGN omission; migration explicitly authorised |
| UI foundation | **ACCEPTED AND FROZEN** |

Both new decisions are recorded in full in `PHASE-1-PLAN.md` §9.

---

## 3. What was merged today — fourteen pull requests

| PR | SHA | What |
| --- | --- | --- |
| #60 | `9a9b001` | D-23 product-area navigation order |
| #61 | `c3670ff` | UI, brand and navigation foundation, with the new Login screen |
| #62 | `61f73f6` | Deploy: ship everything in `public/`, not a hardcoded list |
| #63 | `7f5a00d` | Deploy: do not demand that `.htaccess` be served |
| #64 | `5ea3584` | Test script: record the deployed build |
| #65 | `c5cec56` | Record the roadmap as 43 entries, and assert the number |
| #66 | `9cbfa52` | Record the observed production delta; issue the test script |
| #67 | `2bbeae9` | Organisation: one route-backed tab strip, not a footer of buttons |
| #68 | `157d35f` | Remove the dead styles for the section-button row |
| #69 | `2f5afb6` | Fix text that was unreadable in the dark theme |
| #70 | `3c2b021` | Record UI acceptance; resume P1-01 functional verification |
| #71 | `29892f1` | **P1-01 scope completion + D-24 guarded permanent delete** |
| #72 | `c5c757f` | **D-25 — the organisation's primary legal entity** |
| #73 | `251988b` | Observe the D-25 column on production, rather than inferring it |

All deployed. Deploy runs #84–#97, one failure (#86) analysed in §6.

---

## 4. The main finding of the day — P1-01 shipped with Update missing

P1-01 was accepted with **Create, Read, Deactivate and Reactivate** present and
**Update missing** on Legal Entities, Business Units, Departments and Teams.

### How it passed every gate

CI was green throughout, and could not have been otherwise. **Every P1-01 test
asserted that an operation which exists behaves correctly, and an operation that
does not exist has no test to fail.** Twenty-one negative cases, all proven
non-vacuous by mutation, and not one of them could detect a missing endpoint — a
mutation asks *"does removing this break something?"*, and you cannot remove what
was never written.

### Root cause — three things lined up

1. **The lifecycle was written as one word.** "Create → Read → Update →
   Deactivate/Reactivate" reads as a single idea and was built as one: the
   *shape* was implemented — controller, service, status column, refusal path —
   and each entity was judged complete by having that shape rather than all five
   verbs.
2. **Deactivate/Reactivate looked like the hard part, and were.** Every
   interesting rule lives in the lifecycle transitions. Update has no rule of its
   own beyond the same-organisation boundary, so it read as trivial — and trivial
   work is the work that gets assumed done.
3. **Nothing was looking.** The only cause worth building a guard for.

The same blind spot explains two half-built `code` fields: each was accepted by
its controller and carried in form state, so every automated check touching it
passed, and nothing asserted a user could ever *see* it.

### The guard

`tests/Architecture/LifecycleCompletenessTest.php` asserts that operations are
**present** — the class of check P1-01 had none of. Three halves:

- every catalogued entity exposes every action it owes;
- every collection root reachable in the route table appears in the catalogue,
  so a new entity cannot slip past a hand-written list;
- `DELETE` reaches only the four D-24 master types, asserted as an equality.

---

## 5. D-24 and D-25 — what was built

### D-24 guarded permanent delete

Purge is permitted for a Legal Entity, Business Unit, Department or Team **with
no dependency of any kind**. Never for the Organisation, team membership history
or management relationship history.

**The dependency check reads the schema, not a hand-written list.**
`PurgeDependencies` walks every foreign key pointing at the record's table. A
checklist only knows what was true when it was written; a table added by P1-03 or
P1-05 would simply not be on it, and the purge would succeed with a row still
pointing at the destroyed record.

Two properties follow, both asserted rather than assumed: **status is never
consulted**, so an inactive child and an ended membership block exactly as live
ones do; and **nothing cascades**, because the code that would cascade does not
exist — with every foreign key beneath already `RESTRICT`, so the database
refuses too.

The dependency state is **re-checked inside the write transaction**, as a
**locking** read: under MySQL's REPEATABLE READ a plain `SELECT` reads the
transaction's own snapshot and would miss precisely the row the second check
exists to catch. Asserted structurally, deliberately — in any single-threaded
test the second check is indistinguishable from the first.

### D-25 organisation primary legal entity

`organisations.primary_legal_entity_id` — nullable, FK → `legal_entities`,
indexed, `ON DELETE RESTRICT`. Additive, no seed, no backfill.

**D-14 is unchanged**, and that is asserted in three cases rather than claimed:
the junction still carries **no `primary` flag** (a mutation adding one fails the
build); the primary legal entity is **not** the parent of the business units — a
business unit may associate with a different entity, the primary may carry no
associations at all, and many-to-many still works both ways; and selecting a
primary **grants nothing**.

**The D-24 guard picked D-25 up on its own.** Nothing in `PurgeDependencies` was
changed. The migration added a foreign key, the schema walk found it, and a D-24
test *failed* reporting a reference it had not been told about. That is the guard
behaving exactly as designed, and the reason it reads the schema.

---

## 6. Defects found today — kept, not smoothed over

### 6.1 One test was genuinely vacuous

The HTTP re-parent case asserted only that the parent had not changed. A request
refused for **any** unrelated reason satisfies that — so it **passed while the
mutation was live**. Rewritten to prove the rename landed first. Found by
mutation, not by review.

### 6.2 Two mutations reported SURVIVED that had never applied

A quoting error in the mutation script meant the patch failed silently and the
run reported SURVIVED. Re-run properly, both were CAUGHT. **A mutation that
silently fails to apply is exactly the false safety this project keeps finding**,
and it is recorded here because the tooling was wrong, not the code.

### 6.3 Three responsive defects, and the end of guessing breakpoints

The page slid sideways by **33px at 390px**, then **12px at 768px**, then
**122px at 1101px** — one pixel above a containment breakpoint that had already
been moved once. Two guessed breakpoints, each overtaken by the next column
added.

The breakpoint is gone. Tables now sit in a scroller that has none. **0 of 78**
page/width combinations overflow, from 360px to 1920px, against 3 of 60 before.

The first of the three was also misdiagnosed at first: the table was blamed, and
the actual culprit was the add-form, where a `min-width` larger than the space
available beat every `max-width`.

### 6.4 A circular refusal, invisible to the test suite

The purge refusal closed with *"Deactivate it instead"* — and deactivating is
**also** refused while an entity is the organisation's primary. Two refusals,
each pointing at the other.

The suite asserted each refusal in isolation and was right about both. **Only
walking the two together in a browser showed the loop.** A blocker can now carry
its own closing sentence.

### 6.5 Text unreadable in the dark theme

A business unit's name rendered in the browser's default *visited* purple,
because nothing in the application defined a link colour at all; and the Active
pill hardcoded a dark-green hex with no dark value, measuring **1.15:1**.

The contrast audit itself was wrong three times before it was right — it walked
past gradients, forced alpha to 1, and matched the wrong selector. Guards now
make both causes unrepresentable, and D-24's danger tokens were measured
**before** the colours were chosen rather than after.

### 6.6 Deploy #86 failed — a fix that broke the deploy

Making the deploy ship all of `public/` was correct; demanding that `.htaccess`
be **served** was not. Corrected in #63: dotfiles excluded, `/.htaccess` asserted
403. The first version of that assertion was itself vacuous — it matched a
pre-existing different check — and was anchored properly.

### 6.7 A fix reported as delivered that had not been merged

The dark-theme correction was pushed and reported as done while the pull request
sat unmerged. The Product Owner replied *"Still same."* That was my error, and it
is the reason every report since names the merge SHA.

---

## 7. Evidence

| | |
| --- | --- |
| Tests | **289 passing, 4331 assertions** |
| Mutations run today | **45** — 17 scope completion, 16 D-24, 12 D-25 |
| Caught | **43**; the two "survived" were the tooling failure in §6.2 and are caught |
| Pint | clean |
| CI | now rolls back **and** re-applies migrations on MySQL 8.4 |
| Browser | Chromium, both themes, 360px–1920px, no console errors |

### Why the MySQL rollback step was added

A `down()` is needed only on the day something must be undone, which is the worst
possible day to discover it is broken — and the suite cannot see it, because
SQLite rejects dropping a foreign key by name outright. A rollback MySQL would
accept and one MySQL would reject look **identical** locally. CI now proves it
both ways, and the D-25 migration rolled back and re-applied cleanly.

---

## 8. Production state at end of day — READ THIS BEFORE RESUMING

Read from production at 10:36 on 1 September 2026 by the read-only verification
workflow. **Counts and schema facts only — no name, email, identity or
structural value was read or printed.**

| | |
| --- | --- |
| Organisations | 1 |
| Legal entities | 1 |
| Business units | 3, **all active** |
| Business unit ↔ legal entity associations | 3 |
| Departments | 3 |
| Teams | 2 |
| Team memberships | 1 — **0 current, 1 ended** |
| Management relationships | 0 |
| Users | 1, carrying an organisation |
| Legal entities spanning multiple business units | **1** |
| Organisations with a primary legal entity | **0** |
| `d25_column_exists` / `nullable` / FK target | true / true / `legal_entities` |
| `organisation_delete_routes` | **4** |

### What these numbers already prove

- **Check 5 (team membership) is effectively exercised** — a membership exists
  with an end date, and the row was retained.
- **Half of check 4 is exercised** — one legal entity spans multiple business
  units, which is the D-14 shape in one direction.
- **D-24 is live** — four DELETE routes, and no more.
- **D-25 is live and correctly empty** — the column exists and nothing
  backfilled it.
- **The D-25 migration preserved every record.**

### Known-wrong data the Product Owner has flagged

Two department names are wrong and are **corrections, not deletions**:

| Now | Should be |
| --- | --- |
| Singapore Retai Sales | Singapore Retail Sales |
| HR Admin | Singapore HR Admin |

Both are fixed with **Edit**, which keeps the record and its history. Neither
needs Delete, and Delete would be refused if either has a team.

---

## 9. Still outstanding for P1-01

| Check | Status |
| --- | --- |
| 2 · Reach Organisation | **OBSERVED** |
| 5a · D-16 organisation on the creating administrator | **OBSERVED** |
| 3 · Record the structure that genuinely exists | **PARTIAL** — real structure now exists |
| 4 · Business unit ↔ two legal entities, and the converse | **HALF** — one direction present |
| 5 · Add then remove a team member | **EFFECTIVELY OBSERVED** — 1 ended membership |
| 7 · Deactivating a business unit with an active department is refused | **NOT OBSERVED** |
| 9 · Move a department between business units | **NOT OBSERVED** — conditional on a genuine move |
| 6 · Multi-user management cycle | **CARRIED TO P1-03** — one user in production |

Plus the new sections of the test script: **S** (scope completion), **L** (D-25)
and **P** (D-24), none of which has been run on production.

---

## 10. What must NOT happen next

- **Do not start P1-02.** P1-01 has not been accepted.
- **Do not create a second user by hand**, reopen bootstrap, or write to MySQL.
  Bootstrap is closed while a System Administrator exists, and `platform_role` is
  written in exactly one place — the grant redemption path. **P1-03 owns user
  provisioning.**
- **Do not invent business data** to make a check pass. The one exception is the
  deliberately-labelled temporary record in step P1, which exists to be deleted.
- **Do not reopen** D-08B, D-14, D-15, D-16, D-23, D-24 or D-25.
- **Do not treat a green CI run as acceptance.**

---

## Resume Here Next Session

### The Product Owner's steps, in order

Everything below is done in a browser at https://semantiq.claas2saas.com, signed
in as the System Administrator. **The full script is
`doc/v2/phase-1/P1-01-ORGANISATION-PRODUCT-OWNER-TEST-SCRIPT.md`** — this is the
order to work it in.

**Step 1 — Fix the two wrong department names.** Organisation → Departments →
Edit. *Singapore Retai Sales* → *Singapore Retail Sales*; *HR Admin* →
*Singapore HR Admin*. Confirm neither business unit changed.

**Step 2 — Section L, the primary legal entity.** Company Profile → select the
real primary legal entity → Save → refresh and confirm it persisted. Then
observe both refusals (deactivate and delete), clear the selection, observe the
entity deactivates normally, reactivate it, and **re-select the correct primary**
so production is left in its true state.

**Step 3 — Section S, the rest of the scope completion.** Jurisdiction dropdown,
registered address, business-unit and team edits, the department and team code
fields, and the Management Hierarchy explanatory state. S15 and S16 will be
NOT APPLICABLE until P1-03.

**Step 4 — Section P, permanent delete.** Create a business unit named exactly
*ZZ TEST — DELETE ME*, delete it permanently, then observe the refusals on real
records that have dependencies. This is the only place inventing data is
required rather than forbidden.

**Step 5 — Check 7, the deactivation refusal.** Pick a business unit that has an
active department and try to deactivate it. **Refused, naming the children,
nothing changed.** This is the most important remaining check and it writes
nothing.

**Step 6 — Checks 4 and 9, only if genuine.** Associate a second legal entity if
that is really how the business works; move a department only if it genuinely
belongs elsewhere. If not, mark **NOT APPLICABLE** — that is a data condition,
not a defect.

**Step 7 — Presentation and console.** Look over every screen for developer
language, narrow the browser to phone width, and open the developer console.

**Step 8 — Return the results.** PASS / FAIL / NOT APPLICABLE per step, with the
evidence listed in §10 of the script — above all **the refusal messages**.

### Then, and only then

P1-01 receives explicit Product Owner acceptance, or a defect list. **P1-02 does
not unlock until acceptance is given.**

### One open question for the Product Owner

None blocking. The primary-legal-entity gap is closed by D-25; nothing else from
the scope-completeness audit is outstanding.

---

## 11. Evidence references

| Item | Reference |
| --- | --- |
| Scope completion + D-24 | PR #71, `29892f1`, deploy #95 |
| D-25 | PR #72, `c5c757f`, deploy #96 |
| Production observation of D-25 | PR #73, `251988b`, verification run #8 |
| Root cause, gap matrix, all mutations | `P1-01-ORGANISATION-VERIFICATION.md` §7.3h–§7.3j |
| D-24 and D-25 in full | `PHASE-1-PLAN.md` §9 |
| The Product Owner's steps | `P1-01-ORGANISATION-PRODUCT-OWNER-TEST-SCRIPT.md` sections S, L, P |
| Delivery rules | `CLAUDE.md` |
