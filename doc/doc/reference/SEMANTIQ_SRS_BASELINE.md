# SemantIQ Software Requirements Specification v0.3 - Repository-Aligned Markdown Baseline

> Generated as a GitHub/Claude Code readable mirror of the approved Word SRS. The Word SRS remains the formal baseline.

## Repository Alignment Baseline v1.3

The formal product scope is implemented against the following confirmed repository baseline unless the user approves a later architecture change:

- Laravel 13 on PHP 8.5 backend, React 19 frontend, MySQL hosted through cPanel, modular monolith.
- GitHub Actions deploys the application to cPanel; `main` is the only long-lived deploy branch.
- Canonical project documentation root is `doc/`.
- Current hosted deployment mode is one customer organisation / Microsoft Entra tenant per SemantIQ application instance.
- The control plane remains multi-tenant-ready: customer-owned records, Fabric IDs, approvals, audit and policy metadata preserve organisation/tenant context and cross-organisation access is denied. Shared multi-customer SaaS tenancy and multi-tenant Entra sign-in are future/enterprise options requiring explicit approval.
- Fabric DEV/TEST/PROD workspaces are data-platform environments and do not imply equivalent long-lived Git branches.


SEMANTIQ

Software Requirements Specification

Automated Microsoft Fabric Data Intelligence Platform


| Product promise: Bring Your Data. Bring Your Fabric. We Build the Intelligence. |
| --- |



| Document | Value |
| --- | --- |
| Document type | Software Requirements Specification (SRS) |
| Version | 0.1 |
| Date | 22 August 2026 |
| Product | Semantiq |
| Primary platform | Microsoft Fabric |
| Document status | Detailed product requirements baseline |


Data -> Trusted Data -> Business Meaning -> Governed Intelligence -> AI -> Business Decisions


# Document Control


| Version | Date | Author / Owner | Summary |
| --- | --- | --- | --- |
| 0.1 | 22 Aug 2026 | Solution Architecture / Product Team | Initial detailed SRS covering productised end-to-end Fabric setup, API automation, in-app screens, security, orchestration and help guides. |



## Reference Inputs

This SRS is derived from the Semantiq product story and requirements, and extended using the Intelligent OS solution-scope structure. The two source documents define the core concept: a single application that discovers, ingests, cleans, standardises, models, governs and prepares organisational data for AI, while acting as an intelligent control plane over the customer's Microsoft Fabric environment.


| Reference | Purpose in this SRS |
| --- | --- |
| Semantiq_Requirement.docx | Product journey, Fabric readiness, data foundation, Bronze/Silver/Gold, semantic layer, Data Agent, security, validation, deployment and continuous improvement. |
| Intelligent_OS_Solution_Scope_Requirements.docx | Module structure, control-plane concept, source discovery, data quality, governance, semantic recommendations, conversational intelligence, lifecycle management and user experience. |
| Microsoft Learn (current as of Aug 2026) | REST API endpoints, identity support, tenant settings, service-principal controls, workspace/capacity APIs, Lakehouse, pipelines, semantic models, Data Agents, job scheduling and deployment pipelines. |



# Contents

1. Executive Summary

2. Product Scope and Design Principles

3. Actors, Personas and Roles

4. Solution Architecture

5. Authentication, SSO and Customer Tenant Integration

6. End-to-End Product Journey

7. Application Screen and Navigation Requirements

8. Functional Requirements by Module

9. Fabric Provisioning and Configuration Requirements

10. Data Source, Ingestion and Lakehouse Requirements

11. Data Quality, Business Modelling and Semantic Layer

12. AI Readiness, Fabric Data Agent and Conversational Intelligence

13. Security, Governance and Compliance

14. Deployment, Monitoring and Lifecycle Management

15. In-App Help Centre and Guided Configuration

16. API and Automation Specification

17. Semantiq Configuration Data Model

18. Error Handling, Status and Orchestration

19. Non-Functional Requirements

20. Acceptance Scenarios and Test Criteria

21. Release Scope and Product Roadmap

22. Assumptions, Constraints and Out of Scope

Appendix A. Detailed Help Topic: SSO and Fabric Automation Identity

Appendix B. Official Microsoft API Reference Register

Appendix C. Requirement Traceability Summary


# 1. Executive Summary

Semantiq is a productised front-end and control-plane application that connects to a customer's Microsoft Fabric environment and automates or guides the complete journey required to create a governed Data Intelligence Layer. The customer should not need to manually design every Fabric workspace, connection, Lakehouse, ingestion pipeline, semantic model, security rule, AI instruction or Fabric Data Agent.

The product must support two customer starting points: (1) Bring Your Fabric, where a Fabric capacity and tenant already exist, and (2) Provision Fabric, where the customer has Microsoft Azure/Entra but does not yet have a Fabric capacity. In both cases, Semantiq must first establish secure identity, assess readiness, determine which actions can be executed by API, request explicit approval for privileged actions, and provide guided help for any step that cannot or should not be automated.


| Core product rule: Automate stable, supported operations. Use explicit approval for privileged changes. Use a guided help workflow for unsupported, preview-only or tenant-policy-sensitive operations. Never silently elevate permissions or change customer security settings. |
| --- |



## 1.1 Business Outcomes

- Reduce Fabric implementation effort by turning a multi-tool engineering project into a guided product workflow.
- Create a repeatable Data Intelligence Layer across multiple industries and business domains.
- Keep customer source systems as systems of record while Fabric becomes the governed analytics and AI data estate.
- Replace ad hoc data extraction, repetitive DAX/report engineering and disconnected dashboards with governed self-service intelligence.
- Provide business users with natural-language access to certified semantic models and Fabric Data Agents.
- Create a reusable control plane for onboarding, configuration, monitoring, governance, change management and support.

## 1.2 Product Success Definition

A customer is considered successfully onboarded when Semantiq can authenticate to the tenant, verify required Fabric settings, identify or provision a Fabric capacity, create/attach DEV-TEST-PROD workspaces, register at least one source, land raw data in a Lakehouse, transform it into trusted business-ready structures, deploy a governed semantic model, configure and publish a Fabric Data Agent, validate security and ground-truth questions, and expose the environment through the Semantiq user experience and approved downstream channels.


# 2. Product Scope and Design Principles


## 2.1 In Scope

- Customer tenant onboarding and Microsoft Entra SSO.
- Customer-owned Fabric automation identity / service principal configuration.
- Fabric readiness assessment, tenant setting verification and capacity discovery.
- Optional Fabric capacity provisioning through Azure Resource Manager when customer grants the required Azure RBAC.
- DEV, TEST and PROD workspace creation, capacity assignment and role configuration.
- Source registry, Fabric connection configuration, gateway guidance, connection health and schema discovery.
- Lakehouse creation and medallion architecture (Bronze, Silver, Gold).
- Pipeline/notebook generation, scheduling, incremental loading, retries and operational monitoring.
- Data profiling, standardisation, quality rules, entity mapping, business keys and star-schema recommendations.
- Power BI semantic model generation, relationships, measures, descriptions, synonyms, RLS/OLS rules and business glossary.
- AI readiness configuration, business instructions, verified questions and ground-truth test packs.
- Fabric Data Agent creation, configuration, update and publish using supported REST APIs.
- Deployment pipelines, lifecycle promotion, monitoring, audit and change management.
- In-app help topics that include exact Microsoft portal navigation, prerequisites, roles, field values, screenshots/placeholders, verification tests and troubleshooting.

## 2.2 Product Design Principles


| ID | Principle | Requirement |
| --- | --- | --- |
| P-01 | Customer ownership | Customer data, Fabric workspaces and Fabric items remain in the customer tenant unless explicitly agreed otherwise. |
| P-02 | Least privilege | Request only the minimum Microsoft Entra, Fabric and Azure permissions required for each operation. |
| P-03 | Control-plane separation | Semantiq stores orchestration metadata and configuration; business data remains in the customer Fabric data plane unless temporary processing is explicitly required. |
| P-04 | Approval before privilege | Tenant-setting changes, role elevation, production deployment and destructive actions require explicit user confirmation. |
| P-05 | API first, guided fallback | Supported public APIs are preferred; unsupported/preview operations are performed only behind capability flags or guided manually. |
| P-06 | Idempotent automation | A repeated setup action must detect existing assets and avoid unnecessary duplicates. |
| P-07 | Explainability | Every automation step records what was changed, the API called, resulting resource IDs and the user/service identity that initiated the action. |
| P-08 | Human validation of business meaning | Semantiq may propose models, relationships, measures and business definitions, but business owners must approve definitions before certification. |
| P-09 | Environment separation | Production is not edited directly when DEV -> TEST -> PROD promotion is available. |
| P-10 | Security inheritance | User-facing data access must respect Fabric permissions and semantic-model security rather than bypass them. |



# 3. Actors, Personas and Roles


| Persona | Typical authority | Primary responsibilities |
| --- | --- | --- |
| Customer Tenant Admin | Microsoft Entra / Global or relevant application administrator | Approves enterprise application consent and identity configuration. |
| Fabric Administrator | Fabric tenant admin | Reviews/enables tenant settings, capacity and governance controls. |
| Azure Platform Admin | Azure subscription/resource-group role holder | Creates or authorises Fabric capacity provisioning via Azure Resource Manager when required. |
| Semantiq Platform Admin | Semantiq tenant administrator | Manages Semantiq organisation configuration, users, integration state and support. |
| Data Platform Admin | Fabric workspace administrator | Creates and manages workspaces, roles, connections, Lakehouses, pipelines and deployment topology. |
| Data Owner / Steward | Business/data governance role | Registers sources, approves data quality rules, business terms, classifications and lineage. |
| Semantic Model Owner | Power BI / analytics owner | Approves relationships, measures, KPI definitions, RLS/OLS and semantic-model release. |
| AI / Agent Owner | Data Agent owner | Approves Data Agent scope, instructions, source selection, test pack and publication. |
| Business User | End user | Asks governed business questions and consumes approved insights. |
| Semantiq Support / Operator | Vendor support role with customer approval | Diagnoses integration failures using metadata and logs without unnecessary access to customer data. |



