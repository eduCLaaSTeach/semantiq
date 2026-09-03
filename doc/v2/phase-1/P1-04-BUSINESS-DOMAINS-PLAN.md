# P1-04 — Business Domains: PLAN

**PLAN ONLY.** No design, no schema written, no migration, no route, no
controller, no service, no screen, no production data.

**APPROVED BY THE PRODUCT OWNER, 3 September 2026**, with decisions **D-40 to
D-48 answered** and one schema correction. §19 records each decision as given.
Where a decision changed the plan, the affected section was rewritten rather
than annotated, so no superseded proposal is left standing to be built by
mistake.

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
| 8 | The **enable requires an active owner** rule — D-42, §6 |
| 9 | Read: list of domains, one domain's record |
| 10 | Refusals, confirmations, and the retention rules of §15 |
| 11 | Security events for every domain change, through the existing D-12 boundary |

### Out of scope — each owned elsewhere

| Item | Owner |
| --- | --- |
| **Role assignments** | P1-05 |
| **Domain entitlements** — who may see a domain | **P1-05** |
| **Scope assignments** | P1-05 |
| **Sensitivity — the whole dimension** | **P1-05.** D-47: P1-04 does not model it *at all*, not as a ceiling and not as an inert statement. Standard / Confidential / Restricted and the enforced ceilings are P1-05's, and P1-04 must not pre-model them |
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

> **D-40 — DECIDED.** The route is **`/console/domains`**. The user-facing label
> stays **Business Domains**, unchanged from the approved menu. The route is
> shorter than the label deliberately; the label is what a person reads and the
> route is not user-facing copy.

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
| **Rename display name** | **Yes** — D-41 | Display name only. The `code` never changes. See below |
| **Edit description** | **Yes** | Business language, free text |
| **Enable / Disable** | **Yes** | §6. This is how an unused baseline domain is put away |
| **Assign / change / clear owner** | **Yes** | §7 |
| **Edit default access expectations** | **Yes** | §13 — a statement, not a control |
| **Delete / purge** | **NO — no such route** | A baseline domain is part of the product's vocabulary. Disabling is the operation |
| **Change its identity code** | **NO** | §11 — the stable key, never editable |

### Rename — D-41, DECIDED

Renaming the **display name is permitted**; the **identity code is immutable**.

An organisation that calls it *Commercial* rather than *Sales* sees its own
word, and the domain remains `sales` in every later unit and in any comparison
between deployments. The Product Owner's own example: **`sales` may display as
*Commercial*, but its identity remains `sales`.**

That is the same shape as P1-03's identity key being immutable while display
information is not, and it is why §11 separates the two.

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
| **Clear owner** | **Conditionally** — D-42 | Permitted while the domain is **disabled**. **Refused while it is enabled** — assign a replacement, or disable first. §7 |
| **Guarded purge** | **Yes, narrowly** — D-43 | §15.3. Only a custom domain that has **never had an owner** and nothing referencing it |
| **Convert to baseline / baseline to custom** | **NO** | The two origins are not interchangeable |

**"Custom Domains" is not itself a domain — D-44, DECIDED.** It appears in the
source scope list alongside the seven, but it names the **capability** to add
your own, not an eighth baseline entry. Reading it the other way would have
seeded a meaningless record into every deployment.

**The baseline set is exactly seven, and closed:** Executive, Sales, Finance,
People, Operations, Customer, Learning.

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

### Enabling requires an active owner — D-42, DECIDED

> **A domain may not be ENABLED unless it has a current owner who is an active,
> eligible user.**

This is the rule that makes "enabled" mean *this organisation is actually using
this domain*, and it is why the baseline seven arrive **disabled** (§16, D-46).

