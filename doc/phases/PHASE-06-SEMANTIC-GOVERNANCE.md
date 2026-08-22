# Phase 06 - Semantic Model, Security and Governance

**Reference ID:** P06-SEM
**SRS sections:** 11.3 and 13
**Completion phrase:** `CONFIRM PHASE 06 COMPLETE`

> **Execution gate:** Do not start a later phase until this phase has passed verification and the user has explicitly confirmed completion.

## Objective

Generate a governed Power BI semantic layer from approved Gold data, establish certified business meaning, implement row/object-level restrictions and prove that security and lineage are enforced before AI use.

## Preconditions

- Phase 05 is `CONFIRMED` in `IMPLEMENTATION_STATUS.md`.
- Current branch/tests are in a known passing state.
- User approves the phase implementation plan before material code changes.

## Implementation activities

1. Generate a semantic model from approved Gold data, preferring star-schema relationships and Direct Lake where policy/capability allows.
2. Generate explicit measures/KPIs with expression, format, grain, business definition, owner and approval state.
3. Map technical names to business-friendly names while preserving source lineage.
4. Generate/edit/version descriptions, synonyms and business glossary links.
5. Implement semantic-model certification lifecycle: Draft -> Reviewed -> Approved -> Published -> Deprecated.
6. Implement RLS and object/column restrictions with explicit principal mapping.
7. Provide test-as-role/user workflow and block production AI source approval until security tests pass.
8. Capture sensitivity/classification and governance owner/status/effective date.
9. Detect breaking model changes, show impact on agents/reports and require revalidation.
10. Deploy only approved model definitions and retain version/lineage evidence.

## Required outputs

- Semantic model definition
- Measures/KPIs
- Business glossary
- Descriptions/synonyms
- Security policies
- Certification workflow
- Security test evidence
- Lineage and impact analysis

## Application screens

| ID | Screen | Primary role | Key functions | Integration | Help |
| --- | --- | --- | --- | --- | --- |
| SC-018 | Semantic Model Studio | Semantic Owner | Facts/dimensions, relationships, measures, names, descriptions | Semantic Model API | HLP-SEM-001 |
| SC-019 | Security & Governance | Security Admin | RLS/OLS, sensitivity, role mappings, access review | Model + workspace APIs | HLP-SEC-001 |


## Functional requirements

| ID | Requirement | Priority | Acceptance / Notes |
| --- | --- | --- | --- |
| FR-SEM-001 | Generate a Power BI semantic model from approved Gold data. | Must | Model definition deploys successfully via supported API. |
| FR-SEM-002 | Prefer a star schema with explicit fact/dimension relationships where appropriate. | Must | Relationship cardinality and direction are reviewable. |
| FR-SEM-003 | Prefer Direct Lake where appropriate and supported by the selected architecture. | Should | Mode is selected through policy and capability checks. |
| FR-SEM-004 | Generate explicit measures rather than relying on implicit aggregation for certified KPIs. | Must | Measure list has definition, format, owner and approval state. |
| FR-SEM-005 | Map technical field names to business-friendly names. | Must | Original source name remains traceable. |
| FR-SEM-006 | Generate table/column/measure descriptions and business synonyms. | Must | Metadata can be edited and versioned. |
| FR-SEM-007 | Maintain a business glossary linked to semantic-model objects. | Must | Term shows definition, owner, status and linked fields/measures. |
| FR-SEM-008 | Support RLS and object/column-level restrictions through semantic-model design and approved role mappings. | Must | Security tests prove different user views. |
| FR-SEM-009 | Support sensitivity/classification metadata and governance status where underlying platform capability permits. | Must | Classification displayed in catalogue. |
| FR-SEM-010 | Provide model certification workflow: Draft -> Reviewed -> Approved -> Published -> Deprecated. | Must | Only approved model can be production AI source. |
| FR-SEM-011 | Detect breaking semantic-model changes and block uncontrolled promotion. | Must | Impact list shown before release. |
| FR-SEM-012 | Maintain verified KPI definitions to prevent conflicting calculations. | Must | Each KPI has owner, formula/measure, grain and business definition. |
| FR-SEM-013 | Support refresh/redeploy after approved source or model changes. | Must | Change creates new version and regression run. |
| FR-SEM-014 | Record lineage from source fields to semantic model objects and AI sources. | Must | User can trace an answerable metric back to source lineage. |


## API / automation register

| ID | Operation | Pattern | Mode | Use in Semantiq |
| --- | --- | --- | --- | --- |
| API-013 | Create semantic model | POST /v1/workspaces/{workspaceId}/semanticModels | AUTO | Requires definition; version before deployment. |


## In-app help topics

| Topic ID | Help topic |
| --- | --- |
| HLP-SEM-001 | Review the generated semantic model |
| HLP-SEC-001 | Configure and test RLS/OLS |


## Acceptance evidence

