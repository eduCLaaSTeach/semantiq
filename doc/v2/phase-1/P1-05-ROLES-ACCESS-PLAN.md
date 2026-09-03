# P1-05 — Roles & Access: PLAN

**PLAN ONLY.** No design, no schema written, no migration, no route, no
controller, no service, no screen, no access engine, no role, no assignment, no
production privilege change. §31 lists the decisions the Product Owner must make
before a DESIGN can be written.

Source of scope: `doc/SemantIQ_v2_PHASE_1_System_Administration.md` → **P1-05 —
Roles & Access**, and `doc/SemantIQ_v2.2_Ground_Zero_Architecture_Reset_Three_Phase_Blueprint.md` §2.

| | |
| --- | --- |
| Preceding units | P1-00 · P1-01 · P1-02 · P1-03 · P1-04, all **ACCEPTED** |
| Gates carried into this unit | **2** — §31.13 and §11 |
| Status | **Awaiting Product Owner review** |

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
| Being somebody's **manager** | **Nothing** on its own. It participates **only** in Team scope, and only where §16 says |
| Being in a **group** | **Nothing** — P1-03, D-35. Whether that changes at all is **D-58**, §31, and it must not be assumed |
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
| 2 | **Roles** — the seven baseline, their lifecycle, whether custom roles exist (**D-51**) |
| 3 | **Role assignments** — a person holds a role, §4 |
| 4 | **Domain entitlements** — a person may see a domain, §5 |
| 5 | **Scope assignments** — which records inside it, §6 |
| 6 | **Sensitivity ceilings** — which fields and actions, §7 |
| 7 | **The Access Simulator** — preview, §8 |
| 8 | **Deny by default**, and failing closed on anything unknown or conflicting, §11 |
| 9 | **Backend enforcement** on every protected route and API, §23 |
| 10 | **Resolution of the `users.platform_role` seam**, §3.4 — P1-05 owns it |
| 11 | **Revocation with immediate effect**, §22 |
| 12 | Security events for every privileged change, §27 |
| 13 | **The P1-04 carried gate**, proven — §11.3 |
| 14 | Search, filter and administrative usability at real volume, §28 |

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

| Operation | Baseline role | Custom role (if D-51 permits) |
| --- | --- | --- |
| **Create** | **NO** — the seven are the product's vocabulary | Yes, if D-51 permits |
| **Read** — list, one | Yes | Yes |
| **Change display name** | **D-52** | Yes |
| **Change description** | Yes | Yes |
| **Change what the role permits** | **D-53** — the most dangerous operation in the unit | D-53 |
| **Activate / Deactivate** | **D-54** — and what happens to live assignments | D-54 |
| **Assign** | §4 | §4 |
| **Replace / Revoke** | §4 | §4 |
| **Retain history** | **Always.** Never rewritten | Always |
| **Purge** | **NO** for baseline. **Never**, once it has ever been assigned | Only if never assigned — §3.5 |
| **Change its stable code** | **NO, ever** — §3.3 | **NO, ever** |

### 3.3 A role has an immutable code, exactly as a domain does

`system_administrator`, `organisation_administrator`, `executive`,
`domain_owner`, `manager`, `business_user`, `auditor`.

**Immutable, for the same reason P1-04's domain code is:** every assignment,
every audit line and every Phase 2 security mapping will join to it, and a
mutable identity silently retargets all of them. The display name is the
organisation's word; the code is SemantIQ's.

### 3.4 THE `users.platform_role` SEAM — P1-05 owns resolving it

P1-00 introduced `users.platform_role` with exactly one value,
`system_administrator`, and `PlatformRole` has one case with a test asserting it
stays that way. Its own docblock says **"P1-05 OWNS REPLACING THIS."**

> **The instruction this plan takes seriously: do not casually extend the column
> with seven values.**

Adding six cases to that enum is the smallest diff and the worst outcome,
because it produces **two competing authorization models** — a column that
`RequireSystemAdministrator` reads, and an assignment table that the engine
reads — with nothing saying which wins when they disagree. They *will* disagree:
the first time somebody's role assignment is revoked and the column is not
updated, or vice versa.

