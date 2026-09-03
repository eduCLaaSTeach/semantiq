# P1-05 — Roles & Access: PLAN

**PLAN ONLY.** No design, no schema written, no migration, no route, no
controller, no service, no screen, no access engine, no role, no assignment, no
production privilege change. §31 records the decisions **as answered**.

Source of scope: `doc/SemantIQ_v2_PHASE_1_System_Administration.md` → **P1-05 —
Roles & Access**, and `doc/SemantIQ_v2.2_Ground_Zero_Architecture_Reset_Three_Phase_Blueprint.md` §2.

| | |
| --- | --- |
| Preceding units | P1-00 · P1-01 · P1-02 · P1-03 · P1-04, all **ACCEPTED** |
| Gates carried into this unit | **2** — §11.3 (P1-04) and §29 (P1-02) |
| **Decisions** | **D-49 to D-73 — ANSWERED at PLAN review, 3 September 2026.** §31 |
| Status | **APPROVED. Proceeding to DESIGN** |

### The Ground-Zero baseline, and it is binding

| Dimension | Controls |
| --- | --- |
| **Role** | **What actions** a person may perform |
| **Business Domain** | **Which business area** is visible |
| **Scope** | **Which records / rows** inside that area |
| **Sensitivity** | **Which fields / objects** may be exposed |

**A System Administrator receives no business data automatically.** Every
section below is written against these four sentences, and §30 breaks each.

### What the Product Owner review changed

Seven decisions came back **changed or new**, and each changed the plan rather
than being annotated onto it:

| # | Change | Where |
| --- | --- | --- |
| **D-52** | Baseline role names are **NOT editable**. They are security vocabulary, not branding — *"Super Admin"* must be unreachable | §3.2 |
| **Role schema** | **No mutable `roles` table.** An immutable catalogue plus a `role_code` on assignments — because with D-51/52/53/54 answered, **nothing about a role is manageable** | §3.6 |
| **D-60** | Ceiling is **per entitlement only**, never also per person | §7 |
| **D-62** | **Independent complete grant paths** replace the widest/lowest rule entirely | §10 |
| **D-63** | Above the ceiling → the engine returns **DENY**. No redaction engine here | §7.3 |
| **D-71** | Log **privileged-surface** denials. **No repeated-denial detector** in this unit | §27 |
| **D-73 — NEW** | **Privileged step-up re-authentication.** SYS-018, missing from the first draft | **§11b** |

**Two structural rules also arrived**, and both prevent a specific bug:

| Rule | Prevents |
| --- | --- |
| **Grant-path parentage** — entitlement belongs to a role-assignment period; scope and ceiling belong to an entitlement period | **Old access silently returning** when a role is re-assigned later |
| **Platform-scoped assignments** — `system_administrator` may exist with **no organisation** | Bootstrap being unable to create the first administrator, because it runs **before any Company Profile exists** |

---

## 0. The sentence this unit exists to make true, and the one it must never make false

> **Effective access = Identity + Platform Role + Business Domain + Scope +
> Sensitivity + Organisation / Team Relationship.**
>
> **Everything else grants nothing.**

**This is the most security-sensitive unit in Phase 1**, and the failure mode is
not a missing feature. It is a *convenience*: a line of code that says *"well,
they're an administrator"*, or *"they own the domain, so obviously…"*, written
by somebody solving a real problem at four in the afternoon.

**Six facts that must never, on their own, grant anything:**

| Fact | What it grants |
| --- | --- |
| Being a **System Administrator** | **Nothing.** Platform administration is not business-data entitlement |
| Being a **Domain Owner** | **Nothing.** Accountability is not entitlement — P1-04, D-42 |
| Being somebody's **manager** | **Nothing** on its own. It participates **only** through an **explicitly assigned** Team scope — **never recursively**, D-66, §16 |
| Being in a **group** | **Nothing — D-58 CONFIRMED IT.** P1-03 groups keep granting nothing, and **the engine must not read `group_memberships` at all** |
| **Owning** a domain | **Nothing.** Same as row 2, stated twice because it is the one people re-derive |
| Being in a **Business Unit** | **Nothing** on its own. It participates **only** in Business Unit scope |

Each of those may participate **only where the approved model explicitly says
it does**, and §30 breaks each one deliberately.

---

## 1. Purpose and business outcome

### The sentence this unit is built to make true

> *"This person can see exactly this much of the organisation's intelligence,
> for reasons anybody can read, and the same answer applies whether they ask
> through a screen, an API, Power BI or an AI conversation."*

Today SemantIQ has identity (P1-00/P1-02), organisation structure (P1-01),
people and groups (P1-03) and a named intelligence estate (P1-04). **It has no
way to say who may see any of it** — deliberately, because every earlier unit
refused to invent one.

| Unit | Needs the access engine for |
| --- | --- |
| **P1-06 Security Status** | Posture is *"who can see what"* — unanswerable without this |
| **P1-07 Access Reviews** | A review reviews *assignments*. There are none yet |
| **P1-08 Audit** | The events worth keeping are privileged changes, and this unit creates the privileges |
| **Phase 2 Fabric** | Security propagation into semantic models and Power BI **derives from this engine** and must not restate it |
| **Phase 3 Workplace / AI** | *"AI never broadens access"* is only meaningful once there is an access to not broaden |

**The business outcome:** an administrator can grant a person a specific,
reviewable amount of the organisation's intelligence — and can show, before
publishing the change, exactly what that person will and will not be able to
see.

### The mistake this plan is built to prevent

**P1-05 is where a generic role-CRUD screen would do enormous damage.** Roles,
assignments, entitlements, scopes and ceilings all look like list-and-form
screens. Building them that way produces something that *appears* to work, is
demonstrable, and enforces nothing — because the screens are not the control.

> **The deliverable is an ENGINE with an administration surface, not an
> administration surface with an engine bolted on.**

§9, §23 and §24 exist to keep that distinction. If a DESIGN can be written for
the screens before the engine is specified, the DESIGN is wrong.

---

## 2. Exact in scope / out of scope

### In scope

| # | Item |
| --- | --- |
| 1 | The **effective-access engine** — one implementation, §9 |
| 2 | **The immutable role catalogue** — the seven baseline roles, **and an explicit role → permitted-action matrix**, §3 |
| 3 | **Role assignments** — a person holds a role. **Platform-scoped or organisation-scoped**, §4 |
| 4 | **Domain entitlements** — a person may see a domain, §5 |
| 5 | **Scope assignments** — which records inside it, with **explicit structural targets**, §6 |
| 6 | **Sensitivity ceilings — one per entitlement**, §7 |
| 7 | **The Access Simulator** — preview, §8 |
| 8 | **Deny by default**, and failing closed on anything unknown or conflicting, §11 |
| 9 | **Privileged step-up re-authentication** — D-73, §11b |
| 10 | **Backend enforcement** on every protected route and API, §23 |
| 11 | **Removal of `users.platform_role`**, with bootstrap, `BootstrapState`, `RequireSystemAdministrator` and the last-administrator guard all moved to the assignment authority — §3.4 |
| 12 | **Enumerated migration of existing P1-01…P1-04 administration routes** to Organisation Administrator where appropriate — §13.2 |
| 13 | **Revocation with immediate effect**, §22 |
| 14 | Security events for every privileged change, §27 |
| 15 | **The P1-04 carried gate**, closed — §11.3 |
| 16 | Search, filter and administrative usability at real volume, §28 |

### Out of scope — each owned elsewhere

| Item | Owner |
| --- | --- |
| Per-domain security **posture reporting** | P1-06 |
| **Access reviews** — recertification, campaigns, attestations | P1-07 |
| **Durable audit storage** and the audit screens | P1-08 |
| **Row-level security in Fabric**, semantic-model roles, Power BI RLS/OLS | **Phase 2** — and it **derives from** this engine (§25), it does not restate it |
| The **AI conversation surface** | Phase 3 — but the boundary it must respect is defined here (§25) |
| Any **actual business data** | There is none. Phase 2 onboards it |
| Creating users, groups, teams, domains | P1-03 / P1-01 / P1-04 |

### What already exists and must not be rebuilt

| Existing | Reuse |
| --- | --- |
| `RequireSystemAdministrator`, `RequireOrganisation` | The administration gates. **Not** the access engine — §12 |
| `SecurityEventLogger` and the D-12 key boundary | §27. A new key needs a decision |
| `PurgeDependencies` | §3, §4 — history blocks removal, as everywhere else |
| Organisation hierarchy — business units, departments, teams, memberships, management relationships | **The source of Team and Business Unit scope**, §6. Read, never rewritten |
| `business_domains` + `business_domain_owners` | Entitlements reference domains. **Ownership is read only to display accountability, never to grant** |
| The frozen UI foundation | Shell, tables, filters, refusal and confirmation banners, pagination |

---

## 3. Role model and lifecycle

### 3.1 The seven baseline roles

| Role | What it is |
| --- | --- |
| **System Administrator** | Administers the platform. **No business data.** §12 |
| **Organisation Administrator** | Administers the organisation's people, structure and assignments. §13 |
| **Executive** | Cross-functional intelligence over **explicitly entitled** domains. §14 |
| **Domain Owner / Director** | Accountable for a domain, and **entitled to nothing by that fact**. §15 |
| **Manager** | Business user whose Team scope follows the hierarchy. §16 |
| **Business User** | The ordinary case. Own or Team. §17 |
| **Auditor** | Reads evidence about access, not the data itself. §18 |

**A role says what a person may DO. It does not say what data they may see.**
That is the domain entitlement (§5), the scope (§6) and the ceiling (§7).
Conflating the two is how *"Executive"* silently becomes *"sees everything"*.

### 3.2 Lifecycle — named operation by operation

**The word "CRUD" appears nowhere in this plan.** P1-01's most expensive defect
was Update missing from four entity types behind that word.

**Four decisions came back and between them they empty this table:**

