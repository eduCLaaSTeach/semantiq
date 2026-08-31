# P1-00 — Application Entry, Login & First-Run Bootstrap — DESIGN

**Status:** **DESIGN APPROVED — 31 August 2026.** D-03.1, D-13 and the
`RoutePrefixCollisionTest` correction are decided in §19. **P1-00 EXECUTE is
authorised.** No open Product Owner decision blocks implementation.
**Unit:** P1-00 (Phase 1 delivery order 2)
**PLAN:** `P1-00-LOGIN-BOOTSTRAP-PLAN.md` — **APPROVED 31 August 2026**, decisions D-03, D-04, D-09, D-10, D-11, D-12
**Predecessor:** P1-BASE — **ACCEPTED** at `3d075bf`
**UI standard:** `doc/design-system/ui-and-ux-layout-template-shared.md` §5.7 Auth archetype, WCAG AA

> **This document is the specification EXECUTE follows.** It is not redesigned
> while coding. Where implementation reveals that something here is wrong, the
> discrepancy is reported — not silently resolved in code.

---

## 0. Three findings the PLAN could not have known

These emerged from reading the actual code and must be settled before or during
EXECUTE. Two are defects; one is a conflict with an approved decision.

### 0.1 `/bootstrap` cannot be a route — **design changed accordingly**

`bootstrap` is one of the directories the Apache boundary refuses:

```apache
RewriteRule ^(app|bootstrap|config|database|doc|deployment|node_modules|public|resources|routes|storage|tests|vendor)(/|$) - [F,L]
```

A bootstrap route at `/bootstrap` would return **403 from Apache** in production
while passing every local test — precisely the failure that cost a deployment
when the authenticated area sat at `/app`. The first-run path is **`/first-run`**
instead. Every route in §1 was checked against that list.

### 0.2 `RoutePrefixCollisionTest` guards only one direction — **APPROVED to fix in P1-00**

The test asserts that every name in its own `PROTECTED_ROOTS` list appears in the
forwarder. It does **not** assert the reverse. When PR #40 added `public` to the
forwarder's deny list, `PROTECTED_ROOTS` was not updated, so the mirror quietly
stopped being a mirror.

Consequence today: a route beginning `/public` would **pass CI and 403 in
production**. Nothing currently uses that prefix, so nothing is broken — but the
guard that exists to prevent the `/app` class of failure has a hole in it.

**Approved as a correction to an existing security guard, not new scope.** The
protection becomes bidirectional: every protected Apache root is represented in
the collision test, and no application route may use a prefix Apache blocks.

This is my defect, introduced in PR #40. The fix belongs in this unit because
P1-00 is the unit that adds routes:

- add `public` to `PROTECTED_ROOTS`;
- add the reverse assertion — every directory named in the forwarder's deny rule
  must appear in `PROTECTED_ROOTS`;
- prove it non-vacuous by removing an entry and observing the failure.

### 0.3 D-03 rule 5 cannot be implemented as literally worded — **RESOLVED by D-03.1**

Approved D-03 rule 5 reads:

> "Verified `oid` **and** `tid` must match the expected grant subject."

`oid` is the directory-assigned object identifier. **The operator cannot know it
when issuing the grant** — it is only observable after that user has signed in,
or by looking the user up in the Entra portal. Requiring an `oid` match at issue
time makes the approved mechanism unusable for a genuinely first-time sign-in,
which is the only situation bootstrap exists for.

**Recommended reading (A):** the grant records an expected **UPN/email**.
At callback the design requires:

- `tid` **exactly equals** the configured tenant — a hard match, as approved;
- the verified UPN/email **case-insensitively equals** the grant's expected
  subject;
- `oid` is **captured and stored** on the created administrator, not matched.

The residual risk is that UPN is mutable and reassignable, so a UPN could in
principle be reassigned between issue and redemption. The **30-minute TTL** is
what bounds that, and the window is operator-controlled.

**Stricter alternative (B):** the operator looks the user up in the Entra portal
and supplies the `oid` when issuing the grant. Exact and immutable, at the cost
of a portal lookup in the bootstrap runbook.

**Resolved: reading A is approved as D-03.1** (§19). The operator is explicitly
*not* required to obtain `oid` from Entra before first login. After the first
successful authentication the captured `oid` becomes the identity key, and all
subsequent mapping uses `oid + tid` — never email or UPN.

---

## 1. Route map

Every path checked against the Apache-blocked directory list. None collides.

### 1.1 Pre-authentication

