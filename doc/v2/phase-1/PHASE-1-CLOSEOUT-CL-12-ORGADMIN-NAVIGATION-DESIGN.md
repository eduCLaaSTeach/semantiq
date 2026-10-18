# Phase 1 Closeout — CL-12: Organisation Administrator navigation

> # ⚠️ AMENDED — READ §0 FIRST
>
> **The design below §0 describes the FIRST Product Owner ruling**, which was
> implemented in #147, refined in #148, deployed, and is correct for what it
> was asked. **The Product Owner then clarified the role model itself**, and
> the current target is §0. The original is kept in full and deliberately not
> rewritten: it was a decision, not a mistake, and erasing it would hide that
> the model changed rather than that somebody got it wrong.

---

## 0. THE AMENDED RULING — CURRENT AND BINDING

| | |
| --- | --- |
| Baseline | `main` = **`7be3afd6a80edbb04409ef8e06c5e9a9a7d34ea9`** |
| Supersedes | the eight-node model in §1–§12 below |
| Ruled | Organisation Administrator receives **full current administration access** |

### 0.1 The model

**There are two ADMINISTRATION areas and one BUSINESS area.**

| Area | Kind | System Administrator | Organisation Administrator |
| --- | --- | --- | --- |
| **Fabric Configuration** | administration | ✅ | ✅ **(new)** |
| **System Administration** | administration | ✅ | ✅ **all 11 (new)** |
| **SemantIQ Workplace** | business | ✅ | ❌ **stays out** |

**SemantIQ Workplace is governed separately by business access.** Administration
authority is not business-data authority, and an administrator does not receive
the business area for being one. No business-access navigation mechanism exists
yet; building one is **not** in CL-12 and is carried as its own unit.

### 0.2 What changed, and what deliberately did not

**CHANGED — one line in `RoleCatalogue`.** Organisation Administrator now holds
`PlatformAdmin`, `OrgAdmin`, `AccessAdmin`, `EvidenceRead`. Identity & SSO,
System Health and Integrations are therefore genuinely theirs to open rather
than shown-and-refused.

**This widens a real authorisation, and the amended Gate B package said so
before it was approved:** an Organisation Administrator can now write the Entra
client secret and the deployment-wide integration credentials for the identity,
email, ai and fabric families. The Product Owner chose that knowing the
consequence.

**NOT CHANGED, and each is asserted rather than assumed:**

- `BusinessData` is **absent** — administration authority is still not
  business-data authority;
- `grantableBy(OrganisationAdministrator)` still **excludes**
  `system_administrator` — the privilege-escalation path stays closed;
- `requiringStepUp()` is unchanged;
- the System Administrator role definition is unchanged;
- the two role codes are **not** collapsed;
- no route, `ActionClass`, `RoleCode`, `AccessEngine`, schema, frontend
  component or deployment workflow was touched.

**The two roles now hold the same four action classes.** What still separates
them is **grantability** and **navigation**: a System Administrator sees the
whole roadmap including SemantIQ Workplace; an Organisation Administrator sees
the two administration areas.

### 0.3 What the Organisation Administrator sees — 2 areas, 25 nodes

**Fabric Configuration — 14 nodes, every one LOCKED.** Overview · Data Sources ·
Connect Source · Discovery · Data Classification · Ingestion · Data Quality ·
Business Model · Security Mapping · Semantic Model · AI Readiness · Pipelines &
Refresh · Power BI Publication · Monitoring.

They share one policy key, `fabric.view`. **None has a route, controller, table
or service.** Showing the area grants nothing — there is nothing behind it to
reach — and `route === null` is asserted per node so that a future route cannot
quietly turn a roadmap entry into access.

**System Administration — all 11 nodes, every one DELIVERED.** Administration
Home · Organisation · Users & Groups · Roles & Access · Business Domains ·
Identity & SSO · Security Status · Access Reviews · Audit · System Health ·
Integrations.

### 0.4 A finding worth recording: nothing in the Administration module changed

`AdministrationHomeProjection::seesPlatformValues()` asks `AccessEngine` with
`ActionClass::PlatformAdmin` — **the same decision `RequireActionClass` makes**,
not a second copy of the rule. Administration Home therefore followed the
`RoleCatalogue` amendment with **no production change at all**: the platform
tiles stopped being withheld, and the platform rows entered the Action Queue, on
their own.