| Operation | Available | Decision |
| --- | --- | --- |
| **Create** a role | **NO** | D-51 — the seven are the product's vocabulary. **No custom roles** |
| **Read** — list, one | **Yes** | The screen shows the seven and what each means |
| **Change display name** | **NO** | **D-52 — CHANGED at review.** See below |
| **Change description** | **NO** | Follows D-52. A description that contradicts a fixed name is the same hazard |
| **Change what the role permits** | **NO** | D-53 — fixed in Release 1, and named in an explicit catalogue (§3.3) |
| **Activate / Deactivate** | **NO** | D-54 |
| **Assign** | **Yes** | §4 |
| **Replace / Revoke** | **Yes** | §4 |
| **Retain history** | **Always** | §4 |
| **Purge** a role | **NO** | Nothing to purge — there is no role record to remove |
| **Change its stable code** | **NO, ever** | §3.5 |

#### D-52 — role names are NOT editable, and the reason is the "Super Admin" hazard

The first draft recommended permitting a display-name change, by analogy with
D-41 for domains. **The Product Owner refused it, and the analogy was wrong.**

> **A domain name is the organisation's word for its own business area. A role
> name is SECURITY VOCABULARY.**

*Sales* may be called *Commercial* with no consequence at all. **Renaming
*Business User* to *Super Admin* changes what every administrator believes they
are granting** — and P1-03 already produced that exact hazard on its first day
of production use, when a group called *Super Admin* appeared and was carried
forward to this unit as a naming risk.

An editable role name also means **the same role means different things in
different deployments**, so no screenshot, no runbook and no support
conversation transfers between them.

### 3.3 The role → permitted-action catalogue — D-53, and it is a DESIGN deliverable

> **"A role says what a person may do" is prose, and prose is not a control.**
> DESIGN must produce **one explicit catalogue**: for each of the seven roles,
> exactly which actions it permits.

**At minimum the catalogue distinguishes five action classes**, and the
separation between the first four and the fifth is the whole point:

| Class | Examples | Requires domain + scope + sensitivity? |
| --- | --- | --- |
| **Platform administration** | Identity & SSO, session policy, providers, deployment-facing settings | **No** — and it grants **no** business data |
| **Organisation administration** | Users, groups, teams, hierarchy, domains, company profile | **No** — and it grants **no** business data |
| **Access administration** | Role assignments, entitlements, scopes, ceilings | **No** — and it grants **no** business data |
| **Evidence / audit read** | Who holds what, when it changed, security events | **No** — and it grants **no** business data |
| **Business-data actions** | Reading, exporting, querying business records | **YES — always.** Role alone never suffices |

**System Administrator and Organisation Administrator administration rights are
separate from business-data entitlement, and the catalogue must show that as a
structural fact** rather than as a sentence somebody has to believe.

### 3.4 THE `users.platform_role` SEAM — D-49, APPROVED: assignments only

**Assignments become the single source of truth. The column is removed in this
unit. No compatibility column is left behind.**

That was option (c) of the first draft, and it is approved because it is the
only one that cannot produce **two competing authorization models** — a column
`RequireSystemAdministrator` reads and an assignment table the engine reads,
with nothing saying which wins when they disagree. They *would* disagree, the
first time a revocation updated one and not the other.

#### 3.4.1 THE BOOTSTRAP CORRECTION — and it is the reason this is not a simple swap

> **System Administrator is a PLATFORM-SCOPED role. It must be assignable before
> a Company Profile exists.**

This was missed by the first draft and it changes the data model:

| Role | Organisation |
| --- | --- |
| **`system_administrator`** | **May be NULL.** A platform-scoped assignment |
| **Every other role** | **REQUIRED.** Organisation and business roles are meaningless without one |

**Why it matters, concretely.** `GrantRedeemer::redeem()` creates the very first
user on a deployment that has **no organisation at all** — `organisation_id` is
still null, and P1-01's Company Profile has not been created. An assignment
model that required an organisation would make the first administrator
**impossible to create**, and the failure would appear only on a genuinely
empty deployment, which is the one case no existing test covers by accident.

#### 3.4.2 The exact seam DESIGN must replace — not assume

**`GrantRedeemer` writes `'platform_role' => PlatformRole::SystemAdministrator`
inside its atomic transaction, at line 63.** DESIGN must replace **that exact
statement** with the creation of a role assignment **inside the same
transaction**, so that either both the user and their assignment exist or
neither does. A bootstrap that creates a user and then separately assigns a role
has a window in which the deployment has a user who is nobody.

**Every reader of the column, enumerated from the source rather than
remembered:**

| Where | Reads | Must become |
| --- | --- | --- |
| `GrantRedeemer::redeem()` | **Writes** `platform_role` | Creates the assignment, same transaction |
| `BootstrapState` | `User::activeSystemAdministrators()->exists()` | The same question, asked of assignments |
| `RequireSystemAdministrator` | `$user->isSystemAdministrator()` | The engine |
| `User::isSystemAdministrator()` | `platform_role === SystemAdministrator` | The engine — **one implementation** |
| `User::scopeActiveSystemAdministrators()` | `where('platform_role', …)` | A join to active assignments |
| `UserDirectoryService::refuseIfLastAdministrator()` | The same scope, under `lockForUpdate()` | §3.4.3 |
| `SystemAdministratorNavigationAuthorizer` | `isSystemAdministrator()` | The engine |
| `ConsoleController` | `isSystemAdministrator()` for a prop | The engine |
| `PlatformRole` enum, `users.platform_role` column, its migration | — | **Removed** |

**Nine call sites. Not one may keep its own answer.**

#### 3.4.3 The last-administrator guard must survive the migration

P1-03 delivered a guard that refuses to deactivate the **only** active System
Administrator, held by a **locking read inside the write transaction** so that
two administrators cannot concurrently remove each other and leave zero.

> **That guard must still hold after the column disappears — and it now has
> more ways to be reached.**

| Path that could reach zero administrators | Must be refused |
| --- | --- |
| Deactivating the last active one (P1-03) | Already guarded. **Must keep working** |
| **Revoking the last active one's role assignment** — new in P1-05 | **Must be guarded** |
| Two administrators revoking each other **concurrently** | **Must be serialised**, exactly as P1-04 serialises on the parent row |

**Zero administrators is unrecoverable**: bootstrap does not reopen once a
System Administrator has existed. §30 breaks the guard, and the concurrency case
runs against **MySQL**, because SQLite has no `SELECT … FOR UPDATE` and would
report a lock that is not there — the lesson P1-04 paid for.

#### 3.4.4 What the migration must prove before it is safe

| # | Requirement |
| --- | --- |
| 1 | **The existing `platform_role` value becomes a role assignment**, in the same migration |
| 2 | **The column, the enum and the guard test are removed in that same unit.** A dead column that once meant something is the second model wearing a disguise |
| 3 | **Exactly one implementation** of *"is this person a System Administrator"* remains, asserted structurally |
| 4 | **migrate → rollback → migrate leaves the administrator still an administrator**, proven on **MySQL** |
| 5 | **Bootstrap still produces a working first administrator on an empty deployment** — with no organisation |
| 6 | **Production has exactly one row to migrate**, already confirmed: `platform_roles_total: 1`, `P1-04-BUSINESS-DOMAINS-VERIFICATION.md` §9.5 |

### 3.5 The role code is immutable

`system_administrator`, `organisation_administrator`, `executive`,
`domain_owner`, `manager`, `business_user`, `auditor`.

Every assignment, every security event and every Phase 2 security mapping joins
to it. Immutable for the same reason P1-03's `external_subject` and P1-04's
domain code are.

### 3.6 THERE IS NO `roles` TABLE — the schema simplification

**Because D-51, D-52, D-53 and D-54 were all answered "no", NOTHING ABOUT A ROLE
IS MANAGEABLE.** No creation, no rename, no re-description, no capability
change, no deactivation.

> **Building a mutable `roles` table anyway would be creating a role-management
> data model for a thing nobody can manage** — a screen with a Save button that
> can never be pressed, a service with no operations, and a migration that
> exists only to look extensible.

**Instead:**

| | |
| --- | --- |
| **The catalogue** | An **immutable product constant** — the seven codes, their names, and the actions each permits (§3.3). The same shape as P1-04's `BaselineDomains` |
| **Assignments** | Store a controlled **`role_code`**, validated against the catalogue on write |
| **The screen** | Still shows the seven roles and what each one means, **read-only**. Nothing is lost from the administrator's view |

**This is the difference between the catalogue and P1-04's rejected "static
catalogue as source of truth" (D-46).** There, domains were rows an organisation
owns and a code-only catalogue would have been a second source of truth. Here
**the roles are product vocabulary that no deployment may vary**, so a constant
is the *correct* source of truth and a table would be the second one.

**If a later release genuinely needs custom roles**, that is a schema change
with a decision behind it — not a table built in advance for a requirement
nobody has.

### 3.7 Purge

**There is nothing to purge.** With no `roles` table there is no role record to
remove, and an **assignment** is never deleted by any route — it is revoked, and
the history stays. §30 N-L1.

---

## 4. Role Assignment lifecycle

**A role assignment is: this person holds this role, in this organisation (or
platform-wide), from this moment until this moment.**

### 4.1 Platform-scoped versus organisation-scoped — D-49

| Role | Organisation on the assignment |
| --- | --- |
| **`system_administrator`** | **May be NULL — platform-scoped.** It must be assignable before a Company Profile exists (§3.4.1) |
| All six others | **REQUIRED.** Refused without one |

**This is a validation rule with two directions**, and both are asserted: a
`system_administrator` assignment must be *permitted* with no organisation, and
every other role must be *refused* without one. §30 N-A1.

### 4.2 Operations