| Method | Path | Name | Purpose |
| --- | --- | --- | --- |
| GET | `/` | `entry` | Login page. Replaces the P1-BASE entry page |
| GET | `/auth/microsoft/redirect` | `auth.microsoft.redirect` | Begins the OIDC authorization request |
| GET | `/auth/microsoft/callback` | `auth.microsoft.callback` | **The registered redirect URI** (D-04). Protocol route, no business UI |
| POST | `/auth/logout` | `auth.logout` | Destroys the session. POST only — a GET logout is CSRF-triggerable |

### 1.2 Refusal and outcome states

All pre-authentication, all standalone cards, none carrying the shell.

| Method | Path | Name | Shown when |
| --- | --- | --- | --- |
| GET | `/auth/access-not-assigned` | `auth.access_not_assigned` | Verified identity, no SemantIQ user |
| GET | `/auth/account-inactive` | `auth.account_inactive` | Known user, status inactive |
| GET | `/auth/access-denied` | `auth.access_denied` | Outside effective access, incl. tenant mismatch |
| GET | `/auth/session-expired` | `auth.session_expired` | Idle or absolute lifetime exceeded, or revoked |
| GET | `/auth/signed-out` | `auth.signed_out` | After deliberate logout |
| GET | `/auth/sign-in-unavailable` | `auth.sign_in_unavailable` | Entra unreachable, misconfigured, or protocol failure |

### 1.3 First-run bootstrap

**Not `/bootstrap`** — see §0.1.

| Method | Path | Name | Purpose |
| --- | --- | --- | --- |
| GET | `/first-run/{grant}` | `first_run.begin` | Validates the grant, stores intent in session, offers Sign in with Microsoft |
| GET | `/first-run/closed` | `first_run.closed` | **Bootstrap Closed** state |

The callback is shared: `/auth/microsoft/callback` detects bootstrap intent from
the session rather than using a second registered redirect URI. **One redirect
URI, exactly as registered in D-04.**

### 1.4 Authenticated

| Method | Path | Name | Purpose |
| --- | --- | --- | --- |
| GET | `/console` | `console.home` | The D-11 minimal confirmation state |

### 1.5 Unchanged from P1-BASE

`/up` stays outside all session middleware, so liveness never depends on the
session store.

---

## 2. Controller boundaries

```text
app/Modules/Platform/
  Http/Controllers/
    EntryController              GET /                      — renders the Login page
    Auth/RedirectController      GET /auth/microsoft/redirect
    Auth/CallbackController      GET /auth/microsoft/callback
    Auth/LogoutController        POST /auth/logout
    Auth/StateController         GET the six refusal states
    FirstRun/BeginController     GET /first-run/{grant}
    ConsoleController            GET /console
  Identity/
    IdentityProvider             interface — the adapter boundary
    Microsoft/EntraProvider      the one Release 1 implementation
    Microsoft/EntraDiscovery     OIDC metadata + JWKS, cached
    Microsoft/IdTokenValidator   signature, issuer, audience, nonce, time
    VerifiedIdentity             immutable value object
    IdentityResolver             VerifiedIdentity → User, or a refusal reason
  Bootstrap/
    GrantIssuer                  called only by the Artisan command
    GrantRedeemer                atomic consume + administrator creation
    BootstrapState               UNCONFIGURED / CONFIGURED
  Security/
    SecurityEventLogger          the D-12 redacted event boundary
  Console/Commands/
    IssueBootstrapGrantCommand   semantiq:bootstrap-grant
```

**Controllers orchestrate; they never validate tokens.** All protocol validation
lives in `IdTokenValidator` and `EntraProvider`, so it is unit-testable without
HTTP and cannot be partially bypassed by a new controller.

---

## 3. Identity adapter boundary

The abstraction required by SYS-011 and the PLAN, with exactly one
implementation. It is deliberately small — a boundary, not a framework.

```php
interface IdentityProvider
{
    public function key(): string;                       // 'microsoft'
    public function isConfigured(): bool;                // false => not offered
    public function beginAuthorization(AuthorizationIntent $intent): RedirectResponse;
    public function completeAuthorization(Request $request): VerifiedIdentity;  // throws on any failure
}
```

`VerifiedIdentity` is immutable and carries only: `provider`, `subject` (`oid`),
`tenant` (`tid`), `email`, `displayName`. **No token, no raw claim set, no
refresh material.** Nothing downstream can accidentally persist or log a token,
because nothing downstream ever receives one.

