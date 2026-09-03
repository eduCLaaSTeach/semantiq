# P1-04 — Business Domains: PLAN

**PLAN ONLY.** No design, no schema written, no migration, no route, no
controller, no service, no screen, no production data. §19 lists the decisions
the Product Owner must make before a DESIGN can be written.

Source of scope: `doc/SemantIQ_v2_PHASE_1_System_Administration.md` → **P1-04 —
Business Domains**, menu `System Administration → Business Domains`.

---

## 1. Purpose and business outcome

### The sentence this unit is built to make true

> *"These are the business intelligence domains this organisation has, who is
> accountable for each of them, and what each is expected to be available for —
> and none of that gives anybody access to any of it."*

Today SemantIQ has people (P1-03), an organisation structure (P1-01) and a way
in (P1-00/P1-02). It has **no vocabulary for what the intelligence is about.**
Every later unit needs that vocabulary before it can say anything useful:

| Unit | Needs domains for |
| --- | --- |
| **P1-05 Roles & Access** | Domain entitlements — the thing a role is granted *over* |
| **P1-06 Security Status** | Posture reported per domain rather than as one number |
| **P1-07 Access Reviews** | "Who can see Finance" is not answerable without Finance |
| Phase 2 Fabric | Data classification maps source data **to** a domain |

**The business outcome:** an administrator can state, in business language, what
this organisation's intelligence estate consists of and who owns each part of
it — before anyone is given access to any of it.

### The mistake this plan is built to prevent

**A domain is the most access-shaped object in Phase 1 so far.** A group at
least sounds inert. "Finance domain, owner Salil, enabled" reads like a grant to
almost every reader, and the pressure to make it one will be constant.

P1-03 had one sentence to defend — *a group grants nothing*. P1-04 has to defend
a harder one:

> **A domain existing, being enabled, or having an owner grants ZERO business
> access.** Not to its owner. Not to anybody.

**P1-05 owns roles, domain entitlements, scope, sensitivity ceilings and
effective access.** Every one of those is out of scope here, and §14 makes the
boundary a refusal rather than an intention.

The specific failure to guard against: **Domain Owner quietly becoming a role.**
Naming somebody accountable for Finance is a *business accountability
relationship*. If assigning it also grants them Finance data, then P1-04 has
built an entitlement model without a decision, a design or a review — which is
exactly how P1-05 arrives early through the back door.

---

## 2. Exact in scope / out of scope

### In scope

| # | Item |
| --- | --- |
| 1 | A **Business Domains** screen under System Administration |
| 2 | **Baseline domains** present in every deployment: Executive, Sales, Finance, People, Operations, Customer, Learning |
| 3 | **Custom domains** — an organisation's own domains beyond the baseline |
| 4 | **Enable / disable** a domain, meaning availability and readiness (§6) |
| 5 | **Domain owner** — assign, change, clear (§7), as accountability only |
| 6 | **Default access expectations** recorded as *stated intent*, not enforcement (§13) |
| 7 | A **description / purpose** per domain, in business language |
| 8 | **Sensitivity expectation** recorded as a statement of intent only — decision **D-47**, §19 |
| 9 | Read: list of domains, one domain's record |
| 10 | Refusals, confirmations, and the retention rules of §15 |
| 11 | Security events for every domain change, through the existing D-12 boundary |

### Out of scope — each owned elsewhere

| Item | Owner |
| --- | --- |
| **Role assignments** | P1-05 |
| **Domain entitlements** — who may see a domain | **P1-05** |
| **Scope assignments** | P1-05 |
| **Sensitivity ceilings** — the enforced value | P1-05 |
| **Access Simulator** | P1-05 |
| **Effective-access calculation** | P1-05 |
| **Any authorization that reads a domain** | P1-05 |
| Mapping source data to a domain | Phase 2 — Data Classification |
| Durable audit storage | P1-08 |
| Per-domain security posture | P1-06 |
| Adding or changing users, groups, teams | P1-03 / P1-01 |

### What already exists and must not be rebuilt

