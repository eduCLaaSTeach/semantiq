# Semantiq Data Protection, Data Sovereignty & Secure Configuration Standard

**Document ID:** REF-DPS-001  
**Version:** 1.1 (SemantIQ package v1.3)  
**Status:** Mandatory engineering reference

## Repository-Specific Baseline v1.3

- Current hosted deployment is single-customer per application instance, but organisation/tenant context remains mandatory for customer-owned configuration and audit metadata so future multi-tenant enablement does not weaken isolation.
- The repository currently records a seven-year retention policy for operational data, audit/compliance logs and backups. Treat this as a configurable project-policy baseline, not a universal legal requirement or hard-coded constant.
- **The Singapore PDPA applies to this deployment**, confirmed 25 August 2026 (DEC-002). Apply privacy-by-design and sovereignty controls regardless of regime, and re-confirm applicability for any future customer whose hosting, sector or contracts differ. DEC-002 records four obligations the PDPA creates that this application does not yet meet: access and correction requests, breach notification within three calendar days, a per-category retention basis rather than one number, and a designated privacy contact that is currently optional.
- Runtime application/database credentials remain server-side/secret-manager controlled. GitHub secrets are only for credentials actually needed by approved CI/CD workflows.


## 1. Purpose

This standard is mandatory for every Semantiq phase, pull request, API integration, data flow and AI feature. It prevents security, privacy and sovereignty from being treated as a post-build activity. Every implementation plan must determine **what data is touched, where it is processed/stored, who can access it, what validation applies, what configuration controls it, and what evidence proves the controls work**.

Semantiq must not claim regulatory compliance solely because these controls are implemented. Customer legal, regulatory and contractual requirements remain inputs to the approved policy profile.

## 2. Non-negotiable engineering rules

1. **Customer-approved data boundary first.** Capture the customer's approved geography/region and prohibited geographies before provisioning Fabric or enabling AI.
2. **No silent cross-geo processing or storage.** Cross-geo Fabric/AI settings default to OFF. Any requirement to enable them is a sovereignty exception requiring a documented impact assessment and explicit customer approval.
3. **Data minimisation.** The Semantiq control plane stores metadata, resource IDs, configuration and audit evidence by default; it does not retain business data samples unless the feature requires them and the customer policy permits it.
4. **Tenant isolation.** Every customer-owned row, workflow, credential reference, audit event, cache entry and job must be scoped by organisation/tenant and authorised on every server-side access path.
5. **Encryption.** TLS is required in transit. Fabric data is encrypted at rest by Microsoft-managed keys; where policy requires stronger customer control, evaluate Fabric workspace customer-managed keys (CMK) and Azure Key Vault/Managed HSM.
6. **Private connectivity when required.** Evaluate Fabric tenant/workspace Private Link, managed private endpoints, source firewalls and public-access blocking for Restricted/Highly Restricted workloads.
7. **Classification travels with data.** Data classification, sensitivity label, retention and access requirements must be propagated from source -> Bronze -> Silver -> Gold -> semantic model -> AI/agent consumption where supported.
8. **Least privilege.** Use the minimum Entra, Azure, Fabric, source-system and Semantiq permissions. Never broaden permission merely to make a test pass.
9. **No secrets/customer data in Git.** Secrets, tokens, certificates, customer extracts, raw prompt payloads and unredacted production logs must not be committed.
10. **Safe observability.** Logs use identifiers and hashes where possible; redact tokens, secrets, personal data and sensitive business values. Debug payload capture is disabled in production unless an approved, time-bound support procedure is active.
11. **Retention and deletion.** Every persisted category must have a retention owner and deletion behavior. Offboarding must revoke access and delete/retain customer metadata according to approved policy.
12. **AI boundary control.** Prompts, schemas, grounding data, conversation history, embeddings and model outputs are data flows and must be included in the sovereignty register.

## 3. Fabric sovereignty baseline

Semantiq must discover and display, before production provisioning:

- Microsoft Entra tenant ID and tenant home geography where available;
- selected Fabric capacity and capacity region;
- workspace and item placement;
- source-system region/location;
- customer approved processing/storage geographies;
- whether Multi-Geo is required;
- whether tenant/workspace Private Link or managed private endpoints are required;
- whether workspace CMK is required;
- whether Copilot/Data Agent cross-geo processing, cross-geo storage or cross-geo conversation-history settings are enabled;
- any approved sovereignty exception, approver, expiry/review date and compensating control.

### Default policy

For customers that require data to remain within a nominated geography, Semantiq must reject or pause a configuration that would place storage or processing outside that geography unless a recorded exception is approved.

### Fabric AI settings

The following are treated as **high-impact sovereignty settings** and must never be automatically enabled without explicit policy approval:

- Data sent to Azure OpenAI can be processed outside the capacity geographic region/compliance boundary/national cloud.
- Data sent to Azure OpenAI can be stored outside the capacity geographic region/compliance boundary/national cloud.
- Conversation history stored outside the capacity geographic region/compliance boundary/national cloud.

If an AI feature cannot operate within the customer's approved boundary, the technology-decision process must evaluate a region-compatible Microsoft option and a self-hosted/open-source option before requesting an exception.

## 4. Data protection configuration profile

Each organisation must have a versioned `DataProtectionProfile` containing at minimum:

| Field | Example | Rule |
|---|---|---|
| ApprovedStorageGeographies | Singapore/approved Azure geography | Required before production data onboarding |
| ApprovedProcessingGeographies | Customer-approved list | Required for AI and data-processing workloads |
| CrossGeoProcessingAllowed | false | Default false |
| CrossGeoStorageAllowed | false | Default false |
| ConversationHistoryOutsideGeoAllowed | false | Default false |
| PublicInternetAccessAllowed | policy-defined | Restricted data should evaluate Private Link/block public access |
| CustomerManagedKeyRequired | true/false | Policy driven |
| PurviewSensitivityLabelsRequired | true/false | Policy driven |
| DLPPolicyRequired | true/false | Policy driven |
| DefaultRetentionClass | e.g. 90 days metadata | No indefinite retention by default |
| ProductionPayloadLogging | false | Default false |
| DataExportAllowed | role/policy based | Audit all privileged exports |
| SupportDataCaptureAllowed | false | Time-bound exception only |

Changes to this profile are privileged, versioned and audited.

## 5. Data classification model

Semantiq should support customer-defined classes. The product baseline uses:

| Class | Typical content | Minimum control expectation |
|---|---|---|
| Public | Public business information | Integrity and availability controls |
| Internal | Internal operational metadata | Authenticated access, encryption, audit |
| Confidential | Commercial, learner/customer records | Least privilege, label/DLP evaluation, redacted logs |
| Restricted | Credentials, government IDs, highly sensitive personal/financial/IP data | Strong isolation, private connectivity evaluation, CMK evaluation, strict export/retention controls |

Classification must be recorded at source, entity/dataset and sensitive-field level where meaningful.

## 6. Required privacy/security controls by layer

### Semantiq application/control plane
- OIDC/OAuth secure session handling and CSRF/XSS/SSRF protections.
- Server-side tenant authorisation on every customer resource.
- Vault-backed secret references; no plaintext secret persistence.
- Config values validated server-side and protected by RBAC.
- Audit every privileged configuration change.
- Rate limiting and abuse controls on externally reachable APIs.

### Fabric and data plane
- Capacity/workspace region validation against `DataProtectionProfile`.
- Encryption-at-rest status displayed; CMK status verified when required.
- RLS/OLS/object/workspace permissions validated with non-admin test identities.
- Private Link/managed private endpoint controls when policy requires.
- Purview sensitivity labels and DLP/protection policy status surfaced when in scope.
- Lineage and source-to-model traceability retained.

### AI/conversational layer
- Do not send credentials or unnecessary personal/sensitive values in prompts.
- Maintain allowlisted grounding sources.
- Store conversation history only according to policy and approved geography.
- Redact or hash sensitive values in prompt/agent observability.
- Require human approval for material external actions and customer-facing high-impact outputs where policy requires.
- Record model/runtime/region and data-flow decision in the AI technology decision file.

## 7. Sovereignty enforcement workflow

1. Capture customer's approved storage/processing geographies and regulatory/contractual constraints.
2. Discover source regions, Fabric tenant/capacity/workspace region and AI service/model regions.
3. Build/update `doc/context/DATA_SOVEREIGNTY_REGISTER.md`.
4. Calculate `PASS`, `WARNING`, `EXCEPTION_REQUIRED` or `BLOCKED` for each data flow.
5. Block production activation for `EXCEPTION_REQUIRED` or `BLOCKED` unless an authorised exception exists.
6. Revalidate whenever capacity, workspace, source, model, AI runtime, network policy or cross-geo tenant setting changes.
7. Include sovereignty evidence in phase verification and go-live evidence.

## 8. Secure coding and context preservation

Every material component must have enough durable context for another engineer/Claude session to understand it without reconstructing intent from code alone.

**Do document:** business purpose, phase/requirement IDs, inputs/outputs, data classifications touched, validation rules, permissions, external APIs, configuration keys, failure modes, audit behavior, tests and sovereignty impact.

**Do not:** add noisy comments explaining obvious syntax. Prefer public interface doc/docstrings plus the context registers and Architecture Decision Records for non-obvious decisions.

A code change is incomplete when it changes behavior but leaves related context registers stale.

## 9. Security/sovereignty release blockers

The following are release blockers:

- tenant-isolation failure;
- plaintext secret/token storage or secret exposure to browser/logs;
- production customer data stored/processed in an unapproved geography without an approved exception;
- required RLS/OLS/workspace security not enforced;
- required CMK/Private Link/DLP/sensitivity policy not configured or evidence unavailable;
- cross-geo AI processing/storage/history enabled contrary to customer policy;
- production logs containing Restricted data or bearer tokens;
- missing data owner/classification/retention for a production dataset;
- stale configuration/validation/data-context records for changed code.

## 10. Microsoft reference baseline (verify freshness at implementation time)

- Fabric security overview: https://learn.microsoft.com/en-us/fabric/security/security-overview
- Fabric end-to-end security scenario: https://learn.microsoft.com/en-us/fabric/security/security-scenario
- Fabric Private Link overview: https://learn.microsoft.com/en-us/fabric/security/security-private-links-overview
- Workspace-level Private Link: https://learn.microsoft.com/en-us/fabric/security/security-workspace-level-private-links-overview
- Workspace customer-managed keys: https://learn.microsoft.com/en-us/fabric/security/workspace-customer-managed-keys
- Fabric governance/compliance overview: https://learn.microsoft.com/en-us/fabric/governance/governance-compliance-overview
- Fabric DLP configuration: https://learn.microsoft.com/en-us/fabric/governance/data-loss-prevention-configure
- Fabric Data Agent tenant settings: https://learn.microsoft.com/en-us/fabric/data-science/data-agent-tenant-settings
- Copilot/Agent admin settings: https://learn.microsoft.com/en-us/fabric/admin/service-admin-portal-copilot

These links are a reference baseline only. Claude Code must verify current Microsoft documentation before implementing an integration or tenant setting.
