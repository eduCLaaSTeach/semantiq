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
