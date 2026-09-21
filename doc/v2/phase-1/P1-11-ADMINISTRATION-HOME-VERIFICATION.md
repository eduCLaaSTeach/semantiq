# P1-11 — Administration Home: VERIFICATION

**GATE C. Implementation complete, UNMERGED and UNDEPLOYED.**

| | |
| --- | --- |
| Unit | **P1-11 — Administration Home** (delivery order 13). The last Phase 1 delivery unit |
| PLAN | APPROVED — merged `b98bba4` (#137) |
| DESIGN | **APPROVED, Gate B closed** — D-182 and three corrections, merged **`59a3f73`** (#138) |
| Decisions in force | D-130 – D-147, D-178 – D-181, **D-182** |
| Schema | **NONE.** No table, no column, no migration |
| Writes | **NONE.** No write path, no cache, no new `SecurityEventLogger` key |
| Status | **NOT MERGED. NOT DEPLOYED.** Awaiting Product Owner Gate C review |

**What was executed and observed is stated below. Where something was not
executed, it says so and says why.**

---

## 1. What was built

| Class | Module | Owns |
| --- | --- | --- |
| `App\Modules\People\Projection\PeopleSummary` / `PeopleSummaryProjection` | **P1-03** — D-180 | Active users, inactive users, active groups. The People queries and their scoping |
| `App\Modules\Domains\Projection\DomainSummary` / `DomainSummaryProjection` | **P1-04** — D-181 | Enabled count, enabled-without-a-current-owner count. **Reuses `currentOwnership()`** |
| `App\Modules\Reviews\Projection\ReviewSummary` / `ReviewSummaryProjection` | **P1-07** | Outstanding and overdue counts, through `ReviewerAuthority::scopeVisible()`. **See §7.1 — this was not in the approved scope and is raised, not hidden** |
| `App\Modules\Administration\Support\AdministrationHomeProjection` | **P1-11** | Composition, the derived Domain reading, the Action Queue |
| `App\Modules\Administration\Support\{Tile, TileState, Readiness, ActionRow}` | **P1-11** | The shapes and the approved readiness vocabulary |
| `App\Modules\Administration\Http\Controllers\AdministrationHomeController` | **P1-11** | One `show()` |
| `resources/js/Pages/Administration/Home.jsx`, `Components/ReadinessBadge.jsx` | **P1-11** | The screen |

**Changed, and nothing else:** `routes/web.php` (one GET), `ApprovedMenu`
(`locked` → `leaf`, plus the stale "until P1-10" docblock), `SystemAdministrator
NavigationAuthorizer` (the D-182 exception), `Console/Home.jsx` (a stale P1-10
reference), `app.css` (one responsive grid), `ci.yml` (one MySQL step), and the
delivered-set assertions that had to grow by one.

---

## 2. The three DESIGN corrections, as implemented

### Correction 1 — the projection class names

`PeopleSummaryProjection::for()` and `DomainSummaryProjection::for()` are what
exists. `PeopleSummary` and `DomainSummary` are the immutable result shapes.
**No ambiguity reached EXECUTE.**

### Correction 2 — the empty deployment

**D-144 as corrected is implemented and proved.** With no organisation:

| Tile | Renders |
| --- | --- |
| Organisation | **`Not configured`** — what `current() === null` genuinely means |
| Users & Groups | **`Withheld`**, no number at all |
| Business Domains | **`Withheld`**, no number at all |
| Access Reviews | **`Withheld`**, no number at all |
| Platform Integrations, System Health | their own state, per §4.8 |
| Action Queue | **leads with "Set up the Organisation"** |

**N13** asserts every line of that, including that the withheld tiles carry no
`badge` and no `metrics`. **M5** (substitute `PeopleSummary::of(0,0,0)`) and
**M6** (give Domains a `Not configured` reading) were both run and both killed.

### Correction 3 — authorise before evaluating

**The two platform-only sources are not constructed, let alone called**, for a
viewer who may not receive them. Measured:

| | `SetupProjection` | `SystemHealthReport` |
| --- | --- | --- |
| System Administrator | **1** | **1** |
| Organisation Administrator | **0** | **0** |

**The spy counts CONSTRUCTION.** Both classes are `final` and cannot be
subclassed into a recording double — which turned out to be the stronger claim:
a `SetupProjection` that is built and not asked still exists, with its secret
store and identity resolver behind it, on a request that may not receive a word
of what it holds.

**The implementation shape this required is recorded, because it is a
departure.** The six source projections are resolved from the container inside
their own guarded branch rather than injected into the constructor. Constructor
injection would have built all six for every viewer before the method body ran,
so "not called" would have been true of the METHOD and false of the OBJECT. It
also makes the isolation rule cover **construction** failure, not only call
failure.

**M3 and M4 — evaluate then withhold — were run and killed, and they change no
rendered output at all.** The page source is byte-identical under both. That is
why the correction asked for a call-count assertion rather than one about the
screen.

---

## 3. D-182 — what was proved

| # | Case | Result |
| --- | --- | --- |
| **V1** | System Administrator sees Administration Home | **PASS** |
| **V2** | Organisation Administrator sees Administration Home | **PASS** |
| **V3** | Auditor, Executive, Business User, Domain Owner, no role | **do not see it** |
| **V4** | Organisation Administrator's System Administration node set | **exactly `['Administration Home']`** — asserted as an equality |
| **V5** | The route authorises with the navigation authorizer replaced by a deny-all | **200 for both kinds** |
| **V6** | **Mutation** — delete the exception | **V2 and V4 fail; V1, V3, V5 still pass** |

**V6's shape was checked, not just its outcome.** Run with the exception
deleted:

```
passed 5  failed 2
  FAILED: test_an_organisation_administrator_sees_administration_home    (V2)
  FAILED: test_an_organisation_administrator_sees_that_node_and_no_other (V4)
```

V4 is V2's equality form, so the two fail together and nothing else moves.

**M2 is the opposite error** — widen the exception to every key — and **only V4
catches it**. V1, V2, V3 and V5 all still pass with an Organisation
Administrator seeing all eleven nodes. Four separate "does not see X"
assertions would have missed it entirely.

**Observed in the browser**, Organisation Administrator at 1440 light:

```
sidebar: System Administration -> ['Administration Home'],  0 locked nodes
```

No other area renders for them, because `workplace.view` and `fabric.view` are
still refused. **That is the honest consequence of D-182's narrowness and is
raised in §7.2**, not presented as a finished experience.

---

## 4. Test and guard results

| | |
| --- | --- |
| Full suite (SQLite) | **1246 tests · 1240 passed · 6 skipped · 1 risky · 91,209 assertions · 151 s** |
| P1-11's own cases | **37 tests · 475 assertions** across four files |
| Pint | **passed** |
| Prettier (the three files this unit touches) | **passed**, house style `--single-quote --no-semi --tab-width 4 --print-width 100` |
| Mutations | **37 runs · 34 killed first time · 3 survived and were closed** — `P1-11-MUTATIONS.md` |
| MySQL | **NOT RUN LOCALLY — no MySQL server exists in this environment.** A CI step was added (§4.1) and runs on the pull request |

### 4.1 MySQL — the step caught a defect on its first run

**Stated plainly: I still have not watched a MySQL run to completion.** There
is no MySQL server in this environment, so the claim rests on the CI job.

A step was added — *"Run the Administration suite against MySQL"* — with the
same count assertion every other engine step carries, because **a path that
matches nothing exits 0**. It was not ceremonial, and it proved that
immediately.

**IT HUNG.** Every other suite in that job finished in under a minute; this one
ran for **twenty-two minutes** and was still going when the run was superseded.

**The cause was mine, and it was invisible on SQLite.** Two of my tests called
`refreshApplication()` and `artisan('migrate:fresh')` MID-TEST to reset between
cases. On SQLite that is cheap and harmless. On MySQL `refreshApplication()`
abandons the `RefreshDatabase` transaction while it still holds locks, and the
next `migrate:fresh` waits on a metadata lock nothing ever releases.

**Both were rewritten to need neither:**

- the failure-isolation case now binds each source **once**, to a closure that
  throws only while a flag selects it and calls `$app->build()` otherwise. One
  application, one fixture, five requests;
- the growth case **grows the same deployment in place** from 2 of everything to
  20, rather than rebuilding. That is also the better measurement — there is one
  organisation, so a second could never have been the scope, and the comparison
  is now one deployment before and after it got ten times bigger.

**This is exactly the failure the step was added to find** — "P1-08 and P1-09
both found engine-specific failures that were green on SQLite" — arriving on the
first run of the third such step.

### 4.2 The guards, and what each would catch

| Guard | Lives in | Proved by |
| --- | --- | --- |
| **G1** Nothing outside People reads or counts a group | **Extended `PeopleBoundaryTest`** | M16, M28 |
| **G2** Nothing outside Domains reaches past the seam | **Extended `DomainsBoundaryTest`** | M17, M27 |
| **G3** No P1-11 file queries anything | `AdministrationHomeIsAProjectionTest` | M16, M17 |
| **G4** The seams live in the modules that own them | same | M20 |
| **G5** Neither seam answers with a null organisation | `PeopleAndDomainSummarySeamsTest` | M7, M8 |
| **G6** The DTOs carry exactly their declared properties | same | M25 |
| **G7** No network name appears anywhere in P1-11 | `AdministrationHomeIsAProjectionTest` | M19 |
| **G8** P1-06 is evaluated once per render | `AdministrationHomeBudgetTest` | M15 |
| **G9** The route set is exactly one GET | `AdministrationHomeIsAProjectionTest` | M18 |
| **G10** No new `SecurityEventLogger` key | `NoSecretReachesTheBrowserTest` (existing) | catalogue unchanged at 15 |
| **G11** No migration | `NoBusinessSchemaTest` (existing) | no migration added |
| **D-136** No Audit feed | `AdministrationHomeIsAProjectionTest` | M32 |
| **The Action Queue issues no query** | same — asserted as the queue's signature, since G3 already makes a query impossible anywhere in the module | M33 |
| **G12** No row whose destination the viewer cannot open | `AdministrationHomeTest` | M13 |
| **G13** A failed source is Not available, never `0` | same | M11, M12 |
| **G14** Every CSS token declared, both themes | existing | sweep, §5 |
| **G15** The node is a leaf and nothing else moved | `ConsoleNavigationTest` | M29 |
| **G16** Zero calls to the platform-only sources | `AdministrationHomeTest` | M3, M4 |
| **G17** A null organisation is never `0` or `Not configured` | same | M5, M6 |
| **G18** D-182, V1–V5 | `AdministrationHomeNavigationTest` | M1, M2, M26 |

**The seam exemptions were the delicate part.** Letting a consumer NAME
`App\Modules\{People,Domains}\Projection\…` would have been easiest as an
allowlist entry — and that exempts a FILE from the whole guard, so the one file
legitimately consuming the seam could then have queried the tables directly and
nothing would have failed. Instead the seam's fully-qualified names are stripped
before the scan and nothing else is. **M16 and M17 confirm it**: adding
`Group::query()->count()` or `BusinessDomain::query()->count()` to
`AdministrationHomeProjection` — the file that legitimately names both seams —
still fails.

---

## 5. Browser verification — what was actually observed

**Chromium `/opt/pw-browsers/chromium-1194`, against a local server with a
seeded deployment**: one organisation, 14 active people, 3 inactive, 4 groups,
4 enabled business domains (2 unowned), 1 disabled, no integration configured.

**Every figure below was read off the rendered DOM, not from the payload.**

| | 1440 light | 1440 dark | 390 light | 390 dark |
| --- | --- | --- | --- | --- |
| Readiness tile columns | **4** | **4** | **1** | **1** |
| Horizontal page overflow | **none** | **none** | **none** | **none** |
| Elements wider than the viewport | **none** | **none** | **none** | **none** |
| Raw enum / key / internal term in the visible text | **none** | **none** | **none** | **none** |
| Person name, email or domain record in the visible text | **none** | **none** | **none** | **none** |
| Console errors | **none** | **none** | **none** | **none** |

The raw-term sweep looked for `not_configured`, `org_admin`, `platform_admin`,
`i-grid`, `administration.view`, `semantiq_user`, `Modules\`, `valued`,
`withheld`, `statusInWords`, `linkLabel`, `organisation_id`, `business_domain`,
`undefined` and `null`. The leak sweep looked for `@northwind.test`, `Person 1`,
`Ada Sysadmin`, `Bo Orgadmin` and `Former 1` — **all present in the seeded data
and counted by the screen**, so their absence is a finding rather than an
accident of an empty database.

> **The only console message in any run was `net::ERR_FAILED` from the Google
> Fonts stylesheet, which the sweep aborts deliberately** — it hangs through
> this environment's inspecting proxy rather than failing, so `networkidle`
> never arrives. Every accepted screen was measured with the same block.

### 5.1 Beside the screens it summarises — the P1-10 Gate D lesson

**This screen was not validated only against itself.** Measured on the same
render, at 1440 light:

| Screen | `h1` size | Lead size | Page width |
| --- | --- | --- | --- |
| **Administration Home** | **20px** | **13px** | **1100** |
| Organisation | 20px | 13px | 1100 |
| Security Status | 20px | 13px | 1100 |
| Access Reviews | 20px | 13px | 1100 |
| System Health | 20px | 13px | 1100 |
| Integrations | 20px | 13px | 1100 |

**Identical.** It uses `org-page`, `org-feature`, `sys-area`, `sys-area-head`,
`sys-rows`, `sys-status` (through `HealthStatusBadge` and `ReadinessBadge`),
`sec-metric` and `org-empty` — all already shipped and already accepted.

**THE TAB PATTERN WAS NOT FORCED ONTO IT.** Organisation and Integrations use
Pattern B because they have sections you switch between. This has areas you read
at once, and a strip would have hidden three-quarters of the page behind a
control nobody needed.

### 5.2 New CSS — one grid, and the reason

`.adm-tiles` is `grid-template-columns: repeat(auto-fit, minmax(220px, 1fr))`,
plus the tile, queue and quiet-state rules that hang off it. **auto-fit rather
than a column count**, so 390px gets one column with no media query to keep in
step and the two-tile areas lay out correctly without a second rule. Only
declared tokens are used; `EveryCssTokenIsDeclaredTest` is what makes that true
rather than intended, and **M22** — a readiness tone naming a class the
stylesheet does not declare — was run and killed.

### 5.3 Keyboard and focus

Tabbed through the Organisation Administrator's view. **Every focusable
element took the shared ring** — `solid 2px rgb(25, 62, 107)` — and every one
was visible. There was **no focusable-but-invisible control**.

The tab order for an Organisation Administrator contained:

```
Open Organisation · Open Users & Groups · Open Business Domains ·
Open Security Status · Open Exceptions · Open Access Reviews
```

**and no "Open Integrations" or "Open System Health"** — D-145 holding in the
tab order as well as on the screen, because a link the viewer cannot open is not
rendered at all.

### 5.4 The professional-polish gate — two defects found by looking

Both were found in the rendered screenshots, not by a test.

**1. The exceptions tile repeated itself.** It was headed *"Open exceptions"*
and carried a link reading *"Open exceptions"* three lines below — the same two
words meaning the ADJECTIVE in the heading and the VERB in the link. **P1-09
found the identical shape** (an area named "Background work" holding a row named
"Background work") and fixed it the same way. The tile is now **"Security
exceptions"**; the count is still labelled "Open exceptions", because that is
what the number is.

**2. An Action Queue row named a screen it did not open.** The exceptions row
read *"Open Security Status"* and landed on the Exceptions tab. It now reads
**"Open Exceptions"** — the tab it actually lands on, in that screen's own
words.

Then, honestly:

> **Would a professional SaaS product team be comfortable showing this exact
> screen to a customer?**

**Yes** — with §7.2 said out loud first.

---

## 6. Performance — D-140, measured

**Measured against the seeded deployment, through the real HTTP stack.**

| | Queries | Projection time | Full response |
| --- | --- | --- | --- |
| **System Administrator** | **113** | 625 ms | **0.49 – 0.76 s** |
| **Organisation Administrator** | **75** | 470 ms | **0.46 – 0.59 s** |

**D-140's ≤ 2 s target is met with margin.** The Organisation Administrator pays
**38 fewer queries and 155 ms less** — that is Correction 3's cost half, and it
is the part a viewer would actually feel.

**No N+1 in this unit's own composition, proved by measurement.** The same page
rendered at two data sizes — 2 and 20 of everything — issues an **identical**
query count with the posture source out of the way. Every figure this unit asks
for is an aggregate and no model row is loaded to be counted.

**The summary is cheaper than the tour.** Administration Home costs less than
visiting the six screens it summarises, asserted as a property rather than as a
number, because that is the claim the product actually makes.

### 6.1 A FINDING — the posture source carries a pre-existing per-domain cost

**This is P1-06's and it is already in production. It is raised, not fixed.**

Measured on the same fixture at 20 business domains:

```
/console/administration          236 queries
/console/security                189 queries
/console/security/exceptions     189 queries
/console/system-health            58 queries
/console/domains                  44 queries
```

`PostureEvaluator`'s `DomainAdapter` issues **five aggregates per business
domain** — entitlements, scopes and ceilings. **Security Status already pays 189
of those 236 today**, on a screen accepted five units ago. P1-11 does not add to
it: P1-06 is evaluated **once** and feeds two tiles, and **M15** — evaluate it a
second time for the exceptions tile — was run and killed.

**Widening P1-11 to re-engineer an accepted unit's evaluator is exactly the
scope creep the professional-polish gate says it does not authorise.** It is
raised here as a Product Owner decision. It becomes more visible as the number
of business domains grows, and Administration Home is the screen that will make
it visible first.

---

## 7. Findings, departures and things raised

### 7.1 The DESIGN contradicted itself about Access Reviews, and it is resolved here

**§4.6 has Administration Home calling `->count()` on a scoped
`AccessReviewItem` builder. Guard G3 says no P1-11 file may reference
`AccessReviewItem` or import `Illuminate\Database` at all.** Both cannot hold.

**Resolved in favour of the rule the unit exists to defend** — P1-11 composes
and does not query — by adding a P1-07-owned read seam in the same shape D-180
and D-181 already approved twice. `ReviewerAuthority::scopeVisible()` is still
the only thing deciding visibility; the seam asks it.

**This is one class and one DTO beyond the scope the Product Owner stated**, and
it is raised rather than absorbed. The alternative was to weaken G3, which would
have meant a dashboard holding another unit's query.

### 7.2 D-182 is narrow, and an Organisation Administrator's sidebar shows one item

**Observed, not inferred.** An Organisation Administrator now sees:

```
SYSTEM ADMINISTRATION
  Administration Home
```

and nothing else. The other four System Administration screens they can reach by
URL — Organisation, Users & Groups, Roles & Access, Business Domains — **are
still hidden**, and SemantIQ Workplace and Fabric Configuration do not render at
all for them.

**That is exactly what D-182 approved**, and G18's V4 asserts it so that
widening it later has to be a decision rather than a side effect. It is stated
here because **a sidebar with one item in it is a thing the Product Owner should
see before testing, not discover during it.** The four hidden screens remain a
carried navigation item.

### 7.3 System Health is not collapsed into one state, and the DESIGN said it would be

**DESIGN §4.7 says the area states are "collapsed to one overall state and a
count of rows not healthy". The overall state was not built, deliberately.**

`SystemHealthArea`'s own docblock refuses an area-level verdict: *"An area-level
verdict would be a SEVENTH status nothing measured — the enum has six — computed
from rows whose meanings do not combine: 'one Not applicable and one
Unavailable' has no summary that is not a lie."* Rolling fourteen rows across
five areas into one badge is the same mistake one level up.

**The tile reports two neutral counts instead** — *Needing attention* and *Not
checked yet* — which are smaller claims and true ones. **Not checked is shown
rather than hidden**: it is the whole reason `HealthStatus` is six long rather
than five, and a tile reporting only failures would let "nobody has looked" read
as "everything is fine".

**This is a departure from an approved DESIGN and is put to the Product
Owner**, not presented as compliance.

### 7.4 Three mutations survived and were closed

Recorded in full in `P1-11-MUTATIONS.md` §7, and summarised because a mutation
record that lists only kills is a mutation record nobody ran:

- **the Domains seam exemption could be widened invisibly.** The non-vacuity
  case asserted an OR over four needles, and the bare class name survived the
  stripper and satisfied it. **The People version of the identical guard killed
  its mutant by luck.** Both now assert that the model namespace survives
  intact;
- **the required-integration rule had no test at all.** Deleting the branch
  changed nothing, because every fixture had Microsoft sign-in unconfigured so
  the row was always produced and never checked. Closed by two cases, the second
  of which needed a fixture with sign-in genuinely configured;
- **the navigation reader read nothing.** `systemAdministrationNodes()` looked
  for `'system_administration'`; the enum's value has hyphens. It returned `[]`
  for every viewer, so the "unrelated roles see nothing" case passed while
  checking nothing. Closed by taking the key from the enum and by
  `assertTheReaderIsNotBroken()`, called before every absence assertion in that
  file.

---

## 8. What this unit did NOT do

| | |
| --- | --- |
| Schema | **NONE.** No table, no column, no migration |
| Writes | **NONE.** No write route, no service call that writes, no `->save()` anywhere in the module |
| Cache | **NONE** — D-141 |
| Security events | **NONE.** `ALLOWED_KEYS` unchanged at **15** |
| Network | **NONE.** `SystemHealthReport` is built on `inspectLocal()` and `storedReport()`; **M19** — reaching `inspect()` — was run and killed |
| Audit feed | **NONE** — D-136 |
| `/console` | **Unchanged** — D-131. Asserted, and the stale "P1-10 owns that" comment corrected to P1-11 |
| Production | **Untouched.** Nothing in this record ran anywhere but locally |

**None of the nine carried items is closed by this unit, and none was
attempted.** Specifically NOT resolved inside P1-11: the production
session-driver alignment; per-user session revocation; the P1-02
second-administrator re-check; the Bootstrap First-Run, idle-timeout and
recovery live observations; the real SMTP observation; the Microsoft privileged
step-up live observation. **A tile may show a carried gate's state. Showing it
closes nothing.**

---

## 9. Not currently observable, and why

| | |
| --- | --- |
| **MySQL** | **Not run by me.** No MySQL server exists in this environment. The CI step added in §4.1 is the evidence, and it runs on the pull request |
| **Production rendering** | **Not observed, and cannot be.** Chromium does not trust this environment's proxy CA, `certutil` is unavailable and `apt` is not available to install it. TLS verification was **not** disabled at any point. Production browser observation is a Gate D activity after deployment |
| **A second permanent administrator** | Carried. No account was manufactured to make anything here observable |
| **The screen at a large data size, live** | The 20-of-everything measurement is a local fixture. Production holds one organisation and a handful of domains |

---

## 10. Status

**GATE C. READY FOR PRODUCT OWNER REVIEW.**

**The implementation pull request is UNMERGED and UNDEPLOYED**, per the
Product Owner's instruction.

**Three things are put to the Product Owner rather than decided here:** §7.1 the
Reviews seam beyond the stated scope, §7.2 the one-item sidebar D-182 produces,
§7.3 the System Health tile that does not collapse to one state. **And one
finding is raised:** §6.1, P1-06's per-domain evaluation cost, which is
pre-existing, already in production, and not P1-11's to fix.
