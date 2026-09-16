# P1-06 — Security Status: verification record

**What was executed and observed.** A passing automated test is not the same
claim as an observed production result, and nothing below presents one as the
other.

| | |
| --- | --- |
| Unit | **P1-06 — Security Status** |
| PLAN | merge `4d93228c1aa2a603f28db1564a6b6304c4b97ba8` (D-75 – D-81) |
| DESIGN | merge `0559315e632d04c071727bc5c8176a2dba475999` (D-82, D-83) |
| Status | **EXECUTE complete — awaiting Gate C review. NOT MERGED. NOT DEPLOYED.** |

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

## 10. What is NOT claimed

- **Nothing has been deployed.** Every observation above is from a local build.
- **No production data was read or changed.**
- The **MySQL** result above is from CI run 35067226635, observed, not inferred.
- **Visibility, discoverability, theme and responsive behaviour are human
  observations**, recorded as observed. No automated test in this project can
  make those claims.
- The states listed in the Product Owner Test Script §11 **cannot be observed on
  production** without creating false permanent history, and are carried forward
  by name rather than inferred from a passing test.