**Three options, and this plan recommends the third. D-49, §31.**

| | Option | For | Against |
| --- | --- | --- | --- |
| **A** | **Extend the enum to seven values.** The column is the role | Smallest change; no new join for the common check | **A person can hold only ONE role**, which the model does not say. No history, no effective dates, no scope. And every later question ("who is a Manager for which team?") has nowhere to go |
| **B** | **Keep the column for platform administration; add assignments for everything else** | `RequireSystemAdministrator` is untouched | **This is the two-models outcome, made permanent.** Two places answer "what is this person", and the first divergence is a security incident nobody can diagnose |
| **C — recommended** | **The assignment table becomes the single source of truth. The column is migrated into it and then REMOVED, in this unit** | **One model.** History, effective dates and scope all have somewhere to live. `RequireSystemAdministrator` asks the engine, and asking it is the only way to answer | The migration must be exactly right, and it must not lock the only administrator out. §3.4.1 |

#### 3.4.1 What option C must prove before it is safe

| # | Requirement |
| --- | --- |
| 1 | **Every existing `platform_role` value becomes a role assignment**, in the same migration, with its own effective-from |
| 2 | **The column is dropped in that same unit**, not left behind "for now". A dead column that once meant something is the second model wearing a disguise |
| 3 | **`RequireSystemAdministrator` reads the engine**, and there is exactly one implementation of "is this person a System Administrator" in the codebase — asserted (§30) |
| 4 | **A migrate → rollback → migrate cycle leaves the administrator still an administrator.** Proven on MySQL, because that is the day it matters |
| 5 | **Bootstrap is unaffected.** P1-00's grant path must still produce a working first administrator on an empty deployment |
| 6 | **Production has exactly one row to migrate** — confirmed by the read-only verification, which already reports `platform_roles_total` |

**If any of those cannot be proven, option B is the honest fallback** — but it
must then be written down as a *known duplicate model* with an owner and a date,
not left implicit.

### 3.5 Purge

A role that has **ever** been assigned can never be permanently removed —
history is evidence. `PurgeDependencies` gives that for free once assignments
carry a foreign key, and it is stated explicitly as well, exactly as P1-04 does.

---

## 4. Role Assignment lifecycle

**A role assignment is: this person holds this role, from this moment, until
this moment.**

| Operation | Available | Notes |
| --- | --- | --- |
| **Create / Assign** | Yes | To a **person**. To a **group** only if D-58 permits — §21 |
| **Read** | Yes | Per person, and per role |
| **Change** the person or the role on an existing assignment | **NO** | That is a revoke plus an assign, and it must look like two events, because it *is* two |
| **Replace** | Yes | Revoke the current, create the next, **in one transaction** — the P1-04 pattern |
| **Revoke** | Yes | Ends the assignment. **Takes effect immediately** — §22 |
| **Retain history** | **Always.** Never deleted, never updated in place | |
| **Activate / Deactivate the assignment** | **NO** | A third state between assigned and revoked is a state every query must remember to exclude. Revoke and re-assign |
| **Purge** | **NO route** | An assignment that existed is evidence that somebody had access |

**May one person hold several roles at once? D-55, §31.** The plan recommends
**yes**, because a real Finance Manager is plausibly *Manager* and *Business
User*, and forbidding it forces one role to absorb the other's meaning. §10 then
has to define precedence, which is why the two decisions are linked.

**Effective dating — D-56.** Whether an assignment may be scheduled to begin or
end in the future. The plan recommends **not in P1-05**: future-dated access is
a real requirement and a large one, and adding it here makes every query
time-dependent and every test harder to trust.

---

## 5. Domain Entitlement lifecycle

**An entitlement is: this person, in this role, may see this domain.**

