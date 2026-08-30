SEMANTIQ v2.2

Ground-Zero Architecture Reset - Three-Phase Planning, Design & Execution Blueprint

Ground-zero application baseline | Fixed cPanel platform | Secure-by-default SSO | Microsoft Fabric to decision intelligence

| **PHASE 1 <br>System Administration <br>**Identity, organisation, roles, domain security and strong platform controls. | **PHASE 2 <br>Fabric Configuration <br>**Connect data, govern it, model it, secure it, and make it AI-ready. | **PHASE 3 <br>SemantIQ Workplace <br>**Personalised intelligence, conversational AI, insights, decisions and Power BI. |
| ---------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------- |

**Clean-slate implementation rule  
**SemantIQ v2 reuses no SemantIQ v1 code, database schema, migrations, APIs, security implementation, workflows, tests, or application configuration. The only reusable asset is the approved CLaaS2SaaS shared UI/UX design system and brand standard.

**Purpose of this document**

Use this blueprint as the working baseline for planning, solution design, implementation sequencing, acceptance, and future change control.

# 0\. Ground-Zero Application Start

The hosting and deployment platform already exists. SemantIQ v2.2 starts from application ground zero: no inherited v1 business code, schema, permissions or workflows, but the established cPanel runtime/deployment baseline is treated as fixed infrastructure rather than redesigned.

## 0.1 Fixed Infrastructure Baseline - Out of Redesign Scope

| **Platform item**         | **Fixed baseline for v2.2**                                                                                                                                                                    |
| ------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Backend**               | Laravel 13 on PHP 8.5.                                                                                                                                                                         |
| **Frontend**              | React 19.                                                                                                                                                                                      |
| **Database**              | MySQL hosted on cPanel. The v2 application schema is designed fresh; the database technology/hosting is not re-selected.                                                                       |
| **Deployment**            | GitHub Actions -> cPanel over SSH/rsync. Existing source-to-cPanel synchronization is already established.                                                                                     |
| **Architecture**          | Modular monolith.                                                                                                                                                                              |
| **Tenancy boundary**      | Single-tenant deployment today, with explicit multi-tenant-ready organisation/tenant boundaries in the new application design.                                                                 |
| **Instruction to Claude** | Do not spend Phase 1 re-evaluating or replacing this infrastructure unless the product owner separately requests an infrastructure change or a verified blocker makes the baseline impossible. |

## 0.2 First Screen - Login & Enterprise SSO

The first user-facing screen in a fresh SemantIQ installation is the Login page. No protected application shell, menu, KPI, business-domain metadata or administrative content is returned before identity verification succeeds.

| **Login-page element**            | **Required v2.2 behaviour**                                                                                                                                                                                      |
| --------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Branding**                      | Use the approved shared CLaaS2SaaS login-page design, SemantIQ name and standard responsive/accessibility rules.                                                                                                 |
| **Primary action**                | Sign in with Microsoft - Microsoft Entra ID / OpenID Connect is the primary Release 1 enterprise SSO path.                                                                                                       |
| **Additional identity providers** | Show another approved IdP only when it has been explicitly configured. The identity layer must not assume Microsoft is the only future provider.                                                                 |
| **Self-registration**             | Disabled by default. Successful external authentication does not create business-data access automatically.                                                                                                      |
| **User feedback**                 | Provide clear states for sign-in unavailable, access not assigned, account inactive, tenant/organisation mismatch, session expired and generic authentication failure without leaking security-sensitive detail. |
| **Help**                          | Provide a simple route to contact the organisation administrator/support when access has not been assigned.                                                                                                      |
| **Security**                      | Do not expose tokens, tenant secrets, internal role mappings or diagnostic traces in browser-visible errors.                                                                                                     |

## 0.3 Ground-Zero First-Run Bootstrap

A clean deployment has no trusted application administrator yet. Phase 1 must therefore design and implement one controlled bootstrap path for the first System Administrator. Bootstrap is a product requirement, not a manual database-edit procedure.

- Fresh installation starts in an Unconfigured state and returns no business data.
- A secure bootstrap method establishes the first organisation/tenant trust and the initial System Administrator.
- The first System Administrator signs in through the verified enterprise identity path before receiving the privileged application session.
- After bootstrap is complete, the bootstrap path is disabled or otherwise made non-reusable according to the approved design.
- No subsequent user receives business access merely because the identity provider authenticated them.

## 0.4 Authentication and Landing Flow

| **Step** | **Action**                        | **Required behaviour**                                                                                                                        |
| -------- | --------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------- |
| 1        | Open SemantIQ                     | Unauthenticated visitor is presented with the Login page, not the application navigation.                                                     |
| 2        | Choose Sign in with Microsoft     | SemantIQ redirects to the configured Microsoft Entra ID authorization flow.                                                                   |
| 3        | Identity provider verifies user   | Enterprise authentication/MFA/conditional-access policy is evaluated by the identity provider as configured by the organisation.              |
| 4        | Process trusted callback          | SemantIQ validates the protocol response, issuer/tenant, anti-forgery state/nonce and required claims before trusting the identity.           |
| 5        | Resolve SemantIQ identity         | Map the verified external identity to the configured organisation and active SemantIQ user record. Unknown/unassigned identities fail closed. |
| 6        | Resolve effective access          | Load role + domain + scope + sensitivity + hierarchy/ownership context. Authentication alone grants no business-domain entitlement.           |
| 7        | Create secure application session | Issue the application session only after identity and access context are valid; apply approved timeout/re-authentication/revocation controls. |
| 8        | Route user                        | Administrators land on their authorised administration experience; business users land on their personalised Workplace.                       |
| 9        | Logout / expiry                   | Terminate the SemantIQ session and return to a safe signed-out/session-expired state.                                                         |

## 0.5 Pre-Authentication Screens and Required States

| **Screen / state**          | **Purpose**                                                                                             |
| --------------------------- | ------------------------------------------------------------------------------------------------------- |
| **Login**                   | Entry page with Microsoft SSO and any explicitly configured additional IdP.                             |
| **First-Run Bootstrap**     | Controlled one-time establishment of organisation trust and first System Administrator.                 |
| **Authentication Callback** | Backend/protocol route; no business UI. Validates the trusted identity response.                        |
| **Access Not Assigned**     | Authenticated identity is valid but has no active SemantIQ user/access assignment.                      |
| **Account Inactive**        | Identity is known but the SemantIQ account is inactive/revoked.                                         |
| **Access Denied**           | User is authenticated but the requested protected resource is outside effective access.                 |
| **Session Expired**         | Session is no longer valid; user is returned safely to sign-in without exposing protected page content. |
| **Signed Out**              | Confirmation/return-to-login state after logout.                                                        |

## 0.6 Ground-Zero Acceptance Principle

Before Phase 1 is considered complete, SemantIQ must prove the entire path from a completely unauthenticated browser through Microsoft SSO, SemantIQ identity mapping, effective-access resolution and a secure role-appropriate landing page - including refusal cases for unknown, inactive and unentitled users.

# **1\. Document Purpose and Operating Rules**

This document defines the clean SemantIQ v2 product structure and the work required to plan, design, build and accept it. It intentionally avoids carrying forward implementation assumptions from SemantIQ v1.

**NON-NEGOTIABLE RESET RULES**

| **Rule**                    | **v2 direction**                                                                                                                                                                |
| --------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Greenfield repository**   | Create a fresh v2 application codebase/repository. The fixed cPanel runtime/deployment environment is an infrastructure constraint, not reusable v1 application implementation. |
| **Fresh database**          | Design a new logical and physical data model from the v2 requirements. Do not copy or rename v1 tables.                                                                         |
| **Fresh security model**    | Define security from business roles, domains, scope and sensitivity. Do not port v1 permission tables or policy code.                                                           |
| **Fresh workflows**         | Redesign each workflow from business intent. Do not preserve a workflow merely because it already exists in v1.                                                                 |
| **Fresh tests**             | Write v2 tests against v2 requirements and threats. Historical v1 defects may inform test ideas but v1 tests are not inherited.                                                 |
| **Only UI standard reused** | Reuse the approved CLaaS2SaaS shell, tokens, page archetypes, branding, responsive behaviour and accessibility standard.                                                        |

**Security philosophy  
**Normal secure behaviour must be easy. Security controls are enforced by the platform. Administrators configure business identity, organisation structure and a small number of policy decisions; they do not configure every technical security mechanism.

## **1.1 What SemantIQ v2 Is**

SemantIQ v2 is a secure Business Decision Intelligence platform that converts enterprise data into governed, role-aware and AI-ready intelligence without forcing business users to understand Microsoft Fabric implementation details.

- System Administration establishes identity, organisation structure, roles, business domains and strong default security.
- Fabric Configuration turns connected source data into governed, quality-controlled, semantically modelled, security-filtered and AI-ready information.
- SemantIQ Workplace gives each user only the intelligence they are entitled to see, with conversational analysis, insights, recommendations, decisions and report creation.

## **1.2 Three-Phase Delivery Model**

| **Phase** | **Product area**                            | **Primary objective**                                                                                                 | **Exit outcome**                                 |
| --------- | ------------------------------------------- | --------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------ |
| 1         | System Administration & Security Foundation | Establish trustworthy identity, organisation, business domains, access scope and secure-by-default platform controls. | A secure organisation ready to onboard data.     |
| 2         | Fabric Configuration & Intelligence Factory | Connect, discover, ingest, govern, model, secure and publish enterprise data for BI and AI.                           | Governed semantic and AI-ready data products.    |
| 3         | SemantIQ Workplace & Decision Intelligence  | Deliver personalised role-aware intelligence, conversation, insights, decisions and Power BI outputs.                 | Business adoption and measurable decision value. |

## **1.3 How Each Phase Is Run**

Each phase follows the same three work modes. A phase does not enter build execution until its planning and solution design outputs are approved.

| **Work mode** | **Question answered**                                            | **Mandatory outputs**                                                                                             |
| ------------- | ---------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| **PLAN**      | What are we solving, for whom, and how will success be measured? | Scope, personas, requirements, workflows, risks, assumptions, acceptance criteria, delivery backlog.              |
| **DESIGN**    | How will the solution behave and be structured?                  | UX flows, logical architecture, data model, security model, integration contracts, validation rules, test design. |
| **EXECUTE**   | How do we build, verify and release it safely?                   | Implementation increments, automated tests, security tests, deployment evidence, UAT, acceptance sign-off.        |

