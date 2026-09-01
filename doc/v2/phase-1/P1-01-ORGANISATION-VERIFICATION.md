# P1-01 — Organisation — VERIFICATION

**Status:** **NOT ACCEPTED.** A live defect (§7.2a) was found by Product Owner
verification and corrected; the seven §7.5 checks remain outstanding.
**Carried gate:** the live multi-user management-cycle check is deferred to **P1-03** (§7.3).
**Deployed:** merge `9afe33d`, deployment [33378638710](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33378638710) — success
**Unit:** P1-01 (Phase 1 delivery order 3)
**PLAN:** `P1-01-ORGANISATION-PLAN.md` — approved; D-14, D-15
**DESIGN:** `P1-01-ORGANISATION-DESIGN.md` — approved; D-16
**Predecessor:** P1-00 — ACCEPTED 31 August 2026
**Successor:** P1-02 — Identity & SSO administration (**not started**)

Nothing below is marked PASS unless it was executed and its output observed.

---

## 1. What was delivered

Organisational structure, and nothing that decides access.

| Delivered | Note |
| --- | --- |
| Organisation (Company Profile) | Created by the screen; no seed row |
| Legal Entities | A separate axis, never a level in the tree |
| Business Units | **No `legal_entity_id`** — the rejected single-parent model |
| Business Unit ↔ Legal Entity | **D-14** junction: optional many-to-many |
| Departments, Teams | The structural tree |
| Team Membership | References `users` (D-15); `left_at`, never deleted |
| Management Hierarchy | Both sides `users`; `effective_to`, never deleted |
| `users.organisation_id` | **D-16** seam: nullable, owned by P1-01 |

**Not delivered, deliberately:** roles, permissions, domains, scopes,
sensitivity, directory sync, user administration, Fabric, Workplace. No P1-02,
P1-03, P1-04 or P1-05 functionality was built early.

---

## 2. Two defects found by the tests, not by review

### 2.1 A directory-enumeration oracle

Route-model binding runs inside Laravel's `web` group, **ahead of route
middleware**. An anonymous request to a protected record therefore answered:

```text
GET /console/organisation/business-units/{existing id}  ->  302
GET /console/organisation/business-units/999999         ->  404
```

An unauthenticated visitor could map the organisation by probing identifiers,
learning which business units and teams exist without ever being permitted to
read one. Nothing was disclosed beyond existence — but existence is what the
design says an anonymous request must not learn.

**Correction.** `EnsureSessionIsCurrent`, `RequireSystemAdministrator` and
`RequireOrganisation` are placed ahead of `SubstituteBindings` in the middleware
priority list, so the gates decide before a record is ever looked up. Both cases
now answer `302`, identically.

This is the same shape as the P1-00 defect where unknown and inactive identities
landed on different URLs. It was found by the anonymous route sweep, which walks
**every** GET route under `/console/organisation` rather than a hand-picked few.

### 2.2 `NavigationRegistry` rejected every valid node

`NavigationRegistry::add()` refuses a node whose route does not resolve — a good
guard, and the reason a menu entry pointing at nothing fails in a test rather
than rendering a link to a 404.

It rejected the first node ever registered. Routes are named **fluently**
(`Route::get(...)->name('profile')`), so a route enters the collection before its
name is set and the collection's name lookup is stale until refreshed. P1-BASE
registered zero nodes, so nothing had exercised the guard.

**Correction.** `refreshNameLookups()` before the check. The guard is kept, not
weakened.

### 2.3 Same-day corrections

Changing a manager, or rejoining a team, twice on one day collided with
`UNIQUE (user_id, effective_from)` and `UNIQUE (team_id, user_id, joined_at)` and
surfaced as a raw integrity error.

Both are **corrections rather than history**: ending a link and inserting another
on the same date writes a zero-length record that P1-07 would have to read past,
which is precisely what those keys exist to prevent. The existing row is now
amended in place; a change on a later date still ends the previous row and
retains it.

---

## 3. Negative-test matrix — 21 cases, 21 mutations, 21 caught

Each case was verified by applying the mutation named in the DESIGN, running the
suite, and observing the test fail. **No mutation survived.**

| # | Case | Mutation applied | Result |
| --- | --- | --- | --- |
| 1 | Anonymous request to any Organisation route | Remove the binding-order priority | **CAUGHT** |
| 2 | Authenticated non-administrator | Drop the platform-role gate | **CAUGHT** |
| 3 | System Administrator reads structure | — (positive control) | **PASS** |
| 4 | Administrator seeks business data via organisation context | Add a domain accessor to the organisation service | **CAUGHT** |
| 5 | Record created without an organisation | Make `organisation_id` nullable | **CAUGHT** |
| 6 | Parent in a different organisation | Drop the same-organisation check | **CAUGHT** |
| 7 | Team whose department is outside its business unit | Drop rule 3 | **CAUGHT** |
| 8 | Cycle in the management chain | Remove the chain walk | **CAUGHT** |
| 9 | User managing themselves | Drop the self-manager check | **CAUGHT** |
| 10 | Second current manager for one user | Drop the single-current-manager check | **CAUGHT** |
| 11 | Deactivate a node with active children | Cascade instead of refusing | **CAUGHT** |
| 12 | Deactivate a team with active memberships | Drop the membership check | **CAUGHT** |
| 13 | Hard delete via any route | Register a DELETE route | **CAUGHT** |
| 14 | Junction row crossing organisations | Drop the junction organisation check | **CAUGHT** |
| 15 | Duplicate current team membership | Drop the uniqueness check | **CAUGHT** |
| 16 | Move recorded as scope-affecting | Stop emitting on move | **CAUGHT** |
| 17 | Refusal bodies | Render the exception message | **PASS** |
| 18 | User with NULL `organisation_id` joins a team or chain | Allow a NULL organisation through | **CAUGHT** |
| 19 | Membership or management across organisations | Drop the comparison | **CAUGHT** |
| 19b | **The same, substituting `tenant_id`** | Replace `organisation_id` with `tenant_id` | **CAUGHT** |
| 20 | Company Profile creation associates its creator | Skip the association | **CAUGHT** |
| 21 | Association read as entitlement | Derive access from `organisation_id` | **CAUGHT** |

Two further mutations outside the numbered matrix were also applied and caught:
adding an attribute to the D-14 junction, and reactivating a child under an
inactive parent.

### 3.1 The mutation that would otherwise have shipped

Case 19b is the one worth naming. Entra `tenant_id` sits in the same column list
as `organisation_id`, is populated on every user, and in single-tenant Release 1
gives the **right answer for the wrong reason**. A guard written against it would
be green today and wrong the first day a second Entra tenant or a second SemantIQ
organisation existed.

The fixture makes that mutation fail: the two users are given the **same** Entra
tenant and **different** SemantIQ organisations, so a `tenant_id` comparison
permits what the rule must refuse. A separate architecture test asserts that no
file in the Organisation module reads `tenant_id` at all — scanning the source
with comments stripped, so the docblocks that *explain* the prohibition do not
have to be deleted to make the guard pass.

