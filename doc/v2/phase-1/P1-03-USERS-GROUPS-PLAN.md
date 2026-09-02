# P1-03 — Users & Groups — PLAN

**Status:** **PLAN APPROVED — Product Owner, 2 September 2026**, with decisions
**D-33 to D-39** decided as recorded in §18. Documentation only.
**Unit:** P1-03 (Phase 1 delivery order 5)
**Predecessors:** P1-00 **ACCEPTED 31 Aug 2026** · P1-01 **ACCEPTED 2 Sep 2026** ·
P1-02 **ACCEPTED 2 Sep 2026**
**UI standard:** `doc/design-system/ui-and-ux-layout-template-shared.md` — **FROZEN**

> **Nothing here is implemented.** No migration, model, route, controller,
> screen, service, seed, test or production user has been created. **This is
> PLAN only.**

---

## 1. Purpose and business outcome

**Blueprint step 4, quoted:** *"Import users and groups — people become visible
without receiving business access by default."*

Today SemantIQ has exactly **one** user, created through the single-use
first-administrator bootstrap, and the bootstrap **closes permanently** once an
active System Administrator exists. There is no second way to bring a person
into the product. That is not an oversight — P1-00 built it that way
deliberately, because SYS-014 and SYS-015 forbid self-registration and require
onboarding to be administrator-governed — but it means **the product currently
cannot onboard anybody**.

### The sentence this unit is built to make true

> A System Administrator can bring a named person into SemantIQ, see who they
> are, place them in the organisation, group them, and end their access —
> **without any of it granting access to business data.**

### The mistake this plan is built to prevent

**Onboarding that quietly becomes entitlement.** Every other unit's boundary is
a wall between an administrator and some data. This one is different: it is the
unit that creates *principals*, and a principal is what every future permission
attaches to. The failure mode is not a leak — it is a group that starts as a
label and ends up being consulted by an access check nobody remembers writing.

So this plan states, before any screen exists, that **a user and a group are
inert**. §12 makes that a boundary and §17 makes it a test.

---

## 2. In scope / out of scope

### In scope

| # | Capability |
| --- | --- |
| 1 | **Users** — bring a person in, see who they are, edit what SemantIQ owns, activate and deactivate |
| 2 | **Entra identity association** — bind a SemantIQ user to an immutable Entra object identity, on the P1-00 rules |
| 3 | **Provisioning lifecycle** — how a person gets from "the administrator intends to add them" to "they can sign in" (§4, decision **D-33**) |
| 4 | **Organisation assignment** — the D-16 seam, written for somebody other than the bootstrap administrator for the first time |
| 5 | **Groups** — create, rename, describe, activate, deactivate |
| 6 | **Group membership** — add a member, end a membership, with history retained |
| 7 | **Search, list and filter** on the user list, where the list makes it necessary (§9) |
| 8 | **Duplicate prevention** — one person, one SemantIQ user (§10) |
| 9 | **Dependency-guarded deactivation and purge** (§11) |
| 10 | The **Users & Groups** navigation entry becomes reachable |
| 11 | **Closing three carried live gates** from P1-01 and P1-02 (§14) |

### Out of scope — each owned elsewhere

| Excluded | Owner |
| --- | --- |
| Roles, permissions, entitlements, scope, sensitivity, effective access, the access simulator | **P1-05** |
| Any expansion of `users.platform_role` beyond its single existing value | **P1-05** — §12 |
| Business domains | **P1-04 onward** |
| Identity configuration, SSO health, session policy | **P1-02 — ACCEPTED. Not rebuilt** |
| The sign-in flow, callback, token validation, bootstrap grants | **P1-00** |
| Organisation structure — legal entities, business units, departments, teams, management hierarchy | **P1-01 — ACCEPTED.** P1-03 *reads* it and adds no structural entity |
| Durable audit storage and the audit catalogue | **P1-08.** P1-03 emits through the existing D-12 boundary and creates no audit table |
| Access reviews | **P1-09** |
| Anything Fabric or Workplace | Phases 2 and 3 |

### What already exists, and must not be rebuilt

Read from the implementation, not assumed.

| Exists | Where | P1-03's relationship |
| --- | --- | --- |
| `users` — `provider`, `external_subject`, `tenant_id`, `email`, `display_name`, `status`, `platform_role`, `organisation_id`, `last_signed_in_at` | `2026_08_31_000001` + `_000011` | **Extended, not replaced.** §15 |
| Identity key `(provider, external_subject, tenant_id)`, unique | `users_identity_uq` | **The join rule. Unchanged.** §10 |
| `IdentityResolver` — resolves, never creates; unknown fails closed | `Platform/Identity/` | **Not touched.** SYS-014 |
| `bootstrap_grants` — single-use, closes once an active administrator exists | `_000002` | **Not reopened, not reused, not extended.** §4 |
| `PlatformRole` — one case, asserted | `Platform/Models/` | **Not extended.** §12 |
| `team_memberships`, `management_relationships` — both reference `users` | P1-01 | **Read as dependencies.** §11 |
| `PurgeDependencies` — walks foreign keys **from the schema** | `Organisation/Support/` | **Reused as-is.** It already blocks on any table referencing a record, so P1-03's own tables protect users the day their migration lands |
| `RequireSystemAdministrator` — Platform, one copy | `Platform/Http/Middleware/` | **Reused.** §12 |
| `NoBusinessSchemaTest` — forbids `roles`, `permissions`, `entitlements`, `scopes`, `domains`… | `tests/Architecture/` | **Left forbidden.** It is the existing guard that P1-03 does not become P1-05 |
| `SecurityEventLogger` — fixed context keys, forbidden key is a hard failure | `Platform/Security/` | Adds event names only; **adds no context key** |

