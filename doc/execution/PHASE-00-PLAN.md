# Phase 00 Implementation Plan

**Reference:** P00-FND, `doc/phases/PHASE-00-FOUNDATION.md`
**Status: PENDING USER APPROVAL. No material implementation has begun.**
**Completion phrase (later, not now):** `CONFIRM PHASE 00 COMPLETE`

---

## Repository observations

Observed directly in the repository at commit `136dec0`, not assumed.

### Stack, confirmed against the approved baseline

| Item | Observed | Matches baseline |
|---|---|---|
| Backend | Laravel 13.26.1, PHP 8.3+ constraint, PHP 8.5 on the server | Yes |
| Frontend | React 19.2.8, Vite 8, plus server-rendered Blade for auth and shell | Yes |
| Database | MySQL on cPanel (`smntqc2sadm_transdb`), SQLite in the test suite | Yes |
| Architecture | Modular monolith. `app/Modules/` does not exist yet | Partially |
| Deploy | GitHub Actions to cPanel over SSH, `main` only, live | Yes |

### What is already built and deployed

| Area | State | Evidence |
|---|---|---|
| Design system | `resources/css/app.css`, tokens from the design template in both themes | Live, serving |
| Sign-in | Entra ID OIDC, authorization code with PKCE, single-use state, nonce check | Live, 21 tests |
| Roles | `app/Enums/Role.php`, five cumulative tiers | Live |
| Application shell | Rail, top bar, four fixed clusters, theme switcher, nav filter, access gating | Live, 42 tests total |
| Navigation config | `config/navigation.php`, single source of truth for sidebar, breadcrumb and guards | Live |
| CI | Build, Pint, PHPUnit run on the runner before deploy | Passing |

### What Phase 00 requires and does not exist

| Required output | State |
|---|---|
| Organisation/tenant context foundation | **Absent.** No `organisations` table, no `organisation_id` on any record, no global scope |
| Configuration data model baseline | **Absent.** Only `users`, `sessions`, `cache`, `jobs` exist |
| Secret-provider abstraction | **Absent.** Entra values read directly from config |
| Workflow orchestration service | **Absent.** No queue worker, no `WorkflowRun`, no correlation IDs |
| Audit log framework | **Absent.** No `AuditEvent` |
| Help Centre framework | **Absent.** No `HelpTopic`, no contextual help |
| Capability registry | **Absent** |
| Status model (10 states) | **Absent** |
| `DataProtectionProfile` | **Absent** |
| Context registers populated | **Absent.** All six carry only the example row |

### Two findings from reading the v1.3 documents

**1. Six help topic IDs are referenced but are not in the index.** Cross-checking every `HLP-*` mentioned in the SRS, the phase documents and the reference set against `doc/reference/HELP_TOPIC_INDEX.md`:

`HLP-AUD-001`, `HLP-MDL-001`, `HLP-ORG-001`, `HLP-SRC-001`, `HLP-SRC-003`, `HLP-VAL-001`

Two of them, `HLP-ORG-001` and `HLP-AUD-001`, are required by this phase's own screens (SC-002 and SC-026). See decision D3.

**2. No functional requirement row maps to P00-FND.** Every `FR-*` in the traceability table maps to P01 onward; the count of `P00` matches in the functional-requirements section is zero. Phase 00 traces through screens, non-functional requirements and the v1.3 sovereignty and context requirements only. This appears intentional for a foundation phase and is recorded here so the traceability rule is satisfied knowingly rather than by omission.

---

## SRS IDs in scope

- **Requirements:** FR-DPS-001 (DataProtectionProfile), FR-DPS-003 (cross-boundary block and exception), FR-DPS-007 (safe logs and support data), FR-CTX-001 (living context), FR-CTX-002 (validation context), FR-CTX-003 (configuration context). No `FR-*` row from the main functional table maps to this phase, as recorded above.
- **Screens:** SC-002 Organisation Setup, SC-025 Help Centre, SC-026 Audit Log.
- **APIs:** none. No row in the API register maps to P00-FND, and no Microsoft call is made in this phase.
- **Help topics:** HLP-ORG-001, HLP-AUD-001, HLP-SOV-001, HLP-CTX-007.
- **Acceptance scenarios:** none map to P00-FND. AT-001 to AT-016 are Phase 09.
- **Non-functional:** NFR-SEC-01, NFR-SEC-02, NFR-PERF-02, NFR-OBS-01, NFR-COMP-01, NFR-COMP-02, NFR-MNT-01, NFR-SUP-01, NFR-A11Y-01.

---

## Proposed implementation

Ten work items. Each is independently reviewable and the order reflects real dependency.

### W1. Organisation and tenant boundary

The spine of the phase and of NFR-SEC-02.

