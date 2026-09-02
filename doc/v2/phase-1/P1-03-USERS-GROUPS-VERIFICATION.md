# P1-03 — Users & Groups: Verification

What was actually executed and actually observed. Where something is unverified,
blocked or was skipped, it says so and says why (`CLAUDE.md` §6).

| | |
| --- | --- |
| PLAN merge SHA | `bc18725f76248e491a26a931168f8e062a8da296` |
| DESIGN merge SHA | `9e67749cc38e4717e30b9359a1933f28ea9e2b47` |
| Implementation merge SHA | *recorded at handover* |

---

## 1. What was delivered

| Area | Delivered |
| --- | --- |
| Schema | `groups`, `group_memberships` |
| Module | `App\Modules\People` — 2 services, 2 controllers, 3 models, 1 violation type |
| Screens | Users list, person record, Groups list, group record |
| Routes | 16 under `/console/people`, behind `RequireSystemAdministrator` and `RequireOrganisation` |
| Shared | `PurgeDependencies` moved to `App\Shared\Lifecycle` |
| Events | 12 new declared security events, all within the D-12 key boundary |

---

## 2. Automated tests

| | |
| --- | --- |
| Total | **452 passing, 6,733 assertions** |
| New for P1-03 | 6 files, 47 cases |
| Existing tests amended | 7, each extended to the third delivered capability without being weakened |

The seven amended tests all record the **exact delivered set** — the reachable
navigation, the module directories, the declared event families. Each was
extended to the new exact set and re-asserted as an equality; none was loosened
to a subset.

### The new files

| File | Covers |
| --- | --- |
| `tests/Architecture/PeopleBoundaryTest.php` | N3, N3b, N4, N5, N6, N40 |
| `tests/Architecture/PeopleRoutingTest.php` | N15 |
| `tests/Feature/People/PeopleAccessBoundaryTest.php` | N1, N2, N3 (behavioural), N4 (behavioural), plus two guards added after review |
| `tests/Feature/People/UserProvisioningTest.php` | N7–N14, N37 (behavioural) |
| `tests/Feature/People/UserLifecycleTest.php` | N19–N27, N41, N41b, N41c |
| `tests/Feature/People/MembershipRulesTest.php` | N28–N33, N42, N43, N44, N29 |
| `tests/Feature/People/PeopleCompletenessTest.php` | N16, N17, N18 |
| `tests/Feature/People/PeoplePresentationTest.php` | N34–N38 |
| `tests/Architecture/ReadableInBothThemesTest.php` (extended) | N39 |

---

## 3. Mutation testing — every guard broken deliberately

`CLAUDE.md` §2. **65 mutations applied; every one observed to fail the suite.**

Four survived on the first attempt. They are recorded here rather than quietly
re-run, because three of them exposed weaknesses in the TESTS — which is what
mutation testing is for, and what a summary reporting "65/65 caught" would have
hidden.

### The four survivors, and what each exposed

| Mutation | What it did | Why it survived | Correction |
| --- | --- | --- | --- |
| **M-N13** | Made the Users list render an empty cell instead of *Not signed in yet* | The assertion read the screen's **raw source**, and the file's own docblock quotes the phrase while explaining the rule. It was matching the comment, not the rendering — a guard that could not fail | Comments are stripped before any screen-source assertion, through a shared `Tests\Support\ScreenSource` helper applied to **every** such assertion rather than only the one that was caught |
| **M-N41c-1** | Moved the sole-administrator check **outside** the write transaction | The assertion was `transactionLevel() > 0`. `RefreshDatabase` wraps every test in its own transaction, so the depth was already 1 before the service was called — **the test was measuring the harness** | Depth is now measured against a **baseline** captured before the service call |
| **M-N27** | Removed the purge re-check inside the transaction | Same cause | Same correction |
| **M-N42** | Added `UNIQUE(group_id, user_id, joined_at)` and started every join at midnight | **A flaw in the mutation, not in the test.** The overlap guard repaired the collision by moving the new period to the previous `left_at`, so the P1-01 failure was never reproduced | The mutation was restated faithfully — DATE-valued timing in the model cast **plus** the key, which is exactly P1-01's shape. CAUGHT |

### Coverage by case

| Cases | Mutations | All caught |
| --- | --- | --- |
| N1, N2, N3, N3b, N4, N5, N6, N40 | 10 | yes |
| N7–N18 | 13 | yes |
| N19–N33, N41–N44 | 26 | yes |
| N34–N39 | 13 | yes |
| The two guards added after review | 2 | yes |
| Screen-copy re-verification after the N13 correction | 7 | yes |

Full mutation table: `doc/v2/phase-1/P1-03-MUTATIONS.md`.

---

## 4. What the automated tests CANNOT observe

Stated plainly rather than implied by a passing run.