## **1.4 Global Design Principles**

| **Principle**                                             | **Meaning**                                                                                                                                                      |
| --------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Business intent first**                                 | Users describe the business outcome. SemantIQ translates that intent into data, security, analytics and AI actions.                                              |
| **Secure by default**                                     | Unknown access is denied. Strong baseline controls are mandatory rather than optional checkboxes.                                                                |
| **One identity, one access context**                      | Every request is evaluated using the authenticated identity and current organisation/domain/scope context.                                                       |
| **AI never broadens access**                              | The conversational layer can only query data already authorised for the current user.                                                                            |
| **One security model across UI, Fabric, Power BI and AI** | Role/domain/scope rules must not be reconfigured independently in every downstream platform.                                                                     |
| **Governed semantics before generative AI**               | AI answers should rely on certified measures, dimensions, business definitions and lineage wherever possible.                                                    |
| **Evidence is automatic**                                 | Material security, administrative and data-governance actions generate audit evidence automatically.                                                             |
| **Technology is replaceable behind contracts**            | The fresh implementation may choose a new application stack; business rules and security contracts must not be coupled to one vendor-specific UI implementation. |

# **2\. Cross-Phase Security and Access Model**

A simple business-facing model with strong technical enforcement underneath.

SemantIQ v2 should avoid turning security into a large configuration project. The administrator primarily assigns four business attributes. The platform enforces the technical controls automatically.

| **Access dimension** | **Examples**                                                                 | **What it controls**                                                             |
| -------------------- | ---------------------------------------------------------------------------- | -------------------------------------------------------------------------------- |
| **Role**             | System Admin, Organisation Admin, Executive, Manager, Business User, Auditor | What actions the user may perform.                                               |
| **Business Domain**  | Sales, Finance, People/HR, Operations, Customer, Learning, Executive         | Which area of business information is visible.                                   |
| **Scope**            | Own, Team, Business Unit, Domain, Organisation                               | Which records/rows are visible inside the authorised domain.                     |
| **Sensitivity**      | Standard, Confidential, Restricted                                           | Which fields/objects can be exposed and which actions require stronger controls. |

**Effective access formula  
**Authenticated identity + active organisation + role + domain + scope + sensitivity policy = effective data and action access. Front-end menu hiding is never the control; backend and data-layer enforcement are mandatory.

## **2.1 Required Behaviour Examples**

| **Persona**          | **Domain**                | **Scope**                | **May see**                                                                   | **Must not automatically see**                                                                    |
| -------------------- | ------------------------- | ------------------------ | ----------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------- |
| Salesperson          | Sales                     | Own                      | Own opportunities, customers, targets and performance.                        | Other salespeople's individual performance; Finance/HR data.                                      |
| Sales Manager        | Sales                     | Team                     | Team pipeline, team members, comparisons, risks and forecast.                 | Sales teams outside assigned scope; restricted HR fields.                                         |
| Finance Manager      | Finance                   | Business Unit / Domain   | Approved financial metrics, ledgers and analysis in scope.                    | HR records and unrelated individual Sales performance unless specifically entitled.               |
| HR Manager           | People                    | Team / Domain            | Approved workforce and HR data in scope.                                      | Finance ledgers and Sales domain data unless explicitly entitled.                                 |
| Executive            | Multiple approved domains | Organisation             | Cross-functional KPIs, trends, risks, forecasts and approved drill-downs.     | Highly restricted raw fields such as bank, identity or medical data unless separately authorised. |
| System Administrator | Platform                  | Platform only by default | Identity, integrations, environment, security posture and technical metadata. | Business-domain data merely because the user is a System Administrator.                           |

## **2.2 Secure Baseline - Non-Configurable Controls**

| **Control**             | **Required v2 behaviour**                                                                                                                |
| ----------------------- | ---------------------------------------------------------------------------------------------------------------------------------------- |
| **Identity protection** | Enterprise authentication, strong session controls and privileged re-authentication where risk requires it.                              |
| **Authorisation**       | Deny by default; backend policy enforcement for every protected resource and action.                                                     |
| **Domain isolation**    | Finance, HR/People, Sales and other domains are isolated unless an explicit cross-domain entitlement exists.                             |
| **Encryption**          | TLS in transit and encryption at rest for supported stores/services.                                                                     |
| **Audit**               | Security-relevant and privileged changes are always logged; audit cannot be disabled by ordinary administrators.                         |
| **Secrets**             | Secrets never appear in browser code, logs or ordinary configuration screens.                                                            |
| **AI boundary**         | AI queries inherit the user's effective data scope. The model never receives unrestricted enterprise data and then decides what to hide. |
| **Data minimisation**   | Store the minimum control-plane/business data needed; prefer metadata and governed data products over uncontrolled duplication.          |
| **Security monitoring** | Authentication anomalies, privileged changes, integration failures and policy violations generate observable security signals.           |
| **Recovery**            | Backups, restore procedures, release rollback and tested recovery are platform responsibilities.                                         |

## **2.3 What Administrators May Configure**

Configuration is limited to genuine organisation/business decisions. Technical security controls should normally be derived automatically.

- Organisation structure, business units, teams and management hierarchy.
- Identity sources and approved user/group onboarding.
- Business domain ownership and user membership.
- Role and data scope assignment.
- Sensitivity classification and exceptional access where justified.
- Approved data/AI geography and other legal/business policies that software cannot safely invent.
- Exception requests with owner, reason, expiry and approval when the secure baseline must be deviated from.

## 2.4 SemantIQ v2 Three-Part Architecture

SemantIQ v2 is one product with three deliberately separated operating areas. Security is a shared platform capability underneath all three; it is not a fourth application and it is not a collection of settings that ordinary users must understand.

| **1\. SYSTEM ADMINISTRATION**                                                                                                                                | **2\. FABRIC CONFIGURATION**                                                                                                                                                                    | **3\. SEMANTIQ WORKPLACE**                                                                                                                                                         |
| ------------------------------------------------------------------------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Identity and SSO <br>Organisation and hierarchy <br>Users and groups <br>Roles, domains, scope, sensitivity <br>Secure baseline <br>Audit and access reviews | Connect and discover data <br>Classify and ingest <br>Quality and standardise <br>Business and semantic model <br>Security propagation <br>AI readiness <br>Power BI publication and monitoring | Personalised Home <br>My Intelligence <br>Ask SemantIQ <br>Explore and insights <br>Risks and recommendations <br>Decisions and alerts <br>Reports and dashboards <br>My Workspace |

**ONE SECURITY CONTEXT ACROSS THE PRODUCT**

Identity + Role + Business Domain + Scope + Sensitivity + organisational relationship determine effective access. The same effective access must drive menu visibility, application APIs, Fabric/semantic security, Power BI consumption, AI retrieval, export and sharing decisions.

## 2.5 Master Information Architecture and Navigation Rules

- Users see business language and business outcomes. Fabric implementation concepts stay inside Fabric Configuration.
- Navigation is generated from the signed-in user's effective access. A user must not see inaccessible business domains merely because the feature exists.
- Menu hiding is convenience only. Every protected query and action must be authorised again at the backend and data/semantic layer.
- System Administrators operate the platform but receive no business-domain data access by default.
- Normal security behaviour is automatic. Admin-facing settings are reserved for real organisation/business decisions, not technical control switches.
- The same domain/scope model is propagated to Fabric, semantic models, Power BI and AI so security is not recreated independently in each layer.
- Unsafe or exceptional behaviour is where friction belongs: re-authentication, reason, approval, expiry and audit evidence are required when policy is overridden.
- Login, first-run bootstrap, authentication callback, access-not-assigned, session-expired and signed-out states are pre-authentication/application-entry experiences. They are not normal sidebar menu items and must be designed before the authenticated shell.

## 2.6 Master Menu Structure

The following menu baseline is authoritative for v2 planning. Phase-specific screen lists later in this document must remain consistent with it. Individual items may be hidden when the user lacks the required role/domain/scope.

### 2.6.1 System Administration Menu

| **Menu**            | **Submenu / key screens**                                                                       | **Purpose**                                                                                  |
| ------------------- | ----------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------- |
| Administration Home | Readiness, action queue, security posture, exceptions                                           | Single operational summary for the platform/organisation administrator.                      |
| Organisation        | Company Profile; Business Units; Departments; Teams; Management Hierarchy; Legal Entities       | Define the organisational structure from which access scope is derived.                      |
| Users & Groups      | Users; Groups; Invitations/Directory Sync; User Lifecycle                                       | Onboard identities and maintain active/inactive membership.                                  |
| Roles & Access      | Role Assignments; Domain Entitlements; Scope Assignments; Sensitivity Ceiling; Access Simulator | Assign understandable business access without exposing low-level ACLs.                       |
| Business Domains    | Executive; Sales; Finance; People; Operations; Customer; Learning; Custom Domains               | Enable domains, owners and default access expectations.                                      |
| Identity & SSO      | Microsoft Entra ID; Other Approved IdPs; Login Experience; SSO Health; Session Policy           | Configure and validate enterprise sign-in after the ground-zero login/bootstrap path exists. |
| Security Status     | Secure Baseline; Privileged Access Health; Exceptions; Security Events                          | Show posture and exceptions; do not expose dozens of technical toggles.                      |
| Access Reviews      | Privileged Reviews; Domain Reviews; Overdue Reviews                                             | Periodic owner review of privileged and sensitive-domain access.                             |
| Audit               | User Access; Admin Changes; Security Events; Configuration Changes                              | Searchable, immutable evidence appropriate to the viewer.                                    |
| System Health       | Application; Integrations; Jobs; Connections; Service Health                                    | Operational health without exposing business data.                                           |

**ADMIN MENTAL MODEL**

The administrator should mainly answer: Who are our people? Which team/domain do they belong to? What level of data should they see? SemantIQ converts those answers into enforceable security. The administrator should not need to design permissions, row filters or AI security manually.

### 2.6.2 Fabric Configuration Menu