# 4. Solution Architecture

SemantIQ is designed as a multi-tenant-ready control plane with a customer-isolated Fabric data plane. The current hosted release deploys one customer organisation / Entra tenant per SemantIQ application instance; shared multi-customer SaaS tenancy is not enabled by default. The front end provides the guided experience; backend services perform token acquisition, API orchestration, long-running-operation polling, generation of Fabric item definitions and audit logging. Credentials are stored only in an approved secret store and never exposed back to browser clients after submission.


| User Experience Semantiq Web Application / Admin Console / Help Centre / Business Q&A |
| --- |
| v |
| Semantiq Control Plane Tenant onboarding, orchestration, metadata, policy, approvals, audit, health and deployment |
| v |
| Integration Layer Microsoft Entra ID + Fabric REST APIs + Azure Resource Manager + optional Microsoft Graph + source connectors |
| v |
| Customer Fabric Data Plane Workspaces -> Connections/Gateways -> OneLake/Lakehouse -> Bronze/Silver/Gold -> Semantic Model -> Data Agent |
| v |
| Consumption Semantiq Q&A / Power BI / Teams / Copilot Studio / Web or business applications |



## 4.1 Logical Components


| Component | Responsibility |
| --- | --- |
| Web Front End | SPA/web application for onboarding, configuration, approvals, monitoring, help and business Q&A. |
| Authentication Service | Microsoft Entra OIDC/OAuth integration, session management, tenant resolution and role mapping. |
| Fabric API Adapter | Typed integration with api.fabric.microsoft.com/v1, including LRO polling and Retry-After handling. |
| Azure Resource Manager Adapter | Optional provisioning of Microsoft.Fabric/capacities when customer grants Azure RBAC. |
| Microsoft Graph Adapter | Optional lookup of tenant/group/user metadata and SharePoint discovery where explicitly enabled. |
| Orchestration Engine | Runs ordered setup workflows, dependencies, retries, approvals and rollback/compensation where supported. |
| Definition Generator | Generates Lakehouse/pipeline/notebook/semantic-model/Data-Agent definitions from approved templates and customer metadata. |
| Metadata Repository | Stores customer tenant configuration, resource IDs, source catalog, business glossary, quality rules, versions and deployment state. |
| Secret Store | Stores client secrets/certificates and source credentials encrypted; UI stores references only. |
| Audit & Observability | Immutable event trail, API request metadata, job history, alerts, performance metrics and support diagnostics. |
| Help Content Service | Context-sensitive configuration instructions linked to each screen and error condition. |



## 4.2 Customer Fabric Target Architecture

The generated target architecture follows the product journey defined in the source requirements: source systems -> Fabric ingestion/connectivity -> OneLake -> Lakehouse Bronze/Silver/Gold -> optional Warehouse/Gold tables -> Power BI Semantic Model -> Fabric Data Agent -> conversational/business applications.

- Enterprise sources: ERP, CRM, Business Central, Dataverse, SQL, Excel, SharePoint, APIs, LMS and external databases.
- Connectivity: Fabric connections, pipelines, mirroring, shortcuts, Eventstream and gateways as applicable.
- Storage: OneLake and Lakehouse with Bronze (raw), Silver (clean/standardised) and Gold (business-ready) layers.
- Semantic intelligence: Power BI semantic model with explicit measures, relationships, business-friendly names, descriptions, synonyms and security.
- AI intelligence: Fabric Data Agent with curated sources, instructions, examples and governed publication.
- Consumption: Semantiq, Power BI, Microsoft Teams, Copilot Studio, web or line-of-business applications.

# 5. Authentication, SSO and Customer Tenant Integration


## 5.1 Identity Model

Semantiq must separate interactive user sign-in from unattended Fabric automation. The browser must never hold a client secret. The recommended product architecture uses Microsoft Entra authorization-code flow with PKCE for interactive SSO, and a confidential service identity (customer-owned service principal, certificate preferred) for unattended Fabric operations.


| Identity | Use | Credential pattern | Key rule |
| --- | --- | --- | --- |
| Semantiq user SSO | User login and delegated actions | OIDC/OAuth 2.0 authorization code + PKCE | No client secret in browser. |
| Fabric automation service principal | Background provisioning and management | Client credentials with certificate preferred; secret supported for MVP | Token scope: https://api.fabric.microsoft.com/.default. |
| Azure provisioning identity | Optional capacity creation | Customer-approved service principal or delegated admin with Azure RBAC | Only required when Semantiq creates Fabric capacity. |
| Source connection identity | Data-source access | Fabric connection credentials, OAuth, managed identity or gateway credential | Stored/managed according to connector capability. |



## 5.2 SSO Integration Screen Requirements


| Field / Control | Requirement |
| --- | --- |
| Organisation name | Required; displayed in audit and support context. |
| Tenant ID | Required UUID. Validate format before calling Microsoft APIs. |
| Primary domain | Optional display field; may be discovered from user token or entered manually. |
| SSO mode | Customer-tenant SSO for the current single-customer deployment. Multi-tenant SemantIQ SSO is a future/enterprise option and requires an approved identity architecture decision. |
| Admin consent status | Show Not Granted / Granted / Expired or Changed. |
| Admin consent action | Open tenant-specific Microsoft admin-consent flow in a new window; never use personal-account common consent for tenant-wide consent. |
| Redirect/callback status | Display configured callback URI and verification result. |
| Test SSO | Sign out/in test; validate issuer, tenant ID, audience, nonce/state, role mapping and session. |
| Help | Open HLP-SSO-001 with exact portal steps and troubleshooting. |



## 5.3 Fabric Automation Identity Screen Requirements


| Field | Type / Validation | Security / Behaviour |
| --- | --- | --- |
| Tenant ID | UUID, required | Must match customer tenant unless explicit cross-tenant scenario is enabled. |
| Application (client) ID | UUID, required | Used for client credentials. |
| Credential type | Certificate / Client secret | Certificate is preferred for production. |
| Client secret | Masked secret input | Never returned after save; store only in secret vault; capture expiry date. |
| Certificate | Upload/reference or vault reference | Store private key only in approved key-management service; expose thumbprint only in UI. |
| Token endpoint | Derived | https://login.microsoftonline.com/{tenantId}/oauth2/v2.0/token |
| Fabric scope | Read-only fixed value | https://api.fabric.microsoft.com/.default |
| Test token | Button | Acquire token server-side and record only metadata, never token content. |
| Test Fabric API | Button | Call GET /v1/capacities and GET /v1/workspaces (or another read-safe endpoint). |
| Credential expiry | Date / monitored | Alert 30/14/7 days before secret/certificate expiry. |



| Important permission model: For service-principal access to Fabric public APIs, tenant controls and Fabric workspace/item permissions are decisive. Delegated API scopes shown in Microsoft REST references apply to user-delegated access. Semantiq must not assume that adding delegated scopes to an Entra app automatically authorises service-principal operations. |
| --- |



# 6. End-to-End Product Journey


| # | Stage | What Semantiq does | Exit state |
| --- | --- | --- | --- |
| 1 | Organisation & SSO | Register customer organisation, establish SSO, obtain tenant consent and confirm identity. | Ready |
| 2 | Automation Identity | Configure customer Fabric service principal/certificate, test token and API access. | Ready |
| 3 | Fabric Readiness | Detect capacity, roles, tenant settings, workspace access, API capability and blockers. | Ready / Action Required |
| 4 | Fabric Environment | Use existing capacity or provision new Fabric capacity; establish DEV/TEST/PROD workspace topology. | Provisioned |
| 5 | Source Discovery | Register enterprise source, connection method, credential/gateway, schema and business domain. | Connected |
| 6 | Ingestion | Create pipeline/mirroring/shortcut strategy, Bronze landing, schedules, incremental logic and logging. | Operational |
| 7 | Data Quality | Profile data; propose/approve cleansing, standardisation, keys and quality gates. | Trusted |
| 8 | Business Modelling | Create Silver/Gold structures, facts/dimensions, relationships and glossary mapping. | Business-ready |
| 9 | Semantic Intelligence | Generate semantic model, measures, KPI definitions, descriptions, synonyms, RLS/OLS and certification workflow. | Governed |
| 10 | Prepare for AI | Curate data subset, AI instructions, verified answers and ground-truth questions. | AI-ready |
| 11 | Fabric Data Agent | Create/update Data Agent definition, connect approved sources, publish and grant approved access. | Published |
| 12 | Validation | Run data, security, semantic, AI and regression tests; capture approval evidence. | Approved |
| 13 | Deployment | Promote DEV -> TEST -> PROD through deployment pipeline and release gate. | Live |
| 14 | Operate & Improve | Monitor capacity, jobs, failures, data quality, usage and agent accuracy; manage changes. | Healthy |



## 6.1 Workflow Behaviour

- Each stage has prerequisites, status, owner, last checked date, evidence, logs, next action and linked help topic.
- The application must support resume-from-last-step after browser refresh, logout or temporary API failure.
- Every write operation must show a pre-action summary: target tenant/workspace, resource to be created/changed, role or setting involved, and whether the operation is reversible.
- Long-running Fabric operations must be tracked asynchronously. The UI must show Pending, Running, Succeeded or Failed and poll only according to Microsoft Retry-After guidance.
- If a step cannot be automated, the workflow changes to Guided Action Required and opens the relevant help topic. The user can return and select Re-check to verify completion through read APIs.
- No production-impacting action may be marked complete solely because the user clicked Done; Semantiq should verify the target state wherever a read API exists.

# 7. Application Screen and Navigation Requirements


## 7.1 Primary Navigation

The left navigation should mirror the lifecycle rather than Microsoft product boundaries so that a non-specialist administrator can progress sequentially: Home -> Setup -> Fabric -> Data Sources -> Data Foundation -> Business Model -> Governance -> AI & Agents -> Validation -> Deploy -> Monitor -> Help -> Audit.


