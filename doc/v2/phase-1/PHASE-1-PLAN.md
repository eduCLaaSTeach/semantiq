# Phase 1 Plan — System Administration & Security Foundation

**Status:** APPROVED — 30 August 2026. D-01, D-02, D-05 and D-06 decided;
D-03 and D-04 deferred to P1-00. P1-BASE approved as a delivery unit and moved
to DESIGN.
**Authority:** `doc/SemantIQ_v2_PHASE_1_System_Administration.md`
**Parent baseline:** `doc/SemantIQ_v2.2_Ground_Zero_Architecture_Reset_Three_Phase_Blueprint.md`
**UI standard:** `doc/design-system/ui-and-ux-layout-template-shared.md`

This is the Phase 1 orchestration plan: sequence, dependencies, open decisions and
phase-level acceptance. It is not a unit plan. Each delivery unit gets its own
PLAN → DESIGN → VERIFICATION documents, and only one unit is active at a time.

---

## 1. Where the repository actually is

Phase 1 does not start from a working application. It starts from an empty one.
`main` currently contains, in full:

| Path | What it is |
| --- | --- |
| `.github/workflows/deploy-test.yml` | Static deploy proof, active |
| `.github/workflows/ci.yml` | Laravel CI, **parked** — no automatic triggers |
| `.github/workflows/deploy.yml` | Laravel deploy, **parked** — no automatic triggers |
| `doc/design-system/` | Approved CLaaS2SaaS UI/UX standard and brand assets |
| `doc/SemantIQ_v2*.md` | The v2 blueprint and three phase documents |
| `public/index.html` | The deploy test page, currently the live site root |

There is no `composer.json`, no `artisan`, no application code, no schema, no tests.

**What is already proven.** GitHub → cPanel delivery works end to end: SSH
authentication, `rsync` transfer and an HTTPS read-back asserting the deployed
commit all pass. The pipeline is a solved problem and is not a Phase 1 risk.

**What is not yet true.** The server has no application, no `.htaccess` forwarder
and no populated database. `public_html` holds `.well-known/`, `.env` and the test
page. The `.env` on the server has empty `DB_*` and `MICROSOFT_*` values.

---

## 2. The missing unit: P1-BASE

`SemantIQ_v2_PHASE_1_System_Administration.md` §5 begins at **P1-00 — Application
Entry, Login & First-Run Bootstrap**, which presumes a Laravel/React application
exists to host a Login page. None exists.

The blueprint anticipates this. §3.7 Phase 1 Execution Sequence, order 1, is:

> Create the fresh v2 application baseline on the already-established
> Laravel/React/MySQL/cPanel delivery platform; add CI quality gates and no v1
> dependencies.

Order 2 is the Login/bootstrap work that the Phase 1 document calls P1-00.

**APPROVED 30 August 2026.** The product owner confirmed this reading of the
blueprint: establish the fresh Laravel/React/MySQL baseline first, prove
CI/deployment/schema/runtime safety, and only then begin P1-00.

Run the baseline as its own delivery unit, **P1-BASE**, ahead of P1-00, with its
own plan, acceptance and verification record. Reasons:

- Its acceptance is infrastructural (app boots, CI green, deploys, migrates), not
  behavioural. Folding it into P1-00 would mix two unrelated definitions of done
  and make the login unit's evidence harder to read.
- It carries several server-side changes — forwarder, database, deployment
  layout — that must be right before any authentication work is trustworthy.
- P1-00's "must prove" list is a security proof. It should not share a unit with
  scaffolding noise.

Unit plan: `P1-BASE-APPLICATION-BASELINE-PLAN.md` — approved.
Unit design: `P1-BASE-APPLICATION-BASELINE-DESIGN.md` — drafted, awaiting approval.

---

## 3. Delivery sequence

