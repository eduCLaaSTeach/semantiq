# P1-02 — Identity & SSO Administration — PLAN

**Status:** PLAN — awaiting Product Owner review. **Documentation only.**
**Unit:** P1-02 (Phase 1 delivery order 4)
**Predecessor:** P1-01 — Organisation — **ACCEPTED 2 September 2026**
**Depends on:** P1-00 — Login, Entra SSO, sessions — **ACCEPTED 31 August 2026**
**UI standard:** `doc/design-system/ui-and-ux-layout-template-shared.md` —
**FROZEN**

> Nothing here is implemented. No migration, model, route, controller, screen,
> service, seed or test has been created. **This is PLAN only.**

---

## 1. Purpose and business outcome

**Phase 1 exit outcome, quoted:** *identity configuration is supportable and
observable without exposing low-level secrets.*

P1-00 made sign-in work. Nobody can currently answer **"is it healthy?"** without
SSH access to the server. Today, an administrator who suspects an identity
problem has exactly two options: try to sign in and see, or ask someone with a
shell. Both are bad. The first is a live experiment on the front door; the second
means the only people who can diagnose identity are the people who can also
change it.

P1-02 closes that gap and nothing else. It is a **window**, not a control panel:
it reports what identity trust is configured, whether that configuration is
coherent, and whether the provider is reachable — in language an administrator
can act on, without ever showing them a secret.

### The sentence this unit is built to make true

> A System Administrator can determine whether sign-in will work, and why not if
> it will not, **without a shell and without seeing a credential**.

### The mistake this plan is built to prevent

P1-00's design names one shape of mistake, and P1-02 has its own. **It is a
diagnostic screen that becomes an attack surface.** Everything on these screens
reports on the mechanism that guards the front door. A field that shows a little
too much, a health check that can be triggered by anyone, a "disable" toggle that
turns a gate off rather than shutting a door — each one converts a support tool
into a way in.

That is why the boundaries in §6 and §10 are written before the screens.

---

## 2. In scope / out of scope

### In scope

| # | Capability |
| --- | --- |
| 1 | **Microsoft Entra ID** — configured status, tenant and application identity in a safe form, redirect-URI status, configuration health |
| 2 | **Other Approved IdPs** — the provider-adapter boundary, and the honest statement that no other provider is configured |
| 3 | **Login Experience** — the identity-related settings that genuinely belong to identity, and an explicit statement of what is owned elsewhere |
| 4 | **SSO Health** — a non-destructive administrator health view over provider trust |
| 5 | **Session Policy** — the P1-00 values, surfaced; editable or read-only per **D-26** below |
| 6 | The **Identity & SSO** navigation entry becomes reachable; nothing else unlocks |

### Out of scope — each owned elsewhere

| Excluded | Owner |
| --- | --- |
| The Login page's design, copy, brand, layout | **Frozen UI foundation** — accepted, not reopened |
| The authentication flow itself: redirect, callback, token validation, nonce, PKCE, state, session issuance | **P1-00** — *"Do not rebuild the Login flow"* |
| Bootstrap, the first-administrator grant, its issuance or redemption | **P1-00** |
| User provisioning, invitation, deactivation | **P1-03** |
| Groups, directory sync, group-to-role mapping | **P1-03** |
| Roles, permissions, access model | **P1-05** |
| Business domains, sensitivity, data access | P1-04 onward |
| Enabling a second identity provider | **A later explicit Product Owner decision** — see §4B |
| Certificate credentials instead of a client secret | Reviewable later; P1-00 §D chose a client secret for Release 1 |
| Immediate administrative session revocation ("sign this user out now") | **P1-03** — it is user administration, not identity configuration |
| Durable audit storage and the audit catalogue | **P1-08** — P1-02 emits events through the existing D-12 boundary and creates no audit table |
| Fabric or Workplace capability of any kind | Phases 2 and 3 |

### What already exists, and must not be rebuilt

Read from the current implementation before writing this plan:

