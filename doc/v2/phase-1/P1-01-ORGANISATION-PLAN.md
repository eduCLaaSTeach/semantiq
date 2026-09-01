# P1-01 — Organisation — PLAN

**Status:** **PLAN APPROVED — 31 August 2026.** D-14 and D-15 decided in §12;
Definition of Ready satisfied. **P1-01 DESIGN is authorised.** No implementation.
**Unit:** P1-01 (Phase 1 delivery order 3)
**Predecessor:** P1-00 — **ACCEPTED 31 August 2026**
**Successor:** P1-02 — Identity & SSO Administration (locked)
**Menu:** `System Administration → Organisation`
**Authority:** `doc/SemantIQ_v2_PHASE_1_System_Administration.md` §5 P1-01 ·
Blueprint §2.8 effective access · `doc/v2/phase-1/PHASE-1-PLAN.md` §3–§5

> Nothing here is implemented. No migration, model, route, screen or seed.

---

## 1. Objective

Create the organisational structure **from which access scope is later
derived** — and nothing else.

Source requirement, verbatim: *"Create the organisational structure from which
access scope is later derived."*

P1-01 is a **structural** unit. It stores who the organisation is and how it is
shaped. It grants nothing. The scope engine that reads this structure is P1-05,
and the phrase that governs every decision below is the one from the source:

> **"no business-domain access is granted here."**

### Where this sits in the access model

```text
Identity            P1-00  ✔ accepted
+ Organisation      P1-01  ← this unit: the structure only
+ Role              P1-05
+ Domain            P1-04
+ Scope             P1-05  ← derived FROM this structure, later
+ Sensitivity       P1-05
+ Ownership/Hierarchy  P1-01 records it · P1-05 interprets it
+ Policy            P1-05
= Effective Access
```

P1-01 supplies the second and seventh terms as **data**. Interpreting them into
access is explicitly a later unit's job.

---

## 2. Scope

| In scope | Subscreen |
| --- | --- |
| Organisation (company profile) | Company Profile |
| Legal entities | Legal Entities |
| Business units | Business Units |
| Departments | Departments |
| Teams and team membership | Teams |
| Management/reporting hierarchy | Management Hierarchy |

### Explicitly out of scope

| Out of scope | Owner |
| --- | --- |
| Roles, permissions | P1-05 |
| Business domains | P1-04 |
| Scopes, sensitivity, the access engine, Access Simulator | P1-05 |
| Users, groups, directory sync, user lifecycle | P1-03 |
| Identity & SSO administration | P1-02 |
| Security Status, Access Reviews, **Audit UI and audit tables**, System Health, Administration Home | P1-06 – P1-10 |
| Fabric Configuration, SemantIQ Workplace | Phases 2–3 |

**Team membership is a structural relationship, not an entitlement.** P1-01
records that a person belongs to a team. It does not decide what that lets them
see. If any code in this unit answers "may they?", it is out of scope.

### Multi-tenancy

Release 1 is **single-tenant**. The organisation boundary stays explicit — every
structural record carries its organisation — so a later multi-tenant release is
a data change rather than a rewrite. **No multi-tenant behaviour is built**: no
tenant switching, no cross-tenant queries, no tenant resolution middleware.

---

## 3. Organisation hierarchy model

Two distinct trees, deliberately separate. Conflating them is the classic error
in this domain, and it makes scope wrong later in ways nobody can debug.

### 3.1 Structural tree — where work sits

```text
Organisation
  └── Business Unit
        └── Department
              └── Team
                    └── Team membership (person ↔ team)
```

One parent each, no cycles.

### 3.2 Legal entities — who employs and contracts

```text
Organisation
  ├── Legal Entity      ─┐
  └── Business Unit     ─┴─  optional many-to-many association
```

A **separate axis**, not a level of the structural tree. A legal entity is who
signs contracts and employs people; a business unit is how work is organised.
They frequently do not align — one legal entity can span several business units,
and one business unit can operate across several legal entities.

**Decided (D-14):** business unit ↔ legal entity is an **optional many-to-many
association**, carried in a junction. That follows from the paragraph above
rather than contradicting it: if one legal entity can span several business
units and one business unit can operate across several legal entities, then any
single-parent attachment forces a falsehood into the data on the first day it
stops being true.

The association **grants nothing** — no scope, no permission, no employment
inference. Person-level employing legal entity, if ever required, belongs to
P1-03 and is not pre-built here.

### 3.3 Management hierarchy — who reports to whom

A **third** relationship: person → manager. It is not the structural tree.
People report across departments, managers change without the department
changing, and the source document is explicit that *"hierarchy is authoritative
for manager/team scope"* — so it must be recorded in its own right.

P1-01 records the relationship. **P1-05 decides what a manager can see.**

> **Decided (D-15):** team membership and management relationships reference the
> P1-00 `users` table and nothing else. There is no separate `people` table, so
> there is no second source of truth for who someone is and no reconciliation
> problem later. The accepted consequence is that the chart is only as complete
> as the user list, and fills in once P1-03 provisions users.