| ID | Scenario | Pass criterion |
| --- | --- | --- |
| AT-009 | Semantic model | Generated model contains approved relationships, explicit measures, descriptions and security configuration. |
| AT-011 | Security | Restricted test user cannot retrieve out-of-scope data through semantic model or agent. |


## Non-functional requirements

| ID | Category | Requirement |
| --- | --- | --- |
| NFR-SEC-01 | Security | Secrets encrypted at rest; no plaintext secrets in logs/database; OWASP-aligned web controls. |
| NFR-SEC-02 | Tenant isolation | All Semantiq records are partitioned and authorised by organisation/tenant context. |
| NFR-VER-01 | Versioning | Generated Fabric definitions, quality rules, semantic configuration and Data Agent definitions are versioned. |
| NFR-COMP-01 | Privacy | Store minimum customer business data in control plane; prefer metadata and resource IDs. |


## Security baseline

| ID | Requirement |
| --- | --- |
| SEC-001 | All external traffic uses TLS 1.2+; HSTS for browser application. |
| SEC-002 | Secrets and certificates are stored in approved vault/KMS and encrypted at rest. |
| SEC-003 | Browser never receives automation client secret or Fabric bearer token intended for backend service use. |
| SEC-004 | Least-privilege roles are applied independently in Semantiq, Entra, Azure and Fabric. |
| SEC-005 | Privileged operations require step-up confirmation and an auditable approver where configured. |
| SEC-006 | Audit records are immutable to standard administrators and retained per customer policy. |
| SEC-007 | Customer data-plane IDs and metadata are tenant-isolated in Semantiq; no cross-tenant lookup by default. |
| SEC-008 | RLS/OLS security tests are mandatory before production AI publication. |
| SEC-009 | Credentials must be rotatable without application downtime. |
| SEC-010 | Sensitive configuration values are masked in UI, logs and support exports. |
| SEC-011 | Semantiq support access is time-bound, customer-approved and auditable when support access to tenant context is required. |
| SEC-012 | Destructive Fabric actions require explicit confirmation and should not be part of default automated onboarding. |


> RLS/OLS security tests are mandatory before a semantic model can be approved as a production AI source.

## AI consumption technology reference

> **Mandatory reference for downstream AI use:** [`AI_CONVERSATIONAL_TECHNOLOGY_GUIDE.md`](../reference/AI_CONVERSATIONAL_TECHNOLOGY_GUIDE.md). Phase 06 must produce a provider-neutral, governed semantic contract. Do not tailor semantic definitions or security rules to compensate for weaknesses of a particular LLM/agent framework. Fabric Data Agent is the default structured-data conversational engine for evaluation in Phase 07, but the approved semantic model must remain consumable by other approved runtimes without bypassing RLS/OLS or certification.

## Verification checklist

- [ ] Semantic model deploys with approved relationships and measures.
- [ ] Restricted test user cannot access protected objects/data.
- [ ] Every certified KPI has owner, definition, formula/measure and grain.
- [ ] Breaking model change marks dependent AI components Revalidation Required.
- [ ] Lineage can trace a semantic object back to Gold/source fields.

## Evidence before user confirmation

- [ ] Automated tests and test-report paths
- [ ] Manual workflow verification
- [ ] Redacted screenshots / request IDs / correlation IDs where applicable
- [ ] Migration/configuration and rollback notes
- [ ] Known issues and risks
- [ ] Confirmation that later-phase scope was not implemented

## User completion gate

Claude Code must ask the user to confirm the phase. Only after the user explicitly sends **`CONFIRM PHASE 06 COMPLETE`** may the phase be marked `CONFIRMED` and the next phase unlocked.

## Claude Code execution rules

1. Read `CLAUDE.md`, the master plan, status file and this phase document.
2. Inspect the existing codebase and do not assume a stack or architecture not present/approved.
3. Create/update `doc/execution/PHASE-06-PLAN.md` and obtain user approval.
4. Implement only current-phase scope.
5. Create/update `doc/execution/PHASE-06-VERIFICATION.md` with evidence.
6. Ask the user for explicit completion confirmation.
7. After confirmation, update `IMPLEMENTATION_STATUS.md` and unlock the next phase.
8. If a Microsoft API/UI/permission differs from the reference, verify current Microsoft documentation, log the decision and obtain approval before changing approach.

## Mandatory v1.3 controls - Governance Enforcement

- Implement policy-driven RLS/OLS/workspace access and verify using non-admin identities.
- Surface/apply Microsoft Purview sensitivity labels and DLP/protection policies where customer licensing/policy requires them; do not claim enforcement where the Microsoft capability does not support the item or automation path.
- Verify CMK status when required and record unsupported-item constraints before enabling CMK on a workspace.
- Maintain lineage and certified KPI/model ownership, and prevent restricted fields from being exposed to semantic/AI layers unless approved.
- Add HLP-GOV-005 and include DLP/label/CMK evidence in verification.
