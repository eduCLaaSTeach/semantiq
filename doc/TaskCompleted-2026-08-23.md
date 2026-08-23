# Day work note - 23 August 2026

Written at the pause point so the next session starts from fact rather than
memory. Read the two sections marked STOPPING POINT and STARTING POINT first;
everything after them is detail.

---

## STOPPING POINT

**Administrator Foundation Release 1, gates 1 to 3, are complete, merged, live
in production and formally confirmed.**

`main` is at `2135299`. Both workflows green. Production is serving.

| Gate | State |
|---|---|
| R1.1 Platform Foundation | CONFIRMED |
| R1.2 Identity & Access | CONFIRMED |
| R1.3 Security Foundation | **CONFIRMED, 23 August 2026** |
| R1.4 Data Protection, Sovereignty & PDPA | **READY_FOR_PLAN** |
| R1.5 to R1.7 | LOCKED |

Nothing is half-finished. No branch is left open, no PR is unmerged, no
migration is pending on the server.

## STARTING POINT

**Next action: write the R1.4 gate 4 plan and present it for approval. Do not
write code first.**

The scope is already approved (see "Gate 4 scope" below), so the plan does not
need to re-argue what to build - it needs to work out how, and surface the
decisions that need settling before implementation.

Before drafting it, read in this order:

1. `CLAUDE.md`
2. `IMPLEMENTATION_STATUS.md` - the Administrator Foundation Release 1 section
3. `doc/execution/ADMIN-FOUNDATION-RELEASE-1-PLAN.md`, the R1.4 section
4. `doc/execution/decisions/DEC-002-pdpa-applies.md` - the three PDPA gaps
5. `doc/reference/DATA_PROTECTION_SOVEREIGNTY_STANDARD.md`
6. `doc/context/SECURITY_PRIVACY_DECISIONS.md` - SEC-DEC-043 and 059 to 061
7. `doc/execution/ADMIN-FOUNDATION-RELEASE-1-VERIFICATION.md` sections 12 and 13
8. `doc/MENU_STRUCTURE.md` and `doc/ROLE_MODEL.md`
9. `.claude/reference-template/ui-and-ux-layout-template-shared.md`

Three lessons from gate 3 that the gate 4 plan must carry forward:

- **Deployment order.** The deploy ships code and does not run migrations. Any
  gate 4 table read from the request path needs the same readiness fallback
  `SecurityStorage` provides, or gate 4 repeats the 500-on-sign-in incident.
  Verification section 12 has the pattern.
- **Day-one browser verification.** Both gate 3 defects that reached production
  needed the EMPTY state to appear, and the browser check ran against a seeded
  database. Verify a new screen with nothing configured. SEC-DEC-061.
- **The audit redactor shapes names.** A key or audit-summary key containing
  `auth`, `session`, `secret`, `key`, `token` or `credential` has its value
  replaced by `Redaction`. Check every new key against
  `Redaction::isSensitiveKey()`. SEC-DEC-044.

---

## Gate 4 scope, as approved

| Item | Source |
|---|---|
| ADM-013 Audit Log screen | Release 1 specification |
| ADM-014 Data Protection Profile | Release 1 specification |
| ADM-015 Data Sovereignty Profile | Release 1 specification |
| ADM-016 Sovereignty Exceptions | Release 1 specification |
| PDPA-01 Personal Data Access & Correction workflow | DEC-002, approved into gate 4 |
| PDPA-02 Data Breach Assessment & Notification workflow | DEC-002, approved into gate 4 |
| PDPA-03 Per-category Retention Policy | DEC-002, approved into gate 4 |
| Required Privacy Contact | SEC-DEC-043, approved |

The product owner's detailed field lists for PDPA-01, PDPA-02, PDPA-03 and the
structured privacy contact were given on 23 August 2026 and are the requirement.
Key points not to lose:

- **PDPA-01** must not expose internal security information, credentials, other
  individuals' data or unrestricted raw audit data. Redaction and review rules
  are a gate 4 planning decision. The five known personal-data tables are
  `users`, `audit_events`, `domain_entitlements`, `user_roles` and
  `access_review_items` - **re-scan the schema, that list is not guaranteed
  complete.** If `SESSION_DRIVER` becomes `database`, `sessions` joins it.
