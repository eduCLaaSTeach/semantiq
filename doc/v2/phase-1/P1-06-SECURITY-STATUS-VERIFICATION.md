# P1-06 — Security Status: verification record

**What was executed and observed.** A passing automated test is not the same
claim as an observed production result, and nothing below presents one as the
other.

| | |
| --- | --- |
| Unit | **P1-06 — Security Status** |
| PLAN | merge `4d93228c1aa2a603f28db1564a6b6304c4b97ba8` (D-75 – D-81) |
| DESIGN | merge `0559315e632d04c071727bc5c8176a2dba475999` (D-82, D-83) |
| Implementation | merge `a798ded381c197279522feffa8f70e7542391649` (Gate C approved 17 September 2026), deploy run 127 |
| G2 correction | merge `f282571f9dbb24cb1d49c8d8249d350c18b3763f`, deploy run 129, 18 September 2026 |
| Deployment record | merge `cbc2161a423c397dfe23f8b207a960e241dacef6` (documentation only) |
| **Status** | **P1-06 PRODUCT OWNER ACCEPTED — 18 September 2026. Gate D CLOSED. P1-06 CLOSED.** |

---

## 0. Acceptance

**P1-06 — SECURITY STATUS — PRODUCT OWNER ACCEPTED, 18 September 2026.**

**Gate D CLOSED. P1-06 CLOSED.**

The Product Owner tested the deployed build and recorded a result for every
case. Recorded on their confirmation, which is what acceptance is.

| Section | Result |
| --- | --- |
| **A — Reaching the screens** | PASS |
| **B — Secure Baseline** | PASS |
| **C — Privileged Access Health** | PASS |
| **D — Exceptions** | PASS |
| **E — Security Events** | PASS |
| **F — Negative, refusal and security cases** | PASS |
| G1 — readable in both themes | PASS |
| **G2 — phone width** | **PASS** — retested against the deployed correction |
| **G2a — every mobile tab opens its screen** | **PASS** — all four confirmed |
| G3 — browser Back | PASS |
| G4 — a word *and* a mark, never colour alone | PASS |
| G5 — no developer terminology | PASS |
| G6 — no security score or percentage | PASS |

### The mobile tab-clipping defect is closed

G2 failed on first testing: at phone width the shared tab strip left one tab
clipped at the screen edge and two wholly outside the viewport, with no
affordance that they existed. The cause, the correction and the measurements are
in §11. The Product Owner has now retested the deployed correction at mobile
size and confirmed both that the responsive layout works and — separately, as
G2a — that each of the four tabs opens the correct screen. **The defect is
closed on observation, not on a passing test.**

### What this acceptance does NOT close

| Carried item | Status, unchanged |
| --- | --- |
| **P1-02 provider-wide SSO Re-check** | **OPEN / CARRIED / UNVERIFIED.** No genuine second permanent System Administrator has been manufactured, and none was created to close it |
| **B-9b — Microsoft's acceptance of the fresh step-up return** | **Unchanged**, exactly as previously recorded: permanently `unverified` in Release 1, with no accepted runtime evidence source |
| **P1-04 disabled-domain gate** | **CLOSED** (in P1-05). Unchanged |

> **Raised here, decided and corrected.** At acceptance, the carried-gate
> register in `PHASE-1-PLAN.md` §10 still named **P1-05** as the destination for
> the P1-02 Re-check gate, although P1-05 and P1-06 were both closed and the
> gate was still open — so no unit owned it. That was raised rather than fixed
> under this acceptance, because the acceptance is scoped to the P1-06 documents.
>
> **The Product Owner decided it on 18 September 2026**, and `PHASE-1-PLAN.md`
> §10 now carries that decision: the gate is reassigned from P1-05 to **Phase 1
> orchestration**, to be executed at the earliest point a genuine second
> **permanent** System Administrator exists in normal operation. **P1-07 does not
> own it**, and it does not block P1-07 from starting. It is revisited before
> final Phase 1 acceptance and **never silently marked PASS, CLOSED or Healthy**.
>
> Read `PHASE-1-PLAN.md` §10 for the authoritative status. This note is not it.

---

## 1. What was built

