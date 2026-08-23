# Requirement Traceability to Implementation Phases

## Functional requirements

| ID | Requirement | Phase(s) |
| --- | --- | --- |
| FR-AUTH-001 | Support Microsoft Entra SSO for Semantiq users using authorization code flow with PKCE. | P01-IDN |
| FR-AUTH-002 | Resolve and persist the customer tenant ID from verified token claims and onboarding configuration. | P01-IDN |
| FR-AUTH-003 | Support tenant-wide admin consent workflow for the Semantiq enterprise application where required. | P01-IDN |
| FR-AUTH-004 | Support a customer-owned Fabric automation service principal for unattended operations. | P01-IDN |
| FR-AUTH-005 | Support certificate credentials and client-secret credentials; prefer certificate for production. | P01-IDN |
| FR-AUTH-006 | Acquire Fabric tokens using scope https://api.fabric.microsoft.com/.default. | P01-IDN |
| FR-AUTH-007 | Store automation credentials only in an encrypted secret-management service. | P01-IDN |
| FR-AUTH-008 | Provide test actions for token acquisition, Fabric connectivity and permission diagnosis. | P01-IDN |
| FR-AUTH-009 | Monitor secret/certificate expiry and create proactive alerts. | P01-IDN |
| FR-AUTH-010 | Allow credential rotation without deleting customer configuration. | P01-IDN |
| FR-AUTH-011 | Map Semantiq application roles to customer users/groups separately from Fabric roles. | P01-IDN |
| FR-AUTH-012 | Support customer offboarding that disables tokens, removes stored credentials and optionally removes Semantiq service-principal access from Fabric. | P01-IDN |
| FR-FAB-001 | Run a Fabric Readiness Assessment immediately after integration. | P02-FAB |
| FR-FAB-002 | List Fabric capacities accessible to the principal and show ID, name, SKU, region and state. | P02-FAB |
| FR-FAB-003 | Allow selection of an existing Fabric capacity. | P02-FAB |
| FR-FAB-004 | Optionally provision a new Fabric capacity through Azure Resource Manager if the customer grants the required Azure RBAC. | P02-FAB |
| FR-FAB-005 | Create a workspace through the Fabric REST API with display name, description and selected capacity when supported. | P02-FAB |
| FR-FAB-006 | Create DEV, TEST and PROD workspaces from a configurable naming template. | P02-FAB |
| FR-FAB-007 | Assign a workspace to a Fabric capacity. | P02-FAB |
| FR-FAB-008 | Add/update/delete workspace role assignments for approved users, groups and service principals. | P02-FAB |
| FR-FAB-009 | Read Fabric tenant settings required by Semantiq and show effective state and scope. | P02-FAB |
| FR-FAB-010 | Support a feature-flagged tenant-setting update capability only when Microsoft public API is enabled for production use by product policy. | P02-FAB |
| FR-FAB-011 | Provide guided manual steps for tenant settings when API update is disabled or unsupported. | P02-FAB |
| FR-FAB-012 | Detect whether service-principal Fabric tenant settings permit public API calls and workspace/connection/deployment-pipeline creation. | P02-FAB |
| FR-FAB-013 | Support assignment of workspaces to Fabric domains when customer governance uses domains. | Cross-phase / final verification |
| FR-FAB-014 | Capture Fabric capacity and workspace region to detect cross-region constraints. | Cross-phase / final verification |
| FR-FAB-015 | Provide a dry-run plan before creating Fabric assets. | Cross-phase / final verification |
| FR-FAB-016 | Tag/record every Semantiq-managed Fabric resource in the control plane for lifecycle tracking. | Cross-phase / final verification |
| FR-FAB-017 | Support discovery/import of an existing Fabric estate rather than forcing new resource creation. | Cross-phase / final verification |
| FR-FAB-018 | Run post-provision verification and produce a readiness score with blockers/warnings. | Cross-phase / final verification |
| FR-SRC-001 | Maintain a source catalogue for SQL, Azure SQL, Business Central, Dataverse, SharePoint, Excel, APIs, ERP, CRM, LMS and external databases. | P03-SRC |
| FR-SRC-002 | Recommend ingestion method per source: Pipeline, Dataflow Gen2, Mirroring, Shortcut, Eventstream, gateway or direct upload. | P03-SRC |
| FR-SRC-003 | Create Fabric cloud/on-prem/VNet connections through supported Connections API. | P03-SRC |
| FR-SRC-004 | Allow privacy level and credential mode to be configured per connection. | P03-SRC |
| FR-SRC-005 | Support VNet gateway creation through Fabric API where applicable. | P03-SRC |
| FR-SRC-006 | For on-premises gateway software installation, provide guided instructions and verify registered gateway after installation. | P03-SRC |
| FR-SRC-007 | Discover source tables/files/schemas and record metadata, row-count/size indicators where available, update frequency and owner. | P03-SRC |
| FR-SRC-008 | Allow user to select only in-scope source objects before ingestion. | P03-SRC |
| FR-SRC-009 | Create Bronze landing structure and ingestion definitions. | P04-ING |
| FR-SRC-010 | Support incremental loading using timestamps, IDs, CDC or source-specific methods where available. | P04-ING |
| FR-SRC-011 | Support schedule definition and item/job scheduling where supported. | P04-ING |
| FR-SRC-012 | Implement retries, failure paths, reject handling and notifications. | P04-ING |
| FR-SRC-013 | Detect schema drift and classify as compatible, warning or breaking change. | P04-ING |
| FR-SRC-014 | Maintain ingestion audit history and lineage from source object to Bronze target. | P04-ING |
| FR-SRC-015 | Support on-demand run and scheduled run for supported Fabric items. | P04-ING |
| FR-DQ-001 | Create or attach a Lakehouse for each configured domain/environment. | P04-ING |
| FR-DQ-002 | Create logical Bronze, Silver and Gold conventions. | P04-ING |
| FR-DQ-003 | Profile ingested data for nulls, duplicates, data types, ranges, distinct values and referential integrity. | P05-DQM |
| FR-DQ-004 | Suggest cleansing rules for duplicate removal, null handling, code/date normalisation and invalid-record handling. | P05-DQM |
| FR-DQ-005 | Support rule severity (Info/Warning/Error) and pass threshold. | P05-DQM |
| FR-DQ-006 | Standardise canonical business entities such as Customer, Employee, Product, Course, Learner, Supplier and Transaction. | P05-DQM |
| FR-DQ-007 | Create/commonise business keys required to join multi-source records. | P05-DQM |
| FR-DQ-008 | Generate transformation implementations using Dataflow Gen2, Notebook/Spark, SQL or Pipeline according to selected pattern. | P05-DQM |
| FR-DQ-009 | Generate fact/dimension recommendations and star-schema candidates. | P05-DQM |
| FR-DQ-010 | Maintain full model/version history and impact analysis. | P05-DQM |
| FR-DQ-011 | Preserve raw source data in Bronze subject to customer retention policy. | P04-ING |
| FR-DQ-012 | Expose data-quality scorecards to the Operations Monitor. | P05-DQM |
| FR-SEM-001 | Generate a Power BI semantic model from approved Gold data. | P06-SEM |
| FR-SEM-002 | Prefer a star schema with explicit fact/dimension relationships where appropriate. | P06-SEM |
| FR-SEM-003 | Prefer Direct Lake where appropriate and supported by the selected architecture. | P06-SEM |
| FR-SEM-004 | Generate explicit measures rather than relying on implicit aggregation for certified KPIs. | P06-SEM |
| FR-SEM-005 | Map technical field names to business-friendly names. | P06-SEM |
| FR-SEM-006 | Generate table/column/measure descriptions and business synonyms. | P06-SEM |
| FR-SEM-007 | Maintain a business glossary linked to semantic-model objects. | P06-SEM |
| FR-SEM-008 | Support RLS and object/column-level restrictions through semantic-model design and approved role mappings. | P06-SEM |
| FR-SEM-009 | Support sensitivity/classification metadata and governance status where underlying platform capability permits. | P06-SEM |
| FR-SEM-010 | Provide model certification workflow: Draft -> Reviewed -> Approved -> Published -> Deprecated. | P06-SEM |
| FR-SEM-011 | Detect breaking semantic-model changes and block uncontrolled promotion. | P06-SEM |
| FR-SEM-012 | Maintain verified KPI definitions to prevent conflicting calculations. | P06-SEM |
| FR-SEM-013 | Support refresh/redeploy after approved source or model changes. | P06-SEM |
| FR-SEM-014 | Record lineage from source fields to semantic model objects and AI sources. | P06-SEM |
| FR-AI-001 | Allow AI owner to select the subset of tables, columns and measures exposed to AI. | P07-AI |
| FR-AI-002 | Generate AI instructions from approved glossary, KPI definitions and business rules. | P07-AI |
| FR-AI-003 | Maintain verified questions/answers and ground-truth expected results. | P07-AI |
| FR-AI-004 | Create Fabric Data Agent via POST /v1/workspaces/{workspaceId}/dataAgents. | P07-AI |
| FR-AI-005 | Generate/update Data Agent public definition using supported definition parts. | P07-AI |
| FR-AI-006 | Support Data Agent data-source configuration, instructions and few-shot examples in the public definition where supported. | P07-AI |
| FR-AI-007 | Publish Data Agent only after validation and approval. | P07-AI |
| FR-AI-008 | Run simple, comparative, trend, ranking, date-filter and follow-up test questions. | P07-AI |
| FR-AI-009 | Diagnose likely cause of wrong answer as data, relationship, measure, semantic definition, instruction, security or unsupported question. | P07-AI |
| FR-AI-010 | Validate Data Agent under different user/security contexts where technically supported. | P07-AI |
| FR-AI-011 | Expose published agent to Semantiq conversational UI and provide integration guidance for Teams/Copilot Studio/web channels. | P07-AI |
| FR-AI-012 | Maintain Data Agent lifecycle and regression tests whenever source/model/instruction changes. | P07-AI |
| FR-OPS-001 | Create Fabric deployment pipeline with DEV, TEST and PROD stages. | P08-OPS |
| FR-OPS-002 | Assign workspaces to deployment pipeline stages. | P08-OPS |
| FR-OPS-003 | Support controlled stage deployment with pre-check, approval and post-check. | P08-OPS |
| FR-OPS-004 | Display ingestion job status, duration, row/volume metrics and failures. | P08-OPS |
| FR-OPS-005 | Display Fabric capacity state and workload indicators available through approved metrics sources. | P08-OPS |
| FR-OPS-006 | Display data-quality breaches and trend. | P08-OPS |
| FR-OPS-007 | Display semantic/agent regression score and failed questions. | P08-OPS |
| FR-OPS-008 | Implement alert rules for failed jobs, stale data, credential expiry and quality threshold breaches. | P08-OPS |
| FR-OPS-009 | Provide audit trail for configuration changes and API actions. | P08-OPS |
| FR-OPS-010 | Support environment backup/export of Semantiq configuration and Fabric public definitions where available. | P08-OPS |
| FR-OPS-011 | Support change impact analysis and mandatory revalidation after source/model/security/agent changes. | P08-OPS |
| FR-OPS-012 | Support graceful customer offboarding and revoke Semantiq-managed access. | P08-OPS |
| FR-HLP-001 | Every setup/configuration screen must have a context-sensitive Help action. | P08-OPS |
| FR-HLP-002 | Each help topic must show prerequisites, required role, expected duration and impact. | P08-OPS |
| FR-HLP-003 | Help topics must include exact Microsoft portal navigation paths and field labels. | P08-OPS |
| FR-HLP-004 | Help topics must provide copyable values such as redirect URI, token scope and API endpoint when relevant. | P08-OPS |
| FR-HLP-005 | Help topics must include a verification step that maps back to Re-check in Semantiq. | P08-OPS |
| FR-HLP-006 | Help topics must include common error messages and troubleshooting. | P08-OPS |
| FR-HLP-007 | Preview or high-privilege Microsoft features must be explicitly labelled. | P08-OPS |
| FR-HLP-008 | Help content must be versioned and record the Microsoft documentation date/reference used. | P08-OPS |
| FR-HLP-009 | The UI must deep-link from an API error to the most relevant help topic. | P08-OPS |
| FR-HLP-010 | Administrators must be able to export an onboarding runbook showing all remaining manual steps. | P08-OPS |