| Transition | Rule |
| --- | --- |
| **Disabled → Enabled** | **Refused** unless a current owner exists and that owner is **active** and eligible (§12). Refusal names the remedy: *"Assign an owner before enabling this domain."* |
| **Enabled → Disabled** | Always permitted. Never refused, and it removes nothing |
| **Clear owner while ENABLED** | **Refused.** *"This domain is enabled. Assign a replacement owner, or disable it first."* |
| **Clear owner while DISABLED** | Permitted |
| **Change owner while enabled** | Permitted, and **one operation** — the outgoing period ends and the incoming one begins inside a single transaction. It is never *clear, then assign*, which would pass through a refused state |
| **The owner is later deactivated** | **Permitted. P1-03's deactivation is never blocked by P1-04** — §12, D-45 |

### The invariant is enforced at the transition, not held continuously

**This must be said plainly rather than discovered in DESIGN.** An enabled
domain **can** end up with an inactive owner, because deactivating a user is
P1-03's operation and P1-04 does not get to refuse it. If the rule were written
as a continuous database invariant, the only way to keep it true would be to
block a P1-03 deactivation — which **D-36 forbids**, because it would make a
safe action unsafe.

So the rule is enforced **at the two moments an administrator asks for it** —
enabling, and clearing an owner — and the drift state that P1-03 can create is
**surfaced rather than prevented**:

> **Needs attention — owner inactive.** Shown on the domain until it is
> reassigned. It is a prompt, not a refusal, and the domain stays enabled.

**The domain is not silently disabled** when its owner goes inactive. Disabling
is an administrator's decision about whether the organisation uses a domain, and
having it happen by side effect would make the status untrustworthy.

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

### There is ONE source of truth for the current owner — the schema correction

**The ownership-history table is authoritative. `business_domains` carries no
`owner_user_id` column.**

The first draft of this plan proposed both — a column *and* an open history row
— which is **two writable sources of truth for the same fact**. They can
disagree, and when they do nothing in the schema says which one is right. The
Product Owner rejected it before DESIGN, and the plan is corrected rather than
annotated.

| Question | Answer |
| --- | --- |
| Who owns this domain now? | The ownership row with **`ended_at IS NULL`** |
| Nobody owns it? | **No such row exists.** Absence, not a NULL column |
| Change of owner | End the current row, insert the next — **one transaction** |
| Clear the owner | End the current row. Nothing is inserted |
| Delete history | **No route. Ever** |
| Two current owners | **Impossible** — at most one open row, held transactionally (§8) |

**This also makes D-43 naturally safe.** Every ownership period, current or
ended, is a row with a foreign key to the domain — so a domain that has *ever*
had an owner is a domain the schema-driven `PurgeDependencies` walk already
refuses to purge. The rule and the mechanism agree without being made to.

### The operations

| Operation | Rule |
| --- | --- |
| **Assign** where there is none | Permitted. Any **active** eligible user (§12). Inserts one open row |
| **Change** | Permitted. Ends the current row and inserts the next **in one transaction**. The outgoing owner becomes history (§15), never erased, never updated in place |
| **Clear** | Permitted **while disabled**; **refused while enabled** — D-42, §6. Ends the current row |
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
| `access_expectation` | enum — §13 | ✓ | ✓ | yes | **A stated expectation, not a control.** Defaults to `undecided` |
| `created_at` / `updated_at` | timestamps | ✓ | ✓ | never | |

**Two columns are deliberately absent, and their absence is asserted (§18):**

| Absent | Why |
| --- | --- |
| **`owner_user_id`** | The ownership-history table is the single source of truth (§7). A column beside it would be a second one |
| **`sensitivity_expectation`** | **D-47.** P1-05 owns the whole sensitivity dimension — Standard, Confidential, Restricted and the enforced ceilings. P1-04 does not pre-model it, not even inertly. This also removed a real contradiction in the first draft, which proposed the column while N3b asserted that no column name may contain *sensitivity* |

### Ownership history — the authoritative record

| Field | Notes |
| --- | --- |
| `domain_id` | FK |
| `user_id` | FK. The person who was accountable |
| `assigned_at` / `ended_at` | **DATETIME. Not dates** — the P1-01 collision, already paid for once in P1-03 and then produced by production on its first day |
| `ended_at IS NULL` | **Means current.** This is the only definition of "current owner" in the system |