| Order | Unit | Delivers | Gate |
| --- | --- | --- | --- |
| 1 | **P1-BASE** | Laravel 13 / React 19 skeleton, MySQL, CI gates, forwarder, deploy | App reachable and deployable; CI green |
| 2 | **P1-00** | Login, Microsoft SSO, callback validation, session, bootstrap, refusal states | Auth path proven end to end including fail-closed cases |
| 3 | **P1-01** | Organisation, business units, departments, teams, hierarchy, legal entities | Scope source established |
| 4 | **P1-02** | Identity & SSO administration, health, session policy | Identity supportable without exposing secrets |
| 5 | **P1-03** | Users & Groups, directory sync, lifecycle | Users exist with no accidental data access **+ carried gate from P1-01: live multi-user management-cycle refusal** |
| 6 | **P1-04** | Business Domains, owners, defaults | Domains assignable |
| 7 | **P1-05** | Roles, entitlements, scopes, sensitivity, Access Simulator | Effective-access engine proven |
| 8 | **P1-06** | Security Status | Posture legible without security expertise |
| 9 | **P1-07** | Access Reviews | Sensitive access has a review lifecycle |
| 10 | **P1-08** | Audit | Phase 1 activity evidenced |
| 11 | **P1-09** | System Health | Operational failures visible safely |
| 12 | **P1-10** | Administration Home | One accurate roll-up, built last from real sources |

The order follows the Phase 1 document exactly, with P1-BASE inserted ahead of it.
Each unit runs PLAN → approve → DESIGN → approve → EXECUTE → TEST → VERIFY →
ACCEPT. A green CI run does not unlock the next unit.

---

## 4. Decision register

All decisions below were made by the product owner on 30 August 2026 unless
marked DEFERRED. They are settled: they are not reopened without a verified
technical impossibility.

### D-01 — Role and access model — **APPROVED: follow the blueprint**

The SemantIQ authorisation model is:

```text
Identity
+ Platform Role
+ Business Domain
+ Scope
+ Sensitivity
+ Organisation / Team / Ownership relationship
+ Policy
= Effective Access
```

Seven baseline roles: System Administrator, Organisation Administrator,
Executive, Domain Owner / Director, Manager, Business User, Auditor.

Domain, Scope and Sensitivity are **independent authorisation dimensions**.
Scope is never derived from a UI role tier. The design system's five-tier model
is **not** the SemantIQ authorisation engine and is not authoritative for
SemantIQ security. This is recorded as an approved SemantIQ-specific deviation
from the shared UI standard, which sits below the blueprint in the
source-of-truth hierarchy.

**System Administrator does not automatically receive business-domain access** —
not Sales, Finance, People, Learning or any other domain.

**P1-BASE constraint:** no role, domain, scope or sensitivity schema is created
in P1-BASE. Those belong to the later Phase 1 units. P1-BASE establishes only the
architectural and module boundaries that will host them.

### D-02 — Navigation architecture — **APPROVED: three product areas**

> **Amended by D-23, 31 August 2026 — presentation order only.** The sidebar
> renders **SemantIQ Workplace, Fabric Configuration, System Administration**.
> The areas, their contents, their ownership and their delivery phases are
> unchanged. See blueprint section 2.4a.

Top-level product areas are **System Administration**, **Fabric Configuration**
and **SemantIQ Workplace**. SemantIQ is not forced into the design system's four
generic clusters (Workspace, Compliance, Application Administration, System
Administration). Recorded as an approved SemantIQ-specific deviation.

The shared UI standard still governs visual design, sidebar behaviour,
typography, tokens, colour, icons, light/dark themes, responsiveness,
accessibility, page archetypes and component behaviour. It does not govern
SemantIQ's product information architecture.

Audit, Access Reviews and Security Status remain **inside System
Administration**. No separate top-level Compliance area is created.

**Phase 1 rule.** No Phase 2 or Phase 3 menus are prebuilt. P1-BASE creates only
the shell and navigation architecture needed to support the three-area model.
During Phase 1, only implemented and accepted System Administration capabilities
become navigable. Fabric Configuration and SemantIQ Workplace must not expose
fake, placeholder or partially implemented screens merely because the shell can
represent them.

### D-05 — MySQL provisioning and migrations — **APPROVED**

Provisioning and application migration are separate concerns.

