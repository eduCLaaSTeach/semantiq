# Phase 08 - Deployment, Operations, Help Centre and Lifecycle Management

**Reference ID:** P08-OPS
**SRS sections:** 14-18, 20 and relevant appendices
**Completion phrase:** `CONFIRM PHASE 08 COMPLETE`

> **Execution gate:** Do not start a later phase until this phase has passed verification and the user has explicitly confirmed completion.

## Objective

Operationalise Semantiq through controlled DEV-TEST-PROD promotion, monitoring, alerting, audit, drift/revalidation management, supportable guided help and safe release controls.

## Preconditions

- Phase 07 is `CONFIRMED` in `IMPLEMENTATION_STATUS.md`.
- Current branch/tests are in a known passing state.
- User approves the phase implementation plan before material code changes.

## Implementation activities

1. Create/attach Fabric deployment pipeline and map DEV/TEST/PROD workspaces to stages.
2. Implement pre-check, approval, deployment, post-check and rollback/degraded-state workflow.
3. Implement Operations Monitor for jobs, duration, row/volume metrics, failures, capacity health, data-quality trend and semantic/agent regression score.
4. Implement alert routing for failed jobs, stale data, credential expiry and quality/security thresholds.
5. Implement drift detection and Compare / Accept External Change / Restore Managed Configuration workflow.
6. Implement configuration/definition backup/export and change impact analysis.
7. Complete Help Centre coverage for every setup/configuration screen, including prerequisites, roles, portal path, copyable values, verification and troubleshooting.
8. Implement deep-linking from error categories to the relevant help topic.
9. Implement searchable audit log with actor, action, target, before/after metadata, result, correlation ID and Microsoft request ID where available.
10. Implement customer offboarding workflow and access revocation checklist.
11. Run all operational acceptance scenarios and record evidence.

## Required outputs

- Deployment pipeline
- Release gates
- Operations dashboard
- Alerts
- Drift/revalidation workflow
- Backup/export
- Complete Help Centre
- Audit log
- Offboarding workflow

## Application screens

| ID | Screen | Primary role | Key functions | Integration | Help |
| --- | --- | --- | --- | --- | --- |
| SC-023 | Deployment | Platform Admin | Deployment pipeline, stage mapping, release gate, approvals | Deployment Pipeline APIs | HLP-DEP-001 |
| SC-024 | Operations Monitor | Platform Admin | Capacity, jobs, refresh, failures, data quality, agent accuracy | Fabric/Power BI metrics + Semantiq telemetry | HLP-OPS-001 |
| SC-025 | Help Centre | All roles | Context help, step guides, troubleshooting, prerequisites | Semantiq content service | - |
| SC-026 | Audit Log | Admins/Auditors | Who, what, when, target, API, result, correlation ID | Semantiq audit store | HLP-AUD-001 |


## Functional requirements

| ID | Requirement | Priority | Acceptance / Notes |
| --- | --- | --- | --- |
| FR-OPS-001 | Create Fabric deployment pipeline with DEV, TEST and PROD stages. | Must | Pipeline ID and stage IDs stored. |
| FR-OPS-002 | Assign workspaces to deployment pipeline stages. | Must | Topology matches Semantiq environment model. |
| FR-OPS-003 | Support controlled stage deployment with pre-check, approval and post-check. | Must | Production deployment cannot bypass configured approver. |
| FR-OPS-004 | Display ingestion job status, duration, row/volume metrics and failures. | Must | Monitor supports date/source/environment filters. |
| FR-OPS-005 | Display Fabric capacity state and workload indicators available through approved metrics sources. | Must | Capacity health warning shown before saturation risk. |
| FR-OPS-006 | Display data-quality breaches and trend. | Must | Critical breach can block downstream deployment. |
| FR-OPS-007 | Display semantic/agent regression score and failed questions. | Must | Agent marked degraded if threshold breached. |
| FR-OPS-008 | Implement alert rules for failed jobs, stale data, credential expiry and quality threshold breaches. | Must | Alert routing configurable. |
| FR-OPS-009 | Provide audit trail for configuration changes and API actions. | Must | Audit entry includes actor, target, before/after metadata, result and correlation ID. |
| FR-OPS-010 | Support environment backup/export of Semantiq configuration and Fabric public definitions where available. | Should | Export package is versioned. |
| FR-OPS-011 | Support change impact analysis and mandatory revalidation after source/model/security/agent changes. | Must | Affected components listed. |
| FR-OPS-012 | Support graceful customer offboarding and revoke Semantiq-managed access. | Must | No orphaned stored credentials. |
| FR-HLP-001 | Every setup/configuration screen must have a context-sensitive Help action. | Must | Help opens at exact topic for current screen. |
| FR-HLP-002 | Each help topic must show prerequisites, required role, expected duration and impact. | Must | Displayed before procedural steps. |
| FR-HLP-003 | Help topics must include exact Microsoft portal navigation paths and field labels. | Must | User can follow without external interpretation. |
| FR-HLP-004 | Help topics must provide copyable values such as redirect URI, token scope and API endpoint when relevant. | Must | Copy button copies exact value. |
| FR-HLP-005 | Help topics must include a verification step that maps back to Re-check in Semantiq. | Must | Topic ends with expected successful state. |
| FR-HLP-006 | Help topics must include common error messages and troubleshooting. | Must | At least authentication, permission, tenant-setting and expired-credential cases for identity topics. |
| FR-HLP-007 | Preview or high-privilege Microsoft features must be explicitly labelled. | Must | No preview API presented as guaranteed production automation. |
| FR-HLP-008 | Help content must be versioned and record the Microsoft documentation date/reference used. | Must | Topic shows last reviewed date. |
| FR-HLP-009 | The UI must deep-link from an API error to the most relevant help topic. | Must | Error category mapped to topic ID. |
| FR-HLP-010 | Administrators must be able to export an onboarding runbook showing all remaining manual steps. | Should | Runbook generated from current state. |