| ID | Screen | Primary role | Key functions | Integration | Help |
| --- | --- | --- | --- | --- | --- |
| SC-001 | Sign In | All users | SSO login, tenant detection, session creation | Microsoft Entra OIDC/OAuth | HLP-SSO-001 |
| SC-002 | Organisation Setup | Semantiq Admin | Organisation name, tenant ID, domain, region, owner | Semantiq metadata | HLP-ORG-001 |
| SC-003 | SSO & Consent | Tenant Admin | Consent status, callback, roles, test sign-in | Microsoft identity platform | HLP-SSO-001 |
| SC-004 | Fabric Automation Identity | Tenant/Fabric Admin | Client ID, credential, token test, expiry | Entra token + Fabric API | HLP-AUTH-002 |
| SC-005 | Fabric Readiness | Fabric Admin | Capacity, tenant settings, roles, workspace capability, blockers | Fabric read APIs | HLP-FAB-001 |
| SC-006 | Tenant Settings | Fabric Admin | Required settings, current/effective state, scope, action mode | GET tenant settings; preview update optional | HLP-FAB-002 |
| SC-007 | Capacity | Fabric/Azure Admin | Existing capacities, SKU, region, state; provision option | Fabric Core + Azure ARM | HLP-FAB-003 |
| SC-008 | Workspace Topology | Data Platform Admin | DEV/TEST/PROD names, capacity, domain, admin groups | Workspace APIs | HLP-FAB-004 |
| SC-009 | Workspace Access | Data Platform Admin | Users/groups/SPs and roles | Workspace role APIs | HLP-FAB-005 |
| SC-010 | Source Catalogue | Data Owner | Source type, domain, owner, criticality, update frequency | Semantiq + connector discovery | HLP-SRC-001 |
| SC-011 | Connection Setup | Data Platform Admin | Fabric connection fields, credentials, privacy, test | Connections API | HLP-SRC-002 |
| SC-012 | Gateway Setup | Data Platform Admin | Gateway type, VNet/on-prem status, capacity, members | Gateway APIs / guided install | HLP-GWY-001 |
| SC-013 | Schema Discovery | Data Steward | Tables/files, columns, keys, volumes, sensitivity hints | Source/Fabric metadata | HLP-SRC-003 |
| SC-014 | Ingestion Plan | Data Engineer/Admin | Method, target, schedule, incremental key, retry policy | Pipeline/Item/Job APIs | HLP-ING-001 |
| SC-015 | Lakehouse & Layers | Data Platform Admin | Lakehouse, Bronze/Silver/Gold layout, naming rules | Lakehouse API | HLP-LKH-001 |
| SC-016 | Data Quality | Data Steward | Profile, null/duplicate/range rules, severity, thresholds | Generated notebook/pipeline rules | HLP-DQ-001 |
| SC-017 | Business Entity Mapping | Data Steward/BA | Entity, business key, source fields, canonical fields | Semantiq metadata + model generation | HLP-MDL-001 |
| SC-018 | Semantic Model Studio | Semantic Owner | Facts/dimensions, relationships, measures, names, descriptions | Semantic Model API | HLP-SEM-001 |
| SC-019 | Security & Governance | Security Admin | RLS/OLS, sensitivity, role mappings, access review | Model + workspace APIs | HLP-SEC-001 |
| SC-020 | AI Readiness | AI Owner | Approved tables/columns/measures, instructions, synonyms, verified questions | Definition/config generation | HLP-AI-001 |
| SC-021 | Fabric Data Agent | AI Owner | Agent name, sources, instructions, examples, publish state | DataAgent APIs | HLP-AGT-001 |
| SC-022 | Validation Centre | Owners/QA | Ground truth, security tests, data checks, regression score | Jobs + comparison engine | HLP-VAL-001 |
| SC-023 | Deployment | Platform Admin | Deployment pipeline, stage mapping, release gate, approvals | Deployment Pipeline APIs | HLP-DEP-001 |
| SC-024 | Operations Monitor | Platform Admin | Capacity, jobs, refresh, failures, data quality, agent accuracy | Fabric/Power BI metrics + Semantiq telemetry | HLP-OPS-001 |
| SC-025 | Help Centre | All roles | Context help, step guides, troubleshooting, prerequisites | Semantiq content service | - |
| SC-026 | Audit Log | Admins/Auditors | Who, what, when, target, API, result, correlation ID | Semantiq audit store | HLP-AUD-001 |



## 7.2 Standard Screen Pattern

- Header: customer organisation, tenant, environment (DEV/TEST/PROD), connection health and last refresh time.
- Step status banner: Ready / Action Required / Running / Failed / Complete / Warning.
- Prerequisite panel: required role, permission, licence/capacity, dependent resources and Microsoft feature status.
- Configuration form: business-friendly labels with advanced technical fields collapsed by default.
- Automation panel: action to be performed, API method/resource, impact, estimated duration, approval requirement and rollback note.
- Help panel: step-by-step manual instructions with portal navigation and verification steps.
- Footer actions: Save Draft, Validate, Run/Test, Apply, Re-check, View Logs and Get Help.

# 8. Functional Requirements by Module

8.1 Tenant & Identity Requirements


| ID | Requirement | Priority | Acceptance / Notes |
| --- | --- | --- | --- |
| FR-AUTH-001 | Support Microsoft Entra SSO for Semantiq users using authorization code flow with PKCE. | Must | User signs in without a browser-held client secret. |
| FR-AUTH-002 | Resolve and persist the customer tenant ID from verified token claims and onboarding configuration. | Must | Tenant mismatch blocks privileged operations. |
| FR-AUTH-003 | Support tenant-wide admin consent workflow for the Semantiq enterprise application where required. | Must | UI shows granted status after re-check. |
| FR-AUTH-004 | Support a customer-owned Fabric automation service principal for unattended operations. | Must | Tenant ID, client ID and credential can be tested server-side. |
| FR-AUTH-005 | Support certificate credentials and client-secret credentials; prefer certificate for production. | Must | Credential type and expiry are visible; secret value is never redisplayed. |
| FR-AUTH-006 | Acquire Fabric tokens using scope https://api.fabric.microsoft.com/.default. | Must | Token test succeeds and token content is not logged. |
| FR-AUTH-007 | Store automation credentials only in an encrypted secret-management service. | Must | Database contains reference/secret ID, not plaintext secret. |
| FR-AUTH-008 | Provide test actions for token acquisition, Fabric connectivity and permission diagnosis. | Must | Result distinguishes authentication, tenant-setting, workspace-role and API-support failures. |
| FR-AUTH-009 | Monitor secret/certificate expiry and create proactive alerts. | Must | Alerts at configurable 30/14/7-day thresholds. |
| FR-AUTH-010 | Allow credential rotation without deleting customer configuration. | Must | New credential validated before activation. |
| FR-AUTH-011 | Map Semantiq application roles to customer users/groups separately from Fabric roles. | Must | Semantiq role does not imply Fabric privilege. |
| FR-AUTH-012 | Support customer offboarding that disables tokens, removes stored credentials and optionally removes Semantiq service-principal access from Fabric. | Must | Offboarding checklist records completion. |


8.2 Fabric Readiness & Environment Requirements


| ID | Requirement | Priority | Acceptance / Notes |
| --- | --- | --- | --- |
| FR-FAB-001 | Run a Fabric Readiness Assessment immediately after integration. | Must | Assessment returns capacity, roles, tenant settings, workspaces, API access and blockers. |
| FR-FAB-002 | List Fabric capacities accessible to the principal and show ID, name, SKU, region and state. | Must | Uses Fabric Core capacities API. |
| FR-FAB-003 | Allow selection of an existing Fabric capacity. | Must | Selected capacity stored and validated as active. |
| FR-FAB-004 | Optionally provision a new Fabric capacity through Azure Resource Manager if the customer grants the required Azure RBAC. | Must | Provisioning is disabled if Azure permission is absent. |
| FR-FAB-005 | Create a workspace through the Fabric REST API with display name, description and selected capacity when supported. | Must | Created workspace ID is stored. |
| FR-FAB-006 | Create DEV, TEST and PROD workspaces from a configurable naming template. | Must | No duplicate created if matching tagged/recorded workspace exists. |
| FR-FAB-007 | Assign a workspace to a Fabric capacity. | Must | Assignment status confirmed after long-running operation. |
| FR-FAB-008 | Add/update/delete workspace role assignments for approved users, groups and service principals. | Must | Last admin protection and least-privilege checks enforced. |
| FR-FAB-009 | Read Fabric tenant settings required by Semantiq and show effective state and scope. | Must | Uses Admin list tenant settings API where authorised. |
| FR-FAB-010 | Support a feature-flagged tenant-setting update capability only when Microsoft public API is enabled for production use by product policy. | Must | Preview endpoint is not used silently; explicit warning and admin approval required. |
| FR-FAB-011 | Provide guided manual steps for tenant settings when API update is disabled or unsupported. | Must | Re-check verifies resulting state. |
| FR-FAB-012 | Detect whether service-principal Fabric tenant settings permit public API calls and workspace/connection/deployment-pipeline creation. | Must | Assessment identifies exact missing setting. |
| FR-FAB-013 | Support assignment of workspaces to Fabric domains when customer governance uses domains. | Should | Optional domain ID stored and applied via API. |
| FR-FAB-014 | Capture Fabric capacity and workspace region to detect cross-region constraints. | Must | Region mismatches shown before deployment. |
| FR-FAB-015 | Provide a dry-run plan before creating Fabric assets. | Must | User sees all proposed names, roles and resources before Apply. |
| FR-FAB-016 | Tag/record every Semantiq-managed Fabric resource in the control plane for lifecycle tracking. | Must | Resource record contains Fabric ID, type, workspace, environment and version. |
| FR-FAB-017 | Support discovery/import of an existing Fabric estate rather than forcing new resource creation. | Must | Existing workspaces/items can be mapped to Semantiq stages. |
| FR-FAB-018 | Run post-provision verification and produce a readiness score with blockers/warnings. | Must | All mandatory checks must be green before source onboarding. |