**One current owner at most**, held the way P1-03 holds current membership: a
locking read inside the write transaction, because MySQL 8.4 has no partial
index. That mechanism is proven and is reused rather than reinvented.

**No uniqueness on `(domain_id, assigned_at)`.** Two ownership periods on one
calendar day must both be recordable — the same rule, for the same reason, that
correction 4 of P1-03 exists for. N22 breaks it deliberately.

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
| **Current owner** — the open ownership row | The organisation, chosen from **existing genuine, active SemantIQ users** (P1-03) | No user is created by P1-04. No column on `users` is written |
| `access_expectation` | The organisation | **A statement. P1-05 decides what, if anything, honours it** |

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
| **Enable requires a current, active owner** | Both | *"Assign an owner before enabling this domain."* — D-42, §6 |
| **Clearing the owner of an enabled domain** | Both | *"This domain is enabled. Assign a replacement owner, or disable it first."* |
| `access_expectation` must be one of the four in §13 | Both | Anything else is rejected, not coerced |
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

**Baseline identity survives a rename.** D-41 permits renaming *Sales* to
*Commercial*; the code stays `sales`, and the domain remains the same domain in
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
| **Inactive** user | **NO** — D-45 | **Yes** — D-45. Surfaced as needing attention | **Yes, always** |
| User with **no organisation** | **No.** D-16 fails closed, exactly as group membership does | n/a | n/a |
| User in a **different organisation** | **No** | n/a | n/a |
| A **group** | **No.** Accountability is a person. A group owner is a committee, and a committee is not accountable | n/a | n/a |

### The inactive-owner question — D-45, DECIDED, all three parts

These are three different questions and the first draft was right to refuse to
answer them as one:

| # | Question | **Decided** |
| --- | --- | --- |
| 1 | **Newly assigning** an inactive user as owner | **REFUSED.** Naming someone who cannot sign in as accountable is a fiction |
| 2 | A **current owner who is later deactivated** | **PERMITTED, and surfaced.** P1-03's deactivation is **never blocked by P1-04.** The domain shows *"Needs attention — owner inactive"* until it is reassigned |
| 3 | **Historical** owners who are inactive | **ALWAYS RETAINED.** History is not rewritten because somebody left |

**Why 2 is permitted rather than refused.** Refusing a deactivation because the
user happens to own a domain would make P1-03's safe action unsafe — the thing
**D-36 exists to forbid** — and would mean an administrator could not offboard
somebody without first hunting through domains. The drift is real, so it is made
**visible** instead of impossible. §6 explains why that means the rule is
enforced at the transition rather than held continuously.

---

## 13. What "default access expectations" means without building access control

This is the phrase from the source scope, and it is the one most likely to be
built as a control by accident. **In P1-04 it is a written statement of intent.
Nothing reads it. Nothing enforces it.**

One value per domain, from a small fixed vocabulary. **D-48, DECIDED:**

| Value | Shown to the administrator as | Enforced by P1-04? |
| --- | --- | --- |
| **`undecided`** | **Not yet determined** — the default, and the honest one | **No** |
| **`broad`** | **Broad access is expected** | **No** |
| **`limited`** | **Access is expected to be limited to selected roles or functions** | **No** |
| **`exceptional`** | **Access is expected to be tightly limited and reviewed** | **No** |

### Why `confidential` and `restricted` were removed

The first draft used *restricted* and *confidential*. **Those two words belong to
the P1-05 sensitivity dimension** — Standard / Confidential / Restricted — and
reusing them here would put two different concepts behind one vocabulary.

The damage is not cosmetic. An administrator who sets a domain to *Confidential*
in P1-04 and later meets *Confidential* in P1-05 would reasonably assume they had
already answered that question, and a developer would reasonably assume the two
fields should agree. **This field is advisory and that one will be enforced**, so
they must not be able to be mistaken for each other.

`broad` / `limited` / `exceptional` describe **how widely access is expected to
be given**. Sensitivity describes **what the data is**. Different questions, and
now different words.

### How the screen must say it

The screen states, in words the administrator reads:

> **This is a statement of intent. It does not grant or restrict anything today.
> Access is assigned in Roles & Access.**