| Existing | Reuse |
| --- | --- |
| `RequireSystemAdministrator` | The gate on every route. Not re-implemented |
| `RequireOrganisation` | Domains belong to the organisation. Used where it lives (§16) |
| `PurgeDependencies` (`App\Shared\Lifecycle`) | The schema-driven dependency walk, if a purge is approved at all |
| `SecurityEventLogger` and the D-12 key boundary | Every event. No new context key without a decision |
| The frozen UI foundation | Shell, tabs, tables, refusal and confirmation banners, status pills |
| `StatusPill`, `Pagination`, the `org-*` classes | Directly |

**No navigation or branding change.** *Business Domains* is already a locked
roadmap entry in `ApprovedMenu`; P1-04 turns that one entry into a link, exactly
as P1-03 did for *Users & Groups*.

---

## 3. Screen and tab structure

**Recommendation: one screen and one record page. No tab strip.**

| Screen | Route shape | Contents |
| --- | --- | --- |
| **Business Domains** | `/console/domains` | The list: name, code, kind (baseline / custom), status, owner, sensitivity expectation |
| **One domain** | `/console/domains/{domain}` | The record: details, owner, enable/disable, expectations, and — for a custom domain — its lifecycle |

**Why no tabs.** P1-03 has two tabs because it delivers **two different kinds of
thing**, users and groups. P1-04 delivers **one** kind. Baseline and custom
domains are the same object with a different origin, and splitting them across
tabs would ask the reader to know which tab a domain lives in before they can
find it. A **Kind** column and a filter answer that without navigation.

**Route shape follows correction 1 of P1-03**: `/console/domains` and
`/console/domains/{domain}` place no dynamic segment at the depth of a static
one. If any sub-collection is ever added it goes under a static segment, never
beside `{domain}`.

> **Decision needed — D-40, §19:** is the menu label *Business Domains* and the
> route `/console/domains`, or should the route read `/console/business-domains`
> to match the label exactly?

---

## 4. Baseline-domain lifecycle — stated operation by operation

**The word "CRUD" does not appear in this plan.** P1-01's most expensive defect
was a delivered unit missing Update on four entity types because "CRUD" was
written where operations should have been named.

Seven baseline domains: **Executive, Sales, Finance, People, Operations,
Customer, Learning.**

| Operation | Available? | Notes |
| --- | --- | --- |
| **Create** | **NO** | The baseline set is fixed. A deployment gets all seven; an organisation that does not use one **disables** it |
| **Read — list / one** | **Yes** | |
| **Rename display name** | **Decision D-41, §19** | See below |
| **Edit description** | **Yes** | Business language, free text |
| **Enable / Disable** | **Yes** | §6. This is how an unused baseline domain is put away |
| **Assign / change / clear owner** | **Yes** | §7 |
| **Edit default access expectations** | **Yes** | §13 — a statement, not a control |
| **Delete / purge** | **NO — no such route** | A baseline domain is part of the product's vocabulary. Disabling is the operation |
| **Change its identity code** | **NO** | §11 — the stable key, never editable |

### Rename, and why it needs a decision

Allowing rename is *useful*: an organisation that calls it *Commercial* rather
than *Sales* should see its own word. Allowing rename is also *dangerous* if the
name is the identity — every later reference breaks, and two deployments become
incomparable.

**This plan recommends: renaming the DISPLAY NAME is permitted, and the
IDENTITY CODE is immutable.** `finance` stays `finance` forever; its label may
read *Financial Performance*. That is the same shape as P1-03's identity key
being immutable while display information is not, and it is why §11 separates
the two.

---

## 5. Custom-domain lifecycle — stated operation by operation

| Operation | Available? | Notes |
| --- | --- | --- |
| **Create** | **Yes** | Name, code, description, optional owner |
| **Read — list / one** | **Yes** | |
| **Edit** | **Yes** | Display name, description. **Not** the code — §11 |
| **Enable** | **Yes** | §6 |
| **Disable** | **Yes** | §6 |
| **Assign owner** | **Yes** | §7 |
| **Change owner** | **Yes** | §7 — the previous owner is retained as history, §15 |
| **Clear owner** | **Decision D-42, §19** | Whether a domain may exist with nobody accountable |
| **Guarded purge** | **Proposed, narrowly — decision D-43, §19** | §15.3 |
| **Convert to baseline / baseline to custom** | **NO** | The two origins are not interchangeable |

