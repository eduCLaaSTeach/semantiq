# P1-07 — Access Reviews: PLAN

**PLAN stage only.** Nothing below is approved, designed or built. This document
exists to be argued with before any code is written.

| | |
| --- | --- |
| Unit | **P1-07 — Access Reviews** |
| Menu | `System Administration → Access Reviews` — today a **locked** node (`app/Shared/Navigation/ApprovedMenu.php:129`) |
| Subscreens | Privileged Reviews, Domain Reviews, Overdue Reviews |
| Baseline | `main` at `3c53dcda9c3e10e479b6a77b1172463db4846b91` |
| Live application | `f282571f9dbb24cb1d49c8d8249d350c18b3763f` |
| Status | **PLAN — awaiting Product Owner approval. No DESIGN, no implementation, no deployment.** |

> **Every source fact below was read out of the repository at
> `3c53dcd` and is cited by file and line**, so a reviewer can check it rather
> than take it on trust. Where this plan *interprets* an ambiguous authority, it
> says so and raises a decision instead of choosing quietly.

---

## 0. The four sentences the whole unit serves

1. **A review confirms or removes existing access. It never creates, widens or
   repairs access.** Retain writes nothing to the access model at all.
2. **A review decision is applied only to state that is still exactly what was
   reviewed.** Anything else is superseded, and no write happens.
3. **Authority to attest to access is not entitlement to use the data.** A
   domain owner reviewing Finance gains no Finance record, ever.
4. **P1-07 is not an approval workflow and does not supply four-eyes.** P1-05
   says so twice, in its PLAN §937 and its DESIGN §1301, and nothing here may be
   read as reversing that.

---

## 1. What the authority actually says

`doc/SemantIQ_v2_PHASE_1_System_Administration.md` §P1-07:

- **Purpose** — *"Periodic confirmation/removal of privileged and
  sensitive-domain access."*
- **Minimum tests** — reviewer sees only reviews they may perform; overdue
  tracking; approve/retain/revoke paths; revocation updates effective access;
  review evidence is auditable.
- **Exit** — *"Sensitive access has an accountable review lifecycle."*

These are **minimums**. §5 of this plan goes considerably further, because the
minimums do not mention concurrency, stale reviews, self-review, the
administrator floor, or step-up — every one of which is reachable on day one.

### 1.1 What P1-05 deferred to here, verbatim

| Deferred item | Where it was deferred |
| --- | --- |
| Recertification, campaigns, attestations | `P1-05-ROLES-ACCESS-PLAN.md:150` |
| *"A review reviews assignments. There are none yet"* | `P1-05-ROLES-ACCESS-PLAN.md:97` |
| Whether a particular Restricted grant is overdue | `P1-06-SECURITY-STATUS-PLAN.md:258` |
| Whether a particular broad scope should be reviewed | `P1-06-SECURITY-STATUS-PLAN.md:261`, D-78 |
| The exception **review lifecycle** (P1-06 derives only) | D-77, `P1-06-SECURITY-STATUS-PLAN.md:633` |

### 1.2 What P1-05 explicitly refused to defer here

> **P1-07 does NOT supply four-eyes approval.** — `P1-05-ROLES-ACCESS-PLAN.md:937-939`,
> repeated at `P1-05-ROLES-ACCESS-DESIGN.md:1301-1302`.

D-65 permits self-assignment with confirmation, a privileged event and step-up,
*precisely because* P1-07 was not going to provide an approver. **This plan does
not reopen that.** It matters in §5E: if self-review were prohibited absolutely,
a reader could mistake the prohibition for the four-eyes control P1-05 was told
not to rely on.

---

## 2. The access model P1-07 must consume, as it exists

Read from the migrations and services, not from prose.

### 2.1 The grant path is four tables, all period-based

| Table | Parent | Cardinality under its parent | File |
| --- | --- | --- | --- |
| `role_assignments` | user | many periods over time | `2026_09_06_000001_*` |
| `domain_entitlements` | **`role_assignment_id`** | many current, one per domain | `2026_09_06_000002_*` |
| `entitlement_scopes` | `domain_entitlement_id` | **many current** — only same type+target duplicates are refused | `2026_09_06_000003_*` |
| `entitlement_ceilings` | `domain_entitlement_id` | **exactly one current** — `setCeiling` ends the open period and inserts the next | `2026_09_06_000004_*` |

Every one of the four carries `assigned_at`/`granted_at`, nullable `ended_at`,
and `*_by_user_id` on both ends. **Nothing is ever deleted**
(`RoleAssignmentService.php:21`). "Current" means `ended_at IS NULL`.

### 2.2 The single most important structural fact for this unit

**`domain_entitlements.role_assignment_id` is a foreign key to a specific
assignment *period*.**

So when a role is revoked and later re-granted, the new assignment is a **new
row**, and any entitlement under it is a **new entitlement row**. There is no
path by which a new assignment period can inherit an old entitlement — or an old
review of one.

This is not a rule anyone has to enforce. It is a consequence of the schema, and
§5A builds on it deliberately: a guard that cannot be forgotten beats a guard
that must be remembered.

### 2.3 The revocation authority already exists, at four levels

| Level | Existing service | Behaviour |
| --- | --- | --- |
| Role assignment | `RoleAssignmentService::revoke()` | Ends the assignment **and every current child beneath it** (`:110`, `:177`) |
| Domain entitlement | `EntitlementService::revoke()` | Ends that entitlement and its children (`:98`) |
| Scope | `EntitlementService::revokeScope()` | Ends **one** scope period (`:213`) |
| Ceiling | `EntitlementService::setCeiling()` | Sets; there is deliberately **no** `clearCeiling()` (`:245-252`) |

> **P1-07 must call these. It must not contain any access-ending logic of its
> own.** A second implementation of revocation is a second interpretation of
> access, and this unit's whole premise is that there is one.

Two P1-05 rules that P1-07 will collide with directly:

- **"Revoking the last scope does not revoke the entitlement"**
  (`EntitlementService.php:207-212`). The entitlement stays current and becomes
  an incomplete, non-authorising path. So "revoke" at scope level does **not**
  mean the access is gone from the screen — it means it grants nothing while
  still existing. §5I decides which level a review actually acts on.
- **The administrator floor.** `AdministratorSetGuard::effectiveCount()` counts
  only users with a current assignment **and** `users.status = active`. Revoking
  the last System Administrator is refused. That refusal must reach the review
  screen unchanged.

### 2.4 The engine, and what fails closed before any row is read

`AccessEngine::globalGates()` (`:153`) denies, **before any query**:

- unauthenticated → `DeniedUnauthenticated`
- **inactive user → `DeniedInactiveUser`** (`:163`, with the comment that
  filtering listings alone would leave every direct route open)
- organisation mismatch → `DeniedOrganisationMismatch`
- malformed business-data question → `DeniedUnknownState`

A disabled domain fails closed through the P1-04 gate closed in P1-05.

**Consequence for P1-07:** an inactive user's retained historical grant is
already denied by the engine. A review that "retained" it would not resurrect
anything — but the *screen* must not imply otherwise. §5K.

### 2.5 `GrantPathReference` already exists

`app/Modules/Access/Engine/GrantPathReference.php:22-31` carries exactly:

```
roleAssignmentId, role, domainEntitlementId, businessDomainId,
entitlementScopeId, scopeType, scopeTargetId, entitlementCeilingId, ceiling
```

and a `narrate(string $domainName)` that describes a path **in words, without
reading a single business record**. §5M proposes reusing it rather than inventing
a second way to describe access.

### 2.6 Roles, and which of them are privileged

`RoleCatalogue::MATRIX` (`:39-56`):

| Role | Action classes | Administration authority? |
| --- | --- | --- |
| `system_administrator` | PlatformAdmin, OrgAdmin, AccessAdmin, EvidenceRead | **Yes — all four** |
| `organisation_administrator` | OrgAdmin, AccessAdmin, EvidenceRead | **Yes — three** |
| `auditor` | EvidenceRead | **Yes — one** |
| `executive` | BusinessData | No |
| `domain_owner` | BusinessData | No |
| `manager` | BusinessData | No |
| `business_user` | BusinessData | No |

