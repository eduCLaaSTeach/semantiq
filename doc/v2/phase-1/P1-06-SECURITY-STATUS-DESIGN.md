# P1-06 — Security Status: DESIGN

**How the posture screens are actually built: one evaluator, one projection, four
read-only screens — and the structural reasons a hidden value cannot leak, a
count cannot become a state, and nothing can report Healthy because nobody
looked.**

| | |
| --- | --- |
| Unit | **P1-06 — Security Status** |
| PLAN | `doc/v2/phase-1/P1-06-SECURITY-STATUS-PLAN.md` — **Product Owner approved 16 September 2026**, merge `4d93228c1aa2a603f28db1564a6b6304c4b97ba8` |
| Baseline | P1-05 **PRODUCT OWNER ACCEPTED**, merge `dde0b610bd694856ed86ab3f6657c3912140eaee` |
| Menu | `System Administration → Security Status` — today a **locked** node in `ApprovedMenu` (`app/Shared/Navigation/ApprovedMenu.php:123`) |
| Status | **DESIGN — awaiting Product Owner review.** No code, schema, migration, route or production change |

> **Nothing in this document has been implemented.** It is the design to be
> reviewed before EXECUTE begins. Every source fact below was read out of the
> repository at `4d93228c1aa2a603f28db1564a6b6304c4b97ba8` and is cited by file
> so a reviewer can check it rather than take it on trust.

---

## 0. The three sentences the whole design serves

1. **Absence of evidence is never evidence of health.** (PLAN §1)
2. **A value a viewer may not see must not be inferable from anything they can
   see** — not the badge, not a count, not a colour, not an ordering.
3. **Every number on these screens comes from a source unit that already owns
   it.** P1-06 computes posture; it never recomputes access.

Where this document makes something structural rather than merely stated, that
is deliberate and follows the house pattern already proven in
`SecurityEventLogger`: *a leak has nowhere to go*, rather than *a leak is
discouraged*.

---

## 1. Module shape

A new module, `App\Modules\Security`, beside the existing five. **It has no
`Models` directory, and that absence is asserted** (§15, F-10): a posture module
with an Eloquent model is a posture module that has started storing something.

```
app/Modules/Security/
├── Posture/
│   ├── PostureState.php            enum: the five states
│   ├── ControlKind.php             enum: PostureControl | InformationalMetric
│   ├── Evidence.php                what an adapter returns
│   ├── PostureRow.php              internal truth: control + state + finding
│   ├── MetricRow.php               internal truth: control + count + context, NO state
│   ├── PostureReport.php           every row, unprojected — never rendered
│   ├── Aggregation.php             THE aggregation contract, nothing else
│   ├── PostureEvaluator.php        THE one evaluator
│   └── Adapters/
│       ├── SourceAdapter.php       the interface
│       ├── IdentityAdapter.php     → P1-02
│       ├── SessionAdapter.php      → P1-02 / P1-00
│       ├── RouteCoverageAdapter.php→ the route table
│       ├── EngineGateAdapter.php   → P1-05 AccessEngine
│       ├── AdministratorAdapter.php→ P1-05 AdministratorSetGuard
│       ├── GrantPathAdapter.php    → P1-05 tables
│       ├── DomainAdapter.php       → P1-04 tables
│       └── StepUpAdapter.php       → P1-05 step-up registration
├── Projection/
│   ├── Viewer.php                  who is looking, and what they may value
│   ├── ViewerRow.php               a VALUED row — carries a state
│   ├── WithheldRow.php             a NAMED row — carries NO state field at all
│   ├── ViewerMetric.php            a count — carries NO state field at all
│   └── PostureProjection.php       PostureReport + Viewer → ViewerReport
├── Catalogue/
│   ├── ControlCatalogue.php        B-1…B-9b, PR-1…PR-10, immutable
│   └── EventCatalogue.php          key → category → label, immutable
└── Http/Controllers/
    ├── BaselineController.php
    ├── PrivilegedAccessController.php
    ├── ExceptionsController.php
    └── SecurityEventsController.php
```

**Why a separate module rather than a folder inside Access.** Security Status
reads P1-02, P1-03, P1-04 and P1-05. Putting it inside any one of them would
make that module depend on the other four, which is the dependency direction
`RequireOrganisation` was deliberately *not* promoted to Platform to avoid
(`routes/web.php`, P1-03 note). Security depends on all of them; none depends on
Security.

---

## 2. One posture engine, and the exact aggregation contract

### 2.1 The five states

```php
enum PostureState: string
{
    case Critical      = 'critical';        // Act now
    case Attention     = 'attention';       // Needs attention
    case Unverified    = 'unverified';      // Not verified
    case NotApplicable = 'not_applicable';  // Not part of Release 1
    case Healthy       = 'healthy';         // Healthy
}
```

`label()` returns the business label; nothing else ever renders the case name.
There is no `not_configured` case (PLAN §2.1) and **no sixth case is addable
without changing `Aggregation`**, because the contract below is a `match` over
the enum with no default — adding a case is a compile-level fail, not a silent
fall-through.

### 2.2 The contract, as one method that nothing else may duplicate

```php
final class Aggregation
{
    /**
     * @param list<PostureState> $contributions
     */
    public static function of(array $contributions): PostureState
    {
        $applicable = array_values(array_filter(
            $contributions,
            static fn (PostureState $s): bool => $s !== PostureState::NotApplicable,
        ));

        // An empty APPLICABLE set is NotApplicable - never Healthy, never
        // Unverified. PLAN §2.2, guarded by N-SS3c. The wholly empty case is
        // unreachable in production because ControlCatalogue is non-empty and
        // N-SS7 asserts it, but it is answered here rather than left to fall
        // through to the Healthy return below.
        if ($applicable === []) {
            return PostureState::NotApplicable;
        }

        foreach ([PostureState::Critical, PostureState::Attention, PostureState::Unverified] as $state) {
            if (in_array($state, $applicable, true)) {
                return $state;
            }
        }

        return PostureState::Healthy;
    }
}
```

Three properties a reviewer should check directly:

| Property | Where it comes from |
| --- | --- |
| `NotApplicable` is filtered out **before** precedence is applied | the first statement — it is not a rank, so it cannot hold an aggregate down |
| `Healthy` is only ever reached by **falling through every other applicable state** | the `return` after the loop — not by "no critical row exists" |
| An empty applicable set is **`NotApplicable`** | the early return — an aggregate over nothing is not a claim of health |

> **The mutation this is built against (F-1).** `IdentityHealthReport::state()`
> (`app/Modules/Identity/Health/IdentityHealthReport.php:39`) reads *any Failed;
> else any Degraded; else Healthy*, and its own docblock says **"NotChecked
> contributes nothing — it is information, not a finding."** Copying that shape
> here — dropping `Unverified` from the loop — is the single most likely wrong
> edit in this unit, because it is *correct in the file it was copied from.*
> N-SS2 and N-SS3b break it.

**P1-02 is not changed.** Its rule is right for its screen. P1-06 maps its four
row states in (§4.2) and never calls `IdentityHealthReport::state()`.

### 2.3 Exactly one evaluator

`PostureEvaluator::evaluate(): PostureReport` is the only thing that produces a
`PostureRow`. Made structural three ways:

1. **`PostureRow` and `MetricRow` have private constructors**, reachable only
   through `PostureEvaluator` and through the test-only fixture builder. A
   controller or a component cannot construct one.
2. **`Aggregation::of()` is the only call site of the precedence rule.** F-7's
   architecture guard greps the `Security` namespace and the `resources/js` tree
   for any second implementation — a `match` over `PostureState`, a severity
   ranking array, a `sort` by state, or a JavaScript `if (state === 'critical')`
   chain producing a summary.
