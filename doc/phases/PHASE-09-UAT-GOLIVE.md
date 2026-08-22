# Phase 09 - End-to-End UAT, Production Go-Live and Customer Handover

**Reference ID:** P09-GO
**SRS sections:** 19-22 and all acceptance criteria
**Completion phrase:** `CONFIRM PHASE 09 COMPLETE AND BASELINE ACCEPTED`

> **Execution gate:** Do not start a later phase until this phase has passed verification and the user has explicitly confirmed completion.

## Objective

Prove the product as an end-to-end customer journey, verify non-functional and security expectations, obtain formal user acceptance, release the production baseline and hand over operating/support guidance.

## Preconditions

- Phase 08 is `CONFIRMED` in `IMPLEMENTATION_STATUS.md`.
- Current branch/tests are in a known passing state.
- User approves the phase implementation plan before material code changes.

## Implementation activities

1. Run the full customer journey from sign-in through tenant readiness, Fabric setup, source onboarding, ingestion, quality, semantic model, Data Agent, deployment and operations.
2. Execute all SRS acceptance scenarios AT-001 through AT-016 and attach evidence.
3. Run security review, tenant-isolation testing, secret/log review, RLS/OLS leakage tests and privileged-operation approval tests.
4. Run performance, long-operation, resilience/recovery, observability, accessibility and supportability checks against the defined NFRs.
5. Validate that all in-app help topics are current, complete, versioned and include Re-check/expected-result steps.
6. Produce production release notes, known limitations, rollback/recovery runbook, operator guide and customer admin guide.
7. Obtain explicit business owner, security owner and platform owner approval for production release.
8. Deploy production release, run smoke tests and monitor the agreed stabilization window.
9. Capture customer confirmation that Phase 09 and the baseline release are accepted; only then set the master plan status to BASELINE COMPLETE.

## Required outputs

- UAT evidence pack
- NFR/security verification report
- Release notes
- Known limitations
- Rollback/recovery runbook
- Operator/admin handover
- Production release
- Baseline completion record

## Application screens

| ID | Screen | Primary role | Key functions | Integration | Help |
| --- | --- | --- | --- | --- | --- |
| SC-001 | Sign In | All users | SSO login, tenant detection, session creation | Microsoft Entra OIDC/OAuth | HLP-SSO-001 |
| SC-005 | Fabric Readiness | Fabric Admin | Capacity, tenant settings, roles, workspace capability, blockers | Fabric read APIs | HLP-FAB-001 |
| SC-010 | Source Catalogue | Data Owner | Source type, domain, owner, criticality, update frequency | Semantiq + connector discovery | HLP-SRC-001 |
| SC-014 | Ingestion Plan | Data Engineer/Admin | Method, target, schedule, incremental key, retry policy | Pipeline/Item/Job APIs | HLP-ING-001 |
| SC-016 | Data Quality | Data Steward | Profile, null/duplicate/range rules, severity, thresholds | Generated notebook/pipeline rules | HLP-DQ-001 |
| SC-018 | Semantic Model Studio | Semantic Owner | Facts/dimensions, relationships, measures, names, descriptions | Semantic Model API | HLP-SEM-001 |
| SC-020 | AI Readiness | AI Owner | Approved tables/columns/measures, instructions, synonyms, verified questions | Definition/config generation | HLP-AI-001 |
| SC-021 | Fabric Data Agent | AI Owner | Agent name, sources, instructions, examples, publish state | DataAgent APIs | HLP-AGT-001 |
| SC-022 | Validation Centre | Owners/QA | Ground truth, security tests, data checks, regression score | Jobs + comparison engine | HLP-VAL-001 |
| SC-023 | Deployment | Platform Admin | Deployment pipeline, stage mapping, release gate, approvals | Deployment Pipeline APIs | HLP-DEP-001 |
| SC-024 | Operations Monitor | Platform Admin | Capacity, jobs, refresh, failures, data quality, agent accuracy | Fabric/Power BI metrics + Semantiq telemetry | HLP-OPS-001 |
| SC-025 | Help Centre | All roles | Context help, step guides, troubleshooting, prerequisites | Semantiq content service | - |
| SC-026 | Audit Log | Admins/Auditors | Who, what, when, target, API, result, correlation ID | Semantiq audit store | HLP-AUD-001 |


## In-app help topics

