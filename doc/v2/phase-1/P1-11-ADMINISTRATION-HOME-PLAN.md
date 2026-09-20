# P1-11 — Administration Home: PLAN

**PLAN ONLY, AND NOT THE ACTIVE UNIT.** No design, no implementation, no
schema, no deployment.

> **RENUMBERED AND DEFERRED — Product Owner amendment, 20 September 2026**
> (`PRODUCT-OWNER-AMENDMENT-PLATFORM-SETUP-AND-BOOTSTRAP.md`).
>
> This document was written and reviewed as **P1-10 — Administration Home**
> (PR #127). The amendment inserts **P1-10 — Platform Integrations & Setup**
> ahead of it, so Administration Home becomes **P1-11**.
>
> **Nothing in the analysis is withdrawn, and D-130 – D-147 are carried forward
> intact.** The amendment makes the central finding *stronger* rather than
> weaker: this unit owns no fact, and after P1-10 there are more real sources
> to project and fewer excuses to invent. §1.1 gains the integration sources
> P1-10 will deliver.
>
> **This unit is NOT active.** It must not be designed or implemented before
> **P1-10 is accepted**. It is kept here so the work and the Product Owner's
> decisions are not lost, not as a queue-jumping plan.

| | |
| --- | --- |
| Unit | **P1-11 — Administration Home** (delivery order 13). **The last Phase 1 unit** |
| Renumbered from | **P1-10**, 20 September 2026. PR #127 was superseded, not merged |
| Authority | Blueprint §2.6.1 — *"Readiness, action queue, security posture, exceptions — single operational summary for the platform/organisation administrator"*; §3.3 — *"Organisation readiness, users, domains, security posture, exceptions and action queue"* |
| Sidebar | `ApprovedMenu` already carries **Administration Home** in first position, `locked`, `i-grid`. It becomes a `leaf` when this unit ships |
| Blocked by | **P1-10 — Platform Integrations & Setup**, which must be accepted first |
| Schema | **NONE proposed.** See §8 |
| Decisions | **D-130 – D-147 OPEN and carried forward unchanged.** P1-10 numbers its own decisions after them |
| Status | **DEFERRED — not the active unit.** Reactivate after P1-10 acceptance |

---

## 0. The one sentence this unit is most likely to violate

> **Administration Home owns no fact.**

Every unit before it answers a question nobody else answers. This one answers
**none**. It is a roll-up, which makes it the unit most likely to reimplement a
check to make a tile look right — and a roll-up that disagrees with the screen
it summarises is worse than no roll-up, because a person who has been
contradicted once stops using both.

**P1-09 already hit this exact failure and it is worth restating**, because
P1-10 is the same shape with more surface: its first draft named
`HealthInspector::inspect()` as network-free on the strength of a comment, and
the code disproved it. The lesson is not "read the comments more carefully" —
it is *project the source, never restate what it says*.

---

## 1. INVENTORY FIRST — what actually exists to roll up

**Nine delivered units. Every one of them already answers something.** This
section is what the unit may project; anything not listed here does not exist
and must not be invented.

### 1.1 The authoritative sources

| Source | Owner | What it can answer | Shape |
| --- | --- | --- | --- |
| `PostureEvaluator::evaluate()` → `PostureProjection` → `ViewerReport` | **P1-06** | **Security posture and exceptions**, already per-viewer, already leak-closed. `ViewerReport::exceptions()` is stated in its own docblock as *"the single source of the list, the tab count and the heading count"* | `ViewerRow` \| `WithheldRow`, `ViewerMetric`, `ViewerDomain` |
| `AccessReviewItem` / `AccessReviewCycle` with the existing `overdue()` scope | **P1-07** | **Overdue and pending reviews.** The controller already derives an `overdue` count from the same scope as the list | Eloquent scope, per-organisation |
| `SystemHealthReport::areas()` | **P1-09** | **Operational health**, 14 rows, six statuses, and it contacts nobody | `HealthRow`, four allowlisted fields |
| `AuditChainVerifier::verify()` / `AuditChainHead` | **P1-08** | Whether the evidence record is sound — **already projected by P1-09**, so P1-10 should read P1-09's row, not walk the chain again | `{intact, checked, finding, sequence}` |
| `IdentityHealthCheck::storedReport()` | **P1-02 / P1-09** | Stored sign-in state **with its age**, network-free by construction | `StoredIdentityHealth` |
| `BusinessDomain::isEnabled()`, `currentOwnership()` | **P1-04** | Domains enabled, and which have an accountable owner | Eloquent |
| `AccessEngine` | **P1-05** | Effective access. **The one definition** of every access question | Service |
| `Organisation`, `LegalEntity`, `BusinessUnit`, `Department`, `Team` | **P1-01** | Organisational structure | Eloquent |
| `User`, `Group` | **P1-03** | People and membership | Eloquent |
| **Platform Integrations state** | **P1-10** *(not yet built)* | **Integration configuration state, connection status and last-tested time** for Identity/SSO, email, AI and Fabric — and the **installation state**. Added by the 20 September 2026 amendment | To be defined by P1-10 |

### 1.2 What does NOT exist, and must not be assumed

**There is no readiness model anywhere in the codebase.** A search for
`isComplete`, `readiness` or `complete()` across `app/Modules/Organisation`
returns **nothing**. The blueprint's word *"readiness"* has never been defined
as a computable thing in this project.

**This is the single largest open question in the unit**, and it is D-130.

> **The amendment changes the shape of D-130 without answering it.** P1-10 will
> deliver a real installation state and real per-integration configuration
> states. That is **not** the same thing as "readiness" — it is a genuine
> source for part of it, and it removes the need to invent that part. Whether
> readiness means anything *beyond* the integration states is still the open
> question, and it is still the Product Owner's.

Also absent, and not to be invented:

- **no action queue, task, or to-do model** — nothing is assigned to anybody;
- **no notification, digest or alert model**;
- **no "setup wizard" or onboarding-step state**;
- **no dashboard, metric-history or trend storage** — P1-09 established that a
  table holding a page's own render is a table nothing reads.

### 1.3 The landing page that already exists, and is NOT this

`/console` → `ConsoleController` → `Console/Home.jsx`. Its own docblock says:

> *"Deliberately minimal: it proves an authenticated session reaches a protected
> route, and nothing more. No sidebar, no Administration Home, no menu … It must
> not grow into the shell."*

and

> *"This is NOT Administration Home — P1-10 owns that."*

**It is the D-11 confirmation state**, approved in P1-00. Whether P1-10 replaces
it, sits beside it, or absorbs it is **D-131** — and it is a real decision, not
a formality: `/console` is where sign-in lands, and it is reachable by **anyone
with a session**, including a person with no role at all. Administration Home
is not.

---

## 2. The distinctions this unit must draw, and the failures behind each

### 2.1 A roll-up is not a second opinion

Administration Home may show that there are **three open exceptions**. It must
get that three from `ViewerReport::exceptions()` — the same derivation Security
Status counts — and never from its own query. **The count and the list must be
incapable of disagreeing**, which means one derivation, not two that agree
today.

### 2.2 Visibility is not authority — again

P1-05's lesson, and P1-06 nearly shipped the inverse of it: the first
implementation filtered the Security Status listing by decision authority and
showed an Auditor an empty screen. **Reaching a summary is not holding what it
summarises**, and a tile linking to a screen is not permission to open it —
every destination re-authorises on arrival.

### 2.3 A summary of evidence is not evidence

P1-08's D-124 rule, which P1-09 honoured: System Health shows that the record is
**sound**; it shows **none of its contents**. Administration Home is under the
same rule and closer to the temptation, because "recent activity" is exactly the
sort of tile a generic admin dashboard grows. **D-136.**

### 2.4 A number is not a status

P1-06 already established this and built for it: `.sec-metric-count` carries
**no semantic colour at all**, because colouring a count turns an informational
metric into a finding. *"12 users"* is not good or bad. **D-138.**

### 2.5 Zero is not the same as nothing to show

An empty action queue on a healthy deployment and an empty action queue because
the viewer may not see any of it are different facts with the same rendering.
P1-06's `WithheldRow` exists for exactly this. **D-139.**

---

## 3. Deployment reality this unit must respect

Established by P1-09, not assumed:

| Fact | Consequence for P1-10 |
| --- | --- |
| `QUEUE_CONNECTION=sync`, no worker, no scheduler | **Nothing can be precomputed on a timetable.** Every tile is derived at render or it does not exist |
| `CACHE_STORE=file` | A cached roll-up is possible but is a **decision** (D-141), not a default |
| **`SESSION_DRIVER=file`** in production against a `database` target | **Carried Phase 1 finding.** P1-10 **must not** resolve, restate or quietly absorb it |
| Microsoft Entra is the only external integration | Administration Home must **contact nobody**, exactly as P1-09 does |

**The render cost is a real question, not a theoretical one.** Administration
Home would invoke the posture evaluator, the review scopes, the health report
and the access engine in one request — on shared cPanel hosting, synchronously.
**D-140 and D-141** exist because a landing page that takes four seconds is a
landing page people route around.

---

## 4. Security boundaries — inherited, not renegotiated

Administration Home must never expose:

- passwords, secrets, access or refresh tokens, client secrets;
- connection strings, raw `.env` values, hostnames, tenant or directory ids;
- unrestricted exception traces or SQL;
- **audit log contents** — D-124, inherited;
- **business-domain records**, learner, customer or business payloads;
- any row a viewer's own projection withholds.

**P1-06's projection already enforces the last two**, which is the strongest
argument for projecting it rather than querying posture again.

---

## 5. What this unit must NOT build

- **No second posture evaluator, review scope, health check or chain walk.**
- **No new access model, role, action class or authority.**
- **No task, notification, digest or alert system.**
- **No metric history, trend, graph or uptime percentage.**
- **No remediation.** Every affordance is a **link** to the screen that owns the
  control, which re-authorises on arrival — P1-06's rule, unchanged.
- **No schema** unless §8 is overturned.
- **No new `SecurityEventLogger` key.** Rendering a summary records nothing.
- **No change to `/up`, `semantiq:health`, D-19, or any carried item.**

---

## 6. Open decisions — D-130 to D-147

### The one that shapes the unit

**D-130 — What is "readiness", and is it in scope at all?**
The blueprint names it twice and the codebase defines it nowhere.
*Options:* **(a)** drop it — the other three areas are well-sourced and
readiness is the only one requiring invention; **(b)** define it as a small,
explicit checklist over facts that already exist (an organisation exists; at
least one legal entity; at least one enabled domain with an owner; identity
configured; at least one administrator); **(c)** defer it to Phase 2 with the
Fabric readiness screen, which the blueprint pairs it with.
*Recommendation:* **(b), and only over facts that already exist** — with the
checklist itself approved item by item, because every item is a product
statement about what a configured SemantIQ is.

### Scope and placement

**D-131 — Does Administration Home replace `/console`, or sit at its own route?**
*Recommendation:* **its own route**, and `/console` keeps the D-11 confirmation
state. A person with no role must still land somewhere honest, and that is not
an administrator's summary.

**D-132 — Who may reach it?** `PlatformAdmin`, `OrgAdmin`, or `EvidenceRead`?
The blueprint says *"platform/organisation administrator"*.
*Recommendation:* **`OrgAdmin`**, with what is **valued** decided by projection
— the P1-06 pattern — so an Organisation Administrator sees their organisation
and platform rows are named but not valued.

**D-133 — Is `RequireOrganisation` present?** Security Status deliberately omits
it so a day-one deployment is not redirected to Company Profile.
*Recommendation:* **absent**, same reasoning.

**D-134 — Which four areas ship?** Readiness, action queue, security posture,
exceptions — or a subset, if D-130 removes readiness.

**D-135 — Is the action queue actionable, or a list of links?**
*Recommendation:* **links only.** An action queue implies assignment, state and
completion — three models that do not exist.

### Content boundaries

**D-136 — Any "recent activity" tile?** *Recommendation:* **no.** D-124.

**D-137 — Are business counts shown** (users, groups, domains, teams)? These are
organisational, not business-domain, data — but they are the thin end.

**D-138 — Do counts carry colour?** *Recommendation:* **no**, per §2.4.

**D-139 — How is "withheld" distinguished from "zero"?**

**D-140 — What is the render budget**, and what happens when a source is slow or
throws? *Recommendation:* a tile that cannot answer says so, in P1-09's
`Not checked` sense, rather than rendering a zero.

**D-141 — Is any part cached?** *Recommendation:* **no** for the first delivery
— a stale summary is the failure this unit is most exposed to, and P1-09 showed
that a cached answer needs an age beside it to stay honest.

### The rest

**D-142 — Does the node become a `leaf` in `ApprovedMenu`,** and does it stay
first in System Administration?

**D-143 — Does it render for a viewer whose every tile is withheld**, or refuse?

**D-144 — Empty-state wording** for a deployment with nothing configured yet.

**D-145 — Does it link to P1-09 System Health**, given that unit is
`PlatformAdmin` and this one may be `OrgAdmin`? A link to a screen the viewer
cannot open is the discoverability failure D-19 was written against.

**D-146 — Does the Product Owner test script run against a real deployment with
real exceptions**, or must some tiles be carried like P1-08's and P1-09's?

**D-147 — What, exactly, closes Phase 1?** P1-11 is the last unit; its
acceptance is not the same event as Phase 1 acceptance, which still needs the
**P1-02 SSO re-check gate**, the **production session-driver alignment** and
the **privilege-change / session-revocation verification** resolved.

---

## 7. Carried items P1-10 must NOT resolve

| Item | Status |
| --- | --- |
| **P1-02** provider-wide SSO Re-check | **OPEN / CARRIED / UNVERIFIED.** Needs a genuine second permanent System Administrator. **Do not create one** |
| **Production session-driver alignment** | **OPEN / CARRIED.** Target `database`, production `file`. **Not P1-10's to fix, absorb or restate** |
| **P1-07** carried items | Carried, unchanged |
| **P1-08** carried items | Carried, unchanged |
| **P1-09** carried items | Carried, unchanged |
| **D-19** | **Unchanged.** P1-10 widens no navigation |

---

## 8. SCHEMA — NONE PROPOSED

**Every tile is derivable from facts other units already store.** The one
candidate would be an action-queue or readiness-state table, and both would be
storing a *judgement about* existing data rather than a new fact — which goes
stale the moment the underlying data changes and is then wrong in the most
convincing possible way.

**If DESIGN finds a requirement that genuinely needs storage, it is a blocker to
be raised, not a table to be added.**

---

## 9. Status

**PLAN ONLY — DEFERRED. NOT THE ACTIVE UNIT.**
**Blocked by P1-10 — Platform Integrations & Setup, which must be accepted
first.**
No design. No implementation. No schema. No deployment.
**D-130 – D-147 are open and none is assumed.**
**P1-02 remains OPEN / CARRIED / UNVERIFIED. The production session-driver
alignment remains OPEN / CARRIED. P1-07, P1-08 and P1-09 carried items remain
carried. D-19 unchanged.**