Two things that are easy to get wrong and are stated here so they cannot be:

- **`domain_owner` is a role that permits BusinessData like any other business
  role.** It is *not* the P1-04 domain-ownership record, and
  `RoleCatalogue.php:22-25` says the two "may not be derived from the other".
- **`RoleCatalogue::grantableBy()` (`:117`) forbids an Organisation
  Administrator from granting or revoking `system_administrator`.** §5D shows
  why that single line decides reviewer authority.

### 2.7 Step-up as it exists today

`StepUpAction` has **five** cases (`:16-20`): `grant_system_administrator`,
`revoke_system_administrator`, `grant_organisation_administrator`, `self_grant`,
`grant_restricted_sensitivity`. `RoleCatalogue::requiringStepUp()` returns
System Administrator and Organisation Administrator.

**Note what is absent: there is no `revoke_organisation_administrator`.** §5J
treats that as a finding to resolve, not a gap to fill silently.

### 2.8 Security events

`SecurityEventLogger` declares its catalogue as constants and validates every
context key against **15 `ALLOWED_KEYS`** (`:328-332`):

```
provider, subject, tenant, user_id, result, reason, expires_at,
organisation_id, entity_type, entity_id, related_id,
role, domain_id, scope, sensitivity
```

It writes `Log::info` only. **There is no event table — P1-08 owns durable
event storage.**

The P1-06 Security Events screen counts the catalogue **derived**
(`SecurityStatusScreensTest.php:150`: `count(SecurityEventLogger::events())`),
not hard-coded — so adding P1-07 keys will not break that assertion. It will,
however, make new rows appear on an **already-accepted screen**. §5N.

### 2.9 There is no scheduler in this deployment

Verified, because it decides §5G and §5H:

- `bootstrap/app.php` has **no** `withSchedule()`.
- `routes/console.php` contains only Laravel's stock `inspire` command.
- `.github/workflows/deploy.yml` installs **no** cron and never runs
  `schedule:run`.

**Nothing in this deployment runs on a timer.** Any design that assumes automatic
generation, automatic reminders or automatic revocation is proposing
infrastructure that does not exist, and must say so rather than assume it.

---

## 3. Scope of Release 1

### 3.1 In scope

- Three route-backed subscreens under one unlocked menu node.
- A review cycle, its items, and a decision per item.
- Retain and revoke, revoke routed through P1-05's existing services.
- Overdue as a **derived** condition, presented on its own tab.
- Reviewer authority enforced on every route and re-checked on every write.
- Security events for the review lifecycle.

### 3.2 Explicitly NOT in scope

| Not in Release 1 | Why |
| --- | --- |
| Automatic/scheduled review generation | §2.9 — no scheduler exists |
| Email or in-app reminders, escalation | No notification infrastructure in Phase 1 |
| Automatic revocation on overdue | A real access change made by a timer with no human decision — D-90 |
| Four-eyes / approval workflow | P1-05 §937 forbids depending on it; reversing that reopens D-65 |
| Manager-chain review | §5D — needs a management-chain authority model that does not exist |
| Trimming scope or ceiling from a review | §5I — a review decides keep-or-not, not re-shape |
| A searchable tamper-resistant audit experience | **P1-08 owns that.** §5N |
| Reviewing P1-06 exceptions | D-77 kept the lifecycle here, but the exception *object* is derived and has no identity yet — raise separately if wanted |

---

## 4. The question this unit must answer

> **Who must periodically confirm which existing access, why are they allowed to
> review it, what exactly are they confirming, what happens when they retain or
> revoke it, and what evidence remains afterwards?**

§5 answers it clause by clause.

---

## 5. The planning areas

### 5A. The exact reviewable object

**The lazy answer is "review a user" or "review a role". Both are wrong here**,
and the schema says why.

One person may hold several **independent** current grant paths at once:

```
Ana ── role_assignment #11 (manager) ── entitlement #40 (Finance) ── scope: team 7
   │                                                              └─ ceiling: confidential
   └─ role_assignment #12 (executive) ─ entitlement #41 (Finance) ── scope: domain
                                                                   └─ ceiling: restricted
```

Both reach Finance. They are different grants, made by different people for
different reasons, and **revoking one must not touch the other.**

Reviewing "Ana" would ask one question about two independent facts. Reviewing
"the manager role" would ask about a role that grants nothing on its own.

#### The candidates

| Candidate object | What it is | Problem |
| --- | --- | --- |
| The user | Ana | Conflates independent grants; a single decision cannot answer both |
| The role assignment | #11 | Right for **platform privilege** (which has no entitlement beneath it); wrong for business data, where the domain is the point |
| The **domain entitlement** | #40 | Uniquely identifies (assignment period, role, domain); owns its scopes and ceiling |
| The engine grant path | #40 + scope + ceiling | One entitlement with three scopes becomes **three** review items asking the same question three times |

#### Recommendation

**Two review objects, one per subscreen, because the two populations are
structurally different things:**

- **Privileged Reviews** review a **current `role_assignment`** whose role holds
  at least one administration class. There is no entitlement beneath platform
  privilege — the role *is* the access (`RoleCatalogue.php:32-35`).
- **Domain Reviews** review a **current `domain_entitlement`**, presented
  together with its current scopes and its one current ceiling as the
  composition being confirmed.

Not the engine path. A reviewer asked *"should Ana still reach Finance through
her Manager role?"* must see **all** of that entitlement's scopes at once to
answer honestly; splitting it per scope asks a question no reviewer can answer
and invites three inconsistent decisions about one grant.

#### What this buys, structurally

| Required protection | How it is obtained |
| --- | --- |
| Revoking one reviewed path must not delete another valid path | `EntitlementService::revoke()` acts on **one row**. The sibling entitlement under a different assignment is untouched **by construction**, not by a filter |
| Retaining one path must not implicitly approve unrelated access | Each item carries its own decision. **No "approve all" control in Release 1** |
| Historical/revoked paths must not reappear | Items are generated only from `ended_at IS NULL` rows, and a terminal item is never reopened |
| A new assignment period must not inherit an old review | **Impossible** — a new assignment period produces new entitlement rows with new ids (§2.2). The old review points at the old row forever |

> **D-84 — is the reviewable object the domain entitlement (recommended) or the
> individual engine grant path?** See §6.

---

### 5B. What counts as "Privileged"

**Do not assume every role is privileged, and do not equate business-data
sensitivity with platform privilege.** Restricted sensitivity is a *business
data* concept; it belongs in §5C, not here.

From §2.6, exactly three roles hold any administration class:

| Role | Classes | Case for including in Privileged Reviews |
| --- | --- | --- |
| System Administrator | all four | Unarguable |
| Organisation Administrator | OrgAdmin, AccessAdmin, EvidenceRead | Holds **AccessAdmin** — can grant and revoke other people's access |
| Auditor | EvidenceRead only | Reads security evidence; sees no business data and changes nothing |

#### Recommendation

**Privileged Reviews cover every current role assignment whose role permits at
least one of `RoleCatalogue::administrationClasses()`** — derived from the
catalogue, never a hard-coded list of three role codes.

That matters: a role added to the catalogue later with an administration class
is then included **by construction**. A hard-coded list would silently omit it,
and nobody would notice until an audit asked why.

**Auditor: include.** EvidenceRead is platform authority over security evidence.
It is the *least* privileged of the three and its review can be the least
ceremonious, but an authority nobody ever reconfirms is an authority that
accumulates. Raised as a decision rather than assumed, because a reasonable
Product Owner could say the opposite.

> **D-85 — does the privileged population include Auditor?** See §6.

---

### 5C. What counts as a Domain Review

**The authority and the UI do not obviously agree, and this plan will not hide
that.**

