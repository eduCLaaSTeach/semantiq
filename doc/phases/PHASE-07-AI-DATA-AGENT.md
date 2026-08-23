# Phase 07 - AI Readiness, Fabric Data Agent and Validation Centre

**Reference ID:** P07-AI
**SRS sections:** 12
**Completion phrase:** `CONFIRM PHASE 07 COMPLETE`

> **Execution gate:** Do not start a later phase until this phase has passed verification and the user has explicitly confirmed completion.

## Objective

Prepare only approved governed data for AI, create/version/configure a Fabric Data Agent, run ground-truth and security regression tests, and publish only after the required validation and user approval.

## Preconditions

- Phase 06 is `CONFIRMED` in `IMPLEMENTATION_STATUS.md`.
- Current branch/tests are in a known passing state.
- User approves the phase implementation plan before material code changes.

## Implementation activities

1. Implement AI Readiness workspace to select approved tables/columns/measures and approved Lakehouse/Warehouse sources.
2. Generate editable/versioned AI instructions from approved glossary, KPI definitions and business rules.
3. Maintain synonyms, restrictions, answer style and verified questions.
4. Maintain a ground-truth test pack containing question, filters, expected result/logic, tolerance and data/model version.
5. Create Fabric Data Agent and store the item ID.
6. Get/back up the current Data Agent definition before changes; generate/update versioned public definition where supported.
7. Configure approved data sources, instructions and examples according to supported Data Agent definition parts.
8. Run validation across correctness, filters, time intelligence, ranking, follow-up context, security and unsupported questions.
9. Diagnose failure category as data, relationship, measure, semantic definition, instruction, security or unsupported scope.
10. Publish only after validation score threshold, security checks and explicit release approval.
11. Expose published agent in Semantiq conversational UI and maintain integration guidance for Teams/Copilot Studio/web channels.
12. Mark agent Revalidation Required whenever dependent source/model/security/instruction changes.

## Required outputs

- AI-approved scope
- AI instructions
- Verified questions
- Ground-truth pack
- Versioned Data Agent definition
- Validation reports
- Published Data Agent
- Conversational handoff status

## Application screens

| ID | Screen | Primary role | Key functions | Integration | Help |
| --- | --- | --- | --- | --- | --- |
| SC-020 | AI Readiness | AI Owner | Approved tables/columns/measures, instructions, synonyms, verified questions | Definition/config generation | HLP-AI-001 |
| SC-021 | Fabric Data Agent | AI Owner | Agent name, sources, instructions, examples, publish state | DataAgent APIs | HLP-AGT-001 |
| SC-022 | Validation Centre | Owners/QA | Ground truth, security tests, data checks, regression score | Jobs + comparison engine | HLP-VAL-001 |


## AI and Conversational AI Technology Selection Gate

> **Mandatory before material AI code:** Read [`AI_CONVERSATIONAL_TECHNOLOGY_GUIDE.md`](../reference/AI_CONVERSATIONAL_TECHNOLOGY_GUIDE.md) and create `doc/execution/AI-TECHNOLOGY-DECISION.md` using the supplied template. Present the technology recommendation to the user and wait for approval.

For the Semantiq baseline, use this evaluation order:

| Capability | Microsoft-first recommendation | Open-source alternative |
| --- | --- | --- |
| Governed structured data Q&A | Fabric Data Agent / Fabric IQ | Custom MAF or LangGraph agent calling governed Fabric interfaces |
| Teams/M365/low-code conversation | Copilot Studio + Fabric Data Agent | Custom web/Teams integration with agent runtime |
| Custom coded agent/orchestration | Microsoft Agent Framework + Foundry Agent Service | LangGraph |
| Unstructured enterprise RAG | Foundry IQ / Azure AI Search | LlamaIndex + approved vector/search store |
| Self-hosted model inference | Microsoft-hosted Foundry model where allowed | vLLM; Ollama for local/POC |
| Custom streamed web agent UI | Existing Semantiq UI + backend API; optionally MAF + AG-UI | LangGraph/custom SSE protocol |

