# How To Use This Claude Gateway

The gateway is the root `CLAUDE.md` plus three folders: `.claude/`, `doc/`, and `scripts/`. You do not copy it in. The infra team provides each project repository already set up, with the four environment branches `DEV`, `QA`, `STAG`, and `PROD` and the gateway at the root. Your job is to update it for your project, which `/fresh-project-start` does with you. `CLAUDE.md` is the loader Claude reads at startup. `.claude/` holds the rules, commands, agents, and skills. `doc/` holds the requirement intake, planning, task tracking, and core repo folders. `scripts/` holds the task event logger.

The gateway is intentionally blank and dynamic for application stack, hosting, source control, CI/CD, runtime, package manager, deployment method, architecture, validation command, and security model. For database schema work, the final source of truth is the project's own schema, read from the source recorded in `.claude/PROJECT-CONTEXT.md`.

## First Command For A New Project

Clone the repository the infra team provided, open Claude in it, and run:

```text
/fresh-project-start
```

There is no prompt to write. The command reads the gateway, checks `doc/solutiondocument/` for the requirements, asks every intake question (stack, hosting, database and its schema source, sign-in, security, team), and writes only the confirmed, non-secret answers into `.claude/PROJECT-CONTEXT.md`. It writes no code. Answer its questions, or pass each one to the PL or TL who owns it.

Use the commands throughout. Every step below has one, so a hand-written prompt is never needed.

## New Project, Start To Finish

The order to follow on a new project, who does each step, and what must be true before the next one starts. Each step is explained in full under Solution Planning And Task Tracking below.

| Step | Who | What | Next step starts when |
| --- | --- | --- | --- |
| 1 | Infra team | Provide the project repository with the `DEV`, `QA`, `STAG`, and `PROD` branches and the gateway (`CLAUDE.md`, `.claude/`, `doc/`, `scripts/`) at the root | The developer can clone it |
| 2 | PL, then developer | Collect the solution pack from the PL and put it under `doc/solutiondocument/`, superseded material in `Archive` | The pack is in the folder |
| 3 | Developer | Run `/fresh-project-start` and answer the intake questions | `.claude/PROJECT-CONTEXT.md` holds the confirmed stack, hosting, database, schema source, authentication, and team facts |
| 4 | Developer | Run `/generate-phase-plan` | The planning package is in `doc/phasedocument/` |
| 5 | PL or the requirement owner | Answer every `GAP`, `CONFLICT`, `ASSUMPTION REQUIRED`, and `STANDARD CONFLICT`, let Claude update the plan, then approve both `02_Gap_Conflict_Report.md` and `MASTER_PHASE_PLAN.md` in words | Claude has recorded both approvals, with name and date, in `PROJECT-CONTEXT.md` |
| 6 | Developer | Run `/generate-project-tasks` | The backlog is in `doc/tasktracking/` |
| 7 | Developer | Put every phase and every task into ClickUp, keyed by Phase ID and Task ID. Mandatory | Every phase and task from `doc/tasktracking/` is in ClickUp |
| 8 | Developer | Run `/execute-approved-task <TASK-ID>` for one task, `P01` tasks first, then `P02`, then the feature tasks | Claude stops at `User Testing` |
| 9 | PL or the requirement owner | Test the task and approve or reject it | `Accepted`, or `Rework Required` and back to step 8 for that task |

The pack normally holds eight category folders: `1` to `5`, `6a`, `6b`, and `7`. They are a recommendation, not a contract. A missing folder is not a blocker; planning records it as a `GAP`.

The data model is not a separate step. It is designed one task at a time, inside Gates 2 to 4 of `/execute-approved-task`: a logical proposal, then a physical proposal checked against the project's schema source, then the migration and the schema self-check. `/semantic-model-plan` runs inside those gates. The `7_Data Modelling Specs` folder feeds the plan in step 4.

ClickUp is mandatory. Every phase and every task goes into ClickUp before development starts, and its status is kept in step as tasks move. `doc/tasktracking/` stays the record of every task and its status, so update ClickUp from it and never the other way round. The project's ClickUp link goes in the `ClickUp URL` field of `PROJECT-CONTEXT.md`.

Work on a working branch cut from `DEV`, never directly on an environment branch, per Branching And Releases below.

Step 8 needs only the command and the Task ID. It already reads the least context the task needs, never assumes or invents a database object, runs the code and security reviews, and stops at every approval:

```text
/execute-approved-task P03-M01-T001
```

Claude never moves to the next Task ID by itself. Start each one with the same command.

## Gateway Commands

Invoke these as slash commands (for example `/database-task`) or by asking Claude to use the matching file in `.claude/commands/`:

- `fresh-project-start` - bootstrap a blank or unknown repository.
- `project-intake` - repeatable intake to confirm stack, database, deployment, and validation facts.
- `semantic-model-plan` - agree the feature's analytics questions, then shape the tables so they answer them and lift into a semantic model.
- `database-task` - run database-backed work under the database schema rules, including the reuse check against existing tables before proposing new storage.
- `document-code` - add or audit structured documentation for a file or scope.
- `deployment-check` - review deployment readiness before delivering deployment work.
- `verified-closeout` - final validation, the gated knowledge-base update, and reporting before calling work done.
- `owner-override` - the gateway owner lifts one named rule for one named action; refused for anyone Claude cannot verify.
- `eod` - close out, review, or correct the day's status report (`/eod`, `/eod who`, `/eod team`, `/eod date <date>`).
- `orientation-map` - build, refresh, or check the owner's one-page project map (`/orientation-map`, `/orientation-map build`, `/orientation-map check`, plus `for <audience>`, `scope <area>`, and `since <date>` variants).
- `day-start` - open a working session on a Task ID (`/day-start <TASK-ID>`): sets the model, reads the core repo context and only that task's entry, and keeps schema work verified against the project's schema source.
- `session-handoff` - hand work across a session boundary (`mid`, `eod`, `dev`), resume from an exact handoff file (`resume <file>`), or list them (`find`).
- `account-handover` - move in-flight work to a different Claude account (`capture`, `index`, `resume <index file>`, `rebuild`).
- `git-workflow` - run a Git or GitHub workflow by action, from `sync` and `branch` to `pr`, `promote`, `release`, `hotfix`, and `cleanup`, inside the `DEV -> QA -> STAG -> PROD` model.
- `security-check` - run the security review pass on demand, over the current change or a named target. It also runs automatically on every delivered code change; this command is for running it earlier or over something Claude did not write.
- `ui-verify` - check a built screen against the reference layout template in `00-read-first/05-layout-template/` (`/ui-verify [SCREEN] [MODE]`). SCREEN is a route, URL, component, or view file, `changed`, the default, for every screen the task added or changed, or `all` for every screen in the app; MODE is `browser` for a side-by-side visual comparison or `static` for a token and markup check. It also runs automatically before any UI change is called done.

Three more slash commands come from skills in `.claude/skills/` rather than from files in `.claude/commands/`. They run the planning and task workflow (see Solution Planning And Task Tracking below):

- `/generate-phase-plan` - read the live standards and every active requirement under `doc/solutiondocument/`, write the planning package to `doc/phasedocument/` in at most five macro phases, and stop for your approval. Planning only. You start it; Claude does not invoke it on its own.
- `/generate-project-tasks` - after both planning documents are approved, build the task backlog in `doc/tasktracking/`.
- `/execute-approved-task <TASK-ID>` - run one approved task through Gates 1 to 6, starting from `doc/corerepo/14_AI_CONTEXT.md` rather than a full reread, and stopping at every approval.

The bundled `ui-ux-design` skill is mandatory for UI work - it carries the approved design system (see UI And UX Work below). The bundled `semantic-data-model` skill is mandatory whenever a table is designed or changed - it carries the approved data-structure pattern (see Data Model And Analytics Work below).

## Rules Overview