---

## 3. Screens and subareas

The blueprint names the subareas: **Users; Groups; Invitations/Directory Sync;
User Lifecycle.** Mapped to route-backed tabs — Pattern B, as P1-01 and P1-02
already use — that is:

**Two tabs — D-38, approved:**

| Tab | Purpose |
| --- | --- |
| **Users** | The list. Who is in SemantIQ, their status, and the way in to one person. **`Add User` is an action on this screen** |
| **Groups** | The list of groups. **`Add Group` is an action on this screen** |

| Record page *(not a tab)* | Purpose |
| --- | --- |
| **User** | One person: identity, organisation, groups, dependencies, and every lifecycle action that acts on them |
| **Group** | One group: its details and its members |

**Three things are deliberately NOT tabs**, and D-38 confirms all three:

- **`Add User`** is an action, not a destination. A permanent tab for a form
  turns a button into navigation.
- **Invitations / Directory Sync** — the mechanism does not exist. D-33 chose
  Option A, so there is nothing to invite and nothing to sync, and a tab for it
  would be a screen in search of a feature.
- **User Lifecycle** — a lifecycle is not a place. Activate and deactivate belong
  on the person they act on, exactly as P1-01 put deactivation on the record.

---

## 4. Provisioning — D-33 APPROVED: the administrator enters the Object ID

**Decided. Option A.** The administrator types the Microsoft Entra **Object ID**.
No email binding, no Microsoft Graph, no pending-invitation table, no change to
the sign-in callback, no reopening of bootstrap.

**The Product Owner's reason, recorded:** *"I do not want to weaken the P1-00
rule that immutable Entra Object ID is the identity key. The current schema and
P1-00 design deliberately exclude email from the identity key because email can
change or be reassigned."*

Option B — binding a pending record on first sign-in — was declined, and it was
declined for exactly the reason this plan warned about: it would have used an
email address once, to decide which pending record a verified sign-in belonged
to, and that is a real weakening of a real rule. Option C was declined because a
Graph permission is a security decision in its own right, not a side effect of a
Users screen.

### 4.1 The Add User flow

| Step | |
| --- | --- |
| 1 | A System Administrator opens **Users** and chooses **Add User** |
| 2 | **Entra Object ID** — **required.** Format-validated and checked for uniqueness, server-side |
| 3 | **Work email** — **required.** Initial human-readable reference only, §4.2 |
| 4 | **Display name** — **optional.** Defaults to the email when omitted |
| 5 | **Organisation** — **required.** The current organisation |
| 6 | **Provider** — set automatically to Microsoft Entra ID. Never typed |
| 7 | **Tenant ID** — taken automatically from the configured trusted tenant. **Never typed** |
| 8 | `platform_role` — **NULL.** §12 |
| 9 | The user is created with **zero business access** |

### 4.2 ⚠ Provisional display information — the distinction that must not blur

**Before a first successful sign-in, `email` and `display_name` are PROVISIONAL
DISPLAY INFORMATION.** They are what an administrator typed, and SemantIQ has
verified none of it.

They must **NEVER** be used for:

- **authentication matching** — the identity key stays `(provider,
  external_subject, tenant_id)`;
- **authorization** — they grant nothing and gate nothing;
- **duplicate identity resolution** — uniqueness is decided on the identity
  triple, never on an address;
- **access** of any kind.

**On the first successful sign-in nothing special happens, and that is the
point.** `IdentityResolver` already refreshes `email` and `display_name` from the
verified Entra token on every sign-in, and it will refresh these the same way. A
provisional value simply becomes a verified projection the first time the real
person authenticates. **No new callback behaviour is added**, and DESIGN must
not add any: the transition is a consequence of code that already exists.

### 4.3 ⚠ SemantIQ cannot confirm the Object ID exists

Release 1 has **no Graph permission by deliberate decision**, so SemantIQ can
check that an Object ID is *well formed* and *not already used*, and it can check
nothing else. **It cannot confirm that the identifier names a real person in the
directory.**

**The screens must say so plainly rather than implying validation occurred.** A
green tick that means "the format is right" and reads as "we found them" is the
kind of dishonest affordance this project keeps rejecting.

