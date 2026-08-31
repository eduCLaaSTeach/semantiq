# P1-01 — Organisation — VERIFICATION

**Status:** BUILD VERIFIED — awaiting Product Owner acceptance.
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

## 7. Production verification

**Not yet executed.** This section is completed after deployment, with observed
output, before acceptance is requested.

| # | Check | Expected | Observed |
| --- | --- | --- | --- |
| 1 | `/console/organisation` anonymous | Redirect to login; no structure | |
| 2 | Signed-in System Administrator | Organisation screen renders | |
| 3 | Create organisation, legal entity, business unit, department, team | Persisted | |
| 4 | One business unit ↔ two legal entities, and one legal entity ↔ two business units | Both permitted — the D-14 shape | |
| 5 | Add a team member, then remove | `left_at` set, row retained | |
| 5a | **D-16:** the administrator who created the profile carries that `organisation_id` | Set, non-NULL | |
| 6 | Set a manager, then attempt a cycle | Cycle refused | |
| 7 | Deactivate a business unit with active departments | Refused, children named | |
| 8 | Attempt a hard delete on any route | No such route | |
| 9 | Move a department between business units | Permitted, event emitted | |
| 10 | Exposure gate, ACME, both checksums | Unchanged and passing | |
| 11 | `semantiq:health` | Green | |

---

## 8. Definition of Done

| # | Condition | Status |
| --- | --- | --- |
| 1 | Every §2 table created; every lifecycle rule enforced | ✅ |
| 2 | All 21 negative cases automated and proven non-vacuous | ✅ |
| 3 | No DELETE route anywhere in the unit | ✅ |
| 4 | No roles, permissions, domains, scopes or sensitivity schema | ✅ |
| 4a | `users.organisation_id` nullable, one writer, `tenant_id` read nowhere | ✅ |
| 5 | Organisation is the first navigable item; nothing else navigable | ✅ |
| 6 | All 11 production checks executed and recorded | ⏳ §7 |
| 7 | Apache boundary, 403 gate, ACME and both checksums pass unchanged | ⏳ §7 |
| 8 | Explicit Product Owner acceptance | ⏳ |

**A green CI run does not unlock P1-02.**