Six existing tests failed on the amendment. **Every one of them was a test
encoding the old model, not a defect** — and three of them are worth naming:

- the G16 **zero-call** guard ("authorise before evaluating") named the
  Organisation Administrator as the withheld viewer. After the amendment **no
  role that can reach Administration Home is withheld**, so the guard was
  repointed to an Auditor and driven **at the projection** rather than over
  HTTP — a zero obtained through a refused route would be a page that never
  rendered;
- the cost half of the same guard was repointed the same way;
- `test_a_deployment_with_nothing_to_do_says_so` asserted an **empty** Action
  Queue and passed only **because the Organisation Administrator could not see
  the unconfigured required integration**. The deployment never had nothing to
  do. It now asserts the queue is **exactly** the outstanding item, which fails
  both if an action leaks in and if that row disappears.

**The withheld branch is now unreachable over HTTP for every current role.** It
is kept and kept tested, because it is the guard that holds if a future role is
given `OrgAdmin` without `PlatformAdmin`.

### 0.5 The implementation

A **12-key allow-list** in `SystemAdministratorNavigationAuthorizer` —
`fabric.view` plus the 11 System Administration keys — compared strictly and
still gated on the role. The class is **not renamed** (Product Owner ruling: a
rename is dependency churn with no product value at this gate), and the
request-local memoisation from #148 is **preserved exactly**: measured at **2**
role lookups for a System Administrator and **3** for an Organisation
Administrator, unchanged from #148 despite the list growing from 8 keys to 12
and the rendered node count from 8 to 25.

**The list is hand-written and cannot go stale.**
`test_the_allow_list_is_exactly_the_two_admin_areas` derives the same set from
`ApprovedMenu` and fails if the two ever diverge. That test is what makes
writing the list out by hand safe.

**Visibility is still derived from nothing.** Computing it from each route's
`ActionClass` remains wrong and is deliberately not done: an **Auditor** holds
`EvidenceRead`, so a class-derived rule hands them Security Status, Access
Reviews and Audit as a side effect of a change about administrators. The role is
checked **first**. Mutation X7 proves it — killed by the Auditor equality.

### 0.6 Disclosure: the role picker was telling administrators the opposite

**Raised as a P1 on #149, and correct.** `RoleCode::OrganisationAdministrator`
read:

> Organisation, people, groups, domains and access administration. **No platform
> authority** and no business data.

`AccessController::roleOptions()` sends `summary()` straight to the Roles &
Access picker, so that sentence is what somebody reads **at the moment they
decide to delegate the role**. After the amended ruling it promised *no platform
authority* while delegating Identity & SSO, System Health and Integrations —
including the Entra client secret and the deployment-wide integration
credentials.

**A summary that understates a role is worse than no summary: it does not merely
fail to inform, it actively reassures.**

The approved wording:

> Full organisation and platform administration, including Identity & SSO,
> System Health and Integrations. No business data. Cannot grant System
> Administrator.

**This is disclosure and UX alignment, NOT another authorisation change.** No
action class, route, navigation definition, schema, frontend component or
workflow moved; the correction is one string plus tests, and the tests assert
the authorisation is unchanged alongside the wording.

**The guard is tied to the grant, not just to the text.**
`test_no_role_summary_denies_an_authority_the_role_actually_holds` walks every
role and fails if a summary and `RoleCatalogue` disagree about platform or
business authority **in either direction** — so the next action-class change
cannot leave the disclosure behind the way this one did. Three mutations were
run and all three caught, including one that corrects the enum but has the
screen send a stale copy; only the rendered-props test kills that one.

**The P1-05 design document still carries the old wording** and is deliberately
left alone — it recorded the model correctly as it then stood, and rewriting it
would hide that the model changed.

---

### 0.6 Gate D is superseded

The eight-check script written against the first ruling must **not** be used.
Its expectations — one product area, exactly eight nodes, Identity & SSO /
System Health / Integrations necessarily hidden — are all now wrong. A revised
script covering two areas, 25 nodes and the locked Fabric entries replaces it.

---

## THE ORIGINAL GATE B DESIGN — SUPERSEDED, PRESERVED


## WS-7 · GATE B — DESIGN ONLY

**No code in this task.** No implementation, no production change, no
deployment, no Phase 2. CL-10 is untouched and `SESSION_DRIVER` is unchanged.