| Operation | Available | Notes |
| --- | --- | --- |
| **Create / Assign** | Yes | **To a PERSON. Never to a group** — D-58 |
| **Read** | Yes | Per person, and per role |
| **Change** the person or the role on an existing assignment | **NO** | That is a revoke plus an assign, and it must look like two events because it **is** two |
| **Replace** | Yes | Revoke the current, create the next, **in one transaction** |
| **Revoke** | Yes | Immediate — §22. **Ends its child grants too** — §4.4 |
| **Retain history** | **Always.** Never deleted, never updated in place | |
| **Activate / Deactivate the assignment** | **NO** | A third state between assigned and revoked is one every query must remember to exclude. Revoke and re-assign |
| **Purge** | **NO route** | An assignment that existed is evidence somebody had access |

**Several roles at once — D-55, approved.** A real Finance Manager is plausibly
*Manager* **and** *Business User*, and forbidding it forces one role to absorb
the other's meaning. §10 governs how they combine.

**No future dating — D-56.** An assignment **begins when it commits and ends
when it is revoked.** No scheduled starts, no expiries. Future-dated access is a
real requirement and a large one; adding it here makes every query
time-dependent and every test harder to trust.

### 4.3 The last System Administrator cannot be revoked away

Revoking a role assignment is a **new path to zero administrators** that P1-03's
deactivation guard never had to cover. §3.4.3 — refused, and serialised against
a concurrent second revocation.

### 4.4 GRANT-PATH PARENTAGE — the rule that stops old access returning

> **A domain entitlement belongs to a specific role-assignment PERIOD. A scope
> and a ceiling belong to a specific entitlement PERIOD.**

```
role assignment period
   └── domain entitlement period
          ├── scope
          └── sensitivity ceiling
```

**Revoking a role assignment ends that grant path and all its active children,
transactionally.** Re-assigning the same role later creates a **new** period —
and **must not silently reactivate the old entitlements.**

**The bug this prevents is specific and it is quiet.** Without parentage:

> An Executive's *Manager* role is revoked. Their Finance entitlement, scope and
> ceiling remain as orphan rows. Months later they are made a *Manager* again
> for an unrelated reason — **and Finance comes back**, with the scope somebody
> set in a different context, without anybody granting it and without appearing
> in any change record.

The same applies one level down: revoking and later re-granting an **entitlement
must not resurrect an old scope or ceiling.**

#### This is NOT the same as an external state gate

**Two different mechanisms, and conflating them breaks one of them:**

| | Parentage — §4.4 | External state gates — §20 |
| --- | --- | --- |
| Examples | Revoking a role assignment; revoking an entitlement | User deactivated / reactivated · domain disabled / re-enabled |
| The grant | **Ended.** It is over | **Preserved.** Untouched |
| Coming back | Requires a **new, deliberate grant** | Access returns **exactly** when the state becomes valid again |
| Why | Somebody **decided** to remove it | Somebody changed a **state**, not a decision |

**Deactivating a user is not a revocation, and disabling a domain is not a
revocation.** Both must leave every assignment intact — P1-04's D-42 says so for
domains, and P1-03's D-36 says so for users. §30 breaks both directions.

---

## 5. Domain Entitlement lifecycle

**An entitlement is: this person, through THIS role-assignment period, may see
this domain.**

| Operation | Available | Notes |
| --- | --- | --- |
| **Grant** | Yes | **Person only** — D-58 |
| **Read** | Yes | By person, and by domain — *"who can see Finance"* must be answerable here |
| **Change** the domain on an existing entitlement | **NO** | Revoke and grant |
| **Revoke** | Yes | Immediate, and **ends its scope and ceiling** — §4.4 |
| **Retain history** | **Always** | |
| **Purge** | **NO route** | |

**An entitlement to a DISABLED domain grants nothing, and is not deleted** —
§11.3. Disabling is the organisation saying *"we are not using this"*; it is not
a revocation and must not destroy the administrator's work.

**No entitlement is ever created automatically — D-57.** Not by ownership, not
by domain creation, not by role assignment, not by business-unit membership.
**Every entitlement is explicit**, because an implicit one is invisible in the
very screen built to make access reviewable.

---

## 6. Scope Assignment lifecycle

**Scope answers: WHICH RECORDS inside an entitled domain.** It belongs to an
entitlement period (D-59, §4.4), never to a person.

### 6.1 Every scope needs an EXPLICIT structural target — D-59

> **The Ground-Zero rule (SYS-005): managers inherit visibility only for
> EXPLICITLY ASSIGNED teams and hierarchies.**

**Vague scopes are forbidden.** *"Their business unit"* and *"the team their
report happens to belong to"* are inferences, and an inference nobody assigned
is an entitlement nobody can review.

| Scope | Target it must carry | Grounded in |
| --- | --- | --- |
| **Own** | **None.** The identity is the target | P1-00 identity |
| **Team** | **A specific `teams.id`**, assigned deliberately. More than one team means more than one scope | P1-01 `teams`, `team_memberships` |
| **Business Unit** | **A specific `business_units.id`**, assigned deliberately | P1-01 `business_units` → `departments` → `teams` |
| **Domain** | **None.** The entitlement already names it | The entitlement |
| **Organisation** | **None.** The organisation on the assignment | P1-01 `organisations` |

**A Team scope is a named team, not "the teams they are in".** DESIGN must
define the reference explicitly and §30 breaks the inference version.

### 6.2 Domain and Organisation scope — the honest answer

**D-59 asked for this and the honest answer is uncomfortable:**

> **Under Release 1's single-organisation model, with the entitlement already
> naming a domain, `Domain` and `Organisation` scope RESOLVE TO THE SAME RECORD
> SET.**

Both mean *every record in this domain*. There is no organisational partition
above business unit for them to differ across, and there is only one
organisation.

**Two labels that silently mean the same thing is exactly what D-59 forbids**,
so one of these must be chosen and written down — **D-74, §31:**

| | Option | For | Against |
| --- | --- | --- | --- |
| **A** | **Deliver `Organisation` only** and drop `Domain` in Release 1 | No two labels for one meaning. An administrator cannot pick the wrong one | The Ground-Zero source lists five scopes; delivering four needs stating |
| **B** | **Deliver both, and document that they are equivalent today**, with `Domain` reserved for the future partition | Matches the source vocabulary | Two identical choices on a screen is a question with no right answer |
| **C — recommended** | **Deliver `Domain` only** and defer `Organisation` until it can mean something different | The entitlement is domain-shaped, so `Domain` is the honest name for "all of it". Keeps the source's five as a vocabulary while shipping four that differ | Same statement required as (A) |

**Whichever is chosen, the reason is recorded** — because the next reader will
otherwise re-derive the equivalence and assume it was an oversight.

### 6.3 Operations

| Operation | Available |
| --- | --- |
| **Assign** a scope to an entitlement | Yes |
| **Change** | **Yes** — a change of degree, not of subject, so it may be edited in place **with the previous value retained as history** |
| **Revoke** | Yes — the entitlement then grants **nothing**, never a default |
| **Retain history** | Always |
| **Purge** | **NO route** |

> **The trap.** Removing a scope must **narrow to nothing**, never fall back to
> a default. A missing scope read as *"no restriction"* is the same class of
> defect as the P1-04 disabled-domain gate. §30 N-D8.

**Scope never widens the domain.** *Organisation* scope on a Sales entitlement
means *all Sales records*, **not** all records. §30 N-P5.

---

## 7. Sensitivity Ceiling — ONE PER ENTITLEMENT

**D-60, CHANGED at review: the ceiling is per entitlement only.**

| Level | Means |
| --- | --- |
| **Standard** | The **default**. Ordinary business fields |
| **Confidential** | Sensitive business fields — commercially or personally significant |
| **Restricted** | Bank, identity, medical and equivalent. Exceptional, and **requires step-up to grant** (§11b) |

### 7.1 Why not also a per-person ceiling

The first draft proposed **both**, with the lowest winning. The Product Owner
refused it, and the refusal is right:

> **Two independent cap paths create a confusing double-grant requirement.**

An administrator raising an entitlement to *Confidential* would find it still
capped by an invisible person-level *Standard* set months earlier by somebody
else — and the screen showing the entitlement would not explain why. **One
ceiling, on the thing it caps.**

**A future global person maximum may be introduced as a separately reviewed
rule** if a real need appears. It is not needed to make Release 1 correct.

### 7.2 Operations

| Operation | Available |
| --- | --- |
| **Set** — on an entitlement | Yes. **Defaults to Standard** |
| **Change** | Yes, retained as history |
| **Clear** | Yes → falls back to **Standard**, the most restrictive default — never the most permissive |
| **Retain history** | Always |
| **Purge** | **NO route** |

### 7.3 Above the ceiling → the engine returns DENY — D-63, CHANGED

> **The engine answers `deny` for that field or action. It does not redact.**

The first draft proposed redaction. **P1-05 has no business data to redact**,
and building a generic redaction engine over data that does not exist would be
designing against an imagined shape.

| Layer | Responsibility |
| --- | --- |
| **The engine (P1-05)** | **Allow or deny**, per field and per action. Nothing else |
| **Phase 2 consumers** | May **omit** a denied field so a report is usable — **and must indicate that content was withheld** |

> **NO SILENT REDACTION.** A report that quietly drops a column is a report
> whose reader believes they are seeing everything. The decision comes from this
> engine; the presentation of the withholding is the consumer's, and it must be
> visible.

**P1-04's `access_expectation` is context only — D-61.** The engine never reads
it. Enforcing it would make P1-04 retrospectively an access-control unit.

---

## 8. Access Simulator — purpose and boundaries

### What it is

**A preview. Nothing else.** *"If I make this change, what will this person be
able to see?"*

### The rule that makes it worth building

> **IT MUST CALL THE SAME ENGINE THAT ENFORCES.** Not a copy, not a simplified
> version, not a "close enough" reimplementation for display.

A simulator with its own logic is worse than no simulator: it produces confident
answers that are wrong exactly when the two implementations have drifted, which
is exactly when somebody most needs the truth. §30 asserts there is **one**
implementation, and the mutation is *give the simulator its own copy*.

