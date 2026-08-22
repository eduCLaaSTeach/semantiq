# Phase 03 - Source Connectivity, Gateway and Schema Discovery

**Reference ID:** P03-SRC
**SRS sections:** 10.1 and source-connectivity requirements
**Completion phrase:** `CONFIRM PHASE 03 COMPLETE`

> **Execution gate:** Do not start a later phase until this phase has passed verification and the user has explicitly confirmed completion.

## Objective

Provide a guided source-onboarding experience that registers enterprise sources, creates/tests supported Fabric connections, handles gateway requirements and discovers only the customer-approved source scope.

## Preconditions

- Phase 02 is `CONFIRMED` in `IMPLEMENTATION_STATUS.md`.
- Current branch/tests are in a known passing state.
- User approves the phase implementation plan before material code changes.

## Implementation activities

1. Implement Source Catalogue with source type, domain, owner, criticality, classification, expected volume and refresh SLA.
2. Implement connector-specific forms for supported source categories without assuming a single authentication method.
3. Recommend connection/ingestion route (cloud connection, on-premises gateway, VNet gateway, mirroring, shortcut or other supported route) and allow authorised override.
4. Create and test supported Fabric connections; store connection IDs and metadata, never plaintext credentials.
5. Implement VNet gateway creation where supported and guided on-premises gateway installation/registration where customer action is required.
6. Discover tables/files/schemas and available metadata after a successful connection.
7. Allow data owners to select the in-scope objects; unselected/sensitive objects remain excluded.
8. Record connection health, last test, owner and audit history.

## Required outputs

- Source catalogue
- Connection setup wizard
- Gateway workflow
- Connection health
- Schema/object discovery
- Scope-selection screen
- Source metadata registry

## Application screens

| ID | Screen | Primary role | Key functions | Integration | Help |
| --- | --- | --- | --- | --- | --- |
| SC-010 | Source Catalogue | Data Owner | Source type, domain, owner, criticality, update frequency | Semantiq + connector discovery | HLP-SRC-001 |
| SC-011 | Connection Setup | Data Platform Admin | Fabric connection fields, credentials, privacy, test | Connections API | HLP-SRC-002 |
| SC-012 | Gateway Setup | Data Platform Admin | Gateway type, VNet/on-prem status, capacity, members | Gateway APIs / guided install | HLP-GWY-001 |
| SC-013 | Schema Discovery | Data Steward | Tables/files, columns, keys, volumes, sensitivity hints | Source/Fabric metadata | HLP-SRC-003 |


## Functional requirements

| ID | Requirement | Priority | Acceptance / Notes |
| --- | --- | --- | --- |
| FR-SRC-001 | Maintain a source catalogue for SQL, Azure SQL, Business Central, Dataverse, SharePoint, Excel, APIs, ERP, CRM, LMS and external databases. | Must | Source type controls connector form. |
| FR-SRC-002 | Recommend ingestion method per source: Pipeline, Dataflow Gen2, Mirroring, Shortcut, Eventstream, gateway or direct upload. | Must | Recommendation shows reason and can be overridden by authorised user. |
| FR-SRC-003 | Create Fabric cloud/on-prem/VNet connections through supported Connections API. | Must | Connection ID and test result stored. |
| FR-SRC-004 | Allow privacy level and credential mode to be configured per connection. | Must | UI validates connector-specific requirements. |
| FR-SRC-005 | Support VNet gateway creation through Fabric API where applicable. | Must | Gateway capacity and VNet fields validated. |
| FR-SRC-006 | For on-premises gateway software installation, provide guided instructions and verify registered gateway after installation. | Must | No attempt to remotely install gateway without customer action. |
| FR-SRC-007 | Discover source tables/files/schemas and record metadata, row-count/size indicators where available, update frequency and owner. | Must | Discovery can be refreshed. |
| FR-SRC-008 | Allow user to select only in-scope source objects before ingestion. | Must | Unselected objects are not ingested. |


## API / automation register

| ID | Operation | Pattern | Mode | Use in Semantiq |
| --- | --- | --- | --- | --- |
| API-007 | Create connection | POST /v1/connections | AUTO / approval | Create cloud/on-prem/VNet Fabric connection. |
| API-008 | Create gateway | POST /v1/gateways | AUTO where supported | VNet/streaming VNet gateway; on-prem software install still guided. |


