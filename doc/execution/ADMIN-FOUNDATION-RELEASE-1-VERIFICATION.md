# Administrator Foundation Release 1 - Verification

**Plan:** `doc/execution/ADMIN-FOUNDATION-RELEASE-1-PLAN.md`
**Specification:** `doc/execution/Semantiq_ADMINISTRATOR_FOUNDATION_RELEASE_1_v1.0.md`

One section per gate. A gate is added when its batch is built, and nothing is
written here that was not actually run.

---

# Gate 1 - Platform (R1.1)

**Date:** 24 August 2026
**Batch:** R1.1
**Features:** ADM-001 Platform Overview, ADM-021 System Configuration, ADM-024 Diagnostics
**Gate 1 exit criteria (section 32):** application health, configuration framework, error handling and audit infrastructure available.

## 1. Automated tests

Run on this branch immediately before the commit.

```text
php artisan test
  tests: 161   passed: 161   assertions: 1747   duration: 3.6s

./vendor/bin/pint --test
  result: passed
```

Test count before this batch was 86 with 330 assertions. The 75 new tests are:

| File | What it holds the line on |
|---|---|
| `tests/Unit/Audit/RedactionTest.php` | A credential is recognised in any spelling; a sensitive value never reaches a summary in any key; a credential in free text is swept; an ordinary message survives; a long value is fingerprinted, not stored; nesting is bounded |
| `tests/Unit/Platform/LifecycleStatusTest.php` | Every status maps onto one of the six badge roles; the vocabulary matches section 31 exactly, both directions; a vocabulary rejects a status from another record family; the platform is as healthy as its worst dependency; a boolean setting refuses loose truthiness |
| `tests/Feature/Identity/OrganisationScopeTest.php` | The bootstrap organisation exists after migration; a second organisation stops automatic resolution; a scoped read returns nothing with no context; a scoped write is refused; one organisation cannot read another's rows; a disabled organisation does not resolve |
| `tests/Feature/Audit/AuditLoggerTest.php` | An event carries what an investigation needs; a secret handed to the writer never reaches the table; an event cannot be changed or deleted through the model; a refusal is recorded; a system action with no signed-in person is still recorded; one request shares one correlation id; a malformed inbound correlation id is replaced; **a refused privileged route leaves a trace** |
| `tests/Feature/Platform/PlatformOverviewTest.php` | Real health states, not placeholders; an outstanding dependency appears in the to-do list; no configured credential reaches the page; recent changes render; the empty state explains itself; the boundary holds |
| `tests/Feature/Platform/SystemConfigurationTest.php` | An unset setting reads its catalogue default; an unknown key throws; the screen renders the catalogue; an unknown category is a 404; a change is stored and audited; an unchanged value writes no event; catalogue rules are enforced; a field from another category is dropped; an unchecked box stores false; **a secret-bearing key can never be written**; an actor below the tier is refused and the refusal audited |
| `tests/Feature/Platform/FeatureFlagTest.php` | A flag with no row reads its default; an undeclared flag is off but cannot be written; a toggle records the change and the reason; a one-direction switch refuses the unsafe direction and is allowed once its precondition holds; an unknown key in the URL is refused before the body is read |
| `tests/Feature/Platform/DiagnosticsTest.php` | It reports what triage needs; **it never shows a credential or a host**; the extended fact set is off until switched on; refusals are listed with their references; the empty state explains itself |
| `tests/Feature/Shell/NavigationIntegrityTest.php` | Every node names a declared policy and a registered icon; every node route resolves and generates a URL; **every `admin.*` GET route is reachable from the rail**; two nodes sharing a route are told apart by their parameters |

## 2. Migration validation

Run against the local SQLite database, `up` then `down` then `up`:

```text
php artisan migrate --force
  2026_08_24_090000_create_organisations_table   DONE
  2026_08_24_090100_create_audit_events_table    DONE
  2026_08_24_090200_create_system_settings_table DONE
  2026_08_24_090300_create_feature_flags_table   DONE

php artisan migrate:rollback --step=4 --force
  all four rolled back cleanly

php artisan migrate --force
  all four re-applied cleanly
```

Every migration has a working `down()`. Nothing existing was altered, dropped or
renamed; all four are additive.

**Not yet run against production MySQL.** A production migration remains a
separately approved action.

## 3. Manual workflow checks

Driven in headless Chromium at 1440x900 and 390x844, in both themes, signed in
as a System Administrator through the local credential path. The verification
account was created for the run and deleted afterwards; its password was
generated at run time, never written to a file and never printed.

| Check | Result |
|---|---|
| `/admin` Platform Overview renders | Pass. Status, four outstanding items, seven health checks, ten journey steps, recent changes |
| `/admin/system/settings/general` renders and saves | Pass |
| `/admin/system/settings/environment` renders and saves | Pass |
| `/admin/system/feature-flags` renders and toggles | Pass |
| `/admin/system/diagnostics` renders | Pass. Runtime, connectivity, correlation id |
| Breadcrumb reaches the right one of two nodes sharing a route | Pass |
| No horizontal page scroll at 1440px | Pass, after a fix - see section 7 |
| No horizontal page scroll at 390px | Pass |
| Dark theme | Pass |
| No JavaScript console error or page error on any screen | Pass |

## 4. Security checks

| Control | Evidence |
|---|---|
| No credential in any table | `VAL-SET-NOSECRET-001`; a setting whose key reads as secret-bearing is refused even when the catalogue declares it ordinary |
| No credential on any page | `DiagnosticsTest::it_never_shows_a_credential_or_a_host` and `PlatformOverviewTest::no_configured_credential_reaches_the_page` assert the tenant, application, client secret and database path are all absent while configured |
| No credential in the audit trail | `AuditLoggerTest::a_secret_handed_to_the_writer_never_reaches_the_table` passes a secret in and asserts it appears nowhere in the encoded row |
| Redaction cannot be skipped | No parameter exists for it. One `Redaction` class, applied unconditionally in the single writer |
| Cross-organisation access denied by default | `OrganisationScopeTest`. No context means no rows and no writes, not everything |
| Every gate enforced at the route, not only the rail | `policy:system-admin` on all six admin routes; `NavigationIntegrityTest` asserts rail and routes agree |
| Denials are audited | The middleware now records `privileged.action.denied` on every 403, and both writers audit a refused change |
| Log injection through a correlation id | An inbound `X-Correlation-Id` is replaced unless it is a well-formed UUID |
| Mass assignment | `AuditEvent` has an empty `$fillable`; `key`, `organisation_id` and the organisation `code` are non-fillable and set explicitly |
| Output escaping | Every Blade expression uses `{{ }}`. There is no `{!! !!}` anywhere in the views |
| CSRF | Every form carries `@csrf`; state-changing routes are PUT or POST |