| Area | Files |
| --- | --- |
| Posture model | `PostureState`, `ControlKind`, `ControlScope`, `ExceptionKind`, `Evidence`, `PostureRow`, `MetricRow`, `DomainPosture`, `PostureReport`, `Aggregation`, `PostureEvaluator` |
| Catalogue | `Control`, `ControlCatalogue` (13 baseline + 11 privileged + the carried gate), `EventCatalogue` (71 keys → 10 categories) |
| Adapters | `Identity`, `Session`, `RouteCoverage`, `EngineGate`, `Administrator`, `StepUp`, `GrantPath`, `Hosting`, `Domain`, `CarriedGate` |
| Projection | `Viewer`, `ViewerRow`, `WithheldRow`, `ViewerMetric`, `ViewerDomain`, `ViewerReport`, `PostureProjection` |
| HTTP | 4 controllers, `RendersPosture`, 4 GET routes |
| Screens | `Baseline`, `PrivilegedAccess`, `Exceptions`, `Events`, plus `SecurityPage`, `SecurityTabs`, `PostureBadge`, `PostureRows`, `PostureMetrics` |

**No table. No column. No migration. No new security event. No new
`ALLOWED_KEYS` entry.** Outside the new module the only edits are the route
group, the `ApprovedMenu` node moving from `locked()` to `leaf()`, and the
shared stylesheet.

## 2. Tests

| Suite | Tests | Assertions |
| --- | ---: | ---: |
| `tests/Unit/Security` | 21 | 73 |
| `tests/Feature/Security` | 62 | 49,647 |
| `SecurityStatusArchitectureTest` | 14 | 2,146 |
| **P1-06 total** | **97** | **51,866** |
| **Whole project** | **767** | **67,622** |

**Executed:** whole suite green — 763 passed, 4 skipped (the pre-existing
SQLite-only skips in the Access concurrency tests, which run on MySQL in CI).

All **43** PLAN cases (N-SS1 – N-SS37 plus N-SS3a/3b/3c, 8a, 12a, 12b) are
placed; the traceability table is in the DESIGN §13.1 and none is orphaned.

## 3. Mutations

**43 mutations applied and observed.** Full record in `P1-06-MUTATIONS.md`.

- **36 caught on first run.**
- **6 survived and exposed real gaps in the suite**, each closed by a new test
  and then re-run and caught: encryption reclassified out of scope (M-B1), the
  external step-up half inferred from the local one (M-B2), the inactive-gate
  reason check (M-F3), route-coverage red branch (M-F6, M-F7), domain rows
  contributing to the aggregate (M-G6).
- **1 was invalid** (M-F1, a no-op) and was redone correctly as M-F1b, which was
  caught.

> **M-B2 is the one to read.** Inferring Microsoft's acceptance of the step-up
> return from SemantIQ's own configuration is the failure **P1-05 hit in
> production**, and the suite as first written did not catch it.

## 3a. Gate C blocker — fixed

**PR-3 and the per-domain privileged row counted an inactive administrator.** A
preserved assignment on a deactivated account produced *"an administrator also
holds business access"* about somebody the access engine refuses at the global
gate. Fixed in both call sites; see `P1-06-MUTATIONS.md` §7a.

**Observed live on the rendered screen**, cycling one privileged account through
all three states while a System Administrator watched:

| State | PR-3 | Finance domain | PR-5 (informational) |
| --- | --- | --- | ---: |
| Active | Needs attention | Needs attention | 0 |
| **Deactivated** | **Healthy** | **Healthy** | **1** |
| Reactivated | Needs attention | Needs attention | 0 |

**Nothing was re-granted between the second and third rows.** The assignment and
the entitlement are untouched throughout — the row reads the account's status,
it does not end the grant.

## 4. Two defects the guards found in code already written

| Defect | Found by |
| --- | --- |
| `IdentityAdapter` held its **own copy of the precedence order** — a second implementation of the rule this unit is built around | The architecture guard against a second evaluator, once narrowed correctly |
| The route-verb guard read a fixed character window and **reported a P1-02 POST as a Security Status violation** — a false red | The guard failing on correct code |

Both are recorded in `P1-06-MUTATIONS.md` §8.

## 5. MySQL