| **Menu**             | **Submenu / key screens**                                                  | **Purpose**                                                      |
| -------------------- | -------------------------------------------------------------------------- | ---------------------------------------------------------------- |
| Overview             | Readiness; recent activity; blockers; health                               | Status of the data-to-intelligence factory.                      |
| Data Sources         | Connections; owners; domains; source health                                | Catalogue connected enterprise systems.                          |
| Connect Source       | Guided Source Wizard; credential validation; ownership                     | Business-friendly onboarding with minimal technical choices.     |
| Discovery            | Metadata; profiling; relationships; candidate keys                         | Understand source structure before ingestion.                    |
| Data Classification  | Business domain; owner; sensitivity; personal data; AI eligibility         | Convert discovered data into governed classifications.           |
| Ingestion            | Ingestion plan; landing; refresh; incremental/full patterns                | Bring approved source data into the governed Fabric environment. |
| Data Quality         | Rules; failures; trends; owners; remediation                               | Make quality visible, measurable and actionable.                 |
| Business Model       | Business entities; measures; dimensions; relationships; glossary           | Translate technical data into governed business concepts.        |
| Security Mapping     | Role/domain/scope mapping; row/object/field controls; access simulation    | Generate technical security from SemantIQ business access rules. |
| Semantic Model       | Certified measures; definitions; lineage; publication state                | Publish trusted analytics semantics rather than raw schema.      |
| AI Readiness         | Trusted questions; grounded answers; quality/security evaluation; approval | Prove the data product is safe and useful for conversational AI. |
| Pipelines & Refresh  | Schedules; SLA; failures; retries; incident actions                        | Operate ingestion and transformation reliably.                   |
| Power BI Publication | Datasets/models; reports; dashboard handoff; recipient validation          | Publish governed BI outputs while preserving access policy.      |
| Monitoring           | Data health; lineage; SLA; integration and publication health              | Observe the end-to-end data product after publication.           |

### 2.6.3 SemantIQ Workplace Menu

| **Menu**              | **Business purpose**                                                                                                        |
| --------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| Home                  | Personal role-aware summary: KPIs, what changed, attention required, risks, opportunities, AI insights and quick questions. |
| My Intelligence       | Only authorised business-domain intelligence. Domain branches are listed in 2.6.4.                                          |
| Ask SemantIQ          | Conversational analysis over governed data within the current identity/domain/scope.                                        |
| Explore               | Metrics, dimensions, trends, comparisons, drill-down and saved analysis without SQL or DAX.                                 |
| Insights              | What changed, drivers, explanations, saved insights and shareable authorised analysis.                                      |
| Risks & Opportunities | Detected risks, opportunities, anomalies and areas requiring attention.                                                     |
| Recommendations       | AI/data-backed recommendations, rationale, expected impact, owner and status.                                               |
| Decisions & Alerts    | Assigned decisions, alerts, action queue, acknowledgements and decision history.                                            |
| Reports & Dashboards  | Generated analysis, saved reports, Power BI dashboards, report creation and controlled publication.                         |
| My Workspace          | Saved views, questions, drafts, alerts, recent activity and personal working items.                                         |
| Help                  | Business guidance, feature help and guided resolution when an external Microsoft portal action is unavoidable.              |

### 2.6.4 My Intelligence Domain Navigation

My Intelligence is dynamic. Only domains granted to the signed-in user appear. The domain menu is business-facing; it does not expose Fabric workspaces, Lakehouses, pipelines or semantic-model administration.

| **Domain**              | **Recommended navigation**                                                                                                                                                        |
| ----------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Executive Intelligence  | Enterprise Overview; Strategic KPIs; Financial Performance; Sales Performance; Workforce; Operations; Customer; Cross-Functional Risks; Opportunities; Forecast; Ask Executive AI |
| Sales Intelligence      | Overview; Revenue; Pipeline; Opportunities; Customers; Products; Sales Team; Forecast; Trends; Risks & Opportunities; Ask Sales AI                                                |
| Finance Intelligence    | Financial Overview; Revenue; Expenses; Profitability; Cash Flow; Budget vs Actual; Receivables; Payables; Business Units; Variance Analysis; Forecast; Risks; Ask Finance AI      |
| People Intelligence     | Workforce Overview; Headcount; Attrition; Recruitment; Performance; Skills; Workforce Cost; Attendance; Learning & Development; Workforce Planning; Risks; Ask People AI          |
| Operations Intelligence | Operations Overview; Service Levels; Throughput; Productivity; Exceptions; Capacity; Cost; Trends; Forecast; Risks; Ask Operations AI                                             |
| Customer Intelligence   | Customer Overview; Segments; Revenue; Retention; Engagement; Satisfaction; At-Risk Customers; Growth Opportunities; Trends; Ask Customer AI                                       |
| Learning Intelligence   | Learning Overview; Enrolment; Attendance; Engagement; Progress; Completion; Assessment; At-Risk Learners; Skills; Intervention Opportunities; Ask Learning AI                     |
| Custom Intelligence     | Domain-specific navigation approved during domain planning; it must still follow the same security and business-language rules.                                                   |

## 2.7 Persona and Menu Visibility Baseline

| **Persona**                   | **System Administration**                    | **Fabric Configuration**                                | **Workplace / data scope**                                                                    |
| ----------------------------- | -------------------------------------------- | ------------------------------------------------------- | --------------------------------------------------------------------------------------------- |
| System Administrator          | Full platform administration                 | Platform/integration configuration as assigned          | No business-domain data by default; only separately entitled domains.                         |
| Organisation Administrator    | Organisation, users, roles, domains, reviews | Data onboarding/operations only when explicitly granted | Only entitled business domains and scope.                                                     |
| Executive                     | No admin by default                          | No Fabric administration by default                     | Multiple approved domains, organisation scope; highly restricted raw fields remain protected. |
| Domain Owner / Director       | Limited domain governance where approved     | Domain configuration/approval where delegated           | Own domain, approved business-unit/domain scope.                                              |
| Manager                       | No system administration                     | No Fabric administration                                | Assigned domain, Team or Business Unit scope.                                                 |
| Business User                 | No system administration                     | No Fabric administration                                | Assigned domain, Own or explicitly assigned record scope.                                     |
| Auditor / Compliance Reviewer | Read-only evidence only                      | Read-only evidence where required                       | No normal business access unless separately entitled.                                         |

## 2.8 Security Inheritance and Enforcement Model

**EFFECTIVE ACCESS**

Authenticated Identity + Active Organisation + Platform Role + Business Domain + Scope + Sensitivity + Ownership/Hierarchy + Policy = Effective Access

| **Enforcement point** | **Required behaviour**                                                                                                                     |
| --------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| Navigation / UI       | Show only authorised product areas, domain branches and actions. Never treat hidden navigation as the security control.                    |
| Application API       | Re-evaluate effective access for every protected read/write operation.                                                                     |
| Fabric / Data Layer   | Apply generated row, object and field restrictions from the same business access model.                                                    |
| Semantic Model        | Expose only certified measures/dimensions and authorised objects/rows.                                                                     |
| Power BI              | Use governed models and preserve recipient/user access; report generation must not weaken security.                                        |
| AI Retrieval          | Retrieve only data the current user could obtain through authorised non-AI paths. Never query everything and filter the answer afterwards. |
| Exports / Sharing     | Validate both content scope and recipient entitlement before exposure.                                                                     |
| Audit                 | Record material access/security/configuration events without leaking sensitive payloads.                                                   |

### 2.8.1 Required Access Examples

| **Example**                                                       | **Expected result**                                                                                                                                                |
| ----------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Salesperson asks for own performance                              | Allowed: own customers, opportunities, target, activity, performance, forecast and authorised insights.                                                            |
| Salesperson asks for another salesperson's individual performance | Denied or safely redirected unless that record is explicitly within the user's scope.                                                                              |
| Sales Manager asks for team comparison                            | Allowed only for members of assigned teams.                                                                                                                        |
| Finance user asks for Finance analysis                            | Allowed within Finance domain and assigned business-unit/domain scope. Sales/HR detail is not inherited.                                                           |
| HR/People user asks for employee analytics                        | Allowed within People domain and approved population; highly sensitive fields require stricter field/purpose controls.                                             |
| Executive views enterprise performance                            | Allowed across approved domains and organisation scope, but highly restricted raw identity/medical/bank-type fields remain protected unless separately authorised. |
| System Administrator browses business intelligence                | No business data by default. Platform administration does not imply Sales/Finance/People access.                                                                   |

## 2.9 AI and Power BI Boundary Rules

- AI is an intelligence interface, not a security bypass. Retrieval services receive an already-authorised user context and must never broaden it.
- Conversation history, prompts, responses, embeddings and telemetry are protected data and inherit retention/geography controls appropriate to their content.
- AI answers should prefer governed semantic measures and certified business definitions over raw tables.
- Generated insights and recommendations must distinguish data-backed facts from model interpretation.
- Power BI is an output/visualisation channel, not a second place for administrators to recreate security manually.
- A generated report/dashboard must inherit the governed semantic model and validate recipient access before sharing or publication.
- No LLM directly performs deterministic Fabric provisioning or privileged security configuration; validated application workflows execute approved changes.

## 2.10 Screen-to-Phase Traceability

| **Product area**                       | **Primary menus**                                                                                                                                                                                                      | **Delivery phase** |
| -------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------ |
| Pre-authentication / Application Entry | Login; First-Run Bootstrap; Authentication Callback; Access Not Assigned; Access Denied; Session Expired; Signed Out                                                                                                   | Phase 1            |
| System Administration                  | Administration Home; Organisation; Users & Groups; Roles & Access; Business Domains; Identity & SSO; Security Status; Access Reviews; Audit; System Health                                                             | Phase 1            |
| Fabric Configuration                   | Overview; Data Sources; Connect Source; Discovery; Data Classification; Ingestion; Data Quality; Business Model; Security Mapping; Semantic Model; AI Readiness; Pipelines & Refresh; Power BI Publication; Monitoring | Phase 2            |
| SemantIQ Workplace                     | Home; My Intelligence; Ask SemantIQ; Explore; Insights; Risks & Opportunities; Recommendations; Decisions & Alerts; Reports & Dashboards; My Workspace; Help                                                           | Phase 3            |

## 2.11 v2 Source-of-Truth Hierarchy

When Claude or a developer encounters conflicting information, use this order. SemantIQ v1 is historical reference only and is deliberately excluded from the implementation source-of-truth chain.