| Exists | Where | P1-02's relationship to it |
| --- | --- | --- |
| `IdentityProvider` interface — `key()`, `isConfigured()`, `beginAuthorization()`, `completeAuthorization()` | `Platform/Identity/IdentityProvider.php` | **Reads `key()` and `isConfigured()`.** Never calls the other two |
| `EntraProvider` — the single Release 1 implementation | `Platform/Identity/Microsoft/EntraProvider.php` | Reports on it; does not modify it |
| `EntraDiscovery` — OIDC metadata and JWKS, cached 24h, with a 5-minute refetch lock | `Platform/Identity/Microsoft/EntraDiscovery.php` | **This is the SSO-health mechanism that already exists.** P1-02 reads through it rather than writing a second HTTP client |
| `IdTokenValidator` | `Platform/Identity/Microsoft/IdTokenValidator.php` | Not touched |
| `IdentityResolver` — unknown / inactive identity refusals | `Platform/Identity/IdentityResolver.php` | Not touched |
| `ConfigurationValidator` + `ConfigurationRequirements` — the four identity keys, required in production | `Platform/Support/` | **Reports presence, never values.** The existing validator already names a key without its value; P1-02 follows that precedent exactly |
| `HealthInspector` — database, migrations, configuration, storage, assets | `Platform/Health/HealthInspector.php` | **Identity is not among its checks.** P1-02 adds one, in the same shape |
| `SecurityEventLogger` — fixed context keys, forbidden keys are a hard failure | `Platform/Security/SecurityEventLogger.php` | Adds its events to the existing catalogue; adds no new context key |
| `EnsureSessionIsCurrent` — absolute lifetime, active-user revalidation | `Platform/Http/Middleware/` | **Reports its policy. Does not change its behaviour in this unit** |
| `SecretAndLeakageTest` — the client secret never reaches a page payload | `tests/Feature/Identity/` | **Extended, not replaced.** Its assertions must now cover every P1-02 screen |

---

## 3. The five subscreens and their exact capabilities

One feature, `Identity & SSO`, with five route-backed tabs — the Pattern B tab
strip P1-01 already uses, so this introduces no new navigation architecture.

### 3.1 Microsoft Entra ID

**Read-only in this unit.** Every field is derived from configuration the server
already holds.

| Shown | Source | Form shown |
| --- | --- | --- |
| Provider | `IdentityProvider::key()` | `Microsoft Entra ID` |
| Configured | `isConfigured()` | `Configured` / `Not configured` |
| Directory (tenant) | `identity.microsoft.tenant_id` | **See §6.2 — masked, decision D-27** |
| Application (client) ID | `identity.microsoft.client_id` | **See §6.2 — masked, decision D-27** |
| Client secret | `identity.microsoft.client_secret` | **`Present` or `Missing`. The value is never read into a variable that reaches a view** |
| Redirect URI | `identity.microsoft.redirect_uri` | Shown **in full** — see §6.3 |
| Redirect URI consistency | compared against `route('auth.microsoft.callback')` | `Matches this deployment` / `Does not match` |
| Sign-in offered on the Login page | `isConfigured()` | The same predicate the Login page uses, stated |
| Configuration health | §7 | One overall result plus per-check rows |
| Last health result and when | §7.4 | Business-readable, or *Never checked* |

**Never shown, on any screen, in any form, complete or partial:** client secret,
any fragment of it, any token, authorization code, PKCE verifier, `state`,
`nonce`, session identifier, cookie value, or `APP_KEY`.

**No enable/disable control.** The Product Owner's brief allows a provider
enabled/disabled state *"if appropriate"*. **It is not appropriate in Release 1,
and this plan recommends against it** — see §4B and decision **D-28**.

### 3.2 Other Approved IdPs

The screen exists to answer one question honestly: *what else could sign people
in?* The answer today is **nothing**, and saying so plainly is the whole value.

