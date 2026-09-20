# P1-10 — Platform Integrations & Setup: DESIGN

**DESIGN ONLY.** No implementation, no migration, no deployment.

| | |
| --- | --- |
| Unit | **P1-10 — Platform Integrations & Setup** (delivery order 12) |
| PLAN | merge `b26c07b33046673758ff429452547a9954328644` — **D-148 – D-178 ANSWERED** |
| Authority | `PRODUCT-OWNER-AMENDMENT-PLATFORM-SETUP-AND-BOOTSTRAP.md`, merge `09855b0` |
| **Schema** | **FIVE TABLES.** Justified one by one in §11 |
| Corrections | **Six applied** — indexed in §14. **Three were defects the Product Owner found that this design had missed** |
| Followed by | **P1-11 — Administration Home**, deferred |
| Status | **AWAITING PRODUCT OWNER REVIEW** |

---

## 0. The four sentences this design serves

1. **A credential lives in exactly one place.** After cutover, `.env` is not an
   identity authority and there is no fallback to it.
2. **A connection is not a capability.** The AI and Fabric tests prove reach
   and credentials, and are structurally incapable of doing more.
3. **The Bootstrap principal cannot become a user.** Not by a flag, not by a
   cast, not by a role — it is a different type with a different guard.
4. **Bootstrap closes by a write, atomically with the transition that makes it
   unnecessary** — not because a session expired, a file was deleted or
   somebody pressed a button, and **not merely while a computed predicate
   happens to hold.** Once closed it reopens only when a trusted operator
   deliberately opens it (§4.5, Correction 3).

---

## 1. THE SHARPEST CONSTRAINT, found by reading the container

**`PlatformServiceProvider::registerIdentity()` bakes the tenant into three
singletons at first resolution:**

```php
$this->app->singleton(EntraDiscovery::class, fn () => new EntraDiscovery(
    (string) config('identity.microsoft.tenant_id'),
));
$this->app->singleton(IdTokenValidator::class, fn ($app) => new IdTokenValidator(
    $app->make(EntraDiscovery::class),
    (string) config('identity.microsoft.client_id'),
    (string) config('identity.microsoft.tenant_id'),
));
$this->app->singleton(IdentityProvider::class, fn ($app) => new EntraProvider(
    …, tenant, client, SECRET, redirect,
));
```

**This is why "just read from the database instead" is not a one-line change.**

- The bindings are **closures**, so they do not run at boot — but they run
  **once per request, at first resolution**, and the values are then fixed for
  that request.
- `EntraDiscovery`'s **cache keys are `semantiq:entra:{tenant}:metadata|jwks`**.
  A tenant changed mid-request leaves the old tenant's cached trust in play.
- During First-Run the administrator **changes the tenant and then immediately
  tests it**. If the singleton was already resolved in that request, the test
  validates the *previous* configuration and reports success.

### The resolution — §3.3

**Configuration is resolved per resolution, not per process, and the test path
constructs its own provider rather than reusing the container's.** A saved
configuration is only adopted by the container's singleton on the **next**
request; the verification step uses an explicitly constructed instance bound to
the configuration being tested. **The two are never the same object**, which is
what makes "test what I just typed" mean what it says.

> **This would have been a defect exactly like P1-09's cold-cache one:** a
> guarantee that holds in the steady state and fails on the path the feature
> exists for.

---

## 2. Route and screen map

### 2.1 Normal Platform Integrations — `PlatformAdmin`, D-150

| Verb | Route | Purpose |
| --- | --- | --- |
| GET | `/console/platform-integrations` | The landing page, four cards (D-148) |
| GET | `/console/platform-integrations/email` | SMTP detail |
| GET | `/console/platform-integrations/ai` | AI provider detail |
| GET | `/console/platform-integrations/fabric` | Fabric detail |
| PUT | `/console/platform-integrations/email` | Save SMTP configuration |
| PUT | `/console/platform-integrations/ai` | Save AI configuration |
| PUT | `/console/platform-integrations/fabric` | Save Fabric configuration |
| POST | `/console/platform-integrations/{family}/test` | One explicit test (D-157) |

**There is no Identity/SSO route under this prefix.** The Identity card
**links** to `identity.entra`, which P1-02 owns (D-148). An architecture guard
asserts the prefix contains no identity route and the module names no Entra
configuration key.

**No DELETE.** Removing an integration is `PUT` with cleared fields, which goes
through the same typed validation and the same Audit event as any other change.
A delete verb would be a second write path with different rules.

### 2.2 First-Run Setup — the Bootstrap guard, NOT `PlatformAdmin`

Registered under `/first-run`, **outside** the `console` prefix and **outside**
`EnsureSessionIsCurrent`, because that middleware resolves a `User` and the
Bootstrap principal is not one (D-166).

> ### CORRECTION 1 — the static routes collide with `/first-run/{grant}`
>
> **The existing route file already reads:**
>
> ```php
> Route::prefix('first-run')->name('first_run.')->group(function (): void {
>     Route::get('closed', fn () => (new StateController)('bootstrap-closed'))->name('closed');
>     Route::get('{grant}', BeginController::class)->name('begin');
> });
> ```
>
> `{grant}` is **unconstrained**, and `closed` survives only because it is
> declared **first**. Every static route this design adds sits at the same
> depth, so `/first-run/sign-in` would be captured as a grant token the moment
> declaration order changed — and **order is not a guarantee**, it is a habit.
>
> **This is the collision class P1-03 correction 1 and P1-04 were written
> against**, arriving a third time.
>
> **The fix is structural.** `GrantIssuer` generates `Str::random(64)`, so the
> grant route is constrained to that exact shape:
>
> ```php
> Route::get('{grant}', BeginController::class)
>     ->where('grant', '[A-Za-z0-9]{64}')
>     ->name('begin');
> ```
>
> **No static route can match it**: `sign-in`, `identity`, `email`, `ai`,
> `fabric`, `first-administrator`, `complete` and `closed` are all far shorter
> than 64 characters and several contain a hyphen. The constraint is not a
> convenience — it makes the ambiguity **unrepresentable**.
>
> **Guard — `FirstRunRoutesDoNotCollide`.** It resolves every static First-Run
> URI through Laravel's router and asserts each reaches its own controller,
> **with the route collection deliberately re-registered in reverse declaration
> order**, so a test that passes only because of ordering fails.
> **Mutation: remove the `where()` constraint.** The reversed-order case must
> fail.