**"Custom Domains" is not itself a domain.** It appears in the source scope list
alongside the seven, but it names the *capability* to add your own, not an
eighth baseline entry. The plan reads it that way; **D-44 (§19) asks the Product
Owner to confirm it**, because reading it wrongly would seed a meaningless
record into every deployment.

---

## 6. Enable / disable semantics

**This is the most easily-confused thing in the unit, so it is defined by what
it is NOT.**

| Disabling a domain **does** | Disabling a domain **does NOT** |
| --- | --- |
| Say the organisation is **not currently using** this domain | Remove anybody's access — there is none to remove |
| Remove it from pickers where a **new** thing would be attached to it | Change any entitlement — none exist |
| Mark it **not ready** for the units that come later | Delete the domain, its owner, or its history |
| Give P1-05 a documented signal it must decide how to honour | **Grant anything to anybody** |

**In P1-04, enabled/disabled describes AVAILABILITY AND READINESS, not
authorization**, because there is no authorization to describe. That is stated
plainly on the screen, not left for a reader to infer.

### The trap DESIGN must be held to

> **Disabling a domain must never be capable of BROADENING access.**

This is not hypothetical. The natural P1-05 implementation of "disabled" is *a
filter that removes the domain from a set*. Written carelessly — a filter that
is skipped when the set is empty, a default that treats "no domains enabled" as
"no restriction" — disabling every domain becomes *allow everything*.

**P1-04 cannot test that, because P1-05 does not exist.** So P1-04 does two
things instead:

1. Ships **no code that reads `enabled` to make any decision**, and asserts that
   as a boundary test (§18).
2. **Carries the check forward as a gate on P1-05** (§17), stated in
   `PHASE-1-PLAN.md` §10 so it cannot be quietly lost — the register already lost
   two gates once.

---

## 7. Domain-owner assignment / change / clear semantics

### What a Domain Owner is

**An accountable person. A name to go to.** The person who answers *"is this
domain's intelligence right, and should it exist?"*

### What a Domain Owner is NOT

| Not | Why |
| --- | --- |
| A **role** | P1-05 owns the role model. `platform_role` is not written by P1-04, ever |
| An **entitlement** | Being owner grants no access to the domain's data |
| A **scope** | No organisational reach is conferred |
| A **sensitivity ceiling** | None is raised |
| A **permission to administer SemantIQ** | Owners are ordinary users |

**The owner of Finance cannot necessarily see Finance.** That sentence is
counter-intuitive and correct, and the screen must say it rather than leave the
reader to discover it.

### The operations

| Operation | Rule |
| --- | --- |
| **Assign** where there is none | Permitted. Any eligible user (§12) |
| **Change** | Permitted. The outgoing owner becomes history (§15), never erased |
| **Clear** | **Decision D-42, §19** |
| **Effect on the user** | **NONE.** No column on `users` is written. No role, no group, no membership |

### The guard that makes it true

**P1-04 must write nothing to `users`.** Not `platform_role`, not any new column.
The relationship lives entirely on the domain side. That is asserted as a
boundary test (§18, N4) in exactly the shape P1-03 used, because it is the same
risk wearing a different hat.

---

## 8. Exact field and data-point matrix

**Proposed. Every row is subject to DESIGN and to §19.**

### A domain

| Field | Type | Baseline | Custom | Editable | Notes |
| --- | --- | --- | --- | --- | --- |
| `id` | surrogate key | ✓ | ✓ | never | Internal |
| `organisation_id` | FK | ✓ | ✓ | never | D-16. Domains belong to the organisation |
| **`code`** | short string | ✓ | ✓ | **never** | **The stable identity.** §11 |
| **`name`** | string | ✓ | ✓ | **yes** (D-41) | The display name |
| `description` | text | ✓ | ✓ | yes | Business language, what this domain covers |
| `kind` | enum `baseline` / `custom` | ✓ | ✓ | never | Origin. Not convertible |
| `status` | enum `enabled` / `disabled` | ✓ | ✓ | yes | §6 |
| `owner_user_id` | FK → `users`, nullable | ✓ | ✓ | yes | §7. Nullable pending D-42 |
| `access_expectation` | enum — §13 | ✓ | ✓ | yes | **A stated expectation, not a control** |
| `sensitivity_expectation` | enum — D-47 | ✓ | ✓ | yes | **A statement of intent.** Not a ceiling |
| `created_at` / `updated_at` | timestamps | ✓ | ✓ | never | |