The value is **advisory only. Nothing may read it to make an authorization
decision**, in P1-04 or afterwards without a decision — N25 breaks that
deliberately.

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
| S11 | **P1-04 never refuses a P1-03 deactivation.** Owning a domain is not a reason a person cannot be offboarded — D-45, D-36 |
| S12 | **`access_expectation` is never read to make a decision**, by any code in the application |

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
| Owner history | **Always.** Ending an ownership sets `ended_at`; the row stays. **It is never updated in place and there is no route that deletes one** |
| A disabled domain | **Always.** Disabling deletes nothing |
| A renamed domain | **Always the same domain.** The code did not change |
| A baseline domain | **Always.** No delete route exists |

### 15.3 Guarded purge — D-43, APPROVED NARROWLY

**Permanent removal of a CUSTOM domain only, and only when all four hold.**

| # | Condition |
| --- | --- |
| 1 | `kind = custom` — never a baseline domain |
| 2 | **No owner has ever been assigned** — not current, not historical |
| 3 | **No durable schema reference exists**, by the schema-driven `PurgeDependencies` walk |
| 4 | The dependency check is **repeated inside the write transaction**, as D-24 and D-39 require |

**Once a domain has history, disable rather than purge.** That is the Product
Owner's wording and it is the whole rule: this exists for **the domain created
by mistake five minutes ago**, and for nothing else.

**Conditions 2 and 3 agree by construction.** Since the ownership-history table
is the source of truth (§7), every ownership period is a row with a foreign key
to the domain — so a domain that has ever had an owner is already a domain the
walk refuses. Condition 2 is stated **as well**, not instead: two independent
reasons to refuse, and the explicit one does not depend on anybody remembering
why the implicit one works.

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
| `business_domains` | One row per domain. Columns per §8. **No `owner_user_id`** |
| `business_domain_owners` | **The authoritative record of ownership.** Periods with `assigned_at` / `ended_at` as **DATETIME**, no uniqueness on `assigned_at`. `ended_at IS NULL` is the current owner |

**The membership lesson is applied at the schema, not discovered again.** P1-01
keyed team membership on `(team_id, user_id, joined_at)` over dates and could
not represent two periods in one day; P1-03 paid for that with a correction.
Owner history has exactly the same shape and takes the fixed form from the start.

Constraints: `organisation_id` FK; unique `(organisation_id, code)`; unique
`(organisation_id, name)`; on `business_domain_owners` a FK to the domain, a FK
to `users`, and an index supporting the *current owner* lookup. **No unique key
involving `assigned_at`** — that is the P1-01 collision, and it is refused here
rather than corrected later. Identifier names kept within MySQL's 64-character
limit — `MigrationIdentifierLengthTest` already asserts it.

`RequireOrganisation` is used **where it lives**, in the Organisation module. It
depends on `OrganisationService`, so promoting it to Platform would make Platform
depend backwards — settled as correction 3 of P1-03 and not reopened.

### How the seven baseline domains enter a deployment — D-46, DECIDED

**Controlled initialisation. Not migration seeding, and not a static
catalogue.** The seven are materialised **idempotently, once the Organisation
exists**. The three mechanisms as they were weighed:

| Option | Mechanism | For | Against |
| --- | --- | --- | --- |
| **A** | **Migration inserts all seven** | Simple; every deployment identical; no screen needed | **A migration writing business rows.** Fights the standing rule against seeding business data, and cannot know the organisation — which does not exist when migrations run |
| **B — recommended** | **Controlled initialisation at Company Profile creation**, in the same transaction that creates the organisation | The organisation exists, so `organisation_id` is real. One explicit place. Testable. Mirrors D-16, which already associates the creating administrator there | Touches a P1-01 path — a change that must be small, reviewed and asserted |
| **C** | **A static catalogue in code**, with rows created on first use | No seeding at all | Two sources of truth for what a domain is; every screen must merge them |

**B, DECIDED.** It is the only option where `organisation_id` is knowable, and
it puts the decision in one reviewable place rather than in a migration that runs
before the organisation exists.

