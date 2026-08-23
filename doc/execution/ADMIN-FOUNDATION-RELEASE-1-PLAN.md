# Administrator Foundation Release 1 - Implementation Plan

**Specification:** `doc/execution/Semantiq_ADMINISTRATOR_FOUNDATION_RELEASE_1_v1.0.md`
**Status:** AWAITING APPROVAL. Nothing in section 4 has been implemented.
**Gate:** Section 32 gates 1 to 7, then explicit user confirmation before the Fabric Environment Release.

---

## 1. Current repository assessment

Read from the repository at `0845aa1` and probed against the live site. Not recalled.

| Item | Observed |
|---|---|
| Backend | Laravel 13.26.1, PHP 8.5.9 on the server |
| Frontend | Vite 8, no CSS framework, server-rendered Blade. React not installed |
| Database | MySQL 8.0.46 on cPanel, migrated and live. SQLite in tests |
| Structure | Flat Laravel. `app/Modules/` does not exist |
| Tests | 86 passing, 330 assertions. Pint clean. CI on every pull request |
| Deploy | `main` to cPanel over SSH, live and green |
| Live state | Sign-in, Entra SSO, shell, navigation and the administration 403 all working in production |

### Tables that exist

`users` (with `entra_object_id`, `entra_tenant_id`, `role`, `is_auditor`, `last_signed_in_at`), `domain_entitlements`, `sessions`, `cache`, `jobs`, `password_reset_tokens`.

### Of the 23 tables in section 29

Two exist: `users`, `user_domain_entitlements` (shipped as `domain_entitlements`). **Twenty-one are absent.**

### Of the 25 features

None are built. Three screens exist that this release reshapes: Home, My Intelligence and the Administration landing.

---

## 2. Existing features this release reuses rather than rebuilds

| Asset | Reused for |
|---|---|
| `App\Enums\Role`, six cumulative tiers | ADM-006. Extended, not replaced |
| `App\Enums\BusinessDomain`, seven domains | ADM-005 domain entitlements, section 30 |
| `DomainEntitlement` model and table | ADM-005. Already enforces role-independent domain access |
| `Support\Navigation`, filter-not-fork gate | ADM-007 navigation layer |
| `EnforceNavigationPolicy` middleware | ADM-007 route layer. Extended with permission keys |
| `MicrosoftSignInController`, OIDC with PKCE | ADM-018. The flow exists; this release adds its configuration record |
| `SignInController`, credential path with throttling | ADM-009 local bootstrap administrator |
| Design system, tokens, shell, icon registry | Every screen |
| `semantiq:promote` | Superseded by ADM-005 once the Users screen exists |

The access model already satisfies section 30's central claim - a role alone never grants business data - and has a test asserting a System Administrator with no entitlement is refused Finance.

---

## 3. Decisions, answered 23 August 2026

**D1. Six tiers stay; `collaborator` maps onto `domain_owner`.**
ADM-006 lists five roles. `doc/ROLE_MODEL.md`, which `CLAUDE.md` names as the authorization authority, lists seven and distinguishes a Domain Owner who approves definitions from an Analyst who explores them. Six tiers are live with production data behind them. ADM-006 is read as shorthand, not contradiction: `collaborator` becomes a documented alias for `domain_owner`. No migration, no data rewrite.

**D2. Permissions layer on top of tiers.**
ADM-007's stated purpose is granularity, not replacement. The tier stays the coarse gate; permission keys become the fine one. **Both must agree or the request is denied.** A tier maps to a default permission set, so nothing already tested changes behaviour.

**D3. Administration is reshaped to the ADM tree for this release.**
The live rail advertises Fabric Environment, Data Engineering and Semantic Intelligence, none of which this release builds. A rail promising what the product cannot do is worse than a short one. MENU_STRUCTURE's later groups return as their releases land. The four fixed clusters are untouched.

**D4. Blade for administrator CRUD; React where state is genuinely interactive.**
Lists, forms and policy pages are the archetypes the design system already covers, work without JavaScript, and need no client state. React 19 is introduced for **Connection Test Centre** (ADM-020) and **Access Reviews** (ADM-008), where live results and multi-step state earn it, and later for Ask SemantIQ.

---

## 4. Batches

Seven changes, aligned to the seven dependency gates in section 32. Each is a pull request. Each ends at a gate that can be checked before the next begins.