| Operation | Available | Notes |
| --- | --- | --- |
| **Grant** | Yes | Person (or group, D-58) × domain |
| **Read** | Yes | By person, and by domain — *"who can see Finance"* is P1-07's question and must be answerable here |
| **Change** the domain on an existing entitlement | **NO** | Revoke and grant |
| **Revoke** | Yes | Immediate — §22 |
| **Retain history** | **Always** | |
| **Purge** | **NO route** | |

**An entitlement to a DISABLED domain grants nothing, and is not deleted** —
§11.3, and the P1-04 carried gate. Disabling is the organisation saying *"we are
not using this"*; it is not a revocation and must not silently destroy the
administrator's assignment work.

**No entitlement is ever created automatically.** Not by ownership, not by
domain creation, not by role assignment, not by being in a business unit. **D-57
asks whether ANY role carries an implicit entitlement**, and the plan recommends
**no — every entitlement is explicit**, because an implicit one is invisible in
the very screen built to make access reviewable.

---

## 6. Scope Assignment lifecycle

**Scope answers: WHICH RECORDS inside a domain the person may see.**

| Scope | Means | Derived from |
| --- | --- | --- |
| **Own** | Records belonging to that person | Identity |
| **Team** | Records of the teams they are in — and, for a Manager, the teams they manage (§16) | P1-01 `team_memberships`, `management_relationships` |
| **Business Unit** | Records within their business unit | P1-01 `business_units` → departments → teams |
| **Domain** | All records in the entitled domain | The entitlement itself |
| **Organisation** | Everything in the organisation, within the domain and ceiling | The organisation |

**Scope is per entitlement, not per person.** *Sales + Team* and *Finance +
Own* must be expressible for one person at once; a single scope per user cannot
represent a real organisation. **D-59** confirms this.

| Operation | Available |
| --- | --- |
| **Assign** — a scope to an entitlement | Yes |
| **Change** | **Yes** — this is a *change of degree*, not of subject, so unlike §4 and §5 it may be edited in place, **with the previous value retained as history** |
| **Revoke** | Yes — the entitlement then grants nothing, rather than defaulting to something wider |
| **Retain history** | Always |
| **Purge** | **NO route** |

> **The trap.** Removing a scope must **narrow to nothing**, never fall back to
> a default. A missing scope that is read as *"no restriction"* is the same
> class of defect as the P1-04 disabled-domain gate, and §30 breaks it.

**Scope never widens the domain.** *Organisation* scope on a Sales entitlement
means *all Sales records in the organisation* — **not** all records in the
organisation. §30 N-S4.

---

## 7. Sensitivity Ceiling lifecycle

**A ceiling answers: which FIELDS and ACTIONS, within what they can already
see.**

| Level | Means |
| --- | --- |
| **Standard** | Ordinary business fields |
| **Confidential** | Sensitive business fields — commercially or personally significant |
| **Restricted** | Bank, identity, medical and equivalent. Exceptional, and reviewed |

**A ceiling only ever REDUCES.** It is a maximum, never a grant: holding a
Restricted ceiling gives access to nothing by itself. **The lowest applicable
ceiling wins** — §10.

| Operation | Available |
| --- | --- |
| **Set** — per person, or per entitlement (**D-60**) | Yes |
| **Change** | Yes, retained as history |
| **Clear** | Yes → falls back to the **most restrictive** default, never the most permissive |
| **Retain history** | Always |
| **Purge** | **NO route** |

**Default when nothing is set: Standard.** Stated so nobody has to infer it,
and asserted (§30).

> **P1-04 deliberately shipped no sensitivity of any kind — D-47.** The
> `access_expectation` field it does carry (`undecided` / `broad` / `limited` /
> `exceptional`) is **advisory and must remain so.** **D-61** asks whether P1-05
> reads it at all; the plan recommends **it is shown to the administrator as
> context and never read by the engine**, because the moment it is enforced,
> P1-04 has retrospectively become an access-control unit.

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

## 10. Conflict and precedence rules

**Real people hold several assignments. The rules must be stated, not
discovered.**

