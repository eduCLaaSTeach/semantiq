# P1-00 — Application Entry, Login & First-Run Bootstrap — PLAN

**Status:** **PLAN APPROVED — 31 August 2026.** All Product Owner decisions
recorded in §20. P1-00 is authorised to proceed to **DESIGN ONLY**; see
`P1-00-LOGIN-BOOTSTRAP-DESIGN.md`. No implementation.
**Unit:** P1-00 (Phase 1 delivery order 2, immediately after P1-BASE)
**Predecessor:** P1-BASE — **ACCEPTED 31 August 2026** at `3d075bf`
**Successor:** P1-01 — Organisation (locked until P1-00 is accepted)
**Authority:** `doc/SemantIQ_v2.2_Ground_Zero_Architecture_Reset_Three_Phase_Blueprint.md`
§0.2–§0.6, §2.8, §3.4 (SYS-000, SYS-001, SYS-011, SYS-013–SYS-018)
`doc/SemantIQ_v2_PHASE_1_System_Administration.md` §5 P1-00
`doc/v2/phase-1/PHASE-1-PLAN.md` §3–§5
**UI standard:** `doc/design-system/ui-and-ux-layout-template-shared.md` §5.7 Auth archetype

> **Lifecycle position.** PLAN → APPROVE ✓ → **DESIGN** *(next)* → APPROVE →
> EXECUTE → TEST → VERIFY → ACCEPT. Nothing in §§1–23 is implemented. **D-03**,
> **D-04**, **D-09**, **D-10**, **D-11** and **D-12** are all decided in §20 and
> are binding on DESIGN.

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
| Step-up / re-authentication for privileged actions (SYS-018) | Deferred to a later unit; not part of P1-00 |
| Session timeout *policy administration* | P1-02 (the fixed values are decided in §20 D-10) |

**No pre-building.** PHASE-1-PLAN §5: *"Future menus, tables and services are not
created early. A shared dependency needed by more than one unit is raised for
approval before it is introduced."* Three such dependencies were raised and are
now approved as **D-09**, **D-11** and **D-12** in §20.

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
| Bootstrap grant record | Single-use, expiring, replay-proof first-admin grant | Shape follows **D-03**, approved |

### Minimum `users` fields

| Field | Why |
| --- | --- |
| External subject identifier (Entra `oid`) | The stable join key. **Not** email — email is mutable and reassignable |
| Home tenant identifier (`tid`) | Tenant validation, and multi-tenant readiness without building it |
| Identity provider discriminator | The adapter boundary made real rather than decorative |
| Email / UPN | Display and administrator correlation only, never the authorisation key |
| Display name | UI |
| Status (active / inactive) | Active-user validation; inactive must fail closed |
| Platform role seam (`system_administrator` only) | **D-09**, approved. A P1-00 seam that P1-05 replaces |
| Timestamps, last successful sign-in | Support and audit correlation |

### What is deliberately absent

No roles table, no domains, no scopes, no sensitivity, no organisations, no
teams, no entitlements, no permissions. `NoBusinessSchemaTest` must be **amended
to transfer ownership of `users` to P1-00 while continuing to forbid the rest** —
a test change, reviewed as part of this unit, not a silent relaxation.

---

## 7. Session model

| Aspect | Position |
| --- | --- |
| Mechanism | Laravel **server-side** session; the browser holds an opaque session cookie only |
| Token storage | Entra ID/access tokens are **not** placed in the session, in the browser, or in logs. Validated, claims extracted, discarded |
| Cookie | `HttpOnly`, `Secure`, `SameSite` per DESIGN; the site is HTTPS-only |
| Fixation | Session identifier regenerated on privilege transition (anonymous → authenticated, and on bootstrap completion) |
| Issuance | Only **after** identity *and* access context are valid (Blueprint step 7) |
| Expiry | **60 minutes idle, 12 hours absolute** (D-10, approved) |
| Logout | Server-side destruction, not merely a cookie clear; lands on **Signed Out**. No global Entra sign-out (D-04) |
| Revocation | Inactive users lose access at the **next protected request**, re-evaluated server-side before protected functionality is served (D-10, approved) |
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

