# P1-01 — Organisation — DESIGN

**Status:** DESIGN — awaiting Product Owner review. **Documentation only.**
**Unit:** P1-01 (Phase 1 delivery order 3)
**PLAN:** `P1-01-ORGANISATION-PLAN.md` — **APPROVED**, D-14 and D-15 decided
**Predecessor:** P1-00 — ACCEPTED 31 August 2026
**UI standard:** `doc/design-system/ui-and-ux-layout-template-shared.md`

> Nothing here is implemented. No migration, model, route, controller, screen,
> seed or test has been created.

---

## 1. What this unit is, in one line

It records organisational structure. **It grants nothing.**

Source requirement: *"no business-domain access is granted here"* and *"deleting
or restructuring hierarchy must not silently broaden access."* Every decision
below serves those two sentences.

There is one shape of mistake this design is built to prevent: code in P1-01
that answers **"may they?"**. Structure is data. P1-05 reads it and decides
access. If a helper here starts resolving entitlements, the boundary is gone and
nobody will notice until P1-05 disagrees with it.

---

## 2. Data model

Three separate things, kept separate: a structural tree, a legal axis, and a
management chain.

```text
organisations
  ├── legal_entities                        the legal axis
  ├── business_units                        the structural tree
  │     ├── departments
  │     │     └── teams
  │     │           └── team_memberships  → users
  │     └── business_unit_legal_entity    ⇄ legal_entities   (many-to-many, D-14)
  └── management_relationships            → users, users
```

### 2.1 `organisations`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint unsigned, PK | |
| `name` | string(255) | Display name |
| `legal_name` | string(255), nullable | |
| `country` | string(2), nullable | ISO 3166-1 alpha-2 |
| `timezone` | string(64), nullable | |
| `status` | enum(`active`,`inactive`) | |
| timestamps | | |

Release 1 is single-tenant, so exactly one row is expected. The table exists
anyway because every other table carries `organisation_id`, and that column is
what keeps the boundary real without building multi-tenancy. **No tenant
resolution, no switching, no cross-organisation queries.**

### 2.2 `legal_entities`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint unsigned, PK | |
| `organisation_id` | FK → organisations | |
| `name` | string(255) | |
| `registration_number` | string(64), nullable | |
| `jurisdiction` | string(64), nullable | |
| `registered_address` | text, nullable | |
| `status` | enum(`active`,`inactive`) | |
| timestamps | | |

```text
UNIQUE (organisation_id, name)          legal_entities_org_name_uq
```

### 2.3 `business_units`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint unsigned, PK | |
| `organisation_id` | FK → organisations | |
| `name` | string(255) | |
| `code` | string(32), nullable | Administrator correlation and import only. **Never an identity key** |
| `status` | enum(`active`,`inactive`) | |
| timestamps | | |

```text
UNIQUE (organisation_id, name)          business_units_org_name_uq
UNIQUE (organisation_id, code)          business_units_org_code_uq
```

**No `legal_entity_id` column.** That was the rejected model — see §2.6.

### 2.4 `departments`

`id` · `organisation_id` · `business_unit_id` · `name` · `code` · `status` · timestamps

```text
UNIQUE (business_unit_id, name)         departments_bu_name_uq
INDEX  (organisation_id)                departments_org_idx
```

### 2.5 `teams`

`id` · `organisation_id` · `department_id` · `name` · `code` · `status` · timestamps

```text
UNIQUE (department_id, name)            teams_dept_name_uq
INDEX  (organisation_id)                teams_org_idx
```

### 2.6 `business_unit_legal_entity` — the D-14 junction

| Column | Type |
| --- | --- |
| `id` | bigint unsigned, PK |
| `organisation_id` | FK → organisations |
| `business_unit_id` | FK → business_units |
| `legal_entity_id` | FK → legal_entities |
| timestamps | |

```text
UNIQUE (business_unit_id, legal_entity_id)   bu_le_pair_uq
INDEX  (legal_entity_id)                      bu_le_entity_idx
```