### 3.2 Cases 4 and 21, asserted against the boundary

There is no business data in P1-01 to withhold, so a test that merely found
nothing would pass for the wrong reason and keep passing after the boundary was
removed — the P1-00 lesson. Both assert against the authorisation boundary
instead:

- **Case 4** reflects over every class in the Organisation module and fails if
  any method name asks a question about domain, permission, entitlement, scope or
  sensitivity. Eloquent's local-scope convention is stripped first, so
  `scopeCurrent` passes while `scopeForDomain` would not.
- **Case 21** shows an administrator **with** an organisation and one **without**
  reaching exactly the same screens, and asserts the authorisation gate never
  mentions the column. Association is not entitlement.

### 3.3 The D-16 writer, asserted behaviourally

That Company Profile creation is the **only** writer of `users.organisation_id`
is proven by exercising every P1-01 service that touches a user — associate,
move, add member, remove member, set manager, clear manager, and a refusal path —
and asserting no user's `organisation_id` changed.

A source scan was written first and discarded: it can only match the spelling of
a write it already anticipates, and would pass against any write phrased
differently. It also produced a false positive on `StructureService`, which
legitimately writes `organisation_id` onto structural rows and never onto a user.

---

## 4. Local verification

| Check | Result |
| --- | --- |
| `pint` | **PASS** |
| `phpunit` | **PASS** — 155 tests, 1653 assertions |
| `vite build` | **PASS** |
| Mutation testing | **PASS** — 23 mutations applied, 23 caught |
| Generated MySQL DDL for the D-16 migration | reviewed; see §5 |

CI run [33378371924](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33378371924)
on `55ee78b` — **success**, every step including **`Run migrations against MySQL`**
against the real MySQL 8.4 service. The suite itself runs on SQLite, so that step
is what proves the same migrations apply to the engine production uses.

---

## 5. Schema evidence

The D-16 migration's generated MySQL, produced from the MySQL grammar and read
before it ran anywhere:

```sql
alter table `users` add `organisation_id` bigint unsigned null after `id`;
alter table `users` add index `users_organisation_idx`(`organisation_id`);
alter table `users` add constraint `users_organisation_fk`
  foreign key (`organisation_id`) references `organisations` (`id`);
```

The index is declared **before** the constraint deliberately. MySQL creates an
index for a foreign key that has none, so declaring the constraint first would
have left two indexes on one column — the named one and an implicit
`users_organisation_id_foreign`. In this order the constraint reuses it.

Nullable, additive, and no row is rewritten, so a rollback to the previous
release keeps working against this schema.

---

## 6. Reviewed transfers

`organisations`, `teams` and `business_units` are removed from the forbidden
lists in `NoBusinessSchemaTest` and `P1BoundaryTest` and are now owned by P1-01 —
the same reviewed transfer by which `users` moved to P1-00. Everything still
listed belongs to a unit that has **not** been delivered.

`NoBusinessSchemaTest` now asserts the module directories are exactly
`['Organisation', 'Platform']`. The point is unchanged: a directory is not
pre-created to reserve a name.

---

## 7. Production verification — 31 August 2026

Deployment [33378638710](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33378638710)
on merge `9afe33d`. Every step succeeded, including **`Run database migrations`**
against the live cPanel MySQL, **`Verify application health over SSH`**, and the
**web-exposure negative tests**.

### 7.1 Observed from outside, with output

Anonymous, cache-busted HTTPS requests to `https://semantiq.claas2saas.com`.

```text
/console/organisation                      302  -> /
/console/organisation/legal-entities       302  -> /
/console/organisation/business-units       302  -> /
/console/organisation/business-units/1     302  -> /
/console/organisation/departments          302  -> /
/console/organisation/teams                302  -> /
/console/organisation/teams/1              302  -> /
/console/organisation/hierarchy            302  -> /
```

```text
DELETE /console/organisation/teams/1            405
DELETE /console/organisation/business-units/1   405
DELETE /console/organisation/legal-entities/1   404
DELETE /console/organisation/departments/1      404
```

```text
GET /console/organisation/business-units/1            302
GET /console/organisation/business-units/999999       302
GET /console/organisation/business-units/2147483647   302
```

| # | Check | Observed | Result |
| --- | --- | --- | --- |
| 1 | `/console/organisation` and every child route, anonymous | 302 to login; no structure in any body | **PASS** |
| 8 | Hard delete on any route | **405** where the URI exists for GET, **404** where it does not. No DELETE is registered anywhere | **PASS** |
| — | **The §2.1 enumeration fix, in production** | An id that exists, one that does not, and one out of range all answer **302**. Before the fix a missing record answered 404 | **PASS** |
| 10 | Exposure gate, ACME, both checksums | Deployment steps 18, 19, 27 and 28 all succeeded | **PASS** |
| 11 | `semantiq:health` | Deployment step 25 succeeded | **PASS** |

The 405 responses are the stronger evidence of the two. `console/organisation/teams/{team}` exists for GET, so a registered DELETE would have been dispatched; Laravel answering **Method Not Allowed** is the router stating that no DELETE exists for a URI it otherwise knows.

### 7.2 Schema state, read from the server

`verify-organisation.yml` run
[33379047787](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33379047787)
— read-only, manual dispatch. Schema facts and counts; no name, email, identity,
grant or secret is read. Verbatim output:

```json
{"tables_present":{"organisations":true,"legal_entities":true,"business_units":true,
 "business_unit_legal_entity":true,"departments":true,"teams":true,
 "team_memberships":true,"management_relationships":true},
 "row_counts":{"organisations":0,"legal_entities":0,"business_units":0,
 "business_unit_legal_entity":0,"departments":0,"teams":0,
 "team_memberships":0,"management_relationships":0},
 "d16_column_exists":true,"d16_column_nullable":true,
 "d16_foreign_key_target":"organisations",
 "users_total":1,"users_with_organisation":0,
 "organisation_delete_routes":0}
```

| Claim | Evidence | Result |
| --- | --- | --- |
| All eight P1-01 tables exist in production | `tables_present` all `true` | **PASS** |
| **D-16 column exists** | `d16_column_exists = true` | **PASS** |
| **D-16 column is nullable** | `d16_column_nullable = true` | **PASS** |
| **D-16 foreign key targets `organisations`** | `d16_foreign_key_target = "organisations"` | **PASS** |
| **No seed row was created** | every `row_counts` value is `0` | **PASS** |
| **No backfill ran** | `users_total = 1`, `users_with_organisation = 0` | **PASS** |
| The existing System Administrator is untouched | `users_total` still `1`, as at P1-00 acceptance | **PASS** |
| No DELETE route exists in the unit | `organisation_delete_routes = 0` | **PASS** |

The two `users_*` counts together are the direct evidence for D-16's population
rule: the column exists on the live table, and the one existing administrator
carries **NULL** — no seed, no backfill, no manual database write, and no change
to bootstrap. They acquire an organisation only by creating the Company Profile,
which is check 5a in §7.5.