| Shown | Form |
| --- | --- |
| Approved providers | A list containing exactly one entry: Microsoft Entra ID, marked as the Release 1 provider |
| Other providers | An explanatory empty state: **no other identity provider is configured, and adding one requires a Product Owner decision** |
| The adapter boundary | A short statement that a further provider would be added through the existing `IdentityProvider` contract rather than by changing the authentication flow |

**Google, Okta, Auth0 and every other named provider are absent** — not greyed
out, not listed as "available", not shown as a disabled toggle. A greyed-out
Google button is a product claim, and Release 1 does not make it.

### 3.3 Login Experience

**The Login page is frozen. This screen does not restyle, rewrite or re-lay-out
it.** Its job is to state clearly which parts of the sign-in experience are
identity configuration and which are owned elsewhere — because that boundary is
currently invisible, and an administrator looking for "why does the Login page
say that" has nowhere to look.

| Element | Owner | Configurable in P1-02? |
| --- | --- | --- |
| Brand, layout, hero, colours, typography | Frozen UI foundation | **No** |
| Headline, supporting copy, journey chips, benefit cards, trust row | Approved Product Owner copy | **No** |
| The `Continue with Microsoft` button | P1-00 flow + frozen UI | **No** |
| Whether a Microsoft sign-in option appears at all | Derived from `isConfigured()` | **No — reported, not set.** It is a consequence of configuration, and a switch here would be a second source of truth for the front door |
| Refusal state copy — not-assigned, inactive, session-expired, signed-out, sign-in-unavailable | P1-00 | **No** |
| Support contact shown on refusal states | *Currently hardcoded* | **Candidate — decision D-29** |

**This screen may end up genuinely read-only, and that is an acceptable
outcome.** Its value is the ownership map. **D-29** asks the Product Owner
whether the support-contact line is worth making configurable; if the answer is
no, the screen is informational and should say so rather than inventing a
setting to justify itself.

### 3.4 SSO Health

The one screen with real operational value, and the one with the sharpest
security constraints.

| Check | Question answered | Method |
| --- | --- | --- |
| Provider configured | Are all four keys present? | `isConfigured()`, presence only |
| Configuration complete | Does the production validator pass for identity keys? | `ConfigurationValidator`, existing |
| Directory reachable | Is the tenant's OIDC metadata retrievable? | `EntraDiscovery::metadata()` — **cached**, §7.2 |
| Trust anchor available | Are signing keys (JWKS) retrievable and non-empty? | `EntraDiscovery`, existing |
| Issuer consistency | Does the published issuer match the configured tenant? | Discovery `issuer`, compared |
| Redirect URI consistency | Does the configured redirect URI match this deployment's callback route? | String comparison |
| Secret present | Is a client secret configured? | Presence only, **never the value** |
| Session policy coherent | Are the enforced values internally consistent? | §7.5 — **and see the finding in §8.4** |
| Last result | When was health last established, and what did it say? | §7.4 |

**Health checking is strictly non-destructive.** It performs no write, changes no
configuration, issues no token, starts no authorization, and **never validates a
credential by attempting a sign-in**. It reads configuration and fetches public
metadata — nothing else.

Every failure states a **business-readable reason and what to do**, not an
exception. *"The directory could not be reached, so sign-in will fail. Check
outbound network access from the server."* — never `discovery_unavailable`.

### 3.5 Session Policy

| Value | Enforced today | Where |
| --- | --- | --- |
| Absolute session lifetime | **12 hours** | `EnsureSessionIsCurrent::ABSOLUTE_HOURS` |
| Idle timeout | **See §8.4 — this is not what the documents say** | `config/session.php` `lifetime` |
| Active-user revalidation | Every protected request, uncached | `EnsureSessionIsCurrent` |
| Session driver | Database | `config/session.php` |

The screen shows each value, what it means in plain words, and what a person
experiences when it triggers.

**Recommendation: read-only in Release 1.** See **D-26** in §15, with the
reasoning and the guardrails that would be required if the Product Owner decides
otherwise.

---

## 4. Data and configuration points, per screen

### A. Microsoft Entra ID

