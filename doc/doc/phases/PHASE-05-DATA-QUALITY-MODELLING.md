# Phase 05 - Data Quality, Standardisation and Business Modelling

**Reference ID:** P05-DQM
**SRS sections:** 11.1-11.2
**Completion phrase:** `CONFIRM PHASE 05 COMPLETE`

> **Execution gate:** Do not start a later phase until this phase has passed verification and the user has explicitly confirmed completion.

## Objective

Turn raw/ingested data into trusted business-ready structures through profiling, approved quality rules, canonical business entities, business keys and explainable model recommendations.

## Preconditions

- Phase 04 is `CONFIRMED` in `IMPLEMENTATION_STATUS.md`.
- Current branch/tests are in a known passing state.
- User approves the phase implementation plan before material code changes.

## Implementation activities

1. Profile Bronze/Silver data for nulls, duplicates, data types, ranges, distinct values, freshness and referential integrity.
2. Generate suggested data-quality rules with type, scope, severity, threshold, action-on-fail, owner and version.
3. Require data-steward approval before production rules are applied.
4. Implement quality gates that can quarantine/reject or block Silver/Gold promotion.
5. Create canonical entity mappings for customer, product/service, employee/user, learner, transaction, organisation unit and calendar where applicable.
6. Create/commonise business keys for multi-source joins and store rationale/test results.
7. Generate and version transformations using the approved execution pattern.
8. Generate fact/dimension and star-schema candidates with confidence/rationale and allow accept/edit/reject.
9. Implement model/version history and downstream impact analysis.
10. Expose data-quality scorecards to Operations Monitor.

## Required outputs

- Profiling results
- DQ rule catalogue
- Quality gates
- Canonical mappings
- Business keys
- Transformation versions
- Fact/dimension recommendations
- DQ scorecards

## Application screens

| ID | Screen | Primary role | Key functions | Integration | Help |
| --- | --- | --- | --- | --- | --- |
| SC-016 | Data Quality | Data Steward | Profile, null/duplicate/range rules, severity, thresholds | Generated notebook/pipeline rules | HLP-DQ-001 |
| SC-017 | Business Entity Mapping | Data Steward/BA | Entity, business key, source fields, canonical fields | Semantiq metadata + model generation | HLP-MDL-001 |


## Functional requirements

| ID | Requirement | Priority | Acceptance / Notes |
| --- | --- | --- | --- |
| FR-DQ-003 | Profile ingested data for nulls, duplicates, data types, ranges, distinct values and referential integrity. | Must | Profiling produces per-column metrics. |
| FR-DQ-004 | Suggest cleansing rules for duplicate removal, null handling, code/date normalisation and invalid-record handling. | Must | Rules require steward approval before production. |
| FR-DQ-005 | Support rule severity (Info/Warning/Error) and pass threshold. | Must | Quality gate can block Gold promotion. |
| FR-DQ-006 | Standardise canonical business entities such as Customer, Employee, Product, Course, Learner, Supplier and Transaction. | Must | Source fields map to canonical entity fields. |
| FR-DQ-007 | Create/commonise business keys required to join multi-source records. | Must | Key strategy documented and testable. |
| FR-DQ-008 | Generate transformation implementations using Dataflow Gen2, Notebook/Spark, SQL or Pipeline according to selected pattern. | Must | Generated artifact is versioned. |
| FR-DQ-009 | Generate fact/dimension recommendations and star-schema candidates. | Must | User can accept, edit or reject recommendation. |
| FR-DQ-010 | Maintain full model/version history and impact analysis. | Must | Changes show downstream tables/models/agents affected. |
| FR-DQ-012 | Expose data-quality scorecards to the Operations Monitor. | Must | Score by source/domain/table with trend. |


## In-app help topics

| Topic ID | Help topic |
| --- | --- |
| HLP-DQ-001 | Review and approve data-quality rules |


## Acceptance evidence

| ID | Scenario | Pass criterion |
| --- | --- | --- |
| AT-008 | Quality gate | Known duplicate/null issue is detected; approved rule blocks Gold promotion until resolved or waived. |


