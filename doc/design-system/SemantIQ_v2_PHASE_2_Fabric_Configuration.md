# SemantIQ v2 — Phase 2: Fabric Configuration & Intelligence Factory

**Document purpose:** Execution authority for Phase 2.

**Prerequisite:** Phase 1 must be fully accepted. Phase 2 must consume the identity, role, domain, scope and sensitivity model produced by Phase 1. It must not invent a separate access model.

**Delivery principle:** Build **one menu at a time**. Plan → Design → Implement → Test → Verify → Accept → unlock next menu.

---

## 1. Fixed Platform Constraints

- Laravel 13 / PHP 8.5
- React 19
- MySQL on cPanel
- GitHub Actions → cPanel over SSH/rsync
- modular monolith
- single-tenant today with multi-tenant-ready boundaries
- Microsoft Fabric is the target data-intelligence platform
- approved CLaaS2SaaS UI/UX design standard

Infrastructure already exists and is not redesigned in this phase.

---

## 2. Greenfield Rule

Do not reuse SemantIQ v1 connector logic, Fabric logic, database schema, API contracts or security implementation.

Phase 2 is implemented fresh against the accepted Phase 1 access model.

---

## 3. Phase 2 Business Outcome

Phase 2 converts enterprise source data into governed, secure, quality-controlled, semantically modelled and AI-ready data products.

End-to-end target:

```text
Connect
  ↓
Discover
  ↓
Classify
  ↓
Ingest
  ↓
Clean & Standardise
  ↓
Model
  ↓
Secure
  ↓
Semantic Layer
  ↓
AI Readiness
  ↓
Publish
  ↓
Monitor
```

The administrator should answer business questions such as:
- What system is this?
- Which domain owns the data?
- Who is the data owner?
- Is the data sensitive/personal?
- How fresh must it be?
- Who should normally see it?

SemantIQ should derive technical Fabric and semantic security wherever safe and supported.

---

# 4. Mandatory Incremental Delivery Rule

Only one P2 menu unit may be active at any time.

For each unit:
1. PLAN and stop.
2. DESIGN and stop.
3. IMPLEMENT only that menu.
4. TEST functional + integration + security + failure cases.
5. VERIFY with real evidence.
6. Obtain acceptance.
7. Unlock next unit.

Do not:
- build every Fabric menu at once;
- create all connectors at once;
- prebuild downstream semantic/AI features before upstream data is accepted;
- duplicate Phase 1 authorization independently inside Power BI or AI;
- call a unit complete because an API call succeeds once.

Use one representative source and one representative domain until the full path is proven.

---

# 5. Phase 2 Delivery Units

## P2-01 — Data Sources

### Menu
`Fabric Configuration → Data Sources`

### Purpose
Catalogue source systems and their ownership/governance context.

### Fields/behaviour
- source name/type;
- owner;
- business domain;
- status;
- refresh expectation;
- sensitivity;
- connection health.

### Rules
- source record does not automatically ingest data;
- credentials are never displayed;
- source cannot be published without required ownership/domain data.

### Tests
- create/edit source definition;
- invalid domain/owner;
- unauthorized admin access;
- secret redaction;
- audit changes.

### Exit
Source catalogue exists without performing ingestion.

---

## P2-02 — Connect Source

### Menu
`Fabric Configuration → Connect Source`

### Purpose
Guided connection wizard with minimum technical choices.

### First-release rule
Implement **one approved representative connector first**. Do not implement a connector catalogue in parallel.

### Flow
- select system type;
- provide/authorize connection using approved secret mechanism;
- validate connectivity;
- assign owner/domain;
- record connection health;
- never expose credentials to browser/log.

### Tests
- success;
- invalid credentials;
- timeout;
- unavailable source;
- permission denial;
- secret leakage check;
- retry behaviour only where safe.

### Exit
One source connects safely and provides metadata access.

---

## P2-03 — Discovery

### Menu
`Fabric Configuration → Discovery`

### Purpose
Inspect source structure before ingestion.

### Discover
- entities/tables;
- fields;
- types;
- relationships;
- candidate keys;
- volume/profile metadata as appropriate.

### Rules
- metadata discovery is not production publication;
- sensitive sample payloads should be minimised;
- discovery output must be human-reviewable.