## Screens

| ID | Screen | Phase(s) |
| --- | --- | --- |
| SC-001 | Sign In | P01-IDN, P09-GO |
| SC-002 | Organisation Setup | P00-FND, P01-IDN |
| SC-003 | SSO & Consent | P01-IDN |
| SC-004 | Fabric Automation Identity | P01-IDN |
| SC-005 | Fabric Readiness | P02-FAB, P09-GO |
| SC-006 | Tenant Settings | P02-FAB |
| SC-007 | Capacity | P02-FAB |
| SC-008 | Workspace Topology | P02-FAB |
| SC-009 | Workspace Access | P02-FAB |
| SC-010 | Source Catalogue | P03-SRC, P09-GO |
| SC-011 | Connection Setup | P03-SRC |
| SC-012 | Gateway Setup | P03-SRC |
| SC-013 | Schema Discovery | P03-SRC |
| SC-014 | Ingestion Plan | P04-ING, P09-GO |
| SC-015 | Lakehouse & Layers | P04-ING |
| SC-016 | Data Quality | P05-DQM, P09-GO |
| SC-017 | Business Entity Mapping | P05-DQM |
| SC-018 | Semantic Model Studio | P06-SEM, P09-GO |
| SC-019 | Security & Governance | P06-SEM |
| SC-020 | AI Readiness | P07-AI, P09-GO |
| SC-021 | Fabric Data Agent | P07-AI, P09-GO |
| SC-022 | Validation Centre | P07-AI, P09-GO |
| SC-023 | Deployment | P08-OPS, P09-GO |
| SC-024 | Operations Monitor | P08-OPS, P09-GO |
| SC-025 | Help Centre | P00-FND, P08-OPS, P09-GO |
| SC-026 | Audit Log | P00-FND, P08-OPS, P09-GO |