## 5. Data protection and sovereignty

- Classification: Internal for configuration, flags, organisation and audit metadata. **No customer business data enters this release.**
- `audit_events` records an IP address, which is personal data. It is recorded because the event schema requires it for security-relevant actions and inherits the same retention policy as the row.
- Retention is policy driven. The project baseline of seven years is recorded in the data register, not hard-coded anywhere.
- Cross-geo processing and storage remain OFF. Nothing in this batch activates a resource in any geography.
- **Open item, unchanged:** the control-plane hosting geography is still unconfirmed, so flows SOV-001, SOV-005 and SOV-006 are recorded as not verified.

## 6. Context registers

Updated in this change, as `CLAUDE.md` requires:

- `CODE_CONTEXT_REGISTER.md` - CTX-CODE-014 to 028 added; CTX-CODE-011 removed with the controller it described
- `DATA_CONTEXT_REGISTER.md` - DATA-008 to 012
- `VALIDATION_RULES_REGISTER.md` - ten new `VAL-*` rules, each with the test that enforces it
- `CONFIGURATION_REGISTER.md` - CFG-PLAT-001 to 003, CFG-SET-001 to 002, CFG-FLAG-001 to 002
- `DATA_SOVEREIGNTY_REGISTER.md` - SOV-005 and SOV-006
- `SECURITY_PRIVACY_DECISIONS.md` - SEC-DEC-011 to 021

## 7. What browser verification caught that the tests did not

Recorded because it keeps being the same lesson.

1. **The feature flags table pushed the whole page sideways and its last two columns fell off the screen.** A flex item defaults to `min-width: auto`, so the card refused to shrink below its widest child and the scroll container never got narrower than the table. Fixed with `min-width: 0` on `.stack > *` and `.table-scroll`. Every test passed throughout.
2. **Row headers rendered with column-header letter-spacing**, which made every row label look mechanical. Fixed with a `.cell-heading` class, which also removed four repeated blocks of inline style.
3. **A border hung under the last row of every table**, because the last-row rule targeted `td` and a row header is a `th`.

The browser check now also asserts no horizontal page overflow at both widths, so the first of those cannot recur silently.

## 8. Known issues and deferred items

| # | Item | Why it is deferred |
|---|---|---|
| 1 | **`audit_events` mass deletes bypass the model guard.** `->query()->delete()` does not fire Eloquent events | Nothing in application code can close it. The control is a database grant - INSERT and SELECT only for the application user - which needs an approved production database change. Recorded as SEC-DEC-021 and it is an **open gap** |
| 2 | ADM-001's "link each warning to the screen that fixes it" | The screens to link to - authentication policy, data protection, sovereignty, integrations - are built in gates 3 to 5. A link field nothing sets was removed rather than stubbed |
| 3 | The Release 1 matrix grants Administrator READ on platform, audit, data protection, sovereignty and system | Widening access is never the safe default. The granular read grant belongs to gate 2's permission registry where it can be a read permission rather than a whole cluster. SEC-DEC-020 |
| 4 | Eight menu-structure gaps, M1 to M8 | Section 21.3 of the plan. Each needs a decision before its own gate, and none should be closed by an implementer picking a side |
| 5 | Data Health and Intelligence Health on Platform Overview | There is no data estate or semantic model to report on until the Fabric release builds one. They are journey steps rather than invented figures |
| 6 | The scheduler heartbeat needs a server cron entry | Documented in `routes/console.php`. Until it is installed the Scheduler check reports Unknown, which is honest: nothing is claimed to be running |

## 9. Result against the gate

| Gate 1 criterion | Result |
|---|---|
| Application health available | **Pass.** Seven checks, four states, a roll-up, and a screen for each of ADM-001 and ADM-024 |
| Configuration framework available | **Pass.** A code catalogue, typed values, per-key validation and editing tier, database overrides only, and feature flags with preconditions |
| Error handling available | **Pass.** Every probe converts a failure into a state rather than throwing; every external message is scrubbed; a refusal returns to the form with a message and is audited |
| Audit infrastructure available | **Pass at the application layer, with one stated limit.** A single write path, unconditional redaction, correlation ids, denials recorded, and append-only enforced at the model. The database-grant control in item 1 above is outstanding |

**Gate 1 is met, subject to the open items in section 8.** Item 1 is a
deployment action for go-live and does not block gate 2.

R1.2 has not been started. It needs review of this batch first.

---

# Gate 2 - Identity and Access (R1.2)

**Date:** 25 August 2026
**Batch:** R1.2
**Features:** ADM-002 Organisation Profile, ADM-003 Business Units, ADM-004 Teams, ADM-005 User Registry, ADM-006 Roles, ADM-007 Permissions, ADM-008 Access Reviews
**Gate 2 exit criteria (section 32):** a System Administrator exists, the User Registry works, roles work, permissions are enforced server-side, the domain entitlement model exists, and the last System Administrator cannot be removed.

## 1. Automated tests

```text
php artisan test
  tests: 243   passed: 243   assertions: 2201   duration: 5.3s

./vendor/bin/pint --test
  result: passed
```

Before this batch: 161 tests, 1747 assertions. **82 new tests.**

