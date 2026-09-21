# P1-11 — Administration Home: DESIGN

**DESIGN ONLY. No implementation. No schema. No deployment.**

| | |
| --- | --- |
| Unit | **P1-11 — Administration Home** (delivery order 13). The last Phase 1 delivery unit |
| PLAN | **APPROVED** 21 September 2026 — `P1-11-ADMINISTRATION-HOME-PLAN.md` |
| Decisions in force | **D-130 – D-147** (§6 of the PLAN) and **D-179 – D-181** (§6a). **D-178** is P1-10's contract to this unit |
| Schema | **NONE.** §12 |
| New code outside this unit | **Two read projections**, owned by P1-03 and P1-04 — §5, §6 |
| Blockers | **One, and it is real** — §14.1 |

---

## 0. What this document is answering

The PLAN established that Administration Home **owns no source-of-record fact —
only the approved composition and derived interpretation of facts other units
supply.** This DESIGN says exactly which class supplies each fact, exactly what
this unit derives from it, and exactly what stops a future edit from reaching
past a seam into somebody else's table.

**Everything below was read from current `main`.** Where a class is named, it
exists today unless it is marked **NEW**; where a signature is given, it is the
signature in the file.

---

## 1. Route, access and navigation

### 1.1 The route

```
GET /console/administration          name: administration.home
```

**One route. One verb. No parameters.** Nothing on this screen changes
anything, so there is nothing for a second verb to do.

| | |
| --- | --- |
| Middleware | `RequireActionClass::class.':'.ActionClass::OrgAdmin->value` — **D-132** |
| `RequireOrganisation` | **ABSENT** — **D-133**. A day-one deployment must not be bounced to Company Profile by the screen that is supposed to tell it what to do next |
| Controller | `App\Modules\Administration\Http\Controllers\AdministrationHomeController` — **NEW**, one `show()` method |
| Inertia page | `Administration/Home` — **NEW** |

**`/console` is unchanged** — D-131. It keeps the D-11 confirmation state and
stays reachable by anyone with a session, including a person with no role at
all. Administration Home is not that page and must not become it.

### 1.2 Navigation

`ApprovedMenu` currently carries:

```php
NavigationNode::locked($area, 'Administration Home', 'i-grid', $policy),
```

It becomes:

```php
NavigationNode::leaf($area, 'Administration Home', 'i-grid', 'administration.home', 'administration.view'),
```

**The policy key `administration.view` already exists** — `ApprovedMenu` declares
it for the locked node today, so the leaf reuses it rather than inventing one.

**First in System Administration, unchanged.** No other node moves, no other
label changes, no area is reordered. **D-19 is unchanged**: the node already
sits inside System Administration, so nothing is widened by making it
navigable.

> **One stale sentence to correct while here.** `ApprovedMenu`'s docblock reads
> *"stays locked until P1-10, which is deliberately built last"* — written
> before the 20 September renumbering, when Administration Home **was** P1-10.
> It means this unit. EXECUTE corrects it to P1-11 in the same commit that
> makes the node a leaf; leaving it would be a third document disagreeing about
> which unit is which.

---

## 2. THE ONE REAL BLOCKER — navigation authority and D-132 disagree

**This is a genuine finding, established by reading the code, and it needs a
Product Owner ruling before EXECUTE.**

`SystemAdministratorNavigationAuthorizer::allows()` — the only real
`NavigationAuthorizer` in the application — is, in full:

```php
return $user instanceof User
    && $user->isActive()
    && $this->engine->holdsRole($user, RoleCode::SystemAdministrator);
```

**Every node in System Administration is shown to System Administrators and to
nobody else.** That is true today for Organisation, Users & Groups, Business
Domains and Roles & Access, all of which are `OrgAdmin` routes.

`RouteAuthorizationMatrixTest` proves the other half behaviourally: an
Organisation Administrator **reaches** `/console/organisation` and gets **200**.

So the current state of the repository is:

> **An Organisation Administrator can open four System Administration screens
> and cannot see a single one of them in the sidebar.**

**This is pre-existing and P1-11 does not create it.** But P1-11 is the *home*
screen — the one whose entire purpose is to be the place an administrator
starts — and D-145 forbids rendering a link the viewer cannot open. Shipping a
home page that an Organisation Administrator is authorised for and cannot find
is the discoverability failure the professional-polish gate names last and this
project has already missed once.

**§14.1 states the three options and the recommendation. It is not resolved
here.**

---

## 3. Composition — one controller, one pass, four areas