### Owner history

| Field | Notes |
| --- | --- |
| `domain_id` | FK |
| `user_id` | FK. The person who was accountable |
| `assigned_at` / `ended_at` | Datetimes. **Not dates** — the P1-01 collision, already paid for once in P1-03 |
| `ended_at IS NULL` | Means current |

**One current owner at most**, held the way P1-03 holds current membership: a
locking read inside the write transaction, because MySQL 8.4 has no partial
index. That mechanism is proven and is reused rather than reinvented.

### What is deliberately ABSENT

**No column that could be read as a grant.** No `role`, `permission`, `scope`,
`entitlement`, `ceiling`, `grantee`, `allow`, `deny`, `visible_to`. §18 N3b
asserts the physical column set and fails on any column name containing those
words — the guard P1-03 proved non-vacuous.

---

## 9. Source of truth for every field

| Field | Source of truth | Consequence |
| --- | --- | --- |
| `code` | **SemantIQ, at creation.** Baseline codes ship with the product | Immutable. The join key for every later unit |
| `name` | **The organisation.** Their word for it | Freely editable if D-41 permits |
| `description` | The organisation | Editable |
| `kind` | SemantIQ | Set at creation, never changed |
| `status` | The organisation | Editable |
| `owner_user_id` | The organisation, chosen from **existing genuine SemantIQ users** (P1-03) | No user is created by P1-04 |
| `access_expectation` | The organisation | **A statement. P1-05 decides what, if anything, honours it** |
| `sensitivity_expectation` | The organisation | Same |

**Nothing here is sourced from Microsoft Entra**, and P1-04 adds no Graph
permission. Domains are a SemantIQ concept.

---

## 10. Validation and uniqueness rules

| Rule | Applies to | Refusal |
| --- | --- | --- |
| `name` required, ≤ 255 | Both | Business sentence |
| `name` unique per organisation | Both | *"A domain called that already exists."* |
| `code` unique per organisation | Both | *"That code is already used by another domain."* |
| `code` required for custom, ≤ 32 | Custom | |
| `code` matches the pattern in §11 | Custom | Named, not a regex shown to the user |
| `description` ≤ 500 | Both | |
| Owner must be eligible (§12) | Both | §12's sentence |
| Owner must belong to the same organisation | Both | D-16, exactly as P1-03 |
| `kind` never accepted from a request | Both | Ignored, not sanitised — P1-03's N11 shape |
| Baseline domains cannot be created or deleted | Baseline | No route exists |

**Every refusal is a business sentence.** P1-03 shipped a duplicate-group path
that raised a database integrity error, found only by reading the test script
back against the code. **The uniqueness constraints are the real guard; a
locking read exists so the administrator gets a sentence instead of a
constraint violation.** That pattern is already built and is reused.

---

## 11. Domain naming and code rules

### The code is the identity

| Property | Rule |
| --- | --- |
| Stability | **Immutable once created**, baseline and custom alike |
| Baseline values | Ship with the product: `executive`, `sales`, `finance`, `people`, `operations`, `customer`, `learning` |
| Shape | Lower-case, alphanumeric and hyphen, no spaces |
| Length | ≤ 32 |
| Uniqueness | Per organisation |
| Visibility | **Shown on the record page, read-only, described in business terms** — not hidden, because an administrator comparing two deployments needs it |

**Why immutable.** Every later unit joins to a domain: P1-05 entitlements, P1-06
posture, P1-07 reviews, Phase 2 classification. A mutable identity means those
references silently retarget. This is the same rule, for the same reason, as
P1-03's `external_subject`.

**Baseline identity survives a rename.** If D-41 permits renaming *Sales* to
*Commercial*, the code stays `sales`, and the domain remains the same domain in
every later unit and in any comparison between deployments.

### Reserved codes

