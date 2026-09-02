# P1-03 — Users & Groups — DESIGN

**Status:** **DESIGN APPROVED — Product Owner, 2 September 2026**, with five
corrections applied as recorded in §0. Documentation only.
**Unit:** P1-03 (Phase 1 delivery order 5)
**PLAN:** `P1-03-USERS-GROUPS-PLAN.md` — **APPROVED 2 September 2026**, decisions
**D-33 to D-39**
**PLAN merge SHA:** `bc18725f76248e491a26a931168f8e062a8da296` (PR #82)
**Predecessors:** P1-00 **ACCEPTED** · P1-01 **ACCEPTED** · P1-02 **ACCEPTED**
**UI standard:** `doc/design-system/ui-and-ux-layout-template-shared.md` — **FROZEN**

> **Nothing here is implemented.** No migration, model, route, controller,
> screen, service, seed, test or production user has been created. **This is
> DESIGN only.**

---

## 0. Corrections applied at DESIGN review

| # | Correction | Where |
| --- | --- | --- |
| 1 | **The route collision was not removed, only renamed.** `/console/people/{user}` versus `/console/people/groups` is the identical dynamic-versus-static clash. Users move under `/console/people/users` | §2, §13.1, N15 |
| 2 | **The last active System Administrator cannot deactivate themselves.** A lockout-prevention guard, and the one exception to "deactivation is always permitted" | §7.1a |
| 3 | `PurgeDependencies` moves to `App\Shared\Lifecycle`. **`RequireOrganisation` does NOT move** — People depends on the accepted Organisation module rather than Platform depending backwards on it | §8.2, §13.2, §18 |
| 4 | **Group membership must survive leave → rejoin on the same day.** Timestamp precision, not a date-keyed uniqueness that collides | §6.2 |
| 5 | The `groups` schema guard asserts the **actual physical column set**, `created_at` and `updated_at` included | §12, N3b |

**Correction 1 was my error, and the evidence was already in this document:** N15
required route ordering and a numeric constraint to work, which is precisely the
proof that the collision had survived the rename. Renaming `users` to `people`
moved the clash; it did not remove it.

---

## 1. What this unit is, in one line

**It makes a second person possible, and gives them nothing.**

Today SemantIQ has one user and no way to admit another: bootstrap closes
permanently once an active System Administrator exists, and `IdentityResolver`
refuses an unknown identity by design. P1-03 opens the only other door — an
administrator naming a person by their immutable Entra Object ID — and then
spends most of its effort making sure that door hands out no access.

---

## 2. Module, prefix and naming

A new module, `App\Modules\People`, and a route prefix `/console/people`.

**Why not `Users`.** `App\Modules\Platform\Models\User` stays exactly where it is
— P1-00 owns the identity record — so a module also called `Users` would put two
different meanings of the word one directory apart.

**The prefix is `/console/people`, and users live under `/console/people/users`
— correction 1.**

The first version of this design put user records at `/console/people/{user}` and
the group list at `/console/people/groups`, and claimed that renaming the prefix
had removed a collision. **It had not.** A dynamic segment and a static one at
the same depth clash whatever the parent is called, and the proof was sitting in
this document's own test list: N15 required route-declaration order *and* a
numeric constraint to work. A guard that is needed is a collision that exists.

So the hierarchy is made unambiguous instead:

```
/console/people/users            /console/people/groups
/console/people/users/{user}     /console/people/groups/{group}
```

`users` and `groups` are both **static** segments at the same depth, and every
dynamic segment sits one level below a static one. **Correctness no longer
depends on declaration order or on anybody remembering `whereNumber()`.** A
numeric constraint is still applied as defence in depth, and N15 now proves the
structure rather than the ordering.

The *feature* is still called **Users & Groups** — the approved menu label — and
`RoutePrefixCollisionTest` already forbids a prefix that Apache denies; `people`
is not one.

`NoBusinessSchemaTest::test_only_delivered_modules_exist` gains `People`, on the
day it is delivered and not before — the same way `Identity` was added by P1-02.

---

## 3. Screens

Two route-backed tabs — Pattern B, as P1-01 and P1-02 use — and two record pages.
**D-38.**

### 3.1 Users — `/console/people/users`

The list, and the way in to one person.

| Column | Notes |
| --- | --- |
| Name | `display_name`. Links to the record page |
| Email | `email` |
| Status | `Active` / `Inactive`, as a status pill |
| Organisation | The organisation's name, or **`Not assigned`** |
| Last signed in | A date, or **`Not signed in yet`** — §5.4 |

**Actions:** `Add User` (§4), and a row link into each person. **`Add User` is an
action on this screen, never a tab** — D-38.

**Search and filter** — §10.

### 3.2 User — `/console/people/users/{user}`

One person. Every lifecycle action that acts on them lives here, not on a
"lifecycle" screen.

| Section | Contents |
| --- | --- |
| **Identity** | Provider (`Microsoft Entra ID`), **Object ID — masked with Reveal**, **Directory (tenant) — masked with Reveal**, and the sentence that these come from Microsoft and cannot be changed here |
| **Directory details** | Email and display name, **read-only**, labelled as coming from Microsoft — with the provisional caveat while `last_signed_in_at` is NULL (§5.4) |
| **Status** | `Active` / `Inactive`, with **Deactivate** or **Reactivate**. §7.1a governs the one case where Deactivate is refused |
| **Organisation** | The current organisation, or `Not assigned`, with the guarded assignment control (§7) |
| **Platform role** | Read-only, and it says **why**: *"Roles are assigned in a later release."* §12 |
| **Groups** | The groups they are currently in, and the ones they have left, with the date |
| **Dependencies** | What points at them — teams, management, groups — read from the same source the guards use |
| **Danger zone** | **Remove permanently**, visible **only** when the D-39 conditions hold (§8) |

### 3.3 Groups — `/console/people/groups`

| Column | |
| --- | --- |
| Name, Code, Description | |
| Members | The count of **current** members |
| Status | `Active` / `Inactive` |

**Actions:** `Add Group`, and a row link into each group.

### 3.4 Group — `/console/people/groups/{group}`

Its details, its **current** members and its **past** members — past ones
quietened rather than hidden, exactly as P1-01 renders ended team memberships.

**Actions:** edit details, activate/deactivate, add a member, end a membership,
and — only when the group has *no membership history at all* — remove
permanently.

### 3.5 What is deliberately not a screen

No **Invitations / Directory Sync** tab: D-33 chose Option A, so there is nothing
to invite and nothing to sync. No **User Lifecycle** tab: a lifecycle is not a
place.

---

## 4. Add User — the Object ID flow

**D-33 = A.** The administrator types the Entra Object ID. No Graph, no
invitation, no email binding, no bootstrap reopening.

### 4.1 The form

| Field | Required | Source |
| --- | --- | --- |
| **Entra Object ID** | **Yes** | Typed. Format-validated and uniqueness-checked server-side |
| **Work email** | **Yes** | Typed. **Provisional display information only** — §5 |
| **Display name** | No | Typed. **Defaults to the email when omitted** |
| **Organisation** | **Yes** | The current organisation. Pre-selected, because Release 1 has one |
| Provider | — | **Set automatically** to `microsoft`. Never typed, never posted |
| Tenant | — | **Taken from the configured trusted tenant.** Never typed, never posted |
| `platform_role` | — | **NULL.** Not on the form, not in the request, not writable |

**Provider and tenant are not accepted from the request at all.** They are not
hidden fields and not disabled inputs — a disabled input is still a value a
crafted request can supply. The service reads the tenant from
`config('identity.microsoft.tenant_id')` and ignores anything posted under those
names. §14 case N11.

### 4.2 ⚠ The honesty requirement

Release 1 has **no Microsoft Graph permission, by decision**. SemantIQ can check
that an Object ID is **well formed** and **not already used**, and it can check
nothing else.

**The screen says so, in those terms:**

> *SemantIQ cannot check that this ID exists in Microsoft Entra. Copy it from the
> user's profile in the Microsoft Entra admin centre. If it is wrong, this person
> will never be able to sign in, and you will need to remove the record and add
> them again.*

**A validation tick that means "the format is right" and reads as "we found
them" is forbidden.** The form reports format and uniqueness in those exact
words, and claims nothing about the directory.

### 4.3 What happens on save

1. Validate the Object ID's **format** — a GUID.
2. Validate the **identity triple** is unused. The uniqueness constraint is the
   real guard; this check exists so the refusal reads in business language rather
   than as a constraint violation.
3. Create the user inside a transaction with `provider = microsoft`, the
   configured `tenant_id`, `status = active`, `platform_role = NULL`,
   `organisation_id` set, `last_signed_in_at = NULL`.
4. Record `user.provisioned`.
5. Confirm through the existing `role="status"` channel — *"User added."*

**On a duplicate**, refuse in business language — *"That person is already in
SemantIQ."* — with a link to their record. Never a constraint message.

### 4.4 The mistyped Object ID

A wrong ID produces a user who can never sign in, because no directory identity
will ever match it. **The correction path is the D-39 guarded purge** — never
signed in, no history — and **the identity key is never made editable**. An
editable identity key is how one person quietly becomes another, and no screen,
route, request or service method in this unit can write `provider`,
`external_subject` or `tenant_id` after creation. §14 case N10.

---

## 5. Provisional data, and the transition on first sign-in

### 5.1 The rule

Before a first successful sign-in, `email` and `display_name` are **what an
administrator typed**. SemantIQ has verified none of it.

They are **NEVER** used for **authentication matching**, **authorization**,
**duplicate identity resolution**, or **access**. The identity key is, and stays,
`(provider, external_subject, tenant_id)`.

### 5.2 The transition — no new code

**Nothing new happens on first sign-in, and that is the design.**
`IdentityResolver` already refreshes `email`, `display_name` and
`last_signed_in_at` from the verified Entra token on **every** sign-in:

```php
$user->forceFill([
    'email' => $identity->email,
    'display_name' => $identity->displayName,
    'last_signed_in_at' => now(),
])->save();
```

A provisional value simply becomes a verified projection the first time the real
person authenticates. **P1-03 adds no callback behaviour and changes no line of
`IdentityResolver`.** If DESIGN had needed to touch it, that would have been the
signal that D-33 = A was not really what was chosen.

### 5.3 The consequence worth stating

The administrator's typed email may differ from the person's actual Entra email,
and on first sign-in **it will be silently replaced**. That is correct — the
directory is authoritative — but it will surprise somebody, so the record page
labels the value while it is provisional (§5.4) rather than letting the change
look like data loss.

### 5.4 `Not signed in yet`

Wherever `last_signed_in_at` is NULL, both the list and the record show
**`Not signed in yet`** — those words, not an empty cell and not a dash.

On the record page, while it is NULL, the directory details carry one muted line:

> *Provisional. These details were entered by an administrator and have not been
> confirmed by Microsoft. They will be replaced when this person first signs in.*

Once they have signed in, that line is gone and the details are simply what
Microsoft says.

---

## 6. Schema

**Two new tables. No new `users` columns — D-34.**

### 6.1 `groups`

| Column | |
| --- | --- |
| `id` | |
| `organisation_id` | FK → `organisations`, **RESTRICT**. Set at creation, never changed |
| `name` | Required |
| `code` | Nullable, short |
| `description` | Nullable |
| `status` | `active` / `inactive` |
| timestamps | |

Unique `(organisation_id, name)`. Unique `(organisation_id, code)` where present.

**No column that could be read as a grant.** No `role`, no `permission`, no
`scope`, no `domain`, no `is_admin`. §12.

### 6.2 `group_memberships` — and why it is NOT keyed like P1-01's

| Column | Type | |
| --- | --- | --- |
| `id` | | |
| `group_id` | FK → `groups` | |
| `user_id` | FK → `users` | |
| `joined_at` | **`datetime`** | Required. **Timestamp precision, not a date** |
| `left_at` | **`datetime`**, nullable | **NULL means current** |
| timestamps | | |

Index on `user_id`; index on `group_id`.

#### ⚠ Correction 4 — the same-day rejoin collision is not inherited

P1-01 keys team membership as `UNIQUE(team_id, user_id, joined_at)` over
date-valued timing. **That cannot represent join → leave → rejoin on the same
day**: the second period has the same three key values as the first, and the
database refuses it with an integrity error that has nothing to do with what the
administrator did wrong — because they did nothing wrong.

**P1-03 does not copy that.** Two decisions:

1. **`joined_at` and `left_at` are `datetime`**, so two genuine membership
   periods on one calendar day are distinguishable by their actual boundaries.
2. **There is no `UNIQUE(group_id, user_id, joined_at)`.** The invariant worth
   enforcing is *"at most one **current** membership"*, and that is what is
   enforced — not "no two rows share a start".

#### How "at most one current membership" is enforced

A partial unique index on `(group_id, user_id)` **where `left_at IS NULL`** is
the ideal expression. **MySQL 8.4 does not support partial indexes**, and the
deployment target is MySQL, so it is enforced instead by:

- a **locking read inside the write transaction** — `SELECT … FOR UPDATE` on the
  user's current membership of that group — which is the same mechanism
  `PurgeDependencies` uses for the second D-24 re-check, and which closes the
  concurrent-join window that a plain check-then-insert leaves open;
- a service-level refusal, in business language, when one is already current.

**Stated honestly:** this is an application-enforced invariant, not a database
one, because the database this deploys to cannot express it. The locking read is
what makes it hold under concurrency, and N32 breaks it deliberately to prove
that.

#### What the lifecycle then looks like

| Step | Row state |
| --- | --- |
| Join | one row, `left_at` NULL |
| Leave | that row gains `left_at`; **it is never deleted** |
| Rejoin, same day | a **new** row, `left_at` NULL, `joined_at` after the previous `left_at` |
| Leave again | the new row gains `left_at` |

`left_at` may not precede `joined_at`, and a rejoin's `joined_at` may not precede
the previous period's `left_at` — otherwise two periods would overlap in history
and the record would say somebody was in a group twice at once.

### 6.3 House rules, from P1-01 experience

- Index declared **before** the foreign key, so MySQL reuses it rather than
  creating a second.
- Short explicit identifier names — `MigrationIdentifierLengthTest` enforces
  MySQL's 64-character cap. Proposed: `groups_org_name_uq`, `groups_org_code_uq`,
  `group_memberships_uq`, `group_members_user_idx`.
- `down()` drops constraints before columns, and CI already exercises
  rollback-then-migrate.
- **No seed, no backfill, no production user created by a migration.**

### 6.4 Nothing else

No `user_provisionings`. No `roles`, `permissions`, `entitlements`, `scopes` or
`domains` — `NoBusinessSchemaTest` forbids them and **that list is not
shortened**. **If EXECUTE finds another schema requirement it stops and explains
before adding it.**

---

## 7. User lifecycle — how each operation behaves

| Operation | Route | Behaviour |
| --- | --- | --- |
| **Create** | `POST /console/people/users` | §4 |
| **Read — list** | `GET /console/people/users` | §10 |
| **Read — one** | `GET /console/people/users/{user}` | §3.2 |
| **Assign / change organisation** | `PUT /console/people/users/{user}` | §7.2 |
| **Deactivate** | `PATCH /console/people/users/{user}/deactivate` | §7.1, and **§7.1a** |
| **Reactivate** | `PATCH /console/people/users/{user}/reactivate` | Restores authentication eligibility. **Rebuilds nothing**, because nothing was removed |
| **Guarded purge** | `DELETE /console/people/users/{user}` | §8 |
| **Reveal an identifier** | `POST /console/people/users/{user}/reveal` | §9 |
| **Edit directory fields** | **No route** | They are Entra's. §5 |
| **Edit the identity key** | **No route** | §4.4 |
| **Change platform role** | **No route** | §12 |
| **Force sign-out now** | **No route — D-36** | P1-00 revalidates every protected request uncached, so an inactive user is refused at their next request |

### 7.1 Deactivate — always permitted, never silent

**Deactivation is never blocked** — not by teams, not by groups, not by
management, not by history. D-36. A person leaving is exactly when they have the
most relationships, and a guard that refuses then is a guard that makes the safe
action impossible.

**Before confirming**, the screen shows a dependency summary in the Product
Owner's own shape:

> *This user currently manages 3 people, belongs to 2 teams and 1 group.
> Deactivation stops their SemantIQ access but does not remove these
> relationships.*

Counts are **current** only — `effective_to IS NULL`, `left_at IS NULL` — and each
clause is omitted when its count is zero, so nobody reads *"manages 0 people"*.

**Nothing is rewritten.** Team memberships, group memberships and management
relationships are untouched. They are changed only through the feature that owns
them, deliberately.

**The timing is stated on screen:** access ends at the person's **next request**,
not instantly, because that is what P1-00 actually does.

### 7.1a ⚠ The one exception — the last active System Administrator

**Correction 2.** Deactivation stays always permitted for ordinary users. It is
refused in exactly one case:

> **If the target is an active System Administrator, deactivation is permitted
> only when another active System Administrator remains after the transaction.**

**In production today, with one System Administrator, that means the Product
Owner cannot deactivate themselves** — which is the point. The alternative is a
deployment with zero active administrators, no route back in through the
application, and bootstrap permanently closed because it closes on the existence
of an administrator record rather than an active one.

**The refusal, in business language:**

> *This is the only active System Administrator. Add or retain another active
> System Administrator before deactivating this account.*

#### How it is enforced

The count is re-read **inside the write transaction, with a locking read**,
immediately before the status changes:

```
SELECT count(*) FROM users
WHERE platform_role = 'system_administrator' AND status = 'active'
AND id <> :target
FOR UPDATE
```

A plain check-then-write would let two administrators deactivate each other
concurrently, each seeing the other as the survivor, and leave zero. **The
locking read is what makes the guard hold**, and it is the same mechanism D-24's
second re-check already uses. N41 breaks it deliberately.

#### What this guard is not

It does **not** reopen bootstrap, assign anybody a role, touch `platform_role`,
or build any part of P1-05. It reads two columns and refuses one write.
**P1-00's recovery path remains an emergency mechanism, not the normal way back
from a UI action** — which is exactly why the UI must not create the emergency.

### 7.2 Organisation — assign is easy, change is guarded

| Case | Behaviour |
| --- | --- |
| **`NULL` → an organisation** | Permitted. Nothing existing can be invalidated |
| **Change to a different organisation** | **Refused while any current relationship would become cross-organisation** — a current team membership, a current group membership, or a current management relationship in either direction |
| The refusal | Names **what must be resolved first**, by kind and count: *"This user is currently in 2 teams and 1 group, and manages 3 people, all in their present organisation. End those first."* Never a bare "not allowed" |
| **Clear to `NULL`** | Same guard. A user with no organisation cannot hold any of those rows |

**Why refuse rather than cascade.** Every P1-01 rule requires membership and
management to stay inside one organisation. Moving a user silently would either
break that invariant or delete somebody's history to preserve it, and **deleting
history to make an edit succeed is the thing this product does not do.**

---

## 8. Guarded purge — D-39

### 8.1 The conditions, all of which must hold

| # | Condition |
| --- | --- |
| 1 | `last_signed_in_at IS NULL` — **never signed in** |
| 2 | No team membership, **current or historical** |
| 3 | No group membership, **current or historical** |
| 4 | No management relationship in **either direction**, current or historical |
| 5 | No other durable schema reference |
| 6 | **Not the bootstrap System Administrator** |
| 7 | The dependency guard **re-checks inside the transaction** |

**Any user who has ever signed in is deactivated, never purged.**

### 8.2 Reusing D-24's mechanism, and one gap in it

Conditions 2 to 5 are exactly what `PurgeDependencies` already does: it reads
**every foreign key pointing at a row's table from the schema**, so a table added
by a later unit blocks automatically. P1-05's future keys will become blockers
with no change to this unit — which is what the Product Owner asked for.

`PurgeDependencies` therefore moves from `App\Modules\Organisation\Support` to
**`App\Shared\Lifecycle\PurgeDependencies`**: two modules now need it, and a
second copy of a delete guard is the worst possible place for two sources of
truth. A file move plus import updates, **no behavioural change**, with a guard
that exactly one such class exists — the same shape as P1-02's middleware
promotion, and reported the same way. **Approved — correction 3:** move, do not
copy; one class only; no behaviour change; P1-01 imports updated; P1-01 purge
behaviour and tests intact.

**It is genuinely cross-module infrastructure**, not Organisation-specific: it
walks schema references and knows nothing about business units. That is why it
moves and `RequireOrganisation`, which does know about organisations, does not.

Its `LABELS` map gains business phrasing for `management_relationships`,
`group_memberships` and the `manager_id` direction. That map already fails
`PurgeGuardTest` when a referencing table has no real label, so the addition is
required by an existing test rather than optional.

> ### ⚠ Two references the schema walk does NOT see
>
> `sessions.user_id` and `bootstrap_grants.consumed_by_user_id` are declared as
> plain columns with **no foreign-key constraint**, so `Schema::getForeignKeys`
> does not return them and the walk cannot find them. Read from the migrations,
> not assumed.
>
> - **`sessions`** is correct to ignore: a session is not durable history, and
>   deactivation already ends access.
> - **`bootstrap_grants.consumed_by_user_id` is condition 6**, and it therefore
>   **needs its own explicit check**. Relying on the schema walk here would have
>   produced a purge guard that silently permitted deleting the founding
>   administrator — a guard that looks complete and is not.
>
> This is exactly the failure mode P1-01 kept finding, caught by reading the
> migrations rather than trusting the mechanism to be total.
>
> **The historical P1-00 migration is NOT amended to add a foreign key merely so
> the walker can see it** — approved at review. Changing an accepted unit's
> schema to suit a later unit's convenience is a bigger change than an explicit
> six-line check, and it would rewrite history that P1-00 owns.

### 8.3 Groups

A group may be purged only with **zero membership history** and no other durable
reference. One member ever, even ended, and it **deactivates instead**.

**No membership history may be deleted, and there is no cascade.** A membership
that can be erased is not evidence.

### 8.4 On the screens

**Remove permanently** appears **only** when the conditions hold. When they do
not, the record shows **Deactivate** and, where a purge was plausibly what the
administrator wanted, one line saying why it is not offered — *"This person has
signed in, so their record is kept. Deactivate them instead."*

A disabled destructive button with a tooltip is not used: P1-01 settled that a
control which cannot act should not be rendered.

---

## 9. Masking and reveal — D-37, reused

**Exactly the P1-02 D-27 pattern.** The page payload carries only the masked
form; **Reveal** is a `POST` round-trip that re-authorises and returns one value;
the revealed value lives in component state for that view only.

`IdentitySafeValue::masked()` is reused as-is. It sits in
`App\Modules\Identity\Support`; P1-03 uses it directly rather than copying the
rule, because two masking rules that drift is precisely the outcome D-37 exists
to prevent.

**No second reveal mechanism is created.** The endpoint is
`POST /console/people/reveal`, taking the user and a field name of exactly
`object_id` or `tenant`. Any other value is refused with a 422 that names
nothing about which fields exist — the same refusal shape P1-02 uses, where the
two accepted names are `directory` and `application`.

**Why a round-trip rather than a CSS mask:** if the full identifier shipped in
the page props, the mask would be decoration and the value would sit in the page
source. That was proven necessary in P1-02 and is not re-litigated here.

---

## 10. Search, filter and pagination

| Screen | Search | Filter | Pagination |
| --- | --- | --- | --- |
| **Users** | Across `display_name` and `email` | **Status** (Active / Inactive), **Organisation** (Assigned / Not assigned), **Group** | **Yes**, server-side |
| **Groups** | Across `name`, `code`, `description` | Status | Yes |
| **A group's members** | Across member name and email | Current / Past | Yes |

Search is server-side and debounced, per the standard, because the user list is
paginated from the first release. A list that works at one row and falls over at
two thousand is a defect scheduled for later.

**Both lists ship a no-results state with a `Clear filters` escape.**

> **⚠ Stated rather than glossed:** at acceptance production will hold **two or
> three** users. Search, filter and pagination will be *exercised* but not
> *stressed*. §14 proves them against seeded volume in tests; the verification
> document will say that the production observation is small, rather than
> implying scale was demonstrated.

---

## 11. Same-organisation enforcement, and the other validation rules

| Rule | Where enforced |
| --- | --- |
| Membership requires user and group in the **same organisation** | Service, before the write. The D-16 rule, exactly as P1-01 applies it to teams |
| A user with `organisation_id IS NULL` may not join a group | Service. Fails closed |
| Membership requires an **active** user and an **active** group | Service |
| **No duplicate current membership** | Service **and** a partial-uniqueness check inside the write transaction |
| `left_at` may not precede `joined_at` | Service |
| Group `name` unique within its organisation | Database + business-language refusal |
| Group `code` unique within its organisation, where present | Database + business-language refusal |
| Identity triple unique | **Database** — `users_identity_uq`. The constraint is the guard; the service check exists to word the refusal |
| Object ID is a well-formed GUID | Request validation, **server-side** |
| Every SemantIQ-owned text field trimmed and length-bounded | Request validation |

**Every one of these is enforced server-side.** Browser validation is a
convenience and is never the control — §14 case N9.

---

## 12. ⚠ Proof that groups and users grant nothing

This is the boundary the unit is most likely to break, so it is designed as
evidence rather than as intent.

| Guard | How it is proven |
| --- | --- |
| `platform_role` is **never written** by P1-03 | No route, no form field, no service method, no `fillable` path in this module. §14 N4 |
| `PlatformRole` keeps **exactly one case** | The existing test is left in place and not relaxed. §14 N5 |
| A group has **no permission surface** | Asserted on the **actual physical column set** — §14 N3b, corrected below |
| **Nothing consults group membership for access** | Asserted on the source: outside `app/Modules/People` and its tests, **no file references `group_memberships`, the `GroupMembership` model or `->groups(`**. §14 N3 |
| **A newly created user has nothing** | Asserted behaviourally: create a user, then assert `platform_role` is NULL, `isSystemAdministrator()` is false, and every P1-01 and P1-02 administrator route refuses them. §14 N2 |
| No forbidden schema appears | `NoBusinessSchemaTest`, unchanged |

### 12.1 The `groups` schema guard — the physical set, correction 5

The first version said "exactly the seven columns", counting conceptually and
**forgetting that `timestamps()` produces two**. A column-count guard that is
wrong about the count either fails on day one or, worse, is loosened until it
passes and stops guarding anything.

The expected **physical** set is asserted, in full:

```
id, organisation_id, name, code, description, status, created_at, updated_at
```

**Nine for `group_memberships`:**

```
id, group_id, user_id, joined_at, left_at, created_at, updated_at
```

The invariant that matters is not the number. It is that **`groups` contains no
role, permission, scope, domain, sensitivity, entitlement, administrator or
grant field** — so the guard also fails on any column whose name contains any of
those words, which catches `owner_role` or `default_scope` even if somebody
updated the expected list without thinking.

**The screen says it too.** The record page's Platform role row reads *"Roles are
assigned in a later release."* rather than hiding the field — an administrator
who cannot find the control should be told it does not exist yet, not left
hunting.

**SYS-004 restated:** System Administrator grants no business-domain access, and
P1-03 must not make group membership the back door that `platform_role` is not.

---

## 13. Routes, authorisation and events

### 13.1 Routes

Inside the existing `console` group, so `EnsureSessionIsCurrent` runs first.

**Every dynamic segment sits one level below a static one.** `users` and
`groups` are both static, at the same depth, and disjoint.

| Method | URI | Name |
| --- | --- | --- |
| GET | `/console/people/users` | `people.users` |
| POST | `/console/people/users` | `people.users.store` |
| GET | `/console/people/users/{user}` | `people.user` |
| PUT | `/console/people/users/{user}` | `people.users.update` |
| PATCH | `/console/people/users/{user}/deactivate` | `people.users.deactivate` |
| PATCH | `/console/people/users/{user}/reactivate` | `people.users.reactivate` |
| POST | `/console/people/users/{user}/reveal` | `people.users.reveal` |
| DELETE | `/console/people/users/{user}` | `people.users.purge` |
| GET | `/console/people/groups` | `people.groups` |
| POST | `/console/people/groups` | `people.groups.store` |
| GET | `/console/people/groups/{group}` | `people.group` |
| PUT | `/console/people/groups/{group}` | `people.groups.update` |
| PATCH | `/console/people/groups/{group}/deactivate` | `people.groups.deactivate` |
| PATCH | `/console/people/groups/{group}/reactivate` | `people.groups.reactivate` |
| DELETE | `/console/people/groups/{group}` | `people.groups.purge` |
| POST | `/console/people/groups/{group}/members` | `people.groups.members.add` |
| PATCH | `/console/people/groups/{group}/members/{membership}/remove` | `people.groups.members.remove` |

**`/console/people` itself redirects to `/console/people/users`**, so the
navigation entry lands somewhere real and the bare prefix is never a dead URL.

**Correctness does not depend on declaration order.** `{user}` cannot capture
`groups`, because `groups` is not under `users`. A numeric constraint is applied
to `{user}` and `{group}` as **defence in depth**, not as the mechanism. §14 case
N15 proves the structure — that the group routes and the user routes are
disjoint, and that `/console/people/users/groups` is a 404 rather than a lookup
— rather than proving an ordering somebody could reshuffle.

**Two new DELETE routes** — a user and a group. `LifecycleCompletenessTest`
currently asserts the delete set is exactly P1-01's four; it is extended to the
exact set of **six**, so a seventh fails the build.

### 13.2 Authorisation

**System Administrator only, every route**, through the Platform
`RequireSystemAdministrator` P1-02 promoted. Menu visibility is presentation; the
route is the control; both are asserted separately.

`RequireOrganisation` is needed too — a group and a user assignment both require
an organisation to exist. **It is NOT moved — correction 3.**

The first version of this design proposed promoting it to Platform alongside
`RequireSystemAdministrator`, by analogy. The analogy does not hold: that
middleware reads one column on a user, while this one **depends on
`OrganisationService`, resolves the current organisation and redirects to the
Company Profile**. Moving it would make Platform depend backwards on
Organisation.

**People uses `App\Modules\Organisation\Http\Middleware\RequireOrganisation`
directly.** A newer module depending on an accepted one is the right direction of
dependency, and it is not duplicated.

*Company-Profile-style exception:* the Users list is reachable without an
organisation, so an administrator who has not yet created one is sent to the
Company Profile rather than shown an empty list they cannot act on.

### 13.3 Events

Through the existing D-12 boundary, **adding no context key**.

| Event | Context |
| --- | --- |
| `user.provisioned` | `user_id`, `organisation_id`, `entity_type`, `entity_id` |
| `user.activated` / `user.deactivated` | `user_id`, `entity_id`, `result` |
| `user.organisation.assigned` | `user_id`, `entity_id`, `organisation_id` |
| `user.purged` | `entity_type`, `entity_id`, `user_id` |
| `user.provision.refused` | `result`, `reason` |
| `group.created` / `group.updated` / `group.deactivated` / `group.reactivated` / `group.purged` | `entity_type`, `entity_id`, `organisation_id`, `user_id` |
| `group.member.added` / `group.member.removed` | `entity_id`, `related_id`, `user_id` |

**No email, no display name, no group name, no Object ID.** The logger has no key
for free text and none is added — the guard that makes a leak unrepresentable
rather than merely discouraged.

---

## 14. Tests, and the mutation that must make each fail

`CLAUDE.md` §2. Every guard is broken deliberately and observed to fail, and the
mutation is recorded beside the case.

### Boundary — the ones that matter most

| # | Case | Mutation |
| --- | --- | --- |
| N1 | Anonymous, and authenticated non-administrator, on **every** route | Drop the gate from one route |
| N2 | **A newly created user has nothing** — no role, not an administrator, refused by every P1-01 and P1-02 admin route | Give the provisioning path a role |
| N3 | **Nothing outside People reads group membership** | Have any authorisation path read `group_memberships` |
| N3b | **`groups` and `group_memberships` have exactly their physical column sets**, `created_at` and `updated_at` included; and **no column name contains** role, permission, scope, domain, sensitivity, entitlement, admin or grant | Add `is_admin`; add `owner_role`; add a column and update the expected list without reading it |
| N4 | **No P1-03 path writes `platform_role`** | Add it to the update request |
| N5 | `PlatformRole` still has one case | Add a second |
| N6 | **Authentication never resolves a user by email** | Make `IdentityResolver` fall back to email |

### Provisioning

| # | Case | Mutation |
| --- | --- | --- |
| N7 | Duplicate identity refused, in business language | Remove `users_identity_uq` |
| N8 | Two administrators provisioning the same person concurrently | One user, one refusal | Replace the constraint with check-then-insert |
| N9 | A malformed Object ID is refused **server-side** | Validate in the browser only |
| N10 | **The identity key cannot be edited after creation** | Make `external_subject` fillable from a screen |
| N11 | **`provider` and `tenant_id` posted in the request are ignored** | Accept them from input |
| N12 | Provisional email/display name are **never** used for authentication, authorisation or duplicate resolution | Resolve or authorise by email anywhere |
| N13 | `Not signed in yet` shows when `last_signed_in_at` is NULL, and the real value afterwards | Render an empty cell |
| N14 | The form claims the directory was checked | Add a tick that implies the person exists |

### Routing and lifecycle completeness

| # | Case | Mutation |
| --- | --- | --- |
| N15 | **The user and group route sets are structurally disjoint**: every `people.users.*` URI begins `/console/people/users` and every `people.groups.*` URI begins `/console/people/groups`; **no route has a dynamic segment at the depth of a static one**; `/console/people/users/groups` is **404**, not a lookup; and the whole set still resolves after the route file is **reversed** | Put a user route back at `/console/people/{user}` — reversing the file then makes `groups` resolve as a user, which order-dependent routing hides and this does not |
| N16 | The application's DELETE routes are **exactly six** | Add a seventh |
| N17 | **Every operation named in PLAN §5 and §6 exists** as a service method | Delete one — the P1-01 presence guard, written before implementation this time |
| N18 | Every write **confirms itself** | Return a bare redirect — the existing P1-01 guard, extended to this module |

### Lifecycle guards

| # | Case | Mutation |
| --- | --- | --- |
| N19 | **Deactivation is never blocked** by any dependency, for an ordinary user | Add a dependency guard to deactivation |
| N41 | **The sole active System Administrator cannot deactivate themselves**, and the refusal is the business sentence | Remove the guard — production is then one click from zero administrators |
| N41b | With **two** active System Administrators, either may be deactivated | Refuse whenever the target is an administrator |
| N41c | **The count is re-read inside the transaction, with a locking read** | Move the check outside the transaction — two concurrent deactivations then leave zero |
| N20 | The dependency summary counts **current** relationships only, and omits zero clauses | Count historical rows too |
| N21 | Deactivation **changes no** membership or management row | Have it end them |
| N22 | Reactivation restores eligibility and **nothing else** | Have it recreate a membership |
| N23 | Organisation change refused while cross-organisation rows would result, naming what to resolve | Allow it, and watch a P1-01 invariant break |
| N24 | Purging a user who has **ever signed in** | Allow it |
| N25 | Purging a user with **any** membership history | Check only current rows |
| N26 | **Purging the bootstrap administrator** | Rely on the schema walk, which cannot see that column |
| N27 | Purge re-checks **inside** the transaction | Check only before it |
| N28 | Purging a group with ended memberships | Check only current members |
| N29 | Membership history can be deleted | Add a delete route for it |

### Membership rules

| # | Case | Mutation |
| --- | --- | --- |
| N30 | Cross-organisation membership refused | Drop the same-organisation check |
| N31 | Membership for a user with no organisation refused | Let NULL pass |
| N32 | A second **current** membership of one group refused | Allow stacking |
| N33 | Inactive user or inactive group refused | Allow either |
| N42 | **join → leave → rejoin → leave → rejoin, all on one calendar day**, succeeds and leaves three periods in history | Key membership on `(group_id, user_id, joined_at)` over dates — the P1-01 collision, reproduced |
| N43 | A rejoin whose `joined_at` precedes the previous `left_at` is refused | Allow overlapping periods |
| N44 | **No database integrity error ever reaches the administrator** | Let the constraint surface instead of the service refusing first |

### Presentation and privacy

| # | Case | Mutation |
| --- | --- | --- |
| N34 | The full Object ID and tenant are **absent from every page payload** | Send them as props and mask in CSS |
| N35 | Reveal accepts exactly two field names | Accept a third |
| N36 | No security event carries an email, a name or an Object ID | Add one to the context |
| N37 | Directory fields are read-only | Make `email` editable |
| N38 | Search, filter and pagination work **against seeded volume** | Remove the limit; ignore the filter |
| N39 | Every People surface is readable in both themes | Use a raw semantic hex |
| N40 | Exactly one `PurgeDependencies` and one `RequireOrganisation` class exist | Leave a copy behind |

**N17 and N16 are the presence guards**, and they exist because of P1-01: *an
operation that does not exist has no test to fail.* PLAN §5 and §6 named every
operation precisely so this test could be written at all.

---

## 15. Carried gates — how P1-03 closes two

| Gate | Verification |
| --- | --- |
| **1 — P1-01 management cycle** | Once the Product Owner has provisioned a genuine second user: **Set** them a manager, **Change** it, **Clear** it, then attempt a real cycle — A manages B, then make B manage A — and observe the refusal, with the message recorded verbatim |
| **2 — P1-02 non-administrator refusal** | The same genuine second user signs in and is refused at Identity & SSO. **Every user P1-03 creates has `platform_role = NULL`**, so this needs no special setup |
| **3 — P1-02 provider-wide lock** | **MOVED TO P1-05.** P1-03 cannot assign `platform_role`, so closing it here would mean manufacturing a second privileged production account. Automated evidence stands |

**No fake or permanent production users are created for any gate.** The Product
Owner provisions one genuine person, and both gates are observed against them.

---

## 16. Product Owner test script — outline

Written in full before acceptance, with all twelve `CLAUDE.md` §3 elements.

1. **Feature** — Users & Groups: two screens, two record pages.
2. **Deployed build / merge SHA** — recorded at handover.
3. **Preconditions** — signed in as a System Administrator; the Company Profile
   exists.
4. **Test data required** — **the Entra Object ID of one genuine colleague.**
   The Product Owner needs it from the Microsoft Entra admin centre before
   starting, and the script says where to find it.
5. **⚠ PERMANENCE WARNING, before step 1** — this unit creates a **real person's
   record**. Once that person signs in, **their record can no longer be
   removed** — only deactivated (D-39). Groups and memberships are likewise
   retained as history. **Choose a real colleague who should genuinely have
   access, not a placeholder**, because a placeholder that signs in is permanent.
6. **Steps** — add the user; see `Not signed in yet`; reveal and hide the Object
   ID; create a group; add them to it; end the membership and see it kept as
   history; try to remove a group with history and be refused; deactivate and
   read the dependency summary; reactivate; confirm no role control exists.
7. **Expected result for every step.**
8. **Negative and security cases** — the two carried gates (§15), plus: no
   screen shows a role control; a duplicate Object ID is refused; a malformed one
   is refused.
9. **Visual and UX checks** — both themes, narrow width, empty states, the
   no-results state, the refusal and confirmation banners.
10. **Evidence to capture** — the user record before and after first sign-in; the
    dependency summary; the cycle refusal message.
11. **PASS / FAIL per step.**
12. **What cannot be tested yet** — carried honestly: search and filter at scale
    (production will hold two or three users; automated evidence covers volume);
    the provider-wide Re-check lock (moved to P1-05); and **whether an Object ID
    is real**, which SemantIQ cannot check at all and which the first sign-in is
    the only true test of.

---

## 17. What this design deliberately does not build

- No role, permission, entitlement, scope, sensitivity or access model, and no
  expansion of the `platform_role` seam.
- No group-derived access, and no column a future reader could mistake for one.
- No Microsoft Graph call, no directory sync, no invitation, no pending record.
- **No change to `IdentityResolver`, the sign-in flow or the callback** — §5.2.
- No editable identity key.
- No force-sign-out.
- No deletion of membership history, and no cascade.
- No audit table; events go through the existing D-12 boundary.
- No structural entity added to P1-01.

---

## 18. Stop point

**Nothing here is implemented.** No migration, model, route, controller, service,
screen, test or production user has been created.

**One structural move, approved at review:** `PurgeDependencies` →
`App\Shared\Lifecycle`. Move, not copy; one class; no behaviour change; P1-01
imports updated and its purge tests intact.

**`RequireOrganisation` does not move.** It depends on `OrganisationService` and
resolves the current organisation, so promoting it would make Platform depend
backwards on Organisation. People uses it where it lives.

**The bootstrap-administrator finding stands** (§8.2): because
`bootstrap_grants.consumed_by_user_id` carries no foreign key, D-39's condition 6
needs its own explicit check, and the historical P1-00 migration is **not**
amended to suit the walker.

**P1-03 DESIGN — APPROVED BY THE PRODUCT OWNER, 2 September 2026**, with the five
corrections in §0 applied. EXECUTE follows.
