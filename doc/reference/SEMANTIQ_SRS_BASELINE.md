# SemantIQ Software Requirements Specification

Version: 0.4
Status: Repository-aligned product baseline
Product direction: Business Decision Intelligence experience with automated Microsoft Fabric control plane

## 1. Purpose

SemantIQ is a productised Business Decision Intelligence application that allows a customer to bring its Microsoft tenant, Fabric capacity/environment and enterprise data sources, then use SemantIQ to configure and operate the required data-intelligence platform from a single frontend.

Normal business users consume role-relevant intelligence, insights, risks, opportunities, recommendations and conversational analytics. They are not expected to open Microsoft Fabric or understand Fabric architecture.

Authorised administrators use SemantIQ to connect identity, validate or provision Fabric resources, connect data sources, orchestrate ingestion, build the medallion data foundation, configure quality and semantic models, govern data, prepare data for AI, create/validate Data Agents and operate the resulting platform.

## 2. Product Vision

SemantIQ shall provide this customer journey:

```text
Bring your Microsoft environment
        -> Connect your data
        -> SemantIQ builds and governs the data foundation
        -> SemantIQ creates business meaning
        -> SemantIQ prepares governed intelligence for AI
        -> Business users receive role-aware insights and conversation
        -> Administrators monitor, validate and improve the system
```

Product promise:

`Business users experience intelligence, not infrastructure. Administrators configure outcomes, not Microsoft Fabric services.`

## 3. Confirmed Repository Baseline

- Backend: Laravel 13 on PHP 8.5
- Frontend: React 19
- Database: MySQL hosted through cPanel
- Build: Composer plus Node.js/npm for frontend assets
- Architecture: modular monolith
- Deployment: GitHub Actions to cPanel over SSH
- Current deployment model: one SemantIQ application instance per customer organisation / Entra tenant
- Product design: future multi-tenant readiness through explicit organisation/tenant scoping

The implementation must preserve the existing repository design-system authority under `doc/design-system/`.

## 4. Experience Architecture

SemantIQ has two experience layers.

### 4.1 Business Decision Intelligence Experience

Default users work in:

- Home
- My Intelligence
- Ask SemantIQ
- Explore
- Decisions & Alerts
- Reports & Insights
- My Workspace
- Help

Business users see only authorised domains and data.

### 4.2 Platform Control Plane

Privileged users additionally access Administration for:

- organisation/users;
- Fabric environment;
- data sources;
- data engineering;
- data quality;
- business modelling;
- semantic intelligence;
- AI/agents;
- governance;
- data protection;
- data sovereignty;
- deployment;
- monitoring;
- system configuration.

## 5. User Classes

### 5.1 Business users

Examples:
- CEO / Executive
- Sales Director / Sales Manager
- Finance Director / Finance Manager
- HR / People Director
- Operations Manager
- Customer/Service leader
- Learning leader
- Analyst
- Contributor
- Viewer

### 5.2 Administrative users

- System Administrator
- Organisation Administrator
- Domain Owner
- Data/Governance Administrator
- Auditor / Compliance Reviewer

Platform administration does not automatically grant unrestricted business-domain data access.

## 6. Functional Requirements - Business Experience

### 6.1 Role-aware Home

**FR-BIZ-001** The system shall determine the current user's effective organisation, platform role, domain entitlements and data scope after authentication.

**FR-BIZ-002** The Home page shall display role-relevant KPI cards, what changed, attention required, risks, opportunities, recommendations, alerts and recent decisions.

**FR-BIZ-003** The Home page shall not display metrics from an unauthorised domain.

**FR-BIZ-004** Insight cards shall support contextual actions including `Why?`, `Explore`, `Compare`, `Ask SemantIQ`, `Save`, `Create Alert` and `Assign` where authorised.

**FR-BIZ-005** The system shall support empty/onboarding states when a domain has not yet been configured or data is unavailable.

### 6.2 My Intelligence

**FR-BIZ-010** The system shall provide domain workspaces for Executive, Sales, Finance, People, Operations, Customer, Learning and configurable custom domains.