**Association only.** No dates, no percentages, no "primary" flag, no attributes
of any kind. An attribute here would be the first thing a later unit reads as
employment or entitlement, and D-14 states the association grants nothing.

A single-parent `legal_entity_id` on `business_units` was proposed and
**rejected**: the PLAN itself said one legal entity may span several business
units and one business unit may operate across several legal entities, so a
single parent would write a falsehood into the data the first day that stopped
being true.

### 2.7 `team_memberships`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint unsigned, PK | |
| `organisation_id` | FK → organisations | |
| `team_id` | FK → teams | |
| `user_id` | FK → users | **D-15: `users` only.** No `people` table |
| `joined_at` | date | |
| `left_at` | date, nullable | NULL means current |
| timestamps | | |

```text
UNIQUE (team_id, user_id, joined_at)    team_memberships_uq
INDEX  (user_id)                        team_memberships_user_idx
```

`left_at` rather than deletion: *"who was in this team in March"* is a question
P1-07 access review will ask, and a deleted row cannot answer it.

### 2.8 `management_relationships`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint unsigned, PK | |
| `organisation_id` | FK → organisations | |
| `user_id` | FK → users | The report |
| `manager_id` | FK → users | The manager |
| `effective_from` | date | |
| `effective_to` | date, nullable | |
| timestamps | | |

```text
UNIQUE (user_id, effective_from)        mgmt_user_from_uq
INDEX  (manager_id)                     mgmt_manager_idx
```

A user has **one** current manager: one row with `effective_to IS NULL`.
Enforced in the application and asserted by test — a partial unique index is not
portable across MySQL and the SQLite used by the suite, and a guard that only
exists in production is a guard nobody has run.

### 2.9 What is deliberately absent

No roles, permissions, domains, scopes, sensitivity, entitlements. No `people`
table. No `legal_entity_id` on any person or membership row. `P1BoundaryTest`
already fails if a migration creates business schema; its forbidden list gains
nothing here because `organisations` and `teams` become **owned by P1-01** — a
reviewed transfer, exactly as `users` moved to P1-00.

---

## 3. Lifecycle

Two states, `active` and `inactive`. **No hard delete anywhere, on any route.**

| Action | Behaviour |
| --- | --- |
| Deactivate a node with **active children** | **Refused**, naming the blocking children |
| Deactivate a leaf | Permitted |
| Deactivate a node with active memberships | **Refused** |
| Reactivate | Permitted only if the parent is active |
| Move a node to a new parent | Permitted, recorded, **flagged scope-affecting** |
| Remove a team member | `left_at` set; row retained |
| End a management relationship | `effective_to` set; row retained |
| Hard delete | **Not offered.** No route, no controller action, no model method |

Refusing the cascade is the deliberate choice. A cascade is convenient exactly
once and unexplainable every time afterwards, and the source document warns that
restructuring must not silently broaden access — a silent cascade is precisely
how structure changes underneath someone's scope.

---

## 4. Validation

Each rule is a service-layer invariant with a negative test. None is a UI
affordance; the screen may also prevent it, but the screen is not the control.

| # | Rule | Failure |
| --- | --- | --- |
| 1 | Parent must exist and be in the same organisation | Refused |
| 2 | Parent must be active when creating a child | Refused |
| 3 | A team's department must belong to the team's business unit | Refused |
| 4 | Depth fixed: Organisation → Business Unit → Department → Team | No route to nest further |
| 5 | Junction rows must reference a business unit and legal entity in the **same** organisation | Refused |
| 6 | A user may not be their own manager | Refused |
| 7 | **No cycle in the management chain** | Refused — §4.1 |
| 8 | One current manager per user | Refused |
| 9 | A membership's user and team must share an organisation | Refused |
| 10 | Duplicate current membership of the same team | Refused |

### 4.1 Cycle prevention

The structural tree cannot cycle: each node has one typed parent of a different
type, so a cycle is unrepresentable. **Nothing to enforce, so nothing is
enforced** — a cycle check there would be theatre.

