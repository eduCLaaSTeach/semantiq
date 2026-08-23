# Phase 00 - Engineering Foundation and Business Experience Shell

**Reference ID:** P00-FND
**Completion phrase:** `CONFIRM PHASE 00 COMPLETE`

> Execution gate: do not start Phase 01 until this phase has passed verification and the user has explicitly confirmed completion.

## 1. Objective

Establish the SemantIQ engineering foundation and the product shell that clearly separates:

1. the Business Decision Intelligence experience used by normal users; and
2. the privileged Platform Control Plane used to automate Microsoft Fabric and related services.

Phase 00 must make the product feel like a business intelligence application, not a Fabric administration portal.

## 2. Product Experience Principle

Business users see business outcomes such as Sales, Finance, People, Operations, Customer and Learning intelligence. They should not need to know that Lakehouses, pipelines, semantic models or Fabric Data Agents exist.

Administrators configure desired outcomes in SemantIQ. Later phases connect those screens to supported Microsoft APIs and guided configuration workflows.

## 3. Preconditions

- Inspect the actual repository before planning.
- Current approved application baseline: Laravel 13/PHP 8.5, React 19, MySQL/cPanel, modular monolith.
- Preserve the existing design system under `doc/design-system/`.
- Read `doc/MENU_STRUCTURE.md` and `doc/ROLE_MODEL.md` before UI or authorization design.
- Read the data protection/sovereignty standard before persistence, logging or integration design.

## 4. Phase 00 UI Shell

### 4.1 Business shell

Create the navigation framework for:

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

Do not implement later-phase business analytics logic in Phase 00. Use approved empty/onboarding states and phase-aware placeholders.

### 4.2 Administration boundary

Users with administrative rights may additionally see:

```text
Administration
```

with phase-aware placeholders for:

```text
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

Backend route/policy guards are mandatory. Hiding a menu item is not authorization.

### 4.3 Role-aware Home

Implement a Home shell capable of rendering role/domain-aware cards for:

- My KPIs
- What Changed
- Attention Required
- Risks
- Opportunities
- AI Insights
- Recommended Actions
- Recent Decisions
- Alerts

Phase 00 may use safe fixture/demo metadata only. It must not claim live business intelligence before later data phases are connected.

### 4.4 My Intelligence shell

Provide entitlement-driven navigation placeholders for:

- Executive Intelligence
- Sales Intelligence
- Finance Intelligence
- People Intelligence
- Operations Intelligence
- Customer Intelligence
- Learning Intelligence
- Custom Domains

A user sees only entitled domains.

### 4.5 Ask SemantIQ shell

Create the non-functional conversational shell with:

- question input;
- domain context indicator;
- suggested-question area;
- conversation-history placeholder;
- answer/visual/source layout contract;
- permission/error/empty/loading states.

Do not select or integrate an LLM in Phase 00.

## 5. Engineering Foundation Activities

1. Inspect and scaffold the missing Laravel/React application where necessary without replacing the approved stack.
2. Establish modular-monolith boundaries for Business Experience, Identity/Access, Control Plane, Integration, Governance, Audit, Help and shared platform services.
3. Implement organisation/tenant context even though current deployment is one customer per instance.
4. Implement role and domain-entitlement policy abstractions based on `doc/ROLE_MODEL.md`.
5. Implement the configuration data-model baseline for Organisation, User/Role references, DomainEntitlement, WorkflowRun, AuditEvent, HelpTopic and generic external resource references.
6. Implement secret-provider abstraction. No real Fabric automation credentials are stored in browser-accessible configuration.
7. Implement asynchronous workflow orchestration for operations longer than 10 seconds, with correlation IDs, retry/status and resumability.
8. Implement common status values: Not Started, In Progress, Action Required, Approval Required, Ready, Succeeded, Warning, Failed, Drift Detected and Revalidation Required.
9. Implement immutable/auditable event capture for configuration changes and external API operations.
10. Implement contextual Help framework so each later administrator configuration screen can provide exact step-by-step guidance.
11. Create integration adapter/capability-registry interfaces so API-driven, preview and guided-only Microsoft operations can be supported without redesigning the frontend.
12. Establish CI/test/static-analysis gates and a safe local development workflow.
13. Establish context registers for code, data, validation, configuration, sovereignty and security/privacy decisions.

## 6. Required Outputs

- Running SemantIQ application shell.
- Business-first primary navigation.
- Privileged Administration boundary.
- Role/domain entitlement framework.
- Home intelligence shell.
- My Intelligence domain shell.
- Ask SemantIQ shell without an LLM dependency.
- Organisation/tenant context foundation.
- Configuration persistence baseline.
- Secret-provider abstraction.
- Workflow orchestration and status framework.
- Audit framework.
- Contextual Help framework.
- Integration capability registry.
- CI/test baseline.
- Context registers and phase-gate workflow.

## 7. Phase 00 Screens

| ID | Screen | User | Purpose |
| --- | --- | --- | --- |
| P00-UI-001 | Business Home | All business roles | Personalised intelligence shell and onboarding/empty states |
| P00-UI-002 | My Intelligence | All entitled roles | Domain-entitlement-driven intelligence navigation |
| P00-UI-003 | Ask SemantIQ | Entitled roles | Conversational shell, no production AI integration yet |
| P00-UI-004 | Explore | Entitled roles | Governed exploration shell |
| P00-UI-005 | Decisions & Alerts | Entitled roles | Decision/alert shell |
| P00-UI-006 | Reports & Insights | Entitled roles | Report/insight shell |
| P00-UI-007 | My Workspace | All users | Personal saves/preferences shell |
| P00-UI-008 | Help | All users | Business-user help shell |
| P00-UI-009 | Administration Landing | Admin roles | Privileged control-plane entry |
| P00-UI-010 | Organisation Setup | Admin | Organisation/tenant baseline and policy profile |
| P00-UI-011 | Platform Help Centre | Admin | Guided setup/troubleshooting framework |
| P00-UI-012 | Audit Log | Admin/Auditor | Administrative activity evidence |

## 8. UX Requirements

- Follow `doc/design-system/ui-and-ux-layout-template-shared.md` exactly for enforced design rules.
- Business terminology takes precedence over technical platform terminology on business-user pages.
- Administrator screens may use Fabric terminology where technically necessary, but must explain it in plain language.
- Every data-driven screen must cover success, empty, loading, validation, permission-denied, error and small-screen states.
- Business-user navigation must remain simple even as administrator capabilities expand.

## 9. Authorization Requirements

- Implement backend authorization for every protected route/action.
- Effective authorization is role + organisation + domain entitlement + data scope + field/object policy.
- System Administrator does not automatically receive all sensitive business data.
- Use deny-by-default for unknown roles/domains/scopes.
- Add tests proving domain and administration boundaries.

## 10. Data Protection and Sovereignty Requirements

- Create versioned `DataProtectionProfile` and organisation-scoped policy records.
- Store approved storage and processing geographies, cross-geo policy, retention profile, Private Link/CMK/Purview requirements and policy approver.
- Implement reusable `VAL-SOV-GEO-001` server-side policy validation.
- Production payload logging is disabled by default.
- Credentials/tokens are redacted from logs.
- Business fixture/demo data must be synthetic and non-sensitive.

## 11. AI Architecture Preparation

Phase 00 does not select or implement an LLM.

Create replaceable contracts/interfaces for:

- Model Provider
- Agent Runtime
- Retrieval/Knowledge Provider
- Tool/MCP Provider
- Conversation Store
- Evaluation/Observability
- Channel/UI Adapter

Deterministic Fabric provisioning/configuration must remain outside the LLM execution path.

## 12. Verification Checklist

- [ ] Repository stack and scaffold verified.
- [ ] Business shell and Administration boundary render according to the design system.
- [ ] Sales-only user cannot see Finance/People domains.
- [ ] Business user cannot reach administrator APIs by direct URL/API call.
- [ ] System Administrator without domain entitlement does not automatically receive sensitive business data.
- [ ] Ask SemantIQ shell works as a UI contract without introducing an unapproved LLM/runtime.
- [ ] Sample long-running workflow records correlation/audit information and can resume.
- [ ] No real secret exists in source, logs or database.
- [ ] Contextual Help opens from a sample administrator screen.
- [ ] Context registers are established and referenced by the phase plan.
- [ ] CI/tests/static analysis pass where available.

## 13. User Completion Gate

Claude Code must create `doc/execution/PHASE-00-PLAN.md`, obtain user approval, implement only approved Phase 00 scope, create `doc/execution/PHASE-00-VERIFICATION.md`, present evidence and stop.

Only after the user sends exactly `CONFIRM PHASE 00 COMPLETE` may Phase 01 be unlocked.