```
AdministrationHomeController::show(Request)
        │
        ├── AdministrationHomeProjection::for(User $viewer, ?int $organisationId)   NEW, this unit
        │        │
        │        ├── A. Readiness
        │        │      ├── OrganisationService::current()                 P1-01, exists
        │        │      ├── PeopleSummary::for(...)                        P1-03, NEW
        │        │      ├── DomainSummary::for(...)                        P1-04, NEW
        │        │      └── SetupProjection::all()                         P1-10, exists
        │        │
        │        ├── B. Security
        │        │      └── PostureEvaluator::evaluate()                   P1-06, exists
        │        │            → PostureProjection::for($report, $viewer)   ONE evaluation, two tiles
        │        │
        │        ├── C. Reviews & Operations
        │        │      ├── ReviewerAuthority::scopeVisible(...)           P1-07, exists
        │        │      │     + AccessReviewItem::scopeOverdue()           P1-07, exists
        │        │      └── SystemHealthReport::areas()                    P1-09, exists
        │        │
        │        └── D. Action Queue
        │               └── derived from A, B and C. No source of its own
        │
        └── Inertia::render('Administration/Home', [...])
```

**`AdministrationHomeProjection` is the only new class in this unit that holds
logic.** It composes; it does not query. **It has no Eloquent import at all**,
and §11's guard asserts that as a property of the file rather than as an
intention.

---

## 4. Source contracts — exactly what each tile reads

### 4.1 A · Organisation — D-179

| | |
| --- | --- |
| Seam | `OrganisationService::current(): ?Organisation` — **exists** |
| Reads | The single organisation, or `null` |
| Derives | `current() === null` → **Not configured** · otherwise → **Configured** |
| Shows | The state, and an authorised link to Organisation |
| Does NOT show | Legal entity, business unit, department or team counts. **Release 1, D-179** |

**No structure-count projection is added.** If counts are wanted later, **P1-01
owns that read** — not a query copied into a tile.

### 4.2 A · Users & Groups — D-180 — **NEW SEAM, owned by P1-03**

**Contract:**

```php
namespace App\Modules\People\Projection;

final readonly class PeopleSummary
{
    public function __construct(
        public bool $valued,          // false → Withheld. The counts are then all null
        public ?int $activeUsers,
        public ?int $inactiveUsers,
        public ?int $activeGroups,
    ) {}
}

final class PeopleSummaryProjection
{
    public function for(User $viewer, ?int $organisationId): PeopleSummary;
}
```

**Binding properties, each with the reason it is binding:**

| Property | Why |
| --- | --- |
| **The queries live in `App\Modules\People`** | D-180. The next screen reuses the seam rather than copying a query out of a dashboard |
| **Scope is applied INSIDE `for()`**, never by the caller | A caller that must remember to scope is a caller that will forget |
| **`$organisationId === null` returns `valued: false`** with every count `null` | **Never "all organisations".** This is `ReviewerAuthority::scopeVisible()`'s rule, which states it in its own words: *"'No organisation on the assignment' must never widen into 'every organisation'."* P1-11 uses the same rule because it is the same risk |
| **Three integers and a boolean. Nothing else** | No name, no email, no membership, no person-level row, no business payload. A projection that cannot carry a person cannot leak one |
| **No table, no cache** | D-141, D-180 |
| **No duplicated access logic** | It asks `AccessEngine` if it must ask anything; it does not re-derive a role |

**Counts are counts.** `0 active groups` is a fact, not a finding — D-138, and
`sec-metric-count` already carries that rule in shared CSS: *"A count is a
NUMBER, not a status … carrying no semantic colour at all."*

### 4.3 A · Business Domains — D-181 — **NEW SEAM, owned by P1-04**

**Contract — facts only:**

```php
namespace App\Modules\Domains\Projection;

final readonly class DomainSummary
{
    public function __construct(
        public bool $valued,          // false → Withheld
        public ?int $enabled,         // enabled domains in scope
        public ?int $enabledUnowned,  // of those, how many have no CURRENT accountable owner
    ) {}
}

final class DomainSummaryProjection
{
    public function for(User $viewer, ?int $organisationId): DomainSummary;
}
```

**`enabledUnowned` reuses P1-04's accepted ownership model and does not restate
it.** The definition of *current* already exists in exactly one place:

```php
// BusinessDomain, today
public function currentOwnership(): HasOne
{
    return $this->hasOne(DomainOwnership::class)->whereNull('ended_at');
}

// DomainOwnership, today
public function isCurrent(): bool { return $this->ended_at === null; }
```

