# Understanding PROJECT-CONTEXT.md - Fill It Before You Start

Read this once and you will have everything you need. It explains every field in
`.claude/PROJECT-CONTEXT.md`: what the field means, why it matters, and what a good answer looks
like - with examples.

## Fill it first - how this file works

- `.claude/PROJECT-CONTEXT.md` is the record of confirmed facts about THIS project. Claude reads
  it before doing anything, and everything it builds stands on what is written there.
- **Fill it out BEFORE you start working with Claude, on a kickoff call.** The LD gets the whole
  chain on one call at project start - PL, LD, TD, and TA - and the group answers the fields
  together while the LD writes the confirmed answers into the file. This is a group job, not a
  solo one. If the group gets stuck on a field, take it to the TL.
- **The hierarchy table below is for afterward, not for kickoff.** Once the project is moving, a
  field may still be missing, may change, or may only become relevant later (deployment and
  security fields are usually settled closer to go-live, once the TL is involved). When that
  happens, escalate the single field one level at a time instead of calling another group meeting.
- **Claude asking is the fallback, not the plan.** If a field was missed at kickoff, Claude will
  stop and ask for it when the work needs it - and then fill it in from your answer. That safety
  net exists so work never proceeds on a guess; it is not an excuse to skip the kickoff call.
- A field you cannot answer stays `<ASK_DEVELOPER>` - escalate it per the hierarchy instead of
  guessing.
- Some sections are **already answered** for every project (marked "confirmed" or "approved" in
  the file). Leave them alone. They are listed at the bottom of this guide.

## Five rules for answering