The Login page offers a provider only when `isConfigured()` is true — Blueprint
§0.2: *"Show another approved IdP only when it has been explicitly configured."*

---

## 4. OIDC authorization flow

Authorization Code Flow with PKCE. The client is confidential (it holds a
secret, D-04) **and** uses PKCE — defence in depth, since PKCE defeats code
interception even if the redirect is somehow intercepted.

### 4.1 Authorization request

```text
GET https://login.microsoftonline.com/{tenant}/oauth2/v2.0/authorize
    client_id             = <client id>
    response_type         = code
    redirect_uri          = https://semantiq.claas2saas.com/auth/microsoft/callback
    response_mode         = query
    scope                 = openid profile email          (D-04 — nothing more)
    state                 = <32 random bytes, base64url>
    nonce                 = <32 random bytes, base64url>
    code_challenge        = BASE64URL(SHA256(verifier))
    code_challenge_method = S256
```

### 4.2 PKCE / state / nonce lifecycle

| Value | Created | Stored | Lifetime | Destroyed |
| --- | --- | --- | --- | --- |
| `state` | Before redirect | Server-side session | 10 minutes | On first callback, **before** validation proceeds |
| `nonce` | Before redirect | Server-side session | 10 minutes | Same |
| `code_verifier` | Before redirect | Server-side session | 10 minutes | Same |
| Bootstrap intent | On `/first-run/{grant}` | Server-side session | 10 minutes | On callback |

**Single use, enforced by removal before validation.** They are read out and
deleted from the session as the first action of the callback, so a replayed
callback finds nothing to compare against and fails. All three are compared with
`hash_equals`.

None of these values is ever logged (D-12).

### 4.3 Token exchange

Back-channel POST to the token endpoint, server to server, carrying the client
secret and `code_verifier`. The response's **ID token** is the only artefact
used. The access token is not requested for any API, not stored, and not logged.

---

## 5. Exact validation sequence

Every step is mandatory. Failure at any step raises a typed exception, issues no
session, and maps to a refusal state. **No step may be skipped when another has
already passed.**

| # | Check | Accepted value | Failure |
| --- | --- | --- | --- |
| 1 | `state` present and matches session | `hash_equals` | Sign-in Unavailable |
| 2 | Provider returned no `error` | — | Sign-in Unavailable |
| 3 | ID token is a well-formed JWS | — | Sign-in Unavailable |
| 4 | Signature verifies against tenant JWKS | `kid` from header, RS256 | Sign-in Unavailable |
| 5 | **Issuer** | exactly `https://login.microsoftonline.com/{tenant}/v2.0` | Sign-in Unavailable |
| 6 | **Audience** | exactly the configured client ID | Sign-in Unavailable |
| 7 | `exp` / `nbf` / `iat` | ±120 s leeway | Sign-in Unavailable |
| 8 | `nonce` | `hash_equals` against session | Sign-in Unavailable |
| 9 | **`tid`** | exactly the configured tenant | **Access Denied** |
| 10 | Required claims present | `oid`, `tid`, and one of `email` / `preferred_username` | Sign-in Unavailable |
| 11 | Resolve `oid` + `tid` to exactly one `users` row | — | **Access Not Assigned**, *no row created* |
| 12 | User status is active | — | **Account Inactive** |
| 13 | Effective access resolved | **empty set in P1-00** | — |
| 14 | Session issued | — | — |

Steps 5, 6 and 9 are three separate checks and must stay separate. Issuer proves
*who signed it*; audience proves *who it was for*; `tid` proves *which directory
the user belongs to*. A token can pass two and fail the third.

### 5.1 Signing key handling

Discovery document and JWKS fetched over HTTPS and cached for **24 hours**. On
an unknown `kid` the JWKS is re-fetched **once**, rate-limited to at most one
refetch per 5 minutes, so Microsoft's key rotation never requires a deployment
and a hostile token cannot force unbounded outbound fetches.

---

## 6. Claim requirements and mapping

| Claim | Required | Maps to | Notes |
| --- | --- | --- | --- |
| `oid` | Yes | `users.external_subject` | **The join key.** Immutable per user per tenant |
| `tid` | Yes | `users.tenant_id` | Validated before mapping |
| `email` or `preferred_username` | Yes (one) | `users.email` | Display and correlation **only** |
| `name` | No | `users.display_name` | Falls back to email |

> `email` is **never** the authorisation key. It is mutable and reassignable; a
> reassigned mailbox must never inherit a SemantIQ identity. This is why the
> unique index is on `(external_subject, tenant_id)`, not on email.