Reads, and only reads: `identity.microsoft.tenant_id`, `client_id`,
`client_secret` *(presence only)*, `redirect_uri`; `route('auth.microsoft.callback')`;
`IdentityProvider::key()` and `isConfigured()`.

**Existing `.env` keys are preserved exactly. P1-02 writes no `.env` file,
replaces no `.env` file, and adds no identity key.** The unit has no mechanism
capable of writing to `.env`, and §13 records that as a deliberate absence rather
than an omission.

### B. Other Approved IdPs

Reads the set of registered `IdentityProvider` implementations from the service
container. **Derived, not hardcoded** — a hardcoded list of one would be a lie the
day a second provider is approved, and this screen exists to be honest about
exactly that.

### C. Login Experience

Reads `isConfigured()` and the P1-00 route names. Writes nothing unless **D-29**
is approved.

### D. SSO Health

Reads everything above, plus `EntraDiscovery` metadata and JWKS **through the
existing cache**, plus the last recorded health result (§7.4).

### E. Session Policy

Reads `EnsureSessionIsCurrent::ABSOLUTE_HOURS`, `config('session.lifetime')`,
`config('session.driver')`. Writes nothing unless **D-26** is approved.

---

## 5. Read-only versus editable

| Screen | Release 1 recommendation |
| --- | --- |
| Microsoft Entra ID | **Read-only** |
| Other Approved IdPs | **Read-only** |
| Login Experience | **Read-only**, unless D-29 |
| SSO Health | **Read-only**, plus one explicit *Re-check now* action (§7.3) |
| Session Policy | **Read-only**, unless D-26 |

### Why this unit is almost entirely read-only, deliberately

Identity configuration lives in the server `.env`, protected by the Apache
boundary and outside the deployment rsync path. That is not an accident — it is
the arrangement P1-BASE and P1-00 built and verified, and it is why a compromised
application cannot rewrite its own trust anchors.

**An editable Entra screen would require the application to write `.env`**, which
would hand exactly that capability to anything that could reach the screen. The
exit outcome asks for identity to be *supportable and observable*. Observability
delivers it. Editability would buy convenience at the cost of the boundary.

If the Product Owner wants editable identity configuration later, it needs its
own decision, its own threat model, and probably its own storage — not a text
field over `.env`.

---

## 6. Security and secret handling

### 6.1 The absolute rule

**Never rendered, logged, returned, echoed, or placed in any page payload — in
whole or in part:** client secret; any fragment, prefix, suffix, length or hash
of it; access, ID or refresh tokens; authorization codes; PKCE verifiers;
`state`; `nonce`; session identifiers; cookie values; `APP_KEY`; database
credentials; any `.env` line.

**A secret is reported as `Present` or `Missing`. Nothing else.** Not masked, not
truncated, not its length — because a length is a fact about a secret, and the
habit of showing "just a bit" is how the first four characters end up in a
screenshot in a support ticket.

### 6.2 Tenant and client identifiers — decision D-27

These are **not secrets**, but they are not nothing either: together they
identify the exact Entra application to attack.

**Recommendation: masked by default, revealed on an explicit per-view action, in
the form `a1b2c3d4-…-9f8e`** — enough to confirm *"yes, that is our tenant"*
against a value the administrator already has, without putting the full
identifier in every screenshot.

Alternatives for the Product Owner in §15.

### 6.3 The redirect URI is shown in full

Deliberate, and the reasoning matters: it is **already public** — it appears in
the browser address bar during every sign-in — and a mismatch between it and the
Entra registration is one of the most common causes of a broken sign-in. Masking
it would hide the single field an administrator most needs to compare.

### 6.4 Refusals and errors

No stack trace, no framework internal, no configuration value, no exception
message. The same standard as P1-01's negative case 17, and the same reason.

### 6.5 Events

`SecurityEventLogger`, existing D-12 boundary, existing context keys. Candidate
events: `identity.health.checked`, `identity.health.degraded`,
`identity.configuration.viewed`.