The projection is implemented as `whereDoesntHave('currentOwnership')` over
enabled domains in scope — **the same relation the Domains screens use**, so
the dashboard and the feature cannot disagree about who owns what.

**P1-11 derives the verdict. P1-04 does not.**

```
enabled = 0                                   → Not configured
enabled > 0 AND enabledUnowned > 0            → Needs attention
enabled > 0 AND enabledUnowned = 0            → Ready
```

> **Why the verdict is not in P1-04.** D-130 made the readiness interpretation a
> Product Owner decision, and §0 of the PLAN makes that the one thing this unit
> owns. A three-way verdict inside P1-04 would be P1-04 owning a judgement about
> its own data that only this screen asked for — and the next consumer wanting
> a different reading would have to work around it.

**Not coupled to entitlements.** Domain ownership grants zero access by itself —
P1-04's founding rule — and this projection touches no entitlement, role or
scope.

### 4.4 A · Platform Integrations — D-178

| | |
| --- | --- |
| Seam | `SetupProjection::all(): array` — **exists**, returns one `IntegrationView::toArray()` per family |
| Reads | `family`, `name`, `status`, `statusInWords`, `required`, `lastTestedAt` |
| Ignores | `fields`, `choices`, `secrets` — present in the array, **not read and not forwarded** |
| Derives | Nothing. The status **is** the status |
| Runs | **No connection test. No provider call. No decryption** |

**`all()` once, not `forFamily()` four times** — §9's budget.

> **The identity row is the one to be careful with, and P1-10 proved why.**
> Identity has two possible authorities — the environment before the controlled
> cutover, the encrypted store after it. P1-10 shipped a defect that asked the
> wrong one and told a working deployment its sign-in was *Not configured*.
> `SetupProjection` now asks the authority the sign-in path asks. **P1-11 reads
> `SetupProjection` and nothing else** — not `config('identity.microsoft.*')`,
> not `PlatformSetting`, not a second resolver.

### 4.5 B · Security posture and Open exceptions — **ONE evaluation**

| | |
| --- | --- |
| Seam | `PostureEvaluator::evaluate(): PostureReport`, then `PostureProjection::for($report, $viewer): ViewerReport` — **both exist** |
| Posture tile | `ViewerReport::aggregate()`, `aggregateLabel()`, `caption()` |
| Exceptions tile | `ViewerReport::exceptions()` — **the same object** |
| Withheld | `ViewerReport::seesPlatformValues` and `withheld()` already carry it |
| Derives | **Nothing.** Both tiles render what P1-06 already decided |

**`RendersPosture::summary()` already produces exactly this shape** —
`aggregate`, `aggregateLabel`, `caption`, `exceptionCount`, `seesPlatformValues`.
The DESIGN reuses it rather than assembling a second one.

> **Two tiles, one evaluation, and this is not an optimisation.** `ViewerReport`
> states in its own docblock that `exceptions()` is *"the single source of the
> list, the tab count and the heading count"*. Evaluating twice would be two
> answers to one question, and the screen would eventually show both.

### 4.6 C · Access Reviews

| | |
| --- | --- |
| Seam | `ReviewerAuthority::scopeVisible(Builder, User, ?int)` — **exists** — plus `AccessReviewItem::scopeOverdue()` — **exists** |
| Reads | `->count()` on the scoped builder, and on the scoped-and-overdue builder |
| Derives | **Overdue > 0 → an Action Queue row.** Nothing else |
| Visibility | **Decided entirely by P1-07.** P1-11 never writes a `where` against review tables |

**`scopeVisible()` is already organisation-scoped for a platform-scoped System
Administrator**, and says so in its own comment. P1-11 inherits that rather
than re-deciding it.

### 4.7 C · System Health

| | |
| --- | --- |
| Seam | `SystemHealthReport::areas(): list<SystemHealthArea>` — **exists** |
| Reads | The area states, collapsed to one overall state and a count of rows not healthy |
| Derives | An Action Queue row when something is not healthy **and the viewer is authorised for System Health** |
| Network | **NONE** |

**The zero-network guarantee is structural, not a promise.**
`SystemHealthReport` is built on `HealthInspector::inspectLocal()` and
`IdentityHealthCheck::storedReport()`. `inspectLocal()`'s own docblock explains
why it exists: `inspect()` reaches `report()`, which on a cold discovery cache
**asks Microsoft over the network**. P1-09 built the local projection precisely
so a screen could promise to contact nobody and mean it.

