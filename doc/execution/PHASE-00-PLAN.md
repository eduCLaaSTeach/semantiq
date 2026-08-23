# Phase 00 Plan - Engineering Foundation and Business Experience Shell

**Reference:** P00-FND, `doc/phases/PHASE-00-FOUNDATION.md`, `doc/phases/PHASE-00-UI-SHELL.md`
**Completion phrase (later, not now):** `CONFIRM PHASE 00 COMPLETE`
**Status:** SUPERSEDED FOR SEQUENCING by `doc/execution/ADMIN-FOUNDATION-RELEASE-1-PLAN.md`.

The gap analysis below stands and is still accurate. What changed is the ORDER.
Administrator Foundation Release 1, added on 23 August 2026, requires the
administrator platform to be complete and verified before Fabric provisioning,
source onboarding, semantic intelligence, AI features or business-user modules
are built. That reverses the batching proposed here, which put business shells
ahead of the control plane.

The engineering foundation this plan called Batches A and B is absorbed into
Release 1 gates 1, 2, 3, 4 and 6. The business screens it called Batches C, D
and E are explicitly deferred by that document and return after the Fabric
Environment Release.

Nothing here was implemented before the resequencing.

---

## 1. Verified state

Read from the repository at `89c3cbd`, and probed against the live site, not recalled.

### Already built, merged and running in production

| Requirement | Where |
|---|---|
| 4.1 Business shell navigation, all eight top-level items | `config/navigation.php`, 292 nodes |
| 4.2 Administration boundary, fifteen groups, route-guarded | `EnforceNavigationPolicy`, live 403 confirmed |
| 4.3 Role-aware Home with all nine card types | `pages/home.blade.php`, empty states |
| 4.4 My Intelligence, entitlement-driven | `pages/intelligence.blade.php` |
| 5.4 Role and domain-entitlement policy | `Role`, `BusinessDomain`, `DomainEntitlement` |
| 5.12 CI, test and static-analysis gates | `.github/workflows/ci.yml`, 86 tests |
| 5.13 Context registers, all six | `doc/context/` |
| 9 Backend authorization, deny by default | `Navigation::allows()`, one rule for rail and route |
| P00-UI-001, 002, 009 | Home, My Intelligence, Administration Landing |
| Identity (Phase 01 scope, shipped early) | Entra OIDC with PKCE, live and working |

### Required by this phase and absent

| Section | Requirement | State |
|---|---|---|
| 5.2 | Modular-monolith boundaries | **Absent.** Flat Laravel |
| 5.3 | Organisation / tenant context | **Absent.** No `organisations` table, no scope |
| 5.5 | WorkflowRun, AuditEvent, HelpTopic, external resource references | **Absent** |
| 5.6 | Secret-provider abstraction | **Absent.** Entra values read straight from config |
| 5.7 | Asynchronous workflow orchestration | **Absent.** No queue worker, no correlation IDs |
| 5.8 | The ten status values | **Absent** |
| 5.9 | Immutable audit capture | **Absent** |
| 5.10 | Contextual Help framework | **Absent** |
| 5.11 | Integration capability registry | **Absent** |
| 10 | `DataProtectionProfile`, `VAL-SOV-GEO-001` | **Absent** |
| 11 | Seven replaceable AI contracts | **Absent** |
| 7 | P00-UI-003 to 008, 010, 011, 012 | **Absent.** Nine screens |

Roughly two thirds of the phase remains.

---

## 2. Decisions, answered 23 August 2026

**D1. Home stays empty-state.** FOUNDATION 4.3 permits fixture metadata; UI-SHELL 3 forbids faking production insights. The stricter reading wins: nothing on Home can be mistaken for a real number. Cards say what will be there and what has to happen first.

**D2. New code lands in modules; existing code moves later.** Everything from Batch A onward sits under `app/Modules/`. Sign-in, shell and navigation move in their own change once the boundary is proven, rather than refactoring tested, live code alongside new work.

**D3. React 19 arrives with Ask SemantIQ, and only there.** That screen is the first with genuine client state. Server-rendered Blade keeps sign-in, navigation and the static shells, which do not need it. One shell, two rendering strategies, each where it earns its place.

**D4. Seven fixed business domains.** Custom domains are recorded as deferred: a customer-defined domain needs a name, an owner and an approval story nobody has specified, and the entitlement table already accepts new rows without redesign.

### Settled by the precedence rule rather than by asking

- **`doc/design-system/` does not exist.** FOUNDATION 3 and 8 both name it. The template is at `.claude/reference-template/`. Correcting the phase documents' paths is part of Batch F rather than moving the file.
- **Identity shipped before its phase.** Entra SSO is Phase 01 scope (`P01-IDN`) and is already live. Recorded in `IMPLEMENTATION_STATUS.md` as pre-gate work in Batch F. Nothing is marked confirmed that nobody confirmed.

---

## 3. Batches