`users_total = 1` also re-confirms SYS-014/SYS-015 across this deployment: no
self-registration, and no user appeared as a side effect of the migration.

### 7.3 Deferred to P1-03 — the live multi-user management cycle

**Product Owner decision, 31 August 2026.**

Check 6 requires setting a manager and then attempting a **genuine** management
cycle. The implementation refuses self-management before the chain walk runs, so
a real cycle needs **at least two SemantIQ users**. Production has
`users_total = 1`, and P1-03 — which provisions additional users — has not been
delivered.

My earlier statement that one Product Owner sign-in could complete all eight
outstanding checks was **wrong**: check 6 is not executable in P1-01 at all.

It is **not** solved by inserting a user, reopening bootstrap, writing to MySQL,
building P1-03 early, or weakening the cycle rule. None of those was done.

| | |
| --- | --- |
| **P1-01 evidence for the cycle rule** | The mutation-proven automated coverage in §3 — case 8, mutation *remove the chain walk*, **CAUGHT** |
| **Carried gate** | The live multi-user management-cycle observation becomes a **mandatory carried verification gate for P1-03**, to be executed before P1-03 acceptance, once a second legitimate SemantIQ user exists |
| **Implementation impact** | **None.** The management-cycle rule and its implementation are unchanged |

### 7.2a Live defect found by the Product Owner — CORRECTED

**Found on 31 August 2026 by live verification. Not caught by any test.**

After signing in, `/console` still rendered the P1-00 standalone card: no
sidebar, no Organisation. The unit's first navigable capability was built,
routed and authorised — and unreachable from the page an administrator lands on.

Investigating it found a **second, worse cause hidden by the first.**

```text
Reproduced before fixing:
/console                 component Console/Home   productAreas []
/console/organisation                             productAreas []   <-- nobody suspected this
```

| # | Cause | Effect |
| --- | --- | --- |
| 1 | `/console` was never moved onto `AppShell` | The landing page had no shell, so no navigation could appear on it |
| 2 | `SystemAdministratorNavigationAuthorizer` took `Request` through its **constructor**, and `NavigationRegistry` is a **singleton** — so it read `semantiq_user` from a Request captured at construction, not the one the session middleware set it on | Every node denied. `productAreas` resolved to `[]` on **every page, the Organisation screens included.** Their sidebars were empty too |

**Corrections.** The request is read at call time. `productAreas` is shared as a
closure — Inertia calls `share()` before `$next($request)`, so eager evaluation
only worked because the middleware priority happens to run the session gate
first; that dependency is removed rather than relied on. `/console` renders
inside the shell with a deliberately minimal canvas: **not** Administration Home
(P1-10 owns that) and **not** a placeholder dashboard.

Backend authorisation is untouched. Sidebar visibility remains presentation
only, and every `/console` route still re-authorises independently.

**Why the existing tests missed it.** `NavigationRegistryTest` exercised the
registry **in isolation** — a hand-built registry, a stub authorizer, no HTTP.
`OrganisationBoundaryTest` exercised `/console/organisation` **directly by URL**.
Both were correct about their own subject. Neither asked the question a person
asks: *after signing in, is the capability actually there?* Every seam was
tested; the join between them was not.

**Regression proof.** `ConsoleNavigationTest` — 7 cases against the real HTTP
response. Four mutations applied, **all four caught**, including reverting
`/console` to the exact P1-00 card the Product Owner saw.

| Mutation | Result |
| --- | --- |
| Revert `/console` to the P1-00 card | **CAUGHT** |
| Authorizer holds a stale `Request` | **CAUGHT** |
| Authorizer admits everyone | **CAUGHT** |
| Register a Phase 2 node | **CAUGHT** |

**Live confirmation**, deployment
[33382545040](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33382545040)
on merge `4f99c46`:

| Check | Observed | Result |
| --- | --- | --- |
| Served bundle is the corrected build | `/build/assets/app-DajlIiJG.js` — hash matches the post-fix local build | **PASS** |
| The landing page carries the shell | `console-landing` and `shell-rail` both present in the served bundle; `console-landing` exists only in the corrected version | **PASS** |
| `/console` anonymous | 302 to login, **zero** occurrences of "Organisation" in the body | **PASS** |
| Signed-in sidebar shows Organisation | Requires an authenticated session — see §7.5 check 2 | **not yet observed** |

### 7.3a Counts-only baseline, before any browser action

`verify-organisation.yml` run
[33381020970](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33381020970),
captured **before** the Product Owner performs any browser action, so the live
verification is judged on a before/after delta rather than an absolute reading.

```json
{"users_total":1,"users_with_organisation":0,
 "team_memberships_current":0,"team_memberships_ended":0,
 "business_units_with_multiple_legal_entities":0,
 "legal_entities_with_multiple_business_units":0,
 "business_units_active":0,"business_units_inactive":0,
 "department_moved_events":0,
 "organisation_delete_routes":0}
```

All structural row counts are `0`, and every table is present.

### 7.3b Second reading — 1 September 2026, after the UI foundation

`verify-organisation.yml` run
[33474494282](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33474494282)
on `c5cec56`, taken to confirm the UI, Brand and Navigation Foundation preserved
the P1-01 baseline. It did not: **the Product Owner has since entered real
data**, and the delta is the evidence.

```json
{"tables_present":{"organisations":true,"legal_entities":true,"business_units":true,
 "business_unit_legal_entity":true,"departments":true,"teams":true,
 "team_memberships":true,"management_relationships":true},
 "row_counts":{"organisations":1,"legal_entities":0,"business_units":1,
 "business_unit_legal_entity":0,"departments":0,"teams":0,
 "team_memberships":0,"management_relationships":0},
 "d16_column_exists":true,"d16_column_nullable":true,
 "d16_foreign_key_target":"organisations",
 "users_total":1,"users_with_organisation":1,
 "team_memberships_current":0,"team_memberships_ended":0,
 "business_units_with_multiple_legal_entities":0,
 "legal_entities_with_multiple_business_units":0,
 "business_units_active":0,"business_units_inactive":1,
 "department_moved_events":0,
 "organisation_delete_routes":0}
```

Read against the §7.3a baseline, and **only** what the counts actually support:

| Movement | 31 Aug | 1 Sep | What it does and does not show |
| --- | --- | --- | --- |
| `organisations` | 0 | **1** | A Company Profile was created through the screen. Check 3 is **begun**, not complete. |
| `users_with_organisation` | 0 | **1** | **Check 5a is OBSERVED.** The administrator who created the profile acquired that `organisation_id`, by the one writer, with no seed, backfill or manual write. |
| `business_units` | 0 | **1** | One business unit exists. |
| `business_units_inactive` | 0 | **1** | The business unit was deactivated **and the deactivation was permitted**. With `departments = 0` this is the *allowed* case, **not** check 7 — check 7 needs a business unit with an active department and expects a **refusal**. |
| `legal_entities`, `departments`, `teams`, `team_memberships`, `management_relationships` | 0 | 0 | Checks 4, 5, 7 and 9 have **not** been exercised. |
| `organisation_delete_routes` | 0 | 0 | No DELETE route anywhere in the unit **as at that reading**. D-24 later added four guarded ones — §7.3i. The reading stands as recorded. |