---

## 4. Relationships

| Relationship | Cardinality | Required? |
| --- | --- | --- |
| Organisation → Legal Entity | 1 → many | at least one |
| Organisation → Business Unit | 1 → many | at least one |
| Business Unit → Department | 1 → many | optional |
| Department → Team | 1 → many | optional |
| Business Unit ↔ Legal Entity | many ↔ many, via junction | optional |
| Team ↔ User | many ↔ many | optional |
| User → Manager (a User) | many → 1 | optional |

Every record belongs to exactly one organisation. That column is what keeps the
multi-tenant boundary real without building multi-tenancy.

**Depth:** the structural tree is fixed at four levels. A general adjacency tree
would be more flexible and would make every later scope query recursive; the
source names exactly these levels, so P1-01 implements exactly these.

---

## 5. Required data points

Indicative. Exact columns are DESIGN.

| Entity | Data |
| --- | --- |
| **Organisation** | name, legal/display name, **primary legal entity** *(omitted by the DESIGN without a decision; closed by D-25, 1 September 2026)*, country, timezone, status, timestamps |
| **Legal Entity** | organisation, name, registration/company number, jurisdiction, registered address, status |
| **Business Unit** | organisation, name, code, status |
| **Department** | organisation, business unit, name, code, status |
| **Team** | organisation, department, name, code, status |
| **Business Unit ↔ Legal Entity** | organisation, business unit, legal entity — association only, no attributes that could be read as entitlement |
| **Team membership** | team, user, joined/left dates, status |
| **Management relationship** | user, manager (user), effective from/to |

Codes are for administrator correlation and import. **They are never identity
keys** — the same lesson as P1-00, where email is carried but `oid` is the key.

---

## 6. Lifecycle and status

Every structural record has **active** or **inactive**. Nothing is hard-deleted
by default.

> **Amended by D-24, 1 September 2026.** "By default" is now literal rather than
> absolute. A Legal Entity, Business Unit, Department or Team may be permanently
> deleted **only when nothing uses it** — no children active or inactive, no
> associations, no membership history, no other durable reference. Everything
> below still governs every record that is used, and the reason it gives is the
> reason the exception is guarded: a deleted row that something still pointed at
> is precisely what makes past decisions unexplainable. See `PHASE-1-PLAN.md`
> D-24.

| Rule | Reason |
| --- | --- |
| Deactivation is the normal end state | Structure is referenced by later access decisions and by audit; a deleted row makes past decisions unexplainable |
| Deactivating a parent does **not** silently deactivate children | A silent cascade is how structure quietly changes underneath access |
| A deactivated node accepts no new children and no new members | |
| Historical membership is retained with end dates | "Who was in this team in March" is an access-review question P1-07 will ask |

---

## 7. Hierarchy validation

Every rule below must be a **negative test**, not a UI affordance.

| Rule | Why |
| --- | --- |
| No cycles in the structural tree | A cycle makes descendant queries non-terminating |
| **No cycles in the management chain** | A reports to B reports to A is unresolvable, and P1-05 will walk this chain |
| A node's parent must be in the same organisation | The boundary is meaningless if a child can cross it |
| A team's department must belong to the team's business unit | Otherwise the tree lies about itself |
| A person may not manage themselves | |
| Depth is fixed at the four named levels | |
| A person may hold multiple team memberships | Real, and P1-05 must handle it |

---

## 8. Deletion and deactivation

The source document's sharpest constraint:

> **"deleting or restructuring hierarchy must not silently broaden access."**

P1-01 grants no access, so it cannot broaden any today. But it defines the
structure P1-05 will read, and a restructure is exactly how someone silently
inherits a wider scope later. So the rules are set now, while they are cheap:

| Action | Behaviour |
| --- | --- |
| Deactivate a node with active children | **Refused** — deactivate or move children first, explicitly |
| Move a node to a new parent | Permitted, recorded, and **flagged as a scope-affecting change** for the later audit catalogue |
| Hard delete of a **used** record | **Not offered.** Deactivation only |
| Hard delete of an **unused** master record | **D-24:** permitted for a Legal Entity, Business Unit, Department or Team with no dependency of any kind. Guarded, re-checked inside the write transaction, never cascaded, and audited as `*.purged`. Never for the Organisation, membership history or management history |
| Remove a team member | Membership end-dated, not erased |
| Remove a manager relationship | End-dated, not erased |

Refusing a cascade is deliberate: a cascade is convenient exactly once, and
unexplainable every time afterwards.

---

## 9. Audit events required later

Consistent with **D-12** from P1-00: P1-01 emits structured, redacted security
events through the existing logging boundary and **creates no audit table** —
P1-08 owns durable storage, the catalogue and the UI.