The transition is **one-way**. Recovery — what happens if every System
Administrator is lost — is decided in §20 D-03: a fresh operator-issued grant
through the same privileged channel, permitted only at zero valid System
Administrators, never silently re-enabling bootstrap and never bypassing Entra.

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

P1-00 therefore had a genuine boundary problem: bootstrap creates the most
privileged principal in the system, and doing so with no durable evidence would
be indefensible. **Resolved by D-12, approved.**

P1-00 emits security-relevant events through the application log with a
structured, redacted shape — actor, action, target, result, timestamp — and does
**not** create an audit table. P1-08 later introduces durable storage and adopts
these events. Events:

`bootstrap.grant.issued` · `bootstrap.completed` · `bootstrap.refused` ·
`auth.login.succeeded` · `auth.login.refused.unknown_identity` ·
`auth.login.refused.inactive` · `auth.login.refused.tenant` ·
`auth.login.refused.protocol` · `auth.logout` · `auth.session.expired`

**Never logged:** client secret · authorization code · access or ID token ·
bootstrap token · `state` · `nonce` · PKCE verifier. Identity is recorded as
`oid`/`tid`, not as raw credentials.

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
| Bootstrap grant brute force | High-entropy grant, 30-minute TTL, single use, rate limited |
| Bootstrap replay after consumption | Atomic conditional consume; second attempt refused |
| Bootstrap hijack by a different identity | Verified subject must match the grant; mismatch refuses **and does not consume** |
| Directory enumeration through refusal states | "Not assigned" and "inactive" must not be distinguishable to an anonymous caller by status, timing or body |
| Session fixation | Identifier regenerated on privilege change |
| Session riding after deactivation | Inactive user loses access at the next protected request (D-10) |
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
| All System Administrators lost | Operator-issued recovery grant through the same privileged channel, per §20 D-03 |
| Client secret expired | Sign-in fails cleanly with **Sign-in Unavailable**; rotation owned by the SemantIQ platform / Entra application owner, 12-month target with a 30-day alert (D-04) |

---

## 15. External prerequisites

Outside this repository. None can be satisfied by code. All are **operational
values, not architecture** — see §20.6.

| # | Prerequisite | Owner | Needed by |
| --- | --- | --- | --- |
| 1 | Entra ID application registration | Approved tenant administrator (Application Administrator or Cloud Application Administrator) | EXECUTE |
| 2 | Tenant ID, client ID | Product Owner | EXECUTE |
| 3 | Client secret placed **only** in the server `.env` | Product Owner / operator | EXECUTE |
| 4 | Redirect URI registered exactly as `https://semantiq.claas2saas.com/auth/microsoft/callback` | Product Owner | EXECUTE |
| 5 | Identity of the nominated first System Administrator | Product Owner | Live bootstrap verification |
| 6 | Named platform operator authorised to issue bootstrap grants | Product Owner | Live bootstrap verification |

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
database/migrations/                    users, bootstrap grants             [D-09]
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

**SATISFIED — 31 August 2026.** All conditions hold:

| # | Condition | Status |
| --- | --- | --- |
| 1 | PLAN approved by the Product Owner | ✅ |
| 2 | **D-03** decided — mechanism, TTL, closure, recovery, who may issue | ✅ Option A |
| 3 | **D-04** decided — Entra shape and ownership identified | ✅ single-tenant, `openid profile email`, client secret in server `.env` |
| 4 | **D-09** decided — System Administrator seam before P1-05 | ✅ Option A |
| 5 | **D-10** decided — session lifetime and revocation | ✅ 60 min idle / 12 h absolute / next protected request |
| 6 | **D-11** decided — post-authentication landing | ✅ Option A |
| 7 | **D-12** decided — audit and security event boundary | ✅ redacted events through the existing logging boundary |
| 8 | No secret requested through, or pasted into, chat, GitHub or documentation | ✅ none requested, none present |

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