| **Priority** | **Source**                                                             | **Rule**                                                                                                                |
| ------------ | ---------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| 1            | Explicit product-owner decision for SemantIQ v2                        | Overrides lower-level documentation for the approved change.                                                            |
| 2            | This SemantIQ v2.1 Architecture Reset blueprint                        | Authoritative product structure, menu, security philosophy and delivery boundaries.                                     |
| 3            | Approved phase PLAN and DESIGN documents                               | Authoritative requirements and technical design for the active phase.                                                   |
| 4            | Shared CLaaS2SaaS UI/UX design standard                                | Only reusable implementation standard from the prior solution family.                                                   |
| 5            | Current SemantIQ v2 code and tests                                     | Evidence of what is implemented after confirming it matches approved design.                                            |
| Excluded     | SemantIQ v1 code, schema, migrations, permissions, workflows and tests | Do not reuse or treat as implementation precedent unless the product owner explicitly asks for a historical comparison. |

## 2.12 Claude / Developer Execution Contract

| **Rule**                                 | **Required behaviour**                                                                                                                                          |
| ---------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Greenfield means greenfield              | Create fresh v2 application code and database design. Do not copy v1 backend/business files, migrations, permission model, tests or workflows.                  |
| Only UI design is reusable               | Reuse the approved CLaaS2SaaS screen design system, shell, tokens, fonts, icons, page archetypes, responsive/accessibility rules and brand assets.              |
| Plan before design; design before code   | For every phase: PLAN -> approve -> DESIGN -> approve -> EXECUTE -> verify -> business acceptance.                                                              |
| One phase at a time                      | Do not implement Fabric Configuration before Phase 1 is accepted; do not implement Workplace before Phase 2 is accepted.                                        |
| Security is derived, not hand-configured | Normal admins assign business identity/domain/scope/sensitivity; technical enforcement is generated automatically.                                              |
| No hidden inheritance from v1            | Do not read v1 implementation to decide how a feature should work. Use v2 requirements and approved design.                                                     |
| Negative security tests are mandatory    | Prove cross-domain, cross-team, cross-user and restricted-field access is denied at API/data/AI/report layers.                                                  |
| Stop on conflict                         | If a request conflicts with this architecture, security model or phase boundary, stop and obtain an explicit product decision instead of silently compromising. |

**CLAUDE STARTING INSTRUCTION**

Before writing any SemantIQ v2 code, read this blueprint and the shared CLaaS2SaaS UI/UX design standard. Produce the active phase PLAN first. Do not inspect or reuse SemantIQ v1 backend implementation as a template. Stop for approval before creating the phase DESIGN, and stop again before execution.

| **1** | **System Administration & Security Foundation <br>**Establish a secure organisation, trustworthy identities and simple role/domain/scope access. |
| ----- | ------------------------------------------------------------------------------------------------------------------------------------------------ |

# **3\. Phase 1 - Purpose and Business Outcome**

Phase 1 creates the administration foundation required before any enterprise data is onboarded. The result is a secure SemantIQ organisation in which every user has a verified identity and a deterministic access context.

**Phase 1 success statement  
**An administrator can onboard the organisation and users without needing deep security expertise, while SemantIQ automatically enforces strong role, domain, scope and sensitivity controls.

## **3.1 In-Scope Capabilities**

| **Capability**                | **What Phase 1 must deliver**                                                                                                                            |
| ----------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Application Entry & Login** | Build the ground-zero Login page, Microsoft SSO flow, approved additional-IdP boundary, first-admin bootstrap, logout/session-expiry and refusal states. |
| **Organisation Setup**        | Create organisation profile, business units, teams, departments, legal entities and management hierarchy.                                                |
| **Identity & SSO**            | Microsoft Entra ID SSO first; architecture supports approved additional identity providers through a standard identity adapter.                          |
| **User & Group Onboarding**   | Synchronise or invite users/groups, map them to organisational structure and maintain active/inactive lifecycle.                                         |
| **Role Assignment**           | Assign System Admin, Organisation Admin, Executive, Manager, Business User and Auditor roles as appropriate.                                             |
| **Business Domains**          | Create/enable Sales, Finance, People, Operations, Customer, Learning and custom domains.                                                                 |
| **Scope Assignment**          | Own, Team, Business Unit, Domain or Organisation scope.                                                                                                  |
| **Sensitivity Policy**        | Standard, Confidential and Restricted access ceilings and step-up controls.                                                                              |
| **Security Posture**          | Show a simple health/status view instead of exposing low-level security switches.                                                                        |
| **Audit & Access Evidence**   | Record authentication, role/domain/scope changes, security exceptions and privileged administrative operations.                                          |
| **Access Review**             | Review who has privileged or sensitive access and confirm/remove it periodically.                                                                        |

## **3.2 Administrator Experience**

The Phase 1 experience should be a guided setup, not a collection of security pages.

| **Step** | **Admin action**                | **Expected result**                                                                                     |
| -------- | ------------------------------- | ------------------------------------------------------------------------------------------------------- |
| 1        | Bootstrap and sign in           | First System Administrator is established through the approved secure bootstrap and Microsoft SSO path. |
| 2        | Create / identify organisation  | Organisation profile established.                                                                       |
| 3        | Connect identity provider       | Tenant/domain trust and sign-in method validated.                                                       |
| 4        | Import users and groups         | People become visible without receiving business access by default.                                     |
| 5        | Define organisation structure   | Business units, teams, managers and reporting structure.                                                |
| 6        | Enable business domains         | Sales, Finance, People and other domains activated as required.                                         |
| 7        | Assign role + domain + scope    | Each user receives understandable business access.                                                      |
| 8        | Review security posture         | SemantIQ confirms baseline controls and highlights only items that require an organisation decision.    |
| 9        | Validate with access simulation | Admin can preview what a selected user/role would be allowed to see before publishing changes.          |

## **3.3 Phase 1 Core Screens**

| **Screen**              | **Purpose**                                                                                                                |
| ----------------------- | -------------------------------------------------------------------------------------------------------------------------- |
| **Login**               | Ground-zero branded sign-in page with Microsoft SSO as the primary action and only explicitly configured alternative IdPs. |
| **Administration Home** | Organisation readiness, users, domains, security posture, exceptions and action queue.                                     |
| **Organisation**        | Organisation details, business units, departments, teams, legal entities.                                                  |
| **Users & Groups**      | Identity, status, manager, team, role/domain/scope assignments.                                                            |
| **Roles & Access**      | Business-friendly assignment and access simulation; no low-level ACL complexity exposed.                                   |
| **Business Domains**    | Domain owner, authorised groups, default scope behaviour, data sensitivity expectations.                                   |
| **Identity & SSO**      | Connection status, tenant/provider, validation and sign-in health.                                                         |
| **Security Status**     | Enforced baseline, privileged access health, open exceptions, access review status.                                        |
| **Audit**               | Searchable evidence appropriate to the viewer's audit permission.                                                          |
| **Access Reviews**      | Review privileged/domain access by owner and due date.                                                                     |
| System Health           | Application, integration, job, connection and service health without exposing business data.                               |
| First-Run Bootstrap     | Controlled one-time establishment of organisation/tenant trust and the first System Administrator.                         |
| Access / Session States | Access not assigned, inactive account, access denied, session expired and signed-out experiences.                          |

## **3.4 Phase 1 Functional Requirements**

| **ID**  | **Requirement**                                                                                                                                                                                                  | **Priority** |
| ------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------ |
| SYS-000 | A fresh SemantIQ deployment shall start with no business access and shall require a controlled bootstrap of the first organisation/tenant trust and System Administrator before normal administration can begin. | Must         |
| SYS-001 | The platform shall authenticate every interactive user through an approved identity provider before any protected content is returned.                                                                           | Must         |
| SYS-002 | A user shall have no business-domain data access until a domain entitlement is granted.                                                                                                                          | Must         |
| SYS-003 | Effective access shall combine role, domain, scope and sensitivity.                                                                                                                                              | Must         |
| SYS-004 | System Administrator status shall not automatically grant Finance, People, Sales or other business-data access.                                                                                                  | Must         |
| SYS-005 | Managers shall inherit team visibility only for explicitly assigned teams/hierarchies.                                                                                                                           | Must         |
| SYS-006 | Unknown or conflicting access combinations shall fail closed.                                                                                                                                                    | Must         |
| SYS-007 | Administrators shall be able to preview effective access before publishing an access change.                                                                                                                     | Should       |
| SYS-008 | Every privileged access change shall create immutable audit evidence containing actor, action, target, result and timestamp.                                                                                     | Must         |
| SYS-009 | Security baseline controls shall not be disable-able through ordinary organisation administration.                                                                                                               | Must         |
| SYS-010 | Sensitive/privileged access shall support periodic access review and revocation.                                                                                                                                 | Must         |
| SYS-011 | The platform shall support Microsoft SSO and use an identity-provider abstraction for future approved providers.                                                                                                 | Must         |
| SYS-012 | The user interface shall expose business roles and scopes rather than vendor-specific permission terminology.                                                                                                    | Must         |
| SYS-013 | SemantIQ shall provide a dedicated pre-authentication Login page using the approved shared design, with Sign in with Microsoft as the primary Release 1 action.                                                  | Must         |
| SYS-014 | Successful identity-provider authentication shall not by itself create a SemantIQ user, domain entitlement or business-data access; unknown or unassigned identities shall fail closed.                          | Must         |
| SYS-015 | Self-registration shall be disabled by default. User onboarding and business access shall be administrator/directory governed.                                                                                   | Must         |
| SYS-016 | SemantIQ shall validate trusted SSO callback/protocol controls and resolve the verified identity to an active organisation/user before creating an application session.                                          | Must         |
| SYS-017 | The application shall provide safe logout, session-expired, access-not-assigned, inactive-account and access-denied states without leaking protected content or diagnostic secrets.                              | Must         |
| SYS-018 | Designated privileged actions shall support step-up authentication or re-authentication according to the approved security policy rather than exposing a user-configurable security toggle.                      | Must         |

## **3.5 Phase 1 Planning Deliverables**

