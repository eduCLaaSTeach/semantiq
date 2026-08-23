# SemantIQ Role and Access Model

Version: 1.0
Status: Authoritative role model baseline

## 1. Purpose

SemantIQ uses role-based and policy-based access so each user sees only the business intelligence, data, configuration and administrative actions appropriate to their responsibilities.

The role model has two dimensions:

1. Platform role - what the user may do in SemantIQ.
2. Business-domain entitlement - which domains and data the user may access.

A role alone never grants access to all business data. Effective access is the intersection of role, organisation scope, domain entitlement, data security policy and source/semantic permissions.

## 2. Platform Roles

### System Administrator

Purpose: operate the SemantIQ platform itself.

Typical capabilities:
- platform configuration;
- integration setup;
- Fabric environment automation;
- user/role administration;
- security, data protection and sovereignty configuration;
- deployment and monitoring;
- audit and diagnostics.

System Administrator does not automatically receive unrestricted HR, finance or other sensitive business data. Business-domain access must be explicitly granted.

### Administrator

Purpose: administer a customer organisation and its data-intelligence environment.

Typical capabilities:
- organisation configuration;
- user and group administration;
- source onboarding;
- business model/governance administration;
- domain-level configuration;
- approval workflows;
- operational monitoring.

### Domain Owner

Purpose: own a business intelligence domain such as Sales, Finance, People, Operations, Customer or Learning.

Typical capabilities:
- approve domain definitions and KPIs;
- approve business glossary terms;
- review data quality;
- approve semantic definitions;
- maintain verified answers and ground-truth questions;
- review domain insights and AI quality;
- manage authorised domain users where policy allows.

### Analyst / Collaborator

Purpose: explore and analyse approved business information.

Typical capabilities:
- access assigned intelligence domains;
- explore governed metrics;
- save analysis and insights;
- use Ask SemantIQ;
- create alerts where permitted;
- contribute comments or decisions.

### Contributor

Purpose: interact with assigned insights, alerts, decisions or records without broad analytical/admin rights.

Typical capabilities:
- view assigned/owned business intelligence;
- use approved conversational questions;
- acknowledge/assign/comment on alerts or decisions as permitted;
- save personal views.

### Viewer

Purpose: read authorised business intelligence.

Typical capabilities:
- view assigned dashboards/metrics;
- read insights;
- use conversational intelligence if specifically allowed;
- no configuration or data-management changes.

### Auditor / Compliance Reviewer

Purpose: review governance evidence without operating the platform.

Typical capabilities:
- read audit trail;
- review data-protection/sovereignty evidence;
- review access and configuration changes;
- read policy and decision records;
- no operational change rights by default.

## 3. Business-Domain Entitlements

Supported baseline domains:

- Executive
- Sales
- Finance
- People
- Operations
- Customer
- Learning
- Custom domain

Entitlements may be constrained by:
- business unit;
- team;
- geography;
- legal entity;
- cost centre;
- department;
- customer segment;
- programme/course;
- other approved organisational dimensions.

## 4. Example Effective Access

### CEO

- Platform role: Viewer or Executive Viewer
- Domains: Executive, Sales, Finance, People, Operations, Customer
- Scope: organisation-wide, subject to highly restricted fields
- Administration: none unless separately granted

### Sales Director

- Platform role: Domain Owner
- Domain: Sales
- Scope: assigned region/business unit or organisation-wide sales
- Finance: only approved sales-financial metrics, not finance ledgers
- People: only approved sales-team metrics, not confidential HR records

### Finance Director

- Platform role: Domain Owner
- Domain: Finance
- Scope: approved entities/cost centres
- Sensitive fields: controlled by finance policy

### HR Director

- Platform role: Domain Owner
- Domain: People
- Scope: approved organisation population
- Sensitive HR fields: additional field/purpose controls

### Sales Manager

- Platform role: Analyst / Collaborator
- Domain: Sales
- Scope: assigned region/team

### Platform Administrator

- Platform role: System Administrator
- Domain: none by default
- Business data: only metadata/technical data needed to operate the platform unless separately entitled

## 5. Authorization Model

Every protected request should evaluate:

```text
Authenticated User
    AND
Active Organisation/Tenant
    AND
Platform Role Policy
    AND
Business Domain Entitlement
    AND
Record/Data Scope
    AND
Field/Object Security
    AND
Data Protection Policy
    AND
Sovereignty/Processing Policy where applicable
```

Frontend menu visibility is convenience only. Backend authorization is mandatory for every protected API/action.

## 6. Administrative Segregation

High-impact actions require additional controls, including where appropriate:

- explicit confirmation;
- maker-checker approval;
- re-authentication;
- reason for change;
- impact preview;
- audit record;
- correlation ID;
- rollback/recovery record.

Examples include:
- granting administrator roles;
- changing cross-geo settings;
- changing storage/AI processing geography;
- enabling public network access;
- altering production workspaces;
- publishing production agents;
- changing data-retention policy;
- destructive data actions.

## 7. AI and Conversational Access

Ask SemantIQ must never broaden access.

The conversational layer must:
- use the current user's identity or an approved delegated security context;
- query only authorised semantic/data sources;
- preserve RLS/OLS/CLS and domain policies;
- not reveal hidden fields in generated explanations;
- not answer from another domain merely because the underlying model can access it;
- treat conversation history as protected data;
- apply retention and sovereignty policy to prompts, responses, embeddings and telemetry.

## 8. Role-to-Menu Baseline

| Menu | System Admin | Admin | Domain Owner | Analyst | Contributor | Viewer | Auditor |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Home | Yes | Yes | Yes | Yes | Yes | Yes | Limited |
| My Intelligence | Entitled only | Entitled only | Domain | Domain | Assigned | Read | No/limited |
| Ask SemantIQ | Entitled only | Entitled only | Domain | Domain | If allowed | If allowed | No |
| Explore | Entitled only | Entitled only | Domain | Domain | Limited | Read/No | No |
| Decisions & Alerts | Admin | Admin | Domain | Domain | Assigned | Read | Read evidence |
| Reports & Insights | Entitled | Entitled | Domain | Domain | Assigned | Read | Read evidence |
| My Workspace | Yes | Yes | Yes | Yes | Yes | Yes | Yes |
| Administration | Full platform | Organisation | Domain subset | No | No | No | Read evidence only |

## 9. Data Protection Principles

- Least privilege by default.
- Deny by default for unknown domain/scope combinations.
- Sensitive HR, finance, identity and personal data require explicit classification and access rules.
- Technical administrators should not automatically gain business-data visibility.
- Purpose limitation should be supported for highly sensitive data.
- All permission changes are auditable.
- Access reviews should be supported for privileged and sensitive-domain roles.