**FR-BIZ-011** Domain visibility shall be entitlement-driven.

**FR-BIZ-012** Each domain shall support a configurable set of KPIs, measures, dimensions, insights, risks, opportunities, trends and forecasts.

**FR-BIZ-013** Business labels and definitions shall come from governed semantic metadata and glossary terms rather than exposing technical column names.

**FR-BIZ-014** Domain pages shall allow drill-down only within the user's authorised scope.

### 6.3 Ask SemantIQ

**FR-AI-001** Ask SemantIQ shall be accessible as a first-class business menu for authorised users.

**FR-AI-002** The conversational layer shall constrain retrieval and query execution to the user's authorised organisation, domains, records, objects and fields.

**FR-AI-003** Answers shall prefer governed semantic measures, certified metrics and approved business definitions.

**FR-AI-004** The response contract shall support concise narrative, key metrics, supporting visual/data context, source/definition context and suggested follow-ups where available.

**FR-AI-005** Multi-turn follow-up questions shall preserve permitted conversation context.

**FR-AI-006** The system shall distinguish data-backed facts from AI-generated interpretation/recommendation.

**FR-AI-007** AI shall not directly execute deterministic Fabric provisioning, destructive operations or privileged configuration. Such actions shall use validated backend workflows.

**FR-AI-008** Conversation storage, telemetry, prompts, responses, embeddings and model processing shall follow the approved data-protection and sovereignty profile.

### 6.4 Explore

**FR-BIZ-020** Users shall explore authorised metrics by approved dimensions without writing SQL or DAX.

**FR-BIZ-021** Explore shall support period selection, comparison, filtering, drill-down and saving personal analysis.

**FR-BIZ-022** The system may generate narrative interpretation and suggested follow-up questions from governed results.

### 6.5 Decisions & Alerts

**FR-DEC-001** The system shall surface data-driven risks, anomalies, opportunities and recommendations.

**FR-DEC-002** Users shall create alerts based on approved metric/threshold conditions where authorised.

**FR-DEC-003** A decision record shall preserve insight/evidence reference, owner, decision/status, comments and timestamps.

**FR-DEC-004** AI recommendations shall remain advisory unless a separately approved workflow explicitly permits automated action.

### 6.6 Reports & My Workspace

**FR-BIZ-030** Users shall view authorised reports and saved insights by domain.

**FR-BIZ-031** Users shall save personal questions, views, alerts, insights and reports.

**FR-BIZ-032** Personal workspace objects shall be scoped to the user and organisation unless deliberately shared.

## 7. Functional Requirements - Identity, Roles and Access

**FR-ID-001** The system shall support Microsoft Entra SSO for the customer tenant.

**FR-ID-002** The administrator experience shall provide guided configuration from app registration through redirect URI, API permissions, admin consent, service-principal setup and connection validation when manual Microsoft steps are required.

**FR-ID-003** Role and domain entitlement administration shall be available only to authorised administrators.

**FR-ID-004** Backend authorization shall enforce every protected route/action; menu hiding is insufficient.

**FR-ID-005** Effective access shall be the intersection of authenticated user, organisation/tenant, platform role, business-domain entitlement, record/data scope, object/field security and applicable data-protection policy.

**FR-ID-006** The System Administrator role shall not automatically grant unrestricted access to sensitive business-domain data.

**FR-ID-007** Privileged access and changes shall be auditable.

## 8. Functional Requirements - Fabric Environment Automation

### 8.1 Readiness and Connection

**FR-FAB-001** SemantIQ shall connect to the customer's Microsoft/Fabric environment through approved identities and APIs.

**FR-FAB-002** SemantIQ shall perform a readiness assessment covering tenant identity, capacity, permissions, tenant settings, workspace prerequisites, AI settings, geography and connectivity.

**FR-FAB-003** SemantIQ shall classify each setup activity as API-automated, API-assisted or guided-manual based on current Microsoft capability.

**FR-FAB-004** If an operation cannot be completed by supported API, SemantIQ shall provide an in-app help workflow and validate completion afterward.