- Approved persona and access matrix covering System Admin, Organisation Admin, Executive, Manager, Business User and Auditor.
- Organisation hierarchy model and ownership rules.
- Identity onboarding strategy including Microsoft SSO and future identity-provider boundary.
- Role/domain/scope/sensitivity definitions with examples and denied cases.
- Threat model for authentication, privilege escalation, cross-domain leakage, session theft and insider misuse.
- Phase 1 backlog, dependencies, acceptance criteria and release plan.
- Ground-zero user journey covering Login -> Microsoft SSO -> identity mapping -> access resolution -> role-appropriate landing, including refusal/error states.
- Approved first-System-Administrator bootstrap method and post-bootstrap disablement/recovery rules; no manual production database editing as the normal setup path.
- Session lifecycle requirements: issuance, timeout, logout, re-authentication/step-up, privilege change/revocation behaviour and security-event evidence.

## **3.6 Phase 1 Design Deliverables**

- Fresh identity and authorisation logical architecture.
- Fresh conceptual and physical data model for organisation, identity, hierarchy, role, domain, scope, sensitivity, exception and audit records.
- Backend authorisation policy contracts and access-decision interface.
- UI wireframes/screens using the approved shared CLaaS2SaaS design system.
- Audit event catalogue and sensitive-log redaction rules.
- Security test design including negative cross-domain and cross-scope scenarios.
- Deployment, secret-management, backup, monitoring and incident-response design for the new platform baseline.
- Login-page and pre-authentication state designs using the shared CLaaS2SaaS screen standard, including mobile, accessibility, loading and failure states.
- Microsoft Entra ID OIDC/SSO trust design: app registration/config boundary, callback validation, tenant/issuer validation, claims mapping and error handling.
- First-admin bootstrap and session/security lifecycle design with explicit negative tests for unknown tenant, unknown user, inactive user and no-entitlement cases.

## **3.7 Phase 1 Execution Sequence**

| **Order** | **Execution package**                                                                                                                                          | **Exit evidence**                                                                                    |
| --------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------- |
| 1         | Create the fresh v2 application baseline on the already-established Laravel/React/MySQL/cPanel delivery platform; add CI quality gates and no v1 dependencies. | Fresh application baseline deployed through the existing pipeline; infrastructure is not redesigned. |
| 2         | Implement Login page, first-admin bootstrap, Microsoft SSO/callback, identity mapping, secure session, logout/expiry and refusal states.                       | Ground-zero authentication path proven end-to-end, including fail-closed cases.                      |
| 3         | Implement organisation, teams and management hierarchy.                                                                                                        | Scope source established.                                                                            |
| 4         | Implement roles, domains, scopes and sensitivity policy.                                                                                                       | Access engine available.                                                                             |
| 5         | Implement admin screens and access simulation.                                                                                                                 | Business-friendly administration available.                                                          |
| 6         | Implement audit/security posture/access reviews.                                                                                                               | Control evidence available.                                                                          |
| 7         | Run security, isolation and privilege tests.                                                                                                                   | No cross-domain/scope leakage.                                                                       |
| 8         | UAT with representative Finance, Sales, HR and Executive personas.                                                                                             | Phase 1 business acceptance.                                                                         |

## **3.8 Phase 1 Exit Criteria**

- All users must authenticate successfully using the approved identity flow.
- A Sales user cannot access Finance or People data without explicit entitlement.
- A salesperson cannot see another salesperson's individual records unless scope permits it.
- A Sales manager can see assigned team data but not unrelated teams.
- An Executive can see authorised cross-domain enterprise intelligence without automatically receiving restricted raw personal fields.
- System Administrators can operate the platform without automatically gaining business-domain access.
- Security baseline and audit controls are enabled and cannot be bypassed through normal UI/API paths.
- Critical/high security test findings are resolved before Phase 2 production data onboarding.

| **2** | **Fabric Configuration & Intelligence Factory <br>**Take enterprise data from connection through governed semantic models and AI-ready intelligence. |
| ----- | ---------------------------------------------------------------------------------------------------------------------------------------------------- |

- An unauthenticated browser receives only the Login/application-entry experience and no protected shell, menu or business metadata.
- Microsoft SSO is proven end-to-end from the Login page through trusted callback validation, active-user mapping, access-context resolution and role-appropriate landing.
- Unknown/unassigned identities, inactive users, access-denied requests and expired sessions are proven to fail closed without protected-data leakage.
- The first-System-Administrator bootstrap can be completed securely and cannot remain an unrestricted reusable setup path afterwards.

# **4\. Phase 2 - Purpose and Business Outcome**

Phase 2 is the data and intelligence factory. It hides Microsoft Fabric complexity behind a guided SemantIQ configuration experience and produces governed data products that are ready for Power BI, analytics and AI.

**Phase 2 success statement  
**A data/product administrator can connect a business system, classify its purpose and ownership, then allow SemantIQ to guide or automate the Fabric journey until trusted semantic data is available to the authorised business domain.

## **4.1 End-to-End Data Journey**

| **Stage**                   | **Outcome**                                                                                   |
| --------------------------- | --------------------------------------------------------------------------------------------- |
| **1\. Connect**             | Register approved enterprise source and connection method.                                    |
| **2\. Discover**            | Inspect schemas, entities, fields, relationships and metadata.                                |
| **3\. Classify**            | Assign business domain, owner, sensitivity, personal-data indicators and access expectations. |
| **4\. Ingest**              | Create governed ingestion into the approved Fabric landing/data foundation.                   |
| **5\. Clean & Standardise** | Profile quality, standardise types/formats, resolve agreed data-quality rules.                |
| **6\. Model**               | Create business entities, relationships, calculations and conformed dimensions.               |
| **7\. Secure**              | Translate SemantIQ role/domain/scope rules into data/semantic access controls.                |
| **8\. Semantic Layer**      | Publish certified measures, dimensions, definitions, lineage and business vocabulary.         |
| **9\. AI Readiness**        | Validate AI-safe data products, governed questions, prompt/retrieval boundaries and quality.  |
| **10\. Publish**            | Expose authorised outputs to SemantIQ Workplace and approved Power BI experiences.            |

## **4.2 Phase 2 Core Screens**

| **Screen**               | **Purpose**                                                                                 |
| ------------------------ | ------------------------------------------------------------------------------------------- |
| **Data Sources**         | Connected systems, owner, domain, status, refresh, sensitivity and health.                  |
| Connect Source           | Guided source wizard, ownership and connection validation with minimum technical choices.   |
| **Discovery**            | Entities/fields/relationships, profiling findings and suggested classification.             |
| **Data Quality**         | Rules, failed checks, trends, owners and remediation status.                                |
| **Business Model**       | Business entities, measures, dimensions, relationships and glossary.                        |
| **Security Mapping**     | Visual mapping of domain/scope rules into Fabric/semantic security; generated by default.   |
| **Semantic Model**       | Certified measures, descriptions, definitions, lineage and publication status.              |
| **AI Readiness**         | Trusted questions, grounded answers, coverage, quality, security tests and go/no-go status. |
| **Pipelines & Refresh**  | Operational health, SLA, refresh status, failures and retry/incident actions.               |
| **Power BI Publication** | Approved datasets/models/reports to publish; security inherited from the governed model.    |
| Overview                 | Readiness, blockers, recent activity and end-to-end factory health.                         |
| Data Classification      | Business domain, owner, sensitivity, personal-data handling and AI eligibility.             |
| Ingestion                | Approved ingestion plans, landing, refresh and incremental/full patterns.                   |
| Monitoring               | Data product health, lineage, SLA, integration and publication monitoring.                  |

## **4.3 Source Onboarding - Business Questions Only**

The user should not need to configure Fabric internals unless a supported API or platform limitation genuinely requires a technical decision.

| **Question shown to user**               | **Why SemantIQ needs it**                     | **SemantIQ derives**                                              |
| ---------------------------------------- | --------------------------------------------- | ----------------------------------------------------------------- |
| What system is this?                     | Select connector/integration pattern.         | Connection workflow and technical adapter.                        |
| Which business domain owns it?           | Establish data ownership and access boundary. | Default workspace/model/security mapping.                         |
| Who is the data owner?                   | Governance accountability.                    | Approval and issue routing.                                       |
| Does it contain personal/sensitive data? | Apply protection and handling requirements.   | Field classification, masking/restriction, AI eligibility checks. |
| How fresh must the data be?              | Define service expectation.                   | Refresh/pipeline schedule and monitoring target.                  |
| Who should normally see it?              | Set access intent.                            | Domain/scope controls in semantic and downstream use.             |

## **4.4 Security Propagation**

**One policy model, multiple enforcement points  
**SemantIQ defines business access once. Fabric data security, semantic model security, Power BI consumption and AI retrieval must all consume the same effective identity/domain/scope policy instead of being independently configured.

| **Business rule**                               | **Expected technical outcome**                                                                                   |
| ----------------------------------------------- | ---------------------------------------------------------------------------------------------------------------- |
| **Salesperson = Sales + Own**                   | Only the salesperson's authorised Sales rows/objects are queryable by Workplace, Power BI and AI.                |
| **Sales Manager = Sales + Team**                | Team records are available; unrelated teams are filtered out.                                                    |
| **Finance = Finance + assigned scope**          | Finance measures/data are available only within approved entity/business-unit scope.                             |
| **Executive = approved domains + Organisation** | Enterprise-level semantic metrics are available across approved domains; restricted raw fields remain protected. |
| **System Admin = platform role only**           | Can operate connections, jobs and platform metadata without automatically gaining business-row access.           |

## **4.5 Phase 2 Functional Requirements**

| **ID**  | **Requirement**                                                                                                                                  | **Priority** |
| ------- | ------------------------------------------------------------------------------------------------------------------------------------------------ | ------------ |
| FAB-001 | SemantIQ shall onboard approved enterprise sources through pluggable connector/integration contracts.                                            | Must         |
| FAB-002 | Source onboarding shall capture business domain, owner, sensitivity and intended access before production publication.                           | Must         |
| FAB-003 | The platform shall discover source metadata and present a human-reviewable classification before ingestion.                                      | Must         |
| FAB-004 | Data quality rules and failures shall be visible with accountable owner and remediation status.                                                  | Must         |
| FAB-005 | The business semantic layer shall use governed measures, dimensions and definitions rather than exposing raw technical schema to business users. | Must         |
| FAB-006 | The same SemantIQ access model shall constrain Fabric/semantic/Power BI/AI consumption.                                                          | Must         |
| FAB-007 | AI-ready publication shall require security, lineage, data quality and grounded-answer validation.                                               | Must         |
| FAB-008 | SemantIQ shall clearly distinguish automated actions, recommended actions and portal-only/manual actions.                                        | Must         |
| FAB-009 | Operational pipeline failures shall be monitored, surfaced and attributable without exposing secrets.                                            | Must         |
| FAB-010 | Publishing changes that could broaden business-data access shall require impact preview and controlled approval.                                 | Must         |
| FAB-011 | Source credentials and service identities shall be stored and used through approved secret-management mechanisms.                                | Must         |
| FAB-012 | The system shall preserve lineage from source through published semantic/AI data product.                                                        | Should       |