| # | Not observable | Where it is covered instead |
| --- | --- | --- |
| 1 | **The `FOR UPDATE` clause on the sole-administrator count and the current-membership read.** SQLite's grammar compiles `lockForUpdate()` to nothing at all, so on SQLite there is no clause to find: asserting its presence would fail against correct code, and asserting its absence would pass against code that never asked for it | CI now runs `tests/Feature/People` against **MySQL 8.4** as a second step, where the clause exists and the assertion fires. The SQLite run asserts the source asks for the lock and **says in the test itself** that the lock is not observed there |
| 2 | **A genuine concurrent race** — two administrators deactivating each other simultaneously | Not reproducible in a single-process test suite. What is asserted is the property that makes it impossible: the count is re-read inside the transaction, with a locking read |
| 3 | **Whether an Object ID names a real person** | SemantIQ has no Graph permission by decision (D-33 = A). Nothing can check it. The first successful sign-in is the only proof, and it is carried into the Product Owner script as U1 |
| 4 | **Actual rendered contrast, spacing and overflow** | Measured in a real browser — §5 |

---

## 5. Browser verification

*Recorded from the actual run — §5.1 below.*

---

## 6. MySQL migrate / rollback / migrate

*Recorded at handover.*

---

## 7. Deviations from the DESIGN

| # | Deviation | Why, and what is unchanged |
| --- | --- | --- |
| 1 | DESIGN §9 names the reveal endpoint `POST /console/people/reveal`, taking the user in the body. It is implemented as **`POST /console/people/users/{user}/reveal`** | The behaviour DESIGN specifies is unchanged: `POST` rather than `GET`, exactly two accepted field names, and a 422 refusal identical for every other value so the endpoint cannot be used to ask which columns exist — all asserted by `PeoplePresentationTest`. The path differs so that the user id travels in the address like every other record route in the module, which keeps the two route sets structurally disjoint under correction 1 |

---

## 8. Gaps closed between the DESIGN and the first implementation

Found by reading the DESIGN back against the built screens, before handover.
None was found by a failing test, which is why they are listed.

| # | Gap | Fix |
| --- | --- | --- |
| 1 | The Users list was scoped to the current organisation alone, so a person with **no organisation** was invisible — and the *Assigned / Not assigned* filter DESIGN §10 requires could not have meant anything | People with no organisation are in the list, and the filter was added |
| 2 | The **organisation assignment control** did not exist on the record screen, though PLAN §5 names *Assign organisation — Yes* and both the route and the service were built | The control is on the record, offering the organisation or *Not assigned*; where a change is blocked the reason is stated instead of a control that would refuse |
| 3 | No **Status** filter on Groups; no **search**, **Current/Past** filter or pagination on a group's members | All added |
| 4 | Lists printed *Page 1 of 3* with no way to reach page 2 — a count is not navigation | A `Pagination` component with working **Previous** / **Next** |
| 5 | A group's empty state said *"Nobody has ever been in this group"* whenever a filter matched nobody, which for a group with history is **untrue** | The two facts are now distinguished, and asserted |

---

## 9. Two defects found by adversarial reading

Neither was found by a failing test. Both are now guarded.

| # | Defect | Consequence had it shipped |
| --- | --- | --- |
| 1 | `removeMember` bound the group and the membership **independently** and never checked that they belonged together | `PATCH /groups/1/members/99/remove` would end membership 99 — in a different group — and then confirm *"Membership ended."* on group 1, where the administrator would see no change. Somebody elsewhere would silently leave a group |
| 2 | Every People record route was reachable by id **regardless of organisation** | Release 1 has one organisation, so it is unreachable through the screens — which is exactly why it was asserted rather than assumed. A numeric id in the address bar was the whole attack |

---

## 10. Carried gates

| Gate | Status |
| --- | --- |
| **1 — P1-01 management cycle**, needing a genuine second user | **Open, and carried into the Product Owner script (step 43).** P1-03 makes it closable for the first time: the Product Owner provisions one genuine colleague, and the cycle is exercised against them |
| **2 — P1-02 non-administrator refusal**, needing a genuine non-administrator | **Open, and carried into the script (steps 44–45).** Every user P1-03 creates has `platform_role = NULL`, so it needs no special setup |
| **3 — P1-02 provider-wide Re-check lock** | **MOVED TO P1-05** by Product Owner decision. P1-03 cannot assign `platform_role`, so closing it here would mean manufacturing a second privileged production account. Automated evidence stands |

**No production user was created by anybody but the Product Owner.** Nothing in
this delivery seeded, invented or manufactured a person.

---

## 11. Statements this document does NOT make

- It does **not** claim search, filter or pagination were observed at scale in
  production. They were observed against 60 seeded users and 40 seeded groups in
  the test suite, and will be *exercised* rather than *stressed* at acceptance.
- It does **not** claim the locking reads were observed on SQLite. They were not,
  and could not be.
- It does **not** claim any Object ID was validated against Microsoft Entra.
  Nothing in SemantIQ can do that.