`users_total` is still **1**, so §7.3 stands unchanged: check 6 remains carried
to P1-03.

**Nothing in this reading is inferred from a passing test.** Counts are the only
evidence, no name or identity was read, and where the counts cannot distinguish
two outcomes the check is left unobserved rather than assumed.

### 7.3c P1-01 screens re-verified under the new shell — 1 September 2026

The UI foundation replaced the shell every Organisation screen renders inside,
so all six were re-opened in a browser at 1440px and 390px:

| Screen | Heading | Shell | Active item | Sideways scroll | Console errors |
| --- | --- | --- | --- | --- | --- |
| `/console/organisation` | Company Profile | ✅ | Organisation | none | 0 |
| `…/legal-entities` | Legal Entities | ✅ | Organisation | none | 0 |
| `…/business-units` | Business Units | ✅ | Organisation | none | 0 |
| `…/departments` | Departments | ✅ | Organisation | none | 0 |
| `…/teams` | Teams | ✅ | Organisation | none | 0 |
| `…/hierarchy` | Management Hierarchy | ✅ | Organisation | none | 0 |

No implementation wording reaches any screen, and nothing is clipped. The one
element reported as overflowing is the visually-hidden `Actions` column header,
which is 1px wide by design for screen readers — checked rather than assumed.

**No P1-01 behaviour was changed by the foundation.** The routes, the
authorisation and the refusal paths are exactly as delivered.

### 7.3d Organisation tab navigation — 1 September 2026

A presentation and navigation correction only. **No schema, service, lifecycle
rule, validation, authorisation or D-14/D-15/D-16 behaviour was touched**, and
no production data was read or written.

The five section buttons at the foot of Company Profile are replaced by one
route-backed tab strip on every Organisation screen, per the shared standard's
Pattern B: real `<a href>` links in a `<nav>` landmark with `aria-current="page"`
on the active tab, never the ARIA tab widget, which the standard forbids mixing
with links on one strip.

Walked in a browser at 1440px and 390px. Every line is an observation:

| Behaviour | Observed |
| --- | --- |
| Six tabs, approved order | Company Profile · Legal Entities · Business Units · Departments · Teams · Management Hierarchy |
| All real links | yes — every tab is an `<a>` with an `href` |
| Exactly one active tab | yes, on every screen |
| Click across | `/console/organisation` → `…/business-units` → `…/departments`, URL changing each time |
| Browser Back ×2 | `…/business-units`, then `/console/organisation` — tab follows |
| Browser Forward | `…/business-units` — tab follows |
| Refresh | stays on the section, tab still selected |
| Cold URL | `…/hierarchy` opens with **Management Hierarchy** selected |
| Detail screen | `…/business-units/1` keeps **Business Units** selected and shows *← Back to Business Units*; the link and browser Back both return to the list |
| Old button row | gone |
| Keyboard | the strip is reachable by Tab and shows a 2px focus ring |
| Narrow screen | the strip scrolls **within itself**; the page does **not** scroll sideways |
| Console errors | 0 |

The Company Profile card was made compact — Name beside Legal name, Country
beside Timezone — so it is sized to its content instead of running the width of
the canvas.

**One deliberate departure from the standard, recorded rather than hidden.**
Pattern C returns from a detail screen through the breadcrumb and says there is
no separate back link. D-21 defers breadcrumbs, so following that literally
would leave a detail screen with no visible way back at all. A local contextual
back link is used instead, on that screen only. It is **not** a global
breadcrumb system and D-21 is unchanged.

**The list screens keep their inline create form** rather than moving a primary
action beside the section title. Converting an inline form into a button would
change how the screen behaves, and this correction is presentation only.

Ten regression tests cover the strip; seven mutations were applied and all
seven were caught. One of them survived first time round — the route checks were
asserting the test's own constant instead of the component's declared hrefs, so
pointing a tab at a route that did not exist passed. They now read the component.

### 7.3e Unreadable text in the dark theme — CORRECTED

**Found on 1 September 2026 by the Product Owner, on a dark screen. Not caught
by any test, and not caught by my own visual verification either — every
screenshot I had taken of a list was in the light theme.**

A business unit's name was effectively invisible on the Business Units list.

**Two separate causes, both real.**

1. **No link colour existed anywhere in the application.** Nothing defined one,
   so a bare `<a>` fell through to the browser default — `#0000EE` unvisited and
   `#551A8B` **visited**. That visited purple on the dark card is what the
   Product Owner circled. The same bug was on the Teams list, which nobody had
   looked at.

2. **The Active status pill hardcoded `#3D5418`** with no dark-theme value.
   Measured on the dark card: **1.15:1**, against a 4.5:1 requirement.

The shared standard names this failure mode exactly: *"Semantic colors used as
text, icons, or thin edges on a surface always go through the theme-aware
readable tokens, never the raw semantic hex; raw Violet-Red on a dark card is
about 1.6:1 and invisible."* The code did the thing the standard warns about.

**Corrected.** A link colour is defined once against the accent token, with
`:visited` pinned to the same value so a visited link never fades out; and the
pill now reads `--badge-success-bg` / `--badge-success-fg`, defined in all three
theme blocks. The dark foreground `#A8CC72` is a tint derived from Avocado Green
and measures **5.02:1** on the pill over the dark card — computed, not eyeballed.

**A full contrast audit now backs this up rather than a spot check.** Every text
run on nine screens, in both themes, measured as a computed colour against the
surface it is actually painted on:

| | |
| --- | --- |
| Screens | Login, `/console`, all six Organisation sections, one detail screen |
| Themes | light and dark |
| Below the WCAG AA threshold | **0** |
| Not automatically measurable | 44 runs sitting over the hero's gradient, checked visually instead |

One further fix came out of it: the sign-in unavailable note measured 4.11:1 in
dark and now uses the full body colour.

**The audit was wrong three times before it was right, and each error produced
confident numbers.** It walked past the hero's gradient to the page canvas and
reported 22 white-on-beige failures that do not exist; its alpha compositing
forced `a: 1` after one blend, so translucent chips were measured against a
surface that is not on the screen; and one of its own guards matched
`.org-list a:visited` instead of the bare rule, passing with the global rule
deleted. A measurement that is not itself checked is not evidence.

Three regression guards make the two causes unrepresentable: a link with no
defined colour, a semantic hex used as text, and a token that exists in one
theme only. Four mutations applied, four caught.

### 7.3f Third reading — 1 September 2026, verification resumed

`verify-organisation.yml` run
[33479698980](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33479698980)
on `2f5afb6`, taken on resuming P1-01 functional verification after the UI
foundation was accepted and frozen.

