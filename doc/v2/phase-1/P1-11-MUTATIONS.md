# P1-11 — Administration Home: mutation record

**Every guard broken deliberately, and the result observed. `CLAUDE.md` §2.**

A test that cannot fail is worse than no test: it reports safety that does not
exist. Each row below is a change made to the running code, the filter that was
run against it, and what happened. **The three that SURVIVED are recorded with
what was done about them** — a mutation record that lists only kills is a
mutation record nobody ran.

| | |
| --- | --- |
| Unit | **P1-11 — Administration Home** |
| DESIGN | Gate B approved — `P1-11-ADMINISTRATION-HOME-DESIGN.md`, merged as `59a3f73` |
| Harness | Apply one change, run the named filter, restore. One mutation live at a time |
| Total | **37 runs · 34 killed first time · 3 survived and were closed** |

---

## 1. D-182 — the navigation exception

| # | Mutation | Filter | Result |
| --- | --- | --- | --- |
| **M1** | `SystemAdministratorNavigationAuthorizer::allows()` — delete the D-182 exception, `return false` | `AdministrationHomeNavigationTest` | **KILLED** |
| **M2** | Widen the exception to every policy key — `return holdsRole(OrganisationAdministrator)` | `AdministrationHomeNavigationTest` | **KILLED** |
| **M26** | `ORGANISATION_ADMINISTRATOR_EXCEPTION` drifts to `'administration.home'` | `AdministrationHomeNavigationTest` | **KILLED** |
| **M29** | The menu node goes back to `NavigationNode::locked(...)` | `ConsoleNavigationTest`, `AdministrationHomeNavigationTest` | **KILLED** |

**M1 IS V6 OF THE DESIGN, AND ITS SHAPE WAS CHECKED RATHER THAN ITS OUTCOME.**
The requirement is not "some test fails" — it is that **V2 fails and V1, V3, V4
and V5 still pass**, because a visibility test that failed for a different
reason would prove nothing. Run with the exception deleted:

```
passed 5  failed 2
  FAILED: test_an_organisation_administrator_sees_administration_home       (V2)
  FAILED: test_an_organisation_administrator_sees_that_node_and_no_other    (V4)
```

V4 is V2's equality form, so both fail together and nothing else moves. **V1
(System Administrator sees it), V3 (unrelated roles do not), V5 (the route
authorises independently) all stayed green** — which is what makes V2 a
statement about D-182 rather than about the fixture.

**M2 is the opposite error and is caught by the equality.** With the exception
widened, an Organisation Administrator sees all eleven System Administration
nodes; V1, V2, V3 and V5 all still pass, and **only V4 fails**. Four separate
"does not see X" assertions would have missed it entirely.

---

## 2. DESIGN CORRECTION 3 — authorise before evaluating

| # | Mutation | Filter | Result |
| --- | --- | --- | --- |
| **M3** | Evaluate `SetupProjection::all()` **then** withhold the result | `AdministrationHomeTest`, `AdministrationHomeBudgetTest` | **KILLED** |
| **M4** | Evaluate `SystemHealthReport::areas()` **then** withhold the result | same | **KILLED** |
| **M15** | Evaluate `PostureEvaluator` a second time for the exceptions tile | `AdministrationHomeBudgetTest` | **KILLED** |

**M3 AND M4 ARE THE MUTATIONS THE PRODUCT OWNER'S CORRECTION NAMES**, and the
important property is that **they change no rendered output at all.** The tile
still reads *Withheld*, with no number and no link; the page source is
byte-identical. They are caught only because the guard asserts a **call count of
zero**, which is why the correction asked for a spy rather than for an
assertion about the screen.

**The spy counts CONSTRUCTION, not method calls.** Both classes are `final`, so
neither can be subclassed into a recording double — and that turned out to be
the stronger claim: a `SetupProjection` that is built and then not asked still
exists, with its secret store and identity resolver behind it, on a request that
may not receive a word of what it holds.

**The spy is proved to be wired in the same test.** A binding that never fired
would record zero for every viewer and the assertion would pass while checking
nothing, so the System Administrator's count is asserted to be **exactly 1** in
the same run.

---

## 3. DESIGN CORRECTION 2 — the empty deployment

| # | Mutation | Filter | Result |
| --- | --- | --- | --- |
| **M5** | On the null-organisation path, substitute `PeopleSummary::of(0, 0, 0)` | `AdministrationHomeTest` | **KILLED** |
| **M6** | Give the Domains tile a `Not configured` reading because the organisation is missing | `AdministrationHomeTest` | **KILLED** |
| **M7** | `PeopleSummaryProjection` drops its scope when `$organisationId === null` | `PeopleAndDomainSummarySeamsTest`, `AdministrationHomeTest` | **KILLED** |
| **M8** | `DomainSummaryProjection` drops its scope when `$organisationId === null` | same | **KILLED** |