**P1-11 must never call `inspect()`, `report()`, `semantiq:health`, a connection
tester, or `EntraDiscovery`.** §11's guard asserts the absence of each by name.

---

## 5. Viewer scope, null organisation, and the two administrator kinds

**D-133 removed `RequireOrganisation`. That makes the null case normal rather
than exceptional, so it is specified rather than assumed.**

### 5.1 The rule

> **A missing scope narrows. It never widens.**

There is exactly one place this could go wrong and it is worth naming: a System
Administrator is **platform-scoped**, and platform-scoped is not the same as
*every organisation*. Nothing in this screen may read a null `organisation_id`
as "show me all of them".

**`ReviewerAuthority::scopeVisible()` already states the rule in its own
comment** — *"'No organisation on the assignment' must never widen into 'every
organisation'"* — and the two NEW projections adopt it verbatim.

### 5.2 Both administrator kinds, explicitly

| Tile | System Administrator | Organisation Administrator |
| --- | --- | --- |
| **Organisation** | Configured / Not configured | Configured / Not configured |
| **Users & Groups** | **Withheld** when no organisation is resolvable; counts for that organisation when one is | Counts for **their own** organisation |
| **Business Domains** | **Withheld** when no organisation is resolvable; Ready / Needs attention / Not configured when one is | The verdict for **their own** organisation |
| **Platform Integrations** | Full four-family status | **Withheld.** These are deployment-wide credentials, not one organisation's — the same reasoning that makes Integrations `PlatformAdmin` |
| **Security posture** | Valued | **P1-06 decides.** `seesPlatformValues` already governs this and is not re-decided here |
| **Open exceptions** | Valued | **P1-06 decides** |
| **Access Reviews** | Their organisation's items | Their organisation's items |
| **System Health** | Valued, with a link | **Withheld, and NO link.** System Health is `PlatformAdmin`; D-145 forbids rendering a destination the viewer cannot open |

**No global-data privilege is invented for a System Administrator anywhere on
this screen.** Where a platform-scoped viewer has no organisation, the honest
answer is **Withheld**, not a global count.

### 5.3 Three states that must never collapse — D-139

| State | Means | Rendered as |
| --- | --- | --- |
| **`0`** | Asked, answered, the answer is none | The number, neutral, no colour |
| **`Withheld`** | This viewer may not be told | `Withheld`, and **no number at all** |
| **`Not available`** | The source could not answer this render | `Not available`, and **no number at all** |

> **A failed source is never a zero.** This is §8's rule and it is restated here
> because `0` and *"we could not ask"* look identical the moment somebody
> renders a default. `sec-metric-count` gets a number or the tile gets a word;
> there is no path that puts `0` in the box for the other two states.

---

## 6. Where the new code lives — ownership is not negotiable

| Class | Module | Owns |
| --- | --- | --- |
| `App\Modules\People\Projection\PeopleSummaryProjection` | **P1-03** | The People queries and their scoping |
| `App\Modules\People\Projection\PeopleSummary` | **P1-03** | The shape |
| `App\Modules\Domains\Projection\DomainSummaryProjection` | **P1-04** | The Domain queries and their scoping |
| `App\Modules\Domains\Projection\DomainSummary` | **P1-04** | The shape |
| `App\Modules\Administration\Support\AdministrationHomeProjection` | **P1-11** | Composition and the derived verdicts, and nothing else |
| `App\Modules\Administration\Http\Controllers\AdministrationHomeController` | **P1-11** | One `show()` |

**Adding a projection during P1-11's implementation does not transfer ownership
to P1-11.** The tests in §11 are what make that true a year from now rather
than only today.

---

## 7. The Action Queue — derivation, in full

**It owns no task.** It is a list of authorised links, derived on every render
from the same objects the tiles above used. **No extra query.**

| Condition | Row | Destination | Authorised for |
| --- | --- | --- | --- |
| `OrganisationService::current() === null` | Organisation is not set up yet | Organisation | OrgAdmin |
| `DomainSummary::$enabledUnowned > 0` | Some enabled business domains have no accountable owner | Business Domains | OrgAdmin |
| `ViewerReport::exceptions()` is not empty | Open security exceptions need review | Security Status | per P1-06 |
| Overdue review count `> 0` | Access reviews are overdue | Access Reviews | per P1-07 |
| System Health is not healthy | Something needs operational attention | System Health | **PlatformAdmin only** |
| Any integration is `not_configured` **and** `required`, or is `degraded` / `unavailable` | An integration needs attention | Platform Integrations | **PlatformAdmin only** |

