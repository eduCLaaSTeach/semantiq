# SemantIQ v2 — Phase 1: System Administration & Security Foundation

**Document purpose:** Execution authority for Phase 1 of the SemantIQ v2 greenfield build.

**Parent baseline:** `SemantIQ_v2.2_Ground_Zero_Architecture_Reset_Three_Phase_Blueprint`

**Delivery principle:** Build **one menu or application-entry unit at a time**. Plan it, design it, implement it, test it, verify it, obtain approval, then unlock the next unit.

---

## 1. Fixed Platform Constraints

The following platform foundation is already established and is **not a redesign topic** for Phase 1:

- Backend: Laravel 13 / PHP 8.5
- Frontend: React 19
- Database: MySQL on cPanel
- Deployment: GitHub Actions → cPanel over SSH/rsync
- Architecture: modular monolith
- Tenancy: single-tenant today, with multi-tenant-ready boundaries
- Shared UI/UX: approved CLaaS2SaaS design standard

These are fixed constraints unless the product owner explicitly changes them.

---

## 2. Greenfield Rule

SemantIQ v2 is a clean-slate application.

### Reuse allowed
Only the approved CLaaS2SaaS UI/UX design system may be reused:
- shell/layout
- typography
- colour tokens
- icons
- responsive rules
- accessibility rules
- page archetypes
- branding assets

### Reuse prohibited
Do not copy or use SemantIQ v1 as an implementation template for:
- application code
- database schema
- migrations
- permissions
- authorization code
- privacy/security workflows
- business logic
- API contracts
- tests
- menu logic
- application architecture decisions

Historical v1 material may be consulted only as lessons/evidence, never as a source implementation.

---

## 3. Phase 1 Business Outcome

Phase 1 creates the secure administration foundation required before enterprise business data is onboarded.

At Phase 1 completion:
- every interactive user is authenticated through an approved identity provider;
- Microsoft SSO works end to end from the ground-zero Login page;
- a fresh installation has a safe first-System-Administrator bootstrap path;
- organisation, teams and hierarchy are defined;
- users receive deterministic access through **Role + Domain + Scope + Sensitivity**;
- unknown or conflicting access fails closed;
- System Administrators do not automatically receive business-domain data;
- security is strong by default and does not require administrators to understand low-level security controls;
- access changes, security events and privileged actions produce evidence.

---

# 4. Mandatory Incremental Delivery Rule

Claude must **not implement Phase 1 as one large release**.

Only one delivery unit may be active at a time.

For every unit:

1. **PLAN**
   - confirm scope;
   - list requirements;
   - identify data/entities;
   - identify security rules;
   - identify screens/states;
   - define acceptance criteria;
   - define negative tests;
   - identify files likely to change;
   - identify migration/schema impact;
   - stop for approval.

2. **DESIGN**
   - define screen flow;
   - define data model/API contracts;
   - define authorization;
   - define error/refusal states;
   - define test plan;
   - use approved CLaaS2SaaS UI/UX standard;
   - stop for approval.

3. **EXECUTE**
   - implement **only the approved unit**;
   - do not prebuild future menus, future tables or future services unless the current unit has a proven dependency and it is explicitly approved.

4. **TEST**
   - automated functional tests;
   - authorization/security tests;
   - validation/error tests;
   - negative/refusal tests;
   - responsive/accessibility checks where relevant.

5. **VERIFY**
   - manual browser verification;
   - database/schema verification when applicable;
   - confirm no protected data appears in denied cases;
   - record real test evidence.

6. **ACCEPT**
   - produce a verification report;
   - obtain explicit product-owner acceptance;
   - only then unlock the next unit.

### Hard rule
A green CI run does **not** unlock the next unit by itself.

---

# 5. Phase 1 Delivery Units

## P1-00 — Application Entry, Login & First-Run Bootstrap

This is the true ground-zero starting point and is **not a sidebar menu item**.

### Build
- branded Login page;
- **Sign in with Microsoft** as the primary Release 1 action;
- approved future IdP adapter boundary;
- Microsoft Entra ID authentication flow;
- callback/issuer/tenant validation;
- active-user mapping;
- no self-registration by default;
- secure application session;
- logout;
- session-expired state;
- access-not-assigned state;
- inactive-account state;
- access-denied state;
- signed-out state;
- controlled first-System-Administrator bootstrap.

### First-run flow
```text
Fresh deployment
    ↓
Login / bootstrap entry
    ↓
Establish organisation / tenant trust
    ↓
Verify first System Administrator via Microsoft SSO
    ↓
Create trusted initial admin identity
    ↓
Disable/restrict reusable bootstrap path
    ↓
Continue normal administration
```

