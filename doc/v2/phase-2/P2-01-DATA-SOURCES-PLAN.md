# P2-01 — Data Sources — PLAN (Gate A)

**Fabric Configuration Phase, unit 1 of 14.** Plan only. No design, no code, no
schema, no production change.

| | |
| --- | --- |
| Phase | **2 — Fabric Configuration Phase** |
| Unit | **P2-01 — Data Sources** |
| Authority | `doc/SemantIQ_v2_PHASE_2_Fabric_Configuration.md` §5 |
| Baseline | `main` = `03f238ee52d95645b91a41d6271069b75f6b2320` |
| Gate | **A — PLAN.** Design needs separate approval |

---

## 1. What this unit is

A **catalogue of source systems** and the governance context around each one.

The phase document's exit criterion is the whole scope in one line:

> **Source catalogue exists without performing ingestion.**

It records *what systems exist, who owns them, which domain they belong to and
how sensitive they are*. It connects to nothing, moves no data, and reads no
source.

## 2. What it deliberately does NOT do

| Not in P2-01 | Belongs to |
| --- | --- |
| Connecting to a source | **P2-02 Connect Source** |
| Reading schema or metadata | P2-03 Discovery |
| Any data movement | P2-05 Ingestion |
| A connector catalogue | P2-02, and *one* connector first |
| Fabric API calls of any kind | P2-02 onward |

§4 is explicit: *"Only one P2 menu unit may be active at any time"* and *"do
not prebuild downstream semantic/AI features before upstream data is
accepted."*

## 3. What it consumes from Phase 1 — and must not reinvent

The phase document requires Phase 2 to *"consume the identity, role, domain,
scope and sensitivity model produced by Phase 1"* and *"must not invent a
separate access model."* Everything needed already exists:

| P2-01 needs | Phase 1 component | State |
| --- | --- | --- |
| Business domain | `BusinessDomain`, `DomainKind`, `DomainStatus` | Delivered, in production |
| Owner | `DomainOwnership` | Delivered |
| Sensitivity | `Sensitivity`, `EntitlementCeiling` | Delivered |
| Who may administer | `ActionClass`, `RoleCatalogue`, `RequireActionClass` | Delivered |
| Credentials never displayed | Platform Integrations secret store, masking and reveal rules | Delivered, proven in production |

**The Greenfield Rule bars reusing SemantIQ v1, not Phase 1.** Reusing the
Phase 1 access model is mandatory; reinventing it is the error.

---

# 4. DEPENDENCIES ON PHASE 1 — the Product Owner decision

**Four are hard and sit in code.** Three are deliberate tripwires built during
Phase 1 to stop Phase 2 starting by accident. They are working exactly as
intended.

### D1 — The module directory is fenced

`tests/Architecture/NoBusinessSchemaTest::test_only_delivered_modules_exist`
asserts the module list is **exactly** the eleven Phase 1 delivered. Its own
docblock says why:

> *"from here the list is guarding against a PHASE 2 directory appearing —
> Fabric, Semantic, Ingestion or anything else — before its unit is approved."*

**Creating `app/Modules/Fabric/` fails an architecture test.** Opening the fence
is part of delivering P2-01 and is the moment the approval is recorded in code.

**Impact:** none on PLAN. Blocks implementation until P2-01 is approved.

### D2 — Fabric schema is fenced

`NoBusinessSchemaTest::FORBIDDEN` contains `'fabric'`, and
`P1_05_STILL_FORBIDDEN` fails if somebody quietly removes an entry to get a red
test out of the way.

**Impact:** a `fabric`-named table needs the fence opened deliberately. P2-01's
own table (a source catalogue) may not trip it depending on its name — **the
name is a design decision, not a workaround**, and must not be chosen to dodge
the guard.

### D3 — CL-12's acceptance test asserts Fabric is entirely locked  ⚠️ **BLOCKING**

`OrganisationAdministratorNavigationTest::test_an_organisation_administrator_sees_all_fourteen_fabric_nodes_still_locked`
asserts **every one of the 14 Fabric nodes has `route === null` and
`locked === true`**, per node. Its stated reason:

> *"a route here turns a locked node into real access."*

**Delivering Data Sources gives a Fabric node a route and breaks that test by
design.**

**This is the real blocker, and it is about sequencing, not code.** That test is
part of the CL-12 acceptance evidence currently under UAT. Amending it while
Sowjanya is testing against it would change the thing being tested mid-test.

> **P2-01 cannot be IMPLEMENTED until CL-12 is accepted.** PLAN and DESIGN are
> unaffected.

### D4 — All 14 Fabric nodes share one policy key

`ApprovedMenu` gives every Fabric node the single key `fabric.view`, and
`fabric.view` is already in the Organisation Administrator allow-list.

**So the moment any Fabric node gains a route, BOTH administrators can open
it** — there is no per-node authorisation inside Fabric Configuration, and no
`ActionClass` currently guards a Fabric route.

**Two Product Owner decisions are needed before design:**

1. **Who may use Data Sources?** Both administrators, or System Administrator
   only? Today's navigation answers *both*.
2. **One key or fourteen?** Keeping `fabric.view` means every future Fabric
   screen is visible to whoever can see any of them. Splitting per node is a
   navigation change with CL-12 implications.

**Neither is mine to decide.** Both are raised here rather than settled in
design.

### Soft dependencies — do not block P2-01

| | |
| --- | --- |
| **Microsoft Fabric not configured** | Administration Home reports *Not configured*. **Blocks P2-02**, not P2-01 — the catalogue connects to nothing |
| **Phase 1 not fully accepted** | The phase document's stated prerequisite. CL-10 on hold, CL-11 not started, CL-12 in UAT |
| CL-10 / CL-11 | **No dependency.** Session driver and revocation do not touch a source catalogue |

---

## 5. Recommendation

**Proceed with PLAN and DESIGN. Do not implement yet.**

| Stage | Status |
| --- | --- |
| PLAN | **Safe now** — no code, no fences touched |
| DESIGN | **Safe now**, once D4's two questions are ruled |
| IMPLEMENT | **Blocked by D3** until CL-12 is accepted |
| DEPLOY | After implementation, normally |

This follows the phase document's own §4 — *"PLAN and stop. DESIGN and stop."*
— and honours the Phase 1 prerequisite without wasting the UAT wait.

## 6. Scope of the unit, from §5

**Fields:** source name/type · owner · business domain · status · refresh
expectation · sensitivity · connection health.

**Rules:** a source record does not automatically ingest data · credentials are
never displayed · a source cannot be published without required ownership and
domain data.

**Tests named by the phase document:** create and edit a source definition ·
invalid domain or owner · unauthorised administrator access · secret redaction
· audit of changes.

## 7. What DESIGN must settle

1. D4's two rulings — who may use it, and one policy key or fourteen.
2. The table name, against D2's fence, chosen on merit.
3. Whether `connection health` is stored or derived — it is the one field that
   looks like it needs a connection, and P2-01 performs none.
4. What `status` means for a source that connects to nothing yet.
5. How *"cannot be published without required ownership/domain data"* is
   enforced — and what "published" means before any connector exists.
6. Which `ActionClass` guards the route.
7. The audit trail, reusing the Phase 1 evidence model.

## 8. Risks

| Risk | Handling |
| --- | --- |
| **Scope creep into P2-02** | The exit criterion is a catalogue with no ingestion. A connector appearing in P2-01 is the failure |
| **A tripwire removed to get green** | D1–D3 exist to force a decision. Each is opened deliberately, in its own commit, with the reason recorded |
| **Reinventing Phase 1 access** | Forbidden by the phase document. Domains, owners and sensitivity are consumed, never duplicated |
| **"Connection health" pulling a connector forward** | Settle in design: derived and empty until P2-02, or not shown at all |

## 9. Gate A exit

This PLAN is complete when the Product Owner:

1. approves the scope in §1 and §2;
2. **rules on D4's two questions**;
3. confirms **implementation waits for CL-12 acceptance** (D3);
4. authorises DESIGN to begin.

**No code is written until Gate B design approval.**