| Topic ID | Help topic |
| --- | --- |
| HLP-SSO-001 | Set up Semantiq SSO and grant tenant admin consent |
| HLP-FAB-001 | Run the Fabric Readiness Assessment |
| HLP-SRC-002 | Create and test a Fabric connection |
| HLP-ING-001 | Create an ingestion plan and schedule |
| HLP-DQ-001 | Review and approve data-quality rules |
| HLP-SEM-001 | Review the generated semantic model |
| HLP-SEC-001 | Configure and test RLS/OLS |
| HLP-AI-001 | Prepare approved data and business instructions for AI |
| HLP-AGT-001 | Create, configure, validate and publish a Fabric Data Agent |
| HLP-DEP-001 | Create deployment pipeline and promote DEV -> TEST -> PROD |
| HLP-OPS-001 | Troubleshoot failed Fabric API or job runs |


## Acceptance evidence

| ID | Scenario | Pass criterion |
| --- | --- | --- |
| AT-001 | SSO onboarding | New customer signs in, admin consent is granted, tenant is verified and Semantiq role assigned. |
| AT-002 | Fabric identity | Customer enters automation app credentials; token and Fabric read test succeed. |
| AT-003 | Readiness blocker | Required service-principal tenant setting is disabled; Semantiq identifies blocker, opens guide, and turns green after admin enables it. |
| AT-004 | Existing Fabric | Customer selects active capacity and imports an existing workspace without duplication. |
| AT-005 | New Fabric workspace | Semantiq creates DEV workspace, assigns capacity and grants automation identity approved role. |
| AT-006 | Source connection | SQL/SharePoint/other supported source connection is created/tested and source objects are discovered. |
| AT-007 | Bronze load | Initial data lands in Bronze with lineage/run metadata. |
| AT-008 | Quality gate | Known duplicate/null issue is detected; approved rule blocks Gold promotion until resolved or waived. |
| AT-009 | Semantic model | Generated model contains approved relationships, explicit measures, descriptions and security configuration. |
| AT-010 | Data Agent | Semantiq creates Data Agent, deploys definition, runs test pack and publishes after approval. |
| AT-011 | Security | Restricted test user cannot retrieve out-of-scope data through semantic model or agent. |
| AT-012 | Deployment | Approved content is promoted DEV -> TEST -> PROD and post-release smoke test passes. |
| AT-013 | Rate limit | 429 response triggers Retry-After scheduling rather than immediate repeated calls. |
| AT-014 | Credential expiry | Automation credential approaching expiry generates alert and can be rotated with no loss of configuration. |
| AT-015 | Drift | Manual Fabric change is detected and user can Accept External Change or Restore Managed Configuration. |
| AT-016 | Help flow | User follows SSO/Fabric help topic, completes portal action, selects Re-check and receives verified completion. |


## Non-functional requirements

| ID | Category | Requirement |
| --- | --- | --- |
| NFR-SEC-01 | Security | Secrets encrypted at rest; no plaintext secrets in logs/database; OWASP-aligned web controls. |
| NFR-SEC-02 | Tenant isolation | All Semantiq records are partitioned and authorised by organisation/tenant context. |
| NFR-AVL-01 | Availability | Target 99.9% monthly availability for Semantiq control-plane application, excluding Microsoft/customer dependencies. |
| NFR-PERF-01 | Interactive performance | 95th percentile page response < 3 seconds for metadata screens excluding external API execution. |
| NFR-PERF-02 | Long operations | Operations over 10 seconds run asynchronously with progress/status; browser request is not held open. |
| NFR-SCL-01 | Scalability | Control plane supports many customer tenants and thousands of managed Fabric items without cross-tenant contention. |
| NFR-OBS-01 | Observability | Structured logs, metrics, distributed correlation IDs, alerting and searchable audit. |
| NFR-DR-01 | Recovery | Configuration database backed up; documented RPO/RTO; secret vault integrated with platform recovery. |
| NFR-UX-01 | Usability | Non-specialist admin can follow guided setup with contextual help and clear prerequisite status. |
| NFR-A11Y-01 | Accessibility | Web UI follows WCAG 2.1 AA target for navigation, labels, contrast and keyboard usage. |
| NFR-COMP-01 | Privacy | Store minimum customer business data in control plane; prefer metadata and resource IDs. |
| NFR-COMP-02 | Audit retention | Retention configurable according to customer and regulatory requirements. |
| NFR-MNT-01 | Maintainability | Fabric API integration isolated behind adapters/capability flags so Microsoft API changes do not require front-end redesign. |
| NFR-VER-01 | Versioning | Generated Fabric definitions, quality rules, semantic configuration and Data Agent definitions are versioned. |
| NFR-SUP-01 | Supportability | Support export contains configuration and request IDs but redacts secrets and sensitive business data. |


## Complete acceptance set

