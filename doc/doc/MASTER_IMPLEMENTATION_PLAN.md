# Semantiq Phased Implementation Master Plan

**Baseline:** SemantIQ Software Requirements Specification v0.3 repository-aligned baseline

> This file controls implementation order. The SRS remains the requirements source of truth. If a phase reference conflicts with the SRS, stop and ask for a baseline decision.


## Repository Alignment Baseline v1.3

- Canonical documentation root is `doc/`, matching the existing repository and design-system links.
- `IMPLEMENTATION_STATUS.md` stays at repository root.
- Current hosted deployment is one customer organisation/Entra tenant per SemantIQ application instance. The architecture remains multi-tenant-ready, but shared multi-customer SaaS tenancy is not enabled by default.
- `main` is the only long-lived Git branch and merging/pushing to it triggers the live deployment. Short-lived phase/work branches may be used for pull requests; there is no permanent Git DEV/TEST/PROD branch chain.
- Fabric DEV/TEST/PROD workspaces described in later phases are Fabric environment topology, not Git branches.
- Runtime DB credentials remain server-side; deployment credentials required by CI remain GitHub Environment/Actions secrets.

## Claude Code usage

1. Read root `CLAUDE.md` first.
2. Read `IMPLEMENTATION_STATUS.md` and work on the one active/unlocked phase only.
3. Read this master plan and the current phase reference.
4. Inspect the existing repository before planning. The approved application baseline is Laravel 13/PHP 8.5, React 19, MySQL on cPanel, modular monolith. Verify the scaffold before relying on it and do not replace the stack without approval.
5. Create/update the current phase plan under `doc/execution/` and get user approval.
6. Implement only current phase scope, then create a verification report.
7. Ask the user for the exact completion confirmation phrase.
8. Only after explicit confirmation update the status file and unlock the next phase.

## Phase gate states

| State | Meaning |
| --- | --- |
| LOCKED | Previous phase not user-confirmed. |
| READY_FOR_PLAN | Claude may inspect and propose a plan. |
| PLAN_PENDING_APPROVAL | Wait for user approval of phase plan. |
| IN_PROGRESS | Approved implementation underway. |
| VERIFYING | Run tests/manual validation. |
| AWAITING_USER_CONFIRMATION | Evidence presented; next phase stays locked. |
| CONFIRMED | User explicitly confirmed completion. |
| BLOCKED | Dependency/API/security/requirement issue requires resolution. |


## Phase map

| Phase | Ref | Title | Reference | Completion phrase |
| --- | --- | --- | --- | --- |
| 00 | P00-FND | Engineering Foundation and Control Plane Skeleton | [`PHASE-00-FOUNDATION.md`](phases/PHASE-00-FOUNDATION.md) | `CONFIRM PHASE 00 COMPLETE` |
| 01 | P01-IDN | Tenant Onboarding, SSO and Fabric Automation Identity | [`PHASE-01-IDENTITY.md`](phases/PHASE-01-IDENTITY.md) | `CONFIRM PHASE 01 COMPLETE` |
| 02 | P02-FAB | Fabric Readiness, Capacity and Workspace Provisioning | [`PHASE-02-FABRIC-CORE.md`](phases/PHASE-02-FABRIC-CORE.md) | `CONFIRM PHASE 02 COMPLETE` |
| 03 | P03-SRC | Source Connectivity, Gateway and Schema Discovery | [`PHASE-03-SOURCE-CONNECTIVITY.md`](phases/PHASE-03-SOURCE-CONNECTIVITY.md) | `CONFIRM PHASE 03 COMPLETE` |
| 04 | P04-ING | Ingestion, Lakehouse and Medallion Data Foundation | [`PHASE-04-INGESTION-LAKEHOUSE.md`](phases/PHASE-04-INGESTION-LAKEHOUSE.md) | `CONFIRM PHASE 04 COMPLETE` |
| 05 | P05-DQM | Data Quality, Standardisation and Business Modelling | [`PHASE-05-DATA-QUALITY-MODELLING.md`](phases/PHASE-05-DATA-QUALITY-MODELLING.md) | `CONFIRM PHASE 05 COMPLETE` |
| 06 | P06-SEM | Semantic Model, Security and Governance | [`PHASE-06-SEMANTIC-GOVERNANCE.md`](phases/PHASE-06-SEMANTIC-GOVERNANCE.md) | `CONFIRM PHASE 06 COMPLETE` |
| 07 | P07-AI | AI Readiness, Fabric Data Agent and Validation Centre | [`PHASE-07-AI-DATA-AGENT.md`](phases/PHASE-07-AI-DATA-AGENT.md) | `CONFIRM PHASE 07 COMPLETE` |
| 08 | P08-OPS | Deployment, Operations, Help Centre and Lifecycle Management | [`PHASE-08-DEPLOYMENT-OPERATIONS.md`](phases/PHASE-08-DEPLOYMENT-OPERATIONS.md) | `CONFIRM PHASE 08 COMPLETE` |
| 09 | P09-GO | End-to-End UAT, Production Go-Live and Customer Handover | [`PHASE-09-UAT-GOLIVE.md`](phases/PHASE-09-UAT-GOLIVE.md) | `CONFIRM PHASE 09 COMPLETE AND BASELINE ACCEPTED` |