### Tests
- expected metadata;
- unsupported type;
- source schema changes;
- no credential leakage;
- access boundary.

### Exit
Source structure is understood and ready for classification.

---

## P2-04 — Data Classification

### Menu
`Fabric Configuration → Data Classification`

### Purpose
Convert discovered data into governed business classifications.

### Capture
- business domain;
- data owner;
- sensitivity;
- personal/sensitive-data indicators;
- intended audience/access;
- AI eligibility.

### Rules
- classification precedes production publication;
- unknown sensitivity defaults conservatively;
- AI eligibility is not automatic.

### Tests
- missing classification blocks downstream approval;
- restricted classification propagates;
- domain mismatch refusal;
- audit classification changes.

### Exit
Data has business ownership and security meaning.

---

## P2-05 — Ingestion

### Menu
`Fabric Configuration → Ingestion`

### Purpose
Move approved data into the governed Fabric data foundation.

### Capabilities
- ingestion plan;
- landing target;
- full vs incremental pattern;
- refresh;
- job status.

### Rules
- use approved Fabric APIs/patterns;
- no unapproved geography/resource target;
- ingestion failure must not silently publish incomplete data;
- credentials remain protected.

### Tests
- first full ingestion;
- incremental behaviour if in scope;
- failure/retry;
- partial failure;
- permissions;
- duplicate/replay handling;
- source-to-target row/control counts where appropriate.

### Exit
Representative data is landed reliably.

---

## P2-06 — Data Quality

### Menu
`Fabric Configuration → Data Quality`

### Purpose
Make data-quality conditions measurable and actionable.

### Capabilities
- quality rules;
- failed checks;
- trends;
- accountable owner;
- remediation status.

### Rules
- poor quality must be visible;
- critical quality failure may block semantic/AI publication;
- quality status is based on actual checks.

### Tests
- passing rule;
- failing rule;
- threshold behaviour;
- owner/remediation lifecycle;
- publication block where required.

### Exit
Data is quality-evaluated and actionable.

---

## P2-07 — Business Model

### Menu
`Fabric Configuration → Business Model`

### Purpose
Translate technical data into business concepts.

### Components
- business entities;
- measures;
- dimensions;
- relationships;
- glossary;
- business definitions.

### Rules
Business users should not need raw SQL/schema knowledge.

### Tests
- definitions;
- relationship validation;
- duplicate/ambiguous measure handling;
- domain ownership;
- change impact.

### Exit
A governed business model exists for the representative domain.

---

## P2-08 — Security Mapping

### Menu
`Fabric Configuration → Security Mapping`

### Purpose
Translate the accepted Phase 1 business access model into Fabric/semantic access controls.

### Security context
```text
Identity
+ Domain
+ Scope
+ Sensitivity
+ organisational relationship
→ data/semantic enforcement
```

### Required scenarios
- salesperson = Sales + Own;
- manager = Sales + Team;
- Finance = Finance + assigned entity/business-unit scope;
- Executive = approved domains + Organisation;
- System Admin = platform administration without automatic business rows.

### Rules
- one policy model, multiple enforcement points;
- do not manually recreate unrelated security in each report/model;
- access simulator should show expected result before publication.

### Tests
- cross-user negative tests;
- cross-domain negative tests;
- manager team boundaries;
- restricted-field controls;
- System Admin no-business-data default.

### Exit
Security is proven at data/semantic layers before publication.

---

## P2-09 — Semantic Model

### Menu
`Fabric Configuration → Semantic Model`

### Purpose
Publish trusted business semantics.

### Components
- certified measures;
- dimensions;
- descriptions;
- definitions;
- lineage;
- publication state.

### Rules
- use governed business definitions;
- raw schema is not the business interface;
- security mapping remains attached to the semantic consumption path.

### Tests
- measure correctness;
- lineage;
- authorization through semantic query;
- unpublished/draft protection;
- definition consistency.

### Exit
Certified semantic model is ready for controlled consumption.

---

## P2-10 — AI Readiness

### Menu
`Fabric Configuration → AI Readiness`

### Purpose
Prove the data product is safe and useful for conversational AI.

