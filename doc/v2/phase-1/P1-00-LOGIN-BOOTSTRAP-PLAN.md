# P1-00 — Application Entry, Login & First-Run Bootstrap — PLAN

**Status:** PLAN — awaiting Product Owner review. No design, no code.
**Unit:** P1-00 (Phase 1 delivery order 2, immediately after P1-BASE)
**Predecessor:** P1-BASE — **ACCEPTED 31 August 2026** at `3d075bf`
**Successor:** P1-01 — Organisation (locked until P1-00 is accepted)
**Authority:** `doc/SemantIQ_v2.2_Ground_Zero_Architecture_Reset_Three_Phase_Blueprint.md`
§0.2–§0.6, §2.8, §3.4 (SYS-000, SYS-001, SYS-011, SYS-013–SYS-018)
`doc/SemantIQ_v2_PHASE_1_System_Administration.md` §5 P1-00
`doc/v2/phase-1/PHASE-1-PLAN.md` §3–§5
**UI standard:** `doc/design-system/ui-and-ux-layout-template-shared.md` §5.7 Auth archetype

> **Lifecycle position.** PLAN → *(you are here)* → APPROVE → DESIGN → APPROVE →
> EXECUTE → TEST → VERIFY → ACCEPT. Nothing in §§1–23 is implemented. Two
> blocking decisions (**D-03**, **D-04**) and three new ones (**D-09**–**D-11**)
> are raised in §20 and are **not** decided here.

---

## 1. Objective

Prove the entire path from a completely unauthenticated browser, through
Microsoft Entra ID SSO, to a secure SemantIQ session — **including every refusal
case** — and establish the first System Administrator through a controlled
bootstrap rather than a manual database edit.

Blueprint §0.6 states the bar:

> "Before Phase 1 is considered complete, SemantIQ must prove the entire path
> from a completely unauthenticated browser through Microsoft SSO, SemantIQ
> identity mapping, effective-access resolution and a secure role-appropriate
> landing page — including refusal cases for unknown, inactive and unentitled
> users."

P1-00 delivers the identity and session boundary. It delivers **no business
capability**, and — this is the point most easily got wrong — **successful
Microsoft authentication grants zero automatic business-domain access.**

### The single most important rule in this unit

```text
Identity
+ Organisation
+ Role
+ Domain
+ Scope
+ Sensitivity
+ Ownership / Hierarchy
+ Policy
= Effective Access
```

P1-00 establishes **only the first term**. Every other term is a later unit.
Authentication is not authorisation, and a System Administrator receives **no
business-domain data** by virtue of being a System Administrator
(Blueprint SYS-004, §2.5, §2.8.1; PHASE-1-PLAN D-01).

---

## 2. Actors and personas

| Actor | In P1-00 | Not in P1-00 |
| --- | --- | --- |
| **Unauthenticated visitor** | Sees only the Login page and pre-auth states | Never sees shell, menu, or any business metadata |
| **Operator / deployer** | Holds SSH + GitHub deploy trust; initiates the bootstrap grant | Does not become an application user by that fact |
| **First System Administrator** | Established once, through the bootstrap path, and must still pass Entra SSO | Receives no business-domain access |
| **Known active SemantIQ user** | Authenticates and receives a session | Has no entitlements to resolve yet — P1-04/P1-05 own those |
| **Known inactive user** | Refused, fail closed | — |
| **Valid Microsoft identity, no SemantIQ assignment** | Refused, fail closed | Is **not** auto-provisioned (SYS-015) |
| **Identity from an unapproved tenant** | Refused, fail closed | — |

Roles beyond "System Administrator" (Organisation Administrator, Executive,
Domain Owner, Manager, Business User, Auditor) are **named** by D-01 but
**modelled** in P1-05. P1-00 does not create them.

---

## 3. User journeys

### 3.1 First-run journey (once per deployment)