A **Security suite step against MySQL 8.4** was added to `ci.yml`, beside the
existing People, Domains and Access steps. It asserts a minimum test count (a
path matching nothing exits 0) and fails on any skip.

> **Why it is needed:** posture is computed from aggregate queries, several of
> them through `whereHas` and `whereDoesntHave`. The cross-contamination fixture
> depends on correlated subqueries resolving per domain — exactly the shape that
> silently returns the wrong rows when an engine plans it differently.

**EXECUTED AND OBSERVED.** CI run
[35067226635](https://github.com/eduCLaaSTeach/semantiq/actions/runs/35067226635)
on head `5be93f6973b9176fafa2ccf028f7febc28aade9b`: **every step green**,
including *Run the Security suite against MySQL*, with no skip. Formatting,
the full SQLite suite, the MySQL migration run and the People, Domains, Access
and Security MySQL suites all passed.

## 6. Browser verification — what was actually observed

Chromium, driven by Playwright, against a local build.

**Matrix: 2 roles × 2 viewports × 2 themes × 4 screens = 32 screen loads**, plus
the navigation walk, the tab strip and browser Back in each of the 8
role/viewport/theme combinations.

**Result: 0 findings** on the final run.

Checked on every combination: the heading rendered; the aggregate badge carried
words rather than a code; no rendered text matched a dotted internal identifier;
no horizontal scrolling; no row collapsed to zero height; the tab strip had four
tabs with exactly one marked current; browser Back returned to the previous
screen **and the heading changed with it**; no console errors beyond blocked
Google Fonts requests, which are the sandbox's network policy and not the
product's.

**Observed directly, by reading the screens:**

- Security Status is **clickable in the sidebar** for a System Administrator and
  opens Secure Baseline. (The P1-05 lesson: a capability reachable only by URL
  is one that was not delivered.)
- An Organisation Administrator's Secure Baseline shows **12 withheld rows**,
  each carrying **one** sentence, and **one distinct sentence across all of
  them** — so the wording cannot vary with the value.
- That viewer's badge reads **"Healthy — organisation controls"**, never a bare
  "Healthy", and the caption names the 16 withheld platform controls separately
  from the counts.
- A Business User is sent to `/auth/access-denied` with no posture wording
  anywhere on the page.
- All 71 events render as business language. No dotted identifier appears.

### 6.1 Four defects found by LOOKING, which no test would have caught

| Defect | Fix |
| --- | --- |
| The tab read **"Exceptions(9)"** — JSX collapses a leading space inside an element | An explicit non-breaking separator |
| Every withheld row printed **"Managed by the platform administrator" twice** | The sentence appears once, and a withheld row carries no badge at all |
| The events catalogue used a **CSS grid**, which gives every cell in a row the height of the tallest — one category has 22 entries against another's three, leaving a column of dead space taller than most of the page | Multi-column, so the groups pack |
| At 390px the summary left roughly **300px of blank space** — `flex-basis` follows the main axis, so the caption's `flex: 1 1 320px` became a 320px *height* once the container turned into a column | `flex: 0 1 auto` in the mobile query |

> **There is no JavaScript test runner in this project.** No CI test renders the
> DOM, so none of the four could have been caught automatically. They were found
> by opening the pages and reading them, which is why CLAUDE.md §5 exists.

## 7. The professional-polish gate

Asked and answered honestly for all four screens, in both themes, at both
widths. **Would a professional SaaS product team be comfortable showing these
screens to a customer?** After the four fixes above — yes.

Checked and clean: no raw icon names, enum values, database keys or route names;
no debug or placeholder copy; no developer terminology on any surface; spelling
and grammar; consistent sentence case; no truncation or overflow; empty, refusal
and unverified states all present and worded; colour never the only signal;
navigation discoverable rather than merely present.

## 8. Carried gates

| Gate | Status |
| --- | --- |
| **P1-02 provider-wide SSO Re-check** | **OPEN / CARRIED / UNVERIFIED.** Unchanged. No second System Administrator was manufactured. It is now *visible* for the first time, rendered as an Exception of kind *Verification incomplete* |
| **P1-04 disabled-domain gate** | CLOSED (P1-05). P1-06 reports a disabled domain as a fail-closed success |
| **B-9b — Microsoft's acceptance of the step-up return** | **Newly surfaced, permanently `unverified` in Release 1.** There is no accepted runtime evidence source, and the implementation is a constant with no branch that could report healthy |

## 9. Raised separately — NOT a P1-06 defect, and NOT changed here

> **An Organisation Administrator and an Auditor can reach Security Status, but
> cannot see any sidebar at all.**

`SystemAdministratorNavigationAuthorizer` admits System Administrators and
nobody else, so **no delivered capability** appears in the menu for those two
roles — not Organisation, not Users & Groups, not Roles & Access, not Business
Domains, not Identity & SSO, and not Security Status. That is D-19, a Phase 1
presentation rule accepted before this unit existed.

The consequence for P1-06 specifically is that the two roles the unit was
carefully designed to serve — the ones D-76's "named but not valued" rule
exists for — **can only reach these screens by typing a URL.**

This was observed in the browser during verification. **It has not been changed**,
because changing it means changing an approved product decision that reaches
every unit, which CLAUDE.md §4 says to raise rather than to fix under a quality
gate. **It is offered to the Product Owner as a separate decision.**

## 9a. Production deployment and verification — 17 September 2026

Merged `a798ded381c197279522feffa8f70e7542391649`; deploy run 127 succeeded;
post-merge CI run 256 green.

**"Nothing to migrate."** P1-06 has no migration and none was invented.

Verified against production **without signing in and without changing anything**:

| Check | Result |
| --- | --- |
| All four routes exist | `302` to sign-in, not `404` — deployed and reachable |
| Refusal leaks nothing | No posture wording on any unauthenticated response |
| GET-only | `POST`/`PUT`/`PATCH`/`DELETE` all `405` on all four routes |
| No reveal route | `/console/security/reveal` → `404` |
| No 500 | Every probed path `200`, `302` or `404` |
| Four tabs, no fifth | The deployed bundle carries exactly four `/console/security*` tab hrefs and no Domain Posture tab |
| No raw event key shipped | No dotted identifier in the deployed bundle |
| No secret shipped | No token, PEM or client-secret shape in the deployed bundle |
| Events screen is not a history | *"This is not a history"* present in the deployed bundle |

**Production posture, read-only** (`verify-access` run 9): **1 active System
Administrator**, 0 current entitlements, 0 current scopes, 0 current ceilings, 0
Restricted ceilings, 3 users, 3 enabled domains, `users.platform_role` absent.

So PR-1 reports **Needs attention** (the sole-administrator condition), PR-3
reports **Healthy** (no administrator holds a business entitlement), and **no
second administrator was created**.

### What could NOT be verified from here

**Nobody signed in.** Sign-in is Microsoft Entra SSO, so the four screens were
not rendered as the authenticated System Administrator in production. Section A
of the Product Owner Test Script exists for exactly that, and this record does
not claim it.

## 10. What is NOT claimed

- **No production data was changed and no business record was created.
  Production security/access state was inspected read-only through
  `verify-access` run 9.**

  The distinction matters and the first draft of this line blurred it. Production
  state WAS read — the administrator count, entitlements, scopes, ceilings, users
  and enabled domains in §9a all come from that inspection. What did not happen is
  a change: nothing was written, nothing was created, and no test or business
  record was added to make a screen say something.
- **Nobody signed in through the authenticated production UI** during deployment
  verification, so the four screens were not observed as an administrator sees
  them — see §9a.
- The **MySQL** result above is from CI run 35067226635, observed, not inferred.
- **Visibility, discoverability, theme and responsive behaviour are human
  observations**, recorded as observed. No automated test in this project can
  make those claims.
- The states listed in the Product Owner Test Script §11 **cannot be observed on
  production** without creating false permanent history, and are carried forward
  by name rather than inferred from a passing test.

## 11. Gate D, Section G — Product Owner testing, and the G2 correction

### 11.1 What the Product Owner reported

Sections A–F **PASS** in full. Section G:

| Case | Result |
| --- | --- |
| G1 — readable in both themes | **PASS** |
| G2 — phone width | **FAIL** |
| G3 — browser Back | **PASS** |
| G4 — a word *and* a mark, never colour alone | **PASS** |
| G5 — no developer terminology | **PASS** |
| G6 — no security score or percentage | **PASS** |

The G2 report, in the Product Owner's words: at phone width the main content is
readable, **but the Security Status tab strip is not fully responsive — the
third tab is visibly clipped and the fourth tab is outside the visible
viewport.**

Gate D remains **OPEN**. P1-06 is **not** Product Owner accepted.

### 11.2 Why §6 of this document did not catch it

This is the failure CLAUDE.md §2 names: an assertion that passed for a reason
unrelated to what it claimed to check.

The responsive check in §6 asserted that the **page** did not scroll sideways —
`document.documentElement.scrollWidth > clientWidth`. The shared Pattern B strip
is built to guarantee exactly that and nothing more: it scrolls **itself**
(`overflow-x: auto`, `.org-tabs ul { min-width: max-content }`) and then hides
its scrollbar completely (`scrollbar-width: none`, `::-webkit-scrollbar { height: 0 }`).

So the strip clipped its own content while the page reported no overflow at all.
The check could not have failed on this defect. It reported a responsive safety
the screen did not have.

### 11.3 The defect, measured

Re-measured at 390 × 844 on `/console/security`, this time on each **tab's own
box** rather than on the page. Before the correction:

| Tab | Horizontal extent | Inside the 390px viewport? | Clickable at its centre? |
| --- | --- | --- | --- |
| Secure Baseline | 80 – 231 | yes | yes |
| Privileged Access Health | 233 – 448 | **no — clipped at the edge** | yes |
| Exceptions (11) | 450 – 599 | **no — wholly off-screen** | **no** |
| Security Events | 601 – 749 | **no — wholly off-screen** | **no** |

That reproduces the Product Owner's observation independently.

The same measurement found the strip was clipping on **two other features** that
share it, and had been since they shipped:

| Feature | Clipped at the edge | Wholly off-screen |
| --- | --- | --- |
| Organisation (6 tabs) | Business Units | Departments, Teams, Management Hierarchy |
| Identity & SSO (5 tabs) | Other Identity Providers | Login Experience, SSO Health, Session Policy |
| Users & groups (2 tabs) | none | none — it fits, which is why the pattern survived this long |

On **every** one of those screens the page reported no horizontal overflow, so
the §6 check would have passed on all of them too.

The clipping is a property of the **strip**, not of Security Status's labels. It
is fixed once, in the shared rule.

### 11.4 The correction

One bounded responsive rule in `resources/css/app.css`. Below 640px the strip
**wraps** instead of scrolling:

- `.org-tabs` releases its overflow and its negative top margin;
- `.org-tabs ul` gains `flex-wrap: wrap` and drops `min-width: max-content` —
  both are required, because wrapping declared alone can never take effect while
  the list keeps a single-row minimum;
- `.org-tab` becomes a fully rounded pill, because a wrapped tab has no rule to
  attach to;
- the active tab keeps **fill, border and weight** together, so it is still
  never carried by colour alone.

Nothing else changed. No posture calculation, adapter, route, authorisation
rule, catalogue entry, schema, event, navigation rule or React component was
touched, and the desktop strip is byte-for-byte the same design.

### 11.5 Browser verification — what was actually observed

Chromium at `/opt/pw-browsers/`, signed in as the System Administrator, both
themes. Every assertion is made on the tab, not on the page: its own box, its
own label, and a hit test at its own centre.

| Requirement | Observed |
| --- | --- |
| All four tabs accessible and fully readable at 390px | Yes — four rows, each tab 44px tall, no label clipped inside its own box, every tab hit-tests to itself |
| No page-level horizontal scroll | Yes — and the strip no longer scrolls itself either, so there is nothing hidden to scroll to |
| Secure Baseline content readable at 390px | Yes — all 14 control rows, their states and their "Managed in …" links render and wrap. Swept every element on the page: **none** extends past either viewport edge |
| All four Security Status screens at mobile width | Yes — Secure Baseline, Privileged Access Health, Exceptions, Security Events, light and dark |
| Desktop light not regressed | Yes — 1440px, one row, 10px browser-tab radius, active tab still attached and breaking the strip's rule |
| Desktop dark not regressed | Yes — same |
| Browser Back still works | Yes — clicked forward through all four tabs, then Back three times, returning exactly along the trail |
| No console errors | Yes — checked with the web fonts stubbed rather than aborted, so the harness could not manufacture its own errors |

The three other strips were re-checked at 390px in the same run and are now free
of the clipping recorded in §11.3.

**Looked at, not only measured.** The wrapped strip was inspected at 3× on both
themes. The four pills read as one set, every label is complete, the active tab
is obviously the current one, and the rule still separates the strip from the
content below it. Screenshots were captured during verification; they are
evidence of the run, not committed artefacts.

### 11.6 Regression guard and its mutations

`tests/Architecture/TabStripFitsANarrowScreenTest.php`. It is a **source-text
guard and it is not the evidence** — the evidence is §11.5. What it does is stop
the three declarations that caused the clipping from coming back silently.

| Mutation | Result |
| --- | --- |
| M-G2a — delete `flex-wrap: wrap` | **Caught** |
| M-G2b — delete `min-width: 0` (leaving `max-content` to force one row) | **Caught** |
| M-G2c — delete `overflow: visible` | **Caught** |
| M-G2d — narrow the breakpoint to 320px, so a phone never receives the rule | **Caught** |
| M-G2e — delete the active tab's `font-weight: 700` | **Caught** |
| M-G2f — delete the active tab's `border-color` | **Caught** |
| M-G2g — give the desktop tab a pill radius | **Caught** |
| M-G2h — delete the whole narrow-width block | **Caught** |

M-G2b and M-G2d are the two that matter: each leaves a rule that *looks* like a
responsive fix and does nothing at all.

The browser check itself was also mutated. With the correction reverted and the
bundle rebuilt, it failed on the exact tabs listed in §11.3 — so it is not
vacuous either, which its predecessor in §6 was.

### 11.7 What is NOT claimed here

- **This has not been observed on production.** It is verified locally in a real
  browser. Production observation is the Product Owner's, after deployment.
- **Sections A–F were not re-run by me.** They are the Product Owner's PASS, and
  this correction touches no server-side behaviour that could change them.
- **Gate D is not closed.** That is the Product Owner's decision, not mine.

### 11.8 Deployment of the G2 correction — 18 September 2026

Merged `f282571f9dbb24cb1d49c8d8249d350c18b3763f`; deploy run 129 succeeded;
post-merge CI run 262 green.

**"Nothing to migrate."** The correction is one CSS rule, one test file and two
documents. No migration exists and none was invented.

CI run 260 on the first push failed on `pint --test` alone — a one-line `match`
in the guard's helper — and skipped every later step. Recorded because a skipped
step is not a passing one. The formatting was fixed, all eight mutations were
re-run against the formatted file and all eight were still caught, and run 261
on that head was green before the merge.

Verified against production **without signing in and without changing anything**:

| Check | Result |
| --- | --- |
| The deployed stylesheet is the bundle that was verified | `build/assets/app-CnOf4vS7.css` is **byte-identical** to the local build the browser checks in §11.5 ran against |
| The entry page serves that bundle | It references exactly `app-CnOf4vS7.css`, so what was measured is what is live |
| The narrow-width rule is in it | `.org-tabs{…overflow:visible}`, `.org-tabs ul{…flex-wrap:wrap;min-width:0}`, `.org-tab{border-radius:var(--radius-pill);white-space:normal;transform:none}`, `.org-tab-active{border-color:…}` |
| All four Security Status routes reachable | `302` to sign-in, not `404` and not `500` |
| The three other strips' features reachable | Organisation, Identity & SSO and Users & groups all `302` |

The minifier emits `@media (width<=640px)` rather than `max-width: 640px`. That
is the CSS range syntax, and **all nine** media queries in this bundle already
shipped that way — it is not something this correction introduced.

### What could NOT be verified from here

**Nobody signed in.** Sign-in is Microsoft Entra SSO, so the wrapped strip was
not observed on production at phone width as the authenticated System
Administrator. G2 and the new G2a in the Product Owner Test Script exist for
exactly that, and this record does not claim it.