| ID | Scenario | Pass criterion |
| --- | --- | --- |
| AT-001 | SSO onboarding | New customer signs in, admin consent is granted, tenant is verified and Semantiq role assigned. |
| AT-002 | Fabric identity | Customer enters automation app credentials; token and Fabric read test succeed. |
| AT-003 | Readiness blocker | Required service-principal tenant setting is disabled; Semantiq identifies blocker, opens guide, and turns green after admin enables it. |
| AT-004 | Existing Fabric | Customer selects active capacity and imports an existing workspace without duplication. |
| AT-005 | New Fabric workspace | Semantiq creates DEV workspace, assigns capacity and grants automation identity approved role. |
| AT-006 | Source connection | SQL/SharePoint/other supported source connection is created/tested and source objects are discovered. |
| AT-007 | Bronze load | Initial data lands in Bronze with lineage/run metadata. |
| AT-008 | Quality gate | Known duplicate/null issue is detected; approved rule blocks Gold promotion until resolved or waived. |
| AT-009 | Semantic model | Generated model contains approved relationships, explicit measures, descriptions and security configuration. |
| AT-010 | Data Agent | Semantiq creates Data Agent, deploys definition, runs test pack and publishes after approval. |
| AT-011 | Security | Restricted test user cannot retrieve out-of-scope data through semantic model or agent. |
| AT-012 | Deployment | Approved content is promoted DEV -> TEST -> PROD and post-release smoke test passes. |
| AT-013 | Rate limit | 429 response triggers Retry-After scheduling rather than immediate repeated calls. |
| AT-014 | Credential expiry | Automation credential approaching expiry generates alert and can be rotated with no loss of configuration. |
| AT-015 | Drift | Manual Fabric change is detected and user can Accept External Change or Restore Managed Configuration. |
| AT-016 | Help flow | User follows SSO/Fabric help topic, completes portal action, selects Re-check and receives verified completion. |


## AI / conversational go-live decision check

> Production acceptance must confirm the implementation still matches the user-approved `doc/execution/AI-TECHNOLOGY-DECISION.md` and [`AI_CONVERSATIONAL_TECHNOLOGY_GUIDE.md`](../reference/AI_CONVERSATIONAL_TECHNOLOGY_GUIDE.md). Re-verify product/API maturity, model/runtime versions, region, identity, licensing and supportability immediately before go-live. Any change from Microsoft to open-source (or vice versa), or any change in agent framework/model hosting, requires a documented decision and targeted regression/security testing before release.

## Verification checklist

- [ ] AT-001 through AT-016 are Pass or have explicitly accepted exceptions.
- [ ] All mandatory NFRs have evidence or approved exception.
- [ ] No unresolved blocker/critical security issue remains.
- [ ] Production smoke test passes.
- [ ] User formally accepts production baseline.

## Evidence before user confirmation

- [ ] Automated tests and test-report paths
- [ ] Manual workflow verification
- [ ] Redacted screenshots / request IDs / correlation IDs where applicable
- [ ] Migration/configuration and rollback notes
- [ ] Known issues and risks
- [ ] Confirmation that later-phase scope was not implemented

## User completion gate

Claude Code must ask the user to confirm the phase. Only after the user explicitly sends **`CONFIRM PHASE 09 COMPLETE AND BASELINE ACCEPTED`** may the phase be marked `CONFIRMED` and the next phase unlocked.

## Claude Code execution rules

1. Read `CLAUDE.md`, the master plan, status file and this phase document.
2. Inspect the existing codebase and do not assume a stack or architecture not present/approved.
3. Create/update `doc/execution/PHASE-09-PLAN.md` and obtain user approval.
4. Implement only current-phase scope.
5. Create/update `doc/execution/PHASE-09-VERIFICATION.md` with evidence.
6. Ask the user for explicit completion confirmation.
7. After confirmation, update `IMPLEMENTATION_STATUS.md` and unlock the next phase.
8. If a Microsoft API/UI/permission differs from the reference, verify current Microsoft documentation, log the decision and obtain approval before changing approach.

## Mandatory v1.3 controls - Sovereignty & Context Go-Live Gate

- UAT must prove actual production storage/processing regions match the approved `DataProtectionProfile` and `DATA_SOVEREIGNTY_REGISTER.md`.
- Verify tenant isolation, RLS/OLS, secret handling, logging redaction, network controls, CMK and Purview/DLP controls where required.
- All sovereignty exceptions must have named approver, reason, scope, compensating control and review/expiry date.
- Go-live is blocked if any code/data/validation/configuration context register is stale or if a production data flow is unclassified/unowned/unmapped.
- Final handover includes the data-flow/sovereignty register, configuration register, validation register, security decisions and operational verification evidence.
