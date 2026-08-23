# Phase 02 - Fabric Readiness, Capacity and Workspace Provisioning

**Reference ID:** P02-FAB
**SRS sections:** 9, 16.2-16.3
**Completion phrase:** `CONFIRM PHASE 02 COMPLETE`

> **Execution gate:** Do not start a later phase until this phase has passed verification and the user has explicitly confirmed completion.

## Objective

Assess customer Fabric readiness, detect blockers, select or provision capacity, create the DEV/TEST/PROD topology, assign capacity and manage approved workspace access with least privilege.

## Preconditions

- Phase 01 is `CONFIRMED` in `IMPLEMENTATION_STATUS.md`.
- Current branch/tests are in a known passing state.
- User approves the phase implementation plan before material code changes.

## Implementation activities

1. Run Fabric Readiness Assessment using identity, token, capacity, tenant-setting, role, topology, region, AI/licensing and network checks.
2. Implement Tenant Settings screen that distinguishes detected state, required state, effective scope and automation mode.
3. Keep preview tenant-setting update behind a disabled-by-default feature flag; guided manual + Re-check is the default.
4. List accessible Fabric capacities and allow selection of an active capacity.
5. Implement optional Fabric capacity provisioning through Azure Resource Manager only when the customer grants required Azure RBAC.
6. Create DEV, TEST and PROD workspaces from configurable naming templates and make creation idempotent.
7. Assign each workspace to the selected capacity and poll long-running operation status where applicable.
8. Implement workspace role management for users, groups and service principals with last-admin and least-privilege protection.
9. Re-run readiness after configuration and produce an onboarding runbook of remaining blockers.

## Required outputs

- Readiness dashboard
- Tenant settings verification
- Capacity selection/provisioning
- DEV/TEST/PROD workspaces
- Capacity assignments
- Workspace role configuration
- Readiness audit/runbook

## Application screens

| ID | Screen | Primary role | Key functions | Integration | Help |
| --- | --- | --- | --- | --- | --- |
| SC-005 | Fabric Readiness | Fabric Admin | Capacity, tenant settings, roles, workspace capability, blockers | Fabric read APIs | HLP-FAB-001 |
| SC-006 | Tenant Settings | Fabric Admin | Required settings, current/effective state, scope, action mode | GET tenant settings; preview update optional | HLP-FAB-002 |
| SC-007 | Capacity | Fabric/Azure Admin | Existing capacities, SKU, region, state; provision option | Fabric Core + Azure ARM | HLP-FAB-003 |
| SC-008 | Workspace Topology | Data Platform Admin | DEV/TEST/PROD names, capacity, domain, admin groups | Workspace APIs | HLP-FAB-004 |
| SC-009 | Workspace Access | Data Platform Admin | Users/groups/SPs and roles | Workspace role APIs | HLP-FAB-005 |


## Functional requirements

| ID | Requirement | Priority | Acceptance / Notes |
| --- | --- | --- | --- |
| FR-FAB-001 | Run a Fabric Readiness Assessment immediately after integration. | Must | Assessment returns capacity, roles, tenant settings, workspaces, API access and blockers. |
| FR-FAB-002 | List Fabric capacities accessible to the principal and show ID, name, SKU, region and state. | Must | Uses Fabric Core capacities API. |
| FR-FAB-003 | Allow selection of an existing Fabric capacity. | Must | Selected capacity stored and validated as active. |
| FR-FAB-004 | Optionally provision a new Fabric capacity through Azure Resource Manager if the customer grants the required Azure RBAC. | Must | Provisioning is disabled if Azure permission is absent. |
| FR-FAB-005 | Create a workspace through the Fabric REST API with display name, description and selected capacity when supported. | Must | Created workspace ID is stored. |
| FR-FAB-006 | Create DEV, TEST and PROD workspaces from a configurable naming template. | Must | No duplicate created if matching tagged/recorded workspace exists. |
| FR-FAB-007 | Assign a workspace to a Fabric capacity. | Must | Assignment status confirmed after long-running operation. |
| FR-FAB-008 | Add/update/delete workspace role assignments for approved users, groups and service principals. | Must | Last admin protection and least-privilege checks enforced. |
| FR-FAB-009 | Read Fabric tenant settings required by Semantiq and show effective state and scope. | Must | Uses Admin list tenant settings API where authorised. |
| FR-FAB-010 | Support a feature-flagged tenant-setting update capability only when Microsoft public API is enabled for production use by product policy. | Must | Preview endpoint is not used silently; explicit warning and admin approval required. |
| FR-FAB-011 | Provide guided manual steps for tenant settings when API update is disabled or unsupported. | Must | Re-check verifies resulting state. |
| FR-FAB-012 | Detect whether service-principal Fabric tenant settings permit public API calls and workspace/connection/deployment-pipeline creation. | Must | Assessment identifies exact missing setting. |


