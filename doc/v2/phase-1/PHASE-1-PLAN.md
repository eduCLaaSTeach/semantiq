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
| 6 | **P1-04** | Business Domains, owners, accountability, access expectations | The organisation's intelligence estate is named and owned — **and none of it grants access to anything** |
| 7 | **P1-05** | Roles, entitlements, scopes, sensitivity, Access Simulator | Effective-access engine proven |
| 8 | **P1-06** | Security Status | Posture legible without security expertise |
| 9 | **P1-07** | Access Reviews | Sensitive access has a review lifecycle |
| 10 | **P1-08** | Audit | Phase 1 activity evidenced |
| 11 | **P1-09** | System Health | Operational failures visible safely |
| 12 | **P1-10** | **Platform Integrations & Setup** | Customer-configurable SSO, email, AI and Fabric connections, and the local Bootstrap Administrator that makes first-run setup possible |
| 13 | **P1-11** | Administration Home | One accurate roll-up, built last from real sources |

> **AMENDED by Product Owner amendment, 20 September 2026** —
> `PRODUCT-OWNER-AMENDMENT-PLATFORM-SETUP-AND-BOOTSTRAP.md`.
>
> **Order 12 was P1-10 Administration Home.** A new unit is inserted at 12 and
> Administration Home becomes **P1-11**. Administration Home stays last for the
> same reason it always was — it projects facts other units own — and that
> reason is now stronger, because P1-10 delivers integration and readiness
> facts it would otherwise have had to invent.
>
> **P1-11 must not be implemented before P1-10 is accepted.**

The order follows the Phase 1 document exactly, with P1-BASE inserted ahead of it.
Each unit runs PLAN → approve → DESIGN → approve → EXECUTE → TEST → VERIFY →
ACCEPT. A green CI run does not unlock the next unit.

### Delivery status — current, 21 September 2026

**This is the authoritative status register.** §1 above is a snapshot of the
repository on the day this plan was written and is kept as history; it is not
where to look for what is delivered.