### Components
- trusted questions;
- expected grounded answers;
- coverage;
- quality;
- security tests;
- go/no-go approval.

### Rules
- AI uses governed semantic/retrieval paths;
- ungoverned data is blocked;
- restricted user data must not be inferred from hidden context;
- factual metrics and AI interpretation must be distinguishable later in Workplace.

### Tests
- trusted question set;
- unauthorized data retrieval;
- adversarial access prompts;
- stale/unavailable data handling;
- groundedness/correctness threshold.

### Exit
Representative data product is explicitly approved for AI consumption.

---

## P2-11 — Pipelines & Refresh

### Menu
`Fabric Configuration → Pipelines & Refresh`

### Purpose
Operate ingestion and transformation reliably.

### Capabilities
- schedules;
- SLA;
- refresh status;
- failures;
- retries;
- incident actions.

### Rules
- job state reflects real execution;
- bounded retry;
- no secret exposure;
- stale-data state is explicit.

### Tests
- schedule;
- failed run;
- retry;
- repeated failure;
- stale-data indicator;
- unauthorized operational action.

### Exit
Data product can be operated without manual database manipulation.

---

## P2-12 — Power BI Publication

### Menu
`Fabric Configuration → Power BI Publication`

### Purpose
Publish approved semantic models/reports while inheriting governed security.

### Rules
- no separate security burden for normal report creators;
- recipient access is validated;
- generated/published output may not broaden access;
- high-risk publication changes require impact preview.

### Tests
- authorised publication;
- recipient mismatch;
- Sales Own vs Manager Team behaviour;
- restricted field protection;
- publication rollback/failure.

### Exit
Governed BI output is ready for Workplace.

---

## P2-13 — Monitoring

### Menu
`Fabric Configuration → Monitoring`

### Purpose
Observe data-product health after publication.

### Monitor
- data health;
- lineage;
- SLA;
- source/integration health;
- publication health;
- semantic/AI availability.

### Tests
- real health signals;
- failure state;
- stale state;
- permission boundary;
- no secrets/business payload leakage in diagnostics.

### Exit
Operations can detect and triage the data journey.

---

## P2-14 — Overview

### Menu
`Fabric Configuration → Overview`

### Why implemented last
Overview is a roll-up of all accepted Phase 2 capabilities.

### Show
- readiness;
- blockers;
- recent activity;
- health;
- outstanding classification/quality/security issues;
- publication status.

### Tests
- summary values map to real underlying sources;
- access-aware display;
- empty/not-configured states;
- no fabricated green status.

### Exit
One accurate view of the complete data-to-intelligence factory.

---

# 6. Phase 2 Acceptance

Phase 2 is accepted only after P2-01 through P2-14 are individually accepted and one representative domain is proven end to end.

Required proof:
- source → discovery → classification → ingestion → quality → model → security → semantic → AI readiness → publication → monitoring;
- credentials remain protected;
- lineage and ownership visible;
- Sales/Finance/People isolation proven;
- salesperson and manager receive different record scopes from the same governed model;
- Power BI and AI preserve the same access model;
- ungoverned/unsecured data is blocked from AI-ready publication;
- failures can be detected and recovered without direct production database edits.

---

# 7. Phase 2 Handoff to Phase 3

Phase 3 receives only accepted outputs:

- certified semantic data products;
- approved measures/dimensions/business definitions;
- effective security context;
- validated AI query/retrieval interface;
- Power BI publication contract;
- operational health/freshness status.

Phase 3 must not create a second data/security route around Phase 2.

---

# 8. Claude Execution Contract

Claude must:
- work on only one approved P2 unit at a time;
- produce PLAN, wait;
- produce DESIGN, wait;
- implement, test and verify;
- request acceptance;
- not begin the next unit before acceptance;
- use one representative connector/domain first;
- preserve Phase 1 security as the single source of access policy;
- not copy v1 Fabric implementation;
- not prebuild AI/Power BI functions before upstream menus are accepted.

Recommended files:

```text
doc/v2/phase-2/
  P2-01-DATA-SOURCES-PLAN.md
  P2-01-DATA-SOURCES-DESIGN.md
  P2-01-DATA-SOURCES-VERIFICATION.md
  ...
```