## 20. Product Owner decisions — **ALL DECIDED, 31 August 2026**

> **PLAN APPROVED.** All six decisions are settled below and are binding on
> DESIGN. Named human identities and runtime values are deliberately absent —
> see §20.6.

### D-03 — First System Administrator bootstrap · **APPROVED — Option A**

Single-use bootstrap grant, redeemed only through successful Entra SSO.

| # | Approved rule |
| --- | --- |
| 1 | Mechanism: single-use, high-entropy bootstrap grant |
| 2 | Stored server-side **as a hash only**, never plaintext |
| 3 | The grant alone grants **no** administrator privilege |
| 4 | The nominated user must complete Microsoft Entra SSO successfully |
| 5 | Verified `oid` **and** `tid` must match the expected grant subject |
| 6 | Consumed **atomically**, and only after successful identity verification |
| 7 | Wrong identity **refuses without consuming** the grant |
| 8 | TTL = **30 minutes** |
| 9 | Bootstrap closes once a System Administrator exists |
| 10 | Bootstrap remains unavailable during normal configured operation |

**Recovery.** Permitted through the same privileged operator channel **only when
the system has zero valid System Administrators**. Recovery issues a fresh,
short-lived, single-use grant, and the replacement administrator completes Entra
SSO exactly like the original bootstrap. Recovery must be explicitly initiated by
an authorised platform operator; must never silently re-enable bootstrap; must be
auditable; must fail closed; must never create an administrator directly through
MySQL; and must never bypass Entra identity verification.

**Who may issue grants.** Restricted to designated SemantIQ platform operators
holding approved cPanel/SSH administrative access. It must **not** be exposed as
a normal application screen, a public HTTP endpoint, or a generic admin menu
action.

**Never print the bootstrap grant into GitHub Actions logs.** The same discipline
that governs APP_KEY governs this value.

### D-04 — Microsoft Entra ID registration · **APPROVED**

| Item | Approved position |
| --- | --- |
| **Tenant model** | **Single-tenant.** Release 1 trusts only the approved eduCLaaS Entra tenant. Multi-tenant identity is not required for P1-00. The architecture stays capable of a future adapter change, but **multi-tenant behaviour is not built now** |
| **Redirect URI** | Exactly `https://semantiq.claas2saas.com/auth/microsoft/callback` |
| **OIDC scopes** | **`openid profile email` only.** No Microsoft Graph. No Mail, Files, Groups, Directory or other API permissions. Anything beyond requires a later explicit Product Owner decision |
| **Credential method** | **Client secret** for Release 1, not a certificate — smaller operational footprint for the initial login implementation, compatible with the existing protected server `.env`, and certificate lifecycle can be reviewed later under P1-02 |
| **Secret storage** | Server `.env` **only**. Not a GitHub Actions secret unless a future workflow has a genuine runtime requirement; CI does not need the application client secret |
| **Secret handling** | Never committed to Git, never in documentation, never printed in CI, never pasted into chat, **never exposed to React/browser code** |
| **Rotation** | Owner: SemantIQ platform / Entra application owner. Target **12 months maximum**, or shorter if tenant policy requires. Operational alert from **at least 30 days** before expiry. P1-02 may later add formal administration and health around this |
| **Registration permissions** | Performed by an approved tenant administrator holding **Application Administrator** or **Cloud Application Administrator**, as the tenant permits |
| **Logout** | **No global Entra sign-out in Release 1.** SemantIQ logout destroys the Laravel server-side session, invalidates the SemantIQ browser session, and lands on **Signed Out**. It must **not** sign the user out of their wider Microsoft 365 session — SemantIQ must not unexpectedly sign a user out of Outlook or Teams. **No mandatory Entra front-channel/global logout is required in P1-00** |