| File | What it holds the line on |
|---|---|
| `tests/Feature/Identity/LastAdministratorTest.php` | All three removal paths refused separately - demote, disable, lock; a disabled or expired administrator does not count towards the total; promotion is not blocked; the route refuses and shows the reason |
| `tests/Feature/Identity/AuthorizationTest.php` | Unknown key denies; tier defaults work with no role; **a role carrying a permission above its holder's tier grants nothing**; a role CAN add an opt-in permission; a disabled role grants nothing but keeps its assignments; a suspended account holds nothing; effective permissions are determinable; nobody delegates what they do not hold; nobody grants a tier above their own; **a System Administrator with no entitlement reads no business data**; a domain grant confers no platform authority; no permission key names a business domain |
| `tests/Feature/Identity/UserRegistryTest.php` | Accounts start invited; email uniqueness; every non-active status refuses sign-in; the access window opens and closes by itself; a closed window expires an active account; the sign-in refusal is byte-identical to a wrong password; every access change is audited; a tier change and a domain grant are different events; no password reaches an audit summary |
| `tests/Feature/Identity/RouteAuthorizationTest.php` | All eight screens refuse four business tiers by URL; an Administrator reaches all eight; **the rail and the route agree for all six tiers on all eight screens**; a suspended System Administrator is refused; every write route is gated; no escalation through the tier route or by assigning a higher role; another organisation's account 404s; refusals audited |
| `tests/Feature/Identity/StructureAndReviewTest.php` | A unit cannot be its own parent nor sit under its own descendant; codes unique and normalised; a disabled unit takes no team; team reassignment is its own event; the snapshot is taken once; a review cannot complete while anything is undecided; applying revokes through `UserRegistry`; applying twice is safe; an item keeps its snapshot label after a rename; each decision audited as it is made; a primary tier is never reviewable |
| `tests/Feature/Identity/CrossOrganisationTest.php` | Business units, teams and access reviews invisible across organisations; the user registry scoped despite `users` carrying no global scope; the administrator count does not borrow another organisation's; everything fails closed with no context; a route cannot reach another organisation's account by id |
| `tests/Feature/Shell/NavigationIntegrityTest.php` (extended) | Every policy's permission is declared; **a rail node and the route it points at are gated by the same permission** |

## 2. Migrations

Nine, all additive, all exercised `up` then `down` then `up`:

```text
2026_08_25_090000_add_organisation_profile_to_organisations_table   ADM-002
2026_08_25_090100_create_business_units_table                       ADM-003
2026_08_25_090200_create_teams_table                                ADM-004
2026_08_25_090300_create_roles_table                                ADM-006
2026_08_25_090400_create_role_permissions_table                     ADM-007
2026_08_25_090500_create_user_roles_table                           ADM-005
2026_08_25_090600_add_identity_context_to_users_table               ADM-005
2026_08_25_090700_create_access_reviews_table                       ADM-008
2026_08_25_090800_create_access_review_items_table                  ADM-008
2026_08_25_090900_seed_built_in_roles                               ADM-006
```

Nothing existing is dropped or renamed. Existing accounts are backfilled to the bootstrap organisation and stay active, so no one is locked out by the migration. The six built-in roles are seeded by migration rather than a seeder, because production runs `migrate --force` and never runs seeders.

**Not yet run against production MySQL.** A production migration remains a separately approved action.

## 3. Permissions introduced

Twenty-one keys, all in `PermissionRegistry`. **There is no `permissions` table** - see plan D6.

| Key | Ceiling | Auto-granted from | Risk |
|---|---|---|---|
| `admin.platform.view` | System Administrator | same | Normal |
| `admin.system.view` / `.update` | System Administrator | same | Normal / Elevated |
| `admin.organisation.view` / `.update` | Administrator | same | Normal / Elevated |
| `admin.business_units.view` / `.manage` | Administrator | same | Normal |
| `admin.teams.view` / `.manage` | Administrator | same | Normal |
| `admin.users.view` / `.create` / `.update` / `.disable` | Administrator | same | Normal / Elevated / Elevated / High |
| `admin.roles.view` | Administrator | same | Normal |
| **`admin.roles.manage`** | Administrator | **System Administrator** | **High** |
| `admin.roles.assign` | Administrator | same | High |
| `admin.permissions.view` | Administrator | same | Normal |
| `admin.entitlements.view` / `.grant` | Administrator | same | Normal / High |
| `admin.access_reviews.view` / `.manage` | Administrator | same | Normal / Elevated |
| `admin.audit.view` | System Administrator | same | Normal |

`admin.roles.manage` is the only opt-in permission, and it is what makes the role table load-bearing - see plan D7.

## 4. Audit events introduced

`user.created`, `user.updated`, `user.disabled`, `user.unlocked`, `user.role.assigned`, `user.role.removed`, `user.entitlement.granted`, `user.entitlement.revoked`, `organisation.updated`, `business_unit.created`, `business_unit.updated`, `business_unit.disabled`, `team.created`, `team.updated`, `team.reassigned`, `role.created`, `role.updated`, `role.permissions_changed`, `role.deleted`, `access_review.created`, `access_review.opened`, `access_review.decided`, `access_review.completed`, `access_review.applied`, `access_review.cancelled`, `auth.login.succeeded`, `auth.login.failed`, `auth.logout`.

Plus `privileged.action.denied` on every refused route, refused delegation, refused elevation and refused role change.

## 5. Security checks

| Control | Evidence |
|---|---|
| Permissions enforced server-side | Three layers - rail, route middleware, service - all asking one `Authorization::allows()`. A test asserts the rail and the route agree for every tier on every screen |
| No escalation by direct route access | Posting the tier route asking for a tier above the actor's is refused and audited; assigning a higher role is refused; a role carrying a permission above its holder's tier is inert |
| The last System Administrator | Three removal paths tested separately; inactive administrators excluded from the count |
| Platform role never implies business data | `a_system_administrator_with_no_entitlement_reads_no_business_data`, plus a structural test that no permission key names a business domain |
| Disabled users cannot authenticate | Both sign-in paths, plus the authorization layer, so a live session does not outlive the change. **The two paths word the refusal differently on purpose** - see section 10 |
| Cross-organisation | Every new table behind the boundary; the user registry explicitly scoped; a guessed id 404s |
| Every write route gated | A structural test walks the route table and fails on any ungated POST, PUT, PATCH or DELETE |
| Mass assignment | `code`, `tier`, `is_system`, `organisation_id` and `permission_key` are all non-fillable and set explicitly |
| Output escaping | Every Blade expression uses `{{ }}`. No raw output anywhere |

## 6. Browser verification

Headless Chromium, 1440x900 and 390x844, both themes. Eleven screens plus the role permission editor. No console error, no page error, **no horizontal page overflow at either width**.

Two things the browser caught that the tests did not:

1. **Badges wrapped mid-phrase** - "No access" rendering as "No / access" in a narrow column, which reads as two states rather than one. Fixed with `white-space: nowrap` on the pill.
2. **The permission editor was a page of forty disabled checkboxes** for a role whose ceiling is below every declared permission, with no explanation. It now shows an empty state saying why.

## 7. Menu structure

DEC-001 approved and applied. `doc/MENU_STRUCTURE.md` updated, not worked around.

- **M1 resolved.** New top-level `Security` group with five leaves, authored in the rail and rendering as unbuilt. Secret References moved there from System Configuration - **one node, verified programmatically**.
- **M3 resolved.** `Permissions` is a first-class leaf and the screen is built.
- **M7 still open.** The specification was re-read and defines nothing for Security Groups. The node stays visibly deferred rather than being invented.

## 8. Known issues and items carried forward

| # | Item | Status |
|---|---|---|
| 1 | `audit_events` mass deletes bypass the model guard | **CLOSED 25 August 2026.** Two `BEFORE` triggers applied to production and proved - `UPDATE audit_events SET action = action LIMIT 1;` returns `#1644`. The privilege grant originally proposed could not express it: MySQL has no DENY. SEC-DEC-037 |
| 2 | M7 Security Groups has no requirement | **Open.** Needs a requirement, not an implementation |
| 3 | M2, M4, M5 menu gaps | Gates 4 and 5 |
| 4 | Access Reviews are server-rendered, deviating from plan D4 | Recorded as D5. React remains planned for ADM-020 in gate 5 |
| 5 | The Administrator read grants in the Release 1 matrix | Still not implemented. SEC-DEC-020 |
| 6 | Control-plane hosting geography and privacy regime | **BOTH CLOSED 25 August 2026.** Server, backups and replication all Singapore-only; the **Singapore PDPA applies** (DEC-002) |
| 12 | **PDPA obligations this application does not yet meet** | **Open, planned for gate 4.** No way to answer an access or correction request; no breach-notification workflow against a three-calendar-day deadline; the seven-year retention baseline needs a per-category basis rather than one number. DEC-002, SEC-DEC-042 |
| 13 | `organisations.privacy_contact` is optional | **Open, product owner decision.** SEC-DEC-043 |
| 7 | Scheduler cron entry not installed | **RESOLVED 25 August 2026.** The live Platform Overview reports Scheduler **Healthy, last run 1 minute ago** |
| 8 | R1.2 migrations on production | **CONFIRMED RUN 25 August 2026.** `/admin` renders on the live site, and reaching it requires `users.status` and `user_roles`, both added by R1.2. The schema is level with the code |
| 9 | **`QUEUE_CONNECTION=sync` in production** | **New, open.** The deploy's `.env` template sets it, so queued work runs inline in the web request. Harmless today because nothing is queued, but **ADM-022 Background Jobs in gate 6 needs a real queue driver and a worker**. Raise before gate 6, not during it |
| 10 | `audit_events` append-only triggers | **Applied and proved 25 August 2026.** SEC-DEC-037 |
| 11 | **If `audit_events` is ever rebuilt, the triggers must be re-applied by hand** | **Active constraint.** They belong to the table, so a rollback takes them with it and a re-migrate does not bring them back. SEC-DEC-039 |

## 9. Result against the gate

| Gate 2 criterion | Result |
|---|---|
| System Administrator exists | **Pass.** Six built-in roles seeded by migration; the tier model is live and protected |
| User Registry works | **Pass.** Create, edit, place, disable, lock, unlock, access windows, search, filter, pagination |
| Roles work | **Pass.** Built-in and customer-defined, with a ceiling that cannot be raised and a permission editor that refuses delegation of what the actor lacks |
| Permissions enforced server-side | **Pass.** Three layers, one implementation, with a test asserting they agree |
| Domain entitlement model exists | **Pass.** Separate table, separate route, separate audit event, and tests asserting neither dimension implies the other |
| The last System Administrator cannot be removed | **Pass.** Three paths, one guard, tested separately |

**Gate 2 was accepted subject to one security blocker, raised in review on 25 August 2026 and fixed in the same pull request. See section 10.**

---

## 10. Review fix - cross-organisation mutation boundary and sign-in disclosure

**Raised by:** the product owner, in review of R1.2, 25 August 2026.
**Fixed in:** PR #18, the same pull request. No separate branch and no separate PR.

### 10.1 The blocker, and that it was real

`users` deliberately carries no global organisation scope, because it is the
authentication table and a fail-closed global scope there would mean nobody can
sign in when the context fails to resolve (SEC-DEC-022). That choice moves the
whole tenancy burden onto the paths that WRITE an account.

R1.2 discharged that burden on the READ paths only. `UserController::show()` and
`edit()` called `authorizeSubject()`; the five mutation routes did not, and
`UserRegistry` had no organisation check at all.

**The hole was verified before it was fixed.** A throwaway test was written to
demonstrate it, and it succeeded: a System Administrator in one organisation
disabled a Viewer in another by supplying that account's id, with a second
administrator present so the last-administrator invariant could not be what
refused. The probe was then deleted and replaced by the permanent tests below.

### 10.2 What was changed

| Change | Detail |
|---|---|
| `UserRegistry::assertInOrganisation()` | Called first by all seven mutations: `update`, `changeTier`, `changeStatus`, `assignRole`, `removeRole`, `grantEntitlement`, `revokeEntitlement`. **The service is authoritative** - console commands, queued jobs, future APIs and the access-review applier all pass through it |
| `UserRegistry::assertRoleInOrganisation()` | The same hole from the other side: another organisation's role being attached to one of ours. A null owner is the shared built-in set and is allowed |
| `SubjectOutsideOrganisation` | Extends `DomainException`, **not** `RuntimeException`, so no controller's existing catch block can swallow a tenancy violation into a friendly form error. Implements `HttpExceptionInterface` for a 404 |
| `UserController::authorizeSubject()` | Now called by all five mutation routes as well, as the early check for a clean 404. It asks the service, so "in this organisation" has one definition |
| `MicrosoftSignInController::resolve()` | **A second real bug the guard exposed:** SSO created accounts with no organisation, which under the new rule would make them permanently unmanageable. New accounts are now placed, and the sign-in is refused rather than creating an unowned account if no organisation can be resolved |