Claude no longer loads the whole `.claude/rules/` folder up front. At session start it reads only `hard-stops.md` and `secret-handling.md`; the always-on safety invariants (ask-don't-assume, requirements, plans, and tasks run through the solution development kit, secret handling, secure coding, verified schema changes, the approved UI system, app-owned per-feature authorization, the approved AI model catalog, guarded AI features, Git-allowed but deploy/migrate-gated, automatic feedback logging, automatic EOD status reporting, the automatic orientation map, no "done" without validation evidence, and no compliance claim made on the strength of code) are summarized inline in the root `CLAUDE.md`, so they stay in force without reading every file. Every other rule loads only when its task-gate fires: writing code that will be delivered loads code documentation, secure coding, production readiness, and enterprise governance; database work loads the database schema rule; a UI screen loads the design skill and `ui-ux-quality.md`; Git/release, API, deployment, and knowledge-base work each load their own rule. Conditional rules apply only when their trigger matches:

- `solution-development-kit.md` - requirement intake from `doc/solutiondocument/`, solution and phase planning, task tracking in `doc/tasktracking/`, or work on a Task ID. It is the authority for the planning and task lifecycle. `phased-workflow.md`, which loads for any non-trivial work, defers to it and adds no phases of its own.
- `ui-ux-quality.md` - the project has a user interface.
- `api-design.md` - a service interface is exposed or consumed.
- `resilience.md` - code calls a dependency across a process or network boundary.
- `data-governance.md` - data is stored beyond transient request state.
- `semantic-data-model.md` - a persisted data structure is designed or altered, or a data model, ERD, or a feature's analytics is planned.
- `operations-incident.md` - a production service runs.
- `ai-agent-governance.md` - the project ships runtime LLM or agent behavior.
- `ai-guardrails.md` - loads right after `ai-agent-governance.md`, whenever user input reaches a model or model output reaches a user, a tool, or another system.
- `ai-model-catalog.md` - the app calls an AI model at runtime, or a provider, model, or catalog screen changes.
- `owner-override.md` - someone asks to override, bypass, skip, or relax a rule.

## Overriding A Rule

The rules bind every user in every session, in every project the gateway is copied into. Only the gateway owner can lift one, and only one named rule for one named action at a time, through `/owner-override`. The typical case is debugging: asking to see DDL directly rather than going through the shape preview and a migration.

The test is agreement, not a single credential. Every identity the session exposes must belong to the owner, checked as hashes against `.claude/OVERRIDE-AUTHORITY.md`, which stores digests rather than values. One mismatch refuses, whatever else lines up.

The signed-in Claude account is the required anchor in every session, because it is always present and authenticated server-side rather than read from a file a user can edit. The GitHub identity acts as a veto: if it resolves and is not the owner's, the session is refused; if it cannot be read it is skipped, never counted as a pass. In a local session a personal key file on the owner's own machine (`~/.c2s/override.key`), kept outside the repository and never committed, is mandatory on top of the anchor. A session whose kind is unclear is treated as local.

Holding a credential is not the same as being the owner. A bearer token is a copyable string and signing in on someone else's machine leaves a working session behind, which is why no single credential is ever sufficient. `git config` identity and anything you state, type, or paste are rejected outright. Claude reports only whether authority was verified; it will not name the owner, the source, which entry matched, or which source dissented, and will not confirm a guess.

Any dissent, a missing key file in a local session, an unreadable anchor, or an authority file that is missing or empty means no override for anyone: Claude refuses, explains the compliant path, and does that work instead. A teammate's laptop cannot override regardless of which accounts are signed in on it. Diagnosing a teammate's problem needs no override; only lifting a rule does.

Under a verified override, output carries a banner, produced artifacts are risk-labeled, and the override is listed in the final report. An override changes what Claude may do, never what it may claim, so validation, approvals, and schema verification are still reported exactly as they happened. Adding an entry to the authority list happens only inside an already-verified session; recovering a lost key is a human git edit under normal review, not something a session can request.

## What Claude Must Ask

Claude should ask enough questions to understand:

- what needs to be built, changed, fixed, reviewed, deployed, or investigated
- why the work is needed and what success means
- approved language, framework, runtime, frontend, backend, package manager, and architecture
- database scope, the schema source of truth, the migration tool, and whether database-backed work is in scope
- source control provider, CI/CD pipeline, artifact flow, hosting target, deployment method, rollback, and monitoring
- environment variables using placeholders only
- security, data sensitivity, and compliance constraints
- the secure coding facts, such as the templating engine and whether it escapes output, the CSRF mechanism, whether the server fetches URLs someone supplies and which internal addresses it may reach, whether the app accepts uploads and their limits, where the security audit trail lives, the dependency vulnerability audit command, and any compliance framework the organization pursues. Claude asks for one only when the work actually depends on it
- for authentication, the authentication model and its design choices, once the work reaches them: how users sign in, the local session technology, the default role a brand-new user is provisioned with, and which tier manages roles and grants. A client secret is never recorded, only referenced
- validation commands and where they can run
- documentation and knowledge-base expectations
- who is on the team, for the `## Team` table in `.claude/PROJECT-CONTEXT.md`: each person's name, email, role (`TL` Technical Lead, `PL` Project Lead, `LD` Lead Developer, `TD` Tech Developer, `TA` Tech Associate), work start, work end, timezone, and the From and To dates of their period on the project. Claude reads this to label the daily EOD report and will not invent a colleague's row.
- whether approved requirements sit under `doc/solutiondocument/`. When they do, they answer what to build, and Claude asks only about what they leave unclear, recording anything missing as a `GAP` rather than filling it. Anything in an `Archive` folder is not a requirement unless someone re-approves it. There is no sprint checklist to write into `.claude/PROJECT-CONTEXT.md`: tasks come from the approved phase plan and live in `doc/tasktracking/`
- for a UI, the App Definition: the app name, title-bar name, navigation tree (the features you place under the four fixed clusters - Workspace, Compliance, Application Administration, System Administration; use only the subset you need, and you cannot add another cluster), and the brand-assets destination path (`<BRAND_ASSETS_PATH>` - where the logo/favicon files live in the project; always asked, never chosen). The theme, tokens, fonts, and the asset files themselves are approved in the bundled `ui-ux-design` skill and are never asked.
- for any table it designs or changes, the analytics questions that data must answer, plus the Analytics And Semantic Model facts: whether a semantic model or BI layer consumes this data and who owns it, how reporting reads it, the reporting time zone and business-day boundary, the key convention, which conformed dimensions the platform already has, how long history and event rows are kept, and where the ERD and data-model write-up live. Claude drafts the question list and you confirm, cut, or add; it will not invent the questions and will not propose a shape before you have confirmed them.
- for a runtime AI model call, the AI Model Catalog facts: where the catalog lives, the providers and model ids in use, what `{{env.NAME}}` resolves against, the project-wide timeout and retry policy, the currency, and who may manage the catalog. Also the route for each step and the multi-call patterns you allow, whether a repair retry is allowed, how long before a model's retirement date administrators are alerted, where the eval attack corpus lives and how often red teaming runs, who owns the AI feedback review queue, and where the AI inventory lives. The record shape, placeholder set, and engine behavior are approved in the bundled `ai-model-integration` skill and are not asked.
- for any feature that sends user input to a model, the AI Guardrails facts: which guardrail layers are required and which an administrator may turn off, the moderation and relevance checks, the strictness levels and what each sets, the fail mode of each check, the streaming moderation mode per feature (buffered or retractable), the content categories and the strike weight of each, the strike threshold and pause length, the link allow-list for model output, who may add retrieval content, the limits and workflow caps, whether text blocked as abusive is quarantined (off unless you say so), who may flip each kill switch and the non-AI fallback per feature, any AI disclosure or labeling obligation, and who manages the guardrail settings
- whether conditional areas apply: a service interface, cross-boundary calls, stored data with retention or privacy needs, a running production service, shipped LLM/agent behavior, or a runtime AI model call

If any answer is missing, vague, conflicting, risky, or stale, Claude must stop and ask again before acting.

Every intake field, what it means, and what a good answer looks like is documented with examples in `00-read-first/03-reference/PROJECT-CONTEXT-UNDERSTANDING.md`.

## What To Store

Store only confirmed non-secret project facts in:

```text
.claude/PROJECT-CONTEXT.md
```

Never store secrets, passwords, tokens, private keys, full connection strings, `.env` values, customer records, or production row data. Use placeholders instead:

```text
<APP_URL>
<DATABASE_NAME>
<DATABASE_USER>
<HOSTING_RESOURCE>
<TOKEN>
<SECRET_VALUE>
```

Requirements, the phase plan, task status, and the project's living memory are not intake facts, so they do not go there. They live under `doc/`: requirements in `doc/solutiondocument/`, the plan in `doc/phasedocument/`, every task with its status and event history in `doc/tasktracking/`, and the compact current-state record in `doc/corerepo/`. `.claude/PROJECT-CONTEXT.md` only points at them and records who approved the two planning documents and when.

## Solution Planning And Task Tracking

Requirements, the phase plan, and every task run through one workflow, the Claude Solution Development Kit, now merged into this gateway. `.claude/rules/solution-development-kit.md` is the authority for it. Where it overlaps the older phased workflow or the EOD task status, it wins. It runs in two stages.

**Stage 1, planning.** Put the approved requirements under `doc/solutiondocument/`, in whatever folders the project uses; the shipped category folders are a suggestion, not a contract. Move superseded material into an `Archive` folder, which planning ignores. Run `/generate-phase-plan`. Claude reads the live standards and every active requirement, builds the complete feature and function inventory, runs the Phase 1 infrastructure readiness baseline, then writes the planning package to `doc/phasedocument/`: the standards baseline, the solution understanding, the requirement register, the gap and conflict report, the architecture, the dependency map, a QA report, and `MASTER_PHASE_PLAN.md` with one file per phase. Then it stops. No application code is written until you explicitly approve both `02_Gap_Conflict_Report.md` and `MASTER_PHASE_PLAN.md`. A gap, a conflict between documents, or a conflict between a requirement and a standard (`STANDARD CONFLICT`) is yours to settle; Claude records it and never reconciles it quietly.

Nothing in the requirements disappears silently. Every requirement, workflow step, menu, feature, function, report, analytic, integration, AI capability, data requirement, security rule, and acceptance condition gets one disposition from a closed list: `Covered`, `Duplicate`, `Conflict`, `Gap`, `Out of Scope - Explicit`, `Deferred - Approved`, or `Not Applicable - Verified`. They are recorded in `doc/corerepo/04_REQUIREMENT_COVERAGE.md` and `doc/corerepo/03_FEATURE_FUNCTION_INDEX.md`, which map to the requirements and never replace them. `Out of Scope - Explicit` needs a source or your decision that says so, and `Deferred - Approved` needs your recorded approval. An unresolved material `Conflict` or `Gap` blocks the planning and build work it affects until you resolve it. The dispositions sit beside the finding labels (`GAP`, `CONFLICT`, `STANDARD CONFLICT`, and the rest): a label classifies a finding, and a disposition records where each source item ended up.

A plan has at most five macro phases, `P01` to `P05`. It uses fewer only where a phase genuinely does not apply, and says why:

1. Platform, Infrastructure & Master Foundation: runtime, repository, and hosting readiness, frontend and backend startup, database ownership with its schema source and migration tool, the master data baseline with its baseline indexing, and the existing integrations the solution needs.
2. Core Application, Login, Administration & AI Foundation: the application shell, sign-in per the recorded authentication model, the application's own per-feature authorization, administration, the security audit trail, and the AI model catalog where the solution calls a model.
3. Feature / Function Design & Dependency Approval: the Gate 1 design of each feature, the logical data proposal, and the indexing and idempotency decisions.
4. Feature / Function Build & Technical Verification: the migration and schema verification, coding authorization, the build, and both review passes.
5. UAT, Acceptance & Release Readiness: user testing, rework, regression, and release readiness. Release readiness never approves a production deploy or a migration by itself.

The phases are the delivery stages, so a phase no longer runs a `PLAN -> DESIGN -> IMPLEMENT -> TEST -> VERIFY -> ACCEPT` cycle of its own. Detailed work belongs in tasks and subtasks, never in an extra phase for a module, a layer, or a technology. A phase is accepted only when all its mandatory acceptance criteria pass.

**Infrastructure readiness.** `infra-readiness-agent` checks the foundation the solution needs during Phase 1, and again whenever hosting, the runtime, the repository, an environment, the database, an external service, the deployment target, or AI connectivity materially changes. Each check lands in `doc/corerepo/02_INFRA_READINESS.md` as PASS, FAIL, NOT APPLICABLE, or BLOCKED, with its evidence. A check that never ran is not verified, and nothing is READY without evidence. Where the schema source cannot be read, database-dependent work is blocked.

**Stage 2, tasks.** After both approvals, `/generate-project-tasks` turns the plan into a backlog in `doc/tasktracking/`, organized `Phase -> Module/Menu or Workstream -> Task -> Subtask`. Each task carries a stable Task ID, its phase, requirement and source feature IDs, dependencies, the gates that apply, acceptance criteria, and whether user testing is needed. Task IDs follow one pattern, `P03-M01-T001` for a task and `P03-M01-T001-S01` for a subtask, and a published ID is never renumbered or deleted: a task nobody wants any more goes to `Cancelled` and keeps its ID.

Every task also states each technical workstream explicitly: Frontend, Backend, Database, Integration/API, AI, Security, Tests/Verification, Acceptance/UAT, the indexing requirement, and the idempotency requirement. One that does not apply reads `Not Applicable - Verified` with its reason, never a blank. The workstreams describe one feature from every side. They are not separate frontend and backend phases, and delivery stays end to end per feature. Indexing is decided from the approved access patterns (keys, joins, filters, sorts, tenant, status, and date lookups, uniqueness), never one index per column, and the physical proposal names the indexes the schema self-check then verifies. Idempotency is designed and tested wherever a retry or a duplicate could do harm, such as webhooks, queues, imports, callbacks, background jobs, AI-triggered actions, and transactional creates, and is not forced onto simple reads; `.claude/rules/resilience.md` governs how it is built.

`TASK_REGISTER.md` holds every task and its status, `TASK_BOARD.md` the progress view, `TRACEABILITY_MATRIX.md` the requirement to task trace, `task-events.jsonl` the append-only event log, and `COMPLETION_LOG.md` the accepted tasks with their timing. When the `.claude/` standards change materially after approval, the plan is refreshed before the affected tasks are generated or run.

A backlog is not permission to code. Once one exists, all implementation runs under a Task ID, one at a time, through `/execute-approved-task <TASK-ID>`. Work that matches no Task ID stops and asks which task it belongs to, and new scope enters through the plan, never untracked. Claude never starts the next Task ID by itself. The six gates run in order, and each stops for you:

1. Plan, design, and evaluate. Claude confirms the task is `Ready`, its dependencies are met, and no unresolved material conflict or gap blocks it, looks at what already exists, and presents the plan, the design with each workstream under its own heading, the risks, the validation approach, and, where there is a real choice, the options with a recommendation. You approve before any code changes.
2. Logical data proposal: the entities, fields, meanings, and analytics the task needs, with no physical table or column names. You approve.
3. Physical proposal checked against the project's schema source, reusing existing tables first and naming the indexes the access patterns need. You approve.
4. The migration, authored through the confirmed migration tool, then the self-check against the approved proposal and `SCHEMA VERIFIED`.
5. Coding authorization and implementation. Claude waits for you to authorize coding for this Task ID, implements only that task, normally in the order database verified -> backend -> backend tests -> frontend -> integration and AI -> end-to-end validation, runs validation, including the duplicate and retry tests its idempotency requirement calls for, and both review passes, logs `IMPLEMENTATION_COMPLETED`, and stops.
6. User testing and acceptance. You test. Approval makes the task `Accepted`. Rejection makes it `Rework Required` with your exact feedback, and only that task goes back through the gates.

Gates 2 to 4 apply only when the task creates, changes, maps to, or depends on persisted structures; otherwise Claude records `NO DATA MODEL CHANGE REQUIRED` with its evidence. Non-trivial work in a project with no backlog runs the same gates in the same order, without a Task ID or events.

A task's status comes from a closed list: `Planned -> Ready -> In Progress -> Implementation Complete -> User Testing -> Accepted`, plus `Blocked`, `Rework Required`, and `Cancelled`. Only `Accepted` counts as finished, and only your explicit approval after testing makes it so. Finished code, passing tests, Claude's own testing, a developer saying it is done, an EOD `Done`, and silence never do. Merging or promoting through Git does not either, and `Accepted` never approves a production deploy or migration.

Time is measured, never estimated. `scripts/task-event.sh <TASK-ID> <EVENT> [ACTOR] [NOTE]` appends a timestamped event, such as `TASK_STARTED` or `USER_TESTING_APPROVED`, to `doc/tasktracking/task-events.jsonl`, and every duration is computed from those events. Four measures stay separate: active implementation time, implementation elapsed time, user testing elapsed time, and end-to-end cycle time through approval. The last one includes waiting on approvals and on the database owner, so it is never reported as developer effort. A recorded event is never edited or deleted, and a missing timestamp stays missing rather than guessed.

**The core repo.** `doc/corerepo/` is the project's compact living memory: the current state, infrastructure readiness, feature and requirement coverage, a code map by path and symbol, the verified data and entity models, the integration register, the AI register (which is also the AI inventory, with each feature's prompt version, kill switch, owner, and risk level), a task status snapshot, the decision and conflict register, the change log, the project handover, and a short `14_AI_CONTEXT.md` plus `PROJECT_MANIFEST.yaml`. `core-repo-maintainer` updates only the files an accepted or verified change affects, and never after a routine edit. Each fact is recorded once. The core repo owns neither the requirements nor the task status: its coverage files map to `doc/solutiondocument/`, and `10_TASK_STATUS.md` is a snapshot of `doc/tasktracking/`, which stays the record. `06_DB_MODEL.md` holds only structures that are `SCHEMA VERIFIED`. In these projects it is also where the working-memory log lives: `01_CURRENT_STATE.md` for focus and progress, `11_DECISION_CONFLICT_REGISTER.md` for decisions and open questions. It is written in vendor-neutral language so another developer or another AI tool can pick the project up, and it never holds a secret or a production value.