### The initial state of a baseline domain — DECIDED, and it matters

| Field | Initial value |
| --- | --- |
| `code` | The standard immutable code — `executive`, `sales`, `finance`, `people`, `operations`, `customer`, `learning` |
| `name` | The standard display name |
| `status` | **Disabled** |
| Owner | **None.** No ownership row is created |
| `access_expectation` | **`undecided`** |

> **Do not pretend the organisation uses every baseline domain simply because
> SemantIQ knows the vocabulary.**

That single sentence is why the initial status is *Disabled* and not *Enabled*,
and it is the reason the D-42 enable rule is coherent: **enabling a domain is an
act**, performed by an administrator who has decided the organisation uses it and
has named somebody accountable for it. Arriving enabled would have made *enabled*
mean nothing on the first screen anybody sees.

### Three constraints on how initialisation is built

| # | Constraint |
| --- | --- |
| 1 | **No silent business-row seeding from a schema migration.** A migration writes structure, not the organisation's rows |
| 2 | **No mutation as a side effect of an ordinary GET request.** Opening the Business Domains screen must never be what creates the seven — a read that writes is untestable, unauditable, and races itself under two administrators |
| 3 | **Do not materially redesign P1-01.** DESIGN defines the **smallest safe integration point** into Company Profile creation, and it must be small enough to be read and asserted in full |

Constraint 2 is stated because it is the shortcut that would otherwise be
reached for: *materialise on first view* looks convenient and quietly makes a
read path a write path.

**Seeding is stated, not silent.** The standing rule is *do not seed business
data without saying why*. The why: the seven baseline domains are **product
vocabulary, not the organisation's data.** The organisation's contribution is
which of them it enables, what it calls them, and who owns them — and every one
of those starts empty.

### The existing production Organisation

Production already has an organisation and **will not run Company Profile
creation again**. It gets an **explicit, idempotent, one-time P1-04
initialisation**, keyed on `code`, so running it twice changes nothing and
running it after an administrator has renamed or enabled a domain changes
nothing either.

**Two distinct paths, both required, and DESIGN must specify both:** the one-time
initialisation for the deployment that already exists, and the integration point
for every Company Profile created afterwards.

---

## 17. Acceptance criteria

P1-04 is complete when a System Administrator can:

| # | Criterion |
| --- | --- |
| 1 | Reach **Business Domains** from the sidebar, as a link |
| 2 | See the seven baseline domains, marked as baseline, **all of them Disabled, none of them owned** |
| 3 | Read one domain's record: name, code, description, kind, status, current owner, ownership history, access expectation |
| 4 | **Be refused when enabling a domain that has no owner**, in a sentence naming the remedy |
| 5 | **Assign** an active owner, then **enable** the domain; **disable** it again |
| 6 | **Be refused when clearing the owner of an enabled domain**, and permitted once it is disabled |
| 7 | **Create** a custom domain; be refused a duplicate name, a duplicate code, and a reserved baseline code — each in a business sentence |
| 8 | **Change** an owner and see the previous one retained as history, with both periods visible |
| 9 | **Rename** a baseline domain and see its `code` unchanged |
| 10 | Confirm, on screen, that the owner **has gained no access** — the sentence is present and true |
| 11 | Record an access expectation from the four values, and read the statement that it enforces nothing |
| 12 | **Deactivate a user who owns a domain, and not be blocked** — then see that domain marked *Needs attention — owner inactive* |
| 13 | Be refused every operation §14 forbids, in business language |
| 14 | See every write confirm itself |
| 15 | Read every screen in both themes and at small width, with no implementation wording |

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
| N3c | **`business_domains` has no `owner_user_id` column** — the ownership table is the only source of truth (§7) | Add the column and have assignment write both. The test must fail on the column's *existence*, not on the two disagreeing, because a second source of truth is wrong even while it agrees |
| N3d | **No column anywhere in P1-04 has a name containing `sensitivity`** — D-47 defers the whole dimension to P1-05 | Add `sensitivity_expectation` back |
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
| N24 | An inactive user cannot be **newly** assigned — D-45 | Allow it |
| N24b | **Deactivating a domain owner is never refused by P1-04** — D-45, D-36. P1-03's operation still succeeds | Have the owner check refuse the deactivation |
| N24c | A domain whose current owner is inactive shows **Needs attention — owner inactive**, and is **still enabled** | Have the drift auto-disable the domain; remove the surfaced state |
| N24d | **Current owner is read from the open ownership row**, never from a cached or duplicated field | Answer it from anywhere else |
| N24e | Changing an owner is **one transaction** — it never passes through an ownerless state observable to anyone else, and never trips the enabled-domain clear-owner refusal | Implement change as *clear, then assign* |

