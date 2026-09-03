# P1-04 — Business Domains: DESIGN

**DESIGN ONLY.** No implementation, no migration, no route, no controller, no
service, no screen, no production change. This document says exactly what will
be built so that it can be argued with before it exists.

| | |
| --- | --- |
| PLAN | `P1-04-BUSINESS-DOMAINS-PLAN.md` — **APPROVED 3 September 2026** |
| PLAN merge SHA | `b083b30f261820a00f8fdfc37addcd1a6e063789` (PR #88) |
| Decisions binding this design | **D-40 to D-48**, PLAN §19 |
| DESIGN reviewed | **APPROVED 3 September 2026**, with three corrections applied below |
| Status | **Corrections applied. Proceeding to EXECUTE** |

### The three corrections from that review

| # | Correction | Where |
| --- | --- | --- |
| **1** | **Serialise on the `business_domains` row, not only the current ownership row.** With no current owner there is no row to lock. Lock order **Domain → ownership → dependency checks**, identical in all five operations, with seven concurrency cases | **§6.2**, §7.1, §11, §16 C1–C7 |
| **2** | **Rescope the architecture guard.** The repository-wide string rule could not hold — migrations, tests and React legitimately name a domain. Split into **Guard A** (module dependency within `app/`) and **Guard B** (nothing anywhere authorizes from domain state) | **§1**, §16 N3 / N3e / N3f |
| **3** | **Narrow the inactive-owner wording.** *"This domain still works exactly as before"* claims more than P1-04 can know, because P1-04 implements no domain data access | **§8.3** |

---

## 0. The one sentence this design exists to keep true

> **A domain existing, being enabled, or having an owner grants ZERO business
> access. Not to its owner. Not to anybody.**

Every section below is written so that sentence survives contact with an
implementation. §16 turns it into tests that fail when it stops being true.

**Domain Owner is accountability only — never a role or an entitlement.** P1-04
builds no role, no domain entitlement, no scope, no sensitivity, no effective
access, no Access Simulator and no domain-derived authorization.

---

## 1. Module, and what it is allowed to touch

A fourth module beside `Identity`, `Organisation`, `People` and `Platform`.

```
app/Modules/Domains/
    Models/
        BusinessDomain.php
        DomainOwnership.php
        DomainKind.php               enum: baseline | custom
        DomainStatus.php             enum: enabled  | disabled
        AccessExpectation.php        enum: undecided | broad | limited | exceptional
    Services/
        DomainService.php            create, update, enable, disable, purge
        DomainOwnershipService.php   set owner, clear owner, read the current owner
        BaselineDomainInitialiser.php
    Support/
        BaselineDomains.php          the seven code/name pairs
        DomainViolation.php          the refusal type, mirroring PeopleViolation
    Console/
        InitialiseBusinessDomains.php  the one-time command of §4
    Http/Controllers/
        DomainController.php
        Concerns/InteractsWithDomains.php
```

**No `DomainsServiceProvider`.** The first draft listed one; reading the
codebase shows **People has none either**. Services are autowired from their
constructors, and the navigation menu is registered once by
`OrganisationServiceProvider` from `ApprovedMenu`, which is a static list. A
provider that registered nothing would be a file whose only content is the
appearance of structure.

**Command registration** therefore goes in `bootstrap/app.php`
(`->withCommands([...])`), which is where this application already declares its
providers and routing — not `bootstrap/providers.php`, which holds only
`AppServiceProvider`. Corrected here rather than discovered during EXECUTE.

### What it reads from outside itself

| Reused | From | Why not re-implemented |
| --- | --- | --- |
| `RequireSystemAdministrator` | `Platform\Http\Middleware` | P1-02 promoted it precisely so a second copy of an authorisation gate could not exist |
| `RequireOrganisation` | `Organisation\Http\Middleware` | Used **where it lives**. It depends on `OrganisationService` and redirects to the Company Profile, so promoting it to Platform would make Platform depend backwards. Correction 3 of P1-03, not reopened |
| `SecurityEventLogger` | `Platform\Security` | §15. **No new context key** |
| `PurgeDependencies` | `App\Shared\Lifecycle` | §11. The schema-driven walk, unchanged |
| `Organisation`, `User` | `Organisation\Models`, `Platform\Models` | Read only. **No column on `users` is ever written** |
| `AppShell`, `StatusPill`, `Pagination`, `ConfirmPurge`, the `org-*` classes | `resources/js` | §2. The UI foundation is frozen |

### What may touch it — and the scope this guard actually has

**Correction, Product Owner review of 3 September 2026.** The first draft said
*"nothing outside the module may reference a domain, except three files."*
Read literally that is **not achievable and not what the boundary is for**:
migrations define the schema, tests are the evidence, React renders the props,
and the route file and provider have to wire it up. A rule that cannot hold gets
weakened the first time it fails, and a weakened rule protects nothing.

**The boundary is about authorization, not about the string `BusinessDomain`.**

> **No production backend code outside `App\Modules\Domains` may read domain
> state to make an authorization or entitlement decision.**

### Two separate guards, each with a scope it can actually keep

| Guard | Scope | Rule |
| --- | --- | --- |
| **A — module dependency** | **`app/` only**, excluding `app/Modules/Domains` | May reference Domains **only** in the declared exceptions below |
| **B — authorization** | **`app/` and `resources/js/`**, the whole application, module included | **Nothing anywhere** may use a domain's status, its owner or its access expectation to grant, deny, widen or narrow access |

**Guard B is the one that matters** and it has no exceptions at all — not even
inside the Domains module. Guard A is the narrower structural rule that keeps
the module from leaking into the rest of the backend.

### Guard A — the declared exceptions, and what is out of scope

| Exception | What is permitted |
| --- | --- |
| `routes/web.php` | **Wiring only** — the route block, controller references, middleware |
| `bootstrap/app.php` | **Wiring only** — registering the `domains:initialise` command |
| `App\Shared\Navigation\ApprovedMenu` | **One line** — *Business Domains* moving from `locked()` to `leaf()` with its route name. The single edit that entry was designed for |
| `OrganisationService::createProfile()` | The approved initialisation integration of §4.1. **One constructor argument and one statement**, and nothing else |

| Out of scope for Guard A | Why |
| --- | --- |
| `database/migrations/` | Schema **definition**, not module dependency. A migration that creates a table is not a caller of it |
| `tests/` | Evidence. A test that could not name the thing it tests would test nothing |
| `resources/js/` | **Presentation.** The React screens receive domain props and render them. Guard **B** is what stops presentation becoming enforcement |
| `config/`, `lang/` | No behaviour |

**A wiring exception is a wiring exception.** If `routes/web.php` ever contains a
*decision* about a domain rather than a route declaration, that is a Guard B
failure whatever Guard A says about the file.

### Guard B — nothing authorizes from a domain, anywhere

Asserted across the **whole** application, the Domains module included:

- No middleware, policy, gate, authorization service or unrelated module reads
  `business_domains`, `DomainStatus`, `access_expectation`, or ownership in
  order to decide what somebody may see or do.
- **The frontend may render domain state and must never enforce with it.** No
  React component hides, shows, enables or disables anything **other than a
  domain's own management controls** based on domain state. Hiding *Enable* on
  an already-enabled domain is presentation; hiding a menu, a route or another
  unit's data would be enforcement.
- `DomainStatus` and `AccessExpectation` are never read outside the Domains
  module and its screens at all.

§16 N3 and N3e break both guards deliberately.

---

## 2. Screens

**One list and one record page. No tab strip.** P1-03 has tabs because it
delivers two kinds of thing; P1-04 delivers one. Baseline and custom domains are
the same object with a different origin, and a tab would ask the reader to know
which tab a domain lives in before they could find it. A **Kind** column and a
filter answer that without navigation.

`DomainsPage.jsx` mirrors `PeoplePage.jsx` exactly — `AppShell`, `org-page`,
`org-feature`, the `role="alert"` refusal banner and the `role="status"`
confirmation banner — **minus the tab strip**. It is a deliberate difference and
the only one.

Page header, on both screens:

> # Business Domains
> Name the intelligence domains this organisation has and who is accountable for
> each one. **Nothing here grants access to any of it.**

### 2.1 The list — `/console/domains`

| Column | Content |
| --- | --- |
| **Name** | The display name. Links to the record |
| **Code** | The stable identity, in a muted monospace-free style — it is an identifier a person compares, not code to be read |
| **Kind** | *Baseline* or *Custom* |
| **Status** | Pill: **Enabled** / **Disabled** |
| **Owner** | The current owner's name, or **Not assigned**. If that owner is inactive, an attention pill reading **Owner inactive** sits beside it |
| **Access expectation** | The sentence form of §9, not the stored value |

Above the table: the filter bar of §10. Below: `Pagination`, 25 rows a page, as
`UserController::PER_PAGE` already does.

**Empty state.** With no domains at all: *"No business domains yet."* With a
filter that matches nothing: *"No domains match these filters."* These are two
different facts and P1-03 shipped a defect by conflating them once already —
gap 5 of its verification. They are distinguished here from the start.

### 2.2 The record — `/console/domains/{domain}`

Four sections, in this order, plus a fifth only where it applies.

| Section | Contents |
| --- | --- |
| **Details** | Name (editable — D-41), **Code (read-only, always shown)**, Description, Kind (read-only) |
| **Accountability** | Current owner, the *no access* sentence, **Set owner** / **Change owner** / **Clear owner**, and the full ownership history table |
| **Availability** | Status, **Enable** / **Disable**, and the sentence saying what enabled means |
| **Expectation** | The access expectation, its four options, and the inert-statement sentence of §9 |
| **Permanent removal** | **Custom domains only**, and only when §11's conditions hold. Absent entirely on a baseline domain |

**Why the code is shown rather than hidden.** An administrator comparing two
deployments, or reading a later unit's report, needs the value that joins them.
It is labelled *Identity code* with the sentence *"This never changes, even if
the name does."*

### 2.3 The sentence that must be on the Accountability section

> **The owner is accountable for this domain. They do not get access to it.**
> Access is assigned in Roles & Access.

This is not decoration. §16 asserts it is present in the rendered screen source
— through `ScreenSource::rendered()`, which strips comments, because P1-03 had a
screen assertion pass against a docblock that merely quoted the copy.

---

## 3. Baseline domains, and how they arrive

**Seven, and the set is closed** — D-44. *Custom Domains* is the capability to
add your own, not an eighth entry.

| `code` | `name` |
| --- | --- |
| `executive` | Executive |
| `sales` | Sales |
| `finance` | Finance |
| `people` | People |
| `operations` | Operations |
| `customer` | Customer |
| `learning` | Learning |

Held in `Support\BaselineDomains` as a single `const`, and asserted to contain
exactly seven entries with exactly these codes.

### The initial state of every one of them — D-46

| Field | Value |
| --- | --- |
| `code` | as above, immutable forever |
| `name` | as above, editable afterwards |
| `status` | **`disabled`** |
| Owner | **none — no ownership row is created** |
| `access_expectation` | **`undecided`** |
| `description` | `NULL` |

> **Do not pretend the organisation uses every baseline domain simply because
> SemantIQ knows the vocabulary.**

That is why they arrive disabled, and it is what makes the enable rule of §7
coherent: **enabling is an act**, by an administrator who has decided the
organisation uses this domain and has named somebody accountable. Arriving
enabled would make *Enabled* mean nothing on the first screen anybody sees.

### `BaselineDomains` is a catalogue used BY initialisation, not option C

D-46 rejected "a static catalogue in code, with rows created on first use". The
difference is not subtle and is worth stating so the two are not confused:

| Rejected (option C) | This design |
| --- | --- |
| The catalogue **is** the source of truth; rows appear when something reads them | The catalogue is **input to one explicit write**; the table is the source of truth from the first moment |
| Every screen merges code and table | Every screen reads the table only |
| A read path creates data | **No read path creates anything** — §16, N30f |

---

## 4. Initialisation — two paths, both explicit

### 4.1 New organisations — the smallest safe integration point

One call, inside the transaction `OrganisationService::createProfile()` already
opens:

```
$organisation = Organisation::query()->create(...);
$creator->forceFill(['organisation_id' => $organisation->id])->save();
$this->baselineDomains->initialise($organisation, $creator);   // <-- the one line
$this->events->record(SecurityEventLogger::ORGANISATION_CREATED, ...);
```

**Why inside that transaction.** It is the same reasoning D-16 already used for
associating the creator: an organisation that exists without its baseline
vocabulary would need a repair path, and this unit deliberately does not have
one. Either both happen or neither does.

**Why this is not a material redesign of P1-01.** One constructor argument and
one statement, in a method that already had exactly this shape. Nothing about
the organisation, its validation, its screens or its own tests changes. The
diff to `App\Modules\Organisation` is intended to be readable in full in one
screen, and §16 asserts it is the *only* reference to Domains outside the
module.

### 4.2 The existing production organisation — a one-time explicit run

Production already has an organisation and **will never run Company Profile
creation again.** It gets an artisan command:

```
php artisan domains:initialise
```

| Property | Design |
| --- | --- |
| **Idempotent** | Keyed on `(organisation_id, code)`. Inserts only what is missing |
| **Never updates** | A domain an administrator has renamed, enabled or assigned an owner to is **left exactly as it is**. Running it again is not a reset |
| **States what it did** | Prints the codes created and the codes already present. A command that prints nothing cannot be verified |
| **Refuses with no organisation** | Says so and exits non-zero, rather than creating orphan rows |
| **Not wired into deploy** | It is dispatched deliberately, once |

**How it is actually run:** a manually dispatched workflow
`.github/workflows/initialise-domains.yml`, in the shape of `verify-people.yml`
— the same SSH and askpass handling — because that is how this project already
reaches production without anybody holding a private key locally. It differs
from the verify workflows in one declared way: **it writes.** That is stated in
its header comment, its name and its run summary, so nobody mistakes it for a
read-only report.

**It is run once, and its output is recorded in the verification document** as
evidence, exactly as the read-only reports have been.

---

## 5. Lifecycle, operation by operation

**The word "CRUD" appears nowhere.** P1-01's most expensive defect was Update
missing from four entity types behind that word, and an operation that does not
exist has no test to fail.

### 5.1 Baseline domain

| Operation | Route | Available |
| --- | --- | --- |
| Create | — | **NO. No route exists** |
| Read list / one | `GET domains`, `GET domains/{domain}` | Yes |
| Rename display name | `PUT domains/{domain}` | **Yes** — D-41 |
| Edit description | `PUT domains/{domain}` | Yes |
| Set access expectation | `PUT domains/{domain}` | Yes |
| Enable | `PATCH domains/{domain}/enable` | Yes, subject to §7 |
| Disable | `PATCH domains/{domain}/disable` | Yes, always |
| Set / change owner | `PATCH domains/{domain}/owner` | Yes, §6 |
| Clear owner | `PATCH domains/{domain}/owner/clear` | Conditionally, §7 |
| Change `code` | — | **NO. Not fillable on any path** |
| Delete / purge | — | **NO. No route exists** |

### 5.2 Custom domain

Identical, plus:

| Operation | Route | Available |
| --- | --- | --- |
| Create | `POST domains` | Yes |
| Guarded purge | `DELETE domains/{domain}` | Yes, only under §11 |
| Convert to or from baseline | — | **NO.** `kind` is never writable |

### 5.3 The routes, in full

```php
Route::middleware([RequireSystemAdministrator::class, RequireOrganisation::class])
    ->prefix('domains')
    ->name('domains.')
    ->group(function (): void {
        Route::get('/',   [DomainController::class, 'index'])->name('index');
        Route::post('/',  [DomainController::class, 'store'])->name('store');

        Route::get('{domain}',    [DomainController::class, 'show'])->name('show')->whereNumber('domain');
        Route::put('{domain}',    [DomainController::class, 'update'])->name('update')->whereNumber('domain');
        Route::delete('{domain}', [DomainController::class, 'purge'])->name('purge')->whereNumber('domain');

        Route::patch('{domain}/enable',      [DomainController::class, 'enable'])->name('enable')->whereNumber('domain');
        Route::patch('{domain}/disable',     [DomainController::class, 'disable'])->name('disable')->whereNumber('domain');
        Route::patch('{domain}/owner',       [DomainController::class, 'setOwner'])->name('owner.set')->whereNumber('domain');
        Route::patch('{domain}/owner/clear', [DomainController::class, 'clearOwner'])->name('owner.clear')->whereNumber('domain');
    });
```

**Route shape, and why it is safe.** `{domain}` is the only thing at its depth —
there is no static segment beside it, which is the collision P1-03 correction 1
was written for. `whereNumber` is defence in depth, not the mechanism.
`DomainRoutingTest` asserts the set still resolves identically **when the route
file is read in reverse**, which is the test that proves declaration order is not
doing the work.

### 5.4 The stable code versus the editable name — D-41

Two fields, two owners, and they must not be confused.

| | `code` | `name` |
| --- | --- | --- |
| **Whose word is it?** | **SemantIQ's.** Product vocabulary | **The organisation's.** Their word for it |
| **Editable?** | **Never**, on any route, by any path, baseline or custom | **Yes** |
| **Set when?** | At creation. Baseline codes ship with the product | At creation, and whenever they like afterwards |
| **Shape** | Lower-case, alphanumeric and hyphen, ≤ 32 | Free text, ≤ 255 |
| **Unique** | Per organisation | Per organisation |
| **Who reads it?** | **Every later unit.** P1-05 entitlements, P1-06 posture, P1-07 reviews, P1-08 audit, Phase 2 classification | People |

**Why the code is immutable.** Every later unit joins to a domain. A mutable
identity means those references silently retarget — the same rule, for the same
reason, as P1-03's `external_subject`. The worked example is the Product Owner's
own: **`sales` may display as *Commercial*, and its identity remains `sales`.**
The domain is still the same domain in every later unit and in any comparison
between two deployments.

**How it is enforced, not just intended:**

| Layer | Guard |
| --- | --- |
| Model | `code` is absent from `$fillable`; writes go through `forceFill` at creation only |
| Service | `update()` accepts `name`, `description` and `access_expectation`. There is **no parameter** for `code` — it is not sanitised out, it has nowhere to go |
| Request | An extra `code` field in a `PUT` is **ignored**, and N8 asserts the stored value is unchanged after posting one |
| Screen | Rendered read-only, with *"This never changes, even if the name does."* |

**`owner/clear` is a PATCH, not a DELETE.** In this codebase `DELETE` means *a
record is permanently destroyed*, and §16 asserts the application's complete set
of DELETE routes. Clearing an owner destroys nothing — it ends a period — so
making it a DELETE would both misdescribe it and weaken that assertion.

---

## 6. The ownership model

### 6.1 One source of truth

**`business_domain_owners` is authoritative. `business_domains` has no
`owner_user_id` column.** The PLAN's first draft proposed both; the Product
Owner rejected it before DESIGN.

| Question | Answered by |
| --- | --- |
| Who owns this now? | The row with `ended_at IS NULL` |
| Nobody? | **No such row.** Absence, not a NULL column |
| Who owned it before? | Every row with `ended_at` set |

### 6.2 The serialisation boundary — the DOMAIN row, not the ownership row

**Correction, Product Owner review of 3 September 2026. This replaces the
weaker boundary the first draft proposed.**

The first draft locked the current ownership row:

```
DomainOwnership::where(...)->whereNull('ended_at')->lockForUpdate()->first();
```

**That is not a boundary at all in the case that matters most.** When a domain
has **no** current owner there is **no row to lock**, so `lockForUpdate()` locks
nothing and two concurrent first-owner assignments both see "nobody owns this"
and both insert. The lock is strongest exactly when it is least needed and
absent exactly when it is needed most.

It is also the wrong *object*. Enable, disable, set owner, clear owner and purge
each read **two** things — the domain's `status` **and** its current ownership —
and decide from the pair. A lock on one of them cannot serialise a decision
taken over both.

> **The `business_domains` row is the serialisation boundary for every operation
> that can affect the D-42 invariant.**

### 6.2.1 The lock order, and it is the same everywhere

**Domain → ownership → dependency checks.** One order, in every service, so two
services cannot deadlock by approaching the same two tables from opposite ends.

Every one of `setOwner`, `clearOwner`, `enable`, `disable` and `purge` follows
exactly this shape:

```
DB::transaction(function () use ($domain) {
    // 1. THE BOUNDARY. Re-read the domain FROM THE DATABASE under a row lock.
    //    Not the model that was route-bound - that snapshot is already stale.
    $locked = BusinessDomain::query()
        ->whereKey($domain->getKey())
        ->lockForUpdate()
        ->firstOrFail();

    // 2. Ownership, now that nobody else can be inside this domain.
    $current = DomainOwnership::query()
        ->where('business_domain_id', $locked->id)
        ->whereNull('ended_at')
        ->lockForUpdate()
        ->first();

    // 3. Dependency checks, where the operation has any (purge only).

    // 4. RE-CHECK EVERY RULE against $locked and $current, not against
    //    anything read before the transaction opened.

    // 5. Write.
});                                                        // 6. Commit.
```

**Why `firstOrFail()` on a re-read rather than using the bound model.** Laravel
resolved `{domain}` before the transaction opened. Under MySQL's REPEATABLE READ
that instance is a snapshot from before the lock, and deciding from it would
make the lock decorative — the same class of mistake as checking a dependency
outside the transaction that deletes.

**Steps 4 and 5 must both sit inside.** A rule checked before `DB::transaction`
opens has already been overtaken.

### 6.2.2 The five races this must survive

Each is a test, not a claim. `DomainConcurrencyTest` runs each against **MySQL**,
because SQLite does not implement `SELECT ... FOR UPDATE` — the suite skips these
explicitly on SQLite and says so rather than passing vacuously, and CI runs them
in the MySQL step.

| # | Race | Must not be possible |
| --- | --- | --- |
| **C1** | Two concurrent **first-owner assignments** on an unowned domain | Two rows with `ended_at IS NULL`. **This is the race the ownership-row lock could not see** |
| **C2** | **Enable** racing **Clear owner** | Committing *Enabled with no current owner* |
| **C3** | **Clear owner** racing **Enable** — the same pair in the other order | Clearing the owner of a domain that has just become enabled, bypassing D-42 |
| **C4** | Two concurrent **owner replacements** | Two open rows, a lost period, or a period ended twice |
| **C5** | **Purge** racing a **first-owner assignment** | A purged domain leaving an orphan ownership row, or an ownership row surviving its domain |

**In every case the loser must be refused in a business sentence.** A serialised
transaction that then fails a foreign key or a uniqueness constraint has produced
a **database integrity error for an administrator who did nothing wrong** — which
is the exact defect P1-03 shipped and had to correct. Winning the race is not
enough; losing it has to be readable.

### 6.2.3 What did NOT change

The correction is to the **locking**, and to nothing else:

- the **ownership-history table stays authoritative**;
- there is still **no `owner_user_id`** on `business_domains`;
- **no ownership row is ever deleted**;
- the current owner is still, and only, the row with `ended_at IS NULL`.

### 6.3 The operations

**Setting an owner is ONE operation and one transaction**, whether or not
somebody already holds it. `DomainOwnershipService::setOwner()`, inside the
skeleton of §6.2.1:

```
// 1. lock the domain    2. lock the current ownership row (may be null)

refuseIfNotEligible($newOwner);                                    // §8
if ($current?->user_id === $newOwner->id) { return $current; }     // no-op

$current?->forceFill(['ended_at' => now()])->save();
DomainOwnership::create([... 'assigned_at' => now(), 'ended_at' => null]);

record(BUSINESS_DOMAIN_OWNER_ASSIGNED);
```

**It is never *clear, then assign*.** That sequence passes through an ownerless
state, which on an enabled domain is a state §7 refuses — so an implementation
built that way would either refuse a legitimate change or have to special-case
its way around its own rule. One transaction, one refusal surface.

**Reassigning the same person is a no-op**, not a new period. Two adjacent
periods for one person would be history that records nothing that happened.

**`clearOwner()`** ends the current row and inserts nothing. On an **enabled**
domain it is refused (§7).

**Nothing deletes an ownership row, and no route could.** Ending sets `ended_at`.

### 6.4 Why a lock is doing this at all

MySQL 8.4 has **no partial index**, so *at most one row per domain with
`ended_at IS NULL`* cannot be declared in the schema. It is enforced by the
locking reads of §6.2.1 — the mechanism P1-03 proved for group membership and
the D-24 purge guard, applied here to the **parent row** so that it holds when
there is no child row to lock.

**This is why the test must measure correctly.** P1-03 had two mutations
*survive* because the assertion measured `transactionLevel() > 0`, which is true
under `RefreshDatabase` regardless. The domain tests capture a **baseline
transaction level before the service call** and assert the increase. Stated here
so it is not rediscovered.

### 6.5 History is DATETIME, and `assigned_at` carries no uniqueness

`assigned_at` and `ended_at` are `DATETIME`. There is **no unique key involving
`assigned_at`**.

P1-01 keyed team membership on `(team_id, user_id, joined_at)` over dates, could
not represent two periods in one day, and P1-03 paid for that with correction 4
— then **production produced exactly that case on its first day of use**
(`P1-03-USERS-GROUPS-VERIFICATION.md` §12.1). The lesson is applied at the
schema here rather than learned a third time.

---

## 7. Enable requires an active owner — and where that rule lives

### 7.1 The rule

> **A domain may not be ENABLED unless it has a current owner who is an active,
> eligible user.**

| Transition | Behaviour |
| --- | --- |
| Disabled → Enabled, no current owner | **Refused.** *"Assign an owner before enabling this domain. Someone has to be accountable for it."* |
| Disabled → Enabled, current owner **inactive** | **Refused.** *"This domain's owner is no longer active. Assign an active owner before enabling it."* |
| Disabled → Enabled, current owner active | Permitted |
| Enabled → Disabled | **Always permitted.** Never refused, whatever the owner state, and it removes nothing |
| Clear owner while **enabled** | **Refused.** *"This domain is enabled. Assign a replacement owner, or disable it first."* |
| Clear owner while **disabled** | Permitted |
| Set owner, either status | Permitted, subject to §8 |

**Both enable refusals are re-checked inside the transaction, after the domain
row has been locked** — §6.2.1, lock order Domain → ownership. Checking them
before the transaction opens would leave races **C2** and **C3** open: an owner
cleared between the check and the write would commit *Enabled with no owner*,
which is the one state D-42 exists to make impossible.

The owner's **`status` is read from the locked read too**, not from a model
loaded earlier, for the same reason.

### 7.2 The invariant is enforced at the transition, not held continuously

**This is the most important sentence in the design and it is stated here rather
than discovered during EXECUTE.**

An enabled domain **can** end up with an inactive owner, because deactivating a
user is **P1-03's** operation and P1-04 does not get to refuse it. If the rule
were a continuous invariant, the only way to keep it true would be to block a
P1-03 deactivation — which **D-36 forbids**, because it makes a safe action
unsafe, and which would mean an administrator could not offboard somebody
without first hunting through domains.

So:

| | |
| --- | --- |
| **Enforced at** | the two moments an administrator asks — **enable**, and **clear owner** |
| **Not enforced at** | user deactivation. P1-04 adds **no** hook, check or listener there |
| **The resulting drift** | **surfaced, not prevented** — §8.3 |

**The domain is not silently disabled when its owner goes inactive.** Status is
an administrator's decision about whether the organisation uses a domain.
Changing it by side effect would make every *Enabled* on the screen mean
"enabled, unless something changed it for you", which is not a status anybody
can act on.

---

## 8. Owner eligibility, and the Needs attention state

### 8.1 Who may be set as owner

| Candidate | Newly assignable | May remain current | Retained in history |
| --- | --- | --- | --- |
| **Active** user, same organisation | **Yes** | Yes | Yes |
| **Inactive** user | **NO** — D-45 | **Yes** — surfaced | **Yes, always** |
| User with **no organisation** | **No.** D-16 fails closed, exactly as group membership does | n/a | n/a |
| User in a **different organisation** | **No** | n/a | n/a |
| A **group** | **No.** A committee is not accountable | n/a | n/a |

Refusal for the inactive case: *"That person's account is not active. Choose
someone who can sign in."*

**The owner picker offers active users of this organisation only.** The refusal
still exists and is still tested — a picker is a convenience, never the guard.

### 8.2 Deactivation is never blocked by P1-04

`UserDirectoryService::deactivate()` is **not modified**. P1-04 adds nothing to
it. §16 asserts this behaviourally: deactivate a user who owns three domains and
the deactivation succeeds, all three ownership rows are untouched, and all three
domains remain enabled.

### 8.3 Needs attention — owner inactive

A **derived** state. **No column stores it.**

> A domain is *Needs attention — owner inactive* when it has a current owner
> and that owner's `status` is not active.

| Where | How it appears |
| --- | --- |
| List | An attention pill beside the owner's name, and a filter value |
| Record | A banner in the Accountability section: **Needs attention — owner inactive.** *The domain remains enabled. Assign an active owner when you can. This ownership status does not change anyone's access.* |

It uses the **existing** `--badge-attention-bg` / `--badge-attention-fg` tokens
P1-02 added for its own third state. **No new colour token is introduced by this
unit.**

**The wording is deliberately un-alarming, and deliberately narrow.**

The first draft said *"This domain still works exactly as before."* Corrected at
Product Owner review: **P1-04 does not implement domain data access, so "works"
claims more than this unit can know.** It is the mirror image of the overclaim
`CLAUDE.md` §4 forbids — a reassurance about behaviour that does not exist yet.

The approved wording says only what is true today: **the status is unchanged,
and nobody's access moved** — which is the whole content of the news, because
in P1-04 there is no access to move. An alarm-coloured banner, or a broader
reassurance, would both be claims this unit cannot support.

---

## 9. Access expectation — wording, and its inertness

Stored as one of four values; **rendered as a sentence, never as the stored
value.**

| Stored | Shown | Meaning to the reader |
| --- | --- | --- |
| `undecided` | **Not yet determined** | The default |
| `broad` | **Broad access is expected** | |
| `limited` | **Access is expected to be limited to selected roles or functions** | |
| `exceptional` | **Access is expected to be tightly limited and reviewed** | |

Beneath the control, always:

> **This is a statement of intent. It does not grant or restrict anything today.**
> Access is assigned in Roles & Access.

### What the screen may not do

| Forbidden | Why |
| --- | --- |
| A lock or shield icon | Implies enforcement that does not exist |
| The word *policy*, *rule*, *enforced*, *secured* | Same |
| A red/amber "security" colour ramp across the four values | Makes an advisory field read as a control |
| Sorting or grouping the list *by* it as though it ranked risk | Same |

**P1-02 was corrected for exactly this class of overclaim** — it was not allowed
to say *"Sign-in works"* when it had only checked that settings loaded. §16 N27
removes the sentence and adds a lock icon as a deliberate mutation.

### `confidential` and `restricted` are not used here

Those words belong to P1-05's sensitivity dimension. An administrator who set
*Confidential* in an **advisory** field and later met *Confidential* in an
**enforced** one would reasonably believe they had already answered that
question, and a developer would reasonably assume the two should agree.
**Access expectation says how widely access is expected to be given; sensitivity
says what the data is.**

### Nothing reads it

No code branches on `access_expectation`. §16 asserts it across the whole
application source, not just the module.

---

## 10. List behaviour — search, filter, pagination

| Control | Behaviour |
| --- | --- |
| **Search** | Matches `name`, `code` and `description`, case-insensitively, on a substring |
| **Kind** | All / Baseline / Custom |
| **Status** | All / Enabled / Disabled |
| **Owner** | All / Assigned / Not assigned / **Needs attention** |
| **Pagination** | `Pagination.jsx`, 25 a page, with working Previous and Next |

Filters combine (AND) and survive pagination — every filter is carried in the
query string, so page 2 of a filtered list is still filtered. **P1-03 shipped
"Page 1 of 3" with no way to reach page 2**; that component now exists and is
reused rather than re-solved.

The filter bar uses `.org-filters`, added in P1-03 after every filter label
rendered jammed against its control. Reused, not re-styled.

**The *Needs attention* filter is computed from a join to `users`**, not from a
stored flag, consistent with §8.3.

---

## 11. Guarded purge — custom domains only

**Permanent removal of a `custom` domain, and only when all four hold:**

| # | Condition | Checked |
| --- | --- | --- |
| 1 | `kind = custom` | Before, and again inside, against the **locked** domain row |
| 2 | **No ownership row has ever existed** — current or ended | Before, and again inside |
| 3 | **No durable schema reference**, by `PurgeDependencies` | Before, and again inside |

**Purge follows the same lock order as everything else** — Domain → ownership →
dependency checks (§6.2.1). The domain row is locked first, so race **C5** —
purge against a first-owner assignment — is serialised rather than left to a
foreign key to catch after the fact. The loser is refused in the sentence below,
not with an integrity error.
| 4 | The administrator confirms in the `ConfirmPurge` dialog, which **shows the domain's name** so they can see which record, and gives **Cancel** the initial focus | Reused from P1-01, unchanged. It is not the guard — the server re-checks 1–3 inside the transaction |

**Conditions 2 and 3 agree by construction, and both are stated.** Because
ownership periods are rows with a foreign key to the domain, `PurgeDependencies`
already refuses any domain that ever had an owner. Condition 2 is written
explicitly **as well**, so the rule does not depend on anybody remembering why
the mechanism happens to work.

**Once a domain has history, disable rather than purge.** The refusal says so:

> *"This domain has ownership history. Disable it instead — that keeps the
> record of who was accountable for it."*

`PurgeDependencies::LABELS` gains one entry for `business_domain_owners`:
*"ownership history exists"* — uncounted, phrased as a fact, matching how
`team_memberships` and `group_memberships` are already worded.

**No baseline domain has a purge control at all**, and no route would accept
one: the purge path refuses `kind = baseline` before anything else.

> **The condition that will matter later.** `PurgeDependencies` is schema-driven,
> so a foreign key added by P1-05 blocks this purge with no change to P1-04 —
> the mechanism working. **But only for references expressed as foreign keys.**
> P1-03 found `bootstrap_grants.consumed_by_user_id` carrying no constraint,
> invisible to the walk. Every future reference to a domain must be a real
> constraint, and P1-05's design must be asked that question directly.

---

## 12. Schema

### `business_domains`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | `id()` | |
| `organisation_id` | `foreignId` | FK → `organisations`. **Never editable** |
| `code` | `string(32)` | Lower-case, alphanumeric and hyphen. **Never editable** |
| `name` | `string(255)` | Editable |
| `description` | `text` nullable | ≤ 500 validated |
| `kind` | `string(16)` | `baseline` \| `custom`. **Never from a request** |
| `status` | `string(16)` | `enabled` \| `disabled` |
| `access_expectation` | `string(16)` | Default `undecided` |
| `created_at` / `updated_at` | timestamps | |

```
unique (organisation_id, code)   business_domains_org_code_uq
unique (organisation_id, name)   business_domains_org_name_uq
foreign organisation_id          business_domains_org_fk
```

### `business_domain_owners`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | `id()` | |
| `business_domain_id` | `foreignId` | FK → `business_domains` |
| `user_id` | `foreignId` | FK → `users` |
| `assigned_at` | **`dateTime`** | Not a date |
| `ended_at` | **`dateTime`** nullable | `NULL` means current |
| `created_at` / `updated_at` | timestamps | |

```
index   (business_domain_id, ended_at)  domain_owners_domain_ended_idx
index   (user_id)                       domain_owners_user_idx
foreign business_domain_id              domain_owners_domain_fk
foreign user_id                         domain_owners_user_fk
```

**NO unique key involving `assigned_at`.** See §6.4.

Every identifier above is well inside MySQL's 64-character limit, which
`MigrationIdentifierLengthTest` already asserts — the explicit short names
follow the convention `create_group_memberships_table` established.

### Columns that are deliberately absent

| Absent | Why |
| --- | --- |
| `business_domains.owner_user_id` | §6.1. A second writable source of truth for one fact |
| Anything named `*sensitivity*` | **D-47.** P1-05 owns the whole dimension. P1-04 does not pre-model it, not even inertly |
| `needs_attention` or similar | §8.3. Derived, never stored |
| Anything containing `role`, `permission`, `scope`, `entitlement`, `ceiling`, `grant`, `allow`, `deny`, `visible_to` | §0 |

`DomainSchemaTest` asserts the **exact physical column set of both tables**,
timestamps included, and fails on any column name containing any of those words.

### Two architecture tests must be edited, and both edits are narrow

Neither is a formality. Each is a guard that currently says *this unit has not
been delivered*, and each becomes **false** the moment it is — so each is
changed as a **reviewed transfer**, exactly the way `organisations`, `teams` and
`business_units` were transferred to P1-01 and `users` to P1-00.

| Test | Edit | What must NOT be edited with it |
| --- | --- | --- |
| `NoBusinessSchemaTest::FORBIDDEN` | Remove **`domains`** and **`business_domains`** | **`roles`, `permissions`, `scopes`, `sensitivity`, `entitlements`, `audit`, `access_reviews`, `fabric` all stay forbidden.** That list staying intact is itself the proof P1-04 did not pre-build P1-05 |
| `NoBusinessSchemaTest::test_only_delivered_modules_exist` | Add **`Domains`** to the expected module list | Nothing else. A directory is never pre-created to reserve a name |

**The forbidden list is the cheapest zero-entitlement guard in the codebase**,
and it is worth naming explicitly: if EXECUTE ever needs to remove `sensitivity`
or `scopes` from it, the unit has left its scope and the test has just said so.

### Migrations

Two, `2026_09_03_000001_create_business_domains_table.php` and
`..._000002_create_business_domain_owners_table.php`. Both `up()` and `down()`.

**Neither migration writes a row.** §16, N30e asserts it against the migration
source. Structure is a migration's job; the organisation's rows are not.

---

## 13. Authorization and refusals

| # | Rule |
| --- | --- |
| **S1** | Every route behind `RequireSystemAdministrator` **and** `RequireOrganisation` |
| **S2** | Anonymous → the entry page. Authenticated non-administrator → refused, on **every** route including the record routes |
| **S3** | Authentication is decided **before** any record is looked up, so a response cannot reveal whether a domain id exists |
| **S4** | A domain belonging to another organisation is **404 Not Found**, never 403 — the P1-03 `refuseIfOutsideOrganisation()` shape, reused. *Forbidden* would confirm the record exists |
| **S5** | `code`, `kind` and `organisation_id` are **not fillable** and are never read from a request after creation. Ignored, not sanitised |
| **S6** | **No route writes `platform_role`**, or any column on `users` |
| **S7** | Every refusal is a **business sentence**. No database, constraint or foreign-key wording ever reaches an administrator |
| **S8** | Every write confirms itself in past tense, carrying no business content: *"Domain enabled."*, *"Owner assigned."*, *"Domain removed."* |
| **S9** | Uniqueness is pre-checked with a locking read so the administrator gets a sentence; **the database constraint is still the real guard** |
| **S10** | Assigning an owner changes **nothing** about that user |
| **S11** | **P1-04 never refuses a P1-03 deactivation** |
| **S12** | `access_expectation` and `status` are never read to make an authorization decision, anywhere in the application |

### The refusal sentences, in full

| Situation | Sentence |
| --- | --- |
| Duplicate name | *"A domain called that already exists. Open it, or choose another name."* |
| Duplicate code | *"That code is already used by another domain."* |
| Reserved baseline code | *"That code is reserved for a standard domain."* |
| Enable, no owner | *"Assign an owner before enabling this domain. Someone has to be accountable for it."* |
| Enable, inactive owner | *"This domain's owner is no longer active. Assign an active owner before enabling it."* |
| Clear owner while enabled | *"This domain is enabled. Assign a replacement owner, or disable it first."* |
| Inactive candidate | *"That person's account is not active. Choose someone who can sign in."* |
| Owner outside the organisation | *"That person is not part of this organisation."* |
| Purge, baseline | *"Standard domains cannot be removed. Disable it instead."* |
| Purge, has history | *"This domain has ownership history. Disable it instead — that keeps the record of who was accountable for it."* |

**A duplicate must never surface as an integrity error.** P1-03 shipped exactly
that defect and it was found by reading the test script back against the code,
not by a failing test — gap 6 of its verification. The pre-check exists so the
administrator gets a sentence; the constraint exists so the pre-check cannot be
the only thing standing there.

---

## 14. Reserved codes

A custom domain **may not use a baseline code**, in any deployment, **even where
that baseline domain is disabled**.

The check is against `BaselineDomains::CODES` — the closed set — **not against
the rows currently present or enabled**. §16, N10 mutates it to check only
enabled rows, which is the version somebody who misunderstood the rule would
write.

---

## 15. Security events

**Seven events. No new context key.**

| Constant | Value |
| --- | --- |
| `BUSINESS_DOMAIN_CREATED` | `business_domain.created` |
| `BUSINESS_DOMAIN_UPDATED` | `business_domain.updated` |
| `BUSINESS_DOMAIN_ENABLED` | `business_domain.enabled` |
| `BUSINESS_DOMAIN_DISABLED` | `business_domain.disabled` |
| `BUSINESS_DOMAIN_PURGED` | `business_domain.purged` |
| `BUSINESS_DOMAIN_OWNER_ASSIGNED` | `business_domain.owner.assigned` |
| `BUSINESS_DOMAIN_OWNER_CLEARED` | `business_domain.owner.cleared` |

Context, drawn **entirely** from the existing `ALLOWED_KEYS`:

| Key | Carries |
| --- | --- |
| `entity_type` | `business_domain` |
| `entity_id` | The domain's id |
| `organisation_id` | The organisation's id |
| `user_id` | The **acting** administrator, or `null` for the one-time initialisation |
| `related_id` | The **owner's** user id, on the two owner events only |
| `result` | `created` \| `updated` \| `enabled` \| `disabled` \| `purged` \| `assigned` \| `cleared` \| `initialised` |

**`SecurityEventLogger::ALLOWED_KEYS` is not modified.** That is the point of the
D-12 boundary: a domain's **name**, its **code** and its **description** are
business content, there is no key for free text, and so a leak here is
*unrepresentable* rather than merely discouraged. A design that needed a new key
would be a design putting business content in the log.

**Initialisation records one `business_domain.created` per row it actually
creates**, with `result => 'initialised'`. Rows already present record nothing —
an event for something that did not happen is a false line in an audit trail.

### What is deliberately NOT logged

**Refusals.** P1-02's own note says a screen is not logged merely because it is
sensitive, because volume buries what matters and P1-08 inherits the noise.
`user.provision.refused` exists because a failed provision can indicate
enumeration; being told *"assign an owner first"* cannot indicate anything. If
P1-08 later wants attempted-change telemetry, it can decide that with the whole
picture.

---

## 16. The zero-entitlement guards

Every one is broken deliberately and observed to fail — `CLAUDE.md` §2. The
mutation named is the one **a person who misunderstood the rule would plausibly
write**, not the one that is easiest to make fail.

### The boundary

| # | Guard | Mutation |
| --- | --- | --- |
| **N1** | Anonymous and non-administrator refused on **every** route | Drop the gate from one route |
| **N2** | Assigning an owner writes **nothing** to `users` — no role, no group, no membership, no column | Have `setOwner()` write `platform_role` |
| **N3** | **Guard A.** Within `app/`, nothing outside `App\Modules\Domains` references Domains except the three wiring/integration exceptions of §1. Migrations, tests and `resources/js` are **out of this guard's scope** and the test says so in its own name and message | Add a `BusinessDomain` reference to an unrelated service. **And the second mutation that matters: add the reference and then widen the exception list to admit it** — which is how a boundary is really lost |
| **N3e** | **Guard B.** No middleware, policy, gate, authorization service or module **anywhere** — Domains included — reads `business_domains`, `DomainStatus`, `access_expectation` or ownership to decide what somebody may see or do. Scanned across `app/` **and** `resources/js/` | Have `RequireSystemAdministrator` consult a domain; have a React component hide a **menu entry** on `DomainStatus`. Both must fail |
| **N3f** | Guard A **is not vacuous** — it actually finds a violation when one exists | Point the scanner at a directory that contains none, so it passes by scanning nothing. The test asserts it scanned a **non-zero number of files** |
| **N3b** | Both tables have **exactly** their physical column sets; **no column name contains** role, permission, scope, sensitivity, entitlement, ceiling, grant, allow or deny | Add `grantee_role`; then add it **and** update the expected list without reading why the list is there |
| **N3c** | **`business_domains` has no `owner_user_id`** | Add the column and write both. **The test fails on the column's existence**, not on the two disagreeing — a duplicate source of truth is wrong even while it agrees |
| **N3d** | **No column name contains `sensitivity`** | Add `sensitivity_expectation` back |
| **N4** | No P1-04 path writes `platform_role` | Add it to an accepted request field |
| **N5** | `PlatformRole` still has exactly one case | Add a second |
| **N6** | **An owner gets the identical response to a non-owner on every route** | Have any route consult ownership |
| **N7** | The complete set of application `DELETE` routes is the approved one | Add one |
| **N7b** | `NoBusinessSchemaTest::FORBIDDEN` still forbids **`roles`, `permissions`, `scopes`, `sensitivity`, `entitlements`, `audit`, `access_reviews`, `fabric`** after the transfer of §12 | Remove `scopes` or `sensitivity` along with `business_domains` — the plausible over-reach when making a red test green |
| **N7c** | The delivered-module list is **exactly** `Domains, Identity, Organisation, People, Platform` | Add an `Access` directory to reserve the name |

### Identity and lifecycle

| # | Guard | Mutation |
| --- | --- | --- |
| **N8** | `code` is not editable on **any** route | Make it fillable |
| **N9** | `kind` in a request is ignored | Accept it |
| **N10** | A reserved baseline code is refused **even when that baseline is disabled** | Check only the enabled rows |
| **N11** | Duplicate name and code refused **in business language** | Remove the pre-check and let the constraint surface |
| **N12** | Renaming leaves `code` and every reference unchanged | Key anything on `name` |
| **N13** | No route creates or deletes a **baseline** domain | Register one |
| **N14** | **Every operation named in §5 exists** as a service method | Delete one — the P1-01 presence guard |
| **N15** | Every write confirms itself | Return a bare redirect |
| **N16** | The route set resolves identically **with the file reversed** | Put a dynamic segment at a static one's depth |
| **N17** | A domain of another organisation is **404** | Return 403 |

### Ownership

| # | Guard | Mutation |
| --- | --- | --- |
| **N18** | An owner from another organisation is refused | Drop the same-organisation check |
| **N19** | A user with **no organisation** cannot own | Let `NULL` pass |
| **N20** | At most one current owner, held by a locking read **inside** the transaction, measured against a **baseline transaction level** | Remove the lock; move the check outside; and measure with `transactionLevel() > 0`, which is the mistake that let two P1-03 mutations survive |
| **N21** | Changing owner **retains** the previous period | Update the row in place |
| **N22** | Ownership rows cannot be deleted by any route | Add one |
| **N23** | **Two ownership periods on one calendar day** are both recorded | Key ownership on `(business_domain_id, assigned_at)` over dates — the P1-01 collision |
| **N24** | An **inactive** user cannot be newly assigned | Allow it |
| **N25** | **Deactivating an owner is never refused**, their ownership is untouched, and their domains stay enabled | Have the owner check refuse the deactivation; have it clear the ownership |
| **N26** | The current owner is read from the **open row**, never a cached field | Answer it from anywhere else |
| **N27** | Setting an owner is **one transaction** and never passes through an ownerless state | Implement it as clear-then-assign |
| **N28** | Reassigning the **same** person creates no new period | Always insert |

### Concurrency — §6.2, and MySQL only

SQLite does not implement `SELECT ... FOR UPDATE`. These cases **skip explicitly
on SQLite with a stated reason** rather than passing vacuously, and CI runs them
in the MySQL step where they are real.

| # | Race | Mutation |
| --- | --- | --- |
| **C1** | Two concurrent **first-owner assignments** cannot create two current owners | **Lock the ownership row instead of the domain row** — the first draft's design. With no current owner there is no row to lock, so both transactions insert. This mutation must fail, and it is the reason the correction exists |
| **C2** | **Enable** racing **Clear owner** cannot commit *Enabled with no owner* | Check the owner **before** `DB::transaction` opens |
| **C3** | **Clear owner** racing **Enable** cannot bypass D-42 | Decide from the **route-bound** `$domain` instead of re-reading it under the lock — the stale-snapshot mistake |
| **C4** | Two concurrent **owner replacements** leave one open period and no lost history | Drop the domain lock and rely on the ownership lock alone |
| **C5** | **Purge** racing a **first-owner assignment** leaves no orphan row either way | Run the dependency walk before the transaction |
| **C6** | **The loser of every race above is refused in a business sentence** — no integrity error, no constraint text, no 500 | Remove the in-transaction re-check and let the database refuse. The test asserts on the **message the administrator sees**, not merely that something failed |
| **C7** | Every one of the five operations uses the **same lock order** — domain, then ownership, then dependencies | Reverse it in one service. Asserted by reading the service source for the order of the two locking reads, so a future service cannot quietly invent its own |

### Enable, disable, expectation

| # | Guard | Mutation |
| --- | --- | --- |
| **N29** | **Enable with no owner is refused**, naming the remedy | Drop the check; check that an ownership row exists **ever**, rather than a current one |
| **N30** | **Enable with an inactive owner is refused** | Check for a current owner without checking they are active |
| **N31** | **Clear owner is refused while enabled**, permitted while disabled | Allow it in both; refuse it in both |
| **N32** | **Disable is never refused** | Add any condition |
| **N33** | Disabling deletes nothing and keeps owner and history | Have disable clear the owner |
| **N34** | **Nothing reads `status` to authorize** | Have any path branch on it |
| **N35** | **Nothing reads `access_expectation` to decide anything** | Have any path branch on it |
| **N36** | The inert-statement sentence is in the **rendered** screen source | Remove it; add a lock icon or the word *policy*. Asserted through `ScreenSource::rendered()`, because P1-03 had this exact assertion pass against a docblock |

### Initialisation

| # | Guard | Mutation |
| --- | --- | --- |
| **N37** | Initialisation is **idempotent** — twice gives seven, not fourteen | Key it on anything but `code`; drop the existence check |
| **N38** | Run **after** a rename, an enable or an owner assignment, it **changes none of that** | Have it reset `name` or `status` |
| **N39** | The seven arrive **Disabled, unowned, `undecided`** | Create them enabled, or owned by the creating administrator |
| **N40** | **No migration writes a `business_domains` row** | Move initialisation into a migration |
| **N41** | **No GET creates a domain.** Loading the list with an empty table leaves it empty | Materialise on first view |
| **N42** | `BaselineDomains` holds **exactly** seven codes | Add an eighth — *Custom* — which is the D-44 mistake |
| **N43** | Initialisation with no organisation refuses and creates nothing | Let it write with `organisation_id` null |

### Purge, presentation, events

| # | Guard | Mutation |
| --- | --- | --- |
| **N44** | Purge refuses a **baseline** domain | Check only the dependency walk |
| **N45** | Purge refuses a domain with **any** ownership history, current or ended | Check only current ownership |
| **N46** | The dependency check is **repeated inside** the transaction | Check once, before |
| **N47** | No security event carries a name, code, description, email or identifier | Add one to the context |
| **N48** | `ALLOWED_KEYS` is unchanged by this unit | Add a key |
| **N49** | Search, filter and pagination work against seeded volume, and filters survive paging | Remove the limit; ignore the filter; drop the query string from the pager |
| **N50** | Empty-because-no-domains and empty-because-filtered say **different things** | Use one message for both |
| **N51** | Every Domains surface renders in both themes | Use a raw semantic hex instead of a token |
| **N52** | **No database integrity error reaches the administrator** | Let a constraint surface instead of the service refusing first |

**N14, N13 and N7 are the presence guards**, and they exist because of P1-01: an
operation that does not exist has no test to fail. §5 names every operation
precisely so these can be written before the implementation is.

---

## 17. The carried gate this unit creates

| Gate | To | Why it cannot run here |
| --- | --- | --- |
| **Disabling a domain must never BROADEN effective access** | **P1-05** | P1-04 ships no code that reads `status` to decide anything, so the failure is **unreachable and untestable here**. It becomes reachable the moment P1-05 builds effective access |

**The failure it anticipates is concrete.** The natural implementation of
"disabled" in P1-05 is *a filter that removes the domain from a set*. Written
carelessly — a filter skipped when the set is empty, a default that treats "no
domains enabled" as "no restriction" — **disabling every domain becomes allow
everything.**

**To be recorded in `PHASE-1-PLAN.md` §10 when P1-04 is accepted**, because that
register has already lost two gates once and the reason it exists is that a
carried gate is otherwise quietly forgotten.

---

## 18. Product Owner test script — outline

The full script is written at TEST, with all twelve `CLAUDE.md` §3 elements. Its
shape:

| Element | Content |
| --- | --- |
| **1. Feature** | P1-04 — Business Domains |
| **2. Build** | The implementation merge SHA, recorded at the time |
| **3. Preconditions** | Signed in as a System Administrator; the organisation exists; **the seven baseline domains have been initialised** and the run recorded |
| **4. Test data** | One custom domain the Product Owner invents; two existing genuine users as owners. **No user is created for this test** |
| **5. ⚠ Permanence warning** | **A domain that has ever had an owner can never be removed.** Assigning an owner is the step that makes a domain permanent. Said **before** the first step that assigns one, not in a footnote |
| **6–7. Steps and expected results** | Below |
| **8. Negative cases** | The refusals of §13, each one exercised deliberately |
| **9. Visual and UX** | Both themes; small width; the *no access* sentence; the inert-statement sentence; no lock icon; the attention pill legible; empty states; console clean |
| **10. Evidence** | A screenshot per refusal and per confirmation; the ownership history showing two periods |
| **11. PASS / FAIL** | Per step |
| **12. Not testable, and why** | Below |

### The steps, in order

| # | Step | Expected |
| --- | --- | --- |
| 1 | Open **Business Domains** from the sidebar | It is a link. Seven baseline domains, **all Disabled, none owned**, all *Not yet determined* |
| 2 | Open **Finance** | Code `finance` shown read-only, with *"This never changes, even if the name does."* |
| 3 | **Enable** it with no owner | **Refused:** *"Assign an owner before enabling this domain..."* |
| 4 | Assign yourself as owner | Confirmed. History shows one open period |
| 5 | Read the Accountability section | **The owner does not get access to it** is present |
| 6 | **Enable** it | Confirmed |
| 7 | **Clear owner** | **Refused:** *"This domain is enabled. Assign a replacement owner, or disable it first."* |
| 8 | **Change owner** to a second user | Confirmed. **Two periods**, the first ended, the second open |
| 9 | Rename **Sales** to **Commercial** | Name changes; **code still `sales`** |
| 10 | Create a custom domain | Confirmed |
| 11 | Create it again | **Refused**, in a sentence — **not a database error** |
| 12 | Create one with the code `finance` | **Refused:** *"That code is reserved for a standard domain."* |
| 13 | Set an access expectation | Confirmed, and the inert-statement sentence is visible |
| 14 | Purge the custom domain **before** giving it an owner | The confirmation dialog names it; Cancel is focused. Permitted on confirm |
| 15 | Create another, assign an owner, then try to purge | **Refused:** *"This domain has ownership history. Disable it instead..."* |
| 16 | **Deactivate** the user who owns Finance, in Users & Groups | **Permitted. Not blocked** |
| 17 | Return to Business Domains | Finance shows **Needs attention — owner inactive**, and is **still Enabled** |
| 18 | Assign an active owner | The attention state clears |
| 19 | Try to assign the inactive user | **Refused:** *"That person's account is not active."* |
| 20 | **Disable** Finance | Permitted, always. Owner and history intact |

### Anticipated "not currently observable"

Stated in advance so it is not presented later as a pass:

- **That disabling a domain does not broaden access.** There is no access. §17's
  carried gate, and it belongs to P1-05.
- **Search and filter at real volume.** They will be *exercised* against a
  handful of real domains and *stressed* only against seeded volume in the test
  suite. That distinction is stated rather than blurred.
- **Step 16 creates permanent history.** Deactivation is reversible;
  reactivation is a documented step, and the script says which of its data
  cannot be undone before the Product Owner types anything.

---

## 19. What this design deliberately does not build

- **No role assignment, no domain entitlement, no scope, no sensitivity, no
  effective access, no Access Simulator, no domain-derived authorization.** P1-05.
- **No sensitivity of any kind** — D-47. Not the ceiling, not an inert statement,
  not the vocabulary.
- **No mapping of source data to a domain.** Phase 2.
- **No durable audit table.** P1-08.
- **No navigation or branding change.** *Business Domains* moves from `locked()`
  to `leaf()` in `ApprovedMenu` with its route — the single edit that entry was
  designed for. Nothing else in the menu changes.
- **No new colour token.** §8.3 reuses P1-02's attention tokens.
- **No change to `SecurityEventLogger::ALLOWED_KEYS`.**
- **No change to the `srikanth@lithan.com` record.** It stays exactly as P1-03
  left it — a separate operational item
  (`P1-03-USERS-GROUPS-VERIFICATION.md` §12.3), and P1-04 does not touch or
  purge it.

---

**P1-04 DESIGN — APPROVED 3 September 2026** with the three corrections above
applied. No further DESIGN review is required. EXECUTE follows.