**Never a row for:**

- `0 users`, `0 groups`, `0 domains` — **D-180 says this in as many words**;
- an optional integration that is simply `not_configured` — that is the correct
  state of a deployment that does not use it, and P1-10 spent a whole gate
  round making *Not configured* mean exactly that;
- a carried gate. **A tile may show a gate's state; showing it closes nothing**,
  and an action queue cannot ask somebody to close one.

**The queue never renders a row whose destination the viewer cannot open** —
D-145. **Every destination re-authorises on arrival**; a link is a suggestion,
not a grant.

**Empty is a real state and is rendered as one** — *"Nothing needs your
attention."* That is a good outcome and the screen should say so rather than
showing an empty box.

---

## 8. Source-failure isolation

**One source failing must not fail the page, and must not become a zero.**

```php
// The shape, per source. Never a bare try/catch that returns 0.
try {
    $summary = $this->people->for($viewer, $organisationId);
} catch (Throwable) {
    $summary = PeopleSummary::unavailable();   // valued: false, counts null
}
```

| | |
| --- | --- |
| Caught | `Throwable`, per source, at the composition boundary |
| Produces | The source's own **`unavailable()`** state — counts `null`, tile renders **Not available** |
| Never produces | `0`, `Ready`, `healthy`, or a missing tile |
| Logged | Nothing new. **No new `SecurityEventLogger` key** — rendering a summary records nothing, and D-160's catalogue stays at 15 |
| Action Queue | A source that could not answer **contributes no row**. An unanswerable question is not an action |

> **The direction of this rule matters more than the rule.** A dashboard that
> renders `0 open exceptions` because the posture evaluator threw is worse than
> one that renders nothing: it is confidently wrong about security, and it is
> wrong in the reassuring direction.

---

## 9. Query and render budget — D-140

### 9.1 The budget

| Source | Evaluations | Queries | Note |
| --- | --- | --- | --- |
| Organisation | 1 | **1** | `current()` |
| Users & Groups | 1 | **3** | active users, inactive users, active groups — three `count()`s, or one grouped aggregate. **No row is loaded** |
| Business Domains | 1 | **2** | enabled count; enabled-without-current-owner count |
| Platform Integrations | 1 | **≤ 3** | `SetupProjection::all()` — one configuration read, one secret-presence read, one platform-settings read. **`all()` once, not `forFamily()` four times** |
| Security posture **and** exceptions | **1** | per `PostureEvaluator` | **One evaluation, two tiles** |
| Access Reviews | 1 | **2** | visible count; visible-and-overdue count |
| System Health | 1 | per `SystemHealthReport` | Local only |
| Action Queue | — | **0** | Derived from the objects above |

**Target: ≤ 2 s normal production response — D-140.**

### 9.2 The three rules behind the numbers

**No N+1, structurally.** Every People, Domain and Review figure is an
aggregate — `count()` — and **no model row is loaded to be counted**. The
Domains figure uses `whereDoesntHave('currentOwnership')`, a single correlated
subquery, not a loop over domains asking each one.

**Evaluated once where several tiles consume it.** P1-06 is the case that
matters: posture and exceptions are two tiles and **one** `evaluate()` →
`for()`. P1-10 is the same shape at lower cost: `all()` once.

**Zero external network calls.** No `semantiq:health`, no `EntraDiscovery`, no
connection tester, no AI or Fabric provider, no SMTP. §11 asserts each absence
by name.

**No cache — D-141.** A stale summary is the failure this unit is most exposed
to. P1-09 established that a cached answer needs an age beside it to stay
honest, and this screen's answer is *"right now"*.

---

## 10. The screen

### 10.1 It is a dashboard, and it uses the shared visual language

**No new visual system. No new CSS where the shared UI already supports the
requirement. Tabs are not forced onto a dashboard.**

| Element | Existing class | Already used by |
| --- | --- | --- |
| Page shell | `org-page` | Every System Administration feature |
| Feature header | `org-feature` + `h1` + `p` | Organisation, Integrations, System Health |
| Area grouping | `sys-areas` / `sys-area` / `sys-area-head` | System Health |
| Status badge | `HealthStatusBadge` → `sys-status*` | System Health, Platform Integrations |
| Neutral count | `sec-metric` / `sec-metric-count` / `sec-metric-label` | Security Status |
| Action link | `org-action org-action-quiet` | Integrations, Identity |
| Withheld row | `WithheldRow` treatment | Security Status |

