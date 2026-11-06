# Copy/Paste Upgrade Prompt - V1.3 To V1.4

This is the earlier upgrade prompt to V1.4. To reach V1.4.1, use `V1_3_TO_V1_4_1_UPGRADE_PROMPT.md` instead. In a gateway project, the kit files arrive with a newer copy of the gateway's `.claude/`, `doc/`, and `scripts/` folders, and the prompt checks that copy against the project's history.

Upgrade this repository from Claude Solution Development Kit V1.3 to V1.4 without replacing or weakening the repository's current root `CLAUDE.md` or organisation/project `.claude` standards.

First read the current root `CLAUDE.md` using its stated startup and progressive-loading discipline. Then inspect the existing V1.3 kit, `/doc/solutiondocument/`, `/doc/phasedocument/`, `/doc/tasktracking/`, and only the `.claude` files required by the applicable gates. Do not preload the whole repository.

Apply these V1.4 requirements:

1. Preserve the source-of-truth model: `/doc/solutiondocument/` defines what to build; the current root `CLAUDE.md` plus the live `.claude/` rules define how to build it. Surface conflicts; never silently resolve them.
2. Limit the project to at most five macro phases:
   - Phase 1: Platform, Infrastructure & Master Foundation.
   - Phase 2: Core Application, Login, Administration & AI Foundation.
   - Phase 3: Feature / Function Design & Dependency Approval.
   - Phase 4: Feature / Function Build & Technical Verification.
   - Phase 5: UAT, Acceptance & Release Readiness.
   Keep detailed work as tasks and subtasks, not extra phases.
3. Every feature or function task must explicitly state its Frontend, Backend, Database, Integration/API, AI, Security, Tests/Verification, Acceptance/UAT, Indexing and Idempotency responsibilities. Use `Not Applicable - Verified` with a reason rather than omitting a workstream.
4. Create and maintain a complete feature/function index and requirement coverage matrix. Every requirement, workflow step, menu, feature, function, report, analytic, integration, AI capability, data need, security rule and acceptance condition must have a traceable disposition. Nothing may disappear silently.
5. Allowed dispositions are: `Covered`; `Duplicate`; `Conflict`; `Gap`; `Out of Scope - Explicit`; `Deferred - Approved`; `Not Applicable - Verified`. Any unresolved material `Conflict` or `Gap` blocks affected planning and build work until a human resolves it.
6. Phase 1 must run an infrastructure readiness check and a master database and schema reuse baseline before dependent work, against the project's schema source per `.claude/rules/database-schema.md`. Do not invent schema and do not execute DDL.
7. Phase 2 must verify the initial application start, sign-in with its session and logout per the recorded authentication model, the application and system administration foundation, the application's own per-feature authorization and the audit foundation, and the AI model catalog with its AI guardrails where AI is required, before dependent business features.
8. Phase 3 must perform per-feature dependency analysis plus Plan/Design/Evaluate, the logical data proposal, a live existing-table reuse search, indexing design, idempotency design where duplicate execution or retry can cause harm, and the approved physical table/field proposal where required.
9. Phase 4 must author the approved migration, self-check the schema source against the approved proposal, verify required indexes and constraints where available, obtain explicit human coding authorisation, then implement database verified -> backend -> backend tests -> frontend -> integration/AI -> end-to-end validation for that Task ID only, followed by the code and security review passes.
10. Phase 5 must run UAT, rework and regression, security and permission checks, data, index and performance checks where applicable, idempotency and retry checks where applicable, AI guardrail validation where applicable, explicit user acceptance, and completion and time logging.
11. Add `/doc/corerepo/` as vendor-neutral persistent project knowledge containing: project index, current state, infrastructure readiness, feature/function index, requirement coverage, code knowledge base, database model, entity model, integration register, AI component register, task-status snapshot, decision/conflict register, accepted change log, handover, compact AI context, and a small machine-readable `PROJECT_MANIFEST.yaml`.
12. Add an `infra-readiness-agent` and a `core-repo-maintainer`. Keep the agent set lean. Do not create unnecessary specialist calls for every task.
13. Update `/doc/corerepo/` incrementally, only on meaningful state changes. Never rewrite the entire knowledge base after routine code edits. Update physical database facts only from live verified schema evidence.
14. Make `/doc/corerepo/14_AI_CONTEXT.md` and `PROJECT_MANIFEST.yaml` the compact orientation sources for another developer or AI-assisted development tool. Keep them small and vendor-neutral.
15. Keep AI credit and token usage low through progressive loading: read the root gateway, the compact current-state context, the current Task ID, the relevant requirement, the applicable rule gates, and only the affected code files. Do not reread the whole repository, the whole `.claude` tree, or all solution documents on each task unless a full re-baseline is required.
16. Preserve the V1.3 commands `/generate-phase-plan`, `/generate-project-tasks`, and `/execute-approved-task <TASK-ID>`, but update their behaviour to enforce the V1.4 controls.
17. Preserve all existing V1.3 task history, timestamps, acceptance records, requirement links and approved decisions. Never reset or fabricate history.
18. Before declaring the upgrade complete, self-check: no source feature was lost, all unresolved conflicts and gaps are visible, the maximum phase count is five, current task tracking is preserved, the core-repo files exist, current standards were not overwritten, and the V1.4 README and migration notes accurately describe the resulting repository.

Do not begin application feature coding as part of this upgrade. This prompt upgrades the development governance and tooling only. Stop and report any conflict with the repository's current standards rather than bypassing it.