- The authority says *"privileged and **sensitive-domain** access"*.
- The approved subscreen is called **Domain Reviews**.

"Sensitive-domain access" suggests a *subset*. "Domain Reviews" reads like *all
domain access*. They are not the same population and the difference is large.

#### The options

| Option | Population | Consequence |
| --- | --- | --- |
| (a) | Every current domain entitlement | Every business user × every domain they touch. Unbounded; the screen becomes a list nobody reads, and a review nobody reads is worse than no review |
| (b) | Ceiling is Confidential or Restricted | Matches "sensitive" as *depth* of data |
| (c) | Ceiling is Restricted only | Narrowest; misses Confidential, which is still above the ordinary default |
| (d) | (b) **plus** any entitlement with a whole-domain or organisation scope | Adds *breadth* to depth |

#### Recommendation: (d)

**Release 1 reviews a current domain entitlement when either:**

1. its current ceiling is **Confidential or Restricted**, or
2. any of its current scopes satisfies `ScopeType::coversWholeDomain()` —
   i.e. `domain` or `organisation`.

Rationale, grounded in accepted decisions rather than taste:

- "Sensitive" most naturally means sensitivity, and `Sensitivity` already has
  exactly the three levels with `Standard` as the ordinary one.
- **P1-06 already named the second dimension.** PR-8 counts broad scopes and
  D-78 recorded that a whole-domain scope is *"a legitimate, deliberate grant"*
  carrying no posture state *"until an approved threshold or a P1-07 review rule
  exists"* (`P1-06-SECURITY-STATUS-PLAN.md:261`). **This is that rule.** A grant
  that is legitimate but broad is precisely what periodic review is for.
- Option (a) is rejected on usefulness, not on effort: a recertification list
  that includes every ordinary grant trains reviewers to click through it.

**This is an interpretation of an ambiguous authority and is raised as such.**

> **D-86 — the Domain Review population.** See §6.

---

### 5D. Reviewer authority

**The rule this section exists to protect:** authority to *attest* to access must
never become entitlement to *use* the underlying data.

#### The candidates, analysed

| Candidate | Source of standing | Verdict |
| --- | --- | --- |
| **System Administrator** | Holds `AccessAdmin`; can already revoke anything | **Yes** — for both subscreens |
| **Organisation Administrator** | Holds `AccessAdmin` | **Yes, but restricted** — see the finding below |
| **Domain owner** (P1-04 `business_domain_owners`) | Named accountable party for the domain | **Yes, for Domain Reviews** — recommended |
| **Domain Owner *role*** | A business role permitting BusinessData | **No.** It is not ownership (`RoleCatalogue.php:22-25`) |
| **Manager** (`management_relationships`) | Line management | **No in Release 1** — see below |
| **Auditor** | Holds `EvidenceRead` | **Read-only.** May see that reviews happened; decides nothing |
| **The subject** | Holds the access | See §5E |

#### The finding that decides this section

`RoleCatalogue::grantableBy()` (`:117-130`) lets an Organisation Administrator
grant and revoke every role **except `system_administrator`**. The comment calls
this *"the privilege-escalation path this whole unit exists to close"*.

**If an Organisation Administrator could review a System Administrator
assignment, the review screen would become a second route to the very action
that allow-list forbids** — and it would be a *revocation*, which is exactly how
an attacker removes the person who could stop them.

So: **reviewer authority must be derived from `grantableBy()`, not defined
beside it.** An Organisation Administrator may review Organisation Administrator
and Auditor assignments. Only a System Administrator may review a System
Administrator assignment.

This is the sort of thing that would have been easy to write as a separate
allow-list that agreed with `grantableBy()` on the day it was written and drifted
afterwards. Deriving it means the two cannot disagree.

#### Domain owners as reviewers — and the line that must not blur

`business_domain_owners` is a period-based table
(`2026_09_03_000002_*`) and **the Access Engine never reads it** — verified: no
reference to owners in `app/Modules/Access/Engine/`. P1-04's accepted rule is
that a domain existing, being enabled, or having an owner grants **zero**
access.

Making the owner the reviewer is the right accountability: they are the person
who should know whether Finance access is still warranted. But it creates a real
risk of the two meanings collapsing into one, so the DESIGN must state and test:

- the owner may **see the shape** of an entitlement — person, role, domain,
  scope type, ceiling — because that is what they are attesting to;
- the owner gains **no** `domain_entitlement` and **no** business record;
- nothing in P1-07 may write to `business_domain_owners`, and nothing in the
  engine may begin reading it.

Negative case N-R15 asserts the second of those directly.

**Where a domain has no current owner**, the item falls to System Administrator.
Fail-closed, not fail-open: it must never become "anyone may review it".

#### Manager: deferred, with the reason

A manager reviewing a report's access needs a management-chain authority model
(direct reports only? transitively? across business units?) that does not exist,
and it would spread visibility of access information to a much larger
population. Release 1 says no; Release 2 may revisit.

> **D-87 — reviewer authority per subscreen.** See §6.

---

### 5E. Separation of duties and self-review

#### The situation this deployment is actually in

Production has **exactly one active System Administrator** (recorded in
`P1-06-SECURITY-STATUS-VERIFICATION.md` §9a, read-only, `verify-access` run 9).

So if self-review is prohibited absolutely, **the System Administrator's own
privileged review can never be completed in this deployment.** It would sit
permanently un-actionable — a red item on a screen whose whole purpose is that
items get actioned.

#### The options

| Option | Behaviour | Consequence |
| --- | --- | --- |
| (1) Prohibit absolutely | The sole administrator's item can never be decided | A permanently stuck item trains people to ignore the screen |
| (2) Permit, with step-up and a distinguishable event | Mirrors **D-65**, which permits self-assignment on exactly those terms | Consistent, but self-review is always available even when it needn't be |
| (3) **Permit only when no other eligible reviewer exists**, marked as such | Normal case keeps separation of duties; the sole-administrator case stays actionable and is visibly an exception | Recommended |

#### Recommendation: (3)

Self-review is permitted **only** when the eligible-reviewer set for that item,
excluding the subject, is empty. When it is used it:

- requires **step-up** (§5J);
- emits a **distinguishable** event — not the ordinary review event with the
  same actor and subject, because "distinguishable" is what makes it findable
  later. D-73's `self_grant` is the precedent, and P1-05 proved it non-vacuous
  with mutation M-SE13;
- is labelled on the screen as a reviewed-by-the-subject exception, so a later
  reader is not misled into thinking two people were involved.

**This is not four-eyes and must not be described as it.** P1-05 was explicitly
told not to depend on P1-07 for that (§1.2). Option (3) is a *visibility*
control, not an approval control.

#### The last System Administrator

Revoking through a review reaches `AdministratorSetGuard` exactly as Roles &
Access does, and is refused. The review item then cannot be decided as "revoke"
— it can only be retained, or the deployment must first establish another
genuine administrator.

> **We will not manufacture a second permanent System Administrator to make this
> testable.** §8 records what that costs and what evidence replaces it.

> **D-88 — self-review policy.** See §6.

---

### 5F. The review lifecycle

**States are proposed for their business meaning, not because they are
conventional.** Each one below has to earn its place by describing a situation
that actually occurs.

| State | Business meaning | Occurs because |
| --- | --- | --- |
| `pending` | Somebody must decide, and nobody has | The item was generated and is open |
| `retained` | The reviewer confirmed this access should continue. **Nothing about the access changed** | A decision |
| `revoked` | The reviewer removed this access, and it is gone now | A decision |
| `superseded` | The access being reviewed changed or ended by some other route before a decision was made. **No decision is possible and none is invented** | §5K |

**Overdue is deliberately not a state.** It is `pending` **and** past the cycle's
due instant — a derived condition. Making it a state would mean a background
process has to move items into it, and §2.9 says no such process exists; worse,
an item's state would then depend on whether that process had run, which is the
kind of thing that silently stops working.

#### States deliberately excluded from Release 1