```text
Fresh deployment, no System Administrator exists
    ↓
Application entry reports UNCONFIGURED
    ↓
Operator creates a single-use bootstrap grant through the approved channel   [D-03]
    ↓
Nominated first administrator opens the bootstrap entry
    ↓
Sign in with Microsoft  →  Entra ID authenticates (MFA / conditional access
                            per the organisation's own policy)
    ↓
Callback validation: issuer, tenant, state, nonce, required claims
    ↓
Verified identity is matched against the grant's expected subject
    ↓
First System Administrator created + organisation/tenant trust established
    ↓
Grant consumed atomically; bootstrap path closed                             [D-03]
    ↓
Privileged session issued  →  post-bootstrap landing                         [D-11]
```

### 3.2 Normal-login journey

```text
Unauthenticated visitor opens any protected URL
    ↓
Redirected to the Login page — no shell, no menu, no business metadata
    ↓
"Sign in with Microsoft"
    ↓
Redirect to the configured Entra authorization endpoint
    ↓
Entra verifies the user
    ↓
Callback: validate protocol response, issuer, tenant, state, nonce, claims
    ↓
Resolve external identity → active SemantIQ user     (fail closed if unknown)
    ↓
Confirm the user is active                           (fail closed if inactive)
    ↓
Resolve effective-access context — in P1-00 this is deliberately EMPTY
    ↓
Issue the Laravel server-side session
    ↓
Land the user                                                                [D-11]
```

### 3.3 Refusal and error journeys

Every one of these ends in a dedicated pre-authentication state that leaks
nothing. Blueprint §0.2: *"Do not expose tokens, tenant secrets, internal role
mappings or diagnostic traces in browser-visible errors."*

| Journey | Ends at | Session issued? |
| --- | --- | --- |
| Authenticated, no SemantIQ user record | **Access Not Assigned** | No |
| Known user, status inactive | **Account Inactive** | No |
| Identity from a non-approved tenant | **Access Denied** (tenant mismatch, worded generically) | No |
| Issuer fails validation | **Sign-in Unavailable** / generic failure | No |
| `state` or `nonce` invalid or replayed | Generic authentication failure | No |
| Session expired or revoked | **Session Expired** | Terminated |
| User signs out | **Signed Out** | Terminated |
| Authenticated user requests a resource outside effective access | **Access Denied** | Retained |
| Bootstrap attempted after closure | **Bootstrap Closed** | No |
| Bootstrap attempted by an identity other than the grant subject | Refused, grant **not** consumed | No |

Each state offers a route to contact an administrator (Blueprint §0.2, "Help"),
and none of them distinguishes "no such user" from "user inactive" in a way that
lets an anonymous caller enumerate the directory (§12).

---

## 4. Functional scope

Directly from `SemantIQ_v2_PHASE_1_System_Administration.md` §5 P1-00:

| # | Item |
| --- | --- |
| 1 | Branded Login page, approved shared design, standalone card, **no shell** |
| 2 | **Sign in with Microsoft** as the primary Release 1 action |
| 3 | Approved future IdP **adapter boundary** (abstraction only, one implementation) |
| 4 | Microsoft Entra ID OIDC authentication flow |
| 5 | Callback validation — protocol response, `state`, `nonce`, PKCE |
| 6 | Issuer validation |
| 7 | Tenant validation |
| 8 | Authenticated identity → SemantIQ user mapping |
| 9 | Active-user validation |
| 10 | No self-registration by default |
| 11 | Laravel server-side session |
| 12 | Secure logout |
| 13 | Session expiry |
| 14 | Access-not-assigned state |
| 15 | Inactive-account state |
| 16 | Access-denied state |
| 17 | Signed-out state |
| 18 | Controlled first-System-Administrator bootstrap |
| 19 | Bootstrap disabled / restricted after successful first use |
| 20 | No manual MySQL insertion as the normal first-admin process |
| 21 | Successful authentication grants **zero** automatic business-domain access |

Phase 1 doc, line 137: this is the ground-zero starting point and is **not a
sidebar menu item**.

---

## 5. Explicitly out of scope

Not built, not stubbed, not "prepared for".