A task reads the least that answers it. On `/execute-approved-task`, Claude reads `doc/corerepo/14_AI_CONTEXT.md`, then `doc/corerepo/PROJECT_MANIFEST.yaml`, then the task's own entry, its linked requirements, the gates that fire, and only the code it affects. It rereads every solution document only for fresh intake, a full re-baseline, a material scope change, or a traceability or conflict question, and it calls a specialist agent only when that agent's scope is in play.

**Upgrading the kit in an existing project.** Taking a newer copy of the gateway into a project that already has a plan and tasks is maintenance, never permission to redesign or recode. Do it at a safe checkpoint, not mid-task, mid-schema change, mid-deployment, or partway through a UAT fix, on a dedicated branch with a Git rollback point. Work through `00-read-first/04-solution-development-kit/integration/PRE_UPGRADE_CHECKLIST.md` first; the copy-ready prompt is `V1_3_TO_V1_4_1_UPGRADE_PROMPT.md` in the same folder. The upgrade merges into the project's own `CLAUDE.md`, `PROJECT-CONTEXT.md`, and standards rather than overwriting them. It keeps every published Task ID, mapping an older one to its new macro phase rather than renumbering it, keeps every timestamp, event, UAT approval, and acceptance record, drops no requirement to fit, never resets `doc/corerepo/` or the task history, and runs no DDL, migration, deployment, or feature code. Before the upgrade branch merges, `POST_UPGRADE_VALIDATION.md` must end in `PASS - SAFE TO MERGE`. `FAIL - DO NOT MERGE` means stop, restore or reconcile from the checkpoint, and validate again. Where the upgrade materially changes an approved plan, Claude proposes a refresh or a mapping only, and the approved plan stays in force until you approve the refresh.