3. **No authorization is computed in JavaScript, and neither is posture.** The
   rendered props carry finished labels and finished states; the React layer
   chooses a CSS class from a state it was given and computes nothing.

> **The failure this closes.** The natural second evaluator is not a rival
> service — it is a `<SummaryBadge>` component that takes `rows` and works out
> the worst one, or an Exceptions controller that re-filters instead of reading
> the projection. Both would drift from `Aggregation` within one unit. The
> Exceptions screen therefore **receives** its list (§8); it does not derive it.

### 2.4 Fail-closed, and the one thing P1-06 must not do about it

Every adapter failure path resolves to `Unverified` **with the row still
rendered** (PLAN §2.3). Two points the implementation must get exactly right:

| Condition | Result |
| --- | --- |
| Adapter throws, times out, or is unreachable | `Unverified`, reason in business words, **row present** |
| Adapter returns no rows where rows were expected | `Unverified`, **row present** |
| Two adapters disagree | `Attention`, naming both; the disagreement is the finding |
| A stored enum value the code does not recognise | `Unverified`, **and nothing is recorded** |
| A catalogued control with no adapter | **Build failure** (N-SS7), never a missing row |

> **`access.state.unrecognised` already exists** — it is a P1-05 event declared
> at `SecurityEventLogger.php` and emitted by `AccessEngine`. That makes the
> boundary sharper, not softer: **P1-06 does not emit it either.** The event
> belongs to the engine that makes access decisions. A reporting screen that
> recorded one would be writing history it has also declared it cannot read
> (§9). N-SS30 asserts no `SecurityEventLogger::record()` call from the
> `Security` namespace on **any** path, the unrecognised-value path included.

A row is **never omitted to avoid an awkward state.** Omission is the failure
that makes a posture screen lie, because the reader counts what they see.

---

## 3. Viewer-specific authorization and projection

**This is the section with the most ways to be quietly wrong, so it is the most
structural.**

### 3.1 Who may look, and on what authority

From the accepted P1-05 catalogue, read at
`app/Modules/Access/Support/RoleCatalogue.php`:

| Role | Classes held | Consequence for Security Status |
| --- | --- | --- |
| System Administrator | `PlatformAdmin`, `OrgAdmin`, `AccessAdmin`, `EvidenceRead` | Sees every row **valued** |
| Organisation Administrator | `OrgAdmin`, `AccessAdmin`, `EvidenceRead` | Organisation rows valued; platform rows **named, not valued** |
| Auditor | `EvidenceRead` **only** | Identical to the above, and reaches no other administration screen |
| Executive · Domain Owner · Manager · Business User | `BusinessData` only | **Refused**, with no security metadata |

Every route declares `ActionClass::EvidenceRead` through `RequireActionClass`.
**No role is broadened and no new class is added.** The refusal is the existing
one — `redirect()->route('auth.access-denied')`, or `403 {"message":"Forbidden."}`
for JSON — which carries no payload and no hint about what was requested
(`app/Modules/Access/Http/Middleware/RequireActionClass.php`). An unauthorised
caller therefore cannot tell a healthy deployment from a critical one, or a
Security Status route from one that does not exist.

**`RequireOrganisation` is deliberately NOT applied.** Every other console
prefix carries it; this one must not. Posture on a deployment that has not been
configured yet is exactly the day-one screen this unit exists to provide, and
`RequireOrganisation` would redirect it to the Company Profile. A viewer with no
organisation sees the "nothing configured" state (§11.5), not a redirect.

### 3.2 Which rows are platform rows

Fixed in `ControlCatalogue`, not inferred at render time. A control declares its
`scope` as `Platform` or `Organisation` once, beside its identifier, and the
projection reads that field. A platform row is one whose value is legitimately
System-Administrator-only — identity trust, the provider inventory, the client
secret's presence, the session policy, step-up registration, the administrator
set, route authorization coverage.

### 3.3 Withheld is a PRESENTATION outcome, not a sixth state

This is the crux. `not_applicable` means *outside Release 1*. A platform row
hidden from an Organisation Administrator is **applicable, evaluated, and
known** — it is simply not theirs to see. Reusing `not_applicable` for it would
be the same error the PLAN's correction 2 removed, in a new place.

So the projection produces **three row shapes**, and the difference is in the
type, not in a flag:

```php
final class ViewerRow      // VALUED
{
    public function __construct(
        public readonly string $control,      // 'B-1'
        public readonly string $label,        // 'Everybody signs in with Microsoft'
        public readonly PostureState $state,  // the value
        public readonly string $finding,      // business words
        public readonly ?string $ownerHref,   // where it is configured
    ) {}
}

final class WithheldRow    // NAMED, NOT VALUED
{
    public function __construct(
        public readonly string $control,
        public readonly string $label,
    ) {}
    // THERE IS NO $state, NO $finding AND NO $ownerHref. Not null - ABSENT.
}

final class ViewerMetric   // COUNTED
{
    public function __construct(
        public readonly string $control,
        public readonly string $label,
        public readonly int $count,
        public readonly string $context,
    ) {}
    // THERE IS NO $state. Not null - ABSENT.
}
```

> **A `WithheldRow` has no state field, so there is no value for a template to
> print, a sort to order by, a class name to derive, or a future edit to
> forget to strip.** That is the `ALLOWED_KEYS` pattern applied to a screen:
> the leak is unrepresentable rather than filtered. A nullable `?PostureState`
> would have been a filter, and filters are what get removed.

Its rendered sentence is **one constant string**, identical for every withheld
row and every underlying value:

> *"Managed by the platform administrator."*

Never *"managed by the platform administrator — no action needed"*, which would
be a value.

### 3.4 The viewer's aggregate is computed over the viewer's rows

```php
PostureProjection::for(PostureReport $report, Viewer $viewer): ViewerReport
```

The projection runs **before** the controller sees anything. `ViewerReport`
carries `ViewerRow`s, `WithheldRow`s, `ViewerMetric`s and one aggregate, and
that aggregate is `Aggregation::of()` over **the states of the `ViewerRow`s
only**. A `WithheldRow` has no state to contribute, so it contributes nothing —
not by a rule, but because there is nothing to pass.

**The controller never receives a `PostureReport`.** It calls the projection and
renders a `ViewerReport`. F-6's architecture guard asserts that no controller,
and nothing under `resources/js`, references `PostureReport`, `PostureRow`,
`MetricRow` or `PostureState::` by name.

### 3.5 Every leak channel, named and closed

The aggregate is the obvious one. It is not the dangerous one.

| Channel | How a platform value would leak | Closed by |
| --- | --- | --- |
| **Aggregate badge** | Aggregate computed over all rows → OrgAdmin sees "Act now" with every visible row healthy | §3.4 — withheld rows carry no state |
| **Unqualified wording** | A scoped aggregate rendered as a bare "Healthy" | §3.6 — a viewer with withheld rows never sees an unqualified aggregate |
| **Exception count** | A withheld `critical` counted among unresolved conditions | §8 — Exceptions is a projection of `ViewerRow`s only |
| **Tab counts** | "Exceptions (3)" including a withheld row | Same source as the list; one number, one derivation |
| **Totals and captions** | *"9 controls reported, 1 not verified"* where the "not verified" is withheld | The caption counts `ViewerRow`s only; withheld rows are counted **separately and by name** |
| **Ordering** | Withheld rows sorted by severity, so position reveals the value | **Catalogue order is the only order.** Rows are never sorted by state, for any viewer |
| **Colour, icon, pill** | A withheld row styled with the danger token | One `posture-withheld` treatment, neutral, no state-derived class — there is no state to derive from |
| **Row count** | A platform row omitted when critical, present when healthy | The catalogue is fixed; **the same rows are named for every viewer**, always |
| **Remediation link** | A link appearing only when a withheld row needs action | `WithheldRow` has no `ownerHref` field |
| **Response timing / size** | A larger payload when a withheld row has a long finding | The withheld sentence is one constant; payload shape is invariant |