**No new context key is added.** The logger rejects an unknown key as a hard
failure, and that guard is the reason a secret cannot be logged by accident. It
is not weakened for this unit's convenience.

### 6.6 The leakage tests are extended, not duplicated

`SecretAndLeakageTest` already asserts against **real rendered responses** that
the client secret never reaches a page payload. Every P1-02 screen joins that
assertion. A second, parallel leakage test would be the more dangerous option:
two tests that each cover half the surface, and neither knowing it.

---

## 7. The health-check model

### 7.1 Shape

Follows `HealthInspector` exactly: named checks, each returning a status and a
business-readable message, aggregated into one report. **Identity becomes a check
in the existing inspector rather than a parallel health system** — otherwise
`semantiq:health` and the SSO Health screen can disagree about the same
deployment, and the operator has to decide which to believe.

### 7.2 Non-destructive, and rate-limited

Health **reads**. It performs no write, changes no configuration, issues no
token, and never attempts a sign-in.

Discovery is already cached for 24 hours with a 5-minute refetch lock. **The
health screen reads through that cache.** Without it, an administrator holding
the refresh key becomes an outbound request amplifier against Microsoft — the
same reasoning that put the refetch lock in `EntraDiscovery` in the first place.

### 7.3 Re-check now

One explicit action, System Administrator only, rate-limited. It refreshes
discovery and re-evaluates. **It is the only non-idempotent thing in the unit,
and it changes nothing but a cache.**

### 7.4 Last result

Health results are worth remembering: *"it broke sometime after Tuesday"* is a
different investigation from *"it has never worked"*.

**Recommendation: cache, not a table.** A last-result cache entry needs no
migration and no retention policy, and P1-08 owns durable history. **D-30** asks
the Product Owner to confirm; if durable history is wanted now, it needs a table
and therefore a schema decision, which §11 does not currently propose.

### 7.5 Degraded is not failed

Three states, because two would lie:

| State | Meaning |
| --- | --- |
| **Healthy** | Configuration complete and coherent; directory and trust anchor reachable |
| **Degraded** | Sign-in works, but something needs attention — a stale cache, a slow directory, an inconsistency that is not yet fatal |
| **Failed** | Sign-in will not work. The reason is named, and so is the next step |

---

## 8. Session-policy proposal

### 8.1 The baseline, unchanged by this plan

Absolute lifetime **12 hours**; active-user revalidation on every protected
request; idle timeout **as recorded in §8.4**.

### 8.2 Recommendation — expose read-only in Release 1

**Reasons.** These values are a security control, and the current ones are
approved (P1-00 D-10). Making them editable adds a way to weaken authentication
from inside the application — precisely the sort of setting an attacker who
reaches an administrator session would change first, and precisely the sort of
change that is invisible afterwards without the audit trail P1-08 has not built
yet. Read-only delivers the exit outcome — *supportable and observable* — at zero
security cost.

### 8.3 If the Product Owner chooses editable — the guardrails

Not a recommendation; the minimum that would make it safe.

| Guardrail | Value |
| --- | --- |
| Idle timeout range | **5 to 120 minutes.** Below 5 is unusable; above 120 is a longer unattended window than the absolute lifetime justifies |
| Absolute lifetime range | **1 to 24 hours.** Never unbounded, never zero |
| Ordering | Idle timeout **must be shorter than** the absolute lifetime, or the absolute one is decorative |
| Authorisation | System Administrator only |
| Audit | Every change recorded as a security event, with old and new values |
| Effect | Applies to **new** sessions; existing sessions keep the policy they were issued under, so a change cannot silently extend a session already open |
| Active-user revalidation | **Never configurable.** It is D-10, not a preference |
| Storage | Requires a decision — a settings table is a schema change (§11) |

### 8.4 ⚠ A FINDING — the documented idle timeout is not the enforced one

**Found while reading the implementation for this plan, and it changes what §8.1
can honestly claim.**

`EnsureSessionIsCurrent` declares:

```php
public const IDLE_MINUTES = 60;
```