**404, not 403**, following the convention already set by `authorizeSubject()`. A
403 confirms the id exists and belongs to somebody; the ids are sequential
integers, so from this organisation's point of view the record genuinely is not
found and saying so tells an id-probing attacker nothing. SEC-DEC-034.

**Fails closed in both directions.** An account with no organisation is refused
as well as one belonging to somebody else.

**Every refusal is audited** before the exception is thrown, and the audit reason
names the operation but never the other organisation.

### 10.3 Required tests, all present

`tests/Feature/Identity/CrossOrganisationMutationTest.php`, 12 tests. In every
one the attacker is a **System Administrator with a second administrator beside
them**, and the victim is a plain Viewer, so no refusal can be explained by an
insufficient tier, a missing permission or the last-administrator invariant.
Organisation isolation is the only thing left to refuse the operation.

| Operation | Direct service test | HTTP route test |
|---|---|---|
| Profile update | Yes | Yes |
| Tier change | Yes | Yes |
| Status change | Yes | Yes |
| Role assignment | Yes | Yes |
| Role removal | Yes | Yes |
| Entitlement grant | Yes | Yes |
| Entitlement revoke | Yes | Yes |

Every test confirms all six required properties: the operation is refused; the
target user is unchanged; roles are unchanged; entitlements are unchanged; no
cross-organisation data is returned; and a denial audit event is produced.
"Unchanged" is asserted against a full snapshot of the victim - name, email,
role, status, type, organisation, placement, access window, roles and
entitlements - not a single field.

Three further tests: a role from another organisation cannot be attached to one
of our accounts; an unplaced account is refused too; and **the same operations
still succeed within one organisation**, so the guard cannot pass by having
broken the feature.

### 10.4 Microsoft sign-in disclosure, made explicit

**Option 2 chosen and implemented**, as preferred in review.

The credential form returns one identical sentence for a wrong password, an
unknown address and a suspended account, because nobody has proved anything at
that point and naming the state would be account enumeration.

The Microsoft path names the person's own state - disabled, locked, expired, or
the date their window ended - and who to ask. Microsoft has already
authenticated them and it is their own account, so this enumerates nothing.

**The earlier wording was wrong and has been corrected.** SEC-DEC-027 claimed
the refusal was byte-identical without scoping that to the credential path, and
the PR body repeated it. Both are corrected, and SEC-DEC-032 records the
Microsoft decision.

**The audit trail is unchanged.** The same `auth.login.failed` / `denied` event
is written either way, and its reason may be fuller than the sentence shown.

Five tests cover it, including one that follows the redirect and asserts the
rendered page names no other account and no configuration value.

### 10.5 Results after the fix

```text
php artisan test
  tests: 261   passed: 261   assertions: 2299

./vendor/bin/pint --test
  result: passed
```

18 new tests since the blocker was raised (243 to 261).

Browser verification re-run over the eleven gate 2 screens at 1440x900 and
390x844 in both themes: no console error, no page error, no horizontal page
overflow. No visual change was expected or seen - the fix is a service-layer
guard and a message string.

**Gate 2 is met.** R1.3 has not been started and needs review of this batch
first.

---

# Appendix A - Rollback-safe proof of the audit DELETE trigger

**Status: OUTSTANDING.** SEC-DEC-037's `BEFORE UPDATE` trigger was proved in
production on 25 August 2026. The `BEFORE DELETE` trigger has NOT been proved,
and it is the one that matters: a mass delete is the attack the append-only
control exists to stop.

**Do not run `DELETE FROM audit_events LIMIT 1;` against production.** If the
trigger is missing or has stopped firing, that statement destroys a real audit
record, and the record it destroys is evidence.

This procedure proves the same thing without a real row ever being at risk. It
inserts its own marker inside a transaction, tries to delete only that marker,
and rolls the whole thing back. Either the trigger blocks the delete - which is
the pass - or it does not, in which case only the marker was deleted and the
rollback removes the marker too.

It does not block production acceptance of gate 3. It DOES block go-live.

## Step 0 - confirm rollback will actually work

```sql
SELECT ENGINE
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'audit_events';
```

**Expected: `InnoDB`.** If this returns `MyISAM`, STOP. MyISAM does not support
transactions and the rollback in step 3 would do nothing, which would turn this
safe procedure into the unsafe one.

## Step 1 - confirm both triggers are still attached

```sql
SELECT TRIGGER_NAME, ACTION_TIMING, EVENT_MANIPULATION
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE()
  AND EVENT_OBJECT_TABLE = 'audit_events';
```

**Expected: two rows** - `audit_events_no_update` (BEFORE UPDATE) and
`audit_events_no_delete` (BEFORE DELETE). If the DELETE trigger is absent, skip
to "If the proof fails" below and recreate it before doing anything else.

## Step 2 - record the count

```sql
SELECT COUNT(*) AS before_count FROM audit_events;
```

Write the number down. Step 4 compares against it.

## Step 3 - the proof

Paste all four statements into ONE phpMyAdmin SQL box and submit ONCE. Splitting
them across submissions can put each on its own connection, and the transaction
would not span them.

```sql
START TRANSACTION;

INSERT INTO audit_events
  (organisation_id, occurred_at, actor_user_id, actor_type, actor_label,
   action, module, resource_type, resource_id, outcome,
   before_summary, after_summary, reason, ip_address, correlation_id,
   environment, created_at)
VALUES
  (NULL, UTC_TIMESTAMP(), NULL, 'system', 'trigger proof',
   'audit.trigger.proof', 'Security', 'audit_events', 'delete-trigger-proof',
   'succeeded', NULL, NULL,
   'Rollback-safe proof of the BEFORE DELETE trigger. Never committed.',
   NULL, NULL, 'production', UTC_TIMESTAMP());

DELETE FROM audit_events WHERE action = 'audit.trigger.proof';

ROLLBACK;
```

**Expected result - this is the PASS:**

```text
#1644 - audit_events is append only: rows cannot be deleted
```

phpMyAdmin stops at the failing statement and will not run the `ROLLBACK` that
follows it. That is fine: the transaction was never committed, and MySQL rolls
back an open transaction when the connection closes. Submit `ROLLBACK;` on its
own anyway, so the state is closed deliberately rather than by timeout.

## Step 4 - confirm nothing was left behind