| Verb | Route | Step |
| --- | --- | --- |
| GET / POST | `/first-run/sign-in` | 1 — Bootstrap Login |
| GET | `/first-run` | 2 — Setup Overview |
| GET / PUT | `/first-run/identity` | 3 — Identity/SSO *(invokes P1-02's service)* |
| GET / PUT | `/first-run/email` | 4 — optional |
| GET / PUT | `/first-run/ai` | 5 — optional |
| GET / PUT | `/first-run/fabric` | 6 — optional |
| GET / PUT | `/first-run/first-administrator` | 7 — nomination |
| POST | `/first-run/{family}/test` | Connection tests |
| GET | `/first-run/complete` | 9 — Setup Complete |
| POST | `/first-run/sign-out` | Ends the bootstrap session |

**Step 8 is not a route here.** The nominated administrator signs in through
**the existing `/auth/microsoft` path, unchanged** — which is the whole point:
the permanent administrator uses the normal identity path.

> ### CORRECTION 2 — "nominate then sign in" was not a design
>
> **The first draft said the nomination is an email field and the administrator
> then signs in normally. That cannot work**, and reading `CallbackController`
> shows why:
>
> ```php
> $grant = $request->session()->pull(BeginController::SESSION_GRANT);
> $user  = is_string($grant) && $grant !== ''
>        ? $this->completeBootstrap($grant, $identity)   // ← the ONLY path that
>        : …                                             //   creates the first
>                                                        //   System Administrator
> ```
>
> **An ordinary sign-in with no grant in session creates nobody.** The
> nomination screen would have "worked", the administrator would have signed in,
> and the installation would have been left with **no administrator and no
> explanation.**
>
> ### The exact handoff — reusing `GrantIssuer` and `GrantRedeemer` unchanged
>
> ```text
> STEP 7  Bootstrap captures the nominated email/UPN
>           ↓
>         Bootstrap RE-CONFIRMS ITS OWN LOCAL CREDENTIAL      (D-159)
>           ↓
>         tenant is read from the VERIFIED STORED Entra configuration
>         — never typed again, never taken from the form
>           ↓
>         GrantIssuer::issue(subject: nominated, tenant: stored)
>         → existing semantics: Str::random(64), SHA-256 at rest,
>           30-minute TTL, single-use, atomic consumption
>           ↓
>         the one-time link /first-run/{grant} is DISPLAYED ONCE
>           ↓
> STEP 8  nominated administrator opens it → BeginController puts the grant in
>         THEIR session → /auth/microsoft → callback → GrantRedeemer
>           ↓
>         GrantRedeemer remains THE authority that creates the first
>         System Administrator. No second mechanism exists.
> ```
>
> | Case | Behaviour |
> | --- | --- |
> | **Same browser** | The bootstrap principal opens the link itself. The grant enters the same session; the bootstrap session is **separate state** and is not disturbed |
> | **Different person** | The link is **shown once, on screen, for the operator to convey out of band.** It is never emailed — **email is optional in First-Run (D-171)** and a mandatory email dependency here would make a mandatory step depend on an optional one |
> | **Conveyance** | Displayed once and **never persisted, never logged, never in Audit context, never re-displayable.** Only its SHA-256 is stored, exactly as P1-00 established. Leaving the screen loses it, and a fresh grant must be issued |
> | **Wrong identity** | `GrantRedeemer` refuses **without consuming** — D-03 rule 7, already implemented and verified in P1-00 |
> | **Mismatch or expiry** | Refusal screen; bootstrap remains open; the nomination can be corrected and re-issued (D-170) |
> | **Success** | Atomic consumption, first System Administrator created, `BootstrapState` becomes CONFIGURED |
>
> **Nothing about `GrantIssuer` or `GrantRedeemer` changes.** P1-10 calls them.
> The only new thing is *who* asks for the grant — an authenticated bootstrap
> principal instead of an operator at an SSH prompt.

### 2.3 The authorisation boundary, as a table

| | Normal `PlatformAdmin` | Bootstrap principal |
| --- | --- | --- |
| Guard | `EnsureSessionIsCurrent` + `RequireActionClass:platform_admin` | `RequireBootstrapSession` |
| Principal type | `User` | `BootstrapPrincipal` — **not** a `User` |
| `RoleCatalogue` | Holds a role | **Never enters it** |
| `ActionClass` | Yes | **Never obtains one** |
| Domain entitlement | Per P1-05 | **Never** |
| Reaches `/console/*` | Yes | **No — every console route requires a `User`** |
| Reaches `/first-run/*` | **No** | Yes, while UNCONFIGURED |
| Identity configuration | Through P1-02's screens | Through P1-02's **service**, via step 3 |

**The two surfaces share no route and no middleware.** That is the structural
guarantee, and `BootstrapCannotReachConsole` asserts it as an equality over the
route table rather than by testing a handful of URLs.

---

## 3. Identity configuration — the migration, not a second model

### 3.1 Who owns what

```text
P1-10  First-Run identity screen  ──invokes──▶  P1-02  IdentityConfigurationWriter
                                                        │  (owned by the Identity module)
                                                        ▼
                                               integration_configurations
                                               integration_secrets
                                                        ▲
P1-02  Entra screens, health, provider  ──reads──┘
```

**P1-10 writes no identity configuration itself.** It renders a form and calls
P1-02's writer. **The writer, the validation and the typed shape all live in the
Identity module**, which is what keeps the single-owner rule true rather than
merely stated.

### 3.2 The runtime source, and the absence of a fallback

`config/identity.php` stops being the authority. A resolver reads the stored
configuration and **falls back to `.env` only while the cutover is
incomplete**, governed by a single stored flag:

| `identity_source` | Meaning |
| --- | --- |
| `env` | Pre-cutover. Read `.env`. **The only state in which `.env` is authority** |
| `store` | Post-cutover. **Read the store. `.env` is not consulted at all** |

**There is no third state and no "try the store, fall back to `.env`".** A
silent fallback is how two credential authorities coexist, and it fails in the
worst way: the store is edited, the old `.env` value keeps working, and nobody
notices until the `.env` secret expires.

**Guard:** `EnvIsNotIdentityAuthorityAfterCutover` asserts that the resolver has
exactly two branches, that the `store` branch reads no `env()`, and that no
`??` couples them.

**A naming note, found by reading the tree rather than by naming from scratch.**
`App\Modules\Platform\Identity\IdentityResolver` **already exists** and does
something entirely different: it maps an already-verified external identity to
an existing `User`, and never creates one (SYS-014, SYS-015). Calling this class
`IdentityResolver` would put two unrelated jobs behind one word in one
namespace. **This one is `IdentityConfigurationSource`** — it answers *where
the identity configuration comes from*, and the existing class keeps its name
and its meaning untouched.

### 3.3 Per-resolution configuration — §1's resolution

- The three container bindings resolve their values **from the resolver, at
  resolution time**, not from `config()` captured earlier.
- **The test path never uses the container's singleton.** A `ProviderProbe`
  constructs a throwaway `EntraDiscovery` / `EntraProvider` from the
  **candidate** configuration, with **its own cache namespace**
  (`semantiq:entra:probe:{hash}`) so a probe cannot poison the live trust cache
  or read it.
- **Test A1** saves a new tenant and tests it **in the same request**, asserting
  the probe used the new tenant. Its mutation — reuse the container's provider —
  must fail it.

### 3.4 The cutover, step by exact step

```text
1. PRE-CHECK      operator runs `semantiq:identity-cutover --check`
                  → reports, from .env: present/absent per key. NEVER a value.
                  → refuses if identity_source is already `store`.

2. IMPORT         `semantiq:identity-cutover --import`
                  → reads .env in the server process ONLY
                  → writes the encrypted store
                  → identity_source STAYS `env`.  Nothing has switched.

3. VERIFY         `semantiq:identity-cutover --verify`
                  → builds a provider from the STORE (never the container's)
                  → fetches discovery metadata + signing keys
                  → asserts issuer and tenant match the configured directory
                  → reports pass/fail. NEVER a value. NEVER a secret.
                  → refuses to proceed on any failure.

4. SWITCH         `semantiq:identity-cutover --commit`
                  → refuses unless step 3 passed in the last 15 minutes
                  → sets identity_source = `store` in one transaction
                  → records `identity.configuration.cutover` (Audit)

5. ROLLBACK       `semantiq:identity-cutover --rollback`
                  → sets identity_source back to `env`
                  → available while .env still holds the values
                  → after step 6 it refuses, because there is nothing to roll
                    back to, and saying otherwise would be a lie

6. RETIRE .env    A CONTROLLED OPERATOR CHANGE, BY HAND, LATER.
                  Not automated. Not part of any command.
                  Only after the deployment has run on `store` through at least
                  one real sign-in.
```

**Step 6 is deliberately manual and deliberately last.** The PLAN's requirement
is *"no automated destructive edit until the new configuration has been
proven"*, and a real sign-in is the proof — not a metadata fetch.

**Between steps 4 and 6 there are two copies of the secret and exactly one
authority.** That is the rollback window, and it is bounded by an operator
action rather than left open.

**Not performed during DESIGN.** Nothing runs against production.

> ### CORRECTION 4 — a fresh installation has no `.env` to cut over from
>
> **The defect.** §3.4 above describes **one** path and the draft treated it as
> the only one. It begins `identity_source = env` and reaches `store` only
> through `semantiq:identity-cutover --commit`, **an SSH command**. Apply that
> to a genuinely fresh installation and the result is absurd:
>
> ```text
> fresh deployment, identity .env EMPTY
>   → operator creates the Bootstrap administrator
>   → First-Run step 3 types the Entra configuration, tests it, saves it
>   → identity_source is STILL `env`
>   → /auth/microsoft reads .env  →  EMPTY  →  the nominated administrator
>     cannot sign in, and step 8 cannot complete
> ```
>
> **First-Run would have been completable and the installation still
> unusable** — and the only escape would be the SSH command that First-Run
> exists to avoid.
>
> ### Two named paths, and which one a deployment is on
>
> | | **Existing deployment** (§3.4, preserved verbatim) | **Fresh installation** (this correction) |
> | --- | --- | --- |
> | Entered when | `.env` already holds a working identity configuration | The identity `.env` keys are **absent or blank** |
> | Source of truth to import | `.env`, read server-side | **Nothing.** The configuration is typed into First-Run step 3 |
> | Sequence | `--check` → `--import` → `--verify` → `--commit` → real sign-in → **manual `.env` retirement** | save encrypted candidate → **verify candidate** → **atomically set `identity_source = store`** → normal `/auth/microsoft` → nominated administrator completes SSO |
> | SSH required | **Yes**, and deliberately | **No.** *"No SSH cutover command should be required for a genuine fresh installation"* |
> | `.env` afterwards | Retired by an operator, later | **Never an authority at any point** |
>
> ### The fresh-installation transition, exactly
>
> ```text
> STEP 3  the typed configuration is saved ENCRYPTED as a CANDIDATE
>           identity_source is unchanged; nothing is live yet
>           ↓
>         VERIFY THE CANDIDATE — §3.3's ProviderProbe, built from the
>         candidate, in its own cache namespace, never the container's
>         singleton: discovery metadata + signing keys + issuer/tenant match
>           ↓
>         ┌─ verification FAILS → the candidate stays a candidate.
>         │                       identity_source is NOT moved. The screen
>         │                       says so. Nothing has been switched on.
>         │
>         └─ verification PASSES → ONE TRANSACTION:
>                 candidate becomes the stored configuration
>                 identity_source = `store`
>                 `identity.configuration.cutover` recorded (StateChange)
>           ↓
> STEP 8  /auth/microsoft resolves the STORE. Unchanged route, unchanged
>         controller, unchanged callback — only the resolver answers
>         differently. GrantRedeemer creates the administrator (§2.2).
> ```
>
> **The transition is explicit, verified-first and atomic.** It is not a
> fallback, not a lazy "use the store if `.env` is empty", and not a side
> effect of pressing Save — *"there must still be no runtime fallback from
> store to `.env`"*, and none is introduced: `store` still reads no `env()`,
> which C1 already asserts.
>
> **Why `--commit` must still refuse here.** A fresh installation reaches
> `store` through First-Run, so `--commit` on an installation already in
> `store` refuses (§3.4 step 1), and the two paths cannot both move the flag.
>
> ### The mandatory test
>
> | # | Case | Mutation that must kill it |
> | --- | --- | --- |
> | **C4** | **Fresh deployment + EMPTY identity `.env` + configuration saved and verified through Bootstrap → normal `/auth/microsoft` resolves the STORED configuration** | Leave `identity_source` at `env` after a passing First-Run verification — C4 must fail, and it is the exact defect |
> | **C5** | A **failing** candidate verification does **not** move `identity_source` | Move the flag on save rather than on verification |
>
> **C4 configures no `.env` identity keys at all**, so it cannot pass by
> accidentally reading the environment the test harness happens to have.

---

## 4. The Bootstrap principal

### 4.1 Installation state — computed, never stored (D-169)

```php
UNCONFIGURED  ⟺  no active User holds RoleCode::SystemAdministrator
CONFIGURED    ⟺  at least one does
```

**This is P1-00's existing `BootstrapState` predicate, unchanged and reused.**
It is computed *"so it cannot drift from reality"* — P1-00's own words — and a
stored `setup_complete` flag would be exactly the drift this design must avoid.

**Bootstrap access requires UNCONFIGURED *and* a local principal that has not
been closed *and*, once it has been closed, an open recovery context.** All
three are re-evaluated **on every request** (D-166). The third condition is
**Correction 3** and is specified in §4.5; the two below were already true:

- a passing SSO **test** changes nothing — CONFIGURED needs a real
  administrator, which answers D-169's *"do not close bootstrap merely because a
  connection test passed"*;
- **a stale bootstrap session fails on its next request** because the middleware
  re-reads state, **not because the session was deleted.** With production on
  `file` sessions, a file that happens to remain must not grant access — the
  point D-166 singles out.

### 4.2 The principal, and why it cannot become a `User`

```php
final readonly class BootstrapPrincipal
{
    public function __construct(
        public int $id,
        public string $email,
    ) {}
}
```

**A distinct type, deliberately with no relationship to `User`.** It has no
`roles()`, no `organisation_id`, no `status`, and the `AccessEngine` has no
method that accepts it — so *"give the bootstrap principal an entitlement"* does
not typecheck.

**`RequireBootstrapSession`** sets `semantiq_bootstrap` on the request.
**`EnsureSessionIsCurrent` sets `semantiq_user`.** Every console route reads
`semantiq_user`, which a bootstrap request never carries.

| Guard | Asserts |
| --- | --- |
| `BootstrapIsNotAUser` | `BootstrapPrincipal` does not extend `Model`, is not `Authenticatable`, and appears in no `RoleCatalogue`/`ActionClass` signature |
| `BootstrapCannotReachConsole` | Every `console/*` route requires `EnsureSessionIsCurrent`, **as an equality over the route table** |
| `BootstrapGrantsNoEntitlement` | The setup module names no `AccessEngine`, `RoleAssignment`, `DomainEntitlement` or `ActionClass` |

### 4.3 Credentials (D-161, D-162, D-164, D-165)

| | |
| --- | --- |
| Storage | `Hash::make` — configured adaptive hashing. **Never SHA-256 a human password** |
| Creation | `semantiq:bootstrap-administrator --email=…`, password read with `secret()` — **hidden, interactive** |
| Plaintext argument | **Refused.** The command has no password option; passing one is a usage error |
| Echo-back | Never |
| Count | **Exactly one.** A unique constraint on a fixed singleton key, not application logic |
| Rate limit | **5 failures / 15 min per IP + normalised identifier**, plus a bounded global limiter. Success resets the relevant counter |
| Refusals | Generic. Unknown identity and wrong password are **indistinguishable** |
| Session | **30-min idle, 4-hour absolute.** Identifier regenerated at authentication |
| Privileged change | Re-confirm the local credential (D-159) |

**The existing `bootstrap_grants` table is untouched** (D-160). Its
Entra-bound, atomically-consumed, single-use semantics were verified in P1-00
and **remain authoritative for their accepted purpose.** The recovery token for
the local principal reuses the *pattern* — hash at rest, TTL, atomic
single-use consumption in the `WHERE` clause — in its own table, because a
pre-SSO principal has no Entra subject to bind to.

### 4.4 The transition, and the in-between state (D-170)

```text
UNCONFIGURED
   │  bootstrap enabled, First-Run reachable
   │
   ├── SSO configured ───────────────▶ still UNCONFIGURED
   ├── SSO test passes ──────────────▶ still UNCONFIGURED   ← D-169
   │      surface reads: "SSO ready — waiting for administrator sign-in"
   │      bootstrap may still correct configuration and re-nominate
   │
   └── nominated administrator completes the NORMAL SSO path
       and a System Administrator is established
                 │
                 ▼
            CONFIGURED   ← computed, the instant the role assignment commits
                 │          AND, in the SAME transaction, bootstrap is CLOSED ─ §4.5
                 │
                 ├── bootstrap login refuses
                 ├── existing bootstrap sessions fail on their NEXT request
                 ├── the local credential is unusable ─ permanently, not while
                 │     the predicate happens to hold
                 └── no "disable bootstrap" button exists to press
```

**The predicate closes the door; the write takes the door off its hinges.**
The first draft of this design said *"nothing is disabled by a write"* and
treated that as a virtue. **It was a defect, and §4.5 is the correction.**

### 4.5 Closing bootstrap, and reopening it only on purpose

> ### CORRECTION 3 — bootstrap must not silently reopen
>
> **The defect.** §4.1 computes UNCONFIGURED from the administrator count, and
> the first draft gated the local password login on that predicate alone. The
> Product Owner read the consequence the draft did not:
>
> ```text
> first permanent System Administrator established   →  CONFIGURED,  login refused
> that administrator is later deactivated            →  UNCONFIGURED again
> the ORIGINAL bootstrap password starts working again
> ```
>
> **That is a standing local backdoor with a permanent password**, reachable by
> deactivating one account, and it survives for the life of the deployment.
> `bootstrap_administrators.disabled_at` existed in the draft schema and
> **nothing ever wrote it** — the column was the shape of the fix without the
> fix.
>
> ### The superseded historical rule
>
> `BootstrapState`'s docblock says today:
>
> > *"This same predicate is what makes recovery work: if every System
> > Administrator is deactivated, the system is UNCONFIGURED again and the
> > operator channel reopens. Recovery is not a special mode or a flag — it is
> > this returning true."*
>
> **That remains true of the SSH operator grant channel and is unchanged** —
> P1-00's channel still requires SSH, a fresh auditable grant and full Entra
> SSO, and P1-10 does not touch it (D-160). **It is superseded for the local
> password**, which did not exist when it was written. The docblock is amended
> in EXECUTE to say so, and to name the amendment rather than quietly rewrite
> the history.
>
> ### The closing write
>
> When the first permanent System Administrator is successfully established
> — that is, inside `GrantRedeemer`'s existing consumption transaction, which
> is where the role assignment commits:
>
> | | |
> | --- | --- |
> | `disabled_at` | Set to the transition instant. **This is the write the draft lacked** |
> | The password hash | **Replaced with an unusable value**, not merely flagged. The old hash is gone, so restoring `disabled_at` to `NULL` by hand does not restore the old password |
> | Atomicity | **The same transaction as the role assignment.** If the role assignment rolls back, bootstrap is not closed; if closing fails, no administrator is created. There is no interval in which both an administrator and an open bootstrap password exist |
> | Evidence | `bootstrap.closed`, **StateChange**, durable — P1-08's atomicity guard applies, so the evidence and the closure commit together or neither does |
>
> **After this point `UNCONFIGURED` by itself MUST NOT reopen local password
> login.** The predicate is still necessary — it is not sufficient.
>
> ### Recovery is opened deliberately, by a trusted operator
>
> ```text
> local password login is permitted  ⟺  UNCONFIGURED
>                                    ∧  the principal is not closed
>                                       ∨  an UNCONSUMED, UNEXPIRED recovery
>                                          token has been redeemed in THIS
>                                          session, opening a temporary
>                                          recovery context
> ```
>
> - The token is issued **over SSH by a trusted operator** — the same channel
>   and the same evidence discipline as `bootstrap_grants`: hash at rest, TTL,
>   **atomic single-use consumption in the `WHERE` clause** (D-160's pattern).
> - Redemption **explicitly enables** the recovery context and sets a fresh
>   usable credential. Recovery **does not un-close the principal by side
>   effect**; the context is the thing that opens, and it is written, not
>   inferred.
> - **After recovery succeeds** — a System Administrator signs in through the
>   normal SSO path again — **bootstrap closes again by the same write**, with
>   a second `bootstrap.closed`. Recovery is a bounded episode, never a mode
>   the deployment can be left in.
>
> ### The five mandatory tests — and what each is written against
>
> | # | Case | Mutation that must kill it |
> | --- | --- | --- |
> | **B10** | First System Administrator established → **the local password is refused immediately** | Gate the login on the predicate alone |
> | **B11** | **Then deactivate EVERY System Administrator → the old local password is STILL refused.** This is the defect itself, written as a test | Re-derive "bootstrap open" from `isUnconfigured()` — B11 must fail |
> | **B12** | A valid, unexpired, unconsumed operator recovery token → temporary local access **is** permitted | Ignore the token and open on the predicate |
> | **B13** | A **consumed** or **expired** token → refused, and the consumed one **stays** consumed | Non-atomic consumption; TTL compared loosely |
> | **B14** | Recovery **closes again** once a restored SSO administrator signs in successfully | Close only on the *first* transition |
>
> **B11 is the load-bearing case.** It is the one that fails on the design as
> originally written, and the one a future refactor toward "just read the
> predicate" would break first.

---

## 5. Connection tests — one contract, four providers

```php
final readonly class ConnectionResult
{
    public function __construct(
        public HealthStatus $status,      // D-149, reused verbatim
        public string $explanation,       // a CHOSEN sentence, never caught
        public ?string $testedAt = null,
    ) {}
}
```

**`HealthStatus` is P1-09's enum, imported — not re-declared** (D-149). A guard
asserts the module declares no status enum of its own.

**`explanation` is chosen, never caught** — P1-09's rule, and here it is a
security control rather than a polish one: provider error bodies routinely echo
credentials. Every adapter catches `Throwable` and returns one of its **own
declared sentences**; the caught value reaches nothing.

| Provider | The test | Forbidden |
| --- | --- | --- |
| **SMTP** (D-152) | Connect, STARTTLS/TLS per mode, `EHLO`, authenticate, `QUIT`. Then, on explicit request, **one message to the principal's own address** (D-153) | Any other recipient. **There is no recipient field in the request** |
| **AI** (D-175) | The cheapest authenticated metadata call — e.g. list deployments/models. **If a provider cannot validate credentials without inference → `Not checked`** | **No completion, no prompt, no token generation.** A TCP/TLS handshake alone is **not** "AI Available" |
| **Fabric** (D-177) | Client-credentials token, then **metadata for the one configured workspace** | Enumerating workspaces, tables, semantic models; DAX/SQL; ingestion; any create/update/delete |
| **Identity** | **P1-02's existing re-check**, unchanged | A second probe |

**Timeout 10 s per provider call → `Degraded`, never a definitive
`Unavailable`** (D-156). **One test per administrator per integration per 60 s**
(D-157), reusing P1-02's limiter shape. **No polling, no background tests** —
and there is no scheduler to run them on anyway.

**Guards:** `AiTestPerformsNoInference` (the module names no completion,
chat, prompt, embedding or generate path); `FabricTestReadsNoBusinessData` (no
DAX, SQL, table, dataset or item enumeration); `NoArbitraryTestRecipient` (the
test request has no recipient field).

### 5.1 A configuration change invalidates the old result

> ### CORRECTION 5 — a green result must not survive the configuration it tested
>
> **The defect, stated plainly.** `status` and `last_tested_at` sit on
> `integration_configurations` (§11) and the draft had nothing that clears
> them. So:
>
> ```text
> test SMTP against smtp.old.example  →  Available, tested 14:02
> change the host to smtp.new.example, change the password
>   →  the card still reads "Available — last tested 14:02"
> ```
>
> **The timestamp is true and the claim it supports is false.** It is the same
> failure class P1-09's `storedReport()` correction fixed — a stored result
> presenting as current evidence — arriving through a different door.
>
> ### Email, AI and Fabric — invalidate on the write
>
> **Any meaningful configuration or secret change atomically moves `status` to
> `Not checked`** and **clears `last_tested_at`**, in the **same transaction**
> as the change itself. Not afterwards, not best-effort, not on read.
>
> - **"Meaningful"** is a declared property of each typed field, not a guess:
>   every field that reaches the provider (host, port, mode, endpoint,
>   workspace, tenant, client id) and **every secret write**. A change to a
>   display-only field does not invalidate, and the allowlist says which is
>   which so the question is answered in one place.
> - **`last_tested_at` does not survive** as "evidence for the previous
>   configuration". It is cleared, because a timestamp with no statement
>   attached is exactly what misleads.
> - **Only a new explicit test** may set `Available` / `Degraded` /
>   `Unavailable`. **No other write may set a positive status** —
>   `OnlyATestSetsAPositiveStatus` asserts that the status column is written
>   to a positive value in exactly one place.
>
> ### Identity — the same rule, and the harder half
>
> P1-09's `storedReport()` reads `LAST_RESULT_KEY`; P1-02's probe writes
> `LAST_PROBE_KEY`. **Neither knows the configuration changed.** And the
> configuration those checks describe is read directly, bypassing any
> resolver — the current tree has **six such call sites**:
>
> ```text
> IdentityConfigurationReport.php   lines 42, 43, 44, 56   ← the health/config screen
>                                   AND the missingKeys() array, read as
>                                   config($key) — four more, in a shape a
>                                   naive grep for config('identity… misses
> IdentityHealthCheck.php           line 454               ← the health check itself
> EntraController.php               lines 59, 60           ← the Entra screen
> PlatformServiceProvider.php       lines 77, 82-83, 89-92 ← the runtime provider
> IssueBootstrapGrantCommand.php    line 36
> UserDirectoryService.php          line 54
> ```
>
> **`missingKeys()` also stops being a `config()` question.** It reports which
> identity values are absent, and after this correction "absent" means absent
> **from the resolved source** — otherwise the unconfigured empty state would
> describe `.env` on a deployment running from the store.
>
> **Two requirements follow, and the draft stated neither.**
>
> 1. **The health and configuration checks must resolve the same authoritative
>    source as the runtime provider.** Every site above moves to §3.2's
>    `IdentityConfigurationSource`. **Do not leave direct `config('identity.microsoft.*')` reads
>    that bypass the new resolver** — otherwise the screen reports on `.env`
>    while sign-in uses the store, which is worse than no screen.
>    **Guard: `NoDirectIdentityConfigRead`** — outside
>    `IdentityConfigurationSource` itself,
>    **zero** occurrences of the string **`identity.microsoft.`** anywhere in
>    `app/`. **The dotted key, not the `config('…` call**, because
>    `IdentityConfigurationReport::missingKeys()` holds the four keys in an
>    array and reads them as `config($key)`: a guard written against the call
>    shape would score that file clean while four reads bypassed the resolver.
>    **That is the vacuous-guard failure CLAUDE.md §2 describes**, and it is
>    avoidable here by choosing the string the mistake actually contains. The
>    mutation is restoring any one of the six sites, **including the array
>    one**.
>
> 2. **An identity configuration change must invalidate or revision-bind
>    `LAST_RESULT_KEY` and `LAST_PROBE_KEY`**, so **P1-09's `storedReport()`
>    can never show the previous tenant's health as current.**
>
> ### Revision binding, not a hopeful `forget()`
>
> **A cache `forget()` alone is best-effort and this must not be.** It can fail
> silently, it does not help a second application node, and production runs
> `CACHE_STORE=file` where a missed unlink leaves the stale entry readable.
>
> **The mechanism is a source-bound revision.** `platform_settings` carries a
> monotonic `identity_config_revision`, incremented **in the same transaction**
> as any identity configuration or secret write. The cache keys become
> **revision-bound**:
>
> ```text
> semantiq:identity:health:last:{revision}
> semantiq:identity:probe:last:{revision}
> ```
>
> A stale entry is then **unreadable rather than merely unwanted**: after a
> change, `storedReport()` looks for a key that has never been written and
> returns `Not checked` — which is precisely what P1-09 built it to do. The
> old entry is also forgotten, but **nothing depends on that succeeding.**
>
> **`storedReport()` itself does not change.** It already returns `Not checked`
> for an absent or unrecognised entry, and P1-09 proved that over eight shapes
> of rubbish. The correction changes *which key it reads*, not what it
> tolerates.
>
> ### The mandatory tests
>
> | # | Case | Mutation that must kill it |
> | --- | --- | --- |
> | **H1** | SMTP tested Available → change the host → status is **Not checked** and `last_tested_at` is **null**, in one transaction | Clear the status but keep the timestamp |
> | **H2** | Change a **secret only** → same invalidation | Invalidate on non-secret fields only |
> | **H3** | Only an explicit test sets a positive status; **no other write can** | Let the save path write `Available` |
> | **H4** | **Identity: change the tenant → `storedReport()` reports `Not checked`, NOT the previous tenant's result** | Keep the un-revisioned key — H4 must fail |
> | **H5** | **Revision binding survives a cache that ignores `forget()`** — P1-09's `FORGETFUL` broken store, reused | Rely on `forget()` alone — H5 must fail |
> | **H6** | The health screen and `/auth/microsoft` resolve the **same** source | Restore one direct `config()` read |
>
> **H5 is the one that distinguishes this correction from the obvious fix.**
> A `forget()`-only implementation passes H4 and fails H5.

---

## 6. Secrets

| Rule | Mechanism |
| --- | --- |
| Encrypted at rest | `Crypt::encryptString` on the `APP_KEY`. **This unit introduces application-level encryption — there is none today** |
| Never returned to React | The projection has **no field** for a secret value. D-155, made unrepresentable rather than remembered |
| No reveal endpoint | **None exists.** The route set has no reveal verb for any secret |
| Not in Audit context | The closed `ALLOWED_KEYS` list is **unchanged at 15** (D-158) |
| Not in logs or errors | `explanation` is chosen, never caught |
| Not in URLs | Every write is a `PUT` body |
| Not in workflow output | No CI or deploy step reads the store |

```php
final readonly class IntegrationView   // what React receives
{
    public function __construct(
        public string $family,
        public HealthStatus $status,
        public string $explanation,
        public bool $secretConfigured,   // PRESENCE, never the value
        public ?string $lastChangedAt,
        public ?string $lastTestedAt,
        public array $fields,            // NON-SECRET typed fields only
    ) {}
}
```

**Guard:** `NoSecretReachesTheBrowser` — the view class has no secret-shaped
property, and a rendered-props test asserts a saved secret's value appears
nowhere in the payload.

### 6.1 `APP_KEY` — an operational restriction for Release 1

**`APP_KEY` rotation would make every stored secret undecryptable.** Today
nothing in SemantIQ is encrypted, so rotation is currently harmless; **after
this unit it is not.**

**`key_version` is kept, and its implication is corrected.**

> **`key_version` does not make `APP_KEY` rotation safe by itself.** It is a
> record of *which* key encrypted a row. It is **not** a mechanism that can
> decrypt a row whose key is gone. A version column on a ciphertext that
> nothing can decrypt tells you accurately which key you no longer have.

**The Release 1 rule, stated as an operational restriction rather than a
feature:**

| | |
| --- | --- |
| Rotation while encrypted integration secrets exist | **UNSUPPORTED in Release 1**, unless the **old key is available** to an approved re-encryption procedure |
| Rotation tooling in P1-10 | **Not built.** That would be pre-building, and the Product Owner has not asked for it |
| Where the restriction lives | **Documented as an operational restriction** in the P1-10 handover and the deployment notes — an instruction to operators, not a promise made by code |
| What a future tool must do | **Decrypt with the old key, re-encrypt with the new, and only then retire the old key.** `key_version` tells it which rows are which; it does not do the work |
| What must not be implied | **That a version column alone protects stored secrets.** It does not, and the draft's wording invited that reading |

**Consequence for the operator today:** once P1-10 ships, changing `APP_KEY` on
a deployment that holds integration secrets is a **data-loss operation** unless
the old key is retained and a re-encryption step is performed first. That is
the sentence that belongs in the runbook, and it is not softened here.

---

## 7. Step-up (D-159)

| Actor | Before a privileged credential change |
| --- | --- |
| **`PlatformAdmin`** | **Microsoft step-up**, through P1-05's existing `StepUpService` and registry. New `StepUpAction` cases; P1-07 already proved a second completion registers cleanly |
| **Bootstrap principal** | **Local credential re-confirmation.** Microsoft step-up **does not exist yet**, and requiring it would make First-Run unsatisfiable |

**A plain Test connection requires neither.** It changes nothing.

---

## 8. Audit (D-158)

| Event | Outcome class |
| --- | --- |
| `integration.configuration.changed` | StateChange |
| `integration.connection.tested` | BestEffort |
| `identity.configuration.cutover` | StateChange |
| `bootstrap.administrator.created` | StateChange |
| `bootstrap.signin.succeeded` | **StateChangeRecordedFirst** — CORRECTION 6 |
| `bootstrap.signin.refused` | Refusal |
| `bootstrap.signout` | BestEffort |
| `bootstrap.recovery.issued` / `.consumed` | StateChange |
| `bootstrap.closed` | StateChange |

**Context uses only existing `ALLOWED_KEYS`** — `provider`, `result`, `reason`,
`user_id`, `expires_at`. **No key is added**, so a credential, endpoint or host
has nowhere to go. State-change events obey P1-08's atomicity guard.

**Guard:** `IntegrationsAddNoAllowedKey` — the list is still exactly 15.

> ### CORRECTION 6 — bootstrap sign-in must fail closed if evidence cannot be written
>
> **The defect.** The draft classed `bootstrap.signin.succeeded` as
> **BestEffort**, which means exactly one thing in this codebase: *if the write
> fails, carry on.* Carrying on here means **issuing a session for the most
> privileged local credential in the deployment with no record that it was
> ever used.** An attacker who can make audit writes fail gets a silent
> sign-in — and the deployment cannot afterwards answer "was this used?".
>
> **A successful login is a state change**, not a courtesy note. Normal
> successful login is already classed that way; the draft gave the *local
> bootstrap* login weaker semantics than the ordinary one, which is backwards.
>
> **The correction:** `bootstrap.signin.succeeded` uses
> **`OutcomeClass::StateChangeRecordedFirst`** — the same semantic class as
> normal successful login.
>
> ```text
> correct credential
>   ↓
> durable evidence is written and COMMITTED
>   ↓                              ↘ write fails
> session identifier regenerated     NO session is issued.
> bootstrap session issued           The attempt is REFUSED.
> ```
>
> **Evidence first, privilege second, in that order, with no path that
> reverses them.** P1-08's `AuditWriter` atomicity guard already enforces the
> ordering for this class; the correction is using the class.
>
> **What deliberately does NOT change:**
>
> | | |
> | --- | --- |
> | `bootstrap.signin.refused` | **Refusal semantics, unchanged.** A refusal that cannot be recorded must still refuse — hardening a refusal into a failure would let a broken audit store lock out recovery while *granting* nothing |
> | `bootstrap.signout` | **BestEffort, unchanged.** Failing a sign-out on an audit error keeps a privileged session alive, which is the wrong failure direction |
>
> ### The mandatory negative test
>
> | # | Case | Mutation that must kill it |
> | --- | --- | --- |
> | **B15** | **Evidence persistence fails + the CORRECT credential → login REFUSED and NO bootstrap session exists** | Restore `BestEffort` — B15 must fail |
>
> **B15 supplies the right password.** A test that fails the credential too
> would pass for the wrong reason — it would be satisfied by any refusal, which
> is the failure CLAUDE.md §2 names. The assertion is on the *session*, not on
> the message: no bootstrap session identifier is issued, and the previous
> session is not elevated.

---

## 9. Screens

**Platform Integrations** reuses P1-09's card and status presentation, including
the three neutral treatments. **First-Run** uses a deliberately different,
narrower chrome — **no sidebar, no product areas** — because a setup surface
that looks like the console invites the assumption that the console is
reachable from it.

Required/optional is **shown on every step** (D-167). Optional integrations left
**Not configured** do not block completion (D-171), and First-Run is resumable
until shutdown.

**Browser matrix:** every screen × 1440/390 × light/dark, per-element overflow,
both themes, keyboard focus, and the P1-09 rule that measurement is per element
rather than per page.

---

## 10. Test matrix — the security cases are the unit

| # | Case | Mutation that must kill it |
| --- | --- | --- |
| **B1** | Bootstrap cannot reach any `console/*` route | Drop `EnsureSessionIsCurrent` from one console route |
| **B2** | Bootstrap cannot assign itself a role | Accept a `BootstrapPrincipal` in `AccessEngine` |
| **B3** | **Bootstrap refuses the instant a System Administrator exists** | Cache the predicate |
| **B4** | **A stale bootstrap session fails on its NEXT request** — session untouched, state changed | Check only at sign-in |
| **B5** | An old local credential cannot be replayed after reset | Keep the old hash |
| **B6** | Recovery token is single-use and expiring | Non-atomic consumption |
| **B7** | Unknown identity and wrong password are indistinguishable | Return a different message |
| **B8** | 5 failures / 15 min; global limiter caps rotation | Remove either |
| **B9** | **Exactly one local principal exists** | Allow a second row |
| **S1** | No secret reaches the rendered props | Add a value field |
| **S2** | A failed test reveals no token — injected provider error carries one | Put the caught message in `explanation` |
| **S3** | No reveal route exists for any secret | Add one |
| **S4** | `ALLOWED_KEYS` is still 15 | Add a key |
| **A1** | **Saving a tenant and testing it in ONE request tests the NEW tenant** | Reuse the container's provider |
| **A2** | Probe cache cannot poison live trust | Share the namespace |
| **C1** | `store` mode reads no `env()` | Add a `??` fallback |
| **C2** | `--commit` refuses without a recent passing verify | Drop the check |
| **C3** | `--rollback` refuses once `.env` is retired | Let it claim success |
| **T1** | AI test performs no inference | Add a one-token completion |
| **T2** | A provider that cannot validate → **`Not checked`**, never Available | Report Available on a TLS handshake |
| **T3** | Fabric test touches only the configured workspace | Enumerate workspaces |
| **T4** | Test email goes only to the principal's own address | Add a recipient field |
| **T5** | Timeout → **Degraded**, not Unavailable | Swap the status |
| **T6** | Success enables no business access | Grant on success |
| **P1** | `/up` and `semantiq:health` unchanged | Change either |
| **P2** | P1-05 effective access is unchanged by any integration state | Consult integration state in `AccessEngine` |

**The six corrections add twelve further mandatory cases**, stated with their
mutations in the sections that introduce them, and they are part of this matrix
rather than an appendix to it:

| From | Cases |
| --- | --- |
| **Correction 1** (§2.2) | `FirstRunRoutesDoNotCollide`, asserted under **reversed declaration order** |
| **Correction 3** (§4.5) | **B10 – B14** — close on transition; **B11: every administrator deactivated and the old password still refused**; operator recovery; consumed/expired refusal; close again |
| **Correction 4** (§3.4) | **C4 – C5** — fresh install with an **empty** identity `.env` reaches `store`; a failing verification does not |
| **Correction 5** (§5.1) | **H1 – H6** — invalidation on write; secret-only change; only a test sets a positive status; **H4** identity revision binding; **H5** survives a `forget()`-ignoring cache; one resolved source |
| **Correction 6** (§8) | **B15** — evidence write fails, **correct** credential, **no session** |

**Every guard is broken deliberately and the mutation recorded** — CLAUDE.md §2.
**B11, C4, H5 and B15 are the four that fail on the design as first written**,
and are the ones to run first.
**The MySQL suite step is mandatory**: this unit writes, and P1-08 and P1-09
both found engine-specific failures that were green on SQLite.

---

## 11. SCHEMA — five tables, each justified

| # | Table | Why it exists, and why it is not merged into another |
| --- | --- | --- |
| 1 | `integration_configurations` | **Typed, non-secret** fields per family: host, port, mode, endpoint, workspace id, tenant/client id, `status`, `last_tested_at`, `last_changed_at`. **Queryable without decrypting anything** — a status card must not need the key |
| 2 | `integration_secrets` | **Separate because the access pattern is different.** Secrets are written rarely, read only by the adapter that needs one, and **never** by a listing. A separate table means "list the integrations" cannot accidentally load a ciphertext, and the encryption boundary is one table wide. Carries `key_version` (§6.1) |
| 3 | `platform_settings` | **Exactly one row, fixed primary key.** Holds `identity_source` (`env` \| `store`), the cutover timestamps, and **`identity_config_revision`** — the monotonic counter Correction 5 binds the identity health cache keys to. **Not a KV store** — a typed, singleton, enumerated row. A unique constraint on the fixed key makes a second row impossible |
| 4 | `bootstrap_administrators` | **The one local principal** (D-160): email, password hash, `disabled_at`, `last_signed_in_at`. **`disabled_at` is written by the Correction 3 closing transaction and the password hash is replaced with an unusable value at the same moment** — the draft carried the column and never wrote it. Separate from `bootstrap_grants`, whose Entra-bound semantics stay untouched. A unique constraint on a fixed singleton column enforces "exactly one" in the database rather than in application code |
| 5 | `bootstrap_recovery_tokens` | Hash, TTL, atomic single-use consumption — **the `bootstrap_grants` pattern, which P1-00 verified**, for a principal with no Entra subject to bind to. **Correction 3 makes this the only thing that can reopen local password login** once bootstrap has closed |

**No generic settings table. No configuration-JSON dumping ground.** Every
column is typed and validated through a DTO with a closed field allowlist per
family, **even where an encrypted payload is stored internally** — because the
allowlist is what stops a future field arriving unvalidated.

**Could this be fewer tables?** 1+2 could merge, and should not: it would put a
ciphertext column on the table every listing reads. 4+5 could merge, and should
not: a token is transient and a principal is not, and merging them means
deleting a consumed token mutates the principal's row.

---

## 12. What this design does NOT do

- **No second Entra configuration model.** P1-10 calls P1-02's writer.
- **No notification, task, digest or alert system.**
- **No AI inference, prompt, RAG, agent or business-data retrieval.**
- **No Fabric data source, discovery, ingestion, lakehouse, semantic model,
  pipeline or publication.**
- **No Graph scope expansion** — SMTP avoids D-04 entirely.
- **No Graph/SES/Resend adapters** built ahead of need.
- **No TOTP** (D-163), and the ruling's lapse condition is recorded.
- **No Organisation fields in First-Run** (D-168).
- **No bootstrap Audit viewer** (D-173).
- **No certificate credentials, no managed identity** (D-176).
- **No `APP_KEY` rotation tooling** — §6.1 records it as an **operational
  restriction for Release 1**, not as something this unit solves.
- **No change** to `/up`, `semantiq:health`, D-19, P1-05, or any carried item.

---

## 13. Genuine blockers

**None that stop DESIGN approval.** Two things are raised rather than decided:

1. **`APP_KEY` rotation** (§6.1) becomes operationally significant the day this
   ships. **Rotation is UNSUPPORTED in Release 1 while encrypted integration
   secrets exist, unless the old key is available to an approved re-encryption
   procedure.** `key_version` records which key encrypted a row; **it does not
   make rotation safe by itself.** No tooling is built now, and the restriction
   is documented for operators rather than implied by a column.
2. **Step 6 of the cutover is a manual operator change** by design, **on the
   existing-deployment path only.** It needs an operator with SSH at a time of
   their choosing, and the deployment runs with two copies of the secret and
   one authority until then. **That window is intentional and bounded**, but it
   is a real operational state and is named rather than hidden. **A fresh
   installation never enters it** — Correction 4 gives it no `.env` copy to
   retire and no SSH step to perform.

**Neither weakens an existing authentication guarantee.** Had either required
that, it would be reported as a blocker instead of designed around — the
instruction in the ruling set, and the correct answer here is that it did not.

---

## 14. The six corrections, and what each one was

Recorded as an index because **three of the six were defects in this design
that the Product Owner found and it had missed.** Listing them where they were
made would let them read as refinements. They were not.

| # | Where | What the draft said | What was wrong with it |
| --- | --- | --- | --- |
| **1** | §2.2 | The First-Run static routes were listed and declared | `/first-run/{grant}` is **unconstrained** and already in production. `closed` survives on **declaration order alone**. `sign-in` would have been swallowed by the grant route. **Fixed structurally** with a 64-character constraint and a reversed-order guard — the third appearance of the collision class P1-03 and P1-04 were written against |
| **2** | §2.2 | "Nominate an email, the administrator then signs in normally" | **`CallbackController` creates nobody without a grant in session.** The nomination would have "worked" and left the installation with no administrator. **Fixed** by routing step 7 through the existing `GrantIssuer` / `GrantRedeemer`, unchanged, with no email dependency (D-171) |
| **3** | §4.5 | *"Nothing is disabled by a write"* — bootstrap gated on the computed predicate alone | **A standing local backdoor.** Deactivate every System Administrator and the original bootstrap password works again. `disabled_at` was in the schema and **nothing wrote it**. **Fixed** by an atomic closing write, an unusable hash, `bootstrap.closed` evidence, and recovery only through a trusted operator token. **The most important of the six** |
| **4** | §3.4 | One cutover path, beginning at `.env` and committed over SSH | **A genuine fresh installation has no `.env` to cut over from.** First-Run would complete and the deployment still be unusable, escapable only by the SSH command First-Run exists to avoid. **Fixed** with a second, explicit, verified-first, atomic store-authority transition — **and still no runtime fallback** |
| **5** | §5.1 | `status` and `last_tested_at` on the configuration row | **A green result outliving the configuration it tested** — the P1-09 `storedReport()` failure class through a different door. Identity was worse: **six direct `config('identity.microsoft.*')` reads** bypass the resolver, so the screen would describe `.env` while sign-in used the store. **Fixed** by transactional invalidation, one resolved source, and **revision-bound** cache keys rather than a best-effort `forget()` |
| **6** | §8 | `bootstrap.signin.succeeded` as **BestEffort** | **A silent privileged sign-in** when the audit write fails — weaker semantics than an ordinary login, which is backwards. **Fixed** with `StateChangeRecordedFirst`: evidence commits before any bootstrap session is issued. Refusals keep refusal semantics; sign-out stays BestEffort, because failing those has the wrong failure direction |

**Plus one non-correction:** the `APP_KEY` wording in §6.1, which implied that
`key_version` makes rotation safe. It does not, and the rule is now recorded as
an **operational restriction for Release 1**.

**Four of the new cases fail on the design as first written** — **B11**
(deactivate every administrator, password still refused), **C4** (fresh install
with an empty identity `.env`), **H5** (a cache that ignores `forget()`) and
**B15** (correct credential, unwritable evidence, no session). They are the
proof that these corrections are load-bearing rather than editorial.

---

## 15. Status

**DESIGN ONLY — AWAITING PRODUCT OWNER REVIEW.**
**NO IMPLEMENTATION / NO MIGRATION / NO DEPLOYMENT.**
Nothing was run against production. No `.env` was read, changed or retired. No
bootstrap account, and no AI, Fabric or email credential exists.

**P1-02 remains OPEN / CARRIED / UNVERIFIED. Production session-driver
alignment remains OPEN / CARRIED. The privilege-change / session-revocation
phase gate stands. P1-07, P1-08 and P1-09 carried items remain carried. D-19
unchanged. P1-11 deferred with D-130 – D-147 preserved.**
