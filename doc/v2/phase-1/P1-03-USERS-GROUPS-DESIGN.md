# P1-03 — Users & Groups — DESIGN

**Status:** **DESIGN — awaiting Product Owner review.** Documentation only.
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

**Why not the prefix `/console/users`.** The Groups list would sit at
`/console/users/groups` and a user record at `/console/users/{id}`. Those are the
same URL shape, and the segment `groups` would be captured as an id unless every
route carried a numeric constraint. Relying on a `whereNumber` to stop a list
screen being read as a person is the kind of arrangement that works until
somebody adds a route and forgets. **`/console/people` removes the collision
rather than guarding it.**

The *feature* is still called **Users & Groups** — the approved menu label — and
`RoutePrefixCollisionTest` already forbids a prefix that Apache denies; `people`
is not one.

`NoBusinessSchemaTest::test_only_delivered_modules_exist` gains `People`, on the
day it is delivered and not before — the same way `Identity` was added by P1-02.

---

## 3. Screens

Two route-backed tabs — Pattern B, as P1-01 and P1-02 use — and two record pages.
**D-38.**

### 3.1 Users — `/console/people`

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

### 3.2 User — `/console/people/{user}`

One person. Every lifecycle action that acts on them lives here, not on a
"lifecycle" screen.

| Section | Contents |
| --- | --- |
| **Identity** | Provider (`Microsoft Entra ID`), **Object ID — masked with Reveal**, **Directory (tenant) — masked with Reveal**, and the sentence that these come from Microsoft and cannot be changed here |
| **Directory details** | Email and display name, **read-only**, labelled as coming from Microsoft — with the provisional caveat while `last_signed_in_at` is NULL (§5.4) |
| **Status** | `Active` / `Inactive`, with **Deactivate** or **Reactivate** |
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

### 6.2 `group_memberships`

| Column | |
| --- | --- |
| `id` | |
| `group_id` | FK → `groups` |
| `user_id` | FK → `users` |
| `joined_at` | Required |
| `left_at` | Nullable. **NULL means current** |
| timestamps | |

Index on `user_id`. Deliberately the **same shape as P1-01's
`team_memberships`**, so membership means one thing in the product rather than
two — and so the schema-driven purge guard treats them alike without being told.

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
| **Create** | `POST /console/people` | §4 |
| **Read — list** | `GET /console/people` | §10 |
| **Read — one** | `GET /console/people/{user}` | §3.2 |
| **Assign / change organisation** | `PUT /console/people/{user}` | §7.2 |
| **Deactivate** | `PATCH /console/people/{user}/deactivate` | §7.1 |
| **Reactivate** | `PATCH /console/people/{user}/reactivate` | Restores authentication eligibility. **Rebuilds nothing**, because nothing was removed |
| **Guarded purge** | `DELETE /console/people/{user}` | §8 |
| **Reveal an identifier** | `POST /console/people/reveal` | §9 |
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
promotion, and reported the same way.

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
| A group has **no permission surface** | Asserted on the schema: `groups` columns are exactly the seven in §6.1, and a test fails if an eighth appears. §14 N3b |
| **Nothing consults group membership for access** | Asserted on the source: outside `app/Modules/People` and its tests, **no file references `group_memberships`, the `GroupMembership` model or `->groups(`**. §14 N3 |
| **A newly created user has nothing** | Asserted behaviourally: create a user, then assert `platform_role` is NULL, `isSystemAdministrator()` is false, and every P1-01 and P1-02 administrator route refuses them. §14 N2 |
| No forbidden schema appears | `NoBusinessSchemaTest`, unchanged |

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

| Method | URI | Name |
| --- | --- | --- |
| GET | `/console/people` | `people.users` |
| POST | `/console/people` | `people.users.store` |
| POST | `/console/people/reveal` | `people.users.reveal` |
| GET | `/console/people/groups` | `people.groups` |
| POST | `/console/people/groups` | `people.groups.store` |
| GET | `/console/people/groups/{group}` | `people.group` |
| PUT | `/console/people/groups/{group}` | `people.groups.update` |
| PATCH | `/console/people/groups/{group}/deactivate` | `people.groups.deactivate` |
| PATCH | `/console/people/groups/{group}/reactivate` | `people.groups.reactivate` |
| DELETE | `/console/people/groups/{group}` | `people.groups.purge` |
| POST | `/console/people/groups/{group}/members` | `people.groups.members.add` |
| PATCH | `/console/people/groups/{group}/members/{membership}/remove` | `people.groups.members.remove` |
| GET | `/console/people/{user}` | `people.user` |
| PUT | `/console/people/{user}` | `people.users.update` |
| PATCH | `/console/people/{user}/deactivate` | `people.users.deactivate` |
| PATCH | `/console/people/{user}/reactivate` | `people.users.reactivate` |
| DELETE | `/console/people/{user}` | `people.users.purge` |

**Ordering matters and is not left to chance:** every `groups` route is
registered **before** the `{user}` routes, and `{user}` additionally carries a
numeric constraint. Either alone would work; both together mean a future
reordering cannot turn the Groups list into a user lookup. §14 case N15.

**Two new DELETE routes** — a user and a group. `LifecycleCompletenessTest`
currently asserts the delete set is exactly P1-01's four; it is extended to the
exact set of **six**, so a seventh fails the build.

### 13.2 Authorisation

**System Administrator only, every route**, through the Platform
`RequireSystemAdministrator` P1-02 promoted. Menu visibility is presentation; the
route is the control; both are asserted separately.

`RequireOrganisation` is needed too — a group and a user assignment both require
an organisation to exist. It currently lives in the Organisation module, so it is
**promoted to `App\Modules\Platform\Http\Middleware\RequireOrganisation`** on the
same reasoning and with the same one-class guard as its sibling. A file move,
imports updated, **no behavioural change**, and P1-01's tests must pass with no
assertion altered.

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
| N3b | `groups` has exactly its seven columns | Add `is_admin` |
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
| N15 | `/console/people/groups` resolves to the Groups list, not a user lookup | Register `{user}` first and drop the numeric constraint |
| N16 | The application's DELETE routes are **exactly six** | Add a seventh |
| N17 | **Every operation named in PLAN §5 and §6 exists** as a service method | Delete one — the P1-01 presence guard, written before implementation this time |
| N18 | Every write **confirms itself** | Return a bare redirect — the existing P1-01 guard, extended to this module |

### Lifecycle guards

| # | Case | Mutation |
| --- | --- | --- |
| N19 | **Deactivation is never blocked** by any dependency | Add a dependency guard to deactivation |
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

Two structural moves are proposed and flagged rather than slipped in, both
following the precedent P1-02 set:

1. **`PurgeDependencies`** → `App\Shared\Lifecycle` — two modules now need the
   delete guard, and a second copy is the worst possible duplication.
2. **`RequireOrganisation`** → `App\Modules\Platform\Http\Middleware` — the same,
   for the same reason.

Both are file moves with **no behavioural change**, each with a guard that
exactly one such class exists, and each requires P1-01's tests to pass with **no
assertion altered**. If the Product Owner would rather neither accepted module
were touched, say so and P1-03 will reference them where they live — which is
worse, and is offered as a preference rather than a recommendation.

**One finding from this design is worth the Product Owner's attention now**
(§8.2): `bootstrap_grants.consumed_by_user_id` carries **no foreign-key
constraint**, so the schema-driven dependency walk cannot see it. D-39's
condition 6 therefore needs its own explicit check. Without that reading, the
purge guard would have looked complete and quietly permitted removing the
founding administrator.

**P1-03 DESIGN READY FOR PRODUCT OWNER REVIEW.**