## Non-functional requirements

| ID | Category | Requirement |
| --- | --- | --- |
| NFR-VER-01 | Versioning | Generated Fabric definitions, quality rules, semantic configuration and Data Agent definitions are versioned. |
| NFR-OBS-01 | Observability | Structured logs, metrics, distributed correlation IDs, alerting and searchable audit. |
| NFR-COMP-01 | Privacy | Store minimum customer business data in control plane; prefer metadata and resource IDs. |


## Data-quality rule model

| Rule property | Examples / behaviour |
| --- | --- |
| Rule type | Not Null, Unique, Range, Pattern, Referential Integrity, Allowed Values, Freshness, Row Count, Duplicate, Custom SQL/Spark. |
| Scope | Column, table, entity or ingestion batch. |
| Severity | Info, Warning, Error, Critical. |
| Threshold | 100% pass, maximum failure %, min/max count or custom expression. |
| Action on fail | Log only, quarantine/reject, stop Silver promotion, stop Gold promotion, alert owner. |
| Owner | Data steward or business owner. |
| Version | Effective date, change reason, approver. |


## Canonical entities

| Canonical entity | Typical source aliases | Typical key / relationships |
| --- | --- | --- |
| Customer | Account, Client, Organisation, Buyer | Customer ID; links to sales/orders/payments/cases. |
| Product / Service | SKU, Item, Offering, Course | Product/Service ID; links to transactions and pricing. |
| Employee / User | Staff, Agent, Consultant, Faculty | Employee/User ID; links to departments and activities. |
| Learner | Student, Participant, Candidate | Learner ID; links to courses, enrolments, attendance, assessments. |
| Transaction | Sale, Invoice, Payment, Order, Booking | Transaction ID; links to customer, product and date. |
| Organisation Unit | Company, Department, Region, Business Unit | Org Unit ID; hierarchy and security scope. |
| Calendar | Date, Period, Fiscal Period | Date key; shared date dimension. |


## Verification checklist

- [ ] Known duplicate/null defects are detected by test data.
- [ ] Critical rule can block Gold promotion.
- [ ] Approved mapping is traceable from source field to canonical field.
- [ ] Rejected model recommendation is not deployed.
- [ ] Version history shows what changed, why and who approved it.

## Evidence before user confirmation

- [ ] Automated tests and test-report paths
- [ ] Manual workflow verification
- [ ] Redacted screenshots / request IDs / correlation IDs where applicable
- [ ] Migration/configuration and rollback notes
- [ ] Known issues and risks
- [ ] Confirmation that later-phase scope was not implemented

## User completion gate

Claude Code must ask the user to confirm the phase. Only after the user explicitly sends **`CONFIRM PHASE 05 COMPLETE`** may the phase be marked `CONFIRMED` and the next phase unlocked.

## Claude Code execution rules

1. Read `CLAUDE.md`, the master plan, status file and this phase document.
2. Inspect the existing codebase and do not assume a stack or architecture not present/approved.
3. Create/update `doc/execution/PHASE-05-PLAN.md` and obtain user approval.
4. Implement only current-phase scope.
5. Create/update `doc/execution/PHASE-05-VERIFICATION.md` with evidence.
6. Ask the user for explicit completion confirmation.
7. After confirmation, update `IMPLEMENTATION_STATUS.md` and unlock the next phase.
8. If a Microsoft API/UI/permission differs from the reference, verify current Microsoft documentation, log the decision and obtain approval before changing approach.

## Mandatory v1.3 controls - Privacy-Aware Data Quality

- Profiling/anomaly previews must minimise personal/restricted values; use masked/tokenised samples where practical.
- Data cleansing and standardisation must not weaken classification, retention or access restrictions.
- Pseudonymisation/tokenisation rules, where used, are versioned validation/transformation rules with tests and lineage.
- Every generated entity/field relationship updates `DATA_CONTEXT_REGISTER.md` and applicable `VALIDATION_RULES_REGISTER.md` entries.