> **Ordering is the one most likely to be introduced by a well-meaning
> improvement** — "show the problems first" is a reasonable-sounding request
> that turns a withheld row's position into its value. Catalogue order is fixed
> for every viewer and F-6 breaks it by sorting.

### 3.6 What an Organisation Administrator actually reads

- Badge: **"Healthy — organisation controls"**, never a bare "Healthy".
- Caption: *"No unresolved conditions in the controls you can see. 6 organisation
  controls reported, 1 not verified. 5 platform controls are managed by the
  platform administrator."*
- Each withheld row: its **name**, and *"Managed by the platform administrator."*

This is honest in both directions: it does not claim health it cannot see, and
it does not hint at trouble it must not reveal. A hidden row would have made the
count wrong; a valued row would have widened the role. **This is D-76, built.**

---

## 4. Data-source adapters

### 4.1 The interface, and the four rules it enforces

```php
interface SourceAdapter
{
    /** @return list<Evidence> */
    public function evidence(): array;
}
```

| Rule | How it is enforced |
| --- | --- |
| **Read-only** | An adapter returns `Evidence`. It has no write path, and F-9's guard asserts no `DB::` write, no `save()`, no `update()`, no `delete()` and no `Cache::put()` anywhere in the `Security` namespace |
| **No duplicated rule** | An adapter may **count** and **read state**. It may never **decide**. Every decision is delegated to the owning unit's own service |
| **No external probe on render** | F-12's guard asserts no call to `EntraDiscovery::probe()`, `IdentityHealthCheck::recheck()`, `Http::`, or any HTTP client from the `Security` namespace |
| **Failure is `Unverified`** | `PostureEvaluator` wraps every `evidence()` call in a `try`/`catch (Throwable)` and yields `Unverified` with the control still present |

### 4.2 The adapters, and exactly what each delegates to

| Adapter | Reads | Delegates the decision to | Never |
| --- | --- | --- | --- |
| `IdentityAdapter` | `IdentityHealthCheck::report()` — nine rows, documented as *"Rendering this NEVER touches the network by choice"* | P1-02 | Calls `recheck()`; calls `IdentityHealthReport::state()` |
| `SessionAdapter` | `SessionPolicy` coherence, `EnsureSessionIsCurrent` present in the middleware priority list | P1-02 / P1-00 | Re-derives the approved policy |
| `RouteCoverageAdapter` | The route table: every `console/*` route's `RequireActionClass` parameter | The route table itself | Maintains a hand-written list of routes |
| `EngineGateAdapter` | Presence and reachability of `AccessEngine::globalGates()`' inactive-user and disabled-domain gates | P1-05 | Re-implements a gate to "check" it |
| `AdministratorAdapter` | `AdministratorSetGuard::effectiveCount()` | P1-05 | Counts `role_assignments` itself |
| `GrantPathAdapter` | Current entitlements, scopes, ceilings | P1-05 models | Decides whether a path authorises anything |
| `DomainAdapter` | `business_domains`, `business_domain_owners` | P1-04 | Reads `access_expectation` (D-61) |
| `StepUpAdapter` | Step-up route registration, `StepUpAction` catalogue, local redirect configuration | P1-05 | Infers Entra registration (§5) |

> **`AdministratorAdapter` is the worked example of "no duplicated rule".**
> `AdministratorSetGuard::effectiveCount()` filters on *both* a current
> assignment and `users.status = active`, and its docblock explains that this is
> deliberately **different** from the D-49 rollback reconstruction, which
> ignores account status because it asks a different question. An adapter that
> wrote its own count would pick one of those two definitions by accident and
> then disagree with Roles & Access about how many administrators exist.

### 4.3 The one row P1-06 must not read the value of

`IdentityHealthCheck` produces a `client_secret` row — *"Client secret
present"*. **Presence is the evidence; the secret is not.** The adapter carries
the row's state and finding and never touches the configuration value. P1-02's
own screen has a `POST /console/identity/entra/reveal`; **P1-06 has no reveal
route, no reveal action and no revealable field** (§10, §11).

### 4.4 "Live" means recomputed, not probed

D-81, as corrected. P1-06 holds **no cache of its own** — consistent with D-69's
refusal of a permission cache, and for the same reason: a cached posture is
wrong at precisely the moment something has just changed.

It also **never triggers a probe to render a page.** P1-02 already separates
*identity trust available* (cache-backed, a good answer) from *Microsoft
reachable* (a live probe, `NotChecked` until somebody presses the button), and
`report()` is documented as reading the cache and the stored probe result
without touching the network. P1-06 consumes whatever P1-02 currently says. **An
unrun probe is `Unverified`** — the honest answer, and it costs nothing.

Source-specific caching stays with the source unit. P1-06 neither overrides nor
duplicates it.

---

## 5. The B-9 split, built

`ControlCatalogue` declares **two controls, not one with two findings.** A single
row could be rendered green on the strength of its local half; two rows cannot.

| Control | Label | Evidence | Rule |
| --- | --- | --- | --- |
| **B-9a** | *"Privileged changes need a fresh Microsoft sign-in"* | `auth.microsoft.step-up` and `access.step-up.begin` / `.redirect` registered; `StepUpAction` has its five cases; the local step-up redirect configuration is present | Any missing → **`Critical`**. Privileged changes would be unconfirmable |
| **B-9b** | *"Microsoft accepts the return from a fresh sign-in"* | **None exists** | **`Unverified`**, always, in Release 1 |

`PR-9a` and `PR-9b` are the same two facts on the Privileged Access Health
screen, read from the same adapter, so the two screens cannot disagree.

**B-9b's implementation is a constant, and that is the point.** There is no
branch that could make it `Healthy`, because there is no evidence source that
could justify one. When a future unit introduces an accepted live source — the
way P1-02's probe is an accepted source for reachability — B-9b gains an
adapter. Until then the honest answer is a constant, and a constant cannot be
made to lie by a configuration change.

Its finding says so in business words:

> *"SemantIQ's side of this is configured. Whether Microsoft accepts the return
> address cannot be checked from inside SemantIQ, and will first be proven the
> next time somebody confirms their identity."*

> **The mutation this is built against (F-4).** P1-05 already produced this
> exact failure in production: the local redirect configuration was correct and
> step-up still failed, because Entra had not registered the URI. A screen that
> inferred B-9b from B-9a would have shown green over it.

---

## 6. Posture controls versus informational metrics

### 6.1 The distinction is in the type

```php
enum ControlKind { case PostureControl; case InformationalMetric; }
```

A catalogue entry declares its kind once. The evaluator's return type follows
from it: a `PostureControl` yields a `PostureRow` (which has a state), an
`InformationalMetric` yields a `MetricRow` (which has none). **There is no code
path that gives a metric a state, because `MetricRow` has no field to put one
in**, and `Aggregation::of()` takes `list<PostureState>` — a `MetricRow` cannot
be passed to it.

### 6.2 The catalogue

**Posture controls — state-bearing, contributing:**

| # | Control | Rule |
| --- | --- | --- |
| **PR-1** | Active System Administrators | `0` → `Critical` · `1` → `Attention` · `≥2` → `Healthy` |
| **PR-3** | Privileged people holding business-domain entitlements | Any → `Attention`, worded as *an explicit permitted grant worth reviewing*, never as a violation |
| **PR-6** | Incomplete grant paths | Entitlement with no current scope, or no current ceiling → `Attention` |
| **PR-9a** | Step-up — SemantIQ's side | Missing → `Critical` |
| **PR-9b** | Step-up — the external half | `Unverified` |
| **PR-10** | The inactive-account gate | Absent or bypassable → `Critical` |