## Database Work

The project's own schema is the source of truth, read from the source recorded in `.claude/PROJECT-CONTEXT.md`: the database's own metadata where approved read access exists, otherwise the committed migrations and the schema definition the confirmed migration tool maintains. Tell Claude which one applies and which migration tool the project uses; it will ask rather than infer either from the stack.

Before any database work, Claude must:

1. Read `.claude/rules/database-schema.md`.
2. Confirm the schema source and the migration tool from `.claude/PROJECT-CONTEXT.md`, or ask.
3. Verify every table and column it will reference against the schema source in the current session.
4. Search the existing schema and the table dictionary before proposing new storage.
5. Show you the exact shape and get your explicit confirmation before a migration is written.
6. Stop if the schema source cannot be read.

Claude must not invent table names, column names, relationships, indexes, constraints, schema details, or DDL, and it never executes DDL directly. Running a migration against any environment is a deployment action that needs your explicit approval.

**On a tracked task, the data work runs as gates.** When a Task ID needs persistence, Claude works through Gates 2 to 4 of `/execute-approved-task` in order, and each one stops for you. First a logical proposal: the entities, fields, meanings, and the analytics they serve, with no physical table or column names. You approve it. Then a physical proposal, built against the project's schema source, reusing existing tables where they fit and naming the indexes the task's access patterns need. You approve that. Then Claude authors the migration through the confirmed migration tool and checks the result against the approved proposal, including the index evidence the schema source shows, and records `SCHEMA VERIFIED`, or stops on `SCHEMA VERIFICATION FAILED`. Even after it passes, no code is written until you authorize coding for that Task ID. A chat message, a screenshot, or a remembered schema saying the table exists does not count. The evidence lands in `doc/tasktracking/data-design/` as `<TASK-ID>_LOGICAL_DATA_PROPOSAL.md`, `<TASK-ID>_PHYSICAL_TABLE_PROPOSAL.md`, and `<TASK-ID>_SCHEMA_VERIFICATION.md`. A task with no data change records `NO DATA MODEL CHANGE REQUIRED` and skips these gates.

## Data Model And Analytics Work

Whenever a table is designed or changed, `.claude/rules/semantic-data-model.md` is the authoritative rule and the bundled `.claude/skills/semantic-data-model/` skill is the approved pattern. It sits on top of the database workflow above: every name is still verified against the schema source, every change is still shown and confirmed before its migration, and nothing here permits DDL.

The reason it exists is that a screen and a report want different things. A screen needs the current state of one record, so a screen-shaped table stores the current state and overwrites it. Every analytical question is a count across rows and across time: how many contacts arrived, from which source, how many were enriched, how many failed to enrich and why, how many are recurring, how long each step took. If the source was never stored as a code, no query can group by source. If the status was overwritten, no query can count last month's transitions. If the failed enrichment attempt was only written to a log, no query can report a failure rate. That is not a reporting backlog, it is data that no longer exists, and no later query or migration recovers it.

So the design gains one step at the front. Claude drafts the analytics question set, you confirm it, and each question is traced to the table, column, or reference set that answers it. Anything the shape cannot answer is raised as a gap for you to decide on, never quietly dropped. What follows from the confirmed list: one declared grain per table (one sentence saying what a single row means), atomic rows rather than stored totals, ratios stored as their two parts, anything grouped by held as a codified code plus label reused from the platform's existing dimension, state changes and step outcomes written as rows with coded reasons, duplicates linked rather than deleted, a timezone-aware business event date that is not the audit column, single-column stable keys, and soft delete so a number already reported stays reproducible. A reference member that rows already use is deactivated, never deleted or recoded, and when an earlier step changes, the dependent steps are marked `needs review` as a recorded state change rather than deleted or quietly regenerated.

The change then ships what a semantic model is built from: the complete data structure, the entity relationships with cardinality and optionality, the ERD, and the hand-off that states each table's grain, each measure's unit, default aggregation, additivity, and one agreed definition in words, plus each dimension's hierarchy and whether it is conformed platform-wide. Metadata only; no row data and no PII, and it lands through the knowledge-base gate, with schema metadata travelling in the same change unit as the schema change.

It is deliberately proportionate. The confirmed question set sizes the structure, so a three-question feature gets three answers, and Claude will not add a date dimension, versioned history on every column, a snapshot table, or a star schema that no confirmed question needs. It will also not build a warehouse, a cube, an extract job, a second reporting store, or the dashboards; those are separate work with their own approval. Run `/semantic-model-plan` to do this in order: questions, reuse search, shape preview, then the migration.

On a tracked task, `/semantic-model-plan` runs inside Gates 2 and 3, and that order wins. The question set and a logical shape with no physical names come first and are approved before the existing schema is searched. The physical shape is then shown and approved, and no code depends on it until the schema self-check records `SCHEMA VERIFIED`. See Database Work above.

## UI And UX Work

When the project has a user interface, `.claude/rules/ui-ux-quality.md` is the authoritative standard, and the bundled `.claude/skills/ui-ux-design/` skill is the approved design system, delivered as written guidelines: the layout, both themes, the color combination, fonts, and icon style are specified value-by-value in the skill (all tokens in `reference/design-tokens.md`), and the brand assets (`assets/`: four per-theme logos and two `.ico` favicons) are the only files meant to be copied into the project. Every application reproduces the same look and feel.

The developer supplies only the app-specific values in `.claude/PROJECT-CONTEXT.md`: the app name, the browser title-bar name, the sidebar navigation tree, and the brand-assets destination path (`<BRAND_ASSETS_PATH>` - where the logo/favicon files live in the project, per the confirmed stack; Claude asks for it and never chooses the location, or the developer copies the files manually and records the path). Claude must not invent these - and must not ask about or vary the theme, colors, fonts, logos, or favicon, which are the approved standard.

Claude builds every screen from the one master shell (full-height sidebar, slim top bar over the canvas), keeps standing navigation in the sidebar under the four fixed clusters (Workspace, Compliance, Application Administration, System Administration - using only the ones the app needs, never adding another) at most three accordion levels deep, implements the approved token values in the project's own stack and styling system in both themes, swaps the per-theme logos and favicon with the theme switcher, and designs the empty, loading, and error states for every data-driven view. Claude applies the UI standard exactly the same way every time - the same archetypes, component primitives, status-role meanings, and iconography - and only the app identity, navigation tree, entities, and domain content vary; Claude never invents a one-off pattern.

The approved solution icon pack ships in `.claude/skills/ui-ux-design/assets/solution-icons/`, listed in its `catalog.json`. It follows the company's dev task sheet in four levels, Solution > Module > Sub-module > Feature, the last two only where they apply. Each solution is a folder, such as `eos/`. A node with children is a folder inside its parent's folder holding its own `icon.svg`, which is the one to use, such as `eos/operations-hub/icon.svg`. A leaf is one file named after it in its parent's folder, such as `agentic-crm/agentic-leads-generator.svg`. Claude asks which solution, module, sub-module, and feature the app is and records its icon as `App icon:` in `.claude/PROJECT-CONTEXT.md`: the icon of the most specific node the app is - its Feature, else its Sub-module, else its Module, else its Solution. When the app's own node is not in the catalog, the nearest ancestor's icon is used, else none. It is never guessed, invented, or borrowed from a sibling. The app icon sits directly left of the application name in the top bar at 20px, and above the app name at 48px on the three standalone auth cards. A screen that lists solutions, modules, sub-modules, or features shows each item's icon, 24px in a list and 40px on a card. Every icon takes the `--solution-icon` token (Midnight Blue in light, Green Gold in dark) and no other color. It is embedded inline, from an SVG sprite, or as a CSS mask, never through an `<img>`, because the icon's color cannot reach inside an image and it renders black. An icon is never recolored or redrawn, and never used as the company logo, the favicon, or a navigation icon. Where marketing drew them, a node's folder also holds `outline.svg` (the outline drawing) and `in-parent.svg` (the icon with a small badge of its parent); both are used only when asked.

Every list screen sorts and filters, and that is a requirement rather than a nice-to-have. Every orderable column sorts, the list opens on a stated default order, and a search and filter bar sits above it carrying a facet for each dimension the list is genuinely narrowed by. Both run over the whole result set, on the server when the list is paginated, so filtering finds the matches on page three instead of reordering the ten rows on screen. Page sizes are fixed in every app, old and new: 5, 10, 20, 30, 50, 70, and 100 per page, opening on 10, chosen from one dropdown in the table footer that stays visible on a phone too, never a row of buttons. Changing a filter, the sort, or the page size returns to page one, the toolbar count and the pagination total both report the filtered number, and the sort, filters, and page sit in the URL so a refresh, the back button, or a link to a colleague reproduces the same view. A short read-only sub-table inside a detail page and the dashboard's recent-activity table are the two places neither is expected.

