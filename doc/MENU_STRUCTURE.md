# SemantIQ Menu Structure

Version: 1.1
Status: Authoritative functional navigation baseline

## 1. Navigation Principle

SemantIQ is a Business Decision Intelligence application first and a Microsoft Fabric control plane second.

Business users must see business outcomes, role-relevant metrics, insights, risks, recommendations and conversational intelligence. They must not be required to understand or navigate Microsoft Fabric concepts such as capacities, Lakehouses, pipelines, semantic models, Data Agents or tenant settings.

Platform administrators configure desired outcomes in SemantIQ. SemantIQ performs supported Microsoft Fabric, Power BI, Entra and AI configuration through APIs and guided automation behind the scenes. Microsoft portals are used only when an operation cannot be completed safely or is not exposed by a supported API.

The visual shell, navigation presentation, icons, spacing, themes and responsive behavior remain governed by `doc/design-system/ui-and-ux-layout-template-shared.md`.

## 2. Experience Layers

```text
SEMANTIQ
|
|-- Business Intelligence Experience      - default for business users
|
`-- Platform Control Plane                - privileged administrators only
```

## 3. Business User Primary Navigation

```text
Home
My Intelligence
Ask SemantIQ
Explore
Decisions & Alerts
Reports & Insights
My Workspace
Help
```

Administration appears only for users with an administrative entitlement.

## 4. Home - Personal Intelligence Home

The Home page is role-aware and personalised.

```text
Home
|-- Executive Summary / Role Summary
|-- My KPIs
|-- What Changed
|-- Attention Required
|-- Risks
|-- Opportunities
|-- AI Insights
|-- Recommended Actions
|-- Recent Decisions
|-- My Alerts
`-- Quick Questions
```

### Expected behavior

- Determine the user's effective role, business domain, organisation scope and data entitlements after sign-in.
- Render only approved KPIs, data and actions.
- Explain important changes rather than displaying charts alone.
- Provide drill-through actions such as `Why?`, `Explore`, `Compare`, `Ask SemantIQ`, `Create Alert` and `Assign`.
- Respect row-level, object-level, field-level and business-domain permissions.
- Never expose underlying Fabric implementation details to a normal business user.

## 5. My Intelligence

```text
My Intelligence
|-- Executive Intelligence
|-- Sales Intelligence
|-- Finance Intelligence
|-- People Intelligence
|-- Operations Intelligence
|-- Customer Intelligence
|-- Learning Intelligence
`-- Custom Business Domains
```

Only domains authorised for the current user are visible.

### 5.1 Executive Intelligence

```text
Executive Intelligence
|-- Enterprise Overview
|-- Strategic KPIs
|-- Financial Performance
|-- Sales Performance
|-- Workforce
|-- Operations
|-- Customer
|-- Cross-Functional Risks
|-- Opportunities
|-- Forecast
`-- Ask Executive AI
```

### 5.2 Sales Intelligence

```text
Sales Intelligence
|-- Overview
|-- Revenue
|-- Pipeline
|-- Opportunities
|-- Customers
|-- Products
|-- Sales Team
|-- Forecast
|-- Trends
|-- Risks & Opportunities
`-- Ask Sales AI
```

### 5.3 Finance Intelligence

```text
Finance Intelligence
|-- Financial Overview
|-- Revenue
|-- Expenses
|-- Profitability
|-- Cash Flow
|-- Budget vs Actual
|-- Receivables
|-- Payables
|-- Business Units
|-- Variance Analysis
|-- Forecast
|-- Risks
`-- Ask Finance AI
```

### 5.4 People Intelligence

```text
People Intelligence
|-- Workforce Overview
|-- Headcount
|-- Attrition
|-- Recruitment
|-- Performance
|-- Skills
|-- Workforce Cost
|-- Attendance
|-- Learning & Development
|-- Workforce Planning
|-- Risks
`-- Ask People AI
```

Sensitive HR data must be subject to stricter field, purpose and role controls than general business metrics.

### 5.5 Operations Intelligence

```text
Operations Intelligence
|-- Operations Overview
|-- Service Levels
|-- Throughput
|-- Productivity
|-- Exceptions
|-- Capacity
|-- Cost
|-- Trends
|-- Forecast
|-- Risks
`-- Ask Operations AI
```

