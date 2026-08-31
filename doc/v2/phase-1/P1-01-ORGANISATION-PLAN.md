# P1-01 — Organisation — PLAN

**Status:** PLAN — awaiting Product Owner review. **Planning documentation only.**
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
  └── Legal Entity
```

A **separate axis**, not a level of the structural tree. A legal entity is who
signs contracts and employs people; a business unit is how work is organised.
They frequently do not align — one legal entity can span several business units,
and one business unit can operate across several legal entities.

**Proposed:** legal entity attaches at business unit level, optional. §10 D-14
puts that to the Product Owner rather than assuming it.

### 3.3 Management hierarchy — who reports to whom

A **third** relationship: person → manager. It is not the structural tree.
People report across departments, managers change without the department
changing, and the source document is explicit that *"hierarchy is authoritative
for manager/team scope"* — so it must be recorded in its own right.

P1-01 records the relationship. **P1-05 decides what a manager can see.**

> P1-01 has no `users` beyond the P1-00 identity table. Team membership and
> management relationships reference that table. Whether a person may exist
> without a user record is **D-15** (§10).

---

## 4. Relationships

| Relationship | Cardinality | Required? |
| --- | --- | --- |
| Organisation → Legal Entity | 1 → many | at least one |
| Organisation → Business Unit | 1 → many | at least one |
| Business Unit → Department | 1 → many | optional |
| Department → Team | 1 → many | optional |
| Business Unit → Legal Entity | many → 1 | optional — **D-14** |
| Team ↔ Person | many → many | optional |
| Person → Manager | many → 1 | optional |

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
| **Organisation** | name, legal/display name, primary legal entity, country, timezone, status, timestamps |
| **Legal Entity** | organisation, name, registration/company number, jurisdiction, registered address, status |
| **Business Unit** | organisation, name, code, optional legal entity, status |
| **Department** | organisation, business unit, name, code, status |
| **Team** | organisation, department, name, code, status |
| **Team membership** | team, person, joined/left dates, status |
| **Management relationship** | person, manager, effective from/to |

Codes are for administrator correlation and import. **They are never identity
keys** — the same lesson as P1-00, where email is carried but `oid` is the key.

---

## 6. Lifecycle and status

Every structural record has **active** or **inactive**. Nothing is hard-deleted
by default.

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
| Hard delete | **Not offered.** Deactivation only |
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
token, no personal data beyond the person reference.

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
| 10 | Person managing themselves | Refused |
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
| 5 | No hard delete exists on any route |
| 6 | Structural moves are recorded as scope-affecting events |
| 7 | **No business-domain access is granted anywhere in this unit** |
| 8 | A System Administrator gains no business data through organisation context |
| 9 | No roles, permissions, domains, scopes or sensitivity schema is created |
| 10 | Screens meet the approved design system, light and dark, responsive, WCAG AA |
| 11 | Apache boundary, 403 exposure gate, ACME check and both checksums still pass |
| 12 | Explicit Product Owner acceptance. A green CI run does not unlock P1-02 |

---

## 12. Product Owner decisions

Two, and only two. Both are genuine forks where a wrong guess would be
expensive to unpick later; everything else follows from the source documents.

### D-14 — Where a legal entity attaches · **BLOCKING**

Legal structure and operating structure rarely align, and the source document
lists Legal Entities as a peer subscreen without saying how it joins.

| Option | Assessment |
| --- | --- |
| **A (recommended)** — optional legal entity on **business unit** | Matches how organisations usually work: a business unit operates under one employing entity. Optional, so it can be filled in progressively |
| **B** — legal entity on **department** | Finer-grained and occasionally true, but multiplies the records an administrator maintains, for a distinction most organisations do not draw |
| **C** — legal entity on the **person** | Most accurate for employment reality and the most work; better suited to P1-03, which owns people |

Choosing A does not preclude C later — a person-level attribution can be added
in P1-03 without changing the structural tree.

### D-15 — Whether a "person" can exist without a SemantIQ user · **BLOCKING**

Team membership and management relationships reference people. Today the only
people SemantIQ knows are those with a `users` row from P1-00 — created only by
bootstrap or, from P1-03, by administrator provisioning.

| Option | Assessment |
| --- | --- |
| **A (recommended)** — structure references **`users` only** | Simplest and safest. One identity table, no shadow people, no reconciliation. It means hierarchy can only be built for people who exist in SemantIQ, which in practice means P1-03 lands before an organisation chart is fully populated |
| **B** — a separate `people` table, optionally linked to a user | Lets the full chart be modelled before users exist, at the cost of two sources of truth for "who someone is" and a merge problem the moment they sign in |

**A is recommended and the consequence stated plainly:** with A, P1-01 delivers
the structure and the *relationships that can be filled in today*, and the chart
becomes fully populated once P1-03 provisions users. B trades that sequencing
constraint for a duplicate-identity problem, which is the more expensive of the
two.

---

## 13. Stop point

**This PLAN stops here.** DESIGN begins only when the Product Owner has approved
it and decided **D-14** and **D-15**.

No migration, model, route, controller, screen, seed or test is created before
that design is approved.