**Informational metrics — count and context, no state, never aggregated:**

| # | Metric | Context sentence |
| --- | --- | --- |
| **PR-2** | Organisation Administrators | *"No threshold is applied. This is a count, not a finding."* |
| **PR-4** | Restricted sensitivity grants | *"Restricted access is granted deliberately and is confirmed with a fresh Microsoft sign-in."* |
| **PR-5** | Inactive people holding current assignments | *"Assignments are preserved; an inactive account has no effective access."* |
| **PR-7** | Domain owners without an entitlement | *"Owning a domain grants nothing. This is not a gap."* |
| **PR-8** | Whole-domain and organisation scopes | *"A broad scope is a deliberate grant. No threshold is applied."* |

**PR-1's `≥2 → Healthy` half is built, not just specified.** A rule whose green
branch is never exercised is a rule nobody has tested; N-SS9 asserts both halves
against fixtures, and §13.3 records honestly that the green half **cannot be
observed in production** without manufacturing a second administrator, which is
forbidden.

### 6.3 PR-5 and PR-10 are one idea

PR-5 counts inactive people whose assignments were preserved — **P1-05 behaving
exactly as designed**, since P1-03 preserves relationships and
`AccessEngine::globalGates()` denies an inactive user outright
(`DecisionReason::DeniedInactiveUser`, checked before any path is considered).
The count is harmless *because the gate holds.*

So the **gate** is what carries state (PR-10, `Critical` if it fails) and the
count does not. Marking the count amber would invent risk from approved
behaviour; leaving the gate unwatched would be the real gap.

> **The mutation this is built against (F-5).** Giving PR-4 or PR-5 a state is
> the single most tempting wrong edit in this unit, because "a Restricted grant
> should be amber" sounds like caution. It would mean P1-06 declaring approved,
> step-up-protected P1-05 behaviour to be a fault, and would train
> administrators that amber means nothing. N-SS14, N-SS15, N-SS16 and N-SS17
> break it from both directions.

### 6.4 What no indicator reads

`access_expectation` (D-61 — context only; the engine never reads it), and a
role's *name* as a proxy for risk. Only an observed state carries a state.

---

## 7. Domain posture

### 7.1 Where it lives — a DESIGN decision worth the Product Owner's eye

The approved PLAN names **four** subscreens. Domain posture (PLAN §5) needs a
home among them. This design puts it as a section titled **"By business
domain"** on the **Privileged Access Health** screen, because six of its seven
facets are access facts — entitlements into the domain, privileged grants into
it, scope breadth, ceilings, incomplete grants — and the seventh, the
accountable owner, is accountability *for* access.

The alternative considered was a fifth tab. It was rejected because it would add
a subscreen the PLAN did not approve. **If the Product Owner prefers a fifth
tab, say so at review and this section moves; nothing else changes.**

### 7.2 Per-domain facets

| Facet | Source | Treatment |
| --- | --- | --- |
| Enabled / disabled | `BusinessDomain::status` | Disabled → `Healthy` **for the gate**, labelled *"Disabled — nobody reaches its information"* |
| Accountable owner | current `business_domain_owners` row | Enabled **without** an owner → `Attention` |
| Current entitlements | `DomainEntitlement` | Count |
| Privileged grants into it | entitlements whose role is in `RoleCatalogue::requiringStepUp()` | Any → `Attention` |
| Scope breadth | `EntitlementScope` where `ScopeType::coversWholeDomain()` | Count |
| Sensitivity ceilings | `EntitlementCeiling` at `Restricted` | Count |
| Incomplete grants | entitlement without a current scope or ceiling | Count |

A disabled domain is a **fail-closed success**, not a fault. Calling one a fault
teaches people to ignore the screen.

### 7.3 No cross-contamination, structurally

The per-domain evaluation takes a **domain id** and returns that domain's rows.
It is a pure function of one domain's data:

```php
DomainAdapter::evidenceFor(int $domainId): array
```

There is no method that takes the whole set and partitions it, because that is
where a mis-scoped `groupBy`, a missing `where`, or an outer query reused across
iterations puts one domain's rows into another's total. Each domain's queries
carry `business_domain_id = ?` at the top level, and the result is never
computed from a collection shared across domains.

N-SS21's fixture is two domains with **deliberately different** entitlement,
scope and ceiling counts, so a contamination bug changes an assertion rather
than coincidentally matching. The mutation drops the `where` clause.

### 7.4 What no domain row implies

Nothing here implies data classification or Fabric security exists. Sensitivity
is a **ceiling on a grant**, not a label on data, and there is no data in Phase
1. **Domain ownership is never access** — said on the screen, not just here.

---

## 8. Exceptions as a derived projection

### 8.1 The definition, as one function of already-projected rows

> **An Exception is any `ViewerRow` whose state is not `Healthy`.**

```php
ViewerReport::exceptions(): array   // filter over $this->rows, nothing else
```

It reads the **projection**, not the report, so a withheld row can never appear
and can never be counted. It reads `ViewerRow`s only, so a `ViewerMetric` cannot
appear — there is no state to test. And because `not_applicable` rows are
`ViewerRow`s with `PostureState::NotApplicable`, the filter excludes them by
name, not by accident.

**One derivation, one number.** The tab count, the page heading count and the
list all come from this method. An "Exceptions (3)" badge that counted
separately is exactly how a count and a list come to disagree.

### 8.2 The four kinds

Declared in the catalogue beside each control, because the four are answered by
completely different people:

| Kind | Means | Release-1 example |
| --- | --- | --- |
| **Unresolved security condition** | Observed, actionable now | Zero administrators; step-up unavailable |
| **Accepted limitation** | Known, deliberate, recorded | Encryption not observable from inside the application |
| **Verification incomplete** | The control exists; this deployment has no evidence | The live probe never run; **the P1-02 SSO Re-check** (§12) |
| **Not yet configured** | A setup step outstanding | A step-up redirect URI absent |

### 8.3 No write action, anywhere

There is no acknowledge, no dismiss, no snooze, no "mark as reviewed" and **no
route that could carry one** (§10). Accepted limitations live in
`ControlCatalogue` — a reviewed static register in code — so changing what this
deployment accepts costs a code change and a Product Owner decision, which is
the correct weight for it. **D-77, built.**

### 8.4 The "not part of Release 1" area

Rendered on the same screen, clearly separated, labelled, and **never counted in
the unresolved total**.

> **An honest consequence of the approved PLAN, worth stating plainly.** PLAN
> §3.4 reserves `not_applicable` for capabilities genuinely outside Release 1 —
> data classification and Fabric security — and says they are *"stated as absent
> rather than as controls at all"*. Followed strictly, **Release 1 renders zero
> `not_applicable` rows.** The state still exists in the model and its
> aggregation behaviour is still specified and tested (N-SS3a, N-SS3c), because
> the contract must be right before the first real instance arrives rather than
> retrofitted around it. But a reviewer should know that those two cases are
> **fixture-only in Release 1**, and this document does not pretend otherwise.
> If the Product Owner would rather see the two absent capabilities rendered as
> genuine `not_applicable` rows, that is a small change to the catalogue and
> nothing else.

---

## 9. Security Events — the P1-08 boundary, built

### 9.1 What the screen is

Three panels, none of which is a history:

1. **The recorded-events catalogue** — all **71** declared types, in business
   categories and readable labels.
2. **The redaction contract** — the **15** permitted context keys and the
   sentence that there is no free-text channel.
3. **The limitation panel** — *"Searchable security history arrives with Audit.
   What you see here is what is being recorded, not a record of what
   happened."* Carried into Exceptions as kind **Verification incomplete**.