1. **Never put secrets in an answer.** No passwords, tokens, keys, or full connection strings -
   not in chat, not in the file. Name *where* the secret lives instead ("the server `.env`, key
   `DB_PASSWORD`").
2. **Short and specific beats long and vague.** "Laravel 11 on PHP 8.4" is a great answer.
   "The usual stack" is not an answer.
3. **"I don't know" is a valid answer - guessing is not.** Escalate the field one level up the
   hierarchy and leave it `<ASK_DEVELOPER>` until the right person answers.
4. **Your answer becomes a fact.** Claude builds on it. If something changes later, update the
   file - do not let it go stale.
5. **If you are unsure, do not bluff.** A wrong "confirmed" fact is worse than an honest unknown.

## Who answers what - the hierarchy (for after kickoff)

Our chain is: **TA (Tech Associate) -> TD (Tech Developer) -> LD (Lead Developer) ->
PL (Project Lead) -> TL (Technical Lead)**.

This table is for what comes up after the kickoff call - a field nobody set yet, one that changes,
or one that only becomes relevant later. Escalate to the next level up instead of guessing - a TA
asks their TD, a TD asks their LD, and so on. Never skip past an unknown and never guess on
someone else's behalf. The same table also tells the LD who to pull into the kickoff-call
discussion for each field.

| Level | You own the answers for |
| --- | --- |
| TA (Tech Associate) | The current task in your words, the commands you actually run to test/build, local setup, versions you can read from the repo (lockfiles, composer.json/package.json) |
| TD (Tech Developer) | Stack and runtime versions, validation commands, entities and their screens, migrations, config variable names, charting/UI stack facts |
| LD (Lead Developer) | System boundaries, approved/banned libraries, data-access pattern, API conventions, logging/observability, navigation tree and access policies, secure coding conventions (escaping, CSRF, sanitizer, uploads, audit trail location, dependency audit tool) |
| PL (Project Lead) | Business purpose, primary users, scope and non-goals, success criteria, ClickUp linkage, confirmed decisions, role/permission decisions (Viewer scope, any Custom role) |
| TL (Technical Lead) | Security and identity conventions, compliance frameworks and who owns the evidence, deployment and hosting, production URLs and access, secret locations, anything production-impacting, any deviation from the approved standards |

---

## Section-by-section: what each field means

### Required Intake Status

The identity card of the project and the current task. Fill these in first on every new project -
Claude reads them before anything else and will stop to ask for any you leave out.

| Field | What it means | Example of a good answer |
| --- | --- | --- |
| Solution | Which solution this app belongs to in the org portfolio, as the company's dev task sheet names it | "Agentic CRM" |
| Module | The specific module inside that solution | "Product Manager, CLaaS Developer" |
| Sub-module | The sub-module inside that module, only where the dev task sheet has one | "Enterprise MDM" - or "none" |
| ClickUp URL | The one main link where all tasks for this project live - not a link to any single task, recorded as a markdown link | `[ClickUp Workspace](<CLICKUP_URL>)` |
| Business purpose | Why this app exists, in 1-3 plain sentences | "Track feature requests from Product Managers and prioritize the CLaaS Developer backlog; replaces the current spreadsheet" |
| Primary users | Who actually uses it, roughly how many | "Product Managers and CLaaS Developers, about 40 users" |
| Application type | The shape of the software | "Web application" / "REST API only" / "Web app + background jobs" |
| Backend stack and version | Server-side framework and language versions | "Laravel 11 on PHP 8.4" |
| Frontend stack and version | What renders the UI | "Blade + Tailwind" / "React 18 + Vite 5" |
| Database engine and version | Which database, which version | "SQL Server 2019" |
| Package manager/runtime versions | The tool versions builds depend on | "Composer 2.7, Node 20, npm 10" |
| Authentication model | How users sign in. This app provides authorization per feature either way | "Single sign-on through the company identity provider; server-side session after sign-in" |
| Data sensitivity/PII | What personal or sensitive data the app touches | "Requester names, emails, feature descriptions; no financial or health data" |

Validation commands and success criteria are no longer answered once at the project level - they
are per-task now, since what proves one task works is rarely what proves another one does. Each
task carries its own acceptance criteria in `doc/tasktracking/TASK_REGISTER.md`, generated from
the approved phase plan, and Claude sets out its validation approach at the task's first gate. See
Solution Planning And Task Tracking, below.

### Team

Who works on this project. Claude reads this to label the daily EOD status report it writes into
`doc/eod/` (see `.claude/rules/eod-reporting.md`). One row per person.

| Field | What it means | Example of a good answer |
| --- | --- | --- |
| Name | What the developer types when Claude asks whose session this is, so use the name they will actually give | "Ava Reyes" |
| Email | Their work email, for attribution only | `name@example.com` |
| Role | One of `TL` Technical Lead, `PL` Project Lead, `LD` Lead Developer, `TD` Tech Developer, `TA` Tech Associate | "LD" |
| Work start | The time their working day normally begins | "09:00" |
| Work end | The time it normally ends | "18:00" |
| Timezone | The zone those two times are in | "GMT+6" |
| From | Their first day on this project | "17 August 2026" |
| To | Their last day, left empty while they are still on it | "" |

Claude asks whose session it is rather than working it out, because on a shared Claude plan there is
nothing to work out: everyone signs in as the same account, shows the same account email, and reaches
the same GitHub login, and `git config` is a plain text file often set up identically across a team.
All of that identifies the team, none of it identifies a person. You type the name; this table
supplies the rest.

Work start, work end, and timezone are a declared schedule, not a measurement. Claude never records
hours worked, session counts, or activity levels in a report.

From and To are the person's period on the project, and they decide who appears in a given day's
report: a developer is seeded into a report only when its date falls inside their window. Someone who
joins mid-project starts appearing on their From date and not before, so earlier reports are not
rewritten to imply they were there. Someone who leaves, or moves to another project, gets their To
date filled and stops appearing the next day, while every report they were part of stays exactly as it
was. Keep the row after they leave; it is what keeps their past sections attributable. If they come
back, clear To rather than adding a second row, so one person keeps one row.

Row order here is the section order in every daily report, so keep it stable. Claude fills the row of
whoever is present and asks you to complete the rest; it will not invent a colleague's name, role,
hours, or dates.

### Solution Planning And Task Tracking

This section used to hold a sprint checklist typed in by hand. It now only points at where the
requirements, the plan, the tasks, and the persistent core repo live, and records the two planning
approvals. All of it runs through the Claude Solution Development Kit, and
`.claude/rules/solution-development-kit.md` is the authority for it.

Task status is never kept in this file. `doc/tasktracking/` is the one place it lives, so there is
nothing here to update at the start of a sprint. Tasks come from the approved phase plan through
`/generate-project-tasks`, not from a list somebody maintains.

| Field | What it means | Example of a good answer |
| --- | --- | --- |
| Requirement source | Where the approved requirements live. Read recursively, in whatever folders the project uses; any folder named `Archive` is ignored unless an active source references it or a human re-approves it | `doc/solutiondocument/` - leave the default |
| Phase plan location | Where `/generate-phase-plan` writes the planning package: `MASTER_PHASE_PLAN.md` plus one file per phase in `phases/`, at most five, `P01` to `P05` | `doc/phasedocument/` - leave the default |
| Standards baseline | The snapshot of the `.claude/` standards the plan was built against. A snapshot only; the live standards still win, and a material change means the plan is refreshed | `doc/phasedocument/PROJECT_STANDARDS_BASELINE.md` |
| `02_Gap_Conflict_Report.md` approval | Who approved the gap and conflict report, and when. It stays `not approved` until a human says so explicitly, and no application code is written before it is approved | "Approved by <name> (PL), 3 October 2026" |
| `MASTER_PHASE_PLAN.md` approval | Who approved the master phase plan, and when. Same rule: explicit, in words, and both approvals are needed before the task backlog is generated | "Approved by <name> (PL), 3 October 2026" |
| Task source of truth | `TASK_REGISTER.md` holds every task and its status, `TASK_BOARD.md` the progress view, `TRACEABILITY_MATRIX.md` the requirement to task trace | `doc/tasktracking/` - leave the default |
| Task event log | The append-only record of every task event, written only through `scripts/task-event.sh` with a real timestamp. Task time is computed from it, never guessed | `doc/tasktracking/task-events.jsonl` |
| Per-task data design evidence | Where each task's logical data proposal, physical table proposal, and schema verification record land when it touches persisted data | `doc/tasktracking/data-design/` |
| Persistent core repo | The compact living project memory, kept by `core-repo-maintainer` on accepted or verified state changes. It maps to the requirements and snapshots task status, and owns neither. A task reads its `14_AI_CONTEXT.md` and `PROJECT_MANIFEST.yaml` first | `doc/corerepo/` - leave the default |
| Infrastructure readiness record | Each infrastructure check with its status and evidence, kept by `infra-readiness-agent` in Phase 1 and whenever infrastructure materially changes. Nothing is READY without evidence | `doc/corerepo/02_INFRA_READINESS.md` |

A task moves `Planned -> Ready -> In Progress -> Implementation Complete -> User Testing ->
Accepted`, with `Blocked`, `Rework Required`, and `Cancelled` as the exceptions. Only
`Accepted` counts as finished, and only explicit user testing approval makes it so. Developers work
one Task ID at a time through `/execute-approved-task <TASK-ID>`; the six gates are described in
`00-read-first/01-getting-started/HOW_TO_USE.md`.

### Scope And Non-Goals

What we are deliberately NOT building, so Claude never wanders into it.

| Field | What it means | Example |
| --- | --- | --- |
| Declared non-goals (this phase) | Features that are out of scope on purpose | "No mobile app; no billing integration; no multi-language in phase 1" |
| Deferred work | Things agreed for a later phase | "Bulk CSV import moves to phase 2" |

### System Boundaries

Where this system ends and other systems begin.

| Field | What it means | Example |
| --- | --- | --- |
| What the system does | Its actual responsibilities | "Manages feature requests, approvals, and prioritization" |
| What the system does NOT do | Explicit exclusions | "Does not manage billing; does not manage the CLaaS product catalog master data" |
| External integration seams | Other systems it talks to, and how | "Reads the product catalog from the CLaaS Products API; sends mail through the org SMTP relay" |
| Module / layer map | The internal structure and who owns what | "Controllers -> Services -> Repositories; modules: Requests, Approvals, Reports" |
| Allowed dependency direction | Which layer may call which | "Controllers call services, services call repositories - never the reverse, no circular references" |

### Stack And Dependency Decisions

The rules about libraries and code organization, so every developer (and Claude) writes code the
same way.

| Field | What it means | Example |
| --- | --- | --- |
| Framework & stack choice rationale | Why this stack was picked (one line is enough) | "Laravel - team expertise and existing modules use it" |
| Approved libraries | Libraries you are allowed to use, and for what | "maatwebsite/excel for exports; spatie/laravel-permission for roles" |
| Banned libraries | Libraries you must not use, and why | "moment.js - dead project, use date-fns" |
| Formatter / linter configuration | The config file that decides code style - the formatter always wins over personal taste | "Laravel Pint, pint.json at the repo root" |
| Version-pinning / lockfile policy | How dependency versions are locked | "composer.lock and package-lock.json are committed; never delete them" |
| Data-access layer / pattern | The one approved way code touches the database | "All queries go through repository classes in app/Repositories; no raw queries in controllers" |

### UI Application Definition

**Most of this is already decided.** The look and feel - layout, both themes, colors, fonts,
logos, favicons, icon style - is the approved design system in `.claude/skills/ui-ux-design/`.
Claude will never ask you about colors or fonts, and you should never answer with new ones.

You only supply what is unique to YOUR app:

| Field | What it means | Example |
| --- | --- | --- |
| App name | The name shown in the top bar | "C2S Feature Desk" |
| App icon | The one file from the solution icon pack (`.claude/skills/ui-ux-design/assets/solution-icons/`, listed in `catalog.json`) that stands for this app: the icon of the most specific node the app is - its Feature, else its Sub-module, else its Module, else its Solution - or the nearest ancestor's icon when the app's own node is not in the catalog, else none. A node with children keeps its icon as `icon.svg` in its own folder, and a leaf is one file named after it in its parent's folder. You name the solution, module, sub-module, and feature; Claude asks and never guesses, invents, or borrows a sibling's icon. It sits left of the app name in the top bar and above it on the auth cards, always in the `--solution-icon` color | "agentic-crm/agentic-leads-generator.svg" - or "none" |
| Browser / title-bar name | The text in the browser tab (`<title>`) | "Feature Desk" |
| Tagline (optional) | A short line for login/empty states | "Feature requests, sorted." - or skip it |
| Brand-assets destination path | Where the six logo/favicon files (from `.claude/skills/ui-ux-design/assets/`) live inside YOUR project. Depends on your stack. Claude must never pick this itself | "public/images/brand" (Laravel) / "wwwroot/brand" (.NET) / "static/brand" (SvelteKit) |
| UI stack | The framework that renders the interface | "Blade + Tailwind" / "React 18 + MUI-free custom components" |
| Charting library | Only if the app has charts; it gets skinned with the approved tokens | "Chart.js" - or "no charts" |
| Sidebar menus | The features you place under the four fixed clusters, in top-to-bottom order - Workspace (day-to-day work), Compliance (logs, audits), Application Administration (the app's own admin), System Administration (config, integrations) - each feature a leaf or an accordion group (max 3 levels), with an icon idea and who can see it. Use only the clusters you need; you cannot add a fifth. In PROJECT-CONTEXT the four clusters are pre-listed, each defaulting to `<ASK_DEVELOPER>` - confirm which clusters the app uses and fill in their features | "Workspace: Dashboard, Feature Requests (All Requests, New Request, Recycle Bin); Compliance: Reports; Application Administration: Users; System Administration: Settings" |
| Access policies | Which roles see which cluster/feature | "Compliance -> Collaborator and Administrator only" |

Two UI subsections carry behavior rather than identity. Both hold behavior that is already required, so
what you are confirming is the naming and the storage, never whether the feature exists:

| Field | What it means | Example |
| --- | --- | --- |
| List query parameter convention | The URL query names a list uses for its search, sort, direction, page, size, and each facet, so a filtered view survives a refresh and can be pasted to a colleague. Pre-filled with `q`, `sort`, `dir`, `page`, `size`; replace them only if the project already has a convention | "keep the defaults" - or "we use `search` and `orderBy`" |
| Server-side sort and filter for paginated lists | Pre-confirmed as required. A paginated list sorts and filters on the server, so the matches on page three are reachable | "required" |
| Draft storage shape | Where a step-by-step form's resumable draft lives: a separate draft table, or the record's own table with a draft state. A draft in the live table keeps one row and one id but needs its required columns nullable until completion; a separate table keeps the live constraints strict | "separate `product_drafts` table" |
| Draft state value source | Whether the draft state comes from the platform's existing status dimension or a new one, so the value is a code and not free text | "reuse the platform status dimension" |
| Drafts in flight per user per flow | One resumable draft is the default. Several only if the flow genuinely needs them, and then the list is the resume surface | "one" |
| Fields excluded from a saved draft | Pre-confirmed: credentials, keys, tokens, and passwords are never persisted into a draft and are re-collected on resume | "all of them, always" |
| Autosave beyond the per-step save | Saving on each `Continue` is the floor. Say whether you also want an interval, on-blur, or on-close save on top | "on close as well" |

**Entities** are a data shape, not a screen. Each entity gets its own heading, not a table row -
`Columns` and the mapping notes can run long, and a table cell reads poorly once a list grows past
a few items:

```text
#### Entity: FeatureRequest (plural: Feature Requests)

- Database table(s): feature_requests
- Columns: id, title, status, requester_id, created_at
- Transaction / child table(s): feature_request_status_history
- Field -> column mapping notes: status field maps to the `state` column; no other renames
- Default list sort (column + direction): created_at desc
- List filter facets (the dimensions this list is narrowed by): status, requester
```

| Field | What it means | Example |
| --- | --- | --- |
| Entity (name, plural) | The thing the app manages, singular and plural | "FeatureRequest, plural Feature Requests" |
| Database table(s) | The actual database table(s) that store this entity | "feature_requests" |
| Columns | The columns Claude will actually work with | "id, title, status, requester_id, created_at" |
| Transaction / child table(s) | Any related detail, history, or line-item table, if one exists | "feature_request_status_history" |
| Field -> column mapping notes | Anything non-obvious about how entity fields map to columns | "status field maps to the `state` column; no other renames" |
| Default list sort | The order this entity's list opens in. Every list declares one, so the order never depends on what the database happened to return | "created_at desc" |
| List filter facets | The dimensions this list is narrowed by, which become the filter bar's facets. Pick the ones people actually filter on, not one per column | "status, requester" |

Add one heading block per entity. A table or column name written here is a reference only, never a
substitute for verification - Claude re-verifies every one against the project's schema source
before writing any code against it, per `.claude/rules/database-schema.md`. Naming
the table here just tells Claude where to look; it does not skip the lookup.

Two small role decisions are per-app (the role model itself is already approved; the PL confirms
these):

| Field | What it means | Example |
| --- | --- | --- |
| Viewer scope | What a Viewer may see: their OWN records, their TEAM's, or their Business Unit's | "Viewer sees their Business Unit" |
| Custom role | Only if this app genuinely needs a sixth role beyond the five-tier baseline - define its scope and grants first | "Auditor: read-only across all records, Compliance cluster only" - usually "not needed" |

### Authentication And Authorization

Authentication follows the model recorded under Required Intake Status. Your app provides authorization, per feature. Never put a client secret in this file.

| Field | What it means | Example |
| --- | --- | --- |
| Local session technology | What holds the signed-in user between requests. Entirely your choice | "Server session, Redis-backed" |
| Local session lifetime and expiry behavior | How long it lasts and what happens when it ends | "8 hours; on expiry, sign in again" |
| Where a sign-in attempt's outcome is recorded | A durable record in the security audit trail, one closed set of codes | "audit_log table, sign_in_outcome codes" |
| Where a failed sign-in lands | The page a user sees after any failed sign-in, with one generic message | "/signed-out with a generic notice" |

| Field | What it means | Example |
| --- | --- | --- |
| Feature registry storage | Where the list of grantable features lives | "Database table, through the confirmed migration tool" |
| Roles, grants, assignment storage | Where roles and who holds them live | "Same database" |
| Shared action vocabulary | The columns of the grant matrix. One list for all features, not one per feature | "view, create, edit, delete, export, approve" |
| Default role on first login | What a brand-new user gets. They are not an error; authentication already let them in | "Viewer" |
| Page a new user lands on when the default is no access | Only if your default is deliberately nothing | "/welcome, naming the admin to contact" |
| Tier that manages roles and grants | Defaults to admin and above | "Administrator" |
| Tier that manages the feature registry | System admin only. It describes what the app is, not how you use it | "System Administrator" |
| Grant ceiling | Always bounded by the actor: a role only up to their own tier, a grant only if they hold it themselves. Anything above their authority renders locked and survives their save | "enforced at the handler" |
| Grant-change audit location | Where a permission change is recorded, by whom, and with what authority they held at the time. It is part of the security audit trail | "audit_log table" |
| Documented exceptions | Anything you deliberately do differently in the feature-authorization rules | "none" |

### Security And Identity Conventions

References and names only - never the secrets themselves.

| Field | What it means | Example |
| --- | --- | --- |
| Secret manager / secret store | Where real secrets live | "Server environment variables managed in the hosting panel" / "Azure Key Vault" |
| CI credential model | How CI authenticates: short-lived/federated or static secrets | "GitHub Actions OIDC federated to Azure" / "no CI yet" |
| Token signing scheme | How auth tokens are signed | "RS256 (asymmetric)" / "handled by Entra ID - not our code" |
| Authorization scope convention | The naming pattern for permissions. With per-feature authorization this is the feature code plus the action | "feature.action, for example invoices.approve" |
| Password hashing algorithm | Only if the app stores passwords itself, under its recorded authentication model | "argon2id" |
| Token / session lifetime | How long sign-ins last and how they renew | "8-hour session, sliding renewal on activity" |
| Encryption in transit | Minimum TLS version | "TLS 1.2 minimum, HTTPS only" |
| Encryption at rest | What is encrypted in storage and how | "SQL Server TDE on the database" |
| Key / secret rotation schedule | How often keys/secrets rotate | "Annually, or immediately on suspicion" |
| Field-level encryption targets | Any columns encrypted beyond at-rest | "None" - most apps |
| AI/LLM feature conventions (+ sub-items) | Only if THIS app ships AI features to users. If not, one answer covers it all | "No AI/LLM features in this app" |

### Secure Coding Conventions

How this app keeps input from turning into code and output from turning into script, and where
security-relevant actions are recorded. Mechanisms and names only - never a secret.

| Field | What it means | Example |
| --- | --- | --- |
| Templating engine and output escaping | What writes the HTML, and whether it escapes values by default | "Blade; `{{ }}` escapes, `{!! !!}` is never used on user content" |
| HTML sanitizer | The one approved library for rich text, or none | "None - rich text renders as plain text" / "HTMLPurifier with the approved tag allow-list" |
| CSRF protection mechanism | How state-changing requests authenticated by the session cookie are protected. A route authenticated only by a bearer token needs none | "Framework CSRF middleware on every POST, PUT, PATCH, and DELETE web route" |
| Standing CSRF exemptions | Always signature-verified inbound webhook routes. Anything else is listed under Documented exceptions | "the payment provider webhook" |
| Session cookie attributes | The flags on the session cookie. `Strict` breaks a sign-in callback when the identity provider is on another site | "HttpOnly, Secure, no Domain attribute, SameSite=Lax" |
| Content security policy and security headers | The policy and headers the app sends, if any | "No CSP yet; framing denied, nosniff, strict-origin-when-cross-origin referrer policy, HSTS in production" |
| Origins allowed to read responses cross-origin | Which other sites may read the app's responses from a browser. None means same origin only | "None - same origin only" |
| Supplied-URL fetches (+ sub-item) | Whether the server fetches a URL somebody typed in, such as a webhook target, an import source, or an AI model endpoint, and which internal addresses it may reach. Internal addresses are refused unless the environment configuration names them | "Yes, webhooks and the AI model catalog; internal: the self-hosted model at `<MODEL_HOST>`, named in `ALLOWED_INTERNAL_HOSTS`" / "No" |
| File uploads (+ sub-items) | Whether the app accepts files, the allowed types and size, where they are stored and how they are served, and any scanner | "Yes: PDF and PNG, 10 MB, private disk outside the web root, PDFs served as a download, no scanner" / "No uploads" |
| Production error display | Always debug off; an unexpected error shows a generic message and a correlation id, with detail in the log | "debug off; generic message plus correlation id" |
| Security audit trail location | Where sign-in outcomes, role and grant changes, account status changes, permanent deletes, exports, and configuration changes are recorded, refused attempts included | "audit_log table" |
| How the audit trail is kept append-only | Whether the database itself refuses updates and deletes, or only the code avoids them. Until the grant is in place, Claude reports it as convention only | "The app's database user can insert and select but not update or delete on audit_log" |
| Audited actions beyond the defaults | Extra actions this app must record, including records whose reads are audited | "Viewing a salary record" / "None" |
| Dependency vulnerability audit tool | The command that checks the lockfile for known advisories | "composer audit and npm audit, run locally and in CI" |
| Blocking advisory | Always critical, high, or unrated. It blocks whether or not a fix exists, unless the fixed version is taken or an exception is recorded | "critical, high, or unrated" |
| Compliance frameworks and evidence owner | Any framework the organization pursues, any agreed mapping of controls to its criteria, and who owns the evidence. Claude uses it to name the control a change supports, never to call the app compliant | "SOC 2 Type II, evidence owned by the compliance team" / "None" |
| Documented exceptions | Approved deviations from the secure coding and dependency vulnerability rules: who accepted each and when, why, the follow-up and its owner, and when it is revisited | "None" |

### AI Model Catalog

Only if the app calls a model at runtime. If it does not, the first answer covers the whole section.
These fields hold confirmed defaults, allowed ranges, and allowed shapes. The values an administrator
changes later on the AI screens, such as a step's current route, live in the app, never here.

| Field | What it means | Example |
| --- | --- | --- |
| Does the app call an AI model at runtime | Whether any feature sends a request to a model while the app runs | "Yes, invoice drafting" / "No" |
| Catalog storage location | Where the model records live: a database table, a versioned configuration file, or the settings provider | "The `ai_model` table in the app's own store" |
| Providers and model ids in use | Each provider, and the dated snapshot model id, never a moving alias | "`<PROVIDER_A>`, `<DATED_SNAPSHOT_MODEL_ID>`" |
| Environment source for `{{env.NAME}}` | Where a record's `{{env.NAME}}` placeholder reads its value from | "Process environment" |
| Per-call timeout and connect timeout | One project-wide value the engine applies to every call | "60 seconds total, 5 seconds to connect" |
| Retry policy and dedupe key | When a failed model call is retried, and the key that stops a retry from billing twice | "No automatic retry" |
| Cost currency and rounding | One currency for every price, and how many decimals show | "USD, 4 decimals on screen" |
| Who manages the catalog | Defaults to System Administrator. The records hold provider keys | "System Administrator" |
| Default route and allowed records per step | Each step's default route shape, and the catalog records each role may use. The live route is changed on the AI Routing screen | "Outline: single, `<RECORD_NAME>`" |
| Eval result store for route changes | Where the pass or fail per step, catalog record, and prompt version is recorded. The routing screen offers only records that passed | "The `ai_eval_result` table" |
| Multi-call patterns allowed | Which patterns the app may use: dual drafting, cross critique, a judge with its mode, and pre-drafting. None is on by default | "Single only" |
| Repair retry | Whether output that fails validation gets one more try, and how many | "One" |
| Model retirement alert lead time | How long before a provider retires a model administrators are warned | "60 days" |
| Stream keep-alive interval | How often a long AI stream sends a keep-alive while nothing else is sent | "15 seconds" |
| Grounding verification level per feature | How hard an answer drawn from company data is checked: the citation and quote check, stronger checks, or human review | "Quote check; human review for approvals" |
| Eval attack corpus and red-team cadence | Where the attack cases live, and how often people who did not build the feature try to break it | "`tests/ai/attacks`, quarterly" |
| Owner of the AI feedback queue | Who reviews reported AI output. The queue sits in Application Administration > AI Oversight, so the owner holds a tier that reaches it | "Administrator" |
| AI inventory location and risk-level scale | Where every AI feature is listed, and the scale its risk level uses | "`doc/corerepo/09_AI_COMPONENT_REGISTER.md`; low, medium, high" |
| Documented exceptions | Any deliberate departure from the catalog pattern | "None" |

### AI Guardrails

Only if the app calls a model at runtime. The checks between a user and the model, and between the
model and wherever its output lands. Like the catalog section, this holds confirmed defaults,
allowed ranges, and allowed shapes. The word lists and the live settings are data an administrator
changes on the guardrail screens, never written here.

| Field | What it means | Example |
| --- | --- | --- |
| Required layers | The checks that are always on and render locked on the Guardrails screen | "Length cap, rules, moderation, output check" |
| Optional layers | The checks an administrator may turn off. There is no master switch: stopping AI for everyone is the kill switch's job | "Small talk, relevance" |
| Moderation and relevance checks | The default catalog record each check calls, and the records allowed for it. The record is chosen on the Guardrails screen | "`<MODERATION_RECORD>`" |
| Fail mode per check | What happens when a check cannot run. Closed means the message is blocked; open means it passes and the gap is recorded. A screening refusal always blocks, and injection screening for an agent with write tools always fails closed | "Moderation open, output check closed" |
| Strictness levels | The levels on the Guardrails screen, and what each one sets | "Standard: the confirmed default thresholds" |
| Content category set and strike weights | The categories for what a user wrote, and how many strikes each counts as. A kill switch, pause, limit, or length-cap refusal never counts | "Abusive 3, injection 2, off-topic 1, spam 1" |
| Strike threshold and pause | How many strikes pause a user's AI access, for how long, and whether the count resets after | "5 strikes, 60 minutes, reset" |
| Streaming mode per feature | Buffered holds output until the check passes. Retractable shows it as it arrives and takes it back on a hit | "Buffered" |
| Limits | The per-user rate in user requests, the per-record cap, the daily budget in calls, tokens, and cost, the alert percent, and the workflow caps | "20 requests per hour per user, 50 per day in the project currency, alert at 80%" |
| Quarantine of abusive text | Whether text blocked as abusive is kept for review, with its access and retention. Off unless confirmed | "None" |
| Link allow-list for model output | Where a link written by the model may point. A link that traces to a source the app supplied needs no entry | "The app's own help pages" |
| Who may add retrieval content | Who adds documents the model can draw on, and how an entry is removed and re-indexed | "Administrators, through the document library" |
| Prompt-leak canary | Whether a marker is placed in prompts to show a leak. Default none | "None" |
| Kill switch owner and fallbacks | Who may turn AI off, and the non-AI path and message per feature. The owner holds the tier that reaches the Kill Switches screen | "System Administrator; drafting falls back to the blank form" |
| AI disclosure requirement | Whether users must be told they are dealing with AI, how AI content is labeled, and any marking a regulation requires. When unconfirmed, AI content does not reach users | "Label every generated item" |
| Who manages guardrail settings | Defaults to System Administrator | "System Administrator" |
| Settings storage shape | Typed values, or one row per settings area | "One row per settings area" |
| Metrics destination and annotate-mode policy | Where block rates and the rest go, and when a check runs in annotate mode first. Annotate covers only a check added to an enforcing pipeline or a changed threshold, never the baseline layers | "Existing metrics stack; one week in annotate" |
| Documented exceptions | Any deliberate departure from the guardrail rules | "None" |

### Migrations And Data Change Workflow

Schema design always goes through the shape preview and confirmation in `.claude/rules/database-schema.md`
first - this section is only about how confirmed changes are delivered as files.

| Field | What it means | Example |
| --- | --- | --- |
| File-based migration tool | The migration system, if the project uses one | "Laravel migrations in database/migrations" - or "none" |
| Migration file naming convention | The naming/ordering pattern | "Framework default timestamps (YYYY_MM_DD_HHMMSS_name)" |

### Analytics And Semantic Model

Only needed once the project stores data. These answers shape the tables, so they are worth settling
before the first schema proposal rather than after. Claude asks the per-feature analytics questions
separately, in the session, and never invents them.

| Field | What it means | Example |
| --- | --- | --- |
| Feeds a semantic model / BI layer | Whether anything reports on this data beyond the app's own screens | "Yes, a Power BI semantic model" / "Not yet, but the tables must not block it" |
| Semantic model / BI tool and owner | The product and the person or team who builds the model | "Owned by the data team; they build the model from our documented structure" |
| How reporting reads the data | Whether reports hit the operational tables, a replica, an extract, or a warehouse | "Read replica, refreshed hourly" / "directly against the operational database for now" |
| Reporting time zone and day boundary | The zone a "by day" count is grouped in, and where a day starts | "Asia/Singapore, midnight to midnight" |
| First day of week and fiscal year start | Week and fiscal calendar semantics for period reporting | "Week starts Monday; fiscal year starts 1 April" |
| Timestamp storage convention | How timestamps are stored, so they are unambiguous | "UTC, timezone-aware, converted at the reporting layer" |
| Surrogate / business key convention | The key style for new tables, and where business keys sit | "Single-column identity surrogate key; external reference in its own column" |
| Conformed dimensions the platform has | The shared code lists a new feature must reuse rather than clone | "Shared source, status, country, and currency lists; owner comes from the user table" |
| History / event / snapshot retention | How long transition, attempt, and snapshot rows are kept | "Same 7 years as operational data" / "attempts pruned after 2 years" |
| Where the question set is recorded | The home for each feature's confirmed analytics questions | "In the feature's knowledge-base write-up" |
| ERD and deliverable location | Where the data model and ERD live, and in what format | "Mermaid ERD in the knowledge-base feature file; column metadata in the table dictionary" |
| Approved materialized aggregates | Any pre-computed totals the project has approved, and how each rebuilds | "None" - most projects |
| Documented exceptions | Any approved deviation from the analytics-ready rules | "None" |

### Quality, Observability, And Operability

The quality bar and how the running app reports on itself.

| Field | What it means | Example |
| --- | --- | --- |
| Minimum test coverage bar | The coverage number, if the team enforces one | "No formal percentage; every business rule needs a test" / "70% lines" |
| Gating mechanism | How unfinished work stays switched off in shared code | "Config-based feature flags (config/features.php)" |
| Structured logging format | The log format and library | "JSON lines via Monolog" |
| Metrics / tracing destination | Where metrics and traces go | "None yet" / "Application Insights" |
| Health / readiness endpoints | The URL(s) that report app health | "/up returns 200 when app and DB are healthy" |
| Per-environment config keys | Which variable NAMES differ per environment (names only, no values) | "APP_URL, DB_HOST, MAIL_HOST, ENTRA_TENANT_ID" |

### API / Interface Conventions

Only if the project exposes or consumes an API.

| Field | What it means | Example |
| --- | --- | --- |
| Interface paradigm | The API style | "REST with JSON" |
| Resource / operation naming style | URL and operation naming | "Plural kebab-case resources: /api/v1/feature-requests" |
| Error contract format | The single error shape every endpoint returns | "problem+json with a correlationId field" |
| Version scheme | How the API is versioned | "URL prefix /api/v1; breaking changes bump the version" |
| Standard header names | Headers every request/response carries | "X-Correlation-Id; Idempotency-Key on retryable POSTs" |
| Pagination style and page-size cap | How lists paginate and the maximum page size | "page + per_page query params, max 100" |
| Authoritative contract spec | The single spec file the API is generated/validated from | "OpenAPI 3.1 at docs/openapi.yaml" |

### Configuration Contract

| Field | What it means | Example |
| --- | --- | --- |
| Config example-file path | The committed example listing every variable name (placeholders only) | ".env.example" |
| Config library / loader | How config is read | "Framework config/ + env()" |
| Config precedence order | Which source wins when values overlap | "Process env > .env file > config defaults" |
| Enumerated config variables | The list of variable names the app reads (names only) | "APP_URL, DB_HOST, DB_DATABASE, MAIL_HOST, ENTRA_CLIENT_ID" |

### Deployment Policy + Required Deployment Details

Everything about where and how the app ships. This is TL territory - the TL confirms these once
per project; anyone below that level escalates instead of guessing.

| Field | What it means | Example |
| --- | --- | --- |
| Deployment target / hosting provider | Where the app runs | "Plesk shared hosting" / "Azure App Service" |
| Hosting account/project/resource | The account, project, or resource identifier on the host (a placeholder name, never a secret) | "Plesk subscription <HOSTING_RESOURCE>" / "Azure resource group rg-featuredesk" |
| Deployment method + who runs it | How a release physically gets there | "SSH: git pull, composer install --no-dev, php artisan migrate - only the TL deploys production" |
| Source control provider | Where the repo lives | "GitHub, org CLaaS2SaaS" |
| GitHub repository URL | The plain repository URL (never a token-bearing URL), recorded as a markdown link | `[GitHub Repository](<GITHUB_REPO_URL>)` |
| CI/CD or pipeline | Automated pipeline, if any | "GitHub Actions runs tests on PR; no auto-deploy" / "none" |
| Runtime/build/deploy model | How the artifact is built and served | "PHP-FPM behind Apache; assets built with Vite at deploy time" |
| Infrastructure-as-code | Only if infra is defined in the repo | "None" - most projects |
| Local development support | How developers run it locally | "Local Apache + PHP 8.4 DevStack; SQL Server dev instance" |
| Production URL and subdirectory | The live address, recorded as a markdown link, plus the subdirectory if any | `[Production Site](<PROD_SITE_URL>)`, served from / |
| Environment site URLs (DEV / QA / STAG / PROD) | The deployed website URL per environment branch, recorded as a markdown link per environment; keep the `<..._SITE_URL>` placeholder until that environment has a live site | `` `DEV`: [Development Site](<DEV_SITE_URL>) `` |
| Web root / artifact path / entry point | Where on the server the app lives | "httpdocs/ with public/ as the document root" |
| Runtime version on target | The runtime the SERVER actually has | "PHP 8.4 confirmed on the host" |
| Package manager availability on target | Can you run composer/npm on the server? | "Composer yes; Node no - build assets before upload" |
| Background processing / scheduler | Cron or queue support on the host | "Plesk scheduled task runs artisan schedule:run every minute" |
| Rollback and monitoring/log access | How to undo a bad deploy and where logs are | "Redeploy previous tag; logs in storage/logs + hosting panel" |
| Required .env keys | The variable names production needs (placeholders only) | "DB_HOST=<DB_HOST>, DB_DATABASE=<DATABASE_NAME>, MAIL_HOST=<MAIL_HOST>" |

### Required Validation Details To Ask For

The commands Claude runs (or asks you to run) before claiming work is done.

| Field | What it means | Example |
| --- | --- | --- |
| Syntax/type/static checks | Static analysis commands | "vendor/bin/phpstan analyse" - or "none" |
| Lint/format commands | Style check commands | "./vendor/bin/pint --test" |
| Unit/integration/e2e commands | The test suites | "php artisan test" |
| Build/package commands | What must compile cleanly | "npm run build" |
| Security checks | The dependency audit, static security analysis, and secret scan, and where each runs | "composer audit and npm audit in CI; no static security analysis yet" |
| Deployment smoke test | The quick manual check after a deploy | "Open /up, sign in, load the dashboard" |
| Known validation limitations | What CANNOT be verified locally | "No e2e suite; SSO only testable on staging" |

### Application Database Connection

Only if the app itself opens a database connection at runtime (most do). Schema design is covered
by Database Schema below.

| Field | What it means | Example |
| --- | --- | --- |
| Does the app connect directly | Yes or no | "Yes" |
| Database engine | What it connects to | "SQL Server 2019" |
| Host / name / user placeholders | Placeholder names only - never real values | "DB_HOST=<DB_HOST>, DB_DATABASE=<DATABASE_NAME>, DB_USERNAME=<DATABASE_USER>" |
| Schema/database owner | Who owns the database | "Platform team" |
| Where connection secrets live | The env keys and their home | "Server .env, managed in the hosting panel - values never in the repo" |

---

## Sections you will (almost) never be asked about

These are already answered for every project, so PROJECT-CONTEXT.md lists only the bare confirmed
values with no explanation. This is where those values are explained. Claude reads them; you do not
need to do anything, but read the row if you want to know what a confirmed value means.

### UI look and feel

Layout, both themes, colors, fonts, logos, favicons, and icon style are the approved design system
in `.claude/skills/ui-ux-design/`. Used by default in every app; only an explicit developer request
changes it. You never answer color or font questions.

### Feature toggles - all On

| Toggle | What "On" means |
| --- | --- |
| Customizable dashboard | User-arrangeable, per-user-persisted widget grid on the dashboard |
| Notifications | In-app notifications via the top-bar bell (unread dot + panel, newest first); other channels like email only when a feature needs them |
| Authentication | Sign-in follows the authentication model recorded under Required Intake Status; your app provides authorization, per feature - see Authentication And Authorization above |
| Recycle bin | Soft-delete by default: destructive deletes route to a recycle bin with a worded confirmation; per-user restore; only admin permanently deletes |
| Audit log | Records who did what and when, refused attempts included, for create, update, delete/restore, permission, and configuration changes, in the security audit trail per `.claude/rules/secure-coding.md` (metadata only, no secrets); retention follows Data lifecycle |

### Roles & tenancy

Approved baseline: System Administrator / Administrator / Collaborator / Contributor / Viewer,
single-tenant, with an Administrator (org root) -> Business Unit -> Team hierarchy, and System
Administrator above it owning platform configuration rather than records. Scopes: Administrator sees
all records and is the role that permanently deletes application records; Collaborator sees own plus
their Business Unit; Contributor sees own or directly assigned; Viewer is read-only.

These five ship as the default roles, and a role is a named set of feature grants rather than a fixed
list of clusters. The tier decides record scope; the grant decides whether a user reaches an
individual feature. Adding a role or moving a grant is something an administrator does in a screen,
so it is not a question asked at intake. What they can move is bounded, though: opening those screens is
permission to administer access, never to award access they do not hold, and a role or grant above
their own authority is shown locked and kept as it is when they save. What you are asked for is in Authentication And
Authorization above: where the registry and grants are stored, the default role a first-time user
gets, and which tier manages them. The Viewer's scope (OWN / TEAM / Business Unit) and any optional
Custom role are still per-app - see the two role-decision rows under UI Application Definition
above.

### Resilience thresholds - all "none yet"

No outbound integration, retry, circuit-breaker, or async code exists yet, so no thresholds are set.
Each value (per-dependency timeout, retry attempts/backoff/jitter, breaker trip/reset, dead-letter
and replay location) is filled into PROJECT-CONTEXT in the same change that introduces the first
outbound call, retry, or async job - never afterward. The `Idempotency-Key` header for retryable
mutations is documented but not yet enforced in code.

### Data lifecycle

Approved default is a 7-year retention window for operational data, backups, and audit/compliance
logs. Nothing else is in scope: no data sensitivity classification scheme (data is handled
uniformly), no applicable privacy regime, and no recovery objectives (RPO/RTO), restore-test
cadence, or failover/disaster process. Override a value per project only when a data class genuinely
needs a different one.

One field here does need an answer: **abandoned step-by-step form draft retention**. A draft that
nobody came back to holds whatever the user typed, indefinitely, so say how long one is kept before
it is purged. It is normally far shorter than the operational window.

An app that calls a model at runtime needs one more: **classification and retention of the AI rows**,
the guardrail verdicts, strikes, pauses, feedback, background job state, and pre-drafts. Verdict and
feedback rows can hold excerpts of what users wrote, so they get a confirmed class and period rather
than the operational default by accident.

### Operations / incident response - all "none yet"

The project does not yet run a formally operated production service, so there are no operational
bars. Service-level objectives, alert thresholds, incident severity taxonomy and response times,
the on-call/escalation path, and failure-mode runbooks are all defined before the first production
go-live. An app that calls a model also maps its AI incidents, such as prompt injection, data leaked
through output, runaway cost, and a provider incident or model retirement, to that severity
taxonomy.

### Git and review knobs

Lightweight workflow, nothing enforced by branch protection yet: commits are not required to be
signed; merge strategy is chosen per Pull Request (merge, squash, or rebase); the review bar is one
superior's approval by convention (the author opens the PR and notifies their superior); there is no
CODEOWNERS file, no working-branch lifetime cap, no fixed rebase cadence, and no review-response
SLA. The DEV -> QA -> STAG -> PROD promotion flow and PR practice from
`.claude/rules/git-branching-release.md` still apply; each knob gets a firm value here when the team
formalizes it.

### Database Schema

Claude verifies tables and columns against the project's schema source and never asks for table/column
details it can read there. Confirm these per app: the schema source of truth (the database's own
metadata where approved read access exists, or the committed migrations and schema definition); the
schema name where the engine uses one; the table name prefix convention (the module/domain prefix on
table names, or `none`); and the table/column naming casing style (snake_case, camelCase, PascalCase,
or lowercase-run-together, plus how multi-word names are joined). The base name follows the confirmed
casing, and Claude still verifies the real name against the schema source, per
`.claude/rules/database-schema.md`.

### Knowledge Base Policy

Rules for Claude's own behavior - nothing for you to answer.

---

## Good answer vs bad answer

| Claude asks... | Bad answer | Good answer |
| --- | --- | --- |
| "What is the backend stack?" | "the usual" | "Laravel 11 on PHP 8.4" |
| "How is this deployed?" | "like always" | "SSH to the Plesk host, git pull, composer install --no-dev; only the TL deploys production" |
| "Where do DB credentials live?" | *(pastes the password)* | "Server .env, key DB_PASSWORD - never in the repo" |
| "What is the page-size cap?" | "whatever is fine" | "max 100 per page" |
| "Which roles see Reports?" | "everyone I guess" | "Collaborator and Administrator only - same as the Compliance cluster" |
| "What is the coverage bar?" | *(silence)* | "I don't know - escalate to the LD" |

One habit covers everything: **answer like you are writing it on a whiteboard the whole team will
follow for a year.** Short, exact, no secrets.
