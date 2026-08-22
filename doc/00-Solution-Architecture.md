# CLaaS2SaaS SemantIQ — Solution Architecture

**Document ID:** 00-Solution-Architecture
**Status:** Draft for review
**Owner:** Technical Lead
**Applies to:** eduCLaaSTeach/semantiq

---

## 1. Purpose

SemantIQ is a **control plane for Microsoft Fabric**. It guides an organisation from raw
source systems to a governed conversational AI application, executing the 80-step
end-to-end procedure through the Microsoft APIs where they exist, and verifying and
recording it where they do not.

The reference target architecture SemantIQ builds and governs:

```
ERP / CRM / LMS / SQL / Excel / APIs / Dataverse / Business Central / Other sources
        |
Fabric Data Factory / Mirroring / Shortcuts / On-premises Gateway
        |
OneLake
        |
Lakehouse - Bronze -> Silver -> Gold
        |
Fabric Warehouse / Gold tables
        |
Power BI Semantic Model + DAX + RLS + Prep for AI
        |
Fabric Data Agent
        |
Copilot Studio Agent
        |
Teams / Web / Business application
```

## 2. Confirmed decisions

| # | Decision | Confirmed value |
|---|---|---|
| 1 | Fabric tenancy | **Bring-your-own-tenant.** Each customer connects their own Microsoft Entra ID and Fabric tenant. SemantIQ never hosts customer data. |
| 2 | Provisioning posture | **Both supported.** SemantIQ can create Fabric artifacts *or* attach to existing ones. Attach is built first; provision layers on top. |
| 3 | Conversational runtime | **Fabric Data Agent** as the governed answer engine, surfaced through a **Copilot Studio** agent to Teams and web. |
| 4 | Deployment target | GoDaddy server, AlmaLinux 9.8, MySQL 8.0.46, Laravel 13, React 19. See section 9. |
| 5 | Personas | **Data Engineer, Business User, Administrator** — mapped to the five-tier role baseline in section 7. |

## 3. The central design problem: not every step has an API

The 80-step procedure mixes three fundamentally different kinds of work. Treating them
identically is the single biggest way this product could fail, so SemantIQ classifies
**every** step into one of three automation tiers, and the UI is honest about which tier
the user is standing in.

| Tier | Name | What SemantIQ does | Example steps |
|---|---|---|---|
| **A** | **Automated** | Executes the change through a Microsoft API and records the result. | Create workspace, assign capacity, create lakehouse, create and run pipelines, create Data Agent, git integration, deployment pipelines |
| **B** | **Guided and verified** | Cannot make the change (no API, or portal-only authoring). SemantIQ states the exact action, deep-links to the correct Microsoft screen, then **reads back tenant/artifact state to verify** it was done, and blocks progress until verified. | Fabric tenant AI settings, cross-geo settings, gateway installation, private connectivity, Copilot Studio agent authoring, channel publication |
| **C** | **Governed record** | The artifact is knowledge, not configuration. SemantIQ is the system of record and the review workflow. | Business definitions, KPI ownership, ground-truth question bank, AI change process, sign-off for go-live |

**Design rule:** a Tier B step is never marked complete on a user's word alone. It is
complete when a verification probe returns the expected state, or when a named approver
records a written exception. This is what makes SemantIQ a governance tool rather than a
checklist app.

**Verification note:** the Microsoft Fabric, Power BI, Power Platform, and Copilot Studio
API surfaces change frequently, and service-principal support varies per endpoint. Every
step's tier is therefore stored as **configuration, not code**, and a
`CapabilityProbe` suite runs against a live development tenant to re-confirm tiers per
release. A step may be promoted from B to A without a code change.

## 4. Application architecture

A **modular monolith**: one Laravel 13 application, internally partitioned, with React 19
served through Inertia.

