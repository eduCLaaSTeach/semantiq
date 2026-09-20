# P1-10 — Platform Integrations & Setup: DESIGN

**DESIGN ONLY.** No implementation, no migration, no deployment.

| | |
| --- | --- |
| Unit | **P1-10 — Platform Integrations & Setup** (delivery order 12) |
| PLAN | merge `b26c07b33046673758ff429452547a9954328644` — **D-148 – D-178 ANSWERED** |
| Authority | `PRODUCT-OWNER-AMENDMENT-PLATFORM-SETUP-AND-BOOTSTRAP.md`, merge `09855b0` |
| **Schema** | **FIVE TABLES.** Justified one by one in §11 |
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
4. **Bootstrap closes because state says so**, not because a session expired,
   a file was deleted or somebody pressed a button.

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

**Bootstrap access requires UNCONFIGURED *and* an enabled local principal.**
Both are re-evaluated **on every request** (D-166), so:

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
                 │
                 ├── bootstrap login refuses
                 ├── existing bootstrap sessions fail on their NEXT request
                 ├── the local credential is unusable
                 └── no "disable bootstrap" button exists to press
```

**Nothing is disabled by a write.** The predicate changes and every guard reads
it, which is why there is no window between "administrator established" and
"bootstrap closed" for a concurrent request to slip through.

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

### 6.1 Key rotation — raised, not solved

**`APP_KEY` rotation would make every stored secret undecryptable.** Today
nothing in SemantIQ is encrypted, so rotation is currently harmless; **after
this unit it is not.**

**DESIGN records the implication and proposes the minimum:** the store keeps a
`key_version`, and a future rotation re-encrypts rather than guessing. **No
rotation tooling is built now** — that would be pre-building. **This is flagged
for the Product Owner rather than silently accepted**, because the operational
character of `APP_KEY` changes the day this unit ships.

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
| `bootstrap.signin.succeeded` / `.refused` | BestEffort / Refusal |
| `bootstrap.recovery.issued` / `.consumed` | StateChange |
| `bootstrap.closed` | StateChange |

**Context uses only existing `ALLOWED_KEYS`** — `provider`, `result`, `reason`,
`user_id`, `expires_at`. **No key is added**, so a credential, endpoint or host
has nowhere to go. State-change events obey P1-08's atomicity guard.

**Guard:** `IntegrationsAddNoAllowedKey` — the list is still exactly 15.

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

**Every guard is broken deliberately and the mutation recorded** — CLAUDE.md §2.
**The MySQL suite step is mandatory**: this unit writes, and P1-08 and P1-09
both found engine-specific failures that were green on SQLite.

---

## 11. SCHEMA — five tables, each justified

| # | Table | Why it exists, and why it is not merged into another |
| --- | --- | --- |
| 1 | `integration_configurations` | **Typed, non-secret** fields per family: host, port, mode, endpoint, workspace id, tenant/client id, `status`, `last_tested_at`, `last_changed_at`. **Queryable without decrypting anything** — a status card must not need the key |
| 2 | `integration_secrets` | **Separate because the access pattern is different.** Secrets are written rarely, read only by the adapter that needs one, and **never** by a listing. A separate table means "list the integrations" cannot accidentally load a ciphertext, and the encryption boundary is one table wide. Carries `key_version` (§6.1) |
| 3 | `platform_settings` | **Exactly one row, fixed primary key.** Holds `identity_source` (`env` \| `store`) and the cutover timestamps. **Not a KV store** — a typed, singleton, enumerated row. A unique constraint on the fixed key makes a second row impossible |
| 4 | `bootstrap_administrators` | **The one local principal** (D-160): email, password hash, `disabled_at`, `last_signed_in_at`. Separate from `bootstrap_grants`, whose Entra-bound semantics stay untouched. A unique constraint on a fixed singleton column enforces "exactly one" in the database rather than in application code |
| 5 | `bootstrap_recovery_tokens` | Hash, TTL, atomic single-use consumption — **the `bootstrap_grants` pattern, which P1-00 verified**, for a principal with no Entra subject to bind to |

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
- **No `APP_KEY` rotation tooling** — the implication is raised in §6.1.
- **No change** to `/up`, `semantiq:health`, D-19, P1-05, or any carried item.

---

## 13. Genuine blockers

**None that stop DESIGN approval.** Two things are raised rather than decided:

1. **`APP_KEY` rotation** (§6.1) becomes operationally significant the day this
   ships. Recording `key_version` is the minimum; rotation tooling is not built
   now, and the Product Owner should know the character of `APP_KEY` changes.
2. **Step 6 of the cutover is a manual operator change** by design. It needs an
   operator with SSH at a time of their choosing, and the deployment runs with
   two copies of the secret and one authority until then. **That window is
   intentional and bounded**, but it is a real operational state and is named
   rather than hidden.

**Neither weakens an existing authentication guarantee.** Had either required
that, it would be reported as a blocker instead of designed around — the
instruction in the ruling set, and the correct answer here is that it did not.

---

## 14. Status

**DESIGN ONLY — AWAITING PRODUCT OWNER REVIEW.**
**NO IMPLEMENTATION / NO MIGRATION / NO DEPLOYMENT.**
Nothing was run against production. No `.env` was read, changed or retired. No
bootstrap account, and no AI, Fabric or email credential exists.

**P1-02 remains OPEN / CARRIED / UNVERIFIED. Production session-driver
alignment remains OPEN / CARRIED. The privilege-change / session-revocation
phase gate stands. P1-07, P1-08 and P1-09 carried items remain carried. D-19
unchanged. P1-11 deferred with D-130 – D-147 preserved.**