### What it must distinguish

| | |
| --- | --- |
| **Current effective access** | What this person can see **right now** |
| **Proposed effective access** | What they would see **if the change were published** |
| **Why** | For each answer, **which assignment, entitlement, scope and ceiling produced it** — and for a refusal, **which one was missing or which one capped it** |

**The "why" is not a nice-to-have.** An access model nobody can explain is one
nobody can review, and P1-07 exists to review it.

### Boundaries

| | |
| --- | --- |
| **It changes nothing** | It is a **read**. It writes no assignment and no entitlement — and, per P1-04's lesson, **no state at all**: no GET may mutate |
| **It cannot exceed the caller's own authority** | An administrator may simulate a person **in their own organisation** only |
| **It shows no business data** | There is none in Phase 1, and even in Phase 2 it must show *what would be visible*, not the values |
| **It is not an approval workflow** | Publishing a change is the ordinary assignment path. P1-07 owns recertification |

---

## 9. Effective-access calculation

### 9.1 One engine, one answer

> **Exactly one implementation, in one place, called by every enforcement point
> and by the simulator.**

Asserted structurally (§30): no second function anywhere may compute
entitlement, scope or ceiling.

### 9.2 The question it answers

**Not** *"what can this person see?"* — an open-ended query is an invitation to
build a permissive default. The engine answers a **closed** question:

> **May this identity perform this action on this resource, in this domain, at
> this sensitivity, right now?** → **allow** or **deny**, plus **why**.

Everything a screen needs (what to list, what to hide) is derived from repeated
closed questions or from an explicit, separately reviewed projection — never
from a "give me everything" call whose result is then filtered.

### 9.3 Resolution order — proposed, subject to §10 and D-62

| # | Step | On failure |
| --- | --- | --- |
| 1 | Identity is **authenticated** | **DENY** |
| 2 | User is **active** | **DENY** — §20 |
| 3 | User belongs to the **same organisation** as the resource | **DENY** — §19 |
| 4 | User holds a **role** permitting the action | **DENY** |
| 5 | A domain **entitlement** exists for the resource's domain | **DENY** |
| 6 | That domain is **enabled** | **DENY** — §11.3, the P1-04 gate |
| 7 | The **scope** on that entitlement includes the record | **DENY** |
| 8 | The **ceiling** permits the field/action's sensitivity | **DENY**, or **redact to the ceiling** — **D-63** |
| — | Anything unknown, malformed, conflicting or unreachable | **DENY** — §11 |

**Every step is a narrowing.** No step may widen what an earlier step allowed,
and §30 breaks that.

### 9.4 What must never appear in the engine

Named so a reviewer can search for them:

- `if (isSystemAdministrator())` returning **allow** for business data
- `if (ownsDomain())` returning allow
- `if (isManager())` returning allow outside Team scope
- `if (domains.isEmpty()) return allow` — **the P1-04 gate, as one line**
- `if (scope === null) return allow`
- any `catch { return allow }`
- `?: true`, `?? true` on an authorization value

---

## 10. Independent grant paths — D-62, REPLACING the first draft's rule

**The first draft's "widest scope, lowest ceiling, across all grants" rule is
NOT APPROVED and has been removed.** It is replaced by something simpler and
safer to reason about.

### 10.1 The rule

> **A GRANT PATH is: an active role assignment + an active domain entitlement +
> its scope + its sensitivity ceiling.**
>
> **The request is ALLOWED when at least one complete, active grant path
> authorises it.**

```
grant path  =  role assignment (active)
                 └── domain entitlement (active, domain enabled)
                        ├── scope        (covers this record)
                        └── ceiling      (covers this field/action)
```

Each path is evaluated **whole and independently**. A path either authorises the
request or it does not; it never contributes half an answer to another path.

### 10.2 What follows from it

| # | Consequence |
| --- | --- |
| 1 | **Role actions combine as a union.** Holding Manager and Business User permits both sets of actions |
| 2 | **Several entitlements widen reachable records** through their **own** scopes — independently, not by merging |
| 3 | **A restrictive second grant NEVER reduces access already granted by another valid path.** A narrow Finance grant cannot claw back a wide Sales one |
| 4 | **A ceiling caps its OWN path only.** It is not a global maximum and it does not reach across |
| 5 | **Revoked and historical rows simply stop contributing.** They are not evaluated |
| 6 | **A revoked grant is NOT a deny against a separate active grant** |

### 10.3 Why consequence 6 is the important one

**P1-05 rejects explicit deny records — D-64.** If a revoked historical row were
allowed to subtract from a live grant, it would **become** a deny record — an
invisible one, created by an ordinary revocation, that nobody chose and no
screen shows.

> **A revocation ends a path. It does not create a rule.**

§30 N-P3 breaks exactly that: *let a revoked row veto an active path.*

### 10.4 Why this is safer than the rule it replaces

The first draft took the **widest** scope and the **lowest** ceiling across
*all* grants — mixing dimensions from different decisions. Two problems:

| | |
| --- | --- |
| It **combined halves of unrelated decisions** | A wide scope from one grant could pair with a low ceiling from another, producing an effective access **nobody ever assigned** and no screen could explain |
| The asymmetry was **easy to get wrong** | Widest-for-one and lowest-for-the-other invites the uniform simplification, which silently **raises** every ceiling |

**Independent paths remove both**, because there is nothing to combine. The
simulator's *"why"* also becomes answerable in one sentence: **which path
allowed it**, or, for a refusal, **which link in every path was missing**.

### 10.5 The global gates are absolute and sit OUTSIDE any path

**No grant path can satisfy these. They are checked first and they deny
outright:**

| Gate |
| --- |
| **Unauthenticated** → deny |
| **Inactive user** → deny |
| **Organisation mismatch** → deny |
| **Disabled domain** → deny |
| **Unknown, malformed or conflicting state** → deny |

**A gate is not a path and a path cannot outvote a gate.** §30 N-D1 breaks it.

---

## 11. Deny-by-default behaviour

### 11.1 The rule

> **No grant means no access.** Every path returns deny unless something
> explicitly, currently and verifiably allows.

### 11.2 What must fail closed

| Condition | Result |
| --- | --- |
| No role, no entitlement, no scope | **DENY** |
| Entitlement to a domain that no longer exists | **DENY** |
| Scope referencing a deleted or inactive team | **DENY** |
| Unrecognised role, scope or sensitivity value in the database | **DENY**, and it is a **security event** — data has been changed outside the application |
| The engine throws, times out, or a dependency is unreachable | **DENY** |
| A cache is cold, stale or missing | **DENY**, then recompute — §22 |
| The organisation is inactive | **DENY** |

**A failure to decide is a denial.** Not an error page that some later change
turns into a pass.

### 11.3 THE P1-04 CARRIED GATE — mandatory, and stated as its cases

> **A disabled domain can never broaden access.**

The failure is concrete: the natural implementation of "disabled" is *a filter
that removes a domain from a set*, and a filter skipped when the set is empty
turns **no domains enabled** into **allow everything**.

**All five cases must be run and recorded:**

| # | Case | Required outcome |
| --- | --- | --- |
| 1 | **One** domain disabled | No access through it. The others are **unchanged** |
| 2 | **All** domains disabled | **No domain access at all** |
| 3 | **No enabled domains at all** | **NO DOMAIN ACCESS — never unrestricted access** |
| 4 | An entitlement whose domain is **subsequently disabled** | Grants nothing while disabled. **The entitlement is NOT deleted** |
| 5 | **Re-enable** | Access returns to **exactly** what it was, and no further |

**Case 3 is not a rewording of case 2.** *All disabled* and *none exist* reach
the same empty set by different paths, and a filter that guards one may not
guard the other.

---

## 11b. Privileged step-up re-authentication — D-73, NEW

**SYS-018 of the Ground-Zero source requires that designated privileged actions
support step-up authentication. The first draft of this plan missed it
entirely** — and P1-05 is the first unit that can actually grant privilege, so
this is where it stops being theoretical.

### 11b.1 What requires fresh re-authentication

| Action | Why |
| --- | --- |
| **Granting System Administrator** | Creates a second platform administrator |
| **Revoking System Administrator** | The highest-consequence revocation there is |
| **Granting Organisation Administrator** | Creates somebody who can assign access |
| **Self-granting** a role or a domain entitlement | §13.1 |
| **Granting a Restricted sensitivity ceiling** | The highest data classification there is |

### 11b.2 What it must NOT be

| Forbidden | Why |
| --- | --- |
| **A password screen** | SemantIQ has no passwords. Adding one would invent the credential store the whole identity design exists to avoid |
| **A confirmation dialog labelled "step-up"** | A dialog proves the browser is present, not that the human is. **Do not fake it** |
| **A "recently signed in" flag the application sets itself** | The application would be asserting freshness rather than proving it |

### 11b.3 What it must be

> **A fresh authentication through the trusted Microsoft boundary**, reusing
> P1-00's provider rather than working around it.

DESIGN must specify **three things**, none optional:

| # | |
| --- | --- |
| 1 | **The secure return-to-action mechanism** — how the intended action survives the round trip **without becoming a way to replay or forge one**. The action, its target and its parameters are bound to the request, never carried in a URL somebody can edit |
| 2 | **The freshness proof** — how the application knows the authentication *just* happened, **from the provider's response** rather than from its own state, and what "just" means as a bounded number |
| 3 | **Anti-replay** — a completed step-up authorises **one** action, **once**. Not a window in which everything is privileged |

### 11b.4 It must not weaken P1-00

P1-02 spent a whole unit making the identity path fail closed, and D-31/D-32
settled the session policy. **Step-up must not reopen any of it.**

**If correct Microsoft re-authentication needs a bounded extension to P1-00's
auth path — a `prompt` or `max_age` parameter, a second callback route, a
distinct state store — DESIGN must say so explicitly**, name exactly what
changes, and treat it as a **reviewed change to P1-00** rather than a quiet
addition.