### 5.6 Customer Intelligence

```text
Customer Intelligence
|-- Customer Overview
|-- Segments
|-- Revenue
|-- Retention
|-- Engagement
|-- Satisfaction
|-- At-Risk Customers
|-- Growth Opportunities
|-- Trends
`-- Ask Customer AI
```

### 5.7 Learning Intelligence

```text
Learning Intelligence
|-- Learning Overview
|-- Enrolment
|-- Attendance
|-- Engagement
|-- Progress
|-- Completion
|-- Assessment
|-- At-Risk Learners
|-- Skills
|-- Intervention Opportunities
`-- Ask Learning AI
```

## 6. Ask SemantIQ

`Ask SemantIQ` is a first-class business experience, not an administrator feature.

```text
Ask SemantIQ
|-- New Conversation
|-- Suggested Questions
|-- Domain Selector
|-- Conversation History
|-- Saved Questions
`-- Shared Questions
```

### Conversational behavior

- Automatically constrain answers to the user's authorised domains and data scope.
- Prefer governed semantic measures and certified business definitions.
- Return a concise answer, supporting metrics/visuals, explanation and source context where available.
- Support follow-up questions without forcing the user to restate context.
- Offer useful actions such as `Show analysis`, `Compare`, `Drill down`, `Create alert` or `Save insight`.
- Never allow an LLM to bypass security or directly perform deterministic Fabric administration.
- Clearly distinguish data-backed facts from AI-generated interpretation or recommendation.

## 7. Explore

```text
Explore
|-- Business Metrics
|-- Dimensions
|-- Trends
|-- Comparisons
|-- Drill Down
|-- Saved Analysis
`-- My Views
```

Users explore governed business concepts without SQL, DAX or knowledge of the physical data model.

## 8. Decisions & Alerts

```text
Decisions & Alerts
|-- Attention Required
|-- Risks
|-- Opportunities
|-- Anomalies
|-- Recommendations
|-- My Alerts
|-- Assigned Decisions
`-- Decision History
```

Decision records should preserve the insight, evidence, owner, status, comments, decision and timestamp. AI recommendations are advisory unless an explicitly approved workflow says otherwise.

## 9. Reports & Insights

```text
Reports & Insights
|-- My Reports
|-- Executive Reports
|-- Sales
|-- Finance
|-- People
|-- Operations
|-- Customer
|-- Learning
|-- Saved Insights
`-- Scheduled Reports
```

## 10. My Workspace

```text
My Workspace
|-- My Dashboard
|-- Saved Insights
|-- Saved Questions
|-- My Alerts
|-- My Reports
|-- My Decisions
`-- Preferences
```

## 11. Help

```text
Help
|-- Getting Started
|-- Using My Intelligence
|-- Asking SemantIQ
|-- Exploring Data
|-- Decisions & Alerts
|-- Reports
|-- Privacy & Data Use
|-- Troubleshooting
`-- Contact Support
```

Business help must use business terminology. Fabric-specific setup help belongs in the administrator experience.

## 12. Administration - Privileged Control Plane

```text
Administration
|-- Platform Overview
|-- Organisation & Users
|-- Security
|-- Fabric Environment
|-- Data Sources
|-- Data Engineering
|-- Data Quality
|-- Business Model
|-- Semantic Intelligence
|-- AI & Agents
|-- Governance
|-- Data Protection
|-- Data Sovereignty
|-- Deployment
|-- Monitoring
`-- System Configuration
```

### 12.1 Platform Overview

```text
Platform Overview
|-- Setup Progress
|-- Environment Health
|-- Data Health
|-- Intelligence Health
|-- Security & Sovereignty Status
|-- Pending Actions
|-- Failed Automations
`-- Recent Changes
```

### 12.2 Organisation & Users

