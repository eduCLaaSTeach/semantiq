# Phase 04 - Ingestion, Lakehouse and Medallion Data Foundation

**Reference ID:** P04-ING
**SRS sections:** 10.2-10.3 and ingestion requirements
**Completion phrase:** `CONFIRM PHASE 04 COMPLETE`

> **Execution gate:** Do not start a later phase until this phase has passed verification and the user has explicitly confirmed completion.

## Objective

Create the Fabric Lakehouse/data foundation and deploy controlled ingestion definitions that land source data into Bronze, support incremental/scheduled execution and establish the path to Silver and Gold.

## Preconditions

- Phase 03 is `CONFIRMED` in `IMPLEMENTATION_STATUS.md`.
- Current branch/tests are in a known passing state.
- User approves the phase implementation plan before material code changes.

## Implementation activities

1. Implement Ingestion Plan screen covering method, target, schedule, incremental key/watermark, retry policy, reject handling and naming.
2. Create or attach the domain/environment Lakehouse and persist Lakehouse/SQL endpoint metadata.
3. Create Bronze/Silver/Gold logical conventions and source-to-target mapping.
4. Generate/version Fabric Data Pipeline, notebook, SQL or other selected ingestion artifact according to capability/policy.
5. Run the initial load and persist job/run IDs, row/volume metrics, source object and target metadata.
6. Implement incremental loading using timestamp, ID, CDC or source-specific mechanism when available.
7. Create/enable/disable schedules using supported job scheduler APIs.
8. Implement Retry-After handling for throttling, retries, failure paths, rejects and notifications.
9. Detect source schema drift and classify compatible, warning or breaking changes; breaking drift blocks promotion.

## Required outputs

- Lakehouse
- Bronze/Silver/Gold conventions
- Ingestion definitions
- Initial load
- Scheduling
- Incremental state
- Run/audit history
- Schema-drift controls

## Application screens

| ID | Screen | Primary role | Key functions | Integration | Help |
| --- | --- | --- | --- | --- | --- |
| SC-014 | Ingestion Plan | Data Engineer/Admin | Method, target, schedule, incremental key, retry policy | Pipeline/Item/Job APIs | HLP-ING-001 |
| SC-015 | Lakehouse & Layers | Data Platform Admin | Lakehouse, Bronze/Silver/Gold layout, naming rules | Lakehouse API | HLP-LKH-001 |


## Functional requirements

| ID | Requirement | Priority | Acceptance / Notes |
| --- | --- | --- | --- |
| FR-SRC-009 | Create Bronze landing structure and ingestion definitions. | Must | Source-to-target mapping recorded. |
| FR-SRC-010 | Support incremental loading using timestamps, IDs, CDC or source-specific methods where available. | Must | Incremental key/watermark persisted. |
| FR-SRC-011 | Support schedule definition and item/job scheduling where supported. | Must | Schedule can be enabled/disabled and inspected. |
| FR-SRC-012 | Implement retries, failure paths, reject handling and notifications. | Must | Failure includes source, object, run ID and remediation action. |
| FR-SRC-013 | Detect schema drift and classify as compatible, warning or breaking change. | Must | Breaking change pauses promotion until reviewed. |
| FR-SRC-014 | Maintain ingestion audit history and lineage from source object to Bronze target. | Must | History searchable by source/run. |
| FR-SRC-015 | Support on-demand run and scheduled run for supported Fabric items. | Must | Job state is polled and persisted. |
| FR-DQ-001 | Create or attach a Lakehouse for each configured domain/environment. | Must | Lakehouse ID and SQL endpoint metadata stored. |
| FR-DQ-002 | Create logical Bronze, Silver and Gold conventions. | Must | Naming and storage paths follow configured standard. |
| FR-DQ-011 | Preserve raw source data in Bronze subject to customer retention policy. | Must | No Silver/Gold cleaning overwrites raw lineage. |


## API / automation register

| ID | Operation | Pattern | Mode | Use in Semantiq |
| --- | --- | --- | --- | --- |
| API-009 | Create Lakehouse | POST /v1/workspaces/{workspaceId}/lakehouses | AUTO | Provision Lakehouse. |
| API-010 | Create Data Pipeline | POST /v1/workspaces/{workspaceId}/dataPipelines | AUTO | Create pipeline item; deploy definition as supported. |
| API-011 | Run item job | POST /v1/workspaces/{workspaceId}/items/{itemId}/jobs/{jobType}/instances | AUTO | Run on demand; honour Retry-After. |
| API-012 | Create item schedule | POST /v1/workspaces/{workspaceId}/items/{itemId}/jobs/{jobType}/schedules | AUTO | Create supported schedule. |