8.3 Source Connectivity & Ingestion Requirements


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
| FR-SRC-009 | Create Bronze landing structure and ingestion definitions. | Must | Source-to-target mapping recorded. |
| FR-SRC-010 | Support incremental loading using timestamps, IDs, CDC or source-specific methods where available. | Must | Incremental key/watermark persisted. |
| FR-SRC-011 | Support schedule definition and item/job scheduling where supported. | Must | Schedule can be enabled/disabled and inspected. |
| FR-SRC-012 | Implement retries, failure paths, reject handling and notifications. | Must | Failure includes source, object, run ID and remediation action. |
| FR-SRC-013 | Detect schema drift and classify as compatible, warning or breaking change. | Must | Breaking change pauses promotion until reviewed. |
| FR-SRC-014 | Maintain ingestion audit history and lineage from source object to Bronze target. | Must | History searchable by source/run. |
| FR-SRC-015 | Support on-demand run and scheduled run for supported Fabric items. | Must | Job state is polled and persisted. |


8.4 Data Quality & Modelling Requirements


| ID | Requirement | Priority | Acceptance / Notes |
| --- | --- | --- | --- |
| FR-DQ-001 | Create or attach a Lakehouse for each configured domain/environment. | Must | Lakehouse ID and SQL endpoint metadata stored. |
| FR-DQ-002 | Create logical Bronze, Silver and Gold conventions. | Must | Naming and storage paths follow configured standard. |
| FR-DQ-003 | Profile ingested data for nulls, duplicates, data types, ranges, distinct values and referential integrity. | Must | Profiling produces per-column metrics. |
| FR-DQ-004 | Suggest cleansing rules for duplicate removal, null handling, code/date normalisation and invalid-record handling. | Must | Rules require steward approval before production. |
| FR-DQ-005 | Support rule severity (Info/Warning/Error) and pass threshold. | Must | Quality gate can block Gold promotion. |
| FR-DQ-006 | Standardise canonical business entities such as Customer, Employee, Product, Course, Learner, Supplier and Transaction. | Must | Source fields map to canonical entity fields. |
| FR-DQ-007 | Create/commonise business keys required to join multi-source records. | Must | Key strategy documented and testable. |
| FR-DQ-008 | Generate transformation implementations using Dataflow Gen2, Notebook/Spark, SQL or Pipeline according to selected pattern. | Must | Generated artifact is versioned. |
| FR-DQ-009 | Generate fact/dimension recommendations and star-schema candidates. | Must | User can accept, edit or reject recommendation. |
| FR-DQ-010 | Maintain full model/version history and impact analysis. | Must | Changes show downstream tables/models/agents affected. |
| FR-DQ-011 | Preserve raw source data in Bronze subject to customer retention policy. | Must | No Silver/Gold cleaning overwrites raw lineage. |
| FR-DQ-012 | Expose data-quality scorecards to the Operations Monitor. | Must | Score by source/domain/table with trend. |


8.5 Governance & Semantic Intelligence Requirements


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


8.6 AI & Fabric Data Agent Requirements


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


8.7 Deployment, Operations & Lifecycle Requirements


| ID | Requirement | Priority | Acceptance / Notes |
| --- | --- | --- | --- |
| FR-OPS-001 | Create Fabric deployment pipeline with DEV, TEST and PROD stages. | Must | Pipeline ID and stage IDs stored. |
| FR-OPS-002 | Assign workspaces to deployment pipeline stages. | Must | Topology matches Semantiq environment model. |
| FR-OPS-003 | Support controlled stage deployment with pre-check, approval and post-check. | Must | Production deployment cannot bypass configured approver. |
| FR-OPS-004 | Display ingestion job status, duration, row/volume metrics and failures. | Must | Monitor supports date/source/environment filters. |
| FR-OPS-005 | Display Fabric capacity state and workload indicators available through approved metrics sources. | Must | Capacity health warning shown before saturation risk. |
| FR-OPS-006 | Display data-quality breaches and trend. | Must | Critical breach can block downstream deployment. |
| FR-OPS-007 | Display semantic/agent regression score and failed questions. | Must | Agent marked degraded if threshold breached. |
| FR-OPS-008 | Implement alert rules for failed jobs, stale data, credential expiry and quality threshold breaches. | Must | Alert routing configurable. |
| FR-OPS-009 | Provide audit trail for configuration changes and API actions. | Must | Audit entry includes actor, target, before/after metadata, result and correlation ID. |
| FR-OPS-010 | Support environment backup/export of Semantiq configuration and Fabric public definitions where available. | Should | Export package is versioned. |
| FR-OPS-011 | Support change impact analysis and mandatory revalidation after source/model/security/agent changes. | Must | Affected components listed. |
| FR-OPS-012 | Support graceful customer offboarding and revoke Semantiq-managed access. | Must | No orphaned stored credentials. |


8.8 Help Centre & Guided Administration Requirements


| ID | Requirement | Priority | Acceptance / Notes |
| --- | --- | --- | --- |
| FR-HLP-001 | Every setup/configuration screen must have a context-sensitive Help action. | Must | Help opens at exact topic for current screen. |
| FR-HLP-002 | Each help topic must show prerequisites, required role, expected duration and impact. | Must | Displayed before procedural steps. |
| FR-HLP-003 | Help topics must include exact Microsoft portal navigation paths and field labels. | Must | User can follow without external interpretation. |
| FR-HLP-004 | Help topics must provide copyable values such as redirect URI, token scope and API endpoint when relevant. | Must | Copy button copies exact value. |
| FR-HLP-005 | Help topics must include a verification step that maps back to Re-check in Semantiq. | Must | Topic ends with expected successful state. |
| FR-HLP-006 | Help topics must include common error messages and troubleshooting. | Must | At least authentication, permission, tenant-setting and expired-credential cases for identity topics. |
| FR-HLP-007 | Preview or high-privilege Microsoft features must be explicitly labelled. | Must | No preview API presented as guaranteed production automation. |
| FR-HLP-008 | Help content must be versioned and record the Microsoft documentation date/reference used. | Must | Topic shows last reviewed date. |
| FR-HLP-009 | The UI must deep-link from an API error to the most relevant help topic. | Must | Error category mapped to topic ID. |
| FR-HLP-010 | Administrators must be able to export an onboarding runbook showing all remaining manual steps. | Should | Runbook generated from current state. |



# 9. Fabric Provisioning and Configuration Requirements


## 9.1 Fabric Readiness Assessment Checks


| Check | Pass condition | Severity |
| --- | --- | --- |
| Tenant identity | Tenant ID matches onboarding configuration. | Blocker |
| Fabric access token | Token acquired for Fabric audience. | Blocker |
| Capacity | At least one active compatible Fabric capacity available, or Azure provisioning path available. | Blocker |
| Fabric administrator | A Fabric admin is available for tenant-level settings. | Blocker for tenant configuration |
| Service-principal public API setting | Required Fabric developer tenant setting permits the automation identity. | Blocker |
| Service-principal workspace/connection/deployment setting | Required if Semantiq uses SP to create those resources. | Blocker for those actions |
| Workspace creation permission | Caller/service principal permitted to create workspace. | Blocker |
| Capacity permission | Caller/service principal has contributor/admin access required by chosen capacity. | Blocker |
| DEV/TEST/PROD topology | Existing workspaces discovered or names available for creation. | Warning/Action |
| Region | Capacity/workspace region compatible with target architecture. | Warning/Blocker |
| AI settings | Required Fabric AI/Data Agent tenant settings enabled as applicable. | Blocker for agent phase |
| Licensing | Fabric/Power BI licences available for required users and workloads. | Warning/Blocker |
| Network | Required gateway/private connectivity path available. | Source-specific blocker |



## 9.2 Tenant Settings Policy

Semantiq must read tenant settings using the Fabric Admin tenant settings API when authorised. Microsoft currently exposes a public preview API to update a tenant setting. Because the update API is explicitly preview and not recommended for production use, the product must treat writes as feature-flagged. The default production pattern is: detect -> explain -> guide admin -> re-check. A future stable Microsoft API may be enabled without redesigning the user workflow.


| Setting / control | Why Semantiq checks it | Default action |
| --- | --- | --- |
| Service principals can call Fabric public APIs | Required for service-principal REST calls protected by Fabric permission model. | Detect; guide admin if disabled. |
| Service principals can create workspaces, connections, and deployment pipelines | Required for SP-based creation of those core resources. | Detect; guide admin if disabled. |
| Admin API settings for service principals | Needed only if Semantiq uses admin APIs with SP. | Enable only if required by product function. |
| Copilot/Fabric AI/Data Agent settings | Required to use AI/Data Agent features depending tenant/region policy. | Detect and guide. |
| Cross-geo AI processing/storage | May be required by customer region and Microsoft AI service location. | Never enable automatically without explicit policy approval. |



## 9.3 Capacity Provisioning

When a customer already has a Fabric capacity, Semantiq lists accessible capacities and the customer selects one. When no capacity exists, Semantiq may offer Azure-based capacity provisioning. Capacity creation is an Azure Resource Manager operation for Microsoft.Fabric/capacities and requires an Azure subscription/resource-group context and sufficient Azure RBAC. This is separate from Fabric workspace permissions.

- Fields: Azure tenant, subscription, resource group, capacity name, region, SKU, administrators/tags and cost acknowledgement.
- Validation: provider availability, SKU/region support, naming, quota, customer RBAC and billing acknowledgement.
- Approval: capacity creation must always be an explicit privileged action.
- Post-check: call Fabric List/Get Capacity API and confirm the capacity is Active before workspace assignment.

## 9.4 DEV / TEST / PROD Workspace Template


| Environment | Default naming | Purpose | Minimum Semantiq automation role |
| --- | --- | --- | --- |
| DEV | {Org}-{Domain}-DEV | Build and iterate ingestion, models and agents. | Contributor (Admin only where role assignment/config requires it) |
| TEST | {Org}-{Domain}-TEST | Integration, security, regression and business acceptance. | Contributor / controlled deployment access |
| PROD | {Org}-{Domain}-PROD | Approved production data intelligence. | Least privilege; production write only through release workflow |