The consequence, stated: a mistyped Object ID produces a user who can never sign
in, because no directory identity will ever match it. **The correction path is
the D-39 guarded purge** — never-signed-in, no history — and the identity key is
**not** made casually editable to accommodate typos. An editable identity key is
how one person quietly becomes another.

### 4.4 What D-33 = A therefore removes from this unit

- **No `user_provisionings` table.** No pending records, no invitations.
- **No email-binding callback change.** P1-00's flow is untouched.
- **No bootstrap reopening.** `bootstrap_grants` is not reused or extended.
- **No Microsoft Graph**, and no new Entra application permission.

---

## 5. User lifecycle — stated operation by operation

**The word "CRUD" does not appear in this plan.** P1-01's most expensive defect
was a delivered unit missing Update on four entity types, because "CRUD" was
written where operations should have been named. Every operation below is named
or explicitly refused.

| Operation | Available? | Notes |
| --- | --- | --- |
| **Create** | **Yes** — the administrator enters the Object ID, D-33 = A | Never by self-registration. SYS-015 |
| **Read — list** | **Yes** | §9 |
| **Read — one** | **Yes** | The record page |
| **Edit — SemantIQ-owned fields** | **Yes** | Exactly the fields §7 marks SemantIQ-owned |
| **Edit — directory-owned fields** | **NO — refused** | `email`, `display_name`, `external_subject`, `tenant_id`, `provider` are projections of Entra. §10. An editable field that a sign-in silently overwrites is worse than a read-only one |
| **Activate** | **Yes** | Restores sign-in for a deactivated user |
| **Deactivate** | **Yes** | The primary way access ends. §11 |
| **Assign organisation** | **Yes** | §11B |
| **Change organisation** | **Guarded — see §11B** | Not a simple edit: it can orphan P1-01 structure |
| **Group membership — add** | **Yes** | §6 |
| **Group membership — end** | **Yes** | Ends it; does not erase it |
| **Guarded purge** | **Yes, narrowly** | §11C. Far narrower than D-24's |
| **Delete (unguarded)** | **NO — no such route exists** | As P1-01: no DELETE beyond the guarded purge, asserted by `LifecycleCompletenessTest` |
| **Change platform role** | **NO — refused in P1-03** | §12. P1-05 owns the role model |
| **Reset password / manage credentials** | **NO — impossible by design** | SemantIQ holds no credential. Entra owns authentication |
| **Force sign-out / revoke session now** | **NO — D-36, decided** | Deactivation already ends access at the person's next request, because P1-00 revalidates every protected request uncached. Immediate revocation is a separate capability with its own design |

## 6. Group lifecycle — stated operation by operation

| Operation | Available? | Notes |
| --- | --- | --- |
| **Create** | **Yes** | Name, code, description |
| **Read — list / one** | **Yes** | |
| **Edit** | **Yes** | Name, code, description. **Not** its members — that is membership |
| **Activate / Deactivate** | **Yes** | An inactive group keeps its members and its history |
| **Membership — add** | **Yes** | Active users of the same organisation only |
| **Membership — end** | **Yes** | Sets an end date. The row is retained, exactly as `team_memberships` does |
| **Membership — remove from history** | **NO** | Erasing that somebody was once a member destroys the evidence the group exists to provide |
| **Guarded purge** | **Yes** | Only a group with no membership **history at all**. §11C |
| **Nested groups** | **NO — D-35** | Group-in-group is where an access model's complexity hides. Not in Release 1 |
| **Group-derived access** | **NO — never in P1-03** | §12 |

---

## 7. Field matrix and source of truth

**Every field says who owns it.** This table is the answer to "what can be edited
locally versus what remains authoritative in Entra".

### 7.1 `users`

| Field | Source of truth | Editable in SemantIQ | Shown to an administrator |
| --- | --- | --- | --- |
| `provider` | **P1-00 / Entra** | No | As a provider name, not a key |
| `external_subject` | **Entra — the object id** | **Never** | **Masked with an explicit Reveal — D-37, approved: exactly the P1-02 D-27 pattern, reused, not reinvented.** Entered once at Add User; never editable afterwards |
| `tenant_id` | **Entra** | **Never — and never typed.** Taken from the configured trusted tenant | Masked, D-37 |
| `email` | **Entra**, refreshed every sign-in | **No** | Yes — administrators correlate people by it |
| `display_name` | **Entra**, refreshed every sign-in | **No** | Yes |
| `status` | **SemantIQ** | **Yes** — activate / deactivate | Yes |
| `organisation_id` | **SemantIQ** (D-16, P1-01) | **Yes**, guarded — §11B | As the organisation's name |
| `platform_role` | **SemantIQ**, P1-05 seam | **NO in P1-03** — §12 | Read-only, and labelled as owned by a later release |
| `last_signed_in_at` | **SemantIQ**, written by P1-00 | No | Yes — the most useful field on the list |
| `last_signed_in_at` **when NULL** | — | — | **`Not signed in yet`** — D-33. The list and the record both say it in those words |