```sql
SELECT COUNT(*) AS after_count FROM audit_events;
SELECT COUNT(*) AS proof_rows FROM audit_events WHERE action = 'audit.trigger.proof';
```

**Expected:** `after_count` equals the number from step 2, and `proof_rows` is
`0`. If `proof_rows` is not 0, the marker committed - delete it is not possible
while the trigger stands, so drop the trigger, delete the marker, recreate the
trigger, and re-run this appendix from step 1.

## If the proof fails

A failure here means step 3's `DELETE` **succeeded** with no `#1644`. The
append-only guarantee is not in force at the database, and only the model hooks
are protecting the trail - which SEC-DEC-021 already established do not fire on
a mass delete.

1. Run `ROLLBACK;` immediately.
2. Run step 4 and confirm `proof_rows` is `0`.
3. Recreate the trigger:

```sql
CREATE TRIGGER audit_events_no_delete
BEFORE DELETE ON audit_events
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_events is append only: rows cannot be deleted.';
```

4. Re-run this appendix from step 1.
5. Record the gap in `doc/context/SECURITY_PRIVACY_DECISIONS.md` against
   SEC-DEC-037, including how long the trigger was absent if that can be
   established.

## When to re-run this

Per SEC-DEC-039, triggers belong to the table and do not survive it. Re-run this
appendix after any operation that rebuilds `audit_events`, after any database
credential rotation that recreates the application's database user
(SEC-DEC-040), and as part of go-live acceptance.

---

# Gate 3 - Security (R1.3)

**Features:** ADM-009 Authentication Policy, ADM-010 Session Policy, ADM-011 API
Security, ADM-012 Secret References, plus the Security Overview roll-up.
**Plan:** `doc/execution/R1.3-GATE-3-SECURITY-PLAN.md`, decisions D1 to D5
approved by the product owner 25 August 2026.
**Navigation:** `doc/MENU_STRUCTURE.md` 12.16, route family settled by DEC-001.

## 1. Automated tests

| Command | Result |
|---|---|
| `php artisan test` | **384 tests, 2818 assertions, all passing.** 269 before this gate, so 115 are new |
| `./vendor/bin/pint` | Clean |
| `php artisan migrate` then `migrate:rollback --step=2` then `migrate` | Both new migrations applied, rolled back and re-applied without error |

New test files:

| File | Tests | What it proves |
|---|---|---|
| `tests/Feature/Security/SecurityPolicyServiceTest.php` | 16 | The six guards on the policy store, organisation isolation, and that no catalogue key is redacted out of its own audit trail |
| `tests/Feature/Security/AuthenticationPolicyTest.php` | 16 | That ADM-009 CHANGES BEHAVIOUR. Every test drives a real sign-in and asserts what the policy did to it |
| `tests/Feature/Security/SessionPolicyTest.php` | 24 | Idle and maximum enforcement, both session drivers, revocation, concurrency, and the four re-authentication paths |
| `tests/Feature/Security/ApiSecurityTest.php` | 18 | The headers on a real response, the payload limit, and all eight controls including one deliberately broken |
| `tests/Feature/Security/SecretReferenceTest.php` | 14 | That a credential cannot reach the table through the form, the model, or any free-text field |
| `tests/Feature/Security/SecurityOverviewTest.php` | 15 | That the roll-up tells the truth. Each test creates a real problem and asserts it surfaces |
| `tests/Feature/Security/SecurityAccessTest.php` | 12 | Structural: the route family, the gating, the rail-route agreement, and the two-dimension model |

## 2. Migrations

| Migration | Tables | Rollback |
|---|---|---|
| `2026_08_26_090000_create_security_policies_table` | `security_policies` | Working `down()`, verified |
| `2026_08_26_090100_create_secret_references_table` | `secret_references` | Working `down()`, verified |

**One table, not the two the release plan listed.** `session_policies` is dropped:
authentication and session policy are the same key/value store viewed through
two screens. SEC-DEC-046.

No existing table is altered, so rolling this gate back cannot damage gate 1 or
gate 2 data.

## 3. Permissions introduced

| Key | Ceiling | Auto-granted from | Risk | Audited |
|---|---|---|---|---|
| `admin.security.view` | System Administrator | System Administrator | Normal | No |
| `admin.security.update` | System Administrator | System Administrator | High | Yes |
| `admin.secrets.view` | System Administrator | System Administrator | Normal | No |
| `admin.secrets.manage` | System Administrator | System Administrator | High | Yes |

None names a business domain, and a System Administrator holding all four still
holds no entitlement to Sales, Finance or People data. Asserted, not assumed:
`SecurityAccessTest::a_system_administrator_holds_no_business_domain_by_virtue_of_the_security_grants`.

## 4. Audit events introduced

| Action | When | Outcome recorded |
|---|---|---|
| `security.policy.updated` | Any policy change, and any refused change | Succeeded or Denied, with before, after and the reason |
| `security.sessions.revoked` | An administrator ends an account's sessions, or is refused | Succeeded with a count, or Denied with why |
| `security.sessions.limited` | The concurrency policy ends older sessions | Succeeded with the count and the limit |
| `security.secret_reference.created` / `.updated` / `.retired` | Secret reference lifecycle | Succeeded, with the pointer but never a value |
| `security.request.refused` | An oversized request | Denied, with the declared size |
| `auth.session.expired` | Idle or maximum timeout ends a session | Succeeded, naming which rule |
| `auth.reauthentication.succeeded` / `.failed` | Identity confirmation | Succeeded or Denied |

Existing `auth.login.failed` gains new reasons from the ADM-009 checks: mode,
tenant, domain, and self-provisioning.

## 5. Security checks

| Check | Result |
|---|---|
| Every security route gated by a declared permission | Pass, structurally asserted |
| Every security WRITE route also demands a fresh identity confirmation | Pass, structurally asserted |
| Rail node and route gated by the same permission | Pass, leaf by leaf |
| Cross-organisation policy read or write | Refused, global scope, fails closed |
| Cross-organisation session revocation | 404, through the `UserRegistry` guard (SEC-DEC-033, SEC-DEC-034) |
| A credential reaching `secret_references` | Refused at three layers, and removed from the flashed input |
| A credential reaching `security_policies` | Refused, and the refusal audited without the value |
| Secret value rendered anywhere | None. No column holds one |
| Entra client secret on a screen | None. Presence only, SEC-DEC-017 |
| Refusal wording discloses the tenant or domain allow-list | No. SEC-DEC-045 |
| HSTS default | **OFF**, duration one day. Gate 3 rule 8 |
| A control that cannot be verified reported as Healthy | None. `NotVerified` is a distinct state and a distinct badge |
| An unavailable action rendered as a working control | None. The revocation action is absent, and the route refuses independently |