Both counts were read from the code at
`app/Modules/Platform/Security/SecurityEventLogger.php`, not estimated: the
`EVENTS` list holds **71** entries and `ALLOWED_KEYS` holds **15**.

### 9.2 Source truth versus display text

`EventCatalogue` maps every declared key to exactly one category and one
readable label. The **keys come from `SecurityEventLogger::events()` at
runtime**, so coverage cannot drift; the **mapping** is the catalogue's own
data. The guard (F-11) is completeness in both directions:

- every key returned by `events()` has a category and a label — a new event
  added by a future unit **fails the build** until somebody writes its label;
- every key in the mapping is still declared — a removed event fails too;
- **no rendered prop anywhere on these screens matches `/^[a-z_]+(\.[a-z_]+)+$/`** —
  which is what stops `access.step_up.refused` reaching an administrator's eye.

> This is CLAUDE.md §4 made structural. The catalogue is source truth; it is
> **not** display text.

### 9.3 The complete mapping — 71 keys, ten categories

**Every declared key appears exactly once below.** Counts are per category and
sum to 71.


#### First-run setup — 3

| Declared key (source truth, never rendered) | Rendered label |
| --- | --- |
| `bootstrap.grant.issued` | First-run setup grant issued |
| `bootstrap.completed` | First-run setup completed |
| `bootstrap.refused` | First-run setup refused |

#### Sign-in — 7

| Declared key (source truth, never rendered) | Rendered label |
| --- | --- |
| `auth.login.succeeded` | Signed in |
| `auth.login.refused.unknown_identity` | Sign-in refused — account not recognised |
| `auth.login.refused.inactive` | Sign-in refused — account not active |
| `auth.login.refused.tenant` | Sign-in refused — outside the approved directory |
| `auth.login.refused.protocol` | Sign-in refused — sign-in could not be completed |
| `auth.logout` | Signed out |
| `auth.session.expired` | Session expired |

#### Sign-in configuration — 2

| Declared key (source truth, never rendered) | Rendered label |
| --- | --- |
| `identity.health.checked` | Sign-in health checked |
| `identity.health.state_changed` | Sign-in health changed |

#### Organisation structure changes — 22

| Declared key (source truth, never rendered) | Rendered label |
| --- | --- |
| `organisation.created` | Organisation created |
| `organisation.updated` | Organisation updated |
| `legal_entity.created` | Legal entity created |
| `legal_entity.updated` | Legal entity updated |
| `legal_entity.deactivated` | Legal entity deactivated |
| `business_unit.created` | Business unit created |
| `business_unit.updated` | Business unit updated |
| `business_unit.deactivated` | Business unit deactivated |
| `department.created` | Department created |
| `department.updated` | Department updated |
| `department.deactivated` | Department deactivated |
| `department.moved` | Department moved |
| `team.created` | Team created |
| `team.updated` | Team updated |
| `team.deactivated` | Team deactivated |
| `team.moved` | Team moved |
| `team.member.added` | Person added to a team |
| `team.member.removed` | Person removed from a team |
| `management.relationship.set` | Manager set |
| `management.relationship.cleared` | Manager cleared |
| `business_unit.legal_entity.associated` | Business unit linked to a legal entity |
| `business_unit.legal_entity.dissociated` | Business unit unlinked from a legal entity |

#### People and group changes — 11

| Declared key (source truth, never rendered) | Rendered label |
| --- | --- |
| `user.provisioned` | Person added |
| `user.provision.refused` | Adding a person was refused |
| `user.activated` | Person reactivated |
| `user.deactivated` | Person deactivated |
| `user.organisation.assigned` | Person assigned to the organisation |
| `group.created` | Group created |
| `group.updated` | Group updated |
| `group.deactivated` | Group deactivated |
| `group.activated` | Group reactivated |
| `group.member.added` | Person added to a group |
| `group.member.removed` | Person removed from a group |

#### Business domain changes — 6

| Declared key (source truth, never rendered) | Rendered label |
| --- | --- |
| `business_domain.created` | Business domain created |
| `business_domain.updated` | Business domain updated |
| `business_domain.enabled` | Business domain enabled |
| `business_domain.disabled` | Business domain disabled |
| `business_domain.owner.assigned` | Business domain owner assigned |
| `business_domain.owner.cleared` | Business domain owner cleared |

#### Access changes — 8

| Declared key (source truth, never rendered) | Rendered label |
| --- | --- |
| `access.role.assigned` | Role assigned |
| `access.role.revoked` | Role removed |
| `access.role.self_assigned` | Role assigned to oneself |
| `access.entitlement.granted` | Domain entitlement granted |
| `access.entitlement.revoked` | Domain entitlement removed |
| `access.scope.assigned` | Scope assigned |
| `access.scope.revoked` | Scope removed |
| `access.ceiling.set` | Sensitivity limit set |

#### Privileged confirmations — 3

| Declared key (source truth, never rendered) | Rendered label |
| --- | --- |
| `access.step_up.requested` | Identity re-confirmation requested |
| `access.step_up.completed` | Identity re-confirmed |
| `access.step_up.refused` | Identity re-confirmation refused |

#### Permanent deletions — 7

| Declared key (source truth, never rendered) | Rendered label |
| --- | --- |
| `legal_entity.purged` | Legal entity permanently deleted |
| `business_unit.purged` | Business unit permanently deleted |
| `department.purged` | Department permanently deleted |
| `team.purged` | Team permanently deleted |
| `user.purged` | Person permanently deleted |
| `group.purged` | Group permanently deleted |
| `business_domain.purged` | Business domain permanently deleted |

#### Access system conditions — 2

| Declared key (source truth, never rendered) | Rendered label |
| --- | --- |
| `access.state.unrecognised` | Access state could not be interpreted |
| `access.engine.failed` | Access decision could not be completed |

#### Why ten categories and not the five the PLAN sketched

PLAN §7.2 proposed *Sign-in · Administration changes · Access changes ·
Privileged confirmations · Permanent deletions* and gave DESIGN the mapping. Five
would have forced 39 of the 71 into a single "Administration changes" bucket,
which is a heading a reader scrolls past rather than uses. The five proposed
names all survive; four of them unchanged. "Administration changes" is split
into the four things an administrator actually distinguishes — organisation
structure, people and groups, business domains, and first-run setup — and
"Access system conditions" is added for the two events that are **conditions
rather than changes**, and which a reader should not have to find among role
grants.

### 9.4 What P1-06 does NOT do, and why it is a fact rather than a preference

`SecurityEventLogger::record()` ends at `Log::info(...)`, and its own docblock
reads *"No audit table — P1-08 owns durable storage and adopts these events
later."* The evidence is therefore **not durable** (rotated log files), **not
queryable** (text lines, no index), **not tamper-resistant** (ordinary files on
the application server) and **not complete** (retention is a rotation setting,
not a policy).

| P1-06 | P1-08 |
| --- | --- |
| *What* is recorded, and the redaction contract | The durable store and its schema |
| Coverage as posture | Searchable history, filters, retention |
| A link to Audit when it exists | Tamper resistance, export, viewer-appropriate redaction |

**P1-06 reads no log file, creates no table, records no event, and adds no key
to `ALLOWED_KEYS`.** Four separate guards (F-10), because they are four separate
edits somebody could make.

---

## 10. The no-secret contract, and where it is enforced

### 10.1 A boundary, not a review

Every rendered value crosses `PostureProjection`. Nothing reaches Inertia except
a `ViewerReport` built from `ViewerRow`, `WithheldRow` and `ViewerMetric`, and
those three types hold **only**:

- a control identifier from the catalogue (`B-1`, `PR-4`) — a fixed, non-secret token;
- a label and a finding, both written by hand in `ControlCatalogue` or by an
  adapter from a fixed set of sentences;
- a `PostureState` enum case, or an `int` count;
- a `?string` href chosen from the **route names of existing screens**.