### Expectations, enable/disable, and presentation

| # | Case | Mutation |
| --- | --- | --- |
| N25 | **Nothing in the codebase reads `access_expectation` to make a decision** | Have any path branch on it |
| N26 | **Nothing reads `status` to make an authorization decision** | Have any path branch on it |
| N26b | **Enabling a domain with no owner is refused**, in a sentence naming the remedy — D-42 | Drop the check; check only that an ownership row exists **ever**, rather than a current one |
| N26c | **Enabling a domain whose only owner is inactive is refused** | Check for a current owner without checking that they are active |
| N26d | **Clearing the owner of an ENABLED domain is refused**; clearing it while disabled is permitted | Allow it in both states; refuse it in both states |
| N26e | Disabling is **never** refused, whatever the owner state | Add any condition to disable |
| N27 | The screen states that expectations enforce nothing | Remove the sentence; add a lock icon or the word *policy* |
| N28 | A disabled domain is **not deleted** and keeps its owner and history | Have disable clear the owner |
| N29 | No security event carries a name, email or identifier | Add one to the context |
| N30 | Search, filter and pagination work against seeded volume | Remove the limit; ignore the filter |
| N30b | Baseline initialisation is **idempotent** — running it twice produces seven domains, not fourteen | Key it on anything but `code`; drop the existence check |
| N30c | Initialisation run **after** an administrator has renamed, enabled or assigned an owner **changes none of that** | Have it reset `name` or `status` to the standard value |
| N30d | The seven arrive **Disabled, unowned, `undecided`** — D-46 | Have initialisation create them enabled, or owned by the creating administrator |
| N30e | **No migration writes a `business_domains` row** — asserted against the migration source | Move initialisation into a migration |
| N30f | **No GET request creates a domain.** Loading the Business Domains screen with an empty table leaves it empty | Materialise the seven on first view |
| N31 | Every Domains surface is readable in both themes | Use a raw semantic hex |
| N32 | **No database integrity error ever reaches the administrator** | Let a constraint surface instead of the service refusing first |

**N14, N13 and N9 are the presence guards**, and they exist because of P1-01:
*an operation that does not exist has no test to fail.* §4 and §5 name every
operation precisely so these can be written before the implementation.

---

## 19. Product Owner decisions — ANSWERED, 3 September 2026

All nine were answered at PLAN review. **Each is now a decision of record and
DESIGN is bound by it.** Where an answer changed the plan, the section above was
rewritten; nothing superseded is left standing.