| | |
| --- | --- |
| Baseline | `main` = **`5f2e8d8a7f5408b14d746325d05d1a44c01378c5`** |
| Workstream | **WS-7**, which has **no dependency on WS-1 or WS-2** |
| Register item | **CL-12** — disposition **FIX IN CLOSEOUT — expose all seven** |
| Gate | **B — DESIGN.** Implementation needs a separate approval |
| Discrepancies found | **None.** All seven are already route-authorised — §3 |

---

## 1. Purpose and problem statement

**An Organisation Administrator is authorised for seven System Administration
screens and can discover one of them.**

Every one of the seven re-authorises on arrival and lets them in. Typed into the
address bar, they return 200. In the sidebar, six of the seven are absent — so
the only way to reach them is to already know the URL.

`CLAUDE.md` §4 names this exact failure as the last item on the
professional-polish gate: **"navigation that exists technically but the user
cannot actually discover."** It is not an access defect. Nothing is
under-protected and nothing leaks. It is a discoverability defect, and the fix
is a presentation change.

### Why it looks like this today

D-182 (P1-11) granted **one** node — Administration Home — to the Organisation
Administrator, because a landing page nobody can navigate to is not a landing
page. It was deliberately narrow:

> "SO THE EXCEPTION IS ONE POLICY KEY, FOR ONE REASON: the route behind it
> already admits the viewer. It is not a principle that generalises itself to
> the next node; the next node needs its own ruling."
> — `SystemAdministratorNavigationAuthorizer`

**CL-12 is that ruling.** The reasoning D-182 used for one node is now applied,
by explicit Product Owner decision, to the six others that satisfy the same
test: *the route behind it already admits the viewer.*

### The historical record is preserved, not rewritten

**P1-11 accepted the then-current behaviour and that acceptance stands.** At the
time, OrgAdmin seeing only Administration Home was correct, deliberate, and
recorded as a carried navigation item with its own evidence to gather. The
acceptance record, D-182's wording, and the test that asserts the narrow
behaviour are **historically correct for the state they describe**.

CL-12 **prospectively supersedes** the navigation exception from the date of its
approval. Nothing in P1-11's acceptance is edited, reversed or deleted; the
superseding ruling is recorded beside it, in the pattern the Closeout PLAN uses
for every superseded statement.

---

## 2. Current-state evidence

### 2.1 The navigation seam — a single server-side filter

| Layer | File | Role |
| --- | --- | --- |
| Menu definition | `app/Shared/Navigation/ApprovedMenu.php` | Every node, its label, icon, route name and **policy key** |
| Node invariants | `app/Shared/Navigation/NavigationNode.php` | Leaf / locked / group; a locked node may not hold a route |
| Filter | `app/Shared/Navigation/NavigationRegistry.php` | `visibleFor()` drops any node whose policy key the authorizer refuses |
| **Visibility predicate** | `app/Modules/Organisation/Support/SystemAdministratorNavigationAuthorizer.php` | **The one decision point.** Admits System Administrators, plus the single D-182 key |
| Share | `app/Http/Middleware/HandleInertiaRequests.php` | `'productAreas' => fn () => app(NavigationRegistry::class)->visibleFor()` |
| Render | `resources/js/Layouts/AppShell.jsx` | Renders what it is given |

**`AppShell.jsx` contains no role logic.** Its own header states it: *"Product
areas arrive already filtered by the server."* The client cannot widen or
narrow the menu, so **no frontend change is required by CL-12.**

### 2.2 The predicate as it stands

```php
public const ORGANISATION_ADMINISTRATOR_EXCEPTION = 'administration.view';

public function allows(string $policyKey): bool
{
    $user = request()->attributes->get('semantiq_user');

    if (! $user instanceof User || ! $user->isActive()) {
        return false;
    }

    if ($this->engine->holdsRole($user, RoleCode::SystemAdministrator)) {
        return true;
    }

    return $policyKey === self::ORGANISATION_ADMINISTRATOR_EXCEPTION
        && $this->engine->holdsRole($user, RoleCode::OrganisationAdministrator);
}
```

One key, one role. Everything else returns `false`.

---

## 3. Route authorisation evidence — all ten destinations