# 10. Data Source, Ingestion and Lakehouse Requirements


## 10.1 Source Onboarding Wizard


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
| 9 | Choose ingestion pattern | Full + incremental, CDC, schedule or near-real-time. |
| 10 | Preview generated plan | Target Lakehouse, Bronze objects, pipeline/notebook, schedule, errors and naming. |
| 11 | Deploy | Create Fabric artifacts and run initial load. |
| 12 | Validate | Check row counts, schema, freshness and quality baseline. |



## 10.2 Medallion Layer Requirements


| Layer | Purpose | Semantiq rule |
| --- | --- | --- |
| Bronze / Raw | Preserve source fidelity and lineage. | Minimal transformation; retain source object, load time, source key and ingestion run metadata. |
| Silver / Clean | Standardise and validate data. | Apply approved type, null, duplicate, format, reference and entity-standardisation rules. |
| Gold / Business | Create analytics-ready business models. | Facts, dimensions, calculated fields, conformed keys and business-grain definitions. |



## 10.3 Ingestion Operational Requirements

- Every run has a globally unique run/correlation ID.
- Run log captures start/end time, source, source object, target, bytes/rows where available, watermark, warnings, rejects and error payload.
- Retries use exponential/backoff policy while respecting Microsoft Retry-After headers.
- Schema drift is checked before transformation; breaking drift pauses downstream promotion.
- Initial full load and recurring incremental load are separately identifiable.
- Data freshness SLA is stored per source and monitored in Operations.

# 11. Data Quality, Business Modelling and Semantic Layer


## 11.1 Data Quality Rule Model


| Rule property | Examples / behaviour |
| --- | --- |
| Rule type | Not Null, Unique, Range, Pattern, Referential Integrity, Allowed Values, Freshness, Row Count, Duplicate, Custom SQL/Spark. |
| Scope | Column, table, entity or ingestion batch. |
| Severity | Info, Warning, Error, Critical. |
| Threshold | 100% pass, maximum failure %, min/max count or custom expression. |
| Action on fail | Log only, quarantine/reject, stop Silver promotion, stop Gold promotion, alert owner. |
| Owner | Data steward or business owner. |
| Version | Effective date, change reason, approver. |



## 11.2 Business Entity Mapping

Semantiq must create a reusable canonical business layer so that the same product can work across industries. The app should propose mappings based on metadata and names, but an authorised data steward confirms the mapping before the model is certified.


| Canonical entity | Typical source aliases | Typical key / relationships |
| --- | --- | --- |
| Customer | Account, Client, Organisation, Buyer | Customer ID; links to sales/orders/payments/cases. |
| Product / Service | SKU, Item, Offering, Course | Product/Service ID; links to transactions and pricing. |
| Employee / User | Staff, Agent, Consultant, Faculty | Employee/User ID; links to departments and activities. |
| Learner | Student, Participant, Candidate | Learner ID; links to courses, enrolments, attendance, assessments. |
| Transaction | Sale, Invoice, Payment, Order, Booking | Transaction ID; links to customer, product and date. |
| Organisation Unit | Company, Department, Region, Business Unit | Org Unit ID; hierarchy and security scope. |
| Calendar | Date, Period, Fiscal Period | Date key; shared date dimension. |



## 11.3 Semantic Model Studio Detailed Requirements

- Model canvas shows facts, dimensions, relationship cardinality/filter direction and hidden technical objects.
- Measure editor captures name, DAX/expression, format, business definition, owner, synonyms and test values.
- Field catalogue captures source field, business name, description, data type, classification and expose-to-AI flag.
- Business glossary is bidirectionally linked: selecting a glossary term shows semantic objects, and selecting an object shows approved terms.
- Security tab defines RLS roles and object restrictions; test-as-role/user is required before approval.
- Publish action creates/updates the semantic model definition and records the deployed version.

# 12. AI Readiness, Fabric Data Agent and Conversational Intelligence


## 12.1 AI Readiness Workspace


| Configuration | Requirement |
| --- | --- |
| AI scope | Only approved semantic tables/columns/measures or approved Lakehouse/Warehouse sources are exposed. |
| Business instructions | Generated from glossary and business rules, then reviewed by AI owner. |
| Synonyms | Business vocabulary, abbreviations, local terminology and common misspellings. |
| Verified questions | High-value management questions with approved interpretation and expected result/logic. |
| Ground truth set | Regression test pack with question, filters, expected result, tolerance and source snapshot/version. |
| Restrictions | Topics/data the agent must not answer, including unavailable or restricted fields. |
| Answer style | Narrative, table, chart suggestion, units, date context and source/lineage citation behaviour. |



## 12.2 Fabric Data Agent Automation

Microsoft Fabric now exposes Data Agent create, get definition, update definition and publish operations through the Fabric REST API. Semantiq should generate the public definition from approved configuration, store a versioned copy, deploy it to the target workspace, then publish only after validation. Definition handling must be treated as a versioned deployment artifact rather than an opaque manual configuration.


| Operation | Fabric API pattern | Semantiq behaviour |
| --- | --- | --- |
| Create agent | POST /v1/workspaces/{workspaceId}/dataAgents | Create shell and store Data Agent ID. |
| Get definition | POST .../dataAgents/{dataAgentId}/getDefinition | Backup/inspect current public definition before changes. |
| Update definition | POST .../dataAgents/{dataAgentId}/updateDefinition | Deploy generated configuration and poll LRO if 202. |
| Publish | Data Agent publish operation | Publish validated staging configuration after approval. |
| List/Get | Data Agent item APIs | Synchronise external changes and detect drift. |



## 12.3 Validation Scoring


| Test dimension | Example | Pass rule |
| --- | --- | --- |
| Data correctness | Total revenue last month | Exact or defined numerical tolerance. |
| Filter correctness | Show only Singapore | No out-of-scope records. |
| Time intelligence | Compare with same quarter last year | Correct period and measure. |
| Ranking | Top 5 customers by margin | Correct order and tie handling. |
| Follow-up context | Which customer caused that change? | Retains prior period/filter context. |
| Security | Restricted user asks for payroll | No restricted data returned. |
| Unsupported question | Question outside approved scope | Agent states limitation instead of fabricating. |



# 13. Security, Governance and Compliance


### 12.4 AI and Conversational Technology Selection Reference

All implementation work under AI Readiness, Fabric Data Agent and Conversational Intelligence must also follow [`AI_CONVERSATIONAL_TECHNOLOGY_GUIDE.md`](AI_CONVERSATIONAL_TECHNOLOGY_GUIDE.md). The guide defines the Microsoft-first stack, open-source alternatives, architecture controls and the mandatory AI technology decision record. The SRS does not hard-code a general-purpose LLM or orchestration framework; the selected technology must be re-evaluated and user-approved for the specific scenario.

## 13.1 Security Requirements


| ID | Requirement |
| --- | --- |
| SEC-001 | All external traffic uses TLS 1.2+; HSTS for browser application. |
| SEC-002 | Secrets and certificates are stored in approved vault/KMS and encrypted at rest. |
| SEC-003 | Browser never receives automation client secret or Fabric bearer token intended for backend service use. |
| SEC-004 | Least-privilege roles are applied independently in Semantiq, Entra, Azure and Fabric. |
| SEC-005 | Privileged operations require step-up confirmation and an auditable approver where configured. |
| SEC-006 | Audit records are immutable to standard administrators and retained per customer policy. |
| SEC-007 | Customer data-plane IDs and metadata are organisation/tenant-scoped in SemantIQ; no cross-organisation lookup is allowed. This boundary is mandatory even in the current single-customer deployment. |
| SEC-008 | RLS/OLS security tests are mandatory before production AI publication. |
| SEC-009 | Credentials must be rotatable without application downtime. |
| SEC-010 | Sensitive configuration values are masked in UI, logs and support exports. |
| SEC-011 | Semantiq support access is time-bound, customer-approved and auditable when support access to tenant context is required. |
| SEC-012 | Destructive Fabric actions require explicit confirmation and should not be part of default automated onboarding. |



## 13.2 Governance Requirements

- Every source, dataset, semantic model, KPI and Data Agent has an owner and lifecycle state.
- Lineage must be traceable from source system through Bronze/Silver/Gold to semantic model and AI source.
- Business glossary definitions must have owner, approval state and effective date.
- Production changes require change reason, approver and regression evidence.
- Sensitivity/classification should be captured even where platform-level label automation is not yet available.
- AI is for governed insight by default; write-back, business transactions or autonomous decisions are out of baseline scope unless separately approved.

# 14. Deployment, Monitoring and Lifecycle Management


## 14.1 Deployment Workflow

1. DEV build complete -> automated technical checks -> submit for TEST.
1. Deploy to TEST -> run ingestion/data-quality/security/semantic/agent regression pack.
1. Business owner approves definitions and expected answers.
1. Security owner approves role/RLS/OLS test evidence.
1. Platform owner approves production plan and impact summary.
1. Deploy to PROD through Fabric deployment pipeline where supported.
1. Run smoke test and update release status to Live.
1. If post-check fails, mark release Degraded/Failed and execute configured rollback/restore process where supported.

## 14.2 Operations Dashboard


| Monitor domain | Key indicators |
| --- | --- |
| Fabric connectivity | Token/API availability, tenant-setting drift, workspace access. |
| Capacity | Capacity state, configured SKU/region, available utilisation indicators. |
| Ingestion | Success rate, duration, stale sources, incremental watermark, failed/rejected rows. |
| Data quality | Pass score, critical rule failures, trending deterioration. |
| Semantic model | Deployment version, refresh/deployment state, breaking-change warnings. |
| Agent quality | Ground-truth pass %, failed questions, unsupported questions, regression trend. |
| Security | Access changes, RLS test status, privileged changes. |
| Credentials | Secret/certificate expiry and connection credential failures. |
| Usage | Questions, active users, datasets/models/agents used, subject to privacy policy. |



# 15. In-App Help Centre and Guided Configuration