- `organisations` migration: `organisation_id`, name, status, region, `owner_user_id`, timestamps, per SRS section 17.
- `organisation_id` on `users`, and on every customer-owned table created afterwards.
- A global Eloquent scope resolving the active organisation from the authenticated session, applied through a trait rather than remembered per model.
- The scope **fails closed**: no active organisation context means no rows, never all rows.
- Tests that create two organisations and assert a request in one cannot read, update or delete a record in the other, per the phase checklist's explicit allowance for multiple fixtures under single-customer deployment.

### W2. Configuration data model baseline

Migrations for the entities Phase 00 names, using the SRS section 17 field lists:

`organisations`, `workflow_runs`, `audit_events`, `help_topics`, `fabric_items`, `data_protection_profiles`

Each with a working `down()`, each carrying `organisation_id` where the record is customer-owned. Nothing from later phases: no sources, no connections, no semantic models.

### W3. Status model

The ten states from SRS section 18.1 as a PHP enum: Not Started, In Progress, Action Required, Approval Required, Ready, Succeeded, Warning, Failed, Drift Detected, Revalidation Required.

Mapped onto the design system's six badge roles, since the template's badge palette is a closed set:

| Status | Badge role | Reasoning |
|---|---|---|
| Not Started | neutral | Nothing has happened |
| In Progress | info | Underway, no judgement yet |
| Action Required | warning | A person must act |
| Approval Required | violet | Distinct from Action Required: waiting on authority, not effort |
| Ready | info | Prerequisites met |
| Succeeded | success | |
| Warning | warning | |
| Failed | danger | |
| Drift Detected | warning | External change, not yet a failure |
| Revalidation Required | warning | Evidence stale, not yet invalid |

### W4. Workflow orchestration

- `WorkflowRun` with `workflow_type`, `organisation_id`, status, `current_step`, `correlation_id`.
- Laravel queued jobs. **No inline sleeping and no held browser request** (NFR-PERF-02).
- Long-running-operation polling by re-queueing with a delay, so a restart resumes rather than restarts.
- A correlation ID minted per run and carried through every log line and audit row.
- A sample workflow that can be killed mid-run and resumes, which is what the checklist actually asks to be proven.

### W5. Audit framework

`AuditEvent`: actor, action, target, `before_hash`, `after_hash`, `api_request_id`, result, timestamp, `correlation_id`, `organisation_id`.

Append-only by construction: no update or delete path in the model. Hashes rather than payload copies, per NFR-COMP-01, minimising business data in the control plane.

### W6. Secret-provider abstraction

An interface with an env-backed implementation for now. **No real credential is stored in this phase** - that is Phase 01. The point is that Phase 01 has somewhere to put one that is not a config file, and that the interface can be re-pointed at a secret manager without touching callers.

### W7. Data protection profile and the sovereignty gate

- `data_protection_profiles`, versioned, organisation-scoped, with the fields from the standard's section 4: approved storage and processing geographies, three cross-geo flags defaulting to **false**, public access, CMK, Purview labels, DLP, retention class, production payload logging **false**, data export, support capture **false**.
- Profile changes are privileged, versioned and audited.
- `VAL-SOV-GEO-001` as a **server-side** reusable check returning PASS, WARNING, EXCEPTION_REQUIRED or BLOCKED. Server-side matters: a client-side check is decoration.
- Logging redaction: credentials and tokens never logged; production payload capture off by default (FR-DPS-007).

### W8. Help framework

`HelpTopic` per SRS section 15.1, plus a contextual help control on one screen so the checklist item is genuinely demonstrable rather than asserted. Seeds `HLP-ORG-001`, `HLP-AUD-001`, `HLP-SOV-001`, `HLP-CTX-007`, subject to decision D3.

### W9. Capability registry

An interface describing whether a Microsoft operation is stable, preview or guided-only, so Phase 02 onward can branch on capability rather than on hardcoded assumptions (NFR-MNT-01). Interface and registry only; no Microsoft call in this phase.

### W10. Screens, navigation and context registers

- **SC-002 Organisation Setup** - the first real form, including the data protection profile fields the v1.3 controls require.
- **SC-026 Audit Log** - the first real list/index screen: sort, filter, pagination, URL state.
- **SC-025 Help Centre** - topic list and detail.
- **Navigation corrected** per decisions D1 and D2 below.
- All six context registers populated for everything built, with CI failing a behaviour change that leaves them stale (FR-CTX-001).

---

## Files and components affected