### R1.1 - Gate 1, Platform

`app/Modules/` established. Organisation context and the global scope that fails closed. Audit infrastructure. System settings and feature flags. The ten status values. Health, diagnostics.

**Features:** ADM-001 Platform Overview, ADM-021 System Configuration, ADM-024 Diagnostics.
**Tables:** `organisations`, `audit_events`, `system_settings`, `feature_flags`.

### R1.2 - Gate 2, Identity and Access

**Features:** ADM-002 Organisation Profile, ADM-003 Business Units, ADM-004 Teams, ADM-005 User Registry, ADM-006 Roles, ADM-007 Permissions, ADM-008 Access Reviews.
**Tables:** `business_units`, `teams`, `roles`, `permissions`, `role_permissions`, `user_roles`, `access_reviews`, `access_review_items`.

The invariant this gate exists for: **the last active System Administrator cannot be removed, disabled or demoted.**

### R1.3 - Gate 3, Security

**Features:** ADM-009 Authentication Policy, ADM-010 Session Policy, ADM-011 API Security, ADM-012 Secret References.
**Tables:** `security_policies`, `session_policies`, `secret_references`.

### R1.4 - Gate 4, Governance

**Features:** ADM-013 Audit Log screen, ADM-014 Data Protection Profile, ADM-015 Data Sovereignty Profile, ADM-016 Sovereignty Exceptions.
**Tables:** `data_protection_profiles`, `data_sovereignty_profiles`, `sovereignty_exceptions`.

### R1.5 - Gate 5, Integration

**Features:** ADM-017 Integration Registry, ADM-018 Microsoft Entra Integration, ADM-019 API Configuration, ADM-020 Connection Test Centre.
**Tables:** `integrations`, `integration_test_runs`, `api_client_configs`.

This is the gate the Fabric release depends on.

### R1.6 - Gate 6, Operations

**Features:** ADM-022 Background Jobs, ADM-023 Scheduler. Workflow orchestration with correlation IDs, retry and resumability.

### R1.7 - Gate 7, Verification

ADM-025 Help framework and its topics. Context registers refreshed. `doc/execution/ADMIN-FOUNDATION-RELEASE-1-VERIFICATION.md` with evidence against every gate.

---

## 5. Proposed Laravel modules and classes

Per D2 of the Phase 00 plan, new code lands in modules; the working sign-in and shell move later.

```text
app/Modules/
  Platform/        health, diagnostics, system settings, feature flags, status enums
  Identity/        organisations, business units, teams, users, roles, permissions,
                   entitlements, access reviews
  Security/        authentication policy, session policy, secret references, API guards
  Governance/      data protection, sovereignty, exceptions
  Audit/           the immutable writer, the query surface, redaction
  Integration/     registry, Entra, API clients, connection tests, capability registry
  Help/            topics, contextual help resolution
  Workflow/        queued orchestration, correlation, retry, resumability
```

Notable classes:

| Class | Responsibility |
|---|---|
| `Identity\Support\OrganisationContext` | The one answer to "whose data is this", session or explicitly bound |
| `Identity\Support\BelongsToOrganisation` | Global read scope plus create-time stamping. Fails closed |
| `Identity\Support\PermissionRegistry` | The catalogue of permission keys and their risk level |
| `Identity\Policies\SystemAdministratorGuard` | Refuses removal of the last active System Administrator |
| `Audit\AuditLogger` | The single write path. Hashes, never payloads |
| `Audit\Redaction` | One definition of what counts as a secret, used by audit and logging |
| `Security\SecretResolver` | Resolves a reference to a value at point of use. Never persists one |
| `Integration\ConnectionTester` | Bounded timeout, sanitized error, correlation ID |
| `Integration\CapabilityRegistry` | Whether an operation is stable, preview or guided-only |
| `Workflow\WorkflowRunner` | Resumable orchestration |
| `Platform\HealthProbe` | Database, queue, scheduler, cache, integrations |

---

## 6. Proposed React screens

Two in this release, both mounted inside the existing Blade shell rather than replacing it.

| Screen | Why React |
|---|---|
| ADM-020 Connection Test Centre | A test runs, streams progress and returns a sanitized result without a page reload |
| ADM-008 Access Reviews | Multi-item decision state held across a long review session before one submit |