> **Faking step-up is worse than not having it**, because it puts the word into
> a security document while proving nothing, and every later reader will believe
> the control exists.

---

## 12. System Administrator boundary

> **A System Administrator administers the platform and receives ZERO business
> data by virtue of the role.**

| May | May not, by virtue of the role |
| --- | --- |
| Manage identity, SSO, session policy | See Finance, Sales, People or any other domain's data |
| Manage users, groups, organisation structure | Read a record they are not entitled to |
| Manage domains, roles, assignments, entitlements | Query it through the API, Power BI or AI |
| See **metadata**: that a domain exists, that a person is entitled | See the **contents** |

**They may of course be granted business access — as a person, by an explicit
entitlement, exactly like anybody else.** The point is that **the role does not
carry it**, and §30 asserts it behaviourally: a System Administrator with no
entitlement gets **the same answer as a stranger** on every data path.

**This is also the D-49 problem in miniature.** If `RequireSystemAdministrator`
and the engine disagree about who is an administrator, this boundary has two
definitions — which is why §3.4 recommends removing the column.

---

## 13. Organisation Administrator boundary

**Administers the organisation's people, structure and access — and, like the
System Administrator, receives no business data for it.**

| May | May not |
| --- | --- |
| Manage users, groups, teams, hierarchy, domains | See business data without an explicit entitlement |
| Assign roles, entitlements, scopes, ceilings | **Grant the System Administrator platform role — NEVER** |
| See who has access to what | Change Identity & SSO, session policy or platform configuration |

### 13.1 Self-assignment — D-65, approved WITH a security condition

An administrator who can grant themselves Finance has, in effect, Finance.
**It is permitted in Release 1 because the deployment has exactly one System
Administrator**, and refusing it would make the product unusable — but only with
all three of these:

| # | Condition |
| --- | --- |
| 1 | **Explicit confirmation** — the screen states plainly that they are granting themselves access |
| 2 | **A privileged security event**, distinguishable from an ordinary grant |
| 3 | **Fresh re-authentication** under §11b |

> **Correction to the first draft: P1-07 does NOT supply four-eyes approval.**
> The first draft said it did. **P1-07 supplies later review and
> recertification — it is not an approval workflow**, and describing it as one
> would have left a control nobody was building, in a document that made it look
> covered.

### 13.2 An Organisation Administrator may never grant System Administrator

**That is a platform-scoped role and organisation administration does not reach
it.**

This is the single most valuable privilege-escalation guard in the unit:
without it, **organisation administration is platform administration one
assignment away**, and the whole §12/§13 separation collapses in a way that
would look, on any screen, like an ordinary grant. §30 N-B9 breaks it.

### 13.3 Which existing routes become Organisation Administrator's — ENUMERATED, not swapped

P1-01 to P1-04 put **every** administration route behind
`RequireSystemAdministrator`, because that was the only role that existed.

> **DESIGN must enumerate those routes ONE BY ONE and state, for each, whether
> an Organisation Administrator may reach it. Do not replace every
> `RequireSystemAdministrator` blindly.**

| Area | Expected outcome |
| --- | --- |
| **Identity & SSO** (P1-02) | **SYSTEM ADMINISTRATOR ONLY.** Providers, session policy, secrets and health are platform authority |
| Organisation, business units, departments, teams (P1-01) | Expected to open to Organisation Administrator |
| Users & Groups (P1-03) | Expected to open |
| Business Domains (P1-04) | Expected to open |
| Roles & Access (P1-05) | Expected to open — **except** granting the System Administrator role (§13.2) |

**Every row is a decision, and the enumeration is the deliverable.** A
find-and-replace across the route file would hand Identity & SSO to organisation
administration in a single commit that reviewed as trivial — and §30 N-E9
asserts the Identity routes stay System-Administrator-only.

---

## 14. Executive boundary

> **An Executive sees ONLY the domains they are explicitly entitled to.**

*Executive* is the role most likely to be read as *"sees everything"*, and the
blueprint is explicit that it must not be: *"Executive sees cross-functional
KPIs… must not automatically see highly restricted raw fields such as bank,
identity or medical data unless separately authorised."*

| | |
| --- | --- |
| Domains | **Explicit entitlements only.** No implicit set |
| Scope | Typically Organisation **within each entitled domain** — never across un-entitled ones |
| Sensitivity | **A ceiling still applies.** Restricted raw fields remain protected unless separately authorised |

§30 breaks it: *give Executive an implicit entitlement to every enabled domain*.

---

## 15. Domain Owner / Director boundary

> **Being the owner of a domain grants access to NOTHING.**

P1-04 established this and defended it through a whole unit; P1-05 is where it
gets quietly undone, because *"the owner of Finance obviously needs Finance"* is
persuasive and wrong.

| | |
| --- | --- |
| Ownership (P1-04) | **Accountability.** Who answers for the domain |
| The *Domain Owner / Director* **role** | A set of **actions** — and it too carries **no automatic entitlement** |
| To see the domain | An **explicit entitlement**, like anybody else |

**The owner and the role are two different things** and the plan says so
because they will be confused: a person may own Finance without holding the
role, and hold the role without owning anything.

§30 breaks both: *entitle the owner automatically*, and *entitle every holder of
the Domain Owner role to the domains they own*.

---

## 16. Manager hierarchy behaviour — NO RECURSION

**A Manager's Team scope follows P1-01's hierarchy — and that is the ONLY place
management participates.**

| | |
| --- | --- |
| Source | `management_relationships` and `team_memberships`, **read, never rewritten** |
| Grants | **Nothing on its own.** Manager + entitlement + an **explicitly assigned** Team scope grants that team's records **in that domain** |
| Unrelated teams | **Never** — the source case from the Phase 1 document |
| Depth | **NO RECURSION — D-66** |

### D-66, approved: no recursive management chain

> **Manager Team scope is limited to EXPLICITLY ASSIGNED, current team and
> hierarchy relationships.** SYS-005: *managers inherit visibility only for
> explicitly assigned teams and hierarchies.*

**Two inferences are forbidden by name, because both are exactly the sort of
thing that gets written as a convenience:**

| Forbidden | Why |
| --- | --- |
| **Granting a whole team because ONE direct report happens to belong to it** | The manager was never given that team. The report's membership is **their** fact, not the manager's grant |
| **Including reports-of-reports** — the recursive chain | A senior manager's scope would grow **silently** every time somebody is hired three levels below them, with **no assignment change and no review.** Access that expands through hiring is access nobody granted |

**Deeper reach is available and it is deliberate**: assign the team explicitly.
It then appears in the entitlement, in the simulator's explanation and in
P1-07's review. §30 N-B6 and N-M2 break both inferences.

**A management relationship must never grant across domains.** Managing
somebody who works in Finance does not entitle a Sales manager to Finance.

---

## 17. Business User — Own and Team behaviour

| Scope | Means |
| --- | --- |
| **Own** | Only records belonging to them. *A salesperson cannot view another salesperson's records* — the source case |
| **Team** | Records of teams they are a **member** of |

**"Own" needs a definition, and P1-05 must give it — D-67.** There is no
business data yet, so *ownership of a record* has no meaning until Phase 2. The
plan proposes **P1-05 defines the CONTRACT** — *a record is Own if its subject
or assigned user is this identity* — and **Phase 2 supplies the mapping per data
product**. Leaving it undefined until Phase 2 would let Phase 2 invent it, which
is how the engine gets a second implementation.

---

## 18. Auditor behaviour

**An Auditor reads the EVIDENCE about access, not the data.**

| May | May not |
| --- | --- |
| See who holds which role, entitlement, scope, ceiling | See business data without a separate explicit entitlement |
| See when access was granted, changed, revoked, and by whom | Change anything |
| Read security events within the D-12 boundary | Disable, edit or purge audit evidence |

**Auditor is read-only, and that is a property of the role rather than of the
screens.** §30: no write path anywhere accepts an Auditor.

**D-68, approved: Auditor is ORGANISATION-WIDE for access and security
evidence, read-only, with zero automatic business-data entitlement.** A scoped
auditor who cannot see all the evidence cannot do the job the role exists for —
and an auditor who could read business data would be an auditor with something
to audit about themselves.

---

## 19. Same-organisation enforcement

**Every assignment, entitlement, scope and ceiling is within one organisation,
and cross-organisation is refused at every layer.**

| Rule |
| --- |
| A role assignment may only name a user of the **same** organisation |
| An entitlement may only name a domain of the **same** organisation |
| A scope may only name a team or business unit of the **same** organisation |
| The engine **re-checks the organisation** on every decision, from the stored record — not from the session, not from a request field |
| A resource of another organisation is **Not Found**, never *forbidden* — the P1-03/P1-04 shape |

Release 1 is single-tenant, so this is unreachable through the screens — which
is exactly why it is **asserted rather than assumed**. It is also the rule most
likely to be quietly dropped when multi-tenancy arrives.

---

## 20. Inactive user, domain and group effects

| Thing becomes inactive | Effect on access | Effect on the record |
| --- | --- | --- |
| **User deactivated** (P1-03) | **All effective access ends immediately** — §22 | Assignments, entitlements, scopes are **retained**. Deactivation is not revocation |
| **User reactivated** | Access returns to **exactly** what it was — no more | Unchanged |
| **Domain disabled** (P1-04) | Entitlements to it grant **nothing** | **Retained**, never deleted — §11.3 case 4 |
| **Domain re-enabled** | Access returns to exactly what it was | Unchanged |
| **Group deactivated** (P1-03) | **Nothing changes — groups never granted anything.** D-58 | Retained |
| **Team or business unit deactivated** (P1-01) | Scopes resolving through it **narrow**, never widen | Retained |
| **Organisation inactive** | **Everything denies** | Retained |