```json
{"row_counts":{"organisations":1,"legal_entities":0,"business_units":1,
 "business_unit_legal_entity":0,"departments":0,"teams":0,
 "team_memberships":0,"management_relationships":0},
 "d16_column_exists":true,"d16_column_nullable":true,
 "d16_foreign_key_target":"organisations",
 "users_total":1,"users_with_organisation":1,
 "business_units_active":1,"business_units_inactive":0,
 "department_moved_events":0,"organisation_delete_routes":0}
```

Delta against §7.3b, and only what the counts support:

| Movement | 05:40 | 06:55 | What it shows |
| --- | --- | --- | --- |
| `business_units_active` | 0 | **1** | A **reactivation was performed and permitted**. |
| `business_units_inactive` | 1 | **0** | The same row; nothing was created or removed. |
| Everything else | — | unchanged | No legal entity, department, team, membership or management link exists. |

**Observed lifecycle behaviour so far**, in order, on one real business unit
with zero departments: created → deactivated → reactivated, each **permitted**.
That is the *allowed* path through the rule.

**It is not check 7.** Check 7 requires a business unit that genuinely has an
**active department** and expects the deactivation to be **REFUSED** with the
blocking children named. With `departments = 0` that case has not been
reachable, so it remains unobserved.

Check 2 — a signed-in System Administrator reaches Organisation — is **OBSERVED**:
the Product Owner has been working in the Company Profile and Business Units
screens on production throughout, and supplied screenshots of both.

### 7.3g P1-01 functional verification — where it actually stands

| Check | Status | Evidence |
| --- | --- | --- |
| 2 · Reach Organisation | **OBSERVED** | Product Owner working in the live screens, 1 Sep 2026 |
| 5a · D-16 `organisation_id` on the creating administrator | **OBSERVED** | `users_with_organisation` 0 → 1, §7.3b |
| 3 · Record the structure that genuinely exists | **PARTIAL** | Organisation and one business unit exist; no legal entity, department or team yet |
| 7 · Deactivating a business unit with an active department is refused | **NOT OBSERVED** | needs a real department under a real business unit; `departments = 0` |
| 4 · Business unit ↔ two legal entities, and the converse | **NOT OBSERVED** | needs `legal_entities >= 2`; currently 0 |
| 9 · Move a department between business units | **NOT OBSERVED** | needs a real department and a legitimate move |
| 5 · Add then remove a team member | **NOT OBSERVED** | needs a real team and a factually correct membership |
| 6 · Multi-user management cycle | **CARRIED TO P1-03** | `users_total = 1`; §7.3 |

**Four checks remain, and none of them is executable by me.** Every one needs
real business structure entered through the screens, and inventing it is
forbidden — so they are the Product Owner's steps 5 to 12 in
`P1-01-ORGANISATION-PRODUCT-OWNER-TEST-SCRIPT.md`.

Checks 4, 9 and 5 stay **conditional**: if the real organisation has no genuine
many-to-many association, no legitimate department move and no factually correct
team membership, they are marked NOT APPLICABLE and carried forward with their
mutation-proven automated evidence intact. That is a data condition, not an
implementation defect.

### 7.3h P1-01 SCOPE COMPLETENESS CORRECTION — 1 September 2026

The Product Owner directed a full audit of the P1-01 implementation against its
plan and design, rather than a fix of the three defects they happened to notice.
The audit found six gaps. Four of them are the same gap.

#### The scope-gap matrix

Read against `PHASE-1-PLAN.md` and the P1-01 design. **Every scoped entity is
listed, including the ones that were complete**, because a matrix that lists only
failures cannot be checked.

| Entity | Create | Read | **Update** | Deactivate | Reactivate | Move | Other | Gap found |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Organisation (Company Profile) | ✅ | ✅ | ✅ | n/a | n/a | n/a | — | **none** |
| Legal Entity | ✅ | ✅ | ❌ **missing** | ✅ | ✅ | n/a | associate / dissociate ✅ | no update route, controller or service method; `registered_address` validated on create but never rendered; jurisdiction was free text |
| Business Unit | ✅ | ✅ | ❌ **missing** | ✅ | ✅ | n/a | D-14 association ✅ | no update anywhere |
| Department | ✅ | ✅ | ❌ **missing** | ✅ | ✅ | ✅ | — | no update; `code` accepted by the controller and held in form state but **never rendered**, so it could be created and then never read or corrected |
| Team | ✅ | ✅ | ❌ **missing** | ✅ | ✅ | ✅ | membership add / end ✅ | no update; `code` in the same half-built state as Department's; the create form had no code field at all |
| Management Hierarchy | set ✅ | ✅ | change — *representable, unreachable* | n/a | n/a | n/a | clear ✅ | `setManager()` already ended the previous link and opened a new one, so Change existed in the service; the screen offered no way to invoke it on a user who already had a manager, and said nothing about why a single-user organisation cannot have one |

Two findings that are **not** gaps, recorded because they were checked:

- **Schema: complete.** `registered_address`, `jurisdiction`, `departments.code`
  and `teams.code` all already exist, nullable, with the right types. **No
  migration was required and none was written.** The Product Owner's instruction
  was to stop and explain before implementing any schema change; there was
  nothing to explain, because there was nothing to change.
- **Hard delete: absent as at this audit.** No DELETE route, no destroy
  controller method, no model delete call anywhere in Organisation.
  **Superseded the same day by D-24** (§7.3i): permanent deletion now exists for
  four master types, guarded, and the guard that asserted "none" was amended to
  assert the exact permitted set rather than deleted.

#### Root cause — why the design said Update and four entity types shipped without it

Not an oversight in any one file. Three things lined up:

1. **The lifecycle was written as one word.** The plan says
   "Create → Read → Update → Deactivate/Reactivate". That reads as a single
   idea, and it was implemented as a single idea: the *shape* was built — a
   controller, a service, a status column, a refusal path — and each entity was
   then judged complete by whether it had that shape, not by whether it had all
   five verbs.

2. **Deactivate/Reactivate look like the hard part, and they were.** The
   interesting rules of P1-01 — refuse a deactivation with active children, no
   cascade, no reactivation under an inactive parent, no hard delete (as the
   rule stood before D-24) — all live in the lifecycle transitions. Attention went where the rules were. Update has
   no rule of its own beyond the same-organisation boundary, so it read as
   trivial, and trivial work is the work that gets assumed done.

3. **Nothing was looking.** This is the real cause, and the only one worth
   building a guard for. Every P1-01 test asserted that an operation which exists
   behaves correctly. **An operation that does not exist has no test to fail.**
   Twenty-one negative cases, all proven non-vacuous by mutation, and not one of
   them could have detected a missing endpoint — because a mutation test asks
   "does removing this break something?", and you cannot remove what was never
   written. CI was green, and green meant nothing here.

The same blind spot explains the two half-built `code` fields. Each was accepted
by its controller and carried in form state, so every automated check touching it
passed; nothing asserted that a user could ever *see* it.

#### The guard

`tests/Architecture/LifecycleCompletenessTest.php`. It asserts the presence of
operations rather than their behaviour, which is the class of check P1-01 had
none of.