**That constant is never used.** `grep` finds exactly two references: its
declaration, and nothing else. `ABSOLUTE_HOURS` is used, in
`beyondAbsoluteLifetime()`; `IDLE_MINUTES` is not used anywhere in the
application or in any test.

The idle timeout actually enforced is Laravel's own `config('session.lifetime')`,
which is **120** — set by `SESSION_LIFETIME=120` in `.env` and `.env.example`.

**So the enforced idle timeout is 120 minutes, not 60.** The Product Owner's
brief states the baseline as *"idle timeout: 60 minutes"*, and P1-00 D-10
approved 60. Production has been enforcing double that.

**No test would have caught it.** There is no test anywhere asserting idle-timeout
behaviour — the same shape as the P1-01 finding: the guard was written, and then
nothing looked at whether it was connected.

**This plan does not fix it.** It is a live authentication-policy discrepancy on
an accepted unit, and the correction is the Product Owner's call, not mine.
**Decision D-31** in §15 puts three options to you. Whichever is chosen, the fix
belongs in P1-02's DESIGN and EXECUTE, with a test that fails when the two
disagree — because a constant nothing reads is exactly how this happened.

---

## 9. Refusal, error, empty and success states

`CLAUDE.md` §4 requires all four. P1-01 shipped three and had to be corrected;
this plan names all four before anything is built.

| State | Where | Behaviour |
| --- | --- | --- |
| **Empty** | Other Approved IdPs | *"No other identity provider is configured."* Explains that adding one needs a Product Owner decision. Not an error |
| **Empty** | SSO Health, never run | *"Health has not been checked on this deployment yet."* Offers the check |
| **Empty** | Entra, unconfigured | Names which parts are missing — **by key name, never by value** — and states that sign-in is unavailable until they are set on the server |
| **Refusal** | Non-administrator | Redirect to access-denied, disclosing nothing. Identical to P1-01's boundary |
| **Refusal** | Re-check, rate-limited | *"Health was checked moments ago. Try again shortly."* Names no internal timer |
| **Error** | Directory unreachable | *"The directory could not be reached, so sign-in will fail."* Plus the next step. **Never the exception** |
| **Success** | Re-check completes | The `role="status"` confirmation built for P1-01 — reused, not reinvented |
| **Success** | Any future save | The same channel. Every write confirms itself; the guard added in P1-01 already enforces this for Organisation and the same shape applies here |

---

## 10. Authorisation boundary

**System Administrator only**, every screen, every action, enforced by
middleware on the route — the same pattern as P1-01, re-authorising per request
rather than trusting navigation visibility.

- Anonymous → refused, nothing disclosed.
- Authenticated non-administrator → refused. Authentication is not authorisation.
- Navigation visibility is **presentation**; the route is the control. Both are
  asserted, separately, as in P1-01.
- **Reading identity configuration grants no business access** — the SYS-004
  boundary, restated. P1-02 answers *"is the front door healthy?"*, never *"may
  this person see Finance?"*

**One boundary specific to this unit:** the Identity screens must not become a
way to discover whether a *particular* account exists. They report on the
provider, never on identities. P1-00 already makes not-assigned and inactive read
identically; nothing here may undo that by, for example, showing recent failed
sign-ins with usernames attached.

---

## 11. Schema and migration impact

**Expected: NONE.** Everything in §1–§10 is derived from existing configuration,
existing services, and cache.

A schema change becomes necessary **only** if the Product Owner approves:

| Decision | What it would need |
| --- | --- |
| **D-26** editable session policy | A settings table, or an equivalent durable store |
| **D-30** durable health history | A health-result table — **and it should not be built here**, because P1-08 owns durable history and building a second one is how two audit stories start |

**If either is approved, DESIGN stops and explains before writing a migration**,
per the standing instruction.

---

## 12. Acceptance criteria