**Implementation rule:** Do not replace the Fabric semantic layer with prompt logic. Do not use AI to execute deterministic Fabric provisioning. Multi-agent orchestration must be justified; start with the simplest single-agent/tool architecture. Model/vendor/framework choices are not considered approved until the user accepts the technology decision record.

## Functional requirements

| ID | Requirement | Priority | Acceptance / Notes |
| --- | --- | --- | --- |
| FR-AI-001 | Allow AI owner to select the subset of tables, columns and measures exposed to AI. | Must | Unselected objects are not included in generated agent configuration. |
| FR-AI-002 | Generate AI instructions from approved glossary, KPI definitions and business rules. | Must | Instructions are editable and versioned. |
| FR-AI-003 | Maintain verified questions/answers and ground-truth expected results. | Must | Test pack supports simple and complex questions. |
| FR-AI-004 | Create Fabric Data Agent via POST /v1/workspaces/{workspaceId}/dataAgents. | Must | Data Agent ID stored and LRO handled. |
| FR-AI-005 | Generate/update Data Agent public definition using supported definition parts. | Must | Definition version is stored before update. |
| FR-AI-006 | Support Data Agent data-source configuration, instructions and few-shot examples in the public definition where supported. | Must | Config can be round-tripped with Get Definition. |
| FR-AI-007 | Publish Data Agent only after validation and approval. | Must | Publish action requires release gate. |
| FR-AI-008 | Run simple, comparative, trend, ranking, date-filter and follow-up test questions. | Must | Results stored with score and evidence. |
| FR-AI-009 | Diagnose likely cause of wrong answer as data, relationship, measure, semantic definition, instruction, security or unsupported question. | Must | Validation output routes issue to responsible module. |
| FR-AI-010 | Validate Data Agent under different user/security contexts where technically supported. | Must | RLS/OLS leakage test must pass before production. |
| FR-AI-011 | Expose published agent to Semantiq conversational UI and provide integration guidance for Teams/Copilot Studio/web channels. | Should | Channel status and handoff guide recorded. |
| FR-AI-012 | Maintain Data Agent lifecycle and regression tests whenever source/model/instruction changes. | Must | Change automatically marks agent as Revalidation Required. |


## API / automation register

| ID | Operation | Pattern | Mode | Use in Semantiq |
| --- | --- | --- | --- | --- |
| API-014 | Create Data Agent | POST /v1/workspaces/{workspaceId}/dataAgents | AUTO | Create Data Agent; supports LRO. |
| API-015 | Get Data Agent definition | POST .../dataAgents/{dataAgentId}/getDefinition | AUTO | Backup/synchronise definition. |
| API-016 | Update Data Agent definition | POST .../dataAgents/{dataAgentId}/updateDefinition | AUTO / approval | Deploy approved public definition. |
| API-017 | Publish Data Agent | Data Agent publish endpoint | AUTO / release approval | Publish staging configuration after validation. |


## In-app help topics

| Topic ID | Help topic |
| --- | --- |
| HLP-AI-001 | Prepare approved data and business instructions for AI |
| HLP-AGT-001 | Create, configure, validate and publish a Fabric Data Agent |


## Acceptance evidence

| ID | Scenario | Pass criterion |
| --- | --- | --- |
| AT-010 | Data Agent | Semantiq creates Data Agent, deploys definition, runs test pack and publishes after approval. |
| AT-011 | Security | Restricted test user cannot retrieve out-of-scope data through semantic model or agent. |


## Non-functional requirements

| ID | Category | Requirement |
| --- | --- | --- |
| NFR-SEC-01 | Security | Secrets encrypted at rest; no plaintext secrets in logs/database; OWASP-aligned web controls. |
| NFR-VER-01 | Versioning | Generated Fabric definitions, quality rules, semantic configuration and Data Agent definitions are versioned. |
| NFR-OBS-01 | Observability | Structured logs, metrics, distributed correlation IDs, alerting and searchable audit. |


## AI readiness model