## **4.6 Phase 2 Planning Deliverables**

- Prioritised source-system catalogue and first-wave business domains.
- Target Fabric tenancy/capacity/environment decisions and API feasibility assessment.
- Data ownership, classification and domain model.
- Expected freshness/SLA and data-volume assumptions.
- Source-to-intelligence workflow map and automation/manual boundary.
- Semantic model and AI-readiness success measures.
- Cost, capacity, data-egress and operational risk assumptions.

## **4.7 Phase 2 Design Deliverables**

- Fresh connector abstraction and source metadata model.
- Fabric provisioning/integration architecture with API contracts and error handling.
- Ingestion, quality, modelling and semantic publication architecture.
- Canonical business metadata model: domain, entity, measure, dimension, glossary, lineage and certification.
- Security-propagation design from SemantIQ identity context to data/semantic/Power BI/AI enforcement.
- AI-readiness validation framework and trusted-question/answer test set.
- Observability, pipeline monitoring, retry, failure handling and operational support design.
- Phase 2 UI flows using the approved shared design system.

## **4.8 Phase 2 Execution Sequence**

| **Order** | **Execution package**                                                        | **Exit evidence**                         |
| --------- | ---------------------------------------------------------------------------- | ----------------------------------------- |
| 1         | Build connector framework and first source connector.                        | Secure connection and metadata discovery. |
| 2         | Build source discovery/classification and data ownership flow.               | Governance context before ingestion.      |
| 3         | Implement Fabric landing/ingestion automation.                               | Data arrives in approved environment.     |
| 4         | Implement profiling, quality and standardisation.                            | Trusted quality layer.                    |
| 5         | Implement business modelling and semantic publication.                       | Governed business semantic model.         |
| 6         | Implement security propagation and negative access tests.                    | No cross-domain/scope leakage.            |
| 7         | Implement AI-readiness validation and governed data access endpoint/adapter. | Safe AI consumption.                      |
| 8         | Implement Power BI publication flow and inherited security validation.       | BI output ready.                          |
| 9         | Operational monitoring and failure recovery.                                 | Production supportable.                   |
| 10        | UAT with one full domain from source to insight.                             | Phase 2 acceptance.                       |

## **4.9 Phase 2 Exit Criteria**

- At least one representative business domain is proven end-to-end from source connection through governed semantic model.
- Source credentials are protected and not exposed to browser users or logs.
- Data quality, ownership, lineage and business definitions are visible and actionable.
- Sales/Finance/People domain isolation is proven at data and semantic layers.
- A salesperson and manager receive different row scopes from the same governed model without manual report-by-report security duplication.
- Power BI and AI consumption preserve the same user access boundary.
- AI readiness rejects or blocks ungoverned/unsecured data products.
- Operational failures can be detected, triaged and recovered without direct database manipulation.

## **4.10 Phase 2 Handoff to Workplace**

Phase 3 must consume published, governed products from Phase 2. It must not create a second data/security path around the Fabric intelligence factory.

| **Phase 2 handoff**                  | **What Phase 3 receives**                                                                                           |
| ------------------------------------ | ------------------------------------------------------------------------------------------------------------------- |
| **Certified semantic data products** | Approved measures, dimensions, business definitions and lineage.                                                    |
| **Effective security context**       | Role/domain/scope/sensitivity rules that can be applied consistently to queries and retrieval.                      |
| **AI-ready access surface**          | Validated governed query/retrieval interfaces and trusted-question evaluation evidence.                             |
| **Power BI publication contract**    | Approved semantic models and publication/security rules.                                                            |
| **Operational health**               | Refresh, data-quality and availability status that Workplace can surface when intelligence is stale or unavailable. |

| **3** | **SemantIQ Workplace & Decision Intelligence <br>**Give each user a personalised, role-aware intelligence workspace and governed AI assistant. |
| ----- | ---------------------------------------------------------------------------------------------------------------------------------------------- |

# **5\. Phase 3 - Purpose and Business Outcome**

Phase 3 is the business-facing product. Users sign in, SemantIQ determines their identity and effective access, and Workplace presents only authorised data, insights, questions, recommendations and decision workflows.

**Phase 3 success statement  
**Users can understand performance, ask questions, create analysis and make better decisions without knowing SQL, DAX, Fabric or the underlying security implementation.

## **5.1 Sign-In to Workplace Flow**

| **Step** | **Action**              | **Behaviour**                                                                                  |
| -------- | ----------------------- | ---------------------------------------------------------------------------------------------- |
| 1        | Authenticate            | Microsoft SSO or another approved identity provider verifies the user.                         |
| 2        | Resolve access context  | SemantIQ loads organisation, role, domains, scope, sensitivity and manager/team relationships. |
| 3        | Build personalised home | Only entitled KPIs, alerts, insights, decisions and domains are rendered.                      |
| 4        | View / Explore          | Queries execute against governed semantic data within the same access context.                 |
| 5        | Ask SemantIQ            | Conversational AI receives only authorised governed data/retrieval context.                    |
| 6        | Save / Share / Report   | Output preserves permissions; sharing cannot broaden access.                                   |
| 7        | Create Power BI output  | User can request a governed report/dashboard specification or approved publication workflow.   |

## **5.2 Workplace Primary Navigation**

| **Area**               | **Business purpose**                                                                                               |
| ---------------------- | ------------------------------------------------------------------------------------------------------------------ |
| **Home**               | Role-aware summary: KPIs, what changed, attention required, risks, opportunities, AI insights and quick questions. |
| **My Intelligence**    | Authorised domain intelligence such as Sales, Finance, People, Operations, Customer or Learning.                   |
| **Ask SemantIQ**       | Conversational analysis grounded in governed data and constrained to the current user.                             |
| **Explore**            | Drill into metrics, trends, dimensions, comparisons and saved analysis without SQL/DAX.                            |
| **Decisions & Alerts** | Assigned decisions, alerts, action queue, acknowledgements and decision history.                                   |
| Reports & Dashboards   | Generated analysis, saved reports, Power BI dashboards, report creation and controlled publication.                |
| **My Workspace**       | Personal saved views, questions, drafts, alerts and recent activity.                                               |
| Insights               | What changed, drivers, explanations, saved insights and authorised sharing.                                        |
| Risks & Opportunities  | Detected risks, opportunities, anomalies and areas requiring attention.                                            |
| Recommendations        | Data/AI-backed recommendations with rationale, expected impact, owner and status.                                  |
| Help                   | Business guidance and guided resolution for unavoidable external-platform actions.                                 |

## **5.3 Persona Behaviour**

| **Persona**         | **Workplace visibility**                                                         | **Example questions**                                                |
| ------------------- | -------------------------------------------------------------------------------- | -------------------------------------------------------------------- |
| Salesperson         | Own performance, customers, opportunities, target, forecast, personal risks.     | "Why am I behind target?" "Which of my opportunities are at risk?"   |
| Sales Manager       | Team performance, member comparison, team pipeline, risks and forecast.          | "Who needs attention this month?" "What changed in team conversion?" |
| Finance Manager     | Approved financial KPIs, variance, cash, AR/AP, profitability in assigned scope. | "What is driving margin deterioration?"                              |
| HR / People Manager | Approved workforce metrics and authorised employee-level information.            | "Where is attrition risk increasing?"                                |
| Executive           | Enterprise view across authorised domains with protected restricted fields.      | "What are the top five risks to this quarter?"                       |

## **5.4 Conversational AI Rules**

| **Rule**                      | **Required behaviour**                                                                                                                    |
| ----------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------- |
| **Identity-bound**            | Every conversation is associated with the authenticated user and active access context.                                                   |
| **Authorised retrieval only** | Retrieval/query services return only data the current user could access through non-AI interfaces.                                        |
| **Governed semantics first**  | Certified measures, business definitions and semantic models are preferred over raw tables.                                               |
| **No hidden-data inference**  | AI must not reveal or infer restricted individual information from aggregates, prompts, context windows or conversation history.          |
| **Fact vs interpretation**    | Responses distinguish source-backed facts from AI interpretation, forecast or recommendation.                                             |
| **Traceability**              | Where practical, answers expose metric definitions/source context and allow drill-through to governed analysis.                           |
| **Action boundary**           | AI may suggest or prepare actions; deterministic configuration/data changes go through validated application workflows and authorisation. |
| **Safe sharing**              | Saved/shared conversations and insights remain permission-aware; recipients only see content they are authorised to access.               |

## **5.5 Insights, Recommendations and Decisions**

SemantIQ Workplace should go beyond dashboards. The product should help users understand change, prioritise attention and make accountable decisions.

| **Capability**      | **Expected output**                                                               |
| ------------------- | --------------------------------------------------------------------------------- |
| **What changed**    | Material movement in KPIs, trends or drivers since a meaningful comparison point. |
| **Why it changed**  | Driver analysis supported by governed dimensions and measures.                    |
| **Risk**            | Condition likely to cause negative impact, with evidence and confidence/context.  |
| **Opportunity**     | Positive improvement/growth opportunity supported by data.                        |
| **Recommendation**  | Suggested next action, explicitly separated from factual observation.             |
| **Decision record** | Decision, owner, rationale, related evidence, follow-up date and outcome.         |
| **Alert**           | User-defined or system-generated condition with scope and recipient rules.        |

## **5.6 Power BI Interaction**

Power BI should be an output/visualisation channel, not a separate security administration burden. A user may request a report or dashboard based on authorised governed measures; SemantIQ should preserve the same access context and publication controls.