Mapping refreshes `email` and `display_name` on each successful sign-in — they
are projections of the directory, not SemantIQ-owned data. It **never** creates a
row (SYS-014, SYS-015).

---

## 7. Schema

Two migrations. `MigrationIdentifierLengthTest` already guards MySQL's 64-character
index-identifier limit; index names below are explicit and short.

### 7.1 `users`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint unsigned, PK | |
| `provider` | string(32) | `microsoft`. The adapter boundary made real |
| `external_subject` | string(64) | Entra `oid` |
| `tenant_id` | string(64) | Entra `tid` |
| `email` | string(255) | Display/correlation only |
| `display_name` | string(255) | |
| `status` | enum(`active`,`inactive`) | Default `active`. Inactive fails closed |
| `platform_role` | string(32), **nullable** | **D-09 seam.** Only `system_administrator` or NULL |
| `last_signed_in_at` | timestamp, nullable | |
| `created_at` / `updated_at` | timestamps | |

```text
UNIQUE INDEX users_provider_subject_tenant_uq (provider, external_subject, tenant_id)
INDEX        users_platform_role_idx          (platform_role)
```

**The `platform_role` seam (D-09).** A single nullable string admitting exactly
one non-null value, enforced by a PHP enum and a check in the model, with a
docblock stating that **P1-05 owns replacing it**. No roles table, no
permissions, no domains, no scopes, no sensitivity. A test asserts no other
value is ever written.

### 7.2 `bootstrap_grants`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint unsigned, PK | |
| `token_hash` | string(64), unique | **SHA-256 of the grant. The plaintext is never stored** (D-03 rule 2) |
| `expected_subject` | string(255) | Expected UPN/email — subject to §0.3 |
| `expected_tenant` | string(64) | Must equal the configured tenant |
| `issued_by` | string(255) | Operator context for audit — not a secret |
| `expires_at` | timestamp | issue + **30 minutes** (D-03 rule 8) |
| `consumed_at` | timestamp, nullable | **NULL is the single-use guard** |
| `consumed_by_user_id` | bigint unsigned, nullable | FK → `users.id` |
| `created_at` / `updated_at` | timestamps | |

```text
UNIQUE INDEX boot_grants_token_hash_uq (token_hash)
INDEX        boot_grants_expires_idx   (expires_at)
```

### 7.3 `NoBusinessSchemaTest` amendment

`users` moves from the forbidden list to P1-00's ownership; `bootstrap_grants` is
added as owned. Everything else — roles, permissions, domains, scopes,
sensitivity, organisations, teams, entitlements — **stays forbidden**. The
amendment is reviewed as part of this unit, not slipped in.

---

## 8. Bootstrap lifecycle

### 8.1 Issuing (operator, over SSH)

```text
php artisan semantiq:bootstrap-grant --subject=<upn>
```

1. Refuse unless `BootstrapState` is **UNCONFIGURED** (or the recovery condition
   in §8.4 holds).
2. Generate 32 cryptographically random bytes → base64url.
3. Store **only** `hash('sha256', $grant)`.
4. Print the one-time URL to **stdout, once**.
5. Emit `bootstrap.grant.issued` — subject and expiry, **never the grant**.

> **Never printed into GitHub Actions logs** (D-03). The command is operator-run
> over SSH and is never invoked by the deploy workflow. A test asserts the
> workflow does not reference it.

### 8.2 Beginning — `GET /first-run/{grant}`

Looks up by `hash('sha256', $grant)`; requires `consumed_at IS NULL` and
`expires_at > now()`. On success it stores **bootstrap intent + grant id** in the
session and renders the bootstrap card. **It grants nothing** (D-03 rule 3) — the
only next step is Sign in with Microsoft.

Any failure → `/first-run/closed`, with no distinction between *unknown*,
*expired* and *already consumed*, so the endpoint cannot be probed.

Rate limited: 10 attempts per IP per minute.

### 8.3 Redeeming — in the shared callback

After the full §5 validation succeeds and bootstrap intent is present:

```sql
UPDATE bootstrap_grants
   SET consumed_at = :now, consumed_by_user_id = :user
 WHERE id = :id
   AND consumed_at IS NULL
   AND expires_at > :now
```

**Atomic consumption** (D-03 rule 6): the guard is in the `WHERE`, so two
concurrent redemptions cannot both succeed regardless of application timing.
The statement must report **exactly one affected row**; zero means another
request won, and the whole transaction rolls back.

