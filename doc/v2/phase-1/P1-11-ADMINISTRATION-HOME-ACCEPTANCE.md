# P1-11 — Administration Home: Product Owner acceptance

**P1-11 PRODUCT OWNER ACCEPTED — GATE D CLOSED. P1-11 CLOSED.**
22 September 2026.

| | |
| --- | --- |
| Unit | **P1-11 — Administration Home** (delivery order 13). **The final Phase 1 delivery unit** |
| Schema | **NONE.** No table, no column, no migration. The count stayed at 35 |
| Writes | **NONE.** No write path, no cache, no new `SecurityEventLogger` key — the catalogue stayed at 15 |
| Production identity authority | **`env` — unchanged. No Entra cutover was performed** |
| Product Owner Gate D result | **8 / 8 PASS**, observed live on production |
| Carried items | **Eleven, all still OPEN** — §7 |

> **P1-11 CLOSED IS NOT PHASE 1 CLOSED.** P1-11 was the last unit to build.
> Phase 1 closeout is a separate gate and has not begun — §8.

---

## 1. The delivery, end to end

| Stage | Reference |
| --- | --- |
| **PLAN** | `b98bba495fba14ef9577002743e0784520500b1c` (#137) — D-179, D-180, D-181 |
| **DESIGN** | `59a3f739bd66b26dea7f7e61ba0e8cc603960881` (#138) — Gate B, D-182 and three corrections |
| **Gate C approved at** | **`8f69569c81d35eea61f3e40fa0c4949d67655b79`** — PR #139 head |
| **Implementation merge SHA** | **`59cacedbc0e4fd099a1e0ad51cc2a963be68f7b1`** (#139), squashed to `main` |
| **Deployment run** | **35587621169** — *Deploy to cPanel (SSH)*, **SUCCESS**, 29/29 steps |
| **Exact deployed build** | **`59caced`** — verified by asset hash, §4.1 |

**Gate B took one round, Gate C took two, Gate D took one.** Recorded rather
than smoothed over:

| Held at | For |
| --- | --- |
| Gate B | D-182 — a home screen that was authorised but undiscoverable — plus three DESIGN corrections: the projection class names, the internally inconsistent empty-deployment state, and authorising **before** evaluating a platform-only source |
| Gate C round 1 | One evidence gap — the source-failure isolation matrix covered five sources and not `ReviewSummaryProjection` |
| **Gate D** | **Nothing. Passed first time, 8 / 8** |

---

## 2. What the Product Owner reviewed, and accepted

> **THIS SECTION IS PRODUCT OWNER LIVE PRODUCTION OBSERVATION.** It is not
> automated evidence, not a local browser run, and not inferred from a passing
> test. The distinction matters in both directions: §5 records what CI and the
> local suite proved, and it is a different and weaker claim about the same
> screen. **Neither is presented as the other.**

**Live production Gate D review, 22 September 2026. CHECKS 1–8 — PASS.**
The Product Owner supplied the production Administration Home screenshot as
Gate D evidence.

| # | Check | Result |
| --- | --- | --- |
| **1** | It looks like the rest of SemantIQ | **PASS** |
| **2** | Readiness tells the truth about **their** deployment | **PASS** |
| **3** | The dashboard agrees with the screens it summarises | **PASS** |
| **4** | The Action Queue is real | **PASS** |
| **5** | Nothing is invented | **PASS** |
| **6** | An Organisation Administrator sees the right, smaller screen | **PASS** |
| **7** | Responsive, both themes, keyboard | **PASS** |
| **8** | Nothing else moved | **PASS** |

**Check 3 was named in the DESIGN as the most important**, and it is the one a
summary screen is most likely to fail: a roll-up that disagrees with its source
is worse than no roll-up. It passed against the live screens.

### 2.1 The live observations this closes

Every item that was carried into Gate D because it could not be observed from
the delivery environment is now **CLOSED by Product Owner observation**:

| Was outstanding at Gate D | Now |
| --- | --- |
| **Administration Home rendered on production** | **OBSERVED.** Screenshot supplied as evidence |
| **PO-R2 live** — an Organisation Administrator sees exactly `Administration Home` | **OBSERVED — Check 6 PASS** |
| **PO-R3 live** — System Health shows the two neutral counts and no invented overall verdict | **OBSERVED — Checks 3 and 5 PASS** |
| **Source-screen agreement** | **OBSERVED — Check 3 PASS** |
| **Action Queue behaviour** | **OBSERVED — Check 4 PASS** |
| **Responsive, theme and keyboard** | **OBSERVED — Check 7 PASS** |
| **Regression — nothing else moved** | **OBSERVED — Check 8 PASS** |

**The delivery team's browser evidence remains what it always was** — a local
server, because this environment's browser does not trust the inspecting proxy's
certificate authority and TLS verification was never disabled to work around it.
**The Product Owner's review is the live observation.**

---

## 3. The rulings that are now current authority

**PO-R1, PO-R2 and PO-R3 survive acceptance and are not reopened by it.**

| | Ruling | Standing |
| --- | --- | --- |
| **PO-R1** | The **Access Reviews read seam** is APPROVED. `ReviewSummaryProjection` is kept, **owned by P1-07 / Reviews**, and is **not** to be moved into Administration. `ReviewerAuthority::scopeVisible()` remains the authority for review visibility. **P1-11 must not directly query or reference `AccessReviewItem`** — guard G3 stands | **IN FORCE.** DESIGN §4.6's scoped-`AccessReviewItem`-builder wording is **SUPERSEDED**, with the original preserved historically |
| **PO-R2** | An Organisation Administrator's System Administration navigation is **exactly** `['Administration Home']`. No other node is exposed as part of P1-11 | **IN FORCE.** It required no implementation change, and none was made. Held by an **equality** assertion, so widening it later must be a decision rather than a side effect. **Observed live — Check 6 PASS** |
| **PO-R3** | System Health shows the two neutral counts — **Needing attention** and **Not checked yet**. **No single aggregate System Health status is to be created.** P1-11 derives only neutral counts from P1-09 rows | **IN FORCE.** DESIGN §4.7's *"collapsed to one overall state"* is **SUPERSEDED**, with the original preserved historically. **P1-09 was not changed.** Observed live |

---

## 4. The production facts at acceptance

Read from the live system, not inferred.

| Fact | Value |
| --- | --- |
| `GET /up` | **200**, body `ok` |
| **Deployed build** | **`59caced`** |
| **Deploy run** | **35587621169 — SUCCESS**, 29/29 steps, 2 m 12 s |
| `GET /console/administration` | **302 → sign-in** — present, and failing closed for an unauthenticated visitor |
| A route that does **not** exist | **404** — so the 302 above distinguishes *exists* from *absent* |
| `/console` | **302 → sign-in**, unchanged |
| All fourteen accepted console routes | **302**. None 404, none 500 |
| **Migrations applied by this deployment** | **`INFO  Nothing to migrate.`** — quoted from the deployment log |
| **Schema** | **35 migrations, unchanged.** P1-11 added none |
| Effective identity authority | **`env`** |
| **Entra cutover** | **NOT PERFORMED** |
| `SESSION_DRIVER` | **`file`** — unchanged, see §7 |
| **D-31 `SESSION_LIFETIME` step** | `SESSION_LIFETIME already matches the approved policy. Leaving .env untouched.` — quoted from the log. **Standard approved deployment behaviour, NOT a P1-11 change** |
| Audit key catalogue | **15**, unchanged |

### 4.1 The deployed build was verified, not assumed

**A deployment reporting success is not the same claim as the right build being
live.** Vite asset names are content hashes, so the deployed SHA was established
independently of the workflow's own report:

| | |
| --- | --- |
| Production references and serves | `app-BppKLV8o.js`, `app-D8VIisje.css` — both **200** |
| Building **`59caced`** produces | `app-BppKLV8o.js`, `app-D8VIisje.css` |
| Building the **previous** `main` (`59a3f73`) produces | `app-D4pobQzZ.js`, `app-KvceA1Ro.css` |

**The previous release's hashes are different**, so the check discriminates: had
the deployment not landed, production would still be serving `app-D4pobQzZ.js`.

---

## 5. CI, MySQL and automated evidence

> **This section is automated and local evidence. It is a different and weaker
> claim than §2**, and is recorded separately for exactly that reason.

| | |
| --- | --- |
| **CI on the Gate C head** | Run **35586681413 — SUCCESS** on `8f69569` |
| Full suite | **1246 tests · 1240 passed · 6 skipped · 1 risky · 91,251 assertions** |
| Targeted Administration suite | **45 tests · 45 passed · 542 assertions** |
| Pint | **PASSED** — the repository's only formatting gate |
| **MySQL** | Every module suite green, including **"Run the Administration suite against MySQL"**. **Not run locally** — no MySQL server exists in the delivery environment |
| Mutations | **39 runs · 36 killed first time · 3 survived and were closed**, each recorded with what closed it |

**The MySQL step earned its place on its first run.** It hung for twenty-two
minutes while every other suite in the same job finished in under one. The cause
was in the unit's own tests — `refreshApplication()` and `migrate:fresh` called
mid-test, harmless on SQLite and a metadata-lock deadlock on MySQL — and the
local suite was green throughout. It is the third engine-specific failure
invisible on SQLite, after P1-08 and P1-09.

**Measured, not asserted:** 113 queries and 0.49–0.76 s for a System
Administrator; **75 and 0.46–0.59 s** for an Organisation Administrator, who
pays 38 fewer because two platform-only sources are never evaluated for them.
Inside D-140's two seconds. The count is identical at two data sizes, so this
unit's own composition has no N+1.

---

## 6. What this unit delivered

1. **One screen, one route, no schema.** `GET /console/administration` composes
   eight sources and owns none of their facts.
2. **Three read seams, each owned by the module that owns the fact** —
   `PeopleSummaryProjection` (D-180), `DomainSummaryProjection` (D-181, reusing
   `currentOwnership()` so the dashboard and the feature cannot disagree about
   who owns a domain), and `ReviewSummaryProjection` (PO-R1).
3. **D-182** — one explicit navigation exception and one only, so an
   Organisation Administrator can reach the screen they are authorised for
   without any other node being exposed.
4. **Authorise before evaluating a platform-only source.** For a viewer who may
   not receive the value, `SetupProjection` and `SystemHealthReport` are **not
   constructed, let alone called** — measured at **0 and 0**, against **1 and 1**
   for a System Administrator.
5. **A missing scope narrows, and a failed source is never a zero.** Each of six
   sources is forced to throw in turn: its own tile reads *Not available*,
   carries no number and no badge, contributes no Action Queue row, and takes
   nothing else down.

**Three findings were raised rather than absorbed**, and all three became
Product Owner rulings — §3. **Two polish defects were found by looking at the
screen rather than by any test**: a tile headed *"Open exceptions"* carrying a
link reading *"Open exceptions"*, and an Action Queue row naming a destination
it did not land on.

---

## 7. Carried items — **P1-11 closure closes none of these**

**Not one was manufactured to close a checklist.** Each stays open until it can
be observed honestly.

| # | Item | Status |
| --- | --- | --- |
| 1 | P1-02 provider-wide SSO re-check with a genuine **second permanent** System Administrator | **OPEN / CARRIED / UNVERIFIED** |
| 2 | A real completed Microsoft privileged configuration **step-up** observation | **OPEN / CARRIED** |
| 3 | Bootstrap **First-Run** on a genuine fresh or test installation | **OPEN / CARRIED** |
| 4 | Bootstrap **30-minute idle** timeout, live | **OPEN / CARRIED** |
| 5 | Bootstrap **4-hour absolute** timeout, live | **OPEN / CARRIED** |
| 6 | **Recovery flow**, live | **OPEN / CARRIED** |
| 7 | **Real SMTP send test**, when genuine SMTP becomes available | **OPEN / CARRIED** |
| 8 | Production **session-driver alignment**, `file` → `database` | **OPEN / CARRIED** |
| 9 | Privilege-change / **per-user session revocation** | **OPEN / PHASE 1 GATE** |
| 10 | The four System Administration screens an Organisation Administrator can **reach by URL and still cannot see** | **OPEN / CARRIED** — raised by P1-11 |
| 11 | **P1-06's per-domain evaluation cost** | **OPEN / CARRIED as a performance finding** — see below |

**None of the eleven was executed by this unit or by its deployment.**

**Row 8 is not row 11's neighbour by accident, and must not be confused with the
D-31 step in §4.** D-31 governs *how long* an idle session lives and is already
approved; row 8 governs *where* sessions are stored, and is untouched.

**Row 9 remains a statement about what is NOT built.** Nothing in the
application reads `sessions.user_id`.

**Row 11 is a performance finding, not a deployment defect and not a P1-11
blocker.** `PostureEvaluator`'s `DomainAdapter` issues five aggregates per
business domain; at twenty domains `/console/security` costs 189 queries and
`/console/administration` 236. **Security Status already pays most of it and has
since P1-06.** P1-11 evaluates posture **once** for two tiles and adds nothing to
the per-domain cost. **It was carried forward, not fixed here** — re-engineering
an accepted unit's evaluator was never this unit's to do.

**A tile may DISPLAY a carried gate's state. Display closes nothing.**

---

## 8. What P1-11 closure does and does not unlock

**Does:** it closes the last Phase 1 **delivery unit**. All thirteen units are
now Product Owner accepted.

**Does not:** Phase 1. **Final Phase 1 acceptance additionally requires explicit
disposition — a decision recorded, not an omission — of every row in §7**, and
four of them could never be closed by building anything in P1-11: the session
driver, per-user session revocation, the second-administrator re-check, and the
P1-10 live-observation rows.

**Phase 1 closeout has not begun. Phase 2 has not begun.** Neither is authorised
by this acceptance, and **no row above may be moved silently into Phase 2.**