## APIs

| ID | Operation | Phase(s) |
| --- | --- | --- |
| API-001 | List capacities | P02-FAB |
| API-002 | Create workspace | P02-FAB |
| API-003 | Assign workspace to capacity | P02-FAB |
| API-004 | Workspace role assignment | P02-FAB |
| API-005 | List tenant settings | P02-FAB |
| API-006 | Update tenant setting | P02-FAB |
| API-007 | Create connection | P03-SRC |
| API-008 | Create gateway | P03-SRC |
| API-009 | Create Lakehouse | P04-ING |
| API-010 | Create Data Pipeline | P04-ING |
| API-011 | Run item job | P04-ING |
| API-012 | Create item schedule | P04-ING |
| API-013 | Create semantic model | P06-SEM |
| API-014 | Create Data Agent | P07-AI |
| API-015 | Get Data Agent definition | P07-AI |
| API-016 | Update Data Agent definition | P07-AI |
| API-017 | Publish Data Agent | P07-AI |
| API-018 | Create deployment pipeline | P08-OPS |
| API-019 | Deployment pipeline stage operations | P08-OPS |


## Help topics

| ID | Topic | Phase(s) |
| --- | --- | --- |
| HLP-SSO-001 | Set up Semantiq SSO and grant tenant admin consent | P01-IDN, P09-GO |
| HLP-AUTH-002 | Create the Fabric Automation App Registration | P01-IDN |
| HLP-AUTH-003 | Create a certificate or client secret and connect it to Semantiq | P01-IDN |
| HLP-FAB-001 | Run the Fabric Readiness Assessment | P02-FAB, P09-GO |
| HLP-FAB-002 | Enable required Fabric service-principal tenant settings | P02-FAB |
| HLP-FAB-003 | Select or create a Fabric capacity | P02-FAB |
| HLP-FAB-004 | Create DEV, TEST and PROD workspaces | P02-FAB |
| HLP-FAB-005 | Grant the Semantiq service principal workspace access | P02-FAB |
| HLP-SRC-002 | Create and test a Fabric connection | P03-SRC, P09-GO |
| HLP-GWY-001 | Configure an on-premises or VNet gateway | P03-SRC |
| HLP-ING-001 | Create an ingestion plan and schedule | P04-ING, P09-GO |
| HLP-LKH-001 | Create Lakehouse and Bronze/Silver/Gold layout | P04-ING |
| HLP-DQ-001 | Review and approve data-quality rules | P05-DQM, P09-GO |
| HLP-SEM-001 | Review the generated semantic model | P06-SEM, P09-GO |
| HLP-SEC-001 | Configure and test RLS/OLS | P06-SEM, P09-GO |
| HLP-AI-001 | Prepare approved data and business instructions for AI | P07-AI, P09-GO |
| HLP-AGT-001 | Create, configure, validate and publish a Fabric Data Agent | P07-AI, P09-GO |
| HLP-DEP-001 | Create deployment pipeline and promote DEV -> TEST -> PROD | P08-OPS, P09-GO |
| HLP-OPS-001 | Troubleshoot failed Fabric API or job runs | P08-OPS, P09-GO |