Wrapped in one transaction with administrator creation, so a failure at either
step leaves **no administrator and an unconsumed grant**.

Order of checks, which is what makes D-03 rule 7 hold:

1. Full §5 protocol and identity validation.
2. `tid` equals `expected_tenant` **exactly**.
3. Verified UPN equals `expected_subject`, case-insensitively (§0.3).
4. **Only now** attempt consumption.

A mismatch at step 2 or 3 refuses and **returns before any UPDATE** — the grant
is untouched and remains usable by the correct person (D-03 rule 7).

On success: create the user with `platform_role = system_administrator` and
`status = active`, regenerate the session id, emit `bootstrap.completed`, land on
`/console`.

### 8.4 Closure and recovery

`BootstrapState` is **CONFIGURED** when at least one row has
`platform_role = 'system_administrator'` AND `status = 'active'`. It is computed,
not stored, so it cannot drift from reality.

While CONFIGURED, both `/first-run/{grant}` and the Artisan command refuse.

**Recovery (D-03)** is the same command under the same rule: it becomes available
again **only** when the count of active System Administrators is zero. That is
not a special mode and not a flag — it is the same UNCONFIGURED predicate
returning true again. Recovery therefore cannot be silently enabled, requires an
authorised operator with SSH, is auditable through `bootstrap.grant.issued`, and
still requires full Entra SSO. No administrator is ever created through MySQL.

---

## 9. Session design

### 9.1 Cookie and driver

| Setting | Value | Why |
| --- | --- | --- |
| Driver | `database` | As P1-BASE; server-side, opaque cookie |
| `http_only` | true | No JavaScript access |
| `secure` | true | HTTPS-only site |
| `same_site` | `lax` | `strict` would break the return from Entra |
| `encrypt` | true | |
| `path` / `domain` | `/`, app domain | |

`lax` is deliberate: the callback is a top-level GET navigation from an external
origin, which `strict` would strip the cookie from — the OIDC return would break
and look like a random session failure. CSRF is defended by `state` and by
Laravel's token on the POST logout.

### 9.2 Idle and absolute lifetime (D-10)

**Idle — 60 minutes.** `config('session.lifetime') = 60`, rolling.

**Absolute — 12 hours.** Laravel has no built-in absolute lifetime, so it is
explicit: `authenticated_at` is written into the session at issuance and never
refreshed. `EnsureSessionIsCurrent` compares it on every protected request.
Without this, a user active every 59 minutes would stay signed in indefinitely.

### 9.3 Active-user revalidation (D-10)

On **every protected request**, before any protected functionality is served:

1. Session exists and carries a user id → else **Session Expired**.
2. `authenticated_at` within 12 hours → else invalidate, **Session Expired**.
3. Load the user; must exist and be `active` → else invalidate, **Account
   Inactive**.

One indexed primary-key lookup per protected request, deliberately uncached: a
cache would reintroduce exactly the window D-10 closes. Nothing protected is
served from cache ahead of this check.

### 9.4 Fixation and logout

Session id regenerated on every privilege transition — anonymous → authenticated,
and bootstrap completion.

`POST /auth/logout` invalidates the session server-side, regenerates the CSRF
token, emits `auth.logout`, and redirects to `/auth/signed-out`. **No Entra
global logout** (D-04): SemantIQ must not sign the user out of Outlook or Teams.

---

## 10. Screens

All pre-authentication screens use the **Auth archetype** (§5.7): a standalone
centred card, **no shell, no sidebar, no top bar**. Brand mark from the bundled
asset pack, theme-swapped. Montserrat headings, Source Sans 3 body. Tokens only —
no hardcoded hex. Focus ring on every interactive element. Light and dark. WCAG
AA.

| Screen | Content | Never contains |
| --- | --- | --- |
| **Login** (`/`) | Brand, product name, **Sign in with Microsoft**, optional error flash, trust footer | Menu, user list, tenant name, version, diagnostics |
| **Access Not Assigned** | "Your account is not assigned access to SemantIQ." Contact-administrator route | Whether the identity exists |
| **Account Inactive** | Wording **indistinguishable in shape** from Access Not Assigned | Whether the account exists |
| **Access Denied** | Generic refusal | Which tenant, which resource, why |
| **Session Expired** | "Your session has ended." Sign-in again | Any previous page content |
| **Signed Out** | Confirmation + sign in again | — |
| **Sign-in Unavailable** | "Sign-in is temporarily unavailable." | Protocol detail, issuer, stack trace |
| **First-Run Bootstrap** (`/first-run/{grant}`) | Explains first-administrator setup, **Sign in with Microsoft** | The grant, the expected subject |
| **Bootstrap Closed** | "Setup is already complete." | Whether the grant was unknown, expired or used |

