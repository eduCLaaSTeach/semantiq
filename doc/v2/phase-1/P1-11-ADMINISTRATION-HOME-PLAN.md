# P1-11 — Administration Home: PLAN

**PLAN ONLY. ACTIVE UNIT.** No design, no implementation, no schema, no
deployment — until this PLAN is approved.

> **ACTIVATED — 21 September 2026.** P1-10 Platform Integrations & Setup was
> **Product Owner accepted, Gate D closed, P1-10 closed**
> (`P1-10-PLATFORM-INTEGRATIONS-ACCEPTANCE.md`). That was this unit's only
> blocker, and it is now **CLOSED / ACCEPTED**.
>
> **This document was written before P1-10 existed** and reviewed as
> **P1-10 — Administration Home** (PR #127). Two things follow, and both are
> done rather than promised:
>
> - **§1 has been re-inventoried against current `main`**, not updated from
>   memory. The old *"nine delivered units"* sentence is gone, the source map
>   is the actual one, and **three sources are recorded as having no safe
>   projection seam** — named as PLAN issues rather than quietly worked around.
> - **D-130 – D-147 are Product Owner APPROVED** and are recorded as decisions,
>   not questions. §6 states each as a ruling.
>
> **PLAN APPROVED 21 September 2026**, with **D-179, D-180 and D-181** deciding
> the three seams — §6a. **All three PLAN issues are RESOLVED.**
>
> **Nothing in this document is open.** The next stage is **DESIGN**, and this
> document authorises no implementation, no schema and no deployment.

| | |
| --- | --- |
| Unit | **P1-11 — Administration Home** (delivery order 13). **The last Phase 1 delivery unit** |
| Renumbered from | **P1-10**, 20 September 2026. PR #127 was superseded, not merged |
| Authority | Blueprint §2.6.1 — *"Readiness, action queue, security posture, exceptions — single operational summary for the platform/organisation administrator"*; §3.3 — *"Organisation readiness, users, domains, security posture, exceptions and action queue"* |
| Sidebar | `ApprovedMenu` carries **Administration Home** in first position, `locked`, `i-grid`. It becomes a `leaf` when this unit ships — **D-142** |
| Blocked by | **NOTHING. P1-10 — CLOSED / ACCEPTED**, 21 September 2026 |
| Schema | **NONE proposed.** See §8 |
| Decisions | **D-130 – D-147 and D-179 – D-181 — ALL APPROVED.** §6 and §6a. P1-10's D-148 – D-178 closed with that unit; **D-178 is this unit's contract** |
| Status | **PLAN APPROVED**, 21 September 2026. **Next stage: DESIGN.** No implementation, no schema, no deployment |

---

## 0. The one sentence this unit is most likely to violate

> **Administration Home owns no source-of-record fact. It owns only the
> approved composition and derived interpretation of facts supplied by earlier
> units.**

> **CORRECTED 21 September 2026.** This read *"Administration Home owns no
> fact"*, which was a useful warning and too absolute to be true. This unit
> legitimately owns something: **the approved readiness composition** — which
> facts count, how they combine, and what the result is called. D-130 makes
> that a Product Owner decision rather than an engineering one, and a decision
> somebody approved is a thing this unit owns.
>
> **The warning the old sentence carried is kept, because it is the real
> risk.** What this unit must never own is a *source-of-record fact*: a status,
> a count or a verdict that it computes for itself rather than reading from the
> unit that owns it.

Every unit before it answers a question nobody else answers. This one answers
**none of those questions again**. It is a roll-up, which makes it the unit
most likely to reimplement a check to make a tile look right — and a roll-up
that disagrees with the screen it summarises is worse than no roll-up, because
a person who has been contradicted once stops using both.

**P1-09 already hit this exact failure and it is worth restating**, because
P1-11 is the same shape with more surface: P1-09's first draft named
`HealthInspector::inspect()` as network-free on the strength of a comment, and
the code disproved it. The lesson is not "read the comments more carefully" —
it is *project the source, never restate what it says*.

---

## 1. INVENTORY FIRST — what actually exists to roll up

**Twelve delivered units.** This section is what P1-11 may project; anything
not listed here does not exist and must not be invented.

> **RE-INVENTORIED against current `main` on 21 September 2026, by reading the
> code.** The previous version of this section said *"nine delivered units"*,
> predated P1-10 entirely, and described three sources as *"Eloquent"* — which
> is not a projection seam, it is the absence of one. That distinction is the
> most useful thing in this section and it is now stated explicitly per source.

### 1.1 The authoritative sources — verified, 21 September 2026

**A SEAM IS A CLASS P1-11 MAY CALL.** Not a model it may query. The column
that matters is the last one: where it reads **SEAM**, P1-11 projects; where it
reads **NONE**, projecting would mean writing this unit's own query against
another unit's tables — which is how a roll-up starts disagreeing with the
screen it summarises.

| Source | Unit | Seam that exists **today** | What it answers | Verdict |
| --- | --- | --- | --- | --- |
| **Security posture** | **P1-06** | `PostureEvaluator::evaluate()` → `PostureReport`, then `PostureProjection::for($report, $viewer)` → `ViewerReport`. `RendersPosture::summary()` already produces `aggregate`, `aggregateLabel`, `caption`, `exceptionCount`, `seesPlatformValues` | Posture aggregate **and** open exceptions, already per-viewer and already leak-closed. `ViewerReport::exceptions()` states in its own docblock that it is *"the single source of the list, the tab count and the heading count"* | **SEAM — strong.** Two tiles from one call |
| **System health** | **P1-09** | `SystemHealthReport::areas()`, built on `HealthInspector::inspectLocal()` and `IdentityHealthCheck::storedReport()` | Operational health, six statuses, **and it contacts nobody** | **SEAM — strong.** `inspectLocal()` already satisfies D-140's zero-network rule by construction |
| **Platform integrations** | **P1-10** | `SetupProjection::forFamily(IntegrationFamily)` → `IntegrationView`, and `::all()`. `IntegrationView` carries `status`, `statusInWords`, `describedAs`, `required`, `lastTestedAt`, and `secrets` as **booleans** | Identity/SSO, Email & Notifications, AI Provider and Microsoft Fabric configuration state | **SEAM — strong.** This is D-178, §1.1a |
| **Sign-in state** | **P1-02 / P1-09** | `IdentityHealthCheck::storedReport()` → `StoredIdentityHealth` | Stored sign-in state **with its age**, network-free by construction | **SEAM — strong.** Already consumed by P1-09; P1-11 should read P1-09's row rather than call it again |
| **Evidence soundness** | **P1-08** | `AuditProjection::scopeVisible(Builder, User, ?int)` and `::row()`; the chain itself via `AuditChainVerifier` | Whether the evidence record is sound | **SEAM — exists, and mostly not to be used.** P1-09 already projects the chain verdict, and **D-136 forbids an activity feed**. P1-11 consumes Audit **indirectly**, through P1-09's row, or not at all |
| **Access reviews** | **P1-07** | `ReviewerAuthority::scopeVisible(Builder, User, ?int)`, plus `AccessReviewItem::scopeOverdue()` | Which review items this viewer may see, and which are overdue | **SEAM — adequate.** Counting a builder the owning unit scoped is composition, not duplication. **P1-11 must not decide visibility itself** |
| **Organisation structure** | **P1-01** | `OrganisationService::current()` — and **nothing else**. Counts of legal entities, business units, departments and teams are queried **inline in each controller** | *"Does an organisation exist"* — yes. *"How much structure exists"* — **no seam** | **PARTIAL — PLAN ISSUE 1** |
| **Users & Groups** | **P1-03** | **NONE.** `UserDirectoryService` and `GroupService` are entirely write-side — provision, deactivate, reactivate, assign, purge. `InteractsWithPeople` holds actor and refusal helpers only | Nothing readable without writing a new query | **NONE — PLAN ISSUE 2** |
| **Business Domains** | **P1-04** | **NONE for reads.** `DomainService` is write-side — create, update, enable, disable, purge, `isPurgeable` | Nothing readable without writing a new query | **NONE — PLAN ISSUE 3** |
| **Effective access** | **P1-05** | `AccessEngine` | The one definition of every access question | **SEAM.** Used for authorisation, not for a tile |

### 1.1a Platform Integration Readiness — P1-10, the D-178 contract

**Added as an authoritative source by the Product Owner amendment of 20
September 2026, and delivered on 21 September 2026.** This is the already-
approved D-178 contract, restated here because this document predates it.

P1-11 **projects**:

- **Microsoft Entra ID / Identity status**, from the **P1-02 authoritative
  identity source as surfaced by P1-10** — not from `.env`, not from
  `config('identity.microsoft.*')`, and not from a second resolver;
- **Email & Notifications** status;
- **AI Provider** status;
- **Microsoft Fabric** status.

P1-11 **does not**:

- run connection tests;
- decrypt credentials;
- call external providers;
- duplicate `SetupProjection` or `IdentityHealthCheck` logic.

> **The identity row is the one to be careful with**, and P1-10 proved why.
> Identity has **two possible authorities** — the environment before the
> controlled cutover, the encrypted store after it — and P1-10 shipped a defect
> that asked the wrong one. It told a deployment whose Microsoft sign-in
> demonstrably works that sign-in was *Not configured*, on the one screen where
> acting on that advice locks everybody out. **P1-11 must read
> `SetupProjection`, which now asks the authority the sign-in path asks.**

### 1.1b The three PLAN issues — **ALL THREE RESOLVED**, §6a

**These were decisions the DESIGN must not make on its own**, because each one
ends either in a new seam owned by the source unit or in P1-11 querying another
unit's tables. **The Product Owner decided all three on 21 September 2026 —
D-179, D-180 and D-181, §6a.** They are kept here with their reasoning, and each
now carries its resolution.

**PLAN ISSUE 1 — P1-01 has no structure-count seam.**
`OrganisationService` exposes `current()` and two writes. Every count on the
Organisation screens is an inline query in the controller that renders it.
*Options:* **(a)** P1-01 gains a small read projection, owned by P1-01, which
P1-11 calls; **(b)** readiness asks only *"does an organisation profile
exist"*, which `current()` already answers, and no count is shown; **(c)**
P1-11 writes the counts itself. **(c) is the one to refuse** — it puts the
definition of *"how many teams"* in two places.
**RESOLVED — D-179: (b).** Use `current()`. **No structure-count projection in
Release 1**, and no structure counts on the screen. If counts become a
requirement later, **P1-01 owns that projection.**

**PLAN ISSUE 2 — P1-03 has no read seam at all.**
Same shape, less ambiguity: there is nothing to call. D-137 permits bounded
administrative counts, so if a Users & Groups count ships, **a seam has to be
built and P1-03 has to own it**.

**RESOLVED — D-180: build it, and P1-03 owns it.** Active users, inactive
users, active groups. Scope applied inside the seam; no person-level row ever
leaves it.

**PLAN ISSUE 3 — P1-04 has no read seam for domains.**
`DomainService` is write-side. *"Enabled domains"* and *"domains with an
accountable owner"* are real readiness facts and there is no class that answers
either.

**RESOLVED — D-181: build it, and P1-04 owns it.** It returns **facts** —
enabled count, unowned-enabled count — and **P1-11 derives the three-way
verdict**, because D-130 made that interpretation this unit's to own.

> **Why this mattered more than it looked.** Three of the four **Readiness**
> tiles in the approved composition (§1.1c) sat on sources with no seam. A
> DESIGN that did not resolve it would either have written three queries into
> P1-11 — the failure §0 exists to prevent — or discovered at EXECUTE that half
> the readiness area could not be built. **It was raised at PLAN, which is the
> point of a PLAN, and decided at PLAN, which is the point of raising it.**

### 1.1c The eight authoritative responsibilities, and the approved composition

**All eight ship — D-134.** Platform Integration Readiness is a **source
feeding** the readiness and operational composition; it **does not delete or
replace any of the eight**.

| # | Responsibility | Source |
| --- | --- | --- |
| 1 | Organisation readiness | P1-01 — **PLAN ISSUE 1** |
| 2 | Users & Groups status | P1-03 — **PLAN ISSUE 2** |
| 3 | Domains readiness | P1-04 — **PLAN ISSUE 3** |
| 4 | Security posture | P1-06 — seam |
| 5 | Open exceptions | P1-06 — seam |
| 6 | Access reviews | P1-07 — seam |
| 7 | System health | P1-09 — seam |
| 8 | Action queue | derived from 1–7 and from P1-10; **no model of its own** — D-135 |

**FINAL composition — approved 21 September 2026**, with each tile's source
named. Nothing on this screen has a source that is not in this table.

| Area | Tile | Source | Shows |
| --- | --- | --- | --- |
| **A. Readiness** | Organisation | `OrganisationService::current()` — D-179 | **Configured / Not configured**, and an authorised link. **No structure counts in Release 1** |
| | Users & Groups | **New P1-03-owned projection** — D-180 | Neutral bounded counts: active users, inactive users, active groups |
| | Business Domains | **New P1-04-owned projection** — D-181 | **Ready / Needs attention / Not configured**; enabled count and unowned-enabled count **only where authorised** |
| | Platform Integrations | P1-10 `SetupProjection` — D-178 | Microsoft Entra ID · Email & Notifications · AI Provider · Microsoft Fabric. **No external test runs** |
| **B. Security** | Security posture | **P1-06, evaluated once** | The aggregate |
| | Open exceptions | **the same P1-06 evaluation** | The count |
| **C. Reviews & Operations** | Access Reviews | P1-07 visibility / overdue seam | Overdue and pending, in the viewer's scope |
| | System Health | P1-09 | **Network-free on this screen** |
| **D. Action Queue** | — | derived from A, B and C | **Authorised links only** |

**B is one evaluation, not two.** Posture and exceptions come from a single
`PostureEvaluator::evaluate()` → `PostureProjection::for()`; `ViewerReport`'s
own docblock already states that `exceptions()` is *"the single source of the
list, the tab count and the heading count"*. Calling it twice would be two
answers to one question.

**C must not contact anybody.** No `semantiq:health`, no Entra discovery, no
integration check, no provider call during render — D-140.

### 1.1d The Action Queue — what it may and may not contain

**Derived authorised links. It owns no task.**

| Condition | Link to |
| --- | --- |
| Organisation not configured | Organisation |
| An enabled domain has no current accountable owner | Business Domains |
| Open security exceptions | Security Status |
| An overdue review | Access Reviews |
| An authorised operational issue | System Health |
| An integration needing attention | Platform Integrations |

**It must NOT gain:** assignment · due date · completion · acknowledgement ·
notifications · an action-queue table.

**Every destination re-authorises on arrival.** A link is a suggestion, not a
grant — and D-145 forbids rendering one the viewer cannot open.

> **`0 groups` is not an action.** D-180 says so explicitly. An action queue
> that fires on an empty count is an action queue people learn to ignore, and
> then the one row that mattered is ignored with it.

### 1.2 What does NOT exist, and must not be assumed

**There is no readiness model anywhere in the codebase.** A search for
`isComplete`, `readiness` or `complete()` across `app/Modules/Organisation`
returns **nothing**. The blueprint's word *"readiness"* has never been defined
as a computable thing in this project.

**This was the single largest open question in the unit. D-130 answered
it** — readiness is derived current configuration completeness over facts other
units already hold, with no score, no percentage, no persistence and no AI.

> **What is left is not the definition; it is three missing seams.** P1-10
> delivers real per-integration configuration state, so that quarter of
> readiness has a source. The other three — organisation structure, Users &
> Groups, Business Domains — are facts that **exist in tables and have no class
> that reads them**. §1.1b.
>
> **D-130 being approved makes this sharper, not softer.** "Derived
> completeness over facts that already exist" is only buildable where a seam
> exists to derive it from.

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

**It is the D-11 confirmation state**, approved in P1-00, and its docblock
already names *"P1-10"* because it was written before the renumbering — it
means **this unit**.

**D-131 decided it: Administration Home has its own route and `/console`
remains separate.** That is not a formality. `/console` is where sign-in lands
and is reachable by **anyone with a session**, including a person with no role
at all. Administration Home is `OrgAdmin` — D-132 — so the two cannot be the
same page without one of them being wrong about who may see it.

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

| Fact | Consequence for P1-11 |
| --- | --- |
| `QUEUE_CONNECTION=sync`, no worker, no scheduler | **Nothing can be precomputed on a timetable.** Every tile is derived at render or it does not exist |
| `CACHE_STORE=file` | A cached roll-up is possible but is a **decision** (D-141), not a default |
| **`SESSION_DRIVER=file`** in production against a `database` target | **Carried Phase 1 finding.** P1-11 **must not** resolve, restate or quietly absorb it. A tile may project P1-09's row; that is not a disposition |
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
- **No change to `/up`, `semantiq:health` or any carried item.** **D-19 is
  narrowly superseded for ONE policy key by D-182** — `administration.view` is
  visible to System Administrator **and** Organisation Administrator. It is
  unchanged for every other System Administration node.

---

## 6. Decisions D-130 to D-147 — **PRODUCT OWNER APPROVED**

> **These are rulings, not questions.** They were approved when this document
> was reviewed as PR #127 and they are **not to be presented as OPEN again**.
> The recommendations that preceded them are gone; what each one decided is
> below.

### The one that shapes the unit

**D-130 — Readiness.** **APPROVED.** Readiness **is** in scope and **is**
Phase 1. It is **derived current configuration completeness** over facts other
units already hold. There is **no score, no percentage, no persistence and no
AI** anywhere in it.

> This is the decision that makes §0's correction necessary. A derived
> interpretation somebody approved is a thing this unit owns — and it is the
> only thing it owns.

### Scope and placement

**D-131 — Route.** **APPROVED.** Administration Home has **its own route**.
`/console` **remains separate** and keeps the D-11 confirmation state, so a
person with no role still lands somewhere honest.

**D-132 — Who may reach it.** **APPROVED.** **`ActionClass::OrgAdmin`.**

**D-133 — Organisation requirement.** **APPROVED.** **No
`RequireOrganisation`.** A null organisation **never broadens scope** — it
narrows what is valued, exactly as Security Status does.

**D-134 — Contents.** **APPROVED.** **All eight** authoritative contents ship.
§1.1c.

**D-135 — Action queue.** **APPROVED.** **Derived authorised links only.** No
task-management model: no assignment, no state, no completion.

### Content boundaries

**D-136 — Recent activity.** **APPROVED.** **No recent-activity or Audit-feed
tile.**

**D-137 — Counts.** **APPROVED.** **Bounded administrative counts are
allowed.** **No business-domain record counts.**

**D-138 — Colour on counts.** **APPROVED.** Neutral counts carry **no
success or failure colour**.

**D-139 — Three distinct states.** **APPROVED.** **`0`**, **`Withheld`** and
**`Not available` / `Not checked`** remain **distinct** and must never collapse
into one another.

**D-140 — Render budget and failure.** **APPROVED.** **Zero external network
calls on render.** Bounded projections. A failing source is **isolated** — it
says so rather than rendering a zero. **Target ≤ 2 s** normal production
response.

**D-141 — Caching.** **APPROVED.** **No P1-11 cache.**

### The rest

**D-142 — Navigation.** **APPROVED.** Administration Home becomes a sidebar
**`leaf`** and **remains first** in System Administration.

**D-143 — Fully withheld viewer.** **APPROVED.** The authorised shell
**renders safely** even when every value is withheld.

**D-144 — Empty deployment.** **APPROVED.** A genuine empty or unconfigured
deployment gets **clear setup guidance**.

**D-145 — Dead links.** **APPROVED.** **Never render an unauthorised or dead
destination link.**

**D-146 — Product Owner test script.** **APPROVED.** **Maximum eight checks**,
using **genuine normal production data**.

**D-147 — What P1-11 acceptance closes.** **APPROVED.** P1-11 acceptance
closes **P1-11 only — not Phase 1**. §7 and `PHASE-1-PLAN.md` §7.

---

## 6a. D-179 to D-181 — the three source seams, **PRODUCT OWNER APPROVED**

**21 September 2026. All three PLAN issues are RESOLVED.** Nothing in this
document is now waiting on a decision.

**The rule these three share, and it is the important part.** Two of them add a
read projection; **neither transfers ownership to P1-11.** The code lives in the
module that owns the fact. P1-11 consumes a contract. The purpose is that the
next screen — and Phase 2 — reuse the same source facts rather than copying
P1-11's queries out of a dashboard.

| | Owns the fact | Owns the query |
| --- | --- | --- |
| People summary facts | **P1-03 / People** | **P1-03 / People** |
| Domain summary facts | **P1-04 / Domains** | **P1-04 / Domains** |
| Organisation existence | **P1-01 / Organisation** | **P1-01 / Organisation** |
| The composition of all of them | **P1-11** | — |

---

### D-179 — Organisation readiness — **APPROVED. PLAN ISSUE 1 RESOLVED.**

**Use the existing `OrganisationService::current()` seam. Add no
structure-count projection for Release 1.**

Administration Home answers exactly one question:

| Condition | State |
| --- | --- |
| No active or current Organisation | **Not configured** |
| An active or current Organisation exists | **Configured** |

**Legal Entities, Business Units, Departments and Teams are NOT mandatory for a
complete Organisation state**, and **their counts are not shown on
Administration Home in Release 1**. They remain visible and manageable on the
Organisation feature itself.

> **This resolves the issue without creating a seam purely to decorate a
> dashboard.** The seam already exists and already answers the question that was
> actually being asked. If structure counts become a requirement later, **P1-01
> must own that read projection** — not P1-11, and not a query copied into a
> tile.

---

### D-180 — Users & Groups status — **APPROVED. PLAN ISSUE 2 RESOLVED.**

**Add a small read projection owned by P1-03 / People. P1-11 must NOT query
People tables directly.**

The class name is a **DESIGN decision**. The contract exposes only **bounded
administrative facts**, at minimum:

- active users;
- inactive users;
- active groups.

**Requirements, all of them binding:**

| | |
| --- | --- |
| Ownership | The **source module owns the queries** |
| Scope | Organisation and viewer scope applied **inside the source-owned seam**, not by the caller |
| Null organisation | **Never becomes "all organisations"** |
| Contents | **No names, emails, memberships or person-level rows.** No business payload |
| Storage | **No new table. No cached snapshot** |
| Access | **No duplicated access logic** |

**Users & Groups is factual status, not a readiness score.** Neutral counts
carry neutral presentation — D-138. **`0 users`, `0 groups`, `Withheld` and
`Not available` remain four different states** — D-139.

> **Do not create an Action Queue item merely because a group count is zero.** A
> deployment with no groups is not a deployment with a problem, and an action
> queue that says otherwise teaches people to ignore it.

---

### D-181 — Business Domain readiness — **APPROVED. PLAN ISSUE 3 RESOLVED.**

**Add a small read projection owned by P1-04 / Domains. P1-11 must NOT query
Business Domain or ownership tables directly.**

**The seam exposes FACTS. P1-11 derives the interpretation.** At minimum:

- enabled domain count;
- enabled domains **without a current accountable owner** count.

P1-11 derives, and **this derivation lives in P1-11 because D-130 made the
interpretation a Product Owner decision this unit owns**:

```text
enabled domains = 0
    → Not configured

enabled domains > 0
AND one or more enabled domains lack a current accountable owner
    → Needs attention

enabled domains > 0
AND every enabled domain has a current accountable owner
    → Ready
```

> **Facts in the source, interpretation in the consumer.** Putting the
> three-way verdict inside P1-04 would make P1-04 own a judgement about its own
> data that only Administration Home asked for — and the next screen that wants
> the same facts with a different reading would have to work around it.

**Requirements:**

| | |
| --- | --- |
| Ownership semantics | **Reuse P1-04's accepted ownership model.** No second definition of *"current accountable owner"* |
| Access | **Domain ownership continues to grant zero access by itself.** Do **not** couple this projection to entitlements |
| Source | The **source module owns the read** |
| Storage | **No schema. No cache** |
| Contents | **No business records** |

---

### The architecture guards these three require

**DESIGN must specify, and EXECUTE must add, tests proving Administration Home
does not directly query:**

- `User`;
- `Group`;
- `BusinessDomain`;
- domain ownership tables;
- Organisation structure tables — `LegalEntity`, `BusinessUnit`, `Department`,
  `Team`.

> **A rule stated in a docblock is a rule that lasts until the next person is in
> a hurry.** P1-10 proved it twice: a guard that had been invalidated by a route
> nobody re-read, and a projection that asked the wrong authority for two
> rounds. These guards are the reason the seams stay seams.

---

## 7. Carried items P1-11 must NOT resolve

> **RETITLED 21 September 2026.** This section said *"P1-10 must NOT resolve"*,
> written when this unit was P1-10. Every occurrence of "P1-10" in it meant
> **this** unit. It now names the unit it governs.

**Nine items. None of them is P1-11's to close, absorb or restate.** Several
would be tempting, because a roll-up is exactly where somebody would think to
"just show" a gate's state and then quietly treat showing it as settling it.

| Item | Status |
| --- | --- |
| **P1-02** provider-wide SSO re-check with a genuine **second permanent** System Administrator | **OPEN / CARRIED / UNVERIFIED.** **Do not create one** |
| A real completed **Microsoft privileged configuration step-up** observation | **OPEN / CARRIED** |
| Bootstrap **First-Run** on a genuine fresh or test installation | **OPEN / CARRIED** |
| Bootstrap **30-minute idle** live observation | **OPEN / CARRIED** |
| Bootstrap **4-hour absolute** live observation | **OPEN / CARRIED** |
| **Recovery flow** live observation | **OPEN / CARRIED** |
| **Real SMTP send test**, when genuine SMTP becomes available | **OPEN / CARRIED** |
| Production **session-driver alignment**, `file` → `database` | **OPEN / CARRIED. NOT P1-11's to fix**, and not to be attempted inside it — it terminates every existing session and is a controlled deployment correction with its own gate |
| Privilege-change / **per-user session revocation** | **OPEN / PHASE 1 GATE.** The control does not exist; nothing reads `sessions.user_id` |
| **D-19** | **NARROWLY SUPERSEDED by D-182, and otherwise unchanged.** *"D-19 remains in force for System Administration navigation generally, except that D-182 explicitly makes `Administration Home` visible to Organisation Administrator because the route itself is `OrgAdmin` and the screen is their authorised administration landing point."* The other four `OrgAdmin` System Administration screens stay hidden from an Organisation Administrator and **remain a carried navigation item** |

**A tile may SHOW a carried gate's state. Showing it closes nothing.** If
Administration Home ever renders something that reads like *"session storage:
healthy"*, that is a projection of P1-09's row and not a disposition of the
carried item.

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

## 8a. What the DESIGN must carry forward

**Stated here so the DESIGN is checked against the PLAN rather than against
memory.**

| | |
| --- | --- |
| **Route and access** | Its own route. `ActionClass::OrgAdmin`. **No `RequireOrganisation`** — D-131, D-132, D-133 |
| **Null organisation** | **Always fails narrow.** Never *"all organisations"*, never a global People or Domain count, never a silently invented global-data privilege for a System Administrator. `Withheld`, `Not configured` or another already-approved honest state, **per the source contract** |
| **Both administrator kinds** | The DESIGN documents **System Administrator and Organisation Administrator behaviour explicitly**, not by implication |
| **Performance — D-140** | Zero external network calls · **no P1-11 cache** · bounded aggregates · **no N+1** · one source failing does not fail the page · **a failed source is never a zero** · target ≤ 2 s |
| **Query budget** | The DESIGN states one, and states how a source consumed by several tiles is **evaluated once** — P1-06 for posture *and* exceptions; P1-10 preferably one `all()` rather than four traversals |
| **Navigation** | First in System Administration · `locked` → **`leaf`** · its own route · **`/console` unchanged** · **D-182: `administration.view` visible to System Administrator AND Organisation Administrator, D-19 otherwise unchanged** · no other node exposed · no sidebar reorder |
| **Visual language** | **A dashboard, not another configuration form.** The existing shared shell, typography, spacing, cards, badges, light and dark tokens, responsive behaviour and focus treatment. **No new CSS where the shared UI already supports the requirement.** Tabs are **not** to be forced onto a dashboard |

> **The last row is there because of what Gate D cost.** P1-10 invented its own
> information architecture, passed three Gate C rounds looking only at itself,
> and was held at Gate D the moment it was put beside Organisation. **This unit
> must not discover the same inconsistency at the same gate.**

---

## 9. Status

**PLAN APPROVED — 21 September 2026. NEXT STAGE: DESIGN.**

**Blocker: NONE.** P1-10 — Platform Integrations & Setup is **CLOSED /
ACCEPTED**, 21 September 2026.

**No implementation. No schema. No deployment.**

**D-130 – D-147 and D-179 – D-181 are all Product Owner APPROVED** — §6 and
§6a. **All three PLAN issues are RESOLVED.** Nothing in this document is open.

**Every carried item in §7 remains open**: the P1-02 second-permanent-
administrator re-check, the Microsoft step-up round trip, First-Run on a fresh
installation, the 30-minute and 4-hour Bootstrap expiries, the recovery flow,
the real SMTP send, the production session-driver alignment and per-user
session revocation. **D-19 is unchanged except for the one narrow D-182
exception above.**

**P1-11 acceptance will close P1-11 only.** Phase 1 acceptance additionally
requires explicit disposition of the phase-level gates — `PHASE-1-PLAN.md` §7
and §10.