## Acceptance scenarios

| ID | Scenario | Phase(s) |
| --- | --- | --- |
| AT-001 | SSO onboarding | P01-IDN, P09-GO |
| AT-002 | Fabric identity | P01-IDN, P09-GO |
| AT-003 | Readiness blocker | P02-FAB, P09-GO |
| AT-004 | Existing Fabric | P02-FAB, P09-GO |
| AT-005 | New Fabric workspace | P02-FAB, P09-GO |
| AT-006 | Source connection | P03-SRC, P09-GO |
| AT-007 | Bronze load | P04-ING, P09-GO |
| AT-008 | Quality gate | P05-DQM, P09-GO |
| AT-009 | Semantic model | P06-SEM, P09-GO |
| AT-010 | Data Agent | P07-AI, P09-GO |
| AT-011 | Security | P06-SEM, P07-AI, P09-GO |
| AT-012 | Deployment | P08-OPS, P09-GO |
| AT-013 | Rate limit | P04-ING, P08-OPS, P09-GO |
| AT-014 | Credential expiry | P08-OPS, P09-GO |
| AT-015 | Drift | P08-OPS, P09-GO |
| AT-016 | Help flow | P01-IDN, P08-OPS, P09-GO |


## v1.3 Data Protection, Sovereignty & Context Traceability