**The question CL-12 must answer before anything else: is each of the seven
already authorised for an Organisation Administrator through existing approved
permissions?** Read from source, not assumed.

### 3.1 The action-class grant matrix

`app/Modules/Access/Support/RoleCatalogue.php` — `MATRIX`:

```php
RoleCode::OrganisationAdministrator->value => [
    ActionClass::OrgAdmin,
    ActionClass::AccessAdmin,
    ActionClass::EvidenceRead,
],
```

**An Organisation Administrator holds `OrgAdmin`, `AccessAdmin` and
`EvidenceRead`. They do NOT hold `PlatformAdmin`.**

### 3.2 Every destination, its route, its gate, and the verdict

Gates read from `routes/web.php`; every route is wrapped in
`RequireActionClass` with the class named.

| # | Destination | Route name | Required action class | OrgAdmin holds it | Verdict |
| --- | --- | --- | --- | --- | --- |
| — | **Administration Home** | `administration.home` | `OrgAdmin` | **Yes** | **Already authorised** — already visible (D-182) |
| 1 | **Organisation** | `organisation.profile` | `OrgAdmin` | **Yes** | **Already authorised** |
| 2 | **Users & Groups** | `people.users` | `OrgAdmin` + `RequireOrganisation` | **Yes** | **Already authorised** |
| 3 | **Roles & Access** | `access.index` | `AccessAdmin` + `RequireOrganisation` | **Yes** | **Already authorised** |
| 4 | **Business Domains** | `domains.index` | `OrgAdmin` + `RequireOrganisation` | **Yes** | **Already authorised** |
| 5 | **Security Status** | `security.baseline` | `EvidenceRead` | **Yes** | **Already authorised** |
| 6 | **Access Reviews** | `access-reviews.privileged` | `EvidenceRead` + `RequireOrganisation` | **Yes** | **Already authorised** |
| 7 | **Audit** | `audit.user-access` | `EvidenceRead` + `RequireOrganisation` | **Yes** | **Already authorised** |
| — | **Identity & SSO** | `identity.entra` | **`PlatformAdmin`** | **No** | **Correctly denied — stays hidden** |
| — | **System Health** | `system-health.show` | **`PlatformAdmin`** | **No** | **Correctly denied — stays hidden** |
| — | **Integrations** | `integrations.show` | **`PlatformAdmin`** | **No** | **Correctly denied — stays hidden** |

> ### ✅ NO DISCREPANCY. NO ESCALATION REQUIRED.
>
> **All seven requested destinations are already route-authorised** for an
> Organisation Administrator through permissions approved in P1-02 … P1-08.
> **No authorisation needs to be widened, and none will be.**
>
> The three that must stay hidden are gated on `PlatformAdmin`, which an
> Organisation Administrator does not hold. They are denied by the existing
> authorisation model and would remain denied even if the menu were wrong —
> which is the property that makes a menu-only change safe.

**`RequireOrganisation` is not a role gate.** It requires an organisation record
to exist, and applies identically to System Administrators today. CL-12 does not
touch it, and its behaviour on these routes is unchanged.

---

## 4. The target state

### 4.1 Visible to an Organisation Administrator — exactly eight nodes

| Order | Node | Policy key | Why visible |
| --- | --- | --- | --- |
| 1 | **Administration Home** | `administration.view` | D-182, already in force |
| 2 | **Organisation** | `organisation.view` | `OrgAdmin` |
| 3 | **Users & Groups** | `people.view` | `OrgAdmin` |
| 4 | **Roles & Access** | `access.view` | `AccessAdmin` |
| 5 | **Business Domains** | `domains.view` | `OrgAdmin` |
| 6 | **Security Status** | `security.view` | `EvidenceRead` |
| 7 | **Access Reviews** | `access-reviews.view` | `EvidenceRead` |
| 8 | **Audit** | `audit.view` | `EvidenceRead` |

**Administration Home is retained and stays first.** It remains the Organisation
Administrator's landing point; CL-12 adds siblings beneath it and changes
nothing about it.

### 4.2 Hidden from an Organisation Administrator — exactly three nodes

| Node | Policy key | Why hidden |
| --- | --- | --- |
| **Identity & SSO** | `identity.view` | `PlatformAdmin` — not held |
| **System Health** | `system-health.view` | `PlatformAdmin` — not held |
| **Integrations** | `integrations.view` | `PlatformAdmin` — not held |