| Order | Unit | Status |
| --- | --- | --- |
| 1 | P1-BASE | **ACCEPTED** |
| 2 | P1-00 | **ACCEPTED** |
| 3 | P1-01 | **ACCEPTED** — 2 Sep 2026 |
| 4 | P1-02 | **ACCEPTED** — 2 Sep 2026. One carried gate remains open against Phase 1 orchestration, §10 |
| 5 | P1-03 | **ACCEPTED** |
| 6 | P1-04 | **ACCEPTED** — 3 Sep 2026 |
| 7 | P1-05 | **ACCEPTED** — 15 Sep 2026 |
| 8 | P1-06 | **ACCEPTED** |
| 9 | P1-07 | **ACCEPTED** — Gate D closed |
| 10 | P1-08 | **ACCEPTED** — Gate D closed |
| 11 | P1-09 | **ACCEPTED** — 20 Sep 2026. Carried: production session-driver alignment, §10 |
| 12 | **P1-10** | **PRODUCT OWNER ACCEPTED — GATE D CLOSED. P1-10 CLOSED.** 21 Sep 2026. `P1-10-PLATFORM-INTEGRATIONS-ACCEPTANCE.md`. Nine carried items remain **OPEN**, §10 and that record's §5 |
| 13 | **P1-11** | **PRODUCT OWNER ACCEPTED — GATE D CLOSED — P1-11 CLOSED**, 22 September 2026. PLAN `b98bba4`. DESIGN / Gate B `59a3f73`. Gate C APPROVED at `8f69569` with rulings **PO-R1** (Access Reviews seam APPROVED, owned by P1-07), **PO-R2** (Organisation Administrator sees exactly `Administration Home`) and **PO-R3** (System Health neutral counts, no aggregate verdict) — all three **in force**. Merged as **`59caced`** (PR #139), deployed by run **35587621169**; the deployed build was verified by asset hash rather than taken from the deployment's own report. **Product Owner live production review: 8 / 8 PASS.** `P1-11-ADMINISTRATION-HOME-ACCEPTANCE.md` |

**ACCEPTED IS NOT THE SAME AS NOTHING OUTSTANDING.** Several accepted units
carry a live observation their delivered state could not execute. Those rows
live in §10 and are the reason unit acceptance does not add up to phase
acceptance on its own — see §7.

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

### D-03 — First-administrator bootstrap — **DEFERRED TO P1-00** · **partly superseded 20 September 2026**

Does not block P1-BASE and is not solved there. Recorded as a P1-00 blocker, to
be brought back for explicit decision at P1-00 planning.

> **D-03 — superseded in part by Product Owner amendment, 20 September 2026.**
> The deferral itself stands and was honoured; what is amended is the **ruling
> it produced in P1-00 §20**. That ruling required the first administrator to
> pass Entra SSO before privileged application access, **which is circular on a
> fresh installation**: SSO cannot be configured until somebody privileged can
> sign in.
>
> **Superseded:** a narrowly scoped local **Bootstrap Administrator** may
> perform First-Run Platform Setup before SSO is configured.
> **Unchanged:** the first *permanent* System Administrator still authenticates
> through the approved normal identity path, and the single-use grant
> mechanism — its hashing, its atomic consumption, its operator-only issuance —
> all stand and are to be reused rather than replaced.

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

Phase 1 is accepted only when P1-BASE and **P1-00 through P1-11** are each
individually accepted **and** the cross-unit proofs in the Phase 1 document §6
pass:

- Login and Microsoft SSO work end to end.
- Unknown, unassigned, inactive and session-expired cases fail closed.
- First-admin bootstrap is secure and restricted after use.

> **AMENDED 20 September 2026.** Two further phase-level conditions are added by
> the Product Owner amendment:
>
> - **Platform Integrations (P1-10)** establishes SSO, email, AI and Fabric
>   connections through the product rather than by hand-editing `.env`, with
>   secrets encrypted at rest and never returned to the browser;
> - **the local Bootstrap Administrator is disabled** once SSO is verified and
>   a permanent System Administrator has authenticated. *"Restricted after use"*
>   now covers **both** the single-use grant and the local bootstrap principal.
>
> The existing blocking items are unchanged and still stand: the **P1-02
> provider-wide SSO re-check**, the **production session-driver alignment**, the
> **privilege-change / session-revocation verification**, and the carried
> P1-07 / P1-08 / P1-09 items per their recorded disposition.
- Organisation and team hierarchy work.
- The role / domain / scope / sensitivity matrix works.
- Salesperson, manager, executive and System Admin isolation scenarios pass.
- Baseline security cannot be casually disabled.
- Privileged changes are auditable.
- Access review works.
- Diagnostics expose no business data or secrets.
- No critical or high security findings remain open.

### PHASE 1 DOES NOT CLOSE WHEN P1-11 CLOSES — 21 September 2026

**P1-11 is the final delivery unit. It is not the final gate.** **P1-11 was
Product Owner accepted on 22 September 2026, so all THIRTEEN units are now
closed** — and the temptation this section was written to resist has arrived in
full. **"The last unit shipped" is not "the phase is done."**

Final Phase 1 acceptance additionally requires **explicit disposition** — a
decision recorded, not an omission — of every row in §10. Four of them cannot
be closed by building anything in P1-11:

| Gate | Why it is not P1-11's to close |
| --- | --- |
| **Production session-driver alignment**, `file` → `database` | A controlled deployment correction that terminates every existing session. **Not a P1-11 change, and not to be attempted inside it** |
| **Privilege-change / per-user session revocation** | The control **does not exist**. Nothing reads `sessions.user_id`. It is a build-or-decide item, and it depends on the row above |
| **P1-02 provider-wide SSO re-check** | Needs a genuine second **permanent** System Administrator, which no unit can manufacture |
| **The P1-10 live-observation rows** | Need a fresh installation, real SMTP, or the deliberate Entra cutover |

**And P1-11 adds two of its own, both raised at Gate C rather than absorbed:**

| Raised by P1-11 | Disposition needed |
| --- | --- |
| **The four System Administration screens an Organisation Administrator can reach and still cannot see** | D-182 deliberately exposed **one** node and no more. Organisation, Users & Groups, Roles & Access and Business Domains stay hidden from them. **A carried navigation item with its own evidence to gather**, not a defect P1-11 left behind |
| **P1-06's per-domain evaluation cost** | `PostureEvaluator`'s `DomainAdapter` issues five aggregates per business domain. Security Status already pays it and has since P1-06; Administration Home shows that summary, so it inherits the cost and is the screen that will make it visible first. **Pre-existing, already live, and not P1-11's to re-engineer** |

> ### Product Owner rulings on both, Gate C
>
> **The navigation item** — **PO-R2, ACCEPTED.** An Organisation Administrator's
> System Administration navigation remains **exactly** `['Administration Home']`.
> No other node is exposed as part of P1-11, and **no implementation change was
> required**. The carried item stands as written.
>
> **The P1-06 cost** — **CARRIED FORWARD, and NOT a P1-11 Gate C blocker.**
> `PostureEvaluator` / `DomainAdapter` is **not to be refactored in PR #139**.
> P1-11 evaluates P1-06 **once** per render and feeds two tiles from the one
> report, and measured performance remains **inside D-140**. The finding is
> P1-06's to dispose of, on its own evidence, not this unit's.

**Not one of these may be silently moved into Phase 2.** Moving one is a
Product Owner decision, recorded here with its reason; a gate that quietly
stops being mentioned is a gate that was lost.

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
cannot execute. It is recorded here so it is executed later rather than quietly
lost — normally against a named later unit, and where no unit can produce the
prerequisite, against Phase 1 orchestration itself.

**This register is only useful if it stays true.** A row that names a unit which
has already shipped is stale, and a stale row is how a gate disappears. Every row
below carries its current status and, where it has moved, the date it moved.

| From | To | Gate | Why it could not run in the originating unit |
| --- | --- | --- | --- |
| **P1-01** *(ACCEPTED 2 Sep 2026)* | **P1-03** | Live multi-user management-cycle refusal, observed in production | **CLOSED 3 Sep 2026** — observed by the Product Owner against a genuine second user. Verbatim wording not retained |
| **P1-02** *(ACCEPTED 2 Sep 2026)* | **P1-03** | A real non-administrator being refused at Identity & SSO | **CLOSED 3 Sep 2026** — `semantiq@educlaas.com`, a real user with no role, signed in and saw an empty System Administration area. No account was manufactured for it |
| **P1-02** *(ACCEPTED 2 Sep 2026)* | **Phase 1 orchestration** — *reassigned 18 Sep 2026, see below* | The provider-wide Re-check limit, observed with two administrators | **OPEN / CARRIED / UNVERIFIED.** Needs a genuine second **permanent** System Administrator. **Do not create a fake privileged account to close it.** Automated evidence stands. It was carried to P1-05 and then to P1-06; both are now closed and the prerequisite still does not exist, so it belongs to no single unit |
| **P1-04** *(ACCEPTED 3 Sep 2026)* | **P1-05** | **A DISABLED DOMAIN CAN NEVER BROADEN ACCESS** | **CLOSED 15 Sep 2026.** Observed by the Product Owner at section H of the P1-05 test script against real production data — `P1-05-ROLES-ACCESS-VERIFICATION.md` §10. P1-04 intentionally contained **no access engine**, so the failure was unreachable there and became reachable the moment P1-05 built effective access. All five cases below were run; they are kept as the historical required evidence |

### P1-10 carried items — **eight OPEN**, recorded 21 September 2026

**P1-10 was accepted and closed on 21 September 2026. None of these closed with
it.** Each needs a prerequisite the deployment does not have, and **not one may
be manufactured** — a fake second administrator, a fake installation or a fake
mail server would close a row while proving nothing.

| From | To | Gate | Status |
| --- | --- | --- | --- |
| **P1-10** *(ACCEPTED 21 Sep 2026)* | **Phase 1 orchestration** | A real completed **Microsoft step-up** for a privileged configuration change | **OPEN / CARRIED.** The redirect, the staging, the single-use consumption, the candidate verification and every refusal path are covered automatically. The live round trip needs a deliberate, scheduled cutover |
| **P1-10** | **A fresh or test installation** | **Bootstrap First-Run**, end to end | **OPEN / CARRIED.** First-Run exists only while a deployment has no System Administrator. Production has one, so seeing it live would mean deactivating every administrator on a running system |
| **P1-10** | **A fresh or test installation** | Bootstrap **30-minute idle** expiry, observed | **OPEN / CARRIED** |
| **P1-10** | **A fresh or test installation** | Bootstrap **4-hour absolute** expiry, observed | **OPEN / CARRIED** |
| **P1-10** | **A fresh or test installation** | **Recovery flow**, observed | **OPEN / CARRIED** |
| **P1-10** | **Phase 1 orchestration** | A **real SMTP send test** | **OPEN / CARRIED.** No mail server exists on this deployment and none was created to satisfy a test. What is proven is *which address SemantIQ would send to*, structurally; not that SMTP works |
| **P1-10** | **Phase 1 orchestration** | The **production Entra cutover**, `env` → store | **OPEN / CARRIED.** Explicitly withheld by the Product Owner at every gate. Production reads its Microsoft configuration from the environment and the cutover timestamps are absent |
| **P1-10** | **Phase 1 orchestration** | Console screens opened **on production by the delivery team** | **OPEN / CARRIED.** Signing in needs Microsoft credentials the delivery environment does not have, and its browser does not trust the inspecting proxy's certificate authority. The Product Owner's Gate D review is the live observation; the delivery team's browser evidence is from a local server |

**The P1-02 second-administrator gate above is the ninth**, and it is not
repeated here because it already has a row of its own.

### Production session-driver alignment — **OPEN / CARRIED**, recorded 20 September 2026

**Not a carried gate in the usual sense.** The four rows above defer a *live
observation* a unit could not make. This one records a **deployment that has
drifted from an approved target**, found while verifying P1-09 in production.

| | |
| --- | --- |
| **Intended target** | **`SESSION_DRIVER=database`** — unchanged. `.env.example` declares it, and the P1-BASE design records it as the one place the baseline deliberately looks ahead |
| **Current production** | **`file`**, established by read-only SSH verification — workflow `verify-session-store`, run `35453840140`. Configuration is not cached, so the value is live |
| **Present P1-09 defect** | **NONE.** `SessionStoreCheck` supports a database round trip only, so *Staying signed in* correctly reads **Not checked**. The Product Owner accepted this and does **not** require the row to be made green |
| **Migration** | **Already exists** — `0001_01_01_000000_create_sessions_table.php`. Nothing needs building to adopt the target |
| **Why it was not fixed during P1-09** | Switching drivers **terminates every existing session**. That is a controlled deployment correction with its own gate, not a UI fix, and the `file` driver is functioning |
| **Resolve by** | **Final Phase 1 acceptance**, together with confirmation of the required privilege-change / session-revocation behaviour |

**DO NOT CLAIM SERVER-SIDE PER-USER SESSION REVOCATION IS IMPLEMENTED.** It is
not. Established from the code, not assumed: the only two invalidations in the
application are `$request->session()->invalidate()` — sign-out and the expiry
middleware — and both act on the **viewer's own** session and work on any
driver. Nothing reads `sessions.user_id`. The table was prepared for that
control; the control does not exist yet.

**What the drift does and does not cost today:** it breaks no delivered
control, and file sessions survive deploys because `storage/` is excluded from
rsync. It will matter the day per-user revocation is built, because on `file`
that would **silently fail rather than error** — the worst of the three
outcomes, and the reason this is carried rather than closed.

**How it was found is worth keeping.** The claim *"`SESSION_DRIVER=database` on
this deployment (verified)"* was written into the P1-09 DESIGN from
`.env.example` — a repository file read and reported as deployment reality.
Nothing was verified. It took a Product Owner looking at a real screen to catch
it. `.env` is excluded from rsync, deliberately, so a value predating the
baseline stays in force indefinitely and nothing else would have corrected it.

---

The first two were recorded in the P1-02 Product Owner test script §12 and were
missing from this register — which is the exact way a carried gate gets quietly
lost, and the reason this table exists. Added when P1-03 was delivered, and
closed by it the next day.

**ONE PHASE 1 CARRIED VERIFICATION GATE REMAINS OPEN: the P1-02 provider-wide
SSO Re-check.** The other three are closed — P1-01→P1-03 and P1-02→P1-03 on
3 September 2026, and P1-04→P1-05 on 15 September 2026.

**ONE PHASE 1 ALIGNMENT FINDING IS ALSO OPEN: the production session driver**,
recorded above on 20 September 2026. It is listed separately because it is not
a deferred observation — it is a deployment that has drifted from an approved
target, and closing it requires a controlled deployment rather than a test.

### The P1-02 gate — reassigned to Phase 1 orchestration, 18 September 2026

It was carried to P1-05, and then stood open through P1-06. Both units are now
closed and the prerequisite still does not exist, so naming a delivery unit that
has already shipped is how this register goes stale — the precise failure it was
created to prevent. **Product Owner decision, 18 September 2026:** the gate is
no longer assigned to P1-05 or to any specific later delivery unit. It becomes a
**Phase 1 orchestration carried gate**.

**Execution rule.** Execute the provider-wide SSO Re-check at the earliest point
that a genuine second **permanent** System Administrator exists in normal
operation.

**Do not**, to satisfy it:

- create a temporary administrator;
- promote somebody only for testing;
- manufacture a privileged account;
- make P1-07 responsible for producing the prerequisite.

**P1-07 does not own this gate.** Nor does the gate block starting P1-07 merely
because a genuine second permanent System Administrator does not yet exist.

**Before final Phase 1 acceptance, revisit it:**

| Situation | What happens |
| --- | --- |
| A genuine second permanent System Administrator exists | Execute the live provider-wide Re-check and record the **observed** evidence |
| The prerequisite still does not exist | Keep it **OPEN / CARRIED / UNVERIFIED** and bring it to the Product Owner **explicitly** as part of Phase 1 final acceptance |

**It is never silently marked PASS, CLOSED or Healthy.** Security Status reports
it as *not verified*, which is the honest answer and is intended to stay that way
until the observation is genuinely available.

### The P1-04 gate, stated as the cases it must run

**A disabled domain can never broaden access.** The failure this anticipates is
concrete rather than theoretical: the natural implementation of "disabled" is *a
filter that removes a domain from a set*, and a filter that is skipped when the
set is empty turns **no domains enabled** into **allow everything.**

P1-05 ran **all five**. They are kept here as the historical required evidence
for a gate that is now closed — the reasoning is not deleted, because it is what
makes the closure meaningful:

| # | Case | Required outcome |
| --- | --- | --- |
| 1 | **One** domain disabled | No access through that domain. Access through the others is unchanged |
| 2 | **All** domains disabled | **No domain access at all** |
| 3 | **No enabled domains** at all | **NO DOMAIN ACCESS — never unrestricted access.** This is the case the whole gate exists for |
| 4 | An entitlement referencing a domain **subsequently disabled** | The entitlement grants nothing while the domain is disabled. It is **not deleted** |
| 5 | **Re-enable** | Access returns to exactly what it was, and no further |

Case 3 is not a rewording of case 2. *All disabled* and *none exist* reach the
same empty set by different paths, and a filter that guards one may not guard
the other.

**P1-03 is not accepted until every gate carried into it has been executed and
recorded with observed output.** Both were, and **P1-03 was accepted on
3 September 2026** — see `P1-03-USERS-GROUPS-VERIFICATION.md` §0. The observed
output is the Product Owner's confirmation that each refusal occurred; the
verbatim wording was not retained, and is recorded as missing rather than
reconstructed.

The originating unit keeps its own automated coverage as its evidence — for the
P1-01 cycle rule that is negative case 8, proven non-vacuous by the mutation
*remove the chain walk*. A carried gate defers the **live observation**, never
the rule and never the test.