There is no `array $extra`, no `mixed $value`, no `$raw`, and no passthrough of
any configuration value, environment variable or model attribute. **A secret has
nowhere to go** — the same property that makes `SecurityEventLogger` safe, for
the same reason.

### 10.2 The specific values that must never appear

| Value | Where it exists | P1-06's treatment |
| --- | --- | --- |
| Entra client secret | Configuration; P1-02's `entra.reveal` POST | **Presence only.** No reveal route, no revealable field |
| Client ID, tenant ID | Configuration | Not rendered. Presence and validity are the evidence |
| Signing keys, JWKS material | `EntraDiscovery` cache | Never read |
| Authorization codes, nonces, PKCE verifiers, state | Sign-in flow | Never read |
| Step-up references | `PendingStepUp` | Never read — opaque and single-use, and posture needs only whether the routes exist |
| Bootstrap grants | P1-00 | Never read |
| Session identifiers | The session | Never read |

### 10.3 The guard is adversarial, not a spelling check

N-SS23 renders **every** Security Status screen as each of the three authorised
roles, walks the complete Inertia prop tree, the JSON response and the log
output, and fails on:

- any value matching a secret shape — long base64/hex runs, `eyJ`-prefixed
  tokens, `-----BEGIN`;
- any value equal to a **seeded fixture secret**, which is the half that
  actually catches things: a distinctive known value planted in configuration
  and then searched for by equality across the whole payload. A regex alone
  passes over a short secret; an equality check does not.

The mutation is a deliberately added passthrough field on `ViewerRow`.

---

## 11. Routes and screens

### 11.1 Every route is a GET

Nested inside the existing `console` group, so `EnsureSessionIsCurrent` already
re-checks the session, the absolute lifetime and that the account is still
active on every request before any of this is reached:

```php
Route::middleware(RequireActionClass::class.':'.ActionClass::EvidenceRead->value)
    ->prefix('security')
    ->name('security.')
    ->group(function (): void {
        Route::get('/',                  [BaselineController::class, 'show'])->name('baseline');
        Route::get('privileged-access',  [PrivilegedAccessController::class, 'show'])->name('privileged');
        Route::get('exceptions',         [ExceptionsController::class, 'show'])->name('exceptions');
        Route::get('events',             [SecurityEventsController::class, 'show'])->name('events');
    });
```

Four routes, four GETs, and **no POST, PUT, PATCH or DELETE under the prefix at
all.** F-9's architecture guard asserts that exact set by enumerating the route
table, so a fifth verb added later fails the build — the same shape as
`IdentityArchitectureTest` and `LifecycleCompletenessTest`, which already assert
the delivered verb sets for P1-02 and P1-01.

This is how *"a mandatory control cannot be switched off from these screens"*
becomes structural rather than asserted: there is no route that could carry the
switch.

**Remediation is navigation.** Every row's affordance is a link to the screen
that owns the control — Identity & SSO, Roles & Access, Business Domains, Users
& Groups — and that screen re-authorises on arrival through its own
`RequireActionClass`. **Visibility here is never permission there**, which is the
P1-05 lesson applied.

`/console/security` has no static segment beside a dynamic one, and no dynamic
segment at all, so the routing collision that P1-03 correction 1 and P1-04 were
written against cannot occur. `security` is not one of the directories the
Apache boundary refuses; `RoutePrefixCollisionTest` covers that in both
directions.

### 11.2 The menu node

`ApprovedMenu` line 123 moves from `locked()` to `leaf()`:

```php
NavigationNode::leaf($area, 'Security Status', 'i-shield', 'security.baseline', 'security.view'),
```

The label, the icon and the position are unchanged — they are the Product
Owner's, verbatim from the phase authority document. `NavigationNode`'s
constructor already refuses a locked node that carries a route, so this is the
only edit needed.

**And it must be discoverable, not merely present.** The P1-05 Product Owner
test found the Access Simulator reachable by URL and invisible in the UI. The
navigation walk in §13.2 checks Security Status the same way, at both widths.

### 11.3 The tab strip

Pattern B, the shared standard, the same component shape and the same CSS
classes as `OrganisationTabs` and `IdentityTabs`: a `<nav>` landmark of real
`<a href>` elements with `aria-current="page"` on the active one — **not** the
ARIA tab widget, which the standard reserves for genuine in-page panels.

| Tab | Route |
| --- | --- |
| Secure Baseline | `/console/security` |
| Privileged Access Health | `/console/security/privileged-access` |
| Exceptions | `/console/security/exceptions` |
| Security Events | `/console/security/events` |

`activeTab()` follows `IdentityTabs`' exact shape, including the first-tab
exception: every other Security URL starts with `/console/security`, so a plain
"starts with" test would light the first tab up on every screen.

A new `SecurityPage` component provides the chrome — feature header, tabs,
section head, refusal (`role="alert"`) and confirmation (`role="status"`) — in
the same places, with the same roles, as `IdentityPage`. It reuses the shared
`org-` prefixed classes. Duplicating the stylesheet under a `sec-` prefix would
give the product two tab strips that drift apart, which is the
two-sources-of-truth failure this project keeps finding, applied to the things a
person actually looks at.

### 11.4 The posture presentation

One `PostureRow` component. Its state class comes from the state it was **given**
— `posture-critical`, `posture-attention`, `posture-unverified`,
`posture-not-applicable`, `posture-healthy` — and a withheld row uses
`posture-withheld`, a neutral treatment with **no state input at all**.

**Colour is never the only signal.** Every state carries its business label as
text and a distinct icon, so the screen is readable without colour and in both
themes — `ReadableInBothThemesTest` already covers the token contract and is
extended to the five new tokens.

`Unverified` is deliberately **neutral, never green and never amber**. Amber
would make it a fault; green would make it a claim. Neutral is the honest
reading of *"nobody has looked."*

### 11.5 Every named state

| State | What it must read |
| --- | --- |
| **Nothing configured** (day one) | The controls that apply, honestly unverified. **Not an empty screen and not a green one** |
| **No exceptions** | *"No unresolved conditions. N applicable controls reported, M not verified."* **Never a bare "All clear"** |
| **No privileged business access** | *"No administrator currently holds an entitlement to business information."* A statement, not an absence |
| **Sole administrator** | PR-1 at `Attention`, carrying `AdministratorSetGuard::SOLE_ADMINISTRATOR_WARNING`'s existing sentence, so the two screens use the same words |
| **Evidence unavailable** | The row, present, at `Unverified`, saying which source and why |
| **Healthy populated** | The aggregate, with the count of what was reported and what was not |
| **Attention / Critical** | Worst first *by catalogue order within each screen*, never by re-sorting rows (§3.5) |
| **All controls not applicable** | Aggregate `NotApplicable`. **Fixture-only in Release 1** (§8.4) |
| **Only informational rows non-healthy** | Aggregate `Healthy`, with counts shown. The case F-5 would break |
| **Refused** | The existing `auth.access-denied` state, unchanged, carrying no security metadata |

> **"No exceptions" is the dangerous screen** — the one most likely to be read
> as "all clear". Its sentence is why N-SS2 exists.

### 11.6 The polish gate

CLAUDE.md §4 applies in full before handover, and three items are specific to
this unit:

- **no raw identifier anywhere** — event keys, control codes shown without a
  label, enum values, route names (F-11 covers props; the human pass covers copy);
- **no developer terminology** — "adapter", "evaluator", "projection",
  "aggregate", "DTO" are words from this document, not from the screen;
- **the withheld sentence reads as information, not as a locked door** — an
  Organisation Administrator should understand that somebody else owns the row,
  not that something is being kept from them.

---

## 12. The P1-02 carried gate

**Status: OPEN · CARRIED · UNVERIFIED.**