| Layer | Technology | Notes |
|---|---|---|
| Presentation | React 19 + TypeScript, Inertia, Vite | Typed page props; assets built in CI, never on the host |
| Application | Laravel 13, PHP 8.3+ | Control plane, token broker, orchestration |
| Persistence | MySQL 8.0.46 | InnoDB, utf8mb4, JSON columns for artifact metadata |
| Async | Laravel queue on the **database** driver | Portable to constrained hosting; Redis is an optional upgrade |
| Progress transport | **HTTP polling** on a run-status endpoint | Deliberate: no WebSocket dependency (section 9) |
| Integration | Internal `Fabric` module | The only code that speaks HTTP to Microsoft |

**Why Inertia rather than a separate SPA:** Microsoft access tokens must never reach the
browser. Inertia keeps one session, one authorisation layer, and one deployment, and the
token broker stays server-side by construction. A public API can be added later beside
Inertia; removing a leaked-token surface afterwards is far harder.

### 4.1 Module map

```
app/Modules/
  Identity/       Entra sign-in, tenant onboarding, consent, token broker, roles
  Readiness/      Tenant preflight probes, capacity checks, admin-role checks
  Environments/   Workspace lifecycle, DEV/TEST/PROD, capacity assignment
  Sources/        Source register, connections, gateway and connectivity records
  DataPlatform/   Lakehouse, medallion layers, warehouse, shortcuts, mirroring
  Ingestion/      Pipelines, copy jobs, incremental strategy, schedules, retries
  Transformation/ Silver/Gold logic, entity standardisation, keys, data quality
  Modelling/      Semantic model, relationships, measures, naming, RLS/CLS
  Semantics/      Business glossary, descriptions, synonyms, AI instructions, verified answers
  Agents/         Data Agent definition, sources, instructions, examples
  Evaluation/     Ground-truth bank, test runs, accuracy scoring, security tests
  Conversation/   Copilot Studio guidance, channels, sharing, routing tests
  Observability/  Capacity metrics, pipeline runs, conversation quality
  Lifecycle/      Git integration, deployment pipelines, AI change process, go-live gate
  Governance/     Audit trail, sensitivity labels, lineage register, exceptions
  Platform/       App users, roles, settings, recycle bin, notifications
```

Boundaries are enforced by a static dependency rule (Deptrac) in CI. Only
`Modules/Fabric` (inside `Identity`/shared infrastructure) may issue outbound Microsoft
calls.

### 4.2 The Blueprint engine

The 80 steps are not 80 screens. They are data.

```
Project  (one customer initiative)
  └── Blueprint            versioned declarative target state
        └── Stage          one of the 14 clusters
              └── StepRun  one of the 80 steps, tier A|B|C
                    └── Operation   one idempotent API call + its long-running poll
```

`StepRun` states: `blocked -> ready -> running -> awaiting_input -> awaiting_verification
-> verified -> failed -> skipped(exception)`.

Properties this buys:

- **Idempotency.** Every Operation carries a deterministic idempotency key, so a resumed
  run never creates a duplicate Fabric artifact.
- **Resumability.** A failed step resumes; the run does not restart.
- **Drift detection.** Re-running a verification probe detects that Fabric no longer
  matches the Blueprint.
- **Templating.** An industry starter blueprint is a seed record, not new code.
- **Traceability.** Requirement -> step -> operation -> audit event is one join.

### 4.3 Long-running operations

Fabric returns `202 Accepted` with a `Location` and `Retry-After` for most meaningful
work. One shared poller handles all of it: an Operation dispatches, stores the operation
URL, and **re-queues itself** with the advertised delay. Nothing sleeps inside a request
or a worker. This is what makes the system survivable on constrained hosting.

## 5. Microsoft integration surface