- **PDPA-02** is a governed incident workflow, not a notification button.
  Deadlines and reminders must be configurable and traceable to the compliance
  requirement, never magic constants. The PDPC deadline is 3 calendar days.
- **PDPA-03** replaces one universal retention duration with per-category
  policies, each carrying a basis, a start event, a disposal action, an owner
  and an approval state. Seven years may still be right for some categories.
  **SEC-DEC-038 applies:** the audit triggers make routine deletion impossible
  by design, so any retention sweep must drop, delete and recreate them as a
  deliberate, separately approved operation.
- **Privacy contact** must be structured (name, email, phone optional, role or
  title optional), must identify organisations where it is currently null,
  must have a safe backfill workflow, must audit changes, and must not simply
  break existing organisations by tightening validation.

---

## What shipped today

Nine pull requests, all merged to `main` and deployed.

| PR | What | Merge commit |
|---|---|---|
| #14 | Phase 00: sign-in, business Home, role-aware navigation | `ef38e0a` |
| #15 | Deploy fix: restore the `public_html` forwarder | `5849d78` |
| #16 | Harden: keep `doc/` off the web server | `89c3cbd` |
| #17 | **R1.1 gate 1** - platform, organisation scope, audit writer | `733ec84` |
| #18 | **R1.2 gate 2** - identity, roles, permissions, access reviews | `b17f396` |
| #19 | Smoke-test fixes: time zone dropdown, Access Reviews explains itself | `b4a781b` |
| #20 | DEC-002: the Singapore PDPA applies, and four obligations it exposes | `ce6a4b8` |
| #21 | **R1.3 gate 3** - authentication, session and API security policy, secret references | `a90d65a` |
| #22 | R1.3 follow-up: two Security Overview states that claimed more than they knew | `2135299` |

Test suite grew from nothing to **413 tests, 2973 assertions**. Pint clean.

---

## Production state at the pause

Facts, recorded so the next session does not have to re-derive them. No
credentials, no connection strings, no customer data.

| Fact | Value |
|---|---|
| URL | `<APP_BASE_URL>` - semantiq.claas2saas.com |
| Laravel / PHP / Composer | 13.26.1 / 8.5.9 / 2.9.2 |
| Environment | `production`, debug **OFF** |
| Migrations | 23 applied, none pending |
| Drivers | cache `file`, database `mysql`, queue `sync`, session `file`, mail `log` |
| Caches | config, routes, events, views all **NOT CACHED** |
| `public/storage` | **NOT LINKED** |
| Hosting / backups / replication | Singapore / Singapore / none outside |
| Applicable privacy regime | Singapore PDPA (DEC-002) |
| `audit_events` | 11 rows, both append-only triggers active |
| Security posture | **Warning** - authentication |
| Secret references | 0 recorded |
| Security policy overrides | 0 - the secure catalogue defaults are in force |
| Custom roles / role permission grants | 0 |
| Access reviews | 0 |

### Verified today after the production rollback incident

The product owner ran `migrate:rollback --step=4` on production, which went two
migrations deeper than gate 3 and rolled back `seed_built_in_roles` and
`create_access_review_items_table` as well. Re-migrating restored the schema.

**Checked afterwards, and nothing was lost:** audit triggers 2, audit rows 11,
reviews missing items 0, built-in roles 6, role assignments 0. `audit_events`
was never in the rollback, so SEC-DEC-039's trigger constraint was not tripped.

`role_permissions = 0` is CORRECT and was mistakenly flagged as a failure in the
check script: built-in roles derive their permissions from their tier at runtime
and store no grant rows. That table only fills for custom roles.

**Standing guidance from this:** do not roll back migrations on production. Use
a forward migration. `--step` counts migrations, not gates, so it is easy to
take more than intended - and the four re-applied migrations now share batch 3,
so a bare `migrate:rollback` would take all four.

---

## Open items carried forward

Ordered by what would hurt most if forgotten.