Six changes, each independently reviewable, each its own pull request. The order is real dependency, not preference: nothing in B can be built without A's tables, and the screens in E display what B produces.

### Batch A - Tenancy and the configuration data model

The spine. Everything else hangs from it.

- `app/Modules/` established with the eight boundaries FOUNDATION 5.2 names.
- `organisations` table, organisation context resolvable from a session **or** bound explicitly by a job.
- A global scope that **fails closed**: no active organisation means no rows, never all rows.
- `WorkflowRun`, `AuditEvent`, `HelpTopic`, `ExternalResource`, `DataProtectionProfile`.
- The ten-state status model as an enum, mapped onto the design system's six badge roles.

### Batch B - Foundation services

- Secret-provider abstraction. No credential is stored in this phase; Phase 01 gets somewhere to put one that is not a config file.
- Workflow orchestration: queued, resumable, correlation ID carried into every log line and audit row. No worker sleeps and no browser request is held.
- Audit framework: append-only, hashes rather than payload copies.
- Capability registry: whether a Microsoft operation is stable, preview or guided-only.
- `VAL-SOV-GEO-001` server-side, with geographies unset and therefore blocking.

### Batch C - AI contracts and Ask SemantIQ

- The seven contracts from FOUNDATION 11: model provider, agent runtime, retrieval, tool/MCP, conversation store, evaluation, channel adapter. Interfaces only; **no model, no provider, no runtime**.
- React 19 introduced, mounted inside the existing shell.
- **P00-UI-003 Ask SemantIQ**: composer, domain indicator, suggested questions, history placeholder, the answer/visual/source layout contract, and every state.

### Batch D - The remaining business shells

**P00-UI-004** Explore, **005** Decisions & Alerts, **006** Reports & Insights, **007** My Workspace, **008** Help. Server-rendered, each covering success, empty, loading, validation, permission-denied, error and small-screen.

### Batch E - Administration screens and contextual help

- **P00-UI-010 Organisation Setup**, including the data-protection profile fields.
- **P00-UI-011 Platform Help Centre**, plus contextual help opening from a real administrator screen, which is what the checklist actually asks to be demonstrated.
- **P00-UI-012 Audit Log**: the first real list screen with sort, filter, pagination and URL state.
- A sample long-running workflow that can be killed mid-run and resumes.

### Batch F - Verification and reconciliation

- All six context registers refreshed against what was built.
- `IMPLEMENTATION_STATUS.md` records the Phase 01 irregularity honestly.
- Phase-document path corrections.
- `doc/execution/PHASE-00-VERIFICATION.md` with real evidence against the section 12 checklist.

---

## 4. Security and authorization

Four layers, all of which must agree:

1. **Cluster and feature access** gates the rail and the route. Built.
2. **Organisation scope**, failing closed. Batch A.
3. **Domain entitlement**, independent of tier. Built.
4. **Record policy and query scope** as entities arrive. Batches A and B.

The claim tested hardest stays the one ROLE_MODEL.md 1 makes: a role alone never grants business data. A System Administrator holds no Sales figures without being entitled to Sales.

---

## 5. Data protection and sovereignty

- **Classification:** Internal only. Organisation configuration, workflow state, audit metadata, entitlements. No customer business data enters the control plane this phase.
- **Geographies:** unset. `VAL-SOV-GEO-001` returns BLOCKED for production activation while they are, because the absence of a value is a refusal and never a pass.
- **Cross-geo:** all three flags default false.
- **Logging:** credentials and tokens redacted; production payload capture off by default.
- **Retention:** policy-driven from the profile, not constants in code.
- **Open item:** the control-plane hosting geography is still unconfirmed and must be recorded before go-live.

---

## 6. Test strategy

| Layer | Coverage |
|---|---|
| Tenancy | Two organisation fixtures; cross-organisation read, update and delete all denied; fails closed with no context |
| Workflow | A run interrupted mid-flight resumes; the correlation ID survives the resume |
| Audit | Every configuration change writes a row; no update or delete path exists |
| Sovereignty | Each of the four verdicts; cross-geo denied by default; the check cannot be bypassed from the client |
| Redaction | A token passed through a logged path never appears in the log |
| Screens | Every state named in FOUNDATION 8, not only the happy path |
| Regression | The 86 existing tests keep passing |

Rendered in a browser in both themes and at 390px, as with the work so far.

---

## 7. Rollback

Every migration reversible and exercised. All changes additive; nothing is dropped or renamed. Deployment rollback is a revert commit on `main`. Production migrations stay a separately approved action.

---

## 8. Open items carried forward

1. Control-plane hosting geography unconfirmed.
2. Approved storage and processing geographies unset; required before Phase 02 provisioning.
3. Custom business domains deferred, per D4.
4. Existing flat code moves into modules after Batch A proves the boundary, per D2.

---

## 9. Approval

Nothing in section 3 has been implemented. On approval I will build Batch A and open its pull request, then stop for review before Batch B.