- Create a report/dashboard specification from a conversation or saved analysis.
- Select approved measures, dimensions, filters and recommended visual types.
- Publish through a controlled workflow using governed semantic models.
- Do not allow a generated report to bypass domain or row-level restrictions.
- Sharing must validate recipient access before content is exposed.

## **5.7 Phase 3 Functional Requirements**

| **ID**  | **Requirement**                                                                                                                                     | **Priority** |
| ------- | --------------------------------------------------------------------------------------------------------------------------------------------------- | ------------ |
| WRK-001 | After sign-in, Workplace shall render only the user's authorised domains, KPIs, records and actions.                                                | Must         |
| WRK-002 | Ask SemantIQ shall never return data outside the user's effective role/domain/scope/sensitivity access.                                             | Must         |
| WRK-003 | A salesperson with Own scope shall not be able to retrieve another salesperson's individual performance through UI, export, report or conversation. | Must         |
| WRK-004 | A manager shall be able to analyse authorised team members using the same governed model.                                                           | Must         |
| WRK-005 | Executives shall receive cross-domain intelligence only for domains and sensitivity levels they are authorised to access.                           | Must         |
| WRK-006 | Users shall be able to save authorised insights and analysis without changing underlying access policy.                                             | Must         |
| WRK-007 | Shared content shall re-evaluate recipient permissions and must not leak the originator's broader access.                                           | Must         |
| WRK-008 | AI responses shall distinguish factual metrics from interpretation/recommendation.                                                                  | Must         |
| WRK-009 | Users shall be able to convert approved analysis into a governed report/Power BI publication workflow.                                              | Should       |
| WRK-010 | The Workplace shall support responsive desktop/mobile usage using the shared CLaaS2SaaS design system.                                              | Must         |
| WRK-011 | Important insights, risks and recommendations shall support drill-down to source metrics/definitions where available.                               | Should       |
| WRK-012 | Conversation history and saved insights shall be treated as protected user/business data.                                                           | Must         |

## **5.8 Phase 3 Planning Deliverables**

- Prioritised personas and domain use cases for first release.
- Top KPI/decision questions per domain and their business definitions.
- Conversational use cases, failure modes and unacceptable-answer scenarios.
- Insight, recommendation, alert and decision taxonomy.
- Power BI/report handoff scenarios and sharing expectations.
- Business adoption and value KPIs such as time-to-insight, answer quality, usage and decision follow-through.

## **5.9 Phase 3 Design Deliverables**

- Personalised Workplace information architecture using the shared design standard.
- Role-aware home and domain-page designs including empty/loading/error/access-denied states.
- Conversational orchestration architecture with identity-bound retrieval and semantic-query adapter.
- Insight/recommendation/decision data model and lifecycle.
- Power BI report-generation/publication contract and security validation.
- Conversation and saved-insight protection/retention design.
- AI evaluation suite: access leakage, groundedness, correctness, hallucination, refusal and adversarial prompt scenarios.

## **5.10 Phase 3 Execution Sequence**

| **Order** | **Execution package**                                                | **Exit evidence**                                      |
| --------- | -------------------------------------------------------------------- | ------------------------------------------------------ |
| 1         | Build authenticated Workplace shell and role-aware Home.             | Personalised authorised experience.                    |
| 2         | Build My Intelligence domain experiences.                            | Domain KPIs/insights in correct scope.                 |
| 3         | Build Explore over governed semantic query services.                 | Self-service analysis without raw schema.              |
| 4         | Build Ask SemantIQ with identity-bound retrieval/query.              | Conversational intelligence with no access broadening. |
| 5         | Build insights, risks, opportunities, recommendations and alerts.    | Decision support beyond dashboards.                    |
| 6         | Build saved analysis, decision records and permission-aware sharing. | Persistent governed collaboration.                     |
| 7         | Build Power BI/report preparation/publication workflow.              | Governed report output.                                |
| 8         | Run AI/security/UAT evaluation across personas and domains.          | Phase 3 acceptance.                                    |

## **5.11 Phase 3 Exit Criteria**

- Every tested persona receives only authorised domains and record scope after authentication.
- Cross-user and cross-domain leakage tests pass for UI, semantic queries, AI, saved insights, sharing and Power BI output.
- AI answers meet agreed groundedness/correctness thresholds on the approved business question set.
- Business users can obtain useful insight without knowledge of Fabric, SQL or DAX.
- Managers can analyse team performance and executives can analyse authorised enterprise metrics using the same security model.
- Recommendations are clearly distinguished from factual observations and can be traced to supporting metrics/context.
- Critical/high security and privacy issues are closed before production go-live.
- Business UAT demonstrates measurable improvement in time-to-insight or decision workflow for selected use cases.

# **6\. Cross-Phase Planning, Design and Execution Governance**

The purpose of the clean-slate reset is to eliminate hidden dependencies and avoid mixing unfinished assumptions into a new architecture. Delivery therefore uses explicit approval gates between planning, design and execution.

## **6.1 Gate Model**

| **Gate**                       | **Evidence required**                                                                      | **Decision**                       |
| ------------------------------ | ------------------------------------------------------------------------------------------ | ---------------------------------- |
| **Gate A - Plan Approved**     | Scope, users, business outcomes, requirements, risks and acceptance criteria are clear.    | Authorise solution design.         |
| **Gate B - Design Approved**   | UX, architecture, data, security, integrations, validation and test strategy are coherent. | Authorise implementation.          |
| **Gate C - Build Verified**    | Automated tests, security tests, integration evidence and operational readiness pass.      | Authorise UAT/deployment.          |
| **Gate D - Business Accepted** | Representative users validate real workflows and access boundaries.                        | Close phase and unlock next phase. |

## **6.2 Definition of Ready for Coding**

- Requirement has a unique ID and acceptance criteria.
- User/persona and authorised data scope are known.
- Expected success, validation and error behaviour are designed.
- Data entities/fields and ownership are defined.
- Security and abuse cases are identified.
- External API/integration dependencies are verified.
- UI archetype/wireframe is approved where user-facing.
- Test cases include at least one negative/refusal/access-denied scenario for protected behaviour.

## **6.3 Definition of Done**

- Code and schema implement only the approved v2 design; no v1 dependencies were copied in implicitly.
- Automated functional, security, isolation and regression tests pass.
- Access-denied cases prove that protected data is not returned, not merely hidden in the UI.
- Logging contains required evidence but no secrets or unauthorised business payloads.
- Operational monitoring, backup/recovery and support behaviour are documented and tested as appropriate.
- UI follows the approved shared CLaaS2SaaS design system and passes responsive/accessibility review.
- UAT evidence maps back to phase requirements and exit criteria.
- Known limitations are explicit; no phase is accepted based solely on a green deployment pipeline.

## **6.4 Required Quality Gates**

| **Quality area**         | **Minimum control**                                                                                                                        |
| ------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------ |
| **Application security** | Threat modelling, secure coding checks, dependency scanning, vulnerability remediation and penetration testing before production maturity. |
| **Authorisation**        | Automated matrix tests for role/domain/scope/sensitivity, including cross-domain and cross-user negative cases.                            |
| **Data security**        | Data-layer/semantic-layer enforcement tests; no reliance on UI hiding.                                                                     |
| **AI security**          | Prompt-injection, data-exfiltration, cross-user leakage, groundedness and refusal evaluation.                                              |
| **Privacy**              | Data minimisation, protected conversation/history, sensitivity handling and deletion/retention behaviour according to approved policy.     |
| **Operations**           | Monitoring, alerting, backup, restore, incident response, deployment rollback and environment separation.                                  |
| **Change control**       | Peer review, CI gates, controlled production deployment and auditable release evidence.                                                    |

## **6.5 SOC 2-Aligned Direction**

SemantIQ should be engineered toward a SOC 2-aligned control environment, while avoiding claims of formal SOC 2 compliance until an independent attestation is completed. The v2 design should support evidence for security, availability, confidentiality, processing integrity and privacy controls as applicable to the organisation's chosen scope.

- Strong logical access and privileged access management.
- Controlled change management and deployment evidence.
- Security monitoring, incident response and access review.
- Confidentiality controls, encryption, secret management and data minimisation.
- Availability, backup, restoration and recovery testing.
- Vendor/integration risk and documented operational ownership.
- Continuous evidence generation instead of manual reconstruction during an audit.

# **7\. High-Level Delivery Roadmap**

The sequence below is intentionally dependency-driven rather than calendar-driven. Duration should be estimated only after the Phase 1 planning gate confirms team capacity, technology choices, environments and first-wave use cases.

| **Work package** | **Mode** | **Primary output**                                                                           |
| ---------------- | -------- | -------------------------------------------------------------------------------------------- |
| Phase 1A         | PLAN     | Personas, organisation model, security model, identity, acceptance matrix.                   |
| Phase 1B         | DESIGN   | Greenfield technical baseline, identity/access architecture, schema, UI, threat/test design. |
| Phase 1C         | EXECUTE  | Build and accept System Administration & Security Foundation.                                |
| Phase 2A         | PLAN     | Source priorities, Fabric environment, data ownership, first domain, semantic/AI outcomes.   |
| Phase 2B         | DESIGN   | Connectors, Fabric automation, data model, security propagation, semantic/AI readiness.      |
| Phase 2C         | EXECUTE  | Build and accept one complete source-to-AI-ready domain, then expand connectors/domains.     |
| Phase 3A         | PLAN     | Business questions, personas, KPI definitions, AI/insight/decision use cases.                |
| Phase 3B         | DESIGN   | Workplace UX, semantic query, AI orchestration, insight/decision/report architecture.        |
| Phase 3C         | EXECUTE  | Build, evaluate and accept Workplace across representative roles and domains.                |

# 8\. Fixed Technical Baseline and Application Decisions Still Required

SemantIQ v2 is greenfield at the application/business-logic level, but the hosting/runtime/deployment platform below is already established and is not a Phase 1 redesign decision. Claude should build on this fixed platform and focus design effort on the new application, identity, security, data and user journeys.

