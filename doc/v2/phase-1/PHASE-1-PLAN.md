# Phase 1 Plan — System Administration & Security Foundation

**Status:** DRAFT — awaiting product-owner approval
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

**Proposal:** run the baseline as its own delivery unit, **P1-BASE**, ahead of
P1-00, with its own plan, acceptance and verification record. Reasons:

- Its acceptance is infrastructural (app boots, CI green, deploys, migrates), not
  behavioural. Folding it into P1-00 would mix two unrelated definitions of done
  and make the login unit's evidence harder to read.
- It carries several server-side changes — forwarder, database, deployment
  layout — that must be right before any authentication work is trustworthy.
- P1-00's "must prove" list is a security proof. It should not share a unit with
  scaffolding noise.

This framing needs approval. The alternative — treating the baseline as P1-00
step zero — is workable but weakens the evidence trail.

Unit plan: `P1-BASE-APPLICATION-BASELINE-PLAN.md` (drafted, awaiting approval).

---

## 3. Delivery sequence

| Order | Unit | Delivers | Gate |
| --- | --- | --- | --- |
| 1 | **P1-BASE** | Laravel 13 / React 19 skeleton, MySQL, CI gates, forwarder, deploy | App reachable and deployable; CI green |
| 2 | **P1-00** | Login, Microsoft SSO, callback validation, session, bootstrap, refusal states | Auth path proven end to end including fail-closed cases |
| 3 | **P1-01** | Organisation, business units, departments, teams, hierarchy, legal entities | Scope source established |
| 4 | **P1-02** | Identity & SSO administration, health, session policy | Identity supportable without exposing secrets |
| 5 | **P1-03** | Users & Groups, directory sync, lifecycle | Users exist with no accidental data access |
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

## 4. Decisions required before implementation

These cannot be resolved from the documents. Each blocks the unit named.

### D-01 — Role model conflict (blocks P1-05, shapes P1-BASE schema)

The two authorities describe different access models.

| | Blueprint §2 / Phase 1 §P1-05 | Design system §7 |
| --- | --- | --- |
| Roles | 7: System Admin, Organisation Admin, Executive, Domain Owner/Director, Manager, Business User, Auditor | 5 tiers: `system_admin`, `admin`, `team`, `self`, `self_view` |
| Scope | Independently assigned: Own, Team, Business Unit, Domain, Organisation | Derived from tier |
| Domain | Orthogonal entitlement per user | Not modelled |
| Sensitivity | Standard / Confidential / Restricted ceiling | Not modelled |

These are not the same shape. The blueprint makes scope, domain and sensitivity
orthogonal to role; the design system derives scope from tier. The design system
says to keep its tier shapes; the blueprint says security is Role + Domain +
Scope + Sensitivity.

Source-of-truth (blueprint §2.11) puts the blueprint at priority 2 and the shared
UI standard at priority 4, so **the blueprint's model governs** and the design
system's five tiers become a presentation concern.

**Recommendation:** implement the blueprint's model as the authorisation engine,
and treat the design-system tiers as a UI vocabulary mapped onto it. Record this
as the documented per-app deviation the design system asks for. Confirm before
P1-05 design.

### D-02 — Navigation cluster mapping (blocks P1-BASE shell)

The design system mandates four fixed sidebar clusters: Workspace, Compliance,
Application Administration, System Administration. The blueprint defines three
product areas: System Administration, Fabric Configuration, SemantIQ Workplace.

They do not map one to one. Options:

- **(a)** Blueprint areas become the clusters, documented as a deviation.
- **(b)** Blueprint areas map onto the four clusters (Workplace → Workspace,
  Fabric Configuration → Application Administration, System Administration →
  System Administration, Compliance holds Audit and Access Reviews).

**Recommendation: (a).** The blueprint's §2.6 menu structure is explicitly
authoritative for v2 navigation and is stated as such. Confirm before the shell
is built, because the shell is built in P1-BASE and changing it later touches
every screen.

### D-03 — First-admin bootstrap method (blocks P1-00)

Required to be a product feature, not a manual database edit, and to be
non-reusable afterwards. Candidate approaches:

- One-time bootstrap token in the server `.env`, consumed on first successful
  SSO sign-in and then invalidated.
- Allowlisted Entra object ID or UPN in configuration, promoted to System
  Administrator on first sign-in.
- Signed one-time bootstrap URL generated by an `artisan` command run over SSH.

Each satisfies "no manual MySQL row insertion". They differ in operational
handling and in what "disabled afterwards" means. **Product-owner choice.**

### D-04 — Microsoft Entra ID app registration (blocks P1-00)

External dependency outside the repository. Needed: tenant ID, client ID, client
secret, redirect URI, and which account performs the registration. Also whether
the Release 1 tenant is single-tenant or accepts multiple.

Secrets go to the server `.env` and GitHub environment secrets only, never to the
repository.

### D-05 — MySQL database provisioning (blocks P1-BASE)

The server `.env` has empty `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`. A cPanel
database and user must be created and those values filled in on the server. Who
does this, and are migrations run from CI over SSH or manually on first release?

### D-06 — Fate of the deploy test page (housekeeping, P1-BASE)

`public/index.html` currently occupies the live site root. Once Laravel is
deployed, `public_html` holds the application tree and the forwarder rewrites into
`public/`. The test page must be removed or moved so it does not shadow the
application's front controller. Recommend removing it in P1-BASE and keeping the
deploy-test workflow only until the real deploy workflow is restored.

---

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

1. Product owner answers **D-01** through **D-06**, or defers the ones that only
   block later units (D-03 and D-04 block P1-00; the rest block P1-BASE).
2. Product owner approves or amends the **P1-BASE** framing in §2.
3. On approval, `P1-BASE-APPLICATION-BASELINE-PLAN.md` moves to DESIGN.
4. No application code is written before that design is approved.
