# SemantIQ v2 — Phase 3: SemantIQ Workplace & Decision Intelligence

**Document purpose:** Execution authority for Phase 3.

**Prerequisites:**
- Phase 1 accepted: identity and access model is production-ready.
- Phase 2 accepted: governed semantic/AI-ready data products are available.

**Delivery principle:** Build **one Workplace menu at a time**. For `My Intelligence`, build **one business domain at a time**.

---

## 1. Fixed Platform Constraints

- Laravel 13 / PHP 8.5
- React 19
- MySQL on cPanel
- GitHub Actions → cPanel over SSH/rsync
- modular monolith
- single-tenant today with multi-tenant-ready boundaries
- shared CLaaS2SaaS UI/UX design standard
- Microsoft SSO/application session already delivered by Phase 1
- governed data/security outputs delivered by Phase 2

---

## 2. Greenfield Rule

Do not reuse SemantIQ v1 Workplace, dashboards, AI, reports or navigation implementation.

Only the shared CLaaS2SaaS screen design is reused.

---

## 3. Phase 3 Business Outcome

Workplace is the business-facing SemantIQ experience.

After sign-in:
1. identity is already verified;
2. SemantIQ resolves Role + Domain + Scope + Sensitivity;
3. only authorised menus/data/actions appear;
4. all queries use governed Phase 2 semantics;
5. AI receives only authorised retrieval context;
6. saving/sharing/reporting never broadens access.

Users should gain insight without needing SQL, DAX, Fabric or security configuration knowledge.

---

# 4. Mandatory Incremental Delivery Rule

Only one P3 menu unit may be active.

For each unit:
1. PLAN;
2. DESIGN;
3. stop for approval;
4. implement only that unit;
5. test;
6. verify in browser with representative personas;
7. verify cross-user/cross-domain refusal;
8. obtain acceptance;
9. unlock next unit.

### Special rule for My Intelligence
Do not build Sales, Finance, People, Operations, Customer, Learning and Executive domains in parallel.

First:
- build the My Intelligence framework;
- choose the first approved domain coming from Phase 2;
- build that domain;
- test and accept it;
- add the next domain only when approved.

---

# 5. Phase 3 Delivery Units

## P3-01 — Home

### Menu
`Workplace → Home`

### Purpose
Personal role-aware summary.

### Content
- KPIs;
- what changed;
- attention required;
- risks;
- opportunities;
- AI insights;
- quick questions;
- recent decisions/alerts where approved.

### Rules
- Home is generated from current effective access;
- no card may expose a hidden domain;
- no raw restricted fields in executive roll-ups unless explicitly entitled.

### Persona tests
- salesperson: own performance only;
- manager: team summary;
- Finance Manager: finance scope;
- Executive: approved cross-domain summary;
- System Admin with no business domain: no business intelligence by default.

### Exit
Personalized Home is correct and leak-free.

---

## P3-02 — My Intelligence Framework

### Menu
`Workplace → My Intelligence`

### Purpose
Dynamically show only authorised business domains.

### Baseline domains
- Executive Intelligence
- Sales Intelligence
- Finance Intelligence
- People Intelligence
- Operations Intelligence
- Customer Intelligence
- Learning Intelligence
- Custom Intelligence

### Rules
- only entitled domains appear;
- domain visibility comes from backend policy, not just frontend menu filtering;
- Fabric implementation terms remain hidden from business users.

### Tests
- user with only Sales sees only Sales;
- Finance domain hidden and inaccessible to Sales user;
- direct URL/API to hidden domain fails;
- multi-domain Executive sees only approved domains.

### Exit
Domain router/framework is accepted before any domain-specific implementation.

---

## P3-02A onward — My Intelligence Domain Packs

Build **one domain pack at a time**.

The first domain must be the representative domain already proven through Phase 2.

Each domain pack receives its own PLAN/DESIGN/VERIFICATION and separate acceptance.

### Sales Intelligence
Recommended navigation:
- Overview
- Revenue
- Pipeline
- Opportunities
- Customers
- Products
- Sales Team
- Forecast
- Trends
- Risks & Opportunities
- Ask Sales AI

