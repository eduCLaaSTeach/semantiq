# Phase 00 UI Shell Specification

Version: 1.0
Phase: 00 - Engineering Foundation and Business Experience Shell

## 1. Goal

Create a state-of-the-art SemantIQ application shell that immediately communicates business intelligence value while keeping Microsoft Fabric infrastructure behind a privileged administration boundary.

The shell must support future Sales, Finance, People, Operations, Customer, Learning and Executive experiences without hard-coding one domain into the layout.

## 2. Shell Layers

### Business shell - default

```text
Global Header
  SemantIQ brand
  Organisation context
  Global Ask entry
  Notifications
  Help
  User/Profile

Primary Navigation
  Home
  My Intelligence
  Ask SemantIQ
  Explore
  Decisions & Alerts
  Reports & Insights
  My Workspace
  Help

Main Content Region
  Page header/context
  Filters/time context where relevant
  KPI/insight content
  Drill-down/side panel areas
```

### Administration shell - privileged

Administration is an additional gated navigation entry, not the default landing experience.

```text
Administration
  Platform Overview
  Organisation & Users
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

## 3. Home Page Contract

The business Home page must be capable of rendering:

```text
Greeting + role/domain context

My KPIs
  KPI cards with value, period and change

What Changed
  ranked material changes

Attention Required
  risks/issues requiring review

Opportunities
  positive signals / growth opportunities

AI Insight
  concise explanation from governed data

Recommended Actions
  advisory next actions

Recent Decisions / Alerts
```

Phase 00 uses safe placeholder/empty states. It must not fake production insights.

## 4. My Intelligence Contract

The page shall render domain cards/navigation from entitlements, not a fixed all-domain menu.

Domain card contract:
- domain name;
- short business description;
- access scope summary;
- current setup state;
- open action;
- optional alert count.

Example domains:
- Executive
- Sales
- Finance
- People
- Operations
- Customer
- Learning

## 5. Ask SemantIQ Contract

Create UI structure only in Phase 00:

- conversation heading/context;
- question composer;
- selected/automatic business domain indicator;
- suggested questions;
- answer container supporting narrative + metrics + visual + source context;
- follow-up action area;
- history panel placeholder;
- empty/loading/error/unauthorised states.

No model/provider is selected in Phase 00.

## 6. Explore Contract

Create a shell capable of future configuration for:
- metric selector;
- dimension selector;
- period selector;
- comparison selector;
- filter controls;
- result visual area;
- AI interpretation area;
- save view action.

## 7. Decisions & Alerts Contract

Create shell states for:
- Attention Required
- Risks
- Opportunities
- Anomalies
- Recommendations
- My Alerts
- Assigned Decisions
- Decision History

Decision cards support future actions such as investigate, assign, acknowledge, comment, resolve and create alert according to policy.

## 8. Administration Landing Contract

The administration landing page should guide setup as a journey rather than expose a technical menu only.

Example progression:

```text
1. Connect Organisation & Microsoft Tenant
2. Configure Identity & Permissions
3. Validate Fabric Environment
4. Configure Workspaces & Geography
5. Connect Data Sources
6. Build Data Foundation
7. Configure Business Model
8. Configure Semantic Intelligence
9. Prepare AI & Data Agents
10. Validate & Go Live
```

Each step shows status, owner, prerequisites, automated/manual classification, help and verification.

## 9. Required States

Every shell page/component must account for:
- loading;
- empty/not configured;
- success;
- warning;
- action required;
- permission denied;
- validation error;
- external-service error;
- offline/timeout where relevant;
- small-screen/responsive behavior.

## 10. Security and Privacy Requirements

- Backend authorization is mandatory for routes and APIs.
- Domain navigation is entitlement-driven.
- System administrators do not automatically receive sensitive business data.
- No secret or privileged Microsoft token is exposed to React/browser state.
- Sensitive content must not be placed in client logs/analytics by default.
- Ask SemantIQ shell must be designed so future conversation history can follow retention and sovereignty policy.

## 11. Design Authority

This specification defines functional shell behavior only.

`doc/design-system/ui-and-ux-layout-template-shared.md` remains authoritative for:
- tokens;
- typography;
- colours;
- spacing;
- shell dimensions;
- navigation visual pattern;
- responsive behavior;
- component archetypes;
- brand assets.

If this file and the design-system template conflict visually, the design-system template wins. A functional conflict must be raised for approval.

## 12. Phase 00 Acceptance

- Business user lands in a business-facing shell, not Fabric setup.
- Role/domain navigation changes based on entitlement fixtures.
- Administrator entry is policy-gated.
- Direct navigation to an admin route is denied for business-only users.
- All major shell states are represented.
- The UI follows the existing design-system authority.
- No unapproved AI/model dependency is introduced.