**No new `users` columns — D-34, approved: none.** `job_title` and `notes` were
declined as duplicate profile data that Entra already holds, with no current
business decision requiring a second copy that can drift.

**Before a first sign-in, `email` and `display_name` are what an administrator
typed — provisional, unverified, and never used for identity, authorisation or
duplicate resolution (§4.2). After it, they are verified directory projections.**
Nothing about their *editability* changes between the two states.

**Why `email` and `display_name` are not editable.** They are overwritten by
`IdentityResolver` on every single sign-in. An administrator who edited either
would see their change silently reverted the next time that person signed in —
a screen that appears to work and does not. Either they are read-only, or the
projection has to stop, and stopping it would leave SemantIQ showing a stale
name for someone who married last month. **Read-only is the honest option**, and
DESIGN must say on the screen that these come from Microsoft.

### 7.2 `groups` *(new)*

| Field | Source of truth | Editable |
| --- | --- | --- |
| `organisation_id` | SemantIQ | Set at creation, never changed |
| `name` | SemantIQ | Yes |
| `code` | SemantIQ | Yes — short identifier, as P1-01 departments and teams have |
| `description` | SemantIQ | Yes |
| `status` | SemantIQ | Activate / deactivate |

**D-35, approved: groups are SemantIQ-owned and flat.** No Entra group sync, no
Graph, no nested groups, and **no group-derived access in P1-03**. They are
organisational labels and membership containers, nothing else.

**P1-05 may later decide how groups participate in access. P1-03 must not
anticipate that decision** — which is why `groups` carries no column that could
be read as a grant (§12).

### 7.3 `group_memberships` *(new)*

| Field | Notes |
| --- | --- |
| `group_id`, `user_id` | Both required |
| `joined_at` | Required |
| `left_at` | NULL means current. **The row is never deleted** |

Deliberately the same shape as P1-01's `team_memberships`, so membership means
one thing in the product rather than two.

---

## 8. Validation rules

| Rule | Why |
| --- | --- |
| A user's identity triple is unique | Already enforced by `users_identity_uq`. §10 |
| A group's `name` is unique within its organisation | Two groups called "Finance" is an administrative accident, not a structure |
| A group's `code` is unique within its organisation, where present | Same |
| A membership requires an **active** user | Adding a deactivated person to a group is a change with no effect and a misleading appearance |
| A membership requires an **active** group | Same, from the other side |
| A membership requires user and group in the **same organisation** | The D-16 rule, exactly as P1-01 applies it to teams |
| A user with `organisation_id = NULL` may not join a group | Fails closed, as P1-01 already does for teams |
| No duplicate **current** membership | One person is in a group once. A second join while one is open is refused, not stacked |
| `left_at` may not precede `joined_at` | |
| Every SemantIQ-owned text field is length-bounded and trimmed | |

---

## 9. Search, list and filter — where genuinely needed

The shared standard requires search and filter on every list screen. **This plan
does not treat that as a formality**, because production has one user and a
search box over one row is furniture.

| Screen | Release 1 | Reasoning |
| --- | --- | --- |
| **Users** | **Search and filter, built** | This is the list that grows without bound, and the one an administrator opens to find one person among many. Search across display name and email; filter by status, by organisation association, and by group |
| **Groups** | **Search only** | A realistic Release 1 organisation has tens of groups, not thousands. A filter row over a short list is noise; the standard's "search and filter" is satisfied honestly by saying which is warranted |
| **A group's members** | **Search only**, plus current/past | Same reasoning |

**Pagination:** the user list is paginated from the first release. A list that
works at one row and falls over at two thousand is a defect scheduled for later.

**⚠ Honest note.** At acceptance, production will hold **two or three** users.
Search, filter and pagination will therefore be *exercised* but not *stressed*,
and §17 must prove them against seeded volume in tests rather than claiming a
production observation nobody can make. Recorded as a limitation, not hidden.

---

## 10. Duplicate-user prevention, and the Entra join rule

**The rule is P1-00's and it does not change:**

```
identity = (provider, external_subject, tenant_id)      unique
```

`external_subject` is the Entra **object id** — immutable, never reassigned.
**Email is never the identity key.** A reassigned mailbox must not inherit a
SemantIQ identity, and that is exactly what email keying would allow.

Duplicate prevention therefore has two layers:

1. **The database.** `users_identity_uq` makes a duplicate identity
   unrepresentable. Two administrators racing to add the same person produce one
   user and one refusal, decided by the constraint and not by a check.
2. **The screens.** Adding somebody who is already present must be refused in
   *business language* — *"That person is already in SemantIQ"* — with a way to
   reach their record, never a constraint-violation message.

**D-33 = A removes the hardest version of this problem.** The identity key is
present at the moment of creation, so a duplicate is caught by the constraint
immediately — there is no window in which two unbound records could later
resolve to the same person. That is a direct consequence of not using email to
bind, and it is worth naming as a benefit of the decision rather than leaving it
implicit.