| Out of scope | Owner |
| --- | --- |
| Identity & SSO **administration** screens, Login Experience, SSO Health, Session Policy UI | **P1-02** — which states: *"Do not rebuild the Login flow. P1-00 owns the application-entry implementation."* |
| Organisation, business units, departments, teams, hierarchy, legal entities | P1-01 |
| Users & Groups administration, directory sync, invitations, lifecycle UI | P1-03 |
| Business Domains | P1-04 |
| Roles, entitlements, scopes, sensitivity, Access Simulator | P1-05 |
| Security Status, Access Reviews, **Audit UI and audit tables**, System Health, Administration Home | P1-06 – P1-10 |
| Any Fabric Configuration or SemantIQ Workplace screen | Phases 2–3 |
| A second identity provider implementation | Later, when explicitly configured |
| Step-up / re-authentication for privileged actions (SYS-018) | Deferred — see §20 D-10 |
| Concrete session timeout *policy administration* | P1-02 (the *values* are D-10 here) |

**No pre-building.** PHASE-1-PLAN §5: *"Future menus, tables and services are not
created early. A shared dependency needed by more than one unit is raised for
approval before it is introduced."* Two such dependencies are raised in §20
(**D-09**, **D-11**) rather than assumed.

---

## 6. Data and entities required

P1-BASE deliberately left the schema almost empty: the only migration is
`0001_01_01_000000_create_sessions_table.php`. There is **no `users` table**, and
`NoBusinessSchemaTest` currently *forbids* one — correctly, because P1-BASE did
not own it.

P1-00 needs the smallest identity surface that can fail closed. Identified here;
**not created** until DESIGN is approved.

| Entity | Purpose | Notes |
| --- | --- | --- |
| `users` | Map a verified external identity to an active SemantIQ principal | New in P1-00 |
| `sessions` | Laravel server-side session store | **Exists** from P1-BASE |
| Bootstrap grant record | Single-use, expiring, replay-proof first-admin grant | Shape depends on **D-03** |

### Minimum `users` fields

| Field | Why |
| --- | --- |
| External subject identifier (Entra `oid`) | The stable join key. **Not** email — email is mutable and reassignable |
| Home tenant identifier (`tid`) | Tenant validation, and multi-tenant readiness without building it |
| Identity provider discriminator | The adapter boundary made real rather than decorative |
| Email / UPN | Display and administrator correlation only, never the authorisation key |
| Display name | UI |
| Status (active / inactive) | Active-user validation; inactive must fail closed |
| Timestamps, last successful sign-in | Support and audit correlation |

### What is deliberately absent

No roles table, no domains, no scopes, no sensitivity, no organisations, no
teams, no entitlements, no permissions. `NoBusinessSchemaTest` must be **amended
to transfer ownership of `users` to P1-00 while continuing to forbid the rest** —
a test change, reviewed as part of this unit, not a silent relaxation.

> **Open:** representing "System Administrator" before P1-05 exists is a real
> modelling problem. Raised as **D-09** in §20. Not decided here.

---

## 7. Session model

| Aspect | Position |
| --- | --- |
| Mechanism | Laravel **server-side** session; the browser holds an opaque session cookie only |
| Token storage | Entra ID/access tokens are **not** placed in the session, in the browser, or in logs. Validated, claims extracted, discarded |
| Cookie | `HttpOnly`, `Secure`, `SameSite` per DESIGN; the site is HTTPS-only |
| Fixation | Session identifier regenerated on privilege transition (anonymous → authenticated, and on bootstrap completion) |
| Issuance | Only **after** identity *and* access context are valid (Blueprint step 7) |
| Expiry | Idle and absolute lifetimes — **values are D-10** |
| Logout | Server-side destruction, not merely a cookie clear; lands on **Signed Out** |
| Revocation | An inactive user must lose access; whether that is immediate or next-request is **D-10** |
| `/up` | Remains outside the session middleware, as P1-BASE established, so liveness never depends on the session store |

---

## 8. Identity mapping rules

Evaluated in this order. **Any failure stops the flow and issues no session.**