## API / automation register

| ID | Operation | Pattern | Mode | Use in Semantiq |
| --- | --- | --- | --- | --- |
| API-001 | List capacities | GET /v1/capacities | AUTO / read | Discover accessible capacity, SKU, region and state. |
| API-002 | Create workspace | POST /v1/workspaces | AUTO / approval | Create DEV/TEST/PROD workspace. |
| API-003 | Assign workspace to capacity | POST /v1/workspaces/{workspaceId}/assignToCapacity | AUTO / approval | Bind workspace to selected capacity. |
| API-004 | Workspace role assignment | POST /v1/workspaces/{workspaceId}/roleAssignments | AUTO / approval | Grant service principal/user/group role. |
| API-005 | List tenant settings | GET /v1/admin/tenantsettings | AUTO / read | Read effective tenant settings when authorised. |
| API-006 | Update tenant setting | POST /v1/admin/tenantsettings/{tenantSettingName}/update | PREVIEW / feature-flag | Preview; default product behaviour is guided manual + re-check. |


## In-app help topics

| Topic ID | Help topic |
| --- | --- |
| HLP-FAB-001 | Run the Fabric Readiness Assessment |
| HLP-FAB-002 | Enable required Fabric service-principal tenant settings |
| HLP-FAB-003 | Select or create a Fabric capacity |
| HLP-FAB-004 | Create DEV, TEST and PROD workspaces |
| HLP-FAB-005 | Grant the Semantiq service principal workspace access |


## Acceptance evidence

| ID | Scenario | Pass criterion |
| --- | --- | --- |
| AT-003 | Readiness blocker | Required service-principal tenant setting is disabled; Semantiq identifies blocker, opens guide, and turns green after admin enables it. |
| AT-004 | Existing Fabric | Customer selects active capacity and imports an existing workspace without duplication. |
| AT-005 | New Fabric workspace | Semantiq creates DEV workspace, assigns capacity and grants automation identity approved role. |


## Non-functional requirements

| ID | Category | Requirement |
| --- | --- | --- |
| NFR-SEC-01 | Security | Secrets encrypted at rest; no plaintext secrets in logs/database; OWASP-aligned web controls. |
| NFR-SEC-02 | Tenant isolation | All Semantiq records are partitioned and authorised by organisation/tenant context. |
| NFR-PERF-02 | Long operations | Operations over 10 seconds run asynchronously with progress/status; browser request is not held open. |
| NFR-OBS-01 | Observability | Structured logs, metrics, distributed correlation IDs, alerting and searchable audit. |
| NFR-MNT-01 | Maintainability | Fabric API integration isolated behind adapters/capability flags so Microsoft API changes do not require front-end redesign. |


## Fabric readiness checklist

| Check | Pass condition | Severity |
| --- | --- | --- |
| Tenant identity | Tenant ID matches onboarding configuration. | Blocker |
| Fabric access token | Token acquired for Fabric audience. | Blocker |
| Capacity | At least one active compatible Fabric capacity available, or Azure provisioning path available. | Blocker |
| Fabric administrator | A Fabric admin is available for tenant-level settings. | Blocker for tenant configuration |
| Service-principal public API setting | Required Fabric developer tenant setting permits the automation identity. | Blocker |
| Service-principal workspace/connection/deployment setting | Required if Semantiq uses SP to create those resources. | Blocker for those actions |
| Workspace creation permission | Caller/service principal permitted to create workspace. | Blocker |
| Capacity permission | Caller/service principal has contributor/admin access required by chosen capacity. | Blocker |
| DEV/TEST/PROD topology | Existing workspaces discovered or names available for creation. | Warning/Action |
| Region | Capacity/workspace region compatible with target architecture. | Warning/Blocker |
| AI settings | Required Fabric AI/Data Agent tenant settings enabled as applicable. | Blocker for agent phase |
| Licensing | Fabric/Power BI licences available for required users and workloads. | Warning/Blocker |
| Network | Required gateway/private connectivity path available. | Source-specific blocker |


