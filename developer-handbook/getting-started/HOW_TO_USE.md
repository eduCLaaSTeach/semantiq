# How To Use This Claude Gateway

This repository contains a portable `.claude/` gateway folder. Copy `.claude/` into the root of any fresh or existing project, then start Claude from that project.

The gateway is intentionally blank and dynamic for application stack, hosting, source control, CI/CD, runtime, package manager, deployment method, architecture, validation command, and security model. For database schema work, the final source of truth is the SQL Server-backed Schema MCP server named `schema`.

## First Prompt For A New Project

Use this prompt after copying `.claude/` into a project:

```text
This is a fresh or unknown project. Use .claude as the project gateway.

Read .claude/README.md and .claude/PROJECT-CONTEXT.md.
Read all applicable .claude/rules files.
Do not assume any tech stack, hosting provider, deployment model, source control provider, CI/CD pipeline, validation command, architecture, or runtime.
For database/schema work, always use the SQL Server-backed Schema MCP server named schema before referencing tables or columns.
Ask me every required question before doing any work.
Keep asking until the task, stack, database, deployment, validation, security, and success criteria are clear.
Do not write code yet.
Do not store secrets.
After I confirm answers, update .claude/PROJECT-CONTEXT.md with confirmed non-secret facts only.
```

## Gateway Commands

Invoke these as slash commands (for example `/schema-browse`) or by asking Claude to use the matching file in `.claude/commands/`:

- `fresh-project-start` - bootstrap a blank or unknown repository.
- `project-intake` - repeatable intake to confirm stack, database, deployment, and validation facts.
- `schema-browse` - inspect existing database metadata through Schema MCP.
- `schema-reuse-plan` - check for reusable tables before proposing new storage.
- `semantic-model-plan` - agree the feature's analytics questions, then shape the tables so they answer them and lift into a semantic model.
- `mcp-health-check` - confirm the `schema` MCP server is connected and authorized.
- `mcp-token-refresh` - refresh the schema MCP credential when its short-lived token expires.
- `database-task` - run database-backed work under the Schema MCP rules.
- `document-code` - add or audit structured documentation for a file or scope.
- `deployment-check` - review deployment readiness before delivering deployment work.
- `verified-closeout` - final validation, the gated knowledge-base update, and reporting before calling work done.
- `owner-override` - the gateway owner lifts one named rule for one named action; refused for anyone Claude cannot verify.
- `eod` - close out, review, or correct the day's status report (`/eod`, `/eod who`, `/eod team`, `/eod date <date>`).
- `security-check` - run the security review pass on demand, over the current change or a named target. It also runs automatically on every delivered code change; this command is for running it earlier or over something Claude did not write.

The bundled `ui-ux-design` skill is mandatory for UI work - it carries the approved design system (see UI And UX Work below). The bundled `semantic-data-model` skill is mandatory whenever a table is designed or changed - it carries the approved data-structure pattern (see Data Model And Analytics Work below).

## Rules Overview