## API / automation register

| ID | Operation | Pattern | Mode | Use in Semantiq |
| --- | --- | --- | --- | --- |
| API-018 | Create deployment pipeline | POST /v1/deploymentPipelines | AUTO / approval | Create DEV/TEST/PROD stages. |
| API-019 | Deployment pipeline stage operations | Deployment pipeline APIs | AUTO / approval | Assign workspaces and deploy stage content. |


## In-app help topics

| Topic ID | Help topic |
| --- | --- |
| HLP-DEP-001 | Create deployment pipeline and promote DEV -> TEST -> PROD |
| HLP-OPS-001 | Troubleshoot failed Fabric API or job runs |


## Acceptance evidence

| ID | Scenario | Pass criterion |
| --- | --- | --- |
| AT-012 | Deployment | Approved content is promoted DEV -> TEST -> PROD and post-release smoke test passes. |
| AT-013 | Rate limit | 429 response triggers Retry-After scheduling rather than immediate repeated calls. |
| AT-014 | Credential expiry | Automation credential approaching expiry generates alert and can be rotated with no loss of configuration. |
| AT-015 | Drift | Manual Fabric change is detected and user can Accept External Change or Restore Managed Configuration. |
| AT-016 | Help flow | User follows SSO/Fabric help topic, completes portal action, selects Re-check and receives verified completion. |


## Non-functional requirements

| ID | Category | Requirement |
| --- | --- | --- |
| NFR-AVL-01 | Availability | Target 99.9% monthly availability for Semantiq control-plane application, excluding Microsoft/customer dependencies. |
| NFR-PERF-01 | Interactive performance | 95th percentile page response < 3 seconds for metadata screens excluding external API execution. |
| NFR-PERF-02 | Long operations | Operations over 10 seconds run asynchronously with progress/status; browser request is not held open. |
| NFR-SCL-01 | Scalability | Control plane supports many customer tenants and thousands of managed Fabric items without cross-tenant contention. |
| NFR-OBS-01 | Observability | Structured logs, metrics, distributed correlation IDs, alerting and searchable audit. |
| NFR-DR-01 | Recovery | Configuration database backed up; documented RPO/RTO; secret vault integrated with platform recovery. |
| NFR-UX-01 | Usability | Non-specialist admin can follow guided setup with contextual help and clear prerequisite status. |
| NFR-A11Y-01 | Accessibility | Web UI follows WCAG 2.1 AA target for navigation, labels, contrast and keyboard usage. |
| NFR-SUP-01 | Supportability | Support export contains configuration and request IDs but redacts secrets and sensitive business data. |


## Standard status model