**M5 AND M6 ARE THE TWO SHAPES D-144 WAS REWRITTEN AGAINST** — the first turns
"nobody asked" into a zero, the second turns it into a status the source never
gave. Both are caught by N13, which asserts the Organisation tile reads *Not
configured*, the queue leads with setting it up, **and every other tile keeps
its own state**.

**M7 AND M8 ESTABLISH THEIR PREMISE FIRST.** §11.2 names this failure — a guard
the framework was quietly enforcing — so each null-organisation case creates
**four people, two groups and two domains in a real organisation**, asserts they
ARE counted when the scope is passed, and only then asserts that nothing is
counted when it is not. Against an empty database both mutants pass.

---

## 4. Source-failure isolation and the Action Queue

| # | Mutation | Filter | Result |
| --- | --- | --- | --- |
| **M11** | The People catch returns `PeopleSummary::of(0, 0, 0)` | `AdministrationHomeTest` | **KILLED** |
| **M12** | Remove the Domains catch entirely — `finally` with no isolation | `AdministrationHomeTest` | **KILLED** |
| **M13** | The System Health row is emitted unconditionally | `AdministrationHomeTest` | **KILLED** |
| **M14** | `0 active groups` raises an Action Queue row | `AdministrationHomeTest` | **KILLED** |
| **M30** | The required-and-not-configured integration branch is deleted | `AdministrationHomeTest` | **SURVIVED → closed, see §7** |
| **M31** | The queue drops the `required` condition — any `not_configured` family raises a row | `AdministrationHomeTest` | **KILLED** |

**M13 is G12** — an Organisation Administrator must never receive a row pointing
at a screen they cannot open. It is caught because the platform-only source was
never evaluated for them, so the row's condition is not suppressed: it is never
asked.

---

## 5. The seams and their fences

| # | Mutation | Filter | Result |
| --- | --- | --- | --- |
| **M9** | `DomainSummaryProjection` counts against `ownerships()` instead of `currentOwnership()` | `PeopleAndDomainSummarySeamsTest` | **KILLED** |
| **M10** | Count disabled domains among the unowned | `PeopleAndDomainSummarySeamsTest`, `AdministrationHomeTest` | **KILLED** |
| **M34** | Replace `whereDoesntHave('currentOwnership')` with a **loop** over enabled domains asking each one who owns it | `AdministrationHomeBudgetTest` | **KILLED** |
| **M16** | `Group::query()->count()` added to `AdministrationHomeProjection` | `AdministrationHomeIsAProjectionTest`, `PeopleBoundaryTest` | **KILLED** |
| **M17** | `BusinessDomain::query()->count()` added to `AdministrationHomeProjection` | `AdministrationHomeIsAProjectionTest`, `DomainsBoundaryTest` | **KILLED** |
| **M20** | A summary class declared inside the Administration module | `AdministrationHomeIsAProjectionTest` | **KILLED** |
| **M25** | `public array $userNames = []` added to `PeopleSummary` | `PeopleAndDomainSummarySeamsTest` | **KILLED** |
| **M27** | Widen the **Domains** seam exemption to strip the Models namespace too | `DomainsBoundaryTest` | **SURVIVED → closed, see §7** |
| **M28** | Widen the **People** seam exemption to strip the Models namespace too | `PeopleBoundaryTest` | **KILLED** |

**M34 IS THE N+1 THE BUDGET CASE EXISTS FOR.** It is invisible at a fixture of
one and invisible in the rendered output at any size - the numbers are
identical, only the query count moves. It is caught because the same page is
measured at two data sizes and the counts must match exactly.

**M9 IS WHY THE SEAM REUSES `currentOwnership()`.** The fixture holds a domain
whose only ownership period has **ended**, so a projection asking the wrong
relation reports it owned. Without that row in the fixture, both relations give
the same answer and the mutation survives — the premise, again, established
rather than assumed.

**M16 AND M17 ARE THE MUTATIONS THE SEAM EXEMPTIONS CREATED.** Extending
`PeopleBoundaryTest` and `DomainsBoundaryTest` to let a consumer NAME the
projection would have been easiest as an allowlist entry — and that would have
exempted the one file legitimately consuming the seam from the whole guard, so
it could then have queried the tables directly. Instead the seam's
fully-qualified names are stripped before the scan and nothing else is, so
naming the seam is free for every file and naming a model is a finding for every
file.

---

## 6. Vocabulary, the screen and the route