Events to emit: `organisation.updated` · `legal_entity.created|updated|deactivated` ·
`business_unit.created|updated|deactivated|moved` ·
`department.created|updated|deactivated|moved` ·
`team.created|updated|deactivated|moved` ·
`team.member.added|removed` · `management.relationship.set|cleared`

The `*.moved` events matter most: a move is the structural change most likely to
alter someone's future scope, and the source document names restructuring
specifically.

**Never logged:** anything that is not a structural identifier — no session, no
token, no personal data beyond the user reference.

---

## 10. Security and negative cases

| # | Case | Required outcome |
| --- | --- | --- |
| 1 | Anonymous request to any Organisation screen | Refused; no structure disclosed |
| 2 | Authenticated user without System Administrator | **Refused** — authentication is not authorisation |
| 3 | A System Administrator reading organisation structure | Permitted — platform administration |
| 4 | A System Administrator attempting to read **business data** via organisation context | **Refused** — SYS-004 still holds |
| 5 | Structural record created without an organisation | Refused |
| 6 | Parent in a different organisation | Refused |
| 7 | Cycle in the structural tree | Refused |
| 8 | Cycle in the management chain | Refused |
| 9 | Team whose department is outside its business unit | Refused |
| 10 | A user managing themselves | Refused |
| 11 | Deactivating a node with active children | Refused, with the reason |
| 12 | Hard delete attempted through any route | Not available |
| 13 | Error and refusal bodies | No stack trace, framework internals or structure beyond what the caller may already see |

Case 4 is the one most likely to be skipped, because P1-01 has no business data
to withhold. As in P1-00 negative case 11, it must assert against the
**authorisation boundary**, not against an empty result.

Each guard must be **deliberately broken and observed to fail** — the P1-BASE
and P1-00 convention. In P1-00 four tests were vacuous before they were right;
every one was caught by mutation, none by review.

---

## 11. Acceptance criteria

| # | Criterion |
| --- | --- |
| 1 | Organisation, legal entities, business units, departments, teams and management hierarchy can be created, updated and deactivated |
| 2 | Team membership and management relationships can be set and ended, with history retained |
| 3 | Every §7 validation rule refuses invalid input, proven by negative test |
| 4 | Deactivating a node with active children is refused |
| 5 | ~~No hard delete exists on any route~~ **D-24:** `DELETE` exists for exactly four master-record purges and for nothing else; each is refused unless the record is completely unused; the Organisation, membership history and management history have no delete on any route |
| 6 | Structural moves are recorded as scope-affecting events |
| 7 | **No business-domain access is granted anywhere in this unit** |
| 8 | A System Administrator gains no business data through organisation context |
| 9 | No roles, permissions, domains, scopes or sensitivity schema is created |
| 10 | Screens meet the approved design system, light and dark, responsive, WCAG AA |
| 11 | Apache boundary, 403 exposure gate, ACME check and both checksums still pass |
| 12 | Explicit Product Owner acceptance. A green CI run does not unlock P1-02 |

---

## 12. Product Owner decisions — **BOTH DECIDED, 31 August 2026**

### D-14 — Business Unit ↔ Legal Entity · **APPROVED — optional many-to-many**

The many-to-one model I proposed was **rejected, correctly**: it contradicted
this plan's own statement that one legal entity may span several business units
and one business unit may operate across several legal entities. A single-parent
attachment would have forced a falsehood into the data the first day that
stopped being true.

Approved model:

- one organisation has many legal entities;
- one organisation has many business units;
- a business unit may be associated with **zero, one or several** legal entities;
- a legal entity may be associated with **zero, one or several** business units.

Legal Entity remains a **separate organisational axis**, never a level in
`Business Unit → Department → Team`.

**The association grants no access.** P1-01 must not infer scope, permissions or
employment from it. Person-level employing legal entity, if later required,
belongs to **P1-03** and must not be pre-built now.

### D-15 — Person representation · **APPROVED — `users` only**

Team memberships and management relationships reference the existing `users`
identity table. **No separate `people` table.**

It is accepted that P1-01 initially carries limited people data and becomes
fully populated once P1-03 provisions additional users. That sequencing
constraint is preferable to duplicate identity records and the reconciliation
problem they create — the same reasoning that made `oid` rather than email the
identity key in P1-00.

---

## 12A. Definition of Ready — **SATISFIED**

| # | Condition | Status |
| --- | --- | --- |
| 1 | PLAN approved by the Product Owner | ✅ |
| 2 | **D-14** decided — business unit ↔ legal entity cardinality | ✅ optional many-to-many |
| 3 | **D-15** decided — person representation | ✅ `users` only |
| 4 | Scope and out-of-scope agreed | ✅ §2 |
| 5 | No settled Phase 1 decision reopened | ✅ |

---

## 13. Stop point

**The PLAN gate is OPEN.** DESIGN is authorised and is specified in
`P1-01-ORGANISATION-DESIGN.md`.

DESIGN is documentation only. No migration, model, route, controller, screen,
seed or test is created before that design is approved.