A labeled button has two looks and no others. Exactly one solid button per action group, the one action that commits, and one neutral secondary look - surface fill, one control border, ink text - for every other labeled action, so `Cancel`, `Back`, `Clear filters`, `Reset`, `Apply`, `Export`, `Duplicate`, and `Test Configuration` read the same on every screen; a borderless or outlined labeled button no longer exists. A group that only changes what is on screen, such as a filter bar or a toolbar of view controls, carries no solid button at all, and the page's solid action stays the archetype's primary CTA in the page header. A button's look never changes with state either: loading swaps the label for a spinner at the same width and nothing else, and a destructive secondary action such as a row's `Delete` keeps the identical shell and colors only its icon and label. Icon-only is chrome rather than a button, limited to a closed list by kind: a dismiss or close mark on something dismissable, a clear mark on a field or a chip, and a form repeater's per-row remove control. A component's own primitives sit outside the two looks and keep the look their own file defines - the shell's icon controls, a sortable column header, the pager, a row's expand chevron, a tab strip, a segmented control, and a toast's single inline action - and a current-item marker inside one of them, such as the active page or the active tab, is a marker rather than an action's emphasis.

Every control in a list's actions column carries its word beside its icon - `View`, `Edit`, `Delete` - as visible text rather than only an accessible name, and it keeps that word at every breakpoint, so a narrow screen scrolls the pinned actions column instead of falling back to bare icons. Past about three actions on a row, the first three stay labeled inline and the rest move behind one `More` control whose accessible name names the row; a detail header's action cluster and a bulk-selection bar work the same way. A row's `Delete` opens the confirmation, so it is a secondary control with a danger-colored label, and the solid danger button is the one in the dialog that commits.

Every toast appears at the top right, in one host offset below the top bar so it never covers the top-bar utilities, newest nearest the top edge. That is the only placement in the standard: an error and a success land in the same place, a narrow screen widens that same top edge rather than moving to another corner, and there is no per-screen or per-type option to move one.

A form reports an error once, where the error is. Each message sits inline under its own field, and a blocked submit is announced by one error toast saying how many fields need attention while focus and scroll move to the first invalid field. There is no error-summary card, because it repeated the same sentences the fields already carried and gave you two places to read them; an error toast persists until you dismiss it anyway. A form-level error that belongs to no field, such as a failed save or an unreachable service, gets one inline alert at the form foot instead, since that is the one thing no field can say. A number field never shows the browser's up and down arrows: one global rule in the app's base stylesheet hides them on every number input, and the phone still gets the number keypad. Every single yes/no value is a switch with a visible label and a visible state word beside it, such as `On` / `Off` or `Active` / `Inactive`, whether it applies instantly or saves with the form. A checkbox appears only where you pick several items from a set, such as a checkbox group, table row selection, or a row of the grant matrix.

Two rules about forms are worth knowing before you review a screen. Data entry is page-hosted: a create, edit, multi-step, or settings form is its own route or a form region on the current page, inside the shell, never a popup. A modal is for a decision that has to be answered now, and it carries at most the three or so fields that are the decision itself, so a dialog that has grown a fourth field has become a form and moves to a page.

Every confirmation and prompt is the kit's own dialog, never the browser's native `confirm`, `prompt`, or `alert`; the browser's own leave-page warning on reload or tab close is the one exception. Every dirty create, edit, or settings form guards every exit, in-app navigation included, so edits are never dropped silently, and `/ui-verify` tries each exit to prove it. A disabled control says why it is disabled and what would make it usable, in visible helper text beside it rather than a hover tooltip. In a reference set edited in the UI, a member already in use shows its code locked and offers `Deactivate` instead of `Delete`, and a feature's set always keeps at least one active member.

The page itself never scrolls: the shell fills the viewport, and only the main area and the nav list scroll. The main scroll container is the containing block for anything absolutely positioned, so a hidden live region cannot stretch the page into blank space. A segmented control, such as the theme switcher or a strictness setting, is one shared component: every segment is the width of the widest label, at the 32px height of the fields beside it. A field's width comes from the form grid, never from a `max-width` on its wrapper, and a chip is never wider than its row, so a long label wraps inside it. A focus ring is never clipped, never crosses the next control, and never hides under a sticky column, a sticky header, or the top bar. Inside a segmented control or a tab strip the ring is drawn inset, and every scroll container keeps enough scroll padding to show it.

And a step-by-step form never loses work. Its advance button reads `Continue`: it validates the step, saves it to the server as a draft, then moves on, so a closed tab, a flat battery, an expired session, or a different machine costs nothing already entered. Coming back resumes at the step you left with the earlier steps filled in, reachable from the list with a `Draft` badge. If the save fails, the step stays put with every value intact rather than pretending it saved. You decide where the draft lives, and Claude will ask: a separate draft table, or the record's own table with a draft state. A draft in the live table keeps one row and one id, but its required columns have to be nullable until completion and every query, count, and report has to filter the state; a separate table keeps the live constraints strict. Either way a draft is not a record, so it stays out of the default list and its counts, and out of every other list, export, and report, though the rows you see with the drafts filter on are counted like any other match, it never holds a credential or key, and how long an abandoned one is kept is a retention answer you give during intake.

Every UI change is checked against the reference layout template before it is called done. The template lives in `00-read-first/05-layout-template/`; open `index.html` to see the approved shell, archetypes, and components working. Claude runs `/ui-verify` on its own over every screen the task added or changed and compares each one with the template screen of the same archetype. In `browser` mode, used when a browser tool and the running app are available, it takes side-by-side screenshots and measures styles at the same viewports, in both themes, with the sidebar expanded and collapsed. In `static` mode it checks the tokens and markup against `design-tokens.md` and the rule, and a static-only run is reported as static-only, never as a visual match. The result is a Match / Mismatch table per area: shell and navigation, tokens and surfaces, typography, buttons, archetype structure, lists, forms, feedback, states, responsive, and accessibility. The comparison is exact, down to a fraction of a pixel: a 0.1px difference, one hex digit in a theme color, or a hardcoded color that bypasses the tokens is a mismatch, and measurements are never rounded. Every mismatch is flagged in the report and listed again in Claude's final summary, fixed or not. Any mismatch blocks the done claim until it is fixed and verified again, unless you authorized that deviation in words and it is recorded as a documented exception in `.claude/PROJECT-CONTEXT.md`. The standard covers existing apps as well as new screens: when the kit first lands in an existing app, when a kit upgrade changes the UI standard, or when you ask, Claude runs `/ui-verify all` across every screen, writes the audit to `doc/ui-verify/ui-audit-<D><Month><YYYY>.md`, and every mismatch in it gets fixed, through Task IDs where a backlog exists. The template is the reference for structure, look, and behavior, never for content, so your app name, navigation, entities, and copy stay your own. Run `/ui-verify` yourself any time a screen looks off.

## Authentication And Authorization Work

Authentication follows the application's own authentication model, recorded in `.claude/PROJECT-CONTEXT.md` and confirmed with you at intake. Authorization is always the application's own: what a user may see, open, edit, approve, export, or delete is yours, because only your product knows what those words mean. The authoritative rule is the Role And Access Model section of `.claude/rules/ui-ux-quality.md`, with `.claude/rules/secure-coding.md` and `.claude/rules/enterprise-governance.md`.

**Authorization is per feature, not per role name.** Every feature the sidebar can show has a registry row with a stable code and the actions it exposes. A role is a named set of feature grants. The grant is what gets enforced, at all four layers: the navigation, the handler, the per-record policy, and the query scope. Hiding a menu item is not access control, and filtering rows in the browser is worse than not filtering, because the data was already sent. One more thing to check on that registry: a feature that is registered, active, and granted but that nobody ever built has no route to link to, so the navigation drops the leaf and renders the heading above it anyway, and whoever holds that grant opens a cluster heading with nothing under it.

**Grants are data you change in a screen.** Roles, the grant matrix, and user assignment live under `Application Administration`; the feature registry lives under `System Administration`, because it describes what the app is made of rather than how your deployment uses it. Changing who can reach a feature never needs a code change or a deploy, and it takes effect on the user's next request.

**Those screens have a ceiling, and it points both ways.** Opening them is permission to administer access, not to award access you were refused, so an administrator assigns a role only up to their own tier and adds a grant only if they hold it themselves. That applies to their own record above all, since editing yourself is the whole reason the limit exists; without it, one ordinary save is a promotion the audit files as legitimate. The other direction is the one that catches an honest administrator: a role or grant sitting above their authority has to be shown as a locked cell and merged back in on save. Render only what they may change, then replace the whole record with what came back, and an application administrator demotes a system administrator by pressing Save on a form that never mentioned the role it removed.

**A brand-new user is not an error.** Someone signing in for the first time has no local record yet, and authentication already let them in, so they get your confirmed default. An app that treats a missing record as "not allowed" locks out every new joiner on day one. The same holds when the configured default role names a role that does not exist, from a typo or a seeder that never ran: the user is admitted with no grants and the audit row says so, never refused and never handed some other role. They stay without a role until an administrator assigns one, because their record now exists and the next sign-in is not a first login. Check the role resolves at migration or seed time, where the database exists. A user whose record exists but whose last role an administrator removed is not a first login, and never gets the default role back.

**The app is never left without an administrator, and deactivation is immediate.** A save that would leave no active system administrator, whether from a role change, a deactivation, or a removed grant, is refused with its reason and recorded. Deactivating a local user, or removing their last role, ends their access on the next request, and both land on the no-access page. A deactivated user's local sessions are torn down. This ends access to your app only.