**Two addresses, one person, is therefore a non-problem here:** an administrator
who adds the same Object ID under a second email address is refused by
`users_identity_uq`, whatever they typed in the email field. **The email is never
consulted for duplicate resolution** — §4.2.

---

## 11. Dependency and lifecycle guards

### 11A. What already points at a user

| Table | Meaning |
| --- | --- |
| `team_memberships` | They are, or were, in a team — P1-01 |
| `management_relationships` (`user_id`) | They report, or reported, to somebody |
| `management_relationships` (`manager_id`) | **People report to them** |
| `sessions` | An open session |
| `bootstrap_grants.consumed_by_user_id` | They redeemed the first-administrator grant |
| *(new)* `group_memberships` | |

**`PurgeDependencies` reads these from the schema**, so P1-03's own tables start
blocking on the day their migration lands, with no change to that class. That
property was proved in P1-01 by D-25 and is reused rather than re-implemented.

### 11B. Deactivation, organisation change, and what they must not do

| Action | Rule |
| --- | --- |
| **Deactivate a user** | **Always permitted.** It is the safe action and must never be blocked by dependencies — a person leaving is exactly when they have the most history. Access ends at their next request, because P1-00 revalidates every protected request uncached |
| Their team memberships | **Retained.** History is not rewritten by someone leaving |
| Their management relationships | **Retained**, and here is the sharp edge: **if people report to them, deactivating leaves a manager who cannot sign in.** DESIGN must decide — **D-36** — whether that is refused, warned, or permitted with the reports listed. **This plan recommends: permitted, with the count of people reporting to them shown before confirming.** Refusing would make an administrator unable to end access for a departing manager, which is the opposite of safe |
| Their group memberships | **Retained**, and end automatically? **No** — a deactivated user's memberships stay as they are. Reactivation must restore the person as they were, not as a stranger |
| **Reactivate** | Restores sign-in. Restores nothing else, because nothing else was removed |
| **Assign an organisation** where there was none | Permitted |
| **Change** an existing organisation | **Guarded.** Their team memberships, management relationships and group memberships all belong to the old organisation and would become cross-organisation rows — which every P1-01 rule forbids. DESIGN must either refuse the change while any such row is current, or require them to be ended first. **This plan recommends refusing, and naming what to end** |

### 11C. Guarded purge — narrower than D-24

D-24 permits a permanent delete of four **structural master** types when nothing
references them. A user is not a structural master: it is a **person**, and the
record is the evidence that they were here.

| Entity | Purge in Release 1? |
| --- | --- |
| **User** | **Yes, but only in one narrow case — D-39.** A user who has **never signed in**, holds **no membership of any kind, current or historical**, appears in **no management relationship**, and is **not the bootstrap administrator**. In other words: an onboarding mistake, removed before it became a person's history. **Any user who has ever signed in is deactivated, never purged** |
| **Pending provisioning record** | **Does not exist.** D-33 = A creates the user directly; there is no intermediate record to cancel. The mistyped-Object-ID correction path is the user purge above |
| **Group** | **Yes** — only with **no membership history at all**. One member ever, even ended, and it deactivates instead |
| **Group membership** | **NO.** Ending is the operation. A membership that can be erased is not evidence |

**Deactivation is the default answer everywhere.** Purge exists for the typo, not
for the departure. DESIGN must make the screens say that, because an
administrator reaching for "remove" usually means "they have left".

---

## 12. ⚠ The authorisation boundary — the one this unit is most likely to break

**Creating a user, or putting somebody in a group, grants NOTHING.** Not business
domain access, not scope, not sensitivity, not management authority, not System
Administrator, not any future P1-05 entitlement.

| Guard | How |
| --- | --- |
| `users.platform_role` is **not editable in P1-03** | No screen, no field, no route, no service method writes it. The only writer stays P1-00's bootstrap redemption |
| `PlatformRole` keeps **exactly one case** | The existing test asserting the case count is left in place and must not be relaxed |
| **No `roles`, `permissions`, `entitlements`, `scopes` or `domains` table** | `NoBusinessSchemaTest` already forbids them. P1-03 does not touch that list |
| **A group has no permission surface** | `groups` carries name, code, description, status, organisation — and no column that could be read as a grant. Adding one is P1-05's decision, not a convenience |
| **Nothing consults group membership for access** | §17 asserts it: the only consumer of `group_memberships` in Release 1 is the Groups screen itself |
| **`RequireSystemAdministrator` unchanged** | Reused as-is. P1-03 adds no role check of its own |

**SYS-004 restated:** System Administrator status grants no Finance, People or
Sales access. P1-03 must not make group membership the back door that
`platform_role` is not.

**The seam must not grow.** `platform_role` is a temporary D-09 seam. P1-03 is
the first unit that will *want* to expand it — an administrator looking at a user
record will ask "how do I make them an administrator?" **The Release 1 answer is
that they cannot, from this screen**, and the screen should say so plainly rather
than hide the field.

---

## 13. Security, privacy and refusal behaviour