### Must prove
- unauthenticated users cannot receive protected shell/menu/business metadata;
- Microsoft login succeeds only through trusted callback validation;
- successful Microsoft authentication alone does not grant SemantIQ business access;
- unknown user fails closed;
- inactive user fails closed;
- user with no assignment fails closed;
- bootstrap cannot remain an unrestricted reusable setup mechanism;
- no manual MySQL row insertion is required as the normal first-admin process.

### Exit
P1-00 must be fully verified and accepted before any authenticated administration menu is implemented.

---

## P1-01 — Organisation

### Menu
`System Administration → Organisation`

### Subscreens
- Company Profile
- Business Units
- Departments
- Teams
- Management Hierarchy
- Legal Entities

### Purpose
Create the organisational structure from which access scope is later derived.

### Key rules
- organisation context is explicit;
- hierarchy is authoritative for manager/team scope;
- no business-domain access is granted here;
- deleting or restructuring hierarchy must not silently broaden access.

### Minimum tests
- create/update organisation data;
- team membership validation;
- management relationship validation;
- invalid hierarchy refusal;
- organisation boundary checks;
- audit for privileged structural changes.

### Exit
Organisation hierarchy is stable enough to support users and access scopes.

---

## P1-02 — Identity & SSO Administration

### Menu
`System Administration → Identity & SSO`

### Subscreens
- Microsoft Entra ID
- Other Approved IdPs
- Login Experience
- SSO Health
- Session Policy

### Purpose
Administer and monitor the identity trust that was first established in P1-00.

### Important boundary
Do **not** rebuild the Login flow. P1-00 owns the application-entry implementation. This menu manages configuration, health and supportability.

### Minimum tests
- current provider status;
- tenant/provider mismatch refusal;
- SSO health checks;
- session policy validation;
- secrets never rendered to browser/log;
- disabling an IdP does not create a bypass.

### Exit
Identity configuration is supportable and observable without exposing low-level secrets.

---

## P1-03 — Users & Groups

### Menu
`System Administration → Users & Groups`

### Subscreens
- Users
- Groups
- Invitations / Directory Sync
- User Lifecycle

### Purpose
Onboard identities and maintain active/inactive membership.

### Key rules
- imported users receive **no business access by default**;
- status and identity linkage are separate from authorization;
- deactivated users lose effective access;
- manager/team relationship is visible but does not override explicit policy.

### Minimum tests
- add/import user;
- group membership;
- activate/deactivate;
- duplicate identity handling;
- unknown identity mapping;
- deactivated-user access refusal;
- audit of lifecycle changes.

### Exit
Users exist safely without accidental business-data access.

---

## P1-04 — Business Domains

### Menu
`System Administration → Business Domains`

### Baseline domains
- Executive
- Sales
- Finance
- People
- Operations
- Customer
- Learning
- Custom Domains

### Purpose
Define which business intelligence domains exist, their owners and default access expectations.

### Key rules
- a domain existing does not grant access to it;
- each domain has an accountable owner;
- domain settings use business language;
- highly sensitive domain data may require stricter sensitivity controls.

### Minimum tests
- enable/disable domain;
- assign owner;
- custom domain validation;
- no automatic entitlement from domain creation;
- audit domain changes.

### Exit
Domains are ready to be assigned through Roles & Access.

---

## P1-05 — Roles & Access

### Menu
`System Administration → Roles & Access`

### Subscreens
- Role Assignments
- Domain Entitlements
- Scope Assignments
- Sensitivity Ceiling
- Access Simulator

### Common access model
```text
Identity
+ Platform Role
+ Business Domain
+ Scope
+ Sensitivity
+ Organisation / Team Relationship
= Effective Access
```

### Baseline roles
- System Administrator
- Organisation Administrator
- Executive
- Domain Owner / Director
- Manager
- Business User
- Auditor

### Baseline scopes
- Own
- Team
- Business Unit
- Domain
- Organisation

### Baseline sensitivity
- Standard
- Confidential
- Restricted

### Critical rules
- System Administrator does not automatically receive Finance, People, Sales or other business data;
- Sales + Own means own permitted Sales records;
- Sales Manager + Team means assigned team records only;
- unknown/conflicting combinations fail closed;
- the Access Simulator previews effective access before publishing a change;
- backend authorization is mandatory; UI hiding is never sufficient.

### Minimum tests
Matrix tests must cover at least:
- Sales user cannot access Finance;
- Finance user cannot access People;
- salesperson cannot view another salesperson's individual data with Own scope;
- manager can view assigned team but not unrelated team;
- Executive sees approved enterprise domains but restricted raw fields remain protected;
- System Admin can administer platform without business-data entitlement;
- denied API request returns no protected payload;
- permission change creates audit evidence.