**The framework's own authorization helpers need wiring.** `Gate`, `Policy`, `can()`, a `Principal`, `request.user`: they resolve the actor from the framework's authentication. Bridge your identity into it deliberately or bypass it and call the policy directly. Half-wired, it does not error, it denies everyone.

**Every sign-in attempt gets recorded, successful or not.** Every failure shows the user the same generic message on purpose, so the recorded outcome is the only place the real cause survives. Claude writes one code per attempt from a closed set, covering success and each kind of failure the sign-in flow can produce. It is a durable record in the security audit trail rather than only a log line, and you decide what the codes are called. An attempt that fails before the identity is known carries no user, and that blank is the fact rather than a gap.

The UI surface of authorization work is the Access Control screens and the standalone auth pages the application owns under its authentication model. Everything else is backend and database.

## AI Model Work

When the app calls an AI model at runtime, `.claude/rules/ai-model-catalog.md` is the authoritative rule and the bundled `.claude/skills/ai-model-integration/` skill is the approved pattern. A model is data, not code: one catalog record holds the endpoint, method, header rows, typed body fields, response paths, price, and a reference to its credential, and one generic engine reads that record and makes the call. Adding, changing, or retiring a model is a configuration change with no code change and no per-provider branch.

The reason is the payload. Providers disagree about where the model id goes, what the output-token field is called, how deep the prompt sits, and where the answer comes back, and one provider's own product line moves the token field three times. Typed body fields are what keep the payload correct per model: a number stays an unquoted number, a boolean stays a boolean, and a structured field can carry a nested array such as a message list.

Two boundaries matter. The catalog owns the provider envelope up to the content path; whatever the model generated inside that block belongs to the calling code, because only the caller knows what it asked for, so Claude never adds content parsing or shape repair to the engine. And the API key lives in one masked field on the record itself, referenced as `{{api_key}}`, entered once, never re-rendered into a form, masked in any echoed request, and never exported or logged.

A record uses the provider's dated snapshot model id, never a moving alias that the provider can point at another model, and carries the provider's lifecycle state and retirement date, so administrators are alerted ahead of a retirement. A replacement model is a new record that passes its test call and then the full eval set before any route moves production traffic to it. A route changed on the AI Routing screen is gated the same way: the eval result is recorded per step, record, and prompt version, the screen offers only records that passed, and the server checks again on save. A provider's own in-call fallback option, which lets a second model answer inside the same request, is never set on a record.

Every AI feature that takes user input is guarded, per `.claude/rules/ai-guardrails.md` and `reference/guardrails.md` in the skill. The checks run cheapest first: the kill switch, a paused user, and the limits; a canned reply to small talk; the length cap; the rules layer; moderation; relevance; then the model call and an output check before anything lands. The one exception is the confirmed retractable streaming mode, where the check runs on the stream and text already shown can be taken back. Uploads, retrieved chunks, and tool results reach the model as data in their own labeled part of the request, never as instructions, and retrieval is filtered to the user's own scope in the data layer. Assume the prompt will leak, so it holds no secret and no authorization logic. Model output renders as text, with no automatic images, and a link renders only when its destination is on the confirmed allow-list or traces to a source the application supplied. Each check has a confirmed fail mode, and a degraded check is never silent: administrators see a warning and an alert fires. Limits cover the whole workflow, every AI feature has its own kill switch with a non-AI fallback, and every guardrail settings change is audited.

A step can make more than one model call: dual drafting with a judge, a critique pass, a repair retry, or a pre-draft of the next step. These are optional patterns in the calling code, above the one engine, confirmed per project, and every call counts against the budget. The routing for each step is data pointing at catalog record ids. No model that drafted, critiqued, or judged content ever decides whether it is accepted: one server-side check does, on every path. An agent's tool call runs as the requesting user, checked against their own grants in code. Long work runs in a background task, and no database transaction stays open across a model call. See `reference/orchestration.md`.

The AI admin screens follow the existing archetypes in the four clusters, per `reference/ai-admin-ui.md`. One System Administration group, `AI`, holds every AI configuration screen in this order: AI Providers, AI Routing, Guardrails, Word Lists, Limits And Budgets, Kill Switches, each its own feature and grant. `Integrations` keeps only the non-AI connections. The Guardrails screen has no master switch: a required layer renders locked on, only a layer the project recorded as optional can be turned off, and stopping AI for everyone is the kill switch's job. A new check on an enforcing pipeline, or a changed threshold, may run in annotate mode first, but the baseline layers always enforce. Usage, guardrail events, and a confirmed quarantine are view-only screens under Compliance > AI Activity: `View`, and an audited `Export` at most. AI pauses, with their `Resume` action, and the feedback queue sit under Application Administration > AI Oversight, because they act on what they show. Users see AI content with the confirmed `AI generated` label and a provenance line, and can rate it or report a problem. A feature that is not configured or switched off says why and who can fix it.

What you tell Claude: where the catalog lives (a database table, a versioned config file, or the settings provider), which providers and model ids are in use, what `{{env.NAME}}` resolves against, the project-wide timeout and retry policy, the currency, and who may manage the catalog, plus the routing and lifecycle facts and the guardrail facts listed under What Claude Must Ask. Record those in `.claude/PROJECT-CONTEXT.md` under AI Model Catalog and AI Guardrails. A database-backed catalog goes through the database schema workflow like any other table.

What Claude will not do: hardcode an endpoint, payload, response shape, or price; keep a second call path for a second provider; re-render or log a saved API key; report a cost of zero when the price or the token count is unknown; or let a record become callable before a real test call passed on its current values. It also will not ship an AI feature without its guardrail pipeline and kill switch, put untrusted content in the instruction part of a prompt, store the text of a message blocked as abusive outside a confirmed quarantine, or route traffic to a model, prompt, or step that has not passed its eval set. The two catalog screens sit first in the System Administration `AI` group, restricted to the system admin tier. They follow the approved archetypes and the test-before-save gate, so `Save` appears only after `Test call` succeeds, and they keep every field in a form section on one row. Entries are named after the provider and model (`OpenAI GPT-5.6`, `Claude Opus 5`), not after the job they do.

## Deployment Work

Before any deployment-related work, Claude must:

1. Read `.claude/rules/deployment.md`.
2. Ask for the real source control, CI/CD, hosting, runtime, artifact, deployment, rollback, and validation details.
3. Avoid provider-specific instructions until the provider and deployment method are confirmed.
4. Use placeholders for environment values.
5. Ask before production-impacting actions.
6. When a change needs them, list the manual server-side or control-panel steps the developer must perform (environment variables, cron or scheduled jobs, SSL/TLS, DNS, database creation, file permissions, extensions, workers, restart), provider-neutral until the provider is confirmed, and say so when none are needed.

Nothing is fixed. Deployment may be any developer-confirmed workflow.

## Branching And Releases

Branch, promotion, and release work follows `.claude/rules/git-branching-release.md`. Working branches are `feature/*`, `bugfix/*`, `release/*`, and `hotfix/*`; environment branches are `DEV`, `QA`, `STAG`, and `PROD`, promoted in that order. After pushing a working branch, Claude shows the Pull Request URL and the ordered next steps rather than merging automatically, unless you ask it to.

Production release tags use CalVer `vYYYY.R.P`, created from `PROD`. All Git and GitHub actions are pre-authorized; Claude states the plan first. Running a production deploy or database migration is a separate action that still needs your explicit approval.

Run these Git workflows with `/git-workflow <action>`: `sync`, `stash`, `branch`, `commit`, `pr`, `promote`, `release`, `inspect`, `conflicts`, `undo`, `force-push`, `cleanup`, and the two end-to-end runs `feature-to-prod` and `hotfix`.

## Knowledge Base Updates

After each completed implementation, Claude asks you whether to create or update the knowledge base now or defer it. Knowledge-base files are written only after:

1. implementation is complete
2. validation ran, or the limitation is documented
3. you have verified and validated the work and explicitly approved the update

Claude never writes knowledge-base files without your explicit approval; if you defer, it records nothing and notes the deferral in the final report.

When you approve, Claude asks whether the work is a solution, a module, a sub-module, or a feature and where it sits in the hierarchy - Solution > Module > Sub-module > Feature. A solution has multiple modules, a module can have sub-modules where applicable, and each module or sub-module can have multiple features. You classify it; Claude does not. A feature write-up covers logical flows and workflows, data models and tables, frontend, backend, API routing, and system architecture; solution, module, and sub-module READMEs hold the overview and link their children.

The knowledge-base README is the index: every knowledge-base file is linked from it, so you can jump straight to any write-up. The knowledge base is living documentation - as the project is built, Claude updates the affected write-ups and the index (behind the same approval gate) so they never go stale against shipped behavior.

The working-memory log is different: it is written as the work happens rather than behind this gate. In a project that uses the solution development kit it lives in `doc/corerepo/` (`01_CURRENT_STATE.md` and `11_DECISION_CONFLICT_REGISTER.md`), so Claude does not ask where to create a separate one. The core repo is its own store, not a knowledge-base tier, so this approval gate does not govern it either.