Everything else is server-rendered.

---

## 7. Proposed migrations

Twenty-one new tables, in gate order, each reversible. Names follow Laravel conventions and section 29, with one deliberate difference recorded below.

| Gate | Tables |
|---|---|
| 1 | `organisations`, `audit_events`, `system_settings`, `feature_flags` |
| 2 | `business_units`, `teams`, `roles`, `permissions`, `role_permissions`, `user_roles`, `access_reviews`, `access_review_items` |
| 3 | `security_policies`, `session_policies`, `secret_references` |
| 4 | `data_protection_profiles`, `data_sovereignty_profiles`, `sovereignty_exceptions` |
| 5 | `integrations`, `integration_test_runs`, `api_client_configs` |
| 6 | `workflow_runs` |

Alterations to `users`: `organisation_id`, `business_unit_id`, `team_id`, `user_type`, `status`, `authentication_source`, `access_start`, `access_end`, `external_reference_id`.

**Naming difference:** section 29 lists `user_domain_entitlements`; the shipped table is `domain_entitlements`. Renaming a live table to match a document is a migration with no behavioural benefit. The existing name stands and the register records the mapping.

Every record carries the section 3 common fields: immutable id, lifecycle status, created and updated timestamps, created-by and updated-by references, and a version indicator where concurrency matters.

---

## 8. Proposed routes

All under `/admin`, all gated by a permission key at the route boundary, not only in the rail.

```text
GET    /admin                          admin.platform.view
GET    /admin/organisation             admin.organisation.view
PUT    /admin/organisation             admin.organisation.update
GET    /admin/business-units           admin.business_units.view
GET    /admin/teams                    admin.teams.view
GET    /admin/users                    admin.users.view
POST   /admin/users                    admin.users.create
PUT    /admin/users/{user}             admin.users.update
POST   /admin/users/{user}/disable     admin.users.disable
POST   /admin/users/{user}/roles       admin.roles.assign
GET    /admin/roles                    admin.roles.view
GET    /admin/permissions              admin.permissions.view
GET    /admin/access-reviews           admin.access_reviews.view
GET    /admin/security/*               admin.security.view / update
GET    /admin/secret-references        admin.secrets.view
GET    /admin/audit                    admin.audit.view
GET    /admin/data-protection          admin.data_protection.view
GET    /admin/sovereignty              admin.sovereignty.view
POST   /admin/sovereignty/exceptions   admin.sovereignty.approve
GET    /admin/integrations             admin.integrations.view
POST   /admin/integrations/{id}/test   admin.integrations.manage
GET    /admin/system/*                 admin.system.view
```

No public API surface is added in this release. ADM-011's controls apply to these routes.

---

## 9. Role and permission matrix

Tier grants a default permission set. A permission may be added to a role but never above the granting user's own authority.

| Permission | SysAdmin | Admin | DomainOwner | Analyst | Contributor | Viewer | Auditor flag |
|---|---|---|---|---|---|---|---|
| `admin.platform.view` | full | read | - | - | - | - | - |
| `admin.organisation.view` | yes | yes | - | - | - | - | - |
| `admin.organisation.update` | yes | yes | - | - | - | - | - |
| `admin.users.view` | yes | yes | - | - | - | - | - |
| `admin.users.create` | yes | yes | - | - | - | - | - |
| `admin.users.disable` | yes | yes | - | - | - | - | - |
| `admin.roles.assign` | yes | yes | - | - | - | - | - |
| `admin.permissions.view` | yes | read | - | - | - | - | - |
| `admin.access_reviews.view` | yes | yes | - | - | - | - | read |
| `admin.security.view` | yes | - | - | - | - | - | read |
| `admin.security.update` | yes | - | - | - | - | - | - |
| `admin.secrets.view` | yes | - | - | - | - | - | - |
| `admin.audit.view` | yes | read | - | - | - | - | **read** |
| `admin.data_protection.view` | yes | read | - | - | - | - | read |
| `admin.sovereignty.view` | yes | read | - | - | - | - | read |
| `admin.sovereignty.approve` | yes | - | - | - | - | - | - |
| `admin.integrations.manage` | yes | - | - | - | - | - | - |
| `admin.system.view` | yes | read | - | - | - | - | - |