| Concern | Rule |
| --- | --- |
| **Personal data** | Users & Groups is the first unit holding personal data — names and email addresses of real people. Every screen is System Administrator only, and nothing here is exposed to any other viewer |
| **Security events** | Through the existing D-12 boundary, **adding no context key**. Candidate events: `user.provisioned`, `user.activated`, `user.deactivated`, `user.organisation.assigned`, `user.purged`, `group.created`, `group.updated`, `group.deactivated`, `group.purged`, `group.member.added`, `group.member.removed`, and `user.provision.refused` |
| **What events must NOT carry** | No email address, no display name, no group name. `SecurityEventLogger` has no key for free text and none is added; `user_id`, `entity_type`, `entity_id`, `result`, `reason` are enough to reconstruct what happened |
| **Refusals** | Business language, never a constraint violation, never an exception, never a stack trace. The P1-01 standard |
| **Enumeration** | The user list is administrator-only, so listing people is not a disclosure. **D-33 = A removes the directory-oracle risk entirely**: SemantIQ never queries Entra during provisioning, so no refusal can reveal whether an address exists there. A duplicate refusal reveals only what the administrator can already see on the Users list |
| **Directory identifiers** | Object id and tenant id masked by default, per **D-37**, reusing P1-02's D-27 pattern rather than inventing a second one |
| **Deactivation timing** | Stated on screen: access ends at the person's **next request**, not instantly, because that is what P1-00 actually does |

---

## 14. Carried verification gates — P1-03 closes two, and moves one

**P1-03 is not accepted until gates 1 and 2 have been executed against genuine
production users and recorded with observed output.**

| # | Gate | From | Closed by | Where |
| --- | --- | --- | --- | --- |
| 1 | **Live management-cycle refusal** | **P1-01**, `PHASE-1-PLAN.md` §10 | With two legitimate users: **Set** a manager, **Change** it, **Clear** it, then attempt a real cycle — A manages B, then make B manage A — and observe the refusal | **P1-03** |
| 2 | **Non-System-Administrator route refusal** | **P1-02** | A genuine non-administrator signs in and cannot reach Identity & SSO | **P1-03** |
| 3 | **Provider-wide Re-check lock** | **P1-02** | Two legitimate System Administrators press *Re-check now* within five minutes; the second is told a live check ran moments ago | **MOVED TO P1-05** |

### Why gate 3 moves rather than being attempted here

**P1-03 explicitly cannot assign `platform_role`** (§12). So the only way to
produce a second System Administrator during this unit would be to write that
column by some route P1-03 is defined as not having — or to manufacture a
privileged production account for the sake of a test.

**Neither is acceptable.** Creating privileged production access for no business
reason is worse than an open gate.

**Moved to P1-05 — Roles & Access**, where creating and assigning legitimate
administrative roles is the unit's actual subject. If a second genuine System
Administrator happens to exist before then, the observation can be made earlier
and recorded; nothing prevents that.

**Automated evidence continues to stand until then** —
`IdentityHealthTest::test_the_probe_lock_holds_across_administrators`, proven
non-vacuous by the mutation *key the lock by user*.

### Gates 1 and 2, and what they need

Both close naturally, because P1-03's entire purpose is that a second real person
exists. Gate 1 needs one more real user of any kind; gate 2 needs a real user who
is genuinely **not** an administrator — and since P1-03 creates every user with
`platform_role = NULL`, every user it creates is exactly that.

> **No fake or permanent production users are to be created to satisfy any
> gate.** The Product Owner provisions a genuine second user through the
> delivered screen, and the gates are observed against that person.

---

## 15. Schema and migration proposal

**A schema change IS expected here** — unlike P1-01's completion and P1-02, this
unit introduces new entities. It is stated up front rather than discovered.

### New tables

| Table | Columns |
| --- | --- |
| `groups` | `id`, `organisation_id` (FK, RESTRICT), `name`, `code` (nullable), `description` (nullable), `status`, timestamps. Unique `(organisation_id, name)`; unique `(organisation_id, code)` where present |
| `group_memberships` | `id`, `group_id` (FK), `user_id` (FK), `joined_at`, `left_at` (nullable), timestamps. Index on `user_id` |

**Two new tables, and no others.**

### Columns added to `users`

**NONE — D-34, approved.** The existing `users` table is reused exactly as it
stands: no `job_title`, no `notes`, and no provisioning or invitation table,
because D-33 = A needs none.

### Constraints of the house style, from P1-01 experience

- Index declared **before** the foreign key, so MySQL reuses it instead of
  creating a second one.
- Identifier names short and explicit — `MigrationIdentifierLengthTest` enforces
  MySQL's 64-character cap.
- `down()` drops constraints before columns, and is **exercised in CI** by the
  rollback-then-migrate step P1-01 added.
- **No seed and no backfill.** No production user is created by a migration.

### What is deliberately absent

No `roles`, no `permissions`, no `entitlements`, no `scopes`, no `domains`, no
`group_roles`, no `group_permissions`, no `user_provisionings`.
`NoBusinessSchemaTest` forbids most of them today and **that list is not
shortened by this unit**.