### 4.3 The two other product areas stay hidden

`workplace.view` (Phase 3) and `fabric.view` (Phase 2) remain refused for an
Organisation Administrator, exactly as today. **No Phase 2 or Phase 3 menu is
introduced.** `NavigationRegistry::visibleFor()` drops an area holding no
visible node, so an Organisation Administrator continues to see **one area:
System Administration**.

### 4.4 Grouping and order — existing conventions, no reordering

The eight visible nodes render in **`ApprovedMenu`'s existing declaration
order**, which `NavigationRegistry` iterates directly. Removing the three hidden
nodes from the full System Administration list yields §4.1 **exactly, with no
reordering, no regrouping and no new group**.

Labels, icons and the `ProductArea` cluster are unchanged. System Administration
keeps `expandedByDefault() === true`, so an Organisation Administrator arrives
with their one area open.

---

## 5. Impact assessment

| Area | Impact |
| --- | --- |
| **Roles** | **NONE.** No role added, removed or redefined |
| **Permissions / action classes** | **NONE.** `RoleCatalogue::MATRIX` unchanged. No grant widened |
| **Routes** | **NONE.** No route added, removed, renamed or re-gated |
| **Route authorisation** | **NONE.** Every `RequireActionClass` declaration unchanged |
| **Organisation scoping** | **NONE.** `RequireOrganisation` untouched |
| **Sensitivity / security rules** | **NONE** |
| **Database** | **NONE.** No migration, no schema change, no data change |
| **Audit** | **NONE.** No new audited event. Visibility is not an action and writes nothing |
| **Backend code** | **ONE presentation file** — the navigation authorizer. No permission, middleware, route, model or schema file changes |
| **Frontend code** | **NONE.** The server filters; `AppShell.jsx` renders what it receives |
| **Phase 2 / Phase 3** | **NONE.** Untouched |

**"Backend impact: none" is stated precisely rather than loosely.** One backend
file changes, because the navigation predicate lives in PHP. What does *not*
change is any authorisation, permission, route or schema. The distinction
matters: a reader who takes "no backend change" literally and then sees a `.php`
file in the diff is entitled to doubt the rest of this document.

---

## 6. Security impact

**The change makes nothing reachable that was not already reachable.**

| Property | Before | After |
| --- | --- | --- |
| Seven routes admit OrgAdmin | Yes | Yes — unchanged |
| Three PlatformAdmin routes deny OrgAdmin | Yes | Yes — unchanged |
| Menu filtering is the access control | **No** | **No** |
| Routes re-authorise independently | Yes | Yes — unchanged |

The blueprint's rule holds throughout: **menu hiding is never the access
control.** `NavigationAuthorizer`'s own contract states that the route must
continue to refuse even if the authorizer wrongly allowed a node through.
Filtering the menu and authorising the request remain two separate code paths.

**The role check stays.** The widened set is gated on
`holdsRole(OrganisationAdministrator)`, not on the policy key alone.

### 6.1 The design decision that matters, and the alternative that was rejected

An apparently more principled implementation would derive visibility from the
action class each route requires. **It is rejected, because it widens the menu
for a role nobody asked about.**

An **Auditor** holds `EvidenceRead`. Under a class-derived rule they would
immediately gain Security Status, Access Reviews and Audit in the sidebar — a
change outside CL-12's approved scope, arriving as a side effect. The existing
`AdministrationHomeNavigationTest` V3 case exists precisely because an Auditor
holds real console authority and is still not D-182's subject.

**So CL-12 keeps an explicit allow-list of policy keys, gated on the OrgAdmin
role.** It mirrors an authorisation fact that §3 proves; it does not try to
re-derive it. Auditor, Executive, Domain Owner, Manager, Business User and
unauthenticated viewers are unaffected.

### 6.2 What stays a carried item

CL-12 closes the **discoverability** gap for seven screens. It does not claim to
close CL-01, and it creates no new carried item.

---

## 7. Accessibility and responsive considerations

No new component, pattern or interaction is introduced, so the existing shared
shell behaviour carries over unchanged. Verification must still **observe** it
rather than assume it:

| Concern | Expectation |
| --- | --- |
| Sidebar collapse / expand | `AppShell` already ships a collapse control with `sr-only` labels ("Expand navigation" / "Collapse navigation"). Unchanged |
| Small-screen navigation | The eight nodes use the same row component as every other node. A longer list must remain scrollable and reachable at small-screen width |
| Keyboard | Each node is a standard link in source order; tab order follows the rendered order in §4.1 |
| Focus | Existing shell focus styles apply unchanged |
| Icons | Existing per-node icons from `ApprovedMenu`; all eight already have one, enforced by `NavigationNode` |
| Themes | No new colour or token. Light and dark unchanged |
| Active state | Existing active-link treatment now applies to seven more destinations for this role |

**The one genuinely new condition is list length.** An Organisation
Administrator's sidebar goes from one row to eight. That is still far shorter
than a System Administrator's full roadmap, which is already the tested worst
case, so no new overflow behaviour is expected — but small-screen rendering is
an explicit check in both §8 and §9 rather than an assumption.

---

## 8. Implementation seam and expected changes

### 8.1 Files expected to change

| File | Change |
| --- | --- |
| `app/Modules/Organisation/Support/SystemAdministratorNavigationAuthorizer.php` | Replace the single-key exception with an explicit **eight-key allow-list**, still gated on the OrgAdmin role. Rewrite the class docblock to record CL-12 superseding the narrow D-182 presentation rule |
| `tests/Feature/Administration/AdministrationHomeNavigationTest.php` | **V4 changes meaning.** Its equality becomes the eight approved labels. Its historical rationale is retained and marked superseded, not deleted |
| `tests/Feature/.../OrganisationAdministratorNavigationTest.php` *(new, name to be settled at Gate C)* | The CL-12 acceptance assertions in §9 |
| `doc/v2/phase-1/PHASE-1-CLOSEOUT-PLAN.md` | Record CL-12 progress at WS-7 |
| `doc/v2/phase-1/P1-11-ADMINISTRATION-HOME-ACCEPTANCE.md` | Add a **superseding note** beside the navigation exception. **The original acceptance text is not edited** |

### 8.2 Files that must NOT change

`routes/web.php` · `RequireActionClass.php` · `RoleCatalogue.php` ·
`RoleCode.php` · `ActionClass.php` · `AccessEngine` · any migration ·
`ApprovedMenu.php` · `NavigationRegistry.php` · `NavigationNode.php` ·
`AppShell.jsx` · anything under `deployment/` or `.github/workflows/`.

**An implementation diff touching any of these is out of scope and should be
refused at Gate C**, because every one of them would mean the change had stopped
being a presentation correction.

### 8.3 Shape of the change

The allow-list is expected to name the **policy keys `ApprovedMenu` already
declares**, in the manner the existing single-key constant does — so the menu
and the exception cannot drift into two different strings. Exact constant naming
and placement are an implementation detail for Gate C.

---

## 9. Automated test plan

### 9.1 The nine required acceptance assertions

| # | Assertion | Shape |
| --- | --- | --- |
| **A1** | OrgAdmin navigation shows Administration Home **plus exactly the approved seven** | **Equality** over the System Administration labels — not `assertContains` |
| **A2** | OrgAdmin navigation does **not** show Identity & SSO | Explicit `assertNotContains`, named separately so a failure says which |
| **A3** | OrgAdmin navigation does **not** show System Health | As A2 |
| **A4** | OrgAdmin navigation does **not** show Integrations | As A2 |
| **A5** | **System Administrator navigation is unchanged** | Equality over the full roadmap, unchanged from today |
| **A6** | **Existing route authorisation is unchanged** | Assert each of the ten routes still declares the same `RequireActionClass`; assert `RoleCatalogue::MATRIX` for OrgAdmin is exactly `[OrgAdmin, AccessAdmin, EvidenceRead]` |
| **A7** | Direct access to the **seven** approved routes still **succeeds** for OrgAdmin | Create a valid Organisation Administrator **with the required existing organisation context**, request each of the seven approved destination GET routes, and **`assertOk()` — exact HTTP 200**. **NOT "assert not 403"**: that would pass on a 302, a 404 or a 500, which is three ways to call a broken screen a success. `ConsoleNavigationTest` already uses this stronger pattern |
| **A8** | Direct access to **PlatformAdmin-only** routes still **denies** OrgAdmin | For ordinary **HTML** requests to Identity & SSO, System Health and Integrations, assert a **redirect to `auth.access-denied`**. This is the approved current behaviour, read from source: `RequireActionClass::refuse()` returns **403 only when `$request->expectsJson()`**, and a redirect otherwise. **Do not write a test expecting an HTML 403** — it would fail against correct code |
| **A9** | **No Phase 2 or Phase 3 menu is introduced** | Assert OrgAdmin sees exactly one product area, System Administration |