- **Half one** — every entity in a declared catalogue exposes every lifecycle
  action listed against it. *Mutation: delete the Legal Entity `PUT` route — the
  original defect, reproduced exactly.* **CAUGHT.**
- **Half two** — every collection root reachable in the route table appears in
  that catalogue. This is what stops the guard rotting: the catalogue is
  hand-written and could simply omit a new entity, so the route table, which
  cannot be forgotten, is checked against it. *Mutation: add a create route for a
  new entity and no update.* **CAUGHT.**
- **Half three** — no Organisation route uses `DELETE`. *Mutation: add one.*
  **CAUGHT.**

#### What was built

| Change | Where |
| --- | --- |
| `updateLegalEntity`, `updateBusinessUnit`, `updateDepartment`, `updateTeam`, sharing one `applyUpdate()` | `StructureService` |
| Whitelisted attributes per entity — **parent keys excluded**, so a rename can never re-parent | `StructureService::only()` |
| Four `update()` controller methods and four `PUT` routes | `*Controller`, `routes/web.php` |
| Packaged ISO 3166-1 jurisdiction list — 249 territories, alphabetical by display name, **no runtime external call** | `Support/Jurisdictions.php` |
| Server-side jurisdiction validation against that same list | `Rules/ApprovedJurisdiction.php` |
| Inline row editing, in the same table, with the same controls | `Components/useRowEditor.js` and the five list screens |
| `registered_address` and the two `code` fields exposed for read and edit | Legal Entities, Departments, Teams |
| Set / Change / Clear a manager, subject excluded from their own choices, one-user explanatory state | `Pages/Organisation/Hierarchy.jsx` |
| 14 new automated cases + 3 architecture cases | `StructureUpdateTest`, `LifecycleCompletenessTest` |

The jurisdiction column keeps **display names**, which is what production already
holds: `Singapore` remains valid and remains selected on the existing entity. No
jurisdiction table was created, and no existing value was migrated.

#### Mutation results — 17 mutations

| # | Mutation | Result |
| --- | --- | --- |
| M1 | Drop `registered_address` from the legal-entity allow-list | **CAUGHT** |
| M2 | Drop the jurisdiction check from `applyUpdate()` | **CAUGHT** |
| M3 | Remove `ApprovedJurisdiction` from the controller's update rules | **CAUGHT** |
| M4 | Make the jurisdiction list fetch at runtime | **CAUGHT** |
| M5 | Store ISO codes instead of display names | **CAUGHT** |
| M6 | Rename `updateBusinessUnit` — the operation is missing | **CAUGHT** |
| M7a | Add `business_unit_id` to the department allow-list (service) | **CAUGHT** |
| M7b | Same, exercised over HTTP | **SURVIVED — see below** |
| M7c | Add `business_unit_id` to the controller's update rules only | **SURVIVED — see below** |
| M7d | Both at once — the change somebody who misread the rule would write | **CAUGHT** |
| M8 | Drop the `*_UPDATED` event from `applyUpdate()` | **CAUGHT** |
| M9 | Add `department_id` to the team allow-list | **CAUGHT** |
| M10 | Drop `requireSameOrganisation()` from `applyUpdate()` | **CAUGHT** |
| M11 | Drop the blank-name check | **CAUGHT** |
| M12 | Delete the previous management link instead of ending it | **CAUGHT** |
| M13 | Delete the Legal Entity `PUT` route — the original defect | **CAUGHT** |
| M14 | Add a scoped entity with create and no update | **CAUGHT** |
| M15 | Add a hard-delete route | **CAUGHT** *(guard superseded by D-24 — see §7.3i)* |
| M16 | Return the jurisdiction list unsorted | **CAUGHT** |

**Two mutations survived, and they are reported rather than tidied away.** The
HTTP update path has two independent barriers — the controller's validated-key
set and the service's allow-list — so breaking either one alone leaves the other
holding. That is defence in depth working, and it means no *single* mutation on
that path is observable from outside. The mutation that matters is the one a
person who misunderstood the rule would actually write — "Edit should let you
change the business unit too", applied to both places — and that is CAUGHT. The
reasoning is recorded in the test itself, not only here.

**One case was genuinely vacuous and was rewritten.** The HTTP re-parent case
originally asserted only that the parent had not changed. A request refused for
any unrelated reason satisfies that, so it passed *while the mutation was live*.
It now asserts the rename landed first; without that, the second assertion proves
nothing. Found by mutation. Not by review.

#### Browser verification — Chromium, 1 September 2026

Against a local throwaway database, seeded with structure that is **not** real
and never leaves the development machine. Recorded as observed, not expected.

| Observation | Result |
| --- | --- |
| Inline edit on Legal Entities: jurisdiction select offers **250 options** (249 territories + *Not recorded*), first is *Afghanistan* | ✅ alphabetical, packaged |
| Registered address typed and saved; value re-read from the list | ✅ persisted |
| Department renamed *Singapore Retai Sales* → *Singapore Retail Sales* through the screen; business unit unchanged | ✅ correction without a move |
| Management Hierarchy with two users: **Set manager** → **Change manager** + **Clear**; the subject is not offered as their own manager | ✅ |
| Management Hierarchy with one user: no Set control, and a written explanation of why | ✅ |
| Browser console, every screen, both themes | ✅ **no errors** |
| Horizontal page overflow — 6 screens × 10 viewport widths from 390px to 1440px | ✅ **0 of 60** |
| Dark theme, all five screens | ✅ |

**Three defects were found in the browser that CI passed over**, all fixed:

1. **The page slid sideways by 33px at 390px on Legal Entities.** Not the table —
   the add-form. A stretched column takes the label's `max-width: 320px`, and
   320px plus card and canvas padding exceeds a 390px viewport minus the rail.
   Fields go fluid below 720px; `min-width: 0` is the half that mattered, because
   a `min-width` larger than the space available beats any `max-width`.
2. **The same slide, 12px, at 768px on Legal Entities.** The table containment
   rule stopped at 720px while a six-column table needs it well above that. The
   breakpoint now belongs to the widest table, not to the phone.
3. **Opening an editing row reflowed the rows around it.** The other rows'
   Edit/Deactivate buttons broke onto two lines as the actions column narrowed.
   The actions column now shrink-wraps and does not wrap.

Also corrected during the polish gate: every inline-edit field's accessible name
now identifies its record (*"Name of Singapore Retail Sales"*) rather than
repeating a bare *"Name"* that collided with the add-form's field; and the move
control carries a visible **MOVE TO** caption above it, so that the difference
between correcting a name and restructuring the company is legible on the screen
rather than only in the code.

#### What this correction did not do

No schema change. No new migration. No production data touched. No hard delete
introduced **in this pass** — D-24 introduced a guarded one afterwards, §7.3i. No P1-02, P1-03, P1-04 or P1-05 capability built early — the
hierarchy screen's one-user state **says** that adding users belongs to User
Management rather than quietly providing it. The accepted UI foundation was not
redesigned; the changes to it are the controls the missing operations need, plus
the three responsive and consistency defects above.

### 7.3i D-24 — guarded permanent delete — 1 September 2026