| Area | Change |
|---|---|
| `app/Modules/` | New. Introduces the modular-monolith boundary the baseline names but that does not yet exist |
| `app/Models/` | Organisation, WorkflowRun, AuditEvent, HelpTopic, FabricItem, DataProtectionProfile |
| `app/Enums/` | WorkflowStatus, DataClassification, SovereigntyVerdict |
| `app/Support/` | Organisation scope, correlation context, secret provider, capability registry |
| `app/Jobs/` | Resumable workflow base and the sample |
| `database/migrations/` | Six or seven new, each reversible |
| `config/navigation.php` | Re-sequenced per D1, Projects group resolved per D2 |
| `app/Enums/Role.php` | Labels only, per D4 |
| `resources/views/shell/` | Three new screens |
| `doc/context/*` | All six populated |
| `.github/workflows/` | Context-staleness check |

---

## Data model and migrations

New tables only; nothing existing is dropped or renamed. Every migration reversible, verified by a full `up` then `down` then `up` cycle on both drivers where the environment allows.

**Naming follows the SRS section 17 entity list, not `doc/00`.** `doc/00`'s first-cut list used `tenants`, `projects`, `blueprints`, `stages`, `steps`. The SRS uses `Organisation`, `WorkflowRun`, `AuditEvent` and has no project or blueprint concept at all. Only `users` exists as a migration today, so aligning now costs nothing; aligning after five phases would cost a great deal. See D2.

**Running the migration on the live database is a separate action and is not covered by approving this plan.** I will ask before it is run.

---

## Microsoft APIs, identity and permissions

**None.** Phase 00 makes no Microsoft call. The capability registry (W9) is an interface awaiting Phase 02. The Microsoft freshness rule therefore does not trigger in this phase, and no decision record is required under it.

---

## Security and privacy impact

| Control | Approach |
|---|---|
| Tenant isolation | Global scope failing closed, proven by negative cross-organisation tests. A release blocker under the standard's section 9 |
| Secrets | Abstraction only; no credential stored this phase. Nothing reaches the browser |
| Logging | Credentials and tokens redacted; production payload capture off by default |
| Audit | Append-only, hashes not payloads |
| Data minimisation | Resource IDs and metadata, not business data (NFR-COMP-01) |
| Sovereignty | `VAL-SOV-GEO-001` server-side, deny-by-default cross-geo |
| Accessibility | WCAG 2.1 AA on all three new screens, per NFR-A11Y-01 |

---

## Test strategy

| Layer | Coverage |
|---|---|
| Tenant boundary | Two-organisation fixtures; cross-organisation read, write and delete all denied. **The phase's central test** |
| Workflow resumption | Job interrupted mid-run resumes and completes; correlation ID stable across the resume |
| Audit | Every configuration change writes a row; no update or delete path exists |
| Sovereignty | `VAL-SOV-GEO-001` returns each of the four verdicts; cross-geo denied by default; server-side enforcement cannot be bypassed from the client |
| Redaction | A token passed through a logged path never appears in the log |
| Screens | Success, empty, loading, validation, permission-denied, error and small-screen states for all three |
| Registers | CI fails when a behaviour change leaves a register stale |
| Regression | The existing 42 tests continue to pass |

Rendered in a browser in both themes, as with previous work, rather than asserted from markup alone.

---

## Rollback and recovery

- Every migration reversible; the `down` path exercised before the `up` is proposed for production.
- All new tables are additive. No existing table is dropped or renamed, so a rollback cannot destroy shipped data.
- Deployment rollback is a revert commit on `main`, which re-runs the pipeline.
- The live database is untouched until you explicitly approve running the migration, as a separate decision from approving this plan.

---

## Assumptions and blockers

### Decisions required before I start

**D1. Navigation order - recommended, not blocked.**
`doc/CONFLICT_RESOLUTION_v1.3.md` already rules that the design system wins on layout and the SRS on feature behaviour. That settles it: the four fixed clusters stay, and the Workspace groups re-sequence into the SRS section 7.1 lifecycle order - Setup, Fabric, Data Sources, Data Foundation, Business Model, Governance, AI and Agents, Validation, Deploy, Monitor. I will proceed on this reading unless you say otherwise.

**D2. Projects and Blueprints - blocked, needs your answer.**
The shipped sidebar leads with a Projects group containing Blueprints. The SRS contains the word "blueprint" **zero times** and "project" twice, incidentally. The Blueprint engine comes from `doc/00`, which I wrote. The SRS models the same idea as `WorkflowRun` with `current_step`.

*Recommendation:* drop Projects and Blueprints from the navigation, model `WorkflowRun` per the SRS, and record the Blueprint engine in `doc/00` as superseded. Every node under it is currently a disabled `Soon` placeholder, so nothing breaks.

*Why I will not decide this myself:* it discards a design you may have valued, and it changes `doc/00`.