**Every row narrows.** Not one of these events may widen anybody's access, and
§30 breaks each.

> **The trap in row 1.** *"Deactivated users lose effective access"* is easy to
> implement as *"skip inactive users when listing"* — which is a listing change,
> not an authorization change. It must be a **deny in the engine**, and the test
> must be a **denied request**, not an absent row.

---

## 21. Groups grant nothing — D-58, USERS ONLY

**D-58, approved: access is assigned to USERS ONLY in P1-05.**

| | |
| --- | --- |
| P1-03 groups | **Continue granting nothing.** D-35 unchanged |
| The access engine | **MUST NOT READ `group_memberships` AT ALL** |
| Every role assignment and entitlement | Names a **person** |

**That is a stronger statement than "groups are not used."** The engine must not
read the table at all, so a later convenience cannot quietly become a grant
path. §30 N-B4 asserts it against the engine's source **and** behaviourally.

### Why users only, and why the reasoning is kept visible

The pressure to add groups will return the first time somebody has two hundred
people to assign, so the trade is recorded rather than assumed:

| | |
| --- | --- |
| **Every grant names a person**, so *"why can this person see Finance?"* has exactly one kind of answer | |
| **Group-based assignment brings five requirements of its own** — immediate effect on membership change; no silent rewriting of assignment history; the simulator naming **which group**; a deactivated group granting nothing; and P1-04's *"Super Admin"* naming hazard | **None of them is about the engine, and every one is a way to get the engine wrong while it is still unproven** |

**Group-based access is a separately reviewed future enhancement, after the
engine is proven.** It is not a stretch goal of this unit.

---

## 22. Revocation and immediate-effect expectations

> **Revocation takes effect on the next request. Not when a cache expires.**

| Event | Expectation |
| --- | --- |
| Role assignment revoked | Immediate |
| Entitlement revoked | Immediate |
| Scope narrowed or removed | Immediate |
| Ceiling lowered | Immediate |
| User deactivated | Immediate |
| Domain disabled | Immediate |
| Group membership removed (if D-58 permits) | Immediate |

**D-69, approved: the NEXT authorization decision after the change commits
reflects it.** No stale window at all — and **session expiry is not the
revocation mechanism.** A revoked user is denied on their next request, not at
their next sign-in.

### Caching

| | |
| --- | --- |
| **NO PERMISSION CACHE IN P1-05 — D-69, approved** | Phase 1 has no data volume to justify one, and a cache is the mechanism by which "immediate" quietly becomes "eventually" |
| **If there is** | It must be **invalidated by the write, in the same transaction** — never time-based expiry, and never "usually within a minute". A permission cache with a TTL is a documented window in which revoked access still works |
| **In every case** | **A cold, stale or unavailable cache DENIES and recomputes.** Never serves a permissive default |

**The existing session (D-10: 60-minute idle, 12-hour absolute) must not be the
revocation mechanism.** A revoked user must be denied on their **next request**,
not at their next sign-in — and §30 breaks that with a live session.

---

## 23. Backend and API enforcement

> **Backend authorization is mandatory. UI hiding is never sufficient.** — the
> Phase 1 document, verbatim.

| # | Rule |
| --- | --- |
| 1 | **Every protected route and API endpoint calls the engine.** Enumerated from the route table, not from a hand-written list |
| 2 | **A denied request returns NO PROTECTED PAYLOAD.** Not a filtered one, not an empty shell containing field names — **nothing** |
| 3 | **A denial reveals nothing about existence.** *Not Found* where existence itself is protected, exactly as P1-03 and P1-04 do |
| 4 | **No endpoint trusts a client-supplied role, scope, domain or organisation.** Those come from the stored identity |
| 5 | **The decision happens BEFORE the data is fetched**, not after. Fetching then filtering is one refactor away from a leak, and it is the shape the AI boundary forbids (§25) |
| 6 | **Every new route is protected by default.** An unprotected route must be an explicit, listed exception, and the list is asserted |

---

## 24. UI enforcement versus backend authority

| Layer | Job |
| --- | --- |
| **Backend** | **The control.** Every decision, every time |
| **UI** | **Presentation.** It hides what the user cannot use, so the screen is honest and uncluttered |

**The UI may hide. It may never be the reason something is safe.**

| # | Requirement |
| --- | --- |
| 1 | For every hidden control, **the backend refuses the corresponding request** — asserted by calling it directly |
| 2 | The frontend **never receives data the user may not see**, so that hiding is cosmetic rather than protective |
| 3 | No authorization decision is computed in JavaScript. The frontend renders what the backend already decided |
| 4 | A refusal reaching the screen is a **business sentence**, never a stack trace or a policy identifier |

---

## 25. Fabric, semantic model, Power BI, AI, export and sharing boundary

> **One security model across UI, Fabric, Power BI and AI.** — the blueprint.
> **AI never broadens access.**

### 25.1 The rule P1-05 must make architecturally possible

**The same effective-access decision must apply to every consumer**: UI, API,
Fabric, semantic models, Power BI, AI and agent queries, exports, sharing, and
whatever comes later.

### 25.2 The architecture that is FORBIDDEN

> **Load unrestricted data into the AI layer and filter the answer afterwards.**

Explicitly forbidden by the blueprint, and it must be impossible **by
construction** rather than by policy — because the moment it is merely
discouraged, the first performance problem makes it attractive.

| Forbidden | Why |
| --- | --- |
| A model or agent receiving data the user may not see, then being asked not to reveal it | The data has already left the boundary. Prompting is not authorization |
| A "service account" that reads everything on behalf of users | It **is** the unrestricted load, with a different name |
| Filtering AI output after generation | The generation already saw it |
| A separate permission model configured inside Fabric or Power BI | Two models, and the blueprint forbids exactly this |

### 25.3 What P1-05 must deliver so Phase 2 can honour it

**P1-05 builds no Fabric integration.** It must leave behind:

| # | |
| 1 | **An engine callable outside an HTTP request** — Phase 2's propagation is not a web request |
| 2 | **An effective-access description Phase 2 can PROJECT** into row-level and object-level security, rather than re-deriving |
| 3 | **A stable identity key** for that projection — P1-00's `(provider, external_subject, tenant_id)`, never email |
| 4 | **A written statement that Phase 2 DERIVES and never restates.** If Phase 2 needs a rule this engine cannot express, that is a change **here**, not a second model there |

**D-70, approved: define the projection CONTRACT now; implement its adapter in
Phase 2.** And **P1-05 must expose an engine usable outside an HTTP request** —
Phase 2's propagation is not a web request, and an engine reachable only through
middleware would force Phase 2 to build its own.

---

## 26. Schema proposal

**Proposed only. Nothing is written until DESIGN is approved.**

| Table | Purpose |
| --- | --- |
| ~~`roles`~~ | **NOT CREATED — §3.6.** An immutable product catalogue instead, because with D-51/52/53/54 answered **nothing about a role is manageable** |
| `role_assignments` | Who holds which `role_code`, in which organisation (**NULLABLE for `system_administrator`** — §4.1), and when. **History, never rewritten** |
| `domain_entitlements` | Who may see which domain — **a child of a role-assignment period**, §4.4 |
| `entitlement_scopes` | The scope on an entitlement, **with its explicit structural target** (§6.1), with history |
| `entitlement_ceilings` | **One per entitlement**, D-60. Defaults to Standard |

**Every child carries its parent's period**, so ending a parent ends its
children transactionally (§4.4), and a later re-grant creates new rows rather
than reviving old ones.

**Shape rules carried from every earlier unit, applied here rather than
rediscovered:**

| # | Rule | Where it was learned |
| --- | --- | --- |
| 1 | **DATETIME** for every effective-from/until. **No uniqueness on the start** | P1-01's collision → P1-03's correction → production reproduced it on day one |
| 2 | **One current record per subject**, held by a **locking read on the PARENT row** inside the write transaction | P1-04's correction — the parent is the boundary because decisions are taken over more than one table |
| 3 | **Immutable codes** on roles, exactly as on domains | P1-03's `external_subject`, P1-04's domain code |
| 4 | **No row is ever deleted.** Revocation sets an end | P1-03 memberships, P1-04 ownership |
| 5 | **Refusals are business sentences**; the constraint is still the real guard | P1-03 shipped an integrity error at an administrator |
| 6 | **Identifier names within MySQL's 64 characters** | Asserted already |

**Columns that must NOT exist**, asserted physically as in P1-03 and P1-04:
anything that caches an effective-access answer, any `is_admin`, any
`can_see_everything`, any denormalised "effective" column. **The engine
computes; the schema stores assignments.**

---

## 27. Security event and audit boundary

**Every privileged change is a security event.** The Phase 1 document requires
*"permission change creates audit evidence"*, and the blueprint requires audit
that ordinary administrators cannot disable.

| Event family | Covers |
| --- | --- |
| `role_assignment.*` | granted, revoked |
| `domain_entitlement.*` | granted, revoked |
| `entitlement_scope.*` | set, changed, removed |
| `entitlement_ceiling.*` | set, changed, cleared |
| `access.self_granted` | The D-65 self-assignment — **distinguishable from an ordinary grant** |
| `access.step_up.*` | required, completed, refused — §11b |
| `access.denied.privileged` | **D-71**, privileged surfaces only |

### D-72, approved — four new context keys, and `role` is a CODE

| Key | Carries |
| --- | --- |
| **`role`** | **One of the seven fixed role codes. NEVER free text** |
| **`domain_id`** | The domain's identifier |
| **`scope`** | The scope value |
| **`sensitivity`** | The ceiling value |

**`related_id` continues to carry structural target references** where it is
sufficient, rather than a fifth key being added for each one.

> **`role` being a CODE rather than a name is the whole reason it is safe.**
> The logger has **no free-text channel at all** — deliberately, since P1-01 —
> and that principle is preserved: **no names, no emails, no descriptions, no
> business content, no arbitrary reason text.** A key that accepted free text
> would be the leak channel the D-12 boundary exists to remove, and at the call
> site it would look identical to these four.