The provider-wide SSO Re-check has been open since P1-02 because it needs a
second **permanent** System Administrator to observe safely, and one must not be
manufactured. P1-06 does not close it and does not manufacture one.

It is rendered as an Exception of kind **Verification incomplete**, state
`Unverified`:

> **Provider-wide sign-in re-check — not verified.** This check needs a second
> permanent System Administrator to observe safely. One has not been created,
> deliberately. Nothing is known to be wrong; nothing has been confirmed either.

Never `Healthy` — nothing confirmed it. Never `Critical` or `Attention` —
nothing indicates a fault. **This is the clearest case in the unit for why
`Unverified` has to be a first-class state** rather than folded into "fine" or
"broken", and N-SS2 is the test that keeps it from being folded.

It carries forward unchanged to P1-07 and beyond until a genuine second
administrator exists in the ordinary course of the business. **P1-06 adds no new
carried gate of its own**, but it does surface three existing ones — B-9b, the
live probe, and this — in one place for the first time, which is most of the
value of the unit.

---

## 13. Tests, traceability and the thirteen failure modes

### 13.1 Traceability

Every PLAN requirement maps to at least one named case, and every case names the
mutation it must catch. The mutations are **run and observed to fail**, and
recorded in `doc/v2/phase-1/P1-06-MUTATIONS.md` beside the case, as P1-03, P1-04
and P1-05 each did.

The PLAN names **43** cases — N-SS1 to N-SS37, plus the six letter-suffixed
cases N-SS3a, N-SS3b, N-SS3c, N-SS8a, N-SS12a and N-SS12b that the seven
corrections added. All 43 are placed below; none is orphaned.

| PLAN section | Design section | Cases |
| --- | --- | --- |
| §2.1 five states and their labels | §2.1 | N-SS1 |
| §2.2 the aggregation contract | §2.2 | N-SS2, N-SS3, N-SS3a, N-SS3b, N-SS3c, N-SS4 |
| §2.3 fail-closed inputs | §2.4 | N-SS5, N-SS6, N-SS7, N-SS8 |
| §3.4 applicable-but-unobservable | §4, §8.4 | N-SS8a |
| §3.1 the B-9 split | §5 | N-SS12a, N-SS12b |
| §4.2 posture controls | §6.2 | N-SS9, N-SS10, N-SS11, N-SS12, N-SS13 |
| §4.3 informational metrics | §6.2, §6.3 | N-SS14, N-SS15, N-SS16, N-SS17, N-SS18 |
| §5 domain posture | §7 | N-SS19, N-SS20, N-SS21, N-SS22 |
| §6 Exceptions as a projection | §8 | N-SS36 |
| §7 Security Events / the P1-08 boundary | §9 | N-SS28, N-SS29, N-SS30, N-SS31, N-SS32, N-SS33 |
| §8 the carried P1-02 gate | §12 | N-SS12a |
| §9 authorization and D-76 | §3 | N-SS25, N-SS26, N-SS27 |
| §10.4 hard guarantees | §10, §11 | N-SS23, N-SS24, N-SS34, N-SS35, N-SS37 |
| §11 schema impact | §1, §15 | N-SS29 |

Three of these sit in sections whose design detail is short, so they are stated
here rather than left implicit:

- **N-SS4** — precedence holds for every adjacent *applicable* pair. Exercised
  against `Aggregation::of()` directly, pair by pair, so a swapped pair fails
  even if no screen would have shown it.
- **N-SS5 and N-SS6** — a throwing adapter yields `Unverified` **with the row
  still rendered**, and two disagreeing adapters yield `Attention` naming both.
  The mutation for N-SS5 is the tempting one: `catch` and `continue`, which
  removes the row and makes the screen lie by omission.
- **N-SS20** — an enabled domain with no current owner is `Attention`. P1-04
  refuses that state through its own UI, so the fixture constructs it directly:
  a stored state that escaped the UI is exactly what a posture screen is for.

### 13.2 What the automated suite cannot do, said plainly

**There is no JavaScript test runner in this project.** No CI test renders the
DOM, so no CI test can assert that a row is *visible* — only that the server
sent it and that the component contains no conditional render or `hidden`
attribute around it. This was established during P1-05's simulator-discoverability
work and it has not changed.

Visibility, discoverability, theme and responsive behaviour are therefore
**browser-verified by hand** under CLAUDE.md §5, at desktop and small-screen
widths, in both themes, with the navigation walked end to end and the console
checked for errors — and **what was observed is recorded, not what was
expected.**

### 13.3 The thirteen failure modes, each with its guard

Each is a mutation a competent person could plausibly write, not a strawman.

| # | Failure mode | The mutation | The guard, and what it observes |
| --- | --- | --- | --- |
| **F-1** | Aggregation copied from P1-02 — `Unverified` contributes nothing | Delete `Unverified` from `Aggregation::of()`'s precedence loop | **N-SS2, N-SS3b.** An all-unverified set returns `Healthy`; a mixed unverified/healthy set returns `Healthy` |
| **F-2** | `not_applicable` returned to the precedence chain | Move it into the loop between `Unverified` and `Healthy` | **N-SS3a.** A deployment of healthy + not-applicable rows can no longer reach `Healthy` |
| **F-3** | An applicable-but-unobservable control reclassified | Change encryption's catalogue entry from `Unverified` to `NotApplicable` | **N-SS8a.** The row leaves the applicable set and stops contributing — the control is silently written out of scope |
| **F-4** | The external half of step-up inferred from the local half | Make B-9b read B-9a's evidence | **N-SS12a.** B-9b reports `Healthy` with no external evidence present |
| **F-5** | An informational metric given a state | Add `PostureState $state` to `MetricRow` and default it | **N-SS14, N-SS15, N-SS16, N-SS17.** A legitimate Restricted grant turns the aggregate amber; a count enters aggregation; a metric defaults to `Healthy` |
| **F-6** | A withheld platform value leaks | Compute the aggregate over all rows; **or** sort rows by state; **or** count withheld rows in the exception total | **N-SS26.** Two fixtures identical except for a platform row's value must produce **byte-identical** Organisation Administrator payloads — aggregate, counts, order, classes and copy. Not "similar": identical |
| **F-7** | A second posture evaluator | A `<SummaryBadge>` component that works out the worst row from `rows` | **N-SS34**, architecture guard: one call site of the precedence rule; no state ranking in `resources/js`; no second `match` over `PostureState` |
| **F-8** | Cross-domain contamination | Drop the `business_domain_id` predicate from one per-domain query | **N-SS21.** Two domains with deliberately different counts; one domain's totals change |
| **F-9** | A mutating route or acting affordance appears | Add `POST /console/security/exceptions/{id}/acknowledge` | **N-SS24**, architecture guard on the enumerated verb set under the prefix; and no write call anywhere in the `Security` namespace |
| **F-10** | The P1-08 boundary breached | Read a log file; add a migration; call `record()`; add an `ALLOWED_KEY` | **N-SS28, N-SS29, N-SS30, N-SS31.** Four guards, because they are four separate edits |
| **F-11** | A raw internal identifier reaches a surface | Render `access.step_up.refused`; or add an event with no label | **N-SS32, N-SS33.** Completeness in both directions, plus a dotted-identifier scan of every rendered prop |
| **F-12** | An outbound probe on render | Call `IdentityHealthCheck::recheck()` instead of `report()` | **N-SS37.** Rendering any Security Status screen must make no outbound HTTP call; the fake HTTP client records one |
| **F-13** | A secret crosses the render boundary | Add a passthrough field carrying a configuration value | **N-SS23.** A seeded fixture secret is searched for by **equality** across the whole prop tree, JSON and log output, for all three roles |