| Concern | Endpoint / mechanism | Notes |
|---|---|---|
| Sign-in | Entra ID OIDC (authorization code + PKCE) | Multi-tenant app registration; admin consent per customer tenant |
| Delegated Fabric calls | On-behalf-of token exchange | Preserves the user's own Fabric permissions — required for honest RLS behaviour |
| Automation | Service principal (client credentials) | Only where the endpoint supports it; probed per release |
| Fabric items | `api.fabric.microsoft.com/v1` | Workspaces, capacities, items, item definitions (base64 TMDL/TMSL/JSON), jobs, git, deployment pipelines |
| Power BI | `api.powerbi.com/v1.0` | Refresh, RLS role membership, dataset users, embed tokens |
| OneLake | `onelake.dfs.fabric.microsoft.com` | Table and file inspection, previews |
| Capacity | Azure Resource Manager, `Microsoft.Fabric/capacities` | SKU verification (F2+), pause/resume, cost signals |
| Tenant settings | Fabric admin APIs (read) + portal deep link (change) | Tier B: verify by read-back |
| Copilot Studio | Portal authoring + deep link; Power Platform environment APIs | Tier B |

Token handling: refresh tokens are stored encrypted at rest under `APP_KEY`, scoped to a
tenant and a user, never logged, never rendered, and never sent to the browser. Access
tokens are held in memory for the duration of a job only.

## 6. Data model (first cut)

`tenants` · `users` · `roles` · `role_user` · `entra_connections` ·
`fabric_capacities` · `projects` · `blueprints` · `blueprint_versions` · `stages` ·
`steps` (catalogue of the 80) · `step_runs` · `operations` ·
`environments` (DEV/TEST/PROD) · `workspaces` · `data_sources` · `connections` ·
`gateways` · `lakehouses` · `medallion_layers` · `warehouses` · `pipelines` ·
`pipeline_runs` · `transformations` · `quality_rules` · `quality_results` ·
`semantic_models` · `model_relationships` · `measures` · `field_metadata` ·
`rls_rules` · `cls_rules` · `glossary_terms` · `ai_instructions` · `verified_answers` ·
`data_agents` · `agent_sources` · `agent_examples` · `ground_truth_questions` ·
`evaluation_runs` · `evaluation_results` · `conversational_apps` · `channels` ·
`access_grants` · `capacity_metrics` · `conversation_reviews` · `git_bindings` ·
`deployment_pipelines` · `change_requests` · `signoffs` · `audit_events` ·
`exceptions` · `deleted_records` (recycle bin)

Tenant isolation is enforced by a global query scope plus a test suite that deliberately
attempts cross-tenant reads.

## 7. Roles

The three confirmed personas map onto the five-tier baseline as follows. **Labels to be
confirmed by the technical lead.**

| Baseline tier | SemantIQ role | Persona | Reaches |
|---|---|---|---|
| system admin | **Platform Administrator** | Administrator | All clusters including `System Administration` — Entra/Fabric connections, capacity, integration settings |
| admin | **Tenant Administrator** | Administrator | `Workspace`, `Compliance`, `Application Administration`; permanently deletes application records |
| team / collaborator | **Lead Data Engineer** | Data Engineer | `Workspace`, `Compliance`; owns blueprints for their team |
| self / contributor | **Data Engineer** | Data Engineer | `Workspace`; own projects and step runs |
| self-view / read-only | **Business User** | Business User | `Workspace` read-only: conversational app, glossary, published answers |

Cluster grants follow the default and are narrowed, never widened.

## 8. Non-functional requirements

| Area | Requirement |
|---|---|
| Tenant isolation | Global scope on every tenant-owned model; negative cross-tenant tests in CI |
| Secrets | Encrypted at rest; masked in UI as an "encrypted at rest" badge; never in drafts |
| Audit | Every Microsoft mutation records actor, tenant, target, payload digest, correlation ID, outcome |
| Rate limits | Per-tenant concurrency cap and exponential backoff with jitter on 429/503 |
| Idempotency | Deterministic keys on every Operation |
| Data residency | Customer data stays in the customer's OneLake. SemantIQ stores metadata, not business rows |
| Cost visibility | Capacity CU consumption surfaced per project and per stage |
| Accessibility | WCAG AA, per the approved design system |
| Observability | Structured logs with correlation IDs; failed-operation register |
| Recoverability | Soft delete to recycle bin; permanent delete gated to admin |