The Auditor column is the reason the capability is a flag and not a tier: it reads evidence across Security, Audit, Governance and Access Reviews while holding no operational authority anywhere.

**Business domains are not in this table.** They are the second dimension and are granted separately, per section 30.

---

## 10. Validation rules

| ID | Rule |
|---|---|
| `VAL-ORG-CODE-001` | Organisation code unique, immutable once dependencies exist |
| `VAL-ORG-DELETE-001` | An organisation cannot be deleted while dependencies exist |
| `VAL-BU-LOOP-001` | Business unit hierarchy must be acyclic |
| `VAL-BU-CODE-001` | Business unit code unique within the organisation |
| `VAL-BU-INACTIVE-001` | A disabled unit accepts no new assignment |
| `VAL-TEAM-BU-001` | A team belongs to exactly one business unit |
| `VAL-USER-EMAIL-001` | Email or UPN unique across the organisation |
| `VAL-USER-DISABLED-001` | A disabled user cannot authenticate |
| `VAL-USER-ELEVATE-001` | A user cannot assign authority above their own |
| `VAL-USER-LASTADMIN-001` | **The last active System Administrator cannot be removed, disabled or demoted** |
| `VAL-USER-WINDOW-001` | Access end must follow access start; an expired window denies sign-in |
| `VAL-ROLE-SYSTEM-001` | Built-in role codes cannot be renamed or deleted |
| `VAL-ROLE-ASSIGNED-001` | An assigned role cannot be deleted without remediation |
| `VAL-PERM-DENY-001` | An unknown permission key denies |
| `VAL-AUTH-TENANT-001` | The authenticating tenant must match the configured tenant |
| `VAL-AUTH-CLAIM-001` | Privileged roles are never auto-provisioned from claims without an approved mapping |
| `VAL-SESSION-REAUTH-001` | Critical actions require re-authentication where policy says so |
| `VAL-SECRET-NOVALUE-001` | **A secret value can never be persisted. References only** |
| `VAL-SOV-GEO-001` | Requested geography must be inside the approved profile or carry an active exception |
| `VAL-SOV-EXPIRY-001` | An expired exception blocks new dependent configuration |
| `VAL-INT-HTTPS-001` | Integration base URLs must be HTTPS outside approved local development |
| `VAL-INT-TIMEOUT-001` | Every outbound call carries a bounded timeout |

---

## 11. Audit event catalogue

Every event carries: id, UTC timestamp, actor id, actor type, action, module, resource type, resource id, outcome, redacted before and after summaries, IP where appropriate, correlation id, reason, environment.

| Action | Module |
|---|---|
| `auth.login.succeeded` / `auth.login.failed` / `auth.logout` | Security |
| `auth.policy.updated` / `session.policy.updated` | Security |
| `organisation.updated` | Identity |
| `business_unit.created` / `.updated` / `.disabled` | Identity |
| `team.created` / `.updated` / `.reassigned` | Identity |
| `user.invited` / `.created` / `.updated` / `.disabled` / `.unlocked` | Identity |
| `user.role.assigned` / `.removed` | Identity |
| `user.entitlement.granted` / `.revoked` | Identity |
| `role.created` / `.updated` / `.permissions_changed` | Identity |
| `access_review.opened` / `.decided` / `.applied` | Identity |
| `secret_reference.created` / `.updated` / `.rotated` | Security |
| `integration.created` / `.updated` / `.disabled` / `.tested` | Integration |
| `data_protection.updated` | Governance |
| `sovereignty.profile.updated` | Governance |
| `sovereignty.exception.requested` / `.approved` / `.rejected` / `.expired` | Governance |
| `system.setting.updated` / `feature_flag.toggled` | Platform |
| `privileged.action.denied` | Any |

A denied privileged action is audited. A trail containing only successes cannot show an attack that failed.

---

## 12. Integration architecture

```text
IntegrationRegistry -> Integration record (type, provider, base URL, auth type,
                       credential REFERENCE, timeout, retry policy, status)
                            |
                            v
        SecretResolver  ->  resolves the reference at point of use
                            never persisted, never logged, never sent to a browser
                            |
                            v
        IntegrationClient - bounded timeout, correlation ID, redacted logging,
                            structured failure, retry only where safe
                            |
                            v
        ConnectionTester -> sanitized result + remediation help reference
                            |
                            v
        CapabilityRegistry - stable / preview / guided-only, so a later release
                             branches on capability rather than on assumption
```