The management chain **can** cycle: `user_id` and `manager_id` are both users.
Before writing a relationship, walk from the proposed manager up the current
chain; if the subject appears, refuse. The walk is bounded by a depth limit so a
pre-existing cycle from bad data cannot hang the request.

This matters because P1-05 will walk that chain to resolve manager scope. A
cycle would be an infinite loop in the access engine, and it must be
unrepresentable before that engine exists.

---

## 5. Routes and screens

`System Administration → Organisation`. Checked against the Apache-blocked
directory list: `organisation` is not among them. `RoutePrefixCollisionTest`
now guards both directions, so a future clash fails CI rather than production.

| Method | Path | Screen |
| --- | --- | --- |
| GET | `/console/organisation` | Company Profile |
| PUT | `/console/organisation` | — |
| GET | `/console/organisation/legal-entities` | Legal Entities |
| GET | `/console/organisation/business-units` | Business Units |
| GET | `/console/organisation/business-units/{id}` | Detail, incl. legal-entity associations |
| GET | `/console/organisation/departments` | Departments |
| GET | `/console/organisation/teams` | Teams |
| GET | `/console/organisation/teams/{id}` | Detail, incl. membership |
| GET | `/console/organisation/hierarchy` | Management Hierarchy |

Create, update, deactivate and reactivate are POST/PUT/PATCH under the matching
collection path. **No DELETE verb is registered anywhere in this unit** — a test
asserts the route table contains none.

All screens sit inside `/console`, behind the existing session middleware, and
use the shared standard's list and detail archetypes with the approved tokens,
Montserrat/Source Sans 3, light and dark, WCAG AA.

### 5.1 Navigation

`NavigationRegistry` from P1-BASE refuses a node without a label, icon, route
name and policy key, and refuses a route name that does not resolve. P1-01
registers the Organisation node — **the first navigable item in SemantIQ.**
`DenyAllNavigationAuthorizer` is replaced with one that admits System
Administrators only.

---

## 6. Backend authorisation

Every route re-authorises. Menu visibility is never the control.

```text
session valid  →  user active  →  platform_role = system_administrator  →  handler
```

- The check is a single explicit gate, refusing anyone who is not a System
  Administrator. It is **not** a role framework, and P1-05 replaces the D-09
  seam it reads.
- **Administering structure grants no business data.** There is no business data
  in this unit; the guard against a future confusion is negative test 4 (§7),
  which asserts against the authorisation boundary rather than an empty result.
- No helper in this unit answers "may this user see domain X". If one appears,
  it belongs to P1-05.

---

## 7. Negative tests

Each must **fail when its guard is removed** — the P1-BASE and P1-00 convention.
In P1-00 four tests were vacuous before they were right, every one caught by
mutation and none by review, so each row names the mutation.

| # | Case | Expected | Mutation that must fail it |
| --- | --- | --- | --- |
| 1 | Anonymous request to any Organisation route | Refused, no structure disclosed | Remove the session middleware |
| 2 | Authenticated non-administrator | **Refused** | Drop the platform-role gate |
| 3 | System Administrator reads structure | Permitted | — (positive control) |
| 4 | System Administrator seeks business data via organisation context | **Refused** | Add a domain accessor to the organisation service |
| 5 | Record created without an organisation | Refused | Make `organisation_id` nullable |
| 6 | Parent in a different organisation | Refused | Drop the same-organisation check |
| 7 | Team whose department is outside its business unit | Refused | Drop rule 3 |
| 8 | Cycle in the management chain | Refused | Remove the chain walk |
| 9 | User managing themselves | Refused | Drop the self-manager check |
| 10 | Second current manager for one user | Refused | Drop the single-current-manager check |
| 11 | Deactivate a node with active children | Refused, children named | Cascade instead of refusing |
| 12 | Deactivate a node with active memberships | Refused | Drop the membership check |
| 13 | Hard delete via any route | **No such route** | Register a DELETE route |
| 14 | Junction row crossing organisations | Refused | Drop the junction organisation check |
| 15 | Duplicate current team membership | Refused | Drop the uniqueness check |
| 16 | Move recorded as scope-affecting | Event emitted | Stop emitting on move |
| 17 | Refusal bodies | No trace, framework internals or unauthorised structure | Render the exception message |

