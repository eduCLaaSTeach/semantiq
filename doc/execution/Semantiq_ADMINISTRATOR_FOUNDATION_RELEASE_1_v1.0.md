# SemantIQ Administrator Foundation Release 1

Version: 1.0  
Status: Build Specification  
Purpose: First executable administrator release for Claude Code implementation

## 1. Release Objective

Administrator Foundation Release 1 establishes the common platform capabilities that every later SemantIQ feature depends on.

This release must be completed, tested, verified, and approved before Microsoft Fabric provisioning, source onboarding, semantic intelligence, AI/Data Agent features, or business-user intelligence modules are implemented.

The release provides:

- Platform administration shell
- Organisation hierarchy
- User lifecycle management
- Role and permission management
- Security policy configuration
- Audit logging
- Data protection policy
- Data sovereignty policy
- Integration framework
- Microsoft Entra integration foundation
- System configuration
- Platform diagnostics, health, jobs, and scheduler visibility

This release does NOT yet build Fabric workspaces, Lakehouse, Warehouse, pipelines, semantic models, Data Agents, or business intelligence domains. Those later modules consume the controls created here.

## 2. Administrator Navigation

```text
Administration
|
|-- Platform Overview
|-- Organisation
|   |-- Organisation Profile
|   |-- Business Units
|   `-- Teams
|-- Users & Access
|   |-- Users
|   |-- Roles
|   |-- Permissions
|   `-- Access Reviews
|-- Security
|   |-- Authentication Policy
|   |-- Session Policy
|   |-- API Security
|   `-- Secret References
|-- Audit
|   |-- User Activity
|   |-- Administrative Changes
|   |-- Security Changes
|   `-- Configuration Changes
|-- Data Protection
|-- Data Sovereignty
|-- Integrations
|   |-- Integration Registry
|   |-- Microsoft Entra
|   |-- API Configuration
|   |-- Credential References
|   `-- Connection Tests
`-- System
    |-- Application Health
    |-- Background Jobs
    |-- Scheduler
    |-- Diagnostics
    `-- Configuration