Entra (ADM-018) is the first registry entry. The OIDC sign-in flow already live becomes a consumer of that record rather than of raw config keys.

---

## 13. Security impact

- Authorization at three layers that must agree: navigation, route or controller, and service rule. Deny by default at each.
- Permission keys checked at the route boundary, not only in the rail.
- Re-authentication for role elevation, System Administrator assignment, sovereignty approval, secret-reference change and integration credential change.
- Rate limiting on sensitive endpoints; correlation IDs throughout; bounded payloads; secure headers; structured errors carrying no secret.
- The last-System-Administrator invariant is enforced in a policy, not in a controller, so no future route can bypass it.
- **No secret value enters a table, a log, an error, a test fixture or a browser.**

## 14. Data protection impact

- Classification: Internal for configuration, audit metadata and entitlements. Confidential for account identity. **No customer business data enters this release.**
- Retention configurable per ADM-014. Seven years is the project default for audit, recorded as policy rather than a constant.
- Audit before and after summaries are redacted and hashed, not payload copies.
- Production payload logging off by default.

## 15. Sovereignty impact

- ADM-015 profile with all four cross-geo flags defaulting false.
- `VAL-SOV-GEO-001` server-side and reusable, returning PASS, WARNING, EXCEPTION_REQUIRED or BLOCKED.
- Unset geographies return BLOCKED for production activation. The absence of a value is a refusal, never a pass.
- ADM-016 exceptions are explicit, approved, audited and expiring.
- **Open item:** the control-plane hosting geography is still unconfirmed and must be recorded before go-live.

---

## 16. Test strategy

| Layer | Coverage |
|---|---|
| Tenancy | Two organisations; cross-organisation read, update and delete denied; fails closed with no context |
| Authorization | Every permission key against every tier; unknown key denies; route and rail agree for all six tiers |
| Last administrator | Removal, disable and demotion of the final System Administrator all refused, by three separate paths |
| Elevation | A user cannot grant authority above their own |
| Domain separation | A System Administrator without entitlement is refused Finance and People |
| Audit | Every mandatory event fires; no update or delete path exists; a denied action is recorded |
| Secrets | A value passed through create, update, log, error and connection-test paths never appears in the database or the output |
| Sovereignty | All four verdicts; cross-geo denied by default; an expired exception blocks |
| Integration | Timeout bounded; a failed test returns sanitized text with no header, token or payload |
| Screens | Success, empty, loading, validation, permission-denied, error and small-screen |
| Regression | The 86 existing tests keep passing |

Rendered in a browser in both themes and at 390px, as with the work so far.

---

## 17. Migration and rollback

Every migration reversible and exercised `up`, `down`, `up` before it is proposed for production. All changes additive; nothing existing is dropped or renamed. Deployment rollback is a revert commit on `main`.

**Production migrations remain a separately approved action.** The last one was verified on MySQL 8.0.46 and this plan does not authorise the next.

---

## 18. Dependencies

- Gate 2 depends on Gate 1's organisation context and audit writer.
- Gates 3, 4, 5 depend on Gate 2's permission enforcement.
- Gate 5 is what the Fabric Environment Release consumes.
- Gate 6 depends on Gate 1's status model.
- Nothing in this release depends on Microsoft being reachable. Entra configuration is recorded and testable; a failing test is a result, not a blocker.

---

## 19. Unresolved decisions

| # | Question | Blocks |
|---|---|---|
| U1 | Which secret provider backs ADM-012 in production - cPanel environment, Azure Key Vault, or another? The abstraction ships either way; the concrete provider does not | Gate 3 completion, not its start |
| U2 | Control-plane hosting geography, still unconfirmed | Go-live, not this release |
| U3 | Approved storage and processing geographies. The mechanism ships with them unset and blocking | Fabric Environment Release |
| U4 | Whether Access Reviews need an approval workflow beyond a single reviewer decision | ADM-008 detail, raised when Gate 2 is reached |
| U5 | Session revocation scope - current device, all devices, or administrator-initiated for another user | ADM-010 detail |

---

## 20. Approval

Nothing in section 4 has been implemented. On approval I will build **R1.1 (Gate 1)**, open its pull request with evidence against that gate, and stop for review before R1.2.