**Sign in with Microsoft** is the primary action: the solid accent button
(`#193E6B` light / `#7FADE1` dark), 32 px tall, per §8 Buttons. Cadmium Violet is
never a button fill.

### 10.1 `/console` — the D-11 confirmation state

Inside the protected boundary, deliberately minimal. Shows: display name, that
authentication succeeded, sign out, and an explicit statement that **no
business/application access has been assigned yet**.

Contains **no** sidebar, Administration Home, menu, P1-01 functionality,
P1-02–P1-10 placeholder, business data, Fabric or Workplace capability.

> It exists to prove `authenticated session → protected route` without
> pre-building the next unit. It is not the shell, and it must not grow into one.

---

## 11. Security events (D-12)

Structured, redacted, through the existing logging boundary. **No audit table** —
P1-08 owns durable storage.

```php
$logger->security('auth.login.refused.inactive', [
    'provider' => 'microsoft',
    'subject'  => $oid,
    'tenant'   => $tid,
    'result'   => 'refused',
    'reason'   => 'inactive',
    'at'       => $timestamp,
]);
```

Events: `bootstrap.grant.issued` · `bootstrap.completed` · `bootstrap.refused` ·
`auth.login.succeeded` · `auth.login.refused.unknown_identity` ·
`auth.login.refused.inactive` · `auth.login.refused.tenant` ·
`auth.login.refused.protocol` · `auth.logout` · `auth.session.expired`

**Never logged:** client secret · authorization code · access or ID token ·
bootstrap grant · `state` · `nonce` · PKCE verifier.

`SecurityEventLogger` accepts a **fixed, typed context shape** rather than an
arbitrary array, so a forbidden value cannot be passed by accident. A test
asserts every event name is declared and that no forbidden key is accepted.

---

## 12. Configuration and secret boundary

`config/identity.php`, all values from environment:

| Key | Env | Secret? |
| --- | --- | --- |
| `microsoft.tenant_id` | `MICROSOFT_TENANT_ID` | No |
| `microsoft.client_id` | `MICROSOFT_CLIENT_ID` | No |
| `microsoft.client_secret` | `MICROSOFT_CLIENT_SECRET` | **Yes** |
| `microsoft.redirect_uri` | `MICROSOFT_REDIRECT_URI` | No |

- Server `.env` **only**. Not a GitHub Actions secret — CI has no runtime need
  (D-04).
- **Never** reaches Inertia props, React, or any rendered page. A test asserts no
  page payload contains the configured secret.
- `ConfigurationRequirements` (P1-BASE) already declares the Microsoft keys as
  *declared but not required*; P1-00 promotes them to **required**, so a
  deployment missing them fails loudly at boot rather than at first sign-in.
- `.env.example` gains the key **names** with empty values.

---

## 13. Migration plan

| Order | Migration | Reversible |
| --- | --- | --- |
| 1 | `create_users_table` | Yes |
| 2 | `create_bootstrap_grants_table` | Yes |

Both run through the deployment pipeline, which already fails the deployment on
migration failure. Both are additive — no existing table is altered, so a
rollback to the previous release keeps working against this schema. Verified in
CI against **real MySQL 8.4**, as P1-BASE established, because SQLite accepts
constructs MySQL rejects.

---

## 14. Abuse and threat cases

| Threat | Control |
| --- | --- |
| Authorization-code replay | Code is single-use at Microsoft; `state`/`nonce`/verifier deleted before validation |
| CSRF on callback | `state`, `hash_equals`, single use |
| `nonce` replay | Compared and deleted; a replay finds nothing |
| Token substitution (wrong audience) | Audience must equal client ID — §5 step 6 |
| Tenant confusion | `tid` exact match — §5 step 9 |
| Issuer spoofing | Issuer exact match + JWKS signature — steps 4–5 |
| Algorithm confusion (`alg: none`, HS256 with public key) | Allow-list **RS256 only**; never trust the header's `alg` |
| Open redirect | No user-supplied return URL is honoured; post-login destination is fixed |
| Bootstrap brute force | 256-bit grant, 30-minute TTL, single use, 10/min/IP |
| Bootstrap replay | Atomic conditional UPDATE; second attempt affects 0 rows |
| Bootstrap hijack | Tenant and subject checked **before** consumption; mismatch does not consume |
| Directory enumeration | Not-assigned and inactive identical in status, shape and wording |
| Timing enumeration | Refusal paths do equivalent work; no early return that is measurably faster |
| Session fixation | Id regenerated on every privilege transition |
| Session riding after deactivation | Revalidated every protected request — §9.3 |
| Secret exposure to the browser | Never in props; asserted by test |
| JWKS fetch abuse | Cached 24 h; at most one refetch per 5 minutes |