```text
Organisation & Users
|-- Organisation Profile
|-- Business Units
|-- Teams
|-- Users
|-- Roles
|-- Permissions
|-- Domain Entitlements
|-- Security Groups
`-- Access Reviews
```

`Permissions` was added by DEC-001, closing gap M3: ADM-007 requires the screen
and this list did not carry it.

`Security Groups` has no feature specifying what it shows. It stays in the tree
as an unbuilt destination rather than being invented. Gap M7 remains open.

### 12.3 Fabric Environment

```text
Fabric Environment
|-- Tenant Connection
|-- SSO & Entra Configuration
|-- Fabric Readiness
|-- Capacity
|-- Tenant Settings
|-- Workspaces
|-- Network & Private Connectivity
|-- Gateways
|-- API Permissions
`-- Environment Validation
```

The administrator stays in SemantIQ. SemantIQ executes supported setup using backend APIs. If Microsoft requires a manual admin action, SemantIQ opens a guided help flow with exact steps and then validates completion.

### 12.4 Data Sources

```text
Data Sources
|-- Source Registry
|-- Add Source
|-- Authentication
|-- Schema Discovery
|-- Connection Test
|-- Source Health
`-- Source Help
```

Supported source categories include SharePoint, Excel/files, SQL Server, Azure SQL, Dataverse, Dynamics 365, Business Central, REST APIs, ERP/CRM/LMS platforms and custom connectors.

### 12.5 Data Engineering

```text
Data Engineering
|-- Ingestion Jobs
|-- Pipelines
|-- Mirroring
|-- Shortcuts
|-- Dataflow Gen2
|-- Incremental Load
|-- Schedules
|-- Lakehouse
|-- Bronze
|-- Silver
|-- Gold
`-- Run History
```

### 12.6 Data Quality

```text
Data Quality
|-- Profiling
|-- Quality Rules
|-- Validation Rules
|-- Duplicates
|-- Null Handling
|-- Standardisation
|-- Anomalies
|-- Rejects
`-- Quality Scorecard
```

### 12.7 Business Model

```text
Business Model
|-- Business Domains
|-- Entity Discovery
|-- Business Entities
|-- Business Keys
|-- Relationships
|-- Facts
|-- Dimensions
|-- Hierarchies
|-- Business Glossary
`-- Model Versions
```

### 12.8 Semantic Intelligence

```text
Semantic Intelligence
|-- Semantic Models
|-- Measures
|-- KPIs
|-- Relationships
|-- Synonyms
|-- Business Definitions
|-- Security
|-- Direct Lake
|-- Validation
`-- Certification
```

### 12.9 AI & Agents

```text
AI & Agents
|-- AI Readiness
|-- Approved Data for AI
|-- Business Instructions
|-- Verified Answers
|-- Ground Truth
|-- Fabric Data Agents
|-- Conversational Apps
|-- Agent Orchestration
|-- AI Validation Centre
|-- Technology Decisions
`-- AI Governance
```

### 12.10 Governance

```text
Governance
|-- Catalogue
|-- Ownership
|-- Classification
|-- Lineage
|-- Access Policy
|-- Certifications
|-- Audit
`-- Governance Decisions
```

### 12.11 Data Protection

```text
Data Protection
|-- Data Protection Profile
|-- Personal / Sensitive Data
|-- Privacy Requests
|-- Breach Register
|-- Sensitivity Labels
|-- DLP Policies
|-- Retention
|-- Minimisation
|-- Export Policy
`-- Exceptions
```

Privacy Requests (PDPA-01) and Breach Register (PDPA-02) were added by DEC-003,
approved 24 August 2026. Before that this group had no home for either
obligation, which `doc/execution/decisions/DEC-002-pdpa-applies.md` had traced
as applying. See `doc/execution/decisions/DEC-003-pdpa-navigation-homes.md`.

The other leaves in this group remain unbuilt and render disabled with a Soon
pill until a gate delivers them.

### 12.12 Data Sovereignty