| Excluded | Why |
| --- | --- |
| `scheduled` / `upcoming` | If cycles are started deliberately (§5G), an item exists only once it is due |
| `cancelled` | A cycle abandoned mid-flight is a real need, but not a day-one one. Raise it if wanted rather than build an unused state |
| `escalated` | No escalation mechanism exists (§3.2) |

#### Transitions, exhaustively

```
                  ┌──────────► retained   (terminal)
   pending ───────┼──────────► revoked    (terminal)
                  └──────────► superseded (terminal)
```

- **Every terminal state is terminal.** There is no reopen. A later review is a
  **new item in a new cycle**, which is what keeps history honest.
- `superseded` is reached without a reviewer decision, at submit time or at
  display time, and never produces a write to the access model.
- There is no transition into `pending` from anywhere. Items are born `pending`.

---

### 5G. Cadence and campaigns

P1-05 deferred *recertification, campaigns, attestations* here
(`P1-05-ROLES-ACCESS-PLAN.md:150`), so Release 1 scope must be decided rather
than assumed.

#### The constraint that decides it

**There is no scheduler** (§2.9, verified three ways). A recurring cadence that
generates items automatically has nothing to run it. Building the configuration
for one and leaving it inert would be a setting that looks like a control and
is not — the failure this project has already named on posture screens.

#### Recommendation

- **A review cycle is started deliberately by a System Administrator.** One
  action, one cycle, covering the population defined by §5B / §5C at that
  instant.
- **A due date is chosen when the cycle is started**, with a safe default.
- **Overdue** = `pending` and `now() > due_at`, computed at read time. Stored in
  UTC and compared as an instant, so it is deterministic and has no timezone
  ambiguity (N-R13).
- **No reminders, no escalation.** Visibility only.
- **A recurring cadence is deferred**, and the DESIGN should keep the shape open
  so adding a scheduler later does not require reshaping the data.

Two guards worth naming now:

- **Starting a cycle while one is open must not produce duplicate items for the
  same access.** Either refuse, or generate only for access not already in an
  open item.
- **Cycle generation must be idempotent** if the request is repeated.

> **D-89 — cadence model for Release 1.** See §6.

---

### 5H. Overdue behaviour

**This is the decision that changes real effective access, and it is flagged as
such.**

| Option | What happens at the due date | Assessment |
| --- | --- | --- |
| (a) **Visibility only** | The item appears on Overdue Reviews and is marked overdue everywhere | Recommended |
| (b) Escalation | Notify somebody else | No notification infrastructure (§3.2) |
| (c) **Automatic revocation** | Access ends with no human decision | See below |

#### Why automatic revocation is not recommended

1. **Nothing can run it.** §2.9 — no scheduler, no cron.
2. **It would be a revocation nobody decided.** Every other access change in
   this system records the person who made it (`ended_by_user_id` on all four
   tables). An automatic revocation has no such person, and the honest value
   would be null — indistinguishable from the historical rows D-49's
   reconstruction produced.
3. **It could attack the administrator floor.** An overdue System Administrator
   review would try to revoke the last administrator. `AdministratorSetGuard`
   would refuse, so the "automatic" control would be one that silently does not
   apply to the most privileged access in the system — the worst possible shape.
4. **It converts an unattended screen into an outage.** Nobody reviews for a
   fortnight; access disappears across the organisation.

**Recommendation: (a), visibility only.** If the Product Owner wants automatic
revocation later it should be a deliberate, separately-designed control with a
grace period and an explicit exclusion for the administrator floor — not a
default.

> **D-90 — overdue behaviour.** See §6. **Do not let this one be decided by
> silence.**

---

### 5I. Retain and revoke behaviour

#### Retain

**Retain writes nothing to the access model. Not a touch, not an `updated_at`.**

The only rows written are P1-07's own: the item's state, the decision, the
reviewer, the instant.

This is worth stating as a *structural* property because it is testable
non-vacuously: N-R4 asserts that the complete set of access rows for the subject
is **byte-identical** before and after a retain — the same technique P1-06 used
to catch the ordering leak an aggregate-only assertion would have missed.

A weaker test ("no new entitlement appeared") would pass even if retain quietly
extended a period or reset a ceiling.

#### Revoke — which level, per review type

| Review type | Reviewed object | Revoke calls | Effect |
| --- | --- | --- | --- |
| Privileged | `role_assignment` | `RoleAssignmentService::revoke()` | Ends the assignment **and every current child**, as P1-05 already defines |
| Domain | `domain_entitlement` | `EntitlementService::revoke()` | Ends that entitlement and its scopes and ceiling. **Other entitlements untouched** |

**Scope and ceiling are not separately revocable from a review in Release 1.**

The reasoning is the P1-05 rule at `EntitlementService.php:207-212`: revoking the
last scope leaves the entitlement **current but non-authorising**. From a review
screen that is a trap — the reviewer believes they removed the access, the list
still shows the entitlement, and the grant path is incomplete rather than gone.
A review answers *keep this or not*. Re-shaping a grant is Roles & Access's job,
where the consequence is visible.

> **D-91 — the revoke level per review type.** See §6.

#### Both paths inherit P1-05's refusals unchanged

The administrator floor, the `grantableBy()` allow-list, and entitlement
currency checks all apply. **P1-07 adds no exception to any of them.**

---

### 5J. Step-up authentication

#### The finding

Revoking a System Administrator **already** requires step-up in P1-05 —
`StepUpAction::RevokeSystemAdministrator` exists (`:17`).

**So if a Privileged Review's revoke calls `RoleAssignmentService::revoke()`
without the controller enforcing step-up the way the Roles & Access controller
does, the review screen becomes a step-up bypass for the single most privileged
action in the product.**

That is the central security risk of this unit, and it arrives not from new code
but from *re-using existing code from a new place*. The DESIGN must make the
step-up requirement a property of the action, not of the screen that calls it.

#### The gap

There is **no** `revoke_organisation_administrator` action (§2.7), although
`RoleCatalogue::requiringStepUp()` includes Organisation Administrator. Whether
that is a deliberate P1-05 asymmetry or an oversight must be established at
DESIGN by reading P1-05's controller — **and reported, not quietly patched.** If
it is an oversight in an accepted unit, §9's conflict rule applies.

#### What requires step-up — proposal

| Action | Step-up? | Reasoning |
| --- | --- | --- |
| Revoke a System Administrator assignment | **Yes** | Already required by P1-05 |
| Revoke an Organisation Administrator assignment | **Yes** | `requiringStepUp()` includes it; resolve the missing action first |
| Revoke an Auditor assignment | No | No existing step-up requirement to inherit |
| Revoke a domain entitlement whose ceiling is **Restricted** | **Yes** | Granting Restricted requires step-up; removing it changes Restricted access |
| Revoke an ordinary domain entitlement | No | Consistent with P1-05 |
| **Retain** privileged access | **Open** | Writes nothing, so nothing to protect — but it is an attestation that platform privilege continues. Genuinely arguable |
| Self-review (§5E) | **Yes** | Mirrors D-65's `self_grant` |

#### What this plan will not do

- **Not invent a weaker confirmation.** A typed confirmation box is not step-up.
- **Not claim Microsoft MFA.** Whether Entra asks for a second factor is the
  customer's Entra policy. SemantIQ requires a **fresh sign-in**; that
  distinction is accepted and stays.
- **Not disturb B-9b.** Microsoft's acceptance of the step-up return remains
  permanently `unverified` in Release 1, exactly as recorded.

> **D-92 — step-up requirements for review decisions.** See §6.

---

### 5K. Access changes while a review is open

**The invariant:** a decision is applied only to state that is still exactly what
was reviewed. Otherwise the item is `superseded` and **no write occurs**.

