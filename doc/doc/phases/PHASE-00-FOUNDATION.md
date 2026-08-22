# Phase 00 - Engineering Foundation and Control Plane Skeleton

**Reference ID:** P00-FND
**SRS sections:** 1-4, 6-8, 17-19 and cross-cutting design principles
**Completion phrase:** `CONFIRM PHASE 00 COMPLETE`

> **Execution gate:** Do not start a later phase until this phase has passed verification and the user has explicitly confirmed completion.

## Objective

Establish the repository, application shell, control-plane architecture, configuration persistence, security baseline, orchestration framework, auditability, help framework and phase-gate workflow before customer-facing Fabric automation is built.

## Preconditions

- Approved GitHub repository access. Confirmed application baseline: Laravel 13/PHP 8.5, React 19, MySQL on cPanel, modular monolith. Inspect the real scaffold before planning.
- SRS baseline available under `doc/reference/`. Existing UI/design material under `doc/design-system/` must be preserved.

## Implementation activities

1. Inspect the existing GitHub repository and confirm the approved Laravel 13/PHP 8.5, React 19, MySQL/cPanel modular-monolith baseline. Scaffold missing application code without replacing frameworks or hosting unless approved.
2. Create the SemantIQ application shell, navigation and organisation/tenant context boundary for the current single-customer deployment, while keeping customer-owned records explicitly scopeable for future multi-tenant SaaS.
3. Implement the configuration data model baseline for Organisation, WorkflowRun, AuditEvent, HelpTopic and generic FabricItem references.
4. Implement secret-provider abstraction; no real credentials are stored until Phase 1.
5. Implement asynchronous workflow orchestration for operations longer than 10 seconds, including correlation IDs, retries and resumable status.
6. Implement the common status model: Not Started, In Progress, Action Required, Approval Required, Ready, Succeeded, Warning, Failed, Drift Detected and Revalidation Required.
7. Implement immutable/auditable event capture for configuration changes and external API operations.
8. Implement the context-help framework and Help Centre content model so later phases can attach exact help topics to screens.
9. Create API adapter/capability-registry interfaces so stable, preview and guided-only Microsoft operations can be handled without front-end redesign.
10. Create CI/CD quality gates, automated tests, linting/static analysis, environment configuration and a safe local-development workflow.
11. Create the phase-gate files and require explicit user confirmation before a phase is marked complete.

## Required outputs

- Running Semantiq shell
- Organisation/tenant context foundation
- Configuration database baseline
- Secret-provider abstraction
- Workflow orchestration service
- Audit log framework
- Help Centre framework
- Capability registry
- CI/CD baseline
- Phase status and evidence workflow

## Application screens

| ID | Screen | Primary role | Key functions | Integration | Help |
| --- | --- | --- | --- | --- | --- |
| SC-002 | Organisation Setup | Semantiq Admin | Organisation name, tenant ID, domain, region, owner | Semantiq metadata | HLP-ORG-001 |
| SC-025 | Help Centre | All roles | Context help, step guides, troubleshooting, prerequisites | Semantiq content service | - |
| SC-026 | Audit Log | Admins/Auditors | Who, what, when, target, API, result, correlation ID | Semantiq audit store | HLP-AUD-001 |


## Non-functional requirements