| Platform / decision                        | v2.2 direction                                                                                                                                                                 |
| ------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Backend - fixed                            | Laravel 13 / PHP 8.5. Do not re-select unless separately instructed.                                                                                                           |
| Frontend - fixed                           | React 19. Do not re-select unless separately instructed.                                                                                                                       |
| Database platform - fixed                  | MySQL on cPanel. Design a fresh v2 schema; do not reuse v1 tables/migrations.                                                                                                  |
| Deployment - fixed                         | GitHub Actions -> cPanel over SSH/rsync. Existing synchronization is already established and treated as infrastructure.                                                        |
| Architecture - fixed                       | Modular monolith.                                                                                                                                                              |
| Tenancy - fixed boundary                   | Single-tenant deployment today with explicit multi-tenant-ready boundaries.                                                                                                    |
| Identity - design required                 | Define Microsoft Entra ID SSO/OIDC implementation, claims/tenant mapping, first-admin bootstrap, session/re-authentication policy and optional approved secondary-IdP adapter. |
| Secrets/security - design required         | Define secure application secret handling, key rotation, session protection, security logging and privileged-operation controls within the fixed platform.                     |
| Fabric integration - design required later | Confirm supported Fabric APIs, automation identity and operation boundaries during Phase 2 planning.                                                                           |
| AI orchestration - design required later   | Choose model/provider/orchestration only during the approved Phase 2/3 design, after data security, residency, quality and cost requirements are defined.                      |

# **9\. Initial Backlog Epics**

| **Epic** | **Name**                          | **Scope**                                                                                                                                                                                                      | **Phase** |
| -------- | --------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------- |
| E01      | Greenfield Application Foundation | Fresh v2 codebase/repository on the fixed Laravel 13/PHP 8.5 + React 19 + MySQL/cPanel baseline; use the established GitHub Actions SSH/rsync deployment pattern without importing v1 application code/schema. | Phase 1   |
| E02      | Identity & Session                | Ground-zero Login, Microsoft Entra SSO/OIDC, first-admin bootstrap, identity mapping, secure session, logout/expiry, revocation and step-up authentication.                                                    | Phase 1   |
| E03      | Organisation & Hierarchy          | Organisation, business units, teams, managers and scope resolution.                                                                                                                                            | Phase 1   |
| E04      | Role/Domain/Scope Security        | Authorisation engine, sensitivity policy, access simulation and denial tests.                                                                                                                                  | Phase 1   |
| E05      | Security Operations               | Audit, posture, access reviews, exceptions and security monitoring.                                                                                                                                            | Phase 1   |
| E06      | Data Source Framework             | Connector abstraction, credentials, discovery and source health.                                                                                                                                               | Phase 2   |
| E07      | Fabric Data Foundation            | Ingestion, quality, standardisation and operational monitoring.                                                                                                                                                | Phase 2   |
| E08      | Business & Semantic Model         | Entities, measures, dimensions, glossary, lineage, certification.                                                                                                                                              | Phase 2   |
| E09      | Security Propagation              | Translate SemantIQ access context into Fabric/semantic/Power BI/AI controls.                                                                                                                                   | Phase 2   |
| E10      | AI Readiness                      | Governed retrieval/query, trusted Q&A, evaluation and publication gates.                                                                                                                                       | Phase 2   |
| E11      | Workplace Core                    | Personalised Home, My Intelligence and role-aware navigation.                                                                                                                                                  | Phase 3   |
| E12      | Ask SemantIQ                      | Identity-bound conversational analysis over governed semantic data.                                                                                                                                            | Phase 3   |
| E13      | Insights & Decisions              | Risks, opportunities, recommendations, alerts and decision records.                                                                                                                                            | Phase 3   |
| E14      | Reports & Power BI                | Governed report generation/publication and permission-aware sharing.                                                                                                                                           | Phase 3   |

# **10\. Key Decisions to Lock Before Phase 1 Build**

| **Decision** | **Question**                                                                                                                                         |
| ------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| **D-01**     | What controlled method will bootstrap the first System Administrator and how is that bootstrap disabled/protected after first use?                   |
| **D-02**     | What Microsoft Entra ID tenant/app-registration/OIDC configuration is required for Release 1, and are any secondary identity providers required now? |
| **D-03**     | What are the session timeout, logout, re-authentication/step-up, privilege-change and revocation rules for normal and privileged users?              |
| **D-04**     | What are the exact v2 roles, domain list and scope hierarchy for Release 1?                                                                          |
| **D-05**     | Which data sensitivity levels and restricted field types require additional control?                                                                 |
| **D-06**     | What is the first business domain and source system for Phase 2 end-to-end proof?                                                                    |
| **D-07**     | Which Microsoft Fabric tenancy/capacity/environment will be used for development and UAT?                                                            |
| **D-08**     | What AI/model hosting options are allowed considering data security, residency, cost and enterprise policy?                                          |
| **D-09**     | What are the RPO, RTO, backup retention and incident-response ownership requirements for the new v2 application data?                                |
| **D-10**     | What evidence/control scope is required for the organisation's SOC 2-aligned roadmap and later formal audit?                                         |

# **11\. Final Product Boundary**

**What SemantIQ v2 must feel like  
**System Admin configures people and business structure, not low-level security. Data/Fabric administrators guide data from source to trusted AI-ready semantic products. Business users receive a personalised Workplace where identity automatically determines what they can see, ask, analyse, save, share and publish.

| **Product part**      | **Admin/user mental model**                                              | **Complexity hidden by SemantIQ**                                                                       |
| --------------------- | ------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------- |
| System Administration | "Who are our people, teams and domains, and what level should they see?" | Permissions, policy evaluation, security enforcement, audit, session controls, privileged controls.     |
| Fabric Configuration  | "Bring this business data in and make it trustworthy and AI-ready."      | Fabric resources, ingestion plumbing, quality implementation, semantic security, lineage, AI readiness. |
| SemantIQ Workplace    | "Show me what matters, let me ask questions and help me decide."         | SQL, DAX, model topology, Fabric internals, retrieval orchestration and row/object/field security.      |

# **12\. Approval Statement for the v2 Baseline**

Approval of this document means approval of the product structure and delivery method. The fixed cPanel technical baseline in Section 8 is already established and is not reopened by this blueprint. Approval does not permit coding until the Phase 1 PLAN and DESIGN gates - including ground-zero Login/SSO and first-admin bootstrap design - are completed and separately approved.

- Three product phases are fixed: System Administration, Fabric Configuration, SemantIQ Workplace.
- SemantIQ v2 is greenfield. No v1 implementation reuse is permitted.
- The shared CLaaS2SaaS UI/UX design system is the only implementation asset intentionally reused.
- Security is secure-by-default and policy-driven, with user-facing configuration kept simple.
- Role + Domain + Scope + Sensitivity is the common access model across the application, Fabric, Power BI and AI.
- Each phase follows PLAN -> DESIGN -> EXECUTE -> VERIFY -> BUSINESS ACCEPTANCE before the next phase is unlocked.
- The fixed runtime/deployment baseline is Laravel 13/PHP 8.5 + React 19 + MySQL on cPanel + GitHub Actions SSH/rsync + modular monolith; v2 redesign starts from application ground zero on top of that established infrastructure.
- The first screen is Login, with Microsoft SSO as the primary Release 1 identity path; protected menus/data appear only after identity and effective access are resolved.

# **13\. Planning Sign-Off**

| **Item**                                     | **Decision / Owner** |
| -------------------------------------------- | -------------------- |
| **Product blueprint approved**               | Pending              |
| **Phase 1 planning authorised**              | Pending              |
| Ground-zero Login / SSO design approved      | Pending              |
| Security and access model approved           | Pending              |
| **First Phase 2 domain/source nominated**    | Pending              |
| **Business acceptance owner(s)**             | Pending              |
| Fixed cPanel technical baseline acknowledged | Pending              |

# **Appendix A - Phase Planning Checklist**

| **Checklist item**                             | **Status / Notes** |
| ---------------------------------------------- | ------------------ |
| **Business outcome defined**                   |                    |
| **Personas defined**                           |                    |
| **Domain and scope rules defined**             |                    |
| **Requirements uniquely identified**           |                    |
| **Negative security scenarios defined**        |                    |
| **Data ownership/classification defined**      |                    |
| **External APIs verified**                     |                    |
| **UI flows/wireframes approved**               |                    |
| **Architecture/data/security design approved** |                    |
| **Test strategy approved**                     |                    |
| **Deployment/recovery design approved**        |                    |
| **UAT and acceptance criteria approved**       |                    |

# **Appendix B - Requirement Traceability Template**

| **Requirement** | **Business intent**          | **Access context**           | **Design**               | **Verification**  | **Status**     |
| --------------- | ---------------------------- | ---------------------------- | ------------------------ | ----------------- | -------------- |
| &lt;REQ-ID&gt;  | &lt;Business requirement&gt; | &lt;Persona/domain/scope&gt; | &lt;Design component&gt; | &lt;Test case&gt; | &lt;Status&gt; |
| &lt;REQ-ID&gt;  | &lt;Business requirement&gt; | &lt;Persona/domain/scope&gt; | &lt;Design component&gt; | &lt;Test case&gt; | &lt;Status&gt; |
| &lt;REQ-ID&gt;  | &lt;Business requirement&gt; | &lt;Persona/domain/scope&gt; | &lt;Design component&gt; | &lt;Test case&gt; | &lt;Status&gt; |
| &lt;REQ-ID&gt;  | &lt;Business requirement&gt; | &lt;Persona/domain/scope&gt; | &lt;Design component&gt; | &lt;Test case&gt; | &lt;Status&gt; |
| &lt;REQ-ID&gt;  | &lt;Business requirement&gt; | &lt;Persona/domain/scope&gt; | &lt;Design component&gt; | &lt;Test case&gt; | &lt;Status&gt; |
| &lt;REQ-ID&gt;  | &lt;Business requirement&gt; | &lt;Persona/domain/scope&gt; | &lt;Design component&gt; | &lt;Test case&gt; | &lt;Status&gt; |
| &lt;REQ-ID&gt;  | &lt;Business requirement&gt; | &lt;Persona/domain/scope&gt; | &lt;Design component&gt; | &lt;Test case&gt; | &lt;Status&gt; |
| &lt;REQ-ID&gt;  | &lt;Business requirement&gt; | &lt;Persona/domain/scope&gt; | &lt;Design component&gt; | &lt;Test case&gt; | &lt;Status&gt; |
| &lt;REQ-ID&gt;  | &lt;Business requirement&gt; | &lt;Persona/domain/scope&gt; | &lt;Design component&gt; | &lt;Test case&gt; | &lt;Status&gt; |