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

---

## 10. Carried verification gates

A carried gate is a check that a unit's design requires but its delivered state
cannot execute. It is recorded here so it is executed against the later unit
rather than quietly lost.

| From | To | Gate | Why it could not run in the originating unit |
| --- | --- | --- | --- |
| **P1-01** | **P1-03** | Live multi-user management-cycle refusal, observed in production | Self-management is refused before the chain walk, so a genuine cycle needs at least two SemantIQ users. P1-01 ships with `users_total = 1`; P1-03 provisions the second |

**P1-03 is not accepted until every gate carried into it has been executed and
recorded with observed output.**

The originating unit keeps its own automated coverage as its evidence — for the
P1-01 cycle rule that is negative case 8, proven non-vacuous by the mutation
*remove the chain walk*. A carried gate defers the **live observation**, never
the rule and never the test.