---

## 15. Negative tests and their non-vacuity proofs

All thirteen mandatory cases. **Each row names the mutation that must make the
test fail** — the P1-BASE convention, adopted after a guard that could not fail
was found being trusted.

| # | Case | Asserted | Mutation that must fail it |
| --- | --- | --- | --- |
| 1 | Unknown identity | Refused; **`users` count unchanged**; no session | Make the resolver create-or-update |
| 2 | Valid identity, no assignment | Access Not Assigned; no session | Same as 1 |
| 3 | Inactive user | Account Inactive; no session | Drop the status check in §5 step 12 |
| 4 | Wrong tenant | Refused; no session | Remove the `tid` comparison |
| 5 | Invalid issuer | Refused; no session | Accept any issuer |
| 6 | Invalid `state` | Refused; no session | Skip the `state` comparison |
| 7 | Replayed `nonce` | Refused; no session | Stop deleting the nonce after use |
| 8 | Expired session | Session Expired; protected content not served | Remove the absolute-lifetime check |
| 9 | Bootstrap after closure | Bootstrap Closed; no admin created | Let the route run while CONFIGURED |
| 10 | Wrong bootstrap identity | Refused; **grant still unconsumed**; no admin | Move consumption before the subject check |
| 11 | Authenticated System Administrator requesting business data | Refused | Grant business access from `platform_role` |
| 12 | Unauthenticated to a protected URL | No shell, menu or business metadata | Remove the middleware |
| 13 | Refusal bodies | No token, secret, tenant, role mapping or trace | Add the exception message to the view |

Additional guards, each also broken deliberately:

| Guard | Mutation |
| --- | --- |
| Audience must match | Accept any audience |
| `alg` allow-list is RS256 only | Permit `none` |
| Wrong tenant does not consume a grant | Consume before validating |
| Concurrent redemption — only one wins | Drop `AND consumed_at IS NULL` |
| `platform_role` admits only `system_administrator` | Write another value |
| Client secret never in a page payload | Pass config into props |
| `SecurityEventLogger` rejects forbidden keys | Log the raw token |
| Route prefix collision, **both directions** (§0.2) | Remove `public` from either list |
| `/first-run` refusals are indistinguishable | Return a distinct message per cause |

**Test doubles.** Protocol tests run against a controlled issuer with a locally
generated RSA key and a stub JWKS — tokens are minted with the exact defect under
test. No live tenant is required for CI, and **no test is weakened because real
values are not in Git** (PLAN §20.6).

---

## 16. Production verification against the real tenant

CI cannot prove the Entra path. These are run once, by hand, against production
after deployment, and recorded in the VERIFICATION document with observed output.

| # | Check | Expected |
| --- | --- | --- |
| 1 | `/` unauthenticated | Login page; no shell, menu or business metadata |
| 2 | Operator issues a grant over SSH | One-time URL printed once; **not** in any CI log |
| 3 | `/first-run/{grant}` | Bootstrap card; no privilege granted |
| 4 | Complete Entra SSO as the nominated administrator | Administrator created; lands on `/console` |
| 5 | Re-open the same grant URL | **Bootstrap Closed**; still exactly one administrator |
| 6 | Issue another grant | Refused — system is CONFIGURED |
| 7 | Sign out | Signed Out; `/console` no longer reachable |
| 8 | Sign in again | Succeeds; **no new user row** |
| 9 | Sign in as a valid tenant user with no SemantIQ record | Access Not Assigned; user count unchanged |
| 10 | Set that user inactive, sign in | Account Inactive |
| 11 | Deactivate a signed-in user, then request `/console` | Refused on the next protected request |
| 12 | Exposure gate, ACME, checksums | Unchanged and passing |
| 13 | Scan every refusal body | No token, secret, tenant, trace |

Step 5 is the one that proves D-03. Steps 9–11 prove SYS-014/SYS-015 and D-10
against reality rather than a fixture.

---

## 17. Rollback and recovery