| # | Criterion |
| --- | --- |
| 1 | A System Administrator can determine whether sign-in will work, and why not if it will not, without a shell |
| 2 | The client secret is **never** rendered, logged or returned — asserted against real responses on every screen |
| 3 | No token, code, PKCE verifier, nonce, state, session id or `APP_KEY` appears in any payload |
| 4 | Tenant and client identifiers are handled per **D-27** |
| 5 | Redirect-URI consistency with this deployment is shown and correct |
| 6 | A tenant or provider mismatch is **refused** by the existing P1-00 path, and P1-02 has not weakened it |
| 7 | SSO Health reports healthy, degraded and failed correctly, with business-readable reasons |
| 8 | Health checking performs **no write and no destructive action**, and is rate-limited |
| 9 | Session policy is displayed accurately — **the displayed value is the enforced value**, asserted by a test, per §8.4 |
| 10 | **Disabling or misconfiguring an IdP cannot create an authentication bypass** — see negative case 6 |
| 11 | Existing `.env` keys are preserved; nothing writes `.env` |
| 12 | No user provisioning, groups, roles or business access is created |
| 13 | The Login page and the UI foundation are unchanged |
| 14 | Screens meet the frozen design system, both themes, responsive, WCAG AA |
| 15 | Every guard proven non-vacuous by a recorded mutation |
| 16 | Explicit Product Owner acceptance. **A green CI run does not unlock P1-03** |

---

## 13. Negative tests

Each with the mutation that must make it fail.

| # | Case | Required outcome | Mutation |
| --- | --- | --- | --- |
| 1 | Anonymous request to any Identity screen | Refused, nothing disclosed | Remove the authentication middleware |
| 2 | Authenticated non-administrator | **Refused** | Drop the platform-role gate |
| 3 | Client secret in any rendered response | **Absent** | Render the secret; render four characters of it; render its length |
| 4 | Any token, code, verifier, nonce, state or session id in a payload | **Absent** | Add one to the health payload |
| 5 | Health check performs a write | **No write occurs** | Make a check update configuration or issue a token |
| 6 | **A provider reported as not configured, or a failed health check, creates a bypass** | **Sign-in is refused, never permitted** | Make an unconfigured or unhealthy provider fall through to an authenticated session |
| 7 | Redirect URI differs from this deployment | Reported as inconsistent | Compare the value against itself |
| 8 | Tenant mismatch at sign-in | Refused by P1-00, unchanged | Weaken the tenant comparison |
| 9 | Displayed session policy differs from enforced | **Test fails** | Change the enforced value without changing the display — **§8.4 is this case, live today** |
| 10 | `.env` written by the application | **No such code path exists** | Add one |
| 11 | A second identity provider appears without approval | Only approved providers listed | Register another provider |
| 12 | Health rate limit removed | Re-check is limited | Remove the limiter |
| 13 | Refusal or error body | No trace, internal or configuration value | Render the exception message |
| 14 | An identity screen discloses whether a given account exists | **It does not** | Add a per-account failure list |
| 15 | Security event carries a forbidden key | Hard failure | Add a secret to the event context |
| 16 | Health disagrees with `semantiq:health` | Both read one source | Duplicate the identity check |

**Each must be deliberately broken and observed to fail**, per `CLAUDE.md` §2,
with the mutation recorded beside the case.

---

## 14. Likely files and modules affected

**Extended:**

- `app/Modules/Platform/Health/HealthInspector.php` — one identity check
- `app/Modules/Platform/Security/SecurityEventLogger.php` — event names only
- `app/Shared/Navigation/ApprovedMenu.php` — `Identity & SSO` becomes reachable
- `tests/Feature/Identity/SecretAndLeakageTest.php` — covers the new screens
- `routes/web.php` — five GET routes, one POST re-check

**New, under a new module `app/Modules/Identity/`:**

- `Support/IdentityConfigurationReport.php` — the safe read model
- `Support/IdentitySafeValue.php` — the masking rules, in one place
- `Health/IdentityHealthCheck.php`
- `Http/Controllers/` — one per subscreen, read-only
- `resources/js/Pages/Identity/` — five screens, existing archetypes
- `tests/Feature/Identity/` — the §13 cases
- `tests/Architecture/` — a guard that no identity secret reaches a view