## In-app help topics

| Topic ID | Help topic |
| --- | --- |
| HLP-SRC-002 | Create and test a Fabric connection |
| HLP-GWY-001 | Configure an on-premises or VNet gateway |


## Acceptance evidence

| ID | Scenario | Pass criterion |
| --- | --- | --- |
| AT-006 | Source connection | SQL/SharePoint/other supported source connection is created/tested and source objects are discovered. |


## Non-functional requirements

| ID | Category | Requirement |
| --- | --- | --- |
| NFR-SEC-01 | Security | Secrets encrypted at rest; no plaintext secrets in logs/database; OWASP-aligned web controls. |
| NFR-SEC-02 | Tenant isolation | All Semantiq records are partitioned and authorised by organisation/tenant context. |
| NFR-PERF-02 | Long operations | Operations over 10 seconds run asynchronously with progress/status; browser request is not held open. |
| NFR-OBS-01 | Observability | Structured logs, metrics, distributed correlation IDs, alerting and searchable audit. |
| NFR-COMP-01 | Privacy | Store minimum customer business data in control plane; prefer metadata and resource IDs. |


## Source onboarding wizard - steps 1-8

| Step | Screen action | Requirement |
| --- | --- | --- |
| 1 | Choose source type | User selects SQL, SharePoint, Excel, Business Central, Dataverse, API, etc. |
| 2 | Define business context | Domain, owner, description, data classification, criticality, expected volume and refresh need. |
| 3 | Choose connection route | Cloud connection, on-premises gateway, VNet gateway, mirroring, shortcut or other supported method. |
| 4 | Enter connector parameters | Server/site/path/database/endpoint and connector-specific parameters. |
| 5 | Enter/authorise credentials | OAuth, Basic, key pair, service principal, managed identity or gateway credentials as supported. |
| 6 | Test connection | Semantiq creates/tests Fabric connection and stores connection ID. |
| 7 | Discover schema | List source objects and profile metadata. |
| 8 | Select scope | Choose in-scope objects and exclude sensitive/unneeded content. |


> Phase boundary: deployment/initial load steps 9-12 belong to Phase 04.

## Verification checklist

- [ ] A supported source can be registered and connection tested.
- [ ] A failed connection returns a diagnosable error without exposing credentials.
- [ ] Schema discovery can be refreshed.
- [ ] Unselected objects are never passed to downstream ingestion plan.
- [ ] On-prem gateway path stops at guided customer action until registration is detected.

## Evidence before user confirmation

- [ ] Automated tests and test-report paths
- [ ] Manual workflow verification
- [ ] Redacted screenshots / request IDs / correlation IDs where applicable
- [ ] Migration/configuration and rollback notes
- [ ] Known issues and risks
- [ ] Confirmation that later-phase scope was not implemented

## User completion gate

Claude Code must ask the user to confirm the phase. Only after the user explicitly sends **`CONFIRM PHASE 03 COMPLETE`** may the phase be marked `CONFIRMED` and the next phase unlocked.

## Claude Code execution rules

1. Read `CLAUDE.md`, the master plan, status file and this phase document.
2. Inspect the existing codebase and do not assume a stack or architecture not present/approved.
3. Create/update `doc/execution/PHASE-03-PLAN.md` and obtain user approval.
4. Implement only current-phase scope.
5. Create/update `doc/execution/PHASE-03-VERIFICATION.md` with evidence.
6. Ask the user for explicit completion confirmation.
7. After confirmation, update `IMPLEMENTATION_STATUS.md` and unlock the next phase.
8. If a Microsoft API/UI/permission differs from the reference, verify current Microsoft documentation, log the decision and obtain approval before changing approach.

## Mandatory v1.3 controls - Source Data Boundary

- Every source registration captures source owner, source region/location, data classification, personal/restricted-data indicators, allowed ingestion destination and retention constraints.
- Connection design must evaluate gateway/VNet/private endpoint/firewall routes and prohibit unapproved public egress for restricted sources.
- Discovery samples must be minimised/redacted; raw credentials and unnecessary source records must not be persisted in the control plane.
- Register each source-to-Fabric flow in `DATA_SOVEREIGNTY_REGISTER.md` before production ingestion.