## In-app help topics

| Topic ID | Help topic |
| --- | --- |
| HLP-ING-001 | Create an ingestion plan and schedule |
| HLP-LKH-001 | Create Lakehouse and Bronze/Silver/Gold layout |


## Acceptance evidence

| ID | Scenario | Pass criterion |
| --- | --- | --- |
| AT-007 | Bronze load | Initial data lands in Bronze with lineage/run metadata. |
| AT-013 | Rate limit | 429 response triggers Retry-After scheduling rather than immediate repeated calls. |


## Non-functional requirements

| ID | Category | Requirement |
| --- | --- | --- |
| NFR-PERF-02 | Long operations | Operations over 10 seconds run asynchronously with progress/status; browser request is not held open. |
| NFR-OBS-01 | Observability | Structured logs, metrics, distributed correlation IDs, alerting and searchable audit. |
| NFR-VER-01 | Versioning | Generated Fabric definitions, quality rules, semantic configuration and Data Agent definitions are versioned. |
| NFR-MNT-01 | Maintainability | Fabric API integration isolated behind adapters/capability flags so Microsoft API changes do not require front-end redesign. |


## Source onboarding wizard - steps 9-12

| Step | Screen action | Requirement |
| --- | --- | --- |
| 9 | Choose ingestion pattern | Full + incremental, CDC, schedule or near-real-time. |
| 10 | Preview generated plan | Target Lakehouse, Bronze objects, pipeline/notebook, schedule, errors and naming. |
| 11 | Deploy | Create Fabric artifacts and run initial load. |
| 12 | Validate | Check row counts, schema, freshness and quality baseline. |


## Medallion rules

| Layer | Purpose | Semantiq rule |
| --- | --- | --- |
| Bronze / Raw | Preserve source fidelity and lineage. | Minimal transformation; retain source object, load time, source key and ingestion run metadata. |
| Silver / Clean | Standardise and validate data. | Apply approved type, null, duplicate, format, reference and entity-standardisation rules. |
| Gold / Business | Create analytics-ready business models. | Facts, dimensions, calculated fields, conformed keys and business-grain definitions. |


## Verification checklist

- [ ] Initial data lands in Bronze with load/source/run metadata.
- [ ] Re-running setup does not create duplicate managed artifacts.
- [ ] Incremental watermark advances only after successful run.
- [ ] 429 response respects Retry-After.
- [ ] Breaking schema drift pauses downstream processing and raises Action Required.

## Evidence before user confirmation

- [ ] Automated tests and test-report paths
- [ ] Manual workflow verification
- [ ] Redacted screenshots / request IDs / correlation IDs where applicable
- [ ] Migration/configuration and rollback notes
- [ ] Known issues and risks
- [ ] Confirmation that later-phase scope was not implemented

## User completion gate

Claude Code must ask the user to confirm the phase. Only after the user explicitly sends **`CONFIRM PHASE 04 COMPLETE`** may the phase be marked `CONFIRMED` and the next phase unlocked.

## Claude Code execution rules

1. Read `CLAUDE.md`, the master plan, status file and this phase document.
2. Inspect the existing codebase and do not assume a stack or architecture not present/approved.
3. Create/update `doc/execution/PHASE-04-PLAN.md` and obtain user approval.
4. Implement only current-phase scope.
5. Create/update `doc/execution/PHASE-04-VERIFICATION.md` with evidence.
6. Ask the user for explicit completion confirmation.
7. After confirmation, update `IMPLEMENTATION_STATUS.md` and unlock the next phase.
8. If a Microsoft API/UI/permission differs from the reference, verify current Microsoft documentation, log the decision and obtain approval before changing approach.

## Mandatory v1.3 controls - Data Placement & Medallion Protection

- Bronze/Silver/Gold storage must remain in approved Fabric workspace/capacity geographies.
- Preserve classification, owner, retention and lineage metadata through Bronze -> Silver -> Gold wherever technically supported; record exceptions where metadata cannot propagate automatically.
- Pipeline diagnostics must not log sensitive row payloads; reject/error stores require classification and retention rules.
- Incremental/checkpoint/temp data is part of the data-flow review and may not be placed in unapproved storage.
- Revalidate sovereignty when pipeline destination, workspace, source, shortcut or mirroring topology changes.