*One-time provisioning.* The cPanel MySQL database and user are created as a
one-time infrastructure administration action. Database and user creation is
never built into application code. `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD`
live in the server `.env` only and are never stored in GitHub or the repository.
The design documents this prerequisite and its verification steps.

*Migrations.* Once the database exists and the server `.env` is configured,
schema migration is part of the controlled deployment:
`php artisan migrate --force --no-interaction` over SSH through the deployment
workflow. Manual execution is not the normal production path. **A migration
failure fails the deployment** rather than letting the application continue on an
inconsistent schema. Migration verification is part of P1-BASE acceptance.

For P1-BASE, only Laravel framework baseline tables required by the approved
architecture are created. No SemantIQ business, role, domain, Fabric or
future-phase schema.

### D-06 — Deploy test page — **APPROVED: remove during P1-BASE**

`public/index.html` is temporary deployment-test material. Transition order:

1. Preserve the existing deployment test until the real Laravel path is ready.
2. Deploy and verify the actual Laravel application.
3. Confirm Laravel responds at the SemantIQ site root.
4. Remove `public/index.html`.
5. Retire `deploy-test.yml`.
6. Keep the real `deploy.yml` as the deployment mechanism.
7. Verify the site again after removal.

The static page must never shadow Laravel's `public/index.php`.

### D-03 — First-administrator bootstrap — **DEFERRED TO P1-00**

Does not block P1-BASE and is not solved there. Recorded as a P1-00 blocker, to
be brought back for explicit decision at P1-00 planning.

### D-04 — Microsoft Entra ID registration — **DEFERRED TO P1-00**

Does not block P1-BASE and is not solved there. Recorded as a P1-00 blocker, to
be brought back for explicit decision at P1-00 planning. Needed then: tenant ID,
client ID, client secret, redirect URI, the registering account, and whether
Release 1 is single-tenant.

### Web exposure — **product-owner direction accepted**

The application tree must never be web-accessible merely because Laravel is
deployed inside `public_html`. The P1-BASE design addresses the document-root and
`public/` forwarding architecture explicitly and protects at minimum `.env`,
`vendor/`, `app/`, `bootstrap/`, `config/`, `database/`, `resources/`, `routes/`,
`storage/`, `tests/`, `deployment/`, `artisan`, Composer and package manifests,
logs and internal documentation. `.well-known/` continues to work for TLS/ACME.

Correct-looking Apache configuration is not proof. **Acceptance performs real
HTTPS negative tests against protected paths.** For sensitive filesystem paths a
redirect to a Login page is not sufficient — the files must not be served at all.

### Deployment safety — **product-owner direction accepted**

`.env`, `.well-known/`, runtime storage and persistent uploaded data must survive
deployment. No unrestricted `rsync --delete` against `public_html`. If `--delete`
is retained, its exclusions and target boundaries must make deletion of
server-managed files impossible. The design states explicitly which paths are
source-controlled, server-managed, persistent runtime data, secrets, or generated
build artefacts. Nothing here is left to assumption.

### D-08 — Document root — **FINAL: D-08B, permanent**

```
SemantIQ cPanel document root : public_html
SemantIQ deployment root      : public_html
D-08A                         : CLOSED / NOT TO BE PURSUED
D-08B                         : APPROVED PERMANENT HOSTING MODEL
```

The product owner has fixed this permanently. The hosting provider is **not** to be
asked to repoint the document root to `public_html/public`, and D-08A is not to be
reopened. Future design work must not assume `public_html/public` is the document
root.

Because the Laravel application tree therefore sits inside the web document root,
the hardened root `.htaccess` and the live exposure suite are **mandatory security
controls**, not defence-in-depth extras.

A separate decision follows on where the front controller lives — see
`DEPLOYMENT-LAYOUT-AMENDMENT.md`, which is drafted and awaiting approval.

### Decisions opened by the design

Two architectural questions surfaced while writing the P1-BASE design. Neither
contradicts an approved decision; both are recorded in
`P1-BASE-APPLICATION-BASELINE-DESIGN.md` §21 for decision at design approval.