| Dimension | Proposed rule | Why |
| --- | --- | --- |
| **Two roles** | The **union of ACTIONS** | A Manager who is also a Business User can do both jobs. Actions are not dangerous on their own — data is |
| **Two entitlements to the same domain** | The **widest SCOPE** of the two | Two grants for the same domain express the same decision twice |
| **Two entitlements, different domains** | Independent. **Never merged** | Sales + Finance is not "Sales and Finance data mixed" |
| **Two scopes** | The **widest** | Same reasoning as above |
| **Two ceilings** | **THE LOWEST. Always.** | A ceiling is a maximum. Taking the higher of two would let a second assignment *raise* a cap, which is the opposite of a cap |
| **A grant and a revocation** | **The revocation wins, always** | §22 |
| **Anything ambiguous, unrecognised or contradictory** | **DENY** | §11 |

> **The asymmetry is deliberate and is the heart of §10.** Scopes take the
> **widest**; ceilings take the **LOWEST**. Applying one rule uniformly to both
> — the obvious simplification — silently raises every ceiling the moment a
> person holds two assignments. **D-62** confirms it; §30 breaks it.

**Explicit deny records — D-64.** Whether an administrator may record *"this
person must NOT see X"* as a first-class object. The plan recommends **not in
P1-05**: deny records interact with everything above and are a large design in
their own right, and absence of a grant already denies.

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
| Manage users, groups, teams, hierarchy | See business data without an explicit entitlement |
| Assign roles, entitlements, scopes | Assign themselves something they could not otherwise have — **D-65, §31** |
| See who has access to what | Change identity/SSO, session policy or platform configuration |

> **D-65 is the self-assignment question and it is not decorative.** An
> administrator who can grant themselves Finance has, in effect, Finance. Three
> answers: permit it and log it prominently; refuse it and require a second
> administrator; or permit it only for roles they already hold. The plan
> recommends **permit-and-log for P1-05**, and flags **four-eyes as P1-07's**
> — because refusing it in a single-administrator deployment makes the product
> unusable, and P1-07 owns recertification.

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

## 16. Manager hierarchy behaviour

**A Manager's Team scope follows P1-01's hierarchy — and that is the ONLY place
management participates.**

| | |
| --- | --- |
| Source | `management_relationships` and `team_memberships`, **read, never rewritten** |
| Grants | **Nothing on its own.** Manager + entitlement + Team scope grants team records **in that domain** |
| Unrelated teams | **Never** — the source case from the Phase 1 document |
| Depth | **D-66:** direct reports only, or the whole chain? |

> **D-66 matters more than it looks.** *Whole chain* means a senior manager's
> scope grows silently every time somebody is hired three levels below them,
> with no assignment change and no review. The plan recommends **direct reports
> plus the teams they manage**, with deeper reach requiring an explicit
> assignment — **visible, reviewable, and deliberate**.

**A management relationship must never grant across domains.** Managing
somebody who works in Finance does not entitle a Sales manager to Finance.
§30 breaks it.

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

**D-68:** whether Auditor is organisation-wide by definition or itself scoped.
The plan recommends **organisation-wide for evidence, and no business-data
entitlement at all** — a scoped auditor who cannot see all the evidence cannot
do the job the role exists for.

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
| **Group deactivated** (P1-03) | If groups participate at all (D-58): **grants nothing** | Retained |
| **Team or business unit deactivated** (P1-01) | Scopes resolving through it **narrow**, never widen | Retained |
| **Organisation inactive** | **Everything denies** | Retained |

**Every row narrows.** Not one of these events may widen anybody's access, and
§30 breaks each.

> **The trap in row 1.** *"Deactivated users lose effective access"* is easy to
> implement as *"skip inactive users when listing"* — which is a listing change,
> not an authorization change. It must be a **deny in the engine**, and the test
> must be a **denied request**, not an absent row.

---

## 21. Group use in access — NOT ASSUMED

**P1-03 groups grant nothing. D-35 was defended through a whole unit, and P1-05
is where it would change. That decision is the Product Owner's — D-58.**