## 15.1 Help Topic Standard Template


| Help section | Content requirement |
| --- | --- |
| Topic ID / title | Stable identifier and task-oriented title. |
| Why this is required | Plain-language purpose and effect on Semantiq/Fabric. |
| Who can do it | Required Microsoft Entra/Fabric/Azure role. |
| Prerequisites | Tenant, licence, capacity, existing IDs, permissions or network requirements. |
| Where to go | Exact Microsoft portal navigation path. |
| Steps | Numbered, field-by-field instructions. |
| Values to copy | Pre-filled Semantiq-specific redirect URI, IDs, scope or group name with Copy buttons. |
| Security note | What permission is being granted and why. |
| Expected result | What the user should see in Microsoft portal. |
| Verify in Semantiq | Re-check action and expected green state. |
| Troubleshooting | Common Microsoft errors and likely causes. |
| Microsoft reference | Official source URL and last reviewed date. |



## 15.2 Required Help Topics


| Topic ID | Help topic |
| --- | --- |
| HLP-SSO-001 | Set up Semantiq SSO and grant tenant admin consent |
| HLP-AUTH-002 | Create the Fabric Automation App Registration |
| HLP-AUTH-003 | Create a certificate or client secret and connect it to Semantiq |
| HLP-FAB-001 | Run the Fabric Readiness Assessment |
| HLP-FAB-002 | Enable required Fabric service-principal tenant settings |
| HLP-FAB-003 | Select or create a Fabric capacity |
| HLP-FAB-004 | Create DEV, TEST and PROD workspaces |
| HLP-FAB-005 | Grant the Semantiq service principal workspace access |
| HLP-SRC-002 | Create and test a Fabric connection |
| HLP-GWY-001 | Configure an on-premises or VNet gateway |
| HLP-ING-001 | Create an ingestion plan and schedule |
| HLP-LKH-001 | Create Lakehouse and Bronze/Silver/Gold layout |
| HLP-DQ-001 | Review and approve data-quality rules |
| HLP-SEM-001 | Review the generated semantic model |
| HLP-SEC-001 | Configure and test RLS/OLS |
| HLP-AI-001 | Prepare approved data and business instructions for AI |
| HLP-AGT-001 | Create, configure, validate and publish a Fabric Data Agent |
| HLP-DEP-001 | Create deployment pipeline and promote DEV -> TEST -> PROD |
| HLP-OPS-001 | Troubleshoot failed Fabric API or job runs |



# 16. API and Automation Specification


## 16.1 Authentication Endpoints


| Purpose | Endpoint / scope | Method / behaviour |
| --- | --- | --- |
| Service-principal token | https://login.microsoftonline.com/{tenantId}/oauth2/v2.0/token | POST form-urlencoded client_credentials. |
| Fabric token scope | https://api.fabric.microsoft.com/.default | Used during token acquisition; not an API URL. |
| Interactive SSO | Microsoft identity platform /authorize and /token endpoints | Authorization code + PKCE; tenant-specific authority after onboarding. |
| Admin consent | Tenant-specific Microsoft Entra admin consent flow | Open browser flow; admin reviews configured permissions. |



## 16.2 Fabric API Register


| ID | Operation | Pattern | Mode | Use in Semantiq |
| --- | --- | --- | --- | --- |
| API-001 | List capacities | GET /v1/capacities | AUTO / read | Discover accessible capacity, SKU, region and state. |
| API-002 | Create workspace | POST /v1/workspaces | AUTO / approval | Create DEV/TEST/PROD workspace. |
| API-003 | Assign workspace to capacity | POST /v1/workspaces/{workspaceId}/assignToCapacity | AUTO / approval | Bind workspace to selected capacity. |
| API-004 | Workspace role assignment | POST /v1/workspaces/{workspaceId}/roleAssignments | AUTO / approval | Grant service principal/user/group role. |
| API-005 | List tenant settings | GET /v1/admin/tenantsettings | AUTO / read | Read effective tenant settings when authorised. |
| API-006 | Update tenant setting | POST /v1/admin/tenantsettings/{tenantSettingName}/update | PREVIEW / feature-flag | Preview; default product behaviour is guided manual + re-check. |
| API-007 | Create connection | POST /v1/connections | AUTO / approval | Create cloud/on-prem/VNet Fabric connection. |
| API-008 | Create gateway | POST /v1/gateways | AUTO where supported | VNet/streaming VNet gateway; on-prem software install still guided. |
| API-009 | Create Lakehouse | POST /v1/workspaces/{workspaceId}/lakehouses | AUTO | Provision Lakehouse. |
| API-010 | Create Data Pipeline | POST /v1/workspaces/{workspaceId}/dataPipelines | AUTO | Create pipeline item; deploy definition as supported. |
| API-011 | Run item job | POST /v1/workspaces/{workspaceId}/items/{itemId}/jobs/{jobType}/instances | AUTO | Run on demand; honour Retry-After. |
| API-012 | Create item schedule | POST /v1/workspaces/{workspaceId}/items/{itemId}/jobs/{jobType}/schedules | AUTO | Create supported schedule. |
| API-013 | Create semantic model | POST /v1/workspaces/{workspaceId}/semanticModels | AUTO | Requires definition; version before deployment. |
| API-014 | Create Data Agent | POST /v1/workspaces/{workspaceId}/dataAgents | AUTO | Create Data Agent; supports LRO. |
| API-015 | Get Data Agent definition | POST .../dataAgents/{dataAgentId}/getDefinition | AUTO | Backup/synchronise definition. |
| API-016 | Update Data Agent definition | POST .../dataAgents/{dataAgentId}/updateDefinition | AUTO / approval | Deploy approved public definition. |
| API-017 | Publish Data Agent | Data Agent publish endpoint | AUTO / release approval | Publish staging configuration after validation. |
| API-018 | Create deployment pipeline | POST /v1/deploymentPipelines | AUTO / approval | Create DEV/TEST/PROD stages. |
| API-019 | Deployment pipeline stage operations | Deployment pipeline APIs | AUTO / approval | Assign workspaces and deploy stage content. |



## 16.3 Azure Resource Manager Integration

Fabric capacity provisioning uses the Azure resource provider for Microsoft.Fabric/capacities rather than the Fabric Core capacity-list API. Semantiq must therefore treat Azure subscription access as a separate optional integration. If not authorised, the Capacity screen presents a guided manual provisioning path and then re-runs Fabric capacity discovery.


## 16.4 Optional Microsoft Graph Integration

Microsoft Graph is optional and should be used only where it reduces manual administration, for example resolving Entra group object IDs or discovering SharePoint sites/files. The product should prefer explicit object IDs or Fabric-native connections to avoid requesting broad Graph permissions unnecessarily. Any Graph permission must be documented separately and require admin consent when Microsoft requires it.


## 16.5 API Client Requirements

- Centralise base URL, API version and typed request/response models.
- Generate a correlation ID for every Semantiq workflow step and include Microsoft request IDs in logs.
- Handle HTTP 202 as a long-running operation, store Location/x-ms-operation-id and poll after Retry-After.
- Handle HTTP 429 by waiting for Retry-After; never busy-loop.
- Classify 401 as authentication, 403 as permission/tenant policy, 404 as resource/drift, 409 as conflict, and 5xx as transient unless API indicates non-retriable.
- Redact Authorization headers, secrets and credential payloads from logs.
- Implement capability flags per Fabric item/API because service-principal support can differ by operation or dependent item.
- Use idempotency logic at orchestration level by checking existing resources before create operations.

# 17. Semantiq Configuration Data Model


| Entity | Core fields / purpose |
| --- | --- |
| Organisation | organisation_id, name, status, region, owner_user_id, created_at |
| TenantIntegration | tenant_id, primary_domain, sso_mode, consent_status, authority, last_verified_at |
| AutomationIdentity | tenant_id, client_id, credential_type, secret_reference, expiry, status |
| FabricCapacity | fabric_capacity_id, display_name, sku, region, state, selected, last_seen_at |
| FabricWorkspace | workspace_id, environment, display_name, capacity_id, domain_id, role_state, managed_flag |
| TenantSettingSnapshot | setting_name, title, enabled, groups, scope, checked_at |
| SourceSystem | source_id, type, domain, owner, criticality, refresh_sla, classification |
| FabricConnection | connection_id, source_id, connectivity_type, gateway_id, privacy_level, status |
| Gateway | gateway_id, type, display_name, region/capacity, health, version |
| DiscoveredObject | source_object_id, source_id, object_name, object_type, schema, selected, profile_version |
| IngestionDefinition | ingestion_id, source_object_id, target, method, schedule, incremental_strategy, watermark |
| FabricItem | item_id, workspace_id, type, display_name, environment, definition_version, status |
| DataQualityRule | rule_id, scope, rule_type, expression, threshold, severity, owner, status, version |
| BusinessEntity | entity_id, name, definition, business_key, owner, status |
| FieldMapping | mapping_id, source_field, canonical_field, transformation, confidence, approval |
| BusinessGlossaryTerm | term_id, name, definition, synonyms, owner, status, effective_date |
| SemanticObject | semantic_model_id, object_type, technical_name, business_name, description, expose_to_ai |
| MeasureDefinition | measure_id, name, expression, format, business_definition, owner, status |
| SecurityPolicy | policy_id, model_id, role_name, policy_type, expression/object list, principal mapping |
| VerifiedQuestion | question_id, domain, question, expected_logic/result, tolerance, owner, status |
| DataAgent | data_agent_id, workspace_id, name, definition_version, publish_state, validation_score |
| ValidationRun | validation_run_id, target_type, target_id, version, score, started_at, completed_at, result |
| Deployment | deployment_id, pipeline_id, source_stage, target_stage, version, approver, result |
| WorkflowRun | workflow_run_id, workflow_type, organisation_id, status, current_step, correlation_id |
| AuditEvent | audit_id, actor, action, target, before_hash, after_hash, api_request_id, result, timestamp |
| HelpTopic | topic_id, title, product_version, microsoft_reference, last_reviewed_at, content_version |



