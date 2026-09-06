# P1-05 — Roles & Access: DEPLOYMENT NOTE

**Read this before deploying P1-05.** It records two things the code cannot
enforce on its own: a configuration change that must be made in Entra, and the
exact limits of rolling this release back.

| | |
| --- | --- |
| DESIGN | `P1-05-ROLES-ACCESS-DESIGN.md` — **APPROVED 6 September 2026** |
| DESIGN merge SHA | `73f62bc5ffa3beab17975e8107698f48f4f20f1c` |
| Binding | **§2.7** the rollback contract · **§9.6** what changes in P1-00 |

---

## 1. A SECOND ENTRA REDIRECT URI MUST BE REGISTERED FIRST

**Step-up re-authentication returns to a DISTINCT callback**, so that an
ordinary sign-in can never be mistaken for a re-authentication or the reverse —
D-73, §9.6.

> **Register `https://<host>/auth/microsoft/step-up` as a second redirect URI on
> the SemantIQ application in Entra, before the deploy.**

**Until that is done, step-up fails on its first use.** Microsoft refuses the
authorization request with `redirect_uri_mismatch`, the administrator is
returned to Roles & Access with a refusal, and **no privileged action is
applied** — which is the correct failure, but it is a failure.

**Nothing else in P1-05 depends on it.** Roles, entitlements, scopes and
sensitivity levels below `restricted` all work without it. What does not work is
granting or revoking System Administrator, granting Organisation Administrator,
self-granting, and granting `restricted`.

---

## 2. THE DEPLOYMENT SEQUENCE

The code and the schema change together, and the existing deploy already stages
them that way: **maintenance window → sync → migrate → cache clear → health →
window closes.** There is no moment at which new code reads a dropped column or
old code reads a missing one.

**Before the deploy, confirmed rather than assumed:**

| # | |
| --- | --- |
| 1 | Production holds **exactly one** `users.platform_role` row — `platform_roles_total: 1`, recorded in `P1-04-BUSINESS-DOMAINS-VERIFICATION.md` §9.5 |

**Immediately after, and BEFORE any Roles & Access administration is used —
this is deployment validation window A, §3 below:**

| # | Verification |
| --- | --- |
| 1 | **The migrated System Administrator assignment exists** — exactly one, from that one column row |
| 2 | **Sign-in works** |
| 3 | **`/console` is reachable** |
| 4 | **The last-administrator guard refuses** — attempt to revoke the sole administrator and observe the refusal |
| 5 | **Schema and read-only counts report as expected** |

---

## 3. ROLLBACK — TWO WINDOWS, AND THEY ARE NOT EQUIVALENT

> **A schema and application rollback restores a COMPATIBLE REPRESENTATION OF
> CURRENT AUTHORITY. It does not restore historical access state.**

### Window A — the deployment validation window

**Immediately after deployment, before any new P1-05 access-administration
write.** If one of the five verifications above fails, **ordinary
`php artisan migrate:rollback` is permitted and loses nothing** — because
nothing new has been written.

`down()` reconstructs `users.platform_role` from the **current** role-assignment
state:

| Current assignment state | Restored `users.platform_role` |
| --- | --- |
| A **current** `system_administrator` assignment exists | `system_administrator` |
| **No** current `system_administrator` assignment | **`NULL`** |

**"Current" means `ended_at IS NULL`, and nothing else.** It does not require
the account to be active: an **inactive** user holding a current assignment has
the role reconstructed, while `users.status = inactive` continues to prevent
them signing in. `users.status` is never touched by the rollback.

**`down()` never refuses because the assignments have changed since it ran.**
That refusal would make an emergency rollback impossible at exactly the moment
one is needed.

### Window B — after Roles & Access has been used

> **ORDINARY MIGRATION ROLLBACK IS NOT LOSSLESS, and must never be described as
> though it were.**

The old `users.platform_role` column **cannot represent**:

| Cannot be represented by the old column |
| --- |
| **Multiple roles** held by one person |
| **Domain entitlements** |
| **Scopes** — including several on one entitlement |
| **Sensitivity ceilings** |
| **P1-05 history** — every ended assignment, entitlement, scope and ceiling |

> **A rollback in window B requires the approved backup and recovery procedure,
> and must explicitly account for P1-05 access data. `migrate:rollback` alone
> does not preserve it.**

The migrations still run and still leave a working deployment. What is lost is
everything the old shape has nowhere to put — silently, because a column that
cannot hold a value simply does not hold it.

---

## 4. WHAT P1-05 DID NOT CHANGE ABOUT RECOVERY

**P1-05 replaced the `users.platform_role` predicate with the assignment-based
predicate, and nothing else.**

| Unchanged |
| --- |
| **`BootstrapState` is COMPUTED, never stored** |
| Recovery is **the same UNCONFIGURED predicate returning true again** — not a mode, not a flag |
| It requires an **authorised operator with SSH**, a **fresh auditable grant**, and **full Entra SSO** |
| **No administrator is ever created through MySQL** |

**P1-05 added no recovery mechanism of its own.** If a future change appears to
need one, that is a Product Owner decision and not an implementation detail.

---

## 5. WHAT STEP-UP PROVES, AND WHAT IT DOES NOT

> **SemantIQ can verify that Microsoft reports a fresh authentication event. It
> cannot independently prove which credential or factor Microsoft required,
> unless the tenant's Entra authentication policy provides and guarantees that
> assurance.**

`prompt=login` and `max_age=0` are **requests to the provider**; the provider
decides whether to honour them. What is **verified** on return is the `auth_time`
claim — present, at or after the moment the step-up was requested, and within
the approved tolerance. Any of those failing performs **no privileged action**.

**"MFA verified" is not claimed anywhere**, and must not be added unless an
approved Entra Conditional Access policy genuinely enforces it. That can be
introduced later as an explicit security-policy enhancement without redesigning
P1-05.