> **`sec-metric-count` already carries D-138 in its own comment** — *"A count is
> a NUMBER, not a status … carrying no semantic colour at all - colouring it
> would make it a finding, which is exactly what an informational metric is
> not."* That is the rule this screen needs, already written, already shipped.

**The only new CSS that may be justified is a responsive tile grid** for the
Readiness row. If `sys-areas`' column layout is acceptable at desktop width,
**no new CSS is added at all** — and that is the preferred outcome. Any grid
that is added uses existing spacing, radius and border tokens and is asserted
by `EveryCssTokenIsDeclared`.

### 10.2 Layout

```
Administration Home
One place to see whether SemantIQ is set up, secure and working.

Readiness
[ Organisation ]  [ Users & Groups ]  [ Business Domains ]  [ Platform Integrations ]

Security
[ Security posture ]  [ Open exceptions ]

Reviews & Operations
[ Access Reviews ]  [ System Health ]

Action Queue
· Some enabled business domains have no accountable owner   → Business Domains
· Access reviews are overdue                                → Access Reviews
```

### 10.3 Responsive, themes, focus

| | |
| --- | --- |
| Desktop (1440) | Readiness four across; Security and Reviews two across |
| ~390px | **One column.** No horizontal page scroll. No mid-word breaking |
| Light and dark | Existing tokens only. Both verified at VERIFY, **on the real screen** |
| Focus | Every link reachable by `Tab` with the shared `2px` ring. No focusable-but-invisible control |
| Accessibility | Each area a `<section>` with `aria-labelledby`. Status never carried by colour alone — the badge's word is always present |

### 10.4 Empty and refusal states

| Situation | Shown |
| --- | --- |
| Nothing configured at all | Every tile **Not configured**, and the Action Queue leads with Organisation. **D-144** |
| Every tile withheld | **The shell renders** with the areas and their withheld tiles. **D-143** — it does not refuse |
| A source failed | That tile reads **Not available**. The rest of the page is unaffected |
| Nothing needs attention | *"Nothing needs your attention."* — not an empty box |

---

## 11. Architecture guards — what stops the seams being bypassed

**These are the DESIGN's most important output.** A rule in a docblock lasts
until the next person is in a hurry; P1-10 proved that twice in one unit.

### 11.1 Extend what exists, do not build a second guard system

**The repository already has this pattern and it is the one to use.**
`PeopleBoundaryTest::test_nothing_outside_people_queries_group_membership()`
walks every source file **outside** `app/Modules/People/` and fails on a model
or table reference. `DomainsBoundaryTest` does the equivalent for Domains.

**P1-11's job is to fall inside the fence those tests already draw, and to
widen it where it does not yet reach.** A new `AdministrationBoundaryTest` that
re-implemented the same scan would be a second guard system — the exact
duplication this unit exists to avoid.

| # | Guard | Where it lives | Mutation that must fail it |
| --- | --- | --- | --- |
| **G1** | No file outside `Modules/People` queries `User`, `Group` or their tables for a **count** | **Extend `PeopleBoundaryTest`** | Add `User::query()->count()` to `AdministrationHomeProjection` |
| **G2** | No file outside `Modules/Domains` queries `BusinessDomain`, `DomainOwnership` or `business_domain_owners` | **Extend `DomainsBoundaryTest`** | Add `BusinessDomain::query()->where(...)` to the projection |
| **G3** | No P1-11 file imports `Illuminate\Database` — no `Builder`, no `DB`, no `Model`; and none references `LegalEntity`, `BusinessUnit`, `Department`, `Team` or `AccessReviewItem` | **NEW — `AdministrationHomeIsAProjectionTest`** | Add `use Illuminate\Support\Facades\DB;` |
| **G4** | The two new projections live under `App\Modules\People` and `App\Modules\Domains` | **NEW**, same file | Move either into `App\Modules\Administration` |
| **G5** | **Neither new projection returns a value when the organisation is null.** Asserted behaviourally — call `for($viewer, null)` and require `valued === false` with every count `null` | **NEW — feature test per module, owned by that module** | Make `for()` drop the scope when `$organisationId === null` |
| **G6** | The two DTOs' public properties are **exactly** the declared set | **NEW**, reflection, the shape this project already uses in `NoSecretReachesTheBrowserTest` | Add `public array $userNames` |
| **G7** | **No network name appears anywhere in P1-11** — `EntraDiscovery`, `ConnectionTester`, `semantiq:health`, `Http::`, `->inspect(`, `->report(` | **NEW**, and modelled on `ConnectionTestsAreNotCapabilitiesTest` | Call `HealthInspector::inspect()` instead of `inspectLocal()` |
| **G8** | **P1-06 is evaluated once per render** — assert `evaluate()` is called exactly once for a request | **NEW**, feature test with a spy | Call `evaluate()` again for the exceptions tile |
| **G9** | The Administration route set is **exactly** `GET console/administration` | **NEW**, the equality shape `IdentityIsNotWritableOnTheConsoleTest` uses | Add any second verb |
| **G10** | **No new `SecurityEventLogger` key.** `ALLOWED_KEYS` stays **15** | **`NoSecretReachesTheBrowserTest` already asserts the catalogue** | Add one |
| **G11** | **No migration is added by this unit** | **`NoBusinessSchemaTest` / the existing migration guards** | Add one |
| **G12** | **The Action Queue renders no row whose destination the viewer cannot open** | **NEW**, feature test across both administrator kinds | Emit the System Health row for an Organisation Administrator |
| **G13** | **A failed source renders Not available, never `0` or a positive state** | **NEW**, feature test forcing each source to throw | Return `new PeopleSummary(true, 0, 0, 0)` from the catch |
| **G14** | Every CSS token used is declared; both themes readable | **`EveryCssTokenIsDeclaredTest`, `ReadableInBothThemesTest`** — already exist | Use an undeclared token in any new grid |
| **G15** | The navigation node is a leaf in first position and nothing else moved | **`NavigationPresentationTest`** — already exists | Reorder the area |