| # | Decision | **Answer** |
| --- | --- | --- |
| **D-40** | Route and label | **`/console/domains`.** User-facing label stays **Business Domains** |
| **D-41** | May a baseline domain's display name change? | **Yes — display name only.** The `code` is immutable. `sales` may display as *Commercial*; its identity remains `sales` |
| **D-42** | May a domain exist with no owner? | **Yes, temporarily — with three refinements.** A domain **may not be enabled** without a current, active, eligible owner. **Clearing the owner of an enabled domain is refused** — assign a replacement or disable first. An owner who later goes inactive **does not block their deactivation**; the domain shows **Needs attention — owner inactive** until reassigned |
| **D-43** | Guarded purge for custom domains? | **Yes, narrowly.** `kind = custom`, **no owner ever assigned**, no durable schema reference, dependency check repeated transactionally. **Once a domain has history, disable rather than purge** |
| **D-44** | Is *Custom Domains* a capability or an eighth baseline domain? | **A capability.** The baseline set is exactly seven and closed: Executive, Sales, Finance, People, Operations, Customer, Learning |
| **D-45** | Inactive users and ownership | **Three answers.** Newly assign an inactive user: **refused.** Deactivating an existing owner: **permitted.** An inactive current owner: **surfaced as needing attention.** Historical ownership: **always retained** |
| **D-46** | How do the seven baseline domains enter a deployment? | **Controlled initialisation** — not migration seeding, not a static catalogue. Materialised **idempotently once the Organisation exists**, as **standard code, standard name, Disabled, no owner, `undecided`**. The existing production Organisation gets an **explicit idempotent one-time P1-04 initialisation**; DESIGN defines the **smallest safe integration point** for future Company Profile creation. **No silent seeding from a migration. No mutation on a GET. No material redesign of P1-01** |
| **D-47** | Is `sensitivity_expectation` in scope? | **NO. Deferred entirely to P1-05.** P1-04 must not pre-model Standard / Confidential / Restricted or any ceiling |
| **D-48** | The access-expectation vocabulary | **Revised:** `undecided`, `broad`, `limited`, `exceptional`. **`confidential` and `restricted` are not used** — those words belong to the P1-05 sensitivity dimension. **Advisory only; nothing may read it for authorization** |

### The two corrections that changed the plan, not just annotated it

**D-47 resolved a real contradiction, not a preference.** The first draft
proposed a `sensitivity_expectation` column **while N3b asserted that no column
name may contain *sensitivity***. Those cannot both be built. One of them was
going to be quietly dropped during EXECUTE, and the odds are it would have been
the assertion rather than the column — a guard deleted to make a feature pass,
which is precisely the failure `CLAUDE.md` §2 exists to catch. The column is
gone and **N3d now breaks the rule deliberately** to prove the guard works.

**The ownership schema correction removed a second source of truth.** The first
draft proposed **both** `business_domains.owner_user_id` **and** an ownership
history table with an open row. Two writable records of one fact, able to
disagree, with nothing in the schema to say which is right. §7 now makes the
history table authoritative and the column does not exist; **N3c fails on the
column's existence**, not on the two disagreeing, because a duplicate source of
truth is wrong even during the period it happens to agree.

That correction also made **D-43 safe by construction**: every ownership period
is a foreign-key row, so a domain that ever had an owner is one the
`PurgeDependencies` walk already refuses.

---

## 20. What this plan deliberately does not build

Stated so that DESIGN cannot quietly widen the unit.

- **No role assignment.** P1-05.
- **No domain entitlement.** P1-05 — this is the single most likely thing to be built by accident, because a domain and an entitlement look alike on a screen.
- **No scope.** P1-05.
- **No sensitivity of any kind.** D-47: not the ceiling, not an inert statement, not the vocabulary. Standard / Confidential / Restricted are P1-05's words and P1-04 does not borrow them — which is also why `access_expectation` reads `limited` and `exceptional` rather than `restricted` and `confidential`.
- **No Access Simulator.** P1-05.
- **No effective-access calculation.** P1-05.
- **No domain-derived authorization of any kind.** P1-05.
- **No mapping of source data to a domain.** Phase 2.
- **No durable audit table.** P1-08.
- **No navigation or branding change.** The UI foundation is frozen.
- **No production data created by anybody but the Product Owner** — with one stated exception, the baseline initialisation of D-46, which creates **product vocabulary** in a disabled, unowned, undecided state and is described in §16 rather than done silently.
- **No change to the `srikanth@lithan.com` record.** It stays exactly as P1-03 left it. That is a separate operational item (`P1-03-USERS-GROUPS-VERIFICATION.md` §12.3) and P1-04 does not touch or purge it.

---

**P1-04 PLAN — APPROVED by the Product Owner, 3 September 2026**, with D-40 to
D-48 answered (§19) and the ownership schema corrected before DESIGN.

**Next: DESIGN.** No implementation, migration, route, screen or production
change until the DESIGN is approved in turn.