### Finance Intelligence
- Financial Overview
- Revenue
- Expenses
- Profitability
- Cash Flow
- Budget vs Actual
- Receivables
- Payables
- Business Units
- Variance Analysis
- Forecast
- Risks
- Ask Finance AI

### People Intelligence
- Workforce Overview
- Headcount
- Attrition
- Recruitment
- Performance
- Skills
- Workforce Cost
- Attendance
- Learning & Development
- Workforce Planning
- Risks
- Ask People AI

### Executive Intelligence
- Enterprise Overview
- Strategic KPIs
- Financial Performance
- Sales Performance
- Workforce
- Operations
- Customer
- Cross-Functional Risks
- Opportunities
- Forecast
- Ask Executive AI

### Operations Intelligence
- Operations Overview
- Service Levels
- Throughput
- Productivity
- Exceptions
- Capacity
- Cost
- Trends
- Forecast
- Risks
- Ask Operations AI

### Customer Intelligence
- Customer Overview
- Segments
- Revenue
- Retention
- Engagement
- Satisfaction
- At-Risk Customers
- Growth Opportunities
- Trends
- Ask Customer AI

### Learning Intelligence
- Learning Overview
- Enrolment
- Attendance
- Engagement
- Progress
- Completion
- Assessment
- At-Risk Learners
- Skills
- Intervention Opportunities
- Ask Learning AI

### Domain-pack security acceptance
Every domain pack must prove:
- correct domain access;
- Own/Team/Business Unit/Domain/Organisation scope where applicable;
- restricted fields remain protected;
- manager hierarchy behaves correctly;
- AI subexperience does not broaden access.

---

## P3-03 — Explore

### Menu
`Workplace → Explore`

### Purpose
Self-service analysis over governed semantics without SQL/DAX.

### Capabilities
- metrics;
- dimensions;
- trends;
- comparisons;
- drill-down;
- saved analysis.

### Rules
- only approved measures/dimensions;
- no raw technical schema exposure;
- all query results preserve effective scope.

### Tests
- Sales Own;
- Sales Team;
- Finance scope;
- unavailable dimension;
- restricted-field refusal;
- saved analysis rechecks permission when reopened.

### Exit
Users can safely explore governed data.

---

## P3-04 — Ask SemantIQ

### Menu
`Workplace → Ask SemantIQ`

### Purpose
Identity-bound conversational analysis over governed data.

### Required rules
- identity-bound;
- authorised retrieval only;
- governed semantics first;
- no hidden-data inference;
- fact vs interpretation distinction;
- traceability where practical;
- deterministic actions remain in validated application workflows;
- saved/shared conversation remains permission-aware.

### Required security tests
- salesperson asks for another salesperson's performance;
- Sales user asks Finance-specific confidential question;
- prompt-injection attempt to ignore access rules;
- user asks model to reveal hidden context;
- manager team query;
- Executive multi-domain query;
- stale/unavailable data;
- conversation-history access from another user.

### Quality tests
- trusted question set;
- groundedness;
- correctness;
- refusal quality;
- source/metric traceability;
- hallucination threshold.

### Exit
Conversational intelligence is useful without widening access.

---

## P3-05 — Insights

### Menu
`Workplace → Insights`

### Purpose
Explain material change and drivers.

### Capabilities
- What changed
- Why it changed
- saved insights
- authorised sharing

### Rules
- based on governed measures;
- explain comparison period;
- preserve recipient permissions;
- distinguish detected fact from generated explanation.

### Exit
Insight records are traceable and access-aware.

---

## P3-06 — Risks & Opportunities

### Menu
`Workplace → Risks & Opportunities`

### Purpose
Surface conditions that may require attention.

### Rules
- risk/opportunity includes supporting evidence/context;
- user only sees items derived from authorised data;
- confidence/limitations are visible where appropriate.

### Tests
- cross-domain leakage;
- scope filtering;
- stale-data behaviour;
- authorized drill-down.

### Exit
Risk/opportunity detection is governed and explainable.

---

## P3-07 — Recommendations

### Menu
`Workplace → Recommendations`

### Purpose
Suggest data-backed next actions.

### Rules
- recommendation is explicitly separate from fact;
- rationale and expected impact shown;
- owner/status may be tracked;
- AI may recommend but deterministic changes require validated workflows and authorization.