Custom domains **may not use a baseline code**, even in a deployment where that
baseline domain was disabled. Refusal: *"That code is reserved for a standard
domain."*

---

## 12. Owner eligibility rules

**Owners are chosen from existing genuine SemantIQ users.** P1-04 creates no
user and provisions nobody.

| Candidate | May be newly assigned? | May remain as current owner? | Retained in history? |
| --- | --- | --- | --- |
| Active user, same organisation | **Yes** | Yes | Yes |
| **Inactive** user | **NO — decision D-45, §19** | **Decision D-45** | **Yes, always** |
| User with **no organisation** | **No.** D-16 fails closed, exactly as group membership does | n/a | n/a |
| User in a **different organisation** | **No** | n/a | n/a |
| A **group** | **No.** Accountability is a person. A group owner is a committee, and a committee is not accountable | n/a | n/a |

### The inactive-owner question, stated properly

Three distinguishable things, and D-45 must answer all three:

1. **Newly assigning** an inactive user as owner — this plan recommends **refuse**.
   Naming someone who cannot sign in as accountable is a fiction.
2. **A current owner who is later deactivated** — this plan recommends
   **permitted, and surfaced**: the domain shows *"Owner is inactive"* and the
   administrator is prompted to reassign. Refusing deactivation would make P1-03's
   safe action unsafe, which D-36 forbids.
3. **Historical owners who are inactive** — **always retained.** History is not
   rewritten because somebody left.

---

## 13. What "default access expectations" means without building access control

This is the phrase from the source scope, and it is the one most likely to be
built as a control by accident. **In P1-04 it is a written statement of intent.
Nothing reads it. Nothing enforces it.**

Proposed shape: one value per domain, from a small fixed vocabulary.

| Value | Means | Enforced by P1-04? |
| --- | --- | --- |
| `broad` | *"Most people in the organisation would be expected to see this."* | **No** |
| `restricted` | *"Only specific roles would be expected to see this."* | **No** |
| `confidential` | *"Access should be exceptional and reviewed."* | **No** |
| `undecided` | *"Not yet determined."* — the honest default | **No** |

### How the screen must say it

The screen states, in words the administrator reads:

> **This is a statement of intent. It does not grant or restrict anything today.
> Access is assigned in Roles & Access.**

Anything softer — a lock icon, a shield, the word *policy*, a coloured
"security" badge — implies enforcement that does not exist, and is exactly the
overclaim `CLAUDE.md` §4 forbids. **P1-02 was corrected for the same class of
mistake**: it was not permitted to say *"Sign-in works"* when it had only
checked that settings loaded.

### Why record it at all

Because P1-05 needs the organisation's intent as an **input to a human
decision**, and capturing it while the domain is being defined is far better
than reconstructing it later. It is a note to the future, deliberately inert.

---

## 14. Security and refusal rules

| # | Rule |
| --- | --- |
| S1 | Every route behind `RequireSystemAdministrator` **and** `RequireOrganisation` |
| S2 | Anonymous and authenticated non-administrators refused on **every** route |
| S3 | **No route writes `platform_role`.** No request field could |
| S4 | **No code outside the Domains module reads a domain to make an authorization decision.** Asserted across the whole application source, as P1-03 does for group membership |
| S5 | A domain of another organisation is **Not Found**, never *forbidden* — the P1-03 guard, reused |
| S6 | `kind` and `code` are never accepted from a request after creation |
| S7 | No security event carries a person's name, email or identifier — the D-12 key boundary is unchanged and no new key is added without a decision |
| S8 | Every refusal is a business sentence. **No database or constraint wording ever reaches an administrator** |
| S9 | Every write confirms itself, in past tense, carrying no business content |
| S10 | **Assigning an owner changes nothing about that user.** Asserted behaviourally, not only in source |

---

## 15. Dependency and historical-retention rules

### 15.1 What will point at a domain

**Today: nothing.** P1-04 is the first unit to create domains.

**Tomorrow: a great deal** — P1-05 entitlements, P1-06 posture, P1-07 reviews,
P1-08 audit, Phase 2 classification.

**That asymmetry is the whole of §15.** A domain is nearly free to delete today
and will be nearly impossible to delete in three units' time. The rules must be
written for the second state, not the first.