| Status | Meaning | UI action |
| --- | --- | --- |
| Not Started | No configuration exists. | Start Setup |
| In Progress | User entered draft or workflow running. | Continue / View Progress |
| Action Required | Manual/admin prerequisite missing. | Open Help / Re-check |
| Approval Required | Privileged or release operation waiting approval. | Review & Approve |
| Ready | Prerequisites satisfied; action can run. | Run / Apply |
| Succeeded | Target verified. | Continue |
| Warning | Function works but risk or optional prerequisite exists. | Review warning |
| Failed | Operation did not complete. | Retry / View Logs / Help |
| Drift Detected | External change differs from Semantiq recorded configuration. | Compare / Accept / Restore |
| Revalidation Required | Upstream change invalidates prior tests. | Run Validation |


## Error classification

| Category | Typical symptom | User-facing guidance |
| --- | --- | --- |
| Authentication | 401, invalid_client, expired secret | Check tenant/client ID, credential, expiry and token scope. |
| Consent / Tenant policy | 403 or admin consent required | Open SSO/Fabric tenant settings help; show exact missing permission/setting if detectable. |
| Fabric permission | 403 despite valid token | Check workspace role, capacity permission, item permission and service-principal tenant setting. |
| Resource not found | 404 / EntityNotFound | Refresh discovery; resource may have moved/deleted; verify workspace/item ID. |
| Conflict | 409 / duplicate name | Show existing resource and offer Use Existing or choose new name. |
| Rate limit | 429 | Respect Retry-After and show scheduled retry. |
| Long-running failure | 202 operation eventually fails | Show operation ID, Microsoft request ID, target and remediation. |
| Unsupported feature | FeatureNotAvailable / operation not supported | Switch to guided path; do not repeatedly retry. |
| Data-quality failure | Quality threshold breached | Show failing rules and blocked downstream step. |
| Security regression | RLS/OLS test mismatch | Block production publish until corrected. |


## AI runtime operations reference

> If an AI/conversational runtime is deployed, Phase 08 must implement the operational controls defined in [`AI_CONVERSATIONAL_TECHNOLOGY_GUIDE.md`](../reference/AI_CONVERSATIONAL_TECHNOLOGY_GUIDE.md). Monitoring must cover the selected runtime/model/agent version, tool calls, latency, rate-limit/quota failures, evaluation/regression score, safety/policy failures and fallback behavior. If the approved stack is Microsoft Agent Framework/Foundry, Copilot Studio or Fabric Data Agent, use the supported Microsoft telemetry/evaluation capabilities; if LangGraph/vLLM or another open-source stack is approved, provide equivalent tracing, health, security and capacity monitoring.

## Verification checklist

- [ ] Production deployment cannot bypass configured approval.
- [ ] Failed post-check results in Degraded/Failed state and documented recovery path.
- [ ] Operations monitor shows representative ingestion/quality/agent signals.
- [ ] Errors deep-link to correct help topic.
- [ ] Drift can be detected and resolved without silent overwrite.
- [ ] Offboarding removes stored credentials and records completion.

## Evidence before user confirmation

- [ ] Automated tests and test-report paths
- [ ] Manual workflow verification
- [ ] Redacted screenshots / request IDs / correlation IDs where applicable
- [ ] Migration/configuration and rollback notes
- [ ] Known issues and risks
- [ ] Confirmation that later-phase scope was not implemented

## User completion gate

Claude Code must ask the user to confirm the phase. Only after the user explicitly sends **`CONFIRM PHASE 08 COMPLETE`** may the phase be marked `CONFIRMED` and the next phase unlocked.

## Claude Code execution rules

1. Read `CLAUDE.md`, the master plan, status file and this phase document.
2. Inspect the existing codebase and do not assume a stack or architecture not present/approved.
3. Create/update `doc/execution/PHASE-08-PLAN.md` and obtain user approval.
4. Implement only current-phase scope.
5. Create/update `doc/execution/PHASE-08-VERIFICATION.md` with evidence.
6. Ask the user for explicit completion confirmation.
7. After confirmation, update `IMPLEMENTATION_STATUS.md` and unlock the next phase.
8. If a Microsoft API/UI/permission differs from the reference, verify current Microsoft documentation, log the decision and obtain approval before changing approach.

## Mandatory v1.3 controls - Operational Data Protection

- Production deployment verifies region, network, CMK, labels/DLP, retention and cross-geo settings against the approved profile and detects configuration drift.
- Observability stores metadata/correlation IDs by default; sensitive payload capture is disabled and any support override is time-bound, approved and audited.
- Implement alerts for key/certificate expiry, CMK failure/revocation, Private Link/public-access drift, cross-geo AI setting changes and unexpected region changes.
- Backup/export/support bundles must follow customer geography, classification and retention policy and must redact secrets.