## 9. Deployment feasibility on the confirmed target

**Answer: yes — and the hosting mechanism is already established in this repository.**

**Verified from the repository, not assumed:** `.github/workflows/deploy.yml` on `DEV`
deploys to **cPanel over SSH** on every push to `DEV`, using `rsync -az --delete` from a
GitHub Actions runner, with `composer install --no-dev` and `npm run build` performed in CI
(PHP 8.5, Node 24) and `node_modules` removed before transfer. `.env` and `storage/` are
excluded from the sync and remain server-managed; the job is bound to the `development`
GitHub environment with `permissions: contents: read`. This settles the question below.

AlmaLinux 9.8, MySQL 8.0.46, PHP 8.4, Laravel 13 and React 19 are a sound stack for this
application. The question is not the OS — it is **whether the account gives root or
long-running process control**.

### 9.1 What the application genuinely requires

| Requirement | Why |
|---|---|
| Valid HTTPS on a stable hostname | Entra ID redirect URIs must be HTTPS; sign-in fails otherwise |
| Outbound HTTPS (443) to Microsoft | `login.microsoftonline.com`, `api.fabric.microsoft.com`, `api.powerbi.com`, `management.azure.com`, `*.onelake.dfs.fabric.microsoft.com`, `*.powerplatform.com` |
| A way to run queued work every minute | All Fabric work is queued; nothing runs inline |
| Cron | Laravel scheduler, poll re-dispatch, metric collection |
| MySQL 8 | Confirmed available |
| Node at build time only | Assets are compiled in CI and deployed as files |

### 9.2 Two hosting profiles

| | **Profile A — VPS / Dedicated with root** (recommended) | **Profile B — Shared / cPanel, no root** |
|---|---|---|
| Queue worker | `systemd` unit running `queue:work`, auto-restart | Cron each minute: `queue:work --stop-when-empty --max-time=55` |
| Scheduler | Cron `* * * * * php artisan schedule:run` | Same |
| Cache / session | Redis | Database / file |
| Realtime | Optional Reverb behind nginx | Not available — polling only |
| Long operations | Comfortable | Bounded by `max_execution_time`; hard requirement that no job exceeds ~50s |
| Verdict | Full functionality | **Fully functional**, with slower progress updates |

**Architectural consequence, and it is a good one:** because Profile B must work, the
application is designed with **no WebSocket dependency and no long-lived process
assumption**. Progress is delivered by polling a run-status endpoint (3s while a step is
running, backing off to 15s when idle), and every unit of work is a small, re-queueable
job. If you later move to Profile A or to Azure, nothing has to be redesigned — Redis and
Reverb become optimisations, not rescues.

**Profile B is confirmed** by the existing pipeline: deployment is cPanel over SSH with
rsync, which is a managed-hosting shape, and the workflow provisions no long-running
process. Nothing above the deployment layer changes as a result — which is the point of
having designed for it.

**Two gaps in the current pipeline, both verified by reading it:**

1. **No queue worker and no scheduler.** The workflow syncs files and fixes `storage/`
   permissions, and stops there. SemantIQ needs two cPanel cron entries — one running
   `php artisan queue:work --stop-when-empty --max-time=55` each minute, one running
   `php artisan schedule:run` each minute. Without them no Fabric operation ever executes,
   because every operation is queued by design. These are cPanel-side actions.
2. **No post-deploy release steps.** The workflow does not run `php artisan migrate --force`,
   `config:cache`, `route:cache` or `view:cache` after the sync. Add them as a final SSH step
   before the first release — noting that a migration is a separate action requiring
   explicit approval under the project's own deployment rule.

### 9.3 Known constraints to design around