**If DESIGN discovers a further schema requirement, it stops and explains why
before adding it** — the standing instruction, and the reason P1-01 and P1-02
both ended up with the schema they said they would have.

---

## 16. Acceptance criteria

| # | Criterion |
| --- | --- |
| 1 | A System Administrator can bring a named person into SemantIQ by the approved mechanism, and that person can sign in |
| 2 | **A new user has no business access, no role, and no entitlement** — asserted against the boundary, not against an empty result |
| 3 | **Group membership grants nothing**, and nothing in the codebase consults it for access |
| 4 | `users.platform_role` cannot be changed from any P1-03 screen, route or service |
| 5 | `PlatformRole` still has exactly one case |
| 6 | Identity is still keyed on `(provider, external_subject, tenant_id)`; **no code path resolves a user for authentication by email** |
| 7 | A duplicate person is refused in business language, and the constraint makes it unrepresentable |
| 8 | Directory-owned fields are read-only and the screen says where they come from |
| 9 | Every lifecycle operation in §5 and §6 exists, or is explicitly and visibly absent |
| 10 | Deactivation is never blocked by dependencies; purge is only ever available in the narrow §11C cases |
| 11 | Changing a user's organisation cannot orphan P1-01 structure |
| 12 | User list search, filter and pagination work against seeded volume |
| 13 | No security event carries an email, a name or any free text |
| 14 | No schema forbidden by `NoBusinessSchemaTest` appears |
| 15 | **Carried gates 1 and 2 closed with observed production output.** Gate 3 is recorded as moved to P1-05, with its automated evidence standing |
| 16 | Screens meet the frozen design system, both themes, responsive, WCAG AA, **verified in a real browser** |
| 17 | Every guard proven non-vacuous by a recorded mutation |
| 18 | Product Owner test script delivered with all twelve elements |
| 19 | Explicit Product Owner acceptance. **A green CI run does not unlock P1-04** |

---

## 17. Negative tests

Each with the mutation that must make it fail.

| # | Case | Required outcome | Mutation |
| --- | --- | --- | --- |
| 1 | Anonymous, then authenticated non-administrator, on every Users & Groups route | **Refused** | Drop the administrator gate from one route |
| 2 | **A newly provisioned user has no business access** | Nothing granted | Give the provisioning path a role or an entitlement |
| 3 | **Group membership is consulted for access anywhere** | **It is not** — asserted by reading the code, since there is no access check yet to observe | Have any authorisation path read `group_memberships` |
| 4 | Any P1-03 route writes `platform_role` | **No such path** | Add one |
| 5 | `PlatformRole` gains a second case | Build fails | Add `organisation_administrator` |
| 6 | Authentication resolves a user by email | **It does not** | Make `IdentityResolver` fall back to email |
| 7 | Duplicate identity | Refused, in business language | Remove `users_identity_uq` |
| 8 | Two administrators provision the same person concurrently | One user, one refusal | Replace the constraint with a check-then-insert |
| 9 | **A malformed Object ID** | Refused, server-side | Validate in the browser only |
| 10 | **The identity key is editable after creation** | **No such route** | Make `external_subject` fillable from a screen |
| 11 | **`tenant_id` is typed by the administrator** | **It is not** — it comes from the configured tenant | Accept it from the request |
| 12 | Editing a directory-owned field | **No such field is editable** | Make `email` editable — and watch a sign-in overwrite it |
| 12b | **A provisional email is used for authentication, authorisation or duplicate resolution** | **It is not** | Resolve a user by email anywhere |
| 13 | Deactivating a user who manages people | Permitted, with the count shown | Silently refuse, or silently orphan the reports |
| 14 | Deactivating a user is blocked by a dependency | **Never blocked** | Add a dependency guard to deactivation |
| 15 | Purging a user who has ever signed in | **Refused** | Allow it |
| 16 | Purging a user with any membership history | **Refused** | Check only current memberships |
| 17 | Purging a group with ended memberships | **Refused** | Check only current members |
| 18 | Membership across two organisations | **Refused** | Drop the same-organisation check |
| 19 | Membership for a user with no organisation | **Refused** | Allow NULL to pass |
| 20 | A second current membership of one group | **Refused** | Allow stacking |
| 21 | A security event carries an email or a name | **Hard failure** | Add one to the context |
| 22 | **`Not signed in yet`** is shown when `last_signed_in_at` is NULL, and replaced by the real value afterwards | Shown correctly | Render an empty cell, or a date that is not there |
| 23 | A user list page returns every row | **Paginated** | Remove the limit |
| 24 | An unguarded DELETE route appears under Users & Groups | Build fails | Add one — `LifecycleCompletenessTest` extended to this unit |
| 25 | **Every §5 and §6 operation exists** | Build fails if one is missing | Delete a service method — the P1-01 presence-guard lesson, applied before implementation rather than after |

