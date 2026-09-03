# P1-05 — Roles & Access: DESIGN

**DESIGN ONLY.** No schema written, no migration, no engine, no role assignment,
no route change, no screen, no production privilege change. This says exactly
what will be built so it can be argued with before it exists.

| | |
| --- | --- |
| PLAN | `P1-05-ROLES-ACCESS-PLAN.md` — **APPROVED 3 September 2026** |
| PLAN merge SHA | `c313a39652681bc198045fa1c175e2e70964c9be` (PR #93) |
| Decisions binding this design | **D-49 to D-73**, PLAN §31 |
| Decision raised and still open | **D-74** — §7.4 |
| Gates that must close here | **P1-04** (§13.3) · **P1-02** (§14.6) |
| Status | **Awaiting Product Owner review** |

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
| **Domain Owner / Director** | **NO automatic entitlement, from ownership or from the role.** Any limited domain-governance action must be **named explicitly in this catalogue** — §1.5. Nothing is inferred |
| **Manager** | Business actions only, through explicit entitlement and **explicitly assigned** scope — no recursion (§7.2) |
| **Business User** | Business actions only, through explicit entitlement and assigned scope |
| **Auditor** | **Read-only access and security evidence, organisation-wide.** No business rows without a separate business grant |

### 1.5 The Domain Owner question, answered rather than left open

**PLAN §15 says ownership grants nothing and the role grants nothing.** That
leaves a real question: *does the Domain Owner role permit anything at all?*

**This design's answer: in Release 1, `domain_owner` permits exactly the same
action classes as `business_user` — that is, none beyond `BUSINESS_DATA` through
explicit grants.** It exists as a role so that accountability can be *recorded
in the access model* and reviewed in P1-07, not because it unlocks anything.

> **If a domain-governance action is wanted later** — approving an entitlement
> to one's own domain, say — it is **added to this catalogue by decision**, and
> the catalogue is where a reviewer sees it. It is never inferred from
> ownership.

### 1.6 The catalogue is asserted, not just written

| Guard |
| --- |
| Exactly **seven** codes |
| **No** code, name or action set is mutable at runtime |
| `BUSINESS_DATA` is the **only** class that reaches grant-path evaluation |
| The four administration classes appear in **no** business-data decision |
| **No `roles` table exists** |

---

## 2. D-49 — the bootstrap migration, rollback and deployment sequence

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

**Migration 4 is data and it is reversible.** Its `down()` restores the column's
value from the assignments, so a rollback of 5 then 4 leaves the administrator an
administrator — asserted, on **MySQL**, in §13.

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

**Two paths now reach zero administrators, where P1-03 had one:**

| Path | Guard |
| --- | --- |
| Deactivating the last active one | P1-03's, **rewritten to count assignments** |
| **Revoking the last active one's assignment** | **New. Same shape** |

**Both count the same thing** — active `system_administrator` assignments held
by active users, excluding the subject — and both take the count **under a
locking read inside the write transaction**, so two administrators cannot
concurrently remove each other.

> **The P1-04 lesson applies exactly.** The lock is taken on the **parent** —
> here the `users` row of the subject — before the assignment rows are read,
> because the decision is taken over both. And the concurrency case runs against
> **MySQL**, because SQLite has no `SELECT … FOR UPDATE` and would report a lock
> that is not there.

**Refusal:** *"This is the only active System Administrator. Add or retain
another before removing this one."* — the existing sentence, extended to cover
revocation.

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
| **`organisation`** | **none** | See §7.4 |

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

### 7.4 Domain and Organisation — D-74, STILL OPEN

**Stated plainly because it has not been decided:**

> Under Release 1's single organisation, with the entitlement already naming a
> domain, **`domain` and `organisation` scope resolve to the SAME record set.**

There is no partition above business unit for them to differ across, and there
is one organisation.

| Option | Consequence for this DESIGN |
| --- | --- |
| **(a)** `organisation` only | The enum drops `domain` |
| **(b)** Both, documented as equivalent today | The enum keeps both, and **the screen must say they are the same today** |
| **(c) — recommended** | The enum drops `organisation` until it can mean something different |

**This design is written for (c) and will change to match whichever is chosen.**
It is called out here rather than resolved silently, because two identical
choices on a screen is a question with no right answer.

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

### 8.3 Clearing falls back to the most restrictive

Clearing a ceiling returns it to **`standard`** — never to the most permissive.
§13 N-D8b.

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

### Independent paths

| # | Guard | Mutation |
| --- | --- | --- |
| N-P1 | An **incomplete** path contributes nothing | Let a path missing its scope authorise |
| N-P2 | A ceiling caps **its own path only** | Make the lowest ceiling a global maximum |
| **N-P3** | **A revoked row is NOT a deny** | Let it veto an active path — an invisible deny record, which D-64 rejects |
| N-P4 | A restrictive grant never reduces another | Intersect paths instead of evaluating independently |
| N-P5 | Scope never widens the domain | Let organisation scope reach outside it |

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
| **N-M10** | **Two concurrent revocations cannot reach zero administrators** | Move the locking read outside the transaction. **MySQL only** — SQLite would report a lock that is not there |

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
- **No fake privileged account.**
- **No change to the `srikanth@lithan.com` record or the `software` domain** — both open operational items.

---

**P1-05 DESIGN — awaiting Product Owner review.** One decision is still open —
**D-74**, §7.4. No implementation, schema, migration, engine, role assignment or
production privilege change until this is approved.