| # | Mutation | Filter | Result |
| --- | --- | --- | --- |
| **M18** | Add `POST /console/administration` | `AdministrationHomeIsAProjectionTest` | **KILLED** |
| **M32** | D-136 — an `AuditChainHead` reference added to the projection | `AdministrationHomeIsAProjectionTest` | **KILLED** |
| **M33** | `actionQueue()` takes a viewer as well as the tiles | `AdministrationHomeIsAProjectionTest` | **KILLED** |
| **M19** | Call `HealthInspector::inspect()` — the one that reaches Microsoft | `AdministrationHomeIsAProjectionTest` | **KILLED** |
| **M21** | `Readiness::NotConfigured` reads *"Not set up"* | `AdministrationHomeIsAProjectionTest` | **KILLED** |
| **M22** | `Readiness::Ready` wears a tone the stylesheet does not declare | `AdministrationHomeIsAProjectionTest` | **KILLED** |
| **M23** | The screen derives its own verdict — renders `'healthy'` when a tile has no metrics | `AdministrationHomeIsAProjectionTest` | **KILLED** |
| **M24** | The screen renders `{metric.count ?? 0}` | `AdministrationHomeIsAProjectionTest` | **KILLED** |

**M33 IS HOW "THE ACTION QUEUE ISSUES NO QUERY" IS HELD.** The queue cannot
query because nothing in the module can — G3 forbids every shape of it — so the
property is asserted as the queue's SIGNATURE: it derives from tiles that were
already built, and a queue that took a viewer or a scope would be something that
can ask a question. Measuring queries around a private method would have tested
the measurement rather than the property.

**M22 IS THE `EveryCssTokenIsDeclared` FAILURE ONE LEVEL UP.** A tone naming a
class the stylesheet does not declare renders as an **unstyled pill** — no build
error, no failing render, and it fails only when somebody looks. The guard reads
the stylesheet rather than a list repeated in the test.

**M23 AND M24 ARE ASSERTED AGAINST THE COMPONENT SOURCE, because there is no
JavaScript test runner in this repository** — no CI test renders the DOM. That
is stated plainly rather than implied: the screen's claim to compute nothing is
checked by reading it, with comments stripped first and word boundaries applied,
because `available` is inside `unavailable`.

---

## 7. THE THREE THAT SURVIVED

**A mutation record that lists only kills is a mutation record nobody ran.**

### M27 — the Domains seam exemption could be widened invisibly

**What survived.** Widening the strip pattern from
`App\Modules\Domains\Projection\\w+` to `App\Modules\Domains\\w+` — which
removes the **Models** namespace as well as the seam's — passed
`test_the_seam_exemption_does_not_hide_a_model_or_a_table`.

**Why.** The case asserted an **OR over four needles**: after stripping,
`use App\Modules\Domains\Models\BusinessDomain;` still left the bare word
`BusinessDomain` behind, and the OR was satisfied by that. The assertion was
TRUE and said nothing whatever about the pattern.

**And the People version of the same case killed its mutant (M28)** — by luck,
because its needle list happened not to contain a bare class name that survives.
One of two identical guards was sound and the other was not, and only the
mutation could tell them apart.

**Closed by** asserting the property directly: the **model namespace must
survive the stripper intact**. Re-run as **M27d — KILLED**. The People case was
rewritten the same way even though its mutant had died, because it was right for
the wrong reason.

### M30 — the required-integration rule had no test at all

**What survived.** Deleting the branch that raises an Action Queue row for a
**required** integration that is not configured changed no test.

**Why.** The behaviour was correct and nothing asserted it. Every fixture had
Microsoft sign-in unconfigured, so the row was always produced — and never
checked. This is the second failure shape `CLAUDE.md` names: a rule that holds
today and would be removed tomorrow without a single red test.

**Closed by** two new cases, and the second is the one that costs something:
a deployment where **Microsoft sign-in IS configured** and the three optional
families are not, asserting **no row**. Building that fixture required writing a
real stored identity configuration and switching `identity_source` to the store
— which is the premise the absence needs. Re-run as **M30b — KILLED**, and
**M31**, the opposite error, killed alongside it.

### The navigation reader that read nothing

**Not a mutation — a defect in the test, found while writing it**, and recorded
because it is the same shape.

`systemAdministrationNodes()` looked for the product-area key
`'system_administration'`. The enum's value is `'system-administration'`, with
hyphens. The helper returned `[]` for **every** viewer, so
`test_unrelated_roles_do_not_see_administration_home` passed while checking
nothing at all. It was caught only because the two positive cases failed beside
it.

**Closed by** taking the key from `ProductArea::SystemAdministration->value`
rather than from a string typed in the test, and by
`assertTheReaderIsNotBroken()` — called before every absence assertion in that
file, requiring the same helper to return a non-trivial list first. `[]` is what
a broken reader and a correctly filtered menu both look like.

---

## 8. What was NOT mutated, and why

| Not mutated | Why |
| --- | --- |
| The database, the session store, the cache, the filesystem, the Audit chain, Microsoft Entra | Standing Product Owner instruction. No mutation touched an operational dependency |
| `PostureEvaluator`'s own logic | P1-06's, accepted five units ago. P1-11's use of it is mutated (M15); its internals are not this unit's to break |
| Production | Nothing in this record ran anywhere but locally |