| ID | Question |
| --- | --- |
| D-07 | React integration pattern: server-driven Inertia pages, or a separate SPA with a JSON API |
| D-08 | Whether the cPanel document root can be repointed to `public_html/public`, which removes the exposure class the forwarder defends against |

## 5. Standing constraints carried into every unit

- **No v1 reuse.** No v1 code, schema, migrations, permissions, workflows, tests
  or API contracts. The pre-reset history is available in `refs/pull/*/head` and
  is for lessons only, never as a source implementation.
- **Only the UI standard is reused**, per design system and blueprint §2.12.
- **Deny by default.** Menu hiding is never the control; every protected read and
  write re-evaluates effective access at the backend.
- **Negative tests are mandatory**, not optional extras: cross-domain,
  cross-team, cross-user and restricted-field denial must be proven per unit.
- **No pre-building.** Future menus, tables and services are not created early.
  A shared dependency needed by more than one unit is raised for approval before
  it is introduced.
- **Stop on conflict.** A request that contradicts the blueprint, the security
  model or a phase boundary stops for an explicit product decision.

---

## 6. Risks

| Risk | Impact | Handling |
| --- | --- | --- |
| Entra registration delayed | P1-00 blocked; it is the second unit | Raise D-04 now; P1-BASE does not depend on it |
| Role model resolved late | P1-05 rework and possible schema churn | D-01 answered before P1-BASE schema, not before P1-05 |
| Cluster mapping resolved late | Shell rebuild touching every screen | D-02 answered before the P1-BASE shell |
| Access model complexity underestimated | P1-05 is the largest unit by far | Consider splitting P1-05 into engine and admin UI at its own plan stage |
| Server `.env` drift | Deploys succeed while the app misbehaves | Config validated at boot; P1-BASE adds a health check reading real state |
| Deploy prunes untracked server files | `.env` loss, unrecoverable | `--delete` stays absent, or excludes `.env`, `.well-known/`, `storage/` explicitly |

---

## 7. Phase-level acceptance

Phase 1 is accepted only when P1-BASE and P1-00 through P1-10 are each
individually accepted **and** the cross-unit proofs in the Phase 1 document §6
pass:

- Login and Microsoft SSO work end to end.
- Unknown, unassigned, inactive and session-expired cases fail closed.
- First-admin bootstrap is secure and restricted after use.
- Organisation and team hierarchy work.
- The role / domain / scope / sensitivity matrix works.
- Salesperson, manager, executive and System Admin isolation scenarios pass.
- Baseline security cannot be casually disabled.
- Privileged changes are auditable.
- Access review works.
- Diagnostics expose no business data or secrets.
- No critical or high security findings remain open.

---

## 8. What happens next

Done: D-01, D-02, D-05 and D-06 decided; D-03 and D-04 deferred to P1-00;
P1-BASE approved and moved from PLAN to DESIGN.

1. Product owner reviews `P1-BASE-APPLICATION-BASELINE-DESIGN.md`, including the
   two questions it raises in its §21 (D-07 React integration pattern, D-08
   document-root option).
2. On design approval, P1-BASE moves to EXECUTE — the first application code of
   SemantIQ v2.
3. P1-BASE then runs TEST → VERIFY → ACCEPT, producing
   `P1-BASE-APPLICATION-BASELINE-VERIFICATION.md` with real evidence.
4. Only after P1-BASE acceptance does P1-00 planning begin, at which point D-03
   and D-04 return for decision.

No application code is written before the design is approved. No migration is
created. The live server is not modified.

### D-23 — Product-area navigation order — **APPROVED 31 August 2026**

The signed-in sidebar renders the three areas in this order:

1. **SemantIQ Workplace**
2. **Fabric Configuration**
3. **System Administration**

**Presentation and information architecture only.** No area's meaning, contents,
ownership or delivery phase changed. Delivery-phase ownership remains System
Administration Phase 1, Fabric Configuration Phase 2, SemantIQ Workplace Phase 3.

Default cluster expansion while Organisation is the only delivered capability:
**System Administration expanded, the other two collapsed** — so the one working
area is open on arrival rather than behind two collapsed sections of unavailable
features.