| Situation | Response |
| --- | --- |
| Deployment fails before migrations | Pipeline stops; previous release intact |
| Migrations succeed, application faults | Revert the merge and redeploy; both migrations are additive, so the previous release runs unchanged against the new schema |
| Entra misconfigured after deploy | Sign-in Unavailable; site otherwise healthy; fix `.env` and retry — **no rollback needed** |
| Client secret expired | Same; rotation per D-04 |
| Bootstrap grant leaked before use | Wait 30 minutes, or issue a new grant; the leaked one still requires the expected identity to pass Entra |
| Administrator created in error | Deactivate; if none remains active, §8.4 recovery applies |
| All administrators lost | §8.4 recovery — same operator channel, same Entra requirement |

**Never** as rollback: editing production files by hand, inserting an
administrator through MySQL, or disabling a deny rule.

---

## 18. Updated Definition of Done

The PLAN §18 list, plus what this design adds:

1. All PLAN §18 items.
2. All thirteen §15 negative cases **and** the nine additional guards, each
   proven non-vacuous by the stated mutation.
3. `/bootstrap` is not used as a route; every route checked against the Apache
   list (§0.1).
4. `RoutePrefixCollisionTest` guards **both** directions, with `public` present
   in both lists (§0.2).
5. The client secret is proven absent from every page payload.
6. All thirteen §16 production checks executed against the real tenant and
   recorded with observed output.
7. `NoBusinessSchemaTest` still forbids roles, permissions, domains, scopes,
   sensitivity, organisations, teams and entitlements.
8. `platform_role` is proven to admit no value other than `system_administrator`.
9. The Apache boundary, 403 exposure gate, ACME round trip and both checksums
   still pass unchanged.
10. Explicit Product Owner acceptance. **A green CI run does not unlock P1-01.**

---

## 19. Decisions — **ALL DECIDED, 31 August 2026**

> **DESIGN APPROVED. No open Product Owner decision blocks P1-00 implementation.**

### D-03.1 — Bootstrap identity matching · **APPROVED — reading A**

For the initial bootstrap grant:

- match the expected Entra **`tid` exactly**;
- match the expected **UPN/email case-insensitively**;
- after successful Entra authentication, **capture the verified `oid`**;
- **from that point onward, SemantIQ identity mapping uses `oid + tid`, not
  email/UPN.**

> The operator is **not** required to obtain `oid` from Entra before first login.
> That was the whole objection to the literal wording of D-03 rule 5, and this
> resolves it: UPN is used exactly once, to match the grant, and never again as
> an identity key.

Unchanged from D-03: 30-minute TTL · single use · hash-only grant storage ·
atomic consumption · wrong identity does not consume the grant · no privilege
until Entra authentication succeeds.

### D-13 — OIDC implementation · **APPROVED — explicit implementation**

- Normal Laravel/PHP HTTP facilities for the OIDC requests.
- **`firebase/php-jwt`** for JWT/JWKS verification.
- Explicit validation of `state`, `nonce`, PKCE, issuer, audience, tenant and
  required claims — the §5 sequence, each check visible and individually
  testable.

**Scope constraint.** Narrowly scoped to Microsoft Entra OIDC. **Do not turn it
into a large generic identity framework.** The `IdentityProvider` boundary (§3)
stays exactly as designed, so a future provider can be added later without
changing the application authentication contract — but only one implementation
exists, and no second is anticipated in P1-00.

### RoutePrefixCollisionTest correction · **APPROVED in P1-00**

Make the protection **bidirectional**:

- every protected Apache root is represented in the collision test; **and**
- no application route can use a prefix Apache blocks.

Recorded as a **correction to an existing security guard, not new scope**.

---

## 20. Stop point

**DESIGN is approved and EXECUTE is authorised.**

Implementation follows this document exactly and does not redesign while coding.
Where implementation reveals something here is wrong, the discrepancy is
reported rather than silently resolved in code.

Two things still stop the unit short of acceptance, by design:

1. **Live Entra values.** Implementation builds the code and configuration
   contract first. At the point real values are required, work stops and the
   Product Owner receives a short manual action list — what to create in Entra,
   the exact redirect URI, the exact `.env` key names, and where each value
   comes from. **The client secret is never printed or returned.**
2. **Acceptance.** EXECUTE → TEST → VERIFY produces
   `P1-00-LOGIN-BOOTSTRAP-VERIFICATION.md` and the statement
   *P1-00 BUILD VERIFIED — READY FOR PRODUCT OWNER ACCEPTANCE*. Only the Product
   Owner issues acceptance, and P1-01 stays locked until then.