| | Option | For | Against |
| --- | --- | --- | --- |
| **A** | **Users only.** Groups keep granting nothing | Simplest to reason about, review and test. Every grant names a person | Assigning 200 people individually. Group screens keep looking like they should do something |
| **B** | **Groups only** | Tidy at scale | Cannot express a one-person exception without a group of one |
| **C** | **Both** | Matches how organisations work | **Two paths to the same access**, and the union rules of §10 must cover them. "Why can this person see Finance?" needs an answer that names the group |

**If groups participate (B or C), all of these must hold:**

| # | Requirement |
| --- | --- |
| 1 | **Membership changes take effect immediately** — adding somebody to an entitled group grants access at once, removing them revokes it at once |
| 2 | **No assignment history is silently rewritten.** The group's grant is one record; who was in the group when is P1-03's membership history. **Neither is edited to reflect the other** |
| 3 | **The simulator must say WHICH GROUP** produced an answer, or the explanation is useless |
| 4 | **A deactivated group grants nothing**, and its assignments are retained |
| 5 | **P1-04's "Super Admin" hazard applies.** A group with a privilege-suggesting name is where somebody wires administration in **by assumption rather than by decision** — carried from the P1-03 acceptance |

**The plan recommends A for P1-05**, and that group-based assignment be
considered as a distinct, separately reviewed change once the engine is proven —
because every requirement above is a way the engine can be got wrong, and none
of them is about the engine itself.

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

**"Immediate" must be defined, not implied — D-69.** The plan proposes: **the
next authorization decision after the change commits reflects the change**, with
no stale window at all.

### Caching

| | |
| --- | --- |
| **If there is no cache** | Simplest, correct, and the plan's recommendation for P1-05. Phase 1 has no data volume to justify one |
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

**D-70:** whether P1-05 delivers that projection interface now or names it as
Phase 2's first task. The plan recommends **naming and shaping it now, building
it in Phase 2** — designing it with no consumer risks designing the wrong thing.

---

## 26. Schema proposal

**Proposed only. Nothing is written until DESIGN is approved.**

| Table | Purpose |
| --- | --- |
| `roles` | The seven baseline, plus custom if D-51. Immutable `code`, editable name |
| `role_assignments` | Who holds which role, and when. **History, never rewritten** |
| `domain_entitlements` | Who may see which domain, in which role |
| `entitlement_scopes` | The scope on an entitlement, with history |
| `sensitivity_ceilings` | The cap, per person or per entitlement (D-60), with history |

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
| `sensitivity_ceiling.*` | set, changed, cleared |
| `access.denied` | **D-71** — see below |

**The D-12 key boundary is unchanged unless a decision changes it.** P1-04
delivered seven events with **no new context key**, and the same discipline
applies: if this unit needs a key for free text, that is a sign it is trying to
log business content.

**A new key may genuinely be needed** — `role_id`, `domain_id`, `scope`,
`sensitivity` are structural identifiers, not content. **D-72** asks the Product
Owner to approve exactly which, one at a time.

> **D-71 — logging denials.** Logging every denial produces enormous volume and
> buries what matters; logging none loses the signal that somebody is probing.
> The plan recommends **log privileged-surface denials and repeated denials, not
> every routine one**, and that P1-08 revisit it with the whole picture. This is
> the same judgement P1-02 and P1-04 already made about refusals.

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
| 1 | A System Administrator can reach **Roles & Access** and see the seven baseline roles |
| 2 | Assign a role; see it in history; **revoke** it and see the history retained |
| 3 | Grant a **domain entitlement**, set a **scope**, set a **ceiling** |
| 4 | Use the **Access Simulator** to see current access, proposed access, **and why** |
| 5 | See that a **System Administrator with no entitlement can see no business data** |
| 6 | See that a **Domain Owner receives nothing automatically** |
| 7 | **Disable a domain and observe access disappear**; re-enable and observe it return unchanged |
| 8 | **Disable every domain and observe that nothing is granted** — not everything |
| 9 | **Revoke access and observe it stop working on the next request**, without signing out |
| 10 | **Deactivate a user and observe access end**, with assignments retained |
| 11 | Be refused every operation §12–§19 forbid, in business language |
| 12 | See the whole matrix of §30 pass, each guard proven non-vacuous |
| 13 | Read every screen in both themes and at small width, with no implementation wording |
| 14 | **The `platform_role` seam is resolved** and there is demonstrably **one** authorization model — §3.4 |