**Not touched:** `EntraProvider`, `IdTokenValidator`, `IdentityResolver`, the
auth controllers, `GrantIssuer`, `GrantRedeemer`, the Login screen, the shell.

---

## 15. Decisions requiring Product Owner approval

### D-26 — Session policy: editable or read-only?

**Recommendation: READ-ONLY in Release 1.**

| Option | Consequence |
| --- | --- |
| **A — Read-only** *(recommended)* | Delivers the exit outcome at no security cost. No schema change. Values stay as approved in P1-00 D-10 |
| B — Editable, guarded | Convenience, at the cost of a way to weaken authentication from inside the application, before P1-08 exists to record it. Needs the §8.3 guardrails **and a schema change** |

### D-27 — How are the tenant and client identifiers displayed?

**Recommendation: B.**

| Option | Consequence |
| --- | --- |
| A — In full | Simplest; puts the exact application identity in every screenshot |
| **B — Masked, revealable on explicit action** *(recommended)* | Enough to confirm a value you already hold; not enough to lift from a screenshot |
| C — Presence only | Safest, and probably too safe — an administrator cannot confirm *which* tenant is configured, which is half the diagnostic value |

### D-28 — Should the Entra provider have an enable/disable control?

**Recommendation: NO for Release 1.**

Release 1 has exactly one provider. A disable control would be a switch whose
only effect is to make sign-in impossible for everyone including the person
pressing it, with no second way in — and a control that can only lock the
building is not an administrative capability. It also creates negative case 6's
risk surface for no benefit. Revisit when a second provider exists.

### D-29 — Is any Login Experience setting genuinely configurable?

**Recommendation: the support-contact line, or nothing.**

The refusal states currently carry hardcoded support guidance. That is the one
element that is identity-related, customer-specific, and outside the frozen
brand. If the Product Owner does not want it configurable, **Login Experience
should be an explicitly read-only ownership map** — which is honest and useful,
and better than inventing a setting to justify a tab.

### D-30 — Does health history need to be durable now?

**Recommendation: cache only.**

Last result and last check time in cache; no migration; P1-08 owns durable
history. Durable history now means a table this unit would own and P1-08 would
have to reconcile with.

### D-31 — ⚠ The idle timeout discrepancy — §8.4

**This needs a decision before P1-02 can display a session policy honestly.**

`IDLE_MINUTES = 60` is declared and never used. The enforced idle timeout is
`session.lifetime` = **120 minutes**. The approved value is 60.

| Option | Consequence |
| --- | --- |
| **A — Enforce 60, as approved** *(recommended)* | Production matches its approved policy. Sessions idle for over an hour end. **Users may be signed out sooner than they are used to** — it is a tightening, and it is the approved value |
| B — Approve 120 as the real value | No behaviour change; updates the documents to match reality. **Requires a Product Owner decision to relax an approved security control** |
| C — Display what is enforced, fix later | Honest display, discrepancy persists. **Not recommended** — it leaves an approved control unenforced with a constant that implies otherwise |

**Whichever is chosen, the dead constant is removed and a test asserts the
displayed value is the enforced value.** A constant nothing reads is how this
happened, and leaving it is how it happens again.

---

## 16. What this plan deliberately does not do

- It does not rebuild the Login flow. P1-00 owns application entry.
- It does not restyle the Login page. The UI foundation is frozen.
- It does not enable another identity provider.
- It does not provision users, sync groups or define roles.
- It does not write `.env`.
- It does not create a schema change, unless D-26 or D-30 forces one — and then
  DESIGN stops and explains first.
- **It does not fix §8.4 unilaterally.** That is a live authentication-policy
  discrepancy on an accepted unit, and D-31 is the Product Owner's call.

---

**P1-02 PLAN READY FOR PRODUCT OWNER REVIEW.**

No DESIGN. No implementation. No routes, controllers, migrations, screens or
services have been created.