The Product Owner changed one earlier lifecycle decision. The blanket "no hard
delete anywhere" rule made a mis-typed master record **permanent garbage**, and
that consequence was not intended by it.

The rule is superseded, not withdrawn. It still governs every record that is
used, and its own reasoning — *"a deleted row makes past decisions
unexplainable"* — is exactly why the exception is guarded rather than general.
The original text is preserved and annotated in `P1-01-ORGANISATION-PLAN.md` §6
and §8, `P1-01-ORGANISATION-DESIGN.md` §3 and negative case 13, and both Product
Owner test scripts. `PHASE-1-PLAN.md` D-24 is the source of truth.

#### What was built

| | |
| --- | --- |
| Service | `purgeLegalEntity`, `purgeBusinessUnit`, `purgeDepartment`, `purgeTeam`, over one `applyPurge()` |
| Guard | `Support/PurgeDependencies.php` |
| Routes | four `DELETE` routes, behind `RequireSystemAdministrator` |
| Events | `legal_entity.purged`, `business_unit.purged`, `department.purged`, `team.purged` |
| UI | `Delete permanently` per row, a native `<dialog>` confirmation, and a per-screen legend distinguishing Edit / Deactivate / Delete permanently |
| Tests | `PurgeGuardTest` — 23 cases; `LifecycleCompletenessTest` and `AccessBoundaryTest` amended |

**No schema change was required and none was written.** Every foreign key into
the four purgeable tables is already `ON DELETE NO ACTION`, so the database
refuses an orphan even if the service guard were ever wrong — asserted by
`test_no_foreign_key_into_a_purgeable_table_cascades`.

#### The one design decision worth stating

**The dependency check reads the schema; it is not a hand-written list.**

D-24 requires that a purge is refused when *"no other durable P1-01 record
references it"*. That is a claim about the whole schema, and a hand-written
checklist only knows what was true on the day it was written — a table added by
P1-03 or P1-05 with a foreign key to `teams` would simply not be on it, and the
purge would succeed while a row still pointed at the destroyed record.

So `PurgeDependencies` walks the foreign keys. A new referencing table blocks
the purge on the day its migration lands, with no change to that file. What a
new table *does* need is a sentence in business language, and
`test_every_referencing_table_has_business_language_for_its_refusal` fails until
it has one — so the generic fallback is a safety net rather than somewhere to
leave things.

Two properties follow for free, and both are asserted rather than assumed:
status is never consulted, so an **inactive** child and an **ended** membership
block exactly as a live one does; and nothing is ever cascaded, because the code
that would do the cascading does not exist.

#### The wording is the approved one, not a new one

The shared UI standard already has a **Recycle Bin / Soft Delete** archetype
(§5.8), and in it a system administrator's destructive action is *"a
confirm-gated labeled `Delete permanently`"*. So the label, the confirm gate and
the role are taken from the approved design system rather than invented for this
change.

P1-01 has **no recycle bin**, and none was built: §5.8 is a later archetype with
restore, buckets and an "empty everything" guard, and D-24 asks for a guarded
purge on the record itself. What was reused is the wording and the confirm gate;
what was not built is a whole archetype the unit has no need for.

The confirmation is a native `<dialog>` opened with `showModal()` rather than a
div dressed as one — that brings the focus trap, Escape, inert background and
the accessible modal role with it, and every one of those is something a
hand-rolled overlay gets wrong on the one screen where getting it wrong destroys
a record. Focus opens on **Cancel**; a dialog that opens with Delete focused
turns a stray Enter into a permanent deletion.

#### The second check

D-24 §4 requires the dependency state to be re-read inside the write
transaction. It is, and it is a **locking** read — under MySQL's REPEATABLE READ
a plain `SELECT` inside the transaction reads the transaction's own snapshot and
would miss precisely the row the second check exists to catch.

That is asserted structurally rather than behaviourally, and deliberately: in
any single-threaded test the second check is indistinguishable from the first,
so a behavioural assertion would pass with it deleted. `PurgeGuardTest` asserts
that `applyPurge()` calls the check twice and that the inner call is locking.
Mutations P6 and P7 confirm both halves.

#### Mutation results — 16 mutations, 16 caught

| # | Mutation | Result |
| --- | --- | --- |
| P1 | Count only **active** departments and teams as dependencies | **CAUGHT** |
| P2 | Count only **current** memberships, ignoring ended history | **CAUGHT** |
| P3 | Drop the D-14 junction from the reference walk | **CAUGHT** |
| P4 | Drop `requireSameOrganisation()` from `applyPurge()` | **CAUGHT** |
| P5 | Drop the security event from `applyPurge()` | **CAUGHT** |
| P6 | Remove the recheck inside the write transaction | **CAUGHT** |
| P7 | Make the inner recheck a non-locking read | **CAUGHT** |
| P8 | Register a `DELETE` for team memberships | **CAUGHT** |
| P9 | Register a `DELETE` for the organisation profile | **CAUGHT** |
| P10 | Move a purge route outside the administrator gate | **CAUGHT** |
| P11 | Add a referencing table with no business-language label | **CAUGHT** |
| P12 | Expose a database word in the refusal | **CAUGHT** |
| P13 | Cascade the children instead of refusing | **CAUGHT** |
| P14 | Make a foreign key `ON DELETE CASCADE` | **CAUGHT** |
| P15 | Hardcode raw Violet-Red in the destructive action | **CAUGHT** |
| P16 | Define the danger tokens in the light theme only | **CAUGHT** |

P1 and P2 are the two that matter. Each is the change somebody would make by
pattern-matching on `deactivateBusinessUnit()`, which legitimately *does* filter
to active children — and each leaves the other half of its test passing, so a
single-state case would have missed it.

P15 and P16 exist because Violet-Red as text is 1.33:1 on the dark card, and
this is the newest place a semantic colour is used as text. The defect that
produced §7.3e would otherwise have repeated itself on the one control that
destroys a record.

#### Browser verification — Chromium, 1 September 2026

Local throwaway data. Recorded as observed.

| Observation | Result |
| --- | --- |
| Confirmation dialog opens with focus on **Cancel**, not on the destructive button — both themes | ✅ |
| **Escape** closes the dialog and destroys nothing — both themes | ✅ |
| Refusal, verbatim: *"This business unit cannot be permanently deleted because it has 1 department. Deactivate it instead."* | ✅ business language, no database terms |
| After the refusal the business unit is still listed | ✅ nothing changed |
| Purging an unused team removes that team and nothing else | ✅ |
| Page overflow at 390 / 768 / 1440, dialog open and closed | ✅ **none** |
| Browser console | ✅ **no errors** |
| Dark theme: danger text and the solid confirm button | ✅ 5.12:1 and 8.05:1, measured |

Contrast values were computed before the colours were chosen, not after — the
light card carries `#991547` at 8.23:1 and the dark card a lighter tint at
5.12:1, and both dark blocks define them so an explicit Dark choice and a system
Dark preference give the same palette.

#### A third responsive defect, and the end of guessing breakpoints