### Gates that must be closed or explicitly carried

| Gate | Requirement |
| --- | --- |
| **P1-04 — disabled domain never broadens access** | **Must be CLOSED here.** All five cases of §11.3 |
| **P1-02 — provider-wide SSO Re-check lock** | Closed **only if** P1-05 legitimately establishes another **real** System Administrator. **A fake privileged account must not be created.** Otherwise it stays open and says so |

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
| N-P1 | Two scopes → **widest** | Take the narrowest, or the first |
| N-P2 | Two ceilings → **LOWEST** | **Take the highest** — the uniform-rule simplification |
| N-P3 | Revocation beats a grant | Order by id and take the last |
| N-P4 | Two entitlements to **different** domains never merge | Union the domains into one set |
| N-P5 | Scope never widens the domain | Let Organisation scope reach outside the entitled domain |

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

### Lifecycle and history

| # | Guard | Mutation |
| --- | --- | --- |
| N-L1 | No route deletes an assignment, entitlement, scope or ceiling | Add one |
| N-L2 | Replace is **one transaction** and never passes through an ungranted state | Revoke, then grant |
| N-L3 | Two assignments **on one calendar day** are both recorded | Date-valued timing — the P1-01 collision |
| N-L4 | Role **codes** are immutable | Make them fillable |
| N-L5 | **Every operation named in §3–§7 exists** | Delete one — the P1-01 presence guard |
| N-L6 | Every write **confirms itself** | Bare redirect |
| N-L7 | The `platform_role` migration is **reversible and leaves the administrator an administrator** | Roll back and check |
| N-L8 | **No schema column caches an effective-access answer** | Add `effective_domains` |

---

## 31. Product Owner decisions required before DESIGN

**No DESIGN is written until these are answered.**