## 6. Browser verification

Chromium, signed in as a System Administrator, on all six screens plus the
confirmation prompt, in **light and dark at 1440px and 390px** - 25 combinations.
No console errors, no page errors, and no page scrolls sideways.

**Seven defects it caught that 384 passing tests did not:**

| # | Defect | Fix |
|---|---|---|
| 1 | Four tables used `col-primary` on their first column. That class is 55% wide and is for a row name carrying a long description; here the LAST column was the long one, so the middle columns wrapped to two or three words a line | `col-label` |
| 2 | "1 reference have already expired", "for 1 minutes", "5 attempt(s)" | Singular and plural spelled out per case |
| 3 | The same four-sentence "Not Available" paragraph appeared beside three fields AND in the banner | Short phrase beside each field, full explanation once in the banner |
| 4 | "Enforced on every request by EnforceSessionPolicy" - a class name in user-facing help | Rewritten |
| 5 | The reason field carried a required asterisk while being only conditionally required, which would make the asterisk mean something different here than on every other screen | Asterisk removed; the badges and help text carry it |
| 6 | The reason placeholder wrapped to three lines in a three-row box at 390px and overflowed it | Shortened |
| 7 | `.settings-foot` had no `flex-wrap`, so on the first form with two buttons AND a note the note was squeezed into a ribbon a few words wide at 390px | `flex-wrap: wrap`, which also fixes the same latent problem on the existing settings screens |

## 7. What the tests caught that review had not

| # | Defect | How it was found |
|---|---|---|
| 1 | **A refused credential was being flashed back into the session and rendered into the next page's HTML.** A form request is a COPY of the request and the exception handler flashes the original, so blanking the field on the form request did nothing | A test that looked for the value on the page afterwards, rather than only asserting the save was refused. SEC-DEC-051 |
| 2 | The revocation audit summary recorded `[redacted]` instead of a count, because the key was `sessions_ended` and the redactor replaces any key containing "session" | An assertion comparing the recorded count to 2. SEC-DEC-044 |
| 3 | Laravel 13 renamed `ValidateCsrfToken` to `PreventRequestForgery`, so the CSRF control reported itself missing on a correctly configured application | The test asserting every control is healthy. SEC-DEC-047 |
| 4 | Laravel's `storage.local` routes carry no middleware and looked like an anonymous hole. They are signature-gated inside the handler - **but only while the local disk is private** | Investigating a failing control, which turned a false positive into a real conditional check. SEC-DEC-053 |
| 5 | `DATA_SOVEREIGNTY_REGISTER.md` still said the applicable privacy regime was "not determined" - DEC-002 had updated the sovereignty standard and the decisions register but not this table | Re-reading the registers before adding to them |

## 8. Menu structure

`doc/MENU_STRUCTURE.md` 12.16 is fully built. Five leaves, five routes, exactly
the family DEC-001 settled. No leaf renders as unbuilt, no duplicate node exists,
and no route was added outside the recorded family.

**Gap M9 highlighted:** Security Overview has no ADM feature behind it. Built as
a read-only roll-up under decision D5; the gap stays open in case a later
requirement defines the screen.

## 9. Known issues and items carried forward

| # | Item | Status |
|---|---|---|
| 1 | **`SESSION_DRIVER=file` on production.** Session revocation and concurrent session limits cannot run | Built, tested under both drivers, reported unavailable on screen. Switching is a separate approved change, and it signs everybody out at the moment it takes effect |
| 2 | **HSTS is off.** Enabling it on production is a separate approval, because it cannot be withdrawn from a browser that has already seen it | By design, gate 3 rule 8 |
| 3 | **The default authentication mode shuts the credential form to local accounts below System Administrator.** The Authentication Policy screen states how many accounts that is | Check the count on production before relying on it |
| 4 | The Content Security Policy is report-only. It has not been run against real usage yet, so what it would block is unknown | Read the browser reports before enforcing |
| 5 | No provider is ever contacted to confirm a secret reference's expiry date. Every date is one an administrator typed in, and the screen says so | By design in Release 1. Resolving a reference belongs with gate 5 |
| 6 | `MASTER_ADMIN_EMAILS` is in the deploy `.env` template and **nothing in the application reads it** | Found while implementing ADM-009. Left alone: it is in the deploy workflow, and removing it is a deployment change |
| 7 | Gap M9, Security Overview has no feature | Open, mitigated |
| 8 | The audit DELETE trigger is still unproved | Appendix A. Does not block gate 3; **does block go-live** |
| 9 | `QUEUE_CONNECTION=sync` on production | Open, gate 6 |
| 10 | PDPA gaps 1 to 3 | Open, gate 4 |

## 10. Result against the gate

| Exit criterion | Result |
|---|---|
| ADM-009 Authentication Policy implemented and enforced | **Pass.** The mode, the allow-lists, auto-create, the threshold and the lock duration all change real sign-in behaviour |
| ADM-010 Session Policy implemented and enforced | **Pass**, with revocation and concurrency reporting themselves unavailable on this environment by design |
| ADM-011 API Security implemented | **Pass.** Eight controls checked live; the headers middleware and the payload limit are new and real |
| ADM-012 Secret References implemented | **Pass.** No credential can reach the table through any path |
| Security Overview | **Pass**, as a read-only roll-up under D5 |
| Navigation matches MENU_STRUCTURE 12.16 | **Pass** |
| Server-side enforcement, not navigation alone | **Pass**, structurally asserted |
| All security policy changes audited | **Pass**, and there is no unaudited path |
| High-risk changes require a reason | **Pass** |
| Secret References stores metadata only | **Pass**, three layers |
| No credential persisted anywhere | **Pass** |
| Not Verified rather than Healthy where unverifiable | **Pass** |
| HSTS off by default | **Pass** |
| No working-looking action for an unavailable capability | **Pass** |
| Automated tests, Pint, migrations up-down-up | **Pass** |
| Responsive UI in both themes | **Pass**, 25 combinations |
| Production `.env` unchanged | **Pass.** Nothing in this gate touches the server |
| Production database unchanged | **Pass.** Two new migrations, not yet run on production |
| No gate 4 PDPA feature implemented | **Pass** |