1. Protocol response is well formed and signed by the expected issuer.
2. **Issuer** matches the configured Entra issuer exactly.
3. **Tenant** (`tid`) is an approved tenant.
4. `state` matches the originating request and has not been replayed.
5. `nonce` matches the one issued for this authorization request.
6. PKCE verifier matches.
7. Required claims are present (`oid`, `tid`, and an email/UPN claim).
8. `oid` + `tid` resolves to **exactly one** existing `users` row.
   - No row → **Access Not Assigned**. **No user is created** (SYS-014, SYS-015).
9. That user's status is active. Inactive → **Account Inactive**.
10. Effective access is resolved. **In P1-00 this resolves to the empty set**,
    and that is the correct, tested outcome — not a gap.
11. Session issued.

> Rule 8 is where self-registration would creep in if anyone made mapping
> "helpful". It must fail closed, and a negative test proves no row is written.

---

## 9. Bootstrap state

The application has an observable installation state:

| State | Meaning | Behaviour |
| --- | --- | --- |
| **UNCONFIGURED** | No System Administrator exists | Bootstrap entry reachable **only** with a valid grant; no business data; normal login still fails closed for everyone |
| **CONFIGURED** | At least one System Administrator exists | Bootstrap entry permanently answers **Bootstrap Closed** |

Blueprint §0.3: *"After bootstrap is complete, the bootstrap path is disabled or
otherwise made non-reusable according to the approved design."*

The transition is **one-way** under the recommended option. Recovery — what
happens if every System Administrator is lost — is a real operational question
and is raised as part of **D-03**; it is not silently designed in.

---

## 10. Backend authorisation boundary

Blueprint §2.5: *"Menu hiding is convenience only. Every protected query and
action must be authorised again at the backend and data/semantic layer."*

P1-BASE already proved deny-by-default with `RequireAuthenticatedSession` on
`/console`, which currently refuses everything. P1-00 changes that middleware
from "refuse everyone" to "refuse everyone without a valid session" — and
**nothing more permissive than that**.

- Authentication establishes *who*. It never establishes *what they may see*.
- Every later unit's protected read and write must re-authorise. P1-00 must not
  introduce any helper that could be mistaken for an entitlement check.
- A System Administrator reaching for business data in P1-00 gets nothing,
  because there is nothing and because there is no grant — and a negative test
  asserts exactly that (§13, case 11).

---

## 11. Audit events

Blueprint SYS-008 requires immutable audit evidence for privileged access
changes, and Phase 1 doc P1-08 lists "successful/failed login evidence" as an
**audit-unit** test. The audit *table*, *UI* and *event catalogue* belong to
**P1-08**.

P1-00 therefore has a genuine boundary problem, and it is raised rather than
resolved unilaterally: bootstrap creates the most privileged principal in the
system, and doing so with no durable evidence would be indefensible.

**Proposal (subject to §20 D-09/D-11 style approval as a shared dependency):**
P1-00 emits security-relevant events through the application log with a
structured, redacted shape — actor, action, target, result, timestamp — and does
**not** create an audit table. P1-08 later introduces durable storage and adopts
these events. Events proposed:

`bootstrap.grant.issued` · `bootstrap.completed` · `bootstrap.refused` ·
`auth.login.succeeded` · `auth.login.refused.unknown_identity` ·
`auth.login.refused.inactive` · `auth.login.refused.tenant` ·
`auth.login.refused.protocol` · `auth.logout` · `auth.session.expired`

No token, secret, client secret, `state`, `nonce`, code or PKCE verifier appears
in any event. Identity is recorded as `oid`/`tid`, not as raw credentials.

---

## 12. Security and abuse cases