## Rules that apply to every phase

- SRS requirement IDs/screens/APIs/help topics/acceptance scenarios must be traceable in plan and verification evidence.
- Public API automation is preferred only when the API is current and supported; preview/high-privilege operations remain feature-flagged or guided as the SRS requires.
- Never commit secrets/tokens/certificates/private keys.
- Do not advance phases automatically after tests pass.
- User confirmation is a hard gate.
- A Microsoft API or portal difference must be verified against current Microsoft documentation and recorded as a decision before the baseline approach changes.

## AI and Conversational Technology Decision Rule

Any phase involving AI or conversational application development must use [`AI_CONVERSATIONAL_TECHNOLOGY_GUIDE.md`](reference/AI_CONVERSATIONAL_TECHNOLOGY_GUIDE.md) as a mandatory architecture reference.

The default technology evaluation order for Semantiq is:

1. **Fabric Data Agent** for governed structured business-data Q&A.
2. **Copilot Studio** for low-code Microsoft 365/Teams/web conversational channels.
3. **Microsoft Agent Framework + Microsoft Foundry Agent Service** may be evaluated for custom coded agent workflows, but because the confirmed primary application is Laravel/PHP/React, a .NET/Python runtime is a separately deployable sidecar/service and requires explicit architecture approval.
4. **Foundry IQ / Azure AI Search** for enterprise unstructured RAG and cited knowledge.
5. **LangGraph / LlamaIndex / vLLM / Ollama** as open-source alternatives where portability, self-hosting or non-Microsoft AI is a requirement.

This is not permission to deploy every technology or add another runtime to the Laravel/PHP/React product. Claude Code must create `doc/execution/AI-TECHNOLOGY-DECISION.md`, compare the specific options, re-verify current product status and obtain user approval before implementing the selected AI stack. Deterministic Fabric provisioning remains API/workflow-driven, not LLM-driven.

## Mandatory Cross-Cutting Standard - Data Protection, Sovereignty & Engineering Context

Every phase must comply with `doc/reference/DATA_PROTECTION_SOVEREIGNTY_STANDARD.md`. The Semantiq control plane must capture the customer's approved storage/processing geographies before production provisioning; cross-geo processing/storage/AI-history settings default OFF and require explicit approval when needed.

The implementation must continuously maintain the engineering context under `doc/context/`: code purpose/dependencies, data classification/lineage/residency/retention, validation rules, typed configuration, sovereignty flows and security/privacy decisions. Phase verification fails if the implementation changes while these registers remain stale.

### Required phase-gate additions
1. Plan identifies data classifications, approved regions and security/privacy implications.
2. Plan lists context-register entries that will be created/changed.
3. Verification proves actual regions/settings/network/encryption/permissions match policy.
4. Verification proves no restricted data/secrets leaked into Git/logs.
5. User completion confirmation remains mandatory after evidence is presented.
