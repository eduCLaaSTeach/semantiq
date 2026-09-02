# P1-02 — Identity & SSO Administration — DESIGN

**Status:** **DESIGN — awaiting Product Owner review.** Documentation only.
**Unit:** P1-02 (Phase 1 delivery order 4)
**PLAN:** `P1-02-IDENTITY-SSO-PLAN.md` — **APPROVED 2 September 2026**, decisions
D-26 to D-31, scope corrections §15a.1 and §15a.2
**PLAN merge SHA:** `f3d801bfe83bf9251b471c4a8a5becb59d7e0e13` (PR #76)
**Predecessor:** P1-01 — Organisation — **ACCEPTED 2 September 2026**
**Depends on:** P1-00 — Login, Entra SSO, sessions — **ACCEPTED 31 August 2026**
**UI standard:** `doc/design-system/ui-and-ux-layout-template-shared.md` — **FROZEN**

> **Nothing here is implemented.** No route, controller, service, screen,
> migration, settings table, second provider or editable security setting has
> been created. **This is DESIGN only.**

---

## 1. What this unit is, in one line

**A window onto the front door, never a handle on it.**

Every screen in P1-02 answers a question about the authentication P1-00 already
built. No screen changes it. The one action in the entire unit — **Re-check
now** — re-evaluates a health report and writes a cache entry, and that is the
full extent of what P1-02 can alter about a running system.

The single exception is not a screen at all: the **D-31 correction**, which
brings the *enforced* idle timeout into line with the *approved* one. That is a
one-time configuration correction, authorised by the Product Owner, described in
full in §8, and it is the only part of this unit that changes how the
application behaves for a signed-in person.

---

## 2. The five screens, and exactly what each shows

One feature, `Identity & SSO`, five route-backed tabs — the **Pattern B** strip
P1-01 already uses. No new navigation architecture, no ARIA tab widget, no
client-only switching.

> **One polish concern, raised and not acted on.** The approved tab label
> **`Other Approved IdPs`** carries an abbreviation that is identity-protocol
> jargon (`IdP`). `CLAUDE.md` §4 flags developer terminology on a user-facing
> surface — and §4 equally forbids using the polish gate to overturn an approved
> product decision. The Product Owner named these five tabs verbatim, so the
> label ships as approved. **`Other Identity Providers` is offered as a separate,
> optional wording change, for the Product Owner to accept or decline on its
> own.** It is not made here.

### 2.1 Microsoft Entra ID — `/console/identity`

The default tab. Every value is derived from configuration the server already
holds; nothing on this screen can be edited.

| Row | Value shown | Source |
| --- | --- | --- |
| Provider | `Microsoft Entra ID` | `IdentityProvider::key()`, mapped to the display name |
| Status | `Configured` / `Not configured` | `IdentityProvider::isConfigured()` |
| Directory (tenant) | **Masked**, `a1b2c3d4-…-9f8e`, with **Reveal** | `identity.microsoft.tenant_id`, §4 |
| Application (client) ID | **Masked**, with **Reveal** | `identity.microsoft.client_id`, §4 |
| Client secret | **`Present`** or **`Missing`**, and nothing else, ever | presence only, §3.2 |
| Sign-in return address | Shown **in full** | `identity.microsoft.redirect_uri` |
| Return address matches this deployment | `Matches this deployment` / `Does not match` | compared with `route('auth.microsoft.callback')` |
| Microsoft sign-in offered on the Login page | `Yes` / `No` | the same `isConfigured()` predicate the Login page itself uses |
| Configuration health | The one-line health summary, linking to **SSO Health** | §6 |

**Field labels are business language, not key names.** `Directory (tenant)`, not
`MICROSOFT_TENANT_ID`. The **only** place a raw configuration key name appears in
the whole unit is the *unconfigured* empty state (§9), where naming the missing
key is the actionable information and the value is by definition absent.

**Never shown here or anywhere else in this unit, whole or in part:** the client
secret, any fragment, prefix, suffix, length or hash of it; access, ID or refresh
tokens; authorization codes; PKCE verifiers; `state`; `nonce`; session
identifiers; cookie values; `APP_KEY`; database credentials; any `.env` line.

**No enable/disable control** — D-28.

### 2.2 Other Approved IdPs — `/console/identity/providers`

| Row | Value |
| --- | --- |
| Approved and in use | **Microsoft Entra ID** — the Release 1 provider |
| Anything else | An **empty state**, not an error: *"No other identity provider is configured. Adding one is a Product Owner decision, not a setting."* |
| How another would be added | One sentence: through the existing identity-provider boundary, without changing the sign-in flow |

The list is **derived from the container**, not written into the page: every
binding of `IdentityProvider` is enumerated and displayed. A hardcoded list of
one would become a lie the day a second provider exists, and this screen's whole
value is that it is not lying.

**Google, Okta, Auth0 and every other provider are absent** — not greyed out,
not listed as "available", not a disabled toggle. A greyed-out Google button is a
product claim Release 1 does not make.

### 2.3 Login Experience — `/console/identity/login-experience`

Read-only — **D-29**. This is an **ownership map**, and it says so on the screen
rather than pretending to be a settings page.

| Element | Owned by | Changeable here |
| --- | --- | --- |
| Brand, layout, colours, typography | The frozen UI foundation | No |
| Headline, supporting copy, journey chips, benefit cards, trust row | Approved Product Owner copy | No |
| The `Continue with Microsoft` button | P1-00 sign-in flow + frozen UI | No |
| Whether a Microsoft sign-in option appears | Derived from provider configuration | **No — reported, not set** |
| Refusal wording: not assigned, inactive, session expired, signed out, sign-in unavailable | P1-00 | No |
| Support contact shown on refusal states | P1-00, currently fixed | **No — D-29 declined making it a setting** |

Two live status rows, so the screen is not purely a table of denials:

- **Microsoft sign-in is currently offered / not offered on the Login page** —
  the observable consequence of configuration, stated where somebody looking at
  the Login page would think to ask.
- **The provider in use** — `Microsoft Entra ID`.

The screen carries **no form, no field and no save button.** A read-only screen
with a disabled Save is worse than one with none: it implies a capability that
does not exist.

### 2.4 SSO Health — `/console/identity/health`

The one screen with operational value, and the one action in the unit.

- **One overall state**: `Healthy`, `Needs attention`, or `Sign-in will fail`
  (§6.3 — the user-facing wording for Healthy / Degraded / Failed).
- **One row per check**, each with its own state, a business-readable finding,
  and — where the state is not Healthy — **what to do about it**.
- **When health was last established**, in plain words, or *"Health has not been
  checked on this deployment yet."*
- **`Re-check now`** — one button, System Administrator only, rate limited.

### 2.5 Session Policy — `/console/identity/session-policy`

Read-only — **D-26**. Four rows, each stating the value **actually enforced**,
what it means in plain words, and what a person experiences when it triggers.

| Row | Displayed as | Read from |
| --- | --- | --- |
| Idle timeout | e.g. *"60 minutes"* | `SessionPolicy::idleMinutes()` → `config('session.lifetime')` |
| Absolute session lifetime | e.g. *"12 hours"* | `SessionPolicy::absoluteHours()` → `EnsureSessionIsCurrent::ABSOLUTE_HOURS` |
| Account re-check | *"Every request. Always on."* | a stated property of `EnsureSessionIsCurrent`, §8.3 |
| Where sessions are stored | e.g. *"Database"* | `config('session.driver')`, mapped to a display word |

**No number on this screen is written into the screen.** Every one is read from
the enforced source — requirement 5 of D-31 — and §12 carries the test that
fails if anybody types one in later.

The screen also states, in one sentence, that **these values cannot be changed
from inside the application**, and why: they are a security control, and the
durable audit that changing a security control would require is owned by P1-08.
An administrator who reads this screen and looks for the edit button deserves an
answer rather than a puzzle.

---

## 3. The read model, and how the secret is kept out of it

### 3.1 Shape

A single safe read model, built once per request, shared by every screen and by
the health check. Screens receive **this object serialised**, never configuration.

```
App\Modules\Identity\Support\
    IdentityConfigurationReport   final readonly — the whole safe read model
    IdentitySafeValue             the masking rule, in exactly one place
    SecretPresence                enum { Present, Missing }
    SessionPolicy                 the enforced session values, §8
```

`IdentityConfigurationReport` holds:

| Property | Type | Note |
| --- | --- | --- |
| `providerKey` | `string` | `microsoft` |
| `providerName` | `string` | `Microsoft Entra ID` |
| `configured` | `bool` | |
| `directoryMasked` | `string` | never the full value |
| `applicationMasked` | `string` | never the full value |
| `secret` | `SecretPresence` | **an enum, not a string** |
| `redirectUri` | `string` | shown in full, §3.3 |
| `redirectUriMatchesDeployment` | `bool` | |
| `missingKeys` | `list<string>` | key **names** only, for the unconfigured empty state |

### 3.2 The secret-exclusion architecture

The honest version first: **PHP cannot make reading a config value impossible.**
Any class can call `config()`. So the design does not claim structural
impossibility; it narrows the surface to one line and puts two independent
guards on it.

1. **One read.** `identity.microsoft.client_secret` is read in exactly one place
   in the whole `Identity` module — `SecretPresence::of()` — which returns an
   **enum**. The string is never assigned to a property, never returned, never
   passed on. Every consumer downstream has a `SecretPresence`, and there is no
   way back from it to the value.
2. **An architecture test** (§12, case A3) reads the module's source and the
   Identity page sources and fails if `client_secret` appears anywhere except
   that one resolver. Mutation: read it a second time somewhere else.
3. **A leakage test against real rendered responses** (§12, case S1) — the
   existing `SecretAndLeakageTest`, extended to cover all five screens, the
   reveal endpoint and the health payload. Not a new parallel test: two
   half-covering leakage tests, neither knowing it, is the more dangerous
   arrangement.

Guard 3 is the one that would actually catch a leak; guards 1 and 2 are what
stop somebody writing one in the first place.

### 3.3 Why the return address is shown in full

Deliberate. It is **already public** — it is in the browser's address bar during
every sign-in — and a mismatch between it and the Entra registration is one of
the most common causes of broken sign-in. Masking it would hide the single field
an administrator most needs to compare against a value in another system.

---

## 4. Masking and reveal — D-27

### 4.1 The mask

`IdentitySafeValue::masked(string $value): string`

| Input length | Output |
| --- | --- |
| ≥ 16 characters | first 8, `…`, last 4 — e.g. `a1b2c3d4-…-9f8e` |
| 1 – 15 characters | `••••••••` — enough to say a value exists, not enough to narrow it |
| empty | `Not set` |

Short values are not partially revealed. Showing 4 of 10 characters is a much
larger disclosure than showing 12 of 36, and a rule that behaves differently at
different lengths is the kind of thing nobody re-reads.

### 4.2 Reveal is a server round-trip, not a client toggle

**This is the decision that makes the mask real rather than cosmetic.**

If the full identifier shipped in the page payload and the mask were CSS, then
the value would already be in the HTML of every screenshot-adjacent artefact —
the page source, the browser cache, the Inertia props. The mask would look like
protection while providing none. That is precisely the shape of failure this
project keeps finding, so the design refuses it:

- The page payload contains **only** the masked form.
- `Reveal` posts to `POST /console/identity/entra/reveal` with `field` =
  `directory` or `application`, re-authorised as System Administrator, and gets
  back **that one value** as JSON.
- The revealed value lives in component state for that view only. Navigating
  away, or pressing `Hide`, discards it. It is never persisted, never put in the
  URL, never written to browser storage.
- **POST, not GET**, and therefore CSRF-protected — for the same reason
  `auth.logout` is POST: a GET that returns a value is triggerable by any
  third-party page the administrator happens to visit.
- `Copy` appears only **after** a reveal, and copies the revealed value.

**This pattern is never applied to the client secret.** There is no reveal
endpoint for it, no field name that would accept it, and `SecretPresence` has no
value to return. The reveal endpoint accepts exactly two field names and refuses
anything else with a 422 that names nothing.

### 4.3 Controls, per the frozen design system

`Reveal`, `Hide` and `Copy` are **labelled** buttons in the one neutral secondary
look, carrying their word. **No new icon is added and no second icon architecture
is created** — the existing registry has no eye glyph, and the design system
already requires an action to carry its word beside any icon, so the word alone
is conforming and needs nothing invented.

---

## 5. Routes, authorisation and navigation

### 5.1 Routes

Inside the existing `console` group, so `EnsureSessionIsCurrent` runs first, then
`RequireSystemAdministrator` per route.

| Method | URI | Name | Purpose |
| --- | --- | --- | --- |
| GET | `/console/identity` | `identity.entra` | Microsoft Entra ID |
| GET | `/console/identity/providers` | `identity.providers` | Other Approved IdPs |
| GET | `/console/identity/login-experience` | `identity.login-experience` | Login Experience |
| GET | `/console/identity/health` | `identity.health` | SSO Health |
| GET | `/console/identity/session-policy` | `identity.session-policy` | Session Policy |
| POST | `/console/identity/health/re-check` | `identity.health.recheck` | Re-check now |
| POST | `/console/identity/entra/reveal` | `identity.entra.reveal` | Reveal one identifier |

**Two POSTs, no PUT, no PATCH, no DELETE.** Neither POST writes business data:
one writes a cache entry, one returns a value it read. `LifecycleCompletenessTest`
already asserts the exact set of `DELETE` routes in the application; §12 case A2
extends the same shape here — a third POST, or any PUT/PATCH/DELETE under
`identity`, fails the build. An `.env`-writing route cannot appear quietly.

`identity` is not one of the directories the Apache boundary refuses, and it sits
under `/console` regardless; `RoutePrefixCollisionTest` covers it in both
directions.

### 5.2 The administrator gate

Every route re-authorises through `RequireSystemAdministrator`. Navigation
visibility is **presentation**; the route is the control. Both are asserted, and
separately, exactly as in P1-01.

**One structural change, and it is a move, not a copy.**
`RequireSystemAdministrator` currently lives in
`app/Modules/Organisation/Http/Middleware/`. A second module referencing another
module's middleware is a boundary smell; **a second copy of an authorisation gate
is far worse** — it is the "two sources of truth" failure this project has hit
repeatedly, applied to the one class where being wrong means letting the wrong
person in.

So it is **promoted** to `App\Modules\Platform\Http\Middleware\RequireSystemAdministrator`:
a file move plus two import updates, with **no behavioural change**. The evidence
that the move is safe is that every existing P1-01 boundary test passes
unchanged, because those tests exercise routes rather than class paths.

*If the Product Owner prefers that no accepted P1-01 file is touched at all*, the
fallback is for the Identity routes to reference the class where it currently
lives. That is strictly worse and is offered only as a preference, not a
recommendation.

### 5.3 Navigation

In `ApprovedMenu::systemAdministration()`, one line changes:

```php
NavigationNode::locked($area, 'Identity & SSO', 'i-fingerprint', $policy),
// becomes
NavigationNode::leaf($area, 'Identity & SSO', 'i-fingerprint', 'identity.entra', 'identity.view'),
```

The icon is unchanged, the label is unchanged, and the position in the approved
order is unchanged. `NavigationNode` refuses a locked node with a route and a
leaf without one, so the two states cannot be half-swapped. The registry already
verifies at boot that a leaf's route resolves.

`SystemAdministratorNavigationAuthorizer` needs **no change**: it admits System
Administrators and does not read the policy key's value.

### 5.4 What reading identity configuration grants

**Nothing.** SYS-004, restated: a System Administrator receives no business-domain
access, and there is no business data in this unit to receive. P1-02 answers *"is
the front door healthy?"* and never *"may this person see Finance?"*

**And one boundary specific to this unit:** no Identity screen may become a way to
discover whether a *particular account* exists. These screens report on the
provider, never on identities. There is no failed-sign-in list, no "last user to
sign in", no per-account diagnostic. P1-00 deliberately makes *not assigned* and
*inactive* read identically to an anonymous caller; nothing here undoes that.

---

## 6. The health model

### 6.1 One source, two presentations

```
App\Modules\Identity\Health\IdentityHealthCheck   → IdentityHealthReport
```

- The **SSO Health screen** renders the full report: every check row, its state,
  its finding and its next step.
- **`HealthInspector`** gains one entry, `'identity'`, that calls the *same*
  object and collapses it to the existing `['ok' => bool, 'detail' => string]`
  shape.

`semantiq:health` and the SSO Health screen therefore cannot disagree about the
same deployment — negative case 16. The mutation is to give the inspector its own
copy of the logic; §12 case H7 compares the two and fails.

**Collapsing rule, stated explicitly:** `Failed` → `ok: false`. `Degraded` →
`ok: true`, with the detail naming what needs attention. A deployment must not be
failed by a redirect-address nuance on a system where sign-in demonstrably works;
an outage must fail it.

### 6.2 The checks

| # | Check | Healthy when | Degraded when | Failed when |
| --- | --- | --- | --- | --- |
| 1 | **Provider configured** | all four identity keys present | — | any is missing |
| 2 | **Configuration valid** | the existing production validator reports no identity problem | — | it reports one |
| 3 | **Directory reachable** | OIDC metadata is available — **fresh or from the 24-hour cache** | — | no metadata can be obtained |
| 4 | **Trust anchor available** | at least one usable RSA signing key | — | none, or unreachable |
| 5 | **Directory identity consistent** | the published issuer carries the configured directory id, **or the directory is configured by domain name, where the comparison does not apply** | the directory is configured by id and the published issuer does not carry it | — |
| 6 | **Sign-in return address** | matches this deployment's callback address | it does not match | — |
| 7 | **Client secret present** | present | — | missing |
| 8 | **Session policy coherent** | enforced idle and absolute lifetimes equal the approved policy, and idle is shorter than absolute | either differs | — |

**Check 5 needs its reasoning on the record.** The sign-in path does *not*
compare the configured tenant against the published issuer: `IdTokenValidator`
takes the issuer **from discovery** and compares it to the token. Discovery is
fetched from the configured tenant's own URL, so for a correctly configured
system the two agree by construction. A naive equality check here would therefore
verify something sign-in does not — and would **falsely** report a perfectly
healthy tenant as broken whenever the directory is configured by domain name
(`contoso.onmicrosoft.com`), because the published issuer carries the directory's
id, not its domain. A false `Failed` on a working system is worse than no check
at all: it teaches people to ignore the screen. So the check runs **only** when
the configured directory is an id, and otherwise reports honestly that the
comparison does not apply.

**Check 6 is Degraded, not Failed,** for the same discipline. A deployment behind
a proxy may legitimately present a different external address, and we cannot
prove from here which of the two is correct. The finding says exactly what the
consequence is — *"if these differ, people will be returned to the wrong place
after signing in"* — which is actionable without asserting an outage we have not
observed.

### 6.3 The three states, said in the administrator's words

| Internal | On screen | Meaning |
| --- | --- | --- |
| Healthy | **`Healthy`** | Configuration is complete and coherent; the directory and its signing keys are available |
| Degraded | **`Needs attention`** | Sign-in works. Something is inconsistent and should be corrected |
| Failed | **`Sign-in will fail`** | Sign-in cannot succeed. The reason is named and so is the next step |

Aggregation: **any Failed → Failed; else any Degraded → Degraded; else Healthy.**

**A cached discovery response is `Healthy`** — §15a.2, and it is worth being
precise about why this is not a judgement call. `EntraDiscovery` caches for 24
hours and, on expiry, simply refetches. There is no "stale but served" condition
to report: either metadata is obtainable (fresh or cached) or it is not. Caching
is the designed behaviour, so it produces no warning at all, and the design has
**no code path that can emit "cached" as a concern**.

**Every non-Healthy row carries an action.** No warning ships without one — if a
condition cannot be acted on by the person reading the screen, it is not
displayed as a warning. Worked examples:

| Finding | What the row says |
| --- | --- |
| Directory unreachable | *"The directory could not be reached, so people cannot sign in. Check that the server can make outbound connections to Microsoft."* |
| Secret missing | *"No client secret is configured, so sign-in cannot complete. Set it on the server through the controlled deployment process."* |
| Return address mismatch | *"The sign-in return address configured for Microsoft is not this deployment's return address. If they differ, people will be returned to the wrong place after signing in. Compare it with the redirect URI registered in Microsoft Entra."* |
| Idle timeout differs from policy | *"The idle timeout in force is N minutes; the approved policy is 60. Correct SESSION_LIFETIME on the server through the controlled deployment process."* |

Never an exception message, never a stack trace, never `discovery_unavailable`.

### 6.4 Non-destructive, and rate limited

Health **reads**. It performs no write to any table, changes no configuration,
issues no token, starts no authorization and **never validates a credential by
attempting a sign-in**. The only thing it writes anywhere is the last-result
cache entry in §6.5.

**`Re-check now`** — `RateLimiter`, **one per 60 seconds per administrator**,
key `identity-health-recheck:{user_id}`. Over the limit: *"Health was checked
moments ago. Try again shortly."* — no internal timer named, no seconds counted
down.

**A deliberate refinement of PLAN §7.3, flagged rather than slipped in.** The
PLAN says Re-check *"refreshes discovery and re-evaluates"*. This design has it
**re-evaluate without invalidating the discovery cache**, and the reason is the
PLAN's own §7.2: an administrator holding the refresh key must not become an
outbound-request amplifier against Microsoft. Invalidating a 24-hour cache on
demand is exactly that. The refinement costs nothing diagnostically, because
**failures are never cached** — `Cache::remember` stores nothing when the fetch
throws — so on a deployment where the directory is genuinely unreachable, every
check already reaches the network. Re-check refreshes the *health result*; the
discovery cache is left to the mechanism that was designed to manage it.

### 6.5 Last result — D-30, cache only

One entry, `semantiq:identity:health:last`, holding the state, the time it was
established and the per-check outcomes. **No table, no migration, no retention
policy.** P1-08 owns durable history.

**Two consequences, stated rather than discovered later:**

- Deployment runs `optimize:clear`, which clears the application cache
  (`.env.example` configures a `file` store; the production store is whatever
  the server `.env` sets, and this design does not read it). **A deployment
  therefore clears the last health result**, and the screen correctly reads
  *"Health has not been checked on this deployment yet."* until somebody checks.
  This is the accepted cost of D-30 and it is what the screen is designed to
  say.
- A cache flush loses the history. That is the definition of cache, and the
  screen never presents a missing result as a failure.

### 6.6 Environments without a directory

CI and developer machines have no Entra tenant — deliberately, per
`ConfigurationRequirements`, which requires the identity keys **in production
only**. So outside production an unconfigured provider reports
`ok: true, "Identity is not configured in this environment, which is expected
outside production."`, mirroring the precedent that already exists.

**That exemption is a vacuity risk and is treated as one.** A check that is
trivially green everywhere except the one environment nobody runs tests in is
not a check. §12 case H6 therefore forces `app.env` to `production` with the keys
absent and asserts the state is **Failed** — the production path is exercised in
CI, and the exemption is proven to be an exemption rather than the whole
behaviour.

---

## 7. Events — D-12 boundary, and §15a.1

Two event names, added to `SecurityEventLogger`'s existing catalogue.
**No new context key.** The logger's fixed key list is the reason a secret cannot
be logged by accident, and it is not widened for this unit's convenience.

| Event | Fires when | Context |
| --- | --- | --- |
| `identity.health.checked` | an explicit **Re-check now** completes | `provider`, `user_id`, `result` |
| `identity.health.degraded` | the overall state **changes** from Healthy to anything else | `provider`, `result`, `reason` |

`result` carries `healthy` / `degraded` / `failed`; `reason` carries the key of
the first non-Healthy check. Both are existing permitted keys.

**Naming, stated plainly so nobody is misled by it:** `identity.health.degraded`
fires for a transition into **either** Degraded or Failed. The approved PLAN
§6.5 names exactly these two events, so the name stays; `result` says which state
was actually reached. The alternative — inventing a third event name not in the
approved plan — would be a silent scope change.

**It fires on change, not on evaluation.** The last-result cache (§6.5) is what
makes that possible, and it is what keeps a screen refresh from producing an
event every time. §15a.1: large volumes of low-value security events bury the
ones that matter.

**Deliberately not logged:**

- **Viewing any Identity screen.** §15a.1, explicitly. `identity.configuration.viewed`
  is not built.
- **A successful reveal.** It is a read of a non-secret identifier by somebody
  already authorised to read the screen it is on. Recording it would be the same
  noise §15a.1 rules out. *Carried forward as a consideration for P1-08 if durable
  audit later wants a deliberate-disclosure trail — noted, not built.*
- **A refused request.** The existing refusal path is unchanged and adds no
  event; inventing one here would be building a piece of P1-08 early.

---

## 8. D-31 — the idle-timeout correction

The finding, restated in one line: **`EnsureSessionIsCurrent::IDLE_MINUTES = 60`
is declared and never read.** The idle timeout actually enforced is Laravel's
`config('session.lifetime')`, which production sets to **120**. The approved
policy is 60. Production has been enforcing double the approved value, and no
test would have caught it, because no test asserted idle behaviour at all.

D-31 is approved: **enforce 60**. Recorded as *an accepted P1-00 policy
discrepancy discovered during P1-02 planning; correction authorised under P1-02,
without reopening the P1-00 authentication architecture.*

### 8.1 One source of truth

```
App\Modules\Identity\Support\SessionPolicy

    idleMinutes():   int      // config('session.lifetime')      — ENFORCED
    absoluteHours(): int      // EnsureSessionIsCurrent::ABSOLUTE_HOURS — ENFORCED
    driver():        string   // config('session.driver')        — ENFORCED
    revalidatesEveryRequest(): bool  // always true; D-10, not a setting

    APPROVED_IDLE_MINUTES  = 60      // the POLICY
    APPROVED_ABSOLUTE_HOURS = 12     // the POLICY

    matchesApprovedPolicy(): bool
```

The distinction that makes this work: **enforced values are read from what
enforces them; approved values are the policy they are checked against.** The
Session Policy screen displays the *enforced* values only. Health check 8
compares the two. Nothing anywhere displays the approved constants as though they
were the truth.

**On the obvious objection — is `APPROVED_IDLE_MINUTES` just `IDLE_MINUTES`
wearing a new name?** It looks similar and the resemblance deserves an answer
rather than a hope. The original was dead: `grep` found its declaration and
nothing else, and removing it would have changed no behaviour and failed no test.
This one is read by two things, and **both fail loudly when it disagrees with
what is enforced** — the health check turns the screen amber in production, and
§12 case D4 fails the build. A constant that nothing reads is how this defect
happened; a constant that two guards read is the thing that would have caught it.
If the implementation ever ends up with `APPROVED_IDLE_MINUTES` referenced only
by its own declaration, that is the same defect again and case A4 fails.

`EnsureSessionIsCurrent::IDLE_MINUTES` is **removed** — requirement 4.

### 8.2 Where the number 60 exists after this change

Exactly three places, each with a distinct job, and no fourth:

| Place | Role |
| --- | --- |
| `config/session.php` — `env('SESSION_LIFETIME', 60)` | the default when the key is absent |
| `.env.example` — `SESSION_LIFETIME=60` | requirement 6: the example represents the approved policy |
| `SessionPolicy::APPROVED_IDLE_MINUTES` | the policy the enforced value is checked against |

**And one place it must exist in production:** the server `.env`'s
`SESSION_LIFETIME`, which currently reads 120. §8.4 is how that changes.

**Nowhere else, and that is enforced.** §12 case A4 reads the Identity module,
the Identity pages and the middleware and fails if the literal appears as an idle
timeout anywhere else — in particular, in a React component.

### 8.3 What does *not* change

- **Absolute session lifetime stays 12 hours** — requirement 2.
  `ABSOLUTE_HOURS` remains where it is, in the middleware that reads it.
- **Active-user revalidation stays on every protected request, uncached** —
  requirement 3. It is D-10, not a preference; it has no configuration key, and
  the design adds none.
- **The P1-00 authentication architecture is not reopened.** No change to the
  sign-in flow, the callback, token validation, or session issuance.

### 8.4 The production configuration change — surgical, controlled, and one key

Modelled exactly on the established `deployment/ensure-app-key.sh` pattern: a
script piped to the server over stdin (the `deployment/` directory is excluded
from rsync and has no business living on the host), run as a deployment step
after the sync.

```
deployment/ensure-session-lifetime.sh   <deploy_path> <minutes>
```

Its contract, written before it is written:

1. **Refuses to run if `.env` is absent.** It never creates one.
2. **Rewrites exactly one line** — the `SESSION_LIFETIME=` assignment — in place,
   preserving every other line byte-for-byte, including comments, ordering and
   any key it does not recognise. If the key is absent, it is **appended**;
   nothing else is touched.
3. **Never prints a value from `.env`.** Not the key it changes, not any other.
   It reports only *"SESSION_LIFETIME already matches the approved policy"* or
   *"SESSION_LIFETIME updated to the approved policy"*.
4. **Writes via a temporary file and an atomic rename**, so an interrupted run
   cannot leave a truncated `.env` — which on this deployment would be an outage
   including the loss of `APP_KEY`.
5. **Is idempotent.** A second run reports "already matches" and writes nothing.
6. **Never replaces, regenerates or overwrites `.env`** — requirement 8.

The value it enforces comes from `SessionPolicy::APPROVED_IDLE_MINUTES`, read at
deploy time by `php artisan` rather than typed into the workflow, so the
deployment and the application cannot disagree about the policy.

**The logic lives in a script so it can be tested for real rather than asserted
about** — the same reasoning that produced `tests/Architecture/AppKeyBootstrapTest.php`,
and §12 case D5 is its equivalent: a fixture `.env` containing many keys, run the
script, assert only the one line changed and every other byte survived.

`optimize:clear` already runs after this step, so the configuration cache is
rebuilt and the change takes effect on the next request.

### 8.5 ⚠ The deployment effect, stated plainly

> **Tightening the idle timeout from 120 minutes to 60 signs people out sooner
> than they are used to.**
>
> On the deployment that carries this change, **anyone whose session has been
> idle for more than 60 minutes is signed out at their next request** — including
> people who were signed in when the deployment started. Anyone idle for less
> than 60 minutes is unaffected. From then on, an hour of inactivity ends a
> session instead of two.
>
> Nothing is lost but the session: signing in again restores everything. No data
> is affected. But a person who leaves a screen open over lunch will be asked to
> sign in again where previously they were not, **and they should be told before
> the deployment rather than discovering it.**
>
> This is not a regression. 60 minutes is the approved policy (P1-00 D-10);
> 120 was the defect. **The mistake is being corrected, not the policy relaxed.**

This warning appears three times deliberately: here, in the deployment step's own
output, and in the Product Owner test script (§13), because a person reading any
one of those should not have to have read the other two.

### 8.6 Rollback

If the change must be reverted, it is one controlled step with the same script and
a different value — never a hand edit on the server, and never a replaced `.env`.
Reverting is a **relaxation of an approved security control** and therefore needs
a Product Owner decision, not a deployment choice. The rest of P1-02 is read-only
and rolls back with the code.

---

## 9. Empty, refusal, error and success states

All four, named before anything is built. `CLAUDE.md` §4; P1-01 shipped three and
had to be corrected.

| State | Where | What happens |
| --- | --- | --- |
| **Empty** | Other Approved IdPs | *"No other identity provider is configured."* — plus the sentence that adding one is a Product Owner decision. Presented as information, **not** as an error |
| **Empty** | SSO Health, never checked | *"Health has not been checked on this deployment yet."* — with `Re-check now` offered, and the note that a deployment clears the last result |
| **Empty** | Microsoft Entra ID, unconfigured | Names which parts are missing **by key name, never by value**, and states that sign-in is unavailable until they are set on the server. The only place a raw key name appears (§2.1) |
| **Refusal** | Anonymous | Redirected by `EnsureSessionIsCurrent`. Nothing disclosed |
| **Refusal** | Authenticated non-administrator | Redirected to access-denied. Identical to P1-01's boundary. **Authentication is not authorisation** |
| **Refusal** | Re-check, rate limited | *"Health was checked moments ago. Try again shortly."* — `role="alert"`, no internal timer named |
| **Refusal** | Reveal, unknown field | 422, naming nothing about what fields exist |
| **Error** | Directory unreachable | The business-readable finding from §6.3, in the health row. **Never the exception** |
| **Success** | Re-check completes | The `role="status"` confirmation channel built in P1-01, reused — *"Health re-checked."* |

**The confirmation channel is reused, not reinvented.** `HandleInertiaRequests`
already shares `confirmation`; P1-01's guard already requires every write that
redirects to confirm itself. `IdentityPage` renders the confirmation and the
refusal in the same positions, with the same roles — `role="status"` for a
success, `role="alert"` for a refusal — so the two features do not develop
separate dialects for the same two outcomes.

---

## 10. Screens, and conformance with the frozen design system

### 10.1 Structure

`resources/js/Components/IdentityPage.jsx` — the chrome, mirroring
`OrganisationPage.jsx` exactly:

```
FEATURE   Identity & SSO — what the feature is for
TAB       the section you are in, route-backed (Pattern B)
CONTENT   the section's own title and body
```

`resources/js/Components/IdentityTabs.jsx` — the five tabs, real `<a href>`
elements inside a `<nav>` landmark with `aria-current="page"` on the active one.
Deep-linkable, back and forward work, a refresh keeps the section. Not the ARIA
tab widget: the standard reserves that for genuine in-page panels and forbids
mixing the two on one strip.

Five pages under `resources/js/Pages/Identity/`: `Entra`, `Providers`,
`LoginExperience`, `Health`, `SessionPolicy`.

### 10.2 Archetype

These are **§5.3 Detail / show** screens — labelled read-only rows — and not
§5.5 Settings / Config.

**That distinction is worth being explicit about, because a reviewer will look
for the settings sub-pattern and should find the reason it is absent.** §5.5's
mandatory *test-before-save* contract governs screens that **configure an
outbound connection**: `Reset`, `Test Configuration`, and a `Save` that only
appears after a passing test. **P1-02 has no save, on any screen, at all.** There
is nothing to test before, and rendering that footer would advertise a capability
the unit deliberately does not have. §5.5's masked-secret badge is likewise
inapplicable: it describes a secret *encrypted at rest in the application's own
store*, and this secret is in the server `.env`, which the application never
reads into a view.

The row that is genuinely §5.5-shaped is **`Re-check now`** — a real async health
action — and it takes §8's async-button contract: disabled while in flight, a
loading affordance at a stable width, guarded against double submission, with
the result reported inline and persisting after any transient confirmation.

### 10.3 Status presentation

Health states use the existing theme-aware status tokens and the existing
`StatusPill` component. **Never colour alone** — every state carries its word.
This matters more here than usual: the P1-01 refusal banner shipped with a raw
semantic hex that measured **1.33:1** on the dark card, and the fix was to route
it through the theme's `--danger-edge` token. `ReadableInBothThemesTest` is
extended to cover every new surface rather than trusting that the lesson was
learned.

### 10.4 The professional-polish gate

Before verification is requested, per `CLAUDE.md` §4 and §5, each of the five
screens is opened in a real browser and inspected whole:

- no raw key names, enum values or route names on any surface — with the one
  deliberate exception in §2.1, which is the actionable information;
- no `Degraded`, no `discovery_unavailable`, no `provider_not_configured`
  reaching a person; the user-facing words are §6.3's;
- consistent sentence case; every button label usable;
- empty, error, refusal and success states each actually seen, not inferred;
- hover, focus and disabled states, including the rate-limited `Re-check now`;
- desktop and small-screen widths — P1-01 produced overflow defects at 390px,
  768px and **1101px**, one pixel above a breakpoint that had already been moved
  once, which is why the fix there was a scroll container rather than another
  breakpoint. Any wide content here uses the same `overflow-x` container;
- both themes; browser console clean;
- the navigation entry is actually **discoverable**, not merely routable.

**What is recorded is what was observed**, not what was expected to be true.

---

## 11. Schema and migration impact

**NONE.** No migration, no table, no column, no index, no seed.

D-26 declined a settings table. D-30 declined a health-history table. The D-31
correction changes a configuration value, removes a dead constant and adds a
deployment script; none of those touches the schema.

Everything the unit displays is derived from configuration, from services that
already exist, and from cache.

**Per the standing instruction: if EXECUTE finds a schema change necessary, it
stops and explains before implementing it.** Nothing in this design anticipates
one.

---

## 12. Tests, and the mutation that must make each fail

`CLAUDE.md` §2: a test that cannot fail reports a safety that does not exist.
Every guard below is broken deliberately and observed to fail, and the mutation is
recorded beside the case. Preference is given to the mutation *a person who
misunderstood the rule would plausibly write*.

### Secrets and leakage

| # | Case | Mutation |
| --- | --- | --- |
| S1 | The client secret appears in **no** rendered response — all five screens, the reveal response, the health payload | Render the secret; render four characters of it; render its length |
| S2 | No token, code, PKCE verifier, nonce, state, session id or `APP_KEY` in any Identity payload | Add one to the health payload |
| S3 | The **full** directory id and application id are absent from every page payload; only the masked form ships | Send the full value as a prop and mask it in CSS |
| S4 | The reveal endpoint returns exactly one requested identifier and refuses any other field name | Accept `client_secret` as a field |
| S5 | A security event carrying a forbidden key is a hard failure | Add a secret to the event context |

### Authorisation

| # | Case | Mutation |
| --- | --- | --- |
| B1 | Anonymous → refused on every route, nothing disclosed | Remove the session middleware from the group |
| B2 | Authenticated non-administrator → refused on every route **and both POSTs** | Drop the administrator gate from one route |
| B3 | Navigation visibility and route authorisation are **two** code paths | Make the route trust the menu |
| B4 | Reading identity configuration grants no business access | — asserted against the boundary, not against an empty result |
| B5 | No Identity screen discloses whether a given account exists | Add a per-account failure list |

### Health

| # | Case | Mutation |
| --- | --- | --- |
| H1 | Each check reports Failed for its own real cause | Break that dependency, one at a time |
| H2 | A **cached** discovery response is Healthy, never Degraded | Report cached metadata as Degraded |
| H3 | Health performs **no write** to any table | Make a check update configuration or issue a token |
| H4 | Health never attempts a sign-in or requests a token | Add a token request to a check |
| H5 | `Re-check now` is rate limited | Remove the limiter |
| H6 | With `app.env=production` and the keys absent, the state is **Failed** | Let the non-production exemption apply in production |
| H7 | `HealthInspector`'s identity result agrees with `IdentityHealthCheck` | Give the inspector its own copy of the logic |
| H8 | A failed or unconfigured provider **cannot create a sign-in bypass** | Make an unconfigured or unhealthy provider fall through to an authenticated session |
| H9 | Every non-Healthy row carries an action | Emit a finding with no next step |
| H10 | The degraded event fires on **transition**, not on every evaluation | Fire on every check |

### D-31 — session policy

| # | Case | Mutation |
| --- | --- | --- |
| D1 | **Idle expiry actually happens.** Database session driver; sign in; travel 61 minutes; the next protected request is refused | Set the lifetime to 120 and the 61-minute request stays signed in — the live defect, reproduced |
| D2 | A session idle for **less** than the timeout survives | Expire everything |
| D3 | The absolute lifetime still ends a session at 12 hours regardless of activity | Refresh `authenticated_at` on each request |
| D4 | **Displayed equals enforced.** Set `session.lifetime` to an unusual value; the screen shows that value and **not** 60 | Hardcode 60 in the page |
| D5 | `ensure-session-lifetime.sh` changes exactly one line of a many-key `.env` and preserves every other byte | Rewrite the file from a template |
| D6 | The script prints no value from `.env` | Echo the line it changed |
| D7 | The script is idempotent | Append on every run |
| D8 | `EnsureSessionIsCurrent::IDLE_MINUTES` no longer exists | Reintroduce it |

**On D1's honesty:** it must exercise Laravel's own expiry, not a re-implementation
of it — the database session handler's `last_activity` comparison is the thing that
enforces the timeout. If time travel does not reach that handler, the fallback is
to age the `sessions.last_activity` row directly, which is the same column the
handler reads. **What is not acceptable is asserting on the configured number and
calling it a behaviour test**, which is the exact failure that let the original
defect through.

### Architecture and completeness

| # | Case | Mutation |
| --- | --- | --- |
| A1 | **No code path writes `.env`** — anywhere in the application | Add one |
| A2 | The Identity routes are exactly five GETs and two POSTs; no PUT, PATCH or DELETE | Add a PUT |
| A3 | `client_secret` is referenced in exactly one place in the Identity module | Read it a second time |
| A4 | The literal `60` appears as an idle timeout in no page, component or Identity class other than the three places in §8.2 | Type it into the Session Policy page |
| A5 | Only approved providers are listed; the list is derived, not written | Register a second provider — it must appear, proving the list is real |
| A6 | Every Identity route resolves and every tab points at a real one | Point a tab at a route that does not exist |
| A7 | Every Identity surface is readable in both themes | Use a raw semantic hex as text |

### Presence guards — the P1-01 lesson, applied before it bites

P1-01's root cause, recorded in its verification §7.3h: **an operation that does
not exist has no test to fail.** Behaviour tests only cover what somebody thought
to write a test for. Two guards here assert *shape*, not behaviour:

- **A6** — a tab added later without a route fails immediately, rather than
  rendering a dead link nobody notices.
- **A2** — a write route added later fails immediately, rather than becoming the
  `.env` editor the unit is defined as not having.

---

## 13. Product Owner test script — outline

The full script is written before acceptance is requested, per `CLAUDE.md` §3,
with all twelve required elements. Its shape:

1. **Feature** — P1-02 Identity & SSO Administration, five screens.
2. **Deployed build / merge SHA** — recorded at handover.
3. **Preconditions** — signed in as a System Administrator; the deployment
   carrying P1-02 is live.
4. **Test data required** — **none.** Nothing in this unit creates, changes or
   deletes a business record. This is the first unit with no test-data warning to
   give, and the script says so plainly.
5. **⚠ The one warning that does apply** — §8.5, in full, **before step 1**: the
   idle timeout tightens from 120 to 60 minutes on this deployment, and anyone
   idle longer than an hour is signed out at their next request.
6. **Steps** — reach Identity & SSO from the menu; read each of the five tabs;
   reveal and hide an identifier; press `Re-check now`; press it twice and see
   the rate-limit refusal; confirm the secret reads only `Present`.
7. **Expected result for every step**, in the Product Owner's words.
8. **Negative and security cases** — sign in as a non-administrator (**carried,
   see item 12**); confirm no screen offers a way to change a security setting;
   confirm no screen shows a secret.
9. **Visual and UX checks** — both themes, a narrow window, the empty state on
   Other Approved IdPs, the health empty state directly after deployment.
10. **Evidence to capture** — screenshots of each tab in both themes; the
    rate-limit refusal; the health report.
11. **PASS / FAIL per step.**
12. **What cannot be tested yet, and why** — carried honestly, never inferred
    from a passing test:
    - **The non-administrator refusal** cannot be observed with real production
      data: production has one user, and P1-01 already carried this same gap to
      P1-03, which owns user provisioning. **NOT CURRENTLY OBSERVABLE WITH REAL
      PRODUCTION DATA** — the automated evidence (case B2) stands, and the live
      observation stays carried to P1-03. **The Product Owner is not asked to
      create a fake user to close it.**
    - **A directory outage** cannot be produced on demand without breaking
      production sign-in. The Failed states are proven by automated evidence
      (case H1) and are not staged live.
    - **Client-secret expiry** is not detectable at all in this unit: it needs a
      Microsoft Graph call this design deliberately does not make. **Named as a
      known limit, not as a check that passed.**

---

## 14. Deployment and rollback

| Step | Effect |
| --- | --- |
| Code deploy | Five read-only screens; the navigation entry becomes reachable; `semantiq:health` gains an identity check |
| `ensure-session-lifetime.sh` | **The only behavioural change to a running system** — §8.4, §8.5 |
| `optimize:clear` | Configuration cache rebuilt; **the identity health cache and last result are cleared**, so SSO Health reads *"not checked yet"* until somebody checks — §6.5 |
| Rollback | The screens roll back with the code. Reverting the idle timeout is a **relaxation of an approved security control** and needs a Product Owner decision, not a deployment choice — §8.6 |

**Production verification** follows the established read-only pattern
(`verify-organisation.yml`): a workflow reporting **facts and booleans only** —
the enforced idle minutes, the enforced absolute hours, whether each of the four
identity keys is present, and the overall health state. **No value of any key is
ever printed, and no log content is returned.**

---

## 15. What this design deliberately does not build

- It does not rebuild or restyle the Login page or the sign-in flow.
- It does not write `.env` from the application. There is no code path that can,
  and case A1 keeps it that way. The one controlled configuration change is a
  deployment step, not an application capability.
- It does not add a settings table, a health-history table, or any migration.
- It does not enable, list or hint at a second identity provider.
- It does not provision users, sync groups or define roles.
- It does not log a visit to a read-only screen.
- It does not build durable audit storage; it emits through the existing D-12
  boundary and adds no context key.
- It does not detect client-secret expiry — named in §13 as a known limit rather
  than quietly omitted.
- It does not make health destructive, and it does not let an administrator's
  refresh key become an outbound-request amplifier — §6.4.

---

## 16. Definition of Done

| # | Criterion |
| --- | --- |
| 1 | A System Administrator can determine whether sign-in will work, and why not if it will not, without a shell |
| 2 | The client secret never appears in any rendered response — asserted against real responses on every screen |
| 3 | No token, code, verifier, nonce, state, session id or `APP_KEY` in any payload |
| 4 | Identifiers masked by default, revealed only by an explicit authorised round-trip; **never applied to the secret** |
| 5 | Return-address consistency shown and correct |
| 6 | Health reports Healthy / Needs attention / Sign-in will fail exactly as §6.2 defines, with an action on every non-Healthy row |
| 7 | Health performs no write and no destructive action, and is rate limited |
| 8 | **The displayed session policy is the enforced session policy**, proven by a test that fails when they drift |
| 9 | **Idle expiry is 60 minutes and is proven by behaviour**, not by a configured number |
| 10 | The dead `IDLE_MINUTES` constant is gone |
| 11 | Existing `.env` keys preserved; exactly one key changed, by a tested script, with no value printed |
| 12 | An unconfigured or unhealthy provider cannot create an authentication bypass |
| 13 | No schema change |
| 14 | The Login page and the UI foundation are unchanged |
| 15 | Five screens meet the frozen design system, both themes, responsive, WCAG AA, verified **in a real browser** and recorded as observed |
| 16 | Every guard proven non-vacuous by a recorded mutation |
| 17 | Product Owner test script delivered with all twelve elements |
| 18 | Explicit Product Owner acceptance. **A green CI run does not unlock P1-03** |

---

## 17. Stop point

**Nothing in this design is implemented.** No route, controller, service, screen,
component, migration, settings table, second provider, editable setting or
deployment script has been created, and no configuration has been changed on any
environment.

Two items are put to the Product Owner rather than decided here:

1. **§5.2** — promoting `RequireSystemAdministrator` from the Organisation module
   to Platform: a file move with no behavioural change, recommended, with the
   worse fallback named.
2. **§2** — the `Other Approved IdPs` tab label, raised as a separate optional
   wording change and **not** made.

Neither blocks EXECUTE; both are cheaper to settle now than after the code exists.

**P1-02 DESIGN READY FOR PRODUCT OWNER REVIEW.**