| Situation after the item was created | Outcome |
| --- | --- |
| Role already revoked by another route | **Superseded.** The access is already gone |
| Entitlement ended by another route | **Superseded** |
| A scope was added or removed | Item **re-reads** the current composition. If the reviewer's displayed composition no longer matches, **superseded** — they attested to something else |
| Ceiling changed | Same as scope |
| User became inactive | Item stays open and is **marked**. The engine already denies (§2.4). Retain is still meaningful: it says the grant should survive reactivation. Revoke is still meaningful. **Neither resurrects anything** |
| User reactivated | Nothing special — the grant was never altered |
| Domain disabled | Item stays open and is **marked**. Access is already fail-closed. Reviewing it is still legitimate |
| Domain re-enabled | Nothing special |
| User moved team / business unit | The scope row is unchanged, so the item is unchanged. **What it grants may differ** — the composition is re-read at display |
| A new independent grant path is added | A **different** entitlement. Not in this item, and not in this cycle if the cycle is already generated |
| The reviewed assignment period ended and another began | **Superseded**, and structurally so: the new period has new entitlement rows with new ids (§2.2). The item points at the old row forever |

#### Why "a review can never revive stale access" holds structurally

Retain performs **no write to the access model** (§5I). A thing that writes
nothing cannot revive anything. This is a stronger guarantee than any filter,
and it is why §5I insists retain be defined as "writes nothing" rather than
"writes the same values back".

#### Presenting superseded items

They must be visible, not silently dropped — a reviewer who opened an item
deserves to know why it can no longer be decided. They must be clearly *not*
awaiting anybody, and must not count as overdue.

> **D-93 — how strictly "still exactly what was reviewed" is defined.** A
> composition change makes the item superseded under the strict reading;
> under a looser reading only the reviewed object's own currency matters. See §6.

---

### 5L. Concurrency

**UI state is never authoritative. Every decision re-validates on the server,
inside the same transaction, against rows locked the way P1-05 locks them.**

| Case | Required behaviour |
| --- | --- |
| Two reviewers submit nearly simultaneously | One wins. The other gets a plain refusal saying it was already decided. **Not** two decisions, **not** two events |
| Access changed between opening and submitting | `superseded`, no write (§5K) |
| Duplicate revoke (double submit, retry) | Second is refused or is a no-op. **Never a second revocation event**, and never a second `ended_at` write |
| Retain after revoke | Refused. The item is terminal |
| Reviewer's authority removed while the page is open | Re-authorised on submit and refused, with the same refusal an unauthorised reviewer sees (no oracle) |
| Cycle generation run twice | Idempotent — no duplicate items for the same access (§5G) |

P1-05's concurrency work is the precedent: it tested these against **MySQL**, not
only SQLite, because `lockForUpdate` behaviour differs. P1-07 must do the same.

---

### 5M. Visibility and privacy

**A reviewer sees only what is necessary to decide.** Access existing is not a
reason to show what the access reaches.

| Shown | Not shown |
| --- | --- |
| The person (name, and whether they are currently active) | Their other unrelated access outside the reviewer's authority |
| The role | — |
| The domain | **Any business record in it** |
| The scope composition, in words | Row counts, samples or identifiers from the domain's data |
| The sensitivity ceiling | Field values at that sensitivity |
| **Why this reviewer is responsible** | — |
| Current state, due and overdue status | — |

#### Proving access exists without showing data

`GrantPathReference::narrate()` (`:51`) already describes a complete path in
words without reading a single business record. **Reuse it.** Building a second
description is how two descriptions eventually disagree — and a screen that
demonstrated access by displaying a Restricted row would be a leak dressed as
evidence.

**Restricted business data is never displayed to prove that Restricted access
exists.** N-R15 asserts that a System Administrator gains no business-data
visibility by reviewing access — which follows from `RoleCatalogue` (System
Administrator has no `BusinessData` class at all), but the screen must not
undo it.

---

### 5N. Evidence, and the P1-08 boundary

**Three different things, which must not be merged:**

| Thing | Owner | Nature |
| --- | --- | --- |
| Review cycle, items, decisions | **P1-07** | Durable domain data — the unit's own state |
| Security events for review actions | **existing** `SecurityEventLogger` | `Log::info`, redacted, key-validated |
| A searchable, tamper-resistant audit experience | **P1-08** | Not built here |

#### P1-07 does have durable tables, and that is not a contradiction

P1-06's rule was that it would **not** create an event table, because P1-08 owns
durable *event* storage. That does not forbid P1-07 owning its own domain
tables: a review item is state with a lifecycle, not an audit record. Without
them there is no unit.

**What P1-07 must not do is become a second audit platform** — no generic event
store, no free-text search over activity, no viewer-dependent redaction engine.
P1-08 builds that, over everything, once.

#### New security event keys

Review actions need events. Proposed:

```
access.review.cycle.started
access.review.item.retained
access.review.item.revoked
access.review.item.superseded
access.review.item.self_reviewed      ← distinguishable, per §5E
access.review.refused
```

**These are raised as an explicit decision, not silently appended.** The
catalogue is a security interface.

Two consequences worth stating now:

1. **The existing 15 `ALLOWED_KEYS` appear sufficient** — a review event needs
   `user_id` (subject), `related_id` (reviewer), `entity_type`, `entity_id`,
   `role`, `domain_id`, `sensitivity`, `result`, `reason`, all already allowed.
   **The recommendation is to change `ALLOWED_KEYS` not at all.** If DESIGN finds
   a genuine need, that becomes its own decision.
2. **New keys will appear on the accepted P1-06 Security Events screen.** That
   screen renders the catalogue and counts it *derived*
   (`SecurityStatusScreensTest.php:150`), so no assertion breaks — but the screen
   an accepted unit owns will show new rows. That is correct behaviour and is
   named here so it is not a surprise at Gate D.

**No free text in any event.** No reviewer comment, no justification note, ever
reaches a security event — D-12's redaction contract, and the leak channel
P1-06 explicitly refused (`P1-06-SECURITY-STATUS-PLAN.md:334`). If reviewer
comments are wanted at all, they are P1-07 domain data with their own exposure
rules, and they are **not** in the Release 1 recommendation.

> **D-94 — the review event keys.** See §6.

---

### 5O. Conceptual entities

**No columns, no migration, no types. Business meaning and lifecycle only.**

| Concept | What it represents | Lifecycle |
| --- | --- | --- |
| **Review cycle** | One deliberate act of saying "confirm this population now", with a due instant | Started → items generated → closes when no item is `pending` |
| **Review item** | One reviewable object (§5A) inside one cycle | Born `pending`; ends `retained`, `revoked` or `superseded` (§5F) |
| **Decision** | Who decided what, when, and under which authority | Written once. **Never edited, never deleted** — the same rule as every P1-05 table |
| **Reviewed composition** | What the reviewer was actually looking at: role, domain, scope set, ceiling | Captured with the item so `superseded` can be detected (§5K) and so evidence means something later |

| Deliberately NOT an entity | Why |
| --- | --- |
| **Reviewer assignment** | **Derived**, not stored. Authority comes from `grantableBy()` and current domain ownership, evaluated at read and again at write. Storing it would let it go stale — a person who lost ownership yesterday still holding a stored reviewer row is exactly the privilege-drift this unit exists to prevent |
| **Overdue** | Derived (§5F) |
| **Review policy / cadence** | Only meaningful with recurring cycles, which Release 1 defers (§5G) |

The one genuinely open modelling question: **does a review item store a snapshot
of the composition, or re-read it and compare?** A snapshot makes "what did they
attest to?" answerable years later; re-reading keeps one source of truth. The
recommendation is **snapshot for evidence, re-read for the decision** — they
answer different questions. Folded into D-93.

---

### 5P. Screens and business states

Three route-backed subscreens under the existing **Pattern B** tab strip.

> **The P1-06 mobile correction applies automatically.** The strip is the shared
> `.org-tabs`, corrected on 18 September 2026 to wrap below 640px rather than
> clip. Three shorter labels are a far easier case than Security Status's four.
> **`TabStripFitsANarrowScreenTest` guards it**, and P1-07 adds nothing that
> could reintroduce the defect — provided it uses the shared pattern and does
> not introduce a strip of its own.