| Case | Required behaviour |
| --- | --- |
| Authorization-code replay | Code redeemed once; a second redemption fails |
| `state` forgery / CSRF on callback | Rejected; no session |
| `nonce` replay | Rejected; no session |
| Open redirect via `redirect_uri` or a post-login `next` parameter | Only allow-listed, same-origin destinations |
| Token substitution (token from another application/audience) | Audience must match the configured client ID |
| Tenant confusion (valid Microsoft account, wrong directory) | Rejected on `tid` |
| Issuer spoofing | Rejected on issuer + signature |
| Bootstrap grant brute force | High-entropy grant, short TTL, single use, rate limited |
| Bootstrap replay after consumption | Atomic conditional consume; second attempt refused |
| Bootstrap hijack by a different identity | Verified subject must match the grant; mismatch refuses **and does not consume** |
| Directory enumeration through refusal states | "Not assigned" and "inactive" must not be distinguishable to an anonymous caller by status, timing or body |
| Session fixation | Identifier regenerated on privilege change |
| Session riding after deactivation | Inactive user loses access per **D-10** |
| Secret leakage | No client secret in repository, logs, CI output, PR text, or browser-visible errors |
| Login-page content leakage | Pre-auth pages carry no menu, no user list, no tenant name, no version string |

---

## 13. Negative tests — mandatory

PHASE-1-PLAN §5: *"Negative tests are mandatory, not optional extras."* Each of
the following must be an automated test that **fails if the guard is removed**;
the P1-BASE convention of deliberately breaking each guard and observing the
failure applies.

| # | Case | Required outcome |
| --- | --- | --- |
| 1 | Unknown identity | Refused, **no user row created**, no session |
| 2 | Valid Microsoft identity, no SemantIQ assignment | **Access Not Assigned**, no session |
| 3 | Inactive SemantIQ user | **Account Inactive**, no session |
| 4 | Wrong / unapproved tenant | Refused on `tid`, no session |
| 5 | Invalid issuer | Refused, no session |
| 6 | Invalid callback `state` | Refused, no session |
| 7 | Invalid / replayed `nonce` | Refused, no session |
| 8 | Expired session | **Session Expired**, protected content not served |
| 9 | Bootstrap attempt after closure | **Bootstrap Closed**, no admin created |
| 10 | Unauthorised bootstrap identity (grant exists, wrong subject) | Refused, grant **not** consumed, no admin created |
| 11 | Authenticated System Administrator requesting business data without explicit business access | Refused — authentication is not entitlement |
| 12 | Unauthenticated request to any protected URL | No shell, no menu, no business metadata |
| 13 | Refusal-state bodies | No token, secret, tenant name, role mapping, stack trace or diagnostic detail |

Cases 1–3 together are the proof of SYS-014/SYS-015. Case 11 is the proof of
SYS-004 and is the one most likely to be quietly skipped, because in P1-00 there
is no business data to request — the test must therefore assert against the
*authorisation boundary*, not against an empty result set.

---

## 14. Failure and recovery behaviour

| Failure | Behaviour |
| --- | --- |
| Entra unreachable / times out | **Sign-in Unavailable**; no partial session; retry offered |
| Entra returns an error response | Generic failure state; detail logged, not shown |
| Clock skew on token validation | Bounded leeway per DESIGN; outside it, refuse |
| Signing-key rotation at Microsoft | Keys re-fetched and cached with a bounded lifetime; rotation must not require a deployment |
| Database unavailable during callback | Fail closed, no session; `/up` still answers per P1-BASE |
| Session store unavailable | Fail closed |
| Bootstrap grant lost before use | Issue a new grant; the old one expires unused |
| All System Administrators lost | **Recovery procedure is an open question — part of D-03** |
| Client secret expired | Sign-in fails cleanly with **Sign-in Unavailable**; rotation ownership is part of **D-04** |

---

## 15. External prerequisites

Outside this repository. None can be satisfied by code.

| # | Prerequisite | Owner | Blocks |
| --- | --- | --- | --- |
| 1 | Entra ID application registration | Product Owner / tenant admin | **D-04** |
| 2 | Tenant ID, client ID | Product Owner | **D-04** |
| 3 | Client secret (or certificate) placed **only** in the server `.env` | Product Owner / operator | **D-04** |
| 4 | Redirect URI registered exactly | Product Owner | **D-04** |
| 5 | Decision on single-tenant vs multi-tenant for Release 1 | Product Owner | **D-04** |
| 6 | Identity of the first System Administrator | Product Owner | **D-03** |
| 7 | Session lifetime values | Product Owner | **D-10** |

---