| Requirement | Primary phase(s) | Reference / context | Verification focus |
|---|---|---|---|
| FR-DPS-001 DataProtectionProfile | 00, 01 | DATA_PROTECTION_SOVEREIGNTY_STANDARD; CONFIGURATION_REGISTER | Versioned policy, approver, audit |
| FR-DPS-002 Region discovery | 02, 03, 07 | DATA_SOVEREIGNTY_REGISTER | Source/capacity/workspace/AI actual vs approved geo |
| FR-DPS-003 Cross-boundary block/exception | 00-09 | VALIDATION_RULES_REGISTER; SECURITY_PRIVACY_DECISIONS | Server-side block and explicit exception evidence |
| FR-DPS-004 AI cross-geo deny-by-default | 07 | AI technology guide; DATA_SOVEREIGNTY_REGISTER | Processing/storage/history switches and approval |
| FR-DPS-005 Network/CMK/Purview/DLP | 02, 03, 06, 08 | DATA_PROTECTION_SOVEREIGNTY_STANDARD | Observed configuration and supported automation/manual guidance |
| FR-DPS-006 Data context | 03-09 | DATA_CONTEXT_REGISTER | Owner/classification/retention/lineage/access |
| FR-DPS-007 Safe logs/support data | 00, 01, 08, 09 | CODE_CONTEXT_REGISTER; CONFIGURATION_REGISTER | Secret/payload redaction tests |
| FR-CTX-001 Living context | 00-09 | CONTEXT_INDEX + all context registers | Context matches implementation/tests |
| FR-CTX-002 Validation context | 00-09 | VALIDATION_RULES_REGISTER | Stable IDs, enforcement, help, tests |
| FR-CTX-003 Configuration context | 00-09 | CONFIGURATION_REGISTER | Typed scoped config, secret/residency impact, approvals |