### 9.2 Additional guards — regressions the nine would not catch

| # | Guard | Why |
| --- | --- | --- |
| **A10** | **Auditor System Administration navigation is exactly `[]`** | **An EQUALITY, not an absence check.** An Auditor holds `EvidenceRead`, so the §6.1 rejected alternative would hand them Security Status, Access Reviews and Audit. A loose `assertNotContains` on one label would miss two of the three; the equality kills all of it. **Kept independent from A11**, so a failure names Auditor specifically rather than dissolving into a loop over five roles |
| **A11** | Executive, Domain Owner, Manager, Business User and an unauthenticated viewer see no System Administration node | The existing V3 case, extended |
| **A12** | An **inactive** Organisation Administrator sees nothing | The inactive check must stay ahead of the role check |
| **A13** | Every policy key in the allow-list is a key `ApprovedMenu` actually declares | Stops the allow-list naming a key that no longer exists, which would be a silently dead entry |
| **A14** | The allow-list contains **no `PlatformAdmin`-gated key** | Stated as an independent property, so adding a fourth key by accident fails loudly |

> ### ⚠️ THREE TEST-PRECISION CORRECTIONS — Product Owner, Gate B approval
>
> **A7, A8 and A10 above are the corrected forms.** The first draft wrote A7 as
> "assert not 403", A8 as "assert the existing refusal behaviour", and A10 as a
> loose absence check. All three were too weak to do their job:
>
> | | Was | Why it failed |
> | --- | --- | --- |
> | **A7** | "not 403" | Satisfied by a 302, a 404 or a 500 |
> | **A8** | "the existing refusal behaviour" | Did not say what that is, inviting a test that expects an HTML 403 — which `RequireActionClass::refuse()` does not return for a browser request |
> | **A10** | loose absence | Would miss two of the three labels the rejected alternative adds |
>
> **An assertion satisfied by any outcome is satisfied by the wrong one** —
> `CLAUDE.md` §2. The corrections are recorded here rather than silently
> applied, because the weakness is the kind this project keeps producing.

### 9.3 Mutations that must be run and observed to fail

Per `CLAUDE.md` §2, each guard must be broken deliberately and the failure
recorded. At minimum:

| # | Mutation | Must be killed by |
| --- | --- | --- |
| M1 | `return true` for any key once OrgAdmin | A1, A2, A3, A4 |
| M2 | Add `identity.view` to the allow-list | A2, A14 |
| M3 | Add `system-health.view` | A3, A14 |
| M4 | Add `integrations.view` | A4, A14 |
| M5 | Drop one of the seven | A1 |
| M6 | Drop `administration.view` | A1 |
| M7 | Derive visibility from action class instead of the allow-list | **A10** |
| M8 | Remove the `holdsRole(OrganisationAdministrator)` check | A10, A11 |
| M9 | Remove the `isActive()` check | A12 |
| M10 | Widen a route's `RequireActionClass` to `OrgAdmin` | A6, A8 |
| M11 | Add `workplace.view` or `fabric.view` | A9 |

**M7 is the mutation this design most expects someone to write**, because it
looks like an improvement. A10 is the only assertion that kills it, which is why
A10 is required rather than optional.

### 9.4 Existing suites that must stay green

`tests/Unit/Navigation/NavigationRegistryTest.php` ·
`tests/Unit/Navigation/ProductAreaOrderTest.php` ·
`tests/Architecture/NavigationPresentationTest.php` ·
`tests/Feature/Organisation/ConsoleNavigationTest.php` ·
`tests/Feature/Access/*` · `tests/Feature/People/PeopleAccessBoundaryTest.php` ·
plus the full suite and the MySQL CI gate.

---

## 10. Product Owner browser verification script

**Eight checks. Real accounts only.** Do not create, promote or modify any
account to run this. If a genuine Organisation Administrator does not exist,
**stop and report it** — a manufactured privileged account is exactly what the
Closeout dispositions forbid.