#### Privileged Reviews

| Aspect | Content |
| --- | --- |
| Rows | Current privileged assignments in the open cycle that this reviewer may act on |
| Per row | Person, role, why this reviewer is responsible, due date, overdue marker, state |
| Filters | State, role, overdue, person search |
| Actions | Retain, Revoke — each on one row, with step-up where §5J requires |
| Empty | *"No privileged access is awaiting your review."* — a real sentence, not a dash |
| Refusal | Identical for "not permitted" and "does not exist" (§5R N-R2) |
| Special | Self-review clearly marked; last-administrator refusal explained in business words |

#### Domain Reviews

Same depth. Rows are entitlements in the §5C population; each shows domain,
person, role, scope composition in words, ceiling, and the reviewer's basis
(domain owner, or System Administrator where no owner exists). Filters add
domain and sensitivity.

#### Overdue Reviews

**A projection over the same items, not a separate entity** (§5F). It shows
`pending` items past due across both populations, within the reviewer's
authority. No second state to keep in step, and no way for the tabs to disagree.

---

### 5Q. Search, filter and volume

Planned against a real organisation, not a three-user test deployment.

- **Pagination is mandatory** on all three screens. Nothing loads an entire
  organisation into the browser.
- Filters: person, domain, reviewer basis, state, due date, overdue, role,
  sensitivity.
- Every filter applies **inside** the reviewer's authority, never as a way to
  widen it. Filtering is a view over a permitted set, and the permitted set is
  computed first.
- Counts shown on tabs must be **the reviewer's own counts**, not deployment
  totals — a total is an information leak about access the viewer may not see.
- The §5C population choice is itself the main volume control; option (a) would
  make this section unsatisfiable.

---

### 5R. Negative and security test catalogue

Concrete cases. Each names **the mutation that must make it fail**, because a
guard nobody has broken deliberately is a guard nobody has tested.

| # | Case | Mutation that must break it |
| --- | --- | --- |
| **N-R1** | A reviewer cannot see a review outside their authority | Remove the authority filter from the query |
| **N-R2** | Direct URL/API access to such an item fails **identically** to a non-existent one | Return 404 for missing, 403 for forbidden |
| **N-R3** | An Organisation Administrator cannot review a System Administrator assignment | Replace the derived rule with a hard-coded role list including it |
| **N-R4** | **Retain creates no access and changes no access row** — byte-identical rows before and after | Make retain touch `updated_at` |
| **N-R5** | Revoke changes effective access **immediately**, asserted through `AccessEngine` | Cache the decision |
| **N-R6** | Revoking one independent grant path does not remove another | Revoke by `user_id` + domain instead of by entitlement id |
| **N-R7** | A superseded item cannot resurrect access | Allow retain to write the reviewed values back |
| **N-R8** | An inactive user stays denied regardless of a retained grant | Remove the engine's inactive gate |
| **N-R9** | A disabled domain stays inaccessible regardless of the review result | Skip the domain-enabled filter |
| **N-R10** | An unknown or conflicting item state **fails closed** | Add a permissive `default` branch |
| **N-R11** | A reviewer whose authority was removed cannot submit from an already-open page | Authorise on render only |
| **N-R12** | Two concurrent decisions resolve to exactly one, with one event | Drop `lockForUpdate` |
| **N-R13** | Overdue is deterministic across timezones | Compare local dates instead of instants |
| **N-R14** | An unauthorised response contains **no** protected information | Include the subject's name in the refusal |
| **N-R15** | A System Administrator gains **no** business-data visibility by reviewing access | Add `BusinessData` to the review query |
| **N-R16** | Review records survive revocation and remain readable | Cascade-delete items with the entitlement |
| **N-R17** | **No free text or secret reaches a security event** | Add a `comment` key to an event |
| **N-R18** | Revoking the **last** System Administrator is refused from the review screen | Bypass `AdministratorSetGuard` |
| **N-R19** | Revoking a System Administrator from a review **requires step-up** | Call the service without the step-up gate — *the §5J bypass* |
| **N-R20** | A new assignment period does not inherit an old review | Key the item on `user_id` + `role_code` instead of the assignment id |
| **N-R21** | Cycle generation is idempotent | Remove the already-open-item check |
| **N-R22** | A domain owner gains no entitlement by reviewing | Let the engine read `business_domain_owners` |
| **N-R23** | Tab counts are the reviewer's own, not deployment totals | Count without the authority filter |
| **N-R24** | Self-review emits a **distinguishable** event | Emit the ordinary retained/revoked event |

**N-R19 is the one to build first.** It is the only case where correct-looking
re-use of accepted code produces a privilege bypass.

---

### 5S. Product Owner test script — designed now, not after

Written at PLAN so the unit is built to be testable by a person, rather than
explained to them afterwards.

#### What can be tested safely against production

| # | Check |
| --- | --- |
| 1 | **Access Reviews is discoverable in the sidebar** — not just reachable by URL |
| 2 | Three tabs, correctly labelled, each loading its own screen |
| 3 | Starting a review cycle, and seeing the population it generated |
| 4 | Only reviews the signed-in person may perform are visible |
| 5 | **Retain** — and confirming afterwards, in Roles & Access, that **nothing changed** |
| 6 | Overdue presentation, using a deliberately short due date |
| 7 | A refusal case — a direct URL to an item outside authority |
| 8 | **Mobile width**, all three tabs fully readable and tappable (the G2/G2a standard) |
| 9 | Light and dark |
| 10 | Browser **Back** between tabs |
| 11 | **No business data** anywhere on any review screen |
| 12 | No developer terminology — no "entitlement id", "grant path", "enum", "null" |

#### What needs automated fixtures, because production has no legitimate scenario

Production today holds **1 active System Administrator, 0 current entitlements,
0 scopes, 0 ceilings** (`P1-06-SECURITY-STATUS-VERIFICATION.md` §9a).

| Cannot be tested live | Why | Evidence instead |
| --- | --- | --- |
| **Revoke changing effective access** | Needs a real grant to a real person to remove | N-R5 through the engine |
| Two independent grant paths, one revoked | Needs two real grants | N-R6 |
| Reviewer separation of duties | Needs a genuine second privileged person | N-R3, N-R11 |
| Self-review as the *exception* path | Needs the eligible set to be genuinely empty | N-R24 |
| Last-administrator refusal | Would require removing the only administrator | N-R18 |
| Concurrent decisions | Not performable by one person in a browser | N-R12, on **MySQL** |
| Domain-owner review | Needs an owner and an entitlement in the same domain | N-R22 |

> **No production privilege will be created to make a test observable.** Where
> the Product Owner would have to enter inaccurate business data or grant access
> somebody should not hold, the case is marked **NOT CURRENTLY OBSERVABLE WITH
> REAL PRODUCTION DATA**, keeps its automated evidence, and carries the live
> observation forward as a gate — the discipline CLAUDE.md §3 requires.

**This will produce at least one new carried gate.** It is a *P1-07* gate and is
recorded separately. It is **not** the P1-02 provider-wide SSO Re-check (§7).

---

## 6. Product Owner decisions — D-84 to D-94

Eleven decisions. They are **derived from the repository**, not invented to fill
a list: each one is a place where the accepted model genuinely admits more than
one defensible answer, and each one blocks something real.

---

### D-84 — What is the reviewable object?

| | |
| --- | --- |
| **Question** | Does a Domain Review review a **domain entitlement** (with its scopes and ceiling shown as one composition), or each **engine grant path** separately? |
| **Recommended** | **The domain entitlement.** Privileged Reviews review the role assignment, which has no entitlement beneath it |
| **Alternatives** | (b) the engine grant path — one entitlement with three scopes becomes three items; (c) the user — conflates independent grants; (d) the role — a role grants nothing on its own |
| **Consequence** | (b) asks the same question several times and invites three inconsistent answers about one grant. (c) makes it impossible to revoke one path and keep another — the protection §5A exists for |
| **Blocks** | The entire data model, every screen, and N-R6 and N-R20 |