## Tenant controls

| Setting / control | Why checked | Default action |
| --- | --- | --- |
| Service principals can call Fabric public APIs | Required for service-principal REST calls protected by Fabric permission model. | Detect; guide admin if disabled. |
| Service principals can create workspaces, connections, and deployment pipelines | Required for SP-based creation of those core resources. | Detect; guide admin if disabled. |
| Admin API settings for service principals | Needed only if Semantiq uses admin APIs with SP. | Enable only if required by product function. |
| Copilot/Fabric AI/Data Agent settings | Required to use AI/Data Agent features depending tenant/region policy. | Detect and guide. |
| Cross-geo AI processing/storage | May be required by customer region and Microsoft AI service location. | Never enable automatically without explicit policy approval. |


## Workspace template

| Environment | Default naming | Purpose | Minimum automation role |
| --- | --- | --- | --- |
| DEV | {Org}-{Domain}-DEV | Build and iterate ingestion, models and agents. | Contributor (Admin only where role assignment/config requires it) |
| TEST | {Org}-{Domain}-TEST | Integration, security, regression and business acceptance. | Contributor / controlled deployment access |
| PROD | {Org}-{Domain}-PROD | Approved production data intelligence. | Least privilege; production write only through release workflow |


## Verification checklist

- [ ] All blocker checks have deterministic pass/fail/unknown states.
- [ ] Existing workspace/capacity is reused without duplication.
- [ ] New DEV workspace creation and capacity assignment succeed in test tenant.
- [ ] Tenant-setting preview mutation cannot run unless explicitly feature-enabled and approved.
- [ ] Workspace role changes are auditable and cannot remove the last required administrator.

## Evidence before user confirmation

- [ ] Automated tests and test-report paths
- [ ] Manual workflow verification
- [ ] Redacted screenshots / request IDs / correlation IDs where applicable
- [ ] Migration/configuration and rollback notes
- [ ] Known issues and risks
- [ ] Confirmation that later-phase scope was not implemented

## User completion gate

Claude Code must ask the user to confirm the phase. Only after the user explicitly sends **`CONFIRM PHASE 02 COMPLETE`** may the phase be marked `CONFIRMED` and the next phase unlocked.

## Claude Code execution rules

1. Read `CLAUDE.md`, the master plan, status file and this phase document.
2. Inspect the existing codebase and do not assume a stack or architecture not present/approved.
3. Create/update `doc/execution/PHASE-02-PLAN.md` and obtain user approval.
4. Implement only current-phase scope.
5. Create/update `doc/execution/PHASE-02-VERIFICATION.md` with evidence.
6. Ask the user for explicit completion confirmation.
7. After confirmation, update `IMPLEMENTATION_STATUS.md` and unlock the next phase.
8. If a Microsoft API/UI/permission differs from the reference, verify current Microsoft documentation, log the decision and obtain approval before changing approach.

## Mandatory v1.3 controls - Fabric Region, Network & Encryption

- Fabric readiness must discover/record tenant home geography, capacity region, workspace placement and compare them with `DataProtectionProfile`.
- Reject or pause capacity/workspace provisioning outside approved storage/processing geographies unless an active sovereignty exception exists.
- Add guided/automated checks for tenant/workspace Private Link, inbound/public-access policy and managed private endpoint prerequisites where required.
- Evaluate and configure Fabric workspace CMK when the customer's policy requires customer-controlled encryption; validate Key Vault/HSM prerequisites and status.
- Cross-geo Fabric/Copilot/Data Agent processing/storage/history switches are high-impact settings and remain OFF unless Phase 07 has an approved need/exception.
- Add HLP-SOV-002, HLP-NET-003 and HLP-ENC-004.