### Exit
Recommendations cannot masquerade as factual data or bypass approval controls.

---

## P3-08 — Decisions & Alerts

### Menu
`Workplace → Decisions & Alerts`

### Capabilities
- assigned decisions;
- alerts;
- attention queue;
- acknowledgements;
- decision history.

### Decision record
- decision;
- owner;
- rationale;
- supporting evidence;
- follow-up date;
- outcome.

### Rules
- recipient/scope aware;
- alert rules operate only over authorised data;
- decision sharing cannot broaden access.

### Exit
Accountable decision workflow is available.

---

## P3-09 — Reports & Dashboards

### Menu
`Workplace → Reports & Dashboards`

### Purpose
Generate/surface governed analysis and Power BI outputs.

### Capabilities
- saved reports;
- generated analysis;
- Power BI dashboards;
- prepare report/dashboard specification;
- controlled publication.

### Rules
- Power BI is an output channel, not a separate security administration system;
- publication uses accepted semantic models;
- recipient validation before sharing;
- report cannot bypass row/domain/sensitivity restrictions.

### Tests
- report from Sales Own;
- manager team report;
- unauthorized recipient;
- restricted field;
- publication failure;
- re-open report after entitlement change.

### Exit
Governed reporting is safe and useful.

---

## P3-10 — My Workspace

### Menu
`Workplace → My Workspace`

### Purpose
Personal working area.

### Content
- saved views;
- saved questions;
- drafts;
- alerts;
- recent activity;
- personal working items.

### Rules
- saved artefacts remain protected business data;
- access re-evaluated when underlying permissions change;
- no copied hidden data persists after access revocation.

### Exit
Personal productivity features preserve current authorization.

---

## P3-11 — Help

### Menu
`Workplace → Help`

### Purpose
Business guidance and guided resolution.

### Content
- feature help;
- user guidance;
- context-sensitive help;
- guided external Microsoft portal steps only where automation is genuinely unavailable.

### Rules
- do not expose secrets/configuration values;
- instructions must state required role and impact;
- external manual action must include a SemantIQ validation step afterward.

### Exit
Users can resolve common issues without technical guesswork.

---

# 6. Cross-Menu Persona Verification

Before Phase 3 acceptance, run the same representative identities across all accepted menus.

Minimum personas:
- Salesperson — Sales + Own
- Sales Manager — Sales + Team
- Finance Manager — Finance + assigned scope
- HR/People Manager — People + approved scope
- Executive — approved cross-domain + Organisation
- System Administrator — platform role with no business-domain access by default

Verify:
- navigation;
- direct URL/API denial;
- query results;
- AI;
- saved insights;
- sharing;
- reports;
- Power BI output.

---

# 7. Phase 3 Acceptance

Phase 3 is accepted only when every enabled Workplace menu and every enabled domain pack has its own acceptance evidence.

Required final proof:
- users receive only authorised domains and record scope;
- no cross-user/cross-domain leakage through UI, APIs, semantic queries, AI, saved content, sharing or reports;
- AI meets agreed groundedness/correctness thresholds;
- business users obtain insights without Fabric/SQL/DAX knowledge;
- managers and executives operate over the same governed security model;
- recommendations are clearly separated from facts;
- critical/high security/privacy issues are closed;
- business UAT demonstrates measurable value such as improved time-to-insight or decision workflow.

---

# 8. Claude Execution Contract

Claude must:
- build one P3 menu at a time;
- for My Intelligence, build one domain pack at a time;
- create unit PLAN and stop;
- create unit DESIGN and stop;
- implement only after approval;
- run persona/security tests;
- produce VERIFICATION evidence;
- wait for acceptance before next unit;
- never bypass Phase 2 with raw/unsecured data access;
- never allow AI to receive unrestricted data and filter afterward;
- never copy v1 implementation.

Recommended files:

```text
doc/v2/phase-3/
  P3-01-HOME-PLAN.md
  P3-01-HOME-DESIGN.md
  P3-01-HOME-VERIFICATION.md

  P3-02-MY-INTELLIGENCE-FRAMEWORK-PLAN.md
  P3-02A-SALES-INTELLIGENCE-PLAN.md
  ...
```