| # | Item | Blocks | Where |
|---|---|---|---|
| 1 | **The audit DELETE trigger is still unproved** | **Go-live.** Not gate 3, not gate 4 | Verification Appendix A has a rollback-safe procedure. Do not run `DELETE FROM audit_events LIMIT 1;` |
| 2 | No email domain allow-list on production - any guest in the tenant may sign in | Nothing technically. It is the weakest thing about the live posture | `/admin/security/authentication` |
| 3 | No allowed tenant set - tenant validation falls back to the Entra registration | Nothing technically | Same screen |
| 4 | No credentials tracked - nothing will warn before one lapses | Nothing technically | `/admin/security/secrets` |
| 5 | `SESSION_DRIVER=file` - session revocation and concurrency cannot run | ADM-010's two capability controls | Separate approval. Switching signs everybody out |
| 6 | HSTS off, duration one day | Nothing. Deliberate | Separate approval. Cannot be withdrawn once browsers see it |
| 7 | `QUEUE_CONNECTION=sync` | **Gate 6** ADM-022 needs a real driver and a worker | CFG-QUEUE-001 |
| 8 | Config, routes, events, views not cached in production | Nothing. A real performance cost | Deploy-workflow change, needs approval |
| 9 | `public/storage` not linked | Nothing today. Will matter by gate 5 | Server |
| 10 | `organisations.privacy_contact` optional | Gate 4 closes it | SEC-DEC-043 |
| 11 | Gap M7 - Security Groups has no requirement | Nothing. Node stays unbuilt | Release plan 21.3 |
| 12 | Gap M9 - Security Overview has no feature specification | Nothing. Built as a roll-up under D5 | Release plan 21.3 |
| 13 | SEC-DEC-020 - Administrator read grants in the Release 1 matrix not implemented | Revisit when delegation is wanted | Decisions register |
| 14 | `MASTER_ADMIN_EMAILS` in the deploy env template, read by nothing | Nothing. Tidy-up | `.github/workflows/deploy.yml` |
| 15 | Fabric approved geographies unset | Phase 02 | CFG-SOV-001 |

---

## Decisions made today

Recorded in full in `doc/context/SECURITY_PRIVACY_DECISIONS.md` and
`doc/execution/decisions/`. The ones that shape later gates:

| Reference | Decision |
|---|---|
| DEC-001 | A top-level Security group; Permissions as a first-class leaf |
| DEC-002 | The Singapore PDPA applies, and the four obligations it exposes |
| SEC-DEC-036 | Hosting, backups and replication all Singapore |
| SEC-DEC-037 to 040 | `audit_events` append-only enforced by database triggers, not grants. Re-apply by hand if the table is ever rebuilt |
| SEC-DEC-044 | The audit redactor shapes what configuration keys may be called |
| SEC-DEC-046 | Security policy lives in its own table with a code catalogue |
| SEC-DEC-047 | ADM-011 is a live verification screen, not a settings page |
| SEC-DEC-048 | HSTS ships off; CSP ships report-only |
| SEC-DEC-049 | Session revocation built, reported unavailable, production unchanged |
| SEC-DEC-050 | Re-authentication stores a timestamp and nothing else |
| SEC-DEC-051 | A refused credential is removed from flashed input, not merely rejected |
| SEC-DEC-055/056 | Reads fall back, writes fail closed; schema-not-ready is a schema question, never a caught exception |
| SEC-DEC-059/060 | No screen reports a healthy state it has not earned; explanations are calculated, not fixed text |
| SEC-DEC-061 | Browser verification must include the day-one empty state |

---

## What was found the hard way today

Kept because each one is a habit worth keeping, not a war story.

1. **A 500 on `/sign-in` in the deployment window.** Gate 3 middleware ran on
   the web stack and read a table the migration had not yet created. The whole
   site, not just the Security screens. Caught by the product owner in review of
   PR #21, before it could happen on a release.
2. **A refused credential was being rendered back into the page.** A form
   request is a copy of the request and the exception handler flashes the
   original. Found by a test that looked for the value on the page afterwards,
   rather than only asserting the save was refused.
3. **Two screens reported healthy states they had not earned**, both needing the
   empty production state to appear. Found by the product owner on the live
   site.
4. **A cross-organisation hole in the user mutation paths**, in R1.2. Proved
   with a throwaway test before being fixed.
5. **Roles were decoration**, in R1.2: one tier served as both ceiling and
   auto-grant, so an assigned role could add nothing. Found by a test.
6. **My own GRANT advice was wrong.** MySQL has no DENY, so `UPDATE`/`DELETE`
   cannot be revoked on one table from a user holding them database-wide.
   Triggers were the answer.

The pattern across all six: tests catch logic, browsers catch presentation, and
production catches the empty state. All three are needed.
