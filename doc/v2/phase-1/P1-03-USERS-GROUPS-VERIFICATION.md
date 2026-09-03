# P1-03 — Users & Groups: Verification

What was actually executed and actually observed. Where something is unverified,
blocked or was skipped, it says so and says why (`CLAUDE.md` §6).

| | |
| --- | --- |
| PLAN merge SHA | `bc18725f76248e491a26a931168f8e062a8da296` |
| DESIGN merge SHA | `9e67749cc38e4717e30b9359a1933f28ea9e2b47` |
| Implementation merge SHA | `cb73f14c4c6cc7aa06a47adb11d0e656bd53b79c` (PR #84) |
| **Status** | **P1-03 PRODUCT OWNER ACCEPTED — 3 September 2026** |

---

## 0. Acceptance

**P1-03 — USERS & GROUPS — PRODUCT OWNER ACCEPTED, 3 September 2026.**

The Product Owner ran the test script on production as `salil@lithan.com` and
confirmed: **all executed steps PASS, no failures observed.** Recorded on their
confirmation, which is what acceptance is.

### The two carried-gate observations

| Step | Gate | Result |
| --- | --- | --- |
| **34 / E4** | Sole System Administrator deactivation refusal | **PASS — observed by the Product Owner** |
| **43 / E5** | Multi-user management-cycle refusal | **PASS — observed by the Product Owner** |

**The verbatim wording of neither refusal was retained or provided.** It is not
reproduced anywhere in this document, and it has not been reconstructed from the
source. The behaviour is recorded as the Product Owner observed it; the exact
sentences they saw are not part of this record, and saying otherwise would be
inventing evidence.

That is a real limit on this evidence and it is stated rather than glossed: a
future reader can know the refusals occurred, and cannot quote them.

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

`CLAUDE.md` §2. **70 mutations applied; every one observed to fail the suite.**

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
| The group duplicate-name guard added after reading the test script | 3 | yes |
| The reworked secret-length guard | 2 | yes |

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

Real Chromium, real rendered screens, against a seeded database holding every
state a screen can be in: somebody who has never signed in, somebody who has, an
inactive person, a person with **no organisation**, a group with live and ended
membership, an empty group, an inactive group, and 34 users so pagination is
real rather than notional.

**Every screen proves it is the screen it claims to be** before anything is
measured, by asserting its `h1`. The P1-02 audit measured the Login page fifteen
times without noticing, because a hand-built session cookie failed to decrypt
and every request fell through to the entry page — and it reported zero defects
on five screens it had never seen. That assertion caught the same mistake again
here: the first cookie was missing the framework's `CookieValuePrefix`, and the
`h1` read *"From business data to confident decisions in moments."*

### 5.1 What was measured

| | |
| --- | --- |
| Screen states | 15 |
| Themes | Light and Dark |
| Widths | 1440 (desktop) and 390 (small) |
| **Measurements** | **60 screen renderings** |
| Per screen | `h1` identity, implementation-term scan, horizontal overflow of the page and of every layout container, contrast of every visible text node against the surface it is actually painted on, console errors |

### 5.2 Findings, and the four defects it caught that CI passed over

**The first pass found four things.** Three were layout, one was contrast:

| # | Finding | Fix |
| --- | --- | --- |
| 1 | Filter bars carried `.org-form-inline` alone — the ROW layout without the label rule that belongs with it. Every filter label sat jammed against its own control and against the next: *"SearchStatusOrganisationGroup"* | A dedicated `.org-filters` reuses the label layout and stays transparent |
| 2 | `.org-form-title` carries `width: 100%`, which spans a flex row and does **nothing at all in a grid** — so *"Group details"* sat in the first cell of the profile grid, reading as a label for the Name field beside it | It spans, and the three titles are headings rather than paragraphs |
| 3 | The record page said *"Organisation"* twice: as the row label, and again above the control inside it | The second is for assistive technology only |
| 4 | **The Inactive status pill measured 4.40:1 in the light theme** — under AA. It painted `--chrome-muted` on `--chrome-hover`: tokens for the DARK chrome, not for a light card. A P1-01 component every list in the product uses | A theme-aware `--badge-neutral` pair, like every other badge |

Finding 4 is the one worth dwelling on. **It is close enough to passing that no
amount of looking would have caught it** — 4.40 against a floor of 4.5. It was
found by measuring computed colour against the surface it is actually painted
on, and it had been in the product since P1-01.

Two further nits were found by reading the screenshots rather than by the
measurements: an organisation's **name** rendered in `.idn-value`, the monospace
face reserved for identifiers; and a note under the Organisation row that
repeated, in different words, the refusal already shown above it.

### 5.3 The finished build

The whole set was re-measured after the fixes.

| | |
| --- | --- |
| Screen renderings measured | **60** |
| Layout, contrast, overflow, wording findings | **0** |
| Pages whose body scrolled sideways at 390px | **0** — wide tables scroll inside their own box, per the standard |
| `h1` mismatches | **0** |

### 5.4 The console errors, attributed rather than dismissed

Every screen logged **one** console error: `net::ERR_CONNECTION_RESET`.

`CLAUDE.md` §4 lists browser console errors as a gate item, so it was identified
rather than waved away. Two ordinary probes reproduced nothing, which is exactly
how an environmental fault behaves. Adding a `requestfailed` listener named it
exactly:

```
https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800
  &family=Source+Sans+3:wght@400;500;600;700  —  net::ERR_CONNECTION_RESET
```

It is the **Google Fonts stylesheet**, which this verification container cannot
reach: its egress proxy reported the matching failures itself — fourteen
connections to `fonts.googleapis.com` reset during a fifteen-screen pass.

**No console error originates from SemantIQ's own code or assets.** The page
carries a real fallback font stack, so this is a cosmetic degradation rather
than a fault, and the screenshots above were taken with the CDN unreachable —
they show what a reader sees in the worst case.

**This is carried, not closed.** The production check belongs in the Product
Owner script (step 56), where the CDN is reachable and the honest answer can be
observed rather than inferred.

---

## 6. MySQL migrate / rollback / migrate

**Not runnable in this environment — no MySQL server is installed** (`pdo_mysql`
is present, a server is not). Executed by CI against **MySQL 8.4**, and the
result is read from the run rather than assumed:

| Step | Result |
| --- | --- |
| `migrate` | success |
| `migrate:rollback --step=1` | success |
| `migrate` again | success |
| `migrate:status` | success |
| **The People suite against MySQL** | success |

### 6.1 A green CI step that proved nothing

The People-on-MySQL step completed in **four seconds**, which looked too good.
`php artisan test tests/Feature/People` **exits 0 when the path matches
nothing** — it prints *"Test file not found"* and returns success. Confirmed
against a deliberately wrong path.

A rename or a moved directory would therefore have left that step permanently
green while running no tests at all: a step reporting safety it was not
providing, which is `CLAUDE.md` §2 in the workflow rather than in a test. The
step now captures its own output and fails unless at least forty People tests
passed.

---

## 7. Deviations from the DESIGN

| # | Deviation | Why, and what is unchanged |
| --- | --- | --- |
| 1 | DESIGN §9 names the reveal endpoint `POST /console/people/reveal`, taking the user in the body. It is implemented as **`POST /console/people/users/{user}/reveal`** | The behaviour DESIGN specifies is unchanged: `POST` rather than `GET`, exactly two accepted field names, and a 422 refusal identical for every other value so the endpoint cannot be used to ask which columns exist — all asserted by `PeoplePresentationTest`. The path differs so that the user id travels in the address like every other record route in the module, which keeps the two route sets structurally disjoint under correction 1 |

---

## 6b. Production, after deployment

The deploy workflow ran on the merge to `main` and succeeded, migrations
included. What follows was then observed against the live site with
**anonymous, cache-busted HTTPS requests** — no session, no test double.

| Request | Observed | What it shows |
| --- | --- | --- |
| `/console/people/users` | **302 → the entry page** | The route is delivered **and gated**. An anonymous caller is sent to sign in, not shown the directory |
| `/console/people/groups` | 302 → the entry page | as above |
| `/console/people/users/1` | 302 → the entry page | Authentication is decided **before** any record is looked up, so the response cannot say whether user 1 exists |
| `/console/people/users/999999` | 302 → the entry page | **Identical** to a real id — the gate answers first either way |
| `/console/people` | 302 → the entry page | The collection root redirects only after the gate |
| `/console/people/users/groups` | **404** | **Negative case 15, observed in production.** A collection name in a record position is Not Found, never a lookup for somebody called "groups" |
| `/console/people/groups/users` | 404 | as above |
| `/console/people/users/users` | 404 | as above |
| `/console/people/groups/groups` | 404 | as above |

The four 404s beside four 302s are the point: they are **different answers**,
which is what proves the record routes are constrained rather than merely
declared in a lucky order.

**Nothing was signed into, and no production data was read, created or changed
by this verification.** Everything past the gate belongs to the Product Owner's
test script.

---

## 7b. One failure outside P1-03's scope, fixed rather than re-run

CI failed on a commit that passed locally, in a **P1-02** test P1-03 does not
touch.

`test_the_secret_is_reported_as_presence_only` asserted that `strlen(SECRET)`
does not appear in the response body. `strlen` is **33** — a two-character
needle — and the body also carries Inertia's asset-version hash: 32 random hex
characters that change whenever any asset is rebuilt. Roughly one build in three
produces a hash containing `33`. Building the frontend for the P1-03 screens
landed on one of those.

**Re-running would sometimes have passed, which is worse than failing.** A guard
whose verdict depends on a random hash is not reporting on the thing it names.
The length is now asserted against the Inertia props — the application-controlled
payload, and the only place a length could actually leak from — with the
framework's version hash removed. The secret itself, a four-character fragment
and its SHA-256 are still asserted against the whole body: long, distinctive
needles with no randomness problem. Two mutations confirm it still catches a
real leak.

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
| 6 | A **duplicate group name or code** raised a database integrity error. Step 21 of the Product Owner script asks them to create a duplicate deliberately, so the script would have handed them a constraint violation for doing exactly what it told them to do. Found by reading the script back against the code | Refused in business language, with the constraint still the real guard underneath. N44 is extended to the group screens |

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
| **1 — P1-01 multi-user management cycle** | **CLOSED, 3 September 2026.** Step 43 run against a genuine second user, refusal observed by the Product Owner. Carried through two units for want of a second account; P1-03 provided it |
| **2 — P1-02 non-administrator refusal** | **CLOSED, 3 September 2026.** Steps 44–45. `semantiq@educlaas.com` — a real user with `platform_role = NULL` — signed in, saw an empty System Administration area, and was told so. No account was manufactured for it |
| **3 — P1-02 provider-wide Re-check lock** | **STILL CARRIED, to P1-05.** Unchanged by Product Owner decision: it needs a second **privileged** account, and P1-03 cannot assign `platform_role` to anybody. Automated evidence stands |

For gates 1 and 2 the verbatim refusal text was not retained — see §0.

**No production user was created by anybody but the Product Owner**, at any
point in this unit.

---

## 11. Statements this document does NOT make

- It does **not** claim search, filter or pagination were observed at scale in
  production. They were observed against 60 seeded users and 40 seeded groups in
  the test suite, and will be *exercised* rather than *stressed* at acceptance.
- It does **not** claim the locking reads were observed on SQLite. They were not,
  and could not be.
- It does **not** claim any Object ID was validated against Microsoft Entra.
  Nothing in SemantIQ can do that.
- It does **not** record a PASS or FAIL for any numbered step of the Product
  Owner test script. §12 records what was observed on screens sent during the
  run; the per-step result, the verbatim refusal messages (E4, E5) and the
  acceptance decision are the Product Owner's and are not written here on their
  behalf.