## 16. Files expected to change

Indicative, for review scale only — not a commitment.

```text
app/Modules/Platform/Identity/          adapter boundary, Entra adapter,
                                        claim validation, identity mapping
app/Modules/Platform/Bootstrap/         grant issue + consume + closure     [D-03]
app/Modules/Platform/Http/Controllers/  entry, login, callback, logout,
                                        bootstrap, refusal states
app/Modules/Platform/Http/Middleware/   RequireAuthenticatedSession (extended)
config/                                 identity/session configuration keys
database/migrations/                    users                               [D-09]
resources/js/Pages/Auth/                Login, refusal states, bootstrap
routes/web.php                          entry, auth, bootstrap routes
tests/Feature/ tests/Architecture/      the §13 negative suite
tests/Architecture/NoBusinessSchemaTest.php   transfer `users` ownership to P1-00
doc/v2/phase-1/P1-00-LOGIN-BOOTSTRAP-DESIGN.md
```

No route may begin with a directory the Apache boundary blocks —
`RoutePrefixCollisionTest` enforces this, and it already cost one deployment
(`/app` → `/console`). Candidate paths must be checked against that list at
DESIGN time.

---

## 17. Definition of Ready

P1-00 may enter DESIGN when **all** hold:

1. This PLAN is approved by the Product Owner.
2. **D-03** is decided — bootstrap mechanism, TTL, closure and recovery.
3. **D-04** is decided — Entra registration values identified and owned.
4. **D-09** is decided — how "System Administrator" is represented before P1-05.
5. **D-10** is decided — session lifetime and revocation timing.
6. **D-11** is decided — post-authentication landing while no menu exists.
7. No secret value has been requested through, or pasted into, chat, GitHub or
   documentation.

## 18. Definition of Done

1. Every §4 scope item implemented; every §5 exclusion still absent.
2. All thirteen §13 negative cases automated, each proven non-vacuous.
3. CI green; live deployment green through the unchanged pipeline.
4. The full path proven end to end against the real Entra tenant.
5. Bootstrap completed once, then proven closed and non-reusable.
6. No secret in repository, logs, CI output, PR text or browser-visible error.
7. `P1-00-LOGIN-BOOTSTRAP-VERIFICATION.md` records observed evidence, including
   anything that failed.
8. The Apache boundary, exposure gate, ACME check and checksum controls still
   pass unchanged.
9. Explicit Product Owner acceptance. **A green CI run does not unlock P1-01.**

## 19. Acceptance criteria

| # | Criterion |
| --- | --- |
| 1 | An unauthenticated browser receives only the Login/entry experience — no shell, menu or business metadata |
| 2 | Microsoft SSO proven end to end: Login → Entra → validated callback → active-user mapping → session → landing |
| 3 | Unknown, unassigned, inactive, wrong-tenant, invalid-issuer, invalid-state/nonce and expired-session cases all fail closed |
| 4 | No self-registration: authentication alone creates no user and no access |
| 5 | First-admin bootstrap completes securely and is afterwards non-reusable |
| 6 | No manual MySQL insertion is required as the normal first-admin process |
| 7 | Logout and session expiry return safe states without leaking protected content |
| 8 | An authenticated System Administrator receives **no** business-domain access |
| 9 | Refusal states leak no token, secret, tenant detail, role mapping or trace |
| 10 | Login page and all pre-auth states meet the approved design system in light and dark, responsive, WCAG AA |

---

## 20. Product Owner decisions required

### D-03 — First-administrator bootstrap · **BLOCKING**

Deferred from Phase 1 planning; now live.

**Recommended: single-use bootstrap grant, redeemed only by completed Entra SSO.**

An operator who already holds SSH and deploy trust runs an Artisan command on the
server. It records a grant — high-entropy secret stored only as a hash, an
expected subject, a short expiry, a single-use flag — and prints the one-time
value to that operator's terminal alone. The nominated administrator opens the
bootstrap entry with it, and it grants **nothing** by itself: they must still
complete Microsoft SSO, and the verified `oid`/`tid` must match the grant's
expected subject. Only then is the first System Administrator created, the grant
consumed by an atomic conditional update, and the bootstrap path closed.