Superseded: the implicit ordering carried by `ProductArea` and by the phase
numbering in blueprint section 2.4, neither of which had ever stated a
navigation order explicitly. Blueprint section 2.4a now states both orderings so
they cannot disagree.

### D-24 — Guarded permanent delete / purge — **APPROVED 1 September 2026**

**Supersedes the blanket "no hard delete anywhere" rule, for four P1-01 master
record types only.** The earlier rule is not withdrawn as a mistake: it was the
right default, it still governs everything it is not explicitly superseded for,
and the reasoning behind it — *"a deleted row makes past decisions
unexplainable"* — is exactly why the exception is guarded rather than general.

What changed is the case it did not anticipate: a master record created by a
human-entry mistake, used by nothing, which the old rule made **permanent
garbage**.

**The business rule.** A System Administrator may permanently delete a
structural master record only when that record is completely safe to remove. If
it is used, or has dependent operational or history records, permanent deletion
is refused and Deactivate remains the only lifecycle action available.

> Unused master record → may purge. Used or referenced master record → cannot
> purge, deactivate only. **No cascade delete, ever.**

| Type | Permanent delete | Guard |
| --- | --- | --- |
| Legal Entity | **Permitted, guarded** | no business-unit association, and no other durable P1-01 record referencing it |
| Business Unit | **Permitted, guarded** | zero departments **including inactive**, zero legal-entity associations, no other reference |
| Department | **Permitted, guarded** | zero teams **including inactive**, no other reference |
| Team | **Permitted, guarded** | zero team-membership rows, **current or ended**, no other reference |
| Organisation / Company Profile | **Never** | the tenancy root |
| Team membership history | **Never** | ends with `left_at`; the row is retained |
| Management relationship history | **Never** | ends with `effective_to`; the row is retained |

An inactive child still counts as a dependency. A historical membership still
counts as usage. Neither is ever removed to make a purge succeed.

**Safety flow.** The action is `Delete permanently` on the record. The
confirmation names the record and states plainly that it cannot be undone. On
confirmation the server **re-checks the dependency state inside the write
transaction** — the frontend guard is never relied on, and the first check is
not sufficient, because a dependency can appear between the confirmation and the
delete.

**Refusal.** Business language, naming the blocker and pointing at Deactivate.
No database or foreign-key terminology reaches the screen.

**Audit.** A successful purge emits `legal_entity.purged`,
`business_unit.purged`, `department.purged` or `team.purged` through the
existing P1 security-event mechanism — actor, entity type, entity identifier,
timestamp, outcome. **This does not implement P1-08 early**; no audit table is
created.

**Routes.** `DELETE` is permitted for exactly these four master-record purges
and nowhere else in P1-01. `LifecycleCompletenessTest` asserts the set as an
equality, so a fifth `DELETE` fails the build.

**Role.** The current role name is **System Administrator**. No Super Admin role
is introduced in P1-01; P1-05 owns the future role model.

**Purge is not a replacement for Deactivate**, and the distinction is stated on
every affected screen:

| Action | For |
| --- | --- |
| **Edit** | a wrong name, code, jurisdiction, address or other detail |
| **Deactivate** | a legitimate record that is no longer operational — retained, with its history |
| **Delete permanently** | an erroneous or unneeded record with no dependencies and no history |

Superseded text, all amended rather than deleted so the original reasoning
survives: `P1-01-ORGANISATION-PLAN.md` §6, §8 and §11 criterion 5;
`P1-01-ORGANISATION-DESIGN.md` §7.1, negative case 13 and §10 criterion 3; and
the "no hard delete" warnings in both Product Owner test scripts.

### D-25 — Organisation Primary Legal Entity — **APPROVED 1 September 2026**

**This closes a PLAN → DESIGN omission, and is recorded as one.** The PLAN
listed *"primary legal entity"* among the Organisation's data points
(`P1-01-ORGANISATION-PLAN.md` §5). The DESIGN's `organisations` table did not
carry it and recorded **no decision to drop it**. The P1-01 scope-completeness
audit found it. D-25 closes it. It was never a designed field, and the documents
do not pretend otherwise.