| # | Decision | Options | Plan recommends |
| --- | --- | --- | --- |
| **D-49** | **The `platform_role` seam** | (a) extend the enum to seven; (b) column **and** assignments; (c) **assignments only, column migrated then removed** | **(c)** — the only one that cannot produce two competing models. §3.4.1 lists what it must prove first |
| **D-50** | Does P1-05 deliver the engine **and** the full administration UI, or split? | (a) one unit; (b) engine first, then admin | **(a) with a staged internal order** — engine, enforcement, then screens. `PHASE-1-PLAN.md` §"risks" already flags splitting this unit |
| **D-51** | **Custom roles** in P1-05? | (a) baseline seven only; (b) custom permitted | **(a)** — custom roles multiply the matrix before the baseline is proven |
| **D-52** | May a **baseline role's display name** change? | (a) no; (b) yes, code immutable | **(b)** — as D-41 for domains |
| **D-53** | May a role's **permitted actions** be edited? | (a) fixed in this release; (b) editable | **(a)** — an editable role definition means the same role name means different things in different deployments, and every test becomes deployment-specific |
| **D-54** | Can a role be **deactivated**, and what happens to live assignments? | (a) not in P1-05; (b) deactivate and suspend assignments; (c) deactivate and require revocation first | **(a)** — a suspended-but-assigned state is a third state every query must remember |
| **D-55** | May one person hold **several roles**? | (a) one; (b) **several** | **(b)** — one role per person forces one role to absorb another's meaning. §10 then governs |
| **D-56** | **Effective dating** — future-dated grants and expiries? | (a) **not in P1-05**; (b) yes | **(a)** — it makes every query time-dependent and every test harder to trust |
| **D-57** | Does **any** role carry an implicit entitlement? | (a) **no, all explicit**; (b) some roles do | **(a)** — an implicit entitlement is invisible in the screen built to make access reviewable |
| **D-58** | **Groups in access** — §21 | (a) **users only**; (b) groups only; (c) both | **(a) for P1-05**, revisited as a separate change once the engine is proven. **Not assumed** |
| **D-59** | Is scope **per entitlement** or per person? | (a) **per entitlement**; (b) per person | **(a)** — one scope per person cannot represent a real organisation |
| **D-60** | Is the ceiling **per person** or **per entitlement**? | (a) per person; (b) per entitlement; (c) both, lowest wins | **(c)** with the lowest winning — §10 |
| **D-61** | Does the engine read P1-04's **`access_expectation`**? | (a) **no — shown as context only**; (b) yes | **(a)** — enforcing it makes P1-04 retrospectively an access-control unit |
| **D-62** | **Precedence** — widest scope, **lowest** ceiling | (a) **as §10**; (b) another rule | **(a)**, and the asymmetry is deliberate |
| **D-63** | A field above the ceiling — **deny the request or redact the field**? | (a) deny; (b) **redact, and say a field was withheld**; (c) deny for actions, redact for fields | **(c)** — denying a whole report for one field is unusable; silently redacting is dishonest |
| **D-64** | **Explicit deny records**? | (a) **not in P1-05**; (b) yes | **(a)** — absence of a grant already denies, and deny records interact with everything |
| **D-65** | May an administrator **grant access to themselves**? | (a) **permit and log prominently**; (b) refuse, require a second administrator; (c) only roles they already hold | **(a) for P1-05**, with four-eyes as **P1-07's**. Refusing it in a single-administrator deployment makes the product unusable |
| **D-66** | **Manager depth** | (a) **direct reports and teams they manage**; (b) the whole chain | **(a)** — the whole chain grows silently with every hire three levels down, with no assignment change and no review |
| **D-67** | What **"Own"** means before Phase 2 data exists | (a) **P1-05 defines the contract, Phase 2 supplies the mapping**; (b) defer entirely | **(a)** — deferring lets Phase 2 invent it, which is a second implementation |
| **D-68** | **Auditor** — organisation-wide or scoped? | (a) **organisation-wide for evidence, no data entitlement**; (b) scoped | **(a)** — an auditor who cannot see all the evidence cannot do the job |
| **D-69** | What **"immediate"** means for revocation | (a) **the next decision after the write commits**; (b) a stated maximum window | **(a)**, with **no permission cache in P1-05** |
| **D-70** | The **Phase 2 projection interface** — now or then? | (a) **shape it now, build it in Phase 2**; (b) build it now; (c) leave it to Phase 2 | **(a)** — designing it with no consumer risks designing the wrong thing; leaving it entirely invites a second model |
| **D-71** | **Log denials?** | (a) **privileged-surface and repeated denials only**; (b) all; (c) none | **(a)** — the same judgement P1-02 and P1-04 already made |
| **D-72** | **New security-event context keys** | Approve each individually | `role_id`, `domain_id`, `scope`, `sensitivity` — **structural identifiers only, no free text** |

---

## 32. What this plan deliberately does not build

- **No Fabric integration, no semantic model, no Power BI RLS/OLS.** Phase 2 — and it **derives** from this engine (§25).
- **No AI surface.** Phase 3 — but the boundary it must respect is defined here.
- **No access reviews or recertification.** P1-07.
- **No durable audit table.** P1-08.
- **No business data.** There is none.
- **No custom roles**, subject to D-51.
- **No effective dating**, subject to D-56.
- **No explicit deny records**, subject to D-64.
- **No group-based assignment**, subject to D-58 — and **not assumed either way**.
- **No permission cache**, subject to D-69.
- **No navigation or branding change.** *Roles & Access* moves from `locked()` to `leaf()` — the single edit that entry was designed for.
- **No fake privileged account.** The P1-02 gate closes only if a **real** second System Administrator is legitimately established.
- **No change to the `srikanth@lithan.com` record.** Still a separate operational item — `P1-03-USERS-GROUPS-VERIFICATION.md` §12.3.

---

**P1-05 PLAN — awaiting Product Owner review.** Twenty-four decisions in §31.
No DESIGN until they are answered.