Claude no longer loads the whole `.claude/rules/` folder up front. At session start it reads only `hard-stops.md` and `secret-handling.md`; the always-on safety invariants (ask-don't-assume, secret handling, Schema MCP, the approved UI system, the approved AI model catalog, Git-allowed but deploy/migrate-gated, automatic feedback logging, automatic EOD status reporting, and no "done" without validation evidence) are summarized inline in the root `CLAUDE.md`, so they stay in force without reading every file. Every other rule loads only when its task-gate fires: writing code that will be delivered loads code documentation, production readiness, and enterprise governance; database work loads Schema MCP; a UI screen loads the design skill and `ui-ux-quality.md`; Git/release, API, deployment, and knowledge-base work each load their own rule. Conditional rules apply only when their trigger matches:

- `ui-ux-quality.md` - the project has a user interface.
- `api-design.md` - a service interface is exposed or consumed.
- `resilience.md` - code calls a dependency across a process or network boundary.
- `data-governance.md` - data is stored beyond transient request state.
- `semantic-data-model.md` - a persisted data structure is designed or altered, or a data model, ERD, or a feature's analytics is planned.
- `operations-incident.md` - a production service runs.
- `ai-agent-governance.md` - the project ships runtime LLM or agent behavior.
- `ai-model-catalog.md` - the app calls an AI model at runtime, or a provider, model, or catalog screen changes.
- `owner-override.md` - someone asks to override, bypass, skip, or relax a rule.

## Overriding A Rule

The rules bind every user in every session, in every project the `.claude/` folder is copied into. Only the gateway owner can lift one, and only one named rule for one named action at a time, through `/owner-override`. The typical case is debugging: asking to see DDL directly rather than going through a Schema MCP proposal.

The test is agreement, not a single credential. Every identity the session exposes must belong to the owner, checked as hashes against `.claude/OVERRIDE-AUTHORITY.md`, which stores digests rather than values. One mismatch refuses, whatever else lines up.

The signed-in Claude account is the required anchor in every session, because it is always present and authenticated server-side rather than read from a file a user can edit. GitHub and the Schema MCP token identity act as vetoes: if either resolves and is not the owner's, the session is refused; if either cannot be read it is skipped, never counted as a pass. In a local session a personal key file on the owner's own machine (`~/.c2s/override.key`), kept outside the repository and never committed, is mandatory on top of the anchor. A session whose kind is unclear is treated as local.

Holding a credential is not the same as being the owner. A bearer token is a copyable string and signing in on someone else's machine leaves a working session behind, which is why no single credential is ever sufficient. `git config` identity and anything you state, type, or paste are rejected outright. Claude reports only whether authority was verified; it will not name the owner, the source, which entry matched, or which source dissented, and will not confirm a guess.

Any dissent, a missing key file in a local session, an unreadable anchor, or an authority file that is missing or empty means no override for anyone: Claude refuses, explains the compliant path, and does that work instead. A teammate's laptop cannot override regardless of which accounts are signed in on it. Diagnosing a teammate's problem needs no override; only lifting a rule does.

Under a verified override, output carries a banner, produced artifacts are risk-labeled, and the override is listed in the final report. An override changes what Claude may do, never what it may claim, so validation, approvals, and MCP verification are still reported exactly as they happened. Adding an entry to the authority list happens only inside an already-verified session; recovering a lost key is a human git edit under normal review, not something a session can request.

## What Claude Must Ask

Claude should ask enough questions to understand:

- what needs to be built, changed, fixed, reviewed, deployed, or investigated
- why the work is needed and what success means
- approved language, framework, runtime, frontend, backend, package manager, and architecture
- database scope, SQL Server schema source of truth, Schema MCP `schema` connectivity, and whether database-backed work is in scope
- source control provider, CI/CD pipeline, artifact flow, hosting target, deployment method, rollback, and monitoring
- environment variables using placeholders only
- security, authentication, authorization, data sensitivity, and compliance constraints
- validation commands and where they can run
- documentation and knowledge-base expectations
- who is on the team, for the `## Team` table in `.claude/PROJECT-CONTEXT.md`: each person's name, email, role (`TL` Technical Lead, `PL` Project Lead, `LD` Lead Developer, `TD` Tech Developer, `TA` Tech Associate), work start, work end, timezone, and the From and To dates of their period on the project. Claude reads this to label the daily EOD report and will not invent a colleague's row.
- the current sprint number and every sprint task, grouped by cluster, module, and feature, written into `.claude/PROJECT-CONTEXT.md` as a checkbox list (`- [ ]` / `- [x]`) - updated at the start of each sprint, since ClickUp requires signing in and Claude cannot open the link, so anything not written there does not exist for Claude
- for a UI, the App Definition: the app name, title-bar name, navigation tree (the features you place under the four fixed clusters - Workspace, Compliance, Application Administration, System Administration; use only the subset you need, and you cannot add another cluster), and the brand-assets destination path (`<BRAND_ASSETS_PATH>` - where the logo/favicon files live in the project; always asked, never chosen). The theme, tokens, fonts, and the asset files themselves are approved in the bundled `ui-ux-design` skill and are never asked.
- for any table it designs or changes, the analytics questions that data must answer, plus the Analytics And Semantic Model facts: whether a semantic model or BI layer consumes this data and who owns it, how reporting reads it, the reporting time zone and business-day boundary, the key convention, which conformed dimensions the platform already has, how long history and event rows are kept, and where the ERD and data-model write-up live. Claude drafts the question list and you confirm, cut, or add; it will not invent the questions and will not propose a shape before you have confirmed them.
- for a runtime AI model call, the AI Model Catalog facts: where the catalog lives, the providers and model ids in use, what `{{env.NAME}}` resolves against, the project-wide timeout and retry policy, the currency, and who may manage the catalog. The record shape, placeholder set, and engine behavior are approved in the bundled `ai-model-integration` skill and are not asked.
- whether conditional areas apply: a service interface, cross-boundary calls, stored data with retention or privacy needs, a running production service, shipped LLM/agent behavior, or a runtime AI model call

If any answer is missing, vague, conflicting, risky, or stale, Claude must stop and ask again before acting.

Every intake field, what it means, and what a good answer looks like is documented with examples in `developer-handbook/reference/PROJECT-CONTEXT-UNDERSTANDING.md`.

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
<MCP_SERVER_NAME>
<MCP_ALIAS>
<SCHEMA_MCP_URL>
<TOKEN>
<SECRET_VALUE>
```

## Database And MCP Work

Before any database-backed or schema-related work, Claude must:

1. Read `.claude/docs/MCP-USAGE.md`.
2. Read `.claude/rules/schema-mcp.md`.
3. Verify the SQL Server-backed Schema MCP server named `schema` is reachable.
4. Confirm the MCP usage rules back to the developer.
5. Verify tables and columns through `mcp__schema__list_tables` and `mcp__schema__describe_table`.
6. Stop if MCP is missing, unreachable, unauthorized, or not approved for the needed work.
7. Refresh the credential with `.claude/commands/mcp-token-refresh.md` when the short-lived token expires; the auth model, token command, and TTL are confirmed facts in `.claude/PROJECT-CONTEXT.md`. Prefer a self-refreshing headers helper so the token renews on reconnect, and never print the token or hardcode the MCP URL.

Expected Schema MCP tools:

- `mcp__schema__list_tables`
- `mcp__schema__describe_table`
- `mcp__schema__find_existing_tables_for_concept`
- `mcp__schema__list_pending_proposals`
- `mcp__schema__propose_table_change`
- `mcp__schema__get_proposal`

Useful schema prompts:

```text
Use .claude/commands/schema-browse.md and show me all database tables through Schema MCP.
```

```text
Use .claude/commands/schema-browse.md and describe <SCHEMA.TABLE> through Schema MCP.
```

```text
Use .claude/commands/schema-reuse-plan.md for this data concept: <CONCEPT>.
```

Claude must not invent table names, column names, relationships, indexes, constraints, schema details, or DDL.

## Data Model And Analytics Work

Whenever a table is designed or changed, `.claude/rules/semantic-data-model.md` is the authoritative rule and the bundled `.claude/skills/semantic-data-model/` skill is the approved pattern. It sits on top of the Schema MCP workflow above: every name is still verified live, every change still goes through the proposal flow, and nothing here permits DDL.

The reason it exists is that a screen and a report want different things. A screen needs the current state of one record, so a screen-shaped table stores the current state and overwrites it. Every analytical question is a count across rows and across time: how many contacts arrived, from which source, how many were enriched, how many failed to enrich and why, how many are recurring, how long each step took. If the source was never stored as a code, no query can group by source. If the status was overwritten, no query can count last month's transitions. If the failed enrichment attempt was only written to a log, no query can report a failure rate. That is not a reporting backlog, it is data that no longer exists, and no later query or migration recovers it.

So the design gains one step at the front. Claude drafts the analytics question set, you confirm it, and each question is traced to the table, column, or reference set that answers it. Anything the shape cannot answer is raised as a gap for you to decide on, never quietly dropped. What follows from the confirmed list: one declared grain per table (one sentence saying what a single row means), atomic rows rather than stored totals, ratios stored as their two parts, anything grouped by held as a codified code plus label reused from the platform's existing dimension, state changes and step outcomes written as rows with coded reasons, duplicates linked rather than deleted, a timezone-aware business event date that is not the audit column, single-column stable keys, and soft delete so a number already reported stays reproducible.

The change then ships what a semantic model is built from: the complete data structure, the entity relationships with cardinality and optionality, the ERD, and the hand-off that states each table's grain, each measure's unit, default aggregation, additivity, and one agreed definition in words, plus each dimension's hierarchy and whether it is conformed platform-wide. Metadata only; no row data and no PII, and it lands through the knowledge-base gate, with schema metadata travelling in the same change unit as the schema change.

It is deliberately proportionate. The confirmed question set sizes the structure, so a three-question feature gets three answers, and Claude will not add a date dimension, versioned history on every column, a snapshot table, or a star schema that no confirmed question needs. It will also not build a warehouse, a cube, an extract job, a second reporting store, or the dashboards; those are separate work with their own approval. Run `/semantic-model-plan` to do this in order: questions, reuse search, shape preview, then propose.

## UI And UX Work

When the project has a user interface, `.claude/rules/ui-ux-quality.md` is the authoritative standard, and the bundled `.claude/skills/ui-ux-design/` skill is the approved design system, delivered as written guidelines: the layout, both themes, the color combination, fonts, and icon style are specified value-by-value in the skill (all tokens in `reference/design-tokens.md`), and the brand assets (`assets/`: four per-theme logos and two `.ico` favicons) are the only files meant to be copied into the project. Every application reproduces the same look and feel.

The developer supplies only the app-specific values in `.claude/PROJECT-CONTEXT.md`: the app name, the browser title-bar name, the sidebar navigation tree, and the brand-assets destination path (`<BRAND_ASSETS_PATH>` - where the logo/favicon files live in the project, per the confirmed stack; Claude asks for it and never chooses the location, or the developer copies the files manually and records the path). Claude must not invent these - and must not ask about or vary the theme, colors, fonts, logos, or favicon, which are the approved standard.

Claude builds every screen from the one master shell (full-height sidebar, slim top bar over the canvas), keeps standing navigation in the sidebar under the four fixed clusters (Workspace, Compliance, Application Administration, System Administration - using only the ones the app needs, never adding another) at most three accordion levels deep, implements the approved token values in the project's own stack and styling system in both themes, swaps the per-theme logos and favicon with the theme switcher, and designs the empty, loading, and error states for every data-driven view. Claude applies the UI standard exactly the same way every time - the same archetypes, component primitives, status-role meanings, and iconography - and only the app identity, navigation tree, entities, and domain content vary; Claude never invents a one-off pattern.

Every list screen sorts and filters, and that is a requirement rather than a nice-to-have. Every orderable column sorts, the list opens on a stated default order, and a search and filter bar sits above it carrying a facet for each dimension the list is genuinely narrowed by. Both run over the whole result set, on the server when the list is paginated, so filtering finds the matches on page three instead of reordering the twenty-five rows on screen. Changing a filter or the sort returns to page one, the toolbar count and the pagination total both report the filtered number, and the sort, filters, and page sit in the URL so a refresh, the back button, or a link to a colleague reproduces the same view. A short read-only sub-table inside a detail page and the dashboard's recent-activity table are the two places neither is expected.

A labeled button has two looks and no others. Exactly one solid button per action group, the one action that commits, and one neutral secondary look - surface fill, one control border, ink text - for every other labeled action, so `Cancel`, `Back`, `Clear filters`, `Reset`, `Apply`, `Export`, `Duplicate`, and `Test Configuration` read the same on every screen; a borderless or outlined labeled button no longer exists. A group that only changes what is on screen, such as a filter bar or a toolbar of view controls, carries no solid button at all, and the page's solid action stays the archetype's primary CTA in the page header. A button's look never changes with state either: loading swaps the label for a spinner at the same width and nothing else, and a destructive secondary action such as a row's `Delete` keeps the identical shell and colors only its icon and label. Icon-only is chrome rather than a button, limited to a closed list by kind: a dismiss or close mark on something dismissable, a clear mark on a field or a chip, and a form repeater's per-row remove control. A component's own primitives sit outside the two looks and keep the look their own file defines - the shell's icon controls, a sortable column header, the pager, a row's expand chevron, a tab strip, a segmented control, and a toast's single inline action - and a current-item marker inside one of them, such as the active page or the active tab, is a marker rather than an action's emphasis.

Every control in a list's actions column carries its word beside its icon - `View`, `Edit`, `Delete` - as visible text rather than only an accessible name, and it keeps that word at every breakpoint, so a narrow screen scrolls the pinned actions column instead of falling back to bare icons. Past about three actions on a row, the first three stay labeled inline and the rest move behind one `More` control whose accessible name names the row; a detail header's action cluster and a bulk-selection bar work the same way. A row's `Delete` opens the confirmation, so it is a secondary control with a danger-colored label, and the solid danger button is the one in the dialog that commits.

Every toast appears at the top right, in one host offset below the top bar so it never covers the top-bar utilities, newest nearest the top edge. That is the only placement in the standard: an error and a success land in the same place, a narrow screen widens that same top edge rather than moving to another corner, and there is no per-screen or per-type option to move one.

A form reports an error once, where the error is. Each message sits inline under its own field, and a blocked submit is announced by one error toast saying how many fields need attention while focus and scroll move to the first invalid field. There is no error-summary card, because it repeated the same sentences the fields already carried and gave you two places to read them; an error toast persists until you dismiss it anyway. A form-level error that belongs to no field, such as a failed save or an unreachable service, gets one inline alert at the form foot instead, since that is the one thing no field can say.

Two rules about forms are worth knowing before you review a screen. Data entry is page-hosted: a create, edit, multi-step, or settings form is its own route or a form region on the current page, inside the shell, never a popup. A modal is for a decision that has to be answered now, and it carries at most the three or so fields that are the decision itself, so a dialog that has grown a fourth field has become a form and moves to a page.

And a step-by-step form never loses work. Its advance button reads `Continue`: it validates the step, saves it to the server as a draft, then moves on, so a closed tab, a flat battery, an expired session, or a different machine costs nothing already entered. Coming back resumes at the step you left with the earlier steps filled in, reachable from the list with a `Draft` badge. If the save fails, the step stays put with every value intact rather than pretending it saved. You decide where the draft lives, and Claude will ask: a separate draft table, or the record's own table with a draft state. A draft in the live table keeps one row and one id, but its required columns have to be nullable until completion and every query, count, and report has to filter the state; a separate table keeps the live constraints strict. Either way a draft is not a record, so it stays out of the default list and its counts, and out of every other list, export, and report, though the rows you see with the drafts filter on are counted like any other match, it never holds a credential or key, and how long an abandoned one is kept is a retention answer you give during intake.

## AI Model Work

When the app calls an AI model at runtime, `.claude/rules/ai-model-catalog.md` is the authoritative rule and the bundled `.claude/skills/ai-model-integration/` skill is the approved pattern. A model is data, not code: one catalog record holds the endpoint, method, header rows, typed body fields, response paths, price, and a reference to its credential, and one generic engine reads that record and makes the call. Adding, changing, or retiring a model is a configuration change with no code change and no per-provider branch.

The reason is the payload. Providers disagree about where the model id goes, what the output-token field is called, how deep the prompt sits, and where the answer comes back, and one provider's own product line moves the token field three times. Typed body fields are what keep the payload correct per model: a number stays an unquoted number, a boolean stays a boolean, and a structured field can carry a nested array such as a message list.

Two boundaries matter. The catalog owns the provider envelope up to the content path; whatever the model generated inside that block belongs to the calling code, because only the caller knows what it asked for, so Claude never adds content parsing or shape repair to the engine. And the API key lives in one masked field on the record itself, referenced as `{{api_key}}`, entered once, never re-rendered into a form, masked in any echoed request, and never exported or logged.

What you tell Claude: where the catalog lives (a database table, a versioned config file, or the settings provider), which providers and model ids are in use, what `{{env.NAME}}` resolves against, the project-wide timeout and retry policy, the currency, and who may manage the catalog. Record those in `.claude/PROJECT-CONTEXT.md` under AI Model Catalog. A database-backed catalog goes through the Schema MCP proposal workflow like any other table.

What Claude will not do: hardcode an endpoint, payload, response shape, or price; keep a second call path for a second provider; re-render or log a saved API key; report a cost of zero when the price or the token count is unknown; or let a record become callable before a real test call passed on its current values. The two catalog screens sit inside the Integrations group under System Administration, restricted to the system admin tier, follow the approved archetypes and the test-before-save gate so `Save` appears only after `Test call` succeeds, and keep every field in a form section on one row. Entries are named after the provider and model (`OpenAI GPT-5.6`, `Claude Opus 5`), not after the job they do.

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

For copy-ready prompts that run these Git workflows (sync, stash, branch, PR, promotion, release), see `developer-handbook/prompts/GIT_PROMPTS.md`.

## Knowledge Base Updates

After each completed implementation, Claude asks you whether to create or update the knowledge base now or defer it. Knowledge-base files are written only after:

1. implementation is complete
2. validation ran, or the limitation is documented
3. you have verified and validated the work and explicitly approved the update

Claude never writes knowledge-base files without your explicit approval; if you defer, it records nothing and notes the deferral in the final report.

When you approve, Claude asks whether the work is a solution, a module, or a feature and where it sits in the hierarchy - a solution has multiple modules, and each module can have multiple features. You classify it; Claude does not. A feature write-up covers logical flows and workflows, data models and tables, frontend, backend, API routing, and system architecture; solution and module READMEs hold the overview and link their children.

The knowledge-base README is the index: every knowledge-base file is linked from it, so you can jump straight to any write-up. The knowledge base is living documentation - as the project is built, Claude updates the affected write-ups and the index (behind the same approval gate) so they never go stale against shipped behavior.

For intake facts, update only `.claude/PROJECT-CONTEXT.md` with confirmed non-secret information.

## Code Review And Security Review

You do not ask for these. Every code change Claude delivers gets both passes before it is called done, in every project the gateway is copied into. `.claude/rules/review-gates.md` is the authoritative rule.

The `code-reviewer` subagent asks whether the change is correct, complete, in your project's patterns, and no larger than what you asked for. The `security-reviewer` subagent asks whether it can be abused, whether it leaks, and whether it trusts something it should not, covering authentication and authorization bypass, injection, secrets, PII exposure, insecure defaults, and dependency risk, plus extra lists when the change touches AI/LLM features, cryptography, governed data, or agent lifecycle.

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

At the first substantive prompt Claude opens or creates today's report at `docs/eod/eod-date-<D><Month><YYYY>.md` (for example `eod-date-17August2026.md`) and asks two short things in one message: whose EOD this is, and what is on your list today. Answer or ignore it and keep working; it never blocks. After that it updates the report as each task reaches an outcome, not on every prompt. One file per day, one `## Developer Name (ROLE)` section per developer, four summary lines (Planned, Progress, Pending / Next, Blockers / Risks) sitting over a task table.

Claude asks whose session it is rather than working it out, because on a shared Claude plan there is nothing to work out. Everyone signs in as the same account, shows the same account email, and reaches the same GitHub login, and `git config` is a plain text file that is often set up identically across a team. All of it identifies the team, none of it identifies a person. So you type the name, and Claude matches it to the `## Team` table in `.claude/PROJECT-CONTEXT.md` to pick up your email, role code, and section position.

That table is the roster, filled during intake alongside every other project fact: name, email, role (`TL` Technical Lead, `PL` Project Lead, `LD` Lead Developer, `TD` Tech Developer, `TA` Tech Associate), work start, work end, timezone, and the From and To dates of your period on the project. The working window is your stated schedule, a static fact; Claude never measures or records the hours you actually worked. If the table is still empty when a session needs it, Claude asks for your row and fills it, then asks you to complete the rest of the team when you can. It will not invent a colleague's name, role, hours, or dates.

From and To decide who appears in a given day's report. You are seeded into a report only when its date falls inside your window, so someone joining mid-project starts appearing on their first day and not before, and earlier reports are never rewritten to imply they were there. When someone leaves or moves to another project, fill their To date: they stop appearing the next day, every report they were part of stays exactly as it was, and their row stays in the table so those past sections remain attributable. If they come back, clear To rather than adding a second row.

The answer is held for that session only and is never written to project context, a settings file, or any global setting. It is attribution, not identity: a typed name cannot verify an override, unlock a rule, or stand in for an approval, and that is exactly why typing it is good enough here.

Every task carries one status from a closed list: `Not Started`, `In Progress`, `Partially Done`, `For Review`, `In Review`, `Blocked`, `Done`, `Dropped`. `Blocked` always states why. `Done` needs the same evidence as any other completion claim, so work that was written but never validated is `Partially Done`, not `Done`. The list is closed so a week of reports reads consistently; if your project genuinely needs another status, add it to the table in `.claude/rules/eod-reporting.md` rather than writing prose into a row.

What it will not do: record hours, session counts, prompt counts, or activity levels; put customer data or production values in a report; or claim progress it did not observe. Anything it reconstructed rather than watched is marked `(inferred)`, and your account of your own day always wins.

The report is written all day and committed at close. Run `/eod` to bring it up to date and commit it, `/eod team` to see everyone's day and who has not reported, `/eod who` when a shared machine is logging to the wrong person, and `/eod date <date>` to correct an earlier day.

This is not the session handoff below. An EOD report is a short status for whoever reads the day's progress; a handoff is technical continuity for the next session. When you finish a day with work still in flight, write both.

## Session Handoff

When you want the work to survive a session boundary, send one of the copy-ready prompts in `developer-handbook/prompts/DAY_HANDOFF_PROMPT.md`. Claude rebuilds the state from durable evidence (the working-memory log, Git history, open PRs, feedback logs, the sprint list, and the current session) and writes a timestamped handoff file - done, in progress, pending, blockers, and the single first action to resume - into a shared `handoffs/` folder, so the next session can point to that one file and pick up. Each handoff is its own `<timestamp>_<short-topic>.md` file so many people's handoffs coexist without overwriting each other, and you resume by naming the exact file (there is no "latest" shortcut). Three variants are provided: an end-of-day handoff that covers the whole day, a mid-work handoff for when you just want to continue in a fresh session on the same machine without explaining the task again, and a developer-to-developer handoff for when another developer will pull the work and finish it on their own device (commit and push first, so nothing is stranded on your machine). Claude cannot read past chat sessions, so keep your working-memory log current; the handoff is only as complete as the trace the session left behind.

## Account Handover

When the Claude account itself changes - the usage allowance is exhausted, or the account is being rotated or reassigned - use the prompts in `developer-handbook/prompts/ACCOUNT_HANDOVER_PROMPT.md`. The sign-in belongs to the Claude Code install rather than to one chat, so switching accounts takes every open session with it at once. The flow is: bring each active session to a clean boundary and push, paste the capture prompt in every chat you are transferring (one handover file each), paste the index prompt once to tie the batch together in a single file, switch and confirm the active account, then point a fresh session at that index. On the new account, read the index and only the one handover file you are about to work on, use one fresh session per file, and keep Sonnet as the default - you switched because an allowance ran out, so the resume should not burn the next one. If the limit hits before you can capture anything, push what is on disk and use the rebuild prompt in that file, which reconstructs state from the repository and asks you to fill the gaps instead of guessing.

## Completion Checklist

A task is not complete until Claude reports:

- what changed
- files changed
- validation commands and results
- MCP tools used, if any
- database/schema impact
- deployment impact
- knowledge-base/table-dictionary status
- documentation/docstring coverage for code changes
- security impact
- UI states covered (success, empty, loading, error, small-screen) for UI work
- for data-structure work: the confirmed analytics question set and any question the shape does not answer, the grain of every table touched, the measures with units and additivity, the dimensions reused or added, the history and outcome capture, and where the entity relationships, ERD, and semantic hand-off landed
- for AI model work: which catalog records changed, the test-call result that gated the save, token and cost impact, and confirmation that no credential value, prompt text, or response content was printed
- the review passes: that the code review and security review both ran and over which files, every finding with its severity, how each `Critical` and `High` was resolved, and explicitly that a pass ran clean when it did
- the EOD report line: the report path, the developer it was logged for, and any task whose status changed
- risks, assumptions, and manual steps, including manual server-side steps the developer must perform

Claude must not claim work is bug free or complete unless validation evidence exists, or the exact validation limitation and follow-up steps are documented.