---

### D-85 — Does the privileged population include Auditor?

| | |
| --- | --- |
| **Question** | Privileged Reviews cover roles holding an administration class. Auditor holds only `EvidenceRead`. In or out? |
| **Recommended** | **In** — derived from `RoleCatalogue::administrationClasses()` so any future administration role is included by construction |
| **Alternatives** | (b) System Administrator + Organisation Administrator only, on the grounds that read-only evidence access is not privilege |
| **Consequence** | (b) leaves an authority over security evidence that nobody ever reconfirms. A hard-coded list of two would also silently omit any role added later |
| **Blocks** | Population generation, and the Privileged Reviews screen's content |

---

### D-86 — What is the Domain Review population?

| | |
| --- | --- |
| **Question** | The authority says *"sensitive-domain access"*; the subscreen is called *Domain Reviews*. Which entitlements are reviewed? |
| **Recommended** | **Ceiling is Confidential or Restricted, OR any current scope satisfies `coversWholeDomain()`** — depth or breadth |
| **Alternatives** | (b) every current entitlement; (c) Confidential + Restricted only, no scope rule; (d) Restricted only |
| **Consequence** | (b) is unbounded — every business user × every domain. A recertification list that long trains reviewers to click through it, which is worse than not reviewing. (d) misses Confidential and every whole-domain grant |
| **Blocks** | Population generation, §5Q volume, and the Product Owner test script's expected row counts |
| **Note** | **This is an interpretation of an ambiguous authority**, raised rather than chosen quietly. The recommendation implements the rule P1-06's D-78 said would be written here |

---

### D-87 — Who may review whom?

| | |
| --- | --- |
| **Question** | Reviewer authority per subscreen |
| **Recommended** | **Privileged:** System Administrator reviews any privileged assignment; Organisation Administrator reviews Organisation Administrator and Auditor **only** — derived from `RoleCatalogue::grantableBy()`, never a parallel list. **Domain:** the domain's current owner(s); System Administrator where a domain has no current owner. **Auditor:** read-only everywhere |
| **Alternatives** | (b) System Administrator only — simple, but makes the sole administrator the reviewer of everything and loses domain accountability; (c) add Manager via `management_relationships` |
| **Consequence** | Getting this wrong is a **privilege-escalation path**: if an Organisation Administrator could review a System Administrator assignment, the review screen becomes a second route to revoking the one person who could stop them — the exact action `grantableBy()` forbids. (c) needs a management-chain authority model that does not exist and widens who can see access information |
| **Blocks** | Every authority check, N-R1, N-R2, N-R3, N-R11, N-R22 |
| **Must hold either way** | Reviewing grants **no** business data. Nothing writes to `business_domain_owners`; the engine never reads it |

---

### D-88 — Self-review and separation of duties

| | |
| --- | --- |
| **Question** | May somebody review their own privileged or domain access? |
| **Recommended** | **Only when no other eligible reviewer exists** — with step-up, a **distinguishable** event, and a visible label saying the subject reviewed it |
| **Alternatives** | (b) prohibit absolutely; (c) permit always with step-up, mirroring D-65 |
| **Consequence** | (b) makes the sole System Administrator's own review **permanently undecidable** in this deployment — a stuck red item teaches people to ignore the screen. (c) allows self-review even where a second reviewer was available |
| **Blocks** | The Privileged Reviews screen, N-R24, and the Product Owner test script |
| **Constraint** | **This is not four-eyes and must not be presented as it.** P1-05 was explicitly told not to depend on P1-07 for approval (PLAN §937). **No second permanent System Administrator will be manufactured to make it testable** |

---

### D-89 — Cadence for Release 1

| | |
| --- | --- |
| **Question** | Are review cycles started deliberately, or generated on a recurring schedule? |
| **Recommended** | **Started deliberately by a System Administrator**, with a due date chosen per cycle and a safe default. Recurring cadence deferred; the model shaped so a scheduler can be added later without reshaping data |
| **Alternatives** | (b) configurable recurring cadence now; (c) fixed built-in period |
| **Consequence** | **There is no scheduler in this deployment** — no `withSchedule()`, no cron, no `schedule:run` (§2.9, verified three ways). (b) would ship configuration for a mechanism that cannot run: a setting that looks like a control and is not |
| **Blocks** | The cycle entity, the Overdue screen, N-R13, N-R21 |

---

### D-90 — What does overdue *do*?

| | |
| --- | --- |
| **Question** | Visibility only, escalation, or automatic revocation? |
| **Recommended** | **Visibility only** |
| **Alternatives** | (b) escalation; (c) automatic revocation, with or without a grace period |
| **Consequence** | **(c) changes real effective access with no human decision.** Four problems: nothing can run it (§2.9); every other access change in this system records who made it, and this one could not; it would try to revoke the last System Administrator and be refused, so the "automatic" control would silently exempt the most privileged access in the product; and an unattended screen becomes an organisation-wide outage. (b) has no notification infrastructure |
| **Blocks** | The overdue rule, and whether P1-07 can change access without a person |
| **Note** | **This is the decision that changes production access. It must not be settled by silence.** If it is wanted later it deserves its own design, with a grace period and an explicit administrator-floor exclusion |

---

### D-91 — What level does a revoke act on?

| | |
| --- | --- |
| **Question** | Does a review revoke the assignment, the entitlement, a scope, or a ceiling? |
| **Recommended** | **Privileged → `RoleAssignmentService::revoke()`. Domain → `EntitlementService::revoke()`. Scope and ceiling are not separately revocable from a review** |
| **Alternatives** | (b) let a reviewer trim scopes or lower a ceiling from the review screen |
| **Consequence** | (b) is a trap. P1-05 is explicit that **revoking the last scope leaves the entitlement current but non-authorising** (`EntitlementService.php:207-212`) — the reviewer believes the access is gone, the list still shows it, and the grant path is incomplete rather than removed. Re-shaping a grant belongs in Roles & Access, where the consequence is visible |
| **Blocks** | Both decision paths, N-R5, N-R6, N-R18 |

---

### D-92 — Which review decisions require step-up?

| | |
| --- | --- |
| **Question** | Retaining or revoking privileged access — is it a D-73 privileged action? |
| **Recommended** | **Step-up for: revoking System Administrator (already required by P1-05), revoking Organisation Administrator, revoking a Restricted-ceiling entitlement, and any self-review.** Retain: **open — genuinely arguable** |
| **Alternatives** | (b) step-up on every privileged decision including retain; (c) step-up on revoke only |
| **Consequence** | **`StepUpAction::RevokeSystemAdministrator` already exists.** If a review's revoke calls the service without the controller enforcing step-up the way Roles & Access does, **the review screen becomes a step-up bypass for the most privileged action in the product.** That is this unit's central security risk, and it arrives from correct-looking re-use, not from new code. For retain: it writes nothing, so there is nothing to protect — but it *is* an attestation that platform privilege continues |
| **Blocks** | Both controllers, and **N-R19**, the case to build first |
| **Open finding** | **There is no `revoke_organisation_administrator` action**, although `requiringStepUp()` includes the role. Whether that is a deliberate P1-05 asymmetry or an oversight must be established at DESIGN and **reported, not quietly patched** (§9) |
| **Constraint** | No weaker confirmation may be substituted. **No claim of Microsoft MFA.** B-9b stays `unverified` |

---

### D-93 — How strictly is "still exactly what was reviewed" defined?

| | |
| --- | --- |
| **Question** | If a scope is added to an entitlement after the item was created but before a decision, is the item superseded? |
| **Recommended** | **Yes — strict.** The reviewer attested to a composition; a changed composition is a different fact. Store a **snapshot** of what was shown, and **re-read** the live state at decision time; a mismatch supersedes |
| **Alternatives** | (b) loose — only the reviewed object's own currency matters, and composition changes are ignored |
| **Consequence** | (b) lets somebody widen a grant while its review is open and have the review approve the widened version. The reviewer's attestation would then cover access they never saw |
| **Blocks** | The item model, §5K's whole table, N-R7, N-R12 |
| **Also settles** | Snapshot **and** re-read, because they answer different questions: *"what did they attest to?"* years later, and *"is it still that?"* at decision time |