### 8.2 Capacity and Workspaces

**FR-FAB-010** Administrators shall discover/select approved Fabric capacity or configure an approved provisioning workflow where supported.

**FR-FAB-011** SemantIQ shall create/configure DEV, TEST and PROD Fabric workspaces as approved by the customer.

**FR-FAB-012** SemantIQ shall assign approved capacity, roles and workspace configuration.

**FR-FAB-013** Fabric environment operations shall record request/correlation identifiers, result and audit evidence.

### 8.3 Tenant and AI Settings

**FR-FAB-020** SemantIQ shall display required tenant/AI settings and their current validation status.

**FR-FAB-021** Cross-geo processing, storage and AI/conversation-history settings shall default to disabled unless approved by policy.

**FR-FAB-022** The application shall block or require approved exception workflow when a requested region/settings combination conflicts with the customer's sovereignty profile.

## 9. Functional Requirements - Source Connectivity

**FR-SRC-001** Administrators shall register sources from SemantIQ without navigating Fabric for normal supported setup.

**FR-SRC-002** Supported baseline categories shall include SharePoint, Excel/files, SQL Server, Azure SQL, Dataverse, Dynamics 365, Business Central, REST API, ERP/CRM/LMS and custom connectors.

**FR-SRC-003** The application shall capture authentication method, connection metadata and source owner without exposing secrets to the browser after submission.

**FR-SRC-004** SemantIQ shall test connection and display actionable error/help content.

**FR-SRC-005** SemantIQ shall discover available tables/files/schemas/metadata where supported.

**FR-SRC-006** SemantIQ shall recommend an ingestion approach such as pipeline, copy job, Dataflow Gen2, mirroring, shortcut, eventstream or direct upload based on source and requirements.

## 10. Functional Requirements - Ingestion and Lakehouse

**FR-ING-001** SemantIQ shall create/configure the required Lakehouse and medallion structure through supported automation.

**FR-ING-002** Bronze shall retain source-aligned raw data with traceability.

**FR-ING-003** Silver shall contain cleansed/standardised data.

**FR-ING-004** Gold shall contain analytics-ready facts, dimensions and business entities.

**FR-ING-005** Pipelines shall support scheduling and incremental/change-based loading where the source permits.

**FR-ING-006** Long-running ingestion shall expose asynchronous status, retries, failure details and audit history.

## 11. Functional Requirements - Data Quality and Business Modelling

**FR-DQ-001** SemantIQ shall profile data and detect nulls, duplicates, invalid values, format inconsistencies and referential issues.

**FR-DQ-002** Administrators/data stewards shall configure reusable quality and standardisation rules.

**FR-DQ-003** Validation rules shall have stable identifiers, severity, scope, implementation location, help reference and tests.

**FR-MDL-001** SemantIQ shall propose business entities, keys, relationships, facts, dimensions and hierarchies from trusted data.

**FR-MDL-002** Users shall approve or revise proposed models before production promotion.

**FR-MDL-003** Technical names shall be mapped to business-friendly names and glossary terms.

## 12. Functional Requirements - Semantic Intelligence

**FR-SEM-001** SemantIQ shall create/configure governed Power BI semantic models through approved supported methods.

**FR-SEM-002** The semantic layer shall manage relationships, explicit measures, KPIs, hierarchies, descriptions, synonyms and business definitions.

**FR-SEM-003** Direct Lake shall be preferred where appropriate and supported, subject to architecture approval.

**FR-SEM-004** Row/object/column security shall reflect SemantIQ role/domain/data-scope policies and underlying Microsoft security requirements.

**FR-SEM-005** Semantic changes shall be versioned and validated before promotion.

## 13. Functional Requirements - AI Readiness and Data Agents

**FR-AGT-001** SemantIQ shall configure Prep for AI or equivalent supported semantic AI preparation for approved data.

**FR-AGT-002** Administrators/domain owners shall define which tables, columns, measures and business definitions are approved for AI.