Case 4 is the one most likely to be skipped, exactly as in P1-00: there is no
business data here to withhold, so a test that merely finds nothing would pass
for the wrong reason and keep passing after the boundary was removed.

---

## 8. Scope-affecting events

Per **D-12**: structured, redacted, through the existing `SecurityEventLogger`.
**No audit table** — P1-08 owns durable storage, the catalogue and the UI.

`organisation.updated` · `legal_entity.created|updated|deactivated` ·
`business_unit.created|updated|deactivated|moved` ·
`department.created|updated|deactivated|moved` ·
`team.created|updated|deactivated|moved` ·
`team.member.added|removed` · `management.relationship.set|cleared` ·
`business_unit.legal_entity.associated|dissociated`

`SecurityEventLogger` accepts a fixed context shape and rejects unknown keys, so
this unit extends the allow-list with structural identifiers only — no personal
data beyond a user reference, no free text.

The `*.moved` events carry the most weight: a move is the change most likely to
alter someone's future scope.

---

## 9. Migration order

| # | Migration | Depends on |
| --- | --- | --- |
| 1 | `create_organisations_table` | — |
| 2 | `create_legal_entities_table` | 1 |
| 3 | `create_business_units_table` | 1 |
| 4 | `create_business_unit_legal_entity_table` | 2, 3 |
| 5 | `create_departments_table` | 3 |
| 6 | `create_teams_table` | 5 |
| 7 | `create_team_memberships_table` | 6, `users` |
| 8 | `create_management_relationships_table` | `users` |

All additive; nothing existing is altered, so a rollback to the previous release
keeps working against this schema. Verified in CI against **real MySQL 8.4** —
`MigrationIdentifierLengthTest` guards the 64-character index-name limit, and
the names in §2 are explicit and short for that reason.

**No seed data.** A default organisation row created by migration would be
invented business content; the Company Profile screen creates it.

---

## 10. Production verification

CI proves the rules; these are run against production after deployment and
recorded in the VERIFICATION document with observed output.

| # | Check | Expected |
| --- | --- | --- |
| 1 | `/console/organisation` anonymous | Redirect to login; no structure |
| 2 | Signed-in System Administrator | Organisation screen renders |
| 3 | Create organisation, legal entity, business unit, department, team | Persisted |
| 4 | Associate one business unit with two legal entities, and one legal entity with two business units | Both permitted — the D-14 shape |
| 5 | Add a team member, then remove | `left_at` set, row retained |
| 6 | Set a manager, then attempt a cycle | Cycle refused |
| 7 | Deactivate a business unit with active departments | Refused, children named |
| 8 | Attempt a hard delete on any route | No such route |
| 9 | Move a department between business units | Permitted and event emitted |
| 10 | Exposure gate, ACME, both checksums | Unchanged and passing |
| 11 | `semantiq:health` | Green |

Step 4 is the point of D-14: a model that could not represent both directions
would fail here, which is exactly why the many-to-one proposal was rejected.

---

## 11. Definition of Done

1. Every §2 table created; every §3 lifecycle rule enforced.
2. All 17 §7 negative cases automated, each proven non-vacuous by its stated mutation.
3. No DELETE route exists anywhere in the unit.
4. No roles, permissions, domains, scopes or sensitivity schema created.
5. Organisation is the first navigable item; nothing else becomes navigable.
6. All 11 §10 production checks executed and recorded.
7. Apache boundary, 403 exposure gate, ACME round trip and both checksums pass unchanged.
8. Explicit Product Owner acceptance. **A green CI run does not unlock P1-02.**

---

## 12. Decisions required

**None.** D-14 and D-15 are decided and this design follows them. Nothing in
building it required a choice the source documents or the PLAN do not already
settle, and inventing one would only hand back work.

---

## 13. Stop point

**DESIGN stops here.** No migration, model, route, controller, screen, seed or
test is created before this design is approved.