| Configuration | Requirement |
| --- | --- |
| AI scope | Only approved semantic tables/columns/measures or approved Lakehouse/Warehouse sources are exposed. |
| Business instructions | Generated from glossary and business rules, then reviewed by AI owner. |
| Synonyms | Business vocabulary, abbreviations, local terminology and common misspellings. |
| Verified questions | High-value management questions with approved interpretation and expected result/logic. |
| Ground truth set | Regression test pack with question, filters, expected result, tolerance and source snapshot/version. |
| Restrictions | Topics/data the agent must not answer, including unavailable or restricted fields. |
| Answer style | Narrative, table, chart suggestion, units, date context and source/lineage citation behaviour. |


## Data Agent operations

| Operation | Fabric API pattern | Semantiq behaviour |
| --- | --- | --- |
| Create agent | POST /v1/workspaces/{workspaceId}/dataAgents | Create shell and store Data Agent ID. |
| Get definition | POST .../dataAgents/{dataAgentId}/getDefinition | Backup/inspect current public definition before changes. |
| Update definition | POST .../dataAgents/{dataAgentId}/updateDefinition | Deploy generated configuration and poll LRO if 202. |
| Publish | Data Agent publish operation | Publish validated staging configuration after approval. |
| List/Get | Data Agent item APIs | Synchronise external changes and detect drift. |


## Validation dimensions

| Test dimension | Example | Pass rule |
| --- | --- | --- |
| Data correctness | Total revenue last month | Exact or defined numerical tolerance. |
| Filter correctness | Show only Singapore | No out-of-scope records. |
| Time intelligence | Compare with same quarter last year | Correct period and measure. |
| Ranking | Top 5 customers by margin | Correct order and tie handling. |
| Follow-up context | Which customer caused that change? | Retains prior period/filter context. |
| Security | Restricted user asks for payroll | No restricted data returned. |
| Unsupported question | Question outside approved scope | Agent states limitation instead of fabricating. |


## Verification checklist

- [ ] Unapproved semantic objects are absent from generated AI configuration.
- [ ] Data Agent definition can be backed up and round-tripped.
- [ ] Ground-truth tests store evidence and score.
- [ ] Security regression passes for restricted user context where technically supported.
- [ ] Publish is impossible before required validation/approval.

## Evidence before user confirmation

- [ ] Automated tests and test-report paths
- [ ] Manual workflow verification
- [ ] Redacted screenshots / request IDs / correlation IDs where applicable
- [ ] Migration/configuration and rollback notes
- [ ] Known issues and risks
- [ ] Confirmation that later-phase scope was not implemented

## User completion gate

Claude Code must ask the user to confirm the phase. Only after the user explicitly sends **`CONFIRM PHASE 07 COMPLETE`** may the phase be marked `CONFIRMED` and the next phase unlocked.

## Claude Code execution rules

1. Read `CLAUDE.md`, the master plan, status file and this phase document.
2. Inspect the existing codebase and do not assume a stack or architecture not present/approved.
3. Create/update `doc/execution/PHASE-07-PLAN.md` and obtain user approval.
4. Implement only current-phase scope.
5. Create/update `doc/execution/PHASE-07-VERIFICATION.md` with evidence.
6. Ask the user for explicit completion confirmation.
7. After confirmation, update `IMPLEMENTATION_STATUS.md` and unlock the next phase.
8. If a Microsoft API/UI/permission differs from the reference, verify current Microsoft documentation, log the decision and obtain approval before changing approach.

## Mandatory v1.3 controls - AI Data Sovereignty

- Before enabling Fabric Data Agent/Copilot/Foundry/open-source runtime, document model/runtime region, prompt/grounding data flows, embedding/vector location if any, and conversation-history location/retention.
- Cross-geo Azure OpenAI/Fabric processing, storage and conversation-history tenant/capacity settings remain OFF by default. If required, create an AI technology decision + sovereignty exception and obtain explicit user/customer approval before enabling.
- If the approved geography cannot be met by a Microsoft-hosted option, evaluate a region-compatible/self-hosted open-source model runtime before requesting cross-border processing.
- AI telemetry must redact sensitive prompt content; never include secrets. Grounding sources are allowlisted and security-trimmed.
- Add HLP-AI-SOV-006. Sovereignty, privacy and prompt-data evidence are part of the Phase 07 exit gate.