**Each of the four is validated on write**, so `role` cannot receive an
invented string, and §30 N-EV1 breaks that.

### D-71, refined — log PRIVILEGED-SURFACE denials only

| | |
| --- | --- |
| **Logged** | Denials on **privileged surfaces** — administration, access management, step-up |
| **Not logged** | **Routine business denials.** They would flood the security log and bury what matters |
| **NOT BUILT IN THIS UNIT** | **A repeated-denial detector.** It needs aggregation, a window and state, and belongs with **P1-06 / P1-08 monitoring** |

**The refinement matters.** The first draft said *"privileged and repeated
denials"*, which reads as one decision and is two: the first is a filter at the
call site; the second is a **stateful detector** that would have been built here
by implication — badly, with window semantics nobody had agreed.

**P1-08 owns durable audit storage.** P1-05 emits through the existing boundary.

---

## 28. Search, filter and administrative usability

**An access model nobody can inspect is one nobody can review.**

| Screen | Must answer |
| --- | --- |
| **By person** | *"What can this person see, and why?"* |
| **By domain** | *"Who can see Finance?"* — **P1-07's central question**, and it must be answerable here |
| **By role** | *"Who is an Executive?"* |
| **Unassigned** | *"Who has no access at all?"* — often the real question |
| **Needs attention** | Entitlements to disabled domains; assignments held by inactive users |

Filters combine, survive pagination, and carry in the query string — the P1-04
pattern. Empty-because-none and empty-because-filtered say **different things**;
P1-03 shipped that defect and P1-04 asserted against it.

**Volume is a real concern here** — every person × every domain — and it must be
*stressed* in the suite against seeded volume, not just *exercised*.

---

## 29. Acceptance criteria

P1-05 is complete when:

| # | Criterion |
| --- | --- |
| 1 | Reach **Roles & Access** and see the **seven roles read-only**, each with the actions it permits (§3.3) |
| 2 | Assign a role; see it in history; **revoke** it and see the history retained |
| 3 | Grant a **domain entitlement**, set a **scope with an explicit target**, set a **ceiling** |
| 4 | Use the **Access Simulator** to see current access, proposed access, **and which grant path produced each answer** |
| 5 | A **System Administrator with no entitlement can see no business data** |
| 6 | A **Domain Owner receives nothing automatically** |
| 7 | **Disable a domain and observe access disappear**; re-enable and observe it return **unchanged** |
| 8 | **Disable every domain and observe that nothing is granted** — not everything |
| 9 | **Revoke access and observe it stop on the next request**, without signing out |
| 10 | **Deactivate a user and observe access end**, with assignments **retained** |
| 11 | **Revoke a role assignment, re-assign the same role, and observe that the old entitlements DO NOT return** — §4.4 |
| 12 | **Be required to re-authenticate** before granting System Administrator, Organisation Administrator, a self-grant, or a Restricted ceiling — §11b |
| 13 | An **Organisation Administrator cannot grant System Administrator**, and **cannot reach Identity & SSO** |
| 14 | Be refused every operation §12–§19 forbid, in business language |
| 15 | See the §30 matrix pass, every guard proven non-vacuous |
| 16 | Read every screen in both themes and at small width, with no implementation wording |
| 17 | **`users.platform_role` is gone**, and there is demonstrably **one** authorization model — §3.4 |
| 18 | **Bootstrap still produces a working first administrator on an empty deployment** — with no organisation |

### Gates

| Gate | Requirement |
| --- | --- |
| **P1-04 — a disabled domain never broadens access** | **MUST BE CLOSED HERE.** All five cases of §11.3, especially **no enabled domains = no domain access, never allow-all** |
| **P1-02 — provider-wide SSO Re-check lock** | **Do not manufacture a second System Administrator.** If a **genuine** one is legitimately assigned during Product Owner testing, use them to close the live observation. **Otherwise carry it forward honestly** and say so |

---

## 30. Negative and mutation cases

Every guard broken deliberately and observed to fail — `CLAUDE.md` §2. Each
mutation is the one **a person who misunderstood the rule would plausibly
write**, not the one easiest to make fail.

### The mandatory matrix — every scenario the Product Owner named

| # | Scenario | Required outcome |
| --- | --- | --- |
| **M1** | Sales user requests Finance | **DENY** |
| **M2** | Finance user requests People | **DENY** |
| **M3** | Sales + **Own** requests another salesperson's record | **DENY** |
| **M4** | Manager + **Team** requests their assigned team | **ALLOW**. Unrelated team → **DENY** |
| **M5** | Executive requests an **un-entitled** domain | **DENY**. Entitled → allow |
| **M6** | Any user requests a **Restricted** field while entitled to the domain | **DENY or redact** — the ceiling holds |
| **M7** | **Domain Owner** requests their own domain with no entitlement | **DENY** |
| **M8** | **System Administrator** requests business data with no entitlement | **DENY** — and can still administer |
| **M9** | Entitled domain is **disabled** | **DENY** |
| **M10** | **No domains enabled** | **DENY** — never allow-all |
| **M11** | **Inactive user** with full assignments | **DENY** |
| **M12** | Unknown or conflicting assignment | **DENY** — fail closed |
| **M13** | Denied **API** request | **No protected payload of any kind** |
| **M14** | **AI/agent** query | **Exactly** the requesting user's effective access — never more |
| **M15** | **Revocation** during a live session | Denied on the **next request**, no cache wait |
| **M16** | A manager whose **one direct report** belongs to a team they were **not assigned** | **DENY** for that team — D-66 |
| **M17** | A **reports-of-reports** record, no explicit assignment | **DENY** — no recursion |
| **M18** | An **Organisation Administrator** granting System Administrator | **REFUSED** |
| **M19** | A role revoked, then **re-assigned later** | Old entitlements **do not return** |
| **M20** | A privileged grant **without** fresh re-authentication | **REFUSED** until step-up completes |

### The boundary

| # | Guard | Mutation |
| --- | --- | --- |
| N-B1 | System Administrator gets **no** business data from the role | `if (isSystemAdministrator()) return true` in the engine |
| N-B2 | Domain **ownership** grants nothing | Auto-create an entitlement on owner assignment |
| N-B3 | The **Domain Owner role** grants nothing | Entitle every holder to the domains they own |
| N-B4 | **Group membership** grants nothing (unless D-58) | Read `group_memberships` in the engine |
| N-B5 | **Business Unit** membership grants nothing on its own | Widen scope from the unit alone |
| N-B6 | **Management** grants nothing outside Team scope | Let a manager read a report's other-domain records |
| N-B7 | **ONE engine.** No second implementation anywhere | Give the simulator its own copy; add a "quick check" helper |
| N-B8 | **ONE definition** of "is a System Administrator" | Leave `users.platform_role` readable beside the assignment table |
| **N-B9** | **An Organisation Administrator can NEVER grant System Administrator** | Permit it. **The single most valuable privilege-escalation guard in the unit** |
| N-B10 | Administration actions grant **no** business rows, for every one of the seven roles | Let any administration role satisfy a business-data path |

### Deny by default and the P1-04 gate

| # | Guard | Mutation |
| --- | --- | --- |
| N-D1 | No grant → deny | Default the result to allow and let checks subtract |
| N-D2 | Engine exception → **deny** | `catch { return true }` |
| N-D3 | **One** domain disabled → only that one is lost | Skip the enabled check |
| N-D4 | **All** domains disabled → nothing granted | The empty-set filter |
| N-D5 | **No enabled domains** → nothing granted | `if (domains.isEmpty()) return allow` — **the gate, as one line** |
| N-D6 | Entitlement to a disabled domain is **retained** | Delete it on disable |
| N-D7 | **Re-enable** restores exactly the prior access | Restore to a default instead |
| N-D8 | **Missing scope → nothing**, never a default | `scope ?? Scope::Organisation` |
| N-D9 | Unrecognised stored value → **deny** and log | Fall through to allow |
| N-D10 | Inactive user → **denied request**, not merely an absent row | Filter listings only |

### Precedence

| # | Guard | Mutation |
| --- | --- | --- |
| N-P1 | **A complete active path allows; an incomplete one does not contribute** | Let a path missing its scope still authorise |
| N-P2 | **A ceiling caps ITS OWN path only** | Make the lowest ceiling anywhere a global maximum — the first draft's rule |
| **N-P3** | **A REVOKED ROW IS NOT A DENY.** It stops contributing; it never subtracts from a separate active path | Let a revoked row veto an active grant — which would make it an **invisible deny record**, and D-64 rejected those |
| N-P4 | A **restrictive** second grant never reduces access an earlier valid grant gave | Intersect the paths instead of evaluating them independently |
| N-P5 | Scope never widens the domain | Let Organisation scope reach outside the entitled domain |
| N-P6 | **A global gate cannot be outvoted by any path** | Evaluate paths first and let one satisfy an inactive user |

### Enforcement

| # | Guard | Mutation |
| --- | --- | --- |
| N-E1 | **Every** protected route calls the engine | Drop one |
| N-E2 | A denial returns **no payload** | Return an empty object with the field names |
| N-E3 | The decision precedes the fetch | Fetch, then filter |
| N-E4 | No client-supplied authorization input is trusted | Accept a `scope` parameter |
| N-E5 | Hiding a control is matched by a **backend refusal** | Hide it and leave the route open |
| N-E6 | No authorization in JavaScript | Compute one in the frontend |
| N-E7 | **Auditor writes nothing** | Let an Auditor through one write path |
| N-E8 | Cross-organisation is **Not Found** | Return 403 |
| **N-E9** | **Identity & SSO stays System-Administrator-only** after §13.3's route migration | Open it to Organisation Administrator — the find-and-replace outcome |
| N-E10 | Every route opened to Organisation Administrator is on the **enumerated** list | Open one that is not |