1. **PHP execution limits.** Every Fabric call happens inside a queued job with an
   explicit timeout well under the host limit. Long-running Fabric operations are
   *polled across jobs*, never awaited.
2. **No inline sleeping.** A poll re-dispatches with a delay; it never blocks a worker.
3. **Egress restrictions.** Some shared hosts filter outbound traffic. This must be
   tested against the live Microsoft endpoints on day one of Phase 0, before any feature
   work. It is the single highest-risk unknown in the whole plan.
4. **Cron granularity.** One minute is the floor; the UI must not promise sub-minute
   step transitions on Profile B.
5. **Server timezone.** Store and compute in UTC; render in the tenant's timezone.
6. **Deployment method.** Git-based deploy with a release directory and an atomic
   symlink switch; `php artisan migrate --force` and `optimize` in the release script.
   Never build assets on the production host.

### 9.4 A note on scale

Profile B is appropriate for pilot and early customers. Once concurrent tenants run
ingestion and evaluation simultaneously, the database queue driver and one-minute cron
will become the bottleneck long before PHP does. Plan the move to Profile A, or to a
container host, as a **capacity decision on a known trigger** (sustained queue depth),
not as an emergency.

## 10. Delivery phases

| Phase | Outcome | Exit criteria |
|---|---|---|
| **0** Foundation | Laravel + Inertia + React skeleton, design system implemented, Entra sign-in, Fabric client, LRO poller, the two cPanel cron entries, post-deploy release steps, CI | A real Fabric workspace is listed in the UI from a real customer tenant, on the real host. **Note:** the repository has no `composer.json` or `package.json` yet, so the `DEV` deploy workflow currently fails at `composer validate` — Phase 0 must land the scaffolding for the pipeline to go green |
| **1** Blueprint engine | Step catalogue (80), stage/run state machine, tier A/B/C execution, verification probes, audit trail, polling progress UI | A blueprint runs end to end against a dev tenant with mixed A/B/C steps |
| **2** Readiness and environments | Clusters 1-2: preflight, workspace lifecycle, capacity, DEV/TEST/PROD | A customer can go from empty tenant to three assigned workspaces |
| **3** Data spine | Clusters 3-7: sources, connections, lakehouse, medallion, ingestion, transformation, quality, dimensional model | Bronze -> Silver -> Gold running on a schedule with quality gates |
| **4** Semantic layer | Cluster 8: model, relationships, measures, naming, descriptions and synonyms, RLS/CLS, Prep for AI, AI instructions, verified answers | A governed semantic model with a reviewed glossary |
| **5** Agent and evaluation | Clusters 9-11: Data Agent studio, ground truth, accuracy and security testing, publication, access | Measured accuracy against a ground-truth bank, with security validated per role |
| **6** Conversational app | Cluster 12: Copilot Studio guidance, routing and multi-turn tests, channels, sharing | A Teams-published agent answering governed questions |
| **7** Operate and govern | Clusters 13-14: monitoring, git, deployment pipelines, change process, go-live gate | Signed-off go-live with an active AI change process |

## 11. Open items for the technical lead

1. ~~Hosting profile A or B~~ — **resolved: Profile B, cPanel over SSH** (section 9.2).
   Outbound egress to the Microsoft endpoints in 9.1 **still needs testing from the cPanel
   host**, and remains the highest-risk unknown in the plan.
2. ~~The approved brand asset pack~~ — **resolved.** The assets now live at
   `doc/design-system/assets/`, and the application vendors its own copies at
   `resources/images/brand/` (Vite-managed, fingerprinted) and `public/brand/` (favicons,
   stable URL), so the app no longer depends on a documentation path.
3. Confirmation of the five role labels in section 7.
4. Whether a single Entra app registration is used for all customer tenants
   (multi-tenant) or one per customer.
5. Whether pilot customers already hold F2+ capacity, or SemantIQ must provision it.

---

*00-Solution-Architecture*