For intake facts, update only `.claude/PROJECT-CONTEXT.md` with confirmed non-secret information.

## Secure Coding

You do not ask for this. Every code change Claude writes follows `.claude/rules/secure-coding.md`. The review passes below catch these defects once they are written; this rule is what keeps most of them from being written at all.

What to expect in the code:

- **The server validates everything.** Form fields, query strings, headers, uploaded files, webhook bodies, and responses from other services are checked on the server. The browser's form validation is there to help the user and is never the thing keeping bad data out, because anyone can send the request without the page. A request is bound to named fields rather than mass-assigned onto a record, since mass assignment is how someone sets their own role by adding one field to the request.
- **Input never becomes syntax.** Values go to the database as bound parameters. A sort column from the URL cannot be a parameter, so it is checked against the list of real column names first, and that matters here because every list screen sorts from the URL. Commands run with separate arguments, and file paths come from ids the app issued.
- **Output is encoded for where it lands.** The template engine's escaping stays on, and no value a user typed goes into an `onclick`, a `style`, or inline script. Rich text goes through the one approved sanitizer, or shows as plain text when there is none. A user-supplied link is checked for its scheme, so a `javascript:` link never renders. An export neutralizes a cell that would run as a spreadsheet formula, and a response carries only the fields listed for it, never the whole record.
- **CSRF stays on.** Every state-changing request authenticated by the session cookie carries the confirmed CSRF token, and no `GET` creates, changes, or deletes application data or access. The local-deactivation teardown and the request's own audit record are the exceptions to that last part. The one standing CSRF exemption is an inbound webhook, which is checked by its signature and a replay check instead. An API authenticated only by a bearer token needs no CSRF token.
- **Cookies and headers are set deliberately.** The session cookie is `HttpOnly`, `Secure` over HTTPS, tied to the app's own host, and `SameSite=Lax`, because `Strict` breaks a sign-in callback when the identity provider is on another site. Cross-origin reads go only to origins you confirm, matched exactly.
- **No unsafe deserialization or dynamic execution.** Untrusted data is never turned back into objects (`unserialize`, `pickle`, and their equivalents), a JSON parser never lets the payload name a class, XML is parsed with external entities off, and nothing a user can influence is evaluated, included, or used to pick a class to load.
- **Outbound calls stay verified.** TLS certificate checks are never switched off, not even to get past a certificate problem. The server does not fetch a webhook, import, model, or connection-test URL that resolves to an internal address, unless the environment configuration names that destination, and it re-checks every redirect. A browser is redirected only to the app's own routes or registered URLs.
- **Uploads are checked by content.** Where the app accepts files, the type is allow-listed and checked against the actual bytes, the size limit is enforced, the file is stored outside the web root under a generated name, anything that can carry script (HTML, SVG) is served as a download, and every download is authorized like the record it belongs to.
- **Errors show nothing internal.** Production runs with debug output off. An unexpected error shows a generic message and a correlation id, and the stack trace stays in the log; a validation message still says exactly what to fix. A record outside the signed-in user's scope answers exactly like one that does not exist. Logs identify people by id, never by name or email.

Security-relevant actions leave an audit record: sign-in outcomes, account status changes, role and grant changes, access tokens and share links issued or revoked, acting as another user, permanent deletes, an administrator restoring someone else's record, exports, configuration changes, and anything else you add during intake. Refused attempts are recorded too, because a blocked privilege change is the only sign that somebody tried. Each record carries who, what, which record, the outcome, when, the correlation id, and the session, and never a secret. The trail is append-only: no code path or screen edits or deletes it, and the database grant that enforces that is a manual step Claude reports as outstanding until you confirm it. A log line does not count as a record, and reading the trail is a feature with its own grant. This only fires when a change adds or alters one of those actions. If the project has no audit trail yet, Claude raises it rather than building one inside an unrelated change, and the action does not ship unrecorded unless you accept that as a documented exception.

A change also ships tests for the security controls it adds or alters, not for protections the framework already applies that the change left alone: a new validation rule rejects bad input, a new raw query treats an injection payload as data, a new render path shows a script payload as text, a new state-changing route refuses a request without a CSRF token and a new exemption refuses one without the control that replaces it, a new download does not return an out-of-scope record, a new audited action writes its record. A change that touches none of these needs none of them, and the report says so.

Two related rules live in other files. Dependency vetting is in `.claude/rules/enterprise-governance.md`: adding or updating a dependency runs the project's confirmed vulnerability audit, and a version with a known critical, high, or unrated advisory is not taken unless the fixed version is used or you accept the risk and it is recorded as an exception. The compliance rule is in `.claude/rules/production-readiness.md`: Claude never calls anything SOC 2, ISO 27001, or otherwise compliant, certified, audit-ready, or aligned because of its code. It describes the control it built and the requirement it supports, and when a framework comes up it names what still sits outside the repository.

## Code Review And Security Review

You do not ask for these. Every code change Claude delivers gets both passes before it is called done, in every project the gateway is copied into. `.claude/rules/review-gates.md` is the authoritative rule.

The `code-reviewer` subagent asks whether the change is correct, complete, in your project's patterns, and no larger than what you asked for. The `security-reviewer` subagent asks whether it can be abused, whether it leaks, and whether it trusts something it should not, covering authentication and authorization bypass, injection, secrets, PII exposure, insecure defaults, and dependency risk, plus extra lists when the change handles input, output, outbound calls, redirects, uploads, errors, or security-relevant actions, adds or updates a dependency, or touches sign-in and authorization, AI/LLM features, cryptography, governed data, or agent lifecycle.

Both run as subagents against the actual diff rather than as Claude marking its own homework. The author of a change is its worst reader, because the assumption that produced the defect also hides it on re-read.

Every finding carries one severity:

- `Critical` - exploitable now, data loss, or a secret exposed.
- `High` - a real defect in a security or correctness control, reachable in practice.
- `Medium` - needs an unlikely precondition, or has a bounded blast radius.
- `Low` - hardening or maintainability, no current failure path.

`Critical` and `High` block. The change is not called done and not proposed for merge until it is fixed and the pass re-run over the fix, or you explicitly sign off on that named finding, which gets recorded as an exception in `.claude/PROJECT-CONTEXT.md`. Blocking is about the claim, not about you: Claude keeps working on the change, it just will not tell you the change is finished. `Medium` and `Low` ship, reported so carrying them is a visible decision.

Depth is proportionate. A change touching authentication gets the full pass; a small contained change gets a real but short one; a documentation-only change reports that there are no code paths to review. What never varies is that the pass ran and its result was reported, including when it found nothing. Claude will not report "no security issues" as a conclusion a pass never actually reached, and it will name any area it could not check rather than letting silence read as clear.

Run `/security-check` when you want the security pass earlier, over a fix, or over code Claude did not write.

## EOD Status Reports

You do not write the end-of-day report. Claude does, as the work happens, so a forgotten report is not a thing that can happen. This runs in every project the gateway is copied into, from the first prompt of the session, without being switched on.

At the first substantive prompt Claude opens or creates today's report at `doc/eod/eod-date-<D><Month><YYYY>.md` (for example `eod-date-17August2026.md`) and asks two short things in one message: whose EOD this is, and what is on your list today. Answer or ignore it and keep working; it never blocks. After that it updates the report as each task reaches an outcome, not on every prompt. One file per day, one `## Developer Name (ROLE)` section per developer, four summary lines (Planned, Progress, Pending / Next, Blockers / Risks) sitting over a task table.

Claude asks whose session it is rather than working it out, because on a shared Claude plan there is nothing to work out. Everyone signs in as the same account, shows the same account email, and reaches the same GitHub login, and `git config` is a plain text file that is often set up identically across a team. All of it identifies the team, none of it identifies a person. So you type the name, and Claude matches it to the `## Team` table in `.claude/PROJECT-CONTEXT.md` to pick up your email, role code, and section position.

That table is the roster, filled during intake alongside every other project fact: name, email, role (`TL` Technical Lead, `PL` Project Lead, `LD` Lead Developer, `TD` Tech Developer, `TA` Tech Associate), work start, work end, timezone, and the From and To dates of your period on the project. The working window is your stated schedule, a static fact; Claude never measures or records the hours you actually worked. If the table is still empty when a session needs it, Claude asks for your row and fills it, then asks you to complete the rest of the team when you can. It will not invent a colleague's name, role, hours, or dates.

From and To decide who appears in a given day's report. You are seeded into a report only when its date falls inside your window, so someone joining mid-project starts appearing on their first day and not before, and earlier reports are never rewritten to imply they were there. When someone leaves or moves to another project, fill their To date: they stop appearing the next day, every report they were part of stays exactly as it was, and their row stays in the table so those past sections remain attributable. If they come back, clear To rather than adding a second row.

The answer is held for that session only and is never written to project context, a settings file, or any global setting. It is attribution, not identity: a typed name cannot verify an override, unlock a rule, or stand in for an approval, and that is exactly why typing it is good enough here.

Every task carries one status from a closed list: `Not Started`, `In Progress`, `Partially Done`, `For Review`, `In Review`, `Blocked`, `Done`, `Dropped`. `Blocked` always states why. `Done` needs the same evidence as any other completion claim, so work that was written but never validated is `Partially Done`, not `Done`. The list is closed so a week of reports reads consistently; if your project genuinely needs another status, add it to the table in `.claude/rules/eod-reporting.md` rather than writing prose into a row.