> **F-6's assertion is byte-identical payloads, deliberately.** An assertion
> that merely checked the aggregate would pass while the ordering leaked. Two
> fixtures differing only in a platform row's underlying value, rendered for an
> Organisation Administrator, must produce the same bytes — which closes every
> channel in §3.5 at once, including ones nobody thought to enumerate.

### 13.4 Non-vacuity

Every guard above is **broken deliberately and observed to fail** before it is
trusted, and the mutation is recorded beside the case. CLAUDE.md §2's specific
warning applies with unusual force to this unit: a posture test is easy to
satisfy by accident, because *"the screen rendered and nothing exploded"* is
compatible with every wrong answer. Two rules follow:

1. **Every state rule is tested in both directions.** Sole administrator →
   `Attention` **and** two administrators → `Healthy`. Privileged user with an
   entitlement → `Attention` **and** without → `Healthy`. A rule tested only on
   its unhappy path is a rule that would pass if it always returned the unhappy
   answer.
2. **Fixtures are built to differ.** Two domains with identical counts cannot
   detect contamination. Two viewers whose payloads coincide cannot detect a
   leak. Where a fixture's values matter, they are chosen to be distinct and the
   choice is stated in the test.

---

## 14. The Product Owner Test Script — what it will contain, and what it cannot

The script is written before acceptance is requested, per CLAUDE.md §3, with all
twelve required parts. Three of them are shaped by facts already known, and
naming them now is the honest thing rather than discovering them at handover.

### 14.1 The permanence warning comes first

Security Status **creates nothing and changes nothing** — every route is a GET.
So the script needs no permanent-data warning for its own steps, and it will say
so plainly. But it must warn about the opposite temptation: **the Product Owner
must not create grants, roles, entitlements or administrators in order to see a
row change colour.** That would mean entering inaccurate business data to
satisfy a test, which CLAUDE.md §3 forbids outright.

### 14.2 What can be observed against real production data

From the state recorded at P1-05 acceptance — one active System Administrator,
platform-scoped; zero current entitlements, scopes and ceilings — the script can
genuinely exercise:

- every screen renders, is reachable from the navigation, and is discoverable
  at both widths and in both themes;
- **PR-1 at `Attention`** — the sole-administrator condition is the live state;
- **B-9b and the live probe at `Unverified`** — the honest unverified rendering,
  which is the unit's headline claim;
- **the P1-02 carried gate** appearing as an Exception of kind *Verification
  incomplete*;
- the Security Events catalogue showing 71 readable labels and **no dotted
  identifier anywhere**;
- the redaction contract and the limitation panel;
- the "no exceptions"-style empty states, in their exact honest wording;
- a refusal: a viewer without `EvidenceRead` receives the standard refusal with
  no security metadata.

### 14.3 What is NOT CURRENTLY OBSERVABLE WITH REAL PRODUCTION DATA

Named now, with its automated evidence, and carried forward as a gate on a later
unit under `PHASE-1-PLAN.md` §10 — **never inferred from a passing test, and
never silently omitted.**

| Not observable | Why | Evidence retained |
| --- | --- | --- |
| **PR-1 `≥2 → Healthy`** | Requires a second permanent System Administrator. *Do not manufacture one* | N-SS9, both halves, against fixtures |
| **PR-3, PR-4, PR-6, PR-8** | Require entitlements, Restricted ceilings, incomplete paths and broad scopes that do not exist in production, and creating them would be false business data | N-SS11, N-SS12, N-SS14 |
| **Any `Critical` state** | Inducing one means breaking a live control — removing the last administrator, unregistering step-up, disabling the inactive-account gate | N-SS10, N-SS12b, N-SS13 |
| **The D-76 withheld view** | Requires a second person holding Organisation Administrator or Auditor and **not** System Administrator | N-SS26, the byte-identical assertion |
| **Cross-domain contamination** | Requires two domains with different grant populations | N-SS21, with deliberately distinct fixtures |
| **The P1-02 provider-wide re-check** | The carried gate itself (§12) | Unchanged; still OPEN |

> Every one of these is a **consequence of a correctly restrained production
> environment**, not an implementation defect. A deployment with one
> administrator, no entitlements and no Restricted grants is a healthy thing for
> a product at this stage to be, and it is precisely why most of the posture
> matrix has to be proven by fixture and mutation rather than by pointing at a
> screen.

---

## 15. Schema, and what this unit does not do

**No new table. No new column. No migration. No new security event. No new
`ALLOWED_KEYS` entry. No change to `SecurityEventLogger`. No change to
`AccessEngine`, `RoleCatalogue`, `ActionClass` or any P1-05 rule. No change to
P1-02's aggregation.**

Every value on these screens is derivable from accepted P1-00 – P1-05 sources:

| Question | Answered by |
| --- | --- |
| Identity posture | `IdentityHealthCheck` / `SessionPolicy` / `ProviderInventory` (P1-02) |
| Administrator count | `AdministratorSetGuard::effectiveCount()` (P1-05) |
| Roles, entitlements, scopes, ceilings | P1-05 tables |
| Domain state and accountability | P1-04 tables |
| People state | P1-03 `users` |
| Route authorization coverage | The route table itself |
| Recorded events | `SecurityEventLogger::events()` |

The only edits outside the new module are:

1. `ApprovedMenu` line 123 — `locked()` → `leaf()`;
2. `routes/web.php` — one four-route GET group;
3. `resources/css/app.css` — the five posture tokens and the withheld treatment,
   added to the shared design system rather than to one screen (the P1-05
   lesson: the underlined-anchor defect was fixed in the design system, not in
   the one place it was noticed).

---

## 16. Exit criteria

1. An administrator with no security background can read the four screens and
   say what is wrong and what to do next.
2. **Nothing reports `Healthy` without evidence** — N-SS2, N-SS3, N-SS3b hold.
3. **A fully healthy deployment CAN reach `Healthy`** — N-SS3a, N-SS3c hold.
4. **No approved P1-05 capability is painted as a fault** — N-SS14 – N-SS17 hold.
5. **A platform value is not inferable from anything an Organisation
   Administrator can see** — N-SS26's byte-identical assertion holds.
6. No mandatory control can be switched off from these screens — N-SS24 holds
   structurally, on the enumerated verb set.
7. No secret appears anywhere — N-SS23 holds, by equality against a seeded value.
8. The P1-08 boundary is intact — N-SS28 – N-SS31.
9. No internal identifier reaches a rendered surface — N-SS32, N-SS33.
10. Rendering triggers no outbound network call — N-SS37.
11. The P1-02 carried gate reads honestly as **not verified**, and B-9b with it.
12. Every mutation in `P1-06-MUTATIONS.md` was **run and observed to fail**.
13. Browser verification recorded as **observed**, at both widths, in both
    themes, with Security Status reached by navigating rather than by URL.
14. Product Owner Test Script complete, with §14.3's list carried forward by
    name rather than inferred from a passing test.

---

## 17. Two questions this design raises for review

Neither reopens an approved decision. Both are consequences discovered while
designing, and CLAUDE.md §4 says to raise them rather than decide them quietly.

1. **Where domain posture lives** (§7.1). This design makes it a section on
   Privileged Access Health, to keep the four approved subscreens. A fifth tab
   is the alternative, and is a one-line change if preferred.
2. **Whether Release 1 renders any `not_applicable` row at all** (§8.4).
   Following PLAN §3.4 strictly, it renders none — the two out-of-scope
   capabilities are stated as absent rather than as controls, so `NotApplicable`
   is fixture-only in this release. If the Product Owner would rather see them
   as genuine rows, it is a catalogue change and nothing else.

**Neither blocks EXECUTE.** If no answer is given, this document's stated choice
stands.

---

**DESIGN complete. Nothing has been implemented. Awaiting Product Owner review
before EXECUTE begins.**