### 15.2 What is retained

| Thing | Retained? |
| --- | --- |
| Owner history | **Always.** Ending an ownership sets `ended_at`; the row stays |
| A disabled domain | **Always.** Disabling deletes nothing |
| A renamed domain | **Always the same domain.** The code did not change |
| A baseline domain | **Always.** No delete route exists |

### 15.3 Guarded purge — proposed narrowly, decision D-43

**Recommendation: permit permanent removal of a CUSTOM domain only, and only
when all of these hold.**

| # | Condition |
| --- | --- |
| 1 | `kind = custom` — never a baseline domain |
| 2 | **No owner has ever been assigned** — not current, not historical |
| 3 | Nothing references it, by the schema-driven `PurgeDependencies` walk |
| 4 | Re-checked **inside** the write transaction, as D-24 and D-39 require |

That is deliberately narrower than D-39's user purge. It exists for **the domain
created by mistake five minutes ago**, and for nothing else.

> **⚠ The condition that will matter later.** `PurgeDependencies` is
> schema-driven, so a foreign key added by P1-05 becomes a blocker with no change
> to P1-04. That is the mechanism working — **but only for references expressed
> as foreign keys.** P1-03 found `bootstrap_grants.consumed_by_user_id` carrying
> no constraint, invisible to the walk, and needing an explicit check. DESIGN
> must ask the same question of every future reference rather than trusting the
> walk to be total.

**If D-43 is declined, disable is the only lifecycle**, which is a defensible
answer and simpler to defend.

---

## 16. Schema and migration proposal

**Proposed only. Nothing is written until DESIGN is approved.**

| Table | Purpose |
| --- | --- |
| `business_domains` | One row per domain. Columns per §8 |
| `business_domain_owners` | Ownership periods. `assigned_at` / `ended_at` as **DATETIME**, no uniqueness on `assigned_at` |

**The membership lesson is applied at the schema, not discovered again.** P1-01
keyed team membership on `(team_id, user_id, joined_at)` over dates and could
not represent two periods in one day; P1-03 paid for that with a correction.
Owner history has exactly the same shape and takes the fixed form from the start.

Constraints: `organisation_id` FK; unique `(organisation_id, code)`; unique
`(organisation_id, name)`; index on `owner_user_id`. Identifier names kept within
MySQL's 64-character limit — `MigrationIdentifierLengthTest` already asserts it.

`RequireOrganisation` is used **where it lives**, in the Organisation module. It
depends on `OrganisationService`, so promoting it to Platform would make Platform
depend backwards — settled as correction 3 of P1-03 and not reopened.

### How the seven baseline domains enter a deployment

**This must be decided, not defaulted — D-46, §19.** Three mechanisms, with the
recommendation stated.

| Option | Mechanism | For | Against |
| --- | --- | --- | --- |
| **A** | **Migration inserts all seven** | Simple; every deployment identical; no screen needed | **A migration writing business rows.** Fights the standing rule against seeding business data, and cannot know the organisation — which does not exist when migrations run |
| **B — recommended** | **Controlled initialisation at Company Profile creation**, in the same transaction that creates the organisation | The organisation exists, so `organisation_id` is real. One explicit place. Testable. Mirrors D-16, which already associates the creating administrator there | Touches a P1-01 path — a change that must be small, reviewed and asserted |
| **C** | **A static catalogue in code**, with rows created on first use | No seeding at all | Two sources of truth for what a domain is; every screen must merge them |

**Recommendation: B.** It is the only option where `organisation_id` is knowable,
and it puts the decision in one reviewable place rather than in a migration that
runs before the organisation exists.

**Whichever is chosen, it is stated and not silent.** The standing rule is *do
not seed business data without saying why*; the why here is that the seven
baseline domains are **product vocabulary, not the organisation's data** — the
organisation's contribution is which ones it enables, what it calls them, and who
owns them.

**Existing deployments.** Production already has an organisation and will not run
Company Profile creation again. DESIGN must state how the seven arrive there —
almost certainly a one-off idempotent initialisation, keyed on `code` so running
it twice changes nothing.

---

## 17. Acceptance criteria

P1-04 is complete when a System Administrator can:

| # | Criterion |
| --- | --- |
| 1 | Reach **Business Domains** from the sidebar, as a link |
| 2 | See the seven baseline domains, correctly marked as baseline |
| 3 | Read one domain's record: name, code, description, kind, status, owner, expectations |
| 4 | **Disable** a baseline domain not used by this organisation, and **enable** it again |
| 5 | **Create** a custom domain; be refused a duplicate name, a duplicate code, and a reserved baseline code — each in a business sentence |
| 6 | **Assign** an owner from existing users; **change** it and see the previous owner retained as history |
| 7 | Confirm, on screen, that the owner **has gained no access** — the sentence is present and true |
| 8 | Record a default access expectation, and read the statement that it enforces nothing |
| 9 | Be refused every operation §14 forbids, in business language |
| 10 | See every write confirm itself |
| 11 | Read every screen in both themes and at small width, with no implementation wording |

### Carried gate created by this unit

| Gate | To | Why |
| --- | --- | --- |
| **Disabling a domain must not broaden access** | **P1-05** | P1-04 ships no code that reads `enabled` to decide anything, so the failure is unreachable and untestable here. It becomes reachable the moment P1-05 builds effective access, and must be tested there. **To be recorded in `PHASE-1-PLAN.md` §10 when P1-04 is accepted** |

---

## 18. Negative and mutation cases

Every guard broken deliberately and observed to fail, per `CLAUDE.md` §2.

### The boundary — the ones that matter most

| # | Case | Mutation |
| --- | --- | --- |
| N1 | Anonymous, and authenticated non-administrator, on **every** route | Drop the gate from one route |
| N2 | **Assigning a Domain Owner changes nothing about that user** — no column on `users`, no role, no group, no membership | Have the assignment write `platform_role` |
| N3 | **Nothing outside the Domains module reads a domain to authorize anything** | Have any authorization path read `business_domains` |
| N3b | `business_domains` and `business_domain_owners` have **exactly** their physical column sets, timestamps included; **no column name contains** role, permission, scope, sensitivity, entitlement, ceiling, grant, allow or deny | Add `grantee_role`; add it **and** update the expected list without reading why it is there |
| N4 | **No P1-04 path writes `platform_role`** | Add it to a request |
| N5 | `PlatformRole` still has one case | Add a second |
| N6 | **A domain owner gets exactly the same answer from every route as a non-owner** | Have any route consult ownership |

### Domain identity and lifecycle

| # | Case | Mutation |
| --- | --- | --- |
| N7 | `code` cannot be edited after creation, on **any** route | Make it fillable |
| N8 | `kind` posted in a request is ignored | Accept it from input |
| N9 | A baseline domain **cannot be created or deleted** — no route exists | Register a DELETE for it |
| N10 | A custom domain may not use a **reserved baseline code**, even when that baseline is disabled | Check only the enabled ones |
| N11 | Duplicate name and duplicate code refused **in business language** | Remove the pre-check and let the constraint surface |
| N12 | Renaming a domain leaves its `code`, and every reference to it, unchanged | Key anything on the name |
| N13 | The application's DELETE routes are **exactly** the approved set | Add one |
| N14 | **Every operation named in §4 and §5 exists** as a service method | Delete one — the P1-01 presence guard |
| N15 | Every write **confirms itself** | Return a bare redirect |
| N16 | Route sets are **structurally disjoint**, and resolve identically when the route file is **reversed** | Put a dynamic segment at a static segment's depth |

### Owner

| # | Case | Mutation |
| --- | --- | --- |
| N17 | An owner from another organisation is refused | Drop the same-organisation check |
| N18 | A user with **no organisation** cannot be an owner | Let NULL pass |
| N19 | At most **one current owner**, held by a locking read inside the transaction | Remove the lock; move the check outside |
| N20 | Changing owner **retains** the previous one as history | Have it update the row in place |
| N21 | Owner history **cannot be deleted** | Add a delete route |
| N22 | Two ownership periods **on one calendar day** are both recorded | Key ownership on `(domain_id, assigned_at)` over dates — the P1-01 collision |
| N23 | Deactivating a user **does not** clear their domain ownership | Have it clear them |
| N24 | An inactive user cannot be **newly** assigned, per D-45 | Allow it |