| ID | Category | Requirement |
| --- | --- | --- |
| NFR-SEC-01 | Security | Secrets encrypted at rest; no plaintext secrets in logs/database; OWASP-aligned web controls. |
| NFR-SEC-02 | Organisation/tenant boundary | Customer-owned SemantIQ records are authorised by organisation/tenant context. Current deployment is single-customer; cross-organisation access is denied and future multi-tenant enablement must not require removing this boundary. |
| NFR-AVL-01 | Availability | Target 99.9% monthly availability for Semantiq control-plane application, excluding Microsoft/customer dependencies. |
| NFR-PERF-01 | Interactive performance | 95th percentile page response < 3 seconds for metadata screens excluding external API execution. |
| NFR-PERF-02 | Long operations | Operations over 10 seconds run asynchronously with progress/status; browser request is not held open. |
| NFR-SCL-01 | Scalability | Current release supports one customer organisation per deployed instance and is designed so a later approved multi-tenant service can isolate customer contexts while managing thousands of Fabric items. |
| NFR-OBS-01 | Observability | Structured logs, metrics, distributed correlation IDs, alerting and searchable audit. |
| NFR-DR-01 | Recovery | Configuration database backed up; documented RPO/RTO; secret vault integrated with platform recovery. |
| NFR-UX-01 | Usability | Non-specialist admin can follow guided setup with contextual help and clear prerequisite status. |
| NFR-A11Y-01 | Accessibility | Web UI follows WCAG 2.1 AA target for navigation, labels, contrast and keyboard usage. |
| NFR-COMP-01 | Privacy | Store minimum customer business data in control plane; prefer metadata and resource IDs. |
| NFR-COMP-02 | Audit retention | Retention configurable according to customer and regulatory requirements. |
| NFR-MNT-01 | Maintainability | Fabric API integration isolated behind adapters/capability flags so Microsoft API changes do not require front-end redesign. |
| NFR-VER-01 | Versioning | Generated Fabric definitions, quality rules, semantic configuration and Data Agent definitions are versioned. |
| NFR-SUP-01 | Supportability | Support export contains configuration and request IDs but redacts secrets and sensitive business data. |


## AI / conversational architecture preparation

> **Reference:** Read [`AI_CONVERSATIONAL_TECHNOLOGY_GUIDE.md`](../reference/AI_CONVERSATIONAL_TECHNOLOGY_GUIDE.md). Phase 00 does not select or implement an LLM. It must establish replaceable interfaces for Model Provider, Agent Runtime, Retrieval/Knowledge Provider, Tool/MCP Provider, Conversation Store, Evaluation/Observability and Channel/UI Adapter so a later approved Microsoft or open-source stack does not require a product-wide rewrite.

Deterministic Fabric provisioning/configuration must remain outside the LLM execution path.

## Verification checklist

- [ ] Repository stack and architecture are documented and approved.
- [ ] Organisation/tenant boundary tests prove a request cannot access records outside its active organisation context. Multiple organisation fixtures may be used for isolation tests even though the current deployment mode is single-customer.
- [ ] A sample long-running workflow can resume and records correlation/audit data.
- [ ] No secret is present in source control, application logs or configuration database.
- [ ] Help topic can be opened contextually from a sample screen.
- [ ] CI pipeline passes required tests and quality checks.

## Evidence before user confirmation

- [ ] Automated tests and test-report paths
- [ ] Manual workflow verification
- [ ] Redacted screenshots / request IDs / correlation IDs where applicable
- [ ] Migration/configuration and rollback notes
- [ ] Known issues and risks
- [ ] Confirmation that later-phase scope was not implemented

## User completion gate

Claude Code must ask the user to confirm the phase. Only after the user explicitly sends **`CONFIRM PHASE 00 COMPLETE`** may the phase be marked `CONFIRMED` and the next phase unlocked.

## Claude Code execution rules

1. Read `CLAUDE.md`, the master plan, status file and this phase document.
2. Inspect the existing codebase and do not assume a stack or architecture not present/approved.
3. Create/update `doc/execution/PHASE-00-PLAN.md` and obtain user approval.
4. Implement only current-phase scope.
5. Create/update `doc/execution/PHASE-00-VERIFICATION.md` with evidence.
6. Ask the user for explicit completion confirmation.
7. After confirmation, update `IMPLEMENTATION_STATUS.md` and unlock the next phase.
8. If a Microsoft API/UI/permission differs from the reference, verify current Microsoft documentation, log the decision and obtain approval before changing approach.

## Mandatory v1.3 controls - Data Protection, Sovereignty & Context

- Create the versioned `DataProtectionProfile` and organisation/tenant-scoped policy store before any production Fabric provisioning.
- Add Organisation Setup fields for approved storage/processing geographies, cross-geo policy, retention profile, CMK/Private Link/Purview requirements and policy approver.
- Implement `VAL-SOV-GEO-001` as a reusable server-side policy check; no production activation can bypass it.
- Establish the `doc/context/` registers and add CI/PR checks requiring relevant context updates for behavior-changing changes.
- Logging baseline must redact credentials/tokens and disable production payload capture by default.
- Add HLP-SOV-001 and HLP-CTX-007 to the Help framework.