**Case 25 is the one this plan exists to make possible.** P1-01 shipped without
Update on four entity types and no test failed, because *an operation that does
not exist has no test to fail*. Naming every operation in §5 and §6 is what lets
a presence guard be written at all.

---

## 18. Decisions — APPROVED 2 September 2026

All seven were decided by the Product Owner on review of this plan. **The
recommendation and the decision agreed in every case except D-33**, where the
Product Owner took the more conservative option — and was right to.

### D-33 — User onboarding — **APPROVED: Option A, the administrator enters the Object ID**

**The plan recommended Option B and the Product Owner declined it.** Recorded in
their words: *"I do not want to weaken the P1-00 rule that immutable Entra Object
ID is the identity key. The current schema and P1-00 design deliberately exclude
email from the identity key because email can change or be reassigned."*

That is the correct call, and the reasoning is worth keeping: Option B's
convenience was real, and so was the weakening it required. **No Graph permission
is added.** §4 carries the full flow, the provisional-data rule, and the honest
statement that SemantIQ cannot confirm an Object ID exists.

**Consequently removed from the unit:** the `user_provisionings` table, the
email-binding callback change, any bootstrap reopening, and Microsoft Graph.

### D-34 — SemantIQ profile fields — **APPROVED: none in Release 1**

No `job_title`, no `notes`, no unnecessary `users` columns. Recorded: *"The PLAN
correctly identified these as duplicate profile data with no current business
decision requiring them."*

### D-35 — Groups — **APPROVED: SemantIQ-owned and flat**

No Entra group sync, no Graph, no nested groups, **no group-derived access in
P1-03**. Groups are organisational labels and membership containers only.
**P1-05 may later decide how groups participate in access; P1-03 must not
anticipate that decision.**

### D-36 — User deactivation — **APPROVED, with exact semantics**

- **Deactivation is always permitted.** Never blocked because the user manages
  people, belongs to teams, belongs to groups, or has historical records.
- **A dependency summary is shown before confirming**, in the Product Owner's
  own shape: *"This user currently manages 3 people, belongs to 2 teams and 1
  group. Deactivation stops their SemantIQ access but does not remove these
  relationships."*
- **Organisational history is never silently rewritten.** Current team, group and
  management relationships remain until an administrator changes them through
  the feature that owns them.
- **No "Force sign out now" in P1-03.** P1-00 revalidates active-user status on
  every protected request, so an inactive user is refused at their next request.
- **Reactivation restores authentication eligibility only.** It invents and
  rebuilds nothing, because nothing was deleted.
- **Organisation change stays guarded:** refused while current memberships or
  management relationships would become cross-organisation, telling the
  administrator what must be resolved first.

### D-37 — Directory identifiers — **APPROVED: masked, with explicit Reveal**

**Exactly the P1-02 D-27 pattern, reused.** No second reveal mechanism is
created. Tenant and Object IDs are not secrets, and are masked by default.

### D-38 — Screen structure — **APPROVED, with one simplification**

Route-backed tabs are **Users | Groups**, and nothing else. `Add User` and
`Add Group` are **actions on those screens, not tabs**. User and Group detail are
record pages, and every lifecycle action lives on the record it acts on. No
Invitations / Directory Sync tab; no User Lifecycle tab. *"This keeps the parent
feature simple and follows the established tab UX without turning actions into
navigation."*

### D-39 — Guarded purge — **APPROVED**

A **user** may be purged only when **all** hold:

- never signed in — `last_signed_in_at IS NULL`;
- no team membership, current or historical;
- no group membership, current or historical;
- no management relationship in either direction, current or historical;
- no other durable schema reference;
- not the bootstrap System Administrator;
- **the dependency guard re-checks inside the transaction.**

**Any user who has ever signed in is deactivated, never purged.**

A **group** may be purged only with zero membership history and no other durable
reference. **No membership history may be deleted. No cascade.**

**The same schema-driven dependency principle established by D-24 is used**, so
a future P1-05 foreign key becomes a blocker automatically rather than needing
somebody to remember.

Recorded: *"purge is for an onboarding mistake, not for a person leaving."*

---

## 19. What this plan deliberately does not do

- It does not build any part of P1-05. No role, permission, entitlement, scope or
  sensitivity, and no expansion of the `platform_role` seam.
- It does not make a group grant anything.
- It does not rebuild SSO, identity configuration or session policy.
- It does not change the P1-00 identity join rule.
- It does not add a structural entity to P1-01.
- It does not create an audit table.
- It does not create a production user, and it does not manufacture privileged
  accounts to close a verification gate.
- It does not add a Microsoft Graph permission, a provisioning table, an
  invitation flow or an email-binding step — **D-33 = A removed all four.**
- It does not manufacture a privileged production account to close a carried
  gate; gate 3 moves to P1-05 instead.

---

**P1-03 PLAN — APPROVED BY THE PRODUCT OWNER, 2 September 2026**, with D-33 to
D-39 decided as recorded in §18.

DESIGN follows. No migration, routes, controllers, screens or services have been
created, and no production user exists beyond the one the bootstrap made.