**FR-AGT-003** SemantIQ shall maintain business instructions, example questions, verified answers and ground-truth tests.

**FR-AGT-004** SemantIQ shall create/configure Fabric Data Agents where they are the approved technology for governed structured analytics.

**FR-AGT-005** SemantIQ shall support an approved alternative agent architecture when Fabric Data Agent is not suitable, subject to the AI technology decision process.

**FR-AGT-006** Agent publication shall require security and accuracy validation.

**FR-AGT-007** The AI Validation Centre shall test simple, complex, follow-up, authorisation and ground-truth questions.

## 14. Functional Requirements - Governance, Protection and Sovereignty

### 14.1 Governance

**FR-GOV-001** The system shall maintain ownership, classification, certification, lineage and policy metadata.

**FR-GOV-002** Configuration and governance decisions shall be auditable.

### 14.2 Data Protection

**FR-DP-001** Each customer organisation shall have a versioned DataProtectionProfile.

**FR-DP-002** The profile shall record classification policy, retention, sensitive-data handling, export policy, masking/minimisation requirements and relevant controls such as Purview/DLP.

**FR-DP-003** SemantIQ shall minimise business payload copies in the control plane and prefer metadata/resource references where possible.

**FR-DP-004** Production logs shall redact secrets/tokens and avoid unrestricted customer payload capture by default.

### 14.3 Data Sovereignty

**FR-SOV-001** The customer profile shall record approved storage, processing and AI-processing geographies.

**FR-SOV-002** `VAL-SOV-GEO-001` shall block incompatible production activation unless an approved exception exists.

**FR-SOV-003** Cross-geo processing/storage and cross-geo AI/conversation-history settings shall default OFF.

**FR-SOV-004** SemantIQ shall record material data flows, storage locations, processing locations, network route and approved exceptions in the sovereignty register.

**FR-SOV-005** Private Link, managed private endpoints, public access blocking, customer-managed keys and Microsoft Purview controls shall be evaluated according to customer policy/data classification.

## 15. Functional Requirements - Help and Guided Setup

**FR-HLP-001** Every technical/configuration screen shall provide contextual Help.

**FR-HLP-002** A help topic shall contain prerequisites, required role/permissions, exact steps, tenant/environment scope, expected result, SemantIQ validation, troubleshooting and official reference.

**FR-HLP-003** Help shall explicitly state what SemantIQ automates and what requires customer administrator action.

**FR-HLP-004** Help shall state security, data-protection and sovereignty impact where relevant.

Example SSO help flow:

1. Open Microsoft Entra admin centre.
2. Create/select app registration.
3. Configure redirect URI.
4. Add required delegated/application permissions.
5. Grant admin consent where required.
6. Configure approved credential/certificate method.
7. Enter non-secret identifiers / securely submit secret material to SemantIQ backend.
8. Configure SemantIQ environment values.
9. Test connection.
10. Validate signed-in identity and required API access.

## 16. Functional Requirements - Operations and Deployment

**FR-OPS-001** Administrators shall monitor application, Fabric, source, pipeline, data-quality, semantic, AI and security health.

**FR-OPS-002** SemantIQ shall expose failed workflows, retry status and correlation IDs.

**FR-OPS-003** Usage/adoption analytics shall include domain, reports, agents and questions without exposing sensitive prompt contents unnecessarily.

**FR-DEP-001** Fabric content shall be promoted through approved DEV -> TEST -> PROD processes rather than edited directly in production where supported.

**FR-DEP-002** Git `main` remains the repository production deploy trigger; Fabric DEV/TEST/PROD environments are not Git branches.

**FR-DEP-003** Production deployments, migrations and destructive configuration actions require explicit approval under repository rules.

## 17. Data Model Baseline

SemantIQ control-plane persistence should include or be capable of representing:

- Organisation
- TenantConnection
- UserReference
- PlatformRole
- Domain
- DomainEntitlement
- DataScopePolicy
- DataProtectionProfile
- SovereigntyPolicy / SovereigntyException
- ExternalConnection
- ExternalResourceReference
- FabricEnvironment / WorkspaceReference
- WorkflowRun / WorkflowStep / WorkflowEvent
- ValidationRule / ValidationResult
- AuditEvent
- HelpTopic
- BusinessGlossaryTerm
- MetricDefinition / KPI Definition
- VerifiedQuestion / GroundTruthCase
- AgentDefinition / AgentVersion
- AlertRule / AlertEvent
- DecisionRecord
- SavedInsight / SavedQuestion / SavedView

Business data should remain in the customer's governed data platform unless a specific SemantIQ feature requires controlled persistence.

## 18. API and Integration Requirements

- Microsoft Graph / Entra APIs as required for identity and directory context.
- Microsoft Fabric REST APIs and other supported Fabric automation surfaces.
- Power BI/Fabric semantic model management surfaces where supported.
- Source-specific APIs/connectors.
- Optional Copilot Studio, Foundry or approved open-source agent runtime based on an architecture decision.

Every Microsoft integration must be re-verified against current official documentation before coding because API versions, preview status, scopes and availability change.

## 19. Non-Functional Requirements

### Security

- OWASP-aligned web controls.
- Least privilege.
- Backend authorization on protected actions.
- Parameterised/safe data access.
- Secret references rather than plaintext secrets.
- Bounded timeouts and safe retries on outbound calls.
- Audit of privileged operations.

### Availability and performance

- Target 99.9% monthly application availability excluding external dependencies.
- Metadata page p95 target under 3 seconds excluding external long-running operations.
- Operations expected to exceed 10 seconds shall be asynchronous.

### Scalability

- Current deployment: one organisation per application instance.
- Persistence and authorization boundaries shall remain future multi-tenant-ready.

### Observability

- Structured logging.
- Correlation IDs.
- Metrics and failure monitoring.
- Redacted support diagnostics.

### Accessibility

- Target WCAG 2.1 AA.

### Maintainability

- Microsoft integration behind adapters/capability flags.
- AI provider/runtime behind replaceable abstractions.
- Configuration, code, data, validation and sovereignty context documented together.

## 20. Context Preservation Requirements

Behavior-changing work shall update the relevant registers under `doc/context/`:

- CODE_CONTEXT_REGISTER.md
- DATA_CONTEXT_REGISTER.md
- VALIDATION_RULES_REGISTER.md
- CONFIGURATION_REGISTER.md
- DATA_SOVEREIGNTY_REGISTER.md
- SECURITY_PRIVACY_DECISIONS.md

A phase cannot be considered verified if implementation and context documentation materially disagree.

## 21. Phase Mapping

| Phase | Scope |
| --- | --- |
| 00 | Engineering foundation, business UI shell, role/domain framework, admin boundary, help/audit/context foundations |
| 01 | Entra identity, SSO, app registration/admin consent workflow, users/access |
| 02 | Fabric readiness, capacity, tenant settings, workspaces, region/network validation |
| 03 | Source connectivity, authentication, gateway, metadata discovery |
| 04 | Ingestion, Lakehouse, Bronze/Silver/Gold, scheduling/monitoring |
| 05 | Data quality, standardisation, business entities and modelling |
| 06 | Semantic intelligence, metrics/KPIs, governance and security |
| 07 | AI readiness, Data Agents, Ask SemantIQ backend, validation centre |
| 08 | Deployment, monitoring, Help Centre completion, lifecycle operations |
| 09 | End-to-end UAT, role/domain verification, production go-live and handover |

## 22. Acceptance Principles

SemantIQ is acceptable only when:

1. a normal business user can obtain role-relevant intelligence without using Microsoft Fabric;
2. an authorised administrator can perform supported end-to-end setup from SemantIQ or follow an exact guided workflow where APIs do not exist;
3. backend security proves users cannot cross domains/scopes through direct URL/API calls;
4. AI respects the same permissions as conventional analytics;
5. data protection and sovereignty policy can block non-compliant configuration;
6. critical Fabric/data/AI workflows provide verification and audit evidence;
7. changes are phase-gated and context registers remain aligned with code/configuration.