### Exit
Effective access engine is proven before Phase 2 data onboarding.

---

## P1-06 — Security Status

### Menu
`System Administration → Security Status`

### Subscreens
- Secure Baseline
- Privileged Access Health
- Exceptions
- Security Events

### Purpose
Show a clear security posture without forcing administrators to configure dozens of technical switches.

### Secure-by-default principle
Controls such as authentication enforcement, backend authorization, audit, encryption assumptions, least privilege and deny-by-default are platform controls—not everyday admin choices.

### Minimum tests
- posture calculations are accurate;
- unresolved exceptions visible;
- privileged-risk indicators accurate;
- no ability to disable mandatory baseline controls through ordinary admin UI;
- security status contains no secrets.

### Exit
Administrator can understand posture without needing security-engineer expertise.

---

## P1-07 — Access Reviews

### Menu
`System Administration → Access Reviews`

### Subscreens
- Privileged Reviews
- Domain Reviews
- Overdue Reviews

### Purpose
Periodic confirmation/removal of privileged and sensitive-domain access.

### Minimum tests
- reviewer sees only reviews they may perform;
- overdue tracking;
- approve/retain/revoke paths;
- revocation updates effective access;
- review evidence is auditable.

### Exit
Sensitive access has an accountable review lifecycle.

---

## P1-08 — Audit

### Menu
`System Administration → Audit`

### Categories
- User Access
- Admin Changes
- Security Events
- Configuration Changes

### Purpose
Searchable tamper-resistant evidence appropriate to the viewer.

### Key rules
- audit access is permission controlled;
- sensitive/network details are independently protected;
- events record actor, action, target, outcome and timestamp;
- ordinary business users do not receive audit administration.

### Minimum tests
- successful/failed login evidence;
- privileged change evidence;
- denied action evidence where policy requires;
- actor identity correctness;
- restricted audit fields remain unavailable to unauthorized viewers.

### Exit
Core Phase 1 security and administrative activity is evidenced.

---

## P1-09 — System Health

### Menu
`System Administration → System Health`

### Areas
- Application
- Integrations
- Jobs
- Connections
- Service Health

### Purpose
Operational visibility without exposing business data.

### Minimum tests
- health status from real checks, not hard-coded success;
- error states;
- no secret exposure;
- no unrestricted business payload in diagnostics;
- permission boundary for operational details.

### Exit
Platform support can identify operational failures safely.

---

## P1-10 — Administration Home

### Menu
`System Administration → Administration Home`

### Why it is implemented last
It is a roll-up screen. Building it after its source menus avoids placeholder logic and duplicated implementations.

### Contents
- organisation readiness;
- users/groups status;
- domains readiness;
- security posture;
- open exceptions;
- access reviews;
- system health;
- action queue.

### Minimum tests
- every summary value traces to a real source;
- permission-aware cards;
- empty/unconfigured states;
- no hidden business-domain data exposed through summaries.

### Exit
Administrator receives one accurate operational overview.

---

# 6. Phase 1 Phase-Level Acceptance

Phase 1 is accepted only when every unit P1-00 through P1-10 has been individually accepted and all cross-unit tests pass.

Required end-state proof:
- Login + Microsoft SSO works end-to-end;
- unknown/unassigned/inactive/session-expired cases fail closed;
- first-admin bootstrap is secure and restricted after use;
- organisation/team hierarchy works;
- role/domain/scope/sensitivity matrix works;
- salesperson/manager/executive/System Admin isolation scenarios pass;
- baseline security cannot be casually disabled;
- privileged changes are auditable;
- access review works;
- diagnostics do not expose business data or secrets;
- no critical/high security issues remain before Phase 2.

---

# 7. Claude Execution Contract

Claude must:
- read this file before Phase 1 work;
- read the shared CLaaS2SaaS UI/UX standard;
- work on **only the current approved P1 unit**;
- create a unit-specific plan and stop for approval;
- create the design and stop for approval;
- implement only after design approval;
- test and verify before requesting acceptance;
- not begin the next unit without explicit acceptance;
- not copy v1 implementation;
- not pre-create future phase tables/services/routes just to “save time”;
- clearly report any shared dependency needed by more than one menu and obtain approval before introducing it;
- preserve the fixed Laravel/React/MySQL/cPanel platform constraints.

Recommended repository documentation pattern:

```text
doc/v2/phase-1/
  P1-00-LOGIN-BOOTSTRAP-PLAN.md
  P1-00-LOGIN-BOOTSTRAP-DESIGN.md
  P1-00-LOGIN-BOOTSTRAP-VERIFICATION.md

  P1-01-ORGANISATION-PLAN.md
  ...
```

Each unit is complete only after its verification document contains real evidence and the product owner accepts it.