Adding a third row action pushed Legal Entities **122px past the canvas at
1101px** — one pixel above the containment rule that had been moved to 1100px
earlier the same day, which had itself replaced a 720px rule for the same
reason.

Two guessed breakpoints, both overtaken by the next column added. A width that
has to be re-guessed every time the content changes is not a fix, so the
breakpoint is gone: the table now sits in a scroller that carries the card
chrome, fills it when the table fits and scrolls inside it when it does not, at
every width there is. Re-measured across **13 viewport widths from 360px to
1920px × 6 screens — 0 of 78 combinations overflow**, against 3 of 60 before.

Also corrected during the polish gate: the lifecycle legend carried three CSS
declarations that did nothing, because the rule sat above `.org-note` and lost
to it on source order. Dead rules were removed rather than made to win —
`.org-note`'s readable measure is the right answer for prose, and a note running
the full width of a 1920px screen is a note nobody reads.

#### One scope gap found by the re-audit — NOT IMPLEMENTED, schema change required

`P1-01-ORGANISATION-PLAN.md` §5 lists the Organisation's data points as
*"name, legal/display name, **primary legal entity**, country, timezone, status,
timestamps"*.

`P1-01-ORGANISATION-DESIGN.md` §2.1 does not carry a primary-legal-entity
column, and gives no reason for dropping it. The table does not have one, the
Company Profile screen does not show one, and no decision records the omission.
It is a silent PLAN → DESIGN drop, and it is the only one: every other §5 data
point is present, and `team_memberships` has no `status` column because `left_at`
IS the status, which the design states explicitly.

**It is not implemented, and no migration was written.** Closing it needs a
nullable `primary_legal_entity_id` foreign key on `organisations`, and the
Product Owner's instruction is explicit: *"If implementing this safely requires
an unexpected schema change, STOP and explain before changing schema."* Three
things need deciding before that column exists:

1. Whether a primary legal entity is wanted at all, or whether D-14 already
   answers it — the junction deliberately carries **no "primary" flag**, and the
   design records that as a decision, because an attribute there is *"the first
   thing a later unit reads as employment or entitlement"*. A primary pointer on
   the organisation is a different shape, but it is adjacent enough to be worth
   asking about rather than assuming.
2. Whether purging a legal entity that is the organisation's primary should be
   refused — under D-24 it would have to be, and that is a new dependency the
   guard would pick up automatically once the foreign key exists.
3. Whether the Company Profile can be created before any legal entity exists.
   It can today, so the column has to be nullable, which makes "primary" an
   optional designation rather than a required one.

**Everything else in the D-24 amendment and the scope completion is delivered.**
This one item is reported and stopped on, as directed.

### 7.4 Outstanding for P1-01 — executable now

**Checks 5a and 2 are now observed** (§7.3b, §7.3f). Four checks remain —
**3 (to completion), 4, 5, 7 and 9** — all executable in a browser by the
existing System Administrator, and all subject to the real-data rule below.

They are recorded as **not yet observed** rather than inferred. Each is covered
by a test proven non-vacuous by mutation, but a passing test is not the same
claim as an observed production result.

Checks 4, 5 and 9 are **conditional on the real organisation genuinely having
that shape**. If it does not, they are marked NOT CURRENTLY OBSERVABLE WITH REAL
PRODUCTION DATA and carried forward — that is a data condition, not an
implementation defect.

The steps are written for the Product Owner in
`P1-01-ORGANISATION-PRODUCT-OWNER-TEST-SCRIPT.md`, which carries the
permanent-data warning in full. **Amended by D-24:** a record you create and
never use can now be permanently deleted, but a record that anything uses cannot
— and an inactive child or an ended membership still counts as usage. So the
warning stands for every record a check actually exercises.

### 7.5 The seven checks

**No check may be satisfied by inventing business data.** Product Owner
direction, 31 August 2026: only real Organisation, Legal Entity, Business Unit,
Department and Team records are created; no second legal entity or business unit
is invented; no department is moved where it does not genuinely belong; and a
team membership is recorded only if it is factually correct.

A refused deactivation is exempt from that constraint — it makes no data change,
which is the point of the check.

Where a check cannot be exercised without creating false or misleading permanent
business history, it is marked **NOT CURRENTLY OBSERVABLE WITH REAL PRODUCTION
DATA**, keeps its mutation-proven automated evidence, and its live observation is
carried to the first legitimate opportunity. **That is not an implementation
defect**. Under D-24 an unused record can be permanently deleted, but the
records these checks create are used **by the check itself** — a department under
a business unit, a membership on a team — so each one is permanent the moment it
serves its purpose. Inventing structure to satisfy a check is still not
reversible.

| # | Check | Condition | Expected | Observed |
| --- | --- | --- | --- | --- |
| 2 | Signed-in System Administrator reaches Organisation | Unconditional | Screen renders | **OBSERVED** 1 Sep 2026 — §7.3f |
| 3 | Create the organisation, and the legal entities, business units, departments and teams that genuinely exist | Unconditional | Persisted | |
| 5a | **D-16:** the administrator who created the profile carries that `organisation_id` | Follows from 3 | `users_with_organisation` 0 → 1 | **OBSERVED** 1 Sep 2026 — §7.3b |
| 7 | Deactivate a business unit that genuinely has an active department | Needs one real business unit with one real department. **No data change either way** | Refused, children named | |
| 4 | One business unit ↔ two legal entities, and one legal entity ↔ two business units | **Only if the real organisation genuinely has that shape** | Both permitted | |
| 9 | Move a department between business units | **Only if a legitimate structural move exists** | Permitted, event emitted | |
| 5 | Add a team member, then remove | **Only if that membership is factually correct** | `left_at` set, row retained | |

---

## 8. Definition of Done

| # | Condition | Status |
| --- | --- | --- |
| 1 | Every §2 table created; every lifecycle rule enforced | ✅ |
| 2 | All 21 negative cases automated and proven non-vacuous | ✅ |
| 3 | ~~No DELETE route anywhere in the unit~~ **D-24:** `DELETE` for exactly four guarded master-record purges, asserted as an equality; none for the Organisation, membership history or management history; no cascade | ✅ §7.3i |
| 4 | No roles, permissions, domains, scopes or sensitivity schema | ✅ |
| 4a | `users.organisation_id` nullable, one writer, `tenant_id` read nowhere | ✅ |
| 5 | Organisation is the first navigable item; nothing else navigable | ✅ |
| 6 | All production checks executed and recorded | **Partial** — checks 1, 8, 10, 11, the D-16 schema claim, **2** and **5a** observed (§7.1, §7.2, §7.3b, §7.3f); checks 3 (partial), 4, 5, 7 and 9 outstanding (§7.3g and the Product Owner Test Script); **check 6 deferred to P1-03** (§7.3) |
| 7 | Apache boundary, 403 gate, ACME and both checksums pass unchanged | ✅ §7.1 |
| 8 | Explicit Product Owner acceptance | ⏳ |

**A green CI run does not unlock P1-02.**