**The relationship:** Organisation → one **optional** Primary Legal Entity.

| It is | It is not |
| --- | --- |
| The organisation's corporate identity — who the company is on paper | A `primary` flag on `business_unit_legal_entity` |
| An organisation-level attribute | An employing entity |
| Optional, and NULL is a real state | An entitlement, a scope or an access rule |
| Recorded, granting nothing | A replacement for, or a change to, D-14 |

**D-14 is unchanged, and this is the point on which D-25 rests.** Business Unit ↔
Legal Entity remains many-to-many; the junction still carries **no attributes of
any kind**; the association still grants no access, employment meaning or
entitlement. Crucially, **the primary legal entity is not the parent of the
business units** — it need not be associated with any business unit at all, and
a business unit may operate under entities that are not the primary. The two
answer different questions: *who are we on paper*, and *which entity does this
business unit operate under*.

**Data model.** `organisations.primary_legal_entity_id` — nullable, FK →
`legal_entities.id`, indexed, `ON DELETE RESTRICT`. Additive migration,
explicitly authorised. **No seed, no backfill, no manual database write.** The
existing production organisation stays NULL after the migration and acquires a
value only when an administrator chooses one on the Company Profile.

**Company Profile.** A dropdown of this organisation's **active** legal entities.
Optional; Set, Change and Clear are one operation — a chosen value or none. With
no legal entity yet recorded the screen says so in plain words rather than
showing an empty control. The server validates the same two conditions the
dropdown renders from — same organisation, and active — because a `<select>`
constrains nothing once the request leaves the browser. No raw identifiers are
shown.

**Lifecycle guards.** While a legal entity is the organisation's primary:

- **Deactivate → REFUSED.** *"This legal entity is the organisation's primary
  legal entity. Select another primary legal entity or clear the selection
  before deactivating it."*
- **Permanent delete → REFUSED**, by the D-24 guard, which reads the schema and
  therefore picked the new foreign key up with no special case written.

**No cascade.** The selection is never cleared on the caller's behalf to let a
deactivation or a purge succeed.

Superseded: `P1-01-ORGANISATION-DESIGN.md` §2.1, which omitted the column. The
omission is recorded there rather than quietly corrected.

---

## 10. Carried verification gates

A carried gate is a check that a unit's design requires but its delivered state
cannot execute. It is recorded here so it is executed against the later unit
rather than quietly lost.

| From | To | Gate | Why it could not run in the originating unit |
| --- | --- | --- | --- |
| **P1-01** *(ACCEPTED 2 Sep 2026)* | **P1-03** | Live multi-user management-cycle refusal, observed in production | Self-management is refused before the chain walk, so a genuine cycle needs at least two SemantIQ users. P1-01 ships with `users_total = 1`; P1-03 provisions the second |
| **P1-02** *(ACCEPTED 2 Sep 2026)* | **P1-03** | A real non-administrator being refused at Identity & SSO | Production held one account, so there was nobody to sign in as. P1-03 owns provisioning, and every user it creates has `platform_role = NULL` — so this needs no special setup and no manufactured account |
| **P1-02** *(ACCEPTED 2 Sep 2026)* | **P1-05** | The provider-wide Re-check limit, observed with two administrators | Needs a second **privileged** account. **Moved from P1-03 to P1-05 by Product Owner decision:** P1-03 cannot assign `platform_role` at all, so closing it there would have meant manufacturing a second privileged production account. Automated evidence stands |

These two were recorded in the P1-02 Product Owner test script §12 and were
missing from this register — which is the exact way a carried gate gets quietly
lost, and the reason this table exists. Added when P1-03 was delivered.

**P1-03 is not accepted until every gate carried into it has been executed and
recorded with observed output.**

The originating unit keeps its own automated coverage as its evidence — for the
P1-01 cycle rule that is negative case 8, proven non-vacuous by the mutation
*remove the chain walk*. A carried gate defers the **live observation**, never
the rule and never the test.