```text
Data Sovereignty
|-- Approved Geographies
|-- Storage Geography
|-- Processing Geography
|-- AI Processing Geography
|-- Cross-Geo Controls
|-- Network Route
|-- Exceptions
`-- Evidence
```

### 12.13 Deployment

```text
Deployment
|-- DEV
|-- TEST
|-- PROD
|-- Deployment Pipeline
|-- Release Validation
|-- History
`-- Rollback Evidence
```

These are Fabric/application environments, not permanent Git branches.

### 12.14 Monitoring

```text
Monitoring
|-- Application Health
|-- Fabric Health
|-- Pipeline Health
|-- Capacity
|-- Data Quality
|-- Semantic Health
|-- AI Quality
|-- Security Alerts
|-- Audit Logs
`-- Usage & Adoption
```

### 12.15 System Configuration

```text
System Configuration
|-- General Settings
|-- Environment Settings
|-- Feature Flags
|-- Integrations
|-- API Registry
|-- Background Jobs
|-- Scheduler
|-- Context Registers
`-- Diagnostics
```

`Secret References` moved from here to Security (12.16) by DEC-001. It belongs
with the policy screens that govern what this application will allow, not with
the settings that describe how it is set up. There is no duplicate entry.

### 12.16 Security

```text
Security
|-- Security Overview
|-- Authentication Policy
|-- Session Policy
|-- API Security
`-- Secret References
```

Added by DEC-001, closing gap M1. This is the authoritative home for ADM-009
Authentication Policy, ADM-010 Session Policy, ADM-011 API Security and ADM-012
Secret References, none of which had a home in this document before.

Route family:

```text
/admin/security
/admin/security/authentication
/admin/security/sessions
/admin/security/api
/admin/security/secrets
```

**Implemented in Release 1 gate 3 (R1.3).** Every leaf now resolves to a real
screen and none renders as unbuilt.

`Security Overview` is the one leaf with no ADM feature behind it. It came from
DEC-001's navigation shape rather than from the Release 1 specification, and is
built as a READ-ONLY roll-up over ADM-009 to ADM-012 - decision D5, approved
25 August 2026. It invents no policy and no control; every number on it is read
from something one of those four features owns. Gap M9 stays open in case a
later requirement says what the screen should be.

`Secret References` is gated by `admin.secrets.view` while the other four are
gated by `admin.security.view`. Both sit at System Administrator today. They are
separate because they protect different things - a set of switches, and a map of
every credential this deployment depends on - and a later decision to delegate
policy reading must not hand the map over with it.

## 13. Role-Aware Navigation Examples

### CEO / Executive

```text
Home
My Intelligence
  Executive
  Sales
  Finance
  People
  Operations
  Customer
Ask SemantIQ
Explore
Decisions & Alerts
Reports & Insights
My Workspace
Help
```

### Sales Manager

```text
Home
My Intelligence
  Sales
Ask SemantIQ
Explore
Decisions & Alerts
Reports & Insights
My Workspace
Help
```

### Finance Manager

```text
Home
My Intelligence
  Finance
Ask SemantIQ
Explore
Decisions & Alerts
Reports & Insights
My Workspace
Help
```

### HR / People Manager

```text
Home
My Intelligence
  People
Ask SemantIQ
Explore
Decisions & Alerts
Reports & Insights
My Workspace
Help
```

### Platform Administrator

Business menus as entitled, plus:

```text
Administration
  Platform Overview
  Organisation & Users
  Security
  Fabric Environment
  Data Sources
  Data Engineering
  Data Quality
  Business Model
  Semantic Intelligence
  AI & Agents
  Governance
  Data Protection
  Data Sovereignty
  Deployment
  Monitoring
  System Configuration
```

## 14. Route Families

```text
/home
/intelligence/{domain}
/ask
/explore
/decisions
/reports
/workspace
/help
/admin/*
```

Domain routes are entitlement-driven. Administrator routes require explicit administrative policies and must not be exposed merely by hiding menu items.

## 15. Implementation Rule

Claude Code must implement navigation incrementally by approved phase. Phase 00 establishes the shell, route-policy framework, business Home placeholders, role-aware menu framework, Administration boundary and contextual Help framework. Later phases activate domain and control-plane functions only after their requirements, APIs, data rules and verification are approved.