A row for a tracked task leads with its Task ID, for example `P02-M01-T004 Sales Handoff Prompt`, so a reader can find it in `doc/tasktracking/`. The EOD status says what happened to the work today and never stands in for the task's tracked status. An EOD `Done` never makes a tracked task `Accepted`; only your explicit approval after user testing does. Writing the report records no task event either, because events are recorded when they happen.

Two more columns say when the work moved. `Started` is stamped once, when a task first leaves `Not Started`, and `Completed` when it reaches `Done` or `Dropped`; a task still in flight has the first and an empty second, and that empty cell is the information. Both carry a date as well as a time (`17 Aug 09:20`), because `In Progress` since this morning and `In Progress` since last Tuesday read identically in a status column and mean very different things to whoever plans around the report. Claude stamps the clock when it watches the change happen in your session, asks when the change happened outside it, and leaves the cell blank rather than guessing. Reopen a finished task and the `Completed` is cleared, with the reason in the Note.

What it will not do: record hours, session counts, prompt counts, or activity levels; treat `Started` and `Completed` as anything but marks on a task, so they are never totalled into hours or a rate; put customer data or production values in a report; or claim progress it did not observe. Anything it reconstructed rather than watched is marked `(inferred)`, and your account of your own day always wins.

The report is written all day and committed at close. Run `/eod` to bring it up to date and commit it, `/eod team` to see everyone's day and who has not reported, `/eod who` when a shared machine is logging to the wrong person, and `/eod date <date>` to correct an earlier day.

This is not the session handoff below. An EOD report is a short status for whoever reads the day's progress; a handoff is technical continuity for the next session. When you finish a day with work still in flight, write both.

## Orientation Map

One page saying where the project actually stands, written for whoever owns it rather than for the team. You do not have to ask for it. Claude builds it the first time a trigger fires and rebuilds it whenever the project moves, in every project the gateway is copied into.

It answers three questions in a fixed order, and the order is the point. Position: what this thing is, in one honest paragraph, and roughly how far through it is. Surface: of everything in the repository, what you have to care about and what you can ignore, naming real files and counting them, because that is the part that actually reduces the confusion. Open: what is unresolved, with the decisions only you can make kept apart from the work Claude can do. Most project summaries answer only the first, which is why they are comforting and useless.

It is a snapshot, not a dashboard. The page reads nothing at runtime, reloading it changes not one character, and every fact on it was written in when it was generated. That is why it carries the date and the commit it was built from, in the footer and in a comment at the top: a page that claims to be current and quietly is not is worse than no page at all.

It also holds no fact of its own. Everything on it is read from `.claude/PROJECT-CONTEXT.md`, the phase plan in `doc/phasedocument/`, the task register, board, and event log in `doc/tasktracking/`, the current state, infrastructure readiness, requirement coverage, and decision and conflict register in `doc/corerepo/`, the working-memory log, the knowledge base, the EOD reports, and the repository at the moment it is generated. So it never becomes one more version of the truth, and a line on it that exists nowhere else is a defect rather than news. If you find one, put the fact where it belongs and regenerate.

Claude rebuilds it when a milestone or a phase is accepted, when the Phase Plan is approved, when a task changes status in `doc/tasktracking/`, when a file joins or leaves the configuration surface, when a blocker opens or closes, or when a documented exception or an accepted `Critical` or `High` finding is recorded. Not on ordinary commits: a page rewritten on every push is noise in the history and stops telling you that something moved. Each rebuild goes back to the sources rather than adjusting the numbers on the existing page, because an inherited error survives every refresh that does not, and it reads as more settled every time it is carried forward. After a refresh Claude reports the difference, and says outright when something you had listed as open is now resolved.

Confirm four things during intake and it runs itself: where the page lives (`doc/orientation-map.html` by default), who it is written for, what counts as a milestone being formally accepted here, and which directories make up the configuration surface. They sit in the Orientation Map section of `.claude/PROJECT-CONTEXT.md`.

Run `/orientation-map` to refresh it now, `/orientation-map check` to ask whether it can still be trusted without rewriting it, and `/orientation-map for a new engineer joining this week`, `scope <subsystem>`, or `since <date>` for a variant written to its own file.

## Session Handoff

When you want the work to survive a session boundary, run `/session-handoff`. Claude rebuilds the state from durable evidence (the working-memory log, Git history, open PRs, feedback logs, the task register, board, and event log in `doc/tasktracking/`, and the current session) and writes a timestamped handoff file - done, in progress, pending, blockers, and the single first action to resume - into a shared `handoffs/` folder, so the next session can point to that one file and pick up. Each handoff is its own `<timestamp>_<short-topic>.md` file so many people's handoffs coexist without overwriting each other, and you resume by naming the exact file (there is no "latest" shortcut). Three modes cover the cases: `/session-handoff eod` covers the whole day, `/session-handoff mid` (the default) is for when you just want to continue in a fresh session on the same machine without explaining the task again, and `/session-handoff dev` is for when another developer will pull the work and finish it on their own device (commit and push first, so nothing is stranded on your machine). Resume with `/session-handoff resume <file>`, and list the handoffs with `/session-handoff find`. Claude cannot read past chat sessions, so keep your working-memory log current; the handoff is only as complete as the trace the session left behind.

In a project that uses the solution development kit, `doc/corerepo/13_HANDOVER.md` is the project-level handover, kept current by `core-repo-maintainer` when a handover is requested or a task or phase closes. It says where the whole project stands for whoever takes it over; a session handoff says where one session stopped. Neither replaces the other.

## Account Handover

When the Claude account itself changes - the usage allowance is exhausted, or the account is being rotated or reassigned - use `/account-handover`. The sign-in belongs to the Claude Code install rather than to one chat, so switching accounts takes every open session with it at once. The flow is: bring each active session to a clean boundary and push, run `/account-handover capture` in every chat you are transferring (one handover file each), run `/account-handover index` once to tie the batch together in a single file, switch and confirm the active account, then run `/account-handover resume <index file>` in a fresh session. On the new account, read the index and only the one handover file you are about to work on, use one fresh session per file, and keep Sonnet as the default - you switched because an allowance ran out, so the resume should not burn the next one. If the limit hits before you can capture anything, push what is on disk and run `/account-handover rebuild`, which reconstructs state from the repository and asks you to fill the gaps instead of guessing.

## Completion Checklist

A task is not complete until Claude reports:

- what changed
- files changed
- validation commands and results
- database/schema impact
- deployment impact
- knowledge-base/table-dictionary status
- documentation/docstring coverage for code changes
- security impact, describing each control as what it is and the requirement it supports, never as making anything compliant
- for code that adds or alters a security control: one line per control touched (the trust boundary and its server-side validation, output encoding or sanitizer, CSRF or cookie change, outbound call or redirect checks, upload handling, audited actions and whether append-only is enforced), the security tests added, and the security checks run
- for a dependency added or updated: the vulnerability audit result, and any advisory accepted as an exception
- UI states covered (success, empty, loading, error, small-screen) for UI work
- for authentication or authorization work: the sign-in pieces changed under the recorded authentication model, the feature registry rows and grants added or changed, and that every grant is enforced at all four layers
- for data-structure work: the confirmed analytics question set and any question the shape does not answer, the grain of every table touched, the measures with units and additivity, the dimensions reused or added, the history and outcome capture, and where the entity relationships, ERD, and semantic hand-off landed
- for AI model work: which catalog records changed, the test-call result that gated the save, token and cost impact, and confirmation that no credential value, prompt text, or response content was printed
- for AI guardrail work: the layers and their order, the fail mode of each check, the streaming mode per feature, the limits and how each refusal reads, what is stored for a blocked message, the kill switches and their fallbacks, the disclosure label applied, and the tests run with synthetic payloads
- the review passes: that the code review and security review both ran and over which files, every finding with its severity, how each `Critical` and `High` was resolved, and explicitly that a pass ran clean when it did
- for a tracked task: the Task ID and its status, the gates passed and who approved each, the technical workstreams with the reason for each `Not Applicable - Verified`, the indexing and idempotency decisions, the data gate outcome (`NO DATA MODEL CHANGE REQUIRED`, or `SCHEMA VERIFIED` with the verified objects), the events recorded through `scripts/task-event.sh`, and the `doc/corerepo/` files updated, or that none needed it. A tracked task ends at `Implementation Complete` and waits for user testing; it becomes `Accepted` only on explicit user testing approval
- for planning: the number of macro phases, never more than five, the count of source items per disposition, every unresolved material `Conflict` or `Gap` with the work it blocks, and every infrastructure check not yet verified
- for a kit upgrade: the post-upgrade validation result, `PASS - SAFE TO MERGE` or `FAIL - DO NOT MERGE`, and what failed
- the EOD report line: the report path, the developer it was logged for, and any task whose status changed
- the orientation map line, when a trigger fired: the page path, which trigger fired, what changed on it, and whether anything previously open is now resolved
- risks, assumptions, and manual steps, including manual server-side steps the developer must perform

Claude must not claim work is bug free or complete unless validation evidence exists, or the exact validation limitation and follow-up steps are documented.