```

## 3. Common Implementation Rules

Every administrative record must include an immutable internal ID, lifecycle status, created/updated timestamps, created/updated by references, and a change/version indicator where concurrency matters.

Every configuration change affecting identity, access, security, data protection, sovereignty, integrations, or infrastructure must create an audit event.

Secrets must never be stored in normal application tables or logs. Only a reference to the approved secret location may be persisted.

All outbound integrations require a bounded timeout, structured failure handling, correlation ID, redacted logging, health-test capability, and retry only where the operation is safe to retry.

# 4. Feature ADM-001 - Platform Overview

## Purpose
Provide the System Administrator with one screen showing whether the SemantIQ platform is operational and whether mandatory foundation configuration is complete.

## What it must do
Display application status, version, environment, database connectivity, queue status, scheduler status, cache status if used, Microsoft Entra integration status, data-protection profile status, sovereignty profile status, unresolved configuration warnings, recent critical failures, and pending administrator actions.

## Key data points

| Data Point | Type | Example |
|---|---|---|
| Environment | enum | Production |
| Application Version | string | 1.0.0 |
| Application Status | enum | Healthy / Warning / Critical |
| Database Status | enum | Connected / Failed |
| Queue Status | enum | Running / Stopped / Unknown |
| Scheduler Last Run | datetime | 2026-08-23 17:00 |
| Entra Status | enum | Not Configured / Connected / Error |
| Data Protection Profile | enum | Incomplete / Complete |
| Sovereignty Profile | enum | Incomplete / Complete |
| Critical Alerts | integer | 2 |

## Main actions
Run health check, view health details, open failed integration, open incomplete configuration, and view recent audit events.

## Permissions
System Administrator: full. Administrator: read. Other roles: no access.

## Acceptance criteria
The dashboard must show real status values, identify failed dependencies, link warnings to the correct setup screen, and never expose credentials.

# 5. Feature ADM-002 - Organisation Profile

## Purpose
Define the legal/business organisation that owns this SemantIQ instance.

## Key data points

| Field | Required | Notes |
|---|---:|---|
| Organisation Name | Yes | Display/legal name |
| Organisation Code | Yes | Unique internal short code |
| Legal Name | No | If different |
| Registration Number | No | Customer supplied |
| Primary Country | Yes | Used for policy context |
| Default Time Zone | Yes | IANA time zone |
| Default Currency | Yes | ISO currency |
| Default Language | Yes | Locale |
| Primary Domain | No | e.g. example.com |
| Data Owner | No | Responsible role/person |
| Privacy Contact | No | Contact reference |
| Security Contact | No | Contact reference |
| Status | Yes | Active / Suspended |

## Validation
Organisation code must be unique. Currency and time zone must use valid standards. The organisation cannot be deleted while dependencies exist. Production identity changes must be audited.

# 6. Feature ADM-003 - Business Units

## Purpose
Model major organisational divisions for role scope, reporting, later RLS, and intelligence entitlements.

## Key data points
Business Unit Name, Code, Parent Business Unit, Manager/Owner, Cost Centre, Country/Region, Status, Effective From, Effective To.

## Rules
No hierarchy loops. Codes unique within the organisation. Disabled units cannot receive new assignments. Historical assignments remain auditable.

## Required functions
Create, edit, activate/deactivate, view hierarchy, assign owner, view assigned users, and view child teams.

# 7. Feature ADM-004 - Teams

## Purpose
Define working teams beneath Business Units.

## Key data points
Team Name, Team Code, Business Unit, Team Lead, Description, Status.

## Rules
A team belongs to one Business Unit. Team codes are unique. Inactive teams cannot receive new users. Reassignment is audited.

# 8. Feature ADM-005 - User Registry

## Purpose
Maintain every SemantIQ user identity and its organisational/security context.

## Key data points

| Field | Required | Notes |
|---|---:|---|
| Display Name | Yes | Human-readable |
| Email / UPN | Yes | Unique login identity |
| External Identity ID | No | Entra object ID after SSO |
| Employee / Reference ID | No | Customer supplied |
| User Type | Yes | Internal / External / Service |
| Business Unit | No | Scope assignment |
| Team | No | Optional |
| Primary Role | Yes | Security role |
| Additional Roles | No | Optional controlled extension |
| Domain Entitlements | No | Sales, Finance, People, etc. |
| Status | Yes | Invited / Active / Disabled / Locked |
| Last Login | No | System maintained |
| Authentication Source | Yes | Local Bootstrap / Entra |
| Access Start | No | Optional |
| Access End | No | Optional |

## Functions
Create/invite, edit profile, assign BU/team, assign/remove roles, assign/remove domain entitlements, disable, unlock, view access history, view audit history.

## Critical rules
Email/UPN is unique. Disabled users cannot authenticate. A user cannot assign authority above their own. The final active System Administrator cannot be removed. Platform administration authority does not automatically grant Finance, People, Sales, or other business-data entitlement.

# 9. Feature ADM-006 - Roles

## Purpose
Define reusable application authority profiles.

## Initial system roles
System Administrator, Administrator, Collaborator, Contributor, Viewer.

Recommended role codes:

```text
system_admin
admin
collaborator
contributor
viewer
```

## Key data points
Role Name, Role Code, Role Type, Description, Is System Role, Status.

## Rules
Built-in role codes cannot be renamed. Role/permission definitions should support versioning. Assigned roles cannot be deleted without remediation.

# 10. Feature ADM-007 - Permissions

## Purpose
Provide granular authorization; menu visibility alone is never treated as authorization.

Recommended permission key format:

```text
<module>.<resource>.<action>
```

Examples:

```text
admin.users.view
admin.users.create
admin.users.update
admin.users.disable
admin.roles.assign
admin.audit.view
admin.security.update
admin.integrations.manage
admin.sovereignty.approve
```

## Key data points
Permission Key, Module, Resource, Action, Description, Risk Level, Requires Audit.

## Enforcement
Authorization must exist in navigation, route/controller/API boundaries, and business/service rules where required.

# 11. Feature ADM-008 - Access Reviews

## Purpose
Allow periodic verification that user access remains appropriate.

## Key data points
Review Name, Scope, Reviewer, Due Date, User, Current Roles, Domain Entitlements, Decision, Decision By, Decision Date, Comment.

## Workflow
Create Review -> Generate Review Items -> Review -> Retain/Modify/Revoke -> Apply Approved Changes -> Audit.

# 12. Feature ADM-009 - Authentication Policy

## Purpose
Control how users authenticate to SemantIQ.

## Release 1 scope
Support a bootstrap/local System Administrator where required, Microsoft Entra SSO configuration foundation, and the ability to require Entra for normal users.

## Key settings
Authentication Mode, Allow Local Admin, Require SSO for Business Users, Auto-create Users, Allowed Tenant ID, Allowed Email Domains, Failed Login Threshold, Lock Duration.

## Rules
Do not auto-provision privileged roles from claims without approved mapping. Validate tenant ID. Local bootstrap should become break-glass after Entra is operational. Failed logins are audited without recording passwords/tokens.

# 13. Feature ADM-010 - Session Policy

## Purpose
Control active authenticated sessions.

## Key settings
Idle Timeout, Maximum Session Duration, Re-authentication for Critical Action, Concurrent Session Policy, Remember Me policy, Session Revocation.

## Critical actions requiring optional/mandatory re-authentication
Role elevation, System Administrator assignment, sovereignty exception approval, secret-reference change, integration credential change, production security-policy change.

# 14. Feature ADM-011 - API Security

## Purpose
Set security controls for SemantIQ APIs before integrations are built.

## Required controls
Authentication by default, authorization per endpoint, CSRF where applicable, input validation, rate limiting for sensitive endpoints, correlation IDs, bounded payload sizes, structured errors, secure headers, and no secrets in errors/logs.

# 15. Feature ADM-012 - Secret References

## Purpose
Record where secrets are managed without storing the secret itself.

## Key data points
Reference Name, Secret Type, Provider, Reference Identifier, Purpose, Environment, Owner, Expiry Date, Rotation Due, Status.

## Never store
Secret values, access tokens, refresh tokens, private keys, passwords, or connection-string passwords.

# 16. Feature ADM-013 - Audit Log

## Purpose
Provide authoritative evidence of important activity and configuration change.

## Audit event structure
Event ID, UTC Timestamp, Actor User ID, Actor Type, Action, Module, Resource Type, Resource ID, Outcome, redacted Before Summary, redacted After Summary, IP Address where appropriate, User Agent where appropriate, Correlation ID, Reason, Environment.

## Mandatory audit events
Login success/failure, logout, user create/disable, role assignment/removal, permission changes, security-policy changes, integration configuration changes, connection tests, sovereignty-policy changes, sovereignty exceptions, retention-policy changes, admin configuration changes, and failed privileged actions.

## Rules
Audit entries are not editable in the normal UI. Secrets/tokens are redacted. Application users cannot casually delete audit history.

# 17. Feature ADM-014 - Data Protection Profile

## Purpose
Define organisation-level data-handling rules before customer data is ingested.

## Key data points
Policy Name, Default Classification, Operational Data Retention, Audit Retention, Sensitive Data Handling, Export Policy, Masking Required, Personal Data Allowed, Special Category Handling, Policy Owner, Effective Date, Status.

Suggested classifications:

```text
Public
Internal
Confidential
Restricted
```

Retention must be configurable. Seven years may be the current project default but must not be hard-coded as a universal legal rule.

# 18. Feature ADM-015 - Data Sovereignty Profile

## Purpose
Prevent SemantIQ from provisioning or using services in unapproved geographies.

## Key data points
Policy Name, Primary Data Geography, Approved Storage Regions, Approved Processing Regions, Approved AI Processing Regions, Cross-Geo Processing Allowed, Cross-Geo Storage Allowed, AI Cross-Geo Allowed, Conversation History Cross-Geo Allowed, Exception Required, Policy Owner, Approval Status.

## Safe defaults

```text
Cross-Geo Processing Allowed = false
Cross-Geo Storage Allowed = false
AI Cross-Geo Allowed = false
Conversation History Cross-Geo Allowed = false
```

Later Fabric provisioning must compare requested service geography with this approved profile.

# 19. Feature ADM-016 - Sovereignty Exceptions

## Purpose
Provide controlled exception handling when a required service cannot satisfy the normal sovereignty profile.

## Key data points
Exception ID, Requested Capability, Requested Geography, Data Classification, Business Reason, Risk Description, Requested By, Requested Date, Approver, Decision, Expiry Date, Review Date, Evidence Reference, Status.

## Rules
No silent exceptions. Approval must be audited. Expired exceptions block new dependent configuration unless renewed.

# 20. Feature ADM-017 - Integration Registry

## Purpose
Create a reusable integration abstraction for Microsoft Entra, Graph, Fabric, Power BI, Copilot Studio, Business Central, SQL, SharePoint, and external APIs.

## Key data points
Integration Name, Integration Type, Provider, Environment, Base URL, Authentication Type, Credential Reference, Tenant ID, Status, Last Test, Last Test Result, Owner, Timeout, Retry Policy.

## Functions
Create, configure, disable, test connection, view test history, view sanitized error, view audit history.

# 21. Feature ADM-018 - Microsoft Entra Integration

## Purpose
Configure the Microsoft identity tenant used by SemantIQ and prepare later Fabric automation.

## Key data points
Tenant ID, Tenant Primary Domain, Client/Application ID, Authentication Method, Credential Reference, Redirect URI, Required Scopes, Admin Consent Status, Last Validation, Connection Status.

## Functions
Enter tenant/app details, validate format, test OpenID/OAuth metadata, validate tenant, test application authentication where supported, show consent status, show required permissions, and link contextual Help.

## Help must explain
App Registration, supported tenancy decision, redirect URI, API permissions, admin consent, secret/certificate guidance, credential storage, and connection testing.

## Release boundary
Full Fabric permissions/provisioning comes in the next release.

# 22. Feature ADM-019 - API Configuration

## Purpose
Provide controlled reusable external API client configuration.

## Key data points
API Name, Base URL, Authentication Type, Credential Reference, Default Timeout, Retry Count, Retry Backoff, Rate-Limit Handling, Enabled.

## Rules
HTTPS required outside approved local development. Timeout mandatory. Credentials never inline. Customer response payloads are not logged by default.

# 23. Feature ADM-020 - Connection Test Centre

## Purpose
Provide a consistent frontend experience to verify integrations.

## Key data points
Integration, Test Type, Started At, Completed At, Result, HTTP Status where useful, Correlation ID, Error Code, Error Summary, Remediation Help reference.

## Security
Never display token, password, authorization header, private key, or full confidential response payload.

# 24. Feature ADM-021 - System Configuration

## Purpose
Centralise non-secret application configuration.

## Example settings
Application Display Name, Default Time Zone, Default Locale, Feature Flags, Notification Defaults, Pagination Default, Support Contact, Environment Label, Maintenance Mode.

Each configuration setting should carry key, category, typed value, default, validation, scope, editable role, audit requirement, approval requirement, and sensitive flag.

# 25. Feature ADM-022 - Background Jobs

## Purpose
Provide visibility of asynchronous application work.

## Key data points
Job ID, Job Type, Queue, Status, Attempt, Created At, Started At, Completed At, Correlation ID, Failure Code, Failure Summary.

## Functions
View queue health/history/failed jobs. Retry only retry-safe jobs. Open diagnostics. Do not expose arbitrary job execution from the UI.

# 26. Feature ADM-023 - Scheduler

## Purpose
Show whether recurring platform activities are running.

## Key data points
Task Name, Schedule, Last Run, Last Result, Next Expected Run, Enabled, Duration.

Initial scheduled activities may include session cleanup, access-review reminders, expired secret-reference detection, sovereignty exception expiry checks, and health snapshots.

# 27. Feature ADM-024 - Diagnostics

## Purpose
Provide safe troubleshooting without exposing confidential data.

## Capabilities
Application version, runtime version, database connectivity, queue connectivity, scheduler status, environment name, configured integrations/status, recent error correlation IDs, configuration completeness.

## Never expose
`.env` contents, passwords, API credentials, tokens, secret values, or production row data.

# 28. Feature ADM-025 - Administrator Help Framework

## Purpose
Make SemantIQ self-guided so administrators do not need to understand every underlying Microsoft product.

Each Help topic should include Purpose, Prerequisites, Required Permissions, Information to Collect, SemantIQ Steps, Microsoft/manual steps if unavoidable, Validation, Expected Result, Security Considerations, Data Protection Considerations, Sovereignty Considerations, Troubleshooting, and official reference links where appropriate.

# 29. Minimum Logical Data Model

```text
organisations
business_units
teams
users
roles
permissions
role_permissions
user_roles
user_domain_entitlements
access_reviews
access_review_items
security_policies
session_policies
audit_events
data_protection_profiles
data_sovereignty_profiles
sovereignty_exceptions
integrations
integration_test_runs
secret_references
api_client_configs
system_settings
feature_flags
```

Final Laravel migration/table names must follow the actual repository conventions and be approved in the implementation plan before generation.

# 30. Domain Entitlement Concept

Role and business-data entitlement are separate.

Example:

```text
User: John
Role: Administrator
Domain Entitlements:
- Sales
- Customer
Not entitled:
- Finance
- People
```

Technical example:

```text
User: Technical Platform Admin
Role: System Administrator
Domain Entitlements:
- None
Result:
Can configure platform.
Cannot read Finance or People business information.
```

Suggested later domain codes:

```text
executive
sales
finance
people
operations
customer
learning
```

# 31. Recommended Status Values

User: invited, active, disabled, locked, expired.  
Integration: draft, configured, connected, warning, error, disabled.  
Policy: draft, approved, superseded, disabled.  
Access Review: draft, open, completed, cancelled.  
Exception: pending, approved, rejected, expired, revoked.

# 32. Administrator Release 1 Dependency Gates

## Gate 1 - Platform
Application health, configuration framework, error handling, and audit infrastructure available.

## Gate 2 - Identity and Access
System Administrator exists, User Registry works, roles work, permissions are enforced server-side, domain entitlement model exists, and the last System Administrator cannot be removed.

## Gate 3 - Security
Authentication policy exists, session policy exists, API baseline applied, and secrets are references only.

## Gate 4 - Governance
Data Protection Profile approved, Data Sovereignty Profile approved, cross-geo defaults disabled, and exception workflow available.

## Gate 5 - Integration
Integration Registry works, Entra configuration exists, connection testing works, errors are sanitized, and no credentials appear in logs.

## Gate 6 - Operations
Background jobs visible, scheduler visible, diagnostics safe, audit events queryable.

## Gate 7 - Verification
Unit, authorization, validation, audit, failure-path, and secret-leakage tests pass; user acceptance is completed.

Only after explicit user confirmation should the project proceed to the Fabric Environment Release.

# 33. What Claude Code Must Produce Before Coding

Before implementation Claude Code must inspect the actual repository and create:

`doc/execution/ADMIN-FOUNDATION-RELEASE-1-PLAN.md`

The plan must contain current repository assessment, existing reusable features, proposed Laravel modules/classes, proposed React screens/components, proposed migrations, proposed routes/APIs, role/permission matrix, validation rules, audit event catalogue, integration architecture, security impact, data-protection impact, sovereignty impact, test strategy, migration/rollback plan, dependencies, and unresolved decisions.

Claude must stop and wait for user approval before implementation.

# 34. Definition of Done

A feature is complete only when UI/API/business logic are implemented as required, authorization is enforced, validation exists, audit events exist where required, errors are handled, secrets are protected, tests are implemented and run, context registers are updated, Help content exists where required, and verification evidence is produced.

Code alone is not completion.