**Gate 3 is complete and ready for review.**

## 11. What the reviewer should know before merging

**This release contains two migrations.** The deploy workflow ships code only.
Until `php artisan migrate --force` is run on the server, every Security screen
will fail: the Overview, all three policy screens and Secret References all read
tables that will not exist yet. The exact command and the screens at risk are in
the completion report.

## 12. Deployment order and release safety

Added after a product-owner review of PR #21 found that gate 3 could not survive
its own release. It is recorded here rather than in a commit message because it
is a property of every future release that adds a table, not a one-off fix.

### The problem, measured rather than assumed

The deploy workflow ships code and does **not** run migrations. That is
deliberate - a deployment that migrates a production database unattended is a
deployment that can lose data - but it opens a window on every release that adds
a table:

```text
code deployed  ->  migration not yet run  ->  the new tables do not exist
```

Gate 3's middleware runs on the **web** stack, not only on `/admin/security`.
`SecurityHeaders` reads a policy on every single response and
`EnforceSessionPolicy` reads two more, so the very first request after a deploy
queried a table that did not exist.

**The measured result: `GET /sign-in` returned 500.** Not the Security screens -
the sign-in screen, so nobody could get in to notice. A test written to
demonstrate it failed exactly that way before any fix was made.

### How readiness is detected

`App\Modules\Security\Support\SecurityStorage` asks one specific question -
`Schema::hasTable()` - and answers only that.

**It does not catch database exceptions.** Catching one and falling back would
also swallow a broken connection, a permissions problem or a corrupt table, and
report all three as "everything is fine, using defaults". Those must still fail
loudly. The check is a schema question, not an error handler.

The class is a container **singleton** and memoises per request, so the answer
costs one schema query per table per request rather than one per consumer, on
every response the application sends. `SecurityPolicies`, `SecurityCapabilities`,
`AuthenticationGuard`, `Reauthentication` and `SessionRegistry` were registered
as singletons in the same change, for the same reason.

### What happens in the window

| Path | Behaviour | Why |
|---|---|---|
| **Policy READ** | Falls back to the `config/security.php` default | Not a compromise: with no table there can be no override, so the catalogue default IS the value in force. The secure defaults stay authoritative |
| **Policy WRITE** | Refused with `SecurityStorageNotInitialised`, before any other check runs | Accepting a write and discarding it would tell an administrator their security policy had changed when nothing had. Nothing is written and nothing is audited as though it might have been |
| **Security headers** | Sent, using the catalogue defaults | A release window must not be a window with the headers switched off |
| **Session policy** | Enforced, using the catalogue defaults | The idle timeout still ends a stale session. A window that silently suspended it would be a security regression dressed as resilience |
| **Critical actions** | Still confirmed | Same reason |
| **Secret References index** | A controlled "Migration required" state | **Deliberately NOT the empty state.** "No references recorded" says the store exists and is empty, which is a different and far more comforting fact than "we cannot tell you" |
| **Secret References writes and detail pages** | Refused by the `security-storage` middleware, redirected to the index with the reason | Fails closed |
| **Security Overview** | Renders. Secret posture reads **Not Verified**, and the storage gap is the first configuration gap listed | Gate 3 rule 9 |
| **Expiring credentials panel** | "Cannot be checked yet" | It previously showed a green tick and "nothing expiring in the next 30 days" - a false healthy about the one thing on that page that can take an integration down, at exactly the moment the screen cannot see the data |
| **Everything else** | Unaffected | Asserted screen by screen |

### A second defect the fix exposed

Four secret-reference routes carry a `{secretReference}` segment, and Laravel's
`SubstituteBindings` lives in the **`web` middleware group**, which runs before
any route-level middleware. An implicit model binding therefore queried
`secret_references` *before* `security-storage` could refuse, and a typed URL
during the window still returned a raw database error.

The parameter is now a plain integer resolved inside the controller, matching
how `sessions.revoke` already resolves its subject. Caught by the test that
walks all five routes with the table dropped, not by review.

### Evidence

`tests/Feature/Security/DeploymentOrderTest.php`, 21 tests:

| Area | Proved |
|---|---|
| Pre-migration | `/sign-in` renders AND a real sign-in completes; a guest redirect is normal; nine existing screens return 200; headers and session policy use the safe defaults; the idle timeout still ends a stale session; a critical action is still confirmed |
| Screens | Overview, all three policy screens and the Secret References index render and explain themselves; the Save button and the New reference button are absent rather than disabled |
| Writes | The service refuses with a controlled message quoting no SQL and no table name; nothing changes; nothing is audited; the form shows the message; all five secret write and detail routes are refused |
| Round trip | pre-migration -> up -> gate 3 behaviour -> down -> safe again -> up, asserted end to end in one test |
| Half-migrated | One table present and the other missing is handled independently, which is the state a failed migration leaves |
| Detection | Readiness is a schema question; the answer is memoised; the services are singletons |

Manual verification against a database with the gate 3 migrations rolled back,
served on a real HTTP server and driven in Chromium in both themes:

```text
/sign-in                        200, with all five base headers and the report-only CSP
/                               200
/admin, /admin/users            200
/admin/organisation             200
/admin/system/diagnostics       200
/admin/security                 200
/admin/security/authentication  200
/admin/security/sessions        200
/admin/security/api             200
/admin/security/secrets         200
/admin/security/secrets/new     302 to the index, with the reason
/admin/security/secrets/1       302 to the index, with the reason
```

Migration sequence run against a file database: up, down, up, down, up. Both
migrations applied and rolled back cleanly five times.

### What is deliberately NOT done

**The deployment workflow is unchanged.** It still does not run migrations, and
making it do so is an architecture change requiring separate approval. This work
makes the application survive the window; it does not remove the window.

### The rule this establishes for later gates

Any release adding a table that request-path code reads must either carry the
same readiness fallback, or not be read from the request path at all. Gate 4's
`data_protection_profiles` and gate 5's `integrations` are both candidates -
whichever screens read them, if anything on the web middleware stack does, the
same window applies.