# 18. Error Handling, Status and Orchestration


## 18.1 Standard Status Model


| Status | Meaning | UI action |
| --- | --- | --- |
| Not Started | No configuration exists. | Start Setup |
| In Progress | User entered draft or workflow running. | Continue / View Progress |
| Action Required | Manual/admin prerequisite missing. | Open Help / Re-check |
| Approval Required | Privileged or release operation waiting approval. | Review & Approve |
| Ready | Prerequisites satisfied; action can run. | Run / Apply |
| Succeeded | Target verified. | Continue |
| Warning | Function works but risk or optional prerequisite exists. | Review warning |
| Failed | Operation did not complete. | Retry / View Logs / Help |
| Drift Detected | External change differs from Semantiq recorded configuration. | Compare / Accept / Restore |
| Revalidation Required | Upstream change invalidates prior tests. | Run Validation |



## 18.2 Error Classification


| Category | Typical symptom | User-facing guidance |
| --- | --- | --- |
| Authentication | 401, invalid_client, expired secret | Check tenant/client ID, credential, expiry and token scope. |
| Consent / Tenant policy | 403 or admin consent required | Open SSO/Fabric tenant settings help; show exact missing permission/setting if detectable. |
| Fabric permission | 403 despite valid token | Check workspace role, capacity permission, item permission and service-principal tenant setting. |
| Resource not found | 404 / EntityNotFound | Refresh discovery; resource may have moved/deleted; verify workspace/item ID. |
| Conflict | 409 / duplicate name | Show existing resource and offer Use Existing or choose new name. |
| Rate limit | 429 | Respect Retry-After and show scheduled retry. |
| Long-running failure | 202 operation eventually fails | Show operation ID, Microsoft request ID, target and remediation. |
| Unsupported feature | FeatureNotAvailable / operation not supported | Switch to guided path; do not repeatedly retry. |
| Data-quality failure | Quality threshold breached | Show failing rules and blocked downstream step. |
| Security regression | RLS/OLS test mismatch | Block production publish until corrected. |



# 19. Non-Functional Requirements


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



# 20. Acceptance Scenarios and Test Criteria


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



# 21. Release Scope and Product Roadmap


| Release | Scope | Notes |
| --- | --- | --- |
| MVP / Release 1 | SSO, customer automation identity, readiness assessment, capacity discovery, workspace setup, source catalogue, Fabric connections, Lakehouse, pipeline basics, data profiling, semantic model generation, Data Agent create/config/publish, validation, audit and help centre. | Focus on supported public APIs and guided fallback. |
| Release 2 | Advanced source connectors, richer schema-drift handling, automated star-schema recommendations, RLS/OLS design assistant, deployment pipelines, richer monitoring and channel integrations. | Expand automation after production validation. |
| Release 3 | Cross-domain intelligence, multi-agent orchestration, advanced insight recommendations, policy-as-code, expanded Fabric item types and autonomous remediation with approvals. | Subject to Microsoft API maturity and customer governance. |



# 22. Assumptions, Constraints and Out of Scope


## 22.1 Assumptions

- Customer provides appropriate Microsoft Fabric/Power BI licences and capacity for production use.
- Customer provides authorised Microsoft administrators to approve Entra, Fabric and Azure actions.
- Customer source systems expose supported documented connectivity methods.
- Customer owns classification and access policies, or agrees to define them during onboarding.
- Customer accepts that Microsoft preview APIs/features can change and are not enabled by default for production automation.
- Customer network/firewall rules allow required Microsoft endpoints or required private/gateway connectivity is provided.

## 22.2 Out of Baseline Scope

- Remediation of data quality at the original source application unless separately scoped.
- Building or replacing customer ERP/CRM/LMS/source systems.
- Unlimited historical migration beyond agreed retention/look-back period.
- Custom machine-learning model development unrelated to Fabric semantic/Data Agent intelligence.
- Autonomous write-back to operational systems or execution of financial/HR/business transactions.
- Bypassing customer Fabric/semantic security to return data to users.
- Automatic enabling of preview or high-risk tenant settings without explicit administrator approval.

# Appendix A. Detailed Help Topic: SSO and Fabric Automation Identity


| Help topic purpose: This content is intended to appear inside Semantiq when the user selects Help on the SSO Integration or Fabric Automation Identity screen. It is deliberately procedural and should be kept aligned with current Microsoft Entra and Fabric UI labels. |
| --- |



## A.1 HLP-SSO-001 - Set Up Semantiq SSO and Grant Tenant Admin Consent


### Who performs this task

A Microsoft Entra administrator authorised to grant consent for the permissions requested by the Semantiq enterprise application. The exact administrator role required depends on the requested permission type. Semantiq should display the permissions it requests before redirecting the administrator to Microsoft consent.


### Before you start

- Confirm the organisation Tenant ID shown in Semantiq.
- Confirm the Semantiq application name/publisher and requested permissions.
- Use a tenant administrator account; do not use a personal Microsoft account for tenant-wide admin consent.
- Confirm the Semantiq callback/redirect URI displayed in the Help panel.

### Steps

1.  In Semantiq, open Setup -> SSO & Consent.

2.  Confirm Organisation, Tenant ID and SSO mode.

3.  Select Review Permissions. Semantiq must show a readable list of delegated/application permissions it is requesting and why each is needed.

4.  Select Grant Admin Consent. Semantiq opens the Microsoft Entra tenant-specific admin consent experience in a separate browser window.

5.  Sign in with an authorised tenant administrator account if prompted.

6.  Review the publisher, application name and requested permissions. If they match the approved Semantiq integration, accept the organisation-wide consent.

7.  Return to Semantiq and select Re-check Consent.

8.  Semantiq verifies the tenant, application/enterprise application state and sign-in capability. The screen changes to Granted / Verified.

9.  Select Test SSO. Sign out and sign back in. Semantiq verifies issuer, audience, tenant and user/session mapping.

10.  If the organisation uses user assignment for enterprise applications, assign the approved users/groups in Microsoft Entra Enterprise applications and run Test SSO again.


### Expected result

Semantiq displays SSO Status = Verified, Tenant = expected tenant ID, Admin Consent = Granted (where required), and the test user can sign in without a new consent prompt.


### Common issues


| Issue | Likely cause | Resolution |
| --- | --- | --- |
| Admin consent button unavailable | Signed-in user lacks required admin role. | Use an appropriately authorised Entra administrator. |
| Redirect URI mismatch | Configured redirect URI does not exactly match application registration. | Compare Semantiq callback value with Entra Authentication settings. |
| User receives need admin approval | Permission requires administrator consent or user-consent policy blocks it. | Complete tenant admin consent. |
| Wrong tenant after sign-in | User switched directories or application is using incorrect authority. | Sign out, select correct tenant and verify Tenant ID. |
| User cannot access app after consent | Enterprise app may require assignment. | Assign user/group or change assignment policy per customer governance. |



## A.2 HLP-AUTH-002 - Create the Fabric Automation App Registration


| Recommended pattern: Use a separate customer-owned app registration for background Fabric automation. This isolates unattended privileges from interactive browser SSO and allows the customer to rotate/revoke the automation credential independently. |
| --- |



### Required administrator

A Microsoft Entra administrator or application owner who can create an app registration, plus a Fabric administrator/workspace administrator for the later Fabric permission steps.


### Create the app registration

1.  Open the Microsoft Entra admin center.

2.  Navigate to Entra ID -> App registrations -> New registration.

3.  Name the application using a customer-owned convention, for example: Semantiq Fabric Automation.

4.  For a customer-owned automation app, select Accounts in this organizational directory only unless the customer has an approved multi-tenant design.

5.  Do not configure a browser redirect URI for a service-principal-only automation app; it uses client credentials rather than interactive sign-in.

6.  Select Register.

7.  On Overview, copy the Application (client) ID and Directory (tenant) ID into the Semantiq Fabric Automation Identity screen.


### Create the credential

1.  Preferred: create a certificate credential and store the private key in the approved customer/vendor key vault used by Semantiq. Record the certificate thumbprint and expiry.

2.  MVP alternative: open Certificates & secrets -> Client secrets -> New client secret. Choose the shortest practical lifetime permitted by customer policy.

3.  Copy the secret VALUE immediately. Microsoft shows the value only once. Paste it into Semantiq over TLS and save.

4.  Semantiq stores the credential in its secret vault and shows only a masked reference/expiry after saving.

5.  Select Test Token. Semantiq requests a token from https://login.microsoftonline.com/{tenantId}/oauth2/v2.0/token using grant_type=client_credentials and scope=https://api.fabric.microsoft.com/.default.


### Enable the service principal in Fabric

1.  Sign in to Microsoft Fabric as a Fabric administrator.

2.  Open Settings (gear) -> Admin portal -> Tenant settings.

3.  Under Developer settings, locate the setting that allows service principals to call Fabric public APIs. Enable it for the entire organisation or, preferably, only for a dedicated security group containing the Semantiq automation service principal.

4.  If Semantiq will create workspaces, connections or deployment pipelines using the service principal, also enable the separate developer setting that allows service principals to create workspaces, connections and deployment pipelines, preferably scoped to the same approved security group.

5.  If the customer uses a security-group scope, create/select the group in Microsoft Entra and add the service principal to it.

6.  Allow time for tenant-setting changes to propagate, then return to Semantiq and select Re-check Fabric Settings.


### Grant Fabric workspace access

1.  Open the target Fabric workspace or use Semantiq Workspace Access after the service principal is permitted by tenant policy.

2.  Grant the service principal the minimum workspace role required. Contributor is generally sufficient for many create/update item operations; workspace Admin is required for specific administration tasks such as some role-management operations.

3.  For production, avoid granting broad workspace Admin unless the workflow requires it. Semantiq should use role elevation only with explicit approval and remove it when no longer required where feasible.

4.  Return to Semantiq and select Test Fabric API. The test should call a read-safe endpoint and report authentication, tenant-setting and workspace-role results separately.


## A.3 HLP-FAB-002 - Verify Required Fabric Tenant Settings