---

### D-94 — The review security-event keys

| | |
| --- | --- |
| **Question** | Which new `SecurityEventLogger` keys does P1-07 add? |
| **Recommended** | `access.review.cycle.started`, `access.review.item.retained`, `access.review.item.revoked`, `access.review.item.superseded`, `access.review.item.self_reviewed`, `access.review.refused`. **No change to `ALLOWED_KEYS`** — the existing 15 appear sufficient |
| **Alternatives** | (b) fewer, with a `result` discriminator on one key; (c) more granular per review type |
| **Consequence** | (b) makes self-review indistinguishable at the event level, which is the one thing §5E says it must not be. Extending `ALLOWED_KEYS` widens what may be written to logs forever — it is a security interface, not a convenience |
| **Blocks** | Event emission, N-R17, N-R24 |
| **Named consequence** | These keys **will appear on the accepted P1-06 Security Events screen.** Its count is derived (`SecurityStatusScreensTest.php:150`), so nothing breaks — but an accepted screen gains rows, and that is stated now rather than discovered at Gate D |
| **Hard constraint** | **No free text in any event.** No reviewer comment or justification reaches a security event — D-12's contract, and the leak channel P1-06 refused |

---

## 7. The carried P1-02 gate — untouched

**P1-07 does not absorb, close, alter or inherit the P1-02 provider-wide SSO
Re-check.** It remains, exactly as `PHASE-1-PLAN.md` §10 records it:

> **OPEN / CARRIED / UNVERIFIED — Phase 1 orchestration**

P1-07 will not:

- create another System Administrator for the SSO gate;
- promote anybody for testing;
- close the gate incidentally;
- alter its ownership.

**§5E is where the temptation lives**, and it is refused there explicitly: the
sole-administrator self-review case is resolved by *policy* (D-88), never by
creating a second administrator. If a genuine second permanent System
Administrator comes to exist in normal operation, Phase 1 orchestration takes
that observation separately, and P1-07 has no part in it.

---

## 8. Live-production test limitations

Production holds **1 active System Administrator, 0 current entitlements, 0
current scopes, 0 current ceilings, 3 users, 3 enabled domains**
(`P1-06-SECURITY-STATUS-VERIFICATION.md` §9a, read-only, `verify-access` run 9).

**A correctly restrained production environment is a healthy thing, and none of
the following is an implementation defect.** Each will be recorded as **NOT
CURRENTLY OBSERVABLE WITH REAL PRODUCTION DATA**, keep its automated evidence,
and carry its live observation forward by name.

| Cannot be observed live | Prerequisite that does not exist | Automated evidence |
| --- | --- | --- |
| Revoke changing effective access | A real entitlement to remove | N-R5, through `AccessEngine` |
| One path revoked, another retained | Two real independent grants | N-R6 |
| Reviewer separation of duties | A genuine second privileged person | N-R3, N-R11 |
| Self-review as the exception path | An eligible set that is genuinely empty | N-R24 |
| Last-administrator refusal | Would mean removing the only administrator | N-R18 |
| Concurrent decisions | Not performable by one person in a browser | N-R12, on **MySQL** |
| Domain-owner review | An owner and an entitlement in the same domain | N-R22 |

**No production privilege, entitlement or administrator will be created to make
any of these observable.** The Product Owner will not be asked to enter
inaccurate business data or to grant access somebody should not hold.

**This unit will therefore produce at least one new carried gate of its own.** It
is a **P1-07** gate, recorded separately, and it is **not** the P1-02 gate.

---

## 9. Source-of-truth conflicts

**One item is flagged for resolution at DESIGN rather than corrected here.**

> **`StepUpAction` has no `revoke_organisation_administrator`**, although
> `RoleCatalogue::requiringStepUp()` includes Organisation Administrator and
> `grant_organisation_administrator` exists.

It may be a deliberate P1-05 asymmetry — or an oversight in an accepted unit.
**This plan does not decide and does not patch it.** DESIGN reads P1-05's
controller, establishes which it is, and **reports it**. If it turns out to be a
defect in an accepted unit, it is raised as its own corrective item, not folded
into P1-07's diff.

No other conflict was found. Everything else in §2 is consistent across the
migrations, services, engine, catalogue and the accepted P1-05 and P1-06
documents.

---

## 10. Schema and migration impact — for later stages only

**No migration is written at PLAN, and none is authorised by it.** Recorded so
the DESIGN stage has a starting point and the Product Owner can see the size.

| Expected | Nature |
| --- | --- |
| New tables for cycle, item and decision (§5O) | **New only.** P1-07 adds no column to any P1-05 access table and alters none |
| Menu | `ApprovedMenu.php:129` — `locked()` becomes `leaf()`. **One line. No navigation redesign** |
| Routes | A new `access-reviews` group. Action class to be decided at DESIGN — `AccessAdmin` for decisions, with reads possibly wider |
| Events | New constants in `SecurityEventLogger` (D-94). **No `ALLOWED_KEYS` change recommended** |
| P1-05 code | **Consumed, not modified.** No change to the engine, the catalogue, the services or the access schema |
| Rollback | Drops P1-07's own tables only. **No access data is touched, so rollback cannot lose a grant** |

---

## 11. Proposed delivery order for EXECUTE

Not authorised. Recorded so DESIGN has a shape to argue with.

1. Domain model and lifecycle states, pure, no persistence
2. Reviewer-authority resolution, **derived from `grantableBy()` and current ownership**
3. Population generation for both subscreens
4. Schema and migration
5. Decision services, calling P1-05's revocation authority
6. **Step-up enforcement — N-R19 first**
7. Routes and authorisation
8. Three screens, shared Pattern B strip
9. Event emission
10. The N-R catalogue, with a recorded mutation for each
11. MySQL concurrency
12. Browser verification, including mobile at 390px
13. Product Owner Test Script and handover documents

---

## 12. What would make this plan wrong

Stated plainly, because a plan that cannot be wrong has not said anything.

- **If D-86 goes to "every entitlement"**, §5Q's volume approach and the test
  script's expectations both change materially.
- **If D-90 goes to automatic revocation**, P1-07 becomes a unit that changes
  production access without a person, and needs infrastructure this deployment
  does not have — a substantially different unit.
- **If D-84 goes to the engine grant path**, the item model, all three screens
  and N-R6 change shape.
- **If the Product Owner wants reviewer comments**, §5N's "no free text" holds
  for *events*, but the comments become P1-07 domain data with their own
  exposure rules — a real addition, not a field.

---

## 13. Exit criteria for the unit

From the authority, plus what this plan adds:

1. Reviewer sees only reviews they may perform — **and a refusal discloses
   nothing**, including existence.
2. Overdue tracking, deterministic across timezones.
3. Retain and revoke both work — **and retain provably changes no access row.**
4. Revocation updates effective access immediately, asserted through the engine.
5. Review evidence is auditable — **durable P1-07 records plus redacted security
   events, without building P1-08 early.**
6. **No review screen exposes business data.**
7. **No review action bypasses step-up, the administrator floor, or
   `grantableBy()`.**
8. Every guard has a recorded mutation that breaks it.
9. The professional-polish gate and mobile verification pass, at 390px and
   desktop, in both themes.

**Exit:** *sensitive access has an accountable review lifecycle* — and the
accountability is real, because the reviewer is a named person with standing,
the decision is recorded, and retaining access provably changes nothing.

---

## 14. Status

**PLAN — awaiting Product Owner approval.**

No DESIGN. No implementation. No schema. No migration. No route, controller,
service or UI code. No production or deployment change.

**D-84 to D-94 require answers before DESIGN begins.** D-90 is the one that
changes real effective access and should not be settled by silence.
