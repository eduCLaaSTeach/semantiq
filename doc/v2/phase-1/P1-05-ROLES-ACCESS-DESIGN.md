# P1-05 — Roles & Access: DESIGN

**DESIGN ONLY.** No schema written, no migration, no engine, no role assignment,
no route change, no screen, no production privilege change. This says exactly
what will be built so it can be argued with before it exists.

| | |
| --- | --- |
| PLAN | `P1-05-ROLES-ACCESS-PLAN.md` — **APPROVED 3 September 2026** |
| PLAN merge SHA | `c313a39652681bc198045fa1c175e2e70964c9be` (PR #93) |
| Decisions binding this design | **D-49 to D-73**, PLAN §31 |
| Decision raised here and **DECIDED** | **D-74** — §7.4, *both scopes delivered, documented as equivalent today* |
| Gates that must close here | **P1-04** (§13.3) · **P1-02** (§14.6) |
| Status | **Under Product Owner review** — **§1, §2, §2.6 and §4 APPROVED 4 Sep 2026** (items 1–4); D-74 **decided** |

---

## 0. The two sentences everything below is built to keep true

> **Role controls ACTIONS. Domain controls BUSINESS AREA. Scope controls
> RECORDS. Sensitivity controls FIELDS.**
>
> **A System Administrator receives no business data automatically.**

**This design is ordered so those survive contact with an implementation.** The
engine is specified before the enforcement, the enforcement before the screens,
and the screens last — because a design that could be built screens-first would
produce something that demonstrates beautifully and enforces nothing.

---

## 1. The immutable role/action catalogue

> **§1 — PRODUCT OWNER APPROVED, 4 September 2026**, subject to the §1.5
> boundary correction recorded below.

**D-51, D-52, D-53, D-54 all answered "no", so nothing about a role is
manageable. There is no `roles` table.**

`App\Modules\Access\Support\RoleCatalogue` — one immutable constant, the same
shape as P1-04's `BaselineDomains`.

### 1.1 The seven roles and their fixed codes

| `role_code` | Display name | Organisation |
| --- | --- | --- |
| `system_administrator` | System Administrator | **NULLABLE — platform-scoped** (§3) |
| `organisation_administrator` | Organisation Administrator | Required |
| `executive` | Executive | Required |
| `domain_owner` | Domain Owner / Director | Required |
| `manager` | Manager | Required |
| `business_user` | Business User | Required |
| `auditor` | Auditor | Required |

**Codes are immutable. Display names are not editable** — D-52. They are
security vocabulary, and *"Super Admin"* must be unreachable.

### 1.2 The five action classes

| Class | Constant | Requires domain + scope + sensitivity |
| --- | --- | --- |
| **Platform administration** | `PLATFORM_ADMIN` | No |
| **Organisation administration** | `ORG_ADMIN` | No |
| **Access administration** | `ACCESS_ADMIN` | No |
| **Evidence / audit read** | `EVIDENCE_READ` | No |
| **Business-data action** | `BUSINESS_DATA` | **YES, ALWAYS** |

**The first four grant no business data at all.** That is not a policy in prose
— it is the structural fact that `BUSINESS_DATA` is the only class the engine
routes into the grant-path evaluation of §6. §13 breaks it.

### 1.3 THE MATRIX — the catalogue itself

| Role | `PLATFORM_ADMIN` | `ORG_ADMIN` | `ACCESS_ADMIN` | `EVIDENCE_READ` | `BUSINESS_DATA` |
| --- | :---: | :---: | :---: | :---: | :---: |
| **System Administrator** | **✓** | ✓ | ✓ | ✓ | **✗ by default** |
| **Organisation Administrator** | **✗** | ✓ | ✓ *(restricted, §2.2)* | ✓ | **✗ by default** |
| **Executive** | ✗ | ✗ | ✗ | ✗ | **✓ through grants only** |
| **Domain Owner / Director** | ✗ | ✗ | ✗ | ✗ | **✓ through grants only** |
| **Manager** | ✗ | ✗ | ✗ | ✗ | **✓ through grants only** |
| **Business User** | ✗ | ✗ | ✗ | ✗ | **✓ through grants only** |
| **Auditor** | ✗ | ✗ | ✗ | **✓** | **✗ without a separate grant** |

### 1.4 What each row means, in the words a reviewer needs

| Role | Design statement |
| --- | --- |
| **System Administrator** | Full platform and system administration. **No business rows by default.** Identity & SSO is theirs alone |
| **Organisation Administrator** | Organisation, users, groups, domains and access administration. **No Identity & SSO. No platform authority. No business rows by default.** And **never** able to grant `system_administrator` — §2.2 |
| **Executive** | **No administration at all.** Business actions only through explicit domain + scope + sensitivity grants |
| **Domain Owner / Director** | A **security/persona role** — *not* the source of domain accountability, which stays in P1-04's `business_domain_owners` (§1.5). It permits `BUSINESS_DATA`, but confers **no automatic entitlement, from ownership or from the role**. Any domain-governance action must be **named explicitly in this catalogue**. Nothing is inferred, in either direction |
| **Manager** | Business actions only, through explicit entitlement and **explicitly assigned** scope — no recursion (§7.2) |
| **Business User** | Business actions only, through explicit entitlement and assigned scope |
| **Auditor** | **Read-only access and security evidence, organisation-wide.** No business rows without a separate business grant |

### 1.5 Domain Owner / Director — a security role, NOT the source of accountability

**Corrected by Product Owner decision, 4 September 2026.** An earlier draft said
the role exists so that accountability can be *recorded in the access model*.
**That was wrong.** It reconnected the two concepts P1-04 deliberately
separated. The corrected statement follows and is binding.

#### 1.5.1 Accountability lives in P1-04, and only there

> **P1-04's `business_domain_owners` remains the SOLE source of domain
> accountability. The P1-05 `domain_owner` role is a security / persona role
> only.**

**Five rules this design must keep true:**

| # | Rule |
| --- | --- |
| 1 | **Owning a domain does NOT automatically assign the `domain_owner` role** |
| 2 | **Holding the `domain_owner` role does NOT make the person owner of any domain** |
| 3 | **Neither relationship may be DERIVED from the other** — not at read time, not at write time, not for display |
| 4 | **Changing P1-04 ownership MUST NOT change P1-05 role assignments** |
| 5 | **Changing P1-05 role assignments MUST NOT change P1-04 ownership history** |

**All four combinations are legitimate. None is an error state:**

| Owns a domain | Holds `domain_owner` | Legitimate |
| :---: | :---: | --- |
| **Yes** | **No** | **Yes** — owns Finance, holds no such role |
| **No** | **Yes** | **Yes** — holds the role, owns no domain |
| **Yes** | **Yes** | **Yes** |
| **No** | **No** | **Yes** |

**No screen, service, engine path or report may treat any of these four as an
inconsistency to be reconciled**, and nothing may offer to "fix" one by writing
the other.

#### 1.5.2 The role is NOT decorative

It participates fully in the Role dimension and **permits the `BUSINESS_DATA`
action class**, exactly as §1.3 shows.

What it does not do is shortcut the other three dimensions. Business-data access
still requires a **complete, valid path**:

> **Role + explicit Domain Entitlement + Scope + Sensitivity** — §6.1.

So the correct statement, used everywhere in this document:

> **Domain Owner / Director provides no automatic domain entitlement and no
> ownership-derived access.**

That is a different claim from *"it does nothing"*. A `domain_owner` with a
complete grant path reaches business data through it like any other business
role. **`✓ through grants only` in §1.3 is correct and stays.**

#### 1.5.3 If governance authority is wanted later

> It is **added to this catalogue by decision**, where a reviewer sees it. It is
> **never** inferred from ownership — and adding it here would still not make
> the role a source of accountability. P1-04 would remain that.

#### 1.5.4 The separation is guarded, not merely stated

**§13 N-B11 to N-B16** break each direction deliberately. They exist because
this is precisely the coupling a well-meaning developer adds as a convenience —
*"they own it, so give them the role"* — and **every functional test would still
pass.**

### 1.6 The catalogue is asserted, not just written

| Guard |
| --- |
| Exactly **seven** codes |
| **No** code, name or action set is mutable at runtime |
| `BUSINESS_DATA` is the **only** class that reaches grant-path evaluation |
| The four administration classes appear in **no** business-data decision |
| **No `roles` table exists** |
| **P1-04 ownership and the `domain_owner` role are structurally independent**, neither derived from the other — §1.5, §13 N-B11 to N-B16 |

---

## 2. D-49 — the bootstrap migration, rollback and deployment sequence

> **§2 — PRODUCT OWNER APPROVED, 4 September 2026**, with the §2.7 rollback
> contract as decided.

**The single most dangerous change in Phase 1 so far**, because getting it wrong
locks the only administrator out of a live deployment, and bootstrap does not
reopen.

### 2.1 What exists today, read from the source

| Where | What it does |
| --- | --- |
| `users.platform_role` | Nullable string, one value |
| `PlatformRole` enum | One case, with a test asserting it stays one |
| `GrantRedeemer::redeem()` **line 63** | **Writes** `'platform_role' => PlatformRole::SystemAdministrator` inside its atomic transaction |
| `BootstrapState` | `User::activeSystemAdministrators()->exists()` |
| `User::isSystemAdministrator()` | `platform_role === SystemAdministrator` |
| `User::scopeActiveSystemAdministrators()` | `where('platform_role', …)->where('status', active)` |
| `RequireSystemAdministrator` | `$user->isSystemAdministrator()` |
| `UserDirectoryService::refuseIfLastAdministrator()` | The same scope, `lockForUpdate()`, inside the write transaction |
| `SystemAdministratorNavigationAuthorizer` | `isSystemAdministrator()` |
| `ConsoleController` | `isSystemAdministrator()` for an Inertia prop |

**Nine call sites. Not one may keep its own answer.**

### 2.2 The single authority that replaces them

`App\Modules\Access\AccessEngine` — §5 — with one narrow question used by all
nine:

```
holdsRole(User $user, RoleCode $role, ?Organisation $in = null): bool
```

**`RequireSystemAdministrator` becomes a call to that**, and there is exactly
one implementation of *"is this person a System Administrator"* in the codebase.
§13 asserts it by searching for a second.

### 2.3 The migration sequence — five migrations, in this order

| # | Migration | Why here |
| --- | --- | --- |
| 1 | `create_role_assignments_table` | Nothing can migrate into a table that does not exist |
| 2 | `create_domain_entitlements_table` | Child of 1 |
| 3 | `create_entitlement_scopes_table` · `create_entitlement_ceilings_table` | Children of 2 |
| 4 | **`migrate_platform_role_to_assignments`** | **Data.** Every `users.platform_role = 'system_administrator'` becomes an active assignment with `organisation_id = users.organisation_id` (which may be NULL) |
| 5 | **`drop_platform_role_from_users`** | **Only after 4 has run.** Removes the column, and `PlatformRole` and its one-case test go with it |

**Migration 4 is data and it is reversible.** Its `down()` reconstructs the
column from the **current** assignment state — never from a remembered original
value — so a rollback of 5 then 4 leaves the administrator an administrator.
Asserted on **MySQL** in §13. **§2.7 is the full rollback contract, and it is
binding: reversible does not mean lossless.**

### 2.4 The deployment sequence, and the window it must not have

**The code and the schema change together, and the deploy already stages them
that way**: maintenance window → sync → migrate → cache clear → health → window
closes. So there is **no moment at which new code reads a dropped column** or
old code reads a missing one.

**What must be proven before that deploy, not after:**

| # | |
| --- | --- |
| 1 | `platform_roles_total: 1` in production — **already confirmed**, `P1-04-BUSINESS-DOMAINS-VERIFICATION.md` §9.5 |
| 2 | Migration 4 produces **exactly one** assignment from that one row |
| 3 | The administrator can still sign in and reach `/console` — verified after the deploy, before anything else is done |
| 4 | **A read-only verification exists that reports assignment counts**, so this is observed rather than assumed |

### 2.5 Bootstrap — replacing the exact seam

**`GrantRedeemer::redeem()` line 63 is deleted and replaced by the creation of a
role assignment INSIDE THE SAME `DB::transaction`:**

```
$user = User::query()->create([ …, /* no platform_role */ ]);

RoleAssignment::create([
    'user_id'         => $user->id,
    'role_code'       => RoleCode::SystemAdministrator,
    'organisation_id' => null,          // PLATFORM-SCOPED — §3
    'assigned_at'     => now(),
    'ended_at'        => null,
]);

// the existing single-row grant consumption, unchanged
```

**Why inside the same transaction, and not one line later.** The existing
`consumed !== 1` guard rolls the whole transaction back when another request
wins the race. If the assignment were created afterwards, a lost race would roll
back the user and leave an assignment pointing at nothing — or, worse, a won
race that failed on the assignment would leave **a deployment with a user who is
nobody and a bootstrap grant already consumed.** Unrecoverable.

**`organisation_id` is NULL here, and that is the whole of §3.** The Company
Profile does not exist yet.

### 2.6 The last-administrator guard after the column disappears

> **§2.6 — PRODUCT OWNER APPROVED, 4 September 2026**, with the two mandatory
> corrections below applied. **Two earlier statements of mine were wrong and are
> corrected in place**: the lock order (§2.6.3) and the claim that only a fresh
> deployment can hold zero administrators (§2.6.6).

#### 2.6.1 The invariant — corrected

**The earlier wording said zero administrators is reachable only on a genuinely
empty deployment and only initial bootstrap resolves it. That is incorrect and
contradicts P1-00, which already approved a controlled recovery path.**

> **NORMAL APPLICATION OPERATIONS MAY NEVER TRANSITION THE EFFECTIVE ACTIVE
> SYSTEM ADMINISTRATOR COUNT FROM ≥ 1 TO 0.**

**A zero state may legitimately exist:**

| # | Legitimate zero state |
| --- | --- |
| 1 | A **genuinely fresh deployment**, before first bootstrap |
| 2 | An **exceptional, approved P1-00 recovery condition** in which there are zero active System Administrators |

**In either zero state, ONLY the existing controlled P1-00 bootstrap/recovery
mechanism may establish a new System Administrator.**

**Effective** means: a `system_administrator` assignment with `ended_at IS NULL`
**held by a user with `status = active`.** Both filters. This is the lockout
question — *"can anybody actually administer this deployment?"* — and it is
**not** the §2.7.2 rollback filter, which asks a different question and
deliberately ignores `users.status`.

#### 2.6.2 The floor is 1, and a count of 1 warns rather than blocks — D-49a

**Product Owner decision, 4 September 2026: the enforced floor stays at 1.**
Release 1 does **not** require a minimum of two System Administrators. A second
genuine administrator is **recommended operationally**, but it is not a
prerequisite for the product to function.

**When the effective count is exactly 1, a visible, non-blocking warning is
surfaced:**

> **"Only one active System Administrator remains. Add another trusted
> administrator to reduce account-lockout risk."**

| It is informational ONLY |
| --- |
| **No administrator is created automatically** |
| **Normal administration is NOT blocked** merely because only one exists |
| **The only refusal is an operation that would take the effective count from 1 → 0** |

#### 2.6.3 MANDATORY CORRECTION — a COMMON serialisation boundary

**The lock order I proposed — *subject user → subject assignments → count
others* — is NOT approved and is replaced.** It is subject-scoped, and in the
two-administrator race transaction A locks user A while transaction B locks user
B, each then reaching for the other's rows. That is **not one common
serialisation boundary**: it is a deadlock, resolved by MySQL picking a victim.

> **A deadlock exception is NOT a security mechanism. The invariant protects the
> whole SET of active System Administrators, so the whole set is what must be
> locked.**

**Every operation capable of reducing the effective count** — there are exactly
two:

| Operation |
| --- |
| **System Administrator assignment revocation** |
| **Deactivation of a user holding a current System Administrator assignment** |

**— performs these six steps inside ONE transaction:**

| # | Step |
| --- | --- |
| 1 | **Lock the current `system_administrator` role-assignment set** in a deterministic order |
| 2 | **Lock the corresponding owning `users` rows** in the same deterministic order |
| 3 | **Re-evaluate** which of those assignments are current *and* held by active users |
| 4 | Determine whether the requested operation **would leave zero** |
| 5 | **Refuse, or perform the write** |
| 6 | **Commit** |

**One consistent order everywhere — assignment id, then user id, both
ascending.** The order is a property of the guard, not of the caller, so the two
competing operations **contend on the same locked administrator set** rather
than on two different subject rows.

**Refusal:** *"This is the only active System Administrator. Add or retain
another before removing this one."* — the existing sentence, extended to cover
revocation.

#### 2.6.4 The concurrency evidence required — MySQL only

**Recorded as a delivery requirement, not a test suggestion.** Three races, each
proven against **MySQL**:

| # | Race | Must show |
| --- | --- | --- |
| **C-A** | A **deactivation** racing B **revocation** | Cannot reach zero |
| **C-B** | A **revocation** racing B **revocation** | Cannot reach zero |
| **C-C** | A **deactivation** racing B **deactivation** | Cannot reach zero |

**And in every one of the three:**

| |
| --- |
| **One request completes** |
| **The other re-evaluates AFTER the first commit** and receives the **normal business refusal** |
| **No raw deadlock, integrity or database error reaches the administrator** |

**SQLite is kept out of this evidence entirely.** It has no `SELECT … FOR
UPDATE` and would report a lock that is not there — the P1-04 C1 lesson, where a
measurement gave the right answer for the wrong reason.

#### 2.6.5 What P1-05 changes about bootstrap and recovery — nothing but the predicate

> **P1-05 ONLY replaces the old `platform_role` predicate with the new
> assignment-based predicate.**

`BootstrapState` is computed, never stored — `User::activeSystemAdministrators()`
today. It continues to be computed, from the same question, against role
assignments instead of a column.

| Explicitly NOT done by P1-05 |
| --- |
| **No new P1-05 recovery mechanism** |
| **No manual database editing**, ever |
| **No weakening of SSH + fresh auditable grant + full Entra SSO** |
| No special mode, no flag — recovery remains **the same UNCONFIGURED predicate returning true again** |

#### 2.6.6 Why the correction matters

My earlier statement would have made a **correct, already-approved recovery path
look like a defect** to the next reader, and the plausible "fix" — a P1-05
recovery route of its own — is precisely the thing that must never exist. The
wrong statement is left visible above rather than deleted.

### 2.7 Rollback semantics — decided by the Product Owner, 4 September 2026

**Approved: rollback restores the CURRENT authoritative System Administrator
truth from role assignments. It is not a time machine.**

Migration 4's `down()` **must not refuse** merely because assignments have
changed since it ran. Such a refusal would make an emergency application
rollback impossible **at exactly the moment rollback is needed.**

#### 2.7.1 What `down()` reconstructs

When migration 5 is rolled back and `users.platform_role` is restored, migration
4's `down()` reconstructs the old seam **from the current role-assignment
state**:

| Current assignment state | Restored `users.platform_role` |
| --- | --- |
| A **current** `system_administrator` assignment exists | `system_administrator` |
| **No** current `system_administrator` assignment | **`NULL`** |

| Rule |
| --- |
| **`users.status` is untouched.** Active/inactive remains entirely independent |
| **Historical, ended assignments are NOT restored as current roles** |
| **The original pre-migration value is NOT consulted**, and must not be stored anywhere for that purpose |

This is D-49 carried through: **once migrated, assignments are the single source
of truth**, including when unwinding.

#### 2.7.2 "Current" means `ended_at IS NULL` — and nothing else

**Do not collapse assignment state and user status.** They answer different
questions and this design keeps them apart:

> **A current role assignment is `ended_at IS NULL`. It does NOT require the
> user account to be active.**

P1-03 deactivation **preserves** role and access relationships (D-36 — a
deactivated person's access must return exactly on reactivation). Therefore:

> **An INACTIVE user holding a still-current System Administrator assignment
> HAS `platform_role = system_administrator` reconstructed on rollback** — while
> `users.status = inactive` continues to prevent them signing in.

**This is deliberately different from the lockout invariant in §2.6**, which
counts assignments **held by active users**, because that guard asks *"can
anybody actually administer this deployment?"* Two questions, two filters,
neither borrowed from the other. §13 **N-M13** breaks the borrow in each
direction.

#### 2.7.3 Deployment rollback is NOT data time travel

**Stated explicitly here and in the deployment note, because the difference is
the difference between a safe rollback and silent data loss:**

> **A schema/application rollback restores a COMPATIBLE REPRESENTATION OF
> CURRENT AUTHORITY. It does not restore historical access state.**

**Two rollback windows, and they are not equivalent.**

**A — the deployment validation window.** Immediately after deployment, **before
any new P1-05 access-administration write is allowed**, five things are verified:

| # | Verification |
| --- | --- |
| 1 | The migrated System Administrator assignment exists — exactly one, from one column row |
| 2 | Sign-in works |
| 3 | `/console` is reachable |
| 4 | The last-administrator guard refuses |
| 5 | Schema and read-only counts report as expected |

**If one of these fails, ordinary `migrate:rollback` is permitted**, and it loses
nothing — because nothing new has been written.

**B — after P1-05 access administration has been used.** **Ordinary migration
rollback is NOT lossless and must never be described as though it were.** The
old `users.platform_role` column **cannot represent**:

| Cannot be represented by the old column |
| --- |
| **Multiple roles** |
| **Domain entitlements** |
| **Scopes** |
| **Sensitivity ceilings** |
| **P1-05 history** — every ended assignment, entitlement, scope and ceiling |

> **A rollback in window B requires the approved backup/recovery procedure and
> must explicitly account for P1-05 access data. `migrate:rollback` alone does
> not preserve it.**

#### 2.7.4 The limitation is guarded, not merely promised

**§13 N-M14** asserts that this limitation is **stated in the deployment
documentation**, so the project cannot quietly come to promise reversible
business data. The mutation is *deleting the statement* — and it must fail.
---

## 3. Platform-scoped versus organisation-scoped assignments

| Role | `organisation_id` |
| --- | --- |
| **`system_administrator`** | **NULL permitted** — platform-scoped |
| Every other role | **NOT NULL. Refused without one** |

### 3.1 Why it cannot be uniform

**`GrantRedeemer` creates the first user on a deployment with no organisation at
all.** A model requiring an organisation on every assignment makes the first
administrator impossible to create — and the failure appears **only on a
genuinely empty deployment**, which no existing test reaches by accident.

### 3.2 How it is enforced

A database `CHECK`-equivalent cannot express *"nullable for one value of another
column"* portably, so it is a **service rule with two assertions**, both
directions:

| Assertion |
| --- |
| A `system_administrator` assignment **is accepted** with `organisation_id = NULL` |
| Every other role **is refused** with `organisation_id = NULL`, in a business sentence |

### 3.3 What a platform-scoped assignment means at decision time

| | |
| --- | --- |
| For `PLATFORM_ADMIN`, `ORG_ADMIN`, `ACCESS_ADMIN`, `EVIDENCE_READ` | The organisation is **not part of the question**. There is one organisation in Release 1, and platform administration is not scoped by it |
| For `BUSINESS_DATA` | **It contributes nothing.** A platform-scoped assignment can never satisfy a business-data path, because §6 requires an entitlement and an entitlement requires an organisation |

**That is the §12 boundary falling out of the data model rather than being
policed by a rule** — which is the strongest form it can take.

---

## 4. The historical parent–child grant model

> **§4 — PRODUCT OWNER APPROVED, 4 September 2026**, with §4.5 as decided.

```
role_assignment            (user, role_code, organisation?, assigned_at, ended_at)
   └── domain_entitlement  (role_assignment_id, domain_id, granted_at, ended_at)
          ├── entitlement_scope    (domain_entitlement_id, scope, target?, …)
          └── entitlement_ceiling  (domain_entitlement_id, sensitivity, …)
```

### 4.1 The rule

> **Ending a parent ends its active children, in the same transaction.**
> **Re-granting a parent creates NEW children. It never revives old ones.**

### 4.2 The bug this exists to prevent

> A Manager role is revoked. The Finance entitlement, its scope and its ceiling
> are left as orphan rows. Months later the same person is made a Manager again
> for an unrelated reason — **and Finance returns**, with a scope somebody set
> in a different context, granted by nobody, appearing in no change record.

**Orphan rows are the mechanism.** Parentage removes it: the child's period ends
with the parent's, and a new parent has no children until somebody grants them.

### 4.3 This is NOT how external state gates behave

| | Parentage (§4.1) | External state gates |
| --- | --- | --- |
| Trigger | Revoking a role assignment or an entitlement | User deactivated/reactivated · **domain disabled/re-enabled** |
| The grant | **Ended** | **Preserved, untouched** |
| Return | Requires a **new deliberate grant** | Returns **exactly** when the state is valid again |
| Because | Somebody **decided** | Somebody changed a **state** |

**Deactivating a user is not a revocation. Disabling a domain is not a
revocation.** P1-03's D-36 and P1-04's D-42 both say so, and §13 breaks both
directions — ending grants on a state change, and failing to end them on a
revocation.

### 4.4 Every write is one transaction

| Operation | Inside one transaction |
| --- | --- |
| Revoke a role assignment | End the assignment **and** every active entitlement, scope and ceiling beneath it |
| Revoke an entitlement | End it **and** its active scope and ceiling |
| Replace an assignment | End the current, create the next — **never revoke-then-assign as two requests** |

**The lock order is the P1-04 order, applied to this shape: the SUBJECT USER row
first, then assignments, then children, then any dependency check.** One order
in every service, so two services cannot deadlock approaching the same tables
from opposite ends.

### 4.5 A revoked child does NOT revoke its parent — decided 4 September 2026

**Product Owner decision: revoking the last current scope does NOT end the
Domain Entitlement.** The entitlement remains current and becomes an
**incomplete, non-authorising grant path** until a new scope is explicitly
assigned.

> **Removing a child must never silently mean that its parent was revoked.**

**The four levels mean four different things, and that is why:**

| Level | Question it answers |
| --- | --- |
| **Role Assignment** | What business/security **role** the person holds |
| **Domain Entitlement** | Which business **domain** they are entitled to participate in |
| **Scope** | Which **records** inside that domain they may access |
| **Sensitivity Ceiling** | Which **fields and actions** that path may expose |

#### 4.5.1 Exact behaviour when the last scope is revoked

| # | |
| --- | --- |
| 1 | The **Domain Entitlement remains current** |
| 2 | The old scope period gets **`ended_at`** |
| 3 | **No old scope is revived** |
| 4 | **Effective business access through that entitlement becomes ZERO immediately** |
| 5 | The engine returns **`denied_scope`** |
| 6 | Assigning a new scope creates a **new scope period** |
| 7 | **The entitlement itself does not need to be re-granted** |

**This is secure because §6 already requires a COMPLETE grant path.** A missing
scope cannot authorise anything — §7.6, N-D8. The entitlement being current is
not the same claim as the entitlement being effective.

#### 4.5.2 The screen must not imply usable access

**An entitlement in this state is NOT shown as though it grants access.**

| | |
| --- | --- |
| **Status** | **No access — scope required** |
| **Supporting text** | *"This domain entitlement has no active scope and currently grants no business-data access."* |

| Requirement |
| --- |
| **The Access Simulator shows the SAME reason from the REAL engine** — §11. It does not reproduce this logic in the UI |
| **Search, filter and status must let an administrator FIND** *Incomplete / No scope* entitlements |

This also gives **P1-07** a clean future review condition, rather than hiding a
technically current but ineffective assignment.

#### 4.5.3 Ceilings follow the same parent/child principle — with the Release 1 rule intact

**Ending a ceiling does not end the entitlement either.** But the sensitivity
rule already approved for Release 1 is preserved and is **not** the same shape as
scope:

> **"Clear ceiling" means deliberately resetting to `standard` — by creating or
> maintaining a CURRENT `standard` ceiling row.** It must **never** produce an
> invisible permissive default.

> **If a malformed state somehow exists with NO current ceiling, the engine
> FAILS CLOSED.** It never assumes `standard`, `confidential` or `restricted`.

**"Missing ceiling" is never silently turned into a default grant.** Clearing is
a deliberate write that leaves a visible row; absence is a fault, and a fault
denies. §8.3, and **§13 N-C7 / N-C8**.

#### 4.5.4 Guards

**§13 N-C1 to N-C10.** Each breaks the shortcut a developer takes when an
incomplete entitlement looks like a bug to be tidied away.
---

## 5. One effective-access engine — the contract

`App\Modules\Access\AccessEngine`. **One implementation. Every enforcement point
and the simulator call it.**

### 5.1 It answers a CLOSED question

```
decide(AccessQuestion $question): AccessDecision
```

| | |
| --- | --- |
| **`AccessQuestion`** | identity · action · action class · domain (where applicable) · resource reference · sensitivity of the field/action · organisation |
| **`AccessDecision`** | `allowed: bool` · `reason: DecisionReason` · `path: GrantPathReference|null` |

**Never `whatCanThisPersonSee()`.** An open-ended query is an invitation to
build a permissive default and then filter it, which is the shape §11 forbids.
Screens derive their lists from repeated closed questions or from an explicit,
separately reviewed projection.

### 5.2 It is usable outside an HTTP request — D-70

**No dependency on the session, the request, or middleware.** The identity is a
parameter. Phase 2's propagation is not a web request, and an engine reachable
only through middleware would force Phase 2 to build its own — which is the
second model the whole unit exists to prevent.

### 5.3 The decision reason is part of the contract

**Not a debugging aid.** The simulator's *"why"* (§11), the privileged denial
log (§12) and P1-07's review all read it.

| Reason | Meaning |
| --- | --- |
| `allowed_by_path` | A complete active path authorised it — `path` names which |
| `denied_unauthenticated` · `denied_inactive_user` · `denied_organisation_mismatch` · `denied_domain_disabled` | A **global gate** (§6.4) |
| `denied_no_role` · `denied_no_entitlement` · `denied_scope` · `denied_ceiling` | **Which link** was missing in every candidate path |
| `denied_unknown_state` | Something stored is unrecognised — **and this is a security event** |
| `denied_engine_failure` | The engine could not decide. **Deny** |

### 5.4 Failure is a denial

Every path through `decide()` returns a decision. **An exception, a timeout or
an unreachable dependency produces `denied_engine_failure`** — never an error
that some later change turns into a pass. §13 N-D2.

---

## 6. Independent grant-path resolution — D-62

### 6.1 A grant path

```
role assignment (active)
   └── domain entitlement (active, and the domain ENABLED)
          ├── scope    covers this record?
          └── ceiling  covers this field/action's sensitivity?
```

**A path is evaluated whole. It authorises or it does not. It never contributes
half an answer to another path.**

### 6.2 The decision

> **Allowed when AT LEAST ONE complete, active grant path authorises the
> request.**

| # | Consequence |
| --- | --- |
| 1 | **Role actions union.** Manager + Business User permits both action sets |
| 2 | **Several entitlements widen reachable records** through their own scopes, independently |
| 3 | **A restrictive second grant never reduces** what another valid path gave |
| 4 | **A ceiling caps its own path only.** Not a global maximum |
| 5 | **Revoked and historical rows are not evaluated at all** |
| 6 | **A revoked grant is NOT a deny** against a separate active path |

### 6.3 Consequence 6 is the one to hold on to

**D-64 rejects explicit deny records.** If a revoked row could subtract from a
live grant it would **become** one — invisible, created by an ordinary
revocation, chosen by nobody and shown on no screen.

> **A revocation ends a path. It does not create a rule.**

§13 N-P3 breaks exactly that.

### 6.4 The global gates sit OUTSIDE every path

**Checked first. No path can satisfy them and no path can outvote them.**

| Gate | Reason |
| --- | --- |
| Unauthenticated | `denied_unauthenticated` |
| Inactive user | `denied_inactive_user` |
| Organisation mismatch | `denied_organisation_mismatch` |
| **Disabled domain** | `denied_domain_disabled` — §13.3 |
| Unknown / malformed / conflicting state | `denied_unknown_state`, **and logged** |

### 6.5 Evaluation order, and why it is a narrowing

1. Global gates. 2. Candidate paths — active assignments with the required
action class. 3. For each, its active entitlements for this domain. 4. Domain
enabled? 5. Scope covers the record? 6. Ceiling covers the sensitivity?

**Every step narrows. No step may widen what an earlier one allowed.** §13 N-D
breaks it.

---

## 7. Exact scope semantics

**Scope belongs to an entitlement period — D-59, §4.**

### 7.1 The structural target each scope carries

| Scope | Target column | Resolves to |
| --- | --- | --- |
| **`own`** | **none** | Records whose subject/assigned user is this identity — the §8 contract |
| **`team`** | **`team_id`, NOT NULL** | Records of **that one team**. More teams means more scope rows |
| **`business_unit`** | **`business_unit_id`, NOT NULL** | Records of that business unit, through P1-01's `business_units → departments → teams` |
| **`domain`** | **none** | Every record in the entitled domain |
| **`organisation`** | **none** | Every record in the entitled domain — **the same set as `domain` today**, D-74/§7.4 |

**A target is required exactly where the table says so, and refused where it
does not apply** — a `team_id` on an `own` scope is a stored contradiction, and
§13 N-S breaks both directions.

### 7.2 Manager scope — NO INFERENCE, NO RECURSION — D-66

> **SYS-005: managers inherit visibility only for explicitly assigned teams and
> hierarchies.**

**Two things this design will not do:**

| Forbidden | What would happen |
| --- | --- |
| Deriving a team **because a direct report belongs to it** | The manager gets a team **nobody assigned**, appearing in no grant and no review |
| **Reports-of-reports** — walking the chain | A senior manager's scope **grows every time somebody is hired three levels below them**, with no assignment change and no review |

**`management_relationships` and `team_memberships` are READ, never walked
recursively**, and only to answer *"is this record within the team named on this
scope?"* — never *"which teams should this manager have?"*

**Deeper reach is available and deliberate: assign the team.** It then appears
in the entitlement, in the simulator and in P1-07.

### 7.3 Business Unit scope

`business_unit_id` → its departments → their teams → their memberships. **Read
downward from the named unit only.** Never inferred from where the person
happens to sit.

### 7.4 Domain and Organisation — D-74, **DECIDED: both, documented as equivalent**

**Product Owner decision, 4 September 2026 — option (b).** Both scopes are
delivered. **Neither is dropped.** The enum keeps all five values:
`own`, `team`, `business_unit`, `domain`, `organisation`.

**The fact this decision was taken about, stated plainly:**

> Under Release 1's single organisation, with the entitlement already naming a
> domain, **`domain` and `organisation` scope resolve to the SAME record set.**

There is no partition above business unit for them to differ across, and there
is one organisation. That is true **today**, and this design does not pretend
otherwise.

**Why both are kept — recorded, not left to be re-derived:**

| | |
| --- | --- |
| **`organisation` is the honest name for what is granted today** | Everything in the entitled domain, across every business unit. A person granting access reads *organisation* and gets what they expect |
| **`domain` is reserved for the partition that does not exist yet** | When a domain is later partitioned — a second organisation, a tenant, a legal entity — `domain` becomes **narrower** than `organisation`. Removing it now would mean a migration of live grants to reintroduce it |
| **Dropping either one would have to be reversed** | Both directions of option (a) and (c) delete a word from a security vocabulary that P1-07 will read back. Deleting is cheap; re-adding it to grants already made is not |

**The obligation that comes with keeping both.** Two identical choices on a
screen, unexplained, is a trap: the next person to grant access assumes one of
them must be narrower, and picks the wrong one on purpose.

> **The scope control MUST state, on the screen, that Domain and Organisation
> grant the same records today, and that Domain is reserved for a future
> partition.**

This is a **delivery requirement of this unit, not a note in this document.**
It is guarded — **§13 N-Q2** asserts the statement against the rendered screen
source, and removing the sentence must fail the test. `ScreenSource::rendered()`
strips comments, so a docblock cannot satisfy it.

**What this decision does NOT do:**

- It does **not** make `organisation` reach outside the entitled domain. §7.5
  still holds, and **§13 N-P5** still breaks it.
- It does **not** give the two scopes two code paths. They resolve through
  **one** resolution, and **§13 N-Q1** breaks a second one — because two paths
  that are equal today drift silently the day a partition arrives.
- It does **not** create the future partition, or any column for it.

### 7.5 Scope never widens the domain

*Organisation* scope on a Sales entitlement means **all Sales records** — not all
records. §13 N-P5.

### 7.6 A missing scope grants NOTHING

**No default, ever.** An entitlement whose scope has been revoked authorises
nothing until a new one is assigned. §13 N-D8 — the same class of defect as the
P1-04 disabled-domain gate.

---

## 8. Per-entitlement sensitivity — D-60, D-63

### 8.1 One ceiling, on the entitlement

| Level | Default |
| --- | --- |
| `standard` | **Yes** |
| `confidential` | |
| `restricted` | **Requires step-up to grant** — §9 |

**No person-level ceiling exists** — D-60. Two independent cap paths would leave
an entitlement raised to *Confidential* still capped by an invisible person-level
*Standard*, on a screen that could not explain why.

### 8.2 Above the ceiling → DENY — D-63

> **The engine returns `denied_ceiling`. It does not redact.**

| Layer | Responsibility |
| --- | --- |
| **The engine** | Allow or deny, per field and per action |
| **Phase 2 consumers** | May **omit** a denied field so a report stays usable — **and must indicate content was withheld** |

**NO SILENT REDACTION.** A report that quietly drops a column is one whose
reader believes they are seeing everything. **P1-05 builds no redaction
engine**, because there is no business data to redact and designing one now
would be designing against an imagined shape.

### 8.3 Clearing is a deliberate write, and absence FAILS CLOSED

**Two different things, kept apart — §4.5.3:**

| State | Meaning | Engine |
| --- | --- | --- |
| **Cleared** | A **current `standard` ceiling row exists**, written deliberately | Caps at `standard` |
| **Absent** | **No current ceiling row at all** — a malformed state | **DENY.** `denied_ceiling_missing` |

**Clearing never produces an invisible permissive default**, and **absence is
never silently read as `standard`**. It returns to `standard` by writing
`standard`, not by deleting a row. §13 N-D8b, **N-C7, N-C8**.

---

## 9. Step-up re-authentication — D-73

### 9.1 The five actions

| Action |
| --- |
| Granting `system_administrator` |
| Revoking `system_administrator` |
| Granting `organisation_administrator` |
| **Self-granting** any role or entitlement — §10.4 |
| Granting a **`restricted`** ceiling |

### 9.2 The mechanism — Microsoft, not a dialog

**Reuse P1-00's provider.** The authorization request for a step-up carries
**`prompt=login`** (and, where the provider honours it, **`max_age=0`**), so the
identity provider — **not SemantIQ** — performs the re-authentication.

> **This is a BOUNDED EXTENSION TO P1-00 and this design says so explicitly
> rather than adding it quietly.** §9.6 states exactly what changes.

### 9.3 The return-to-action binding

**The action must survive the round trip without becoming forgeable.**

| # | Rule |
| --- | --- |
| 1 | The **intended action, its target and its parameters are stored server-side**, keyed by a single-use opaque reference |
| 2 | **Nothing about the action travels in the URL.** A URL somebody can edit is an action somebody can substitute |
| 3 | The reference is bound to **the current session and the current user** |
| 4 | It **expires in minutes**, not hours |
| 5 | On return, the stored action is executed **only if** the reference matches, the session matches, the user matches and the freshness proof passes |

### 9.4 The freshness proof comes from the PROVIDER

| # | Rule |
| --- | --- |
| 1 | Freshness is read from the **provider's response** — the `auth_time` claim — never from a flag SemantIQ set |
| 2 | `auth_time` must be **after the step-up was requested**, within a bounded number of seconds |
| 3 | **An application-set "recently signed in" flag is forbidden.** The application would be asserting freshness rather than proving it |

### 9.5 Anti-replay

> **One completed step-up authorises ONE action, ONCE.**

| # | Rule |
| --- | --- |
| 1 | The reference is **consumed** in the same transaction that performs the action — the single-row `UPDATE … WHERE consumed_at IS NULL` guard `GrantRedeemer` already proves |
| 2 | **No time window in which everything is privileged.** A second privileged action needs a second step-up |
| 3 | A replayed reference is refused **and logged** |

### 9.6 What changes in P1-00, exactly

**Named, bounded and reviewable — not a quiet addition:**

| # | Change |
| --- | --- |
| 1 | The authorization-request builder accepts an optional `prompt` / `max_age` |
| 2 | **A distinct callback route** for step-up returns, so an ordinary sign-in can never be mistaken for one |
| 3 | **A distinct state store** for step-up, so a step-up state cannot satisfy a sign-in and the reverse |
| 4 | The callback reads and verifies `auth_time` |

**Unchanged: issuer and tenant validation, state and nonce anti-forgery, the
required-claims check, the session policy (D-10/D-31) and every refusal state.**
**Step-up may only ADD a check. It may never relax one**, and §13 N-S4 asserts
that a step-up return still passes every P1-00 validation.

---

## 10. Existing-route authorization migration — enumerated, not swapped

**73 console routes exist today, all behind `RequireSystemAdministrator`.**

> **A find-and-replace across the route file would hand Identity & SSO to
> organisation administration in a commit that reviewed as trivial.**

### 10.1 The enumeration

| Area | Routes | Class required | Organisation Administrator |
| --- | --- | --- | --- |
| `console/identity/*` | **7** | **`PLATFORM_ADMIN`** | **NO — System Administrator only** |
| `console/organisation/*` | 38 | `ORG_ADMIN` | **Yes** |
| `console/people/*` | 18 | `ORG_ADMIN` | **Yes** |
| `console/domains/*` | 9 | `ORG_ADMIN` | **Yes** |
| `console/access/*` (new, §12) | — | `ACCESS_ADMIN` | **Yes, except §10.3** |
| `console` (root) | 1 | any authenticated console role | Yes |

### 10.2 The seven Identity routes, by name

`GET console/identity` · `GET console/identity/providers` ·
`GET console/identity/session-policy` · `GET console/identity/login-experience` ·
`GET console/identity/health` · `POST console/identity/entra/reveal` ·
`POST console/identity/health/re-check`

**All seven stay `PLATFORM_ADMIN`.** They expose provider configuration, a
secret-presence reveal and a provider-wide live probe. §13 N-E9 asserts it.

### 10.3 The one exception inside Roles & Access

**Granting or revoking `system_administrator` requires `PLATFORM_ADMIN`**, not
`ACCESS_ADMIN`.

> **Without this, organisation administration is platform administration one
> assignment away** — and the escalation would look, on any screen, like an
> ordinary grant. §13 N-B9.

### 10.4 Self-assignment — D-65

Permitted, with **all three**: explicit confirmation naming it as a self-grant;
a **distinguishable** privileged security event; and **step-up** (§9).

> **P1-07 does not supply four-eyes approval.** It supplies later review and
> recertification. Nothing here may depend on it as an approval control.

### 10.5 How the gate changes

`RequireSystemAdministrator` is **replaced** by a middleware taking an action
class — `RequireAccess::class.':platform_admin'` — which calls the engine.
**`RequireOrganisation` is unchanged.**

**Every route names its class explicitly. There is no default**, so a route
added later without one **fails to boot**, rather than defaulting to something.

---

## 11. The simulator calls the same engine

### 11.1 The rule

> **`AccessSimulator` holds no authorization logic. It builds `AccessQuestion`s,
> calls `AccessEngine::decide()`, and renders the `AccessDecision`.**

A simulator with its own logic gives confident answers that are wrong exactly
when the two implementations have drifted — which is exactly when somebody most
needs the truth. §13 N-B7.

### 11.2 What it shows

| | |
| --- | --- |
| **Current** | Decisions against the stored state |
| **Proposed** | The same questions **inside a transaction that applies the change and is then ROLLED BACK** — the real engine against the real proposed state, with nothing persisted |
| **Why** | For an allow, **which grant path**. For a deny, **which link was missing in every candidate path**, from `DecisionReason` |

**§6's independent paths are what make "why" answerable in one sentence.**

### 11.3 Boundaries

| | |
| --- | --- |
| **Writes nothing** | The proposed-state transaction always rolls back. **No GET mutates** — the P1-04 rule |
| **Cannot exceed the caller** | An administrator may simulate a person in **their own organisation** only |
| **Shows no business data** | It shows *what would be visible*, never values |
| **Not an approval workflow** | Publishing is the ordinary path |

---

## 12. The Phase 2 projection contract — D-70

### 12.1 What it is

An interface **defined now, implemented in Phase 2**:

```
project(User $user): EffectiveAccessProjection
```

carrying, per entitled and **enabled** domain: the domain code, the scope with
its resolved target set, and the ceiling.

### 12.2 The rules that make it a contract

| # | Rule |
| --- | --- |
| 1 | **It is DERIVED from the engine.** It restates nothing and re-implements nothing |
| 2 | **The stable identity key** is P1-00's `(provider, external_subject, tenant_id)` — **never email** |
| 3 | **It is a projection of the same decisions**, so a rule the engine cannot express is **a change here**, not a second model in Fabric |
| 4 | **Callable outside HTTP** — §5.2 |
| 5 | **Phase 2 implements the contract; it does not reinterpret it** — D-67 |

### 12.3 The architecture this forbids

| Forbidden | Why |
| --- | --- |
| A model or agent receiving data the user may not see, then being asked not to reveal it | **The data has already left the boundary. Prompting is not authorization** |
| A service account reading everything on behalf of users | The unrestricted load, renamed |
| Filtering AI output after generation | The generation already saw it |
| A second permission model configured inside Fabric or Power BI | Two models — the blueprint forbids exactly this |

### 12.4 The "Own" contract — D-67

> **A record is `own` when its subject or assigned user is this identity.**

**P1-05 defines it; Phase 2 supplies the per-data-product mapping and must
implement this contract rather than reinterpret it.** Leaving it undefined would
let Phase 2 invent it — which is a second implementation by another name.

---

## 13. Mutation, concurrency and lockout tests

Every guard broken deliberately and observed to fail — `CLAUDE.md` §2. Each
mutation is **the one a person who misunderstood the rule would plausibly
write**.

### The boundary

| # | Guard | Mutation |
| --- | --- | --- |
| N-B1 | System Administrator gets **no** business data from the role | `if (holdsRole(SystemAdministrator)) return allow` in the engine |
| N-B2 | Domain **ownership** grants nothing | Auto-create an entitlement on owner assignment |
| N-B3 | The **Domain Owner role** grants nothing beyond `business_user` | Give it a business-data class of its own |
| N-B4 | **The engine never reads `group_memberships`** | Read it |
| N-B5 | Business-unit membership grants nothing on its own | Widen scope from the unit alone |
| N-B6 | **No manager inference, no recursion** | Derive a team from a direct report; walk the chain |
| N-B7 | **ONE engine** | Give the simulator its own copy |
| N-B8 | **ONE definition** of "is a System Administrator" | Add a second helper |
| **N-B9** | **Organisation Administrator can NEVER grant `system_administrator`** | Permit it |
| N-B10 | The four administration classes reach **no** business-data decision | Let `ORG_ADMIN` satisfy a business path |
| **N-B11** | Assigning a **P1-04 domain owner** creates **no** P1-05 role assignment | Assign `domain_owner` on ownership — *"they own it, so give them the role"* |
| **N-B12** | Assigning the **`domain_owner` role** creates or changes **no** `business_domain_owners` row | Write an ownership row on role assignment |
| **N-B13** | **Removing ownership** does not revoke the role | Revoke it when the ownership period ends |
| **N-B14** | **Revoking the role** does not alter ownership or its history | End the ownership period on revocation |
| **N-B15** | **Neither ownership nor the role alone creates a Domain Entitlement** | Auto-create one from either — the shortcut that makes the role appear to work |
| **N-B16** | The **Access** module never reads `business_domain_owners` to decide a role, and the **Domains** module never reads role assignments to decide ownership | Add either read. **Architecture test** — it fails at the dependency, not at a behaviour |

### Deny by default, and the P1-04 gate

| # | Guard | Mutation |
| --- | --- | --- |
| N-D1 | No path → deny; **a gate cannot be outvoted** | Default to allow; evaluate paths before gates |
| N-D2 | Engine exception → **deny** | `catch { return allow }` |
| **N-D3** | **One** domain disabled → only that one is lost | Skip the enabled check |
| **N-D4** | **All** domains disabled → nothing granted | The empty-set filter |
| **N-D5** | **No enabled domains** → **nothing granted** | `if (domains.isEmpty()) return allow` — **the gate, as one line** |
| **N-D6** | An entitlement to a disabled domain is **retained** | Delete it on disable |
| **N-D7** | **Re-enable** restores exactly the prior access | Restore to a default |
| N-D8 | **Missing scope → nothing** | `scope ?? Scope::Organisation` |
| N-D8b | Clearing a ceiling → **`standard`** | Clear to `restricted` |
| N-D9 | Unrecognised stored value → **deny and log** | Fall through to allow |
| N-D10 | Inactive user → a **denied request**, not merely an absent row | Filter listings only |

**N-D3 to N-D7 are the P1-04 carried gate.** All five run, and
**`no enabled domains = no domain access, never allow-all`** is the one the gate
exists for.

### Incomplete grant paths — §4.5

| # | Guard | Mutation |
| --- | --- | --- |
| **N-C1** | Revoking the **last scope** leaves the entitlement **CURRENT** | End the entitlement too — *"an entitlement with no scope must be a mistake"* |
| **N-C2** | …and business access through it becomes **ZERO immediately** | Let the last decision stand until the next scope is assigned |
| **N-C3** | A missing scope **never defaults** to `domain` or `organisation` | `scope ?? Scope::Domain` — the widest possible mistake |
| **N-C4** | Re-scoping creates a **NEW scope period** | Reopen the ended row by nulling `ended_at` |
| **N-C5** | A previously ended scope **never revives** automatically | Revive the most recent one on re-scope |
| **N-C6** | **Simulator and enforcement return the SAME `denied_scope`** | Give the simulator its own message — it would read correct and prove nothing |
| **N-C7** | **No current ceiling → DENY**, never an assumed level | `ceiling ?? Sensitivity::Standard` — the invisible permissive default |
| **N-C8** | **Clearing WRITES a current `standard` row**; it does not delete | Delete the row and rely on N-C7's absence path |
| **N-C9** | The screen shows **"No access — scope required"** and the supporting sentence | Show it as an ordinary active entitlement. Asserted against rendered screen source |
| **N-C10** | An administrator can **FIND** incomplete entitlements by search/filter/status | Remove the filter — the state exists but nobody can reach it, the P1-04 discoverability defect |

### Independent paths

| # | Guard | Mutation |
| --- | --- | --- |
| N-P1 | An **incomplete** path contributes nothing | Let a path missing its scope authorise |
| N-P2 | A ceiling caps **its own path only** | Make the lowest ceiling a global maximum |
| **N-P3** | **A revoked row is NOT a deny** | Let it veto an active path — an invisible deny record, which D-64 rejects |
| N-P4 | A restrictive grant never reduces another | Intersect paths instead of evaluating independently |
| N-P5 | Scope never widens the domain | Let organisation scope reach outside it |

### Scope equivalence — D-74

| # | Guard | Mutation |
| --- | --- | --- |
| **N-Q1** | **`domain` and `organisation` resolve through ONE resolution** and return the identical record set for the same entitlement | Give `organisation` a second code path — the two are equal today, so **the mutation passes every functional test and the guard is the only thing that catches it** |
| **N-Q2** | **The scope control STATES the equivalence and the reservation** on the rendered screen | Delete the sentence. Asserted against `ScreenSource::rendered()`, which strips comments, so a docblock cannot satisfy it |

### Enforcement

| # | Guard | Mutation |
| --- | --- | --- |
| N-E1 | **Every** protected route names an action class | Drop one — it must fail to boot |
| N-E2 | A denial returns **no payload** | Return an empty object with the field names |
| N-E3 | The decision **precedes** the fetch | Fetch, then filter |
| N-E4 | No client-supplied authorization input is trusted | Accept a `scope` parameter |
| N-E5 | A hidden control is matched by a **backend refusal** | Hide it, leave the route open |
| N-E6 | No authorization in JavaScript | Compute one in the frontend |
| N-E7 | **Auditor writes nothing** | Let an Auditor through one write path |
| N-E8 | Cross-organisation is **Not Found** | Return 403 |
| **N-E9** | **All seven Identity routes stay `PLATFORM_ADMIN`** | Open one to `ORG_ADMIN` — the find-and-replace outcome |
| N-E10 | Every route opened to Organisation Administrator is on §10.1's list | Open one that is not |

### Lifecycle and parentage

| # | Guard | Mutation |
| --- | --- | --- |
| N-L1 | No route deletes an assignment, entitlement, scope or ceiling | Add one |
| N-L2 | Replace is **one transaction** | Revoke, then assign |
| N-L3 | Two assignments **on one calendar day** are both recorded | Date-valued timing — the P1-01 collision |
| N-L4 | Role codes are immutable; the catalogue has **seven** | Add an eighth; make a name mutable — *"Super Admin"* |
| N-L5 | Every named operation exists | Delete one — the P1-01 presence guard |
| N-L6 | Every write confirms itself | Bare redirect |
| **N-L9** | **Re-assigning a revoked role does NOT resurrect old entitlements** | Leave children orphaned instead of ending them with the parent |
| N-L10 | Re-granting a revoked entitlement resurrects no scope or ceiling | As above, one level down |
| N-L11 | **Deactivate → reactivate returns access EXACTLY** | End assignments on deactivation |
| N-L12 | **Disable → re-enable returns access EXACTLY** | Delete entitlements on disable |
| N-L13 | **No column caches an effective-access answer** | Add `effective_domains` |

### The D-49 migration and lockout — MySQL

| # | Guard | Mutation |
| --- | --- | --- |
| **N-M1** | `system_administrator` is **accepted** with a NULL organisation | Require one — **bootstrap then cannot create the first administrator** |
| **N-M2** | Every other role is **refused** with a NULL organisation | Permit it |
| **N-M3** | **Bootstrap creates the user AND the assignment in one transaction** | Create the user, then assign separately |
| **N-M4** | **Bootstrap works on a genuinely empty deployment** — no organisation, no users, no assignments | Run it against an empty database |
| **N-M5** | `migrate → rollback → migrate` **leaves the administrator an administrator** | Roll back and check. **MySQL** |
| **N-M6** | Migration 4 produces **exactly one** assignment from one column row | Make it create two, or none |
| **N-M7** | **`users.platform_role` and `PlatformRole` are GONE** | Leave the column readable — the second model |
| **N-M8** | **The last active System Administrator cannot be DEACTIVATED away** | Drop the guard |
| **N-M9** | **…nor REVOKED away** | Drop the new guard |
| **N-M10** | **Two concurrent revocations cannot reach zero administrators** | Move the locking read outside the transaction. **MySQL only.** Superseded in shape by **N-M15 to N-M20**, §2.6.3 — the set, not the subject, is what is locked |
| **N-M11** | `down()` restores `system_administrator` **from a CURRENT assignment**, and `NULL` when there is none | Restore the original pre-migration value — the "time machine" `down()` the Product Owner rejected |
| **N-M12** | `down()` **never restores an ENDED assignment** as a current role | Ignore `ended_at` |
| **N-M13** | `down()` restores the role for an **INACTIVE** user with a current assignment, and leaves `users.status` untouched | Filter `down()` by user status — collapsing assignment state into account status. Broken **in both directions**: the §2.6 lockout count must NOT drop its active-user filter |
| **N-M14** | The **deployment documentation STATES** that rollback after window A is not lossless, naming entitlements, scopes, ceilings and history | Delete the statement — the guard exists so the project cannot come to promise reversible business data |

### The administrator-set serialisation — MySQL only, §2.6

| # | Guard | Mutation |
| --- | --- | --- |
| **N-M15** | Both reducing operations lock **the whole current administrator SET** in one deterministic order, not the subject row | Lock the subject first — **the design I proposed and the Product Owner rejected.** It passes every single-request test |
| **N-M16** | **C-A** — deactivation racing revocation cannot reach zero | As above |
| **N-M17** | **C-B** — revocation racing revocation cannot reach zero | As above |
| **N-M18** | **C-C** — deactivation racing deactivation cannot reach zero | As above |
| **N-M19** | The losing request **re-evaluates after the winner commits** and returns the **business refusal** | Return the refusal without re-reading — right answer, wrong reason |
| **N-M20** | **No raw deadlock, integrity or database error reaches the administrator** in any of the three races | Let the driver exception surface. A deadlock victim is **not** the security mechanism |
| **N-M21** | A count of exactly **1 warns and does NOT block** normal administration | Refuse an unrelated administrative operation while the count is 1 |
| **N-M22** | `BootstrapState` stays **computed** from the assignment predicate, and P1-05 adds **no** recovery route of its own | Store a flag; add a P1-05 "reset administrator" path |

### Step-up

| # | Guard | Mutation |
| --- | --- | --- |
| N-S1 | Each of the five actions requires step-up | Drop one |
| N-S2 | **One step-up authorises ONE action, ONCE** | Make it a time window |
| N-S3 | Freshness comes from the **provider's `auth_time`** | Set a local flag |
| N-S4 | **A step-up return still passes every P1-00 validation** | Relax state, nonce or tenant for the return path |
| N-S5 | The action is **stored server-side**, not in the URL | Put the target id in the redirect |

### Events

| # | Guard | Mutation |
| --- | --- | --- |
| N-EV1 | `role` accepts **only the seven codes** | Pass free text |
| N-EV2 | **No free-text key exists** on the logger | Add `reason` |
| N-EV3 | A self-grant logs a **distinguishable** privileged event | Log it as an ordinary grant |
| N-EV4 | **Routine business denials are NOT logged** | Log every denial |

### The mandatory matrix — M1 to M20

**Every scenario from PLAN §30 runs as a test**, including *Sales cannot access
Finance*, *Own cannot see another salesperson*, *Manager sees the assigned team
and not an unrelated one*, *Executive only where entitled*, *Restricted stays
protected*, *Domain Owner gets nothing*, *System Administrator gets nothing*,
*disabled domain grants nothing*, *no enabled domains grants nothing*, *inactive
grants nothing*, *conflicting fails closed*, *a denied API returns no payload*,
*AI gets exactly the requesting user's access*, *revocation lands on the next
request*, **and M16–M20**: no manager inference, no recursion, Organisation
Administrator refused `system_administrator`, re-assignment does not resurrect,
and a privileged grant without step-up is refused.

---

## 14. Product Owner test strategy

### 14.1 The order the unit is built and tested

**D-50: engine → enforcement → administration UI → simulator → verification.**
Nothing later is demonstrated before the thing beneath it is proven.

### 14.2 What the Product Owner will do, in outline

| Stage | |
| --- | --- |
| **1. Nothing is granted** | Sign in as today's administrator. **Every business surface refuses.** The baseline of the whole unit |
| **2. Administration still works** | Organisation, Users & Groups, Business Domains, Identity & SSO all reachable — **the D-49 migration did not break anything** |
| **3. Grant a path** | Role → entitlement → scope → ceiling, one step at a time, seeing what each adds |
| **4. Simulate** | Current, proposed, **and which path produced each answer** |
| **5. The boundaries** | Domain Owner gets nothing · System Administrator gets nothing · an Executive sees only what is entitled |
| **6. The P1-04 gate** | Disable one domain · disable all · **observe that nothing is granted, not everything** · re-enable |
| **7. Parentage** | Revoke a role, re-assign it, **observe the old entitlements do not return** |
| **8. Revocation** | Revoke while signed in; **the next request is refused, without signing out** |
| **9. Step-up** | Attempt a privileged grant and **be sent to Microsoft to re-authenticate** |
| **10. Organisation Administrator** | Reaches organisation administration; **cannot reach Identity & SSO; cannot grant System Administrator** |

### 14.3 The permanence warning this script must carry

**Stated before the Product Owner types anything**, in the P1-04 shape:

| Action | Reversible? |
| --- | --- |
| Assigning a role, entitlement, scope, ceiling | **Revocable — but the history is permanent** |
| **Revoking** | Permanent. Re-granting creates a **new** grant, and §4 means the old children **do not return** |
| **A second System Administrator** | Assignable and revocable — **but the last one can never be removed** |

### 14.4 The P1-02 gate — an opportunity, not an instruction

**If a genuine second System Administrator is legitimately assigned during
testing** — because the organisation actually wants one — **that is the moment
to close the provider-wide SSO Re-check lock observation**, carried since P1-02.

> **DO NOT MANUFACTURE ONE.** If no real second administrator is established,
> the gate is **carried forward and said so**. A fake privileged account created
> to close a gate would make the evidence worth less than leaving it open.

### 14.5 What will NOT be testable, and why

| # | Not observable | Why |
| --- | --- | --- |
| U1 | **That AI gets exactly the user's access** | There is no AI surface and no business data. The **contract** is testable (§12); the integration is Phase 2/3 |
| U2 | **Row-level filtering of real records** | There are no records. Scope is tested against the structural targets it resolves |
| U3 | **True simultaneity** | Concurrency is proven against MySQL in CI. Two people clicking at the same instant would look identical to one person clicking twice |
| U4 | **The P1-02 gate**, unless §14.4's condition genuinely arises | |

### 14.6 Gates

| Gate | Requirement |
| --- | --- |
| **P1-04** | **MUST CLOSE HERE.** All five cases, §13 N-D3 to N-D7 |
| **P1-02** | Closes **only** on a genuine second System Administrator. Otherwise carried, honestly |

---

## 15. What this design deliberately does not build

- **No `roles` table**, no custom roles, no role rename, no editable capabilities, no role deactivation.
- **No effective dating**, no explicit deny records, no group-based assignment, **no permission cache**.
- **No person-level ceiling**, and **no redaction engine**.
- **No Fabric, semantic-model or Power BI integration** — the contract only.
- **No AI surface.**
- **No access reviews.** P1-07 — **and it is not an approval workflow.**
- **No durable audit table**, and **no repeated-denial detector.**
- **No password screen, and no dialog pretending to be step-up.**
- **No compatibility column** left behind by D-49.
- **No future organisation partition, and no column for one** — D-74 reserves the *word* `domain`, and builds nothing behind it.
- **No fake privileged account.**
- **No change to the `srikanth@lithan.com` record or the `software` domain** — both open operational items.

---

**P1-05 DESIGN — awaiting Product Owner review.** **D-74 is now decided** —
§7.4, *both scopes delivered and documented as equivalent today.* No decision
remains open. No implementation, schema, migration, engine, role assignment or
production privilege change until this design is approved.