### D-09 — System Administrator representation before P1-05 · **APPROVED — Option A**

The smallest explicit P1-00 seam that can represent `system_administrator`
before the role engine exists: a narrowly typed field on the P1-00 user identity
record.

**Constraints:** only `system_administrator` may exist in P1-00; do **not** add
Organisation Administrator, Executive, Manager, Business User or Auditor; do
**not** create a roles table; do **not** create permissions; do **not** create
domain, scope or sensitivity structures; do **not** treat this field as the
future authorisation engine.

> **P1-05 owns the final role and access model and must replace or migrate this
> P1-00 seam.** A System Administrator still receives **zero automatic
> business-domain access**.

### D-10 — Session lifetime and revocation · **APPROVED**

| Setting | Approved value |
| --- | --- |
| Idle timeout | **60 minutes** |
| Absolute session lifetime | **12 hours** |
| Inactive-user revocation | **Next protected request** |

"Next protected request" means the active-user condition is **re-evaluated
server-side before serving protected functionality**. If the user has become
inactive: destroy or refuse the session, serve the approved inactive/session-ended
state, and **do not continue serving cached protected application data**.

Immediate administrative session-revocation tooling remains owned by the later
user/session administration unit. P1-02 may later make these values
administratively configurable; **P1-00 ships the approved fixed defaults**.

### D-11 — Post-authentication landing · **APPROVED — Option A**

A minimal protected confirmation state inside `/console`.

**May show only:** the signed-in user's safe display identity; confirmation that
authentication succeeded; sign-out; and an explicit message that no
business/application access has yet been assigned.

**Must contain none of:** a sidebar; Administration Home; a fake menu; P1-01
functionality; placeholder P1-02–P1-10 screens; business-domain data; Fabric or
Workplace capability.

Its purpose is to prove `authenticated session → protected route` **without
pre-building the next unit**.

### D-12 — Audit and security event boundary · **APPROVED**

The §11 proposal is approved. P1-00 emits **structured, redacted security events
through the existing application logging boundary** and does **not** create the
P1-08 audit schema early.

Permitted events: bootstrap grant issued; bootstrap completed; bootstrap
refused; login succeeded; login refused; logout; session expired.

**Never logged:** client secret · authorization code · access or ID token ·
bootstrap token · `state` · `nonce` · PKCE verifier.

P1-08 later owns durable audit storage, the catalogue and the UI.

### 20.6 Operational values are not architecture

The following are **external operational prerequisites**, not architecture
decisions, and are deliberately **absent from this repository**:

- Tenant ID · Client ID · client secret
- The nominated first System Administrator identity
- The named Entra registration account
- The named SSH/platform operator

DESIGN may proceed once their **required shape is known, ownership is assigned,
and secure storage location is decided** — all three now hold. The actual values
must be available before EXECUTE and real Entra verification, where technically
required.

> **Tests are not weakened because values are not committed to Git.** Protocol
> validation, mapping and refusal behaviour are tested against controlled
> fixtures; the live end-to-end path is verified separately against the real
> tenant at VERIFY.

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

**This gate is now OPEN.** The PLAN is approved and every decision is recorded
in §20, so DESIGN is authorised.

`P1-00-LOGIN-BOOTSTRAP-DESIGN.md` covers: screen flow and every pre-auth state
against the approved design system; the identity adapter contract and the Entra
implementation; the exact claim-validation sequence; route and controller
structure; the `users` migration and the D-09 seam; the bootstrap grant schema,
lifecycle and atomic consumption; the session configuration; the redacted
security event shapes; and the full test plan including all thirteen negative
cases with their non-vacuity proofs.

**No application code, migration, route, screen, configuration key, Entra
registration or secret is created before that design is approved.**