### Expectations, enable/disable, and presentation

| # | Case | Mutation |
| --- | --- | --- |
| N25 | **Nothing in the codebase reads `access_expectation` or `sensitivity_expectation` to make a decision** | Have any path branch on it |
| N26 | **Nothing reads `status` to make an authorization decision** | Have any path branch on it |
| N27 | The screen states that expectations enforce nothing | Remove the sentence; add a lock icon or the word *policy* |
| N28 | A disabled domain is **not deleted** and keeps its owner and history | Have disable clear the owner |
| N29 | No security event carries a name, email or identifier | Add one to the context |
| N30 | Search, filter and pagination work against seeded volume | Remove the limit; ignore the filter |
| N31 | Every Domains surface is readable in both themes | Use a raw semantic hex |
| N32 | **No database integrity error ever reaches the administrator** | Let a constraint surface instead of the service refusing first |

**N14, N13 and N9 are the presence guards**, and they exist because of P1-01:
*an operation that does not exist has no test to fail.* §4 and §5 name every
operation precisely so these can be written before the implementation.

---

## 19. Product Owner decisions required before DESIGN

**No DESIGN is written until these are answered.**

| # | Decision | Options | Plan recommends |
| --- | --- | --- | --- |
| **D-40** | Route and label | `/console/domains` vs `/console/business-domains`; label *Business Domains* | `/console/domains`, label *Business Domains* — shorter route, label unchanged from the approved menu |
| **D-41** | May a **baseline** domain's display name be changed? | (a) No — fixed vocabulary; (b) **Yes, display name only, code immutable** | **(b)** — the organisation's word, SemantIQ's identity |
| **D-42** | May a domain exist with **no owner**? | (a) Owner mandatory; (b) **Owner optional, unassigned surfaced as needing attention** | **(b)** — mandatory ownership forces a fictitious name at the moment of creation |
| **D-43** | Is there a **guarded purge** for custom domains? | (a) **Yes, on the four conditions in §15.3**; (b) No — disable only | **(a)**, narrowly. It exists for the domain created by mistake, not the domain no longer used |
| **D-44** | Is **"Custom Domains"** the capability to add your own, or an eighth baseline domain? | (a) **The capability**; (b) A baseline domain | **(a)** — reading it as (b) seeds a meaningless record into every deployment |
| **D-45** | **Inactive users and ownership** — three separate answers | Newly assign: **refuse** / permit · Current owner deactivated: **permit and surface** / refuse · Historical: **always retained** | As marked. Refusing deactivation would make P1-03's safe action unsafe, which D-36 forbids |
| **D-46** | How do the **seven baseline domains** enter a deployment? | (a) Migration; (b) **Controlled initialisation at Company Profile creation**; (c) Static catalogue | **(b)** — the only option where `organisation_id` is knowable. And how they reach the **existing** production deployment, which will not run that path again |
| **D-47** | Is **`sensitivity_expectation`** in scope at all for P1-04? | (a) **Yes, as an inert statement of intent**; (b) No — defer entirely to P1-05 | **(a)**, provided §13's wording rules hold. It is the organisation's intent, best captured while the domain is being defined |
| **D-48** | The **`access_expectation`** vocabulary | The four values in §13, or a different set | The four in §13, `undecided` as the honest default |

---

## 20. What this plan deliberately does not build

Stated so that DESIGN cannot quietly widen the unit.

- **No role assignment.** P1-05.
- **No domain entitlement.** P1-05 — this is the single most likely thing to be built by accident, because a domain and an entitlement look alike on a screen.
- **No scope.** P1-05.
- **No sensitivity ceiling.** The *enforced* value is P1-05's; only an inert statement of intent is proposed here, subject to D-47.
- **No Access Simulator.** P1-05.
- **No effective-access calculation.** P1-05.
- **No domain-derived authorization of any kind.** P1-05.
- **No mapping of source data to a domain.** Phase 2.
- **No durable audit table.** P1-08.
- **No navigation or branding change.** The UI foundation is frozen.
- **No production data created by anybody but the Product Owner.**

---

**P1-04 PLAN — awaiting Product Owner review.** Nine decisions in §19.