### 11.2 Every guard is broken deliberately

**Per `CLAUDE.md` §2, each of the fifteen ships with its mutation run and
recorded in `P1-11-MUTATIONS.md`.** A guard that has not been broken
deliberately is a guard nobody has checked — and this unit has three examples
in living memory of an assertion that passed for a reason unrelated to its
claim.

**Watch for these three specifically**, because they are the shapes this
project keeps producing:

- **a guard satisfied by a comment.** P1-10's tab-class guard searched the file
  for `org-tabs` and found it in the docblock explaining why the component uses
  `org-tabs`. G3 and G7 must match **code**, not prose;
- **a guard whose premise was never established.** P1-10's description guard
  passed because nothing in the fixture had been tested, so the mutant fell
  through to the correct answer. G5 and G13 must set up the condition they
  claim to test;
- **a guard the framework was quietly enforcing.** Twice in P1-10 a rule was
  actually being held up by `ConvertEmptyStringsToNull`. G5's null-organisation
  case must be true because the projection decides it, not because something
  upstream never passes null.

---

## 12. SCHEMA — NONE

**No table, no column, no migration.** Every fact on this screen is derived from
data another unit already stores.

The two candidates were considered and both are refused:

| Candidate | Why not |
| --- | --- |
| A readiness-state table | It would store a **judgement about** existing data, which goes stale the moment the data changes and is then wrong in the most convincing possible way |
| An action-queue table | D-135. Assignment, state and completion are three models that do not exist, and the queue owns no task |

**If EXECUTE finds a requirement that genuinely needs storage, it is a blocker
to be raised — not a table to be added.**

---

## 13. Negative and security matrix

| # | Case | Expected |
| --- | --- | --- |
| **N1** | No session | Redirect to sign-in. No props |
| **N2** | Signed in, no role at all | Refused to `auth.access-denied`, the shape every console route already uses |
| **N3** | Organisation Administrator | **200.** Own organisation valued; Platform Integrations and System Health **Withheld**; **no System Health link** |
| **N4** | System Administrator, no organisation resolvable | **200.** People and Domains **Withheld** — **never a global count** |
| **N5** | Inactive user with a role | Refused. `isActive()` is checked by the existing middleware and by the projections |
| **N6** | Every tile withheld | The shell renders — **D-143** |
| **N7** | Posture evaluation throws | Posture and exceptions read **Not available**; every other tile is unaffected; **no Action Queue row** |
| **N8** | `SetupProjection` throws | Integrations reads **Not available**. Sign-in state is **not** substituted from elsewhere |
| **N9** | Page source inspection | **No secret, no email, no person name, no business-domain record.** Integration secrets are booleans upstream and are not forwarded at all |
| **N10** | An enabled domain with no owner | **Needs attention**, and one Action Queue row |
| **N11** | A deployment with 0 groups | **`0`**, neutral, **and no Action Queue row** — D-180 |
| **N12** | A carried gate is open | A tile may reflect the underlying state. **No row asks anybody to close a carried gate** |