| # | Step | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| **1** | Sign in as a **genuine Organisation Administrator** | Sign-in succeeds; the console loads | ☐ |
| **2** | Look at the sidebar | **System Administration** is the **only** product area, and it is expanded | ☐ |
| **3** | Read the System Administration list | **Exactly eight** entries, in this order: Administration Home · Organisation · Users & Groups · Roles & Access · Business Domains · Security Status · Access Reviews · Audit | ☐ |
| **4** | Look for the three platform entries | **Identity & SSO, System Health and Integrations are ABSENT** | ☐ |
| **5** | Open **Administration Home**, then **Users & Groups**, then **Audit** | Each opens normally. **No "not authorised" message and no error** | ☐ |
| **6** | Open **Roles & Access** and **Security Status** | Both open normally — these are the `AccessAdmin` and `EvidenceRead` paths | ☐ |
| **7** | Narrow the browser to a phone width and open the navigation | All eight entries are reachable and readable; nothing is cut off or unscrollable | ☐ |
| **8** | Sign in as an **existing genuine System Administrator** | Their sidebar is **unchanged** — all three product areas and the full System Administration list, including Identity & SSO, System Health and Integrations | ☐ |

**Evidence to capture:** a screenshot of the OrgAdmin sidebar (step 3), one of
the small-screen navigation (step 7), one of the System Administrator sidebar
(step 8), and the role of each account used. **No password, token, session
identifier or personal data.**

**Not observable in this script:** whether a *different* Organisation
Administrator in a *different* organisation sees the same set. One organisation
exists in production, so that remains automated evidence only, and is recorded
as such rather than implied by a PASS.

---

## 11. Rollback approach

**Revert the single commit.** The change is one method body in one presentation
file plus its tests.

| | |
| --- | --- |
| Mechanism | `git revert` of the CL-12 implementation commit, then the ordinary deployment |
| Data to restore | **None.** No migration, no schema change, no row written |
| Sessions affected | **None.** No session is invalidated and nobody is signed out |
| Effect of rollback | An Organisation Administrator returns to seeing Administration Home only. **Their route authorisation is unaffected either way** — the seven screens stay reachable by URL, exactly as they are today |
| Maintenance window | **Not required.** Unlike CL-10, this changes no configuration and interrupts nobody |
| Detection | A1 and A5 fail in CI before merge; after deploy, step 3 of §10 shows it in one look |

**Rollback is genuinely cheap here, and that is a property of the design rather
than a claim about it:** because the change grants nothing, undoing it takes
nothing away.

---

## 12. Explicit non-goals

**CL-12 does NOT:**

1. broaden any backend permission, grant or action class;
2. add, remove, rename or re-gate any route;
3. add or redefine any role;
4. change action-class assignments in `RoleCatalogue::MATRIX`;
5. change organisation scoping or `RequireOrganisation`;
6. change sensitivity or security rules;
7. make Organisation Administrator equivalent to System Administrator — **three
   platform destinations stay hidden and three stay denied**;
8. change direct-route authorisation in any direction;
9. alter what any screen *shows* once opened — projections and their scoping are
   owned by their units and are untouched;
10. introduce any Phase 2 or Phase 3 menu entry or functionality;
11. touch CL-10, `SESSION_DRIVER`, maintenance mode or `.env`;
12. start CL-11, which depends on CL-10 being production-verified;
13. rewrite P1-11's historical acceptance.

---

## 13. Status

**GATE B — DESIGN ONLY. AWAITING PRODUCT OWNER APPROVAL.**

| | |
| --- | --- |
| Route-authorisation verification | **COMPLETE — all seven already authorised.** No discrepancy, no escalation |
| Implementation | **NOT STARTED.** No code written |
| Production | **UNCHANGED** |
| CL-10 | **ON HOLD, untouched.** `SESSION_DRIVER` = `file`; `align-session-driver.yml` undispatched |
| CL-11 | **NOT STARTED** — hard dependency on CL-10 being production-verified |
| Phase 2 | **UNTOUCHED** |

**Approval of this DESIGN is not approval to deploy.** Gate C reviews the
implementation and its mutation evidence; acceptance follows the ordinary
PLAN → APPROVE → DESIGN → APPROVE → EXECUTE → TEST → VERIFY → ACCEPT lifecycle.