*Why this one:* it adds no new standing secret, no new trust channel and no email
dependency — it reuses the SSH boundary that already exists and is already the
only privileged channel. It satisfies every constraint: controlled, restricted
eligibility, auditable, replay-proof, fail closed, closed after use, and no
ordinary database manipulation.

| Alternative | Why not recommended |
| --- | --- |
| **B — Allow-list in server `.env`** (`BOOTSTRAP_ADMIN_UPN`; first successful SSO by that identity becomes admin) | Simplest, but leaves a *standing* privilege-granting value in a hand-maintained file. Easy to leave behind; if an administrator is later removed, it silently re-arms |
| **C — One-time setup token in `.env`** | Same shape as A, but the secret lives in `.env` — a second place to leak from, and rotation means editing production configuration |
| **D — Artisan command creates the administrator directly** | Violates Blueprint §0.3: the first administrator *"signs in through the verified enterprise identity path before receiving the privileged application session."* Also creates a principal that never passed MFA |
| **E — Claim-next-login flag** | A variant of B with the same standing-privilege weakness |

**Risks in the recommendation:** the grant value must never reach a log or CI
output (same discipline as APP_KEY); operator terminal capture is the residual
exposure; a mistyped expected subject wastes a grant (safe — it refuses without
consuming); and one-way closure means losing every administrator needs a
deliberate recovery path.

**Exact decisions required:**

1. Approve mechanism **A**, or select B/C/E.
2. Grant TTL — recommend **30 minutes**.
3. Closure rule — recommend permanent while any System Administrator exists.
4. Recovery if all administrators are lost — recommend a fresh operator-issued
   grant through the same command, which is deliberately as privileged as the
   original. **Confirm this is acceptable.**
5. Who may run the command (which operator identities).
6. The nominated first System Administrator (identity only — no secret).

### D-04 — Microsoft Entra ID registration · **BLOCKING**

**Do not paste any secret into chat, GitHub, this document, or a pull request.**
Secret values belong only in approved server configuration. This PLAN needs the
*shape and ownership* of the configuration, not the values.

| # | Item | Needed at | Recommendation |
| --- | --- | --- | --- |
| 1 | **Tenant ID** (directory ID) | Server `.env` | Not a secret, but treat as configuration |
| 2 | **Application (client) ID** | Server `.env` | Not a secret |
| 3 | **Client secret** *or* certificate | Server `.env` **only** | Prefer a **certificate** if the tenant permits — no expiry surprise mid-quarter; otherwise a secret with a diarised rotation owner |
| 4 | **Redirect URI** | Registered in Entra **and** in config | Propose exactly `https://semantiq.claas2saas.com/auth/microsoft/callback` — **confirm** |
| 5 | **Registering account / role** | Entra portal | Requires Application Administrator or Cloud Application Administrator. **Name the person or role** |
| 6 | **Single-tenant vs multi-tenant** for Release 1 | Registration + validation | Recommend **single-tenant**; multi-tenant widens the trust boundary with no Release 1 benefit |
| 7 | **Scopes / consent** | Registration | Recommend `openid profile email` **only** — no Microsoft Graph. Anything more needs a stated reason |
| 8 | **Secret storage location** | Operations | Server `.env`, which is already excluded from transfer and deletion. **Not** a GitHub Actions secret — the application reads it at runtime, and CI has no need of it |
| 9 | Front-channel logout URI | Optional | Decide whether logout should also terminate the Entra session |

**Exact decisions required:** items 3, 4, 5, 6, 7 and 9 above, plus who owns
secret rotation and on what cadence.

### D-09 — Representing "System Administrator" before P1-05 · **NEW, BLOCKING**

P1-00 must create a System Administrator, but the role model belongs to P1-05,
and PHASE-1-PLAN forbids pre-building it. Three options:

| Option | Assessment |
| --- | --- |
| **A (recommended)** — one narrowly-typed column on `users` admitting only `system_administrator`, explicitly documented as a P1-00 seam that P1-05 replaces | Smallest thing that works; impossible to mistake for the authorisation engine; migrates cleanly |
| **B** — build the full role table now | Directly violates the no-pre-building rule and pre-empts P1-05's design |
| **C** — infer administrator status from "was created by bootstrap" | Implicit, unqueryable, and breaks the moment a second administrator exists |

**Decision required:** approve A, or direct otherwise. This is a shared
dependency and needs explicit approval per PHASE-1-PLAN §5.

### D-10 — Session lifetime and revocation · **NEW, BLOCKING**

Blueprint defers the numbers; P1-02 owns the *administration* of session policy,
but P1-00 must ship with values.

**Decisions required:** idle timeout (recommend **60 minutes**); absolute
lifetime (recommend **12 hours**); whether deactivating a user terminates live
sessions immediately or at next request (recommend **next request** in P1-00,
with immediate revocation deferred to P1-03 where user lifecycle lives).

### D-11 — Post-authentication landing · **NEW, BLOCKING**

Blueprint step 8 says administrators land on "their authorised administration
experience" — but no administration menu exists until P1-01, and P1-00's own exit
criterion forbids implementing one. **The source documents do not resolve this.**

| Option | Assessment |
| --- | --- |
| **A (recommended)** — a minimal authenticated confirmation state inside `/console`: signed-in identity, sign-out, and an explicit "no access assigned yet" message. No menu, no business metadata | Honest about the system's actual state, proves the session works, builds nothing P1-01 owns |
| **B** — land on the P1-BASE shell with empty navigation | Risks implying capability that does not exist; D-02 forbids placeholder screens |
| **C** — land back on a signed-in variant of the entry page | Cannot demonstrate that a protected route is reachable, weakening acceptance criterion 2 |

**Decision required:** approve A, or direct otherwise.

---

## 21. Settled decisions — not reopened

D-01 effective access model · D-02 three product areas · **D-07 Laravel →
Inertia → React 19 → Vite** · D-08B `public_html` as permanent document and
deployment root · root `public_html/index.php` front controller · GitHub Actions
→ SSH → cPanel deployment · APP_KEY preservation · deployment-controlled MySQL
migrations · the approved Apache 403 exposure boundary.

*(A prior source-document scan reported D-07 as still open. It is not — it was
approved during P1-BASE and is in production. Recorded here so the stale reading
is not repeated.)*

---

## 22. Risks specific to this unit

| Risk | Handling |
| --- | --- |
| Identity mapping made "helpful" and quietly auto-provisions | Negative test 1 asserts **no row is written** |
| Refusal states leak enough to enumerate the directory | States are indistinguishable to an anonymous caller; tested |
| Client secret reaches a log, CI output or PR | Same discipline as APP_KEY: never printed, never echoed, never transferred |
| Bootstrap left reusable | Closure asserted by test **and** by live verification after real use |
| A route prefix collides with a blocked directory | Checked against the Apache list at DESIGN; `RoutePrefixCollisionTest` enforces |
| Authentication mistaken for authorisation in later units | The boundary is stated in §10 and tested by negative case 11 |
| Entra tenant unavailable at verification time | Verification cannot be faked; the unit stays unaccepted until the real path runs |

---

## 23. Stop point — the exact DESIGN gate

**This PLAN stops here.**

DESIGN begins only when the Product Owner has:

1. approved this PLAN;
2. decided **D-03** (six sub-decisions);
3. decided **D-04** (items 3, 4, 5, 6, 7, 9 plus rotation ownership);
4. decided **D-09**, **D-10** and **D-11**.

`P1-00-LOGIN-BOOTSTRAP-DESIGN.md` will then cover: screen flow and every
pre-auth state against the approved design system; the identity adapter contract
and the Entra implementation; the exact claim-validation sequence; route and
controller structure; the `users` migration; the bootstrap grant lifecycle; the
session configuration; the audit event shapes; and the full test plan including
all thirteen negative cases with their non-vacuity proofs.

**No application code, migration, route, screen, configuration key, Entra
registration or secret is created before that design is approved.**