---

## 14. Blockers and decisions for the Product Owner

### 14.1 **BLOCKER — Administration Home is invisible to an Organisation Administrator**

**Established from the code, not assumed.** §2 has the full finding. In short:
the route is `OrgAdmin` per D-132, and the only navigation authorizer in the
application shows System Administration nodes **to System Administrators
only**. An Organisation Administrator would be authorised for the home screen
and unable to find it.

**This is pre-existing** — it is already true of Organisation, Users & Groups,
Business Domains and Roles & Access — **but P1-11 is the screen where it stops
being tolerable**, because a home page nobody can navigate to is not a home
page.

| Option | Consequence |
| --- | --- |
| **(a)** Make the route `PlatformAdmin` | Matches today's navigation exactly, contradicts **D-132**, and narrows the audience the blueprint names — *"platform/organisation administrator"* |
| **(b)** Extend the navigation authorizer so an `OrgAdmin` sees the nodes they can actually reach | Correct, and **wider than P1-11** — it changes what four existing screens' menu entries do. It is a navigation change and **D-19 would need re-examination** |
| **(c)** Ship `OrgAdmin` as D-132 says and accept that only System Administrators see the menu item this release | Honest, and leaves a known discoverability gap on the home screen — **recorded as a carried item rather than hidden** |

**Recommendation: (c) for Release 1, with the gap recorded as a carried item**,
and **(b) raised as its own small unit** rather than smuggled into a dashboard.
**(a) is the one to refuse** — it would reverse an approved decision to match an
implementation detail.

**This is a Product Owner ruling. EXECUTE must not start without it.**

### 14.2 Not blockers, but the DESIGN states its position

| | Position |
| --- | --- |
| **New CSS** | **Preferred: none.** A responsive tile grid is the only candidate, and only if the shared column layout reads badly at desktop width. Decided at DESIGN-review or by looking, not by assuming |
| **Module name** | `App\Modules\Administration` — a new module directory for one controller and one projection. The alternative, putting it in `Platform`, would make `Platform` the home of a feature |
| **Empty-queue wording** | *"Nothing needs your attention."* Proposed, not approved |

---

## 15. Product Owner test script outline — **maximum 8 checks**, D-146

**To be written in full at VERIFY. Against genuine normal production data —
no fabricated exception, no invented overdue review, no placeholder credential.**

| # | Check | Confirms |
| --- | --- | --- |
| **1** | Administration Home follows the shared layout | Title, description, four areas in order, the same shell, typography, spacing, cards and badges as Organisation and System Health |
| **2** | Readiness tells the truth about this deployment | Organisation **Configured**; Users & Groups neutral counts; Business Domains **Ready / Needs attention / Not configured**; Platform Integrations the four families as the Integrations screen shows them |
| **3** | The dashboard agrees with the screens it summarises | Open each linked screen and confirm the number matches. **A roll-up that disagrees is worse than none** |
| **4** | Security and Reviews & Operations | Posture aggregate, exception count, overdue reviews, System Health — each matching its own screen |
| **5** | The Action Queue is real | Every row is something that genuinely needs attention, every link opens, and **nothing is listed that is merely zero** |
| **6** | Nothing is invented | No count where a value is withheld; no `0` where the answer is *"not available"*; no link to a screen you cannot open |
| **7** | Responsive, light and dark | Desktop and ~390px, both themes, keyboard focus, no sideways scroll, no console errors |
| **8** | Nothing else moved | Sign-in, `/console`, and the previously accepted System Administration screens unchanged; Administration Home first in the sidebar |

**Expected to be carried, not run:** anything needing a second permanent
administrator, a fresh installation, real SMTP, or the Entra cutover. **None of
the nine carried gates is closed by this unit.**

---

## 16. Status

**DESIGN ONLY. AWAITING PRODUCT OWNER DESIGN REVIEW.**

**No implementation. No schema. No deployment.**

**One blocker: §14.1**, the navigation-authority conflict with D-132. It needs a
ruling before EXECUTE.

**D-130 – D-147 and D-179 – D-181 are in force and none is reinterpreted here.**

**All nine carried items remain OPEN.** P1-11 may display an already-
authoritative state where appropriate and **must not treat display as
resolution**. Specifically **not fixed inside P1-11**: the production session
driver, per-user session revocation, the P1-02 second-administrator re-check,
the Bootstrap live gates, the real SMTP observation and the Microsoft
privileged step-up observation.

**P1-11 acceptance will close P1-11 only** — D-147.