**D3. Six missing help topics - blocked.**
`HLP-ORG-001` and `HLP-AUD-001` are required by this phase's screens but are absent from the index, along with `HLP-MDL-001`, `HLP-SRC-001`, `HLP-SRC-003`, `HLP-VAL-001`. Do these exist in a document I have not seen, or should I draft them for your review and add them to the index?

**D4. Role labels - blocked.**
Keep Platform Administrator, Tenant Administrator, Lead Data Engineer, Data Engineer, Business User; or realign to the SRS section 3 personas, where "Lead Data Engineer" matches nothing and the stewardship roles are Data Owner/Steward, Semantic Model Owner and AI/Agent Owner. Display labels only; tier codes do not change either way.

**D5. Approved geographies - blocked, and I must not guess.**
The `DataProtectionProfile` needs real values for approved storage and processing geographies. The standard's example says Singapore; I will not infer a customer's data residency from an example. What are the approved geographies?

**D6. Retention defaults - blocked.**
`CLAUDE.md` records seven years for operational data, audit and backups. The sovereignty standard's profile example says "90 days metadata" and "no indefinite retention by default". These can coexist across different classes, but the profile needs concrete defaults.

*Proposal:* audit and compliance events seven years; operational metadata 90 days; both policy-driven and overridable. Confirm or correct.

**D7. Draft storage - blocked before SC-002 only.**
`doc/06` questions Q2a to Q2d are unanswered. SC-002 is a multi-step form with a Save Draft action in the SRS section 7.2 footer, so it needs them. Everything else in this phase can proceed without them.

### A process irregularity I need to record honestly

**Phase 01 scope is already built and deployed.** `FR-AUTH-001`, Entra SSO with authorization code and PKCE, traces to **P01-IDN**, which `IMPLEMENTATION_STATUS.md` currently shows as `LOCKED`. It shipped in PR #5, before the v1.3 phase gate existed in this repository.

This is not a failure of the gate - the gate arrived afterwards - but the status file should not silently pretend otherwise.

*Proposal:* note it against Phase 01 in `IMPLEMENTATION_STATUS.md` as pre-gate work requiring verification against the Phase 01 checklist when that phase opens, rather than back-dating a confirmation nobody gave. **I will not mark anything CONFIRMED.** Tell me if you would rather handle it another way.

### Assumptions I will proceed on unless corrected

1. Phase 00 covers only the ten items above. No Fabric call, no source, no semantic model.
2. The design template stays the sole UI authority for layout, theme and brand.
3. Single-customer deployment, with organisation scoping preserved for future multi-tenant enablement.
4. No new runtime, framework or major dependency without separate approval. If a queue driver beyond Laravel's database driver turns out to be needed on cPanel, I will raise it rather than adopt it.

---

## Data protection and sovereignty

- **Data classifications in scope:** Internal (organisation configuration, workflow state, audit metadata). No Confidential or Restricted customer business data enters the control plane in this phase.
- **Approved storage geography:** `<PENDING D5>`
- **Approved processing geography:** `<PENDING D5>`
- **Cross-geo settings required:** No. All three flags default to false, per the standard.
- **Network controls:** Not applicable this phase; no Fabric or Azure resource is provisioned. Evaluated from Phase 02.
- **Encryption and CMK:** Profile field created and enforced from Phase 02. cPanel MySQL at-rest posture to be recorded in the sovereignty register, not assumed.
- **Purview label, DLP:** Profile fields created; evaluated from Phase 03.
- **Retention, deletion, logging:** `<PENDING D6>`. Production payload logging false by default. Credentials and tokens never logged.

---

## Context register impact

| Register | Entries |
|---|---|
| Code | One per new module: organisation scope, workflow engine, audit writer, secret provider, capability registry, sovereignty check, help framework |
| Data | Every new entity, with owner, classification, retention and residency |
| Validation | `VAL-SOV-GEO-001` and the organisation-boundary rule, with stable IDs, enforcement point, help topic and tests |
| Configuration | Every new config key, with scope, secret impact and residency impact |
| Sovereignty | The control-plane storage flow: what is stored, where, and under which approved geography |
| Security and privacy decisions | Fail-closed scoping, append-only audit, hashes over payloads, deny-by-default cross-geo |

Each register is updated **in the same change** as the behaviour, not afterwards. A behaviour change leaving a register stale is a verification failure under `CLAUDE.md`.

---

## User approval

**Status: Pending.**

Nothing in this plan has been implemented. Reply with corrections, or approve to proceed.

D2, D3, D4, D5 and D6 block the start of the work they touch. D1 I will proceed on as reasoned above. D7 blocks only SC-002.

On approval I will set Phase 00 to `IN_PROGRESS` in `IMPLEMENTATION_STATUS.md`, implement only what is above, and produce `doc/execution/PHASE-00-VERIFICATION.md` with real evidence before asking for the completion phrase.