### Lifecycle and history

| # | Guard | Mutation |
| --- | --- | --- |
| N-L1 | No route deletes an assignment, entitlement, scope or ceiling | Add one |
| N-L2 | Replace is **one transaction** and never passes through an ungranted state | Revoke, then grant |
| N-L3 | Two assignments **on one calendar day** are both recorded | Date-valued timing — the P1-01 collision |
| N-L4 | Role **codes** are immutable | Make them fillable |
| N-L5 | **Every operation named in §3–§7 exists** | Delete one — the P1-01 presence guard |
| N-L6 | Every write **confirms itself** | Bare redirect |
| N-L7 | The `platform_role` migration is **reversible and leaves the administrator an administrator** | Roll back and check. **MySQL** |
| N-L8 | **No schema column caches an effective-access answer** | Add `effective_domains` |
| **N-L9** | **Re-assigning a revoked role does NOT resurrect its old entitlements** — §4.4 | Leave entitlements orphaned rather than ending them with their parent |
| N-L10 | Re-granting a revoked entitlement does not resurrect its old scope or ceiling | As above, one level down |
| N-L11 | **Deactivate → reactivate a user returns access EXACTLY**, no more and no less | End the assignments on deactivation |
| N-L12 | **Disable → re-enable a domain returns access EXACTLY** | Delete the entitlements on disable |
| **N-L13** | **A `system_administrator` assignment is permitted with NO organisation**; every other role is **refused** without one | Require an organisation on all of them — **bootstrap then cannot create the first administrator on an empty deployment** |
| N-L14 | **Bootstrap creates the user and the assignment in ONE transaction** | Create the user, then assign separately — a window in which the deployment has a user who is nobody |
| **N-L15** | **The last active System Administrator cannot be REVOKED away**, and two concurrent revocations cannot reach zero | Drop the guard; move the locking read outside the transaction. **MySQL** |

### Step-up, events and the catalogue

| # | Guard | Mutation |
| --- | --- | --- |
| N-S1 | **Each of the five privileged actions requires fresh re-authentication** | Drop one |
| N-S2 | **A completed step-up authorises ONE action, once** | Make it a time window |
| N-S3 | The freshness proof comes **from the provider**, not from application state | Set a local flag |
| N-S4 | **The step-up return does not weaken P1-00** — state, nonce and tenant checks unchanged | Relax one for the return path |
| N-EV1 | The `role` context key accepts **only the seven codes** | Pass free text |
| N-EV2 | **No free-text key exists** on the logger | Add `reason` |
| N-EV3 | A **self-grant** logs a distinguishable privileged event | Log it as an ordinary grant |
| N-EV4 | **Routine business denials are NOT logged** | Log every denial |
| N-C1 | The role catalogue holds **exactly seven** codes, and none can be renamed | Add an eighth; make the name mutable — *"Super Admin"* |
| N-C2 | **No `roles` table exists** | Create one |

---

## 31. Product Owner decisions — ANSWERED, 3 September 2026

**All twenty-four were answered at PLAN review, plus one new decision the review
added and one this plan raises in return.** Each is now a decision of record and
DESIGN is bound by it.

### Answered as recommended

| # | Decision | **Answer** |
| --- | --- | --- |
| **D-49** | The `platform_role` seam | **Assignments are the single source of truth. Migrate the existing System Administrator; update Bootstrap, `BootstrapState`, `RequireSystemAdministrator`, the last-admin guard and every admin check to one authority; REMOVE the column; leave no compatibility column.** Plus the bootstrap correction of §3.4.1 |
| **D-50** | One unit or split | **One unit, internally staged: engine → enforcement → administration UI → simulator → verification** |
| **D-51** | Custom roles | **No. Baseline seven only** |
| **D-53** | Editable role capabilities | **No — fixed in Release 1.** And DESIGN must produce **one explicit role → action catalogue** (§3.3) |
| **D-54** | Deactivating a role | **No** |
| **D-55** | Several roles at once | **Yes** |
| **D-56** | Effective dating | **No.** Begins on commit, ends on revoke |
| **D-57** | Implicit entitlements | **None. Always explicit** |
| **D-58** | Groups | **USERS ONLY.** Groups keep granting nothing, and **the engine must not read group membership** |
| **D-59** | Scope per entitlement | **Yes** — with **explicit structural targets** (§6.1) and the honest Domain/Organisation answer (§6.2) |
| **D-61** | `access_expectation` | **Context only. The engine never reads it** |
| **D-64** | Explicit deny records | **No** |
| **D-65** | Self-assignment | **Permitted, with confirmation + a privileged event + step-up.** And **P1-07 does not supply four-eyes** |
| **D-66** | Manager depth | **No recursion. Explicitly assigned teams and hierarchies only** |
| **D-67** | What "Own" means | **P1-05 defines the contract; Phase 2 implements it, and must not reinterpret it** |
| **D-68** | Auditor | **Organisation-wide for evidence, read-only, zero business entitlement** |
| **D-69** | "Immediate" | **The next decision after commit. No permission cache. Session expiry is not the mechanism** |
| **D-70** | Phase 2 projection | **Define the contract now, implement the adapter in Phase 2.** The engine must be usable outside HTTP |

### Answered DIFFERENTLY from the recommendation — and each changed the plan

| # | Recommended | **Decided** | Why the decision is right |
| --- | --- | --- | --- |
| **D-52** | Display name editable, code immutable | **NOT EDITABLE** | A domain name is the organisation's word for its business; **a role name is security vocabulary**. Renaming *Business User* to *"Super Admin"* changes what every administrator believes they are granting — and P1-03 produced that exact hazard on day one |
| **D-60** | Ceiling per person **and** per entitlement, lowest wins | **PER ENTITLEMENT ONLY** | Two independent cap paths create a confusing double-grant requirement, where an entitlement raised to *Confidential* stays capped by an invisible person-level *Standard* the screen cannot explain |
| **D-62** | Widest scope, lowest ceiling, across all grants | **INDEPENDENT COMPLETE GRANT PATHS** | The old rule combined **halves of unrelated decisions** into an effective access nobody assigned. §10.4 |
| **D-63** | Redact above the ceiling | **DENY at the engine.** Phase 2 consumers may omit, and **must indicate content was withheld** | P1-05 has no business data to redact; a generic redaction engine would be designed against an imagined shape. **No silent redaction** |
| **D-71** | Privileged **and repeated** denials | **PRIVILEGED SURFACES ONLY. No repeated-denial detector in this unit** | "Repeated" is a **stateful detector** needing aggregation and a window — it would have been built here by implication, badly |
| **D-72** | Approve keys individually | **`role` (a fixed CODE, never free text), `domain_id`, `scope`, `sensitivity`.** `related_id` continues for targets | The logger has **no free-text channel** and that principle is preserved |

### New, added by the review

| # | Decision | **Answer** |
| --- | --- | --- |
| **D-73** | **Privileged step-up re-authentication — SYS-018** | **Required before granting/revoking System Administrator, granting Organisation Administrator, self-granting, and granting Restricted.** Fresh **Microsoft** re-authentication — **no password screen, and no dialog pretending to be step-up.** A bounded P1-00 extension must be documented explicitly if one is needed. §11b |

> **D-73 is a gap this plan had and the review caught.** SYS-018 is in the
> Ground-Zero source, P1-05 is the first unit that can actually grant privilege,
> and the first draft did not mention it once.

### Raised in return, by this plan

| # | Decision | Options | Plan recommends |
| --- | --- | --- | --- |
| **D-74** | **`Domain` and `Organisation` scope resolve to the SAME record set** under Release 1's single organisation, with the entitlement already naming a domain (§6.2). D-59 forbids two labels that silently mean one thing — so which is delivered? | (a) `Organisation` only; (b) **both, documented as equivalent today**; (c) **`Domain` only**, deferring `Organisation` until it can differ | **(c)** — the entitlement is domain-shaped, so `Domain` is the honest name for *all of it*. Whichever is chosen, **the reason is recorded**, or the next reader re-derives the equivalence and assumes it was an oversight |

---

## 32. What this plan deliberately does not build

- **No Fabric integration, no semantic model, no Power BI RLS/OLS.** Phase 2 — and it **derives** from this engine (§25), never restates it.
- **No AI surface.** Phase 3 — but the boundary it must respect is defined here.
- **No access reviews or recertification.** P1-07 — **and P1-07 is not an approval workflow**, so nothing here may depend on it as one.
- **No durable audit table**, and **no repeated-denial detector.** P1-06 / P1-08.
- **No business data.** There is none.
- **No `roles` table** — §3.6.
- **No custom roles, no role rename, no editable capabilities, no role deactivation** — D-51, D-52, D-53, D-54.
- **No effective dating** — D-56.
- **No explicit deny records** — D-64. And **no revoked row may become one**, §10.3.
- **No group-based assignment**, and **the engine does not read group membership** — D-58.
- **No permission cache** — D-69.
- **No redaction engine** — D-63. Deny at the engine; presentation is Phase 2's, and it must be visible.
- **No person-level sensitivity ceiling** — D-60.
- **No password screen, and no dialog pretending to be step-up** — D-73.
- **No compatibility column left behind** — D-49.
- **No navigation or branding change.** *Roles & Access* moves from `locked()` to `leaf()`.
- **No fake privileged account.** The P1-02 gate closes only if a **real** second System Administrator is legitimately established during testing; otherwise it is carried forward and said so.
- **No change to the `srikanth@lithan.com` record**, and none to the `software` domain — both open operational items recorded in P1-03 and P1-04 verification.

---

**P1-05 PLAN — APPROVED 3 September 2026** with D-49 to D-73 answered (§31),
six decisions changed from the recommendation, and **D-73 added by the review**.
**One decision is raised in return — D-74**, §6.2.

**Next: DESIGN.** No implementation, schema, migration, engine, role assignment
or production privilege change until it is approved.