Semantiq should show each required tenant setting with Current State, Required State, Scope, Automation Mode and Reason. Where the setting can be read by API, the user should not be asked to manually confirm it. The preview tenant-setting update API must remain disabled by default in production until product governance explicitly approves its use.


| Semantiq status | User action |
| --- | --- |
| Green - Enabled and scoped correctly | No action. Continue. |
| Amber - Enabled broadly | Continue if allowed; suggest narrowing to dedicated security group. |
| Red - Disabled | Open Microsoft Fabric Admin portal and enable per guide. |
| Red - Service principal not in allowed group | Add the enterprise application/service principal to the configured security group. |
| Unknown - API not authorised | Sign in/authorise as Fabric admin or complete the check manually and then Re-check. |
| Preview automation available | Show explicit Preview label and require admin confirmation; do not enable automatically by default. |



## A.4 Help UX Requirements for This Topic

- Display exact Tenant ID and Client ID with Copy buttons.
- Display token scope as a fixed copyable value: https://api.fabric.microsoft.com/.default.
- Display a secure field for secret only while user is entering it; after save show only Last 4/secret reference and expiry.
- Display screenshots/placeholders for Entra App registrations, Certificates & secrets, Fabric Admin portal -> Tenant settings, and Fabric workspace Manage access.
- Provide Re-check buttons after every external Microsoft portal step.
- Provide error-specific deep links from invalid_client, consent-required, Forbidden and resource-not-found errors.
- Link to official Microsoft references and show Last reviewed date.

# Appendix B. Official Microsoft API Reference Register


| Ref | Microsoft source | URL |
| --- | --- | --- |
| MS-01 | Fabric API quickstart | https://learn.microsoft.com/en-us/rest/api/fabric/articles/get-started/fabric-api-quickstart |
| MS-02 | Fabric REST API scopes | https://learn.microsoft.com/en-us/rest/api/fabric/articles/scopes |
| MS-03 | Fabric identity support | https://learn.microsoft.com/en-us/rest/api/fabric/articles/identity-support |
| MS-04 | List capacities | https://learn.microsoft.com/en-us/rest/api/fabric/core/capacities/list-capacities |
| MS-05 | Create workspace | https://learn.microsoft.com/en-us/rest/api/fabric/core/workspaces/create-workspace |
| MS-06 | Assign workspace to capacity | https://learn.microsoft.com/en-us/rest/api/fabric/core/workspaces/assign-to-capacity |
| MS-07 | Workspace role assignments | https://learn.microsoft.com/en-us/rest/api/fabric/core/workspaces/add-workspace-role-assignment |
| MS-08 | List tenant settings | https://learn.microsoft.com/en-us/rest/api/fabric/admin/tenants/list-tenant-settings |
| MS-09 | Update tenant setting (Preview) | https://learn.microsoft.com/en-us/rest/api/fabric/admin/tenants/update-tenant-setting |
| MS-10 | Fabric tenant settings index | https://learn.microsoft.com/en-sg/fabric/admin/tenant-settings-index |
| MS-11 | Developer tenant settings | https://learn.microsoft.com/en-us/fabric/admin/service-admin-portal-developer |
| MS-12 | Create connection | https://learn.microsoft.com/en-us/rest/api/fabric/core/connections/create-connection |
| MS-13 | Create gateway | https://learn.microsoft.com/en-us/rest/api/fabric/core/gateways/create-gateway |
| MS-14 | Create Lakehouse | https://learn.microsoft.com/en-us/rest/api/fabric/lakehouse/items/create-lakehouse |
| MS-15 | Create Data Pipeline | https://learn.microsoft.com/en-us/rest/api/fabric/datapipeline/items/create-data-pipeline |
| MS-16 | Job Scheduler - run on demand | https://learn.microsoft.com/en-us/rest/api/fabric/core/job-scheduler/run-on-demand-item-job |
| MS-17 | Job Scheduler - create schedule | https://learn.microsoft.com/en-us/rest/api/fabric/core/job-scheduler/create-item-schedule |
| MS-18 | Create Semantic Model | https://learn.microsoft.com/en-us/rest/api/fabric/semanticmodel/items/create-semantic-model |
| MS-19 | Create Data Agent | https://learn.microsoft.com/en-us/rest/api/fabric/dataagent/items/create-data-agent |
| MS-20 | Data Agent public definition | https://learn.microsoft.com/en-us/rest/api/fabric/articles/item-management/definitions/data-agent-definition |
| MS-21 | Get Data Agent definition | https://learn.microsoft.com/en-us/rest/api/fabric/dataagent/items/get-data-agent-definition |
| MS-22 | Update Data Agent definition | https://learn.microsoft.com/en-us/rest/api/fabric/dataagent/items/update-data-agent-definition |
| MS-23 | Deployment pipeline create | https://learn.microsoft.com/en-us/rest/api/fabric/core/deployment-pipelines/create-deployment-pipeline |
| MS-24 | Deployment pipeline operations | https://learn.microsoft.com/en-us/rest/api/fabric/core/deployment-pipelines |
| MS-25 | Grant tenant-wide admin consent | https://learn.microsoft.com/en-us/entra/identity/enterprise-apps/grant-admin-consent |
| MS-26 | OAuth 2.0 authorization code + PKCE | https://learn.microsoft.com/en-us/entra/identity-platform/v2-oauth2-auth-code-flow |
| MS-27 | Microsoft Fabric capacity ARM APIs | https://learn.microsoft.com/en-us/rest/api/microsoftfabric/fabric-capacities?view=rest-microsoftfabric-2023-11-01 |
| MS-28 | Service principal token example for Fabric | https://learn.microsoft.com/en-us/fabric/data-factory/set-pipeline-owner-tutorial |


Reference register status: reviewed against Microsoft Learn content available in August 2026. Product engineering must revalidate API support, permissions and preview/stable status during implementation and before each major release.


# Appendix C. Requirement Traceability Summary


| Business / source concept | SRS coverage |
| --- | --- |
| Source concept: Fabric readiness and tenant settings | FR-FAB-001 to FR-FAB-012; SC-005/006 |
| Source concept: DEV/TEST/PROD | FR-FAB-006/007; FR-OPS-001 to FR-OPS-003; SC-008/023 |
| Source concept: enterprise source discovery and ingestion | FR-SRC-001 to FR-SRC-015; SC-010 to SC-014 |
| Source concept: Bronze/Silver/Gold | FR-DQ-001 to FR-DQ-012; SC-015/016/017 |
| Source concept: semantic model and business language | FR-SEM-001 to FR-SEM-014; SC-018 |
| Source concept: RLS/CLS/security/governance | FR-SEM-008/009; Security section; SC-019 |
| Source concept: Prepare for AI / instructions / verified answers | FR-AI-001 to FR-AI-003; SC-020 |
| Source concept: Fabric Data Agent | FR-AI-004 to FR-AI-012; SC-021 |
| Source concept: ground-truth validation | FR-AI-003/008/009/010; SC-022 |
| Source concept: monitoring and continuous improvement | FR-OPS-004 to FR-OPS-011; SC-024/026 |
| New product requirement: SSO/app registration/admin consent | FR-AUTH-001 to FR-AUTH-012; SC-003/004; Appendix A |
| New product requirement: detailed in-app help | FR-HLP-001 to FR-HLP-010; SC-025; Section 15/Appendix A |
| New product requirement: public API orchestration | Section 16; API-001 to API-019; Section 18 |



| Implementation note: This document intentionally distinguishes stable API automation, preview API automation and guided manual administration. Engineering should maintain a capability registry so that Semantiq can automatically move a step from Guided to Automated when Microsoft makes the corresponding API stable and supported. |
| --- |


## Appendix E - Data Protection, Data Sovereignty and Engineering Context Standard

Semantiq shall implement the mandatory requirements in `DATA_PROTECTION_SOVEREIGNTY_STANDARD.md`. Cross-geo data processing/storage and AI conversation-history controls shall be deny-by-default and require explicit customer approval when they exceed the approved data boundary.

### Additional functional requirements

| ID | Requirement | Priority |
|---|---|---|
| FR-DPS-001 | Maintain a versioned DataProtectionProfile per organisation containing approved storage/processing geographies, cross-geo permissions, retention, CMK/network/Purview/DLP requirements and policy owner. | Must |
| FR-DPS-002 | Discover and compare tenant/capacity/workspace/source/AI runtime regions against the approved policy before production activation. | Must |
| FR-DPS-003 | Block an unapproved cross-boundary storage/processing data flow unless a documented sovereignty exception is active. | Must |
| FR-DPS-004 | Keep Fabric AI cross-geo processing/storage/conversation-history settings off unless explicitly approved. | Must |
| FR-DPS-005 | Support policy-driven Private Link/public-access, CMK and Purview sensitivity/DLP checks with guided setup when automation is unavailable. | Must |
| FR-DPS-006 | Maintain data classification, owner, retention, lineage and access metadata for production datasets. | Must |
| FR-DPS-007 | Redact secrets and personal/restricted payloads from application logs/support bundles by default. | Must |
| FR-CTX-001 | Maintain Code, Data, Validation, Configuration and Sovereignty context registers and update them in the same change as behavior. | Must |
| FR-CTX-002 | Associate business-critical validation rules with stable IDs, enforcement location, message/help guidance and tests. | Must |
| FR-CTX-003 | Associate configuration keys with type, scope, default, validation, secret flag, security/residency impact and approval. | Must |

### Additional non-functional requirements

| ID | Category | Requirement |
|---|---|---|
| NFR-SOV-01 | Data sovereignty | No production customer data shall be intentionally stored or processed outside approved geographies without an authorised exception. |
| NFR-PRV-01 | Data minimisation | Control plane persists metadata rather than business payloads unless the approved feature requires payload persistence. |
| NFR-LOG-01 | Logging | Production logs shall not contain bearer tokens, secrets or unrestricted customer data payloads. |
| NFR-CTX-01 | Maintainability | Code/data/validation/configuration context must remain synchronised with implementation and tests. |
